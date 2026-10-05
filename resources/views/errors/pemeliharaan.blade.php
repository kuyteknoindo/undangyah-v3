@php
    $info = $info ?? [];
    $mulai = ! empty($info['mulai']) ? \Illuminate\Support\Carbon::parse($info['mulai'])->translatedFormat('j F Y, H.i').' WIB' : null;
@endphp
<x-layouts.sederhana judul="Pemeliharaan">
    <span class="flex size-16 items-center justify-center rounded-full bg-warning-50 text-warning"><x-ikon nama="akun" :ukuran="32" /></span>
    <h1 class="m-0 text-2xl leading-8 font-semibold tracking-[-0.02em] text-ink">Sedang dalam pemeliharaan</h1>
    <p class="m-0 max-w-md text-sm leading-6 text-muted">{{ $info['pesan'] ?? 'Kami sedang memperbarui layanan agar lebih baik. Mohon coba lagi sebentar lagi.' }}</p>
    <div class="kartu w-full max-w-md divide-y divide-line text-left text-[13px] leading-5">
        <div class="flex justify-between gap-3 px-4 py-3"><span class="text-muted">Status</span><span class="font-semibold text-ink">Dalam pemeliharaan</span></div>
        @if ($mulai)<div class="flex justify-between gap-3 px-4 py-3"><span class="text-muted">Mulai</span><span class="font-semibold text-ink">{{ $mulai }}</span></div>@endif
        @if (! empty($info['eta']))<div class="flex justify-between gap-3 px-4 py-3"><span class="text-muted">Perkiraan selesai</span><span class="font-semibold text-ink">{{ $info['eta'] }}</span></div>@endif
        <div class="flex justify-between gap-3 px-4 py-3"><span class="text-muted">Terakhir diperiksa</span><span class="font-semibold text-ink">{{ now()->translatedFormat('j F Y, H.i') }} WIB</span></div>
    </div>
    <a href="{{ url()->current() }}" class="btn btn-primary">Cek lagi</a>
    <p class="m-0 text-xs text-subtle">Undangan yang sudah dibagikan tetap bisa dibuka tamu.</p>
</x-layouts.sederhana>
