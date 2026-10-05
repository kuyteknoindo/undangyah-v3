{{-- <x-pill warna="hijau|kuning|merah|biru|abu">Aktif</x-pill> --}}
@props(['warna' => 'abu'])
<span {{ $attributes->class(['pill', 'pill-'.$warna]) }}>{{ $slot }}</span>
