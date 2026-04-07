<?php
declare(strict_types=1);
/**
 * Fájl helye: php/handlers/download_handler.php
 * Funkció: 3 rétegű biztonsági letöltés (Session + Token + Önmegsemmisítő) UX hibaoldallal.
 * Módosítás dátuma: 2026. április 07. 16:30:00
 */

class DownloadHandler {
    
    public function handle(): void {
        $jobId = isset($_GET['job_id']) ? preg_replace('/[^a-zA-Z0-9_]/', '', (string)$_GET['job_id']) : '';
        $token = $_GET['token'] ?? '';

        if (empty($jobId) || empty($token)) {
            $this->showErrorPage(400, "Érvénytelen kérés", "A letöltési link hibás vagy hiányos. Kérlek, térj vissza a főoldalra és indítsd újra a folyamatot.");
        }

        $jobFile = JOBS_PATH . '/' . $jobId . '.json';

        if (!file_exists($jobFile)) {
            $this->showErrorPage(404, "A fájl már nem elérhető", "Ezt a dokumentumot már letöltötted. Biztonsági okokból a fájlok az első letöltés után azonnal és véglegesen törlődnek a szerverről.");
        }

        $jobData = json_decode(file_get_contents($jobFile), true);

        // 1. RÉTEG: Kriptográfiai Token ellenőrzés
        if (!hash_equals($jobData['download_token'] ?? '', $token)) {
            writeLog("Érvénytelen token letöltéskor", "WARN", ['job_id' => $jobId]);
            $this->showErrorPage(403, "Biztonsági hiba", "A letöltési token érvénytelen. A fájlhoz a hozzáférés megtagadva.");
        }

        // 2. RÉTEG: Session (Munkamenet) ellenőrzés
        if (($jobData['session_id'] ?? '') !== session_id()) {
            writeLog("Session eltérés letöltéskor", "WARN", ['job_id' => $jobId, 'session' => session_id()]);
            $this->showErrorPage(403, "Hozzáférés megtagadva", "Ezt a fájlt szigorú biztonsági okokból csak arról az eszközről és böngészőből töltheted le, amellyel a folyamatot elindítottad.");
        }

        // Fájl kiszolgálása
        $file = TEMP_PATH . '/' . basename($jobData['result_file'] ?? '');

        if (file_exists($file)) {
            $downloadName = $jobData['download_name'] ?? 'document.pdf';

            // Alapvető letöltési fejlécek
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . str_replace('"', '', $downloadName) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($file));

            // Kimeneti puffer ürítése, hogy elkerüljük a memóriahibákat nagy fájloknál
            if (ob_get_level()) {
                ob_end_clean();
            }

            readfile($file);

            // 3. RÉTEG: Önmegsemmisítés (Fájl és Job JSON azonnali törlése)
            @unlink($file);
            @unlink($jobFile);
            writeLog("Fájl letöltve és biztonságosan törölve", "INFO", ['job_id' => $jobId, 'file' => $downloadName]);

            exit;
        } else {
            $this->showErrorPage(404, "Fájl nem található", "A kért fájl feldolgozása sikeres volt, de a fizikai dokumentum valamiért hiányzik a szerverről.");
        }
    }

    /**
     * Vizuálisan formázott (Bootstrap) hibaoldal megjelenítése nyers HTML szöveg helyett.
     */
    private function showErrorPage(int $code, string $title, string $message): void {
        http_response_code($code);
        $icon = ($code === 404) ? 'fa-file-excel text-warning' : 'fa-shield-alt text-danger';
        
        $html = <<<HTML
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hiba - {$title}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center" style="height: 100vh; margin: 0;">
    <div class="text-center p-5 bg-white shadow rounded-4" style="max-width: 500px; width: 90%;">
        <i class="fas {$icon} fa-4x mb-4"></i>
        <h3 class="text-dark fw-bold mb-3">{$title}</h3>
        <p class="text-muted mb-4">{$message}</p>
        <a href="index.php" class="btn btn-primary btn-lg rounded-pill px-4 shadow-sm">
            <i class="fas fa-redo me-2"></i>Új folyamat indítása
        </a>
    </div>
</body>
</html>
HTML;
        echo $html;
        exit;
    }
}