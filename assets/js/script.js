/**
 * Fájl helye: php/assets/js/script.js
 * Funkció: Kliens interakciók, Sötét mód, QR kód, PDF vezérlés és biztonsági letöltések.
 * Módosítás dátuma: 2026. április 07. 16:45:00
 */

var pollingInterval = null;
var currentPdfTool = '';

var pdfFilesMap = {
    'merge': [],
    'split': [],
    'encrypt': [],
    'rotate': [],
    'wm_main': [],
    'wm_stamp': [],
    'repair': []
};

$(document).ready(function() {
    initTheme();

    $('#btnSearch').click(performSearch);
    $('#searchQuery').keypress(function(e) { if(e.which == 13) performSearch(); });
    
    $('#genForm').on('submit', function(e) {
        e.preventDefault();
        startJob();
    });

    $('#themeToggle').click(toggleTheme);

    // --- BIZTONSÁGI LETÖLTÉS GOMB VIZUÁLIS TILTÁSA KATTINTÁS UTÁN ---
    $(document).on('click', '#downloadBtn, #pdfDownloadBtn', function() {
        var btn = $(this);
        var href = btn.attr('href');
        
        // Csak a saját, biztonságos letöltési linkeknél alkalmazzuk a letiltást
        if (href && href.indexOf('action=download') !== -1) {
            // Várunk 1 másodpercet, hogy a böngésző letöltése biztosan elinduljon
            setTimeout(function() {
                btn.removeClass('btn-success').addClass('btn-secondary disabled');
                btn.html('<i class="fas fa-check-double me-2"></i>Letöltve (Törölve a szerverről)');
                // Megakadályozzuk a további kattintásokat
                btn.css('pointer-events', 'none');
            }, 1000);
        }
    });

    // --- PDF ESZKÖZTÁR ESEMÉNYEK ---
    $('.pdf-dropzone').on('dragover', function(e) {
        e.preventDefault();
        $(this).addClass('dragover');
    });

    $('.pdf-dropzone').on('dragleave', function(e) {
        e.preventDefault();
        $(this).removeClass('dragover');
    });

    $('.pdf-dropzone').on('drop', function(e) {
        e.preventDefault();
        $(this).removeClass('dragover');
        var tool = $(this).data('tool');
        handlePdfFiles(e.originalEvent.dataTransfer.files, tool);
    });

    $('.pdf-dropzone').click(function() {
        var tool = $(this).data('tool');
        $('#pdfFileInput_' + tool).click();
    });

    $('input[type="file"][id^="pdfFileInput_"]').change(function() {
        var tool = $(this).data('tool');
        handlePdfFiles(this.files, tool);
        $(this).val('');
    });
});

window.toggleSplitMode = function() {
    if ($('#splitModeExtract').is(':checked')) {
        $('#pagesInputWrapper').slideDown(200);
    } else {
        $('#pagesInputWrapper').slideUp(200);
        $('#pdfParam_pages').val(''); 
    }
}

window.showPdfWorkspace = function(tool) {
    currentPdfTool = tool;
    $('#pdfToolMenu').fadeOut(200, function() {
        $('.pdf-workspace').hide();
        $('#pdfWs_' + tool).show();
        
        let btnText = "Művelet Végrehajtása";
        if(tool === 'merge') btnText = "Fájlok Összefűzése";
        if(tool === 'split') btnText = "Szétvágás futtatása";
        if(tool === 'encrypt') btnText = "PDF Titkosítása";
        if(tool === 'rotate') btnText = "Oldalak Forgatása";
        if(tool === 'watermark') btnText = "Vízjel Hozzáadása";
        if(tool === 'repair') btnText = "PDF Javítása";
        
        $('#btnPdfActionText').text(btnText);
        $('#pdfStatusContainer, #pdfErrorContainer, #pdfResultContainer').hide();
        
        checkPdfActionReady(tool);
        $('#pdfWorkspaces').fadeIn(200);
    });
}

window.hidePdfWorkspace = function() {
    currentPdfTool = '';
    $('#pdfWorkspaces').fadeOut(200, function() {
        $('#pdfToolMenu').fadeIn(200);
    });
}

