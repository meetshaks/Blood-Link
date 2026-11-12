<?php
declare(strict_types=1);

if (defined('BLOODLINK_BOOTSTRAPPED')) {
    return;
}

define('BLOODLINK_BOOTSTRAPPED', true);

$config = require __DIR__ . '/config/config.php';

if (!is_array($config) || !isset($config['timezone'], $config['db'])) {
    throw new RuntimeException('Invalid BloodLink configuration.');
}

date_default_timezone_set((string) $config['timezone']);

if ((bool) ($config['debug'] ?? false)) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL);
}

if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(self)');
    $requestPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    if (str_contains($requestPath, '/admin/') || str_contains($requestPath, '/user/') || str_contains($requestPath, '/api/')) {
        header('X-Robots-Tag: noindex');
    }
    header("Content-Security-Policy: default-src 'self'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'; object-src 'none'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'");
    if ((bool) ($config['session_secure'] ?? false)) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

require_once __DIR__ . '/lib/escape.php';
require_once __DIR__ . '/lib/icons.php';
require_once __DIR__ . '/lib/http.php';
require_once __DIR__ . '/lib/i18n.php';
require_once __DIR__ . '/lib/session.php';
require_once __DIR__ . '/lib/flash.php';
require_once __DIR__ . '/lib/logger.php';
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/lib/validate.php';
require_once __DIR__ . '/lib/upload.php';
require_once __DIR__ . '/lib/ratelimit.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/support.php';
require_once __DIR__ . '/lib/locations.php';
require_once __DIR__ . '/lib/address.php';

if (!function_exists('bloodlink_config')) {
    function bloodlink_config(): array
    {
        global $config;
        return $config;
    }
}
