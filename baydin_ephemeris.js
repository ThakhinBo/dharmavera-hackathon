/**
 * baydin_ephemeris.js  —  Standalone Western Astrology Ephemeris
 * ================================================================
 * Approximate positions using truncated astronomical formulas.
 * Entered birth times are currently treated as UTC; no time-zone conversion.
 *
 * No external dependencies. Pure JS. Works client-side.
 * Based on: Meeus "Astronomical Algorithms" 2nd Ed.
 */

const Ephemeris = (function () {
  'use strict';

  const RAD = Math.PI / 180;
  const DEG = 180 / Math.PI;
  const PI2 = 2 * Math.PI;

  function norm360(v)  { return ((v % 360) + 360) % 360; }
  function normRad(v)  { return ((v % PI2)  + PI2)  % PI2;  }
  function toRad(d)    { return norm360(d) * RAD; }

  // ── Julian Ephemeris Day ────────────────────────────────────────
  function jde(yr, mo, dy, hr, mn) {
    hr = hr ?? 12; mn = mn ?? 0;
    const ut = hr + mn / 60;
    if (mo <= 2) { yr--; mo += 12; }
    const A = Math.floor(yr / 100);
    const B = 2 - A + Math.floor(A / 4);
    return Math.floor(365.25 * (yr + 4716))
         + Math.floor(30.6001 * (mo + 1))
         + dy + ut / 24 + B - 1524.5;
  }

  // Julian centuries from J2000.0
  function jcen(JDE) { return (JDE - 2451545.0) / 36525; }

  // ── Obliquity of ecliptic (Meeus p.147) ────────────────────────
  function obliquity(T) {
    return 23.4392911
         - 0.0130041667 * T
         - 0.0000001639 * T * T
         + 0.0000005036 * T * T * T;
  }

  // ── Sun geocentric apparent longitude + Earth radius (AU) ───────
  // Meeus Ch.25, accurate to ±0.01°
  function sunLonR(T) {
    const L0 = norm360(280.46646   + 36000.76983  * T + 0.0003032 * T * T);
    const M  = toRad  (357.52911   + 35999.05029  * T - 0.0001537 * T * T);
    const C  = (1.914602 - 0.004817 * T - 0.000014 * T * T) * Math.sin(M)
             + (0.019993 - 0.000101 * T) * Math.sin(2 * M)
             +  0.000289 * Math.sin(3 * M);
    const sunLonTrue  = norm360(L0 + C);
    const omega       = toRad(125.04 - 1934.136 * T);
    const apparent    = norm360(sunLonTrue - 0.00569 - 0.00478 * Math.sin(omega));
    // Earth–Sun radius
    const M_rad = toRad(357.52910918 + 35999.05029094 * T);
    const R = 1.000001018 * (1 - 0.016708617 * 0.016708617)
            / (1 + 0.016708617 * Math.cos(M_rad));
    return { lon: apparent, R };
  }

  // ── Moon geocentric longitude (ELP2000 truncated, 20 terms) ─────
  // Meeus Ch.47, accurate to ±0.1°
  function moonLon(T) {
    const T2 = T * T, T3 = T2 * T, T4 = T3 * T;
    const Lp = norm360(218.3164477 + 481267.88123421 * T - 0.0015786 * T2 + T3 / 538841     - T4 / 65194000);
    const D  = norm360(297.8501921 + 445267.11140340 * T - 0.0018819 * T2 + T3 / 545868     - T4 / 113065000);
    const M  = norm360(357.5291092 + 35999.05029090  * T - 0.0001536 * T2 + T3 / 24490000);
    const Mp = norm360(134.9633964 + 477198.86750550 * T + 0.0087414 * T2 + T3 / 69699      - T4 / 14712000);
    const F  = norm360(93.2720950  + 483202.01752330 * T - 0.0036539 * T2 - T3 / 3526000    + T4 / 863310000);
    const E  = 1 - 0.002516 * T - 0.0000074 * T2;
    // 20 main perturbation terms [Σl, d, m, m', f]
    const TERMS = [
      [ 6288774,  0,  0,  1,  0],
      [ 1274027,  2,  0, -1,  0],
      [  658314,  2,  0,  0,  0],
      [  213618,  0,  0,  2,  0],
      [ -185116,  0,  1,  0,  0],
      [ -114332,  0,  0,  0,  2],
      [   58793,  2,  0, -2,  0],
      [   57066,  2, -1, -1,  0],
      [   53322,  2,  0,  1,  0],
      [   45758,  2, -1,  0,  0],
      [  -40923,  0,  1, -1,  0],
      [  -34720,  1,  0,  0,  0],
      [  -30383,  0,  1,  1,  0],
      [   15327,  2,  0,  0, -2],
      [  -12528,  0,  0,  1,  2],
      [   10980,  0,  0,  1, -2],
      [   10675,  4,  0, -1,  0],
      [   10034,  0,  0,  3,  0],
      [    8548,  4,  0, -2,  0],
      [   -7888,  2,  1, -1,  0],
    ];
    let sl = 0;
    for (const [A, d, m, mp, f] of TERMS) {
      const arg = d * D * RAD + m * M * RAD + mp * Mp * RAD + f * F * RAD;
      const Ef  = Math.abs(m) === 1 ? E : Math.abs(m) === 2 ? E * E : 1;
      sl += A * Ef * Math.sin(arg);
    }
    return norm360(Lp + sl / 1000000);
  }

  // ── Solve Kepler's equation iteratively ─────────────────────────
  function keplerE(e, M_rad) {
    let E = M_rad;
    for (let i = 0; i < 8; i++) E = M_rad + e * Math.sin(E);
    return E;
  }

  // ── Planet heliocentric {L°, R AU} using Keplerian elements ─────
  // Orbital elements at J2000 + secular rates (Meeus Table 31.a)
  // a=semi-major axis AU, e=eccentricity, L0=mean lon°, w=lon of peri°, n=°/century, de=de/century, dw=dw/century
  const PLANETS_ELEM = {
    mercury: { a:0.387098,  e:0.205634, de:-0.000059, L0:252.250906, n:149474.0722491, w0:77.456119,  dw:1.5564776  },
    venus:   { a:0.723330,  e:0.006773, de:-0.000005, L0:181.979801, n: 58519.2130302, w0:131.563707, dw:1.4024460  },
    mars:    { a:1.523688,  e:0.093405, de: 0.000092, L0:355.433275, n: 19140.2993313, w0:336.040709, dw:1.8410539  },
    jupiter: { a:5.202561,  e:0.048498, de:-0.000163, L0: 34.351519, n:  3034.9056606, w0: 14.331207, dw:1.0418738  },
    saturn:  { a:9.554747,  e:0.055546, de:-0.000346, L0: 49.954244, n:  1222.1138488, w0: 92.431407, dw:1.3971467  },
  };

  function helioLonR(name, T) {
    const el = PLANETS_ELEM[name];
    const e  = el.e + el.de * T;
    const w  = norm360(el.w0 + el.dw * T);           // longitude of perihelion
    const L  = norm360(el.L0 + el.n * T);            // mean longitude
    const M  = toRad(L - w);                          // mean anomaly (rad)
    const E  = keplerE(e, M);                         // eccentric anomaly
    const v  = 2 * Math.atan2(
      Math.sqrt(1 + e) * Math.sin(E / 2),
      Math.sqrt(1 - e) * Math.cos(E / 2)
    );                                                 // true anomaly
    const r  = el.a * (1 - e * Math.cos(E));          // heliocentric distance AU
    const lambda = norm360(v * DEG + w);              // true heliocentric longitude
    return { L: lambda, R: r };
  }

  // ── Heliocentric → Geocentric longitude ────────────────────────
  function toGeoLon(planet, earth) {
    const pL = planet.L * RAD, eL = earth.L * RAD;
    const x  = planet.R * Math.cos(pL) - earth.R * Math.cos(eL);
    const y  = planet.R * Math.sin(pL) - earth.R * Math.sin(eL);
    return norm360(Math.atan2(y, x) * DEG);
  }

  // ── Greenwich Mean Sidereal Time (degrees) ──────────────────────
  function gmst(JDE) {
    const JD0 = Math.floor(JDE - 0.5) + 0.5;   // JD at 0h UT
    const T0  = (JD0 - 2451545.0) / 36525;
    return norm360(
      100.4606184
    + 36000.77004 * T0
    + 0.000387933 * T0 * T0
    + (JDE - JD0) * 360.98564724
    );
  }

  // ── Ascendant (ecliptic longitude rising on eastern horizon) ────
  // Meeus Ch.14
  function calcAscendant(JDE, latDeg, lonDeg) {
    const T   = jcen(JDE);
    const eps = obliquity(T) * RAD;
    const LST = norm360(gmst(JDE) + lonDeg) * RAD;   // Local Sidereal Time rad
    const phi = latDeg * RAD;
    const y   = -Math.cos(LST);
    const x   = Math.sin(eps) * Math.tan(phi) + Math.cos(eps) * Math.sin(LST);
    return norm360(Math.atan2(y, x) * DEG);
  }

  // ── Midheaven (MC) ───────────────────────────────────────────────
  function calcMC(JDE, lonDeg) {
    const T   = jcen(JDE);
    const eps = obliquity(T) * RAD;
    const LST = norm360(gmst(JDE) + lonDeg) * RAD;
    return norm360(Math.atan2(Math.sin(LST), Math.cos(eps) * Math.cos(LST)) * DEG);
  }

  // ── Midpoint longitude (shorter arc) ───────────────────────────
  function midpointLon(a, b) {
    const diff = Math.abs(a - b);
    return norm360(diff <= 180 ? (a + b) / 2 : (a + b) / 2 + 180);
  }

  // ── Main entry: compute all positions ───────────────────────────
  // dob: 'YYYY-MM-DD' | timeStr: 'HH:MM' or '' | lat/lonDeg: number or null
  function getPositions(dob, timeStr, latDeg, lonDeg) {
    const yr = +dob.substring(0, 4);
    const mo = +dob.substring(5, 7);
    const dy = +dob.substring(8, 10);
    let hr = 12, mn = 0;
    const hasTime = !!(timeStr && timeStr.includes(':'));
    if (hasTime) { hr = +timeStr.split(':')[0]; mn = +timeStr.split(':')[1]; }
    const hasLoc  = (latDeg !== null && latDeg !== undefined && lonDeg !== null && lonDeg !== undefined);

    const JDE = jde(yr, mo, dy, hr, mn);
    const T   = jcen(JDE);

    const sun   = sunLonR(T);
    const earth = { L: norm360(sun.lon + 180), R: sun.R };  // Earth helio = Sun geo + 180°

    const pos = {
      sun:     sun.lon,
      moon:    moonLon(T),
      mercury: toGeoLon(helioLonR('mercury', T), earth),
      venus:   toGeoLon(helioLonR('venus',   T), earth),
      mars:    toGeoLon(helioLonR('mars',    T), earth),
      jupiter: toGeoLon(helioLonR('jupiter', T), earth),
      saturn:  toGeoLon(helioLonR('saturn',  T), earth),
      hasTime,
      hasLoc,
      JDE,
    };

    if (hasTime && hasLoc) {
      pos.ascendant = calcAscendant(JDE, latDeg, lonDeg);
      pos.midheaven = calcMC(JDE, lonDeg);
    }

    return pos;
  }

  // ── Zodiac sign data ─────────────────────────────────────────────
  const SIGNS = [
    { name:'Aries',       icon:'♈', el:'Fire',  mm:'မိဿ',   qual:'Cardinal', ruler:'Mars'    },
    { name:'Taurus',      icon:'♉', el:'Earth', mm:'ပြိဿ',  qual:'Fixed',    ruler:'Venus'   },
    { name:'Gemini',      icon:'♊', el:'Air',   mm:'မိထုန်', qual:'Mutable',  ruler:'Mercury' },
    { name:'Cancer',      icon:'♋', el:'Water', mm:'ကရကဋ်', qual:'Cardinal', ruler:'Moon'    },
    { name:'Leo',         icon:'♌', el:'Fire',  mm:'သိဟ်',   qual:'Fixed',    ruler:'Sun'     },
    { name:'Virgo',       icon:'♍', el:'Earth', mm:'ကန်',   qual:'Mutable',  ruler:'Mercury' },
    { name:'Libra',       icon:'♎', el:'Air',   mm:'တူ',    qual:'Cardinal', ruler:'Venus'   },
    { name:'Scorpio',     icon:'♏', el:'Water', mm:'ဗြိဿ',  qual:'Fixed',    ruler:'Mars'    },
    { name:'Sagittarius', icon:'♐', el:'Fire',  mm:'ဓနု',   qual:'Mutable',  ruler:'Jupiter' },
    { name:'Capricorn',   icon:'♑', el:'Earth', mm:'မကာရ',  qual:'Cardinal', ruler:'Saturn'  },
    { name:'Aquarius',    icon:'♒', el:'Air',   mm:'ကုမ်',  qual:'Fixed',    ruler:'Saturn'  },
    { name:'Pisces',      icon:'♓', el:'Water', mm:'မိန်',   qual:'Mutable',  ruler:'Jupiter' },
  ];

  function signOf(lon) {
    return SIGNS[Math.floor(norm360(lon) / 30)];
  }

  function degInSign(lon) {
    return Math.floor(norm360(lon) % 30);
  }

  // Display: "12° Aries ♈"
  function lonLabel(lon) {
    const s = signOf(lon);
    const d = degInSign(lon);
    return `${d}° ${s.name} ${s.icon}`;
  }

  // ── Aspect detection ─────────────────────────────────────────────
  function aspectBetween(a, b) {
    let d = Math.abs(a - b) % 360;
    if (d > 180) d = 360 - d;
    if (d <= 8)                return { type:'Conjunction', sym:'☌', harmony: 1,   color:'#FFD700', orb: d.toFixed(1) };
    if (Math.abs(d -  60) <= 6)return { type:'Sextile',    sym:'⚹', harmony: 1,   color:'#5cb85c', orb: Math.abs(d - 60).toFixed(1) };
    if (Math.abs(d -  90) <= 7)return { type:'Square',     sym:'□', harmony:-1,   color:'#e05252', orb: Math.abs(d - 90).toFixed(1) };
    if (Math.abs(d - 120) <= 8)return { type:'Trine',      sym:'△', harmony: 2,   color:'#58a6ff', orb: Math.abs(d - 120).toFixed(1) };
    if (Math.abs(d - 180) <= 8)return { type:'Opposition', sym:'☍', harmony: 0,   color:'#f0ad4e', orb: Math.abs(d - 180).toFixed(1) };
    return null;
  }

  // ── Synastry scoring ─────────────────────────────────────────────
  // Checks 18 key planet pairs, returns per-dimension 1–10 scores
  function calcSynastry(p1, p2, czPct, burPct) {
    const CHK = [
      // Emotional: Sun/Moon cross-aspects + Moon/Moon
      { a:'sun',     b:'moon',    w:3, d:'emotional'    },
      { a:'moon',    b:'sun',     w:3, d:'emotional'    },
      { a:'moon',    b:'moon',    w:2, d:'emotional'    },
      { a:'venus',   b:'moon',    w:1, d:'emotional'    },
      // Attraction: Venus/Mars cross-aspects + Venus/Venus
      { a:'venus',   b:'mars',    w:3, d:'attraction'   },
      { a:'mars',    b:'venus',   w:3, d:'attraction'   },
      { a:'venus',   b:'venus',   w:2, d:'attraction'   },
      { a:'sun',     b:'mars',    w:1, d:'attraction'   },
      // Communication: Mercury aspects + Sun/Sun
      { a:'mercury', b:'mercury', w:2, d:'communication'},
      { a:'mercury', b:'sun',     w:2, d:'communication'},
      { a:'sun',     b:'mercury', w:2, d:'communication'},
      { a:'sun',     b:'sun',     w:1, d:'communication'},
      // Stability: Sun/Venus cross + Moon/Venus + outer planets
      { a:'sun',     b:'venus',   w:2, d:'stability'    },
      { a:'venus',   b:'sun',     w:2, d:'stability'    },
      { a:'moon',    b:'venus',   w:1, d:'stability'    },
      { a:'jupiter', b:'sun',     w:1, d:'stability'    },
      { a:'saturn',  b:'moon',    w:1, d:'stability'    },
      { a:'moon',    b:'mars',    w:1, d:'stability'    },
    ];
    // If Ascendant available, add it
    if (p1.ascendant !== undefined) {
      CHK.push({ a:'ascendant', b:'sun',       w:2, d:'emotional' });
      CHK.push({ a:'ascendant', b:'moon',      w:2, d:'emotional' });
      CHK.push({ a:'ascendant', b:'ascendant', w:2, d:'attraction'});
    }

    const ASC = { Trine:2, Sextile:1.5, Conjunction:0.5, Opposition:-0.5, Square:-1.5 };
    const tot = { emotional:0, attraction:0, communication:0, stability:0 };
    const totW= { emotional:0, attraction:0, communication:0, stability:0 };
    const found = [];

    for (const c of CHK) {
      const lon1 = p1[c.a];
      const lon2 = p2[c.b];
      if (lon1 === undefined || lon2 === undefined) continue;
      totW[c.d] += c.w;
      const asp = aspectBetween(lon1, lon2);
      if (asp) {
        tot[c.d] += (ASC[asp.type] || 0) * c.w;
        found.push({
          ...asp,
          p1planet: c.a,
          p2planet: c.b,
          label: `${cap(c.a)} ${asp.sym} ${cap(c.b)}`,
        });
      }
    }

    const sc = {};
    for (const d of Object.keys(tot)) {
      const raw = totW[d] > 0 ? tot[d] / totW[d] : 0;  // -1.5 … +2
      sc[d] = Math.min(10, Math.max(1, Math.round(5 + raw * 2.2)));
    }
    // Stability blends Eastern results
    sc.stability = Math.min(10, Math.max(1,
      Math.round(sc.stability * 0.4 + czPct / 10 * 0.3 + burPct / 10 * 0.3)
    ));
    sc.overall = Math.min(10, Math.max(1,
      Math.round(sc.emotional * 0.3 + sc.attraction * 0.2 + sc.communication * 0.2 + sc.stability * 0.3)
    ));

    // Sort aspects by harmony desc, then deduplicate label
    found.sort((a, b) => Math.abs(b.harmony) - Math.abs(a.harmony));
    const seen = new Set();
    const unique = found.filter(a => { const k = a.label; if (seen.has(k)) return false; seen.add(k); return true; });

    return { scores: sc, aspects: unique };
  }

  function cap(s) { return s.charAt(0).toUpperCase() + s.slice(1); }

  // ── Geocode city name (OpenStreetMap Nominatim, free, no API key) ─
  async function geocodeCity(city) {
    if (!city || !city.trim()) return null;
    try {
      const url = 'https://nominatim.openstreetmap.org/search?q='
                + encodeURIComponent(city.trim())
                + '&format=json&limit=1&addressdetails=0';
      const res  = await fetch(url, {
        headers: { 'Accept-Language': 'en', 'User-Agent': 'BaydinAstrologyApp/1.0' }
      });
      const data = await res.json();
      if (data && data[0]) {
        return {
          lat:         +data[0].lat,
          lon:         +data[0].lon,
          displayName: data[0].display_name.split(',').slice(0, 2).join(', '),
        };
      }
    } catch (_) {}
    return null;
  }

  // ── Public API ───────────────────────────────────────────────────
  return {
    jde,
    jcen,
    obliquity,
    getPositions,
    signOf,
    degInSign,
    lonLabel,
    midpointLon,
    aspectBetween,
    calcSynastry,
    geocodeCity,
    SIGNS,
  };
})();
