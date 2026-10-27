/* ============================================================================
 * HACKATHON DEMO WORK - NEW FILE (hackathon_demo.js)
 * Southrule.com hackathon: demo loop for the oracle reading page (index.php).
 *
 * Contents:
 *   1. ?demo=1 detection (client half; the server half is in oracle_api.php).
 *   2. Reversal-path remedy section, shown ONLY when the overall reading
 *      score is 5 or below (free actions only, never guaranteed, never
 *      monetized, no medical advice). Scores of 6+ show no remedy section.
 *   3. Saved wave chart: every reading (date + overall score) is stored per
 *      browser in localStorage, so guests need no login. 1 month / 1 year /
 *      2 year views, seeded with clearly labeled sample data so a fresh
 *      visitor sees a wave immediately. Member server history
 *      (bio_oracle_readings / Oracle Pulse) is NOT touched here.
 *   4. Two overlay toggles (Facebook likes per day, Monetization income per
 *      day) with a manual date + number entry form. No Facebook or API
 *      connection. Overlays are display-only lines and NEVER change the
 *      reading score or the reading wave.
 *   5. Per-oracle Remedy buttons (single scores below 5) each open a popup
 *      with ONLY that oracle's tradition (Burmese, Chinese, Jewish,
 *      Christian, Islamic, Hindu, or neutral reflection for Gypsy Cards).
 *      Overall scores of 5 or below keep the six-tab cross-cultural popup.
 *   6. Demo-mode wave views (?demo=1 only): the faint labeled sample line
 *      stays visible next to the visitor's own thick line in every view.
 *   7. Step 3 lifetime graph: ONE thick sine line for the visitor's own
 *      Chinese zodiac animal only. Separate from the Reading Wave, never
 *      merged into any score. Uses only each animal's overall score.
 * ========================================================================== */
