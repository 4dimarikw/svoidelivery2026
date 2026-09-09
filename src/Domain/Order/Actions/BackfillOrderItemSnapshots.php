<?php

declare(strict_types=1);

namespace Domain\Order\Actions;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Запускается в техническое окно без записи каталога и заказов. */
final class BackfillOrderItemSnapshots
{
    public function execute(): void
    {
        $before = $this->businessFingerprint();
        $this->assertSourcesAvailable();

        DB::table('order_items')->orderBy('id')->chunkById(500, function ($items): void {
            DB::transaction(function () use ($items): void {
                $products = DB::table('products')->whereIn('id', $items->pluck('product_id')->unique())->get()->keyBy('id');
                foreach ($items as $item) {
                    // NULL бренда допустим: готовый снимок не нужно "дополнять"
                    // брендом, который мог появиться у товара позднее.
                    if ($item->product_name !== null && $item->product_external_code !== null) {
                        continue;
                    }
                    $product = $products->get($item->product_id);
                    if ($product === null || trim((string) $product->name) === '' || trim((string) $product->external_code) === '') {
                        throw new RuntimeException('Нет источника снимка позиции #'.$item->id);
                    }
                    $values = [];
                    if ($item->product_name === null) {
                        // Тот же смысл названия, что у Product::name (HtmlEntityDecoder).
                        $values['product_name'] = html_entity_decode($product->name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    }
                    if ($item->product_external_code === null) {
                        $values['product_external_code'] = $product->external_code;
                    }
                    if ($item->product_brand === null) {
                        $values['product_brand'] = $product->brand;
                    }
                    DB::table('order_items')->where('id', $item->id)->update($values);
                }
            });
        });

        $this->assertComplete();
        if (! hash_equals($before, $this->businessFingerprint())) {
            throw new RuntimeException('При заполнении снимков изменились бизнес-данные. Не возобновляйте работу до сверки.');
        }
    }

    public function assertComplete(): void
    {
        $invalid = DB::table('order_items')->where(function ($query) {
            $query->whereNull('product_name')->orWhereNull('product_external_code')
                ->orWhereRaw("TRIM(product_name) = ''")->orWhereRaw("TRIM(product_external_code) = ''");
        })->orderBy('id')->limit(100)->pluck('id');
        if ($invalid->isNotEmpty()) {
            throw new RuntimeException('Не заполнены снимки позиций (первые 100 ID): '.$invalid->implode(', '));
        }
    }

    private function assertSourcesAvailable(): void
    {
        $invalid = DB::table('order_items as oi')->leftJoin('products as p', 'p.id', '=', 'oi.product_id')
            ->where(function ($query) {
                $query->whereNull('oi.product_name')->orWhereNull('oi.product_external_code');
            })->where(function ($query) {
                $query->whereNull('p.id')->orWhereNull('p.name')->orWhereNull('p.external_code')
                    ->orWhereRaw("TRIM(p.name) = ''")->orWhereRaw("TRIM(p.external_code) = ''");
            })->orderBy('oi.id')->limit(100)->pluck('oi.id');
        if ($invalid->isNotEmpty()) {
            throw new RuntimeException('Нет достоверного источника для позиций (первые 100 ID): '.$invalid->implode(', '));
        }
    }

    private function businessFingerprint(): string
    {
        // Точные десятичные строки из MySQL, без float. Порядок и каждая
        // строка входят в хеш: проверяется также количество и принадлежность.
        $hash = hash_init('sha256');
        DB::table('order_items')->select(['id', 'order_id', 'product_id', 'price', 'quantity', 'created_at', 'updated_at'])
            ->selectRaw('price * quantity as line_amount')->orderBy('id')->chunkById(500, function ($rows) use ($hash): void {
                foreach ($rows as $row) {
                    hash_update($hash, 'item:'.json_encode($row, JSON_THROW_ON_ERROR)."\n");
                }
            });
        DB::table('orders')->select(['id', 'amount', 'created_at', 'updated_at'])->orderBy('id')
            ->chunkById(500, function ($rows) use ($hash): void {
                foreach ($rows as $row) {
                    hash_update($hash, 'order:'.json_encode($row, JSON_THROW_ON_ERROR)."\n");
                }
            });

        return hash_final($hash);
    }
}
