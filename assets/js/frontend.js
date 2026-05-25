(function () {
    const config = window.SPB_BOOK_BUILDER;
    if (!config) {
        return;
    }

    const root = document.querySelector('.spb-builder');
    if (!root) {
        return;
    }

    const state = {
        gradeId: '',
        playIds: new Set(),
        workIds: new Set(),
        hardcover: false,
        sourceRequestId: 0,
    };

    const money = new Intl.NumberFormat('he-IL', {
        style: 'currency',
        currency: 'ILS',
        maximumFractionDigits: 2,
    });

    const els = {
        grade: root.querySelector('[data-spb-grade]'),
        plays: root.querySelector('[data-spb-plays]'),
        works: root.querySelector('[data-spb-works]'),
        school: root.querySelector('[data-spb-school]'),
        hardcover: root.querySelector('[data-spb-hardcover]'),
        selected: root.querySelector('[data-spb-selected]'),
        save: root.querySelector('[data-spb-save]'),
        message: root.querySelector('[data-spb-message]'),
        template: root.querySelector('[data-spb-template]'),
    };

    function itemsForGrade(items) {
        if (!state.gradeId) {
            return [];
        }
        return items.filter((item) => String(item.gradeId) === String(state.gradeId));
    }

    function selectedItems() {
        const plays = config.plays.filter((item) => state.playIds.has(String(item.id)));
        const works = config.works.filter((item) => state.workIds.has(String(item.id)));
        return { plays, works };
    }

    function calculate() {
        const selected = selectedItems();
        const pages = selected.plays.concat(selected.works).reduce((sum, item) => sum + Number(item.pages || 0), 0);
        const playsPrice = selected.plays.reduce((sum, item) => sum + Number(item.price || 0), 0);
        let price = Number(config.pricing.base_price || 0) + pages * Number(config.pricing.page_price || 0) + playsPrice;
        if (state.hardcover) {
            price += Number(config.pricing.hardcover_price || 0);
        }
        if (config.userFixedPrice !== null && config.userFixedPrice !== undefined) {
            price = Number(config.userFixedPrice || 0);
        }
        return { pages, price, selected };
    }

    function renderPlays() {
        const plays = itemsForGrade(config.plays);
        if (!state.gradeId) {
            els.plays.innerHTML = '<p class="spb-empty">בחרו כיתה כדי לראות מחזות מתאימים.</p>';
            return;
        }
        if (!plays.length) {
            els.plays.innerHTML = '<p class="spb-empty">אין מחזות פעילים לכיתה הזו כרגע.</p>';
            return;
        }

        els.plays.innerHTML = plays.map((play) => {
            const checked = state.playIds.has(String(play.id)) ? 'checked' : '';
            const image = play.image ? `<img src="${escapeAttr(play.image)}" alt="">` : '<div class="spb-card__placeholder"></div>';
            return `
                <label class="spb-play-card">
                    <input type="checkbox" value="${play.id}" data-spb-play ${checked}>
                    ${image}
                    <span class="spb-play-card__body">
                        <strong>${escapeHtml(play.title)}</strong>
                        <span>${escapeHtml(play.author || '')}</span>
                        <span>${Number(play.pages || 0)} עמודים · ${money.format(Number(play.price || 0))}</span>
                    </span>
                </label>
            `;
        }).join('');
    }

    function renderWorks() {
        const works = itemsForGrade(config.works);
        if (!state.gradeId) {
            els.works.innerHTML = '<p class="spb-empty">בחרו כיתה כדי לראות יצירות מתאימות.</p>';
            return;
        }
        if (!works.length) {
            els.works.innerHTML = '<p class="spb-empty">אין יצירות פעילות לכיתה הזו כרגע.</p>';
            return;
        }

        const groups = works.reduce((acc, work) => {
            const key = work.category || 'ללא קטגוריה';
            acc[key] = acc[key] || [];
            acc[key].push(work);
            return acc;
        }, {});

        els.works.innerHTML = Object.keys(groups).map((category) => `
            <div class="spb-work-group">
                <h4>${escapeHtml(category)}</h4>
                <div class="spb-work-table">
                    ${groups[category].map((work) => {
                        const checked = state.workIds.has(String(work.id)) ? 'checked' : '';
                        const required = work.required ? '<span class="spb-badge">חובה</span>' : '';
                        return `
                            <label class="spb-work-row">
                                <input type="checkbox" value="${work.id}" data-spb-work ${checked}>
                                <span>${escapeHtml(work.title)} ${required}</span>
                                <span>${escapeHtml(work.author || '')}</span>
                                <span>${Number(work.pages || 0)} עמודים</span>
                            </label>
                        `;
                    }).join('')}
                </div>
            </div>
        `).join('');
    }

    function renderSummary() {
        const result = calculate();
        root.querySelectorAll('[data-spb-pages]').forEach((el) => { el.textContent = result.pages; });
        root.querySelectorAll('[data-spb-price]').forEach((el) => { el.textContent = money.format(result.price); });

        const rows = result.selected.plays.concat(result.selected.works).map((item) => `
            <li><span>${escapeHtml(item.title)}</span><small>${Number(item.pages || 0)} עמודים</small></li>
        `);
        els.selected.innerHTML = rows.length ? `<ul>${rows.join('')}</ul>` : '<p class="spb-empty">עדיין לא נבחרו פריטים.</p>';
    }

    function renderAll() {
        renderPlays();
        renderWorks();
        renderSummary();
    }

    function syncCurrentCheckboxes() {
        root.querySelectorAll('[data-spb-play]').forEach((input) => {
            input.checked = state.playIds.has(String(input.value));
        });
        root.querySelectorAll('[data-spb-work]').forEach((input) => {
            input.checked = state.workIds.has(String(input.value));
        });
    }

    els.grade.addEventListener('change', () => {
        state.gradeId = els.grade.value;
        state.playIds.clear();
        state.workIds.clear();
        state.sourceRequestId = 0;
        renderAll();
    });

    if (els.template) {
        els.template.addEventListener('change', () => {
            if (els.template.value) {
                window.location.href = els.template.value;
            }
        });
    }

    els.hardcover.addEventListener('change', () => {
        state.hardcover = els.hardcover.checked;
        renderSummary();
    });

    root.addEventListener('change', (event) => {
        const input = event.target;
        if (input.matches('[data-spb-play]')) {
            input.checked ? state.playIds.add(String(input.value)) : state.playIds.delete(String(input.value));
            renderSummary();
            syncCurrentCheckboxes();
        }
        if (input.matches('[data-spb-work]')) {
            input.checked ? state.workIds.add(String(input.value)) : state.workIds.delete(String(input.value));
            renderSummary();
            syncCurrentCheckboxes();
        }
    });

    els.save.addEventListener('click', async () => {
        els.message.textContent = '';
        const payload = new URLSearchParams();
        payload.set('action', 'spb_save_book_request');
        payload.set('nonce', config.nonce);
        payload.set('gradeId', state.gradeId);
        payload.set('schoolName', els.school.value || '');
        payload.set('hardcover', state.hardcover ? '1' : '');
        payload.set('sourceRequestId', String(state.sourceRequestId || 0));
        Array.from(state.playIds).forEach((id) => payload.append('playIds[]', id));
        Array.from(state.workIds).forEach((id) => payload.append('workIds[]', id));

        els.save.disabled = true;
        try {
            const response = await fetch(config.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: payload.toString(),
            });
            const json = await response.json();
            els.message.textContent = json.success ? config.labels.saved : (json.data && json.data.message ? json.data.message : config.labels.error);
            els.message.classList.toggle('is-error', !json.success);
        } catch (error) {
            els.message.textContent = config.labels.error;
            els.message.classList.add('is-error');
        } finally {
            els.save.disabled = false;
        }
    });

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, (char) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;',
        }[char]));
    }

    function escapeAttr(value) {
        return escapeHtml(value).replace(/`/g, '&#096;');
    }

    function loadInitialRequest() {
        const request = config.initialRequest;
        if (!request) {
            return;
        }

        state.gradeId = String(request.gradeId || '');
        state.playIds = new Set((request.playIds || []).map(String));
        state.workIds = new Set((request.workIds || []).map(String));
        state.hardcover = Boolean(request.hardcover);
        state.sourceRequestId = Number(request.id || 0);
        els.grade.value = state.gradeId;
        els.hardcover.checked = state.hardcover;
        if (els.school && request.schoolName) {
            els.school.value = request.schoolName;
        }
    }

    loadInitialRequest();
    renderAll();
})();
