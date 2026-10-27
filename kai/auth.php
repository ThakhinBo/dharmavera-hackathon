<?php
// auth.php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../admin_config.php';

function kai_db(): mysqli {
    static $c = null;
    if (!$c) {
        $c = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($c->connect_error) die('DB error: ' . $c->connect_error);
        $c->set_charset('utf8mb4');
        kai_migrate($c);
    }
    return $c;
}

function kai_migrate(mysqli $c): void {
    $c->query("ALTER TABLE bio_users
        ADD COLUMN IF NOT EXISTS level      VARCHAR(10)  NOT NULL DEFAULT 'silver',
        ADD COLUMN IF NOT EXISTS created_at DATETIME     DEFAULT NOW(),
        ADD COLUMN IF NOT EXISTS last_login DATETIME     DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS admin_note VARCHAR(255) DEFAULT NULL");

    $c->query("UPDATE bio_users SET created_at=NOW() WHERE created_at IS NULL");

    $c->query("CREATE TABLE IF NOT EXISTS bio_block_config (
        block_key   VARCHAR(50)  PRIMARY KEY,
        block_label VARCHAR(120) NOT NULL,
        page        VARCHAR(20)  NOT NULL DEFAULT 'biorhythm',
        sort_order  INT NOT NULL DEFAULT 0,
        silver      TINYINT(1) NOT NULL DEFAULT 1,
        gold        TINYINT(1) NOT NULL DEFAULT 1,
        platinum    TINYINT(1) NOT NULL DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $c->query("ALTER TABLE bio_block_config ADD COLUMN IF NOT EXISTS page VARCHAR(20) NOT NULL DEFAULT 'biorhythm'");

    $c->query("CREATE TABLE IF NOT EXISTS bio_oracle_readings (
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

    $defaults = [
        ['chart',         'Biorhythm Chart',                   'biorhythm',  0],
        ['daily',         'Daily Log',                         'biorhythm',  1],
        ['milestones',    'Milestones',                        'biorhythm',  2],
        ['history',       'History',                           'biorhythm',  3],
        ['compat',        'Harmony',                           'biorhythm',  4],
        ['compat_cz',     'China — Chinese Zodiac',            'biorhythm',  5],
        ['compat_mhb',    'Burmese Astrology — Mahabote',      'biorhythm',  6],
        ['compat_synastry','Western Astrology — Synastry & Composite Chart','biorhythm', 7],
        ['compat_blood',  'Japan / Korea Tradition — Blood Type Compatibility','biorhythm', 8],
        ['compat_mbti',   'Personality Match Score', 'biorhythm', 9],
        ['idx_form',      'Your Details (input form)',         'index',     10],
        ['idx_mahabote',  'Mahabote — Right Now DO/DON\'T',    'index',     11],
        ['idx_famous',    'Famous Birthday Banner',            'index',     12],
        ['idx_streak',    'Visit Streak & dUSD Rewards',        'index',     25],
        ['idx_share',     'Share Reading',                      'index',     26],
        ['idx_dayguide',  'Astrological Day Guide',            'index',     13],
        ['idx_mmcal',     'Myanmar Calendar',                  'index',     14],
        ['idx_overall',   'Today\'s Overall Score',            'index',     15],
        ['idx_biochart',  'Biorhythm Cycles Chart',            'index',     16],
        ['idx_bioscores', 'Today\'s Cycle Scores',             'index',     17],
        ['idx_taya',      'Zanaka Taya — Life Cycle',          'index',     18],
        ['idx_natal',     'Natal Chart — Birth Mahabote',      'index',     19],
        ['idx_zodiac',    'Myanmar Zodiac Animal',             'index',     20],
        ['idx_chinese',   'Chinese Zodiac',                    'index',     21],
        ['idx_compat',    'Compatibility Reading (Index)',     'index',     22],
        ['idx_tharme',    'Tharme — Yearly Fortune',           'index',     23],
        ['idx_dodont',    'Today\'s DO / DON\'T Guide',        'index',     24],
    ];
    $ins = $c->prepare("INSERT IGNORE INTO bio_block_config (block_key,block_label,page,sort_order) VALUES (?,?,?,?)");
    foreach ($defaults as [$k,$l,$p,$o]) { $ins->bind_param('sssi',$k,$l,$p,$o); $ins->execute(); }
}

function requireAdmin(): void {
    if (empty($_SESSION['kai_admin'])) { header('Location: index.php'); exit; }
    // Sign the admin out after 30 idle minutes
    if (time() - (int)($_SESSION['kai_last'] ?? 0) > 1800) {
        $_SESSION = []; session_destroy();
        header('Location: index.php?timeout=1'); exit;
    }
    $_SESSION['kai_last'] = time();
}

// Database-backed limit on admin sign-in attempts (clearing cookies does not reset it).
function kai_login_allowed(string $ip): bool {
    $db = kai_db(); $now = time(); $bucket = 'kai-login:' . $ip; $win = 900;
    $s = $db->prepare("INSERT INTO security_throttle (bucket, hits, window_start) VALUES (?, 1, ?)
        ON DUPLICATE KEY UPDATE hits = IF(window_start < ? - ?, 1, hits + 1), window_start = IF(window_start < ? - ?, ?, window_start)");
    if (!$s) return true;
    $s->bind_param('siiiiii', $bucket, $now, $now, $win, $now, $win, $now); $s->execute();
    $r = $db->prepare("SELECT hits FROM security_throttle WHERE bucket=?");
    $r->bind_param('s', $bucket); $r->execute();
    return (int)($r->get_result()->fetch_assoc()['hits'] ?? 0) <= 10;
}

function kai_level_badge(string $level): string {
    return match($level) {
        'gold'     => '<span class="lvl-badge lvl-gold">⭐ Gold</span>',
        'platinum' => '<span class="lvl-badge lvl-platinum">💎 Platinum</span>',
        default    => '<span class="lvl-badge lvl-silver">🆓 Silver</span>',
    };
}
?>
