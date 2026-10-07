// Runtime ringkas pengganti React + "DC runtime" papan artifact: cukup untuk menjalankan kelas logika TCBase dan
// templat `<x-dc>` (sc-if, sc-for, {{ ekspresi }}) di atas Preact. Tidak memakai eval/Function: ekspresi templat
// hanya jalur properti, `!`, `==`, `!=`, `===`, `!==`, literal, dan kurung (sama dengan runtime papan).
//
//   DCLogic            kelas dasar logika (state/setState/forceUpdate/siklus hidup), setara DCLogic papan.
//   kompilasiTemplat   string templat -> (vals) => vnode[]
//   pasang             pasang kelas logika + templat ke elemen; mengembalikan {logic, perbarui, hancurkan}
//
// Perilaku yang disamakan dengan runtime papan (agar DOM identik):
//   - teks interpolasi {{x}} dibungkus <span class="sc-interp">; nilai undefined/null/boolean = kosong;
//   - simpul teks yang hanya spasi putih tanpa karakter spasi (mis. "\n") dibuang;
//   - atribut style diterapkan sebagai cssText (di sini: lewat deklarasiAman); onChange pada input teks = event input.
import { h, render, Fragment } from 'preact';
import { deklarasiAman, urlGambar, urlMedia, urlTautan, kelasAman } from './sanitasi.js';

export class DCLogic {
  constructor(props) { this.props = props || {}; this.state = {}; this.__host = null; }
  setState(p, cb) { if (this.__host) this.__host.setLogicState(p, cb); }
  forceUpdate() { if (this.__host) this.__host.paksa(); }
  componentDidMount() {}
  componentDidUpdate() {}
  componentWillUnmount() {}
  renderVals() { return {}; }
}

// ---------- ekspresi ----------
const reNama = /^[A-Za-z_$][A-Za-z0-9_$]*/;
const reAngka = /^-?\d+(\.\d+)?$/;
const kurungPembungkus = (t) => { let n = 0; for (let e = 0; e < t.length - 1; e++) { if (t[e] === '(') n++; else if (t[e] === ')') { n--; if (n === 0) return false; } } return true; };
const cariOperator = (t) => {
  let n = 0;
  for (let e = 0; e < t.length; e++) {
    const r = t[e];
    if (r === '[' || r === '(') n++;
    else if (r === ']' || r === ')') n--;
    else if (n === 0 && (r === '=' || r === '!') && t[e + 1] === '=') {
      if ((e > 0 && (t[e - 1] === '=' || t[e - 1] === '!')) || !t.slice(0, e).trim()) continue;
      return { index: e, op: t[e + 2] === '=' ? r + '==' : r + '=' };
    }
  }
  return null;
};
const jalur = (scope, e) => {
  const m = e.match(reNama); if (!m) return undefined;
  let r = scope == null ? undefined : scope[m[0]]; let o = m[0].length;
  while (o < e.length) {
    if (e[o] === '.') {
      const s = e.slice(o + 1).match(reNama) || e.slice(o + 1).match(/^\d+/);
      if (!s) return undefined;
      r = r == null ? undefined : r[s[0]]; o += 1 + s[0].length;
    } else if (e[o] === '[') {
      let s = 1; let i = o + 1;
      while (i < e.length && s > 0) { if (e[i] === '[') s++; else if (e[i] === ']') { s--; if (s === 0) break; } i++; }
      if (s !== 0) return undefined;
      const l = evaluasi(scope, e.slice(o + 1, i)); r = r == null ? undefined : r[l]; o = i + 1;
    } else return undefined;
  }
  return r;
};
export function evaluasi(scope, ekspr) {
  const e = String(ekspr).trim();
  if (!e) return undefined;
  if (e[0] === '(' && e[e.length - 1] === ')' && kurungPembungkus(e)) return evaluasi(scope, e.slice(1, -1));
  const o = cariOperator(e);
  if (o) {
    const a = evaluasi(scope, e.slice(0, o.index)); const b = evaluasi(scope, e.slice(o.index + o.op.length));
    switch (o.op) { case '===': return a === b; case '!==': return a !== b; case '==': return a == b; default: return a != b; }
  }
  if (e[0] === '!') return !evaluasi(scope, e.slice(1));
  if (e === 'true') return true;
  if (e === 'false') return false;
  if (e === 'null') return null;
  if (e === 'undefined') return undefined;
  if (reAngka.test(e)) return Number(e);
  if (e.length >= 2 && (e[0] === '"' || e[0] === "'") && e[e.length - 1] === e[0]) return e.slice(1, -1);
  return jalur(scope, e);
}
const getter = (nilai) => {
  const m = nilai.match(/^\s*\{\{([\s\S]+?)\}\}\s*$/);
  if (m) { const e = m[1]; return (s) => evaluasi(s, e); }
  if (nilai.indexOf('{{') >= 0) { const bag = nilai.split(/\{\{([\s\S]+?)\}\}/g); return (s) => bag.map((x, i) => (i & 1 ? (evaluasi(s, x) ?? '') : x)).join(''); }
  return () => nilai;
};

