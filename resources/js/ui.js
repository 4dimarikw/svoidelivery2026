/**
 * Alpine.data() registrations for the <x-ui.*> component library.
 * Registered on `alpine:init` so load order relative to Alpine.start() never matters.
 */
document.addEventListener('alpine:init', () => {
    /**
     * Backing store for <x-ui.form mode="enhance|ajax" :state="...">.
     *
     * - mode: 'default'  → plain server round-trip, this store does nothing extra.
     * - mode: 'enhance'  → same POST, but exposes `state` for x-model consumers
     *                      (Path C in the component library's value contract).
     * - mode: 'ajax'     → intercepts submit, posts via fetch with
     *                      Accept: application/json, expects a Laravel 422
     *                      validation-error body ({ errors: { field: [msg] } })
     *                      on failure. Falls back to a real submit if fetch
     *                      itself fails (network down, JS disabled never reaches
     *                      here at all — the <form> has no JS-only submit path).
     */
    Alpine.data('uiForm', ({ mode = 'default', state = {}, errors = {} } = {}) => ({
        mode,
        state,
        submitting: false,
        // Seeded from the Blade $errors bag on init (<x-ui.form> passes
        // `errors: @js($errors->messages())`) so a plain 302-redirect page
        // load and a failed ajax submit render identically — <x-ui.error>'s
        // x-text just repeats what Blade already printed, no flash.
        errors,
        status: null,

        errorFor(field) {
            return this.errors[field]?.[0] ?? null;
        },

        async submit(el) {
            // Bind with `x-on:submit.prevent="submit($el)"` — the `.prevent`
            // modifier already stops the native submit, this method doesn't need
            // the event object.
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

                const redirect = response.headers.get('X-Redirect') ?? response.url;
                if (redirect) {
                    window.location.assign(redirect);
                    return;
                }

                this.status = 'ok';
            } catch (e) {
                // Network/JS failure — degrade to a real submit so the form still works.
                this.mode = 'default';
                el.submit();
            } finally {
                this.submitting = false;
            }
        },
    }));

    /**
     * <x-ui.password-field>'s show/hide toggle. Purely presentational —
     * never touches the value, so it composes with any of the three value paths.
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
     * <x-ui.resend-button>'s cooldown. `seconds` is the initial countdown;
     * the button stays disabled until it reaches zero.
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
     * <x-ui.code-list>'s "copy all" affordance. Clipboard API needs HTTPS
     * (fine — Safari 13.1+ supports it); falls back to execCommand for
     * contexts where navigator.clipboard is unavailable.
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
