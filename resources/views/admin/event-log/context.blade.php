{{-- Партиал для App\MoonShine\Resources\EventLog\Pages\EventLogDetailPage —
     читаемый pretty-printed JSON контекста события в MoonShine::Preview.
     Стилизация ограничена готовыми классами MoonShine (.snippet и т.п.):
     собственный CSS проекта в сборку админки не попадает, см. main.css.
     Рендерится через view('admin.event-log.context', [...])->render() —
     это обычная вьюха, не anonymous-компонент, поэтому $json приходит как
     обычная переменная (без @props). --}}
@if (blank($json ?? null) || $json === '[]' || $json === '{}')
    <span>—</span>
@else
    <x-moonshine::snippet color="info" style="display:block; position:relative;"
                          x-data="{ copy() { navigator.clipboard.writeText($refs.content.textContent) } }">

        <button type="button"
                class="snippet-copy"
                style="position:absolute; top:.5rem; right:.5rem;"
                @click.prevent="copy()"
                x-data="tooltip('{{ __('moonshine::ui.copied') }}', {placement: 'top', trigger: 'click', delay: [0, 800]})"
        >
            <x-moonshine::icon icon="document-duplicate" size="4"/>
        </button>

        <pre class="snippet-content"
             x-ref="content"
             style="margin:0; white-space:pre-wrap; word-break:normal; overflow-wrap:anywhere; max-height:60vh; overflow:auto;"
        >{{ $json }}</pre>
    </x-moonshine::snippet>
@endif
