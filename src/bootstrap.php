<?php

declare(strict_types=1);

use App\Config\Config;

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

require_once APP_ROOT . '/src/Helpers/autoload.php';

Config::load(APP_ROOT . '/.env');

date_default_timezone_set(Config::get('APP_TIMEZONE', 'Asia/Jakarta'));

if (Config::bool('APP_DEBUG', false)) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', APP_ROOT . '/storage/logs/php-error.log');
}

// Session: autentikasi dashboard, CSRF, dan cache access token di memory.
if (session_status() === PHP_SESSION_NONE) {
    session_name('qrisbj_session');
    $isHttps = (($_SERVER['HTTPS'] ?? '') !== '') && (($_SERVER['HTTPS'] ?? '') !== 'off');
    $isHttps = $isHttps || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => $isHttps,
    ]);
    session_start();
}
