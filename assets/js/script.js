/**
 * Fájl helye: php/assets/js/script.js
 * Funkció: Kliens oldali interakciók, Sötét mód, QR kód.
 */

var pollingInterval = null;

$(document).ready(function() {
    // 0. Téma inicializálása
    initTheme();

    // 1. Keresés
    $('#btnSearch').click(performSearch);
    $('#searchQuery').keypress(function(e) { if(e.which == 13) performSearch(); });
    
    $('#genForm').on('submit', function(e) {
        e.preventDefault();
        startJob();
    });

    // Téma váltó gomb
    $('#themeToggle').click(toggleTheme);
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
    // QR kód törlése új generáláskor
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
    
    // Teljes URL összeállítása a QR kódhoz
    var fullUrl = downloadLink;
    if (downloadLink.indexOf('http') !== 0) {
        // Relatív link esetén hozzáadjuk a base URL-t
        var baseUrl = window.location.href.split('?')[0];
        // eltávolítjuk az index.php-t a végéről ha ott van, hogy tiszta mappát kapjunk, vagy összefűzzük okosan
        // Egyszerűbb: window.location.origin + window.location.pathname könyvtára
        // De a downloadLink már tartalmazza az index.php-t? Igen.
        // Tehát: http://site.com/folder/ + index.php?action...
        var path = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/') + 1);
        fullUrl = window.location.origin + path + downloadLink;
    }

    $('#downloadBtn').attr('href', downloadLink);
    
    // QR Kód Generálás
    generateQRCode(fullUrl);
    
    $('#resultContainer').show();
}

function generateQRCode(url) {
    $('#qrCodeContainer').empty(); // Előző törlése
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

// Módosítás dátuma: 2025. december 13. 23:10:00