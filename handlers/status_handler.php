<?php
declare(strict_types=1);
/**
 * Fájl helye: php/handlers/status_handler.php
 * Funkció: Aszinkron háttérfolyamatok (Jobok) állapotának lekérdezése.
 * Módosítás dátuma: 2026. április 02. 12:20:00
 */

class StatusHandler {
    
    /**
     * @return void
     */
    public function handle(): void {
        header('Content-Type: application/json');
        
        $jobId = isset($_POST['job_id']) ? preg_replace('/[^a-zA-Z0-9_]/', '', (string)$_POST['job_id']) : '';
        if (empty($jobId)) {
            echo json_encode(['status' => 'error', 'message' => 'Érvénytelen azonosító.']);
            return;
        }

        $jobFile = JOBS_PATH . '/' . $jobId . '.json';

        if (file_exists($jobFile)) {
            $data = json_decode(file_get_contents($jobFile), true);
            echo json_encode($data);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Job nem található']);
        }
    }
}