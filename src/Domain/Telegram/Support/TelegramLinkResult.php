<?php

namespace Domain\Telegram\Support;

enum TelegramLinkResult: string
{
    case Linked = 'linked';
    case LinkedAdmin = 'linked_admin';
    case AlreadyLinked = 'already_linked';
    case InvalidCode = 'link_code_invalid';
    case Occupied = 'chat_occupied';
    case InvalidSender = 'invalid_sender';
}
