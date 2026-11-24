(function () {
    'use strict';

    const root = document.getElementById('pos');
    if (!root) return;

    const config = window.PIZ || {};
    const currency = config.currency || '₽';
    let products = (window.POS_PRODUCTS || []).map(function (product) {
        return Object.assign({}, product);
    });

    const allowNegative = !!config.allow_negative;
    const cart = new Map();
    let payment = 'cash';
    let discountRub = 0;
    let receivedRub = null;
    let token = '';
    let submitting = false;
    let activeCategory = 'all';
    let search = '';
    let addToastTimer = null;

    const el = {
        search: document.getElementById('pos-search'),
        categories: document.getElementById('pos-categories'),
        grid: document.getElementById('pos-grid'),
        empty: document.getElementById('pos-empty'),
        count: document.getElementById('pos-count'),
        cartItems: document.getElementById('cart-items'),
        cartCount: document.getElementById('cart-count'),
        cartTotalCount: document.getElementById('tab-cart-count'),
        subtotal: document.getElementById('cart-subtotal'),
        discount: document.getElementById('cart-discount'),
        total: document.getElementById('cart-total'),
        discountInput: document.getElementById('discount-input'),
        payOptions: document.getElementById('pay-options'),
        cashBox: document.getElementById('cash-box'),
        cashInput: document.getElementById('cash-received'),
        cashChange: document.getElementById('cash-change'),
        customer: document.getElementById('cart-customer'),
        comment: document.getElementById('cart-comment'),
        submit: document.getElementById('pos-submit'),
        error: document.getElementById('pos-error'),
        clear: document.getElementById('cart-clear'),
        receiptContent: document.getElementById('receipt-content'),
        receiptPdf: document.getElementById('receipt-pdf'),
        receiptTotal: document.getElementById('receipt-total'),
        addToast: document.getElementById('pos-add-toast'),
        addToastName: document.getElementById('pos-add-toast-name'),
        addToastCount: document.getElementById('pos-add-toast-count'),
        addToastOpen: document.getElementById('pos-add-toast-open'),
        cartTab: document.querySelector('[data-pos-view="cart"]')
    };

    const numberFormat = new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 2 });

    function money(kop) {
        return numberFormat.format(Math.round(kop) / 100) + ' ' + currency;
    }

    function esc(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function parseRub(value) {
        const cleaned = String(value == null ? '' : value).replace(/\s/g, '').replace(',', '.');
        if (cleaned === '' || isNaN(Number(cleaned))) return 0;
        return Math.max(0, Number(cleaned));
    }

    function findProduct(id) {
        return products.find(function (product) { return product.id === Number(id); });
    }

    function totals() {
        let subtotal = 0;
        let count = 0;
        cart.forEach(function (qty, id) {
            const product = findProduct(id);
            if (!product) return;
            subtotal += product.price * qty;
            count += qty;
        });
        let discount = Math.round(discountRub * 100);
        discount = Math.min(discount, subtotal);
        return { subtotal: subtotal, discount: discount, total: Math.max(0, subtotal - discount), count: count };
    }

    function hue(name) {
        let hash = 0;
        for (let i = 0; i < name.length; i++) {
            hash = ((hash << 5) - hash) + name.charCodeAt(i);
            hash |= 0;
        }
        return Math.abs(hash) % 360;
    }

    function photoMarkup(product, cls) {
        if (product.photo) {
            return '<img src="' + esc(product.photo) + '" alt="' + esc(product.name) + '" loading="lazy" decoding="async">';
        }
        return '<span class="' + cls + '" style="--hue: ' + hue(product.name) + '"><span>' + esc((product.name || '?').trim().charAt(0).toUpperCase()) + '</span></span>';
    }

    function currentStock(id) {
        const product = findProduct(id);
        return product ? product.stock : 0;
    }

    function filteredProducts() {
        const needle = search.trim().toLowerCase();
        return products.filter(function (product) {
            if (activeCategory !== 'all' && product.category !== activeCategory) return false;
            if (!needle) return true;
            return product.name.toLowerCase().indexOf(needle) !== -1
                || String(product.sku || '').toLowerCase().indexOf(needle) !== -1;
        });
    }

    function renderCategories() {
        if (!el.categories) return;
        const categories = Array.from(new Set(products.map(function (p) { return p.category; }).filter(Boolean)));
        let html = '<button type="button" class="chip' + (activeCategory === 'all' ? ' is-active' : '') + '" data-category="all">Все</button>';
        categories.forEach(function (category) {
            html += '<button type="button" class="chip' + (activeCategory === category ? ' is-active' : '') + '" data-category="' + esc(category) + '">' + esc(category) + '</button>';
        });
        el.categories.innerHTML = html;
    }

    function renderGrid() {
        if (!el.grid) return;
        const list = filteredProducts();
        el.grid.innerHTML = list.map(function (product) {
            const inCart = cart.get(product.id) || 0;
            const available = product.stock - inCart;
            const out = product.stock <= 0;
            const low = !out && product.stock <= (product.low || 3);
            const disabled = !allowNegative && available <= 0;
            const badge = out
                ? '<span class="product-card__badge product-card__badge--out">' + (allowNegative ? (product.stock < 0 ? 'минус' : '0') : 'нет') + '</span>'
                : (low ? '<span class="product-card__badge product-card__badge--low">мало</span>' : '');
            return '<button type="button" class="product-card" data-add="' + product.id + '"' + (disabled ? ' disabled' : '') + '>'
                + '<span class="product-card__media">' + photoMarkup(product, 'thumb-ph') + badge + '</span>'
                + '<span class="product-card__body">'
                + '<span class="product-card__name">' + esc(product.name) + '</span>'
                + '<span class="product-card__bottom">'
                + '<span class="product-card__price">' + money(product.price) + '</span>'
                + '<span class="product-card__stock">' + (product.stock + ' ' + esc(product.unit || 'шт')) + '</span>'
                + '</span></span></button>';
        }).join('');

        if (el.empty) {
            el.empty.hidden = list.length > 0;
        }
        if (el.count) {
            el.count.textContent = list.length + ' ' + plural(list.length, 'товар', 'товара', 'товаров') + ' в каталоге';
        }
    }

    function plural(n, one, few, many) {
        const mod10 = n % 10;
        const mod100 = n % 100;
        if (mod10 === 1 && mod100 !== 11) return one;
        if (mod10 >= 2 && mod10 <= 4 && (mod100 < 10 || mod100 >= 20)) return few;
        return many;
    }

    function renderCart() {
        const state = totals();
        const items = [];
        cart.forEach(function (qty, id) {
            const product = findProduct(id);
            if (product) items.push({ product: product, qty: qty });
        });

        if (items.length === 0) {
            el.cartItems.innerHTML = '<div class="cart-empty">' + '<svg class="icon"><use href="#i-cart"></use></svg>' + '<span>Чек пуст.<br>Выберите товары слева.</span></div>';
        } else {
            el.cartItems.innerHTML = items.map(function (item) {
                const p = item.product;
                return '<div class="cart-item" data-cart-item="' + p.id + '">'
                    + '<div class="cart-item__main">'
                    + '<div class="cart-item__row"><span class="cart-item__name">' + esc(p.name) + '</span>'
                    + '<button type="button" class="cart-item__remove" data-remove="' + p.id + '" aria-label="Удалить товар" title="Удалить товар">'
                    + '<svg class="icon"><use href="#i-trash"></use></svg></button></div>'
                    + '<div class="cart-item__row"><span class="qty">'
                    + '<button type="button" data-decrease="' + p.id + '" aria-label="Меньше">−</button>'
                    + '<span>' + item.qty + '</span>'
                    + '<button type="button" data-increase="' + p.id + '" aria-label="Больше"' + (item.qty >= p.stock ? ' disabled' : '') + '>+</button>'
                    + '</span><span class="cart-item__sum">' + money(p.price * item.qty) + '</span></div>'
                    + '<div class="cart-item__meta">' + money(p.price) + ' × ' + item.qty + '</div>'
                    + '</div></div>';
            }).join('');
        }

        if (el.cartCount) el.cartCount.textContent = String(state.count);
        if (el.cartTotalCount) el.cartTotalCount.textContent = String(state.count);
        if (el.cartTab) el.cartTab.classList.toggle('has-items', state.count > 0);
        if (el.subtotal) el.subtotal.textContent = money(state.subtotal);
        if (el.discount) el.discount.textContent = '−' + money(state.discount);
        const discountLine = document.getElementById('cart-discount-line');
        if (discountLine) discountLine.hidden = state.discount === 0;
        if (el.total) el.total.textContent = money(state.total);
        if (el.submit) {
            el.submit.disabled = items.length === 0 || submitting;
            el.submit.innerHTML = (submitting ? 'Оформляем…' : 'Оформить · ' + money(state.total))
                + (submitting ? '' : ' <svg class="icon"><use href="#i-check"></use></svg>');
        }
        if (el.receiptTotal) el.receiptTotal.textContent = money(state.total);
        updateChange(state);
    }

    function updateChange(state) {
        if (!el.cashInput || !el.cashChange) return;
        if (payment !== 'cash' || receivedRub === null) {
            el.cashChange.textContent = '—';
            return;
        }
        const change = Math.round(receivedRub * 100) - state.total;
        el.cashChange.textContent = change >= 0 ? money(change) : 'недостаточно';
        el.cashChange.style.color = change >= 0 ? '' : 'var(--red)';
    }

    function showError(message) {
        if (!el.error) return;
        el.error.textContent = message;
        el.error.hidden = false;
    }

    function clearError() {
        if (el.error) el.error.hidden = true;
    }

    function showAddedToast(product) {
        if (!el.addToast || root.getAttribute('data-view') === 'cart') return;
        if (el.addToastName) el.addToastName.textContent = '«' + product.name + '» добавлен';
        let count = 0;
        cart.forEach(function (qty) { count += qty; });
        if (el.addToastCount) el.addToastCount.textContent = 'В чеке: ' + count + ' ' + plural(count, 'товар', 'товара', 'товаров');
        el.addToast.hidden = false;
        if (el.cartTab) {
            el.cartTab.classList.add('is-bumped');
            window.setTimeout(function () { el.cartTab.classList.remove('is-bumped'); }, 380);
        }
        window.clearTimeout(addToastTimer);
        addToastTimer = window.setTimeout(function () {
            if (el.addToast) el.addToast.hidden = true;
        }, 3200);
    }

    function addProduct(id) {
        const product = findProduct(id);
        if (!product) return;
        const current = cart.get(product.id) || 0;
        if (!allowNegative && current >= product.stock) {
            showError('«' + product.name + '»: больше нет в наличии.');
            return;
        }
        cart.set(product.id, current + 1);
        clearError();
        renderGrid();
        renderCart();
        showAddedToast(product);
    }

    function changeQty(id, delta) {
        const productId = Number(id);
        const product = findProduct(productId);
        if (!product) return;
        const current = cart.get(productId) || 0;
        const next = current + delta;
        if (next <= 0) {
            cart.delete(productId);
        } else if (!allowNegative && next > product.stock) {
            showError('На складе только ' + product.stock + ' ' + product.unit + '.');
            return;
        } else {
            cart.set(productId, next);
        }
        clearError();
        renderGrid();
        renderCart();
    }

    /* ---------- Events ---------- */
    if (el.search) {
        el.search.addEventListener('input', function () {
            search = el.search.value;
            renderGrid();
        });
        el.search.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter') return;
            event.preventDefault();
            const list = filteredProducts();
            const exact = list.find(function (p) {
                return String(p.sku).toLowerCase() === search.trim().toLowerCase();
            });
            if (exact) {
                addProduct(exact.id);
                el.search.value = '';
                search = '';
                renderGrid();
            } else if (list.length === 1) {
                addProduct(list[0].id);
                el.search.value = '';
                search = '';
                renderGrid();
            }
        });
    }

    if (el.categories) {
        el.categories.addEventListener('click', function (event) {
            const chip = event.target.closest('[data-category]');
            if (!chip) return;
            activeCategory = chip.getAttribute('data-category');
            renderCategories();
            renderGrid();
        });
    }

    if (el.grid) {
        el.grid.addEventListener('click', function (event) {
            const card = event.target.closest('[data-add]');
            if (card) addProduct(card.getAttribute('data-add'));
        });
    }

    if (el.cartItems) {
        el.cartItems.addEventListener('click', function (event) {
            const increase = event.target.closest('[data-increase]');
            const decrease = event.target.closest('[data-decrease]');
            const remove = event.target.closest('[data-remove]');
            if (increase) changeQty(increase.getAttribute('data-increase'), 1);
            if (decrease) changeQty(decrease.getAttribute('data-decrease'), -1);
            if (remove) {
                cart.delete(Number(remove.getAttribute('data-remove')));
                clearError();
                renderGrid();
                renderCart();
            }
        });
    }

    if (el.discountInput) {
        el.discountInput.addEventListener('input', function () {
            discountRub = parseRub(el.discountInput.value);
            renderCart();
        });
    }

    if (el.payOptions) {
        el.payOptions.addEventListener('click', function (event) {
            const option = event.target.closest('[data-payment]');
            if (!option) return;
            payment = option.getAttribute('data-payment');
            el.payOptions.querySelectorAll('[data-payment]').forEach(function (button) {
                button.classList.toggle('is-active', button === option);
            });
            if (el.cashBox) el.cashBox.classList.toggle('is-visible', payment === 'cash');
            renderCart();
        });
    }

    if (el.cashInput) {
        el.cashInput.addEventListener('input', function () {
            const value = el.cashInput.value.trim();
            receivedRub = value === '' ? null : parseRub(value);
            clearError();
            renderCart();
        });
    }

    const exactButton = document.querySelector('[data-cash-exact]');
    if (exactButton) {
        exactButton.addEventListener('click', function () {
            const state = totals();
            receivedRub = state.total / 100;
            el.cashInput.value = receivedRub ? String(receivedRub).replace('.', ',') : '';
            clearError();
            renderCart();
        });
    }

    if (el.clear) {
        el.clear.addEventListener('click', function () {
            if (cart.size && !window.confirm('Очистить чек?')) return;
            cart.clear();
            discountRub = 0;
            receivedRub = null;
            if (el.discountInput) el.discountInput.value = '';
            if (el.cashInput) el.cashInput.value = '';
            clearError();
            renderGrid();
            renderCart();
        });
    }

    /* ---------- Submit ---------- */
    async function submitSale() {
        if (submitting) return;
        if (cart.size === 0) {
            showError('Добавьте товары в чек.');
            return;
        }
        const state = totals();
        if (payment === 'cash' && receivedRub !== null && Math.round(receivedRub * 100) < state.total) {
            showError('Полученная сумма меньше суммы к оплате.');
            return;
        }

        submitting = true;
        clearError();
        renderCart();

        token = token || (window.crypto && window.crypto.randomUUID
            ? window.crypto.randomUUID()
            : 'sale-' + Date.now() + '-' + Math.random().toString(16).slice(2));

        const payload = {
            items: Array.from(cart.entries()).map(function (entry) {
                return { id: entry[0], qty: entry[1] };
            }),
            payment_method: payment,
            discount_kop: Math.min(Math.round(discountRub * 100), state.subtotal),
            received_kop: payment === 'cash' && receivedRub !== null ? Math.round(receivedRub * 100) : null,
            customer: el.customer ? el.customer.value.trim() : '',
            comment: el.comment ? el.comment.value.trim() : '',
            token: token
        };

        try {
            const response = await fetch((config.base || '') + '/api/sales', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-Token': config.csrf || '',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(payload)
            });
            const data = await response.json().catch(function () { return { ok: false, error: 'Сервер вернул некорректный ответ.' }; });
            if (!response.ok || !data.ok) {
                showError(data.error || 'Не удалось оформить продажу.');
                submitting = false;
                renderCart();
                return;
            }

            // Уменьшаем локальные остатки, чтобы каталог сразу отражал продажу.
            cart.forEach(function (qty, id) {
                const product = findProduct(id);
                if (product) product.stock = Math.max(0, product.stock - qty);
            });

            cart.clear();
            discountRub = 0;
            receivedRub = null;
            token = '';
            submitting = false;
            payment = 'cash';
            if (el.discountInput) el.discountInput.value = '';
            if (el.cashInput) el.cashInput.value = '';
            if (el.customer) el.customer.value = '';
            if (el.comment) el.comment.value = '';
            if (el.payOptions) {
                el.payOptions.querySelectorAll('[data-payment]').forEach(function (button) {
                    button.classList.toggle('is-active', button.getAttribute('data-payment') === 'cash');
                });
            }
            if (el.cashBox) el.cashBox.classList.add('is-visible');

            renderGrid();
            renderCart();
            clearError();

            if (el.receiptContent) {
                el.receiptContent.innerHTML = data.receipt_html || '';
            }
            if (el.receiptPdf && data.sale_url) {
                el.receiptPdf.href = data.sale_url.replace(/\/$/, '') + '/pdf?v=' + encodeURIComponent(window.PIZ.pdf_version || '');
            }
            if (window.PIZ_openModal) {
                window.PIZ_openModal('receipt-modal');
            }
            if (el.search) el.search.focus();
        } catch (error) {
            showError('Нет связи с сервером. Проверьте интернет и попробуйте снова.');
            submitting = false;
            renderCart();
        }
    }

    if (el.submit) {
        el.submit.addEventListener('click', submitSale);
    }

    // Инициализация
    renderCategories();
    renderGrid();
    if (el.addToastOpen) {
        el.addToastOpen.addEventListener('click', function () {
            if (el.cartTab) el.cartTab.click();
            if (el.addToast) el.addToast.hidden = true;
        });
    }

    renderCart();
})();
