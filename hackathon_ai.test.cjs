// hackathon_ai.test.cjs - HACKATHON Step 4 focused checks for the Meta AI guidance.
// Run from the site root:  node hackathon_ai.test.cjs
// Static verification of the server-side module (endpoint, model, scores-only
// payload, prompt constraints, timeouts, key hygiene). Exits nonzero on failure.
const fs = require('fs');
const path = require('path');

let pass = 0;
let fail = 0;
function ok(cond, name) {
  if (cond) { pass++; console.log('PASS ' + name); }
  else { fail++; console.log('FAIL ' + name); }
}

const root = __dirname;
const aiPath = path.join(root, 'hackathon_ai.php');
const aiSrc = fs.existsSync(aiPath) ? fs.readFileSync(aiPath, 'utf8') : '';
ok(aiSrc !== '', 'hackathon_ai.php ships with the site');
if (aiSrc === '') { console.log('PASS: 0 FAIL: 1'); process.exit(1); }

// Endpoint + model (same endpoint test.js validated; no guessed IDs).
ok(aiSrc.indexOf('https://api.meta.ai/v1/chat/completions') !== -1, 'uses the validated Meta endpoint');
ok(aiSrc.indexOf('muse-spark-1.1') !== -1, 'default model muse-spark-1.1');
ok(aiSrc.indexOf('meta_model') !== -1, 'model override comes from server config');
ok(/never guess(es)? another model/i.test(aiSrc), 'no model guessing on model-not-found');

// Prompt constraints (checked on full text: prompt strings included).
['30 words', 'medical advice', 'diagnosis', 'personality analysis', 'guaranteed prediction', 'fate claim', 'only the sentence'].forEach(function (w) {
  ok(aiSrc.indexOf(w) !== -1, 'prompt constrains: ' + w);
});

// Scores-only payload: strip comments, then forbid personal-data references.
const codeOnly = aiSrc.replace(/\/\*[\s\S]*?\*\//g, '').split('\n').map(function (ln) {
  const i = ln.indexOf('//');
  return i === -1 ? ln : ln.slice(0, i);
}).join('\n');
['question', 'memo', '$_POST', '$_GET', '$_COOKIE', '$_SESSION'].forEach(function (w) {
  ok(codeOnly.indexOf(w) === -1, 'payload code has no ' + w);
});
ok(codeOnly.indexOf('lowScores') !== -1 && codeOnly.indexOf('$avg') !== -1, 'payload built from scores only');

// Reliability: timeouts, key-absent early return, server-side-only logging.
ok(codeOnly.indexOf('CURLOPT_TIMEOUT') !== -1, 'request timeout set');
ok(codeOnly.indexOf('CURLOPT_CONNECTTIMEOUT') !== -1, 'connect timeout set');
ok(codeOnly.indexOf('error_log') !== -1, 'errors logged server-side');
ok(/if\s*\(\$cfg\['key'\]\s*===\s*null\)\s*\{\s*hackathon_ai_debug_log\(\$dbg\);\s*return null;\s*\}/.test(codeOnly), 'missing key logs attempt and returns null before any call');

// Key hygiene in this module: placeholder appears only as a rejected sentinel.
const placeholderUses = (codeOnly.match(/YOUR_META_API_KEY_HERE/g) || []).length;
ok(placeholderUses === 1 && /!==\s*'YOUR_META_API_KEY_HERE'/.test(codeOnly), 'placeholder only rejected, never used');
ok(!/sk-[A-Za-z0-9]{12,}/.test(aiSrc), 'no sk- token in module');
ok(!/Bearer\s+[A-Za-z0-9_\-\.]{20,}/.test(aiSrc), 'no literal bearer token in module');

// Root-wide scan: no real key material in served code.
function servedFiles(dir, out) {
  out = out || [];
  fs.readdirSync(dir, { withFileTypes: true }).forEach(function (e) {
    const p = path.join(dir, e.name);
    if (e.isDirectory()) { if (e.name === 'kai') servedFiles(p, out); return; }
    if (/\.(php|js|html)$/.test(e.name)) out.push(p);
  });
  return out;
}
let dirty = [];
servedFiles(root).forEach(function (p) {
  const t = fs.readFileSync(p, 'utf8');
  if (/sk-[A-Za-z0-9]{12,}/.test(t)) dirty.push(p + ': sk-token');
  if (/Bearer\s+[A-Za-z0-9_\-\.]{20,}/.test(t)) dirty.push(p + ': bearer-token');
  const m = t.match(/meta_api_key['"]?\s*[:=]\s*['"](?!YOUR_META_API_KEY_HERE)([A-Za-z0-9_\-]{8,})['"]/);
  if (m) dirty.push(p + ': meta_api_key value');
});
ok(dirty.length === 0, dirty.length === 0 ? 'no API key in served files' : 'KEY LEAK: ' + dirty.join('; '));