// ---------- pra-proses templat (atribut camelCase dilindungi dari penurunan huruf oleh parser HTML) ----------
const AWAL = 'sc-camel-';
const ot = '(?:=\\s*"[^"]*"|=\\s*\'[^\']*\'|=(?!\\s*["\'])|[^>=])*';
const reTag = new RegExp('(<!---?>|<!--[\\s\\S]*?--!?>)|(<(script|style|textarea|title)(?=[\\s/>])' + ot + '>)([\\s\\S]*?<\\/\\3(?=[\\s/>])[^>]*>)|<[a-zA-Z][^\\s/>]*(?=[\\s/>])' + ot + '>', 'gi');
const reAttr = /=\s*"[^"]*"|=\s*'[^']*'|(\s)([a-z]+[A-Z][A-Za-z0-9]*)(?=\s*=)/g;
const lindungi = (html) => {
  const ubah = (e) => e.replace(reAttr, (r, o, s) => (s === undefined ? r : o + AWAL + s.replace(/[A-Z]/g, (i) => '-' + i.toLowerCase())));
  return html.replace(reTag, (e, r, o, s, i) => (r !== undefined ? e : o ? ubah(o) + i : ubah(e)));
};
const kebabKeCamel = (t) => t.replace(/-([a-z])/g, (n, e) => e.toUpperCase());

const EVENT = {
  onclick: 'onClick', onchange: 'onChange', oninput: 'onInput', onsubmit: 'onSubmit', onkeydown: 'onKeyDown', onkeyup: 'onKeyUp', onmousedown: 'onMouseDown', onmouseup: 'onMouseUp',
  onmouseenter: 'onMouseEnter', onmouseleave: 'onMouseLeave', onfocus: 'onFocus', onblur: 'onBlur', onpointerdown: 'onPointerDown', onpointerup: 'onPointerUp', onpointermove: 'onPointerMove',
  onpointercancel: 'onPointerCancel', ontouchstart: 'onTouchStart', ontouchend: 'onTouchEnd', ontouchmove: 'onTouchMove', ondragstart: 'onDragStart',
};
const BOOLEAN = new Set('allowFullScreen async autoFocus autoPlay checked controls default defer disabled hidden loop multiple muted noValidate open playsInline readOnly required selected autoplay playsinline'.split(' '));
const TEKS_INPUT = new Set(['text', 'search', 'url', 'tel', 'email', 'password', 'number', 'date', 'time', 'datetime-local', 'month', 'week', 'range', 'color']);
const TERLARANG = new Set(['script', 'style', 'link', 'meta', 'base', 'iframe', 'frame', 'embed', 'object', 'portal', 'template', 'head', 'html', 'body', 'applet', 'frameset']);

// ---------- kompilasi ----------
function kompilasiTeks(node) {
  const nilai = node.nodeValue == null ? '' : node.nodeValue;
  if (nilai.indexOf('{{') < 0) return (!nilai.trim() && nilai.indexOf(' ') < 0) ? null : () => nilai;
  const bag = nilai.split(/\{\{([\s\S]+?)\}\}/g);
  return (scope, key) => h(Fragment, { key }, bag.map((s, i) => {
    if (!(i & 1)) return s;
    const c = evaluasi(scope, s);
    if (c === undefined || c === null || typeof c === 'boolean') return null;
    if (Array.isArray(c) || (typeof c === 'object' && 'type' in c && 'props' in c)) return c;
    return h('span', { key: i, class: 'sc-interp' }, String(c));
  }));
}

function kompilasiAnak(node) { return [...node.childNodes].map(kompilasiSimpul).filter((x) => x != null); }
const jalankanAnak = (anak, scope) => anak.map((f, i) => f(scope, i));

