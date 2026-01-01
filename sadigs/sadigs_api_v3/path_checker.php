<?php
// File: path_checker.php
// Letakkan file ini di dalam folder sadigs_api_v3
header('Content-Type: text/html; charset=utf-8');
echo "<h1>SADIGS Path Checker</h1>";
echo "<p>Jika Anda bisa melihat halaman ini, berarti server berhasil menemukan file PHP.</p>";
echo "<hr>";

echo "<h2>Informasi Server:</h2>";
echo "<ul>";
echo "<li><strong>Lokasi File Ini (Absolute Path):</strong><br><code>" . __FILE__ . "</code></li>";
echo "<li><strong>Folder 'Rumah' Domain Ini (Document Root):</strong><br><code>" . $_SERVER['DOCUMENT_ROOT'] . "</code></li>";
echo "<li><strong>Path URL yang Diminta:</strong><br><code>" . $_SERVER['REQUEST_URI'] . "</code></li>";
echo "</ul>";
echo "<hr>";

echo "<h2>Analisa:</h2>";
echo "<p>Bandingkan <strong>Document Root</strong> dengan <strong>Absolute Path</strong>. Path file Anda harus berada di dalam Document Root agar bisa diakses.</p>";
?>