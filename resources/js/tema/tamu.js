// Mode TAMU: halaman undangan publik. Port setia `class Component extends TCBase` + vwBuild/vwLive papan
// UndanganTamu.dc.html. Perbedaan yang disengaja (semuanya tercatat di docs/paritas/_mesin.md):
//   - tanpa localStorage: rekaman datang dari opsi `rec` (lihat App\Services\DataTamu); RSVP/ucapan/pelacakan lewat
//     adaptor `aksi` (kirim, ucapan, lacak) yang diisi halaman PHP; tanpa postMessage/hash/query dari URL.
//   - semua data luar disanitasi (dokumen.js) sebelum dirender; URL musik hanya http(s)/path situs.
//   - kerangka pemuatan (UDSK) opsional (`opsi.kerangka`), hitung mundur berdetak tiap detik (`opsi.denyut`).
// Satu instance per halaman (TCBase memakai id #tcScroll/#tcPage/#tcWrap).
import { TCBase } from './tcbase.js';
import { pasang } from './dc.js';
import {
  starter, validDoc, THEME0, SAMPLE_ASSETS, gbgOf, mbSetLive, mbHidden, secGateId, secHasBtn, mix, lum, onTone, MBD,
} from './prelude.js';
import { sanitasiDoc, sanitasiRec } from './dokumen.js';
import { urlMedia, hex } from './sanitasi.js';
import { TEMPLAT_TAMU } from './templat.js';

const KUNCI_FOTO = ['cover', 'latar', 'penutup', 'pria', 'wanita', 'share', 'bgf', 'bgs1', 'bgs2', 'bgs3', 'bgs4'];

// ---------- data hidup (setara vwLive) ----------
export const dataHidup = (rec, q) => {
  const D = rec.data || {}; const d = D.d || {}; const hid = D.hid || {}; const lists = D.lists || {}; const photos = D.photos || {}; const bgf = D.bgf || {};
  const lib = {}; (D.lib || []).forEach((x) => { if (x && x.id) lib[x.id] = x; });
  const srcOf = (slot) => { const p = photos[slot]; const it = p && lib[p.lib]; return it && it.src ? String(it.src) : ''; };
  const val = (k) => (hid[k] ? '' : (d[k] == null ? '' : String(d[k])));
  const rows = (src) => { const L = MBD.L[src]; if (!L) return []; return (lists[src] || []).map((id) => L.f.map((f) => { const k = src + id + '_' + f[0]; return hid[k] ? '' : (d[k] == null ? '' : String(d[k])); })); };
  const gl = () => (lists.gl || []).map((id) => srcOf('g' + id)).filter(Boolean);
  const bgOn = !!bgf.on;
  const live = {
    val, rows, gallery: gl, gvideo: () => String((D.gset && D.gset.video) || ''),
    guestKeys: { tmNama: 1, tmAlamat: 1, tmKode: 1 },
    guest: (k) => (k === 'tmNama' ? (q.to || val('tmFallback')) : k === 'tmAlamat' ? (q.al || '') : (q.kd || '')),
    photoId: (slot) => { if (slot === 'bgf' && !bgOn) return ''; return srcOf(slot) ? 'lv_' + slot : ''; },
  };
  const assets = []; KUNCI_FOTO.forEach((s) => { const x = srcOf(s); if (x) assets.push({ id: 'lv_' + s, name: s, ar: 1, src: x }); });
  return { live, assets, srcOf };
};

