@php
    $sistemKode = \App\Livewire\Admin\AddOns::SISTEM;
    $harga = fn ($n) => 'Rp '.number_format((int) $n, 0, ',', '.');
    $aturan = function ($a) {
        $t = [];
        $t[] = $a->multi ? 'Bisa berkali-kali' : 'Sekali beli';
        if ($a->butuh_premium) { $t[] = 'Butuh Premium'; }
        if ($a->termasuk_premium) { $t[] = 'Termasuk Premium'; }

        return $t;
    };
@endphp
<div class="mx-auto flex max-w-6xl flex-col gap-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <p class="sub m-0 max-w-2xl">Fitur tambahan yang bisa dibeli tanpa ganti paket. Add-on aktif tampil di bagian Add-on halaman Paket &amp; Billing dan bisa dimasukkan ke Checkout.</p>
        <button type="button" class="btn btn-primary" wire:click="bukaBaru"><x-ikon nama="tambah" :ukuran="16" /> Tambah add-on</button>
    </div>

    <section aria-label="Ringkasan" class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="kartu kpi"><div class="kpi-l">Total add-on</div><div class="kpi-v">{{ $hitung['semua'] }}</div><div class="hint">{{ $hitung['aktif'] }} aktif · {{ $hitung['nonaktif'] }} nonaktif</div></div>
        <div class="kartu kpi"><div class="kpi-l">Aktif</div><div class="kpi-v">{{ $hitung['aktif'] }}</div><div class="hint">Tampil dan bisa dibeli</div></div>
        <div class="kartu kpi"><div class="kpi-l">Termahal</div><div class="kpi-v">{{ $termahal ? $harga($termahal->harga) : '–' }}</div><div class="hint">{{ $termahal?->nama ?? 'Belum ada add-on' }}</div></div>
        <div class="kartu kpi"><div class="kpi-l">Khusus Premium</div><div class="kpi-v">{{ $nButuh }}</div><div class="hint">{{ $nTermasuk }} add-on sudah termasuk Premium</div></div>
    </section>

    <section class="kartu overflow-hidden" aria-labelledby="tb-h">
        <div class="flex flex-col gap-3 border-b border-line p-4 sm:p-5">
            <div>
                <h2 id="tb-h" class="h2">Daftar add-on</h2>
                <div class="sub">{{ $baris->count() }} dari {{ $hitung['semua'] }} add-on · urutan sama dengan tampilan di Paket &amp; Billing</div>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <div class="relative w-full sm:max-w-xs">
                    <label for="aq" class="sr-only">Cari add-on</label>
                    <span class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-muted"><x-ikon nama="bagikan-cari" :ukuran="16" /></span>
                    <input id="aq" type="search" class="inp pl-9" maxlength="60" placeholder="Cari nama atau deskripsi" wire:model.live.debounce.300ms="q" autocomplete="off">
                </div>
                <div class="seg flex-wrap" role="group" aria-label="Filter status">
                    @foreach (['semua' => 'Semua', 'aktif' => 'Aktif', 'nonaktif' => 'Nonaktif'] as $k => $label)
                        <button type="button" wire:click="atur('{{ $k }}')" aria-pressed="{{ $fs === $k ? 'true' : 'false' }}">{{ $label }} <span class="text-muted">{{ $hitung[$k] }}</span></button>
                    @endforeach
                </div>
            </div>
        </div>
        @if ($semua->isEmpty())
            <x-kosong judul="Belum ada add-on" teks="Tambahkan add-on pertama agar pengguna bisa membeli fitur tambahan." ikon="admin-addons">
                <button type="button" class="btn btn-primary btn-sm" wire:click="bukaBaru">Tambah add-on</button>
            </x-kosong>
        @elseif ($baris->isEmpty())
            <x-kosong judul="Tidak ada add-on yang cocok" teks="Ubah kata kunci atau filter status." ikon="bagikan-cari">
                <button type="button" class="btn btn-secondary btn-sm" wire:click="resetFilter">Reset filter</button>
            </x-kosong>
        @else
            <div class="overflow-x-auto">
                <table class="tabel">
                    <caption class="sr-only">Daftar add-on</caption>
                    <thead><tr><th>Urutan</th><th>Add-on</th><th>Harga</th><th>Aturan beli</th><th>Pemilik</th><th>Status</th><th>Aksi</th></tr></thead>
                    <tbody>
                        @foreach ($baris as $r)
                            @php $sys = in_array($r->kode, $sistemKode, true); @endphp
                            <tr wire:key="a-{{ $r->id }}" @class(['opacity-70' => ! $r->aktif])>
                                <td>
                                    <div class="flex gap-1">
                                        <button type="button" class="btn-icon" wire:click="geser({{ $r->id }}, -1)" aria-label="Naikkan urutan {{ $r->nama }}" @disabled($r->id === $semua->first()->id)><x-ikon nama="editor-naik" :ukuran="14" /></button>
                                        <button type="button" class="btn-icon" wire:click="geser({{ $r->id }}, 1)" aria-label="Turunkan urutan {{ $r->nama }}" @disabled($r->id === $semua->last()->id)><x-ikon nama="editor-turun" :ukuran="14" /></button>
                                    </div>
                                </td>
                                <td>
                                    <div class="flex min-w-0 items-center gap-2.5">
                                        <span @class(['flex size-8 shrink-0 items-center justify-center rounded-lg', 'bg-brand-50 text-brand' => $r->aktif, 'bg-gray-100 text-subtle' => ! $r->aktif])><x-ikon nama="admin-addons" :ukuran="16" /></span>
                                        <div class="flex min-w-0 flex-col gap-0.5">
                                            <div class="flex items-center gap-1.5"><span class="font-semibold text-ink">{{ $r->nama }}</span>@if ($sys)<span class="tag" title="Dipakai perhitungan sistem, tidak bisa dihapus">Sistem</span>@endif</div>
                                            <div class="text-xs text-muted">{{ $r->deskripsi }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap"><span class="font-semibold text-ink">{{ $harga($r->harga) }}</span><span class="text-xs text-muted">{{ $r->satuan }}</span></td>
                                <td><div class="flex flex-wrap gap-1">@foreach ($aturan($r) as $t)<span class="tag">{{ $t }}</span>@endforeach</div></td>
                                <td>{{ number_format((int) ($pemilik[$r->kode] ?? 0), 0, ',', '.') }}</td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <button type="button" role="switch" class="sakelar" aria-checked="{{ $r->aktif ? 'true' : 'false' }}" aria-label="Aktifkan {{ $r->nama }}" wire:click="ubahAktif({{ $r->id }})"></button>
                                        <span @class(['text-xs font-medium', 'text-[#067647]' => $r->aktif, 'text-muted' => ! $r->aktif])>{{ $r->aktif ? 'Aktif' : 'Nonaktif' }}</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="flex gap-1">
                                        <button type="button" class="btn-icon" wire:click="bukaEdit({{ $r->id }})" aria-label="Edit {{ $r->nama }}" title="Edit"><x-ikon nama="bagikan-edit" :ukuran="14" /></button>
                                        @if (! $sys)
                                            <button type="button" class="btn-icon text-[#b42318]" wire:click="tanyaHapus({{ $r->id }})" aria-label="Hapus {{ $r->nama }}" title="Hapus"><x-ikon nama="bagikan-hapus" :ukuran="14" /></button>
                                        @else
                                            <span class="inline-flex size-[34px] items-center justify-center text-muted" role="img" aria-label="Add-on sistem, tidak bisa dihapus" title="Add-on sistem, tidak bisa dihapus"><x-ikon nama="editor-kunci" :ukuran="14" /></span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <div class="flex items-start gap-2.5 rounded-xl border border-[#d9e4fb] bg-brand-50 px-4 py-3 text-[13px] leading-5 text-gray-700">
        <x-ikon nama="peringatan" :ukuran="16" class="mt-0.5 text-brand" />
        <div><b>Tambah kuota undangan</b> dan <b>Perpanjang masa aktif</b> dipakai sistem untuk menghitung kuota dan masa aktif pengguna, jadi tidak bisa dihapus. Kamu tetap bisa mengubah harga atau menonaktifkannya. Add-on yang sudah dibeli pengguna atau tercatat di pesanan juga tidak bisa dihapus: nonaktifkan saja.</div>
    </div>

    <x-dialog wire:model="dialogForm" :judul="$editAddon ? 'Edit add-on' : 'Tambah add-on'" :sub="$editAddon ? 'Kode: '.$editAddon->kode.(in_array($editAddon->kode, $sistemKode, true) ? ' · dipakai sistem' : '') : 'Isi detail add-on yang akan dijual.'" lebar="max-w-[640px]">
        @php $sysForm = $editAddon && in_array($editAddon->kode, $sistemKode, true); @endphp
        <div class="grid gap-3.5 sm:grid-cols-2">
            <div>
                <label for="af-nama" class="lbl">Nama add-on</label>
                <input id="af-nama" type="text" class="inp" maxlength="80" placeholder="Contoh: Domain kustom" wire:model="f.nama" @error('f.nama') aria-invalid="true" @enderror>
                @error('f.nama')<div class="galat mt-1" role="alert">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="af-harga" class="lbl">Harga (Rp)</label>
                <input id="af-harga" type="text" inputmode="numeric" class="inp" maxlength="9" placeholder="0" wire:model="f.harga" @error('f.harga') aria-invalid="true" @enderror>
                @error('f.harga')<div class="galat mt-1" role="alert">{{ $message }}</div>@enderror
            </div>
        </div>
        <div>
            <label for="af-desc" class="lbl">Deskripsi singkat</label>
            <input id="af-desc" type="text" class="inp" maxlength="100" placeholder="Contoh: Alamat sendiri, per tahun" wire:model="f.deskripsi" @error('f.deskripsi') aria-invalid="true" @enderror>
            <div class="hint mt-1">Tampil di bawah nama add-on. Maksimal 100 karakter.</div>
            @error('f.deskripsi')<div class="galat mt-1" role="alert">{{ $message }}</div>@enderror
        </div>
        <div>
            <span class="lbl" id="af-per-l">Jenis tagihan</span>
            <div class="seg" role="group" aria-labelledby="af-per-l">
                <button type="button" wire:click="$set('f.tahunan', false)" aria-pressed="{{ empty($f['tahunan']) ? 'true' : 'false' }}">Sekali bayar</button>
                <button type="button" wire:click="$set('f.tahunan', true)" aria-pressed="{{ ! empty($f['tahunan']) ? 'true' : 'false' }}">Per tahun</button>
            </div>
        </div>
        <div class="grid gap-3.5 sm:grid-cols-2">
            <div>
                <label for="af-efek" class="lbl">Efek setelah lunas</label>
                <select id="af-efek" class="inp" wire:model.live="f.efek_jenis" @disabled($sysForm)>
                    <option value="tidak">Hanya dicatat sebagai kepemilikan</option>
                    <option value="kuota_undangan">Tambah kuota undangan</option>
                    <option value="perpanjang_bulan">Perpanjang masa aktif (bulan)</option>
                    <option value="fitur">Buka fitur (kode fitur)</option>
                </select>
                @if ($sysForm)<div class="hint mt-1">Jenis efek add-on sistem terkunci.</div>@endif
            </div>
            @if (($f['efek_jenis'] ?? 'tidak') !== 'tidak')
                <div>
                    <label for="af-nilai" class="lbl">{{ ['kuota_undangan' => 'Jumlah undangan tambahan', 'perpanjang_bulan' => 'Jumlah bulan', 'fitur' => 'Kode fitur'][$f['efek_jenis']] ?? 'Nilai' }}</label>
                    <input id="af-nilai" type="text" class="inp" maxlength="40" wire:model="f.efek_nilai" @error('f.efek_nilai') aria-invalid="true" @enderror>
                    @error('f.efek_nilai')<div class="galat mt-1" role="alert">{{ $message }}</div>@enderror
                </div>
            @endif
        </div>
        <div>
            <span class="lbl">Aturan pembelian</span>
            <div class="flex flex-col divide-y divide-line rounded-xl border border-line">
                @foreach ([['multi', 'Bisa dibeli berkali-kali', 'Contoh: tambah kuota atau perpanjang masa aktif.'], ['butuh_premium', 'Butuh paket Premium', 'Hanya bisa dibeli oleh pengguna dengan paket berbayar aktif.'], ['termasuk_premium', 'Sudah termasuk Premium', 'Pengguna paket berbayar mendapatkannya tanpa membeli.']] as [$kunci, $judul, $ket])
                    <div class="flex items-center justify-between gap-3 px-3 py-2.5">
                        <div><div class="text-[13px] font-semibold text-ink" id="af-{{ $kunci }}-l">{{ $judul }}</div><div class="hint">{{ $ket }}</div></div>
                        <button type="button" role="switch" class="sakelar" aria-checked="{{ ! empty($f[$kunci]) ? 'true' : 'false' }}" aria-labelledby="af-{{ $kunci }}-l" wire:click="$toggle('f.{{ $kunci }}')"></button>
                    </div>
                @endforeach
            </div>
            @error('f.aturan')<div class="galat mt-1" role="alert">{{ $message }}</div>@enderror
        </div>
        <div class="flex items-center justify-between gap-3 rounded-xl border border-line px-3 py-2.5">
            <div><div class="text-[13px] font-semibold text-ink" id="af-aktif-l">Aktif</div><div class="hint">Add-on nonaktif tidak tampil dan tidak bisa dibeli.</div></div>
            <button type="button" role="switch" class="sakelar" aria-checked="{{ ! empty($f['aktif']) ? 'true' : 'false' }}" aria-labelledby="af-aktif-l" wire:click="$toggle('f.aktif')"></button>
        </div>
        <div>
            <span class="lbl">Pratinjau di Paket &amp; Billing</span>
            <div class="rounded-xl border border-line bg-white p-3">
                <div class="flex items-center gap-3">
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand"><x-ikon nama="admin-addons" :ukuran="16" /></span>
                    <div class="min-w-0 flex-1"><div class="truncate text-sm font-semibold text-ink">{{ trim($f['nama'] ?? '') ?: 'Nama add-on' }}</div><div class="truncate text-xs text-muted">{{ trim($f['deskripsi'] ?? '') ?: 'Deskripsi singkat' }}</div></div>
                    <div class="text-sm font-semibold text-ink">{{ $harga((int) preg_replace('/\D/', '', (string) ($f['harga'] ?? ''))) }}{{ ! empty($f['tahunan']) ? \App\Livewire\Admin\AddOns::SATUAN_TAHUN : '' }}</div>
                </div>
                <div class="mt-2 text-xs text-muted">
                    @if (empty($f['aktif']))Nonaktif: tidak tampil untuk pengguna.
                    @elseif (! empty($f['butuh_premium']) && ! empty($f['termasuk_premium']))Aturan bertentangan, perbaiki sebelum menyimpan.
                    @elseif (! empty($f['termasuk_premium']))Pengguna paket berbayar melihat label "Termasuk" tanpa tombol beli.
                    @elseif (! empty($f['butuh_premium']))Pengguna Gratis melihat add-on ini terkunci.
                    @else Semua pengguna bisa menambahkannya ke Checkout.
                    @endif
                </div>
            </div>
        </div>
        @if ($errors->any())
            <div class="flex items-center gap-2 rounded-xl bg-[#fef3f2] px-3 py-2.5 text-[13px] text-[#b42318]" role="alert"><x-ikon nama="peringatan" :ukuran="16" /> Periksa kembali isian yang ditandai merah.</div>
        @endif
        <x-slot:footer>
            <button type="button" class="btn btn-secondary" x-on:click="$wire.dialogForm = false">Batal</button>
            <button type="button" class="btn btn-primary" wire:click="simpan" wire:loading.attr="disabled" wire:target="simpan">{{ $editAddon ? 'Simpan perubahan' : 'Tambah add-on' }}</button>
        </x-slot:footer>
    </x-dialog>

    <x-dialog wire:model="dialogHapus" :judul="'Hapus add-on '.($hapusAddon->nama ?? '').'?'" lebar="max-w-[480px]">
        <p class="m-0 text-sm leading-6 text-gray-700">Add-on dihapus dari katalog dan keranjang pengguna. Add-on ini belum pernah dibeli. Untuk menyembunyikan sementara, matikan saja statusnya.</p>
        <x-slot:footer>
            <button type="button" class="btn btn-secondary" x-on:click="$wire.dialogHapus = false">Batal</button>
            <button type="button" class="btn btn-danger-solid" wire:click="hapus" wire:loading.attr="disabled" wire:target="hapus">Hapus add-on</button>
        </x-slot:footer>
    </x-dialog>

    <x-admin.dialog-konfirmasi-sandi :label="$tertundaLabel" :pesan="$tertundaPesan" />
</div>
