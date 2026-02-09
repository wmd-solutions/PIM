<?php
/**
 * Fájl helye: php/includes/converter.php
 * Funkció: Konvertálás és fájlmentés a temp mappába (Bővített formátumlista).
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/logger.php';

function processConversion($url, $format, $filenameBase, $useDyslexic = false, $directDownload = true) {
    // Calibre igénye miatt kiterjesztéssel hozzuk létre a temp fájlt
    $tempHandle = tempnam(TEMP_PATH, 'pim_src_');
    if (!$tempHandle) throw new Exception("Nem sikerült temp fájlt létrehozni.");
    
    $sourceFile = $tempHandle . '.epub';
    if (!rename($tempHandle, $sourceFile)) {
        unlink($tempHandle);
        throw new Exception("Nem sikerült a temp fájl előkészítése.");
    }

    $outputFile = $sourceFile . '.' . $format;
    
    // Belső fájlnév
    $internalFilename = basename($outputFile);

    writeLog("Feldolgozás indítása", "INFO", [
        'mode' => $directDownload ? 'Direct' : 'Worker',
        'url' => $url, 
        'format' => $format, 
        'target' => $internalFilename
    ]);
    
    try {
        // 1. Letöltés
        writeLog("Letöltés...", "DEBUG");
        $fp_source = @fopen($url, 'rb');
        if (!$fp_source) throw new Exception("Nem nyitható meg az URL: $url");
        
        $fp_dest = fopen($sourceFile, 'w+b');
        if (!$fp_dest) throw new Exception("Nem írható a temp fájl.");
        
        $bytes = stream_copy_to_stream($fp_source, $fp_dest);
        fclose($fp_source);
        fclose($fp_dest);
        
        if (!$bytes) throw new Exception("Üres fájl érkezett.");
        
        // 2. Konvertálás
        // Bővített lista: mobi, azw3, pdf, docx, fb2, txt, rtf
        $convertFormats = ['mobi', 'azw3', 'pdf', 'docx', 'fb2', 'txt', 'rtf'];
        
        if ($useDyslexic || in_array($format, $convertFormats)) {
            convertToCalibreBased($sourceFile, $outputFile, $format, $useDyslexic);
        } else {
            // Csak átnevezés (pl. epub -> epub, ha nincs dyslexic mód)
            if (!rename($sourceFile, $outputFile)) {
                throw new Exception("Fájl átnevezési hiba.");
            }
        }

        // 3. Befejezés
        if (file_exists($outputFile) && filesize($outputFile) > 0) {
            writeLog("Fájl elkészült", "INFO", ['file' => $internalFilename]);
            @unlink($sourceFile);
            return $internalFilename;
        } else {
            throw new Exception("A kimeneti fájl nem jött létre.");
        }

    } catch (Exception $e) {
        writeLog("HIBA", "ERROR", ['msg' => $e->getMessage()]);
        @unlink($sourceFile);
        if (file_exists($outputFile)) @unlink($outputFile);
        throw $e;
    }
}

/**
 * Betűtípus útvonalának meghatározása és normalizálása CSS számára.
 */
function getFontPath($filename) {
    $localPath = BASE_PATH . '/fonts/OpenDyslexic/' . $filename;
    
    if (file_exists($localPath)) {
        $realPath = realpath($localPath);
        return str_replace('\\', '/', $realPath);
    }
    
    return "https://raw.githubusercontent.com/antijingoist/open-dyslexic/master/open-dyslexic/" . $filename;
}

function convertToCalibreBased($source, $dest, $format, $useDyslexic = false) {
    $binary = defined('CALIBRE_BIN') ? CALIBRE_BIN : 'ebook-convert';
    
    if ($binary === 'ebook-convert') {
        $commonPaths = ['/usr/bin/ebook-convert', '/usr/local/bin/ebook-convert', '/opt/calibre/ebook-convert'];
        foreach ($commonPaths as $path) {
            if (file_exists($path)) {
                $binary = $path;
                break;
            }
        }
    }

    $cmdParts = [$binary, escapeshellarg($source), escapeshellarg($dest)];
    $tempCssFile = null;

    if ($useDyslexic) {
        $fontR = getFontPath('OpenDyslexic-Regular.otf');
        $fontB = getFontPath('OpenDyslexic-Bold.otf');
        $fontI = getFontPath('OpenDyslexic-Italic.otf');
        $fontBI = getFontPath('OpenDyslexic-BoldItalic.otf');

        $css = "
            @font-face { 
                font-family: 'OpenDyslexic'; 
                src: url('{$fontR}') format('opentype'); 
                font-weight: normal; 
                font-style: normal; 
            }
            @font-face { 
                font-family: 'OpenDyslexic'; 
                src: url('{$fontB}') format('opentype'); 
                font-weight: bold; 
                font-style: normal; 
            }
            @font-face { 
                font-family: 'OpenDyslexic'; 
                src: url('{$fontI}') format('opentype'); 
                font-weight: normal; 
                font-style: italic; 
            }
            @font-face { 
                font-family: 'OpenDyslexic'; 
                src: url('{$fontBI}') format('opentype'); 
                font-weight: bold; 
                font-style: italic; 
            }
            
            body, p, div, span, h1, h2, h3, h4, h5, h6, td, li, a { 
                font-family: 'OpenDyslexic', sans-serif !important; 
                line-height: 150%; 
            }
        ";

        $tempCssFile = tempnam(TEMP_PATH, 'dys_css_') . '.css';
        file_put_contents($tempCssFile, $css);
        $cmdParts[] = "--extra-css " . escapeshellarg($tempCssFile);
        $cmdParts[] = "--embed-all-fonts";
    } elseif ($format === 'azw3') {
        $cmdParts[] = "--embed-all-fonts";
    }

    // TXT kódolás beállítása (ajánlott)
    if ($format === 'txt') {
        $cmdParts[] = "--input-encoding utf-8";
    }

    $cmd = implode(' ', $cmdParts);
    writeLog("CMD: " . $cmd, "DEBUG");

    $output = [];
    $returnVar = 0;
    
    $envPath = "export PATH=\$PATH:/usr/local/bin:/usr/bin:/bin:/opt/calibre; ";
    exec($envPath . $cmd . " 2>&1", $output, $returnVar);

    if ($tempCssFile && file_exists($tempCssFile)) @unlink($tempCssFile);

    if ($returnVar !== 0) {
        $err = implode("\n", array_slice($output, -20));
        writeLog("Calibre hiba", "ERROR", ['log' => $err]);
        throw new Exception("Konvertálási hiba (Exit: $returnVar). Részletek a logban.");
    }
}

// Módosítás dátuma: 2025. december 13. 22:55:00