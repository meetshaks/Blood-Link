<?php
declare(strict_types=1);

require __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/lib/donations.php';

$user = require_user();
if (!is_post()) {
    json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
}
require_csrf();

$action = (string) ($_POST['action'] ?? '');
try {
    if ($action === 'add') {
        add_donation(
            (int) $user['id'],
            (string) ($_POST['donation_date'] ?? ''),
            (string) ($_POST['place'] ?? ''),
            (string) ($_POST['note'] ?? '')
        );
        json_response(['ok' => true, 'donations' => list_user_donations((int) $user['id'])]);
    }
    if ($action === 'delete') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            json_response(['ok' => false, 'error' => 'validation', 'fields' => ['id' => 'Invalid donation record.']], 400);
        }
        delete_donation((int) $user['id'], (int) $id);
        json_response(['ok' => true, 'donations' => list_user_donations((int) $user['id'])]);
    }
    json_response(['ok' => false, 'error' => 'invalid_action'], 400);
} catch (InvalidArgumentException $exception) {
    $fields = json_decode($exception->getMessage(), true);
    json_response([
        'ok' => false,
        'error' => 'validation',
        'fields' => is_array($fields) ? $fields : ['_form' => $exception->getMessage()],
    ], 400);
} catch (Throwable $exception) {
    app_log('Donation action failed', 'ERROR', ['exception' => $exception->getMessage(), 'user_id' => $user['id']]);
    json_response(['ok' => false, 'error' => 'server'], 500);
}
