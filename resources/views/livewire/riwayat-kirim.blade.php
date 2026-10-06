<div wire:init="muat" class="mx-auto flex max-w-6xl flex-col gap-5">
    @unless ($siap)
        <div role="status" aria-busy="true" class="flex flex-col gap-5">
            <span class="sr-only">Memuat halaman…</span>
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                @for ($i = 0; $i < 4; $i++)<div class="kartu kpi"><span class="skel h-3 w-24"></span><span class="skel h-7 w-12"></span></div>@endfor
            </div>
            <x-skeleton mode="kartu" :jumlah="5" />
        </div>
    @elseif (! $undangan)
        <section class="kartu p-6 sm:p-8">
            <x-kosong judul="Belum ada undangan" teks="Riwayat pengiriman undangan lewat WhatsApp akan tercatat di sini." ikon="bagikan-riwayat">
                @if ($tautanBuat)<a href="{{ $tautanBuat }}" wire:navigate class="btn btn-primary"><x-ikon nama="tambah" :ukuran="16" /> Buat undangan</a>@endif
            </x-kosong>
        </section>
    @else
        <section aria-label="Pilih undangan" class="kartu flex flex-wrap items-end justify-between gap-4 p-4 sm:p-5">
            <div class="flex w-full max-w-md flex-col gap-1.5">
                <label for="rk-inv" class="label">Undangan</label>
                <select id="rk-inv" class="inp" wire:change="pilih($event.target.value)">
                    @foreach ($daftar as $u)
                        <option value="{{ $u->id }}" @selected($u->id === $undangan->id)>{{ $u->judul }} ({{ $u->sudahTerbit() ? 'Terbit' : 'Draf' }})</option>
                    @endforeach
                </select>
            </div>
            <x-pill :warna="$terbit ? 'hijau' : 'kuning'">{{ $terbit ? 'Terbit' : 'Draf' }}</x-pill>
        </section>

        <section aria-label="Ringkasan" class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div class="kartu kpi"><div class="kpi-v">{{ $ringkasan['tercatat'] }}</div><div class="kpi-l">Pesan tercatat</div></div>
            <div class="kartu kpi"><div class="kpi-v">{{ $ringkasan['sudah'] }}</div><div class="kpi-l">Tamu sudah dikirimi</div></div>
            <div class="kartu kpi"><div class="kpi-v">{{ $ringkasan['belum'] }}</div><div class="kpi-l">Tamu belum dikirimi</div></div>
            <div class="kartu kpi"><div class="kpi-v {{ $ringkasan['tanpaNomor'] ? 'text-[#b54708]' : '' }}">{{ $ringkasan['tanpaNomor'] }}</div><div class="kpi-l">Tamu tanpa nomor WhatsApp</div></div>
        </section>

        <section aria-label="Riwayat pengiriman" class="kartu overflow-hidden">
            <div class="flex flex-col gap-3 border-b border-line p-4 sm:p-5">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="h2">Riwayat pengiriman</h2>
                    <button type="button" wire:click="ekspor" wire:loading.attr="disabled" wire:target="ekspor" class="btn btn-secondary btn-sm" @disabled($total === 0)><x-ikon nama="bagikan-unduh" :ukuran="14" /> Ekspor CSV ({{ $total }})</button>
                </div>
                <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_180px_180px]">
                    <div class="relative">
                        <label for="rk-cari" class="sr-only">Cari nama tamu</label>
                        <span class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-muted"><x-ikon nama="bagikan-cari" :ukuran="16" /></span>
                        <input id="rk-cari" type="search" class="inp pl-9" maxlength="80" placeholder="Cari nama tamu..." wire:model.live.debounce.350ms="q" autocomplete="off">
                    </div>
                    <div>
                        <label for="rk-kanal" class="sr-only">Filter kanal</label>
                        <select id="rk-kanal" class="inp" wire:model.live="kanal">
                            <option value="semua">Semua kanal</option>
                            <option value="whatsapp">WhatsApp</option>
                            <option value="email">Email</option>
                        </select>
                    </div>
                    <div>
                        <label for="rk-tgl" class="sr-only">Filter tanggal</label>
                        <select id="rk-tgl" class="inp" wire:model.live="tgl">
                            <option value="semua">Semua tanggal</option>
                            <option value="hari">Hari ini</option>
                            <option value="7">7 hari terakhir</option>
                            <option value="30">30 hari terakhir</option>
                        </select>
                    </div>
                </div>
                <div class="seg self-start" role="group" aria-label="Filter status">
                    <button type="button" wire:click="aturStatus('semua')" aria-pressed="{{ $status === 'semua' ? 'true' : 'false' }}">Semua <span class="text-muted">{{ $hitungStatus['semua'] }}</span></button>
                    <button type="button" wire:click="aturStatus('dibuka')" aria-pressed="{{ $status === 'dibuka' ? 'true' : 'false' }}">Dibuka di WhatsApp <span class="text-muted">{{ $hitungStatus['dibuka'] }}</span></button>
                    <button type="button" wire:click="aturStatus('belum')" aria-pressed="{{ $status === 'belum' ? 'true' : 'false' }}">Belum dikirim <span class="text-muted">{{ $hitungStatus['belum'] }}</span></button>
                </div>
            </div>

            @if ($kosongSemua)
                <x-kosong judul="Belum ada pesan terkirim" teks="Riwayat muncul di sini setelah kamu mengirim undangan ke tamu. Tambahkan tamu lebih dulu di Bagikan Undangan." ikon="bagikan-riwayat">
                    @if ($tautanBagikan)<a href="{{ $tautanBagikan }}" wire:navigate class="btn btn-primary btn-sm">Kirim undangan</a>@endif
                </x-kosong>
            @elseif ($total === 0)
                <x-kosong judul="Riwayat tidak ditemukan" teks="Tidak ada pengiriman yang cocok dengan pencarian atau filter ini." ikon="bagikan-cari">
                    <button type="button" wire:click="resetFilter" class="btn btn-secondary btn-sm">Reset filter</button>
                </x-kosong>
            @else
                <div class="overflow-x-auto">
                    <table class="tabel">
                        <caption class="sr-only">Riwayat pengiriman pesan ke tamu</caption>
                        <thead><tr><th>Penerima</th><th>Jenis</th><th>Kanal</th><th>Waktu</th><th>Status</th><th>Aksi</th></tr></thead>
                        <tbody>
                            @foreach ($baris as $b)
                                <tr wire:key="rk-{{ $b['id'] }}">
                                    <td>
                                        <div class="flex items-center gap-2.5">
                                            <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-bold text-brand" aria-hidden="true">{{ mb_strtoupper(mb_substr(collect(preg_split('/\s+/u', trim($b['nama'])))->take(2)->map(fn ($k) => mb_substr($k, 0, 1))->implode(''), 0, 2)) }}</span>
                                            <span class="flex min-w-0 flex-col">
                                                <span class="truncate font-semibold text-ink">{{ $b['nama'] }}</span>
                                                <span class="truncate text-xs text-muted">{{ $b['hp'] ? '+'.$b['hp'] : ($b['tamu'] ? 'Belum ada nomor' : 'Tamu sudah dihapus') }}</span>
                                            </span>
                                        </div>
                                    </td>
                                    <td>{{ $b['jenis'] === 'pengingat' ? 'Pengingat RSVP' : 'Undangan' }}</td>
                                    <td>{{ $b['kanal'] === 'email' ? 'Email' : 'WhatsApp' }}</td>
                                    <td title="{{ $b['waktu'] ? $b['waktu']->timezone(config('app.timezone'))->locale('id')->translatedFormat('j F Y, H:i') : 'Belum pernah dikirim' }}">{{ $b['waktu'] ? $b['waktu']->locale('id')->diffForHumans() : '–' }}</td>
                                    <td><x-pill :warna="$b['status'] === 'dibuka' ? 'hijau' : 'abu'">{{ $b['status'] === 'dibuka' ? 'Dibuka di WhatsApp' : 'Belum dikirim' }}</x-pill></td>
                                    <td>
                                        @if ($b['bisaWa'])
                                            <a href="{{ $b['urlWa'] }}" target="_blank" rel="noopener noreferrer" wire:click="kirim({{ $b['tamu_id'] }}, '{{ $b['jenis'] }}')" aria-label="{{ $b['status'] === 'belum' ? 'Kirim' : 'Kirim ulang' }} ke {{ $b['nama'] }} lewat WhatsApp" class="btn btn-secondary btn-sm"><x-ikon nama="bagikan-wa" :ukuran="14" />{{ $b['status'] === 'belum' ? 'Kirim' : 'Kirim ulang' }}</a>
                                        @else
                                            <span class="text-xs text-muted">{{ ! $terbit ? 'Terbitkan dulu' : ($b['kanal'] === 'email' ? 'Tanpa alamat email' : '–') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-2 border-t border-line px-4 py-3 text-[13px] text-muted sm:px-5">
                    <span>Menampilkan {{ $dari }}–{{ $sampai }} dari {{ $total }} catatan</span>
                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="sebelumnya" @disabled($hal <= 1) aria-label="Halaman sebelumnya" class="btn btn-secondary btn-sm"><x-ikon nama="bagikan-panah-kiri" :ukuran="14" /> Sebelumnya</button>
                        <span>{{ $hal }} / {{ $halaman }}</span>
                        <button type="button" wire:click="berikutnya" @disabled($hal >= $halaman) aria-label="Halaman berikutnya" class="btn btn-secondary btn-sm">Berikutnya <x-ikon nama="bagikan-panah-kanan" :ukuran="14" /></button>
                    </div>
                </div>
            @endif
        </section>

        <div class="flex items-start gap-2.5 rounded-2xl bg-canvas px-4 py-3 text-[13px] leading-5 text-muted">
            <x-ikon nama="bagikan-info" :ukuran="16" class="mt-0.5" />
            <span>Status "Dibuka di WhatsApp" berarti kamu sudah menekan tombol kirim dan WhatsApp terbuka untuk tamu tersebut. undangyah.com tidak dapat memastikan pesan benar-benar terkirim, sampai, atau dibaca.</span>
        </div>
    @endif
</div>
