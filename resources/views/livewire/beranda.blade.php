@php
    $angka = fn (int|float $n): string => number_format($n, 0, ',', '.');
    $judulBagian = 'text-base leading-6 font-semibold text-ink';
    $labelKecil = 'text-[11px] leading-[14px] font-bold tracking-[0.08em] text-muted uppercase';
    $filters = [['semua', 'Semua'], ['terbit', 'Terbit'], ['draf', 'Draf']];
    $kolom = 'md:grid-cols-[minmax(0,2.4fr)_100px_110px_64px_72px_64px_minmax(190px,auto)]';
@endphp
<div wire:init="muat" class="mx-auto flex max-w-6xl flex-col gap-6">

    {{-- Kerangka pemuat --}}
    @unless ($siap)
        <div role="status" aria-busy="true" class="flex flex-col gap-6">
            <span class="sr-only">Memuat beranda…</span>
            <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_340px]">
                <div class="kartu flex flex-col gap-4 p-5">
                    <div class="flex items-center gap-4"><span class="skel size-14 shrink-0 rounded-2xl"></span><div class="flex flex-1 flex-col gap-2.5"><span class="skel h-3 w-28"></span><span class="skel h-5 w-3/5"></span></div></div>
                    <div class="flex gap-3"><span class="skel h-4 w-32"></span><span class="skel h-4 w-40"></span></div>
                    <span class="skel h-11 w-full"></span>
                </div>
                <div class="kartu flex flex-col gap-3 p-5"><span class="skel h-3 w-32"></span><span class="skel h-6 w-24"></span><span class="skel h-2 w-full"></span><span class="skel mt-2 h-14 w-full"></span></div>
            </div>
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                @for ($i = 0; $i < 4; $i++)
                    <div class="kartu flex flex-col gap-3 p-4"><span class="skel h-3 w-20"></span><span class="skel h-7 w-16"></span></div>
                @endfor
            </div>
            <div class="grid gap-4 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
                <div class="kartu p-5"><span class="skel h-4 w-40"></span><span class="skel mt-4 h-36 w-full"></span></div>
                <div class="kartu p-5"><span class="skel h-4 w-36"></span><span class="skel mt-4 h-36 w-full"></span></div>
            </div>
            <div class="kartu flex flex-col gap-3 p-5">
                <span class="skel h-4 w-32"></span>
                @for ($i = 0; $i < 3; $i++)
                    <div class="flex items-center gap-3"><span class="skel size-10 shrink-0"></span><span class="skel h-3.5 flex-1"></span><span class="skel hidden h-3.5 w-24 sm:block"></span></div>
                @endfor
            </div>
        </div>
    @elseif ($data === null)
        {{-- Keadaan kosong: belum punya undangan --}}
        <section aria-labelledby="kosong-judul" class="kartu grid items-center gap-8 overflow-hidden p-6 sm:p-8 lg:grid-cols-[minmax(0,1fr)_340px]">
            <div>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-50 px-2.5 py-1 text-xs font-semibold text-brand"><x-ikon nama="beranda-sorot" :ukuran="13" />Akun baru</span>
                <h2 id="kosong-judul" class="mt-3 text-2xl leading-8 font-semibold tracking-tight text-ink">Buat undangan digital pertamamu</h2>
                <p class="mt-2 max-w-xl text-sm leading-6 text-muted">Pilih tema, isi data acara, lalu bagikan link pribadi ke setiap tamu. RSVP, ucapan, dan kunjungan akan terpantau di beranda ini.</p>
                <div class="mt-5 flex flex-wrap gap-2.5">
                    <a href="{{ $tautanBuat }}" wire:navigate class="btn btn-primary"><x-ikon nama="tambah" :ukuran="16" /> Buat undangan</a>
                    <button type="button" wire:click="tampilkanContoh" class="btn btn-secondary"><x-ikon nama="beranda-lihat" :ukuran="16" /> Lihat contoh dashboard</button>
                </div>
                <p class="hint mt-3">Contoh hanya ilustrasi dan bisa disembunyikan lagi kapan saja.</p>
            </div>
            <div aria-hidden="true" class="hidden rounded-2xl bg-brand-50 p-5 lg:block">
                <div class="overflow-hidden rounded-xl border border-brand-200 bg-white shadow-[0_8px_20px_rgba(29,78,216,0.10)]">
                    <div class="flex flex-col items-center gap-1 bg-brand px-4 py-7 text-center text-white">
                        <span class="text-[10px] font-semibold tracking-[0.2em] opacity-80">THE WEDDING OF</span>
                        <span class="text-xl font-semibold">Nama &amp; Nama</span>
                    </div>
                    <div class="flex flex-col items-center gap-2 px-4 py-5">
                        <span class="skel h-2 w-3/4"></span><span class="skel h-2 w-1/2"></span>
                        <span class="mt-1 rounded-full bg-brand px-4 py-1.5 text-xs font-semibold text-white">Konfirmasi kehadiran</span>
                    </div>
                </div>
            </div>
        </section>

        <section aria-labelledby="langkah-judul">
            <h2 id="langkah-judul" class="{{ $judulBagian }} mb-3">Tiga langkah menuju undangan siap</h2>
            <div class="grid gap-3 sm:grid-cols-3">
                @foreach ([
                    ['no' => 1, 'judul' => 'Pilih tema', 'isi' => 'Mulai dari koleksi template siap pakai.', 'href' => $this->tautan('template'), 'ikon' => 'beranda-palet'],
                    ['no' => 2, 'judul' => 'Isi data acara', 'isi' => 'Nama, tanggal, lokasi, galeri, dan musik.', 'href' => $tautanBuat, 'ikon' => 'edit'],
                    ['no' => 3, 'judul' => 'Bagikan ke tamu', 'isi' => 'Link pribadi per tamu lewat WhatsApp.', 'href' => $tautanBuat, 'ikon' => 'bagikan'],
                ] as $l)
                    <a href="{{ $l['href'] }}" wire:navigate class="kartu flex items-start gap-3 p-4 transition hover:border-brand-200 hover:bg-brand-50/40">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand"><x-ikon :nama="$l['ikon']" :ukuran="20" /></span>
                        <span class="flex min-w-0 flex-col gap-0.5">
                            <span class="text-xs font-semibold text-brand">Langkah {{ $l['no'] }}</span>
                            <span class="text-sm font-semibold text-ink">{{ $l['judul'] }}</span>
                            <span class="text-[13px] leading-5 text-muted">{{ $l['isi'] }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </section>

        <section aria-labelledby="jelajah-judul">
            <h2 id="jelajah-judul" class="{{ $judulBagian }} mb-3">Jelajahi</h2>
            <div class="grid gap-3 sm:grid-cols-3">
                @foreach ([
                    ['Template desain', 'Lihat semua tema undangan', 'template', 'template'],
                    ['Paket & billing', 'Bandingkan Gratis dan Premium', 'billing', 'billing'],
                    ['Pusat bantuan', 'Panduan membuat undangan', 'bantuan', 'bantuan'],
                ] as [$nama, $ket, $rute, $ikon])
                    <a href="{{ $this->tautan($rute) }}" wire:navigate class="kartu flex min-h-14 items-center gap-3 p-3.5 transition hover:bg-gray-50">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-canvas text-nav"><x-ikon :nama="$ikon" :ukuran="18" /></span>
                        <span class="flex min-w-0 flex-1 flex-col"><span class="text-sm font-semibold text-ink">{{ $nama }}</span><span class="text-xs text-muted">{{ $ket }}</span></span>
                        <x-ikon nama="panah-kanan" :ukuran="16" class="text-subtle" />
                    </a>
                @endforeach
            </div>
        </section>
    @else
        @php
            $hero = $data['hero'];
            $terbit = $hero['status'] === 'terbit';
        @endphp

        @if ($modeContoh)
            <div role="note" class="flex flex-wrap items-center gap-x-3 gap-y-2 rounded-xl border border-brand-200 bg-brand-50 px-4 py-3 text-[13px] leading-5 text-nav">
                <x-ikon nama="beranda-sorot" :ukuran="16" class="text-brand" />
                <span class="min-w-0 flex-1"><b class="text-ink">Mode contoh.</b> Kamu sedang melihat undangan contoh. Data ini hanya ilustrasi dan tidak tersimpan.</span>
                <a href="{{ $tautanBuat }}" wire:navigate class="font-semibold text-brand hover:underline">Buat undangan</a>
                <button type="button" wire:click="sembunyikanContoh" class="btn btn-secondary btn-sm">Sembunyikan contoh</button>
            </div>
        @endif

        {{-- Undangan utama + aksi cepat --}}
        <div class="grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_340px]">
            <section aria-label="Undangan utama" class="kartu flex flex-col gap-4 p-5">
                @unless ($terbit)
                    <div role="note" class="flex items-start gap-2 rounded-lg bg-[#FFFAEB] px-3 py-2 text-[13px] leading-5 text-[#B54708]">
                        <x-ikon nama="peringatan" :ukuran="16" class="mt-0.5" />
                        <span>Undangan ini masih draf. Terbitkan lewat Edit Undangan agar tamu bisa membukanya.</span>
                    </div>
                @endunless
                @if ($hero['perubahan'])
                    <a href="{{ $this->tautan('edit-undangan', ['undangan' => $hero['id']]) }}" wire:navigate class="flex items-start gap-2 rounded-lg bg-brand-50 px-3 py-2 text-[13px] leading-5 text-brand">
                        <x-ikon nama="peringatan" :ukuran="16" class="mt-0.5" />
                        <span class="min-w-0 flex-1">Ada perubahan yang belum diterbitkan. Buka Edit Undangan lalu pilih Terbitkan ulang.</span>
                        <span class="font-semibold underline">Buka editor</span>
                    </a>
                @endif

                <div class="flex flex-wrap items-center gap-x-4 gap-y-3">
                    <div aria-hidden="true" class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-brand text-lg font-semibold tracking-wide text-white">{{ $hero['mono'] }}</div>
                    <div class="min-w-0 flex-1 basis-56">
                        <div class="flex flex-wrap items-center gap-2">
                            <x-pill :warna="$terbit ? 'hijau' : 'kuning'">{{ $terbit ? 'Terbit' : 'Draf' }}</x-pill>
                            <span class="text-xs text-muted">Undangan utama · {{ $hero['jenis'] }}</span>
                        </div>
                        <h2 class="mt-1 text-xl leading-7 font-semibold tracking-tight text-ink [overflow-wrap:anywhere]">{{ $hero['judul'] }}</h2>
                    </div>
                    @unless ($modeContoh)
                        <div class="flex shrink-0 gap-2">
                            <button type="button" wire:click="bagikan({{ $hero['id'] }})" class="btn btn-secondary"><x-ikon nama="bagikan" :ukuran="16" /> Bagikan</button>
                            <a href="{{ $this->tautan('edit-undangan', ['undangan' => $hero['id']]) }}" wire:navigate class="btn btn-primary"><x-ikon nama="edit" :ukuran="16" /> Edit undangan</a>
                        </div>
                    @endunless
                </div>

                <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-[13px] leading-5 text-nav">
                    <span class="inline-flex items-center gap-1.5"><x-ikon nama="beranda-kalender" :ukuran="15" class="text-muted" />{{ $hero['tanggal'] }}</span>
                    <span class="inline-flex min-w-0 items-center gap-1.5"><x-ikon nama="beranda-lokasi" :ukuran="15" class="text-muted" /><span class="truncate">{{ $hero['tempat'] }}</span></span>
                </div>

                @if ($hero['link'])
                    <button type="button" x-data="{ ok: false, salin(t) { try { navigator.clipboard.writeText(t); } catch (e) {} this.ok = true; setTimeout(() => this.ok = false, 1800); } }" aria-label="Salin link undangan {{ $hero['link'] }}"
                            x-on:click="salin(@js('https://'.$hero['link']))"
                            class="flex min-h-11 w-full items-center gap-2.5 rounded-xl border border-line-strong bg-surface px-3.5 text-left text-sm text-nav transition hover:bg-gray-50">
                        <x-ikon nama="beranda-tautan" :ukuran="16" class="text-muted" />
                        <span class="min-w-0 flex-1 truncate font-medium">{{ $hero['link'] }}</span>
                        <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-brand"><x-ikon nama="beranda-salin" :ukuran="14" /><span x-text="ok ? 'Tersalin' : 'Salin'">Salin</span></span>
                    </button>
                @endif

                <div class="grid gap-3 border-t border-line pt-4 sm:grid-cols-2">
                    <div>
                        <div class="flex items-center justify-between text-[13px] font-medium text-nav"><span class="inline-flex items-center gap-1.5"><x-ikon nama="rsvp" :ukuran="15" class="text-muted" />Konfirmasi hadir</span><span class="font-semibold text-ink tabular-nums">{{ $hero['hadirPersen'] }}%</span></div>
                        <div class="mt-1.5 text-sm text-muted"><b class="text-lg font-semibold text-ink tabular-nums">{{ $angka($hero['jumlahHadir']) }}</b> dari {{ $angka($hero['jumlahTamu']) }} tamu</div>
                        <div class="mt-2 h-2 overflow-hidden rounded-full bg-gray-100" role="progressbar" aria-valuenow="{{ $hero['hadirPersen'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="Persentase tamu yang hadir"><div class="h-full rounded-full bg-[#16A34A]" style="width: {{ $hero['hadirPersen'] }}%"></div></div>
                    </div>
                    <div>
                        <div class="flex items-center gap-1.5 text-[13px] font-medium text-nav"><x-ikon nama="beranda-jam" :ukuran="15" class="text-muted" />Menuju hari H</div>
                        @if ($hero['target'])
                            <div x-data="{
                                    t: new Date(@js($hero['target'])).getTime(), d: 0, h: '00', m: '00', s: '00', lewat: '', iv: null,
                                    tick() {
                                        const sisa = this.t - Date.now();
                                        if (sisa <= 0) { this.lewat = sisa > -6 * 3600000 ? 'Acara sedang berlangsung. Selamat berbahagia!' : 'Acara sudah selesai. Terima kasih atas doa dan kehadiran para tamu.'; return; }
                                        this.lewat = '';
                                        const det = Math.floor(sisa / 1000), p = (n) => String(n).padStart(2, '0');
                                        this.d = Math.floor(det / 86400); this.h = p(Math.floor(det % 86400 / 3600)); this.m = p(Math.floor(det % 3600 / 60)); this.s = p(det % 60);
                                    },
                                    init() { this.tick(); this.iv = setInterval(() => this.tick(), 1000); },
                                    destroy() { clearInterval(this.iv); },
                                 }">
                                <div x-show="!lewat" role="timer" aria-label="Hitung mundur menuju acara" class="mt-1.5 flex gap-2">
                                    @foreach ([['d', 'hari'], ['h', 'jam'], ['m', 'menit'], ['s', 'detik']] as [$k, $u])
                                        <div class="min-w-14 flex-1 rounded-lg bg-brand-50 px-2 py-1.5 text-center"><div class="text-lg leading-6 font-semibold text-brand tabular-nums" x-text="{{ $k }}">0</div><div class="text-[11px] text-muted">{{ $u }}</div></div>
                                    @endforeach
                                </div>
                                <div x-show="lewat" x-cloak role="status" class="mt-1.5 text-[13px] leading-5 text-nav" x-text="lewat"></div>
                            </div>
                        @else
                            <p class="mt-1.5 text-[13px] leading-5 text-muted">Tanggal acara belum diisi. Isi lewat Edit Undangan untuk menyalakan hitung mundur.</p>
                        @endif
                    </div>
                </div>
            </section>

            <section aria-labelledby="aksi-judul" class="kartu flex flex-col gap-3 p-5">
                <div class="flex items-center justify-between gap-2">
                    <h2 id="aksi-judul" class="{{ $judulBagian }}">Aksi cepat</h2>
                    <a href="{{ $this->tautan('template') }}" wire:navigate class="inline-flex min-h-8 items-center gap-1 text-[13px] font-semibold text-brand hover:underline"><x-ikon nama="beranda-palet" :ukuran="14" />Template baru</a>
                </div>
                <div class="grid grid-cols-2 gap-2.5 lg:grid-cols-1">
                    @php
                        $aksi = [
                            ['Tambah tamu', 'Tautan pribadi per tamu', 'beranda-tamu', $this->tautan('bagikan')],
                            ['Kirim undangan', 'Link dan pesan WhatsApp', 'beranda-kirim', null],
                            ['Ubah desain', 'Tema, foto, isi', 'edit', $this->tautan('edit-undangan', ['undangan' => $hero['id']])],
                            ['Buku tamu', 'Ucapan dan doa tamu', 'buku', $this->tautan('buku-tamu')],
                        ];
                    @endphp
                    @foreach ($aksi as [$nama, $ket, $ikon, $href])
                        @if ($href === null)
                            <button type="button" @if ($modeContoh) disabled @else wire:click="bagikan({{ $hero['id'] }})" @endif class="flex min-h-14 items-center gap-3 rounded-xl border border-line p-3 text-left transition hover:border-brand-200 hover:bg-brand-50/40 disabled:cursor-default disabled:opacity-60">
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand"><x-ikon :nama="$ikon" :ukuran="18" /></span>
                                <span class="flex min-w-0 flex-col"><span class="text-[13px] font-semibold text-ink">{{ $nama }}</span><span class="text-xs leading-4 text-muted">{{ $ket }}</span></span>
                            </button>
                        @else
                            <a href="{{ $href }}" wire:navigate class="flex min-h-14 items-center gap-3 rounded-xl border border-line p-3 transition hover:border-brand-200 hover:bg-brand-50/40">
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand"><x-ikon :nama="$ikon" :ukuran="18" /></span>
                                <span class="flex min-w-0 flex-col"><span class="text-[13px] font-semibold text-ink">{{ $nama }}</span><span class="text-xs leading-4 text-muted">{{ $ket }}</span></span>
                            </a>
                        @endif
                    @endforeach
                </div>
            </section>
        </div>

        {{-- Langkah menuju undangan siap --}}
        @if ($data['langkah']['tampil'])
            <section aria-labelledby="cl-judul" class="kartu p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 id="cl-judul" class="{{ $judulBagian }}">Langkah menuju undangan siap</h2>
                        <div class="text-[13px] text-muted">{{ $data['langkah']['selesai'] }} dari 4 langkah selesai</div>
                    </div>
                    <div class="h-2 w-40 overflow-hidden rounded-full bg-gray-100" aria-hidden="true"><div class="h-full rounded-full bg-brand" style="width: {{ $data['langkah']['selesai'] * 25 }}%"></div></div>
                </div>
                <div class="mt-4 grid gap-2.5 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($data['langkah']['item'] as $l)
                        <a href="{{ $l['href'] }}" wire:navigate @class(['flex min-h-14 items-center gap-3 rounded-xl border p-3 transition hover:bg-gray-50', 'border-brand-200 bg-brand-50/50' => $l['sekarang'], 'border-line' => ! $l['sekarang']])>
                            @if ($l['ok'])
                                <span role="img" aria-label="Selesai" class="flex size-7 shrink-0 items-center justify-center rounded-full bg-[#16A34A] text-white"><x-ikon nama="centang" :ukuran="15" /></span>
                            @else
                                <span @class(['flex size-7 shrink-0 items-center justify-center rounded-full border-2 text-xs font-semibold', 'border-brand text-brand' => $l['sekarang'], 'border-line-strong text-muted' => ! $l['sekarang']])>{{ $l['no'] }}</span>
                            @endif
                            <span class="flex min-w-0 flex-col"><span class="text-[13px] font-semibold text-ink">{{ $l['label'] }}</span><span class="text-xs leading-4 text-muted">{{ $l['hint'] }}</span></span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Ringkasan --}}
        <section aria-labelledby="ring-judul">
            <div class="mb-3 flex items-center justify-between gap-2">
                <h2 id="ring-judul" class="{{ $judulBagian }}">Ringkasan undangan</h2>
                <a href="{{ $this->tautan('statistik') }}" wire:navigate class="inline-flex min-h-8 items-center gap-1 text-[13px] font-semibold text-brand hover:underline">Statistik lengkap<x-ikon nama="panah-kanan" :ukuran="14" /></a>
            </div>
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                @foreach ($data['kpi'] as $k)
                    <div class="kartu kpi" data-kpi="{{ Str::slug($k['label']) }}">
                        <div class="flex items-center justify-between gap-2"><span class="kpi-l">{{ $k['label'] }}</span><span class="flex size-8 items-center justify-center rounded-lg bg-brand-50 text-brand"><x-ikon :nama="$k['ikon']" :ukuran="16" /></span></div>
                        <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1"><span class="kpi-v">{{ $angka($k['nilai']) }}</span><span class="text-xs font-medium text-muted">{{ $k['delta'] }}</span></div>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Kunjungan + RSVP (undangan utama) --}}
        <div class="grid gap-4 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
            <section aria-labelledby="kunj-judul" class="kartu p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div><h2 id="kunj-judul" class="{{ $judulBagian }}">Kunjungan undangan</h2><div class="text-[13px] text-muted">7 hari terakhir · {{ $hero['judul'] }}</div></div>
                    <div class="text-right"><div class="text-2xl leading-8 font-semibold text-ink tabular-nums">{{ $angka($data['kunjungan']['total']) }}</div><div class="text-xs font-medium {{ $data['kunjungan']['naik'] ? 'text-[#067647]' : 'text-muted' }}">{{ $data['kunjungan']['tren'] }}</div></div>
                </div>
                <div role="img" aria-label="Grafik kunjungan 7 hari terakhir" class="mt-5 flex h-[190px] items-end gap-2 border-b border-line sm:gap-3">
                    @foreach ($data['kunjungan']['bar'] as $b)
                        <div class="flex flex-1 flex-col items-center justify-end gap-1.5">
                            <span class="text-xs font-semibold tabular-nums {{ $b['puncak'] ? 'text-brand' : 'text-subtle' }}">{{ $b['v'] }}</span>
                            <span class="w-full max-w-10 rounded-t-md {{ $b['puncak'] ? 'bg-brand' : 'bg-[#C7D7FB]' }}" style="height: {{ $b['h'] }}px"></span>
                        </div>
                    @endforeach
                </div>
                <div class="mt-1.5 flex gap-2 sm:gap-3" aria-hidden="true">
                    @foreach ($data['kunjungan']['bar'] as $b)
                        <span class="flex-1 text-center text-xs {{ $b['puncak'] ? 'font-semibold text-brand' : 'text-subtle' }}">{{ $b['hari'] }}</span>
                    @endforeach
                </div>
            </section>

            <section id="rsvp" aria-labelledby="rsvp-judul" class="kartu flex flex-col p-5">
                <div><h2 id="rsvp-judul" class="{{ $judulBagian }}">Konfirmasi kehadiran</h2><div class="text-[13px] text-muted">{{ $angka($data['rsvp']['terkirim']) }} undangan terkirim</div></div>
                <div class="my-5 flex flex-wrap items-center gap-5">
                    <div role="img" aria-label="{{ $data['rsvp']['pct'] }} persen hadir" class="relative size-32 shrink-0 rounded-full" style="background: {{ $data['rsvp']['donat'] }}">
                        <div class="absolute inset-[18px] flex flex-col items-center justify-center rounded-full bg-white"><span class="text-xl leading-6 font-semibold text-ink tabular-nums">{{ $data['rsvp']['pct'] }}%</span><span class="text-xs text-muted">hadir</span></div>
                    </div>
                    <div class="flex min-w-40 flex-1 flex-col gap-2.5 text-[13px]">
                        @foreach ([['Hadir', 'hadir', 'pHadir', '#16A34A'], ['Tidak hadir', 'tidak', 'pTidak', '#EF4444'], ['Belum konfirmasi', 'belum', 'pBelum', '#F59E0B']] as [$nama, $k, $p, $warna])
                            <div class="flex items-center gap-2"><span class="size-2.5 shrink-0 rounded-full" style="background: {{ $warna }}"></span><span class="flex-1 text-nav">{{ $nama }}</span><b class="text-ink tabular-nums">{{ $angka($data['rsvp'][$k]) }}</b><span class="w-9 text-right text-xs text-muted tabular-nums">{{ $data['rsvp'][$p] }}%</span></div>
                        @endforeach
                    </div>
                </div>
                <a href="{{ $this->tautan('rsvp') }}" wire:navigate class="mt-auto inline-flex min-h-8 items-center gap-1 text-[13px] font-semibold text-brand hover:underline">Lihat detail RSVP<x-ikon nama="panah-kanan" :ukuran="14" /></a>
            </section>
        </div>

        {{-- Undangan saya --}}
        <section aria-labelledby="inv-judul" class="kartu overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                <h2 id="inv-judul" class="{{ $judulBagian }}">Undangan saya</h2>
                <div class="flex flex-wrap items-center gap-2">
                    <div role="group" aria-label="Filter status" class="flex gap-2">
                        @foreach ($filters as [$k, $label])
                            @php $on = $filter === $k; @endphp
                            <button type="button" wire:click="pilihFilter('{{ $k }}')" aria-pressed="{{ $on ? 'true' : 'false' }}"
                                    class="inline-flex min-h-9 items-center gap-2 rounded-full border-2 py-0 pr-1.5 pl-3.5 text-[13px] leading-5 font-semibold transition {{ $on ? 'border-brand bg-brand-100 text-brand' : 'border-line bg-white text-nav hover:bg-gray-50' }}">
                                {{ $label }}
                                <span class="inline-flex h-6 min-w-6 items-center justify-center rounded-full px-1.5 text-xs tabular-nums {{ $on ? 'bg-brand text-white' : 'bg-gray-100 text-nav' }}">{{ $data['hitung'][$k] }}</span>
                            </button>
                        @endforeach
                    </div>
                    <a href="{{ $tautanBuat }}" wire:navigate class="btn btn-primary btn-sm"><x-ikon nama="tambah" :ukuran="14" /> Buat baru</a>
                </div>
            </div>

            @php
                $terlihat = array_values(array_filter($data['daftar'], fn ($u) => $filter === 'semua' || $u['status'] === $filter));
            @endphp

            <div class="border-t border-line">
                <div class="hidden gap-3 bg-[#f9fafb] px-5 py-2.5 text-xs font-medium text-muted md:grid {{ $kolom }}">
                    <span>Undangan</span><span>Tanggal acara</span><span>Status</span><span class="text-right">Tamu</span><span class="text-right">Dilihat</span><span class="text-right">Hadir</span><span class="text-right">Aksi</span>
                </div>

                {{-- Kerangka saat filter berganti --}}
                <div wire:loading.block wire:target="pilihFilter" class="hidden" role="status" aria-busy="true">
                    <span class="sr-only">Memuat daftar undangan…</span>
                    @for ($i = 0; $i < 2; $i++)
                        <div class="flex items-center gap-3 border-b border-line px-5 py-4"><span class="skel size-10 shrink-0 rounded-xl"></span><div class="flex flex-1 flex-col gap-2"><span class="skel h-3.5 w-1/3"></span><span class="skel h-3 w-1/2"></span></div></div>
                    @endfor
                </div>

                <div wire:loading.remove wire:target="pilihFilter">
                    @forelse ($terlihat as $u)
                        <article wire:key="u-{{ $u['id'] ?? 'contoh-'.$loop->index }}" data-undangan="{{ $u['id'] }}" class="grid items-center gap-x-3 gap-y-3 border-b border-line px-5 py-4 last:border-b-0 {{ $kolom }}">
                            <div class="flex min-w-0 items-center gap-3">
                                <div aria-hidden="true" class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-sm font-semibold text-brand">{{ $u['mono'] }}</div>
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2"><h3 class="text-sm leading-5 font-semibold text-ink [overflow-wrap:anywhere]">{{ $u['judul'] }}</h3>@if ($u['utama'])<span class="tag bg-brand-50 text-brand">Utama</span>@endif</div>
                                    <div class="truncate text-xs text-muted">{{ $u['link'] ?? 'Alamat belum diatur' }}</div>
                                    <div class="text-xs text-subtle">{{ $u['jenis'] }} · {{ $u['terbit'] }}</div>
                                </div>
                            </div>
                            <div class="text-[13px] text-nav"><span class="mr-1.5 text-xs text-muted md:hidden">Tanggal acara</span>{{ $u['tanggal'] }}</div>
                            <div class="flex flex-col items-start gap-1">
                                <x-pill :warna="$u['status'] === 'terbit' ? 'hijau' : 'kuning'">{{ $u['status'] === 'terbit' ? 'Terbit' : 'Draf' }}</x-pill>
                                @if ($u['perubahan'])<span class="text-[11px] leading-4 text-[#B54708]" title="Ada perubahan di Edit Undangan yang belum diterbitkan">Belum diterbitkan ulang</span>@endif
                            </div>
                            <div class="text-[13px] tabular-nums text-nav md:text-right"><span class="mr-1.5 text-xs text-muted md:hidden">Tamu</span>{{ $angka($u['tamu']) }}</div>
                            <div class="text-[13px] tabular-nums text-nav md:text-right"><span class="mr-1.5 text-xs text-muted md:hidden">Dilihat</span>{{ $u['status'] === 'terbit' ? $angka($u['dilihat']) : '–' }}</div>
                            <div class="text-[13px] tabular-nums text-nav md:text-right"><span class="mr-1.5 text-xs text-muted md:hidden">Hadir</span>{{ $u['status'] === 'terbit' ? $angka($u['hadir']) : '–' }}</div>
                            <div class="flex flex-wrap items-center gap-1.5 md:justify-end">
                                @if ($modeContoh)
                                    <span class="text-xs text-muted">Contoh</span>
                                @else
                                    <a href="{{ $this->tautan('edit-undangan', ['undangan' => $u['id']]) }}" wire:navigate aria-label="Edit undangan {{ $u['judul'] }}" class="btn btn-secondary btn-sm"><x-ikon nama="edit" :ukuran="14" />Edit</a>
                                    @if ($u['tautanTamu'])
                                        <a href="{{ $u['tautanTamu'] }}" target="_blank" rel="noopener" aria-label="Lihat {{ $u['judul'] }} seperti tamu" title="Lihat seperti tamu" class="btn-icon"><x-ikon nama="beranda-lihat" :ukuran="16" /></a>
                                    @endif
                                    <button type="button" wire:click="bagikan({{ $u['id'] }})" aria-label="{{ $u['status'] === 'terbit' ? 'Bagikan undangan' : 'Kirim undangan' }} {{ $u['judul'] }}" title="{{ $u['status'] === 'terbit' ? 'Bagikan undangan' : 'Kirim undangan' }}" class="btn-icon"><x-ikon nama="bagikan" :ukuran="16" /></button>
                                    @unless ($u['utama'])
                                        <button type="button" wire:click="jadikanUtama({{ $u['id'] }})" aria-label="Jadikan {{ $u['judul'] }} utama di beranda" title="Jadikan utama di beranda" class="btn-icon"><x-ikon nama="bintang" :ukuran="16" /></button>
                                    @endunless
                                    <button type="button" wire:click="konfirmasiHapus({{ $u['id'] }})" aria-label="Hapus undangan {{ $u['judul'] }}" title="Hapus" class="btn-icon text-[#b42318] hover:bg-[#fef3f2]"><x-ikon nama="beranda-hapus" :ukuran="16" /></button>
                                @endif
                            </div>
                        </article>
                    @empty
                        <x-kosong judul="Tidak ada undangan di filter ini" teks="Pilih filter Semua untuk melihat seluruh undangan." ikon="beranda-undangan">
                            <button type="button" wire:click="pilihFilter('semua')" class="btn btn-secondary">Tampilkan semua</button>
                        </x-kosong>
                    @endforelse
                </div>
            </div>
        </section>

        {{-- Aktivitas + ucapan (undangan utama) --}}
        <div class="grid gap-4 lg:grid-cols-2">
            <section aria-labelledby="akt-judul" class="kartu p-5">
                <div class="mb-2 flex items-center justify-between gap-2">
                    <h2 id="akt-judul" class="{{ $judulBagian }}">Aktivitas tamu</h2>
                    <a href="{{ $this->tautan('rsvp') }}" wire:navigate class="inline-flex min-h-8 items-center gap-1 text-[13px] font-semibold text-brand hover:underline">Lihat semua<x-ikon nama="panah-kanan" :ukuran="14" /></a>
                </div>
                @forelse ($data['aktivitas'] as $a)
                    <a href="{{ $this->tautan('rsvp') }}" wire:navigate class="-mx-2 flex min-h-14 items-center gap-3 rounded-xl px-2 py-2 hover:bg-gray-50">
                        <span aria-hidden="true" class="flex size-10 shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand">{{ $a['ini'] }}</span>
                        <span class="flex min-w-0 flex-1 flex-col"><span class="truncate text-[13px] font-semibold text-ink">{{ $a['nama'] }}</span><span class="truncate text-xs text-muted">{{ $a['info'] }}</span></span>
                        <span class="flex shrink-0 flex-col items-end gap-1">
                            <x-pill :warna="match ($a['jenis']) { 'hadir' => 'hijau', 'tidak' => 'merah', default => 'biru' }">{{ match ($a['jenis']) { 'hadir' => 'Hadir', 'tidak' => 'Tidak hadir', default => 'Dilihat' } }}</x-pill>
                            <span class="text-[11px] text-subtle">{{ $a['waktu'] }}</span>
                        </span>
                    </a>
                @empty
                    <div role="status" class="rounded-xl bg-canvas px-4 py-6 text-center text-[13px] leading-5 text-muted">Belum ada aktivitas. Begitu tamu membuka undangan atau mengonfirmasi kehadiran, kabarnya muncul di sini.</div>
                @endforelse
            </section>

            <section aria-labelledby="uc-judul" class="kartu p-5">
                <div class="mb-2 flex items-center justify-between gap-2">
                    <h2 id="uc-judul" class="{{ $judulBagian }}">Ucapan &amp; doa</h2>
                    <a href="{{ $this->tautan('buku-tamu') }}" wire:navigate class="inline-flex min-h-8 items-center gap-1 text-[13px] font-semibold text-brand hover:underline">Lihat semua<x-ikon nama="panah-kanan" :ukuran="14" /></a>
                </div>
                @forelse ($data['ucapan'] as $w)
                    <a href="{{ $this->tautan('buku-tamu') }}" wire:navigate class="-mx-2 flex items-start gap-3 rounded-xl px-2 py-2.5 hover:bg-gray-50">
                        <span aria-hidden="true" class="flex size-10 shrink-0 items-center justify-center rounded-full bg-[#FFF4CC] text-[#B25E00]"><x-ikon nama="beranda-pesan" :ukuran="18" /></span>
                        <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                            <span class="flex items-center justify-between gap-2"><span class="truncate text-[13px] font-semibold text-ink">{{ $w['nama'] }}</span><span class="shrink-0 text-[11px] text-subtle">{{ $w['waktu'] }}</span></span>
                            <span class="line-clamp-2 text-[13px] leading-5 text-nav [overflow-wrap:anywhere]">{{ $w['pesan'] }}</span>
                        </span>
                    </a>
                @empty
                    <div role="status" class="rounded-xl bg-canvas px-4 py-6 text-center text-[13px] leading-5 text-muted">Belum ada ucapan dari tamu.</div>
                @endforelse
            </section>
        </div>
    @endif

    {{-- Konfirmasi hapus --}}
    <x-dialog wire:model="tampilHapus" judul="Hapus undangan ini?" sub="Tidak bisa dikembalikan." lebar="max-w-[480px]">
        @if ($hapus)
            <p class="m-0 text-sm leading-6 text-nav">Undangan <b class="text-ink">{{ $hapus['judul'] }}</b>, termasuk {{ $angka($hapus['tamu']) }} tamu, RSVP, dan ucapannya, akan dihapus permanen.@if ($hapus['status'] === 'terbit') Tautan undangan juga tidak bisa dibuka lagi oleh tamu.@endif</p>
        @endif
        <x-slot:footer>
            <button type="button" wire:click="$set('tampilHapus', false)" class="btn btn-secondary">Batal</button>
            <button type="button" wire:click="hapus" wire:loading.attr="disabled" wire:target="hapus" class="btn btn-danger-solid">Hapus undangan</button>
        </x-slot:footer>
    </x-dialog>

    {{-- Bagikan --}}
    <x-dialog wire:model="tampilBagikan" judul="Bagikan undangan" :sub="$bagikan ? $bagikan['judul'] : null" lebar="max-w-[480px]">
        @if ($bagikan)
            @if ($bagikan['status'] !== 'terbit')
                <div role="status" class="rounded-lg bg-[#FFFAEB] px-3 py-2 text-[13px] leading-5 text-[#B54708]">Undangan ini belum diterbitkan, jadi tautannya belum bisa dibuka tamu. Terbitkan dulu lewat Edit Undangan.</div>
            @endif
            @if ($bagikan['link'])
                <button type="button" x-data="{ ok: false, salin(t) { try { navigator.clipboard.writeText(t); } catch (e) {} this.ok = true; setTimeout(() => this.ok = false, 1800); } }" x-on:click="salin(@js('https://'.$bagikan['link']))"
                        class="flex min-h-11 w-full items-center gap-2.5 rounded-xl border border-line-strong bg-surface px-3.5 text-left text-sm text-nav hover:bg-gray-50">
                    <x-ikon nama="beranda-salin" :ukuran="16" class="text-muted" />
                    <span class="min-w-0 flex-1 truncate font-medium">{{ $bagikan['link'] }}</span>
                    <span class="text-xs font-semibold text-brand" x-text="ok ? 'Tersalin' : 'Salin link'">Salin link</span>
                </button>
                <a href="https://wa.me/?text={{ rawurlencode('Kami mengundang Anda ke '.$bagikan['judul'].'. Buka undangannya di sini: https://'.$bagikan['link']) }}" target="_blank" rel="noopener" class="flex min-h-11 items-center gap-2.5 rounded-xl border border-line-strong px-3.5 text-sm font-medium text-ink hover:bg-gray-50">
                    <x-ikon nama="beranda-wa" :ukuran="18" class="text-[#16A34A]" />Bagikan lewat WhatsApp
                </a>
            @endif
            @if ($bagikan['tautanTamu'])
                <a href="{{ $bagikan['tautanTamu'] }}" target="_blank" rel="noopener" class="flex min-h-11 items-center gap-2.5 rounded-xl border border-line-strong px-3.5 text-sm font-medium text-ink hover:bg-gray-50">
                    <x-ikon nama="beranda-lihat" :ukuran="18" class="text-muted" />Lihat seperti tamu
                </a>
            @endif
            <a href="{{ $this->tautan('bagikan') }}" wire:navigate class="flex min-h-11 items-center gap-2.5 rounded-xl border border-line-strong px-3.5 text-sm font-medium text-ink hover:bg-gray-50">
                <x-ikon nama="beranda-tamu" :ukuran="18" class="text-muted" />
                <span class="flex min-w-0 flex-1 flex-col"><span>Kirim ke daftar tamu</span><span class="text-xs font-normal text-muted">Tautan pribadi per tamu, kirim lewat WhatsApp</span></span>
                <x-ikon nama="panah-kanan" :ukuran="16" class="text-subtle" />
            </a>
        @endif
    </x-dialog>
</div>
