<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/lib/donors.php';

$user = db()->query("SELECT id, blood_group, division_id, district_id, upazila_id FROM users WHERE status = 'approved' LIMIT 1")->fetch();
if (!is_array($user)) {
    fwrite(STDERR, "No approved donor available for work-location tests.\n");
    exit(1);
}
$userId = (int) $user['id'];
$old = get_user_work_location($userId);
$work = db()->prepare('SELECT d.division_id, u.district_id, u.id FROM upazilas u JOIN districts d ON d.id = u.district_id WHERE u.district_id <> ? LIMIT 1');
$work->execute([(int) $user['district_id']]);
$target = $work->fetch();
if (!is_array($target)) throw new RuntimeException('No alternate district available.');
$data = ['work_division_id' => $target['division_id'], 'work_district_id' => $target['district_id'], 'work_upazila_id' => $target['id'], 'work_area_text' => 'Test landmark'];
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$search = static fn (int $district): array => search_donors(['bg' => $user['blood_group'], 'district' => $district, 'only_available' => false]);

try {
    delete_user_work_location($userId);
    $homeOnly = $search((int) $user['district_id']);
    $assert($homeOnly['total'] >= 1, 'Donor without work location was not found by home.');

    save_user_work_location($userId, $data);
    $workResult = $search((int) $target['district_id']);
    $homeResult = $search((int) $user['district_id']);
    $assert($workResult['total'] >= 1 && count($workResult['donors']) === 1, 'Work location did not match exactly once.');
    $assert(($workResult['donors'][0]['matched_on'] ?? '') === 'work', 'Work match was not marked as work.');
    $assert($homeResult['total'] === 0, 'Home matched while active work location existed.');
    $assert(($workResult['donors'][0]['work_location']['area_text'] ?? '') === 'Test landmark', 'Work area was not returned.');

    db()->prepare('UPDATE user_locations SET is_visible = 0 WHERE user_id = ?')->execute([$userId]);
    $assert($search((int) $user['district_id'])['total'] >= 1, 'Hidden work location did not fall back to home.');
    $assert($search((int) $target['district_id'])['total'] === 0, 'Hidden work location matched.');
    db()->prepare('UPDATE user_locations SET is_visible = 1 WHERE user_id = ?')->execute([$userId]);
    $assert($search((int) $target['district_id'])['total'] >= 1, 'Visible work location did not match.');

    $pageOne = search_donors(['bg' => $user['blood_group'], 'district' => $user['district_id']], 1, 1);
    $pageTwo = search_donors(['bg' => $user['blood_group'], 'district' => $user['district_id']], 2, 1);
    $assert($pageOne['total'] === $pageTwo['total'], 'Pagination totals changed between pages.');
    $ids = array_merge(array_column($pageOne['donors'], 'id'), array_column($pageTwo['donors'], 'id'));
    $assert(count($ids) === count(array_unique($ids)), 'Pagination returned duplicate donor IDs.');
    echo "effective-location tests: PASS\n";
} finally {
    delete_user_work_location($userId);
    if ($old !== null) {
        db()->prepare(
            'INSERT INTO user_locations (user_id, label, division_id, district_id, upazila_id, union_id, city_corporation_id, area_text, is_visible)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$userId, $old['label'], $old['division_id'], $old['district_id'], $old['upazila_id'], $old['union_id'], $old['city_corporation_id'], $old['area_text'], $old['is_visible']]);
    }
}
