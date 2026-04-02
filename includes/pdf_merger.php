<?php
declare(strict_types=1);

/**
 * Fájl helye: php/includes/pdf_merger.php
 * Funkció: PDF fájlok memóriabarát összefűzése pdftk CLI eszközzel.
 * Módosítás dátuma: 2026. április 02. 12:15:00
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/logger.php';

class PdfMerger {
    
    /**
     * Összefűz egy tömbnyi PDF fájlt egyetlen kimeneti fájllá.
     * * @param array<string> $inputFiles Bemeneti PDF fájlok abszolút útvonalai.
     * @param string $outputFile Kimeneti (eredmény) PDF fájl útvonala.
     * @return bool Sikeresség.
     * @throws \Exception Ha hiba történik a folyamat során.
     */
    public function merge(array $inputFiles, string $outputFile): bool {
        if (count($inputFiles) < 2) {
            throw new \Exception("Legalább két fájl szükséges az összefűzéshez.");
        }

        $bin = defined('PDFTK_BIN') ? PDFTK_BIN : 'pdftk';
        
        // Fájl útvonalak biztonságossá tétele shell parancshoz
        $escapedFiles = array_map('escapeshellarg', $inputFiles);
        
        // Parancs: pdftk file1.pdf file2.pdf cat output final.pdf
        $cmd = $bin . ' ' . implode(' ', $escapedFiles) . ' cat output ' . escapeshellarg($outputFile) . ' 2>&1';
        
        writeLog("PDF Összefűzés parancs indítása", "DEBUG", ['cmd' => $cmd]);
        
        $output = [];
        $returnVar = 0;
        exec($cmd, $output, $returnVar);
        
        if ($returnVar !== 0) {
            $err = implode("\n", $output);
            writeLog("PDFTK futtatási hiba", "ERROR", ['log' => $err]);
            throw new \Exception("Hiba a PDF-ek összefűzésekor (Exit kód: $returnVar). Részletek a naplóban.");
        }
        
        if (!file_exists($outputFile) || filesize($outputFile) === 0) {
             throw new \Exception("A kimeneti PDF fájl nem jött létre vagy üres lett.");
        }
        
        return true;
    }
}