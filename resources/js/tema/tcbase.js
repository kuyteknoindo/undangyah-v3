// BERKAS HASIL EKSTRAKSI (scripts/tema-ekstrak.mjs) dari papan artifact UndanganTamu.dc.html: class TCBase (logika editor + render pohon).
// Jangan disunting manual; ubah skrip ekstraksi atau sanitasi.js. Lihat docs/tema-mesin.md.
import { DCLogic } from './dc.js';
import {
  SANS,
  BLUE,
  MAXLV,
  IC,
  IN_GROUPS,
  LOOP_LIST,
  LOOP_DUR,
  EASES,
  EASE_CSS,
  TRIGGERS,
  HOVERS,
  CLICKS,
  A0,
  TOKS,
  col,
  px,
  css,
  clone,
  clamp,
  getPath,
  setPathIn,
  SAMPLE_ASSETS,
  hex2,
  cap,
  V,
  SEGALIGN,
  WID,
  PALETTE,
  PRES,
  PSET,
  pid,
  F,
  g,
  VH,
  PHOTO,
  qmA,
  noneShow,
  noneHide,
  GBG0,
  GTOK,
  gbgOf,
  secHasBtn,
  secGateOn,
  isCoverSec,
  secUsesG,
  snapFlip,
  mergedThemes,
  xFields,
  MBD,
  mbEnc,
  mbRows,
  mbLink,
  mbSpecs,
  mbSummary,
  mkNode,
  lvl,
  hgt,
  isAnc,
  detach,
  attach,
  subtree,
  validDoc,
  starter,
  THEME0,
  PALETTES,
  slug,
} from './prelude.js';

class TCBase extends DCLogic {
  constructor(props) {
    super(props);
    const st = starter();
    this.state = {
      nodes: st.nodes, root: st.root, nid: st.nid, sel: null, ltab: 'widget', q: '', fold: {}, lopen: {}, sopen: { k: true, a: true, n: false, i: false }, dd: null,
      drag: null, live: null, pv: false, gbm: false, vh: 800, pvn: {}, lp: {}, assets: SAMPLE_ASSETS.slice(), aid: 0, theme: Object.assign({}, THEME0), meta: { id: 'tm' + Date.now().toString(36), nama: 'Tema Baru', tier: 'Gratis', status: 'draft' },
      undo: [], redo: [], dirty: false, savedAt: '', toast: '', clip: null, ov: null,
    };
  }

  componentDidMount() {
    try { this.__rs = () => this.measure(); window.addEventListener('resize', this.__rs); } catch (e) {}
    try {
      const k = localStorage.getItem('undangyah-theme-open');
      if (k) { localStorage.removeItem('undangyah-theme-open'); if (k === '__new__') this.newTheme(); else this.openTheme(k); }
    } catch (e) {}
  }
  componentDidUpdate() { this.measure(); this.bindWheel(); }
  bindWheel() {
    try {
      const st = this.state; const el = document.getElementById('tcScroll'); const on = !!(st.pv && st.theme.snap !== 0 && st.theme.snap !== false);
      if (el === this.__wEl && on === this.__wOn) return;
      if (this.__wEl && this.__wFn) this.__wEl.removeEventListener('wheel', this.__wFn);
      this.__wEl = el; this.__wOn = on; this.__wFn = null;
      if (el && on) { const lock = { t: 0 }; this.__wFn = (e) => snapFlip(el, e, lock); el.addEventListener('wheel', this.__wFn, { passive: false }); }
    } catch (e) {}
  }
  componentWillUnmount() { try { clearTimeout(this.__tt); this.ptrOff(); window.removeEventListener('resize', this.__rs); } catch (e) {} }
  // ukur kotak elemen terpilih (overlay seleksi digambar di luar halaman supaya halaman bisa overflow:hidden)
  measure() {
    const s = this.state; let ov = null;
    try {
      if (s.sel && !s.pv && s.nodes[s.sel]) {
        const el = document.querySelector('[data-nid="' + s.sel + '"]'); const wr = document.getElementById('tcWrap');
        if (el && wr && el.offsetWidth > 0) {
          const r = el.getBoundingClientRect(); const w0 = wr.getBoundingClientRect(); const ow = el.offsetWidth; const oh = el.offsetHeight;
          let rot = 0; let c = s.nodes[s.sel]; while (c) { const o = s.live && s.live.id === c.id && s.live.o.rot != null ? s.live.o.rot : (c.rot || 0); rot += o; c = c.par ? s.nodes[c.par] : null; }
          const cx = r.left + r.width / 2 - w0.left; const cy = r.top + r.height / 2 - w0.top;
          ov = { id: s.sel, l: Math.round(cx - ow / 2), t: Math.round(cy - oh / 2), w: ow, h: oh, rot: Math.round(rot * 10) / 10, bl: Math.round(r.left - w0.left), bt: Math.round(r.top - w0.top), bh: Math.round(r.height) };
        }
      }
    } catch (e) { ov = null; }
    const a = JSON.stringify(ov); const b = JSON.stringify(s.ov || null);
    if (a !== b) this.setState({ ov });
    try { const sc = document.getElementById('tcScroll'); const vh = sc ? sc.clientHeight : 0; if (vh > 200 && Math.abs(vh - (s.vh || 0)) > 2) this.setState({ vh }); } catch (e3) {}
  }

  // ---------- undo / commit ----------
  snap() { const s = this.state; return { nodes: s.nodes, root: s.root, theme: s.theme, meta: s.meta }; }
  commit(patch, force) {
    const s = this.state; const now = Date.now();
    if (force || now - (this.__lt || 0) > 700) this.setState({ undo: (s.undo || []).concat([this.snap()]).slice(-80), redo: [] });
    this.__lt = force ? 0 : now;
    this.setState(Object.assign({ dirty: true }, patch));
  }
  undo() { const s = this.state; const u = s.undo || []; if (!u.length) return; this.__lt = 0; this.setState(Object.assign({ undo: u.slice(0, -1), redo: (s.redo || []).concat([this.snap()]), dirty: true, sel: null }, u[u.length - 1])); }
  redo() { const s = this.state; const r = s.redo || []; if (!r.length) return; this.__lt = 0; this.setState(Object.assign({ redo: r.slice(0, -1), undo: (s.undo || []).concat([this.snap()]), dirty: true, sel: null }, r[r.length - 1])); }
  flash(msg) { this.setState({ toast: msg }); try { clearTimeout(this.__tt); } catch (e) {} this.__tt = setTimeout(() => this.setState({ toast: '' }), 2400); }

  // ---------- operasi node ----------
  select(id) { this.setState({ sel: id, dd: null, pvn: {}, lp: {} }); }
  openMusic() {
    const s = this.state;
    if (!s.nodes.gmusic) this.commit({ nodes: Object.assign({}, s.nodes, { gmusic: mkNode('audio', 'gmusic', { name: 'Musik Global', p: { on: true } }) }) }, true);
    this.setState({ sel: 'gmusic', dd: null, gbm: false, pvn: {}, lp: {} });
  }
  patchNode(id, fn, force) { const s = this.state; const n = s.nodes[id]; if (!n) return; this.commit({ nodes: Object.assign({}, s.nodes, { [id]: fn(n) }) }, force); }
  setPath(id, path, val) { this.patchNode(id, (n) => setPathIn(n, path, val)); }
  setG(k, v) { const s = this.state; this.commit({ theme: Object.assign({}, s.theme, { gbg: Object.assign({}, gbgOf(s.theme), { [k]: v }) }) }); }
  setTheme(k, v) { const s = this.state; this.commit({ theme: Object.assign({}, s.theme, { [k]: v }) }); }
  setMeta(k, v) { const s = this.state; this.commit({ meta: Object.assign({}, s.meta, { [k]: v }) }); }
  canPlace(par, h, ro) { const nodes = this.state.nodes; if (ro) return par == null; const L = par ? lvl(nodes, par) + 1 : 0; return L + h <= MAXLV; }
  rootAfter(par) { const s = this.state; let c = par; let top = par; while (c) { top = c; c = s.nodes[c].par; } return top ? s.root.indexOf(top) + 1 : null; }
  addSection() {
    const s = this.state; const cnt = s.root.filter((id) => s.nodes[id] && s.nodes[id].type === 'section').length;
    const sn = s.sel && s.nodes[s.sel] ? s.sel : null;
    return this.addWidget('section', { par: null, idx: sn ? this.rootAfter(sn) : null, name: 'Section ' + (cnt + 1) });
  }
  applyPreset(id, pr) {
    const s = this.state; const n = s.nodes[id]; if (!n) return; const view = WID[n.type].view; const ps = (PSET[view] || {})[pr] || {};
    this.patchNode(id, (o) => {
      const r = Object.assign({}, o, { p: Object.assign({}, o.p, { preset: pr }), s: Object.assign({}, o.s) });
      ['p', 's'].forEach((t) => { const pt = ps[t] || {}; Object.keys(pt).forEach((k) => { if (pt[k] === undefined) delete r[t][k]; else r[t][k] = pt[k]; }); });
      Object.keys(ps.top || {}).forEach((k) => { r[k] = ps.top[k]; });
      return r;
    }, true);
  }

  addWidget(key, where, assetId) {
    const s = this.state; const nid = s.nid + 1; const id = 'n' + nid; const W = WID[key];
    const over = {};
    if (key === 'asset' && assetId) { const a = s.assets.find((x) => x.id === assetId); if (a) { over.p = { assetId: a.id, src: a.src }; over.w = 150; over.h = Math.round(150 / (a.ar || 1)); over.name = a.name; } }
    if (where && where.name) over.name = where.name;
    const n = mkNode(key, id, over); const ro = !!W.rootOnly;
    let par = null; let idx = null;
    if (where) { par = where.par == null ? null : where.par; idx = where.idx; } else {
      const sn = s.sel && s.nodes[s.sel];
      if (sn) { if (WID[sn.type].cont && this.canPlace(sn.id, W.cont ? 1 : 0, ro)) par = sn.id; else { par = sn.par; idx = (par ? s.nodes[par].kids : s.root).indexOf(sn.id) + 1; } }
    }
    if (ro && par != null) { idx = this.rootAfter(par); par = null; }
    if (!this.canPlace(par, hgt(Object.assign({}, s.nodes, { [id]: n }), id) , ro)) { par = null; idx = null; }
    if (n.pos === 'free') { const sib = par ? s.nodes[par].kids.length : s.root.length; n.x = where && where.x != null ? where.x : 24 + (sib % 6) * 14; n.y = where && where.y != null ? where.y : 24 + (sib % 6) * 14; }
    const r = attach(Object.assign({}, s.nodes, { [id]: n }), s.root, id, par, idx);
    this.commit({ nodes: r.nodes, root: r.root, nid, sel: id, ltab: 'widget', pvn: {}, lp: {}, drag: null }, true);
    return id;
  }
  moveNode(id, par, idx, xy) {
    const s = this.state; const n = s.nodes[id]; if (!n) return;
    if (par === id || (par && isAnc(s.nodes, id, par))) return;
    const oldK = n.par ? s.nodes[n.par].kids : s.root; const oi = oldK.indexOf(id);
    const samePar = (n.par || null) === (par || null);
    let r = detach(s.nodes, s.root, id);
    if (samePar && idx != null && oi < idx) idx -= 1;
    r = attach(r.nodes, r.root, id, par, idx);
    if (xy) r.nodes[id] = Object.assign({}, r.nodes[id], xy);
    this.commit({ nodes: r.nodes, root: r.root, sel: id }, true);
  }
  removeNode(id) {
    const s = this.state; const n = s.nodes[id]; if (!n) return;
    const ids = subtree(s.nodes, id); const r = detach(s.nodes, s.root, id);
    ids.forEach((k) => { delete r.nodes[k]; });
    this.commit({ nodes: r.nodes, root: r.root, sel: ids.indexOf(s.sel) >= 0 ? null : s.sel }, true);
  }
  dupNode(id) { if (id === 'gmusic') return;
    const s = this.state; const n = s.nodes[id]; if (!n) return;
    let nid = s.nid; let nodes = Object.assign({}, s.nodes);
    const cp = (oid, par) => {
      const o = nodes[oid]; const nidn = 'n' + (++nid);
      const c = Object.assign(clone(o), { id: nidn, par: par || null, kids: [] });
      nodes[nidn] = c;
      o.kids.forEach((k) => { const kid = cp(k, nidn); c.kids.push(kid); });
      return nidn;
    };
    const top = cp(id, n.par);
    if (nodes[top].pos === 'free') { nodes[top].x = (n.x || 0) + 18; nodes[top].y = (n.y || 0) + 18; }
    nodes[top].name = (n.name || WID[n.type].label) + ' salinan';
    const ks = n.par ? s.nodes[n.par].kids : s.root; const at = ks.indexOf(id) + 1;
    nodes[top] = Object.assign({}, nodes[top]);
    let root = s.root;
    if (n.par) { const p = nodes[n.par]; const k = p.kids.slice(); k.splice(at, 0, top); nodes[n.par] = Object.assign({}, p, { kids: k }); } else { root = root.slice(); root.splice(at, 0, top); }
    this.commit({ nodes, root, nid, sel: top }, true);
  }
  setPos(id, pos) {
    const s = this.state; const n = s.nodes[id]; if (!n || n.pos === pos) return;
    let el = null; try { el = document.querySelector('[data-nid="' + id + '"]'); } catch (e) {}
    if (pos === 'free') this.patchNode(id, (o) => Object.assign({}, o, { pos, x: el ? el.offsetLeft : 20, y: el ? el.offsetTop : 20, w: el ? el.offsetWidth : (o.w || 160), h: el ? el.offsetHeight : (o.h || 80) }), true);
    else this.patchNode(id, (o) => Object.assign({}, o, { pos, h: undefined }), true);
  }
  reorderZ(id, dir) {
    const s = this.state; const n = s.nodes[id]; if (!n) return; const ks = (n.par ? s.nodes[n.par].kids : s.root).slice(); const i = ks.indexOf(id); const j = clamp(i + dir, 0, ks.length - 1); if (i === j) return;
    ks.splice(i, 1); ks.splice(j, 0, id);
    if (n.par) this.commit({ nodes: Object.assign({}, s.nodes, { [n.par]: Object.assign({}, s.nodes[n.par], { kids: ks }) }) }, true); else this.commit({ root: ks }, true);
  }

