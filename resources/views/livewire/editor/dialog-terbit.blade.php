<x-dialog wire:model="dialogTerbit" :judul="$tahap === 'selesai' ? 'Undangan sudah terbit' : 'Terbitkan undangan'" lebar="max-w-[460px]">
    @if ($tahap === 'selesai')
        <div class="flex items-center gap-2 rounded-2xl bg-[#f4f5f7] p-1.5 pl-3">
            <span class="min-w-0 flex-1 truncate text-sm font-bold">{{ $alamat }}</span>
            <button type="button" class="ed-kecil" x-on:click="salinTaut()"><x-ikon nama="editor-salin" :ukuran="14" /><span x-text="salin ? 'Tersalin' : 'Salin link'"></span></button>
        </div>
        <p class="m-0 text-[13px] leading-[19px] text-[#454b55]">
            @if ($u->diterbitkan_at)Diterbitkan {{ $u->diterbitkan_at->timezone(config('app.timezone'))->translatedFormat('j F Y, H:i') }} · @endif tema {{ $tema?->nama ?? '-' }}.
            Tamu bisa membuka link ini. Tambahkan <b>?to=Nama%20Tamu</b> di akhir link untuk menyapa tamu dengan namanya.
        </p>
        <div class="flex items-center gap-3 rounded-2xl border-2 border-[#e2e5e9] p-3">
            <span class="block size-[112px] flex-none overflow-hidden rounded-lg bg-white [&>svg]:size-full" role="img" aria-label="Kode QR link undangan">{!! \App\Services\KodeQr::svg($url, 224) !!}</span>
            <div class="min-w-0 text-[13px] leading-[19px] text-[#454b55]">
                <div class="font-bold text-ink">Kode QR link undangan</div>
                Cetak di undangan fisik atau tampilkan di layar.
                @if (\Illuminate\Support\Facades\Route::has('bagikan'))<a href="{{ route('bagikan', ['undangan' => $u->id]) }}" wire:navigate class="mt-1 block font-bold text-brand">Unduh QR di Bagikan Undangan</a>@endif
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ $url }}" target="_blank" rel="noopener" class="ed-kecil"><x-ikon nama="editor-luar" :ukuran="14" />Buka sebagai tamu</a>
            <a href="https://wa.me/?text={{ rawurlencode('Dengan hormat, kami mengundang Anda ke acara '.$u->judul.'. Buka undangan digital kami: '.$url.'?to=Nama%20Tamu') }}" target="_blank" rel="noopener" class="ed-kecil"><x-ikon nama="bagikan" :ukuran="14" />Bagikan WhatsApp</a>
        </div>
    @else
        <div>
            <label for="pub-slug" class="mb-1.5 block text-[13px] leading-[18px] font-bold">Link undangan</label>
            <div class="flex items-center gap-1 rounded-[14px] border-2 bg-[#f4f5f7] pl-3 focus-within:border-brand focus-within:bg-white {{ $galatSlug !== '' ? 'border-danger' : 'border-[#e2e5e9]' }}">
                <span class="max-w-[140px] truncate text-[13px] text-[#666c76]">{{ parse_url((string) config('app.url'), PHP_URL_HOST) }}/</span>
                <input id="pub-slug" type="text" maxlength="60" class="h-10 min-w-0 flex-1 border-0 bg-transparent px-1 text-sm font-medium outline-none" wire:model.live.debounce.500ms="slugTerbit" aria-invalid="{{ $galatSlug !== '' ? 'true' : 'false' }}">
            </div>
            @if ($galatSlug !== '')<div class="galat mt-1" role="alert">{{ $galatSlug }}</div>@endif
            @if ($terbit)<div class="hint mt-1">Mengganti link membuat link lama berhenti bekerja bagi tamu yang sudah menerimanya.</div>@endif
        </div>

        @if ($blokir || $saran)
            <div class="rounded-2xl border-2 border-[#e2e5e9] p-2.5">
                <div class="px-1 pb-1 text-[13px] font-bold">{{ $blokir ? 'Perlu diperbaiki sebelum terbit' : 'Disarankan dilengkapi (boleh tetap terbit)' }}</div>
                @foreach ([...array_map(fn ($b) => $b + ['blok' => true], $blokir), ...array_map(fn ($s) => $s + ['blok' => false], $saran)] as $m)
                    @php
                        $aksi = match (true) {
                            $m['kunci'] === 'judul' => "tab = 'setting'; \$wire.dialogTerbit = false",
                            $m['kunci'] === 'tema' => "tab = 'tema'; \$wire.dialogTerbit = false",
                            str_starts_with($m['kunci'], 'sec:') => "\$wire.pilihBagian('".substr($m['kunci'], 4)."'); \$wire.dialogTerbit = false",
                            $m['kunci'] === 'tamu' => '',
                            default => "\$wire.pilihBidang('".$m['kunci']."'); \$wire.dialogTerbit = false",
                        };
                    @endphp
                    <button type="button" wire:key="isu-{{ $loop->index }}" class="flex min-h-9 w-full items-center gap-2 rounded-lg px-1 text-left text-[13px] hover:bg-[#f4f5f7]" @if ($aksi !== '') x-on:click="{{ $aksi }}" @else disabled @endif>
                        <span class="size-2 flex-none rounded-full {{ $m['blok'] ? 'bg-[#ea2b2b]' : 'bg-[#f5a524]' }}"></span><span class="flex-1">{{ $m['teks'] }}</span>
                    </button>
                @endforeach
            </div>
        @endif

        @if ($galatTerbit !== '')<div class="galat" role="alert">{{ $galatTerbit }}</div>@endif

        <p class="m-0 text-[13px] leading-[19px] text-[#454b55]">
            Tampilan untuk tamu mengikuti tema <b>{{ $tema?->nama ?? '-' }}</b> persis seperti saat ini. Tema disimpan sebagai salinan saat diterbitkan, jadi perubahan tema oleh admin tidak mengubah undangan yang sudah terbit. Terbitkan ulang untuk memperbarui isi.
        </p>
        @if ($berubah)
            <div class="rounded-xl bg-[#fff4cc] px-3 py-2 text-[13px] font-semibold text-[#b25e00]">Ada perubahan yang belum diterbitkan. Tamu masih melihat versi sebelumnya.</div>
        @endif
    @endif

    <x-slot:footer>
        @if ($terbit)
            <button type="button" class="btn btn-danger mr-auto" wire:click="jadikanDraf" wire:confirm="Jadikan draf? Tamu tidak bisa membuka undangan sampai diterbitkan lagi.">Jadikan draf</button>
        @endif
        @if ($tahap === 'selesai')
            <button type="button" class="btn btn-primary" x-on:click="$wire.dialogTerbit = false">Selesai</button>
        @else
            <button type="button" class="btn btn-secondary" x-on:click="$wire.dialogTerbit = false">Batal</button>
            <button type="button" class="btn btn-primary" wire:click="terbitkan" wire:loading.attr="disabled" wire:target="terbitkan" @disabled(count($blokir) > 0)>{{ $terbit ? 'Terbitkan ulang' : 'Terbitkan sekarang' }}</button>
        @endif
    </x-slot:footer>
</x-dialog>
