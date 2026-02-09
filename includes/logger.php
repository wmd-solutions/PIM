<?php
/**
 * Fájl helye: php/includes/logger.php
 * Funkció: Központi naplózó rendszer hibakereséshez.
 */

function writeLog($message, $level = 'INFO', $context = []) {
    $logFile = LOG_PATH . '/debug.log';
    $timestamp = date('Y-m-d H:i:s');
    
    // Kontextus formázása JSON-ként
    $contextStr = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
    
    $logEntry = sprintf("[%s] [%s] %s%s%s", $timestamp, strtoupper($level), $message, $contextStr, PHP_EOL);
    
    @file_put_contents($logFile, $logEntry, FILE_APPEND);
}

// Módosítás dátuma: 2025. december 13. 13:45:00