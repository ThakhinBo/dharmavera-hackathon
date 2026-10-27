<?php
// core_init.php — Centralized Security, Sessions, and DB Connection

const SITE_ORIGIN = 'https://southrule.com';
const SITE_DOMAIN = 'southrule.com';
const SUPPORT_EMAIL = 'app@southrule.com';

// 1. Secure Session Initialization (Only start if not already started)
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

// 2. Global Security Headers
header_remove('X-Powered-By');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: SAMEORIGIN');
header('Strict-Transport-Security: max-age=31536000');
header('Permissions-Policy: geolocation=(), camera=(), microphone=()');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; font-src 'self' https://cdn.jsdelivr.net https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self' https://nominatim.openstreetmap.org;");

// 3. Timezone Setting
date_default_timezone_set('UTC');

// 4. Database Connection
require_once dirname(__DIR__) . '/db_config.php'; // /home/<site>/db_config.php, outside the web root
$db_ok = isset($conn) && !($conn instanceof mysqli && $conn->connect_error);

// 5. Server-side rate limiting shared by sign-in, the oracle API and the admin panel.
//    Counts are stored in the database, so clearing cookies does not reset them.
//    Returns false once $max hits have been made for $bucket inside $windowSeconds.
function dv_throttle(mysqli $conn, string $bucket, int $max, int $windowSeconds): bool {
    $bucket = substr($bucket, 0, 120);
    $now = time();
    $s = $conn->prepare("INSERT INTO security_throttle (bucket, hits, window_start) VALUES (?, 1, ?)
        ON DUPLICATE KEY UPDATE
          hits = IF(window_start < ? - ?, 1, hits + 1),
          window_start = IF(window_start < ? - ?, ?, window_start)");
    if (!$s) return true;   // never lock people out because of a throttle-table problem
    $s->bind_param('siiiiii', $bucket, $now, $now, $windowSeconds, $now, $windowSeconds, $now);
    $s->execute();
    $r = $conn->prepare("SELECT hits FROM security_throttle WHERE bucket=?");
    $r->bind_param('s', $bucket); $r->execute();
    $hits = (int)($r->get_result()->fetch_assoc()['hits'] ?? 0);
    if (random_int(1, 200) === 1) $conn->query("DELETE FROM security_throttle WHERE window_start < " . ($now - 86400));
    return $hits <= $max;
}