// ---------- membangun dokumen dari rekaman (setara vwBuild) ----------
export const bangunTamu = (recMentah, q) => {
  const rec = sanitasiRec(recMentah);
  const th = rec.theme || {}; const row = th.row || {};
  const bersih = th.doc ? sanitasiDoc(th.doc) : { doc: null, catatan: [] };
  const doc = bersih.doc;
  let nodes; let root; let nid; let T; let upl = [];
  if (doc && validDoc(doc)) { nodes = JSON.parse(JSON.stringify(doc.nodes)); root = doc.root.slice(); nid = doc.nid || 300; T = Object.assign({}, THEME0, doc.theme); upl = doc.assets || []; } else {
    const st = starter(); nodes = st.nodes; root = st.root; nid = st.nid;
    T = Object.assign({}, THEME0, { primary: row.primary || THEME0.primary, accent: row.accent || THEME0.accent, surface: row.surface || THEME0.surface, page: row.page || THEME0.page, text: row.text || THEME0.text, head: row.head || THEME0.head, snap: row.snap === false ? 0 : 1 });
  }
  const L = dataHidup(rec, q); const D = rec.data || {}; const b = D.bgf || {};
  const G = gbgOf(T);
  if (G.on && G.link !== false) {
    if (!b.on) T.gbg = Object.assign({}, T.gbg || {}, { on: false });
    else {
      const sl = (i) => (L.srcOf('bgs' + i) ? 'lv_bgs' + i : '');
      T.gbg = Object.assign({}, T.gbg || {}, { kind: b.kind || 'image', img: L.srcOf('bgf') ? 'lv_bgf' : G.img, vid: b.vsrc || b.vurl || G.vid, sl1: sl(1) || G.sl1, sl2: sl(2) || G.sl2, sl3: sl(3) || G.sl3, sl4: sl(4) || G.sl4, sd: b.sd || G.sd, oc: hex(b.oc) || G.oc, oo: b.oo != null ? b.oo : G.oo, op: b.op != null ? b.op : G.op, blur: b.blur != null ? b.blur : G.blur });
    }
  }
  mbSetLive(L.live);
  const hid = mbHidden(nodes, root, D.hidSec);
  Object.keys(hid).forEach((id) => { if (nodes[id]) nodes[id] = Object.assign({}, nodes[id], { hidden: true }); });
  // status & kolom khusus pratinjau desain dimatikan: yang tampil hanya data asli
  const V0 = L.live.val; const lvStart = (() => { const t = Date.parse(V0('lvTgl') + 'T' + (V0('lvMulai') || '00:00') + ':00+07:00'); return isNaN(t) ? 0 : t; })(); const nowT = Date.now();
  Object.keys(nodes).forEach((id) => {
    const n = nodes[id]; if (!n) return; const pp = {};
    if (n.type === 'qr') pp.showModal = false;
    else if (n.type === 'wish') pp.n = 0;
    else if (n.type === 'live') { pp.platform = V0('lvPlat') || n.p.platform; pp.demo = !V0('lvLink') ? 'empty' : (lvStart && nowT >= lvStart && nowT < lvStart + 6 * 3600 * 1000 ? 'live' : 'upcoming'); if (V0('lvBtn') === '0') nodes[id] = Object.assign({}, n, { hidden: true }); }
    if (Object.keys(pp).length) nodes[id] = Object.assign({}, nodes[id], { p: Object.assign({}, nodes[id].p, pp) });
  });
  if (nodes.gmusic) nodes.gmusic = Object.assign({}, nodes.gmusic, { p: Object.assign({}, nodes.gmusic.p, { previewState: 'off', showVolume: false }) });
  return { nodes, root, nid, theme: T, assets: SAMPLE_ASSETS.concat(upl, L.assets), live: L.live, musUrl: urlMedia(L.live.val('musUrl')), catatan: bersih.catatan, rec };
};

const PESAN_GALAT = {
  pick: ['Pilih undangan', 'Buka undangan lewat link yang dibagikan pengantin.'],
  none: ['Undangan tidak ditemukan', 'Link ini belum terdaftar atau sudah dihapus. Periksa kembali link dari pengantin.'],
  draft: ['Undangan belum diterbitkan', 'Pemilik undangan belum menerbitkannya atau sedang mengubahnya. Coba lagi nanti.'],
  bad: ['Undangan tidak dapat ditampilkan', 'Data undangan rusak. Hubungi pengantin untuk menerbitkan ulang.'],
};

const udRel = (iso, now) => { const t = Date.parse(iso); if (!t) return ''; const df = Math.max(0, ((now || Date.now()) - t) / 1000); if (df < 60) return 'baru saja'; if (df < 3600) return Math.floor(df / 60) + ' mnt lalu'; if (df < 86400) return Math.floor(df / 3600) + ' jam lalu'; return Math.floor(df / 86400) + ' hari lalu'; };

