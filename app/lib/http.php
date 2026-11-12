<?php
declare(strict_types=1);

function request_method(): string
{
    return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
}

function app_url(string $path = ''): string
{
    $base = rtrim((string) bloodlink_config()['base_url'], '/');
    return $base . ($path === '' ? '/' : '/' . ltrim($path, '/'));
}

function is_post(): bool
{
    return request_method() === 'POST';
}

function request_path(): string
{
    $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    return is_string($path) && $path !== '' ? $path : '/';
}

function redirect(string $location, int $status = 302): void
{
    if ($status < 300 || $status > 399) {
        throw new InvalidArgumentException('Redirect status must be 3xx.');
    }
    if (str_starts_with($location, '/')) {
        $location = app_url($location);
    }
    $targetPath = parse_url($location, PHP_URL_PATH);
    if (is_string($targetPath) && rtrim($targetPath, '/') === rtrim(request_path(), '/')) {
        return;
    }
    header('Location: ' . $location, true, $status);
    exit;
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    exit;
}

function client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}