function handlePdfFiles(files, tool) {
    $.each(files, function(i, file) {
        if (file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf')) {
            if (tool === 'merge') {
                pdfFilesMap[tool].push(file);
            } else {
                pdfFilesMap[tool] = [file];
            }
        } else {
            alert('Csak PDF fájlok tölthetők fel!');
        }
    });
    renderPdfList(tool);
}

window.removePdfFile = function(tool, index) {
    pdfFilesMap[tool].splice(index, 1);
    renderPdfList(tool);
}

function renderPdfList(tool) {
    var list = $('#pdfFileList_' + tool);
    list.empty();
    var files = pdfFilesMap[tool];
    
    if (files.length > 0) {
        list.show();
        $.each(files, function(i, file) {
            var item = $('<div class="list-group-item pdf-file-item"></div>');
            var nameSpan = $('<span><i class="fas fa-file-pdf text-danger me-2"></i>' + file.name + '</span>');
            var delBtn = $('<button class="btn btn-sm btn-outline-danger border-0"><i class="fas fa-times"></i></button>');
            
            delBtn.click(function(e) {
                e.stopPropagation();
                removePdfFile(tool, i);
            });
            
            item.append(nameSpan).append(delBtn);
            list.append(item);
        });
    } else {
        list.hide();
    }
    checkPdfActionReady(tool);
}

function checkPdfActionReady(tool) {
    var isReady = false;
    if (tool === 'merge') {
        isReady = pdfFilesMap[tool].length >= 2;
    } else if (tool === 'watermark') {
        if (currentPdfTool === 'watermark') {
            isReady = pdfFilesMap['wm_main'].length === 1 && pdfFilesMap['wm_stamp'].length === 1;
        }
    } else {
        isReady = pdfFilesMap[tool].length === 1;
    }
    $('#btnPdfAction').prop('disabled', !isReady);
}

window.executePdfAction = function() {
    var tool = currentPdfTool;
    if (!tool) return;

    $('#pdfErrorContainer, #pdfResultContainer').hide();
    $('#pdfStatusContainer').show();
    $('#pdfStatusText').text(LANG.pdf_uploading || 'Fájlok feltöltése...');
    $('#pdfProgressBar').css('width', '0%');
    $('#btnPdfAction').prop('disabled', true);

    withRecaptcha('pdf_tool', function(token) {
        var formData = new FormData();
        
        formData.append('action', 'pdf_tool'); 
        formData.append('tool_type', tool);
        formData.append('g_recaptcha_response', token);
        
        if (tool === 'watermark') {
            formData.append('pdf_main', pdfFilesMap['wm_main'][0]);
            formData.append('pdf_stamp', pdfFilesMap['wm_stamp'][0]);
        } else {
            $.each(pdfFilesMap[tool], function(i, file) {
                formData.append('pdf_files[]', file);
            });
        }
        
        if (tool === 'split') formData.append('pages', $('#pdfParam_pages').val());
        if (tool === 'encrypt') formData.append('password', $('#pdfParam_password').val());
        if (tool === 'rotate') formData.append('rotation', $('#pdfParam_rotation').val());

        $.ajax({
            type: 'POST',
            url: 'index.php',
            data: formData,
            processData: false,
            contentType: false,
            xhr: function() {
                var xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener("progress", function(evt) {
                    if (evt.lengthComputable) {
                        var percentComplete = Math.round((evt.loaded / evt.total) * 100);
                        var displayPercent = Math.min(percentComplete, 50); 
                        $('#pdfProgressBar').css('width', displayPercent + '%');
                    }
                }, false);
                return xhr;
            },
            success: function(response) {
                if (response.status === 'started' && response.job_id) {
                    $('#pdfProgressBar').css('width', '50%');
                    $('#pdfStatusText').text('Feldolgozás (pdftk)...');
                    pollPdfStatus(response.job_id, tool);
                } else {
                    showPdfError(response.message || 'Hiba a feladat indításakor.', tool);
                }
            },
            error: function(xhr) {
                showPdfError('Hálózati hiba a feltöltés során.', tool);
            }
        });
    });
}

