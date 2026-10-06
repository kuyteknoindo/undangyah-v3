{{-- Dialog pustaka foto milik pengguna: pilih, unggah baru (progres + galat), cari. Data $pustaka dari trait FotoEditor. --}}
@php
    $galeri = $modeFoto === 'galeri';
    $judulFoto = match ($modeFoto) { 'galeri' => 'Tambah foto galeri', 'bgf' => 'Pilih gambar Background Fixed', default => 'Pilih foto' };
@endphp
<x-dialog wire:model="dialogFoto" :judul="$judulFoto" :sub="$galeri ? 'Pilih beberapa foto sekaligus.' : 'Pilih dari pustakamu atau unggah foto baru.'" lebar="max-w-[760px]">
    <div x-data="{ unggah: false, progres: 0, gagal: '' }"
         x-on:livewire-upload-start="unggah = true; progres = 0; gagal = ''"
         x-on:livewire-upload-progress="progres = $event.detail.progress"
         x-on:livewire-upload-finish="unggah = false"
         x-on:livewire-upload-error="unggah = false; gagal = 'Unggahan gagal. Periksa ukuran file dan koneksi, lalu coba lagi.'"
         class="flex flex-col gap-3.5">
        <div class="flex flex-wrap items-center gap-2.5">
            <label for="ft-unggah" class="btn btn-primary cursor-pointer focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-brand">
                <x-ikon nama="ef-unggah" :ukuran="16" /> Unggah foto
                <input id="ft-unggah" type="file" class="sr-only" multiple wire:model="unggahFoto" accept="image/jpeg,image/png,image/webp,image/gif">
            </label>
            <div class="relative min-w-40 flex-1">
                <label for="ft-cari" class="sr-only">Cari foto</label>
                <span class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-muted"><x-ikon nama="bagikan-cari" :ukuran="16" /></span>
                <input id="ft-cari" type="search" class="inp pl-9" maxlength="60" placeholder="Cari nama foto" wire:model.live.debounce.300ms="cariFoto" autocomplete="off">
            </div>
        </div>

        <div x-show="unggah" x-cloak role="status" aria-live="polite" class="flex items-center gap-3 rounded-xl bg-[#f4f5f7] px-3 py-2.5">
            <div class="min-w-0 flex-1">
                <div class="text-[13px] font-medium text-ink">Mengunggah… <span x-text="progres + '%'"></span></div>
                <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-gray-200" role="progressbar" aria-label="Progres unggahan" aria-valuemin="0" aria-valuemax="100" x-bind:aria-valuenow="progres"><div class="h-full rounded-full bg-brand transition-[width]" x-bind:style="'width:' + progres + '%'"></div></div>
            </div>
        </div>
        <div wire:loading wire:target="unggahFoto" class="sr-only" role="status">Memproses unggahan…</div>
        <div x-show="gagal" x-cloak class="galat" role="alert" x-text="gagal"></div>
        @foreach ($galatFoto as $g)
            <div class="galat" role="alert" wire:key="galat-foto-{{ $loop->index }}">{{ $g }}</div>
        @endforeach

        @if ($pustaka)
            <div class="flex items-center gap-2.5 text-xs leading-4 text-[#666c76]">
                <span>Terpakai {{ $pustaka['terpakai'] }} dari {{ $pustaka['kuota'] }}</span>
                <span class="h-1.5 w-24 overflow-hidden rounded-full bg-gray-200" role="progressbar" aria-label="Kuota penyimpanan terpakai" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $pustaka['persen'] }}"><span class="block h-full rounded-full {{ $pustaka['penuh'] ? 'bg-[#d83a3a]' : 'bg-brand' }}" style="width: {{ $pustaka['persen'] }}%"></span></span>
                @if ($pustaka['penuh'])<span class="font-bold text-[#b42318]">Kuota penuh. Hapus foto di pustaka untuk mengunggah lagi.</span>@endif
            </div>

            @if ($pustaka['item'] === [])
                <x-kosong :judul="$cariFoto !== '' ? 'Foto tidak ditemukan' : 'Pustaka fotomu masih kosong'" :teks="$cariFoto !== '' ? 'Coba kata kunci lain.' : 'Unggah foto pertamamu dengan tombol di atas.'" ikon="ef-foto-kosong" />
            @else
                <ul class="m-0 grid max-h-[46vh] list-none grid-cols-3 gap-2 overflow-y-auto p-1 sm:grid-cols-4" aria-label="Foto di pustaka">
                    @foreach ($pustaka['item'] as $m)
                        @php
                            $dipilih = in_array($m['id'], $pilihanFoto, true);
                        @endphp
                        <li wire:key="pf-{{ $m['id'] }}">
                            <button type="button" wire:click="pilihFoto({{ $m['id'] }})" aria-pressed="{{ $dipilih ? 'true' : 'false' }}" aria-label="{{ $m['nama'] }}"
                                    class="relative block aspect-square w-full overflow-hidden rounded-xl border-2 bg-[#f4f5f7] {{ $dipilih ? 'border-brand ring-2 ring-brand/30' : 'border-[#e2e5e9] hover:border-[#bfd3ff]' }}">
                                @if ($m['thumb'])<img src="{{ $m['thumb'] }}" alt="" class="size-full object-cover" loading="lazy">@endif
                                @if ($dipilih)<span class="absolute top-1.5 right-1.5 flex size-6 items-center justify-center rounded-full bg-brand text-white"><x-ikon nama="centang" :ukuran="14" /></span>@endif
                                <span class="absolute inset-x-0 bottom-0 truncate bg-black/55 px-1.5 py-0.5 text-left text-[10px] leading-4 text-white">{{ $m['nama'] }}</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
                <div class="flex items-center justify-between gap-2 text-xs text-[#666c76]">
                    <span>{{ count($pustaka['item']) }} dari {{ $pustaka['total'] }} foto</span>
                    @if ($pustaka['lagi'])<button type="button" class="ed-kecil" wire:click="lebihFoto">Muat lebih banyak</button>@endif
                </div>
            @endif
        @endif
    </div>
    <x-slot:footer>
        @if ($galeri)<span class="mr-auto self-center text-xs text-[#666c76]" role="status">{{ count($pilihanFoto) }} dipilih · sisa jatah {{ $pustaka['sisaGaleri'] ?? 0 }} foto</span>@endif
        <button type="button" class="btn btn-secondary" x-on:click="$wire.dialogFoto = false">Batal</button>
        <button type="button" class="btn btn-primary" wire:click="terapkanPilihan" @disabled($pilihanFoto === [])>{{ $galeri ? 'Tambahkan '.count($pilihanFoto).' foto' : 'Pakai foto ini' }}</button>
    </x-slot:footer>
</x-dialog>
