<div class="flex flex-col gap-6">
    <x-auth-kepala judul="Verifikasi email" :sub="'Masukkan kode '.$panjang.' digit yang dikirim ke '.$emailTersamar" />

    <form wire:submit="verifikasi" class="flex flex-col gap-4" novalidate>
        @if ($info)
            <div role="status" class="flex items-center gap-2 rounded-[10px] bg-success-50 px-3 py-2.5 text-[13px] leading-[18px] font-medium text-success">
                <x-ikon nama="centang" :ukuran="16" /> {{ $info }}
            </div>
        @endif

        <x-kode-otp aksi="verifikasi" :panjang="$panjang" :galat="$errors->first('kode')" />

        <button type="submit" class="btn btn-primary btn-lg" wire:loading.attr="disabled" wire:target="verifikasi">
            <span wire:loading.remove wire:target="verifikasi">Verifikasi</span>
            <span wire:loading wire:target="verifikasi">Memeriksa kode...</span>
        </button>

        <x-kirim-ulang :detik="$jeda" />
    </form>

    <div class="flex flex-col items-center gap-1 border-t border-line pt-4 text-[13px] leading-5 text-muted">
        <span>Kode berlaku 10 menit. Cek juga folder spam.</span>
        <form method="POST" action="{{ route('keluar') }}">
            @csrf
            <button type="submit" class="font-semibold text-brand hover:underline">Bukan kamu? Keluar</button>
        </form>
    </div>
</div>
