<?php /** @var string|null $error */ ?>
<div class="auth-shell">
    <section class="auth-visual">
        <div class="auth-visual__orb auth-visual__orb--1"></div>
        <div class="auth-visual__orb auth-visual__orb--2"></div>
        <div class="auth-brand">
            <span class="brand__mark brand__mark--lg">P</span>
            <div class="auth-brand__meta">
                <strong>PIZ</strong>
                <span>учёт продаж в киоске</span>
            </div>
        </div>

        <div class="auth-copy">
            <span class="eyebrow">выручка · прибыль · остатки</span>
            <h1>Каждая продажа<br>под контролем</h1>
            <p>Оформляйте продажи за секунды, следите за остатками и видьте реальную прибыль — без тетрадей и таблиц.</p>
            <ul class="auth-features">
                <li><?= icon('check') ?><span>Дашборд с выручкой и прибылью в реальном времени</span></li>
                <li><?= icon('check') ?><span>Оплата наличными, картой или переводом</span></li>
                <li><?= icon('check') ?><span>Сотрудники с ограниченными правами</span></li>
            </ul>
        </div>

        <div class="auth-visual__foot">
            <span>© <?= date('Y') ?> PIZ</span>
            <span class="dot"></span>
            <span>Сделано для ежедневной работы</span>
        </div>
    </section>

    <section class="auth-form-side">
        <div class="auth-card">
            <div class="auth-card__logo">
                <span class="brand__mark">P</span>
            </div>
            <h2>Вход в кабинет</h2>
            <p class="muted">Введите логин и пароль, чтобы продолжить работу.</p>

            <?php if (!empty($error)): ?>
                <div class="alert alert--error"><?= icon('alert') ?><span><?= e($error) ?></span></div>
            <?php elseif (!empty($logout)): ?>
                <div class="alert alert--success"><?= icon('check') ?><span>Вы вышли из системы. До встречи!</span></div>
            <?php elseif (!empty($expired)): ?>
                <div class="alert alert--warn"><?= icon('clock') ?><span>Сессия истекла. Войдите снова.</span></div>
            <?php endif; ?>

            <form method="post" action="<?= e(url('/login')) ?>" class="auth-form" autocomplete="on">
                <?= csrf_field() ?>
                <label class="field">
                    <span class="label">Логин</span>
                    <span class="input-group">
                        <span class="input-group__icon"><?= icon('user') ?></span>
                        <input class="input" type="text" name="login" value="<?= e($oldLogin ?? '') ?>" placeholder="admin" required autofocus autocomplete="username">
                    </span>
                </label>
                <label class="field">
                    <span class="label">Пароль</span>
                    <span class="input-group">
                        <span class="input-group__icon"><?= icon('key') ?></span>
                        <input class="input input--password" type="password" name="password" placeholder="••••••••" required autocomplete="current-password">
                        <button type="button" class="input-group__action" data-password-toggle aria-label="Показать пароль"><?= icon('eye') ?></button>
                    </span>
                </label>
                <button class="btn btn--primary btn--lg btn--block" type="submit">Войти <?= icon('arrow-right') ?></button>
            </form>

            <p class="auth-help">Нет доступа? Обратитесь к администратору киоска — он создаст аккаунт и выдаст права.</p>
        </div>
        <p class="auth-legal">PIZ v<?= e(PIZ_VERSION) ?> · <?= e(date('Y')) ?></p>
    </section>
</div>
