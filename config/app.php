<?php
/**
 * QR kódok alap URL-je.
 *
 * Ha localhost-on nyitják meg az oldalt, automatikusan a gép valós LAN IP-jét
 * használja — így a generált QR kód más eszközökről (pl. telefonról) is működik.
 *
 * Kézzel felülbírálható: definiáld a QR_BASE_URL konstanst e fájl ELŐTT,
 * vagy egyszerűen írd át az alábbi üres stringet, pl.:
 *   define('QR_BASE_URL', 'http://192.168.1.5');
 */
if (!defined('QR_BASE_URL')) {
    define('QR_BASE_URL', ''); // hagyja üresen = auto-detektálás
}

function getQrBaseUrl(): string
{
    if (QR_BASE_URL !== '') {
        return rtrim(QR_BASE_URL, '/');
    }

    // 1) Ha ngrok fut, automatikusan azt használjuk → bármilyen hálózatról működik
    $ngrokJson = @file_get_contents('http://localhost:4040/api/tunnels');
    if ($ngrokJson !== false) {
        $ngrokData = json_decode($ngrokJson, true);
        foreach ($ngrokData['tunnels'] ?? [] as $tunnel) {
            if (($tunnel['proto'] ?? '') === 'https') {
                return rtrim($tunnel['public_url'], '/');
            }
        }
    }

    // 2) Fallback: valós LAN IP (ugyanazon a hálózaton lévő eszközöknek)
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';

    if ($host === 'localhost' || $host === '127.0.0.1') {
        $lanIp = gethostbyname(gethostname());
        if ($lanIp && $lanIp !== '127.0.0.1' && filter_var($lanIp, FILTER_VALIDATE_IP)) {
            $host = $lanIp;
        }
    }

    return $scheme . '://' . $host;
}
