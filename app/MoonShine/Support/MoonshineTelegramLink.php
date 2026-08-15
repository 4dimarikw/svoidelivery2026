<?php

declare(strict_types=1);

namespace App\MoonShine\Support;

use Domain\Telegram\Models\TelegramChat;
use Domain\Telegram\Support\TelegramLinkCode;

/**
 * Тонкий фасад над Domain\Telegram\* для админской стороны привязки
 * Telegram — используется страницей профиля MoonShine (app/MoonShine/Pages/
 * ProfilePage.php) и кнопкой «Отправить себе» на VK-постах
 * (app/MoonShine/Resources/VkPost/Pages/VkPostIndexPage.php), чтобы обе не
 * дублировали запросы к telegraph_chats. Живёт в app/MoonShine/, а не в
 * Domain\, по тому же принципу, что и остальной MoonShine-glue — вендорный
 * MoonshineUser не должен становиться зависимостью Domain\.
 */
final class MoonshineTelegramLink
{
    public static function chatFor(int $moonshineUserId): ?TelegramChat
    {
        return TelegramChat::query()->forMoonshineUser($moonshineUserId)->first();
    }

    public static function issueCode(int $moonshineUserId): string
    {
        return TelegramLinkCode::issueFor(TelegramLinkCode::SUBJECT_MOONSHINE_USER, $moonshineUserId);
    }

    public static function unlink(int $moonshineUserId): void
    {
        TelegramChat::query()
            ->forMoonshineUser($moonshineUserId)
            ->update(['moonshine_user_id' => null]);
    }
}
