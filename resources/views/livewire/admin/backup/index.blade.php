@php
    $zona = config('app.timezone');
    $waktu = fn ($t) => $t?->copy()->timezone($zona)->locale('id')->translatedFormat('j M Y, H.i');
    $rel = fn ($t) => $t?->copy()->locale('id')->diffForHumans();
    $ukuran = fn ($n) => \App\Services\PembuatBackup::ukuran((int) $n);
    $ikonTipe = ['database' => 'backup-database', 'file' => 'backup-file', 'penuh' => 'admin-backup'];
    $perintahRestore = "# 1. Salin berkas backup ke server tujuan, lalu cek checksum (bandingkan dengan kolom Checksum)\nsha256sum <BERKAS_BACKUP>.dump\n\n# 2. Buat database BARU yang kosong (jangan menimpa database yang sedang dipakai)\ncreatedb --host=<HOST> --port=<PORT> --username=<USER_ADMIN_DB> <NAMA_DB_BARU>\n\n# 3. Pulihkan ke database baru itu (sandi lewat PGPASSWORD atau prompt, jangan di baris perintah)\npg_restore --no-owner --no-acl --host=<HOST> --port=<PORT> --username=<USER_ADMIN_DB> --dbname=<NAMA_DB_BARU> <BERKAS_BACKUP>.dump\n\n# 4. Periksa isinya, lalu arahkan DB_DATABASE ke database baru saat jendela pemeliharaan";
