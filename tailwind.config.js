import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import { variabelTema, warnaTema } from './tailwind.tema';

/** @type {import('tailwindcss').Config} */
export default {
    // Kelas "dark" pada <html> diatur tombol tema; lihat resources/js/tema.js.
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                // Serif untuk judul dan label grup: nuansa naskah dinas perguruan tinggi.
                serif: ['"Source Serif 4"', 'Georgia', ...defaultTheme.fontFamily.serif],
            },
            colors: {
                // Aksen biru navy; menimpa "indigo" bawaan Breeze agar semua komponen ikut.
                indigo: {
                    50: '#eef2f9',
                    100: '#dbe4f3',
                    200: '#b9c9e6',
                    300: '#8ea5d2',
                    400: '#5f7bb8',
                    500: '#3f5c9d',
                    600: '#2c4682',
                    700: '#1e3368',
                    800: '#162752',
                    900: '#0f1c3c',
                },
            },
            ...warnaTema,
        },
    },

    plugins: [forms, variabelTema],
};
