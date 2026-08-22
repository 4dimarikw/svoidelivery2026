{{-- Ловушка для ботов + поле-таймер (Infrastructure\Rules\HoneypotRule).
     Имена полей идут из config('security.honeypot.*'), не хардкожены —
     единственный источник правды тот же, что у правила.

     Скрытие — инлайновым style, не Tailwind-классами и не sr-only:
       - sr-only было бы прямо неверно: скринридер поле как раз прочитает
         и предложит заполнить, а его должны обходить именно боты;
       - инлайн-стиль не зависит от JIT-purge Tailwind (не пропал бы из
         собранного CSS при будущей чистке classlist) и работает даже если
         app.css не загрузился — иначе ловушка стала бы видимым полем и
         блокировала бы реальных пользователей, а не ботов.
     !important — чтобы никакой класс-предок (например `.grid > *`) не мог
     случайно её показать. --}}
@if (config('security.honeypot.enabled'))
    @php
        $trapField = config('security.honeypot.field');
        $timerField = config('security.honeypot.timer_field');
    @endphp
    <div
        aria-hidden="true"
        style="position:absolute!important;left:-9999px!important;top:auto;width:1px;height:1px;overflow:hidden"
    >
        <label for="{{ ui_id($trapField) }}">Website</label>
        <input
            type="text"
            name="{{ $trapField }}"
            id="{{ ui_id($trapField) }}"
            value=""
            tabindex="-1"
            autocomplete="off"
        >
    </div>

    <input type="hidden" name="{{ $timerField }}" value="{{ \Illuminate\Support\Facades\Crypt::encryptString((string) now()->timestamp) }}">
@endif
