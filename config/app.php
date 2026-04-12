<?php
if (!defined('QR_BASE_URL')) {
    define('QR_BASE_URL', ''); 
}
function getQrBaseUrl(): string
{
    if (QR_BASE_URL !== '') {
        return rtrim(QR_BASE_URL, '/');
    }
    $ngrokJson = @file_get_contents('http:
    if ($ngrokJson !== false) {
        $ngrokData = json_decode($ngrokJson, true);
        foreach ($ngrokData['tunnels'] ?? [] as $tunnel) {
            if (($tunnel['proto'] ?? '') === 'https') {
                return rtrim($tunnel['public_url'], '/');
            }
        }
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if ($host === 'localhost' || $host === '127.0.0.1') {
        $lanIp = gethostbyname(gethostname());
        if ($lanIp && $lanIp !== '127.0.0.1' && filter_var($lanIp, FILTER_VALIDATE_IP)) {
            $host = $lanIp;
        }
    }
    return $scheme . ':
}
