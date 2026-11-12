(() => {
    const form = document.querySelector('#donation-form');
    const list = document.querySelector('#donation-list');
    const message = document.querySelector('#donation-message');
    const counter = document.querySelector('#donation-count');
    const labels = window.BLOODLINK_DONATIONS;
    const ui = window.BloodLinkUI;
    if (!form || !list) return;
    const bn = labels.lang === 'bn';
    const num = value => (bn ? String(value).replace(/\d/g, d => '০১২৩৪৫৬৭৮৯'[d]) : String(value));
    const fmt = iso => { const [y, m, d] = iso.split('-').map(Number); return bn ? `${num(d)} ${labels.months[m - 1]} ${num(y)}` : `${d} ${labels.months[m - 1]} ${y}`; };
    const showMessage = (text, type = 'success') => {
        message.textContent = text;
        message.className = `alert alert-${type}`;
        message.hidden = false;
    };
    const render = donations => {
        list.textContent = '';
        counter.textContent = num(donations.length);
        if (!donations.length) {
            list.innerHTML = `<div class="empty-state"><div class="card-icon">${ui.icon('droplet', 26)}</div><p></p></div>`;
            list.querySelector('p').textContent = labels.noDonations;
            return;
        }
        donations.forEach(donation => {
            const [, m, d] = donation.donation_date.split('-').map(Number);
            const row = document.createElement('article');
            row.className = 'donation-row';
            row.dataset.id = donation.id;
            row.innerHTML = '<div class="row-main"><div class="date-chip"><div><b></b><i style="font-style:normal"></i></div></div><div><strong></strong><p class="muted"></p></div></div>';
            row.querySelector('b').textContent = num(d);
            row.querySelector('i').textContent = labels.months[m - 1];
            row.querySelector('strong').textContent = fmt(donation.donation_date);
            row.querySelector('p').textContent = [donation.place, donation.note].filter(Boolean).join(' · ');
            const button = document.createElement('button');
            button.className = 'button button-small button-secondary donation-delete';
            button.type = 'button';
            button.innerHTML = ui.icon('trash', 16) + '<span></span>';
            button.querySelector('span').textContent = labels.delete;
            row.appendChild(button);
            list.appendChild(row);
        });
    };
    const request = async body => {
        const response = await fetch(`${window.BLOODLINK_BASE_URL}/api/donation.php`, {
            method: 'POST',
            headers: { 'X-CSRF-Token': window.BLOODLINK_CSRF, Accept: 'application/json' },
            body: new URLSearchParams(body),
        });
        const result = await response.json();
        if (!response.ok || !result.ok) {
            const fields = result.fields || {};
            throw new Error(Object.values(fields)[0] || labels.failed);
        }
        return result;
    };
    form.addEventListener('submit', async event => {
        event.preventDefault();
        const submit = form.querySelector('[type="submit"]');
        ui.busy(submit, true);
        try {
            const result = await request(Object.fromEntries(new FormData(form)));
            render(result.donations);
            form.reset();
            showMessage(labels.added);
            ui.toast(labels.added, 'success');
        } catch (error) {
            showMessage(error.message, 'error');
            ui.toast(error.message, 'error');
        } finally {
            ui.busy(submit, false);
        }
    });
    list.addEventListener('click', async event => {
        const button = event.target.closest('.donation-delete');
        if (!button || !(await ui.confirm(labels.deleteConfirm, { danger: true }))) return;
        const row = button.closest('.donation-row');
        try {
            const result = await request({ action: 'delete', id: row.dataset.id });
            render(result.donations);
            showMessage(labels.deleted);
            ui.toast(labels.deleted, 'success');
        } catch (error) {
            showMessage(error.message, 'error');
            ui.toast(error.message, 'error');
        }
    });
})();
