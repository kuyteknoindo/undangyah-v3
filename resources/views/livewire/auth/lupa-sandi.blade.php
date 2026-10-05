<div class="flex flex-col gap-6">
    @switch($langkah)
        @case(1)
            <x-auth-kepala judul="Lupa kata sandi?" sub="Masukkan email akunmu. Kami kirim kode untuk mengatur ulang kata sandi." />
            <form wire:submit="kirimKode" class="flex flex-col gap-4" novalidate>
                <x-field id="ls-email" label="Email" ikon="surel" type="email" wire:model="email" autocomplete="email" placeholder="nama@email.com" :galat="$errors->first('email')" />
                <p class="m-0 text-xs leading-5 text-muted">Demi keamanan, kami tidak memberi tahu apakah email sudah terdaftar. Jika terdaftar, kode 6 digit akan dikirim.</p>
                <button type="submit" class="btn btn-primary btn-lg" wire:loading.attr="disabled" wire:target="kirimKode">
                    <span wire:loading.remove wire:target="kirimKode">Kirim Kode</span>
                    <span wire:loading wire:target="kirimKode">Mengirim kode...</span>
                </button>
            </form>
            <p class="m-0 text-center text-sm leading-5 text-muted">Ingat kata sandi? <a href="{{ route('masuk') }}" wire:navigate class="font-semibold text-brand hover:underline">Masuk</a></p>
        @break

        @case(2)
            <x-auth-kepala judul="Masukkan kode" :sub="'Jika '.$tujuan.' terdaftar, kode 6 digit sudah dikirim ke sana.'" />
            <form wire:submit="verifikasi" class="flex flex-col gap-4" novalidate>
                @if ($info)
                    <div role="status" class="flex items-center gap-2 rounded-[10px] bg-success-50 px-3 py-2.5 text-[13px] leading-[18px] font-medium text-success"><x-ikon nama="centang" :ukuran="16" /> {{ $info }}</div>
                @endif
                <x-kode-otp aksi="verifikasi" :galat="$errors->first('kode')" />
                <button type="submit" class="btn btn-primary btn-lg" wire:loading.attr="disabled" wire:target="verifikasi">
                    <span wire:loading.remove wire:target="verifikasi">Verifikasi</span>
                    <span wire:loading wire:target="verifikasi">Memeriksa kode...</span>
                </button>
                <x-kirim-ulang :detik="$jeda" />
            </form>
            <div class="text-center"><button type="button" wire:click="ubahEmail" class="text-[13px] leading-5 font-semibold text-brand hover:underline">Ubah email</button></div>
        @break

        @case(3)
            <x-auth-kepala judul="Buat kata sandi baru" sub="Kode terverifikasi. Buat kata sandi baru untuk akunmu." />
            <form wire:submit="simpanSandi" class="flex flex-col gap-4" novalidate>
                <div class="flex flex-col gap-2" x-data="{ p: '' }">
                    <x-field id="ls-sandi" label="Kata sandi baru" ikon="gembok" :sandi="true" wire:model="sandi" x-model="p" autocomplete="new-password" placeholder="Minimal 8 karakter, huruf dan angka" :galat="$errors->first('sandi')" />
                    <x-kekuatan-sandi />
                </div>
                <x-field id="ls-sandi2" label="Ulangi kata sandi" ikon="gembok" :sandi="true" wire:model="sandiKonfirmasi" autocomplete="new-password" placeholder="Ulangi kata sandi baru" :galat="$errors->first('sandiKonfirmasi')" />
                <button type="submit" class="btn btn-primary btn-lg" wire:loading.attr="disabled" wire:target="simpanSandi">
                    <span wire:loading.remove wire:target="simpanSandi">Simpan Kata Sandi</span>
                    <span wire:loading wire:target="simpanSandi">Menyimpan...</span>
                </button>
            </form>
        @break

        @case(4)
            <div class="flex flex-col items-center gap-3 text-center">
                <span class="flex size-14 items-center justify-center rounded-full bg-success-50 text-success"><x-ikon nama="centang" :ukuran="28" /></span>
                <h1 class="m-0 text-2xl leading-8 font-semibold tracking-[-0.02em] text-ink">Kata sandi diperbarui</h1>
                <p class="m-0 text-sm leading-5 text-muted">Kamu sudah bisa masuk dengan kata sandi baru.</p>
            </div>
            <a href="{{ route('masuk') }}" wire:navigate class="btn btn-primary btn-lg">Masuk Sekarang</a>
        @break
    @endswitch
</div>
