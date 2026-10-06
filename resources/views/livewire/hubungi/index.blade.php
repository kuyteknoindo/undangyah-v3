<div class="mx-auto grid max-w-6xl items-start gap-5 xl:grid-cols-[minmax(0,1fr)_340px]">
    <div class="min-w-0">
        @if ($baru)
            <section class="kartu flex flex-col items-start gap-4 p-6 sm:p-8" aria-labelledby="hl-terkirim">
                <span class="flex size-12 items-center justify-center rounded-full bg-success-50 text-success"><x-ikon nama="bantuan-centang" :ukuran="24" /></span>
                <h2 id="hl-terkirim" class="m-0 text-xl leading-7 font-semibold text-ink">Pesan terkirim</h2>
                <p class="m-0 text-sm leading-6 text-body">Nomor tiket kamu <b>{{ $baru->nomor }}</b>. Kami akan membalas lewat email dan WhatsApp. Simpan nomor ini bila perlu menanyakan kelanjutannya.</p>
                <div class="w-full rounded-xl border border-line bg-surface p-3.5">
                    <div class="text-xs leading-4 font-semibold text-muted">{{ $baru->labelKategori() }}</div>
                    <div class="mt-0.5 text-sm leading-5 font-semibold break-words text-ink">{{ $baru->subjek }}</div>
                </div>
                <div class="flex flex-wrap gap-2" x-data="{ nomor: @js($baru->nomor) }">
                    <button type="button" class="btn btn-secondary"
                            x-on:click="navigator.clipboard.writeText(nomor).then(() => $dispatch('toast', { pesan: 'Nomor tiket disalin.', tipe: 'sukses' }), () => $dispatch('toast', { pesan: 'Salin manual: ' + nomor, tipe: 'info' }))">
                        <x-ikon nama="bantuan-salin" :ukuran="16" /> Salin nomor
                    </button>
                    <button type="button" wire:click="pesanLain" class="btn btn-secondary">Kirim pesan lain</button>
                    @if ($tautanBantuan)<a href="{{ $tautanBantuan }}" wire:navigate class="btn btn-ghost">Pusat Bantuan</a>@endif
                </div>
            </section>
        @else
            <section class="kartu flex flex-col gap-5 p-5 sm:p-6" aria-label="Formulir pesan">
                <div>
                    <h2 class="m-0 text-lg leading-7 font-semibold text-ink">Kirim Pesan</h2>
                    <div class="sub">Ceritakan kendalamu, kami bantu carikan solusinya.</div>
                </div>

                @error('form')
                    <div role="alert" class="flex items-start gap-2 rounded-xl border border-[#fecdca] bg-[#fef3f2] p-3 text-[13px] leading-5 font-medium text-[#b42318]"><x-ikon nama="peringatan" :ukuran="16" class="mt-0.5" />{{ $message }}</div>
                @enderror

                <form wire:submit="kirim" class="flex flex-col gap-5" novalidate>
                    <div role="group" aria-labelledby="hl-kat-l">
                        <div id="hl-kat-l" class="lbl">Kategori</div>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($kategoriDaftar as $kunci => $label)
                                @php
