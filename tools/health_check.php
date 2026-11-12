<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must run from the command line.\n");
    exit(1);
}

require __DIR__ . '/../app/lib/db.php';

try {
    $pdo = db();
    $result = $pdo->query('SELECT 1')->fetchColumn();

    if ((int) $result !== 1) {
        throw new RuntimeException('Unexpected database health-check response.');
    }

    printf(
        "BloodLink foundation OK\nPHP: %s\nTimezone: %s\nDatabase: connected\n",
        PHP_VERSION,
        date_default_timezone_get()
    );
} catch (Throwable $exception) {
    fwrite(STDERR, "BloodLink foundation check failed: {$exception->getMessage()}\n");
    exit(1);
}
