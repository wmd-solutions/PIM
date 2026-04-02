/**
 * Fájl helye: php/assets/js/script.js
 * Funkció: Kliens oldali interakciók, Sötét mód, QR kód, és az új PDF összefűző vezérlés.
 * Módosítás dátuma: 2026. április 02. 12:15:00
 */

var pollingInterval = null;
var selectedPdfFiles = []; // Globális tömb a feltöltendő PDF fájlokhoz

$(document).ready(function() {
    // 0. Téma inicializálása
    initTheme();

    // 1. Keresés
    $('#btnSearch').click(performSearch);
    $('#searchQuery').keypress(function(e) { if(e.which == 13) performSearch(); });
    
    // 2. Generátor űrlap
    $('#genForm').on('submit', function(e) {
        e.preventDefault();
        startJob();
    });

    // Téma váltó gomb
    $('#themeToggle').click(toggleTheme);

    // 3. PDF Dropzone és fájlkiválasztás események
    var dropzone = $('#pdfDropzone');
    var fileInput = $('#pdfFileInput');

    dropzone.on('dragover', function(e) {
        e.preventDefault();
        dropzone.addClass('dragover');
    });

    dropzone.on('dragleave', function(e) {
        e.preventDefault();
        dropzone.removeClass('dragover');
    });

    dropzone.on('drop', function(e) {
        e.preventDefault();
        dropzone.removeClass('dragover');
        handlePdfFiles(e.originalEvent.dataTransfer.files);
    });

    dropzone.click(function() {
        fileInput.click();
    });

    fileInput.change(function() {
        handlePdfFiles(this.files);
        // Töröljük a form mezőt, hogy újra ki lehessen választani ugyanazt a fájlt
        $(this).val('');
    });
});

// --- SÖTÉT MÓD KEZELÉS ---
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

// --- FOLYAMATOK ---

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
        var formData = {
            action: 'generate',
            reader_url: readerUrl,
            format: format,
            use_opendyslexic: useDyslexic,
            g_recaptcha_response: token
        };

        $.ajax({
            type: 'POST',
            url: 'index.php',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.status === 'ready') {
                    $('#statusContainer').hide();
                    showResult({
                        download_name: response.download_name,
                        direct_url: response.download_url
                    });
                } else if (response.status === 'started' && response.job_id) {
                    pollStatus(response.job_id);
                } else {
                    showError(response.message || 'Hiba a feladat indításakor.');
                }
            },
            error: function(xhr) {
                console.error(xhr);
                showError('Hálózati hiba a kérés indításakor.');
            }
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
        downloadLink = job.direct_url;
    } else {
        downloadLink = 'index.php?action=download&file=' + encodeURIComponent(job.result_file) + '&name=' + encodeURIComponent(job.download_name);
    }
    
    var fullUrl = downloadLink;
    if (downloadLink.indexOf('http') !== 0) {
        var path = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/') + 1);
        fullUrl = window.location.origin + path + downloadLink;
    }

    $('#downloadBtn').attr('href', downloadLink);
    generateQRCode(fullUrl);
    $('#resultContainer').show();
}

