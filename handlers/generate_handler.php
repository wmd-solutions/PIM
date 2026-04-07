<?php
declare(strict_types=1);
/**
 * Fájl helye: php/handlers/generate_handler.php
 * Funkció: E-könyv forrás URL fogadása és konvertáló Job indítása biztonsági tokennel.
 * Módosítás dátuma: 2026. április 07. 16:00:00
 */

class GenerateHandler {
    
    public function handle(): void {
        header('Content-Type: application/json');
        global $lang;
        
        $token = $_POST['g_recaptcha_response'] ?? '';
        if (!RecaptchaService::verify((string)$token)) {
            echo json_encode(['status' => 'error', 'message' => 'reCAPTCHA ellenőrzés sikertelen.']);
            return;
        }
        
        $inputUrl = isset($_POST['reader_url']) ? trim((string)$_POST['reader_url']) : '';
        $requestedFormat = $_POST['format'] ?? 'epub';
        $useDyslexicRaw = $_POST['use_opendyslexic'] ?? false;
        $useDyslexic = ($useDyslexicRaw === 'true' || $useDyslexicRaw === true);

        if (empty($inputUrl)) {
            echo json_encode(['status' => 'error', 'message' => $lang['err_missing_url'] ?? 'Az URL megadása kötelező.']);
            return;
        }

        if (preg_match('/-(\d+)\/?$/', $inputUrl, $idMatches)) {
            $id = $idMatches[1]; 
            $paddedId = str_pad($id, 10, "0", STR_PAD_LEFT);
            $directUrl = "http://dia.jadox.pim.hu/ebook/" . $paddedId . "/doc.epub";

            if (preg_match('/\/document\/([^\/?#]+)/', $inputUrl, $nameMatches)) {
                $filenameBase = str_replace(['_', '-'], [' ', ' - '], $nameMatches[1]);
            } else {
                $filenameBase = "pim_konyv_" . $id;
            }
            
            if ($useDyslexic) {
                $filenameBase .= "_dyslexic";
            }

            // Direkt (helyi fájlt nem generáló) PIM link esetén nincs token, mert ez egy nyilvános külső link
            if ($requestedFormat === 'epub' && !$useDyslexic) {
                echo json_encode([
                    'status' => 'ready', 
                    'download_url' => $directUrl,
                    'download_name' => $filenameBase . '.epub',
                    'message' => 'Link elkészült!'
                ]);
                return;
            }

            $jobId = uniqid('job_');
            $downloadToken = bin2hex(random_bytes(32)); // ÚJ: Biztonsági token

            $jobData = [
                'id' => $jobId,
                'type' => 'ebook_convert',
                'status' => 'pending', 
                'created_at' => time(),
                'session_id' => session_id(), // ÚJ: Session mentése
                'download_token' => $downloadToken, // ÚJ: Token mentése
                'url' => $directUrl,
                'format' => $requestedFormat,
                'filename_base' => $filenameBase,
                'use_dyslexic' => $useDyslexic,
                'download_name' => $filenameBase . '.' . $requestedFormat
            ];
            
            file_put_contents(JOBS_PATH . '/' . $jobId . '.json', json_encode($jobData));

            $scriptPath = BASE_PATH . '/worker.php';
            $cmd = PHP_BIN . " " . escapeshellarg($scriptPath) . " " . escapeshellarg($jobId) . " > /dev/null 2>&1 &";
            
            writeLog("Worker indítása", "INFO", ['cmd' => $cmd]);
            exec($cmd);

            echo json_encode([
                'status' => 'started', 
                'job_id' => $jobId,
                'message' => 'Feldolgozás elindítva...'
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => $lang['err_url_format'] ?? 'Hibás URL formátum.']);
        }
    }
}