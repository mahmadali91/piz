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

    /* ---------- Print receipt ---------- */
    document.addEventListener('click', function (event) {
        if (event.target.closest('[data-print-receipt]')) {
            event.preventDefault();
            window.print();
        }
    });

    /* ---------- Print full page (reports) ---------- */
    document.addEventListener('click', function (event) {
        if (!event.target.closest('[data-print-page]')) return;
        event.preventDefault();
        document.body.classList.add('printing-page');
        window.print();
        window.setTimeout(function () {
            document.body.classList.remove('printing-page');
        }, 900);
    });
    window.addEventListener('afterprint', function () {
        document.body.classList.remove('printing-page');
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
            form.elements.sale_price.value = data.sale_price || '';
            form.elements.low_stock.value = data.low_stock != null ? data.low_stock : 3;
            if (form.elements.stock_qty) {
                form.elements.stock_qty.value = data.stock_qty != null ? data.stock_qty : 0;
            }
            if (title) title.textContent = data.id ? 'Редактировать товар' : 'Новый товар';
            if (stockField) stockField.style.display = data.id ? 'none' : '';
            if (removePhoto) removePhoto.checked = false;
            if (existingPhoto) existingPhoto.value = data.photo || '';
            setPreview(data.photo_url || '', data.name || '');
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
                cost.value = '';
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
                target.textContent = input.value || (field === 'store_name' ? 'Киоск PIZ' : '');
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
    /* ---------- Expose for page scripts ---------- */
    window.PIZ_openModal = openModal;
    window.PIZ_closeModal = closeModal;
})();
