<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Domain\Telegram\Models\TelegramBot;
use Domain\Telegram\Support\TelegramLinkCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Support\Rules\RussianPhoneNumber;

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
        // Телефон из формы заказа (checkout.blade.php) подставляется отсюда
        // же (old('phone', $profile?->phone)) — приводим к тому же
        // каноническому +7XXXXXXXXXX, что и Domain\Order\Requests\
        // OrderRequest, ещё до validate(), у простого $request->validate()
        // нет своего prepareForValidation().
        if (is_string($request->input('phone'))) {
            $request->merge(['phone' => RussianPhoneNumber::normalize($request->input('phone'))]);
        }

        // Одно поле на сеть из config('social.networks') — social_telegram,
        // social_vk, social_max... — а не social_links[telegram]: <x-ui.input>
        // использует $name одновременно как HTML name и как ключ old()/
        // $errors, скобочная нотация там не работает (см. Profile.blade.php).
        $socialSlugs = array_keys(config('social.networks', []));
        $socialRules = array_fill_keys(
            array_map(fn (string $slug) => "social_{$slug}", $socialSlugs),
            ['nullable', 'url', 'max:255']
        );

        $validated = $request->validate([
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'patronymic' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', new RussianPhoneNumber],
            ...$socialRules,
            'default_order_comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $socialLinks = [];
        foreach ($socialSlugs as $slug) {
            $url = $validated["social_{$slug}"] ?? null;
            unset($validated["social_{$slug}"]);

            if ($url !== null && $url !== '') {
                $socialLinks[$slug] = $url;
            }
        }
        $validated['social_links'] = $socialLinks === [] ? null : $socialLinks;

        $request->user()->profile()->updateOrCreate([], $validated);

        if ($request->wantsJson()) {
            // Ajax path: Fortify-style blank success, no session('status') —
            // the form shows its own inline success via Alpine `status`.
            return response()->json([], 200);
        }

        return back()->with('status', 'account-profile-updated');
    }
}
