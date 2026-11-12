<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This test runner must run from the command line.\n");
    exit(1);
}

require __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/lib/admin.php';
require_once __DIR__ . '/../app/lib/donations.php';
require_once __DIR__ . '/../app/lib/donors.php';
require_once __DIR__ . '/../app/lib/settings.php';
ob_start();

$performance = in_array('--performance', $argv, true);
$passed = 0;
$failed = 0;
$temporaryPhones = ['01739990001', '01739990002'];

function check_test(string $name, bool $condition): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "PASS: {$name}\n";
    } else {
        $failed++;
        echo "FAIL: {$name}\n";
    }
}

function test_user(string $phone, string $name): int
{
    $stmt = db()->prepare(
        'INSERT INTO users (name, phone, password_hash, blood_group, date_of_birth, gender,
         division_id, district_id, upazila_id, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$name, $phone, password_hash('Phase14Test!', PASSWORD_DEFAULT), 'A+', '1990-01-01', 'male', 6, 41, 320, 'approved']);
    return (int) db()->lastInsertId();
}

try {
    db()->exec("DELETE FROM users WHERE phone IN ('01739990001', '01739990002') OR name LIKE 'phase14_perf_%'");
    check_test('HTML escaping blocks XSS', e('<script>alert(1)</script>') === '&lt;script&gt;alert(1)&lt;/script&gt;');
    check_test('Bangladesh phone normalization', normalize_phone('+8801739990001') === '01739990001');
    check_test('Future donation date rejected', validate_donation_date(date('Y-m-d', strtotime('+1 day'))) === false);
    check_test('Valid donation date accepted', validate_donation_date('2020-01-01') === true);

    start_session();
    session_set('_csrf', str_repeat('a', 64));
    check_test('CSRF rejects invalid token', csrf_validate(str_repeat('b', 64)) === false);
    check_test('CSRF accepts matching token', csrf_validate(str_repeat('a', 64)) === true);

    $userA = test_user($temporaryPhones[0], 'Phase 14 A');
    $userB = test_user($temporaryPhones[1], 'Phase 14 B');
    add_donation($userA, '2026-01-01', 'Test place', null);
    $duplicateRejected = false;
    try { add_donation($userA, '2026-01-01', null, null); } catch (InvalidArgumentException) { $duplicateRejected = true; }
    check_test('Duplicate donation date rejected', $duplicateRejected);
    check_test('Donation date synchronized', (string) db()->query("SELECT last_donation_date FROM users WHERE id = {$userA}")->fetchColumn() === '2026-01-01');
    $idorRejected = false;
    try { delete_donation($userB, (int) db()->query("SELECT id FROM donations WHERE user_id = {$userA}")->fetchColumn()); } catch (InvalidArgumentException) { $idorRejected = true; }
    check_test('Donation ownership prevents IDOR', $idorRejected);

    $rateIdentifier = 'phase14-rate-' . bin2hex(random_bytes(4));
    for ($i = 0; $i < 5; $i++) record_login_failure($rateIdentifier, 'user');
    check_test('Login rate limit locks after five attempts', login_is_locked($rateIdentifier, 'user') === true);
    clear_login_failures($rateIdentifier, 'user');

    $explain = db()->query(
        "EXPLAIN SELECT u.id FROM users u
         WHERE u.status = 'approved' AND u.available_toggle = 1
         AND u.blood_group = 'A+' ORDER BY u.name LIMIT 12"
    )->fetchAll();
    check_test('Search query EXPLAIN succeeds', $explain !== []);
    $searchPageOne = search_donors(['bg' => 'A+'], 1, 1);
    $searchPageTwo = search_donors(['bg' => 'A+'], 2, 1);
    $lastSearchPage = max(1, (int) ceil($searchPageOne['total'] / $searchPageOne['per_page']));
    $searchLastPage = search_donors(['bg' => 'A+'], $lastSearchPage, 1);
    $searchOversizedPage = search_donors(['bg' => 'A+'], PHP_INT_MAX, 1);
    $searchPageIds = array_merge(array_column($searchPageOne['donors'], 'id'), array_column($searchPageTwo['donors'], 'id'));
    check_test('Search pagination keeps page totals stable', $searchPageOne['total'] === $searchPageTwo['total']);
    check_test('Search pagination has no duplicate donor IDs', count($searchPageIds) === count(array_unique($searchPageIds)));
    check_test('Oversized search page clamps to last page', $searchOversizedPage['page'] === $lastSearchPage && array_column($searchOversizedPage['donors'], 'id') === array_column($searchLastPage['donors'], 'id'));
    check_test('Upload directory denies PHP execution', str_contains((string) file_get_contents(__DIR__ . '/../public/uploads/.htaccess'), 'Require all denied'));

    if ($performance) {
        $prefix = 'phase14_perf_';
        $stmt = db()->prepare(
            "INSERT INTO users (name, phone, password_hash, blood_group, date_of_birth, gender,
             division_id, district_id, upazila_id, status)
             VALUES (?, ?, ?, 'A+', '1990-01-01', 'male', 6, 41, 320, 'approved')"
        );
        $performanceHash = password_hash('Phase14Test!', PASSWORD_DEFAULT);
        $start = microtime(true);
        for ($i = 0; $i < 5000; $i++) {
            $stmt->execute(["{$prefix}{$i}", sprintf('017%08d', $i + 30000000), $performanceHash]);
        }
        $elapsed = microtime(true) - $start;
        $count = (int) db()->query("SELECT COUNT(*) FROM users WHERE name LIKE '{$prefix}%'")->fetchColumn();
        check_test('5,000 donor performance dataset inserted', $count === 5000);
        check_test('5,000 donor insert benchmark completes', $elapsed < 60.0);
    }
} finally {
    db()->exec("DELETE FROM users WHERE phone IN ('01739990001', '01739990002')");
    if ($performance) db()->exec("DELETE FROM users WHERE name LIKE 'phase14_perf_%'");
}

echo "\nPhase 14: {$passed} passed, {$failed} failed.\n";
ob_end_flush();
exit($failed === 0 ? 0 : 1);