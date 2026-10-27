<?php
// dashboard.php
require_once 'auth.php';
requireAdmin();
$db = kai_db();

// ── AJAX ─────────────────────────────────────────────────────────
if (!empty($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    // CSRF Validation Check
    $csrfToken = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['admin_csrf'] ?? '', $csrfToken)) {
        echo json_encode(['error' => 'Invalid CSRF token. Request blocked.']); 
        exit;
    }

    $action = $_POST['action'] ?? '';

    $parseUserIds = static function ($raw): array {
        $ids = json_decode((string)$raw, true);
        if (!is_array($ids)) return [];
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn($id) => $id > 0)));
        return array_slice($ids, 0, 500);
    };
    $deleteUsers = static function (mysqli $db, array $ids): int {
        if (!$ids) return 0;
        $idList = implode(',', $ids);
        $db->begin_transaction();
        try {
            $db->query("DELETE FROM oracle_live WHERE saved_uid IN ($idList)");
            foreach (['bio_daily_log','bio_milestones','bio_compat_saves'] as $table) {
                if (!$db->query("DELETE FROM `$table` WHERE user_id IN ($idList)")) throw new RuntimeException($db->error);
            }
            if (!$db->query("DELETE FROM bio_users WHERE id IN ($idList)")) throw new RuntimeException($db->error);
            $deleted = $db->affected_rows;
            $db->commit();
            return $deleted;
        } catch (Throwable $e) {
            $db->rollback();
            throw $e;
        }
    };

    if ($action === 'set_level') {
        $uid   = (int)$_POST['uid'];
        $level = in_array($_POST['level'] ?? '', ['silver','gold','platinum']) ? $_POST['level'] : 'silver';
        $s = $db->prepare("UPDATE bio_users SET level=? WHERE id=?");
        $s->bind_param('si', $level, $uid); $s->execute();
        echo json_encode(['ok'=>true]); exit;
    }
    if ($action === 'save_user') {
        $uid   = (int)$_POST['uid'];
        $level = in_array($_POST['level'] ?? '', ['silver','gold','platinum']) ? $_POST['level'] : 'silver';
        $city  = substr(trim($_POST['birth_city'] ?? ''), 0, 100);
        $btime = trim($_POST['birth_time'] ?? '');
        $note  = substr(trim($_POST['admin_note'] ?? ''), 0, 255);
        $goldUntil = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['gold_until'] ?? '') ? $_POST['gold_until'] : null;
        $s = $db->prepare("UPDATE bio_users SET level=?,birth_city=?,birth_time=?,admin_note=?,gold_until=? WHERE id=?");
        $s->bind_param('sssssi', $level, $city, $btime, $note, $goldUntil, $uid);
        $s->execute();
        echo json_encode(['ok'=>true]); exit;
    }
    if ($action === 'reset_pin') {
        // Member forgot their PIN: clear it and sign out their remembered devices.
        // On next sign-in (name + date of birth) they are asked to create a new PIN.
        $uid = (int)$_POST['uid'];
        if ($uid < 1) { echo json_encode(['error'=>'Invalid user']); exit; }
        $s = $db->prepare("UPDATE bio_users SET pin_hash=NULL, pin_fails=0, pin_locked_until=NULL WHERE id=?");
        $s->bind_param('i', $uid); $s->execute();
        $d = $db->prepare("DELETE FROM bio_devices WHERE user_id=?");
        $d->bind_param('i', $uid); $d->execute();
        echo json_encode(['ok'=>true]); exit;
    }
    if ($action === 'delete_user') {
        $uid = (int)$_POST['uid'];
        if ($uid < 1) { echo json_encode(['error'=>'Invalid user']); exit; }
        try {
            $deleted = $deleteUsers($db, [$uid]);
            echo json_encode(['ok'=>true,'deleted'=>$deleted]);
        } catch (Throwable $e) { echo json_encode(['error'=>'Delete failed']); }
        exit;
    }
    if ($action === 'bulk_set_level') {
        $ids = $parseUserIds($_POST['ids'] ?? '[]');
        $level = $_POST['level'] ?? '';
        if (!$ids || !in_array($level, ['silver','gold','platinum'], true)) {
            echo json_encode(['error'=>'Select users and a valid level']); exit;
        }
        $idList = implode(',', $ids);
        $s = $db->prepare("UPDATE bio_users SET level=? WHERE id IN ($idList)");
        $s->bind_param('s', $level);
        if (!$s->execute()) { echo json_encode(['error'=>'Bulk update failed']); exit; }
        echo json_encode(['ok'=>true,'updated'=>$s->affected_rows]); exit;
    }
    if ($action === 'bulk_delete_users') {
        $ids = $parseUserIds($_POST['ids'] ?? '[]');
        if (!$ids) { echo json_encode(['error'=>'No users selected']); exit; }
        try {
            $deleted = $deleteUsers($db, $ids);
            echo json_encode(['ok'=>true,'deleted'=>$deleted]);
        } catch (Throwable $e) { echo json_encode(['error'=>'Bulk delete failed']); }
        exit;
    }
    if ($action === 'save_blocks') {
        $blocks = json_decode($_POST['blocks'] ?? '[]', true);
        if (!is_array($blocks) || !$blocks) { echo json_encode(['error'=>'No block configuration received']); exit; }
        $known = [];
        $knownRes = $db->query("SELECT block_key FROM bio_block_config");
        if (!$knownRes) { echo json_encode(['error'=>'Could not load block configuration']); exit; }
        while ($row = $knownRes->fetch_assoc()) $known[$row['block_key']] = true;
        $seen = [];
        $db->begin_transaction();
        try {
            $s = $db->prepare("UPDATE bio_block_config SET sort_order=?,silver=?,gold=?,platinum=?,block_label=? WHERE block_key=?");
            if (!$s) throw new RuntimeException($db->error);
            foreach ($blocks as $b) {
                if (!is_array($b)) throw new RuntimeException('Invalid block entry');
                $key = preg_replace('/[^a-z_]/', '', (string)($b['key'] ?? ''));
                if (!$key || !isset($known[$key]) || isset($seen[$key])) throw new RuntimeException('Unknown or duplicate block');
                $seen[$key] = true;
                $ord   = max(0, min(999, (int)($b['order'] ?? 0)));
                $sv    = !empty($b['silver']) ? 1 : 0;
                $gd    = !empty($b['gold']) ? 1 : 0;
                $pl    = !empty($b['platinum']) ? 1 : 0;
                $label = substr(trim((string)($b['label'] ?? '')), 0, 120);
                if ($label === '') throw new RuntimeException('Block labels cannot be empty');
                $s->bind_param('iiiiss', $ord, $sv, $gd, $pl, $label, $key);
                if (!$s->execute()) throw new RuntimeException($s->error);
            }
            $db->commit();
            echo json_encode(['ok'=>true,'updated'=>count($seen)]);
        } catch (Throwable $e) {
            $db->rollback();
            echo json_encode(['error'=>'Block configuration was not saved: '.$e->getMessage()]);
        }
        exit;
    }
    if ($action === 'save_block_content') {
        $key     = preg_replace('/[^a-z_]/', '', $_POST['key'] ?? '');
        $content = $_POST['content'] ?? '';
        if (!$key) { echo json_encode(['error'=>'Missing key']); exit; }
        $check = $db->prepare("SELECT 1 FROM bio_block_config WHERE block_key=?");
        if (!$check) { echo json_encode(['error'=>'Could not validate block']); exit; }
        $check->bind_param('s', $key); $check->execute();
        if (!$check->get_result()->fetch_row()) { echo json_encode(['error'=>'Unknown block']); exit; }
        $s = $db->prepare("UPDATE bio_block_config SET content=? WHERE block_key=?");
        if (!$s) { echo json_encode(['error'=>'Prepare failed: '.$db->error]); exit; }
        $s->bind_param('ss', $content, $key);
        if (!$s->execute()) { echo json_encode(['error'=>'Execute failed: '.$s->error]); exit; }
        echo json_encode(['ok'=>true]); exit;
    }
    if ($action === 'get_all_block_contents') {
        $res = $db->query("SELECT block_key, content FROM bio_block_config");
        if (!$res) { echo json_encode(['error'=>'Could not load block content']); exit; }
        $blocks = [];
        while ($r = $res->fetch_assoc()) $blocks[] = $r;
        echo json_encode(['ok'=>true,'blocks'=>$blocks]); exit;
    }
    if ($action === 'get_rank_rules') {
        $result = $db->query("SELECT gold_threshold, platinum_threshold FROM bio_settings WHERE id=1");
        $row = $result ? $result->fetch_assoc() : [];
        echo json_encode(['ok'=>true,
            'gold_threshold'     => (int)($row['gold_threshold']     ?? 100),
            'platinum_threshold' => (int)($row['platinum_threshold'] ?? 500)]);
        exit;
    }
    if ($action === 'save_rank_rules') {
        $gold = max(0, (int)($_POST['gold_threshold'] ?? 100));
        $plat = max(0, (int)($_POST['platinum_threshold'] ?? 500));
        if ($plat <= $gold) { echo json_encode(['error'=>'Platinum threshold must be greater than Gold']); exit; }
        $s = $db->prepare("INSERT INTO bio_settings (id,gold_threshold,platinum_threshold) VALUES (1,?,?) ON DUPLICATE KEY UPDATE gold_threshold=VALUES(gold_threshold),platinum_threshold=VALUES(platinum_threshold)");
        $s->bind_param('ii', $gold, $plat);
        if (!$s->execute()) { echo json_encode(['error'=>'Could not save rank rules']); exit; }
        echo json_encode(['ok'=>true,'gold_threshold'=>$gold,'platinum_threshold'=>$plat]); exit;
    }
    if ($action === 'get_promo') {
        $row = $db->query("SELECT promo_enabled,promo_type,promo_duration_days,promo_end_date,promo_start_points FROM bio_settings WHERE id=1")->fetch_assoc();
        echo json_encode(['ok'=>true,
            'promo_enabled'       => (bool)($row['promo_enabled']      ?? 0),
            'promo_type'          =>       ($row['promo_type']          ?? 'duration'),
            'promo_duration_days' => (int) ($row['promo_duration_days'] ?? 90),
            'promo_end_date'      =>       ($row['promo_end_date']      ?? ''),
            'promo_start_points'  => (int) ($row['promo_start_points']  ?? 100)]); exit;
    }
    if ($action === 'save_promo') {
        $enabled  = empty($_POST['promo_enabled']) ? 0 : 1;
        $type     = in_array($_POST['promo_type'] ?? '', ['duration','fixed_date']) ? $_POST['promo_type'] : 'duration';
        $days     = max(1, (int)($_POST['promo_duration_days'] ?? 90));
        $endDate  = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['promo_end_date'] ?? '') ? $_POST['promo_end_date'] : null;
        $startPts = max(0, (int)($_POST['promo_start_points'] ?? 100));
        if ($enabled && $type === 'fixed_date' && !$endDate) {
            echo json_encode(['error'=>'Choose an end date for a fixed-date promotion']); exit;
        }
        $s = $db->prepare("UPDATE bio_settings SET promo_enabled=?,promo_type=?,promo_duration_days=?,promo_end_date=?,promo_start_points=? WHERE id=1");
        $s->bind_param('isisi', $enabled,$type,$days,$endDate,$startPts);
        if (!$s->execute()) { echo json_encode(['error'=>'Could not save promotion']); exit; }
        echo json_encode(['ok'=>true]); exit;
    }
    if ($action === 'get_point_rules') {
        $res = $db->query("SELECT action_key,label,emoji,points,enabled FROM bio_point_rules ORDER BY id");
        $rules = []; while ($r = $res->fetch_assoc()) $rules[] = $r;
        echo json_encode(['ok'=>true,'rules'=>$rules]); exit;
    }
    if ($action === 'save_point_rules') {
        $rules = json_decode($_POST['rules'] ?? '[]', true);
        if (!is_array($rules)) { echo json_encode(['error'=>'Invalid']); exit; }
        foreach ($rules as $r) {
            $key = preg_replace('/[^a-z_]/','',$r['action_key'] ?? '');
            $pts = min(100, max(0,(int)($r['points'] ?? 0)));
            $on  = isset($r['enabled']) ? (int)(bool)$r['enabled'] : 1;
            $s = $db->prepare("UPDATE bio_point_rules SET points=?,enabled=? WHERE action_key=?");
            $s->bind_param('iis',$pts,$on,$key); $s->execute();
        }
        echo json_encode(['ok'=>true]); exit;
    }
    if (in_array($action, ['recalc_rank','recalc_all_ranks','bulk_recalc_rank'], true)) {
        $row = $db->query("SELECT gold_threshold, platinum_threshold FROM bio_settings WHERE id=1")->fetch_assoc();
        $gold = (int)($row['gold_threshold'] ?? 100);
        $plat = (int)($row['platinum_threshold'] ?? 500);
        $rankNum = ['silver'=>0,'gold'=>1,'platinum'=>2];

        if ($action === 'recalc_rank') {
            $where2 = 'WHERE id=' . (int)($_POST['uid'] ?? 0);
        } elseif ($action === 'bulk_recalc_rank') {
            $ids = $parseUserIds($_POST['ids'] ?? '[]');
            if (!$ids) { echo json_encode(['error'=>'No users selected']); exit; }
            $where2 = 'WHERE id IN (' . implode(',', $ids) . ')';
        } else {
            $where2 = '';
        }
        $res = $db->query("SELECT id,level,points FROM bio_users $where2");
        $changed = 0;
        while ($u = $res->fetch_assoc()) {
            $pts = (int)$u['points'];
			$earned = $pts >= $plat ? 'platinum' : ($pts >= $gold ? 'gold' : 'silver');
            if ($rankNum[$earned] > ($rankNum[$u['level']] ?? 0)) {
                $s = $db->prepare("UPDATE bio_users SET level=? WHERE id=?");
                $s->bind_param('si', $earned, $u['id']); $s->execute();
                $changed++;
            }
        }
        echo json_encode(['ok'=>true,'changed'=>$changed]); exit;
    }
    if ($action === 'get_user') {
        $uid = (int)$_POST['uid'];
        $s = $db->prepare("SELECT id,name,dob,birth_time,birth_city,level,points,admin_note,created_at,last_login,gold_until FROM bio_users WHERE id=?");
        $s->bind_param('i', $uid); $s->execute();
        $u = $s->get_result()->fetch_assoc();
        if (!$u) { echo json_encode(['error'=>'User not found']); exit; }
        $cs = $db->prepare("SELECT id,person2_name,person2_dob,person2_city,saved_at FROM bio_compat_saves WHERE user_id=? ORDER BY saved_at DESC LIMIT 20");
        $cs->bind_param('i', $uid); $cs->execute();
        $u['compats'] = $cs->get_result()->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['ok'=>true,'user'=>$u]); exit;
    }
    
    // ── Database maintenance using secured ROOT variables ──────────────────────
    if (in_array($action, ['db_optimize','db_clear_sessions','db_backup','db_list_backups','db_delete_backup'])) {
        $rootDb  = new mysqli(DB_HOST, DB_ROOT_USER, DB_ROOT_PASS, DB_NAME);
        $backupDir = dirname(__DIR__, 2) . '/db_backups';

        if ($action === 'db_optimize') {
            $results = [];
            $tables = [];
            $tr = $rootDb->query("SHOW TABLES");
            while ($row = $tr->fetch_row()) $tables[] = $row[0];
            foreach ($tables as $t) {
                $r = $rootDb->query("OPTIMIZE TABLE `$t`");
                $status = 'ok';
                if ($r) { $row = $r->fetch_assoc(); $status = $row['Msg_text'] ?? 'ok'; }
                else { $status = $rootDb->error; }
                $results[] = ['table'=>$t, 'status'=>$status];
            }
            echo json_encode(['ok'=>true,'results'=>$results]); exit;
        }

        if ($action === 'db_clear_sessions') {
            $path = session_save_path();
            if (!$path || !is_dir($path)) { echo json_encode(['error'=>'Session path not found']); exit; }
            $cutoff = time() - 86400; 
            $deleted = 0;
            foreach (glob($path . '/sess_*') as $f) {
                if (is_file($f) && filemtime($f) < $cutoff) {
                    if (@unlink($f)) $deleted++;
                }
            }
            echo json_encode(['ok'=>true,'deleted'=>$deleted]); exit;
        }

        if ($action === 'db_backup') {
            if (!is_dir($backupDir)) mkdir($backupDir, 0750, true);
            $fname = 'southcom_' . date('Y-m-d_His') . '.sql';
            $fpath = $backupDir . '/' . $fname;
            $cmd = 'mysqldump -u ' . escapeshellarg(DB_ROOT_USER) . ' -p' . escapeshellarg(DB_ROOT_PASS) . ' ' . escapeshellarg(DB_NAME)
                 . ' ' . escapeshellarg('--single-transaction') . ' > ' . escapeshellarg($fpath) . ' 2>&1';
            exec($cmd, $out, $rc);
            if ($rc !== 0 || !file_exists($fpath) || filesize($fpath) === 0) {
                @unlink($fpath);
                echo json_encode(['error'=>'Backup failed: ' . implode(' ', $out)]); exit;
            }
            echo json_encode(['ok'=>true,'file'=>$fname,'size'=>filesize($fpath)]); exit;
        }

        if ($action === 'db_list_backups') {
            $files = [];
            if (is_dir($backupDir)) {
                foreach (glob($backupDir . '/*.sql') as $f) {
                    $files[] = ['name'=>basename($f), 'size'=>filesize($f), 'mtime'=>filemtime($f)];
                }
            }
            usort($files, fn($a,$b) => $b['mtime'] - $a['mtime']);
            echo json_encode(['ok'=>true,'files'=>$files]); exit;
        }

        if ($action === 'db_delete_backup') {
            $name = basename($_POST['name'] ?? '');
            if (!preg_match('/^southcom_[0-9_\-]+\.sql$/', $name)) { echo json_encode(['error'=>'Invalid filename']); exit; }
            $fpath = $backupDir . '/' . $name;
            if (file_exists($fpath)) unlink($fpath);
            echo json_encode(['ok'=>true]); exit;
        }
    }

    echo json_encode(['error'=>'Unknown action']); exit;
}

