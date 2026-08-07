/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
        // add './app/View/Components/**/*.php' ONLY if a class-based component is ever introduced
    ],
    theme: {
        extend: {
            colors: {
                teal: {
                    100: '#E4EFEF',
                    200: '#C2DBDB',
                    300: '#8FBABA',
                    400: '#5B9494',
                    500: '#3C7878',
                    600: '#2C6371',
                    700: '#1F4E5C',
                    800: '#173E49',
                    900: '#0F2E37',
                    950: '#0B2229',
                },
                cream: {
                    50: '#FAF6EC',
                    100: '#F4EFE0',
                    200: '#E8DEC4',
                    300: '#D8C9A4',
                    400: '#C8B48C',
                },
                tan: {
                    500: '#A89274',
                    600: '#8C7864',
                    700: '#6B5B4A',
                },
                ink: {
                    300: '#998A77',
                    500: '#5A4E40',
                    700: '#332A21',
                    900: '#1A1410',
                    950: '#120E0A',
                },

                // object form so `bg-rust` still works AND the tints/text-safe shade are named
                rust: {
                    DEFAULT: '#B14A2B',
                    50: '#F3E2DA',
                    200: '#E8C8BB',
                    700: '#9B3E22', // 5.36:1 on rust-50 — contrast fix for .chip-rust text
                },
                gold: {
                    DEFAULT: '#C58B2A',
                },

                // alert tints, promoted from magic hex in the design system
                warn: {
                    50: '#F6ECD4',
                    200: '#DFC98F',
                    700: '#6B5320',
                },
                danger: {
                    50: '#F6E3DB',
                    200: '#DFA98F',
                    700: '#7A2E14',
                },

                // hairlines — literal rgba, no color-mix (design system §12)
                rule: 'rgba(15,46,55,.14)',
                hairline: 'rgba(26,20,16,.12)',
                'field-line': 'rgba(26,20,16,.16)',
                'check-line': 'rgba(26,20,16,.30)',
            },

            fontFamily: {
                display: [
                    '"Geologica Variable"',
                    'Geologica',
                    '"Helvetica Neue"',
                    'system-ui',
                    'sans-serif',
                ],
                sans: [
                    '"Onest Variable"',
                    'Onest',
                    '"Helvetica Neue"',
                    'system-ui',
                    'sans-serif',
                ],
                mono: [
                    '"JetBrains Mono"',
                    'ui-monospace',
                    'SFMono-Regular',
                    'monospace',
                ],
            },

            // design system section 04 (type scale). Additive — text-sm/text-base etc. still exist.
            fontSize: {
                'display-xl': ['88px', {lineHeight: '80px', letterSpacing: '-0.015em', fontWeight: '800'}],
                'display-l': ['60px', {lineHeight: '56px', letterSpacing: '0', fontWeight: '700'}],
                'heading-m': ['34px', {lineHeight: '38px', letterSpacing: '0', fontWeight: '600'}],
                'heading-s': ['22px', {lineHeight: '28px', letterSpacing: '0', fontWeight: '600'}],
                'body-l': ['17px', {lineHeight: '26px'}],
                'body-m': ['15px', {lineHeight: '24px'}],
                caption: ['13px', {lineHeight: '20px'}],
                label: ['11px', {lineHeight: '16px', letterSpacing: '0.18em'}],
                'auth-title': ['28px', {lineHeight: '32px'}],
                btn: ['14px', {lineHeight: '20px'}],
                'btn-lg': ['16px', {lineHeight: '22px'}],
                'btn-sm': ['13px', {lineHeight: '18px'}],
                'field-label': ['11px', {lineHeight: '16px', letterSpacing: '0.14em'}],
                micro: ['11px', {lineHeight: '16px'}],
                badge: ['10px', {lineHeight: '14px', letterSpacing: '0.12em'}],
            },

            // deliberately non-colliding keys — do NOT override tracking-tight etc.
            letterSpacing: {
                'brand-tight': '-0.015em',
                'brand-snug': '-0.01em',
                'brand-body': '-0.02em',
                btn: '0.02em',
                badge: '0.12em',
                field: '0.14em',
                meta: '0.16em',
                label: '0.18em',
                eyebrow: '0.20em',
                otp: '0.40em',
            },

            // The design system OMITS these three keys — the main addition of this config.
            // NOTE: this REPLACES Tailwind's default sm/md/lg shadows project-wide,
            // making every shadow teal-tinted (rgba(15,46,55,…)), never neutral black.
            boxShadow: {
                sm: '0 1px 0 rgba(15,46,55,.06), 0 1px 2px rgba(15,46,55,.08)',
                md: '0 6px 14px -6px rgba(15,46,55,.18), 0 2px 4px rgba(15,46,55,.08)',
                lg: '0 24px 48px -20px rgba(15,46,55,.30), 0 6px 12px rgba(15,46,55,.10)',
                focus: '0 0 0 3px rgba(31,78,92,.18)',
                'focus-danger': '0 0 0 3px rgba(177,74,43,.15)',
                none: 'none',
            },

            // NOTE: redefines stock meanings — md 6px→3px, lg 8px→4px, xl 12px→6px.
            // Intentional: the brand is deliberately near-square. `rounded-full` survives.
            borderRadius: {
                DEFAULT: '2px',
                xs: '0px',
                sm: '2px',
                md: '3px',
                lg: '4px',
                xl: '6px',
                pill: '2px', // yes, 2px — the brand has no pills
            },

            spacing: {
                '4.5': '18px',
                '5.5': '22px',
                '6.5': '26px',
                '8.5': '34px',
                18: '72px',
            },
            maxWidth: {
                auth: '420px',
                page: '1240px',
                measure: '36ch',
                lead: '520px',
            },
        },
    },
    plugins: [],
};
