<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/bootstrap.php';

$base = (string) config('scraper.base_url');
foreach (['/api/v1/jobs', '/download?id=not-a-real-job'] as $path) {
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => 'AtlasGlobalBusinessAdmin/1.0 (recon)',
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $error = curl_error($ch);
    echo "=== GET {$path} ===\n";
    echo "HTTP {$status} | {$type}" . ($error ? " | ERROR: {$error}" : '') . "\n";
    echo substr((string) $body, 0, 1200) . "\n\n";
}
