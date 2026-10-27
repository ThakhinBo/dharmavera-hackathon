<?php
/**
 * Myanmar Astrology + Biorhythm Calculator
 * dharmavera.com
 * Combines: Biorhythm cycles, Zanaka Taya, Mahabote, Myanmar Zodiac
 */

require_once __DIR__ . '/core_init.php';

// Use UTC on server — the live Mahabote panel is rendered by JS using browser local time.

// ══════════════════════════════════════════════════════
// MAHABOTE QUESTION METHOD (မေးခွန်းထုတ်နည်း)
// Based on the time the question is asked — NOT birth date.
// Formula: position = (dayOfWeek + hour) % 7  → 0 treated as 7
// 7 Houses: ဘီလူး(1) နဂါး(2) ဆင်(3) မင်း(4) ရသေ့(5) သူဌေး(6) အဿ(7)
// ══════════════════════════════════════════════════════

$mhb_houses = [
    1 => [
        'mm'      => 'ဘီလူး',
        'en'      => 'Ogre',
        'score'   => 0,
        'color'   => '#c9302c',
        'icon'    => '👹',
        'meaning' => 'Disruption, obstacles and interference. Things may not go as planned.',
        'advice'  => 'Postpone important decisions. Be patient and wait for a better time.',
        'status'  => "DON'T",
    ],
    2 => [
        'mm'      => 'နဂါး',
        'en'      => 'Naga',
        'score'   => 8,
        'color'   => '#28a745',
        'icon'    => '🐉',
        'meaning' => 'Power, success and authority. Great energy for achieving goals.',
        'advice'  => 'Act boldly. Authority and strength are on your side right now.',
        'status'  => 'DO',
    ],
    3 => [
        'mm'      => 'ဆင်',
        'en'      => 'Swin (Elephant)',
        'score'   => 9,
        'color'   => '#1a7a1a',
        'icon'    => '🐘',
        'meaning' => 'Victory and triumph. Excellent for competitions, exams and leadership.',
        'advice'  => 'Push forward. Victory is highly likely at this moment.',
        'status'  => 'DO',
    ],
    4 => [
        'mm'      => 'မင်း',
        'en'      => 'Min (King)',
        'score'   => 10,
        'color'   => '#8338EC',
        'icon'    => '👑',
        'meaning' => 'Government, officials and authority support you. Peak power position.',
        'advice'  => 'Ideal for dealing with authorities, applications and official matters.',
        'status'  => 'DO',
    ],
    5 => [
        'mm'      => 'ရသေ့',
        'en'      => 'Yathe (Hermit)',
        'score'   => 4,
        'color'   => '#f0ad4e',
        'icon'    => '🧘',
        'meaning' => 'Peace, wisdom and steady calm. Good for reflection and spiritual matters.',
        'advice'  => 'Suitable for study, meditation and quiet planning. Not for bold action.',
        'status'  => 'CAUTION',
    ],
    6 => [
        'mm'      => 'သူဌေး',
        'en'      => 'Thute (Merchant)',
        'score'   => 6,
        'color'   => '#FF9F1C',
        'icon'    => '💰',
        'meaning' => 'Wealth, beauty and fame. Good time for business deals and financial decisions.',
        'advice'  => 'Favorable for trade, money matters and creative work.',
        'status'  => 'OK',
    ],
    7 => [
        'mm'      => 'အဿ',
        'en'      => 'Assa (Horse)',
        'score'   => 2,
        'color'   => '#5bc0de',
        'icon'    => '🐎',
        'meaning' => 'Travel, communication and trade. Movement and transitions are highlighted.',
        'advice'  => 'Good for travel and communication. Avoid signing binding contracts.',
        'status'  => 'OK',
    ],
];

// Calculate current Mahabote house right now
$now_dt      = new DateTime('now');
// Use 12-hour clock (1–12): 2 PM = 2, 8 PM = 8, never 14 or 20
$now_dow     = (int)$now_dt->format('w') + 1; // Sun=1 … Sat=7
$now_hour24  = (int)$now_dt->format('G');      // 0–23 (for display only)
$now_hour    = (int)$now_dt->format('g');      // 12-hour 1–12 ← used in formula
$now_sum     = $now_dow + $now_hour;
$now_pos     = ($now_sum % 7 == 0) ? 7 : ($now_sum % 7);
$now_house   = $mhb_houses[$now_pos];
$now_time_str = $now_dt->format('l, d M Y  h:i A'); // 12-hr display

// ══════════════════════════════════════════════════════
// CALCULATION ENGINE
// ══════════════════════════════════════════════════════

// ── Zanaka Taya (7 life conditions) ──
// Sequence order: တည် → ထွက် → ပျက် → ကျောက် → ထီး → နန်း → စံ (then repeats)
// Score: ပျက်=0, တည်=5, ကျောက်=6, ထွက်=7, ထီး=8, နန်း=9, စံ=10
// Each Taya lasts 2 years. Full cycle = 14 years.
// Starting age for တည် = birth day number (Sun=1, Mon=2 ... Sat=7)

$taya_list = [
    // index 0
    ['mm'=>'တည်',   'en'=>'Foundation', 'score'=>5,  'color'=>'#f0ad4e',
     'meaning'=>'Planning & building phase. New ventures begin. Money flows both in and out. Good for investing and job applications.',
     'advice'=>'Work hard and plan carefully. Expenses are high but income follows.'],
    // index 1
    ['mm'=>'ထွက်',  'en'=>'Journey',    'score'=>7,  'color'=>'#5bc0de',
     'meaning'=>'New friendships, travel, partnerships. Good for foreign trade, joint ventures and domestic/overseas travel.',
     'advice'=>'Excellent for partnerships, travel and new connections. Expand your network.'],
    // index 2
    ['mm'=>'ပျက်',  'en'=>'Breakdown',  'score'=>0,  'color'=>'#c9302c',
     'meaning'=>'Lowest phase. Business decline, accidents, health issues, rivals appear. Marital problems possible.',
     'advice'=>'Lay low. Avoid major risks, big decisions and conflicts. Focus on protection.'],
    // index 3
    ['mm'=>'ကျောက်','en'=>'Rising',     'score'=>6,  'color'=>'#48CAE4',
     'meaning'=>'Unexpected help arrives. Luck returns. Health recovers. Lottery wins possible. Good mentor found. Legal troubles resolved.',
     'advice'=>'Fortune is returning. Act on opportunities. Good for education and marriage.'],
    // index 4
    ['mm'=>'ထီး',   'en'=>'Fame',       'score'=>8,  'color'=>'#5cb85c',
     'meaning'=>'Fame and recognition. Awards, degrees, promotions and business expansion. Exam success. New home possible.',
     'advice'=>'Step forward boldly. Recognition and rewards are coming your way.'],
    // index 5
    ['mm'=>'နန်း',  'en'=>'Throne',     'score'=>9,  'color'=>'#51cf66',
     'meaning'=>'Power, authority and success. Career advancement, business prosperity, inheritance. Competition victories. Marriage luck.',
     'advice'=>'Peak power phase. Lead, compete and achieve. Give to charity for best results.'],
    // index 6
    ['mm'=>'စံ',    'en'=>'Prosperity', 'score'=>10, 'color'=>'#69db7c',
     'meaning'=>'Royal prosperity. All dangers pass. Great wealth, high position, loyal friends and savings accumulate.',
     'advice'=>'Best possible phase. Pursue your biggest goals. Everything you touch flourishes.'],
];

// Birth day number (used as starting age for တည်)
// Sunday=1, Monday=2, Tuesday=3, Wednesday=4, Thursday=5, Friday=6, Saturday=7
$day_base_num = [0=>1, 1=>2, 2=>3, 3=>4, 4=>5, 5=>6, 6=>7];

// ── Myanmar 8-Animal Zodiac (by birth day of week) ──
$myanmar_zodiac = [
    0 => ['animal'=>'Garuda (Eagle)',       'mm'=>'ဂဠုန်',     'planet'=>'Sun',     'dir'=>'NE',
          'trait'=>'Brave, ambitious, royal pride, protective instinct.'],
    1 => ['animal'=>'Tiger',                'mm'=>'ကျား',      'planet'=>'Moon',    'dir'=>'NE',
          'trait'=>'Powerful, fierce loyalty, strong instincts.'],
    2 => ['animal'=>'Lion',                 'mm'=>'ခြင်သေ့',   'planet'=>'Mars',    'dir'=>'E',
          'trait'=>'Courageous, proud, dominant, warm-hearted.'],
    3 => ['animal'=>'Elephant (tusked)',    'mm'=>'ဆင် (မြင်)','planet'=>'Mercury', 'dir'=>'S',
          'trait'=>'Wise, dignified, reliable, intelligent.'],
    // Wed AM (00:00–11:59) = tusked elephant (index 3)
    // Wed PM (12:00–23:59) = tuskless elephant
    'wed_pm' =>
          ['animal'=>'Elephant (tuskless)', 'mm'=>'ဆင် (မဲ)',  'planet'=>'Rahu',    'dir'=>'NW',
          'trait'=>'Intuitive, mysterious, unpredictable, spiritual.'],
    4 => ['animal'=>'Rat',                  'mm'=>'ကြွက်',     'planet'=>'Jupiter', 'dir'=>'W',
          'trait'=>'Clever, resourceful, adaptable, quick-witted.'],
    5 => ['animal'=>'Guinea Pig',           'mm'=>'ပူး',       'planet'=>'Venus',   'dir'=>'NW',
          'trait'=>'Creative, gentle, artistic, peaceful nature.'],
    6 => ['animal'=>'Dragon (Naga)',        'mm'=>'နဂါး',      'planet'=>'Saturn',  'dir'=>'N',
          'trait'=>'Wise, powerful, mysterious, resilient spirit.'],
];

// ── Chinese Zodiac ──
// (year - 1900) % 12 → 0=Rat … 11=Pig
$chinese_zodiac = [
    0 => ['animal'=>'Rat',     'icon'=>'🐀', 'rank'=>3,  'color'=>'#48CAE4',
          'note'=>'Master strategist and social networker — charmed their way to 1st in the race. Self-focused but brilliantly resourceful.',
          'scores'=>['Leadership'=>7,'Intelligence'=>10,'Sociability'=>8,'Creativity'=>8,'Reliability'=>5]],
    1 => ['animal'=>'Ox',      'icon'=>'🐂', 'rank'=>12, 'color'=>'#8b949e',
          'note'=>'Unmatched endurance and integrity. Prefers solitude over crowds and routine over inspiration — but never breaks a promise.',
          'scores'=>['Leadership'=>5,'Intelligence'=>5,'Sociability'=>3,'Creativity'=>3,'Reliability'=>10]],
    2 => ['animal'=>'Tiger',   'icon'=>'🐯', 'rank'=>8,  'color'=>'#FF9F1C',
          'note'=>'Fearless and magnetic. Acts on instinct rather than analysis — bold enough to break every rule and creative enough to make it work.',
          'scores'=>['Leadership'=>10,'Intelligence'=>6,'Sociability'=>7,'Creativity'=>8,'Reliability'=>4]],
    3 => ['animal'=>'Rabbit',  'icon'=>'🐇', 'rank'=>4,  'color'=>'#5bc0de',
          'note'=>'Perceptive, graceful, and quietly wise. Reads people deeply but steps back from conflict — the diplomat who wins without fighting.',
          'scores'=>['Leadership'=>4,'Intelligence'=>8,'Sociability'=>9,'Creativity'=>9,'Reliability'=>7]],
    4 => ['animal'=>'Dragon',  'icon'=>'🐲', 'rank'=>1,  'color'=>'#8338EC',
          'note'=>'The zodiac\'s singular force — visionary, commanding, and endlessly creative. Can lose focus when a greater challenge isn\'t in sight.',
          'scores'=>['Leadership'=>10,'Intelligence'=>9,'Sociability'=>8,'Creativity'=>10,'Reliability'=>5]],
    5 => ['animal'=>'Snake',   'icon'=>'🐍', 'rank'=>4,  'color'=>'#51cf66',
          'note'=>'The most enigmatic sign. Leads through quiet strategy and deep intuition — never reveals the full picture, yet always two steps ahead.',
          'scores'=>['Leadership'=>8,'Intelligence'=>10,'Sociability'=>4,'Creativity'=>9,'Reliability'=>6]],
    6 => ['animal'=>'Horse',   'icon'=>'🐴', 'rank'=>6,  'color'=>'#FF6B35',
          'note'=>'Electric energy and infectious enthusiasm. The ultimate team energizer — but freedom comes first, and commitments run a distant second.',
          'scores'=>['Leadership'=>9,'Intelligence'=>6,'Sociability'=>10,'Creativity'=>7,'Reliability'=>4]],
    7 => ['animal'=>'Goat',    'icon'=>'🐐', 'rank'=>10, 'color'=>'#E63946',
          'note'=>'The zodiac\'s truest artist — imagination without limit, empathy without walls. Can lead with quiet strength when called upon, but thrives most when creating.',
          'scores'=>['Leadership'=>4,'Intelligence'=>6,'Sociability'=>8,'Creativity'=>10,'Reliability'=>5]],
    8 => ['animal'=>'Monkey',  'icon'=>'🐒', 'rank'=>2,  'color'=>'#FFD700',
          'note'=>'Dazzlingly clever and irresistibly social. Learns anything in half the time — but loyalty is situational and follow-through is rare.',
          'scores'=>['Leadership'=>9,'Intelligence'=>10,'Sociability'=>10,'Creativity'=>9,'Reliability'=>3]],
    9 => ['animal'=>'Rooster', 'icon'=>'🐓', 'rank'=>6,  'color'=>'#f0ad4e',
          'note'=>'Sharp-eyed, disciplined, and demanding. Holds others to the same exacting standard they hold themselves — which not everyone appreciates.',
          'scores'=>['Leadership'=>8,'Intelligence'=>8,'Sociability'=>6,'Creativity'=>5,'Reliability'=>9]],
    10=> ['animal'=>'Dog',     'icon'=>'🐕', 'rank'=>8,  'color'=>'#5cb85c',
          'note'=>'The most trustworthy sign in the zodiac. Guards those they love fiercely — but anxiety keeps them from fully trusting the wider world.',
          'scores'=>['Leadership'=>6,'Intelligence'=>7,'Sociability'=>7,'Creativity'=>5,'Reliability'=>10]],
    11=> ['animal'=>'Pig',     'icon'=>'🐖', 'rank'=>10, 'color'=>'#FF9F1C',
          'note'=>'Pure-hearted and generous to a fault. Finds beauty in everything and never keeps score — the most sincerely good-natured sign.',
          'scores'=>['Leadership'=>3,'Intelligence'=>5,'Sociability'=>9,'Creativity'=>7,'Reliability'=>9]],
];

// ── Mahabote Planets ──
// ══════════════════════════════════════════════════════════
// MAHABOTE BIRTH HOUSE — correct 2D lookup
// Step 1: Myanmar Year = Gregorian year − 639 (if before Apr 17) or − 638 (if Apr 17+)
// Step 2: Myanmar Year % 7 = remainder (0–6)
// Step 3: lookup [remainder][birth day of week 0=Sun…6=Sat]
// ══════════════════════════════════════════════════════════

// Lookup table: row = remainder (0-6), col = day of week (0=Sun … 6=Sat)
$mhb_table = [
//    Sun        Mon        Tue        Wed        Thu        Fri        Sat
    ['ပုတိ',    'သိုက်',  'မရဏ',    'အဓိပတိ', 'ရာဇ',    'အထွန်း', 'ဘင်္ဂ'],   // rem 0
    ['ဘင်္ဂ',  'ပုတိ',   'သိုက်',  'မရဏ',    'အဓိပတိ', 'ရာဇ',    'အထွန်း'], // rem 1
    ['အထွန်း','ဘင်္ဂ',  'ပုတိ',   'သိုက်',  'မရဏ',    'အဓိပတိ', 'ရာဇ'],    // rem 2
    ['ရာဇ',    'အထွန်း','ဘင်္ဂ',  'ပုတိ',   'သိုက်',  'မရဏ',    'အဓိပတိ'], // rem 3
    ['အဓိပတိ','ရာဇ',    'အထွန်း','ဘင်္ဂ',  'ပုတိ',   'သိုက်',  'မရဏ'],    // rem 4
    ['မရဏ',    'အဓိပတိ','ရာဇ',    'အထွန်း','ဘင်္ဂ',  'ပုတိ',   'သိုက်'],   // rem 5
    ['သိုက်',  'မရဏ',   'အဓိပတိ','ရာဇ',    'အထွန်း','ဘင်္ဂ',  'ပုတိ'],    // rem 6
];

// House data keyed by Burmese name
// Scores per ranking: ဘင်္ဂ=5(Worst), မရဏ=6, ပုတိ=6, သိုက်=8, ရာဇ=8, အထွန်း=9, အဓိပတိ=8(Best by traits)
// Trait scores averaged from Grok AI + Gemini AI results
$mhb_houses_birth = [
    'ဘင်္ဂ' => [
        'name'=>'Binga',    'mm'=>'ဘင်္ဂ',    'en_full'=>'The Breaker',
        'symbol'=>'⚡',     'color'=>'#d97706', 'score'=>5,
        'scores'=>['Leadership'=>5,'Intelligence'=>6,'Sociability'=>5,'Creativity'=>6,'Reliability'=>5],
        'trait'=>'Kind-hearted but stubborn and overly outspoken, which can make others dislike them. Self-reliant and hardworking. Outwardly calm, but internally carries a lot of mental stress. Career tends to fluctuate quickly. Often not recognised for their true abilities.',
        'advice'=>'Control your outspokenness. Your hard work deserves recognition — be strategic about how you present your achievements.',
    ],
    'မရဏ' => [
        'name'=>'Marana',   'mm'=>'မရဏ',     'en_full'=>'The Transformer',
        'symbol'=>'🌑',     'color'=>'#6D6875', 'score'=>6,
        'scores'=>['Leadership'=>5,'Intelligence'=>7,'Sociability'=>6,'Creativity'=>6,'Reliability'=>5],
        'trait'=>'Emotions swing between extremes. Very patient, but when angered can be quite intense. Work begins with great enthusiasm but motivation fades over time. Life is full of ups and downs, and they tend to endure hardships alone without showing others.',
        'advice'=>'Find emotional balance. Share your burdens — you do not have to carry everything alone. Build consistent habits to sustain your motivation.',
    ],
    'အဓိပတိ' => [
        'name'=>'Adipati',  'mm'=>'အဓိပတိ',  'en_full'=>'The Supreme Lord',
        'symbol'=>'⭐',     'color'=>'#8338EC', 'score'=>8,
        'scores'=>['Leadership'=>10,'Intelligence'=>9,'Sociability'=>8,'Creativity'=>8,'Reliability'=>9],
        'trait'=>'Natural leader who dislikes being subordinate. Intelligent, but tends to daydream more than take action. Has artistic talent and is a gifted speaker. However, often struggles financially and may have difficulties managing employees or subordinates.',
        'advice'=>'Turn your big ideas into concrete plans. Leadership flows naturally — pair it with disciplined execution and financial planning.',
    ],
    'အထွန်း' => [
        'name'=>'Athoung',  'mm'=>'အထွန်း',  'en_full'=>'The Flourisher',
        'symbol'=>'🌱',     'color'=>'#5cb85c', 'score'=>9,
        'scores'=>['Leadership'=>9,'Intelligence'=>9,'Sociability'=>9,'Creativity'=>9,'Reliability'=>8],
        'trait'=>'Very calm and disciplined, with a strong love for their work. Puts genuine effort into everything, but others often take advantage of their success or benefits. Tends to have poor luck with friendships and may worry a lot about their children.',
        'advice'=>'Set clear boundaries so others do not exploit your efforts. Your discipline is your greatest asset — protect it.',
    ],
    'သိုက်' => [
        'name'=>'Thoike',   'mm'=>'သိုက်',   'en_full'=>'The Nester',
        'symbol'=>'🏠',     'color'=>'#48CAE4', 'score'=>8,
        'scores'=>['Leadership'=>7,'Intelligence'=>8,'Sociability'=>8,'Creativity'=>8,'Reliability'=>9],
        'trait'=>'Does better with practical action than imagination. Despite working hard, may not receive proportional rewards. Financial difficulties are common. Has many friends but struggles to maintain long-term relationships due to frequent mood changes.',
        'advice'=>'Focus on consistent, measurable work. Build financial discipline early. Invest in depth over breadth in relationships.',
    ],
    'ရာဇ' => [
        'name'=>'Raja',     'mm'=>'ရာဇ',     'en_full'=>'The Noble',
        'symbol'=>'👑',     'color'=>'#FF9F1C', 'score'=>8,
        'scores'=>['Leadership'=>9,'Intelligence'=>8,'Sociability'=>8,'Creativity'=>7,'Reliability'=>9],
        'trait'=>'Kind-hearted and able to endure hardship. However, tends to procrastinate. Being overly considerate can sometimes cause problems. Dislikes injustice. Should be cautious in marriage. Both happiness and suffering are often carried on their own shoulders.',
        'advice'=>'Act on your decisions promptly — your considerate nature is a gift, but do not let it delay necessary action. Choose a partner carefully.',
    ],
    'ပုတိ' => [
        'name'=>'Puti',     'mm'=>'ပုတိ',     'en_full'=>'The Resilient',
        'symbol'=>'🔥',     'color'=>'#E63946', 'score'=>6,
        'scores'=>['Leadership'=>6,'Intelligence'=>7,'Sociability'=>6,'Creativity'=>7,'Reliability'=>5],
        'trait'=>'Practical and disciplined, but personality can be easily influenced by environment. Life tends to involve struggle. When angry, can become extremely temperamental. Has strong potential for success and recognition in the later stage of life.',
        'advice'=>'Guard your environment carefully — surround yourself with positive influences. Your best years often come later. Manage your temper; it is your greatest obstacle.',
    ],
];

// ══════════════════════════════════════════════════════
// THARME (ရှားမီး) — Yearly Fortune Wheel
// 8-position circle clockwise from N=Saturday
// circle[position] = house_id; house_id → house data
// Formula: pos = (start_index + age % 8) % 8
// ══════════════════════════════════════════════════════

// Circle order verified against lookup table image and all 3 worked examples
$tharme_circle = [3, 1, 2, 5, 0, 6, 4, 8];

// Start positions derived from image table (R1 column back-calculated)
// Verified: Tue age62 rem6 → (7+6)%8=5→Thu-O ✓ | Sat age52 rem4 → (3+4)%8=7→Shar-Mee ✓ | Rahu age32 rem0 → (6+0)%8=6→Li-Kaw ✓
$tharme_start = [
    0 => 0,  // Sun
    1 => 1,  // Mon
    2 => 7,  // Tue
    3 => 5,  // Wed AM
    4 => 2,  // Thu
    5 => 4,  // Fri
    6 => 3,  // Sat
    'wed_pm' => 6,  // Rahu
];

// 4 pairs (direct + reversed = 8 houses). Circle = [3,1,2,5,0,6,4,8]
// House IDs: 0=Htwe-Saw, 1=O-Thu, 2=Kaw-Li, 3=Saw-Htwe, 4=Li-Kaw, 5=Mee-Shar, 6=Thu-O, 8=Shar-Mee
$tharme_houses = [
    0 => [
        'mm'      => 'ထွေစော',
        'en'      => 'Htwe-Saw',
        'en_full' => 'The Prosperous (Reversed)',
        'direct'  => false,
        'pair'    => 'စောထွေ / ထွေစော',
        'pair_en' => 'Saw-Htwe / Htwe-Saw',
        'score'   => 7,
        'color'   => '#48CAE4',
        'symbol'  => '🌤️',
        'meaning' => 'Similar success as Saw-Htwe but with weakened intensity. Love, social life, and career advancement are favored but with less certainty. Financial gains are possible but modest.',
        'advice'  => 'Good for relationships and career moves. Expect positive outcomes with slightly reduced impact.',
    ],
    1 => [
        'mm'      => 'အိုသူ',
        'en'      => 'O-Thu',
        'en_full' => 'The Challenger (Reversed)',
        'direct'  => false,
        'pair'    => 'သူအို / အိုသူ',
        'pair_en' => 'Thu-O / O-Thu',
        'score'   => 3,
        'color'   => '#f0ad4e',
        'symbol'  => '🌬️',
        'meaning' => 'Similar instability as Thu-O but with weakened intensity. Challenges are present but more manageable. Financial loss and travel disruption are possible but less severe.',
        'advice'  => 'Exercise caution but do not be overly fearful. Effects are less intense — stay flexible and adapt.',
    ],
    2 => [
        'mm'      => 'ကောလီ',
        'en'      => 'Kaw-Li',
        'en_full' => 'The Great Fortune (Direct)',
        'direct'  => true,
        'pair'    => 'ကောလီ / လီကော',
        'pair_en' => 'Kaw-Li / Li-Kaw',
        'score'   => 10,
        'color'   => '#1a7a1a',
        'symbol'  => '👑',
        'meaning' => 'A year of great fortune and material abundance. Highly favorable for business expansion, investments, and education. You may gain assets, wealth, or influential mentors.',
        'advice'  => 'Pursue your biggest goals boldly. Avoid excessive indulgence to maintain this positive momentum.',
    ],
    3 => [
        'mm'      => 'စောထွေ',
        'en'      => 'Saw-Htwe',
        'en_full' => 'The Prosperous (Direct)',
        'direct'  => true,
        'pair'    => 'စောထွေ / ထွေစော',
        'pair_en' => 'Saw-Htwe / Htwe-Saw',
        'score'   => 8,
        'color'   => '#28a745',
        'symbol'  => '🌸',
        'meaning' => 'A year of success and prosperity. Excellent for love, social life, and career advancement. Expect new relationships, promotions, and financial gains, especially from the southeast.',
        'advice'  => 'Act boldly in love and career. New connections, promotions, and financial gains are on their way.',
    ],
    4 => [
        'mm'      => 'လီကော',
        'en'      => 'Li-Kaw',
        'en_full' => 'The Great Fortune (Reversed)',
        'direct'  => false,
        'pair'    => 'ကောလီ / လီကော',
        'pair_en' => 'Kaw-Li / Li-Kaw',
        'score'   => 9,
        'color'   => '#FF9F1C',
        'symbol'  => '🌟',
        'meaning' => 'Similar fortune as Kaw-Li but with moderately reduced intensity. Still highly favorable for business, investments, and education. Wealth and mentors may come, but with less certainty.',
        'advice'  => 'Act on opportunities. Effects are slightly weaker than Kaw-Li but still very positive — do not hesitate.',
    ],
    5 => [
        'mm'      => 'မီးရှား',
        'en'      => 'Mee-Shar',
        'en_full' => 'The High Risk (Reversed)',
        'direct'  => false,
        'pair'    => 'ရှားမီး / မီးရှား',
        'pair_en' => 'Shar-Mee / Mee-Shar',
        'score'   => 2,
        'color'   => '#d97706',
        'symbol'  => '⚡',
        'meaning' => 'Similar hardship as Shar-Mee but with reduced intensity. Caution is still needed in speech, behavior, and travel, but the worst outcomes are less likely.',
        'advice'  => 'Stay cautious in actions and speech. Avoid gambling and legal disputes — the risk is real but manageable.',
    ],
    6 => [
        'mm'      => 'သူအို',
        'en'      => 'Thu-O',
        'en_full' => 'The Challenger (Direct)',
        'direct'  => true,
        'pair'    => 'သူအို / အိုသူ',
        'pair_en' => 'Thu-O / O-Thu',
        'score'   => 4,
        'color'   => '#c9302c',
        'symbol'  => '🌪️',
        'meaning' => 'A challenging year marked by instability and frequent travel. Problems may arise unexpectedly. Avoid gambling, as financial loss is likely. Travel may help mitigate the negative energy.',
        'advice'  => 'Stay grounded. Avoid gambling and risky financial moves. Travel may help shift the energy.',
    ],
    8 => [
        'mm'      => 'ရှားမီး',
        'en'      => 'Shar-Mee',
        'en_full' => 'The High Risk (Direct)',
        'direct'  => true,
        'pair'    => 'ရှားမီး / မီးရှား',
        'pair_en' => 'Shar-Mee / Mee-Shar',
        'score'   => 1,
        'color'   => '#E63946',
        'symbol'  => '⚠️',
        'meaning' => 'A year of high risk and potential hardship. Exercise extreme caution in speech, behavior, and travel. Avoid legal disputes, gambling, and risky ventures — risk of loss, injury, or severe personal distress.',
        'advice'  => 'Focus entirely on caution. Avoid confrontation, legal disputes, and gambling. Protect yourself at all costs.',
    ],
];

// ── Helper Functions ──
function daysSince($birthDate) {
    $b = new DateTime($birthDate);
    $t = new DateTime('today');
    return (int)$b->diff($t)->days;
}

function bioSin($days, $period) {
    return sin(2 * M_PI * $days / $period);
}

function sinToScore($v) {
    // -1..1  →  1..10
    return round(($v + 1) / 2 * 9 + 1);
}

function scoreStatus($s) {
    if ($s >= 8) return ['label'=>'DO',     'badge'=>'badge-do'];
    if ($s >= 6) return ['label'=>'OK',     'badge'=>'badge-ok'];
    if ($s >= 4) return ['label'=>'CAUTION','badge'=>'badge-caution'];
    return             ['label'=>"DON'T",   'badge'=>'badge-dont'];
}
function barWidth($s) {
    // minimum 3% so score 0 (ပျက်) still shows a sliver of red bar
    return max(3, ($s / 10) * 100);
}

// ── Myanmar Calendar (Yan Naing Kyaw algorithm) ──
function mmcal_rg2j($gy,$gm,$gd){
    $a=floor((14-$gm)/12); $y=$gy+4800-$a; $m=$gm+12*$a-3;
    return (int)($gd+floor((153*$m+2)/5)+365*$y+floor($y/4)-floor($y/100)+floor($y/400)-32045);
}
function mmcal_jdn2g($jdn){
    $l=$jdn+68569; $n=(int)floor(4*$l/146097);
    $l-=(int)floor((146097*$n+3)/4); $i=(int)floor(4000*($l+1)/1461001);
    $l-=(int)floor(1461*$i/4)-31; $j=(int)floor(80*$l/2447);
    $d=(int)($l-floor(2447*$j/80)); $l=(int)floor($j/11);
    return ['y'=>(int)(100*($n-49)+$i+$l),'m'=>(int)($j+2-12*$l),'d'=>$d];
}
function mmcal_chkWatat($my){
    $SY=1577917828/4320000; $LM=1577917828/53433336; $MO=1954168.050623;
    $ed=fmod($SY*($my+3739),$LM); $NM=8;
    $TA=($SY/12-$LM)*(12-$NM); if($ed<$TA) $ed+=$LM;
    $TW=$LM-($SY/12-$LM)*$NM; $watat=($ed>=$TW)?1:0;
    $WO=-4.0/$NM;
    if($my==1377) $WO+=1;
    if($my==1344){$watat=1;} elseif($my==1345){$watat=0;}
    $w2fm=(int)round($SY*$my+$MO-$ed+4.5*$LM+$WO);
    return ['watat'=>$watat,'fm'=>$w2fm];
}
function mmcal_chkMy($my){
    $yd=0; $y2=mmcal_chkWatat($my);
    do{ $yd++; $y1=mmcal_chkWatat($my-$yd); }while($y1['watat']==0 && $yd<20);
    $watat=$y2['watat']; $myt=$watat; $bw=0;
    if($watat){ $nd=($y2['fm']-$y1['fm'])%354; if($nd<0)$nd+=354; $bw=(int)floor($nd/31); $myt=$bw+1; }
    return ['myt'=>$myt,'watat'=>$watat,'bw'=>$bw,'tg1'=>(int)($y1['fm']+354*$yd-102)];
}
function mmcal_j2m($jd){
    $SY=1577917828/4320000; $MO=1954168.050623;
    $jdn=(int)round($jd); $my=(int)floor(($jdn-0.5-$MO)/$SY);
    $yo=mmcal_chkMy($my);
    $dd=$jdn-$yo['tg1']+1; $myl=354+$yo['watat']*30+$yo['bw'];
    $mmt=(int)floor(($dd-1)/$myl); $dd-=$mmt*$myl;
    $t=(int)floor($myl/($dd+266));
    $s=29.5+$t*$yo['bw']/5; $c=117+$t*$yo['bw']*14/5;
    $dd+=$t*266-(1-$t)*($myl-266);
    $mm=(int)floor(($dd+$c)/$s);
    $md=(int)($dd-floor($s*$mm-$c-0.1));
    $mm=$mm%16; $mm-=12*(int)floor($mm/13);
    $mml=30-$mm%2; if($mm==3)$mml+=$yo['bw'];
    $ms=(int)(floor(($md+1)/16)+floor($md/16)+floor($md/$mml));
    $d=(int)($md-15*floor($md/16)); $wd=($jdn+2)%7;
    return ['my'=>$my,'myt'=>$yo['myt'],'watat'=>$yo['watat'],'bw'=>$yo['bw'],
            'myl'=>$myl,'mm'=>(int)$mm,'mmt'=>$mmt,'mml'=>(int)$mml,
            'md'=>(int)$md,'ms'=>$ms,'d'=>$d,'wd'=>$wd];
}
function gregorianToMyanmar($gY,$gM,$gD){
    if($gY<700||$gM<1||$gM>12||$gD<1||$gD>31) return null;
    $jd  = mmcal_rg2j($gY,$gM,$gD);
    $m   = mmcal_j2m($jd);
    $mmMonths=[
        0=>['mm'=>'ပဝါဆို','en'=>'First Waso'],
        1=>['mm'=>'တန်ခူး','en'=>'Tagu'],      2=>['mm'=>'ကဆုန်','en'=>'Kason'],
        3=>['mm'=>'နယုန်','en'=>'Nayon'],       4=>['mm'=>'ဝါဆို','en'=>'Waso'],
        5=>['mm'=>'ဝါခေါင်','en'=>'Wagaung'],  6=>['mm'=>'တော်သလင်း','en'=>'Tawthalin'],
        7=>['mm'=>'သီတင်းကျွတ်','en'=>'Thadingyut'],
        8=>['mm'=>'တန်ဆောင်မုန်း','en'=>'Tazaungmon'],
        9=>['mm'=>'နတ်တော်','en'=>'Natdaw'],   10=>['mm'=>'ပြာသိုဒ်','en'=>'Pyatho'],
        11=>['mm'=>'တပေါင်း','en'=>'Tabodwe'], 12=>['mm'=>'တသာ','en'=>'Tabaung'],
    ];
    $mn  = $mmMonths[$m['mm']] ?? ['mm'=>'?','en'=>'?'];
    $mml      = $m['mml'];
    $md       = $m['md'];
    $firstJdn = $jd - $md + 1;
    $calDays  = [];
    for($i = 0; $i < $mml; $i++){
        $djdn  = $firstJdn + $i;
        $dayMd = $i + 1;                          // Myanmar day 1..mml
        $greg  = mmcal_jdn2g($djdn);
        $wd    = ($djdn + 2) % 7;                 // 0=Sat 1=Sun … 6=Fri
        $col   = ($wd == 0) ? 6 : $wd - 1;       // Sun=0 … Sat=6
        // Moon phase directly from day number — no extra mmcal_j2m call
        if ($dayMd < 15)       { $ms=0; $d=$dayMd; }      // waxing
        elseif ($dayMd == 15)  { $ms=1; $d=15; }           // full moon
        elseif ($dayMd < $mml) { $ms=2; $d=$dayMd-15; }   // waning
        else                   { $ms=3; $d=$dayMd-15; }    // new moon
        $calDays[] = [
            'md'=>$dayMd, 'd'=>$d, 'ms'=>$ms,
            'col'=>$col, 'greg_m'=>$greg['m'], 'greg_d'=>$greg['d'],
            'is_birth'=>($djdn == $jd),
        ];
    }
    return [
        'year'     =>(int)$m['my'],
        'month'    =>$mn['mm'].($m['mmt']?' (ဆောင်းပိုင်း)':''),
        'month_en' =>$mn['en'],
        'month_num'=>(int)$m['mm'],
        'day'      =>(int)$md,
        'd'        =>(int)$m['d'],
        'ms'       =>$m['ms'],
        'mml'      =>$mml,
        'watat'    =>$m['watat'],
        'cal_days' =>$calDays,
        'first_col'=>$calDays[0]['col'],
    ];
}
function badge($s) {
    $st = scoreStatus($s);
    return '<span class="status-badge '.$st['badge'].'">'.$st['label'].'</span>';
}

