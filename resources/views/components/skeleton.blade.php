{{-- Kerangka pemuat. <x-skeleton class="h-4 w-32" /> ; atau mode="kartu" untuk daftar baris. --}}
@props(['mode' => 'baris', 'jumlah' => 3])
@if ($mode === 'kartu')
    <div role="status" aria-busy="true" class="flex flex-col gap-3">
        <span class="sr-only">Memuat…</span>
        @for ($i = 0; $i < $jumlah; $i++)
            <div class="kartu flex items-center gap-3 p-4"><span class="skel size-10 shrink-0 rounded-full"></span><div class="flex flex-1 flex-col gap-2"><span class="skel h-3.5 w-1/3"></span><span class="skel h-3 w-2/3"></span></div></div>
        @endfor
    </div>
@else
    <span {{ $attributes->class(['skel']) }} aria-hidden="true"></span>
@endif
