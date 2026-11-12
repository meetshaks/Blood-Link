<?php
declare(strict_types=1);

/* Shared admin view helpers (UI only). */

if (!function_exists('admin_status_badge')) {
    function admin_status_badge(array $u): string
    {
        $suspended = (int) ($u['is_suspended'] ?? 0) === 1
            && (($u['suspended_until'] ?? null) === null || $u['suspended_until'] > date('Y-m-d'));
        if ($suspended) return '<span class="badge badge-danger">' . icon('ban', 14) . e(t('status_suspended')) . '</span>';
        return match ((string) $u['status']) {
            'pending' => '<span class="badge badge-pending">' . icon('hourglass', 14) . e(t('pending')) . '</span>',
            'rejected' => '<span class="badge badge-danger">' . icon('x-circle', 14) . e(t('rejected')) . '</span>',
            default => '<span class="badge badge-available">' . icon('check-circle', 14) . e(t('approved')) . '</span>',
        };
    }

    function admin_avatar(array $u): string
    {
        $initial = e(mb_strtoupper(mb_substr((string) $u['name'], 0, 1)));
        if (!empty($u['photo'])) {
            return '<span class="avatar"><img src="' . e(app_url(ltrim((string) $u['photo'], '/'))) . '" alt="" loading="lazy"></span>';
        }
        return '<span class="avatar" aria-hidden="true">' . $initial . '</span>';
    }

    function admin_district(array $u): string
    {
        return (string) (current_lang() === 'bn' ? ($u['district_bn'] ?? '') : ($u['district_en'] ?? ''));
    }

    /** POST form for a single admin action (CSRF included). $confirm adds a confirm dialog. */
    function admin_action_form(string $action, int $id, string $label, string $iconName, string $return = 'users', ?string $confirm = null, bool $danger = false, string $url = 'admin/action.php', string $class = 'menu-item'): string
    {
        return '<form method="post" action="' . e(app_url($url)) . '"' . ($confirm !== null ? ' data-confirm="' . e($confirm) . '"' . ($danger ? ' data-danger="1"' : '') : '') . '>'
            . csrf_field()
            . '<input type="hidden" name="action" value="' . e($action) . '"><input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="return" value="' . e($return) . '">'
            . '<button class="' . e($class) . ($danger && $class === 'menu-item' ? ' is-danger' : '') . '" type="submit">' . icon($iconName, 16) . '<span>' . e($label) . '</span></button></form>';
    }

    /** Opens a modal (reject / suspend). */
    function admin_modal_button(string $modal, int $id, string $name, string $label, string $iconName, string $class = 'menu-item', bool $danger = false, string $phone = ''): string
    {
        return '<button type="button" class="' . e($class) . ($danger && $class === 'menu-item' ? ' is-danger' : '') . '" data-open-modal="#' . e($modal) . '" data-fill-id="' . $id . '" data-fill-name="' . e($name) . '" data-fill-phone="' . e($phone) . '">' . icon($iconName, 16) . '<span>' . e($label) . '</span></button>';
    }

    /** The two dialogs used by admin_modal_button(). Render once per page, outside any <form>. */
    function admin_action_modals(string $return): string
    {
        $action = e(app_url('admin/action.php'));
        $csrf = csrf_field();
        $ret = '<input type="hidden" name="return" value="' . e($return) . '">';
        $reject = '<dialog id="modal-reject" class="modal-dialog" aria-labelledby="modal-reject-title"><form method="post" action="' . $action . '">' . $csrf . $ret
            . '<input type="hidden" name="action" value="reject"><input type="hidden" name="id" value="">'
            . '<h2 id="modal-reject-title">' . e(t('reject_donor')) . ': <span data-fill="name"></span></h2>'
            . '<div class="field"><label for="reject-reason">' . e(t('reason')) . '</label><textarea id="reject-reason" name="reason" maxlength="255" rows="3"></textarea></div>'
            . '<div class="dialog-actions"><button type="button" class="button button-small button-muted" data-close-modal>' . e(t('cancel')) . '</button><button class="button button-small button-danger" type="submit">' . e(t('reject')) . '</button></div></form></dialog>';
        $suspend = '<dialog id="modal-suspend" class="modal-dialog" aria-labelledby="modal-suspend-title"><form method="post" action="' . $action . '">' . $csrf . $ret
            . '<input type="hidden" name="action" value="suspend"><input type="hidden" name="id" value="">'
            . '<h2 id="modal-suspend-title">' . e(t('suspend_donor')) . ': <span data-fill="name"></span></h2>'
            . '<div class="field"><label for="suspend-months">' . e(t('suspend_duration')) . '</label><select id="suspend-months" name="months"><option value="1">1 ' . e(t('month_unit')) . '</option><option value="3">3 ' . e(t('month_unit')) . '</option><option value="6">6 ' . e(t('month_unit')) . '</option><option value="12">12 ' . e(t('month_unit')) . '</option><option value="0">' . e(t('indefinite')) . '</option></select></div>'
            . '<div class="field" style="margin-top:12px"><label for="suspend-reason">' . e(t('reason')) . '</label><textarea id="suspend-reason" name="reason" maxlength="255" rows="3"></textarea></div>'
            . '<div class="dialog-actions"><button type="button" class="button button-small button-muted" data-close-modal>' . e(t('cancel')) . '</button><button class="button button-small button-danger" type="submit">' . e(t('suspend')) . '</button></div></form></dialog>';
        $reset = '<dialog id="modal-reset-password" class="modal-dialog" aria-labelledby="modal-reset-password-title"><form method="post" action="' . $action . '">' . $csrf . $ret
            . '<input type="hidden" name="action" value="reset_password"><input type="hidden" name="id" value="">'
            . '<h2 id="modal-reset-password-title">' . e(t('reset_password')) . ': <span data-fill="name"></span></h2>'
            . '<p class="muted"><span data-fill="phone"></span></p><label class="check-row"><input type="checkbox" name="verified" value="1" required><span>' . e(t('recovery_verified')) . '</span></label><p class="field-hint">' . e(t('recovery_verified_hint')) . '</p>'
            . '<div class="field"><label for="reset-note">' . e(t('recovery_note')) . '</label><textarea id="reset-note" name="note" maxlength="200" rows="2"></textarea></div>'
            . '<div class="dialog-actions"><button type="button" class="button button-small button-muted" data-close-modal>' . e(t('cancel')) . '</button><button class="button button-small" type="submit" disabled>' . e(t('reset_password')) . '</button></div></form></dialog>';
        return $reject . $suspend . $reset;
    }

    function admin_pagination(int $page, int $perPage, int $total, array $query, string $path): string
    {
        $pages = max(1, (int) ceil($total / $perPage));
        if ($pages <= 1) return '';
        $link = static fn (int $p): string => e($path . '?' . http_build_query(array_merge($query, ['page' => $p])));
        $html = '<nav class="pagination" aria-label="' . e(t('pagination')) . '"><span class="page-info">' . e(t('page_of', ['n' => num($page), 'm' => num($pages)])) . ' · ' . e(t('total_n', ['n' => num($total)])) . '</span><div class="pagination-links">';
        $html .= $page > 1 ? '<a class="button button-secondary button-small" href="' . $link($page - 1) . '">' . icon('chevron-left', 16) . e(t('previous')) . '</a>' : '';
        $html .= $page < $pages ? '<a class="button button-secondary button-small" href="' . $link($page + 1) . '">' . e(t('next')) . icon('chevron-right', 16) . '</a>' : '';
        return $html . '</div></nav>';
    }
}
