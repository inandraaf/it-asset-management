import './bootstrap';

import Alpine from 'alpinejs';
import {
    // Objek utama Chart (WAJIB diimpor — tanpa ini bundle melempar
    // "Chart is not defined" dan mematikan SELURUH JavaScript halaman).
    Chart,
    // Elemen grafik
    ArcElement,
    BarElement,
    CategoryScale,
    Legend,
    LinearScale,
    Tooltip,
    // Controller: WAJIB didaftarkan, jika tidak Chart.js v4 melempar
    // "doughnut is not a registered controller".
    BarController,
    DoughnutController,
} from 'chart.js';
import Swal from 'sweetalert2';

Chart.register(
    // Controller
    BarController,
    DoughnutController,
    // Elemen & skala
    ArcElement,
    BarElement,
    CategoryScale,
    LinearScale,
    Legend,
    Tooltip,
);

// Dipakai oleh skrip inline di dashboard.blade.php.
window.Chart = Chart;

window.Alpine = Alpine;
window.Swal = Swal;

/**
 * Helper: inisialisasi chart saat elemen ada di DOM.
 *
 * @param {string} canvasId
 * @param {object} config
 */
window.initItamChart = (canvasId, config) => {
    const canvas = document.getElementById(canvasId);

    if (!canvas) {
        return;
    }

    // Cegah chart ganda bila skrip dijalankan ulang.
    if (Chart.getChart(canvas)) {
        return;
    }

    new Chart(canvas, config);
};

/**
 * Konfirmasi aksi destruktif memakai SweetAlert2.
 *
 * Semua form dengan atribut `data-confirm` dicegat secara global, sehingga
 * view tidak perlu menulis handler sendiri dan tidak ada lagi `confirm()`
 * bawaan browser.
 *
 * @param {HTMLFormElement} form
 */
window.itamConfirm = (form) => {
    const message = form.dataset.confirm || 'Lanjutkan aksi ini?';
    const confirmText = form.dataset.confirmButton || 'Ya, lanjutkan';

    Swal.fire({
        title: 'Konfirmasi',
        text: message,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: confirmText,
        cancelButtonText: 'Batal',
        confirmButtonColor: '#e11d48',
        cancelButtonColor: '#64748b',
        reverseButtons: true,
        focusCancel: true,
    }).then((result) => {
        if (result.isConfirmed) {
            // submit() tanpa memicu event submit lagi (hindari loop).
            form.submit();
        }
    });
};

// Delegasi global: cukup pasang `data-confirm` pada form.
document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-confirm')) {
        return;
    }

    event.preventDefault();
    window.itamConfirm(form);
});

Alpine.start();
