<?php
declare(strict_types=1);
/**
 * Fájl helye: php/handlers/search_handler.php
 * Funkció: A PIM/DIA katalógusban történő keresés logikájának API végpontja.
 * Módosítás dátuma: 2026. április 02. 12:20:00
 */

class SearchHandler {
    
    /**
     * @return void
     */
    public function handle(): void {
        header('Content-Type: application/json');

        $token = $_POST['g_recaptcha_response'] ?? '';
        if (!RecaptchaService::verify((string)$token)) {
            http_response_code(403);
            echo json_encode([
                'status' => 'error', 
                'message' => 'reCAPTCHA ellenőrzés sikertelen. Kérlek frissítsd az oldalt!'
            ]);
            return;
        }

        $query = isset($_POST['query']) ? trim((string)$_POST['query']) : '';
        if (mb_strlen($query) < 3) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Túl rövid keresőkifejezés.']);
            return;
        }

        try {
            // A performSearch() függvény a globálisan betöltött search.php-ből származik
            $results = performSearch($query);
            echo json_encode(['status' => 'success', 'data' => $results]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
}