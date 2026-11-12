<?php
declare(strict_types=1);

/*
 * Shared layout (public + donor area + admin area).
 * Optional variables a page may set BEFORE requiring this file:
 *   $pageTitle  string        <title> prefix
 *   $adminNav   string|null   when set, the admin sidebar layout is used ('dashboard','users','add','pending','requests','logs','settings')
 *   $beforeMain string        raw HTML rendered between the header and <main> (e.g. hero strip) — build it with e()/t()
 */
$pageTitle = $pageTitle ?? t('site_name');
$htmlLang = current_lang();
$adminNav = $adminNav ?? null;
$beforeMain = $beforeMain ?? '';
$pageDescription = $pageDescription ?? t('disclaimer');
$metaDescription = $metaDescription ?? $pageDescription;
$documentTitle = str_starts_with($pageTitle, t('site_name'))
    ? $pageTitle
    : $pageTitle . ' · ' . t('site_name');
$metaImage = $metaImage ?? rtrim((string) bloodlink_config()['base_url'], '/') . '/assets/img/og-default.png';
$canonicalPath = $canonicalPath ?? request_path();
$canonicalUrl = str_starts_with((string) $canonicalPath, 'http') ? (string) $canonicalPath : app_url(ltrim((string) $canonicalPath, '/'));
$basePath = rtrim((string) parse_url(app_url(), PHP_URL_PATH), '/');
if (!str_starts_with((string) $canonicalPath, 'http') && str_starts_with((string) $canonicalPath, $basePath)) {
    $canonicalUrl = rtrim((string) bloodlink_config()['base_url'], '/') . substr((string) $canonicalPath, strlen($basePath));
}
$absolutePath = static function (string $path) use ($basePath): string {
    if (str_starts_with($path, $basePath)) return rtrim((string) bloodlink_config()['base_url'], '/') . substr($path, strlen($basePath));
    return rtrim((string) bloodlink_config()['base_url'], '/') . '/' . ltrim($path, '/');
};
$isPrivatePage = $adminNav !== null || str_contains(request_path(), '/user/') || str_contains(request_path(), '/admin/') || str_contains(request_path(), '/api/');
$ogLocale = $htmlLang === 'bn' ? 'bn_BD' : 'en_US';
$alternateLocale = $htmlLang === 'bn' ? 'en_US' : 'bn_BD';
$metaImage = $metaImage !== null && $metaImage !== '' ? $metaImage : null;
$isAdminLayout = $adminNav !== null;
$loggedIn = current_user() !== null;

$currentFile = basename(request_path());
$isCurrent = static function (string $file) use ($currentFile): bool {
    return $currentFile === basename($file);
};
$homePath = rtrim((string) parse_url(app_url(), PHP_URL_PATH), '/');
$requestPath = rtrim(request_path(), '/');
$isHome = $requestPath === $homePath || $requestPath === $homePath . '/index.php';

