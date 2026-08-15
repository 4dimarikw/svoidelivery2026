/**
 * Регистрация Alpine.data() для библиотеки компонентов <x-ui.*>.
 * Регистрируется на `alpine:init`, чтобы порядок загрузки относительно Alpine.start() не имел значения.
 */
document.addEventListener('alpine:init', () => {
    /**
     * Хранилище состояния для <x-ui.form mode="enhance|ajax" :state="...">.
     *
     * - mode: 'default'  → обычный серверный round-trip, это хранилище ничего лишнего не делает.
     * - mode: 'enhance'  → тот же POST, но `state` доступен для потребителей x-model
     *                      (путь C в контракте значений компонента).
     * - mode: 'ajax'     → перехватывает submit, отправляет запрос через fetch с
     *                      Accept: application/json, ожидает от Laravel тело ошибки
     *                      валидации 422 ({ errors: { field: [msg] } }) при сбое.
     *                      Если fetch сам не сработал, выполняется обычная отправка формы
     *                      (например, при отсутствии сети; при отключённом JS код сюда вообще не попадёт,
     *                      потому что у <form> нет отдельного JS-only пути отправки).
     */
    Alpine.data('uiForm', ({ mode = 'default', state = {}, errors = {} } = {}) => ({
        mode,
        state,
        submitting: false,
        // Инициализируется из мешка ошибок Blade при старте
        // (<x-ui.form> передаёт `errors: @js($errors->messages())`), чтобы обычная
        // загрузка после 302-редиректа и неудачная ajax-отправка отображались одинаково.
        // x-text у <x-ui.error> просто повторяет то, что уже вывел Blade, без flash-сообщений.
        errors,
        status: null,

        errorFor(field) {
            return this.errors[field]?.[0] ?? null;
        },

        async submit(el) {
            // Привязывается через `x-on:submit.prevent="submit($el)"`:
            // модификатор `.prevent` уже останавливает нативную отправку,
            // поэтому этому методу не нужен объект события.
            if (this.mode !== 'ajax') {
                return true;
            }

            this.submitting = true;
            this.errors = {};
            this.status = null;

            try {
                const response = await fetch(el.action, {
                    method: 'POST',
                    body: new FormData(el),
                    headers: { Accept: 'application/json' },
                });

                if (response.status === 422) {
                    const body = await response.json();
                    this.errors = body.errors ?? {};
                    return;
                }

                if (!response.ok) {
                    throw new Error(`Unexpected response: ${response.status}`);
                }

                // Навигацию выполняет только явный заголовок X-Redirect.
                // response.url здесь НЕ подходит как запасной вариант: PUT-эндпоинты
                // в стиле Fortify возвращают пустой 200 без Location, когда
                // $request->wantsJson(). Тогда fetch кладёт в response.url тот же URL,
                // что и action формы, а переход через window.location.assign() делает GET,
                // который отвечает 405. Отсутствие заголовка означает
                // "успех без навигации" — вместо этого показываем inline-результат через `status`.
                const redirect = response.headers.get('X-Redirect');
                if (redirect) {
                    window.location.assign(redirect);
                    return;
                }

                this.status = 'ok';
            } catch (e) {
                // При сбое сети или JS откатываемся к обычной отправке, чтобы форма продолжала работать.
                this.mode = 'default';
                el.submit();
            } finally {
                this.submitting = false;
            }
        },
    }));

    /**
     * Переключатель показа/скрытия для <x-ui.password-field>.
     * Это чисто визуальная логика — значение поля не меняется, поэтому компонент
     * совместим с любым из трёх путей передачи значения.
     */
    Alpine.data('uiPasswordToggle', () => ({
        show: false,

        toggle() {
            this.show = !this.show;
        },

        get type() {
            return this.show ? 'text' : 'password';
        },
    }));

    /**
     * Задержка повторной отправки для <x-ui.resend-button>.
     * `seconds` — начальный таймер; кнопка остаётся отключённой, пока он не дойдёт до нуля.
     */
    Alpine.data('uiCountdown', (seconds = 60) => ({
        remaining: seconds,
        timer: null,

        init() {
            this.start();
        },

        start() {
            clearInterval(this.timer);
            this.remaining = seconds;
            this.timer = setInterval(() => {
                if (this.remaining <= 0) {
                    clearInterval(this.timer);
                    return;
                }
                this.remaining -= 1;
            }, 1000);
        },

        get disabled() {
            return this.remaining > 0;
        },

        get label() {
            return this.remaining > 0 ? `${this.remaining}s` : null;
        },
    }));

    /**
     * Маска телефона для <x-ui.input-field name="phone" type="tel" x-data="phoneMask">
     * (форма заказа, профиль). Форматирует по ходу набора и переживает вставку
     * из буфера — оба случая идут через один и тот же 'input'-обработчик,
     * который форматирует из цифр, а не патчит строку по кусочкам.
     *
     * Своего x-model не вводит и работает через $el напрямую — иначе спорил бы
     * с value-контрактом <x-ui.input> (три способа получить значение, см.
     * шапку input.blade.php). Правило Support\Rules\RussianPhoneNumber
     * разрешает и +7, и голую 8/7 — маска не навязывает один из них, просто
     * группирует то, что уже набрано. Без JS поле остаётся обычным
     * type="tel" с placeholder/help — вся валидация всё равно на сервере.
     */
    Alpine.data('phoneMask', () => ({
        init() {
            this.$el.value = this.formatted(this.$el.value);
            this.$el.addEventListener('input', () => {
                const caretAtEnd = this.$el.selectionEnd === this.$el.value.length;
                this.$el.value = this.formatted(this.$el.value);
                if (caretAtEnd) {
                    this.$el.setSelectionRange(this.$el.value.length, this.$el.value.length);
                }
            });
        },

        formatted(raw) {
            const hasPlus = raw.trim().startsWith('+');
            let digits = raw.replace(/\D/g, '').slice(0, 11);

            if (digits.length === 0) {
                return '';
            }

            // Без ведущей 7/8 не форматируем — это невалидный по правилу
            // ввод (нужен код страны), маска не должна делать вид, что он
            // подходит под шаблон.
            if (digits[0] !== '7' && digits[0] !== '8') {
                return raw;
            }

            const prefix = hasPlus ? '+7' : digits[0];
            digits = digits.slice(1);

            let out = prefix;
            if (digits.length > 0) out += ' (' + digits.slice(0, 3);
            if (digits.length >= 3) out += ')';
            if (digits.length > 3) out += ' ' + digits.slice(3, 6);
            if (digits.length > 6) out += '-' + digits.slice(6, 8);
            if (digits.length > 8) out += '-' + digits.slice(8, 10);

            return out;
        },
    }));

    /**
     * Действие "скопировать всё" для <x-ui.code-list>.
     * Clipboard API требует HTTPS (это нормально — Safari 13.1+ поддерживает его);
     * если navigator.clipboard недоступен, используется запасной вариант через execCommand.
     */
    Alpine.data('uiCopy', (text = '') => ({
        copied: false,

        async copy() {
            try {
                await navigator.clipboard.writeText(text);
            } catch {
                const el = document.createElement('textarea');
                el.value = text;
                el.style.position = 'fixed';
                el.style.opacity = '0';
                document.body.appendChild(el);
                el.select();
                document.execCommand('copy');
                document.body.removeChild(el);
            }

            this.copied = true;
            setTimeout(() => {
                this.copied = false;
            }, 2000);
        },
    }));
});
