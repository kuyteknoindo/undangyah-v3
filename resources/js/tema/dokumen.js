// Sanitasi DOKUMEN tema (pohon node) dan REKAMAN data tamu. Dipanggil mesin sebelum merender apa pun dari data luar
// (database/terbitan/pesan editor). Hasilnya objek BARU dengan kunci dan nilai yang dikenal; sisanya dibuang.
// Server memeriksa aturan yang sama di App\Services\DokumenTema::bersihkanPohon (dua lapis, bukan satu-satunya pagar).
//
// Prinsip: teks (judul, paragraf, label) dibiarkan sebagai TEKS (perender memasukkannya lewat node teks DOM, tidak pernah
// HTML). Yang dikunci ketat: id, tipe, enum, angka, warna tema, URL aset, dan tautan data (mbl). Nilai yang akhirnya
// masuk string CSS dijaga lagi oleh cssAman/deklarasiAman (sanitasi.js).
import { WID, V, SHADOWS, FONTS, THEME0, GBG0, MBD, MBF, MBVF, EASE_CSS, MAXLV, HOVERS, CLICKS, LOOP_LIST, IN_GROUPS, validDoc } from './prelude.js';
import { teks, ANGKA, hex, urlGambar, urlMedia, urlHttp, nilaiCss } from './sanitasi.js';

export const BATAS = { node: 1200, aset: 60, daftar: 80, kedalamanNilai: 5, teks: 4000, nama: 120 };

const reId = /^[A-Za-z0-9_-]{1,40}$/;
const reKunci = /^[A-Za-z_][A-Za-z0-9_]{0,39}$/;
const KUNCI_BAHAYA = new Set(['__proto__', 'constructor', 'prototype']);
const punya = (o, k) => Object.prototype.hasOwnProperty.call(o, k);

const ENUM_IN = new Set(); IN_GROUPS.forEach((g) => g[1].forEach((x) => ENUM_IN.add(x[0])));
const ENUM_LOOP = new Set(LOOP_LIST.map((x) => x[0]));
const ENUM_HOVER = new Set(HOVERS.map((x) => x[0]));
const ENUM_CLICK = new Set(CLICKS.map((x) => x[0]));
const ENUM_TRIGGER = new Set(['scroll', 'load', 'click']);
const TOKEN_WARNA = new Set(['primary', 'accent', 'surface', 'page', 'text']);

/** Kunci tautan data (mbl) yang sah: bidang MBD, kunci virtual, foto, daftar. */
function kunciMbSah(k) {
  if (k === 'none') return true;
  if (typeof k !== 'string' || k.length > 60) return false;
  if (k.indexOf('v:') === 0) return punya(MBVF, k);
  if (k.indexOf('photo:') === 0) { const s = k.slice(6); return s === 'gallery' || MBD.P.some((p) => p[0] === s); }
  if (k.indexOf('list:') === 0) return punya(MBD.L, k.slice(5));
  return punya(MBF, k);
}

function warnaGaya(v) {
  if (typeof v !== 'string') return undefined;
  const s = v.trim();
  if (s.length > 300) return undefined;
  if (s.indexOf('tok:') === 0) return TOKEN_WARNA.has(s.slice(4)) ? s : undefined;
  return nilaiCss(s) === null ? undefined : s;
}

/** Nilai bebas di dalam `p` (props widget): tipe primitif, larik, objek kecil. */
function nilaiBebas(v, dalam, cat, jalur) {
  if (v === null) return null;
  const t = typeof v;
  if (t === 'boolean') return v;
  if (t === 'number') return Number.isFinite(v) ? Math.max(-1e6, Math.min(1e6, v)) : undefined;
  if (t === 'string') return teks(v, BATAS.teks);
  if (dalam >= BATAS.kedalamanNilai) { cat.push(jalur + ': terlalu dalam'); return undefined; }
  if (Array.isArray(v)) return v.slice(0, BATAS.daftar).map((x, i) => nilaiBebas(x, dalam + 1, cat, jalur + '[' + i + ']')).filter((x) => x !== undefined);
  if (t === 'object') {
    const o = {};
    Object.keys(v).slice(0, 80).forEach((k) => {
      if (!reKunci.test(k) || KUNCI_BAHAYA.has(k)) { cat.push(jalur + '.' + k + ': kunci dibuang'); return; }
      const x = nilaiBebas(v[k], dalam + 1, cat, jalur + '.' + k);
      if (x !== undefined) o[k] = x;
    });
    return o;
  }
  return undefined;
}

