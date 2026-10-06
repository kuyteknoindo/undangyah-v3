@php
    $sistemKode = \App\Livewire\Admin\Product::SISTEM;
    $masa = fn ($p) => $p->durasi_bulan > 0 ? $p->durasi_bulan.' bulan' : 'Selamanya';
    $harga = fn ($n) => 'Rp '.number_format((int) $n, 0, ',', '.');
    $statusPill = ['aktif' => ['hijau', 'Aktif'], 'draf' => ['kuning', 'Draf'], 'arsip' => ['abu', 'Arsip']];
@endphp
<div class="mx-auto flex max-w-6xl flex-col gap-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <p class="sub m-0 max-w-2xl">Atur paket langganan yang dijual: harga, masa aktif, batas undangan dan foto, serta daftar fitur. Paket berstatus Aktif tampil di Paket &amp; Billing dan Checkout pengguna.</p>
        <button type="button" class="btn btn-primary" wire:click="bukaBaru"><x-ikon nama="tambah" :ukuran="16" /> Tambah paket</button>
    </div>

    <section aria-label="Ringkasan" class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="kartu kpi"><div class="kpi-l">Total paket</div><div class="kpi-v">{{ $hitung['semua'] }}</div><div class="hint">{{ $hitung['aktif'] }} aktif · {{ $hitung['draf'] }} draf · {{ $hitung['arsip'] }} arsip</div></div>
        <div class="kartu kpi"><div class="kpi-l">Paket aktif</div><div class="kpi-v">{{ $hitung['aktif'] }}</div><div class="hint">Tampil di halaman harga</div></div>
        <div class="kartu kpi"><div class="kpi-l">Harga tertinggi</div><div class="kpi-v">{{ $tertinggi ? $harga($tertinggi->harga) : '–' }}</div><div class="hint">{{ $tertinggi ? 'Paket '.$tertinggi->nama : 'Belum ada paket aktif' }}</div></div>
        <div class="kartu kpi"><div class="kpi-l">Paket populer</div><div class="kpi-v">{{ $populerPaket?->nama ?? '–' }}</div><div class="hint">Diberi sorotan di halaman harga</div></div>
    </section>

    <div class="flex items-start gap-2.5 rounded-xl border border-[#d9e4fb] bg-brand-50 px-4 py-3 text-[13px] leading-5 text-gray-700">
        <x-ikon nama="peringatan" :ukuran="16" class="mt-0.5 text-brand" />
        <div><b>Gratis</b> dan <b>Premium</b> adalah paket sistem: dipakai untuk menghitung kuota, masa aktif, dan tagihan Checkout, sehingga tidak bisa dihapus atau diarsipkan. Paket yang sudah dimiliki pengguna atau tercatat di pesanan hanya bisa diarsipkan, tidak dihapus. Kode sistem paket tidak berubah setelah dibuat.</div>
    </div>

    <section class="kartu overflow-hidden" aria-labelledby="pv-h">
        <div class="border-b border-line p-4 sm:p-5">
            <h2 id="pv-h" class="h2">Pratinjau halaman harga</h2>
            <div class="sub">Urutan dan isi kartu sama seperti yang dilihat pengguna di Paket &amp; Billing. Paket draf diberi tanda dan belum tampil.</div>
        </div>
        @if ($kartu->isEmpty())
            <x-kosong judul="Belum ada paket yang tampil" teks="Aktifkan atau pulihkan paket dari tabel di bawah." ikon="admin-product" />
        @else
            <div class="grid gap-4 p-4 sm:p-5 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($kartu as $c)
                    <article wire:key="kartu-{{ $c->id }}" aria-label="Paket {{ $c->nama }}" @class(['flex flex-col gap-3.5 rounded-2xl border bg-white p-5', 'border-brand shadow-[0_0_0_1px_#1D4ED8]' => $c->populer, 'border-line' => ! $c->populer && $c->status === 'aktif', 'border-dashed border-line-strong bg-[#fcfcfd]' => $c->status === 'draf'])>
                        <div class="flex flex-wrap items-center gap-1.5">
                            @if ($c->populer)<x-pill warna="biru">Paling populer</x-pill>@endif
                            @if ($c->status === 'draf')<x-pill warna="kuning">Draf · belum tampil</x-pill>@endif
                            @if (in_array($c->kode, $sistemKode, true))<span class="tag">Paket sistem</span>@endif
                            <span class="tag">{{ $c->kode }}</span>
                        </div>
                        <div>
                            <div class="text-base font-semibold text-ink">{{ $c->nama }}</div>
                            <div class="sub">{{ $c->tagline ?: '–' }}</div>
                        </div>
                        <div>
                            <div class="flex items-baseline gap-2"><span class="text-2xl font-semibold tracking-tight text-ink">{{ $harga($c->harga) }}</span>@if ($c->harga_coret > $c->harga)<s class="text-sm text-muted">{{ $harga($c->harga_coret) }}</s>@endif</div>
                            <div class="hint">{{ $c->durasi_bulan > 0 ? 'per '.$c->durasi_bulan.' bulan' : ($c->harga > 0 ? 'sekali bayar, selamanya' : 'selamanya') }}</div>
                        </div>
                        <ul class="m-0 flex list-none flex-col gap-1.5 p-0 text-[13px] leading-5 text-gray-700">
                            @foreach ($c->fitur ?? [] as $t)
                                <li class="flex gap-2"><x-ikon nama="centang" :ukuran="14" class="mt-0.5 text-[#067647]" /><span>{{ $t }}</span></li>
                            @endforeach
                        </ul>
                        <div class="mt-auto flex flex-wrap gap-1.5">
                            <span class="tag">{{ $c->kuota_undangan }} undangan aktif</span>
                            <span class="tag">{{ $c->kuota_tamu === null ? 'Tamu tanpa batas' : number_format($c->kuota_tamu, 0, ',', '.').' tamu' }}</span>
                            <span class="tag">{{ $c->kuota_foto }} foto</span>
                            <span class="tag">{{ $masa($c) }}</span>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="bukaEdit({{ $c->id }})"><x-ikon nama="bagikan-edit" :ukuran="14" /> Edit</button>
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="duplikat({{ $c->id }})"><x-ikon nama="bagikan-duplikat" :ukuran="14" /> Duplikat</button>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    <section class="kartu overflow-hidden" aria-labelledby="tb-h">
        <div class="flex flex-col gap-3 border-b border-line p-4 sm:p-5">
            <div>
                <h2 id="tb-h" class="h2">Semua paket</h2>
                <div class="sub">{{ $baris->count() }} dari {{ $hitung['semua'] }} paket · urutan menentukan posisi kartu di halaman harga</div>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <div class="relative w-full sm:max-w-xs">
                    <label for="pq" class="sr-only">Cari paket</label>
                    <span class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-muted"><x-ikon nama="bagikan-cari" :ukuran="16" /></span>
                    <input id="pq" type="search" class="inp pl-9" maxlength="60" placeholder="Cari nama atau kode" wire:model.live.debounce.300ms="q" autocomplete="off">
                </div>
                <div class="seg flex-wrap" role="group" aria-label="Filter status">
                    @foreach (['semua' => 'Semua', 'aktif' => 'Aktif', 'draf' => 'Draf', 'arsip' => 'Arsip'] as $k => $label)
                        <button type="button" wire:click="atur('{{ $k }}')" aria-pressed="{{ $fs === $k ? 'true' : 'false' }}">{{ $label }} <span class="text-muted">{{ $hitung[$k] }}</span></button>
                    @endforeach
                </div>
            </div>
        </div>
        @if ($baris->isEmpty())
            <x-kosong judul="Tidak ada paket yang cocok" teks="Ubah kata kunci atau filter status." ikon="bagikan-cari">
                <button type="button" class="btn btn-secondary btn-sm" wire:click="resetFilter">Reset filter</button>
            </x-kosong>
        @else
            <div class="overflow-x-auto">
                <table class="tabel">
                    <caption class="sr-only">Daftar paket langganan</caption>
                    <thead><tr><th>Urutan</th><th>Paket</th><th>Harga</th><th>Masa aktif &amp; batas</th><th>Pengguna</th><th>Aksi</th></tr></thead>
                    <tbody>
                        @foreach ($baris as $r)
                            @php $sys = in_array($r->kode, $sistemKode, true); @endphp
                            <tr wire:key="p-{{ $r->id }}">
                                <td>
                                    <div class="flex gap-1">
                                        <button type="button" class="btn-icon" wire:click="geser({{ $r->id }}, -1)" aria-label="Naikkan urutan {{ $r->nama }}" @disabled($r->id === $semua->first()->id)><x-ikon nama="editor-naik" :ukuran="14" /></button>
                                        <button type="button" class="btn-icon" wire:click="geser({{ $r->id }}, 1)" aria-label="Turunkan urutan {{ $r->nama }}" @disabled($r->id === $semua->last()->id)><x-ikon nama="editor-turun" :ukuran="14" /></button>
                                    </div>
                                </td>
                                <td>
                                    <div class="flex min-w-0 items-center gap-2.5">
                                        <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand"><x-ikon nama="admin-product" :ukuran="16" /></span>
                                        <div class="flex min-w-0 flex-col gap-0.5">
                                            <div class="flex items-center gap-1.5"><span class="font-semibold text-ink">{{ $r->nama }}</span>@if ($r->populer)<span class="tag text-[#8a5a00]">Populer</span>@endif</div>
                                            <div class="flex items-center gap-1.5 text-xs text-muted"><span>{{ $r->kode }}@if ($r->sku) · {{ $r->sku }}@endif</span><x-pill :warna="$statusPill[$r->status][0]">{{ $statusPill[$r->status][1] }}</x-pill></div>
                                        </div>
                                    </div>
                                </td>
                                <td><div class="font-semibold text-ink">{{ $harga($r->harga) }}</div>@if ($r->harga_coret > $r->harga)<s class="text-xs text-muted">{{ $harga($r->harga_coret) }}</s>@endif</td>
                                <td><div>{{ $masa($r) }}</div><div class="text-xs text-muted">{{ $r->kuota_undangan }} undangan · {{ $r->kuota_tamu === null ? 'tamu tanpa batas' : number_format($r->kuota_tamu, 0, ',', '.').' tamu' }} · {{ $r->kuota_foto }} foto · {{ count($r->fitur ?? []) }} fitur</div></td>
                                <td>{{ number_format((int) ($pengguna[$r->kode] ?? 0), 0, ',', '.') }}</td>
                                <td>
                                    <div class="flex flex-wrap gap-1">
                                        <button type="button" class="btn-icon {{ $r->populer ? 'text-[#b7791f] bg-[#fff8e6] border-[#f5d99a]' : '' }}" wire:click="populer({{ $r->id }})" aria-label="{{ $r->populer ? 'Lepas tanda populer '.$r->nama : 'Tandai '.$r->nama.' sebagai populer' }}" title="{{ $r->populer ? 'Lepas tanda populer' : 'Tandai populer' }}"><x-ikon nama="{{ $r->populer ? 'katalog-bintang-isi' : 'bagikan-bintang' }}" :ukuran="14" /></button>
                                        <button type="button" class="btn-icon" wire:click="bukaEdit({{ $r->id }})" aria-label="Edit {{ $r->nama }}" title="Edit"><x-ikon nama="bagikan-edit" :ukuran="14" /></button>
                                        <button type="button" class="btn-icon" wire:click="duplikat({{ $r->id }})" aria-label="Duplikat {{ $r->nama }}" title="Duplikat"><x-ikon nama="bagikan-duplikat" :ukuran="14" /></button>
                                        @if (! $sys && $r->status !== 'arsip')
                                            <button type="button" class="btn-icon" wire:click="tanyaArsip({{ $r->id }})" aria-label="Arsipkan {{ $r->nama }}" title="Arsipkan"><x-ikon nama="katalog-arsip" :ukuran="14" /></button>
                                        @endif
                                        @if (! $sys && $r->status === 'arsip')
                                            <button type="button" class="btn-icon" wire:click="pulihkan({{ $r->id }})" aria-label="Pulihkan {{ $r->nama }} sebagai draf" title="Pulihkan"><x-ikon nama="katalog-pulihkan" :ukuran="14" /></button>
                                        @endif
                                        @if (! $sys)
                                            <button type="button" class="btn-icon text-[#b42318]" wire:click="tanyaHapus({{ $r->id }})" aria-label="Hapus {{ $r->nama }}" title="Hapus"><x-ikon nama="bagikan-hapus" :ukuran="14" /></button>
                                        @else
                                            <span class="inline-flex size-[34px] items-center justify-center text-muted" role="img" aria-label="Paket sistem, tidak bisa diarsipkan atau dihapus" title="Paket sistem, tidak bisa diarsipkan atau dihapus"><x-ikon nama="editor-kunci" :ukuran="14" /></span>
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

    <x-dialog wire:model="dialogForm" :judul="$editPaket ? 'Edit paket '.$editPaket->nama : 'Tambah paket'" :sub="$editPaket ? 'Kode sistem: '.$editPaket->kode : 'Paket baru disimpan sebagai draf sampai kamu mengaktifkannya.'" lebar="max-w-[680px]">
        @php $sysForm = $editPaket && in_array($editPaket->kode, $sistemKode, true); @endphp
        <div class="grid gap-3.5 sm:grid-cols-2">
            <div>
                <label for="pf-nama" class="lbl">Nama paket</label>
                <input id="pf-nama" type="text" class="inp" maxlength="60" placeholder="Contoh: Premium" wire:model="f.nama" @error('f.nama') aria-invalid="true" @enderror>
                @error('f.nama')<div class="galat mt-1" role="alert">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="pf-sku" class="lbl">Kode SKU (opsional)</label>
                <input id="pf-sku" type="text" class="inp uppercase" maxlength="20" placeholder="P-PREM" wire:model="f.sku" @error('f.sku') aria-invalid="true" @enderror>
                <div class="hint mt-1">Huruf besar, angka, dan strip. Unik.</div>
                @error('f.sku')<div class="galat mt-1" role="alert">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="pf-harga" class="lbl">Harga (Rp)</label>
                <input id="pf-harga" type="text" inputmode="numeric" class="inp" maxlength="9" placeholder="0" wire:model="f.harga" @error('f.harga') aria-invalid="true" @enderror>
                <div class="hint mt-1">Isi 0 untuk paket gratis. Hanya paket aktif berharga di atas 0 yang bisa dibeli.</div>
                @error('f.harga')<div class="galat mt-1" role="alert">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="pf-coret" class="lbl">Harga coret (opsional)</label>
                <input id="pf-coret" type="text" inputmode="numeric" class="inp" maxlength="9" placeholder="Kosongkan bila tidak ada" wire:model="f.harga_coret" @error('f.harga_coret') aria-invalid="true" @enderror>
                <div class="hint mt-1">Ditampilkan dicoret di samping harga.</div>
                @error('f.harga_coret')<div class="galat mt-1" role="alert">{{ $message }}</div>@enderror
            </div>
        </div>
        <div class="grid gap-3.5 sm:grid-cols-2">
            <div>
                <label for="pf-masa" class="lbl">Masa aktif (bulan)</label>
                <input id="pf-masa" type="text" inputmode="numeric" class="inp" maxlength="3" wire:model="f.durasi_bulan" @error('f.durasi_bulan') aria-invalid="true" @enderror>
                <div class="hint mt-1">0 = selamanya.</div>
                @error('f.durasi_bulan')<div class="galat mt-1" role="alert">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="pf-kuota" class="lbl">Undangan aktif</label>
                <input id="pf-kuota" type="text" inputmode="numeric" class="inp" maxlength="3" wire:model="f.kuota_undangan" @error('f.kuota_undangan') aria-invalid="true" @enderror>
                @error('f.kuota_undangan')<div class="galat mt-1" role="alert">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="pf-tamu" class="lbl">Tamu per undangan</label>
                <input id="pf-tamu" type="text" inputmode="numeric" class="inp" maxlength="6" placeholder="Kosong = tanpa batas" wire:model="f.kuota_tamu" @error('f.kuota_tamu') aria-invalid="true" @enderror>
                @error('f.kuota_tamu')<div class="galat mt-1" role="alert">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="pf-foto" class="lbl">Maks foto galeri</label>
                <input id="pf-foto" type="text" inputmode="numeric" class="inp" maxlength="3" wire:model="f.kuota_foto" @error('f.kuota_foto') aria-invalid="true" @enderror>
                @error('f.kuota_foto')<div class="galat mt-1" role="alert">{{ $message }}</div>@enderror
            </div>
        </div>
        <div>
            <label for="pf-tag" class="lbl">Tagline</label>
            <input id="pf-tag" type="text" class="inp" maxlength="80" placeholder="Contoh: Paling banyak dipilih untuk pernikahan" wire:model="f.tagline" @error('f.tagline') aria-invalid="true" @enderror>
            @error('f.tagline')<div class="galat mt-1" role="alert">{{ $message }}</div>@enderror
        </div>
        <div>
            <div class="mb-1.5 flex items-center justify-between"><span class="lbl m-0" id="pf-fit-l">Daftar fitur</span><span class="hint">{{ count($fitur) }} fitur · tampil berurutan di kartu</span></div>
            <ul class="m-0 flex list-none flex-col gap-2 p-0" aria-labelledby="pf-fit-l">
                @foreach ($fitur as $i => $t)
                    <li class="flex items-center gap-1.5" wire:key="ft-{{ $i }}">
                        <span class="w-5 shrink-0 text-center text-xs text-muted">{{ $i + 1 }}</span>
                        <label for="pf-fit-{{ $i }}" class="sr-only">Fitur {{ $i + 1 }}</label>
                        <input id="pf-fit-{{ $i }}" type="text" class="inp" maxlength="80" wire:model="fitur.{{ $i }}">
                        <button type="button" class="btn-icon" wire:click="geserFitur({{ $i }}, -1)" aria-label="Naikkan fitur {{ $i + 1 }}" @disabled($i === 0)><x-ikon nama="editor-naik" :ukuran="14" /></button>
                        <button type="button" class="btn-icon" wire:click="geserFitur({{ $i }}, 1)" aria-label="Turunkan fitur {{ $i + 1 }}" @disabled($i === count($fitur) - 1)><x-ikon nama="editor-turun" :ukuran="14" /></button>
                        <button type="button" class="btn-icon text-[#b42318]" wire:click="hapusFitur({{ $i }})" aria-label="Hapus fitur {{ $i + 1 }}"><x-ikon nama="bagikan-hapus" :ukuran="14" /></button>
                    </li>
                @endforeach
            </ul>
            <div class="mt-2 flex items-center gap-2">
                <label for="pf-new" class="sr-only">Fitur baru</label>
                <input id="pf-new" type="text" class="inp" maxlength="80" placeholder="Tulis fitur baru lalu tekan Enter" wire:model="fiturBaru" wire:keydown.enter.prevent="tambahFitur">
                <button type="button" class="btn btn-secondary" wire:click="tambahFitur"><x-ikon nama="tambah" :ukuran="14" /> Tambah</button>
            </div>
            @error('fitur')<div class="galat mt-1" role="alert">{{ $message }}</div>@enderror
            @error('fitur.*')<div class="galat mt-1" role="alert">{{ $message }}</div>@enderror
        </div>
        <div class="grid gap-3.5 sm:grid-cols-2">
            <div>
                <span class="lbl" id="pf-st-l">Status</span>
                @if ($sysForm)
                    <x-pill warna="hijau">Aktif</x-pill> <span class="hint">Paket sistem selalu aktif.</span>
                @else
                    <div class="seg" role="group" aria-labelledby="pf-st-l">
                        @foreach (['aktif' => 'Aktif', 'draf' => 'Draf', 'arsip' => 'Arsip'] as $k => $label)
                            <button type="button" wire:click="$set('f.status', '{{ $k }}')" aria-pressed="{{ ($f['status'] ?? '') === $k ? 'true' : 'false' }}">{{ $label }}</button>
                        @endforeach
                    </div>
                    <div class="hint mt-1">{{ ['aktif' => 'Tampil di halaman harga dan bisa dibeli.', 'draf' => 'Tersimpan, tampil sebagai "Segera hadir" dan belum bisa dibeli.', 'arsip' => 'Disembunyikan. Pesanan lama tetap tercatat.'][$f['status'] ?? 'draf'] ?? '' }}</div>
                @endif
                @error('f.status')<div class="galat mt-1" role="alert">{{ $message }}</div>@enderror
            </div>
            <div class="flex items-start justify-between gap-3 rounded-xl border border-line p-3">
                <div><div class="text-[13px] font-semibold text-ink" id="pf-pop-l">Tandai populer</div><div class="hint">Hanya satu paket yang bisa populer.</div></div>
                <button type="button" role="switch" class="sakelar" aria-checked="{{ ! empty($f['populer']) ? 'true' : 'false' }}" aria-labelledby="pf-pop-l" wire:click="$toggle('f.populer')"></button>
            </div>
        </div>
        @if ($sysForm)
            <div class="flex items-start gap-2 rounded-xl bg-gray-50 px-3 py-2.5 text-[13px] leading-5 text-gray-700"><x-ikon nama="editor-kunci" :ukuran="16" class="mt-0.5" /><div>Paket <b>{{ $f['nama'] }}</b> dipakai sistem (kuota, masa aktif, dan Checkout), jadi tidak bisa dihapus atau diarsipkan. Harga, batas, dan fiturnya tetap bisa diubah.</div></div>
        @endif
        @if ($errors->any())
            <div class="flex items-center gap-2 rounded-xl bg-[#fef3f2] px-3 py-2.5 text-[13px] text-[#b42318]" role="alert"><x-ikon nama="peringatan" :ukuran="16" /> Periksa kembali isian yang ditandai merah.</div>
        @endif
        <x-slot:footer>
            <button type="button" class="btn btn-secondary" x-on:click="$wire.dialogForm = false">Batal</button>
            <button type="button" class="btn btn-primary" wire:click="simpan" wire:loading.attr="disabled" wire:target="simpan">{{ $editPaket ? 'Simpan perubahan' : 'Tambah paket' }}</button>
        </x-slot:footer>
    </x-dialog>

    <x-dialog wire:model="dialogKonfirmasi" :judul="$aksiJenis === 'hapus' ? 'Hapus paket '.($aksiPaket->nama ?? '').'?' : 'Arsipkan paket '.($aksiPaket->nama ?? '').'?'" lebar="max-w-[480px]">
        <p class="m-0 text-sm leading-6 text-gray-700">
            @if ($aksiJenis === 'hapus')
                Paket akan dihapus permanen dari katalog. Paket ini belum pernah dipakai pengguna atau pesanan. Jika hanya ingin menyembunyikan, pilih Arsipkan.
            @else
                Paket tidak lagi tampil di halaman harga dan tidak bisa dibeli. Pengguna yang sudah memilikinya tetap memakainya sampai masa aktif habis, dan pesanan lama tetap tercatat.
            @endif
        </p>
        <x-slot:footer>
            <button type="button" class="btn btn-secondary" x-on:click="$wire.dialogKonfirmasi = false">Batal</button>
            <button type="button" class="btn {{ $aksiJenis === 'hapus' ? 'btn-danger-solid' : 'btn-primary' }}" wire:click="jalankanAksi" wire:loading.attr="disabled" wire:target="jalankanAksi">{{ $aksiJenis === 'hapus' ? 'Hapus paket' : 'Arsipkan paket' }}</button>
        </x-slot:footer>
    </x-dialog>

    <x-admin.dialog-konfirmasi-sandi :label="$tertundaLabel" :pesan="$tertundaPesan" />
</div>