@endphp
<div class="mx-auto flex max-w-6xl flex-col gap-5" @if ($adaBerjalan) wire:poll.3s @endif>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <p class="sub m-0 max-w-2xl">Salinan database PostgreSQL dan file unggahan, disimpan di folder privat server. Unduh salinannya ke tempat terpisah supaya aman bila server bermasalah.</p>
        <button type="button" class="btn btn-primary" wire:click="buatCepat" wire:loading.attr="disabled" wire:target="buat,buatCepat"><x-ikon nama="backup-database" :ukuran="16" /> Backup sekarang</button>
    </div>

    <div role="note" class="flex items-start gap-3 rounded-xl border border-[#b2ccff] bg-[#eef3fe] p-3.5 text-[13px] leading-5 text-[#1d4ed8]">
        <span class="mt-0.5 shrink-0"><x-ikon nama="backup-cek" :ukuran="16" /></span>
        <div><b>Restore ke database hidup sengaja tidak otomatis.</b> Menimpa data produksi perlu keputusan dan tindakan pemilik, jadi halaman ini hanya menyediakan <b>Uji pulihkan</b> (ke database sementara yang langsung dihapus) dan petunjuk restore manual.</div>
    </div>

    <section aria-label="Ringkasan" class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="kartu kpi"><div class="kpi-l">Backup tersimpan</div><div class="kpi-v">{{ $jumlahSukses }}</div><div class="hint">{{ $ukuran($ukuranSukses) }} total{{ $total > $jumlahSukses ? ' · '.($total - $jumlahSukses).' gagal/berjalan' : '' }}</div></div>
        <div class="kartu kpi"><div class="kpi-l">Backup terakhir</div><div class="kpi-v !text-xl">{{ $terakhir ? $rel($terakhir->created_at) : 'Belum ada' }}</div><div class="hint">{{ $terakhir ? $waktu($terakhir->created_at).' · '.(\App\Models\Backup::STATUS[$terakhir->status][1] ?? '') : 'Buat backup pertama' }}</div></div>
        <div class="kartu kpi"><div class="kpi-l">Database saat ini</div><div class="kpi-v">{{ $ukuranDb > 0 ? $ukuran($ukuranDb) : '–' }}</div><div class="hint">{{ $mesinDb }}</div></div>
        <div class="kartu kpi"><div class="kpi-l">Ruang disk bebas</div><div class="kpi-v">{{ $ukuran($disk['bebas']) }}</div><div class="hint">dari {{ $ukuran($disk['total']) }} · backup ditolak bila kurang dari 2x perkiraan</div></div>
    </section>

    <div class="grid gap-5 lg:grid-cols-3">
        <section aria-labelledby="au-h" class="kartu flex flex-col gap-4 p-5">
            <div class="flex items-start gap-3">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand"><x-ikon nama="backup-jam" :ukuran="20" /></span>
                <div><h2 id="au-h" class="h2">Backup otomatis</h2><div class="sub">Jadwal salinan berkala dan retensi.</div></div>
            </div>
            <div class="flex items-center justify-between gap-3">
                <div><div class="text-sm font-medium text-ink">Aktifkan jadwal</div><div class="hint">Nonaktif secara bawaan.</div></div>
                <button type="button" role="switch" class="sakelar" aria-checked="{{ $otomatis ? 'true' : 'false' }}" aria-label="Backup otomatis" wire:click="$toggle('otomatis')"></button>
            </div>
            <div>
                <label for="bk-freq" class="lbl">Frekuensi</label>
                <select id="bk-freq" class="inp" wire:model.live="frekuensi" @disabled(! $otomatis)>
                    @foreach (\App\Services\PengaturanBackup::FREKUENSI as $k => $t)<option value="{{ $k }}">{{ $t }}</option>@endforeach
                </select>
                @error('frekuensi')<div role="alert" class="galat mt-1">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="bk-tipe" class="lbl">Isi backup otomatis</label>
                <select id="bk-tipe" class="inp" wire:model.live="tipeOtomatis" @disabled(! $otomatis)>
                    @foreach (\App\Services\PengaturanBackup::TIPE_OTOMATIS as $k => $t)<option value="{{ $k }}">{{ $t }}</option>@endforeach
                </select>
                @error('tipeOtomatis')<div role="alert" class="galat mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="bk-ret" class="lbl">Simpan selama</label>
                    <select id="bk-ret" class="inp" wire:model.live="retensiHari">
                        @foreach (\App\Services\PengaturanBackup::RETENSI_HARI as $h)<option value="{{ $h }}">{{ $h }} hari</option>@endforeach
                    </select>
                    @error('retensiHari')<div role="alert" class="galat mt-1">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label for="bk-min" class="lbl">Selalu sisakan</label>
                    <select id="bk-min" class="inp" wire:model.live="simpanMinimal">
                        @foreach (\App\Services\PengaturanBackup::SIMPAN_MINIMAL as $n)<option value="{{ $n }}">{{ $n }} terakhir</option>@endforeach
                    </select>
                    @error('simpanMinimal')<div role="alert" class="galat mt-1">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="hint">Backup sukses lebih tua dari batas hari dihapus, tetapi sejumlah yang terbaru selalu dipertahankan.
                @if ($jadwal) Jadwal berikutnya: <b>{{ $jadwal->locale('id')->translatedFormat('l, j M Y, H.i') }}</b>. @else Jadwal otomatis nonaktif; backup dibuat manual. @endif
            </div>
            <div class="flex flex-wrap items-center justify-between gap-2 border-t border-[#eef0f3] pt-3">
                <x-pill :warna="$kotor ? 'kuning' : ($sudahDisimpan ? 'hijau' : 'abu')">{{ $kotor ? 'Belum disimpan' : ($sudahDisimpan ? 'Tersimpan' : 'Bawaan') }}</x-pill>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="tanyaRetensi">Bersihkan sekarang</button>
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="batalkanPengaturan" @disabled(! $kotor)>Batal</button>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="simpanPengaturan" wire:loading.attr="disabled" wire:target="simpanPengaturan">Simpan</button>
                </div>
            </div>
        </section>

        <section aria-labelledby="mk-h" class="kartu flex flex-col gap-4 p-5">
            <div class="flex items-start gap-3">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand"><x-ikon nama="backup-database" :ukuran="20" /></span>
                <div><h2 id="mk-h" class="h2">Buat backup sekarang</h2><div class="sub">Maksimal {{ config('backup.maks_per_jam') }} per jam per admin.</div></div>
            </div>
            <div>
                <div id="bk-tipe-l" class="lbl">Isi backup</div>
                <div class="seg flex-wrap" role="group" aria-labelledby="bk-tipe-l">
                    @foreach (\App\Models\Backup::TIPE as $k => $t)
                        <button type="button" wire:click="pilihTipe('{{ $k }}')" aria-pressed="{{ $tipe === $k ? 'true' : 'false' }}">{{ explode(' (', $t)[0] }}</button>
                    @endforeach
                </div>
                <div class="hint mt-1.5">
                    @if ($tipe === 'database') Dump PostgreSQL format custom (<code>pg_dump -Fc</code>), terkompresi dan bisa dipulihkan per tabel.
                    @elseif ($tipe === 'file') Arsip .tar.gz berisi folder unggahan.
                    @else Satu arsip berisi dump database dan arsip file unggahan. @endif
                </div>
            </div>
            @if ($tipe !== 'database')
                <div class="rounded-[10px] bg-[#f8f9fb] px-3 py-2.5 text-[13px] leading-5 text-gray-700">
                    <div class="font-medium text-ink">File unggahan yang ikut</div>
                    @forelse ($sumberFile['sumber'] as $s)<div class="font-mono text-xs">{{ $s['disk'] }}{{ $s['sub'] !== '' ? '/'.$s['sub'] : '' }}</div>@empty<div class="text-muted">Belum ada folder unggahan yang bisa dicadangkan.</div>@endforelse
                    @if ($sumberFile['dilewati'])<div class="hint mt-1">Disk {{ implode(', ', $sumberFile['dilewati']) }} belum dikonfigurasi, dilewati.</div>@endif
                </div>
            @endif
            <div>
                <label for="bk-nama" class="lbl">Nama backup <span class="font-normal text-muted">(opsional)</span></label>
                <input id="bk-nama" type="text" class="inp" maxlength="60" placeholder="mis. sebelum-impor-tema" wire:model="label" autocomplete="off" @error('label') aria-invalid="true" @enderror>
                <div class="hint mt-1">Hanya label tampilan. Nama berkas dibuat sistem. Huruf, angka, spasi, strip, titik.</div>
                @error('label')<div role="alert" class="galat mt-1">{{ $message }}</div>@enderror
                @error('tipe')<div role="alert" class="galat mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="mt-auto flex flex-wrap items-center justify-between gap-2 border-t border-[#eef0f3] pt-3">
                <span class="hint">Sisa jatah jam ini: {{ $sisaKuota }}</span>
                <button type="button" class="btn btn-primary" wire:click="buat" wire:loading.attr="disabled" wire:target="buat,buatCepat" @disabled($adaBerjalan)>
                    <span wire:loading.remove wire:target="buat,buatCepat">Buat backup</span><span wire:loading wire:target="buat,buatCepat">Membuat backup...</span>
                </button>
            </div>
        </section>

        <section aria-labelledby="rs-h" class="kartu flex flex-col gap-4 p-5">
            <div class="flex items-start gap-3">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-[#ecfdf3] text-[#067647]"><x-ikon nama="backup-uji" :ukuran="20" /></span>
                <div><h2 id="rs-h" class="h2">Uji pulih dan restore</h2><div class="sub">Bukti bahwa backup benar-benar bisa dipulihkan.</div></div>
            </div>
            @if ($kemampuan['bisa'])
                <div class="rounded-[10px] bg-[#ecfdf3] px-3 py-2.5 text-[13px] leading-5 text-[#067647]"><b>Uji pulihkan tersedia.</b> Pilih backup database di tabel lalu klik ikon labu. Dump dipulihkan ke database sementara bernama <code>undangyah_uji_pulih_xxxxxxxx</code>, tabel dan barisnya dihitung, lalu database itu dihapus. Database hidup tidak disentuh.</div>
            @else
                <div role="note" class="rounded-[10px] bg-[#fffaeb] px-3 py-2.5 text-[13px] leading-5 text-[#b54708]"><b>Uji pulihkan dinonaktifkan.</b> {{ $kemampuan['alasan'] }}</div>
            @endif
            <div>
                <div class="mb-1.5 flex items-center justify-between gap-2">
                    <div class="lbl !mb-0">Restore manual (oleh pemilik)</div>
                    <button type="button" class="btn btn-secondary btn-sm" x-data x-on:click="navigator.clipboard?.writeText(@js($perintahRestore)); $dispatch('toast', { pesan: 'Perintah disalin.', tipe: 'sukses' })"><x-ikon nama="backup-salin" :ukuran="13" /> Salin</button>
                </div>
                <pre class="m-0 max-h-72 overflow-auto rounded-[10px] bg-[#101828] p-3 font-mono text-[11.5px] leading-[18px] whitespace-pre-wrap text-[#e4e7ec]" tabindex="0" aria-label="Perintah restore manual">{{ $perintahRestore }}</pre>
                <div class="hint mt-1.5">Ganti nilai di dalam &lt; &gt;. Contoh ini tanpa sandi; jangan menulis sandi di riwayat shell. Untuk backup penuh, ekstrak dulu <code>database.dump</code> dari arsip .tar.</div>
            </div>
        </section>
    </div>

    <section class="kartu overflow-hidden" aria-labelledby="hs-h">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line p-4 sm:p-5">
            <div><h2 id="hs-h" class="h2">Riwayat backup</h2><div class="sub">{{ $total > 0 ? ($baris->count() < $total ? $baris->count().' terbaru dari '.$total.' backup' : $total.' backup') : 'Backup yang dibuat akan muncul di sini.' }}</div></div>
        </div>
        @if ($baris->isEmpty())
            <x-kosong judul="Belum ada backup" teks="Buat backup pertama sebelum mengubah data besar-besaran, misalnya sebelum menghapus banyak pengguna atau memperbarui aplikasi." ikon="admin-backup">
                <button type="button" class="btn btn-primary btn-sm" wire:click="buatCepat">Backup sekarang</button>
            </x-kosong>
        @else
            <div class="overflow-x-auto">
                <table class="tabel">
                    <caption class="sr-only">Riwayat backup</caption>
                    <thead><tr><th>Nama</th><th>Waktu</th><th>Ukuran</th><th>Checksum SHA-256</th><th>Status</th><th>Aksi</th></tr></thead>
                    <tbody>
                        @foreach ($baris as $b)
                            @php [$warna, $labelStatus] = \App\Models\Backup::STATUS[$b->status] ?? ['abu', $b->status]; @endphp
                            <tr wire:key="b-{{ $b->id }}">
                                <td class="min-w-56">
                                    <div class="flex items-start gap-2.5">
                                        <span class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand"><x-ikon :nama="$ikonTipe[$b->tipe] ?? 'admin-backup'" :ukuran="16" /></span>
                                        <div class="min-w-0">
                                            <div class="font-semibold break-all text-ink">{{ $b->nama }}</div>
                                            <div class="text-xs text-muted">{{ \App\Models\Backup::TIPE[$b->tipe] ?? $b->tipe }} · {{ $b->sumber === 'jadwal' ? 'otomatis' : 'manual' }}{{ $b->pembuat ? ' · '.$b->pembuat->name : '' }}</div>
                                            @if ($b->catatan)<div class="text-xs text-muted">{{ $b->catatan }}</div>@endif
                                            @if ($b->pesan_galat)<div class="text-xs text-[#b42318]">{{ $b->pesan_galat }}</div>@endif
                                        </div>
                                    </div>
                                </td>
                                <td><div>{{ $waktu($b->created_at) }}</div><div class="text-xs text-muted">{{ $rel($b->created_at) }}</div></td>
                                <td>{{ $b->sukses() ? $ukuran($b->ukuran) : '–' }}</td>
                                <td>
                                    @if ($b->checksum)
                                        <button type="button" class="font-mono text-xs text-gray-700 hover:text-brand" x-data x-on:click="navigator.clipboard?.writeText(@js($b->checksum)); $dispatch('toast', { pesan: 'Checksum disalin.', tipe: 'sukses' })" title="{{ $b->checksum }} (klik untuk menyalin)">{{ substr($b->checksum, 0, 16) }}...</button>
                                    @else <span class="text-muted">–</span> @endif
                                </td>
                                <td>
                                    <x-pill :warna="$warna" title="{{ $b->pesan_galat }}">{{ $labelStatus }}</x-pill>
                                    @if ($b->diverifikasi_at)
                                        <div class="mt-1 text-xs {{ ($b->verifikasi['ok'] ?? false) ? 'text-[#067647]' : 'text-[#b42318]' }}" title="{{ $b->verifikasi['pesan'] ?? '' }}">{{ ($b->verifikasi['ok'] ?? false) ? 'Terverifikasi' : 'Verifikasi gagal' }} · {{ $rel($b->diverifikasi_at) }}</div>
                                    @endif
                                    @if ($b->uji_pulih_at)
                                        <div class="text-xs {{ ($b->uji_pulih['ok'] ?? false) ? 'text-[#067647]' : 'text-[#b42318]' }}" title="{{ $b->uji_pulih['pesan'] ?? '' }}">{{ ($b->uji_pulih['ok'] ?? false) ? 'Uji pulih lolos: '.($b->uji_pulih['tabel'] ?? 0).' tabel, '.number_format($b->uji_pulih['baris'] ?? 0, 0, ',', '.').' baris' : 'Uji pulih gagal' }}</div>
                                    @endif
                                </td>
                                <td>
                                    <div class="flex items-center gap-1">
                                        @if ($b->sukses())
                                            <button type="button" wire:click="unduh({{ $b->id }})" class="btn-icon" aria-label="Unduh {{ $b->nama }}" title="Unduh"><x-ikon nama="bagikan-unduh" :ukuran="14" /></button>
                                            <button type="button" class="btn-icon" wire:click="verifikasi({{ $b->id }})" wire:loading.attr="disabled" wire:target="verifikasi({{ $b->id }})" aria-label="Verifikasi {{ $b->nama }}" title="Verifikasi integritas"><x-ikon nama="backup-cek" :ukuran="14" /></button>
                                            <button type="button" class="btn-icon" wire:click="tanyaUji({{ $b->id }})" @disabled(! $kemampuan['bisa'] || ! $b->bisaDiujiPulih()) aria-label="Uji pulihkan {{ $b->nama }}" title="{{ ! $kemampuan['bisa'] ? 'Uji pulihkan dinonaktifkan (lihat penjelasan di atas)' : ($b->bisaDiujiPulih() ? 'Uji pulihkan ke database sementara' : 'Hanya untuk backup database PostgreSQL') }}"><x-ikon nama="backup-uji" :ukuran="14" /></button>
                                        @endif
                                        @if ($b->status !== 'berjalan')
                                            <button type="button" class="btn-icon text-[#b42318]" wire:click="tanyaHapus({{ $b->id }})" aria-label="Hapus {{ $b->nama }}" title="Hapus"><x-ikon nama="bagikan-hapus" :ukuran="14" /></button>
                                        @else
                                            <span class="hint">Berjalan...</span>
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

    <x-dialog wire:model="dialogHapus" :judul="'Hapus backup '.($hapusBackup->nama ?? '').'?'" lebar="max-w-[480px]">
        <p class="m-0 text-sm leading-6 text-gray-700">Berkas backup dihapus permanen dari server dan tidak bisa dikembalikan. Salinan yang sudah kamu unduh tidak terpengaruh.</p>
        <x-slot:footer>
            <button type="button" class="btn btn-secondary" x-on:click="$wire.dialogHapus = false">Batal</button>
            <button type="button" class="btn btn-danger-solid" wire:click="hapus" wire:loading.attr="disabled" wire:target="hapus">Hapus backup</button>
        </x-slot:footer>
    </x-dialog>

    <x-dialog wire:model="dialogRetensi" judul="Bersihkan backup lama?" lebar="max-w-[520px]">
        <p class="m-0 text-sm leading-6 text-gray-700">Sesuai pengaturan tersimpan: backup lebih tua dari {{ \App\Services\PengaturanBackup::ambil()['retensi_hari'] }} hari dihapus, kecuali {{ \App\Services\PengaturanBackup::ambil()['simpan_minimal'] }} backup sukses terbaru.</p>
        <p class="m-0 text-sm leading-6 font-medium text-ink">{{ $akanDihapus->count() > 0 ? $akanDihapus->count().' backup akan dihapus permanen.' : 'Tidak ada backup yang memenuhi syarat dihapus.' }}</p>
        <x-slot:footer>
            <button type="button" class="btn btn-secondary" x-on:click="$wire.dialogRetensi = false">Batal</button>
            <button type="button" class="btn btn-danger-solid" wire:click="terapkanRetensi" wire:loading.attr="disabled" wire:target="terapkanRetensi" @disabled($akanDihapus->isEmpty())>Hapus {{ $akanDihapus->count() }} backup</button>
        </x-slot:footer>
    </x-dialog>

    <x-dialog wire:model="dialogUji" :judul="'Uji pulihkan '.($ujiBackup->nama ?? '').'?'" sub="Hanya ke database sementara; database hidup tidak disentuh." lebar="max-w-[560px]">
        @if ($hasilUji)
            <div role="status" class="rounded-[10px] px-3 py-2.5 text-[13px] leading-5 font-medium {{ ($hasilUji['ok'] ?? false) ? 'bg-[#ecfdf3] text-[#067647]' : 'bg-[#fef3f2] text-[#b42318]' }}">{{ $hasilUji['pesan'] ?? '' }}</div>
            @if ($hasilUji['ok'] ?? false)
                <dl class="m-0 grid grid-cols-3 gap-3 text-center">
                    <div class="rounded-[10px] bg-[#f8f9fb] p-2.5"><dt class="hint">Tabel</dt><dd class="m-0 text-lg font-semibold text-ink">{{ $hasilUji['tabel'] }}</dd></div>
                    <div class="rounded-[10px] bg-[#f8f9fb] p-2.5"><dt class="hint">Baris</dt><dd class="m-0 text-lg font-semibold text-ink">{{ number_format($hasilUji['baris'], 0, ',', '.') }}</dd></div>
                    <div class="rounded-[10px] bg-[#f8f9fb] p-2.5"><dt class="hint">Durasi</dt><dd class="m-0 text-lg font-semibold text-ink">{{ $hasilUji['durasi'] }} dtk</dd></div>
                </dl>
            @endif
        @else
            <p class="m-0 text-sm leading-6 text-gray-700">Langkah: (1) buat database sementara baru, (2) pulihkan dump ke dalamnya, (3) hitung tabel dan baris, (4) hapus database sementara. Berjalan beberapa detik sampai menit sesuai ukuran data. Batas {{ 2 }} uji per jam.</p>
        @endif
        <x-slot:footer>
            @if ($hasilUji)
                <button type="button" class="btn btn-primary" wire:click="tutupUji">Tutup</button>
            @else
                <button type="button" class="btn btn-secondary" x-on:click="$wire.tutupUji()">Batal</button>
                <button type="button" class="btn btn-primary" wire:click="ujiPulih" wire:loading.attr="disabled" wire:target="ujiPulih"><span wire:loading.remove wire:target="ujiPulih">Jalankan uji pulih</span><span wire:loading wire:target="ujiPulih">Menguji...</span></button>
            @endif
        </x-slot:footer>
    </x-dialog>

    <x-admin.dialog-konfirmasi-sandi :label="$tertundaLabel" :pesan="$tertundaPesan" />
</div>
