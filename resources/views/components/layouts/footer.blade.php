@php use Infrastructure\Settings\SiteSettings; @endphp
<footer class="border-t border-hairline bg-cream-50">
    <div
        class="mx-auto flex max-w-page flex-col items-center space-y-4 px-6 py-10 text-center sm:flex-row sm:items-center sm:justify-between sm:space-y-0 sm:text-left">
        <p class="font-display text-heading-s uppercase text-ink-900">{{ app(SiteSettings::class)->site_name }}</p>

        <div class="font-mono text-micro uppercase tracking-meta text-ink-500">
            <p>&copy; {{ now()->year }} {{ app(SiteSettings::class)->site_name }}. {{ __('layout.footer.rights') }}.</p>
            <p class="mt-1">{{ __('layout.footer.age_notice') }}</p>
        </div>
    </div>
</footer>
