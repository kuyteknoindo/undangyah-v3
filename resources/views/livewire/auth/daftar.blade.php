<div class="flex flex-col gap-6">
    <x-auth-kepala judul="Buat akun baru" sub="Mulai buat undangan digital dalam beberapa menit." />

    <form wire:submit="daftar" class="flex flex-col gap-4" novalidate>
        @error('form')
            <div role="alert" class="flex items-center gap-2 rounded-[10px] bg-danger-50 px-3 py-2.5 text-[13px] leading-[18px] font-medium text-danger">
                <x-ikon nama="peringatan" :ukuran="16" /> {{ $message }}
            </div>
        @enderror

        <x-field id="d-nama" label="Nama lengkap" ikon="pengguna" wire:model="nama" autocomplete="name" placeholder="Nama kamu" :galat="$errors->first('nama')" />
        <x-field id="d-email" label="Email" ikon="surel" type="email" wire:model="email" autocomplete="email" placeholder="nama@email.com" :galat="$errors->first('email')" />
        <x-field id="d-wa" label="Nomor WhatsApp (opsional)" ikon="telepon" awalan="+62" type="tel" wire:model="whatsapp" autocomplete="tel-national" inputmode="tel" placeholder="81234567890" :galat="$errors->first('whatsapp')" />

        <div class="flex flex-col gap-2" x-data="{ p: '' }">
            <x-field id="d-sandi" label="Kata sandi" ikon="gembok" :sandi="true" wire:model="sandi" x-model="p" autocomplete="new-password" placeholder="Minimal 8 karakter, huruf dan angka" :galat="$errors->first('sandi')" />
            <x-kekuatan-sandi />
        </div>
        <x-field id="d-sandi2" label="Konfirmasi kata sandi" ikon="gembok" :sandi="true" wire:model="sandiKonfirmasi" autocomplete="new-password" placeholder="Ulangi kata sandi" :galat="$errors->first('sandiKonfirmasi')" />

        <div class="flex flex-col gap-1.5">
            <label class="flex cursor-pointer items-start gap-2.5 text-[13px] leading-5 text-body">
                <input type="checkbox" wire:model="setuju" class="mt-0.5 size-[18px] shrink-0 rounded border-line-strong accent-brand" @error('setuju') aria-invalid="true" @enderror>
                <span>Saya setuju dengan <strong class="font-semibold">Syarat &amp; Ketentuan</strong> serta <strong class="font-semibold">Kebijakan Privasi</strong> undangyah.com.</span>
            </label>
            @error('setuju')
                <div role="alert" class="flex items-center gap-1.5 text-xs leading-4 font-medium text-danger"><x-ikon nama="peringatan" :ukuran="14" /> {{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary btn-lg" wire:loading.attr="disabled" wire:target="daftar">
            <span wire:loading.remove wire:target="daftar">Daftar Sekarang</span>
            <span wire:loading wire:target="daftar">Membuat akun...</span>
        </button>
    </form>

    <p class="m-0 text-center text-sm leading-5 text-muted">Sudah punya akun? <a href="{{ route('masuk') }}" wire:navigate class="font-semibold text-brand hover:underline">Masuk</a></p>
</div>