// ── ရက်ရာဇာ Yatyaza (wd: 0=Sat,1=Sun,2=Mon,3=Tue,4=Wed,5=Thu,6=Fri) ──
function mmcal_yatyaza($mm, $wd) {
    $m1  = $mm % 4;
    $wd1 = (int)floor($m1 / 2) + 4;
    $wd2 = ((1 - (int)floor($m1 / 2)) + $m1 % 2) * (1 + 2 * ($m1 % 2));
    return ($wd == $wd1 || $wd == $wd2) ? 1 : 0;
}
// ── ပြဿဒါး Pyathada ──
function mmcal_pyathada($mm, $wd) {
    $m1  = $mm % 4;
    $wda = [1, 3, 3, 0, 2, 1, 2]; // indexed 0=Sat…6=Fri
    $p   = 0;
    if ($m1 == 0 && $wd == 4) $p = 2; // afternoon pyathada
    elseif ($m1 == $wda[$wd]) $p = 1;
    return $p;
}
// ── Day Guide: Yatyaza / Pyathada / Tithi for any Gregorian date ──
function getDayGuide($gY, $gM, $gD) {
    try {
        $jdn = mmcal_rg2j($gY, $gM, $gD);
        $m   = mmcal_j2m($jdn);
        $mm  = $m['mm']; $wd = $m['wd']; $md = $m['md']; $ms = $m['ms']; $d = $m['d'];
        $mmEn = [0=>'First Waso',1=>'Tagu',2=>'Kason',3=>'Nayon',4=>'Waso',
                 5=>'Wagaung',6=>'Tawthalin',7=>'Thadingyut',8=>'Tazaungmon',
                 9=>'Natdaw',10=>'Pyatho',11=>'Tabodwe',12=>'Tabaung'];
        $mmMM = [0=>'ပဝါဆို',1=>'တန်ခူး',2=>'ကဆုန်',3=>'နယုန်',4=>'ဝါဆို',
                 5=>'ဝါခေါင်',6=>'တော်သလင်း',7=>'သီတင်းကျွတ်',8=>'တန်ဆောင်မုန်း',
                 9=>'နတ်တော်',10=>'ပြာသိုဒ်',11=>'တပေါင်း',12=>'တသာ'];
        $wdEn   = ['Saturday','Sunday','Monday','Tuesday','Wednesday','Thursday','Friday'];
        $phIco  = ['🌒','🌕','🌘','🌑'];
        $phEn   = ['LaSan','LaPyi','LaSout','LaKway'];
        $phMM   = ['လဆန်း','လပြည့်','လဆုတ်','လကွယ်'];
        $yatyaza  = mmcal_yatyaza($mm, $wd);
        $pyathada = mmcal_pyathada($mm, $wd);
        $dayLabel   = $phEn[$ms] . ($ms==1||$ms==3 ? '' : ' '.$d);
        $dayLabelMM = $phMM[$ms] . ($ms==1||$ms==3 ? '' : ' '.$d);
        $isSabbath = ($md == 8 || $ms == 1 || $md == 23 || $ms == 3);
        $mmReal = ($mm == 0) ? 4 : $mm;
        $isLent = in_array($mmReal, [4, 5, 6]);
        return [
            'my'=>$m['my'], 'mm'=>$mm, 'md'=>$md, 'ms'=>$ms, 'd'=>$d, 'wd'=>$wd,
            'month_en'=>$mmEn[$mm]??'?', 'month_mm'=>$mmMM[$mm]??'?',
            'day_en'=>$wdEn[$wd], 'moon_icon'=>$phIco[$ms],
            'day_label'=>$dayLabel, 'day_label_mm'=>$dayLabelMM,
            'yatyaza'=>$yatyaza, 'pyathada'=>$pyathada,
            'is_sabbath'=>$isSabbath, 'is_lent'=>$isLent,
        ];
    } catch (Throwable $e) { return null; }
}

// ── Calculate today's guide + checked date ──
$todayGuide   = getDayGuide((int)date('Y'), (int)date('n'), (int)date('j'));
$checkedGuide = null;
$checkedDate  = '';
if (!empty($_GET['checkdate'])) {
    $checkedDate = trim($_GET['checkdate']);
    // Validate date format to prevent unexpected input
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkedDate) || !strtotime($checkedDate)) { $checkedDate = date('Y-m-d'); }
    try {
        $cd = new DateTime($checkedDate);
        $checkedGuide = getDayGuide((int)$cd->format('Y'), (int)$cd->format('n'), (int)$cd->format('j'));
    } catch (Throwable $e) { $checkedDate = ''; }
}

// ══════════════════════════════════════════════════════
// PROCESS FORM
// ══════════════════════════════════════════════════════
$result = null;
$error  = '';

$_FORM = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;

