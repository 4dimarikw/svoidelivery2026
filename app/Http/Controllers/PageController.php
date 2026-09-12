<?php

namespace App\Http\Controllers;

use Domain\Content\Actions\Content\LoadPublicPage;
use Illuminate\Http\Request;
use Mews\Purifier\Facades\Purifier;

class PageController extends Controller
{
    public function terms(LoadPublicPage $loadPublicPage)
    {
        $data = $loadPublicPage->handle('terms');
        $data['termsHtml'] = $data['sections']->flatMap(fn ($section) => $section->publishedBlocks)
            ->where('type', 'terms_text')
            ->map(fn ($block) => Purifier::clean((string) data_get($block->content, 'body', ''), 'terms'))
            ->filter(fn ($html) => trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'), " \t\n\r\0\x0B\xc2\xa0") !== '');

        return view('pages.terms', $data);
    }

    /**
     * Статическая страница «О нас» — заглушка, первая точка входа
     * пункта меню <x-ui.mobile-nav>.
     */
    public function about(Request $request, LoadPublicPage $loadPublicPage)
    {
        return view('pages.about', $loadPublicPage->handle((string) $request->route()?->getName()));
    }
}