export class TamuLogic extends TCBase {
  constructor(props) {
    super(props);
    const o = props.opsi || {}; const rec = props.rec || null;
    this.o = o;
    this.vq = { u: rec && rec.slug ? String(rec.slug).toLowerCase() : '', to: String((props.q && props.q.to) || ''), al: String((props.q && props.q.al) || ''), kd: String((props.q && props.q.kd) || '') };
    let err = '';
    if (!rec) err = 'none'; else if (rec.status !== 'publish' && !o.pratinjau) err = 'draft';
    let b = null;
    if (!err) { try { b = bangunTamu(rec, this.vq); } catch (e) { console.error('[tema] bangunTamu gagal:', e); err = 'bad'; } }
    this.vb = b; this.vrec = b ? b.rec : null; this.__gid = b ? secGateId(b.nodes, b.root) : '';
    this.state = Object.assign({}, this.state, b ? { nodes: b.nodes, root: b.root, nid: b.nid, theme: b.theme, assets: b.assets } : {}, { pv: true, zoom: 1, vh: 800, vwErr: err, musPlay: false, gate: !!this.__gid, sheet: '', vNama: this.vq.to || '', vStatus: 'hadir', vJml: 1, vPesan: '', vErr: '', vDone: '', vBusy: false, vUcapan: [], tick: 0 });
  }

  get wadah() { return this.props.wadah || document; }

  componentDidMount() {
    this.__rs = () => this.vwMeasure(); try { window.addEventListener('resize', this.__rs); } catch (e) {}
    this.vwMeasure();
    try {
      this.__ck = (e) => this.vwClick(e); (this.props.wadah || document).addEventListener('click', this.__ck, true);
      if (this.props.aksi && this.props.aksi.lacak && this.vrec && this.vrec.status === 'publish' && this.o.pratinjau !== true) { try { this.props.aksi.lacak({ to: this.vq.to }); } catch (e) {} }
      if (this.o.perbaruiMeta) this.vwMeta();
      if (!this.__gid && this.vwMusAuto()) { this.__ap = () => { try { document.removeEventListener('pointerdown', this.__ap, true); document.removeEventListener('keydown', this.__ap, true); } catch (e) {} if (!this.state.musPlay) this.vwMus(); }; document.addEventListener('pointerdown', this.__ap, true); document.addEventListener('keydown', this.__ap, true); }
      if (this.o.denyut !== false && this.state.nodes && Object.keys(this.state.nodes).some((k) => this.state.nodes[k] && this.state.nodes[k].type === 'count')) { this.__tk = setInterval(() => { if (!document.hidden) this.setState({ tick: Date.now() }); }, 1000); }
      if (this.o.kerangka) { this.__sk = setTimeout(() => this.setState({ __udSkDone: 1 }), 650); }
    } catch (e) {}
  }

  componentWillUnmount() {
    try { document.removeEventListener('pointerdown', this.__ap, true); document.removeEventListener('keydown', this.__ap, true); (this.props.wadah || document).removeEventListener('click', this.__ck, true); window.removeEventListener('resize', this.__rs); clearInterval(this.__tk); clearTimeout(this.__sk); if (this.__au) { this.__au.pause(); this.__au = null; } } catch (e) {}
    super.componentWillUnmount();
  }

  componentDidUpdate() {
    this.bindWheel();
    try { const im = document.querySelectorAll('#tcPage img:not([data-lz])'); for (let i = 0; i < im.length; i++) { im[i].setAttribute('data-lz', '1'); im[i].setAttribute('loading', 'lazy'); im[i].setAttribute('decoding', 'async'); } } catch (e) {}
  }

  /** Ganti rekaman tanpa memasang ulang (pratinjau langsung). */
  gantiRec(rec) {
    try {
      const b = bangunTamu(rec, this.vq); this.vb = b; this.vrec = b.rec; this.__gid = '';
      this.setState({ nodes: b.nodes, root: b.root, nid: b.nid, theme: b.theme, assets: b.assets, vwErr: '', gate: false });
    } catch (e) { this.setState({ vwErr: 'bad' }); }
  }

  vwMeta() {
    const r = this.vrec; if (!r) return; const d = (r.data && r.data.d) || {}; const title = (r.judul || 'Undangan') + (this.vq.to ? ' · untuk ' + this.vq.to : '');
    document.title = title; const desc = [d.tglCover, d.gcLokasi].filter(Boolean).join(' · ');
    const set = (pr, c) => { let m = document.querySelector('meta[property="' + pr + '"]'); if (!m) { m = document.createElement('meta'); m.setAttribute('property', pr); document.head.appendChild(m); } m.setAttribute('content', c); };
    set('og:title', title); set('og:description', desc || 'Anda diundang. Buka undangan digital ini.'); set('og:type', 'website');
  }

