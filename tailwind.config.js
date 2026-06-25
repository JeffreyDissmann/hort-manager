import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
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
            // Schwabinger Schülerladen brand palette.
            colors: {
                hort: {
                    navy: '#223E55',
                    'navy-dark': '#18293F',
                    blue: '#244C71',
                    teal: '#6AC2C7',
                    'teal-dark': '#4FA8AD',
                    purple: '#895B9E',
                    sand: '#F7F5F0',
                },
            },
        },
    },

    plugins: [forms],
};
