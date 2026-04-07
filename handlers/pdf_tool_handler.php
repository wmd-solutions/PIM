<?php
declare(strict_types=1);
/**
 * Fájl helye: php/handlers/pdf_tool_handler.php
 * Funkció: PDF eszközök fogadása, validációja és biztonsági token generálása.
 * Módosítás dátuma: 2026. április 07. 16:00:00
 */

class PdfToolHandler {
    
    public function handle(): void {
        header('Content-Type: application/json');

        $token = $_POST['g_recaptcha_response'] ?? '';
        if (!RecaptchaService::verify((string)$token)) {
            echo json_encode(['status' => 'error', 'message' => 'reCAPTCHA ellenőrzés sikertelen.']);
            return;
        }

        $toolType = $_POST['tool_type'] ?? '';
        $validTools = ['merge', 'split', 'encrypt', 'rotate', 'watermark', 'repair'];
        
        if (!in_array($toolType, $validTools)) {
            echo json_encode(['status' => 'error', 'message' => 'Ismeretlen PDF eszköz: ' . htmlspecialchars($toolType)]);
            return;
        }

        $uploadedPaths = [];

        try {
            if ($toolType === 'watermark') {
                $uploadedPaths = $this->handleWatermarkUploads();
            } else {
                $minFiles = ($toolType === 'merge') ? 2 : 1;
                $uploadedPaths = $this->handleStandardUploads($minFiles);
            }
        } catch (\Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            return;
        }

        $pagesParam = trim($_POST['pages'] ?? '');
        $extension = ($toolType === 'split' && empty($pagesParam)) ? 'zip' : 'pdf';
        
        $jobId = uniqid('job_');
        $baseName = 'Document_' . ucfirst($toolType) . '_' . date('Ymd_His');
        $downloadToken = bin2hex(random_bytes(32)); // ÚJ: Biztonsági token
        
        $jobData = [
            'id' => $jobId,
            'type' => 'pdf_tool',
            'tool_type' => $toolType,
            'status' => 'pending', 
            'created_at' => time(),
            'session_id' => session_id(), // ÚJ: Munkamenet azonosító rögzítése
            'download_token' => $downloadToken, // ÚJ: Token rögzítése
            'input_files' => $uploadedPaths,
            'filename_base' => $baseName,
            'download_name' => $baseName . '.' . $extension,
            'params' => [
                'pages' => $pagesParam,
                'password' => trim($_POST['password'] ?? ''),
                'rotation' => trim($_POST['rotation'] ?? 'east')
            ]
        ];
        
        file_put_contents(JOBS_PATH . '/' . $jobId . '.json', json_encode($jobData));

        $scriptPath = BASE_PATH . '/worker.php';
        $cmd = PHP_BIN . " " . escapeshellarg($scriptPath) . " " . escapeshellarg($jobId) . " > /dev/null 2>&1 &";
        
        writeLog("Worker indítása ($toolType)", "INFO", ['cmd' => $cmd]);
        exec($cmd);

        echo json_encode([
            'status' => 'started', 
            'job_id' => $jobId,
            'message' => 'Feltöltés sikeres, feldolgozás elindítva...'
        ]);
    }

    private function handleStandardUploads(int $minFiles): array {
        if (!isset($_FILES['pdf_files']) || !is_array($_FILES['pdf_files']['name'])) {
            throw new \Exception('Nem érkeztek fájlok.');
        }

        $files = $_FILES['pdf_files'];
        $fileCount = count($files['name']);

        if ($fileCount < $minFiles) {
            throw new \Exception("Legalább $minFiles PDF fájl szükséges ehhez a művelethez!");
        }

        $paths = [];
        for ($i = 0; $i < $fileCount; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $tmp = $files['tmp_name'][$i];
                if (mime_content_type($tmp) !== 'application/pdf') {
                    throw new \Exception('Érvénytelen formátum. Csak PDF engedélyezett.');
                }
                $newPath = UPLOADS_PATH . '/' . uniqid('pdf_') . '_' . $i . '.pdf';
                if (move_uploaded_file($tmp, $newPath)) {
                    $paths[] = $newPath;
                }
            }
        }
        return $paths;
    }

    private function handleWatermarkUploads(): array {
        if (!isset($_FILES['pdf_main']) || !isset($_FILES['pdf_stamp'])) {
            throw new \Exception('Mind a fő dokumentum, mind a vízjel feltöltése kötelező.');
        }
        
        $paths = [];
        foreach (['main', 'stamp'] as $key) {
            $file = $_FILES['pdf_' . $key];
            if ($file['error'] === UPLOAD_ERR_OK) {
                if (mime_content_type($file['tmp_name']) !== 'application/pdf') {
                    throw new \Exception('Mindkét fájlnak érvényes PDF-nek kell lennie.');
                }
                $newPath = UPLOADS_PATH . '/' . uniqid('wm_' . $key . '_') . '.pdf';
                if (move_uploaded_file($file['tmp_name'], $newPath)) {
                    $paths[$key] = $newPath;
                }
            }
        }
        
        if (count($paths) !== 2) throw new \Exception('Hiba a vízjelek szerverre mentésekor.');
        return $paths;
    }
}