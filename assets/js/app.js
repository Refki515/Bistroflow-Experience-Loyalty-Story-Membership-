/**
 * BistroFlow ERP - JavaScript Utama
 * Fungsi shared: modal, toast notification, loading state tombol,
 * validasi real-time, countdown akses bahan musiman.
 * Konsisten dengan Modul 11 (Interaksi & Umpan Balik).
 */

// ============================================================
// MODAL
// ============================================================
function openModal(id) {
    const modal = document.getElementById(id);
    if (modal) modal.classList.add('show');
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if (modal) modal.classList.remove('show');
}

// Tutup modal saat klik overlay (di luar modal-box)
document.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('show');
    }
});

// ============================================================
// TOAST NOTIFICATION
// ============================================================
function showToast(message, type = 'success') {
    const existing = document.querySelector('.toast');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    const icon = type === 'success' ? 'check_circle' : 'error';
    toast.innerHTML = `<span class="material-symbols-outlined">${icon}</span><span>${message}</span>`;
    document.body.appendChild(toast);

    setTimeout(() => toast.remove(), 3000);
}

// ============================================================
// LOADING STATE PADA TOMBOL SUBMIT
// ============================================================
document.addEventListener('submit', (e) => {
    const form = e.target;
    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn && !submitBtn.disabled) {
        const label = submitBtn.querySelector('.btn-label');
        submitBtn.disabled = true;
        submitBtn.dataset.originalHtml = submitBtn.innerHTML;
        submitBtn.innerHTML = '<span class="spinner"></span> Memproses...';
    }
});

// ============================================================
// VALIDASI REAL-TIME (Modul 11: Pencegahan Kesalahan)
// ============================================================
document.addEventListener('blur', (e) => {
    const el = e.target;
    if (el.tagName !== 'INPUT') return;

    // Validasi email
    if (el.type === 'email' && el.value) {
        const valid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(el.value);
        toggleFieldError(el, valid, 'Format email tidak valid');
    }

    // Validasi nomor telepon
    if (el.name === 'nomor_telepon' && el.value) {
        const valid = /^08[0-9]{8,11}$/.test(el.value);
        toggleFieldError(el, valid, 'Nomor telepon harus diawali 08, 10-13 digit');
    }

    // Validasi password
    if (el.name === 'password' && el.value) {
        const valid = el.value.length >= 8;
        toggleFieldError(el, valid, 'Password minimal 8 karakter');
    }
}, true);

function toggleFieldError(el, isValid, message) {
    let errorEl = el.parentElement.parentElement.querySelector('.form-error-dynamic');
    if (!isValid) {
        if (!errorEl) {
            errorEl = document.createElement('p');
            errorEl.className = 'form-error form-error-dynamic';
            el.parentElement.parentElement.appendChild(errorEl);
        }
        errorEl.textContent = message;
        el.style.borderColor = 'var(--color-error)';
    } else {
        if (errorEl) errorEl.remove();
        el.style.borderColor = '';
    }
}

// ============================================================
// COUNTDOWN TIMER (untuk akses bahan musiman per tier)
// ============================================================
function initCountdowns() {
    const timers = document.querySelectorAll('.countdown-timer');
    timers.forEach((timer) => {
        let totalSeconds = parseInt(timer.dataset.seconds || '0', 10);
        if (totalSeconds <= 0) return;

        setInterval(() => {
            if (totalSeconds <= 0) return;
            totalSeconds--;
            const h = Math.floor(totalSeconds / 3600);
            const m = Math.floor((totalSeconds % 3600) / 60);
            const s = totalSeconds % 60;
            timer.textContent =
                String(h).padStart(2, '0') + ':' +
                String(m).padStart(2, '0') + ':' +
                String(s).padStart(2, '0');
        }, 1000);
    });
}

document.addEventListener('DOMContentLoaded', initCountdowns);

// ============================================================
// FILE DROP UPLOAD (Modul 5: file upload ke server PHP)
// ============================================================
document.addEventListener('change', (e) => {
    if (e.target.matches('input[type="file"]')) {
        const wrapper = e.target.closest('.file-drop');
        if (wrapper && e.target.files.length > 0) {
            const fileName = e.target.files[0].name;
            const label = wrapper.querySelector('.file-drop-label');
            if (label) label.textContent = fileName;
        }
    }
});

// ============================================================
// KONFIRMASI AKSI (Modul 11: Konfirmasi sebelum aksi permanen)
// ============================================================
function confirmAction(message) {
    return window.confirm(message);
}
