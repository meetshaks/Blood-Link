<?php
declare(strict_types=1);

function app_log(string $message, string $level = 'INFO', array $context = []): void
{
    $entry = sprintf(
        '[%s] %s %s %s',
        date('c'),
        strtoupper($level),
        $message,
        $context === [] ? '' : json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
    error_log(trim($entry));
}

function log_admin_action(
    int $adminId,
    string $action,
    ?int $targetUserId = null,
    array $details = []
): void {
    require_once __DIR__ . '/db.php';
    $stmt = db()->prepare(
        'INSERT INTO admin_logs (admin_id, action, target_user_id, details, ip_address)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $adminId,
        $action,
        $targetUserId,
        $details === [] ? null : json_encode($details, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        client_ip(),
    ]);
}

function log_user_activity(int $userId, string $action, array $details = []): void
{
    require_once __DIR__ . '/db.php';
    $stmt = db()->prepare(
        'INSERT INTO user_activity_log (user_id, action, details, ip_address)
         VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([
        $userId,
        $action,
        $details === [] ? null : json_encode($details, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        client_ip(),
    ]);
}

function mask_phone(string $phone): string
{
    return strlen($phone) >= 7 ? substr($phone, 0, 3) . '****' . substr($phone, -3) : '****';
}