// ── Database backup download ──
if (isset($_GET['download_backup'])) {
    $name = basename($_GET['download_backup']);
    if (!preg_match('/^southcom_[0-9_\-]+\.sql$/', $name)) { http_response_code(400); exit('Invalid filename'); }
    $fpath = dirname(__DIR__, 2) . '/db_backups/' . $name;
    if (!file_exists($fpath)) { http_response_code(404); exit('Not found'); }
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: ' . filesize($fpath));
    readfile($fpath);
    exit;
}

// ── CSV export ───────────────────────────────────────────────────
if (isset($_GET['export_csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="baydin_users_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','Name','DOB','Birth City','Level','Log Count','Compat Saves','Last Login','Registered','Admin Note']);
    $res = $db->query("SELECT u.id,u.name,u.dob,u.birth_city,u.level,u.last_login,u.created_at,u.admin_note,
        (SELECT COUNT(*) FROM bio_daily_log l WHERE l.user_id=u.id) lc,
        (SELECT COUNT(*) FROM bio_compat_saves c WHERE c.user_id=u.id) cc
        FROM bio_users u ORDER BY u.id DESC");
    while ($r = $res->fetch_assoc())
        fputcsv($out, [$r['id'],$r['name'],$r['dob'],$r['birth_city']??'',$r['level'],
            $r['lc'],$r['cc'],$r['last_login']??'',$r['created_at']??'',$r['admin_note']??'']);
    fclose($out); exit;
}

// ── Logout ───────────────────────────────────────────────────────
if (isset($_GET['logout'])) { session_destroy(); header('Location: index.php'); exit; }

// ── Stats ────────────────────────────────────────────────────────
$total_users  = (int)$db->query("SELECT COUNT(*) FROM bio_users")->fetch_row()[0];
$total_silver = (int)$db->query("SELECT COUNT(*) FROM bio_users WHERE level='silver'")->fetch_row()[0];
$total_gold   = (int)$db->query("SELECT COUNT(*) FROM bio_users WHERE level='gold'")->fetch_row()[0];
$total_plat   = (int)$db->query("SELECT COUNT(*) FROM bio_users WHERE level='platinum'")->fetch_row()[0];
$active_today = (int)$db->query("SELECT COUNT(DISTINCT user_id) FROM bio_daily_log WHERE log_date=CURDATE()")->fetch_row()[0];
$active_week  = (int)$db->query("SELECT COUNT(DISTINCT user_id) FROM bio_daily_log WHERE log_date>=DATE_SUB(CURDATE(),INTERVAL 7 DAY)")->fetch_row()[0];
$active_month = (int)$db->query("SELECT COUNT(DISTINCT user_id) FROM bio_daily_log WHERE log_date>=DATE_SUB(CURDATE(),INTERVAL 30 DAY)")->fetch_row()[0];
$new_month    = (int)$db->query("SELECT COUNT(*) FROM bio_users WHERE created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)")->fetch_row()[0];
$total_compats= (int)$db->query("SELECT COUNT(*) FROM bio_compat_saves")->fetch_row()[0];
$loggedin_7d  = (int)$db->query("SELECT COUNT(*) FROM bio_users WHERE last_login>=DATE_SUB(NOW(),INTERVAL 7 DAY)")->fetch_row()[0];
$oracle_readings = (int)$db->query("SELECT COUNT(*) FROM bio_oracle_readings")->fetch_row()[0];
$oracle_users = (int)$db->query("SELECT COUNT(DISTINCT user_id) FROM bio_oracle_readings")->fetch_row()[0];
$oracle_avg = (float)($db->query("SELECT ROUND(AVG(score),1) FROM bio_oracle_readings")->fetch_row()[0] ?? 0);

// Chart data
function fillDates(array $rows, int $days): array {
    $map = [];
    foreach ($rows as $r) $map[$r['d']] = (int)$r['n'];
    $labels = []; $data = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $labels[] = date('M j', strtotime($d));
        $data[]   = $map[$d] ?? 0;
    }
    return [$labels, $data];
}
$reg_rows = $db->query("SELECT DATE(created_at) d,COUNT(*) n FROM bio_users WHERE created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) GROUP BY d");
[$reg_labels,$reg_data] = fillDates($reg_rows->fetch_all(MYSQLI_ASSOC), 30);

