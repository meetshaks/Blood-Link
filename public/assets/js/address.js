(() => {
    'use strict';

    const ui = window.BLOODLINK_UI || {};
    const labels = ui.address || {};
    const lang = ui.lang === 'bn';
    const digit = value => lang ? String(value).replace(/\d/g, d => '০১২৩৪৫৬৭৮৯'[d]) : String(value);

    const initDisclosure = disclosure => {
        const sync = () => {
            disclosure.querySelector('.disclosure-content')?.querySelectorAll('input, select, textarea, button').forEach(control => {
                control.disabled = !disclosure.open;
            });
        };
        disclosure.addEventListener('toggle', sync);
        sync();
    };

    const init = (form, prefix) => {
        const get = name => form.querySelector(`#${prefix}${name}`);
        const division = get('division_id');
        const district = get('district_id');
        const upazila = get('upazila_id');
        const union = get('union_id');
        const city = get('city_corporation_id');
        const area = get('city_corporation_area_id');
        if (!division || !district || !upazila || !union || !city) return;

        const field = element => element?.closest('.field');
        const cityField = field(city);
        const areaField = field(area);
        const upazilaField = field(upazila);
        const unionField = field(union);
        const apiUrl = form.dataset.geoUrl || `${window.BLOODLINK_BASE_URL || ''}/api/geo.php`;
        const state = {
            division: division.value,
            district: district.value,
            hasAreas: false,
            mode: null,
            city: city.value,
            area: area?.value || '',
            upazila: upazila.value,
            union: union.value
        };
        let modeGroup;
        let modeHint;
        let areaRequest = 0;

        const clear = select => { if (select) select.value = ''; };
        const setVisible = (element, visible) => { if (element) element.hidden = !visible; };
        const setDisabled = (element, disabled) => { if (element) element.disabled = disabled; };
        const filter = (source, target) => {
            if (!source || !target) return;
            [...target.options].forEach((option, index) => {
                option.hidden = index > 0 && source.value !== '' && option.dataset.parent !== source.value;
            });
            if (target.selectedOptions[0]?.hidden) clear(target);
        };
        const resetArea = () => {
            if (!area) return;
            area.innerHTML = '<option value="">—</option>';
            area.disabled = true;
            state.area = '';
            setVisible(areaField, false);
        };
        const clearOutside = () => {
            clear(upazila);
            clear(union);
            state.upazila = '';
            state.union = '';
        };
        const clearInside = () => {
            clear(city);
            resetArea();
            state.city = '';
        };
        const render = () => {
            const inside = state.hasAreas && state.mode === 'inside';
            const outside = !state.hasAreas || state.mode === 'outside';
            setVisible(modeGroup, state.hasAreas);
            if (modeHint) modeHint.hidden = state.mode !== null;
            if (modeGroup) {
                modeGroup.querySelectorAll('input').forEach(input => {
                    input.checked = state.mode === input.value;
                });
            }
            setVisible(cityField, inside);
            setVisible(areaField, inside && !!area && area.options.length > 1);
            setVisible(upazilaField, outside);
            setVisible(unionField, outside && !!upazila.value);
            setDisabled(city, !inside);
            setDisabled(area, !inside || !area || area.options.length <= 1);
            setDisabled(upazila, !outside);
            setDisabled(union, !outside || !upazila.value);
        };
        const loadAreas = async () => {
            const request = ++areaRequest;
            resetArea();
            if (!city.value || !area) {
                render();
                return;
            }
            const response = await fetch(`${apiUrl}?type=city_corporation_areas&parent_id=${encodeURIComponent(city.value)}`, { headers: { Accept: 'application/json' } });
            const payload = await response.json();
            if (request !== areaRequest) return;
            if (!response.ok || !payload.ok) throw new Error('Area request failed');
            payload.items.forEach(item => {
                const option = document.createElement('option');
                option.value = item.id;
                option.textContent = `${lang ? item.name_bn : item.name_en}${item.ward_no ? ` (${labels.ward || 'Ward'} ${digit(item.ward_no)})` : ''}`;
                area.appendChild(option);
            });
            const selected = area.dataset.selected || '';
            if (selected && [...area.options].some(option => option.value === selected)) area.value = selected;
            state.area = area.value;
            area.disabled = payload.items.length === 0;
            render();
        };
        const createModeGroup = () => {
            modeGroup = document.createElement('fieldset');
            modeGroup.className = 'field address-mode-field';
            modeGroup.hidden = true;
            modeGroup.innerHTML = `<legend>${labels.label || ''}</legend><div class="radio-group address-mode-group"><label class="radio-pill"><input type="radio" name="${prefix}address_mode" value="inside"><span>${labels.inside || ''}</span></label><label class="radio-pill"><input type="radio" name="${prefix}address_mode" value="outside"><span>${labels.outside || ''}</span></label></div><p class="field-hint address-mode-hint">${labels.hint || ''}</p>`;
            modeHint = modeGroup.querySelector('.address-mode-hint');
            district.closest('.field')?.after(modeGroup);
            modeGroup.addEventListener('change', event => {
                if (event.target.name !== `${prefix}address_mode`) return;
                state.mode = event.target.value;
                if (state.mode === 'inside') {
                    clearOutside();
                    filter(district, city);
                    const visibleCities = [...city.options].filter(option => !option.hidden && option.value !== '');
                    if (visibleCities.length === 1 && !city.value) city.value = visibleCities[0].value;
                    state.city = city.value;
                    loadAreas().catch(() => render());
                } else {
                    clearInside();
                    filter(district, upazila);
                    state.upazila = upazila.value;
                    render();
                }
            });
        };
        const districtChanged = reset => {
            filter(division, district);
            filter(district, city);
            filter(district, upazila);
            filter(upazila, union);
            state.hasAreas = district.selectedOptions[0]?.dataset.hasAreas === '1';
            if (reset) {
                state.mode = null;
                clearInside();
                clearOutside();
            }
            render();
        };

        createModeGroup();
        division.addEventListener('change', () => {
            clear(district);
            state.division = division.value;
            districtChanged(true);
        });
        district.addEventListener('change', () => {
            state.district = district.value;
            districtChanged(true);
        });
        upazila.addEventListener('change', () => {
            filter(upazila, union);
            state.upazila = upazila.value;
            state.union = union.value;
            render();
        });
        union.addEventListener('change', () => {
            state.union = union.value;
            render();
        });
        city.addEventListener('change', () => {
            state.city = city.value;
            state.area = '';
            resetArea();
            render();
            loadAreas().catch(() => render());
        });
        area?.addEventListener('change', () => {
            state.area = area.value;
            render();
        });

        districtChanged(false);
        if (state.hasAreas && city.value) {
            state.mode = 'inside';
            state.city = city.value;
            loadAreas().catch(() => render());
        } else if (state.hasAreas && upazila.value) {
            state.mode = 'outside';
            state.upazila = upazila.value;
            render();
        } else if (!state.hasAreas) {
            state.mode = 'outside';
            render();
        } else {
            render();
        }
    };

    document.querySelectorAll('[data-address-prefix]').forEach(form => {
        init(form, '');
        init(form, 'work_');
    });
    document.querySelectorAll('details.disclosure').forEach(initDisclosure);
})();
