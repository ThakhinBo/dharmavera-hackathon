// hackathon_demo.test.cjs - HACKATHON focused checks for the demo fixes.
// Run from the site root:  node hackathon_demo.test.cjs
// Covers: per-oracle Remedy buttons, single-tradition popups (one tradition
// each, no cross content), the six-tab overall popup, and demo-mode wave
// views. Requires ./hackathon_demo.js. Exits nonzero on failure.
const path = require('path');
const store = {};
global.localStorage = {
  getItem: function (k) { return (k in store) ? store[k] : null; },
  setItem: function (k, v) { store[k] = String(v); },
  removeItem: function (k) { delete store[k]; }
};
const H = require(path.join(__dirname, 'hackathon_demo.js'));

let pass = 0;
let fail = 0;
function ok(cond, name) {
  if (cond) { pass++; console.log('PASS ' + name); }
  else { fail++; console.log('FAIL ' + name); }
}
function eq(a, b) { return JSON.stringify(a) === JSON.stringify(b); }

// --- Per-oracle Remedy buttons (below 5 only) ---
const SCORES = { mhb: 4, kauCim: 6, tehillim: 7, bible: 8, quran: 9, ram: 7, tarot: 3 };
const btnKeys = [];
Object.keys(SCORES).forEach(function (k) { if (H.perOracleButtonHTML(k, SCORES[k]) !== '') btnKeys.push(k); });
ok(eq(btnKeys, ['mhb', 'tarot']), '6.1-style reading shows exactly Mahabote+Gypsy buttons');
ok(H.perOracleButtonHTML('mhb', 4).indexOf('openRemedyModal') !== -1, 'button opens the modal');
ok(H.perOracleButtonHTML('mhb', 5) === '', 'no button at exactly 5');
ok(H.remedyHTML(6.1) === '', 'no overall remedy at 6.1');
ok(H.perOracleButtonHTML('nope', 3) === '', 'unknown oracle gets no button');
ok(H.perOracleButtonHTML('mhb', 'x') === '', 'non-numeric score gets no button');

// --- Overall remedy section keeps its six-tab popup trigger ---
ok(H.remedyHTML(5).indexOf('data-hack-roverall') !== -1, 'overall remedy links the six-tab popup');
ok(H.remedyHTML(5).indexOf('openOverallRemedyModal') !== -1, 'overall remedy opens all traditions');
ok(H.remedyHTML(6) === '', 'overall remedy absent above 5');

// --- Six-tab overall popup (unchanged) ---
const tabs = H.remedyModalTabs();
ok(tabs.length === 6, 'six tradition tabs');
ok(eq(tabs.map(function (t) { return t.id; }), ['jewish', 'burmese', 'hindu', 'chinese', 'islamic', 'christian']), 'tab ids and order');
ok(tabs.every(function (t) { return t.items.length >= 2 && t.items.length <= 3; }), 'each tab lists 2-3 actions');
const mh = H.remedyModalHTML('Mahabote', 4);
ok((mh.match(/data-hack-rtab/g) || []).length === 6, 'overall modal renders 6 tabs');
ok((mh.match(/data-hack-rpanel/g) || []).length === 6, 'overall modal renders 6 panels');
ok(mh.split('Closing this popup does not change any score').length === 3, 'overall disclaimer top and bottom');

