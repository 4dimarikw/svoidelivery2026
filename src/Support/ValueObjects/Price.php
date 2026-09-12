<?php

declare(strict_types=1);

namespace Support\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * Неизменяемая денежная сумма в целых копейках, исключающая погрешности float при вычислениях. minor()/major()
 * возвращают числовые значения, format()/__toString() — строку с валютой перед числом. multiply()/add()
 * возвращают новый экземпляр. Преобразование рублей из БД в копейки выполняет PriceCast.
 */
final class Price implements JsonSerializable, Stringable
{
    public const MAX_MINOR = 999999999999;

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
     * (decimal(12,2)). Строки разбираются без float; лишняя точность отклоняется.
     */
    public static function fromMajor(int|float|string $major, string $currency = 'RUB'): self
    {
        if (is_float($major)) {
            if (! is_finite($major)) {
                throw new InvalidArgumentException('Invalid monetary amount.');
            }
            $major = json_encode($major, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
        }
        $text = preg_replace('/^[\s\x{00A0}\x{202F}]+|[\s\x{00A0}\x{202F}]+$/u', '', (string) $major);
        if (! preg_match('/\A([0-9]+|[0-9]{1,3}(?:[ \x{00A0}\x{202F}][0-9]{3})+)(?:[.,]([0-9]{1,2}))?\z/u', $text ?? '', $matches)) {
            throw new InvalidArgumentException('Invalid monetary amount.');
        }
        $whole = ltrim(str_replace([' ', "\u{00A0}", "\u{202F}"], '', $matches[1]), '0');
        if (strlen($whole) > 10) {
            throw new InvalidArgumentException('Monetary amount exceeds decimal(12,2).');
        }

        return new self((int) $whole * 100 + (int) str_pad($matches[2] ?? '', 2, '0'), $currency);
    }

    public function decimal(): string
    {
        if ($this->minor > self::MAX_MINOR) {
            throw new InvalidArgumentException('Monetary amount exceeds decimal(12,2).');
        }

        return intdiv($this->minor, 100).'.'.str_pad((string) ($this->minor % 100), 2, '0', STR_PAD_LEFT);
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
        if ($factor < 0 || ($this->minor > 0 && $factor > intdiv(PHP_INT_MAX, $this->minor))) {
            throw new InvalidArgumentException('Monetary multiplication out of range.');
        }

        return new self($this->minor * $factor, $this->currency);
    }

    public function add(self $other): self
    {
        if ($other->minor > PHP_INT_MAX - $this->minor) {
            throw new InvalidArgumentException('Monetary addition out of range.');
        }
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