if (!empty($_FORM['name']) && !empty($_FORM['birthdate'])) {
    $name      = substr(trim($_FORM['name']), 0, 100);
    $birthdate = trim($_FORM['birthdate']);
    $birthtime = substr(trim($_FORM['birthtime'] ?? ''), 0, 5);

    try {
        $bd   = new DateTime($birthdate);
            $today= new DateTime('today');
            if ($bd >= $today) throw new Exception('Birth date must be in the past.');

            $dow  = (int)$bd->format('w');   // 0=Sun … 6=Sat
            $age  = (int)$bd->diff($today)->y;
            $days = daysSince($birthdate);

            // ── Biorhythm (today + 30 days for chart) ──
            $bio_periods = [
                'Physical'     => 23,
                'Emotional'    => 28,
                'Intellectual' => 33,
                'Spiritual'    => 53,
                'Awareness'    => 48,
                'Aesthetic'    => 43,
                'Intuition'    => 38,
            ];
            $bio_colors = [
                'Physical'    =>'#FF6B35','Emotional'  =>'#4FC3F7',
                'Intellectual'=>'#8338EC','Spiritual'  =>'#FF9F1C',
                'Awareness'   =>'#48CAE4','Aesthetic'  =>'#E63946',
                'Intuition'   =>'#5cb85c',
            ];

            // Today's biorhythm scores
            $bio_today = [];
            foreach ($bio_periods as $name_b => $period) {
                $v = bioSin($days, $period);
                $bio_today[$name_b] = [
                    'sin'   => $v,
                    'score' => sinToScore($v),
                    'color' => $bio_colors[$name_b],
                ];
            }

            // Chart data: 33-day window (16 past + today + 16 future), daily points
            // todayIdx = 16
            $chart_labels = [];
            $chart_datasets = [];
            foreach ($bio_periods as $n => $_) { $chart_datasets[$n] = []; }
            for ($i = -16; $i <= 16; $i++) {
                $d_i = $days + $i;
                $dt  = clone $today;
                if ($i < 0)     $dt->modify($i . ' days');
                elseif ($i > 0) $dt->modify('+' . $i . ' days');
                $chart_labels[] = ($i === 0) ? '★ Today' : $dt->format('M d');
                foreach ($bio_periods as $n => $p) {
                    $chart_datasets[$n][] = round(bioSin($d_i, $p) * 1000) / 1000;
                }
            }

            // ── Zanaka Taya ──
            // base = birth day number (Sun=1 … Sat=7)
            // Each taya lasts 2 years. Cycle = 14 years.
            global $taya_list, $day_base_num;
            $base        = $day_base_num[$dow];
            $adjusted    = ($age - $base) % 14;
            if ($adjusted < 0) $adjusted += 14;
            $taya_index  = (int)floor($adjusted / 2) % 7;
            $taya        = $taya_list[$taya_index];

            // Years remaining in this taya (each taya = 2 years)
            $years_in_taya   = $adjusted % 2; // 0 = first year, 1 = second year
            $years_remaining = 2 - $years_in_taya;

            // Next taya
            $next_taya    = $taya_list[($taya_index + 1) % 7];

            // ── Myanmar Zodiac ──
            global $myanmar_zodiac;
            if ($dow === 3 && $birthtime) {
                $hour = (int)(new DateTime($birthdate . ' ' . $birthtime))->format('H');
                $zodiac = ($hour >= 12) ? $myanmar_zodiac['wed_pm'] : $myanmar_zodiac[3];
            } else {
                $zodiac = $myanmar_zodiac[$dow];
            }
            $days_of_week = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
            $zodiac['birth_day'] = $days_of_week[$dow];

            // Myanmar zodiac matrix index (0=Garuda…7=Naga) for compatibility
            $zoo_dow_map = [0=>0, 1=>1, 2=>2, 3=>3, 4=>5, 5=>6, 6=>7];
            $zoo_matrix_idx = $zoo_dow_map[$dow];
            if ($dow === 3 && $birthtime) {
                $hz = (int)(new DateTime($birthdate . ' ' . $birthtime))->format('H');
                if ($hz >= 12) $zoo_matrix_idx = 4; // Tuskless Elephant (Wed PM)
            }

            // ── Mahabote: 2D lookup ──
            global $mhb_table, $mhb_houses_birth;
            $by_year  = (int)$bd->format('Y');
            $by_month = (int)$bd->format('n');
            $by_day   = (int)$bd->format('j');

            // ── Myanmar Calendar Date (birth) ── (must be after $by_year/month/day are set)
            try {
                $mm_date = gregorianToMyanmar($by_year, $by_month, $by_day);
            } catch (Throwable $e) {
                $mm_date = null;
            }
            // Myanmar Year: New Year ≈ Apr 17
            $me_year  = ($by_month < 4 || ($by_month == 4 && $by_day < 17))
                        ? $by_year - 639 : $by_year - 638;
            $me_rem   = (($me_year % 7) + 7) % 7;   // 0–6
            $mhb_name = $mhb_table[$me_rem][$dow];   // $dow = 0=Sun…6=Sat
            $mahabote = $mhb_houses_birth[$mhb_name];
            $mahabote['me_year'] = $me_year;
            $mahabote['me_rem']  = $me_rem;
            $mahabote['mhb_mm']  = $mhb_name;

            // ── Nakat (လူ/ဘီလူး/နတ် နက္ခတ်) ──
            // Myanmar Year % 3: 1=နတ်, 2=လူ, 0=ဘီလူး
            $nakat_rem = (($me_year % 3) + 3) % 3;
            // Nakhat modifiers: averaged from Grok AI + Gemini AI results (applied on top of Mahabote base scores)
            $nakat_data = [
                1 => ['mm'=>'နတ်',   'en'=>'Nat',  'rem'=>1,
                      'trait'=>'နူးညံ့ပျော့ပျောင်း၊ အချော့ကြိုက်၊ အနုအရွမြတ်နိုး — Kind, charming and gentle in nature. Easily wins people\'s hearts.',
                      'mod'=>['Leadership'=>+1,'Intelligence'=>+1,'Sociability'=>0,'Creativity'=>+1,'Reliability'=>+1]],
                2 => ['mm'=>'လူ',    'en'=>'Lu',   'rem'=>2,
                      'trait'=>'အကျိုးအကြောင်း ဆင်ခြင်တတ်၊ စဉ်းစားဉာဏ်ရှိ — Logical, wise and thoughtful before making decisions.',
                      'mod'=>['Leadership'=>0,'Intelligence'=>0,'Sociability'=>0,'Creativity'=>0,'Reliability'=>0]],
                0 => ['mm'=>'ဘီလူး', 'en'=>'Ogre', 'rem'=>0,
                      'trait'=>'ပြတ်သား၊ ခေါင်းဆောင်မှုရှိ၊ စိတ်အားထက်သန် — Decisive, strong-willed and natural leader.',
                      'mod'=>['Leadership'=>+1,'Intelligence'=>-1,'Sociability'=>-2,'Creativity'=>-1,'Reliability'=>-1]],
            ];
            $nakat = $nakat_data[$nakat_rem];
            $nakat['me_year'] = $me_year;

            // ── Chinese Zodiac ──
            global $chinese_zodiac;
            $cz_year  = (int)$bd->format('Y');
            $cz_index = (($cz_year - 1900) % 12 + 12) % 12;
            $cz = $chinese_zodiac[$cz_index];
            $cz['year']  = $cz_year;
            $cz['index'] = $cz_index;
            $cz_avg_raw = array_sum($cz['scores']) / count($cz['scores']);
            $cz['avg']     = round($cz_avg_raw, 1);
            $cz['avg_int'] = (int)round($cz_avg_raw);

            // ── Tharme (ရှားမီး) Yearly Fortune ──
            global $tharme_circle, $tharme_start, $tharme_houses;
            // Determine start index: Wed PM births use index 5
            $th_is_wed_pm = ($dow === 3 && $birthtime &&
                (int)(new DateTime($birthdate . ' ' . $birthtime))->format('H') >= 12);
            $th_start = $th_is_wed_pm ? $tharme_start['wed_pm'] : $tharme_start[$dow];
            $th_rem   = $age % 8;
            $th_idx   = ($th_start + $th_rem) % 8;
            $th_house_id = $tharme_circle[$th_idx];
            $tharme_result = $tharme_houses[$th_house_id];
            $tharme_result['house_id']   = $th_house_id;
            $tharme_result['start_index']= $th_start;
            $tharme_result['rem']        = $th_rem;

            // Build multi-year forecast (current age-2 … age+6)
            $tharme_forecast = [];
            for ($yr_offset = -2; $yr_offset <= 5; $yr_offset++) {
                $yr_age = $age + $yr_offset;
                if ($yr_age < 0) continue;
                $yr_rem  = $yr_age % 8;
                $yr_idx  = ($th_start + $yr_rem) % 8;
                $yr_hid  = $tharme_circle[$yr_idx];
                $yr_h    = $tharme_houses[$yr_hid];
                $tharme_forecast[] = [
                    'age'     => $yr_age,
                    'is_now'  => ($yr_offset === 0),
                    'house_id'=> $yr_hid,
                    'mm'      => $yr_h['mm'],
                    'en'      => $yr_h['en'],
                    'score'   => $yr_h['score'],
                    'color'   => $yr_h['color'],
                    'symbol'  => $yr_h['symbol'],
                ];
            }

            // ── Overall Score ──
            $all_scores = array_column($bio_today, 'score');
            $all_scores[] = $taya['score'];
            $all_scores[] = $mahabote['score'];
            $all_scores[] = $tharme_result['score'];
            $all_scores[] = $cz['avg_int'];
            $overall_sum   = array_sum($all_scores);
            $overall_count = count($all_scores);
            $overall = round($overall_sum / $overall_count);

            $result = compact(
                'name','birthdate','birthtime','age','dow','days',
                'bio_today','chart_labels','chart_datasets','bio_colors',
                'taya','next_taya','years_remaining','zodiac','mm_date','mahabote','nakat',
                'tharme_result','tharme_forecast','cz','zoo_matrix_idx','overall',
                'overall_sum','overall_count'
            );

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$day_names = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];

function can_web_share() {
    // Web Share API is available on mobile browsers; we'll let JS handle it dynamically
    return false; // JS will add the button if supported
}

// ── Famous Birthdays — full 365-day coverage ──
$famous_birthdays = [
    // ── JANUARY ──
    '01-01'=>[['name'=>'J.D. Salinger','note'=>'Author of The Catcher in the Rye','flag'=>'🇺🇸'],['name'=>'Paul Revere','note'=>'American Revolution Hero','flag'=>'🇺🇸']],
    '01-02'=>[['name'=>'Isaac Asimov','note'=>'Sci-Fi Legend · Author of Foundation','flag'=>'🇺🇸'],['name'=>'Cuba Gooding Jr.','note'=>'Oscar-winning Actor','flag'=>'🇺🇸']],
    '01-03'=>[['name'=>'J.R.R. Tolkien','note'=>'Author of The Lord of the Rings','flag'=>'🇬🇧'],['name'=>'Mel Gibson','note'=>'Oscar-winning Actor & Director','flag'=>'🇦🇺']],
    '01-04'=>[['name'=>'Louis Braille','note'=>'Inventor of the Braille System for the Blind','flag'=>'🇫🇷']],
    '01-05'=>[['name'=>'Diane Keaton','note'=>'Oscar-winning Actress','flag'=>'🇺🇸'],['name'=>'Bradley Cooper','note'=>'Actor & Director · A Star Is Born','flag'=>'🇺🇸']],
    '01-06'=>[['name'=>'Rowan Atkinson','note'=>'Mr. Bean & Blackadder Comedy Legend','flag'=>'🇬🇧']],
    '01-07'=>[['name'=>'Nicolas Cage','note'=>'Oscar-winning Hollywood Actor','flag'=>'🇺🇸'],['name'=>'Lewis Hamilton','note'=>'7-Time Formula One World Champion','flag'=>'🇬🇧']],
    '01-08'=>[['name'=>'Elvis Presley','note'=>'King of Rock & Roll','flag'=>'🇺🇸'],['name'=>'Stephen Hawking','note'=>'Theoretical Physicist · A Brief History of Time','flag'=>'🇬🇧']],
    '01-09'=>[['name'=>'Simone de Beauvoir','note'=>'Feminist Philosopher & Author','flag'=>'🇫🇷'],['name'=>'Jimmy Page','note'=>'Led Zeppelin Guitar God','flag'=>'🇬🇧']],
    '01-10'=>[['name'=>'Rod Stewart','note'=>'Rock & Pop Music Legend','flag'=>'🇬🇧']],
    '01-11'=>[['name'=>'Alexander Hamilton','note'=>'Founding Father & First US Treasury Secretary','flag'=>'🇺🇸'],['name'=>'Mary J. Blige','note'=>'Queen of Hip-Hop Soul','flag'=>'🇺🇸']],
    '01-12'=>[['name'=>'Jeff Bezos','note'=>'Founder of Amazon','flag'=>'🇺🇸'],['name'=>'Jack London','note'=>'Author of The Call of the Wild','flag'=>'🇺🇸']],
    '01-13'=>[['name'=>'Orlando Bloom','note'=>'Actor · Lord of the Rings & Pirates of the Caribbean','flag'=>'🇬🇧']],
    '01-14'=>[['name'=>'LL Cool J','note'=>'Hip-Hop Pioneer & Actor','flag'=>'🇺🇸']],
    '01-15'=>[['name'=>'Martin Luther King Jr.','note'=>'Civil Rights Leader & Nobel Peace Laureate','flag'=>'🇺🇸']],
    '01-16'=>[['name'=>'Kate Moss','note'=>'Supermodel & Fashion Icon','flag'=>'🇬🇧']],
    '01-17'=>[['name'=>'Muhammad Ali','note'=>'The Greatest — Heavyweight Boxing Champion','flag'=>'🇺🇸'],['name'=>'Benjamin Franklin','note'=>'Founding Father · Inventor of the Lightning Rod','flag'=>'🇺🇸']],
    '01-18'=>[['name'=>'Cary Grant','note'=>'Golden Age Hollywood Movie Star','flag'=>'🇬🇧'],['name'=>'Kevin Costner','note'=>'Oscar-winning Actor & Director','flag'=>'🇺🇸']],
    '01-19'=>[['name'=>'Edgar Allan Poe','note'=>'Master of Mystery & Horror Writing','flag'=>'🇺🇸'],['name'=>'Dolly Parton','note'=>'Country Music Icon & Philanthropist','flag'=>'🇺🇸']],
    '01-20'=>[['name'=>'Buzz Aldrin','note'=>'Apollo 11 Astronaut · Second Man on the Moon','flag'=>'🇺🇸']],
    '01-21'=>[['name'=>'Christian Dior','note'=>'Fashion House Founder · Haute Couture Pioneer','flag'=>'🇫🇷']],
    '01-22'=>[['name'=>'Lord Byron','note'=>'Romantic Poet · She Walks in Beauty','flag'=>'🇬🇧'],['name'=>'U Thant','note'=>'3rd UN Secretary-General · First from Asia','flag'=>'🇲🇲']],
    '01-23'=>[['name'=>'John Hancock','note'=>'First Signer of the US Declaration of Independence','flag'=>'🇺🇸']],
    '01-24'=>[['name'=>'Neil Diamond','note'=>'Sweet Caroline · Rock & Pop Songwriter Legend','flag'=>'🇺🇸']],
    '01-25'=>[['name'=>'Robert Burns','note'=>'Scotland\'s National Poet · Auld Lang Syne','flag'=>'🏴󠁧󠁢󠁳󠁣󠁴󠁿'],['name'=>'Virginia Woolf','note'=>'Modernist Author & Feminist Pioneer','flag'=>'🇬🇧']],
    '01-26'=>[['name'=>'Ellen DeGeneres','note'=>'Comedian, TV Host & LGBTQ+ Advocate','flag'=>'🇺🇸'],['name'=>'Wayne Gretzky','note'=>'The Great One · Hockey\'s Greatest Player','flag'=>'🇨🇦']],
    '01-27'=>[['name'=>'Wolfgang Amadeus Mozart','note'=>'Classical Music Genius · 41 Symphonies','flag'=>'🇦🇹']],
    '01-28'=>[['name'=>'Alan Alda','note'=>'Actor · M*A*S*H TV Legend','flag'=>'🇺🇸']],
    '01-29'=>[['name'=>'Oprah Winfrey','note'=>'Media Mogul & Philanthropist','flag'=>'🇺🇸'],['name'=>'Anton Chekhov','note'=>'Master of the Short Story & Playwright','flag'=>'🇷🇺']],
    '01-30'=>[['name'=>'Franklin D. Roosevelt','note'=>'32nd US President · New Deal Leader','flag'=>'🇺🇸']],
    '01-31'=>[['name'=>'Justin Timberlake','note'=>'Pop Star & Actor · NSYNC to Solo Superstar','flag'=>'🇺🇸'],['name'=>'Franz Schubert','note'=>'Romantic Era Composer · Unfinished Symphony','flag'=>'🇦🇹']],
    // ── FEBRUARY ──
    '02-01'=>[['name'=>'Harry Styles','note'=>'Pop Star · One Direction to Solo Superstar','flag'=>'🇬🇧'],['name'=>'Langston Hughes','note'=>'Harlem Renaissance Poet','flag'=>'🇺🇸']],
    '02-02'=>[['name'=>'Shakira','note'=>'Global Latin Pop Superstar · Hips Don\'t Lie','flag'=>'🇨🇴'],['name'=>'James Joyce','note'=>'Author of Ulysses · Literary Modernism Pioneer','flag'=>'🇮🇪']],
    '02-03'=>[['name'=>'Norman Rockwell','note'=>'Beloved American Illustrator & Painter','flag'=>'🇺🇸']],
    '02-04'=>[['name'=>'Rosa Parks','note'=>'Mother of the Civil Rights Movement','flag'=>'🇺🇸'],['name'=>'Alice Cooper','note'=>'Shock Rock Pioneer','flag'=>'🇺🇸']],
    '02-05'=>[['name'=>'Cristiano Ronaldo','note'=>'Football Superstar · 5-time Ballon d\'Or','flag'=>'🇵🇹'],['name'=>'Hank Aaron','note'=>'Baseball Home Run Legend','flag'=>'🇺🇸']],
    '02-06'=>[['name'=>'Bob Marley','note'=>'Reggae Legend · One Love','flag'=>'🇯🇲'],['name'=>'Ronald Reagan','note'=>'40th US President · Cold War Hero','flag'=>'🇺🇸']],
    '02-07'=>[['name'=>'Charles Dickens','note'=>'Author of Oliver Twist & A Tale of Two Cities','flag'=>'🇬🇧'],['name'=>'Chris Rock','note'=>'Stand-Up Comedy Legend','flag'=>'🇺🇸']],
    '02-08'=>[['name'=>'James Dean','note'=>'Rebel Without a Cause · Icon of Youth Rebellion','flag'=>'🇺🇸'],['name'=>'John Williams','note'=>'Film Score Composer · Star Wars, Jaws, Schindler\'s List','flag'=>'🇺🇸']],
    '02-09'=>[['name'=>'Michael B. Jordan','note'=>'Actor · Creed & Black Panther','flag'=>'🇺🇸'],['name'=>'Mia Farrow','note'=>'Actress & Humanitarian','flag'=>'🇺🇸']],
    '02-10'=>[['name'=>'Emma Roberts','note'=>'Actress & Scream Queens Star','flag'=>'🇺🇸'],['name'=>'Boris Pasternak','note'=>'Nobel Prize Author of Doctor Zhivago','flag'=>'🇷🇺']],
    '02-11'=>[['name'=>'Thomas Edison','note'=>'Inventor of the Light Bulb & Phonograph','flag'=>'🇺🇸']],
    '02-12'=>[['name'=>'Abraham Lincoln','note'=>'16th US President · Freed the Slaves','flag'=>'🇺🇸'],['name'=>'Charles Darwin','note'=>'Father of Evolution Theory · On the Origin of Species','flag'=>'🇬🇧']],
    '02-13'=>[['name'=>'General Aung San','note'=>'Father of the Myanmar Nation','flag'=>'🇲🇲'],['name'=>'Peter Gabriel','note'=>'Genesis Rock & World Music Pioneer','flag'=>'🇬🇧']],
    '02-14'=>[['name'=>'Frederick Douglass','note'=>'Abolitionist, Author & Civil Rights Pioneer','flag'=>'🇺🇸']],
    '02-15'=>[['name'=>'Galileo Galilei','note'=>'Father of Modern Astronomy & Physics','flag'=>'🇮🇹']],
    '02-16'=>[['name'=>'The Weeknd','note'=>'R&B/Pop Superstar · Blinding Lights','flag'=>'🇨🇦'],['name'=>'Ice-T','note'=>'Hip-Hop Pioneer & Actor','flag'=>'🇺🇸']],
    '02-17'=>[['name'=>'Ed Sheeran','note'=>'Shape of You · Global Singer-Songwriter','flag'=>'🇬🇧'],['name'=>'Michael Jordan','note'=>'Greatest Basketball Player · 6x NBA Champion','flag'=>'🇺🇸']],
    '02-18'=>[['name'=>'Toni Morrison','note'=>'Nobel Prize Author · Beloved','flag'=>'🇺🇸'],['name'=>'Molly Ringwald','note'=>'Actress · The Breakfast Club','flag'=>'🇺🇸']],
    '02-19'=>[['name'=>'Nicolaus Copernicus','note'=>'Heliocentrism Pioneer · Earth Orbits the Sun','flag'=>'🇵🇱']],
    '02-20'=>[['name'=>'Rihanna','note'=>'Pop & R&B Global Superstar · Fenty Beauty Founder','flag'=>'🇧🇧'],['name'=>'Kurt Cobain','note'=>'Nirvana Frontman · Grunge Music Icon','flag'=>'🇺🇸']],
    '02-21'=>[['name'=>'Nina Simone','note'=>'High Priestess of Soul — Jazz & Civil Rights Legend','flag'=>'🇺🇸']],
    '02-22'=>[['name'=>'George Washington','note'=>'First President of the United States','flag'=>'🇺🇸'],['name'=>'Steve Irwin','note'=>'The Crocodile Hunter · Wildlife Conservation Hero','flag'=>'🇦🇺']],
    '02-23'=>[['name'=>'W.E.B. Du Bois','note'=>'Civil Rights Pioneer & Sociologist','flag'=>'🇺🇸']],
    '02-24'=>[['name'=>'Steve Jobs','note'=>'Co-founder of Apple · Visionary Innovator','flag'=>'🇺🇸']],
    '02-25'=>[['name'=>'George Harrison','note'=>'The Quiet Beatle · Lead Guitarist of The Beatles','flag'=>'🇬🇧']],
    '02-26'=>[['name'=>'Victor Hugo','note'=>'Author of Les Misérables & The Hunchback of Notre-Dame','flag'=>'🇫🇷'],['name'=>'Johnny Cash','note'=>'Man in Black · Country Music Icon','flag'=>'🇺🇸']],
    '02-27'=>[['name'=>'Elizabeth Taylor','note'=>'Hollywood Legend & AIDS Activist','flag'=>'🇬🇧']],
    '02-28'=>[['name'=>'Karim Benzema','note'=>'Football Legend · Ballon d\'Or 2022','flag'=>'🇫🇷']],
    '02-29'=>[['name'=>'Tony Robbins','note'=>'Motivational Speaker & Self-Help Guru','flag'=>'🇺🇸']],
    // ── MARCH ──
    '03-01'=>[['name'=>'Justin Bieber','note'=>'Pop Superstar · Baby to Global Icon','flag'=>'🇨🇦'],['name'=>'Ron Howard','note'=>'Oscar-winning Director · A Beautiful Mind','flag'=>'🇺🇸']],
    '03-02'=>[['name'=>'Jon Bon Jovi','note'=>'Livin\' on a Prayer · Rock Legend','flag'=>'🇺🇸'],['name'=>'Dr. Seuss','note'=>'Children\'s Book Author · The Cat in the Hat','flag'=>'🇺🇸']],
    '03-03'=>[['name'=>'Alexander Graham Bell','note'=>'Inventor of the Telephone','flag'=>'🇬🇧'],['name'=>'Jessica Biel','note'=>'Actress & Producer','flag'=>'🇺🇸']],
    '03-04'=>[['name'=>'Channing Tatum','note'=>'Actor & Dancer · Magic Mike','flag'=>'🇺🇸']],
    '03-05'=>[['name'=>'Eva Mendes','note'=>'Actress & Entrepreneur','flag'=>'🇺🇸']],
    '03-06'=>[['name'=>'Michelangelo','note'=>'Renaissance Master · Sistine Chapel & David','flag'=>'🇮🇹'],['name'=>'Shaquille O\'Neal','note'=>'4x NBA Champion · Basketball Giant','flag'=>'🇺🇸']],
    '03-07'=>[['name'=>'Rachel Weisz','note'=>'Oscar-winning Actress','flag'=>'🇬🇧'],['name'=>'Bryan Cranston','note'=>'Actor · Breaking Bad\'s Walter White','flag'=>'🇺🇸']],
    '03-08'=>[['name'=>'Freddie Prinze Jr.','note'=>'Actor & She\'s All That Star','flag'=>'🇺🇸']],
    '03-09'=>[['name'=>'Yuri Gagarin','note'=>'First Human in Space — 1961','flag'=>'🇷🇺'],['name'=>'Bobby Fischer','note'=>'World Chess Champion 1972','flag'=>'🇺🇸']],
    '03-10'=>[['name'=>'Sharon Stone','note'=>'Actress · Basic Instinct','flag'=>'🇺🇸']],
    '03-11'=>[['name'=>'Douglas Adams','note'=>'Author of The Hitchhiker\'s Guide to the Galaxy','flag'=>'🇬🇧']],
    '03-12'=>[['name'=>'Liza Minnelli','note'=>'Cabaret Actress & Singer · Judy Garland\'s Daughter','flag'=>'🇺🇸'],['name'=>'Jack Kerouac','note'=>'Beat Generation Author · On the Road','flag'=>'🇺🇸']],
    '03-13'=>[['name'=>'William H. Macy','note'=>'Actor · Fargo & Shameless','flag'=>'🇺🇸']],
    '03-14'=>[['name'=>'Albert Einstein','note'=>'Physicist · E=mc² · Nobel Prize 1921','flag'=>'🇩🇪']],
    '03-15'=>[['name'=>'Eva Longoria','note'=>'Actress & Producer · Desperate Housewives','flag'=>'🇺🇸'],['name'=>'Andrew Jackson','note'=>'7th US President & Military Hero','flag'=>'🇺🇸']],
    '03-16'=>[['name'=>'Jerry Lewis','note'=>'Comedy Legend & Jerry Lewis Telethon Icon','flag'=>'🇺🇸']],
    '03-17'=>[['name'=>'Rob Lowe','note'=>'Actor · The West Wing & Parks and Recreation','flag'=>'🇺🇸']],
    '03-18'=>[['name'=>'Queen Latifah','note'=>'Grammy & Golden Globe-winning Rapper & Actress','flag'=>'🇺🇸'],['name'=>'Adam Levine','note'=>'Maroon 5 Lead Singer','flag'=>'🇺🇸']],
    '03-19'=>[['name'=>'Bruce Willis','note'=>'Die Hard Action Movie Legend','flag'=>'🇺🇸']],
    '03-20'=>[['name'=>'Spike Lee','note'=>'Oscar-winning Director · Malcolm X & Do the Right Thing','flag'=>'🇺🇸']],
    '03-21'=>[['name'=>'Johann Sebastian Bach','note'=>'Baroque Music Genius · St. Matthew Passion','flag'=>'🇩🇪'],['name'=>'Rosie O\'Donnell','note'=>'Comedian & TV Talk Show Host','flag'=>'🇺🇸']],
    '03-22'=>[['name'=>'Reese Witherspoon','note'=>'Oscar-winning Actress · Legally Blonde','flag'=>'🇺🇸'],['name'=>'William Shatner','note'=>'Actor · Captain Kirk in Star Trek','flag'=>'🇨🇦']],
    '03-23'=>[['name'=>'Chaka Khan','note'=>'Queen of Funk · I\'m Every Woman','flag'=>'🇺🇸']],
    '03-24'=>[['name'=>'Harry Houdini','note'=>'Greatest Escape Artist & Illusionist in History','flag'=>'🇭🇺']],
    '03-25'=>[['name'=>'Elton John','note'=>'Rock & Pop Legend · Rocket Man & Crocodile Rock','flag'=>'🇬🇧'],['name'=>'Aretha Franklin','note'=>'Queen of Soul · Respect','flag'=>'🇺🇸']],
    '03-26'=>[['name'=>'Diana Ross','note'=>'The Supremes · Motown Music Legend','flag'=>'🇺🇸'],['name'=>'Keira Knightley','note'=>'Actress · Pirates of the Caribbean & Pride & Prejudice','flag'=>'🇬🇧']],
    '03-27'=>[['name'=>'Mariah Carey','note'=>'Songbird Supreme · 18 No.1 Billboard Singles','flag'=>'🇺🇸']],
    '03-28'=>[['name'=>'Lady Gaga','note'=>'Pop Performance Art Icon · Born This Way','flag'=>'🇺🇸']],
    '03-29'=>[['name'=>'Elle Macpherson','note'=>'The Body — Supermodel & Entrepreneur','flag'=>'🇦🇺']],
    '03-30'=>[['name'=>'Celine Dion','note'=>'My Heart Will Go On · Canadian Vocal Legend','flag'=>'🇨🇦'],['name'=>'Eric Clapton','note'=>'Slowhand · Guitar Legend · Cream & Clapton','flag'=>'🇬🇧']],
    '03-31'=>[['name'=>'Ewan McGregor','note'=>'Actor · Obi-Wan Kenobi & Trainspotting','flag'=>'🇬🇧'],['name'=>'Cesar Chavez','note'=>'Farmworkers\' Rights Activist','flag'=>'🇺🇸']],
    // ── APRIL ──
    '04-01'=>[['name'=>'Marvin Gaye','note'=>'Soul Music Legend · What\'s Going On','flag'=>'🇺🇸'],['name'=>'Debbie Reynolds','note'=>'Singin\' in the Rain Actress','flag'=>'🇺🇸']],
    '04-02'=>[['name'=>'Hans Christian Andersen','note'=>'Fairy Tale Author · The Little Mermaid & The Ugly Duckling','flag'=>'🇩🇰']],
    '04-03'=>[['name'=>'Alec Baldwin','note'=>'Actor · 30 Rock & Hollywood Star','flag'=>'🇺🇸']],
    '04-04'=>[['name'=>'Robert Downey Jr.','note'=>'Iron Man · Hollywood Comeback Legend','flag'=>'🇺🇸']],
    '04-05'=>[['name'=>'Pharrell Williams','note'=>'Happy · Grammy-winning Producer & Singer','flag'=>'🇺🇸'],['name'=>'Colin Powell','note'=>'First African-American US Secretary of State','flag'=>'🇺🇸']],
    '04-06'=>[['name'=>'Paul Rudd','note'=>'Actor · Ant-Man & Friends','flag'=>'🇺🇸']],
    '04-07'=>[['name'=>'Jackie Chan','note'=>'Hong Kong Action Movie Global Superstar','flag'=>'🇭🇰']],
    '04-08'=>[['name'=>'Kofi Annan','note'=>'7th UN Secretary-General · Nobel Peace Prize 2001','flag'=>'🇬🇭']],
    '04-09'=>[['name'=>'Dennis Quaid','note'=>'Actor · The Parent Trap & Far From Heaven','flag'=>'🇺🇸']],
    '04-10'=>[['name'=>'Steven Seagal','note'=>'Action Movie Star & Martial Arts Master','flag'=>'🇺🇸']],
    '04-11'=>[['name'=>'Jeremy Clarkson','note'=>'Top Gear TV Host & Motoring Journalist','flag'=>'🇬🇧']],
    '04-12'=>[['name'=>'David Letterman','note'=>'Late Night TV Host Legend','flag'=>'🇺🇸'],['name'=>'Yuri Gagarin*','note'=>'(Space flight anniversary: Apr 12, 1961)','flag'=>'🇷🇺']],
    '04-13'=>[['name'=>'Thomas Jefferson','note'=>'3rd US President · Author of the Declaration of Independence','flag'=>'🇺🇸'],['name'=>'Samuel Beckett','note'=>'Playwright · Waiting for Godot','flag'=>'🇮🇪']],
    '04-14'=>[['name'=>'Sarah Michelle Gellar','note'=>'Actress · Buffy the Vampire Slayer','flag'=>'🇺🇸'],['name'=>'Loretta Lynn','note'=>'Country Music Queen of Coal Miner\'s Daughter','flag'=>'🇺🇸']],
    '04-15'=>[['name'=>'Leonardo da Vinci','note'=>'Renaissance Polymath — Painter, Scientist & Inventor','flag'=>'🇮🇹'],['name'=>'Emma Thompson','note'=>'Oscar-winning Actress & Screenwriter','flag'=>'🇬🇧']],
    '04-16'=>[['name'=>'Charlie Chaplin','note'=>'Silent Film Comedy Master · The Tramp','flag'=>'🇬🇧']],
    '04-17'=>[['name'=>'Jennifer Garner','note'=>'Actress · Alias & 13 Going on 30','flag'=>'🇺🇸']],
    '04-18'=>[['name'=>'Conan O\'Brien','note'=>'Late Night TV Host & Comedian','flag'=>'🇺🇸']],
    '04-19'=>[['name'=>'Maria Sharapova','note'=>'5-time Grand Slam Tennis Champion','flag'=>'🇷🇺']],
    '04-20'=>[['name'=>'Jessica Lange','note'=>'Two-time Oscar-winning Actress','flag'=>'🇺🇸']],
    '04-21'=>[['name'=>'Queen Elizabeth II','note'=>'Longest-reigning British Monarch — 70 years','flag'=>'🇬🇧']],
    '04-22'=>[['name'=>'Jack Nicholson','note'=>'3-time Oscar winner · The Shining & One Flew Over the Cuckoo\'s Nest','flag'=>'🇺🇸'],['name'=>'Immanuel Kant','note'=>'Enlightenment Philosopher · Critique of Pure Reason','flag'=>'🇩🇪']],
    '04-23'=>[['name'=>'William Shakespeare','note'=>'Greatest English Playwright · Hamlet & Romeo & Juliet','flag'=>'🇬🇧']],
    '04-24'=>[['name'=>'Barbra Streisand','note'=>'The Way We Were · Oscar & Grammy Legend','flag'=>'🇺🇸'],['name'=>'Kelly Clarkson','note'=>'First American Idol Winner & Pop Star','flag'=>'🇺🇸']],
    '04-25'=>[['name'=>'Al Pacino','note'=>'Scarface & The Godfather · Hollywood Icon','flag'=>'🇺🇸'],['name'=>'Ella Fitzgerald','note'=>'First Lady of Song · Jazz Vocal Legend','flag'=>'🇺🇸']],
    '04-26'=>[['name'=>'Carol Burnett','note'=>'Comedy TV Queen · The Carol Burnett Show','flag'=>'🇺🇸']],
    '04-27'=>[['name'=>'Ulysses S. Grant','note'=>'18th US President & Civil War Hero','flag'=>'🇺🇸']],
    '04-28'=>[['name'=>'Jay Leno','note'=>'The Tonight Show Host & Car Collector','flag'=>'🇺🇸'],['name'=>'Jessica Alba','note'=>'Actress & Founder of The Honest Company','flag'=>'🇺🇸']],
    '04-29'=>[['name'=>'Uma Thurman','note'=>'Actress · Pulp Fiction & Kill Bill','flag'=>'🇺🇸'],['name'=>'Jerry Seinfeld','note'=>'Comedian · Seinfeld TV Legend','flag'=>'🇺🇸']],
    '04-30'=>[['name'=>'Willie Nelson','note'=>'Country Music Outlaw & Cannabis Advocate','flag'=>'🇺🇸'],['name'=>'Kirsten Dunst','note'=>'Actress · Spider-Man & Marie Antoinette','flag'=>'🇺🇸']],
    // ── MAY ──
    '05-01'=>[['name'=>'Tim McGraw','note'=>'Country Music Superstar','flag'=>'🇺🇸']],
    '05-02'=>[['name'=>'David Beckham','note'=>'Football Icon & Global Style Ambassador','flag'=>'🇬🇧']],
    '05-03'=>[['name'=>'James Brown','note'=>'Godfather of Soul · I Got You (I Feel Good)','flag'=>'🇺🇸']],
    '05-04'=>[['name'=>'Audrey Hepburn','note'=>'Oscar-winning Actress & UNICEF Goodwill Ambassador','flag'=>'🇧🇪']],
    '05-05'=>[['name'=>'Adele','note'=>'Hello & Rolling in the Deep · Grammy Legend','flag'=>'🇬🇧'],['name'=>'Karl Marx','note'=>'Philosopher & Economist · Das Kapital','flag'=>'🇩🇪']],
    '05-06'=>[['name'=>'Sigmund Freud','note'=>'Father of Psychoanalysis','flag'=>'🇦🇹'],['name'=>'George Clooney','note'=>'Oscar-winning Actor, Director & Humanitarian','flag'=>'🇺🇸']],
    '05-07'=>[['name'=>'Johannes Brahms','note'=>'Romantic Era Composer · Four Symphonies','flag'=>'🇩🇪'],['name'=>'Eva Perón','note'=>'First Lady of Argentina · Evita','flag'=>'🇦🇷']],
    '05-08'=>[['name'=>'David Attenborough','note'=>'Natural World Documentary Legend','flag'=>'🇬🇧'],['name'=>'Enrique Iglesias','note'=>'Latin Pop Superstar · Hero','flag'=>'🇪🇸']],
    '05-09'=>[['name'=>'Billy Joel','note'=>'Piano Man · Rock & Pop Songwriter Legend','flag'=>'🇺🇸']],
    '05-10'=>[['name'=>'Bono','note'=>'U2 Frontman & Global Humanitarian','flag'=>'🇮🇪'],['name'=>'Fred Astaire','note'=>'Greatest Tap Dancer & Actor in Hollywood History','flag'=>'🇺🇸']],
    '05-11'=>[['name'=>'Salvador Dalí','note'=>'Surrealist Art Master · The Persistence of Memory','flag'=>'🇪🇸']],
    '05-12'=>[['name'=>'Florence Nightingale','note'=>'Founder of Modern Nursing','flag'=>'🇬🇧']],
    '05-13'=>[['name'=>'Stevie Wonder','note'=>'Superstition · Motown Music Genius','flag'=>'🇺🇸']],
    '05-14'=>[['name'=>'Mark Zuckerberg','note'=>'Co-founder of Facebook / Meta','flag'=>'🇺🇸'],['name'=>'Cate Blanchett','note'=>'2-time Oscar-winning Actress','flag'=>'🇦🇺']],
    '05-15'=>[['name'=>'L. Frank Baum','note'=>'Author of The Wonderful Wizard of Oz','flag'=>'🇺🇸'],['name'=>'Madeleine Albright','note'=>'First Female US Secretary of State','flag'=>'🇺🇸']],
    '05-16'=>[['name'=>'Pierce Brosnan','note'=>'Actor · James Bond 007 GoldenEye era','flag'=>'🇮🇪'],['name'=>'Megan Fox','note'=>'Actress & Transformers Star','flag'=>'🇺🇸']],
    '05-17'=>[['name'=>'Enya','note'=>'New Age Music Legend · Orinoco Flow','flag'=>'🇮🇪']],
    '05-18'=>[['name'=>'Tina Fey','note'=>'SNL & 30 Rock Comedy Writer & Actress','flag'=>'🇺🇸'],['name'=>'Pope John Paul II','note'=>'Beloved Catholic Pontiff & Polish Saint','flag'=>'🇵🇱']],
    '05-19'=>[['name'=>'Malcolm X','note'=>'Black Nationalist Leader & Civil Rights Activist','flag'=>'🇺🇸']],
    '05-20'=>[['name'=>'Cher','note'=>'Believe · If I Could Turn Back Time · Music Icon','flag'=>'🇺🇸']],
    '05-21'=>[['name'=>'The Notorious B.I.G.','note'=>'Hip-Hop Legend · Juicy & Big Poppa','flag'=>'🇺🇸']],
    '05-22'=>[['name'=>'Arthur Conan Doyle','note'=>'Creator of Sherlock Holmes','flag'=>'🇬🇧'],['name'=>'Naomi Campbell','note'=>'Supermodel & Fashion Icon','flag'=>'🇬🇧']],
    '05-23'=>[['name'=>'Joan Collins','note'=>'Actress · Dynasty & Hollywood Glamour Icon','flag'=>'🇬🇧']],
    '05-24'=>[['name'=>'Bob Dylan','note'=>'Nobel Prize Singer-Songwriter · Blowin\' in the Wind','flag'=>'🇺🇸'],['name'=>'Queen Victoria','note'=>'Longest-reigning 19th-century British Monarch','flag'=>'🇬🇧']],
    '05-25'=>[['name'=>'U Nu','note'=>'First Prime Minister of Independent Myanmar','flag'=>'🇲🇲'],['name'=>'Mike Myers','note'=>'Austin Powers & Shrek · Canadian Comedy Legend','flag'=>'🇨🇦']],
    '05-26'=>[['name'=>'Helena Bonham Carter','note'=>'Actress · Harry Potter & Fight Club','flag'=>'🇬🇧'],['name'=>'Lenny Kravitz','note'=>'Again · Rock & Soul Multi-instrumentalist','flag'=>'🇺🇸']],
    '05-27'=>[['name'=>'Henry Kissinger','note'=>'Nobel Prize-winning US Secretary of State','flag'=>'🇺🇸']],
    '05-28'=>[['name'=>'Kylie Minogue','note'=>'Can\'t Get You Out of My Head · Australian Pop Princess','flag'=>'🇦🇺'],['name'=>'Ian Fleming','note'=>'Creator of James Bond 007','flag'=>'🇬🇧']],
    '05-29'=>[['name'=>'John F. Kennedy','note'=>'35th US President · Ask Not What Your Country...','flag'=>'🇺🇸']],
    '05-30'=>[['name'=>'Clint Eastwood','note'=>'Dirty Harry & Unforgiven · Hollywood Western Legend','flag'=>'🇺🇸'],['name'=>'Mel Blanc','note'=>'Voice of Bugs Bunny & Looney Tunes Characters','flag'=>'🇺🇸']],
    '05-31'=>[['name'=>'Brooke Shields','note'=>'Supermodel & Actress · The Blue Lagoon','flag'=>'🇺🇸']],
    // ── JUNE ──
    '06-01'=>[['name'=>'Marilyn Monroe','note'=>'Some Like It Hot · Hollywood\'s Greatest Sex Symbol','flag'=>'🇺🇸'],['name'=>'Alanis Morissette','note'=>'Ironic & You Oughta Know · Alternative Rock Queen','flag'=>'🇨🇦']],
    '06-02'=>[['name'=>'Marquis de Sade','note'=>'Controversial French Philosopher & Author','flag'=>'🇫🇷']],
    '06-03'=>[['name'=>'Rafael Nadal','note'=>'14-time French Open Champion · Clay Court King','flag'=>'🇪🇸'],['name'=>'Tony Curtis','note'=>'Hollywood Golden Age Actor · Some Like It Hot','flag'=>'🇺🇸']],
    '06-04'=>[['name'=>'Angelina Jolie','note'=>'Oscar-winning Actress & UN Refugee Agency Ambassador','flag'=>'🇺🇸']],
    '06-05'=>[['name'=>'Mark Wahlberg','note'=>'Actor & Producer · Boogie Nights & Transformers','flag'=>'🇺🇸'],['name'=>'Federico García Lorca','note'=>'Spanish Poet & Playwright','flag'=>'🇪🇸']],
    '06-06'=>[['name'=>'Björn Borg','note'=>'5-time Wimbledon Champion · Tennis Legend','flag'=>'🇸🇪']],
    '06-07'=>[['name'=>'Prince','note'=>'Purple Rain · Funk & Pop Music Genius','flag'=>'🇺🇸'],['name'=>'Tom Jones','note'=>'It\'s Not Unusual · Welsh Singing Legend','flag'=>'🏴󠁧󠁢󠁷󠁬󠁳󠁿']],
    '06-08'=>[['name'=>'Tim Berners-Lee','note'=>'Inventor of the World Wide Web','flag'=>'🇬🇧']],
    '06-09'=>[['name'=>'Johnny Depp','note'=>'Captain Jack Sparrow · Pirates of the Caribbean','flag'=>'🇺🇸'],['name'=>'Michael J. Fox','note'=>'Actor · Back to the Future & Parkinson\'s Advocate','flag'=>'🇨🇦']],
    '06-10'=>[['name'=>'Judy Garland','note'=>'Somewhere Over the Rainbow · Wizard of Oz Legend','flag'=>'🇺🇸'],['name'=>'Maurice Sendak','note'=>'Author of Where the Wild Things Are','flag'=>'🇺🇸']],
    '06-11'=>[['name'=>'Jacques Cousteau','note'=>'Ocean Explorer & Marine Conservation Pioneer','flag'=>'🇫🇷'],['name'=>'Hugh Laurie','note'=>'Actor · House M.D. & Jeeves and Wooster','flag'=>'🇬🇧']],
    '06-12'=>[['name'=>'Anne Frank','note'=>'The Diary of a Young Girl — Holocaust Symbol of Hope','flag'=>'🇩🇪'],['name'=>'George H.W. Bush','note'=>'41st US President','flag'=>'🇺🇸']],
    '06-13'=>[['name'=>'Chris Evans','note'=>'Captain America · Marvel Avengers Hero','flag'=>'🇺🇸']],
    '06-14'=>[['name'=>'Che Guevara','note'=>'Marxist Revolutionary Leader · Cuban Revolution Icon','flag'=>'🇦🇷'],['name'=>'Donald Trump','note'=>'45th & 47th US President','flag'=>'🇺🇸']],
    '06-15'=>[['name'=>'Neil Patrick Harris','note'=>'Actor · Doogie Howser & How I Met Your Mother','flag'=>'🇺🇸'],['name'=>'Ice Cube','note'=>'N.W.A. Hip-Hop Pioneer & Actor','flag'=>'🇺🇸']],
    '06-16'=>[['name'=>'Tupac Shakur','note'=>'Hip-Hop Legend · California Love','flag'=>'🇺🇸'],['name'=>'Stan Laurel','note'=>'Laurel & Hardy Comedy Legend','flag'=>'🇬🇧']],
    '06-17'=>[['name'=>'Venus Williams','note'=>'7-time Grand Slam Tennis Champion','flag'=>'🇺🇸'],['name'=>'Barry Manilow','note'=>'Mandy & Copacabana · Soft Rock Icon','flag'=>'🇺🇸']],
    '06-18'=>[['name'=>'Paul McCartney','note'=>'Yesterday · The Beatles & Wings Legend','flag'=>'🇬🇧']],
    '06-19'=>[['name'=>'Salman Rushdie','note'=>'Midnight\'s Children · Booker Prize Author','flag'=>'🇬🇧']],
    '06-20'=>[['name'=>'Lionel Richie','note'=>'Hello · All Night Long · R&B Legend','flag'=>'🇺🇸'],['name'=>'Nicole Kidman','note'=>'2-time Oscar-winning Actress','flag'=>'🇦🇺']],
    '06-21'=>[['name'=>'Prince William','note'=>'Prince of Wales · Future King of the UK','flag'=>'🇬🇧'],['name'=>'Juliette Lewis','note'=>'Actress & Punk Rock Singer','flag'=>'🇺🇸']],
    '06-22'=>[['name'=>'Meryl Streep','note'=>'Greatest Actress in Hollywood History · 3 Oscars','flag'=>'🇺🇸']],
    '06-23'=>[['name'=>'Zinedine Zidane','note'=>'Football Genius · Ballon d\'Or & World Cup Winner','flag'=>'🇫🇷']],
    '06-24'=>[['name'=>'Lionel Messi','note'=>'Football GOAT · 8-time Ballon d\'Or Winner','flag'=>'🇦🇷']],
    '06-25'=>[['name'=>'George Orwell','note'=>'Author of 1984 & Animal Farm','flag'=>'🇬🇧']],
    '06-26'=>[['name'=>'Mick Jagger','note'=>'The Rolling Stones Frontman · Rock \'n\' Roll Legend','flag'=>'🇬🇧']],
    '06-27'=>[['name'=>'Tobey Maguire','note'=>'Actor · Spider-Man Original Trilogy','flag'=>'🇺🇸']],
    '06-28'=>[['name'=>'Mel Brooks','note'=>'Comedy Director · Blazing Saddles & Young Frankenstein','flag'=>'🇺🇸'],['name'=>'Kathy Bates','note'=>'Oscar-winning Actress · Misery & American Horror Story','flag'=>'🇺🇸']],
    '06-29'=>[['name'=>'Nicole Scherzinger','note'=>'Pussycat Dolls Leader & X-Factor Judge','flag'=>'🇺🇸']],
    '06-30'=>[['name'=>'Lena Horne','note'=>'Jazz & Broadway Singing Legend','flag'=>'🇺🇸']],
    // ── JULY ──
    '07-01'=>[['name'=>'Princess Diana','note'=>"The People's Princess · Royal Humanitarian Icon","flag"=>'🇬🇧'],['name'=>'Pamela Anderson','note'=>'Baywatch Actress & Animal Rights Activist','flag'=>'🇨🇦']],
    '07-02'=>[['name'=>'Thurgood Marshall','note'=>'First African-American US Supreme Court Justice','flag'=>'🇺🇸']],
    '07-03'=>[['name'=>'Tom Cruise','note'=>'Mission: Impossible & Top Gun · Hollywood Action Star','flag'=>'🇺🇸'],['name'=>'Franz Kafka','note'=>'Author of The Metamorphosis · Surrealist Lit Pioneer','flag'=>'🇨🇿']],
    '07-04'=>[['name'=>'Calvin Coolidge','note'=>'30th US President','flag'=>'🇺🇸'],['name'=>'Gina Lollobrigida','note'=>'Italian Film Actress & International Sex Symbol','flag'=>'🇮🇹']],
    '07-05'=>[['name'=>'P.T. Barnum','note'=>'Greatest Showman · Circus & Entertainment Pioneer','flag'=>'🇺🇸']],
    '07-06'=>[['name'=>'Sylvester Stallone','note'=>'Rocky & Rambo · Action Movie Legend','flag'=>'🇺🇸'],['name'=>'50 Cent','note'=>'In Da Club · Hip-Hop Star & Businessman','flag'=>'🇺🇸']],
    '07-07'=>[['name'=>'Ringo Starr','note'=>'The Beatles Drummer · Peace & Love','flag'=>'🇬🇧']],
    '07-08'=>[['name'=>'Kevin Bacon','note'=>'Actor · Footloose & Apollo 13','flag'=>'🇺🇸']],
    '07-09'=>[['name'=>'Tom Hanks','note'=>'2-time Oscar winner · Forrest Gump & Cast Away','flag'=>'🇺🇸']],
    '07-10'=>[['name'=>'Nikola Tesla','note'=>'Inventor of AC Power · Visionary Electrical Engineer','flag'=>'🇷🇸'],['name'=>'Sofia Vergara','note'=>'Actress · Modern Family · Most-paid TV Actress','flag'=>'🇨🇴']],
    '07-11'=>[['name'=>'Yul Brynner','note'=>'Oscar-winning Actor · The King and I','flag'=>'🇷🇺']],
    '07-12'=>[['name'=>'Malala Yousafzai','note'=>'Youngest Nobel Peace Prize Laureate · Education Activist','flag'=>'🇵🇰']],
    '07-13'=>[['name'=>'Harrison Ford','note'=>'Han Solo & Indiana Jones · Hollywood Action Legend','flag'=>'🇺🇸']],
    '07-14'=>[['name'=>'Missy Elliott','note'=>'Work It · Hip-Hop & R&B Visionary','flag'=>'🇺🇸']],
    '07-15'=>[['name'=>'Rembrandt','note'=>'Dutch Golden Age Painting Master','flag'=>'🇳🇱'],['name'=>'Linda Ronstadt','note'=>'You\'re No Good · Rock & Country Vocal Legend','flag'=>'🇺🇸']],
    '07-16'=>[['name'=>'Will Ferrell','note'=>'Elf & Anchorman · SNL Comedy Legend','flag'=>'🇺🇸'],['name'=>'Ginger Rogers','note'=>'Oscar-winning Actress & Dancer · Fred Astaire\'s Partner','flag'=>'🇺🇸']],
    '07-17'=>[['name'=>'David Hasselhoff','note'=>'Baywatch & Knight Rider · Global Pop Phenomenon','flag'=>'🇺🇸']],
    '07-18'=>[['name'=>'Nelson Mandela','note'=>'Anti-Apartheid Leader · 1st Democratically Elected SA President','flag'=>'🇿🇦']],
    '07-19'=>[['name'=>'Benedict Cumberbatch','note'=>'Actor · Sherlock Holmes & Doctor Strange','flag'=>'🇬🇧']],
    '07-20'=>[['name'=>'Carlos Santana','note'=>'Smooth · Guitar Legend & Rock Hall of Famer','flag'=>'🇲🇽']],
    '07-21'=>[['name'=>'Robin Williams','note'=>'Mrs. Doubtfire & Aladdin · Comedy & Drama Legend','flag'=>'🇺🇸'],['name'=>'Ernest Hemingway','note'=>'The Old Man and the Sea · Nobel Prize Author','flag'=>'🇺🇸']],
    '07-22'=>[['name'=>'Selena Gomez','note'=>'Singer, Actress & Rare Beauty Entrepreneur','flag'=>'🇺🇸']],
    '07-23'=>[['name'=>'Daniel Radcliffe','note'=>'Harry Potter · Boy Who Lived','flag'=>'🇬🇧']],
    '07-24'=>[['name'=>'Jennifer Lopez','note'=>'J.Lo · Pop Star, Actress & Business Mogul','flag'=>'🇺🇸'],['name'=>'Amelia Earhart','note'=>'First Female Aviator to Fly Solo Across the Atlantic','flag'=>'🇺🇸']],
    '07-25'=>[['name'=>'Matt LeBlanc','note'=>'Actor · Joey Tribbiani in Friends','flag'=>'🇺🇸']],
    '07-26'=>[['name'=>'Mick Jagger','note'=>'Rolling Stones Frontman · Satisfaction','flag'=>'🇬🇧'],['name'=>'Sandra Bullock','note'=>'Oscar-winning Actress · Gravity & Speed','flag'=>'🇺🇸']],
    '07-27'=>[['name'=>'Maya Rudolph','note'=>'SNL Comedian & Actress','flag'=>'🇺🇸']],
    '07-28'=>[['name'=>'Jacqueline Kennedy Onassis','note'=>'US First Lady & Cultural Icon','flag'=>'🇺🇸']],
    '07-29'=>[['name'=>'Benito Mussolini','note'=>'Italian Fascist Dictator — Historical Figure','flag'=>'🇮🇹']],
    '07-30'=>[['name'=>'Henry Ford','note'=>'Founder of Ford Motor Company · Assembly Line Pioneer','flag'=>'🇺🇸'],['name'=>'Arnold Schwarzenegger','note'=>'The Terminator · Bodybuilder & California Governor','flag'=>'🇦🇹']],
    '07-31'=>[['name'=>'J.K. Rowling','note'=>'Author of Harry Potter Series','flag'=>'🇬🇧']],
    // ── AUGUST ──
    '08-01'=>[['name'=>'Herman Melville','note'=>'Author of Moby-Dick · Maritime Literary Giant','flag'=>'🇺🇸']],
    '08-02'=>[['name'=>'James Baldwin','note'=>'Go Tell It on the Mountain · Civil Rights Author','flag'=>'🇺🇸']],
    '08-03'=>[['name'=>'Tom Brady','note'=>'7-time Super Bowl Champion · NFL GOAT','flag'=>'🇺🇸'],['name'=>'Martha Stewart','note'=>'Lifestyle & Cooking Entrepreneur','flag'=>'🇺🇸']],
    '08-04'=>[['name'=>'Barack Obama','note'=>'44th President of the United States · Yes We Can','flag'=>'🇺🇸'],['name'=>'Louis Armstrong','note'=>'What a Wonderful World · Jazz Trumpet Legend','flag'=>'🇺🇸']],
    '08-05'=>[['name'=>'Neil Armstrong','note'=>'First Human to Walk on the Moon — July 1969','flag'=>'🇺🇸']],
    '08-06'=>[['name'=>'Andy Warhol','note'=>'Campbell\'s Soup Cans · Pop Art Pioneer','flag'=>'🇺🇸'],['name'=>'Geri Halliwell','note'=>'Spice Girls — Ginger Spice','flag'=>'🇬🇧']],
    '08-07'=>[['name'=>'Charlize Theron','note'=>'Oscar-winning Actress · Monster & Mad Max','flag'=>'🇿🇦']],
    '08-08'=>[['name'=>'Roger Federer','note'=>'20-time Grand Slam Champion · Tennis GOAT','flag'=>'🇨🇭'],['name'=>'Dustin Hoffman','note'=>'2-time Oscar-winning Actor · The Graduate & Rain Man','flag'=>'🇺🇸']],
    '08-09'=>[['name'=>'Whitney Houston','note'=>'I Will Always Love You · The Voice of a Generation','flag'=>'🇺🇸']],
    '08-10'=>[['name'=>'Antonio Banderas','note'=>'Actor · Zorro & Desperado','flag'=>'🇪🇸']],
    '08-11'=>[['name'=>'Chris Hemsworth','note'=>'Thor · Marvel Avengers Superhero','flag'=>'🇦🇺'],['name'=>'Viola Davis','note'=>'EGOT winner — Emmy, Grammy, Oscar & Tony','flag'=>'🇺🇸']],
    '08-12'=>[['name'=>'George Soros','note'=>'Billionaire Investor & Philanthropist','flag'=>'🇺🇸'],['name'=>'Pete Sampras','note'=>'14-time Grand Slam Tennis Champion','flag'=>'🇺🇸']],
    '08-13'=>[['name'=>'Alfred Hitchcock','note'=>'Master of Suspense · Psycho & Vertigo Director','flag'=>'🇬🇧'],['name'=>'Fidel Castro','note'=>'Cuban Revolutionary Leader','flag'=>'🇨🇺']],
    '08-14'=>[['name'=>'Magic Johnson','note'=>'5-time NBA Champion · Basketball Legend','flag'=>'🇺🇸'],['name'=>'Steve Martin','note'=>'Wild and Crazy Guy · Comedian & Author','flag'=>'🇺🇸']],
    '08-15'=>[['name'=>'Napoleon Bonaparte','note'=>'French Emperor & Military Genius','flag'=>'🇫🇷'],['name'=>'Julia Child','note'=>'The French Chef · American Culinary Pioneer','flag'=>'🇺🇸']],
    '08-16'=>[['name'=>'Madonna','note'=>'Queen of Pop · Material Girl & Like a Virgin','flag'=>'🇺🇸'],['name'=>'Steve Carell','note'=>'Actor & Comedian · The Office & The 40-Year-Old Virgin','flag'=>'🇺🇸']],
    '08-17'=>[['name'=>'Robert De Niro','note'=>'2-time Oscar winner · Taxi Driver & Raging Bull','flag'=>'🇺🇸'],['name'=>'Sean Penn','note'=>'2-time Oscar-winning Actor & Director','flag'=>'🇺🇸']],
    '08-18'=>[['name'=>'Patrick Swayze','note'=>'Dirty Dancing & Ghost · Dance & Drama Icon','flag'=>'🇺🇸'],['name'=>'Edward Norton','note'=>'Actor · Fight Club & American History X','flag'=>'🇺🇸']],
    '08-19'=>[['name'=>'Bill Clinton','note'=>'42nd US President','flag'=>'🇺🇸'],['name'=>'Coco Chanel','note'=>'Fashion House Founder · Chanel No. 5 Creator','flag'=>'🇫🇷']],
    '08-20'=>[['name'=>'Amy Adams','note'=>'5-time Oscar-nominated Actress','flag'=>'🇺🇸'],['name'=>'H.P. Lovecraft','note'=>'Horror Fiction Author · The Call of Cthulhu','flag'=>'🇺🇸']],
    '08-21'=>[['name'=>'Wilt Chamberlain','note'=>'NBA Basketball Legend · 100-point Game Record','flag'=>'🇺🇸']],
    '08-22'=>[['name'=>'Dua Lipa','note'=>'Levitating & Don\'t Start Now · Pop Superstar','flag'=>'🇬🇧'],['name'=>'Claude Debussy','note'=>'Impressionist Classical Composer · Clair de Lune','flag'=>'🇫🇷']],
    '08-23'=>[['name'=>'Kobe Bryant','note'=>'Black Mamba · 5-time NBA Champion','flag'=>'🇺🇸']],
    '08-24'=>[['name'=>'Dave Chappelle','note'=>'Stand-Up Comedy Legend & Chappelle\'s Show Creator','flag'=>'🇺🇸'],['name'=>'Rupert Grint','note'=>'Actor · Ron Weasley in Harry Potter','flag'=>'🇬🇧']],
    '08-25'=>[['name'=>'Sean Connery','note'=>'First & Finest James Bond · Oscar-winning Actor','flag'=>'🏴󠁧󠁢󠁳󠁣󠁴󠁿'],['name'=>'Tim Burton','note'=>'Director · Edward Scissorhands & Batman','flag'=>'🇺🇸']],
    '08-26'=>[['name'=>'Mother Teresa','note'=>'Nobel Peace Prize Saint · Missionary of Calcutta','flag'=>'🇦🇱']],
    '08-27'=>[['name'=>'Lyndon B. Johnson','note'=>'36th US President · Great Society & Civil Rights Act','flag'=>'🇺🇸'],['name'=>'Aaron Paul','note'=>'Actor · Jesse Pinkman in Breaking Bad','flag'=>'🇺🇸']],
    '08-28'=>[['name'=>'Shania Twain','note'=>'Man! I Feel Like a Woman · Country-Pop Crossover Queen','flag'=>'🇨🇦'],['name'=>'Jack Black','note'=>'Actor & Comedian · School of Rock','flag'=>'🇺🇸']],
    '08-29'=>[['name'=>'Michael Jackson','note'=>'King of Pop · Thriller & Billie Jean','flag'=>'🇺🇸'],['name'=>'Ingrid Bergman','note'=>'Oscar-winning Swedish Actress · Casablanca','flag'=>'🇸🇪']],
    '08-30'=>[['name'=>'Warren Buffett','note'=>'Oracle of Omaha · World\'s Greatest Investor','flag'=>'🇺🇸'],['name'=>'Cameron Diaz','note'=>'Actress · The Mask & There\'s Something About Mary','flag'=>'🇺🇸']],
    '08-31'=>[['name'=>'Richard Gere','note'=>'Pretty Woman & An Officer and a Gentleman · Actor','flag'=>'🇺🇸'],['name'=>'Van Morrison','note'=>'Brown Eyed Girl · Northern Irish Rock & Soul Legend','flag'=>'🇬🇧']],
    // ── SEPTEMBER ──
    '09-01'=>[['name'=>'Barry Gibb','note'=>'Bee Gees — Stayin\' Alive · Disco Era Legend','flag'=>'🇬🇧']],
    '09-02'=>[['name'=>'Keanu Reeves','note'=>'The Matrix & John Wick · Hollywood Action Star','flag'=>'🇨🇦'],['name'=>'Salma Hayek','note'=>'Actress & Producer · Frida','flag'=>'🇲🇽']],
    '09-03'=>[['name'=>'Charlie Sheen','note'=>'Actor · Two and a Half Men & Wall Street','flag'=>'🇺🇸']],
    '09-04'=>[['name'=>'Beyoncé','note'=>'Queen Bey · Crazy in Love & Lemonade · Music Icon','flag'=>'🇺🇸']],
    '09-05'=>[['name'=>'Freddie Mercury','note'=>'Queen Lead Singer · Bohemian Rhapsody','flag'=>'🇬🇧'],['name'=>'Michael Keaton','note'=>'Actor · Batman & Birdman','flag'=>'🇺🇸']],
    '09-06'=>[['name'=>'Idris Elba','note'=>'Actor · The Wire\'s Stringer Bell & Luther','flag'=>'🇬🇧']],
    '09-07'=>[['name'=>'Evan Rachel Wood','note'=>'Actress · Westworld & Thirteen','flag'=>'🇺🇸']],
    '09-08'=>[['name'=>'Pink','note'=>'Get the Party Started · Pop-Rock Vocal Powerhouse','flag'=>'🇺🇸'],['name'=>'Avicii','note'=>'Wake Me Up · EDM DJ & Producer Legend','flag'=>'🇸🇪']],
    '09-09'=>[['name'=>'Adam Sandler','note'=>'Comedian & Actor · Happy Gilmore & Uncut Gems','flag'=>'🇺🇸'],['name'=>'Hugh Grant','note'=>'Four Weddings and a Funeral · British Rom-Com Star','flag'=>'🇬🇧']],
    '09-10'=>[['name'=>'Colin Firth','note'=>'Oscar-winning Actor · The King\'s Speech','flag'=>'🇬🇧'],['name'=>'Arnold Palmer','note'=>'Golf Legend & Pioneer','flag'=>'🇺🇸']],
    '09-11'=>[['name'=>'Harry Connick Jr.','note'=>'Jazz Pianist, Singer & Actor','flag'=>'🇺🇸']],
    '09-12'=>[['name'=>'Jennifer Hudson','note'=>'EGOT Winner · Oscar for Dreamgirls','flag'=>'🇺🇸'],['name'=>'Jesse Owens','note'=>'4 Olympic Gold Medals · Berlin 1936','flag'=>'🇺🇸']],
    '09-13'=>[['name'=>'Roald Dahl','note'=>'Charlie and the Chocolate Factory · Children\'s Author','flag'=>'🇬🇧'],['name'=>'Fiona Apple','note'=>'Criminal · Alternative Singer-Songwriter','flag'=>'🇺🇸']],
    '09-14'=>[['name'=>'Amy Winehouse','note'=>'Back to Black · Soulful Vocal Legend','flag'=>'🇬🇧']],
    '09-15'=>[['name'=>'Agatha Christie','note'=>'Queen of Crime · 2B+ Books Sold Worldwide','flag'=>'🇬🇧'],['name'=>'Tommy Lee Jones','note'=>'Oscar-winning Actor · The Fugitive & No Country for Old Men','flag'=>'🇺🇸']],
    '09-16'=>[['name'=>'Marc Anthony','note'=>'Vivir Mi Vida · Latin Music Superstar','flag'=>'🇺🇸'],['name'=>'B.B. King','note'=>'King of Blues Guitar','flag'=>'🇺🇸']],
    '09-17'=>[['name'=>'Hank Williams','note'=>'Your Cheatin\' Heart · Country Music Pioneer','flag'=>'🇺🇸']],
    '09-18'=>[['name'=>'Jada Pinkett Smith','note'=>'Actress & The Red Table Talk Host','flag'=>'🇺🇸']],
    '09-19'=>[['name'=>'Jeremy Irons','note'=>'Oscar-winning Actor · Reversal of Fortune','flag'=>'🇬🇧']],
    '09-20'=>[['name'=>'Sophia Loren','note'=>'Oscar-winning Italian Actress · Hollywood Legend','flag'=>'🇮🇹']],
    '09-21'=>[['name'=>'Stephen King','note'=>'Master of Horror · It, The Shining & Carrie','flag'=>'🇺🇸'],['name'=>'Bill Murray','note'=>'Ghostbusters & Lost in Translation · Comedy Legend','flag'=>'🇺🇸']],
    '09-22'=>[['name'=>'Andrea Bocelli','note'=>'Classical Crossover Tenor · Time to Say Goodbye','flag'=>'🇮🇹'],['name'=>'Tom Felton','note'=>'Actor · Draco Malfoy in Harry Potter','flag'=>'🇬🇧']],
    '09-23'=>[['name'=>'Bruce Springsteen','note'=>'The Boss · Born to Run & Born in the USA','flag'=>'🇺🇸'],['name'=>'Ray Charles','note'=>'Father of Soul Music · Georgia on My Mind','flag'=>'🇺🇸']],
    '09-24'=>[['name'=>'Jim Henson','note'=>'Creator of The Muppets & Sesame Street','flag'=>'🇺🇸'],['name'=>'F. Scott Fitzgerald','note'=>'Author of The Great Gatsby','flag'=>'🇺🇸']],
    '09-25'=>[['name'=>'Will Smith','note'=>'The Fresh Prince · Men in Black & Ali','flag'=>'🇺🇸'],['name'=>'Mark Hamill','note'=>'Actor · Luke Skywalker in Star Wars','flag'=>'🇺🇸']],
    '09-26'=>[['name'=>'Serena Williams','note'=>'Greatest Women\'s Tennis Player · 23 Grand Slams','flag'=>'🇺🇸'],['name'=>'T.S. Eliot','note'=>'Nobel Prize Poet · The Waste Land','flag'=>'🇺🇸']],
    '09-27'=>[['name'=>'Gwyneth Paltrow','note'=>'Oscar-winning Actress & Goop Entrepreneur','flag'=>'🇺🇸'],['name'=>'Lil Wayne','note'=>'Young Money Hip-Hop Superstar','flag'=>'🇺🇸']],
    '09-28'=>[['name'=>'Brigitte Bardot','note'=>'French Film Actress & Animal Rights Activist','flag'=>'🇫🇷'],['name'=>'Naomi Watts','note'=>'Actress · Mulholland Drive & King Kong','flag'=>'🇦🇺']],
    '09-29'=>[['name'=>'Miguel de Cervantes','note'=>'Author of Don Quixote · Father of the Modern Novel','flag'=>'🇪🇸']],
    '09-30'=>[['name'=>'Truman Capote','note'=>'Breakfast at Tiffany\'s & In Cold Blood Author','flag'=>'🇺🇸'],['name'=>'Martina Hingis','note'=>'5-time Grand Slam Tennis Champion','flag'=>'🇨🇭']],
    // ── OCTOBER ──
    '10-01'=>[['name'=>'Jimmy Carter','note'=>'39th US President · Nobel Peace Prize 2002','flag'=>'🇺🇸'],['name'=>'Brie Larson','note'=>'Oscar-winning Actress · Captain Marvel','flag'=>'🇺🇸']],
    '10-02'=>[['name'=>'Mahatma Gandhi','note'=>'Father of India · Nonviolent Resistance Pioneer','flag'=>'🇮🇳'],['name'=>'Sting','note'=>'The Police & Solo Legend · Every Breath You Take','flag'=>'🇬🇧']],
    '10-03'=>[['name'=>'Gwen Stefani','note'=>'No Doubt & Hollaback Girl · Rock & Pop Star','flag'=>'🇺🇸'],['name'=>'Stevie Ray Vaughan','note'=>'Texas Blues Guitar Legend','flag'=>'🇺🇸']],
    '10-04'=>[['name'=>'Buster Keaton','note'=>'Silent Film Comedy & Stunt Legend','flag'=>'🇺🇸'],['name'=>'Alicia Silverstone','note'=>'Actress · Clueless','flag'=>'🇺🇸']],
    '10-05'=>[['name'=>'Kate Winslet','note'=>'Oscar-winning Actress · Titanic & Eternal Sunshine','flag'=>'🇬🇧']],
    '10-06'=>[['name'=>'Elisabeth Shue','note'=>'Actress · Leaving Las Vegas','flag'=>'🇺🇸']],
    '10-07'=>[['name'=>'Vladimir Putin','note'=>'President of Russia','flag'=>'🇷🇺'],['name'=>'John Mellencamp','note'=>'Jack & Diane · Rock & Heartland Music Legend','flag'=>'🇺🇸']],
    '10-08'=>[['name'=>'Bruno Mars','note'=>'Uptown Funk & Just the Way You Are · Pop Superstar','flag'=>'🇺🇸'],['name'=>'Sigourney Weaver','note'=>'Actress · Alien & Avatar','flag'=>'🇺🇸']],
    '10-09'=>[['name'=>'John Lennon','note'=>'Imagine · Beatle & Peace Activist','flag'=>'🇬🇧'],['name'=>'Sharon Osbourne','note'=>'Music Manager & The Talk TV Host','flag'=>'🇬🇧']],
    '10-10'=>[['name'=>'Mario Lopez','note'=>'Actor · Saved by the Bell & TV Host','flag'=>'🇺🇸']],
    '10-11'=>[['name'=>'Eleanor Roosevelt','note'=>'US First Lady · Human Rights Activist & UN Delegate','flag'=>'🇺🇸']],
    '10-12'=>[['name'=>'Hugh Jackman','note'=>'Wolverine · X-Men Marvel Hero','flag'=>'🇦🇺'],['name'=>'Luciano Pavarotti','note'=>'Greatest Operatic Tenor of the 20th Century','flag'=>'🇮🇹']],
    '10-13'=>[['name'=>'Paul Simon','note'=>'The Sound of Silence · Simon & Garfunkel Legend','flag'=>'🇺🇸'],['name'=>'Sacha Baron Cohen','note'=>'Borat & Ali G · Comedy Character Creator','flag'=>'🇬🇧']],
    '10-14'=>[['name'=>'Usher','note'=>'Yeah! · R&B Music Superstar','flag'=>'🇺🇸'],['name'=>'Roger Moore','note'=>'James Bond 007 · The Saint Actor','flag'=>'🇬🇧']],
    '10-15'=>[['name'=>'Friedrich Nietzsche','note'=>'Philosopher · Thus Spoke Zarathustra','flag'=>'🇩🇪']],
    '10-16'=>[['name'=>'Oscar Wilde','note'=>'The Picture of Dorian Gray · Playwright & Wit','flag'=>'🇮🇪'],['name'=>'Angela Lansbury','note'=>'Murder, She Wrote · Tony-winning Stage Legend','flag'=>'🇬🇧']],
    '10-17'=>[['name'=>'Eminem','note'=>'Slim Shady · Best-selling Solo Rap Artist of All Time','flag'=>'🇺🇸'],['name'=>'Rita Hayworth','note'=>'Gilda · Golden Age Hollywood Goddess','flag'=>'🇺🇸']],
    '10-18'=>[['name'=>'Chuck Berry','note'=>'Father of Rock \'n\' Roll · Johnny B. Goode','flag'=>'🇺🇸']],
    '10-19'=>[['name'=>'John Lithgow','note'=>'3rd Rock from the Sun & Crown Actor','flag'=>'🇺🇸']],
    '10-20'=>[['name'=>'Snoop Dogg','note'=>'Drop It Like It\'s Hot · Hip-Hop Icon','flag'=>'🇺🇸'],['name'=>'Tom Petty','note'=>'Free Fallin\' · Heartbreakers Rock Legend','flag'=>'🇺🇸']],
    '10-21'=>[['name'=>'Carrie Fisher','note'=>'Princess Leia in Star Wars · Author & Mental Health Advocate','flag'=>'🇺🇸'],['name'=>'Alfred Nobel','note'=>'Inventor of Dynamite · Founder of Nobel Prizes','flag'=>'🇸🇪']],
    '10-22'=>[['name'=>'Jeff Goldblum','note'=>'Actor · Jurassic Park & Independence Day','flag'=>'🇺🇸']],
    '10-23'=>[['name'=>'Ryan Reynolds','note'=>'Deadpool · Actor & Aviation Gin Entrepreneur','flag'=>'🇨🇦'],['name'=>'Michael Crichton','note'=>'Author of Jurassic Park & Westworld Creator','flag'=>'🇺🇸']],
    '10-24'=>[['name'=>'Drake','note'=>'God\'s Plan · Toronto Hip-Hop Global Superstar','flag'=>'🇨🇦'],['name'=>'Kevin Kline','note'=>'Oscar-winning Actor · A Fish Called Wanda','flag'=>'🇺🇸']],
    '10-25'=>[['name'=>'Pablo Picasso','note'=>'Cubism Founder · Guernica & Les Demoiselles d\'Avignon','flag'=>'🇪🇸'],['name'=>'Katy Perry','note'=>'Roar · California Gurls · Pop Superstar','flag'=>'🇺🇸']],
    '10-26'=>[['name'=>'Hillary Clinton','note'=>'US Secretary of State & First Female Presidential Nominee','flag'=>'🇺🇸'],['name'=>'Seth MacFarlane','note'=>'Creator of Family Guy & American Dad','flag'=>'🇺🇸']],
    '10-27'=>[['name'=>'John Cleese','note'=>'Monty Python & Fawlty Towers · British Comedy Genius','flag'=>'🇬🇧'],['name'=>'Sylvia Plath','note'=>'The Bell Jar · Confessional Poet','flag'=>'🇺🇸']],
    '10-28'=>[['name'=>'Bill Gates','note'=>'Co-founder of Microsoft · Global Health Philanthropist','flag'=>'🇺🇸'],['name'=>'Joaquin Phoenix','note'=>'Oscar-winning Actor · Joker & Her','flag'=>'🇺🇸']],
    '10-29'=>[['name'=>'Richard Dreyfuss','note'=>'Oscar-winning Actor · Jaws & Close Encounters','flag'=>'🇺🇸'],['name'=>'Winona Ryder','note'=>'Actress · Beetlejuice & Stranger Things','flag'=>'🇺🇸']],
    '10-30'=>[['name'=>'John Adams','note'=>'2nd President of the United States','flag'=>'🇺🇸'],['name'=>'Ezra Pound','note'=>'Modernist Poet · The Cantos','flag'=>'🇺🇸']],
    '10-31'=>[['name'=>'Willow Smith','note'=>'Whip My Hair · Singer & Actress','flag'=>'🇺🇸'],['name'=>'Vanilla Ice','note'=>'Ice Ice Baby · 90s Pop Rap Icon','flag'=>'🇺🇸']],
    // ── NOVEMBER ──
    '11-01'=>[['name'=>'Aishwarya Rai','note'=>'Miss World 1994 · Bollywood Icon & UNICEF Goodwill Ambassador','flag'=>'🇮🇳']],
    '11-02'=>[['name'=>'Keith Urban','note'=>'Country Music Guitar Star · American Idol Judge','flag'=>'🇦🇺'],['name'=>'Marie Antoinette','note'=>'Last Queen of France before the Revolution','flag'=>'🇫🇷']],
    '11-03'=>[['name'=>'Kendall Jenner','note'=>'Supermodel & Keeping Up with the Kardashians Star','flag'=>'🇺🇸']],
    '11-04'=>[['name'=>'Matthew McConaughey','note'=>'Oscar-winning Actor · Dallas Buyers Club','flag'=>'🇺🇸']],
    '11-05'=>[['name'=>'Bryan Adams','note'=>'(Everything I Do) I Do It for You · Canadian Rock Legend','flag'=>'🇨🇦']],
    '11-06'=>[['name'=>'Ethan Hawke','note'=>'Actor & Director · Before Sunrise & Training Day','flag'=>'🇺🇸'],['name'=>'Glenn Frey','note'=>'Eagles Co-founder · Hotel California','flag'=>'🇺🇸']],
    '11-07'=>[['name'=>'Marie Curie','note'=>'First Woman to Win Nobel Prize · Radioactivity Pioneer','flag'=>'🇵🇱'],['name'=>'Albert Camus','note'=>'Nobel Prize Author · The Stranger & The Plague','flag'=>'🇫🇷']],
    '11-08'=>[['name'=>'Bram Stoker','note'=>'Author of Dracula','flag'=>'🇮🇪'],['name'=>'Bonnie Raitt','note'=>'Something to Talk About · Blues & Rock Guitarist','flag'=>'🇺🇸']],
    '11-09'=>[['name'=>'Carl Sagan','note'=>'Cosmos Author & Astronomer · Pale Blue Dot','flag'=>'🇺🇸'],['name'=>'Hedy Lamarr','note'=>'Actress & Inventor — Pioneer of Wireless Technology','flag'=>'🇦🇹']],
    '11-10'=>[['name'=>'Richard Burton','note'=>'Oscar-nominated Actor · Who\'s Afraid of Virginia Woolf?','flag'=>'🏴󠁧󠁢󠁷󠁬󠁳󠁿'],['name'=>'Neil Young','note'=>'Heart of Gold · Godfather of Grunge','flag'=>'🇨🇦']],
    '11-11'=>[['name'=>'Leonardo DiCaprio','note'=>'Oscar-winning Actor · Titanic & The Revenant','flag'=>'🇺🇸']],
    '11-12'=>[['name'=>'Ryan Gosling','note'=>'Actor · La La Land & Drive','flag'=>'🇨🇦'],['name'=>'Grace Kelly','note'=>'Oscar-winning Actress & Princess of Monaco','flag'=>'🇺🇸']],
    '11-13'=>[['name'=>'Whoopi Goldberg','note'=>'EGOT Winner · Ghost & The Color Purple Actress','flag'=>'🇺🇸'],['name'=>'Robert Louis Stevenson','note'=>'Treasure Island & Strange Case of Dr. Jekyll & Mr. Hyde Author','flag'=>'🏴󠁧󠁢󠁳󠁣󠁴󠁿']],
    '11-14'=>[['name'=>'Jawaharlal Nehru','note'=>'First Prime Minister of India · Children\'s Day Hero','flag'=>'🇮🇳'],['name'=>'Claude Monet','note'=>'Water Lilies · Impressionist Painting Master','flag'=>'🇫🇷']],
    '11-15'=>[['name'=>'Georgia O\'Keeffe','note'=>'Mother of American Modernism · Flower Paintings Icon','flag'=>'🇺🇸']],
    '11-16'=>[['name'=>'Diana Krall','note'=>'Jazz Vocal & Piano Superstar','flag'=>'🇨🇦']],
    '11-17'=>[['name'=>'Martin Scorsese','note'=>'Oscar-winning Director · Goodfellas & The Departed','flag'=>'🇺🇸'],['name'=>'Danny DeVito','note'=>'Actor & Director · Twins & It\'s Always Sunny','flag'=>'🇺🇸']],
    '11-18'=>[['name'=>'Kim Kardashian','note'=>'Media Mogul & SKIMS Entrepreneur','flag'=>'🇺🇸'],['name'=>'Owen Wilson','note'=>'Actor · Wedding Crashers & Midnight in Paris','flag'=>'🇺🇸']],
    '11-19'=>[['name'=>'Jodie Foster','note'=>'2-time Oscar-winning Actress · Silence of the Lambs','flag'=>'🇺🇸'],['name'=>'Meg Ryan','note'=>'When Harry Met Sally · America\'s Sweetheart','flag'=>'🇺🇸']],
    '11-20'=>[['name'=>'Bo Derek','note'=>'10 · 1980s Model & Actress','flag'=>'🇺🇸']],
    '11-21'=>[['name'=>'Goldie Hawn','note'=>'Oscar-winning Actress · Private Benjamin & Bird on a Wire','flag'=>'🇺🇸'],['name'=>'Björk','note'=>'Icelandic Avant-garde Music Pioneer','flag'=>'🇮🇸']],
    '11-22'=>[['name'=>'Scarlett Johansson','note'=>'Black Widow · Marvel\'s Highest-grossing Actress','flag'=>'🇺🇸'],['name'=>'Jamie Lee Curtis','note'=>'Halloween Scream Queen & Oscar-winning Actress','flag'=>'🇺🇸']],
    '11-23'=>[['name'=>'Miley Cyrus','note'=>'Wrecking Ball & Flowers · Hannah Montana to Pop Star','flag'=>'🇺🇸']],
    '11-24'=>[['name'=>'Aung San Suu Kyi','note'=>'Myanmar Nobel Peace Laureate','flag'=>'🇲🇲'],['name'=>'Zachary Taylor','note'=>'12th US President','flag'=>'🇺🇸']],
    '11-25'=>[['name'=>'John F. Kennedy Jr.','note'=>'JFK\'s Son · Magazine Publisher & American Icon','flag'=>'🇺🇸'],['name'=>'Christina Applegate','note'=>'Actress · Married with Children & Dead to Me','flag'=>'🇺🇸']],
    '11-26'=>[['name'=>'Tina Turner','note'=>'Simply the Best · Queen of Rock & Roll','flag'=>'🇺🇸'],['name'=>'Charles Schulz','note'=>'Creator of Peanuts · Charlie Brown & Snoopy','flag'=>'🇺🇸']],
    '11-27'=>[['name'=>'Bruce Lee','note'=>'Enter the Dragon · Martial Arts Icon & Philosopher','flag'=>'🇺🇸'],['name'=>'Jimi Hendrix','note'=>'Purple Haze · Greatest Electric Guitarist in History','flag'=>'🇺🇸']],
    '11-28'=>[['name'=>'Jon Stewart','note'=>'The Daily Show Host · Political Comedy Legend','flag'=>'🇺🇸'],['name'=>'William Blake','note'=>'Tyger Tyger · Romantic Visionary Poet & Artist','flag'=>'🇬🇧']],
    '11-29'=>[['name'=>'Chadwick Boseman','note'=>'Black Panther · Wakanda Forever — Hollywood Inspiration','flag'=>'🇺🇸']],
    '11-30'=>[['name'=>'Mark Twain','note'=>'Tom Sawyer & Huckleberry Finn · America\'s Greatest Humorist','flag'=>'🇺🇸'],['name'=>'Ben Stiller','note'=>'Zoolander & Meet the Parents · Comedy Actor & Director','flag'=>'🇺🇸']],
    // ── DECEMBER ──
    '12-01'=>[['name'=>'Bette Midler','note'=>'Wind Beneath My Wings · Actress & Singer','flag'=>'🇺🇸']],
    '12-02'=>[['name'=>'Britney Spears','note'=>'...Baby One More Time · Pop Princess','flag'=>'🇺🇸'],['name'=>'Lucy Liu','note'=>'Charlie\'s Angels · Actress & Artist','flag'=>'🇺🇸']],
    '12-03'=>[['name'=>'Ozzy Osbourne','note'=>'Black Sabbath Prince of Darkness · Heavy Metal Legend','flag'=>'🇬🇧'],['name'=>'Julianne Moore','note'=>'Oscar-winning Actress · Still Alice & Far from Heaven','flag'=>'🇺🇸']],
    '12-04'=>[['name'=>'Jay-Z','note'=>'Hip-Hop Mogul · 99 Problems & Business is Business','flag'=>'🇺🇸'],['name'=>'Tyra Banks','note'=>'America\'s Next Top Model Host & Supermodel','flag'=>'🇺🇸']],
    '12-05'=>[['name'=>'Walt Disney','note'=>'Mickey Mouse Creator · Disneyland Founder','flag'=>'🇺🇸'],['name'=>'Little Richard','note'=>'Tutti Frutti · Architect of Rock \'n\' Roll','flag'=>'🇺🇸']],
    '12-06'=>[['name'=>'Kirk Douglas','note'=>'Spartacus · Hollywood Golden Age Legend','flag'=>'🇺🇸']],
    '12-07'=>[['name'=>'Noam Chomsky','note'=>'Linguist, Philosopher & Political Activist','flag'=>'🇺🇸']],
    '12-08'=>[['name'=>'Jim Morrison','note'=>'Light My Fire · The Doors Rock Legend','flag'=>'🇺🇸'],['name'=>'Sinead O\'Connor','note'=>'Nothing Compares 2 U · Irish Rock Icon','flag'=>'🇮🇪']],
    '12-09'=>[['name'=>'Judi Dench','note'=>'Oscar-winning Actress · M in James Bond films','flag'=>'🇬🇧']],
    '12-10'=>[['name'=>'Emily Dickinson','note'=>'Because I Could Not Stop for Death · American Poet','flag'=>'🇺🇸'],['name'=>'Kenneth Branagh','note'=>'Actor & Director · Hamlet & Cinderella','flag'=>'🇬🇧']],
    '12-11'=>[['name'=>'Teri Garr','note'=>'Actress · Tootsie & Young Frankenstein','flag'=>'🇺🇸']],
    '12-12'=>[['name'=>'Bob Barker','note'=>'The Price Is Right Host · Animal Rights Champion','flag'=>'🇺🇸'],['name'=>'Dionne Warwick','note'=>'Walk On By · Pop & Soul Vocal Legend','flag'=>'🇺🇸']],
    '12-13'=>[['name'=>'Taylor Swift','note'=>'Shake It Off & Anti-Hero · Most Decorated Pop Artist','flag'=>'🇺🇸']],
    '12-14'=>[['name'=>'Nostradamus','note'=>'16th-century Astrologer & Prophet','flag'=>'🇫🇷'],['name'=>'Vanessa Hudgens','note'=>'Actress · High School Musical','flag'=>'🇺🇸']],
    '12-15'=>[['name'=>'Don Johnson','note'=>'Actor · Miami Vice & Nash Bridges','flag'=>'🇺🇸']],
    '12-16'=>[['name'=>'Ludwig van Beethoven','note'=>'Classical Music Giant · 9 Symphonies · Deaf Composer','flag'=>'🇩🇪']],
    '12-17'=>[['name'=>'Pope Francis','note'=>'First Jesuit Pope · First from Latin America','flag'=>'🇦🇷'],['name'=>'Milla Jovovich','note'=>'Actress · Resident Evil & The Fifth Element','flag'=>'🇺🇦']],
    '12-18'=>[['name'=>'Brad Pitt','note'=>'Oscar-winning Actor · Fight Club & Once Upon a Time in Hollywood','flag'=>'🇺🇸'],['name'=>'Keith Richards','note'=>'Rolling Stones Guitarist · Satisfaction Riff Creator','flag'=>'🇬🇧']],
    '12-19'=>[['name'=>'Jake Gyllenhaal','note'=>'Actor · Brokeback Mountain & Nightcrawler','flag'=>'🇺🇸'],['name'=>'Alyssa Milano','note'=>'Actress & Social Activist · #MeToo Movement','flag'=>'🇺🇸']],
    '12-20'=>[['name'=>'Uri Geller','note'=>'Illusionist & Psychic Entertainer','flag'=>'🇮🇱']],
    '12-21'=>[['name'=>'Samuel L. Jackson','note'=>'Jules Winnfield · Marvel\'s Nick Fury · Hollywood Legend','flag'=>'🇺🇸'],['name'=>'Kiefer Sutherland','note'=>'Actor · 24\'s Jack Bauer','flag'=>'🇨🇦']],
    '12-22'=>[['name'=>'Ralph Fiennes','note'=>'Actor · Lord Voldemort & Schindler\'s List','flag'=>'🇬🇧'],['name'=>'Lady Bird Johnson','note'=>'US First Lady & Environmental Beautification Champion','flag'=>'🇺🇸']],
    '12-23'=>[['name'=>'Eddie Vedder','note'=>'Pearl Jam Lead Singer · Grunge Rock Icon','flag'=>'🇺🇸']],
    '12-24'=>[['name'=>'Ricky Martin','note'=>'Livin\' la Vida Loca · Latin Pop Superstar','flag'=>'🇵🇷'],['name'=>'Ava Gardner','note'=>'The Barefoot Contessa · Golden Age Hollywood Star','flag'=>'🇺🇸']],
    '12-25'=>[['name'=>'Isaac Newton','note'=>'Father of Classical Mechanics & Laws of Gravity','flag'=>'🇬🇧'],['name'=>'Humphrey Bogart','note'=>'Casablanca · Hollywood\'s Greatest Film Noir Star','flag'=>'🇺🇸']],
    '12-26'=>[['name'=>'Mao Zedong','note'=>'Founder of the People\'s Republic of China','flag'=>'🇨🇳'],['name'=>'David Sedaris','note'=>'Me Talk Pretty One Day · Humor Essayist','flag'=>'🇺🇸']],
    '12-27'=>[['name'=>'Marlene Dietrich','note'=>'German-American Hollywood Actress & Singer','flag'=>'🇩🇪']],
    '12-28'=>[['name'=>'Denzel Washington','note'=>'2-time Oscar-winning Actor · Training Day & Fences','flag'=>'🇺🇸'],['name'=>'Stan Lee','note'=>'Co-creator of Spider-Man, Iron Man & X-Men · Marvel Legend','flag'=>'🇺🇸']],
    '12-29'=>[['name'=>'Jude Law','note'=>'Actor · Sherlock Holmes & Cold Mountain','flag'=>'🇬🇧'],['name'=>'Ted Danson','note'=>'Actor · Cheers & The Good Place','flag'=>'🇺🇸']],
    '12-30'=>[['name'=>'Tiger Woods','note'=>'15-time Major Golf Champion · Greatest Golfer of All Time','flag'=>'🇺🇸'],['name'=>'Rudyard Kipling','note'=>'The Jungle Book Author · Nobel Prize 1907','flag'=>'🇬🇧']],
    '12-31'=>[['name'=>'Henri Matisse','note'=>'Fauvist Art Master · The Dance Painting','flag'=>'🇫🇷'],['name'=>'Ben Kingsley','note'=>'Oscar-winning Actor · Gandhi','flag'=>'🇬🇧']],
];

$famous_matches = [];
if ($result && $result['birthdate']) {
    $bd_obj = new DateTime($result['birthdate']);
    $bd_key = $bd_obj->format('m-d');
    $famous_matches = $famous_birthdays[$bd_key] ?? [];
}

// Admin block content/labels
$_blk=[];
if ($db_ok) {
    $_br=$conn->query("SELECT block_key,block_label,content,sort_order,silver,gold,platinum FROM bio_block_config");
    if($_br) while($_row=$_br->fetch_assoc()) $_blk[$_row['block_key']]=$_row;
}

// Visitor's rank comes only from a signed-in member session (name + date of birth
// typed into the address bar no longer unlocks Gold/Platinum content).
$_userLevel = 'silver';
try {
    $_suid = (int)($_SESSION['bio_uid'] ?? 0);
    if ($db_ok && $_suid > 0 && empty($_SESSION['bio_need_pin'])) {
        $_urow2 = $conn->query("SELECT level FROM bio_users WHERE id=$_suid LIMIT 1")->fetch_assoc();
        if ($_urow2 && $_urow2['level']) $_userLevel = $_urow2['level'];
    }
} catch (Throwable $e) {}
// HACKATHON (new, Step 3): member's saved birth date pre-fills the lifeline
// graph picker when the site has it; isolated query so level logic is untouched.
$_hackDob = null;
try {
    $_hsuid = (int)($_SESSION['bio_uid'] ?? 0);
    if ($db_ok && $_hsuid > 0 && empty($_SESSION['bio_need_pin'])) {
        $_hdrow = $conn->query("SELECT dob FROM bio_users WHERE id=$_hsuid LIMIT 1");
        if ($_hdrow) {
            $_hdrow = $_hdrow->fetch_assoc();
            if ($_hdrow && !empty($_hdrow['dob']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_hdrow['dob'])) $_hackDob = $_hdrow['dob'];
        }
    }
} catch (Throwable $e) {}
// Security token for oracle_api.php (shared with biorhythm.php)
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
function _blkV(string $key): bool {
    global $_blk, $_userLevel;
    if (!isset($_blk[$key])) return true;
    return isset($_blk[$key][$_userLevel]) ? (bool)$_blk[$key][$_userLevel] : true;
}
function _blkC(string $key):string{
    global $_blk;
    $h=$_blk[$key]['content']??'';
    return $h?'<div class="blk-custom-content" style="font-size:.9rem;margin-bottom:.85rem">'.$h.'</div>':'';
}
function _blkL(string $key,string $def):string{
    global $_blk;
    $label = $_blk[$key]['block_label'] ?? $def;
    if ($key === 'idx_mahabote' && in_array($label, ['MAHABOTE — REAL-TIME GUIDANCE', 'Horary Oracle — Myanmar Arudha Reading'], true)) {
        $label = 'Instant guidance for your question';
    }
    return htmlspecialchars($label,ENT_QUOTES);
}
function _blkO(string $key,int $def):int{
    global $_blk;
    return isset($_blk[$key]['sort_order']) ? (int)$_blk[$key]['sort_order'] : $def;
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
<title>DharmaVera | Ancient Oracles and Personal Biorhythm</title>
<meta name="description" content="Ask a question through seven oracle traditions, then explore your personal biorhythm chart with DharmaVera.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Color+Emoji&family=Noto+Sans+Myanmar:wght@400;700&display=swap">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
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
  .mm-text { font-family: 'Noto Sans Myanmar', sans-serif; font-size: 1.05rem; }

  /* ── Scripture Oracle ── */
  .scrip-slot {
    width:44px;height:44px;background:var(--bg-card2);
    border:1.5px solid var(--border);border-radius:10px;
    display:flex;align-items:center;justify-content:center;
    font-size:1.1rem;font-weight:700;color:var(--muted);
    transition:border-color .2s,color .2s;
  }
  .scrip-slot.filled { color:#e2e8f0; border-color:#a78bfa; }

  /* ── Responsive Refinements ── */
  @media(max-width:576px) {
    .hero h1 { font-size: 1.7rem; }
    .score-ring { width: 55px; height: 55px; font-size: 1.2rem; }
    .overall-ring { width: 100px; height: 100px; }
    .container { padding-left: 16px; padding-right: 16px; }
  }
</style>
<link rel="stylesheet" href="dharmavera.css?v=12">
</head>
<body>

<!-- DHARMAVERA HERO -->
<div class="hero">
  <div class="container dv-hero-grid">
    <img class="dv-logo dv-hero-logo" src="assets/brand/logo-400.png?v=1" width="400" height="400" alt="DharmaVera logo">
    <div class="dv-hero-head">
      <p class="dv-eyebrow">✦ DHARMAVERA · A MOMENT FOR REFLECTION ✦</p>
      <h1>Ancient oracles.<br><em style="color:#b9d8bd">Modern clarity.</em></h1>
    </div>
    <p class="dv-hero-copy">Bring a question to the present moment. Explore seven traditions through one thoughtful reading.</p>
    <div class="dv-hero-cta">
      <div class="dv-hero-actions">
        <a class="dv-primary" href="#live-mahabote">Begin a reading <span aria-hidden="true">↗</span></a>
        <a class="dv-secondary" href="biorhythm.php">Build my personal biorhythm chart <span aria-hidden="true">→</span></a>
      </div>
      <p class="dv-hero-note">Open to everyone · For entertainment and reflection</p>
    </div>
  </div>
</div>

<div class="container py-4" style="max-width:900px;display:flex;flex-direction:column">

<?php if ($error): ?>
  <div class="alert alert-danger mb-4" style="order:-2"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- ── INPUT FORM ── -->
<div class="card-dark p-4 mb-4" style="order:<?= _blkO('idx_form',0) ?>;display:none">
  <div class="section-title"><i class="bi bi-person-circle me-1"></i><?= _blkL('idx_form','Your Details') ?></div>
<?= _blkC('idx_form') ?>
  <form method="POST" novalidate>
    <div class="row g-3">
      <div class="col-sm-6">
        <label class="form-label">Full Name</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-person"></i></span>
          <input type="text" name="name" class="form-control" placeholder="Enter your name"
                 value="<?= htmlspecialchars($_FORM['name'] ?? '') ?>" required>
        </div>
      </div>
      <div class="col-sm-6">
        <label class="form-label">Birth Date</label>
        <input type="date" name="birthdate" class="form-control"
               style="color-scheme:dark"
               value="<?= htmlspecialchars($_FORM['birthdate'] ?? '') ?>" required>
      </div>
      <div class="col-sm-6">
        <label class="form-label">Birth Time <small class="text-muted">(optional)</small></label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-clock"></i></span>
          <input type="time" name="birthtime" class="form-control"
                 value="<?= htmlspecialchars($_FORM['birthtime'] ?? '') ?>">
        </div>
      </div>
      <div class="col-12 text-center mt-1">
        <button type="submit" class="btn btn-primary px-5">
          <i class="bi bi-stars me-2"></i>Calculate My Reading
        </button>
      </div>
    </div>
  </form>
</div>

<!-- ══════════════════════════════════════════════════ -->
<!-- ── HORARY ORACLE (Arudha / Puzza Reading) ── -->
<!-- ══════════════════════════════════════════════════ -->
<div class="card-dark p-4 mb-4" id="live-mahabote" style="order:<?= _blkO('idx_mahabote',1) ?>;display:<?= _blkV('idx_mahabote')?'block':'none' ?>">
  <div class="section-title mb-2"><i class="bi bi-stars me-2"></i>Instant Answers from Ancient Wisdom</div>
<?= _blkC('idx_mahabote') ?>

  <div class="dv-oracle-intro">
    <strong>Keep your true question in your heart—there is no need to type your deepest secrets here.</strong>
    <p class="mb-0 mt-2">In traditional divination (<strong>Arudha Puzza</strong>), the universe speaks through the exact moment you ask. Breathe, tune into your surroundings, and notice what appears around you: a passing sound, a sudden thought, or numbers that catch your eye. Pick four numbers that call to you to unlock guidance synthesized from Mahabote, Kau Cim, Tehillim (Psalms), the Bible, the Quran, Ram Shalaka, and Gypsy Cards wisdom.</p>
  </div>

  <div style="max-width:680px;margin:0 auto 1.1rem">
    <label for="oracle-question" style="display:block;font-size:.72rem;color:var(--muted);letter-spacing:1px;font-weight:700;text-transform:uppercase;margin-bottom:.15rem;text-align:center">Question Title / Memo</label>
    <div style="font-size:.72rem;color:var(--muted);text-align:center;margin-bottom:.45rem">Optional—just a personal note to remember this reading</div>
    <textarea id="oracle-question" class="form-control" maxlength="500" rows="2" placeholder="e.g. New job, family trip, business idea…" style="resize:vertical;min-height:66px"></textarea>
    <div style="font-size:.68rem;color:var(--muted);text-align:center;margin-top:.35rem"><i class="bi bi-shield-lock me-1"></i>Your memo is saved to your private Oracle Pulse only after you log in.</div>
  </div>

  <!-- Step 1: Omen selector -->
  <div style="margin-bottom:1.1rem">
    <div style="font-size:.72rem;color:var(--muted);letter-spacing:1px;font-weight:700;text-transform:uppercase;margin-bottom:.25rem;text-align:center">
      Step 1 — What did you notice when your question arose?
    </div>
    <div style="font-size:.76rem;color:#b7c9bf;font-style:italic;text-align:center;margin-bottom:.65rem">Tune into your surroundings and tap only what you genuinely hear.</div>
    <div style="display:flex;flex-wrap:wrap;gap:.5rem;justify-content:center">
      <?php
      $omens = [
        'normal'       => ['🔕','Calm / Nothing special'],
        'good_sound'   => ['🎵','Auspicious sound (music, bells, laughter)'],
        'wind_blowing' => ['🌬️','A pleasant breeze or wind'],
        'child_crying' => ['😢','A child crying or a dog barking nearby'],
        'glass_breaking'=>['💔','Glass or dishes breaking, or an accident sound'],
      ];
      foreach($omens as $key => $o): ?>
      <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;font-size:.82rem;color:#ccc;
                    background:var(--bg-card2);border:1.5px solid var(--border);border-radius:8px;padding:.35rem .65rem">
        <input type="radio" name="horary-omen" value="<?= $key ?>" <?= $key==='normal'?'checked':'' ?>
               style="accent-color:var(--accent)">
        <?= $o[0] ?> <?= $o[1] ?>
      </label>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Step 2: Unified 4-tap oracle keypad -->
  <div style="font-size:.72rem;color:var(--muted);letter-spacing:1px;font-weight:700;text-transform:uppercase;margin-bottom:.35rem;text-align:center">
    Step 2 — Tap 4 numbers
  </div>
  <div style="font-size:.78rem;color:#aaa;text-align:center;margin-bottom:.9rem;line-height:1.65">
    Your numbers begin the seven-oracle reading: Mahabote, Kau Cim, Tehillim, Bible, Quran, Ram Shalaka, and Gypsy Cards.<br>
    <span style="font-size:.72rem;color:var(--muted)">(First number must be 1–9 for Mahabote)</span>
  </div>

  <!-- 4 tap slots -->
  <div id="unified-slots" style="display:flex;gap:.65rem;justify-content:center;margin-bottom:.9rem">
    <div class="scrip-slot" id="u-s1" title="Mahabote">?</div>
    <div class="scrip-slot" id="u-s2" title="Quran">?</div>
    <div class="scrip-slot" id="u-s3" title="Quran">?</div>
    <div class="scrip-slot" id="u-s4" title="Bible">?</div>
  </div>

  <!-- Phone-style keypad 1-9 then 0 -->
  <div id="unified-keypad" style="display:grid;grid-template-columns:repeat(5,1fr);gap:.5rem;max-width:320px;margin:0 auto 1rem">
    <?php foreach([1,2,3,4,5,6,7,8,9,0] as $dn): ?>
    <button class="unified-btn" id="ubtn-<?= $dn ?>" onclick="unifiedClick(<?= $dn ?>)"
      data-firstonly="<?= ($dn===0)?'1':'0' ?>"
      style="background:var(--bg-card2);border:1.5px solid var(--border);border-radius:10px;
             color:#fff;font-size:1.25rem;font-weight:700;padding:.55rem 0;cursor:pointer;
             transition:background .15s,border-color .15s"
      onmouseover="if(!this.disabled){this.style.borderColor='var(--accent)';this.style.background='rgba(99,179,237,.12)'}"
      onmouseout="if(!this.disabled){this.style.borderColor='var(--border)';this.style.background='var(--bg-card2)'}"><?= $dn ?></button>
    <?php endforeach; ?>
  </div>

  <div id="cooldown-msg" style="font-size:.82rem;color:var(--muted);text-align:center;margin-bottom:.5rem;display:none"></div>
  <div id="mhb-result" style="display:none"></div>

  <!-- ── Step 4: Kau Cim Cylinder (revealed after 4-tap completes) ── -->
  <div id="kau-cim-step" style="border-top:1px solid var(--border);margin-top:1.75rem;padding-top:1.5rem;display:none">
    <div style="font-size:.72rem;color:var(--muted);letter-spacing:1px;font-weight:700;text-transform:uppercase;margin-bottom:.35rem;text-align:center">
      Step 4 — Shake the Cylinder
    </div>
    <div style="font-size:.6rem;color:#d4a017;letter-spacing:1.5px;font-weight:700;text-transform:uppercase;text-align:center;margin-bottom:.45rem">
      签筒 · Chinese Kau Cim — 100 Fortune Sticks
    </div>
    <div style="font-size:.8rem;color:#ccc;text-align:center;line-height:1.65;margin-bottom:1.1rem">
      Focus on the same question. Shake the bamboo cylinder — one of 100 sticks will fall and complete your reading.
    </div>
    <div style="text-align:center;margin-bottom:.5rem">
      <button id="kau-cim-btn" onclick="drawKauCim()"
        style="background:linear-gradient(155deg,#3d6b35,#5a9950);border:2px solid #7dc569;border-radius:14px;
               padding:1rem 2rem;cursor:pointer;color:#fff;font-size:.95rem;font-weight:700;
               box-shadow:0 4px 18px rgba(93,179,80,.2);transition:transform .12s,box-shadow .12s"
        onmouseover="this.style.transform='scale(1.05)';this.style.boxShadow='0 6px 24px rgba(93,179,80,.35)'"
        onmouseout="this.style.transform='';this.style.boxShadow='0 4px 18px rgba(93,179,80,.2)'">
        <div style="font-size:2.2rem;margin-bottom:.3rem;line-height:1">🎋</div>
        Shake the Cylinder · 求签
        <div style="font-size:.7rem;font-weight:400;color:#c8e6c2;margin-top:.25rem">100 sticks · Click to draw your fortune</div>
      </button>
    </div>
  </div>

  <!-- ── Step 5: Gypsy Cards (revealed after Kau Cim drawn) ── -->
  <div id="tarot-step" style="border-top:1px solid var(--border);margin-top:1.75rem;padding-top:1.5rem;display:none">
    <style>@keyframes gwSpin{0%,100%{transform:rotate(-5deg) scale(1)}25%{transform:rotate(5deg) scale(1.04)}50%{transform:rotate(-2deg) scale(.98)}75%{transform:rotate(4deg) scale(1.03)}}</style>
    <div style="font-size:.72rem;color:var(--muted);letter-spacing:1px;font-weight:700;text-transform:uppercase;margin-bottom:.35rem;text-align:center">
      Step 5 — Draw Your Fortune Card
    </div>
    <div style="font-size:.6rem;color:#c792ea;letter-spacing:1.5px;font-weight:700;text-transform:uppercase;text-align:center;margin-bottom:.45rem">
      🃏 Gypsy Cards · Fortune Telling · 55-Card Deck
    </div>
    <div style="font-size:.8rem;color:#ccc;text-align:center;line-height:1.65;margin-bottom:1rem">
      Hold your question in mind. Click the deck to draw your fortune card.
    </div>
    <div style="text-align:center;margin-bottom:.5rem">
      <button id="tarot-btn" onclick="drawTarotCard()"
        style="background:none;border:none;cursor:pointer;padding:.5rem;display:inline-block"
        title="Draw your fortune card">
        <svg id="tarot-deck-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 66 90" width="56" height="76"
             style="display:block;margin:0 auto;animation:gwSpin 2.8s ease-in-out infinite;filter:drop-shadow(0 4px 14px rgba(199,146,234,.4))">
          <rect x="6" y="5" width="54" height="80" rx="5" fill="#26124a" opacity=".5"/>
          <rect x="4" y="3" width="54" height="80" rx="5" fill="#2e1555" opacity=".7"/>
          <rect x="2" y="1" width="54" height="80" rx="5" fill="#3b1a6e" stroke="#7c3aed" stroke-width="1.5"/>
          <rect x="6" y="5" width="46" height="72" rx="3" fill="none" stroke="#c792ea" stroke-width=".7" opacity=".55"/>
          <polygon points="29,16 31.2,23 38.5,23 32.8,27.2 35,34 29,30 23,34 25.2,27.2 19.5,23 26.8,23" fill="#ffd700" opacity=".85"/>
          <polygon points="29,10 30.2,14 29,18 27.8,14" fill="#c792ea" opacity=".65"/>
          <polygon points="29,58 30.2,62 29,66 27.8,62" fill="#c792ea" opacity=".65"/>
          <polygon points="7,38 11,39.5 15,38 11,36.5" fill="#c792ea" opacity=".65"/>
          <polygon points="43,38 47,39.5 51,38 47,36.5" fill="#c792ea" opacity=".65"/>
          <text x="7" y="14" font-size="6" fill="#c792ea" opacity=".7" font-family="serif">♠</text>
          <text x="46" y="14" font-size="6" fill="#c792ea" opacity=".7" font-family="serif">♥</text>
          <text x="7" y="73" font-size="6" fill="#c792ea" opacity=".7" font-family="serif">♦</text>
          <text x="46" y="73" font-size="6" fill="#c792ea" opacity=".7" font-family="serif">♣</text>
        </svg>
        <div style="font-size:.7rem;color:#c792ea;margin-top:.4rem;font-weight:600;letter-spacing:.4px">Click to Draw</div>
        <div style="font-size:.58rem;color:var(--muted);margin-top:.1rem">55 cards · 1 draw per session</div>
      </button>
    </div>
    <div id="tarot-result" style="display:none;margin-top:.25rem"></div>
  </div>
</div>
<!-- HACKATHON DEMO WORK (new): saved wave chart + overlay toggles. Every reading (date + overall score) is saved per browser in localStorage (guests included, no login). Member server history is untouched. Overlays are manual-only and never change reading scores. -->
<div class="card-dark p-4 mb-4" id="hackathon-wave" style="order:2">
  <div class="section-title"><i class="bi bi-water me-1"></i>Reading wave <span style="font-size:.65rem;font-weight:700;color:#8b949e;letter-spacing:1px;border:1px solid var(--border);border-radius:20px;padding:.1rem .6rem;margin-left:.4rem">HACKATHON DEMO</span></div>
  <p style="font-size:.8rem;color:var(--muted);line-height:1.6;margin:0 0 .8rem">Every reading is saved in this browser (date + overall score) - no login needed. Members' server history is unchanged.</p>
  <div class="d-flex gap-2 flex-wrap mb-2" role="group" aria-label="Wave chart range">
    <button type="button" class="btn btn-outline-secondary btn-sm active" data-hack-view="1m" aria-pressed="true">1 month</button>
    <button type="button" class="btn btn-outline-secondary btn-sm" data-hack-view="1y" aria-pressed="false">1 year</button>
    <button type="button" class="btn btn-outline-secondary btn-sm" data-hack-view="2y" aria-pressed="false">2 years</button>
  </div>
  <div style="position:relative;height:260px">
    <canvas id="hackathon-wave-canvas"></canvas>
  </div>
  <div id="hackathon-wave-fallback" style="display:none;font-size:.8rem;color:#f0ad4e;margin-top:.5rem">Chart library not loaded - your readings are still saved in this browser.</div>
  <div id="hackathon-wave-caption" style="font-size:.78rem;color:var(--muted);line-height:1.6;margin-top:.6rem"></div>
  <div class="mt-3 pt-3" style="border-top:1px solid var(--border)">
    <div style="font-size:.72rem;color:var(--muted);letter-spacing:1px;font-weight:700;text-transform:uppercase;margin-bottom:.5rem">Compare (optional overlays)</div>
    <div class="d-flex gap-3 flex-wrap mb-2">
      <label style="display:flex;align-items:center;gap:.45rem;cursor:pointer;text-transform:none;letter-spacing:0;font-size:.85rem;color:#ccc;font-weight:400"><input type="checkbox" id="hackathon-ov-fb" style="accent-color:#4FC3F7;width:1rem;height:1rem"> Facebook likes per day</label>
      <label style="display:flex;align-items:center;gap:.45rem;cursor:pointer;text-transform:none;letter-spacing:0;font-size:.85rem;color:#ccc;font-weight:400"><input type="checkbox" id="hackathon-ov-money" style="accent-color:#74b816;width:1rem;height:1rem"> Monetization income per day</label>
    </div>
    <div style="font-size:.75rem;color:var(--muted);margin-bottom:.7rem">Manual entries only - no Facebook connection or API. Overlays draw their own lines and never change your reading scores or wave.</div>
    <div class="row g-2 align-items-end mb-2">
      <div class="col-12 col-sm-4">
        <label class="form-label" for="hackathon-ov-series">Series</label>
        <select id="hackathon-ov-series" class="form-select form-select-sm">
          <option value="fb">Facebook likes per day</option>
          <option value="money">Monetization income per day</option>
        </select>
      </div>
      <div class="col-6 col-sm-3">
        <label class="form-label" for="hackathon-ov-date">Date</label>
        <input type="date" id="hackathon-ov-date" class="form-control form-control-sm" style="color-scheme:dark">
      </div>
      <div class="col-6 col-sm-3">
        <label class="form-label" for="hackathon-ov-value">Number</label>
        <input type="number" id="hackathon-ov-value" class="form-control form-control-sm" min="0" step="any" placeholder="e.g. 120">
      </div>
      <div class="col-12 col-sm-2">
        <button type="button" class="btn btn-outline-secondary btn-sm w-100" id="hackathon-ov-add">Add</button>
      </div>
    </div>
    <div id="hackathon-ov-msg" style="display:none;font-size:.8rem;margin-bottom:.5rem"></div>
    <div class="row g-3">
      <div class="col-12 col-sm-6">
        <div style="font-size:.72rem;color:#4FC3F7;font-weight:700;margin-bottom:.25rem">Facebook likes per day</div>
        <div id="hackathon-ov-fb-list"></div>
      </div>
      <div class="col-12 col-sm-6">
        <div style="font-size:.72rem;color:#74b816;font-weight:700;margin-bottom:.25rem">Monetization income per day</div>
        <div id="hackathon-ov-money-list"></div>
      </div>
    </div>
  </div>
</div>
<?php
// HACKATHON (new, Step 3): overall score per animal, same averaging as the site's trait display (mean of trait scores, 1 decimal).
$_hackCzOverall = [];
foreach ($chinese_zodiac as $_hci => $_hcz) $_hackCzOverall[$_hci] = round(array_sum($_hcz['scores']) / count($_hcz['scores']), 1);
ksort($_hackCzOverall);
$_hackCzOverall = array_values($_hackCzOverall);
?>
<script>window.DV_CZ_OVERALL = <?= json_encode($_hackCzOverall) ?>;</script>
<!-- HACKATHON (new, Step 3): separate lifetime graph. ONE thick sine line for the visitor's own animal only. NOT an overlay on the Reading Wave, never merged into any score. No trait breakdown, no trait numbers, no ranking, no personality text. -->
<div class="card-dark p-4 mb-4" id="hackathon-life" style="order:3">
  <div class="section-title"><i class="bi bi-graph-up me-1"></i>Lifetime Graph <span style="font-size:.65rem;font-weight:700;color:#8b949e;letter-spacing:1px;border:1px solid var(--border);border-radius:20px;padding:.1rem .6rem;margin-left:.4rem">HACKATHON DEMO</span></div>
  <p style="font-size:.8rem;color:var(--muted);line-height:1.6;margin:0 0 .8rem">One sine line for your own Chinese zodiac animal across a lifetime. Separate from the Reading Wave - it never changes any reading score.</p>
  <div class="row g-2 align-items-end mb-2">
    <div class="col-12 col-sm-5">
      <label class="form-label" for="hackathon-life-date">Birth date</label>
      <input type="date" id="hackathon-life-date" class="form-control form-control-sm" style="color-scheme:dark" value="<?= htmlspecialchars($_hackDob ?? ($result['birthdate'] ?? ''), ENT_QUOTES) ?>">
    </div>
    <div class="col-6 col-sm-3">
      <button type="button" class="btn btn-outline-secondary btn-sm w-100" id="hackathon-life-draw">Draw</button>
    </div>
    <div class="col-6 col-sm-2">
      <button type="button" class="btn btn-outline-secondary btn-sm w-100" id="hackathon-life-clear">Clear</button>
    </div>
  </div>
  <div style="font-size:.75rem;color:var(--muted);margin-bottom:.5rem"><?= !empty($_hackDob) ? 'Using your saved date of birth.' : 'No login needed - just pick a birth date.' ?></div>
  <div id="hackathon-life-msg" style="display:none;font-size:.8rem;color:#e05252;margin-bottom:.5rem"></div>
  <div style="position:relative;height:260px">
    <canvas id="hackathon-life-canvas"></canvas>
  </div>
  <div id="hackathon-life-fallback" style="display:none;font-size:.8rem;color:#f0ad4e;margin-top:.5rem">Chart library not loaded - the lifeline needs the chart library to draw.</div>
  <div id="hackathon-life-info" style="font-size:.82rem;color:#ccc;line-height:1.7;margin-top:.6rem"></div>
  <div id="hackathon-life-caption" style="font-size:.78rem;color:var(--muted);line-height:1.6;margin-top:.6rem">New hackathon visualization, not traditional Chinese astrology. Chinese tradition does not draw this sine line, we do. This chart describes a trajectory over a lifetime, it does not predict the future, and it never changes a reading score.</div>
</div>

<?php if ($result): ?>
<?php
  $r = $result;
  $shareUrl   = SITE_ORIGIN.'/?name='.urlencode($r['name']).'&birthdate='.urlencode($r['birthdate']);
  $shareText  = '✨ My DharmaVera reading: I am '.$r['mahabote']['name'].' ('.$r['mahabote']['mm'].') · Overall Score '.$r['overall'].'/10 — Check yours!';
  $shareTitle = 'My DharmaVera Reading';
?>

<!-- ── Famous Birthday Banner ── -->
<?php if (!empty($famous_matches)): ?>
<div class="famous-banner" style="order:<?= _blkO('idx_famous',7) ?>;display:<?= _blkV('idx_famous')?'flex':'none' ?>">
  <div class="fb-medal" aria-hidden="true"><i class="bi bi-star-fill"></i></div>
  <div class="flex-grow-1">
    <div class="fb-kicker">You share a birthday with</div>
    <ul class="fb-people">
      <?php foreach ($famous_matches as $fm): ?>
      <li>
        <span class="fb-flag"><?= $fm['flag'] ?></span>
        <span><span class="fb-name"><?= htmlspecialchars($fm['name']) ?></span><span class="fb-note"><?= htmlspecialchars($fm['note']) ?></span></span>
      </li>
      <?php endforeach; ?>
    </ul>
    <span class="fb-date"><i class="bi bi-calendar-heart"></i> Born on <?= (new DateTime($r['birthdate']))->format('F j') ?></span>
  </div>
</div>
<?php endif; ?>

<!-- ── Share / PDF panel ── -->
<div class="share-panel no-print" style="order:<?= _blkO('idx_share',20) ?>;display:<?= _blkV('idx_share')?'block':'none' ?>">
  <div class="share-head">
    <div class="share-title"><i class="bi bi-share"></i> Share your reading</div>
    <div class="share-sub">Send your DharmaVera profile to friends and family, or save it as a PDF.</div>
  </div>
  <div class="share-bar">
    <a class="share-btn fb" href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($shareUrl) ?>"
       target="_blank" rel="noopener" onclick="logShare()" title="Share on Facebook" aria-label="Share on Facebook">
      <span class="share-ic"><i class="bi bi-facebook"></i></span><span class="share-lbl">Facebook</span>
    </a>
    <a class="share-btn msg" href="fb-messenger://share?link=<?= urlencode($shareUrl) ?>"
       onclick="logShare()" title="Share on Messenger" aria-label="Share on Messenger">
      <span class="share-ic"><i class="bi bi-messenger"></i></span><span class="share-lbl">Messenger</span>
    </a>
    <a class="share-btn wa" href="https://wa.me/?text=<?= urlencode($shareText.' '.$shareUrl) ?>"
       target="_blank" rel="noopener" onclick="logShare()" title="Share on WhatsApp" aria-label="Share on WhatsApp">
      <span class="share-ic"><i class="bi bi-whatsapp"></i></span><span class="share-lbl">WhatsApp</span>
    </a>
    <a class="share-btn x" href="https://twitter.com/intent/tweet?text=<?= urlencode($shareText) ?>&url=<?= urlencode($shareUrl) ?>"
       target="_blank" rel="noopener" onclick="logShare()" title="Share on X" aria-label="Share on X">
      <span class="share-ic"><i class="bi bi-twitter-x"></i></span><span class="share-lbl">X</span>
    </a>
    <a class="share-btn em" href="mailto:?subject=<?= urlencode($shareTitle) ?>&body=<?= urlencode($shareText."\n\n".$shareUrl) ?>"
       onclick="logShare()" title="Share by email" aria-label="Share by email">
      <span class="share-ic"><i class="bi bi-envelope-fill"></i></span><span class="share-lbl">Email</span>
    </a>
    <a class="share-btn copy" href="#" onclick="copyLink('<?= htmlspecialchars($shareUrl, ENT_QUOTES) ?>'); return false;" title="Copy link" aria-label="Copy link">
      <span class="share-ic"><i class="bi bi-link-45deg"></i></span><span class="share-lbl">Copy link</span>
    </a>
    <a class="share-btn pdf" href="#" onclick="printPDF(); return false;" title="Save as PDF" aria-label="Save as PDF">
      <span class="share-ic"><i class="bi bi-file-earmark-pdf-fill"></i></span><span class="share-lbl">PDF</span>
    </a>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════ -->
<!-- ── ASTROLOGICAL DAY GUIDE: Yatyaza / Pyathada / Tithi ── -->
<!-- ══════════════════════════════════════════════════════════ -->
<?php
function renderDayGuide($g, $dateLabel) {
    if (!$g) return;
    if ($g['yatyaza'])          { $type='yatyaza';  $typeMM='ရက်ရာဇာ';  $typeLabel='Yatyaza — Royal Day';  $typeColor='#51cf66'; $typeBg='#0d2a0d'; $typeIcon='👑'; }
    elseif ($g['pyathada'] == 2){ $type='pyathada'; $typeMM='ပြဿဒါး';  $typeLabel='Pyathada (Afternoon)'; $typeColor='#e05252'; $typeBg='#2a0d0d'; $typeIcon='⚠️'; }
    elseif ($g['pyathada'])     { $type='pyathada'; $typeMM='ပြဿဒါး';  $typeLabel='Pyathada';             $typeColor='#e05252'; $typeBg='#2a0d0d'; $typeIcon='⚠️'; }
    elseif ($g['is_sabbath'])   { $type='sabbath';  $typeMM='တိထိ';     $typeLabel='Tithi — Sabbath Day';  $typeColor='#f0ad4e'; $typeBg='#2a1e00'; $typeIcon='🌙'; }
    else                        { $type='regular';  $typeMM='ပုံမှန်';   $typeLabel='Regular Day';          $typeColor='#48cae4'; $typeBg='#0d2530'; $typeIcon='📅'; }

    $doList = $dontList = [];
    if ($type == 'yatyaza') {
        $doList   = ['Start new ventures & businesses','Hold weddings & ceremonies','Sign contracts & agreements','Move into a new home','Shopping & important purchases','Long-distance travel'];
        $dontList = ['No restrictions — all major activities are considered safe'];
    } elseif ($type == 'pyathada') {
        $doList   = ['Meditation & spiritual practice','Reading & self-study','Quiet personal reflection','Routine day-to-day tasks'];
        $dontList = ['Starting a new job or business','Signing contracts','Long-distance travel','Weddings or moving houses','Major purchases'];
    } elseif ($type == 'sabbath') {
        $doList   = ['Religious activities & prayers','Merit-making & offerings','Temple visits','Mental peace & meditation'];
        $dontList = ['Travel & moving homes'.($g['is_lent'] ? ' (Buddhist Lent — especially avoid)' : ''),'Major business decisions','Starting new ventures'];
    } else {
        $doList   = ['Regular daily activities','Routine work & business'];
        $dontList = [];
        if ($g['is_lent']) $dontList[] = 'Travel & moving homes (Buddhist Lent month — '.$g['month_en'].' — caution advised)';
    }
?>
<div class="card-dark p-4 mb-4" style="order:<?= _blkO('idx_dayguide',3) ?>;display:<?= _blkV('idx_dayguide')?'block':'none' ?>">
  <div class="section-title"><i class="bi bi-stars me-1"></i><?= _blkL('idx_dayguide','Astrological Day Guide — ရက်ရာဇာ · ပြဿဒါး · တိထိ') ?></div>
<?= _blkC('idx_dayguide') ?>

  <!-- Date checker form -->
  <?php
    $todayUrl = '?' . http_build_query(array_filter([
        'name'      => $_GET['name']      ?? '',
        'birthdate' => $_GET['birthdate'] ?? '',
        'birthtime' => $_GET['birthtime'] ?? '',
        'today'     => $_GET['today']     ?? '',
    ], fn($v) => $v !== ''));
  ?>
  <form method="GET" class="d-flex gap-2 align-items-center flex-wrap mb-3" style="max-width:520px">
    <?php foreach(['name','birthdate','birthtime','today'] as $hf): ?>
    <?php if(!empty($_GET[$hf])): ?>
    <input type="hidden" name="<?= $hf ?>" value="<?= htmlspecialchars($_GET[$hf]) ?>">
    <?php endif; ?>
    <?php endforeach; ?>
    <label class="form-label mb-0" style="white-space:nowrap">Check a date:</label>
    <input type="date" name="checkdate" class="form-control form-control-sm"
           style="color-scheme:dark;max-width:180px"
           value="<?= htmlspecialchars($_GET['checkdate'] ?? date('Y-m-d')) ?>">
    <button type="submit" class="btn btn-primary btn-sm px-3">
      <i class="bi bi-search me-1"></i>Check
    </button>
    <?php if(!empty($_GET['checkdate'])): ?>
    <a href="<?= htmlspecialchars($todayUrl) ?>" class="btn btn-outline-secondary btn-sm">Today</a>
    <?php endif; ?>
  </form>

  <!-- Day header -->
  <div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
    <div style="font-size:2.8rem;line-height:1"><?= $typeIcon ?></div>
    <div>
      <div style="font-size:.75rem;color:var(--muted)"><?= $dateLabel ?></div>
      <div style="font-size:1.5rem;font-weight:700;color:<?= $typeColor ?>"><?= $typeLabel ?></div>
      <div class="mm-text" style="font-size:1rem;color:<?= $typeColor ?>;opacity:.85"><?= $typeMM ?></div>
    </div>
    <div class="ms-auto text-end" style="font-size:.82rem;line-height:1.9">
      <div><span style="color:var(--muted)">Myanmar Year</span> <strong style="color:var(--accent)"><?= $g['my'] ?></strong></div>
      <div><span style="color:var(--muted)">Month</span> <strong style="color:#FF9F1C"><?= $g['month_en'] ?></strong>
           <span class="mm-text" style="font-size:.85rem;color:#FF9F1C"> <?= $g['month_mm'] ?></span></div>
      <div><span style="color:var(--muted)">Day</span> <strong style="color:#5cb85c"><?= $g['moon_icon'] ?> <?= $g['day_label'] ?></strong>
           <span class="mm-text" style="font-size:.82rem;color:var(--muted)"> <?= $g['day_label_mm'] ?></span></div>
    </div>
  </div>

  <!-- Day type banner -->
  <div class="p-3 mb-3" style="background:<?= $typeBg ?>;border:1px solid <?= $typeColor ?>44;border-radius:10px;border-left:4px solid <?= $typeColor ?>">
    <div style="font-size:.82rem;color:<?= $typeColor ?>;font-weight:700;letter-spacing:.5px;text-transform:uppercase;margin-bottom:.3rem">
      <?= $g['day_en'] ?> · Myanmar Year <?= $g['my'] ?> · <?= $g['month_en'] ?> · <?= $g['day_label'] ?>
    </div>
    <div style="font-size:.88rem;color:#ccc">
      <?php if ($type=='yatyaza'):  ?>A highly auspicious day. Excellent for important activities and new beginnings.
      <?php elseif($type=='pyathada'): ?>A day prone to obstacles. Better for quiet, personal activities.
      <?php elseif($type=='sabbath'): ?>A sacred lunar day. Ideal for religious practice and reflection.
      <?php else: ?><?= $g['is_lent'] ? 'Buddhist Lent month — caution for travel and moving homes.' : 'A regular day. No special restrictions apply.' ?>
      <?php endif; ?>
    </div>
    <?php if ($g['is_lent']): ?>
    <div style="font-size:.78rem;color:#f0ad4e;margin-top:.4rem"><i class="bi bi-moon me-1"></i>Buddhist Lent month (<?= $g['month_en'] ?>) — avoid travel and moving homes.</div>
    <?php endif; ?>
  </div>

  <!-- Do / Don't -->
  <div class="row g-3">
    <div class="col-sm-6">
      <div class="card-dark2 p-3 h-100">
        <div style="font-size:.68rem;font-weight:700;letter-spacing:1.5px;color:#5cb85c;text-transform:uppercase;margin-bottom:.6rem"><i class="bi bi-check-circle-fill me-1"></i>DO</div>
        <?php foreach ($doList as $item): ?>
        <div style="font-size:.83rem;color:#ccc;padding:.2rem 0;border-bottom:1px solid #21262d22">
          <i class="bi bi-arrow-right-short" style="color:#5cb85c"></i><?= $item ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="col-sm-6">
      <div class="card-dark2 p-3 h-100">
        <div style="font-size:.68rem;font-weight:700;letter-spacing:1.5px;color:#e05252;text-transform:uppercase;margin-bottom:.6rem"><i class="bi bi-x-circle-fill me-1"></i>DON'T</div>
        <?php if (empty($dontList)): ?>
        <div style="font-size:.83rem;color:#5cb85c"><i class="bi bi-check2-all me-1"></i>No restrictions today</div>
        <?php else: foreach ($dontList as $item): ?>
        <div style="font-size:.83rem;color:#ccc;padding:.2rem 0;border-bottom:1px solid #21262d22">
          <i class="bi bi-x-lg" style="color:#e05252;font-size:.65rem"></i> <?= $item ?>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>
</div>
<?php } // end renderDayGuide ?>

<?php if ($checkedGuide): ?>
  <?php renderDayGuide($checkedGuide, 'Checked Date: '.date('D, d M Y', strtotime($checkedDate))); ?>
<?php else: ?>
  <?php renderDayGuide($todayGuide, 'Today — '.date('D, d M Y')); ?>
<?php endif; ?>

<?php
  $r = $result;
  // ── Myanmar Calendar: Gregorian month view ──
  $calDispDate = !empty($checkedDate) ? new DateTime($checkedDate) : new DateTime('today');
  $calY  = (int)$calDispDate->format('Y');
  $calM  = (int)$calDispDate->format('n');
  $daysInMonth = (int)(new DateTime("$calY-$calM-01"))->format('t');
  // Day-of-week of the 1st (0=Sun..6=Sat)
  $firstDow = (int)(new DateTime("$calY-$calM-01"))->format('w');
  // Birth date for highlighting
  $bd_obj  = new DateTime($r['birthdate']);
  $bd_gM   = (int)$bd_obj->format('n');
  $bd_gD   = (int)$bd_obj->format('j');
  // Today
  $today_gY = (int)date('Y');
  $today_gM = (int)date('n');
  $today_gD = (int)date('j');
  // Pre-compute Myanmar data for each day in this Gregorian month
  $moonIcons2 = ['🌒','🌕','🌘','🌑'];
  $moonMM2    = ['လဆန်း','လပြည့်','လဆုတ်','လကွယ်'];
  $mmMonthNames = [
      0=>'ပဝါဆို (First Waso)',1=>'တန်ခူး (Tagu)',2=>'ကဆုန် (Kason)',3=>'နယုန် (Nayon)',
      4=>'ဝါဆို (Waso)',5=>'ဝါခေါင် (Wagaung)',6=>'တော်သလင်း (Tawthalin)',
      7=>'သီတင်းကျွတ် (Thadingyut)',8=>'တန်ဆောင်မုန်း (Tazaungmon)',
      9=>'နတ်တော် (Natdaw)',10=>'ပြာသိုဒ် (Pyatho)',11=>'တပေါင်း (Tabodwe)',12=>'တသာ (Tabaung)',
  ];
  $calCells = [];
  $hasBirthday = false;
  $mmYears = []; $mmMonths = [];
  for ($d = 1; $d <= $daysInMonth; $d++) {
      $jdn  = mmcal_rg2j($calY, $calM, $d);
      $mwd  = ($jdn + 2) % 7;   // 0=Sat,1=Sun…6=Fri (for astro functions)
      $mdat = mmcal_j2m($jdn);
      $mm   = $mdat['mm'];
      $md   = $mdat['md'];
      $ms   = $mdat['ms'];
      $mdd  = $mdat['d'];
      $myr  = $mdat['my'];
      $yatz  = mmcal_yatyaza($mm, $mwd);
      $pyath = mmcal_pyathada($mm, $mwd);
      $showYatz  = $yatz; // show yatyaza even alongside pyathada
      $isSabbath = ($md == 8 || $ms == 1 || $md == 23 || $ms == 3);
      $moonLbl = $ms==0 ? $moonMM2[0].' '.$mdd : ($ms==1 ? $moonMM2[1] : ($ms==2 ? $moonMM2[2].' '.$mdd : $moonMM2[3]));
      $isBirth = ($calM == $bd_gM && $d == $bd_gD);
      $isToday = ($calY == $today_gY && $calM == $today_gM && $d == $today_gD);
      if ($isBirth) $hasBirthday = true;
      $mmYears[$myr] = true;
      $mmMonths[$mm] = $mmMonthNames[$mm] ?? '?';
      $calCells[$d] = compact('mm','md','ms','mdd','mwd','yatz','pyath','showYatz','isSabbath','moonLbl','isBirth','isToday');
  }
  $monthName   = $calDispDate->format('F Y');
  $mmYearStr   = implode(' / ', array_keys($mmYears));
  $mmMonthStr  = implode(' · ', array_unique(array_values($mmMonths)));
?>
<div class="card-dark p-4 mb-4 dv-compact-card mmcal-card" style="order:<?= _blkO('idx_mmcal',4) ?>;display:<?= _blkV('idx_mmcal')?'block':'none' ?>">
  <div class="section-title"><i class="bi bi-calendar3 me-1"></i><?= $monthName ?> — <?= _blkL('idx_mmcal','Myanmar Calendar') ?></div>
<?= _blkC('idx_mmcal') ?>
  <div style="font-size:.82rem;color:var(--muted);margin-bottom:.75rem;line-height:1.6">
    Myanmar Year <strong style="color:var(--accent)"><?= $mmYearStr ?></strong>
    &nbsp;·&nbsp; <span class="mm-text" style="color:#FF9F1C"><?= $mmMonthStr ?></span>
  </div>
  <div class="card-dark2 p-3 mmcal-inner">
    <div style="overflow-x:auto">
    <table class="mmcal-table" style="width:100%;border-collapse:separate;border-spacing:2px">
      <thead><tr><?php foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $dn): ?>
        <th style="text-align:center;padding:4px 1px;font-size:.65rem;color:var(--muted);font-weight:700;letter-spacing:.5px"><?= $dn ?></th>
      <?php endforeach; ?></tr></thead>
      <tbody>
      <?php
        // Build grid: leading nulls + days + trailing nulls
        $grid = array_merge(array_fill(0, $firstDow, null), range(1, $daysInMonth));
        while (count($grid) % 7 !== 0) $grid[] = null;
        foreach (array_chunk($grid, 7) as $week):
      ?><tr><?php foreach ($week as $gd):
        if ($gd === null) { echo '<td></td>'; continue; }
        $c = $calCells[$gd];
        // Determine cell style
        if ($c['pyath'] && $c['showYatz']) {
            // Both — split green (left) / red (right)
            $bgS = 'background:linear-gradient(to right,rgba(92,184,92,.25) 50%,rgba(220,53,69,.25) 50%);border-left:2px solid #5cb85c;border-right:2px solid #dc3545;border-top:1.5px solid #888;border-bottom:1.5px solid #888;';
            $numColor = '#e6edf3';
        } elseif ($c['pyath']) {
            $bgS = 'background:rgba(220,53,69,.22);border:1.5px solid #dc3545;';
            $numColor = '#ff6b6b';
        } elseif ($c['isBirth']) {
            $bgS = 'background:rgba(255,215,0,.22);border:1.5px solid #FFD700;';
            $numColor = '#FFD700';
        } elseif ($c['isToday']) {
            $bgS = 'background:rgba(72,202,228,.18);border:1.5px solid #48cae4;';
            $numColor = '#48cae4';
        } elseif ($c['showYatz']) {
            $bgS = 'background:rgba(92,184,92,.15);border:1.5px solid #5cb85c;';
            $numColor = '#5cb85c';
        } elseif ($c['isSabbath']) {
            $bgS = 'background:rgba(240,173,78,.1);border:1px solid rgba(240,173,78,.3);';
            $numColor = '#f0ad4e';
        } else {
            $bgS = 'background:var(--bg-dark);border:1px solid rgba(255,255,255,.06);';
            $numColor = '#e6edf3';
        }
      ?>
        <td style="padding:1px;vertical-align:top">
          <div class="mmcal-cell" style="border-radius:6px;padding:4px 2px 3px;text-align:center;<?= $bgS ?>min-height:62px">
            <div style="font-size:1rem;font-weight:700;color:<?= $numColor ?>;line-height:1.2"><?= $gd ?></div>
            <div style="font-size:.72rem;line-height:1"><?= $moonIcons2[$c['ms']] ?></div>
            <div class="mm-text" style="font-size:.48rem;color:var(--muted);line-height:1.3;white-space:nowrap"><?= $c['moonLbl'] ?></div>
            <?php if ($c['showYatz']): ?>
            <div class="mm-text" style="font-size:.44rem;color:#5cb85c;line-height:1.2;white-space:nowrap">ရက်ရာဇာ</div>
            <?php endif; ?>
            <?php if ($c['pyath']): ?>
            <div class="mm-text" style="font-size:.44rem;color:#ff6b6b;line-height:1.2;white-space:nowrap">ပြဿဒါး</div>
            <?php endif; ?>
            <?php if ($c['isSabbath'] && !$c['pyath'] && !$c['yatz']): ?>
            <div class="mm-text" style="font-size:.44rem;color:#f0ad4e;line-height:1.2;white-space:nowrap">ဥပုသ်</div>
            <?php endif; ?>
          </div>
        </td>
      <?php endforeach; ?></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <div class="d-flex gap-3 flex-wrap mt-2" style="font-size:.68rem;color:var(--muted)">
      <span>🌒 လဆန်း</span><span>🌕 လပြည့်</span><span>🌘 လဆုတ်</span><span>🌑 လကွယ်</span>
      <span style="color:#5cb85c">▐ ရက်ရာဇာ</span>
      <span style="color:#dc3545">▐ ပြဿဒါး</span>
      <span style="color:#f0ad4e">▐ ဥပုသ်</span>
      <?php if ($hasBirthday): ?><span style="color:#FFD700">▐ Birthday</span><?php endif; ?>
      <span style="color:#48cae4">▐ Today</span>
    </div>
  </div>
</div>

<?php
  $ov_color = $r['overall'] >= 8 ? '#5cb85c' : ($r['overall'] >= 6 ? '#48cae4' : ($r['overall'] >= 4 ? '#f0ad4e' : '#e05252'));
  $ov_status = scoreStatus($r['overall']);
?>

<!-- ── OVERALL SCORE ── -->
<div class="card-dark p-4 mb-4 text-center" style="order:<?= _blkO('idx_overall',5) ?>;display:<?= _blkV('idx_overall')?'block':'none' ?>">
  <div class="section-title"><?= _blkL('idx_overall',"Today's Overall Score") ?> for <?= htmlspecialchars($r['name']) ?></div>
<?= _blkC('idx_overall') ?>
  <div class="d-flex justify-content-center align-items-center gap-4 flex-wrap">
    <div id="overall-ring" class="overall-ring" style="border-color:<?= $ov_color ?>;color:<?= $ov_color ?>">
      <div id="overall-score-num" style="font-size:2.2rem"><?= $r['overall'] ?></div>
      <div style="font-size:.7rem;letter-spacing:1px">/ 10</div>
    </div>
    <div class="text-start">
      <div style="font-size:1.8rem;font-weight:700;color:<?= $ov_color ?>"><?= $ov_status['label'] ?></div>
      <div style="font-size:.88rem;color:#adb5bd;margin-top:.3rem;line-height:1.8">
        <span style="color:#8b949e">Born:</span> <strong style="color:#e6edf3"><?= date('D, d M Y', strtotime($r['birthdate'])) ?><?= $r['birthtime'] ? ' at '.$r['birthtime'] : '' ?></strong><br>
        <span style="color:#8b949e">Age:</span> <strong style="color:#e6edf3"><?= $r['age'] ?></strong> <span style="color:#8b949e">years ·</span> <strong style="color:#e6edf3"><?= number_format($r['days']) ?></strong> <span style="color:#8b949e">days alive</span><br>
        <span style="color:#8b949e">Birth day:</span> <strong style="color:#e6edf3"><?= $day_names[$r['dow']] ?></strong>
      </div>
      <?php if(!empty($r['mm_date'])): $md2=$r['mm_date']; $mpE=['LaSan','LaPyi','LaSout','LaKway']; $mdL=$mpE[$md2['ms']].($md2['ms']==1||$md2['ms']==3?'':' '.$md2['d']); ?>
      <div style="font-size:.84rem;margin-top:.4rem;line-height:1.9">
        <span style="color:#8b949e">You are</span> <strong style="color:#e6edf3"><?= $r['age'] ?></strong> <span style="color:#8b949e">years old.</span>
        <span style="color:#8b949e">Birthday:</span> <strong style="color:#e6edf3"><?= date('m/d/Y', strtotime($r['birthdate'])) ?></strong><br>
        <span style="color:#8b949e">Myanmar Year</span> <strong style="color:var(--accent)"><?= $md2['year'] ?></strong>
        <span style="color:#8b949e">· Month</span> <strong style="color:#FF9F1C"><?= $md2['month_en'] ?></strong>
        <span style="color:#8b949e">· Date</span> <strong style="color:#5cb85c"><?= $mdL ?></strong>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <div id="overall-update-note" style="display:none;font-size:.78rem;color:#74b816;margin-top:.75rem;text-align:center"></div>
</div>

<!-- ── BIORHYTHM GRAPH ── -->
<div class="card-dark p-4 mb-4" style="order:<?= _blkO('idx_biochart',6) ?>;display:<?= _blkV('idx_biochart')?'block':'none' ?>">
  <div class="section-title"><i class="bi bi-activity me-1"></i><?= _blkL('idx_biochart','Biorhythm Cycles — 33 Day View') ?> &nbsp;<span style="font-size:.75rem;color:var(--muted);font-weight:400">★ = You are here</span></div>
<?= _blkC('idx_biochart') ?>
  <div class="chart-wrapper">
    <canvas id="bioChart"></canvas>
  </div>
  <div class="text-center mt-2" style="font-size:.75rem;color:var(--muted)">
    <i class="bi bi-arrow-left-right me-1"></i>Dashed vertical line = Today
  </div>
  <div class="mt-2 text-center" style="font-size:.82rem;color:var(--muted)">
    This is general Biorhythm Cycles with common formula.
    If you want to build your own, <a href="biorhythm.php" style="color:var(--accent)">Click here</a>.
  </div>
</div>

<!-- ── BIORHYTHM SCORES TODAY ── -->
<div class="card-dark p-4 mb-4" style="order:<?= _blkO('idx_bioscores',7) ?>;display:<?= _blkV('idx_bioscores')?'block':'none' ?>">
  <div class="section-title"><i class="bi bi-bar-chart-fill me-1"></i><?= _blkL('idx_bioscores',"Today's Cycle Scores") ?></div>
<?= _blkC('idx_bioscores') ?>
  <div class="row g-2">
    <?php foreach ($r['bio_today'] as $bname => $bdata):
      $bs = scoreStatus($bdata['score']); ?>
    <div class="col-sm-6">
      <div class="card-dark2 p-3 d-flex align-items-center gap-3">
        <div class="score-ring" style="border-color:<?= $bdata['color'] ?>;color:<?= $bdata['color'] ?>">
          <?= $bdata['score'] ?>
        </div>
        <div class="flex-grow-1">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <span style="font-weight:600;font-size:.95rem"><?= $bname ?></span>
            <?= badge($bdata['score']) ?>
          </div>
          <div class="progress-custom">
            <div class="bar-fill" style="width:<?= barWidth($bdata['score']) ?>%;background:<?= $bdata['color'] ?>"></div>
          </div>
          <div style="font-size:.75rem;color:var(--muted);margin-top:3px"><?= $bdata['score'] ?>/10</div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- ── ZANAKA TAYA + MAHABOTE ── -->
<div class="row g-4 mb-4" style="order:<?= _blkO('idx_taya',8) ?>">

  <!-- Zanaka Taya -->
  <div class="col-md-6" style="order:<?= _blkO('idx_taya',8) ?>;display:<?= _blkV('idx_taya')?'block':'none' ?>">
    <div class="card-dark p-4 h-100">
      <div class="section-title"><i class="bi bi-calendar2-star me-1"></i><?= _blkL('idx_taya','Janaka Calculation — 2 years Life Cycle') ?></div>
<?= _blkC('idx_taya') ?>
      <div class="mm-text mb-3" style="font-size:.85rem;color:var(--muted);letter-spacing:.5px">တည် · ထွက် · ပျက် · ကျောက် · ထီး · နန်း · စံ</div>
      <div class="d-flex align-items-center gap-3 mb-3">
        <div class="score-ring" style="border-color:<?= $r['taya']['color'] ?>;color:<?= $r['taya']['color'] ?>;width:70px;height:70px;font-size:1.5rem">
          <?= $r['taya']['score'] ?>
        </div>
        <div>
          <div style="font-size:1.4rem;font-weight:700">
            <span class="mm-text"><?= $r['taya']['mm'] ?></span>
            <span style="font-size:1rem;color:#adb5bd"> / <?= $r['taya']['en'] ?></span>
          </div>
          <?= badge($r['taya']['score']) ?>
        </div>
      </div>
      <div class="progress-custom mb-2">
        <div class="bar-fill" style="width:<?= barWidth($r['taya']['score']) ?>%;background:<?= $r['taya']['color'] ?>"></div>
      </div>
      <p style="font-size:.88rem;color:#ccc;margin-top:.75rem"><?= $r['taya']['meaning'] ?></p>
      <div class="card-dark2 p-2 mt-2" style="font-size:.82rem">
        <i class="bi bi-lightbulb-fill me-1" style="color:#f0ad4e"></i><?= $r['taya']['advice'] ?>
      </div>
      <div class="mt-3 pt-2" style="border-top:1px solid var(--border);font-size:.8rem;color:var(--muted)">
        <i class="bi bi-clock me-1"></i>
        <strong style="color:#fff"><?= $r['years_remaining'] ?> year<?= $r['years_remaining']!=1?'s':'' ?></strong>
        remaining in this Taya &nbsp;·&nbsp;
        <i class="bi bi-arrow-right-circle me-1"></i>
        Next: <strong style="color:<?= $r['next_taya']['color'] ?>"><?= $r['next_taya']['mm'] ?> · <?= $r['next_taya']['en'] ?></strong>
        (<?= $r['next_taya']['score'] ?>/10)
      </div>

      <!-- Janaka Moment Reading -->
      <div class="mt-3 pt-2" style="border-top:1px solid var(--border)">
        <div style="font-size:.72rem;color:var(--muted);letter-spacing:1px;font-weight:700;text-transform:uppercase;margin-bottom:.6rem">
          <i class="bi bi-stopwatch me-1"></i>Janaka — Right Now (Moment of Asking)
        </div>
        <button id="janaka-now-btn" onclick="checkJanakaNow()"
          style="background:rgba(99,179,237,.1);border:1.5px solid var(--accent);border-radius:10px;
                 color:var(--accent);font-size:.85rem;font-weight:600;padding:.45rem 1.2rem;cursor:pointer">
          <i class="bi bi-stars me-1"></i>Check This Moment
        </button>
        <div id="janaka-now-result" style="display:none;margin-top:.75rem"></div>
      </div>
    </div>
  </div>

  <!-- Mahabote Birth House / Natal Chart -->
  <div class="col-md-6" style="order:<?= _blkO('idx_natal',9) ?>;display:<?= _blkV('idx_natal')?'block':'none' ?>">
    <div class="card-dark p-4 h-100">
      <div class="section-title"><i class="bi bi-globe2 me-1"></i><?= _blkL('idx_natal','Natal Chart — Birth Mahabote') ?></div>
<?= _blkC('idx_natal') ?>
      <div style="font-size:.7rem;color:var(--muted);margin-bottom:.75rem">This is your Original Birth Chart — it never changes.</div>

      <!-- Current house -->
      <div class="d-flex align-items-center gap-3 mb-3">
        <div style="font-size:2.8rem;line-height:1"><?= $r['mahabote']['symbol'] ?></div>
        <div>
          <div style="font-size:1.4rem;font-weight:700">
            <span class="mm-text"><?= $r['mahabote']['mm'] ?></span>
            <span style="color:var(--muted);font-size:1rem"> · <?= $r['mahabote']['name'] ?></span>
          </div>
          <div style="font-size:.8rem;color:var(--muted);margin-bottom:4px"><?= $r['mahabote']['en_full'] ?></div>
          <?= badge($r['mahabote']['score']) ?>
        </div>
        <div class="ms-auto">
          <div class="score-ring" style="border-color:<?= $r['mahabote']['color'] ?>;color:<?= $r['mahabote']['color'] ?>;width:60px;height:60px;font-size:1.3rem">
            <?= $r['mahabote']['score'] ?>
          </div>
        </div>
      </div>
      <div class="progress-custom mb-3">
        <div class="bar-fill" style="width:<?= barWidth($r['mahabote']['score']) ?>%;background:<?= $r['mahabote']['color'] ?>"></div>
      </div>
      <p style="font-size:.88rem;color:#ccc;margin-top:.5rem"><?= $r['mahabote']['trait'] ?></p>
      <div class="card-dark2 p-2 mt-2 mb-3" style="font-size:.82rem">
        <i class="bi bi-lightbulb-fill me-1" style="color:#f0ad4e"></i><?= $r['mahabote']['advice'] ?>
      </div>

      <!-- Trait Scores Table -->
      <?php
        $mhb_trait_colors = [
          'Leadership'   => '#8338EC',
          'Intelligence' => '#48CAE4',
          'Sociability'  => '#FF9F1C',
          'Creativity'   => '#E63946',
          'Reliability'  => '#5cb85c',
        ];
        $mhb_base_scores = $r['mahabote']['scores'] ?? [];
        $nk_mod = $r['nakat']['mod'] ?? [];
        $mhb_adj_scores = [];
        foreach ($mhb_base_scores as $t => $v) {
            $mhb_adj_scores[$t] = max(1, min(10, $v + ($nk_mod[$t] ?? 0)));
        }
        $mhb_avg = count($mhb_adj_scores) ? round(array_sum($mhb_adj_scores) / count($mhb_adj_scores), 1) : 0;
        $mhb_avg_int = (int)round($mhb_avg);
        $mhb_avg_color = $mhb_avg_int >= 8 ? '#5cb85c' : ($mhb_avg_int >= 6 ? '#48cae4' : ($mhb_avg_int >= 4 ? '#f0ad4e' : '#e05252'));
      ?>
      <?php if (!empty($mhb_adj_scores)): ?>
      <div class="mt-3 pt-2" style="border-top:1px solid var(--border)">
        <div style="font-size:.72rem;color:var(--muted);margin-bottom:.5rem">Personality Traits · Nakhat-adjusted</div>
        <div class="table-responsive">
          <table class="table table-dark table-borderless mb-0" style="font-size:.82rem">
            <thead style="color:var(--muted);font-size:.7rem;letter-spacing:.5px">
              <tr>
                <th>Trait</th>
                <th style="width:55px;text-align:center">Score</th>
                <th>Progress</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($mhb_adj_scores as $trait => $score):
                $tc = $mhb_trait_colors[$trait] ?? $r['mahabote']['color'];
                $base = $mhb_base_scores[$trait] ?? $score;
                $diff = $score - $base;
                $diffStr = $diff > 0 ? "<span style='color:#5cb85c;font-size:.65rem'>+{$diff}</span>" : ($diff < 0 ? "<span style='color:#e05252;font-size:.65rem'>{$diff}</span>" : '');
              ?>
              <tr style="border-bottom:1px solid var(--border)">
                <td style="vertical-align:middle">
                  <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:<?= $tc ?>;margin-right:6px"></span>
                  <?= $trait ?> <?= $diffStr ?>
                </td>
                <td style="text-align:center;font-weight:700;color:<?= $tc ?>;vertical-align:middle"><?= $score ?></td>
                <td style="vertical-align:middle;min-width:100px">
                  <div class="progress-custom">
                    <div class="bar-fill" style="width:<?= barWidth($score) ?>%;background:<?= $tc ?>"></div>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr style="border-top:2px solid var(--border)">
                <td style="font-weight:700;color:var(--text)">Average</td>
                <td style="text-align:center;font-weight:700;color:<?= $mhb_avg_color ?>"><?= $mhb_avg ?></td>
                <td style="vertical-align:middle">
                  <div class="progress-custom">
                    <div class="bar-fill" style="width:<?= barWidth($mhb_avg) ?>%;background:<?= $mhb_avg_color ?>"></div>
                  </div>
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
      <?php endif; ?>

      <!-- Nakat -->
      <div class="mt-3 pt-2" style="border-top:1px solid var(--border)">
        <div class="section-title">Human (Lu) · Ogre (ဘီလူး) · Celestial Being (Nat)</div>
        <?php $nk = $r['nakat']; ?>
        <div class="card-dark2 p-3">
          <div style="font-size:1.3rem;font-weight:700;margin-bottom:.25rem">
            <span><?= htmlspecialchars($nk['en']) ?></span>
            <span style="color:var(--muted);font-size:1rem"> (<span class="mm-text"><?= $nk['mm'] ?></span>)</span>
          </div>
          <div style="font-size:.82rem;color:#ccc"><?= $nk['trait'] ?></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ── MYANMAR ZODIAC (under Zanaka Taya) ── -->
<div class="card-dark p-4 mb-4" style="order:<?= _blkO('idx_zodiac',10) ?>;display:<?= _blkV('idx_zodiac')?'block':'none' ?>">
  <div class="section-title"><i class="bi bi-compass me-1"></i><?= _blkL('idx_zodiac','Myanmar Zodiac Animal') ?></div>
<?= _blkC('idx_zodiac') ?>
  <div style="font-size:.75rem;color:var(--muted);margin-bottom:.75rem">
    🦅 Garuda (Bird) &nbsp;·&nbsp; 🐯 Tiger &nbsp;·&nbsp; 🦁 Lion &nbsp;·&nbsp; 🐘 Elephant &nbsp;·&nbsp; 🐘 Tuskless Elephant &nbsp;·&nbsp; 🐀 Rat &nbsp;·&nbsp; 🐹 Guinea Pig &nbsp;·&nbsp; 🐉 Dragon
  </div>
  <?php
    $zoo_icons = ['Garuda (Eagle)'=>'🦅','Tiger'=>'🐯','Lion'=>'🦁',
                  'Elephant (tusked)'=>'🐘','Elephant (tuskless)'=>'🐘',
                  'Rat'=>'🐀','Guinea Pig'=>'🐹','Dragon (Naga)'=>'🐉'];
    $zicon = $zoo_icons[$r['zodiac']['animal']] ?? '⭐';
    $mmd   = $r['mm_date'];
  ?>
  <div class="d-flex align-items-start gap-4 flex-wrap">
    <div style="font-size:3rem;line-height:1"><?= $zicon ?></div>
    <div class="flex-grow-1">
      <div style="font-size:1.5rem;font-weight:700;margin-bottom:.25rem">
        <?= $r['zodiac']['animal'] ?>
        <span class="mm-text" style="font-size:1.2rem;color:#adb5bd;font-weight:400"> <?= $r['zodiac']['mm'] ?></span>
      </div>
      <div style="font-size:.85rem;color:var(--muted);margin-bottom:.4rem">
        Born on <strong style="color:#fff"><?= $r['zodiac']['birth_day'] ?></strong> ·
        Planet: <strong style="color:#fff"><?= $r['zodiac']['planet'] ?></strong> ·
        Direction: <strong style="color:#fff"><?= $r['zodiac']['dir'] ?></strong>
      </div>
      <div style="font-size:.88rem;color:#ccc;margin-bottom:.75rem"><?= $r['zodiac']['trait'] ?></div>
    </div>
  </div>
</div>

<!-- ── CHINESE ZODIAC ── -->
<?php
  $czr = $r['cz'];
  $czColor = $czr['color'];
  $czAvgColor = $czr['avg_int'] >= 8 ? '#5cb85c' : ($czr['avg_int'] >= 6 ? '#48cae4' : ($czr['avg_int'] >= 4 ? '#f0ad4e' : '#e05252'));
  $czSt = scoreStatus($czr['avg_int']);
  $ordinalSuffix = function($n) {
    $s = ['th','st','nd','rd'];
    $v = $n % 100;
    return $n . ($s[($v - 20) % 10] ?? $s[min($v, 3)] ?? $s[0]);
  };
?>
<div class="card-dark p-4 mb-4 dv-compact-card" style="order:<?= _blkO('idx_chinese',11) ?>;display:<?= _blkV('idx_chinese')?'block':'none' ?>">
  <div class="section-title"><i class="bi bi-yin-yang me-1"></i><?= _blkL('idx_chinese',"Chinese Zodiac — Signs' Personality") ?></div>
<?= _blkC('idx_chinese') ?>
  <div style="font-size:.75rem;color:var(--muted);margin-bottom:.85rem">
    Calculated by birth year · 12-year cycle
  </div>

  <!-- Header row: icon + name + rank -->
  <div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
    <div style="font-size:3.2rem;line-height:1"><?= $czr['icon'] ?></div>
    <div class="flex-grow-1">
      <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
        <span style="font-size:1.6rem;font-weight:700"><?= $czr['animal'] ?></span>
        <span style="font-size:.85rem;color:var(--muted)">Birth Year <?= $czr['year'] ?></span>
        <span style="font-size:.78rem;padding:3px 10px;border-radius:20px;background:#1a2a3a;color:var(--accent);border:1px solid var(--accent);font-weight:700">
          <?= $ordinalSuffix($czr['rank']) ?> Rank
        </span>
        <?= badge($czr['avg_int']) ?>
      </div>
      <p style="font-size:.9rem;color:#ccc;margin:0"><?= $czr['note'] ?></p>
    </div>
    <div>
      <div class="score-ring" style="border-color:<?= $czAvgColor ?>;color:<?= $czAvgColor ?>;width:70px;height:70px;font-size:1.4rem">
        <?= $czr['avg'] ?>
      </div>
      <div style="font-size:.65rem;color:var(--muted);text-align:center;margin-top:3px">avg / 10</div>
    </div>
  </div>

  <!-- Score table -->
  <div class="table-responsive mt-2">
    <table class="table table-dark table-borderless mb-0 dv-tight-table cz-table" style="font-size:.88rem">
      <thead style="color:var(--muted);font-size:.75rem;letter-spacing:.5px">
        <tr>
          <th>Trait</th>
          <th style="width:60px;text-align:center">Score</th>
          <th>Progress</th>
          <th style="width:80px;text-align:center">Status</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $traitColors = [
          'Leadership'   => '#8338EC',
          'Intelligence' => '#48CAE4',
          'Sociability'  => '#FF9F1C',
          'Creativity'   => '#E63946',
          'Reliability'  => '#5cb85c',
        ];
        foreach ($czr['scores'] as $trait => $score):
          $tc = $traitColors[$trait] ?? $czColor;
        ?>
        <tr style="border-bottom:1px solid var(--border)">
          <td style="vertical-align:middle">
            <span style="display:inline-block;width:9px;height:9px;border-radius:50%;background:<?= $tc ?>;margin-right:7px"></span>
            <?= $trait ?>
          </td>
          <td style="text-align:center;font-weight:700;color:<?= $tc ?>;vertical-align:middle"><?= $score ?>/10</td>
          <td class="cz-bar" style="vertical-align:middle">
            <div class="progress-custom">
              <div class="bar-fill" style="width:<?= barWidth($score) ?>%;background:<?= $tc ?>"></div>
            </div>
          </td>
          <td style="text-align:center;vertical-align:middle"><?= badge($score) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr style="border-top:2px solid var(--border)">
          <td style="font-weight:700;color:var(--text)">Overall Average</td>
          <td style="text-align:center;font-weight:700;color:<?= $czAvgColor ?>"><?= $czr['avg'] ?>/10</td>
          <td style="vertical-align:middle">
            <div class="progress-custom">
              <div class="bar-fill" style="width:<?= barWidth($czr['avg']) ?>%;background:<?= $czAvgColor ?>"></div>
            </div>
          </td>
          <td style="text-align:center"><?= badge($czr['avg_int']) ?></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>



<!-- ── STREAK CARD ── -->
<div class="card-dark p-4 mb-4 no-print" id="streak-card" style="display:none;order:<?= _blkO('idx_streak',21) ?>">
  <div class="section-title"><i class="bi bi-trophy-fill me-1" style="color:#f0ad4e"></i>Your Stats &amp; dUSD</div>
  <!-- Stats row -->
  <div class="d-flex align-items-center gap-3 flex-wrap">
    <div style="font-size:2rem;line-height:1">🔥</div>
    <div>
      <div style="font-size:2rem;font-weight:800;color:var(--text);line-height:1" id="streak-count">0</div>
      <div style="font-size:.72rem;color:var(--muted);font-weight:600;letter-spacing:.5px;margin-top:2px">DAY STREAK</div>
    </div>
    <div style="border-left:1px solid var(--border);padding-left:16px">
      <div style="font-size:2rem;font-weight:800;color:#58a6ff;line-height:1" id="streak-pts">0 dUSD</div>
      <div style="font-size:.72rem;color:var(--muted);font-weight:600;letter-spacing:.5px;margin-top:2px">TOTAL dUSD</div>
    </div>
    <div class="ms-auto text-end">
      <div style="font-size:.72rem;color:var(--muted);margin-bottom:4px">Best Streak</div>
      <div style="font-size:1.1rem;font-weight:700;color:#f0ad4e" id="streak-max">0</div>
    </div>
  </div>
  <!-- Earn dUSD section -->
  <div style="margin-top:14px;border-top:1px solid var(--border);padding-top:12px">
    <div class="section-title" style="margin-bottom:.5rem">HOW TO EARN dUSD</div>
    <div class="d-flex flex-wrap gap-2" style="font-size:.75rem" id="streak-earn-badges">
      <span class="streak-badge" style="background:#1a2a1a;color:#5cb85c;border:1px solid #5cb85c44">📅 Daily visit +1 dUSD</span>
      <span class="streak-badge" style="background:#2a1a0d;color:#f0ad4e;border:1px solid #f0ad4e44">📤 Sharing +2 dUSD</span>
      <span class="streak-badge" style="background:#0d1a2a;color:#58a6ff;border:1px solid #58a6ff44">⏱ 5 min+ session +1 dUSD</span>
      <span class="streak-badge" style="background:#1a0d2a;color:#c792ea;border:1px solid #c792ea44">💞 Compat share +2 dUSD</span>
    </div>
  </div>
  <div id="streak-toast" style="display:none;margin-top:8px;font-size:.8rem;color:#5cb85c;font-weight:700"></div>
</div>

<!-- ── COMPATIBILITY READING ── -->
<div class="card-dark p-4 mb-4" id="compat-section" style="order:<?= _blkO('idx_compat',12) ?>;display:<?= _blkV('idx_compat')?'block':'none' ?>">
  <div class="section-title"><i class="bi bi-people-fill me-1"></i><?= _blkL('idx_compat','Compatibility Reading — Chinese Zodiac & Mahabote') ?></div>
<?= _blkC('idx_compat') ?>
  <div style="font-size:.75rem;color:var(--muted);margin-bottom:1rem">
    Enter your partner's details to see your astrological compatibility based on Chinese Zodiac and Mahabote birth types.
  </div>

  <!-- Partner input form -->
  <div id="compat-form-wrap">
    <div class="row g-3 mb-3">
      <div class="col-sm-6">
        <label class="form-label">Partner's Name</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-person-heart"></i></span>
          <input type="text" id="p2-name" class="form-control" placeholder="Partner's name"
                 value="<?= htmlspecialchars($_GET['p2_name'] ?? '') ?>">
        </div>
      </div>
      <div class="col-sm-6">
        <label class="form-label">Partner's Birth Date</label>
        <input type="date" id="p2-dob" class="form-control" style="color-scheme:dark"
               value="<?= htmlspecialchars($_GET['p2_dob'] ?? '') ?>">
      </div>
      <div class="col-12">
        <button class="btn btn-primary px-4" onclick="calcCompat()">
          <i class="bi bi-heart-pulse-fill me-2"></i>Check Compatibility
        </button>
      </div>
    </div>
  </div>

  <!-- Result (shown after calculation) -->
  <div id="compat-result" style="display:none">
    <div class="row g-3 align-items-start">
      <!-- Percentage ring + label -->
      <div class="col-auto text-center">
        <div class="compat-pct-ring mx-auto mb-2" id="compat-ring" style="border-color:#ff9f1c;color:#ff9f1c">
          <div id="compat-pct" style="font-size:1.8rem;font-weight:700">0%</div>
          <div style="font-size:.6rem;color:var(--muted)">match</div>
        </div>
        <div id="compat-label" style="font-size:.8rem;font-weight:700"></div>
      </div>
      <!-- Breakdown bars -->
      <div class="col flex-grow-1">
        <div id="compat-names" style="font-size:.95rem;font-weight:700;margin-bottom:.75rem"></div>
        <div class="mb-2">
          <div class="d-flex justify-content-between mb-1" style="font-size:.78rem;color:var(--muted)">
            <span>Chinese Zodiac</span><span id="cz-score">–</span>
          </div>
          <div class="compat-bar"><div class="compat-bar-fill" id="cz-bar" style="background:#f0ad4e;width:0%"></div></div>
        </div>
        <div class="mb-2">
          <div class="d-flex justify-content-between mb-1" style="font-size:.78rem;color:var(--muted)">
            <span>Mahabote Birth Type</span><span id="mhb-score">–</span>
          </div>
          <div class="compat-bar"><div class="compat-bar-fill" id="mhb-bar" style="background:#8338ec;width:0%"></div></div>
        </div>
        <div id="compat-summary" style="font-size:.82rem;color:#ccc;margin-top:.5rem"></div>
      </div>
    </div>

    <!-- Social actions (always visible) -->
    <div style="border-top:1px solid var(--border);margin-top:1rem;padding-top:1rem">
      <div class="d-flex align-items-center gap-2 flex-wrap">
        <button class="social-action-btn" id="compat-like-btn"
                style="border-color:#e05252;color:#e05252" onclick="toggleCompatLike()">
          <i class="bi bi-heart-fill"></i> Like <span id="compat-like-count">0</span>
        </button>
        <button class="social-action-btn" id="compat-follow-btn"
                style="border-color:#58a6ff;color:#58a6ff" onclick="toggleCompatFollow()">
          <i class="bi bi-person-plus-fill"></i> Follow <span id="compat-follow-count">0</span>
        </button>
        <span style="font-size:.72rem;color:var(--muted)" id="compat-followers-label"></span>
        <!-- Share button — only shown when >= 60% -->
        <button class="share-btn wa ms-auto" id="compat-share-btn" style="display:none" onclick="shareCompat()">
          <i class="bi bi-share-fill"></i> Share Result
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ── THARME YEARLY FORTUNE ── -->
<?php $th = $r['tharme_result']; ?>
<div class="card-dark p-4 mb-4" style="order:<?= _blkO('idx_tharme',13) ?>;display:<?= _blkV('idx_tharme')?'block':'none' ?>">
  <div class="section-title"><i class="bi bi-arrow-repeat me-1"></i><?= _blkL('idx_tharme','Shar-Mee Horoscope Reading') ?></div>
<?= _blkC('idx_tharme') ?>
  <div style="font-size:.78rem;color:var(--muted);margin-bottom:.75rem">
    4 categories: <strong style="color:#fff">Saw-Htwe · Thu-O · Kaw-Li · Shar-Mee</strong> — each has a direct order (full effect) and reversed order (weakened effect).
  </div>

  <!-- Current year result -->
  <div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
    <div style="font-size:2.8rem;line-height:1"><?= $th['symbol'] ?></div>
    <div style="flex:1">
      <div style="font-size:1.5rem;font-weight:700">
        <span class="mm-text"><?= $th['mm'] ?></span>
        <span style="color:var(--muted);font-size:1rem"> · <?= $th['en'] ?></span>
      </div>
      <div class="d-flex align-items-center gap-2 flex-wrap mt-1">
        <?= badge($th['score']) ?>
        <?php if ($th['direct']): ?>
          <span style="font-size:.75rem;padding:2px 8px;border-radius:12px;background:#0d2d0d;color:#5cb85c;border:1px solid #5cb85c">Direct Order — Full Effect</span>
        <?php else: ?>
          <span style="font-size:.75rem;padding:2px 8px;border-radius:12px;background:#2d2200;color:#f0ad4e;border:1px solid #f0ad4e">Reversed Order — Weakened Effect</span>
        <?php endif; ?>
        <span style="font-size:.72rem;color:var(--muted)" class="mm-text"><?= $th['pair'] ?></span>
        <span style="font-size:.72rem;color:var(--muted)">(<?= $th['pair_en'] ?>)</span>
      </div>
      <div style="font-size:.75rem;color:var(--muted);margin-top:5px">
        Birth position <strong style="color:#fff"><?= $th['start_index'] ?></strong>
        &nbsp;+&nbsp; age <strong style="color:#fff"><?= $r['age'] ?></strong>
        &nbsp;÷ 8 = rem <strong style="color:#fff"><?= $th['rem'] ?></strong>
        &nbsp;→ circle position <strong style="color:#fff"><?= ($th['start_index'] + $th['rem']) % 8 ?></strong>
        &nbsp;→ <strong style="color:<?= $th['color'] ?>"><?= $th['mm'] ?></strong>
      </div>
    </div>
    <div class="score-ring ms-auto" style="border-color:<?= $th['color'] ?>;color:<?= $th['color'] ?>;width:70px;height:70px;font-size:1.5rem">
      <?= $th['score'] ?>
    </div>
  </div>
  <div class="progress-custom mb-3">
    <div class="bar-fill" style="width:<?= barWidth($th['score']) ?>%;background:<?= $th['color'] ?>"></div>
  </div>
  <p style="font-size:.88rem;color:#ccc;margin-top:.5rem"><?= $th['meaning'] ?></p>
  <div class="card-dark2 p-2 mt-2 mb-4" style="font-size:.82rem">
    <i class="bi bi-lightbulb-fill me-1" style="color:#f0ad4e"></i><?= $th['advice'] ?>
  </div>

  <!-- Year-by-year forecast table -->
  <div class="section-title mt-2"><i class="bi bi-calendar3 me-1"></i>Year-by-Year Shar-Mee Forecast</div>
  <div class="d-flex flex-wrap gap-2">
    <?php foreach ($r['tharme_forecast'] as $fy): ?>
    <div class="card-dark2 p-3 text-center" style="min-width:90px;flex:1;border-color:<?= $fy['is_now'] ? $fy['color'] : 'var(--border)' ?>;<?= $fy['is_now'] ? 'box-shadow:0 0 0 2px '.$fy['color'].'44' : '' ?>">
      <div style="font-size:.7rem;color:var(--muted);letter-spacing:.5px;margin-bottom:3px">
        AGE <?= $fy['age'] ?>
        <?= $fy['is_now'] ? '<span style="color:'.$fy['color'].'"> ◀ NOW</span>' : '' ?>
      </div>
      <div style="font-size:1.4rem;margin-bottom:2px"><?= $fy['symbol'] ?></div>
      <div class="mm-text" style="font-size:1rem;font-weight:700;color:<?= $fy['color'] ?>"><?= $fy['mm'] ?></div>
      <div style="font-size:.72rem;color:var(--muted)"><?= $fy['en'] ?></div>
      <div style="font-size:.8rem;font-weight:700;color:<?= $fy['color'] ?>;margin-top:3px"><?= $fy['score'] ?>/10</div>
      <?= badge($fy['score']) ?>
    </div>
    <?php endforeach; ?>
  </div>

</div>


<!-- ── DO / DON'T TABLE ── -->
<div class="card-dark p-4 mb-4 dv-compact-card" style="order:<?= _blkO('idx_dodont',14) ?>;display:<?= _blkV('idx_dodont')?'block':'none' ?>">
  <div class="section-title"><i class="bi bi-check2-square me-1"></i><?= _blkL('idx_dodont',"Today's DO / DON'T Guide") ?> for <?= htmlspecialchars($r['name']) ?></div>
<?= _blkC('idx_dodont') ?>
  <div class="table-responsive">
    <table class="table table-dark table-borderless dv-tight-table dodont-table" style="border-color:var(--border)">
      <thead style="color:var(--muted);font-size:.8rem;letter-spacing:.5px">
        <tr>
          <th>Trait</th><th>Score</th><th>Status</th><th>Advice</th>
        </tr>
      </thead>
      <tbody style="font-size:.9rem">
        <?php
          // ── Biorhythm rows (fixed order) ──
          $bio_order = ['Physical','Emotional','Intellectual','Spiritual','Awareness','Aesthetic','Intuition'];
          $advice_map = [
            'Physical'    =>['DO'=>'Exercise, sports, outdoor work','CAUTION'=>'Light activity only','DON\'T'=>'Avoid heavy strain or surgery'],
            'Emotional'   =>['DO'=>'Meetings, negotiations, relationships','CAUTION'=>'Be mindful of emotions','DON\'T'=>'Avoid conflicts and major decisions'],
            'Intellectual'=>['DO'=>'Study, planning, contracts, exams','CAUTION'=>'Double-check your work','DON\'T'=>'Avoid signing important documents'],
            'Spiritual'   =>['DO'=>'Meditation, prayer, inner work','CAUTION'=>'Reflect before acting','DON\'T'=>'Avoid spiritual commitments'],
            'Awareness'   =>['DO'=>'Trust your instincts fully','CAUTION'=>'Verify before trusting gut','DON\'T'=>'Ignore strong gut feelings'],
            'Aesthetic'   =>['DO'=>'Creative work, art, design','CAUTION'=>'Review creative output','DON\'T'=>'Avoid major aesthetic decisions'],
            'Intuition'   =>['DO'=>'Follow your inner voice','CAUTION'=>'Balance logic with feeling','DON\'T'=>'Do not act on impulse alone'],
          ];
          $table_rows = [];
          foreach ($bio_order as $bn) {
            if (!isset($r['bio_today'][$bn])) continue;
            $bd = $r['bio_today'][$bn];
            $st = scoreStatus($bd['score']);
            $adv = $advice_map[$bn][$st['label']] ?? $advice_map[$bn]['CAUTION'];
            $table_rows[] = ['name'=>$bn,'score'=>$bd['score'],'color'=>$bd['color'],'advice'=>$adv];
          }
          // ── Astrological rows (fixed order: CZ → Mahabote; Shar-Mee + Janaka removed, see hackathon-edition cleanup below) ──
          $czAdvice = [
            'DO'      => 'Lean into your natural strengths — lead, connect, and create boldly.',
            'OK'      => 'Use your innate traits to navigate the day with steady confidence.',
            'CAUTION' => 'Be mindful of your weaknesses; balance effort across all areas.',
            "DON'T"   => 'Avoid overextending — rest and focus on what you do best.',
          ];
          $czSLabel = scoreStatus($r['cz']['avg_int'])['label'];
          $table_rows[] = ['name'=>'Chinese Zodiac · '.$r['cz']['animal'],'score'=>$r['cz']['avg_int'],'color'=>$r['cz']['color'],'advice'=>$czAdvice[$czSLabel] ?? $czAdvice['CAUTION']];
          $table_rows[] = ['name'=>'Mahabote - ('.$r['mahabote']['name'].')','score'=>$r['mahabote']['score'],'color'=>$r['mahabote']['color'],'advice'=>$r['mahabote']['advice']];
          // HACKATHON-EDITION CLEANUP (new): Shar-Mee and Janaka Calculation rows removed from THIS guide's display only (9 rows remain).
          // The underlying tharme/taya scores, forecasts, and all other cards are untouched - no data, history, or scores deleted.
          // $table_rows[] = ['name'=>'Shar-Mee - ('.$r['tharme_result']['en'].')','score'=>$r['tharme_result']['score'],'color'=>$r['tharme_result']['color'],'advice'=>$r['tharme_result']['advice']];
          // $table_rows[] = ['name'=>'Janaka Calculation ('.$r['taya']['en'].')','score'=>$r['taya']['score'],'color'=>$r['taya']['color'],'advice'=>$r['taya']['advice']];
          foreach ($table_rows as $row):
        ?>
        <tr style="border-bottom:1px solid var(--border)">
          <td>
            <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:<?= $row['color'] ?>;margin-right:8px"></span>
            <?= $row['name'] ?>
          </td>
          <td><strong style="color:<?= $row['color'] ?>"><?= $row['score'] ?>/10</strong></td>
          <td><?= badge($row['score']) ?></td>
          <td style="color:var(--muted)"><?= $row['advice'] ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="text-center mb-4">
  <a href="?" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>New Reading</a>
</div>

<!-- CHART.JS SCRIPTS -->
<script>
// ── Biorhythm Line Chart ──
const labels = <?= json_encode($r['chart_labels']) ?>;
const todayIdx = 16; // index of today in 33-day window (-16 … +16)

// Per-point sizes: big star at today, tiny dot elsewhere
const N = 33;
const pointRadii  = Array.from({length: N}, (_, i) => i === todayIdx ? 14 : 2);
const pointStyles = Array.from({length: N}, (_, i) => i === todayIdx ? 'star' : 'circle');
const pointBgs    = col => Array.from({length: N}, (_, i) => i === todayIdx ? '#fff' : col);

const datasets = [
<?php foreach ($r['chart_datasets'] as $bname => $data):
  $col = $r['bio_colors'][$bname]; ?>
  {
    label: '<?= $bname ?>',
    data: <?= json_encode($data) ?>,
    borderColor: '<?= $col ?>',
    backgroundColor: 'transparent',
    borderWidth: 2,
    pointRadius: pointRadii,
    pointHoverRadius: pointRadii.map(r => r + 3),
    pointStyle: pointStyles,
    pointBackgroundColor: pointBgs('<?= $col ?>'),
    pointBorderColor: '<?= $col ?>',
    pointBorderWidth: 1.5,
    tension: 0.4,
  },
<?php endforeach; ?>
];

const bioCtx = document.getElementById('bioChart').getContext('2d');
new Chart(bioCtx, {
  type: 'line',
  data: { labels, datasets },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    clip: false,
    interaction: { mode: 'index', intersect: false },
    plugins: {
      legend: {
        labels: { color: '#8b949e', boxWidth: 12, font: { size: 11 } }
      },
      tooltip: {
        backgroundColor: '#1c2128',
        borderColor: '#30363d',
        borderWidth: 1,
        titleColor: '#e6edf3',
        bodyColor: '#8b949e',
        callbacks: {
          label: ctx => ` ${ctx.dataset.label}: ${ctx.parsed.y.toFixed(3)}`
        }
      },
    },
    scales: {
      x: {
        ticks: { color: '#8b949e', font: { size: 10 } },
        grid: { color: '#21262d' },
      },
      y: {
        min: -1.1, max: 1.1,
        ticks: { display: false },
        grid: { color: '#21262d' },
      }
    }
  },
  plugins: [{
    id: 'todayLine',
    afterDraw(chart) {
      const ctx = chart.ctx;
      const x = chart.scales.x.getPixelForValue(todayIdx);
      const top = chart.chartArea.top;
      const bottom = chart.chartArea.bottom;
      ctx.save();
      ctx.setLineDash([5, 5]);
      ctx.strokeStyle = 'rgba(255,255,255,0.4)';
      ctx.lineWidth = 1.5;
      ctx.beginPath();
      ctx.moveTo(x, top);
      ctx.lineTo(x, bottom);
      ctx.stroke();
      ctx.setLineDash([]);
      ctx.fillStyle = 'rgba(255,255,255,0.7)';
      ctx.font = '11px sans-serif';
      ctx.fillText('Today', x + 4, top + 14);
      ctx.restore();
    }
  }]
});

</script>

<?php endif; ?>

</div>

<footer class="dv-footer">
  <div class="dv-footer-brand"><img class="dv-coin" src="assets/brand/logo-coin-96.png?v=1" width="96" height="96" alt="" aria-hidden="true"> DharmaVera</div>
  <div>© 2026 DharmaVera · Developed by kAi</div>
  <div>Readings and predictions are for entertainment and reflection only.</div>
  <div><a href="privacy.html">Privacy Policy</a> · <a href="terms.html">Terms &amp; Conditions</a> · <a href="about.html">About &amp; Credits</a></div>
</footer>

<!-- HACKATHON DEMO WORK (new): demo loop logic (remedy, wave chart, overlays). -->
<script src="hackathon_demo.js?v=4"></script>
<script>
// ── Horary Oracle — Arudha Puzza System ──

function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

// ── Unified Oracle (Mahabote + Quran + Bible) ──
const unifiedClicks = [];

// ── Disable / enable unified keypad ──
function lockUnifiedKeypad() {
  document.querySelectorAll('.unified-btn').forEach(b => { b.disabled=true; b.style.opacity='.35'; b.style.cursor='default'; });
  document.querySelectorAll('input[name="horary-omen"]').forEach(r => r.disabled=true);
  const question = document.getElementById('oracle-question');
  if (question) question.disabled = true;
  // kau-cim-btn is NOT locked here — the cylinder must stay clickable for Step 4
}
function lockKauCimBtn() {
  const kb = document.getElementById('kau-cim-btn');
  if (kb) { kb.disabled=true; kb.style.opacity='.4'; kb.style.cursor='default'; }
}
function unlockUnifiedKeypad() {
  document.querySelectorAll('.unified-btn').forEach(b => {
    b.disabled=false; b.style.opacity=''; b.style.cursor='pointer';
  });
  document.querySelectorAll('input[name="horary-omen"]').forEach(r => r.disabled=false);
  const question = document.getElementById('oracle-question');
  if (question) question.disabled = false;
  const kb = document.getElementById('kau-cim-btn');
  if (kb) { kb.disabled=false; kb.style.opacity=''; kb.style.cursor='pointer'; }
}

// ── Single tap handler ──
function unifiedClick(n) {
  const COOLDOWN = 90 * 60 * 1000;
  // HACKATHON DEMO WORK (new): ?demo=1 skips the browser-side cooldown so judges can re-draw; normal visitors keep it.
  const _demoTap = (window.HackathonDemo && window.HackathonDemo.isDemo());
  if (!_demoTap && localStorage.getItem('mhb_last_answered') &&
      (Date.now() - parseInt(localStorage.getItem('mhb_last_answered'))) < COOLDOWN) return;
  if (unifiedClicks.length >= 4) return;

  // First tap must be 1-9 (Mahabote has no "0" celestial body)
  if (unifiedClicks.length === 0 && n === 0) return;

  unifiedClicks.push(n);
  const idx = unifiedClicks.length;
  const slot = document.getElementById('u-s' + idx);
  if (slot) { slot.textContent = n; slot.classList.add('filled'); }

  // After 1st tap: enable 0
  if (idx === 1) {
    const z = document.getElementById('ubtn-0');
    if (z) { z.disabled=false; z.style.opacity=''; z.style.cursor='pointer'; }
  }

  if (idx === 4) {
    lockUnifiedKeypad();
    drawKauCim();
  }
}

// ── Step 4: reveal Kau Cim after 4 taps ──
function revealStep4() {
  const step4 = document.getElementById('kau-cim-step');
  if (step4) {
    step4.style.display = 'block';
    step4.scrollIntoView({behavior:'smooth', block:'nearest'});
  }
}

// ── Score helpers ──
const _sc = s => s>=7?'#51cf66':s>=4?'#f0ad4e':'#e05252';
const _sb = s => `<div class="dv-obar"><div style="width:${s*10}%;background:${_sc(s)}"></div></div>`;
const _st = s => s>=9?'Excellent':s>=7?'Favorable':s>=5?'Neutral':s>=3?'Challenging':'Very Difficult';
const _overallLabel = a => a>=8?'✦ Excellent':a>=7?'✦ Favorable':a>=5?'◈ Neutral':a>=3?'◇ Challenging':'◇ Difficult';

// ── Seven oracles, in display order, with their original-script names ──
// Readings are calculated on the server (oracle_api.php); this page only displays them.
const ORACLE_ORDER = [
  { key:'mhb',      num:'①', icon:'☸️', script:'မဟာဘုတ်', lang:'my',
    label: s => 'Mahabote (Burman) — Arudha Puzza' },
  { key:'kauCim',   num:'②', icon:'🎋', script:'求籤',      lang:'zh',
    label: s => 'Kau Cim · Stick #' + String(s.num).padStart(2,'0') },
  { key:'tehillim', num:'③', icon:'✡️', script:'תְּהִלִּים', lang:'he',
    label: s => 'Tehillim · Psalm ' + s.num },
  { key:'bible',    num:'④', icon:'✝️', script:'Βίβλος',    lang:'el',
    label: s => 'Bible · ' + s.book + ' Ch ' + s.ch },
  { key:'quran',    num:'⑤', icon:'☪️', script:'القرآن',    lang:'ar',
    label: s => 'Quran · Sura ' + s.num + ' — ' + s.name },
  { key:'ram',      num:'⑥', icon:'🕉️', script:'राम शलाका', lang:'hi',
    label: s => 'Ram Shalaka · Chaupai ' + (s.idx + 1) },
  { key:'tarot',    num:'⑦', icon:'🃏', script:'',          lang:'',
    label: s => 'Gypsy Cards · ' + (s.n ? (s.v||'')+(s.s||'')+' '+s.n : 'Card drawn') }
];

function oracleRowHTML(o, s) {
  const script = o.script ? `<small lang="${o.lang}">${o.script}</small>` : '';
  const score = s.score;
  // HACKATHON DEMO WORK (new): per-oracle Remedy button for single scores below 5 (all 7 oracles); opens one modal, never a new page.
  return `<div class="dv-orow" id="${o.key}-oracle-row">
      <span class="dv-onum">${o.num}</span><span class="dv-oic">${o.icon}</span>
      <span class="dv-olbl">${escHtml(o.label(s))}${script}</span>
      ${_sb(score)}
      <span class="dv-oscore" style="color:${_sc(score)}">${score}<small>/10</small></span>
      ${window.HackathonDemo ? window.HackathonDemo.perOracleButtonHTML(o.key, score) : ''}
    </div>`;
}

// ── Talk to the private oracle engine ──
async function oracleApi(data) {
  try {
    const fd = new FormData();
    fd.append('csrf', DV_CSRF);
    for (const [k, v] of Object.entries(data)) fd.append(k, v ?? '');
    // HACKATHON DEMO WORK (new): forward the ?demo=1 flag so the server skips the cooldown (see oracle_api.php).
    const _demoQ = (window.HackathonDemo && window.HackathonDemo.isDemo()) ? '?demo=1' : '';
    const r = await fetch('oracle_api.php' + _demoQ, { method:'POST', body:fd, credentials:'same-origin' });
    return await r.json();
  } catch (e) { return { error:'Could not reach DharmaVera. Please check your connection and try again.' }; }
}

// ── Render the reading summary (new draw or restored during the 90-minute window) ──
function renderOracleResult(r) {
  const avg = r.avg;
  // HACKATHON DEMO WORK (new): demo-mode label switch for the cooldown note below.
  const _demoCta = (window.HackathonDemo && window.HackathonDemo.isDemo());
  const avgColor = _sc(avg);
  const rows = ORACLE_ORDER.map(o => r[o.key] ? oracleRowHTML(o, r[o.key]) : '').join('');

  const ctaHTML = IS_LOGGED_IN
    ? `<div style="font-size:.78rem;color:#bbb;margin-bottom:.7rem;line-height:1.55">Open your full reading: your psalm in Hebrew, the Ram Shalaka chaupai, scripture themes, Kau Cim poem, card meaning, and Arudha details.</div>
       <a class="dv-oracle-cta" href="biorhythm.php?tab=chart&amp;reading=latest#oracle-detail-section" style="display:inline-block;background:linear-gradient(135deg,var(--accent),#a78bfa);border-radius:10px;padding:.6rem 1.75rem;color:#fff;font-size:.88rem;font-weight:700;text-decoration:none;box-shadow:0 3px 14px rgba(99,102,241,.3)"><i class="bi bi-bar-chart-steps me-2"></i>Open full reading &amp; My Chart &rarr;</a>
       <div style="font-size:.65rem;color:var(--muted);margin-top:.65rem">${_demoCta ? 'Demo mode &middot; draw again any time' : 'Oracles locked &middot; Next reading in 90 minutes'}</div>`
    : `<div style="font-size:.85rem;color:#bbb;margin-bottom:.65rem;line-height:1.6">Explore the complete reading, including your psalm in Hebrew, the Ram Shalaka chaupai, scripture themes, the Kau Cim poem, and your card interpretation.</div>
       <a class="dv-oracle-cta" href="biorhythm.php?tab=chart&amp;reading=latest#oracle-detail-section" style="display:inline-block;background:linear-gradient(135deg,var(--accent),#a78bfa);border-radius:10px;padding:.6rem 1.75rem;color:#fff;font-size:.88rem;font-weight:700;text-decoration:none;box-shadow:0 3px 14px rgba(99,102,241,.3)"><i class="bi bi-person-plus me-2"></i>See my full reading &mdash; Create a free profile &rarr;</a>
       <div style="font-size:.65rem;color:var(--muted);margin-top:.55rem">Free forever &middot; Unlock full reading details</div>`;
  // HACKATHON DEMO WORK (new): Reversal-path remedy section renders in the result below when avg <= 5 (see HackathonDemo.remedyHTML).
  // HACKATHON Step 4 (new): one Meta AI guidance sentence per reading (generated server-side from scores only, never the typed question).
  const aiHTML = r.ai ? '<div class="hack-ai-card" style="background:rgba(88,166,255,.06);border:1px solid rgba(88,166,255,.3);border-radius:12px;padding:.8rem 1rem;margin:1rem 0;text-align:left">'
    + '<div style="font-size:.72rem;font-weight:800;color:#58a6ff;letter-spacing:1px;text-transform:uppercase;margin-bottom:.35rem">Meta AI guidance - new hackathon feature</div>'
    + '<p style="font-size:.85rem;color:#e6edf3;line-height:1.65;margin:0 0 .4rem">' + escHtml(String(r.ai)) + '</p>'
    + '<p style="font-size:.7rem;color:var(--muted);line-height:1.5;margin:0">Reflective guidance only, not medical advice, not a prediction, and it does not change any score.</p></div>' : '';

  const el = document.getElementById('mhb-result');
  el.innerHTML = `
    <div style="border-top:1px solid var(--border);margin-top:1.25rem;padding-top:1.25rem">
      <div style="text-align:center;margin-bottom:1.1rem">
        <div style="font-size:.55rem;color:var(--muted);letter-spacing:1.5px;font-weight:700;text-transform:uppercase;margin-bottom:.3rem">Today's Oracle Reading</div>
        <div id="oracle-avg-num" style="font-size:3rem;font-weight:900;color:${avgColor};line-height:1">${avg.toFixed(1)}</div>
        <div id="oracle-avg-count" style="font-size:.7rem;color:var(--muted);margin-bottom:.3rem">/ 10 &nbsp;&middot;&nbsp; average of ${r.count} oracles</div>
        <div id="oracle-avg-label" style="display:inline-block;background:${avgColor}20;border:1px solid ${avgColor}50;border-radius:20px;
                    padding:.25rem 1rem;font-size:.82rem;font-weight:700;color:${avgColor}">${_overallLabel(avg)}</div>
      </div>
      ${aiHTML}
      <div class="dv-orows">${rows}</div>
      ${window.HackathonDemo ? window.HackathonDemo.remedyHTML(avg) : ''}
      <div id="tarot-inline-card" style="display:none"></div>
      <div style="background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.1);border-radius:12px;padding:1rem;text-align:center">
        ${ctaHTML}
      </div>
    </div>`;
  el.style.display = 'block';
}

// ── Show a reading returned by the server, and lock the keypad for its 90 minutes ──
function applyReading(r, scroll) {
  // HACKATHON DEMO WORK (new): demo mode skips the browser cooldown stamp (normal visitors keep it).
  const _demoApply = (window.HackathonDemo && window.HackathonDemo.isDemo());
  try {
    if (!_demoApply) localStorage.setItem('mhb_last_answered', String(r.ts));
    localStorage.removeItem('oracle_session'); localStorage.removeItem('tarot_session');   // older client-side copies
  } catch(_) {}
  const questionEl = document.getElementById('oracle-question');
  if (questionEl && r.question) questionEl.value = r.question;
  [r.d1, r.d2, r.d3, r.d4].forEach((digit, i) => {
    const slot = document.getElementById('u-s' + (i + 1));
    if (slot && Number.isInteger(digit)) { slot.textContent = digit; slot.classList.add('filled'); }
  });
  const savedOmen = [...document.querySelectorAll('input[name="horary-omen"]')].find(input => input.value === r.omenKey);
  if (savedOmen) savedOmen.checked = true;
  lockUnifiedKeypad();
  renderOracleResult(r);
  // HACKATHON DEMO WORK (new): save every reading (date + overall score) to the per-browser wave chart (guests included; member history untouched).
  try { if (window.HackathonDemo) window.HackathonDemo.saveReading(r.avg, r.ts); } catch(_) {}
  showTarotResult(r.tarot);
  if (scroll) document.getElementById('mhb-result').scrollIntoView({behavior:'smooth', block:'nearest'});
  updateCooldown();
  // HACKATHON DEMO WORK (new): in demo mode leave the keypad open for the next draw.
  if (_demoApply) {
    unifiedClicks.length = 0;
    for (let i=1;i<=4;i++) { const sl=document.getElementById('u-s'+i); if(sl){sl.textContent='?';sl.classList.remove('filled');} }
    unlockUnifiedKeypad();
    const _dz=document.getElementById('ubtn-0'); if(_dz){_dz.disabled=true;_dz.style.opacity='.35';_dz.style.cursor='default';}
  }
}

// ── After the fourth tap: ask the server for the reading ──
async function showAllOracleResults() {
  const [d1,d2,d3,d4] = unifiedClicks;
  const omen = (document.querySelector('input[name="horary-omen"]:checked') || {value:'normal'}).value;
  const now = new Date();
  const res = await oracleApi({
    action:'draw', d1, d2, d3, d4, omen,
    mins: now.getHours() * 60 + now.getMinutes(),
    question: (document.getElementById('oracle-question')?.value || '').trim()
  });
  if (!res.ok) {
    const el = document.getElementById('mhb-result');
    el.innerHTML = `<div style="text-align:center;color:#f0ad4e;padding:1rem;font-size:.88rem">${escHtml(res.error || 'Something went wrong. Please try again.')}</div>`;
    el.style.display = 'block';
    unifiedClicks.length = 0;
    for (let i = 1; i <= 4; i++) { const sl = document.getElementById('u-s'+i); if (sl) { sl.textContent='?'; sl.classList.remove('filled'); } }
    unlockUnifiedKeypad();
    const z = document.getElementById('ubtn-0'); if (z) { z.disabled=true; z.style.opacity='.35'; z.style.cursor='default'; }
    return;
  }
  applyReading(res.reading, true);
}

function updateCooldown() {
  // HACKATHON DEMO WORK (new): demo mode never locks; show a demo notice instead.
  if (window.HackathonDemo && window.HackathonDemo.isDemo()) {
    const _dm = document.getElementById('cooldown-msg');
    if (_dm) { _dm.textContent = 'Demo mode (?demo=1): the 90-minute cooldown is skipped - draw as often as you like.'; _dm.style.display='block'; }
    return;
  }
  const COOLDOWN = 90 * 60 * 1000;
  const last = localStorage.getItem('mhb_last_answered');
  const msg  = document.getElementById('cooldown-msg');
  if (!last) return;
  const remaining = COOLDOWN - (Date.now() - parseInt(last));
  if (remaining <= 0) {
    unifiedClicks.length = 0;
    for (let i=1;i<=4;i++) {
      const sl = document.getElementById('u-s'+i);
      if (sl) { sl.textContent='?'; sl.classList.remove('filled'); }
    }
    unlockUnifiedKeypad();
    const question = document.getElementById('oracle-question');
    if (question) question.value='';
    const z = document.getElementById('ubtn-0');
    if (z) { z.disabled=true; z.style.opacity='.35'; z.style.cursor='default'; }
    document.getElementById('mhb-result').style.display = 'none';
    const step4 = document.getElementById('kau-cim-step');
    if (step4) step4.style.display = 'none';
    const _cs5 = document.getElementById('tarot-step');
    if (_cs5) _cs5.style.display = 'none';
    if (msg) msg.style.display = 'none';
    try { localStorage.removeItem('oracle_session'); localStorage.removeItem('tarot_session'); } catch(_) {}
    return;
  }
  lockUnifiedKeypad();
  lockKauCimBtn();
  const mins = Math.ceil(remaining / 60000);
  if (msg) { msg.textContent=`Next reading available in ${mins} minute${mins!==1?'s':''}`; msg.style.display='block'; }
  setTimeout(updateCooldown, 30000);
}

document.addEventListener('DOMContentLoaded', async () => {
  const z = document.getElementById('ubtn-0');
  if (z) { z.disabled=true; z.style.opacity='.35'; z.style.cursor='default'; }
  try { localStorage.removeItem('oracle_session'); localStorage.removeItem('tarot_session'); } catch(_) {}
  // The server keeps this browser's reading for 90 minutes; show it if there is one.
  // HACKATHON DEMO WORK (new): in demo mode start fresh every load so judges can draw immediately.
  if (window.HackathonDemo && window.HackathonDemo.isDemo()) return;
  const res = await oracleApi({ action:'current' });
  if (res.ok && res.reading) {
    applyReading(res.reading, false);
  } else {
    try { localStorage.removeItem('mhb_last_answered'); } catch(_) {}
  }
});

// ── Fourth tap → reading (the Kau Cim stick and the card are drawn on the server) ──
function drawKauCim() {
  if (unifiedClicks.length < 4) return;
  lockKauCimBtn();
  showAllOracleResults();
}
</script>


<script>
// ══════════════════════════════════════════════════════════════
// GYPSY WITCH FORTUNE TELLING CARDS — 55-Card Tarot Oracle
// ══════════════════════════════════════════════════════════════
const IS_LOGGED_IN = <?= (!empty($_SESSION['bio_uid']) && empty($_SESSION['bio_need_pin'])) ? 'true' : 'false' ?>;
const DV_CSRF = <?= json_encode($_SESSION['csrf_token']) ?>;

// Members see their card face; guests see the card back. The card itself is drawn on the server.
function showTarotResult(t) {
  if (!t) return;
  const member = typeof t.idx === 'number';
  const cardFile = member ? `${String(t.idx + 1).padStart(2,'0')}.svg` : 'back.svg';
  const cardArt = `<img class="dv-inline-card-image" src="assets/cards/${cardFile}" width="88" height="132" alt="${member ? escHtml(t.n) + ' card face' : 'DharmaVera card back'}">`;
  const inlineCard = document.getElementById('tarot-inline-card');
  if (inlineCard) {
    inlineCard.innerHTML = `<div class="dv-drawn-card">${cardArt}<div><div class="dv-drawn-kicker">YOUR SEVENTH ORACLE</div><strong>${member ? escHtml(t.n) : 'Your card is drawn'}</strong><p>${member ? 'Open My DharmaVera for the full card meaning and history.' : 'Create a free profile to reveal your card and its meaning.'}</p></div></div>`;
    inlineCard.style.display = 'block';
  }
  const _ks = document.getElementById('kau-cim-step');
  if (_ks) _ks.style.display = 'none';
  const _ts5 = document.getElementById('tarot-step');
  if (_ts5) _ts5.style.display = 'none';
}
function drawTarotCard() {}   // the card is drawn on the server with the rest of the reading
</script>

<script>
// ══════════════════════════════════════════════════════════════
// UUID / STREAK / SHARE / COMPATIBILITY — Baydin Feature Layer
// ══════════════════════════════════════════════════════════════

// ── UUID identity ──
function getUUID() {
  let id = localStorage.getItem('baydin_uuid');
  if (!id) {
    id = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
      const r = Math.random() * 16 | 0;
      return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
    });
    localStorage.setItem('baydin_uuid', id);
  }
  return id;
}
const UUID = getUUID();

