{{-- Logo sementara berbentuk teks; gambar logo asli dipasang kemudian. --}}
@props(['ukuran' => 'md'])
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2 font-semibold tracking-tight text-ink']) }}>
    <span class="flex {{ $ukuran === 'lg' ? 'size-12 rounded-xl' : 'size-9 rounded-[10px]' }} items-center justify-center bg-brand text-white">
        <x-ikon nama="surel" :ukuran="$ukuran === 'lg' ? 24 : 18" />
    </span>
    <span class="{{ $ukuran === 'lg' ? 'text-2xl' : 'text-lg' }} leading-none">undangyah</span>
</span>
