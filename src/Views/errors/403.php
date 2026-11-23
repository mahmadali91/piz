<?php $inApp = true; ?>
<div class="error-page error-page--app">
    <div class="error-card">
        <div class="error-code">403</div>
        <h1>Недостаточно прав</h1>
        <p class="muted">Ваша роль не позволяет открыть этот раздел. Попросите администратора расширить права в разделе «Сотрудники».</p>
        <div class="error-actions">
            <a class="btn btn--primary" href="<?= e(url('/')) ?>">На дашборд</a>
            <a class="btn btn--ghost" href="<?= e(url('/pos')) ?>">В кассу</a>
        </div>
    </div>
</div>
