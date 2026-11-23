<?php $inApp = false; ?>
<div class="error-page">
    <div class="error-card">
        <div class="error-code">419</div>
        <h1>Сессия истекла</h1>
        <p class="muted">Из соображений безопасности форма устарела. Обновите страницу и попробуйте снова.</p>
        <div class="error-actions">
            <a class="btn btn--primary" href="javascript:location.reload()">Обновить страницу</a>
            <a class="btn btn--ghost" href="<?= e(url('/login')) ?>">Войти заново</a>
        </div>
    </div>
</div>
