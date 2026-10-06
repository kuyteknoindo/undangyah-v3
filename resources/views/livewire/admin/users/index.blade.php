@php
    $angka = fn (int|float $n): string => number_format($n, 0, ',', '.');
    $tgl = fn ($t, string $f = 'j M Y') => $t ? $t->locale('id')->translatedFormat($f) : '–';
    $statusUser = fn ($u) => $u->ditangguhkan_at ? ['Ditangguhkan', 'merah'] : (! $u->hasVerifiedEmail() ? ['Belum verifikasi', 'kuning'] : ['Aktif', 'hijau']);
    $judulAksi = ['peran' => 'Ubah peran', 'paket' => 'Atur paket secara manual', 'tangguh' => 'Tangguhkan akun?', 'hapus' => 'Hapus akun?', 'masuk' => 'Masuk sebagai pengguna?'][$jenisAksi] ?? 'Konfirmasi';
    $labelTombolAksi = ['peran' => 'Simpan peran', 'paket' => 'Simpan paket', 'tangguh' => 'Ya, tangguhkan', 'hapus' => 'Ya, hapus akun', 'masuk' => 'Masuk sebagai'][$jenisAksi] ?? 'Lanjut';
    $aksiBahaya = in_array($jenisAksi, ['tangguh', 'hapus'], true);
    $labelLog = [
        'ubah_peran' => 'Peran diubah', 'atur_paket' => 'Paket diatur manual', 'verifikasi_manual' => 'Email diverifikasi manual', 'kirim_ulang_verifikasi' => 'Verifikasi dikirim ulang',
        'tangguhkan' => 'Ditangguhkan', 'aktifkan' => 'Diaktifkan kembali', 'hapus_akun' => 'Akun dihapus', 'impersonasi_mulai' => 'Admin masuk sebagai pengguna', 'impersonasi_selesai' => 'Sesi masuk sebagai berakhir',
    ];
