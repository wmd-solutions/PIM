<?php
declare(strict_types=1);
/**
 * Fájl helye: php/handlers/pdf_merge_handler.php
 * Funkció: PDF fájlok feltöltésének fogadása és az összefűző Job indítása.
 * Módosítás dátuma: 2026. április 02. 12:20:00
 */

class PdfMergeHandler {
    
    /**
     * @return void
     */
    public function handle(): void {
        header('Content-Type: application/json');
        global $lang;

        $token = $_POST['g_recaptcha_response'] ?? '';
        if (!RecaptchaService::verify((string)$token)) {
            echo json_encode(['status' => 'error', 'message' => 'reCAPTCHA ellenőrzés sikertelen.']);
            return;
        }

        if (!isset($_FILES['pdf_files']) || !is_array($_FILES['pdf_files']['name'])) {
            echo json_encode(['status' => 'error', 'message' => 'Nem érkeztek fájlok.']);
            return;
        }

        $files = $_FILES['pdf_files'];
        $fileCount = count($files['name']);

        if ($fileCount < 2) {
            echo json_encode([
                'status' => 'error', 
                'message' => $lang['err_pdf_min_two'] ?? 'Legalább két PDF fájl szükséges az összefűzéshez!'
            ]);
            return;
        }

        $uploadedPaths = [];
        for ($i = 0; $i < $fileCount; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $tmpName = $files['tmp_name'][$i];
                $originalName = basename($files['name'][$i]);
                $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

                if ($ext !== 'pdf' || mime_content_type($tmpName) !== 'application/pdf') {
                    $errorMsg = isset($lang['err_pdf_invalid']) ? sprintf($lang['err_pdf_invalid'], $originalName) : 'Érvénytelen fájlformátum.';
                    echo json_encode(['status' => 'error', 'message' => $errorMsg]);
                    return;
                }

                $newPath = UPLOADS_PATH . '/' . uniqid('pdf_') . '_' . $i . '.pdf';
                if (move_uploaded_file($tmpName, $newPath)) {
                    $uploadedPaths[] = $newPath;
                }
            }
        }

        if (count($uploadedPaths) !== $fileCount) {
            echo json_encode(['status' => 'error', 'message' => 'Hiba történt a fájlok szerverre mentésekor.']);
            return;
        }

        $jobId = uniqid('job_');
        $baseName = 'Merged_Document_' . date('Ymd_His');
        
        $jobData = [
            'id' => $jobId,
            'type' => 'pdf_merge',
            'status' => 'pending', 
            'created_at' => time(),
            'input_files' => $uploadedPaths,
            'filename_base' => $baseName,
            'download_name' => $baseName . '.pdf'
        ];
        
        file_put_contents(JOBS_PATH . '/' . $jobId . '.json', json_encode($jobData));

        $scriptPath = BASE_PATH . '/worker.php';
        $cmd = PHP_BIN . " " . escapeshellarg($scriptPath) . " " . escapeshellarg($jobId) . " > /dev/null 2>&1 &";
        
        writeLog("Worker indítása (PDF Merge)", "INFO", ['cmd' => $cmd]);
        exec($cmd);

        echo json_encode([
            'status' => 'started', 
            'job_id' => $jobId,
            'message' => 'Feltöltés sikeres, összefűzés elindítva...'
        ]);
    }
}