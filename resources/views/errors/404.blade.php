<x-layouts.sederhana judul="Halaman tidak ditemukan">
    <span class="flex size-16 items-center justify-center rounded-full bg-brand-50 text-brand"><x-ikon nama="bantuan" :ukuran="32" /></span>
    <div class="text-5xl leading-none font-bold tracking-tight text-brand">404</div>
    <h1 class="m-0 text-2xl leading-8 font-semibold tracking-[-0.02em] text-ink">Halaman tidak ditemukan</h1>
    <p class="m-0 max-w-md text-sm leading-6 text-muted">Alamat yang kamu buka tidak ada atau sudah dipindahkan. Periksa lagi alamatnya, cari di Pusat Bantuan, atau kembali ke Beranda.</p>
    <form action="{{ route('bantuan') }}" method="GET" class="mt-2 flex w-full max-w-md flex-col gap-1.5 text-left">
        <label for="nf-q" class="lbl">Cari di Pusat Bantuan</label>
        <div class="flex gap-2">
            <input id="nf-q" name="q" type="search" class="inp" placeholder="Cari pertanyaan...">
            <button type="submit" class="btn btn-primary">Cari</button>
        </div>
    </form>
    <div class="mt-2 flex flex-wrap justify-center gap-2">
        <a href="{{ route('beranda') }}" class="btn btn-secondary"><x-ikon nama="beranda" :ukuran="16" /> Beranda</a>
        <a href="{{ route('bantuan') }}" class="btn btn-secondary">Pusat Bantuan</a>
    </div>
    <a href="{{ route('hubungi') }}" class="mt-1 inline-flex min-h-6 items-center text-[13px] font-semibold text-brand hover:underline">Laporkan masalah ini</a>
</x-layouts.sederhana>
