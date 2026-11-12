(() => {
    'use strict';
    const form = document.querySelector('#search-form');
    if (!form) return;
    const cfg = window.BLOODLINK;
    const labels = cfg.labels;
    const ui = window.BloodLinkUI;

    const division = document.querySelector('#division');
    const district = document.querySelector('#district');
    const city = document.querySelector('#city-corporation');
    const upazila = document.querySelector('#upazila');
    const union = document.querySelector('#union');
    const cityWrap = document.querySelector('#city-wrap');
    const cityAreaWrap = document.querySelector('#city-area-wrap');
    const cityArea = document.querySelector('#city-corporation-area');
    const upazilaWrap = document.querySelector('#upazila-wrap');
    const areaMode = document.querySelector('#area-mode');
    const list = document.querySelector('#results-list');
    const welcomePanel = document.querySelector('#welcome-panel');
    const welcomeMarkup = welcomePanel?.outerHTML || '';
    const pagination = document.querySelector('#pagination');
    const heading = document.querySelector('#results-heading');
    const availabilityFilters = [...document.querySelectorAll('[data-availability]')];
    const bgError = document.querySelector('#bg-error');
    const locationButton = document.querySelector('#use-location');
    const shareButton = document.querySelector('#share-search');
    const defaultHeading = heading.textContent;
    let controller;
    let searchTimer;
    let restoring = false;
    const hideWelcome = () => { if (welcomePanel) welcomePanel.hidden = true; };
    const showWelcome = () => { if (welcomePanel) welcomePanel.hidden = false; };
    const bindWelcomeGroups = () => document.querySelectorAll('[data-select-blood]').forEach(button => button.addEventListener('click', () => {
        const radio = form.querySelector(`[name="bg"][value="${CSS.escape(button.dataset.selectBlood || '')}"]`);
        if (!radio) return;
        radio.checked = true;
        radio.dispatchEvent(new Event('change', { bubbles: true }));
        document.querySelector('#search-submit')?.focus();
    }));

    /* ---------- helpers ---------- */
    const bn = cfg.lang === 'bn';
    const num = value => (bn ? String(value).replace(/\d/g, d => '০১২৩৪৫৬৭৮৯'[d]) : String(value));
    const fill = (template, values) => Object.entries(values).reduce((out, [k, v]) => out.replaceAll(`{${k}}`, v), template);
    const label = item => (bn ? item.name_bn : item.name_en);
    const esc = text => { const d = document.createElement('div'); d.textContent = text ?? ''; return d.innerHTML; };
    const fmtDate = iso => {
        const [y, m, d] = String(iso).split('-').map(Number);
        return bn ? `${num(d)} ${cfg.months[m - 1]} ${num(y)}` : `${d} ${cfg.months[m - 1]} ${y}`;
    };
    const initials = name => { const parts = String(name).trim().split(/\s+/); return (parts[0]?.[0] || '') + (parts.length > 1 ? parts[parts.length - 1][0] : ''); };
    const spin = (select, on) => { select.closest('.field')?.querySelector('.load-spin')?.toggleAttribute('hidden', !on); };

    const reset = select => { select.innerHTML = '<option value="">—</option>'; select.disabled = true; };
    const load = async (type, parentId, select) => {
        reset(select);
        if (!parentId) return 0;
        spin(select, true);
        try {
            const response = await fetch(`${cfg.baseUrl}/api/geo.php?type=${encodeURIComponent(type)}&parent_id=${encodeURIComponent(parentId)}&v=2`, { headers: { Accept: 'application/json' } });
            const data = await response.json();
            if (!response.ok || !data.ok) throw new Error('Location request failed');
            data.items.forEach(item => {
                const option = document.createElement('option');
                option.value = item.id;
                if (item.has_areas !== undefined) option.dataset.hasAreas = String(item.has_areas);
                option.textContent = label(item) + (item.type === 'pourashava' ? ` (${labels.pourashava})` : '') + (item.ward_no ? ` (${labels.ward} ${num(item.ward_no)})` : '');
                select.appendChild(option);
            });
            select.disabled = data.items.length === 0;
            return data.items.length;
        } finally {
            spin(select, false);
        }
    };

    const validGroups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
    const stateParams = ['bg', 'division', 'district', 'upazila', 'union', 'city_corporation', 'city_corporation_area', 'availability_status', 'page'];
    const updateUrl = page => {
        if (restoring) return;
        const url = new URL(window.location.href);
        stateParams.forEach(key => url.searchParams.delete(key));
        const data = new FormData(form);
        if (validGroups.includes(String(data.get('bg') || ''))) url.searchParams.set('bg', String(data.get('bg')));
        ['division', 'district', 'upazila', 'union', 'city_corporation', 'city_corporation_area'].forEach(key => {
            const value = String(data.get(key) || '');
            if (/^\d+$/.test(value) && Number(value) > 0) url.searchParams.set(key, value);
        });
        const status = availabilityFilters.find(button => button.classList.contains('is-active'))?.dataset.availability;
        if (status && status !== 'all') url.searchParams.set('availability_status', status);
        if (page > 1) url.searchParams.set('page', String(page));
        window.history.replaceState({}, '', url);
    };

    /* ---------- area mode: upazila/union  OR  city corporation ---------- */
    const setMode = mode => {
        const isCity = mode === 'city';
        const modeHint = areaMode.querySelector('.address-mode-hint');
        areaMode.querySelectorAll('input').forEach(input => { input.checked = input.value === mode; });
        if (modeHint) modeHint.hidden = mode !== null;
        cityWrap.hidden = !isCity;
        upazilaWrap.hidden = mode !== 'upazila';
        city.disabled = !isCity;
        cityArea.disabled = !isCity;
        upazila.disabled = mode !== 'upazila';
        union.disabled = mode !== 'upazila' || !upazila.value;
        if (isCity) { upazila.value = ''; reset(union); } else { city.value = ''; reset(cityArea); cityAreaWrap.hidden = true; }
        if (mode === null) {
            cityWrap.hidden = true;
            upazilaWrap.hidden = true;
            city.disabled = true; cityArea.disabled = true; upazila.disabled = true; union.disabled = true;
        }
        if (isCity) {
            const visibleCities = [...city.options].filter(option => !option.hidden && option.value !== '');
            if (visibleCities.length === 1 && city.value === '') {
                city.value = visibleCities[0].value;
                city.dispatchEvent(new Event('change'));
            }
        }
    };
    areaMode.addEventListener('change', event => { if (event.target.name === 'address_mode') setMode(event.target.value); });

    /* ---------- cascade ---------- */
    const divisionChanged = async (value = division.value) => {
        reset(district); reset(city); reset(cityArea); reset(upazila); reset(union);
        cityAreaWrap.hidden = true;
        areaMode.hidden = true; setMode(null);
        availabilityFilters.forEach(button => button.classList.toggle('is-active', button.dataset.availability === 'all'));
        division.value = value;
        try { await load('districts', division.value, district); } catch { ui.toast(labels.error, 'error'); }
    };
    const districtChanged = async (value = district.value) => {
        reset(city); reset(cityArea); reset(upazila); reset(union);
        cityAreaWrap.hidden = true;
        areaMode.hidden = true; setMode(null);
        district.value = value;
        try {
            const [cities] = await Promise.all([load('city_corporations', district.value, city), load('upazilas', district.value, upazila)]);
            const hasAreas = district.selectedOptions[0]?.dataset.hasAreas === '1';
            areaMode.hidden = !hasAreas;
            setMode(hasAreas ? null : 'upazila');
        } catch { ui.toast(labels.error, 'error'); }
    };
    division.addEventListener('change', () => divisionChanged());
    district.addEventListener('change', () => districtChanged());
    upazila.addEventListener('change', () => { reset(union); load('unions', upazila.value, union).catch(() => ui.toast(labels.error, 'error')); });
    city.addEventListener('change', async () => {
        reset(cityArea);
        cityAreaWrap.hidden = !city.value;
        if (city.value) {
            upazila.value = ''; reset(union);
            try { await load('city_corporation_areas', city.value, cityArea); } catch { ui.toast(labels.error, 'error'); }
        }
    });
    union.addEventListener('change', () => { if (union.value) city.value = ''; });

    /* ---------- rendering ---------- */
    const skeleton = () => {
        hideWelcome();
        const card = `<div class="card skeleton-card" aria-hidden="true"><div class="sk-row"><div class="sk" style="width:52px;height:52px;border-radius:50%"></div><div style="flex:1;display:grid;gap:8px"><div class="sk" style="height:14px;width:60%"></div><div class="sk" style="height:12px;width:35%"></div></div><div class="sk" style="width:46px;height:46px;border-radius:10px"></div></div><div class="sk" style="height:12px;width:80%"></div><div class="sk" style="height:12px;width:55%"></div><div class="sk" style="height:24px;width:40%;border-radius:999px"></div><div class="sk" style="height:44px;border-radius:8px"></div></div>`;
        list.className = 'donor-grid';
        list.innerHTML = card.repeat(3);
    };

    const emptyState = () => {
        hideWelcome();
        list.className = '';
        list.innerHTML = `<div class="empty-state"><div class="card-icon">${ui.icon('user-x', 26)}</div><h3>${esc(labels.noResults)}</h3><p>${esc(labels.noResultsHint)}</p><div class="button-row"></div></div>`;
        const row = list.querySelector('.button-row');
        const clear = document.createElement('button');
        clear.type = 'button'; clear.className = 'button'; clear.textContent = labels.reset;
        clear.addEventListener('click', resetAll);
        row.appendChild(clear);
    };

    const donorCard = donor => {
        const card = document.createElement('article');
        card.className = 'card donor-card';
        const home = donor.home_location || {};
        const work = donor.work_location;
        const searchedArea = ['division', 'district', 'upazila', 'union', 'city_corporation', 'city_corporation_area']
            .some(key => String(new FormData(form).get(key) || '') !== '');
        const locationText = location => {
            if (!location) return '';
            const union = location.union_bn || location.union_en ? (bn ? location.union_bn : location.union_en) : '';
            const city = bn ? location.city_corporation_bn : location.city_corporation_en;
            const upazila = bn ? location.upazila_bn : location.upazila_en;
            const district = bn ? location.district_bn : location.district_en;
            const area = location.city_corporation_area_bn || location.city_corporation_area_en || '';
            const note = location.area_text || '';
            return [union || area || note, upazila || city, district].filter(Boolean).join(', ');
        };
        const matchedOn = donor.matched_on === 'work' && work ? 'work' : 'home';
        const available = Number(donor.is_available) === 1;
        const gender = labels[donor.gender] || '';
        const avatar = donor.photo
            ? `<img src="${esc(cfg.baseUrl + donor.photo)}" alt="" loading="lazy">`
            : esc(initials(donor.name));
        card.innerHTML = `
            <div class="donor-top">
                <div class="avatar" aria-hidden="true">${avatar}</div>
                <div class="donor-id"><h3></h3><p></p></div>
                <span class="blood-badge" aria-label=""></span>
            </div>
            <div class="donor-locations"></div>
            <p class="donor-line">${ui.icon('calendar', 16)}<span class="last"></span></p>
            <div class="donor-donation-slot"></div>
            <div><span class="badge ${available ? 'badge-available' : 'badge-upcoming'}">${ui.icon(available ? 'check-circle' : 'clock', 15)}<span class="st"></span></span></div>
            <div class="donor-contact"></div>`;
        card.querySelector('h3').textContent = donor.name;
        card.querySelector('h3').title = donor.name;
        card.querySelector('.donor-id p').textContent = [donor.age != null ? `${num(donor.age)} ${labels.years}` : '', gender].filter(Boolean).join(' · ');
        const badge = card.querySelector('.blood-badge');
        badge.textContent = donor.blood_group;
        badge.setAttribute('aria-label', donor.blood_group);
        const locations = card.querySelector('.donor-locations');
        const locationRow = (location, key, matched) => {
            const row = document.createElement('p');
            row.className = 'donor-line donor-location-row';
            const icon = document.createElement('span');
            icon.setAttribute('aria-hidden', 'true');
            icon.innerHTML = ui.icon('map-pin', 16);
            row.append(icon);
            const content = document.createElement('span');
            content.className = 'donor-location-content';
            const label = document.createElement('strong');
            label.textContent = key === 'work' ? labels.locWork : labels.locHome;
            content.append(label);
            if (matched) {
                const badge = document.createElement('span');
                badge.className = 'badge badge-pending donor-match-badge';
                badge.textContent = labels.matchedArea;
                content.append(badge);
            }
            const details = document.createElement('span');
            details.className = 'donor-location-details';
            details.textContent = locationText(location) || labels.locationNotAvailable;
            content.append(details);
            row.append(content);
            return row;
        };
        if (matchedOn === 'work' && work) locations.append(locationRow(work, 'work', searchedArea), locationRow(home, 'home', false));
        else {
            locations.append(locationRow(home, 'home', searchedArea));
            if (work) locations.append(locationRow(work, 'work', false));
        }
        if (donor.work_updated_at) {
            const months = Math.floor((Date.now() - new Date(String(donor.work_updated_at).replace(' ', 'T')).getTime()) / (30.44 * 86400000));
            if (months > 6) {
                const note = document.createElement('p');
                note.className = 'field-hint';
                note.textContent = fill(labels.locationUpdated, { n: num(months) });
                locations.append(note);
            }
        }
        card.querySelector('.last').textContent = `${labels.lastDonation}: ${donor.last_donation_date ? fmtDate(donor.last_donation_date) : labels.never}`;
        const donationSlot = card.querySelector('.donor-donation-slot');
        if (Number(donor.donation_count) > 0) {
            donationSlot.className = 'donor-line donor-donation-line';
            const donationIcon = document.createElement('span');
            donationIcon.setAttribute('aria-hidden', 'true');
            donationIcon.innerHTML = ui.icon('droplet', 16);
            const donationText = document.createElement('span');
            donationText.textContent = fill(labels.donationCount, { n: num(donor.donation_count) });
            donationSlot.append(donationIcon, donationText);
            if (donor.badge) {
                const badge = document.createElement('span');
                badge.className = `donor-tier-chip tier-${donor.badge}`;
                badge.textContent = labels[`badge${donor.badge.charAt(0).toUpperCase()}${donor.badge.slice(1)}`];
                donationSlot.append(badge);
            }
        }
        card.querySelector('.st').textContent = available ? labels.available : fill(labels.availableIn, { n: num(donor.days_left) });
        const contact = card.querySelector('.donor-contact');
        if (donor.phone) {
            const call = document.createElement('a');
            call.className = 'button donor-call'; call.href = `tel:${donor.phone}`;
            call.innerHTML = ui.icon('phone', 18);
            const phone = document.createElement('span');
            phone.textContent = donor.phone;
            call.appendChild(phone);
            call.setAttribute('aria-label', `${labels.callNow} ${donor.phone}`);
            call.title = `${labels.callNow} ${donor.phone}`;
            const copy = document.createElement('button');
            copy.type = 'button'; copy.className = 'button button-secondary donor-copy';
            copy.setAttribute('aria-label', labels.copy); copy.title = labels.copy;
            copy.innerHTML = ui.icon('copy', 18);
            copy.addEventListener('click', async () => { try { await navigator.clipboard.writeText(donor.phone); ui.toast(labels.copied, 'success'); } catch { ui.toast(donor.phone, 'info'); } });
            contact.append(call, copy);
        } else {
            contact.innerHTML = `<div class="number-hidden">${ui.icon('lock', 16)}<span></span></div>`;
            contact.querySelector('span').textContent = labels.hidden;
        }
        return card;
    };

    const render = data => {
        heading.textContent = fill(labels.found, { n: num(data.total) });
        if (!data.donors.length) { emptyState(); pagination.textContent = ''; return; }
        list.className = 'donor-grid';
        list.textContent = '';
        data.donors.forEach(donor => list.appendChild(donorCard(donor)));
        pagination.textContent = '';
        const pages = Math.max(1, Math.ceil(data.total / data.per_page));
        if (pages > 1) {
            const info = document.createElement('span');
            info.className = 'page-info';
            info.textContent = fill(labels.page, { n: num(data.page), m: num(pages) });
            const links = document.createElement('div');
            links.className = 'pagination-links';
            const mk = (text, icon, page, disabled) => {
                const b = document.createElement('button');
                b.type = 'button'; b.className = 'button button-secondary button-small'; b.disabled = disabled;
                b.innerHTML = icon === 'chevron-left' ? ui.icon(icon, 16) + '<span></span>' : '<span></span>' + ui.icon(icon, 16);
                b.querySelector('span').textContent = text;
                b.addEventListener('click', () => search(page));
                return b;
            };
            links.append(mk(labels.previous, 'chevron-left', data.page - 1, data.page <= 1), mk(labels.next, 'chevron-right', data.page + 1, data.page >= pages));
            pagination.append(info, links);
        }
    };

    /* ---------- search ---------- */
    const search = async page => {
        window.clearTimeout(searchTimer);
        searchTimer = null;
        if (!form.querySelector('[name="bg"]:checked')) {
            bgError.closest('.field').classList.add('has-error');
            form.querySelector('[name="bg"]').focus();
            return;
        }
        bgError.closest('.field').classList.remove('has-error');
        if (controller) controller.abort();
        controller = new AbortController();
        skeleton();
        pagination.textContent = '';
        list.setAttribute('aria-busy', 'true');
        const params = new URLSearchParams(new FormData(form));
        params.delete('address_mode');
        params.set('page', page);
        updateUrl(page);
        const status = availabilityFilters.find(button => button.classList.contains('is-active'))?.dataset.availability || 'all';
        params.set('availability_status', status);
        if (cityWrap.hidden) { params.delete('city_corporation'); params.delete('city_corporation_area'); }
        try {
            const response = await fetch(`${cfg.baseUrl}/api/search.php?${params}`, { signal: controller.signal, headers: { Accept: 'application/json' } });
            const data = await response.json();
            if (!response.ok || !data.ok) throw new Error('Search failed');
            render(data);
            list.setAttribute('aria-busy', 'false');
            if (window.matchMedia('(max-width: 899px)').matches) heading.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } catch (error) {
            if (error.name === 'AbortError') return;
            list.className = '';
            list.innerHTML = `<div class="error-state"></div>`;
            list.firstChild.textContent = labels.error;
            list.setAttribute('aria-busy', 'false');
            ui.toast(labels.error, 'error');
        }
    };

    const scheduleSearch = () => {
        if (!form.querySelector('[name="bg"]:checked')) { updateUrl(1); return; }
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(() => {
            searchTimer = null;
            search(1);
        }, 150);
    };

    const resetAll = () => {
        window.clearTimeout(searchTimer);
        searchTimer = null;
        if (controller) controller.abort();
        form.reset();
        reset(district); reset(city); reset(cityArea); reset(upazila); reset(union);
        cityAreaWrap.hidden = true;
        areaMode.hidden = true; setMode(null);
        bgError.closest('.field').classList.remove('has-error');
        heading.textContent = defaultHeading;
        pagination.textContent = '';
        list.className = '';
        list.setAttribute('aria-busy', 'false');
        list.innerHTML = welcomeMarkup;
        bindWelcomeGroups();
        updateUrl(1);
    };

    form.addEventListener('submit', event => { event.preventDefault(); search(1); });
    form.addEventListener('change', event => {
        if (event.target.matches('[name="bg"], [name="division"], [name="district"], [name="upazila"], [name="union"], [name="city_corporation"], [name="city_corporation_area"], [name="address_mode"]')) {
            scheduleSearch();
        }
    });
    document.querySelector('#reset-filters').addEventListener('click', resetAll);
    availabilityFilters.forEach(button => button.addEventListener('click', () => {
        availabilityFilters.forEach(item => item.classList.toggle('is-active', item === button));
        if (form.querySelector('[name="bg"]:checked')) search(1);
    }));
    bindWelcomeGroups();

    const restoreFromUrl = async () => {
        const params = new URLSearchParams(window.location.search);
        restoring = true;
        form.reset();
        reset(district); reset(city); reset(cityArea); reset(upazila); reset(union);
        cityAreaWrap.hidden = true;
        areaMode.hidden = true; setMode(null);
        const bg = params.get('bg');
        if (validGroups.includes(bg)) {
            const radio = form.querySelector(`[name="bg"][value="${CSS.escape(bg)}"]`);
            if (radio) radio.checked = true;
        }
        const status = ['all', 'available', 'resting'].includes(params.get('availability_status')) ? params.get('availability_status') : 'all';
        availabilityFilters.forEach(button => button.classList.toggle('is-active', button.dataset.availability === status));
        const divisionId = /^\d+$/.test(params.get('division') || '') ? params.get('division') : '';
        const districtId = /^\d+$/.test(params.get('district') || '') ? params.get('district') : '';
        const upazilaId = /^\d+$/.test(params.get('upazila') || '') ? params.get('upazila') : '';
        const unionId = /^\d+$/.test(params.get('union') || '') ? params.get('union') : '';
        const cityId = /^\d+$/.test(params.get('city_corporation') || '') ? params.get('city_corporation') : '';
        const cityAreaId = /^\d+$/.test(params.get('city_corporation_area') || '') ? params.get('city_corporation_area') : '';
        if (divisionId) {
            await divisionChanged(divisionId);
            if ([...district.options].some(o => o.value === districtId)) {
                await districtChanged(districtId);
                if ([...upazila.options].some(o => o.value === upazilaId)) {
                    upazila.value = upazilaId;
                    await load('unions', upazilaId, union);
                    if ([...union.options].some(o => o.value === unionId)) union.value = unionId;
                }
                const hasAreas = district.selectedOptions[0]?.dataset.hasAreas === '1';
                if (hasAreas && [...city.options].some(o => o.value === cityId)) {
                    city.value = cityId;
                    setMode('city');
                    await load('city_corporation_areas', cityId, cityArea);
                    cityAreaWrap.hidden = false;
                    if ([...cityArea.options].some(o => o.value === cityAreaId)) cityArea.value = cityAreaId;
                }
            }
        }
        restoring = false;
        if (validGroups.includes(bg)) search(Math.max(1, Number(params.get('page')) || 1));
    };

    locationButton?.addEventListener('click', () => {
        if (!navigator.geolocation) { ui.toast(labels.locationUnavailable, 'error'); return; }
        ui.busy(locationButton, true);
        navigator.geolocation.getCurrentPosition(async position => {
            try {
                const response = await fetch(`${cfg.baseUrl}/api/nearest.php?lat=${encodeURIComponent(position.coords.latitude)}&lng=${encodeURIComponent(position.coords.longitude)}`, { headers: { Accept: 'application/json' } });
                const data = await response.json();
                if (!response.ok || !data.ok) throw new Error(data.error || 'location');
                restoring = true;
                await divisionChanged(String(data.division.id));
                await districtChanged(String(data.district.id));
                restoring = false;
                ui.toast(fill(labels.locationSet, { district: label(data.district) }), 'success');
                scheduleSearch();
            } catch (error) {
                restoring = false;
                ui.toast(error.message === 'outside_bangladesh' ? labels.outsideBangladesh : labels.locationUnavailable, 'error');
            } finally { ui.busy(locationButton, false); }
        }, error => {
            ui.busy(locationButton, false);
            const message = error.code === 1 ? labels.locationDenied : error.code === 3 ? labels.locationTimeout : labels.locationUnavailable;
            ui.toast(message, 'error');
        }, { enableHighAccuracy: false, timeout: 10000 });
    });

    shareButton?.addEventListener('click', async () => {
        const url = window.location.href;
        try {
            if (navigator.share) await navigator.share({ title: document.title, url });
            else { await navigator.clipboard.writeText(url); ui.toast(labels.linkCopied, 'success'); }
        } catch (error) {
            if (error.name !== 'AbortError') ui.toast(labels.linkCopied, 'info');
        }
    });
    window.addEventListener('popstate', restoreFromUrl);
    restoreFromUrl().catch(() => ui.toast(labels.error, 'error'));
})();
