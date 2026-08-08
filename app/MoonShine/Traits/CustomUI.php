<?php

declare(strict_types=1);

namespace App\MoonShine\Traits;

use MoonShine\UI\Components\Table\TableBuilder;

/**
 * Общие UI-хелперы для MoonShine-страниц Order-домена.
 */
trait CustomUI
{
    /**
     * Переключить таблицу в вертикальный режим (пары "поле: значение"
     * вместо строк-колонок) — используется в модалках/детальных страницах
     * Order/OrderCustomer/OrderItem, где горизонтальная таблица не влезает.
     */
    protected function modifyTableVertical(TableBuilder $table): TableBuilder
    {
        return $table->vertical();
    }
}
