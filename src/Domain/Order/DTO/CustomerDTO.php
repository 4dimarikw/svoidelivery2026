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
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            addressId: $data['address_id'] ?? null,
            firstName: $data['first_name'],
            lastName: $data['last_name'],
            phone: $data['phone'],
        );
    }

    public static function fromRequest(OrderRequest $request): self
    {
        return new self(
            addressId: $request->input('address_id') ?? null,
            firstName: $request->input('first_name'),
            lastName: $request->input('last_name'),
            phone: $request->input('phone'),
        );
    }

    public function toArray(): array
    {
        return [
            'address_id' => $this->addressId,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'phone' => $this->phone,
        ];
    }

    public function toNotNullArray(): array
    {
        return array_filter($this->toArray(), fn ($value) => $value !== null);
    }
}
