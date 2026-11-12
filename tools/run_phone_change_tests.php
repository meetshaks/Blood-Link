<?php
declare(strict_types=1);

ob_start();
require __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/lib/settings.php';

$pdo = db();
$location = $pdo->query('SELECT d.division_id, d.id AS district_id, u.id AS upazila_id FROM districts d JOIN upazilas u ON u.district_id = d.id ORDER BY d.id, u.id LIMIT 1')->fetch();
if (!is_array($location)) throw new RuntimeException('No test location is available.');

$suffix = (string) random_int(1000000, 9999999);
$oldPhone = '017' . $suffix . '0';
$newPhone = '017' . $suffix . '1';
$duplicatePhone = '017' . $suffix . '2';
$suspendedPhone = '017' . $suffix . '3';
$password = 'TestPassword123!';
$userIds = [];
$passed = 0;
$failed = 0;
$check = static function (string $name, bool $condition) use (&$passed, &$failed): void {
    if ($condition) { $passed++; echo "PASS: {$name}\n"; }
    else { $failed++; echo "FAIL: {$name}\n"; }
};

try {
    $insert = $pdo->prepare(
        'INSERT INTO users (name, phone, password_hash, blood_group, date_of_birth, gender, division_id, district_id, upazila_id, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $insert->execute(['Phone test', $oldPhone, password_hash($password, PASSWORD_DEFAULT), 'A+', '1990-01-01', 'male', $location['division_id'], $location['district_id'], $location['upazila_id'], 'approved']);
    $userId = (int) $pdo->lastInsertId();
    $userIds[] = $userId;
    $insert->execute(['Duplicate test', $duplicatePhone, password_hash($password, PASSWORD_DEFAULT), 'B+', '1990-01-01', 'female', $location['division_id'], $location['district_id'], $location['upazila_id'], 'pending']);
    $duplicateId = (int) $pdo->lastInsertId();
    $userIds[] = $duplicateId;
    $insert->execute(['Suspended test', $suspendedPhone, password_hash($password, PASSWORD_DEFAULT), 'O+', '1990-01-01', 'other', $location['division_id'], $location['district_id'], $location['upazila_id'], 'approved']);
    $suspendedId = (int) $pdo->lastInsertId();
    $userIds[] = $suspendedId;
    $pdo->prepare('UPDATE users SET is_suspended = 1 WHERE id = ?')->execute([$suspendedId]);
    $pdo->prepare("INSERT INTO change_requests (user_id, field_name, old_value, new_value) VALUES (?, 'phone', ?, ?)")->execute([$userId, $oldPhone, $newPhone]);

    $profile = [
        'name' => 'Phone test', 'phone' => $newPhone, 'current_password' => $password,
        'date_of_birth' => '1990-01-01', 'gender' => 'male',
        'division_id' => $location['division_id'], 'district_id' => $location['district_id'],
        'upazila_id' => $location['upazila_id'], 'union_id' => '',
        'city_corporation_id' => '', 'city_corporation_area_id' => '', 'village' => '',
    ];
    $wrongPassword = $profile;
    $wrongPassword['current_password'] = 'WrongPassword123!';
    try { update_user_profile($userId, $wrongPassword); $check('wrong password rejected', false); }
    catch (InvalidArgumentException $exception) { $check('wrong password rejected', str_contains($exception->getMessage(), 'current_password')); }
    $result = update_user_profile($userId, $profile);
    $check('successful phone change', ($result['phone_changed'] ?? false) === true);
    $check('new phone login works', attempt_user_login($newPhone, $password)['ok'] === true);
    $check('old phone no longer logs in', attempt_user_login($oldPhone, $password)['ok'] === false);
    $check('pending phone request cancelled', (int) $pdo->query("SELECT COUNT(*) FROM change_requests WHERE user_id = {$userId} AND field_name = 'phone' AND status = 'cancelled'")->fetchColumn() === 1);
    $check('masked audit entry written', (int) $pdo->query("SELECT COUNT(*) FROM user_activity_log WHERE user_id = {$userId} AND action = 'phone_changed'")->fetchColumn() === 1);

    $profile['phone'] = $duplicatePhone;
    $profile['current_password'] = $password;
    try { update_user_profile($userId, $profile); $check('duplicate phone rejected', false); }
    catch (InvalidArgumentException $exception) { $check('duplicate phone rejected', str_contains($exception->getMessage(), 'phone')); }
    $profile['phone'] = $suspendedPhone;
    try { update_user_profile($userId, $profile); $check('suspended duplicate phone rejected', false); }
    catch (InvalidArgumentException $exception) { $check('suspended duplicate phone rejected', str_contains($exception->getMessage(), 'phone')); }

    $profile['phone'] = '012345';
    try { update_user_profile($userId, $profile); $check('invalid phone rejected', false); }
    catch (InvalidArgumentException $exception) { $check('invalid phone rejected', str_contains($exception->getMessage(), 'phone')); }

    $profile['phone'] = $newPhone;
    unset($profile['current_password']);
    $check('unchanged phone needs no password', update_user_profile($userId, $profile)['phone_changed'] === false);

    try { create_change_request($userId, 'phone', '01712345678'); $check('new phone requests blocked', false); }
    catch (InvalidArgumentException) { $check('new phone requests blocked', true); }
} finally {
    if ($userIds !== []) {
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $pdo->prepare("DELETE FROM users WHERE id IN ({$placeholders})")->execute($userIds);
    }
}

echo "Phone-change tests: {$passed} passed, {$failed} failed.\n";
ob_end_flush();
exit($failed === 0 ? 0 : 1);
