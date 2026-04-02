<?php
declare(strict_types=1);
/**
 * Fájl helye: php/services/recaptcha_service.php
 * Funkció: Google reCAPTCHA v3 token ellenőrzése és érvényesítése.
 * Módosítás dátuma: 2026. április 02. 12:20:00
 */

class RecaptchaService {
    
    /**
     * @param string $token
     * @return bool
     */
    public static function verify(string $token): bool {
        if (empty($token)) {
            return false;
        }
        
        if (class_exists('ReCaptcha\ReCaptcha')) {
            $recaptcha = new \ReCaptcha\ReCaptcha(RECAPTCHA_SECRET_KEY);
            $resp = $recaptcha->setExpectedHostname($_SERVER['SERVER_NAME'])
                              ->verify($token, $_SERVER['REMOTE_ADDR']);
            return $resp->isSuccess();
        } else {
            $url = 'https://www.google.com/recaptcha/api/siteverify';
            $data = [
                'secret' => RECAPTCHA_SECRET_KEY,
                'response' => $token,
                'remoteip' => $_SERVER['REMOTE_ADDR']
            ];
            $options = [
                'http' => [
                    'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                    'method'  => 'POST',
                    'content' => http_build_query($data)
                ]
            ];
            $context  = stream_context_create($options);
            $result = @file_get_contents($url, false, $context);
            
            if (!$result) return false;
            
            $json = json_decode($result);
            return isset($json->success) && $json->success;
        }
    }
}