function kompilasiSimpul(node) {
  if (node.nodeType === 3) return kompilasiTeks(node);
  if (node.nodeType !== 1) return null;
  const tag = node.localName;
  if (tag === 'sc-for') {
    const list = getter(node.getAttribute('list') || ''); const sbg = node.getAttribute('as') || 'item'; const anak = kompilasiAnak(node);
    return (scope, key) => {
      let l = list(scope); if (!Array.isArray(l)) l = [];
      return h(Fragment, { key }, l.map((it, u) => h(Fragment, { key: u }, jalankanAnak(anak, Object.assign({}, scope, { [sbg]: it, $index: u })))));
    };
  }
  if (tag === 'sc-if') {
    const nilai = getter(node.getAttribute('value') || ''); const anak = kompilasiAnak(node);
    return (scope, key) => (nilai(scope) ? h(Fragment, { key }, jalankanAnak(anak, scope)) : null);
  }
  if (tag === 'sc-helmet') return null;
  if (TERLARANG.has(tag)) return null;

  const jenisInput = tag === 'input' ? String(node.getAttribute('type') || 'text').toLowerCase() : '';
  const props = [];
  for (const { name, value } of [...node.attributes]) {
    if (name === 'sc-name' || name === 'data-dc-tpl') continue;
    let f = name;
    if (f.startsWith(AWAL)) f = kebabKeCamel(f.slice(AWAL.length));
    if (f === 'class') { props.push(['class', getter(value), 'kelas']); continue; }
    if (f === 'style') { props.push(['style', getter(value), 'gaya']); continue; }
    if (f === 'for') f = 'htmlFor';
    if (f.toLowerCase().startsWith('on')) {
      f = EVENT[f.toLowerCase()] || ('on' + f[2].toUpperCase() + f.slice(3));
      if (f === 'onChange' && (tag === 'textarea' || (tag === 'input' && TEKS_INPUT.has(jenisInput)))) f = 'onInput';
      props.push([f, getter(value), 'event']); continue;
    }
    if (value === '' && BOOLEAN.has(f)) { props.push([f === 'autoplay' ? 'autoplay' : f, () => true, 'bool']); continue; }
    if (f === 'checked' && tag === 'input' && value.indexOf('{{') < 0) { props.push(['defaultChecked', () => true, 'bool']); continue; }
    props.push([f, getter(value), f === 'src' || f === 'poster' || f === 'href' ? f : 'biasa']);
  }
  const anak = kompilasiAnak(node);
  return (scope, key) => {
    const p = { key };
    for (let i = 0; i < props.length; i++) {
      const [nama, ambil, jenis] = props[i];
      let v = ambil(scope);
      if (jenis === 'gaya') { v = deklarasiAman(v == null ? '' : String(v)); p.style = v; continue; }
      if (jenis === 'kelas') { v = kelasAman(v); if (v) p.class = v; continue; }
      if (jenis === 'event') { if (typeof v === 'function') p[nama] = v; continue; }
      if (jenis === 'src' || jenis === 'poster') { v = v == null ? '' : (tag === 'img' ? urlGambar(String(v)) : (tag === 'video' && jenis === 'src' ? urlMedia(String(v)) : urlGambar(String(v)))); p[nama] = v || undefined; continue; }
      if (jenis === 'href') { v = urlTautan(v == null ? '' : String(v)); p[nama] = v || undefined; continue; }
      if ((nama === 'value' || nama === 'checked') && v === undefined) v = nama === 'checked' ? false : '';
      p[nama] = v;
    }
    return h(tag, p, jalankanAnak(anak, scope));
  };
}

const cacheTemplat = new Map();
/** Kompilasi string templat (isi `<x-dc>`). Hasil di-cache menurut string. */
export function kompilasiTemplat(str) {
  let c = cacheTemplat.get(str);
  if (c) return c;
  const t = document.createElement('template');
  t.innerHTML = lindungi(str);
  const anak = kompilasiAnak(t.content);
  c = (scope) => jalankanAnak(anak, scope || {});
  cacheTemplat.set(str, c);
  return c;
}

/**
 * Pasang kelas logika + templat ke elemen. setState bersifat sinkron pada this.state (seperti runtime papan) dan
 * menjadwalkan render ulang di mikrotugas berikutnya (batch). Render dilakukan oleh Preact (diff).
 */
export function pasang(Kelas, templat, wadah, props) {
  const tpl = kompilasiTemplat(templat);
  const logic = new Kelas(props || {});
  const cbs = [];
  let jadwal = false; let mati = false; let sudahMount = false; let ulang = 0;
  const host = {
    setLogicState(p, cb) {
      if (mati) return;
      const cur = logic.state || {}; const nx = typeof p === 'function' ? p(cur) : p;
      logic.state = Object.assign({}, cur, nx);
      if (cb) cbs.push(cb);
      host.paksa();
    },
    paksa() { if (mati || jadwal) return; jadwal = true; Promise.resolve().then(flush); },
  };
  logic.__host = host;
  function gambar() {
    let vals = {};
    try { vals = Object.assign({}, logic.props, logic.renderVals() || {}); } catch (e) { console.error('[tema] renderVals gagal:', e); if (props && props.onGalat) props.onGalat(e); }
    render(h(Fragment, null, tpl(vals)), wadah);
  }
  function flush() {
    jadwal = false; if (mati) return;
    gambar();
    if (!sudahMount) { sudahMount = true; try { logic.componentDidMount(); } catch (e) { console.error(e); } } else { try { logic.componentDidUpdate(); } catch (e) { console.error(e); } }
    const todo = cbs.splice(0); todo.forEach((f) => { try { f(); } catch (e) { console.error(e); } });
    if (jadwal) { if (++ulang > 40) { jadwal = false; ulang = 0; console.error('[tema] render ulang berlebihan dihentikan'); } } else ulang = 0;
  }
  flush();
  return {
    logic,
    perbarui(p) { logic.props = Object.assign({}, logic.props, p || {}); host.paksa(); },
    segarkan() { host.paksa(); },
    hancurkan() { if (mati) return; mati = true; try { logic.componentWillUnmount(); } catch (e) { console.error(e); } render(null, wadah); },
  };
}