$act_rows = $db->query("SELECT log_date d,COUNT(DISTINCT user_id) n FROM bio_daily_log WHERE log_date>=DATE_SUB(CURDATE(),INTERVAL 30 DAY) GROUP BY d");
[$act_labels,$act_data] = fillDates($act_rows->fetch_all(MYSQLI_ASSOC), 30);

$mon_rows = $db->query("SELECT DATE_FORMAT(created_at,'%Y-%m') d,COUNT(*) n FROM bio_users WHERE created_at>=DATE_SUB(NOW(),INTERVAL 12 MONTH) GROUP BY d ORDER BY d");
$mon_map = []; while ($r = $mon_rows->fetch_assoc()) $mon_map[$r['d']] = (int)$r['n'];
$mon_labels = []; $mon_data = [];
for ($i = 11; $i >= 0; $i--) { $m = date('Y-m', strtotime("-$i months")); $mon_labels[] = date('M Y', strtotime($m.'-01')); $mon_data[] = $mon_map[$m] ?? 0; }

$login_rows = $db->query("SELECT DATE(last_login) d,COUNT(*) n FROM bio_users WHERE last_login>=DATE_SUB(NOW(),INTERVAL 30 DAY) GROUP BY d");
[$login_labels,$login_data] = fillDates($login_rows->fetch_all(MYSQLI_ASSOC), 30);

