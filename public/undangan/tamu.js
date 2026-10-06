/* Perilaku halaman tamu: gerbang buka, musik, hitung mundur, salin, lembar RSVP/ucapan.
   Tanpa dependensi. Teks dari server/tamu hanya dimasukkan lewat textContent, tidak pernah innerHTML. */
(function () {
  'use strict';

  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var html = document.documentElement;
  var root = $('.uy-undangan');
  if (!root) return;

  var cfg = {};
  try { cfg = JSON.parse($('#uyp-konfig').textContent) || {}; } catch (e) { cfg = {}; }
  var kurangGerak = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
  var csrf = (($('meta[name="csrf-token"]') || {}).content) || '';

  // Variabel warna tema dipakai ulang oleh lembar dan tombol musik (di luar .uy-undangan).
  ['acc', 'dep', 'tint', 'bgb', 'page', 'ink', 'mut', 'onacc', 'ona'].forEach(function (k) {
    var v = root.style.getPropertyValue('--uy-' + k);
    if (v) document.body.style.setProperty('--uy-' + k, v);
  });

  // ---------------------------------------------------------------- musik
  var kartuMusik = $('[data-uy-bagian="musik"]', root);
  var sumber = kartuMusik ? (kartuMusik.getAttribute('data-sumber') || '') : '';
  var yt = kartuMusik ? (kartuMusik.getAttribute('data-youtube') || '') : '';
  var tombolMusik = $('[data-uy-aksi="musik"]', root);
  var tombolAmbang = $('#uyp-musik');
  var audio = null;
  var main = false;
  var dijedaPengguna = false;
  var amanUrl = function (u) { return /^https?:\/\/[^\s]+$/i.test(u); };
  var punyaAudio = amanUrl(sumber);

  function labelMusik() {
    if (tombolMusik) tombolMusik.textContent = main ? (tombolMusik.getAttribute('data-label-on') || 'Pause') : (tombolMusik.getAttribute('data-label') || 'Play');
    if (tombolAmbang) {
      tombolAmbang.setAttribute('aria-pressed', main ? 'true' : 'false');
      tombolAmbang.setAttribute('aria-label', main ? 'Jeda musik latar' : 'Putar musik latar');
    }
  }
  function setMusik(nyala, otomatis) {
    if (!punyaAudio) return;
    if (!audio) {
      audio = new Audio();
      audio.loop = true;
      audio.preload = 'none';
      audio.src = sumber;
      audio.addEventListener('error', function () { main = false; labelMusik(); });
    }
    if (nyala) {
      var p = audio.play();
      main = true;
      if (p && p.catch) p.catch(function () { main = false; labelMusik(); });
    } else {
      audio.pause();
      main = false;
      if (!otomatis) dijedaPengguna = true;
    }
    labelMusik();
  }
  if (punyaAudio && tombolAmbang) tombolAmbang.hidden = false;
  if (tombolAmbang) tombolAmbang.addEventListener('click', function () { setMusik(!main); });
  if (tombolMusik) tombolMusik.addEventListener('click', function () {
    if (punyaAudio) setMusik(!main);
    else if (amanUrl(yt)) window.open(yt, '_blank', 'noopener,noreferrer');
  });
  var mulaiOtomatis = function () {
    if (punyaAudio && cfg.musikAuto !== false && kartuMusik && kartuMusik.getAttribute('data-auto') === '1' && !main && !dijedaPengguna) setMusik(true, true);
  };

  // ---------------------------------------------------------------- gerbang
  var sampul = $('.uy-bag-sampul', root);
  var tombolBuka = $('[data-uy-aksi="buka"]', root);
  var gerbang = false;

  function kunciGerbang(on) {
    $$(':scope > section', root).forEach(function (s) {
      if (s !== sampul) { if (on) s.setAttribute('inert', ''); else s.removeAttribute('inert'); }
    });
  }
  function bukaGerbang() {
    if (!gerbang) return;
    gerbang = false;
    html.classList.remove('uyp-gerbang');
    kunciGerbang(false);
    mulaiOtomatis();
    var berikut = sampul.nextElementSibling;
    if (berikut) {
      var y = berikut.getBoundingClientRect().top + window.pageYOffset;
      window.scrollTo({ top: y, behavior: kurangGerak ? 'auto' : 'smooth' });
    }
  }
  if (sampul) {
    gerbang = true;
    html.classList.add('uyp-gerbang');
    kunciGerbang(true);
    window.scrollTo(0, 0);
    if (tombolBuka) tombolBuka.addEventListener('click', bukaGerbang);
    else sampul.addEventListener('click', bukaGerbang);
  }
  if (!sampul || !gerbang) {
    var pertama = function () {
      document.removeEventListener('pointerdown', pertama, true);
      document.removeEventListener('keydown', pertama, true);
      mulaiOtomatis();
    };
    document.addEventListener('pointerdown', pertama, true);
    document.addEventListener('keydown', pertama, true);
  }

  // ---------------------------------------------------------------- hitung mundur
  $$('.uy-hitung[data-target]', root).forEach(function (el) {
    var target = Date.parse(el.getAttribute('data-target'));
    var angka = $$('b', el);
    if (isNaN(target) || angka.length < 4) return;
    var tik = function () {
      var s = Math.max(0, Math.floor((target - Date.now()) / 1000));
      var v = [Math.floor(s / 86400), Math.floor((s % 86400) / 3600), Math.floor((s % 3600) / 60), s % 60];
      for (var i = 0; i < 4; i++) angka[i].textContent = String(v[i]);
      return s > 0;
    };
    if (tik()) {
      var h = setInterval(function () { if (!tik()) clearInterval(h); }, 1000);
    }
  });

  // ---------------------------------------------------------------- salin
  function salinTeks(t) {
    if (navigator.clipboard && navigator.clipboard.writeText) return navigator.clipboard.writeText(t);
    return new Promise(function (ok, gagal) {
      var a = document.createElement('textarea');
      a.value = t; a.setAttribute('readonly', ''); a.style.position = 'fixed'; a.style.opacity = '0';
      document.body.appendChild(a); a.select();
      var berhasil = false;
      try { berhasil = document.execCommand('copy'); } catch (e) { berhasil = false; }
      a.remove();
      berhasil ? ok() : gagal(new Error('salin'));
    });
  }
  $$('[data-uy-aksi="salin"]', root).forEach(function (b) {
    var asli = b.textContent;
    b.addEventListener('click', function () {
      salinTeks(b.getAttribute('data-salin') || '').then(function () { b.textContent = 'Tersalin'; }, function () { b.textContent = 'Gagal'; });
      setTimeout(function () { b.textContent = asli; }, 1800);
    });
  });

  // ---------------------------------------------------------------- ucapan: daftar dan muat lebih banyak
  var daftar = $('.uy-ucapan-daftar', root);
  var kursor = cfg.kursor || 0;
  var adaLagi = !!cfg.adaLagi;
  var tombolMuat = null;

  function butirUcapan(x) {
    var d = document.createElement('div'); d.className = 'uy-ucapan';
    var k = document.createElement('div'); k.className = 'uy-ucapan-k';
    var b = document.createElement('b'); b.textContent = x.nama || '';
    var w = document.createElement('span'); w.textContent = x.waktu || '';
    k.appendChild(b); k.appendChild(w);
    var p = document.createElement('div'); p.textContent = x.pesan || '';
    d.appendChild(k); d.appendChild(p);
    return d;
  }
  function pastikanDaftar() {
    if (daftar) return daftar;
    var bag = $('.uy-bag-rsvp', root);
    if (!bag) return null;
    daftar = document.createElement('div'); daftar.className = 'uy-ucapan-daftar';
    bag.appendChild(daftar);
    return daftar;
  }
  function tambahUcapanBaru(x) {
    var d = pastikanDaftar();
    if (!d) return;
    // Pesan "belum ada ucapan" bukan butir ucapan; hilang begitu ada ucapan pertama.
    $$(':scope > div:not(.uy-ucapan)', d).forEach(function (n) { n.remove(); });
    d.insertBefore(butirUcapan(x), d.firstChild);
  }
  function aturTombolMuat() {
    var d = pastikanDaftar();
    if (!d) return;
    if (!adaLagi) { if (tombolMuat) { tombolMuat.remove(); tombolMuat = null; } return; }
    if (!tombolMuat) {
      tombolMuat = document.createElement('button');
      tombolMuat.type = 'button'; tombolMuat.className = 'uyp-muat'; tombolMuat.textContent = 'Muat ucapan lainnya';
      tombolMuat.addEventListener('click', muatLagi);
      d.parentNode.insertBefore(tombolMuat, d.nextSibling);
    }
  }
  function muatLagi() {
    if (!tombolMuat || tombolMuat.disabled) return;
    tombolMuat.disabled = true;
    fetch(cfg.urlUcapan + '?sebelum=' + encodeURIComponent(kursor || 0), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { return r.json().then(function (j) { return { r: r, j: j }; }); })
      .then(function (o) {
        if (!o.r.ok || !o.j.ok) throw new Error(o.j.pesan || 'gagal');
        (o.j.data || []).forEach(function (x) { daftar.appendChild(butirUcapan(x)); });
        adaLagi = !!o.j.ada_lagi;
        if (o.j.kursor) kursor = o.j.kursor;
        tombolMuat.disabled = false;
        aturTombolMuat();
      })
      .catch(function () { tombolMuat.disabled = false; tombolMuat.textContent = 'Gagal memuat. Coba lagi'; });
  }
  if (adaLagi && daftar) aturTombolMuat();

  // ---------------------------------------------------------------- lembar RSVP / ucapan
  var lembar = $('#uyp-lembar');
  var panel = $('.uyp-panel', lembar);
  var form = $('#uyp-form');
  var fNama = $('#uyp-nama'), fPesan = $('#uyp-pesan'), fSitus = $('#uyp-situs');
  var elGalat = $('#uyp-galat'), btnKirim = $('#uyp-kirim');
  var blokStatus = $('#uyp-blok-status'), blokJumlah = $('#uyp-blok-jumlah');
  var elJumlah = $('#uyp-jumlah');
  var selesai = $('#uyp-selesai'), blokUcapan = $('#uyp-ucapan'), isiUcapan = $('#uyp-ucapan-isi');
  var hitung = $('#uyp-hitung');
  var jenis = 'rsvp';
  var status = 'hadir';
  var jumlah = 1;
  var pemicu = null;
  var mengirim = false;

  function tampilGalat(t) { elGalat.textContent = t || ''; elGalat.hidden = !t; }
  function setStatus(s) {
    status = s;
    $$('#uyp-blok-status button').forEach(function (b) { b.setAttribute('aria-pressed', b.getAttribute('data-status') === s ? 'true' : 'false'); });
    blokJumlah.hidden = !(jenis === 'rsvp' && s === 'hadir' && cfg.tampilJumlah);
  }
  function setJumlah(n) {
    jumlah = Math.max(1, Math.min(cfg.maksJumlah || 20, n));
    elJumlah.textContent = String(jumlah);
  }
  function salinUcapanKeLembar() {
    isiUcapan.textContent = '';
    var sumberList = $$('.uy-ucapan-daftar > .uy-ucapan', root).slice(0, 10);
    sumberList.forEach(function (n) { isiUcapan.appendChild(n.cloneNode(true)); });
    if (!sumberList.length) {
      var kosong = document.createElement('div');
      kosong.className = 'uyp-selesai';
      kosong.textContent = 'Belum ada ucapan. Jadilah yang pertama.';
      isiUcapan.appendChild(kosong);
    }
    blokUcapan.hidden = false;
  }
  function bukaLembar(j, el) {
    if (gerbang) return;
    jenis = j; pemicu = el || null;
    $('#uyp-judul').textContent = j === 'rsvp' ? (cfg.judulRsvp || 'Konfirmasi Kehadiran') : (cfg.judulUcapan || 'Ucapan & Doa');
    btnKirim.textContent = j === 'rsvp' ? (cfg.tombolRsvp || 'Kirim konfirmasi') : (cfg.tombolUcapan || 'Kirim ucapan');
    $('#uyp-lbl-pesan').textContent = j === 'rsvp' ? 'Ucapan & doa (opsional)' : 'Ucapan & doa';
    blokStatus.hidden = j !== 'rsvp';
    form.hidden = false; selesai.hidden = true; tampilGalat('');
    blokUcapan.hidden = true;
    if (!fNama.value) fNama.value = cfg.nama || '';
    fPesan.value = ''; hitung.textContent = '0/500'; fSitus.value = '';
    setStatus(status); setJumlah(jumlah);
    if (j === 'ucapan') salinUcapanKeLembar();
    lembar.hidden = false;
    html.classList.add('uyp-lembar-buka');
    panel.scrollTop = 0;
    setTimeout(function () { (fNama.value ? fPesan : fNama).focus({ preventScroll: true }); }, 30);
  }
  function tutupLembar() {
    lembar.hidden = true;
    html.classList.remove('uyp-lembar-buka');
    if (pemicu && pemicu.focus) pemicu.focus();
  }

  root.addEventListener('click', function (e) {
    var t = e.target.closest ? e.target.closest('[data-uy-bagian="rsvp"],[data-uy-bagian="ucapan"]') : null;
    if (t) { e.preventDefault(); bukaLembar(t.getAttribute('data-uy-bagian'), t); }
  });
  root.addEventListener('keydown', function (e) {
    if (e.key !== 'Enter' && e.key !== ' ') return;
    var t = e.target.closest ? e.target.closest('[data-uy-bagian="rsvp"],[data-uy-bagian="ucapan"]') : null;
    if (t && e.target === t) { e.preventDefault(); bukaLembar(t.getAttribute('data-uy-bagian'), t); }
  });
  $$('[data-uyp-tutup]', lembar).forEach(function (b) { b.addEventListener('click', tutupLembar); });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !lembar.hidden) { tutupLembar(); return; }
    if (e.key === 'Tab' && !lembar.hidden) {
      var f = $$('button:not([hidden]):not([disabled]),input:not([tabindex="-1"]),textarea', panel).filter(function (x) { return x.offsetParent !== null; });
      if (!f.length) return;
      var a = f[0], z = f[f.length - 1];
      if (e.shiftKey && document.activeElement === a) { e.preventDefault(); z.focus(); }
      else if (!e.shiftKey && document.activeElement === z) { e.preventDefault(); a.focus(); }
    }
  });
  $$('#uyp-blok-status button').forEach(function (b) { b.addEventListener('click', function () { setStatus(b.getAttribute('data-status')); }); });
  $('#uyp-kurang').addEventListener('click', function () { setJumlah(jumlah - 1); });
  $('#uyp-tambah').addEventListener('click', function () { setJumlah(jumlah + 1); });
  fPesan.addEventListener('input', function () { hitung.textContent = fPesan.value.length + '/500'; tampilGalat(''); });
  fNama.addEventListener('input', function () { tampilGalat(''); });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (mengirim) return;
    var nama = fNama.value.trim(), pesan = fPesan.value.trim();
    if (!nama) { tampilGalat('Isi nama Anda dulu.'); fNama.focus(); return; }
    if (jenis === 'ucapan' && !pesan) { tampilGalat('Tulis ucapan Anda dulu.'); fPesan.focus(); return; }
    var badan = { nama: nama, pesan: pesan, kode: cfg.kode || '', situs_web: fSitus.value };
    if (jenis === 'rsvp') { badan.status = status; if (status === 'hadir' && cfg.tampilJumlah) badan.jumlah = jumlah; }
    mengirim = true; btnKirim.disabled = true; tampilGalat('');
    fetch(jenis === 'rsvp' ? cfg.urlRsvp : cfg.urlUcapan, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(badan)
    }).then(function (r) {
      return r.text().then(function (t) { var j = {}; try { j = JSON.parse(t); } catch (x) {} return { r: r, j: j }; });
    }).then(function (o) {
      if (o.r.status === 419) throw new Error('Sesi halaman sudah habis. Muat ulang halaman lalu coba lagi.');
      if (!o.r.ok || !o.j.ok) throw new Error(o.j.pesan || 'Gagal mengirim. Coba lagi sebentar lagi.');
      cfg.nama = nama;
      if (o.j.ucapan) tambahUcapanBaru(o.j.ucapan);
      form.hidden = true; selesai.hidden = false;
      $('#uyp-selesai-judul').textContent = 'Terima kasih, ' + (o.j.nama || nama) + '!';
      $('#uyp-selesai-isi').textContent = o.j.pesan || '';
      salinUcapanKeLembar();
      panel.scrollTop = 0;
    }).catch(function (err) {
      tampilGalat(err && err.message && err.message !== 'Failed to fetch' ? err.message : 'Tidak dapat terhubung. Periksa koneksi Anda lalu coba lagi.');
    }).then(function () { mengirim = false; btnKirim.disabled = false; });
  });
})();