(function () {
'use strict';

/* ============================ storage =================================== */
var LS_READINGS = 'dv_hack_wave_v1';
var LS_FB = 'dv_hack_ov_fb_v1';
var LS_MONEY = 'dv_hack_ov_money_v1';
var LS_VIEW = 'dv_hack_wave_view_v1';
var LS_FB_ON = 'dv_hack_ov_fb_on_v1';
var LS_MONEY_ON = 'dv_hack_ov_money_on_v1';
var MAX_READINGS = 2000;
var MAX_OVERLAY = 1000;

function lsGet(key, fallback) {
  try {
    if (typeof localStorage === 'undefined') return fallback;
    var raw = localStorage.getItem(key);
    if (!raw) return fallback;
    return JSON.parse(raw);
  } catch (_) { return fallback; }
}

function lsSet(key, val) {
  try {
    if (typeof localStorage !== 'undefined') localStorage.setItem(key, JSON.stringify(val));
  } catch (_) {}
}

function isoDay(ts) {
  var d = new Date(ts);
  return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
}

function isDateStr(s) {
  if (typeof s !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(s)) return false;
  return !isNaN(Date.parse(s + 'T00:00:00Z'));
}

function esc(s) {
  return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

/* ============================ 1. demo flag ============================== */
function demoFlagFromQuery(search) {
  if (typeof search !== 'string' || search === '') return false;
  var q = search.charAt(0) === '?' ? search.slice(1) : search;
  var parts = q.split('&');
  for (var i = 0; i < parts.length; i++) {
    var kv = parts[i].split('=');
    var k = '';
    var v = '';
    try { k = decodeURIComponent(kv[0] || ''); v = decodeURIComponent(kv[1] || ''); } catch (_) { continue; }
    if (k === 'demo' && v === '1') return true;
  }
  return false;
}

function isDemo() {
  if (typeof window === 'undefined' || !window.location) return false;
  try { return demoFlagFromQuery(window.location.search); } catch (_) { return false; }
}

/* ============================ 2. remedy ================================= */
function remedyShows(avg) {
  return typeof avg === 'number' && isFinite(avg) && avg <= 5;
}

function remedyHTML(avg) {
  if (!remedyShows(avg)) return '';
  var score = (Math.round(avg * 10) / 10).toFixed(1);
  var html = '';
  html += '<div class="hack-remedy" style="background:rgba(116,184,22,.07);border:1px solid rgba(116,184,22,.35);border-left:4px solid #74b816;border-radius:12px;padding:1rem 1.2rem;margin:1.1rem 0;text-align:left">';
  html += '<div style="font-size:.95rem;font-weight:800;color:#a9e34b;margin-bottom:.4rem"><i class="bi bi-arrow-counterclockwise me-1"></i>Reversal path &mdash; free actions that may improve the outlook</div>';
  html += '<p style="font-size:.82rem;color:#ccc;line-height:1.7;margin:0 0 .7rem">Your overall reading score is <strong style="color:#fff">' + score + ' / 10</strong>. The outlook is difficult right now. These traditional, completely free actions may help improve the outlook. Nothing here is guaranteed, nothing is for sale, and this is not medical advice.</p>';
  html += '<ul style="font-size:.82rem;color:#ccc;line-height:1.75;margin:0 0 .7rem;padding-left:1.2rem">';
  html += '<li><strong style="color:#fff">Prayer (tefillah)</strong> &mdash; a quiet prayer in your own words for clarity and calm.</li>';
  html += '<li><strong style="color:#fff">Charity (tzedakah)</strong> &mdash; a small, freely given act of generosity, of any size, with no expectation of return.</li>';
  html += '<li><strong style="color:#fff">Rest</strong> &mdash; sleep, quiet time, or a short break from the decision; return to it with a clearer mind.</li>';
  html += '<li><strong style="color:#fff">Reflection and repair (teshuvah)</strong> &mdash; honestly name what went wrong, make amends where you can, and choose one small better step.</li>';
  html += '<li><strong style="color:#fff">Burmese yadaya</strong> &mdash; a small traditional averting act, such as offering drinking water at a stupa or sweeping a quiet sacred place, done with a sincere wish for better fortune.</li>';
  html += '</ul>';
  html += '<p style="font-size:.72rem;color:var(--muted);line-height:1.6;margin:0">All of these actions are free &mdash; DharmaVera never sells remedies, blessings, or rank. This section is for reflection only and gives no medical advice; for health concerns please see a qualified professional.</p>';
  // HACKATHON (new): the overall score is not tied to one oracle, so it keeps the cross-cultural six-tab popup.
  html += '<div style="text-align:center;margin-top:.8rem"><button type="button" data-hack-roverall="1" onclick="HackathonDemo.openOverallRemedyModal()" style="background:rgba(116,184,22,.12);border:1px solid rgba(116,184,22,.5);border-radius:10px;color:#a9e34b;font-size:.8rem;font-weight:700;padding:.5rem 1.4rem;cursor:pointer">See remedies from all six traditions</button></div>';
  html += '</div>';
  return html;
}

/* ============================ 3. wave data ============================== */
var VIEW_DAYS = { '1m': 30, '1y': 365, '2y': 730 };

function inView(dstr, nowMs, days) {
  var t = Date.parse(dstr + 'T00:00:00Z');
  if (isNaN(t)) return false;
  return t >= nowMs - days * 86400000 - 86400000 && t <= nowMs + 86400000;
}

function filterByView(points, view, nowMs) {
  var days = VIEW_DAYS[view] || 30;
  return (points || []).filter(function (p) {
    return p && isDateStr(p.d) && inView(p.d, nowMs, days);
  });
}

function sortedByDay(points) {
  return (points || []).slice().sort(function (a, b) {
    if (a.d < b.d) return -1;
    if (a.d > b.d) return 1;
    return (a.t || 0) - (b.t || 0);
  });
}

function loadReadings() {
  var a = lsGet(LS_READINGS, []);
  if (!Array.isArray(a)) return [];
  return a.filter(function (e) {
    return e && isDateStr(e.d) && typeof e.s === 'number' && isFinite(e.s);
  });
}

/* HACKATHON: saves every reading (date + overall score) per browser, so
 * guests need no login. Member server history is untouched. Re-saving the
 * same server timestamp (page reload restoring a reading) is skipped. */
function saveReading(avg, tsMs) {
  if (typeof avg !== 'number' || !isFinite(avg)) return { ok: false };
  var t = (typeof tsMs === 'number' && isFinite(tsMs)) ? tsMs : Date.now();
  var list = loadReadings();
  if (list.length && list[list.length - 1].t === t) { refreshWave(); return { ok: true, duplicate: true }; }
  list.push({ d: isoDay(t), s: Math.round(avg * 10) / 10, t: t });
  while (list.length > MAX_READINGS) list.shift();
  lsSet(LS_READINGS, list);
  refreshWave();
  return { ok: true };
}

function mulberry32(seed) {
  return function () {
    seed |= 0; seed = (seed + 0x6D2B79F5) | 0;
    var t = Math.imul(seed ^ (seed >>> 15), 1 | seed);
    t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}

/* HACKATHON: deterministic, clearly labeled sample data - every point is
 * flagged sample:true and the chart legend + caption label it as examples
 * only, never as the visitor's readings. About 2 years, every 3 days. */
function sampleWave(nowMs) {
  var rand = mulberry32(20260704);
  var now = new Date(nowMs);
  var midnightUTC = Date.UTC(now.getUTCFullYear(), now.getUTCMonth(), now.getUTCDate());
  var pts = [];
  var N = 243;
  for (var k = N - 1; k >= 0; k--) {
    var ts = midnightUTC - k * 3 * 86400000;
    var wave = 5.5 + 2.2 * Math.sin((N - 1 - k) / 9) + (rand() - 0.5) * 1.6;
    var v = Math.max(1, Math.min(10, Math.round(wave * 10) / 10));
    pts.push({ d: isoDay(ts), s: v, sample: true });
  }
  return pts;
}

/* HACKATHON: pure function - NEVER mutates its inputs. Overlays only add
 * their own display columns; they never change reading values. Every saved
 * reading keeps its own chart slot (same-day repeats get " -2", " -3"
 * suffixes) so repeated demo draws all stay visible. */
/* HACKATHON demo views (new): with opts.demo set (?demo=1 page only), the
 * clearly labeled sample spread is ALSO returned next to the visitor's own
 * readings, so 1 month / 1 year / 2 years visibly differ. Without demo,
 * behavior is unchanged (samples only until 2 own readings). */
function buildWaveData(opts) {
  opts = opts || {};
  var nowMs = (typeof opts.nowMs === 'number' && isFinite(opts.nowMs)) ? opts.nowMs : Date.now();
  var view = opts.view || '1m';
  var demo = !!opts.demo;
  var days = VIEW_DAYS[view] || 30;
  var real = sortedByDay(filterByView(opts.readings || [], view, nowMs));
  var showSample = real.length < 2;
  var base = showSample ? sortedByDay(filterByView(sampleWave(nowMs), view, nowMs)) : real;
  var demoSamples = demo ? sortedByDay(filterByView(sampleWave(nowMs), view, nowMs)) : [];

  function overlayList(entries) {
    var out = [];
    (entries || []).forEach(function (e) {
      if (!e || !isDateStr(e.d) || typeof e.v !== 'number' || !isFinite(e.v)) return;
      if (!inView(e.d, nowMs, days)) return;
      out.push(e);
    });
    return out;
  }
  var fbList = opts.fbOn ? overlayList(opts.fbEntries) : [];
  var moneyList = opts.moneyOn ? overlayList(opts.moneyEntries) : [];

  var dayBaseCount = {};
  base.forEach(function (p) { dayBaseCount[p.d] = (dayBaseCount[p.d] || 0) + 1; });
  var daySet = {};
  base.forEach(function (p) { daySet[p.d] = true; });
  if (showSample) real.forEach(function (p) { daySet[p.d] = true; });
  fbList.forEach(function (e) { daySet[e.d] = true; });
  moneyList.forEach(function (e) { daySet[e.d] = true; });
  // HACKATHON demo views (new): in demo mode the sample spread joins the
  // labels so the faint comparison line can draw across the whole view.
  if (demo) demoSamples.forEach(function (p) { daySet[p.d] = true; });
  var daysSorted = Object.keys(daySet).sort();

  var labels = [];
  var dayFirstIdx = {};
  daysSorted.forEach(function (day) {
    var n = dayBaseCount[day] || 0;
    if (n === 0) {
      dayFirstIdx[day] = labels.length;
      labels.push(day);
      return;
    }
    for (var i = 0; i < n; i++) {
      if (i === 0) dayFirstIdx[day] = labels.length;
      labels.push(i === 0 ? day : day + ' -' + (i + 1));
    }
  });

  var baseByLabel = {};
  var baseCursor = {};
  base.forEach(function (p) {
    var k = baseCursor[p.d] || 0;
    baseCursor[p.d] = k + 1;
    baseByLabel[dayFirstIdx[p.d] + k] = p.s;
  });

  var realByDay = {};
  real.forEach(function (p) { realByDay[p.d] = p.s; });
  var sampleByDay = {};
  demoSamples.forEach(function (p) { sampleByDay[p.d] = p.s; });
  var fbByDay = {};
  fbList.forEach(function (e) { fbByDay[e.d] = e.v; });
  var moneyByDay = {};
  moneyList.forEach(function (e) { moneyByDay[e.d] = e.v; });

  var baseValues = [];
  var realValues = [];
  var fbValues = [];
  var moneyValues = [];
  var sampleValues = [];
  var ownDayValues = [];
  for (var j = 0; j < labels.length; j++) {
    baseValues.push((j in baseByLabel) ? baseByLabel[j] : null);
    var day = labels[j].length > 10 ? labels[j].slice(0, 10) : labels[j];
    var isFirst = (dayFirstIdx[day] === j);
    realValues.push((showSample && isFirst && (day in realByDay)) ? realByDay[day] : null);
    fbValues.push((opts.fbOn && isFirst && (day in fbByDay)) ? fbByDay[day] : null);
    moneyValues.push((opts.moneyOn && isFirst && (day in moneyByDay)) ? moneyByDay[day] : null);
    sampleValues.push((demo && isFirst && (day in sampleByDay)) ? sampleByDay[day] : null);
    ownDayValues.push((demo && isFirst && (day in realByDay)) ? realByDay[day] : null);
  }

  // HACKATHON demo views (new): the visitor's own column keeps every slot
  // when the base already is their readings (same-day repeats included).
  var ownValues = null;
  if (demo && real.length) ownValues = showSample ? ownDayValues : baseValues.slice();

  return {
    labels: labels,
    baseValues: baseValues,
    baseIsSample: showSample,
    baseLabel: showSample ? 'Sample wave - example data only (not your readings)' : 'Your readings - saved in this browser',
    realValues: showSample ? realValues : null,
    fbValues: opts.fbOn ? fbValues : null,
    moneyValues: opts.moneyOn ? moneyValues : null,
    realCount: real.length,
    totalSaved: (opts.readings || []).length,
    // HACKATHON demo views (new): separate faint-sample + thick-own columns,
    // demo mode only (both null outside demo).
    sampleValues: demo ? sampleValues : null,
    ownValues: ownValues
  };
}

/* ============================ 4. overlays =============================== */
function loadOverlay(which) {
  var a = lsGet(which === 'money' ? LS_MONEY : LS_FB, []);
  if (!Array.isArray(a)) return [];
  return a.filter(function (e) {
    return e && isDateStr(e.d) && typeof e.v === 'number' && isFinite(e.v);
  });
}

/* HACKATHON: manual entries only - no Facebook connection, no API.
 * Overlays are display-only and never change the reading score or wave. */
function addOverlayEntry(which, dateStr, value) {
  var key = which === 'money' ? LS_MONEY : LS_FB;
  if (which !== 'money' && which !== 'fb') return { ok: false, error: 'Unknown series.' };
  if (!isDateStr(dateStr)) return { ok: false, error: 'Please pick a valid date.' };
  var v = (typeof value === 'number') ? value : parseFloat(value);
  if (!isFinite(v) || v < 0) return { ok: false, error: 'Please enter a number of 0 or more.' };
  var list = loadOverlay(which);
  list.push({ d: dateStr, v: v });
  list.sort(function (a, b) { return a.d < b.d ? -1 : a.d > b.d ? 1 : 0; });
  while (list.length > MAX_OVERLAY) list.shift();
  lsSet(key, list);
  refreshWave();
  return { ok: true };
}

function removeOverlayEntry(which, index) {
  var key = which === 'money' ? LS_MONEY : LS_FB;
  if (which !== 'money' && which !== 'fb') return { ok: false };
  var list = loadOverlay(which);
  if (index < 0 || index >= list.length) return { ok: false };
  list.splice(index, 1);
  lsSet(key, list);
  refreshWave();
  return { ok: true };
}

/* ============================ chart + DOM =============================== */
var waveChart = null;

function el(id) {
  return (typeof document !== 'undefined') ? document.getElementById(id) : null;
}

function currentViewGet() {
  var v = lsGet(LS_VIEW, '1m');
  return VIEW_DAYS[v] ? v : '1m';
}

function setView(v) {
  if (!VIEW_DAYS[v]) return false;
  lsSet(LS_VIEW, v);
  refreshWave();
  return true;
}

function overlayListHTML(which) {
  var list = loadOverlay(which);
  if (!list.length) return '<div style="font-size:.75rem;color:var(--muted)">No manual entries yet.</div>';
  var name = which === 'money' ? 'income' : 'likes';
  var out = '';
  for (var i = 0; i < list.length; i++) {
    out += '<div style="display:flex;align-items:center;gap:.5rem;font-size:.78rem;color:#ccc;padding:.2rem 0;border-bottom:1px solid rgba(48,54,61,.5)">';
    out += '<span style="color:var(--muted)">' + esc(list[i].d) + '</span>';
    out += '<strong style="color:#fff">' + esc(String(list[i].v)) + '</strong>';
    out += '<span style="font-size:.7rem;color:var(--muted)">' + name + '/day</span>';
    out += '<button type="button" onclick="HackathonDemo.removeOverlayEntry(\u0027' + which + '\u0027,' + i + ')" title="Remove" style="margin-left:auto;background:none;border:1px solid var(--border);border-radius:6px;color:var(--muted);font-size:.72rem;padding:0 .45rem;cursor:pointer">x</button>';
    out += '</div>';
  }
  return out;
}

function renderOverlayLists() {
  var fbList = el('hackathon-ov-fb-list');
  var moneyList = el('hackathon-ov-money-list');
  if (fbList) fbList.innerHTML = overlayListHTML('fb');
  if (moneyList) moneyList.innerHTML = overlayListHTML('money');
}

function refreshWave() {
  if (typeof document === 'undefined') return;
  var canvas = el('hackathon-wave-canvas');
  if (!canvas) return;
  var view = currentViewGet();
  var fbBox = el('hackathon-ov-fb');
  var moneyBox = el('hackathon-ov-money');
  var fbOn = fbBox ? !!fbBox.checked : lsGet(LS_FB_ON, false) === true;
  var moneyOn = moneyBox ? !!moneyBox.checked : lsGet(LS_MONEY_ON, false) === true;
  // HACKATHON demo views (new): demo mode keeps the faint sample line; otherwise unchanged.
  var data = buildWaveData({
    readings: loadReadings(), view: view, nowMs: Date.now(), demo: isDemo(),
    fbOn: fbOn, fbEntries: loadOverlay('fb'),
    moneyOn: moneyOn, moneyEntries: loadOverlay('money')
  });

  var btns = document.querySelectorAll('[data-hack-view]');
  for (var i = 0; i < btns.length; i++) {
    var on = btns[i].getAttribute('data-hack-view') === view;
    if (btns[i].classList) btns[i].classList.toggle('active', on);
    btns[i].setAttribute('aria-pressed', on ? 'true' : 'false');
  }

  var cap = el('hackathon-wave-caption');
  if (cap) {
    var parts = [];
    parts.push('<strong>' + data.totalSaved + '</strong> reading' + (data.totalSaved === 1 ? '' : 's') + ' saved in this browser.');
    if (data.baseIsSample) parts.push('Showing <strong>sample data</strong> (examples only, not your readings) until you save 2 or more readings here.');
    // HACKATHON demo views (new): demo-only caption note; other wording unchanged.
    if (data.sampleValues && data.ownValues) parts.push('Demo mode: the faint line is sample data for comparison; the thick line is your own readings.');
    parts.push('Scores over time only - this chart does not predict the future or claim any pattern.');
    cap.innerHTML = parts.join(' ');
  }
  renderOverlayLists();

  if (typeof window === 'undefined' || typeof window.Chart === 'undefined') {
    var fb = el('hackathon-wave-fallback');
    if (fb) fb.style.display = 'block';
    return;
  }
  // HACKATHON demo views (new): demo mode draws the visitor's own thick line
  // plus a faint labeled sample line; outside demo this is identical data.
  var mainData = data.baseValues;
  var mainLabel = data.baseLabel;
  var mainIsSample = data.baseIsSample;
  var mainWidth = 2;
  if (data.ownValues) {
    mainData = data.ownValues;
    mainLabel = 'Your readings - saved in this browser';
    mainIsSample = false;
    mainWidth = 3;
  }
  var datasets = [{
    label: mainLabel,
    data: mainData,
    borderColor: mainIsSample ? '#8b949e' : '#58a6ff',
    backgroundColor: mainIsSample ? 'rgba(139,148,158,.10)' : 'rgba(88,166,255,.10)',
    borderWidth: mainWidth, tension: 0.35, spanGaps: true, pointRadius: 2,
    borderDash: mainIsSample ? [6, 4] : [],
    yAxisID: 'y'
  }];
  if (data.sampleValues && data.ownValues) {
    datasets.push({ label: 'Sample wave - example data (demo comparison)', data: data.sampleValues, borderColor: 'rgba(139,148,158,.55)', backgroundColor: 'rgba(139,148,158,.05)', borderWidth: 1, tension: 0.35, spanGaps: true, pointRadius: 1, borderDash: [6, 4], yAxisID: 'y' });
  }
  if (data.realValues && !data.ownValues) {
    datasets.push({ label: 'Your saved readings (this browser)', data: data.realValues, borderColor: '#58a6ff', backgroundColor: '#58a6ff', showLine: false, pointRadius: 5, pointHoverRadius: 7, yAxisID: 'y' });
  }
  if (data.fbValues) {
    datasets.push({ label: 'Facebook likes per day (manual entries)', data: data.fbValues, borderColor: '#4FC3F7', backgroundColor: 'rgba(79,195,247,.08)', borderWidth: 2, tension: 0.3, spanGaps: true, pointRadius: 3, yAxisID: 'y1' });
  }
  if (data.moneyValues) {
    datasets.push({ label: 'Monetization income per day (manual entries)', data: data.moneyValues, borderColor: '#74b816', backgroundColor: 'rgba(116,184,22,.08)', borderWidth: 2, tension: 0.3, spanGaps: true, pointRadius: 3, yAxisID: 'y2' });
  }
  if (waveChart) { try { waveChart.destroy(); } catch (_) {} waveChart = null; }
  waveChart = new window.Chart(canvas, {
    type: 'line',
    data: { labels: data.labels, datasets: datasets },
    options: {
      responsive: true, maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: { legend: { labels: { color: '#8b949e', boxWidth: 12, font: { size: 11 } } } },
      scales: {
        x: { ticks: { color: '#8b949e', font: { size: 10 }, maxTicksLimit: 8, maxRotation: 45, minRotation: 45 }, grid: { color: '#21262d' } },
        y: { min: 1, max: 10, ticks: { color: '#8b949e', font: { size: 10 } }, grid: { color: '#21262d' }, title: { display: true, text: 'Reading score', color: '#8b949e', font: { size: 10 } } },
        y1: { display: fbOn, position: 'right', beginAtZero: true, ticks: { color: '#4FC3F7', font: { size: 10 }, maxTicksLimit: 6 }, grid: { drawOnChartArea: false }, title: { display: true, text: 'Likes/day', color: '#4FC3F7', font: { size: 10 } } },
        y2: { display: moneyOn, position: 'right', beginAtZero: true, ticks: { color: '#74b816', font: { size: 10 }, maxTicksLimit: 6 }, grid: { drawOnChartArea: false }, title: { display: true, text: 'Income/day', color: '#74b816', font: { size: 10 } } }
      }
    }
  });
}

function initWaveCard() {
  if (typeof document === 'undefined') return;
  if (!document.getElementById('hackathon-wave')) return;
  var fbBox = el('hackathon-ov-fb');
  var moneyBox = el('hackathon-ov-money');
  if (fbBox) {
    fbBox.checked = lsGet(LS_FB_ON, false) === true;
    fbBox.addEventListener('change', function () { lsSet(LS_FB_ON, !!fbBox.checked); refreshWave(); });
  }
  if (moneyBox) {
    moneyBox.checked = lsGet(LS_MONEY_ON, false) === true;
    moneyBox.addEventListener('change', function () { lsSet(LS_MONEY_ON, !!moneyBox.checked); refreshWave(); });
  }
  var btns = document.querySelectorAll('[data-hack-view]');
  for (var i = 0; i < btns.length; i++) {
    (function (b) {
      b.addEventListener('click', function () { setView(b.getAttribute('data-hack-view')); });
    })(btns[i]);
  }
  var dateEl = el('hackathon-ov-date');
  if (dateEl && !dateEl.value) {
    var now = new Date();
    dateEl.value = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');
  }
  var addBtn = el('hackathon-ov-add');
  if (addBtn) {
    addBtn.addEventListener('click', function () {
      var series = el('hackathon-ov-series') ? el('hackathon-ov-series').value : 'fb';
      var d = dateEl ? dateEl.value : '';
      var raw = el('hackathon-ov-value') ? el('hackathon-ov-value').value : '';
      var msg = el('hackathon-ov-msg');
      var res = addOverlayEntry(series === 'money' ? 'money' : 'fb', d, raw);
      if (msg) {
        msg.style.display = 'block';
        msg.style.color = res.ok ? '#5cb85c' : '#e05252';
        msg.textContent = res.ok ? 'Saved.' : (res.error || 'Could not save.');
        if (res.ok) {
          var valEl = el('hackathon-ov-value');
          if (valEl) valEl.value = '';
          setTimeout(function () { msg.style.display = 'none'; }, 2500);
        }
      }
    });
  }
  refreshWave();
}

/* ============ per-oracle remedies + modal (HACKATHON, new) ================
 * Small Remedy buttons next to single oracle scores below 5 open ONE popup
 * modal (never a new page) with six tradition tabs. Free actions only, no
 * payment links, no required amounts, no medical advice. Opening or closing
 * the popup never changes any score. */
var ORACLE_DISPLAY_NAMES = {
  mhb: 'Mahabote', kauCim: 'Kau Cim', tehillim: 'Tehillim',
  bible: 'Bible', quran: 'Quran', ram: 'Ram Shalaka', tarot: 'Gypsy Cards'
};

function perOracleButtonHTML(key, score) {
  if (!ORACLE_DISPLAY_NAMES[key]) return '';
  if (typeof score !== 'number' || !isFinite(score) || score >= 5) return '';
  return '<button type="button" class="hack-remedy-btn" onclick="HackathonDemo.openRemedyModal(\'' + key + '\',' + score + ')" style="margin-left:.5rem;background:rgba(116,184,22,.12);border:1px solid rgba(116,184,22,.5);border-radius:20px;color:#a9e34b;font-size:.7rem;font-weight:700;padding:.15rem .7rem;cursor:pointer;white-space:nowrap">Remedy</button>';
}

function remedyModalTabs() {
  return [
    { id: 'jewish', label: 'Jewish', intro: 'Free traditional actions - what to do and how.', items: [
      { t: 'Tzedakah - give charity freely', d: 'Set aside any amount you choose for someone in need - traditionally in multiples of 18 for Chai (life), but any amount, or none at all, is acceptable. There is no required payment.' },
      { t: 'Tikkun and Hatavat Chalom - repair and favorable reinterpretation', d: 'Reflect on what you can repair (tikkun). After a troubling dream, practice Hatavat Chalom: deliberately reinterpret the dream in a favorable light together with trusted friends.' },
      { t: 'Tehillim recitation for reflection', d: 'Read or recite a psalm slowly for calm and reflection. Ask your own rabbi for exact traditional wording.' }
    ] },
    { id: 'burmese', label: 'Burmese', intro: 'Free traditional actions - what to do and how.', items: [
      { t: 'Yadaya merit-making', d: 'Perform a small traditional yadaya - for example offering drinking water or sweeping a quiet sacred place - done lawfully, humanely, and with a sincere wish.' },
      { t: 'Charitable good deeds', d: 'Offer food, help a neighbor, or support someone in need. Keep every act lawful and humane.' },
      { t: 'Precepts and quiet reflection', d: 'Keep the basic precepts for a day and sit quietly for a few minutes, extending goodwill to all beings.' }
    ] },
    { id: 'hindu', label: 'Hindu', intro: 'Free traditional actions - what to do and how.', items: [
      { t: 'Graha Shanti', d: 'Chant or listen to a short planetary peace prayer at home in the morning for calm and steadiness.' },
      { t: 'Parihara', d: 'Perform a simple traditional remedy such as lighting a lamp or offering water and flowers at your home altar.' }
    ] },
    { id: 'chinese', label: 'Chinese', intro: 'Free traditional actions - what to do and how.', items: [
      { t: 'Hua Jie', d: 'Resolve and dissolve heavy energy: tidy your entrance, clear clutter, and do one quiet good deed.' },
      { t: 'An Tai Sui', d: 'Pay quiet respect to Tai Sui at a temple or home altar and ask for a smooth year; no offering purchase is required.' }
    ] },
    { id: 'islamic', label: 'Islamic', intro: 'Free traditional actions - what to do and how.', items: [
      { t: 'Du\'a', d: 'Raise your hands and ask in your own words for guidance and relief.' },
      { t: 'Istighfar', d: 'Quietly repeat Astaghfirullah with sincere reflection, morning and evening.' },
      { t: 'Sadaqah', d: 'Give any small voluntary charity - food, help, or a kind word - with no fixed amount.' }
    ] },
    { id: 'christian', label: 'Christian', intro: 'Free traditional actions - what to do and how.', items: [
      { t: 'Prayer and blessings', d: 'Pray in your own words and speak a blessing over others.' },
      { t: 'Repentance', d: 'Name your faults honestly, seek forgiveness, and make amends where you can.' },
      { t: 'Fasting and charitable giving', d: 'Keep a simple fast and share food or give to someone in need.' }
    ] }
  ];
}

var REMEDY_DISCLAIMER = 'Traditional practices for reflection. They may improve the outlook but are never guaranteed and never monetized. This is not medical advice. Closing this popup does not change any score.';

/* ===== single-tradition popups (HACKATHON, new) ============================
 * Per-oracle Remedy buttons open a popup showing ONLY that oracle's matching
 * tradition - no tabs, no other religion content. Each action states what it
 * is, why tradition does it, how to do it step by step, and cost (always
 * free or any amount the user chooses). No payment links, no required
 * amounts, no medical advice. The six-tab modal stays for overall scores. */
var SINGLE_TRADITIONS = {
  tehillim: { oracle: 'Tehillim', heading: 'Jewish remedies',
    intro: 'Detailed traditional actions - what each is, why tradition does it, how to do it, and cost. All free.',
    guide: 'For exact traditional wording, ask your own rabbi.',
    actions: [
      { t: 'Tzedakah',
        what: 'Tzedakah means righteousness expressed as charity: setting aside money or goods for someone in need.',
        why: 'Tradition teaches that generosity repairs the world and softens hardship; it is a duty and a merit, never a purchase.',
        how: ['Choose a person in need or a charity you trust.', 'Set aside any amount you choose - traditionally in multiples of 18 for Chai (life).', 'Give it quietly, without expecting anything back.'],
        cost: 'Free to choose: any amount, or none at all, is acceptable. There is no required payment.' },
      { t: 'Tikkun',
        what: 'Tikkun means repair: putting right what you broke, in yourself and toward others.',
        why: 'Tradition holds that sincere repair restores relationships and the self.',
        how: ['Reflect on what you can repair.', 'Make amends where you have wronged someone - apologize plainly.', 'Do one concrete good deed for someone else today.'],
        cost: 'Free.' },
      { t: 'Hatavat Chalom',
        what: 'Hatavat Chalom is the traditional favorable reinterpretation of a troubling dream or omen.',
        why: 'Tradition teaches that a dream follows its interpretation, so the community deliberately reframes it toward good.',
        how: ['Gather three trusted friends.', 'Tell them the troubling dream or sign.', 'Have them respond that it was good and may it be good.', 'Recite verses for overturning, redeeming, and peace together.', 'Deliberately reframe the sign in a favorable light together.'],
        cost: 'Free.',
        note: 'Ask your own rabbi for exact siddur wording.' },
      { t: 'Tehillim recitation for reflection',
        what: 'Reading or reciting a psalm slowly for calm and reflection.',
        why: 'The psalms give voice to worry, hope, and trust; tradition uses them for comfort.',
        how: ['Choose a psalm.', 'Read or recite it slowly, pausing on lines that speak to you.', 'Sit quietly for a minute afterward.'],
        cost: 'Free, with no claim that recitation alone changes an outcome.' }
    ] },
  mhb: { oracle: 'Mahabote', heading: 'Burmese remedies',
    intro: 'Detailed traditional actions - what each is, why tradition does it, how to do it, and cost. All free.',
    guide: 'For guidance on traditional practice, ask your own monk.',
    foot: 'In Buddhist teaching, good deeds are moral actions in themselves, not a guaranteed reversal.',
    actions: [
      { t: 'Yadaya merit-making',
        what: 'Yadaya is a deliberate good deed done to delay or neutralize worry, in traditional terms.',
        why: 'It turns anxiety into wholesome action and merit.',
        how: ['Choose a simple deed: give food, flowers, or money to monks or to people in need.', 'Do it mindfully, with a sincere wish.', 'Dedicate the merit to relief from the worry.'],
        cost: 'Free, or any amount you choose.',
        note: 'A moral action, not magic that bypasses karma.' },
      { t: 'Dana and kindness',
        what: 'Dana is generous giving: alms, help, and support.',
        why: 'Giving loosens greed and builds merit and goodwill.',
        how: ['Offer alms in the morning, help a stranger, or support a monastery or charity of your choice.', 'Give what you can, cheerfully.', 'Rejoice in the giving afterward.'],
        cost: 'Any amount you choose.' },
      { t: 'Mindful pause and good conduct',
        what: 'A pause for reflection, kind speech, and one steady habit.',
        why: 'Calm conduct prevents hasty decisions and sustains motivation.',
        how: ['Sit quietly and reflect on the worry.', 'Speak kindly through the day.', 'Avoid one hasty decision that day.', 'Keep one consistent habit, such as meditation or prayer.'],
        cost: 'Free.' }
    ] },
  kauCim: { oracle: 'Kau Cim', heading: 'Chinese remedies',
    intro: 'Detailed traditional actions - what each is, why tradition does it, how to do it, and cost. All free.',
    guide: 'For temple practice, follow the guidance of the temple attendant.',
    actions: [
      { t: 'Hua Jie',
        what: 'Hua Jie means resolving: dissolving tension through clarity and good deeds.',
        why: 'Naming the worry and acting kindly dissolves stuck, heavy energy in practical terms.',
        how: ['Pause.', 'Write down the worry clearly.', 'Reflect on what part you can actually change.', 'Do a charitable act or help someone else.'],
        cost: 'Free.' },
      { t: 'An Tai Sui and Pai Tai Sui',
        what: 'Temple prayer for the year, directed to the Tai Sui deity.',
        why: 'Tradition seeks a smooth year - health, family safety, and protection - under Tai Sui.',
        how: ['Go to a temple, or set up quietly at home with incense, candles, fruits, and flowers.', 'Write your name and birthdate on paper for the Tai Sui deity.', 'Pray sincerely for health, family safety, and protection.', 'Thank at the end of the year.', 'Follow the guidance of the temple attendant.'],
        cost: 'Free; use only what you already have.' },
      { t: 'Protective and calming habits',
        what: 'Small traditional comforts and cautions for tense days.',
        why: 'They keep the mind calm and decisions safe.',
        how: ['Wear red for comfort in tradition.', 'Keep the home tidy.', 'Avoid a major risky decision on a tense day.', 'Keep a quiet period, Duo Chun - staying calm and quiet for an hour or two at a seasonal turning point, if that tradition fits you.'],
        cost: 'Free.' }
    ] },
  bible: { oracle: 'Bible', heading: 'Christian remedies',
    intro: 'Detailed traditional actions - what each is, why tradition does it, how to do it, and cost. All free.',
    guide: 'For confession, blessing, or exact wording, ask your own priest or pastor.',
    actions: [
      { t: 'Prayer',
        what: 'Prayer in your own words and with the Lord\'s Prayer.',
        why: 'Tradition prays for forgiveness, protection from temptation, and calm.',
        how: ['Pray in your own words.', 'Pray the Lord\'s Prayer slowly.', 'Pray with a good conscience.'],
        cost: 'Free.',
        note: 'Not as a formula for a guaranteed outcome.' },
      { t: 'Repentance and reconciliation',
        what: 'Repentance means turning back through apology and amends.',
        why: 'Repairing wrongs restores peace with God and people.',
        how: ['Reflect on what needs repair.', 'Apologise where you have wronged someone.', 'Make amends where possible.', 'Seek blessing or confession with a priest or pastor if that is your practice.'],
        cost: 'Free.' },
      { t: 'Fasting, prayer, and almsgiving',
        what: 'The three traditional forms of conversion: in relation to yourself, to God, and to others.',
        why: 'Together they express turning toward God and neighbor.',
        how: ['Fast simply and safely.', 'Pray regularly.', 'Give generously to the poor in proportion to your means.'],
        cost: 'Free; giving is in proportion to your means.',
        note: 'No medical fasting advice - eat normally if fasting would harm health.' },
      { t: 'Blessing and protection',
        what: 'Asking for blessing and protection from God, with scripture and kindness.',
        why: 'Blessing entrusts the worry to God; kindness lives it out.',
        how: ['Ask for blessing and protection from God.', 'Read a psalm or gospel passage slowly for reflection.', 'Do a concrete act of kindness that day.'],
        cost: 'Free.' }
    ] },
  quran: { oracle: 'Quran', heading: 'Islamic remedies',
    intro: 'Detailed traditional actions - what each is, why tradition does it, how to do it, and cost. All free.',
    guide: 'For guidance on practice, ask your own imam.',
    actions: [
      { t: 'Du\'a',
        what: 'Supplication: asking sincerely for guidance and relief.',
        why: 'Tradition calls it the essence of worship and a source of calm and ease.',
        how: ['Pray sincerely in your own words.', 'Add traditional supplications for protection, calm, and ease.', 'Ask especially in regular prayer, and in the late night if you wish.'],
        cost: 'Free.' },
      { t: 'Istighfar',
        what: 'Istighfar is seeking forgiveness.',
        why: 'It cleanses the heart and opens the way to relief.',
        how: ['Repeat seeking forgiveness calmly.', 'Reflect on any wrong done to others.', 'Restore their rights where possible.', 'Turn away from the harmful habit.'],
        cost: 'Free.' },
      { t: 'Sadaqah',
        what: 'Sadaqah is voluntary charity given sincerely.',
        why: 'It is a means of seeking mercy - not a transaction that guarantees protection.',
        how: ['Choose a person or charity.', 'Give with sincerity.', 'Expect no return.'],
        cost: 'Any amount you choose.',
        note: 'Health problems still need medical help; doctors are also a means of healing in tradition.' },
      { t: 'Ruqyah',
        what: 'Ruqyah for reflection: slow recitation of Al-Fatiha, Ayat al-Kursi, Al-Ikhlas, Al-Falaq, and An-Nas.',
        why: 'Traditionally recited for protection and calm.',
        how: ['Sit quietly.', 'Recite each passage slowly.', 'Reflect on their meanings.'],
        cost: 'Free, with no claim that recitation alone cures illness.' }
    ] },
  ram: { oracle: 'Ram Shalaka', heading: 'Hindu remedies',
    intro: 'Detailed traditional actions - what each is, why tradition does it, how to do it, and cost. All free.',
    guide: 'For a personalised plan, ask a purohit.',
    actions: [
      { t: 'Graha Shanti',
        what: 'Graha Shanti means planetary peace: rites that calm the mind about planetary influences.',
        why: 'Tradition does it to steady focus and invite calm.',
        how: ['Light a lamp in the morning.', 'Offer flowers.', 'Recite a mantra slowly for calm and focus.'],
        cost: 'Free.',
        note: 'Ask a purohit for a personalised plan if you want one. No claim of a specific result.' },
      { t: 'Parihara charity and seva',
        what: 'Parihara remedies plus seva, selfless service.',
        why: 'Serving others without expectation is itself the remedy in tradition.',
        how: ['Give food, clothes, or help to people in need.', 'Feed the poor, or help elderly people or labourers.', 'Do the service without expecting a return.'],
        cost: 'Free, or whatever you can give.' },
      { t: 'Fasting and discipline',
        what: 'A simple fast or a disciplined day: satvik food, honesty, patience, regular prayer.',
        why: 'Discipline steadies the mind.',
        how: ['Eat simply (satvik food).', 'Speak honestly and stay patient.', 'Pray regularly through the day.'],
        cost: 'Free.',
        note: 'No medical fasting advice - eat normally if fasting would harm health.' }
    ] },
  tarot: { oracle: 'Gypsy Cards', heading: 'Neutral reflection',
    intro: 'Detailed reflection actions - what each is, why, how, and cost. All free.',
    folkNote: 'Gypsy Cards are folk cards, not a faith tradition, so this popup offers neutral reflection actions. If you want a tradition-specific remedy, use the overall remedy instead.',
    actions: [
      { t: 'Pause and write',
        what: 'A short written reflection.',
        why: 'Writing separates what you can control from what you cannot.',
        how: ['Write the worry in one sentence.', 'Write one part you can control and one part you cannot.', 'Choose one small next step for the controllable part.'],
        cost: 'Free.' },
      { t: 'Charity and rest',
        what: 'Small kindness plus proper rest.',
        why: 'Helping steadies mood; rest prevents hasty decisions.',
        how: ['Give help or a small gift to someone in need if you can.', 'Rest properly.', 'Avoid a hasty decision while upset.'],
        cost: 'Free.' },
      { t: 'Talk to a trusted person',
        what: 'Sharing the worry with someone steady.',
        why: 'A calmer reading of the situation brings perspective.',
        how: ['Share the worry with a trusted friend or counsellor.', 'Listen to their calmer reading.', 'Note: if you want a tradition-specific remedy, the overall remedy popup offers all six traditions to choose from.'],
        cost: 'Free.' }
    ] }
};

function singleTraditionContent(key) {
  return SINGLE_TRADITIONS[key] || null;
}

function singleTraditionModalHTML(key, score) {
  var c = SINGLE_TRADITIONS[key];
  if (!c) return '';
  var s = (typeof score === 'number' && isFinite(score)) ? score : '?';
  var title = 'Remedies for reflection - ' + c.oracle + ' (score ' + s + ')';
  var h = '';
  h += '<div id="hackathon-remedy-overlay" style="position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:9990;display:flex;align-items:center;justify-content:center;padding:1rem;box-sizing:border-box" onclick="if(event.target===this)HackathonDemo.closeRemedyModal()">';
  h += '<div role="dialog" aria-modal="true" aria-label="' + esc(title) + '" style="background:#161b22;border:1px solid #30363d;border-radius:14px;max-width:640px;width:100%;max-height:90vh;overflow-y:auto;padding:1.2rem 1.3rem;position:relative;box-sizing:border-box">';
  h += '<button type="button" data-hack-rclose="1" onclick="HackathonDemo.closeRemedyModal()" aria-label="Close" style="position:absolute;top:.6rem;right:.7rem;background:#21262d;border:1px solid #30363d;border-radius:8px;color:#e6edf3;font-size:1rem;line-height:1;padding:.3rem .65rem;cursor:pointer">x</button>';
  h += '<div style="font-size:1rem;font-weight:800;color:#fff;margin:0 2.5rem .5rem 0">' + esc(title) + '</div>';
  h += '<div style="font-size:.75rem;color:#a9e34b;background:rgba(116,184,22,.08);border:1px solid rgba(116,184,22,.35);border-radius:8px;padding:.5rem .7rem;line-height:1.55;margin-bottom:.8rem">' + esc(REMEDY_DISCLAIMER) + '</div>';
  h += '<div style="font-size:.9rem;font-weight:800;color:#fff;margin-bottom:.2rem">' + esc(c.heading) + '</div>';
  h += '<div style="font-size:.75rem;color:#8b949e;margin-bottom:.6rem">' + esc(c.intro) + '</div>';
  if (c.folkNote) {
    h += '<div style="font-size:.8rem;color:#ccc;background:rgba(88,166,255,.07);border:1px solid rgba(88,166,255,.35);border-radius:8px;padding:.55rem .75rem;line-height:1.6;margin-bottom:.8rem">' + esc(c.folkNote) + '</div>';
    h += '<div style="text-align:center;margin-bottom:.9rem"><button type="button" data-hack-roverall="1" onclick="HackathonDemo.openOverallRemedyModal()" style="background:rgba(88,166,255,.12);border:1px solid rgba(88,166,255,.5);border-radius:10px;color:#58a6ff;font-size:.8rem;font-weight:700;padding:.5rem 1.4rem;cursor:pointer">Open all-traditions remedy</button></div>';
  }
  c.actions.forEach(function (a) {
    h += '<div style="margin-bottom:.9rem;border-top:1px solid #21262d;padding-top:.6rem">';
    h += '<div style="font-size:.88rem;font-weight:800;color:#fff;margin-bottom:.3rem">' + esc(a.t) + '</div>';
    h += '<div style="font-size:.8rem;color:#ccc;line-height:1.6;margin-bottom:.25rem"><strong style="color:#a9e34b">What it is:</strong> ' + esc(a.what) + '</div>';
    h += '<div style="font-size:.8rem;color:#ccc;line-height:1.6;margin-bottom:.25rem"><strong style="color:#a9e34b">Why tradition does it:</strong> ' + esc(a.why) + '</div>';
    h += '<div style="font-size:.8rem;color:#ccc;line-height:1.6;margin-bottom:.25rem"><strong style="color:#a9e34b">How to do it:</strong><ol style="margin:.25rem 0 0;padding-left:1.3rem">';
    a.how.forEach(function (step) { h += '<li style="margin-bottom:.15rem">' + esc(step) + '</li>'; });
    h += '</ol></div>';
    h += '<div style="font-size:.8rem;color:#ccc;line-height:1.6"><strong style="color:#a9e34b">Cost:</strong> ' + esc(a.cost) + '</div>';
    if (a.note) h += '<div style="font-size:.76rem;color:#8b949e;line-height:1.6;margin-top:.25rem">' + esc(a.note) + '</div>';
    h += '</div>';
  });
  if (c.guide) h += '<div style="font-size:.78rem;color:#ccc;background:rgba(255,255,255,.04);border:1px solid #30363d;border-radius:8px;padding:.5rem .7rem;line-height:1.6;margin-bottom:.8rem">' + esc(c.guide) + '</div>';
  if (c.foot) h += '<div style="font-size:.78rem;color:#ccc;line-height:1.6;margin-bottom:.8rem">' + esc(c.foot) + '</div>';
  h += '<div style="font-size:.75rem;color:#a9e34b;background:rgba(116,184,22,.08);border:1px solid rgba(116,184,22,.35);border-radius:8px;padding:.5rem .7rem;line-height:1.55;margin-top:.8rem">' + esc(REMEDY_DISCLAIMER) + '</div>';
  h += '<div style="text-align:center;margin-top:.9rem"><button type="button" data-hack-rclose="2" onclick="HackathonDemo.closeRemedyModal()" style="background:rgba(88,166,255,.12);border:1px solid rgba(88,166,255,.5);border-radius:10px;color:#58a6ff;font-size:.85rem;font-weight:700;padding:.5rem 2rem;cursor:pointer">Close</button></div>';
  h += '</div></div>';
  return h;
}

function remedyModalHTML(oracleName, score) {
  var tabs = remedyModalTabs();
  var title = 'Remedies for reflection';
  if (oracleName) title += ' - ' + oracleName;
  if (typeof score === 'number' && isFinite(score)) title += ' (score ' + score + ')';
  var h = '';
  h += '<div id="hackathon-remedy-overlay" style="position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:9990;display:flex;align-items:center;justify-content:center;padding:1rem;box-sizing:border-box" onclick="if(event.target===this)HackathonDemo.closeRemedyModal()">';
  h += '<div role="dialog" aria-modal="true" aria-label="' + esc(title) + '" style="background:#161b22;border:1px solid #30363d;border-radius:14px;max-width:640px;width:100%;max-height:90vh;overflow-y:auto;padding:1.2rem 1.3rem;position:relative;box-sizing:border-box">';
  h += '<button type="button" data-hack-rclose="1" onclick="HackathonDemo.closeRemedyModal()" aria-label="Close" style="position:absolute;top:.6rem;right:.7rem;background:#21262d;border:1px solid #30363d;border-radius:8px;color:#e6edf3;font-size:1rem;line-height:1;padding:.3rem .65rem;cursor:pointer">x</button>';
  h += '<div style="font-size:1rem;font-weight:800;color:#fff;margin:0 2.5rem .5rem 0">' + esc(title) + '</div>';
  h += '<div style="font-size:.75rem;color:#a9e34b;background:rgba(116,184,22,.08);border:1px solid rgba(116,184,22,.35);border-radius:8px;padding:.5rem .7rem;line-height:1.55;margin-bottom:.8rem">' + esc(REMEDY_DISCLAIMER) + '</div>';
  h += '<div role="tablist" aria-label="Traditions" style="display:flex;flex-wrap:wrap;gap:.4rem;margin-bottom:.8rem">';
  tabs.forEach(function (t, i) {
    var on = i === 0;
    h += '<button type="button" role="tab" data-hack-rtab="' + t.id + '" aria-selected="' + (on ? 'true' : 'false') + '" onclick="HackathonDemo.showRemedyTab(\'' + t.id + '\')" style="border-radius:8px;font-size:.78rem;font-weight:700;padding:.4rem .8rem;cursor:pointer;border:1px solid ' + (on ? '#58a6ff' : '#30363d') + ';color:' + (on ? '#fff' : '#ccc') + ';background:' + (on ? 'rgba(88,166,255,.15)' : 'rgba(255,255,255,.04)') + '">' + esc(t.label) + '</button>';
  });
  h += '</div>';
  tabs.forEach(function (t, i) {
    h += '<div role="tabpanel" data-hack-rpanel="' + t.id + '" style="display:' + (i === 0 ? 'block' : 'none') + '">';
    h += '<div style="font-size:.75rem;color:#8b949e;margin-bottom:.5rem">' + esc(t.intro) + '</div>';
    t.items.forEach(function (it) {
      h += '<div style="margin-bottom:.65rem"><div style="font-size:.85rem;font-weight:700;color:#fff">' + esc(it.t) + '</div><div style="font-size:.8rem;color:#ccc;line-height:1.6">' + esc(it.d) + '</div></div>';
    });
    h += '</div>';
  });
  h += '<div style="font-size:.75rem;color:#a9e34b;background:rgba(116,184,22,.08);border:1px solid rgba(116,184,22,.35);border-radius:8px;padding:.5rem .7rem;line-height:1.55;margin-top:.8rem">' + esc(REMEDY_DISCLAIMER) + '</div>';
  h += '<div style="text-align:center;margin-top:.9rem"><button type="button" data-hack-rclose="2" onclick="HackathonDemo.closeRemedyModal()" style="background:rgba(88,166,255,.12);border:1px solid rgba(88,166,255,.5);border-radius:10px;color:#58a6ff;font-size:.85rem;font-weight:700;padding:.5rem 2rem;cursor:pointer">Close</button></div>';
  h += '</div></div>';
  return h;
}

function showRemedyTab(id) {
  if (typeof document === 'undefined') return false;
  var root = document.getElementById('hackathon-remedy-modal-root');
  if (!root) return false;
  var tabs = root.querySelectorAll('[data-hack-rtab]');
  var found = false;
  for (var i = 0; i < tabs.length; i++) {
    var on = tabs[i].getAttribute('data-hack-rtab') === id;
    if (on) found = true;
    tabs[i].setAttribute('aria-selected', on ? 'true' : 'false');
    tabs[i].style.borderColor = on ? '#58a6ff' : '#30363d';
    tabs[i].style.color = on ? '#fff' : '#ccc';
    tabs[i].style.background = on ? 'rgba(88,166,255,.15)' : 'rgba(255,255,255,.04)';
  }
  if (!found) return false;
  var panels = root.querySelectorAll('[data-hack-rpanel]');
  for (var j = 0; j < panels.length; j++) {
    panels[j].style.display = (panels[j].getAttribute('data-hack-rpanel') === id) ? 'block' : 'none';
  }
  return true;
}

var _remedyEscBound = false;

function _remedyRoot() {
  if (typeof document === 'undefined') return null;
  var root = document.getElementById('hackathon-remedy-modal-root');
  if (!root) {
    root = document.createElement('div');
    root.id = 'hackathon-remedy-modal-root';
    document.body.appendChild(root);
  }
  return root;
}

function _remedyShow() {
  try { document.body.style.overflow = 'hidden'; } catch (_) {}
  if (!_remedyEscBound) {
    _remedyEscBound = true;
    document.addEventListener('keydown', function (ev) { if (ev && ev.key === 'Escape') closeRemedyModal(); });
  }
}

/* HACKATHON (new): per-oracle buttons open ONLY that oracle's tradition. */
function openRemedyModal(key, score) {
  var root = _remedyRoot();
  if (!root) return false;
  if (!SINGLE_TRADITIONS[key]) return openOverallRemedyModal();
  root.innerHTML = singleTraditionModalHTML(key, score);
  root.style.display = 'block';
  _remedyShow();
  return true;
}

/* HACKATHON (new): the overall score is not tied to one oracle, so overall
 * scores of 5 or below keep the cross-cultural six-tab popup. */
function openOverallRemedyModal() {
  var root = _remedyRoot();
  if (!root) return false;
  root.innerHTML = remedyModalHTML('', undefined);
  root.style.display = 'block';
  _remedyShow();
  showRemedyTab('jewish');
  return true;
}

function closeRemedyModal() {
  if (typeof document === 'undefined') return false;
  var root = document.getElementById('hackathon-remedy-modal-root');
  if (root) root.style.display = 'none';
  try { document.body.style.overflow = ''; } catch (_) {}
  return true;
}

/* ============ lifetime graph (HACKATHON Step 3, new) ======================
 * Separate lifetime graph: ONE thick sine line for the visitor's own Chinese
 * zodiac animal only. NOT an overlay on the Reading Wave and NEVER merged
 * into any reading score. Uses only the overall score per animal from the
 * site's existing trait table (window.DV_CZ_OVERALL, emitted by index.php
 * with the same averaging as the trait display). No trait breakdown, no
 * trait numbers, no ranking, no personality text. */

// Site lookup, mirrored exactly: (year - 1900) % 12, 0=Rat..11=Pig (index.php).
var ZODIAC_ANIMALS = ['Rat', 'Ox', 'Tiger', 'Rabbit', 'Dragon', 'Snake', 'Horse', 'Goat', 'Monkey', 'Rooster', 'Dog', 'Pig'];

function zodiacIndexForYear(year) {
  if (typeof year !== 'number' || !isFinite(year)) return -1;
  year = Math.floor(year);
  return (((year - 1900) % 12) + 12) % 12;
}

function zodiacOverallScores() {
  try {
    var a = (typeof window !== 'undefined') ? window.DV_CZ_OVERALL : null;
    if (!Array.isArray(a) || a.length !== 12) return null;
    for (var i = 0; i < 12; i++) { if (typeof a[i] !== 'number' || !isFinite(a[i])) return null; }
    return a;
  } catch (_) { return null; }
}

function exactAgeYears(birthISO, nowMs) {
  if (!isDateStr(birthISO)) return NaN;
  var t = Date.parse(birthISO + 'T00:00:00Z');
  if (isNaN(t) || t > nowMs) return NaN;
  return (nowMs - t) / (365.25 * 86400000);
}

function zodiacSineValue(center, index, tYears) {
  var phase = index * Math.PI / 6;
  var v = center + 1.5 * Math.sin(2 * Math.PI * tYears / 12 + phase);
  return Math.max(1, Math.min(10, Math.round(v * 100) / 100));
}

function zodiacPeaksTroughs(index) {
  var peaks = [], troughs = [];
  for (var k = -1; k <= 8; k++) {
    var p = 3 - index + 12 * k, q = 9 - index + 12 * k;
    if (p >= 0 && p <= 90) peaks.push(p);
    if (q >= 0 && q <= 90) troughs.push(q);
  }
  return { peaks: peaks, troughs: troughs };
}

function zodiacNextExtreme(index, ageNow) {
  var pt = zodiacPeaksTroughs(index);
  var np = null, nt = null, i;
  for (i = 0; i < pt.peaks.length; i++) { if (pt.peaks[i] >= ageNow) { np = pt.peaks[i]; break; } }
  for (i = 0; i < pt.troughs.length; i++) { if (pt.troughs[i] >= ageNow) { nt = pt.troughs[i]; break; } }
  return { nextPeak: np, nextTrough: nt };
}

var lifeChart = null;

function drawLifeline() {
  if (typeof document === 'undefined') return false;
  var dateEl = el('hackathon-life-date');
  var msg = el('hackathon-life-msg');
  var canvas = el('hackathon-life-canvas');
  if (!dateEl || !canvas) return false;
  function showMsg(t) { if (msg) { msg.textContent = t; msg.style.display = 'block'; } }
  function hideMsg() { if (msg) msg.style.display = 'none'; }
  var nowMs = Date.now();
  var age = exactAgeYears(dateEl.value, nowMs);
  if (!isFinite(age)) { showMsg('Please pick a valid birth date (not in the future).'); return false; }
  hideMsg();
  var idx = zodiacIndexForYear(parseInt(dateEl.value.slice(0, 4), 10));
  var table = zodiacOverallScores();
  if (idx < 0 || !table) { showMsg('Chinese zodiac data is unavailable right now.'); return false; }
  var center = table[idx];
  var animal = ZODIAC_ANIMALS[idx];
  var labels = [], values = [], centerLine = [];
  for (var a = 0; a <= 180; a++) {
    var t = a / 2;
    labels.push(t);
    values.push(zodiacSineValue(center, idx, t));
    centerLine.push(center);
  }
  var marker = labels.map(function () { return null; });
  var mi = Math.max(0, Math.min(180, Math.round(age * 2)));
  marker[mi] = zodiacSineValue(center, idx, age);
  var ex = zodiacNextExtreme(idx, age);
  var info = el('hackathon-life-info');
  if (info) {
    var parts = [];
    parts.push('<strong style="color:#fff">' + esc(animal) + '</strong> &middot; center ' + esc(String(center)));
    parts.push('Today: age ' + (Math.round(age * 10) / 10).toFixed(1) + ' &rarr; ' + esc(String(zodiacSineValue(center, idx, age))));
    parts.push('Next peak age: <strong style="color:#fff">' + (ex.nextPeak === null ? 'beyond 90' : ex.nextPeak) + '</strong> &middot; Next trough age: <strong style="color:#fff">' + (ex.nextTrough === null ? 'beyond 90' : ex.nextTrough) + '</strong>');
    info.innerHTML = parts.join('<br>');
  }
  if (typeof window === 'undefined' || typeof window.Chart === 'undefined') {
    var fb = el('hackathon-life-fallback');
    if (fb) fb.style.display = 'block';
    return true;
  }
  if (lifeChart) { try { lifeChart.destroy(); } catch (_) {} lifeChart = null; }
  lifeChart = new window.Chart(canvas, {
    type: 'line',
    data: { labels: labels, datasets: [
      { label: animal + ' lifeline', data: values, borderColor: '#FFD700', backgroundColor: 'rgba(255,215,0,.08)', borderWidth: 4, tension: 0.35, pointRadius: 0, yAxisID: 'y' },
      { label: 'Center ' + center, data: centerLine, borderColor: 'rgba(139,148,158,.6)', borderWidth: 1, borderDash: [6, 4], pointRadius: 0, yAxisID: 'y' },
      { label: 'Today', data: marker, borderColor: '#FF6B6B', backgroundColor: '#FF6B6B', showLine: false, pointRadius: 6, pointHoverRadius: 8, yAxisID: 'y' }
    ] },
    options: {
      responsive: true, maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: { legend: { labels: { color: '#8b949e', boxWidth: 12, font: { size: 11 } } } },
      scales: {
        x: { ticks: { color: '#8b949e', font: { size: 10 }, maxTicksLimit: 10 }, grid: { color: '#21262d' }, title: { display: true, text: 'Age', color: '#8b949e', font: { size: 10 } } },
        y: { min: 1, max: 10, ticks: { color: '#8b949e', font: { size: 10 } }, grid: { color: '#21262d' }, title: { display: true, text: 'Score', color: '#8b949e', font: { size: 10 } } }
      }
    }
  });
  return true;
}

function clearLifeline() {
  if (typeof document === 'undefined') return false;
  if (lifeChart) { try { lifeChart.destroy(); } catch (_) {} lifeChart = null; }
  var info = el('hackathon-life-info');
  if (info) info.innerHTML = '';
  var fb = el('hackathon-life-fallback');
  if (fb) fb.style.display = 'none';
  return true;
}

function initLifelineCard() {
  if (typeof document === 'undefined') return;
  if (!document.getElementById('hackathon-life')) return;
  var drawBtn = el('hackathon-life-draw');
  if (drawBtn) drawBtn.addEventListener('click', drawLifeline);
  var clearBtn = el('hackathon-life-clear');
  if (clearBtn) clearBtn.addEventListener('click', clearLifeline);
  var dateEl = el('hackathon-life-date');
  if (dateEl && dateEl.value) drawLifeline();
}

/* ============================ public API ================================ */
var api = {
  version: '1.3-hackathon',
  isDemo: isDemo,
  demoFlagFromQuery: demoFlagFromQuery,
  remedyShows: remedyShows,
  remedyHTML: remedyHTML,
  saveReading: saveReading,
  loadReadings: loadReadings,
  filterByView: filterByView,
  sampleWave: sampleWave,
  buildWaveData: buildWaveData,
  loadOverlay: loadOverlay,
  addOverlayEntry: addOverlayEntry,
  removeOverlayEntry: removeOverlayEntry,
  refreshWave: refreshWave,
  setView: setView,
  // HACKATHON (new fixes): per-oracle modal API.
  perOracleButtonHTML: perOracleButtonHTML,
  remedyModalTabs: remedyModalTabs,
  remedyModalHTML: remedyModalHTML,
  openRemedyModal: openRemedyModal,
  closeRemedyModal: closeRemedyModal,
  showRemedyTab: showRemedyTab,
  // HACKATHON (new): single-tradition popup API.
  singleTraditionContent: singleTraditionContent,
  singleTraditionModalHTML: singleTraditionModalHTML,
  openOverallRemedyModal: openOverallRemedyModal,
  // HACKATHON Step 3 (new): lifetime graph API.
  zodiacIndexForYear: zodiacIndexForYear,
  zodiacOverallScores: zodiacOverallScores,
  exactAgeYears: exactAgeYears,
  zodiacSineValue: zodiacSineValue,
  zodiacPeaksTroughs: zodiacPeaksTroughs,
  zodiacNextExtreme: zodiacNextExtreme,
  drawLifeline: drawLifeline,
  clearLifeline: clearLifeline
};

if (typeof module !== 'undefined' && module.exports) { module.exports = api; }
else if (typeof window !== 'undefined') { window.HackathonDemo = api; }

if (typeof document !== 'undefined') {
  // HACKATHON Step 3 (new): wire the separate lifetime graph alongside the wave.
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', initWaveCard); document.addEventListener('DOMContentLoaded', initLifelineCard); }
  else { initWaveCard(); initLifelineCard(); }
}

})();
