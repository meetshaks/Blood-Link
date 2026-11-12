<?php
declare(strict_types=1);

require __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/lib/admin.php';
no_store_headers();
if (in_array(strtolower((string) ($_SERVER['HTTPS'] ?? '')), ['on', '1'], true) || in_array(strtolower((string) ($_SERVER['HTTP_HOST'] ?? '')), ['localhost', 'localhost:80', 'localhost:443'], true)) {
    header('Clear-Site-Data: "cache"');
}
$admin = current_admin();
if ($admin !== null) log_admin_action((int) $admin['id'], 'admin_logout');
destroy_session();
redirect('/admin/login.php');