  // ---------- engine pointer ----------
  ptrOff() { try { const d = document; d.removeEventListener('pointermove', this.__mv); d.removeEventListener('pointerup', this.__up); d.removeEventListener('pointercancel', this.__up); d.body.style.userSelect = ''; } catch (e) {} try { cancelAnimationFrame(this.__raf); } catch (e) {} this.__raf = 0; }
  startPtr(e, spec) {
    if (e && e.button != null && e.button !== 0) return;
    if (e) { try { e.stopPropagation(); } catch (x) {} try { e.preventDefault(); } catch (x) {} }
    this.ptrOff();
    this.__p = { spec, sx: e.clientX, sy: e.clientY, started: false, ev: e };
    this.__mv = (ev) => this.ptrMove(ev); this.__up = (ev) => this.ptrUp(ev);
    document.addEventListener('pointermove', this.__mv); document.addEventListener('pointerup', this.__up); document.addEventListener('pointercancel', this.__up);
  }
  ptrMove(ev) {
    const P = this.__p; if (!P) return;
    P.ev = ev;
    if (!P.started) { if (Math.hypot(ev.clientX - P.sx, ev.clientY - P.sy) < 4) return; P.started = true; this.dragBegin(P, ev); }
    if (this.__raf) return;
    this.__raf = requestAnimationFrame(() => { this.__raf = 0; if (this.__p) this.dragMove(this.__p, this.__p.ev); });
  }
  ptrUp(ev) {
    const P = this.__p; this.ptrOff(); this.__p = null;
    if (!P) return;
    if (!P.started) {
      const sp = P.spec;
      if (sp.kind === 'node' || sp.kind === 'lnode') this.select(sp.id);
      else if (sp.kind === 'new') this.addWidget(sp.key, null, sp.aid);
      return;
    }
    try { this.dragMove(P, ev); } catch (e) {}
    this.dragEnd(P, ev);
  }
  dragBegin(P, ev) {
    const s = this.state; const sp = P.spec; const nodes = s.nodes;
    try { document.body.style.userSelect = 'none'; } catch (e) {}
    if (sp.kind === 'new') { const W = WID[sp.key]; P.subj = { id: null, h: W.cont ? 1 : 0, ro: !!W.rootOnly }; P.mode = (W.pos === 'free') ? 'newfree' : 'fl'; P.label = sp.label; this.__d = { mode: P.mode }; }
    else if (sp.kind === 'node' || sp.kind === 'lnode') {
      const n = nodes[sp.id]; P.subj = { id: sp.id, h: hgt(nodes, sp.id), ro: !!WID[n.type].rootOnly };
      if (sp.kind === 'node' && n.pos === 'free') { P.mode = 'fmove'; P.n0 = { x: n.x || 0, y: n.y || 0 }; } else P.mode = 'fl';
      P.label = n.name || WID[n.type].label; if (sp.kind === 'node' && s.sel !== sp.id) this.setState({ sel: sp.id });
    } else if (sp.kind === 'resize' || sp.kind === 'rotate') {
      const n = nodes[sp.id]; let el = null; try { el = document.querySelector('[data-nid="' + sp.id + '"]'); } catch (e) {}
      P.mode = sp.kind; P.n0 = { x: n.x || 0, y: n.y || 0, rot: n.rot || 0 }; P.w0 = el ? el.offsetWidth : (n.w || 100); P.h0 = el ? el.offsetHeight : (n.h || 60);
      if (el) { const r = el.getBoundingClientRect(); P.cx = r.left + r.width / 2; P.cy = r.top + r.height / 2; }
    }
    this.__d = { mode: P.mode, tgt: null };
    this.setState({ drag: { mode: P.mode, ghost: P.mode === 'fl' || P.mode === 'newfree', label: P.label || '', gx: ev.clientX, gy: ev.clientY, srcId: P.mode === 'fl' ? (sp.id || null) : null, ind: null, dropIn: null, lid: null, zone: null, lend: false, id: sp.id || null } });
  }
  dragMove(P, ev) {
    const s = this.state; const nodes = s.nodes; const sp = P.spec; const dx = ev.clientX - P.sx; const dy = ev.clientY - P.sy;
    if (P.mode === 'fl' || P.mode === 'newfree') {
      const t = this.targetFrom(ev.clientX, ev.clientY, P, P.mode === 'newfree');
      this.__d = { mode: P.mode, tgt: t };
      this.setState({ drag: { mode: P.mode, ghost: true, label: P.label || '', gx: ev.clientX, gy: ev.clientY, srcId: sp.id || null, ind: t && t.ind ? t.ind : null, dropIn: t && t.src === 'canvas' ? t.par : null, lid: t && t.lid ? t.lid : null, zone: t ? t.zone : null, lend: !!(t && t.lend), id: sp.id || null } });
    } else if (P.mode === 'fmove') {
      const t = this.targetFrom(ev.clientX, ev.clientY, P, true);
      this.__d = { mode: P.mode, tgt: t };
      const n = nodes[sp.id];
      this.setState({ live: { id: sp.id, o: { x: Math.round(P.n0.x + dx), y: Math.round(P.n0.y + dy) } }, drag: { mode: 'fmove', ghost: false, label: '', gx: 0, gy: 0, srcId: null, ind: null, dropIn: t && t.par !== (n.par || null) ? t.par : null, lid: null, zone: null, lend: false, id: sp.id } });
    } else if (P.mode === 'resize') {
      const n = nodes[sp.id]; const hd = sp.h; const sx = hd[0], sy = hd[1];
      const th = (P.n0.rot || 0) * Math.PI / 180; const c = Math.cos(th), sn = Math.sin(th);
      const dxl = dx * c + dy * sn, dyl = -dx * sn + dy * c; const free = n.pos === 'free';
      const k = (!free && (n.al || 'center') === 'center') ? 2 : 1;
      let w = P.w0, h = P.h0;
      if (sx) w = Math.max(24, P.w0 + sx * dxl * k);
      if (sy) h = Math.max(16, P.h0 + sy * dyl);
      if (sx && sy && ((n.type === 'asset' && n.p.lock) || ev.shiftKey)) { const ar = P.w0 / P.h0; const scl = Math.max(w / P.w0, h / P.h0); w = Math.max(24, P.w0 * scl); h = w / ar; }
      const o = {};
      if (free) {
        const lx = sx * (w - P.w0) / 2, ly = sy * (h - P.h0) / 2;
        const cxn = P.n0.x + P.w0 / 2 + lx * c - ly * sn; const cyn = P.n0.y + P.h0 / 2 + lx * sn + ly * c;
        o.w = Math.round(w); o.h = Math.round(h); o.x = Math.round(cxn - w / 2); o.y = Math.round(cyn - h / 2);
      } else { if (sx) o.w = Math.round(w); if (sy) o.h = Math.round(h); }
      this.__live = { id: sp.id, o };
      this.setState({ live: { id: sp.id, o } });
    } else if (P.mode === 'rotate') {
      let r = Math.atan2(ev.clientY - P.cy, ev.clientX - P.cx) * 180 / Math.PI + 90;
      r = ((r + 540) % 360) - 180; r = ev.shiftKey ? Math.round(r / 15) * 15 : Math.round(r);
      this.__live = { id: sp.id, o: { rot: r } };
      this.setState({ live: { id: sp.id, o: { rot: r } } });
    }
  }
  dragEnd(P, ev) {
    const s = this.state; const sp = P.spec; const t = this.__d && this.__d.tgt; const lv = this.__live; this.__live = null;
    this.setState({ drag: null, live: null });
    if (P.mode === 'fl') {
      if (!t) return;
      if (sp.kind === 'new') this.addWidget(sp.key, { par: t.par, idx: t.idx }, sp.aid); else this.moveNode(sp.id, t.par, t.idx);
    } else if (P.mode === 'newfree') {
      if (!t) return;
      const W = WID[sp.key]; const w = sp.key === 'asset' ? 150 : (W.w || 100); const h = sp.key === 'asset' ? 150 : (W.h || 100);
      const xy = this.freeCoords(t.par, ev.clientX, ev.clientY, w, h);
      this.addWidget(sp.key, { par: t.par, idx: null, x: xy.x, y: xy.y }, sp.aid);
    } else if (P.mode === 'fmove') {
      const n = s.nodes[sp.id]; const dx = ev.clientX - P.sx, dy = ev.clientY - P.sy;
      if (t && (t.par || null) !== (n.par || null)) {
        const w = n.w || 100; const h = n.h || 100; const xy = this.freeCoords(t.par, ev.clientX, ev.clientY, w, h, P.sx, P.sy, P.n0);
        this.moveNode(sp.id, t.par, null, xy);
      } else this.patchNode(sp.id, (n0) => Object.assign({}, n0, { x: Math.round(P.n0.x + dx), y: Math.round(P.n0.y + dy) }), true);
    } else if ((P.mode === 'resize' || P.mode === 'rotate') && lv) {
      this.patchNode(sp.id, (n0) => Object.assign({}, n0, lv.o), true);
    }
  }
  freeCoords(par, cx, cy, w, h) {
    let el = null; try { el = document.querySelector('[data-kids="' + (par || 'root') + '"]'); } catch (e) {}
    if (!el) return { x: 24, y: 24 };
    const r = el.getBoundingClientRect();
    return { x: Math.round(cx - r.left - w / 2), y: Math.round(cy - r.top - h / 2) };
  }
  idxIn(parId, cx, cy, ex) {
    const s = this.state; const nodes = s.nodes; const k = parId ? nodes[parId].kids : s.root;
    const horiz = parId && nodes[parId].p.dir === 'row'; let idx = k.length;
    for (let i = 0; i < k.length; i++) {
      const id = k[i]; if (id === ex || nodes[id].pos === 'free') continue;
      const el = document.querySelector('[data-nid="' + id + '"]'); if (!el) continue;
      const r = el.getBoundingClientRect(); const mid = horiz ? r.left + r.width / 2 : r.top + r.height / 2;
      if ((horiz ? cx : cy) < mid) { idx = i; break; }
    }
    return idx;
  }
  fitDepth(par, idx, h, ro) {
    const s = this.state; const nodes = s.nodes;
    while (ro ? par != null : (par ? lvl(nodes, par) + 1 : 0) + h > MAXLV) {
      if (par == null) return null;
      const pn = nodes[par]; idx = (pn.par ? nodes[pn.par].kids : s.root).indexOf(par) + 1; par = pn.par;
    }
    return { par, idx };
  }
  indFor(par, idx, ex, pr) {
    const s = this.state; const nodes = s.nodes; const k = par ? nodes[par].kids : s.root; const horiz = par && nodes[par].p.dir === 'row';
    const flow = []; k.forEach((id, i) => { if (id !== ex && nodes[id].pos !== 'free') flow.push({ id, i }); });
    const rel = (r) => ({ l: r.left - pr.left, t: r.top - pr.top, w: r.width, h: r.height });
    let host = null; try { host = document.querySelector('[data-kids="' + (par || 'root') + '"]'); } catch (e) {}
    if (!flow.length) { if (!host) return null; const r = rel(host.getBoundingClientRect()); return { k: 'box', l: r.l, t: r.t, w: r.w, h: r.h }; }
    let before = null; for (let i = 0; i < flow.length; i++) { if (flow[i].i >= idx) { before = flow[i]; break; } }
    const ref = before || flow[flow.length - 1]; const el = document.querySelector('[data-nid="' + ref.id + '"]'); if (!el) return null;
    const r = rel(el.getBoundingClientRect());
    if (horiz) return { k: 'line', l: (before ? r.l : r.l + r.w) - 2, t: r.t, w: 4, h: r.h };
    return { k: 'line', l: r.l, t: (before ? r.t : r.t + r.h) - 2, w: r.w, h: 4 };
  }
  targetFrom(cx, cy, P, freeMode) {
    const s = this.state; const nodes = s.nodes; const ex = P.spec.id || null; const h = P.subj.h; const ro = !!P.subj.ro; let els = [];
    try { els = document.elementsFromPoint(cx, cy) || []; } catch (e) { return null; }
    const excl = (id) => ex && isAnc(nodes, ex, id);
    for (let i = 0; i < els.length; i++) {
      const el = els[i]; if (!el.closest) continue;
      if (el.closest('[data-lend]')) { const f = this.fitDepth(null, s.root.length, h, ro); return f ? { src: 'layers', par: null, idx: s.root.length, lend: true, zone: 'end' } : null; }
      const r = el.closest('[data-lid]');
      if (r) {
        const id = r.getAttribute('data-lid'); if (excl(id) || !nodes[id]) return null;
        const rc = r.getBoundingClientRect(); const f = (cy - rc.top) / rc.height; const H = nodes[id]; const isC = WID[H.type].cont; let par, idx, zone;
        if (isC && f > 0.28 && f < 0.72) { par = id; idx = H.kids.length; zone = 'in'; } else { par = H.par; idx = (par ? nodes[par].kids : s.root).indexOf(id) + (f >= 0.5 ? 1 : 0); zone = f < 0.5 ? 'before' : 'after'; }
        const ft = this.fitDepth(par, idx, h, ro); if (!ft) return null;
        if (zone === 'in' && ft.par !== id) zone = 'after';
        return { src: 'layers', par: ft.par, idx: ft.idx, lid: id, zone };
      }
    }
    const page = document.getElementById('tcPage'); if (!page) return null;
    const pr = page.getBoundingClientRect();
    if (cx < pr.left - 30 || cx > pr.right + 30 || cy < pr.top - 30 || cy > pr.bottom + 80) return null;
    let hit = null;
    for (let i = 0; i < els.length; i++) {
      const b = els[i].closest && els[i].closest('[data-nid]'); if (!b) continue;
      const id = b.getAttribute('data-nid'); if (excl(id) || !nodes[id]) continue;
      if (nodes[id].pos === 'free' && !WID[nodes[id].type].cont) continue;
      hit = id; break;
    }
    let par = null; let idx = null;
    if (!hit) { par = null; idx = this.idxIn(null, cx, cy, ex); } else {
      const H = nodes[hit]; const el = document.querySelector('[data-nid="' + hit + '"]'); const r = el.getBoundingClientRect(); const isC = WID[H.type].cont;
      const e = Math.min(10, r.height / 4);
      if (freeMode) { par = isC ? hit : H.par; idx = null; } else if (isC && cy > r.top + e && cy < r.bottom - e) { par = hit; idx = this.idxIn(hit, cx, cy, ex); } else {
        par = H.par; const k = par ? nodes[par].kids : s.root; const at = k.indexOf(hit); const horiz = par && nodes[par].p.dir === 'row';
        idx = at + ((horiz ? cx > r.left + r.width / 2 : cy > r.top + r.height / 2) ? 1 : 0);
      }
    }
    const ft = this.fitDepth(par, idx == null ? 0 : idx, h, ro); if (!ft) return null;
    return { src: 'canvas', par: ft.par, idx: idx == null ? null : ft.idx, ind: freeMode ? null : this.indFor(ft.par, ft.idx, ex, pr) };
  }