$publicNav = [
    ['url' => app_url(), 'label' => t('nav_search'), 'icon' => 'search', 'current' => $isHome],
    ['url' => app_url('about.php'), 'label' => t('nav_about'), 'icon' => 'info', 'current' => $isCurrent('about.php')],
];
if ($loggedIn) {
    $publicNav[] = ['url' => app_url('user/dashboard.php'), 'label' => t('dashboard_title'), 'icon' => 'user-circle', 'current' => str_contains(request_path(), '/user/')];
} else {
    $publicNav[] = ['url' => app_url('login.php'), 'label' => t('nav_login'), 'icon' => 'log-in', 'current' => $isCurrent('login.php')];
}
$registerUrl = app_url('register.php');
$adminItems = [];
$pendingCount = 0;
if ($isAdminLayout) {
    $pendingCount = (int) db()->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn();
    $adminItems = [
        ['dashboard', 'admin/dashboard.php', 'dashboard', t('admin_dashboard')],
        ['users', 'admin/users.php', 'users', t('donor_management')],
        ['add', 'admin/user_add.php', 'user-plus', t('add_donor')],
        ['pending', 'admin/pending.php', 'hourglass', t('pending_donors')],
        ['requests', 'admin/requests.php', 'refresh', t('change_requests')],
        ['logs', 'admin/logs.php', 'clipboard', t('audit_logs')],
    ];
    if (($admin['role'] ?? '') === 'super') {
        $adminItems[] = ['settings', 'admin/settings.php', 'sliders', t('system_settings')];
    }
    ob_start();
    foreach ($adminItems as [$key, $path, $ico, $label]): ?>
        <a href="<?= e(app_url($path)) ?>" <?= $adminNav === $key ? 'aria-current="page"' : '' ?>>
            <span><?= icon($ico, 18) ?><?= e($label) ?></span>
            <?php if ($key === 'pending' && $pendingCount > 0): ?><span class="count"><?= e(num($pendingCount)) ?></span><?php endif; ?>
        </a>
    <?php endforeach;
    $adminNavMarkup = ob_get_clean();
}
$jsIcons = icons_for_js(['droplet', 'map-pin', 'phone', 'calendar', 'user-x', 'check-circle', 'x-circle', 'info', 'alert', 'lock', 'chevron-left', 'chevron-right', 'spinner', 'clock', 'copy', 'user']);
?><!doctype html>
<html lang="<?= e($htmlLang) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#b71c1c">
    <script>
    try {
        const splashKey = 'bloodlink-startup-splash-shown';
        if (!sessionStorage.getItem(splashKey)) {
            sessionStorage.setItem(splashKey, '1');
            document.documentElement.dataset.appStartup = 'true';
        }
    } catch (error) {
        console.warn('BloodLink could not access its startup preference:', error);
    }
    </script>
    <meta name="description" content="<?= e($pageDescription ?? t('disclaimer')) ?>">
    <?php if ($isPrivatePage): ?><meta name="robots" content="noindex"><?php endif; ?>
    <link rel="canonical" href="<?= e($canonicalUrl) ?>">
    <meta property="og:title" content="<?= e($documentTitle) ?>">
    <meta property="og:description" content="<?= e($metaDescription) ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= e(t('site_name')) ?>">
    <meta property="og:url" content="<?= e($canonicalUrl) ?>">
    <meta property="og:locale" content="<?= e($ogLocale) ?>">
    <meta property="og:locale:alternate" content="<?= e($alternateLocale) ?>">
    <?php if ($metaImage !== null): ?>
    <meta property="og:image" content="<?= e($metaImage) ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <?php endif; ?>
    <meta name="twitter:card" content="<?= $metaImage !== null ? 'summary_large_image' : 'summary' ?>">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="<?= e(t('site_name')) ?>">
    <link rel="manifest" href="<?= e(app_url('manifest.webmanifest')) ?>" crossorigin="use-credentials">
    <link rel="apple-touch-icon" href="<?= e(app_url('assets/icons/icon-192.png?v=4')) ?>">
    <link rel="alternate" hreflang="bn" href="<?= e($absolutePath(language_url('bn'))) ?>">
    <link rel="alternate" hreflang="en" href="<?= e($absolutePath(language_url('en'))) ?>">
    <title><?= e($documentTitle) ?></title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='14' fill='%23B71C1C'/%3E%3Crect x='9' y='9' width='46' height='46' rx='11' fill='%23fff'/%3E%3Cpath transform='translate(14 14) scale(1.5)' d='M12 22a7 7 0 0 0 7-7c0-2-1-3.9-3-5.5s-3.5-4-4-6.5c-.5 2.5-2 4.9-4 6.5C6 11.1 5 13 5 15a7 7 0 0 0 7 7z' fill='%23D32F2F' stroke='%23D32F2F' stroke-width='2' stroke-linejoin='round'/%3E%3C/svg%3E">
    <link rel="preload" href="<?= e(app_url('assets/fonts/hind-siliguri-bengali-400-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= e(app_url('assets/css/fonts.css?v=2')) ?>">
    <link rel="stylesheet" href="<?= e(app_url('assets/css/base.css?v=55')) ?>">
    <script src="<?= e(app_url('assets/js/ui.js?v=12')) ?>" defer></script>
    <script src="<?= e(app_url('assets/js/pwa.js?v=9')) ?>" defer></script>
    <?php if ($isAdminLayout): ?><script src="<?= e(app_url('assets/js/dashboard.js?v=9')) ?>" defer></script><?php endif; ?>
</head>
<body class="<?= $isAdminLayout ? 'is-admin' : 'is-public' ?>"<?= ($loggedIn || $isAdminLayout) ? ' data-auth="1"' : '' ?><?= !empty($forcedPasswordChange) ? ' data-force-password="1"' : '' ?>>
<section class="app-splash" data-app-splash role="status" aria-live="polite" aria-label="<?= e(t('site_name')) ?>">
    <div class="app-splash-content">
        <img class="app-splash-logo" src="<?= e(app_url('assets/icons/icon-192.png?v=4')) ?>" alt="" width="112" height="112" fetchpriority="high">
        <strong class="app-splash-name"><?= e(t('site_name')) ?></strong>
        <span class="app-splash-tagline"><?= e(t('splash_tagline')) ?></span>
        <span class="app-splash-loading"><span class="app-splash-spinner" aria-hidden="true"></span><?= e(t('splash_loading')) ?></span>
    </div>
