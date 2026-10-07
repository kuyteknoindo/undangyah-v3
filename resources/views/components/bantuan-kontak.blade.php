{{-- Kartu kontak layanan (gaya papan). Hanya kontak yang diisi pemilik (Pengaturan `kontak`) yang tampil.
     varian "bantuan" = papan Pusat Bantuan (Kirim pesan + WhatsApp + email + jam); "hubungi" = papan Hubungi Layanan (WhatsApp utama + status buka). --}}
@props(['kontak', 'judul' => 'Butuh bantuan lain?', 'hubungi' => null, 'varian' => 'bantuan'])
@php $hub = $varian === 'hubungi'; @endphp
<section class="bl-card" aria-label="{{ $hub ? 'Kontak cepat' : $judul }}" style="padding:24px;display:flex;flex-direction:column;gap:{{ $hub ? 14 : 16 }}px">
    <div style="display:flex;flex-direction:column;gap:4px">
        <h2 style="margin:0;font-size:18px;line-height:24px;font-weight:700">{{ $judul }}</h2>
        @if ($kontak['balasan'])
            <div style="font-size:13px;line-height:19px;font-weight:500;color:#666C76">{{ $hub ? 'Balasan' : 'Tim kami membalas' }} dalam {{ $kontak['balasan'] }} pada jam layanan.</div>
        @endif
    </div>
    @if (! $hub && $hubungi)
        <a href="{{ $hubungi }}" wire:navigate class="bl-btn bl-btn-p" style="min-height:48px"><x-ikon nama="bantuan-pesan" :ukuran="18" /> Kirim pesan</a>
    @endif
    @if ($kontak['whatsapp'])
        <a href="{{ $kontak['whatsapp'] }}" target="_blank" rel="noopener noreferrer" class="bl-btn {{ $hub ? 'bl-btn-p' : 'bl-btn-s' }}" @if ($hub) style="min-height:48px" @endif><x-ikon nama="bantuan-wa" :ukuran="18" /> Chat WhatsApp</a>
    @endif
    @if ($kontak['email'])
        <a href="mailto:{{ $kontak['email'] }}" class="bl-btn bl-btn-s" style="text-transform:none;letter-spacing:0;min-width:0"><x-ikon nama="bantuan-surel" :ukuran="18" /> <span style="overflow:hidden;text-overflow:ellipsis">{{ $kontak['email'] }}</span></a>
    @endif
    @if ($kontak['jam_layanan'])
        <div @if ($hub) style="display:flex;flex-direction:column;gap:8px;border-top:2px solid #E2E5E9;padding-top:14px" @else style="display:contents" @endif>
            <div style="display:flex;align-items:center;gap:10px;font-size:13px;line-height:18px;font-weight:600;color:#454B55"><span aria-hidden="true" style="flex:none;display:flex;color:#666C76"><x-ikon nama="bantuan-jam" :ukuran="18" /></span>{{ $hub ? '' : 'Jam layanan ' }}{{ $kontak['jam_layanan'] }}</div>
            @if ($hub && $kontak['buka'] !== null)
                <div role="status" style="display:flex;align-items:center;gap:8px;font-size:12px;line-height:16px;font-weight:700;color:{{ $kontak['buka'] ? '#3C7A00' : '#666C76' }}"><span aria-hidden="true" style="width:8px;height:8px;border-radius:4px;background:currentColor"></span>{{ $kontak['buka'] ? 'Sedang buka sekarang' : 'Sedang tutup' }}</div>
            @endif
        </div>
    @endif
</section>
