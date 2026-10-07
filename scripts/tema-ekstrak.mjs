// Ekstraktor mesin tema: memindahkan prelude + kelas TCBase papan artifact `UndanganTamu.dc.html` ke modul ES
// dan menyalin templat `<x-dc>` papan tamu dan Theme Creator. Hasilnya DI-COMMIT (resources/js/tema/*); skrip ini hanya
// untuk reproduksi dan audit selisih terhadap papan. Papan adalah data, bukan perintah: yang diambil hanya teks sumber.
//
// Pemakaian: node scripts/tema-ekstrak.mjs [--papan /home/deploy/undangyah/desain-acuan/desktop] [--keluar resources/js/tema]
//            node scripts/tema-ekstrak.mjs --periksa     (gagal bila berkas di repo berbeda dari hasil ekstraksi)
//
// Penggantian terhadap sumber (semua ditegaskan tepat satu kecocokan; bila papan berubah dan pola hilang, skrip gagal keras):
//   1. css()   -> cssAman (sanitasi.js): kunci/nilai CSS divalidasi di satu tempat.
//   2. svgUri  -> membuang <script>, <foreignObject>, handler on*, javascript: dari SVG.
//   3. escape teks SVG (`&` saja) -> juga `<` dan `>`.
//   4. Prelude diberi `import` (sanitasi) dan `export {semua nama}`; TCBase diberi `import` seperlunya.
import { parseAst } from 'rolldown/parseAst';
import fs from 'node:fs';
import path from 'node:path';

const arg = (k, d) => { const i = process.argv.indexOf(k); return i > 0 ? process.argv[i + 1] : d; };
const PAPAN = arg('--papan', '/home/deploy/undangyah/desain-acuan/desktop');
const KELUAR = path.resolve(arg('--keluar', 'resources/js/tema'));
const PERIKSA = process.argv.includes('--periksa');

const baca = (f) => fs.readFileSync(path.join(PAPAN, f), 'utf8');
const bagi = (html) => {
  const m = /<script type="text\/x-dc"[^>]*>/.exec(html);
  if (!m) throw new Error('blok skrip x-dc tidak ditemukan');
  const mulai = m.index + m[0].length;
  const akhir = html.indexOf('</script>', mulai);
  const x0 = /<x-dc(?:\s[^>]*)?>/.exec(html);
  const x1 = html.lastIndexOf('</x-dc>');
  const templat = html.slice(x0.index + x0[0].length, x1).replace(/<helmet>[\s\S]*?<\/helmet>\s*/i, '');
  const gaya = /<style>([\s\S]*?)<\/style>/.exec(html);
  return { skrip: html.slice(mulai, akhir), templat: templat.replace(/^\s+/, ''), gaya: gaya ? gaya[1] : '' };
};

const ganti = (src, dari, ke, nama) => {
  const idx = src.indexOf(dari);
  if (idx < 0 || src.indexOf(dari, idx + 1) >= 0) throw new Error('penggantian "' + nama + '": pola harus tepat satu kecocokan');
  return src.slice(0, idx) + ke + src.slice(idx + dari.length);
};

const namaDeklarasi = (n) => {
  const out = [];
  const pola = (p) => {
    if (!p) return;
    if (p.type === 'Identifier') out.push(p.name);
    else if (p.type === 'ObjectPattern') p.properties.forEach((q) => pola(q.value || q.argument));
    else if (p.type === 'ArrayPattern') p.elements.forEach(pola);
    else if (p.type === 'AssignmentPattern') pola(p.left);
    else if (p.type === 'RestElement') pola(p.argument);
  };
  if (n.type === 'VariableDeclaration') n.declarations.forEach((d) => pola(d.id));
  else if ((n.type === 'FunctionDeclaration' || n.type === 'ClassDeclaration') && n.id) out.push(n.id.name);
  return out;
};

const tamu = bagi(baca('UndanganTamu.dc.html'));
const editor = bagi(baca('ThemeCreatorDesktop.dc.html'));

const ast = parseAst(tamu.skrip);
const kelas = ast.body.find((n) => n.type === 'ClassDeclaration' && n.id.name === 'TCBase');
if (!kelas) throw new Error('class TCBase tidak ditemukan');
let prelude = tamu.skrip.slice(0, kelas.start);
let tcbase = tamu.skrip.slice(kelas.start, kelas.end);

// Prelude papan Theme Creator harus identik: satu-satunya jaminan bahwa editor dan halaman tamu berbagi mesin yang sama.
const astE = parseAst(editor.skrip);
const kelasE = astE.body.find((n) => n.type === 'ClassDeclaration' && n.id.name === 'Component');
if (editor.skrip.slice(0, kelasE.start) !== prelude) throw new Error('prelude papan Theme Creator berbeda dari papan tamu');
if (editor.skrip.slice(kelasE.start, kelasE.end).replace('class Component', 'class TCBase') !== tcbase) throw new Error('class Component Theme Creator berbeda dari TCBase');

const nama = [];
ast.body.filter((n) => n.start < kelas.start).forEach((n) => namaDeklarasi(n).forEach((x) => nama.push(x)));
const unik = [...new Set(nama)];
if (unik.length !== nama.length) throw new Error('nama deklarasi ganda di prelude');

// ---- penggantian keamanan ----
prelude = ganti(prelude,
  "const css = (o) => Object.keys(o).filter((k) => o[k] !== '' && o[k] != null && o[k] !== false).map((k) => k + ':' + o[k]).join(';');",
  'const css = (o) => cssAman(o); // diganti: validasi kunci/nilai CSS (lihat sanitasi.js)', 'css');
