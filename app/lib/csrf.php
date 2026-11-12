<?php
declare(strict_types=1);

function csrf_token(): string
{
    start_session();
    if (!isset($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_validate(?string $token = null): bool
{
    $token ??= (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf'] ?? '');
    $expected = session_get('_csrf');
    return is_string($expected) && $token !== '' && hash_equals($expected, $token);
}

function require_csrf(): void
{
    if (!csrf_validate()) {
        json_response(['ok' => false, 'error' => 'csrf'], 419);
    }
}
