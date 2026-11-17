import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

const token = (name) => `rgb(var(--${name}) / <alpha-value>)`;

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            // Colours are CSS variables (see resources/css/app.css) so dark mode is one place.
            colors: {
                page: token('page'),
                surface: token('surface'),
                ink: token('ink'),
                muted: token('muted'),
                line: token('line'),
                accent: token('accent'),
                'accent-ink': token('accent-ink'),
                good: token('good'),
                bad: token('bad'),
                warn: token('warn'),
            },
            fontFamily: {
                sans: ['"Inter Variable"', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
