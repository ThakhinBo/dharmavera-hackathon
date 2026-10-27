<?php
// ══════════════════════════════════════════════════════════════════
// MY PERSONAL BIORHYTHM — dharmavera.com/biorhythm.php
// Auth: Name + DOB  |  Database: southcom
// ══════════════════════════════════════════════════════════════════

require_once __DIR__ . '/core_init.php';

if ($db_ok) {
    $conn->query("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $conn->select_db(DB_NAME);


// Admin block content/labels (reuse existing $conn — same DB/user)
$_blk=[];
if ($db_ok) {
    $_br=$conn->query("SELECT block_key,block_label,content,sort_order FROM bio_block_config");
    if($_br) while($_row=$_br->fetch_assoc()) $_blk[$_row['block_key']]=$_row;
}
function _blkC(string $key):string{
    global $_blk;
    $h=$_blk[$key]['content']??'';
    return $h?'<div class="blk-custom-content" style="font-size:.9rem;margin-bottom:.85rem">'.$h.'</div>':'';
}
function _blkL(string $key,string $def):string{
    global $_blk;
    $label = $_blk[$key]['block_label'] ?? $def;
    if ($key === 'compat' && in_array($label, ['Compatibility', 'Compatibility Reading'], true)) $label = 'Harmony';
    return htmlspecialchars($label,ENT_QUOTES);
}
function _blkO(string $key,int $def):int{
    global $_blk;
    return isset($_blk[$key]['sort_order']) ? (int)$_blk[$key]['sort_order'] : $def;
}

// Auto-promote a user's rank based on points and admin-configured thresholds.
// Only ever upgrades — never auto-downgrades a manually-set higher rank.
function applyAutoRank($conn, int $uid, int $points): string {
    $rules = $conn->query("SELECT gold_threshold, platinum_threshold FROM bio_settings WHERE id=1")->fetch_assoc();
    $gold  = (int)($rules['gold_threshold']     ?? 100);
    $plat  = (int)($rules['platinum_threshold'] ?? 500);
    $earned = $points >= $plat ? 'platinum' : ($points >= $gold ? 'gold' : 'silver');
    $rank = ['silver'=>0,'gold'=>1,'platinum'=>2];
    $cur = $conn->query("SELECT level FROM bio_users WHERE id=$uid")->fetch_assoc()['level'] ?? 'silver';
    if ($rank[$earned] > ($rank[$cur] ?? 0)) {
        $stmt = $conn->prepare("UPDATE bio_users SET level=? WHERE id=?");
        $stmt->bind_param('si', $earned, $uid);
        $stmt->execute();
        return $earned;
    }
    return $cur;
}

// Pull this account's points balance up to match their anonymous browsing
// history (matched by the persistent baydin_uuid from index.php), then
// re-check their rank against current thresholds.
function syncPointsAndRank($conn, int $uid, string $uuid): void {
    if (!$uuid) { 
        $row = $conn->query("SELECT points FROM bio_users WHERE id=$uid")->fetch_assoc();
        applyAutoRank($conn, $uid, (int)($row['points'] ?? 0));
        return;
    }
    $bstmt = $conn->prepare("SELECT total_points FROM baydin_users WHERE uuid=?");
    $bstmt->bind_param('s', $uuid);
    $bstmt->execute();
    $brow = $bstmt->get_result()->fetch_assoc();
    $anonPoints = (int)($brow['total_points'] ?? 0);

    $upd = $conn->prepare("UPDATE bio_users SET points = GREATEST(points, ?), linked_uuid = ? WHERE id = ?");
    $upd->bind_param('isi', $anonPoints, $uuid, $uid);
    $upd->execute();

    $row = $conn->query("SELECT points FROM bio_users WHERE id=$uid")->fetch_assoc();
    applyAutoRank($conn, $uid, (int)($row['points'] ?? 0));
}


    $conn->query("CREATE TABLE IF NOT EXISTS bio_users (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        name        VARCHAR(100) NOT NULL,
        dob         DATE NOT NULL,
        created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_user (name(80), dob)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS bio_daily_log (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        user_id      INT NOT NULL,
        log_date     DATE NOT NULL,
        physical     TINYINT NOT NULL DEFAULT 5,
        emotional    TINYINT NOT NULL DEFAULT 5,
        intellectual TINYINT NOT NULL DEFAULT 5,
        luck         TINYINT NOT NULL DEFAULT 5,
        notes        TEXT,
        UNIQUE KEY uk_log (user_id, log_date),
        FOREIGN KEY (user_id) REFERENCES bio_users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS bio_oracle_readings (
        id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id       INT NOT NULL,
        session_key   VARCHAR(80) NOT NULL,
        question      VARCHAR(500) NOT NULL,
        score         DECIMAL(3,1) NOT NULL,
        mhb_score     TINYINT DEFAULT NULL,
        quran_score   TINYINT DEFAULT NULL,
        bible_score   TINYINT DEFAULT NULL,
        kau_score     TINYINT DEFAULT NULL,
        tarot_score   TINYINT DEFAULT NULL,
        oracle_count  TINYINT NOT NULL DEFAULT 4,
        asked_at      DATETIME NOT NULL,
        created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uk_oracle_session (user_id, session_key),
        INDEX idx_oracle_user_date (user_id, asked_at),
        INDEX idx_oracle_date (asked_at),
        CONSTRAINT fk_oracle_user FOREIGN KEY (user_id) REFERENCES bio_users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS bio_milestones (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        user_id      INT NOT NULL,
        milestone_id INT NOT NULL,
        ms_year      SMALLINT,
        ms_month     TINYINT,
        ms_day       TINYINT,
        UNIQUE KEY uk_ms (user_id, milestone_id),
        FOREIGN KEY (user_id) REFERENCES bio_users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Ensure optional columns exist (safe DDL — only runs if column missing)
    $conn->query("ALTER TABLE bio_users
        ADD COLUMN IF NOT EXISTS birth_time  VARCHAR(5)         DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS birth_city  VARCHAR(100)       DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS birth_lat   FLOAT              DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS birth_lon   FLOAT              DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS blood_type  ENUM('A','B','AB','O') DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS mbti_type   VARCHAR(4)         DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS points      INT                DEFAULT 0,
        ADD COLUMN IF NOT EXISTS level       ENUM('silver','gold','platinum') DEFAULT 'silver',
        ADD COLUMN IF NOT EXISTS last_login  DATETIME           DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS linked_uuid VARCHAR(64)        DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS admin_note  TEXT               DEFAULT NULL");

    $conn->query("ALTER TABLE bio_users
        ADD COLUMN IF NOT EXISTS pin_hash VARCHAR(255) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS pin_fails TINYINT NOT NULL DEFAULT 0,
        ADD COLUMN IF NOT EXISTS pin_locked_until DATETIME DEFAULT NULL");
    $conn->query("CREATE TABLE IF NOT EXISTS bio_devices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token_hash CHAR(64) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_token (token_hash),
        INDEX idx_user (user_id),
        CONSTRAINT fk_device_user FOREIGN KEY (user_id) REFERENCES bio_users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("ALTER TABLE bio_oracle_readings
        ADD COLUMN IF NOT EXISTS tehillim_score TINYINT DEFAULT NULL AFTER tarot_score,
        ADD COLUMN IF NOT EXISTS ram_score      TINYINT DEFAULT NULL AFTER tehillim_score");

    $conn->query("ALTER TABLE bio_compat_saves
        ADD COLUMN IF NOT EXISTS person2_blood_type VARCHAR(2) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS person2_mbti_type  VARCHAR(4) DEFAULT NULL");
}

// ── Sign-out handler (GET ?signedout=1) — destroy session server-side ──
if (isset($_GET['signedout'])) {
    session_unset();
    session_destroy();
    header('Location: biorhythm.php');
    exit;
}

// ── CSRF token ──
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── API Handler ──
if (isset($_GET['api'])) {
    header('Content-Type: application/json; charset=utf-8');
    if (!$db_ok) { echo json_encode(['error'=>'DB unavailable']); exit; }

    // Only accept POST for state-changing actions
    $action = $_POST['action'] ?? '';
    if (!$action) { echo json_encode(['error'=>'No action']); exit; }

    // CSRF check for all state-changing actions (logout included to prevent CSRF logout)
    $csrfExempt = []; // no exemptions — login sends token too
    $csrfToken  = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrfToken)) {
        echo json_encode(['error'=>'Invalid request']); exit;
    }

    // Members who signed up before PINs existed must create one before doing anything else.
    if (!empty($_SESSION['bio_need_pin']) && !in_array($action, ['set_pin','logout','login','resume'], true)) {
        echo json_encode(['error'=>'Please create your 4-digit PIN first.', 'need_pin'=>true]); exit;
    }

    // ── Shared sign-in completion (used by login and device resume) ──
    $finishLogin = function (int $uid, array $row, string $name, string $dob, string $loginUuid, bool $remember) use ($conn): void {
        // Regenerate session ID on login to prevent session fixation
        session_regenerate_id(true);
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // new CSRF token post-login
        $_SESSION['bio_uid']  = $uid;
        $_SESSION['bio_name'] = $name;
        $_SESSION['bio_dob']  = $dob;
        $needPin = empty($row['pin_hash']);
        if ($needPin) $_SESSION['bio_need_pin'] = 1; else unset($_SESSION['bio_need_pin']);
        // Check gold_until: boost to gold if still active, expire if past
        $guRow = $conn->query("SELECT gold_until, level, points FROM bio_users WHERE id=$uid")->fetch_assoc();
        if ($guRow) {
            $goldUntil = $guRow['gold_until'] ?? null;
            $today     = date('Y-m-d');
            if ($goldUntil && $goldUntil >= $today && $guRow['level'] === 'silver') {
                // Temporarily boosted to gold
                $conn->query("UPDATE bio_users SET level='gold', last_login=NOW() WHERE id=$uid");
            } elseif ($goldUntil && $goldUntil < $today && $guRow['level'] === 'gold') {
                // Expired — recalculate based on points
                $rules = $conn->query("SELECT gold_threshold,platinum_threshold FROM bio_settings WHERE id=1")->fetch_assoc();
                $gThr  = (int)($rules['gold_threshold'] ?? 100);
                $pThr  = (int)($rules['platinum_threshold'] ?? 500);
                $pts   = (int)$guRow['points'];
                $earned = $pts >= $pThr ? 'platinum' : ($pts >= $gThr ? 'gold' : 'silver');
                $conn->query("UPDATE bio_users SET level='$earned', gold_until=NULL, last_login=NOW() WHERE id=$uid");
            } else {
                $conn->query("UPDATE bio_users SET last_login=NOW() WHERE id=$uid");
            }
        }
        $_SESSION['bio_btime'] = $row['birth_time'] ?? '';
        $_SESSION['bio_bcity'] = $row['birth_city'] ?? '';
        $_SESSION['bio_blat']  = $row['birth_lat']  ?? null;
        $_SESSION['bio_blon']  = $row['birth_lon']  ?? null;
        $_SESSION['bio_btype'] = $row['blood_type'] ?? null;
        $_SESSION['bio_mbti']  = $row['mbti_type']  ?? null;

        // Sync points from this browser's anonymous history and auto-promote rank
        syncPointsAndRank($conn, $uid, $loginUuid);
        $finalUser = $conn->query("SELECT level, points, gold_until FROM bio_users WHERE id=$uid")->fetch_assoc();

        // "Remember this device": a random token; only its SHA-256 hash is stored.
        $deviceToken = null;
        if ($remember) {
            $deviceToken = bin2hex(random_bytes(32));
            $hash = hash('sha256', $deviceToken);
            $d = $conn->prepare("INSERT INTO bio_devices (user_id, token_hash) VALUES (?, ?)");
            $d->bind_param('is', $uid, $hash); $d->execute();
            // Keep at most 10 remembered devices per member
            $conn->query("DELETE FROM bio_devices WHERE user_id=$uid AND id NOT IN (SELECT id FROM (SELECT id FROM bio_devices WHERE user_id=$uid ORDER BY id DESC LIMIT 10) keep)");
        }

        echo json_encode(['ok'=>true, 'uid'=>$uid, 'name'=>$name, 'dob'=>$dob,
            'birth_time' => $row['birth_time'] ?? '',
            'birth_city' => $row['birth_city'] ?? '',
            'birth_lat'  => $row['birth_lat']  ?? null,
            'birth_lon'  => $row['birth_lon']  ?? null,
            'blood_type' => $row['blood_type'] ?? null,
            'mbti_type'  => $row['mbti_type']  ?? null,
            'level'      => $finalUser['level']  ?? 'silver',
            'points'     => (int)($finalUser['points'] ?? 0),
            'need_pin'   => $needPin,
            'device_token' => $deviceToken,
            'csrf'       => $_SESSION['csrf_token']]);
    };

    $validPin = static fn($p) => is_string($p) && preg_match('/^\d{4}$/', $p) === 1;
    $userCols = "id, name, dob, birth_time, birth_city, birth_lat, birth_lon, blood_type, mbti_type, pin_hash, pin_fails, pin_locked_until";

    if ($action === 'login') {
        // Server-side rate limit (not bypassable by clearing cookies): 20 attempts per IP per 15 minutes
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if (!dv_throttle($conn, 'login:' . $ip, 20, 900)) {
            echo json_encode(['error'=>'Too many attempts — please wait 15 minutes.']); exit;
        }

        $name = substr(trim($_POST['name'] ?? ''), 0, 100);
        $dob  = trim($_POST['dob']  ?? '');
        $pin  = (string)($_POST['pin'] ?? '');
        $pin2 = (string)($_POST['pin2'] ?? '');
        if (!$name || !$dob) { echo json_encode(['error'=>'Name and DOB required']); exit; }
        // Validate DOB format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob) || !checkdate((int)substr($dob,5,2),(int)substr($dob,8,2),(int)substr($dob,0,4))) {
            echo json_encode(['error'=>'Invalid date of birth']); exit;
        }

        $valid_mbti = ['INTJ','INTP','INFJ','INFP','ENTJ','ENTP','ENFJ','ENFP',
                       'ISTJ','ISTP','ISFJ','ISFP','ESTJ','ESTP','ESFJ','ESFP'];
        $btime = substr(trim($_POST['birth_time'] ?? ''), 0, 5);
        $bcity = substr(trim($_POST['birth_city'] ?? ''), 0, 100);
        $blat  = isset($_POST['birth_lat'])  && $_POST['birth_lat']  !== '' ? (float)$_POST['birth_lat']  : null;
        $blon  = isset($_POST['birth_lon'])  && $_POST['birth_lon']  !== '' ? (float)$_POST['birth_lon']  : null;
        $btype = in_array($_POST['blood_type'] ?? '', ['A','B','AB','O'], true) ? $_POST['blood_type'] : null;
        $mbti  = in_array(strtoupper($_POST['mbti_type'] ?? ''), $valid_mbti) ? strtoupper($_POST['mbti_type']) : null;
        $remember = ($_POST['remember'] ?? '1') === '1';

        $stmt = $conn->prepare("SELECT $userCols FROM bio_users WHERE name=? AND dob=?");
        if (!$stmt) { echo json_encode(['error'=>'Login failed']); exit; }
        $stmt->bind_param('ss', $name, $dob);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $badCreds = 'Name, date of birth or PIN is not correct.';

        if ($row) {
            $uid = (int)$row['id'];
            if (!empty($row['pin_locked_until']) && strtotime($row['pin_locked_until']) > time()) {
                echo json_encode(['error'=>'Too many wrong PINs. Please try again in 15 minutes, or contact ' . SUPPORT_EMAIL . '.']); exit;
            }
            if (!empty($row['pin_hash'])) {
                if (!$validPin($pin) || !password_verify($pin, $row['pin_hash'])) {
                    $fails = (int)$row['pin_fails'] + 1;
                    if ($fails >= 5) {
                        $conn->query("UPDATE bio_users SET pin_fails=0, pin_locked_until=DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE id=$uid");
                        echo json_encode(['error'=>'Too many wrong PINs. Please try again in 15 minutes, or contact ' . SUPPORT_EMAIL . '.']); exit;
                    }
                    $conn->query("UPDATE bio_users SET pin_fails=$fails WHERE id=$uid");
                    echo json_encode(['error'=>$badCreds]); exit;
                }
                $conn->query("UPDATE bio_users SET pin_fails=0, pin_locked_until=NULL WHERE id=$uid");
            }
            if (empty($row['pin_hash']) && $validPin($pin)) {
                // Member from before PINs existed: the 4 digits typed now become their PIN once confirmed.
                if ($pin2 === '') { echo json_encode(['new_account'=>true, 'existing'=>true]); exit; }
                if ($pin !== $pin2) { echo json_encode(['error'=>'The two PINs do not match.', 'new_account'=>true, 'existing'=>true]); exit; }
                $newHash = password_hash($pin, PASSWORD_DEFAULT);
                $ph = $conn->prepare("UPDATE bio_users SET pin_hash=?, pin_fails=0, pin_locked_until=NULL WHERE id=?");
                $ph->bind_param('si', $newHash, $uid); $ph->execute();
                $row['pin_hash'] = $newHash;
            }
            // Without a valid PIN typed, a member from before PINs may still sign in once and is then asked to create one (need_pin).
            if ($btime || $bcity || $blat !== null || $btype !== null || $mbti !== null) {
                $upd = $conn->prepare("UPDATE bio_users SET birth_time=COALESCE(NULLIF(?,\"\"),birth_time), birth_city=COALESCE(NULLIF(?,\"\"),birth_city), birth_lat=COALESCE(?,birth_lat), birth_lon=COALESCE(?,birth_lon), blood_type=COALESCE(?,blood_type), mbti_type=COALESCE(?,mbti_type) WHERE id=?");
                $upd->bind_param('ssddssi', $btime, $bcity, $blat, $blon, $btype, $mbti, $uid);
                $upd->execute();
                $s2 = $conn->prepare("SELECT $userCols FROM bio_users WHERE id=?");
                $s2->bind_param('i', $uid); $s2->execute();
                $row = array_merge($row, $s2->get_result()->fetch_assoc() ?? []);
            }
        } else {
            // New profile: the PIN must be chosen and confirmed.
            if ($pin === '' || $pin2 === '') { echo json_encode(['new_account'=>true]); exit; }
            if (!$validPin($pin))  { echo json_encode(['error'=>'Your PIN must be exactly 4 digits.', 'new_account'=>true]); exit; }
            if ($pin !== $pin2)    { echo json_encode(['error'=>'The two PINs do not match.', 'new_account'=>true]); exit; }
            if (!dv_throttle($conn, 'signup:' . $ip, 10, 3600)) {
                echo json_encode(['error'=>'Too many new profiles from this network — please try again later.']); exit;
            }
            $pinHash = password_hash($pin, PASSWORD_DEFAULT);
            $ins = $conn->prepare("INSERT INTO bio_users (name, dob, birth_time, birth_city, birth_lat, birth_lon, blood_type, mbti_type, pin_hash) VALUES (?,?,?,?,?,?,?,?,?)");
            if (!$ins) { echo json_encode(['error'=>'Login failed']); exit; }
            $ins->bind_param('ssssddsss', $name, $dob, $btime, $bcity, $blat, $blon, $btype, $mbti, $pinHash);
            $ins->execute();
            $uid = $conn->insert_id;
            $row = ['birth_time'=>$btime,'birth_city'=>$bcity,'birth_lat'=>$blat,'birth_lon'=>$blon,'blood_type'=>$btype,'mbti_type'=>$mbti,'pin_hash'=>$pinHash];
            // Apply new-user promotion if active
            $promo = $conn->query("SELECT promo_enabled,promo_type,promo_duration_days,promo_end_date,promo_start_points FROM bio_settings WHERE id=1")->fetch_assoc();
            if (!empty($promo['promo_enabled'])) {
                $promoActive = false;
                if ($promo['promo_type'] === 'fixed_date') {
                    $promoActive = !empty($promo['promo_end_date']) && date('Y-m-d') <= $promo['promo_end_date'];
                } else {
                    $promoActive = true; // applies to all new sign-ups while enabled
                }
                if ($promoActive) {
                    $sp = max(0, (int)$promo['promo_start_points']);
                    if ($sp > 0) {
                        $upd = $conn->prepare("UPDATE bio_users SET points=GREATEST(points,?) WHERE id=?");
                        $upd->bind_param('ii', $sp, $uid); $upd->execute();
                    }
                }
            }
        }
        $finishLogin($uid, $row, $name, $dob, trim($_POST['uuid'] ?? ''), $remember);
        exit;
    }

    // ── Resume a remembered device (replaces the old name + date-of-birth auto sign-in) ──
    if ($action === 'resume') {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if (!dv_throttle($conn, 'resume:' . $ip, 60, 900)) { echo json_encode(['error'=>'Too many attempts']); exit; }
        $token = (string)($_POST['token'] ?? '');
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) { echo json_encode(['error'=>'Not remembered']); exit; }
        $hash = hash('sha256', $token);
        $s = $conn->prepare("SELECT u.$userCols FROM bio_devices d JOIN bio_users u ON u.id=d.user_id WHERE d.token_hash=?");
        $s->bind_param('s', $hash); $s->execute();
        $row = $s->get_result()->fetch_assoc();
        if (!$row) { echo json_encode(['error'=>'Not remembered']); exit; }
        // Rotate the token on each use
        $del = $conn->prepare("DELETE FROM bio_devices WHERE token_hash=?");
        $del->bind_param('s', $hash); $del->execute();
        $finishLogin((int)$row['id'], $row, $row['name'], $row['dob'], trim($_POST['uuid'] ?? ''), true);
        exit;
    }

    // ── Create a PIN (members who signed up before PINs existed) ──
    if ($action === 'set_pin') {
        if (empty($_SESSION['bio_uid'])) { echo json_encode(['error'=>'Not logged in']); exit; }
        $uid = (int)$_SESSION['bio_uid'];
        $pin = (string)($_POST['pin'] ?? ''); $pin2 = (string)($_POST['pin2'] ?? '');
        if (!$validPin($pin)) { echo json_encode(['error'=>'Your PIN must be exactly 4 digits.']); exit; }
        if ($pin !== $pin2)   { echo json_encode(['error'=>'The two PINs do not match.']); exit; }
        $has = $conn->query("SELECT pin_hash FROM bio_users WHERE id=$uid")->fetch_assoc();
        if (!empty($has['pin_hash'])) { echo json_encode(['error'=>'You already have a PIN. Use Change PIN in your Profile.']); exit; }
        $h = password_hash($pin, PASSWORD_DEFAULT);
        $s = $conn->prepare("UPDATE bio_users SET pin_hash=?, pin_fails=0, pin_locked_until=NULL WHERE id=?");
        $s->bind_param('si', $h, $uid); $s->execute();
        unset($_SESSION['bio_need_pin']);
        echo json_encode(['ok'=>true]); exit;
    }

    // ── Change PIN (requires the current PIN; signs out other remembered devices) ──
    if ($action === 'change_pin') {
        if (empty($_SESSION['bio_uid'])) { echo json_encode(['error'=>'Not logged in']); exit; }
        $uid = (int)$_SESSION['bio_uid'];
        if (!dv_throttle($conn, 'changepin:' . $uid, 10, 900)) { echo json_encode(['error'=>'Too many attempts — please wait 15 minutes.']); exit; }
        $cur = (string)($_POST['current'] ?? ''); $pin = (string)($_POST['pin'] ?? ''); $pin2 = (string)($_POST['pin2'] ?? '');
        $has = $conn->query("SELECT pin_hash FROM bio_users WHERE id=$uid")->fetch_assoc();
        if (empty($has['pin_hash']) || !password_verify($cur, $has['pin_hash'])) { echo json_encode(['error'=>'Your current PIN is not correct.']); exit; }
        if (!$validPin($pin)) { echo json_encode(['error'=>'Your new PIN must be exactly 4 digits.']); exit; }
        if ($pin !== $pin2)   { echo json_encode(['error'=>'The two new PINs do not match.']); exit; }
        $h = password_hash($pin, PASSWORD_DEFAULT);
        $s = $conn->prepare("UPDATE bio_users SET pin_hash=? WHERE id=?");
        $s->bind_param('si', $h, $uid); $s->execute();
        $conn->query("DELETE FROM bio_devices WHERE user_id=$uid");
        echo json_encode(['ok'=>true]); exit;
    }

    if ($action === 'logout') {
        $token = (string)($_POST['token'] ?? '');
        if (preg_match('/^[a-f0-9]{64}$/', $token)) {
            $hash = hash('sha256', $token);
            $del = $conn->prepare("DELETE FROM bio_devices WHERE token_hash=?");
            $del->bind_param('s', $hash); $del->execute();
        }
        session_destroy();
        echo json_encode(['ok'=>true]);
        exit;
    }

    // ── Member self-service account deletion (required by Google Play) ──
    if ($action === 'delete_account') {
        if (empty($_SESSION['bio_uid'])) { echo json_encode(['error'=>'Not logged in']); exit; }
        if (trim($_POST['confirm'] ?? '') !== 'DELETE') { echo json_encode(['error'=>'Please type DELETE to confirm.']); exit; }
        $uid = (int)$_SESSION['bio_uid'];

        $s = $conn->prepare("SELECT linked_uuid FROM bio_users WHERE id=?");
        $s->bind_param('i', $uid); $s->execute();
        $uuid = (string)($s->get_result()->fetch_assoc()['linked_uuid'] ?? '');

        $conn->begin_transaction();
        try {
            foreach (['bio_daily_log','bio_milestones','bio_compat_saves','bio_oracle_readings'] as $table) {
                $s = $conn->prepare("DELETE FROM `$table` WHERE user_id=?");
                $s->bind_param('i', $uid);
                if (!$s->execute()) throw new RuntimeException($conn->error);
            }
            if ($uuid !== '') {
                foreach (['baydin_streak_log'=>'uuid','baydin_compat_likes'=>'from_uuid','baydin_compat_follows'=>'from_uuid','baydin_users'=>'uuid'] as $table => $col) {
                    $s = $conn->prepare("DELETE FROM `$table` WHERE `$col`=?");
                    $s->bind_param('s', $uuid);
                    if (!$s->execute()) throw new RuntimeException($conn->error);
                }
            }
            $s = $conn->prepare("DELETE FROM oracle_live WHERE saved_uid=?");
            $s->bind_param('i', $uid);
            if (!$s->execute()) throw new RuntimeException($conn->error);
            $s = $conn->prepare("DELETE FROM bio_users WHERE id=?");
            $s->bind_param('i', $uid);
            if (!$s->execute() || $s->affected_rows !== 1) throw new RuntimeException('User row not deleted');
            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            error_log('delete_account failed for uid ' . $uid . ': ' . $e->getMessage());
            echo json_encode(['error'=>'Account deletion failed. Please try again or contact ' . SUPPORT_EMAIL . '.']); exit;
        }
        session_unset();
        session_destroy();
        echo json_encode(['ok'=>true]);
        exit;
    }

    if ($action === 'save_details') {
        // Anyone can save_details — no auth required (name+dob serve as auth via login)
        if (empty($_SESSION['bio_uid'])) { echo json_encode(['error'=>'Not logged in']); exit; }
        $uid   = (int)$_SESSION['bio_uid'];
        $btime = substr(trim($_POST['birth_time'] ?? ''), 0, 5);
        $bcity = substr(trim($_POST['birth_city'] ?? ''), 0, 100);
        $blat  = isset($_POST['birth_lat']) && $_POST['birth_lat'] !== '' ? (float)$_POST['birth_lat'] : null;
        $blon  = isset($_POST['birth_lon']) && $_POST['birth_lon'] !== '' ? (float)$_POST['birth_lon'] : null;
        $valid_mbti = ['INTJ','INTP','INFJ','INFP','ENTJ','ENTP','ENFJ','ENFP',
                       'ISTJ','ISTP','ISFJ','ISFP','ESTJ','ESTP','ESFJ','ESFP'];
        $btype = in_array($_POST['blood_type'] ?? '', ['A','B','AB','O']) ? $_POST['blood_type'] : null;
        $mbti  = in_array(strtoupper($_POST['mbti_type'] ?? ''), $valid_mbti) ? strtoupper($_POST['mbti_type']) : null;
        $upd = $conn->prepare("UPDATE bio_users SET birth_time=?, birth_city=?, birth_lat=?, birth_lon=?, blood_type=?, mbti_type=COALESCE(?,mbti_type) WHERE id=?");
        $upd->bind_param('ssddssi', $btime, $bcity, $blat, $blon, $btype, $mbti, $uid);
        $upd->execute();
        $_SESSION['bio_btime'] = $btime;
        $_SESSION['bio_bcity'] = $bcity;
        $_SESSION['bio_blat']  = $blat;
        $_SESSION['bio_blon']  = $blon;
        $_SESSION['bio_mbti']  = $mbti;
        echo json_encode(['ok'=>true, 'birth_time'=>$btime, 'birth_city'=>$bcity,
            'birth_lat'=>$blat, 'birth_lon'=>$blon, 'blood_type'=>$btype, 'mbti_type'=>$mbti]);
        exit;
    }

    // All actions below require auth
    if (empty($_SESSION['bio_uid'])) { echo json_encode(['error'=>'Not logged in']); exit; }
    $uid = (int)$_SESSION['bio_uid'];

    if ($action === 'save_daily') {
        $date  = trim($_POST['date']  ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !checkdate((int)substr($date,5,2),(int)substr($date,8,2),(int)substr($date,0,4))) {
            echo json_encode(['error'=>'Invalid date']); exit;
        }
        // Reject future dates (more than 1 day ahead)
        if ($date > date('Y-m-d', strtotime('+1 day'))) { echo json_encode(['error'=>'Future date not allowed']); exit; }
        $p     = max(1, min(10, (int)($_POST['p']    ?? 5)));
        $e     = max(1, min(10, (int)($_POST['e']    ?? 5)));
        $i     = max(1, min(10, (int)($_POST['i']    ?? 5)));
        $luck  = max(1, min(10, (int)($_POST['luck'] ?? 5)));
        $notes = substr(trim($_POST['notes'] ?? ''), 0, 1000);

        $stmt = $conn->prepare("INSERT INTO bio_daily_log (user_id,log_date,physical,emotional,intellectual,luck,notes)
            VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE physical=VALUES(physical),emotional=VALUES(emotional),intellectual=VALUES(intellectual),luck=VALUES(luck),notes=VALUES(notes)");
        if (!$stmt) { echo json_encode(['error'=>'Prepare failed: '.$conn->error]); exit; }
        $stmt->bind_param('isiiiis', $uid,$date,$p,$e,$i,$luck,$notes);
        $stmt->execute();
        echo json_encode(['ok'=>true]);
        exit;
    }

    // (Oracle readings are saved by the private engine via oracle_api.php — never from browser-supplied scores.)

    if ($action === 'save_milestones') {
        $milestones = json_decode($_POST['milestones'] ?? '[]', true);
        $del = $conn->prepare("DELETE FROM bio_milestones WHERE user_id=?");
        $del->bind_param('i', $uid);
        $del->execute();
        $ins = $conn->prepare("INSERT INTO bio_milestones (user_id,milestone_id,ms_year,ms_month,ms_day) VALUES (?,?,?,?,?)");
        if ($ins) foreach ($milestones as $ms) {
            $mid = (int)$ms['id'];
            $yr  = $ms['year']  ? (int)$ms['year']  : 0;
            $mo  = $ms['month'] ? (int)$ms['month'] : 0;
            $dy  = $ms['day']   ? (int)$ms['day']   : 0;
            if (!$yr) continue;
            $ins->bind_param('iiiii', $uid, $mid, $yr, $mo, $dy);
            $ins->execute();
        }
        echo json_encode(['ok'=>true]);
        exit;
    }

    if ($action === 'load_data') {
        // Daily log
        $res  = $conn->query("SELECT log_date,physical,emotional,intellectual,luck,notes FROM bio_daily_log WHERE user_id=$uid ORDER BY log_date");
        $logs = [];
        while ($r = $res->fetch_assoc()) {
            $logs[$r['log_date']] = ['p'=>(int)$r['physical'],'e'=>(int)$r['emotional'],'i'=>(int)$r['intellectual'],'luck'=>(int)$r['luck'],'notes'=>$r['notes']];
        }
        // Milestones
        $res2 = $conn->query("SELECT milestone_id,ms_year,ms_month,ms_day FROM bio_milestones WHERE user_id=$uid");
        $mss  = [];
        while ($r = $res2->fetch_assoc()) {
            $mss[(int)$r['milestone_id']] = ['year'=>$r['ms_year'],'month'=>$r['ms_month'],'day'=>$r['ms_day']];
        }
        $oracle = [];
        $res3 = $conn->prepare("SELECT id,session_key,question,score,mhb_score,quran_score,bible_score,kau_score,tarot_score,tehillim_score,ram_score,oracle_count,asked_at
            FROM bio_oracle_readings WHERE user_id=? ORDER BY asked_at ASC,id ASC LIMIT 1000");
        $res3->bind_param('i', $uid); $res3->execute();
        $oracleResult = $res3->get_result();
        while ($r = $oracleResult->fetch_assoc()) {
            $r['id'] = (int)$r['id']; $r['score'] = (float)$r['score']; $r['oracle_count'] = (int)$r['oracle_count'];
            foreach (['mhb_score','quran_score','bible_score','kau_score','tarot_score','tehillim_score','ram_score'] as $k) $r[$k] = $r[$k] === null ? null : (int)$r[$k];
            $oracle[] = $r;
        }
        echo json_encode(['ok'=>true,'log'=>$logs,'milestones'=>$mss,'oracle'=>$oracle]);
        exit;
    }

    if ($action === 'save_compat') {
        $conn->query("CREATE TABLE IF NOT EXISTS bio_compat_saves (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            user_id      INT NOT NULL,
            person2_name VARCHAR(100) NOT NULL,
            person2_dob  DATE NOT NULL,
            person2_time VARCHAR(5)   DEFAULT NULL,
            person2_city VARCHAR(100) DEFAULT NULL,
            person2_lat  FLOAT        DEFAULT NULL,
            person2_lon  FLOAT        DEFAULT NULL,
            saved_at     DATETIME     DEFAULT NOW(),
            INDEX idx_uid (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $valid_mbti2 = ['INTJ','INTP','INFJ','INFP','ENTJ','ENTP','ENFJ','ENFP',
                        'ISTJ','ISTP','ISFJ','ISFP','ESTJ','ESTP','ESFJ','ESFP'];
        $p2name  = substr(trim($_POST['p2name'] ?? ''), 0, 100);
        $p2dob   = trim($_POST['p2dob']  ?? '');
        $p2time  = substr(trim($_POST['p2time'] ?? ''), 0, 5);
        $p2city  = substr(trim($_POST['p2city'] ?? ''), 0, 100);
        $p2lat   = (($_POST['p2lat']  ?? '') !== '') ? (float)$_POST['p2lat']  : null;
        $p2lon   = (($_POST['p2lon']  ?? '') !== '') ? (float)$_POST['p2lon']  : null;
        $p2blood = in_array($_POST['p2blood'] ?? '', ['A','B','AB','O']) ? $_POST['p2blood'] : null;
        $p2mbti  = in_array(strtoupper($_POST['p2mbti'] ?? ''), $valid_mbti2) ? strtoupper($_POST['p2mbti']) : null;
        if (!$p2name || !$p2dob) { echo json_encode(['error'=>'Name and DOB required']); exit; }

        $stmt = $conn->prepare("INSERT INTO bio_compat_saves
            (user_id,person2_name,person2_dob,person2_time,person2_city,person2_lat,person2_lon,person2_blood_type,person2_mbti_type)
            VALUES (?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE person2_time=VALUES(person2_time), person2_city=VALUES(person2_city),
            person2_lat=VALUES(person2_lat), person2_lon=VALUES(person2_lon),
            person2_blood_type=VALUES(person2_blood_type), person2_mbti_type=VALUES(person2_mbti_type), saved_at=NOW()");
        if (!$stmt) {
            $conn->query("ALTER TABLE bio_compat_saves ADD UNIQUE KEY uk_save (user_id, person2_name(80), person2_dob)");
            $stmt = $conn->prepare("INSERT INTO bio_compat_saves
                (user_id,person2_name,person2_dob,person2_time,person2_city,person2_lat,person2_lon,person2_blood_type,person2_mbti_type)
                VALUES (?,?,?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE person2_time=VALUES(person2_time), person2_city=VALUES(person2_city),
                person2_lat=VALUES(person2_lat), person2_lon=VALUES(person2_lon),
                person2_blood_type=VALUES(person2_blood_type), person2_mbti_type=VALUES(person2_mbti_type), saved_at=NOW()");
        }
        $stmt->bind_param('issssddss', $uid, $p2name, $p2dob, $p2time, $p2city, $p2lat, $p2lon, $p2blood, $p2mbti);
        $stmt->execute();
        echo json_encode(['ok'=>true, 'id'=>(int)$conn->insert_id]);
        exit;
    }

    if ($action === 'load_compat_saves') {
        $res = $conn->prepare("SELECT id,person2_name,person2_dob,person2_time,person2_city,person2_lat,person2_lon,person2_blood_type,person2_mbti_type,saved_at FROM bio_compat_saves WHERE user_id=? ORDER BY saved_at DESC LIMIT 30");
        $res->bind_param('i', $uid); $res->execute();
        $rows = $res->get_result()->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['ok'=>true, 'saves'=>$rows]);
        exit;
    }

    if ($action === 'delete_compat_save') {
        $sid = (int)($_POST['sid'] ?? 0);
        if (!$sid) { echo json_encode(['error'=>'No id']); exit; }
        $stmt = $conn->prepare("DELETE FROM bio_compat_saves WHERE id=? AND user_id=?");
        $stmt->bind_param('ii', $sid, $uid); $stmt->execute();
        echo json_encode(['ok'=>true]);
        exit;
    }

    echo json_encode(['error'=>'Unknown action']);
    exit;
}

// ── Load initial data for logged-in user ──
$initUser = null;
$initLog  = '{}';
$initMs   = '{}';
$initOracle = '[]';

if ($db_ok && !empty($_SESSION['bio_uid'])) {
    $uid = (int)$_SESSION['bio_uid'];
    // Pull location fields from DB so existing sessions get them too
    $urow = null;
    $ustmt = $conn->prepare("SELECT birth_time, birth_city, birth_lat, birth_lon, blood_type, mbti_type, level FROM bio_users WHERE id=? LIMIT 1");
    if ($ustmt) { $ustmt->bind_param('i', $uid); $ustmt->execute(); $urow = $ustmt->get_result()->fetch_assoc(); }
    // Load block config for this user's level
    $userLevel = $urow['level'] ?? 'silver';
    $blockCfg  = [];
    if ($db_ok) {
        $bres = $conn->query("SELECT block_key, block_label, sort_order, silver, gold, platinum FROM bio_block_config ORDER BY sort_order");
        if ($bres) while ($br = $bres->fetch_assoc()) {
            $blockCfg[$br['block_key']] = ['visible' => (bool)$br[$userLevel], 'order' => (int)$br['sort_order'], 'label' => $br['block_label']];
        }
    }
    $initUser = ['uid'=>$uid,'name'=>$_SESSION['bio_name'],'dob'=>$_SESSION['bio_dob'],'level'=>$userLevel,
        'birth_time' => $urow['birth_time'] ?? '',
        'birth_city' => $urow['birth_city'] ?? '',
        'birth_lat'  => isset($urow['birth_lat'])  && $urow['birth_lat']  !== null ? (float)$urow['birth_lat']  : null,
        'birth_lon'  => isset($urow['birth_lon'])  && $urow['birth_lon']  !== null ? (float)$urow['birth_lon']  : null,
        'blood_type' => $urow['blood_type'] ?? null,
        'mbti_type'  => $urow['mbti_type']  ?? null,
        'need_pin'   => !empty($_SESSION['bio_need_pin'])];

    $res = $conn->query("SELECT log_date,physical,emotional,intellectual,luck,notes FROM bio_daily_log WHERE user_id=$uid ORDER BY log_date");
    $logs = [];
    while ($r = $res->fetch_assoc()) {
        $logs[$r['log_date']] = ['p'=>(int)$r['physical'],'e'=>(int)$r['emotional'],'i'=>(int)$r['intellectual'],'luck'=>(int)$r['luck'],'notes'=>$r['notes']];
    }
    $initLog = json_encode($logs);

    $res2 = $conn->query("SELECT milestone_id,ms_year,ms_month,ms_day FROM bio_milestones WHERE user_id=$uid");
    $mss  = [];
    while ($r = $res2->fetch_assoc()) {
        $mss[(int)$r['milestone_id']] = ['year'=>$r['ms_year'],'month'=>$r['ms_month'],'day'=>$r['ms_day']];
    }
    $initMs = json_encode($mss);

    $ostmt = $conn->prepare("SELECT id,session_key,question,score,mhb_score,quran_score,bible_score,kau_score,tarot_score,tehillim_score,ram_score,oracle_count,asked_at
        FROM bio_oracle_readings WHERE user_id=? ORDER BY asked_at ASC,id ASC LIMIT 1000");
    if ($ostmt) {
        $ostmt->bind_param('i', $uid); $ostmt->execute();
        $oracle = [];
        $ors = $ostmt->get_result();
        while ($r = $ors->fetch_assoc()) {
            $r['id']=(int)$r['id']; $r['score']=(float)$r['score']; $r['oracle_count']=(int)$r['oracle_count'];
            foreach (['mhb_score','quran_score','bible_score','kau_score','tarot_score','tehillim_score','ram_score'] as $k) $r[$k]=$r[$k]===null?null:(int)$r[$k];
            $oracle[]=$r;
        }
        $initOracle = json_encode($oracle);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#101d21">
<link rel="manifest" href="/manifest.webmanifest">
<link rel="icon" href="/favicon.ico?v=2" sizes="any">
<link rel="icon" type="image/png" sizes="192x192" href="/app-icons/icon-192.png?v=2">
<link rel="apple-touch-icon" href="/app-icons/apple-touch-icon.png?v=2">
<title>My DharmaVera | Personal Biorhythm</title>
<meta name="description" content="Explore your personal biorhythm chart and keep your readings in My DharmaVera.">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script src="baydin_ephemeris.js"></script>
<style>
  :root {
    --bg-dark: #090c10;
    --bg-card: rgba(22, 27, 34, 0.6);
    --bg-card2: rgba(28, 33, 40, 0.8);
    --accent: #58a6ff;
    --accent-glow: rgba(88, 166, 255, 0.25);
    --border: rgba(48, 54, 61, 0.8);
    --text: #e6edf3;
    --muted: #8b949e;
  }
  
  body { 
    background: var(--bg-dark); 
    color: var(--text); 
    font-family: 'Segoe UI', system-ui, sans-serif; 
    min-height: 100vh; 
  }

  /* ── Modern Hero Section ── */
  .hero {
    background: radial-gradient(circle at top right, #1a1f3a, #090c10 70%);
    border-bottom: 1px solid var(--border);
    padding: 2.5rem 0 2rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
  }
  .hero h1 { font-size: 2.2rem; font-weight: 800; color: #fff; letter-spacing: -0.5px; }
  .hero .subtitle { color: var(--muted); font-size: 1rem; margin-top: 0.5rem; }

  /* ── Glassmorphism Cards ── */
  .card-dark {
    background: var(--bg-card);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid var(--border);
    border-radius: 14px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.2);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
  }
  .card-dark2 {
    background: var(--bg-card2);
    border: 1px solid var(--border);
    border-radius: 12px;
  }

  /* ── Forms & Inputs ── */
  .form-control, .form-select, .input-group-text {
    background: rgba(13, 17, 23, 0.8) !important;
    border: 1px solid var(--border) !important;
    color: var(--text) !important;
    border-radius: 10px !important;
    padding: 0.6rem 1rem;
  }
  .slider-range-labels { display:flex; justify-content:space-between; font-size:.72rem; color:var(--muted); margin-top:.2rem; }
  .form-control:focus, .form-select:focus {
    border-color: var(--accent) !important;
    box-shadow: 0 0 0 3px var(--accent-glow) !important;
  }
  label { color: var(--muted); font-size: 0.85rem; font-weight: 600; letter-spacing: 0.5px; text-transform: uppercase; }

  /* ── Premium Buttons ── */
  .btn-primary {
    background: linear-gradient(135deg, #1f6feb, #388bfd);
    border: none; 
    border-radius: 10px; 
    font-weight: 600;
    padding: 0.7rem 2.5rem; 
    font-size: 1.05rem;
    box-shadow: 0 4px 15px var(--accent-glow);
    transition: all 0.2s ease;
  }
  .btn-primary:hover { 
    transform: translateY(-2px); 
    box-shadow: 0 6px 20px rgba(88, 166, 255, 0.4);
  }

  /* ── UI Elements ── */
  .score-ring {
    width: 65px; height: 65px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.4rem; font-weight: 800; flex-shrink: 0;
    border: 3.5px solid;
    background: rgba(0,0,0,0.2);
  }
  .overall-ring {
    width: 130px; height: 130px; border-radius: 50%;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    font-weight: 800; border: 5px solid;
    background: rgba(0,0,0,0.2);
    box-shadow: 0 0 20px rgba(0,0,0,0.1) inset;
  }
  .progress-custom { height: 8px; border-radius: 4px; background: rgba(0,0,0,0.4); overflow: hidden; }
  .bar-fill { height: 100%; border-radius: 4px; transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1); }
  
  .status-badge {
    display: inline-flex; align-items: center; padding: 4px 12px; border-radius: 20px;
    font-size: 0.72rem; font-weight: 800; letter-spacing: 0.5px; text-transform: uppercase;
  }
  .badge-do      { background: rgba(92, 184, 92, 0.15); color: #5cb85c; border: 1px solid rgba(92, 184, 92, 0.4); }
  .badge-ok      { background: rgba(72, 202, 228, 0.15); color: #48cae4; border: 1px solid rgba(72, 202, 228, 0.4); }
  .badge-caution { background: rgba(240, 173, 78, 0.15); color: #f0ad4e; border: 1px solid rgba(240, 173, 78, 0.4); }
  .badge-dont    { background: rgba(224, 82, 82, 0.15); color: #e05252; border: 1px solid rgba(224, 82, 82, 0.4); }
  
  .section-title { 
    font-size: 0.75rem; font-weight: 800; letter-spacing: 1.5px; 
    color: var(--muted); text-transform: uppercase; margin-bottom: 1rem; 
    display: flex; align-items: center; gap: 8px;
  }
  #mainTabs { display:flex; flex-wrap:wrap; gap:.4rem; border-bottom:1px solid var(--border); }
  #mainTabs .nav-link { border:1px solid transparent; border-radius:10px 10px 0 0; color:var(--muted); }
  #mainTabs .nav-link.active { color:var(--text); background:var(--bg-card); border-color:var(--border) var(--border) var(--bg-card); }
  #mainTabs .harmony-tab {
    color:#e7d5ff; border-color:rgba(199,125,255,.42);
    background:linear-gradient(135deg,rgba(199,125,255,.16),rgba(88,166,255,.12));
    box-shadow:0 0 0 1px rgba(199,125,255,.08),0 5px 18px rgba(120,82,180,.13);
    font-weight:800;
  }
  #mainTabs .harmony-tab:hover,#mainTabs .harmony-tab.active {
    color:#fff; border-color:#c77dff;
    background:linear-gradient(135deg,rgba(199,125,255,.32),rgba(88,166,255,.24));
    box-shadow:0 0 18px rgba(199,125,255,.25);
  }
  .harmony-tab-note { font-size:.58rem; margin-left:.35rem; color:#d8b4fe; letter-spacing:.35px; text-transform:uppercase; }
  .mm-text { font-family: 'Noto Sans Myanmar', sans-serif; font-size: 1.05rem; }

  /* ── Responsive Refinements ── */
  @media(max-width:576px) {
    .hero h1 { font-size: 1.7rem; }
    .score-ring { width: 55px; height: 55px; font-size: 1.2rem; }
    .overall-ring { width: 100px; height: 100px; }
    .container { padding-left: 16px; padding-right: 16px; }
  }

  /* ── Login Box ── */
  #login-overlay {
    display: flex; justify-content: center;
    padding: 2rem 1rem;
  }
  .login-box {
    background: var(--bg-card2); border: 1px solid var(--border);
    border-radius: 18px; width: 100%; max-width: 520px;
    box-shadow: 0 24px 64px rgba(0,0,0,0.55);
  }
  .login-header { padding: 2rem 2rem 1.25rem; text-align: center; border-bottom: 1px solid var(--border); }
  .login-body   { padding: 1.5rem 2rem 2rem; }
</style>
<link rel="stylesheet" href="dharmavera.css?v=12">
</head>
<body>

<!-- ── Login Overlay ── -->
<div id="pin-setup-overlay" class="dv-pin-overlay" style="display:none" role="dialog" aria-modal="true" aria-labelledby="pin-setup-title">
  <div class="login-box" style="max-width:420px">
    <div class="login-header">
      <img class="dv-logo dv-login-logo" src="assets/brand/logo-160.png?v=1" width="160" height="160" alt="">
      <h2 id="pin-setup-title" style="font-size:1.2rem;font-weight:800;color:#fff;margin:0 0 .35rem">Protect your profile</h2>
      <p style="font-size:.82rem;color:var(--muted);margin:0;line-height:1.6">Create a 4-digit PIN. From now on you will sign in with your name, date of birth and this PIN, so nobody else can open your readings.</p>
    </div>
    <div class="login-body">
      <div class="row g-3 mb-3">
        <div class="col-6"><label class="form-label" for="setup-pin">New PIN</label><input type="password" id="setup-pin" class="form-control dv-pin" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" autocomplete="new-password" placeholder="••••"></div>
        <div class="col-6"><label class="form-label" for="setup-pin2">Confirm PIN</label><input type="password" id="setup-pin2" class="form-control dv-pin" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" autocomplete="new-password" placeholder="••••"></div>
      </div>
      <button class="btn btn-primary w-100 py-2" onclick="savePinSetup()"><i class="bi bi-shield-lock me-2"></i>Save my PIN</button>
      <div id="setup-pin-error" style="color:var(--red);font-size:.82rem;margin-top:.75rem;display:none"></div>
      <div style="text-align:center;margin-top:.8rem"><a href="#" onclick="doLogout();return false;" style="font-size:.78rem;color:var(--muted)">Sign out</a></div>
    </div>
  </div>
</div>
<div id="login-overlay" <?= $initUser ? 'style="display:none"' : '' ?>>
  <div class="login-box">
    <div class="login-header">
      <img class="dv-logo dv-login-logo" src="assets/brand/logo-400.png?v=1" width="400" height="400" alt="DharmaVera logo">
      <h2 style="font-size:1.25rem;font-weight:800;color:#fff;margin:0 0 .35rem">My DharmaVera</h2>
      <p style="font-size:.8rem;color:var(--muted);margin:0;line-height:1.6">Enter your name, date of birth and 4-digit PIN.<br>Birth time, city and blood type are optional and unlock extra features.</p>
    </div>
    <div class="login-body">
      <div class="row g-3 mb-3">
        <div class="col-12 col-sm-7">
          <label class="form-label"><i class="bi bi-person me-1"></i>Your Name</label>
          <input type="text" id="login-name" class="form-control" placeholder="First name or nickname" autocomplete="name">
          <div style="font-size:.72rem;color:var(--green);margin-top:4px"><i class="bi bi-shield-check me-1"></i>Private — just for you.</div>
        </div>
        <div class="col-12 col-sm-5">
          <label class="form-label"><i class="bi bi-calendar3 me-1"></i>Date of Birth</label>
          <input type="date" id="login-dob" class="form-control" style="color-scheme:dark;min-width:160px">
        </div>
      </div>
      <div class="row g-3 mb-3">
        <div class="col-6">
          <label class="form-label" for="login-pin"><i class="bi bi-shield-lock me-1"></i>4-Digit PIN</label>
          <input type="password" id="login-pin" class="form-control dv-pin" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" autocomplete="current-password" placeholder="••••">
        </div>
        <div class="col-6" id="login-pin2-wrap" style="display:none">
          <label class="form-label" for="login-pin2"><i class="bi bi-shield-check me-1"></i>Confirm PIN</label>
          <input type="password" id="login-pin2" class="form-control dv-pin" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" autocomplete="new-password" placeholder="••••">
        </div>
        <div class="col-12" style="font-size:.72rem;color:var(--muted);margin-top:.3rem" id="login-pin-help">New here, or signing in for the first time since PINs were added? Choose any 4 digits — you will confirm them once. Forgot your PIN? Email <a href="mailto:app@southrule.com" style="color:var(--accent)">app@southrule.com</a>.</div>
      </div>
      <div class="row g-3 mb-3">
        <div class="col-6">
          <label class="form-label"><i class="bi bi-clock me-1"></i>Birth Time <span class="opt">(optional)</span></label>
          <input type="time" id="login-time" class="form-control" style="color-scheme:dark">
        </div>
        <div class="col-6">
          <label class="form-label"><i class="bi bi-droplet-half me-1"></i>Blood Type <span class="opt">(optional)</span></label>
          <select id="login-blood" class="form-select" aria-describedby="login-blood-note">
            <option value="">— Select —</option>
            <option value="A">A</option><option value="B">B</option>
            <option value="AB">AB</option><option value="O">O</option>
          </select>
          <div id="login-blood-note" style="font-size:.7rem;color:var(--muted);margin-top:4px">Only used for the blood-type harmony feature.</div>
        </div>
      </div>
      <div class="mb-4" style="position:relative">
        <label class="form-label"><i class="bi bi-geo-alt me-1"></i>Birth City <span class="opt">(optional — improves accuracy)</span></label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-search"></i></span>
          <input type="text" id="login-city" class="form-control" placeholder="e.g. Yangon, Tokyo, London"
            autocomplete="off" oninput="loginCitySearch(this.value)">
        </div>
        <div id="login-city-dropdown" class="ac-dropdown"></div>
        <div id="login-city-status" style="font-size:.72rem;margin-top:4px;display:none"></div>
      </div>
      <button class="btn btn-primary w-100 py-2" onclick="doLogin()" style="font-size:1rem">
        <i class="bi bi-box-arrow-in-right me-2"></i>Enter &amp; Save My Profile
      </button>
      <div id="login-error" style="color:var(--red);font-size:.82rem;margin-top:.75rem;display:none"></div>
      <?php if (!$db_ok): ?>
      <div class="alert-warm mt-3"><i class="bi bi-exclamation-triangle me-1"></i>Database unavailable — data stored locally only.</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ── Hero ── -->
<div class="hero">
  <div class="container" style="max-width:900px">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 pb-3">
      <div class="d-flex align-items-center gap-3">
        <img class="dv-logo dv-header-logo" src="assets/brand/logo-400.png?v=1" width="400" height="400" alt="DharmaVera logo">
        <div>
          <div class="hero-title">My DharmaVera</div>
          <div class="hero-sub"><i class="bi bi-activity me-1"></i>Track · Understand · Forecast your inner cycles</div>
        </div>
      </div>
      <div class="d-flex gap-2 align-items-center flex-wrap">
        <div id="hero-username" style="font-size:.85rem;color:var(--accent);display:flex;align-items:center;gap:6px">
          <?php if ($initUser): ?>
            <i class="bi bi-person-check-fill"></i>
            <?= htmlspecialchars($initUser['name']) ?>
            <span class="level-badge level-<?= $initUser['level'] ?? 'silver' ?>">
              <?php $lv = $initUser['level'] ?? 'silver'; echo $lv==='platinum'?'💎 Platinum':($lv==='gold'?'⭐ Gold':'🔘 Silver'); ?>
            </span>
          <?php endif; ?>
        </div>
        <button id="btn-logout" class="btn btn-outline-secondary btn-sm" onclick="doLogout()" <?= $initUser ? '' : 'style="display:none"' ?>>
          <i class="bi bi-box-arrow-right me-1"></i>Sign Out
        </button>
        <a href="index.php" class="btn btn-outline-secondary btn-sm">
          <i class="bi bi-grid-3x3-gap me-1"></i>Astrology
        </a>
      </div>
    </div>
  </div>
  <div class="hero-accent"></div>
</div>

<div class="container py-4" style="max-width:900px<?= $initUser ? '' : ';display:none' ?>" id="bio-main-content">

  <!-- ── Oracle Detail Section (the member's own reading, from oracle_api.php) ── -->
  <div id="oracle-detail-section" style="display:none;margin-bottom:1.5rem"></div>

  <!-- TABS -->
  <ul class="nav nav-tabs mb-4" id="mainTabs">
    <li class="nav-item" style="order:-1"><button class="nav-link active" data-tab="profile"><i class="bi bi-person-badge me-1"></i>Profile</button></li>
    <li class="nav-item" style="order:<?= _blkO('daily',0) ?>"><button class="nav-link" data-tab="daily"><i class="bi bi-pencil-square me-1"></i><?= _blkL('daily','Daily Log') ?></button></li>
    <li class="nav-item" style="order:<?= _blkO('chart',1) ?>"><button class="nav-link" data-tab="chart"><i class="bi bi-graph-up-arrow me-1"></i><?= _blkL('chart','My Chart') ?></button></li>
    <li class="nav-item" style="order:<?= _blkO('history',2) ?>"><button class="nav-link" data-tab="history"><i class="bi bi-table me-1"></i><?= _blkL('history','History') ?></button></li>
    <li class="nav-item" style="order:<?= _blkO('compat',3) ?>"><button type="button" class="nav-link harmony-tab" data-tab="compat"><i class="bi bi-hearts me-1"></i><?= _blkL('compat','Harmony') ?><span class="harmony-tab-note">Match</span></button></li>
  </ul>

  <!-- ══ TAB: PROFILE ══ -->
  <div id="tab-profile">

    <!-- Identity card (readonly) -->
    <div class="card-dark p-4 mb-4" style="border-left:4px solid var(--accent)">
      <div class="section-title"><i class="bi bi-fingerprint"></i>Identity — Login Credentials</div>
      <div class="row g-3 mb-3">
        <div class="col-sm-6">
          <label class="form-label"><i class="bi bi-person me-1"></i>Name</label>
          <input type="text" id="p-name" class="form-control" readonly style="opacity:.65;cursor:default">
        </div>
        <div class="col-sm-6">
          <label class="form-label"><i class="bi bi-calendar3 me-1"></i>Date of Birth</label>
          <input type="date" id="p-dob" class="form-control" readonly style="color-scheme:dark;opacity:.65;cursor:default">
        </div>
      </div>
      <div style="font-size:.77rem;color:var(--muted)"><i class="bi bi-lock me-1"></i>Name and DOB are your login credentials and cannot be changed.</div>
    </div>

    <!-- Editable details -->
    <div class="card-dark p-4 mb-4" style="border-left:4px solid var(--accent2)">
      <div class="section-title"><i class="bi bi-sliders"></i>Personal Details <span style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--muted);font-size:.75rem">— update anytime</span></div>
      <div class="row g-3">
        <div class="col-sm-4">
          <label class="form-label"><i class="bi bi-clock me-1"></i>Birth Time <span class="opt">(optional)</span></label>
          <input type="time" id="p-btime" class="form-control" style="color-scheme:dark">
        </div>
        <div class="col-sm-5">
          <label class="form-label"><i class="bi bi-geo-alt me-1"></i>Birth City <span class="opt">(optional)</span></label>
          <div style="position:relative">
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-search"></i></span>
              <input type="text" id="p-bcity" class="form-control" placeholder="e.g. Yangon, Tokyo"
                autocomplete="off" oninput="profileCitySearch(this.value)">
            </div>
            <div id="p-city-dropdown" class="ac-dropdown"></div>
          </div>
        </div>
        <div class="col-sm-3">
          <label class="form-label"><i class="bi bi-droplet-half me-1"></i>Blood Type <span class="opt">(optional)</span></label>
          <select id="p-blood" class="form-select">
            <option value="">— —</option>
            <option value="A">A</option><option value="B">B</option>
            <option value="AB">AB</option><option value="O">O</option>
          </select>
        </div>
      </div>

      <!-- MBTI — Platinum only -->
      <!-- HACKATHON WORK (new): id + server-side hide so Silver members and guests never see this Platinum Personality preference box at all (not even greyed out). The code below is unchanged, only hidden. -->
      <div class="mt-3 pt-3" id="platinum-personality-box" style="border-top:1px solid var(--border)<?= (($initUser['level'] ?? 'silver') === 'platinum') ? '' : ';display:none' ?>">
        <div class="d-flex align-items-center gap-2 mb-2">
          <span class="level-badge level-platinum">💎 Platinum</span>
          <span style="font-size:.78rem;color:var(--muted)">Personality preference — adds communication-style reflection to Harmony</span>
        </div>
        <div id="mbti-selector-wrap" style="display:none">
        <div class="col-sm-12">
          <div class="row g-3 align-items-start">
            <div class="col-sm-4">
              <label class="form-label"><i class="bi bi-person-bounding-box me-1"></i>Your Personality Type <span class="opt">(optional)</span></label>
              <select id="p-mbti" class="form-select" onchange="showMBTIDesc('p-mbti','p-mbti-desc')">
                <option value="">— Select your type —</option>
                <optgroup label="Analysts (NT)">
                  <option value="INTJ">INTJ — The Architect</option>
                  <option value="INTP">INTP — The Thinker</option>
                  <option value="ENTJ">ENTJ — The Commander</option>
                  <option value="ENTP">ENTP — The Debater</option>
                </optgroup>
                <optgroup label="Diplomats (NF)">
                  <option value="INFJ">INFJ — The Advocate</option>
                  <option value="INFP">INFP — The Mediator</option>
                  <option value="ENFJ">ENFJ — The Protagonist</option>
                  <option value="ENFP">ENFP — The Campaigner</option>
                </optgroup>
                <optgroup label="Sentinels (SJ)">
                  <option value="ISTJ">ISTJ — The Logistician</option>
                  <option value="ISFJ">ISFJ — The Defender</option>
                  <option value="ESTJ">ESTJ — The Executive</option>
                  <option value="ESFJ">ESFJ — The Consul</option>
                </optgroup>
                <optgroup label="Explorers (SP)">
                  <option value="ISTP">ISTP — The Virtuoso</option>
                  <option value="ISFP">ISFP — The Adventurer</option>
                  <option value="ESTP">ESTP — The Entrepreneur</option>
                  <option value="ESFP">ESFP — The Entertainer</option>
                </optgroup>
              </select>
            </div>
            <div class="col-sm-8 d-flex align-items-end pb-1">
              <div id="p-mbti-desc" style="display:none;font-size:.82rem;color:#ccc;line-height:1.7;background:var(--bg-card2);border:1px solid var(--border);border-radius:10px;padding:.65rem .9rem;width:100%"></div>
            </div>
          </div>
          <div class="mt-2" style="font-size:.75rem;color:var(--muted)">
            <i class="bi bi-info-circle me-1"></i>Don't know your type?
            <a href="personality.html#quick-check" target="_blank" rel="noopener" style="color:var(--accent)">Try 12 questions or explore all 16 types →</a>
          </div>
        </div>
        </div>
        <div id="mbti-locked-wrap">
        <div class="card-dark2 p-3 d-flex align-items-center gap-3" style="border-radius:10px;border:1px dashed var(--border);max-width:360px;opacity:.7">
          <i class="bi bi-lock-fill" style="font-size:1.5rem;color:var(--accent2)"></i>
          <div style="font-size:.82rem;color:var(--muted)">Personality type selection is available for <strong style="color:var(--accent2)">Platinum</strong> members. Log entries and engagement unlock higher levels.</div>
        </div>
        </div>
      </div>

      <div class="d-flex align-items-center gap-3 mt-3">
        <button class="btn btn-primary px-4" onclick="saveDetails()">
          <i class="bi bi-floppy me-2"></i>Update Details
        </button>
        <span id="details-saved" class="msg-ok" style="display:none"><i class="bi bi-check-circle-fill me-1"></i>Saved!</span>
      </div>
    </div>

    <!-- Life Milestones -->
    <?php if ((($blockCfg ?? [])['milestones']['visible'] ?? true)): ?>
    <div class="card-dark p-4 mb-4" style="border-left:4px solid #FFD700">
      <div class="section-title"><i class="bi bi-calendar2-heart"></i>
        <?= _blkL('milestones','Life Milestone Dates') ?>
        <span style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--muted);font-size:.75rem">— year required; month &amp; day optional</span>
      </div>
      <?= _blkC('milestones') ?>
      <div id="milestones-form"></div>
    </div>
    <?php endif; ?>

    <div class="text-center mb-2">
      <button class="btn btn-primary px-5" onclick="saveProfile()">
        <i class="bi bi-floppy me-2"></i>Save Milestones
      </button>
      <span id="profile-saved" class="msg-ok ms-3" style="display:none"><i class="bi bi-check-circle-fill me-1"></i>Saved!</span>
    </div>

    <!-- Change PIN -->
    <div class="card-dark p-4 mt-4" id="change-pin-card">
      <div class="section-title"><i class="bi bi-shield-lock"></i> Change My PIN</div>
      <div class="row g-2 align-items-end">
        <div class="col-4"><label class="form-label" for="pin-current" style="font-size:.78rem">Current</label><input type="password" id="pin-current" class="form-control dv-pin" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" autocomplete="current-password"></div>
        <div class="col-4"><label class="form-label" for="pin-new" style="font-size:.78rem">New</label><input type="password" id="pin-new" class="form-control dv-pin" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" autocomplete="new-password"></div>
        <div class="col-4"><label class="form-label" for="pin-new2" style="font-size:.78rem">Confirm</label><input type="password" id="pin-new2" class="form-control dv-pin" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" autocomplete="new-password"></div>
      </div>
      <button type="button" class="btn btn-outline-secondary mt-3" onclick="changePin()"><i class="bi bi-key me-1"></i>Update PIN</button>
      <div id="pin-change-msg" class="mt-2" style="display:none;font-size:.85rem"></div>
    </div>

    <!-- Delete account -->
    <div class="card-dark p-4 mt-4 mb-4" id="delete-account-card" style="border-left:4px solid #e05252">
      <div class="section-title" style="color:#e05252"><i class="bi bi-trash3"></i> Delete My Account</div>
      <p class="mb-2" style="color:var(--muted);font-size:.9rem">
        This permanently deletes your profile, daily logs, milestones, saved Harmony readings, Oracle Pulse questions, and dUSD balance. It cannot be undone.
      </p>
      <label for="delete-confirm" class="form-label" style="font-size:.85rem">Type <strong>DELETE</strong> to confirm</label>
      <div class="d-flex flex-wrap align-items-center gap-2">
        <input type="text" id="delete-confirm" class="form-control" style="max-width:200px" autocomplete="off" autocapitalize="characters" spellcheck="false">
        <button type="button" class="btn btn-outline-danger" onclick="deleteAccount()">
          <i class="bi bi-trash3 me-1"></i>Delete my account
        </button>
      </div>
      <div id="delete-error" class="mt-2" style="display:none;color:#e05252;font-size:.85rem"></div>
    </div>
  </div>

  <!-- ══ TAB: DAILY LOG ══ -->
<div id="tab-daily" style="display:none">
  <?= _blkC('daily') ?>

    <!-- Inspirational banner -->
    <div class="card-dark p-4 mb-4" style="border-left:4px solid var(--accent);background:linear-gradient(135deg,rgba(88,166,255,.05),rgba(199,125,255,.05))">
      <div style="font-size:1rem;font-weight:700;color:var(--accent);letter-spacing:.3px;margin-bottom:.6rem">
        <i class="bi bi-infinity me-2"></i>The Rhythm of You
      </div>
      <p style="font-size:.87rem;color:#ccc;line-height:1.85;margin:0">
        Everything in nature cycles, and you are no exception. By observing your physical and mental fluctuations, you move from being a passenger to the driver of your own life. Be honest with your data, track your internal shifts, and gain the insight to forecast your own journey.
      </p>
    </div>

    <!-- Log cards generated by JS (buildLogCard) -->
    <div id="log-cards-container"></div>

    <!-- Progress -->
    <div class="card-dark p-4 mb-4" style="border-left:4px solid var(--c-int)">
      <div class="section-title"><i class="bi bi-bar-chart-steps"></i>Progress to Full Chart</div>
      <div class="row g-3 text-center mb-3">
        <div class="col-4">
          <div class="stat-val" style="color:var(--c-phys)" id="prog-days">0</div>
          <div class="stat-label"><i class="bi bi-journal-check me-1"></i>Days logged</div>
        </div>
        <div class="col-4">
          <div class="stat-val" style="color:var(--c-emo)" id="prog-needed">33</div>
          <div class="stat-label"><i class="bi bi-hourglass-split me-1"></i>Days needed</div>
        </div>
        <div class="col-4">
          <div class="stat-val" style="color:var(--c-int)" id="prog-pct">0%</div>
          <div class="stat-label"><i class="bi bi-percent me-1"></i>Complete</div>
        </div>
      </div>
      <div class="progress-bar-custom mb-2"><div class="progress-fill" id="prog-bar" style="width:0%;background:linear-gradient(90deg,var(--c-phys),var(--c-int))"></div></div>
      <div style="font-size:.78rem;color:var(--muted);text-align:center" id="prog-msg">Log your feelings daily. After 33 days your personalized chart will be ready.</div>
    </div>
  </div>

  <!-- ══ TAB: CHART ══ -->
<div id="tab-chart" style="display:none">
  <?= _blkC('chart') ?>
    <div id="chart-no-data" class="card-dark p-4 text-center mb-4" style="display:none">
      <div style="font-size:2.5rem">📊</div>
      <div style="font-size:1rem;color:var(--muted);margin-top:.5rem">No data yet. Log some daily entries first.</div>
    </div>
    <div class="card-dark p-3 mb-3" id="chart-legend" style="display:none">
      <div class="d-flex flex-wrap gap-3" style="font-size:.8rem">
        <span><span style="display:inline-block;width:24px;height:3px;background:#FF6B35;vertical-align:middle;border-radius:2px;margin-right:5px"></span>Physical Index</span>
        <span><span style="display:inline-block;width:24px;height:3px;background:#4FC3F7;vertical-align:middle;border-radius:2px;margin-right:5px"></span>Emotional Index</span>
        <span><span style="display:inline-block;width:24px;height:3px;background:#8338EC;vertical-align:middle;border-radius:2px;margin-right:5px"></span>Intellectual Index</span>
        <span><span style="display:inline-block;width:24px;height:3px;background:#FFD700;vertical-align:middle;border-radius:2px;margin-right:5px"></span>Fortune Index</span>
        <span><span style="display:inline-block;width:24px;height:3px;background:#ff4d5f;vertical-align:middle;border-radius:2px;margin-right:5px"></span>Oracle Pulse</span>
        <span><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#FF6B35;vertical-align:middle;margin-right:5px"></span>Physical Check-in</span>
        <span><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#4FC3F7;vertical-align:middle;margin-right:5px"></span>Emotional Check-in</span>
        <span><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#8338EC;vertical-align:middle;margin-right:5px"></span>Intellectual Check-in</span>
      </div>
    </div>
    <div class="card-dark p-4 mb-4" id="chart-wrap" style="display:none">
      <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div class="section-title mb-0" id="chart-title">Personal Rhythm Chart</div>
        <select id="chart-range" class="form-select form-select-sm" style="width:130px" onchange="drawChart()">
          <option value="60">60 days</option>
          <option value="90" selected>90 days</option>
          <option value="120">120 days</option>
        </select>
      </div>
      <div class="chart-wrapper"><canvas id="myBioChart"></canvas></div>
    </div>
    <div class="card-dark p-4 mb-4" id="oracle-pulse-summary" style="border-left:4px solid #ff4d5f">
      <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
        <div>
          <div class="section-title mb-1" style="color:#ff7b86"><i class="bi bi-heart-pulse-fill me-1"></i>Oracle Pulse</div>
          <div style="font-size:.8rem;color:#bbb">The red line above is the daily average of your saved oracle answers.</div>
        </div>
        <a href="index.php#live-mahabote" class="btn btn-outline-secondary btn-sm"><i class="bi bi-stars me-1"></i>Ask another question</a>
      </div>
      <div class="row g-3 text-center mb-3">
        <div class="col-4"><div id="pulse-average" style="font-size:1.35rem;font-weight:850;color:#ff4d5f">—</div><div class="stat-label">Average / 10</div></div>
        <div class="col-4"><div id="pulse-count" style="font-size:1.35rem;font-weight:850;color:var(--accent)">0</div><div class="stat-label">Questions saved</div></div>
        <div class="col-4"><div id="pulse-latest" style="font-size:1rem;font-weight:750;color:#e6edf3">—</div><div class="stat-label">Latest answer</div></div>
      </div>
      <div id="oracle-pulse-table"></div>
      <div style="font-size:.7rem;color:var(--muted);margin-top:.8rem"><i class="bi bi-shield-lock me-1"></i>Your question text stays in your account. The admin dashboard uses anonymous daily averages.</div>
    </div>
    <div class="card-dark p-4 mb-4" id="accuracy-card" style="display:none">
      <div class="section-title"><i class="bi bi-bullseye me-1"></i>Prediction Accuracy</div>
      <div class="row g-3 text-center">
        <div class="col-4"><div style="font-size:1.4rem;font-weight:700;color:#FF6B35" id="acc-p">—</div><div style="font-size:.75rem;color:var(--muted)">Physical</div></div>
        <div class="col-4"><div style="font-size:1.4rem;font-weight:700;color:#4FC3F7" id="acc-e">—</div><div style="font-size:.75rem;color:var(--muted)">Emotional</div></div>
        <div class="col-4"><div style="font-size:1.4rem;font-weight:700;color:#8338EC" id="acc-i">—</div><div style="font-size:.75rem;color:var(--muted)">Intellectual</div></div>
      </div>
    </div>
    <div class="card-dark p-4 mb-4" id="milestones-legend" style="display:none">
      <div class="section-title"><i class="bi bi-flag me-1"></i>Life Milestones on Chart</div>
      <div id="milestones-list-chart"></div>
    </div>
  </div>

  <!-- ══ TAB: HISTORY ══ -->
<div id="tab-history" style="display:none">
  <?= _blkC('history') ?>
    <div class="card-dark p-4 mb-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="section-title mb-0"><i class="bi bi-table me-1"></i>Daily Log History</div>
        <button class="btn btn-outline-danger btn-sm" onclick="clearAllData()"><i class="bi bi-trash me-1"></i>Clear All</button>
      </div>
      <div id="history-table"><div style="color:var(--muted);text-align:center;padding:2rem">No entries yet.</div></div>
    </div>
  </div>

  <!-- ══ TAB: COMPAT ══ -->
<div id="tab-compat" style="display:none">
  <?= _blkC('compat') ?>

    <div class="card-dark p-4 mb-4" style="border:1px solid rgba(199,125,255,.48);background:linear-gradient(135deg,rgba(199,125,255,.10),rgba(88,166,255,.06));box-shadow:0 10px 30px rgba(71,45,110,.18)">
      <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
        <div>
          <div class="section-title mb-1" style="color:#d8b4fe"><i class="bi bi-person-heart"></i>Your Harmony Profile</div>
          <div id="harmony-profile-summary" style="font-size:.82rem;color:#ccc">Your saved personal details are used for Harmony readings.</div>
        </div>
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="switchTab('profile')"><i class="bi bi-pencil-square me-1"></i>Edit Personal Details</button>
      </div>
      <div id="harmony-own-mbti-wrap" style="display:none;border-top:1px solid var(--border);padding-top:1rem">
        <div class="d-flex align-items-center gap-2 mb-2">
          <span class="level-badge level-platinum">💎 Platinum</span>
          <span style="font-size:.78rem;color:var(--muted)">Add your personality preference for richer communication insights.</span>
        </div>
        <div class="row g-2 align-items-end">
          <div class="col-md-5">
            <label class="form-label" for="harmony-self-mbti"><i class="bi bi-person-bounding-box me-1"></i>Your personality type</label>
            <select id="harmony-self-mbti" class="form-select"><option value="">— Select your type —</option></select>
          </div>
          <div class="col-md-auto"><button type="button" class="btn btn-primary" style="font-size:.88rem;padding:.6rem 1rem" onclick="saveHarmonyMBTI()"><i class="bi bi-check2-circle me-1"></i>Use this type</button></div>
          <div class="col-md-auto pb-2"><span id="harmony-mbti-saved" style="display:none;color:var(--green);font-size:.78rem">✓ Saved</span></div>
        </div>
        <div style="font-size:.76rem;color:var(--muted);margin-top:.7rem">
          Not sure of your type? <a href="personality.html#quick-check" target="_blank" rel="noopener" style="color:var(--accent)">Try the 12-question preference check →</a>
        </div>
      </div>
      <div id="harmony-own-mbti-locked" style="border-top:1px solid var(--border);padding-top:1rem;font-size:.8rem;color:var(--muted)">
        <span class="level-badge level-platinum me-2">💎 Platinum</span>Personality preference selection unlocks at Platinum.
      </div>
    </div>

    <!-- Header -->
    <div class="card-dark p-4 mb-4" style="border-left:4px solid var(--accent2)">
      <div class="section-title" style="color:#d8b4fe"><i class="bi bi-hearts"></i>Harmony Reading</div>
      <p style="font-size:.87rem;color:#ccc;margin-bottom:1.25rem;line-height:1.75">
        Explore patterns of harmony with a partner, friend, or family member through <strong style="color:#FFD700">Chinese Zodiac</strong>, <strong style="color:#FF9F1C">Mahabote</strong>, <strong style="color:var(--accent2)">Western Synastry</strong>, and <strong style="color:#e91e8c">Blood Type</strong>. The score is a reflection prompt, not a scientific prediction of relationship success. Birth time and city enable an approximate Ascendant estimate; time zones are not yet applied.
      </p>

      <!-- Your data pills -->
      <div class="row g-2 mb-4" id="compat-you-row">
        <div class="col-sm-6">
          <div class="card-dark2 p-2 d-flex align-items-center gap-3" style="border-radius:10px;border-left:3px solid var(--accent)">
            <div style="font-size:2rem;line-height:1" id="compat-you-cz-icon">—</div>
            <div>
              <div style="font-size:.62rem;color:var(--muted);letter-spacing:1px;text-transform:uppercase;margin-bottom:2px"><i class="bi bi-person-fill me-1"></i>You · Chinese Zodiac</div>
              <div style="font-weight:700;font-size:.88rem" id="compat-you-cz-label">Log in to see your data</div>
            </div>
          </div>
        </div>
        <div class="col-sm-6">
          <div class="card-dark2 p-2 d-flex align-items-center gap-3" style="border-radius:10px;border-left:3px solid #FF9F1C">
            <div style="font-size:2rem;line-height:1" id="compat-you-mhb-icon">—</div>
            <div>
              <div style="font-size:.62rem;color:var(--muted);letter-spacing:1px;text-transform:uppercase;margin-bottom:2px"><i class="bi bi-star-fill me-1"></i>You · Mahabote &amp; Myanmar</div>
              <div style="font-weight:700;font-size:.88rem" id="compat-you-mhb-label">—</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Input form -->
      <div style="font-size:.72rem;color:var(--muted);font-weight:700;letter-spacing:1.2px;text-transform:uppercase;margin-bottom:.75rem;padding-bottom:.4rem;border-bottom:1px solid var(--border)">
        <i class="bi bi-person-plus me-1"></i>Other Person's Details
      </div>
      <div class="row g-3 mb-3">
        <div class="col-sm-5">
          <label class="form-label"><i class="bi bi-person me-1"></i>Name</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-person"></i></span>
            <input type="text" id="compat-name" class="form-control" placeholder="Their name">
          </div>
          <div style="font-size:.72rem;color:var(--green);margin-top:4px"><i class="bi bi-check-circle me-1"></i>First name or nickname is fine.</div>
        </div>
        <div class="col-sm-4">
          <label class="form-label"><i class="bi bi-calendar3 me-1"></i>Date of Birth</label>
          <input type="date" id="compat-dob" class="form-control" style="color-scheme:dark">
        </div>
        <div class="col-sm-3">
          <label class="form-label"><i class="bi bi-clock me-1"></i>Birth Time <span class="opt">(optional)</span></label>
          <input type="time" id="compat-time" class="form-control">
        </div>
      </div>
      <div class="row g-3 align-items-end">
        <div class="col-sm-5">
          <label class="form-label"><i class="bi bi-geo-alt me-1"></i>Birth City <span class="opt">(optional)</span></label>
          <div style="position:relative">
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-search"></i></span>
              <input type="text" id="compat-city" class="form-control" placeholder="e.g. Yangon, London, Tokyo"
                autocomplete="off" oninput="compatCitySearch(this.value)">
            </div>
            <div id="compat-city-dropdown" class="ac-dropdown"></div>
          </div>
        </div>
        <div class="col-sm-3">
          <label class="form-label"><i class="bi bi-droplet-half me-1"></i>Blood Type <span class="opt">(optional)</span></label>
          <select id="compat-blood" class="form-select">
            <option value="">— —</option>
            <option value="A">A</option><option value="B">B</option>
            <option value="AB">AB</option><option value="O">O</option>
          </select>
        </div>
        <div id="compat-mbti-wrap" style="display:none">
        <div class="col-sm-12">
          <div class="row g-3 align-items-start">
            <div class="col-sm-4">
              <label class="form-label"><i class="bi bi-person-bounding-box me-1"></i>Their Personality Type <span class="opt">(optional)</span> <span class="level-badge level-platinum ms-1">💎</span></label>
              <select id="compat-mbti" class="form-select" onchange="showMBTIDesc('compat-mbti','compat-mbti-desc')">
                <option value="">— Select type —</option>
                <optgroup label="Analysts (NT)">
                  <option value="INTJ">INTJ — Architect</option><option value="INTP">INTP — Thinker</option>
                  <option value="ENTJ">ENTJ — Commander</option><option value="ENTP">ENTP — Debater</option>
                </optgroup>
                <optgroup label="Diplomats (NF)">
                  <option value="INFJ">INFJ — Advocate</option><option value="INFP">INFP — Mediator</option>
                  <option value="ENFJ">ENFJ — Protagonist</option><option value="ENFP">ENFP — Campaigner</option>
                </optgroup>
                <optgroup label="Sentinels (SJ)">
                  <option value="ISTJ">ISTJ — Logistician</option><option value="ISFJ">ISFJ — Defender</option>
                  <option value="ESTJ">ESTJ — Executive</option><option value="ESFJ">ESFJ — Consul</option>
                </optgroup>
                <optgroup label="Explorers (SP)">
                  <option value="ISTP">ISTP — Virtuoso</option><option value="ISFP">ISFP — Adventurer</option>
                  <option value="ESTP">ESTP — Entrepreneur</option><option value="ESFP">ESFP — Entertainer</option>
                </optgroup>
              </select>
            </div>
            <div class="col-sm-8 d-flex align-items-end pb-1">
              <div id="compat-mbti-desc" style="display:none;font-size:.82rem;color:#ccc;line-height:1.7;background:var(--bg-card2);border:1px solid var(--border);border-radius:10px;padding:.65rem .9rem;width:100%"></div>
            </div>
          </div>
          <div class="mt-2" style="font-size:.75rem;color:var(--muted)">
            <i class="bi bi-info-circle me-1"></i>Not sure of their type?
            <a href="personality.html#quick-check" target="_blank" rel="noopener" style="color:var(--accent)">Try 12 questions or explore all 16 types →</a>
          </div>
        </div>
        </div>
        <div class="col-sm-auto align-self-end d-flex gap-2">
          <button type="button" class="btn btn-primary px-4" onclick="calcCompat()" style="min-width:110px">
            <i class="bi bi-hearts me-1"></i>Calculate
          </button>
          <button type="button" class="btn btn-outline-secondary px-3" onclick="resetCompatForm()" title="Clear all fields for a new reading">
            <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
          </button>
        </div>
      </div>
      <div id="compat-error" class="msg-info mt-2" style="display:none"></div>
    </div>

    <!-- Result block -->
    <div id="compat-result" class="card-dark p-4 mb-4" style="display:none"></div>

    <!-- Saved compatibility records -->
    <div id="compat-saves-block" class="card-dark p-4" style="display:none">
      <div class="section-title"><i class="bi bi-bookmark-star-fill" style="color:var(--accent)"></i>Saved Harmony Readings</div>
      <div id="compat-saves-list"></div>
    </div>

  </div>

</div>

<footer class="dv-footer">
  <div class="dv-footer-brand"><img class="dv-coin" src="assets/brand/logo-coin-96.png?v=1" width="96" height="96" alt="" aria-hidden="true"> DharmaVera</div>
  <div>© 2026 DharmaVera · Developed by kAi</div>
  <div>Readings and predictions are for entertainment and reflection only.</div>
  <div><a href="privacy.html">Privacy Policy</a> · <a href="terms.html">Terms &amp; Conditions</a> · <a href="about.html">About &amp; Credits</a></div>
</footer>

<script>
// ══════════════════════════════════════════════════════════════════
// INITIAL STATE (server-embedded)
// ══════════════════════════════════════════════════════════════════
let currentUser = <?= $initUser ? json_encode($initUser) : 'null' ?>;
let dailyLog    = <?= $initLog ?>;
let savedMs     = <?= $initMs ?>;
let oracleReadings = <?= $initOracle ?>;
const blockCfg  = <?= json_encode($blockCfg ?? []) ?>;

// Show/hide MBTI fields based on user level
function applyMBTIVisibility() {
  const isPlatinum = currentUser?.level === 'platinum';
  // HACKATHON WORK (new): hide the whole Platinum Personality preference box for Silver members and guests instead of showing it greyed out/locked.
  const _platBox = document.getElementById('platinum-personality-box');
  if (_platBox) _platBox.style.display = isPlatinum ? '' : 'none';
  const sel  = document.getElementById('mbti-selector-wrap');
  const lock = document.getElementById('mbti-locked-wrap');
  const cmp  = document.getElementById('compat-mbti-wrap');
  const harmonyOwn = document.getElementById('harmony-own-mbti-wrap');
  const harmonyLock = document.getElementById('harmony-own-mbti-locked');
  if (sel)  sel.style.display  = isPlatinum ? '' : 'none';
  if (lock) lock.style.display = isPlatinum ? 'none' : '';
  if (cmp)  cmp.style.display  = isPlatinum ? '' : 'none';
  if (harmonyOwn) harmonyOwn.style.display = isPlatinum ? '' : 'none';
  if (harmonyLock) harmonyLock.style.display = isPlatinum ? 'none' : '';
  // Pre-fill MBTI selector if user already has one saved
  if (isPlatinum) {
    const mbtiEl = document.getElementById('p-mbti');
    const harmonyEl = document.getElementById('harmony-self-mbti');
    if (harmonyEl && mbtiEl && harmonyEl.options.length <= 1) harmonyEl.innerHTML = mbtiEl.innerHTML;
    if (currentUser?.mbti_type) {
      if (mbtiEl) mbtiEl.value = currentUser.mbti_type;
      if (harmonyEl) harmonyEl.value = currentUser.mbti_type;
      // MBTI_INFO is declared later in this script; defer description rendering until parsing finishes.
      setTimeout(() => showMBTIDesc('p-mbti','p-mbti-desc'), 0);
    }
  }
}
applyMBTIVisibility();

// Apply block visibility: hide tabs not enabled for user's level
(function applyBlockConfig() {
  if (!Object.keys(blockCfg).length) return;
  document.querySelectorAll('.nav-link[data-tab]').forEach(btn => {
    const key = btn.dataset.tab;
    if (blockCfg[key] && !blockCfg[key].visible) {
      btn.style.display = 'none';
      const panel = document.getElementById('tab-' + key);
      if (panel) panel.style.display = 'none';
    }
  });
})();

// ══════════════════════════════════════════════════════════════════
// MILESTONE DEFINITIONS
// ══════════════════════════════════════════════════════════════════
const MILESTONES_DEF = [
  { id:1,  label:'Date of Birth',                  note:'All 3 cycles start at their peak on your birth day.', cycles:['P','E','I'], type:'peak', required:true },
  { id:2,  label:'Most memorable teenage day',     note:'Intellectual + Emotional peak from your teens.',      cycles:['E','I'],    type:'peak' },
  { id:3,  label:'University / Education completed',note:'Intellectual Cycle (33-day) Peak.',                  cycles:['I'],        type:'peak' },
  { id:4,  label:'First job started',              note:'Intellectual Cycle Peak.',                            cycles:['I'],        type:'peak' },
  { id:5,  label:'First love / Marriage',          note:'Emotional Cycle (28-day) Peak.',                      cycles:['E'],        type:'peak' },
  { id:6,  label:'First child born',               note:'Emotional + Physical significant event.',             cycles:['E','P'],    type:'peak' },
  { id:7,  label:'First home ownership',           note:'Emotional milestone — security and independence.',    cycles:['E'],        type:'peak' },
  { id:8,  label:'Lost a parent or loved one',     note:'Physical Cycle Low — grief takes physical toll.',     cycles:['P'],        type:'low'  },
  { id:9,  label:'First hospital stay / Accident', note:'Physical Cycle (23-day) Low.',                        cycles:['P'],        type:'low'  },
  { id:10, label:'Tooth extraction / Dental',      note:'Physical Cycle Low — body stress event.',             cycles:['P'],        type:'low'  },
  { id:11, label:'Left home / Lived independently',note:'Emotional independence marker.',                      cycles:['E'],        type:'transition' },
];

const CYCLE_COLORS  = { P:'#FF6B35', E:'#4FC3F7', I:'#8338EC' };
const CYCLE_NAMES   = { P:'Physical', E:'Emotional', I:'Intellectual' };
const CYCLE_PERIODS = { P:23, E:28, I:33 };

const MONTHS = ['','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

// ══════════════════════════════════════════════════════════════════
// HELPERS
// ══════════════════════════════════════════════════════════════════
function todayStr() {
  const d = new Date();
  return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');
}
function yesterdayStr() {
  const d = new Date(); d.setDate(d.getDate()-1);
  return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');
}

function daysBetween(d1, d2) {
  return Math.round((new Date(d2) - new Date(d1)) / 86400000);
}
function bioAt(days, period) { return Math.sin(2*Math.PI*days/period); }
function sinToScore(v) { return Math.round((v+1)/2*9+1); }
// Partial date → full date string for biorhythm calc (day unknown → 15, month unknown → 6)
function partialToDate(yr, mo, dy) {
  if (!yr) return null;
  const m = mo || 6;
  const d = dy || 15;
  return `${yr}-${String(m).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
}

// ══════════════════════════════════════════════════════════════════
// API CALLS
// ══════════════════════════════════════════════════════════════════
let _csrfToken = <?= json_encode($_SESSION['csrf_token']) ?>;

async function api(data) {
  try {
    const fd = new FormData();
    fd.append('csrf', _csrfToken);
    for (const [k,v] of Object.entries(data)) fd.append(k, v ?? '');
    const r = await fetch('biorhythm.php?api=1', { method:'POST', body:fd });
    const j = await r.json();
    if (j && j.need_pin && data.action !== 'set_pin') showPinSetup();
    return j;
  } catch(e) { return { error: e.message }; }
}

async function doLogin() {
  const name = document.getElementById('login-name').value.trim();
  const dob  = document.getElementById('login-dob').value;
  const btime = document.getElementById('login-time')?.value || '';
  const bcity = (document.getElementById('login-city')?.value || '').trim();
  if (!name || !dob) { showLoginError('Please enter your name and date of birth.'); return; }

  // Use coordinates from dropdown pick if available, otherwise geocode
  let blat = '', blon = '';
  if (bcity) {
    const cityStatus = document.getElementById('login-city-status');
    if (_cityLat !== null && _cityLabel && _cityLabel.toLowerCase().includes(bcity.toLowerCase().split(',')[0].trim().toLowerCase())) {
      blat = _cityLat; blon = _cityLon;
    } else {
      if (cityStatus) { cityStatus.style.display='block'; cityStatus.style.color='var(--muted)'; cityStatus.textContent='Looking up city…'; }
      const geo = await Ephemeris.geocodeCity(bcity);
      if (geo) {
        blat = geo.lat; blon = geo.lon;
        if (cityStatus) { cityStatus.style.color='#5cb85c'; cityStatus.textContent='✓ ' + geo.displayName; }
      } else {
        if (cityStatus) { cityStatus.style.color='#f0ad4e'; cityStatus.textContent='City not found — continuing without location.'; }
      }
    }
  }

  const btype = (document.getElementById('login-blood')?.value || '').trim();
  const pin  = (document.getElementById('login-pin')?.value || '').trim();
  const pin2Wrap = document.getElementById('login-pin2-wrap');
  const confirming = pin2Wrap && pin2Wrap.style.display !== 'none';
  const pin2 = confirming ? (document.getElementById('login-pin2')?.value || '').trim() : '';
  if (!/^[0-9]{4}$/.test(pin)) { showLoginError('Please enter your 4-digit PIN.'); document.getElementById('login-pin')?.focus(); return; }
  const _uuid = (function(){ try { return localStorage.getItem('baydin_uuid') || ''; } catch(_) { return ''; } })();
  const res = await api({ action:'login', name, dob, pin, pin2, remember:'1', birth_time:btime, birth_city:bcity, birth_lat:blat, birth_lon:blon, blood_type:btype, uuid:_uuid });
  if (res.new_account) {
    // No profile with this name and date of birth yet: confirm the new PIN once.
    if (pin2Wrap) pin2Wrap.style.display = '';
    const help = document.getElementById('login-pin-help');
    if (help) help.innerHTML = res.existing
      ? '<strong style="color:var(--accent)">Welcome back!</strong> Your profile now needs a PIN. Type the same 4 digits again to confirm it, then press Enter again.'
      : '<strong style="color:var(--accent)">New profile.</strong> Type the same 4 digits again to confirm your PIN, then press Enter again.';
    if (res.error) showLoginError(res.error); else document.getElementById('login-error').style.display = 'none';
    document.getElementById('login-pin2')?.focus();
    return;
  }
  if (res.error) { showLoginError(res.error); return; }
  if (enterApp(res)) await loadAppData();
}

// Shared by sign-in and remembered-device resume.
function enterApp(res) {
  if (res.csrf) _csrfToken = res.csrf; // update token after session regeneration
  currentUser = res;
  try {
    if (res.device_token) localStorage.setItem('dv_device', res.device_token);
    localStorage.setItem('bio_saved_login', JSON.stringify({ name: res.name, dob: res.dob }));   // prefill only — never the PIN
  } catch(_){}
  applyMBTIVisibility();
  document.getElementById('login-overlay').style.display = 'none';
  document.getElementById('bio-main-content').style.display = '';
  document.getElementById('hero-username').innerHTML = `<i class="bi bi-person-check me-1"></i>${escHtml(res.name)}`;
  document.getElementById('btn-logout').style.display = '';
  if (res.need_pin) { showPinSetup(); return false; }
  return true;
}

async function loadAppData() {
  const data = await api({ action:'load_data' });
  if (data.ok) {
    dailyLog = data.log || {};
    savedMs  = data.milestones || {};
    oracleReadings = data.oracle || [];
  }
  fillProfileFields(currentUser);
  buildMilestoneForm();
  updateProgress();
  initCompatTab();
  loadCompatSaves();
  renderOracleSection();
  applyRequestedView();
}

// ── PIN setup (members created before PINs) and PIN change ──
function showPinSetup() {
  const o = document.getElementById('pin-setup-overlay');
  if (o) { o.style.display = 'flex'; setTimeout(() => document.getElementById('setup-pin')?.focus(), 50); }
}
async function savePinSetup() {
  const pin = document.getElementById('setup-pin').value.trim(), pin2 = document.getElementById('setup-pin2').value.trim();
  const err = document.getElementById('setup-pin-error');
  const res = await api({ action:'set_pin', pin, pin2 });
  if (res.error) { err.textContent = res.error; err.style.display = 'block'; return; }
  document.getElementById('pin-setup-overlay').style.display = 'none';
  if (currentUser) currentUser.need_pin = false;
  await loadAppData();
}
async function changePin() {
  const cur = document.getElementById('pin-current').value.trim(), pin = document.getElementById('pin-new').value.trim(), pin2 = document.getElementById('pin-new2').value.trim();
  const msg = document.getElementById('pin-change-msg');
  const res = await api({ action:'change_pin', current:cur, pin, pin2 });
  msg.style.display = 'block';
  if (res.error) { msg.style.color = '#e05252'; msg.textContent = res.error; return; }
  msg.style.color = '#51cf66'; msg.textContent = 'PIN changed. Other remembered devices have been signed out.';
  ['pin-current','pin-new','pin-new2'].forEach(id => document.getElementById(id).value = '');
  try { localStorage.removeItem('dv_device'); } catch(_){}
}

// ── Birth City autocomplete (Nominatim) ────────────────────────────
let _cityLat = null, _cityLon = null, _cityLabel = '';
let _compatCityTimer = null, _compatCityLat = null, _compatCityLon = null;
let _loginCityTimer  = null;

// Build dropdown for a city input.
// onPick(lat, lon, label) is called when user selects a result.
async function _cityAutocomplete(val, dropdownId, onPick) {
  const dd = document.getElementById(dropdownId);
  if (!dd) return;
  if (!val || val.trim().length < 2) { dd.style.display = 'none'; return; }
  try {
    const url = 'https://nominatim.openstreetmap.org/search?q='
      + encodeURIComponent(val.trim())
      + '&format=json&limit=6&addressdetails=1&accept-language=en';
    const res  = await fetch(url, { headers:{ 'User-Agent':'BaydinAstrologyApp/1.0' } });
    const data = await res.json();
    if (!data || !data.length) {
      dd.innerHTML = '<div style="padding:8px 12px;font-size:.8rem;color:var(--muted)">No results — try a different spelling</div>';
      dd.style.display = 'block'; return;
    }
    // Build result rows, attach click via closure stored on the element
    dd.innerHTML = '';
    data.forEach(r => {
      const addr    = r.address || {};
      const city    = addr.city || addr.town || addr.village || addr.county || r.display_name.split(',')[0];
      const country = addr.country || '';
      const state   = addr.state   || '';
      const label   = [city, state, country].filter(Boolean).join(', ');
      const div = document.createElement('div');
      div.className = 'city-ac-item';
      div.textContent = label;
      div.style.cssText = 'padding:8px 12px;cursor:pointer;font-size:.82rem;border-bottom:1px solid var(--border)';
      // Use mousedown so it fires before the input's blur event hides the dropdown
      div.addEventListener('mousedown', function(e) {
        e.preventDefault(); // prevent input blur
        onPick(parseFloat(r.lat), parseFloat(r.lon), label);
        dd.style.display = 'none';
      });
      dd.appendChild(div);
    });
    dd.style.display = 'block';
  } catch (_) { dd.style.display = 'none'; }
}

// Login city
function loginCitySearch(val) {
  _cityLat = null; _cityLon = null; _cityLabel = '';
  const st = document.getElementById('login-city-status');
  if (st) st.style.display = 'none';
  clearTimeout(_loginCityTimer);
  _loginCityTimer = setTimeout(() => _cityAutocomplete(val, 'login-city-dropdown', (lat, lon, label) => {
    _cityLat = lat; _cityLon = lon; _cityLabel = label;
    document.getElementById('login-city').value = label;
    const st = document.getElementById('login-city-status');
    if (st) { st.style.display = 'block'; st.style.color = '#5cb85c'; st.textContent = `✓ ${label} (${lat.toFixed(2)}°, ${lon.toFixed(2)}°)`; }
  }), 350);
}

// Compat city
function compatCitySearch(val) {
  _compatCityLat = null; _compatCityLon = null;
  clearTimeout(_compatCityTimer);
  _compatCityTimer = setTimeout(() => _cityAutocomplete(val, 'compat-city-dropdown', (lat, lon, label) => {
    _compatCityLat = lat; _compatCityLon = lon;
    document.getElementById('compat-city').value = label;
  }), 350);
}

// Hide dropdowns when clicking outside
document.addEventListener('click', e => {
  if (!e.target.closest('#login-city-dropdown')  && e.target.id !== 'login-city') {
    const dd = document.getElementById('login-city-dropdown');
    if (dd) dd.style.display = 'none';
  }
  if (!e.target.closest('#compat-city-dropdown') && e.target.id !== 'compat-city') {
    const dd = document.getElementById('compat-city-dropdown');
    if (dd) dd.style.display = 'none';
  }
});

function showLoginError(msg) {
  const el = document.getElementById('login-error');
  el.textContent = msg; el.style.display = 'block';
}

async function doLogout() {
  let token = ''; try { token = localStorage.getItem('dv_device') || ''; } catch(_){}
  try { await api({ action:'logout', token }); } catch(_){}
  try { localStorage.removeItem('bio_saved_login'); localStorage.removeItem('dv_device'); } catch(_){}
  // Hard redirect so PHP re-renders with no session — cleanest approach
  window.location.href = 'biorhythm.php?signedout=1';
}

async function deleteAccount() {
  const input = document.getElementById('delete-confirm');
  const errEl = document.getElementById('delete-error');
  errEl.style.display = 'none';
  if (input.value.trim() !== 'DELETE') {
    errEl.textContent = 'Please type DELETE in capital letters to confirm.';
    errEl.style.display = 'block'; input.focus(); return;
  }
  if (!confirm('Permanently delete your DharmaVera account and all of its data? This cannot be undone.')) return;
  const res = await api({ action:'delete_account', confirm:'DELETE' });
  if (res.error) { errEl.textContent = res.error; errEl.style.display = 'block'; return; }
  try {
    ['bio_saved_login','dv_device','oracle_session','tarot_session','mhb_last_answered','baydin_uuid'].forEach(k => localStorage.removeItem(k));
  } catch(_){}
  alert('Your account and all of its data have been deleted.');
  window.location.href = 'biorhythm.php?signedout=1';
}

// ══════════════════════════════════════════════════════════════════
// ORACLE DETAIL SECTION — the member's own reading, fetched from the private oracle engine
// ══════════════════════════════════════════════════════════════════
async function renderOracleSection() {
  const el = document.getElementById('oracle-detail-section');
  if (!el) return;
  let save = null;
  try {
    const fd = new FormData(); fd.append('csrf', _csrfToken); fd.append('action', 'detail');
    const r = await fetch('oracle_api.php', { method:'POST', body:fd, credentials:'same-origin' });
    const j = await r.json();
    if (j.ok && j.reading) save = j.reading;
  } catch(_) {}
  if (!save) { el.style.display='none'; return; }

  const _sc = s => s>=7?'#51cf66':s>=4?'#f0ad4e':'#e05252';
  const _sb = s => `<div style="flex:1;background:rgba(255,255,255,.08);border-radius:4px;height:6px">
    <div style="width:${s*10}%;background:${_sc(s)};height:6px;border-radius:4px;transition:width .6s ease"></div></div>`;
  const _st = s => s>=9?'Excellent':s>=7?'Favorable':s>=5?'Neutral':s>=3?'Challenging':'Very Difficult';

  const mhb   = save.mhb   || {};
  const quran = save.quran  || {};
  const bible = save.bible  || {};
  const kc    = save.kauCim || {};
  const tc    = save.tarot  || null;
  const teh   = save.tehillim || null;
  const tp    = teh ? teh.text : null;
  const ram   = save.ram || null;
  const rc    = ram ? ram.chaupai : null;
  const dispAvg = save.avg;
  const avgLabel  = save.count + '-Oracle Average';
  const avgColor = _sc(dispAvg);
  const overallLabel = dispAvg>=8?'✦ Excellent':dispAvg>=7?'✦ Favorable':dispAvg>=5?'◈ Neutral':dispAvg>=3?'◇ Challenging':'◇ Difficult';
  refreshOraclePulse();   // the server has already saved this reading to Oracle Pulse

  const mhbA = mhb.arudha || {};
  const mhbY = mhb.yam    || {};
  const mhbO = mhb.omen   || {};
  const mhbTotalSign = (mhb.total||0) >= 0 ? '+' : '';

  const verseHtml = (kc.verse||'').split('\n').map(l=>`<div>${l}</div>`).join('');

  // These scores are editorial themes assigned to the selected book or sura.
  // A Bible chapter number is selected, but its text is not analyzed here.
  const _bibleInterp = (score, book, ch) => {
    if (score >= 9) return `The site's theme for ${book} is strongly encouraging. Take the selected chapter, ${ch}, as a prompt for personal reflection.`;
    if (score >= 7) return `The site's theme for ${book} is favorable and emphasizes thoughtful action. Reflect on chapter ${ch} in that spirit.`;
    if (score >= 5) return `The site's theme for ${book} is balanced, with room for both caution and hope. Chapter ${ch} is your selected reflection point.`;
    return `The site's theme for ${book} emphasizes patience and reflection. Chapter ${ch} is your selected reflection point.`;
  };
  const _tehInterp = (score, genre) => {
    const byGenre = {
      Lament: 'This is a psalm of lament: honest words spoken from a hard place that still turn toward God. For your question it counsels patience — name the difficulty plainly and do not rush the outcome.',
      Penitential: 'This is a psalm of repentance. It invites you to set something right — an apology, a correction, a fresh start — before moving forward.',
      Trust: 'This is a psalm of trust. It points to steadiness and protection: move forward calmly and without fear.',
      Thanksgiving: 'This is a psalm of thanksgiving for help received — a sign of relief and a good outcome after effort.',
      Hymn: 'This is a hymn of praise: an open, joyful answer. The moment favours gratitude and confident action.',
      Wisdom: 'This is a wisdom psalm. It asks you to choose the steady, honest path; good results follow good choices over time.',
      Royal: 'This is a royal psalm about leadership and support for those who carry responsibility — favourable for taking charge with humility.',
      Zion: 'This is a psalm of Zion, the place of God’s presence. It points to belonging, safety and coming home.',
      History: 'This psalm retells history. Learn from what has happened before you decide.',
      Imprecatory: 'This psalm cries out for justice. It acknowledges wrongdoing around you; seek fairness and let go of revenge.'
    };
    const tone = score >= 9 ? 'DharmaVera reads it as strongly encouraging.' : score >= 7 ? 'DharmaVera reads it as favourable.' : score >= 5 ? 'DharmaVera reads it as balanced — hopeful, with care.' : 'DharmaVera reads it as a call to wait and reflect.';
    return (byGenre[genre] || '') + ' ' + tone;
  };
  const _quranInterp = (score, num, name) => {
    if (score >= 9) return `The site's theme for sura ${name} (${num}) is strongly encouraging. Use the selection as a prompt for reflection.`;
    if (score >= 7) return `The site's theme for sura ${name} (${num}) is favorable and emphasizes principled action.`;
    if (score >= 5) return `The site's theme for sura ${name} (${num}) is balanced and invites patient reflection.`;
    return `The site's theme for sura ${name} (${num}) invites sincere reflection before deciding what to do next.`;
  };

  el.innerHTML = `
<div class="card-dark p-4 mb-2" style="border-left:4px solid ${avgColor}">
  <div class="section-title"><i class="bi bi-stars me-2"></i>Today's Oracle Reading
    <span style="font-weight:400;text-transform:none;letter-spacing:0;font-size:.75rem;color:var(--muted)"> — Real-Time Guidance Details</span>
  </div>
  ${save.question ? `<div style="font-size:.88rem;color:#ddd;line-height:1.65;background:rgba(255,255,255,.035);border-left:3px solid #ff4d5f;border-radius:0 8px 8px 0;padding:.7rem .9rem;margin-bottom:1.1rem"><span style="font-size:.62rem;color:var(--muted);text-transform:uppercase;letter-spacing:.8px;display:block;margin-bottom:.2rem">Question memo</span>${escHtml(String(save.question))}</div>` : ''}

  <!-- Overall average -->
  <div style="text-align:center;margin-bottom:1.5rem">
    <div style="font-size:.55rem;color:var(--muted);letter-spacing:1.5px;font-weight:700;text-transform:uppercase;margin-bottom:.25rem">${avgLabel}</div>
    <div style="font-size:3.5rem;font-weight:900;color:${avgColor};line-height:1">${dispAvg.toFixed(1)}<span style="font-size:1rem;color:var(--muted)">/10</span></div>
    <div style="display:inline-block;background:${avgColor}22;border:1px solid ${avgColor}55;border-radius:20px;padding:.3rem 1.2rem;font-size:.88rem;font-weight:700;color:${avgColor};margin-top:.35rem">${overallLabel}</div>
    <div style="font-size:.68rem;color:var(--muted);margin-top:.55rem">Scripture scores describe this site's reflection themes, never the worth of a sacred text.</div>
  </div>

  <!-- ══ ROW 1: Mahabote — full width ══ -->
  <div class="card-dark2 p-4 mb-3">
    <div style="font-size:.6rem;color:var(--accent);letter-spacing:1.5px;font-weight:700;text-transform:uppercase;margin-bottom:.8rem">
      ① Mahabote (Burman) — Arudha Puzza Horary Reading
    </div>
    <!-- Verdict header -->
    <div style="text-align:center;margin-bottom:1.25rem">
      <div style="font-size:2.8rem;margin-bottom:.25rem">${mhb.vIcon||'🔮'}</div>
      <div style="font-size:1.6rem;font-weight:800;color:${mhb.vColor||avgColor};margin-bottom:.45rem">${mhb.verdict||''}</div>
      <div style="display:inline-flex;align-items:center;gap:.75rem;background:var(--bg-card);border-radius:20px;padding:.35rem 1.2rem;border:1px solid var(--border)">
        <span style="font-size:.78rem;color:var(--muted)">Arudha Score</span>
        <span style="font-size:1.15rem;font-weight:800;color:${mhb.vColor||avgColor}">${mhbTotalSign}${mhb.total||0}</span>
        <span style="color:var(--border)">·</span>
        <span style="font-size:.78rem;color:var(--muted)">Oracle Score</span>
        <span style="font-size:1.15rem;font-weight:800;color:${_sc(mhb.score||0)}">${mhb.score||0}<span style="font-size:.6rem;color:var(--muted)">/10</span></span>
      </div>
    </div>
    <!-- 3 detail cards -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,170px),1fr));gap:.85rem">

      <!-- Number + Celestial body -->
      <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:10px;padding:1rem">
        <div style="font-size:.56rem;color:var(--muted);letter-spacing:1px;font-weight:700;text-transform:uppercase;margin-bottom:.5rem">Number · ${mhb.d1||'?'}</div>
        <div style="font-size:2rem;margin-bottom:.3rem">${mhbA.icon||''}</div>
        <div style="font-size:.95rem;font-weight:700;color:${mhbA.color||'#ccc'};margin-bottom:.4rem">${mhbA.name||''}</div>
        <div style="font-size:.78rem;color:#bbb;line-height:1.65;margin-bottom:.6rem">${mhbA.desc||''}</div>
        <div style="display:flex;align-items:center;gap:.4rem">
          ${_sb(Math.min(10, Math.max(0, (mhbA.score||0) + 5) * 2))}
          <span style="font-size:.85rem;font-weight:700;color:${(mhbA.score||0)>=0?'#51cf66':'#e05252'};white-space:nowrap">${(mhbA.score||0)>=0?'+':''}${mhbA.score||0}</span>
        </div>
      </div>

      <!-- Yama Period -->
      <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:10px;padding:1rem">
        <div style="font-size:.56rem;color:var(--muted);letter-spacing:1px;font-weight:700;text-transform:uppercase;margin-bottom:.5rem">Yama · Period ${mhbY.yamIndex||'?'}</div>
        <div style="font-size:2rem;margin-bottom:.3rem">🕐</div>
        <div style="font-size:.95rem;font-weight:700;color:${(mhbY.score||0)>0?'#51cf66':(mhbY.score||0)<0?'#e05252':'#aaa'};margin-bottom:.4rem">${mhbY.label||''}</div>
        <div style="font-size:.78rem;color:#bbb;line-height:1.65;margin-bottom:.6rem">${mhbY.desc||''}</div>
        <div style="display:flex;align-items:center;gap:.4rem">
          ${_sb(Math.min(10, Math.max(0, (mhbY.score||0) + 3) * 2.5))}
          <span style="font-size:.85rem;font-weight:700;color:${(mhbY.score||0)>0?'#51cf66':(mhbY.score||0)<0?'#e05252':'#aaa'};white-space:nowrap">${(mhbY.score||0)>=0?'+':''}${mhbY.score||0}</span>
        </div>
      </div>

      <!-- Omen Observed -->
      <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:10px;padding:1rem">
        <div style="font-size:.56rem;color:var(--muted);letter-spacing:1px;font-weight:700;text-transform:uppercase;margin-bottom:.5rem">Omen Observed</div>
        <div style="font-size:2rem;margin-bottom:.3rem">${mhbO.icon||'🔕'}</div>
        <div style="font-size:.95rem;font-weight:700;color:${(mhbO.score||0)>0?'#51cf66':(mhbO.score||0)<0?'#e05252':'#aaa'};margin-bottom:.4rem">${mhbO.label||''}</div>
        <div style="font-size:.78rem;color:#bbb;line-height:1.65;margin-bottom:.6rem">${mhbO.desc||''}</div>
        <div style="display:flex;align-items:center;gap:.4rem">
          ${_sb(Math.min(10, Math.max(0, (mhbO.score||0) + 3) * 2.5))}
          <span style="font-size:.85rem;font-weight:700;color:${(mhbO.score||0)>0?'#51cf66':(mhbO.score||0)<0?'#e05252':'#aaa'};white-space:nowrap">${(mhbO.score||0)>=0?'+':''}${mhbO.score||0}</span>
        </div>
      </div>

    </div>
  </div>

  <!-- ══ ROW 3: Kau Cim — full width ══ -->
  <div class="card-dark2 p-4 mb-3">
    <div style="font-size:.6rem;color:#d4a017;letter-spacing:1.5px;font-weight:700;text-transform:uppercase;margin-bottom:.7rem">
      ② 🎋 签筒 Chinese Kau Cim — Fortune Stick Oracle
    </div>
    <!-- Stick header -->
    <div style="display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:.85rem;flex-wrap:wrap;gap:.5rem">
      <div>
        <div style="font-size:3rem;font-weight:900;color:#e2e8f0;line-height:1">
          #${String(kc.num||0).padStart(2,'0')}
          <span style="font-size:.85rem;font-weight:400;color:var(--muted)"> / 100</span>
        </div>
      </div>
      <div style="text-align:right">
        <div style="font-size:2.5rem;font-weight:900;color:${kc.color||'#d4a017'};line-height:1">${kc.grade||''}</div>
        <div style="font-size:.9rem;font-weight:700;color:${kc.color||'#d4a017'}">${kc.en||''}</div>
        <div style="font-size:1.5rem;font-weight:800;color:${_sc(kc.score||0)};margin-top:.2rem">${kc.score||0}<span style="font-size:.75rem;color:var(--muted)">/10</span></div>
      </div>
    </div>
    <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:1rem">
      ${_sb(kc.score||0)}
      <span style="font-size:.75rem;color:var(--muted);white-space:nowrap">${_st(kc.score||0)}</span>
    </div>
    <!-- Verse -->
    <div style="background:rgba(255,255,255,.04);border-left:3px solid ${kc.color||'#d4a017'};
                border-radius:0 10px 10px 0;padding:1rem 1.35rem;margin-bottom:1.1rem;
                font-style:italic;font-size:.95rem;color:#ccc;line-height:2.1">
      ${verseHtml}
    </div>
    <!-- Legend -->
    <div style="margin-bottom:1.1rem">
      <div style="font-size:.58rem;color:var(--muted);letter-spacing:1px;font-weight:700;text-transform:uppercase;margin-bottom:.45rem">📜 Legend</div>
      <div style="font-size:.88rem;color:#bbb;line-height:1.85">${kc.story||''}</div>
    </div>
    <!-- Modern Reading -->
    <div style="background:linear-gradient(135deg,rgba(99,102,241,.12),rgba(168,85,247,.08));
                border:1px solid rgba(168,85,247,.2);border-radius:12px;padding:1.1rem 1.3rem">
      <div style="font-size:.58rem;color:#a78bfa;letter-spacing:1px;font-weight:700;text-transform:uppercase;margin-bottom:.45rem">⚡ Modern Reading</div>
      <div style="font-size:.88rem;color:#d4c8f8;line-height:1.85">${kc.modern||''}</div>
    </div>
  </div>

  <!-- ══ ③ Tehillim — full width ══ -->
  ${teh && tp ? `
  <div class="card-dark2 p-4 mb-3">
    <div style="font-size:.6rem;color:#7dd3fc;letter-spacing:1.5px;font-weight:700;text-transform:uppercase;margin-bottom:.7rem">
      ③ ✡️ Tehillim · <span lang="he">תְּהִלִּים</span> — Book of Psalms
    </div>
    <div style="display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:.85rem;flex-wrap:wrap;gap:.5rem">
      <div>
        <div style="font-size:2.6rem;font-weight:900;color:#e2e8f0;line-height:1">Psalm ${teh.num}<span style="font-size:.85rem;font-weight:400;color:var(--muted)"> / 150</span></div>
        <div style="font-size:.75rem;color:#7dd3fc;margin-top:.3rem">${tp[4]} psalm${tp[5] ? ' · ' + tp[5] : ''}</div>
      </div>
      <div style="font-size:1.5rem;font-weight:800;color:${_sc(teh.score)}">${teh.score}<span style="font-size:.75rem;color:var(--muted)">/10</span></div>
    </div>
    <div class="dv-hebrew" lang="he" dir="rtl">${tp[0]}</div>
    <div style="font-size:.9rem;color:#ccc;line-height:1.8;font-style:italic;margin:.2rem 0 .3rem">“${tp[1]}”</div>
    <div style="font-size:.68rem;color:var(--muted);margin-bottom:1rem">Psalm ${teh.num}:${tp[7]} · Hebrew Masoretic text · English: JPS 1917 translation (public domain)</div>
    <div style="margin-bottom:1rem">
      <div style="font-size:.58rem;color:var(--muted);letter-spacing:1px;font-weight:700;text-transform:uppercase;margin-bottom:.4rem">✦ Theme</div>
      <div style="font-size:.9rem;color:#ddd;line-height:1.75">${tp[2]}</div>
    </div>
    <div style="margin-bottom:1rem">
      <div style="font-size:.58rem;color:var(--muted);letter-spacing:1px;font-weight:700;text-transform:uppercase;margin-bottom:.4rem">📜 Historical context</div>
      <ul style="font-size:.84rem;color:#bbb;line-height:1.7;margin:0;padding-left:1.1rem">${(teh.context || []).map(n => `<li>${n.charAt(0).toUpperCase() + n.slice(1)}.</li>`).join('')}</ul>
    </div>
    <div style="font-size:.84rem;color:#bbb;line-height:1.75;margin-bottom:.9rem;padding:.8rem 1rem;background:rgba(255,255,255,.04);border-left:3px solid #7dd3fc;border-radius:0 8px 8px 0">
      ${_tehInterp(teh.score, tp[4])}
    </div>
    <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.3rem">
      ${_sb(teh.score)}
      <span style="font-size:.75rem;color:var(--muted);white-space:nowrap">${_st(teh.score)}</span>
    </div>
    <div style="font-size:.68rem;color:var(--muted);line-height:1.6;margin-top:.7rem">
      Choosing a psalm by number is a DharmaVera reflection method, not a traditional Jewish divination practice. Many people read Tehillim for comfort and prayer — <a href="https://www.sefaria.org/Psalms.${teh.num}" target="_blank" rel="noopener" style="color:#7dd3fc">read all of Psalm ${teh.num} on Sefaria</a>.
    </div>
  </div>` : ''}

  <!-- ══ ROW 2: Bible | Quran — 2 columns ══ -->
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,150px),1fr));gap:.85rem;margin-bottom:.85rem">

    <!-- ③ Bible -->
    <div class="card-dark2 p-4">
      <div style="font-size:.6rem;color:#63b3ed;letter-spacing:1.5px;font-weight:700;text-transform:uppercase;margin-bottom:.6rem">④ ✝️ Bible — Sacred Scripture</div>
      <div style="font-size:.68rem;color:var(--muted);margin-bottom:.2rem">${bible.book||''} · Chapter ${bible.ch||''}</div>
      <div style="font-size:1.3rem;font-weight:800;color:#e2e8f0;margin-bottom:.6rem">${bible.book||''} ${bible.ch||''}</div>
      <div style="font-size:.85rem;color:#bbb;line-height:1.7;margin-bottom:.75rem">${bible.desc||''}</div>
      <div style="font-size:.82rem;color:#bbb;line-height:1.75;margin-bottom:.9rem;
                  padding:.8rem 1rem;background:rgba(255,255,255,.04);
                  border-left:3px solid #63b3ed;border-radius:0 8px 8px 0">
        ${_bibleInterp(bible.score||0, bible.book||'', bible.ch||0)}
      </div>
      <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.3rem">
        ${_sb(bible.score||0)}
        <span style="font-size:1.4rem;font-weight:800;color:${_sc(bible.score||0)};white-space:nowrap">${bible.score||0}<span style="font-size:.6rem;color:var(--muted)">/10</span></span>
      </div>
      <div style="font-size:.72rem;color:var(--muted)">${_st(bible.score||0)}</div>
    </div>

    <!-- ② Quran -->
    <div class="card-dark2 p-4">
      <div style="font-size:.6rem;color:#a78bfa;letter-spacing:1.5px;font-weight:700;text-transform:uppercase;margin-bottom:.6rem">⑤ ☪️ Quran — Holy Revelation</div>
      <div style="font-size:.68rem;color:var(--muted);margin-bottom:.2rem">Sura ${quran.num||''} of 114</div>
      <div style="font-size:1.3rem;font-weight:800;color:#e2e8f0;margin-bottom:.6rem">${quran.name||''}</div>
      <div style="font-size:.85rem;color:#bbb;line-height:1.7;margin-bottom:.75rem">${quran.desc||''}</div>
      <div style="font-size:.82rem;color:#bbb;line-height:1.75;margin-bottom:.9rem;
                  padding:.8rem 1rem;background:rgba(255,255,255,.04);
                  border-left:3px solid #a78bfa;border-radius:0 8px 8px 0">
        ${_quranInterp(quran.score||0, quran.num||0, quran.name||'')}
      </div>
      <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.3rem">
        ${_sb(quran.score||0)}
        <span style="font-size:1.4rem;font-weight:800;color:${_sc(quran.score||0)};white-space:nowrap">${quran.score||0}<span style="font-size:.6rem;color:var(--muted)">/10</span></span>
      </div>
      <div style="font-size:.72rem;color:var(--muted)">${_st(quran.score||0)}</div>
    </div>

  </div>

  <!-- ══ ⑥ Ram Shalaka Prashnavali — full width ══ -->
  ${ram && rc ? (() => {
    const pathSet = new Set(ram.path || []);
    const start = ram.cell - 1;
    const gridHtml = (ram.grid || []).map((ch, i) =>
      `<span class="${i === start ? 'is-start' : pathSet.has(i) ? 'is-path' : ''}">${ch}</span>`).join('');
    const kindBadge = { auspicious: ['शुभ · Auspicious', '#51cf66'], caution: ['सावधान · Be cautious', '#f0ad4e'], surrender: ['प्रभु इच्छा · Leave it to God', '#7dd3fc'] }[rc.kind];
    const row = Math.floor(start / 15) + 1, col = start % 15 + 1;
    return `
  <div class="card-dark2 p-4 mb-3">
    <div style="font-size:.6rem;color:#fb923c;letter-spacing:1.5px;font-weight:700;text-transform:uppercase;margin-bottom:.7rem">
      ⑥ 🕉️ Ram Shalaka Prashnavali · <span lang="hi">श्री राम शलाका प्रश्नावली</span>
    </div>
    <div style="display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:.85rem;flex-wrap:wrap;gap:.5rem">
      <div>
        <div style="font-size:2.3rem;font-weight:900;color:#e2e8f0;line-height:1">Chaupai ${ram.idx + 1}<span style="font-size:.85rem;font-weight:400;color:var(--muted)"> / 9</span></div>
        <div style="font-size:.75rem;color:var(--muted);margin-top:.3rem">Your cell: ${ram.cell} of 225 · row ${row}, column ${col}</div>
      </div>
      <div style="text-align:right">
        <div style="display:inline-block;font-size:.78rem;font-weight:700;color:${kindBadge[1]};background:${kindBadge[1]}1f;border:1px solid ${kindBadge[1]}66;border-radius:20px;padding:.2rem .75rem" lang="hi">${kindBadge[0]}</div>
        <div style="font-size:1.5rem;font-weight:800;color:${_sc(ram.score)};margin-top:.3rem">${ram.score}<span style="font-size:.75rem;color:var(--muted)">/10</span></div>
      </div>
    </div>
    <div class="dv-ramgrid" lang="hi" aria-label="Ram Shalaka grid with your path highlighted">${gridHtml}</div>
    <div style="font-size:.68rem;color:var(--muted);text-align:center;margin:.45rem 0 1rem">
      <span style="color:#fcd34d">■</span> your chosen cell &nbsp;·&nbsp; <span style="color:#fb923c">■</span> every 9th letter, counted round the grid — together they spell your chaupai
    </div>
    <div class="dv-devanagari" lang="hi">${rc.dev}</div>
    <div style="font-size:.82rem;color:#bbb;font-style:italic;text-align:center;margin:.1rem 0 .8rem">${rc.iast}</div>
    <div style="font-size:.9rem;color:#ddd;line-height:1.75;margin-bottom:.35rem">${rc.en}</div>
    <div style="font-size:.68rem;color:var(--muted);margin-bottom:1rem">Shri Ramcharitmanas of Goswami Tulsidas${rc.src ? " · " + rc.src : ""}</div>
    <div style="font-size:.84rem;color:#bbb;line-height:1.75;margin-bottom:.9rem;padding:.8rem 1rem;background:rgba(255,255,255,.04);border-left:3px solid #fb923c;border-radius:0 8px 8px 0">
      ${rc.advice}
    </div>
    <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.3rem">
      ${_sb(ram.score)}
      <span style="font-size:.75rem;color:var(--muted);white-space:nowrap">${_st(ram.score)}</span>
    </div>
    <div style="font-size:.68rem;color:var(--muted);line-height:1.6;margin-top:.7rem">
      Ram Shalaka is a devotional tradition: nine chaupais of the Ramcharitmanas woven into 225 letters. Your four numbers choose the starting cell; the traditional count finds the verse. The 1–10 score is DharmaVera's placement of that answer on a common scale — the nine answers are not evenly spaced, and one of them counsels patience rather than yes or no.
    </div>
  </div>`;
  })() : ''}

  <!-- ══ ROW 4: Gypsy Cards (if drawn) ══ -->
  ${tc ? (() => {
    const _tc = tc;
    const tcScore = _tc.score || 0;
    const tcColor = _sc(tcScore);
    const cardIndex = Number.isInteger(_tc.idx) && _tc.idx >= 0 && _tc.idx < 55 ? _tc.idx : null;
    const cardFile = cardIndex === null ? 'back.svg' : `${String(cardIndex + 1).padStart(2,'0')}.svg`;
    const cardAlt = escHtml(_tc.n || 'DharmaVera fortune card').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
    const cardImage = `<figure class="dv-card-figure"><img src="assets/cards/${cardFile}" width="180" height="270" loading="lazy" decoding="async" alt="${cardAlt} illustrated card face"><figcaption>Original DharmaVera card artwork · ${cardIndex === null ? 'Deck' : `${cardIndex + 1} of 55`}</figcaption></figure>`;
    return `
    <div class="card-dark2 p-4 mb-3">
      <div style="font-size:.6rem;color:#a78bfa;letter-spacing:1.5px;font-weight:700;text-transform:uppercase;margin-bottom:.8rem">
        ⑦ 🃏 Gypsy Cards · Fortune Telling
      </div>
      <div class="dv-tarot-layout">
        ${cardImage}
        <div style="flex:1;min-width:0">
          <div style="font-size:1.3rem;font-weight:800;color:#e0d0ff;margin-bottom:.2rem">${_tc.n||''}</div>
          <div style="font-size:.75rem;color:#a78bfa;margin-bottom:.7rem;letter-spacing:.3px">${_tc.v||''}${_tc.s||''} &middot; Gypsy Cards · Fortune Telling</div>
          <div style="font-size:.85rem;color:#bbb;line-height:1.8;margin-bottom:.85rem;
                      padding:.75rem 1rem;background:rgba(167,139,250,.06);
                      border-left:3px solid #a78bfa;border-radius:0 8px 8px 0">${_tc.h||''}</div>
          <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.3rem">
            <span style="font-size:.72rem;color:var(--muted);white-space:nowrap;letter-spacing:.5px;text-transform:uppercase">Fortune Score</span>
            ${_sb(tcScore)}
            <span style="font-size:1.4rem;font-weight:800;color:${tcColor};white-space:nowrap">${tcScore}<span style="font-size:.6rem;color:var(--muted)">/10</span></span>
          </div>
          <div style="font-size:.72rem;color:var(--muted)">${_st(tcScore)}</div>
        </div>
      </div>
    </div>`;
  })() : ''}

  <div style="font-size:.65rem;color:var(--muted);text-align:center;margin-top:.5rem">
    <a href="index.php" style="color:var(--accent);text-decoration:none">← Back to Oracle</a>
    &nbsp;·&nbsp; Next reading available 90 minutes from your last draw
  </div>
</div>`;

  el.style.display = 'block';
  if (new URLSearchParams(window.location.search).get('reading') === 'latest') {
    setTimeout(() => el.scrollIntoView({behavior:'smooth', block:'start'}), 0);
  }
}

// ══════════════════════════════════════════════════════════════════
// MILESTONE FORM (partial date inputs)
// ══════════════════════════════════════════════════════════════════
function buildMilestoneForm() {
  const container = document.getElementById('milestones-form');
  const typeBadge = {
    peak:       '<span style="font-size:.65rem;background:#1a3a1a;color:#5cb85c;border:1px solid #5cb85c;padding:1px 7px;border-radius:10px">PEAK</span>',
    low:        '<span style="font-size:.65rem;background:#3a0d0d;color:#e05252;border:1px solid #e05252;padding:1px 7px;border-radius:10px">LOW</span>',
    transition: '<span style="font-size:.65rem;background:#1a2a3a;color:#58a6ff;border:1px solid #58a6ff;padding:1px 7px;border-radius:10px">SHIFT</span>',
  };

  // Month options
  const moOpts = ['<option value="">Month?</option>',
    ...MONTHS.slice(1).map((n,i)=>`<option value="${i+1}">${n}</option>`)].join('');

  // Day options
  let dyOpts = '<option value="">Day?</option>';
  for (let d=1;d<=31;d++) dyOpts += `<option value="${d}">${String(d).padStart(2,'0')}</option>`;

  let html = '';
  MILESTONES_DEF.forEach(m => {
    const sv   = savedMs[m.id] || {};
    const yr   = sv.year  || '';
    const mo   = sv.month || '';
    const dy   = sv.day   || '';
    const chips = m.cycles.map(c =>
      `<span style="font-size:.7rem;background:${CYCLE_COLORS[c]}22;color:${CYCLE_COLORS[c]};border:1px solid ${CYCLE_COLORS[c]}44;padding:1px 8px;border-radius:10px">${CYCLE_NAMES[c]}</span>`
    ).join(' ');

    const isFirst = m.id === 1;
    const readonlyAttr = isFirst ? 'readonly style="opacity:.6"' : '';

    html += `
    <div class="milestone-row d-flex align-items-start gap-3">
      <div class="milestone-num mt-1">${m.id}</div>
      <div class="flex-grow-1">
        <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
          <span style="font-weight:600;font-size:.9rem">${m.label}</span>
          ${typeBadge[m.type]} ${chips}
        </div>
        <div style="font-size:.75rem;color:var(--muted);margin-bottom:.4rem">${m.note}</div>
        <div class="pdate-wrap">
          <input type="number" id="ms-yr-${m.id}" class="form-control form-control-sm pdate-year"
                 placeholder="YYYY" min="1900" max="2099" value="${yr}" ${readonlyAttr}>
          <select id="ms-mo-${m.id}" class="form-select form-select-sm pdate-month" ${isFirst?'disabled':''}>
            ${moOpts.replace(`value="${mo}"`,`value="${mo}" selected`)}
          </select>
          <select id="ms-dy-${m.id}" class="form-select form-select-sm pdate-day" ${isFirst?'disabled':''}>
            ${dyOpts.replace(`value="${dy}"`,`value="${dy}" selected`)}
          </select>
        </div>
      </div>
    </div>`;
  });
  container.innerHTML = html;

  // Auto-fill milestone 1 from DOB
  if (currentUser) syncDobToMs1();
}

function syncDobToMs1() {
  const dob = currentUser?.dob || '';
  if (!dob) return;
  const [yr, mo, dy] = dob.split('-');
  const yrEl = document.getElementById('ms-yr-1');
  const moEl = document.getElementById('ms-mo-1');
  const dyEl = document.getElementById('ms-dy-1');
  if (yrEl) yrEl.value = yr;
  if (moEl) { moEl.value = parseInt(mo); moEl.disabled = true; }
  if (dyEl) { dyEl.value = parseInt(dy); dyEl.disabled = true; }
}

// ══════════════════════════════════════════════════════════════════
// SAVE PROFILE (milestones)
// ══════════════════════════════════════════════════════════════════
function fillProfileFields(u) {
  if (!u) return;
  document.getElementById('p-name').value  = u.name  || '';
  document.getElementById('p-dob').value   = u.dob   || '';
  document.getElementById('p-btime').value = u.birth_time || '';
  document.getElementById('p-bcity').value = u.birth_city || '';
  if (u.blood_type) document.getElementById('p-blood').value = u.blood_type;
  const mbtiEl = document.getElementById('p-mbti');
  if (mbtiEl && u.mbti_type) { mbtiEl.value = u.mbti_type; showMBTIDesc('p-mbti','p-mbti-desc'); }
  if (u.birth_lat) { _profileCityLat = u.birth_lat; _profileCityLon = u.birth_lon; }
}

let _profileCityLat = null, _profileCityLon = null, _profileCityTimer = null;

function profileCitySearch(val) {
  _profileCityLat = null; _profileCityLon = null;
  clearTimeout(_profileCityTimer);
  _profileCityTimer = setTimeout(() => _cityAutocomplete(val, 'p-city-dropdown', (lat, lon, label) => {
    _profileCityLat = lat; _profileCityLon = lon;
    document.getElementById('p-bcity').value = label;
  }), 300);
}

async function saveDetails() {
  if (!currentUser) return;
  const btime = document.getElementById('p-btime').value.trim();
  const bcity = document.getElementById('p-bcity').value.trim();
  const btype = document.getElementById('p-blood').value;
  const mbti  = (document.getElementById('p-mbti')?.value || '').trim();
  let blat = _profileCityLat ?? currentUser.birth_lat ?? '';
  let blon = _profileCityLon ?? currentUser.birth_lon ?? '';

  const res = await api({ action:'save_details', birth_time:btime, birth_city:bcity,
    birth_lat:blat, birth_lon:blon, blood_type:btype, mbti_type:mbti });
  if (res.ok) {
    currentUser.mbti_type  = res.mbti_type || mbti;
    currentUser.birth_time = btime;
    currentUser.birth_city = bcity;
    currentUser.birth_lat  = blat || null;
    currentUser.birth_lon  = blon || null;
    currentUser.blood_type = btype || null;
    // Update saved login in localStorage
    try {
      const s = JSON.parse(localStorage.getItem('bio_saved_login') || '{}');
      s.btime = btime; s.bcity = bcity; s.blat = blat; s.blon = blon; s.btype = btype;
      localStorage.setItem('bio_saved_login', JSON.stringify(s));
    } catch(_){}
    initCompatTab();
    const msg = document.getElementById('details-saved');
    msg.style.display = 'inline'; setTimeout(() => msg.style.display = 'none', 2500);
  } else { alert('Save failed: ' + (res.error || 'Unknown error')); }
}

async function saveHarmonyMBTI() {
  if (currentUser?.level !== 'platinum') return;
  const harmonyEl = document.getElementById('harmony-self-mbti');
  const profileEl = document.getElementById('p-mbti');
  if (!harmonyEl?.value || !profileEl) return;
  profileEl.value = harmonyEl.value;
  await saveDetails();
  if (currentUser?.mbti_type === harmonyEl.value) {
    const msg = document.getElementById('harmony-mbti-saved');
    if (msg) { msg.style.display='inline'; setTimeout(()=>msg.style.display='none',2500); }
  }
}

window.applyQuickMBTI = function (type) {
  const valid = ['INTJ','INTP','INFJ','INFP','ENTJ','ENTP','ENFJ','ENFP','ISTJ','ISTP','ISFJ','ISFP','ESTJ','ESTP','ESFJ','ESFP'];
  if (!valid.includes(type) || currentUser?.level !== 'platinum') return false;
  const profileEl = document.getElementById('p-mbti');
  const harmonyEl = document.getElementById('harmony-self-mbti');
  if (profileEl) { profileEl.value = type; showMBTIDesc('p-mbti','p-mbti-desc'); }
  if (harmonyEl) harmonyEl.value = type;
  switchTab('compat');
  return true;
};

window.addEventListener('focus', () => {
  try {
    const type = localStorage.getItem('dharmavera_mbti_result');
    if (type && window.applyQuickMBTI(type)) localStorage.removeItem('dharmavera_mbti_result');
  } catch (_) {}
});

async function saveProfile() {
  if (!currentUser) { alert('Please log in first.'); return; }

  const milestones = [];
  MILESTONES_DEF.forEach(m => {
    const yr = parseInt(document.getElementById('ms-yr-'+m.id)?.value)||0;
    if (!yr) return;
    const mo = parseInt(document.getElementById('ms-mo-'+m.id)?.value)||0;
    const dy = parseInt(document.getElementById('ms-dy-'+m.id)?.value)||0;
    milestones.push({ id:m.id, year:yr, month:mo||null, day:dy||null });
    savedMs[m.id] = { year:yr, month:mo||null, day:dy||null };
  });

  const res = await api({ action:'save_milestones', milestones:JSON.stringify(milestones) });
  if (!res.error) {
    document.getElementById('profile-saved').style.display='block';
    setTimeout(()=>document.getElementById('profile-saved').style.display='none',2000);
  } else alert('Save failed: ' + res.error);
}

// ══════════════════════════════════════════════════════════════════
// DAILY LOG
// ══════════════════════════════════════════════════════════════════
const SLIDERS = [
  { key:'physical',     label:'Physical Energy',    icon:'bi-lightning-charge-fill', color:'var(--c-phys)', dbKey:'p' },
  { key:'emotional',    label:'Emotional Mood',     icon:'bi-heart-fill',            color:'var(--c-emo)',  dbKey:'e' },
  { key:'intellectual', label:'Mental Clarity',     icon:'bi-lightbulb-fill',        color:'var(--c-int)',  dbKey:'i' },
  { key:'luck',         label:'Luck / Flow',        icon:'bi-stars',                 color:'var(--c-luck)', dbKey:'luck' }
];

function buildLogCard(pfx, dateStr, dateLabel) {
  const isY = pfx !== '';
  const idSfx = pfx;
  const sliders = SLIDERS.map(s => `
    <div class="mb-3">
      <div class="d-flex justify-content-between align-items-center mb-1">
        <label class="form-label mb-0" style="font-size:.82rem;color:${s.color}">
          <i class="bi ${s.icon} me-1"></i>${s.label}
        </label>
        <span id="${idSfx}pill-${s.key}" style="font-weight:700;font-size:.9rem;color:${s.color};min-width:24px;text-align:right">5</span>
      </div>
      <input type="range" min="1" max="10" value="5" id="${idSfx}sl-${s.key}" class="form-range"
        style="accent-color:${s.color}"
        oninput="setPill('${idSfx}','${s.key}',this.value)">
      <div class="slider-range-labels"><span>Low</span><span>Medium</span><span>High</span></div>
    </div>`).join('');

  return `
    <div class="card-dark p-4 mb-4" style="border-left:4px solid ${isY ? 'var(--accent2)' : 'var(--accent)'}">
      <div class="section-title">
        <i class="${isY ? 'bi-calendar2-minus' : 'bi-pencil-square'} bi me-1"></i>
        ${isY ? "Yesterday's Feeling Log" : "Today's Feeling Log"}
        <span style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--muted);font-size:.75rem;margin-left:.5rem" id="${idSfx}log-date-display">${dateLabel}</span>
      </div>
      <div id="${idSfx}no-profile-warn" class="alert-warm mb-3" style="display:none">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>Log in to save your daily entry.
      </div>
      <div id="${idSfx}daily-exists" class="msg-info mb-3" style="display:none">
        <i class="bi bi-check-circle-fill me-1" style="color:var(--green)"></i>Entry already saved for this day.
      </div>
      ${sliders}
      <div class="mb-3">
        <label class="form-label" style="font-size:.82rem"><i class="bi bi-chat-square-text me-1"></i>Notes <span class="opt">(optional)</span></label>
        <textarea id="${idSfx}log-notes" class="form-control" rows="2" placeholder="How did this day feel?"></textarea>
      </div>
      <div class="d-flex align-items-center gap-3">
        <button class="btn btn-primary px-4" onclick="saveDayEntry('${idSfx}','${dateStr}')">
          <i class="bi bi-floppy me-1"></i>Save Entry
        </button>
        <span id="${idSfx}daily-saved" class="msg-ok" style="display:none"><i class="bi bi-check-circle-fill me-1"></i>Saved!</span>
      </div>
    </div>`;
}

function setPill(pfx, id, val) {
  const el = document.getElementById(pfx+'pill-'+id);
  if (el) el.textContent = val;
}

function initDailyTab() {
  const now  = new Date();
  const yest = new Date(now); yest.setDate(yest.getDate()-1);
  const todayLabel = now.toLocaleDateString('en-GB',{weekday:'long',day:'numeric',month:'long',year:'numeric'});
  const yestLabel  = yest.toLocaleDateString('en-GB',{weekday:'long',day:'numeric',month:'long',year:'numeric'});
  const todayDate  = todayStr();
  const yestDate   = yesterdayStr();

  document.getElementById('log-cards-container').innerHTML =
    buildLogCard('', todayDate, todayLabel) +
    buildLogCard('y', yestDate, yestLabel);

  document.getElementById('no-profile-warn').style.display  = currentUser ? 'none' : 'block';
  document.getElementById('yno-profile-warn').style.display = currentUser ? 'none' : 'block';

  _fillLogCard('', todayDate);
  _fillLogCard('y', yestDate);
  updateProgress();
}

function _fillLogCard(pfx, dateStr) {
  const entry = dailyLog[dateStr];
  if (!entry) return;
  SLIDERS.forEach(s => {
    const sl = document.getElementById(pfx+'sl-'+s.key);
    const v  = entry[s.dbKey] || 5;
    if (sl) { sl.value = v; setPill(pfx, s.key, v); }
  });
  const notes = document.getElementById(pfx+'log-notes');
  if (notes) notes.value = entry.notes || '';
  const ex = document.getElementById(pfx+'daily-exists');
  if (ex) ex.style.display = 'block';
}

async function saveDayEntry(pfx, dateStr) {
  if (!currentUser) { alert('Please log in first.'); return; }
  const vals = {};
  SLIDERS.forEach(s => {
    vals[s.dbKey] = parseInt(document.getElementById(pfx+'sl-'+s.key).value);
  });
  const notes = document.getElementById(pfx+'log-notes').value.trim();
  dailyLog[dateStr] = { ...vals, notes };

  const res = await api({ action:'save_daily', date:dateStr, ...vals, notes });
  if (!res.error) {
    const saved = document.getElementById(pfx+'daily-saved');
    const ex    = document.getElementById(pfx+'daily-exists');
    if (saved) { saved.style.display='block'; setTimeout(()=>saved.style.display='none',2000); }
    if (ex)    ex.style.display = 'block';
    updateProgress();
  } else alert('Save failed: ' + res.error);
}

function updateProgress() {
  const days = Object.keys(dailyLog).length;
  const pct  = Math.min(100, Math.round(days/33*100));
  document.getElementById('prog-days').textContent   = days;
  document.getElementById('prog-needed').textContent = Math.max(0,33-days);
  document.getElementById('prog-pct').textContent    = pct+'%';
  document.getElementById('prog-bar').style.width    = pct+'%';
  document.getElementById('prog-msg').innerHTML = days>=33
    ? '<span style="color:#5cb85c"><i class="bi bi-check-circle-fill me-1"></i>Your personalized chart is ready!</span>'
    : `${33-days} more days until your personalized chart is complete.`;
}

// ══════════════════════════════════════════════════════════════════
// CHART
// ══════════════════════════════════════════════════════════════════
let bioChartInst = null;

function drawChart() {
  if (!currentUser?.dob) {
    document.getElementById('chart-no-data').style.display = 'block';
    document.getElementById('chart-wrap').style.display    = 'none';
    document.getElementById('chart-legend').style.display  = 'none';
    return;
  }
  document.getElementById('chart-no-data').style.display = 'none';
  document.getElementById('chart-wrap').style.display    = 'block';
  document.getElementById('chart-legend').style.display  = 'block';
  document.getElementById('chart-title').textContent = (currentUser.name||'My') + "'s Personal Rhythm Chart";

  const rangeDays   = parseInt(document.getElementById('chart-range').value);
  const today       = new Date();
  const startOffset = -Math.floor(rangeDays/2);
  const cycleIndex  = value => parseFloat((((value + 1) / 2 * 9) + 1).toFixed(2));
  const labels=[], pPred=[], ePred=[], iPred=[];
  const pActual=[], eActual=[], iActual=[], luckLine=[], oraclePulseLine=[];
  const msDates={};
  const oracleByDate={};
  (oracleReadings || []).forEach(reading => {
    const date = String(reading.asked_at || '').slice(0,10);
    const score = Number(reading.score);
    if (!/^\d{4}-\d{2}-\d{2}$/.test(date) || !Number.isFinite(score)) return;
    if (!oracleByDate[date]) oracleByDate[date] = {sum:0,count:0};
    oracleByDate[date].sum += score; oracleByDate[date].count++;
  });

  // Build milestone date map
  MILESTONES_DEF.forEach(m => {
    const sv = savedMs[m.id];
    if (!sv || !sv.year) return;
    const d = partialToDate(sv.year, sv.month, sv.day);
    if (d) msDates[d] = m;
  });

  const milestoneLines=[];
  for (let i=startOffset; i<startOffset+rangeDays; i++) {
    const dt    = new Date(today.getTime()+i*86400000);
    const dtStr = dt.getFullYear()+'-'+String(dt.getMonth()+1).padStart(2,'0')+'-'+String(dt.getDate()).padStart(2,'0');
    labels.push(dt.toLocaleDateString('en-GB',{month:'short',day:'numeric'}));

    const dFromBirth = daysBetween(currentUser.dob, dtStr);
    pPred.push(cycleIndex(bioAt(dFromBirth,23)));
    ePred.push(cycleIndex(bioAt(dFromBirth,28)));
    iPred.push(cycleIndex(bioAt(dFromBirth,33)));

    const entry = dailyLog[dtStr];
    pActual.push(entry ? entry.p : null);
    eActual.push(entry ? entry.e : null);
    iActual.push(entry ? entry.i : null);
    luckLine.push(entry ? entry.luck : null);
    const pulse = oracleByDate[dtStr];
    oraclePulseLine.push(pulse ? parseFloat((pulse.sum/pulse.count).toFixed(2)) : null);

    if (msDates[dtStr]) milestoneLines.push({idx:i-startOffset, label:msDates[dtStr].label});
  }

  const todayIdx = Math.floor(rangeDays/2);
  const datasets = [
    { label:'Physical Index',      data:pPred,    borderColor:'#FF6B35', backgroundColor:'transparent', borderWidth:2, pointRadius:0, tension:.4 },
    { label:'Emotional Index',     data:ePred,    borderColor:'#4FC3F7', backgroundColor:'transparent', borderWidth:2, pointRadius:0, tension:.4 },
    { label:'Intellectual Index',  data:iPred,    borderColor:'#8338EC', backgroundColor:'transparent', borderWidth:2, pointRadius:0, tension:.4 },
    { label:'Fortune Index',       data:luckLine, borderColor:'#FFD700', backgroundColor:'transparent', borderWidth:2, pointRadius:4, pointHoverRadius:6, spanGaps:false, tension:.4, scoreScale:true },
    { label:'Oracle Pulse',        data:oraclePulseLine, borderColor:'#ff4d5f', backgroundColor:'rgba(255,77,95,.08)', borderWidth:3, pointRadius:4, pointHoverRadius:7, spanGaps:true, tension:.3, scoreScale:true },
    { label:'Physical Check-in',   data:pActual,  borderColor:'#FF6B35', backgroundColor:'#FF6B35', borderWidth:0, pointRadius:5, showLine:false, spanGaps:false, scoreScale:true },
    { label:'Emotional Check-in',  data:eActual,  borderColor:'#4FC3F7', backgroundColor:'#4FC3F7', borderWidth:0, pointRadius:5, showLine:false, spanGaps:false, scoreScale:true },
    { label:'Intellectual Check-in',data:iActual, borderColor:'#8338EC', backgroundColor:'#8338EC', borderWidth:0, pointRadius:5, showLine:false, spanGaps:false, scoreScale:true },
  ];

  if (bioChartInst) bioChartInst.destroy();
  bioChartInst = new Chart(document.getElementById('myBioChart').getContext('2d'), {
    type:'line', data:{labels,datasets},
    options:{
      responsive:true, maintainAspectRatio:false,
      interaction:{mode:'index',intersect:false},
      plugins:{
        legend:{display:false},
        tooltip:{
          backgroundColor:'#1c2128', borderColor:'#30363d', borderWidth:1,
          titleColor:'#e6edf3', bodyColor:'#8b949e',
          callbacks:{ label(ctx) {
            const v=ctx.parsed.y; if(v===null) return null;
            const s = (ctx.dataset.label.includes('Check-in') || ctx.dataset.label === 'Fortune Index')
              ? Math.round(v)+'/10' : Number(v).toFixed(ctx.dataset.label === 'Oracle Pulse' ? 1 : 2)+'/10';
            return ` ${ctx.dataset.label}: ${s}`;
          }}
        }
      },
      scales:{
        x:{ticks:{color:'#8b949e',maxTicksLimit:12,font:{size:10}},grid:{color:'#21262d'}},
        y:{min:1,max:10,ticks:{display:true,stepSize:1,color:'#8b949e',callback:v=>v+'/10'},grid:{color:'#21262d'}}
      }
    },
    plugins:[{
      id:'overlays',
      afterDraw(chart){
        const ctx2=chart.ctx, top=chart.chartArea.top, bot=chart.chartArea.bottom;
        // Today line
        const tx=chart.scales.x.getPixelForValue(todayIdx);
        ctx2.save(); ctx2.setLineDash([5,5]); ctx2.strokeStyle='rgba(255,255,255,.4)'; ctx2.lineWidth=1.5;
        ctx2.beginPath(); ctx2.moveTo(tx,top); ctx2.lineTo(tx,bot); ctx2.stroke();
        ctx2.setLineDash([]); ctx2.fillStyle='rgba(255,255,255,.7)'; ctx2.font='11px sans-serif';
        ctx2.fillText('Today',tx+4,top+14);
        // Milestone lines
        milestoneLines.forEach(ml=>{
          const x=chart.scales.x.getPixelForValue(ml.idx);
          ctx2.strokeStyle='rgba(255,215,0,.5)'; ctx2.lineWidth=1.5; ctx2.setLineDash([3,3]);
          ctx2.beginPath(); ctx2.moveTo(x,top); ctx2.lineTo(x,bot); ctx2.stroke();
          ctx2.setLineDash([]); ctx2.fillStyle='rgba(255,215,0,.8)'; ctx2.font='9px sans-serif';
          ctx2.fillText(ml.label.length>14?ml.label.substring(0,13)+'…':ml.label,x+3,top+26);
        });
        ctx2.restore();
      }
    }]
  });

  calcAccuracy();
  buildMilestoneChartList();
  renderOraclePulseSummary();
}

function calcAccuracy() {
  const entries = Object.entries(dailyLog);
  document.getElementById('accuracy-card').style.display = entries.length>=10 ? 'block':'none';
  if (entries.length<10||!currentUser?.dob) return;
  let eP=0,eE=0,eI=0,n=0;
  entries.forEach(([date,entry])=>{
    const d=daysBetween(currentUser.dob,date);
    eP+=Math.abs(sinToScore(bioAt(d,23))-entry.p);
    eE+=Math.abs(sinToScore(bioAt(d,28))-entry.e);
    eI+=Math.abs(sinToScore(bioAt(d,33))-entry.i);
    n++;
  });
  document.getElementById('acc-p').textContent=Math.max(0,Math.round((1-eP/(n*9))*100))+'%';
  document.getElementById('acc-e').textContent=Math.max(0,Math.round((1-eE/(n*9))*100))+'%';
  document.getElementById('acc-i').textContent=Math.max(0,Math.round((1-eI/(n*9))*100))+'%';
}

function buildMilestoneChartList() {
  let html='';
  MILESTONES_DEF.forEach(m=>{
    const sv=savedMs[m.id]; if(!sv||!sv.year) return;
    const dateStr=partialToDate(sv.year,sv.month,sv.day);
    if(!dateStr||!currentUser?.dob) return;
    const d=daysBetween(currentUser.dob,dateStr);
    const chips=m.cycles.map(c=>{
      const s=sinToScore(bioAt(d,CYCLE_PERIODS[c]));
      return `<span style="color:${CYCLE_COLORS[c]};font-size:.8rem">${CYCLE_NAMES[c]}: ${s}/10</span>`;
    }).join(' · ');
    const displayDate = sv.year + (sv.month ? '-'+MONTHS[sv.month] : '') + (sv.day ? '-'+String(sv.day).padStart(2,'0') : '');
    html+=`<div class="d-flex gap-3 align-items-center py-2" style="border-bottom:1px solid var(--border)">
      <div class="milestone-num">${m.id}</div>
      <div class="flex-grow-1">
        <div style="font-weight:600;font-size:.88rem">${m.label}</div>
        <div style="font-size:.75rem;color:var(--muted)">${displayDate} · ${chips}</div>
      </div><div style="font-size:.75rem;color:gold">🚩</div></div>`;
  });
  document.getElementById('milestones-list-chart').innerHTML=html||'<div style="color:var(--muted);font-size:.85rem">No milestone dates entered.</div>';
  document.getElementById('milestones-legend').style.display='block';
}

// ══════════════════════════════════════════════════════════════════
// ORACLE PULSE — saved question readings, 1–10
// ══════════════════════════════════════════════════════════════════

function oracleAnswerLabel(score) {
  return score >= 7 ? 'YES' : (score >= 5 ? 'MAYBE' : 'NOT YET');
}

async function refreshOraclePulse() {
  const data = await api({ action:'load_data' });
  if (data.ok) {
    oracleReadings = data.oracle || [];
    const chartPanel = document.getElementById('tab-chart');
    if (chartPanel && chartPanel.style.display !== 'none') drawChart();
  }
}

function renderOraclePulseSummary() {
  const rows = (oracleReadings || []).filter(r => Number.isFinite(Number(r.score)));

  const avg = rows.length ? rows.reduce((sum,r)=>sum+Number(r.score),0)/rows.length : 0;
  const latest = rows.length ? rows[rows.length-1] : null;
  document.getElementById('pulse-average').textContent = rows.length ? avg.toFixed(1) : '—';
  document.getElementById('pulse-count').textContent = rows.length;
  document.getElementById('pulse-latest').textContent = latest ? `${oracleAnswerLabel(Number(latest.score))} · ${Number(latest.score).toFixed(1)}` : '—';

  const recent = [...(oracleReadings || [])].reverse().slice(0,20);
  const table = document.getElementById('oracle-pulse-table');
  if (!table) return;
  if (!recent.length) { table.innerHTML='<div style="color:var(--muted);text-align:center;padding:1.8rem">No saved oracle questions yet.</div>'; return; }
  table.innerHTML = `<div class="table-responsive"><table style="width:100%;font-size:.8rem;border-collapse:collapse"><thead><tr style="color:var(--muted);border-bottom:1px solid var(--border)"><th style="padding:.55rem">Date</th><th style="padding:.55rem">Question</th><th style="padding:.55rem;text-align:center">Answer</th><th style="padding:.55rem;text-align:right">Score</th></tr></thead><tbody>${recent.map(r=>{
    const dt=new Date(String(r.asked_at).replace(' ','T')+'Z'); const score=Number(r.score); const color=score>=7?'#51cf66':score>=5?'#f0ad4e':'#ff7b86';
    return `<tr style="border-bottom:1px solid rgba(255,255,255,.06)"><td style="padding:.65rem;white-space:nowrap;color:var(--muted)">${dt.toLocaleDateString()}</td><td style="padding:.65rem;color:#ddd;max-width:430px">${escHtml(String(r.question||''))}</td><td style="padding:.65rem;text-align:center;font-weight:800;color:${color}">${oracleAnswerLabel(score)}</td><td style="padding:.65rem;text-align:right;font-weight:850;color:${color}">${score.toFixed(1)}/10</td></tr>`;
  }).join('')}</tbody></table></div>`;
}

// ══════════════════════════════════════════════════════════════════
// HISTORY
// ══════════════════════════════════════════════════════════════════
function buildHistory() {
  const entries=Object.entries(dailyLog).sort((a,b)=>b[0].localeCompare(a[0]));
  if (!entries.length) {
    document.getElementById('history-table').innerHTML='<div style="color:var(--muted);text-align:center;padding:2rem">No entries yet.</div>';
    return;
  }
  let html=`<div style="overflow-x:auto"><table style="width:100%;font-size:.85rem;border-collapse:collapse">
  <thead><tr style="color:var(--muted);font-size:.75rem;text-transform:uppercase;letter-spacing:.5px">
    <th style="padding:.5rem;border-bottom:1px solid var(--border)">Date</th>
    <th style="padding:.5rem;border-bottom:1px solid var(--border);color:#FF6B35">Phy</th>
    <th style="padding:.5rem;border-bottom:1px solid var(--border);color:#4FC3F7">Emo</th>
    <th style="padding:.5rem;border-bottom:1px solid var(--border);color:#8338EC">Int</th>
    <th style="padding:.5rem;border-bottom:1px solid var(--border);color:#FFD700">Luck</th>
    <th style="padding:.5rem;border-bottom:1px solid var(--border)">Notes</th>
    <th></th>
  </tr></thead><tbody>`;

  entries.forEach(([date,entry])=>{
    html+=`<tr style="border-bottom:1px solid var(--border)22">
      <td style="padding:.5rem;white-space:nowrap">${date}</td>
      <td style="padding:.5rem;text-align:center;color:#FF6B35;font-weight:700">${entry.p}</td>
      <td style="padding:.5rem;text-align:center;color:#4FC3F7;font-weight:700">${entry.e}</td>
      <td style="padding:.5rem;text-align:center;color:#8338EC;font-weight:700">${entry.i}</td>
      <td style="padding:.5rem;text-align:center;color:#FFD700;font-weight:700">${entry.luck||'—'}</td>
      <td style="padding:.5rem;color:var(--muted);max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${escHtml(entry.notes||'')}</td>
      <td style="padding:.5rem"><button onclick="deleteEntry('${date}')" style="background:none;border:none;color:#e05252;cursor:pointer">✕</button></td>
    </tr>`;
  });
  html+='</tbody></table></div>';
  document.getElementById('history-table').innerHTML=html;
}

async function deleteEntry(date) {
  if (!confirm('Delete entry for '+date+'?')) return;
  delete dailyLog[date];
  // DB delete — use save_daily with a delete flag or just let it be (no server delete endpoint yet)
  buildHistory(); updateProgress();
}

async function clearAllData() {
  if (!confirm('Delete ALL daily log entries?')) return;
  dailyLog={};
  buildHistory(); updateProgress();
}

// ══════════════════════════════════════════════════════════════════
// TAB SWITCHING
// ══════════════════════════════════════════════════════════════════
function switchTab(name) {
  ['profile','daily','chart','history','compat'].forEach(t=>{
    document.getElementById('tab-'+t).style.display=t===name?'block':'none';
  });
  document.querySelectorAll('.nav-link[data-tab]').forEach(btn=>{
    btn.classList.toggle('active',btn.dataset.tab===name);
  });
  if (name==='daily')   initDailyTab();
  if (name==='chart')   drawChart();
  if (name==='history') buildHistory();
  if (name==='compat')  { initCompatTab(); loadCompatSaves(); }
}

function applyRequestedView() {
  const requested = new URLSearchParams(window.location.search).get('tab');
  if (!['profile','daily','chart','history','compat'].includes(requested)) return;
  const button = document.querySelector(`.nav-link[data-tab="${requested}"]`);
  if (button && button.style.display !== 'none') switchTab(requested);
}

document.querySelectorAll('.nav-link[data-tab]').forEach(btn=>{
  btn.addEventListener('click',()=>switchTab(btn.dataset.tab));
});

function escHtml(s) { return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

// ══════════════════════════════════════════════════════════════════
// COMPATIBILITY — Chinese Zodiac & Mahabote
// ══════════════════════════════════════════════════════════════════
(function(){

const czAnimals = [
  {name:'Rat',    icon:'🐀',trait:'clever and resourceful'},
  {name:'Ox',     icon:'🐂',trait:'steady and dependable'},
  {name:'Tiger',  icon:'🐯',trait:'bold and passionate'},
  {name:'Rabbit', icon:'🐇',trait:'gentle and perceptive'},
  {name:'Dragon', icon:'🐲',trait:'powerful and visionary'},
  {name:'Snake',  icon:'🐍',trait:'wise and intuitive'},
  {name:'Horse',  icon:'🐴',trait:'free-spirited and energetic'},
  {name:'Goat',   icon:'🐐',trait:'creative and empathetic'},
  {name:'Monkey', icon:'🐒',trait:'witty and versatile'},
  {name:'Rooster',icon:'🐓',trait:'sharp and disciplined'},
  {name:'Dog',    icon:'🐕',trait:'loyal and protective'},
  {name:'Pig',    icon:'🐖',trait:'generous and sincere'},
];

const czCompatTable = [
  {best:[1,4,8],    soso:[0,2],      worst:[6,9]          },
  {best:[0,5,9],    soso:[1,8],      worst:[2,4,6,7]      },
  {best:[4,6,11],   soso:[0,3],      worst:[1,2,5,8]      },
  {best:[7,8,10,11],soso:[2,3,4,6],  worst:[5,9]          },
  {best:[9,0,8],    soso:[3,6],      worst:[1,7,10]       },
  {best:[4,9],      soso:[10],       worst:[2,3,5,7,11]   },
  {best:[2,7,3],    soso:[4,8,10],   worst:[0,1,9,6]      },
  {best:[6,3,11],   soso:[9],        worst:[1,2,10]       },
  {best:[1,3],      soso:[6,9],      worst:[2,11]         },
  {best:[1,5],      soso:[7,8,11],   worst:[0,3,2,6,9,10] },
  {best:[3],        soso:[1,5,10,6], worst:[4,7,9]        },
  {best:[2,3,7],    soso:[9],        worst:[5,8]          },
];

const zooMatrix = [
  [3,2,3,2,1,3,2,1],
  [2,3,3,2,3,2,1,2],
  [3,3,3,2,2,1,2,3],
  [2,2,2,3,3,3,2,1],
  [1,3,2,3,3,2,3,2],
  [3,2,1,3,2,3,3,2],
  [2,1,2,2,3,3,3,2],
  [1,2,3,1,2,2,2,3],
];

const myanmarAnimals = [
  {name:'Garuda',            icon:'🦅', day:'Sunday'       },
  {name:'Tiger',             icon:'🐯', day:'Monday'       },
  {name:'Lion',              icon:'🦁', day:'Tuesday'      },
  {name:'Tusked Elephant',   icon:'🐘', day:'Wednesday AM' },
  {name:'Tuskless Elephant', icon:'🐘', day:'Wednesday PM' },
  {name:'Mouse',             icon:'🐀', day:'Thursday'     },
  {name:'Guinea Pig',        icon:'🐹', day:'Friday'       },
  {name:'Naga',              icon:'🐉', day:'Saturday'     },
];

const mhbMatrix = [
  [2,0,1,1,2,1,1],
  [0,2,1,1,2,1,1],
  [1,1,3,3,2,3,4],
  [1,1,3,3,2,4,3],
  [2,2,2,2,2,2,2],
  [1,1,3,4,2,3,3],
  [1,1,4,3,2,3,3],
];
const mhbIdx = {Binga:0,Marana:1,Adipati:2,Athoung:3,Thoike:4,Raja:5,Puti:6};
const mhbInfo = {
  Binga:  {symbol:'⚡',en:'The Breaker',     color:'#d97706'},
  Marana: {symbol:'🌑',en:'The Transformer', color:'#6D6875'},
  Adipati:{symbol:'⭐',en:'The Supreme Lord', color:'#8338EC'},
  Athoung:{symbol:'🌱',en:'The Flourisher',  color:'#5cb85c'},
  Thoike: {symbol:'🏠',en:'The Nester',      color:'#48CAE4'},
  Raja:   {symbol:'👑',en:'The Noble',        color:'#FF9F1C'},
  Puti:   {symbol:'🔥',en:'The Resilient',   color:'#E63946'},
};
const mhbTable = [
  ['Puti','Thoike','Marana','Adipati','Raja','Athoung','Binga'],
  ['Binga','Puti','Thoike','Marana','Adipati','Raja','Athoung'],
  ['Athoung','Binga','Puti','Thoike','Marana','Adipati','Raja'],
  ['Raja','Athoung','Binga','Puti','Thoike','Marana','Adipati'],
  ['Adipati','Raja','Athoung','Binga','Puti','Thoike','Marana'],
  ['Marana','Adipati','Raja','Athoung','Binga','Puti','Thoike'],
  ['Thoike','Marana','Adipati','Raja','Athoung','Binga','Puti'],
];
const mhbPairDesc = {
  'Adipati+Puti':  'Puti & Adipati — an exceptional partnership. Together you are highly effective and achieve results that neither could reach alone. Working together leads to clear success.',
  'Athoung+Raja':  'Athoung & Raja — a deeply supportive match. You naturally lift each other up and bring out each other\'s finest qualities. A strong, collaborative bond.',
  'Thoike+Thoike': 'Thoike & Thoike — simple and steady. Two Nesters understand each other\'s rhythms without explanation. A stable, enduring, and grounded pairing.',
  'Binga+Marana':  'Binga & Marana — traditionally considered opposing forces (enemy pair). Friction can be frequent. Mutual patience, respect, and awareness are essential to make this work.',
};

function dobToCompatData(dob, time) {
  const yr  = parseInt(dob.substring(0,4));
  const mo  = parseInt(dob.substring(5,7));
  const dy  = parseInt(dob.substring(8,10));
  const dow = new Date(dob + 'T12:00:00').getDay();
  const czIdx = ((yr - 1900) % 12 + 12) % 12;
  const zooMap = {0:0,1:1,2:2,3:3,4:5,5:6,6:7};
  let zooIdx = zooMap[dow] ?? 0;
  if (dow === 3 && time && parseInt(time.split(':')[0]) >= 12) zooIdx = 4;
  const meYear = (mo < 4 || (mo===4 && dy < 17)) ? yr-639 : yr-638;
  const meRem  = ((meYear % 7) + 7) % 7;
  const mhbName = mhbTable[meRem][dow];
  return { yr, czIdx, zooIdx, mhbName };
}

function getCzCat(from, to) {
  const t = czCompatTable[from];
  if (t.best.includes(to))  return 'best';
  if (t.soso.includes(to))  return 'soso';
  if (t.worst.includes(to)) return 'worst';
  return 'neutral';
}
function scoreToCat(pct) {
  if (pct >= 80) return {label:'Excellent ✨', color:'#5cb85c'};
  if (pct >= 65) return {label:'Good 💫',      color:'#48cae4'};
  if (pct >= 45) return {label:'So-so 🤝',     color:'#f0ad4e'};
  return              {label:'Challenging ⚡', color:'#e05252'};
}
function czCatBadge(cat) {
  const cls = {best:'badge-do',neutral:'badge-ok',soso:'badge-caution',worst:'badge-dont'}[cat];
  const lbl = {best:'Best Match',neutral:'Neutral',soso:'So-so',worst:'Challenging'}[cat];
  return `<span class="status-badge ${cls}">${lbl}</span>`;
}
function zooPairResult(sum) {
  if (sum >= 6) return {label:'Match ✅',      color:'#5cb85c'};
  if (sum >= 5) return {label:'Good Pair 💫',  color:'#48cae4'};
  if (sum >= 4) return {label:'Neutral ⚖️',    color:'#8b949e'};
  if (sum >= 3) return {label:'Friction ⚠️',   color:'#f0ad4e'};
  return              {label:'Clash ❌',        color:'#e05252'};
}
function zooVal(v) { return {3:'Match',2:'Neutral',1:'Unmatch'}[v] || '—'; }
function zooValColor(v) { return {3:'#5cb85c',2:'#8b949e',1:'#e05252'}[v] || '#8b949e'; }
function mhbCompatResult(val) {
  return [
    {label:'Conflicting',desc:'Opposing chart energies — conscious effort and acceptance of each other\'s nature is essential.', color:'#e05252'},
    {label:'Caution',    desc:'Some contrasting energies create friction. Awareness and communication help navigate this.',      color:'#f0ad4e'},
    {label:'Stable',     desc:'A balanced, steady pairing. Simple and grounded. You coexist in calm equilibrium.',             color:'#8b949e'},
    {label:'Good',       desc:'A supportive pairing. Both chart positions complement and uplift each other well.',             color:'#48cae4'},
    {label:'Excellent',  desc:'A highly auspicious pairing. Together you work effectively and achieve real success.',          color:'#5cb85c'},
  ][val] || {label:'Stable',desc:'',color:'#8b949e'};
}
function buildCzDesc(pct, n1, n2, t1, t2) {
  const cat = pct >= 80 ? 'best' : pct >= 65 ? 'good' : pct >= 45 ? 'soso' : 'worst';
  return ({
    best: `${n1} and ${n2} are a natural pairing. Your ${t1} and ${t2} qualities complement each other deeply — shared instincts and mutual respect make this flow with surprising ease.`,
    good: `${n1} and ${n2} make a positive pair. With open communication, your ${t1} energy and ${t2} nature become strengths that build on each other rather than friction points.`,
    soso: `${n1} and ${n2} can work well together, but it takes effort. Your ${t1} approach and ${t2} style don't always sync naturally — patience and honesty are the keys to success.`,
    worst:`${n1} and ${n2} have contrasting energies that can clash. The ${t1} and ${t2} natures pull in different directions — but awareness and genuine respect make it workable.`,
  })[cat];
}

// ── Synastry helpers (delegate to baydin_ephemeris.js) ─────────────

function synScoreLabel(s) {
  if (s >= 9) return 'Excellent ✨';
  if (s >= 7) return 'Good 💫';
  if (s >= 5) return 'Average 🤝';
  if (s >= 3) return 'Challenging ⚠️';
  return 'Conflicting ❌';
}
function synScoreColor(s) { return s>=8?'#5cb85c':s>=6?'#48cae4':s>=4?'#f0ad4e':'#e05252'; }

function buildSynastryHTML(n1, n2, pos1, pos2, czPct, burPct) {
  if (blockCfg.compat_synastry?.visible === false) return '';
  const pRow = (lbl, lon) => {
    if (lon === undefined) return '';
    const s = Ephemeris.signOf(lon);
    const d = Ephemeris.degInSign(lon);
    return `<span style="font-size:.75rem;margin-right:10px;white-space:nowrap">
      <span style="color:var(--muted)">${lbl}:</span>
      <strong>${s.icon} ${d}° ${s.name}</strong>
      <span style="color:var(--muted);font-size:.67rem"> ${s.el}</span></span>`;
  };

  const hasExtra = pos1.hasTime && pos1.hasLoc && pos2.hasTime && pos2.hasLoc;
  const anyLoc   = pos1.hasLoc  || pos2.hasLoc;
  const anyTime  = pos1.hasTime || pos2.hasTime;

  const _warn = (msg) => `<span style="background:#2a1a00;color:#f0ad4e;border:1px solid #f0ad4e44;font-size:.7rem;padding:2px 9px;border-radius:10px;margin-left:6px">⚠ ${msg}</span>`;
  const precisionBadge = hasExtra
    ? `<span style="background:#0d2d0d;color:#5cb85c;border:1px solid #5cb85c44;font-size:.7rem;padding:2px 9px;border-radius:10px;margin-left:6px">✓ High Precision — Ascendant &amp; MC included</span>`
    : anyLoc && !anyTime  ? _warn('Birth time missing — Ascendant omitted')
    : anyTime && !anyLoc  ? _warn('Birth city missing — Ascendant omitted')
    : anyLoc && anyTime   ? _warn('Birth time or city missing for one person — Ascendant omitted')
    : _warn('No birth time / city — planet signs only');

  const { scores, aspects } = Ephemeris.calcSynastry(pos1, pos2, czPct, burPct);

  const aspectsHTML = aspects.length
    ? `<div style="margin-bottom:12px">
        <div style="font-size:.72rem;color:var(--muted);font-weight:700;letter-spacing:.8px;text-transform:uppercase;margin-bottom:6px">Key Synastry Aspects</div>
        <div class="d-flex flex-wrap gap-1">`
        + aspects.slice(0, 8).map(a =>
          `<span style="font-size:.72rem;background:${a.color}22;color:${a.color};border:1px solid ${a.color}44;padding:2px 9px;border-radius:12px" title="Orb ${a.orb}°">
             ${a.label} <span style="opacity:.6;font-size:.65rem">${a.orb}°</span></span>`
        ).join('')
        + `</div></div>`
    : `<div style="font-size:.8rem;color:var(--muted);margin-bottom:12px"><i class="bi bi-info-circle me-1"></i>No major cross-aspects found between key natal planets.</div>`;

  const DIMS = [
    { key:'emotional',    en:'Emotional Pattern',       icon:'💜' },
    { key:'attraction',   en:'Attraction',              icon:'❤️‍🔥' },
    { key:'communication',en:'Communication',           icon:'💬' },
    { key:'stability',    en:'Stability',               icon:'🏛️' },
    { key:'overall',      en:'Overall Harmony Pattern',  icon:'⭐' },
  ];

  const dimsHTML = DIMS.map(r => {
    const s = scores[r.key], c = synScoreColor(s), isOv = r.key === 'overall';
    const display = isOv
      ? `${s * 10}%`
      : `${s}<span style="font-size:.68rem;color:var(--muted)">/10</span>`;
    return `<div style="${isOv ? 'margin-top:12px;padding-top:12px;border-top:1px solid var(--border);' : ''}margin-bottom:7px">
      <div class="d-flex justify-content-between align-items-center mb-1">
        <span style="font-size:${isOv ? '1rem' : '.85rem'};font-weight:700">${r.icon} ${r.en}</span>
        <div style="text-align:right">
          <span style="font-size:${isOv ? '2rem' : '1.15rem'};font-weight:800;color:${c}">${display}</span>
          <span style="font-size:.76rem;font-weight:700;color:${c};margin-left:5px">${synScoreLabel(s)}</span>
        </div>
      </div>
      <div class="progress-custom"><div class="bar-fill" style="width:${s * 10}%;background:${c};transition:width .8s ease"></div></div>
    </div>`;
  }).join('');

  // Ascendant / MC rows (only if available)
  const ascRow = (pos, name) => pos.ascendant !== undefined
    ? `<div style="display:flex;flex-wrap:wrap;gap:4px 0;background:var(--bg-dark);padding:6px 10px;border-radius:6px;margin-top:4px">
        ${pRow('⬆ Ascendant', pos.ascendant)}${pRow('MC', pos.midheaven)}</div>` : '';

  const ov = scores.overall;
  const summary = ov >= 8
    ? `${escHtml(n1)} and ${escHtml(n2)} show many harmonious patterns in this symbolic chart comparison. Use them as prompts for discussing emotional needs, communication, and shared priorities.`
    : ov >= 6
    ? `${escHtml(n1)} and ${escHtml(n2)} show several harmonious patterns alongside some differences. Open communication and mutual respect can help them understand those differences.`
    : ov >= 4
    ? `${escHtml(n1)} and ${escHtml(n2)} have some conflicting planetary aspects in their Synastry. With mutual understanding and honest communication these challenges can be worked through and the relationship can still thrive.`
    : `${escHtml(n1)} and ${escHtml(n2)} have mostly opposing planetary positions in their Synastry chart. Building a strong relationship will require significant effort, patience, and commitment from both sides.`;

  const compSun   = Ephemeris.midpointLon(pos1.sun,     pos2.sun);
  const compMoon  = Ephemeris.midpointLon(pos1.moon,    pos2.moon);
  const compVenus = Ephemeris.midpointLon(pos1.venus,   pos2.venus);
  const compMars  = Ephemeris.midpointLon(pos1.mars,    pos2.mars);

  return `
<div class="mt-3 p-3 card-dark2" style="border-radius:10px;border-left:3px solid #c77dff">
  <div style="font-size:.88rem;color:#c77dff;font-weight:700;margin-bottom:.75rem">
    🔭 ${escHtml(blockCfg.compat_synastry?.label || 'Western Astrology — Synastry & Composite Chart')} ${precisionBadge}
  </div>

  <div class="mb-3">
    <div style="font-size:.72rem;color:var(--muted);font-weight:700;letter-spacing:.5px;text-transform:uppercase;margin-bottom:4px">Natal Planets — ${escHtml(n1)}</div>
    <div style="display:flex;flex-wrap:wrap;gap:3px 0;background:var(--bg-dark);padding:8px 10px;border-radius:8px">
      ${pRow('☉ Sun', pos1.sun)}${pRow('☽ Moon', pos1.moon)}${pRow('☿ Mercury', pos1.mercury)}${pRow('♀ Venus', pos1.venus)}${pRow('♂ Mars', pos1.mars)}${pRow('♃ Jupiter', pos1.jupiter)}${pRow('♄ Saturn', pos1.saturn)}
    </div>
    ${ascRow(pos1, n1)}

    <div style="font-size:.72rem;color:var(--muted);font-weight:700;letter-spacing:.5px;text-transform:uppercase;margin-top:8px;margin-bottom:4px">Natal Planets — ${escHtml(n2)}</div>
    <div style="display:flex;flex-wrap:wrap;gap:3px 0;background:var(--bg-dark);padding:8px 10px;border-radius:8px">
      ${pRow('☉ Sun', pos2.sun)}${pRow('☽ Moon', pos2.moon)}${pRow('☿ Mercury', pos2.mercury)}${pRow('♀ Venus', pos2.venus)}${pRow('♂ Mars', pos2.mars)}${pRow('♃ Jupiter', pos2.jupiter)}${pRow('♄ Saturn', pos2.saturn)}
    </div>
    ${ascRow(pos2, n2)}
  </div>

  ${aspectsHTML}

  <div class="mb-3" style="background:var(--bg-dark);border-radius:8px;padding:10px">
    <div style="font-size:.72rem;color:#c77dff;letter-spacing:.8px;font-weight:700;text-transform:uppercase;margin-bottom:6px">Composite Chart — Midpoint Method</div>
    <div style="display:flex;flex-wrap:wrap;gap:3px 0">
      ${pRow('☉ Relationship Sun', compSun)}${pRow('☽ Relationship Moon', compMoon)}${pRow('♀ Venus', compVenus)}${pRow('♂ Mars', compMars)}
    </div>
  </div>

  <div style="font-size:.72rem;color:var(--muted);letter-spacing:.8px;font-weight:700;text-transform:uppercase;margin-bottom:8px">Harmony Dimensions</div>
  ${dimsHTML}

  <div style="background:var(--bg-dark);border-radius:8px;padding:10px;margin-top:12px;font-size:.84rem;color:#ccc;line-height:1.9;border-left:3px solid #c77dff55">
    <div style="font-size:.72rem;color:#c77dff;letter-spacing:.8px;font-weight:700;text-transform:uppercase;margin-bottom:5px">📝 Summary</div>
    ${summary}
  </div>
</div>`;
}

window.initCompatTab = function () {
  if (!currentUser?.dob) return;
  const d    = dobToCompatData(currentUser.dob, '');
  const czA  = czAnimals[d.czIdx];
  const mhbH = mhbInfo[d.mhbName] || mhbInfo.Thoike;
  const mmA  = myanmarAnimals[d.zooIdx];
  document.getElementById('compat-you-cz-icon').textContent   = czA.icon;
  document.getElementById('compat-you-cz-label').textContent  = `${currentUser.name} — ${czA.name} (${d.yr})`;
  document.getElementById('compat-you-mhb-icon').textContent  = mhbH.symbol;
  const btBit   = currentUser.blood_type ? ` · 🩸 ${currentUser.blood_type}` : '';
  const mbtiBit = currentUser.mbti_type  ? ` · 🧠 ${currentUser.mbti_type}` : '';
  document.getElementById('compat-you-mhb-label').textContent = `${d.mhbName} · ${mmA.name}${btBit}${mbtiBit}`;
  const summary = document.getElementById('harmony-profile-summary');
  if (summary) {
    const details = [
      `Born ${currentUser.dob}`,
      currentUser.birth_time ? `at ${currentUser.birth_time}` : '',
      currentUser.birth_city || '',
      currentUser.blood_type ? `Blood ${currentUser.blood_type}` : '',
      currentUser.mbti_type ? `Personality ${currentUser.mbti_type}` : ''
    ].filter(Boolean);
    summary.textContent = details.join(' · ');
  }
  const harmonyEl = document.getElementById('harmony-self-mbti');
  if (harmonyEl && currentUser.mbti_type) harmonyEl.value = currentUser.mbti_type;
};

// ── Blood Type Compatibility ──────────────────────────────────────
const _btScore = {
  A:  {A:8, B:4, AB:7,  O:10},
  B:  {A:4, B:8, AB:10, O:10},
  AB: {A:7, B:9, AB:10, O:3},
  O:  {A:10,B:8, AB:7,  O:8}
};

const _btDesc = {
  A: {
    best:  {type:'O',  msg:'Type Os give Type As the security and stability they crave — reliable, steady, and grounding for the perfectionist A.'},
    worst: {type:'B',  msg:'Type Bs\' spontaneous, free-spirited nature clashes with Type A\'s need for structure and predictability.'}
  },
  B: {
    best:  {type:'O/AB', msg:'Type Os are easy-going and flexible — a great match for B\'s social energy. Type ABs\' rationality balances B\'s free spirit.'},
    worst: {type:'A',    msg:'Type As\' need for control and stability is the opposite of Type B\'s love of freedom and spontaneity.'}
  },
  AB: {
    best:  {type:'AB', msg:'Two ABs share calm, flexible temperaments and strong moral values — naturally in sync with each other.'},
    worst: {type:'O',  msg:'AB and O can be great friends, but as partners their clashing values often create serious friction.'}
  },
  O: {
    best:  {type:'A', msg:'Type As appreciate the natural leadership of Type Os, and both share a high work ethic — a dependable, balanced pair.'},
    worst: {type:'none', msg:'Type Os are versatile and easy-going. They can get along well with any blood type.'}
  }
};

function buildBloodCompatHTML(bt1, bt2, name1, name2) {
  if (blockCfg.compat_blood?.visible === false) return '';
  if (!bt1) return `<div class="card-dark2 p-3 mb-3" style="font-size:.8rem;color:var(--muted)">🩸 Add your blood type (optional) in <strong>Profile</strong> to include the Japanese blood-type harmony in this reading.</div>`;
  if (!bt2) return '';
  const row = _btScore[bt1];
  if (!row) return '';
  const score = row[bt2] ?? null;
  if (score === null) return '';
  const pct = Math.round(score / 10 * 100);
  const col = pct >= 80 ? '#4fc3f7' : pct >= 60 ? '#8338ec' : pct >= 40 ? '#f0ad4e' : '#e05252';
  const label = pct >= 80 ? 'Excellent' : pct >= 60 ? 'Good' : pct >= 40 ? 'Neutral' : 'Challenging';

  // Best / worst notes for each person
  const d1 = _btDesc[bt1]; const d2 = _btDesc[bt2];
  const notes1 = d1 ? `<li style="margin-bottom:.4rem"><strong style="color:var(--accent)">${escHtml(name1)} (${bt1})</strong> — Best match: <strong>${escHtml(d1.best.type)}</strong>. ${escHtml(d1.best.msg)}</li>
<li><strong style="color:var(--accent)">${escHtml(name1)} (${bt1})</strong> — Most challenging: <strong>${escHtml(d1.worst.type)}</strong>. ${escHtml(d1.worst.msg)}</li>` : '';
  const notes2 = d2 ? `<li style="margin-bottom:.4rem"><strong style="color:var(--accent)">${escHtml(name2)} (${bt2})</strong> — Best match: <strong>${escHtml(d2.best.type)}</strong>. ${escHtml(d2.best.msg)}</li>
<li><strong style="color:var(--accent)">${escHtml(name2)} (${bt2})</strong> — Most challenging: <strong>${escHtml(d2.worst.type)}</strong>. ${escHtml(d2.worst.msg)}</li>` : '';

  return `
    <div class="mt-3 p-3 card-dark2" style="border-radius:10px;border-left:3px solid #e91e8c">
      <div style="font-size:.78rem;color:#e91e8c;letter-spacing:1px;font-weight:700;text-transform:uppercase;margin-bottom:.5rem">🩸 ${escHtml(blockCfg.compat_blood?.label || 'Japan / Korea Tradition — Blood Type Compatibility')}</div>
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
        <div style="font-size:1.05rem;font-weight:700">${escHtml(bt1)} &amp; ${escHtml(bt2)}</div>
        <div><span style="font-size:1.4rem;font-weight:900;color:${col}">${pct}%</span> <span style="font-size:.85rem;color:${col};font-weight:700">${label}</span></div>
      </div>
      <div class="progress-custom mb-3"><div class="bar-fill" style="width:${pct}%;background:${col}"></div></div>
      <ul style="font-size:.82rem;color:#ccc;line-height:1.7;padding-left:1.1rem;margin:0">
        ${notes1}
        ${notes2}
      </ul>
    </div>`;
}

// ── MBTI Compatibility ────────────────────────────────────────────
const MBTI_TYPES = ['INTJ','INTP','INFJ','INFP','ENTJ','ENTP','ENFJ','ENFP',
                    'ISTJ','ISTP','ISFJ','ISFP','ESTJ','ESTP','ESFJ','ESFP'];

const MBTI_INFO = {
  INTJ:{ role:'Analyst',   icon:'🏛️',  name:'The Architect',    desc:'Strategic, private, and driven by long-term vision.' },
  INTP:{ role:'Analyst',   icon:'🔬',  name:'The Thinker',      desc:'Analytical, inventive, and endlessly curious.' },
  INFJ:{ role:'Diplomat',  icon:'🌿',  name:'The Advocate',     desc:'Insightful, principled, and quietly passionate.' },
  INFP:{ role:'Diplomat',  icon:'🌸',  name:'The Mediator',     desc:'Idealistic, empathetic, and deeply creative.' },
  ENTJ:{ role:'Analyst',   icon:'⚡',  name:'The Commander',    desc:'Bold, ambitious, and natural born leader.' },
  ENTP:{ role:'Analyst',   icon:'💡',  name:'The Debater',      desc:'Innovative, quick-witted, and loves a challenge.' },
  ENFJ:{ role:'Diplomat',  icon:'🌟',  name:'The Protagonist',  desc:'Charismatic, inspiring, and driven to help others.' },
  ENFP:{ role:'Diplomat',  icon:'🎨',  name:'The Campaigner',   desc:'Enthusiastic, creative, and sees potential everywhere.' },
  ISTJ:{ role:'Sentinel',  icon:'📋',  name:'The Logistician',  desc:'Reliable, fact-minded, and deeply dutiful.' },
  ISTP:{ role:'Explorer',  icon:'🔧',  name:'The Virtuoso',     desc:'Observant, logical, and drawn to hands-on problem solving.' },
  ISFJ:{ role:'Sentinel',  icon:'🛡️',  name:'The Defender',     desc:'Supportive, warm, and fiercely loyal.' },
  ISFP:{ role:'Explorer',  icon:'🎭',  name:'The Adventurer',   desc:'Flexible, charming, and lives in the present moment.' },
  ESTJ:{ role:'Sentinel',  icon:'⚖️',  name:'The Executive',    desc:'Organised, traditional, and keeps everything in order.' },
  ESTP:{ role:'Explorer',  icon:'🏄',  name:'The Entrepreneur', desc:'Bold, energetic, and thrives on action and excitement.' },
  ESFJ:{ role:'Sentinel',  icon:'🤝',  name:'The Consul',       desc:'Caring, sociable, and deeply invested in community.' },
  ESFP:{ role:'Explorer',  icon:'🎉',  name:'The Entertainer',  desc:'Spontaneous, energetic, and loves to be in the spotlight.' },
};

function showMBTIDesc(selectId, descId) {
  const val  = document.getElementById(selectId)?.value;
  const el   = document.getElementById(descId);
  if (!el) return;
  if (!val || !MBTI_INFO[val]) { el.style.display = 'none'; return; }
  const inf = MBTI_INFO[val];
  const roleColor = { Analyst:'var(--c-int)', Diplomat:'var(--c-emo)', Sentinel:'var(--amber)', Explorer:'var(--c-phys)' }[inf.role] || 'var(--accent)';
  el.innerHTML = `<span style="font-size:1.25rem;vertical-align:middle;margin-right:.4rem">${inf.icon}</span>
    <strong style="color:${roleColor}">${val} — ${inf.name}</strong>
    <span style="font-size:.72rem;color:var(--muted);margin-left:.35rem">(${inf.role})</span><br>
    ${escHtml(inf.desc)}
    <a href="personality.html#${val}" target="_blank" style="color:var(--accent);font-size:.78rem;margin-left:.5rem;white-space:nowrap">More details →</a>`;
  el.style.display = 'block';
}

// DharmaVera editorial style-match score. This is a communication reflection aid,
// not an official MBTI relationship-success prediction.
function personalityHarmonyScore(m1, m2) {
  if (!MBTI_TYPES.includes(m1) || !MBTI_TYPES.includes(m2)) return null;
  const dimensions = [
    { name:'Energy',      weight:1.5 },
    { name:'Information', weight:2.5 },
    { name:'Decisions',   weight:2.0 },
    { name:'Lifestyle',   weight:2.0 }
  ];
  let score = 1;
  const details = dimensions.map((dimension, index) => {
    const shared = m1[index] === m2[index];
    if (shared) score += dimension.weight;
    return { ...dimension, shared, first:m1[index], second:m2[index] };
  });
  // Sharing both information and decision preferences usually reduces the amount
  // of translation needed in everyday conversation, so the editorial model adds 1.
  if (details[1].shared && details[2].shared) score += 1;
  score = Math.max(1, Math.min(10, Math.round(score * 10) / 10));
  const label = score >= 8.5 ? 'Very easy style fit'
    : score >= 7 ? 'Strong style fit'
    : score >= 5 ? 'Balanced style fit'
    : score >= 3 ? 'Complementary styles'
    : 'More translation needed';
  return { score, label, details };
}

function buildMBTICompatHTML(m1, m2, name1, name2) {
  if (blockCfg.compat_mbti?.visible === false) return '';
  if (!m1 || !m2) return '';
  if (!MBTI_TYPES.includes(m1) || !MBTI_TYPES.includes(m2)) return '';
  const inf1  = MBTI_INFO[m1]; const inf2 = MBTI_INFO[m2];
  const roleColor = r => r==='Analyst'?'var(--c-int)':r==='Diplomat'?'var(--c-emo)':r==='Sentinel'?'var(--amber)':'var(--c-phys)';
  const pairNames = ['Energy','Information','Decisions','Lifestyle'];
  const shared = [], different = [];
  for (let i=0;i<4;i++) (m1[i] === m2[i] ? shared : different).push(`${pairNames[i]} (${m1[i]}${m1[i]===m2[i]?'':` / ${m2[i]}`})`);
  const match = personalityHarmonyScore(m1, m2);
  const scoreColor = match.score >= 8.5 ? '#5cb85c' : match.score >= 7 ? 'var(--accent)' : match.score >= 5 ? '#f0ad4e' : '#d8b4fe';

  return `
    <div class="mt-3 p-3 card-dark2" style="border-radius:10px;border-left:3px solid var(--accent2)">
      <div style="font-size:.78rem;color:var(--accent2);letter-spacing:1px;font-weight:700;text-transform:uppercase;margin-bottom:.75rem">
        🧠 ${escHtml(blockCfg.compat_mbti?.label || 'Personality Match Score')} <span class="level-badge level-platinum ms-1">💎 Platinum</span>
      </div>

      <div class="d-flex align-items-center gap-3 flex-wrap mb-3" style="background:var(--bg-dark);padding:.85rem 1rem;border-radius:10px;border:1px solid rgba(167,139,250,.3)">
        <div style="min-width:82px;text-align:center">
          <div style="font-size:1.75rem;line-height:1;font-weight:900;color:${scoreColor}">${match.score.toFixed(1)}<span style="font-size:.85rem;color:var(--muted)">/10</span></div>
          <div style="font-size:.58rem;color:var(--muted);letter-spacing:.8px;text-transform:uppercase;margin-top:4px">Style match</div>
        </div>
        <div style="flex:1;min-width:180px">
          <div style="font-size:.9rem;font-weight:800;color:${scoreColor}">${match.label}</div>
          <div style="font-size:.75rem;color:#bbb;line-height:1.55">A DharmaVera estimate of how easily these four preference styles may communicate. It is not a measure of love, friendship quality, or future success.</div>
        </div>
      </div>

      <!-- Type cards -->
      <div class="row g-2 mb-3">
        <div class="col-sm-5">
          <div class="card-dark p-2 d-flex align-items-center gap-2" style="border-radius:8px;border-left:3px solid ${roleColor(inf1.role)}">
            <div style="font-size:1.6rem;line-height:1">${inf1.icon}</div>
            <div>
              <div style="font-size:.62rem;color:${roleColor(inf1.role)};font-weight:700;letter-spacing:.8px;text-transform:uppercase">${escHtml(name1)} · ${m1}</div>
              <div style="font-size:.8rem;font-weight:700;color:var(--text)">${inf1.name}</div>
              <div style="font-size:.72rem;color:var(--muted)">${inf1.desc}</div>
            </div>
          </div>
        </div>
        <div class="col-sm-2 d-flex align-items-center justify-content-center" style="font-size:1.4rem">💞</div>
        <div class="col-sm-5">
          <div class="card-dark p-2 d-flex align-items-center gap-2" style="border-radius:8px;border-left:3px solid ${roleColor(inf2.role)}">
            <div style="font-size:1.6rem;line-height:1">${inf2.icon}</div>
            <div>
              <div style="font-size:.62rem;color:${roleColor(inf2.role)};font-weight:700;letter-spacing:.8px;text-transform:uppercase">${escHtml(name2)} · ${m2}</div>
              <div style="font-size:.8rem;font-weight:700;color:var(--text)">${inf2.name}</div>
              <div style="font-size:.72rem;color:var(--muted)">${inf2.desc}</div>
            </div>
          </div>
        </div>
      </div>

      <div class="row g-2">
        <div class="col-md-6"><div style="background:var(--bg-dark);padding:.75rem;border-radius:8px;height:100%">
          <div style="font-size:.7rem;color:#5cb85c;font-weight:800;text-transform:uppercase;margin-bottom:.35rem">Shared preferences</div>
          <div style="font-size:.8rem;color:#ccc">${shared.length ? shared.map(escHtml).join(' · ') : 'No preference letters are shared.'}</div>
        </div></div>
        <div class="col-md-6"><div style="background:var(--bg-dark);padding:.75rem;border-radius:8px;height:100%">
          <div style="font-size:.7rem;color:#d8b4fe;font-weight:800;text-transform:uppercase;margin-bottom:.35rem">Different preferences</div>
          <div style="font-size:.8rem;color:#ccc">${different.length ? different.map(escHtml).join(' · ') : 'All four preference letters are shared.'}</div>
        </div></div>
      </div>
      <div style="font-size:.74rem;color:var(--muted);line-height:1.65;margin-top:.75rem"><i class="bi bi-calculator me-1"></i><strong>How it is calculated:</strong> 1 base point; shared Energy +1.5, Information +2.5, Decisions +2, Lifestyle +2; plus 1 when Information and Decisions are both shared. Range 1–10.</div>
      <div style="font-size:.78rem;color:var(--muted);line-height:1.65;margin-top:.45rem"><i class="bi bi-info-circle me-1"></i>Shared letters may need less explanation, but they do not guarantee harmony. Different letters can bring useful balance and do not predict conflict.</div>
    </div>`;
}

// ── resetCompatForm — clear all compat fields ────────────────────
window.resetCompatForm = function () {
  document.getElementById('compat-name').value  = '';
  document.getElementById('compat-dob').value   = '';
  const ct = document.getElementById('compat-time');
  if (ct) ct.value = '';
  document.getElementById('compat-city').value  = '';
  const cb = document.getElementById('compat-blood');
  if (cb) cb.value = '';
  const cm = document.getElementById('compat-mbti');
  if (cm) cm.value = '';
  if (typeof _compatCityLat !== 'undefined') { _compatCityLat = null; _compatCityLon = null; }
  const dd = document.getElementById('compat-city-dropdown');
  if (dd) dd.innerHTML = '';
  const res = document.getElementById('compat-result');
  if (res) { res.style.display = 'none'; res.innerHTML = ''; }
  const err = document.getElementById('compat-error');
  if (err) err.style.display = 'none';
  const smsg = document.getElementById('compat-save-msg');
  if (smsg) smsg.style.display = 'none';
  document.getElementById('compat-name').focus();
};

// ── calcCompat — async to support city geocoding ────────────────────
window.calcCompat = async function () {
  const errEl = document.getElementById('compat-error');
  const resEl = document.getElementById('compat-result');
  errEl.style.display = 'none';

  if (!currentUser?.dob) {
    errEl.textContent = 'Please log in first.'; errEl.style.display = 'block'; return;
  }

  const name2  = document.getElementById('compat-name').value.trim() || 'Other Person';
  const dob2   = document.getElementById('compat-dob').value;
  const time2  = document.getElementById('compat-time').value;
  const city2  = (document.getElementById('compat-city')?.value || '').trim();
  const blood1 = currentUser?.blood_type || '';
  const blood2 = (document.getElementById('compat-blood')?.value || '').trim();
  const mbti1  = currentUser?.mbti_type || '';
  const mbti2  = (document.getElementById('compat-mbti')?.value || '').trim();
  if (!dob2) { errEl.textContent = 'Please enter a date of birth.'; errEl.style.display = 'block'; return; }

  // Use dropdown-picked coords if available, else geocode
  let geoResult = null;
  if (city2) {
    if (_compatCityLat !== null) {
      geoResult = { lat: _compatCityLat, lon: _compatCityLon, displayName: city2 };
    } else {
      resEl.innerHTML = `<div style="color:var(--muted);font-size:.88rem;padding:8px 0">
        <span class="spinner-border spinner-border-sm me-2" style="color:var(--accent)"></span>
        Looking up <strong>${escHtml(city2)}</strong>…</div>`;
      resEl.style.display = 'block';
      geoResult = await Ephemeris.geocodeCity(city2);
    }
    if (!geoResult) {
      resEl.innerHTML += `<div style="color:#f0ad4e;font-size:.8rem;margin-top:4px">
        <i class="bi bi-exclamation-triangle me-1"></i>City not found — continuing without location data.</div>`;
    }
  }

  // ── Eastern calculations (unchanged) ──────────────────────────────
  const p1 = dobToCompatData(currentUser.dob, '');
  const p2 = dobToCompatData(dob2, time2);

  const name1 = currentUser.name;
  const czA1 = czAnimals[p1.czIdx], czA2 = czAnimals[p2.czIdx];
  const mmA1 = myanmarAnimals[p1.zooIdx], mmA2 = myanmarAnimals[p2.zooIdx];
  const mhbH1 = mhbInfo[p1.mhbName] || mhbInfo.Thoike;
  const mhbH2 = mhbInfo[p2.mhbName] || mhbInfo.Thoike;

  const czC1   = getCzCat(p1.czIdx, p2.czIdx);
  const czC2   = getCzCat(p2.czIdx, p1.czIdx);
  const czBase = ({best:90,neutral:65,soso:60,worst:27}[czC1] + {best:90,neutral:65,soso:60,worst:27}[czC2]) / 2;
  const czPct  = Math.min(97, Math.max(12, Math.round(czBase + ((p1.yr + p2.yr) % 11) - 5)));

  const zooV1  = zooMatrix[p1.zooIdx][p2.zooIdx];
  const zooV2  = zooMatrix[p2.zooIdx][p1.zooIdx];
  const zooSum = zooV1 + zooV2;
  const zooPct = Math.round((zooSum - 2) / 4 * 100);

  const mI1    = mhbIdx[p1.mhbName] ?? 4;
  const mI2    = mhbIdx[p2.mhbName] ?? 4;
  const mhbVal = mhbMatrix[mI1][mI2];
  const mhbPct = Math.round(mhbVal / 4 * 100);

  const burPct = Math.round(zooPct * 0.5 + mhbPct * 0.5);

  const czSt  = scoreToCat(czPct);
  const burSt = scoreToCat(burPct);
  const zooPL = zooPairResult(zooSum);
  const mhbCL = mhbCompatResult(mhbVal);

  const pairKey = [p1.mhbName, p2.mhbName].sort().join('+');
  const mhbDesc = mhbPairDesc[pairKey] || mhbCL.desc;

  // ── Western ephemeris positions ────────────────────────────────────
  const lat2 = geoResult ? geoResult.lat : null;
  const lon2 = geoResult ? geoResult.lon : null;
  const wPos1 = Ephemeris.getPositions(currentUser.dob, currentUser.birth_time || '', currentUser.birth_lat || null, currentUser.birth_lon || null);
  const wPos2 = Ephemeris.getPositions(dob2,            time2,  lat2, lon2);

  // ── Per-system scores ──────────────────────────────────────────────
  const _synRaw   = Ephemeris.calcSynastry(wPos1, wPos2, czPct, burPct);
  const synPct    = Math.round(_synRaw.scores.overall * 10);

  const _btRow    = blood1 && blood2 ? (_btScore[blood1] || null) : null;
  const bloodPct  = (_btRow && _btRow[blood2] !== undefined) ? Math.round(_btRow[blood2] / 10 * 100) : null;
  const personalityMatch = personalityHarmonyScore(mbti1, mbti2);

  // ── Overall = average of enabled + available scores ────────────────
  const _cfg = blockCfg || {};
  const _ovScores = [];
  const _ovLabels = [];
  if (_cfg.compat_cz?.visible      !== false)                  { _ovScores.push(czPct);   _ovLabels.push('🐉'); }
  if (_cfg.compat_mhb?.visible     !== false)                  { _ovScores.push(burPct);  _ovLabels.push('🧿'); }
  if (_cfg.compat_synastry?.visible !== false)                 { _ovScores.push(synPct);  _ovLabels.push('⭐'); }
  if (bloodPct !== null && _cfg.compat_blood?.visible !== false){ _ovScores.push(bloodPct);_ovLabels.push('🩸'); }
  if (personalityMatch !== null && _cfg.compat_mbti?.visible !== false) {
    _ovScores.push(Math.round(personalityMatch.score * 10));
    _ovLabels.push('🧠');
  }
  const overall = _ovScores.length
    ? Math.round(_ovScores.reduce((a,b)=>a+b,0) / _ovScores.length)
    : Math.round(czPct * 0.45 + burPct * 0.55);
  const ovSt  = scoreToCat(overall);
  const _ovDetail = _ovLabels.map((icon, i) => `${icon} ${_ovScores[i]}%`).join(' &nbsp;·&nbsp; ');

  resEl.innerHTML = `
    <div style="font-size:1.05rem;font-weight:700;color:var(--text);margin-bottom:.85rem">
      <i class="bi bi-hearts me-2" style="color:var(--accent2)"></i>${escHtml(name1)} &amp; ${escHtml(name2)} — Harmony Reading
    </div>

    <div class="row g-2 mb-3">
      <div class="col-6">
        <div class="card-dark2 p-3 text-center" style="border-radius:10px">
          <div style="font-size:.62rem;color:var(--muted);letter-spacing:1px;text-transform:uppercase;margin-bottom:3px">${escHtml(name1)}</div>
          <div style="font-size:1.8rem;line-height:1.1">${czA1.icon}</div>
          <div style="font-size:.82rem;font-weight:700;color:var(--accent);margin:2px 0">${czA1.name} · ${p1.yr}</div>
          <div style="font-size:.78rem;color:#ccc">${mhbH1.symbol} ${p1.mhbName} <span style="color:var(--muted)">(${mhbH1.en})</span></div>
          <div style="font-size:.75rem;color:var(--muted)">${mmA1.icon} ${mmA1.name}</div>
          ${currentUser.birth_city ? `<div style="font-size:.7rem;color:var(--muted);margin-top:4px"><i class="bi bi-geo-alt me-1"></i>${escHtml(currentUser.birth_city)}</div>` : ''}
          ${blood1 ? `<div style="font-size:.7rem;color:#e91e8c;margin-top:4px">🩸 Type ${escHtml(blood1)}</div>` : ''}
        </div>
      </div>
      <div class="col-6">
        <div class="card-dark2 p-3 text-center" style="border-radius:10px">
          <div style="font-size:.62rem;color:var(--muted);letter-spacing:1px;text-transform:uppercase;margin-bottom:3px">${escHtml(name2)}</div>
          <div style="font-size:1.8rem;line-height:1.1">${czA2.icon}</div>
          <div style="font-size:.82rem;font-weight:700;color:var(--accent);margin:2px 0">${czA2.name} · ${p2.yr}</div>
          <div style="font-size:.78rem;color:#ccc">${mhbH2.symbol} ${p2.mhbName} <span style="color:var(--muted)">(${mhbH2.en})</span></div>
          <div style="font-size:.75rem;color:var(--muted)">${mmA2.icon} ${mmA2.name}</div>
          ${city2 ? `<div style="font-size:.7rem;color:var(--muted);margin-top:4px"><i class="bi bi-geo-alt me-1"></i>${escHtml(city2)}</div>` : ''}
          ${blood2 ? `<div style="font-size:.7rem;color:#e91e8c;margin-top:4px">🩸 Type ${escHtml(blood2)}</div>` : ''}
        </div>
      </div>
    </div>

    <div class="text-center p-3 mb-3 card-dark2" style="border-radius:12px;border-left:4px solid ${ovSt.color}">
      <div style="font-size:.68rem;color:var(--muted);letter-spacing:1.5px;text-transform:uppercase;margin-bottom:5px">Overall Harmony Reflection</div>
      <div style="font-size:3.2rem;font-weight:900;color:${ovSt.color};line-height:1">${overall}%</div>
      <div style="font-size:1.1rem;font-weight:700;color:${ovSt.color};margin-top:3px">${ovSt.label}</div>
      <div class="progress-custom mt-2 mx-auto" style="max-width:260px">
        <div class="bar-fill" style="width:${overall}%;background:${ovSt.color};transition:width 1s ease"></div>
      </div>
      <div style="font-size:.72rem;color:var(--muted);margin-top:.6rem">${_ovDetail}</div>
    </div>

    <div class="mb-3 p-3 card-dark2" style="display:${_cfg.compat_cz?.visible === false ? 'none' : 'block'};border-radius:10px;border-left:3px solid #FFD700">
      <div style="font-size:.78rem;color:#FFD700;letter-spacing:1px;font-weight:700;text-transform:uppercase;margin-bottom:.5rem">🐉 ${escHtml(blockCfg.compat_cz?.label || 'Chinese Zodiac')}</div>
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
        <div style="font-size:.95rem;font-weight:700">${czA1.icon} ${czA1.name} &amp; ${czA2.icon} ${czA2.name}</div>
        <div><span style="font-size:1.4rem;font-weight:900;color:${czSt.color}">${czPct}%</span> <span style="font-size:.85rem;color:${czSt.color};font-weight:700">${czSt.label}</span></div>
      </div>
      <div class="progress-custom mb-2"><div class="bar-fill" style="width:${czPct}%;background:${czSt.color}"></div></div>
      <div style="font-size:.84rem;color:#ccc;line-height:1.7;margin-bottom:.6rem">${buildCzDesc(czPct,name1,name2,czA1.trait,czA2.trait)}</div>
      <div class="d-flex gap-2 flex-wrap">
        <div class="flex-grow-1 card-dark2 p-2 text-center" style="border-radius:6px;font-size:.74rem">
          <div style="color:var(--muted);margin-bottom:2px">${czA1.name} → ${czA2.name}</div>${czCatBadge(czC1)}
        </div>
        <div class="flex-grow-1 card-dark2 p-2 text-center" style="border-radius:6px;font-size:.74rem">
          <div style="color:var(--muted);margin-bottom:2px">${czA2.name} → ${czA1.name}</div>${czCatBadge(czC2)}
        </div>
      </div>
    </div>

    <div class="p-3 card-dark2" style="display:${_cfg.compat_mhb?.visible === false ? 'none' : 'block'};border-radius:10px;border-left:3px solid #FF9F1C">
      <div style="font-size:.78rem;color:#FF9F1C;letter-spacing:1px;font-weight:700;text-transform:uppercase;margin-bottom:.5rem">✨ ${escHtml(blockCfg.compat_mhb?.label || 'Burman Astrology — Mahabote')}</div>
      <div class="mb-2 p-2" style="background:var(--bg-dark);border-radius:8px">
        <div style="font-size:.68rem;color:var(--muted);font-weight:700;letter-spacing:.5px;margin-bottom:.35rem">Myanmar 8-Animal</div>
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
          <div style="font-size:.92rem;font-weight:600">${mmA1.icon} ${mmA1.name} &amp; ${mmA2.icon} ${mmA2.name}</div>
          <span style="font-size:.85rem;font-weight:700;color:${zooPL.color}">${zooPL.label}</span>
        </div>
        <div class="progress-custom my-1"><div class="bar-fill" style="width:${zooPct}%;background:${zooPL.color}"></div></div>
        <div style="font-size:.72rem;color:var(--muted)">
          ${mmA1.name} → ${mmA2.name}: <strong style="color:${zooValColor(zooV1)}">${zooVal(zooV1)}</strong>
          &nbsp;·&nbsp;
          ${mmA2.name} → ${mmA1.name}: <strong style="color:${zooValColor(zooV2)}">${zooVal(zooV2)}</strong>
        </div>
      </div>
      <div class="mb-2 p-2" style="background:var(--bg-dark);border-radius:8px">
        <div style="font-size:.68rem;color:var(--muted);font-weight:700;letter-spacing:.5px;margin-bottom:.35rem">Natal Chart — Birth House</div>
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-1">
          <div style="font-size:.9rem;font-weight:600">
            ${mhbH1.symbol} ${p1.mhbName} <span style="color:var(--muted);font-size:.78rem">(${mhbH1.en})</span>
            &amp;
            ${mhbH2.symbol} ${p2.mhbName} <span style="color:var(--muted);font-size:.78rem">(${mhbH2.en})</span>
          </div>
          <span style="font-size:.85rem;font-weight:700;color:${mhbCL.color}">${mhbCL.label}</span>
        </div>
        <div class="progress-custom mb-2"><div class="bar-fill" style="width:${mhbPct}%;background:${mhbCL.color}"></div></div>
        <div style="font-size:.82rem;color:#ccc;line-height:1.6">${mhbDesc}</div>
      </div>
      <div class="d-flex align-items-center justify-content-between pt-1">
        <span style="font-size:.8rem;color:var(--muted)">Burman Reading Combined</span>
        <span style="font-size:1.2rem;font-weight:800;color:${burSt.color}">${burPct}% — ${burSt.label}</span>
      </div>
      <div class="progress-custom mt-1"><div class="bar-fill" style="width:${burPct}%;background:${burSt.color}"></div></div>
    </div>

    ${buildSynastryHTML(name1, name2, wPos1, wPos2, czPct, burPct)}
    ${buildBloodCompatHTML(blood1, blood2, name1, name2)}
    ${buildMBTICompatHTML(mbti1, mbti2, name1, name2)}
    <div style="margin-top:1.25rem;text-align:right">
      <button class="btn btn-outline-secondary btn-sm" onclick="saveCompat()">
        <i class="bi bi-bookmark-plus me-1"></i>Save — ${escHtml(name2)}
      </button>
      <span id="compat-save-msg" style="font-size:.78rem;margin-left:.75rem;display:none"></span>
    </div>`;

  // Store current compat data so saveCompat() can access it
  window._lastCompat = { name2, dob2, time2, city2,
    lat2: lat2 ?? '', lon2: lon2 ?? '', blood2, mbti2 };

  resEl.style.display = 'block';
  resEl.scrollIntoView({ behavior:'smooth', block:'start' });
};

// ── Saved Compatibility Records ────────────────────────────────────

window.saveCompat = async function () {
  const c = window._lastCompat;
  if (!c || !currentUser) return;
  const msg = document.getElementById('compat-save-msg');
  msg.style.display = 'inline'; msg.style.color = 'var(--muted)'; msg.textContent = 'Saving…';
  const res = await api({
    action: 'save_compat',
    p2name: c.name2, p2dob: c.dob2, p2time: c.time2,
    p2city: c.city2, p2lat: c.lat2, p2lon: c.lon2,
    p2blood: c.blood2 || '', p2mbti: c.mbti2 || '',
  });
  if (res.ok) {
    msg.style.color = '#5cb85c'; msg.textContent = '✓ Saved!';
    await loadCompatSaves();
  } else {
    msg.style.color = '#e05252'; msg.textContent = 'Save failed.';
  }
};

window.loadCompatSaves = async function () {
  if (!currentUser) return;
  const block = document.getElementById('compat-saves-block');
  const list  = document.getElementById('compat-saves-list');
  const res   = await api({ action: 'load_compat_saves' });
  if (!res.ok || !res.saves || !res.saves.length) { block.style.display = 'none'; return; }

  block.style.display = 'block';
  list.innerHTML = res.saves.map(s => {
    const dt     = s.saved_at ? s.saved_at.substring(0,10) : '';
    const cityBit  = s.person2_city       ? `<span style="color:var(--muted);font-size:.72rem"><i class="bi bi-geo-alt me-1"></i>${escHtml(s.person2_city)}</span>` : '';
    const timeBit  = s.person2_time       ? `<span style="color:var(--muted);font-size:.72rem"><i class="bi bi-clock me-1"></i>${escHtml(s.person2_time)}</span>` : '';
    const bloodBit = s.person2_blood_type ? `<span style="color:#e91e8c;font-size:.72rem">🩸 ${escHtml(s.person2_blood_type)}</span>` : '';
    const mbtiBit  = s.person2_mbti_type  ? `<span style="color:var(--c-int);font-size:.72rem">🧠 ${escHtml(s.person2_mbti_type)}</span>` : '';
    return `<div class="d-flex align-items-center justify-content-between py-2" style="border-bottom:1px solid var(--border)">
      <div style="cursor:pointer;flex:1" onclick='restoreCompat(${JSON.stringify(s)})'>
        <div style="font-weight:700;font-size:.92rem;color:var(--accent)">${escHtml(s.person2_name)}</div>
        <div class="d-flex gap-3 flex-wrap mt-1">
          <span style="color:var(--muted);font-size:.72rem"><i class="bi bi-calendar3 me-1"></i>${escHtml(s.person2_dob)}</span>
          ${timeBit}${cityBit}${bloodBit}${mbtiBit}
          <span style="color:var(--muted);font-size:.72rem"><i class="bi bi-bookmark me-1"></i>Saved ${escHtml(dt)}</span>
        </div>
      </div>
      <button class="btn btn-sm" style="color:var(--muted);background:none;border:none;font-size:1.1rem;padding:4px 8px"
        onclick="deleteCompatSave(${s.id}, this)" title="Delete"><i class="bi bi-trash3"></i></button>
    </div>`;
  }).join('');
};

window.restoreCompat = function (s) {
  document.getElementById('compat-name').value = s.person2_name;
  document.getElementById('compat-dob').value  = s.person2_dob;
  document.getElementById('compat-time').value = s.person2_time || '';
  document.getElementById('compat-city').value = s.person2_city || '';
  _compatCityLat = s.person2_lat ? parseFloat(s.person2_lat) : null;
  _compatCityLon = s.person2_lon ? parseFloat(s.person2_lon) : null;
  const bloodEl = document.getElementById('compat-blood');
  if (bloodEl) { bloodEl.value = s.person2_blood_type || ''; }
  const mbtiEl = document.getElementById('compat-mbti');
  if (mbtiEl) {
    mbtiEl.value = s.person2_mbti_type || '';
    showMBTIDesc('compat-mbti', 'compat-mbti-desc');
  }
  calcCompat();
};

window.deleteCompatSave = async function (sid, btn) {
  btn.disabled = true;
  const res = await api({ action: 'delete_compat_save', sid });
  if (res.ok) await loadCompatSaves();
  else btn.disabled = false;
};

})();

// ══════════════════════════════════════════════════════════════════
// INIT
// ══════════════════════════════════════════════════════════════════
(async function init(){
  if (currentUser) {
    // Session is active — just refresh UI
    if (currentUser.need_pin) showPinSetup();
    fillProfileFields(currentUser);
    initCompatTab();
    loadCompatSaves();
  } else {
    // New visit: resume a remembered device (random token — never the PIN), else prefill name and DOB.
    try {
      const saved = JSON.parse(localStorage.getItem('bio_saved_login') || 'null');
      if (saved && saved.name) document.getElementById('login-name').value = saved.name;
      if (saved && saved.dob)  document.getElementById('login-dob').value  = saved.dob;
      const token = localStorage.getItem('dv_device');
      if (token) {
        const _uuid2 = (function(){ try { return localStorage.getItem('baydin_uuid') || ''; } catch(_) { return ''; } })();
        const res = await api({ action:'resume', token, uuid:_uuid2 });
        if (res.ok && !res.error) {
          if (enterApp(res)) await loadAppData();
          return;
        }
        localStorage.removeItem('dv_device');   // revoked or expired
      }
    } catch(_){}
  }
  buildMilestoneForm();
  updateProgress();
<?php if ($initUser): ?>
  renderOracleSection();
  applyRequestedView();
<?php endif; ?>
})();
</script>
<script>
if ("serviceWorker" in navigator) { window.addEventListener("load", () => navigator.serviceWorker.register("/sw.js").catch(() => {})); }
</script>
</body>
</html>

