# PIZZA / КИОСК ДОНЕР — краткий бриф

## Production

- Сайт: https://piz.itop7.ru (HTTPS, HTTP → HTTPS 301)
- Хостинг: Beget, PHP 8.2 (`php8.2` CLI), MySQL `mahmadgv_piz`
- Папка: `~/piz.itop7.ru/public_html`
- SSH: `ssh -i ./piz_deploy_key2 -o IdentitiesOnly=yes mahmadgv@mahmadgv.beget.tech`
- GitHub: https://github.com/mahmadali91/piz, ветка `main`
- Секреты: `info.txt`, `config/config.php`, `piz_deploy_key2` — не коммитить и не публиковать.

## Стек

PHP 8.1+ без Composer, PDO MySQL, vanilla JS/CSS, GD, ZipArchive/SimpleXML.
PWA: `manifest.webmanifest`, `sw.php` → `sw.js`, офлайн `offline.html`.

## Что уже есть

- Касса: наличные/карта/перевод, скидка, чек 80 мм, отрицательные остатки.
- Меню: **Товары и остаток**, **Приход**, **Расход**, **Продажи**, **Смена**.
- Товары и остаток: каталог, остаток, инвентаризация, движения склада,
  ползунок **«Массово»**, история цен в карточке.
- Приход: партии с себестоимостью + история приходов.
- Расход: несколько товаров одной операцией на сотрудника/брак, учитывается как расход.
- Цены: массово на процент/сумму/значение.
- Поставщики: накладные, закупочные цены, оплаты, долг.
- Инвентаризация: документ, расчёт/факт/расхождение, акт.
- PDF: чек, отчёт, смена, накладная, акт инвентаризации (кнопок печати нет).
- Боковое меню: сворачивается до иконок; ветка «Ещё» и меню администратора по клику.
- Вход: восстановление пароля по email; email задаётся в карточке сотрудника.
- Смены: открытие/закрытие, отчёт с остатком, CSV/PDF.
- Роли: администратор-суперпользователь, управляющий, кассир, свои права.
- Версия: файл `VERSION`, отображается в блоке пользователя и настройках.

## Ключевые файлы

- `src/Controllers/`: Products, Stock, Prices, Shift, Pos, Sale, Reports.
- `src/Models/`: Product, Receipt, Writeoff, Shift, Sale, User.
- `src/Views/products/index.php` — каталог, массовые цены, история цен.
- `src/Views/stock/in.php`, `out.php` — приход/расход.
- `src/Views/suppliers/` — поставщики, накладные, оплаты.
- `src/Views/inventory/` — инвентаризация и акт.
- `src/Services/PdfService.php` — PDF всех документов.
- `database/schema.sql`, `tools/migrate.php`.
- `tools/generate_product_art.py` — 35 иллюстраций из `data/products.csv`.
- `uploads/products/generated-<id>.jpg` — фото товаров (не в Git).
- `docs/`, `README.md`, `ИНСТРУКЦИЯ.md`, `ИСТОРИЯ.md`, `archive/`.

## Команды

```bash
php8.2 tools/migrate.php
php8.2 tools/maintenance.php stats
php8.2 tools/maintenance.php photos:generate --all
python3 tools/generate_product_art.py
git pull --ff-only
```

## Текущие данные (не перепроверять без причины)

35 товаров, 35 фото, 2 пользователя (`admin`, `kasir1`), реальные продажи.
Остатки меняются реальными продажами — не «исправлять» их без запроса.
Последняя версия: `1.24.26-2`.

## Документация

- `README.md` — обзор.
- `ИНСТРУКЦИЯ.md` — полная инструкция.
- `docs/USER_GUIDE.md` — продавец.
- `docs/DEPLOY_BEGET.md`, `docs/GITHUB.md` — инфраструктура.
- `docs/TOKEN_REPORT.md` — отчёт по токенам.
- `tools/seed_demo.php` — демо-данные за 3 месяца и очистка `--purge`.
- `archive/requests/ЗАПРОСЫ.md` — все запросы.

## Правила экономии

Подробно — в `AGENTS.md`. Кратко: не перечитывать историю, не снимать скриншоты
без изменения UI, не гонять полный smoke после каждой правки, не переписывать
документацию и версии на каждую мелочь.
