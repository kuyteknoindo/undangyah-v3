<div class="bl-root" style="max-width:1200px;margin:0 auto;display:flex;flex-direction:column;gap:24px"
     x-data="{ daftar: @js(array_column($bagian, 'anchor')), aktif: @js($bagian[0]['anchor']), kunci: 0,
        pantau() {
            if (Date.now() < this.kunci) return;
            let sekarang = this.daftar[0];
            this.daftar.forEach((id) => { const el = document.getElementById(id); if (el && el.getBoundingClientRect().top <= 140) sekarang = id; });
            this.aktif = sekarang;
        },
        gaya(id) { const on = this.aktif === id; return 'display:flex;align-items:center;gap:10px;min-height:44px;padding:6px 12px;border-radius:14px;font-size:13px;line-height:18px;font-weight:' + (on ? 700 : 600) + ';background:' + (on ? '#E3ECFD' : 'transparent') + ';color:' + (on ? '#1D4ED8' : '#262A31'); },
        gayaNo(id) { const on = this.aktif === id; return 'flex:none;width:24px;height:24px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;background:' + (on ? '#1D4ED8' : '#F4F5F7') + ';color:' + (on ? '#FFFFFF' : '#454B55'); } }"
     x-on:scroll.window.passive="pantau()">
    <x-billing.gaya />
    <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:16px">
        <div role="group" aria-label="Pilih dokumen" style="display:inline-flex;gap:6px;padding:6px;background:#FFFFFF;border:2px solid #E2E5E9;border-radius:20px">
            @foreach (['syarat' => 'Syarat & Ketentuan', 'privasi' => 'Kebijakan Privasi'] as $kunci => $label)
                <a href="{{ $tautan[$kunci] }}" wire:navigate @if ($dokumen === $kunci) aria-current="page" @endif
                   style="display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:0 20px;border-radius:14px;box-sizing:border-box;font-size:14px;line-height:19px;font-weight:700;text-align:center;background:{{ $dokumen === $kunci ? '#1D4ED8' : 'transparent' }};color:{{ $dokumen === $kunci ? '#FFFFFF' : '#454B55' }}">{{ $label }}</a>
            @endforeach
        </div>
        <span class="bl-chip" style="background:#FFFFFF;border:2px solid #E2E5E9;color:#454B55;padding:8px 14px"><x-ikon nama="bantuan-jam" :ukuran="16" /> Terakhir diperbarui {{ $diperbarui }}</span>
    </div>

    @if ($contoh)
        <div role="note" style="display:flex;align-items:flex-start;gap:10px;background:#FFF4CC;color:#7A5200;border-radius:16px;padding:12px 16px;font-size:13px;line-height:19px;font-weight:600"><span aria-hidden="true" style="flex:none;display:flex;margin-top:1px"><x-ikon nama="peringatan" :ukuran="18" /></span>Teks contoh untuk prototipe. Ganti dengan dokumen hukum resmi sebelum diterbitkan.</div>
    @endif

    <div style="display:flex;flex-wrap:wrap;gap:24px;align-items:flex-start">
        <nav aria-label="Daftar isi" style="flex:0 0 260px;position:sticky;top:24px;align-self:flex-start;box-sizing:border-box;background:#FFFFFF;border:2px solid #E2E5E9;border-radius:24px;padding:18px 12px;display:flex;flex-direction:column;gap:4px">
            <div style="padding:0 12px 8px;font-size:11px;line-height:14px;font-weight:700;letter-spacing:.08em;color:#666C76">ISI DOKUMEN</div>
            @foreach ($bagian as $b)
                <a href="#{{ $b['anchor'] }}" x-on:click="aktif = '{{ $b['anchor'] }}'; kunci = Date.now() + 900"
                   x-bind:aria-current="aktif === '{{ $b['anchor'] }}' ? 'true' : 'false'"
                   x-bind:style="gaya('{{ $b['anchor'] }}')"
                   style="display:flex;align-items:center;gap:10px;min-height:44px;padding:6px 12px;border-radius:14px;font-size:13px;line-height:18px;font-weight:{{ $loop->first ? 700 : 600 }};background:{{ $loop->first ? '#E3ECFD' : 'transparent' }};color:{{ $loop->first ? '#1D4ED8' : '#262A31' }}">
                    <span aria-hidden="true" x-bind:style="gayaNo('{{ $b['anchor'] }}')" style="flex:none;width:24px;height:24px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;background:{{ $loop->first ? '#1D4ED8' : '#F4F5F7' }};color:{{ $loop->first ? '#FFFFFF' : '#454B55' }}">{{ $b['no'] }}</span>
                    <span style="flex:1;min-width:0">{{ $b['judul'] }}</span>
                </a>
            @endforeach
        </nav>

        <div style="flex:1 1 420px;min-width:0;display:flex;flex-direction:column;gap:16px">
            <article aria-label="Isi dokumen" class="bl-card bh-artikel" style="padding:36px 40px;display:flex;flex-direction:column;gap:32px">
                <header style="display:flex;flex-direction:column;gap:6px;border-bottom:2px solid #E2E5E9;padding-bottom:16px"><h2 style="margin:0;font-family:ui-serif,'New York',Georgia,serif;font-weight:600;font-size:30px;line-height:36px">{{ $judul }}</h2></header>
                @foreach ($bagian as $b)
                    <section id="{{ $b['anchor'] }}" style="scroll-margin-top:24px;display:flex;flex-direction:column;gap:8px">
                        <h3 style="margin:0;font-size:18px;line-height:24px;font-weight:700">{{ $b['no'] }}. {{ $b['judul'] }}</h3>
                        <div style="margin:0;font-size:15px;line-height:25px;font-weight:500;color:#454B55;max-width:680px;display:flex;flex-direction:column;gap:8px">{{ \App\Support\TeksBantuan::html($b['isi']) }}</div>
                    </section>
                @endforeach
            </article>
            @if ($tautanHubungi)
                <div class="bl-card" style="padding:18px 24px;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px">
                    <div style="font-size:14px;line-height:20px;font-weight:600">Ada pertanyaan? Tim kami siap membantu.</div>
                    <a href="{{ $tautanHubungi }}" wire:navigate class="bl-btn bl-btn-s">Hubungi layanan pelanggan</a>
                </div>
            @endif
        </div>
    </div>
</div>
