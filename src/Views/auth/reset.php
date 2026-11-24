<?php /** @var bool $valid */ /** @var string $token */ /** @var string|null $error */ ?>
<div class="auth-shell">
    <section class="auth-visual">
        <div class="auth-visual__orb auth-visual__orb--1"></div>
        <div class="auth-visual__orb auth-visual__orb--2"></div>
        <div class="auth-brand">
            <span class="brand__mark brand__mark--lg">Д</span>
            <div class="auth-brand__meta"><strong>ДОНЕР</strong><span>учёт продаж в киоске</span></div>
        </div>
        <div class="auth-copy">
            <span class="eyebrow">новый пароль</span>
            <h1>Безопасность<br>аккаунта</h1>
            <p>Придумайте новый пароль. После сохранения старый перестанет работать.</p>
            <ul class="auth-features">
                <li><?= icon('shield') ?><span>Минимум 8 символов и хотя бы одна буква</span></li>
                <li><?= icon('key') ?><span>Ссылка одноразовая</span></li>
            </ul>
        </div>
        <div class="auth-visual__foot"><span>© <?= date('Y') ?> ДОНЕР</span><span class="dot"></span><span>Разработчик Ali</span></div>
    </section>
    <section class="auth-form-side">
        <div class="auth-card">
            <div class="auth-card__logo"><span class="brand__mark">Д</span></div>
            <h2>Новый пароль</h2>
            <?php if (!$valid): ?>
                <div class="alert alert--error"><?= icon('alert') ?><span>Ссылка недействительна или истекла. Запросите новую.</span></div>
                <p class="auth-help"><a class="btn btn--primary btn--block" href="<?= e(url('/forgot-password')) ?>">Запросить новую ссылку</a></p>
            <?php else: ?>
                <p class="muted">Введите новый пароль для входа.</p>
                <?php if (!empty($error)): ?><div class="alert alert--error"><?= icon('alert') ?><span><?= e($error) ?></span></div><?php endif; ?>
                <form method="post" action="<?= e(url('/reset-password')) ?>" class="auth-form" autocomplete="off">
                    <?= csrf_field() ?>
                    <input type="hidden" name="token" value="<?= e($token) ?>">
                    <label class="field">
                        <span class="label">Новый пароль</span>
                        <span class="input-group">
                            <span class="input-group__icon"><?= icon('key') ?></span>
                            <input class="input input--password" type="password" name="password" minlength="8" required autofocus autocomplete="new-password">
                            <button type="button" class="input-group__action" data-password-toggle aria-label="Показать пароль"><?= icon('eye') ?></button>
                        </span>
                    </label>
                    <label class="field">
                        <span class="label">Повторите пароль</span>
                        <input class="input" type="password" name="password_confirm" minlength="8" required autocomplete="new-password">
                    </label>
                    <button class="btn btn--primary btn--lg btn--block" type="submit">Сохранить пароль <?= icon('check') ?></button>
                </form>
            <?php endif; ?>
        </div>
        <p class="auth-legal">ДОНЕР v<?= e(PIZ_VERSION) ?> · <?= e(date('Y')) ?></p>
    </section>
</div>
