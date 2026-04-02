<?php
/**
 * Fájl helye: php/worker.php
 * Funkció: Háttérben futó konvertálási és fájlfeldolgozási folyamat (CLI).
 * Indítása: php worker.php {JOB_ID}
 * Módosítás dátuma: 2026. április 02. 14:00:00
 */

if (php_sapi_name() !== 'cli') {
    die('Access Denied');
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/logger.php';

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

$jobData = json_decode(file_get_contents($jobFile), true);
$jobData['status'] = 'processing';
$jobData['started_at'] = time();
file_put_contents($jobFile, json_encode($jobData));

$jobType = $jobData['type'] ?? 'ebook_convert';
writeLog("Worker indult", "INFO", ['job_id' => $jobId, 'type' => $jobType]);

try {
    if ($jobType === 'ebook_convert') {
        require_once __DIR__ . '/includes/converter.php';
        $resultFile = processConversion(
            $jobData['url'],
            $jobData['format'],
            $jobData['filename_base'],
            $jobData['use_dyslexic'],
            false
        );
        
        $jobData['status'] = 'completed';
        $jobData['result_file'] = $resultFile;

    } elseif ($jobType === 'pdf_tool') {
        
        require_once __DIR__ . '/includes/pdf_toolkit.php';
        $toolkit = new PdfToolkit();
        
        $toolName = $jobData['tool_type'];
        $outputFilename = $jobData['download_name']; 
        $outputPath = TEMP_PATH . '/' . $outputFilename;
        $inputs = $jobData['input_files'];
        $params = $jobData['params'];

        // Dinamikus elágazás a szerszámokhoz
        switch ($toolName) {
            case 'merge':
                $toolkit->merge($inputs, $outputPath);
                break;
            case 'split':
                $toolkit->split($inputs[0], $params['pages'], $outputPath);
                break;
            case 'encrypt':
                $toolkit->encrypt($inputs[0], $params['password'], $outputPath);
                break;
            case 'rotate':
                $toolkit->rotate($inputs[0], $params['rotation'], $outputPath);
                break;
            case 'watermark':
                $toolkit->watermark($inputs, $outputPath);
                break;
            case 'repair':
                $toolkit->repair($inputs[0], $outputPath);
                break;
            default:
                throw new Exception("Ismeretlen PDF eszköz: $toolName");
        }
        
        $jobData['status'] = 'completed';
        $jobData['result_file'] = $outputFilename;
        
        // Eredeti feltöltött fájlok törlése siker esetén
        if (is_array($inputs)) {
            foreach ($inputs as $file) @unlink($file);
        }

    } else {
        throw new Exception("Ismeretlen Job típus: $jobType");
    }

    $jobData['completed_at'] = time();
    writeLog("Worker sikeres befejezés", "INFO", ['job_id' => $jobId, 'file' => $jobData['result_file']]);

} catch (Exception $e) {
    $jobData['status'] = 'error';
    $jobData['message'] = $e->getMessage();
    
    // Takarítás hiba esetén is (PDF módoknál)
    if ($jobType === 'pdf_tool' && isset($jobData['input_files']) && is_array($jobData['input_files'])) {
        foreach ($jobData['input_files'] as $file) @unlink($file);
    }
    
    writeLog("Worker hiba", "ERROR", ['job_id' => $jobId, 'msg' => $e->getMessage()]);
}

file_put_contents($jobFile, json_encode($jobData));
exit(0);