// ── API helper ──
function apiPost(data) {
  data.uuid = UUID;
  return fetch('baydin_api.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams(data).toString()
  }).then(r => r.json()).catch(() => ({}));
}

// ── Streak init (on every page load with results) ──
<?php if ($result): ?>
(function initStreak() {
  if (!<?= _blkV('idx_streak') ? 'true' : 'false' ?>) return; // admin disabled this block for the visitor's rank
  apiPost({ action: 'init', name: <?= json_encode($r['name']) ?> }).then(d => {
    if (!d.ok) return;
    const card = document.getElementById('streak-card');
    if (card) {
      card.style.display = 'block';
      document.getElementById('streak-count').textContent = d.streak;
      document.getElementById('streak-max').textContent   = d.max_streak;
      document.getElementById('streak-pts').textContent   = d.total_points + ' dUSD';
      if (d.pts_earned > 0) showStreakToast('🔥 Daily visit! +' + d.pts_earned + ' dUSD');
      // Update earn-points badges dynamically from admin rules
      if (d.point_rules && d.point_rules.length) {
        const colors  = ['#5cb85c','#f0ad4e','#58a6ff','#c792ea','#e05252'];
        const bgs     = ['#1a2a1a','#2a1a0d','#0d1a2a','#1a0d2a','#2a0d0d'];
        const emojiMap = {daily_visit:'📅',share:'📤',session_5min:'⏱',compat_share:'💞'};
        const lblMap   = {daily_visit:'Daily visit',share:'Sharing',session_5min:'5 min+ session',compat_share:'Compat share'};
        const badgesEl = document.getElementById('streak-earn-badges');
        if (badgesEl) {
          badgesEl.innerHTML = d.point_rules.map((r,i)=>{
            const clr=colors[i%colors.length], bg=bgs[i%bgs.length];
            return `<span class="streak-badge" style="background:${bg};color:${clr};border:1px solid ${clr}44">${emojiMap[r.key]||'⭐'} ${lblMap[r.key]||r.key} +${r.pts} dUSD</span>`;
          }).join('');
        }
      }
    }
  });

  // 5-min heartbeat
  setTimeout(() => {
    apiPost({ action: 'heartbeat' }).then(d => {
      if (d.pts_earned > 0) {
        showStreakToast('⏱ 5 min+ session! +1 dUSD');
        const el = document.getElementById('streak-pts');
        if (el) {
          const cur = parseInt(el.textContent) || 0;
          el.textContent = (cur + 1) + ' dUSD';
        }
      }
    });
  }, 5 * 60 * 1000);
})();
<?php endif; ?>