function bersihP(p, cat, jalur) {
  const o = {};
  if (!p || typeof p !== 'object' || Array.isArray(p)) return o;
  Object.keys(p).forEach((k) => {
    if (!reKunci.test(k) || KUNCI_BAHAYA.has(k)) { cat.push(jalur + '.' + k + ': kunci dibuang'); return; }
    if (k === 'mbl') {
      const m = {}; const src = p.mbl && typeof p.mbl === 'object' ? p.mbl : {};
      Object.keys(src).forEach((q) => { if (reKunci.test(q) && !KUNCI_BAHAYA.has(q) && kunciMbSah(src[q])) m[q] = src[q]; else cat.push(jalur + '.mbl.' + q + ': tautan data tidak dikenal'); });
      o.mbl = m; return;
    }
    const x = nilaiBebas(p[k], 0, cat, jalur + '.' + k);
    if (x !== undefined) o[k] = x;
  });
  return o;
}

function bersihS(s, cat, jalur) {
  const o = {};
  if (!s || typeof s !== 'object' || Array.isArray(s)) return o;
  Object.keys(s).forEach((k) => {
    if (!reKunci.test(k) || KUNCI_BAHAYA.has(k)) return;
    const v = s[k];
    if (['fs', 'bw', 'r', 'pad', 'ls'].indexOf(k) >= 0) { const x = ANGKA(v, k === 'ls' ? -50 : 0, k === 'fs' ? 300 : 500, undefined); if (x !== undefined) o[k] = x; return; }
    if (k === 'lh') { const x = ANGKA(v, 0.5, 6, undefined); if (x !== undefined) o[k] = x; return; }
    if (k === 'op') { const x = ANGKA(v, 0, 100, undefined); if (x !== undefined) o[k] = x; return; }
    if (k === 'fw') { const x = ANGKA(v, 100, 900, undefined); if (x !== undefined) o[k] = Math.round(x / 100) * 100; return; }
    if (k === 'ff') { if (['head', 'body', 'script'].indexOf(v) >= 0) o[k] = v; return; }
    if (k === 'ta') { if (['left', 'center', 'right', 'justify'].indexOf(v) >= 0) o[k] = v; return; }
    if (k === 'sh') { if (typeof v === 'string' && punya(SHADOWS, v)) o[k] = v; return; }
    if (k === 'it' || k === 'tt') { o[k] = !!v; return; }
    if (k === 'bg' || k === 'color' || k === 'bc') { const x = warnaGaya(v); if (x !== undefined) o[k] = x; else if (v !== undefined) cat.push(jalur + '.' + k + ': warna tidak aman dibuang'); return; }
    if (typeof v === 'number') { if (Number.isFinite(v)) o[k] = Math.max(-1e5, Math.min(1e5, v)); return; }
    if (typeof v === 'boolean') { o[k] = v; return; }
    if (typeof v === 'string') { const x = warnaGaya(v); if (x !== undefined && v.length <= 120) o[k] = x; }
  });
  return o;
}

function bersihA(a, cat, jalur) {
  const src = a && typeof a === 'object' ? a : {};
  const i = src.in && typeof src.in === 'object' ? src.in : {};
  const l = src.loop && typeof src.loop === 'object' ? src.loop : {};
  const oi = {};
  oi.preset = typeof i.preset === 'string' && ENUM_IN.has(i.preset) ? i.preset : 'none';
  oi.trigger = ENUM_TRIGGER.has(i.trigger) ? i.trigger : 'scroll';
  oi.delay = ANGKA(i.delay, 0, 30, 0);
  oi.dur = ANGKA(i.dur, 0.05, 20, 0.8);
  oi.ease = typeof i.ease === 'string' && punya(EASE_CSS, i.ease) ? i.ease : 'smooth';
  oi.replay = !!i.replay;
  oi.margin = ANGKA(i.margin, -1000, 1000, 0);
  oi.amount = ANGKA(i.amount, 0, 100, 20);
  const ol = { preset: typeof l.preset === 'string' && ENUM_LOOP.has(l.preset) ? l.preset : 'none' };
  if (i.preset !== undefined && oi.preset !== i.preset) cat.push(jalur + '.in.preset: animasi tidak dikenal');
  return { in: oi, loop: ol };
}

