@php
    $harga = fn ($n) => 'Rp '.number_format((int) $n, 0, ',', '.');
    $zona = config('app.timezone');
    $rel = function ($v) use ($zona) {
        if (! $v->berlaku_sampai) { return 'Tanpa batas waktu'; }
        $hari = (int) now($zona)->startOfDay()->diffInDays($v->berlaku_sampai->copy()->timezone($zona)->startOfDay(), false);

        return $hari < 0 ? 'Lewat '.(-$hari).' hari' : ($hari === 0 ? 'Berakhir hari ini' : $hari.' hari lagi');
    };
@endphp
<div class="mx-auto flex max-w-6xl flex-col gap-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <p class="sub m-0 max-w-2xl">Kode diskon yang bisa dimasukkan pengguna di Checkout. Status Habis dan Kedaluwarsa dihitung otomatis dari kuota dan tanggal berlaku. Jumlah terpakai bertambah saat pesanan lunas dan kembali bila pesanan batal atau kedaluwarsa.</p>
        <button type="button" class="btn btn-primary" wire:click="bukaBaru"><x-ikon nama="tambah" :ukuran="16" /> Buat voucher</button>
    </div>

    <section aria-label="Ringkasan" class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="kartu kpi"><div class="kpi-l">Total voucher</div><div class="kpi-v">{{ $hitung['semua'] }}</div><div class="hint">{{ $hitung['nonaktif'] }} dimatikan manual</div></div>
        <div class="kartu kpi"><div class="kpi-l">Aktif</div><div class="kpi-v">{{ $hitung['aktif'] }}</div><div class="hint">Bisa dipakai di Checkout</div></div>
        <div class="kartu kpi"><div class="kpi-l">Habis / kedaluwarsa</div><div class="kpi-v">{{ $hitung['habis'] + $hitung['kedaluwarsa'] }}</div><div class="hint">{{ $hitung['habis'] }} habis · {{ $hitung['kedaluwarsa'] }} kedaluwarsa</div></div>
        <div class="kartu kpi"><div class="kpi-l">Total pemakaian</div><div class="kpi-v">{{ number_format($terpakai, 0, ',', '.') }}</div><div class="hint">dari {{ number_format($kuotaTotal, 0, ',', '.') }} total kuota</div></div>
    </section>

    <section class="kartu overflow-hidden" aria-label="Daftar voucher">
        <div class="flex flex-col gap-3 border-b border-line p-4 sm:p-5">
            <div class="flex flex-wrap items-center gap-3">
                <div class="relative w-full sm:max-w-xs">
                    <label for="vq" class="sr-only">Cari kode voucher</label>
                    <span class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-muted"><x-ikon nama="bagikan-cari" :ukuran="16" /></span>
                    <input id="vq" type="search" class="inp pl-9" maxlength="40" placeholder="Cari kode voucher" wire:model.live.debounce.300ms="q" autocomplete="off">
                </div>
                <button type="button" class="btn btn-secondary btn-sm sm:ml-auto" wire:click="ekspor" wire:loading.attr="disabled" wire:target="ekspor" @disabled($total === 0)><x-ikon nama="bagikan-unduh" :ukuran="14" /> Ekspor CSV ({{ $total }})</button>
            </div>
            <div class="seg flex-wrap self-start" role="group" aria-label="Filter status">
                <button type="button" wire:click="atur('semua')" aria-pressed="{{ $fs === 'semua' ? 'true' : 'false' }}">Semua <span class="text-muted">{{ $hitung['semua'] }}</span></button>
                @foreach (\App\Livewire\Admin\Vouchers::STATUS as $k => [$warna, $label])
                    <button type="button" wire:click="atur('{{ $k }}')" aria-pressed="{{ $fs === $k ? 'true' : 'false' }}">{{ $label }} <span class="text-muted">{{ $hitung[$k] }}</span></button>
                @endforeach
            </div>
        </div>
        @if ($hitung['semua'] === 0)
            <x-kosong judul="Belum ada voucher" teks="Buat voucher pertama untuk promo atau kerja sama." ikon="admin-voucher">
                <button type="button" class="btn btn-primary btn-sm" wire:click="bukaBaru">Buat voucher</button>
            </x-kosong>
        @elseif ($total === 0)
            <x-kosong judul="Tidak ada voucher yang cocok" teks="Ubah kata kunci atau filter status." ikon="bagikan-cari">
                <button type="button" class="btn btn-secondary btn-sm" wire:click="resetFilter">Reset filter</button>
            </x-kosong>
        @else
            <div class="overflow-x-auto">
                <table class="tabel">
                    <caption class="sr-only">Daftar voucher</caption>
                    <thead><tr><th>Kode</th><th>Diskon</th><th>Berlaku untuk</th><th>Pemakaian</th><th>Berlaku sampai</th><th>Status</th><th>Aksi</th></tr></thead>
                    <tbody>
                        @foreach ($baris as $v)
                            @php
                                $st = $v->status();
                                $pct = $v->batas_pakai ? min(100, (int) round($v->terpakai / $v->batas_pakai * 100)) : 0;
                            @endphp
                            <tr wire:key="v-{{ $v->id }}">
                                <td>
                                    <div class="flex items-center gap-1.5">
                                        <span class="font-mono text-[13px] font-semibold tracking-wide text-ink">{{ $v->kode }}</span>
                                        <button type="button" class="btn-icon !size-7" x-data x-on:click="navigator.clipboard?.writeText(@js($v->kode)); $dispatch('toast', { pesan: 'Kode {{ $v->kode }} disalin.', tipe: 'sukses' })" aria-label="Salin kode {{ $v->kode }}" title="Salin kode"><x-ikon nama="bagikan-salin" :ukuran="13" /></button>
                                    </div>
                                </td>
                                <td>
                                    <div class="font-semibold text-ink">{{ $v->jenis === 'persen' ? $v->nilai.'%' : $harga($v->nilai) }}</div>
                                    <div class="text-xs text-muted">{{ $v->jenis === 'persen' ? 'Diskon persen' : 'Potongan nominal' }}{{ $v->min_belanja ? ' · min. '.$harga($v->min_belanja) : '' }}{{ $v->jenis === 'persen' && $v->maks_diskon ? ' · maks. '.$harga($v->maks_diskon) : '' }}</div>
                                </td>
                                <td>{{ $v->paket_berlaku ? collect($v->paket_berlaku)->map(fn ($k) => $namaPaket[$k] ?? $k)->implode(', ') : 'Semua paket' }}</td>
                                <td class="min-w-36">
                                    <div class="flex items-baseline justify-between gap-2"><span class="font-medium text-ink">{{ number_format($v->terpakai, 0, ',', '.') }} / {{ $v->batas_pakai === null ? '∞' : number_format($v->batas_pakai, 0, ',', '.') }}</span><span class="text-xs text-muted">{{ $v->batas_pakai === null ? 'tanpa batas' : ($v->batas_pakai - $v->terpakai > 0 ? 'sisa '.number_format($v->batas_pakai - $v->terpakai, 0, ',', '.') : 'kuota habis') }}</span></div>
                                    @if ($v->batas_pakai)
                                        <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-gray-100" role="progressbar" aria-label="Pemakaian {{ $v->kode }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $pct }}"><div @class(['h-full rounded-full', 'bg-brand' => $pct < 80, 'bg-[#fdb022]' => $pct >= 80 && $pct < 100, 'bg-[#f79009]' => $pct >= 100]) style="width: {{ $pct }}%"></div></div>
                                    @endif
                                </td>
                                <td><div>{{ $v->berlaku_sampai ? $v->berlaku_sampai->timezone($zona)->locale('id')->translatedFormat('j M Y') : '–' }}</div><div class="text-xs {{ $v->berlaku_sampai && $v->berlaku_sampai->isPast() ? 'text-[#b42318]' : 'text-muted' }}">{{ $rel($v) }}</div></td>
                                <td><x-pill :warna="\App\Livewire\Admin\Vouchers::STATUS[$st][0]">{{ \App\Livewire\Admin\Vouchers::STATUS[$st][1] }}</x-pill></td>
                                <td>
                                    <div class="flex items-center gap-1">
                                        <button type="button" role="switch" class="sakelar mr-1" aria-checked="{{ $v->aktif ? 'true' : 'false' }}" aria-label="Aktifkan voucher {{ $v->kode }}" title="{{ $v->aktif ? 'Matikan voucher' : 'Nyalakan voucher' }}" wire:click="ubahAktif({{ $v->id }})"></button>
                                        <button type="button" class="btn-icon" wire:click="bukaEdit({{ $v->id }})" aria-label="Edit voucher {{ $v->kode }}" title="Edit"><x-ikon nama="bagikan-edit" :ukuran="14" /></button>
                                        <button type="button" class="btn-icon text-[#b42318]" wire:click="tanyaHapus({{ $v->id }})" aria-label="Hapus voucher {{ $v->kode }}" title="Hapus"><x-ikon nama="bagikan-hapus" :ukuran="14" /></button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-2 border-t border-line px-4 py-3 text-[13px] text-muted sm:px-5">
                <span>Menampilkan {{ $baris->count() }} dari {{ $total }} voucher ({{ $hitung['semua'] }} total). Ekspor CSV mengikuti filter yang aktif.</span>
                @if ($halaman > 1)
                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="sebelumnya" @disabled($halAktif <= 1) class="btn btn-secondary btn-sm" aria-label="Halaman sebelumnya">Sebelumnya</button>
                        <span>{{ $halAktif }} / {{ $halaman }}</span>
                        <button type="button" wire:click="berikutnya" @disabled($halAktif >= $halaman) class="btn btn-secondary btn-sm" aria-label="Halaman berikutnya">Berikutnya</button>
                    </div>
                @endif
            </div>
        @endif
    </section>

    <x-dialog wire:model="dialogForm" :judul="$editVoucher ? 'Edit voucher' : 'Buat voucher'" :sub="$editVoucher ? 'Perubahan langsung berlaku di Checkout.' : 'Kode bisa dibagikan ke pengguna untuk dipakai di Checkout.'" lebar="max-w-[640px]">
        @php $persen = ($f['jenis'] ?? 'persen') !== 'nominal'; @endphp
        <div>
            <label for="vf-kode" class="lbl">Kode voucher</label>
            <div class="flex gap-2">
                <input id="vf-kode" type="text" class="inp font-mono uppercase" maxlength="20" placeholder="HEMAT20" wire:model="f.kode" autocomplete="off" @error('f.kode') aria-invalid="true" @enderror>
                <button type="button" class="btn btn-secondary shrink-0" wire:click="acak" title="Buat kode acak"><x-ikon nama="katalog-acak" :ukuran="14" /> Acak</button>
            </div>
            <div class="hint mt-1">A-Z dan 0-9, 3-20 karakter, unik.</div>
            @error('f.kode')<div class="galat mt-1" role="alert">{{ $message }}</div>@enderror
        </div>
        <div class="grid gap-3.5 sm:grid-cols-2">
            <div>
                <span class="lbl" id="vf-jenis-l">Jenis diskon</span>
                <div class="seg" role="group" aria-labelledby="vf-jenis-l">
                    <button type="button" wire:click="setJenis('persen')" aria-pressed="{{ $persen ? 'true' : 'false' }}">Persen (%)</button>
                    <button type="button" wire:click="setJenis('nominal')" aria-pressed="{{ ! $persen ? 'true' : 'false' }}">Nominal (Rp)</button>
                </div>
            </div>
            <div>
                <label for="vf-nilai" class="lbl">{{ $persen ? 'Besar diskon (%)' : 'Besar potongan (Rp)' }}</label>
                <input id="vf-nilai" type="text" inputmode="numeric" class="inp" maxlength="{{ $persen ? 3 : 9 }}" placeholder="{{ $persen ? '20' : '50000' }}" wire:model="f.nilai" @error('f.nilai') aria-invalid="true" @enderror>
                <div class="hint mt-1">{{ $persen ? '1-100% dari subtotal.' : 'Maksimal sebesar subtotal pesanan.' }}</div>
                @error('f.nilai')<div class="galat mt-1" role="alert">{{ $message }}</div>@enderror
            </div>
        </div>
        <div class="grid gap-3.5 sm:grid-cols-2">
            @if ($persen)
                <div>
                    <label for="vf-maks" class="lbl">Maksimal diskon (opsional)</label>
                    <input id="vf-maks" type="text" inputmode="numeric" class="inp" maxlength="9" placeholder="Tanpa batas" wire:model="f.maks_diskon" @error('f.maks_diskon') aria-invalid="true" @enderror>
                    @error('f.maks_diskon')<div class="galat mt-1" role="alert">{{ $message }}</div>@enderror
                </div>
            @endif
            <div>
                <label for="vf-min" class="lbl">Minimal belanja (opsional)</label>
                <input id="vf-min" type="text" inputmode="numeric" class="inp" maxlength="9" placeholder="Tanpa minimal" wire:model="f.min_belanja" @error('f.min_belanja') aria-invalid="true" @enderror>
                @error('f.min_belanja')<div class="galat mt-1" role="alert">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="vf-kuota" class="lbl">Kuota pemakaian</label>
                <input id="vf-kuota" type="text" inputmode="numeric" class="inp" maxlength="7" placeholder="Kosong = tanpa batas" wire:model="f.batas_pakai" @error('f.batas_pakai') aria-invalid="true" @enderror>
                <div class="hint mt-1">Sudah terpakai: <b>{{ number_format((int) ($editVoucher->terpakai ?? 0), 0, ',', '.') }}</b>. Bertambah otomatis saat voucher dipakai.</div>
                @error('f.batas_pakai')<div class="galat mt-1" role="alert">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="vf-dari" class="lbl">Berlaku mulai (opsional)</label>
                <input id="vf-dari" type="date" class="inp" wire:model="f.dari" @error('f.dari') aria-invalid="true" @enderror>
                @error('f.dari')<div class="galat mt-1" role="alert">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="vf-sampai" class="lbl">Berlaku sampai (opsional)</label>
                <input id="vf-sampai" type="date" class="inp" wire:model="f.sampai" @error('f.sampai') aria-invalid="true" @enderror>
                <div class="hint mt-1">Berlaku sampai akhir hari itu. Kosongkan bila tanpa batas waktu.</div>
                @error('f.sampai')<div class="galat mt-1" role="alert">{{ $message }}</div>@enderror
            </div>
        </div>
        <fieldset class="m-0 min-w-0 border-0 p-0">
            <legend class="lbl">Berlaku untuk paket</legend>
            <div class="flex flex-wrap gap-2">
                @foreach ($namaPaket as $kode => $nama)
                    <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-line px-3 py-1.5 text-[13px] text-gray-700">
                        <input type="checkbox" value="{{ $kode }}" wire:model="f.paket" class="size-4 accent-[#1D4ED8]"> {{ $nama }}
                    </label>
                @endforeach
            </div>
            <div class="hint mt-1">Tidak ada yang dicentang = berlaku untuk semua paket.</div>
        </fieldset>
        <div class="flex items-center justify-between gap-3 rounded-xl border border-line px-3 py-2.5">
            <div><div class="text-[13px] font-semibold text-ink" id="vf-aktif-l">Aktifkan voucher</div><div class="hint">Matikan untuk menghentikan sementara tanpa menghapus.</div></div>
            <button type="button" role="switch" class="sakelar" aria-checked="{{ ! empty($f['aktif']) ? 'true' : 'false' }}" aria-labelledby="vf-aktif-l" wire:click="$toggle('f.aktif')"></button>
        </div>
        @if ($statusForm)
            <div class="flex flex-wrap items-center gap-2 rounded-xl bg-gray-50 px-3 py-2.5 text-[13px] text-gray-700">
                <span>Status setelah disimpan</span> <x-pill :warna="$statusForm[1]">{{ $statusForm[2] }}</x-pill> <span class="text-muted">{{ $statusForm[3] }}</span>
            </div>
        @endif
        @if ($errors->any())
            <div class="flex items-center gap-2 rounded-xl bg-[#fef3f2] px-3 py-2.5 text-[13px] text-[#b42318]" role="alert"><x-ikon nama="peringatan" :ukuran="16" /> Periksa kembali isian yang ditandai merah.</div>
        @endif
        <x-slot:footer>
            <button type="button" class="btn btn-secondary" x-on:click="$wire.dialogForm = false">Batal</button>
            <button type="button" class="btn btn-primary" wire:click="simpan" wire:loading.attr="disabled" wire:target="simpan">{{ $editVoucher ? 'Simpan perubahan' : 'Buat voucher' }}</button>
        </x-slot:footer>
    </x-dialog>

    <x-dialog wire:model="dialogHapus" :judul="'Hapus voucher '.($hapusVoucher->kode ?? '').'?'" lebar="max-w-[480px]">
        <p class="m-0 text-sm leading-6 text-gray-700">Kode ini tidak bisa dipakai lagi di Checkout. Voucher ini belum pernah dipakai pesanan. Untuk menghentikan sementara, matikan saja statusnya.</p>
        <x-slot:footer>
            <button type="button" class="btn btn-secondary" x-on:click="$wire.dialogHapus = false">Batal</button>
            <button type="button" class="btn btn-danger-solid" wire:click="hapus" wire:loading.attr="disabled" wire:target="hapus">Hapus voucher</button>
        </x-slot:footer>
    </x-dialog>

    <x-admin.dialog-konfirmasi-sandi :label="$tertundaLabel" :pesan="$tertundaPesan" />
</div>
