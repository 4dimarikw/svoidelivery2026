<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Domain\Profile\Models\Address;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AddressController extends Controller
{
    public function index(Request $request): View
    {
        $addresses = $request->user()->addresses()->latest()->get();

        // Уже посчитали коллекцию — отдаём то же число в addressesCount()
        // (account-nav.blade.php, user-menu.blade.php), чтобы она не делала
        // свой отдельный count()-запрос по той же таблице.
        $request->user()->setAttribute('addresses_count', $addresses->count());

        return view('account.addresses.index', [
            'addresses' => $addresses,
        ]);
    }

    public function create(): View
    {
        return view('account.addresses.create', ['address' => null]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $request->user()->addresses()->create($this->validated($request));

        if ($request->wantsJson()) {
            // The only ajax form in this feature that navigates elsewhere —
            // X-Redirect is the sole channel uiForm.submit() will follow
            // (see resources/js/ui.js — no fallback to response.url).
            return response()->json([], 200)->header('X-Redirect', route('account.addresses.index'));
        }

        return redirect()->route('account.addresses.index')->with('status', 'address-created');
    }

    public function edit(Request $request, Address $address): View
    {
        abort_unless($address->user_id === $request->user()->id, 403);

        return view('account.addresses.edit', ['address' => $address]);
    }

    public function update(Request $request, Address $address): RedirectResponse|JsonResponse
    {
        abort_unless($address->user_id === $request->user()->id, 403);

        $address->update($this->validated($request));

        if ($request->wantsJson()) {
            // Stays on the edit form, inline success — same pattern as the
            // profile page, not a redirect.
            return response()->json([], 200);
        }

        return back()->with('status', 'address-updated');
    }

    public function destroy(Request $request, Address $address): RedirectResponse
    {
        abort_unless($address->user_id === $request->user()->id, 403);

        $address->delete();

        // No wantsJson branch — this form is deliberately mode="default"
        // (plain POST-spoofed-DELETE + confirm()), not ajax.
        return redirect()->route('account.addresses.index')->with('status', 'address-deleted');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'label' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'is_default' => ['nullable', 'boolean'],
        ]);
    }
}
