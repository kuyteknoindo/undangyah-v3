{{-- Kerangka kanvas saat RenderUndangan belum tersedia: hanya daftar bagian yang tampil, berurutan. --}}
<div class="flex flex-col gap-px bg-[#e2e5e9]" data-kerangka>
    @foreach ($bagian['urutan'] as $id)
        @continue(in_array($id, $bagian['sembunyi'], true))
        <button type="button" data-bagian="{{ $id }}" class="flex min-h-24 w-full flex-col items-center justify-center gap-1 bg-white px-4 py-6 text-center">
            <span class="text-sm font-bold text-[#262a31]">{{ \App\Support\SkemaUndangan::bagian()[$id]['name'] }}</span>
            <span class="text-xs text-[#666c76]">Pratinjau bagian ini muncul setelah renderer undangan tersedia</span>
        </button>
    @endforeach
</div>
