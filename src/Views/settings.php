<?php
$tablesOk = count(array_filter($health['tables']));
$tablesTotal = count($health['tables']);
?>
<div class="page">
    <div class="settings-grid">
        <div class="settings-stack">
            <section class="card">
                <header class="card__head">
                    <div>
                        <h3>Магазин</h3>
                        <p>Название и контакты печатаются в чеке</p>
                    </div>
                    <span class="badge <?= $canManage ? 'badge--green' : 'badge--gray' ?>"><?= $canManage ? 'можно менять' : 'только просмотр' ?></span>
                </header>

                <form method="post" action="<?= e(url('/settings/save')) ?>" id="settings-form">
                    <?= csrf_field() ?>
                    <fieldset <?= $canManage ? '' : 'disabled' ?> style="border: 0; padding: 0; margin: 0;">
                        <div class="stack">
                            <label class="field">
                                <span class="label">Название киоска</span>
                                <input class="input" type="text" name="store_name" maxlength="80" value="<?= e($settings['store_name'] ?? 'Киоск PIZ') ?>" required>
                            </label>
                            <div class="form-grid">
                                <label class="field">
                                    <span class="label">Адрес</span>
                                    <input class="input" type="text" name="store_address" maxlength="160" value="<?= e($settings['store_address'] ?? '') ?>" placeholder="г. Москва, ул. Примерная, 1">
                                </label>
                                <label class="field">
                                    <span class="label">Телефон</span>
                                    <input class="input" type="text" name="store_phone" maxlength="40" value="<?= e($settings['store_phone'] ?? '') ?>" placeholder="+7 900 000-00-00">
                                </label>
                                <label class="field">
                                    <span class="label">Символ валюты</span>
                                    <input class="input" type="text" name="currency" maxlength="8" value="<?= e($settings['currency'] ?? '₽') ?>">
                                </label>
                                <label class="field">
                                    <span class="label">Минимальный остаток по умолчанию</span>
                                    <input class="input" type="number" name="low_stock_default" min="0" max="100000" value="<?= e($settings['low_stock_default'] ?? '3') ?>">
                                </label>
                            </div>

                            <div class="form-section">
                                <div class="form-grid">
                                    <label class="field">
                                        <span class="label">Текст над чеком</span>
                                        <textarea class="textarea" name="receipt_header" maxlength="500" rows="3" placeholder="ИНН, смена, рекламный текст…"><?= e($settings['receipt_header'] ?? '') ?></textarea>
                                    </label>
                                    <label class="field">
                                        <span class="label">Текст под чеком</span>
                                        <textarea class="textarea" name="receipt_footer" maxlength="500" rows="3"><?= e($settings['receipt_footer'] ?? '') ?></textarea>
                                    </label>
                                </div>
                            </div>

                            <?php if ($canManage): ?>
                                <div class="form-actions">
                                    <button class="btn btn--primary" type="submit"><?= icon('check') ?> Сохранить настройки</button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </fieldset>
                </form>
            </section>

            <section class="card">
                <header class="card__head">
                    <div>
                        <h3>Безопасность</h3>
                        <p>Смена собственного пароля</p>
                    </div>
                    <?= icon('shield', 'icon icon--muted') ?>
                </header>
                <form method="post" action="<?= e(url('/settings/password')) ?>" autocomplete="off">
                    <?= csrf_field() ?>
                    <div class="stack">
                        <div class="form-grid">
                            <label class="field">
                                <span class="label">Текущий пароль</span>
                                <input class="input" type="password" name="current_password" required autocomplete="current-password">
                            </label>
                            <span></span>
                            <label class="field">
                                <span class="label">Новый пароль</span>
                                <span class="input-group">
                                    <input class="input input--password" type="password" name="password" minlength="8" required autocomplete="new-password">
                                    <button type="button" class="input-group__action" data-password-toggle aria-label="Показать пароль"><?= icon('eye') ?></button>
                                </span>
                            </label>
                            <label class="field">
                                <span class="label">Повторите пароль</span>
                                <input class="input" type="password" name="password_confirm" minlength="8" required autocomplete="new-password">
                            </label>
                        </div>
                        <div class="form-actions">
                            <button class="btn btn--ghost" type="submit"><?= icon('key') ?> Сменить пароль</button>
                        </div>
                    </div>
                </form>
            </section>
        </div>

        <div class="settings-stack">
            <section class="receipt-preview">
                <div class="receipt-preview__paper">
                    <div style="text-align: center;">
                        <div class="receipt__logo" style="margin: 0 auto 5px;">P</div>
                        <div class="receipt__store" data-preview-name><?= e($settings['store_name'] ?? 'Киоск PIZ') ?></div>
                        <div class="receipt__muted" data-preview-address><?= e($settings['store_address'] ?? '') ?></div>
                        <div class="receipt__muted" data-preview-phone><?= e($settings['store_phone'] ?? '') ?></div>
                    </div>
                    <div class="receipt__divider"></div>
                    <div class="receipt__meta"><span>Чек № 000000-0001</span><span><?= date('d.m.Y H:i') ?></span></div>
                    <div class="receipt__meta"><span>Кассир: <?= e($user['name']) ?></span></div>
                    <div class="receipt__divider"></div>
                    <div style="font-size: 11px; display: flex; flex-direction: column; gap: 2px;">
                        <div style="display: flex; justify-content: space-between;"><span>Шаурма классическая</span><span>1 × 250</span></div>
                        <div style="display: flex; justify-content: space-between;"><span>Чай</span><span>2 × 60</span></div>
                    </div>
                    <div class="receipt__divider"></div>
                    <div class="receipt__totals">
                        <div><span>Подытог</span><span><?= e(money(37000)) ?></span></div>
                        <div class="receipt__grand"><span>ИТОГО</span><span><?= e(money(37000)) ?></span></div>
                        <div><span>Оплата</span><span>Наличные</span></div>
                    </div>
                    <div class="receipt__divider"></div>
                    <div class="receipt__thanks" data-preview-footer><?= e($settings['receipt_footer'] ?? 'Спасибо за покупку!') ?></div>
                </div>
                <p class="muted" style="text-align: center; font-size: 11px; margin-top: 12px;">Так чек будет выглядеть на термопринтере 80 мм</p>
            </section>

            <section class="card">
                <header class="card__head">
                    <div>
                        <h3>Система</h3>
                        <p>Состояние сервиса</p>
                    </div>
                    <span class="badge <?= $tablesOk === $tablesTotal ? 'badge--green' : 'badge--amber' ?>"><?= $tablesOk ?>/<?= $tablesTotal ?> таблиц</span>
                </header>
                <div class="health-list">
                    <div class="health-row"><span>Версия PIZ</span><strong><?= e(PIZ_VERSION) ?></strong></div>
                    <div class="health-row"><span>PHP</span><strong><?= e($health['php']) ?></strong></div>
                    <div class="health-row"><span>MySQL / MariaDB</span><strong><?= e($health['mysql']) ?></strong></div>
                    <div class="health-row"><span>База данных</span><strong class="health-ok">подключена</strong></div>
                    <div class="health-row">
                        <span>Запись в uploads</span>
                        <strong class="<?= $health['uploads_writable'] ? 'health-ok' : 'health-bad' ?>"><?= $health['uploads_writable'] ? 'да' : 'нет' ?></strong>
                    </div>
                    <div class="health-row">
                        <span>Запись в config</span>
                        <strong class="<?= $health['config_writable'] ? 'health-ok' : 'health-bad' ?>"><?= $health['config_writable'] ? 'да' : 'нет' ?></strong>
                    </div>
                    <div class="health-row"><span>Товаров</span><strong><?= (int) $health['products'] ?></strong></div>
                    <div class="health-row"><span>Сотрудников</span><strong><?= (int) $health['users'] ?></strong></div>
                    <div class="health-row"><span>Продаж</span><strong><?= (int) $health['sales'] ?></strong></div>
                    <div class="health-row"><span>Последняя продажа</span><strong><?= $health['last_sale'] ? e(dt_human($health['last_sale'])) : '—' ?></strong></div>
                </div>
                <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-top: 14px;">
                    <a class="btn btn--ghost btn--sm" href="<?= e(url('/activity')) ?>"><?= icon('activity') ?> Журнал</a>
                    <a class="btn btn--ghost btn--sm" href="<?= e(url('/healthz')) ?>" target="_blank" rel="noopener"><?= icon('shield') ?> Проверка</a>
                    <form method="post" action="<?= e(url('/logout')) ?>" style="margin-left: auto;">
                        <?= csrf_field() ?>
                        <button class="btn btn--ghost btn--sm" type="submit"><?= icon('logout') ?> Выйти</button>
                    </form>
                </div>
            </section>
        </div>
    </div>
</div>
