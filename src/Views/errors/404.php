<?php $inApp = !empty($showLayout); ?>
<div class="error-page <?= $inApp ? 'error-page--app' : '' ?>">
    <div class="error-card">
        <div class="error-code">404</div>
        <h1>Страница не найдена</h1>
        <p class="muted">Возможно, ссылка устарела или страница была удалена.</p>
        <div class="error-actions">
            <a class="btn btn--primary" href="<?= e(url('/')) ?>">На дашборд</a>
            <?php if (!$inApp): ?>
                <a class="btn btn--ghost" href="<?= e(url('/login')) ?>">Войти</a>
            <?php endif; ?>
        </div>
    </div>
</div>
