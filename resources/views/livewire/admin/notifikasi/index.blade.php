@php
    $angka = fn (int|float $n): string => number_format($n, 0, ',', '.');
    [$jenisNama, $jenisBg, $jenisFg, $jenisCta, $jenisIkon] = $jenis[$tipe] ?? $jenis['sistem'];
    $adaTautan = trim($tautan) !== '' && \App\Models\Notifikasi::tautanAman($tautan) !== null;
    $labelPeran = ['semua' => 'Semua peran', 'user' => 'Pengguna', 'admin' => 'Admin'];
    $labelTarget = fn ($s) => ($namaPaket[$s->target_paket] ?? ucfirst($s->target_paket)).' · '.($labelPeran[$s->target_peran] ?? $s->target_peran);
@endphp
<div class="mx-auto flex max-w-6xl flex-col gap-5">
    <p class="sub m-0 max-w-2xl">Kirim pengumuman ke kotak Notifikasi pengguna di dalam aplikasi. Pengiriman dilakukan bertahap di latar belakang, hanya ke akun dengan email terverifikasi yang tidak ditangguhkan.</p>

    <section aria-label="Ringkasan siaran" class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div class="kartu kpi"><span class="kpi-l">Total siaran</span><span class="kpi-v">{{ $angka($ringkasan['total']) }}</span><span class="hint">Semua status</span></div>
        <div class="kartu kpi"><span class="kpi-l">Selesai terkirim</span><span class="kpi-v">{{ $angka($ringkasan['terkirim']) }}</span><span class="hint">Siaran yang sudah sampai ke semua sasaran</span></div>
        <div class="kartu kpi"><span class="kpi-l">Notifikasi terkirim</span><span class="kpi-v">{{ $angka($ringkasan['penerima']) }}</span><span class="hint">Jumlah dari semua siaran</span></div>
    </section>

    <div class="grid gap-5 grid-cols-[minmax(0,1fr)] lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
        <section aria-labelledby="bc-h" class="kartu flex flex-col gap-4 p-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 id="bc-h" class="h2">Siaran baru</h2>
                    <div class="sub">Pesan singkat untuk pengguna aplikasi.</div>
                </div>
                <button type="button" wire:click="kosongkan" class="btn btn-ghost btn-sm">Mulai baru</button>
            </div>

            <div>
                <div class="flex items-baseline justify-between"><label for="bc-judul" class="lbl">Judul</label><span class="hint tabular-nums">{{ mb_strlen($judul) }}/60</span></div>
                <input id="bc-judul" type="text" wire:model.live.debounce.300ms="judul" maxlength="60" placeholder="Contoh: Tema baru sudah tersedia" class="inp" @error('judul') aria-invalid="true" @enderror>
                @error('judul')<div role="alert" class="galat mt-1">{{ $message }}</div>@enderror
            </div>

            <div>
                <div class="flex items-baseline justify-between"><label for="bc-isi" class="lbl">Isi pesan</label><span class="hint tabular-nums">{{ mb_strlen($isi) }}/300</span></div>
                <textarea id="bc-isi" wire:model.live.debounce.300ms="isi" maxlength="300" rows="4" placeholder="Tulis isi pengumuman. Singkat dan jelas lebih mudah dibaca." class="inp" @error('isi') aria-invalid="true" @enderror></textarea>
                @error('isi')<div role="alert" class="galat mt-1">{{ $message }}</div>@enderror
            </div>

            <div class="grid gap-3.5 sm:grid-cols-3">
                <div>
                    <label for="bc-tipe" class="lbl">Jenis</label>
                    <select id="bc-tipe" wire:model.live="tipe" class="inp">
                        @foreach ($tipeBoleh as $t)<option value="{{ $t }}">{{ $jenis[$t][0] }}</option>@endforeach
                    </select>
                    @error('tipe')<div role="alert" class="galat mt-1">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label for="bc-paket" class="lbl">Paket sasaran</label>
                    <select id="bc-paket" wire:model.live="paket" class="inp">
                        @foreach ($namaPaket as $kode => $nama)<option value="{{ $kode }}">{{ $nama }}</option>@endforeach
                    </select>
                    @error('paket')<div role="alert" class="galat mt-1">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label for="bc-peran" class="lbl">Peran sasaran</label>
                    <select id="bc-peran" wire:model.live="peran" class="inp">
                        @foreach ($labelPeran as $k => $nama)<option value="{{ $k }}">{{ $nama }}</option>@endforeach
                    </select>
                    @error('peran')<div role="alert" class="galat mt-1">{{ $message }}</div>@enderror
                </div>
            </div>

            <div>
                <label for="bc-tautan" class="lbl">Tautan saat diklik <span class="font-normal text-muted">(opsional)</span></label>
                <input id="bc-tautan" type="text" list="bc-saran" wire:model.live.debounce.300ms="tautan" maxlength="500" placeholder="/billing atau https://…" class="inp" autocomplete="off" @error('tautan') aria-invalid="true" @enderror>
                <datalist id="bc-saran">@foreach ($saranTautan as $jalur => $nama)<option value="{{ $jalur }}">{{ $nama }}</option>@endforeach</datalist>
                <div class="hint mt-1">Halaman di undangyah (diawali "/") atau alamat https. Tautan lain ditolak.</div>
                @error('tautan')<div role="alert" class="galat mt-1">{{ $message }}</div>@enderror
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-[#eef0f3] pt-4">
                <div class="sub" aria-live="polite"><span class="font-semibold text-ink tabular-nums">{{ $angka($penerima) }}</span> pengguna akan menerima siaran ini.</div>
                <button type="button" wire:click="mintaKirim" wire:loading.attr="disabled" wire:target="mintaKirim" class="btn btn-primary"><x-ikon nama="bagikan-kirim" :ukuran="16" /> Kirim siaran</button>
            </div>
        </section>

        <aside aria-labelledby="pv-h" class="flex flex-col gap-4">
            <section class="kartu flex flex-col gap-3 p-5">
                <div>
                    <h2 id="pv-h" class="h2">Pratinjau</h2>
                    <div class="sub">Seperti yang dilihat pengguna di halaman Notifikasi.</div>
                </div>
                <article class="flex items-start gap-3.5 rounded-xl border border-line bg-[#F5F8FF] p-4">
                    <span aria-hidden="true" class="flex size-11 shrink-0 items-center justify-center rounded-full" style="background:{{ $jenisBg }};color:{{ $jenisFg }}"><x-ikon :nama="$jenisIkon" :ukuran="22" /></span>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <span class="text-sm leading-[19px] font-bold [overflow-wrap:anywhere] {{ trim($judul) === '' ? 'text-subtle' : 'text-ink' }}">{{ trim($judul) !== '' ? $judul : 'Judul notifikasi' }}</span>
                            <span role="img" aria-label="Belum dibaca" class="size-2.5 shrink-0 rounded-full bg-brand"></span>
                        </div>
                        <div class="mt-0.5 text-[13px] leading-[19px] font-medium [overflow-wrap:anywhere] whitespace-pre-line {{ trim($isi) === '' ? 'text-subtle' : 'text-nav' }}">{{ trim($isi) !== '' ? $isi : 'Isi pesan akan tampil di sini.' }}</div>
                        <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1.5">
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs leading-4 font-bold" style="background:{{ $jenisBg }};color:{{ $jenisFg }}">{{ $jenisNama }}</span>
                            <span class="text-xs leading-4 font-semibold text-muted">Baru saja</span>
                            @if ($adaTautan)<span class="text-xs leading-4 font-bold text-brand">{{ $jenisCta }}</span>@endif
                        </div>
                    </div>
                </article>
            </section>
            <section class="kartu p-5">
                <div class="text-[13px] leading-[18px] font-semibold text-ink">Sasaran</div>
                <div class="mt-1 text-[13px] leading-[18px] text-muted">{{ $namaPaket[$paket] ?? '–' }} · {{ $labelPeran[$peran] ?? '–' }}</div>
                <div class="kpi-v mt-2">{{ $angka($penerima) }}</div>
                <div class="hint">penerima saat ini (email terverifikasi, tidak ditangguhkan)</div>
            </section>
        </aside>
    </div>

    <section aria-labelledby="rw-h" class="kartu overflow-hidden" @if ($adaBerjalan) wire:poll.5s @endif>
        <div class="flex flex-col gap-3 p-4 sm:p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 id="rw-h" class="h2">Riwayat siaran</h2>
                    <div class="sub">{{ $angka($total) }} siaran{{ $adaBerjalan ? ' · ada yang sedang diproses' : '' }}</div>
                </div>
                <div class="w-full sm:w-72">
                    <label for="rw-q" class="sr-only">Cari siaran</label>
                    <input id="rw-q" type="search" wire:model.live.debounce.400ms="q" maxlength="100" placeholder="Cari judul atau isi" class="inp" autocomplete="off">
                </div>
            </div>
            <div class="seg flex-wrap" role="group" aria-label="Filter status">
                @foreach (['semua' => 'Semua'] + collect(\App\Models\SiaranNotifikasi::STATUS)->map(fn ($v) => $v[0])->all() as $kunci => $label)
                    <button type="button" wire:click="pilihFilter('{{ $kunci }}')" aria-pressed="{{ $filter === $kunci ? 'true' : 'false' }}">{{ $label }} <span class="tabular-nums text-muted">{{ $angka((int) ($hitung[$kunci] ?? 0)) }}</span></button>
                @endforeach
            </div>
        </div>

        @if ($total === 0)
            <x-kosong :judul="$q !== '' || $filter !== 'semua' ? 'Tidak ada yang cocok' : 'Belum ada siaran'" :teks="$q !== '' || $filter !== 'semua' ? 'Tidak ada siaran yang cocok dengan pencarian atau filter ini.' : 'Siaran yang kamu kirim akan tercatat di sini.'" ikon="admin-notif" />
        @else
            <div class="overflow-x-auto">
                <table class="tabel min-w-[820px]">
                    <caption class="sr-only">Riwayat siaran notifikasi</caption>
                    <thead><tr><th>Siaran</th><th>Sasaran</th><th>Status</th><th>Waktu</th><th class="text-right">Aksi</th></tr></thead>
                    <tbody>
                        @foreach ($riwayat as $s)
                            @php
                                $info = $s->infoStatus();
                                $persen = $s->jumlah_target > 0 ? min(100, (int) floor($s->terkirim / $s->jumlah_target * 100)) : ($s->status === 'selesai' ? 100 : 0);
                            @endphp
                            <tr wire:key="siaran-{{ $s->id }}">
                                <td class="max-w-[320px]">
                                    <div class="truncate font-semibold text-ink" title="{{ $s->judul }}">{{ $s->judul }}</div>
                                    <div class="line-clamp-2 text-xs text-muted" title="{{ $s->isi }}">{{ $s->isi }}</div>
                                </td>
                                <td><div>{{ $labelTarget($s) }}</div><div class="text-xs text-muted tabular-nums">{{ $angka($s->jumlah_target) }} penerima</div></td>
                                <td>
                                    <x-pill :warna="$info[1]">{{ $info[0] }}</x-pill>
                                    <div class="mt-0.5 text-xs text-muted tabular-nums">{{ $angka($s->terkirim) }} / {{ $angka($s->jumlah_target) }}@if ($s->belumSelesai()) ({{ $persen }}%)@endif</div>
                                </td>
                                <td class="whitespace-nowrap"><div>{{ $s->created_at->locale('id')->translatedFormat('j M Y, H.i') }}</div><div class="text-xs text-muted">{{ $s->admin?->name ?? 'Admin dihapus' }}</div></td>
                                <td>
                                    <div class="flex items-center justify-end gap-1.5">
                                        @if ($s->belumSelesai())
                                            <button type="button" wire:click="prosesSekarang({{ $s->id }})" wire:loading.attr="disabled" wire:target="prosesSekarang({{ $s->id }})" class="btn btn-secondary btn-sm">Proses sekarang</button>
                                        @endif
                                        <button type="button" wire:click="gunakanLagi({{ $s->id }})" class="btn-icon" aria-label="Gunakan lagi {{ $s->judul }}" title="Gunakan lagi"><x-ikon nama="bagikan-duplikat" :ukuran="15" /></button>
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

    <x-dialog wire:model="tampilKirim" judul="Kirim siaran ini?" lebar="max-w-[480px]">
        <p class="sub m-0">Siaran dikirim ke <span class="font-semibold text-ink tabular-nums">{{ $angka($penerima) }}</span> pengguna ({{ $namaPaket[$paket] ?? '–' }} · {{ $labelPeran[$peran] ?? '–' }}). Setelah dikirim, notifikasi tidak bisa ditarik kembali.</p>
        <div class="rounded-[10px] border border-line bg-surface px-3.5 py-3">
            <div class="text-sm font-semibold text-ink [overflow-wrap:anywhere]">{{ $judul }}</div>
            <div class="mt-0.5 text-[13px] leading-[18px] text-muted [overflow-wrap:anywhere] whitespace-pre-line">{{ $isi }}</div>
        </div>
        <x-slot:footer>
            <button type="button" wire:click="$set('tampilKirim', false)" class="btn btn-secondary">Batal</button>
            <button type="button" wire:click="kirim" wire:loading.attr="disabled" wire:target="kirim" class="btn btn-primary">Ya, kirim</button>
        </x-slot:footer>
    </x-dialog>
</div>
