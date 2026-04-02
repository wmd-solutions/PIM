<?php
declare(strict_types=1);
/**
 * Fájl helye: php/services/garbage_collector.php
 * Funkció: Elavult ideiglenes fájlok és job-ok automatikus takarítása.
 * Módosítás dátuma: 2026. április 02. 12:20:00
 */

class GarbageCollector {
    
    /**
     * @return void
     */
    public static function cleanUp(): void {
        $expireTime = 3600; // 1 óra elévülés
        $dirsToCheck = [TEMP_PATH, JOBS_PATH, UPLOADS_PATH];
        
        foreach ($dirsToCheck as $dir) {
            if (!is_dir($dir)) continue;
            
            $files = glob($dir . '/*');
            if ($files !== false) {
                foreach ($files as $file) {
                    if (is_file($file) && basename($file)[0] !== '.') {
                        if (time() - filemtime($file) > $expireTime) {
                            @unlink($file);
                        }
                    }
                }
            }
        }
    }
}