<?php
// flush_cache.php – jalankan sekali, lalu hapus file demi keamanan
header('Content-Type: text/plain');

// 1. LiteSpeed Cache (if LSCache plugin installed)
if (function_exists('lscache_purge_all')) {
    lscache_purge_all();
    echo "LiteSpeed Cache purged.\n";
} else {
    // alternatif: hapus folder LSCache secara manual
    $lsCacheDir = __DIR__ . '/.lscache/';
    if (is_dir($lsCacheDir)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($lsCacheDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file) : unlink($file);
        }
        echo "LiteSpeed cache folder deleted.\n";
    } else {
        echo "LiteSpeed cache folder not found.\n";
    }
}

// 2. PHP Opcache
if (function_exists('opcache_reset')) {
    if (opcache_reset()) {
        echo "OPcache reset.\n";
    } else {
        echo "Failed to reset OPcache.\n";
    }
} else {
    echo "OPcache not enabled.\n";
}

// 3. Cloudflare (via API) – set env vars CF_TOKEN and CF_ZONE_ID on server
$cloudflareToken = getenv('CF_TOKEN');
$zoneId = getenv('CF_ZONE_ID');
if ($cloudflareToken && $zoneId) {
    $ch = curl_init("https://api.cloudflare.com/client/v4/zones/{$zoneId}/purge_cache");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer {$cloudflareToken}",
            "Content-Type: application/json"
        ],
        CURLOPT_POSTFIELDS => json_encode(['purge_everything' => true])
    ]);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($http === 200) {
        echo "Cloudflare cache purged.\n";
    } else {
        echo "Cloudflare purge failed (HTTP {$http}).\n";
    }
} else {
    echo "Cloudflare token/zone missing – skip.\n";
}
?>
