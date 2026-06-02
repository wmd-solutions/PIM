<?php
declare(strict_types=1);
/**
 * Fájl helye: php/handlers/pdf_merge_handler.php
 * Funkció: PDF fájlok részleges feltöltésének fogadása és az összefűző Job indítása (Biztonságos és batch-barát).
 * Módosítás dátuma: 2026. június 02. 11:24:00
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

        $subAction = $_POST['sub_action'] ?? '';

        // --- 1. CSOPORTOS RÉSZFELTÖLTÉSEK FOGADÁSA ÉS ELMENTÉSE ---
        if ($subAction === 'upload_batch') {
            $this->handleBatchUpload();
            return;
        }

        // --- 2. VÉGLEGES ÖSSZEFŰZÉSI FOLYAMAT ELINDÍTÁSA ---
        $preUploadedFilesJson = $_POST['pre_uploaded_files'] ?? '';
        if (empty($preUploadedFilesJson)) {
            echo json_encode(['status' => 'error', 'message' => 'Hiányzó fájlok az összefűzéshez.']);
            return;
        }

        $uploadedPaths = json_decode($preUploadedFilesJson, true);
        if (!is_array($uploadedPaths) || count($uploadedPaths) < 2) {
            echo json_encode([
                'status' => 'error', 
                'message' => $lang['err_pdf_min_two'] ?? 'Legalább két PDF fájl szükséges az összefűzéshez!'
            ]);
            return;
        }

        // Szigorú korlát ellenőrzése
        if (count($uploadedPaths) > 300) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Biztonsági hiba: Legfeljebb 300 fájl fűzhető össze egyszerre!'
            ]);
            return;
        }

        // Szigorú útvonal-ellenőrzés (Path traversal megelőzése)
        $realUploadsPath = realpath(UPLOADS_PATH);
        if ($realUploadsPath === false) {
            echo json_encode(['status' => 'error', 'message' => 'Szerveroldali hiba: Átmeneti könyvtár nem érhető el.']);
            return;
        }

        foreach ($uploadedPaths as $path) {
            $realPath = realpath($path);
            if ($realPath === false || !file_exists($realPath)) {
                echo json_encode(['status' => 'error', 'message' => 'A feltöltött fájl nem található: ' . basename($path)]);
                return;
            }
            if (strpos($realPath, $realUploadsPath) !== 0) {
                echo json_encode(['status' => 'error', 'message' => 'Biztonsági hiba: Érvénytelen fájlútvonal.']);
                return;
            }
            if (mime_content_type($realPath) !== 'application/pdf') {
                echo json_encode(['status' => 'error', 'message' => 'Érvénytelen fájltípus: ' . basename($path)]);
                return;
            }
        }

        $jobId = uniqid('job_');
        $baseName = 'Merged_Document_' . date('Ymd_His');
        $downloadToken = bin2hex(random_bytes(32)); // 3 rétegű védelemhez token generálás

        $jobData = [
            'id' => $jobId,
            'type' => 'pdf_merge',
            'status' => 'pending', 
            'created_at' => time(),
            'session_id' => session_id(), // Csak arról a sessionből töltheti le, aki felrakta
            'download_token' => $downloadToken,
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
            'download_token' => $downloadToken, // A JS számára szükséges
            'message' => 'Feltöltés sikeres, összefűzés elindítva...'
        ]);
    }

    /**
     * Egy-egy batch csoport fájljainak biztonságos elmentése
     */
    private function handleBatchUpload(): void {
        global $lang;

        if (!isset($_FILES['pdf_files']) || !is_array($_FILES['pdf_files']['name'])) {
            echo json_encode(['status' => 'error', 'message' => 'Nem érkeztek fájlok ebben a feltöltési csoportban.']);
            return;
        }

        $files = $_FILES['pdf_files'];
        $fileCount = count($files['name']);
        $uploadedPaths = [];

        // Mappa automatikus előkészítése
        if (!is_dir(UPLOADS_PATH)) {
            mkdir(UPLOADS_PATH, 0777, true);
        }

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

        if (count($uploadedPaths) === 0) {
            echo json_encode(['status' => 'error', 'message' => 'A csoportból egyetlen fájlt sem sikerült sikeresen elmenteni.']);
            return;
        }

        echo json_encode([
            'status' => 'success',
            'uploaded_files' => $uploadedPaths
        ]);
    }
}