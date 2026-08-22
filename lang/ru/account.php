<?php

// App-owned auth/account copy (login, register, password reset). Not in
// auth.php/passwords.php/validation.php — those are managed by
// laravel-lang/common and get overwritten by `composer post-update-cmd` →
// `artisan lang:update`. Not in ui.php either — that's reserved for
// <x-ui.*> component-internal strings, not page copy.

return [
    'field' => [
        'name' => 'Имя',
        'email' => 'E-mail',
        'password' => 'Пароль',
        'password_confirmation' => 'Повторите пароль',
        'new_password' => 'Новый пароль',
        'first_name' => 'Имя',
        'last_name' => 'Фамилия',
        'patronymic' => 'Отчество',
        'phone' => 'Телефон',
        'phone_help' => 'Российский номер с +7 или 8: +7 (999) 123-45-67, 8 999 123 45 67',
        'default_order_comment' => 'Комментарий к заказу по умолчанию',
        'current_password' => 'Текущий пароль',
        'new_password_confirmation' => 'Повторите новый пароль',
    ],

    'login' => [
        'title' => 'С возвращением',
        'subtitle' => 'Войдите, чтобы заказывать как свои',
        'remember' => 'Запомнить меня',
        'forgot' => 'Забыли пароль?',
        'submit' => 'Войти',
        'no_account' => 'Впервые у нас?',
        'create' => 'Создать аккаунт',
        'or' => 'или',
    ],

    'telegram' => [
        'section_title' => 'Telegram',
        'linked_as' => 'Аккаунт привязан к Telegram ID :id.',
        'not_linked' => 'Откройте бота в Telegram и нажмите «Start», чтобы привязать аккаунт.',
        'open_in_telegram' => 'Открыть в Telegram',
        'unlink' => 'Отвязать Telegram',
        'unlink_blocked' => 'Отвязать Telegram нельзя: это единственный способ войти в аккаунт. Сначала добавьте e-mail и пароль.',
        'email_optional' => 'Вы вошли через Telegram — e-mail можно не указывать.',
        'failed' => 'Не удалось войти через Telegram. Попробуйте ещё раз.',
        'linked' => 'Telegram привязан.',
        'unlinked' => 'Telegram отвязан.',
        'webapp_login' => 'Войти через Telegram',
    ],

    'register' => [
        'title' => 'Стать своим',
        'subtitle' => 'Минута — и доставка уже ваша',
        'age_gate' => 'Мне есть 18 лет',
        'age_gate_required' => 'Подтвердите, что вам есть 18 лет.',
        'terms_gate' => 'Принимаю условия сервиса',
        'terms_gate_required' => 'Нужно принять условия сервиса.',
        'password_hint' => 'Минимум 8 символов',
        'submit' => 'Создать аккаунт',
        'have_account' => 'Уже с нами?',
        'login' => 'Войти',
    ],

    'forgot' => [
        'title' => 'Восстановление пароля',
        'subtitle' => 'Пришлём ссылку на почту',
        'submit' => 'Отправить ссылку',
        'back' => 'Вернуться ко входу',
    ],

    'reset' => [
        'title' => 'Новый пароль',
        'subtitle' => 'Придумайте новый пароль для входа',
        'submit' => 'Сохранить пароль',
    ],

    'confirm' => [
        'title' => 'Подтвердите пароль',
        'subtitle' => 'Это защищённая область — подтвердите пароль перед продолжением',
        'submit' => 'Подтвердить',
    ],

    'verify' => [
        'title' => 'Подтвердите e-mail',
        'subtitle' => 'Мы отправили ссылку для подтверждения на вашу почту',
        'sent' => 'Письмо с новой ссылкой отправлено.',
        'resend' => 'Отправить письмо ещё раз',
    ],

    // Honeypot/троттл регистрации и сброса пароля, SmartCaptcha — см.
    // config/security.php, Infrastructure\Rules\HoneypotRule/SmartCaptchaRule.
    'security' => [
        // Одно сообщение на все причины отказа honeypot'а (приманка
        // заполнена / поле-таймер битое или отсутствует / слишком быстрая
        // отправка) — бот не должен понять, какая именно проверка сработала.
        'form_rejected' => 'Не удалось отправить форму. Обновите страницу и попробуйте снова.',
        // Отдельное сообщение только для протухшей формы — это не бот, это
        // пользователь со старой вкладкой, ему нужен конкретный совет.
        'form_expired' => 'Форма устарела. Обновите страницу и заполните заново.',
        'captcha_required' => 'Подтвердите, что вы не робот.',
        'captcha_failed' => 'Проверка капчи не пройдена. Попробуйте ещё раз.',
        'register_throttled' => 'Слишком много попыток регистрации. Попробуйте через :seconds с.',
        'password_throttled' => 'Слишком много запросов. Попробуйте через :seconds с.',
        'verification_throttled' => 'Слишком много запросов. Попробуйте через минуту.',
    ],

    'nav' => [
        'profile' => 'Профиль',
        'orders' => 'Мои заказы',
    ],

    'profile' => [
        'basic_title' => 'Основные данные',
        'details_title' => 'Личные данные',
        'password_title' => 'Пароль',
        'basic_updated' => 'Данные сохранены.',
        'details_updated' => 'Данные сохранены.',
        'password_updated' => 'Пароль изменён.',
        'submit' => 'Сохранить',
        'saving' => 'Сохраняем…',
    ],

    'address' => [
        'label' => 'Название',
        'city' => 'Город',
        'address' => 'Адрес',
        'comment' => 'Комментарий',
        'is_default' => 'Сделать адресом по умолчанию',
        'add' => 'Добавить адрес',
        'edit' => 'Изменить',
        'delete' => 'Удалить',
        'delete_confirm' => 'Удалить этот адрес?',
        'empty' => 'Пока нет ни одного адреса.',
        'default_marker' => 'По умолчанию',
        'index_title' => 'Адреса доставки',
        'created' => 'Адрес добавлен.',
        'updated' => 'Адрес сохранён.',
        'deleted' => 'Адрес удалён.',
    ],

    'favorites' => [
        'title' => 'Избранное',
        'empty' => 'В избранном пока ничего нет.',
        'clear' => 'Очистить всё',
        'clear_confirm' => 'Удалить все товары из избранного?',
        'added' => 'Товар добавлен в избранное.',
        'removed' => 'Товар удалён из избранного.',
        'cleared' => 'Избранное очищено.',
    ],

    'cart' => [
        'title' => 'Корзина',
        'empty' => 'Корзина пуста.',
        'items' => 'Товары',
        'total_label' => 'Итого',
        'checkout' => 'Оформить заказ',
        'continue' => 'Продолжить покупки',
        'clear' => 'Очистить корзину',
        'clear_confirm' => 'Очистить корзину?',
        'remove' => 'Удалить из корзины',
        'added' => 'Товар добавлен в корзину.',
        'removed' => 'Товар удалён из корзины.',
        'cleared' => 'Корзина очищена.',
        // no-JS фолбэк CartController::rejectUnavailable() — тот же текст,
        // что и catalog.cart.unavailable (JSON-ветка), другая область строк.
        'unavailable' => 'Товар закончился и недоступен для заказа.',
        'has_unavailable' => 'В корзине есть товары, которых нет в наличии. Уберите их, чтобы оформить заказ.',
    ],
];
