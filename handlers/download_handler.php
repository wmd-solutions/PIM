<?php
declare(strict_types=1);
/**
 * Fájl helye: php/handlers/download_handler.php
 * Funkció: Kész fájlok biztonságos letöltésének kiszolgálása a temp mappából.
 * Módosítás dátuma: 2026. április 02. 12:20:00
 */

class DownloadHandler {
    
    /**
     * @return void
     */
    public function handle(): void {
        $file = isset($_GET['file']) ? basename((string)$_GET['file']) : '';
        if (empty($file)) {
            http_response_code(400);
            die("Hibás kérés: hiányzó fájlnév.");
        }

        $path = TEMP_PATH . '/' . $file;
        $downloadName = isset($_GET['name']) ? basename((string)$_GET['name']) : $file;

        if (file_exists($path)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $downloadName . '"');
            header('Content-Length: ' . filesize($path));
            readfile($path);
        } else {
            http_response_code(404);
            die("A fájl nem található (esetleg lejárt vagy törölve lett).");
        }
    }
}