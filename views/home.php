<?php
declare(strict_types=1);
/**
 * Fájl helye: php/views/home.php
 * Funkció: A fő HTML felület és kliensoldali struktúra.
 * Módosítás dátuma: 2026. április 02. 12:35:00
 */
if (!defined('BASE_PATH')) {
    exit('No direct script access allowed');
}
global $lang;
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['app_title']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <?php 
    // Automatikus CSS verziózás a fájl módosítási ideje alapján
    $cssVersion = file_exists(BASE_PATH . '/assets/css/style.css') ? filemtime(BASE_PATH . '/assets/css/style.css') : '1.0'; 
    ?>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo $cssVersion; ?>">
    
    <!-- Google reCAPTCHA v3 -->
    <script src="https://www.google.com/recaptcha/api.js?render=<?php echo defined('RECAPTCHA_SITE_KEY') ? RECAPTCHA_SITE_KEY : ''; ?>"></script>
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
                    <li class="nav-item"><a class="nav-link" id="pdf-tab" data-bs-toggle="tab" href="#pdf"><i class="fas fa-file-pdf me-2"></i><?php echo $lang['tab_pdf']; ?></a></li>
                </ul>

                <div class="card-body p-4">
                    <div class="tab-content">
                        <!-- 1. KERESÉS -->
                        <div class="tab-pane fade show active" id="search">
                            <div class="input-group input-group-lg mb-3">
                                <input type="text" class="form-control" id="searchQuery" placeholder="<?php echo $lang['search_placeholder']; ?>">
                                <button class="btn btn-primary" type="button" id="btnSearch"><i class="fas fa-search"></i></button>
                            </div>
                            <div class="loader" id="searchLoader"></div>
                            <div id="searchResults" class="mt-4"><div class="text-center text-muted py-4"><i class="fas fa-arrow-up me-2"></i><?php echo $lang['search_no_input']; ?></div></div>
                        </div>

                        <!-- 2. GENERÁTOR -->
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

                            <!-- Állapotjelzők (Generátor) -->
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

                        <!-- 3. ÚJ: PDF ESZKÖZÖK -->
                        <div class="tab-pane fade" id="pdf">
                            <div class="alert alert-info mb-4 border-info">
                                <h5 class="alert-heading h6 fw-bold"><i class="fas fa-file-pdf me-2"></i><?php echo $lang['pdf_merge_title']; ?></h5>
                                <p class="mb-0 small"><?php echo $lang['pdf_merge_desc']; ?></p>
                            </div>

                            <div id="pdfDropzone" class="pdf-dropzone p-5 text-center border rounded bg-light mb-4">
                                <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted"><?php echo $lang['pdf_dropzone']; ?></h5>
                            </div>
                            
                            <!-- A JAVÍTÁS ITT TÖRTÉNT: Az input a dropzone div-en kívülre került -->
                            <input type="file" id="pdfFileInput" name="pdf_files[]" multiple accept=".pdf" class="d-none">

                            <div id="pdfFileList" class="list-group mb-4" style="display:none;">
                                <!-- Ide kerülnek a kiválasztott fájlok jQuery segítségével -->
                            </div>

                            <div class="d-grid">
                                <button type="button" class="btn btn-primary btn-lg" id="btnPdfMerge" disabled onclick="startPdfMerge()">
                                    <i class="fas fa-object-group me-2"></i><?php echo $lang['btn_upload_merge']; ?>
                                </button>
                            </div>

                            <!-- Állapotjelzők (PDF) -->
                            <div class="mt-4" id="pdfStatusContainer" style="display:none;">
                                <div class="d-flex align-items-center justify-content-center text-primary mb-2">
                                    <div class="spinner-border me-3" role="status"></div>
                                    <span id="pdfStatusText"><?php echo $lang['pdf_uploading']; ?></span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar progress-bar-striped progress-bar-animated" id="pdfProgressBar" role="progressbar" style="width: 0%"></div>
                                </div>
                            </div>

                            <div id="pdfErrorContainer" class="alert alert-danger mt-4" style="display:none;">
                                <i class="fas fa-exclamation-triangle me-2"></i> <span id="pdfErrorText"></span>
                            </div>

                            <div id="pdfResultContainer" class="mt-4 animate__animated animate__fadeIn" style="display:none;">
                                <div class="alert alert-success d-flex align-items-center"><i class="fas fa-check-circle me-2 fa-lg"></i><div><?php echo $lang['msg_success_default']; ?></div></div>
                                
                                <label class="form-label fw-bold small text-uppercase text-muted"><?php echo $lang['res_filename_label']; ?></label>
                                <div class="filename-box d-flex justify-content-between align-items-center mb-3">
                                    <span id="pdfResultFileName"></span>
                                    <button class="btn btn-sm btn-outline-warning text-dark border-0" onclick="copyText('pdfResultFileName', this)"><i class="far fa-copy fa-lg"></i></button>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <a href="#" id="pdfDownloadBtn" class="btn btn-success w-100 py-2"><i class="fas fa-download me-2"></i><?php echo $lang['btn_download']; ?></a>
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
    const RECAPTCHA_SITE_KEY = '<?php echo defined('RECAPTCHA_SITE_KEY') ? RECAPTCHA_SITE_KEY : ''; ?>';
    const LANG = { 
        btn_processing: '<?php echo $lang['btn_processing']; ?>', 
        processing: '<?php echo $lang['text_processing']; ?>',
        pdf_uploading: '<?php echo $lang['pdf_uploading']; ?>'
    };
</script>

<?php 
// Automatikus JS verziózás a fájl módosítási ideje alapján
$jsVersion = file_exists(BASE_PATH . '/assets/js/script.js') ? filemtime(BASE_PATH . '/assets/js/script.js') : '1.0'; 
?>
<script src="assets/js/script.js?v=<?php echo $jsVersion; ?>"></script>

</body>
</html>