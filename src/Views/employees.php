<?php
$roleChips = ['admin' => 'badge--red', 'manager' => 'badge--blue', 'cashier' => 'badge--green', 'custom' => 'badge--gray'];
?>
<div class="page">
    <div class="toolbar">
        <div class="muted" style="font-size: 13px; max-width: 620px;">
            Сотрудники входят под своими логинами. Права можно настроить точно: от «только касса» до полного доступа.
        </div>
        <button class="btn btn--primary" type="button" data-employee-new><?= icon('plus') ?> Добавить сотрудника</button>
    </div>

    <div class="user-grid">
        <?php foreach ($users as $item):
            $isSelf = (int) $item['id'] === (int) $currentUserId;
            $editData = [
                'id' => (int) $item['id'],
                'name' => $item['name'],
                'login' => $item['login'],
                'role' => $item['role'],
                'active' => (int) $item['active'] === 1,
                'permissions' => $item['permissions'],
            ];
            $permissionLabels = array_map(static fn ($permission) => \PIZ\Permissions::label($permission), $item['permissions']);
            ?>
            <article class="user-card <?= (int) $item['active'] === 1 ? '' : 'user-card--off' ?>">
                <div class="user-card__top">
                    <span class="avatar avatar--lg"><?= e(initials((string) $item['name'])) ?></span>
                    <div class="user-card__meta">
                        <strong><?= e($item['name']) ?></strong>
                        <span>@<?= e($item['login']) ?></span>
                    </div>
                    <?php if ($isSelf): ?>
                        <span class="badge badge--gray">это вы</span>
                    <?php endif; ?>
                </div>

                <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                    <span class="badge <?= e($roleChips[(string) $item['role']] ?? 'badge--gray') ?>"><?= e(role_label((string) $item['role'])) ?></span>
                    <?php if ((int) $item['active'] !== 1): ?>
                        <span class="badge badge--gray">доступ отключён</span>
                    <?php endif; ?>
                    <span class="muted" style="font-size: 11px; margin-left: auto;">
                        <?= $item['last_login_at'] ? 'вход ' . e(dt_human($item['last_login_at'])) : 'ещё не входил' ?>
                    </span>
                </div>

                <div class="user-card__permissions">
                    <?php if ($item['role'] === 'admin'): ?>
                        <span class="perm-tag">Все права</span>
                    <?php elseif ($permissionLabels === []): ?>
                        <span class="perm-tag">Нет прав</span>
                    <?php else: ?>
                        <?php foreach (array_slice($permissionLabels, 0, 4) as $label): ?>
                            <span class="perm-tag"><?= e($label) ?></span>
                        <?php endforeach; ?>
                        <?php if (count($permissionLabels) > 4): ?>
                            <span class="perm-tag">+<?= count($permissionLabels) - 4 ?></span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <div class="user-card__foot">
                    <form method="post" action="<?= e(url('/employees/toggle')) ?>" data-confirm="<?= (int) $item['active'] === 1 ? 'Отключить доступ сотруднику?' : 'Включить доступ сотруднику?' ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                        <button class="btn btn--ghost btn--sm" type="submit" <?= $isSelf ? 'disabled title="Нельзя отключить себя"' : '' ?>>
                            <?= (int) $item['active'] === 1 ? icon('logout') . ' Отключить' : icon('check') . ' Включить' ?>
                        </button>
                    </form>
                    <div class="user-card__actions">
                        <button class="icon-btn" type="button" title="Сбросить пароль" data-password-open="<?= (int) $item['id'] ?>" data-name="<?= e($item['name']) ?>"><?= icon('key') ?></button>
                        <button class="btn btn--ghost btn--sm" type="button"
                                data-employee-edit="<?= e(json_encode($editData, JSON_UNESCAPED_UNICODE)) ?>"><?= icon('edit') ?> Изменить</button>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</div>

