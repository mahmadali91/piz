<?php
/** @var array $user */
$shot = static fn (string $name): string => asset('assets/knowledge/' . $name);
$sections = [
    ['dashboard', 'Дашборд и отчёты', 'Сводка дня, период, график и таблица'],
    ['pos', 'Касса', 'Продажа, оплата и чек'],
    ['products', 'Товары и остатки', 'Каталог, остатки и движение'],
    ['stock-in', 'Приход', 'Поступление и себестоимость'],
    ['stock-out', 'Расход', 'Списание и брак'],
    ['sales', 'Продажи', 'История и отмена'],
    ['shifts', 'Смены', 'Открытие, закрытие и отчёт'],
    ['suppliers', 'Поставщики', 'Накладные и долги'],
    ['inventory', 'Инвентаризация', 'Факт, расчёт и акт'],
    ['prices', 'Цены', 'Массовое изменение'],
    ['employees', 'Сотрудники', 'Роли и доступы'],
    ['settings', 'Настройки', 'Магазин и безопасность'],
    ['activity', 'Журнал', 'Кто и что изменил'],
    ['mobile', 'Телефон', 'Касса и дашборд на ходу'],
];
?>
<div class="page knowledge">
    <header class="knowledge-hero">
        <div>
            <span class="knowledge-eyebrow">Управление · инструкция</span>
            <h2>База знаний</h2>
            <p>Короткие инструкции по всем разделам «КИОСК ДОНЕР»: от первой смены до инвентаризации, поставщиков и отчётов.</p>
        </div>
        <div class="knowledge-hero__actions">
            <a class="btn btn--primary" href="<?= e(url('/pos')) ?>"><?= icon('cart') ?> Открыть кассу</a>
            <a class="btn btn--ghost" href="#quick-start"><?= icon('list') ?> Быстрый старт</a>
        </div>
    </header>

    <nav class="knowledge-toc" aria-label="Разделы базы знаний">
        <?php foreach ($sections as $index => [$id, $title, $description]): ?>
            <a class="knowledge-toc__item" href="#<?= e($id) ?>">
                <span class="knowledge-toc__num"><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
                <span><strong><?= e($title) ?></strong><small><?= e($description) ?></small></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <section class="knowledge-quick" id="quick-start">
        <div class="knowledge-quick__head">
            <span class="knowledge-eyebrow">С чего начать</span>
            <h3>Первая смена за 4 шага</h3>
        </div>
        <div class="knowledge-quick__steps">
            <div><span>01</span><strong>Откройте смену</strong><p>Дашборд → «Смена» или «Открыть смену». Укажите кассира и начальную кассу.</p></div>
            <div><span>02</span><strong>Добавьте товары</strong><p>Касса → нажмите на карточку товара. Количество меняется кнопками «+ / −».</p></div>
            <div><span>03</span><strong>Примите оплату</strong><p>Выберите наличные, карту или перевод. Нажмите «Оформить» и отдайте чек.</p></div>
            <div><span>04</span><strong>Закройте смену</strong><p>Смена → «Закрыть смену». Система покажет выручку, остаток и расхождения.</p></div>
        </div>
    </section>

    <section class="knowledge-section" id="dashboard">
        <div class="knowledge-section__text">
            <span class="knowledge-num">01</span>
            <h3>Дашборд и отчёты</h3>
            <p>Главный экран показывает выручку, прибыль, маржинальность, продажи, средний чек, расходы и скидки за выбранный период.</p>
            <ul>
                <li>Периоды: <b>Сегодня, 7 дней, Месяц, 3 месяца, 6 месяцев</b> и свои даты.</li>
                <li>На телефоне даты открываются кнопкой с календарём; «6 месяцев» скрыто.</li>
                <li>Динамику можно смотреть как <b>график, линии, таблицу или список</b>. Вид запоминается.</li>
                <li><b>Дни / Товары / Склад / PDF</b> — выгрузки. На телефоне они в «Ещё».</li>
            </ul>
            <p class="knowledge-tip">Нет продаж в предыдущем периоде — сравнение покажет <b>+100%</b>.</p>
        </div>
        <figure class="knowledge-figure">
            <a href="<?= e($shot('dashboard.jpg')) ?>" target="_blank" rel="noopener"><img src="<?= e($shot('dashboard.jpg')) ?>" alt="Дашборд с графиком и показателями" loading="lazy"></a>
            <figcaption>Дашборд: показатели, фильтры и динамика по дням.</figcaption>
        </figure>
    </section>

    <section class="knowledge-section knowledge-section--reverse" id="pos">
        <div class="knowledge-section__text">
            <span class="knowledge-num">02</span>
            <h3>Касса</h3>
            <p>Рабочее место продавца. Слева каталог, справа чек. Нажмите на товар, чтобы добавить его.</p>
            <ul>
                <li>Количество меняется кнопками <b>«+ / −»</b>; корзина удаляет позицию.</li>
                <li>В чеке видно <b>цену за единицу × количество</b> и сумму позиции.</li>
                <li>Оплата: наличные, карта, перевод; можно указать полученную сумму и сдачу.</li>
                <li>Скидка в рублях; после оформления чек можно сохранить в PDF.</li>
                <li>На телефоне каталог можно показать <b>по 2, 3 или 4 товара в ряд</b>.</li>
            </ul>
        </div>
        <figure class="knowledge-figure">
            <a href="<?= e($shot('pos.jpg')) ?>" target="_blank" rel="noopener"><img src="<?= e($shot('pos.jpg')) ?>" alt="Рабочее место кассы" loading="lazy"></a>
            <figcaption>Касса: каталог, чек и оплата.</figcaption>
        </figure>
    </section>

    <section class="knowledge-section" id="products">
        <div class="knowledge-section__text">
            <span class="knowledge-num">03</span>
            <h3>Товары и остатки</h3>
            <p>Каталог товаров с фото, артикулом, категорией, ценой и остатком. Поиск работает по названию и артикулу.</p>
            <ul>
                <li>Фильтры: категория, низкий остаток, архив.</li>
                <li>Карточка товара: цена, себестоимость, минимальный остаток, фото.</li>
                <li>История цен хранится в карточке товара.</li>
                <li>Импорт и шаблон CSV — в меню товаров.</li>
            </ul>
        </div>
        <figure class="knowledge-figure">
            <a href="<?= e($shot('products.jpg')) ?>" target="_blank" rel="noopener"><img src="<?= e($shot('products.jpg')) ?>" alt="Каталог товаров и остатков" loading="lazy"></a>
            <figcaption>Товары: остатки, цены и действия.</figcaption>
        </figure>
    </section>

    <section class="knowledge-section knowledge-section--reverse" id="stock-in">
        <div class="knowledge-section__text">
            <span class="knowledge-num">04</span>
            <h3>Приход</h3>
            <p>Оформляйте поступление товара от поставщика.</p>
            <ul>
                <li>Выберите товары и количество.</li>
                <li>Укажите закупочную цену и поставщика.</li>
                <li>После сохранения остаток увеличится, появится партия в истории приходов.</li>
            </ul>
        </div>
        <figure class="knowledge-figure">
            <a href="<?= e($shot('stock-in.jpg')) ?>" target="_blank" rel="noopener"><img src="<?= e($shot('stock-in.jpg')) ?>" alt="Оформление прихода" loading="lazy"></a>
            <figcaption>Приход: товары, количество и закупочная цена.</figcaption>
        </figure>
    </section>

    <section class="knowledge-section" id="stock-out">
        <div class="knowledge-section__text">
            <span class="knowledge-num">05</span>
            <h3>Расход</h3>
            <p>Списание товара на сотрудника, брак или другие расходы.</p>
            <ul>
                <li>Добавьте несколько товаров в одну операцию.</li>
                <li>Укажите сотрудника и причину.</li>
                <li>Списание уменьшает остаток и учитывается в расходах периода.</li>
            </ul>
        </div>
        <figure class="knowledge-figure">
            <a href="<?= e($shot('stock-out.jpg')) ?>" target="_blank" rel="noopener"><img src="<?= e($shot('stock-out.jpg')) ?>" alt="Оформление расхода" loading="lazy"></a>
            <figcaption>Расход: списание на сотрудника или брак.</figcaption>
        </figure>
    </section>

    <section class="knowledge-section knowledge-section--reverse" id="sales">
        <div class="knowledge-section__text">
            <span class="knowledge-num">06</span>
            <h3>Продажи</h3>
            <p>История всех чеков с фильтрами по периоду, оплате, статусу и поиску.</p>
            <ul>
                <li>Откройте продажу, чтобы увидеть состав и суммы.</li>
                <li>Право <b>«Отмена продаж»</b> позволяет отменить чек и вернуть товар на склад.</li>
                <li>PDF чека доступен из карточки продажи.</li>
            </ul>
        </div>
        <figure class="knowledge-figure">
            <a href="<?= e($shot('sales.jpg')) ?>" target="_blank" rel="noopener"><img src="<?= e($shot('sales.jpg')) ?>" alt="История продаж" loading="lazy"></a>
            <figcaption>Продажи: фильтры и список чеков.</figcaption>
        </figure>
    </section>

    <section class="knowledge-section" id="shifts">
        <div class="knowledge-section__text">
            <span class="knowledge-num">07</span>
            <h3>Смены</h3>
            <p>Смена фиксирует кассира, открытие и закрытие, выручку и остатки.</p>
            <ul>
                <li>Открытие: кассир и начальная касса.</li>
                <li>Закрытие: фактическая касса и комментарий.</li>
                <li>Отчёт смены: продажи, оплаты, списания, остаток и расхождения.</li>
                <li>PDF и CSV отчёта — без кнопок печати.</li>
            </ul>
        </div>
        <figure class="knowledge-figure">
            <a href="<?= e($shot('shifts.jpg')) ?>" target="_blank" rel="noopener"><img src="<?= e($shot('shifts.jpg')) ?>" alt="История смен" loading="lazy"></a>
            <figcaption>Смены: история и отчёты.</figcaption>
        </figure>
    </section>

    <section class="knowledge-section knowledge-section--reverse" id="suppliers">
        <div class="knowledge-section__text">
            <span class="knowledge-num">08</span>
            <h3>Поставщики и накладные</h3>
            <p>Справочник поставщиков, закупочные накладные, оплаты и долг.</p>
            <ul>
                <li>Создайте поставщика и накладную с товарами и закупочными ценами.</li>
                <li>Оплаты уменьшают долг; история видна в карточке.</li>
                <li>PDF накладной доступен из документа.</li>
            </ul>
        </div>
        <figure class="knowledge-figure">
            <a href="<?= e($shot('suppliers.jpg')) ?>" target="_blank" rel="noopener"><img src="<?= e($shot('suppliers.jpg')) ?>" alt="Поставщики и накладные" loading="lazy"></a>
            <figcaption>Поставщики: накладные, оплаты и долги.</figcaption>
        </figure>
    </section>

    <section class="knowledge-section" id="inventory">
        <div class="knowledge-section__text">
            <span class="knowledge-num">09</span>
            <h3>Инвентаризация</h3>
            <p>Сверка расчётного и фактического остатка.</p>
            <ul>
                <li>Создайте документ — система подставит текущий остаток.</li>
                <li>Внесите фактические количества; разница считается автоматически.</li>
                <li>После завершения остатки обновятся, акт можно скачать в PDF.</li>
            </ul>
        </div>
        <figure class="knowledge-figure">
            <a href="<?= e($shot('inventory.jpg')) ?>" target="_blank" rel="noopener"><img src="<?= e($shot('inventory.jpg')) ?>" alt="Инвентаризация" loading="lazy"></a>
            <figcaption>Инвентаризация: расчёт, факт и расхождение.</figcaption>
        </figure>
    </section>

    <section class="knowledge-section knowledge-section--reverse" id="prices">
        <div class="knowledge-section__text">
            <span class="knowledge-num">10</span>
            <h3>Цены</h3>
            <p>Массовое изменение цен на процент, сумму или конкретное значение.</p>
            <ul>
                <li>Выберите товары или категорию.</li>
                <li>Проверьте предпросмотр новых цен.</li>
                <li>После сохранения изменения попадут в историю цен.</li>
            </ul>
        </div>
        <figure class="knowledge-figure">
            <a href="<?= e($shot('prices.jpg')) ?>" target="_blank" rel="noopener"><img src="<?= e($shot('prices.jpg')) ?>" alt="Массовое изменение цен" loading="lazy"></a>
            <figcaption>Цены: массовое изменение и предпросмотр.</figcaption>
        </figure>
    </section>

    <section class="knowledge-section" id="employees">
        <div class="knowledge-section__text">
            <span class="knowledge-num">11</span>
            <h3>Сотрудники и права</h3>
            <p>Пользователи, роли и точечные права доступа.</p>
            <ul>
                <li>Роли: администратор, управляющий, кассир, свои права.</li>
                <li>Можно выдать отдельные права: продажи, склад, отчёты, поставщики.</li>
                <li>Пароль сотрудника можно сбросить в его карточке.</li>
            </ul>
        </div>
        <figure class="knowledge-figure">
            <a href="<?= e($shot('employees.jpg')) ?>" target="_blank" rel="noopener"><img src="<?= e($shot('employees.jpg')) ?>" alt="Сотрудники и права" loading="lazy"></a>
            <figcaption>Сотрудники: роли и доступы.</figcaption>
        </figure>
    </section>

    <section class="knowledge-section knowledge-section--reverse" id="settings">
        <div class="knowledge-section__text">
            <span class="knowledge-num">12</span>
            <h3>Настройки</h3>
            <p>Название киоска, контакты, валюта, тексты чека и минимальный остаток.</p>
            <ul>
                <li>Менять настройки может только право <b>«Настройки»</b>.</li>
                <li>Смена собственного пароля — в этом же разделе.</li>
                <li>Системная информация доступна только администратору.</li>
            </ul>
        </div>
        <figure class="knowledge-figure">
            <a href="<?= e($shot('settings.jpg')) ?>" target="_blank" rel="noopener"><img src="<?= e($shot('settings.jpg')) ?>" alt="Настройки" loading="lazy"></a>
            <figcaption>Настройки: магазин, чек и безопасность.</figcaption>
        </figure>
    </section>

    <section class="knowledge-section" id="activity">
        <div class="knowledge-section__text">
            <span class="knowledge-num">13</span>
            <h3>Журнал</h3>
            <p>История действий: входы, продажи, изменения товаров, настроек и прав.</p>
            <ul>
                <li>Фильтр по периоду, сотруднику и типу действия.</li>
                <li>Помогает найти, кто изменил остаток или цену.</li>
            </ul>
        </div>
        <figure class="knowledge-figure">
            <a href="<?= e($shot('activity.jpg')) ?>" target="_blank" rel="noopener"><img src="<?= e($shot('activity.jpg')) ?>" alt="Журнал действий" loading="lazy"></a>
            <figcaption>Журнал: кто и что изменил.</figcaption>
        </figure>
    </section>

    <section class="knowledge-section knowledge-section--reverse" id="mobile">
        <div class="knowledge-section__text">
            <span class="knowledge-num">14</span>
            <h3>Телефон</h3>
            <p>Сервис работает как PWA: можно добавить на главный экран.</p>
            <ul>
                <li>В кассе на телефоне — вкладки «Каталог» и «Чек».</li>
                <li>Сетка товаров: 2, 3 или 4 в ряд; по умолчанию 2.</li>
                <li>На дашборде — компактные действия, фильтры в строку и даты в модальном окне.</li>
                <li>Скачивание отчётов на телефоне — в кнопке «Ещё».</li>
            </ul>
            <p class="knowledge-tip">В Safari: «Поделиться» → «На экран “Домой”». В Chrome: меню → «Установить приложение».</p>
        </div>
        <figure class="knowledge-figure">
            <a href="<?= e($shot('pos-mobile.jpg')) ?>" target="_blank" rel="noopener"><img src="<?= e($shot('pos-mobile.jpg')) ?>" alt="Касса на телефоне" loading="lazy"></a>
            <figcaption>Касса на телефоне: 2 товара в ряд и вкладка «Чек».</figcaption>
        </figure>
    </section>

    <section class="knowledge-help">
        <div>
            <span class="knowledge-eyebrow">Если что-то не работает</span>
            <h3>Частые вопросы</h3>
        </div>
        <div class="knowledge-faq">
            <details><summary>Не находится товар</summary><p>Проверьте поиск по названию или артикулу, категорию и фильтр «Архив». Возможно, товар архивирован.</p></details>
            <details><summary>Не открывается смена</summary><p>Смена уже открыта или нет права «Смены». Откройте дашборд — статус смены виден в кнопке.</p></details>
            <details><summary>Не печатается PDF</summary><p>PDF скачивается файлом. В браузере нажмите «Открыть» или «Скачать»; встроенной кнопки печати в сервисе нет.</p></details>
            <details><summary>Забыли пароль</summary><p>На экране входа — «Забыли пароль?». Нужен email, указанный в карточке сотрудника.</p></details>
            <details><summary>Нет доступа к разделу</summary><p>Обратитесь к администратору: права настраиваются в «Сотрудники» → карточка сотрудника.</p></details>
        </div>
    </section>

    <div class="knowledge-footer">
        <span>КИОСК ДОНЕР · база знаний</span>
        <span>Версия v<?= e(PIZ_VERSION) ?></span>
    </div>
</div>