  vwMusAuto() { const g = this.state.nodes.gmusic; return !!(g && g.p.on !== false && g.p.autoplay !== false && this.vb && this.vb.musUrl); }

  vwOpen() {
    if (this.__opening) return; this.__opening = true; const gid = this.__gid;
    try { if (this.vwMusAuto() && !this.state.musPlay) this.vwMus(); } catch (e) {}
    const done = () => { this.__opening = false; this.setState({ gate: false, nodes: Object.assign({}, this.state.nodes, gid && this.state.nodes[gid] ? { [gid]: Object.assign({}, this.state.nodes[gid], { hidden: true }) } : {}) }); };
    try {
      const sc = document.getElementById('tcScroll'); const pg = document.getElementById('tcPage'); const nodes = this.state.nodes;
      const kid = (id) => pg && Array.prototype.find.call(pg.children, (c) => c.getAttribute && c.getAttribute('data-nid') === id); const el = kid(gid); const nid2 = this.state.root.slice(this.state.root.indexOf(gid) + 1).find((id) => nodes[id] && nodes[id].type === 'section' && !nodes[id].hidden); const nx = nid2 && kid(nid2);
      if (!sc || !el || !nx) { done(); return; }
      const rm = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      const top = nx.getBoundingClientRect().top - sc.getBoundingClientRect().top + sc.scrollTop; const from = sc.scrollTop; const dur = rm ? 0 : 900; const t0 = performance.now();
      const sty = { st: sc.style.scrollSnapType, sb: sc.style.scrollBehavior }; sc.style.scrollSnapType = 'none'; sc.style.scrollBehavior = 'auto';
      const fin = () => { el.style.display = 'none'; sc.scrollTop = 0; sc.style.scrollSnapType = sty.st; sc.style.scrollBehavior = sty.sb; done(); };
      if (!dur) { fin(); return; }
      const ease = (t) => (t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2);
      const step = (now) => { const t = Math.min(1, (now - t0) / dur); sc.scrollTop = from + (top - from) * ease(t); if (t < 1) requestAnimationFrame(step); else fin(); };
      requestAnimationFrame(step);
    } catch (e) { done(); }
  }

  vwClick(e) {
    const s = this.state; if (s.vwErr || !e || !e.target || !e.target.closest) return;
    const el = e.target.closest('[data-nid]'); if (!el) return; const id = el.getAttribute('data-nid'); const n = s.nodes[id]; if (!n) return;
    if (s.gate) {
      const chain = []; let cur = el; while (cur) { chain.push(cur.getAttribute('data-nid')); cur = cur.parentElement && cur.parentElement.closest ? cur.parentElement.closest('[data-nid]') : null; }
      if (chain.indexOf(this.__gid) >= 0 && (!secHasBtn(s.nodes, this.__gid) || chain.some((x) => s.nodes[x] && s.nodes[x].type === 'button'))) this.vwOpen();
      return;
    }
    if (n.type === 'rsvp') this.vwSheet('rsvp', n); else if (n.type === 'wish') this.vwSheet('wish', n);
  }

  vwSheet(kind, n) {
    const s = this.state; this.setState({ sheet: kind, sQty: !!(n && n.p && n.p.showQty), vErr: '', vDone: '', vNama: s.vNama || this.vq.to || '', vStatus: s.vStatus || 'hadir', vJml: s.vJml || 1, vPesan: '' });
    if (kind === 'wish') this.vwMuatUcapan();
  }

  vwReal() { return !!(this.vrec && this.vq.u && this.vrec.status === 'publish' && this.o.pratinjau !== true); }

  vwMuatUcapan() {
    const a = this.props.aksi; if (!a || !a.ucapan || !this.vwReal()) return;
    Promise.resolve().then(() => a.ucapan()).then((l) => { if (Array.isArray(l)) this.setState({ vUcapan: l.slice(0, 30).map((w) => ({ nama: String(w.nama || ''), pesan: String(w.pesan || ''), waktu: String(w.waktu || (w.at ? udRel(w.at, Date.now()) : '')) })) }); }).catch(() => {});
  }

