<?php

declare(strict_types=1);

namespace Domain\Order\DTO;

use Domain\Order\Requests\OrderRequest;
use Support\Traits\Makeable;

/**
 * Данные покупателя из формы оформления. Адрес не хранится текстом —
 * только ссылка на уже сохранённую запись Domain\Profile\Models\Address
 * (книга адресов), которую AssignCustomer снимает в OrderCustomer на
 * момент заказа. Null, если способ доставки адреса не требует (самовывоз).
 */
final class CustomerDTO
{
    use Makeable;

    public function __construct(
        public readonly ?int $addressId,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string $phone,
        public readonly string $messengerUrl,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            // Данные могут прийти строками (например, из формы) —
            // конструктор строго типизирован (?int + strict_types=1).
            addressId: isset($data['address_id']) ? (int) $data['address_id'] : null,
            firstName: $data['first_name'],
            lastName: $data['last_name'],
            phone: $data['phone'],
            messengerUrl: $data['messenger_url'],
        );
    }

    public static function fromRequest(OrderRequest $request): self
    {
        return new self(
            // input() отдаёт address_id строкой ('1') — форма шлёт всё
            // строками, а конструктор строго типизирован (?int +
            // strict_types=1). Пустое значение — не адрес 0, а «доставка
            // адреса не требует» → null (filled(), не integer(): последний
            // вернул бы 0 при отсутствии ключа, а 0 — валидный, но чужой id).
            addressId: $request->filled('address_id') ? $request->integer('address_id') : null,
            firstName: $request->input('first_name'),
            lastName: $request->input('last_name'),
            phone: $request->input('phone'),
            messengerUrl: $request->input('messenger_url'),
        );
    }

    public function toArray(): array
    {
        return [
            'address_id' => $this->addressId,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'phone' => $this->phone,
            'messenger_url' => $this->messengerUrl,
        ];
    }

    public function toNotNullArray(): array
    {
        return array_filter($this->toArray(), fn ($value) => $value !== null);
    }
}
