<div class="contents">
    <x-auth-kepala judul="Selamat datang kembali" sub="Masuk untuk mengelola undangan, tamu, dan RSVP kamu." />

    <div class="au-form">
        @if ($info)
            <div role="status" aria-live="polite" class="au-kabar">
                <span>{{ $info }}</span>
                <button type="button" wire:click="$set('info', '')" aria-label="Tutup">&times;</button>
            </div>
        @endif

        @error('form')
            <div role="alert" class="au-kabar au-kabar-galat"><span>{{ $message }}</span></div>
        @enderror

        <form wire:submit="masuk" class="au-form" novalidate>
            <x-au.field id="l-email" label="Email" ikon="surel" type="email" wire:model.blur="email" autocomplete="email" placeholder="nama@email.com" :galat="$errors->first('email')" />
            <x-au.field id="l-sandi" label="Kata sandi" ikon="gembok" :sandi="true" wire:model.blur="sandi" autocomplete="current-password" placeholder="Masukkan kata sandi" :galat="$errors->first('sandi')" />

            <div class="flex items-center justify-between gap-2">
                <label class="au-cek">
                    <input type="checkbox" wire:model="ingat"> Ingat saya
                </label>
                <a href="{{ route('lupa-sandi') }}" wire:navigate class="au-lupa">Lupa kata sandi?</a>
            </div>

            <button type="submit" class="au-tombol au-utama-tombol" wire:loading.attr="disabled" wire:target="masuk">
                <span wire:loading.remove wire:target="masuk">Masuk</span>
                <span wire:loading wire:target="masuk">Memeriksa akun...</span>
            </button>
        </form>

        <div class="au-pembatas" aria-hidden="true">
            <span></span><span class="au-pembatas-teks">atau masuk dengan</span><span></span>
        </div>
        <div class="au-sosial">
            <button type="button" wire:click="segera('google')" class="au-tombol au-kedua"><span aria-hidden="true" class="au-g">G</span> Google</button>
            <button type="button" wire:click="segera('wa')" class="au-tombol au-kedua"><span aria-hidden="true" class="flex" style="color:#3C7A00"><x-ikon nama="whatsapp" :ukuran="18" /></span> WhatsApp</button>
        </div>

        <div class="au-bawah">Belum punya akun? <a href="{{ route('daftar') }}" wire:navigate>Daftar sekarang</a></div>
    </div>
</div>
