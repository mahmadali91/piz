# GitHub: репозиторий и деплой на Beget

Репозиторий: **https://github.com/mahmadali91/piz** (ветка `main`).

## 1. Что уже настроено

- Проект инициализирован как Git-репозиторий, первый коммит отправлен в `main`.
- `config/config.php`, `info.txt`, `uploads/`, `storage/` и локальные инструменты исключены через `.gitignore`.
- Добавлен CI (`.github/workflows/ci.yml`): проверка синтаксиса PHP 8.2/8.3 и защита от коммита секретов.

Проверить локально:

```bash
git remote -v
git log --oneline -5
git status
```

## 2. Как отправить изменения (со своего компьютера)

```bash
cd /путь/к/проекту
git add .
git commit -m "Кратко: что изменилось"
git push
```

Если Git просит пароль — используйте **Personal Access Token** (GitHub → Settings → Developer settings →
Personal access tokens → Fine-grained tokens, доступ к репозиторию `piz`, права Contents: Read and write)
либо SSH-ключ. Пароль от аккаунта GitHub для Git больше не подходит.

Проверить, что секреты не попали в индекс:

```bash
git status --short
git ls-files | grep -E "config\.php|info\.txt" || echo "секретов нет"
```

## 3. Деплой-ключ для Beget (если репозиторий станет приватным)

Сейчас репозиторий публичный, и `git clone` по HTTPS работает без ключей. Для приватного
репозитория или обновлений по SSH сделайте одноразовый ключ **на сервере Beget**:

```bash
ssh mahmadgv@mahmadgv.beget.tech

ssh-keygen -t ed25519 -C "piz-beget" -f ~/.ssh/github_piz -N ""
cat ~/.ssh/github_piz.pub
```

Скопируйте вывод (начинается с `ssh-ed25519 ...`).

В GitHub:

1. Откройте https://github.com/mahmadali91/piz/settings/keys.
2. **Add deploy key**.
3. Title: `Beget piz`.
4. Key: вставьте строку.
5. **Allow write access** — не включайте, серверу достаточно чтения.
6. Add key.

Настройте SSH на сервере:

```bash
cat >> ~/.ssh/config <<'EOF'
Host github.com
    HostName github.com
    User git
    IdentityFile ~/.ssh/github_piz
    IdentitiesOnly yes
EOF
chmod 600 ~/.ssh/config
ssh -T git@github.com
```

Ожидаемый ответ: `Hi mahmadali91/piz! You've successfully authenticated...`

Клонирование на сервере:

```bash
cd ~/piz.itop7.ru
mv public_html public_html.backup-$(date +%F) 2>/dev/null || true
git clone git@github.com:mahmadali91/piz.git public_html
```

Если проект уже склонирован по HTTPS:

```bash
cd ~/piz.itop7.ru/public_html
git remote set-url origin git@github.com:mahmadali91/piz.git
```

## 4. Обновление сайта

```bash
ssh mahmadgv@mahmadgv.beget.tech
cd ~/piz.itop7.ru/public_html
git pull --ff-only
```

- `config/config.php` не в Git — он останется на месте.
- Фото в `uploads/` не в Git — они не пропадут.
- Если в обновлении меняется схема БД, в описании коммита будет команда миграции. Текущая версия
  создаёт схему установщиком и дополнительных миграций не требует.

## 5. GitHub Actions

После каждого пуша в `main` запускается workflow **CI**:

- `php -l` для всех PHP-файлов на PHP 8.2 и 8.3;
- проверка, что в репозитории нет `config/config.php` и `info.txt`.

Статус: вкладка **Actions** в репозитории. Красный крестик означает синтаксическую ошибку —
сайт после такого пуша может не открыться, поэтому дождитесь зелёной галочки.

## 6. Что нельзя коммитить

| Файл | Почему |
|---|---|
| `config/config.php` | пароль базы данных |
| `info.txt` | доступы к БД |
| `uploads/*` | пользовательские фото, личные данные |
| `storage/logs/*` | журналы с ошибками и IP |
| `.tools/` | локальные инструменты разработки |

`.gitignore` уже это исключает. Если секрет случайно попал в коммит — смените пароль базы
в панели Beget и почистите историю (`git filter-repo`) либо пересоздайте репозиторий.

## 7. Полезные команды

```bash
git status
git diff
git log --oneline --graph -10
git pull --rebase
git push
```

Конфликты при `git pull` на сервере: не редактируйте файлы проекта на сервере вручную — все
изменения делайте локально и присылайте через Git. Единственное исключение — `config/config.php`,
который создаётся установщиком и не отслеживается.
