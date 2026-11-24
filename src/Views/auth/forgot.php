<?php /** @var bool $sent */ ?>
<div class="auth-shell">
    <section class="auth-visual">
        <div class="auth-visual__orb auth-visual__orb--1"></div>
        <div class="auth-visual__orb auth-visual__orb--2"></div>
        <div class="auth-brand">
            <span class="brand__mark brand__mark--lg">Д</span>
            <div class="auth-brand__meta"><strong>ДОНЕР</strong><span>учёт продаж в киоске</span></div>
        </div>
        <div class="auth-copy">
            <span class="eyebrow">доступ · безопасность</span>
            <h1>Восстановление<br>доступа</h1>
            <p>Укажите email, который привязан к аккаунту. Мы отправим ссылку для создания нового пароля.</p>
            <ul class="auth-features">
                <li><?= icon('shield') ?><span>Ссылка действует 60 минут и используется один раз</span></li>
                <li><?= icon('key') ?><span>Пароль хранится только в виде необратимого хеша</span></li>
            </ul>
        </div>
        <div class="auth-visual__foot"><span>© <?= date('Y') ?> ДОНЕР</span><span class="dot"></span><span>Разработчик Ali</span></div>
    </section>
    <section class="auth-form-side">
        <div class="auth-card">
            <div class="auth-card__logo"><span class="brand__mark">Д</span></div>
            <h2>Забыли пароль?</h2>
            <p class="muted">Введите логин или email — отправим ссылку для сброса.</p>
            <?php if ($sent): ?>
                <div class="alert alert--success"><?= icon('mail') ?><span>Письмо отправлено. Проверьте почту и папку «Спам».</span></div>
            <?php endif; ?>
            <form method="post" action="<?= e(url('/forgot-password')) ?>" class="auth-form" autocomplete="on">
                <?= csrf_field() ?>
                <label class="field">
                    <span class="label">Логин или email</span>
                    <span class="input-group">
                        <span class="input-group__icon"><?= icon('user') ?></span>
                        <input class="input" type="text" name="identity" required autofocus autocomplete="username" placeholder="admin или mail@example.com">
                    </span>
                </label>
                <button class="btn btn--primary btn--lg btn--block" type="submit">Отправить ссылку <?= icon('arrow-right') ?></button>
            </form>
            <p class="auth-help"><a class="link" href="<?= e(url('/login')) ?>">← Вернуться ко входу</a></p>
        </div>
        <p class="auth-legal">ДОНЕР v<?= e(PIZ_VERSION) ?> · <?= e(date('Y')) ?></p>
    </section>
</div>
