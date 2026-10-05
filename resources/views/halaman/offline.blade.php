<x-layouts.sederhana judul="Offline">
    <div x-data="{ online: navigator.onLine, dicoba: false, cek() { this.dicoba = true; this.online = navigator.onLine; } }"
         x-on:online.window="online = true" x-on:offline.window="online = false" class="flex flex-col items-center gap-4">
        <span class="flex size-16 items-center justify-center rounded-full bg-gray-100 text-muted"><x-ikon nama="bantuan" :ukuran="32" /></span>
        <h1 class="m-0 text-2xl leading-8 font-semibold tracking-[-0.02em] text-ink" x-text="online ? 'Kamu sudah online lagi' : 'Kamu sedang offline'">Kamu sedang offline</h1>
        <p class="m-0 max-w-md text-sm leading-6 text-muted" x-text="online ? 'Koneksi tersambung kembali. Kamu bisa melanjutkan.' : (dicoba ? 'Masih belum tersambung. Coba lagi sebentar lagi.' : 'Sambungan internet terputus, jadi beberapa fitur tidak bisa dipakai untuk sementara.')">Sambungan internet terputus, jadi beberapa fitur tidak bisa dipakai untuk sementara.</p>
        <div class="kartu w-full max-w-md space-y-2 p-4 text-left text-[13px] leading-5">
            <div class="flex gap-2.5"><span class="mt-2 size-1.5 shrink-0 rounded-full bg-brand"></span>Cek Wi-Fi atau paket data, lalu matikan mode pesawat.</div>
            <div class="flex gap-2.5"><span class="mt-2 size-1.5 shrink-0 rounded-full bg-brand"></span>Draf di Edit Undangan tersimpan otomatis, jadi isianmu tidak hilang saat koneksi putus.</div>
            <div class="flex gap-2.5"><span class="mt-2 size-1.5 shrink-0 rounded-full bg-brand"></span>Undangan yang sudah dibagikan tetap bisa dibuka tamu.</div>
        </div>
        <button type="button" class="btn btn-primary" x-on:click="cek()">Coba lagi</button>
        <a x-show="online" x-cloak href="{{ route('beranda') }}" class="btn btn-secondary">Ke Beranda</a>
    </div>
</x-layouts.sederhana>
