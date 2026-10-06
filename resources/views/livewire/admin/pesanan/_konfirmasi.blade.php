{{-- Dialog konfirmasi aksi pesanan. Variabel: $konfirmasi (array dari AksiPesananAdmin::dataKonfirmasi, atau null). --}}
<x-dialog wire:model="dialogAksi" :judul="$konfirmasi['judul'] ?? 'Konfirmasi'" :sub="$konfirmasi['sub'] ?? null" lebar="max-w-[480px]">
    @if ($konfirmasi)
        @if ($konfirmasi['efek'])
            <ul class="m-0 flex list-none flex-col gap-2 p-0">
                @foreach ($konfirmasi['efek'] as $e)
                    <li class="flex items-start gap-2 text-[13px] leading-[18px] text-body"><x-ikon nama="centang" :ukuran="15" class="mt-px shrink-0 text-success" /> <span>{{ $e }}</span></li>
                @endforeach
            </ul>
        @endif
        @if ($konfirmasi['peringatan'])
            <div role="note" class="flex items-start gap-2 rounded-xl bg-warning-50 px-3 py-2.5 text-[13px] leading-[18px] text-warning"><x-ikon nama="peringatan" :ukuran="16" class="mt-px shrink-0" /> <span>{{ $konfirmasi['peringatan'] }}</span></div>
        @endif
        @if (in_array($konfirmasi['jenis'], ['kedaluwarsa'], true))
            <p class="sub m-0">Pesanan ditutup sebelum batas waktunya. Pembeli menerima notifikasi dan harus membuat pesanan baru.</p>
        @endif
        @if ($konfirmasi['jenis'] !== 'kedaluwarsa')
            <div class="flex flex-col gap-1.5">
                <label for="ap-alasan" class="label">{{ $konfirmasi['label'] }}@if ($konfirmasi['wajib']) <span class="text-danger" aria-hidden="true">*</span>@endif</label>
                <textarea id="ap-alasan" class="inp" rows="3" maxlength="300" wire:model="alasan" @if ($errors->has('alasan')) aria-invalid="true" aria-describedby="ap-alasan-galat" @endif placeholder="{{ $konfirmasi['wajib'] ? 'Tulis alasan singkat' : 'Mis. nomor mutasi rekening' }}"></textarea>
                @error('alasan')<div id="ap-alasan-galat" role="alert" class="galat">{{ $message }}</div>@enderror
            </div>
        @endif
        @if ($konfirmasi['bisaTurunkan'])
            <div class="flex items-center justify-between gap-4 rounded-xl border border-line px-3 py-2.5">
                <div class="min-w-0">
                    <div id="ap-sw-turun" class="text-[13px] leading-[18px] font-semibold text-ink">Turunkan paket ke Gratis</div>
                    <div class="hint">Paket pembeli kembali ke Gratis bila paket ini masih yang aktif.</div>
                </div>
                <button type="button" role="switch" class="sakelar" aria-checked="{{ $turunkan ? 'true' : 'false' }}" aria-labelledby="ap-sw-turun" wire:click="$toggle('turunkan')"></button>
            </div>
        @endif
        @if ($konfirmasi['jenis'] === 'refund')
            <p class="hint m-0">Hanya penandaan. Pengembalian dana dilakukan manual di luar aplikasi.</p>
        @endif
    @endif
    <x-slot:footer>
        <button type="button" class="btn btn-secondary" wire:click="batalAksi">Kembali</button>
        <button type="button" wire:click="jalankan" wire:loading.attr="disabled" wire:target="jalankan"
                class="btn {{ in_array($konfirmasi['jenis'] ?? '', ['batal', 'tolak', 'refund', 'kedaluwarsa'], true) ? 'btn-danger-solid' : 'btn-primary' }}">{{ $konfirmasi['tombol'] ?? 'Lanjut' }}</button>
    </x-slot:footer>
</x-dialog>