$pulseRows = $db->query("SELECT DATE(asked_at) d,ROUND(AVG(score),2) avg_score,COUNT(*) readings,COUNT(DISTINCT user_id) users
    FROM bio_oracle_readings WHERE asked_at>=DATE_SUB(CURDATE(),INTERVAL 29 DAY) GROUP BY DATE(asked_at) ORDER BY d");
$pulseMap = [];
if ($pulseRows) while ($r=$pulseRows->fetch_assoc()) $pulseMap[$r['d']] = $r;
$pulse_labels=[]; $pulse_scores=[]; $pulse_counts=[]; $pulse_users=[];
for ($i=29;$i>=0;$i--) {
    $d=date('Y-m-d',strtotime("-$i days")); $pulse_labels[]=date('M j',strtotime($d));
    $pulse_scores[]=isset($pulseMap[$d])?(float)$pulseMap[$d]['avg_score']:null;
    $pulse_counts[]=isset($pulseMap[$d])?(int)$pulseMap[$d]['readings']:0;
    $pulse_users[]=isset($pulseMap[$d])?(int)$pulseMap[$d]['users']:0;
}

// ── Users ────────────────────────────────────────────────────────
$search = trim($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$per    = 25; $offset = ($page - 1) * $per;
$sortColumns = [
    'id'=>'u.id', 'name'=>'u.name', 'dob'=>'u.dob', 'city'=>'u.birth_city',
    'level'=>'u.level', 'points'=>'u.points', 'logs'=>'log_count',
    'compats'=>'compat_count', 'last_login'=>'u.last_login',
    'last_log'=>'last_log', 'joined'=>'u.created_at',
];
$sort = array_key_exists($_GET['sort'] ?? '', $sortColumns) ? $_GET['sort'] : 'id';
$dir  = strtolower($_GET['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
$orderSql = $sortColumns[$sort] . ' ' . strtoupper($dir) . ', u.id DESC';
$where  = $search ? "WHERE u.name LIKE '%" . $db->real_escape_string($search) . "%'" : '';
$total_filtered = (int)$db->query("SELECT COUNT(*) FROM bio_users u $where")->fetch_row()[0];
$pages = max(1, (int)ceil($total_filtered / $per));

$users_res = $db->query("
    SELECT u.id,u.name,u.dob,u.birth_city,u.level,u.points,u.admin_note,u.created_at,u.last_login,
           (SELECT MAX(log_date) FROM bio_daily_log l WHERE l.user_id=u.id) last_log,
           (SELECT COUNT(*) FROM bio_daily_log l WHERE l.user_id=u.id) log_count,
           (SELECT COUNT(*) FROM bio_compat_saves c WHERE c.user_id=u.id) compat_count
    FROM bio_users u $where ORDER BY $orderSql LIMIT $per OFFSET $offset");
$users = $users_res ? $users_res->fetch_all(MYSQLI_ASSOC) : [];

// ── Blocks ───────────────────────────────────────────────────────
$blocks_res = $db->query("SELECT * FROM bio_block_config WHERE block_key <> 'idx_form' ORDER BY page,sort_order");
$blocks_by_page = ['biorhythm' => [], 'index' => []];
while ($r = $blocks_res->fetch_assoc()) $blocks_by_page[$r['page']][] = $r;

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES); }
$sortLink = static function (string $key, string $label) use ($sort, $dir, $search): string {
    $active = $sort === $key;
    $nextDir = $active && $dir === 'asc' ? 'desc' : 'asc';
    $icon = $active ? ($dir === 'asc' ? '▲' : '▼') : '↕';
    $url = '?' . http_build_query(['tab'=>'users','q'=>$search,'sort'=>$key,'dir'=>$nextDir]);
    return '<a class="sort-link' . ($active ? ' active' : '') . '" href="' . e($url) . '">' . e($label) . ' <span>' . $icon . '</span></a>';
};
$active_tab = $_GET['tab'] ?? 'dashboard';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>DharmaVera Admin</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<link href="quill.snow.css" rel="stylesheet">
<script src="quill.js"></script>
<style>
:root{
    --bg:#0a0b10;
    --card:rgba(22, 27, 34, 0.65);
    --card2:rgba(28, 33, 40, 0.85);
    --border:rgba(102, 252, 241, 0.15);
    --accent:#66fcf1;
    --accent2:#b388ff;
    --text:#e6edf3;
    --muted:#8b949e;
    --green:#3fb950;
    --yellow:#d29922;
    --red:#f85149;
    --purple:#bc8cff
}
*{box-sizing:border-box}
body{background:var(--bg);color:var(--text);font-family:'Segoe UI',system-ui,sans-serif;margin:0;font-size:.9rem}
a{color:var(--accent);text-decoration:none}
.sidebar{width:220px;min-height:100vh;background:var(--card); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border-right:1px solid var(--border);position:fixed;top:0;left:0;bottom:0;display:flex;flex-direction:column;z-index:100}
.sidebar-brand{padding:1.25rem 1rem .75rem;font-weight:700;font-size:1rem;border-bottom:1px solid var(--border)}
.sidebar-brand span{color:var(--accent)}
.nav-item{padding:.5rem .85rem;font-size:.875rem;cursor:pointer;border-radius:6px;margin:.15rem .5rem;display:flex;align-items:center;gap:.6rem;color:var(--muted);transition:background .25s,color .25s}
.nav-item:hover{background:var(--card2);color:var(--text)}
.nav-item.active{background:rgba(102,252,241,.15);color:var(--accent);font-weight:600}
.sidebar-bottom{margin-top:auto;padding:.75rem .5rem;border-top:1px solid var(--border)}
.main{margin-left:220px;padding:1.5rem;min-height:100vh}
.page-title{font-size:1.3rem;font-weight:700;margin-bottom:1.25rem; letter-spacing: 1px;}
.stat-card{background:var(--card); backdrop-filter: blur(12px); border:1px solid var(--border);border-radius:12px;padding:1rem 1.25rem; box-shadow: 0 4px 16px rgba(0,0,0,0.2); transition: transform 0.25s ease, box-shadow 0.25s ease;}
.stat-card:hover{transform: translateY(-2px); box-shadow: 0 8px 24px rgba(102,252,241,0.1); border-color: rgba(102,252,241,0.3);}
.stat-val{font-size:2rem;font-weight:800;line-height:1.1}
.stat-label{font-size:.76rem;color:var(--muted);margin-top:2px; text-transform: uppercase; letter-spacing: 0.5px;}
.chart-card{background:var(--card); backdrop-filter: blur(12px); border:1px solid var(--border);border-radius:12px;padding:1.25rem; box-shadow: 0 4px 16px rgba(0,0,0,0.2);}
.chart-title{font-size:.75rem;font-weight:700;color:var(--accent2);margin-bottom:.85rem;letter-spacing:1px;text-transform:uppercase}
.data-table{width:100%;border-collapse:collapse;font-size:.83rem}
.data-table th{background:rgba(0,0,0,0.2);color:var(--accent2);font-weight:700;padding:.55rem .7rem;font-size:.72rem;letter-spacing:1px;text-transform:uppercase;border-bottom:1px solid var(--border);white-space:nowrap}
.data-table td{padding:.5rem .7rem;border-bottom:1px solid var(--border);vertical-align:middle}
.data-table tr:hover td{background:rgba(255,255,255,0.03)}
.data-table tr.is-selected td{background:rgba(102,252,241,.07)}
.sort-link{color:var(--accent2);display:inline-flex;align-items:center;gap:.3rem}
.sort-link span{font-size:.65rem;color:var(--muted)}
.sort-link.active,.sort-link:hover{color:var(--accent)}
.bulk-bar{display:none;align-items:center;gap:.55rem;flex-wrap:wrap;background:rgba(102,252,241,.07);border:1px solid var(--border);border-radius:9px;padding:.6rem .75rem;margin-bottom:.75rem}
.bulk-bar.active{display:flex}
.user-select{width:1rem;height:1rem;accent-color:var(--accent);cursor:pointer}
.table-wrap{background:var(--card); backdrop-filter: blur(12px); border:1px solid var(--border);border-radius:12px;overflow:auto; box-shadow: 0 4px 16px rgba(0,0,0,0.2);}
.form-control,.form-select{background:rgba(0,0,0,0.2);border:1px solid var(--border);color:var(--text);font-size:.84rem;padding:.32rem .6rem;border-radius:6px; backdrop-filter: blur(4px);}
.form-control:focus,.form-select:focus{background:rgba(0,0,0,0.4);border-color:var(--accent);color:var(--text);box-shadow:0 0 0 3px rgba(102,252,241,.12);outline:none}
.form-select option{background:#161b22}
.tab-page{display:none}.tab-page.active{display:block}
.lvl-badge{padding:1px 9px;border-radius:10px;font-size:.71rem;font-weight:700;white-space:nowrap}
.lvl-silver{background:#1a2a1a;color:#aaa;border:1px solid #aaa3}
.lvl-gold{background:#8a6a00;color:#FFD700;border:1px solid #FFD70055}
.lvl-platinum{background:#1a1a2e;color:#a8d8ff;border:1px solid #a8d8ff55}
.pg-btn{background:rgba(0,0,0,0.2);border:1px solid var(--border);color:var(--text);padding:2px 10px;border-radius:6px;font-size:.8rem;cursor:pointer}
.pg-btn.active{background:var(--accent);color:#000;border-color:var(--accent);font-weight:700}
.block-row{background:rgba(0,0,0,0.1);border:1px solid var(--border);border-radius:8px;padding:.65rem .9rem;display:flex;align-items:center;gap:.85rem;cursor:grab}
.block-row:active{cursor:grabbing}
.block-row.sortable-ghost{opacity:.4;background:rgba(255,255,255,0.05)}
.drag-handle{color:var(--accent2);font-size:1rem}
.modal-backdrop-custom{position:fixed;inset:0;background:rgba(0,0,0,.7); backdrop-filter: blur(4px); z-index:1050;display:flex;align-items:center;justify-content:center}
.modal-box{background:var(--card); backdrop-filter: blur(16px); border:1px solid var(--border);border-radius:14px;padding:1.5rem;width:100%;max-width:520px;max-height:90vh;overflow-y:auto; box-shadow: 0 16px 40px rgba(0,0,0,0.5);}
.modal-title{font-size:1.05rem;font-weight:700;margin-bottom:1.1rem; letter-spacing: 0.5px;}
.label-sm{font-size:.75rem;color:var(--accent2);font-weight:700; letter-spacing: 1px; text-transform:uppercase; margin-bottom:3px}
.ql-editor{color:#1a1a1a !important}
.ql-editor.ql-blank::before{color:#888 !important}
@media(max-width:768px){.sidebar{display:none}.main{margin-left:0}}
</style>
</head>
<body>

<div class="sidebar">
  <div class="sidebar-brand">🔮 <span>DharmaVera</span> Admin</div>
  <div style="padding:.5rem 0">
    <div class="nav-item" id="nav-dashboard" onclick="showTab('dashboard')"><i class="bi bi-speedometer2"></i>Dashboard</div>
    <div class="nav-item" id="nav-users"     onclick="showTab('users')"><i class="bi bi-people"></i>Users</div>
    <div class="nav-item" id="nav-blocks"    onclick="showTab('blocks')"><i class="bi bi-grid-3x3-gap"></i>Block Config</div>
    <div class="nav-item" id="nav-ranks"     onclick="showTab('ranks')"><i class="bi bi-award"></i>Rank Rules</div>
    <div class="nav-item" id="nav-database"  onclick="showTab('database')"><i class="bi bi-hdd-stack"></i>Database</div>
  </div>
  <div class="sidebar-bottom">
    <div style="font-size:.75rem;color:var(--muted);padding:.25rem .5rem;margin-bottom:.25rem"><i class="bi bi-person-circle me-1"></i><?= e(KAI_USER) ?></div>
    <div class="nav-item" onclick="location.href='?logout=1'" style="color:var(--red)"><i class="bi bi-box-arrow-left"></i>Logout</div>
  </div>
</div>

<div class="main">

<!-- ══ DASHBOARD ══ -->
<div id="tab-dashboard" class="tab-page">
  <div class="page-title"><i class="bi bi-speedometer2 me-2" style="color:var(--accent)"></i>Dashboard</div>

  <div class="row g-3 mb-4">
    <?php foreach ([
      ['Total Users',      $total_users,  'var(--accent)',  'bi-people'],
      ['New This Month',   $new_month,    'var(--green)',   'bi-person-plus'],
      ['Active This Week', $active_week,  'var(--yellow)',  'bi-activity'],
      ['Logged In 7d',     $loggedin_7d,  'var(--purple)',  'bi-box-arrow-in-right'],
      ['Compat Saves',     $total_compats,'#ff7b72',        'bi-hearts'],
      ['Active Today',     $active_today, 'var(--green)',   'bi-circle-fill'],
      ['Oracle Questions', $oracle_readings,'#ff4d5f',      'bi-heart-pulse-fill'],
    ] as [$lbl,$val,$color,$icon]): ?>
    <div class="col-6 col-md-4 col-lg-2">
      <div class="stat-card" style="border-top:3px solid <?= $color ?>">
        <div class="stat-val" style="color:<?= $color ?>"><?= number_format($val) ?></div>
        <div class="stat-label"><i class="bi <?= $icon ?> me-1"></i><?= $lbl ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-4"><div class="stat-card text-center"><div class="stat-val" style="color:#aaa"><?= $total_silver ?></div><div class="stat-label">🆓 Silver</div></div></div>
    <div class="col-4"><div class="stat-card text-center"><div class="stat-val" style="color:#FFD700"><?= $total_gold ?></div><div class="stat-label">⭐ Gold</div></div></div>
    <div class="col-4"><div class="stat-card text-center"><div class="stat-val" style="color:#a8d8ff"><?= $total_plat ?></div><div class="stat-label">💎 Platinum</div></div></div>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-md-6">
      <div class="chart-card">
        <div class="chart-title">New Registrations — Last 30 Days</div>
        <div style="position:relative;height:180px"><canvas id="chart-reg"></canvas></div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="chart-card">
        <div class="chart-title">Daily Active Users (Log entries) — Last 30 Days</div>
        <div style="position:relative;height:180px"><canvas id="chart-act"></canvas></div>
      </div>
    </div>
  </div>
  <div class="row g-3">
    <div class="col-md-6">
      <div class="chart-card">
        <div class="chart-title">Login Activity — Last 30 Days</div>
        <div style="position:relative;height:180px"><canvas id="chart-login"></canvas></div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="chart-card">
        <div class="chart-title">Monthly Signups — Last 12 Months</div>
        <div style="position:relative;height:180px"><canvas id="chart-mon"></canvas></div>
      </div>
    </div>
  </div>
  <div class="row g-3 mt-1">
    <div class="col-12">
      <div class="chart-card" style="border-left:3px solid #ff4d5f">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-2">
          <div>
            <div class="chart-title mb-1">All Users’ Oracle Pulse — Daily Average, Last 30 Days</div>
            <div style="font-size:.73rem;color:var(--muted)">Anonymous aggregate of saved member readings. Y axis is fixed from 1 to 10; missing days remain blank.</div>
          </div>
          <div class="d-flex gap-3 text-end">
            <div><div style="font-size:1.2rem;font-weight:800;color:#ff4d5f"><?= $oracle_avg ? number_format($oracle_avg,1) : '—' ?></div><div class="stat-label">All-time avg</div></div>
            <div><div style="font-size:1.2rem;font-weight:800;color:var(--accent)"><?= number_format($oracle_users) ?></div><div class="stat-label">Users recorded</div></div>
          </div>
        </div>
        <div style="position:relative;height:260px"><canvas id="chart-oracle-pulse"></canvas></div>
      </div>
    </div>
  </div>
</div>

<!-- ══ USERS ══ -->
<div id="tab-users" class="tab-page">
  <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div class="page-title mb-0"><i class="bi bi-people me-2" style="color:var(--accent)"></i>Users <span style="font-size:.85rem;color:var(--muted);font-weight:400">(<?= number_format($total_filtered) ?>)</span></div>
    <div class="d-flex gap-2 flex-wrap align-items-center">
      <form method="GET" class="d-flex gap-2" onsubmit="this.tab.value='users'">
        <input type="hidden" name="tab" value="users">
        <input type="hidden" name="sort" value="<?= e($sort) ?>">
        <input type="hidden" name="dir" value="<?= e($dir) ?>">
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search name…" class="form-control" style="width:180px">
        <button class="btn btn-sm btn-outline-secondary px-3">Go</button>
        <?php if($search):?><a href="?tab=users" class="btn btn-sm btn-outline-danger">✕</a><?php endif;?>
      </form>
      <a href="?export_csv=1" class="btn btn-sm btn-outline-success px-3"><i class="bi bi-download me-1"></i>CSV</a>
    </div>
  </div>

  <div id="bulk-bar" class="bulk-bar">
    <strong id="selected-count" style="color:var(--accent)">0 selected</strong>
    <select id="bulk-level" class="form-select" style="width:145px">
      <option value="">Choose level…</option>
      <option value="silver">🆓 Silver</option>
      <option value="gold">⭐ Gold</option>
      <option value="platinum">💎 Platinum</option>
    </select>
    <button class="btn btn-sm btn-outline-primary" onclick="bulkSetLevel()"><i class="bi bi-pencil-square me-1"></i>Update Level</button>
    <button class="btn btn-sm btn-outline-warning" onclick="bulkRecalculate()"><i class="bi bi-arrow-repeat me-1"></i>Recalculate Rank</button>
    <button class="btn btn-sm btn-outline-danger" onclick="bulkDeleteUsers()"><i class="bi bi-trash3 me-1"></i>Delete Selected</button>
    <span id="bulk-msg" style="font-size:.78rem"></span>
  </div>

  <div class="table-wrap mb-3">
    <table class="data-table">
      <thead><tr>
        <th title="Select all users on this page"><input type="checkbox" id="select-page" class="user-select" onchange="togglePageSelection(this.checked)" aria-label="Select all users on this page"></th>
        <th><?= $sortLink('id','#') ?></th><th><?= $sortLink('name','Name') ?></th><th><?= $sortLink('dob','DOB') ?></th><th><?= $sortLink('city','City') ?></th><th><?= $sortLink('level','Level') ?></th><th><?= $sortLink('points','dUSD') ?></th>
        <th><?= $sortLink('logs','Logs') ?></th><th><?= $sortLink('compats','Compats') ?></th><th><?= $sortLink('last_login','Last Login') ?></th><th><?= $sortLink('last_log','Last Log') ?></th><th><?= $sortLink('joined','Joined') ?></th><th>Actions</th>
      </tr></thead>
      <tbody>
      <?php foreach ($users as $u): ?>
      <tr id="urow-<?= $u['id'] ?>">
        <td><input type="checkbox" class="user-select user-row-select" value="<?= $u['id'] ?>" onchange="updateBulkSelection()" aria-label="Select <?= e($u['name']) ?>"></td>
        <td style="color:var(--muted)"><?= $u['id'] ?></td>
        <td>
          <a href="#" style="font-weight:600;color:var(--accent)" onclick="editUser(<?= $u['id'] ?>);return false"><?= e($u['name']) ?></a>
          <?php if ($u['admin_note']): ?><div style="font-size:.7rem;color:var(--muted)"><?= e($u['admin_note']) ?></div><?php endif; ?>
        </td>
        <td style="color:var(--muted)"><?= e($u['dob']) ?></td>
        <td style="color:var(--muted);font-size:.78rem"><?= e($u['birth_city'] ?? '—') ?></td>
        <td><?= kai_level_badge($u['level']) ?></td>
        <td style="text-align:center;color:var(--muted)"><?= number_format((int)$u['points']) ?></td>
        <td style="text-align:center;color:var(--muted)"><?= $u['log_count'] ?></td>
        <td style="text-align:center;color:var(--muted)"><?= $u['compat_count'] ?></td>
        <td style="color:var(--muted);font-size:.77rem"><?= $u['last_login'] ? substr($u['last_login'],0,10) : '—' ?></td>
        <td style="color:var(--muted);font-size:.77rem"><?= $u['last_log'] ?? '—' ?></td>
        <td style="color:var(--muted);font-size:.77rem"><?= $u['created_at'] ? substr($u['created_at'],0,10) : '—' ?></td>
        <td>
          <button class="btn btn-sm" style="color:var(--accent);background:none;border:none;font-size:.95rem;padding:2px 6px"
            onclick="editUser(<?= $u['id'] ?>)" title="Edit"><i class="bi bi-pencil"></i></button>
          <button class="btn btn-sm" style="color:var(--red);background:none;border:none;font-size:.95rem;padding:2px 6px"
            onclick="deleteUser(<?= $u['id'] ?>,'<?= e(addslashes($u['name'])) ?>')" title="Delete"><i class="bi bi-trash3"></i></button>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$users): ?><tr><td colspan="13" style="text-align:center;color:var(--muted);padding:2rem">No users found.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1): ?>
  <div class="d-flex gap-1 flex-wrap align-items-center">
    <?php for($i=1;$i<=$pages;$i++): ?>
    <a href="?<?= e(http_build_query(['tab'=>'users','q'=>$search,'sort'=>$sort,'dir'=>$dir,'page'=>$i])) ?>" class="pg-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<!-- ══ BLOCKS ══ -->
<div id="tab-blocks" class="tab-page">
  <div class="page-title"><i class="bi bi-grid-3x3-gap me-2" style="color:var(--accent)"></i>Block Configuration</div>
  <p style="font-size:.85rem;color:var(--muted);margin-bottom:1.5rem">
    Drag rows to reorder. Toggle visibility per user level. Click <strong style="color:var(--text)">Save All</strong> when done.
  </p>

  <?php foreach (['biorhythm' => 'biorhythm.php — Tabs', 'index' => 'index.php — Sections'] as $pageKey => $pageLabel): ?>
  <div style="margin-bottom:1.5rem">
    <div style="font-size:.75rem;font-weight:700;color:var(--accent2);letter-spacing:1px;text-transform:uppercase;margin-bottom:.6rem;padding-left:.25rem">
      <?= $pageLabel ?>
    </div>
    <div class="d-flex align-items-center gap-2 px-2 mb-1" style="font-size:.72rem;font-weight:700;color:var(--muted); text-transform: uppercase; letter-spacing: 1px;">
      <span style="width:20px"></span>
      <span style="flex:1">Block Name</span>
      <span style="width:32px"></span>
      <span style="width:70px;text-align:center;color:#aaa">🆓 Silver</span>
      <span style="width:70px;text-align:center;color:#FFD700">⭐ Gold</span>
      <span style="width:70px;text-align:center;color:#a8d8ff">💎 Plat.</span>
    </div>
    <div class="block-list d-flex flex-column gap-1" data-page="<?= $pageKey ?>">
      <?php foreach ($blocks_by_page[$pageKey] ?? [] as $b):
        $isCompatSubBlock = str_starts_with($b['block_key'], 'compat_');
        $isSubBlock = $isCompatSubBlock || $b['block_key'] === 'milestones';
      ?>
      <div class="block-row<?= $isSubBlock ? ' sub-block' : '' ?>"
           data-key="<?= e($b['block_key']) ?>" data-label="<?= e($b['block_label']) ?>" data-order="<?= (int)$b['sort_order'] ?>"
           <?= $isSubBlock ? 'style="margin-left:2rem;background:rgba(255,255,255,.03);border-radius:8px;"' : '' ?>>
        <?php if ($isSubBlock): ?>
        <span style="width:32px;text-align:center;color:var(--muted);font-size:.8rem">↳</span>
        <?php else: ?>
        <span class="drag-handle"><i class="bi bi-grip-vertical"></i></span>
        <?php endif; ?>
        <span style="flex:1">
          <input type="text" class="block-label-input form-control form-control-sm"
            value="<?= e($b['block_label']) ?>"
            style="background:transparent;border:1px solid transparent;font-size:.87rem;padding:2px 6px;color:var(--text)"
            onfocus="this.style.borderColor='var(--accent)';this.style.background='rgba(0,0,0,0.4)'"
            onblur="this.style.borderColor='transparent';this.style.background='transparent'">
        </span>
        <?php if (!$isCompatSubBlock): ?>
        <button class="btn btn-sm" style="color:var(--accent);background:rgba(0,0,0,0.2);border:1px solid var(--border);border-radius:6px;padding:2px 8px;font-size:.75rem;white-space:nowrap"
          onclick="openContentEditor('<?= e($b['block_key']) ?>','<?= e(addslashes($b['block_label'])) ?>')">
          <i class="bi bi-pencil-square me-1"></i>Content
        </button>
        <?php endif; ?>
        <?php foreach (['silver','gold','platinum'] as $lvl): ?>
        <div style="width:70px;text-align:center">
          <div class="form-check form-switch d-flex justify-content-center m-0">
            <input class="form-check-input" type="checkbox" role="switch" data-level="<?= $lvl ?>"
              <?= $b[$lvl] ? 'checked' : '' ?> style="cursor:pointer;width:2em;height:1.1em">
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>

  <div class="d-flex align-items-center gap-3 mt-2">
    <button class="btn btn-primary px-4" onclick="saveBlocks()"><i class="bi bi-floppy me-2"></i>Save All</button>
    <span id="block-save-msg" style="font-size:.82rem;display:none"></span>
  </div>
</div>

<!-- ══ RANK RULES ══ -->
<div id="tab-ranks" class="tab-page">
  <div class="page-title"><i class="bi bi-award me-2" style="color:var(--accent)"></i>Rank Rules</div>
  <p style="font-size:.85rem;color:var(--muted);margin-bottom:1.5rem">
    Users are automatically promoted when their dUSD crosses thresholds. Automatic checks never downgrade a manually assigned higher rank.
  </p>

  <div class="stat-card" style="max-width:420px">
    <div class="row g-3 mb-3">
      <div class="col-6">
        <div class="label-sm">⭐ Gold at (dUSD)</div>
        <input type="number" id="rr-gold" class="form-control" min="0" step="10">
      </div>
      <div class="col-6">
        <div class="label-sm">💎 Platinum at (dUSD)</div>
        <input type="number" id="rr-plat" class="form-control" min="0" step="10">
      </div>
    </div>
    <div class="d-flex align-items-center gap-3">
      <button class="btn btn-primary px-4" onclick="saveRankRules()"><i class="bi bi-floppy me-2"></i>Save</button>
      <span id="rr-save-msg" style="font-size:.82rem;display:none"></span>
    </div>
  </div>

  <div class="mt-4">
    <button class="btn btn-outline-warning px-4" onclick="recalcAllRanks()">
      <i class="bi bi-arrow-repeat me-2"></i>Promote All Eligible Users Now
    </button>
    <span id="rr-recalc-msg" style="font-size:.82rem;margin-left:.75rem;display:none"></span>
  </div>

  <hr style="border-color:var(--border);margin:2rem 0">
  <div class="page-title" style="font-size:1rem"><i class="bi bi-gift me-2" style="color:#f0ad4e"></i>New User Promotion</div>
  <p style="font-size:.82rem;color:var(--muted);margin-bottom:1rem">Give new sign-ups a starting dUSD balance. Their resulting rank follows the thresholds above.</p>
  <div class="stat-card" style="max-width:520px">
    <div class="form-check form-switch mb-3">
      <input class="form-check-input" type="checkbox" id="promo-enabled" onchange="togglePromoType()">
      <label class="form-check-label" for="promo-enabled" style="font-weight:600">Enable Promotion</label>
    </div>
    <div class="row g-3 mb-3">
      <div class="col-6">
        <div class="label-sm">Starting dUSD for New Users</div>
        <input type="number" id="promo-start-pts" class="form-control" min="0" step="10" value="100">
        <div id="promo-rank-preview" style="font-size:.75rem;color:var(--muted);margin-top:4px">The resulting rank is based on the thresholds above.</div>
      </div>
      <div class="col-6">
        <div class="label-sm">Promotion Type</div>
        <select id="promo-type" class="form-select" onchange="togglePromoType()">
          <option value="duration">Always on (while toggle is enabled)</option>
          <option value="fixed_date">End on specific date (e.g. July 4)</option>
        </select>
      </div>
    </div>
    <div id="promo-date-row" class="row g-3 mb-3" style="display:none">
      <div class="col-6">
        <div class="label-sm">Promotion End Date</div>
        <input type="date" id="promo-end-date" class="form-control">
        <div style="font-size:.75rem;color:var(--muted);margin-top:4px">Users after this date get no bonus</div>
      </div>
    </div>
    <div class="d-flex gap-3 align-items-center">
      <button class="btn btn-primary px-4" onclick="savePromo()"><i class="bi bi-floppy me-2"></i>Save</button>
      <span id="promo-msg" style="font-size:.82rem;display:none"></span>
    </div>
  </div>

  <hr style="border-color:var(--border);margin:2rem 0">
  <div class="page-title" style="font-size:1rem"><i class="bi bi-coin me-2" style="color:#58a6ff"></i>dUSD Earning Rules</div>
  <p style="font-size:.82rem;color:var(--muted);margin-bottom:1rem">Control how many dUSD each user action earns. Disable to turn off that action entirely.</p>
  <div class="stat-card" style="max-width:520px">
    <div id="point-rules-list"></div>
    <div class="mt-3 d-flex gap-3 align-items-center">
      <button class="btn btn-primary px-4" onclick="savePointRules()"><i class="bi bi-floppy me-2"></i>Save Rules</button>
      <span id="pr-msg" style="font-size:.82rem;display:none"></span>
    </div>
  </div>
</div>

<!-- ══ DATABASE ══ -->
<div id="tab-database" class="tab-page">
  <div class="page-title"><i class="bi bi-hdd-stack me-2" style="color:var(--accent)"></i>Database Maintenance</div>

  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="stat-card h-100 d-flex flex-column">
        <div class="fw-bold mb-2"><i class="bi bi-arrow-repeat me-1" style="color:var(--accent)"></i>Optimize Tables</div>
        <p style="font-size:.8rem;color:var(--muted);flex:1">Defragments and reclaims unused space.</p>
        <button class="btn btn-outline-primary btn-sm" onclick="dbOptimize()"><i class="bi bi-play-fill me-1"></i>Run Optimize</button>
        <div id="db-optimize-result" style="font-size:.75rem;margin-top:.6rem;max-height:140px;overflow-y:auto"></div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="stat-card h-100 d-flex flex-column">
        <div class="fw-bold mb-2"><i class="bi bi-clock-history me-1" style="color:var(--yellow)"></i>Clear Old Sessions</div>
        <p style="font-size:.8rem;color:var(--muted);flex:1">Deletes login session files older than 24 hours.</p>
        <button class="btn btn-outline-warning btn-sm" onclick="dbClearSessions()"><i class="bi bi-trash3 me-1"></i>Clear Sessions</button>
        <div id="db-sessions-result" style="font-size:.75rem;margin-top:.6rem"></div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="stat-card h-100 d-flex flex-column">
        <div class="fw-bold mb-2"><i class="bi bi-download me-1" style="color:var(--green)"></i>Backup Database</div>
        <p style="font-size:.8rem;color:var(--muted);flex:1">Creates a full .sql dump of the database.</p>
        <button class="btn btn-outline-success btn-sm" onclick="dbBackup()"><i class="bi bi-play-fill me-1"></i>Run Backup</button>
        <div id="db-backup-result" style="font-size:.75rem;margin-top:.6rem"></div>
      </div>
    </div>
  </div>

  <div class="d-flex align-items-center justify-content-between mb-2">
    <div style="font-size:.85rem;font-weight:700;color:var(--accent2);text-transform:uppercase;letter-spacing:1px">Existing Backups</div>
    <button class="btn btn-sm btn-outline-secondary" onclick="loadBackupList()"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
  </div>
  <div class="table-wrap">
    <table class="data-table">
      <thead><tr><th>File</th><th>Size</th><th>Created</th><th></th></tr></thead>
      <tbody id="db-backups-tbody"><tr><td colspan="4" style="text-align:center;color:var(--muted);padding:1.5rem">Loading…</td></tr></tbody>
    </table>
  </div>
</div>

</div><!-- /main -->

<!-- ══ Edit User Modal ══ -->
<div id="edit-modal" class="modal-backdrop-custom" style="display:none" onclick="if(event.target===this)closeModal()">
  <div class="modal-box" onclick="event.stopPropagation()">
    <div class="modal-title"><i class="bi bi-person-gear me-2" style="color:var(--accent)"></i><span id="modal-name">Edit User</span></div>
    <input type="hidden" id="modal-uid">

    <div class="row g-2 mb-3">
      <div class="col-6">
        <div class="label-sm">Name (Read only)</div>
        <input type="text" id="modal-name-val" class="form-control" readonly style="opacity:.6">
      </div>
      <div class="col-6">
        <div class="label-sm">DOB (Read only)</div>
        <input type="text" id="modal-dob" class="form-control" readonly style="opacity:.6">
      </div>
    </div>
    <div class="row g-2 mb-3">
      <div class="col-6">
        <div class="label-sm">Birth City</div>
        <input type="text" id="modal-city" class="form-control" placeholder="e.g. Yangon">
      </div>
      <div class="col-3">
        <div class="label-sm">Birth Time</div>
        <input type="time" id="modal-btime" class="form-control" style="color-scheme:dark">
      </div>
      <div class="col-3">
        <div class="label-sm">Level</div>
        <select id="modal-level" class="form-select">
          <option value="silver">🆓 Silver</option>
          <option value="gold">⭐ Gold</option>
          <option value="platinum">💎 Platinum</option>
        </select>
      </div>
    </div>
    <div class="row g-3 mb-3">
      <div class="col-6">
        <div class="label-sm">⭐ Gold Until <span style="font-weight:400;color:var(--muted)">(temporary Gold expiry)</span></div>
        <input type="date" id="modal-gold-until" class="form-control" style="color-scheme:dark">
        <div style="font-size:.72rem;color:var(--muted);margin-top:3px">Auto-expires at midnight UTC. Leave blank for no expiry.</div>
      </div>
      <div class="col-6" style="display:flex;align-items:flex-end">
        <button class="btn btn-sm btn-outline-secondary" style="font-size:.75rem" onclick="clearGoldUntil()">Clear Gold Until</button>
      </div>
    </div>
    <div class="mb-3">
      <div class="label-sm">Admin Note</div>
      <input type="text" id="modal-note" class="form-control" placeholder="Internal note…">
    </div>

    <div style="margin-bottom:.75rem">
      <div class="label-sm" style="margin-bottom:.4rem">Stats</div>
      <div id="modal-stats" style="font-size:.8rem;color:var(--muted)"></div>
      <div style="font-size:.8rem;color:var(--muted);margin-top:4px">
        <i class="bi bi-coin me-1"></i>dUSD: <strong id="modal-points" style="color:var(--text)">—</strong>
        <button class="btn btn-sm btn-outline-secondary ms-2" style="font-size:.7rem;padding:1px 8px" onclick="recalcUserRank()">
          <i class="bi bi-arrow-repeat me-1"></i>Recalculate Rank
        </button>
        <span id="modal-recalc-msg" style="font-size:.75rem;margin-left:6px;display:none"></span>
      </div>
    </div>

    <div class="d-flex gap-2 justify-content-between align-items-center">
      <button class="btn btn-outline-warning btn-sm" onclick="resetPinModal()" title="Member forgot their PIN"><i class="bi bi-key me-1"></i>Reset PIN</button>
      <button class="btn btn-danger btn-sm" onclick="deleteUserModal()"><i class="bi bi-trash3 me-1"></i>Delete User</button>
      <div class="d-flex gap-2">
        <span id="modal-save-msg" style="font-size:.78rem;align-self:center;display:none"></span>
        <button class="btn btn-outline-secondary btn-sm" onclick="closeModal()">Cancel</button>
        <button class="btn btn-primary btn-sm px-4" onclick="saveUser()"><i class="bi bi-floppy me-1"></i>Save</button>
      </div>
    </div>
  </div>
</div>

<script>
// Expose CSRF to JavaScript
const CSRF_TOKEN = '<?= $_SESSION['admin_csrf'] ?? '' ?>';

// ── Tab switching ─────────────────────────────────────────────────
function showTab(name) {
  document.querySelectorAll('.tab-page').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  document.getElementById('tab-'+name)?.classList.add('active');
  document.getElementById('nav-'+name)?.classList.add('active');
  const url = new URL(location.href);
  url.searchParams.set('tab', name);
  if (name !== 'users') ['q','page','sort','dir'].forEach(key => url.searchParams.delete(key));
  history.replaceState(null,'',url.pathname+'?'+url.searchParams.toString());
}
showTab('<?= e($active_tab) ?>');

// ── Charts ────────────────────────────────────────────────────────
const co = (label, data, color, type='bar') => ({
  type, data:{labels:label, datasets:[{data, backgroundColor: type==='line'?color+'26':color+'b3',
    borderColor:color, borderWidth:type==='line'?2:1, fill:type==='line', tension:.4,
    pointRadius:type==='line'?2:0, borderRadius:type==='bar'?4:0}]},
  options:{responsive:true, maintainAspectRatio:false,
    plugins:{legend:{display:false}},
    scales:{x:{grid:{color:'rgba(255,255,255,0.05)'},ticks:{color:'#8b949e',font:{size:10},maxTicksLimit:8}},
            y:{grid:{color:'rgba(255,255,255,0.05)'},ticks:{color:'#8b949e',font:{size:10}},beginAtZero:true}}}
});

new Chart('chart-reg',   co(<?= json_encode($reg_labels) ?>,   <?= json_encode($reg_data) ?>,   '#66fcf1'));
new Chart('chart-act',   co(<?= json_encode($act_labels) ?>,   <?= json_encode($act_data) ?>,   '#3fb950', 'line'));
new Chart('chart-login', co(<?= json_encode($login_labels) ?>, <?= json_encode($login_data) ?>, '#b388ff', 'line'));
new Chart('chart-mon',   co(<?= json_encode($mon_labels) ?>,   <?= json_encode($mon_data) ?>,   '#d29922'));

const oraclePulseCounts = <?= json_encode($pulse_counts) ?>;
const oraclePulseUsers = <?= json_encode($pulse_users) ?>;
new Chart('chart-oracle-pulse', {
  type:'line',
  data:{labels:<?= json_encode($pulse_labels) ?>,datasets:[{label:'Daily Oracle Average',data:<?= json_encode($pulse_scores) ?>,
    borderColor:'#ff4d5f',backgroundColor:'rgba(255,77,95,.14)',borderWidth:3,pointRadius:4,pointHoverRadius:7,spanGaps:false,tension:.3,fill:true}]},
  options:{responsive:true,maintainAspectRatio:false,interaction:{mode:'index',intersect:false},plugins:{legend:{display:false},tooltip:{callbacks:{
    label(ctx){return ctx.parsed.y===null?' No readings':` Average: ${ctx.parsed.y.toFixed(2)}/10`;},
    afterLabel(ctx){return ` ${oraclePulseCounts[ctx.dataIndex]} reading(s) from ${oraclePulseUsers[ctx.dataIndex]} user(s)`;}
  }}},scales:{x:{grid:{color:'rgba(255,255,255,.05)'},ticks:{color:'#8b949e',maxTicksLimit:10,font:{size:10}}},
    y:{min:1,max:10,grid:{color:'rgba(255,255,255,.07)'},ticks:{color:'#ff9aa4',stepSize:1,callback:v=>v+'/10'}}}}
});

// ── AJAX helper with CSRF ───────────────────────────────────────────────────
async function post(data) {
  const fd = new FormData(); 
  fd.append('ajax','1');
  fd.append('csrf', CSRF_TOKEN); // CSRF Inclusion
  for (const [k,v] of Object.entries(data)) fd.append(k,v);
  try {
    const r = await fetch('dashboard.php',{method:'POST',body:fd});
    if (!r.ok) return {error:`Request failed (${r.status})`};
    return await r.json();
  } catch (_) { return {error:'Network or server response error'}; }
}

// ── Edit user modal ───────────────────────────────────────────────
let _editUid = null;

async function editUser(uid) {
  _editUid = uid;
  const msg = document.getElementById('modal-save-msg');
  msg.style.display = 'none';
  document.getElementById('modal-stats').textContent = 'Loading…';
  document.getElementById('edit-modal').style.display = 'flex';

  const res = await post({action:'get_user', uid});
  if (!res.ok) return;
  const u = res.user;
  document.getElementById('modal-uid').value       = u.id;
  document.getElementById('modal-name').textContent = u.name;
  document.getElementById('modal-name-val').value   = u.name;
  document.getElementById('modal-dob').value         = u.dob;
  document.getElementById('modal-city').value        = u.birth_city || '';
  document.getElementById('modal-btime').value       = u.birth_time || '';
  document.getElementById('modal-level').value       = u.level || 'silver';
  document.getElementById('modal-note').value        = u.admin_note || '';
  document.getElementById('modal-gold-until').value  = u.gold_until || '';
  document.getElementById('modal-points').textContent = (u.points ?? 0).toLocaleString();
  document.getElementById('modal-recalc-msg').style.display = 'none';

  document.getElementById('modal-stats').innerHTML =
    `<span class="me-3"><i class="bi bi-calendar3 me-1"></i>Joined: ${u.created_at?.slice(0,10)||'—'}</span>` +
    `<span class="me-3"><i class="bi bi-box-arrow-in-right me-1"></i>Last login: ${u.last_login?.slice(0,10)||'—'}</span>`;
}

function closeModal() { document.getElementById('edit-modal').style.display='none'; _editUid=null; }

// ── User selection and bulk actions ──────────────────────────────
const selectedUserIds = () => [...document.querySelectorAll('.user-row-select:checked')].map(el => Number(el.value));
function updateBulkSelection() {
  const boxes = [...document.querySelectorAll('.user-row-select')];
  const selected = boxes.filter(el => el.checked);
  boxes.forEach(el => el.closest('tr')?.classList.toggle('is-selected', el.checked));
  const all = document.getElementById('select-page');
  if (all) { all.checked = boxes.length > 0 && selected.length === boxes.length; all.indeterminate = selected.length > 0 && selected.length < boxes.length; }
  document.getElementById('bulk-bar')?.classList.toggle('active', selected.length > 0);
  const count = document.getElementById('selected-count');
  if (count) count.textContent = `${selected.length} selected`;
}
function togglePageSelection(checked) {
  document.querySelectorAll('.user-row-select').forEach(el => { el.checked = checked; });
  updateBulkSelection();
}
function bulkMessage(text, ok=false) {
  const msg = document.getElementById('bulk-msg');
  msg.style.color = ok ? '#3fb950' : '#f85149'; msg.textContent = text;
}
async function bulkSetLevel() {
  const ids = selectedUserIds(), level = document.getElementById('bulk-level').value;
  if (!ids.length || !level) { bulkMessage('Choose users and a level.'); return; }
  const r = await post({action:'bulk_set_level',ids:JSON.stringify(ids),level});
  if (!r.ok) { bulkMessage(r.error || 'Update failed.'); return; }
  bulkMessage(`Updated ${r.updated} user${r.updated===1?'':'s'}.`, true); setTimeout(()=>location.reload(),700);
}
async function bulkRecalculate() {
  const ids = selectedUserIds();
  if (!ids.length) { bulkMessage('Choose at least one user.'); return; }
  const r = await post({action:'bulk_recalc_rank',ids:JSON.stringify(ids)});
  if (!r.ok) { bulkMessage(r.error || 'Recalculation failed.'); return; }
  bulkMessage(`Checked ${ids.length}; promoted ${r.changed}.`, true); setTimeout(()=>location.reload(),700);
}
async function bulkDeleteUsers() {
  const ids = selectedUserIds();
  if (!ids.length) { bulkMessage('Choose at least one user.'); return; }
  if (!confirm(`Permanently delete ${ids.length} selected user${ids.length===1?'':'s'} and all related data? This cannot be undone.`)) return;
  const r = await post({action:'bulk_delete_users',ids:JSON.stringify(ids)});
  if (!r.ok) { bulkMessage(r.error || 'Delete failed.'); return; }
  bulkMessage(`Deleted ${r.deleted} user${r.deleted===1?'':'s'}.`, true); setTimeout(()=>location.reload(),700);
}

(async function loadPromo() {
  const r = await post({action:'get_promo'});
  if (!r.ok) return;
  document.getElementById('promo-enabled').checked = r.promo_enabled;
  document.getElementById('promo-type').value       = r.promo_type;
  document.getElementById('promo-start-pts').value  = r.promo_start_points;
  if (r.promo_end_date) document.getElementById('promo-end-date').value = r.promo_end_date;
  togglePromoType();
  updatePromoRankPreview();
  if (r.promo_enabled && r.promo_type === 'fixed_date' && !r.promo_end_date) {
    const msg = document.getElementById('promo-msg');
    msg.style.display=''; msg.style.color='#f0ad4e';
    msg.textContent='Choose an end date and save. This incomplete promotion is inactive.';
  }
})();
function togglePromoType() {
  const isDate = document.getElementById('promo-type').value === 'fixed_date';
  document.getElementById('promo-date-row').style.display = isDate ? '' : 'none';
}
function updatePromoRankPreview() {
  const pts = Number(document.getElementById('promo-start-pts')?.value || 0);
  const gold = Number(document.getElementById('rr-gold')?.value || 0);
  const plat = Number(document.getElementById('rr-plat')?.value || 0);
  const rank = pts >= plat ? 'Platinum' : pts >= gold ? 'Gold' : 'Silver';
  const preview = document.getElementById('promo-rank-preview');
  if (preview) preview.textContent = `${pts.toLocaleString()} dUSD starts a new user at ${rank} under the current thresholds.`;
}
document.getElementById('promo-start-pts')?.addEventListener('input', updatePromoRankPreview);
document.getElementById('rr-gold')?.addEventListener('input', updatePromoRankPreview);
document.getElementById('rr-plat')?.addEventListener('input', updatePromoRankPreview);
async function savePromo() {
  const msg = document.getElementById('promo-msg');
  const r = await post({
    action:'save_promo',
    promo_enabled:      document.getElementById('promo-enabled').checked ? '1' : '',
    promo_type:         document.getElementById('promo-type').value,
    promo_duration_days:90,
    promo_end_date:     document.getElementById('promo-end-date').value || '',
    promo_start_points: document.getElementById('promo-start-pts').value,
  });
  msg.style.display=''; msg.style.color=r.ok?'#3fb950':'#e05252';
  msg.textContent = r.ok ? '\u2713 Saved' : `\u2717 ${r.error || 'Error'}`;
  setTimeout(()=>msg.style.display='none',2500);
}
let _ptRules = [];
(async function loadPointRules() {
  const r = await post({action:'get_point_rules'});
  if (!r.ok) return;
  _ptRules = r.rules;
  renderPointRules();
})();
function renderPointRules() {
  const emojiMap = {daily_visit:'📅',share:'📤',session_5min:'⏱',compat_share:'💞'};
  const el = document.getElementById('point-rules-list');
  el.innerHTML = _ptRules.map((rule,i)=>`
    <div class="d-flex align-items-center gap-3 mb-3" style="border-bottom:1px solid var(--border);padding-bottom:.75rem">
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" id="pr-on-${i}" ${rule.enabled==1?'checked':''}>
      </div>
      <div style="flex:1;font-weight:600;font-size:.9rem">${emojiMap[rule.action_key]||''} ${rule.label}</div>
      <div style="display:flex;align-items:center;gap:6px">
        <span style="font-size:.8rem;color:var(--muted)">dUSD:</span>
        <input type="number" id="pr-pts-${i}" value="${rule.points}" min="0" max="100" class="form-control" style="width:72px;text-align:center">
      </div>
    </div>`).join('');
}
async function savePointRules() {
  const msg = document.getElementById('pr-msg');
  const rules = _ptRules.map((rule,i)=>({
    action_key: rule.action_key,
    points:     document.getElementById(`pr-pts-${i}`).value,
    enabled:    document.getElementById(`pr-on-${i}`).checked?1:0,
  }));
  const r = await post({action:'save_point_rules', rules:JSON.stringify(rules)});
  msg.style.display=''; msg.style.color=r.ok?'#3fb950':'#e05252';
  msg.textContent = r.ok ? '\u2713 Saved' : '\u2717 Error';
  setTimeout(()=>msg.style.display='none',2500);
}
async function recalcUserRank() {
  const msg = document.getElementById('modal-recalc-msg');
  msg.style.display = 'inline'; msg.style.color = 'var(--muted)'; msg.textContent = 'Checking…';
  const res = await post({action:'recalc_rank', uid:_editUid});
  if (res.ok) {
    if (res.changed > 0) {
      msg.style.color = '#3fb950'; msg.textContent = '✓ Promoted!';
      setTimeout(() => editUser(_editUid), 800);
    } else {
      msg.style.color = 'var(--muted)'; msg.textContent = 'No change — threshold not yet reached.';
    }
  } else { msg.style.color = '#f85149'; msg.textContent = 'Failed.'; }
}

async function saveUser() {
  const uid  = document.getElementById('modal-uid').value;
  const msg  = document.getElementById('modal-save-msg');
  msg.style.display='inline'; msg.style.color='var(--muted)'; msg.textContent='Saving…';
  const res = await post({
    action:'save_user', uid,
    level:      document.getElementById('modal-level').value,
    birth_city: document.getElementById('modal-city').value,
    birth_time: document.getElementById('modal-btime').value,
    admin_note: document.getElementById('modal-note').value,
    gold_until: document.getElementById('modal-gold-until').value || '',
  });
  if (res.ok) {
    msg.style.color='#3fb950'; msg.textContent='✓ Saved!';
    setTimeout(() => location.reload(), 800);
  } else { msg.style.color='#f85149'; msg.textContent='Save failed.'; }
}

function clearGoldUntil() { document.getElementById('modal-gold-until').value = ''; }
async function resetPinModal() {
  if (!_editUid) return;
  if (!confirm('Reset this member\x27s PIN? Only do this after confirming who they are. Their remembered devices will be signed out, and they will create a new PIN at their next sign-in.')) return;
  const res = await post({action:'reset_pin', uid:_editUid});
  alert(res.ok ? 'PIN reset. Ask the member to sign in with their name and date of birth and create a new PIN.' : (res.error || 'Reset failed.'));
}
async function deleteUser(uid, name) {
  if (!confirm(`Delete user "${name}" and ALL their data? This cannot be undone.`)) return;
  const res = await post({action:'delete_user', uid});
  if (res.ok) { document.getElementById('urow-'+uid)?.remove(); closeModal(); }
}
function deleteUserModal() { deleteUser(_editUid, document.getElementById('modal-name-val').value); }

function esc(s) { const d=document.createElement('div'); d.textContent=s; return d.innerHTML; }

// ── Block config ──────────────────────────────────────────────────
document.querySelectorAll('.block-list').forEach(list => {
  Sortable.create(list, { animation:150, handle:'.drag-handle', ghostClass:'sortable-ghost' });
});

async function saveBlocks() {
  const blocks = [];
  document.querySelectorAll('.block-list').forEach(list => {
    let mainOrder = 0;
    list.querySelectorAll(':scope > .block-row').forEach(row => {
      const order = row.classList.contains('sub-block') ? Number(row.dataset.order || 0) : mainOrder++;
      blocks.push({
        key:      row.dataset.key,
        order,
        label:    row.querySelector('.block-label-input')?.value || row.dataset.label || '',
        silver:   row.querySelector('[data-level="silver"]')?.checked   ? 1 : 0,
        gold:     row.querySelector('[data-level="gold"]')?.checked     ? 1 : 0,
        platinum: row.querySelector('[data-level="platinum"]')?.checked ? 1 : 0,
      });
    });
  });
  const msg = document.getElementById('block-save-msg');
  msg.style.display='inline'; msg.style.color='var(--muted)'; msg.textContent='Saving…';
  const r = await post({action:'save_blocks', blocks:JSON.stringify(blocks)});
  msg.style.color = r.ok ? '#3fb950' : '#f85149';
  msg.textContent = r.ok ? `✓ Saved ${r.updated} blocks. Changes are live.` : `Save failed: ${r.error || 'Unknown error'}`;
}

// ── Rank rules ──────────────────────────────────────────────────────
(async function loadRankRules() {
  const res = await post({action:'get_rank_rules'});
  if (res && res.ok) {
    document.getElementById('rr-gold').value = res.gold_threshold;
    document.getElementById('rr-plat').value = res.platinum_threshold;
    updatePromoRankPreview();
  }
})();

function saveRankRules() {
  const gold = Number(document.getElementById('rr-gold').value);
  const plat = Number(document.getElementById('rr-plat').value);
  const msg  = document.getElementById('rr-save-msg');
  if (!Number.isFinite(gold) || gold < 0 || !Number.isFinite(plat) || plat <= gold) {
    msg.style.display='inline'; msg.style.color='#f85149'; msg.textContent='Platinum must be greater than Gold.'; return;
  }
  msg.style.display='inline'; msg.style.color='var(--muted)'; msg.textContent='Saving…';
  post({action:'save_rank_rules', gold_threshold:gold, platinum_threshold:plat}).then(r => {
    if (r.ok) {
      document.getElementById('rr-gold').value = r.gold_threshold;
      document.getElementById('rr-plat').value = r.platinum_threshold;
      updatePromoRankPreview();
      msg.style.color='#3fb950'; msg.textContent='✓ Saved!';
    } else { msg.style.color='#f85149'; msg.textContent=r.error || 'Save failed.'; }
  });
}

function recalcAllRanks() {
  const msg = document.getElementById('rr-recalc-msg');
  msg.style.display='inline'; msg.style.color='var(--muted)'; msg.textContent='Recalculating…';
  post({action:'recalc_all_ranks'}).then(r => {
    if (r.ok) {
      msg.style.color='#3fb950'; msg.textContent = `✓ Done — ${r.changed} user${r.changed===1?'':'s'} promoted.`;
      setTimeout(() => location.reload(), 1500);
    } else { msg.style.color='#f85149'; msg.textContent='Failed.'; }
  });
}

// ── Database maintenance ────────────────────────────────────────────
function fmtBytes(n) {
  if (n < 1024) return n + ' B';
  if (n < 1024*1024) return (n/1024).toFixed(1) + ' KB';
  return (n/1024/1024).toFixed(2) + ' MB';
}

function dbOptimize() {
  const box = document.getElementById('db-optimize-result');
  box.innerHTML = '<span style="color:var(--muted)">Running…</span>';
  post({action:'db_optimize'}).then(r => {
    if (!r.ok) { box.innerHTML = '<span style="color:#f85149">Failed.</span>'; return; }
    box.innerHTML = r.results.map(x =>
      `<div><i class="bi bi-check-circle me-1" style="color:#3fb950"></i>${esc(x.table)} — ${esc(x.status)}</div>`
    ).join('');
  });
}

function dbClearSessions() {
  const box = document.getElementById('db-sessions-result');
  box.innerHTML = '<span style="color:var(--muted)">Clearing…</span>';
  post({action:'db_clear_sessions'}).then(r => {
    box.innerHTML = r.ok
      ? `<span style="color:#3fb950">✓ Removed ${r.deleted} old session file${r.deleted===1?'':'s'}.</span>`
      : '<span style="color:#f85149">Failed.</span>';
  });
}

function dbBackup() {
  const box = document.getElementById('db-backup-result');
  box.innerHTML = '<span style="color:var(--muted)">Backing up… this may take a moment.</span>';
  post({action:'db_backup'}).then(r => {
    if (r.ok) {
      box.innerHTML = `<span style="color:#3fb950">✓ Saved ${esc(r.file)} (${fmtBytes(r.size)})</span>`;
      loadBackupList();
    } else {
      box.innerHTML = `<span style="color:#f85149">${esc(r.error || 'Failed.')}</span>`;
    }
  });
}

async function loadBackupList() {
  const tbody = document.getElementById('db-backups-tbody');
  tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;color:var(--muted);padding:1.5rem">Loading…</td></tr>';
  const r = await post({action:'db_list_backups'});
  if (!r.ok || !r.files.length) {
    tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;color:var(--muted);padding:1.5rem">No backups yet.</td></tr>';
    return;
  }
  tbody.innerHTML = r.files.map(f => `
    <tr>
      <td>${esc(f.name)}</td>
      <td style="color:var(--muted)">${fmtBytes(f.size)}</td>
      <td style="color:var(--muted)">${new Date(f.mtime*1000).toLocaleString()}</td>
      <td>
        <a class="btn btn-sm" style="color:var(--accent);background:none;border:none;font-size:.9rem;padding:2px 6px"
           href="?download_backup=${encodeURIComponent(f.name)}" title="Download"><i class="bi bi-download"></i></a>
        <button class="btn btn-sm" style="color:var(--red);background:none;border:none;font-size:.9rem;padding:2px 6px"
           onclick="deleteBackup('${esc(f.name)}')" title="Delete"><i class="bi bi-trash3"></i></button>
      </td>
    </tr>`).join('');
}

function deleteBackup(name) {
  if (!confirm(`Delete backup "${name}"? This cannot be undone.`)) return;
  post({action:'db_delete_backup', name}).then(r => { if (r.ok) loadBackupList(); });
}

document.getElementById('nav-database')?.addEventListener('click', () => {
  if (!document.getElementById('db-backups-tbody').dataset.loaded) {
    document.getElementById('db-backups-tbody').dataset.loaded = '1';
    loadBackupList();
  }
});

// ── Content Editor (Quill) ─────────────────────────────────────────
let _quill = null;
let _contentKey = null;
let _allBlockContents = null;

async function _loadBlockContents() {
  if (_allBlockContents) return _allBlockContents;
  const r = await post({action:'get_all_block_contents'});
  if (r.ok) _allBlockContents = Object.fromEntries(r.blocks.map(b => [b.block_key, b.content || '']));
  return _allBlockContents || {};
}

async function openContentEditor(key, label) {
  _contentKey = key;
  document.getElementById('content-modal-key').textContent   = key;
  document.getElementById('content-modal-label').textContent = 'Edit Content — ' + label;
  document.getElementById('content-save-msg').style.display  = 'none';

  if (!_quill) {
    _quill = new Quill('#content-quill-editor', {
      theme: 'snow',
      modules: { toolbar: [
        [{ header: [2, 3, false] }],
        ['bold', 'italic', 'underline'],
        [{ color: [] }, { background: [] }],
        [{ list: 'ordered' }, { list: 'bullet' }],
        ['link'],
        ['clean']
      ]}
    });
    const ql = document.querySelector('.ql-toolbar');
    if (ql) { ql.style.borderColor='var(--border)'; ql.style.background='rgba(28,33,40,0.9)'; }
    const qc = document.querySelector('.ql-container');
    if (qc) { qc.style.borderColor='var(--border)'; qc.style.color='var(--text)'; qc.style.fontSize='.9rem'; }
    const qe = document.querySelector('.ql-editor');
    if (qe) qe.style.minHeight='180px';
  }

  const contents = await _loadBlockContents();
  const html = contents[key] || '';
  _quill.root.innerHTML = html;

  document.getElementById('content-modal').style.display = 'flex';
  setTimeout(() => _quill.focus(), 100);
}

function closeContentModal() {
  document.getElementById('content-modal').style.display = 'none';
  _contentKey = null;
}

async function saveBlockContent() {
  if (!_contentKey || !_quill) return;
  const msg     = document.getElementById('content-save-msg');
  const content = _quill.root.innerHTML;
  msg.style.display = 'inline'; msg.style.color = 'var(--muted)'; msg.textContent = 'Saving…';
  try {
    const fd = new FormData();
    fd.append('ajax','1');
    fd.append('csrf', CSRF_TOKEN);
    fd.append('action','save_block_content');
    fd.append('key', _contentKey);
    fd.append('content', content);
    const resp = await fetch('dashboard.php', {method:'POST', body:fd});
    const text = await resp.text();
    let r;
    try { r = JSON.parse(text); }
    catch(e) { msg.style.color='#f85149'; msg.textContent='Server error: '+text.slice(0,120); return; }
    if (r.ok) {
      if (_allBlockContents) _allBlockContents[_contentKey] = content;
      msg.style.color = '#3fb950'; msg.textContent = '✓ Saved!';
      setTimeout(closeContentModal, 900);
    } else {
      msg.style.color = '#f85149'; msg.textContent = 'Save failed: '+(r.error||'unknown');
    }
  } catch(e) {
    msg.style.color = '#f85149'; msg.textContent = 'Network error: '+e.message;
  }
}
</script>

<!-- ══ Content Editor Modal ══ -->
<div id="content-modal" class="modal-backdrop-custom" style="display:none" onclick="if(event.target===this)closeContentModal()">
  <div class="modal-box" onclick="event.stopPropagation()" style="max-width:780px;width:95%">
    <div class="modal-title"><i class="bi bi-pencil-square me-2" style="color:var(--accent)"></i><span id="content-modal-label">Edit Content</span></div>
    <div style="font-size:.78rem;color:var(--muted);margin-bottom:.75rem">Block key: <code id="content-modal-key" style="color:var(--accent)"></code></div>
    <div id="content-quill-editor" style="min-height:220px;background:rgba(13,17,23,0.8);border:1px solid var(--border);border-radius:10px;color:var(--text)"></div>
    <div class="d-flex align-items-center gap-3 mt-3">
      <button class="btn btn-primary px-4" onclick="saveBlockContent()"><i class="bi bi-floppy me-2"></i>Save Content</button>
      <button class="btn btn-outline-secondary px-3" onclick="closeContentModal()">Cancel</button>
      <span id="content-save-msg" style="font-size:.82rem;display:none"></span>
    </div>
  </div>
</div>
</body>
</html>
