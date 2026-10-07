<div class="bl-root" style="max-width:1200px;margin:0 auto;display:flex;flex-direction:column;gap:24px">
    <x-billing.gaya />
    <section aria-label="Cari bantuan" style="position:relative;overflow:hidden;box-sizing:border-box;background:#E3ECFD;border:2px solid #BFD3FF;border-radius:24px;padding:36px 40px;display:flex;flex-direction:column;gap:18px" class="bh-hero">
        <div aria-hidden="true" style="position:absolute;top:-110px;right:-70px;width:300px;height:300px;border-radius:150px;border:2px solid #BFD3FF"></div>
        <div aria-hidden="true" style="position:absolute;top:-60px;right:-20px;width:190px;height:190px;border-radius:95px;border:2px solid #BFD3FF"></div>
        <div style="position:relative;display:flex;flex-direction:column;gap:6px;max-width:760px">
            <h2 style="margin:0;font-family:ui-serif,'New York',Georgia,serif;font-weight:600;font-size:32px;line-height:38px;color:#1A3FA8">Ada yang bisa kami bantu?</h2>
            <div style="font-size:15px;line-height:22px;font-weight:500;color:#454B55">Cari jawaban dari pertanyaan yang paling sering ditanyakan, atau pilih kategori di bawah.</div>
        </div>
        <div class="bl-fld" style="position:relative;max-width:640px;display:flex;align-items:center;gap:10px;min-height:56px;padding:0 6px 0 18px;box-sizing:border-box;background:#FFFFFF;border:2px solid #BFD3FF;border-radius:18px">
            <span aria-hidden="true" style="flex:none;display:flex;color:#666C76"><x-ikon nama="bantuan-cari" :ukuran="20" /></span>
            <label for="hp-cari" class="sr-only">Cari pertanyaan</label>
            <input id="hp-cari" type="text" autocomplete="off" maxlength="120" placeholder="Cari pertanyaan..." wire:model.live.debounce.300ms="cari" style="flex:1;min-width:0;height:48px;border:0;background:transparent;font-size:16px;line-height:22px;outline:none;box-shadow:none">
            @if ($cari !== '')
                <button type="button" wire:click="aturUlang" aria-label="Hapus pencarian" style="flex:none;width:44px;height:44px;border-radius:22px;display:flex;align-items:center;justify-content:center;color:#666C76"><x-ikon nama="tutup" :ukuran="18" style="stroke-width:2.5" /></button>
            @endif
        </div>
    </section>

    <div style="display:flex;flex-wrap:wrap;gap:24px;align-items:flex-start">
        <div style="flex:1 1 420px;min-width:0;display:flex;flex-direction:column;gap:16px">
            <section aria-label="Kategori bantuan" style="display:flex;flex-wrap:wrap;gap:8px">
                @foreach (['semua' => 'Semua'] + \App\Models\BantuanArtikel::KATEGORI as $kunci => $label)
                    @php $aktif = $kategoriAktif === $kunci; @endphp
                    <button type="button" wire:click="pilihKategori('{{ $kunci }}')" aria-pressed="{{ $aktif ? 'true' : 'false' }}" aria-label="{{ $label }}, {{ $jumlah[$kunci] }} pertanyaan"
                            style="box-sizing:border-box;display:inline-flex;align-items:center;gap:10px;min-height:44px;padding:0 10px 0 16px;border-radius:999px;font-size:14px;line-height:19px;font-weight:700;border:2px solid {{ $aktif ? '#1D4ED8' : '#E2E5E9' }};background:{{ $aktif ? '#E3ECFD' : '#FFFFFF' }};color:{{ $aktif ? '#1D4ED8' : '#262A31' }}">
                        <span>{{ $label }}</span>
                        <span aria-hidden="true" style="min-width:24px;height:24px;padding:0 7px;box-sizing:border-box;border-radius:12px;display:inline-flex;align-items:center;justify-content:center;font-size:12px;line-height:16px;font-weight:700;background:{{ $aktif ? '#1D4ED8' : '#F4F5F7' }};color:{{ $aktif ? '#FFFFFF' : '#454B55' }}">{{ $jumlah[$kunci] }}</span>
                    </button>
                @endforeach
            </section>

            <div role="status" style="font-size:13px;line-height:18px;font-weight:600;color:#666C76">{{ $ringkasan }}</div>

            @if ($artikel->isEmpty())
                <div class="bl-card" role="status" style="padding:36px 28px;display:flex;flex-direction:column;align-items:center;gap:12px;text-align:center">
                    <span aria-hidden="true" style="width:72px;height:72px;border-radius:36px;background:#E3ECFD;color:#1D4ED8;display:flex;align-items:center;justify-content:center"><x-ikon nama="bantuan-cari" :ukuran="30" style="stroke-width:1.75" /></span>
                    <div style="font-size:18px;line-height:24px;font-weight:700">Jawaban belum ditemukan</div>
                    <div style="max-width:420px;font-size:14px;line-height:21px;font-weight:500;color:#666C76">Coba kata kunci lain, atau pilih kategori yang berbeda. Kalau masih belum ketemu, tim kami siap membantu.</div>
                    <div style="display:flex;flex-wrap:wrap;justify-content:center;gap:12px;margin-top:8px">
                        <button type="button" wire:click="aturUlang" class="bl-btn bl-btn-s">Reset pencarian</button>
                        @if ($tautanHubungi)<a href="{{ $tautanHubungi }}" wire:navigate class="bl-btn bl-btn-p">Hubungi kami</a>@endif
                    </div>
                </div>
            @else
                <section aria-label="Pertanyaan umum" style="display:flex;flex-direction:column;gap:12px">
                    @foreach ($artikel as $a)
                        @php
                            $buka = $terbuka === $a->id;
                            $pilihan = $suara->has($a->id) ? ($suara[$a->id] ? 'ya' : 'tidak') : null;
                            $href = $tautan($a);
                        @endphp
                        <div wire:key="artikel-{{ $a->id }}" class="bl-card" style="overflow:hidden{{ $buka ? ';border-color:#BFD3FF' : '' }}">
                            <h3 style="margin:0">
                                <button type="button" wire:click="ubahBuka({{ $a->id }}, {{ $buka ? 'true' : 'false' }})" aria-expanded="{{ $buka ? 'true' : 'false' }}" aria-controls="jawab-{{ $a->id }}"
                                        style="width:100%;box-sizing:border-box;display:flex;align-items:center;gap:16px;min-height:64px;padding:12px 22px;border-radius:22px;font-size:16px;line-height:22px;font-weight:700">
                                    <span style="flex:1;min-width:0">{{ $a->judul }}</span>
                                    <span aria-hidden="true" style="flex:none;display:flex;color:#666C76;transition:transform 220ms;transform:{{ $buka ? 'rotate(180deg)' : 'none' }}"><x-ikon nama="panah-bawah" :ukuran="20" style="stroke-width:2.25" /></span>
                                </button>
                            </h3>
                            @if ($buka)
                                <div id="jawab-{{ $a->id }}" style="padding:0 22px 20px;display:flex;flex-direction:column;gap:12px">
                                    <div style="font-size:14px;line-height:22px;font-weight:500;color:#454B55;display:flex;flex-direction:column;gap:8px">{{ \App\Support\TeksBantuan::html($a->isi) }}</div>
                                    @if ($href)
                                        <div><a href="{{ $href }}" wire:navigate style="min-height:44px;display:inline-flex;align-items:center;gap:6px;font-size:13px;line-height:18px;font-weight:700;color:#1D4ED8">{{ $a->rute_label ?: 'Buka halaman' }} <x-ikon nama="panah-kanan" :ukuran="16" style="stroke-width:2.5" /></a></div>
                                    @endif
                                    <div style="border-top:2px solid #E2E5E9;padding-top:12px;display:flex;flex-wrap:wrap;align-items:center;gap:10px 12px">
                                        @if ($pilihan === null)
                                            <span style="font-size:13px;line-height:18px;font-weight:700;margin-right:4px">Apakah ini membantu?</span>
                                            <button type="button" wire:click="suara({{ $a->id }}, 'ya')" class="bl-btn bl-btn-s" style="min-height:40px;padding:0 16px">Ya</button>
                                            <button type="button" wire:click="suara({{ $a->id }}, 'tidak')" class="bl-btn bl-btn-s" style="min-height:40px;padding:0 16px">Tidak</button>
                                        @else
                                            <span role="status" style="display:inline-flex;align-items:center;gap:8px;font-size:13px;line-height:18px;font-weight:700;color:#3C7A00"><x-ikon nama="centang" :ukuran="16" style="stroke-width:3" />{{ $pilihan === 'ya' ? 'Terima kasih, senang bisa membantu.' : 'Terima kasih masukannya.' }}</span>
                                            @if ($pilihan === 'tidak' && $tautanHubungi)<a href="{{ $tautanHubungi }}" wire:navigate style="min-height:44px;display:inline-flex;align-items:center;gap:6px;font-size:13px;line-height:18px;font-weight:700;color:#1D4ED8">Hubungi layanan</a>@endif
                                            <button type="button" wire:click="suara({{ $a->id }}, 'batal')" style="min-height:44px;padding:0 6px;font-size:12px;line-height:16px;font-weight:700;color:#666C76;text-decoration:underline">Ubah jawaban</button>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </section>
            @endif
        </div>

        <aside aria-label="Butuh bantuan lain" style="flex:0 1 320px;min-width:0;position:sticky;top:24px;display:flex;flex-direction:column;gap:16px">
            <x-bantuan-kontak :kontak="$kontak" :hubungi="$tautanHubungi" />
            @if ($tautanSyarat)
                <a href="{{ $tautanSyarat }}" wire:navigate class="bl-card bl-rowh" style="padding:16px 20px;display:flex;align-items:center;gap:12px;min-height:44px">
                    <span aria-hidden="true" style="flex:none;width:36px;height:36px;border-radius:18px;background:#F4F5F7;display:flex;align-items:center;justify-content:center;color:#454B55"><x-ikon nama="bantuan-dokumen" :ukuran="18" /></span>
                    <span style="flex:1;min-width:0;font-size:14px;line-height:19px;font-weight:700">Syarat &amp; Privasi</span>
                    <span aria-hidden="true" style="flex:none;display:flex;color:#AEB3BB"><x-ikon nama="panah-kanan" :ukuran="18" /></span>
                </a>
            @endif
        </aside>
    </div>
</div>
