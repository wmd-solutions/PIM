<?php
declare(strict_types=1);
/**
 * Fájl helye: php/index.php
 * Funkció: Belépési pont (Gateway), routing, autoloader és kivételkezelés.
 * Módosítás dátuma: 2026. április 02. 12:20:00
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/logger.php';
require_once __DIR__ . '/includes/search.php'; // A meglévő procedurális függvény miatt

// 1. Dinamikus Autoloader (Snake_case fájlnév alapú osztálybetöltés)
spl_autoload_register(function (string $className) {
    $snakeCase = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $className));
    $directories = ['/handlers/', '/services/', '/includes/'];
    
    foreach ($directories as $dir) {
        $file = __DIR__ . $dir . $snakeCase . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// 2. Központi Globális Kivételkezelő
set_exception_handler(function (\Throwable $e) {
    writeLog("Nem kezelt kivétel a rendszerben", "CRITICAL", [
        'msg' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error', 
        'message' => 'Szerveroldali hiba történt a kérés feldolgozásakor.'
    ]);
    exit;
});

// 3. Automatikus karbantartás indítása (5% eséllyel)
if (rand(1, 100) <= 5) {
    GarbageCollector::cleanUp();
}

// 4. API Kérések Irányítása (Routing)
$action = $_POST['action'] ?? $_GET['action'] ?? null;
$method = $_SERVER["REQUEST_METHOD"];

if ($action !== null) {
    if ($method === 'GET' && $action === 'download') {
        (new DownloadHandler())->handle();
    } elseif ($method === 'POST') {
        switch ($action) {
            case 'check_status':
                (new StatusHandler())->handle();
                break;
            case 'search':
                (new SearchHandler())->handle();
                break;
            case 'pdf_merge':
                (new PdfMergeHandler())->handle();
                break;
            case 'generate':
                (new GenerateHandler())->handle();
                break;
            default:
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'Érvénytelen művelet.']);
                break;
        }
    }
    exit; // API kérés kiszolgálása után azonnali kilépés, hogy ne renderelődjön a HTML
}

// 5. Frontend nézet betöltése (Alapértelmezett GET kérés esetén)
require_once __DIR__ . '/views/home.php';