function generateQRCode(url) {
    $('#qrCodeContainer').empty();
    new QRCode(document.getElementById("qrCodeContainer"), {
        text: url,
        width: 128,
        height: 128,
        colorDark : "#000000",
        colorLight : "#ffffff",
        correctLevel : QRCode.CorrectLevel.L
    });
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
            type: "POST",
            url: "index.php",
            data: { 
                action: "search", 
                query: query,
                g_recaptcha_response: token 
            },
            dataType: "json",
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

function selectBook(url) {
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

function copyText(elementId, btnElement) {
    var element = document.getElementById(elementId);
    if (!element) return;
    var text = element.innerText;
    navigator.clipboard.writeText(text).then(function() {
        if(btnElement) {
            var icon = btnElement.querySelector('i');
            var originalClass = icon ? icon.className : '';
            if(icon) icon.className = 'fas fa-check text-success';
            setTimeout(function() { if(icon) icon.className = originalClass; }, 2000);
        }
    });
}

// --- ÚJ: PDF FÁJL KEZELÉS ---

function handlePdfFiles(files) {
    $.each(files, function(i, file) {
        // Ellenőrzés: csak PDF mehet be
        if (file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf')) {
            selectedPdfFiles.push(file);
        }
    });
    renderPdfList();
}

function removePdfFile(index) {
    selectedPdfFiles.splice(index, 1);
    renderPdfList();
}

function renderPdfList() {
    var list = $('#pdfFileList');
    list.empty();
    
    if (selectedPdfFiles.length > 0) {
        list.show();
        $.each(selectedPdfFiles, function(i, file) {
            var item = $('<div class="list-group-item pdf-file-item"></div>');
            var nameSpan = $('<span><i class="fas fa-file-pdf text-danger me-2"></i>' + file.name + '</span>');
            var delBtn = $('<button class="btn btn-sm btn-outline-danger border-0"><i class="fas fa-times"></i></button>');
            
            delBtn.click(function(e) {
                e.stopPropagation();
                removePdfFile(i);
            });
            
            item.append(nameSpan).append(delBtn);
            list.append(item);
        });
    } else {
        list.hide();
    }
    
    // Gomb engedélyezése ha van legalább 2 fájl
    $('#btnPdfMerge').prop('disabled', selectedPdfFiles.length < 2);
}

function startPdfMerge() {
    if (selectedPdfFiles.length < 2) return;

    $('#pdfErrorContainer, #pdfResultContainer').hide();
    $('#pdfStatusContainer').show();
    $('#pdfStatusText').text(LANG.pdf_uploading || 'Fájlok feltöltése...');
    $('#pdfProgressBar').css('width', '0%');
    $('#btnPdfMerge').prop('disabled', true);

    withRecaptcha('pdf_merge', function(token) {
        var formData = new FormData();
        formData.append('action', 'pdf_merge');
        formData.append('g_recaptcha_response', token);
        
        $.each(selectedPdfFiles, function(i, file) {
            formData.append('pdf_files[]', file);
        });

        $.ajax({
            type: 'POST',
            url: 'index.php',
            data: formData,
            processData: false, // Fontos fájlfeltöltésnél
            contentType: false, // Fontos fájlfeltöltésnél
            xhr: function() {
                var xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener("progress", function(evt) {
                    if (evt.lengthComputable) {
                        var percentComplete = Math.round((evt.loaded / evt.total) * 100);
                        // A progress bar 0-50% között jelzi a feltöltést (szerver feldolgozás kapja a többit)
                        var displayPercent = Math.min(percentComplete, 50); 
                        $('#pdfProgressBar').css('width', displayPercent + '%');
                    }
                }, false);
                return xhr;
            },
            success: function(response) {
                if (response.status === 'started' && response.job_id) {
                    $('#pdfProgressBar').css('width', '50%');
                    $('#pdfStatusText').text(LANG.processing || 'Feldolgozás és összefűzés...');
                    pollPdfStatus(response.job_id);
                } else {
                    showPdfError(response.message || 'Hiba a feladat indításakor.');
                }
            },
            error: function(xhr) {
                console.error(xhr);
                showPdfError('Hálózati hiba a feltöltés során.');
            }
        });
    });
}

function pollPdfStatus(jobId) {
    var localPollingInterval = setInterval(function() {
        $.ajax({
            type: 'POST',
            url: 'index.php',
            data: { action: 'check_status', job_id: jobId },
            dataType: 'json',
            success: function(job) {
                // Polling alatt vizuális haladás (50-től 90%-ig araszol)
                var currentWidth = parseInt($('#pdfProgressBar')[0].style.width) || 50;
                if (currentWidth < 90) {
                    $('#pdfProgressBar').css('width', (currentWidth + 5) + '%');
                }

                if (job.status === 'completed') {
                    clearInterval(localPollingInterval);
                    $('#pdfProgressBar').css('width', '100%');
                    setTimeout(function() {
                        showPdfResult(job);
                    }, 500);
                } else if (job.status === 'error') {
                    clearInterval(localPollingInterval);
                    showPdfError(job.message);
                }
            },
            error: function() { }
        });
    }, 2000);
}

function showPdfResult(job) {
    $('#pdfStatusContainer').hide();
    
    $('#pdfResultFileName').text(job.download_name);
    var downloadLink = 'index.php?action=download&file=' + encodeURIComponent(job.result_file) + '&name=' + encodeURIComponent(job.download_name);
    
    $('#pdfDownloadBtn').attr('href', downloadLink);
    $('#pdfResultContainer').show();
    
    // Lista ürítése a sikeres generálás után
    selectedPdfFiles = [];
    renderPdfList();
}

function showPdfError(msg) {
    $('#pdfStatusContainer').hide();
    $('#btnPdfMerge').prop('disabled', selectedPdfFiles.length < 2);
    $('#pdfErrorText').text(msg);
    $('#pdfErrorContainer').show();
}