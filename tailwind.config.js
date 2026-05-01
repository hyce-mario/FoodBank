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
    safelist: [
        // Finance module — type/status badge classes set via PHP methods
        'bg-green-100','text-green-700',
        'bg-red-100','text-red-700',
        'bg-amber-100','text-amber-700',
        'bg-gray-100','text-gray-500',
        // Event-day role accent colors — used via PHP variables, must be explicit
        'bg-blue-600','bg-blue-700','bg-blue-50','bg-blue-100',
        'bg-purple-600','bg-purple-700','bg-purple-50','bg-purple-100',
        'bg-orange-500','bg-orange-600','bg-orange-50','bg-orange-100',
        'bg-green-600','bg-green-700','bg-green-50','bg-green-100',
        'text-blue-600','text-blue-700',
        'text-purple-600','text-purple-700',
        'text-orange-500','text-orange-600','text-orange-700',
        'text-green-600','text-green-700',
        'border-blue-200','border-blue-300','border-blue-500',
        'border-purple-200','border-purple-300',
        'border-orange-200','border-orange-300',
        'border-green-200','border-green-300',
        'ring-blue-400','ring-purple-400','ring-orange-400','ring-green-400',
        'hover:bg-blue-700','hover:bg-purple-700','hover:bg-orange-600','hover:bg-green-700',
        'bg-navy-700','bg-navy-800','bg-navy-900','bg-navy-50','bg-navy-100',
        'hover:bg-navy-800','active:bg-navy-900','text-navy-700','border-navy-100',
        // Settings page 2-column layout
        'lg:grid-cols-[220px_1fr]',
    ],
    plugins: [],
};
