<?php
/**
 * Fájl helye: php/worker.php
 * Funkció: Háttérben futó konvertálási folyamat (CLI).
 * Indítása: php worker.php {JOB_ID}
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

writeLog("Worker indult", "INFO", ['job_id' => $jobId]);

try {
    // Konvertálás indítása (directDownload = false, hogy csak fájlnevet kapjunk)
    $resultFile = processConversion(
        $jobData['url'],
        $jobData['format'],
        $jobData['filename_base'],
        $jobData['use_dyslexic'],
        false // Fontos: NE írjon kimenetet!
    );

    // Siker: Frissítjük a Job állapotát
    $jobData['status'] = 'completed';
    $jobData['result_file'] = $resultFile; // A tempben lévő fájl neve
    $jobData['completed_at'] = time();
    
    writeLog("Worker sikeres befejezés", "INFO", ['job_id' => $jobId, 'file' => $resultFile]);

} catch (Exception $e) {
    // Hiba: Frissítjük a Job állapotát
    $jobData['status'] = 'error';
    $jobData['message'] = $e->getMessage();
    
    writeLog("Worker hiba", "ERROR", ['job_id' => $jobId, 'msg' => $e->getMessage()]);
}

// Job fájl mentése
file_put_contents($jobFile, json_encode($jobData));

exit(0);
// Módosítás dátuma: 2025. december 13. 14:30:00