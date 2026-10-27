<?php
/*
 * DharmaVera oracle API — the only public door to the private oracle engine.
 *
 *   POST action=draw     d1..d4, omen, mins (visitor's local minutes of day), question
 *   POST action=current  the visitor's reading from the last 90 minutes (summary)
 *   POST action=detail   full reading — signed-in members only, their own reading
 *
 * Guests only ever receive names and scores. Full texts go to a signed-in member
 * for their own current reading. A reading is kept server-side for 90 minutes and
 * tied to this browser by a random HttpOnly cookie (dv_rd); draws are limited per
 * network. Readings are purged after two hours.
 */
require_once __DIR__ . '/core_init.php';
require_once dirname(__DIR__) . '/dv_private/oracle_engine.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

const DV_COOLDOWN_MS = 90 * 60 * 1000;

function dv_out(array $a): void { echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); dv_out(['error' => 'POST only']); }
if (!$db_ok) dv_out(['error' => 'Service unavailable']);

// Same-site request + CSRF token (issued by index.php / biorhythm.php)
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && $origin !== SITE_ORIGIN) { http_response_code(403); dv_out(['error' => 'Forbidden']); }
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)($_POST['csrf'] ?? ''))) {
    http_response_code(403); dv_out(['error' => 'Session expired — please reload the page.']);
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if (!dv_throttle($conn, 'oracle:' . $ip, 120, 3600)) { http_response_code(429); dv_out(['error' => 'Too many requests — please try again later.']); }

$uid    = (int)($_SESSION['bio_uid'] ?? 0);
$member = $uid > 0 && empty($_SESSION['bio_need_pin']);
$action = (string)($_POST['action'] ?? '');
$nowMs  = (int)round(microtime(true) * 1000);

if (random_int(1, 50) === 1) $conn->query("DELETE FROM oracle_live WHERE created_ms < " . ($nowMs - 2 * 3600 * 1000));

// This browser's current reading (if any, and less than 90 minutes old)
$token = (string)($_COOKIE['dv_rd'] ?? '');
$current = null; $savedUid = 0; $tokenHash = '';
if (preg_match('/^[a-f0-9]{64}$/', $token)) {
    $tokenHash = hash('sha256', $token);
    $s = $conn->prepare("SELECT reading, saved_uid FROM oracle_live WHERE token_hash=? AND created_ms > ?");
    $minMs = $nowMs - DV_COOLDOWN_MS;
    $s->bind_param('si', $tokenHash, $minMs); $s->execute();
    if ($row = $s->get_result()->fetch_assoc()) {
        $current = json_decode($row['reading'], true);
        $savedUid = (int)$row['saved_uid'];
    }
}

if ($action === 'current') {
    if (!$current) dv_out(['none' => true]);
    // HACKATHON Step 4 (new): reuse the stored AI sentence; never regenerate on refresh.
    dv_out(['ok' => true, 'reading' => dv_oracle_summary($current, $member) + ['ai' => ($current['ai'] ?? null)]]);
}

if ($action === 'draw') {
    // HACKATHON DEMO-ONLY WORK (new): a ?demo=1 flag skips the 90-minute
    // cooldown check below so judges can draw repeatedly. Normal visitors
    // without the flag keep the original cooldown behavior. The per-network
    // draw throttle further down still applies to everyone, including demos.
    $hackathonDemoBypass = (($_GET['demo'] ?? '') === '1' || ($_POST['demo'] ?? '') === '1');
    // One reading per 90 minutes: an existing reading is returned instead of a new draw.
    // HACKATHON Step 4 (new): cooldown returns reuse the stored AI sentence; no new generation here.
    if ($current && !$hackathonDemoBypass) dv_out(['ok' => true, 'existing' => true, 'reading' => dv_oracle_summary($current, $member) + ['ai' => ($current['ai'] ?? null)]]);
    if (!dv_throttle($conn, 'draw:' . $ip, 20, 3600)) { http_response_code(429); dv_out(['error' => 'Too many readings from this network — please try again later.']); }

    $digits = [];
    foreach (['d1', 'd2', 'd3', 'd4'] as $i => $k) {
        $v = filter_var($_POST[$k] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => $i === 0 ? 1 : 0, 'max_range' => 9]]);
        if ($v === false) dv_out(['error' => 'Invalid numbers']);
        $digits[] = $v;
    }
    $omen = (string)($_POST['omen'] ?? 'normal');
    if (!in_array($omen, ['good_sound', 'wind_blowing', 'normal', 'child_crying', 'glass_breaking'], true)) $omen = 'normal';
    $mins = filter_var($_POST['mins'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1439]]);
    if ($mins === false) $mins = (int)gmdate('G') * 60 + (int)gmdate('i');
    $question = trim((string)($_POST['question'] ?? ''));
    $question = function_exists('mb_substr') ? mb_substr($question, 0, 500) : substr($question, 0, 500);

    $reading = dv_oracle_draw($digits, $omen, $mins, $question);
    // HACKATHON Step 4 (new): one AI guidance sentence per NEW draw. Scores-only
    // payload (overall + individual scores below 5); the typed question memo is
    // NEVER sent. Stored inside this reading's JSON blob so cooldown/current
    // reuse it without regenerating; failures leave scores untouched, no sentence.
    $reading['ai'] = null;
    try {
        require_once __DIR__ . '/hackathon_ai.php';
        $hackAvg = isset($reading['avg']) ? (float)$reading['avg'] : null;
        $hackLow = [];
        foreach (['mhb','kauCim','tehillim','bible','quran','ram','tarot'] as $hk) {
            $hs = $reading[$hk]['score'] ?? $reading['scores'][$hk] ?? null;
            if (is_numeric($hs) && (float)$hs < 5) $hackLow[] = round((float)$hs, 1);
        }
        sort($hackLow);
        if ($hackAvg !== null) {
            $hackSentence = hackathon_ai_sentence($hackAvg, $hackLow);
            if (is_string($hackSentence) && $hackSentence !== '') $reading['ai'] = $hackSentence;
        }
    } catch (Throwable $hackE) { error_log('[hackathon-ai] draw hook failed: ' . $hackE->getMessage()); }
    $newToken = bin2hex(random_bytes(32));
    $newHash  = hash('sha256', $newToken);
    $json = json_encode($reading, JSON_UNESCAPED_UNICODE);
    $saved = $member ? $uid : null;
    $ins = $conn->prepare("INSERT INTO oracle_live (token_hash, reading, created_ms, saved_uid) VALUES (?, ?, ?, ?)");
    $ins->bind_param('ssii', $newHash, $json, $reading['ts'], $saved); $ins->execute();
    setcookie('dv_rd', $newToken, ['expires' => time() + 5400, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
    if ($member) dv_oracle_save($conn, $uid, $reading);
    // HACKATHON Step 4 (new): include this draw's AI sentence (null when unavailable).
    dv_out(['ok' => true, 'reading' => dv_oracle_summary($reading, $member) + ['ai' => ($reading['ai'] ?? null)]]);
}

if ($action === 'detail') {
    if (!$member) dv_out(['error' => 'login_required']);
    if (!$current) dv_out(['none' => true]);
    if ($savedUid !== $uid) {            // a guest reading carried over after signing in
        dv_oracle_save($conn, $uid, $current);
        $u = $conn->prepare("UPDATE oracle_live SET saved_uid=? WHERE token_hash=?");
        $u->bind_param('is', $uid, $tokenHash); $u->execute();
    }
    unset($current['sessionKey']);
    dv_out(['ok' => true, 'reading' => $current]);
}

dv_out(['error' => 'Unknown action']);
