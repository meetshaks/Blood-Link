<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must run from the command line.\n");
    exit(1);
}

require __DIR__ . '/../app/lib/db.php';

try {
    $deletedAttempts = db()->exec("DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 30 DAY)");
    $deletedRateLimits = db()->exec("DELETE FROM api_rate_limits WHERE created_at < (NOW() - INTERVAL 2 HOUR)");
    $deletedLogs = db()->exec("DELETE FROM admin_logs WHERE created_at < (NOW() - INTERVAL 365 DAY)");
    printf(
        "Maintenance complete\nLogin attempts removed: %d\nAPI rate-limit rows removed: %d\nAudit logs removed: %d\n",
        $deletedAttempts,
        $deletedRateLimits,
        $deletedLogs
    );
} catch (Throwable $exception) {
    fwrite(STDERR, "Maintenance failed: {$exception->getMessage()}\n");
    exit(1);
}