function bersihIx(ix) {
  const s = ix && typeof ix === 'object' ? ix : {};
  return {
    hover: typeof s.hover === 'string' && ENUM_HOVER.has(s.hover) ? s.hover : 'none',
    click: typeof s.click === 'string' && ENUM_CLICK.has(s.click) ? s.click : 'none',
    url: urlHttp(s.url) || '',
    anchor: typeof s.anchor === 'string' && /^[A-Za-z0-9_-]{0,40}$/.test(s.anchor) ? s.anchor : '',
  };
}

function bersihNode(id, n, cat) {
  if (!n || typeof n !== 'object') return null;
  if (typeof n.type !== 'string' || !punya(WID, n.type) || !WID[n.type] || typeof WID[n.type].view !== 'string' || typeof V[WID[n.type].view] !== 'function') { cat.push(id + ': tipe widget tidak dikenal'); return null; }
  const W = WID[n.type];
  const jal = id;
  const num = (v, a, b, d) => ANGKA(v, a, b, d);
  const o = {
    id, type: n.type, name: teks(n.name, BATAS.nama), par: n.par == null ? null : String(n.par),
    kids: Array.isArray(n.kids) ? n.kids.filter((k) => typeof k === 'string').slice(0, 300) : [],
    pos: n.pos === 'free' ? 'free' : (n.pos === 'flow' ? 'flow' : (W.pos || 'flow')),
    x: num(n.x, -5000, 5000, 0), y: num(n.y, -5000, 5000, 0),
    w: n.w == null ? undefined : num(n.w, 0, 5000, undefined), h: n.h == null ? undefined : num(n.h, 0, 5000, undefined),
    rot: num(n.rot, -360, 360, 0), hidden: !!n.hidden, z: n.z == null ? undefined : num(n.z, 0, 99, undefined),
    al: ['left', 'center', 'right'].indexOf(n.al) >= 0 ? n.al : 'center',
    p: bersihP(n.p, cat, jal + '.p'), s: bersihS(n.s, cat, jal + '.s'), a: bersihA(n.a, cat, jal + '.a'), ix: bersihIx(n.ix),
  };
  if (n.mt != null) o.mt = num(n.mt, -1000, 3000, undefined);
  if (n.mb != null) o.mb = num(n.mb, -1000, 3000, undefined);
  if (n.fill != null) o.fill = !!n.fill;
  return o;
}

function bersihTema(t, cat) {
  const s = t && typeof t === 'object' ? t : {};
  const T = Object.assign({}, THEME0);
  ['primary', 'accent', 'surface', 'page', 'text'].forEach((k) => { const h = hex(s[k]); if (h) T[k] = h; else if (s[k] !== undefined) cat.push('theme.' + k + ': warna harus #RGB/#RRGGBB'); });
  if (typeof s.head === 'string' && punya(FONTS, s.head)) T.head = s.head;
  T.base = ANGKA(s.base, 10, 28, THEME0.base);
  T.snap = s.snap === 0 || s.snap === false ? 0 : 1;
  if (s.bgfx !== undefined) T.bgfx = s.bgfx ? 1 : 0;
  if (s.gbg && typeof s.gbg === 'object') {
    const g = s.gbg; const o = {};
    if (g.on !== undefined) o.on = !!g.on;
    if (['image', 'video', 'slide'].indexOf(g.kind) >= 0) o.kind = g.kind;
    if (g.link !== undefined) o.link = g.link !== false;
    ['img', 'sl1', 'sl2', 'sl3', 'sl4'].forEach((k) => { if (typeof g[k] === 'string' && /^[A-Za-z0-9_-]{0,60}$/.test(g[k])) o[k] = g[k]; });
    if (g.vid !== undefined) o.vid = urlMedia(g.vid) || '';
    if (g.poster !== undefined) o.poster = urlGambar(g.poster) || '';
    if (g.sd !== undefined) o.sd = ANGKA(g.sd, 2, 10, GBG0.sd);
    if (g.muted !== undefined) o.muted = !!g.muted;
    if (g.loop !== undefined) o.loop = !!g.loop;
    if (g.op !== undefined) o.op = ANGKA(g.op, 0, 100, 100);
    if (g.blur !== undefined) o.blur = ANGKA(g.blur, 0, 40, 0);
    if (g.oc !== undefined) o.oc = hex(g.oc) || GBG0.oc;
    if (g.oo !== undefined) o.oo = ANGKA(g.oo, 0, 100, 50);
    T.gbg = o;
  }
  return T;
}