function pollPdfStatus(jobId, tool) {
    var localPollingInterval = setInterval(function() {
        $.ajax({
            type: 'POST',
            url: 'index.php',
            data: { action: 'check_status', job_id: jobId },
            dataType: 'json',
            success: function(job) {
                var currentWidth = parseInt($('#pdfProgressBar')[0].style.width) || 50;
                if (currentWidth < 90) $('#pdfProgressBar').css('width', (currentWidth + 5) + '%');

                if (job.status === 'completed') {
                    clearInterval(localPollingInterval);
                    $('#pdfProgressBar').css('width', '100%');
                    setTimeout(function() { showPdfResult(job, tool); }, 500);
                } else if (job.status === 'error') {
                    clearInterval(localPollingInterval);
                    showPdfError(job.message, tool);
                }
            },
            error: function() { }
        });
    }, 2000);
}

function showPdfResult(job, tool) {
    $('#pdfStatusContainer').hide();
    $('#pdfResultFileName').text(job.download_name);
    
    var downloadLink = 'index.php?action=download&job_id=' + encodeURIComponent(job.id) + '&token=' + encodeURIComponent(job.download_token);
    
    // Gomb állapotának és feliratának alaphelyzetbe állítása új feladat esetén
    $('#pdfDownloadBtn')
        .removeClass('btn-secondary disabled')
        .addClass('btn-success')
        .css('pointer-events', 'auto')
        .attr('href', downloadLink)
        .html('<i class="fas fa-download me-2"></i>Letöltés');

    $('#pdfResultContainer').show();
    
    if (tool === 'watermark') {
        pdfFilesMap['wm_main'] = [];
        pdfFilesMap['wm_stamp'] = [];
        renderPdfList('wm_main');
        renderPdfList('wm_stamp');
    } else {
        pdfFilesMap[tool] = [];
        renderPdfList(tool);
    }
}

function showPdfError(msg, tool) {
    $('#pdfStatusContainer').hide();
    checkPdfActionReady(tool); 
    $('#pdfErrorText').text(msg);
    $('#pdfErrorContainer').show();
}

function initTheme() {
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        document.documentElement.setAttribute('data-bs-theme', 'dark');
        $('#themeToggle i').removeClass('fa-moon').addClass('fa-sun');
    } else {
        document.documentElement.setAttribute('data-bs-theme', 'light');
        $('#themeToggle i').removeClass('fa-sun').addClass('fa-moon');
    }
}

function toggleTheme() {
    const currentTheme = document.documentElement.getAttribute('data-bs-theme');
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    
    document.documentElement.setAttribute('data-bs-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    
    if (newTheme === 'dark') {
        $('#themeToggle i').removeClass('fa-moon').addClass('fa-sun');
    } else {
        $('#themeToggle i').removeClass('fa-sun').addClass('fa-moon');
    }
}

function withRecaptcha(actionName, callback) {
    grecaptcha.ready(function() {
        grecaptcha.execute(RECAPTCHA_SITE_KEY, {action: actionName}).then(function(token) {
            callback(token);
        });
    });
}

function startJob() {
    $('#errorContainer, #resultContainer').hide();
    $('#statusContainer').show();
    $('#statusText').text(LANG.processing);
    $('#submitBtn').prop('disabled', true);
    $('#qrCodeContainer').empty();

    var readerUrl = $('#reader_url').val();
    var format = $('#formatSelect').val();
    var useDyslexic = $('#use_opendyslexic').is(':checked');

    withRecaptcha('generate', function(token) {
        var formData = { action: 'generate', reader_url: readerUrl, format: format, use_opendyslexic: useDyslexic, g_recaptcha_response: token };

        $.ajax({
            type: 'POST',
            url: 'index.php',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.status === 'ready') {
                    $('#statusContainer').hide();
                    showResult({ download_name: response.download_name, direct_url: response.download_url });
                } else if (response.status === 'started' && response.job_id) {
                    pollStatus(response.job_id);
                } else {
                    showError(response.message || 'Hiba a feladat indításakor.');
                }
            },
            error: function() { showError('Hálózati hiba a kérés indításakor.'); }
        });
    });
}

function pollStatus(jobId) {
    pollingInterval = setInterval(function() {
        $.ajax({
            type: 'POST',
            url: 'index.php',
            data: { action: 'check_status', job_id: jobId },
            dataType: 'json',
            success: function(job) {
                if (job.status === 'completed') {
                    clearInterval(pollingInterval);
                    showResult(job);
                } else if (job.status === 'error') {
                    clearInterval(pollingInterval);
                    showError(job.message);
                }
            },
            error: function() { }
        });
    }, 2000);
}

