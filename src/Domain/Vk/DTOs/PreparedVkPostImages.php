<?php

declare(strict_types=1);

namespace Domain\Vk\DTOs;

use Domain\Vk\Enums\VkImageSendMode;

final readonly class PreparedVkPostImages
{
    /** @param list<string> $images URLs or readable local paths, in post order. */
    public function __construct(public VkImageSendMode $mode, public array $images, public array $imageUrls = []) {}
}
