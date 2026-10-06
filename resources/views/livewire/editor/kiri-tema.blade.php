<div>
    <div class="ed-label">Tema tersedia · {{ $semuaTema->count() }}</div>
    <p class="m-0 mt-1 text-xs leading-[17px] text-[#666c76]">Dibuat oleh tim undangyah di Theme Creator dan sudah dipublikasikan. Pilih satu untuk langsung melihatnya di kanvas.</p>
</div>

<div role="group" aria-label="Filter paket tema" class="flex flex-wrap gap-1.5">
    @foreach (['Semua', ...\App\Models\Tema::TIER] as $t)
        <button type="button" class="ed-kecil" x-on:click="tier = '{{ $t }}'" x-bind:style="tier === '{{ $t }}' ? 'background:#1d4ed8;border-color:#1d4ed8;color:#fff' : ''" x-bind:aria-pressed="tier === '{{ $t }}'">{{ $t }}</button>
    @endforeach
</div>

<div class="grid grid-cols-2 gap-2.5">
    @foreach ($semuaTema as $t)
        @php
            $dipakai = $u->tema_id === $t->id;
            $kunciTema = $terkunci[$t->id] ?? false;
            $fontNama = ['jakarta' => 'Sans Modern', 'script' => 'Script Elegan'][$t->font_judul] ?? 'Serif Klasik';
        @endphp
        <button type="button" wire:key="tema-{{ $t->id }}" wire:click="pilihTema({{ $t->id }})" x-show="tier === 'Semua' || tier === '{{ $t->tier }}'"
                aria-label="Pakai tema {{ $t->nama }}, {{ $t->tier }}{{ $dipakai ? ', sedang dipakai' : '' }}{{ $kunciTema ? ', terkunci di paketmu' : '' }}"
                class="relative flex flex-col gap-1.5 rounded-2xl border-2 p-2 text-left {{ $dipakai ? 'border-brand bg-brand-100 shadow-[0_3px_0_#bfd3ff]' : 'border-[#e2e5e9] bg-white' }} {{ $kunciTema ? 'opacity-70' : '' }}">
            <x-buat-undangan.sampul :tema="$t" :judul="($isi['priaP'] ?? 'Raka').' & '.($isi['wanitaP'] ?? 'Sinta')" tanggal="" />
            @if ($dipakai)
                <span class="absolute top-3 right-3 flex size-5 items-center justify-center rounded-full bg-brand text-white"><x-ikon nama="centang" :ukuran="12" /></span>
            @elseif ($kunciTema)
                <span class="absolute top-3 right-3 flex size-5 items-center justify-center rounded-full bg-[#262a31] text-white"><x-ikon nama="editor-kunci" :ukuran="11" /></span>
            @endif
            <span class="px-0.5">
                <span class="block truncate text-[13px] leading-[18px] font-bold text-[#262a31]">{{ $t->nama }}</span>
                <span class="mt-0.5 flex items-center gap-1.5 text-[11px] leading-4 font-semibold text-[#666c76]">
                    <span class="rounded-md px-1.5" style="{{ \App\Support\PratinjauTema::TIER[$t->tier] ?? '' }}">{{ $t->tier }}</span>{{ $fontNama }}
                </span>
            </span>
        </button>
    @endforeach
</div>
@if ($semuaTema->isEmpty())
    <div class="rounded-2xl bg-[#f4f5f7] p-4 text-center text-[13px] text-[#666c76]">Belum ada tema yang dipublikasikan.</div>
@endif

<p class="m-0 text-xs leading-[17px] text-[#666c76]">Tema dipakai: <b>{{ $tema?->nama ?? 'belum ada' }}</b>{{ $tema ? ' ('.$tema->tier.')' : '' }}. Mengganti tema hanya mengubah warna, font, dan tampilan. Isi undangan tidak berubah.</p>

<div>
    <div class="ed-label mb-2">Hiasan</div>
    <button type="button" role="switch" aria-checked="{{ ($pengaturan['lingkaran'] ?? true) ? 'true' : 'false' }}" wire:click="toggleLingkaran"
            class="flex min-h-12 w-full items-center gap-3 rounded-2xl border-2 border-[#e2e5e9] px-3 text-left">
        <span class="flex-1 text-sm leading-5 font-semibold">Lingkaran di sampul</span>
        <span class="ed-sw"></span>
        <span class="w-10 text-xs font-bold text-[#666c76]">{{ ($pengaturan['lingkaran'] ?? true) ? 'Aktif' : 'Mati' }}</span>
    </button>
</div>
