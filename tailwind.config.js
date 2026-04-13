import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', 'Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                brand: {
                    50:  '#fff7ed',
                    100: '#ffedd5',
                    200: '#fed7aa',
                    400: '#fb923c',
                    500: '#f97316',
                    600: '#ea6b0a',
                    700: '#c2550a',
                },
                navy: {
                    50:  '#eef1f7',
                    100: '#d5dcf0',
                    600: '#1e3a5f',
                    700: '#1b2b4b',
                    800: '#152240',
                    900: '#0f1a30',
                },
            },
        },
    },
    plugins: [],
};
