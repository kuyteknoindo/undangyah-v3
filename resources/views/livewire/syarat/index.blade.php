<div class="mx-auto flex max-w-6xl flex-col gap-5"
     x-data="{ daftar: @js(array_column($bagian, 'anchor')), aktif: @js($bagian[0]['anchor']), kunci: 0,
        pantau() {
            if (Date.now() < this.kunci) return;
            let sekarang = this.daftar[0];
            this.daftar.forEach((id) => { const el = document.getElementById(id); if (el && el.getBoundingClientRect().top <= 140) sekarang = id; });
            this.aktif = sekarang;
        } }"
     x-on:scroll.window.passive="pantau()">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div role="group" aria-label="Pilih dokumen" class="seg">
            @foreach (['syarat' => 'Syarat & Ketentuan', 'privasi' => 'Kebijakan Privasi'] as $kunci => $label)
                <a href="{{ $tautan[$kunci] }}" wire:navigate @if ($dokumen === $kunci) aria-current="page" @endif
                   class="inline-flex min-h-11 items-center rounded-[10px] px-5 text-sm leading-[19px] font-bold {{ $dokumen === $kunci ? 'bg-brand text-white' : 'text-nav hover:bg-white' }}">{{ $label }}</a>
            @endforeach
        </div>
        <span class="inline-flex items-center gap-1.5 text-[13px] leading-[18px] text-muted"><x-ikon nama="bantuan-jam" :ukuran="16" /> Terakhir diperbarui {{ $diperbarui }}</span>
    </div>

    @if ($contoh)
        <div role="note" class="flex items-start gap-2.5 rounded-xl border border-[#F5D58A] bg-warning-50 p-3.5 text-[13px] leading-5 text-[#7A5200]">
            <x-ikon nama="peringatan" :ukuran="18" class="mt-0.5" />Teks contoh untuk prototipe. Ganti dengan dokumen hukum resmi sebelum diterbitkan.
        </div>
    @endif

    <div class="grid items-start gap-5 grid-cols-[minmax(0,1fr)] lg:grid-cols-[240px_minmax(0,1fr)]">
        <nav aria-label="Daftar isi" class="kartu flex flex-col gap-1 p-3 lg:sticky lg:top-4">
            <div class="px-2 pb-1 text-[11px] leading-[14px] font-semibold tracking-[0.06em] text-subtle uppercase">Isi dokumen</div>
            @foreach ($bagian as $b)
                <a href="#{{ $b['anchor'] }}" x-on:click="aktif = '{{ $b['anchor'] }}'; kunci = Date.now() + 900"
                   x-bind:aria-current="aktif === '{{ $b['anchor'] }}' ? 'true' : null"
                   x-bind:class="aktif === '{{ $b['anchor'] }}' ? 'bg-brand-100 text-brand font-bold' : 'text-body font-semibold'"
                   class="flex min-h-11 items-center gap-2.5 rounded-[14px] px-3 py-1.5 text-[13px] leading-[18px]">
                    <span x-bind:class="aktif === '{{ $b['anchor'] }}' ? 'bg-brand text-white' : 'bg-canvas text-nav'" class="flex size-6 shrink-0 items-center justify-center rounded-xl text-xs font-bold">{{ $b['no'] }}</span>
                    <span>{{ $b['judul'] }}</span>
                </a>
            @endforeach
        </nav>

        <div class="flex min-w-0 flex-col gap-4">
            <article aria-label="Isi dokumen" class="kartu flex flex-col gap-6 p-5 sm:p-8">
                <header><h2 class="m-0 text-2xl leading-8 font-bold tracking-tight text-ink">{{ $judul }}</h2></header>
                @foreach ($bagian as $b)
                    <section id="{{ $b['anchor'] }}" class="scroll-mt-24">
                        <h3 class="m-0 mb-2 text-base leading-6 font-semibold text-ink">{{ $b['no'] }}. {{ $b['judul'] }}</h3>
                        <div class="flex flex-col gap-2 text-sm leading-6 text-body">{{ \App\Support\TeksBantuan::html($b['isi']) }}</div>
                    </section>
                @endforeach
            </article>
            @if ($tautanHubungi)
                <div class="kartu flex flex-wrap items-center justify-between gap-3 p-4">
                    <div class="text-sm leading-5 font-semibold text-ink">Ada pertanyaan? Tim kami siap membantu.</div>
                    <a href="{{ $tautanHubungi }}" wire:navigate class="btn btn-primary">Hubungi layanan pelanggan</a>
                </div>
            @endif
        </div>
    </div>
</div>
