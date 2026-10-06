@php
    $terbit = $undangan?->sudahTerbit() ?? false;
    $linkSaatIni = $undangan ? $host.'/'.$undangan->slug : '';
    $linkBaru = $host.'/'.($slug !== '' ? $slug : '...');
    $angka = fn (int $n): string => number_format($n, 0, ',', '.');
    $adaPerubahan = $undangan && ! $sama && $slug !== '';
@endphp
<div wire:init="muat" class="mx-auto flex max-w-6xl flex-col gap-5">
    @unless ($siap)
        <div role="status" aria-busy="true" class="flex flex-col gap-5">
            <span class="sr-only">Memuat halaman…</span>
            <div class="kartu flex flex-col gap-3 p-5"><span class="skel h-4 w-32"></span><span class="skel h-11 w-full max-w-md"></span></div>
            <div class="grid gap-5 grid-cols-[minmax(0,1fr)] lg:grid-cols-[minmax(0,1fr)_360px]">
                <div class="kartu flex flex-col gap-4 p-5"><span class="skel h-5 w-40"></span><span class="skel h-14 w-full"></span><span class="skel h-12 w-full"></span><span class="skel h-10 w-48"></span></div>
                <div class="kartu flex flex-col gap-3 p-5"><span class="skel h-4 w-40"></span><span class="skel h-3 w-full"></span><span class="skel h-3 w-5/6"></span><span class="skel h-3 w-full"></span></div>
            </div>
        </div>
    @elseif (! $undangan)
        <section aria-labelledby="lk-kosong" class="kartu p-6 sm:p-8">
            <x-kosong judul="Belum ada undangan" teks="Link kustom berlaku untuk undangan yang sudah kamu buat. Buat undangan pertamamu untuk mengatur alamatnya." ikon="template-tautan">
                <a href="{{ $tautanBuat }}" wire:navigate class="btn btn-primary"><x-ikon nama="tambah" :ukuran="16" /> Buat undangan</a>
            </x-kosong>
        </section>
    @else
        <section aria-label="Pilih undangan" class="kartu flex flex-wrap items-end justify-between gap-4 p-4 sm:p-5">
            <div class="flex w-full max-w-md flex-col gap-1.5">
                <label for="lk-inv" class="label">Undangan</label>
                <select id="lk-inv" class="inp" wire:change="pilih($event.target.value)">
                    @foreach ($daftar as $u)
                        <option value="{{ $u->id }}" @selected($u->id === $undangan->id)>{{ $u->judul }} ({{ $u->sudahTerbit() ? 'Terbit' : 'Draf' }})</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center gap-2.5">
                <x-pill :warna="$terbit ? 'hijau' : 'kuning'">{{ $terbit ? 'Terbit' : 'Draf' }}</x-pill>
                <span class="sub">{{ $terbit ? 'Link bisa diganti.' : 'Belum diterbitkan.' }}</span>
            </div>
        </section>

        @if ($catatan !== '')
            <div role="status" class="flex flex-wrap items-center gap-3 rounded-2xl border border-[#abefc6] bg-[#ecfdf3] px-4 py-3 text-[13px] leading-5 font-medium text-[#067647]">
                <x-ikon nama="centang" :ukuran="16" />
                <span class="min-w-0 flex-1">{{ $catatan }}</span>
                @if ($tautanBagikan)
                    <a href="{{ $tautanBagikan }}" wire:navigate class="btn btn-secondary btn-sm shrink-0">Bagikan link baru</a>
                @endif
            </div>
        @endif

        <div class="grid items-start gap-5 grid-cols-[minmax(0,1fr)] lg:grid-cols-[minmax(0,1fr)_360px]">
            <div class="flex min-w-0 flex-col gap-5">
                @if ($terbit)
                    <section aria-label="Ganti alamat link" class="kartu flex flex-col gap-5 p-5 sm:p-6">
                        <div>
                            <h2 class="h2">Alamat link</h2>
                            <div class="sub mt-0.5">Pilih alamat yang mudah diingat dan enak dibagikan.</div>
                        </div>

                        <div class="flex items-center justify-between gap-3 rounded-xl bg-canvas p-3.5"
                             x-data="{ salin: false, async copy() { const t = @js(url('/'.$undangan->slug)); try { await navigator.clipboard.writeText(t); } catch (e) { const a = document.createElement('textarea'); a.value = t; document.body.appendChild(a); a.select(); try { document.execCommand('copy'); } catch (e2) {} a.remove(); } this.salin = true; setTimeout(() => this.salin = false, 1800); } }">
                            <div class="min-w-0">
                                <div class="text-[11px] leading-[14px] font-bold tracking-[0.08em] text-muted uppercase">Link saat ini</div>
                                <div class="mt-1 truncate text-sm leading-5 font-semibold text-ink">{{ $linkSaatIni }}</div>
                            </div>
                            <button type="button" x-on:click="copy()" class="btn btn-secondary btn-sm shrink-0">
                                <x-ikon nama="template-salin" :ukuran="14" /> <span x-text="salin ? 'Tersalin' : 'Salin'">Salin</span>
                            </button>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label for="lk-slug" class="label">Alamat baru</label>
                            <div class="field" @if ($galat) data-invalid @elseif ($tersedia) style="border-color:#12b76a" @endif>
                                <span class="text-sm font-medium text-muted">{{ $host }}/</span>
                                <input id="lk-slug" type="text" class="field-input" wire:model.live.debounce.500ms="slug" maxlength="{{ $maks }}" autocomplete="off" autocapitalize="none" spellcheck="false"
                                       @if ($galat) aria-invalid="true" @endif aria-describedby="lk-pesan">
                            </div>
                            <div id="lk-pesan" aria-live="polite" class="min-h-4">
                                @if ($galat)
                                    <div role="alert" class="flex items-center gap-1.5 text-xs leading-4 font-medium text-danger"><x-ikon nama="peringatan" :ukuran="14" /> {{ $galat }}</div>
                                @elseif ($tersedia)
                                    <div class="flex items-center gap-1.5 text-xs leading-4 font-medium text-success"><x-ikon nama="centang" :ukuran="14" /> Link tersedia</div>
                                @elseif ($sama)
                                    <div class="hint">Ini link yang sedang dipakai.</div>
                                @endif
                            </div>
                        </div>

                        <div class="rounded-xl border border-line p-3.5">
                            <div class="text-[11px] leading-[14px] font-bold tracking-[0.08em] text-muted uppercase">Pratinjau link</div>
                            <div class="mt-1 text-sm leading-5 font-semibold break-all {{ $tersedia ? 'text-brand' : 'text-body' }}">{{ $linkBaru }}</div>
                        </div>

                        @if (count($saran) > 0)
                            <div>
                                <div class="text-[13px] leading-[18px] font-semibold text-ink">Saran link yang tersedia</div>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    @foreach ($saran as $s)
                                        <button type="button" wire:click="pakaiSaran('{{ $s }}')" wire:key="saran-{{ $s }}" aria-label="Pakai saran {{ $s }}" class="btn btn-secondary btn-sm">{{ $s }}</button>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div class="flex flex-wrap justify-end gap-2 border-t border-line pt-4">
                            <button type="button" wire:click="kembalikan" class="btn btn-secondary" @disabled(! $adaPerubahan)>Kembalikan</button>
                            <button type="button" wire:click="bukaKonfirmasi" class="btn btn-primary" @disabled(! $tersedia)>Ganti link</button>
                        </div>
                    </section>
                @else
                    <section role="status" class="kartu flex flex-col items-center gap-1.5 p-8 text-center">
                        <span class="flex size-12 items-center justify-center rounded-full bg-warning-50 text-warning"><x-ikon nama="template-tautan" :ukuran="22" /></span>
                        <div class="mt-1 text-sm leading-5 font-semibold text-ink">Terbitkan dulu untuk mengganti link</div>
                        <div class="sub max-w-md">Link kustom hanya bisa diganti untuk undangan yang sudah terbit, karena yang dipindahkan adalah link yang sudah aktif. Sebelum terbit, kamu bisa memilih link langsung di dialog Terbitkan pada editor.</div>
                        <div class="mt-2 flex flex-wrap justify-center gap-2">
                            @if ($tautanEditor)
                                <a href="{{ $tautanEditor }}" wire:navigate class="btn btn-primary"><x-ikon nama="edit" :ukuran="16" /> Buka editor</a>
                            @endif
                            <a href="{{ $tautanBuat }}" wire:navigate class="btn btn-secondary"><x-ikon nama="tambah" :ukuran="16" /> Buat undangan</a>
                        </div>
                    </section>
                @endif

                <section aria-label="Domain kustom" class="kartu flex flex-wrap items-center gap-4 p-5">
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand"><x-ikon nama="template-globe" :ukuran="22" /></span>
                    <div class="min-w-0 flex-1 basis-60">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="h2">Domain kustom</h2>
                            <span class="rounded-md px-1.5 text-[11px] leading-4 font-semibold" style="{{ \App\Support\PratinjauTema::TIER['Deluxe'] }}">DELUXE</span>
                            <span class="tag">Segera hadir</span>
                        </div>
                        <div class="sub mt-0.5">Pakai alamat sendiri, misalnya namakalian.com. Pengaturan domain belum tersedia di versi desktop ini.</div>
                    </div>
                    @if ($tautanBilling)
                        <a href="{{ $tautanBilling }}" wire:navigate class="btn btn-secondary btn-sm">Lihat paket</a>
                    @endif
                </section>
            </div>

            <aside class="flex min-w-0 flex-col gap-5">
                <section aria-label="Yang terjadi saat link diganti" class="kartu flex flex-col gap-3 p-5">
                    <h2 class="h2">Saat link diganti</h2>
                    <ul class="m-0 flex list-none flex-col gap-2.5 p-0 text-[13px] leading-5 text-body">
                        @foreach ([
                            'Link lama dialihkan ke link baru selama '.\App\Services\PenahananSlug::HARI.' hari, dan tidak bisa dipakai orang lain selama itu. Setelahnya link lama tidak aktif, jadi kirim link baru ke tamu.',
                            'RSVP, ucapan, dan data kunjungan ikut pindah ke link baru.',
                            'Isi dan tema undangan tidak berubah.',
                            "Aturan: $min sampai $maks karakter, huruf kecil, angka, dan tanda hubung. Kata seperti admin, api, login, dan daftar dicadangkan sistem.",
                            'Kalau editor terbuka di tab lain, muat ulang setelah mengganti link.',
                        ] as $butir)
                            <li class="flex items-start gap-2"><x-ikon nama="template-info" :ukuran="15" class="mt-0.5 text-brand" /><span>{{ $butir }}</span></li>
                        @endforeach
                    </ul>
                    @if ($dampak)
                        <div class="grid grid-cols-2 gap-2.5 border-t border-line pt-3">
                            @foreach ([[$dampak['terkirim'], 'tamu sudah dikirimi link'], [$dampak['rsvp'], 'RSVP ikut pindah'], [$dampak['ucapan'], 'ucapan ikut pindah'], [$dampak['kunjungan'], 'kunjungan ikut pindah']] as [$n, $label])
                                <div class="rounded-xl bg-canvas p-3">
                                    <div class="text-lg leading-6 font-semibold text-ink">{{ $angka($n) }}</div>
                                    <div class="text-xs leading-4 text-muted">{{ $label }}</div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>

                <section aria-label="Riwayat perubahan link" class="kartu flex flex-col gap-3 p-5">
                    <h2 class="h2">Riwayat perubahan</h2>
                    @forelse ($riwayat as $r)
                        <div class="rounded-xl bg-canvas p-3" wire:key="riwayat-{{ $loop->index }}">
                            <div class="flex flex-wrap items-center gap-1.5 text-[13px] leading-5 font-semibold break-all text-ink">{{ $r['dari'] }} <x-ikon nama="template-panah" :ukuran="13" class="text-muted" /> {{ $r['ke'] }}</div>
                            <div class="hint">{{ $r['pada'] }}</div>
                        </div>
                    @empty
                        <div class="sub">Belum ada perubahan link untuk undangan ini.</div>
                    @endforelse
                </section>

                <section aria-label="Semua link undangan" class="kartu flex flex-col gap-3 p-5">
                    <h2 class="h2">Semua link undanganmu</h2>
                    <div class="flex flex-col gap-2">
                        @foreach ($daftar as $u)
                            <button type="button" wire:click="pilih({{ $u->id }})" wire:key="semua-{{ $u->id }}" @if ($u->id === $undangan->id) aria-current="true" @endif
                                    class="box-border flex min-h-14 w-full items-center gap-2.5 rounded-xl border-2 px-3 py-2 text-left {{ $u->id === $undangan->id ? 'border-brand bg-[#F2F6FF]' : 'border-line bg-white hover:bg-gray-50' }}">
                                <span class="flex min-w-0 flex-1 flex-col">
                                    <span class="truncate text-[13px] leading-[18px] font-semibold text-ink">{{ $u->judul }}</span>
                                    <span class="truncate text-xs leading-4 text-muted">{{ $host }}/{{ $u->slug }}</span>
                                </span>
                                <x-pill :warna="$u->sudahTerbit() ? 'hijau' : 'kuning'">{{ $u->sudahTerbit() ? 'Terbit' : 'Draf' }}</x-pill>
                            </button>
                        @endforeach
                    </div>
                </section>
            </aside>
        </div>

        <x-dialog wire:model="tampilKonfirmasi" judul="Ganti link undangan?" :sub="$undangan->judul" lebar="max-w-[520px]">
            <div class="rounded-xl bg-canvas p-3.5 text-sm leading-5">
                <div class="break-all text-muted line-through">{{ $linkSaatIni }}</div>
                <div class="mt-1 font-semibold break-all text-brand">{{ $linkBaru }}</div>
            </div>
            <div class="text-[13px] leading-5 text-body">
                @if (($dampak['terkirim'] ?? 0) > 0)
                    {{ $dampak['terkirim'] }} tamu sudah dikirimi link lama. Kirim ulang link baru ke mereka lewat halaman Bagikan. RSVP, ucapan, dan data kunjungan ikut pindah.
                @else
                    RSVP, ucapan, dan data kunjungan ikut pindah ke link baru.
                @endif
            </div>
            <label class="flex cursor-pointer items-start gap-2.5 text-[13px] leading-5 font-medium text-ink">
                <input type="checkbox" wire:model.live="paham" class="mt-0.5 size-4 shrink-0 accent-[#1D4ED8]">
                <span>Saya mengerti link lama tidak bisa dibuka lagi.</span>
            </label>
            @if ($galatKonfirmasi !== '')
                <div role="alert" class="flex items-center gap-1.5 rounded-xl bg-danger-50 px-3 py-2.5 text-[13px] leading-5 font-medium text-danger"><x-ikon nama="peringatan" :ukuran="16" /> {{ $galatKonfirmasi }}</div>
            @endif
            <x-slot:footer>
                <button type="button" wire:click="batalKonfirmasi" class="btn btn-secondary">Batal</button>
                <button type="button" wire:click="ganti" wire:loading.attr="disabled" wire:target="ganti" class="btn btn-primary" @disabled(! $paham)>Ya, ganti link</button>
            </x-slot:footer>
        </x-dialog>
    @endif
</div>
