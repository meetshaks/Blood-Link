<?php
declare(strict_types=1);
$accountHelpSupport = support_contact();
?>
<dialog id="account-help-dialog" class="modal-dialog account-help-dialog" aria-labelledby="account-help-title">
    <div class="account-help-content">
        <div class="account-help-header">
            <h2 id="account-help-title"><?= e(t('account_help_title')) ?></h2>
            <button type="button" class="modal-close" aria-label="<?= e(t('close')) ?>" data-close-modal><?= icon('x', 20) ?></button>
        </div>
        <p class="account-help-intro"><?= e(t('account_help_intro')) ?></p>
        <ol class="account-help-steps">
            <li><?= e(t('recovery_step_1')) ?></li>
            <li><?= e(t('recovery_step_2')) ?></li>
            <li><?= e(t('recovery_step_3')) ?></li>
        </ol>
        <p class="account-help-security"><?= e(t('account_help_security')) ?></p>
        <?php if ($accountHelpSupport['phone_href'] === '' && $accountHelpSupport['email_href'] === '' && $accountHelpSupport['hours'] === ''): ?>
            <p class="muted"><?= e(t('support_contact_soon')) ?></p>
        <?php else: ?>
            <div class="account-help-contact">
                <?php if ($accountHelpSupport['phone_href'] !== ''): ?><a class="button button-block account-help-phone" href="<?= e($accountHelpSupport['phone_href']) ?>"><?= icon('phone', 17) ?><span><?= e(t('account_help_call')) ?> <?= e($accountHelpSupport['phone']) ?></span></a><?php endif; ?>
                <?php if ($accountHelpSupport['email_href'] !== ''): ?><a class="button button-block button-secondary account-help-email" href="<?= e($accountHelpSupport['email_href']) ?>"><?= icon('mail', 17) ?><span><?= e(t('account_help_email')) ?> <?= e($accountHelpSupport['email']) ?></span></a><?php endif; ?>
                <?php if ($accountHelpSupport['hours'] !== ''): ?><p class="account-help-hours"><?= icon('clock', 16) ?><span><?= e(t('support_label_hours')) ?>: <?= e($accountHelpSupport['hours']) ?></span></p><?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</dialog>
