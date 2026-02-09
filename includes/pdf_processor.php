<?php
/**
 * Fájl helye: php/includes/pdf_processor.php
 * Funkció: PDF szétvágása képekre gerinc-detektálással (PHP Imagick alapú).
 * Logika: A Python 'strict_pdf_splitter.py' alapján (np.mean axis=0 elv).
 * Frissítés: Erőforrás optimalizálás a "cache resources exhausted" hiba ellen.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/logger.php';

class PdfProcessor {
    private $outputDir;
    private $dpi;

    public function __construct($outputDir, $dpi = 300) {
        $this->outputDir = $outputDir;
        $this->dpi = $dpi;
        
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0777, true);
        }

        // JAVÍTÁS: Imagick erőforrás korlátok beállítása
        // Ez megakadályozza, hogy egyetlen művelet feleméssze az összes memóriát.
        try {
            // Memória limit (RAM) - 512MB
            Imagick::setResourceLimit(Imagick::RESOURCETYPE_MEMORY, 512 * 1024 * 1024);
            // Térkép limit (Memory Map) - 512MB
            Imagick::setResourceLimit(Imagick::RESOURCETYPE_MAP, 512 * 1024 * 1024);
            // Szálkezelés: 1 szálra korlátozva stabilabb webszerver környezetben
            Imagick::setResourceLimit(Imagick::RESOURCETYPE_THREAD, 1);
        } catch (Exception $e) {
            writeLog("Imagick resource limit beállítás sikertelen (nem kritikus): " . $e->getMessage(), "WARN");
        }
    }

    /**
     * PDF feldolgozása.
     * @param string $pdfPath A PDF fájl útvonala.
     * @param callable|null $progressCallback Opcionális függvény a haladás jelzésére.
     */
    public function process($pdfPath, callable $progressCallback = null) {
        if (!extension_loaded('imagick')) {
            throw new Exception("A PHP Imagick kiterjesztés hiányzik.");
        }

        try {
            // Gyors oldalszám ellenőrzés
            $imagick = new Imagick();
            $imagick->pingImage($pdfPath);
            $pageCount = $imagick->getNumberImages();
            $imagick->clear();
            $imagick->destroy(); // Fontos: destroy() hívása

            writeLog("PDF Feldolgozás (PHP Logic): $pageCount oldal", "INFO");

            if ($progressCallback) {
                $progressCallback(5, "PDF betöltve, $pageCount oldal feldolgozása...");
            }

            $processedCount = 0;

            // Oldalankénti feldolgozás a memória kímélése érdekében
            for ($i = 0; $i < $pageCount; $i++) {
                try {
                    $this->processPage($pdfPath, $i);
                    $processedCount++;
                    
                    // Progress számítás (10% - 90% tartományban a feldolgozás)
                    if ($progressCallback) {
                        $percent = 10 + intval(($processedCount / $pageCount) * 80);
                        $progressCallback($percent, "Oldal $processedCount / $pageCount feldolgozása...");
                    }

                    // Agresszív memóriatisztítás minden oldal után
                    gc_collect_cycles();
                } catch (Exception $e) {
                    writeLog("Hiba a(z) $i. oldalnál: " . $e->getMessage(), "ERROR");
                    // Nem állunk meg egy oldal hibája miatt, de jelezzük
                }
            }

            return $processedCount * 2; // Két kép per oldal

        } catch (Exception $e) {
            throw new Exception("PDF feldolgozási hiba: " . $e->getMessage());
        }
    }

    private function processPage($pdfPath, $pageIndex) {
        $img = new Imagick();
        try {
            $img->setResolution($this->dpi, $this->dpi);
            
            // Csak az adott oldalt olvassuk be
            $img->readImage($pdfPath . '[' . $pageIndex . ']');
            $img->setImageFormat('jpg');
            $img->setImageCompressionQuality(85);

            $width = $img->getImageWidth();
            $height = $img->getImageHeight();

            // --- GERINC KERESÉS ---
            $analysis = clone $img;
            $analysis->transformImageColorspace(Imagick::COLORSPACE_GRAY);
            // 1 pixel magasra méretezés a FILTER_BOX (átlagoló) szűrővel
            $analysis->resizeImage($width, 1, Imagick::FILTER_BOX, 1);

            $iterator = $analysis->getPixelIterator();
            $row = $iterator->current(); 
            
            $startX = intval($width * 0.4);
            $endX = intval($width * 0.6);
            
            $minBrightness = 1.0; 
            $spineX = intval($width / 2); 

            $x = 0;
            foreach ($row as $pixel) {
                if ($x >= $startX && $x <= $endX) {
                    $color = $pixel->getColor(true); 
                    $brightness = $color['r']; 

                    if ($brightness < $minBrightness) {
                        $minBrightness = $brightness;
                        $spineX = $x;
                    }
                }
                $x++;
            }
            
            // Elemzés objektum törlése azonnal
            $analysis->clear();
            $analysis->destroy();

            // --- VÁGÁS ÉS MENTÉS ---
            
            // Bal oldal
            $leftPage = clone $img;
            $leftPage->cropImage($spineX, $height, 0, 0);
            $leftPage->setImagePage(0, 0, 0, 0); 
            $leftPage->writeImage($this->outputDir . sprintf("/page_%03d_1.jpg", $pageIndex + 1));
            $leftPage->clear();
            $leftPage->destroy();

            // Jobb oldal
            $rightPage = clone $img;
            $rightPage->cropImage($width - $spineX, $height, $spineX, 0);
            $rightPage->setImagePage(0, 0, 0, 0);
            $rightPage->writeImage($this->outputDir . sprintf("/page_%03d_2.jpg", $pageIndex + 1));
            $rightPage->clear();
            $rightPage->destroy();

        } finally {
            // Biztosítjuk, hogy a fő képobjektum is törlődjön hiba esetén is
            if ($img) {
                $img->clear();
                $img->destroy();
            }
        }
    }
}

// Módosítás dátuma: 2025. december 15. 11:55:00