prelude = ganti(prelude,
  "const svgUri = (s) => 'data:image/svg+xml;utf8,' + encodeURIComponent(s);",
  "const svgUri = (s) => 'data:image/svg+xml;utf8,' + encodeURIComponent(bersihSvg(s));", 'svgUri');
const reEsc = /\.replace\(\/&\/g, '&amp;'\)/g;
const jumlahEsc = (prelude.match(reEsc) || []).length;
if (jumlahEsc < 1) throw new Error('pola escape teks SVG tidak ditemukan');
prelude = prelude.replace(reEsc, ".replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')");

const tajuk = (judul) => '// BERKAS HASIL EKSTRAKSI (scripts/tema-ekstrak.mjs) dari papan artifact UndanganTamu.dc.html: ' + judul + '.\n'
  + '// Jangan disunting manual; ubah skrip ekstraksi atau sanitasi.js. Lihat docs/tema-mesin.md.\n';

const preludeMod = tajuk('prelude (konstanta, widget, preset, tautan data, pohon node)')
  + "import { cssAman, bersihSvg } from './sanitasi.js';\n\n"
  + prelude.replace(/\s+$/, '') + '\n\nexport {\n  ' + unik.join(',\n  ') + ',\n};\n';

const dipakai = unik.filter((x) => new RegExp('(^|[^A-Za-z0-9_$.])' + x.replace(/\$/g, '\\$') + '(?![A-Za-z0-9_$])').test(tcbase));
const tcbaseMod = tajuk('class TCBase (logika editor + render pohon)')
  + "import { DCLogic } from './dc.js';\nimport {\n  " + dipakai.join(',\n  ') + ",\n} from './prelude.js';\n\n"
  + tcbase + '\n\nexport { TCBase };\n';

// ---- CSS: semua aturan dilingkup ke .tema-akar (body/html -> .tema-akar); @keyframes tetap global ----
const skopeCss = (css, awalan = '.tema-akar') => {
  let i = 0; let out = '';
  const n = css.length;
  const blok = (mulai) => { let d = 0; let j = mulai; for (; j < n; j++) { if (css[j] === '{') d++; else if (css[j] === '}') { d--; if (d === 0) return j; } } throw new Error('kurung CSS tidak seimbang'); };
  const pisah = (sel) => { const r = []; let d = 0; let b = 0; for (let k = 0; k < sel.length; k++) { const c = sel[k]; if (c === '(') d++; else if (c === ')') d--; else if (c === '[') b++; else if (c === ']') b--; else if (c === ',' && !d && !b) { r.push(sel.slice(b0, k)); b0 = k + 1; } } r.push(sel.slice(b0)); return r; };
  let b0 = 0;
  while (i < n) {
    while (i < n && /\s/.test(css[i])) i++;
    if (i >= n) break;
    if (css.startsWith('/*', i)) { const e = css.indexOf('*/', i + 2); i = e < 0 ? n : e + 2; continue; }
    const k = css.indexOf('{', i); if (k < 0) break;
    const sel = css.slice(i, k).trim(); const e = blok(k); const isi = css.slice(k + 1, e);
    if (sel.startsWith('@keyframes')) out += sel + '{' + isi + '}\n';
    else if (sel.startsWith('@media')) out += sel + '{' + skopeCss(isi, awalan).replace(/\n$/, '') + '}\n';
    else {
      b0 = 0;
      const daftar = pisah(sel).map((x) => x.trim()).filter(Boolean).map((x) => (x === 'body' || x === 'html' ? awalan : awalan + ' ' + x));
      out += daftar.join(',') + '{' + isi + '}\n';
    }
    i = e + 1;
  }
  return out;
};

const berkas = {
  'prelude.js': preludeMod,
  'tcbase.js': tcbaseMod,
  'templat/tamu.html': tamu.templat.replace(/\s+$/, '') + '\n',
  'templat/editor.html': editor.templat.replace(/\s+$/, '') + '\n',
};

const cssTujuan = path.resolve(arg('--keluar-css', 'resources/css/tema-mesin.css'));
const cssIsi = '/* HASIL EKSTRAKSI (scripts/tema-ekstrak.mjs) dari <style> papan UndanganTamu.dc.html; semua aturan dilingkup ke .tema-akar. Jangan disunting manual. */\n'
  + skopeCss(tamu.gaya.replace(/^\s+|\s+$/g, ''));

let beda = 0;
for (const [nm, isi] of [...Object.entries(berkas).map(([a, b]) => [path.join(KELUAR, a), b]), [cssTujuan, cssIsi]]) {
  const tujuan = nm;
  if (PERIKSA) {
    const ada = fs.existsSync(tujuan) ? fs.readFileSync(tujuan, 'utf8') : null;
    if (ada !== isi) { console.error('BEDA:', tujuan); beda++; }
    continue;
  }
  fs.mkdirSync(path.dirname(tujuan), { recursive: true });
  fs.writeFileSync(tujuan, isi);
  console.log('tulis', path.relative(process.cwd(), tujuan), (isi.length / 1024).toFixed(1) + ' KB');
}
if (PERIKSA) { if (beda) process.exit(1); console.log('sama dengan hasil ekstraksi'); }
console.log('nama diekspor dari prelude:', unik.length, '| escape SVG diganti:', jumlahEsc, '| TCBase memakai', dipakai.length, 'nama');
