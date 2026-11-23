<?php /** @var array|null $seed */ ?>
<div class="setup-wrap">
    <div class="setup-card">
        <div class="setup-brand">
            <span class="brand__mark">P</span>
            <div>
                <strong>PIZ</strong>
                <span>первый запуск</span>
            </div>
        </div>

        <ol class="steps">
            <li class="steps__item is-done"><span><?= icon('check') ?></span><em>База данных</em></li>
            <li class="steps__item is-active"><span>2</span><em>Администратор</em></li>
            <li class="steps__item"><span>3</span><em>Готово</em></li>
        </ol>

        <h1>Создайте администратора</h1>
        <p class="muted">Это основной аккаунт владельца. Позже вы сможете добавлять сотрудников с ограниченными правами.</p>

        <?php if (!empty($seed)): ?>
            <div class="alert alert--success">
                <?= icon('check') ?>
                <span>Каталог загружен: <strong><?= (int) $seed['added'] ?></strong> <?= plural((int) $seed['added'], 'товар', 'товара', 'товаров') ?>. Остатки начинаются с нуля — оформите приход в разделе «Товары».</span>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert--error"><?= icon('alert') ?><span><?= e(implode(' ', $errors)) ?></span></div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('/setup/admin')) ?>" class="stack" autocomplete="off">
            <?= csrf_field() ?>
            <div class="form-grid form-grid--2">
                <label class="field">
                    <span class="label">Ваше имя</span>
                    <input class="input" type="text" name="name" value="<?= e($old['name'] ?? '') ?>" placeholder="Али" required autofocus>
                </label>
                <label class="field">
                    <span class="label">Название киоска</span>
                    <input class="input" type="text" name="store_name" value="<?= e($old['store_name'] ?? 'Киоск PIZ') ?>" maxlength="80">
                </label>
            </div>
            <label class="field">
                <span class="label">Логин для входа</span>
                <input class="input" type="text" name="login" value="<?= e($old['login'] ?? 'admin') ?>" pattern="[a-z0-9._\-]{3,32}" required>
                <span class="hint">Латиница, цифры, точка, дефис или подчёркивание. Минимум 3 символа.</span>
            </label>
            <div class="form-grid form-grid--2">
                <label class="field">
                    <span class="label">Пароль</span>
                    <span class="input-group">
                        <input class="input input--password" type="password" name="password" minlength="8" required autocomplete="new-password">
                        <button type="button" class="input-group__action" data-password-toggle aria-label="Показать пароль"><?= icon('eye') ?></button>
                    </span>
                    <span class="hint">Минимум 8 символов, хотя бы одна буква.</span>
                </label>
                <label class="field">
                    <span class="label">Повторите пароль</span>
                    <input class="input" type="password" name="password_confirm" minlength="8" required autocomplete="new-password">
                </label>
            </div>
            <div class="setup-actions">
                <button class="btn btn--primary btn--lg" type="submit">Создать аккаунт и войти <?= icon('arrow-right') ?></button>
            </div>
        </form>

        <p class="setup-note"><?= icon('shield') ?> Пароль хранится только в виде необратимого хеша. Никто, включая разработчика, не сможет его прочитать.</p>
    </div>
</div>
