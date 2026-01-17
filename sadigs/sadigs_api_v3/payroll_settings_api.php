<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

// Cek Akses (Hanya Ketua Yayasan & Bendahara)
$allowed = ['Ketua Yayasan', 'Bendahara Yayasan'];
$user_roles = $_SESSION['roles'] ?? [];
if (empty(array_intersect($allowed, $user_roles))) {
    sendJSONResponse(['success' => false, 'message' => 'Akses ditolak.'], 403);
}

$pdo = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // 1. Ambil Config Global
        $config = $pdo->query("SELECT * FROM payroll_config")->fetchAll(PDO::FETCH_KEY_PAIR);

        // 2. Ambil Standar Gaji per Role (Gaji Pokok & Tunjangan Jabatan)
        $roles = ['Kepala Sekolah', 'Sekretaris Sekolah', 'Bendahara Sekolah', 'Musyrif', 'Ustadz', 'Kepala Asrama Putra', 'Kepala Asrama Putri'];
        $standards = [];
        
        foreach ($roles as $role) {
            // Ambil Gaji Pokok
            $stmt = $pdo->prepare("SELECT amount FROM salary_standards WHERE role_name = ? AND component_id = (SELECT id FROM salary_components WHERE name = 'Gaji Pokok')");
            $stmt->execute([$role]);
            $gapok = $stmt->fetchColumn() ?: 0;

            // Ambil Tunjangan Jabatan
            $stmt = $pdo->prepare("SELECT amount FROM salary_standards WHERE role_name = ? AND component_id = (SELECT id FROM salary_components WHERE name = 'Tunjangan Jabatan')");
            $stmt->execute([$role]);
            $tunjangan = $stmt->fetchColumn() ?: 0;

            $standards[$role] = ['gapok' => $gapok, 'tunjangan' => $tunjangan];
        }

        // 3. Ambil Aturan Tarif Mengajar (Teaching Rates)
        $rates = $pdo->query("SELECT * FROM teaching_rate_rules")->fetchAll(PDO::FETCH_ASSOC);
        $rateMap = [];
        foreach ($rates as $r) {
            $rateMap[$r['role_name']] = $r;
        }

        sendJSONResponse([
            'success' => true,
            'config' => $config,
            'standards' => $standards,
            'rates' => $rateMap
        ]);

    } catch (Exception $e) {
        sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    try {
        $pdo->beginTransaction();

        // 1. Simpan Config Global
        if (isset($input['config'])) {
            $stmt = $pdo->prepare("INSERT INTO payroll_config (config_key, config_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)");
            foreach ($input['config'] as $key => $val) {
                $stmt->execute([$key, $val]);
            }
        }

        // 2. Simpan Standar Gaji (Gapok & Tunjangan)
        if (isset($input['standards'])) {
            $compGapok = $pdo->query("SELECT id FROM salary_components WHERE name = 'Gaji Pokok'")->fetchColumn();
            $compTunjangan = $pdo->query("SELECT id FROM salary_components WHERE name = 'Tunjangan Jabatan'")->fetchColumn();

            $stmtStd = $pdo->prepare("INSERT INTO salary_standards (role_name, component_id, amount) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE amount = VALUES(amount)");
            
            foreach ($input['standards'] as $role => $vals) {
                // Update Gapok
                $pdo->prepare("DELETE FROM salary_standards WHERE role_name = ? AND component_id = ?")->execute([$role, $compGapok]);
                $stmtStd->execute([$role, $compGapok, $vals['gapok']]);
                
                // Update Tunjangan
                $pdo->prepare("DELETE FROM salary_standards WHERE role_name = ? AND component_id = ?")->execute([$role, $compTunjangan]);
                $stmtStd->execute([$role, $compTunjangan, $vals['tunjangan']]);
            }
        }

        // 3. Simpan Tarif Mengajar
        if (isset($input['rates'])) {
            $stmtRate = $pdo->prepare("INSERT INTO teaching_rate_rules (role_name, tier_1_limit, tier_1_rate, tier_2_rate) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE tier_1_limit=VALUES(tier_1_limit), tier_1_rate=VALUES(tier_1_rate), tier_2_rate=VALUES(tier_2_rate)");
            foreach ($input['rates'] as $role => $r) {
                $stmtRate->execute([$role, $r['limit'], $r['rate1'], $r['rate2']]);
            }
        }

        $pdo->commit();
        sendJSONResponse(['success' => true, 'message' => 'Pengaturan gaji berhasil disimpan.']);

    } catch (Exception $e) {
        $pdo->rollBack();
        sendJSONResponse(['success' => false, 'message' => 'Gagal menyimpan: ' . $e->getMessage()], 500);
    }
}
?>