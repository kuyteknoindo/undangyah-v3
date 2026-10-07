@php
    $waktu = function ($t) {
        $detik = $t->diffInSeconds(now(), true);
        if ($detik >= 86400) {
            return $t->locale('id')->translatedFormat('j M Y');
        }

        return $detik < 60 ? 'baru saja' : ($detik < 3600 ? floor($detik / 60).' mnt lalu' : floor($detik / 3600).' jam lalu');
    };
    $kolom = fn (bool $salah) => 'height:48px;padding:0 14px;border-radius:14px;border:2px solid '.($salah ? '#EA2B2B' : '#AEB3BB').';background:#FFFFFF;font-size:14px;line-height:20px;box-sizing:border-box;width:100%';
@endphp
<div class="bl-root" style="max-width:1200px;margin:0 auto;display:flex;flex-wrap:wrap;gap:24px;align-items:flex-start">
    <x-billing.gaya />
    <div style="flex:1 1 440px;min-width:0;display:flex;flex-direction:column;gap:16px">
        @if ($baru)
            <section class="bl-card" aria-live="polite" aria-labelledby="hl-terkirim" style="padding:36px 32px;display:flex;flex-direction:column;align-items:flex-start;gap:16px" x-data="{ info: '' , t: null,
                salin(nomor) { const ok = () => this.kabar('Nomor tiket disalin.'); try { navigator.clipboard.writeText(nomor).then(ok, () => this.kabar('Salin manual: ' + nomor)); } catch (e) { this.kabar('Salin manual: ' + nomor); } },
                kabar(m) { this.info = m; clearTimeout(this.t); this.t = setTimeout(() => this.info = '', 2400); } }">
                <span aria-hidden="true" style="width:64px;height:64px;border-radius:32px;background:#D7FFB8;color:#3C7A00;display:flex;align-items:center;justify-content:center"><x-ikon nama="centang" :ukuran="30" style="stroke-width:3" /></span>
                <h2 id="hl-terkirim" style="margin:0;font-family:ui-serif,'New York',Georgia,serif;font-weight:600;font-size:28px;line-height:34px">Pesan terkirim</h2>
                <div style="font-size:14px;line-height:22px;font-weight:500;color:#454B55;max-width:520px">Nomor tiket kamu <b style="font-size:16px;color:#262A31">{{ $baru->nomor }}</b>. Kami akan membalas lewat email dan WhatsApp. Simpan nomor ini bila perlu menanyakan kelanjutannya.</div>
                <div style="width:100%;box-sizing:border-box;background:#F4F5F7;border-radius:16px;padding:14px 16px;display:flex;flex-direction:column;gap:4px"><div style="font-size:11px;line-height:14px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#666C76">{{ $baru->labelKategori() }}</div><div style="font-size:14px;line-height:20px;font-weight:700;overflow-wrap:anywhere">{{ $baru->subjek }}</div></div>
                <div style="display:flex;flex-wrap:wrap;gap:12px">
                    <button type="button" class="bl-btn bl-btn-s" x-on:click="salin(@js($baru->nomor))"><x-ikon nama="bantuan-salin" :ukuran="16" /> Salin nomor</button>
                    <button type="button" wire:click="pesanLain" class="bl-btn bl-btn-p">Kirim pesan lain</button>
                    @if ($tautanBantuan)<a href="{{ $tautanBantuan }}" wire:navigate class="bl-btn bl-btn-s">Pusat Bantuan</a>@endif
                </div>
                <div role="status" x-show="info" x-cloak x-text="info" style="font-size:13px;line-height:18px;font-weight:700;color:#3C7A00"></div>
            </section>
        @else
            <section class="bl-card" aria-label="Formulir pesan" style="padding:28px 32px;display:flex;flex-direction:column;gap:20px">
                <div style="display:flex;flex-direction:column;gap:4px"><h2 style="margin:0;font-size:20px;line-height:26px;font-weight:700">Kirim Pesan</h2><div style="font-size:14px;line-height:20px;font-weight:500;color:#666C76">Ceritakan kendalamu, kami bantu carikan solusinya.</div></div>

                @error('form')
                    <div role="alert" style="display:flex;align-items:flex-start;gap:10px;background:#FFE0E0;color:#B02A2A;border-radius:14px;padding:12px 14px;font-size:13px;line-height:18px;font-weight:600"><span aria-hidden="true" style="flex:none;display:flex;margin-top:1px"><x-ikon nama="peringatan" :ukuran="16" /></span>{{ $message }}</div>
                @enderror

                <form wire:submit="kirim" novalidate style="display:flex;flex-direction:column;gap:20px">
                    <div role="group" aria-labelledby="hl-kat-l" style="display:flex;flex-direction:column;gap:8px">
                        <div id="hl-kat-l" style="font-size:13px;line-height:18px;font-weight:700">Kategori</div>
                        <div style="display:flex;flex-wrap:wrap;gap:8px">
                            @foreach ($kategoriDaftar as $kunci => $label)
                                @php $on = $kategori === $kunci; @endphp
                                <button type="button" wire:click="pilihKategori('{{ $kunci }}')" aria-pressed="{{ $on ? 'true' : 'false' }}"
                                        style="min-height:44px;padding:0 14px;border-radius:999px;font-size:13px;line-height:18px;font-weight:700;box-sizing:border-box;border:2px solid {{ $on ? '#1D4ED8' : ($errors->has('kategori') ? '#EA2B2B' : '#E2E5E9') }};background:{{ $on ? '#E3ECFD' : '#FFFFFF' }};color:{{ $on ? '#1D4ED8' : '#262A31' }}">{{ $label }}</button>
                            @endforeach
                        </div>
                        @error('kategori')<div role="alert" style="font-size:12px;line-height:16px;font-weight:700;color:#EA2B2B">{{ $message }}</div>@enderror
                    </div>

                    <div style="display:flex;flex-direction:column;gap:8px">
                        <label for="hl-inv" style="font-size:13px;line-height:18px;font-weight:700">Undangan terkait <span style="font-weight:500;color:#666C76">(opsional)</span></label>
                        <select id="hl-inv" wire:model="undanganId" style="height:48px;padding:0 14px;border-radius:14px;border:2px solid #AEB3BB;background:#FFFFFF;font-size:14px;line-height:20px;box-sizing:border-box">
                            <option value="">Tidak terkait undangan tertentu</option>
                            @foreach ($undanganDaftar as $u)
                                <option value="{{ $u->id }}">{{ $u->judul }} ({{ $u->status === 'terbit' ? 'aktif' : 'draf' }})</option>
                            @endforeach
                        </select>
                        @error('undanganId')<div role="alert" style="font-size:12px;line-height:16px;font-weight:700;color:#EA2B2B">{{ $message }}</div>@enderror
                    </div>

                    <div style="display:flex;flex-direction:column;gap:8px">
                        <label for="hl-sub" style="font-size:13px;line-height:18px;font-weight:700">Subjek</label>
                        <input id="hl-sub" type="text" wire:model="subjek" maxlength="100" placeholder="Ringkas masalahmu" aria-invalid="{{ $errors->has('subjek') ? 'true' : 'false' }}" style="{{ $kolom($errors->has('subjek')) }}">
                        @error('subjek')<div role="alert" style="font-size:12px;line-height:16px;font-weight:700;color:#EA2B2B">{{ $message }}</div>@enderror
                    </div>

                    <div style="display:flex;flex-direction:column;gap:8px" x-data="{ n: {{ mb_strlen($pesan) }} }">
                        <label for="hl-msg" style="font-size:13px;line-height:18px;font-weight:700">Pesan</label>
                        <textarea id="hl-msg" rows="6" wire:model="pesan" maxlength="1000" placeholder="Jelaskan selengkap mungkin" x-on:input="n = $el.value.length" aria-invalid="{{ $errors->has('pesan') ? 'true' : 'false' }}" style="{{ $kolom($errors->has('pesan')) }};height:auto;padding:12px 14px;resize:vertical;min-height:140px"></textarea>
                        <div style="display:flex;justify-content:space-between;gap:12px">@error('pesan')<div role="alert" style="font-size:12px;line-height:16px;font-weight:700;color:#EA2B2B">{{ $message }}</div>@enderror<div style="margin-left:auto;font-size:12px;line-height:16px;font-weight:600;color:#666C76" x-text="n + ' / 1000'">{{ mb_strlen($pesan) }} / 1000</div></div>
                    </div>

                    <div style="display:flex;flex-direction:column;gap:8px">
                        <div style="font-size:13px;line-height:18px;font-weight:700">Lampiran <span style="font-weight:500;color:#666C76">(opsional)</span></div>
                        <input id="hl-file" type="file" class="sr-only" tabindex="-1" aria-hidden="true" multiple accept="image/png,image/jpeg,image/webp,application/pdf" wire:model="berkasBaru">
                        <div style="display:flex;flex-wrap:wrap;align-items:center;gap:12px"><button type="button" class="bl-btn bl-btn-s" onclick="document.getElementById('hl-file').click()"><x-ikon nama="bantuan-unggah" :ukuran="16" /> Pilih berkas</button><span style="font-size:12px;line-height:16px;font-weight:500;color:#666C76">Maksimal 3 berkas, masing-masing 2 MB. Format PNG, JPG, WebP, atau PDF.</span></div>
                        @if ($galatBerkas !== '')<div role="alert" style="font-size:12px;line-height:16px;font-weight:700;color:#EA2B2B">{{ $galatBerkas }}</div>@endif
                        @error('berkasBaru.*')<div role="alert" style="font-size:12px;line-height:16px;font-weight:700;color:#EA2B2B">Berkas gagal diunggah. Coba berkas lain.</div>@enderror
                        @error('lampiran')<div role="alert" style="font-size:12px;line-height:16px;font-weight:700;color:#EA2B2B">{{ $message }}</div>@enderror
                        @error('lampiran.*')<div role="alert" style="font-size:12px;line-height:16px;font-weight:700;color:#EA2B2B">{{ $message }}</div>@enderror
                        @foreach ($lampiran as $i => $f)
                            <div wire:key="lampiran-{{ $i }}-{{ $f->getFilename() }}" style="display:flex;align-items:center;gap:10px;background:#F4F5F7;border-radius:14px;padding:6px 6px 6px 14px"><span aria-hidden="true" style="flex:none;display:flex;color:#666C76"><x-ikon nama="bantuan-berkas" :ukuran="18" /></span><span style="flex:1;min-width:0;font-size:13px;line-height:18px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $f->getClientOriginalName() }}</span><span style="flex:none;font-size:12px;line-height:16px;font-weight:600;color:#666C76">{{ \App\Models\TiketLampiran::ukuranTeks((int) $f->getSize()) }}</span><button type="button" wire:click="hapusLampiran({{ $i }})" aria-label="Hapus lampiran {{ $f->getClientOriginalName() }}" style="flex:none;width:44px;height:44px;border-radius:22px;display:flex;align-items:center;justify-content:center;color:#666C76"><x-ikon nama="tutup" :ukuran="16" style="stroke-width:2.5" /></button></div>
                        @endforeach
                    </div>

                    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:14px;border-top:2px solid #E2E5E9;padding-top:20px">
                        <button type="submit" class="bl-btn bl-btn-p" style="min-height:48px;padding:0 24px" wire:loading.remove wire:target="kirim"><x-ikon nama="bantuan-kirim" :ukuran="16" /> Kirim Pesan</button>
                        <button type="button" disabled class="bl-btn bl-btn-off" style="min-height:48px;padding:0 24px" aria-live="polite" wire:loading.flex wire:target="kirim">Mengirim...</button>
                        <span style="font-size:12px;line-height:17px;font-weight:500;color:#666C76;max-width:360px">Kami membalas lewat email dan WhatsApp yang terdaftar di akunmu.</span>
                    </div>
                </form>
            </section>
        @endif
    </div>

    <aside aria-label="Kontak dan tiket" style="flex:0 1 320px;min-width:0;display:flex;flex-direction:column;gap:16px">
        @if ($kontak['ada'] || $kontak['jam_layanan'])
            <x-bantuan-kontak :kontak="$kontak" judul="Hubungi Langsung" varian="hubungi" />
        @endif

        <section class="bl-card" aria-label="Tiket terakhir" style="padding:24px;display:flex;flex-direction:column;gap:14px">
            <div style="display:flex;align-items:baseline;justify-content:space-between;gap:12px"><h2 style="margin:0;font-size:18px;line-height:24px;font-weight:700">Tiket Saya</h2>@if ($jumlah > 0)<span style="font-size:12px;line-height:16px;font-weight:700;color:#666C76">{{ $jumlah }} tiket</span>@endif</div>
            @if ($jumlah === 0)
                <div style="font-size:13px;line-height:19px;font-weight:500;color:#666C76">Belum ada tiket. Pesan yang kamu kirim lewat formulir ini akan muncul di sini.</div>
            @endif
            @foreach ($tiket as $t)
                @php
                    $selesai = $t->status === 'selesai';
                    $terbuka = $bukaTiket === $t->id;
                @endphp
                <div wire:key="tiket-{{ $t->id }}" style="border:2px solid #E2E5E9;border-radius:16px;overflow:hidden">
                    <button type="button" wire:click="ubahTiket({{ $t->id }})" aria-expanded="{{ $terbuka ? 'true' : 'false' }}" style="width:100%;box-sizing:border-box;display:flex;align-items:center;gap:10px;min-height:60px;padding:10px 12px 10px 14px">
                        <span style="flex:1;min-width:0;display:flex;flex-direction:column;gap:2px">
                            <span style="font-size:13px;line-height:18px;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $t->subjek }}</span>
                            <span style="font-size:11px;line-height:15px;font-weight:600;color:#666C76">{{ $t->nomor }} · {{ $waktu($t->created_at) }}</span>
                        </span>
                        <span class="bl-chip" style="flex:none;background:{{ $selesai ? '#D7FFB8' : '#FFF4CC' }};color:{{ $selesai ? '#3C7A00' : '#7A5200' }}">{{ $t->status()[0] }}</span>
                    </button>
                    @if ($terbuka)
                        <div style="border-top:1px solid #E2E5E9;padding:12px 14px;display:flex;flex-direction:column;gap:6px;background:#FAFBFC">
                            <div style="font-size:11px;line-height:14px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#666C76">{{ $t->labelKategori() }}@if ($t->undangan) · {{ $t->undangan->judul }}@endif</div>
                            <div style="font-size:13px;line-height:19px;font-weight:500;color:#454B55;white-space:pre-line;overflow-wrap:anywhere">{{ $t->pesan }}</div>
                            @if ($t->lampiran->isNotEmpty())<div style="font-size:12px;line-height:16px;font-weight:600;color:#666C76;overflow-wrap:anywhere">Lampiran: {{ $t->lampiran->pluck('nama')->implode(', ') }}</div>@endif
                        </div>
                    @endif
                </div>
            @endforeach
            @if ($jumlah > $ringkas)
                <button type="button" wire:click="ubahSemua" style="min-height:44px;font-size:13px;line-height:18px;font-weight:700;color:#1D4ED8;text-align:center">{{ $semua ? 'Tampilkan lebih sedikit' : 'Lihat semua ('.$jumlah.')' }}</button>
            @endif
            <div style="font-size:11px;line-height:15px;font-weight:500;color:#666C76">Riwayat tiket tersimpan di akunmu.</div>
        </section>
    </aside>
</div>
