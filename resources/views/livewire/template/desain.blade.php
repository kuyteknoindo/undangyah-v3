@php
    $jumlahTampil = $tema->count().' dari '.$semua->count().' tema';
    $kosongSemua = $semua->isEmpty();
    $kunciPv = $pv ? ($terkunci[$pv->id] ?? false) : false;
    $dipakaiPv = $pv && $undangan && $undangan->tema_id === $pv->id;
@endphp
<div wire:init="muat" class="mx-auto flex max-w-6xl flex-col gap-5">
    @unless ($siap)
        <div role="status" aria-busy="true" class="flex flex-col gap-5">
            <span class="sr-only">Memuat halaman…</span>
            <div class="kartu flex flex-col gap-3 p-5"><span class="skel h-4 w-48"></span><span class="skel h-11 w-full max-w-md"></span></div>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-4">
                @for ($i = 0; $i < 8; $i++)
                    <div class="kartu flex flex-col gap-3 p-3"><span class="skel aspect-[4/5] w-full rounded-xl"></span><span class="skel h-3.5 w-2/3"></span><span class="skel h-3 w-1/2"></span></div>
                @endfor
            </div>
        </div>
    @else
        @if ($catatan !== '')
            <div role="status" class="flex items-center gap-3 rounded-2xl border px-4 py-3 text-[13px] leading-5 font-medium {{ $catatanTipe === 'galat' ? 'border-[#fecdca] bg-danger-50 text-danger' : ($catatanTipe === 'info' ? 'border-line bg-canvas text-body' : 'border-[#abefc6] bg-[#ecfdf3] text-[#067647]') }}">
                <x-ikon :nama="$catatanTipe === 'sukses' ? 'centang' : 'template-info'" :ukuran="16" />
                <span class="min-w-0 flex-1">{{ $catatan }}</span>
                @if ($tautanEditor)
                    <a href="{{ $tautanEditor }}" wire:navigate class="btn btn-secondary btn-sm shrink-0"><x-ikon nama="edit" :ukuran="14" /> Buka editor</a>
                @endif
                @if ($catatanTipe === 'galat' && $tautanBilling && str_contains($catatan, 'Upgrade'))
                    <a href="{{ $tautanBilling }}" wire:navigate class="btn btn-secondary btn-sm shrink-0">Lihat paket</a>
                @endif
            </div>
        @endif

        <section aria-label="Undangan yang diubah" class="kartu grid gap-4 p-4 sm:p-5 md:grid-cols-[minmax(0,360px)_minmax(0,1fr)] md:items-end">
            <div class="flex flex-col gap-1.5">
                <label for="td-inv" class="label">Terapkan tema ke undangan</label>
                @if ($daftarUndangan->isNotEmpty())
                    <select id="td-inv" class="inp" wire:change="pilihUndangan($event.target.value)">
                        @foreach ($daftarUndangan as $u)
                            <option value="{{ $u->id }}" @selected($u->id === $undangan?->id)>{{ $u->judul }} ({{ $u->sudahTerbit() ? 'Terbit' : 'Draf' }})</option>
                        @endforeach
                    </select>
                @else
                    <select id="td-inv" class="inp" disabled><option>Belum ada undangan</option></select>
                @endif
            </div>
            <div class="min-w-0">
                <div class="text-[11px] leading-[14px] font-bold tracking-[0.08em] text-muted uppercase">Tema aktif</div>
                <div class="mt-1 truncate text-base leading-6 font-semibold text-ink">
                    {{ $aktif?->nama ?? ($undangan?->tema_id ? 'Tema tidak tersedia lagi' : 'Belum dipilih') }}
                </div>
                <div class="sub">
                    @if (! $undangan)
                        Belum ada undangan tersimpan.
                    @elseif ($aktif)
                        Untuk undangan {{ $undangan->judul }}.
                    @elseif ($undangan->tema_id)
                        Tema undangan ini sudah tidak diterbitkan. Pilih tema lain.
                    @else
                        Pilih tema di bawah untuk {{ $undangan->judul }}.
                    @endif
                </div>
            </div>
            @unless ($undangan)
                <div class="flex items-start gap-2 rounded-xl bg-canvas p-3 text-[13px] leading-5 text-muted md:col-span-2">
                    <x-ikon nama="template-info" :ukuran="16" class="mt-0.5" />
                    <span>Belum ada undangan tersimpan. Buat undangan dulu supaya tema bisa diterapkan, atau <a href="{{ $tautanBuat }}" wire:navigate class="font-semibold text-brand">buat undangan baru</a>.</span>
                </div>
            @endunless
        </section>

        <section aria-label="Cari dan saring tema" class="flex flex-col gap-3 md:flex-row md:items-center">
            <div class="field max-w-md flex-1">
                <span class="text-muted"><x-ikon nama="template-cari" :ukuran="18" /></span>
                <label for="td-q" class="sr-only">Cari tema</label>
                <input id="td-q" type="search" class="field-input" placeholder="Cari nama tema atau gaya huruf" maxlength="60" wire:model.live.debounce.300ms="q" autocomplete="off">
            </div>
            <div role="group" aria-label="Saring menurut paket" class="seg self-start">
                @foreach ($chip as $c)
                    <button type="button" wire:click="pilihTier('{{ $c['nama'] }}')" aria-pressed="{{ $tier === $c['nama'] ? 'true' : 'false' }}">{{ $c['nama'] }} · {{ $c['jumlah'] }}</button>
                @endforeach
            </div>
            <div class="sub md:ml-auto" aria-live="polite">{{ $jumlahTampil }}</div>
        </section>

        @if ($tema->isEmpty())
            <div role="status" class="kartu">
                <x-kosong :judul="$kosongSemua ? 'Belum ada tema yang diterbitkan' : 'Tema tidak ditemukan'" :teks="$kosongSemua ? 'Tema yang diterbitkan admin akan muncul di sini.' : 'Tidak ada tema yang cocok dengan pencarian atau paket yang dipilih.'" ikon="template">
                    @unless ($kosongSemua)
                        <button type="button" wire:click="tampilkanSemua" class="btn btn-secondary btn-sm">Tampilkan semua</button>
                    @endunless
                </x-kosong>
            </div>
        @endif

        <section aria-label="Daftar tema" class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-4">
            @foreach ($tema as $t)
                @php
                    $kunci = $terkunci[$t->id] ?? false;
                    $dipakai = $undangan && $undangan->tema_id === $t->id;
                @endphp
                <article wire:key="tema-{{ $t->id }}" class="kartu flex flex-col gap-3 rounded-3xl p-3 {{ $dipakai ? '!border-brand' : '' }}">
                    <button type="button" wire:click="pratinjau({{ $t->id }})" aria-label="Pratinjau tema {{ $t->nama }}" class="relative block w-full rounded-xl text-left">
                        <x-buat-undangan.sampul :tema="$t" :judul="$judulPv" :tanggal="$tanggalPv" />
                        @if ($dipakai)
                            <span class="absolute top-2 left-2 inline-flex items-center gap-1 rounded-full bg-brand px-2 py-0.5 text-[11px] leading-4 font-semibold text-white"><x-ikon nama="centang" :ukuran="12" /> Dipakai</span>
                        @endif
                        @if ($kunci)
                            <span class="absolute top-2 right-2 flex size-6 items-center justify-center rounded-full bg-gray-900 text-white"><x-ikon nama="gembok" :ukuran="13" /></span>
                        @endif
                    </button>
                    <div class="flex items-center justify-between gap-2">
                        <span class="truncate text-sm leading-5 font-semibold text-ink">{{ $t->nama }}</span>
                        <span class="shrink-0 rounded-md px-1.5 text-[11px] leading-4 font-semibold" style="{{ \App\Support\PratinjauTema::TIER[$t->tier] ?? \App\Support\PratinjauTema::TIER['Gratis'] }}">{{ $t->tier }}</span>
                    </div>
                    <div class="flex items-center gap-2 text-xs leading-4 text-muted">
                        <span class="flex gap-1" aria-hidden="true">
                            @foreach ([$t->warna_utama, $t->warna_aksen, $t->warna_permukaan] as $w)
                                <span class="size-3 rounded-full border border-line" style="background:{{ $w }}"></span>
                            @endforeach
                        </span>
                        <span class="truncate">Huruf judul: {{ $namaHuruf[$t->font_judul] ?? 'Serif klasik' }}</span>
                    </div>
                    <div class="mt-auto flex flex-wrap items-center gap-2">
                        <button type="button" wire:click="pratinjau({{ $t->id }})" class="btn btn-secondary btn-sm"><x-ikon nama="mata" :ukuran="14" /> Pratinjau</button>
                        @if ($dipakai)
                            <span class="inline-flex min-h-8 items-center gap-1 text-[13px] font-semibold text-brand">Dipakai</span>
                        @elseif ($kunci)
                            @if ($tautanBilling)
                                <a href="{{ $tautanBilling }}" wire:navigate class="btn btn-secondary btn-sm">Upgrade</a>
                            @endif
                        @elseif ($undangan)
                            <button type="button" wire:click="pakai({{ $t->id }})" wire:loading.attr="disabled" wire:target="pakai({{ $t->id }})" class="btn btn-primary btn-sm">Pakai tema</button>
                        @endif
                    </div>
                </article>
            @endforeach
        </section>

        <x-dialog wire:model="tampilPratinjau" :judul="$pv ? 'Pratinjau tema '.$pv->nama : 'Pratinjau tema'" lebar="max-w-[880px]">
            @if ($pv)
                @php
                    $palet = [['Utama', $pv->warna_utama], ['Aksen', $pv->warna_aksen], ['Latar', $pv->warna_permukaan], ['Teks', $pv->warna_teks]];
                @endphp
                <div class="grid gap-5 md:grid-cols-[minmax(0,320px)_minmax(0,1fr)]">
                    <div class="mx-auto w-full max-w-[320px] md:max-w-none">
                        @if ($modePratinjau === 'penuh' && $htmlPenuh)
                            <iframe title="Pratinjau penuh {{ $pv->nama }}" srcdoc="{{ $htmlPenuh }}" sandbox="allow-scripts" loading="lazy" class="aspect-[9/16] w-full rounded-xl border-2 border-line bg-white"></iframe>
                        @else
                            <x-buat-undangan.sampul :tema="$pv" :judul="$judulPv" :tanggal="$tanggalPv" :besar="true" />
                        @endif
                    </div>
                    <div class="flex min-w-0 flex-col gap-4">
                        <div class="flex items-center gap-2">
                            <h3 class="m-0 truncate text-lg leading-7 font-semibold text-ink">{{ $pv->nama }}</h3>
                            <span class="rounded-md px-1.5 text-[11px] leading-4 font-semibold" style="{{ \App\Support\PratinjauTema::TIER[$pv->tier] ?? \App\Support\PratinjauTema::TIER['Gratis'] }}">{{ $pv->tier }}</span>
                        </div>
                        @if ($penuhTersedia)
                            <div class="seg self-start" role="group" aria-label="Jenis pratinjau">
                                <button type="button" wire:click="$set('modePratinjau', 'sampul')" aria-pressed="{{ $modePratinjau !== 'penuh' ? 'true' : 'false' }}">Sampul</button>
                                <button type="button" wire:click="$set('modePratinjau', 'penuh')" aria-pressed="{{ $modePratinjau === 'penuh' ? 'true' : 'false' }}">Undangan penuh</button>
                            </div>
                            @if ($modePratinjau === 'penuh' && ! $htmlPenuh)
                                <div class="hint">Pratinjau penuh belum bisa ditampilkan. Sampul ditampilkan sebagai gantinya.</div>
                            @endif
                        @endif
                        <div>
                            <div class="text-[11px] leading-[14px] font-bold tracking-[0.08em] text-muted uppercase">Palet warna</div>
                            <div class="mt-2 grid grid-cols-2 gap-2 text-[13px] leading-[18px] text-body">
                                @foreach ($palet as [$nm, $hex])
                                    <div class="flex items-center gap-2"><span class="size-4 rounded-full border border-line" style="background:{{ $hex }}"></span>{{ $nm }} {{ strtoupper($hex) }}</div>
                                @endforeach
                            </div>
                        </div>
                        <div class="sub">Huruf judul: {{ $namaHuruf[$pv->font_judul] ?? 'Serif klasik' }}. Pratinjau sampul memakai {{ $undangan ? 'judul undanganmu' : 'teks contoh' }}; isi undanganmu tetap sama saat tema diganti.</div>
                    </div>
                </div>
            @endif
            <x-slot:footer>
                @if ($pv)
                    @if ($dipakaiPv)
                        <span class="mr-auto inline-flex items-center gap-1.5 text-[13px] font-semibold text-brand"><x-ikon nama="centang" :ukuran="14" /> Sedang dipakai</span>
                    @elseif ($kunciPv)
                        @if ($tautanBilling)
                            <a href="{{ $tautanBilling }}" wire:navigate class="btn btn-primary">Upgrade ke {{ $pv->tier }}</a>
                        @endif
                    @elseif ($undangan)
                        <button type="button" wire:click="pakai({{ $pv->id }})" wire:loading.attr="disabled" wire:target="pakai({{ $pv->id }})" class="btn btn-primary">Pakai tema ini</button>
                    @endif
                @endif
                <button type="button" wire:click="tutupPratinjau" class="btn btn-secondary">Tutup</button>
            </x-slot:footer>
        </x-dialog>
    @endif
</div>
