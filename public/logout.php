<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';
no_store_headers();
if (in_array(strtolower((string) ($_SERVER['HTTPS'] ?? '')), ['on', '1'], true) || in_array(strtolower((string) ($_SERVER['HTTP_HOST'] ?? '')), ['localhost', 'localhost:80', 'localhost:443'], true)) {
    header('Clear-Site-Data: "cache"');
}
destroy_session();
flash('success', t('logged_out'));
redirect('/login.php');
