<?php

namespace App\Http\Middleware;

use App\MoonShine\Pages;
use App\MoonShine\Resources\EventLog\Pages\EventLogIndexPage;
use App\MoonShine\Resources\Order\Pages\OrderDetailPage;
use App\MoonShine\Resources\Order\Pages\OrderFormPage;
use App\MoonShine\Resources\Order\Pages\OrderIndexPage;
use App\MoonShine\Resources\Product\Pages\ProductFormPage;
use App\MoonShine\Resources\SiteMenuItem\Pages\SiteMenuItemIndexPage;
use App\MoonShine\Resources\UntappdBeer\Pages\UntappdBeerFormPage;
use App\MoonShine\Resources\VkPost\Pages\VkPostFormPage;
use App\MoonShine\Resources\VkPost\Pages\VkPostIndexPage;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use MoonShine\Contracts\Core\CrudPageContract;
use MoonShine\Contracts\Core\DependencyInjection\CrudRequestContract;
use Symfony\Component\HttpFoundation\Response;

/** Checks run outside MethodController, which otherwise turns authorization exceptions into 500. */
class SecureMoonShineActions
{
    private const ADMIN_PAGES = [
        'event-logging-settings-page', 'catalog-import-settings-page', 'vk-sync-settings-page', 'scheduling-page',
    ];

    private const METHODS = [
        Pages\EventLoggingSettingsPage::class => ['save'],
        Pages\CatalogImportSettingsPage::class => ['store'],
        Pages\VkSyncSettingsPage::class => ['save'],
        Pages\SiteSettingsPage::class => ['save'],
        Pages\ProfilePage::class => ['unlinkTelegram'],
        OrderIndexPage::class => ['createTestOrder'],
        OrderFormPage::class => ['createDelivery', 'retryDelivery'],
        OrderDetailPage::class => ['createDelivery', 'retryDelivery'],
        EventLogIndexPage::class => ['clearTable'],
        ProductFormPage::class => ['updateImageFromUntappd'],
        UntappdBeerFormPage::class => ['resync'],
        VkPostIndexPage::class => ['downloadImages', 'sendToSelf', 'broadcastAll'],
        VkPostFormPage::class => ['downloadImages', 'sendToSelf', 'broadcastAll'],
        SiteMenuItemIndexPage::class => ['itemMove', 'transferItem'],
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route()?->getName() ?? '';
        if (! str_starts_with($route, 'moonshine.')) {
            return $next($request);
        }
        $adminPage = in_array($request->route('pageUri'), self::ADMIN_PAGES, true);
        $methodCall = $route === 'moonshine.method';
        $scheduler = $route === 'moonshine.scheduling.run';
        if (! $adminPage && ! $methodCall && ! $scheduler) {
            return $next($request);
        }
        $user = $request->user(config('moonshine.auth.guard', 'moonshine'));
        if ($user === null) {
            return $request->expectsJson() ? response()->json(['message' => 'Unauthenticated.'], 401)
                : redirect()->guest(route('moonshine.login'));
        }
        if ($adminPage || $scheduler) {
            abort_unless($user->isSuperUser(), 403);
        }
        if ($scheduler) {
            abort_unless($request->isMethod('POST'), 405);
        }
        if (! $methodCall) {
            return $next($request);
        }

        $method = $request->input('method');
        abort_unless(is_string($method), 400);
        $expectedVerb = in_array($method, ['itemMove', 'transferItem'], true) ? 'PATCH' : 'POST';
        if (! $request->isMethod($expectedVerb)) {
            return response()->json(['message' => 'Method not allowed.'], 405, ['Allow' => $expectedVerb]);
        }

        $crud = app(CrudRequestContract::class);
        // Avoid the package's custom not-found renderer when page/resource do not match.
        $page = moonshineRequest()->findPage();
        abort_if($page === null, 404);
        // Discovered CRUD pages may also resolve globally, with no resource in the URL.
        // Such a call must not skip the resource's object/policy checks below.
        abort_if($page instanceof CrudPageContract && $crud->getResource() === null, 404);
        abort_unless(in_array($method, self::METHODS[$page::class] ?? [], true), 403);
        if (in_array($method, ['createTestOrder', 'clearTable', 'transferItem'], true)) {
            abort_unless($user->isSuperUser(), 403);
        }
        if ($page instanceof Pages\ProfilePage) {
            // The method always uses the authenticated admin, never a supplied target id.
            abort_if($request->hasAny(['user_id', 'moonshine_user_id', 'resourceItem']), 422);
        }
        if ($resource = $crud->getResource()) {
            if (! in_array($method, ['createTestOrder', 'clearTable'], true)) {
                $field = in_array($method, ['resync', 'updateImageFromUntappd'], true) ? 'itemId' : 'resourceItem';
                $id = $request->route($field, $request->input($field));
                validator([$field => $id], [$field => ['required', 'integer', 'min:1']])->validate();
                $otherId = $request->input($field === 'itemId' ? 'resourceItem' : 'itemId');
                abort_if($otherId !== null && (! is_scalar($otherId) || (string) $otherId !== (string) $id), 422);
                $item = $resource->getModel()->newQuery()->findOrFail($id);
                abort_unless(Gate::forUser($user)->allows('update', $item), 403);
                // Keep policy target and the object used by the action identical.
                $resource->setItem($item);
            }
        }

        return $next($request);
    }
}
