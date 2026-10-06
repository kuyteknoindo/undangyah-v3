{{-- Satu baris bagian di daftar kiri. Variabel: $b (data bagian), $gerak, $grup, $pos, $total. --}}
<div wire:key="baris-{{ $b['id'] }}" role="listitem" data-sid="{{ $b['id'] }}" data-grup="{{ $grup }}" data-aktif="{{ $aktif === $b['id'] ? '1' : '0' }}"
     class="ed-baris {{ $b['sembunyi'] ? 'opacity-60' : '' }}" x-bind:class="atas === '{{ $b['id'] }}' && seret !== '{{ $b['id'] }}' ? 'ed-atas' : ''"
     @if ($gerak)
        draggable="true"
        x-on:dragstart="seret = '{{ $b['id'] }}'; $event.dataTransfer.effectAllowed = 'move'; $event.dataTransfer.setData('text/plain', '{{ $b['id'] }}')"
        x-on:dragover.prevent="if (seret) atas = '{{ $b['id'] }}'"
        x-on:drop.prevent="if (seret && seret !== '{{ $b['id'] }}') $wire.pindahBagian(seret, '{{ $b['id'] }}'); seret = null; atas = null"
        x-on:dragend="seret = null; atas = null"
     @endif>
    @if ($gerak)
        <button type="button" class="ed-grip" aria-label="Geser urutan {{ $b['nama'] }}, posisi {{ $pos }} dari {{ $total }}. Pakai panah atas atau bawah untuk memindahkan."
                x-on:keydown.arrow-up.prevent="$wire.geserBagian('{{ $b['id'] }}', -1)" x-on:keydown.arrow-down.prevent="$wire.geserBagian('{{ $b['id'] }}', 1)">
            <x-ikon nama="editor-grip" :ukuran="16" />
        </button>
    @else
        <span class="flex w-7 flex-none items-center justify-center text-[#9aa0a9]" title="Posisi tetap"><x-ikon nama="editor-kunci" :ukuran="13" /></span>
    @endif
    <button type="button" wire:click="pilihBagian('{{ $b['id'] }}')" aria-current="{{ $aktif === $b['id'] ? 'true' : 'false' }}"
            class="flex min-h-[52px] min-w-0 flex-1 items-center gap-2.5 rounded-xl py-1.5 pr-2 text-left">
        <span class="flex size-9 flex-none items-center justify-center rounded-xl {{ $b['selesai'] ? 'bg-[#d7ffb8] text-[#3c7a00]' : 'bg-[#f4f5f7] text-[#454b55]' }}"><x-ikon :nama="'editor-'.$b['id']" :ukuran="18" /></span>
        <span class="flex min-w-0 flex-1 flex-col">
            <span class="flex items-center gap-1.5">
                <span class="truncate text-sm leading-5 font-bold {{ $aktif === $b['id'] ? 'text-brand' : 'text-[#262a31]' }}">{{ $b['nama'] }}</span>
                @if ($b['opsional'])<span class="rounded-md bg-[#f2f4f7] px-1.5 text-[10px] leading-4 font-bold text-[#666c76]">Opsional</span>@endif
            </span>
            <span class="truncate text-xs leading-4 font-medium {{ $b['selesai'] ? 'text-[#3c7a00]' : ($b['sebagian'] ? 'text-[#b25e00]' : 'text-[#666c76]') }}">{{ $b['teks'] }}</span>
        </span>
        @if ($b['selesai'])
            <span class="flex size-5 flex-none items-center justify-center rounded-full bg-[#58cc02] text-white"><x-ikon nama="centang" :ukuran="12" /></span>
        @else
            <span class="size-5 flex-none rounded-full border-2 border-[#d0d5dd]"></span>
        @endif
    </button>
</div>
