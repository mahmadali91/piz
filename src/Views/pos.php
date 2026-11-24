<div class="page" id="pos" data-view="catalog">
    <div class="pos-mobile-tabs">
        <button type="button" class="tab is-active" data-pos-view="catalog">Каталог</button>
        <button type="button" class="tab" data-pos-view="cart">Чек · <span id="tab-cart-count">0</span></button>
    </div>

    <div class="pos">
        <section class="pos__catalog">
            <div class="pos__search-row">
                <div class="search-box pos__search">
                    <?= icon('search') ?>
                    <input class="input" id="pos-search" type="search" placeholder="Поиск товара или артикул…" autocomplete="off" autofocus>
                </div>
                <?php if (\PIZ\Auth::can('products.view')): ?>
                    <a class="btn btn--ghost" href="<?= e(url('/products')) ?>" title="Управление товарами"><?= icon('box') ?><span class="hide-sm">Товары</span></a>
                <?php endif; ?>
            </div>

            <div class="chips" id="pos-categories"></div>

            <div class="pos__meta">
                <span class="pos__count" id="pos-count"></span>
                <span class="muted" style="font-size: 11.5px;">Нажмите на товар, чтобы добавить его в чек</span>
            </div>

            <div class="product-grid" id="pos-grid"></div>
            <div class="empty" id="pos-empty" hidden>
                <?= icon('search') ?>
                <p>Ничего не найдено. Попробуйте другой запрос.</p>
            </div>
        </section>

        <aside class="pos__cart">
            <div class="cart-head">
                <h3><?= icon('cart') ?> Чек <span class="cart-head__count" id="cart-count">0</span></h3>
                <button type="button" class="cart-head__clear" id="cart-clear">Очистить</button>
            </div>

            <div class="cart-items" id="cart-items">
                <div class="cart-empty">
                    <?= icon('cart') ?>
                    <span>Чек пуст.<br>Выберите товары слева.</span>
                </div>
            </div>

            <div class="cart-foot">
                <div class="alert alert--error" id="pos-error" hidden></div>

                <div class="discount-row">
                    <span class="label">Скидка</span>
                    <input class="input" id="discount-input" inputmode="decimal" placeholder="0" autocomplete="off">
                    <span class="muted" style="font-size: 12px;"><?= e(currency()) ?></span>
                </div>

                <div class="cart-line"><span>Подытог</span><strong id="cart-subtotal">0</strong></div>
                <div class="cart-line" id="cart-discount-line"><span>Скидка</span><strong id="cart-discount">0</strong></div>
                <div class="cart-line cart-line--total"><span>К оплате</span><strong id="cart-total">0</strong></div>

                <div class="pay-options" id="pay-options">
                    <button type="button" class="pay-option is-active" data-payment="cash">
                        <?= icon('cash') ?><span>Наличные</span>
                    </button>
                    <button type="button" class="pay-option" data-payment="card">
                        <?= icon('card') ?><span>Карта</span>
                    </button>
                    <button type="button" class="pay-option" data-payment="transfer">
                        <?= icon('bank') ?><span>Перевод</span>
                    </button>
                </div>

                <div class="cash-box is-visible" id="cash-box">
                    <div class="cash-row">
                        <span class="label">Получено от покупателя</span>
                        <input class="input" id="cash-received" inputmode="decimal" placeholder="Сумма" autocomplete="off">
                    </div>
                    <div class="change-line"><span>Сдача</span><strong id="cash-change">—</strong></div>
                    <button type="button" class="btn btn--ghost btn--sm" data-cash-exact style="align-self: flex-end;">Без сдачи</button>
                </div>

                <details class="cart-more">
                    <summary><?= icon('user', 'icon icon--xs') ?> Покупатель и комментарий</summary>
                    <div class="stack" style="gap: 8px;">
                        <input class="input" id="cart-customer" placeholder="Имя покупателя (необязательно)" autocomplete="off">
                        <textarea class="textarea" id="cart-comment" placeholder="Комментарий к продаже (необязательно)"></textarea>
                    </div>
                </details>

                <button class="btn btn--primary btn--lg btn--block" type="button" id="pos-submit" disabled>
                    Оформить · 0 <?= icon('check') ?>
                </button>
            </div>
        </aside>
    </div>
</div>

<div class="modal" id="receipt-modal" aria-hidden="true">
    <div class="modal__backdrop" data-modal-close></div>
    <div class="modal__panel modal__panel--receipt" role="dialog" aria-modal="true" aria-labelledby="receipt-title">
        <div class="modal__head">
            <div>
                <h3 id="receipt-title">Продажа оформлена</h3>
                <p>Чек <?= icon('check', 'icon icon--xs') ?> оплата принята</p>
            </div>
            <button type="button" class="icon-btn" data-modal-close aria-label="Закрыть"><?= icon('close') ?></button>
        </div>
        <div class="modal__body" id="receipt-content"></div>
        <div class="receipt-modal__actions">
            <button type="button" class="btn btn--primary" data-print-receipt><?= icon('print') ?> Печать</button>
            <a class="btn btn--ghost" id="receipt-open" href="#" target="_blank" rel="noopener">Открыть чек</a>
            <button type="button" class="btn btn--ghost" data-modal-close>Новая продажа</button>
        </div>
    </div>
</div>

<script>
    window.POS_PRODUCTS = <?= json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    window.PIZ = <?= json_encode([
        'base' => base_url(),
        'csrf' => csrf_token(),
        'currency' => currency(),
        'allow_negative' => (bool) ($allowNegative ?? false),
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
