<div class="mx-auto grid max-w-5xl gap-5 grid-cols-[minmax(0,1fr)] lg:grid-cols-[minmax(0,1fr)_340px] lg:items-start"
     x-data="{
        get kotor() {
            const a = $wire.asli || {};
            return $wire.nama !== a.nama || $wire.kota !== a.kota || $wire.bahasa !== a.bahasa;
        },
     }">

    <div class="flex min-w-0 flex-col gap-5">
        @if ($catatan !== '')
            <div role="status" class="flex items-start gap-2 rounded-xl bg-success-50 px-4 py-3 text-sm leading-5 font-medium text-success">
                <x-ikon nama="centang" :ukuran="18" class="mt-0.5" />
                <span class="flex-1">{{ $catatan }}</span>
                <button type="button" wire:click="$set('catatan', '')" aria-label="Tutup pesan" class="text-success/70 hover:text-success"><x-ikon nama="tutup" :ukuran="16" /></button>
            </div>
        @endif

        {{-- Data diri --}}
        <section aria-label="Data diri" class="kartu flex flex-col gap-4 p-5 sm:p-6">
            <h2 class="h2">Data diri</h2>

            <div>
                <label for="ia-nama" class="lbl">Nama lengkap</label>
                <input id="ia-nama" type="text" wire:model="nama" class="inp" maxlength="100" autocomplete="name" placeholder="Nama sesuai identitas" @error('nama') aria-invalid="true" @enderror>
                @error('nama')<div role="alert" class="galat mt-1.5">{{ $message }}</div>@enderror
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="ia-kota" class="lbl">Kota <span class="ml-1 font-normal text-subtle">Opsional</span></label>
                    <input id="ia-kota" type="text" wire:model="kota" class="inp" maxlength="100" autocomplete="address-level2" placeholder="Contoh: Jakarta" @error('kota') aria-invalid="true" @enderror>
                    @error('kota')<div role="alert" class="galat mt-1.5">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label for="ia-bahasa" class="lbl">Bahasa aplikasi</label>
                    <select id="ia-bahasa" wire:model="bahasa" class="inp">
                        <option value="id">Indonesia</option>
                        <option value="en" disabled>English (segera hadir)</option>
                    </select>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3">
                <span x-show="kotor" x-cloak class="inline-flex items-center gap-1.5 text-xs font-medium text-warning"><span class="size-1.5 rounded-full bg-warning"></span> Belum disimpan</span>
                <button type="button" class="btn btn-primary" wire:click="simpanData" x-bind:disabled="!kotor" wire:loading.attr="disabled" wire:target="simpanData">Simpan</button>
            </div>
        </section>

        {{-- Email --}}
        <section aria-label="Email" class="kartu flex flex-col gap-4 p-5 sm:p-6">
            <h2 class="h2">Email</h2>

            <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-line bg-surface px-4 py-3">
                <div class="min-w-0 truncate text-sm leading-5 font-medium text-ink">{{ $user->email }}</div>
                @if ($emailTerverifikasi)
                    <span class="pill pill-hijau"><x-ikon nama="akun-terverifikasi" :ukuran="14" /> Terverifikasi</span>
                @else
                    <span class="pill pill-kuning">Belum diverifikasi</span>
                @endif
            </div>

            @if ($user->email_baru)
                <div role="status" class="flex flex-col gap-3 rounded-xl bg-warning-50 px-4 py-3 text-[13px] leading-5 text-warning">
                    <div>Menunggu konfirmasi untuk <b class="break-all">{{ $user->email_baru }}</b>. Email berubah setelah tautan di dalamnya dibuka.</div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="btn btn-secondary btn-sm" wire:click="kirimUlangTautan" wire:loading.attr="disabled" wire:target="kirimUlangTautan">Kirim ulang tautan</button>
                        <button type="button" class="btn btn-secondary btn-sm" wire:click="batalkanEmail">Batalkan</button>
                    </div>
                </div>
            @else
                <form wire:submit="kirimTautanEmail" class="flex flex-col gap-3" novalidate>
                    <div>
                        <label for="ia-email" class="lbl">Ubah email</label>
                        <input id="ia-email" type="email" wire:model="emailBaru" class="inp" autocomplete="email" placeholder="Email baru" @error('emailBaru') aria-invalid="true" @enderror>
                        <div class="hint mt-1.5">Kami mengirim tautan konfirmasi ke email baru sebelum perubahan berlaku.</div>
                        @error('emailBaru')<div role="alert" class="galat mt-1.5">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <button type="submit" class="btn btn-secondary" wire:loading.attr="disabled" wire:target="kirimTautanEmail">
                            <span wire:loading.remove wire:target="kirimTautanEmail">Kirim tautan konfirmasi</span>
                            <span wire:loading wire:target="kirimTautanEmail">Mengirim...</span>
                        </button>
                    </div>
                </form>
            @endif
        </section>

        {{-- Nomor WhatsApp --}}
        <section aria-label="Nomor WhatsApp" class="kartu flex flex-col gap-3 p-5 sm:p-6">
            <h2 class="h2">Nomor WhatsApp</h2>
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-line bg-surface px-4 py-3">
                <div class="text-sm leading-5 font-medium text-ink">{{ $telepon ?? 'Belum diisi' }}</div>
                <div class="flex items-center gap-2">
                    @if ($teleponTerverifikasi)
                        <span class="pill pill-hijau"><x-ikon nama="akun-terverifikasi" :ukuran="14" /> Terverifikasi</span>
                    @else
                        <span class="pill pill-kuning">Belum diverifikasi</span>
                    @endif
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="bukaWa">{{ $telepon ? 'Ubah' : 'Tambah' }}</button>
                </div>
            </div>
            <p class="hint m-0">Verifikasi nomor dengan kode OTP belum aktif. Nomor yang diubah disimpan dan ditandai Belum diverifikasi.</p>
        </section>
    </div>

    <aside class="flex min-w-0 flex-col gap-5">
        <section aria-label="Sesi dan perangkat" class="kartu flex flex-col gap-4 p-5">
            <h2 class="h2">Sesi dan perangkat</h2>
            <div class="flex items-start gap-3">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand"><x-ikon nama="akun-layar" :ukuran="20" /></span>
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-sm leading-5 font-semibold text-ink">{{ $sesi['nama'] }}</span>
                        <span class="tag">Perangkat ini</span>
                    </div>
                    <div class="text-[13px] leading-[18px] text-muted">Aktif sekarang{{ $sesi['ip'] !== '' ? ' · '.$sesi['ip'] : '' }}</div>
                </div>
            </div>
            <p class="hint m-0">
                @if ($sesi['lain'] > 0)
                    {{ $sesi['lain'] }} perangkat lain juga sedang masuk. Semua sesi lain berakhir saat kata sandi diubah.
                @else
                    Tidak ada perangkat lain yang sedang masuk.
                @endif
            </p>
            <form method="POST" action="{{ route('keluar') }}">
                @csrf
                <button type="submit" class="btn btn-secondary w-full"><x-ikon nama="keluar" :ukuran="16" /> Keluar dari perangkat ini</button>
            </form>
        </section>

        <section aria-label="Keamanan" class="kartu flex flex-col gap-3 p-5">
            <h2 class="h2">Keamanan</h2>
            <a href="{{ route('akun.sandi') }}" wire:navigate class="flex items-center gap-3 rounded-xl border border-line px-3 py-3 transition-colors hover:bg-gray-50">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-[10px] bg-brand-50 text-brand"><x-ikon nama="gembok" :ukuran="18" /></span>
                <span class="flex min-w-0 flex-1 flex-col">
                    <span class="text-sm leading-5 font-semibold text-ink">Ubah Kata Sandi</span>
                    <span class="truncate text-[13px] leading-[18px] text-muted">{{ $infoSandi }}</span>
                </span>
                <x-ikon nama="panah-kanan" :ukuran="18" class="text-subtle" />
            </a>
        </section>

        <section aria-label="Hapus akun" class="kartu flex flex-col gap-3 p-5">
            <div>
                <h2 class="h2">Hapus akun</h2>
                <p class="sub m-0 mt-1">Menghapus akun akan menghapus profil, undangan, daftar tamu, dan data RSVP. Tindakan ini tidak bisa dibatalkan.</p>
            </div>
            @if ($user->isAdmin())
                <p class="hint m-0">Akun admin tidak bisa dihapus dari halaman ini.</p>
            @else
                <div><button type="button" class="btn btn-danger" wire:click="bukaHapus"><x-ikon nama="akun-hapus" :ukuran="16" /> Hapus Akun</button></div>
            @endif
        </section>
    </aside>

    {{-- Ubah nomor WhatsApp --}}
    <x-dialog wire:model="tampilWa" judul="Ubah Nomor WhatsApp" sub="Verifikasi OTP nomor belum aktif; nomor baru ditandai Belum diverifikasi." lebar="max-w-[460px]">
        <form wire:submit="simpanWa" id="form-wa" novalidate>
            <x-field id="wa-baru" label="Nomor WhatsApp baru" ikon="telepon" awalan="+62" type="tel" wire:model="waBaru" autocomplete="tel-national" inputmode="tel" placeholder="812 3456 7890" :galat="$errors->first('waBaru')" />
        </form>
        <x-slot:footer>
            <button type="button" class="btn btn-secondary" x-on:click="$wire.tampilWa = false">Batal</button>
            <button type="submit" form="form-wa" class="btn btn-primary" wire:loading.attr="disabled" wire:target="simpanWa">Simpan Nomor</button>
        </x-slot:footer>
    </x-dialog>

    {{-- Hapus akun --}}
    <x-dialog wire:model="tampilHapus" judul="Hapus akun ini?" sub="Semua undangan, daftar tamu, dan data RSVP ikut terhapus dan tidak bisa dikembalikan." lebar="max-w-[480px]">
        <form wire:submit="hapusAkun" id="form-hapus" class="flex flex-col gap-3.5" novalidate>
            <x-field id="del-teks" label="Ketik HAPUS untuk melanjutkan" wire:model="teksHapus" placeholder="HAPUS" autocomplete="off" :galat="$errors->first('teksHapus')" />
            <x-field id="del-sandi" label="Kata sandi" ikon="gembok" :sandi="true" wire:model="sandiHapus" autocomplete="current-password" placeholder="Kata sandi akunmu" :galat="$errors->first('sandiHapus')" />
        </form>
        <x-slot:footer>
            <button type="button" class="btn btn-secondary" x-on:click="$wire.tampilHapus = false">Batal</button>
            <button type="submit" form="form-hapus" class="btn btn-danger-solid" wire:loading.attr="disabled" wire:target="hapusAkun">Ya, Hapus Akun</button>
        </x-slot:footer>
    </x-dialog>
</div>
