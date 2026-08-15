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
        'vk_url' => 'Ссылка на VK',
        'telegram_url' => 'Ссылка на Telegram',
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
    ],
];
