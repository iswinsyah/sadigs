<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    $pdo->beginTransaction();

    // 1. Definisi Grup Peran (Sesuai Request)
    $all_roles = [
        'Ketua Yayasan', 'Sekretaris Yayasan', 'Bendahara Yayasan',
        'Kepala Sekolah', 'Admin Sekolah', 'Sekretaris Sekolah', 'Bendahara Sekolah',
        'Kepala Asrama Putra', 'Kepala Asrama Putri',
        'Musyrif', 'Musyrifah', 'Ustadz', 'Ustadzah',
        'Santri Rijal', "Santri Nisa'", 'Walisantri'
    ];

    $groups = [
        'ManajemenYayasan' => ['Ketua Yayasan', 'Sekretaris Yayasan', 'Bendahara Yayasan'],
        'ManajemenSekolah' => ['Kepala Sekolah', 'Kepala Asrama Putra', 'Kepala Asrama Putri', 'Admin Sekolah'],
        'AdministrasiPegawai' => ['Kepala Sekolah', 'Admin Sekolah', 'Kepala Asrama Putra', 'Kepala Asrama Putri', 'Musyrif', 'Musyrifah', 'Ustadz', 'Ustadzah']
    ];

    // 2. Pastikan Kategori Ada di Database
    $categories_to_ensure = [
        'Umum' => '1. UMUM',
        'ManajemenYayasan' => '2. MANAJEMEN YAYASAN',
        'ManajemenSekolah' => '3. MANAJEMEN SEKOLAH',
        'AdministrasiPegawai' => '4. ADMINISTRASI PEGAWAI'
    ];

    // Tambahkan kategori untuk setiap peran individual (untuk fallback)
    foreach ($all_roles as $role) {
        // Buat ID kategori dari nama role (hapus spasi/karakter aneh)
        $cat_id = str_replace([' ', "'"], '', $role);
        $categories_to_ensure[$cat_id] = strtoupper($role);
    }

    $sort_order = 1;
    foreach ($categories_to_ensure as $id => $label) {
        $stmt = $pdo->prepare("INSERT INTO menu_categories (category_id, label, sort_order) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE label = VALUES(label), sort_order = VALUES(sort_order)");
        $stmt->execute([$id, $label, $sort_order++]);
    }

    // 3. Ambil Semua Menu dan Izinnya
    $stmt = $pdo->query("SELECT menu_id FROM menus");
    $menus = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($menus as $menu_id) {
        // Ambil role yang diizinkan untuk menu ini
        $stmtPerms = $pdo->prepare("SELECT role_name FROM menu_permissions WHERE menu_id = ? AND is_allowed = 1");
        $stmtPerms->execute([$menu_id]);
        $allowed_roles = $stmtPerms->fetchAll(PDO::FETCH_COLUMN);

        $target_category = null;

        // ATURAN 1: UMUM (Semua role dicentang)
        // Kita anggap "Semua" jika jumlah role yang diizinkan >= jumlah total role - 2 (toleransi sedikit jika ada role baru)
        // Atau strict: count($allowed_roles) == count($all_roles)
        // Mari gunakan strict intersection untuk akurasi
        if (count(array_intersect($all_roles, $allowed_roles)) == count($all_roles)) {
            $target_category = 'Umum';
        }
        
        // Jika belum masuk Umum, cek aturan grup
        if (!$target_category) {
            foreach ($groups as $cat_id => $group_roles) {
                // Cek apakah SEMUA role dalam grup ini ada di allowed_roles
                $intersection = array_intersect($group_roles, $allowed_roles);
                
                // Syarat: Jumlah irisan harus SAMA dengan jumlah anggota grup
                // DAN (Opsional: tidak boleh ada role lain? Request bilang "memiliki centang di ... secara bersamaan". 
                // Biasanya ini berarti "minimal role ini". Tapi agar rapi, kita prioritaskan grup terbesar dulu).
                
                // Kita urutkan pengecekan dari grup terbesar (Administrasi Pegawai) ke terkecil di loop ini?
                // Array $groups di atas urutannya: Yayasan (3), Sekolah (4), Pegawai (8).
                // Sebaiknya kita cek yang paling spesifik/besar dulu agar tidak salah masuk.
                // Tapi request user urutannya 2, 3, 4. Mari kita ikuti logika "Best Fit".
                
                if (count($intersection) == count($group_roles)) {
                    // Jika menu ini punya akses untuk grup ini, kita tetapkan.
                    // Note: Jika satu menu memenuhi syarat untuk 2 grup (jarang terjadi dengan definisi grup di atas), yang terakhir di loop menang.
                    // Mari kita balik urutan $groups agar yang paling besar (Pegawai) dicek duluan atau belakangan?
                    // Request:
                    // 2. Yayasan (Ketua, Sek, Ben)
                    // 3. Sekolah (Kepsek, KA Putra, KA Putri, Admin)
                    // 4. Pegawai (8 role)
                    
                    // Jika menu dicentang untuk 8 role pegawai, dia masuk Administrasi Pegawai.
                    // Apakah 8 role ini mencakup 4 role sekolah? YA. (Kepsek, Admin, KA Putra, KA Putri ada di keduanya).
                    // Jadi jika kita cek Sekolah dulu, dia akan masuk Sekolah. Padahal harusnya Pegawai.
                    // SOLUSI: Cek grup dengan jumlah anggota terbanyak dulu (Administrasi Pegawai).
                    
                    $target_category = $cat_id;
                }
            }
            
            // Fix prioritas: Cek manual urutan prioritas jika tertimpa
            $is_pegawai = count(array_intersect($groups['AdministrasiPegawai'], $allowed_roles)) == count($groups['AdministrasiPegawai']);
            if ($is_pegawai) $target_category = 'AdministrasiPegawai';
        }

        // ATURAN FALLBACK (Di luar 4 poin di atas)
        if (!$target_category && count($allowed_roles) > 0) {
            // Masukkan ke Div peran pertama yang ditemukan
            // Contoh: Dicentang Kepala Sekolah & Ketua Yayasan.
            // Kita masukkan ke Ketua Yayasan (jika urutan array duluan) atau Kepala Sekolah.
            // Ini keterbatasan sistem 1 menu = 1 kategori.
            $first_role = $allowed_roles[0];
            $target_category = str_replace([' ', "'"], '', $first_role);
        }

        // Update Kategori Menu
        if ($target_category) {
            $stmtUpdate = $pdo->prepare("UPDATE menus SET category_id = ? WHERE menu_id = ?");
            $stmtUpdate->execute([$target_category, $menu_id]);
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Susunan menu berhasil diatur ulang otomatis berdasarkan izin akses.']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Gagal: ' . $e->getMessage()]);
}
?>