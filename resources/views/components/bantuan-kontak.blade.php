{{-- Kartu kontak layanan. Hanya kontak yang diisi pemilik (Pengaturan `kontak`) yang tampil. --}}
@props(['kontak', 'judul' => 'Butuh bantuan lain?', 'hubungi' => null])
<section class="kartu flex flex-col gap-3 p-5" aria-label="{{ $judul }}">
    <div>
        <h2 class="h2">{{ $judul }}</h2>
        @if ($kontak['balasan'])<div class="sub mt-0.5">Tim kami membalas dalam {{ $kontak['balasan'] }} pada jam layanan.</div>@endif
    </div>
    @if ($hubungi)
        <a href="{{ $hubungi }}" wire:navigate class="btn btn-primary"><x-ikon nama="bantuan-pesan" :ukuran="16" /> Kirim pesan</a>
    @endif
    @if ($kontak['whatsapp'])
        <a href="{{ $kontak['whatsapp'] }}" target="_blank" rel="noopener noreferrer" class="btn btn-secondary"><x-ikon nama="bantuan-wa" :ukuran="16" /> Chat WhatsApp</a>
    @endif
    @if ($kontak['email'])
        <a href="mailto:{{ $kontak['email'] }}" class="btn btn-secondary min-w-0"><x-ikon nama="bantuan-surel" :ukuran="16" /> <span class="truncate">{{ $kontak['email'] }}</span></a>
    @endif
    @if ($kontak['jam_layanan'])
        <div class="flex flex-col gap-1.5 border-t border-line pt-3">
            <div class="flex items-center gap-2 text-[13px] leading-[18px] text-muted"><x-ikon nama="bantuan-jam" :ukuran="16" /> Jam layanan {{ $kontak['jam_layanan'] }}</div>
            @if ($kontak['buka'] !== null)
                <div role="status" class="flex items-center gap-2 text-xs leading-4 font-bold {{ $kontak['buka'] ? 'text-[#3C7A00]' : 'text-muted' }}">
                    <span class="size-2 rounded-full {{ $kontak['buka'] ? 'bg-[#58A700]' : 'bg-gray-400' }}"></span>{{ $kontak['buka'] ? 'Sedang buka sekarang' : 'Sedang tutup' }}
                </div>
            @endif
        </div>
    @endif
</section>
