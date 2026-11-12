<ul class="support-list<?= $support['phone_href'] === '' ? ' support-phone-empty' : '' ?><?= $support['hours'] === '' ? ' support-hours-empty' : '' ?>">
    <?php if ($support['phone_href'] !== ''): ?>
    <li class="support-row support-row-phone"><span class="support-icon"><?= icon('phone', 14) ?></span><span class="support-text"><span class="support-label"><?= e(t('support_label_phone')) ?></span><a title="<?= e(t('support_label_phone')) ?>" aria-label="<?= e(t('support_label_phone')) ?>: <?= e($support['phone']) ?>" href="<?= e($support['phone_href']) ?>"><?= e($support['phone']) ?></a></span></li>
    <?php endif; ?>
    <?php if ($support['email_href'] !== ''): ?>
    <li class="support-row support-row-email"><span class="support-icon"><?= icon('mail', 14) ?></span><span class="support-text"><span class="support-label"><?= e(t('support_label_email')) ?></span><a title="<?= e($support['email']) ?>" aria-label="<?= e(t('support_label_email')) ?>: <?= e($support['email']) ?>" href="<?= e($support['email_href']) ?>"><?= e($support['email']) ?></a></span></li>
    <?php endif; ?>
    <?php if ($support['hours'] !== ''): ?>
    <li class="support-row support-row-hours"><span class="support-icon"><?= icon('clock', 14) ?></span><span class="support-text"><span class="support-label"><?= e(t('support_label_hours')) ?></span><span title="<?= e(t('support_label_hours')) ?>" aria-label="<?= e(t('support_label_hours')) ?>: <?= e($support['hours']) ?>"><?= e($support['hours']) ?></span></span></li>
    <?php endif; ?>
</ul>