function showStreakToast(msg) {
  const el = document.getElementById('streak-toast');
  if (!el) return;
  el.textContent = msg;
  el.style.display = 'block';
  setTimeout(() => { el.style.display = 'none'; }, 4000);
}

// ── Log share action ──
function logShare() {
  apiPost({ action: 'share' }).then(d => {
    if (d.pts_earned > 0) {
      showStreakToast('📤 Shared! +2 points earned');
      const el = document.getElementById('streak-pts');
      if (el) {
        const cur = parseInt(el.textContent) || 0;
        el.textContent = (cur + 2) + ' pts';
      }
    }
  });
}

// ── PDF / Print ──
function printPDF() {
  logShare();
  window.print();
}

// ── Copy link ──
function copyLink(url) {
  if (navigator.clipboard) {
    navigator.clipboard.writeText(url).then(() => showToast('Link copied!'));
  } else {
    const el = document.createElement('textarea');
    el.value = url; document.body.appendChild(el);
    el.select(); document.execCommand('copy');
    document.body.removeChild(el);
    showToast('Link copied!');
  }
}
function showToast(msg) {
  let t = document.getElementById('copy-toast');
  if (!t) {
    t = document.createElement('div');
    t.id = 'copy-toast';
    t.style.cssText = 'position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:#1f6feb;color:#fff;padding:8px 20px;border-radius:20px;font-size:.85rem;font-weight:600;z-index:9999;opacity:0;transition:opacity .3s';
    document.body.appendChild(t);
  }
  t.textContent = msg;
  t.style.opacity = '1';
  setTimeout(() => { t.style.opacity = '0'; }, 2500);
}