  // ---------- Daftar Tema ----------
  newTheme() {
    const st = starter(); const id = 'tm' + Date.now().toString(36);
    const next = { nodes: st.nodes, root: st.root, nid: st.nid, sel: null, theme: Object.assign({}, THEME0), meta: { id, nama: 'Tema Baru', tier: 'Gratis', status: 'draft' }, assets: SAMPLE_ASSETS.slice(), aid: 0, undo: [], redo: [], dirty: false, pv: false, dd: null, gbm: false, pvn: {}, lp: {}, savedAt: '' };
    this.setState(next);
    this.saveTheme('draft', Object.assign({}, this.state, next), true);
    this.flash('Tema baru dibuat sebagai draf');
  }
  openTheme(id) {
    const r = mergedThemes().find((x) => x.id === id); if (!r) return;
    let d = null; try { const docs = JSON.parse(localStorage.getItem('undangyah-themedocs') || '{}') || {}; d = docs[id]; } catch (e) {}
    const base = { sel: null, undo: [], redo: [], dirty: false, pv: false, dd: null, gbm: false, pvn: {}, lp: {}, meta: { id: r.id, nama: r.nama, tier: r.tier, status: r.status } };
    if (d && validDoc(d)) { this.setState(Object.assign(base, { nodes: d.nodes, root: d.root, nid: d.nid || 300, theme: Object.assign({}, THEME0, d.theme), assets: SAMPLE_ASSETS.concat(d.assets || []), aid: (d.assets || []).length, savedAt: 'Dimuat dari penyimpanan' })); return; }
    const st = starter();
    this.setState(Object.assign(base, { nodes: st.nodes, root: st.root, nid: st.nid, theme: Object.assign({}, THEME0, { primary: r.primary, accent: r.accent, surface: r.surface, page: r.page, text: r.text, head: r.head, snap: r.snap === false ? 0 : 1, bgfx: r.bgfixed ? 1 : 0 }), assets: SAMPLE_ASSETS.slice(), aid: 0, savedAt: 'Tema bawaan — belum punya desain, mulai dari kerangka dasar' }));
  }
  toList() {
    const s = this.state;
    if (s.dirty) this.saveTheme(s.meta.status, null, true);
    try { localStorage.setItem('undangyah-admin-menu', 'theme'); } catch (e) {}
  }


