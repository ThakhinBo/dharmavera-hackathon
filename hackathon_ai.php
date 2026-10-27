<?php
/*
 * HACKATHON Step 4 (new): server-side Meta Model API guidance sentence.
 *
 * Called from the oracle draw flow AFTER the 7 scores and overall score are
 * computed. Generates exactly one short reflective sentence per NEW reading
 * draw via https://api.meta.ai/v1/chat/completions (model muse-spark-1.1
 * by default, overridable from server config). The sentence is stored inside
 * the reading's JSON blob so cooldown/current reuse it without regenerating.
 * Any failure (missing key, timeout, API error) returns null and the reading
 * flow continues perfectly with no sentence shown and nothing faked.
 *
 * Privacy: ONLY numeric scores (overall + individual scores below 5) are sent.
 * The typed question memo and any personal text are NEVER sent to the API.
 *
 * Key handling: the key is read server-side only from a private config file
 * OUTSIDE the web root (same pattern as dv_private/oracle_engine.php). It
 * NEVER appears here, in JavaScript, in logs, in chat, or in commits.
 */
if (PHP_SAPI !== 'cli' && basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    http_response_code(403);
    exit;
}

const HACKATHON_AI_ENDPOINT = 'https://api.meta.ai/v1/chat/completions';
const HACKATHON_AI_MODEL_DEFAULT = 'muse-spark-1.1';

/*
 * HACKATHON diagnostic work (new): one JSON line per AI attempt in a PRIVATE
 * log outside the web root (same folder as the key file; never in public_html,
 * never fetchable by URL). Whitelisted fields only: timestamp, key path tried,
 * file exists/readable, key state (missing/placeholder/real), key LENGTH
 * (never the value), model, HTTP status, curl error, API error body capped at
 * 200 chars, plus temporary response-shape fields (same privacy rules). The
 * key, the full request, and any typed question text are NEVER written here.
 * A failed write must never break a reading.
 */
/*
 * HACKATHON diagnostic work (temporary): record Meta's actual response shape -
 * top-level keys, choices count, types/keys of choices[0] and its message, and
 * the raw body capped at 500 chars. Never the key, never question text.
 */
function hackathon_ai_shape(array $dbg, $data, string $raw): array {
    $dbg['resp_keys'] = is_array($data) ? array_keys($data) : null;
    $dbg['choices_n'] = (is_array($data) && isset($data['choices']) && is_array($data['choices'])) ? count($data['choices']) : 'MISSING';
    $c0 = (is_array($data) && isset($data['choices']) && is_array($data['choices']) && array_key_exists(0, $data['choices'])) ? $data['choices'][0] : null;
    $dbg['c0_type'] = is_object($c0) ? 'object' : gettype($c0);
    $dbg['c0_keys'] = is_array($c0) ? array_keys($c0) : null;
    $dbg['finish_reason'] = (is_array($c0) && array_key_exists('finish_reason', $c0)) ? $c0['finish_reason'] : null;
    $msg = is_array($c0) ? ($c0['message'] ?? null) : null;
    $dbg['msg_type'] = is_object($msg) ? 'object' : gettype($msg);
    $dbg['msg_keys'] = is_array($msg) ? array_keys($msg) : null;
    $dbg['content_type'] = is_array($msg) ? (is_array($msg['content'] ?? null) ? 'array[' . count($msg['content']) . ']' : gettype($msg['content'] ?? null)) : null;
    $dbg['raw_500'] = function_exists('mb_substr') ? mb_substr($raw, 0, 500) : substr($raw, 0, 500);
    return $dbg;
}

/*
 * HACKATHON (fixed): extract reply text from Meta's actual shape, trying in
 * order: choices[0].message.content as string, content as array of text parts,
 * choices[0].text. Anything else returns null (logged with shape, no sentence).
 */
