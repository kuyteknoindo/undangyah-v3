<div class="mx-auto flex max-w-6xl flex-col gap-5">
    <section aria-label="Cari bantuan" class="relative overflow-hidden rounded-2xl bg-brand px-5 py-8 text-white sm:px-8 sm:py-10">
        <div aria-hidden="true" class="pointer-events-none absolute -top-16 -right-10 size-56 rounded-full bg-white/10"></div>
        <div aria-hidden="true" class="pointer-events-none absolute -bottom-20 left-1/3 size-48 rounded-full bg-white/5"></div>
        <div class="relative flex max-w-2xl flex-col gap-4">
            <div>
                <h2 class="m-0 text-2xl leading-8 font-bold tracking-tight">Ada yang bisa kami bantu?</h2>
                <p class="mt-1 mb-0 text-sm leading-6 text-white/85">Cari jawaban dari pertanyaan yang paling sering ditanyakan, atau pilih kategori di bawah.</p>
            </div>
            <div class="relative">
                <span class="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-muted"><x-ikon nama="bantuan-cari" :ukuran="18" /></span>
                <label for="hp-cari" class="sr-only">Cari pertanyaan</label>
                <input id="hp-cari" type="search" wire:model.live.debounce.300ms="cari" maxlength="120" autocomplete="off" placeholder="Cari pertanyaan..."
                       class="inp !min-h-12 !rounded-xl !pr-11 !pl-10">
                @if ($cari !== '')
                    <button type="button" wire:click="aturUlang" aria-label="Hapus pencarian" class="absolute top-1/2 right-2 flex size-8 -translate-y-1/2 items-center justify-center rounded-lg text-muted hover:bg-gray-100"><x-ikon nama="tutup" :ukuran="16" /></button>
                @endif
            </div>
        </div>
    </section>

    <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_320px]">
        <div class="flex min-w-0 flex-col gap-4">
            <section aria-label="Kategori bantuan" class="flex flex-wrap gap-2">
                @foreach (['semua' => 'Semua'] + \App\Models\BantuanArtikel::KATEGORI as $kunci => $label)
                    @php
$aktif = $kategoriAktif === $kunci;
@endphp
                    <button type="button" wire:click="pilihKategori('{{ $kunci }}')" aria-pressed="{{ $aktif ? 'true' : 'false' }}" aria-label="{{ $label }}, {{ $jumlah[$kunci] }} pertanyaan"
                            class="inline-flex min-h-11 items-center gap-2.5 rounded-full border-2 py-0 pr-2.5 pl-4 text-sm leading-[19px] font-bold {{ $aktif ? 'border-brand bg-brand-100 text-brand' : 'border-line bg-white text-ink hover:bg-gray-50' }}">
                        <span>{{ $label }}</span>
                        <span class="inline-flex h-6 min-w-6 items-center justify-center rounded-xl px-1.5 text-xs leading-4 font-bold {{ $aktif ? 'bg-brand text-white' : 'bg-canvas text-nav' }}">{{ $jumlah[$kunci] }}</span>
                    </button>
                @endforeach
            </section>

            <div role="status" class="text-[13px] leading-[18px] text-muted">{{ $ringkasan }}</div>

            @if ($artikel->isEmpty())
                <div class="kartu p-6">
                    <x-kosong judul="Jawaban belum ditemukan" teks="Coba kata kunci lain, atau pilih kategori yang berbeda. Kalau masih belum ketemu, tim kami siap membantu." ikon="bantuan-cari">
                        <button type="button" wire:click="aturUlang" class="btn btn-secondary">Reset pencarian</button>
                        @if ($tautanHubungi)<a href="{{ $tautanHubungi }}" wire:navigate class="btn btn-primary">Hubungi kami</a>@endif
                    </x-kosong>
                </div>
            @else
                <section aria-label="Pertanyaan umum" class="flex flex-col gap-2.5">
                    @foreach ($artikel as $a)
                        @php
                            $buka = $terbuka === $a->id;
                            $pilihan = $suara->has($a->id) ? ($suara[$a->id] ? 'ya' : 'tidak') : null;
                            $href = $tautan($a);
                        @endphp
                        <div wire:key="artikel-{{ $a->id }}" class="kartu overflow-hidden {{ $buka ? '!border-[#BFD3FF]' : '' }}">
                            <h3 class="m-0">
                                <button type="button" wire:click="ubahBuka({{ $a->id }}, {{ $buka ? 'true' : 'false' }})" aria-expanded="{{ $buka ? 'true' : 'false' }}" aria-controls="jawab-{{ $a->id }}"
                                        class="flex min-h-14 w-full items-center justify-between gap-4 px-5 py-3 text-left text-[15px] leading-[22px] font-semibold text-ink hover:bg-gray-50">
                                    <span>{{ $a->judul }}</span>
                                    <span class="flex shrink-0 text-muted transition-transform duration-200 {{ $buka ? 'rotate-180' : '' }}"><x-ikon nama="panah-bawah" :ukuran="18" /></span>
                                </button>
                            </h3>
                            @if ($buka)
                                <div id="jawab-{{ $a->id }}" class="flex flex-col gap-3 border-t border-line px-5 py-4">
                                    <div class="flex flex-col gap-2 text-sm leading-6 text-body">{{ \App\Support\TeksBantuan::html($a->isi) }}</div>
                                    @if ($href)
                                        <div><a href="{{ $href }}" wire:navigate class="inline-flex items-center gap-1.5 text-[13px] font-semibold text-brand hover:underline">{{ $a->rute_label ?: 'Buka halaman' }} <x-ikon nama="panah-kanan" :ukuran="14" /></a></div>
                                    @endif
                                    <div class="flex flex-wrap items-center gap-2 border-t border-line pt-3">
                                        @if ($pilihan === null)
                                            <span class="mr-1 text-[13px] text-muted">Apakah ini membantu?</span>
                                            <button type="button" wire:click="suara({{ $a->id }}, 'ya')" class="btn btn-secondary btn-sm">Ya</button>
                                            <button type="button" wire:click="suara({{ $a->id }}, 'tidak')" class="btn btn-secondary btn-sm">Tidak</button>
                                        @else
                                            <span role="status" class="inline-flex items-center gap-1.5 text-[13px] font-semibold text-ink"><x-ikon nama="bantuan-centang" :ukuran="16" class="text-[#58A700]" />{{ $pilihan === 'ya' ? 'Terima kasih, senang bisa membantu.' : 'Terima kasih masukannya.' }}</span>
                                            @if ($pilihan === 'tidak' && $tautanHubungi)<a href="{{ $tautanHubungi }}" wire:navigate class="btn btn-primary btn-sm">Hubungi layanan</a>@endif
                                            <button type="button" wire:click="suara({{ $a->id }}, 'batal')" class="btn btn-ghost btn-sm">Ubah jawaban</button>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </section>
            @endif
        </div>

        <aside aria-label="Butuh bantuan lain" class="flex flex-col gap-3">
            <x-bantuan-kontak :kontak="$kontak" :hubungi="$tautanHubungi" />
            @if ($tautanSyarat)
                <a href="{{ $tautanSyarat }}" wire:navigate class="kartu flex min-h-14 items-center gap-3 p-3.5 transition hover:bg-gray-50">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-canvas text-nav"><x-ikon nama="bantuan-dokumen" :ukuran="18" /></span>
                    <span class="flex-1 text-sm font-semibold text-ink">Syarat &amp; Privasi</span>
                    <x-ikon nama="panah-kanan" :ukuran="16" class="text-subtle" />
                </a>
            @endif
        </aside>
    </div>
</div>
