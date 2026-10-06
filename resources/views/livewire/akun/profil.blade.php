<div class="mx-auto grid max-w-5xl gap-5 grid-cols-[minmax(0,1fr)] lg:grid-cols-[minmax(0,360px)_minmax(0,1fr)] lg:items-start"
     x-data="{
        tersedia: false, terpasang: false, tawaran: null,
        init() {
            const mandiri = (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) || window.navigator.standalone;
            if (mandiri) this.terpasang = true;
        },
        simpanTawaran(e) { e.preventDefault(); this.tawaran = e; this.tersedia = true; },
        selesaiPasang() { this.tawaran = null; this.terpasang = true; $wire.panduanPasang = false; },
        pasang() {
            if (!this.tawaran) { $wire.panduanPasang = true; return; }
            try {
                this.tawaran.prompt();
                Promise.resolve(this.tawaran.userChoice).then((r) => { this.tawaran = null; this.tersedia = false; this.terpasang = !!(r && r.outcome === 'accepted'); });
            } catch (e) { $wire.panduanPasang = true; }
        },
     }"
     x-on:beforeinstallprompt.window="simpanTawaran($event)" x-on:appinstalled.window="selesaiPasang()">

    <div class="flex flex-col gap-5">
        {{-- Kartu profil --}}
        <section aria-label="Profil pengguna" class="kartu flex flex-col gap-4 p-5">
            <div class="flex items-start gap-4">
                <a href="{{ route('akun.ubah') }}" wire:navigate aria-label="Ganti foto profil" class="group relative shrink-0">
                    <x-avatar :user="$user" kelas="size-[72px] text-2xl" />
                    <span class="absolute -right-1 -bottom-1 flex size-7 items-center justify-center rounded-full border-2 border-white bg-gray-900 text-white group-hover:bg-brand"><x-ikon nama="akun-kamera" :ukuran="14" /></span>
                </a>
                <div class="min-w-0 flex-1 pt-1">
                    <div class="truncate text-base leading-6 font-semibold text-ink">{{ $user->name }}</div>
                    <div class="truncate text-[13px] leading-[18px] text-muted">{{ $user->email }}</div>
                    @if (filled($user->bio))
                        <p class="m-0 mt-2 text-[13px] leading-5 break-words text-body">{{ $user->bio }}</p>
                    @endif
                    @if ($bergabung)
                        <div class="mt-2 text-xs leading-4 text-subtle">Bergabung {{ $bergabung }}</div>
                    @endif
                </div>
            </div>

            <dl class="m-0 grid grid-cols-3 divide-x divide-line rounded-xl border border-line bg-surface py-3 text-center">
                @foreach ([['Undangan', $statistik['undangan']], ['Tamu', $statistik['tamu']], ['Hadir', $statistik['hadir']]] as [$nama, $angka])
                    <div class="flex flex-col gap-0.5 px-2">
                        <dd class="m-0 text-xl leading-7 font-semibold tracking-tight text-ink tabular-nums">{{ number_format($angka, 0, ',', '.') }}</dd>
                        <dt class="text-xs leading-4 text-muted">{{ $nama }}</dt>
                    </div>
                @endforeach
            </dl>

            <a href="{{ route('akun.ubah') }}" wire:navigate class="btn btn-secondary"><x-ikon nama="edit" :ukuran="16" /> Edit Profil</a>
        </section>

        {{-- Kartu paket --}}
        <section aria-label="Paket langganan" class="kartu flex flex-col gap-4 p-5">
            <div class="flex items-center gap-3">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand"><x-ikon nama="bintang" :ukuran="20" /></span>
                <div class="min-w-0 flex-1">
                    <div class="text-sm leading-5 font-semibold text-ink">Paket {{ $paket['nama'] }}</div>
                    <div class="text-[13px] leading-[18px] text-muted">{{ $subPaket }}</div>
                </div>
                <x-pill :warna="$paket['kode'] === 'gratis' ? 'abu' : ($paket['berakhir'] ? 'merah' : 'hijau')">{{ $paket['kode'] === 'gratis' ? 'Gratis' : ($paket['berakhir'] ? 'Berakhir' : 'Aktif') }}</x-pill>
            </div>
            @if (Route::has('billing'))
                <a href="{{ route('billing') }}" wire:navigate class="btn btn-primary">{{ $paket['aktif'] ? 'Kelola Paket' : 'Upgrade Paket' }}</a>
            @endif
        </section>

        {{-- Pasang aplikasi --}}
        <section aria-label="Pasang aplikasi" class="kartu flex flex-col gap-4 p-5">
            <div class="flex items-start gap-3">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand"><x-ikon nama="akun-ponsel" :ukuran="20" /></span>
                <div class="min-w-0 flex-1">
                    <div class="text-sm leading-5 font-semibold text-ink">Pasang undangyah</div>
                    <div class="text-[13px] leading-5 text-muted">Buka dari layar utama atau jendela sendiri, tanpa unduh dari toko aplikasi.</div>
                </div>
            </div>
            <button type="button" x-show="!terpasang" x-on:click="pasang()" class="btn btn-secondary"><x-ikon nama="akun-unduh" :ukuran="16" /> Cara memasang</button>
            <div x-show="terpasang" x-cloak class="flex items-center gap-2 text-[13px] leading-[18px] font-medium text-success"><x-ikon nama="centang" :ukuran="16" /> Aplikasi sudah terpasang</div>
        </section>
    </div>

    <div class="flex flex-col gap-5">
        @foreach ($kelompok as $grup)
            <section aria-label="{{ $grup['judul'] }}">
                <h2 class="m-0 mb-2 px-1 text-xs leading-4 font-semibold tracking-[0.04em] text-muted uppercase">{{ $grup['judul'] }}</h2>
                <div class="kartu divide-y divide-line overflow-hidden">
                    @foreach ($grup['item'] as $m)
                        @php($kelas = 'flex min-h-[60px] items-center gap-3 px-4 py-3')
                        @if ($m['href'])
                            <a href="{{ $m['href'] }}" wire:navigate class="{{ $kelas }} transition-colors hover:bg-gray-50">
                        @else
                            <div class="{{ $kelas }}" aria-disabled="true">
                        @endif
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-[10px] bg-brand-50 text-brand"><x-ikon :nama="$m['ikon']" :ukuran="18" /></span>
                            <span class="flex min-w-0 flex-1 flex-col">
                                <span class="truncate text-sm leading-5 font-semibold text-ink">{{ $m['label'] }}</span>
                                @if (! empty($m['sub']))
                                    <span class="truncate text-[13px] leading-[18px] text-muted">{{ $m['sub'] }}</span>
                                @endif
                            </span>
                            @if (! empty($m['lencana']))
                                <span class="min-w-5 rounded-full bg-danger px-1.5 text-center text-[11px] leading-5 font-bold text-white">{{ $m['lencana'] > 99 ? '99+' : $m['lencana'] }}</span>
                            @endif
                            @if ($m['href'])
                                <x-ikon nama="panah-kanan" :ukuran="18" class="text-subtle" />
                            @else
                                <span class="tag">Segera</span>
                            @endif
                        @if ($m['href'])
                            </a>
                        @else
                            </div>
                        @endif
                    @endforeach
                </div>
            </section>
        @endforeach

        <section aria-label="Keluar">
            <h2 class="m-0 mb-2 px-1 text-xs leading-4 font-semibold tracking-[0.04em] text-muted uppercase">Sesi</h2>
            <div class="kartu flex flex-col gap-3 p-4">
                <p class="m-0 text-[13px] leading-5 text-muted">Keluar dari akun ini di perangkat ini. Data undangan tetap tersimpan.</p>
                <button type="button" class="btn btn-danger" x-on:click="$wire.konfirmasiKeluar = true"><x-ikon nama="keluar" :ukuran="16" /> Keluar</button>
            </div>
            <div class="mt-3 text-center text-xs leading-4 text-subtle">undangyah.com versi desktop</div>
        </section>
    </div>

    {{-- Dialog konfirmasi keluar --}}
    <x-dialog wire:model="konfirmasiKeluar" judul="Keluar dari akun ini?" sub="Kamu perlu masuk lagi untuk mengelola undangan." lebar="max-w-[440px]">
        <x-slot:footer>
            <button type="button" class="btn btn-secondary" x-on:click="$wire.konfirmasiKeluar = false">Batal</button>
            <form method="POST" action="{{ route('keluar') }}">
                @csrf
                <button type="submit" class="btn btn-danger-solid">Ya, Keluar</button>
            </form>
        </x-slot:footer>
    </x-dialog>

    {{-- Panduan pasang aplikasi --}}
    <x-dialog wire:model="panduanPasang" judul="Cara memasang undangyah" sub="Pilih sesuai perangkatmu." lebar="max-w-[520px]">
        @foreach ([
            ['Komputer (Chrome atau Edge)', [['Cari ikon instal', 'Ada di ujung kanan bilah alamat.'], ['Klik Instal', 'undangyah terbuka di jendela sendiri.']]],
            ['Android (Chrome)', [['Buka menu browser', 'Ketuk ikon titik tiga di pojok kanan atas.'], ['Pilih Instal aplikasi', 'Atau Tambahkan ke layar utama, lalu konfirmasi.']]],
            ['iPhone (Safari)', [['Ketuk tombol Bagikan', 'Ikon kotak dengan panah ke atas di bilah bawah.'], ['Pilih Tambah ke Layar Utama', 'Lalu ketuk Tambah di pojok kanan atas.']]],
        ] as [$perangkat, $langkah])
            <div class="flex flex-col gap-2.5">
                <div class="text-[11px] leading-4 font-semibold tracking-[0.06em] text-muted uppercase">{{ $perangkat }}</div>
                @foreach ($langkah as $i => [$judul, $isi])
                    <div class="flex items-start gap-3">
                        <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-bold text-brand">{{ $i + 1 }}</span>
                        <div>
                            <div class="text-sm leading-5 font-semibold text-ink">{{ $judul }}</div>
                            <div class="text-[13px] leading-[18px] text-muted">{{ $isi }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endforeach
        <p class="m-0 rounded-[10px] bg-surface px-3 py-2.5 text-xs leading-[18px] text-muted">Browser ini belum menawarkan tombol instal otomatis, jadi ikuti langkah di atas.</p>
        <x-slot:footer>
            <button type="button" class="btn btn-primary" x-on:click="$wire.panduanPasang = false">Mengerti</button>
        </x-slot:footer>
    </x-dialog>
</div>
