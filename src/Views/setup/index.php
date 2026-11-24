<?php /** @var array $requirements */ ?>
<div class="setup-wrap">
    <div class="setup-card">
        <div class="setup-brand">
            <span class="brand__mark">Д</span>
            <div>
                <strong>ДОНЕР</strong>
                <span>первый запуск</span>
            </div>
        </div>

        <ol class="steps">
            <li class="steps__item is-active"><span>1</span><em>База данных</em></li>
            <li class="steps__item"><span>2</span><em>Администратор</em></li>
            <li class="steps__item"><span>3</span><em>Готово</em></li>
        </ol>

        <h1>Подключение к базе данных</h1>
        <p class="muted">Данные доступа к MySQL/MariaDB — в панели Beget или в файле <code>info.txt</code>, который вам выдали. Пароль базы не сохраняется в Git.</p>

        <?php if (!empty($manualConfig)): ?>
            <div class="alert alert--warn"><?= icon('alert') ?><span>Автоматически записать файл не удалось. Создайте <code>config/config.php</code> на хостинге и вставьте в него этот код:</span></div>
            <textarea class="code-area" rows="12" readonly><?= e($manualConfig) ?></textarea>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert--error"><?= icon('alert') ?><span><?= e(implode(' ', $errors)) ?></span></div>
        <?php elseif (!empty($error)): ?>
            <div class="alert alert--error"><?= icon('alert') ?><span><?= e($error) ?></span></div>
        <?php endif; ?>

        <div class="req-box">
            <div class="req-box__head">
                <strong>Требования сервера</strong>
                <span class="badge <?= $requirementsOk ? 'badge--green' : 'badge--red' ?>"><?= $requirementsOk ? 'Всё в порядке' : 'Есть проблемы' ?></span>
            </div>
            <ul class="req-list">
                <?php foreach ($requirements as $check): ?>
                    <li class="<?= $check['ok'] ? 'is-ok' : (empty($check['optional']) ? 'is-fail' : 'is-warn') ?>">
                        <?= $check['ok'] ? icon('check') : icon('alert') ?>
                        <span><?= e($check['label']) ?></span>
                        <?php if (!$check['ok']): ?><em><?= e($check['hint']) ?></em><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <form method="post" action="<?= e(url('/setup/db')) ?>" class="stack">
            <?= csrf_field() ?>
            <div class="form-grid form-grid--2">
                <label class="field">
                    <span class="label">Хост</span>
                    <input class="input" type="text" name="db_host" value="<?= e($old['host'] ?? 'localhost') ?>" required>
                </label>
                <label class="field">
                    <span class="label">Порт</span>
                    <input class="input" type="text" name="db_port" value="<?= e($old['port'] ?? '3306') ?>" inputmode="numeric">
                </label>
            </div>
            <label class="field">
                <span class="label">Имя базы данных</span>
                <input class="input" type="text" name="db_name" value="<?= e($old['name'] ?? '') ?>" placeholder="mahmadgv_piz" required>
            </label>
            <div class="form-grid form-grid--2">
                <label class="field">
                    <span class="label">Пользователь</span>
                    <input class="input" type="text" name="db_user" value="<?= e($old['user'] ?? '') ?>" placeholder="mahmadgv_piz" required>
                </label>
                <label class="field">
                    <span class="label">Пароль</span>
                    <input class="input" type="password" name="db_pass" value="" autocomplete="new-password">
                </label>
            </div>
            <div class="setup-actions">
                <button class="btn btn--primary btn--lg" type="submit">Проверить и продолжить <?= icon('arrow-right') ?></button>
            </div>
        </form>

        <details class="setup-help">
            <summary>Где взять данные базы?</summary>
            <p>В панели Beget откройте <strong>MySQL</strong> → нужная база. Хост — <code>localhost</code>, имя базы и пользователь обычно совпадают, пароль указан рядом. Эти же данные лежат в файле <code>info.txt</code> в комплекте проекта (не публикуйте его).</p>
        </details>
    </div>
</div>
