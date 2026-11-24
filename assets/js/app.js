(function () {
    'use strict';

    /* ---------- Modals ---------- */
    let lastFocused = null;

    function openModal(id) {
        const modal = document.getElementById(id);
        if (!modal) return;
        lastFocused = document.activeElement;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
        const focusable = modal.querySelector('input:not([type="hidden"]), select, textarea, button');
        if (focusable) {
            window.setTimeout(() => focusable.focus({ preventScroll: true }), 40);
        }
    }

    function closeModal(modal) {
        if (!modal) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        if (!document.querySelector('.modal.is-open')) {
            document.body.classList.remove('modal-open');
        }
        if (lastFocused && typeof lastFocused.focus === 'function') {
            lastFocused.focus({ preventScroll: true });
        }
    }

    document.addEventListener('click', function (event) {
        const opener = event.target.closest('[data-modal-open]');
        if (opener) {
            event.preventDefault();
            openModal(opener.getAttribute('data-modal-open'));
            return;
        }
        const closer = event.target.closest('[data-modal-close]');
        if (closer) {
            event.preventDefault();
            closeModal(closer.closest('.modal'));
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            const modal = document.querySelector('.modal.is-open');
            if (modal) closeModal(modal);
        }
    });

    /* ---------- Sidebar (mobile) ---------- */
    document.addEventListener('click', function (event) {
        if (event.target.closest('[data-sidebar-toggle]')) {
            document.body.classList.toggle('sidebar-open');
        }
        if (event.target.closest('[data-sidebar-close]')) {
            document.body.classList.remove('sidebar-open');
        }
    });

    /* ---------- Sidebar (collapse on desktop) ---------- */
    const sidebarRoot = document.documentElement;
    const sidebarCollapse = document.querySelector('[data-sidebar-collapse]');

    function syncSidebarToggle() {
        if (!sidebarCollapse) return;
        const collapsed = sidebarRoot.classList.contains('sidebar-collapsed');
        sidebarCollapse.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        sidebarCollapse.setAttribute('aria-label', collapsed ? 'Развернуть меню' : 'Свернуть меню');
        sidebarCollapse.title = collapsed ? 'Развернуть меню' : 'Свернуть меню';
    }

    document.querySelectorAll('.nav__item').forEach(function (item) {
        const label = item.querySelector('span:not(.nav__kbd)');
        if (!label) return;
        const text = label.textContent.trim();
        if (text) {
            item.setAttribute('aria-label', text);
            item.setAttribute('title', text);
        }
    });

    document.addEventListener('click', function (event) {
        if (!event.target.closest('[data-sidebar-collapse]')) return;
        event.preventDefault();
        const collapsed = sidebarRoot.classList.toggle('sidebar-collapsed');
        try {
            localStorage.setItem('piz-sidebar-collapsed', collapsed ? '1' : '0');
        } catch (e) {}
        syncSidebarToggle();
    });

    syncSidebarToggle();

    /* ---------- Группа «Ещё» и меню администратора ---------- */
    function closeAdminMenu() {
        document.querySelectorAll('[data-admin-menu].is-open').forEach(function (menu) {
            menu.classList.remove('is-open');
            menu.setAttribute('aria-hidden', 'true');
        });
        document.querySelectorAll('[data-admin-menu-toggle]').forEach(function (button) {
            button.setAttribute('aria-expanded', 'false');
        });
    }

    document.addEventListener('click', function (event) {
        const groupToggle = event.target.closest('[data-nav-group-toggle]');
        if (groupToggle) {
            event.preventDefault();
            const wasCollapsed = sidebarRoot.classList.contains('sidebar-collapsed');
            if (wasCollapsed) {
                sidebarRoot.classList.remove('sidebar-collapsed');
                try { localStorage.setItem('piz-sidebar-collapsed', '0'); } catch (e) {}
                syncSidebarToggle();
            }
            const panel = document.getElementById(groupToggle.getAttribute('aria-controls'));
            if (!panel) return;
            const open = wasCollapsed ? true : groupToggle.getAttribute('aria-expanded') !== 'true';
            groupToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            panel.hidden = !open;
            closeAdminMenu();
            return;
        }

        const adminToggle = event.target.closest('[data-admin-menu-toggle]');
        if (adminToggle) {
            event.preventDefault();
            const menu = document.getElementById(adminToggle.getAttribute('aria-controls'));
            if (!menu) return;
            const open = !menu.classList.contains('is-open');
            closeAdminMenu();
            menu.classList.toggle('is-open', open);
            menu.setAttribute('aria-hidden', open ? 'false' : 'true');
            adminToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            return;
        }

        if (!event.target.closest('[data-admin-menu]')) {
            closeAdminMenu();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeAdminMenu();
    });

    /* ---------- Toasts ---------- */
    function dismissToast(toast) {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(-4px)';
        toast.style.transition = 'all .18s ease';
        window.setTimeout(() => toast.remove(), 200);
    }

    document.querySelectorAll('[data-toast]').forEach(function (toast) {
        const timer = window.setTimeout(() => dismissToast(toast), 5200);
        const close = toast.querySelector('[data-toast-close]');
        if (close) {
            close.addEventListener('click', function () {
                window.clearTimeout(timer);
                dismissToast(toast);
            });
        }
    });

    /* ---------- Password visibility ---------- */
    document.addEventListener('click', function (event) {
        const toggle = event.target.closest('[data-password-toggle]');
        if (!toggle) return;
        const wrapper = toggle.closest('.input-group') || toggle.parentElement;
        const input = wrapper ? wrapper.querySelector('input') : null;
        if (!input) return;
        const isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';
        toggle.setAttribute('aria-label', isPassword ? 'Скрыть пароль' : 'Показать пароль');
        toggle.classList.toggle('is-on', isPassword);
        const use = toggle.querySelector('use');
        if (use) use.setAttribute('href', isPassword ? '#i-eye-off' : '#i-eye');
    });

    /* ---------- Confirm forms ---------- */
    document.addEventListener('submit', function (event) {
        const form = event.target;
        const message = form.getAttribute('data-confirm');
        if (message && !window.confirm(message)) {
            event.preventDefault();
        }
    });

    /* ---------- Быстрый переход в кассу (N) ---------- */
    document.addEventListener('keydown', function (event) {
        if (event.key.toLowerCase() !== 'n' || event.metaKey || event.ctrlKey || event.altKey) return;
        const active = document.activeElement;
        if (active && (active.tagName === 'INPUT' || active.tagName === 'TEXTAREA' || active.tagName === 'SELECT' || active.isContentEditable)) return;
        const link = document.querySelector('.nav__item[href$="/pos"]');
        if (link) {
            event.preventDefault();
            window.location.href = link.href;
        }
    });

    /* ---------- Product form ---------- */
    const productModal = document.getElementById('product-modal');
    if (productModal) {
        const form = productModal.querySelector('form');
        const title = productModal.querySelector('[data-modal-title]');
        const stockField = productModal.querySelector('[data-stock-field]');
        const preview = productModal.querySelector('[data-photo-preview]');
        const fileInput = productModal.querySelector('input[type="file"]');
        const removePhoto = productModal.querySelector('input[name="remove_photo"]');
        const existingPhoto = productModal.querySelector('[data-existing-photo]');
        const historyWrap = productModal.querySelector('[data-price-history-wrap]');
        const historyBox = productModal.querySelector('[data-price-history]');

        function renderPriceHistory(rows) {
            if (!historyWrap || !historyBox) return;
            historyBox.innerHTML = '';
            if (!rows || !rows.length) {
                const empty = document.createElement('p');
                empty.className = 'hint';
                empty.textContent = 'История появится после первого изменения цены.';
                historyBox.appendChild(empty);
                return;
            }
            rows.forEach(function (row) {
                const item = document.createElement('div');
                item.className = 'price-history__item';

                const head = document.createElement('div');
                head.className = 'price-history__head';
                const date = document.createElement('strong');
                date.textContent = row.date || '';
                const meta = document.createElement('span');
                meta.textContent = (row.source || '') + ' · ' + (row.user || 'Система');
                head.appendChild(date);
                head.appendChild(meta);

                const changes = document.createElement('div');
                changes.className = 'price-history__changes';
                if (row.purchase_changed) {
                    const line = document.createElement('span');
                    line.textContent = 'Себестоимость: ' + row.old_purchase + ' → ' + row.new_purchase;
                    changes.appendChild(line);
                }
                if (row.sale_changed) {
                    const line = document.createElement('span');
                    line.textContent = 'Цена продажи: ' + row.old_sale + ' → ' + row.new_sale;
                    changes.appendChild(line);
                }
                if (!row.purchase_changed && !row.sale_changed) {
                    const line = document.createElement('span');
                    line.textContent = 'Текущая цена: ' + row.new_sale;
                    changes.appendChild(line);
                }

                item.appendChild(head);
                item.appendChild(changes);
                if (row.comment) {
                    const comment = document.createElement('div');
                    comment.className = 'price-history__comment';
                    comment.textContent = row.comment;
                    item.appendChild(comment);
                }
                historyBox.appendChild(item);
            });
        }

        function setPreview(url, name) {
            if (!preview) return;
            preview.innerHTML = '';
            if (url) {
                const img = document.createElement('img');
                img.src = url;
                img.alt = name || '';
                preview.appendChild(img);
            } else {
                const span = document.createElement('span');
                span.className = 'thumb-ph';
                span.style.setProperty('--hue', String(Math.abs(hashCode(name || '?')) % 360));
                const inner = document.createElement('span');
                inner.textContent = (name || '?').trim().charAt(0).toUpperCase() || '?';
                span.appendChild(inner);
                preview.appendChild(span);
            }
        }

        function hashCode(str) {
            let hash = 0;
            for (let i = 0; i < str.length; i++) {
                hash = ((hash << 5) - hash) + str.charCodeAt(i);
                hash |= 0;
            }
            return hash;
        }

        function fillForm(data) {
            if (!data) return;
            form.reset();
            form.elements.id.value = data.id || '';
            form.elements.name.value = data.name || '';
            form.elements.sku.value = data.sku || '';
            form.elements.category.value = data.category || '';
            form.elements.unit.value = data.unit || 'шт';
            if (form.elements.purchase_price) form.elements.purchase_price.value = data.purchase_price || '';
            if (form.elements.sale_price) form.elements.sale_price.value = data.sale_price || '';
            if (!form.elements.sale_price) {
                const saleInput = form.querySelector('[name="sale_price"]');
                if (saleInput) saleInput.value = data.sale_price || '';
            }
            form.elements.low_stock.value = data.low_stock != null ? data.low_stock : 3;
            if (form.elements.stock_qty) {
                form.elements.stock_qty.value = data.stock_qty != null ? data.stock_qty : 0;
            }
            if (title) title.textContent = data.id ? 'Редактировать товар' : 'Новый товар';
            if (stockField) stockField.style.display = data.id ? 'none' : '';
            if (removePhoto) removePhoto.checked = false;
            if (existingPhoto) existingPhoto.value = data.photo || '';
            setPreview(data.photo_url || '', data.name || '');
            if (historyWrap) {
                historyWrap.hidden = !data.id;
                renderPriceHistory(data.price_history || []);
            }
            form.querySelector('[type="submit"]').textContent = data.id ? 'Сохранить изменения' : 'Добавить товар';
        }

        document.querySelectorAll('[data-product-edit]').forEach(function (button) {
            button.addEventListener('click', function () {
                try {
                    fillForm(JSON.parse(button.getAttribute('data-product-edit')));
                } catch (error) {
                    fillForm(null);
                }
                openModal('product-modal');
            });
        });

        document.querySelectorAll('[data-product-new]').forEach(function (button) {
            button.addEventListener('click', function () {
                fillForm({ low_stock: 3, unit: 'шт', category: form.elements.category.value });
                form.elements.name.focus();
                openModal('product-modal');
            });
        });

        if (fileInput) {
            fileInput.addEventListener('change', function () {
                const file = fileInput.files && fileInput.files[0];
                if (!file) return;
                if (removePhoto) removePhoto.checked = false;
                const reader = new FileReader();
                reader.onload = function (event) {
                    if (preview) {
                        preview.innerHTML = '';
                        const img = document.createElement('img');
                        img.src = event.target.result;
                        preview.appendChild(img);
                    }
                };
                reader.readAsDataURL(file);
            });
        }
    }

    /* ---------- Массовое изменение цен ---------- */
    const priceToggle = document.querySelector('[data-price-mode]');
    const pricePanel = document.querySelector('[data-price-panel]');
    if (priceToggle && pricePanel) {
        const syncPricePanel = function () {
            pricePanel.hidden = !priceToggle.checked;
        };
        priceToggle.addEventListener('change', syncPricePanel);
        if (new URLSearchParams(window.location.search).get('mass') === '1') {
            priceToggle.checked = true;
        }
        syncPricePanel();
    }

    /* ---------- Stock form ---------- */
    const stockModal = document.getElementById('stock-modal');
    if (stockModal) {
        const form = stockModal.querySelector('form');
        const title = stockModal.querySelector('[data-modal-title]');
        const select = form.elements.id;
        const mode = form.elements.mode;
        const qty = form.elements.qty;
        const cost = form.elements.unit_cost;
        const costRow = stockModal.querySelector('[data-cost-row]');
        const hint = stockModal.querySelector('[data-stock-hint]');

        function updateMode() {
            const isIn = mode.value === 'in';
            if (costRow) costRow.style.display = isIn ? '' : 'none';
            if (title) title.textContent = isIn ? 'Приход товара' : 'Инвентаризация';
            if (qty) qty.placeholder = isIn ? 'Например, 20' : 'Фактический остаток';
            if (hint) {
                const selected = select.options[select.selectedIndex];
                const current = selected ? selected.getAttribute('data-stock') : '';
                hint.textContent = isIn
                    ? 'Сейчас на складе: ' + (current || '0') + '. Остаток увеличится на указанное количество.'
                    : 'Сейчас на складе: ' + (current || '0') + '. Остаток станет равен указанному числу.';
            }
        }

        mode.addEventListener('change', updateMode);
        select.addEventListener('change', updateMode);

        document.querySelectorAll('[data-stock-open]').forEach(function (button) {
            button.addEventListener('click', function () {
                const data = button.getAttribute('data-stock-open');
                form.reset();
                if (data) {
                    try {
                        const parsed = JSON.parse(data);
                        if (parsed.id) select.value = String(parsed.id);
                        if (parsed.mode) mode.value = parsed.mode;
                    } catch (error) { /* ignore */ }
                }
                qty.value = '';
                if (cost) cost.value = '';
                updateMode();
                openModal('stock-modal');
            });
        });
    }

    /* ---------- Employee form ---------- */
    const employeeModal = document.getElementById('employee-modal');
    if (employeeModal) {
        const form = employeeModal.querySelector('form');
        const title = employeeModal.querySelector('[data-modal-title]');
        const presets = window.PIZ_ROLE_PRESETS || {};
        const passwordHint = employeeModal.querySelector('[data-password-hint]');

        const checkboxes = Array.prototype.slice.call(
            employeeModal.querySelectorAll('input[name="permissions[]"]')
        );

        function applyPreset(role) {
            const preset = presets[role];
            if (!preset) return;
            checkboxes.forEach(function (box) {
                box.checked = preset.indexOf(box.value) !== -1;
            });
        }

        form.elements.role.addEventListener('change', function () {
            applyPreset(form.elements.role.value);
        });

        function fillEmployee(data) {
            form.reset();
            form.elements.id.value = data.id || '';
            form.elements.name.value = data.name || '';
            form.elements.login.value = data.login || '';
            if (form.elements.email) form.elements.email.value = data.email || '';
            form.elements.role.value = data.role || 'cashier';
            form.elements.active.checked = data.active !== false;
            form.elements.password.value = '';
            if (form.elements.password_confirm) form.elements.password_confirm.value = '';
            const permissions = data.permissions || [];
            checkboxes.forEach(function (box) {
                box.checked = permissions.indexOf(box.value) !== -1;
            });
            if (title) title.textContent = data.id ? 'Редактировать сотрудника' : 'Новый сотрудник';
            if (passwordHint) {
                passwordHint.textContent = data.id
                    ? 'Оставьте пустым, чтобы не менять пароль.'
                    : 'Минимум 8 символов, хотя бы одна буква.';
            }
            form.querySelector('[type="submit"]').textContent = data.id ? 'Сохранить' : 'Добавить сотрудника';
        }

        document.querySelectorAll('[data-employee-new]').forEach(function (button) {
            button.addEventListener('click', function () {
                fillEmployee({ role: 'cashier', active: true, permissions: presets.cashier || [] });
                openModal('employee-modal');
            });
        });

        document.querySelectorAll('[data-employee-edit]').forEach(function (button) {
            button.addEventListener('click', function () {
                let data = {};
                try {
                    data = JSON.parse(button.getAttribute('data-employee-edit'));
                } catch (error) { /* ignore */ }
                fillEmployee(data);
                openModal('employee-modal');
            });
        });
    }

    /* ---------- Reset employee password ---------- */
    document.querySelectorAll('[data-password-open]').forEach(function (button) {
        button.addEventListener('click', function () {
            const modal = document.getElementById('password-modal');
            if (!modal) return;
            modal.querySelector('form').reset();
            modal.querySelector('form').elements.id.value = button.getAttribute('data-password-open');
            modal.querySelector('[data-password-name]').textContent = button.getAttribute('data-name') || '';
            openModal('password-modal');
        });
    });

    /* ---------- Settings preview ---------- */
    const settingsForm = document.getElementById('settings-form');
    if (settingsForm) {
        const map = {
            store_name: '[data-preview-name]',
            store_address: '[data-preview-address]',
            store_phone: '[data-preview-phone]',
            receipt_footer: '[data-preview-footer]'
        };
        Object.keys(map).forEach(function (field) {
            const input = settingsForm.elements[field];
            const target = document.querySelector(map[field]);
            if (!input || !target) return;
            const update = function () {
                target.textContent = input.value || (field === 'store_name' ? 'КИОСК ДОНЕР' : '');
            };
            input.addEventListener('input', update);
            update();
        });
    }

    /* ---------- POS: mobile tabs ---------- */
    const pos = document.getElementById('pos');
    if (pos) {
        document.querySelectorAll('[data-pos-view]').forEach(function (tab) {
            tab.addEventListener('click', function () {
                pos.setAttribute('data-view', tab.getAttribute('data-pos-view'));
                document.querySelectorAll('[data-pos-view]').forEach(function (other) {
                    other.classList.toggle('is-active', other === tab);
                });
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        });
    }
    /* ---------- PWA: установка на телефон ---------- */
    (function initPwa() {
        if ('serviceWorker' in navigator && location.protocol === 'https:') {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register('/sw.php', { scope: '/' }).catch(function () {
                    /* установка не критична для работы */
                });
            });
        }

        const banner = document.getElementById('install-banner');
        const isStandalone = window.matchMedia('(display-mode: standalone)').matches
            || window.navigator.standalone === true;
        const ua = navigator.userAgent || '';
        const isIOS = /iphone|ipad|ipod/i.test(ua)
            || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
        const isMobile = window.matchMedia('(max-width: 900px)').matches
            || /android|iphone|ipad|ipod|mobile/i.test(ua);

        function safeGet(storage, key) {
            try { return storage.getItem(key); } catch (error) { return null; }
        }
        function safeSet(storage, key, value) {
            try { storage.setItem(key, value); } catch (error) { /* ignore */ }
        }

        const stateEls = document.querySelectorAll('[data-install-state]');
        function setInstalledState() {
            stateEls.forEach(function (el) {
                el.textContent = isStandalone ? 'установлено' : 'не установлено';
                el.className = 'badge ' + (isStandalone ? 'badge--green' : 'badge--gray');
            });
        }
        setInstalledState();

        let deferredPrompt = null;
        const dismissedUntil = parseInt(safeGet(localStorage, 'piz_install_dismissed') || '0', 10);
        let dismissed = dismissedUntil > Date.now();
        const shownThisSession = safeGet(sessionStorage, 'piz_install_shown') === '1';

        function showBanner() {
            if (!banner || isStandalone || dismissed) return;
            banner.hidden = false;
            document.body.classList.add('has-install-banner');
            safeSet(sessionStorage, 'piz_install_shown', '1');
        }

        function hideBanner(remember) {
            if (banner) banner.hidden = true;
            document.body.classList.remove('has-install-banner');
            if (remember) {
                dismissed = true;
                safeSet(localStorage, 'piz_install_dismissed', String(Date.now() + 30 * 24 * 60 * 60 * 1000));
            }
        }

        function openInstallHelp() {
            if (window.PIZ_openModal) {
                window.PIZ_openModal('install-help-modal');
            } else {
                openModal('install-help-modal');
            }
        }

        if (banner) {
            const hint = banner.querySelector('[data-install-hint]');
            const bannerButton = banner.querySelector('[data-install-app]');
            if (isIOS) {
                if (hint) hint.textContent = 'Safari → Поделиться → Домой';
                if (bannerButton) bannerButton.textContent = 'Как установить';
            }

            banner.querySelectorAll('[data-install-dismiss]').forEach(function (button) {
                button.addEventListener('click', function () {
                    hideBanner(true);
                });
            });

            window.addEventListener('beforeinstallprompt', function (event) {
                event.preventDefault();
                deferredPrompt = event;
                if (!shownThisSession && !isStandalone) {
                    showBanner();
                }
            });

            window.addEventListener('appinstalled', function () {
                hideBanner(true);
                stateEls.forEach(function (el) {
                    el.textContent = 'установлено';
                    el.className = 'badge badge--green';
                });
            });

            if (isMobile && !isStandalone && !dismissed && !shownThisSession) {
                window.setTimeout(showBanner, 1200);
            }
        }

        document.querySelectorAll('[data-install-app]').forEach(function (button) {
            button.addEventListener('click', async function () {
                if (deferredPrompt) {
                    deferredPrompt.prompt();
                    try {
                        await deferredPrompt.userChoice;
                    } catch (error) { /* ignore */ }
                    deferredPrompt = null;
                    hideBanner(true);
                    return;
                }
                openInstallHelp();
            });
        });
    })();

    /* ---------- Expose for page scripts ---------- */
    window.PIZ_openModal = openModal;
    window.PIZ_closeModal = closeModal;
})();

/* ---------- Динамика: график / таблица / список ---------- */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-report-views]').forEach(function (container) {
        const buttons = container.querySelectorAll('[data-report-view]');
        const panels = container.querySelectorAll('[data-report-panel]');
        let savedView = 'chart';
        try { savedView = localStorage.getItem('piz-report-view') || 'chart'; } catch (e) {}
        const savedButton = Array.prototype.find.call(buttons, function (button) {
            return button.getAttribute('data-report-view') === savedView;
        });
        buttons.forEach(function (button) {
            button.addEventListener('click', function () {
                const view = button.getAttribute('data-report-view');
                try { localStorage.setItem('piz-report-view', view); } catch (e) {}
                buttons.forEach(function (other) {
                    const active = other === button;
                    other.classList.toggle('is-active', active);
                    other.setAttribute('aria-selected', active ? 'true' : 'false');
                });
                panels.forEach(function (panel) {
                    panel.hidden = panel.getAttribute('data-report-panel') !== view;
                });
            });
        });
        if (savedButton) savedButton.click();
    });
});