function hackathon_ai_extract_text($data): ?string {
    if (!is_array($data) || !isset($data['choices']) || !is_array($data['choices'])) return null;
    if (!array_key_exists(0, $data['choices']) || !is_array($data['choices'][0])) return null;
    $c0 = $data['choices'][0];
    $msg = $c0['message'] ?? null;
    if (is_array($msg) && array_key_exists('content', $msg)) {
        $content = $msg['content'];
        if (is_string($content)) return $content;
        if (is_array($content)) {
            $parts = [];
            foreach ($content as $part) {
                if (is_string($part)) { $parts[] = $part; continue; }
                if (is_array($part) && isset($part['text']) && is_string($part['text'])) $parts[] = $part['text'];
                elseif (is_array($part) && isset($part['content']) && is_string($part['content'])) $parts[] = $part['content'];
            }
            if ($parts) return implode(' ', $parts);
        }
    }
    if (isset($c0['text']) && is_string($c0['text'])) return $c0['text'];
    return null;
}

function hackathon_ai_debug_log(array $dbg): void {
    static $allowed = ['key_path', 'key_file_exists', 'key_file_readable', 'key_state', 'key_len', 'model', 'http_status', 'curl_error', 'api_error_200', 'resp_keys', 'choices_n', 'c0_type', 'c0_keys', 'finish_reason', 'msg_type', 'msg_keys', 'content_type', 'raw_500'];
    $line = ['ts' => gmdate('c')];
    foreach ($allowed as $k) {
        $line[$k] = array_key_exists($k, $dbg) ? $dbg[$k] : null;
    }
    $path = dirname(__DIR__) . '/dv_private/hackathon_ai_debug.log';
    @file_put_contents($path, json_encode($line, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function hackathon_ai_config(): array {
    static $cfg = null;
    if ($cfg !== null) return $cfg;
    $cfg = ['key' => null, 'model' => HACKATHON_AI_MODEL_DEFAULT];
    $path = dirname(__DIR__) . '/dv_private/hackathon_meta_key.php';
    // HACKATHON diagnostic work (new): key-file facts for the private debug log (never the key value).
    $cfg['key_path'] = $path;
    $cfg['key_file_exists'] = is_file($path);
    $cfg['key_file_readable'] = is_readable($path);
    $cfg['key_state'] = 'missing';
    $cfg['key_len'] = 0;
    if (is_file($path)) {
        $data = @include $path;
        if (is_array($data)) {
            if (!empty($data['meta_model']) && is_string($data['meta_model'])) {
                $m = trim($data['meta_model']);
                if ($m !== '') $cfg['model'] = $m;
            }
            $k = $data['meta_api_key'] ?? null;
            if (is_string($k)) {
                $k = trim($k);
                // HACKATHON diagnostic work (new): record placeholder-vs-real + length only.
                if ($k !== '' && $k !== 'YOUR_META_API_KEY_HERE') { $cfg['key'] = $k; $cfg['key_state'] = 'real'; $cfg['key_len'] = strlen($k); }
                elseif ($cfg['key'] === null && $k !== '') $cfg['key_state'] = 'placeholder';
            }
        }
    }
    return $cfg;
}

function hackathon_ai_log(string $msg): void {
    // Server-side log only. Callers must NEVER pass the key or auth headers.
    error_log('[hackathon-ai] ' . $msg);
}

function hackathon_ai_sentence(float $avg, array $lowScores): ?string {
    $cfg = hackathon_ai_config();
    // HACKATHON diagnostic work (new): one whitelisted line per attempt in the private log.
    $dbg = [
        'key_path' => $cfg['key_path'], 'key_file_exists' => $cfg['key_file_exists'],
        'key_file_readable' => $cfg['key_file_readable'], 'key_state' => $cfg['key_state'],
        'key_len' => $cfg['key_len'], 'model' => $cfg['model'],
        'http_status' => null, 'curl_error' => null, 'api_error_200' => null,
        'resp_keys' => null, 'choices_n' => null, 'c0_type' => null, 'c0_keys' => null,
        'finish_reason' => null, 'msg_type' => null, 'msg_keys' => null, 'content_type' => null,
        'raw_500' => null,
    ];
    if ($cfg['key'] === null) { hackathon_ai_debug_log($dbg); return null; }
    if (!function_exists('curl_init')) { hackathon_ai_log('cURL unavailable; skipping sentence'); $dbg['curl_error'] = 'cURL unavailable'; hackathon_ai_debug_log($dbg); return null; }
    $avgR = round($avg, 1);
    $lows = [];
    foreach ($lowScores as $s) {
        if (is_numeric($s)) $lows[] = round((float)$s, 1);
    }
    sort($lows);
    $user = 'Overall score: ' . $avgR . ' out of 10.';
    if ($lows) $user .= ' Low individual scores: ' . implode(', ', $lows) . '.';
    $user .= ' Write one calm reflective sentence tied to this score and to timing and quiet reflection, such as a calm next step or a remedy reflection.';
    $user .= ' Do not give medical advice, a diagnosis, a personality analysis, a guaranteed prediction, or a fate claim. Reply with only the sentence, nothing else.';
    $payload = [
        'model' => $cfg['model'],
        'messages' => [
            ['role' => 'system', 'content' => 'You write short reflective guidance for a fortune-reading app. Reply with exactly one short sentence, no more than 30 words, in plain language.'],
            ['role' => 'user', 'content' => $user],
        ],
        // HACKATHON (fixed): NO max_tokens - the model spends the budget on reasoning (debug log showed finish_reason=length with content=null) and the working test.js sends no cap; length is enforced by the prompt + the trim below.
        'temperature' => 0.5,
    ];
    $ch = curl_init(HACKATHON_AI_ENDPOINT);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $cfg['key']],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);
    $raw = curl_exec($ch);
    if ($raw === false) { hackathon_ai_log('request failed: ' . curl_error($ch)); $dbg['curl_error'] = substr(curl_error($ch), 0, 200); curl_close($ch); hackathon_ai_debug_log($dbg); return null; }
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode($raw, true);
    if ($code !== 200 || !is_array($data)) {
        $excerpt = function_exists('mb_substr') ? mb_substr((string)$raw, 0, 500) : substr((string)$raw, 0, 500);
        hackathon_ai_log('API error HTTP ' . $code . ': ' . $excerpt);
        $dbg['http_status'] = $code;
        $dbg['api_error_200'] = function_exists('mb_substr') ? mb_substr((string)$raw, 0, 200) : substr((string)$raw, 0, 200);
        $dbg = hackathon_ai_shape($dbg, $data, (string)$raw);
        hackathon_ai_debug_log($dbg);
        return null;   // model-not-found lands here with its exact text; never guess another model ID
    }
    // HACKATHON (fixed): extract via helper (content string, content parts, c0.text).
    $text = hackathon_ai_extract_text($data);
    if (!is_string($text) || trim($text) === '') { hackathon_ai_log('API response had no usable text content'); $dbg['http_status'] = $code; $dbg['api_error_200'] = 'response had no usable text content'; $dbg = hackathon_ai_shape($dbg, $data, (string)$raw); hackathon_ai_debug_log($dbg); return null; }
    $text = trim(preg_replace('/\s+/', ' ', $text));
    if ($text === '') { $dbg['http_status'] = $code; $dbg = hackathon_ai_shape($dbg, $data, (string)$raw); hackathon_ai_debug_log($dbg); return null; }
    // HACKATHON (fixed): trim to one sentence, 30 words or less, before display.
    $sentences = preg_split('/(?<=[.!?])\s+/', $text, 2);
    if (is_array($sentences) && isset($sentences[0]) && trim($sentences[0]) !== '') $text = trim($sentences[0]);
    $words = preg_split('/\s+/', $text);
    if (is_array($words) && count($words) > 30) $text = implode(' ', array_slice($words, 0, 30));
    if (function_exists('mb_strlen') && mb_strlen($text) > 500) $text = mb_substr($text, 0, 500);
    elseif (strlen($text) > 500) $text = substr($text, 0, 500);
    $dbg['http_status'] = $code;
    $dbg = hackathon_ai_shape($dbg, $data, (string)$raw);
    hackathon_ai_debug_log($dbg);
    return $text;
}
