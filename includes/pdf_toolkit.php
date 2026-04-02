<?php
declare(strict_types=1);
/**
 * Fájl helye: php/includes/pdf_toolkit.php
 * Funkció: PDF manipuláció a pdftk parancssori eszközzel (Minden funkció).
 * Módosítás dátuma: 2026. április 02. 14:00:00
 */

require_once __DIR__ . '/logger.php';

class PdfToolkit {
    private string $bin;

    public function __construct() {
        $this->bin = defined('PDFTK_BIN') ? PDFTK_BIN : 'pdftk';
    }

    private function executeCommand(string $cmd): void {
        writeLog("PDFTK Exec", "DEBUG", ['cmd' => $cmd]);
        $output = [];
        $returnVar = 0;
        exec($cmd . " 2>&1", $output, $returnVar);

        if ($returnVar !== 0) {
            $err = implode("\n", $output);
            writeLog("PDFTK Error", "ERROR", ['log' => $err]);
            throw new \Exception("Hiba a PDF feldolgozásakor (Kód: $returnVar).");
        }
    }

    public function merge(array $inputFiles, string $outputFile): void {
        $escapedFiles = array_map('escapeshellarg', $inputFiles);
        $cmd = $this->bin . ' ' . implode(' ', $escapedFiles) . ' cat output ' . escapeshellarg($outputFile);
        $this->executeCommand($cmd);
    }

    public function split(string $inputFile, string $pages, string $outputFile): void {
        if (!empty($pages)) {
            // Megadott oldalak kivonása
            $cmd = $this->bin . ' ' . escapeshellarg($inputFile) . ' cat ' . escapeshellarg($pages) . ' output ' . escapeshellarg($outputFile);
            $this->executeCommand($cmd);
        } else {
            // Összes oldal szétvágása (Burst) és csomagolása ZIP-be
            $tempDir = TEMP_PATH . '/' . uniqid('burst_');
            mkdir($tempDir, 0777, true);
            
            $cmd = $this->bin . ' ' . escapeshellarg($inputFile) . ' burst output ' . escapeshellarg($tempDir . '/page_%04d.pdf');
            $this->executeCommand($cmd);

            // ZIP létrehozása
            $zip = new \ZipArchive();
            if ($zip->open($outputFile, \ZipArchive::CREATE) !== true) {
                throw new \Exception("Nem sikerült a ZIP fájlt létrehozni.");
            }
            $files = glob($tempDir . '/*.pdf');
            foreach ($files as $file) {
                $zip->addFile($file, basename($file));
            }
            $zip->close();

            // Takarítás
            foreach ($files as $file) @unlink($file);
            @unlink($tempDir . '/doc_data.txt'); // A pdftk burst generálja
            @rmdir($tempDir);
        }
    }

    public function encrypt(string $inputFile, string $password, string $outputFile): void {
        if (empty($password)) throw new \Exception("A titkosításhoz jelszó szükséges!");
        $cmd = $this->bin . ' ' . escapeshellarg($inputFile) . ' output ' . escapeshellarg($outputFile) . ' user_pw ' . escapeshellarg($password) . ' allow printing';
        $this->executeCommand($cmd);
    }

    public function rotate(string $inputFile, string $direction, string $outputFile): void {
        $validDirs = ['east', 'west', 'south'];
        if (!in_array($direction, $validDirs)) $direction = 'east';
        
        $cmd = $this->bin . ' ' . escapeshellarg($inputFile) . ' cat 1-end' . $direction . ' output ' . escapeshellarg($outputFile);
        $this->executeCommand($cmd);
    }

    public function watermark(array $inputFiles, string $outputFile): void {
        $main = $inputFiles['main'];
        $stamp = $inputFiles['stamp'];
        // A multistamp az előtérbe nyomja a vízjelet minden oldalra
        $cmd = $this->bin . ' ' . escapeshellarg($main) . ' multistamp ' . escapeshellarg($stamp) . ' output ' . escapeshellarg($outputFile);
        $this->executeCommand($cmd);
    }

    public function repair(string $inputFile, string $outputFile): void {
        // Puszta átírás újraépíti az xref táblát és a struktúrát
        $cmd = $this->bin . ' ' . escapeshellarg($inputFile) . ' output ' . escapeshellarg($outputFile);
        $this->executeCommand($cmd);
    }
}