<?php
// sadigs_api_v3/admin_user_manager.php
// ALAT KHUSUS KETUA YAYASAN: Edit Data User (Nama, Username, Password)
require_once 'db_connect.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Cek Akses
$pdo = getDBConnection();
$user_id = $_SESSION['user_id'] ?? 0;
$stmt = $pdo->prepare("SELECT role_name FROM user_roles WHERE user_id = ? AND status = 'approved'");
$stmt->execute([$user_id]);
$roles = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (!in_array('Ketua Yayasan', $roles) && !in_array('Admin Sekolah', $roles) && !in_array('Kepala Sekolah', $roles)) {
    die("<h2 style='color:red;text-align:center;margin-top:50px;'>⛔ Akses Ditolak. Halaman ini khusus Ketua Yayasan/Admin.</h2>");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen User (Admin)</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background: #f4f6f9; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 25px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        h1 { color: #26667F; margin-top: 0; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; color: #555; }
        .btn { padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 0.85rem; cursor: pointer; border: none; }
        .btn-edit { background: #ffc107; color: #000; }
        .btn-save { background: #28a745; color: white; }
        .btn-cancel { background: #6c757d; color: white; }
        input.edit-input { width: 100%; padding: 5px; border: 1px solid #ccc; border-radius: 3px; }
        .hidden { display: none; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🛠️ Editor Data Pengguna</h1>
        <p>Gunakan halaman ini untuk memperbaiki nama, username, atau mereset password staf/santri.</p>
        
        <div id="loading">Memuat data...</div>
        <table id="userTable" class="hidden">
            <thead>
                <tr>
                    <th width="5%">ID</th>
                    <th width="25%">Nama Lengkap</th>
                    <th width="20%">Username</th>
                    <th width="25%">Email</th>
                    <th width="15%">Password Baru</th>
                    <th width="10%">Aksi</th>
                </tr>
            </thead>
            <tbody id="tableBody"></tbody>
        </table>
    </div>

    <script>
        let allUsers = [];

        async function loadUsers() {
            try {
                const res = await fetch('get_all_users_management.php');
                const json = await res.json();
                if (json.success) {
                    allUsers = json.data;
                    renderTable();
                    document.getElementById('loading').classList.add('hidden');
                    document.getElementById('userTable').classList.remove('hidden');
                }
            } catch (e) {
                alert('Gagal memuat data: ' + e);
            }
        }

        function renderTable() {
            const tbody = document.getElementById('tableBody');
            tbody.innerHTML = '';
            allUsers.forEach(u => {
                const tr = document.createElement('tr');
                tr.id = `row-${u.user_id}`;
                tr.innerHTML = `
                    <td>${u.user_id}</td>
                    <td>
                        <span class="view-mode">${u.full_name}</span>
                        <input type="text" class="edit-mode edit-input hidden" value="${u.full_name}" id="name-${u.user_id}">
                    </td>
                    <td>
                        <span class="view-mode"><strong>${u.username}</strong></span>
                        <input type="text" class="edit-mode edit-input hidden" value="${u.username}" id="user-${u.user_id}">
                    </td>
                    <td>
                        <span class="view-mode">${u.email || '-'}</span>
                        <input type="email" class="edit-mode edit-input hidden" value="${u.email || ''}" id="email-${u.user_id}">
                    </td>
                    <td>
                        <span class="view-mode text-gray-400">********</span>
                        <input type="text" class="edit-mode edit-input hidden" placeholder="Isi utk reset" id="pass-${u.user_id}">
                    </td>
                    <td>
                        <button class="btn btn-edit view-mode" onclick="toggleEdit(${u.user_id})">✏️ Edit</button>
                        <button class="btn btn-save edit-mode hidden" onclick="saveUser(${u.user_id})">💾 Save</button>
                        <button class="btn btn-cancel edit-mode hidden" onclick="toggleEdit(${u.user_id})">❌</button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }

        window.toggleEdit = function(id) {
            const row = document.getElementById(`row-${id}`);
            const viewModes = row.querySelectorAll('.view-mode');
            const editModes = row.querySelectorAll('.edit-mode');
            
            viewModes.forEach(el => el.classList.toggle('hidden'));
            editModes.forEach(el => el.classList.toggle('hidden'));
        }

        window.saveUser = async function(id) {
            const fullName = document.getElementById(`name-${id}`).value;
            const username = document.getElementById(`user-${id}`).value;
            const email = document.getElementById(`email-${id}`).value;
            const password = document.getElementById(`pass-${id}`).value;

            if(!fullName || !username) { alert("Nama dan Username wajib diisi!"); return; }

            const payload = {
                user_id: id,
                full_name: fullName,
                username: username,
                email: email
            };
            if(password) payload.password = password;

            try {
                const res = await fetch('edit_account.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(payload)
                });
                const json = await res.json();
                
                if(json.success) {
                    alert('✅ Berhasil disimpan!');
                    // Update tampilan lokal tanpa reload
                    loadUsers(); 
                } else {
                    alert('❌ Gagal: ' + json.message);
                }
            } catch (e) {
                alert('Error server: ' + e);
            }
        }

        loadUsers();
    </script>
</body>
</html>