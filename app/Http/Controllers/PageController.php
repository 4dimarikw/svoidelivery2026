<?php

namespace App\Http\Controllers;

class PageController extends Controller
{
    /**
     * Статическая страница «О нас» — заглушка, первая точка входа
     * пункта меню <x-ui.mobile-nav>.
     */
    public function about()
    {
        return view('pages.about');
    }
}
