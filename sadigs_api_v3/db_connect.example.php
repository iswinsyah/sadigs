<?php
// =================================================================
// SADIGS 3.0: CONTOH FILE KONEKSI DATABASE (PDO)
// Salin file ini menjadi 'db_connect.php' dan isi dengan kredensial server Anda.
// =================================================================

// PENTING: Ganti nilai-nilai ini dengan kredensial database Anda yang sebenarnya.
define('DB_HOST', 'localhost'); // Biasanya 'localhost' untuk Hostinger
define('DB_USER', 'u829486010_sadigsmysql'); // Username database dari hPanel
define('DB_PASS', 'Khilafet@1924'); // Password database dari hPanel
define('DB_NAME', 'u829486010_sadigsdatabase'); // Nama database dari hPanel

function getDBConnection() {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    
    $options = array(
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, 
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,     
        PDO::ATTR_EMULATE_PREPARES   => false                
    );
    
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (\PDOException $e) {
        // Ini akan menghentikan eksekusi dan mengirim error 500 yang jelas
        // Log error ke file di server untuk debugging, tanpa menampilkannya ke user.
        // Pastikan folder root web Anda memiliki izin tulis untuk file php_error.log.
        $log_path = $_SERVER['DOCUMENT_ROOT'] . '/php_error.log';
        error_log("PDO Connection Error: " . $e->getMessage(), 3, $log_path);

        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(array(
            'success' => false, 
            'message' => 'Koneksi database gagal total.'
        ));
        exit;
    }
}

function sendJSONResponse($data, $statusCode = 200) {
    // Fungsi ini dipanggil dari file API setelah require_once db_connect.php
    header('Content-Type: application/json');
    http_response_code($statusCode);
    echo json_encode($data);
}