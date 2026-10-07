{{--
    Butir menu sidebar (nilai dari papan Main.dc.html). `kecil` = butir submenu Panel Admin (32px, 13px, ikon 15).
    `badge` = label biru ("BARU"); `lencana` = jumlah merah (mis. RSVP baru), diisi angka > 0.
--}}
@props(['href', 'ikon' => null, 'aktif' => false, 'badge' => null, 'lencana' => 0, 'kecil' => false])
<a href="{{ $href }}" wire:navigate @if ($aktif) aria-current="page" @endif {{ $attributes->class(['nav-item', 'nav-item-kecil' => $kecil]) }}>
    @if ($ikon)
        <x-ikon :nama="$ikon" :ukuran="$kecil ? 15 : 18" />
    @endif
    <span class="min-w-0 flex-1 truncate">{{ $slot }}</span>
    @if ($badge)
        <span class="shrink-0 rounded-md bg-brand-50 px-1.5 py-0.5 text-[10px] leading-3.5 font-semibold tracking-[0.02em] text-brand">{{ $badge }}</span>
    @endif
    @if ($lencana > 0)
        <span class="box-border h-5 min-w-5 shrink-0 rounded-[10px] bg-[#EF4444] px-1.5 text-center text-[11px] leading-5 font-semibold text-white" aria-label="{{ $lencana }} baru">{{ $lencana }}</span>
    @endif
</a>
