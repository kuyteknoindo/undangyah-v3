<div>
    <div class="ed-label mb-2 px-1">Bagian undangan</div>
    <div class="flex flex-col gap-1">
        @include('livewire.editor.baris', ['b' => $baris['sampul'], 'gerak' => false, 'grup' => 'tetap', 'pos' => 1, 'total' => 1])
        @include('livewire.editor.baris', ['b' => $baris['penutup'], 'gerak' => false, 'grup' => 'tetap', 'pos' => 1, 'total' => 1])
    </div>
</div>

@foreach ([['konten', 'Konten Undangan', 'Informasi yang dilihat oleh tamu', $konten], ['fitur', 'Fitur Tambahan', 'Pelengkap agar undangan lebih personal', $fitur]] as [$kodeGrup, $judulGrup, $subGrup, $ids])
    <div wire:key="grup-{{ $kodeGrup }}">
        <div class="mb-2 px-1">
            <div class="text-sm leading-5 font-bold text-[#262a31]">{{ $judulGrup }}</div>
            <div class="text-xs leading-4 text-[#666c76]">{{ $subGrup }}</div>
        </div>
        <div class="flex flex-col gap-1" role="list">
            @foreach ($ids as $i => $id)
                @include('livewire.editor.baris', ['b' => $baris[$id], 'gerak' => true, 'grup' => $kodeGrup, 'pos' => $i + 1, 'total' => count($ids)])
            @endforeach
        </div>
    </div>
@endforeach

<div class="rounded-2xl bg-[#f4f5f7] p-3 text-xs leading-[17px] text-[#666c76]">
    Tarik ikon titik-titik di kiri untuk mengubah urutan bagian; urutan di kanvas ikut berubah. Bisa juga dengan fokus di ikon lalu tekan panah atas atau bawah. Sampul selalu di awal dan Penutup di akhir.
</div>
@include('livewire.editor.kiri-bgf')