  /** RSVP/ucapan: validasi dasar di klien, penyimpanan lewat adaptor `aksi.kirim` (server memvalidasi ulang). */
  vwSend(kind) {
    const s = this.state; const nama = String(s.vNama || '').trim(); const pesan = String(s.vPesan || '').trim();
    if (s.vBusy) return;
    if (!nama) { this.setState({ vErr: 'Isi nama Anda dulu.' }); return; }
    if (kind === 'wish' && !pesan) { this.setState({ vErr: 'Tulis ucapan Anda dulu.' }); return; }
    const selesai = () => { this.setState({ vDone: kind, vErr: '', vPesan: '', vBusy: false }); if (kind === 'wish' || pesan) this.vwMuatUcapan(); };
    const a = this.props.aksi;
    if (this.vwReal() && a && a.kirim) {
      this.setState({ vBusy: true, vErr: '' });
      Promise.resolve().then(() => a.kirim({ jenis: kind, nama, hadir: s.vStatus === 'tidak' ? 'tidak' : 'hadir', jumlah: s.vStatus === 'tidak' ? 0 : Math.max(1, +s.vJml || 1), pesan, to: this.vq.to }))
        .then((r) => { if (r && r.ok === false) this.setState({ vErr: String(r.galat || 'Gagal mengirim. Coba lagi.'), vBusy: false }); else selesai(); })
        .catch(() => this.setState({ vErr: 'Gagal mengirim. Periksa koneksi lalu coba lagi.', vBusy: false }));
      return;
    }
    selesai();
  }

  measure() {}

  vwMeasure() {
    try { const w = window.innerWidth || 500; const h = window.innerHeight || 800; const zoom = Math.min(1, Math.round(w / 500 * 1000) / 1000); const vh = Math.round(h / zoom); if (zoom !== this.state.zoom || Math.abs(vh - this.state.vh) > 1) this.setState({ zoom, vh }); } catch (e) {}
  }

  vwMus() {
    const s = this.state; const g = s.nodes.gmusic; if (!g) return; const on = !s.musPlay;
    try {
      const url = this.vb && this.vb.musUrl;
      if (url) { if (!this.__au) { this.__au = new Audio(url); this.__au.loop = true; } if (on) { const pr = this.__au.play(); if (pr && pr.catch) pr.catch(() => {}); } else this.__au.pause(); }
    } catch (e) {}
    this.setState({ musPlay: on, nodes: Object.assign({}, s.nodes, { gmusic: Object.assign({}, g, { p: Object.assign({}, g.p, { previewState: on ? 'on' : 'off' }) }) }) });
  }

