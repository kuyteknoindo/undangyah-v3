@php
    $rp = fn (int|float $n): string => 'Rp '.number_format($n, 0, ',', '.');
    $tgl = fn ($t) => $t?->locale('id')->translatedFormat('j M Y, H.i').' WIB';
    $menunggu = $status === 'menunggu';
    $lunas = $status === 'lunas';
    $mati = in_array($status, ['kedaluwarsa', 'batal'], true);
    $ikonStatus = ['menunggu' => ['beranda-jam', 'bg-warning-50 text-warning'], 'lunas' => ['centang', 'bg-success-50 text-success'], 'kedaluwarsa' => ['peringatan', 'bg-gray-100 text-nav'], 'batal' => ['tutup', 'bg-danger-50 text-danger']];
    $judulStatus = [
        'menunggu' => ['Menunggu pembayaran', 'Selesaikan transfer sebelum batas waktu, lalu unggah bukti pembayaran. Paketmu aktif setelah admin memverifikasi.'],
        'lunas' => ['Pembayaran diterima', 'Terima kasih! Pesanan ini sudah lunas.'],
        'kedaluwarsa' => ['Waktu pembayaran habis', 'Pesanan ini kedaluwarsa karena tidak dibayar sebelum batas waktu. Buat pesanan baru bila masih ingin membeli.'],
        'batal' => ['Pesanan dibatalkan', 'Pesanan ini sudah dibatalkan dan tidak bisa dibayar. Kamu bisa membuat pesanan baru kapan saja.'],
    ];
