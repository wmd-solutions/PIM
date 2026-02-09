<?php
/**
 * Fájl helye: php/index.php
 * Funkció: Belépési pont, aszinkron job kezelés és letöltés + reCAPTCHA + QR + Sötét mód.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/logger.php';
require_once __DIR__ . '/includes/search.php';

// -----------------------------------------------------------------------------
// AUTOMATIKUS KARBANTARTÁS (Garbage Collection)
// -----------------------------------------------------------------------------
if (rand(1, 100) <= 5) {
    cleanUpOldFiles();
}

function cleanUpOldFiles() {
    $expireTime = 3600; // 1 óra
    $dirsToCheck = [TEMP_PATH, JOBS_PATH];
    
    foreach ($dirsToCheck as $dir) {
        if (!is_dir($dir)) continue;
        $files = glob($dir . '/*');
        foreach ($files as $file) {
            if (is_file($file) && basename($file)[0] !== '.') {
                if (time() - filemtime($file) > $expireTime) {
                    @unlink($file);
                }
            }
        }
    }
}

// reCAPTCHA ellenőrző segédfüggvény
function verifyRecaptcha($token) {
    if (empty($token)) return false;
    
    if (class_exists('ReCaptcha\ReCaptcha')) {
        $recaptcha = new \ReCaptcha\ReCaptcha(RECAPTCHA_SECRET_KEY);
        $resp = $recaptcha->setExpectedHostname($_SERVER['SERVER_NAME'])
                          ->verify($token, $_SERVER['REMOTE_ADDR']);
        return $resp->isSuccess();
    } else {
        $url = 'https://www.google.com/recaptcha/api/siteverify';
        $data = [
            'secret' => RECAPTCHA_SECRET_KEY,
            'response' => $token,
            'remoteip' => $_SERVER['REMOTE_ADDR']
        ];
        $options = [
            'http' => [
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($data)
            ]
        ];
        $context  = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        $json = json_decode($result);
        return $json->success;
    }
}

// -----------------------------------------------------------------------------
// LETÖLTÉS KISZOLGÁLÁSA
// -----------------------------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] == 'download' && isset($_GET['file'])) {
    $file = basename($_GET['file']);
    $path = TEMP_PATH . '/' . $file;
    $downloadName = isset($_GET['name']) ? basename($_GET['name']) : $file;

    if (file_exists($path)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $downloadName . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    } else {
        http_response_code(404);
        die("A fájl nem található (lejárt vagy törölve).");
    }
}

// -----------------------------------------------------------------------------
// JOB STÁTUSZ ELLENŐRZÉS
// -----------------------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'check_status') {
    header('Content-Type: application/json');
    $jobId = isset($_POST['job_id']) ? preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['job_id']) : '';
    $jobFile = JOBS_PATH . '/' . $jobId . '.json';

    if (file_exists($jobFile)) {
        $data = json_decode(file_get_contents($jobFile), true);
        echo json_encode($data);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Job nem található']);
    }
    exit;
}

// -----------------------------------------------------------------------------
// KERESÉS (reCAPTCHA)
// -----------------------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'search') {
    header('Content-Type: application/json');
    
    $token = isset($_POST['g_recaptcha_response']) ? $_POST['g_recaptcha_response'] : '';
    if (!verifyRecaptcha($token)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'reCAPTCHA ellenőrzés sikertelen. Kérlek frissítsd az oldalt!']);
        exit;
    }

    $query = isset($_POST['query']) ? trim($_POST['query']) : '';
    try {
        $results = performSearch($query);
        echo json_encode(['status' => 'success', 'data' => $results]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// -----------------------------------------------------------------------------
// GENERÁLÁS (reCAPTCHA)
// -----------------------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'generate') {
    header('Content-Type: application/json');
    
    $token = isset($_POST['g_recaptcha_response']) ? $_POST['g_recaptcha_response'] : '';
    if (!verifyRecaptcha($token)) {
        echo json_encode(['status' => 'error', 'message' => 'reCAPTCHA ellenőrzés sikertelen.']);
        exit;
    }
    
    $inputUrl = isset($_POST['reader_url']) ? trim($_POST['reader_url']) : '';
    $requestedFormat = isset($_POST['format']) ? $_POST['format'] : 'epub';
    $useDyslexic = isset($_POST['use_opendyslexic']) && ($_POST['use_opendyslexic'] === 'true' || $_POST['use_opendyslexic'] === true);

    if (empty($inputUrl)) {
        echo json_encode(['status' => 'error', 'message' => $lang['err_missing_url']]);
        exit;
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

        if ($requestedFormat === 'epub' && !$useDyslexic) {
            echo json_encode([
                'status' => 'ready', 
                'download_url' => $directUrl,
                'download_name' => $filenameBase . '.epub',
                'message' => 'Link elkészült!'
            ]);
            exit;
        }

        $jobId = uniqid('job_');
        $jobData = [
            'id' => $jobId,
            'status' => 'pending', 
            'created_at' => time(),
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
        exit;

    } else {
        echo json_encode(['status' => 'error', 'message' => $lang['err_url_format']]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['app_title']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- Google reCAPTCHA v3 -->
    <script src="https://www.google.com/recaptcha/api.js?render=<?php echo RECAPTCHA_SITE_KEY; ?>"></script>
    <!-- QRCode.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-9 col-lg-8">
            <div class="card main-card">
                <div class="header-bg text-center">
                    <h3><i class="fas fa-book-open me-2"></i><?php echo $lang['app_title']; ?></h3>
                    <p class="mb-0 small opac-50"><?php echo $lang['app_subtitle']; ?></p>
                    
                    <!-- Sötét Mód Váltó Gomb -->
                    <button class="theme-toggle-btn" id="themeToggle" title="<?php echo $lang['toggle_dark_mode']; ?>">
                        <i class="fas fa-moon"></i>
                    </button>
                </div>

                <ul class="nav nav-tabs nav-fill px-3 pt-3" id="appTabs">
                    <li class="nav-item"><a class="nav-link active" id="search-tab" data-bs-toggle="tab" href="#search"><i class="fas fa-search me-2"></i><?php echo $lang['tab_search']; ?></a></li>
                    <li class="nav-item"><a class="nav-link" id="generator-tab" data-bs-toggle="tab" href="#generator"><i class="fas fa-cogs me-2"></i><?php echo $lang['tab_generator']; ?></a></li>
                </ul>

                <div class="card-body p-4">
                    <div class="tab-content">
                        <!-- KERESÉS -->
                        <div class="tab-pane fade show active" id="search">
                            <div class="input-group input-group-lg mb-3">
                                <input type="text" class="form-control" id="searchQuery" placeholder="<?php echo $lang['search_placeholder']; ?>">
                                <button class="btn btn-primary" type="button" id="btnSearch"><i class="fas fa-search"></i></button>
                            </div>
                            <div class="loader" id="searchLoader"></div>
                            <div id="searchResults" class="mt-4"><div class="text-center text-muted py-4"><i class="fas fa-arrow-up me-2"></i><?php echo $lang['search_no_input']; ?></div></div>
                        </div>

                        <!-- GENERÁTOR -->
                        <div class="tab-pane fade" id="generator">
                            <div class="alert alert-info mb-4 border-info">
                                <h5 class="alert-heading h6 fw-bold"><i class="fas fa-info-circle me-2"></i><?php echo $lang['gen_manual_title']; ?></h5>
                                <hr>
                                <ol class="mb-0 ps-3 small">
                                    <li class="mb-2"><?php echo $lang['gen_manual_step1']; ?></li>
                                    <li class="mb-2"><?php echo $lang['gen_manual_step2']; ?></li>
                                    <li><?php echo $lang['gen_manual_step3']; ?></li>
                                </ol>
                            </div>

                            <form id="genForm" onsubmit="return false;">
                                <div class="mb-3">
                                    <label class="form-label fw-bold"><?php echo $lang['label_reader_url']; ?></label>
                                    <div class="input-group input-group-lg">
                                        <span class="input-group-text bg-light"><i class="fas fa-link text-muted"></i></span>
                                        <input type="url" class="form-control" id="reader_url" name="reader_url" placeholder="<?php echo $lang['placeholder_reader_url']; ?>" required>
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label fw-bold"><?php echo $lang['label_format']; ?></label>
                                    <select class="form-select form-select-lg" name="format" id="formatSelect">
                                        <option value="epub" selected><?php echo $lang['format_epub']; ?></option>
                                        <option value="azw3"><?php echo $lang['format_azw3']; ?></option>
                                        <option value="mobi"><?php echo $lang['format_mobi']; ?></option>
                                        <option value="pdf"><?php echo $lang['format_pdf']; ?></option>
                                        <option value="docx"><?php echo $lang['format_docx']; ?></option>
                                        <option value="fb2"><?php echo $lang['format_fb2']; ?></option>
                                        <option value="txt"><?php echo $lang['format_txt']; ?></option>
                                        <option value="rtf"><?php echo $lang['format_rtf']; ?></option>
                                    </select>
                                    <div class="form-text text-muted"><?php echo $lang['format_info']; ?></div>
                                </div>
                                <div class="mb-4 p-3 border rounded bg-light">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" id="use_opendyslexic" name="use_opendyslexic">
                                        <label class="form-check-label fw-bold" for="use_opendyslexic"><?php echo $lang['label_dyslexic']; ?></label>
                                    </div>
                                    <div class="form-text text-muted small mt-2"><i class="fas fa-info-circle me-1"></i> <?php echo $lang['info_dyslexic']; ?></div>
                                </div>
                                <div class="d-grid">
                                    <button type="button" class="btn btn-primary btn-lg" id="submitBtn" onclick="startJob()"><i class="fas fa-download me-2"></i><?php echo $lang['btn_generate']; ?></button>
                                </div>
                            </form>

                            <!-- Állapotjelzők -->
                            <div class="mt-4" id="statusContainer" style="display:none;">
                                <div class="d-flex align-items-center justify-content-center text-primary mb-2">
                                    <div class="spinner-border me-3" role="status"></div>
                                    <span id="statusText"><?php echo $lang['text_processing']; ?></span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 100%"></div>
                                </div>
                            </div>

                            <div id="errorContainer" class="alert alert-danger mt-4" style="display:none;">
                                <i class="fas fa-exclamation-triangle me-2"></i> <span id="errorText"></span>
                            </div>

                            <div id="resultContainer" class="mt-4 animate__animated animate__fadeIn" style="display:none;">
                                <div class="alert alert-success d-flex align-items-center"><i class="fas fa-check-circle me-2 fa-lg"></i><div><?php echo $lang['msg_success_default']; ?></div></div>
                                
                                <label class="form-label fw-bold small text-uppercase text-muted"><?php echo $lang['res_filename_label']; ?></label>
                                <div class="filename-box d-flex justify-content-between align-items-center mb-3">
                                    <span id="resultFileName"></span>
                                    <button class="btn btn-sm btn-outline-warning text-dark border-0" onclick="copyText('resultFileName', this)"><i class="far fa-copy fa-lg"></i></button>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <a href="#" id="downloadBtn" class="btn btn-success w-100 py-2"><i class="fas fa-download me-2"></i><?php echo $lang['btn_download']; ?></a>
                                </div>

                                <!-- QR Kód -->
                                <div class="text-center mt-4">
                                    <label class="form-label fw-bold small text-uppercase text-muted"><?php echo $lang['res_qr_label']; ?></label>
                                    <div id="qrCodeContainer"></div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
            <div class="text-center mt-3 text-muted small">&copy; <?php echo date("Y"); ?> <?php echo $lang['footer_text']; ?></div>
        </div>
    </div>
</div>
<script>
    const RECAPTCHA_SITE_KEY = '<?php echo RECAPTCHA_SITE_KEY; ?>';
    const LANG = { btn_processing: '<?php echo $lang['btn_processing']; ?>', processing: '<?php echo $lang['text_processing']; ?>' };
</script>
<script src="assets/js/script.js"></script>
</body>
</html>
<!-- Módosítás dátuma: 2025. december 13. 23:10:00 -->