/**
 * Bersihkan dokumen tema {nodes, root, nid, theme, assets}. Mengembalikan {doc, catatan} atau {doc:null, catatan}
 * bila struktur tidak bisa diselamatkan (bukan pohon sah, terlalu besar, dst.).
 */
export function sanitasiDoc(doc) {
  const catatan = [];
  const gagal = (m) => { catatan.push(m); return { doc: null, catatan }; };
  if (!doc || typeof doc !== 'object' || !doc.nodes || typeof doc.nodes !== 'object' || !Array.isArray(doc.root)) return gagal('dokumen bukan {nodes, root, theme}');
  const ids = Object.keys(doc.nodes);
  if (ids.length > BATAS.node) return gagal('terlalu banyak node (maks ' + BATAS.node + ')');
  const nodes = {};
  for (const id of ids) {
    if (!reId.test(id) || KUNCI_BAHAYA.has(id)) { catatan.push(id + ': id node tidak sah'); continue; }
    const n = bersihNode(id, doc.nodes[id], catatan);
    if (n) nodes[id] = n;
  }
  // kaitan induk-anak harus konsisten dan tanpa siklus; node yatim dibuang
  const seen = {};
  const lurus = (daftar, par, dalam) => {
    const out = [];
    for (const id of daftar) {
      const n = nodes[id];
      if (!n || seen[id] || (n.par || null) !== par) { catatan.push(String(id) + ': kaitan induk-anak tidak konsisten'); continue; }
      seen[id] = 1;
      if (dalam > MAXLV + 1) { catatan.push(id + ': terlalu dalam'); continue; }
      n.kids = lurus(n.kids, id, dalam + 1);
      out.push(id);
    }
    return out;
  };
  const root = lurus(doc.root.filter((x) => typeof x === 'string'), null, 0);
  Object.keys(nodes).forEach((id) => { if (!seen[id]) delete nodes[id]; });
  // gmusic (musik global) hidup di nodes tetapi tidak di root/kids
  if (doc.nodes.gmusic && !nodes.gmusic) { const g = bersihNode('gmusic', doc.nodes.gmusic, catatan); if (g && g.type === 'audio') { g.par = null; g.kids = []; nodes.gmusic = g; } }
  const theme = bersihTema(doc.theme, catatan);
  const assets = [];
  (Array.isArray(doc.assets) ? doc.assets : []).slice(0, BATAS.aset).forEach((a) => {
    if (!a || typeof a !== 'object' || typeof a.id !== 'string' || !/^[A-Za-z0-9_-]{1,60}$/.test(a.id) || /^(sa|lv_)/.test(a.id)) return;
    const src = urlGambar(a.src);
    if (!src) { catatan.push('aset ' + a.id + ': URL tidak sah'); return; }
    assets.push({ id: a.id, name: teks(a.name, 60), ar: ANGKA(a.ar, 0.05, 20, 1), src });
  });
  const hasil = { nodes, root, nid: Math.round(ANGKA(doc.nid, 1, 1e6, 300)), theme, assets };
  if (!validDoc(hasil)) return gagal('struktur pohon tidak sah');
  return { doc: hasil, catatan };
}

// ---------- rekaman data tamu ----------
const KIND = {}; MBD.F.forEach((f) => { KIND[f[0]] = f[4]; });
const reDaftar = /^([a-z]{2})(\d{1,9})_([A-Za-z0-9]+)$/;

/** Nilai satu bidang menurut jenisnya (url/color/date/time/switch); teks biasa hanya dipotong. */
export function nilaiBidang(kunci, v) {
  if (v == null) return '';
  if (typeof v === 'boolean') v = v ? '1' : '0';
  if (typeof v === 'number') v = String(v);
  if (typeof v !== 'string') return '';
  let jenis = KIND[kunci];
  const m = reDaftar.exec(kunci);
  if (!jenis && m && MBD.L[m[1]]) { const f = MBD.L[m[1]].f.find((q) => q[0] === m[3]); jenis = f ? f[3] : 'text'; }
  const s = teks(v, jenis === 'text' || !jenis ? 2000 : 600);
  switch (jenis) {
    case 'url': return kunci === 'musUrl' ? (urlMedia(s) || '') : (urlHttp(s) || '');
    case 'color': return hex(s) || '';
    case 'date': return /^\d{4}-\d{2}-\d{2}$/.test(s) ? s : '';
    case 'time': return /^([01]\d|2[0-3]):[0-5]\d$/.test(s) ? s : '';
    case 'switch': return s === '1' ? '1' : '0';
    default: return s;
  }
}

