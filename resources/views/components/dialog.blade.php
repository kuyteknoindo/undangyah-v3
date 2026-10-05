{{--
    Dialog modal berbasis Alpine. Dikendalikan dari Livewire dengan wire:model (boolean), mis.:
    <x-dialog wire:model="tampilHapus" judul="Hapus paket?" lebar="max-w-[480px]"> ... <x-slot:footer> ... </x-slot:footer> </x-dialog>
    Tutup: Esc, klik latar, atau tombol X.
--}}
@props(['judul', 'sub' => null, 'lebar' => 'max-w-[560px]'])
<div x-data="{ buka: false }" x-modelable="buka" {{ $attributes->whereStartsWith('wire:model') }}
     x-show="buka" x-cloak x-on:keydown.escape.window="buka = false" class="fixed inset-0 z-[80] flex items-start justify-center overflow-auto bg-gray-900/45 px-4 py-16"
     x-on:click.self="buka = false" role="dialog" aria-modal="true" aria-label="{{ $judul }}">
    <div x-show="buka" x-trap.noscroll="buka" x-transition.opacity class="relative flex w-full {{ $lebar }} flex-col rounded-2xl bg-white shadow-[0_20px_40px_-8px_rgba(16,24,40,0.25)]" style="animation: dialog-muncul .25s ease both">
        <div class="flex items-start gap-3 px-6 pt-5">
            <div class="min-w-0 flex-1">
                <h2 class="m-0 text-base leading-6 font-semibold text-ink">{{ $judul }}</h2>
                @if ($sub)<p class="sub m-0 mt-0.5">{{ $sub }}</p>@endif
            </div>
            <button type="button" class="btn-icon" x-on:click="buka = false" aria-label="Tutup"><x-ikon nama="tutup" :ukuran="16" /></button>
        </div>
        <div class="flex flex-col gap-3.5 px-6 py-4">{{ $slot }}</div>
        @isset($footer)
            <div class="flex flex-wrap justify-end gap-2 border-t border-[#eef0f3] px-6 py-4">{{ $footer }}</div>
        @endisset
    </div>
</div>
