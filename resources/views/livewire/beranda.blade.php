{{--
    Beranda = dashboard penuh (papan Main.dc.html). Nilai px/warna mengikuti hasil render papan di 1440 px.
    Akun tanpa undangan melihat dashboard yang sama dengan keadaan kosong per widget.
--}}
@php
    $angka = fn (int|float $n): string => number_format($n, 0, ',', '.');
    $tautanSemua = fn (string $nama, ?int $id = null) => $id ? $this->tautan($nama, ['undangan' => $id]) : $this->tautan($nama);
    $judulKartu = 'm-0 text-[15px] leading-[22px] font-semibold text-ink';
    $tautanKartu = 'inline-flex min-h-8 items-center gap-1 text-[13px] leading-[18px] font-semibold text-brand hover:underline';
    $serif = 'font-[ui-serif,Georgia,serif]';
    $kolom = 'grid-cols-[minmax(240px,2.4fr)_minmax(110px,1fr)_140px_64px_72px_64px_240px]';
    $filters = [['semua', 'Semua'], ['utama', 'Utama'], ['aktif', 'Aktif'], ['draf', 'Draf']];
@endphp
<div wire:init="muat" class="mx-auto flex max-w-[1240px] flex-col gap-6">

    @unless ($siap)
        {{-- Kerangka pemuat --}}
        <div role="status" aria-busy="true" aria-live="polite" class="flex flex-col gap-6">
            <span class="sr-only">Memuat beranda…</span>
            <div class="flex flex-wrap items-stretch gap-5">
                <div class="kartu flex min-w-0 flex-[2_1_600px] flex-col gap-4 p-7">
                    <div class="flex items-center gap-5"><span class="skel size-[72px] shrink-0 rounded-[14px]"></span><div class="flex flex-1 flex-col gap-2.5"><span class="skel h-3 w-28"></span><span class="skel h-6 w-3/5"></span></div></div>
                    <span class="skel h-4 w-2/5"></span>
                    <span class="skel mt-3 h-16 w-full"></span>
                </div>
                <div class="kartu flex min-w-0 flex-[1_1_320px] flex-col gap-3.5 p-5"><span class="skel h-5 w-28"></span><div class="grid flex-1 grid-cols-2 gap-2.5"><span class="skel h-24"></span><span class="skel h-24"></span><span class="skel h-24"></span><span class="skel h-24"></span></div></div>
            </div>
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                @for ($i = 0; $i < 4; $i++)
                    <div class="kartu flex flex-col gap-3 px-5 py-[18px]"><span class="skel h-3 w-20"></span><span class="skel h-7 w-16"></span></div>
                @endfor
            </div>
            <div class="flex flex-wrap items-stretch gap-5">
                <div class="kartu flex min-w-0 flex-[2_1_520px] flex-col gap-3 px-6 py-5"><span class="skel h-4 w-40"></span><span class="skel h-[200px] w-full"></span></div>
                <div class="kartu flex min-w-0 flex-[1_1_320px] flex-col gap-3 px-6 py-5"><span class="skel h-4 w-40"></span><span class="skel size-32 rounded-full"></span></div>
            </div>
            <div class="kartu flex flex-col gap-3 p-5"><span class="skel h-4 w-32"></span><span class="skel h-12 w-full"></span><span class="skel h-12 w-full"></span></div>
        </div>
    @else
        @php
            $hero = $data['hero'];
            $kosong = $data['kosong'];
            $aktif = ! $kosong && $hero['status'] === 'aktif';
            $draf = ! $kosong && ! $aktif;
            $idUtama = $modeContoh ? null : ($hero['id'] ?? null);
            $langkah = $data['langkah'];
        @endphp

        @if ($modeContoh)
            <div role="note" class="box-border flex flex-wrap items-center gap-x-3.5 gap-y-2.5 rounded-xl border border-[#DCE6FC] bg-[#EEF3FE] px-4 py-3 text-[13px] leading-[18px] text-[#1A3FA8]">
                <span class="flex shrink-0"><x-ikon nama="beranda-lihat" :ukuran="16" /></span>
                <span class="min-w-0 flex-[1_1_280px]"><b class="font-semibold">Mode contoh.</b> Kamu sedang melihat undangan contoh — data ini hanya ilustrasi dan tidak tersimpan.</span>
                <a href="{{ $tautanBuat }}" wire:navigate class="btn btn-primary btn-sm">Buat undangan</a>
                <button type="button" wire:click="sembunyikanContoh" class="btn btn-secondary btn-sm">Sembunyikan contoh</button>
            </div>
        @endif

        {{-- Baris 1: undangan utama + aksi cepat --}}
        <div class="flex flex-wrap items-stretch gap-5">
            <section aria-label="Undangan utama" class="kartu relative flex min-w-0 flex-[2_1_600px] flex-col overflow-hidden">
                <div aria-hidden="true" class="absolute top-0 right-0 left-0 z-[2] h-[3px] bg-[linear-gradient(90deg,#1D4ED8_0%,#60A5FA_100%)]"></div>

                @if ($draf)
                    <div role="note" class="box-border flex items-center gap-2.5 border-b border-[#FDE7B0] bg-[#FFFAEB] px-6 py-2.5 text-[13px] leading-[18px] font-medium text-[#93370D]">
                        <x-ikon nama="peringatan" :ukuran="16" />
                        <span>Undangan ini masih draf. Terbitkan lewat Edit Undangan agar tamu bisa membukanya.</span>
                    </div>
                @endif
                @if (! $kosong && $hero['perubahan'])
                    <a href="{{ $tautanSemua('edit-undangan', $idUtama) }}" wire:navigate class="box-border flex items-center gap-2.5 border-b border-[#FDE7B0] bg-[#FFFAEB] px-6 py-2.5 text-[13px] leading-[18px] font-medium text-[#93370D]">
                        <x-ikon nama="beranda-segarkan" :ukuran="16" />
                        <span class="flex-1">Ada perubahan yang belum diterbitkan. Buka Edit Undangan lalu pilih Terbitkan ulang.</span>
                        <span class="font-semibold">Buka editor</span>
                    </a>
                @endif

                <div class="relative isolate">
                    <div aria-hidden="true" class="pointer-events-none absolute inset-0 z-0 overflow-hidden bg-[radial-gradient(circle_at_100%_0%,rgba(29,78,216,0.08)_0,rgba(29,78,216,0)_46%),linear-gradient(120deg,#F1F5FD_0%,#F8FAFE_55%,#FFFFFF_100%)]">
                        <svg width="420" height="220" viewBox="0 0 420 220" fill="none" class="absolute -top-[30px] -right-10"><circle cx="340" cy="80" r="150" stroke="#1D4ED8" stroke-opacity="0.07" stroke-width="1.5"/><circle cx="340" cy="80" r="100" stroke="#1D4ED8" stroke-opacity="0.07" stroke-width="1.5"/><circle cx="340" cy="80" r="52" fill="#1D4ED8" fill-opacity="0.04"/></svg>
                    </div>

                    <div class="relative z-[1] flex flex-wrap items-start gap-5 px-7 pt-7 pb-2">
                        <div aria-hidden="true" class="{{ $serif }} flex size-[72px] shrink-0 items-center justify-center rounded-[14px] bg-[linear-gradient(135deg,#1D4ED8_0%,#4F7BEF_100%)] text-[26px] leading-[30px] font-semibold text-white shadow-[inset_0_0_0_1px_rgba(255,255,255,0.22),0_0_0_4px_rgba(29,78,216,0.08),0_8px_18px_-8px_rgba(29,78,216,0.4)]">
                            @if ($kosong)
                                <x-ikon nama="beranda-undangan" :ukuran="28" />
                            @else
                                {{ $hero['mono'] }}
                            @endif
                        </div>
                        <div class="flex min-w-0 flex-[1_1_260px] flex-col gap-1.5">
                            <div class="flex flex-wrap items-center gap-2">
                                @if ($kosong)
                                    <span class="pill pill-abu">Belum ada</span>
                                @else
                                    <span class="pill {{ $aktif ? 'pill-hijau' : 'pill-kuning' }}">{{ $aktif ? 'Aktif' : 'Draf' }}</span>
                                @endif
                                <span class="text-xs leading-[18px] font-medium text-muted">Undangan utama@unless ($kosong) · {{ $hero['jenis'] }}@endunless</span>
                            </div>
                            @if ($kosong)
                                <a href="{{ $tautanBuat }}" wire:navigate class="{{ $serif }} text-[28px] leading-[34px] font-semibold tracking-[-0.01em] text-ink [overflow-wrap:anywhere]">Belum ada undangan</a>
                            @else
                                <a href="{{ $tautanSemua('edit-undangan', $idUtama) }}" @unless ($idUtama) tabindex="-1" @else wire:navigate @endunless aria-label="Edit undangan {{ $hero['judul'] }}" class="{{ $serif }} text-[28px] leading-[34px] font-semibold tracking-[-0.01em] text-ink [overflow-wrap:anywhere]">{{ $hero['judul'] }}</a>
                            @endif
                        </div>
                        <div class="flex shrink-0 flex-wrap gap-2">
                            @if ($kosong)
                                <a href="{{ $tautanBuat }}" wire:navigate class="btn btn-primary min-h-10 px-4"><x-ikon nama="tambah" :ukuran="16" :tebal="2.5" /> Buat undangan</a>
                            @elseif ($idUtama)
                                <button type="button" wire:click="bagikan({{ $idUtama }})" class="btn btn-secondary min-h-10"><x-ikon nama="bagikan" :ukuran="16" /> Bagikan</button>
                                <a href="{{ $tautanSemua('edit-undangan', $idUtama) }}" wire:navigate class="btn btn-primary min-h-10 px-4"><x-ikon nama="edit" :ukuran="16" /> Edit undangan</a>
                            @endif
                        </div>
                    </div>

                    <div class="relative z-[1] flex flex-wrap items-center gap-x-5 gap-y-2.5 pr-7 pb-[30px] pl-[120px] max-sm:pl-7">
                        @if ($kosong)
                            <span class="text-[13px] leading-[18px] font-medium text-[#4B5563]">Pilih tema, isi data acara, lalu bagikan link pribadi ke setiap tamu.</span>
                            <button type="button" wire:click="tampilkanContoh" class="btn btn-secondary btn-sm"><x-ikon nama="beranda-lihat" :ukuran="14" /> Lihat contoh dashboard</button>
                        @else
                            <div class="flex flex-[0_1_auto] flex-wrap gap-x-4 gap-y-1.5 text-[13px] leading-[18px] font-medium text-[#4B5563]">
                                <span class="inline-flex items-center gap-1.5"><x-ikon nama="beranda-kalender" :ukuran="15" class="text-subtle" />{{ $hero['tanggal'] }}</span>
                                <span class="inline-flex min-w-0 items-center gap-1.5"><x-ikon nama="beranda-lokasi" :ukuran="15" class="text-subtle" /><span class="[overflow-wrap:anywhere]">{{ $hero['tempat'] }}</span></span>
                            </div>
                            @if ($hero['link'])
                                <button type="button" x-data="{ ok: false, salin(t) { try { navigator.clipboard.writeText(t); } catch (e) {} this.ok = true; setTimeout(() => this.ok = false, 1800); } }" aria-label="Salin link undangan {{ $hero['link'] }}"
                                        x-on:click="salin(@js('https://'.$hero['link']))"
                                        class="box-border inline-flex min-h-9 max-w-full items-center gap-2 rounded-[10px] border border-line bg-[#F7F8FA] py-0 pr-1 pl-3 text-left text-[13px] leading-[18px] font-medium text-ink">
                                    <x-ikon nama="beranda-tautan" :ukuran="15" class="text-subtle" />
                                    <span class="min-w-0 truncate">{{ $hero['link'] }}</span>
                                    <span class="inline-flex min-h-7 shrink-0 items-center gap-1 rounded-[7px] border border-line bg-white px-2.5 text-xs font-semibold text-brand"><x-ikon nama="beranda-salin" :ukuran="13" /><span x-text="ok ? 'Tersalin' : 'Salin'">Salin</span></span>
                                </button>
                            @endif
                        @endif
                    </div>
                </div>

                <div class="relative grid flex-1 grid-cols-[repeat(auto-fit,minmax(240px,1fr))] border-t border-[#E6EAF2] bg-white">
                    <div class="flex flex-col justify-center gap-2 border-r border-[#DCE5FB] px-6 py-[18px] max-sm:border-r-0">
                        <div class="flex items-center justify-between gap-2">
                            <span class="inline-flex items-center gap-2 text-xs leading-4 font-semibold text-[#374151]"><span aria-hidden="true" class="flex size-6 items-center justify-center rounded-lg bg-[#E7F6EC] text-[#16A34A]"><x-ikon nama="beranda-tamu" :ukuran="14" /></span>Konfirmasi hadir</span>
                            <span class="rounded-full bg-brand-50 px-2 py-0.5 text-xs leading-4 font-bold text-brand">{{ $kosong ? 0 : $hero['hadirPersen'] }}%</span>
                        </div>
                        <div class="text-[13px] leading-[18px] text-[#4B5563]"><b class="text-xl leading-[26px] font-semibold text-ink tabular-nums">{{ $angka($kosong ? 0 : $hero['jumlahHadir']) }}</b> dari {{ $angka($kosong ? 0 : $hero['jumlahTamu']) }} tamu</div>
                        <div class="h-1.5 overflow-hidden rounded-[3px] bg-[#EEF0F3]" role="progressbar" aria-valuenow="{{ $kosong ? 0 : $hero['hadirPersen'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="Persentase tamu yang hadir"><div class="h-full rounded-[3px] bg-brand" style="width: {{ $kosong ? 0 : $hero['hadirPersen'] }}%"></div></div>
                    </div>
                    <div class="flex flex-col justify-center gap-2 px-6 py-[18px]">
                        <div class="flex items-center gap-2 text-xs leading-4 font-semibold text-[#374151]"><span aria-hidden="true" class="flex size-6 items-center justify-center rounded-lg bg-[#EEF0FF] text-[#4F46E5]"><x-ikon nama="beranda-jam" :ukuran="14" /></span>Menuju hari H</div>
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
                                <div x-show="!lewat" role="timer" aria-label="Hitung mundur menuju acara" class="grid grid-cols-4 gap-1.5">
                                    @foreach ([['d', 'hari'], ['h', 'jam'], ['m', 'menit'], ['s', 'detik']] as [$k, $u])
                                        <div class="rounded-lg border border-[#DCE5FB] bg-brand-50 px-1 py-1.5 text-center"><div class="text-lg leading-6 font-bold text-brand tabular-nums" x-text="{{ $k }}">0</div><div class="text-[11px] leading-[14px] text-muted">{{ $u }}</div></div>
                                    @endforeach
                                </div>
                                <div x-show="lewat" x-cloak role="status" class="text-[15px] leading-[22px] font-semibold text-ink" x-text="lewat"></div>
                            </div>
                        @else
                            <p class="m-0 text-[13px] leading-5 text-muted">{{ $kosong ? 'Hitung mundur menyala setelah undangan pertamamu punya tanggal acara.' : 'Tanggal acara belum diisi. Isi lewat Edit Undangan untuk menyalakan hitung mundur.' }}</p>
                        @endif
                    </div>
                </div>
            </section>

            <section aria-labelledby="aksi-judul" class="kartu flex min-w-0 flex-[1_1_320px] flex-col gap-3.5 px-5 pt-[18px] pb-5">
                <div class="flex items-center justify-between gap-2">
                    <h2 id="aksi-judul" class="{{ $judulKartu }}">Aksi cepat</h2>
                    <a href="{{ $this->tautan('template') }}" wire:navigate class="{{ $tautanKartu }}"><x-ikon nama="beranda-kilau" :ukuran="14" />Template baru</a>
                </div>
                <div class="grid flex-1 grid-cols-2 gap-2.5">
                    <x-beranda.ubin :href="$tautanSemua('bagikan', $idUtama)" ikon="beranda-tamu-plus" nama="Tambah tamu" ket="Tautan pribadi per tamu" />
                    @if ($idUtama)
                        <x-beranda.ubin ikon="beranda-kirim" nama="Kirim undangan" ket="Link & kode QR" wire:click="bagikan({{ $idUtama }})" />
                    @else
                        <x-beranda.ubin ikon="beranda-kirim" nama="Kirim undangan" ket="Link & kode QR" disabled title="Buat undangan dulu untuk membagikan link dan kode QR" />
                    @endif
                    <x-beranda.ubin :href="$tautanSemua('edit-undangan', $idUtama)" ikon="edit" nama="Ubah desain" ket="Tema, foto, isi" />
                    <x-beranda.ubin :href="$tautanSemua('buku-tamu', $idUtama)" ikon="buku" nama="Buku tamu" ket="Ucapan & doa tamu" />
                </div>
            </section>
        </div>

        {{-- Langkah menuju undangan siap (onboarding kecil di dalam dashboard) --}}
        @if ($langkah['tampil'])
            <section aria-labelledby="langkah-judul" class="kartu flex flex-col gap-3.5 px-6 py-5">
                <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                    <div>
                        <h2 id="langkah-judul" class="{{ $judulKartu }}">Langkah menuju undangan siap</h2>
                        <div class="text-[13px] leading-[18px] text-muted">{{ $langkah['selesai'] }} dari 4 langkah selesai</div>
                    </div>
                    <div aria-hidden="true" class="h-1.5 w-[220px] max-w-full overflow-hidden rounded-[3px] bg-[#EEF0F3]"><div class="h-full rounded-[3px] bg-[#16A34A]" style="width: {{ $langkah['pct'] }}%"></div></div>
                </div>
                <div class="grid grid-cols-[repeat(auto-fit,minmax(220px,1fr))] gap-3">
                    @foreach ($langkah['item'] as $l)
                        <a href="{{ $l['href'] }}" wire:navigate class="box-border flex items-start gap-3 rounded-xl border p-3.5 transition hover:bg-white {{ $l['sekarang'] ? 'border-brand-200 bg-brand-50/60' : 'border-[#EEF0F3] bg-[#FCFCFD]' }}" @if ($l['sekarang']) aria-current="step" @endif>
                            @if ($l['ok'])
                                <span aria-label="Selesai" class="flex size-6 shrink-0 items-center justify-center rounded-full bg-[#16A34A] text-white"><x-ikon nama="centang" :ukuran="14" :tebal="3" /></span>
                            @else
                                <span class="flex size-6 shrink-0 items-center justify-center rounded-full border {{ $l['sekarang'] ? 'border-brand text-brand' : 'border-line-strong text-muted' }} bg-white text-xs leading-4 font-semibold">{{ $l['no'] }}</span>
                            @endif
                            <span class="min-w-0"><span class="block text-[13px] leading-[18px] font-semibold text-ink">{{ $l['label'] }}</span><span class="block text-xs leading-4 text-muted">{{ $l['hint'] }}</span></span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Baris 2: ringkasan --}}
        <section aria-labelledby="ring-judul" class="flex flex-col gap-3">
            <div class="flex items-center justify-between gap-3">
                <h2 id="ring-judul" class="m-0 text-base leading-[22px] font-semibold text-ink">Ringkasan undangan</h2>
                <a href="{{ $this->tautan('statistik') }}" wire:navigate class="{{ $tautanKartu }}">Statistik lengkap<x-ikon nama="panah-kanan" :ukuran="16" /></a>
            </div>
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                @foreach ($data['stats'] as $st)
                    <a href="{{ $this->tautan('statistik') }}" wire:navigate class="kartu flex flex-col gap-3 px-5 py-[18px]">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-[13px] leading-[18px] font-medium text-muted">{{ $st['label'] }}</span>
                            <span aria-hidden="true" class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-[#F3F5F8] text-[#4B5563]"><x-ikon :nama="$st['ikon']" :ukuran="18" /></span>
                        </div>
                        <div class="flex flex-wrap items-end justify-between gap-2">
                            <span class="text-[28px] leading-[34px] font-semibold tracking-[-0.02em] text-ink tabular-nums">{{ $angka($st['nilai']) }}</span>
                            <span class="inline-flex items-center gap-[3px] rounded-full px-2 py-0.5 text-xs leading-4 font-semibold whitespace-nowrap {{ $st['delta'] > 0 ? 'bg-[#ECFDF3] text-[#067647]' : 'bg-[#F2F4F7] text-muted' }}">{{ $st['delta'] > 0 ? '+'.$st['delta'] : '0' }} hari ini</span>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>

        {{-- Baris 3: kunjungan + konfirmasi kehadiran --}}
        <div class="flex flex-wrap items-stretch gap-5">
            <section aria-labelledby="kunj-judul" class="kartu flex min-w-0 flex-[2_1_520px] flex-col gap-[18px] px-6 py-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 id="kunj-judul" class="{{ $judulKartu }}">Kunjungan undangan</h2>
                        <div class="text-[13px] leading-[18px] text-muted">7 hari terakhir</div>
                    </div>
                    <div class="text-right">
                        <div class="text-2xl leading-[30px] font-semibold tracking-[-0.02em] text-ink tabular-nums">{{ $angka($data['kunjungan']['total']) }}</div>
                        <div class="text-xs leading-4 font-medium {{ $data['kunjungan']['total'] > 0 ? ($data['kunjungan']['naik'] ? 'text-[#067647]' : 'text-[#B42318]') : 'text-muted' }}">{{ $data['kunjungan']['tren'] }}</div>
                    </div>
                </div>
                <div class="relative h-[200px]">
                    <div aria-hidden="true" class="pointer-events-none absolute inset-x-0 top-5 bottom-6 flex flex-col justify-between">
                        <div class="border-t border-dashed border-line"></div><div class="border-t border-dashed border-line"></div><div class="border-t border-dashed border-line"></div><div class="border-t border-line"></div>
                    </div>
                    <div role="img" aria-label="Grafik kunjungan 7 hari terakhir" class="absolute inset-0 grid grid-cols-7 items-end gap-2.5">
                        @foreach ($data['kunjungan']['bar'] as $b)
                            <div class="flex h-full flex-col items-center justify-end gap-1.5">
                                <span class="text-[11px] leading-[14px] font-semibold tabular-nums {{ $b['puncak'] ? 'text-brand' : 'text-subtle' }}">{{ $angka($b['v']) }}</span>
                                <span class="block w-full max-w-[34px] rounded-t-md rounded-b-[2px] {{ $b['puncak'] ? 'bg-brand' : 'bg-[#C7D7FB]' }}" style="height: {{ $b['h'] }}px"></span>
                                <span class="mt-1 text-xs leading-4 font-medium text-muted">{{ $b['hari'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            <section id="rsvp" aria-labelledby="rsvp-judul" class="kartu flex min-w-0 flex-[1_1_320px] flex-col gap-[18px] px-6 py-5">
                <div>
                    <h2 id="rsvp-judul" class="{{ $judulKartu }}">Konfirmasi kehadiran</h2>
                    <div class="text-[13px] leading-[18px] text-muted">{{ $angka($data['rsvp']['terkirim']) }} undangan terkirim</div>
                </div>
                <div class="flex flex-wrap items-center gap-5">
                    <div role="img" aria-label="{{ $data['rsvp']['pct'] }} persen hadir" class="flex size-32 shrink-0 items-center justify-center rounded-full" style="background: {{ $data['rsvp']['donat'] }}">
                        <div class="flex size-24 flex-col items-center justify-center rounded-full bg-white">
                            <span class="text-2xl leading-7 font-semibold tracking-[-0.02em] text-ink">{{ $data['rsvp']['pct'] }}%</span>
                            <span class="text-[11px] leading-[14px] text-muted">hadir</span>
                        </div>
                    </div>
                    <div class="flex min-w-0 flex-[1_1_140px] flex-col gap-2.5">
                        @foreach ([['Hadir', 'hadir', 'pHadir', '#16A34A'], ['Tidak hadir', 'tidak', 'pTidak', '#EF4444'], ['Belum konfirmasi', 'belum', 'pBelum', '#F59E0B']] as [$nama, $k, $p, $warna])
                            <div class="flex items-center gap-2.5 text-[13px] leading-[18px]">
                                <span aria-hidden="true" class="size-2 shrink-0 rounded" style="background: {{ $warna }}"></span>
                                <span class="flex-1 text-[#4B5563]">{{ $nama }}</span>
                                <b class="font-semibold text-ink tabular-nums">{{ $angka($data['rsvp'][$k]) }}</b>
                                <span class="w-10 text-right text-subtle tabular-nums">{{ $data['rsvp'][$p] }}%</span>
                            </div>
                        @endforeach
                    </div>
                </div>
                <a href="{{ $tautanSemua('rsvp', $idUtama) }}" wire:navigate class="btn btn-secondary mt-auto min-h-10 w-full">Lihat detail RSVP<x-ikon nama="panah-kanan" :ukuran="16" /></a>
            </section>
        </div>

        {{-- Baris 4: undangan saya --}}
        <section aria-labelledby="inv-judul" class="kartu flex flex-col overflow-hidden">
            <div class="flex flex-wrap items-center gap-3 px-5 py-4">
                <h2 id="inv-judul" class="{{ $judulKartu }} flex-auto">Undangan saya</h2>
                <div role="group" aria-label="Filter status" class="seg">
                    @foreach ($filters as [$k, $label])
                        <button type="button" wire:click="pilihFilter('{{ $k }}')" aria-pressed="{{ $filter === $k ? 'true' : 'false' }}">{{ $label }} <span class="font-medium text-subtle">{{ $data['hitung'][$k] }}</span></button>
                    @endforeach
                </div>
                <a href="{{ $tautanBuat }}" wire:navigate class="btn btn-primary min-h-[38px] px-4"><x-ikon nama="tambah" :ukuran="16" :tebal="2.5" />Buat baru</a>
            </div>
            <div class="overflow-x-auto">
                <div class="min-w-[940px]">
                    <div aria-hidden="true" class="grid {{ $kolom }} items-center gap-3 border-y border-[#EEF0F3] bg-[#F9FAFB] px-5 py-2.5 text-xs leading-4 font-medium text-muted">
                        <span>Undangan</span><span>Tanggal acara</span><span>Status</span><span class="text-right">Tamu</span><span class="text-right">Dilihat</span><span class="text-right">Hadir</span><span class="text-right">Aksi</span>
                    </div>

                    @forelse ($data['daftar'] as $u)
                        @php $nyata = $u['id'] !== null; @endphp
                        <article data-undangan class="grid {{ $kolom }} items-center gap-3 border-b border-[#EEF0F3] px-5 py-3.5 hover:bg-[#FAFBFC]">
                            <div class="flex min-w-0 items-center gap-3">
                                <div aria-hidden="true" class="{{ $serif }} flex size-10 shrink-0 items-center justify-center rounded-[10px] bg-brand-50 text-sm font-semibold text-brand">{{ $u['mono'] }}</div>
                                <div class="min-w-0">
                                    <div class="flex min-w-0 items-center gap-1.5">
                                        <h3 class="m-0 truncate text-sm leading-5 font-semibold text-ink">{{ $u['judul'] }}</h3>
                                        @if ($u['utama'])<span class="shrink-0 rounded-md bg-brand-50 px-1.5 py-px text-[11px] leading-4 font-semibold text-brand">Utama</span>@endif
                                    </div>
                                    @if ($u['link'])<div class="truncate text-xs leading-4 text-muted">{{ $u['link'] }}</div>@endif
                                    <div class="text-xs leading-4 text-subtle">{{ $u['jenis'] }} · {{ $u['terbit'] }}</div>
                                </div>
                            </div>
                            <div class="text-[13px] leading-[18px] text-[#374151]">{{ $u['tanggal'] }}</div>
                            <div class="flex flex-col items-start gap-1">
                                <span class="pill {{ $u['status'] === 'aktif' ? 'pill-hijau' : 'pill-kuning' }}">{{ $u['status'] === 'aktif' ? 'Aktif' : 'Draf' }}</span>
                                @if ($u['perubahan'])<div title="Ada perubahan di Edit Undangan yang belum diterbitkan" class="text-[11px] leading-[14px] font-medium text-[#B54708]">Belum diterbitkan ulang</div>@endif
                            </div>
                            <div class="text-right text-[13px] leading-[18px] font-semibold text-ink tabular-nums">{{ $angka($u['tamu']) }}</div>
                            <div class="text-right text-[13px] leading-[18px] font-semibold text-ink tabular-nums">{{ $u['status'] === 'aktif' ? $angka($u['dilihat']) : '–' }}</div>
                            <div class="text-right text-[13px] leading-[18px] font-semibold text-ink tabular-nums">{{ $u['status'] === 'aktif' ? $angka($u['hadir']) : '–' }}</div>
                            <div class="flex items-center justify-end gap-1.5">
                                @if ($nyata)
                                    <a href="{{ $this->tautan('edit-undangan', ['undangan' => $u['id']]) }}" wire:navigate aria-label="Edit undangan {{ $u['judul'] }}" class="btn btn-secondary btn-sm min-h-[34px] px-3"><x-ikon nama="edit" :ukuran="14" />Edit</a>
                                    @if ($u['tautanTamu'])
                                        <a href="{{ $u['tautanTamu'] }}" target="_blank" rel="noopener" aria-label="Lihat {{ $u['judul'] }} seperti tamu" title="Lihat seperti tamu" class="btn-icon"><x-ikon nama="beranda-lihat" :ukuran="16" /></a>
                                    @endif
                                    <button type="button" wire:click="bagikan({{ $u['id'] }})" aria-label="{{ $u['status'] === 'aktif' ? 'Bagikan undangan' : 'Kirim undangan' }} {{ $u['judul'] }}" title="{{ $u['status'] === 'aktif' ? 'Bagikan undangan' : 'Kirim undangan' }}" class="btn-icon"><x-ikon nama="bagikan" :ukuran="16" /></button>
                                    @if ($u['utama'])
                                        <span class="w-[34px] shrink-0" aria-hidden="true"></span>
                                    @else
                                        <button type="button" wire:click="jadikanUtama({{ $u['id'] }})" aria-label="Jadikan {{ $u['judul'] }} utama di dashboard" title="Jadikan utama di dashboard" class="btn-icon"><x-ikon nama="bintang" :ukuran="16" /></button>
                                    @endif
                                    <button type="button" wire:click="konfirmasiHapus({{ $u['id'] }})" aria-label="Hapus undangan {{ $u['judul'] }}" title="Hapus undangan" class="btn-icon text-[#B42318] hover:bg-[#FEF3F2]"><x-ikon nama="beranda-hapus" :ukuran="16" /></button>
                                @endif
                            </div>
                        </article>
                    @empty
                        <div role="status" class="flex flex-col items-center gap-1.5 px-5 py-10 text-center">
                            @if ($data['kosong'])
                                <div class="text-sm leading-5 font-semibold text-ink">Belum ada undangan</div>
                                <div class="text-[13px] leading-[18px] text-muted">Undangan yang kamu buat akan muncul di sini.</div>
                                <a href="{{ $tautanBuat }}" wire:navigate class="btn btn-primary btn-sm mt-2.5">Buat undangan pertama</a>
                            @elseif ($cari !== '')
                                <div class="text-sm leading-5 font-semibold text-ink">Tidak ada undangan yang cocok dengan “{{ $cari }}”</div>
                                <div class="text-[13px] leading-[18px] text-muted">Coba kata lain, atau hapus pencarian untuk melihat seluruh undangan.</div>
                                <button type="button" wire:click="hapusCari" class="btn btn-secondary btn-sm mt-2.5">Hapus pencarian</button>
                            @else
                                <div class="text-sm leading-5 font-semibold text-ink">Tidak ada undangan di filter ini</div>
                                <div class="text-[13px] leading-[18px] text-muted">Pilih filter Semua untuk melihat seluruh undangan.</div>
                                <button type="button" wire:click="pilihFilter('semua')" class="btn btn-secondary btn-sm mt-2.5">Tampilkan semua</button>
                            @endif
                        </div>
                    @endforelse
                </div>
            </div>
        </section>

        {{-- Baris 5: aktivitas tamu + ucapan & doa --}}
        <div class="flex flex-wrap items-stretch gap-5">
            <section aria-labelledby="akt-judul" class="kartu flex min-w-0 flex-[1_1_420px] flex-col px-5 pt-[18px] pb-2">
                <div class="flex items-center justify-between gap-3 pb-1.5">
                    <h2 id="akt-judul" class="{{ $judulKartu }}">Aktivitas tamu</h2>
                    <a href="{{ $tautanSemua('rsvp', $idUtama) }}" wire:navigate class="{{ $tautanKartu }}">Lihat semua<x-ikon nama="panah-kanan" :ukuran="16" /></a>
                </div>
                @forelse ($data['aktivitas'] as $a)
                    <a href="{{ $tautanSemua('rsvp', $idUtama) }}" wire:navigate class="flex items-center gap-3 border-t border-[#F0F1F4] py-3">
                        <div aria-hidden="true" class="flex size-9 shrink-0 items-center justify-center rounded-full bg-[#F3F5F8] text-xs font-semibold text-[#4B5563]">{{ $a['ini'] }}</div>
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm leading-5 font-semibold text-ink">{{ $a['nama'] }}</div>
                            <div class="text-xs leading-4 text-muted">{{ $a['info'] }}</div>
                        </div>
                        <div class="flex shrink-0 flex-col items-end gap-[3px]">
                            @php
                                [$labelAktif, $warnaAktif] = match ($a['jenis']) {
                                    'hadir' => ['Hadir', 'bg-[#ECFDF3] text-[#067647]'],
                                    'dilihat' => ['Dilihat', 'bg-brand-50 text-brand'],
                                    'tidak' => ['Tidak hadir', 'bg-[#FEF3F2] text-[#B42318]'],
                                    default => ['Belum dibuka', 'bg-[#F2F4F7] text-[#4B5563]'],
                                };
                            @endphp
                            <span class="rounded-full px-2 py-px text-[11px] leading-4 font-semibold whitespace-nowrap {{ $warnaAktif }}">{{ $labelAktif }}</span>
                            <span class="text-[11px] leading-[14px] text-subtle">{{ $a['waktu'] }}</span>
                        </div>
                    </a>
                @empty
                    <div role="status" class="px-1 py-6 text-[13px] leading-5 text-muted">Belum ada aktivitas. Begitu tamu membuka undangan atau mengonfirmasi kehadiran, kabarnya muncul di sini.</div>
                @endforelse
            </section>

            <section aria-labelledby="uc-judul" class="kartu flex min-w-0 flex-[1_1_420px] flex-col px-5 pt-[18px] pb-2">
                <div class="flex items-center justify-between gap-3 pb-1.5">
                    <h2 id="uc-judul" class="{{ $judulKartu }}">Ucapan &amp; doa</h2>
                    <a href="{{ $tautanSemua('buku-tamu', $idUtama) }}" wire:navigate class="{{ $tautanKartu }}">Lihat semua<x-ikon nama="panah-kanan" :ukuran="16" /></a>
                </div>
                @forelse ($data['ucapan'] as $w)
                    <a href="{{ $tautanSemua('buku-tamu', $idUtama) }}" wire:navigate class="flex gap-3 border-t border-[#F0F1F4] py-3.5">
                        <span aria-hidden="true" class="flex size-9 shrink-0 items-center justify-center rounded-full bg-[#FDF2F8] text-[#C11574]"><x-ikon nama="beranda-hati" :ukuran="16" /></span>
                        <div class="flex min-w-0 flex-1 flex-col gap-1">
                            <div class="flex items-baseline justify-between gap-2">
                                <span class="min-w-0 truncate text-sm leading-5 font-semibold text-ink">{{ $w['nama'] }}</span>
                                <span class="shrink-0 text-[11px] leading-[14px] text-subtle">{{ $w['waktu'] }}</span>
                            </div>
                            <div class="text-[13px] leading-5 text-[#374151] [overflow-wrap:anywhere]">{{ $w['pesan'] }}</div>
                        </div>
                    </a>
                @empty
                    <div role="status" class="px-1 py-6 text-[13px] leading-5 text-muted">Belum ada ucapan dari tamu.</div>
                @endforelse
            </section>
        </div>
    @endunless

    {{-- Konfirmasi hapus --}}
    <x-dialog wire:model="tampilHapus" judul="Hapus undangan ini?" sub="Tidak bisa dikembalikan." lebar="max-w-[480px]">
        @if ($hapus)
            <p class="m-0 text-sm leading-6 text-nav">Undangan <b class="text-ink">{{ $hapus['judul'] }}</b>, termasuk {{ $angka($hapus['tamu']) }} tamu, RSVP, dan ucapannya, akan dihapus permanen.@if ($hapus['status'] === 'aktif') Tautan undangan juga tidak bisa dibuka lagi oleh tamu.@endif</p>
        @endif
        <x-slot:footer>
            <button type="button" wire:click="$set('tampilHapus', false)" class="btn btn-secondary">Batal</button>
            <button type="button" wire:click="hapus" wire:loading.attr="disabled" wire:target="hapus" class="btn btn-danger-solid">Hapus undangan</button>
        </x-slot:footer>
    </x-dialog>

    {{-- Bagikan Undangan (papan: modal 460 px, kartu 2 px) --}}
    @if ($tampilBagikan && $bagikan)
        <div x-data x-on:keydown.escape.window="$wire.set('tampilBagikan', false)" class="fixed inset-0 z-[80] box-border flex items-center justify-center overflow-auto p-6">
            <button type="button" wire:click="$set('tampilBagikan', false)" aria-label="Tutup" tabindex="-1" class="absolute inset-0 size-full cursor-default bg-[rgba(38,42,49,0.55)]"></button>
            <section role="dialog" aria-modal="true" aria-labelledby="bagikan-judul" x-trap.noscroll="true" class="relative box-border flex w-full max-w-[460px] flex-col gap-3 rounded-3xl bg-white p-6">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <h2 id="bagikan-judul" class="m-0 text-lg leading-6 font-bold text-ink">Bagikan Undangan</h2>
                        <div class="text-[13px] leading-[18px] font-medium text-[#666C76] [overflow-wrap:anywhere]">{{ $bagikan['judul'] }}@if ($bagikan['link']) · {{ $bagikan['link'] }}@endif</div>
                    </div>
                    <button type="button" wire:click="$set('tampilBagikan', false)" aria-label="Tutup" class="-mt-2 -mr-2 flex size-11 shrink-0 items-center justify-center rounded-[22px] text-[#666C76]"><x-ikon nama="tutup" :ukuran="20" :tebal="2.25" /></button>
                </div>

                @unless ($bagikan['terbit'])
                    <div role="status" class="rounded-lg bg-[#FFFAEB] px-3 py-2 text-[13px] leading-5 text-[#B54708]">Undangan ini belum diterbitkan, jadi tautannya belum bisa dibuka tamu. Terbitkan dulu lewat Edit Undangan.</div>
                @endunless

                @if ($bagikan['qr'])
                    <div class="flex items-center gap-4 rounded-2xl border-2 border-[#E2E5E9] px-3.5 py-3">
                        <div role="img" aria-label="Kode QR undangan" class="size-24 shrink-0 overflow-hidden rounded-[10px] bg-white [&>svg]:block [&>svg]:size-full">{!! $bagikan['qr'] !!}</div>
                        <div class="flex min-w-0 flex-1 flex-col gap-1.5">
                            <div class="text-sm leading-[19px] font-bold text-ink">Kode QR undangan</div>
                            <div class="text-xs leading-4 font-medium text-[#666C76]">Cetak di kartu fisik atau tampilkan di layar acara.</div>
                            <a href="{{ $bagikan['unduhQr'] }}" download="qr-{{ $bagikan['slug'] }}.svg" class="inline-flex min-h-9 items-center self-start rounded-xl bg-[#E3ECFD] px-3.5 text-xs leading-4 font-bold tracking-[0.5px] text-brand uppercase">Unduh QR</a>
                        </div>
                    </div>
                @endif

                @if ($bagikan['link'])
                    <button type="button" x-data="{ ok: false, salin(t) { try { navigator.clipboard.writeText(t); } catch (e) {} this.ok = true; setTimeout(() => this.ok = false, 1800); } }" x-on:click="salin(@js($bagikan['url'] ?? 'https://'.$bagikan['link']))"
                            class="box-border flex min-h-14 items-center gap-3 rounded-2xl border-2 border-[#E2E5E9] px-4 text-left">
                        <span aria-hidden="true" class="flex size-9 shrink-0 items-center justify-center rounded-full bg-[#E3ECFD] text-brand"><x-ikon nama="beranda-salin" :ukuran="18" /></span>
                        <span class="flex-1 text-sm leading-[19px] font-bold text-ink"><span x-text="ok ? 'Tersalin' : 'Salin'">Salin</span> link undangan</span>
                    </button>
                @endif
                @if ($bagikan['tautanTamu'])
                    <a href="{{ $bagikan['tautanTamu'] }}" target="_blank" rel="noopener" class="box-border flex min-h-14 items-center gap-3 rounded-2xl border-2 border-[#E2E5E9] px-4">
                        <span aria-hidden="true" class="flex size-9 shrink-0 items-center justify-center rounded-full bg-[#E3ECFD] text-brand"><x-ikon nama="beranda-lihat" :ukuran="18" /></span>
                        <span class="flex-1 text-sm leading-[19px] font-bold text-ink">Lihat seperti tamu</span>
                    </a>
                @endif
                @if ($bagikan['wa'])
                    <a href="{{ $bagikan['wa'] }}" target="_blank" rel="noopener" class="box-border flex min-h-14 items-center gap-3 rounded-2xl border-2 border-[#E2E5E9] px-4">
                        <span aria-hidden="true" class="flex size-9 shrink-0 items-center justify-center rounded-full bg-[#D7FFB8] text-[#3C7A00]"><x-ikon nama="beranda-wa" :ukuran="18" /></span>
                        <span class="flex-1 text-sm leading-[19px] font-bold text-ink">Bagikan lewat WhatsApp</span>
                    </a>
                @endif
                <a href="{{ $this->tautan('bagikan', ['undangan' => $bagikan['id']]) }}" wire:navigate class="box-border flex min-h-14 items-center gap-3 rounded-2xl border-2 border-[#E2E5E9] px-4">
                    <span aria-hidden="true" class="flex size-9 shrink-0 items-center justify-center rounded-full bg-[#E3ECFD] text-brand"><x-ikon nama="beranda-tamu" :ukuran="18" /></span>
                    <span class="flex-1"><span class="block text-sm leading-[19px] font-bold text-ink">Kirim ke daftar tamu</span><span class="block text-xs leading-4 font-medium text-[#666C76]">Tautan pribadi per tamu, kirim lewat WhatsApp</span></span>
                    <x-ikon nama="panah-kanan" :ukuran="16" class="text-subtle" />
                </a>
            </section>
        </div>
    @endif
</div>
