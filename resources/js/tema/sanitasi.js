// Sanitasi mesin tema: SATU tempat untuk semua nilai yang bisa mengalir dari dokumen tema / isi undangan ke
// string CSS, atribut URL, nama kelas, dan SVG. Tidak ada eval/Function; teks isi hanya masuk lewat node teks DOM.
//
//  - cssAman(o)          dipakai oleh css() prelude (hasil ekstraksi): kunci dan nilai diperiksa satu per satu.
//  - deklarasiAman(str)  dipakai perender pada SETIAP atribut style (jaring pengaman untuk string yang disusun manual).
//  - urlGambar/urlMedia/urlTautan  hanya http(s), path situs ("/..."), data:image/* (gambar saja).
//  - bersihSvg(s)        membuang skrip dan handler dari SVG yang dirakit di kode.
// Aturan nilai CSS: tanpa `; { } < > \ @ !` di luar tanda kutip, tanpa komentar, tanpa fungsi berbahaya
// (expression, image-set, src, element, paint, ...), url() hanya ke URL yang lolos urlGambar.

const MAKS_NILAI = 600000; // data URI gambar boleh panjang; selebihnya jauh lebih pendek
const MAKS_URL = 2048;

const reKontrol = /[\u0000-\u0020\u007f-\u009f\u2028\u2029]/;
const reTerlarangUrl = /["\\<>`]/;

/** http(s) dengan host, tanpa spasi/kutip/backslash/userinfo. Mengembalikan '' bila tidak sah. */
export function urlHttp(u) {
  if (typeof u !== 'string') return '';
  const s = u.trim();
  if (!s || s.length > MAKS_URL || reKontrol.test(s) || reTerlarangUrl.test(s) || s.indexOf("'") >= 0) return '';
  if (!/^https?:\/\/[^/?#]+/i.test(s)) return '';
  try {
    const p = new URL(s);
    if ((p.protocol !== 'http:' && p.protocol !== 'https:') || !p.hostname || p.username || p.password) return '';
  } catch (e) { return ''; }
  return s;
}

/** Path situs: diawali satu "/", bukan "//" (protocol-relative) atau "/\". */
export function urlSitus(u) {
  if (typeof u !== 'string') return '';
  const s = u.trim();
  if (!s || s.length > MAKS_URL || reKontrol.test(s) || reTerlarangUrl.test(s) || s.indexOf("'") >= 0) return '';
  return /^\/(?![/\\])/.test(s) ? s : '';
}

const reData = /^data:image\/(?:png|jpe?g|gif|webp|avif|bmp|svg\+xml)(?:;[a-z0-9=.+-]+)*,/i;
/** data:image/* (SVG hanya aman karena dipakai sebagai gambar, bukan dokumen). Dibatasi 6 MB. */
export function urlData(u) {
  if (typeof u !== 'string') return '';
  const s = u.trim();
  if (!s || s.length > 6e6 || !reData.test(s) || reKontrol.test(s) || reTerlarangUrl.test(s)) return '';
  return s;
}

let izinBlob = false;
/** Mode editor boleh memakai blob: (pratinjau berkas lokal sebelum diunggah). */
export function atur(o) { izinBlob = !!(o && o.blob); }
const urlBlob = (u) => (izinBlob && typeof u === 'string' && /^blob:https?:\/\/[^\s"'<>\\]+$/i.test(u.trim()) ? u.trim() : '');

export const urlGambar = (u) => urlHttp(u) || urlSitus(u) || urlData(u) || urlBlob(u);
export const urlMedia = (u) => urlHttp(u) || urlSitus(u) || urlBlob(u);
export function urlTautan(u) {
  if (typeof u !== 'string') return '';
  const s = u.trim();
  if (/^(mailto|tel):[^\s"'<>\\]{1,200}$/i.test(s)) return s;
  return urlHttp(s) || urlSitus(s);
}

export const hex = (c) => (typeof c === 'string' && /^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i.test(c.trim()) ? c.trim() : '');

const FUNGSI_TERLARANG = new Set(['expression', 'javascript', 'vbscript', 'livescript', 'mocha', 'behavior', 'binding', '-moz-binding',
  'image-set', '-webkit-image-set', 'image', 'src', 'element', '-moz-element', 'paint', 'cross-fade', '-webkit-cross-fade']);
const PROP_TERLARANG = new Set(['behavior', '-moz-binding']);
const reProp = /^-{0,2}[a-z][a-z0-9-]*$/;

/** Isi argumen url(...) yang sah (sudah tanpa kutip). */
function urlCssSah(arg) { return !!urlGambar(arg); }

/**
 * Periksa satu nilai CSS. Mengembalikan string nilai (angka diubah jadi string) atau null bila tidak aman.
 * Pemindai mengenali tanda kutip dan kurung agar `url("data:image/svg+xml;utf8,...")` sah walau berisi `;`.
 */
export function nilaiCss(v) {
  if (typeof v === 'number') return Number.isFinite(v) ? String(v) : null;
  if (typeof v !== 'string') return null;
  const n = v.length;
  if (n > MAKS_NILAI) return null;
  let i = 0; let dalam = 0;
  while (i < n) {
    const c = v.charCodeAt(i);
    if (c < 32 || c === 127) return null; // tab/baris baru tidak pernah dibutuhkan
    const ch = v[i];
    if (ch === '"' || ch === "'") {
      let j = i + 1;
      while (j < n && v[j] !== ch) {
        const cj = v.charCodeAt(j);
        if (v[j] === '\\' || cj < 32 || cj === 127) return null;
        j++;
      }
      if (j >= n) return null;
      i = j + 1; continue;
    }
    if (ch === ';' || ch === '{' || ch === '}' || ch === '<' || ch === '>' || ch === '\\' || ch === '@' || ch === '!') return null;
    if (ch === '/' && (v[i + 1] === '*' || (i > 0 && v[i - 1] === '*'))) return null;
    if (ch === '(') {
      let k = i; while (k > 0 && /[A-Za-z0-9_-]/.test(v[k - 1])) k--;
      const nama = v.slice(k, i).toLowerCase();
      if (FUNGSI_TERLARANG.has(nama)) return null;
      if (nama === 'url') {
        let j = i + 1; while (j < n && v[j] === ' ') j++;
        let arg = ''; let akhir;
        if (v[j] === '"' || v[j] === "'") {
          const q = v[j]; let e = j + 1;
          while (e < n && v[e] !== q) { if (v[e] === '\\') return null; e++; }
          if (e >= n) return null;
          arg = v.slice(j + 1, e); akhir = e + 1;
        } else {
          let e = j;
          while (e < n && v[e] !== ')') { if (/[\s"'()\\]/.test(v[e])) return null; e++; }
          arg = v.slice(j, e); akhir = e;
        }
        while (akhir < n && v[akhir] === ' ') akhir++;
        if (v[akhir] !== ')' || !urlCssSah(arg)) return null;
        i = akhir + 1; continue;
      }
      dalam++; i++; continue;
    }
    if (ch === ')') { dalam--; if (dalam < 0) return null; }
    i++;
  }
  return dalam === 0 ? v : null;
}

const propAman = (k) => reProp.test(k) && !PROP_TERLARANG.has(k);

/** css({prop: nilai}) -> "prop:nilai;prop:nilai". Nilai kosong/null/false dilewati; yang tidak aman dibuang diam-diam. */
export function cssAman(o) {
  const out = [];
  const ks = Object.keys(o);
  for (let q = 0; q < ks.length; q++) {
    const k = ks[q]; const v = o[k];
    if (v === '' || v == null || v === false) continue;
    if (!propAman(k)) continue;
    const s = nilaiCss(v);
    if (s === null || s === '') continue;
    out.push(k + ':' + s);
  }
  return out.join(';');
}

const cacheDek = new Map();
/** Bersihkan string atribut style hasil rakitan manual: tiap deklarasi diperiksa ulang. */
export function deklarasiAman(str) {
  if (str == null || str === '') return '';
  if (typeof str !== 'string') return '';
  const hit = cacheDek.get(str);
  if (hit !== undefined) return hit;
  const out = [];
  let mulai = 0; let kutip = ''; let dalam = 0;
  const proses = (seg) => {
    const t = seg.trim();
    if (!t) return;
    const ix = t.indexOf(':');
    if (ix <= 0) return;
    const k = t.slice(0, ix).trim().toLowerCase();
    const s = nilaiCss(t.slice(ix + 1).trim());
    if (!propAman(k) || s === null || s === '') return;
    out.push(k + ':' + s);
  };
  for (let i = 0; i < str.length; i++) {
    const ch = str[i];
    if (kutip) { if (ch === kutip) kutip = ''; continue; }
    if (ch === '"' || ch === "'") kutip = ch;
    else if (ch === '(') dalam++;
    else if (ch === ')') dalam = Math.max(0, dalam - 1);
    else if (ch === ';' && dalam === 0) { proses(str.slice(mulai, i)); mulai = i + 1; }
  }
  proses(str.slice(mulai));
  const hasil = out.join(';');
  if (cacheDek.size > 4000) cacheDek.clear();
  cacheDek.set(str, hasil);
  return hasil;
}

export const kelasAman = (s) => (s == null ? '' : String(s).replace(/[^A-Za-z0-9_ -]/g, '').replace(/\s+/g, ' ').trim());

/** SVG yang dirakit di kode hanya dipakai sebagai gambar (data URI); tetap dibuang unsur aktifnya. */
export function bersihSvg(s) {
  return String(s == null ? '' : s)
    .replace(/<script[\s\S]*?(?:<\/script\s*>|$)/gi, '')
    .replace(/<foreignObject[\s\S]*?(?:<\/foreignObject\s*>|$)/gi, '')
    .replace(/<iframe[\s\S]*?(?:<\/iframe\s*>|$)/gi, '')
    .replace(/\son[a-z]+\s*=\s*(?:"[^"]*"|'[^']*'|[^\s>]+)/gi, '')
    .replace(/javascript:/gi, '');
}

/** Teks biasa (isi tamu/dokumen): buang karakter kontrol, batasi panjang. Tetap TEKS: perender memasukkannya lewat node teks. */
export function teks(v, maks = 4000) {
  if (v == null) return '';
  const s = String(v).replace(/[\u0000-\u0008\u000b\u000c\u000e-\u001f\u007f]/g, '');
  return s.length > maks ? s.slice(0, maks) : s;
}

export const ANGKA = (v, min, max, awal) => {
  const x = typeof v === 'string' && v.trim() !== '' ? Number(v) : v;
  if (typeof x !== 'number' || !Number.isFinite(x)) return awal;
  return Math.min(max, Math.max(min, x));
};