// --- Single-tradition popups: structure, content, isolation ---
const EXPECTED = {
  tehillim: { oracle: 'Tehillim', need: ['Tzedakah', '18', 'Chai', 'Tikkun', 'Hatavat Chalom', 'Tehillim', 'rabbi'], markers: ['Tzedakah', 'Tikkun', 'Hatavat Chalom', 'Tehillim', 'Chai', 'siddur', 'rabbi'] },
  mhb: { oracle: 'Mahabote', need: ['Yadaya', 'Dana', 'monastery', 'monk', 'karma', 'Buddhist'], markers: ['Yadaya', 'yadaya', 'Dana', 'monks', 'monastery', 'Buddhist', 'karma'] },
  kauCim: { oracle: 'Kau Cim', need: ['Hua Jie', 'Tai Sui', 'Duo Chun', 'incense', 'red'], markers: ['Hua Jie', 'Tai Sui', 'Duo Chun', 'incense'] },
  bible: { oracle: 'Bible', need: ["Lord's Prayer", 'Repentan', 'Fasting', 'almsgiving', 'Blessing', 'priest', 'pastor'], markers: ["Lord's Prayer", 'almsgiving', 'gospel', 'priest', 'pastor', 'confession'] },
  quran: { oracle: 'Quran', need: ["Du'a", 'Istighfar', 'Sadaqah', 'Ruqyah', 'Al-Fatiha', 'Ayat al-Kursi', 'imam'], markers: ["Du'a", 'Istighfar', 'Sadaqah', 'Ruqyah', 'Al-Fatiha', 'imam'] },
  ram: { oracle: 'Ram Shalaka', need: ['Graha Shanti', 'Parihara', 'satvik', 'seva', 'purohit'], markers: ['Graha Shanti', 'Parihara', 'purohit', 'satvik', 'seva'] },
  tarot: { oracle: 'Gypsy Cards', need: ['folk cards', 'overall remedy', 'Pause and write', 'trusted'], markers: [] }
};
const KEYS = Object.keys(EXPECTED);
function joined(k) {
  const c = H.singleTraditionContent(k);
  let s = c.heading + ' ' + (c.guide || '') + ' ' + (c.foot || '') + ' ' + (c.folkNote || '');
  c.actions.forEach(function (a) { s += ' ' + a.t + ' ' + a.what + ' ' + a.why + ' ' + a.how.join(' ') + ' ' + a.cost + ' ' + (a.note || ''); });
  return s;
}
KEYS.forEach(function (k) {
  const c = H.singleTraditionContent(k);
  ok(!!c && c.actions.length >= 3, k + ' has 3+ actions');
  ok(c.actions.every(function (a) { return a.t && a.what && a.why && Array.isArray(a.how) && a.how.length >= 2 && a.cost; }), k + ' actions have what/why/how/cost');
  ok(c.actions.every(function (a) { return /free|amount/i.test(a.cost); }), k + ' every cost is free/any-amount');
  ok(EXPECTED[k].need.every(function (w) { return joined(k).indexOf(w) !== -1; }), k + ' has required content');
  ok(joined(k).length > 700, k + ' popup is detailed, not one-liners');
  const html = H.singleTraditionModalHTML(k, 4);
  ok(html.indexOf('Remedies for reflection - ' + EXPECTED[k].oracle + ' (score 4)') !== -1, k + ' title matches oracle+score');
  ok(html.indexOf('data-hack-rtab') === -1 && html.indexOf('data-hack-rpanel') === -1, k + ' popup has no tabs');
  ok(html.split('Closing this popup does not change any score').length === 3, k + ' disclaimer top+bottom');
});
KEYS.forEach(function (k) {
  let clean = true;
  KEYS.forEach(function (j) {
    if (j === k) return;
    EXPECTED[k].markers.forEach(function (m) { if (joined(j).indexOf(m) !== -1) clean = false; });
  });
  ok(clean, EXPECTED[k].oracle + ' markers appear nowhere else');
});
let allSingle = '';
KEYS.forEach(function (k) { allSingle += ' ' + joined(k); });
ok(allSingle.indexOf('http') === -1 && allSingle.indexOf('$') === -1, 'no payment links or amounts anywhere');
ok(allSingle.indexOf('pay now') === -1 && allSingle.indexOf('click to pay') === -1, 'no paid-service wording');
ok(H.singleTraditionModalHTML('tarot', 3).indexOf('data-hack-roverall') !== -1, 'gypsy popup points to overall remedy');
ok(H.singleTraditionContent('xx') === null && H.singleTraditionModalHTML('xx', 3) === '', 'unknown key yields nothing');

