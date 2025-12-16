/**
 * =================================================================
 * SADIGS 3.0: LOGIKA UTAMA APLIKASI (Front-end)
 * Menggunakan jalur API RELATIF setelah lingkungan server dibersihkan dari WordPress.
 * =================================================================
 */

// JALUR API RELATIF: Ini adalah jalur yang paling stabil jika root server sudah bersih.
const API_BASE_URL = '/sadigs_api_v3/'; 

// UTILITY FUNCTION (fungsi displayMessage dihilangkan untuk fokus pada logika utama)
function displayMessage(message, type, targetId = 'msg-box') {
    const messageDiv = document.getElementById(targetId);
    if (!messageDiv) return;

    messageDiv.style.display = 'none';
    messageDiv.classList.remove('error', 'success', 'loading');
    messageDiv.innerHTML = '';
    
    messageDiv.textContent = message;
    
    if (type === 'success') {
        messageDiv.classList.add('success');
    } else if (type === 'error') {
        messageDiv.classList.add('error');
    }

    messageDiv.style.display = 'block';
}


document.addEventListener('DOMContentLoaded', () => {
    
    // =================================================================
    // 1. LOGIKA LOGIN (sadigs/index.html)
    // =================================================================
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;

            displayMessage('Memproses login...', 'success'); // Menggunakan 'success' sementara untuk tampilan loading

            if (username === "" || password === "") {
                displayMessage('Username dan Kata Sandi wajib diisi.', 'error');
                return;
            } else if (password.length < 8) {
                displayMessage('Kata sandi minimal 8 karakter.', 'error');
                return;
            }

            try {
                // Perlu disesuaikan jalur API ke root relatif
                const response = await fetch(API_BASE_URL + 'login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username, password }),
                    cache: 'no-cache' 
                });
                
                // Pemeriksaan kritis untuk menangkap respons non-JSON
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                     // Ini menangkap error 404/500 yang mengembalikan HTML/Plain Text
                     throw new Error('Respons bukan JSON. Server mengembalikan Error HTML atau API tidak ditemukan.');
                }
                
                const data = await response.json();

                if (response.ok && data.success) {
                    displayMessage(`Login berhasil! Mengalihkan...`, 'success');
                    
                    setTimeout(() => {
                        // Jalur pengalihan sudah diatur di login.php ke ../dashboard.html
                        window.location.href = data.redirect_path || 'dashboard.html'; 
                    }, 1000);
                    
                } else {
                    displayMessage(data.message || 'Login gagal. Coba lagi.', 'error');
                }

            } catch (error) {
                console.error('Error during login fetch:', error);
                displayMessage(`GAGAL KONEKSI SERVER: ${error.message}`, 'error');
            }
        });
    }

    // =================================================================
    // 2. LOGIKA SIGNUP (sadigs/signup.html)
    // =================================================================
    const signupForm = document.getElementById('signup-form');
    if (signupForm) {

        // Fungsi untuk memuat status kuota dan menyesuaikan UI
        async function loadAndApplyQuotas() {
            try {
                const response = await fetch(API_BASE_URL + 'quota.php', { cache: 'no-cache' });
                const data = await response.json();

                if (data.success && data.quotas) {
                    data.quotas.forEach(quota => {
                        // Jika kuota untuk peran ini sudah penuh
                        if (quota.is_full) {
                            // Cari checkbox yang sesuai dengan role_name
                            const checkbox = document.querySelector(`input[name="role"][value="${quota.role_name}"]`);
                            if (checkbox) {
                                // Sembunyikan seluruh elemen <label> yang membungkus checkbox
                                checkbox.parentElement.style.display = 'none';
                            }
                        }
                    });
                }
            } catch (error) {
                console.error('Gagal memuat data kuota:', error);
                // Jika gagal, tidak melakukan apa-apa, biarkan semua checkbox terlihat
            }
        }

        signupForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            const username = document.getElementById('username').value.trim();
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const selectedRoles = Array.from(document.querySelectorAll('input[name="role"]:checked')).map(cb => cb.value);

            displayMessage('Memproses pendaftaran...', 'success'); 
            
            if (username === "" || email === "" || password === "" || selectedRoles.length === 0) {
                displayMessage('Semua bidang (termasuk peran) wajib diisi.', 'error');
                return;
            }
            if (password.length < 8) {
                displayMessage('Kata sandi minimal 8 karakter.', 'error');
                return;
            }

            try {
                const response = await fetch(API_BASE_URL + 'signup.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username, email, password, roles: selectedRoles }),
                    cache: 'no-cache'
                });

                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                     throw new Error('Respons bukan JSON. Server mengembalikan Error HTML atau API tidak ditemukan.');
                }
                
                const data = await response.json();

                if (response.ok && data.success) {
                    displayMessage(data.message || 'Pendaftaran berhasil. Silakan login.', 'success');
                    // Opsional: Redirect ke halaman login setelah beberapa detik
                    setTimeout(() => {
                        window.location.href = 'index.html'; 
                    }, 3000);
                } else {
                    displayMessage(data.message || 'Pendaftaran gagal.', 'error');
                }

            } catch (error) {
                console.error('Error during signup fetch:', error);
                displayMessage(`GAGAL KONEKSI SERVER: ${error.message}`, 'error');
            }
        });

        // Panggil fungsi untuk memuat kuota saat halaman signup dimuat
        loadAndApplyQuotas();
    }


    // =================================================================
    // 3. LOGIKA DASHBOARD (sadigs/dashboard.html) - Cek Sesi dan Logout
    // =================================================================
    const sidebarUsername = document.getElementById('sidebar-username');
    
    // Logika ini hanya berjalan jika elemen dashboard ada
    if (sidebarUsername) {
        
        // Fungsi untuk mengurus navigasi (jika menggunakan dashboard.html sederhana)
        async function checkSession() {
            try {
                // Memanggil session_check.php untuk mendapatkan status sesi
                const response = await fetch(API_BASE_URL + 'session_check.php', { cache: 'no-cache' });
                const data = await response.json();

                if (data.success) {
                    sidebarUsername.textContent = data.username;
                    // Lanjutkan rendering dashboard di dashboard.html
                    // Catatan: Logika rendering dashboard dinamis sudah dipindahkan ke dashboard.html itu sendiri
                } else {
                    // Redirect ke halaman login jika sesi tidak valid
                    window.location.href = 'index.html'; 
                }
            } catch (error) {
                console.error("Session check failed, redirecting to login:", error);
                // Jika koneksi ke API gagal, paksa redirect ke login
                window.location.href = 'index.html'; 
            }
        }
        
        checkSession(); 
    }
    
    // =================================================================
    // 4. LOGIKA LOGOUT (Hanya untuk tombol Logout)
    // =================================================================
    const logoutButtonGlobal = document.getElementById('logout-btn');
    if (logoutButtonGlobal) {
        logoutButtonGlobal.addEventListener('click', () => {
            // Arahkan ke endpoint logout.php
            window.location.href = API_BASE_URL + 'logout.php';
        });
    }

});