  renderVals() {
    const s = this.state; const err = s.vwErr;
    if (this.vb) mbSetLive(this.vb.live);
    const base = err ? { gb: { on: false }, mus: { on: false, rows: [], cs: '', sticky: '' }, root: [], pageSty: '', pvCls: '' } : super.renderVals();
    const T = s.theme; const z = s.zoom || 1; let rmo = false; try { rmo = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches); } catch (e) { /* abaikan */ }
    const snap = !!(T.snap !== 0 && T.snap !== false);
    const bg = mix(T.surface || '#F3F6FB', T.page || '#FFFFFF', lum(T.page) < 0.4 ? 0.0 : 0.35);
    const msg = PESAN_GALAT[err || 'pick'];
    const vw = {
      ok: !err, wait: false, bad: !!err, title: msg[0], body: msg[1], list: [], hasList: false, bg: err ? '#F4F5F7' : bg,
      scrollSty: 'position:fixed;left:0;top:0;right:0;bottom:0;overflow-x:hidden;overflow-y:' + (s.gate ? 'hidden' : 'auto') + ';background:' + bg + ';display:flex;flex-direction:column;align-items:center;' + (snap && !rmo ? 'scroll-snap-type:y mandatory;scroll-behavior:smooth;scroll-padding:0;' : ''),
      wrapSty: 'position:relative;flex:none;width:500px;zoom:' + z + ';',
    };
    const mus = base.mus; if (mus && mus.on) mus.down = () => this.vwMus();
    const ink = T.text || '#10203A'; const pg = T.page || '#FFFFFF'; const isW = s.sheet === 'wish'; const done = s.vDone;
    const wl = s.sheet && this.vwReal() ? (s.vUcapan || []) : [];
    const chip = (on) => 'flex:1;min-height:46px;border-radius:14px;box-sizing:border-box;font-size:14px;font-weight:700;text-align:center;' + (on ? 'background:' + T.primary + ';color:' + onTone(T.primary) + ';border:2px solid ' + T.primary + ';' : 'background:transparent;color:' + ink + ';border:2px solid ' + mix(ink, pg, 0.8) + ';');
    vw.sheetOn = !!s.sheet && !err; vw.gateOn = !!s.gate && !err;
    vw.sh = {
      bg: pg, ink, mut: mix(ink, pg, 0.45), line: mix(ink, pg, 0.82), fld: mix(ink, pg, 0.95), pri: T.primary, onPri: onTone(T.primary),
      title: isW ? 'Ucapan & Doa' : 'Konfirmasi Kehadiran', isRsvp: !isW, isWish: isW, form: !done, done: !!done,
      nama: s.vNama || '', namaChg: (e) => this.setState({ vNama: e.target.value, vErr: '' }), pesan: s.vPesan || '', pesanChg: (e) => this.setState({ vPesan: e.target.value, vErr: '' }),
      pesanLabel: isW ? 'Ucapan & doa' : 'Ucapan & doa (opsional)', hadirSty: chip(s.vStatus !== 'tidak'), tidakSty: chip(s.vStatus === 'tidak'), pickHadir: () => this.setState({ vStatus: 'hadir' }), pickTidak: () => this.setState({ vStatus: 'tidak' }),
      qty: !!s.sQty && s.vStatus !== 'tidak' && !isW, jml: s.vJml || 1, minus: () => this.setState({ vJml: Math.max(1, (+s.vJml || 1) - 1) }), plus: () => this.setState({ vJml: Math.min(10, (+s.vJml || 1) + 1) }),
      err: s.vErr || '', hasErr: !!s.vErr, send: () => this.vwSend(isW ? 'wish' : 'rsvp'), sendText: s.vBusy ? 'Mengirim…' : (isW ? 'Kirim ucapan' : 'Kirim konfirmasi'), close: () => this.setState({ sheet: '', vDone: '', vErr: '' }),
      doneTitle: 'Terima kasih, ' + (s.vNama || 'Tamu') + '!', doneBody: done === 'rsvp' ? (s.vStatus === 'tidak' ? 'Konfirmasi Anda sudah kami terima. Kami tetap berterima kasih atas doanya.' : 'Kehadiran Anda tercatat' + (s.sQty ? ' untuk ' + (s.vJml || 1) + ' orang' : '') + '. Sampai jumpa di hari bahagia kami.') : 'Ucapan Anda sudah kami terima dan tampil di daftar di bawah.',
      note: this.vwReal() ? '' : 'Ini pratinjau, jawaban tidak disimpan.', hasNote: !this.vwReal(), wishes: wl, hasWishes: wl.length > 0, noWish: !wl.length, showWishes: isW || !!done,
    };
    const hasil = Object.assign({}, base, { vw, mus, pageSty: (base.pageSty || '').replace(/box-shadow:[^;]*;?/, '').replace(/min-height:[^;]*;/, 'min-height:' + Math.round((s.vh || 800)) + 'px;'), pvCls: 'tcpv' });
    hasil.udSkOn = !!this.o.kerangka && !s.__udSkDone;
    return hasil;
  }
}

/**
 * Pasang halaman tamu ke `wadah` (elemen mana pun; tambahkan kelas `tema-akar` pada wadah).
 * opsi: { rec, q:{to,al,kd}, aksi:{kirim, ucapan, lacak}, pratinjau, kerangka, denyut, perbaruiMeta, onGalat }
 * Mengembalikan { logic, perbarui(rec), hancurkan() }.
 */
export function mulaiTamu(wadah, opsi) {
  const o = opsi || {};
  wadah.classList.add('tema-akar');
  const h = pasang(TamuLogic, TEMPLAT_TAMU, wadah, { rec: o.rec, q: o.q || {}, aksi: o.aksi || {}, opsi: o, wadah, onGalat: o.onGalat });
  return { logic: h.logic, perbarui: (rec) => h.logic.gantiRec(rec), hancurkan: () => h.hancurkan() };
}