<div class="modal" id="employee-modal" aria-hidden="true">
    <div class="modal__backdrop" data-modal-close></div>
    <div class="modal__panel modal__panel--wide" role="dialog" aria-modal="true" aria-labelledby="employee-modal-title">
        <div class="modal__head">
            <div>
                <h3 id="employee-modal-title" data-modal-title>Новый сотрудник</h3>
                <p>Логин, роль и точные права доступа</p>
            </div>
            <button type="button" class="icon-btn" data-modal-close aria-label="Закрыть"><?= icon('close') ?></button>
        </div>
        <form method="post" action="<?= e(url('/employees/save')) ?>" autocomplete="off">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="">
            <div class="modal__body">
                <div class="stack">
                    <div class="form-grid">
                        <label class="field">
                            <span class="label">Имя *</span>
                            <input class="input" type="text" name="name" maxlength="120" required>
                        </label>
                        <label class="field">
                            <span class="label">Логин *</span>
                            <input class="input" type="text" name="login" maxlength="32" pattern="[a-z0-9._\-]{3,32}" required placeholder="ali">
                        </label>
                        <label class="field">
                            <span class="label">Роль</span>
                            <select class="select" name="role">
                                <option value="admin">Администратор — всё</option>
                                <option value="manager">Управляющий — товары и отчёты</option>
                                <option value="cashier">Кассир — только касса</option>
                                <option value="custom">Свои права</option>
                            </select>
                        </label>
                        <label class="field">
                            <span class="label">Новый пароль</span>
                            <span class="input-group">
                                <input class="input input--password" type="password" name="password" minlength="8" autocomplete="new-password">
                                <button type="button" class="input-group__action" data-password-toggle aria-label="Показать пароль"><?= icon('eye') ?></button>
                            </span>
                            <span class="hint" data-password-hint>Минимум 8 символов, хотя бы одна буква.</span>
                        </label>
                        <label class="field">
                            <span class="label">Повторите пароль</span>
                            <input class="input" type="password" name="password_confirm" minlength="8" autocomplete="new-password">
                        </label>
                        <label class="field" style="justify-content: flex-end;">
                            <span class="label">Доступ</span>
                            <label class="switch"><input type="checkbox" name="active" value="1" checked> Активен</label>
                        </label>
                    </div>

                    <div class="form-section">
                        <div class="perm-groups">
                            <?php foreach ($permissionGroups as $group => $items): ?>
                                <div class="perm-group">
                                    <div class="perm-group__title"><?= e($group) ?></div>
                                    <div class="perm-group__items">
                                        <?php foreach ($items as $permission => $label): ?>
                                            <label class="checkbox">
                                                <input type="checkbox" name="permissions[]" value="<?= e($permission) ?>">
                                                <span><?= e($label) ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal__foot">
                <button class="btn btn--ghost" type="button" data-modal-close>Отмена</button>
                <button class="btn btn--primary" type="submit">Добавить сотрудника</button>
            </div>
        </form>
    </div>
</div>

<div class="modal" id="password-modal" aria-hidden="true">
    <div class="modal__backdrop" data-modal-close></div>
    <div class="modal__panel" role="dialog" aria-modal="true" aria-labelledby="password-modal-title">
        <div class="modal__head">
            <div>
                <h3 id="password-modal-title">Сбросить пароль</h3>
                <p>Сотрудник: <strong data-password-name></strong></p>
            </div>
            <button type="button" class="icon-btn" data-modal-close aria-label="Закрыть"><?= icon('close') ?></button>
        </div>
        <form method="post" action="<?= e(url('/employees/password')) ?>" autocomplete="off">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="">
            <div class="modal__body">
                <div class="stack">
                    <label class="field">
                        <span class="label">Новый пароль</span>
                        <span class="input-group">
                            <input class="input input--password" type="password" name="password" minlength="8" required>
                            <button type="button" class="input-group__action" data-password-toggle aria-label="Показать пароль"><?= icon('eye') ?></button>
                        </span>
                    </label>
                    <label class="field">
                        <span class="label">Повторите пароль</span>
                        <input class="input" type="password" name="password_confirm" minlength="8" required>
                    </label>
                </div>
            </div>
            <div class="modal__foot">
                <button class="btn btn--ghost" type="button" data-modal-close>Отмена</button>
                <button class="btn btn--primary" type="submit"><?= icon('key') ?> Сменить пароль</button>
            </div>
        </form>
    </div>
</div>

<script>
    window.PIZ_ROLE_PRESETS = <?= json_encode($rolePresets, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
