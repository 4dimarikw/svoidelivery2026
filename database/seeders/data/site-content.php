<?php

return [
    'sections' => [
        ['key' => 'home', 'title' => 'Каталог', 'route_name' => 'home', 'fragment' => '', 'sort_order' => 10],
        ['key' => 'about', 'title' => 'О нас', 'route_name' => 'about', 'fragment' => '', 'sort_order' => 0],
        // route_name = null — глобальная секция, не привязанная к странице
        // (см. миграцию make_site_sections_route_name_nullable), нужна
        // подвалу сайта (Domain\Content\Actions\Content\LoadSiteFooter).
        ['key' => 'footer', 'title' => 'Подвал сайта', 'route_name' => null, 'fragment' => '', 'sort_order' => 30],
    ],
    'menus' => [
        'main' => [
            'title' => 'Главное',
            'is_active' => true,
            'items' => [
                ['key' => 'home', 'parent_key' => null, 'section_key' => 'home', 'external_url' => null, 'label' => 'Каталог', 'open_in_new_tab' => false, 'is_active' => false],
                ['key' => 'about', 'parent_key' => null, 'section_key' => 'about', 'external_url' => null, 'label' => null, 'open_in_new_tab' => false, 'is_active' => true],
            ],
        ],
    ],
    'blocks' => [
        'about' => [
            'type' => 'about_info',
            'title' => 'О нас',
            'content' => [
                'heading' => 'О нас',
                'lead' => 'Приветствуем вас в онлайн-баре "Svoi Delivery"!',
                'schedule' => 'Заказы принимаем по будням до 17:00 (пн-чт) до 16:00 (пт)! Заказы, оформленные в выходные, обрабатываются в понедельник.',
                'ordering_heading' => 'Как оформить заказ:',
                'promo_lead' => 'Акции – специальные предложения и скидки на крафтовое пиво, сидр и мёд:',
                'notice' => 'ОБЯЗАТЕЛЬНО: ОСМОТР ВЛОЖЕНИЯ ПЕРЕД ПОЛУЧЕНИЕМ (ДО ПОДПИСАНИЯ НАКЛАДНОЙ) НА ПРЕДМЕТ СООТВЕТСТВИЯ КОЛИЧЕСТВА И ДЛЯ ВЫЯВЛЕНИЯ СКРЫТЫХ ДЕФЕКТОВ. В СЛУЧАЕ ВЫЯВЛЕНИЯ ПОВРЕЖДЕНИЙ У ВАС ЕСТЬ ПРАВО СОСТАВИТЬ АКТ ОБНАРУЖЕНИЯ НЕСООТВЕТСТВИЯ В ПУНКТЕ ВЫДАЧИ.',
            ],
            'items' => [
                ['group_key' => 'ordering_rules', 'key' => 'rule-1', 'title' => 'Кратность заказа', 'content' => ['text' => 'заказ должен быть кратным 12 или 20 банкам/бутылкам (в заказе могут быть по 1 шт любой позиции)']],
                ['group_key' => 'ordering_rules', 'key' => 'rule-2', 'title' => 'Учёт количества', 'content' => ['text' => 'при заказе просьба учитывать количество бутылок/банок']],
                ['group_key' => 'ordering_rules', 'key' => 'rule-3', 'title' => 'Пример кратности', 'content' => ['text' => 'должна соблюдаться кратность 12/20 (пример: заказ 12 банок/12 бутылок)']],
                ['group_key' => 'ordering_rules', 'key' => 'rule-4', 'title' => 'Доставка Яндекс/Достависта', 'content' => ['text' => 'отправка день в день (при заказе до 14:00) по Москве и за МКАД – рассчитывается по тарифам Яндекс/Достависта']],
                ['group_key' => 'ordering_rules', 'key' => 'rule-5', 'title' => 'Доставка СДЭК', 'content' => ['text' => 'отправка СДЭК – рассчитает менеджер после оформления заказа (отправка в ПВЗ на следующий день)']],
                ['group_key' => 'promotions', 'key' => 'promo-1', 'title' => 'Скидка на день рождения', 'content' => ['text' => 'Скидка 5% в ваш день рождения при предъявлении документа, удостоверяющего личность']],
                ['group_key' => 'promotions', 'key' => 'promo-2', 'title' => 'Скидка от 10000 ₽', 'content' => ['text' => 'Скидка 5% при покупке от 10000 рублей']],
                ['group_key' => 'promotions', 'key' => 'promo-3', 'title' => 'Скидка от 15000 ₽', 'content' => ['text' => 'Скидка 7% при покупке от 15000 рублей']],
            ],
        ],
        'footer' => [
            'type' => 'footer_info',
            'title' => 'Подвал сайта',
            'content' => [
                'rights' => 'Все права защищены',
                'age_notice' => '18+ · Алкоголь может быть вреден для вашего здоровья',
            ],
        ],
    ],
];
