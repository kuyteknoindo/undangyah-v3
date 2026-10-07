# Catatan paritas per papan artifact

Satu berkas per papan: `docs/paritas/<Papan>.md` (nama papan tanpa `.dc.html`). Format (baris pertama berurutan, lalu isi bebas):

```
rute: <nama rute Laravel, mis. beranda | admin.product | publik: /{slug}>
status: sama | beda desain | beda fungsi | belum ada
diperiksa: <YYYY-MM-DD>, lebar 1440 (dan lebar lain bila diuji)
```

Lalu bagian `## Selisih` (daftar selisih yang MASIH ada; kosong bila status `sama`), `## Elemen/state/interaksi papan` (hasil membaca berkas papan: elemen, state, handler, kunci data) dan `## Bukti` (nama tangkapan layar di `qa/shots/`, pasangan `desain-<Papan>-1440.png` dan `build-<Papan>-1440.png`). Jangan menulis "sama" tanpa membandingkan tangkapan layar berdampingan dan menguji interaksi.
`PARITAS.md` (akar repo) disusun koordinator dari berkas-berkas ini; jangan diedit agen.
