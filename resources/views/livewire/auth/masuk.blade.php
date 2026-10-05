<div class="flex flex-col gap-6">
    <x-auth-kepala judul="Selamat datang kembali" sub="Masuk untuk mengelola undangan, tamu, dan RSVP kamu." />

    <form wire:submit="masuk" class="flex flex-col gap-4" novalidate>
        @if ($info)
            <div role="status" class="flex items-start gap-2 rounded-[10px] bg-warning-50 px-3 py-2.5 text-[13px] leading-[18px] font-medium text-warning">
                <span class="flex-1">{{ $info }}</span>
                <button type="button" wire:click="$set('info', '')" aria-label="Tutup" class="-my-1 flex size-7 items-center justify-center text-lg leading-none">&times;</button>
            </div>
        @endif

        @error('form')
            <div role="alert" class="flex items-center gap-2 rounded-[10px] bg-danger-50 px-3 py-2.5 text-[13px] leading-[18px] font-medium text-danger">
                <x-ikon nama="peringatan" :ukuran="16" /> {{ $message }}
            </div>
        @enderror

        <x-field id="l-email" label="Email" ikon="surel" type="email" wire:model="email" autocomplete="email" placeholder="nama@email.com" :galat="$errors->first('email')" />
        <x-field id="l-sandi" label="Kata sandi" ikon="gembok" :sandi="true" wire:model="sandi" autocomplete="current-password" placeholder="Masukkan kata sandi" :galat="$errors->first('sandi')" />

        <div class="flex items-center justify-between gap-2">
            <label class="flex min-h-10 cursor-pointer items-center gap-2.5 text-[13px] leading-[18px] font-medium">
                <input type="checkbox" wire:model="ingat" class="size-[18px] rounded border-line-strong accent-brand"> Ingat saya
            </label>
            <a href="{{ route('lupa-sandi') }}" wire:navigate class="text-[13px] leading-[18px] font-semibold text-brand hover:underline">Lupa kata sandi?</a>
        </div>

        <button type="submit" class="btn btn-primary btn-lg" wire:loading.attr="disabled" wire:target="masuk">
            <span wire:loading.remove wire:target="masuk">Masuk</span>
            <span wire:loading wire:target="masuk">Memeriksa akun...</span>
        </button>
    </form>

    <div class="flex items-center gap-3" aria-hidden="true">
        <span class="h-px flex-1 bg-line"></span><span class="text-xs leading-4 font-medium text-muted">atau masuk dengan</span><span class="h-px flex-1 bg-line"></span>
    </div>
    <div class="grid grid-cols-2 gap-2.5">
        <button type="button" wire:click="segera('google')" class="btn btn-secondary"><span aria-hidden="true" class="flex size-[22px] items-center justify-center rounded-full bg-canvas text-[13px] font-bold">G</span> Google</button>
        <button type="button" wire:click="segera('wa')" class="btn btn-secondary"><x-ikon nama="telepon" :ukuran="18" class="text-success" /> WhatsApp</button>
    </div>

    <p class="m-0 text-center text-sm leading-5 text-muted">Belum punya akun? <a href="{{ route('daftar') }}" wire:navigate class="font-semibold text-brand hover:underline">Daftar sekarang</a></p>
</div>