</section>
<script>
window.BLOODLINK_BASE_URL = <?= json_encode(rtrim((string) bloodlink_config()['base_url'], '/'), JSON_THROW_ON_ERROR) ?>;
window.BLOODLINK_UI = <?= json_encode([
    'lang' => $htmlLang,
    'cancel' => t('cancel'),
    'confirm' => t('confirm'),
    'close' => t('close'), 'copied' => t('copied'),
    'ward' => t('ward'),
    'address' => [
        'label' => t('addr_mode_label'),
        'inside' => t('addr_mode_inside'),
        'outside' => t('addr_mode_outside'),
        'hint' => t('addr_mode_hint'),
        'ward' => t('ward'),
    ],
    'show' => t('show_password'),
    'hide' => t('hide_password'),
    'icons' => $jsIcons,
    'msg' => [
        'phone' => t('err_phone'),
        'password' => t('err_password_min'),
        'match' => t('err_password_match'),
        'dob' => t('err_dob_adult'),
        'required' => t('err_required'),
    ],
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>;
</script>
<a class="skip-link" href="#main-content"><?= e(t('skip_to_content')) ?></a>
<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="<?= e($isAdminLayout ? app_url('admin/dashboard.php') : app_url()) ?>">
            <span class="brand-mark"><?= icon('droplet', 22) ?></span>
            <span>
                <span class="brand-name"><?= e(t('site_name')) ?></span>
                <span class="brand-tag"><?= e($isAdminLayout ? t('admin_area') : t('org_tagline')) ?></span>
            </span>
        </a>
        <?php if (!$isAdminLayout): ?>
        <nav class="desktop-nav" aria-label="<?= e(t('primary_nav')) ?>">
            <?php foreach ($publicNav as $item): ?>
                <a href="<?= e($item['url']) ?>" <?= $item['current'] ? 'aria-current="page"' : '' ?>><?= icon($item['icon'], 18) ?><?= e($item['label']) ?></a>
            <?php endforeach; ?>
            <?php if (!$loggedIn): ?><a class="nav-cta" href="<?= e($registerUrl) ?>"><?= icon('user-plus', 18) ?><?= e(t('nav_register')) ?></a><?php endif; ?>
        </nav>
        <?php endif; ?>
        <div class="language-switcher <?= $isAdminLayout ? 'admin-header-control' : 'header-lang-desktop' ?>" role="group" aria-label="<?= e(t('language')) ?>">
            <?= icon('globe', 16) ?>
            <a href="<?= e(language_url('bn')) ?>" hreflang="bn" lang="bn" <?= $htmlLang === 'bn' ? 'aria-current="page"' : '' ?>><?= e(t('language_bn', [], 'bn')) ?></a>
            <a href="<?= e(language_url('en')) ?>" hreflang="en" lang="en" <?= $htmlLang === 'en' ? 'aria-current="page"' : '' ?>><?= e(t('language_en', [], 'en')) ?></a>
        </div>
        <?php if ($isAdminLayout): ?>
            <a class="button button-small button-secondary admin-header-control" href="<?= e(app_url('admin/logout.php')) ?>"><?= icon('log-out', 16) ?><span class="logout-label"><?= e(t('admin_logout')) ?></span></a>
            <button class="menu-toggle admin-menu-toggle" type="button" aria-expanded="false" aria-controls="admin-drawer" aria-label="<?= e(t('menu')) ?>" data-menu-toggle>
                <?= icon('menu', 22) ?><?= icon('x', 22) ?>
            </button>
        <?php else: ?>
            <?php if ($loggedIn): ?><a class="donor-logout-desktop" href="<?= e(app_url('logout.php')) ?>" aria-label="<?= e(t('logout')) ?>"><?= icon('log-out', 16) ?><span><?= e(t('logout')) ?></span></a><?php endif; ?>
            <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="mobile-drawer" aria-label="<?= e(t('menu')) ?>" data-menu-toggle>
                <?= icon('menu', 22) ?><?= icon('x', 22) ?>
            </button>
        <?php endif; ?>
    </div>
    <?php if (!$isAdminLayout): ?>
    <div class="drawer-backdrop" data-drawer-backdrop></div>
    <aside id="mobile-drawer" class="side-drawer" role="dialog" aria-modal="true" aria-label="<?= e(t('menu')) ?>" aria-hidden="true" inert>
        <div class="side-drawer-header">
            <a class="side-drawer-brand" href="<?= e(app_url()) ?>">
                <span class="brand-mark"><?= icon('droplet', 22) ?></span>
                <span>
                    <span class="brand-name"><?= e(t('site_name')) ?></span>
                    <span class="brand-tag"><?= e(t('org_tagline')) ?></span>
                </span>
            </a>
            <button class="drawer-close" type="button" aria-label="<?= e(t('close')) ?>" data-drawer-close><?= icon('x', 22) ?></button>
        </div>
        <nav class="side-drawer-nav" aria-label="<?= e(t('primary_nav')) ?>">
            <?php foreach ($publicNav as $item): ?>
                <a href="<?= e($item['url']) ?>" <?= $item['current'] ? 'aria-current="page"' : '' ?>><?= icon($item['icon'], 20) ?><span><?= e($item['label']) ?></span></a>
            <?php endforeach; ?>
            <?php if (!$loggedIn): ?><a class="side-drawer-register" href="<?= e($registerUrl) ?>"><?= icon('user-plus', 20) ?><span><?= e(t('nav_register')) ?></span></a><?php endif; ?>
        </nav>
        <div class="side-drawer-bottom">
            <div class="drawer-language" role="group" aria-label="<?= e(t('language')) ?>">
                <a href="<?= e(language_url('bn')) ?>" hreflang="bn" lang="bn" <?= $htmlLang === 'bn' ? 'aria-current="page"' : '' ?>><?= e(t('language_bn', [], 'bn')) ?></a>
                <a href="<?= e(language_url('en')) ?>" hreflang="en" lang="en" <?= $htmlLang === 'en' ? 'aria-current="page"' : '' ?>><?= e(t('language_en', [], 'en')) ?></a>
            </div>
            <?php if ($loggedIn): ?><a class="drawer-logout" href="<?= e(app_url('logout.php')) ?>" aria-label="<?= e(t('logout')) ?>"><?= icon('log-out', 20) ?><span><?= e(t('logout')) ?></span></a><?php endif; ?>
        </div>
    </aside>
    <?php endif; ?>
    <?php if ($isAdminLayout): ?>
    <div class="drawer-backdrop" data-drawer-backdrop></div>
    <aside id="admin-drawer" class="side-drawer" role="dialog" aria-modal="true" aria-label="<?= e(t('admin_menu')) ?>" aria-hidden="true" inert>
        <div class="side-drawer-header">
            <a class="side-drawer-brand" href="<?= e(app_url('admin/dashboard.php')) ?>">
                <span class="brand-mark"><?= icon('droplet', 22) ?></span>
                <span>
                    <span class="brand-name"><?= e(t('site_name')) ?></span>
                    <span class="brand-tag"><?= e(t('admin_area')) ?></span>
                </span>
            </a>
            <button class="drawer-close" type="button" aria-label="<?= e(t('close')) ?>" data-drawer-close><?= icon('x', 22) ?></button>
        </div>
        <nav class="side-drawer-nav admin-drawer-nav" aria-label="<?= e(t('admin_menu')) ?>">
            <?= $adminNavMarkup ?>
        </nav>
        <div class="side-drawer-bottom">
            <div class="drawer-language" role="group" aria-label="<?= e(t('language')) ?>">
                <a href="<?= e(language_url('bn')) ?>" hreflang="bn" lang="bn" <?= $htmlLang === 'bn' ? 'aria-current="page"' : '' ?>><?= e(t('language_bn', [], 'bn')) ?></a>
                <a href="<?= e(language_url('en')) ?>" hreflang="en" lang="en" <?= $htmlLang === 'en' ? 'aria-current="page"' : '' ?>><?= e(t('language_en', [], 'en')) ?></a>
            </div>
            <a class="drawer-logout" href="<?= e(app_url('admin/logout.php')) ?>"><?= icon('log-out', 20) ?><span><?= e(t('admin_logout')) ?></span></a>
        </div>
    </aside>
    <?php endif; ?>
</header>
<?= $beforeMain /* trusted markup built by the calling page with e() */ ?>
<?php if ($isAdminLayout):
?>
<div class="container admin-shell">
    <aside class="card admin-sidebar">
        <div class="side-title"><span><?= icon('shield', 18) ?><?= e(t('admin_menu')) ?></span></div>
        <nav class="admin-nav" aria-label="<?= e(t('admin_menu')) ?>">
            <?= $adminNavMarkup ?>
        </nav>
    </aside>
    <main id="main-content" class="admin-main">
<?php else: ?>
<main id="main-content" class="container">
<?php endif; ?>
<?php foreach (flash_all() as $type => $message):
    $kind = match ($type) { 'success' => 'success', 'info' => 'info', 'warning' => 'warning', default => 'error' };
    $ico = match ($kind) { 'success' => 'check-circle', 'info' => 'info', 'warning' => 'alert', default => 'alert' };
?>
    <div class="alert alert-<?= e($kind) ?>" role="<?= $kind === 'error' ? 'alert' : 'status' ?>"><?= icon($ico, 18) ?><span><?= e($message) ?></span></div>
<?php endforeach; ?>
