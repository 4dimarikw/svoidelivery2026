<?php

return [
    /*
     * When false, every response passing through the PreventSiteIndexing
     * middleware gets "X-Robots-Tag: noindex, nofollow".
     */
    'indexable' => (bool) env('SEO_INDEXABLE', false),

    /*
     * Appended to titles as " | Brand" unless the title already contains
     * the brand as a standalone word. Empty or null disables the suffix.
     */
    'title_brand' => null,

    /*
     * Fallback values used when neither a SEO record nor the page provides one.
     * Pass a SeoDefaults instance to SeoManager::forPage() to override them at runtime.
     */
    'default' => [
        'title' => null,
        'description' => null,
        'keywords' => null,
    ],

    /*
     * Route name patterns that receive "X-Robots-Tag: noindex" while the site
     * is indexable (e.g. 'account.*', 'cart.*', 'login').
     */
    'noindex_routes' => [],

    'robots' => [
        /*
         * Registers GET /robots.txt (route name "seo.robots").
         */
        'enabled' => true,

        /*
         * Absolute sitemap URL added to robots.txt while the site is indexable.
         * Use a plain string here, not route(), to stay compatible with config:cache.
         */
        'sitemap_url' => env('SEO_SITEMAP_URL'),
    ],
];
