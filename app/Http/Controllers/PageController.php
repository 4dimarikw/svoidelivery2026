<?php

namespace App\Http\Controllers;

use Domain\Content\Actions\Content\LoadPublicPage;
use Illuminate\Http\Request;

class PageController extends Controller
{
    /**
     * Статическая страница «О нас» — заглушка, первая точка входа
     * пункта меню <x-ui.mobile-nav>.
     */
    public function about(Request $request, LoadPublicPage $loadPublicPage)
    {
        return view('pages.about', $loadPublicPage->handle((string) $request->route()?->getName()));
    }
}
