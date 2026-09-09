<?php

declare(strict_types=1);

namespace Domain\Catalog\Exceptions;

use RuntimeException;

/** Отказ добавления товара: транзакция откатывается вместе с созданием корзины. */
final class ProductUnavailableException extends RuntimeException {}