/* ---------- Подсказка на линейном графике ---------- */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-line-chart]').forEach(function (chart) {
        const tooltip = chart.querySelector('[data-line-tooltip]');
        if (!tooltip) return;
        let points = [];
        try { points = JSON.parse(chart.getAttribute('data-line-points') || '[]'); } catch (e) { points = []; }
        if (!points.length) return;

        function show(event, hit) {
            const index = Number(hit.getAttribute('data-line-hit'));
            const point = points[index];
            if (!point) return;
            tooltip.innerHTML = '<strong>' + point.date + '</strong>'
                + '<span><i style="background:#f26b21"></i>Выручка <b>' + point.revenue + '</b></span>'
                + '<span><i style="background:#24b364"></i>Прибыль <b>' + point.profit + '</b></span>'
                + '<span><i style="background:#e05b5b"></i>Расходы <b>' + point.expense + '</b></span>';
            tooltip.hidden = false;
            const rect = chart.getBoundingClientRect();
            const width = tooltip.offsetWidth || 160;
            let left = event.clientX - rect.left + 14;
            let top = event.clientY - rect.top - 12;
            left = Math.max(4, Math.min(left, rect.width - width - 4));
            top = Math.max(4, top);
            tooltip.style.left = left + 'px';
            tooltip.style.top = top + 'px';
        }

        function hide() {
            tooltip.hidden = true;
        }

        chart.addEventListener('pointermove', function (event) {
            const hit = event.target.closest('[data-line-hit]');
            if (hit) show(event, hit); else hide();
        });
        chart.addEventListener('pointerdown', function (event) {
            const hit = event.target.closest('[data-line-hit]');
            if (hit) show(event, hit);
        });
        chart.addEventListener('pointerleave', hide);
        chart.addEventListener('pointercancel', hide);
    });
});