/** Rekaman {theme:{row,doc}, data:{d,hid,hidSec,lists,photos,lib,gset,bgf,order}, ...} -> rekaman bersih. */
export function sanitasiRec(rec) {
  const r = rec && typeof rec === 'object' ? rec : {};
  const D = r.data && typeof r.data === 'object' ? r.data : {};
  const d = {};
  const src = D.d && typeof D.d === 'object' ? D.d : {};
  Object.keys(src).slice(0, 4000).forEach((k) => { if (/^[A-Za-z][A-Za-z0-9_]{0,40}$/.test(k) && !KUNCI_BAHAYA.has(k)) d[k] = nilaiBidang(k, src[k]); });
  const peta = (o) => { const x = {}; if (o && typeof o === 'object') Object.keys(o).slice(0, 4000).forEach((k) => { if (/^[A-Za-z][A-Za-z0-9_]{0,40}$/.test(k) && !KUNCI_BAHAYA.has(k) && o[k]) x[k] = true; }); return x; };
  const lists = {};
  if (D.lists && typeof D.lists === 'object') {
    Object.keys(D.lists).forEach((tp) => {
      if (!/^[a-z]{2}$/.test(tp) || !Array.isArray(D.lists[tp])) return;
      lists[tp] = D.lists[tp].map((x) => Number(x)).filter((x) => Number.isInteger(x) && x > 0 && x < 1e9).slice(0, BATAS.daftar);
    });
  }
  const lib = []; const idLib = {};
  (Array.isArray(D.lib) ? D.lib : []).slice(0, 80).forEach((x) => {
    if (!x || typeof x.id !== 'string' || !/^[A-Za-z0-9_-]{1,60}$/.test(x.id)) return;
    const s = urlGambar(x.src); if (!s) return; lib.push({ id: x.id, src: s }); idLib[x.id] = 1;
  });
  const photos = {};
  if (D.photos && typeof D.photos === 'object') Object.keys(D.photos).forEach((slot) => { const p = D.photos[slot]; if (/^[A-Za-z0-9]{1,12}$/.test(slot) && p && typeof p.lib === 'string' && idLib[p.lib]) photos[slot] = { lib: p.lib }; });
  const g = D.gset && typeof D.gset === 'object' ? D.gset : {};
  const gset = { layout: ['grid', 'masonry', 'slider'].indexOf(g.layout) >= 0 ? g.layout : 'grid', video: urlHttp(g.video) || '' };
  const b = D.bgf && typeof D.bgf === 'object' ? D.bgf : {};
  const bgf = {};
  if (b.on !== undefined) bgf.on = !!b.on;
  if (['image', 'video', 'slide'].indexOf(b.kind) >= 0) bgf.kind = b.kind;
  const vs = urlMedia(b.vsrc) || urlMedia(b.vurl); if (vs) bgf.vsrc = vs;
  if (b.sd != null) bgf.sd = ANGKA(b.sd, 2, 10, 4);
  if (b.oc != null) bgf.oc = hex(b.oc) || '#000000';
  ['oo', 'op'].forEach((k) => { if (b[k] != null) bgf[k] = ANGKA(b[k], 0, 100, k === 'oo' ? 50 : 100); });
  if (b.blur != null) bgf.blur = ANGKA(b.blur, 0, 40, 0);
  const th = r.theme && typeof r.theme === 'object' ? r.theme : {};
  const row = th.row && typeof th.row === 'object' ? th.row : {};
  return {
    v: 1, invId: teks(r.invId, 40), slug: String(r.slug || '').toLowerCase().replace(/[^a-z0-9-]/g, '').slice(0, 80), judul: teks(r.judul, 200),
    status: r.status === 'publish' ? 'publish' : 'draft',
    theme: { row: { primary: hex(row.primary), accent: hex(row.accent), surface: hex(row.surface), page: hex(row.page), text: hex(row.text), head: typeof row.head === 'string' && punya(FONTS, row.head) ? row.head : undefined, snap: row.snap === false ? false : true }, doc: th.doc },
    data: { d, hid: peta(D.hid), hidSec: peta(D.hidSec), lists, photos, lib, gset, bgf, order: [] },
  };
}