function showResult(job) {
    $('#statusContainer').hide();
    $('#submitBtn').prop('disabled', false);
    $('#resultFileName').text(job.download_name);
    
    var downloadLink;
    if (job.direct_url) {
        // Nyilvános PIM link: nincs Session védelem
        downloadLink = job.direct_url;
        $('#generatorSecurityWarning').hide();
        $('#qrCodeWrapper').show();
        
        var fullUrl = downloadLink.indexOf('http') !== 0 ? window.location.origin + window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/') + 1) + downloadLink : downloadLink;
        generateQRCode(fullUrl);
    } else {
        // Helyi szerver link: Session + Token védelemmel
        downloadLink = 'index.php?action=download&job_id=' + encodeURIComponent(job.id) + '&token=' + encodeURIComponent(job.download_token);
        $('#generatorSecurityWarning').show();
        $('#qrCodeWrapper').hide(); 
    }

    // Gomb állapotának alaphelyzetbe állítása az esetleges korábbi inaktiválás után
    $('#downloadBtn')
        .removeClass('btn-secondary disabled')
        .addClass('btn-success')
        .css('pointer-events', 'auto')
        .attr('href', downloadLink)
        .html('<i class="fas fa-download me-2"></i>' + (LANG.btn_download || 'Letöltés'));

    $('#resultContainer').show();
}

function generateQRCode(url) {
    $('#qrCodeContainer').empty();
    new QRCode(document.getElementById("qrCodeContainer"), { text: url, width: 128, height: 128, colorDark : "#000000", colorLight : "#ffffff", correctLevel : QRCode.CorrectLevel.L });
}

function showError(msg) {
    if(pollingInterval) clearInterval(pollingInterval);
    $('#statusContainer').hide();
    $('#submitBtn').prop('disabled', false);
    $('#errorText').text(msg);
    $('#errorContainer').show();
}

function performSearch() {
    var query = $('#searchQuery').val();
    if(query.length < 3) { alert("Kérlek adj meg legalább 3 karaktert!"); return; }

    $('#searchResults').hide();
    $('#searchLoader').show();

    withRecaptcha('search', function(token) {
        $.ajax({
            type: "POST", url: "index.php", data: { action: "search", query: query, g_recaptcha_response: token }, dataType: "json",
            success: function(response) {
                $('#searchLoader').hide();
                $('#searchResults').show();
                if(response.status === 'success' && response.data.length > 0) {
                    var html = '<div class="list-group">';
                    $.each(response.data, function(index, book) {
                        html += `<div class="list-group-item list-group-item-action p-3 book-card" onclick="selectBook('${book.url}')">
                            <div class="d-flex w-100 justify-content-between align-items-center">
                                <div><h5 class="mb-1 text-primary"><i class="fas fa-book me-2"></i>${book.title}</h5><small class="text-muted">${book.url}</small></div>
                                <button class="btn btn-outline-primary btn-sm rounded-pill"><i class="fas fa-arrow-right"></i> Kiválasztás</button>
                            </div></div>`;
                    });
                    html += '</div>';
                    $('#searchResults').html(html);
                } else {
                    $('#searchResults').html('<div class="alert alert-warning">Nincs találat.</div>');
                }
            },
            error: function(xhr) {
                $('#searchLoader').hide();
                $('#searchResults').show();
                var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : "Hiba történt.";
                $('#searchResults').html('<div class="alert alert-danger">' + msg + '</div>');
            }
        });
    });
}

window.selectBook = function(url) {
    var triggerEl = document.querySelector('#appTabs a[href="#generator"]');
    var tab = new bootstrap.Tab(triggerEl);
    tab.show();
    var input = document.getElementById('reader_url'); 
    if(input) {
        input.value = url;
        input.classList.add('bg-warning');
        setTimeout(function(){ input.classList.remove('bg-warning'); }, 200);
    }
}

window.copyText = function(elementId, btnElement) {
    var element = document.getElementById(elementId);
    if (!element) return;
    navigator.clipboard.writeText(element.innerText).then(function() {
        if(btnElement) {
            var icon = btnElement.querySelector('i');
            var originalClass = icon ? icon.className : '';
            if(icon) icon.className = 'fas fa-check text-success';
            setTimeout(function() { if(icon) icon.className = originalClass; }, 2000);
        }
    });
}