// ── Native Web Share (mobile) ──
function nativeShare() {
  if (navigator.share) {
    navigator.share({
      title: <?= json_encode($result ? ($shareTitle ?? 'My DharmaVera Reading') : 'DharmaVera') ?>,
      text:  <?= json_encode($result ? ($shareText  ?? '') : '') ?>,
      url:   <?= json_encode($result ? ($shareUrl   ?? '') : SITE_ORIGIN.'/') ?>
    }).then(() => logShare()).catch(() => {});
  }
}
// Add native share button if supported
if (navigator.share) {
  document.querySelectorAll('.share-bar').forEach(bar => {
    const btn = document.createElement('a');
    btn.className = 'share-btn native'; btn.href = '#';
    btn.title = 'Share'; btn.setAttribute('aria-label', 'Share');
    btn.innerHTML = '<span class="share-ic"><i class="bi bi-box-arrow-up"></i></span><span class="share-lbl">Share</span>';
    btn.onclick = (e) => { e.preventDefault(); nativeShare(); };
    bar.prepend(btn);
  });
}

// ═══════════════════════════════════════════════════
// COMPATIBILITY CALCULATOR
// ═══════════════════════════════════════════════════

// Mahabote lookup table (mirrors PHP $mhb_table)
const MHB_TABLE = [
  ['ပုတိ','သိုက်','မရဏ','အဓိပတိ','ရာဇ','အထွန်း','ဘင်္ဂ'],
  ['ဘင်္ဂ','ပုတိ','သိုက်','မရဏ','အဓိပတိ','ရာဇ','အထွန်း'],
  ['အထွန်း','ဘင်္ဂ','ပုတိ','သိုက်','မရဏ','အဓိပတိ','ရာဇ'],
  ['ရာဇ','အထွန်း','ဘင်္ဂ','ပုတိ','သိုက်','မရဏ','အဓိပတိ'],
  ['အဓိပတိ','ရာဇ','အထွန်း','ဘင်္ဂ','ပုတိ','သိုက်','မရဏ'],
  ['မရဏ','အဓိပတိ','ရာဇ','အထွန်း','ဘင်္ဂ','ပုတိ','သိုက်'],
  ['သိုက်','မရဏ','အဓိပတိ','ရာဇ','အထွန်း','ဘင်္ဂ','ပုတိ'],
];
// Mahabote type → score (for compatibility calc)
const MHB_SCORE = {'အဓိပတိ':8,'အထွန်း':9,'သိုက်':8,'ရာဇ':8,'မရဏ':6,'ဘင်္ဂ':5,'ပုတိ':6};
// Mahabote compatibility pairs matrix (7×7)
// Order: ဘင်္ဂ=0,မရဏ=1,အဓိပတိ=2,အထွန်း=3,သိုက်=4,ရာဇ=5,ပုတိ=6
const MHB_NAMES = ['ဘင်္ဂ','မရဏ','အဓိပတိ','အထွန်း','သိုက်','ရာဇ','ပုတိ'];
const MHB_COMPAT = [
  [55,55,65,60,60,65,70],
  [55,55,60,65,60,60,65],
  [65,60,75,90,80,85,70],
  [60,65,90,75,85,80,75],
  [60,60,80,85,75,85,70],
  [65,60,85,80,85,75,75],
  [70,65,70,75,70,75,70],
];

