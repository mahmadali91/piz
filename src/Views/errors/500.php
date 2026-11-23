<?php /** @var string|null $message */ ?>
<div class="error-page <?= !empty($showLayout) ? 'error-page--app' : '' ?>">
    <div class="error-card">
        <div class="error-code">500</div>
        <h1>Что-то пошло не так</h1>
        <p class="muted">Мы записали ошибку в журнал. Обновите страницу или попробуйте позже.</p>
        <?php if (!empty($message)): ?>
            <pre class="code-area"><?= e($message) ?></pre>
        <?php endif; ?>
        <div class="error-actions">
            <a class="btn btn--primary" href="javascript:location.reload()">Повторить</a>
            <a class="btn btn--ghost" href="<?= e(url('/')) ?>">На главную</a>
        </div>
    </div>
</div>
