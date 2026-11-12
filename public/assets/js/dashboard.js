(() => {
    const shortageToggle = document.querySelector('#show-gaps');
    shortageToggle?.addEventListener('change', event => {
        document.querySelectorAll('.shortage-table tbody tr').forEach(row => {
            row.hidden = event.target.checked && row.dataset.hasGap !== '1';
        });
    });
    const districtCells = [...document.querySelectorAll('.shortage-table .district-name')];
    const phoneHeatmap = window.matchMedia('(max-width: 640px)');
    const shortageTable = document.querySelector('.shortage-table');
    let tooltip = null;
    let fitTimer = 0;
    const closeTooltip = () => {
        tooltip?.remove();
        tooltip = null;
    };
    const fitDistrictNames = () => {
        if (!phoneHeatmap.matches) {
            districtCells.forEach(cell => {
                cell.style.removeProperty('font-size');
                cell.classList.remove('has-boundary', 'is-truncated');
                cell.removeAttribute('title');
            });
            shortageTable?.style.removeProperty('--district-w');
            return;
        }
        if (shortageTable && districtCells.length) {
            const sample = districtCells[0];
            const styles = getComputedStyle(sample);
            const canvas = document.createElement('canvas');
            const context = canvas.getContext('2d');
            if (context) {
                context.font = `${styles.fontWeight} ${styles.fontSize} ${styles.fontFamily}`;
                const measuredMaxNameWidth = Math.max(...districtCells.map(cell => context.measureText(cell.dataset.districtName || cell.textContent.trim()).width));
                const districtWidth = Math.min(measuredMaxNameWidth + 16, 110);
                shortageTable.style.setProperty('--district-w', `${districtWidth.toFixed(2)}px`);
                shortageTable.dataset.maxNameWidth = measuredMaxNameWidth.toFixed(2);
                shortageTable.dataset.districtWidth = districtWidth.toFixed(2);
            }
        }
        districtCells.forEach(cell => {
            const fullName = cell.dataset.districtName || cell.textContent.trim();
            cell.removeAttribute('title');
            cell.classList.toggle('has-boundary', /[\s-]/u.test(fullName));
            cell.style.fontSize = '12.5px';
            cell.classList.remove('is-truncated');
            while (cell.scrollWidth > cell.clientWidth && parseFloat(cell.style.fontSize) > 10.5) {
                cell.style.fontSize = `${(parseFloat(cell.style.fontSize) - 0.5).toFixed(1)}px`;
            }
            if (cell.scrollWidth > cell.clientWidth) {
                cell.classList.add('is-truncated');
                cell.title = fullName;
            }
        });
    };
    const scheduleDistrictFit = () => {
        window.clearTimeout(fitTimer);
        fitTimer = window.setTimeout(fitDistrictNames, 80);
    };
    const showDistrictTooltip = cell => {
        closeTooltip();
        tooltip = document.createElement('div');
        tooltip.className = 'shortage-tooltip';
        tooltip.setAttribute('role', 'status');
        tooltip.textContent = cell.dataset.districtName || cell.textContent.trim();
        document.body.appendChild(tooltip);
        const rowBox = cell.closest('tr').getBoundingClientRect();
        const left = Math.max(8, Math.min(rowBox.left, window.innerWidth - tooltip.offsetWidth - 8));
        tooltip.style.left = `${left}px`;
        tooltip.style.top = `${Math.min(window.innerHeight - tooltip.offsetHeight - 8, rowBox.bottom + 4)}px`;
    };
    districtCells.forEach(cell => cell.addEventListener('click', () => {
        if (cell.classList.contains('is-truncated')) showDistrictTooltip(cell);
    }));
    window.addEventListener('resize', scheduleDistrictFit, { passive: true });
    window.addEventListener('orientationchange', scheduleDistrictFit, { passive: true });
    if (phoneHeatmap.addEventListener) phoneHeatmap.addEventListener('change', scheduleDistrictFit);
    else phoneHeatmap.addListener?.(scheduleDistrictFit);
    window.addEventListener('scroll', closeTooltip, { passive: true });
    document.addEventListener('keydown', event => { if (event.key === 'Escape') closeTooltip(); });
    scheduleDistrictFit();
    document.fonts?.ready?.then(scheduleDistrictFit);
    document.querySelectorAll('[data-present-at]').forEach(button => button.addEventListener('click', async () => {
        const labels = window.BLOODLINK_DASHBOARD;
        const value = button.dataset.presentAt;
        try {
            const response = await fetch(`${window.BLOODLINK_BASE_URL}/api/user.php`, {
                method: 'POST', headers: { 'X-CSRF-Token': window.BLOODLINK_CSRF, Accept: 'application/json' },
                body: new URLSearchParams({ action: 'set_present_at', present_at: value }),
            });
            const result = await response.json();
            if (!response.ok || !result.ok) throw new Error('present');
            document.querySelectorAll('[data-present-at]').forEach(item => {
                item.classList.toggle('button-secondary', item.dataset.presentAt !== result.present_at);
            });
            BloodLinkUI.toast(labels.presentSaved, 'success');
        } catch { BloodLinkUI.toast(labels.presentFailed, 'error'); }
    }));
    const button = document.querySelector('#availability-toggle');
    const status = document.querySelector('#availability-status');
    const state = document.querySelector('#availability-state');
    const card = document.querySelector('#status-card');
    const labels = window.BLOODLINK_DASHBOARD;
    if (!button) return;
    const apply = available => {
        button.dataset.available = available ? '1' : '0';
        button.setAttribute('aria-checked', available ? 'true' : 'false');
        status.textContent = available ? labels.listed : labels.hidden;
        if (state) state.textContent = available ? labels.on : labels.off;
        if (card) card.classList.toggle('is-off', !available);
    };
    button.addEventListener('click', async event => {
        event.preventDefault();
        const available = button.dataset.available !== '1';
        BloodLinkUI.busy(button, true);
        const body = new URLSearchParams({ action: 'toggle_availability', available: available ? '1' : '0' });
        try {
            const response = await fetch(`${window.BLOODLINK_BASE_URL}/api/user.php`, {
                method: 'POST',
                headers: { 'X-CSRF-Token': window.BLOODLINK_CSRF, Accept: 'application/json' },
                body,
            });
            const result = await response.json();
            if (!response.ok || !result.ok) throw new Error('Availability update failed');
            apply(Boolean(result.available));
            BloodLinkUI.toast(labels.saved, 'success');
        } catch {
            status.textContent = labels.failed;
            BloodLinkUI.toast(labels.failed, 'error');
        } finally {
            BloodLinkUI.busy(button, false);
        }
    });
})();