// Chinese Zodiac compatibility matrix (12×12)
const CZ_ANIMALS = ['Rat','Ox','Tiger','Rabbit','Dragon','Snake','Horse','Goat','Monkey','Rooster','Dog','Pig'];
const CZ_COMPAT = [
  [75,65,55,65,90,70,35,55,90,65,60,70],
  [65,75,70,60,65,90,55,35,65,90,65,70],
  [55,70,75,65,60,65,90,65,35,55,90,65],
  [65,60,65,75,70,55,65,90,55,35,60,90],
  [90,65,60,70,75,65,60,55,90,70,35,60],
  [70,90,65,55,65,75,70,60,65,90,60,35],
  [35,55,90,65,60,70,75,65,60,55,90,65],
  [55,35,65,90,55,60,65,75,70,60,65,90],
  [90,65,35,55,90,65,60,70,75,65,60,55],
  [65,90,55,35,70,90,55,60,65,75,70,60],
  [60,65,90,60,35,60,90,65,60,70,75,65],
  [70,70,65,90,60,35,65,90,55,60,65,75],
];

function getDOW(dateStr) {
  return new Date(dateStr + 'T12:00:00').getDay(); // 0=Sun
}
function getMyanmarYear(year, month, day) {
  return (month < 4 || (month === 4 && day < 17)) ? year - 639 : year - 638;
}
function getMahabote(dateStr) {
  const d = new Date(dateStr + 'T12:00:00');
  const yr = d.getFullYear(), mo = d.getMonth()+1, dy = d.getDate();
  const dow = d.getDay();
  const meYear = getMyanmarYear(yr, mo, dy);
  const rem = ((meYear % 7) + 7) % 7;
  return MHB_TABLE[rem][dow];
}
function getCZIndex(year) {
  return ((year - 1900) % 12 + 12) % 12;
}

