<?php
declare(strict_types=1);
$adminNav = $adminNav ?? null;
?>
</main>
<?php if ($adminNav !== null): ?></div><?php endif; ?>
<?php if ($adminNav !== null): ?>
<footer class="site-footer site-footer-admin"><div class="container footer-admin-inner"><?= icon('droplet', 16) ?><?= e(t('site_name')) ?> · <?= e(t('admin_area')) ?></div></footer>
<?php else:
    $support = support_contact();
    $hasSupport = $support['phone_href'] !== '' || $support['email_href'] !== '' || $support['hours'] !== '';
    $loggedIn = current_user() !== null;
?>
<footer class="site-footer" aria-label="<?= e(t('footer_landmark')) ?>">
    <div class="container footer-top<?= $hasSupport ? '' : ' footer-no-support' ?>">
        <section class="footer-column footer-brand-column" aria-labelledby="footer-brand-title">
            <div class="footer-brand-intro">
                <h2 id="footer-brand-title" class="footer-heading"><span class="footer-mark"><?= icon('droplet', 20) ?></span><?= e(t('site_name')) ?></h2>
                <p><?= e(t('footer_mission')) ?></p>
            </div>
            <a class="footer-cta" href="<?= e(app_url($loggedIn ? 'user/dashboard.php' : 'register.php')) ?>"><?= icon($loggedIn ? 'user-circle' : 'user-plus', 16) ?><span class="footer-cta-long"><?= e(t($loggedIn ? 'dashboard_title' : 'become_donor_btn')) ?></span><span class="footer-cta-short"><?= e(t($loggedIn ? 'footer_dashboard_short' : 'footer_join_short')) ?></span></a>
        </section>
        <nav class="footer-column" aria-labelledby="footer-links-title">
            <h2 id="footer-links-title" class="footer-heading footer-links-heading"><?= e(t('footer_quick_links')) ?></h2>
            <div class="footer-links-list">
                <a href="<?= e(app_url()) ?>"><?= e(t('nav_search')) ?></a>
                <a href="<?= e(app_url('about.php')) ?>"><?= e(t('nav_about')) ?></a>
                <a href="<?= e(app_url('about.php#faq-title')) ?>"><?= e(t('faq_title')) ?></a>
                <a href="<?= e(app_url('about.php#eligibility-title')) ?>"><?= e(t('eligibility_title')) ?></a>
                <?php if (!$loggedIn): ?><a href="<?= e(app_url('register.php')) ?>"><?= e(t('nav_register')) ?></a><a href="<?= e(app_url('login.php')) ?>"><?= e(t('nav_login')) ?></a><?php else: ?><a href="<?= e(app_url('register.php')) ?>"><?= e(t('nav_register')) ?></a><a href="<?= e(app_url('user/dashboard.php')) ?>"><?= e(t('dashboard_title')) ?></a><?php endif; ?>
            </div>
        </nav>
        <?php if ($hasSupport): ?>
        <section class="footer-column" aria-labelledby="footer-support-title">
            <h2 id="footer-support-title" class="footer-heading"><?= e(t('footer_support')) ?><button type="button" class="link-button footer-heading-note" data-open-modal="#account-help-dialog" aria-hidden="false"><?= e(t('footer_support_note')) ?></button></h2>
            <div class="support-card"><?php require __DIR__ . '/../partials/support_contact.php'; ?></div>
        </section>
        <?php endif; ?>
    </div>
    <div class="container footer-app-install">
        <a href="#pwa-install-banner" data-pwa-install-open><?= icon('download', 16) ?><span><?= e(t('pwa_install_title')) ?></span></a>
    </div>
</footer>
<?php require __DIR__ . '/../partials/account_help_dialog.php'; ?>
<aside id="pwa-install-banner" class="pwa-install-banner" data-pwa-install-banner data-ios-description="<?= e(t('pwa_install_ios_description')) ?>" data-ios-action="<?= e(t('pwa_install_ios_button')) ?>" data-ios-done="<?= e(t('pwa_install_ios_done')) ?>" data-manual-description="<?= e(t('pwa_install_manual_description')) ?>" data-manual-action="<?= e(t('pwa_install_manual_button')) ?>" data-install-failed="<?= e(t('pwa_install_failed')) ?>" role="region" aria-label="<?= e(t('pwa_install_title')) ?>" hidden>
    <img class="pwa-install-icon" src="<?= e(app_url('assets/icons/icon-192.png?v=4')) ?>" alt="" aria-hidden="true">
    <span class="pwa-install-copy">
        <strong data-pwa-install-title><?= e(t('pwa_install_title')) ?></strong>
        <span data-pwa-install-description><?= e(t('pwa_install_description')) ?></span>
    </span>
    <button class="button button-small pwa-install-action" type="button" data-pwa-install-action><?= e(t('pwa_install_button')) ?></button>
    <button class="pwa-install-dismiss" type="button" data-pwa-install-dismiss aria-label="<?= e(t('close')) ?>"><?= icon('x', 18) ?></button>
</aside>
<?php endif; ?>
<aside class="pwa-update-banner" data-pwa-update-banner data-busy-message="<?= e(t('pwa_update_busy')) ?>" data-failed-message="<?= e(t('update_failed')) ?>" role="region" aria-live="polite" aria-label="<?= e(t('pwa_update_title')) ?>" hidden>
    <span class="pwa-update-copy">
        <strong><?= e(t('pwa_update_title')) ?></strong>
        <span><?= e(t('pwa_update_description')) ?></span>
        <span class="pwa-update-status" data-pwa-update-status role="status"></span>
    </span>
    <span class="pwa-update-actions">
        <button class="button button-small button-secondary" type="button" data-pwa-update-later><?= e(t('pwa_update_later')) ?></button>
        <button class="button button-small" type="button" data-pwa-update-now><?= e(t('pwa_update_now')) ?></button>
    </span>
</aside>
</body>
</html>