// --- Demo-mode wave views (unchanged behavior) ---
const NOW = Date.UTC(2026, 9, 27, 12, 0, 0);
function d(n) { return new Date(NOW - n * 86400000).toISOString().slice(0, 10); }
const R = [{ d: d(20), s: 4, t: 11 }, { d: d(10), s: 8, t: 12 }, { d: d(2), s: 6, t: 13 }];
const nd = H.buildWaveData({ readings: R, view: '1m', nowMs: NOW });
ok(nd.sampleValues === null && nd.ownValues === null, 'non-demo behavior unchanged');
const dd = H.buildWaveData({ readings: R, view: '1m', nowMs: NOW, demo: true });
ok(dd.sampleValues !== null && dd.ownValues !== null, 'demo keeps sample + own columns');
function nz(a) { return a.filter(function (x) { return x !== null; }); }
ok(eq(nz(dd.ownValues), [4, 8, 6]), 'demo own values intact');

// --- Step 3: lifetime graph (one sine line, own animal only) ---
global.window = global.window || {};
global.window.DV_CZ_OVERALL = [7.6, 5.2, 7.0, 7.4, 8.4, 7.4, 7.2, 6.6, 8.2, 7.2, 7.0, 6.6];
ok(H.zodiacIndexForYear(1988) === 4, '1988 is Dragon index 4');
ok(H.zodiacIndexForYear(2020) === 0, '2020 is Rat index 0');
ok(H.zodiacIndexForYear(2019) === 11, '2019 is Pig index 11');
ok(H.zodiacIndexForYear(2000) === 4 && H.zodiacIndexForYear(2024) === 4, 'dragons share index 4');
ok(H.zodiacIndexForYear(1900) === 0 && H.zodiacIndexForYear(1899) === 11, 'lookup wraps like the site formula');
const ZT = H.zodiacOverallScores();
ok(ZT !== null && ZT.length === 12 && ZT[4] === 8.4, 'dragon center 8.4 from trait table');
ok(ZT[0] === 7.6 && ZT[11] === 6.6, 'rat/pig centers spot-checked');
const dPT = H.zodiacPeaksTroughs(4);
ok(eq(dPT.peaks.slice(0, 4), [11, 23, 35, 47]), 'dragon peaks 11/23/35/47');
ok(eq(dPT.troughs.slice(0, 4), [5, 17, 29, 41]), 'dragon troughs 5/17/29/41');
let dMin = 99, dMax = -99;
for (let a = 0; a <= 180; a++) { const v = H.zodiacSineValue(8.4, 4, a / 2); if (v < dMin) dMin = v; if (v > dMax) dMax = v; }
ok(Math.abs(dMin - 6.9) < 1e-9 && Math.abs(dMax - 9.9) < 1e-9, 'dragon range 6.9 to 9.9');
ok(Math.abs(H.zodiacSineValue(8.4, 4, 38) - 8.4) < 1e-9, 'age 38 crosses 8.4');
ok(H.zodiacSineValue(8.4, 4, 37.99) > H.zodiacSineValue(8.4, 4, 38.01), 'age-38 crossing runs downward');
const rPT = H.zodiacPeaksTroughs(0);
ok(eq(rPT.peaks.slice(0, 4), [3, 15, 27, 39]), 'rat peaks match index 0');
ok(eq(rPT.troughs.slice(0, 4), [9, 21, 33, 45]), 'rat troughs match index 0');
ok(ZT[0] !== ZT[4], 'second animal has a different center');
ok(H.zodiacSineValue(9.5, 0, 3) === 10, 'clamp caps at 10');
ok(H.zodiacSineValue(0.5, 0, 9) === 1, 'clamp floors at 1');
const nx = H.zodiacNextExtreme(4, 38);
ok(nx.nextPeak === 47 && nx.nextTrough === 41, 'dragon at 38: next peak 47, trough 41');
const ageX = H.exactAgeYears('2000-01-01', Date.UTC(2026, 0, 1));
ok(Math.abs(ageX - 26) < 0.01, 'exact age in years');
ok(isNaN(H.exactAgeYears('2100-01-01', Date.UTC(2026, 0, 1))), 'future birth rejected');
ok(isNaN(H.exactAgeYears('not-a-date', Date.UTC(2026, 0, 1))), 'bad date rejected');
console.log('----');
console.log('PASS: ' + pass + ' FAIL: ' + fail);
process.exit(fail ? 1 : 0);
