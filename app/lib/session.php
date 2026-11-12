<?php
declare(strict_types=1);

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    if (headers_sent($file, $line)) {
        throw new RuntimeException("Cannot start session; headers sent in {$file}:{$line}.");
    }

    $config = bloodlink_config();
    session_name((string) ($config['session_name'] ?? 'bloodlink_session'));
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => (bool) ($config['session_secure'] ?? false),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_cache_limiter('');
    session_start();
}

function session_get(string $key, mixed $default = null): mixed
{
    start_session();
    return $_SESSION[$key] ?? $default;
}

function session_set(string $key, mixed $value): void
{
    start_session();
    $_SESSION[$key] = $value;
}

function session_forget(string $key): void
{
    start_session();
    unset($_SESSION[$key]);
}

function no_store_headers(): void
{
    if (PHP_SAPI !== 'cli' && !headers_sent()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private');
        header('Pragma: no-cache');
        header('Expires: 0');
    }
}

function destroy_session(): void
{
    start_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies') && !headers_sent()) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'] ?? '/',
            'domain' => $params['domain'] ?? '',
            'secure' => (bool) ($params['secure'] ?? false),
            'httponly' => (bool) ($params['httponly'] ?? true),
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }
    session_destroy();
    start_session();
    session_regenerate_id(true);
}
