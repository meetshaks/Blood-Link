<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must run from the command line.\n");
    exit(1);
}

require __DIR__ . '/../app/lib/db.php';

$pdo = db();
$demoPhones = [
    '01710000001', '01710000002', '01710000003',
    '01710000004', '01710000005', '01710000006',
];
$demoAdminUsername = 'demo_admin';
$userPassword = 'DemoUser!2026';
$adminPassword = 'DemoAdmin!2026';

try {
    $pdo->beginTransaction();
    $placeholders = implode(',', array_fill(0, count($demoPhones), '?'));
    $delete = $pdo->prepare("DELETE FROM users WHERE phone IN ({$placeholders})");
    $delete->execute($demoPhones);

    $admin = $pdo->prepare('SELECT id FROM admins WHERE username = ?');
    $admin->execute([$demoAdminUsername]);
    $adminId = $admin->fetchColumn();
    if ($adminId === false) {
        $createAdmin = $pdo->prepare(
            'INSERT INTO admins (name, username, password_hash, role) VALUES (?, ?, ?, ?)'
        );
        $createAdmin->execute([
            'Demo Administrator',
            $demoAdminUsername,
            password_hash($adminPassword, PASSWORD_DEFAULT),
            'super',
        ]);
        $adminId = $pdo->lastInsertId();
    } else {
        $pdo->prepare('UPDATE admins SET password_hash = ?, is_active = 1, role = ? WHERE id = ?')
            ->execute([password_hash($adminPassword, PASSWORD_DEFAULT), 'super', $adminId]);
    }

    $users = [
        [
            'Demo Available A+', '01710000001', 'A+', '1992-03-14', 'male',
            6, 41, 320, 3053, null, 'Kaliganj, Gazipur', 'approved', 1, 0, null,
        ],
        [
            'Demo Recent Donation', '01710000002', 'B+', '1990-08-21', 'female',
            6, 41, 321, null, null, 'Kaliakair, Gazipur', 'approved', 1, 0, null,
        ],
        [
            'Demo Pending Donor', '01710000003', 'O+', '1996-11-02', 'male',
            6, 41, 322, null, null, 'Kapasia, Gazipur', 'pending', 1, 0, null,
        ],
        [
            'Demo Rejected Donor', '01710000004', 'AB-', '1988-05-09', 'female',
            6, 41, 323, null, null, 'Gazipur Sadar', 'rejected', 1, 0, 'Example rejection',
        ],
        [
            'Demo Suspended Donor', '01710000005', 'A-', '1991-12-30', 'male',
            6, 41, 324, null, null, 'Sreepur, Gazipur', 'approved', 1, 1, 'Example suspension',
        ],
        [
            'Demo City Corporation', '01710000006', 'O+', '1994-07-18', 'female',
            2, 15, 135, null, 3, 'Rajshahi City Corporation', 'approved', 1, 0, null,
        ],
    ];
    $insert = $pdo->prepare(
        'INSERT INTO users
         (name, phone, password_hash, blood_group, date_of_birth, gender,
          division_id, district_id, upazila_id, union_id, city_corporation_id,
          village, status, available_toggle, is_suspended, suspend_reason,
          approved_by, approved_at, reject_reason)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $userIds = [];
    foreach ($users as $user) {
        $insert->execute([
            $user[0], $user[1], password_hash($userPassword, PASSWORD_DEFAULT),
            $user[2], $user[3], $user[4], $user[5], $user[6], $user[7],
            $user[8], $user[9], $user[10], $user[11], $user[12], $user[13],
            $user[14], in_array($user[11], ['approved', 'rejected'], true) ? $adminId : null,
            in_array($user[11], ['approved', 'rejected'], true) ? date('Y-m-d H:i:s') : null,
            $user[14] === null ? null : $user[14],
        ]);
        $userIds[$user[1]] = (int) $pdo->lastInsertId();
    }

    $donation = $pdo->prepare(
        'INSERT INTO donations (user_id, donation_date, place, note, added_by)
         VALUES (?, ?, ?, ?, ?)'
    );
    $donation->execute([$userIds['01710000002'], '2026-10-15', 'Demo Medical College', 'Recent demo donation', 'admin']);
    $donation->execute([$userIds['01710000001'], '2025-01-10', 'Gazipur Blood Bank', 'Historical demo donation', 'admin']);
    $pdo->prepare('UPDATE users SET last_donation_date = ? WHERE id = ?')
        ->execute(['2026-10-15', $userIds['01710000002']]);
    $pdo->prepare('UPDATE users SET last_donation_date = ? WHERE id = ?')
        ->execute(['2025-01-10', $userIds['01710000001']]);

    $pdo->commit();
    echo "Demo data loaded successfully.\n\n";
    echo "Demo admin: {$demoAdminUsername} / {$adminPassword}\n";
    echo "Demo donor password: {$userPassword}\n";
    echo "Demo donor phones:\n";
    foreach ($demoPhones as $phone) echo "  {$phone}\n";
    echo "\nDemo records are safe to reload; the six demo donors are replaced each run.\n";
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "Demo data load failed: {$exception->getMessage()}\n");
    exit(1);
}
