@php
    $angka = fn (int|float $n): string => number_format($n, 0, ',', '.');
    $inisial = function (string $nama): string {
        $kata = preg_split('/\s+/u', trim($nama), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $ini = collect($kata)->take(2)->map(fn ($k) => mb_strtoupper(mb_substr($k, 0, 1)))->implode('');

        return $ini !== '' ? $ini : '?';
    };
    $teksRentang = [7 => '7 hari terakhir', 30 => '30 hari terakhir', 90 => '90 hari terakhir'];
    $grafik = $tampilan === 'grafik';
@endphp
<div wire:init="muat" class="mx-auto flex max-w-6xl flex-col gap-5">
    @unless ($siap)
        <div role="status" aria-busy="true" class="flex flex-col gap-5">
            <span class="sr-only">Memuat statistik…</span>
            <div class="kartu flex flex-col gap-3 p-5"><span class="skel h-3 w-24"></span><span class="skel h-11 w-full max-w-md"></span></div>
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-3 xl:grid-cols-6">
                @for ($i = 0; $i < 6; $i++)
                    <div class="kartu flex flex-col gap-3 p-4"><span class="skel h-3 w-16"></span><span class="skel h-7 w-12"></span><span class="skel h-3 w-20"></span></div>
                @endfor
            </div>
            <div class="kartu p-5"><span class="skel h-4 w-40"></span><span class="skel mt-4 h-44 w-full"></span></div>
            <div class="grid gap-4 lg:grid-cols-2">
                <div class="kartu p-5"><span class="skel h-4 w-32"></span><span class="skel mt-4 h-32 w-full"></span></div>
                <div class="kartu p-5"><span class="skel h-4 w-32"></span><span class="skel mt-4 h-32 w-full"></span></div>
            </div>
        </div>
    @elseif (! $undangan)
        <section aria-labelledby="st-kosong" class="kartu p-6 sm:p-8">
            <x-kosong judul="Belum ada undangan" teks="Kunjungan, RSVP, dan pengiriman undangan akan tampil sebagai statistik di sini." ikon="statistik">
                <a href="{{ $tautanBuat }}" wire:navigate class="btn btn-primary"><x-ikon nama="tambah" :ukuran="16" /> Buat undangan</a>
            </x-kosong>
        </section>
    @else
        @php
            $k = $data['kunjungan'];
            $tamu = $data['tamu'];
            $buckets = $k['buckets'];
            $nilaiV = array_column($buckets, 'nilai');
            $tipsV = array_map(fn ($b) => $b['penuh'].': '.$b['nilai'].' kunjungan', $buckets);
            $tipsJam = array_map(fn ($n, $i) => 'Pukul '.sprintf('%02d', $i).'.00 sampai '.sprintf('%02d', $i).'.59: '.$n.' kunjungan', $data['jam'], array_keys($data['jam']));
            $labelJam = array_map(fn ($i) => $i % 3 === 0 ? sprintf('%02d', $i) : '', array_keys($data['jam']));
            $namaHari = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
            $tipsHari = array_map(fn ($n, $i) => $namaHari[$i].': '.$n.' kunjungan', $data['hari'], array_keys($data['hari']));
            $delta = $k['delta'];
            $persen = fn (int $a, int $b, string $dari): string => $b > 0 ? round($a / $b * 100).'% dari '.$dari : '–';
            $kpis = [
                ['Tamu', $tamu['total'], 'terdaftar', 'text-muted'],
                ['Terkirim', $tamu['terkirim'], $persen($tamu['terkirim'], $tamu['total'], 'tamu'), 'text-muted'],
                ['Dilihat', $tamu['dilihat'], $persen($tamu['dilihat'], $tamu['total'], 'tamu'), 'text-muted'],
                ['RSVP', $data['rsvp']['total'], $data['rsvp']['hadir'].' hadir · '.$data['rsvp']['tidak'].' tidak', 'text-muted'],
                ['Ucapan', $data['ucapan'], $data['ucapan'] > 0 ? 'di buku tamu' : 'belum ada', 'text-muted'],
                ['Kunjungan', $k['total'], $delta === null ? ($k['total'] > 0 ? 'periode sebelumnya kosong' : 'belum ada kunjungan') : ($delta >= 0 ? '+' : '').$delta.'% dari periode sebelumnya', $delta === null ? 'text-muted' : ($delta >= 0 ? 'text-[#3C7A00]' : 'text-[#C81E1E]')],
            ];
            $alur = [['Terkirim', 'dari '.$tamu['total'].' tamu terdaftar', '#1D4ED8'], ['Dilihat', null, '#4A74E6'], ['Sudah RSVP', null, '#7C9CEE'], ['Hadir', null, '#58A700']];
            $a = $tamu['alur'];
            $alur[1][1] = $a[0] > 0 ? round($a[1] / $a[0] * 100).'% dari terkirim' : '–';
            $alur[2][1] = $a[1] > 0 ? round($a[2] / $a[1] * 100).'% dari yang melihat' : '–';
            $alur[3][1] = $a[2] > 0 ? round($a[3] / $a[2] * 100).'% dari yang RSVP' : '–';
            $belumTamu = $belum['daftar'];
        @endphp

        <section aria-label="Pilih undangan" class="kartu flex flex-wrap items-end justify-between gap-4 p-4 sm:p-5">
            <div class="flex w-full max-w-md flex-col gap-1.5">
                <label for="st-inv" class="label">Undangan</label>
                <select id="st-inv" class="inp" wire:change="pilihUndangan($event.target.value)">
                    @foreach ($daftar as $d)
                        <option value="{{ $d->id }}" @selected($d->id === $undangan->id)>{{ $d->judul }} ({{ $d->sudahTerbit() ? 'Terbit' : 'Draf' }})</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center gap-2.5">
                <x-pill :warna="$undangan->sudahTerbit() ? 'hijau' : 'kuning'">{{ $undangan->sudahTerbit() ? 'Terbit' : 'Draf' }}</x-pill>
                @unless ($undangan->sudahTerbit())
                    <span class="sub">Kunjungan tercatat setelah undangan diterbitkan.</span>
                @endunless
            </div>
        </section>

        <div class="flex flex-wrap items-center gap-3">
            <div class="seg" role="group" aria-label="Rentang waktu">
                @foreach ([7 => '7 hari', 30 => '30 hari', 90 => '90 hari'] as $h => $teks)
                    <button type="button" wire:click="pilihRentang({{ $h }})" aria-pressed="{{ $rentang === $h ? 'true' : 'false' }}">{{ $teks }}</button>
                @endforeach
            </div>
            <span class="sub min-w-0 flex-1">{{ $teksRentang[$rentang] }} ({{ app(\App\Services\StatistikUndangan::class)->tglPendek($data['awal']) }} sampai {{ app(\App\Services\StatistikUndangan::class)->tglLengkap($data['akhir']->copy()->subDay()) }}). Kartu di bawah selain Kunjungan menghitung semua waktu.</span>
            <div class="seg" role="group" aria-label="Tampilan data">
                <button type="button" wire:click="pilihTampilan('grafik')" aria-pressed="{{ $grafik ? 'true' : 'false' }}" class="inline-flex items-center gap-1.5"><x-ikon nama="bt-grafik" :ukuran="14" /> Grafik</button>
                <button type="button" wire:click="pilihTampilan('tabel')" aria-pressed="{{ $grafik ? 'false' : 'true' }}" class="inline-flex items-center gap-1.5"><x-ikon nama="bt-tabel" :ukuran="14" /> Tabel</button>
            </div>
        </div>

        <section aria-label="Ringkasan" class="grid grid-cols-2 gap-4 lg:grid-cols-3 xl:grid-cols-6">
            @foreach ($kpis as [$label, $nilai, $ket, $warna])
                <div class="kartu kpi"><div class="kpi-l">{{ $label }}</div><div class="kpi-v">{{ $angka($nilai) }}</div><div class="text-xs leading-4 {{ $warna }}">{{ $ket }}</div></div>
            @endforeach
        </section>

        <section aria-label="Kunjungan undangan" class="kartu flex flex-col gap-4 p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="h2">Kunjungan undangan</h2>
                    <div class="sub">{{ $teksRentang[$rentang] }}, per {{ $rentang === 90 ? 'minggu' : 'hari' }}</div>
                </div>
                <div class="text-right"><div class="text-2xl leading-8 font-semibold text-ink tabular-nums">{{ $angka($k['total']) }}</div><div class="sub">kunjungan</div></div>
            </div>
            @if ($grafik)
                <x-statistik.batang :nilai="$nilaiV" :label="array_column($buckets, 'label')" :sub="array_column($buckets, 'sub')" :tips="$tipsV" judul="Kunjungan undangan" kosong="Belum ada kunjungan pada rentang ini." :rapat="$rentang === 30" />
            @else
                <div class="overflow-x-auto">
                    <table class="tabel"><caption class="sr-only">Kunjungan undangan</caption><thead><tr><th>Periode</th><th class="text-right">Kunjungan</th></tr></thead>
                        <tbody>@foreach ($buckets as $b)<tr><td>{{ $b['penuh'] }}</td><td class="text-right tabular-nums">{{ $b['nilai'] }}</td></tr>@endforeach</tbody></table>
                </div>
            @endif
        </section>

        <div class="grid grid-cols-1 items-start gap-4 lg:grid-cols-2 [&>*]:min-w-0">
            <section aria-label="Alur tamu" class="kartu flex flex-col gap-4 p-5">
                <div><h2 class="h2">Alur tamu</h2><div class="sub">Dari undangan terkirim sampai hadir (semua waktu)</div></div>
                @foreach ($alur as $i => [$label, $ket, $warna])
                    @php
                        $lebar = $a[0] > 0 ? ($a[$i] > 0 ? max(2, $a[$i] / $a[0] * 100) : 0) : 0;
                    @endphp
                    <div class="flex flex-col gap-1.5">
                        <div class="flex items-baseline justify-between gap-3"><span class="text-sm font-medium text-ink">{{ $label }}</span><span class="text-sm font-semibold text-ink tabular-nums">{{ $angka($a[$i]) }}</span></div>
                        <div class="h-2.5 overflow-hidden rounded-full bg-[#eef0f3]" role="presentation"><div class="h-full rounded-full" style="width: {{ round($lebar, 1) }}%; background: {{ $warna }}"></div></div>
                        <div class="hint">{{ $ket }}</div>
                    </div>
                @endforeach
                @if ($tamu['dilihat'] > $a[1])
                    <div class="rounded-lg bg-canvas px-3 py-2 text-xs leading-4 text-muted">Alur hanya menghitung tamu yang sudah ditandai terkirim. {{ $angka($tamu['dilihat'] - $a[1]) }} tamu yang membuka tanpa ditandai terkirim tidak masuk alur.</div>
                @endif
                @if (Route::has('rsvp'))
                    <a href="{{ route('rsvp', ['undangan' => $undangan->id]) }}" wire:navigate class="inline-flex items-center gap-1 text-[13px] font-semibold text-brand hover:underline">Lihat detail RSVP <x-ikon nama="panah-kanan" :ukuran="14" /></a>
                @endif
            </section>

            <section aria-label="Tamu belum membuka" class="kartu flex flex-col gap-3 p-5">
                <div>
                    <h2 class="h2">Tamu belum membuka</h2>
                    <div class="sub">{{ $belum['jumlah'] > 0 ? $angka($belum['jumlah']).' dari '.$angka($tamu['total']).' tamu belum membuka undangan' : 'Semua tamu terdaftar sudah membuka undangan' }}</div>
                </div>
                @if ($tamu['total'] === 0)
                    <div role="status" class="rounded-xl bg-canvas px-4 py-6 text-center text-[13px] text-muted">Belum ada tamu terdaftar. Tambahkan tamu di halaman RSVP &amp; Tamu agar tiap tamu punya tautan pribadi.</div>
                @elseif ($belum['jumlah'] === 0)
                    <div role="status" class="rounded-xl bg-canvas px-4 py-6 text-center text-[13px] text-muted">Tidak ada tamu yang tertinggal.</div>
                @else
                    <ul class="m-0 flex list-none flex-col gap-2 p-0">
                        @foreach ($belumTamu as $t)
                            @php
                                $terkirim = $t->dikirim_at !== null;
                                $tombol = $terkirim ? 'Kirim ulang' : ($t->hp ? 'Kirim undangan' : 'Pilih kontak');
                            @endphp
                            <li wire:key="belum-{{ $t->id }}" class="flex items-center gap-3 rounded-xl border border-line p-3">
                                <span aria-hidden="true" class="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-50 text-[13px] font-bold text-brand">{{ $inisial($t->nama) }}</span>
                                <span class="flex min-w-0 flex-1 flex-col">
                                    <span class="truncate text-sm font-semibold text-ink">{{ $t->nama }}</span>
                                    <span class="truncate text-xs text-muted">{{ $t->grup ? $t->grup.' · ' : '' }}{{ $terkirim ? 'terkirim '.$t->dikirim_at->locale('id')->diffForHumans() : 'belum dikirim' }}</span>
                                </span>
                                @if (isset($belum['baris'][$t->id]))
                                    <a href="{{ $belum['baris'][$t->id] }}" target="_blank" rel="noopener noreferrer" wire:click="catatKirim({{ $t->id }})" class="btn btn-secondary btn-sm shrink-0" aria-label="{{ $tombol }} {{ $t->nama }} lewat WhatsApp"><x-ikon nama="bt-kirim" :ukuran="14" /> {{ $tombol }}</a>
                                @else
                                    <span class="hint shrink-0">Terbitkan dulu</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    @if ($belum['jumlah'] > $ringkas)
                        <button type="button" wire:click="ubahSemuaBelum" class="btn btn-secondary btn-sm self-start">{{ $semuaBelum ? 'Tampilkan lebih sedikit' : 'Lihat semua ('.$angka(min($belum['jumlah'], 50)).')' }}</button>
                    @endif
                @endif
            </section>
        </div>

        <div class="grid grid-cols-1 items-start gap-4 lg:grid-cols-2 [&>*]:min-w-0">
            <section aria-label="Kunjungan per jam" class="kartu flex flex-col gap-4 p-5">
                <div><h2 class="h2">Kunjungan per jam</h2><div class="sub">Pukul berapa tamu paling sering membuka undangan</div></div>
                @if ($grafik)
                    <x-statistik.batang :nilai="$data['jam']" :label="$labelJam" :tips="$tipsJam" judul="Kunjungan per jam" kosong="Belum ada kunjungan pada rentang ini." :rapat="true" />
                @else
                    <div class="max-h-80 overflow-auto">
                        <table class="tabel"><caption class="sr-only">Kunjungan per jam</caption><thead><tr><th>Periode</th><th class="text-right">Kunjungan</th></tr></thead>
                            <tbody>@foreach ($data['jam'] as $i => $n)<tr><td>{{ sprintf('%02d', $i) }}.00 sampai {{ sprintf('%02d', $i) }}.59</td><td class="text-right tabular-nums">{{ $n }}</td></tr>@endforeach</tbody></table>
                    </div>
                @endif
            </section>

            <section aria-label="Kunjungan per hari" class="kartu flex flex-col gap-4 p-5">
                <div><h2 class="h2">Kunjungan per hari</h2><div class="sub">Hari apa tamu paling sering membuka undangan</div></div>
                @if ($grafik)
                    <x-statistik.batang :nilai="$data['hari']" :label="['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min']" :tips="$tipsHari" judul="Kunjungan per hari" kosong="Belum ada kunjungan pada rentang ini." />
                @else
                    <div class="overflow-x-auto">
                        <table class="tabel"><caption class="sr-only">Kunjungan per hari</caption><thead><tr><th>Hari</th><th class="text-right">Kunjungan</th></tr></thead>
                            <tbody>@foreach ($data['hari'] as $i => $n)<tr><td>{{ $namaHari[$i] }}</td><td class="text-right tabular-nums">{{ $n }}</td></tr>@endforeach</tbody></table>
                    </div>
                @endif
            </section>
        </div>

        <section aria-label="Sumber dan perangkat" class="kartu flex items-start gap-3 p-5">
            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-canvas text-nav"><x-ikon nama="bt-info" :ukuran="18" /></span>
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2"><span class="text-sm font-semibold text-ink">Sumber kunjungan &amp; perangkat</span><x-pill warna="abu">Segera hadir</x-pill></div>
                <p class="sub m-0 mt-1">Saat ini undangyah.com hanya mencatat waktu dan, bila tautannya pribadi, nama tamu pada setiap kunjungan. Asal tautan (WhatsApp, Instagram) dan jenis perangkat belum dicatat, jadi belum ditampilkan agar angkanya tidak mengada-ada.</p>
            </div>
        </section>
    @endif
</div>