  // ---------- Pemeriksaan tema (QA) ----------
  qaRun() {
    const s = this.state; const T = s.theme; const nodes = s.nodes; const out = [];
    const add = (lv, text, id) => out.push({ lv, text, id: id || '' });
    const lumn = (h) => { const r = hex2(h).map((v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); }); return 0.2126 * r[0] + 0.7152 * r[1] + 0.0722 * r[2]; };
    const ratio = (a, b) => { try { const x = lumn(a); const y = lumn(b); return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05); } catch (e) { return 21; } };
    const r1 = ratio(T.text, T.page); const r2 = ratio(T.accent, T.page); const r3 = ratio(T.text, T.surface);
    if (r1 < 3) add('err', 'Kontras teks terhadap halaman hanya ' + r1.toFixed(1) + ':1 (minimum 4,5:1). Teks sulit dibaca.'); else if (r1 < 4.5) add('warn', 'Kontras teks terhadap halaman ' + r1.toFixed(1) + ':1, di bawah 4,5:1 untuk teks biasa.');
    if (r3 < 4.5) add('warn', 'Kontras teks di atas warna Surface ' + r3.toFixed(1) + ':1, di bawah 4,5:1.');
    if (r2 < 2) add('warn', 'Warna aksen nyaris menyatu dengan halaman (' + r2.toFixed(1) + ':1). Judul atau ornamen beraksen akan hilang.');
    const secs = s.root.map((id) => nodes[id]).filter((x) => x && x.type === 'section');
    if (!secs.length) add('err', 'Belum ada section. Tamu akan melihat halaman kosong.'); else if (secs.length < 4) add('warn', 'Baru ' + secs.length + ' section. Undangan biasanya punya sampul, mempelai, acara, dan RSVP.');
    secs.forEach((x) => { if (!(x.kids || []).length) add('warn', 'Section “' + (x.name || 'Section') + '” kosong.', x.id); });
    const types = {}; Object.keys(nodes).forEach((k) => { if (nodes[k]) types[nodes[k].type] = (types[nodes[k].type] || 0) + 1; });
    [['event', 'Acara (tanggal, jam, lokasi)'], ['rsvp', 'RSVP'], ['mempelai', 'Mempelai']].forEach((x) => { if (!types[x[0]]) add('warn', 'Belum ada widget ' + x[1] + '. Tema ini tidak bisa menampilkannya ke tamu.'); });
    const first = secs[0]; const gOn = secGateOn(nodes, first); const hasB = !!first && secHasBtn(nodes, first.id);
    if (!gOn) add('warn', 'Cover Gate belum aktif. Tamu langsung melihat seluruh halaman, dan musik tidak bisa mulai otomatis. Nyalakan “Jadikan Cover Gate” di pengaturan section pertama.', first ? first.id : '');
    else if (!hasB) add('warn', 'Cover Gate aktif tetapi section pertama tidak punya tombol. Tamu harus mengetuk bagian mana pun di section itu untuk membuka undangan.', first.id);
    secs.slice(1).forEach((x) => { if (x.p.gate === true) add('warn', 'Cover Gate di section “' + (x.name || 'Section') + '” diabaikan. Hanya section paling atas yang bisa menjadi Cover Gate.', x.id); });
    if (nodes.gmusic && nodes.gmusic.p.on !== false && nodes.gmusic.p.autoplay !== false && !gOn) add('warn', 'Musik Autoplay hanya menyala saat tamu pertama kali menyentuh layar. Dengan Cover Gate, musik menyala tepat saat undangan dibuka.', 'gmusic');
    let linked = 0; let total = 0; const cut = []; const names = [];
    Object.keys(nodes).forEach((id) => {
      const n = nodes[id]; if (!n || !n.type || !n.p) return;
      mbRows(n.type).forEach((path) => { total++; if (mbLink(n, path)) linked++; else if (n.p.mbl && n.p.mbl[mbEnc(path)] === 'none') cut.push([id, n, path]); });
      Object.keys(n.p).forEach((k) => { const v = n.p[k]; if (typeof v === 'string' && /\b(Raka|Rakha|Sinta)\b/i.test(v) && !mbLink(n, 'p.' + k)) names.push([id, n, k]); });
    });
    cut.slice(0, 6).forEach((x) => add('warn', 'Tautan data dimatikan manual di “' + (x[1].name || x[1].type) + '” (' + x[2].replace(/^p\./, '') + '). Tamu akan melihat teks contoh.', x[0]));
    if (cut.length > 6) add('warn', '…dan ' + (cut.length - 6) + ' tautan lain dimatikan manual.');
    names.slice(0, 6).forEach((x) => add('err', 'Teks contoh “' + String(x[1].p[x[2]]).slice(0, 36) + '” di “' + (x[1].name || x[1].type) + '” belum tertaut ke data. Nama Raka/Sinta akan ikut tampil di undangan orang lain.', x[0]));
    if (names.length > 6) add('err', '…dan ' + (names.length - 6) + ' teks contoh lain belum tertaut.');
    add('ok', 'Cakupan tautan data: ' + linked + ' dari ' + total + ' kolom terhubung ke Edit Undangan.');
    if (nodes.gmusic) add('ok', 'Musik Global aktif. Tamu menyalakannya lewat tombol musik.');
    const rank = { err: 0, warn: 1, ok: 2 }; return out.map((x, i) => Object.assign({ i }, x)).sort((a, b) => rank[a.lv] - rank[b.lv] || a.i - b.i);
  }
  // ---------- Pratinjau sebagai tamu (halaman publik dengan tema yang sedang diedit) ----------
  guestRec() {
    const s = this.state; const d = {}; MBD.F.forEach((f) => { d[f[0]] = f[3]; }); const lists = {};
    Object.keys(MBD.L).forEach((tp) => { const L = MBD.L[tp]; lists[tp] = L.rows.map((r, i) => i + 1); L.rows.forEach((r, i) => L.f.forEach((f, j) => { d[tp + (i + 1) + '_' + f[0]] = r[j]; })); });
    lists.gl = [1, 2, 3, 4, 5, 6]; const T = s.theme; const m = s.meta;
    const row = { id: m.id, nama: m.nama, tier: m.tier, primary: T.primary, accent: T.accent, surface: T.surface, page: T.page, text: T.text, head: T.head, bgfixed: null, snap: T.snap !== 0 && T.snap !== false };
    return { v: 1, invId: 'theme-preview', slug: '_theme', judul: m.nama + ' (pratinjau tema)', status: 'publish', publishedAt: new Date().toISOString(), theme: { row, doc: { nodes: s.nodes, root: s.root, nid: s.nid, theme: T, assets: s.assets.filter((x) => x.id.indexOf('sa') !== 0) } }, data: { d, hid: {}, hidSec: {}, lists, photos: {}, lib: [], gset: { layout: 'grid', video: '' }, bgf: {}, order: [] } };
  }
  viewGuest() {
    try { const m = JSON.parse(localStorage.getItem('undangyah-published') || '{}') || {}; m._theme = this.guestRec(); localStorage.setItem('undangyah-published', JSON.stringify(m)); localStorage.setItem('undangyah-view-slug', '_theme'); } catch (e) { this.flash('Gagal menyiapkan pratinjau tamu (penyimpanan browser penuh).'); }
  }
  // ---------- Riwayat versi ----------
  verAll() { try { const m = JSON.parse(localStorage.getItem('undangyah-themever') || '{}'); return m && typeof m === 'object' ? m : {}; } catch (e) { return {}; } }
  verSnap(label) {
    const s = this.state; const id = s.meta && s.meta.id; if (!id) return;
    const doc = { nodes: s.nodes, root: s.root, nid: s.nid, theme: s.theme, assets: s.assets.filter((x) => x.id.indexOf('sa') !== 0) };
    let str = ''; try { str = JSON.stringify([doc.nodes, doc.root, doc.theme]); } catch (e) { return; } let h = 5381; for (let i = 0; i < str.length; i++) h = ((h << 5) + h + str.charCodeAt(i)) | 0; const sig = String(h) + ':' + str.length;
    const all = this.verAll(); let l = all[id] || []; if (l.length && l[0].sig === sig) return;
    l = [{ at: new Date().toISOString(), label, sig, n: Object.keys(s.nodes).length, sec: s.root.length, doc }].concat(l).slice(0, 8);
    for (let k = 0; k < 8; k++) { all[id] = l; try { localStorage.setItem('undangyah-themever', JSON.stringify(all)); return; } catch (e) { l = l.slice(0, Math.max(1, l.length - 1)); if (l.length <= 1) { try { all[id] = l; localStorage.setItem('undangyah-themever', JSON.stringify(all)); } catch (e2) {} return; } } }
  }
  verRestore(i) {
    const id = this.state.meta.id; const l = this.verAll()[id] || []; const v = l[i]; if (!v || !v.doc || !validDoc(v.doc)) { this.flash('Versi ini tidak bisa dipulihkan.'); return; }
    this.verSnap('Sebelum dipulihkan');
    const d = v.doc; const s = this.state;
    this.setState({ nodes: JSON.parse(JSON.stringify(d.nodes)), root: d.root.slice(), nid: d.nid || 300, theme: Object.assign({}, THEME0, d.theme), assets: SAMPLE_ASSETS.concat(d.assets || []), sel: null, dd: null, undo: (s.undo || []).slice(-30), redo: [], dirty: true, verOpen: false });
    this.flash('Versi dipulihkan. Tekan Simpan draf untuk menyimpannya.');
  }

  // ---------- simpan ----------
  saveTheme(status, st, quiet) {
    const s = st || this.state; const m = Object.assign({}, s.meta, { status }); const T = s.theme;
    try {
      const up = s.assets.filter((a) => a.id.indexOf('sa') !== 0);
      localStorage.setItem('undangyah-theme-doc', JSON.stringify({ nodes: s.nodes, root: s.root, nid: s.nid, theme: T, meta: m, assets: up }));
      const cat = JSON.parse(localStorage.getItem('undangyah-themes') || '[]'); const arr = Array.isArray(cat) ? cat : [];
      const gb = gbgOf(T); const secs = s.root.map((id) => s.nodes[id]).filter((x) => x && x.type === 'section').map((x) => ({ id: x.id, nama: x.name || 'Section', ikutGlobal: x.p.gbg !== false && !isCoverSec(x), fotoSendiri: !!x.p.bgimg, tautkan: !!x.p.bgimg && mbLink(x, 'p.bgimg') === 'photo:bgf' }));
      const bgfixed = { global: !!gb.on, jenis: gb.kind, tautkan: gb.link !== false, variabel: GTOK[gb.kind], muted: !!gb.muted, loop: !!gb.loop, section: secs };
      const row = { id: m.id, nama: m.nama, tier: m.tier, status, primary: T.primary, accent: T.accent, surface: T.surface, page: T.page, text: T.text, head: T.head, bgfixed: T.bgfx ? bgfixed : null, metabox: mbSummary(s.nodes, s.root), snap: T.snap !== 0 && T.snap !== false };
      const i = arr.findIndex((x) => x.id === m.id); if (i >= 0) arr[i] = row; else arr.push(row);
      localStorage.setItem('undangyah-themes', JSON.stringify(arr));
      try { const docs = JSON.parse(localStorage.getItem('undangyah-themedocs') || '{}') || {}; docs[m.id] = { nodes: s.nodes, root: s.root, nid: s.nid, theme: T, assets: s.assets.filter((x) => x.id.indexOf('sa') !== 0) }; localStorage.setItem('undangyah-themedocs', JSON.stringify(docs)); } catch (e2) {}
      this.setState({ meta: m, dirty: false, savedAt: (status === 'publish' ? 'Dipublikasikan ' : 'Draf tersimpan ') + new Date().toTimeString().slice(0, 5) });
      try { this.verSnap(status === 'publish' ? 'Dipublikasikan' : 'Draf disimpan'); } catch (e3) {}
      if (!quiet) this.flash(status === 'publish' ? 'Tema dipublikasikan — tampil di tab Tema pengguna' : 'Draf tersimpan');
    } catch (e) { this.flash('Penyimpanan gagal (penyimpanan browser penuh/diblokir)'); }
  }
  onFile(e) {
    const files = e && e.target && e.target.files ? Array.prototype.slice.call(e.target.files) : [];
    files.forEach((f) => {
      if (!/^image\//.test(f.type)) return;
      const fr = new FileReader();
      fr.onload = () => {
        const src = String(fr.result); const im = new Image();
        const done = (ar) => { const s = this.state; const id = 'up' + (s.aid + 1) + '_' + Date.now().toString(36); this.setState({ assets: s.assets.concat([{ id, name: f.name.replace(/\.[^.]+$/, '').slice(0, 28), ar, src }]), aid: s.aid + 1, ltab: 'asset' }); };
        im.onload = () => done(im.naturalWidth && im.naturalHeight ? im.naturalWidth / im.naturalHeight : 1); im.onerror = () => done(1); im.src = src;
      };
      fr.readAsDataURL(f);
    });
    try { e.target.value = ''; } catch (x) {}
  }

  // ---------- kontrol Properti (generik) ----------
  mkCtl(spec, bind, T, s, ddId, ctx) {
    const kind = spec[0];
    const F = (flag, o) => Object.assign({ kSub: false, kNote: false, kText: false, kArea: false, kNum: false, kRange: false, kSw: false, kSeg: false, kDd: false, kCol: false, kPair: false, kRep: false, kBtn: false, kPre: false, kMh: false, label: '' }, { [flag]: true }, o);
    const numCtl = (path, label, o) => {
      const v = bind.get(path);
      return { label, v: v == null ? '' : v, ph: o.ph || '', u: o.u || '', min: o.min != null ? o.min : '', max: o.max != null ? o.max : '', step: o.step || 1, set: (e) => { const t = String(e.target.value).trim(); const f = parseFloat(t); bind.set(path, t === '' || isNaN(f) ? undefined : f); } };
    };
    if (kind === 'sub') return F('kSub', { label: spec[1] });
    if (kind === 'note') return F('kNote', { label: spec[1] });
    const path = spec[1]; const label = spec[2]; const o = spec[3] || {};
    if (kind === 'btn') return F('kBtn', { label: spec[1], fn: spec[2], ic: IC[o.ic || 'copy'] });
    if (kind === 'pair') { const a = spec[1][0]; const b = spec[1][1]; return F('kPair', { a: numCtl(a[1], a[2], a[3] || {}), b: numCtl(b[1], b[2], b[3] || {}) }); }
    const cur = bind.get(path);
    if (kind === 'pre' && ctx) {
      const n = ctx.n; const view = WID[n.type].view; const list = PRES[view] || []; const curP = pid(n, view);
      const items = list.map((x) => {
        const ps = (PSET[view] || {})[x[0]] || {}; const on = x[0] === curP;
        const nc = Object.assign({}, n, ps.top || {}, { p: Object.assign({}, n.p, ps.p || {}, { preset: x[0] }), s: Object.assign({}, n.s, ps.s || {}) });
        let vw; try { vw = V[view](nc, T, ctx.E); } catch (e) { vw = { k: 'simple', cs: '', text: '' }; }
        const fixed = nc.pos === 'free' || view === 'asset';
        const iw = fixed ? Math.max(24, nc.w || 150) : 460; const ih = fixed ? Math.max(24, nc.h || 150) : 0;
        const sc = fixed ? Math.min(1.5, 100 / ih, 124 / iw) : 0.28;
        return {
          label: x[1], on, pick: () => bind.preset(x[0]),
          cell: 'display:flex;flex-direction:column;gap:6px;padding:5px;border-radius:12px;border:2px solid ' + (on ? BLUE : '#E2E5E9') + ';background:' + (on ? '#F4F8FF' : '#FFFFFF') + ';cursor:pointer;box-sizing:border-box;min-width:0;text-align:left;' + (on ? 'box-shadow:0 0 0 2px #BFD3FF' : ''),
          box: 'position:relative;height:124px;overflow:hidden;border-radius:8px;background:' + T.page + ';border:1px solid #EDEFF2;box-sizing:border-box',
          inner: 'position:absolute;pointer-events:none;width:' + iw + 'px;' + (ih ? 'height:' + ih + 'px;left:50%;top:50%;transform:translate(-50%,-50%) scale(' + sc + ');transform-origin:center center' : 'left:0;top:0;transform:scale(' + sc + ');transform-origin:0 0'),
          cap: 'font-size:11px;line-height:14px;font-weight:' + (on ? 800 : 600) + ';color:' + (on ? BLUE : '#454B55') + ';overflow:hidden;text-overflow:ellipsis;white-space:nowrap',
          kSimple: vw.k === 'simple', cls: vw.cls || '', kRows: vw.k === 'rows', kAsset: vw.k === 'asset', kCont: vw.k === 'cont', cs: vw.cs || '', text: vw.text || '', rows: vw.rows || [], src: vw.src || '',
        };
      });
      return F('kPre', { label, items });
    }
    if (kind === 'mh') {
      const d = o.d || 0; const v = cur == null ? d : cur; const unit = bind.get(path + 'u') === 'vh' ? 'vh' : 'px';
      const to = (u) => () => { if (u === unit) return; bind.patch((x) => { const key = path.split('.')[1]; const v0 = x.p[key] != null ? x.p[key] : d; const nv = u === 'vh' ? Math.round(v0 / VH * 10) / 10 : Math.round(v0 * VH); return Object.assign({}, x, { p: Object.assign({}, x.p, { [key + 'u']: u, [key]: nv }) }); }); };
      return F('kMh', { label, v, ph: String(d), step: unit === 'vh' ? 1 : 10, hint: unit === 'vh' ? 'vh = persen tinggi layar pengunjung (di editor 1vh ≈ ' + VH + 'px)' : 'px = tinggi tetap dalam piksel', set: (e) => { const t = String(e.target.value).trim(); const f = parseFloat(t); bind.set(path, t === '' || isNaN(f) ? undefined : Math.max(0, f)); }, units: ['px', 'vh'].map((u) => ({ label: u, on: unit === u, pick: to(u), sty: 'flex:none;min-width:44px;min-height:38px;padding:0 8px;border-radius:10px;box-sizing:border-box;font-size:12px;line-height:15px;font-weight:800;text-align:center;display:flex;align-items:center;justify-content:center;' + (unit === u ? 'background:#E3ECFD;color:' + BLUE + ';box-shadow:0 2px 0 #BFD3FF' : 'background:#F4F5F7;color:#666C76') })) });
    }
    if (kind === 'text' || kind === 'date') return F('kText', { label, v: cur == null ? '' : cur, ph: o.ph || '', type: kind === 'date' ? 'date' : 'text', set: (e) => bind.set(path, e.target.value) });
    if (kind === 'area') return F('kArea', { label, v: cur == null ? '' : cur, rows: o.rows || 3, set: (e) => bind.set(path, e.target.value) });
    if (kind === 'num') return F('kNum', numCtl(path, label, o));
    if (kind === 'range') { const v = cur == null ? (o.min || 0) : cur; return F('kRange', { label, v, vs: String(v) + (o.u || ''), min: o.min != null ? o.min : 0, max: o.max != null ? o.max : 100, step: o.step || 1, set: (e) => { const f = parseFloat(e.target.value); bind.set(path, isNaN(f) ? undefined : f); } }); }
    if (kind === 'sw') { const on = o.on && ctx ? !!o.on(ctx.n, s) : (o.dflt ? (cur == null ? true : !!cur) : !!cur); return F('kSw', { label, on, toggle: () => bind.set(path, !on), trk: 'flex:none;width:44px;height:26px;border-radius:13px;padding:3px;box-sizing:border-box;display:flex;align-items:center;justify-content:' + (on ? 'flex-end' : 'flex-start') + ';background:' + (on ? BLUE : '#C9CED6') }); }
    if (kind === 'seg') return F('kSeg', { label, opts: o.o.map((x) => { const on = cur === x[0]; return { label: x[1], on, pick: () => bind.set(path, x[0]), sty: 'flex:1 1 0;min-width:0;min-height:34px;padding:0 6px;border-radius:10px;box-sizing:border-box;font-size:12px;line-height:15px;font-weight:700;text-align:center;display:flex;align-items:center;justify-content:center;' + (on ? 'background:#E3ECFD;color:' + BLUE + ';box-shadow:0 2px 0 #BFD3FF' : 'background:#F4F5F7;color:#666C76') }; }) });
    if (kind === 'dd' || kind === 'asset') {
      const key = ddId + ':' + path; const open = s.dd === key;
      const curV = cur == null ? (o.d != null ? o.d : '') : cur;
      const items = []; const flat = [];
      const addOpt = (x) => {
        flat.push(x); const on = curV === x[0];
        items.push({ isHead: false, notHead: true, label: x[1], on, pick: () => { if (kind === 'asset' && path === 'p.assetId') bind.setAsset(x[0]); else bind.set(path, x[0] === '' ? undefined : x[0]); this.setState({ dd: null }); }, sty: 'display:flex;align-items:center;min-height:34px;padding:0 12px;border-radius:10px;font-size:13px;line-height:17px;font-weight:600;box-sizing:border-box;width:100%;' + (on ? 'background:#E3ECFD;color:' + BLUE : 'color:#262A31') });
      };
      if (kind === 'asset') [['', 'Tanpa gambar']].concat(s.assets.map((a) => [a.id, a.name])).forEach(addOpt);
      else if (o.g) o.g.forEach((g) => { items.push({ isHead: true, notHead: false, label: g[0], on: false, pick: () => {}, sty: '' }); g[1].forEach(addOpt); });
      else (o.o || []).forEach(addOpt);
      const hit = flat.find((x) => x[0] === curV);
      const acts = (o.acts || []).map((a) => ({ d: a.d, tip: a.tip, fn: a.fn }));
      return F('kDd', { label, cur: hit ? hit[1] : (curV === '' ? '—' : String(curV)), open, toggle: () => this.setState({ dd: open ? null : key }), items, hasActs: acts.length > 0, acts, chevSty: 'flex:none;transition:transform 120ms;' + (open ? 'transform:rotate(180deg)' : '') });
    }
    if (kind === 'col') {
      const eff = cur !== undefined ? cur : (o.d || ''); const res = col(eff, T) || ''; const hex = /^#[0-9a-fA-F]{6}$/.test(res) ? res : (/^#[0-9a-fA-F]{3}$/.test(res) ? '#' + res.slice(1).split('').map((c) => c + c).join('') : '#FFFFFF');
      const tokName = typeof eff === 'string' && eff.indexOf('tok:') === 0 ? (TOKS.find((t) => t[0] === eff) || ['', eff])[1] : (eff || 'Bawaan');
      return F('kCol', { label, hex, v: tokName, setHex: (e) => bind.set(path, String(e.target.value).toUpperCase()), chipSty: 'flex:none;width:32px;height:32px;border-radius:10px;border:2px solid #E2E5E9;box-sizing:border-box;background:' + (res || '#FFFFFF'), sw: TOKS.map((t) => ({ tip: t[1], pick: () => bind.set(path, t[0]), sty: 'flex:none;width:24px;height:24px;border-radius:12px;box-sizing:border-box;border:2px solid ' + (eff === t[0] ? BLUE : '#E2E5E9') + ';box-shadow:' + (eff === t[0] ? '0 0 0 2px #BFD3FF' : 'none') + ';background:' + col(t[0], T) })), clear: () => bind.set(path, undefined), none: () => bind.set(path, 'transparent') });
    }
    if (kind === 'rep') {
      const items = Array.isArray(cur) ? cur : []; const fs = o.f;
      const upd = (arr) => bind.set(path, arr);
      return F('kRep', { label, canAdd: items.length < 12, addLabel: 'Tambah ' + (o.name || 'item').toLowerCase(), add: () => upd(items.concat([Object.assign({}, o.add)])), rows: items.map((it, i) => ({ n: (o.name || 'Item') + ' ' + (i + 1), canDel: items.length > 1, del: () => upd(items.filter((x, j) => j !== i)), up: () => { if (i > 0) { const a = items.slice(); const t = a[i - 1]; a[i - 1] = a[i]; a[i] = t; upd(a); } }, f: fs.map((f) => ({ label: f[1], v: it[f[0]] == null ? '' : it[f[0]], set: (e) => { const v = e.target.value; upd(items.map((x, j) => (j === i ? Object.assign({}, x, { [f[0]]: v }) : x))); } })) })) });
    }
    return F('kNote', { label: '' });
  }

  appFields(n, par) {
    const W = WID[n.type]; const free = n.pos === 'free'; const A = [];
    xFields(n).forEach((x) => A.push(x));
    A.push(['sub', 'Tata letak']);
    A.push(['seg', 'pos', 'Posisi', { o: [['flow', 'Mengalir'], ['free', 'Bebas (drag di mana saja)']] }]);
    if (free) { A.push(['pair', [['num', 'x', 'X', { u: 'px' }], ['num', 'y', 'Y', { u: 'px' }]]]); A.push(['pair', [['num', 'w', 'Lebar', { u: 'px' }], ['num', 'h', 'Tinggi', { u: 'px' }]]]); A.push(['num', 'z', 'Z-index', { min: 0, max: 99 }]); } else {
      A.push(['pair', [['num', 'w', 'Lebar (kosong = penuh)', { min: 24 }], ['num', 'h', 'Tinggi (kosong = auto)', { min: 16 }]]]);
      if (n.w) A.push(['seg', 'al', 'Perataan blok', { o: SEGALIGN }]);
      A.push(['pair', [['num', 'mt', 'Margin atas'], ['num', 'mb', 'Margin bawah']]]);
      if (par && par.p.dir === 'row') A.push(['sw', 'fill', 'Isi ruang (flex)']);
    }
    A.push(['range', 'rot', 'Rotasi', { min: -180, max: 180, u: '°' }]);
    if (!W.noStyle) {
      A.push(['sub', 'Gaya']);
      A.push(['col', 's.bg', 'Warna latar']);
      A.push(['pair', [['num', 's.r', 'Radius', { min: 0 }], ['num', 's.pad', 'Padding', { min: 0 }]]]);
      A.push(['num', 's.bw', 'Tebal border', { min: 0, max: 12 }]);
      if ((n.s.bw || 0) > 0) A.push(['col', 's.bc', 'Warna border', { d: 'tok:accent' }]);
      A.push(['dd', 's.sh', 'Bayangan', { o: [['none', 'Tidak ada'], ['soft', 'Lembut'], ['medium', 'Sedang'], ['strong', 'Kuat']], d: 'none' }]);
    } else if (W.appx) { A.push(['sub', 'Gaya']); W.appx.forEach((x) => A.push(x)); }
    A.push(['range', 's.op', 'Opasitas', { min: 0, max: 100, u: '%' }]);
    if (W.typo && !W.noTypo) {
      A.push(['sub', 'Tipografi']);
      A.push(['seg', 's.ff', 'Font', { o: [['head', 'Judul'], ['body', 'Isi'], ['script', 'Script']] }]);
      A.push(['col', 's.color', 'Warna teks']);
      A.push(['pair', [['num', 's.fs', 'Ukuran (px)', { min: 8, max: 120 }], ['num', 's.lh', 'Tinggi baris', { min: 1, max: 3, step: 0.1 }]]]);
      A.push(['seg', 's.fw', 'Ketebalan', { o: [[400, 'Normal'], [600, 'Tebal'], [800, 'Sangat']] }]);
      A.push(['seg', 's.ta', 'Rata teks', { o: SEGALIGN }]);
      A.push(['num', 's.ls', 'Jarak huruf (px)', { step: 0.5 }]);
      A.push(['sw', 's.it', 'Miring']); A.push(['sw', 's.tt', 'HURUF BESAR']);
    }
    return A;
  }

  renderVals() {
    const s = this.state; const T = s.theme; const nodes = s.nodes; const pv = s.pv; const D = s.drag; const live = s.live;
    const amap = {}; s.assets.forEach((a) => { amap[a.id] = a; });
    const E = { pv, vh: s.vh || 800, src: (aid) => (aid && amap[aid] ? amap[aid].src : '') };
    const sel = s.sel && nodes[s.sel] ? s.sel : null;
    const snapOn = !!pv && T.snap !== 0 && T.snap !== false;
    const getN = (id) => (live && live.id === id ? Object.assign({}, nodes[id], live.o) : nodes[id]);
    const HND = [['nw', -1, -1, 0, 0, 'nwse-resize'], ['n', 0, -1, 50, 0, 'ns-resize'], ['ne', 1, -1, 100, 0, 'nesw-resize'], ['e', 1, 0, 100, 50, 'ew-resize'], ['se', 1, 1, 100, 100, 'nwse-resize'], ['s', 0, 1, 50, 100, 'ns-resize'], ['sw', -1, 1, 0, 100, 'nesw-resize'], ['w', -1, 0, 0, 50, 'ew-resize']];
    const handles = (id) => HND.map((h) => ({ t: '', s: 'position:absolute;left:' + h[3] + '%;top:' + h[4] + '%;width:11px;height:11px;margin:-5.5px 0 0 -5.5px;background:#FFFFFF;border:2px solid ' + BLUE + ';border-radius:3px;box-sizing:border-box;cursor:' + h[5] + ';pointer-events:auto;z-index:70;touch-action:none', down: (e) => this.startPtr(e, { kind: 'resize', id, h: [h[1], h[2]] }) }))
      .concat([{ t: '↻', s: 'position:absolute;left:50%;top:-36px;width:22px;height:22px;margin-left:-11px;background:' + BLUE + ';color:#FFFFFF;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:13px;line-height:1;cursor:grab;pointer-events:auto;z-index:70;box-shadow:0 2px 6px rgba(0,0,0,.25);touch-action:none', down: (e) => this.startPtr(e, { kind: 'rotate', id }) }]);
    const EMPTY = [];

    const vm = (id, lv, parN) => {
      const n = getN(id); if (!n) return null;
      const W = WID[n.type]; const view = V[W.view](n, T, E);
      const free = n.pos === 'free'; const isSel = sel === id && !pv;
      const dragging = D && D.srcId === id && D.mode === 'fl';
      const pRow = parN && parN.p && parN.p.dir === 'row';
      const bs = { position: free ? 'absolute' : 'relative', 'box-sizing': 'border-box', display: n.hidden ? 'none' : 'block', transform: n.rot ? 'rotate(' + n.rot + 'deg)' : '', opacity: dragging ? 0.35 : (n.s && n.s.op != null ? n.s.op / 100 : ''), 'min-width': 0 };
      if (free) { bs.left = px(n.x || 0); bs.top = px(n.y || 0); bs.width = px(n.w || 100); bs.height = px(n.h || 100); bs['z-index'] = n.z != null ? n.z : 2; } else {
        bs.width = px(n.w); bs.height = px(n.h); bs['margin-top'] = px(n.mt); bs['margin-bottom'] = px(n.mb);
        if (n.w) bs['align-self'] = { left: 'flex-start', center: 'center', right: 'flex-end' }[n.al || 'center'];
        if (pRow) bs.flex = n.fill ? '1 1 0' : (n.w ? '0 0 auto' : '0 1 auto');
        if (isSel) bs['z-index'] = 3;
      }
      if (snapOn && lv === 0 && n.type === 'section') { bs['scroll-snap-align'] = 'start'; bs['scroll-snap-stop'] = 'always'; }
      const playing = pv || s.pvn[id] !== undefined; const par2 = (s.pvn[id] || 0) % 2 ? 'a' : 'b';
      const ai = n.a.in; const al = n.a.loop;
      const a1 = 'display:block;width:100%;height:100%;' + (playing && ai.preset !== 'none' ? 'animation:tcin-' + ai.preset + '-' + par2 + ' ' + (ai.dur || 0.8) + 's ' + (EASE_CSS[ai.ease] || 'ease') + ' ' + (ai.delay || 0) + 's both' : '');
      const a2 = 'display:block;width:100%;height:100%;' + ((playing || s.lp[id]) && al.preset !== 'none' ? 'animation:tcl-' + al.preset + '-' + par2 + ' ' + (LOOP_DUR[al.preset] || 4) + 's ' + (al.preset === 'spin' || al.preset === 'spin-r' ? 'linear' : 'ease-in-out') + ' infinite' : '');
      const canK = W.cont && lv < MAXLV;
      return {
        id, fr: free ? '1' : '', box: css(bs), a1, a2, hc: [pv && n.ix.hover !== 'none' ? 'tchv-' + n.ix.hover : '', view.cls || ''].filter(Boolean).join(' '), cs: view.cs || '', text: view.text || '', rows: view.rows || EMPTY, src: view.src || '',
        kSimple: view.k === 'simple', kRows: view.k === 'rows', kAsset: view.k === 'asset', kCont: view.k === 'cont' && canK,
        k: canK ? n.kids.map((c) => vm(c, lv + 1, n)).filter(Boolean) : EMPTY, empty: !!W.cont && !n.kids.length,
        dropIn: !!(D && D.dropIn === id), down: pv ? (e) => {} : (e) => this.startPtr(e, { kind: 'node', id }),
      };
    };
    const rootVm = s.root.map((id) => vm(id, 0, null)).filter(Boolean);

    // ---- panel kiri: palette ----
    const q = (s.q || '').trim().toLowerCase();
    const groups = PALETTE.map((g, gi) => {
      const items = g[1].filter((k) => !q || WID[k].label.toLowerCase().indexOf(q) >= 0).map((k) => ({ label: WID[k].label, d: IC[WID[k].ic], down: (e) => this.startPtr(e, { kind: 'new', key: k, label: WID[k].label }) }));
      const open = !!q || s.fold['g' + gi] !== true;
      return { title: g[0], count: items.length, show: items.length > 0, open, toggle: () => this.setState({ fold: Object.assign({}, s.fold, { ['g' + gi]: open ? true : false }) }), chevSty: 'flex:none;transition:transform 120ms;' + (open ? 'transform:rotate(180deg)' : ''), items: open ? items : EMPTY };
    });
    const anyW = groups.some((g) => g.show);
    const ltabs = [['widget', 'Widget', IC.widget], ['asset', 'Asset', IC.asset], ['tema', 'Tema', IC.theme]].map((t) => { const on = s.ltab === t[0]; return { label: t[1], d: t[2], on, pick: () => this.setState({ ltab: t[0] }), sty: 'flex:1 1 0;min-width:0;min-height:44px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;font-size:11px;line-height:13px;font-weight:700;letter-spacing:.4px;text-transform:uppercase;box-sizing:border-box;border-bottom:3px solid ' + (on ? BLUE : 'transparent') + ';color:' + (on ? BLUE : '#666C76') }; });
    const assetItems = s.assets.map((a) => ({ name: a.name, src: a.src, up: a.id.indexOf('sa') !== 0, rm: (e) => { try { e.stopPropagation(); } catch (x) {} this.setState({ assets: s.assets.filter((x) => x.id !== a.id) }); }, down: (e) => this.startPtr(e, { kind: 'new', key: 'asset', aid: a.id, label: a.name }) }));
    const pickFile = () => { try { document.getElementById('tcFile').click(); } catch (e) {} };

    // ---- Tema ----
    const tbind = { get: (k) => (k === 'nama' || k === 'tier' || k === 'status' ? s.meta[k] : T[k]), set: (k, v) => (k === 'nama' || k === 'tier' ? this.setMeta(k, v) : this.setTheme(k, v === undefined ? THEME0[k] : v)) };
    const themeCtls = [
      ['sub', 'Info tema'], ['text', 'nama', 'Nama tema'], ['seg', 'tier', 'Paket', { o: [['Gratis', 'Gratis'], ['Premium', 'Premium'], ['Deluxe', 'Deluxe']] }],
      ['sub', 'Warna global'], ['col', 'primary', 'Primary'], ['col', 'accent', 'Aksen'], ['col', 'surface', 'Surface (kartu)'], ['col', 'page', 'Latar halaman'], ['col', 'text', 'Teks'],
      ['sub', 'Tipografi global'], ['seg', 'head', 'Font judul', { o: [['playfair', 'Serif'], ['jakarta', 'Sans'], ['script', 'Script']] }], ['range', 'base', 'Ukuran teks dasar', { min: 12, max: 20, u: 'px' }],
      ['sub', 'Background Fixed untuk pengguna'], ['sw', 'bgfx', 'Aktifkan fitur Background Fixed'], ['note', 'Menyala: tema ini menampilkan kartu Background Fixed di halaman Edit Undangan, tempat pengguna mengatur gambar, video, atau slideshow. Mati: kartu itu disembunyikan untuk tema ini.'], ['sub', 'Perpindahan section'], ['sw', 'snap', 'Snap antar section (smooth)'], ['note', 'Saat menggulir, tampilan langsung berpindah halus ke section berikutnya. Aktif di mode Pratinjau dan di undangan pengguna. Tip: beri section tinggi 100vh agar tiap section memenuhi layar.'],
    ].map((sp) => this.mkCtl(sp, tbind, T, s, 'th'));
    const palettes = PALETTES.map((p) => ({ name: p[0], sw: [p[1].primary, p[1].accent, p[1].surface, p[1].text].map((c) => ({ c: 'flex:none;width:18px;height:18px;border-radius:9px;border:1.5px solid rgba(0,0,0,.12);background:' + c })), pick: () => this.commit({ theme: Object.assign({}, T, p[1]) }, true) }));

    // ---- Properti ----
    let P = { canAct: true, on: false, name: '', setName: () => {}, ic: '', back: () => {}, dup: () => {}, del: () => {}, sections: EMPTY, typeLabel: '', hasUp: false, up: () => {}, zf: () => {}, zb: () => {}, isFree: false };
    if (sel && !pv) {
      const n = getN(sel); const W = WID[n.type]; const parN = n.par ? nodes[n.par] : null;
      const bind = {
        get: (path) => (path === 'pos' ? n.pos : getPath(n, path)),
        set: (path, val) => { if (path === 'pos') this.setPos(sel, val); else this.setPath(sel, path, val); },
        patch: (fn) => this.patchNode(sel, fn, true), preset: (pr) => this.applyPreset(sel, pr),
        setAsset: (aid) => { const a = amap[aid]; this.patchNode(sel, (o) => setPathIn(setPathIn(o, 'p.assetId', aid || ''), 'p.src', a ? a.src : '')); },
      };
      const mk = (specs) => specs.map((sp) => { if (sp[3] && sp[3].if && !sp[3].if(n) && !noneShow(n, sp)) return null; if (noneHide(n, sp)) return null; return this.mkCtl(sp, bind, T, s, sel, { n, E }); }).filter(Boolean);
      const playNow = () => this.setState({ pvn: Object.assign({}, s.pvn, { [sel]: (s.pvn[sel] || 0) + 1 }) });
      const anim = [
        ['sub', 'Animasi 1 · Masuk / Keluar'],
        ['dd', 'a.in.preset', 'Preset', { g: IN_GROUPS, acts: [{ d: IC.play, tip: 'Putar', fn: playNow }, { d: IC.reset, tip: 'Reset animasi', fn: () => { const a = clone(n.a); a.in = clone(A0.in); const pv2 = Object.assign({}, s.pvn); delete pv2[sel]; this.setState({ pvn: pv2 }); this.setPath(sel, 'a', a); } }] }],
        ['dd', 'a.in.trigger', 'Trigger', { o: TRIGGERS }],
        ['pair', [['num', 'a.in.delay', 'Delay (s)', { step: 0.1, min: 0 }], ['num', 'a.in.dur', 'Durasi (s)', { step: 0.1, min: 0.1 }]]],
        ['dd', 'a.in.ease', 'Easing', { o: EASES }],
        ['sub', 'Viewport trigger'], ['sw', 'a.in.replay', 'Replay tiap scroll'], ['pair', [['num', 'a.in.margin', 'Margin (px)'], ['num', 'a.in.amount', 'Amount (%)', { min: 0, max: 100 }]]],
        ['note', 'Replay memutar ulang animasi setiap elemen masuk viewport. Margin menggeser batas pemicu; Amount = persen elemen yang terlihat sebelum animasi mulai.'],
        ['sub', 'Animasi 2 · Looping (berjalan bersamaan)'],
        ['dd', 'a.loop.preset', 'Preset', { o: LOOP_LIST, acts: [{ d: IC.play, tip: 'Putar / hentikan', fn: () => this.setState({ lp: Object.assign({}, s.lp, { [sel]: !s.lp[sel] }) }) }, { d: IC.reset, tip: 'Reset', fn: () => { const a = clone(n.a); a.loop = { preset: 'none' }; this.setPath(sel, 'a', a); } }, { d: IC.copy, tip: 'Salin animasi', fn: () => { this.setState({ clip: clone(n.a) }); this.flash('Animasi disalin'); } }] }],
        ['note', 'Animasi 2 berjalan terus selama elemen tampil dan aktif bersamaan dengan Animasi 1.'],
      ];
      if (s.clip) anim.push(['btn', 'Tempel animasi tersalin', () => this.setPath(sel, 'a', clone(s.clip)), { ic: 'copy' }]);
      const ix = [['dd', 'ix.hover', 'Efek hover', { o: HOVERS }], ['dd', 'ix.click', 'Saat diklik', { o: CLICKS }], ['text', 'ix.url', 'Tautan URL', { ph: 'https://', if: (nn) => nn.ix.click === 'link' }], ['text', 'ix.anchor', 'ID bagian tujuan', { ph: 'mis. rsvp', if: (nn) => nn.ix.click === 'scroll' }], ['note', 'Efek hover tampil saat mode Pratinjau menyala.']];
      const NODUMMY = ['section', 'container', 'spacer', 'divider', 'asset', 'orn'];
      const konten = () => {
        const MB = mbSpecs(n); const f = (Array.isArray(W.fields) ? W.fields : []).filter((x) => !MB.hide[x[1]]);
        if (NODUMMY.indexOf(n.type) >= 0) { const j = f.length && f[0][0] === 'pre' ? 1 : 0; return f.slice(0, j).concat(MB.specs, f.slice(j)); }
        const hasPhoto = f.some((x) => x[0] === 'asset');
        const note = ['note', hasPhoto ? 'Foto di sini hanya contoh / bawaan tema. Di halaman Edit Undangan pengguna bisa mengganti fotonya sendiri (unggah, pilih dari galeri, potong). Data teks juga hanya contoh dan diisi pengguna di Edit Undangan.' : 'Data pada widget ini hanya contoh (dummy) supaya desain terlihat. Data asli diisi pengguna di halaman Edit Undangan.'];
        const i = f.length && f[0][0] === 'pre' ? 1 : 0; return f.slice(0, i).concat([note], MB.specs, f.slice(i));
      };
      const defs = [['k', 'Konten', konten], ['a', 'Appearance', () => this.appFields(n, parN)], ['n', 'Animation', () => anim], ['i', 'Interactions', () => ix]];
      const sections = (n.id === 'gmusic' ? defs.slice(0, 1) : defs).map((d) => { const open = s.sopen[d[0]]; return { title: d[1], open, toggle: () => this.setState({ sopen: Object.assign({}, s.sopen, { [d[0]]: !open }) }), chevSty: 'flex:none;transition:transform 120ms;' + (open ? 'transform:rotate(180deg)' : ''), ctls: open ? mk(d[2]()) : EMPTY }; });
      P = { canAct: n.id !== 'gmusic', on: true, name: n.name || '', ph: W.label, setName: (e) => this.setPath(sel, 'name', e.target.value), ic: IC[W.ic], typeLabel: n.id === 'gmusic' ? 'Musik Global · sticky' : W.label + (n.pos === 'free' ? ' · bebas' : ''), back: () => this.setState({ sel: null, dd: null }), dup: () => this.dupNode(sel), del: () => this.removeNode(sel), sections, hasUp: !!n.par, up: () => this.select(n.par), isFree: n.pos === 'free', zf: () => this.reorderZ(sel, 1), zb: () => this.reorderZ(sel, -1), hid: !!n.hidden, hideToggle: () => this.setPath(sel, 'hidden', !n.hidden) };
    }

    // ---- Layers ----
    const lay = []; const walk = (ids, lv) => ids.forEach((id) => {
      const n = nodes[id]; if (!n) return; const W = WID[n.type]; const hasK = !!(W.cont && n.kids.length); const open = s.lopen[id] !== false; const isSel = sel === id;
      let sh = '';
      if (D && D.lid === id) { if (D.zone === 'before') sh = 'box-shadow:inset 0 3px 0 ' + BLUE + ';'; else if (D.zone === 'after') sh = 'box-shadow:inset 0 -3px 0 ' + BLUE + ';'; else if (D.zone === 'in') sh = 'background:#D6E4FF;outline:2px dashed ' + BLUE + ';outline-offset:-2px;'; }
      lay.push({
        id, name: n.name || W.label, d: IC[W.ic], hasK, caret: 'flex:none;width:20px;height:24px;display:flex;align-items:center;justify-content:center;color:#9AA0A9;transition:transform 120ms;' + (open ? 'transform:rotate(90deg)' : ''), toggleOpen: (e) => { try { e.stopPropagation(); } catch (x) {} this.setState({ lopen: Object.assign({}, s.lopen, { [id]: !open }) }); },
        rs: 'display:flex;align-items:center;gap:6px;height:36px;padding:0 6px 0 ' + (6 + lv * 14) + 'px;box-sizing:border-box;border-radius:10px;cursor:pointer;touch-action:none;' + (isSel ? 'background:#E3ECFD;color:' + BLUE + ';' : 'color:#262A31;') + (D && D.srcId === id ? 'opacity:.4;' : '') + sh,
        nameSty: 'flex:1 1 0;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:13px;line-height:17px;font-weight:' + (n.type === 'section' ? 800 : 600) + ';' + (n.hidden ? 'opacity:.45;text-decoration:line-through' : ''),
        eye: n.hidden ? IC.eyeoff : IC.eye, eyeTip: n.hidden ? 'Tampilkan' : 'Sembunyikan', eyeSty: 'flex:none;width:26px;height:26px;border-radius:8px;display:flex;align-items:center;justify-content:center;color:' + (n.hidden ? '#D83A3A' : '#9AA0A9'), toggleEye: (e) => { try { e.stopPropagation(); } catch (x) {} this.setPath(id, 'hidden', !n.hidden); },
        del: (e) => { try { e.stopPropagation(); } catch (x) {} this.removeNode(id); }, stop: (e) => { try { e.stopPropagation(); } catch (x) {} },
        down: (e) => this.startPtr(e, { kind: 'lnode', id }),
      });
      if (hasK && open) walk(n.kids, lv + 1);
    });
    walk(s.root, 0);

    // ---- canvas ----
    const dI = D && D.ind; const indOn = !!dI;
    const indSty = dI ? (dI.k === 'box' ? 'position:absolute;left:' + dI.l + 'px;top:' + dI.t + 'px;width:' + dI.w + 'px;height:' + dI.h + 'px;border:2px solid ' + BLUE + ';background:rgba(29,78,216,.08);border-radius:6px;pointer-events:none;z-index:90;box-sizing:border-box' : 'position:absolute;left:' + dI.l + 'px;top:' + dI.t + 'px;width:' + dI.w + 'px;height:' + dI.h + 'px;background:' + BLUE + ';border-radius:3px;box-shadow:0 0 0 3px rgba(29,78,216,.25);pointer-events:none;z-index:90') : '';
    const ghostOn = !!(D && D.ghost);
    const pageSty = css({ width: '500px', 'min-height': '760px', background: T.page, color: T.text, 'font-family': SANS, 'font-size': T.base + 'px', position: 'relative', display: 'flex', 'flex-direction': 'column', 'box-sizing': 'border-box', 'box-shadow': '0 12px 40px rgba(20,30,50,.18)', overflow: 'clip', 'border-radius': pv ? '4px' : '2px' });
    const tierSty = { Gratis: 'background:#D7FFB8;color:#3C7A00', Premium: 'background:#FFF4CC;color:#B25E00', Deluxe: 'background:#E8DDFF;color:#5B2FB0' }[s.meta.tier];
    const dragCursor = D ? 'cursor:grabbing;' : '';
    const ov = s.ov; const ovOn = !!(ov && sel && ov.id === sel && !pv && !(D && D.mode === 'fl'));
    const selN = ovOn ? getN(sel) : null;
    const ovBox = ovOn ? 'position:absolute;left:' + ov.l + 'px;top:' + ov.t + 'px;width:' + ov.w + 'px;height:' + ov.h + 'px;transform:rotate(' + ov.rot + 'deg);transform-origin:50% 50%;pointer-events:none;z-index:100;box-sizing:border-box' : '';
    const tbTop = ovOn ? ov.bt + ov.bh + 12 : 0; // toolbar di bawah elemen agar tidak menutupi tombol rotate (di atas)
    const tbSty = ovOn ? 'position:absolute;left:' + Math.max(-2, ov.bl - 2) + 'px;top:' + tbTop + 'px;z-index:110;display:flex;align-items:center;gap:2px;background:' + BLUE + ';color:#FFFFFF;border-radius:8px;padding:2px 3px 2px 9px;font-size:11px;line-height:14px;font-weight:700;white-space:nowrap;cursor:default;box-shadow:0 2px 8px rgba(29,78,216,.35)' : '';
    const selW = selN ? WID[selN.type] : null; const stopE = (e) => { try { e.stopPropagation(); } catch (x) {} };


    // ---- Background Global ----
    const G = gbgOf(T); const gbind = { get: (k) => G[k], set: (k, v) => this.setG(k, v === undefined ? GBG0[k] : v) };
    let gb = { on: false, wrap: '', stick: '', media: '', imgSty: '', isImg: false, isVid: false, isSlide: false, vsrc: '', poster: '', vidSty: '', slides: EMPTY, ov: '' };
    const anyG = s.root.some((id) => nodes[id] && nodes[id].type === 'section' && secUsesG(nodes[id], T));
    if (G.on && anyG) {
      const phs = (seed) => PHOTO(T, 'dark', seed); const opv = clamp(G.op != null ? G.op : 100, 0, 100) / 100; const bl = Math.max(0, G.blur || 0);
      const fill = 'position:absolute;left:0;top:0;width:100%;height:100%;';
      const slideSrc = [G.sl1, G.sl2, G.sl3, G.sl4].map((id) => (id ? E.src(id) : '')).filter(Boolean);
      const isSlide = G.kind === 'slide' && slideSrc.length !== 1; const isVid = G.kind === 'video' && !!G.vid;
      const one = G.kind === 'slide' ? (slideSrc[0] || '') : (G.kind === 'video' ? (G.poster || '') : E.src(G.img));
      const list = isSlide ? (slideSrc.length ? slideSrc : [phs(2), phs(4), phs(6)]) : [];
      const N = Math.max(2, Math.min(4, list.length)); const sd = Math.max(2, Math.min(10, G.sd || 4));
      gb = {
        on: true, wrap: 'position:absolute;left:0;top:0;width:100%;height:100%;z-index:0;pointer-events:none;overflow:clip',
        stick: 'position:sticky;top:0;width:100%;height:min(' + Math.max(300, s.vh || 800) + 'px,100%);overflow:hidden',
        media: fill + 'opacity:' + opv + ';' + (bl ? 'filter:blur(' + bl + 'px);transform:scale(1.08);' : ''),
        isImg: !isSlide && !isVid, imgSty: fill + 'background:url("' + (one || phs(5)) + '") center/cover no-repeat',
        isVid, vsrc: G.vid || '', poster: G.poster || '', vidSty: fill + 'object-fit:cover',
        isSlide, slides: isSlide ? list.slice(0, 4).map((src, i) => ({ sty: fill + 'background:url("' + src + '") center/cover no-repeat;opacity:0;animation:tcss' + N + ' ' + (N * sd) + 's linear ' + (i * sd) + 's infinite both' })) : EMPTY,
        ov: fill + 'background:' + qmA(/^#[0-9a-fA-F]{6}$/.test(G.oc) ? G.oc : '#000000', clamp(G.oo != null ? G.oo : 50, 0, 100) / 100),
      };
    }
    // ---- Musik Global (sticky, bukan widget di aliran halaman) ----
    const gmN = nodes.gmusic ? getN('gmusic') : null; const musOn = !!gmN && gmN.p.on !== false;
    let mus = { on: false, sticky: '', cs: '', rows: EMPTY, down: (e) => {} };
    if (musOn) {
      const mv = V.audio(gmN, T, E);
      mus = { on: true, sticky: 'position:sticky;top:0;height:0;z-index:60;pointer-events:none;overflow:visible;flex:none', cs: mv.cs || '', rows: mv.rows || EMPTY, down: pv ? (e) => {} : (e) => { stopE(e); this.openMusic(); } };
    }
    const gSp = [['seg', 'kind', 'Jenis media', { o: [['image', 'Image'], ['video', 'Video'], ['slide', 'Slideshow']] }], ['sw', 'link', 'Hubungkan ke Edit Undangan › Background Fixed', { dflt: 1 }]];
    if (G.link !== false) gSp.push(['note', 'Sumber media: ' + GTOK[G.kind] + ' — diisi pengguna di halaman Background Fixed. Media di bawah hanya contoh untuk pratinjau tema.']);
    if (G.kind === 'image') gSp.push(['asset', 'img', G.link !== false ? 'Gambar contoh (pratinjau)' : 'Gambar latar tema']);
    if (G.kind === 'video') { gSp.push(['text', 'vid', G.link !== false ? 'URL video contoh (.mp4, pratinjau)' : 'URL video (MP4 ≤ 20 MB, ≤ 30 detik)', { ph: 'https://…/video.mp4' }]); gSp.push(['text', 'poster', 'URL poster video (opsional)']); gSp.push(['sw', 'muted', 'Muted', { dflt: 1 }]); gSp.push(['sw', 'loop', 'Loop', { dflt: 1 }]); gSp.push(['note', 'Video MP4 maksimal 20 MB dan durasi maksimal 30 detik. YouTube tetap tersedia sebagai opsi link. Di kanvas video selalu muted.']); }
    if (G.kind === 'slide') { gSp.push(['range', 'sd', 'Durasi tiap slide', { min: 2, max: 10, u: ' dtk' }]); [1, 2, 3, 4].forEach((i) => gSp.push(['asset', 'sl' + i, 'Foto slide ' + i + (G.link !== false ? ' (contoh)' : '')])); }
    gSp.push(['range', 'op', 'Opacity', { min: 0, max: 100, u: '%' }]); gSp.push(['range', 'blur', 'Blur', { min: 0, max: 20, u: 'px' }]);
    gSp.push(['sub', 'Overlay']); gSp.push(['col', 'oc', 'Warna overlay', { d: '#000000' }]); gSp.push(['range', 'oo', 'Opacity overlay', { min: 0, max: 100, u: '%' }]);
    const gbCtls = s.gbm ? gSp.map((sp) => this.mkCtl(sp, gbind, T, s, 'gb')) : EMPTY;
    const swTrk = (on) => 'flex:none;width:44px;height:26px;border-radius:13px;padding:3px;box-sizing:border-box;display:flex;align-items:center;justify-content:' + (on ? 'flex-end' : 'flex-start') + ';background:' + (on ? BLUE : '#C9CED6') + ';border:0';
    const gbSecs = s.root.map((id) => nodes[id]).filter((n) => n && n.type === 'section').map((n) => { const cv = isCoverSec(n); const on = !cv && n.p.gbg !== false; return { name: n.name || 'Section', on, lock: cv, own: !cv && !!n.p.bgimg, trk: swTrk(on) + (cv ? ';opacity:.45;cursor:not-allowed' : ''), toggle: () => { if (!cv) this.setPath(n.id, 'p.gbg', !on); } }; });

    return {
      toList: () => this.toList(),
      mus, musOpenFn: () => this.openMusic(), musPillText: musOn ? 'Aktif' : 'Mati', musPill: 'flex:none;border-radius:8px;padding:2px 8px;font-size:11px;line-height:15px;font-weight:800;' + (musOn ? 'background:#D7FFB8;color:#3C7A00' : 'background:#ECEEF1;color:#666C76'),
      gb, gbShow: !!s.gbm, gbOpenFn: () => this.setState({ gbm: true, dd: null }), gbClose: () => this.setState({ gbm: false, dd: null }), gbCtls, gbSecs, gbNoSec: !gbSecs.length, gbEnTrk: swTrk(G.on), gbEnable: () => this.setG('on', !G.on), gbBody: G.on ? '' : 'opacity:.55',
      gbPillText: G.on ? 'Aktif' : 'Mati', gbPill: 'flex:none;border-radius:8px;padding:2px 8px;font-size:11px;line-height:15px;font-weight:800;' + (G.on ? 'background:#D7FFB8;color:#3C7A00' : 'background:#ECEEF1;color:#666C76'), gbCount: gbSecs.filter((x) => x.on).length + '/' + gbSecs.length,
      root: rootVm, emptyPage: !s.root.length,
      ovOn, ovBox, tbSty, hs: ovOn ? handles(sel) : EMPTY, tbName: selN ? (selN.name || selW.label) : '', hasUp: !!(selN && selN.par), stop: stopE, tbUp: (e) => { stopE(e); if (selN) this.select(selN.par); }, tbDup: (e) => { stopE(e); this.dupNode(sel); }, tbDel: (e) => { stopE(e); this.removeNode(sel); },
      addSec: () => this.addSection(), infoDummy: 'Mode desain · data pada kanvas hanya contoh (dummy). Data asli diisi pengguna di halaman Edit Undangan.', pageSty, pvCls: pv ? 'tcpv' : '', indOn, indSty,
      ghostOn, ghostSty: D ? 'position:fixed;left:' + ((D.gx || 0) + 14) + 'px;top:' + ((D.gy || 0) + 10) + 'px;z-index:9999;pointer-events:none;background:' + BLUE + ';color:#FFFFFF;border-radius:12px;padding:8px 14px;font-size:13px;line-height:17px;font-weight:700;box-shadow:0 8px 24px rgba(29,78,216,.4);white-space:nowrap' : '', ghostLabel: D ? D.label : '',
      bgDown: () => { if (s.sel || s.dd) this.setState({ sel: null, dd: null }); }, scrollSty: 'flex:1 1 0;min-width:0;overflow:auto;padding:' + (snapOn ? '0' : '30px') + ' 40px 140px;box-sizing:border-box;display:flex;flex-direction:column;justify-content:flex-start;align-items:center;' + (snapOn ? 'scroll-snap-type:y mandatory;scroll-behavior:smooth;scroll-padding:0;' : '') + dragCursor,
      // top bar
      nama: s.meta.nama, setNama: (e) => this.setMeta('nama', e.target.value), tierText: s.meta.tier, tierSty: 'flex:none;border-radius:10px;padding:4px 10px;font-size:12px;line-height:16px;font-weight:700;' + tierSty,
      statusText: s.meta.status === 'publish' ? 'Publik' : 'Draf', statusSty: 'flex:none;border-radius:10px;padding:4px 10px;font-size:12px;line-height:16px;font-weight:700;' + (s.meta.status === 'publish' ? 'background:#D7FFB8;color:#3C7A00' : 'background:#FFF4CC;color:#B25E00'),
      savedText: s.dirty ? 'Ada perubahan belum disimpan' : (s.savedAt || 'Belum ada perubahan'), undoDis: !(s.undo || []).length, redoDis: !(s.redo || []).length, undoFn: () => this.undo(), redoFn: () => this.redo(),
      pvOn: pv, pvToggle: () => this.setState({ pv: !pv, sel: null, dd: null }), pvSty: 'min-height:44px;padding:0 16px;border-radius:16px;box-sizing:border-box;display:inline-flex;align-items:center;gap:8px;font-size:13px;line-height:18px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;' + (pv ? 'background:' + BLUE + ';color:#FFFFFF;box-shadow:0 4px 0 #1A3FA8' : 'background:#FFFFFF;color:#262A31;border:2px solid #E2E5E9'), pvText: pv ? 'Keluar pratinjau' : 'Pratinjau',

      qaOpen: !!s.qaOpen, qaShow: () => this.setState({ qaOpen: true, verOpen: false }), qaClose: () => this.setState({ qaOpen: false }), ...(() => {
        const items = s.qaOpen ? this.qaRun().map((x) => ({ text: x.text, isErr: x.lv === 'err', isWarn: x.lv === 'warn', isOk: x.lv === 'ok', hasNode: !!x.id && !!s.nodes[x.id], go: () => { if (x.id && s.nodes[x.id]) this.setState({ qaOpen: false, sel: x.id, dd: null, pvn: {}, lp: {} }); } })) : [];
        const ne = items.filter((x) => x.isErr).length; const nw = items.filter((x) => x.isWarn).length;
        return { qaItems: items, qaHead: ne ? ne + ' masalah penting, ' + nw + ' saran' : nw ? 'Tidak ada masalah penting, ' + nw + ' saran' : 'Tema siap dipublikasikan', qaHeadSty: ne ? 'background:#FFE8E8;color:#B02A2A;' : nw ? 'background:#FFF4CC;color:#7A5200;' : 'background:#D7FFB8;color:#3C7A00;' };
      })(),
      verOpen: !!s.verOpen, verShow: () => this.setState({ verOpen: true, qaOpen: false }), verClose: () => this.setState({ verOpen: false }), ...(() => {
        const l = s.verOpen ? (this.verAll()[s.meta.id] || []) : []; const fmt = (iso) => { const dt = new Date(iso); return dt.toDateString().slice(4, 10) + ' · ' + dt.toTimeString().slice(0, 5); };
        return { verItems: l.map((v, i) => ({ when: fmt(v.at), label: v.label, info: v.sec + ' section · ' + v.n + ' elemen', restore: () => this.verRestore(i), first: i === 0 })), verNone: !l.length };
      })(),
      guestHref: 'UndanganTamu.dc.html#_theme', viewGuestFn: () => this.viewGuest(),
      saveDraft: () => this.saveTheme('draft'), publish: () => this.saveTheme('publish'), toast: s.toast, hasToast: !!s.toast,
      // kiri
      showProps: P.on, noProps: !P.on, P, ltabs, isW: s.ltab === 'widget', isA: s.ltab === 'asset', isT: s.ltab === 'tema', q: s.q, setQ: (e) => this.setState({ q: e.target.value }), groups, anyW, noW: !anyW, assetItems, pickFile, onFile: (e) => this.onFile(e), noAssets: !assetItems.length,
      themeCtls, palettes,
      // kanan
      layRows: lay, layCount: Object.keys(nodes).filter((k) => k !== 'gmusic').length, hasLay: lay.length > 0, lendSty: 'flex:1 1 auto;min-height:70px;border-radius:12px;margin-top:6px;box-sizing:border-box;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:600;color:#9AA0A9;' + (D && D.lend ? 'background:#D6E4FF;outline:2px dashed ' + BLUE + ';outline-offset:-2px;color:' + BLUE : 'border:2px dashed #E2E5E9'),
    };
  }
}

export { TCBase };