@endphp
<div class="mx-auto flex max-w-6xl flex-col gap-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="sub m-0 max-w-2xl">Akun pengguna terdaftar. Perubahan peran, paket manual, penangguhan, penghapusan, dan "masuk sebagai" tercatat atas namamu.</p>
        <button type="button" wire:click="ekspor" wire:loading.attr="disabled" wire:target="ekspor" class="btn btn-secondary"><x-ikon nama="rsvp-unduh" :ukuran="16" /> Ekspor CSV</button>
    </div>

    {{-- KPI --}}
    <section aria-label="Ringkasan pengguna" class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="kartu kpi"><span class="kpi-l">Total pengguna</span><span class="kpi-v">{{ $angka($kpi['total']) }}</span><span class="hint">{{ $angka($kpi['belum']) }} belum verifikasi email</span></div>
        <div class="kartu kpi"><span class="kpi-l">Paket berbayar aktif</span><span class="kpi-v">{{ $angka($kpi['berbayar']) }}</span><span class="hint">{{ $kpi['total'] ? round($kpi['berbayar'] / $kpi['total'] * 100) : 0 }}% dari semua pengguna</span></div>
        <div class="kartu kpi"><span class="kpi-l">Aktif 30 hari</span><span class="kpi-v">{{ $angka($kpi['aktif30']) }}</span><span class="hint">Login dalam 30 hari terakhir</span></div>
        <div class="kartu kpi"><span class="kpi-l">Ditangguhkan</span><span class="kpi-v">{{ $angka($kpi['ditangguhkan']) }}</span><span class="hint">Tidak bisa masuk</span></div>
    </section>

    <section aria-labelledby="us-h" class="kartu overflow-hidden">
        <div class="flex flex-col gap-3 p-4 sm:p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 id="us-h" class="h2">Daftar pengguna</h2>
                    <div class="sub">{{ $angka($total) }} pengguna{{ $adaFilter ? ' cocok dengan filter' : '' }}</div>
                </div>
                <div class="w-full sm:w-80">
                    <label for="us-q" class="sr-only">Cari pengguna</label>
                    <input id="us-q" type="search" wire:model.live.debounce.400ms="q" maxlength="100" placeholder="Cari nama, email, atau nomor HP" class="inp" autocomplete="off">
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2.5">
                <div class="seg flex-wrap" role="group" aria-label="Filter paket">
                    @foreach ($segmenPaket as $sg)
                        <button type="button" wire:click="$set('paket', '{{ $sg['kode'] }}')" aria-pressed="{{ $paket === $sg['kode'] ? 'true' : 'false' }}">{{ $sg['label'] }} <span class="tabular-nums text-muted">{{ $angka($sg['n']) }}</span></button>
                    @endforeach
                </div>
                <label for="us-st" class="sr-only">Status</label>
                <select id="us-st" wire:model.live="status" class="inp w-auto">
                    <option value="">Semua status</option>
                    <option value="aktif">Aktif</option>
                    <option value="ditangguhkan">Ditangguhkan</option>
                    <option value="belum">Belum verifikasi</option>
                </select>
                <label for="us-r" class="sr-only">Peran</label>
                <select id="us-r" wire:model.live="peran" class="inp w-auto">
                    <option value="">Semua peran</option>
                    <option value="user">Pengguna</option>
                    <option value="admin">Admin</option>
                </select>
                <label for="us-u" class="sr-only">Urutkan</label>
                <select id="us-u" wire:model.live="urut" class="inp w-auto">
                    @foreach ($urutan as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach
                </select>
                @if ($adaFilter)
                    <button type="button" wire:click="aturUlangFilter" class="btn btn-ghost btn-sm">Reset filter</button>
                @endif
            </div>
        </div>

        @if ($total === 0)
            <x-kosong judul="Tidak ada pengguna yang cocok" teks="Coba ubah kata kunci atau filter paket, status, dan peran." ikon="admin-users">
                @if ($adaFilter)<button type="button" wire:click="aturUlangFilter" class="btn btn-secondary btn-sm">Reset filter</button>@endif
            </x-kosong>
        @else
            <div class="overflow-x-auto">
                <table class="tabel min-w-[960px]">
                    <caption class="sr-only">Daftar pengguna</caption>
                    <thead><tr><th>Pengguna</th><th>Paket</th><th>Undangan</th><th>Bergabung</th><th>Login terakhir</th><th>Status</th><th class="text-right">Aksi</th></tr></thead>
                    <tbody>
                        @foreach ($baris as $u)
                            @php
                                $diri = $u->is($admin);
                                $st = $statusUser($u);
                                $berbayar = $u->paketAktif() !== 'gratis';
                            @endphp
                            <tr wire:key="user-{{ $u->id }}">
                                <td>
                                    <div class="flex items-center gap-2.5">
                                        <span class="flex size-9 shrink-0 items-center justify-center rounded-full text-xs font-semibold text-white" style="background: {{ $u->warnaAvatar() }}">{{ $u->inisial() }}</span>
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-1.5">
                                                <button type="button" wire:click="bukaDetail({{ $u->id }})" class="max-w-[220px] truncate text-left font-semibold text-ink hover:text-brand">{{ $u->name }}</button>
                                                @if ($diri)<span class="tag">Kamu</span>@endif
                                                @if ($u->isAdmin())<span class="tag" style="background:#EEF3FE;color:#1D4ED8">Admin</span>@endif
                                            </div>
                                            <div class="max-w-[260px] truncate text-xs text-muted">{{ $u->email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <x-pill :warna="$berbayar ? 'biru' : 'abu'">{{ $paketBaris[$u->id] }}</x-pill>
                                    <div class="mt-0.5 text-xs {{ $u->paket !== 'gratis' && $u->paket_sampai && $u->paket_sampai->isPast() ? 'text-danger' : 'text-muted' }}">
                                        @if ($u->paket !== 'gratis' && $u->paket_sampai)
                                            {{ $u->paket_sampai->isPast() ? 'Berakhir ' : 's.d. ' }}{{ $tgl($u->paket_sampai) }}
                                        @elseif ($berbayar)
                                            Tanpa batas waktu
                                        @else
                                            Paket dasar
                                        @endif
                                    </div>
                                </td>
                                <td class="whitespace-nowrap tabular-nums">{{ $angka((int) $u->undangan_count) }}<span class="text-muted"> / {{ $kuotaBaris[$u->id] }}</span></td>
                                <td class="whitespace-nowrap">{{ $tgl($u->created_at) }}</td>
                                <td class="whitespace-nowrap" @if ($u->login_terakhir_at) title="{{ $u->login_terakhir_at->format('d/m/Y H:i') }}" @endif>{{ $u->login_terakhir_at ? $u->login_terakhir_at->locale('id')->diffForHumans() : 'Belum pernah' }}</td>
                                <td><x-pill :warna="$st[1]">{{ $st[0] }}</x-pill></td>
                                <td>
                                    <div class="flex items-center justify-end gap-1.5">
                                        @if (! $diri && ! $u->isAdmin() && ! $u->ditangguhkan_at && $u->hasVerifiedEmail())
                                            <button type="button" wire:click="mintaAksi('masuk', {{ $u->id }})" class="btn btn-secondary btn-sm" aria-label="Masuk sebagai {{ $u->name }}"><x-ikon nama="au-masuk" :ukuran="14" /> Masuk sebagai</button>
                                        @endif
                                        @if (! $diri && ! $u->hasVerifiedEmail())
                                            <button type="button" wire:click="verifikasi({{ $u->id }})" class="btn btn-secondary btn-sm" aria-label="Verifikasi email {{ $u->name }}">Verifikasi</button>
                                        @endif
                                        <button type="button" wire:click="bukaDetail({{ $u->id }})" class="btn-icon" aria-label="Detail {{ $u->name }}" title="Detail"><x-ikon nama="bt-mata" :ukuran="15" /></button>
                                        @if ($diri)
                                            <span class="inline-flex size-[34px] items-center justify-center text-subtle" role="img" aria-label="Akunmu sendiri tidak bisa ditangguhkan" title="Akunmu sendiri tidak bisa ditangguhkan"><x-ikon nama="au-kunci" :ukuran="15" /></span>
                                        @elseif ($u->ditangguhkan_at)
                                            <button type="button" wire:click="aktifkan({{ $u->id }})" class="btn-icon" aria-label="Aktifkan {{ $u->name }}" title="Aktifkan"><x-ikon nama="au-aktif" :ukuran="15" /></button>
                                        @else
                                            <button type="button" wire:click="mintaAksi('tangguh', {{ $u->id }})" class="btn-icon" aria-label="Tangguhkan {{ $u->name }}" title="Tangguhkan"><x-ikon nama="au-tangguh" :ukuran="15" /></button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-[#eef0f3] px-4 py-3 sm:px-5">
                <div class="sub">Menampilkan {{ $angka($dari) }}–{{ $angka($sampai) }} dari {{ $angka($total) }}</div>
                <div class="flex items-center gap-2">
                    <button type="button" wire:click="sebelumnya" @disabled($halaman <= 1) class="btn btn-secondary btn-sm" aria-label="Halaman sebelumnya">Sebelumnya</button>
                    <span class="text-[13px] font-medium tabular-nums text-ink">{{ $halaman }} / {{ $maks }}</span>
                    <button type="button" wire:click="berikutnya" @disabled($halaman >= $maks) class="btn btn-secondary btn-sm" aria-label="Halaman berikutnya">Berikutnya</button>
                </div>
            </div>
        @endif
    </section>

    {{-- Detail pengguna --}}
    <x-dialog wire:model="tampilDetail" :judul="$detailUser?->name ?? 'Detail pengguna'" :sub="$detailUser?->email" lebar="max-w-[720px]">
        @if ($detailUser)
            @php
                $st = $statusUser($detailUser);
                $diri = $detailUser->is($admin);
                $berbayar = $detailUser->paketAktif() !== 'gratis';
            @endphp
            <div class="flex flex-wrap items-center gap-2">
                <x-pill :warna="$st[1]">{{ $st[0] }}</x-pill>
                <x-pill :warna="$detailUser->isAdmin() ? 'biru' : 'abu'">{{ $detailUser->isAdmin() ? 'Admin' : 'Pengguna' }}</x-pill>
                @if ($diri)<span class="tag">Kamu</span>@endif
            </div>
            @if ($detailUser->ditangguhkan_at)
                <div role="note" class="rounded-[10px] bg-[#FEF3F2] px-3 py-2.5 text-[13px] leading-[18px] text-[#B42318]">Ditangguhkan sejak {{ $tgl($detailUser->ditangguhkan_at, 'j M Y H:i') }}{{ $detailUser->ditangguhkan_alasan ? ': '.$detailUser->ditangguhkan_alasan : '.' }}</div>
            @endif

            <dl class="grid gap-x-6 gap-y-3 text-[13px] leading-[18px] sm:grid-cols-2">
                <div><dt class="text-muted">Nomor HP</dt><dd class="m-0 font-medium text-ink">{{ $detailUser->teleponTampil() ?? '–' }}</dd></div>
                <div><dt class="text-muted">Kota</dt><dd class="m-0 font-medium text-ink">{{ $detailUser->kota ?: '–' }}</dd></div>
                <div><dt class="text-muted">Bergabung</dt><dd class="m-0 font-medium text-ink">{{ $tgl($detailUser->created_at, 'j M Y H:i') }}</dd></div>
                <div><dt class="text-muted">Login terakhir</dt><dd class="m-0 font-medium text-ink">{{ $detailUser->login_terakhir_at ? $tgl($detailUser->login_terakhir_at, 'j M Y H:i') : 'Belum pernah' }}</dd></div>
                <div><dt class="text-muted">Email terverifikasi</dt><dd class="m-0 font-medium text-ink">{{ $detailUser->email_verified_at ? $tgl($detailUser->email_verified_at, 'j M Y H:i') : 'Belum' }}</dd></div>
                <div><dt class="text-muted">Paket</dt>
                    <dd class="m-0 font-medium text-ink">
                        {{ collect($daftarPaket)->firstWhere('kode', $detailUser->paketAktif())?->nama ?? ucfirst($detailUser->paketAktif()) }}
                        @if ($berbayar)
                            <span class="font-normal text-muted">· {{ $detailUser->paket_sampai ? 'sampai '.$tgl($detailUser->paket_sampai) : 'tanpa batas waktu' }}</span>
                        @elseif ($detailUser->paket !== 'gratis' && $detailUser->paket_sampai)
                            <span class="font-normal text-danger">· paket {{ $detailUser->paket }} berakhir {{ $tgl($detailUser->paket_sampai) }}</span>
                        @endif
                    </dd>
                </div>
                <div><dt class="text-muted">Undangan</dt><dd class="m-0 font-medium text-ink">{{ $angka($detail['undangan']) }} <span class="font-normal text-muted">({{ $angka($detail['undangan_terbit']) }} terbit)</span></dd></div>
                <div><dt class="text-muted">Tamu di semua undangan</dt><dd class="m-0 font-medium text-ink">{{ $angka($detail['tamu']) }}</dd></div>
            </dl>
            @if ($detail['paket_manual'])
                <div class="hint">Paket terakhir diatur manual oleh {{ $detail['paket_manual']->admin_email }} pada {{ $tgl($detail['paket_manual']->created_at, 'j M Y H:i') }}.</div>
            @endif

            <div>
                <h3 class="m-0 mb-2 text-[13px] leading-[18px] font-semibold text-ink">Pesanan terakhir</h3>
                @if ($detail['pesanan']->isEmpty())
                    <div class="sub">Belum ada pesanan.</div>
                @else
                    <div class="overflow-x-auto rounded-[10px] border border-line">
                        <table class="tabel">
                            <thead><tr><th>Nomor</th><th>Total</th><th>Status</th><th>Tanggal</th></tr></thead>
                            <tbody>
                                @foreach ($detail['pesanan'] as $p)
                                    @php
                                        $info = $p->infoStatus();
                                    @endphp
                                    <tr><td class="whitespace-nowrap font-medium text-ink">{{ $p->nomor }}</td><td class="whitespace-nowrap tabular-nums">Rp {{ $angka($p->total) }}</td><td><x-pill :warna="$info[1]">{{ $info[0] }}</x-pill></td><td class="whitespace-nowrap">{{ $tgl($p->created_at) }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            @if ($detail['aksi']->isNotEmpty())
                <div>
                    <h3 class="m-0 mb-2 text-[13px] leading-[18px] font-semibold text-ink">Riwayat aksi admin</h3>
                    <ul class="m-0 flex list-none flex-col gap-1 p-0 text-[13px] leading-[18px] text-muted">
                        @foreach ($detail['aksi'] as $a)
                            <li>{{ $tgl($a->created_at, 'j M Y H:i') }} · <span class="font-medium text-ink">{{ $labelLog[$a->aksi] ?? $a->aksi }}</span> · {{ $a->admin_email }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <x-slot:footer>
                @if ($diri)
                    <span class="sub mr-auto">Ini akunmu. Peran, penangguhan, dan penghapusan akun sendiri dikunci.</span>
                @else
                    @unless ($detailUser->hasVerifiedEmail())
                        <button type="button" wire:click="verifikasi({{ $detailUser->id }})" class="btn btn-secondary btn-sm">Tandai terverifikasi</button>
                        <button type="button" wire:click="kirimUlangVerifikasi({{ $detailUser->id }})" class="btn btn-secondary btn-sm">Kirim ulang verifikasi</button>
                    @endunless
                    <button type="button" wire:click="mintaAksi('peran', {{ $detailUser->id }})" class="btn btn-secondary btn-sm">Ubah peran</button>
                    <button type="button" wire:click="mintaAksi('paket', {{ $detailUser->id }})" class="btn btn-secondary btn-sm">Atur paket</button>
                    @if ($detailUser->ditangguhkan_at)
                        <button type="button" wire:click="aktifkan({{ $detailUser->id }})" class="btn btn-secondary btn-sm">Aktifkan akun</button>
                    @else
                        <button type="button" wire:click="mintaAksi('tangguh', {{ $detailUser->id }})" class="btn btn-danger btn-sm">Tangguhkan</button>
                    @endif
                    @unless ($detailUser->isAdmin())
                        <button type="button" wire:click="mintaAksi('hapus', {{ $detailUser->id }})" class="btn btn-danger btn-sm">Hapus akun</button>
                    @endunless
                @endif
            </x-slot:footer>
        @endif
    </x-dialog>

    {{-- Dialog aksi: peran, paket, tangguh, hapus, masuk sebagai --}}
    <x-dialog wire:model="tampilAksi" :judul="$judulAksi" :sub="$aksiUser ? $aksiUser->name.' · '.$aksiUser->email : null" lebar="max-w-[520px]">
        @if ($aksiUser)
            @if ($jenisAksi === 'peran')
                <div>
                    <label for="ua-peran" class="lbl">Peran baru</label>
                    <select id="ua-peran" wire:model="peranBaru" class="inp">
                        <option value="user">Pengguna</option>
                        <option value="admin">Admin</option>
                    </select>
                    @error('peranBaru')<div role="alert" class="galat mt-1">{{ $message }}</div>@enderror
                </div>
                <p class="sub m-0">Admin bisa mengubah akun pengguna lain dan melihat seluruh Panel Admin. Berikan hanya kepada orang yang dipercaya. Selalu harus ada setidaknya satu admin aktif.</p>
            @elseif ($jenisAksi === 'paket')
                <div class="grid gap-3.5 sm:grid-cols-2">
                    <div>
                        <label for="ua-paket" class="lbl">Paket</label>
                        <select id="ua-paket" wire:model.live="paketBaru" class="inp">
                            <option value="gratis">Gratis</option>
                            @foreach ($daftarPaket as $pk)
                                @if ($pk->kode !== 'gratis')<option value="{{ $pk->kode }}">{{ $pk->nama }}{{ $pk->status !== 'aktif' ? ' ('.$pk->status.')' : '' }}</option>@endif
                            @endforeach
                        </select>
                        @error('paketBaru')<div role="alert" class="galat mt-1">{{ $message }}</div>@enderror
                    </div>
                    @if ($paketBaru !== 'gratis')
                        <div>
                            <label for="ua-sampai" class="lbl">Berlaku sampai</label>
                            <input id="ua-sampai" type="date" wire:model="paketSampai" min="{{ now()->addDay()->format('Y-m-d') }}" max="{{ now()->addYears(10)->format('Y-m-d') }}" @disabled($tanpaBatas) class="inp" @error('paketSampai') aria-invalid="true" @enderror>
                            @error('paketSampai')<div role="alert" class="galat mt-1">{{ $message }}</div>@enderror
                        </div>
                    @endif
                </div>
                @if ($paketBaru !== 'gratis')
                    <label class="flex items-center gap-2 text-[13px] text-ink"><input type="checkbox" wire:model.live="tanpaBatas" class="size-4 rounded border-line-strong"> Tanpa batas waktu</label>
                @endif
                <div>
                    <label for="ua-catatan" class="lbl">Catatan <span class="font-normal text-muted">(opsional, tersimpan di riwayat aksi)</span></label>
                    <input id="ua-catatan" type="text" wire:model="catatanPaket" maxlength="200" class="inp" placeholder="Contoh: kompensasi gangguan, kerja sama">
                    @error('catatanPaket')<div role="alert" class="galat mt-1">{{ $message }}</div>@enderror
                </div>
                <p class="sub m-0">Tidak membuat pesanan atau tagihan. Pengguna menerima notifikasi tentang paket barunya.</p>
            @elseif ($jenisAksi === 'tangguh')
                <p class="sub m-0">Pengguna langsung keluar dari semua sesi dan tidak bisa masuk. Undangan miliknya berhenti ditayangkan sampai akun diaktifkan kembali.</p>
                <div>
                    <label for="ua-alasan" class="lbl">Alasan <span class="font-normal text-muted">(opsional, hanya terlihat admin)</span></label>
                    <input id="ua-alasan" type="text" wire:model="alasanTangguh" maxlength="200" class="inp">
                    @error('alasanTangguh')<div role="alert" class="galat mt-1">{{ $message }}</div>@enderror
                </div>
            @elseif ($jenisAksi === 'hapus')
                <div role="note" class="rounded-[10px] bg-[#FEF3F2] px-3 py-2.5 text-[13px] leading-[18px] text-[#B42318]">Akun beserta undangan, tamu, pesanan, dan notifikasinya dihapus permanen. Tindakan ini tidak bisa dibatalkan.</div>
                <div>
                    <label for="ua-email" class="lbl">Ketik <span class="font-semibold">{{ $aksiUser->email }}</span> untuk konfirmasi</label>
                    <input id="ua-email" type="text" wire:model="emailKonfirmasi" maxlength="190" autocomplete="off" class="inp">
                </div>
            @elseif ($jenisAksi === 'masuk')
                <p class="sub m-0">Kamu akan melihat aplikasi seperti {{ $aksiUser->name }}. Sesi dibatasi {{ $impersonasiMaks }} menit, tombol Kembali ke Admin selalu ada di atas layar, dan tindakan ini dicatat di riwayat aksi admin.</p>
                <div role="note" class="rounded-[10px] bg-[#FFFAEB] px-3 py-2.5 text-[13px] leading-[18px] text-[#B54708]">Selama sesi, ubah sandi/email/profil dan pembelian paket dinonaktifkan. Data lain tetap nyata: hati-hati saat mengubah atau menghapus.</div>
            @endif
            @error('aksi')<div role="alert" class="galat">{{ $message }}</div>@enderror
        @endif

        <x-slot:footer>
            <button type="button" wire:click="tutupAksi" class="btn btn-secondary">Batal</button>
            <button type="button" wire:click="jalankanAksi" wire:loading.attr="disabled" wire:target="jalankanAksi" @class(['btn', 'btn-danger-solid' => $aksiBahaya, 'btn-primary' => ! $aksiBahaya])>
                {{ $labelTombolAksi }}
            </button>
        </x-slot:footer>
    </x-dialog>
</div>
