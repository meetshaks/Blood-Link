<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';
http_response_code(404);
$pageTitle = 'Page not found';
require __DIR__ . '/../app/views/layout/header.php';
?>
<section class="error-page">
    <p class="error-code">404</p>
    <h1><?= e(t('err_404_title')) ?></h1>
    <p class="muted"><?= e(t('err_404_text')) ?></p>
    <div class="button-row" style="justify-content:center"><a class="button" href="<?= e(app_url()) ?>"><?= icon('search', 18) ?><?= e(t('back_home')) ?></a></div>
</section>
<?php require __DIR__ . '/../app/views/layout/footer.php'; ?>
