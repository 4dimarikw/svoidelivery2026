<?php

declare(strict_types=1);

namespace Tests\Feature\Order;

use Domain\Order\Actions\BuildOrderXml;
use Domain\Order\Actions\OrderXmlFile;
use Domain\Order\Models\Order;
use Domain\Order\Models\OrderCustomer;
use Domain\Order\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SimpleXMLElement;
use Tests\TestCase;

/**
 * Domain\Order\Actions\BuildOrderXml — выделена из UploadOrderToFTP, чтобы
 * тот же XML можно было приложить к письму (Domain\Order\Mail\NewOrderCreated,
 * см. HandleOrderCreated::notifyAdmin()). Разметка/поведение не менялись при
 * выносе — эти тесты дублируют то, что раньше молчаливо проверялось только
 * через UploadOrderToFtpTest (успешная выгрузка на фейковый диск).
 */
class BuildOrderXmlTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_returns_filename_and_valid_xml_with_items(): void
    {
        $order = Order::factory()->create();
        OrderCustomer::factory()->for($order)->create([
            'first_name' => 'Иван',
            'last_name' => 'Иванов',
            'phone' => '+79991234567',
        ]);
        OrderItem::factory()->for($order)->count(2)->create();

        $order->load(['orderCustomer', 'orderItems.product', 'deliveryType']);

        $file = app(BuildOrderXml::class)->execute($order);

        $this->assertInstanceOf(OrderXmlFile::class, $file);
        $this->assertMatchesRegularExpression('/^.+_\d{4}-\d{2}-\d{2}-\d{6}\.xml$/', $file->filename);

        $xml = new SimpleXMLElement($file->contents);

        $this->assertSame($order->number, (string) $xml->Order->OrderNumber);
        $this->assertCount(2, $xml->Order->Items->Item);

        foreach ($xml->Order->Items->Item as $item) {
            $this->assertMatchesRegularExpression('/^\d+\.\d{2}$/', (string) $item->Price);
        }
    }

    /**
     * SimpleXMLElement::addChild() экранирует "<" сам, но сырой "&" рвёт
     * узел — addTextChild() обязан прогонять все значения через
     * htmlspecialchars(..., ENT_XML1). Регрессия на этот случай.
     */
    public function test_ampersand_and_angle_bracket_in_comment_do_not_break_xml(): void
    {
        $order = Order::factory()->create([
            'comment' => 'Позвонить & написать в Telegram <до 18:00>',
        ]);
        OrderCustomer::factory()->for($order)->create();

        $order->load(['orderCustomer', 'orderItems.product', 'deliveryType']);

        $file = app(BuildOrderXml::class)->execute($order);

        $xml = simplexml_load_string($file->contents);

        $this->assertNotFalse($xml);
        $this->assertStringContainsString(
            'Позвонить & написать в Telegram <до 18:00>',
            (string) $xml->Order->Comment
        );
    }
}
