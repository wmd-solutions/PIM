<?php
declare(strict_types=1);
/**
 * Fájl helye: php/index.php
 * Funkció: Belépési pont (Gateway), routing, autoloader, kivétel- és munkamenetkezelés.
 * Módosítás dátuma: 2026. április 07. 16:00:00
 */

// 1. Biztonságos Munkamenet (Session) Indítása
session_name('PIMSESSID');
session_set_cookie_params([
    'lifetime' => 0, // Böngésző bezárásáig él
    'path' => '/',
    'secure' => isset($_SERVER['HTTPS']), // Csak HTTPS-en, ha elérhető
    'httponly' => true, // XSS védelem a sütihez
    'samesite' => 'Strict' // CSRF védelem
]);
session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/logger.php';
require_once __DIR__ . '/includes/search.php';

// 2. Dinamikus Autoloader
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

// 3. Központi Globális Kivételkezelő
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

// 4. Automatikus karbantartás
if (rand(1, 100) <= 5) {
    if(class_exists('GarbageCollector')) {
        GarbageCollector::cleanUp();
    }
}

// 5. API Kérések Irányítása (Routing)
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
            case 'generate':
                (new GenerateHandler())->handle();
                break;
            case 'pdf_tool':
                (new PdfToolHandler())->handle();
                break;
            default:
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'Érvénytelen művelet: ' . htmlspecialchars((string)$action)]);
                break;
        }
    }
    exit;
}

// 6. Frontend nézet betöltése
require_once __DIR__ . '/views/home.php';