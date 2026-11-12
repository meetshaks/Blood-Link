<?php
declare(strict_types=1);

require __DIR__ . '/../../app/bootstrap.php';
$user = require_user();
if (!is_post()) json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
require_csrf();

$action = (string) ($_POST['action'] ?? '');
if (!in_array($action, ['toggle_availability', 'set_present_at'], true)) {
    json_response(['ok' => false, 'error' => 'invalid_action'], 400);
}
if ($action === 'set_present_at') {
    $where = (string) ($_POST['present_at'] ?? '');
    if (!set_user_present_at((int) $user['id'], $where)) json_response(['ok' => false, 'error' => 'invalid_present_at'], 400);
    json_response(['ok' => true, 'present_at' => $where]);
}
$available = filter_var($_POST['available'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
if ($available === null) {
    json_response(['ok' => false, 'error' => 'invalid_availability'], 400);
}
if (!set_user_availability((int) $user['id'], $available)) {
    json_response(['ok' => false, 'error' => 'update_failed'], 500);
}
json_response(['ok' => true, 'available' => $available]);