// Current user's data
const ME_DOB  = <?= json_encode($result ? ($result['birthdate'] ?? '') : '') ?>;
const ME_NAME = <?= json_encode($result ? ($result['name'] ?? '') : '') ?>;
const ME_MHB  = <?= json_encode($result ? ($result['mahabote']['mhb_mm'] ?? '') : '') ?>;
const ME_CZ   = <?= json_encode($result ? ($result['cz']['index'] ?? 0) : 0) ?>;

let compatTarget = '';

function calcCompat() {
  const p2Name = document.getElementById('p2-name').value.trim();
  const p2Dob  = document.getElementById('p2-dob').value;
  if (!p2Name || !p2Dob) { alert('Please enter partner name and birth date.'); return; }

  const p2Year = parseInt(p2Dob.split('-')[0]);
  const p2CZ   = getCZIndex(p2Year);
  const p2MHB  = getMahabote(p2Dob);

  // CZ compatibility
  const czScore = CZ_COMPAT[ME_CZ][p2CZ];

  // Mahabote compatibility
  const myMhbIdx = MHB_NAMES.indexOf(ME_MHB);
  const p2MhbIdx = MHB_NAMES.indexOf(p2MHB);
  const mhbScore = (myMhbIdx >= 0 && p2MhbIdx >= 0) ? MHB_COMPAT[myMhbIdx][p2MhbIdx] : 65;

  // Overall: 60% CZ + 40% Mahabote
  const overall = Math.round(czScore * 0.6 + mhbScore * 0.4);

  // ── Display results ──
  const ringEl = document.getElementById('compat-ring');
  const color  = overall >= 75 ? '#5cb85c' : overall >= 60 ? '#f0ad4e' : '#e05252';
  ringEl.style.borderColor = color;
  ringEl.style.color = color;
  document.getElementById('compat-pct').textContent = overall + '%';

  const labels = {75:'💞 Excellent Match',60:'👍 Good Match',0:'⚠️ Challenging'};
  const label  = overall >= 75 ? labels[75] : overall >= 60 ? labels[60] : labels[0];
  document.getElementById('compat-label').style.color = color;
  document.getElementById('compat-label').textContent = label;

  document.getElementById('compat-names').innerHTML =
    `<span style="color:${color}">${escHtml(ME_NAME)}</span> <span style="color:var(--muted)">×</span> ${escHtml(p2Name)}`;
  document.getElementById('cz-score').textContent  = czScore + '%';
  document.getElementById('mhb-score').textContent = mhbScore + '%';
  document.getElementById('cz-bar').style.width    = czScore + '%';
  document.getElementById('mhb-bar').style.width   = mhbScore + '%';

  const cz1 = CZ_ANIMALS[ME_CZ], cz2 = CZ_ANIMALS[p2CZ];
  document.getElementById('compat-summary').innerHTML =
    `${escHtml(ME_NAME)}: <strong style="color:#f0ad4e">${escHtml(cz1)}</strong> · ${escHtml(ME_MHB)} &nbsp;|&nbsp; ` +
    `${escHtml(p2Name)}: <strong style="color:#f0ad4e">${escHtml(cz2)}</strong> · ${escHtml(p2MHB)}`;

  document.getElementById('compat-result').style.display = 'block';

  // Show share button if >= 60%
  document.getElementById('compat-share-btn').style.display = overall >= 60 ? 'inline-flex' : 'none';

  // Update URL with p2 params (for sharing)
  const url = new URL(window.location.href);
  url.searchParams.set('p2_name', p2Name);
  url.searchParams.set('p2_dob',  p2Dob);
  history.replaceState({}, '', url.toString());
  compatTarget = p2Name + '|' + p2Dob;

  // Load social stats from server
  loadCompatStats(compatTarget);
}

function loadCompatStats(target) {
  apiPost({ action: 'get_compat_stats', target }).then(d => {
    document.getElementById('compat-like-count').textContent   = d.likes || 0;
    document.getElementById('compat-follow-count').textContent = d.followers || 0;
    document.getElementById('compat-followers-label').textContent =
      d.followers > 0 ? d.followers + ' follower' + (d.followers !== 1 ? 's' : '') : '';

    const likeBtn = document.getElementById('compat-like-btn');
    const followBtn = document.getElementById('compat-follow-btn');
    if (d.liked)     { likeBtn.classList.add('liked');     likeBtn.querySelector('.bi').className   = 'bi bi-heart-fill'; }
    if (d.following) { followBtn.classList.add('following'); followBtn.querySelector('.bi').className = 'bi bi-person-check-fill'; }
  });
}

function toggleCompatLike() {
  if (!compatTarget) return;
  apiPost({ action: 'toggle_like', target: compatTarget }).then(d => {
    const btn = document.getElementById('compat-like-btn');
    document.getElementById('compat-like-count').textContent = d.total_likes || 0;
    if (d.liked) {
      btn.classList.add('liked');
      btn.querySelector('.bi').className = 'bi bi-heart-fill';
    } else {
      btn.classList.remove('liked');
      btn.querySelector('.bi').className = 'bi bi-heart';
    }
  });
}

function toggleCompatFollow() {
  if (!compatTarget) return;
  apiPost({ action: 'toggle_follow', target: compatTarget }).then(d => {
    const btn = document.getElementById('compat-follow-btn');
    document.getElementById('compat-follow-count').textContent = d.followers || 0;
    if (d.following) {
      btn.classList.add('following');
      btn.querySelector('.bi').className = 'bi bi-person-check-fill';
    } else {
      btn.classList.remove('following');
      btn.querySelector('.bi').className = 'bi bi-person-plus-fill';
    }
  });
}

function shareCompat() {
  const url   = window.location.href;
  const p2n   = document.getElementById('p2-name').value || 'partner';
  const pct   = document.getElementById('compat-pct').textContent;
  const text  = `💞 ${ME_NAME} & ${p2n} — ${pct} astrological compatibility! Check yours at <?= SITE_DOMAIN ?>`;

  if (navigator.share) {
    navigator.share({ title: 'Compatibility Reading', text, url }).then(() => {
      apiPost({ action: 'compat_share' }).then(d => {
        if (d.pts_earned > 0) showStreakToast('💞 Compat shared! +2 points earned');
      });
    }).catch(() => {});
  } else {
    window.open('https://wa.me/?text=' + encodeURIComponent(text + '\n' + url), '_blank');
    apiPost({ action: 'compat_share' }).then(d => {
      if (d.pts_earned > 0) showStreakToast('💞 Compat shared! +2 points earned');
    });
  }
}

// ── Auto-run compatibility if p2 params are in URL ──
document.addEventListener('DOMContentLoaded', () => {
  const p = new URLSearchParams(window.location.search);
  const p2n = p.get('p2_name'), p2d = p.get('p2_dob');
  if (p2n && p2d && document.getElementById('p2-name')) {
    document.getElementById('p2-name').value = p2n;
    document.getElementById('p2-dob').value  = p2d;
    calcCompat();
  }
});

// ── Auto-fill Your Details from biorhythm login (localStorage) ──
<?php if (empty($_FORM['name'])): // Only when form hasn't been submitted yet ?>
document.addEventListener('DOMContentLoaded', () => {
  try {
    const saved = JSON.parse(localStorage.getItem('bio_saved_login') || 'null');
    if (!saved || !saved.name || !saved.dob) return;
    const form = document.querySelector('form[method="POST"]');
    if (!form) return;
    const nameEl = form.querySelector('[name="name"]');
    const dobEl  = form.querySelector('[name="birthdate"]');
    const timeEl = form.querySelector('[name="birthtime"]');
    if (nameEl) nameEl.value = saved.name;
    if (dobEl)  dobEl.value  = saved.dob;
    if (timeEl && saved.btime) timeEl.value = saved.btime;
    // Auto-submit so results appear immediately
    form.submit();
  } catch(_) {}
});
<?php endif; ?>

<?php if ($result): ?>
// ── Janaka Right Now ──
const BIRTH_DOW_JS   = <?= (int)$r['dow'] + 1 ?>;  // 1=Sun … 7=Sat
const OVERALL_ORIG_SUM   = <?= (int)$r['overall_sum'] ?>;
const OVERALL_ORIG_COUNT = <?= (int)$r['overall_count'] ?>;

const janakaFortunes = {
  1:{ status:'Taw',   en:'Striving',    score:4,  color:'#f0ad4e', icon:'⚙️',
      desc:'The ship is being built. A time of toil and hardship — keep pushing forward.' },
  2:{ status:'Hlwin', en:'Adrift',      score:3,  color:'#868e96', icon:'🌊',
      desc:'The ship has set sail without a set course. Instability and unexpected change are common.' },
  3:{ status:'Pyat',  en:'Shipwrecked', score:2,  color:'#e05252', icon:'💥',
      desc:'The ship is broken. Serious obstacles and danger are present — be very cautious.' },
  4:{ status:'Hsay',  en:'Rescued',     score:6,  color:'#48CAE4', icon:'🛟',
      desc:'Mechalā the goddess arrives to rescue. Unexpected help appears — things will resolve well.' },
  5:{ status:'Kyauk', en:'On the Rock', score:7,  color:'#74b816', icon:'🪨',
      desc:'You have reached the rock. Fortune is turning — moving from difficulty toward better times.' },
  6:{ status:'Nan',   en:'Enthroned',   score:9,  color:'#9C88FF', icon:'👑',
      desc:'You sit on the throne. Advancement, recognition, and success are strongly supported.' },
  7:{ status:'San',   en:'Prosperous',  score:10, color:'#51cf66', icon:'✨',
      desc:'A time of enjoying abundance. Extremely auspicious — wealth, happiness, and great fortune.' },
};

function checkJanakaNow() {
  const now = new Date();
  const minsSince6 = (now.getHours() * 60 + now.getMinutes() - 360 + 1440) % 1440;
  const yam = (Math.floor(minsSince6 / 90) % 8) + 1;

  let idx = (BIRTH_DOW_JS + yam) % 7;
  if (idx === 0) idx = 7;
  const f = janakaFortunes[idx];

  const timeStr = now.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});

  document.getElementById('janaka-now-result').innerHTML = `
    <div class="card-dark2 p-3">
      <div class="d-flex align-items-center gap-3 mb-2">
        <div style="font-size:2rem;line-height:1">${f.icon}</div>
        <div class="flex-grow-1">
          <div style="font-weight:700;font-size:.98rem;color:${f.color}">${f.status} · ${f.en}</div>
          <div style="font-size:.72rem;color:var(--muted)">Yama period ${yam} · Asked at ${timeStr}</div>
        </div>
        <div class="score-ring" style="border-color:${f.color};color:${f.color};width:48px;height:48px;font-size:1.1rem;flex-shrink:0">${f.score}</div>
      </div>
      <div style="font-size:.83rem;color:#ccc;line-height:1.6">${f.desc}</div>
    </div>`;
  document.getElementById('janaka-now-result').style.display = 'block';

  const btn = document.getElementById('janaka-now-btn');
  if (btn) { btn.disabled = true; btn.style.opacity = '.4'; }

  // Update Overall Score (add Janaka moment as an additional component)
  const newSum   = OVERALL_ORIG_SUM + f.score;
  const newCount = OVERALL_ORIG_COUNT + 1;
  const newScore = Math.round(newSum / newCount);
  const colors   = ['#e05252','#e05252','#e05252','#e05252','#f0ad4e','#f0ad4e','#74b816','#74b816','#51cf66','#51cf66','#51cf66'];
  const c = colors[Math.min(10, Math.max(0, newScore))];
  const ring = document.getElementById('overall-ring');
  const num  = document.getElementById('overall-score-num');
  if (ring) { ring.style.borderColor = c; ring.style.color = c; }
  if (num)  { num.textContent = newScore; }
  const note = document.getElementById('overall-update-note');
  if (note) {
    note.innerHTML = `<i class="bi bi-plus-circle-fill me-1"></i>Updated: Janaka Moment (${f.score}/10) included in average`;
    note.style.display = 'block';
  }
}
<?php endif; ?>
</script>

<script>
if ("serviceWorker" in navigator) { window.addEventListener("load", () => navigator.serviceWorker.register("/sw.js").catch(() => {})); }
</script>
</body>
</html>
