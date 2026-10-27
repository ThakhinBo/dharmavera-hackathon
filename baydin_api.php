<?php
// baydin_api.php — Streak, Likes, Follows AJAX endpoint
require_once __DIR__ . '/core_init.php';

header('Content-Type: application/json; charset=utf-8');

if (!$db_ok) { echo json_encode(['error'=>'DB unavailable']); exit; }

// Create tables once per session to avoid overhead on every request
if (empty($_SESSION['baydin_tables_checked'])) {
    $_SESSION['baydin_tables_checked'] = true;
    $conn->query("CREATE TABLE IF NOT EXISTS baydin_users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    uuid          VARCHAR(64) UNIQUE NOT NULL,
    display_name  VARCHAR(100) DEFAULT NULL,
    streak_days   INT DEFAULT 0,
    max_streak    INT DEFAULT 0,
    total_points  INT DEFAULT 0,
    last_visit    DATE DEFAULT NULL,
    created_at    DATETIME DEFAULT NOW()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS baydin_streak_log (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    uuid       VARCHAR(64) NOT NULL,
    action     VARCHAR(50) NOT NULL,
    points     INT DEFAULT 0,
    log_date   DATE NOT NULL,
    logged_at  DATETIME DEFAULT NOW(),
    INDEX idx_uuid_date (uuid(36), log_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS baydin_compat_likes (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    from_uuid   VARCHAR(64) NOT NULL,
    target_key  VARCHAR(200) NOT NULL,
    liked_at    DATETIME DEFAULT NOW(),
    UNIQUE KEY uk_like (from_uuid(36), target_key(80))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS baydin_compat_follows (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    from_uuid   VARCHAR(64) NOT NULL,
    target_key  VARCHAR(200) NOT NULL,
    followed_at DATETIME DEFAULT NOW(),
    UNIQUE KEY uk_follow (from_uuid(36), target_key(80))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} // end session tables guard

// Simple IP rate limit: max 120 requests per minute
$_rl_key = 'baydin_rl_' . ($_SERVER['REMOTE_ADDR'] ?? '0');
if (!isset($_SESSION[$_rl_key])) $_SESSION[$_rl_key] = ['n'=>0,'t'=>time()];
if (time() - $_SESSION[$_rl_key]['t'] > 60) { $_SESSION[$_rl_key] = ['n'=>0,'t'=>time()]; }
$_SESSION[$_rl_key]['n']++;
if ($_SESSION[$_rl_key]['n'] > 120) {
    http_response_code(429);
    echo json_encode(['error'=>'Too many requests']); exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['error'=>'Method not allowed']); exit; }

// Load point rules from DB (cached in session for the request lifetime)
$_pt = [];
if (empty($_SESSION['pt_rules_cache'])) {
    $pr = $conn->query("SELECT action_key, points, enabled FROM bio_point_rules");
    if ($pr) while ($r = $pr->fetch_assoc()) $_pt[$r['action_key']] = ['pts'=>(int)$r['points'],'on'=>(bool)$r['enabled']];
    $_SESSION['pt_rules_cache'] = $_pt;
    $_SESSION['pt_rules_ts']    = time();
} else {
    // Refresh every 5 minutes so admin changes take effect promptly
    if (time() - ($_SESSION['pt_rules_ts'] ?? 0) > 300) {
        unset($_SESSION['pt_rules_cache']);
        $pr2 = $conn->query("SELECT action_key, points, enabled FROM bio_point_rules");
        if ($pr2) while ($r2 = $pr2->fetch_assoc()) $_pt[$r2['action_key']] = ['pts'=>(int)$r2['points'],'on'=>(bool)$r2['enabled']];
        $_SESSION['pt_rules_cache'] = $_pt;
        $_SESSION['pt_rules_ts']    = time();
    } else {
        $_pt = $_SESSION['pt_rules_cache'] ?? [];
    }
}
function ptVal(array $rules, string $key, int $default): int {
    return (isset($rules[$key]) && $rules[$key]['on']) ? $rules[$key]['pts'] : 0;
}

$action = trim($_POST['action'] ?? '');
$raw_uuid = trim($_POST['uuid'] ?? '');
$uuid = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $raw_uuid) ? $raw_uuid : '';
$today = date('Y-m-d');

function ensureUser($conn, $uuid, $name = null) {
    if (!$uuid) return false;
    $stmt = $conn->prepare("INSERT IGNORE INTO baydin_users (uuid, display_name) VALUES (?,?)");
    $stmt->bind_param('ss', $uuid, $name);
    $stmt->execute();
    return true;
}

function addPoints($conn, $uuid, $act, $pts, $date) {
    $stmt = $conn->prepare("SELECT id FROM baydin_streak_log WHERE uuid=? AND action=? AND log_date=?");
    $stmt->bind_param('sss', $uuid, $act, $date);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) return 0;

    $ins = $conn->prepare("INSERT INTO baydin_streak_log (uuid,action,points,log_date) VALUES (?,?,?,?)");
    $ins->bind_param('ssis', $uuid, $act, $pts, $date);
    $ins->execute();
    $upd = $conn->prepare("UPDATE baydin_users SET total_points=total_points+? WHERE uuid=?");
    $upd->bind_param('is', $pts, $uuid);
    $upd->execute();
    return $pts;
}

function updateStreak($conn, $uuid, $today) {
    $stmt = $conn->prepare("SELECT streak_days, max_streak, last_visit FROM baydin_users WHERE uuid=?");
    $stmt->bind_param('s', $uuid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) return ['streak'=>0,'max_streak'=>0];

    $last   = $row['last_visit'];
    $streak = (int)$row['streak_days'];
    $max    = (int)$row['max_streak'];
    if ($last === $today) return ['streak'=>$streak,'max_streak'=>$max];

    $yesterday = date('Y-m-d', strtotime('-1 day', strtotime($today)));
    $streak = ($last === $yesterday) ? $streak + 1 : 1;
    $max    = max($max, $streak);

    $upd = $conn->prepare("UPDATE baydin_users SET streak_days=?,max_streak=?,last_visit=? WHERE uuid=?");
    $upd->bind_param('iiss', $streak, $max, $today, $uuid);
    $upd->execute();
    return ['streak'=>$streak,'max_streak'=>$max,'new_day'=>true];
}

// ── INIT: called on every page load ──
if ($action === 'init') {
    $name = trim($_POST['name'] ?? '');
    ensureUser($conn, $uuid, $name ?: null);
    $info = updateStreak($conn, $uuid, $today);

    $pts = 0;
    if (!empty($info['new_day'])) {
        $pts = addPoints($conn, $uuid, 'daily_visit', ptVal($_pt,'daily_visit',1), $today);
    }
    $stmt = $conn->prepare("SELECT streak_days,max_streak,total_points FROM baydin_users WHERE uuid=?");
    $stmt->bind_param('s', $uuid);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc() ?? [];

    // Build rules payload for front-end badge display
$_rules_out = [];
foreach ($_pt as $k => $v) { if ($v['on']) $_rules_out[] = ['key'=>$k,'pts'=>$v['pts']]; }
echo json_encode(['ok'=>true,
    'streak'       =>(int)($u['streak_days']  ?? 0),
    'max_streak'   =>(int)($u['max_streak']   ?? 0),
    'total_points' =>(int)($u['total_points'] ?? 0),
    'pts_earned'   =>$pts,
    'point_rules'  =>$_rules_out]);
    exit;
}

// ── HEARTBEAT: 5-min session ──
if ($action === 'heartbeat') {
    if (!$uuid) { echo json_encode(['ok'=>false]); exit; }
    $pts = addPoints($conn, $uuid, 'session_5min', ptVal($_pt,'session_5min',1), $today);
    echo json_encode(['ok'=>true,'pts_earned'=>$pts]);
    exit;
}

// ── SHARE: +2 points ──
if ($action === 'share') {
    if (!$uuid) { echo json_encode(['ok'=>false]); exit; }
    $pts = addPoints($conn, $uuid, 'share', ptVal($_pt,'share',2), $today);
    $stmt = $conn->prepare("SELECT total_points FROM baydin_users WHERE uuid=?");
    $stmt->bind_param('s', $uuid);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc() ?? [];
    echo json_encode(['ok'=>true,'pts_earned'=>$pts,'total_points'=>(int)($u['total_points'] ?? 0)]);
    exit;
}

// ── TOGGLE LIKE ──
if ($action === 'toggle_like') {
    $target = trim($_POST['target'] ?? '');
    if (!$uuid || !$target) { echo json_encode(['error'=>'Missing data']); exit; }

    $stmt = $conn->prepare("SELECT id FROM baydin_compat_likes WHERE from_uuid=? AND target_key=?");
    $stmt->bind_param('ss', $uuid, $target);
    $stmt->execute();
    $is_liked = $stmt->get_result()->num_rows > 0;

    if ($is_liked) {
        $del = $conn->prepare("DELETE FROM baydin_compat_likes WHERE from_uuid=? AND target_key=?");
        $del->bind_param('ss', $uuid, $target);
        $del->execute();
    } else {
        $ins = $conn->prepare("INSERT IGNORE INTO baydin_compat_likes (from_uuid, target_key) VALUES (?,?)");
        $ins->bind_param('ss', $uuid, $target);
        $ins->execute();
    }

    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM baydin_compat_likes WHERE target_key=?");
    $stmt->bind_param('s', $target);
    $stmt->execute();
    $likes = (int)$stmt->get_result()->fetch_assoc()['c'];

    echo json_encode(['ok'=>true,'liked'=>!$is_liked,'likes'=>$likes]);
    exit;
}

// ── TOGGLE FOLLOW ──
if ($action === 'toggle_follow') {
    $target = trim($_POST['target'] ?? '');
    if (!$uuid || !$target) { echo json_encode(['error'=>'Missing data']); exit; }

    $stmt = $conn->prepare("SELECT id FROM baydin_compat_follows WHERE from_uuid=? AND target_key=?");
    $stmt->bind_param('ss', $uuid, $target);
    $stmt->execute();
    $is_following = $stmt->get_result()->num_rows > 0;

    if ($is_following) {
        $del = $conn->prepare("DELETE FROM baydin_compat_follows WHERE from_uuid=? AND target_key=?");
        $del->bind_param('ss', $uuid, $target);
        $del->execute();
    } else {
        $ins = $conn->prepare("INSERT IGNORE INTO baydin_compat_follows (from_uuid, target_key) VALUES (?,?)");
        $ins->bind_param('ss', $uuid, $target);
        $ins->execute();
    }

    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM baydin_compat_follows WHERE target_key=?");
    $stmt->bind_param('s', $target);
    $stmt->execute();
    $followers = (int)$stmt->get_result()->fetch_assoc()['c'];

    echo json_encode(['ok'=>true,'following'=>!$is_following,'followers'=>$followers]);
    exit;
}

// ── GET COMPAT STATS ──
if ($action === 'get_compat_stats') {
    $target = trim($_POST['target'] ?? '');
    if (!$target) { echo json_encode(['likes'=>0,'followers'=>0,'liked'=>false,'following'=>false]); exit; }

    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM baydin_compat_likes WHERE target_key=?");
    $stmt->bind_param('s', $target);
    $stmt->execute();
    $likes = (int)$stmt->get_result()->fetch_assoc()['c'];

    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM baydin_compat_follows WHERE target_key=?");
    $stmt->bind_param('s', $target);
    $stmt->execute();
    $followers = (int)$stmt->get_result()->fetch_assoc()['c'];

    $liked = false; $following = false;
    if ($uuid) {
        $stmt = $conn->prepare("SELECT id FROM baydin_compat_likes WHERE from_uuid=? AND target_key=?");
        $stmt->bind_param('ss', $uuid, $target);
        $stmt->execute();
        $liked = $stmt->get_result()->num_rows > 0;

        $stmt = $conn->prepare("SELECT id FROM baydin_compat_follows WHERE from_uuid=? AND target_key=?");
        $stmt->bind_param('ss', $uuid, $target);
        $stmt->execute();
        $following = $stmt->get_result()->num_rows > 0;
    }
    echo json_encode(['ok'=>true,'likes'=>$likes,'followers'=>$followers,'liked'=>$liked,'following'=>$following]);
    exit;
}

echo json_encode(['error'=>'Unknown action']);