// Konfigurasi API
const API_BASE_URL = 'api.php';

// Fungsi untuk mengambil data dari API
async function fetchData(action, params = {}) {
    const urlParams = new URLSearchParams({action, ...params});
    const response = await fetch(`${API_BASE_URL}?${urlParams}`);
    return await response.json();
}

// Fungsi untuk mengirim data ke API
async function postData(action, data) {
    const response = await fetch(API_BASE_URL, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({action, ...data})
    });
    return await response.json();
}

// Fungsi untuk memeriksa status autentikasi
function checkAuth() {
    // Diimplementasikan sesuai kebutuhan (bisa menggunakan session atau token)
}

// Fungsi untuk logout
function logout() {
    if (confirm('Apakah Anda yakin ingin keluar?')) {
        window.location.href = 'logout.php';
    }
}

// Inisialisasi event listener untuk modal
function initModalListeners() {
    // Tutup modal ketika klik tombol close
    document.querySelectorAll('.modal .close').forEach(button => {
        button.addEventListener('click', function() {
            this.closest('.modal').style.display = 'none';
        });
    });
    
    // Tutup modal ketika klik di luar konten modal
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.style.display = 'none';
            }
        });
    });
}

// Format tanggal
function formatDate(dateString) {
    const options = { day: 'numeric', month: 'long', year: 'numeric' };
    const date = new Date(dateString);
    return date.toLocaleDateString('id-ID', options);
}

// Format waktu (contoh: "5 menit yang lalu")
function formatTimeAgo(timestamp) {
    const now = new Date();
    const past = new Date(timestamp);
    const diff = now - past;
    
    const seconds = Math.floor(diff / 1000);
    const minutes = Math.floor(seconds / 60);
    const hours = Math.floor(minutes / 60);
    const days = Math.floor(hours / 24);
    
    if (days > 0) return `${days} hari yang lalu`;
    if (hours > 0) return `${hours} jam yang lalu`;
    if (minutes > 0) return `${minutes} menit yang lalu`;
    return `${seconds} detik yang lalu`;
}