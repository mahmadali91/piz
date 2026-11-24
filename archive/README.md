# Архив проекта PIZZA / КИОСК ДОНЕР

Дата снимка: **24.11.2026**.

Всё, что относится к проекту, хранится **внутри папки `PIZZA`**. Ничего важного не вынесено в соседние папки или `/tmp`.

## Структура архива

```
PIZZA/
├── README.md                         обзор проекта
├── ИНСТРУКЦИЯ.md                     главная инструкция
├── ИСТОРИЯ.md                        все запросы и ответы
├── docs/                             подробные инструкции и скриншоты
├── dist/                             ZIP текущей версии
├── temp/                             временные файлы проекта
└── archive/
    ├── README.md                     этот файл
    ├── requests/
    │   ├── ЗАПРОСЫ.md                все запросы заказчика
    │   └── ИСТОРИЯ.md                снимок полной истории
    ├── instructions/
    │   ├── README.md
    │   ├── ИНСТРУКЦИЯ.md
    │   ├── USER_GUIDE.md
    │   ├── DEPLOY_BEGET.md
    │   └── GITHUB.md
    └── versions/
        ├── ВЕРСИИ.md                 описание версий и коммитов
        └── piz-full-history.bundle   полная история Git
```

## Где что искать

| Что нужно | Где лежит |
|---|---|
| Все запросы заказчика | `archive/requests/ЗАПРОСЫ.md` |
| Полная переписка и решения | `ИСТОРИЯ.md` и `archive/requests/ИСТОРИЯ.md` |
| Инструкция по работе | `ИНСТРУКЦИЯ.md` |
| Руководство продавца | `docs/USER_GUIDE.md` |
| Установка на Beget | `docs/DEPLOY_BEGET.md` |
| GitHub и деплой | `docs/GITHUB.md` |
| Скриншоты | `docs/screenshots/` |
| Текущая сборка | `dist/piz-kiosk-1.0.0.zip` |
| Все версии Git | `archive/versions/piz-full-history.bundle` |
| Временные файлы | `temp/` |

## Версии

Полная история Git сохранена в `archive/versions/piz-full-history.bundle`.
Это один файл, из которого можно восстановить любую версию:

```bash
git clone archive/versions/piz-full-history.bundle PIZZA-restored
cd PIZZA-restored
git log --oneline --decorate
git checkout <коммит-или-тег>
```

Теги версий:

- `v1.0.0-pwa` — мобильная PWA-версия;
- `v1.0.0-doner` — версия с приходом, расходом, остатками, ценами и сменами.

Подробности — в `archive/versions/ВЕРСИИ.md`.

## Важно

- `info.txt`, `config/config.php` и приватный SSH-ключ содержат секреты и **не входят в Git**.
- `uploads/` с фотографиями товаров хранится на сервере; в Git попадает только структура.
- Временные файлы и тестовые скрипты создаются только в `temp/` и не должны попадать в Git.
