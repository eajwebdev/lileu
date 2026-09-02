import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.jsx',
    ],

    theme: {
        extend: {
            colors: {
                // Lil'Eu brand palette — chocolate / cream / blush.
                chocolate: {
                    50: '#F6F1EE',
                    100: '#E7DCD5',
                    200: '#CDBAAE',
                    300: '#AE9384',
                    400: '#8A6B5A',
                    500: '#6B5044',
                    600: '#523C33',
                    DEFAULT: '#3B2A22',
                    700: '#3B2A22',
                    800: '#2B1B16',
                    900: '#1D1210',
                },
                blush: {
                    50: '#FDF5F6',
                    100: '#FBE9EC',
                    200: '#F7CED3',
                    300: '#EFAFB8',
                    DEFAULT: '#EFAFB8',
                    400: '#E28D99',
                    500: '#CE6D7C',
                    600: '#B05061',
                },
                cream: {
                    50: '#FDFAF5',
                    100: '#FBF5EC',
                    DEFAULT: '#F5E8D9',
                    200: '#F5E8D9',
                    300: '#EBD8C3',
                    400: '#DCC2A7',
                },
                vanilla: '#FFFDFC',
                cherry: {
                    DEFAULT: '#C71827',
                    soft: '#E0505C',
                    dark: '#9C111D',
                },
                caramel: {
                    DEFAULT: '#D7973E',
                    soft: '#E7BA7B',
                    dark: '#AC7526',
                },
                success: {
                    DEFAULT: '#2F7D4A',
                    soft: '#5AA372',
                    light: '#E4F1E8',
                },
                amberstatus: '#B7791F',
            },
            fontFamily: {
                sans: ['"Plus Jakarta Sans"', ...defaultTheme.fontFamily.sans],
                display: ['Fraunces', 'Georgia', ...defaultTheme.fontFamily.serif],
                receipt: ['"Plus Jakarta Sans"', ...defaultTheme.fontFamily.sans],
            },
            borderRadius: {
                '4xl': '2rem',
            },
            boxShadow: {
                soft: '0 2px 10px -2px rgba(59, 42, 34, 0.08), 0 8px 24px -12px rgba(59, 42, 34, 0.12)',
                lift: '0 10px 30px -12px rgba(59, 42, 34, 0.28)',
                inset_cream: 'inset 0 1px 0 0 rgba(255, 253, 252, 0.6)',
            },
            backgroundImage: {
                'cream-fade': 'linear-gradient(180deg, #FBF5EC 0%, #F5E8D9 100%)',
                'choco-fade': 'linear-gradient(135deg, #3B2A22 0%, #2B1B16 100%)',
            },
            keyframes: {
                'fade-up': {
                    '0%': { opacity: '0', transform: 'translateY(12px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
            },
            animation: {
                'fade-up': 'fade-up .5s ease-out both',
            },
        },
    },

    plugins: [forms],
};