@endphp
<div class="mx-auto flex max-w-6xl flex-col gap-5" @if ($menunggu) wire:poll.20s @endif>
    @if (! $pesanan)
        <section class="kartu p-6 sm:p-8">
            <x-kosong judul="Tidak ada pesanan yang menunggu pembayaran" teks="Buat pesanan di Checkout, lalu halaman ini menampilkan instruksi pembayarannya." ikon="billing-kwitansi">
                <a href="{{ route('billing') }}" wire:navigate class="btn btn-primary">Ke Paket &amp; Billing</a>
            </x-kosong>
        </section>
    @else
        <div class="grid items-start gap-5 grid-cols-[minmax(0,1fr)] xl:grid-cols-[minmax(0,1fr)_340px]">
            <div class="flex min-w-0 flex-col gap-5">
                {{-- Status --}}
                <section role="status" class="kartu flex flex-wrap items-center gap-4 p-5">
                    <span class="flex size-16 shrink-0 items-center justify-center rounded-full {{ $ikonStatus[$status][1] ?? 'bg-gray-100 text-nav' }}"><x-ikon :nama="$ikonStatus[$status][0] ?? 'peringatan'" :ukuran="28" /></span>
                    <div class="min-w-[12rem] flex-1">
                        <h2 class="m-0 text-lg leading-7 font-semibold text-ink">{{ $judulStatus[$status][0] ?? ucfirst($status) }}</h2>
                        <div class="text-sm leading-5 text-muted">{{ $judulStatus[$status][1] ?? '' }}</div>
                    </div>
                    @if ($menunggu)
                        <div x-data="{ sisa: {{ $sisaDetik }}, akhir: 0, teks: '--:--:--', timer: null,
                                    mulai() { this.akhir = Date.now() + this.sisa * 1000; this.hitung(); this.timer = setInterval(() => this.hitung(), 1000); },
                                    hitung() { const ms = Math.max(0, this.akhir - Date.now()); this.sisa = Math.floor(ms / 1000);
                                        const p = (n) => String(n).padStart(2, '0');
                                        this.teks = p(Math.floor(ms / 3600000)) + ':' + p(Math.floor((ms % 3600000) / 60000)) + ':' + p(Math.floor((ms % 60000) / 1000));
                                        if (ms <= 0 && this.timer) { clearInterval(this.timer); this.timer = null; $wire.$refresh(); } },
                                    destroy() { if (this.timer) clearInterval(this.timer); } }"
                             x-init="mulai()" wire:ignore
                             class="w-full shrink-0 rounded-2xl px-5 py-3 text-center sm:w-auto" x-bind:class="sisa < 3600 ? 'bg-danger-50 text-danger' : 'bg-warning-50 text-warning'">
                            <div class="text-[11px] leading-[14px] font-semibold tracking-[0.06em]">SELESAIKAN DALAM</div>
                            <div class="text-2xl leading-8 font-bold tabular-nums" x-text="teks" aria-live="off">--:--:--</div>
                        </div>
                    @endif
                </section>

                @if ($lunas)
                    <section class="kartu flex flex-col gap-3 p-5">
                        <h2 class="h2">Yang berubah di akunmu</h2>
                        <ul class="m-0 flex list-none flex-col gap-2 p-0">
                            @foreach ($efek as $e)
                                <li class="flex items-start gap-2 text-sm leading-5 text-body"><x-ikon nama="centang" :ukuran="18" class="mt-0.5 text-success" /> <span>{{ $e }}</span></li>
                            @endforeach
                        </ul>
                        <div class="flex flex-wrap gap-2.5">
                            <a href="{{ route('billing') }}" wire:navigate class="btn btn-primary">Lihat paket &amp; riwayat</a>
                            <a href="{{ route('beranda') }}" wire:navigate class="btn btn-secondary">Ke Beranda</a>
                        </div>
                    </section>
                @endif

                @if ($mati)
                    <section class="kartu flex flex-col gap-3 p-5">
                        <h2 class="h2">Mau coba lagi?</h2>
                        <div class="sub">Item pesanan ini bisa dimasukkan lagi ke keranjang, lalu kamu membuat pesanan baru di Checkout.</div>
                        <div class="flex flex-wrap gap-2.5">
                            <button type="button" wire:click="pesanUlang" class="btn btn-primary">Buat pesanan baru</button>
                            <a href="{{ route('billing') }}" wire:navigate class="btn btn-secondary">Kembali ke Paket &amp; Billing</a>
                        </div>
                    </section>
                @endif

                @if ($menunggu)
                    <section aria-labelledby="k-cara" class="kartu flex flex-col gap-4 p-5">
                        <div>
                            <h2 id="k-cara" class="h2">Cara transfer</h2>
                            <div class="sub">{{ $instruksi ?: 'Silakan lakukan transfer sesuai petunjuk berikut.' }}</div>
                        </div>
                        @if ($placeholder)
                            <div role="alert" class="flex items-start gap-2 rounded-xl bg-warning-50 px-4 py-3 text-[13px] leading-[18px] font-medium text-warning"><x-ikon nama="peringatan" :ukuran="18" class="mt-0.5" /> Data tujuan di bawah masih contoh (belum diisi admin). Jangan transfer sebelum admin memperbarui rekening.</div>
                        @endif
                        @if ($tujuan && $tujuan['nilai'] !== '')
                            <div x-data="{ salin: false, async copy(t) { try { await navigator.clipboard.writeText(t); } catch (e) {} this.salin = true; setTimeout(() => this.salin = false, 1800); } }" class="flex flex-col gap-3 rounded-xl border border-line p-4">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="text-[13px] leading-[18px] text-muted">{{ $tujuan['label'] }}</div>
                                        <div class="text-xl leading-7 font-bold tracking-wide text-ink tabular-nums">{{ $tujuan['nilai'] }}</div>
                                        @if ($tujuan['atas'])<div class="text-[13px] leading-[18px] text-muted">a.n. {{ $tujuan['atas'] }}</div>@endif
                                    </div>
                                    <button type="button" class="btn btn-secondary btn-sm" x-on:click="copy({{ \Illuminate\Support\Js::from(preg_replace('/\s/', '', $tujuan['nilai'])) }})"><x-ikon nama="bagikan-salin" :ukuran="14" /> <span x-text="salin ? 'Tersalin' : 'Salin'">Salin</span></button>
                                </div>
                                <div class="flex items-baseline justify-between gap-3 border-t border-line pt-3"><span class="text-[13px] leading-[18px] text-muted">Nominal</span><span class="text-xl leading-7 font-bold text-ink tabular-nums">{{ $rp($pesanan->total) }}</span></div>
                                @if ($pesanan->kode_unik > 0)<div class="hint">Transfer tepat sampai 3 digit terakhir ({{ $pesanan->kode_unik }}) supaya mudah dicocokkan.</div>@endif
                            </div>
                        @endif
                        @if ($langkah)
                            <ol class="m-0 flex list-decimal flex-col gap-1.5 pl-5 text-sm leading-5 text-body">
                                @foreach ($langkah as $l)<li>{{ $l }}</li>@endforeach
                            </ol>
                        @endif
                    </section>

                    <section aria-labelledby="k-bukti" class="kartu flex flex-col gap-3 p-5">
                        <div>
                            <h2 id="k-bukti" class="h2">Bukti pembayaran</h2>
                            <div class="sub">Gambar JPG, PNG, atau WebP, maksimal 2 MB. Admin memeriksa bukti ini sebelum mengaktifkan pesananmu.</div>
                        </div>
                        @if ($pesanan->bukti_ditolak_at && ! $pesanan->bukti_path)
                            <div role="alert" class="flex items-start gap-2 rounded-xl bg-danger-50 px-4 py-3 text-[13px] leading-[18px] font-medium text-danger"><x-ikon nama="peringatan" :ukuran="18" class="mt-0.5" /> Bukti sebelumnya ditolak{{ $pesanan->catatan_admin ? ': '.$pesanan->catatan_admin : '.' }} Unggah bukti yang benar.</div>
                        @endif
                        @if ($pesanan->bukti_path)
                            <div class="flex flex-wrap items-center gap-4 rounded-xl border border-line p-3">
                                <a href="{{ route('pesanan.bukti', $pesanan) }}?v={{ $versiBukti }}" target="_blank" rel="noopener"><img src="{{ route('pesanan.bukti', $pesanan) }}?v={{ $versiBukti }}" alt="Pratinjau bukti pembayaran" class="size-24 rounded-lg border border-line object-cover"></a>
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm leading-5 font-semibold text-ink">{{ $pesanan->bukti_nama }}</div>
                                    <div class="text-[13px] leading-[18px] text-muted">Terkirim {{ $tgl($pesanan->bukti_at) }}. Menunggu verifikasi admin.</div>
                                </div>
                                <button type="button" wire:click="hapusBukti" wire:confirm="Hapus bukti pembayaran ini?" class="btn btn-danger btn-sm"><x-ikon nama="akun-hapus" :ukuran="14" /> Hapus bukti</button>
                            </div>
                        @endif
                        <div>
                            <label class="btn btn-secondary cursor-pointer" for="k-file"><x-ikon nama="billing-gambar" :ukuran="16" /> {{ $pesanan->bukti_path ? 'Ganti gambar' : 'Pilih gambar' }}</label>
                            <input id="k-file" type="file" wire:model="bukti" accept="image/jpeg,image/png,image/webp" class="sr-only" aria-describedby="k-bukti-info">
                            <span id="k-bukti-info" wire:loading wire:target="bukti" class="sub ml-3">Mengunggah…</span>
                        </div>
                        @error('bukti')<div role="alert" class="galat">{{ $message }}</div>@enderror
                    </section>

                    <div class="flex flex-wrap gap-2.5">
                        <button type="button" wire:click="$set('dialogBatal', true)" class="btn btn-danger">Batalkan pesanan</button>
                    </div>
                @endif

                <section class="kartu flex flex-wrap items-center gap-3 p-5">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand"><x-ikon nama="bantuan" :ukuran="20" /></span>
                    <div class="min-w-[12rem] flex-1">
                        <div class="text-sm leading-5 font-semibold text-ink">Butuh bantuan dengan pembayaran?</div>
                        <div class="text-[13px] leading-[18px] text-muted">Tim layanan membantu soal pesanan, invoice, atau kendala bayar.</div>
                    </div>
                    @if (Route::has('hubungi'))<a href="{{ route('hubungi') }}" wire:navigate class="btn btn-secondary btn-sm">Hubungi layanan</a>@endif
                </section>
            </div>

            <aside aria-labelledby="k-rin" class="kartu flex flex-col gap-4 p-5 xl:sticky xl:top-24">
                <div class="flex items-center justify-between gap-2">
                    <h2 id="k-rin" class="h2">Rincian pesanan</h2>
                    @php [$labelS, $warnaS] = $pesanan->infoStatus(); @endphp
                    <x-pill :warna="$warnaS">{{ $labelS }}</x-pill>
                </div>
                <dl class="m-0 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 text-[13px] leading-[18px]">
                    <dt class="text-muted">Invoice</dt><dd class="m-0 text-right font-semibold text-ink">{{ $pesanan->nomor }}</dd>
                    <dt class="text-muted">Dibuat</dt><dd class="m-0 text-right">{{ $tgl($pesanan->created_at) }}</dd>
                    <dt class="text-muted">Metode</dt><dd class="m-0 text-right">{{ $pesanan->metode_label ?? '-' }}</dd>
                    <dt class="text-muted">{{ $lunas ? 'Dibayar' : 'Batas bayar' }}</dt><dd class="m-0 text-right">{{ $lunas ? $tgl($pesanan->dibayar_at) : $tgl($pesanan->kedaluwarsa_at) }}</dd>
                </dl>
                <div class="flex flex-col gap-2 border-t border-line pt-3 text-sm leading-5">
                    @foreach ($pesanan->item as $it)
                        <div class="flex justify-between gap-3"><span class="min-w-0">{{ $it->namaTampil() }}</span><span class="shrink-0 font-medium tabular-nums">{{ $rp($it->harga) }}</span></div>
                    @endforeach
                    <div class="flex justify-between gap-3 border-t border-dashed border-line pt-2.5"><span>Subtotal</span><span class="font-medium tabular-nums">{{ $rp($pesanan->subtotal) }}</span></div>
                    @if ($pesanan->diskon > 0)<div class="flex justify-between gap-3 text-success"><span>Voucher {{ $pesanan->voucher_kode }}</span><span class="font-medium tabular-nums">- {{ $rp($pesanan->diskon) }}</span></div>@endif
                    @if ($pesanan->biaya > 0)<div class="flex justify-between gap-3 text-muted"><span>Biaya pembayaran</span><span class="tabular-nums">{{ $rp($pesanan->biaya) }}</span></div>@endif
                    @if ($pesanan->kode_unik > 0)<div class="flex justify-between gap-3 text-muted"><span>Kode unik</span><span class="tabular-nums">+ {{ $rp($pesanan->kode_unik) }}</span></div>@endif
                </div>
                <div class="flex items-baseline justify-between gap-3 border-t border-line pt-3"><span class="text-sm leading-5 font-semibold text-ink">Total</span><span class="text-xl leading-7 font-bold text-ink tabular-nums">{{ $rp($pesanan->total) }}</span></div>
                <a href="{{ route('invoice', $pesanan) }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm"><x-ikon nama="billing-cetak" :ukuran="14" /> Lihat invoice</a>
            </aside>
        </div>

        <x-dialog wire:model="dialogBatal" judul="Batalkan pesanan ini?" lebar="max-w-[480px]">
            <p class="sub m-0">Pesanan {{ $pesanan->nomor }} akan dibatalkan dan tidak bisa dibayar lagi. Kamu tetap bisa membuat pesanan baru.</p>
            <x-slot:footer>
                <button type="button" class="btn btn-secondary" x-on:click="buka = false">Kembali</button>
                <button type="button" wire:click="batalkan" class="btn btn-danger-solid">Ya, batalkan</button>
            </x-slot:footer>
        </x-dialog>
    @endif
</div>
