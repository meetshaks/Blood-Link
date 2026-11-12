<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must run from the command line.\n");
    exit(1);
}

require __DIR__ . '/../app/lib/db.php';

$name = trim((string) ($argv[1] ?? ''));
$username = trim((string) ($argv[2] ?? ''));
$password = (string) ($argv[3] ?? '');

if ($name === '' || !preg_match('/^[A-Za-z0-9_.-]{3,60}$/', $username) || strlen($password) < 8) {
    fwrite(STDERR, "Usage: php tools/create_admin.php \"Name\" username password\n");
    exit(1);
}

try {
    $pdo = db();
    $stmt = $pdo->prepare(
        'INSERT INTO admins (name, username, password_hash, role) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$name, $username, password_hash($password, PASSWORD_DEFAULT), 'super']);
    echo "Super admin created.\n";
} catch (PDOException $exception) {
    if (($exception->errorInfo[1] ?? null) === 1062) {
        fwrite(STDERR, "That username already exists.\n");
    } else {
        fwrite(STDERR, "Admin creation failed.\n");
    }
    exit(1);
}
