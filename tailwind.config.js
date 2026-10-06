import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    /**
     * Dark mode SENGAJA dimatikan.
     *
     * Selector `.dark` tidak pernah dipasang di HTML, jadi varian `dark:`
     * tidak akan pernah aktif — tema selalu terang walau OS pengguna memakai
     * dark mode. (Default Tailwind `'media'` justru mengikuti preferensi OS.)
     *
     * Memakai selector kustom (bukan 'class' biasa) agar seluruh kelas `dark:`
     * tetap terdeteksi dan dihapus saat build, bukan bocor ke file CSS.
     */
    darkMode: ['class', '.dark'],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
