<?php
/**
 * Fájl helye: php/includes/search.php
 * Funkció: A dia.hu katalógusában való keresés logikája (DOMDocument alapú scraper).
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/logger.php';

/**
 * Szöveg ékezetmentesítése és URL-baráttá tétele (Slugify).
 *
 * @param string $text
 * @return string
 */
function slugify($text) {
    // Ékezetek cseréje
    $transliterator = Transliterator::createFromRules(':: Any-Latin; :: Latin-ASCII; :: NFD; :: [:Nonspacing Mark:] Remove; :: NFC;', Transliterator::FORWARD);
    $text = $transliterator->transliterate($text);
    
    // Csak betűk, számok és kötőjel maradjon
    $text = preg_replace('~[^\pL\d]+~u', '_', $text);
    $text = trim($text, '_');
    
    return empty($text) ? 'cim_nelkul' : $text;
}

/**
 * Keresés végrehajtása.
 */
function performSearch($query) {
    if (empty($query)) return [];

    writeLog("Keresés indítása (DOM Parser)", "INFO", ['query' => $query]);

    // Két URL-t állítunk össze: Cím és Szerző alapján
    $urlsToFetch = [
        'title'  => "https://dia.hu/katalogus?t=" . urlencode($query) . "&g=All&a=",
        'author' => "https://dia.hu/katalogus?t=&g=All&a=" . urlencode($query)
    ];

    $allResults = [];
    $processedIds = []; // ID alapú szűrés a duplikációk ellen

    foreach ($urlsToFetch as $type => $searchUrl) {
        writeLog("Lekérés indítása", "DEBUG", ['type' => $type, 'url' => $searchUrl]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $searchUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36');
        
        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode != 200 || empty($html)) {
            writeLog("Sikertelen lekérés", "WARN", ['code' => $httpCode]);
            continue;
        }

        // HTML Feldolgozása DOMDocument-tel (Stabilabb, mint a Regex)
        $dom = new DOMDocument();
        // A @ elnyomja a HTML5 validációs figyelmeztetéseket
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);

        // Megkeressük az összes linket
        $links = $xpath->query("//a[@href]");

        foreach ($links as $linkNode) {
            $href = $linkNode->getAttribute('href');
            $title = trim($linkNode->textContent);
            
            // Ha a link szövege túl rövid, vagy 'olvasás' gomb, próbáljuk meg a szülő elemből kinyerni a címet
            if (mb_strlen($title) < 3 || strtolower($title) === 'olvasás') {
                // Ez opcionális finomhangolás lehetne, de a DIA oldalon általában a cím maga a link
                // Ha mégis üres, kihagyjuk
                if (empty($title)) continue;
            }

            $bookData = null;

            // 1. ESET: Hagyományos reader.dia.hu link
            if (preg_match('/reader\.dia\.hu\/document\/([^\/?#]+)/', $href, $matches)) {
                $slugFull = $matches[1];
                // ID kinyerése a végéről (pl. ...-8459)
                if (preg_match('/-(\d+)$/', $slugFull, $idMatch)) {
                    $bookId = $idMatch[1];
                    $bookData = [
                        'id' => $bookId,
                        'url' => $href,
                        'slug_raw' => $slugFull
                    ];
                }
            }
            // 2. ESET: Resolver link (pl. resolver.pim.hu/dia.pim.hu_70_7508)
            elseif (strpos($href, 'resolver.pim.hu') !== false) {
                // Minta: dia.pim.hu_{ID}_{EGYEB}
                // A tapasztalatok alapján az első szám az ID.
                if (preg_match('/dia\.pim\.hu_(\d+)_/', $href, $idMatch)) {
                    $bookId = $idMatch[1];
                    
                    // Rekonstruáljuk a reader linket, hogy a generátorunk működjön vele
                    // A reader általában elfogadja a helyes ID-t akkor is, ha a slug eleje nem tökéletes,
                    // de megpróbáljuk a címből generálni.
                    $generatedSlug = slugify($title) . '-' . $bookId;
                    $reconstructedUrl = "https://reader.dia.hu/document/" . $generatedSlug;

                    $bookData = [
                        'id' => $bookId,
                        'url' => $reconstructedUrl, // A generátornak ezt adjuk át
                        'original_url' => $href,
                        'slug_raw' => $generatedSlug
                    ];
                }
            }

            // Ha találtunk érvényes könyvet és még nem dolgoztuk fel ezt az ID-t
            if ($bookData && !in_array($bookData['id'], $processedIds)) {
                $processedIds[] = $bookData['id'];
                
                // Cím formázása megjelenítéshez
                $displayTitle = $title;
                if ($displayTitle == $bookData['slug_raw']) {
                    // Ha a cím megegyezik a sluggal, próbáljuk szebbé tenni
                    $displayTitle = str_replace(['_', '-'], [' ', ' - '], $displayTitle);
                    $displayTitle = preg_replace('/ - \d+$/', '', $displayTitle);
                    $displayTitle = ucwords($displayTitle);
                }

                $allResults[] = [
                    'title' => $displayTitle,
                    'url' => $bookData['url'], // A rekonstruált vagy eredeti reader URL
                    'slug' => $bookData['slug_raw']
                ];
            }
        }
    }

    writeLog("Keresés befejezve", "INFO", ['talalatok_szama' => count($allResults)]);
    return $allResults;
}

// Módosítás dátuma: 2025. december 13. 21:25:00