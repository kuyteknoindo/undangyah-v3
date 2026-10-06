@php
    $angka = fn (int|float $n): string => number_format($n, 0, ',', '.');
    $inisial = function (string $nama): string {
        $kata = preg_split('/\s+/u', trim($nama), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $ini = collect($kata)->take(2)->map(fn ($k) => mb_strtoupper(mb_substr($k, 0, 1)))->implode('');

        return $ini !== '' ? $ini : '?';
    };
    $chips = [['semua', 'Semua', 'total'], ['tampil', 'Tampil', 'tampil'], ['sembunyi', 'Disembunyikan', 'sembunyi'], ['sematan', 'Disematkan', 'sematan']];
    @endphp
<div wire:init="muat" class="mx-auto flex max-w-6xl flex-col gap-5">
    @unless ($siap)
        <div role="status" aria-busy="true" class="flex flex-col gap-5">
            <span class="sr-only">Memuat buku tamu…</span>
            <div class="kartu flex flex-col gap-3 p-5"><span class="skel h-3 w-24"></span><span class="skel h-11 w-full max-w-md"></span></div>
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                @for ($i = 0; $i < 4; $i++)
                    <div class="kartu flex flex-col gap-3 p-4"><span class="skel h-3 w-20"></span><span class="skel h-7 w-12"></span></div>
                @endfor
            </div>
            <div class="kartu flex flex-col gap-4 p-5">
                <span class="skel h-4 w-32"></span><span class="skel h-10 w-full"></span>
                @for ($i = 0; $i < 3; $i++)
                    <div class="flex flex-col gap-2 rounded-2xl border border-line p-4"><span class="skel h-4 w-40"></span><span class="skel h-3 w-full"></span><span class="skel h-3 w-2/3"></span></div>
                @endfor
            </div>
        </div>
    @elseif (! $undangan)
        <section aria-labelledby="bt-kosong" class="kartu p-6 sm:p-8">
            <x-kosong judul="Belum ada undangan" teks="Ucapan dan doa dari tamu akan terkumpul di sini setelah undanganmu dibagikan." ikon="buku">
                <a href="{{ $tautanBuat }}" wire:navigate class="btn btn-primary"><x-ikon nama="tambah" :ukuran="16" /> Buat undangan</a>
            </x-kosong>
        </section>
    @else
        <section aria-label="Pilih undangan" class="kartu flex flex-wrap items-end justify-between gap-4 p-4 sm:p-5">
            <div class="flex w-full max-w-md flex-col gap-1.5">
                <label for="bt-inv" class="label">Undangan</label>
                <select id="bt-inv" class="inp" wire:change="pilihUndangan($event.target.value)">
                    @foreach ($daftar as $d)
                        <option value="{{ $d->id }}" @selected($d->id === $undangan->id)>{{ $d->judul }} ({{ $d->sudahTerbit() ? 'Terbit' : 'Draf' }})</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center gap-2.5">
                <x-pill :warna="$undangan->sudahTerbit() ? 'hijau' : 'kuning'">{{ $undangan->sudahTerbit() ? 'Terbit' : 'Draf' }}</x-pill>
                @unless ($undangan->sudahTerbit())
                    <span class="sub">Ucapan masuk setelah undangan diterbitkan.</span>
                @endunless
            </div>
        </section>

        <section aria-label="Ringkasan buku tamu" class="grid grid-cols-1 items-start gap-4 lg:grid-cols-2 [&>*]:min-w-0">
            <div class="grid grid-cols-2 gap-4">
                @foreach ([['Total ucapan', $hitung['total']], ['Tampil', $hitung['tampil']], ['Disematkan', $hitung['sematan']], ['Disembunyikan', $hitung['sembunyi']]] as [$label, $nilai])
                    <div class="kartu kpi"><div class="kpi-l">{{ $label }}</div><div class="kpi-v">{{ $angka($nilai) }}</div></div>
                @endforeach
            </div>

            <div role="group" aria-label="Pengaturan buku tamu" class="kartu flex flex-col gap-4 p-5">
                <div class="flex items-center justify-between gap-4">
                    <div class="min-w-0">
                        <div id="bt-sw-buka" class="text-sm leading-5 font-semibold text-ink">Izinkan tamu menulis ucapan</div>
                        <div class="sub">{{ $pengaturan['buka'] ? 'Formulir ucapan terbuka di undangan.' : 'Ditutup: tamu tidak bisa mengirim ucapan baru.' }}</div>
                    </div>
                    <button type="button" role="switch" class="sakelar" aria-checked="{{ $pengaturan['buka'] ? 'true' : 'false' }}" aria-labelledby="bt-sw-buka" wire:click="ubahBuka" wire:loading.attr="disabled" wire:target="ubahBuka"></button>
                </div>
                <div class="border-t border-line"></div>
                <div class="flex items-center justify-between gap-4">
                    <div class="min-w-0">
                        <div id="bt-sw-setuju" class="text-sm leading-5 font-semibold text-ink">Setujui ucapan dulu</div>
                        <div class="sub">{{ $pengaturan['setujui'] ? 'Ucapan baru tersembunyi sampai kamu tampilkan.' : 'Ucapan baru langsung tampil di undangan.' }}</div>
                    </div>
                    <button type="button" role="switch" class="sakelar" aria-checked="{{ $pengaturan['setujui'] ? 'true' : 'false' }}" aria-labelledby="bt-sw-setuju" wire:click="ubahSetujui" wire:loading.attr="disabled" wire:target="ubahSetujui"></button>
                </div>
                <p class="hint">Pengaturan ini tersimpan per undangan dan langsung berlaku di halaman tamu.</p>
            </div>
        </section>

        <section aria-label="Daftar ucapan" class="kartu flex flex-col">
            <div class="flex flex-col gap-4 p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="h2">Ucapan tamu</h2>
                    <button type="button" wire:click="ekspor" wire:loading.attr="disabled" wire:target="ekspor" class="btn btn-secondary btn-sm"><x-ikon nama="bt-unduh" :ukuran="15" /> Ekspor CSV ({{ $angka($item->total()) }})</button>
                </div>
                <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_200px]">
                    <div class="flex flex-col gap-1.5">
                        <label for="bt-cari" class="label">Cari ucapan atau nama tamu</label>
                        <div class="field">
                            <x-ikon nama="bt-cari" :ukuran="16" class="text-muted" />
                            <input id="bt-cari" type="search" class="field-input" placeholder="Cari ucapan atau nama..." wire:model.live.debounce.400ms="cari" maxlength="100" autocomplete="off">
                        </div>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label for="bt-urut" class="label">Urutkan</label>
                        <select id="bt-urut" class="inp" wire:model.live="urut">
                            <option value="baru">Terbaru</option>
                            <option value="lama">Terlama</option>
                            <option value="nama">Nama A–Z</option>
                        </select>
                    </div>
                </div>
                <div role="group" aria-label="Filter ucapan" class="flex flex-wrap items-center gap-2">
                    @foreach ($chips as [$kunci, $label, $hk])
                        <button type="button" wire:click="pilihFilter('{{ $kunci }}')" aria-pressed="{{ $filter === $kunci ? 'true' : 'false' }}"
                                class="inline-flex min-h-8 items-center gap-1.5 rounded-full border px-3 text-[13px] leading-5 font-medium transition {{ $filter === $kunci ? 'border-brand bg-brand-50 text-brand' : 'border-line-strong bg-white text-nav hover:bg-gray-50' }}">
                            {{ $label }}<span class="tag">{{ $angka($hitung[$hk]) }}</span>
                        </button>
                    @endforeach
                    <span class="hint ml-1">Ucapan yang disematkan selalu di atas.</span>
                </div>
            </div>

            <div class="flex flex-col gap-3 border-t border-line p-5">
                @if ($terhapus)
                    <div role="status" class="flex flex-wrap items-center gap-3 rounded-xl bg-gray-900 px-4 py-2.5 text-[13px] leading-5 text-white">
                        <span class="min-w-0 flex-1">Ucapan dari {{ $terhapus['nama'] }} dihapus.</span>
                        <button type="button" wire:click="urungkan" class="inline-flex items-center gap-1.5 font-semibold text-blue-300 hover:underline"><x-ikon nama="bt-kembali" :ukuran="14" /> Urungkan</button>
                    </div>
                @endif

                @if ($hitung['total'] === 0)
                    <div role="status">
                        <x-kosong judul="Belum ada ucapan" teks="Ucapan dan doa dari tamu akan tampil di sini setelah mereka mengirimnya lewat undangan." ikon="bt-pesan">
                            @if (Route::has('bagikan'))
                                <a href="{{ route('bagikan') }}" wire:navigate class="btn btn-secondary btn-sm">Bagikan undangan</a>
                            @endif
                        </x-kosong>
                    </div>
                @elseif ($item->total() === 0)
                    <div role="status">
                        <x-kosong judul="Ucapan tidak ditemukan" teks="Tidak ada ucapan yang cocok dengan pencarian atau filter ini." ikon="bt-cari">
                            <button type="button" wire:click="resetFilter" class="btn btn-secondary btn-sm">Reset filter</button>
                        </x-kosong>
                    </div>
                @else
                    <div class="grid gap-3 lg:grid-cols-2">
                        @foreach ($item as $x)
                            <article wire:key="ucapan-{{ $x->id }}"
                                     class="flex min-w-0 flex-col gap-3 rounded-2xl p-4 {{ $x->disembunyikan ? 'border-2 border-dashed border-[#CBD0D6] bg-[#F4F5F7]' : ($x->disematkan ? 'border-2 border-[#BFD3FF] bg-white' : 'border-2 border-line bg-white') }}">
                                <div class="flex items-start gap-3">
                                    <span aria-hidden="true" class="flex size-10 shrink-0 items-center justify-center rounded-full bg-brand-50 text-sm font-bold text-brand">{{ $inisial($x->nama) }}</span>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                            <span class="text-sm leading-5 font-semibold text-ink [overflow-wrap:anywhere]">{{ $x->nama ?: 'Tanpa nama' }}</span>
                                            @if ($x->disematkan)<span class="tag bg-brand-50 text-brand"><x-ikon nama="bt-sematan" :ukuran="11" class="mr-1" />Disematkan</span>@endif
                                            @if ($x->disembunyikan)<span class="tag"><x-ikon nama="bt-mata-tutup" :ukuran="11" class="mr-1" />Disembunyikan</span>@endif
                                        </div>
                                        <div class="hint" title="{{ $x->created_at?->translatedFormat('j F Y, H.i') }}">{{ $x->created_at?->locale('id')->diffForHumans() ?? '–' }}</div>
                                    </div>
                                </div>
                                <p class="m-0 text-sm leading-6 whitespace-pre-line text-body [overflow-wrap:anywhere]">{{ $x->pesan }}</p>

                                @if ($hapusId === $x->id)
                                    <div role="alert" class="flex flex-wrap items-center gap-2 rounded-xl bg-[#fef3f2] px-3 py-2">
                                        <span class="min-w-0 flex-1 text-[13px] leading-5 font-medium text-[#b42318]">Hapus ucapan ini?</span>
                                        <button type="button" wire:click="batalHapus" class="btn btn-secondary btn-sm">Batal</button>
                                        <button type="button" wire:click="hapus" class="btn btn-danger-solid btn-sm">Hapus</button>
                                    </div>
                                @else
                                    <div class="flex flex-wrap gap-2">
                                        <button type="button" wire:click="sematkan({{ $x->id }})" class="btn btn-secondary btn-sm"><x-ikon nama="bt-sematan" :ukuran="14" /> {{ $x->disematkan ? 'Lepas sematan' : 'Sematkan' }}</button>
                                        <button type="button" wire:click="sembunyikan({{ $x->id }})" class="btn btn-secondary btn-sm"><x-ikon :nama="$x->disembunyikan ? 'bt-mata' : 'bt-mata-tutup'" :ukuran="14" /> {{ $x->disembunyikan ? 'Tampilkan' : 'Sembunyikan' }}</button>
                                        <button type="button" wire:click="tanyaHapus({{ $x->id }})" class="btn btn-danger btn-sm" aria-label="Hapus ucapan {{ $x->nama }}"><x-ikon nama="bt-hapus" :ukuran="14" /> Hapus</button>
                                    </div>
                                @endif
                            </article>
                        @endforeach
                    </div>

                    @if ($item->lastPage() > 1)
                        <nav aria-label="Halaman ucapan" class="flex flex-wrap items-center justify-between gap-3 pt-2">
                            <span class="hint">Menampilkan {{ $angka($item->firstItem()) }}–{{ $angka($item->lastItem()) }} dari {{ $angka($item->total()) }} ucapan</span>
                            <div class="flex items-center gap-2">
                                <button type="button" wire:click="ke({{ $item->currentPage() - 1 }})" @disabled($item->onFirstPage()) class="btn btn-secondary btn-sm"><x-ikon nama="bt-panah-kiri" :ukuran="14" /> Sebelumnya</button>
                                <span class="text-[13px] text-muted">{{ $item->currentPage() }} / {{ $item->lastPage() }}</span>
                                <button type="button" wire:click="ke({{ $item->currentPage() + 1 }})" @disabled(! $item->hasMorePages()) class="btn btn-secondary btn-sm">Berikutnya <x-ikon nama="bt-panah-kanan" :ukuran="14" /></button>
                            </div>
                        </nav>
                    @endif
                @endif
            </div>
        </section>
    @endif
</div>
