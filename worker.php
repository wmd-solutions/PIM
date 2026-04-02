<?php
/**
 * Fájl helye: php/worker.php
 * Funkció: Háttérben futó konvertálási és fájlfeldolgozási folyamat (CLI).
 * Indítása: php worker.php {JOB_ID}
 * Módosítás dátuma: 2026. április 02. 12:15:00
 */

// Csak parancssorból futtatható
if (php_sapi_name() !== 'cli') {
    die('Access Denied');
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/logger.php';
require_once __DIR__ . '/includes/converter.php';

// Argumentumok ellenőrzése
if ($argc < 2) {
    writeLog("Worker indítása sikertelen: Hiányzó Job ID", "ERROR");
    exit(1);
}

$jobId = $argv[1];
$jobFile = JOBS_PATH . '/' . $jobId . '.json';

if (!file_exists($jobFile)) {
    writeLog("Worker hiba: Job fájl nem található ($jobId)", "ERROR");
    exit(1);
}

// Job adatok betöltése
$jobData = json_decode(file_get_contents($jobFile), true);
$jobData['status'] = 'processing';
$jobData['started_at'] = time();
file_put_contents($jobFile, json_encode($jobData));

$jobType = isset($jobData['type']) ? $jobData['type'] : 'ebook_convert'; // Visszafelé kompatibilitás
writeLog("Worker indult", "INFO", ['job_id' => $jobId, 'type' => $jobType]);

try {
    if ($jobType === 'ebook_convert') {
        // --- 1. DIA KÖNYV KONVERTÁLÁS LOGIKÁJA ---
        $resultFile = processConversion(
            $jobData['url'],
            $jobData['format'],
            $jobData['filename_base'],
            $jobData['use_dyslexic'],
            false // Fontos: NE írjon kimenetet!
        );
        
        $jobData['status'] = 'completed';
        $jobData['result_file'] = $resultFile;

    } elseif ($jobType === 'pdf_merge') {
        // --- 2. PDF ÖSSZEFŰZÉS LOGIKÁJA ---
        require_once __DIR__ . '/includes/pdf_merger.php';
        $merger = new PdfMerger();
        
        $outputFilename = $jobData['filename_base'] . '.pdf';
        $outputPath = TEMP_PATH . '/' . $outputFilename;
        
        $success = $merger->merge($jobData['input_files'], $outputPath);
        
        if ($success) {
            $jobData['status'] = 'completed';
            $jobData['result_file'] = $outputFilename;
        }
        
        // Takarítás: A feltöltött eredeti PDF fájlok azonnali törlése
        if (isset($jobData['input_files']) && is_array($jobData['input_files'])) {
            foreach ($jobData['input_files'] as $uploadedFile) {
                if (file_exists($uploadedFile)) @unlink($uploadedFile);
            }
        }
    } else {
        throw new Exception("Ismeretlen Job típus: $jobType");
    }

    $jobData['completed_at'] = time();
    writeLog("Worker sikeres befejezés", "INFO", ['job_id' => $jobId, 'file' => $jobData['result_file']]);

} catch (Exception $e) {
    // Hiba: Frissítjük a Job állapotát
    $jobData['status'] = 'error';
    $jobData['message'] = $e->getMessage();
    
    // Takarítás hiba esetén is (PDF Merge esetén)
    if ($jobType === 'pdf_merge' && isset($jobData['input_files'])) {
        foreach ($jobData['input_files'] as $uploadedFile) {
            if (file_exists($uploadedFile)) @unlink($uploadedFile);
        }
    }
    
    writeLog("Worker hiba", "ERROR", ['job_id' => $jobId, 'msg' => $e->getMessage()]);
}

// Job fájl mentése
file_put_contents($jobFile, json_encode($jobData));

exit(0);