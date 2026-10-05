<div class="mx-auto flex max-w-5xl flex-col gap-6">
    <section class="rounded-2xl border border-line bg-white p-6 shadow-[0_1px_2px_rgba(16,24,40,0.04)] sm:p-8">
        <h2 class="text-lg font-semibold tracking-tight text-ink">Belum ada undangan</h2>
        <p class="mt-1 max-w-xl text-sm leading-6 text-muted">Mulai dari memilih tema, isi data acara, lalu bagikan ke tamu lewat tautan pribadi.</p>
        <a href="{{ route('buat-undangan') }}" wire:navigate class="btn btn-primary mt-5"><x-ikon nama="tambah" :ukuran="16" /> Buat undangan pertama</a>
    </section>

    <section aria-labelledby="langkah-judul">
        <h2 id="langkah-judul" class="mb-3 text-sm font-semibold text-ink">Tiga langkah mulai</h2>
        <div class="grid gap-3 sm:grid-cols-3">
            @foreach ([
                ['no' => 1, 'judul' => 'Pilih tema', 'isi' => 'Mulai dari koleksi template siap pakai.', 'route' => 'template'],
                ['no' => 2, 'judul' => 'Isi data acara', 'isi' => 'Nama, tanggal, lokasi, galeri foto.', 'route' => 'buat-undangan'],
                ['no' => 3, 'judul' => 'Bagikan ke tamu', 'isi' => 'Tautan pribadi per tamu lewat WhatsApp.', 'route' => 'bagikan'],
            ] as $l)
                <a href="{{ route($l['route']) }}" wire:navigate class="flex flex-col gap-1 rounded-xl border border-line bg-white p-4 transition hover:border-brand-200 hover:bg-brand-50/40">
                    <span class="text-xs font-semibold text-brand">Langkah {{ $l['no'] }}</span>
                    <span class="text-sm font-semibold text-ink">{{ $l['judul'] }}</span>
                    <span class="text-[13px] leading-5 text-muted">{{ $l['isi'] }}</span>
                </a>
            @endforeach
        </div>
    </section>
</div>
