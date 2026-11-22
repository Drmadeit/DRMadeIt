/*
 * DXF polyline fixer — runs entirely in the browser (also loadable in Node for tests).
 * Reads LINE, ARC, CIRCLE, LWPOLYLINE, POLYLINE, SPLINE and ELLIPSE, breaks everything into
 * segments, removes duplicates and zero-length bits, then chains touching segments back into
 * polylines (closing small gaps), and writes a clean R12 DXF that CAM software reads.
 */
(function (root) {
  'use strict';

  const D2R = Math.PI / 180;
  const dist = (p, q) => Math.hypot(p[0] - q[0], p[1] - q[1]);

  // ---------- Parse ----------
  function parse(text) {
    const lines = text.split(/\r\n|\r|\n/);
    const pairs = [];
    for (let i = 0; i + 1 < lines.length; i += 2) {
      pairs.push([parseInt(lines[i].trim(), 10), lines[i + 1].trim()]);
    }
    const out = { units: 0, entities: [], skipped: {} };

    // Header: $INSUNITS
    for (let i = 0; i < pairs.length - 1; i++) {
      if (pairs[i][0] === 9 && pairs[i][1] === '$INSUNITS' && pairs[i + 1][0] === 70) {
        out.units = parseInt(pairs[i + 1][1], 10) || 0;
        break;
      }
    }

    // Entities section
    let start = -1;
    for (let i = 0; i < pairs.length - 1; i++) {
      if (pairs[i][0] === 0 && pairs[i][1] === 'SECTION' && pairs[i + 1][0] === 2 && pairs[i + 1][1] === 'ENTITIES') {
        start = i + 2;
        break;
      }
    }
    if (start < 0) throw new Error('No ENTITIES section found. Is this an ASCII DXF file?');

    // Split into entity records
    const recs = [];
    let cur = null;
    for (let i = start; i < pairs.length; i++) {
      const [code, val] = pairs[i];
      if (code === 0) {
        if (val === 'ENDSEC' || val === 'EOF') break;
        cur = { type: val, g: [] };
        recs.push(cur);
      } else if (cur) {
        cur.g.push([code, val]);
      }
    }

    for (let i = 0; i < recs.length; i++) {
      const r = recs[i];
      if (r.type === 'POLYLINE') {
        const verts = [];
        while (recs[i + 1] && recs[i + 1].type === 'VERTEX') verts.push(recs[++i]);
        if (recs[i + 1] && recs[i + 1].type === 'SEQEND') i++;
        out.entities.push({ type: 'POLYLINE', g: r.g, verts });
      } else if (['LINE', 'ARC', 'CIRCLE', 'LWPOLYLINE', 'SPLINE', 'ELLIPSE'].includes(r.type)) {
        out.entities.push(r);
      } else if (r.type !== 'VERTEX' && r.type !== 'SEQEND') {
        out.skipped[r.type] = (out.skipped[r.type] || 0) + 1;
      }
    }
    return out;
  }

  const num = (g, code, def = 0) => {
    const f = g.find((p) => p[0] === code);
    return f ? parseFloat(f[1]) : def;
  };
  const str = (g, code, def = '0') => {
    const f = g.find((p) => p[0] === code);
    return f ? f[1] : def;
  };
  // Entities drawn with extrusion (0,0,-1) are mirrored in X (common from some CAD apps).
  const mirrored = (g) => num(g, 230, 1) < 0;

  // ---------- Explode into segments {a, b, bulge, layer} ----------
  function explode(parsed) {
    const segs = [];
    const circles = [];
    const counts = {};
    const addPoly = (pts, closed, layer, flip) => {
      const n = pts.length;
      for (let i = 0; i < (closed ? n : n - 1); i++) {
        const p = pts[i];
        const q = pts[(i + 1) % n];
        segs.push({ a: [flip ? -p.x : p.x, p.y], b: [flip ? -q.x : q.x, q.y], bulge: flip ? -p.b : p.b, layer });
      }
    };

    for (const e of parsed.entities) {
      counts[e.type] = (counts[e.type] || 0) + 1;
      const g = e.g;
      const layer = str(g, 8);
      const flip = mirrored(g);
      if (e.type === 'LINE') {
        segs.push({ a: [num(g, 10), num(g, 20)], b: [num(g, 11), num(g, 21)], bulge: 0, layer });
      } else if (e.type === 'ARC') {
        let cx = num(g, 10);
        const cy = num(g, 20);
        const r = num(g, 40);
        let s = num(g, 50);
        let en = num(g, 51);
        if (flip) {
          cx = -cx;
          [s, en] = [180 - en, 180 - s];
        }
        let sweep = en - s;
        while (sweep <= 0) sweep += 360;
        while (sweep > 360) sweep -= 360;
        segs.push({
          a: [cx + r * Math.cos(s * D2R), cy + r * Math.sin(s * D2R)],
          b: [cx + r * Math.cos(en * D2R), cy + r * Math.sin(en * D2R)],
          bulge: Math.tan((sweep * D2R) / 4),
          layer,
        });
      } else if (e.type === 'CIRCLE') {
        circles.push({ c: [flip ? -num(g, 10) : num(g, 10), num(g, 20)], r: num(g, 40), layer });
      } else if (e.type === 'LWPOLYLINE') {
        const pts = [];
        for (const [code, val] of g) {
          if (code === 10) pts.push({ x: parseFloat(val), y: 0, b: 0 });
          else if (code === 20 && pts.length) pts[pts.length - 1].y = parseFloat(val);
          else if (code === 42 && pts.length) pts[pts.length - 1].b = parseFloat(val);
        }
        addPoly(pts, (num(g, 70) & 1) === 1, layer, flip);
      } else if (e.type === 'POLYLINE') {
        const flags = num(g, 70);
        if (flags & (16 | 64)) { // 3D mesh / polyface: not a 2D outline
          counts.POLYLINE--;
          parsed.skipped['POLYLINE (3D mesh)'] = (parsed.skipped['POLYLINE (3D mesh)'] || 0) + 1;
          continue;
        }
        const pts = e.verts
          .filter((v) => !(num(v.g, 70) & 16)) // skip spline frame control points
          .map((v) => ({ x: num(v.g, 10), y: num(v.g, 20), b: num(v.g, 42) }));
        addPoly(pts, (flags & 1) === 1, layer, flip);
      } else if (e.type === 'SPLINE') {
        const pts = sampleSpline(g);
        if (pts.length < 2) continue;
        addPoly(pts.map(([x, y]) => ({ x, y, b: 0 })), false, layer, flip);
      } else if (e.type === 'ELLIPSE') {
        addPoly(sampleEllipse(g).map(([x, y]) => ({ x, y, b: 0 })), false, layer, flip);
      }
    }
    return { segs, circles, counts };
  }

  // NURBS evaluation (de Boor) for SPLINE entities.
  function sampleSpline(g) {
    const deg = num(g, 71, 3);
    const knots = [];
    const ctrl = [];
    const w = [];
    for (const [code, val] of g) {
      if (code === 40) knots.push(parseFloat(val));
      else if (code === 10) ctrl.push([parseFloat(val), 0]);
      else if (code === 20 && ctrl.length) ctrl[ctrl.length - 1][1] = parseFloat(val);
      else if (code === 41) w.push(parseFloat(val));
    }
    if (ctrl.length <= deg || knots.length !== ctrl.length + deg + 1) {
      // fall back to fit points if present
      const fit = [];
      for (const [code, val] of g) {
        if (code === 11) fit.push([parseFloat(val), 0]);
        else if (code === 21 && fit.length) fit[fit.length - 1][1] = parseFloat(val);
      }
      return fit;
    }
    const weights = ctrl.map((_, i) => (w.length === ctrl.length ? w[i] : 1));
    const t0 = knots[deg];
    const t1 = knots[knots.length - deg - 1];
    const steps = Math.max(24, ctrl.length * 10);
    const pts = [];
    for (let s = 0; s <= steps; s++) {
      const t = t0 + ((t1 - t0) * s) / steps;
      let k = deg;
      while (k < ctrl.length - 1 && t >= knots[k + 1]) k++;
      const d = [];
      for (let j = 0; j <= deg; j++) {
        const i = k - deg + j;
        const wi = weights[i];
        d.push([ctrl[i][0] * wi, ctrl[i][1] * wi, wi]);
      }
      for (let r = 1; r <= deg; r++) {
        for (let j = deg; j >= r; j--) {
          const i = k - deg + j;
          const den = knots[i + deg - r + 1] - knots[i];
          const a = den === 0 ? 0 : (t - knots[i]) / den;
          d[j] = [0, 1, 2].map((c) => (1 - a) * d[j - 1][c] + a * d[j][c]);
        }
      }
      pts.push([d[deg][0] / d[deg][2], d[deg][1] / d[deg][2]]);
    }
    return pts;
  }

  function sampleEllipse(g) {
    const cx = num(g, 10);
    const cy = num(g, 20);
    const mx = num(g, 11);
    const my = num(g, 21);
    const ratio = num(g, 40, 1);
    let s = num(g, 41, 0);
    let e = num(g, 42, Math.PI * 2);
    if (e <= s) e += Math.PI * 2;
    const major = Math.hypot(mx, my);
    const rot = Math.atan2(my, mx);
    const steps = Math.max(24, Math.ceil(((e - s) / (Math.PI * 2)) * 96));
    const pts = [];
    for (let i = 0; i <= steps; i++) {
      const t = s + ((e - s) * i) / steps;
      const x = major * Math.cos(t);
      const y = major * ratio * Math.sin(t);
      pts.push([cx + x * Math.cos(rot) - y * Math.sin(rot), cy + x * Math.sin(rot) + y * Math.cos(rot)]);
    }
    return pts;
  }

  // ---------- Fix ----------
  /**
   * opts.tol: endpoints closer than this are the same point.
   * opts.gap: open ends closer than this get bridged with a short line (0 = never).
   */
  function fix(parsed, opts) {
    const tol = Math.max(1e-9, opts.tol);
    const gap = Math.max(tol, opts.gap || 0);
    const { segs: raw, circles, counts } = explode(parsed);
    const report = {
      input: counts, skipped: parsed.skipped, segments: raw.length,
      zeroLength: 0, duplicates: 0, bridged: 0, closed: 0, open: 0, circles: circles.length, openEnds: [],
    };

    // zero-length + duplicates
    const keyOf = (p) => Math.round(p[0] / tol) + ',' + Math.round(p[1] / tol);
    const seen = new Set();
    const segs = [];
    for (const s of raw) {
      if (dist(s.a, s.b) <= tol) {
        report.zeroLength++;
        continue;
      }
      let ka = keyOf(s.a);
      let kb = keyOf(s.b);
      let b = s.bulge;
      if (ka > kb) {
        [ka, kb] = [kb, ka];
        b = -b;
      }
      const k = s.layer + '|' + ka + '|' + kb + '|' + Math.round(b * 1e4);
      if (seen.has(k)) {
        report.duplicates++;
        continue;
      }
      seen.add(k);
      segs.push(s);
    }
    const seenC = new Set();
    const outCircles = circles.filter((c) => {
      const k = c.layer + '|' + keyOf(c.c) + '|' + Math.round(c.r / tol);
      if (seenC.has(k)) {
        report.duplicates++;
        return false;
      }
      seenC.add(k);
      return true;
    });
    report.circles = outCircles.length;

    // spatial hash of endpoints
    const cell = gap;
    const grid = new Map();
    const cKey = (x, y) => x + ':' + y;
    const add = (p, idx) => {
      const k = cKey(Math.floor(p[0] / cell), Math.floor(p[1] / cell));
      if (!grid.has(k)) grid.set(k, []);
      grid.get(k).push(idx);
    };
    segs.forEach((s, i) => {
      add(s.a, i);
      add(s.b, i);
    });
    const used = new Uint8Array(segs.length);
    const nearest = (p, layer) => {
      const cx = Math.floor(p[0] / cell);
      const cy = Math.floor(p[1] / cell);
      let best = null;
      for (let dx = -1; dx <= 1; dx++) {
        for (let dy = -1; dy <= 1; dy++) {
          for (const i of grid.get(cKey(cx + dx, cy + dy)) || []) {
            if (used[i] || segs[i].layer !== layer) continue;
            for (const end of ['a', 'b']) {
              const d = dist(p, segs[i][end]);
              if (d <= gap && (!best || d < best.d)) best = { i, end, d };
            }
          }
        }
      }
      return best;
    };
    const flipSeg = (s) => ({ a: s.b, b: s.a, bulge: -s.bulge, layer: s.layer });

    const chains = [];
    for (let i = 0; i < segs.length; i++) {
      if (used[i]) continue;
      used[i] = 1;
      const chain = [segs[i]];
      const layer = segs[i].layer;
      // forward
      for (;;) {
        const end = chain[chain.length - 1].b;
        const n = nearest(end, layer);
        if (!n) break;
        used[n.i] = 1;
        let s = n.end === 'a' ? { ...segs[n.i] } : flipSeg(segs[n.i]);
        if (n.d <= tol) s.a = end;
        else {
          chain.push({ a: end, b: s.a, bulge: 0, layer });
          report.bridged++;
        }
        chain.push(s);
      }
      // backward
      for (;;) {
        const startPt = chain[0].a;
        const n = nearest(startPt, layer);
        if (!n) break;
        used[n.i] = 1;
        let s = n.end === 'b' ? { ...segs[n.i] } : flipSeg(segs[n.i]);
        if (n.d <= tol) s.b = startPt;
        else {
          chain.unshift({ a: s.b, b: startPt, bulge: 0, layer });
          report.bridged++;
        }
        chain.unshift(s);
      }
      // close?
      const first = chain[0].a;
      const last = chain[chain.length - 1].b;
      const d = dist(first, last);
      let closed = false;
      if (d <= tol) {
        chain[chain.length - 1].b = first;
        closed = true;
      } else if (d <= gap) {
        chain.push({ a: last, b: first, bulge: 0, layer });
        report.bridged++;
        closed = true;
      }
      if (closed) report.closed++;
      else {
        report.open++;
        report.openEnds.push(first, last);
      }
      chains.push({ segs: chain, closed, layer });
    }
    return { chains, circles: outCircles, report };
  }

  // ---------- Write R12 DXF ----------
  function write(result) {
    const f = (n) => String(+n.toFixed(8));
    const o = ['0', 'SECTION', '2', 'HEADER', '9', '$ACADVER', '1', 'AC1009', '0', 'ENDSEC', '0', 'SECTION', '2', 'ENTITIES'];
    for (const c of result.chains) {
      o.push('0', 'POLYLINE', '8', c.layer, '66', '1', '10', '0', '20', '0', '30', '0', '70', c.closed ? '1' : '0');
      const verts = c.segs.map((s) => [s.a, s.bulge]);
      if (!c.closed) verts.push([c.segs[c.segs.length - 1].b, 0]);
      for (const [p, b] of verts) {
        o.push('0', 'VERTEX', '8', c.layer, '10', f(p[0]), '20', f(p[1]), '30', '0');
        if (Math.abs(b) > 1e-12) o.push('42', f(b));
      }
      o.push('0', 'SEQEND', '8', c.layer);
    }
    for (const c of result.circles) {
      o.push('0', 'CIRCLE', '8', c.layer, '10', f(c.c[0]), '20', f(c.c[1]), '30', '0', '40', f(c.r));
    }
    o.push('0', 'ENDSEC', '0', 'EOF');
    return o.join('\r\n') + '\r\n';
  }

  // ---------- SVG preview ----------
  function svg(result) {
    let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
    const grow = (p, r = 0) => {
      minX = Math.min(minX, p[0] - r); maxX = Math.max(maxX, p[0] + r);
      minY = Math.min(minY, p[1] - r); maxY = Math.max(maxY, p[1] + r);
    };
    result.chains.forEach((c) => c.segs.forEach((s) => { grow(s.a); grow(s.b); }));
    result.circles.forEach((c) => grow(c.c, c.r));
    if (!isFinite(minX)) return '';
    const w = maxX - minX || 1;
    const h = maxY - minY || 1;
    const pad = Math.max(w, h) * 0.04;
    const dot = Math.max(w, h) * 0.012;
    const f = (n) => +n.toFixed(5);
    const arc = (s) => {
      if (!s.bulge) return `L${f(s.b[0])} ${f(s.b[1])}`;
      const theta = 4 * Math.atan(Math.abs(s.bulge));
      const r = dist(s.a, s.b) / (2 * Math.sin(theta / 2));
      return `A${f(r)} ${f(r)} 0 ${theta > Math.PI ? 1 : 0} ${s.bulge > 0 ? 1 : 0} ${f(s.b[0])} ${f(s.b[1])}`;
    };
    let body = '';
    for (const c of result.chains) {
      const d = `M${f(c.segs[0].a[0])} ${f(c.segs[0].a[1])}` + c.segs.map(arc).join('') + (c.closed ? 'Z' : '');
      body += `<path d="${d}" class="${c.closed ? 'ok' : 'bad'}"/>`;
    }
    for (const c of result.circles) body += `<circle cx="${f(c.c[0])}" cy="${f(c.c[1])}" r="${f(c.r)}" class="ok"/>`;
    for (const p of result.report.openEnds) body += `<circle cx="${f(p[0])}" cy="${f(p[1])}" r="${f(dot)}" class="end"/>`;
    return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="${f(minX - pad)} ${f(-maxY - pad)} ${f(w + 2 * pad)} ${f(h + 2 * pad)}" role="img" aria-label="Preview of the fixed drawing"><g transform="scale(1,-1)">${body}</g></svg>`;
  }

  const api = { parse, explode, fix, write, svg };
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
  else root.DxfFix = api;
})(this);