$on = $kategori === $kunci;
@endphp
                                <button type="button" wire:click="pilihKategori('{{ $kunci }}')" aria-pressed="{{ $on ? 'true' : 'false' }}"
                                        class="min-h-11 rounded-full border-2 px-3.5 text-[13px] leading-[18px] font-bold {{ $on ? 'border-brand bg-brand-100 text-brand' : ($errors->has('kategori') ? 'border-danger bg-white text-ink' : 'border-line bg-white text-ink hover:bg-gray-50') }}">{{ $label }}</button>
                            @endforeach
                        </div>
                        @error('kategori')<div role="alert" class="galat mt-1.5">{{ $message }}</div>@enderror
                    </div>

                    <div>
                        <label for="hl-inv" class="lbl">Undangan terkait <span class="font-normal text-muted">(opsional)</span></label>
                        <select id="hl-inv" wire:model="undanganId" class="inp">
                            <option value="">Tidak terkait undangan tertentu</option>
                            @foreach ($undanganDaftar as $u)
                                <option value="{{ $u->id }}">{{ $u->judul }} ({{ $u->status === 'terbit' ? 'terbit' : 'draf' }})</option>
                            @endforeach
                        </select>
                        @error('undanganId')<div role="alert" class="galat mt-1.5">{{ $message }}</div>@enderror
                    </div>

                    <div>
                        <label for="hl-sub" class="lbl">Subjek</label>
                        <input id="hl-sub" type="text" wire:model="subjek" maxlength="120" placeholder="Ringkas masalahmu" class="inp" @error('subjek') aria-invalid="true" @enderror>
                        @error('subjek')<div role="alert" class="galat mt-1.5">{{ $message }}</div>@enderror
                    </div>

                    <div x-data="{ n: {{ mb_strlen($pesan) }} }">
                        <label for="hl-msg" class="lbl">Pesan</label>
                        <textarea id="hl-msg" wire:model="pesan" maxlength="1000" rows="6" placeholder="Jelaskan selengkap mungkin" class="inp !min-h-36" x-on:input="n = $el.value.length" @error('pesan') aria-invalid="true" @enderror></textarea>
                        <div class="mt-1.5 flex items-start justify-between gap-3">
                            <div>@error('pesan')<div role="alert" class="galat">{{ $message }}</div>@enderror</div>
                            <div class="hint shrink-0" x-text="n + ' / 1000'">{{ mb_strlen($pesan) }} / 1000</div>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="kirim">
                            <span wire:loading.remove wire:target="kirim" class="inline-flex items-center gap-2"><x-ikon nama="bantuan-kirim" :ukuran="16" /> Kirim Pesan</span>
                            <span wire:loading wire:target="kirim">Mengirim...</span>
                        </button>
                        <span class="hint">Kami membalas lewat email dan WhatsApp yang terdaftar di akunmu.</span>
                    </div>
                </form>
            </section>
        @endif
    </div>

    <aside aria-label="Kontak dan tiket" class="flex flex-col gap-4">
        @if ($kontak['ada'] || $kontak['jam_layanan'])
            <x-bantuan-kontak :kontak="$kontak" judul="Hubungi Langsung" />
        @endif

        <section class="kartu flex flex-col gap-3 p-5" aria-label="Tiket terakhir">
            <div class="flex items-center justify-between gap-2">
                <h2 class="h2">Tiket Saya</h2>
                @if ($jumlah > 0)<span class="hint">{{ $jumlah }} tiket</span>@endif
            </div>
            @if ($jumlah === 0)
                <div class="sub">Belum ada tiket. Pesan yang kamu kirim lewat formulir ini akan muncul di sini.</div>
            @endif
            @foreach ($tiket as $t)
                @php
                    [$labelStatus, $warna] = $t->status();
                    $terbuka = $bukaTiket === $t->id;
                    $waktu = $t->created_at->gt(now()->subMinute()) ? 'Baru saja' : ($t->created_at->gt(now()->subDay()) ? $t->created_at->diffForHumans() : $t->created_at->translatedFormat('j M Y'));
                @endphp
                <div wire:key="tiket-{{ $t->id }}" class="overflow-hidden rounded-xl border border-line">
                    <button type="button" wire:click="ubahTiket({{ $t->id }})" aria-expanded="{{ $terbuka ? 'true' : 'false' }}" class="flex min-h-14 w-full items-center justify-between gap-3 px-3.5 py-2.5 text-left hover:bg-gray-50">
                        <span class="flex min-w-0 flex-col">
                            <span class="truncate text-[13px] leading-[18px] font-semibold text-ink">{{ $t->subjek }}</span>
                            <span class="truncate text-xs leading-4 text-muted">{{ $t->nomor }} · {{ $waktu }}</span>
                        </span>
                        <x-pill :warna="$warna">{{ $labelStatus }}</x-pill>
                    </button>
                    @if ($terbuka)
                        <div class="flex flex-col gap-1.5 border-t border-line bg-surface px-3.5 py-3">
                            <div class="text-xs leading-4 font-semibold text-muted">{{ $t->labelKategori() }}@if ($t->undangan) · {{ $t->undangan->judul }}@endif</div>
                            <div class="text-[13px] leading-5 break-words whitespace-pre-line text-body">{{ $t->pesan }}</div>
                        </div>
                    @endif
                </div>
            @endforeach
            @if ($jumlah > $ringkas)
                <button type="button" wire:click="ubahSemua" class="btn btn-ghost btn-sm self-start">{{ $semua ? 'Tampilkan lebih sedikit' : 'Lihat semua ('.$jumlah.')' }}</button>
            @endif
            <div class="hint">Riwayat tiket tersimpan di akunmu.</div>
        </section>
    </aside>
</div>
