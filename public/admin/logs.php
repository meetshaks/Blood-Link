<?php
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/lib/admin.php';
$admin = require_admin();
$logs = db()->query(
    "SELECT l.created_at,
            CAST(l.action AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS action,
            l.target_user_id,
            CAST(l.ip_address AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS ip_address,
            CAST(a.username AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS username,
            CAST('admin' AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS actor_type
     FROM admin_logs l JOIN admins a ON a.id = l.admin_id
     UNION ALL
     SELECT l.created_at,
            CAST(l.action AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS action,
            l.user_id AS target_user_id,
            CAST(l.ip_address AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS ip_address,
            CAST(CONCAT('User: ', u.name) AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS username,
            CAST('user' AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS actor_type
     FROM user_activity_log l JOIN users u ON u.id = l.user_id
     ORDER BY created_at DESC LIMIT 200"
)->fetchAll();
$q = trim((string) ($_GET['q'] ?? ''));
if ($q !== '') {
    $logs = array_values(array_filter($logs, static fn (array $l): bool => stripos((string) $l['action'], $q) !== false || stripos((string) $l['username'], $q) !== false));
}
$pageTitle = t('audit_logs');
$adminNav = 'logs';
require __DIR__ . '/../../app/views/layout/header.php';
?>
<div class="admin-heading"><h1><?= e(t('audit_logs')) ?></h1></div>
<section class="card">
    <form class="admin-filters" method="get" role="search" style="grid-template-columns:1fr auto">
        <div class="field"><label class="sr-only" for="q"><?= e(t('search_logs')) ?></label><input id="q" name="q" type="search" value="<?= e($q) ?>" placeholder="<?= e(t('search_logs')) ?>"></div>
        <button class="button" type="submit"><?= icon('search', 18) ?><?= e(t('filter')) ?></button>
    </form>
    <?php if ($logs === []): ?><div class="empty-state"><div class="card-icon"><?= icon('clipboard', 26) ?></div><p><?= e(t('no_logs')) ?></p></div><?php else: ?>
    <div class="table-wrap">
        <table class="data-table responsive-table">
            <thead><tr><th><?= e(t('col_time')) ?></th><th><?= e(t('username')) ?></th><th><?= e(t('col_action')) ?></th><th><?= e(t('col_target')) ?></th><th>IP</th></tr></thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td class="cell-main log-time"><?= e(date_fmt((string) $log['created_at'])) ?> <?= e(substr((string) $log['created_at'], 11, 5)) ?></td>
                    <td data-label="<?= e(t('username')) ?>"><?= e($log['username']) ?></td>
                    <td data-label="<?= e(t('col_action')) ?>"><span class="badge badge-unavailable"><?= e(match ((string) $log['action']) { 'user_password_reset' => t('log_user_password_reset'), default => (string) $log['action'] }) ?></span></td>
                    <td data-label="<?= e(t('col_target')) ?>"><?= $log['target_user_id'] ? '#' . (int) $log['target_user_id'] : '—' ?></td>
                    <td data-label="IP" class="muted"><?= e($log['ip_address'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../../app/views/layout/footer.php'; ?>
