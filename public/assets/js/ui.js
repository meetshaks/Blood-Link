/*
 * BloodLink shared UI behaviour (vanilla JS, CSP-safe).
 * Exposes window.BloodLinkUI = { toast, busy, confirm, icon }.
 * Strings come from window.BLOODLINK_UI (server-side t() output) — nothing is hard-coded here.
 */
(() => {
    'use strict';
    window.addEventListener('pageshow', event => {
        if (event.persisted && document.body?.dataset.auth === '1') window.location.reload();
    });
    if (window.location.hash === '#recovery') document.addEventListener('DOMContentLoaded', () => document.querySelector('#recovery')?.setAttribute('open', ''));
    const cfg = window.BLOODLINK_UI || { icons: {}, msg: {}, cancel: 'Cancel', confirm: 'OK', close: 'Close', show: 'Show', hide: 'Hide' };

    const icon = (name, size = 18) => {
        const inner = cfg.icons && cfg.icons[name] ? cfg.icons[name] : '';
        return `<svg class="icon" width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">${inner}</svg>`;
    };

    /* ---------- Toasts ---------- */
    const region = document.createElement('div');
    region.className = 'toast-region';
    document.body.appendChild(region);
    const toastIcons = { success: 'check-circle', error: 'x-circle', info: 'info' };

    const toast = (message, type = 'info') => {
        const item = document.createElement('div');
        item.className = `toast toast-${type}`;
        item.setAttribute('role', type === 'error' ? 'alert' : 'status');
        item.innerHTML = icon(toastIcons[type] || 'info', 20) + '<span></span>';
        item.querySelector('span').textContent = message;
        region.appendChild(item);
        window.setTimeout(() => {
            item.classList.add('is-leaving');
            window.setTimeout(() => item.remove(), 220);
        }, 4000);
    };

    const busy = (button, state) => {
        if (!button) return;
        button.disabled = state;
        button.toggleAttribute('data-loading', state);
        button.setAttribute('aria-busy', state ? 'true' : 'false');
    };

    /* ---------- Confirm dialog (<dialog>, Esc closes, focus on Cancel) ---------- */
    const confirmDialog = (message, options = {}) => new Promise(resolve => {
        const dialog = document.createElement('dialog');
        dialog.className = 'confirm-dialog';
        dialog.setAttribute('aria-label', message);
        dialog.innerHTML = '<form method="dialog"><p></p><div class="dialog-actions"><button value="cancel" class="button button-small button-muted"></button><button value="confirm" class="button button-small"></button></div></form>';
        dialog.querySelector('p').textContent = message;
        dialog.querySelector('[value="cancel"]').textContent = cfg.cancel;
        const ok = dialog.querySelector('[value="confirm"]');
        ok.textContent = cfg.confirm;
        if (options.danger) ok.classList.add('button-danger');
        dialog.addEventListener('close', () => { dialog.remove(); resolve(dialog.returnValue === 'confirm'); }, { once: true });
        document.body.appendChild(dialog);
        dialog.showModal();
        dialog.querySelector('[value="cancel"]').focus();
    });

    window.BloodLinkUI = { toast, busy, confirm: confirmDialog, icon };

    document.addEventListener('click', async event => {
        const button = event.target.closest('[data-copy-url]');
        if (!button) return;
        try {
            await navigator.clipboard.writeText(button.dataset.copyUrl || '');
            toast(cfg.copied, 'success');
        } catch {
            toast(cfg.copied, 'info');
        }
    });

    /* ---------- Side drawers ---------- */
    const focusableSelector = 'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])';
    const backdrop = document.querySelector('[data-drawer-backdrop]');
    const drawers = [...document.querySelectorAll('.side-drawer')].map(drawer => {
        const toggle = document.querySelector(`[data-menu-toggle][aria-controls="${drawer.id}"]`);
        if (!toggle) return null;
        const closeButton = drawer.querySelector('[data-drawer-close]');
        let lastFocus = null;
        const setOpen = open => {
            if (open) drawers.forEach(other => other?.close(false));
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            drawer.dataset.open = open ? 'true' : 'false';
            if (open) backdrop?.setAttribute('data-open', 'true');
            else if (!drawers.some(other => other?.isOpen())) backdrop?.removeAttribute('data-open');
            drawer.setAttribute('aria-hidden', open ? 'false' : 'true');
            drawer.inert = !open;
            document.documentElement.classList.toggle('drawer-open', open);
            if (open) {
                lastFocus = document.activeElement;
                window.setTimeout(() => closeButton?.focus(), 0);
            } else {
                lastFocus?.focus?.();
                lastFocus = null;
            }
        };
        const drawerState = {
            isOpen: () => toggle.getAttribute('aria-expanded') === 'true',
            close: (restore = true) => {
                if (!drawerState.isOpen()) return;
                if (!restore) lastFocus = null;
                setOpen(false);
            },
        };
        toggle.addEventListener('click', () => setOpen(!drawerState.isOpen()));
        closeButton?.addEventListener('click', () => drawerState.close());
        drawer.querySelectorAll('a').forEach(link => link.addEventListener('click', () => drawerState.close()));
        drawer.addEventListener('keydown', event => {
            if (event.key === 'Escape') {
                event.preventDefault();
                drawerState.close();
                return;
            }
            if (event.key !== 'Tab') return;
            const focusable = [...drawer.querySelectorAll(focusableSelector)].filter(element => element.offsetParent !== null);
            if (!focusable.length) return;
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        });
        return drawerState;
    }).filter(Boolean);
    backdrop?.addEventListener('click', () => drawers.forEach(drawer => drawer.close()));
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') drawers.forEach(drawer => drawer.close());
    });
    const media = window.matchMedia('(min-width: 900px)');
    const onMediaChange = event => { if (event.matches) drawers.forEach(drawer => drawer.close()); };
    if (media.addEventListener) media.addEventListener('change', onMediaChange);
    else media.addListener?.(onMediaChange);

    /* ---------- Password visibility ---------- */
    document.querySelectorAll('[data-toggle-password]').forEach(button => {
        const input = document.getElementById(button.dataset.togglePassword);
        if (!input) return;
        const render = shown => {
            button.innerHTML = shown ? button.dataset.iconHide : button.dataset.iconShow;
            button.setAttribute('aria-label', shown ? cfg.hide : cfg.show);
            button.setAttribute('aria-pressed', shown ? 'true' : 'false');
        };
        button.dataset.iconShow = button.querySelector('[data-icon-show]')?.outerHTML || '';
        button.dataset.iconHide = button.querySelector('[data-icon-hide]')?.outerHTML || '';
        render(false);
        button.addEventListener('click', () => { input.type = input.type === 'password' ? 'text' : 'password'; render(input.type === 'text'); });
    });

    /* ---------- Photo preview (FileReader → data: URL; blob: is blocked by CSP) ---------- */
    document.querySelectorAll('input[type="file"][data-preview]').forEach(input => {
        const box = document.querySelector(input.dataset.preview);
        if (!box) return;
        input.addEventListener('change', () => {
            const file = input.files && input.files[0];
            if (!file || !file.type.startsWith('image/')) return;
            const reader = new FileReader();
            reader.onload = () => { box.innerHTML = ''; const img = document.createElement('img'); img.alt = ''; img.src = String(reader.result); box.appendChild(img); };
            reader.readAsDataURL(file);
        });

        document.querySelectorAll('[data-phone-change]').forEach(form => {
            const phone = form.querySelector('[name="phone"]');
            const current = form.querySelector('#phone-password-field');
            if (!phone || !current) return;
            const original = phone.value;
            const render = () => { current.hidden = phone.value.replace(/[\s-]+/g, '') === original.replace(/[\s-]+/g, ''); };
            phone.addEventListener('input', render);
            render();
        });
    });

    /* ---------- Forms that need a confirmation before submit ---------- */
    document.addEventListener('submit', async event => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.dataset.confirm || form.dataset.confirmed === '1') return;
        event.preventDefault();
        if (await confirmDialog(form.dataset.confirm, { danger: form.dataset.danger === '1' })) {
            form.dataset.confirmed = '1';
            form.submit();
        }
    });

    /* ---------- Generic modal (admin reject / suspend etc.) ---------- */
    const modalOpeners = new WeakMap();
    const modalFocusables = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
    document.addEventListener('click', event => {
        const opener = event.target.closest('[data-open-modal]');
        if (opener) {
            const dialog = document.querySelector(opener.dataset.openModal);
            if (!dialog) return;
            modalOpeners.set(dialog, opener);
            if (!dialog.dataset.modalReady) {
                dialog.dataset.modalReady = '1';
                dialog.addEventListener('close', () => modalOpeners.get(dialog)?.focus?.());
            }
            Object.entries(opener.dataset).forEach(([key, value]) => {
                if (!key.startsWith('fill')) return;
                const name = key.slice(4).replace(/^./, c => c.toLowerCase());
                dialog.querySelectorAll(`[data-fill="${name}"]`).forEach(el => { el.textContent = value; });
                dialog.querySelectorAll(`input[name="${name}"], select[name="${name}"]`).forEach(el => { el.value = value; });
            });
            document.addEventListener('change', event => {
                const checkbox = event.target.closest('#modal-reset-password input[name="verified"]');
                if (!checkbox) return;
                const submit = checkbox.closest('form')?.querySelector('button[type="submit"]');
                if (submit) submit.disabled = !checkbox.checked;
            });
            const details = opener.closest('details');
            if (details) details.open = false;
            dialog.showModal();
            const first = dialog.querySelector('[data-modal-initial-focus], [data-close-modal], textarea, input:not([type="hidden"]), select');
            window.setTimeout(() => first?.focus(), 0);
            return;
        }
        const closer = event.target.closest('[data-close-modal]');
        if (closer) { closer.closest('dialog')?.close(); return; }
        if (event.target instanceof HTMLDialogElement && event.target.classList.contains('modal-dialog')) event.target.close();
    });
    document.addEventListener('keydown', event => {
        const dialog = document.querySelector('dialog.modal-dialog[open]');
        if (!dialog) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            dialog.close();
            return;
        }
        if (event.key !== 'Tab') return;
        const focusable = [...dialog.querySelectorAll(modalFocusables)].filter(element => element.offsetParent !== null);
        if (!focusable.length) return;
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });

    /* ---------- Close open row-action menus on outside click / Esc ---------- */
    document.addEventListener('click', event => {
        document.querySelectorAll('details.row-actions[open]').forEach(d => { if (!d.contains(event.target)) d.open = false; });
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') document.querySelectorAll('details.row-actions[open]').forEach(d => { d.open = false; });
    });

    const bulkForm = document.querySelector('#bulk-pending-form');
    if (bulkForm) {
        const all = document.querySelector('#select-all-pending');
        const checks = [...document.querySelectorAll('.pending-check')];
        const counter = document.querySelector('#pending-selected');
        const submit = document.querySelector('#approve-selected');
        const update = () => {
            const count = checks.filter(check => check.checked).length;
            counter.textContent = counter.textContent.replace(/\d+|[০-৯]+/, String(count));
            submit.disabled = count === 0;
            all.checked = count > 0 && count === checks.length;
            all.indeterminate = count > 0 && count < checks.length;
        };
        all.addEventListener('change', () => { checks.forEach(check => { check.checked = all.checked; }); update(); });
        checks.forEach(check => check.addEventListener('change', update));
        update();
    }

    /* ---------- Inline validation on blur ---------- */
    const setError = (input, message) => {
        const field = input.closest('.field');
        if (!field) return;
        let slot = field.querySelector('.field-error');
        if (!slot) { slot = document.createElement('p'); slot.className = 'field-error'; slot.setAttribute('role', 'alert'); field.appendChild(slot); }
        field.classList.toggle('has-error', Boolean(message));
        slot.innerHTML = message ? icon('alert', 14) + '<span></span>' : '';
        if (message) slot.querySelector('span').textContent = message;
        input.setAttribute('aria-invalid', message ? 'true' : 'false');
    };
    const rules = {
        phone: input => (/^(\+?88)?01[3-9][0-9]{8}$/.test(input.value.trim()) ? '' : cfg.msg.phone),
        password: input => (input.value.length >= 8 ? '' : cfg.msg.password),
        match: input => { const other = document.getElementById(input.dataset.matches); return other && other.value === input.value ? '' : cfg.msg.match; },
        dob: input => {
            if (!input.value) return cfg.msg.required;
            const limit = new Date(); limit.setFullYear(limit.getFullYear() - 18);
            return new Date(input.value) <= limit ? '' : cfg.msg.dob;
        },
        required: input => (input.value.trim() ? '' : cfg.msg.required),
    };
    document.querySelectorAll('[data-validate]').forEach(input => {
        const run = () => { const rule = rules[input.dataset.validate]; if (rule) setError(input, input.value === '' && input.dataset.validate !== 'dob' && !input.required ? '' : rule(input)); };
        input.addEventListener('blur', run);
        input.addEventListener('input', () => { if (input.closest('.field')?.classList.contains('has-error')) run(); });
    });
})();

/* ---------- Copy-to-clipboard buttons: <button data-copy="#selector"> ---------- */
document.addEventListener('click', async event => {
    const button = event.target.closest('[data-copy]');
    if (!button) return;
    const source = document.querySelector(button.dataset.copy);
    if (!source) return;
    try { await navigator.clipboard.writeText(source.textContent.trim()); window.BloodLinkUI.toast(window.BLOODLINK_UI.copied || 'OK', 'success'); }
    catch { window.BloodLinkUI.toast(source.textContent.trim(), 'info'); }
});
