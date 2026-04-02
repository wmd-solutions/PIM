<?php
/**
 * Fájl helye: php/lang/hu.php
 * Funkció: Magyar nyelvi szövegek tárolása.
 * Módosítás dátuma: 2026. április 02. 12:15:00
 */

$lang = [
    // Fejléc
    'app_title' => 'PIM / DIA Könyv Letöltő',
    'app_subtitle' => 'Kereső, Konverter és Akadálymentesítő',
    'footer_text' => 'PIM/DIA Tool | Keresés a <a href="https://opac.dia.hu/search" target="_blank">opac.dia.hu</a> adatbázisában.',
    'toggle_dark_mode' => 'Sötét/Világos mód váltása',

    // Fülek
    'tab_search' => 'Keresés',
    'tab_generator' => 'Generátor',
    'tab_pdf' => 'PDF Eszközök', // ÚJ

    // Keresés fül
    'search_placeholder' => 'Szerző vagy cím keresése...',
    'search_no_input' => 'Írj be valamit a kereséshez!',
    'search_error_min_chars' => 'Kérlek adj meg legalább 3 karaktert!',
    'search_error_empty' => 'Üres keresőkifejezés',
    'search_error_server' => 'Szerveroldali hiba történt.',
    'search_no_results' => 'Nincs találat. Ha biztos vagy benne, hogy létezik a könyv, ellenőrizd a naplót vagy próbáld meg kézzel a dia.hu oldalon.',
    'search_btn_select' => 'Kiválasztás',

    // Generátor fül
    'gen_manual_title' => 'Kézi használati útmutató',
    'gen_manual_step1' => 'Nyisd meg a <a href="https://opac.dia.hu/search" target="_blank" class="fw-bold text-decoration-none">PIM / DIA Kereső (opac.dia.hu)</a> oldalát.',
    'gen_manual_step2' => 'Keress rá a szerzőre/műre és kattints az <span class="badge bg-dark text-light">OLVASÁS</span> gombra.',
    'gen_manual_step3' => 'Másold ki az URL-t és illeszd be ide.',
    
    'label_reader_url' => 'Olvasói URL:',
    'placeholder_reader_url' => 'https://reader.dia.hu/document/...',
    'label_format' => 'Kimeneti Formátum:',
    'format_epub' => 'Eredeti EPUB (Link Generálása)',
    'format_azw3' => 'Kindle AZW3 (Fontokkal együtt)',
    'format_mobi' => 'Kindle MOBI (Régebbi)',
    'format_pdf' => 'PDF (Calibre alapú)',
    'format_docx' => 'DOCX (Microsoft Word)',
    'format_fb2' => 'FB2 (PocketBook, Onyx)',
    'format_txt' => 'TXT (Sima szöveg)',
    'format_rtf' => 'RTF (Rich Text)',
    'format_info' => 'A PDF, AZW3 és MOBI konvertálás a szerveren történik (Calibre), ez igénybe vehet pár másodpercet.',
    
    'label_dyslexic' => 'Olvashatóság segítése: <span style="font-family:sans-serif; font-weight:bold;">OpenDyslexic</span> betűtípus',
    'info_dyslexic' => 'Lecseréli a könyv betűtípusát a diszlexiások számára könnyebben olvasható OpenDyslexic fontra. Ez a művelet konvertálást igényel (lassabb).',
    
    'btn_generate' => 'Link Létrehozása / Letöltés',
    'btn_processing' => 'Feldolgozás...',
    'text_processing' => 'Konvertálás és feldolgozás folyamatban, kérlek várj...',

    // ÚJ: PDF Eszközök fül
    'pdf_merge_title' => 'PDF Fájlok Összefűzése',
    'pdf_merge_desc' => 'Válassz ki több PDF fájlt (vagy húzd be őket), amiket egyetlen dokumentummá szeretnél fűzni. A sorrend a kiválasztás/behúzás sorrendje lesz.',
    'pdf_dropzone' => 'Húzd ide a PDF fájlokat, vagy kattints a tallózáshoz',
    'btn_upload_merge' => 'Kiválasztott fájlok összefűzése',
    'pdf_uploading' => 'Fájlok feltöltése és feldolgozása...',
    'err_pdf_min_two' => 'Kérlek válassz ki legalább két PDF fájlt az összefűzéshez!',
    'err_pdf_invalid' => 'A fájl nem érvényes PDF: %s',

    // Eredmények
    'res_success_title' => 'Sikeres művelet!',
    'res_filename_label' => 'JAVASOLT FÁJLNÉV:',
    'res_link_label' => 'LETÖLTÉSI HIVATKOZÁS:',
    'res_qr_label' => 'MOBIL LETÖLTÉS (QR):',
    'btn_download' => 'Megnyitás / Letöltés',
    'btn_copy_link' => 'Link Másolása',
    'btn_copy_filename_title' => 'Név másolása',
    'msg_http_warning' => '<strong>Megjegyzés:</strong> A link <em>http</em> protokollt használ. Ha a letöltés nem indul el automatikusan, kattints a linkre jobb gombbal, és válaszd a "Link mentése másként..." opciót.',
    'msg_conversion_failed' => 'A konvertálás sikertelen volt, de az eredeti fájl letölthető.',
    'msg_success_default' => 'A letöltési link elkészült!',

    // Hibák
    'err_url_format' => 'Hibás URL formátum. Nem található az ID.',
    'err_missing_url' => 'Add meg az URL-t!',
    'err_processing' => 'Feldolgozási hiba',
];