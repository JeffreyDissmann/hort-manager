import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
                // Playful hand-drawn display font, used for the brand wordmark + big headings.
                display: ['Barriecito', ...defaultTheme.fontFamily.sans],
            },

            // The entire palette is driven by CSS variables (defined in resources/css/app.css),
            // so both the light/dark scheme AND per-theme colour tuning happen in ONE place —
            // component markup never needs to change again. Channels are space-separated RGB so
            // Tailwind's `<alpha-value>` opacity modifiers keep working (e.g. `bg-ink/5`).
            //
            // Two layers:
            //   • Semantic neutrals — swap between light and dark:
            //       canvas   page background
            //       surface  cards / raised panels
            //       ink      primary text & borders
            //   • Brand hues (Schwabinger Schülerladen) — tunable per theme, but by convention
            //     `hort-navy` stays dark in both themes: it is the app chrome (nav, dark buttons)
            //     AND the correct text colour ON solid accent backgrounds. Text on a neutral
            //     surface uses `ink`; text on a brand background uses `hort-navy`/`white`.
            colors: {
                canvas: 'rgb(var(--color-canvas) / <alpha-value>)',
                surface: 'rgb(var(--color-surface) / <alpha-value>)',
                ink: 'rgb(var(--color-ink) / <alpha-value>)',
                hort: {
                    navy: 'rgb(var(--color-navy) / <alpha-value>)',
                    'navy-dark': 'rgb(var(--color-navy-dark) / <alpha-value>)',
                    blue: 'rgb(var(--color-blue) / <alpha-value>)',
                    teal: 'rgb(var(--color-teal) / <alpha-value>)',
                    'teal-dark': 'rgb(var(--color-teal-dark) / <alpha-value>)',
                    purple: 'rgb(var(--color-purple) / <alpha-value>)',
                },
            },
        },
    },

    plugins: [forms],
};
