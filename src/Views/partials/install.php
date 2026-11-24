<div class="install-banner" id="install-banner" hidden>
    <span class="install-banner__mark">Д</span>
    <div class="install-banner__copy">
        <strong>Установить ДОНЕР</strong>
        <span data-install-hint>Ярлык на главном экране</span>
    </div>
    <button class="btn btn--primary btn--sm install-banner__btn" type="button" data-install-app>Установить</button>
    <button class="install-banner__close" type="button" data-install-dismiss aria-label="Скрыть предложение">
        <?= icon('close') ?>
    </button>
</div>

<div class="modal" id="install-help-modal" aria-hidden="true">
    <div class="modal__backdrop" data-modal-close></div>
    <div class="modal__panel" role="dialog" aria-modal="true" aria-labelledby="install-help-title">
        <div class="modal__head">
            <div>
                <h3 id="install-help-title">Установка на телефон</h3>
                <p>ДОНЕР будет открываться как обычное приложение</p>
            </div>
            <button type="button" class="icon-btn" data-modal-close aria-label="Закрыть"><?= icon('close') ?></button>
        </div>
        <div class="modal__body">
            <div class="install-steps">
                <div class="install-step">
                    <span class="install-step__num">1</span>
                    <div>
                        <strong>Android — Chrome</strong>
                        <p>Нажмите меню ⋮ в правом верхнем углу → «Установить приложение» или «Добавить на главный экран».</p>
                    </div>
                </div>
                <div class="install-step">
                    <span class="install-step__num">2</span>
                    <div>
                        <strong>iPhone — Safari</strong>
                        <p>Нажмите кнопку «Поделиться» (квадрат со стрелкой) → «На экран «Домой»» → «Добавить».</p>
                    </div>
                </div>
            </div>
            <div class="install-hint-box">
                <?= icon('sparkles') ?>
                <span>После установки ДОНЕР откроется без адресной строки — как отдельное приложение. Значок появится на главном экране.</span>
            </div>
        </div>
        <div class="modal__foot">
            <button class="btn btn--primary" type="button" data-modal-close>Понятно</button>
        </div>
    </div>
</div>
