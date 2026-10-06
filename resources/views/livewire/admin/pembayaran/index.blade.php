@php
    $harga = fn ($n) => 'Rp '.number_format((int) $n, 0, ',', '.');
    $tujuan = function ($m) {
        $d = (array) ($m->data ?? []);

        return $m->tipe === 'instruksi'
            ? [(string) ($d['tujuan_label'] ?? 'Tujuan'), (string) ($d['tujuan_nilai'] ?? '')]
            : ['Rekening '.($d['bank'] ?? ''), (string) ($d['nomor'] ?? '')];
    };
@endphp
<div class="mx-auto flex max-w-6xl flex-col gap-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <p class="sub m-0 max-w-2xl">Metode yang tampil di Checkout. Pembayaran memakai transfer manual ke rekening atau nomor e-wallet yang kamu isi sendiri, lalu pembeli mengunggah bukti dan kamu mengonfirmasinya di Data Pesanan. Perubahan berlaku untuk pesanan baru; pesanan lama menyimpan salinan data saat dibuat.</p>
        <button type="button" class="btn btn-primary" wire:click="bukaBaru"><x-ikon nama="tambah" :ukuran="16" /> Tambah metode</button>
    </div>

    @if ($contohAktif->isNotEmpty())
        <div class="flex items-start gap-3 rounded-xl border-2 border-[#f04438] bg-[#fef3f2] px-4 py-3.5 text-[13px] leading-5 text-[#7a271a]" role="alert" data-peringatan-placeholder>
            <x-ikon nama="peringatan" :ukuran="20" class="mt-0.5 text-[#d92d20]" />
            <div>
                <div class="text-sm font-semibold text-[#b42318]">PERINGATAN: rekening pembayaran masih data contoh</div>
                <div class="mt-1">Metode aktif berikut masih berisi rekening/nomor contoh, bukan milikmu: <b>{{ $contohAktif->pluck('label')->implode(', ') }}</b>. Pembeli akan diminta mentransfer uang ke tujuan yang salah. Isi rekening nyata lewat tombol Edit sebelum dipakai pelanggan, atau nonaktifkan metodenya.</div>
                @if ($menungguContoh > 0)
                    <div class="mt-1 font-medium">{{ $menungguContoh }} pesanan yang sedang menunggu pembayaran sudah menampilkan rekening contoh. Hubungi pembelinya atau batalkan pesanannya di Data Pesanan.</div>
                @endif
            </div>
        </div>
    @endif
    @if ($aktif->isEmpty())
        <div class="flex items-start gap-3 rounded-xl border border-[#fecdca] bg-[#fef3f2] px-4 py-3 text-[13px] leading-5 text-[#7a271a]" role="alert">
            <x-ikon nama="peringatan" :ukuran="18" class="mt-0.5 text-[#d92d20]" />
            <div><b>Semua metode pembayaran nonaktif.</b> Pengguna tidak bisa membayar di Checkout. Aktifkan minimal satu rekening transfer atau e-wallet.</div>
        </div>
    @endif

    <section aria-label="Ringkasan" class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="kartu kpi"><div class="kpi-l">Metode aktif</div><div class="kpi-v">{{ $aktif->count() }} / {{ $semua->count() }}</div><div class="hint">Tampil di Checkout</div></div>
        <div class="kartu kpi"><div class="kpi-l">Rekening nyata</div><div class="kpi-v">{{ $aktif->count() - $contohAktif->count() }}</div><div class="hint">{{ $contohAktif->count() ? $contohAktif->count().' aktif masih data contoh' : 'Semua metode aktif sudah diisi' }}</div></div>
        <div class="kartu kpi"><div class="kpi-l">Kode unik</div><div class="kpi-v">{{ $aktif->where('kode_unik', true)->count() }}</div><div class="hint">metode aktif memakai kode unik</div></div>
        <div class="kartu kpi"><div class="kpi-l">Batas waktu bayar</div><div class="kpi-v">{{ $jamAktif }} jam</div><div class="hint">Pesanan baru kedaluwarsa setelahnya</div></div>
    </section>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_340px]">
        <div class="flex min-w-0 flex-col gap-5">
            <section class="kartu overflow-hidden" aria-labelledby="mt-h">
                <div class="border-b border-line p-4 sm:p-5">
                    <h2 id="mt-h" class="h2">Daftar metode</h2>
                    <div class="sub">Urutan menentukan susunan pilihan di Checkout; metode aktif paling atas jadi pilihan awal.</div>
                </div>
                @if ($semua->isEmpty())
                    <x-kosong judul="Belum ada metode pembayaran" teks="Tambahkan rekening agar pembeli bisa membayar di Checkout." ikon="admin-payment">
                        <button type="button" class="btn btn-primary btn-sm" wire:click="bukaBaru">Tambah metode</button>
                    </x-kosong>
                @else
                    <ul class="m-0 list-none p-0">
                        @foreach ($semua as $m)
                            @php [$tLabel, $tNilai] = $tujuan($m); $contoh = $cek->masihPlaceholder($m); @endphp
                            <li wire:key="m-{{ $m->id }}" @class(['flex flex-wrap items-start gap-3 border-b border-line px-4 py-4 last:border-b-0 sm:px-5', 'opacity-70' => ! $m->aktif])>
                                <div class="flex shrink-0 gap-1">
                                    <button type="button" class="btn-icon" wire:click="geser({{ $m->id }}, -1)" aria-label="Naikkan urutan {{ $m->label }}" @disabled($m->id === $semua->first()->id)><x-ikon nama="editor-naik" :ukuran="14" /></button>
                                    <button type="button" class="btn-icon" wire:click="geser({{ $m->id }}, 1)" aria-label="Turunkan urutan {{ $m->label }}" @disabled($m->id === $semua->last()->id)><x-ikon nama="editor-turun" :ukuran="14" /></button>
                                </div>
                                <span @class(['flex size-9 shrink-0 items-center justify-center rounded-lg', 'bg-brand-50 text-brand' => $m->aktif, 'bg-gray-100 text-subtle' => ! $m->aktif])><x-ikon nama="admin-payment" :ukuran="18" /></span>
                                <div class="min-w-0 flex-1 basis-56">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <span class="text-sm font-semibold text-ink">{{ $m->label }}</span>
                                        <span class="tag">{{ $m->tipe === 'instruksi' ? 'E-wallet / instruksi' : 'Transfer manual' }}</span>
                                        @if ($contoh)<x-pill warna="merah">Data contoh</x-pill>@endif
                                        @if ($m->kode_unik)<span class="tag">Kode unik</span>@endif
                                    </div>
                                    @if ($m->keterangan)<div class="sub">{{ $m->keterangan }}</div>@endif
                                    <div class="mt-1 text-[13px] text-gray-700"><span class="text-muted">{{ $tLabel }}:</span> <span class="font-mono font-semibold {{ $contoh ? 'text-[#b42318]' : 'text-ink' }}">{{ $tNilai ?: '–' }}</span> · a.n. {{ ($m->data['atas'] ?? '') ?: '–' }}</div>
                                    <div class="hint mt-0.5">{{ $m->teksBiaya() }} · {{ number_format((int) ($pesananPakai[$m->kode] ?? 0), 0, ',', '.') }} pesanan</div>
                                </div>
                                <div class="flex shrink-0 items-center gap-1">
                                    <button type="button" role="switch" class="sakelar mr-1" aria-checked="{{ $m->aktif ? 'true' : 'false' }}" aria-label="Aktifkan {{ $m->label }}" wire:click="ubahAktif({{ $m->id }})"></button>
                                    <button type="button" class="btn-icon" wire:click="bukaEdit({{ $m->id }})" aria-label="Edit {{ $m->label }}" title="Edit"><x-ikon nama="bagikan-edit" :ukuran="14" /></button>
                                    <button type="button" class="btn-icon text-[#b42318]" wire:click="tanyaHapus({{ $m->id }})" aria-label="Hapus {{ $m->label }}" title="Hapus"><x-ikon nama="bagikan-hapus" :ukuran="14" /></button>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section class="kartu p-4 sm:p-5" aria-labelledby="bw-h">
                <h2 id="bw-h" class="h2">Batas waktu pembayaran</h2>
                <div class="sub mb-3">Pesanan yang belum dibayar otomatis kedaluwarsa setelah batas ini. Berlaku untuk pesanan baru.</div>
                <div class="flex flex-wrap items-end gap-3">
                    <div class="w-40">
                        <label for="bw-jam" class="lbl">Batas waktu (jam)</label>
                        <input id="bw-jam" type="text" inputmode="numeric" class="inp" maxlength="3" wire:model="kedaluwarsaJam" wire:keydown.enter="simpanBatasWaktu" @error('kedaluwarsaJam') aria-invalid="true" @enderror>
                    </div>
                    <button type="button" class="btn btn-primary" wire:click="simpanBatasWaktu" wire:loading.attr="disabled" wire:target="simpanBatasWaktu">Simpan batas waktu</button>
                </div>
                <div class="hint mt-1">1-168 jam (maksimal 7 hari).</div>
                @error('kedaluwarsaJam')<div class="galat mt-1" role="alert">{{ $message }}</div>@enderror
            </section>
        </div>

        <aside class="kartu h-fit p-4 sm:p-5" aria-labelledby="pv-h">
            <h2 id="pv-h" class="h2">Yang terlihat di Checkout</h2>
            <div class="sub mb-3">Pilihan pembayaran untuk pembeli, dengan contoh biaya pada tagihan {{ $harga($dasarContoh) }}.</div>
            @if ($aktif->isEmpty())
                <div class="rounded-xl bg-[#fef3f2] px-3 py-2.5 text-[13px] text-[#b42318]">Tidak ada pilihan pembayaran. Pembeli tidak bisa menyelesaikan Checkout.</div>
            @else
                <ul class="m-0 flex list-none flex-col gap-2 p-0">
                    @foreach ($aktif as $i => $m)
                        @php $biaya = $m->biayaUntuk($dasarContoh); @endphp
                        <li @class(['rounded-xl border px-3 py-2.5', 'border-brand bg-[#f5f8ff]' => $i === 0, 'border-line bg-white' => $i !== 0])>
                            <div class="flex items-center justify-between gap-2"><span class="text-[13px] font-semibold text-ink">{{ $m->label }}</span><span class="text-xs text-muted">{{ $m->teksBiaya() }}</span></div>
                            @if ($m->keterangan)<div class="hint">{{ $m->keterangan }}</div>@endif
                            <div class="hint">Total {{ $harga($dasarContoh + $biaya) }}{{ $biaya ? ' (biaya '.$harga($biaya).')' : '' }}{{ $m->kode_unik ? ' + kode unik 3 digit' : '' }}</div>
                            @if ($cek->masihPlaceholder($m))<div class="mt-1 text-xs font-semibold text-[#b42318]">Rekening masih data contoh</div>@endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </aside>
    </div>

    <x-dialog wire:model="dialogForm" :judul="$editMetode ? 'Edit metode '.$editMetode->label : 'Tambah metode'" :sub="$editMetode ? 'Kode sistem: '.$editMetode->kode : 'Metode baru dimatikan sampai kamu mengaktifkannya.'" lebar="max-w-[680px]">
        @php $instruksiTipe = ($f['tipe'] ?? 'manual') === 'instruksi'; @endphp
        @if ($formContoh)
            <div class="flex items-start gap-2.5 rounded-xl border border-[#fecdca] bg-[#fef3f2] px-3 py-2.5 text-[13px] leading-5 text-[#7a271a]" role="alert">
                <x-ikon nama="peringatan" :ukuran="16" class="mt-0.5 text-[#d92d20]" />
                <div><b>Isian masih berupa data contoh</b> (nomor kosong/nol, "BANK CONTOH", atau nama "GANTI DI ADMIN"). Ganti dengan rekening nyata. Tanda contoh baru hilang setelah kamu menyimpan rekening nyata.</div>
            </div>
        @endif
        <div class="grid gap-3.5 sm:grid-cols-2">
            <div>
                <label for="mf-label" class="lbl">Nama metode</label>
                <input id="mf-label" type="text" class="inp" maxlength="80" placeholder="Contoh: Transfer BCA" wire:model="f.label" @error('f.label') aria-invalid="true" @enderror>
                @error('f.label')<div class="galat mt-1" role="alert">{{ $message }}</div>@enderror
            </div>
            <div>
                <span class="lbl" id="mf-tipe-l">Jenis</span>
                <div class="seg" role="group" aria-labelledby="mf-tipe-l">
                    <button type="button" wire:click="$set('f.tipe', 'manual')" aria-pressed="{{ ! $instruksiTipe ? 'true' : 'false' }}">Rekening bank</button>
                    <button type="button" wire:click="$set('f.tipe', 'instruksi')" aria-pressed="{{ $instruksiTipe ? 'true' : 'false' }}">E-wallet / lainnya</button>
                </div>
            </div>
        </div>
        <div>
            <label for="mf-ket" class="lbl">Keterangan singkat (opsional)</label>
            <input id="mf-ket" type="text" class="inp" maxlength="160" placeholder="Contoh: Transfer ke rekening kami, lalu unggah bukti" wire:model="f.keterangan" @error('f.keterangan') aria-invalid="true" @enderror>
            @error('f.keterangan')<div class="galat mt-1" role="alert">{{ $message }}</div>@enderror
        </div>
        <div class="grid gap-3.5 sm:grid-cols-2">
            @if (! $instruksiTipe)
                <div>
                    <label for="mf-bank" class="lbl">Nama bank</label>
                    <input id="mf-bank" type="text" class="inp" maxlength="40" placeholder="Contoh: BCA" wire:model="f.bank" @error('f.bank') aria-invalid="true" @enderror>
                    @error('f.bank')<div class="galat mt-1" role="alert">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label for="mf-nomor" class="lbl">Nomor rekening</label>
                    <input id="mf-nomor" type="text" inputmode="numeric" class="inp font-mono" maxlength="30" placeholder="1234567890" wire:model="f.nomor" @error('f.nomor') aria-invalid="true" @enderror>
                    @error('f.nomor')<div class="galat mt-1" role="alert">{{ $message }}</div>@enderror
                </div>
            @else
                <div>
                    <label for="mf-tlabel" class="lbl">Label tujuan</label>
                    <input id="mf-tlabel" type="text" class="inp" maxlength="40" placeholder="Nomor e-wallet" wire:model="f.tujuan_label" @error('f.tujuan_label') aria-invalid="true" @enderror>
                    @error('f.tujuan_label')<div class="galat mt-1" role="alert">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label for="mf-tnilai" class="lbl">Nomor / tujuan</label>
                    <input id="mf-tnilai" type="text" class="inp font-mono" maxlength="40" placeholder="081234567890" wire:model="f.tujuan_nilai" @error('f.tujuan_nilai') aria-invalid="true" @enderror>
                    @error('f.tujuan_nilai')<div class="galat mt-1" role="alert">{{ $message }}</div>@enderror
                </div>
            @endif
        </div>
        <div>
            <label for="mf-atas" class="lbl">Atas nama</label>
            <input id="mf-atas" type="text" class="inp" maxlength="60" placeholder="Nama pemilik rekening / akun" wire:model="f.atas" @error('f.atas') aria-invalid="true" @enderror>
            @error('f.atas')<div class="galat mt-1" role="alert">{{ $message }}</div>@enderror
        </div>
        <div class="grid gap-3.5 sm:grid-cols-2">
            <div>
                <label for="mf-bt" class="lbl">Biaya tetap (Rp)</label>
                <input id="mf-bt" type="text" inputmode="numeric" class="inp" maxlength="7" wire:model="f.biaya_tetap" @error('f.biaya_tetap') aria-invalid="true" @enderror>
                @error('f.biaya_tetap')<div class="galat mt-1" role="alert">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="mf-bp" class="lbl">Biaya persen (%)</label>
                <input id="mf-bp" type="text" inputmode="decimal" class="inp" maxlength="6" placeholder="0,7" wire:model="f.biaya_persen" @error('f.biaya_persen') aria-invalid="true" @enderror>
                @error('f.biaya_persen')<div class="galat mt-1" role="alert">{{ $message }}</div>@enderror
            </div>
        </div>
        <div>
            <label for="mf-ins" class="lbl">Instruksi untuk pembeli (opsional)</label>
            <textarea id="mf-ins" class="inp" maxlength="300" rows="2" wire:model="f.instruksi" @error('f.instruksi') aria-invalid="true" @enderror></textarea>
            <div class="hint mt-1">Tampil di halaman konfirmasi pembeli. Maksimal 300 karakter.</div>
            @error('f.instruksi')<div class="galat mt-1" role="alert">{{ $message }}</div>@enderror
        </div>
        <div>
            <label for="mf-lng" class="lbl">Langkah pembayaran (satu per baris, opsional)</label>
            <textarea id="mf-lng" class="inp" rows="4" wire:model="f.langkah" @error('f.langkah') aria-invalid="true" @enderror></textarea>
            @error('f.langkah')<div class="galat mt-1" role="alert">{{ $message }}</div>@enderror
        </div>
        <div class="flex flex-col divide-y divide-line rounded-xl border border-line">
            <div class="flex items-center justify-between gap-3 px-3 py-2.5">
                <div><div class="text-[13px] font-semibold text-ink" id="mf-unik-l">Tambahkan kode unik 3 digit</div><div class="hint">Total transfer ditambah angka acak agar mudah dicocokkan.</div></div>
                <button type="button" role="switch" class="sakelar" aria-checked="{{ ! empty($f['kode_unik']) ? 'true' : 'false' }}" aria-labelledby="mf-unik-l" wire:click="$toggle('f.kode_unik')"></button>
            </div>
            <div class="flex items-center justify-between gap-3 px-3 py-2.5">
                <div><div class="text-[13px] font-semibold text-ink" id="mf-aktif-l">Aktif</div><div class="hint">Metode nonaktif tidak tampil di Checkout.</div></div>
                <button type="button" role="switch" class="sakelar" aria-checked="{{ ! empty($f['aktif']) ? 'true' : 'false' }}" aria-labelledby="mf-aktif-l" wire:click="$toggle('f.aktif')"></button>
            </div>
        </div>
        @if ($errors->any())
            <div class="flex items-center gap-2 rounded-xl bg-[#fef3f2] px-3 py-2.5 text-[13px] text-[#b42318]" role="alert"><x-ikon nama="peringatan" :ukuran="16" /> Periksa kembali isian yang ditandai merah.</div>
        @endif
        <x-slot:footer>
            <button type="button" class="btn btn-secondary" x-on:click="$wire.dialogForm = false">Batal</button>
            <button type="button" class="btn btn-primary" wire:click="simpan" wire:loading.attr="disabled" wire:target="simpan">{{ $editMetode ? 'Simpan perubahan' : 'Tambah metode' }}</button>
        </x-slot:footer>
    </x-dialog>

    <x-dialog wire:model="dialogHapus" :judul="'Hapus metode '.($hapusMetode->label ?? '').'?'" lebar="max-w-[480px]">
        <p class="m-0 text-sm leading-6 text-gray-700">Metode dihapus dari Checkout. Metode ini belum pernah dipakai pesanan. Untuk menyembunyikan sementara, matikan saja statusnya.</p>
        <x-slot:footer>
            <button type="button" class="btn btn-secondary" x-on:click="$wire.dialogHapus = false">Batal</button>
            <button type="button" class="btn btn-danger-solid" wire:click="hapus" wire:loading.attr="disabled" wire:target="hapus">Hapus metode</button>
        </x-slot:footer>
    </x-dialog>

    <x-admin.dialog-konfirmasi-sandi :label="$tertundaLabel" :pesan="$tertundaPesan" />
</div>
