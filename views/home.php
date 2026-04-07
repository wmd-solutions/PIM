<?php
declare(strict_types=1);
/**
 * Fájl helye: php/views/home.php
 * Funkció: A fő HTML felület és kliensoldali struktúra (Biztonsági szövegekkel).
 * Módosítás dátuma: 2026. április 07. 16:00:00
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
    
    <?php $cssVersion = file_exists(BASE_PATH . '/assets/css/style.css') ? filemtime(BASE_PATH . '/assets/css/style.css') : '1.0'; ?>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo $cssVersion; ?>">
    
    <script src="https://www.google.com/recaptcha/api.js?render=<?php echo defined('RECAPTCHA_SITE_KEY') ? RECAPTCHA_SITE_KEY : ''; ?>"></script>
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
                    <button class="theme-toggle-btn" id="themeToggle" title="<?php echo $lang['toggle_dark_mode']; ?>">
                        <i class="fas fa-moon"></i>
                    </button>
                </div>

                <ul class="nav nav-tabs nav-fill px-3 pt-3" id="appTabs">
                    <li class="nav-item"><a class="nav-link active" id="search-tab" data-bs-toggle="tab" href="#search"><i class="fas fa-search me-2"></i><?php echo $lang['tab_search']; ?></a></li>
                    <li class="nav-item"><a class="nav-link" id="generator-tab" data-bs-toggle="tab" href="#generator"><i class="fas fa-cogs me-2"></i><?php echo $lang['tab_generator']; ?></a></li>
                    <li class="nav-item"><a class="nav-link" id="pdf-tab" data-bs-toggle="tab" href="#pdf"><i class="fas fa-file-pdf me-2"></i><?php echo $lang['tab_pdf'] ?? 'PDF Eszközök'; ?></a></li>
                </ul>

                <div class="card-body p-4">
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="search">
                            <div class="input-group input-group-lg mb-3">
                                <input type="text" class="form-control" id="searchQuery" placeholder="<?php echo $lang['search_placeholder']; ?>">
                                <button class="btn btn-primary" type="button" id="btnSearch"><i class="fas fa-search"></i></button>
                            </div>
                            <div class="loader" id="searchLoader"></div>
                            <div id="searchResults" class="mt-4"><div class="text-center text-muted py-4"><i class="fas fa-arrow-up me-2"></i><?php echo $lang['search_no_input']; ?></div></div>
                        </div>

                        <div class="tab-pane fade" id="generator">
                            <div class="alert alert-info mb-4 border-info">
                                <h5 class="alert-heading h6 fw-bold"><i class="fas fa-info-circle me-2"></i><?php echo $lang['gen_manual_title']; ?></h5>
                                <hr><ol class="mb-0 ps-3 small"><li class="mb-2"><?php echo $lang['gen_manual_step1']; ?></li><li class="mb-2"><?php echo $lang['gen_manual_step2']; ?></li><li><?php echo $lang['gen_manual_step3']; ?></li></ol>
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
                            <div class="mt-4" id="statusContainer" style="display:none;">
                                <div class="d-flex align-items-center justify-content-center text-primary mb-2"><div class="spinner-border me-3" role="status"></div><span id="statusText"><?php echo $lang['text_processing']; ?></span></div>
                                <div class="progress"><div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 100%"></div></div>
                            </div>
                            <div id="errorContainer" class="alert alert-danger mt-4" style="display:none;"><i class="fas fa-exclamation-triangle me-2"></i> <span id="errorText"></span></div>
                            <div id="resultContainer" class="mt-4 animate__animated animate__fadeIn" style="display:none;">
                                <div class="alert alert-success d-flex align-items-center"><i class="fas fa-check-circle me-2 fa-lg"></i><div><?php echo $lang['msg_success_default']; ?></div></div>
                                <label class="form-label fw-bold small text-uppercase text-muted"><?php echo $lang['res_filename_label']; ?></label>
                                <div class="filename-box d-flex justify-content-between align-items-center mb-3">
                                    <span id="resultFileName"></span><button class="btn btn-sm btn-outline-warning text-dark border-0" onclick="copyText('resultFileName', this)"><i class="far fa-copy fa-lg"></i></button>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <a href="#" id="downloadBtn" class="btn btn-success w-100 py-2"><i class="fas fa-download me-2"></i><?php echo $lang['btn_download']; ?></a>
                                </div>
                                
                                <div class="text-danger text-center small mt-2 fw-bold" id="generatorSecurityWarning" style="display:none;">
                                    <i class="fas fa-user-shield me-1"></i> Biztonsági védelem: A fájl az első letöltés után azonnal törlődik a szerverről!
                                </div>

                                <div class="text-center mt-4" id="qrCodeWrapper">
                                    <label class="form-label fw-bold small text-uppercase text-muted"><?php echo $lang['res_qr_label']; ?></label>
                                    <div id="qrCodeContainer"></div>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="pdf">
                            <div id="pdfToolMenu">
                                <div class="alert alert-info mb-4 border-info">
                                    <h5 class="alert-heading h6 fw-bold"><i class="fas fa-toolbox me-2"></i>PDF Eszköztár</h5>
                                    <p class="mb-0 small">Válaszd ki, hogy milyen műveletet szeretnél elvégezni a PDF fájl(ok)on. A műveletek a szerveren futnak memóriabarát módon.</p>
                                </div>

                                <div class="row g-3">
                                    <div class="col-md-6 col-lg-4"><div class="card h-100 tool-card text-center p-3 rounded-4" onclick="showPdfWorkspace('merge')"><i class="fas fa-object-group fa-3x text-primary mb-3 mt-2"></i><h6 class="fw-bold">Összefűzés</h6><small class="text-muted">Több PDF egyesítése egy fájlba</small></div></div>
                                    <div class="col-md-6 col-lg-4"><div class="card h-100 tool-card text-center p-3 rounded-4" onclick="showPdfWorkspace('split')"><i class="fas fa-cut fa-3x text-danger mb-3 mt-2"></i><h6 class="fw-bold">Szétvágás</h6><small class="text-muted">Oldalak kivonása vagy darabolás ZIP-be</small></div></div>
                                    <div class="col-md-6 col-lg-4"><div class="card h-100 tool-card text-center p-3 rounded-4" onclick="showPdfWorkspace('encrypt')"><i class="fas fa-lock fa-3x text-warning mb-3 mt-2"></i><h6 class="fw-bold">Titkosítás</h6><small class="text-muted">Jelszavas védelem hozzáadása</small></div></div>
                                    <div class="col-md-6 col-lg-4"><div class="card h-100 tool-card text-center p-3 rounded-4" onclick="showPdfWorkspace('rotate')"><i class="fas fa-sync fa-3x text-success mb-3 mt-2"></i><h6 class="fw-bold">Forgatás</h6><small class="text-muted">Oldalak forgatása 90° vagy 180°-kal</small></div></div>
                                    <div class="col-md-6 col-lg-4"><div class="card h-100 tool-card text-center p-3 rounded-4" onclick="showPdfWorkspace('watermark')"><i class="fas fa-stamp fa-3x text-info mb-3 mt-2"></i><h6 class="fw-bold">Vízjelezés</h6><small class="text-muted">Bélyegző/vízjel nyomása a lapokra</small></div></div>
                                    <div class="col-md-6 col-lg-4"><div class="card h-100 tool-card text-center p-3 rounded-4" onclick="showPdfWorkspace('repair')"><i class="fas fa-wrench fa-3x text-secondary mb-3 mt-2"></i><h6 class="fw-bold">Javítás</h6><small class="text-muted">Sérült struktúrájú PDF helyreállítása</small></div></div>
                                </div>
                            </div>

                            <div id="pdfWorkspaces" style="display:none;">
                                <button class="btn btn-sm btn-outline-secondary mb-4" onclick="hidePdfWorkspace()">
                                    <i class="fas fa-arrow-left me-1"></i> Vissza az eszköztárhoz
                                </button>

                                <div id="pdfWs_merge" class="pdf-workspace" style="display:none;">
                                    <h4 class="mb-3 text-primary"><i class="fas fa-object-group me-2"></i>PDF Összefűzés</h4>
                                    <div class="pdf-dropzone p-4 text-center border rounded bg-light mb-3" data-tool="merge">
                                        <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-2"></i><h6 class="text-muted">Húzd ide a PDF-eket, vagy kattints a tallózáshoz (min. 2 fájl)</h6>
                                    </div>
                                    <input type="file" id="pdfFileInput_merge" data-tool="merge" multiple accept=".pdf" class="d-none">
                                    <div id="pdfFileList_merge" class="list-group mb-3" style="display:none;"></div>
                                </div>

                                <div id="pdfWs_split" class="pdf-workspace" style="display:none;">
                                    <h4 class="mb-3 text-danger"><i class="fas fa-cut me-2"></i>PDF Szétvágás / Kivonás</h4>
                                    <div class="pdf-dropzone p-4 text-center border rounded bg-light mb-3" data-tool="split">
                                        <i class="fas fa-file-upload fa-3x text-muted mb-2"></i><h6 class="text-muted">Húzz ide EGY PDF fájlt, vagy kattints a tallózáshoz</h6>
                                    </div>
                                    <input type="file" id="pdfFileInput_split" data-tool="split" accept=".pdf" class="d-none">
                                    <div id="pdfFileList_split" class="list-group mb-3" style="display:none;"></div>
                                    
                                    <div class="mb-3 p-3 border rounded bg-white">
                                        <label class="form-label fw-bold text-dark mb-3">Válaszd ki a vágás módját:</label>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="radio" name="splitMode" id="splitModeZip" value="zip" checked onchange="toggleSplitMode()">
                                            <label class="form-check-label" for="splitModeZip">
                                                <i class="fas fa-file-archive text-warning me-1"></i> <strong>Minden oldal külön PDF-be vágása</strong> <span class="text-muted small">(Egyetlen ZIP fájlban letölthető)</span>
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="splitMode" id="splitModeExtract" value="extract" onchange="toggleSplitMode()">
                                            <label class="form-check-label" for="splitModeExtract">
                                                <i class="fas fa-file-pdf text-danger me-1"></i> <strong>Bizonyos oldalak kivonása</strong> <span class="text-muted small">(Egyetlen új PDF fájlba fűzve)</span>
                                            </label>
                                        </div>
                                    </div>

                                    <div class="mb-3" id="pagesInputWrapper" style="display:none;">
                                        <label class="form-label fw-bold">Kivonandó oldalak (pl: 1-5, 8, 11-end):</label>
                                        <input type="text" class="form-control" id="pdfParam_pages" placeholder="Példa: 1-5, 8">
                                    </div>
                                </div>

                                <div id="pdfWs_encrypt" class="pdf-workspace" style="display:none;">
                                    <h4 class="mb-3 text-warning"><i class="fas fa-lock me-2"></i>PDF Titkosítás</h4>
                                    <div class="pdf-dropzone p-4 text-center border rounded bg-light mb-3" data-tool="encrypt">
                                        <i class="fas fa-file-upload fa-3x text-muted mb-2"></i><h6 class="text-muted">Húzz ide EGY PDF fájlt, vagy kattints a tallózáshoz</h6>
                                    </div>
                                    <input type="file" id="pdfFileInput_encrypt" data-tool="encrypt" accept=".pdf" class="d-none">
                                    <div id="pdfFileList_encrypt" class="list-group mb-3" style="display:none;"></div>
                                    <div class="mb-3"><label class="form-label fw-bold">Jelszó a megnyitáshoz:</label><input type="text" class="form-control" id="pdfParam_password" placeholder="Add meg a jelszót..."></div>
                                </div>

                                <div id="pdfWs_rotate" class="pdf-workspace" style="display:none;">
                                    <h4 class="mb-3 text-success"><i class="fas fa-sync me-2"></i>PDF Forgatás</h4>
                                    <div class="pdf-dropzone p-4 text-center border rounded bg-light mb-3" data-tool="rotate">
                                        <i class="fas fa-file-upload fa-3x text-muted mb-2"></i><h6 class="text-muted">Húzz ide EGY PDF fájlt, vagy kattints a tallózáshoz</h6>
                                    </div>
                                    <input type="file" id="pdfFileInput_rotate" data-tool="rotate" accept=".pdf" class="d-none">
                                    <div id="pdfFileList_rotate" class="list-group mb-3" style="display:none;"></div>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Forgatás iránya:</label>
                                        <select class="form-select" id="pdfParam_rotation">
                                            <option value="east">90° Jobbra (Óramutató járásával megegyező)</option>
                                            <option value="west">90° Balra (Óramutató járásával ellentétes)</option>
                                            <option value="south">180° Fejjel lefelé</option>
                                        </select>
                                    </div>
                                </div>

                                <div id="pdfWs_watermark" class="pdf-workspace" style="display:none;">
                                    <h4 class="mb-3 text-info"><i class="fas fa-stamp me-2"></i>PDF Vízjelezés</h4>
                                    <label class="form-label fw-bold">1. Fő Dokumentum:</label>
                                    <div class="pdf-dropzone p-3 text-center border rounded bg-light mb-3" data-tool="wm_main">
                                        <i class="fas fa-file-pdf fa-2x text-muted mb-1"></i><h6 class="text-muted m-0">Kattints a fő PDF tallózásához</h6>
                                    </div>
                                    <input type="file" id="pdfFileInput_wm_main" data-tool="wm_main" accept=".pdf" class="d-none">
                                    <div id="pdfFileList_wm_main" class="list-group mb-3" style="display:none;"></div>

                                    <label class="form-label fw-bold">2. Vízjel / Bélyegző fájl:</label>
                                    <div class="pdf-dropzone p-3 text-center border rounded bg-light mb-3" data-tool="wm_stamp">
                                        <i class="fas fa-stamp fa-2x text-muted mb-1"></i><h6 class="text-muted m-0">Kattints a vízjel PDF tallózásához</h6>
                                    </div>
                                    <input type="file" id="pdfFileInput_wm_stamp" data-tool="wm_stamp" accept=".pdf" class="d-none">
                                    <div id="pdfFileList_wm_stamp" class="list-group mb-3" style="display:none;"></div>
                                </div>

                                <div id="pdfWs_repair" class="pdf-workspace" style="display:none;">
                                    <h4 class="mb-3 text-secondary"><i class="fas fa-wrench me-2"></i>PDF Javítás</h4>
                                    <div class="pdf-dropzone p-4 text-center border rounded bg-light mb-3" data-tool="repair">
                                        <i class="fas fa-file-upload fa-3x text-muted mb-2"></i><h6 class="text-muted">Húzz ide EGY sérült PDF fájlt, vagy kattints a tallózáshoz</h6>
                                    </div>
                                    <input type="file" id="pdfFileInput_repair" data-tool="repair" accept=".pdf" class="d-none">
                                    <div id="pdfFileList_repair" class="list-group mb-3" style="display:none;"></div>
                                </div>

                                <div class="d-grid mt-4">
                                    <button type="button" class="btn btn-primary btn-lg" id="btnPdfAction" disabled onclick="executePdfAction()">
                                        <i class="fas fa-play me-2"></i><span id="btnPdfActionText">Művelet Végrehajtása</span>
                                    </button>
                                </div>

                                <div class="mt-4" id="pdfStatusContainer" style="display:none;">
                                    <div class="d-flex align-items-center justify-content-center text-primary mb-2"><div class="spinner-border me-3" role="status"></div><span id="pdfStatusText">Feldolgozás...</span></div>
                                    <div class="progress"><div class="progress-bar progress-bar-striped progress-bar-animated" id="pdfProgressBar" role="progressbar" style="width: 0%"></div></div>
                                </div>
                                <div id="pdfErrorContainer" class="alert alert-danger mt-4" style="display:none;"><i class="fas fa-exclamation-triangle me-2"></i> <span id="pdfErrorText"></span></div>
                                <div id="pdfResultContainer" class="mt-4 animate__animated animate__fadeIn" style="display:none;">
                                    <div class="alert alert-success d-flex align-items-center"><i class="fas fa-check-circle me-2 fa-lg"></i><div>Művelet sikeresen befejeződött!</div></div>
                                    <label class="form-label fw-bold small text-uppercase text-muted">Eredmény fájl:</label>
                                    <div class="filename-box d-flex justify-content-between align-items-center mb-3">
                                        <span id="pdfResultFileName"></span><button class="btn btn-sm btn-outline-warning text-dark border-0" onclick="copyText('pdfResultFileName', this)"><i class="far fa-copy fa-lg"></i></button>
                                    </div>
                                    <div class="d-grid gap-2"><a href="#" id="pdfDownloadBtn" class="btn btn-success w-100 py-2"><i class="fas fa-download me-2"></i>Letöltés</a></div>
                                    
                                    <div class="text-danger text-center small mt-2 fw-bold">
                                        <i class="fas fa-user-shield me-1"></i> Biztonsági védelem: A fájl az első letöltés után azonnal törlődik a szerverről!
                                    </div>
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
    const LANG = { btn_processing: '<?php echo $lang['btn_processing'] ?? ''; ?>', processing: '<?php echo $lang['text_processing'] ?? ''; ?>', pdf_uploading: 'Feltöltés...' };
</script>

<?php $jsVersion = file_exists(BASE_PATH . '/assets/js/script.js') ? filemtime(BASE_PATH . '/assets/js/script.js') : '1.0'; ?>
<script src="assets/js/script.js?v=<?php echo $jsVersion; ?>"></script>

</body>
</html>