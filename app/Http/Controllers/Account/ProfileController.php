<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Domain\Telegram\Models\TelegramBot;
use Domain\Telegram\Support\TelegramLinkCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();

        // Код нужен только тому, кто ещё не привязан, и только когда есть
        // активный бот (иначе кнопку показывать всё равно не для чего) — не
        // тратим кеш на код, который никто никогда не откроет.
        $bot = ! $user->telegramLinked() ? TelegramBot::current() : null;

        return view('account.profile', [
            // profile can be null — legacy users, or users created before the
            // CreateUserProfile listener existed. The view/form must be
            // null-safe rather than assume it always exists.
            'profile' => $user->profile,
            'telegramBotUsername' => $bot?->username,
            'telegramLinkCode' => $bot ? TelegramLinkCode::issue($user) : null,
        ]);
    }

    public function update(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'patronymic' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'vk_url' => ['nullable', 'url', 'max:255'],
            'telegram_url' => ['nullable', 'url', 'max:255'],
            'default_order_comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $request->user()->profile()->updateOrCreate([], $validated);

        if ($request->wantsJson()) {
            // Ajax path: Fortify-style blank success, no session('status') —
            // the form shows its own inline success via Alpine `status`.
            return response()->json([], 200);
        }

        return back()->with('status', 'account-profile-updated');
    }
}
