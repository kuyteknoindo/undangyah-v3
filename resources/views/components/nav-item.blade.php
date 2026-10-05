@props(['href', 'ikon' => null, 'aktif' => false, 'badge' => null])
<a href="{{ $href }}" wire:navigate @if ($aktif) aria-current="page" @endif {{ $attributes->class(['nav-item']) }}>
    @if ($ikon)
        <x-ikon :nama="$ikon" :class="$aktif ? 'text-brand' : 'text-subtle'" />
    @endif
    <span class="min-w-0 flex-1 truncate">{{ $slot }}</span>
    @if ($badge)
        <span class="rounded-full bg-brand-100 px-1.5 text-[10px] leading-4 font-bold text-brand">{{ $badge }}</span>
    @endif
</a>
