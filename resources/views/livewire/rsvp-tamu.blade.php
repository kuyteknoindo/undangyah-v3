@php
    $angka = fn (int|float $n): string => number_format($n, 0, ',', '.');
    $inisial = fn (string $n): string => mb_strtoupper(mb_substr(trim($n), 0, 1)) ?: '?';
    $labelStatus = ['hadir' => ['Hadir', 'hijau'], 'tidak' => ['Tidak hadir', 'merah'], 'belum' => ['Belum RSVP', 'kuning']];
    $waTeks = function ($tamu) use ($undangan) {
        return 'Halo '.$tamu->nama.', kami mengundang Anda ke '.$undangan->judul.'. Mohon konfirmasi kehadiran lewat tautan pribadi berikut: '.route('undangan.publik', $undangan->slug).'?to='.$tamu->kode;
    };
    $waLink = fn ($tamu) => 'https://wa.me/'.($tamu->hp ?: '').'?text='.rawurlencode($waTeks($tamu));
    $contohCsv = "nama,hp,grup,jumlah\nBudi Santoso,081234567890,Keluarga,2\nSiti Aminah,,Teman,1\n";
    $adaUndangan = $undangan !== null;
@endphp
<div wire:init="muat" class="mx-auto flex max-w-6xl flex-col gap-5">
    @unless ($siap)
        <div role="status" aria-busy="true" class="flex flex-col gap-5">
            <span class="sr-only">Memuat halaman…</span>
            <div class="kartu flex flex-col gap-3 p-5"><span class="skel h-4 w-24"></span><span class="skel h-11 w-full max-w-md"></span></div>
            <div class="kartu flex flex-col gap-4 p-6"><span class="skel h-4 w-40"></span><span class="skel h-10 w-32"></span><span class="skel h-3 w-full"></span></div>
            <div class="kartu flex flex-col gap-3 p-5">
                <span class="skel h-4 w-48"></span>
                @for ($i = 0; $i < 4; $i++)
                    <div class="flex items-center gap-3"><span class="skel size-9 shrink-0 rounded-full"></span><span class="skel h-3.5 flex-1"></span><span class="skel hidden h-3.5 w-24 sm:block"></span></div>
                @endfor
            </div>
        </div>
    @elseif (! $adaUndangan)
        <section aria-labelledby="rv-kosong" class="kartu p-6 sm:p-8">
            <x-kosong judul="Belum ada undangan" teks="Daftar tamu dan konfirmasi kehadiran muncul di sini setelah kamu membuat dan membagikan undangan." ikon="rsvp">
                <a href="{{ Route::has('buat-undangan') ? route('buat-undangan') : '#' }}" wire:navigate class="btn btn-primary"><x-ikon nama="tambah" :ukuran="16" /> Buat undangan</a>
            </x-kosong>
        </section>
    @else
        {{-- Pilih undangan --}}
        <section aria-label="Pilih undangan" class="kartu flex flex-wrap items-end justify-between gap-4 p-4 sm:p-5">
            <div class="flex w-full max-w-md flex-col gap-1.5">
                <label for="rv-inv" class="label">Undangan</label>
                <select id="rv-inv" class="inp" wire:change="pilih($event.target.value)">
                    @foreach ($daftar as $u)
                        <option value="{{ $u->id }}" @selected($u->id === $undangan->id)>{{ $u->judul }} ({{ $u->sudahTerbit() ? 'Terbit' : 'Draf' }})</option>
                    @endforeach
                </select>
            </div>
            <div class="flex flex-wrap items-center gap-2.5">
                <x-pill :warna="$undangan->sudahTerbit() ? 'hijau' : 'kuning'">{{ $undangan->sudahTerbit() ? 'Terbit' : 'Draf' }}</x-pill>
                @unless ($undangan->sudahTerbit())
                    <span class="sub">Tautan pribadi baru bisa dibuka tamu setelah undangan terbit.</span>
                @endunless
            </div>
        </section>

        {{-- Ringkasan --}}
        <section aria-label="Ringkasan RSVP" class="grid gap-5 lg:grid-cols-[minmax(0,1.35fr)_minmax(0,1fr)]">
            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-brand to-brand-dark p-6 text-white">
                <div aria-hidden="true" class="absolute -top-16 -right-10 size-48 rounded-full bg-white/10"></div>
                <div aria-hidden="true" class="absolute -right-4 -bottom-20 size-44 rounded-full bg-white/5"></div>
                <div class="relative">
                    <div class="text-[11px] leading-[14px] font-bold tracking-[0.1em] uppercase opacity-80">Konfirmasi kehadiran</div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-5xl leading-none font-semibold tracking-tight tabular-nums">{{ $angka($r['orangHadir']) }}</span>
                        <span class="text-sm opacity-90">orang akan hadir</span>
                    </div>
                    <p class="mt-2 text-[13px] leading-5 opacity-90">
                        @if ($adaData)
                            {{ $angka($r['totalRsvp']) }} dari {{ $angka(max($r['tamu'], $r['totalRsvp'])) }} tamu sudah menjawab. {{ $angka($r['terkirim']) }} dari {{ $angka($r['tamu']) }} undangan sudah dikirim.
                        @else
                            Belum ada tamu atau jawaban. Tambahkan tamu lalu kirim undangan.
                        @endif
                    </p>
                    @php
                        $tot = $r['hadir'] + $r['tidak'] + $r['belum'];
                    @endphp
                    <div role="img" aria-label="{{ $r['hadir'] }} hadir, {{ $r['tidak'] }} tidak hadir, {{ $r['belum'] }} belum RSVP" class="mt-4 flex h-2.5 gap-1">
                        @if ($tot === 0)
                            <div class="flex-1 rounded-[5px] bg-white/30"></div>
                        @else
                            @if ($r['hadir'] > 0)<div class="rounded-[5px] bg-white" style="flex: {{ $r['hadir'] }} 1 0"></div>@endif
                            @if ($r['tidak'] > 0)<div class="rounded-[5px] bg-[#ff9c91]" style="flex: {{ $r['tidak'] }} 1 0"></div>@endif
                            @if ($r['belum'] > 0)<div class="rounded-[5px] bg-[#ffc800]" style="flex: {{ $r['belum'] }} 1 0"></div>@endif
                        @endif
                    </div>
                    <div class="mt-4 grid grid-cols-3 gap-3">
                        <div><div class="text-2xl leading-8 font-semibold tabular-nums">{{ $angka($r['hadir']) }}</div><div class="mt-0.5 flex items-center gap-1.5 text-xs opacity-90"><span class="size-2 rounded-full bg-white"></span>Hadir</div></div>
                        <div><div class="text-2xl leading-8 font-semibold tabular-nums">{{ $angka($r['tidak']) }}</div><div class="mt-0.5 flex items-center gap-1.5 text-xs opacity-90"><span class="size-2 rounded-full bg-[#ff9c91]"></span>Tidak hadir</div></div>
                        <div><div class="text-2xl leading-8 font-semibold tabular-nums">{{ $angka($r['belum']) }}</div><div class="mt-0.5 flex items-center gap-1.5 text-xs opacity-90"><span class="size-2 rounded-full bg-[#ffc800]"></span>Belum RSVP</div></div>
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-4">
                <div class="kartu flex flex-wrap items-center justify-between gap-3 p-4">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-[#fff4cc] text-[#8a4a00]"><x-ikon nama="rsvp-lonceng" :ukuran="18" /></span>
                        <div class="min-w-0">
                            <div class="text-sm leading-5 font-semibold text-ink">
                                {{ $r['belum'] > 0 ? $r['belum'].' tamu belum konfirmasi' : ($r['tamu'] > 0 ? 'Semua tamu sudah konfirmasi' : 'Belum ada tamu') }}
                            </div>
                            <div class="sub">{{ $r['belum'] > 0 ? 'Kirim pengingat lewat WhatsApp satu per satu.' : ($r['tamu'] > 0 ? 'Tidak ada pengingat yang perlu dikirim.' : 'Tambahkan tamu agar bisa mengirim pengingat.') }}</div>
                        </div>
                    </div>
                    <button type="button" wire:click="$set('tampilPengingat', true)" @disabled($r['belum'] === 0) class="btn btn-secondary btn-sm shrink-0"><x-ikon nama="rsvp-wa" :ukuran="14" /> Kirim pengingat</button>
                </div>
                <div class="kartu grid grid-cols-2 gap-px overflow-hidden bg-line sm:grid-cols-4 lg:grid-cols-2 xl:grid-cols-4">
                    <div class="kpi bg-white"><div class="kpi-l">Tamu diundang</div><div class="kpi-v">{{ $angka($r['tamu']) }}</div></div>
                    <div class="kpi bg-white"><div class="kpi-l">Total jiwa</div><div class="kpi-v">{{ $angka($r['jiwaDiundang']) }}</div></div>
                    <div class="kpi bg-white"><div class="kpi-l">Sudah dikirim</div><div class="kpi-v">{{ $angka($r['terkirim']) }}</div></div>
                    <div class="kpi bg-white"><div class="kpi-l">Tingkat respons</div><div class="kpi-v">{{ $r['tingkat'] }}%</div></div>
                </div>
                @if ($batasTamu !== null)
                    <div class="hint px-1">Kuota tamu paketmu: {{ $angka($r['tamu']) }} dari {{ $angka($batasTamu) }} terpakai.</div>
                @endif
            </div>
        </section>

        {{-- Daftar --}}
        <section aria-label="Daftar RSVP dan tamu" class="kartu overflow-hidden">
            <div class="flex flex-col gap-3 p-4 sm:p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="seg" role="group" aria-label="Tampilan daftar">
                        <button type="button" wire:click="gantiTab('jawaban')" aria-pressed="{{ $tab === 'jawaban' ? 'true' : 'false' }}">Jawaban RSVP <span class="ml-1 text-xs text-muted">{{ $angka($r['totalRsvp']) }}</span></button>
                        <button type="button" wire:click="gantiTab('tamu')" aria-pressed="{{ $tab === 'tamu' ? 'true' : 'false' }}">Daftar tamu <span class="ml-1 text-xs text-muted">{{ $angka($r['tamu']) }}</span></button>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" wire:click="bukaImpor" class="btn btn-secondary btn-sm"><x-ikon nama="rsvp-unggah" :ukuran="14" /> Impor CSV</button>
                        <button type="button" wire:click="ekspor" wire:loading.attr="disabled" wire:target="ekspor" class="btn btn-secondary btn-sm"><x-ikon nama="rsvp-unduh" :ukuran="14" /> Ekspor CSV ({{ $angka($total) }})</button>
                        <button type="button" wire:click="bukaTambah" @disabled($sisaTamu === 0) class="btn btn-primary btn-sm"><x-ikon nama="tambah" :ukuran="14" /> Tambah tamu</button>
                    </div>
                </div>

                @if ($adaData)
                    <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_180px_180px]">
                        <div class="flex flex-col gap-1.5">
                            <label for="rv-cari" class="sr-only">{{ $tab === 'tamu' ? 'Cari nama tamu atau grup' : 'Cari nama tamu atau pesan' }}</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-muted"><x-ikon nama="rsvp-cari" :ukuran="16" /></span>
                                <input id="rv-cari" type="search" wire:model.live.debounce.400ms="cari" maxlength="100" autocomplete="off" class="inp pl-9" placeholder="{{ $tab === 'tamu' ? 'Cari nama atau grup...' : 'Cari nama atau pesan...' }}">
                            </div>
                        </div>
                        <div>
                            <label for="rv-grup" class="sr-only">Filter grup</label>
                            <select id="rv-grup" wire:model.live="grup" class="inp">
                                <option value="semua">Semua grup</option>
                                @foreach ($daftarGrup as $g)
                                    <option value="{{ $g }}">{{ $g }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="rv-urut" class="sr-only">Urutkan</label>
                            <select id="rv-urut" wire:model.live="urut" class="inp">
                                <option value="baru">Terbaru</option>
                                <option value="lama">Terlama</option>
                                <option value="nama">Nama A–Z</option>
                                @if ($tab === 'tamu')
                                    <option value="status">Belum RSVP dulu</option>
                                @else
                                    <option value="jumlah">Jumlah terbanyak</option>
                                @endif
                            </select>
                        </div>
                    </div>
                    <div role="group" aria-label="Filter status" class="flex flex-wrap gap-2">
                        @foreach ($chips as $c)
                            <button type="button" wire:click="pilihStatus('{{ $c['nilai'] }}')" aria-pressed="{{ $status === $c['nilai'] ? 'true' : 'false' }}"
                                    class="inline-flex min-h-8 items-center gap-1.5 rounded-full border px-3 text-[13px] font-medium {{ $status === $c['nilai'] ? 'border-brand bg-brand-50 text-brand' : 'border-line-strong bg-white text-nav hover:bg-gray-50' }}">
                                {{ $c['label'] }} <span class="text-xs opacity-70">{{ $angka($c['jumlah']) }}</span>
                            </button>
                        @endforeach
                    </div>
                @endif

                @if ($tab === 'tamu' && count($terpilih) > 0)
                    <div role="status" class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-brand-50 px-4 py-2.5 text-[13px] font-medium text-brand">
                        <span>{{ count($terpilih) }} tamu dipilih</span>
                        <span class="flex gap-2">
                            <button type="button" wire:click="$set('terpilih', [])" class="btn btn-ghost btn-sm">Batalkan pilihan</button>
                            <button type="button" wire:click="mintaHapusMassal" class="btn btn-danger btn-sm"><x-ikon nama="rsvp-hapus" :ukuran="14" /> Hapus yang dipilih</button>
                        </span>
                    </div>
                @endif
            </div>

            @if (! $adaData)
                <x-kosong judul="Belum ada tamu atau jawaban RSVP" teks="Tambahkan tamu atau impor dari CSV, kirim undangan, lalu jawaban kehadiran mereka akan muncul di sini." ikon="rsvp-grup">
                    <button type="button" wire:click="bukaTambah" class="btn btn-primary btn-sm"><x-ikon nama="tambah" :ukuran="14" /> Tambah tamu</button>
                    <button type="button" wire:click="bukaImpor" class="btn btn-secondary btn-sm"><x-ikon nama="rsvp-unggah" :ukuran="14" /> Impor CSV</button>
                </x-kosong>
            @elseif ($total === 0)
                <x-kosong judul="Tidak ada yang cocok" teks="Tidak ada baris yang cocok dengan pencarian atau filter ini." ikon="rsvp-cari">
                    <button type="button" wire:click="aturUlangFilter" class="btn btn-secondary btn-sm">Reset filter</button>
                </x-kosong>
            @elseif ($tab === 'jawaban')
                <div class="overflow-x-auto">
                    <table class="tabel min-w-[820px]">
                        <caption class="sr-only">Daftar jawaban RSVP</caption>
                        <thead><tr><th>Tamu</th><th>Status</th><th>Jumlah</th><th>Grup</th><th>Pesan</th><th>Waktu</th><th class="text-right">Aksi</th></tr></thead>
                        <tbody>
                            @foreach ($baris as $j)
                                @php
                                    $st = $labelStatus[$j->status] ?? $labelStatus['belum'];
                                @endphp
                                <tr wire:key="rsvp-{{ $j->id }}">
                                    <td><div class="flex items-center gap-2.5"><span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-brand-100 text-xs font-bold text-brand-dark">{{ $inisial($j->nama) }}</span><span class="font-medium text-ink">{{ $j->nama }}</span></div></td>
                                    <td><x-pill :warna="$st[1]">{{ $st[0] }}</x-pill></td>
                                    <td class="tabular-nums">{{ $j->status === 'hadir' ? max(1, $j->jumlah).' orang' : '–' }}</td>
                                    <td>{{ $j->tamu?->grup ?: '–' }}</td>
                                    <td class="max-w-[260px]"><span class="line-clamp-2 break-words">{{ $j->pesan ?: '–' }}</span></td>
                                    <td title="{{ $j->created_at?->format('d/m/Y H:i') }}" class="whitespace-nowrap">{{ $j->created_at?->locale('id')->diffForHumans() ?? '–' }}</td>
                                    <td>
                                        <div class="flex items-center justify-end gap-2">
                                            <button type="button" wire:click="balikStatus({{ $j->id }})" class="btn btn-secondary btn-sm">{{ $j->status === 'hadir' ? 'Jadikan tidak hadir' : 'Jadikan hadir' }}</button>
                                            <button type="button" wire:click="mintaHapusRsvp({{ $j->id }})" class="btn-icon" aria-label="Hapus RSVP {{ $j->nama }}"><x-ikon nama="rsvp-hapus" :ukuran="15" /></button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="tabel min-w-[960px]">
                        <caption class="sr-only">Daftar tamu dan status kirim</caption>
                        <thead>
                            <tr>
                                <th class="w-10"><input type="checkbox" wire:click="pilihHalaman" @checked(count($terpilih) > 0 && $baris->pluck('id')->every(fn ($i) => in_array((string) $i, array_map('strval', $terpilih), true))) aria-label="Pilih semua tamu di halaman ini" class="size-4 rounded border-line-strong"></th>
                                <th>Tamu</th><th>Grup</th><th>Nomor WhatsApp</th><th>Jml</th><th>Status kirim</th><th>RSVP</th><th class="text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($baris as $t)
                                @php
                                    $rv = $rsvpTamu[$t->id] ?? null;
                                    $st = $labelStatus[$rv?->status ?? 'belum'] ?? $labelStatus['belum'];
                                    $tautan = route('undangan.publik', $undangan->slug).'?to='.$t->kode;
                                @endphp
                                <tr wire:key="tamu-{{ $t->id }}">
                                    <td><input type="checkbox" wire:model.live="terpilih" value="{{ $t->id }}" aria-label="Pilih {{ $t->nama }}" class="size-4 rounded border-line-strong"></td>
                                    <td><div class="flex items-center gap-2.5"><span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-brand-100 text-xs font-bold text-brand-dark">{{ $inisial($t->nama) }}</span><span class="font-medium text-ink">{{ $t->nama }}</span></div></td>
                                    <td>{{ $t->grup ?: '–' }}</td>
                                    <td class="whitespace-nowrap">{{ \App\Support\NomorWa::tampil($t->hp) ?? 'Belum ada nomor' }}</td>
                                    <td class="tabular-nums">{{ $t->jumlah }}</td>
                                    <td>
                                        @if ($t->dikirim_at)
                                            <x-pill warna="biru">Terkirim {{ $t->dikirim_at->locale('id')->diffForHumans() }}</x-pill>
                                        @else
                                            <x-pill warna="abu">Belum dikirim</x-pill>
                                        @endif
                                    </td>
                                    <td><x-pill :warna="$st[1]">{{ $st[0] }}</x-pill></td>
                                    <td>
                                        <div class="flex items-center justify-end gap-1.5"
                                             x-data="{ salin: false, async copy() { const t = @js($tautan); try { await navigator.clipboard.writeText(t); } catch (e) { const a = document.createElement('textarea'); a.value = t; document.body.appendChild(a); a.select(); try { document.execCommand('copy'); } catch (e2) {} a.remove(); } this.salin = true; setTimeout(() => this.salin = false, 1800); } }">
                                            <button type="button" x-on:click="copy()" class="btn-icon" x-bind:aria-label="salin ? 'Tautan tersalin' : 'Salin tautan pribadi {{ addslashes($t->nama) }}'" title="Salin tautan pribadi"><span x-show="!salin"><x-ikon nama="rsvp-tautan" :ukuran="15" /></span><span x-show="salin" x-cloak><x-ikon nama="centang" :ukuran="15" /></span></button>
                                            <button type="button" wire:click="bukaUbahRsvp({{ $t->id }})" class="btn-icon" aria-label="Ubah RSVP {{ $t->nama }}" title="Tandai hadir / ubah RSVP"><x-ikon nama="rsvp-lingkar-centang" :ukuran="15" /></button>
                                            <button type="button" wire:click="bukaUbah({{ $t->id }})" class="btn-icon" aria-label="Ubah {{ $t->nama }}" title="Ubah tamu"><x-ikon nama="edit" :ukuran="15" /></button>
                                            <button type="button" wire:click="mintaHapus({{ $t->id }})" class="btn-icon" aria-label="Hapus {{ $t->nama }}" title="Hapus tamu"><x-ikon nama="rsvp-hapus" :ukuran="15" /></button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($adaData && $total > 0)
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-[#eef0f3] px-4 py-3 sm:px-5">
                    <div class="sub">Menampilkan {{ $angka($dari) }}–{{ $angka($sampai) }} dari {{ $angka($total) }} {{ $tab === 'tamu' ? 'tamu' : 'jawaban' }}</div>
                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="sebelumnya" @disabled($halaman <= 1) class="btn btn-secondary btn-sm" aria-label="Halaman sebelumnya"><x-ikon nama="rsvp-kiri" :ukuran="14" /> Sebelumnya</button>
                        <span class="text-[13px] font-medium tabular-nums text-ink">{{ $halaman }} / {{ $halamanMaks }}</span>
                        <button type="button" wire:click="berikutnya" @disabled($halaman >= $halamanMaks) class="btn btn-secondary btn-sm" aria-label="Halaman berikutnya">Berikutnya <x-ikon nama="rsvp-kanan" :ukuran="14" /></button>
                    </div>
                </div>
            @endif
        </section>
    @endif

    @if ($adaUndangan)
        {{-- Tambah / ubah tamu --}}
        <x-dialog wire:model="tampilForm" :judul="$tamuId ? 'Ubah tamu' : 'Tambah tamu'" sub="Kode tautan pribadi dibuat otomatis dan tidak bisa diubah.">
            <form id="rv-form-tamu" wire:submit="simpanTamu" class="flex flex-col gap-3.5" novalidate>
                <div>
                    <label for="rv-nama" class="lbl">Nama tamu</label>
                    <input id="rv-nama" type="text" wire:model="nama" maxlength="100" class="inp" autocomplete="off" @error('nama') aria-invalid="true" @enderror>
                    @error('nama')<div role="alert" class="galat mt-1">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label for="rv-hp" class="lbl">Nomor WhatsApp <span class="font-normal text-muted">(opsional)</span></label>
                    <input id="rv-hp" type="tel" inputmode="tel" wire:model="hp" maxlength="20" class="inp" placeholder="0812 3456 7890" autocomplete="off" @error('hp') aria-invalid="true" @enderror>
                    @error('hp')<div role="alert" class="galat mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="grid gap-3.5 sm:grid-cols-[minmax(0,1fr)_140px]">
                    <div>
                        <label for="rv-ngrup" class="lbl">Grup <span class="font-normal text-muted">(opsional)</span></label>
                        <input id="rv-ngrup" type="text" list="rv-daftar-grup" wire:model="namaGrup" maxlength="60" class="inp" placeholder="Keluarga, Teman, Kolega" autocomplete="off" @error('namaGrup') aria-invalid="true" @enderror>
                        <datalist id="rv-daftar-grup">@foreach ($daftarGrup as $g)<option value="{{ $g }}"></option>@endforeach</datalist>
                        @error('namaGrup')<div role="alert" class="galat mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label for="rv-jumlah" class="lbl">Jumlah tamu</label>
                        <input id="rv-jumlah" type="number" min="1" max="20" wire:model="jumlah" class="inp" @error('jumlah') aria-invalid="true" @enderror>
                        @error('jumlah')<div role="alert" class="galat mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>
                @if (! $tamuId && $sisaTamu !== null)
                    <div class="hint">Sisa kuota tamu: {{ $angka($sisaTamu) }}.</div>
                @endif
            </form>
            <x-slot:footer>
                <button type="button" class="btn btn-secondary" wire:click="tutupDialog">Batal</button>
                <button type="submit" form="rv-form-tamu" class="btn btn-primary" wire:loading.attr="disabled" wire:target="simpanTamu">{{ $tamuId ? 'Simpan perubahan' : 'Tambah tamu' }}</button>
            </x-slot:footer>
        </x-dialog>

        {{-- Ubah RSVP tamu --}}
        <x-dialog wire:model="tampilUbahRsvp" judul="Ubah RSVP tamu" sub="Tandai kehadiran secara manual, misalnya tamu yang menjawab lewat telepon.">
            <form id="rv-form-rsvp" wire:submit="simpanRsvp" class="flex flex-col gap-3.5" novalidate>
                <div>
                    <span class="lbl">Kehadiran</span>
                    <div class="seg" role="group" aria-label="Kehadiran">
                        <button type="button" wire:click="$set('rsvpStatus', 'hadir')" aria-pressed="{{ $rsvpStatus === 'hadir' ? 'true' : 'false' }}">Hadir</button>
                        <button type="button" wire:click="$set('rsvpStatus', 'tidak')" aria-pressed="{{ $rsvpStatus === 'tidak' ? 'true' : 'false' }}">Tidak hadir</button>
                    </div>
                    @error('rsvpStatus')<div role="alert" class="galat mt-1">{{ $message }}</div>@enderror
                </div>
                @if ($rsvpStatus === 'hadir')
                    <div>
                        <label for="rv-rjumlah" class="lbl">Jumlah orang yang hadir</label>
                        <input id="rv-rjumlah" type="number" min="1" max="20" wire:model="rsvpJumlah" class="inp w-32" @error('rsvpJumlah') aria-invalid="true" @enderror>
                        @error('rsvpJumlah')<div role="alert" class="galat mt-1">{{ $message }}</div>@enderror
                    </div>
                @endif
            </form>
            <x-slot:footer>
                <button type="button" class="btn btn-secondary" wire:click="tutupDialog">Batal</button>
                <button type="submit" form="rv-form-rsvp" class="btn btn-primary" wire:loading.attr="disabled" wire:target="simpanRsvp">Simpan</button>
            </x-slot:footer>
        </x-dialog>

        {{-- Hapus satu tamu --}}
        <x-dialog wire:model="tampilHapus" judul="Hapus tamu ini?" lebar="max-w-[480px]">
            <p class="m-0 text-sm leading-6 text-body">Tamu dan tautan pribadinya dihapus. Jawaban RSVP yang sudah masuk tetap tersimpan sebagai jawaban tanpa tamu terdaftar.</p>
            <x-slot:footer>
                <button type="button" class="btn btn-secondary" wire:click="tutupDialog">Batal</button>
                <button type="button" class="btn btn-danger-solid" wire:click="hapusTamu" wire:loading.attr="disabled" wire:target="hapusTamu">Hapus tamu</button>
            </x-slot:footer>
        </x-dialog>

        {{-- Hapus satu jawaban --}}
        <x-dialog wire:model="tampilHapusRsvp" judul="Hapus jawaban ini?" lebar="max-w-[480px]">
            <p class="m-0 text-sm leading-6 text-body">Jawaban RSVP dihapus. Bila tamu terdaftar, statusnya kembali menjadi belum RSVP.</p>
            <x-slot:footer>
                <button type="button" class="btn btn-secondary" wire:click="tutupDialog">Batal</button>
                <button type="button" class="btn btn-danger-solid" wire:click="hapusRsvp" wire:loading.attr="disabled" wire:target="hapusRsvp">Hapus jawaban</button>
            </x-slot:footer>
        </x-dialog>

        {{-- Hapus massal --}}
        <x-dialog wire:model="tampilHapusMassal" judul="Hapus {{ count($terpilih) }} tamu?" lebar="max-w-[480px]">
            <p class="m-0 text-sm leading-6 text-body">Semua tamu yang dipilih dan tautan pribadinya akan dihapus. Tindakan ini tidak bisa dibatalkan.</p>
            <x-slot:footer>
                <button type="button" class="btn btn-secondary" wire:click="tutupDialog">Batal</button>
                <button type="button" class="btn btn-danger-solid" wire:click="hapusMassal" wire:loading.attr="disabled" wire:target="hapusMassal">Hapus {{ count($terpilih) }} tamu</button>
            </x-slot:footer>
        </x-dialog>

        {{-- Impor CSV --}}
        <x-dialog wire:model="tampilImpor" judul="Impor tamu dari CSV" sub="Kolom: nama, hp, grup, jumlah. Maksimal 500 baris dan 256 KB." lebar="max-w-[720px]">
            <div class="flex flex-col gap-3.5">
                <div>
                    <label for="rv-berkas" class="lbl">Berkas CSV</label>
                    <input id="rv-berkas" type="file" wire:model="berkasCsv" accept=".csv,.txt,text/csv,text/plain" class="inp file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-1 file:text-[13px] file:font-semibold file:text-brand" @error('berkasCsv') aria-invalid="true" @enderror>
                    <div wire:loading wire:target="berkasCsv" class="hint mt-1">Membaca berkas…</div>
                    @error('berkasCsv')<div role="alert" class="galat mt-1">{{ $message }}</div>@enderror
                    <a href="data:text/csv;charset=utf-8,{{ rawurlencode($contohCsv) }}" download="contoh-tamu.csv" class="mt-1.5 inline-flex items-center gap-1.5 text-[13px] font-medium text-brand"><x-ikon nama="rsvp-berkas" :ukuran="14" /> Unduh contoh CSV</a>
                </div>

                @if ($imporGalat !== '')
                    <div role="alert" class="rounded-xl border border-[#fecdca] bg-[#fef3f2] px-4 py-3 text-[13px] leading-5 font-medium text-[#b42318]">{{ $imporGalat }}</div>
                @elseif (count($impor) > 0 || count($imporGagal) > 0 || $imporDuplikat > 0)
                    <div role="status" class="flex flex-wrap gap-2">
                        <x-pill warna="hijau">{{ count($impor) }} siap diimpor</x-pill>
                        @if ($imporDuplikat > 0)<x-pill warna="abu">{{ $imporDuplikat }} duplikat dilewati</x-pill>@endif
                        @if (count($imporGagal) > 0)<x-pill warna="merah">{{ count($imporGagal) }} baris gagal</x-pill>@endif
                    </div>
                    @if (count($impor) > 0)
                        <div class="overflow-x-auto rounded-xl border border-line">
                            <table class="tabel">
                                <caption class="sr-only">Pratinjau tamu yang akan diimpor</caption>
                                <thead><tr><th>Nama</th><th>WhatsApp</th><th>Grup</th><th>Jml</th></tr></thead>
                                <tbody>
                                    @foreach (array_slice($impor, 0, 8) as $b)
                                        <tr><td>{{ $b['nama'] }}</td><td>{{ \App\Support\NomorWa::tampil($b['hp']) ?? '–' }}</td><td>{{ $b['grup'] ?? '–' }}</td><td>{{ $b['jumlah'] }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if (count($impor) > 8)<div class="hint">…dan {{ count($impor) - 8 }} baris lain.</div>@endif
                    @endif
                    @if (count($imporGagal) > 0)
                        <div class="rounded-xl border border-[#fecdca] bg-[#fef3f2] px-4 py-3">
                            <div class="text-[13px] font-semibold text-[#b42318]">Baris yang tidak diimpor</div>
                            <ul class="m-0 mt-1.5 list-disc pl-5 text-[13px] leading-5 text-[#b42318]">
                                @foreach (array_slice($imporGagal, 0, 10) as $g)
                                    <li>Baris {{ $g['baris'] }}: {{ $g['alasan'] }}</li>
                                @endforeach
                            </ul>
                            @if (count($imporGagal) > 10)<div class="hint mt-1">…dan {{ count($imporGagal) - 10 }} baris lain.</div>@endif
                        </div>
                    @endif
                    @if ($sisaTamu !== null)
                        <div class="hint">Sisa kuota tamu: {{ $angka($sisaTamu) }}. Baris di atas kuota dilewati.</div>
                    @endif
                @endif
            </div>
            <x-slot:footer>
                <button type="button" class="btn btn-secondary" wire:click="tutupDialog">Batal</button>
                <button type="button" class="btn btn-primary" wire:click="jalankanImpor" @disabled(count($impor) === 0) wire:loading.attr="disabled" wire:target="jalankanImpor">Impor {{ count($impor) }} tamu</button>
            </x-slot:footer>
        </x-dialog>

        {{-- Pengingat --}}
        <x-dialog wire:model="tampilPengingat" judul="Kirim pengingat RSVP" sub="Pesan dibuka di WhatsApp satu per satu. Setelah dibuka, tamu ditandai sudah diingatkan." lebar="max-w-[640px]">
            @if ($tampilPengingat)
                <div class="max-h-[50vh] divide-y divide-[#eef0f3] overflow-y-auto rounded-xl border border-line">
                    @forelse ($pengingat as $t)
                        <div class="flex items-center justify-between gap-3 px-3.5 py-2.5" wire:key="ing-{{ $t->id }}">
                            <div class="min-w-0">
                                <div class="truncate text-sm font-medium text-ink">{{ $t->nama }}</div>
                                <div class="sub truncate">{{ \App\Support\NomorWa::tampil($t->hp) ?? 'Belum ada nomor · pilih kontak sendiri' }}@if ($t->diingatkan_at) · diingatkan {{ \Illuminate\Support\Carbon::parse($t->diingatkan_at)->locale('id')->diffForHumans() }}@endif</div>
                            </div>
                            <a href="{{ $waLink($t) }}" target="_blank" rel="noopener noreferrer" wire:click="tandaiDiingatkan({{ $t->id }})" class="btn btn-secondary btn-sm shrink-0" aria-label="Buka WhatsApp untuk {{ $t->nama }}"><x-ikon nama="rsvp-wa" :ukuran="14" /> {{ $t->diingatkan_at ? 'Ingatkan lagi' : ($t->hp ? 'Buka WhatsApp' : 'Pilih kontak') }}</a>
                        </div>
                    @empty
                        <div class="sub p-4 text-center">Semua tamu sudah konfirmasi.</div>
                    @endforelse
                </div>
                @if ($r['belum'] > $maksPengingat)<div class="hint">Menampilkan {{ $maksPengingat }} tamu pertama dari {{ $angka($r['belum']) }}.</div>@endif
            @endif
            <x-slot:footer>
                <button type="button" class="btn btn-primary" wire:click="tutupDialog">Selesai</button>
            </x-slot:footer>
        </x-dialog>
    @endif
</div>
