<?php
/** @var array $products */
$query = $_GET;
unset($query['page']);
?>
<div class="page">
    <section class="summary-grid">
        <div class="stat">
            <span class="stat__label">Позиций в продаже</span>
            <span class="stat__value"><?= (int) $stats['total'] ?></span>
        </div>
        <div class="stat">
            <span class="stat__label">Единиц на складе</span>
            <span class="stat__value"><?= (int) $stats['units'] ?></span>
        </div>
        <div class="stat <?= $stats['low_count'] > 0 ? 'stat--accent' : '' ?>">
            <span class="stat__label">Заканчивается</span>
            <span class="stat__value <?= $stats['low_count'] > 0 ? 'stat__value--red' : '' ?>"><?= (int) $stats['low_count'] ?></span>
        </div>
        <?php if ($finance): ?>
            <div class="stat">
                <span class="stat__label">Остаток по закупке</span>
                <span class="stat__value"><?= e(money($stats['cost_value'])) ?></span>
            </div>
            <div class="stat">
                <span class="stat__label">Потенциал по продаже</span>
                <span class="stat__value"><?= e(money($stats['retail_value'])) ?></span>
            </div>
        <?php endif; ?>
    </section>

    <section class="card">
        <div class="toolbar" style="margin-bottom: 14px;">
            <form method="get" action="<?= e(url('/products')) ?>" class="toolbar__left" style="gap: 8px;">
                <div class="search-box">
                    <?= icon('search') ?>
                    <input class="input" type="search" name="q" value="<?= e($filters['search']) ?>" placeholder="Название, артикул, категория…">
                </div>
                <select class="select" name="category" aria-label="Категория">
                    <option value="">Все категории</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= e($category) ?>" <?= $filters['category'] === $category ? 'selected' : '' ?>><?= e($category) ?></option>
                    <?php endforeach; ?>
                </select>
                <label class="checkbox"><input type="checkbox" name="low" value="1" <?= $filters['low_only'] ? 'checked' : '' ?>> Мало</label>
                <label class="checkbox"><input type="checkbox" name="archived" value="1" <?= $filters['include_archived'] ? 'checked' : '' ?>> Архив</label>
                <button class="btn btn--ghost btn--sm" type="submit"><?= icon('filter') ?> Показать</button>
            </form>
            <div class="toolbar__right">
                <?php if ($canManage): ?>
                    <button class="btn btn--primary btn--sm" type="button" data-product-new><?= icon('plus') ?> Добавить товар</button>
                    <button class="btn btn--ghost btn--sm" type="button" data-stock-open='{"mode":"in"}'><?= icon('truck') ?> Приход</button>
                    <button class="btn btn--ghost btn--sm" type="button" data-stock-open='{"mode":"set"}'><?= icon('list') ?> Инвентаризация</button>
                    <button class="btn btn--ghost btn--sm" type="button" data-modal-open="import-modal"><?= icon('upload') ?> Импорт</button>
                <?php endif; ?>
                <a class="btn btn--ghost btn--sm" href="<?= e(query_url('/products/export', $query)) ?>"><?= icon('download') ?> Экспорт</a>
            </div>
        </div>

        <?php if ($products === []): ?>
            <div class="empty">
                <?= icon('box') ?>
                <p>Товаров не найдено</p>
                <?php if ($canManage): ?>
                    <button class="btn btn--soft btn--sm" type="button" data-product-new>Добавить первый товар</button>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-wrap table-wrap--products">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Товар</th>
                        <th>Артикул</th>
                        <th>Категория</th>
                        <?php if ($finance): ?><th class="num">Закупка</th><?php endif; ?>
                        <th class="num">Цена</th>
                        <th class="num">Остаток</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($products as $product):
                        $editData = [
                            'id' => (int) $product['id'],
                            'name' => $product['name'],
                            'sku' => $product['sku'],
                            'category' => $product['category'],
                            'unit' => $product['unit'],
                            'purchase_price' => $finance ? kop_to_input((int) $product['purchase_price_kop']) : '',
                            'sale_price' => kop_to_input((int) $product['sale_price_kop']),
                            'low_stock' => (int) $product['low_stock'],
                            'stock_qty' => (int) $product['stock_qty'],
                            'photo' => $product['photo'],
                            'photo_url' => \PIZ\Services\ImageUploader::url($product['photo'] ?? null),
                        ];
                        $low = (int) $product['stock_qty'] <= (int) $product['low_stock'];
                        ?>
                        <tr>
                            <td>
                                <div class="product-cell">
                                    <?= product_photo($product, 'thumb') ?>
                                    <div class="product-cell__text">
                                        <strong><?= e($product['name']) ?></strong>
                                        <span>
                                            <?php if ((int) $product['active'] !== 1): ?>
                                                <span class="badge badge--gray">В архиве</span>
                                            <?php else: ?>
                                                <?= (int) $product['low_stock'] ?> <?= e($product['unit']) ?> — минимум
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td class="muted"><?= e($product['sku'] ?: '—') ?></td>
                            <td><?= e($product['category']) ?></td>
                            <?php if ($finance): ?><td class="num muted"><?= e(money((int) $product['purchase_price_kop'])) ?></td><?php endif; ?>
                            <td class="num strong"><?= e(money((int) $product['sale_price_kop'])) ?></td>
                            <td class="num">
                                <span class="stock-pill <?= (int) $product['stock_qty'] <= 0 ? 'stock-pill--out' : ($low ? 'stock-pill--low' : '') ?>">
                                    <?= (int) $product['stock_qty'] ?> <?= e($product['unit']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <?php if ($canManage): ?>
                                        <button class="icon-btn" type="button" title="Редактировать" data-product-edit="<?= e(json_encode($editData, JSON_UNESCAPED_UNICODE)) ?>"><?= icon('edit') ?></button>
                                    <?php endif; ?>
                                    <?php if ($canStock): ?>
                                        <button class="icon-btn" type="button" title="Приход / инвентаризация" data-stock-open='<?= e(json_encode(['id' => (int) $product['id'], 'mode' => 'in'], JSON_UNESCAPED_UNICODE)) ?>'><?= icon('truck') ?></button>
                                    <?php endif; ?>
                                    <?php if ($canManage): ?>
                                        <form method="post" action="<?= e(url('/products/toggle')) ?>" data-confirm="<?= (int) $product['active'] === 1 ? 'Убрать товар в архив? Он пропадёт из кассы, история продаж сохранится.' : 'Вернуть товар в продажу?' ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
                                            <button class="icon-btn" type="submit" title="<?= (int) $product['active'] === 1 ? 'В архив' : 'Вернуть в продажу' ?>">
                                                <?= (int) $product['active'] === 1 ? icon('trash') : icon('refresh') ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="product-mobile-list">
                <?php foreach ($products as $product):
                    $low = (int) $product['stock_qty'] <= (int) $product['low_stock'];
                    $editData = [
                        'id' => (int) $product['id'],
                        'name' => $product['name'],
                        'sku' => $product['sku'],
                        'category' => $product['category'],
                        'unit' => $product['unit'],
                        'purchase_price' => $finance ? kop_to_input((int) $product['purchase_price_kop']) : '',
                        'sale_price' => kop_to_input((int) $product['sale_price_kop']),
                        'low_stock' => (int) $product['low_stock'],
                        'stock_qty' => (int) $product['stock_qty'],
                        'photo' => $product['photo'],
                        'photo_url' => \PIZ\Services\ImageUploader::url($product['photo'] ?? null),
                    ];
                    ?>
                    <article class="product-mobile-card">
                        <?= product_photo($product, 'thumb thumb--lg') ?>
                        <div class="product-mobile-card__body">
                            <div class="product-mobile-card__top">
                                <strong><?= e($product['name']) ?></strong>
                                <span class="stock-pill <?= (int) $product['stock_qty'] <= 0 ? 'stock-pill--out' : ($low ? 'stock-pill--low' : '') ?>">
                                    <?= (int) $product['stock_qty'] ?> <?= e($product['unit']) ?>
                                </span>
                            </div>
                            <div class="product-mobile-card__meta">
                                <?= e($product['sku'] ?: '—') ?> · <?= e($product['category']) ?>
                                <?php if ((int) $product['active'] !== 1): ?>
                                    <span class="badge badge--gray">архив</span>
                                <?php endif; ?>
                            </div>
                            <div class="product-mobile-card__bottom">
                                <strong><?= e(money((int) $product['sale_price_kop'])) ?></strong>
                                <span>минимум <?= (int) $product['low_stock'] ?> <?= e($product['unit']) ?></span>
                            </div>
                        </div>
                        <div class="product-mobile-card__actions">
                            <?php if ($canManage): ?>
                                <button class="icon-btn" type="button" title="Редактировать" data-product-edit="<?= e(json_encode($editData, JSON_UNESCAPED_UNICODE)) ?>"><?= icon('edit') ?></button>
                            <?php endif; ?>
                            <?php if ($canStock): ?>
                                <button class="icon-btn" type="button" title="Приход / инвентаризация" data-stock-open='<?= e(json_encode(['id' => (int) $product['id'], 'mode' => 'in'], JSON_UNESCAPED_UNICODE)) ?>'><?= icon('truck') ?></button>
                            <?php endif; ?>
                            <?php if ($canManage): ?>
                                <form method="post" action="<?= e(url('/products/toggle')) ?>" data-confirm="<?= (int) $product['active'] === 1 ? 'Убрать товар в архив? Он пропадёт из кассы, история продаж сохранится.' : 'Вернуть товар в продажу?' ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
                                    <button class="icon-btn" type="submit" title="<?= (int) $product['active'] === 1 ? 'В архив' : 'Вернуть в продажу' ?>">
                                        <?= (int) $product['active'] === 1 ? icon('trash') : icon('refresh') ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php if ($canManage): ?>
    <div class="modal" id="product-modal" aria-hidden="true">
        <div class="modal__backdrop" data-modal-close></div>
        <div class="modal__panel modal__panel--wide" role="dialog" aria-modal="true" aria-labelledby="product-modal-title">
            <div class="modal__head">
                <div>
                    <h3 id="product-modal-title" data-modal-title>Новый товар</h3>
                    <p>Фото, цены и минимальный остаток</p>
                </div>
                <button type="button" class="icon-btn" data-modal-close aria-label="Закрыть"><?= icon('close') ?></button>
            </div>
            <form method="post" action="<?= e(url('/products/save')) ?>" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="">
                <div class="modal__body">
                    <div class="stack">
                        <div class="product-form-photo">
                            <div class="photo-preview" data-photo-preview>
                                <?= icon('image') ?>
                            </div>
                            <div class="photo-actions">
                                <label class="field">
                                    <span class="label">Фото товара</span>
                                    <input type="file" name="photo" accept="image/jpeg,image/png,image/webp">
                                    <span class="hint">JPG, PNG или WEBP до 6 МБ. Фото сжимается автоматически.</span>
                                </label>
                                <label class="checkbox"><input type="checkbox" name="remove_photo" value="1"> Удалить текущее фото</label>
                            </div>
                        </div>

                        <div class="form-grid">
                            <label class="field span-2">
                                <span class="label">Название *</span>
                                <input class="input" type="text" name="name" maxlength="160" required placeholder="Например, Шаурма классическая">
                            </label>
                            <label class="field">
                                <span class="label">Артикул</span>
                                <input class="input" type="text" name="sku" maxlength="32" placeholder="SKU-001">
                                <span class="hint">Если пусто — присвоится автоматически.</span>
                            </label>
                            <label class="field">
                                <span class="label">Категория</span>
                                <input class="input" type="text" name="category" list="category-list" maxlength="80" placeholder="Напитки">
                                <datalist id="category-list">
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= e($category) ?>"></option>
                                    <?php endforeach; ?>
                                </datalist>
                            </label>
                            <?php if ($finance): ?>
                                <label class="field">
                                    <span class="label">Закупочная цена</span>
                                    <input class="input" type="text" name="purchase_price" inputmode="decimal" placeholder="0">
                                </label>
                            <?php endif; ?>
                            <label class="field">
                                <span class="label">Цена продажи *</span>
                                <input class="input" type="text" name="sale_price" inputmode="decimal" required placeholder="0">
                            </label>
                            <label class="field">
                                <span class="label">Единица</span>
                                <input class="input" type="text" name="unit" maxlength="16" value="шт">
                            </label>
                            <label class="field">
                                <span class="label">Минимальный остаток</span>
                                <input class="input" type="number" name="low_stock" min="0" max="100000" value="3">
                                <span class="hint">Ниже этого значения товар попадёт в «Пора докупить».</span>
                            </label>
                            <label class="field" data-stock-field>
                                <span class="label">Начальный остаток</span>
                                <input class="input" type="number" name="stock_qty" min="0" max="1000000" value="0">
                                <span class="hint">Можно изменить позже через приход.</span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal__foot">
                    <button class="btn btn--ghost" type="button" data-modal-close>Отмена</button>
                    <button class="btn btn--primary" type="submit">Добавить товар</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal" id="stock-modal" aria-hidden="true">
        <div class="modal__backdrop" data-modal-close></div>
        <div class="modal__panel" role="dialog" aria-modal="true" aria-labelledby="stock-modal-title">
            <div class="modal__head">
                <div>
                    <h3 id="stock-modal-title" data-modal-title>Приход товара</h3>
                    <p>Остаток обновится сразу после сохранения</p>
                </div>
                <button type="button" class="icon-btn" data-modal-close aria-label="Закрыть"><?= icon('close') ?></button>
            </div>
            <form method="post" action="<?= e(url('/products/stock')) ?>">
                <?= csrf_field() ?>
                <div class="modal__body">
                    <div class="stack">
                        <label class="field">
                            <span class="label">Товар</span>
                            <select class="select" name="id" required>
                                <option value="">Выберите товар</option>
                                <?php foreach ($products as $product): ?>
                                    <?php if ((int) $product['active'] !== 1) continue; ?>
                                    <option value="<?= (int) $product['id'] ?>" data-stock="<?= (int) $product['stock_qty'] ?>">
                                        <?= e($product['name']) ?> · сейчас <?= (int) $product['stock_qty'] ?> <?= e($product['unit']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="field">
                            <span class="label">Операция</span>
                            <select class="select" name="mode">
                                <option value="in">Приход — увеличить остаток</option>
                                <option value="set">Инвентаризация — указать фактический остаток</option>
                            </select>
                        </label>
                        <div class="form-grid">
                            <label class="field">
                                <span class="label">Количество</span>
                                <input class="input" type="number" name="qty" min="0" required placeholder="Например, 20">
                            </label>
                            <label class="field" data-cost-row>
                                <span class="label">Закупочная цена за единицу</span>
                                <input class="input" type="text" name="unit_cost" inputmode="decimal" placeholder="Необязательно">
                            </label>
                        </div>
                        <label class="field">
                            <span class="label">Причина / комментарий</span>
                            <input class="input" type="text" name="reason" maxlength="255" placeholder="Например, поставка от 23 ноября">
                        </label>
                        <div class="import-help" data-stock-hint></div>
                    </div>
                </div>
                <div class="modal__foot">
                    <button class="btn btn--ghost" type="button" data-modal-close>Отмена</button>
                    <button class="btn btn--primary" type="submit">Сохранить</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal" id="import-modal" aria-hidden="true">
        <div class="modal__backdrop" data-modal-close></div>
        <div class="modal__panel" role="dialog" aria-modal="true" aria-labelledby="import-modal-title">
            <div class="modal__head">
                <div>
                    <h3 id="import-modal-title">Импорт товаров</h3>
                    <p>Excel (.xlsx) или CSV</p>
                </div>
                <button type="button" class="icon-btn" data-modal-close aria-label="Закрыть"><?= icon('close') ?></button>
            </div>
            <form method="post" action="<?= e(url('/products/import')) ?>" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="modal__body">
                    <div class="stack">
                        <label class="file-drop">
                            <?= icon('upload') ?>
                            <strong>Выберите файл с товарами</strong>
                            <span>.xlsx или .csv, до 10 МБ</span>
                            <input type="file" name="file" accept=".xlsx,.csv,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
                        </label>
                        <label class="checkbox"><input type="checkbox" name="update_prices" value="1" checked> Обновлять цены у уже существующих товаров</label>
                        <div class="import-help">
                            <strong>Как оформить таблицу.</strong> Подойдут колонки:
                            <code>Наименование</code>, <code>Закупка</code>, <code>Продажа</code>,
                            <code>Остаток</code> (необязательно), <code>Категория</code>, <code>Артикул</code>,
                            <code>Ед.</code>, <code>Мин. остаток</code>.
                            Заголовок можно оставить в любой из первых 15 строк — программа найдёт его сама.
                            Совпадение товаров — по названию.
                        </div>
                        <a class="link" href="<?= e(url('/products/template')) ?>"><?= icon('download', 'icon icon--xs') ?> Скачать шаблон CSV</a>
                    </div>
                </div>
                <div class="modal__foot">
                    <button class="btn btn--ghost" type="button" data-modal-close>Отмена</button>
                    <button class="btn btn--primary" type="submit"><?= icon('upload') ?> Загрузить</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>
