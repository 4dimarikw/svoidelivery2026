<?php

declare(strict_types=1);

namespace Support\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * Денежная сумма. Внутри всегда хранится в минорных единицах (копейках, int) —
 * это исключает погрешности float-арифметики при сложении/умножении. Наружу
 * отдаётся либо через minor()/major(), либо готовой строкой через format()/
 * __toString() (символ валюты ПЕРЕД числом — брендбук §06, тот же формат, что
 * раньше жил в product-card.blade.php как number_format(...)).
 *
 * Иммутабелен: multiply()/add() возвращают новый экземпляр, не мутируют текущий.
 * Никакой наценки/настроек/обращений к контейнеру внутри — конвертация между
 * рублями (как хранится products.price) и копейками (как считает этот VO)
 * живёт целиком в Support\Casts\PriceCast, а не здесь.
 */
final class Price implements JsonSerializable, Stringable
{
    private const CURRENCIES = [
        'RUB' => '₽',
    ];

    private function __construct(
        private readonly int $minor,
        private readonly string $currency = 'RUB',
    ) {
        if ($minor < 0) {
            throw new InvalidArgumentException('Сумма не может быть отрицательной.');
        }

        if (! isset(self::CURRENCIES[$currency])) {
            throw new InvalidArgumentException("Неизвестная валюта: {$currency}.");
        }
    }

    /**
     * Из минорных единиц (копеек) — например, для сумм, уже посчитанных в БД/кэше.
     */
    public static function fromMinor(int $minor, string $currency = 'RUB'): self
    {
        return new self($minor, $currency);
    }

    /**
     * Из основных единиц (рублей) — то, как хранится products.price/cart_items.price
     * (decimal(12,2)).
     */
    public static function fromMajor(int|float|string $major, string $currency = 'RUB'): self
    {
        return new self((int) round(((float) $major) * 100), $currency);
    }

    public function minor(): int
    {
        return $this->minor;
    }

    public function major(): float
    {
        return $this->minor / 100;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function symbol(): string
    {
        return self::CURRENCIES[$this->currency];
    }

    public function multiply(int $factor): self
    {
        return new self($this->minor * $factor, $this->currency);
    }

    public function add(self $other): self
    {
        if ($other->currency !== $this->currency) {
            throw new InvalidArgumentException('Нельзя сложить суммы в разных валютах.');
        }

        return new self($this->minor + $other->minor, $this->currency);
    }

    public function isZero(): bool
    {
        return $this->minor === 0;
    }

    public function format(int $decimals = 0): string
    {
        return $this->symbol().' '.number_format($this->major(), $decimals, ',', ' ');
    }

    public function __toString(): string
    {
        return $this->format();
    }

    public function jsonSerialize(): float
    {
        return $this->major();
    }
}