// Private key file: outside the web root, valid PHP shape when present.
const tplPath = path.join(root, '..', 'dv_private', 'hackathon_meta_key.php');
if (!fs.existsSync(tplPath)) {
  console.log('SKIP template checks (private key file not configured at ../dv_private/)');
} else {
  const resolved = path.resolve(tplPath);
  const rooted = path.resolve(root);
  ok(resolved !== rooted && resolved.indexOf(rooted + path.sep) !== 0, 'private key file is outside the web root: ' + resolved);
  const tpl = fs.readFileSync(tplPath, 'utf8');
  ok(tpl.indexOf('<?php') === 0 && tpl.indexOf('return [') !== -1 && tpl.indexOf('meta_api_key') !== -1, 'template is a valid key config');
}

// Integration presence (skipped file-by-file when a sibling is absent).
function hasIn(file, needle, name) {
  const p = path.join(root, file);
  if (!fs.existsSync(p)) { console.log('SKIP ' + name + ' (' + file + ' absent)'); return; }
  ok(fs.readFileSync(p, 'utf8').indexOf(needle) !== -1, name);
}
hasIn('oracle_api.php', 'hackathon_ai_sentence($hackAvg, $hackLow)', 'draw calls AI once per new reading');
hasIn('oracle_api.php', "['ai' => ($current['ai'] ?? null)]", 'cooldown/current reuse stored sentence');
hasIn('index.php', 'Meta AI guidance - new hackathon feature', 'AI card label rendered');
hasIn('index.php', 'does not change any score', 'AI disclaimer rendered');

// HACKATHON diagnostics (new): private per-attempt log, outside root, whitelisted fields only.
ok(codeOnly.indexOf('hackathon_ai_debug.log') !== -1, 'diagnostic log filename present');
ok(/dirname\(__DIR__\)\s*\.\s*'\/dv_private\/hackathon_ai_debug\.log'/.test(codeOnly), 'diagnostic log lives outside the web root');
ok(/FILE_APPEND\s*\|\s*LOCK_EX/.test(codeOnly), 'diagnostic log appends safely');
var dbgFn = (codeOnly.match(/function hackathon_ai_debug_log[\s\S]*?\n\}/) || [''])[0];
ok(dbgFn !== '', 'debug writer function present');
['key_path', 'key_file_exists', 'key_file_readable', 'key_state', 'key_len', 'model', 'http_status', 'curl_error', 'api_error_200'].forEach(function (k) {
  ok(dbgFn.indexOf(k) !== -1, 'debug log allows field: ' + k);
});
ok(dbgFn.indexOf('allowed') !== -1, 'debug writer whitelists fields');
ok(!/\$cfg\['key'\]/.test(dbgFn), 'debug writer never sees the key value');
ok(/mb_substr\(\(string\)\$raw, 0, 200\)/.test(codeOnly), 'API error body truncated to 200 chars');
ok((codeOnly.match(/hackathon_ai_debug_log\(\$dbg\)/g) || []).length >= 6, 'every attempt outcome is logged');

// HACKATHON parser fix (new): no max_tokens starvation, multi-format extraction, shape logging, trim.
ok(codeOnly.indexOf('max_tokens') === -1, 'no max_tokens cap (reasoning budget starves content; test.js sends none)');
ok(codeOnly.indexOf('hackathon_ai_extract_text') !== -1, 'text extractor helper present');
ok(codeOnly.indexOf("$c0['text']") !== -1, 'parser falls back to choices[0].text');
ok(codeOnly.indexOf('hackathon_ai_shape') !== -1, 'shape logger helper present');
var shapeFn = (codeOnly.match(/function hackathon_ai_shape[\s\S]*?\n\}/) || [''])[0];
ok(shapeFn !== '', 'shape logger function present');
['resp_keys', 'choices_n', 'c0_type', 'c0_keys', 'finish_reason', 'msg_type', 'msg_keys', 'content_type', 'raw_500'].forEach(function (k) {
  ok(shapeFn.indexOf(k) !== -1, 'shape log records: ' + k);
});
ok(shapeFn.indexOf('0, 500') !== -1, 'raw body truncated to 500 chars');
ok(codeOnly.indexOf('preg_split') !== -1, 'sentence splitter present');
ok(codeOnly.indexOf('array_slice') !== -1 && codeOnly.indexOf(', 0, 30)') !== -1, 'sentence trimmed to 30 words or less');

console.log('----');
console.log('PASS: ' + pass + ' FAIL: ' + fail);
process.exit(fail ? 1 : 0);
