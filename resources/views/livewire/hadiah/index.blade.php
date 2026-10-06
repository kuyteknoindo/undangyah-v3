@php
    $angka = fn (int|float $n): string => number_format($n, 0, ',', '.');
    $rp = fn (int|float $n): string => 'Rp '.number_format($n, 0, ',', '.');
    $inisial = function (string $nama): string {
        $kata = preg_split('/\s+/u', trim($nama), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $ini = collect($kata)->take(2)->map(fn ($k) => mb_strtoupper(mb_substr($k, 0, 1)))->implode('');

        return $ini !== '' ? $ini : '?';
    };
    $chips = [['semua', 'Semua', 'total'], ['done', 'Terkonfirmasi', 'selesai'], ['pending', 'Menunggu', 'menunggu']];
    $salin = "{ salin: false, async copy(t) { try { await navigator.clipboard.writeText(t); } catch (e) { const a = document.createElement('textarea'); a.value = t; document.body.appendChild(a); a.select(); try { document.execCommand('copy'); } catch (e2) {} a.remove(); } this.salin = true; setTimeout(() => this.salin = false, 1800); } }";
@endphp
<div wire:init="muat" class="mx-auto flex max-w-6xl flex-col gap-5">
    @unless ($siap)
        <div role="status" aria-busy="true" class="flex flex-col gap-5">
            <span class="sr-only">Memuat hadiah digital…</span>
            <div class="kartu flex flex-col gap-3 p-5"><span class="skel h-3 w-24"></span><span class="skel h-11 w-full max-w-md"></span></div>
            <div class="skel h-12 w-full"></div>
            <div class="grid gap-5 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)]">
                <div class="kartu flex flex-col gap-3 p-5"><span class="skel h-4 w-40"></span><span class="skel h-24 w-full"></span><span class="skel h-24 w-full"></span></div>
                <div class="kartu flex flex-col gap-3 p-5"><span class="skel h-4 w-32"></span><span class="skel h-20 w-full"></span></div>
            </div>
            <div class="kartu p-5"><span class="skel h-4 w-32"></span><span class="skel mt-4 h-40 w-full"></span></div>
        </div>
    @elseif (! $undangan)
        <section aria-labelledby="hd-kosong" class="kartu p-6 sm:p-8">
            <x-kosong judul="Belum ada undangan" teks="Atur rekening, alamat kirim kado, dan catat hadiah setelah kamu membuat undangan." ikon="hadiah">
                <a href="{{ $tautanBuat }}" wire:navigate class="btn btn-primary"><x-ikon nama="tambah" :ukuran="16" /> Buat undangan</a>
            </x-kosong>
        </section>
    @else
        @php
            $rekening = $data['rekening'];
            $alamat = $data['alamat'];
            $ringkas = $data['ringkas'];
            $item = $data['item'];
        @endphp

        <section aria-label="Pilih undangan" class="kartu flex flex-wrap items-end justify-between gap-4 p-4 sm:p-5">
            <div class="flex w-full max-w-md flex-col gap-1.5">
                <label for="hd-inv" class="label">Undangan</label>
                <select id="hd-inv" class="inp" wire:change="pilihUndangan($event.target.value)">
                    @foreach ($daftar as $d)
                        <option value="{{ $d->id }}" @selected($d->id === $undangan->id)>{{ $d->judul }} ({{ $d->sudahTerbit() ? 'Terbit' : 'Draf' }})</option>
                    @endforeach
                </select>
            </div>
            <x-pill :warna="$undangan->sudahTerbit() ? 'hijau' : 'kuning'">{{ $undangan->sudahTerbit() ? 'Terbit' : 'Draf' }}</x-pill>
        </section>

        <div role="note" class="flex items-start gap-2.5 rounded-xl border border-brand-200 bg-brand-50 px-4 py-3 text-[13px] leading-5 text-nav">
            <x-ikon nama="bt-info" :ukuran="16" class="mt-0.5 text-brand" />
            <span>Hadiah dikirim tamu langsung ke rekening atau e-wallet kamu. undangyah.com tidak menerima atau menahan uang. Konfirmasi di halaman ini hanya untuk pencatatan.</span>
        </div>

        @if ($data['bagianDisembunyikan'])
            <div role="note" class="flex flex-wrap items-center gap-2.5 rounded-xl bg-[#FFFAEB] px-4 py-3 text-[13px] leading-5 text-[#B54708]">
                <x-ikon nama="peringatan" :ukuran="16" />
                <span class="min-w-0 flex-1">Bagian Kado Digital disembunyikan di editor, jadi rekening belum tampil di undangan.</span>
                <a href="{{ $tautanEditor }}" wire:navigate class="font-semibold underline">Buka editor</a>
            </div>
        @elseif ($data['adaPerubahan'] && $undangan->sudahTerbit())
            <div role="note" class="flex flex-wrap items-center gap-2.5 rounded-xl bg-brand-50 px-4 py-3 text-[13px] leading-5 text-brand">
                <x-ikon nama="peringatan" :ukuran="16" />
                <span class="min-w-0 flex-1">Ada perubahan yang belum diterbitkan. Rekening dan alamat di undangan tamu berubah setelah kamu menerbitkan ulang lewat editor.</span>
                <a href="{{ $tautanEditor }}" wire:navigate class="font-semibold underline">Buka editor</a>
            </div>
        @endif

        <div class="grid grid-cols-1 items-start gap-5 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)] [&>*]:min-w-0">
            <section aria-label="Rekening dan e-wallet" class="kartu flex flex-col gap-4 p-5">
                <div class="flex items-start justify-between gap-3">
                    <div><h2 class="h2">Rekening &amp; e-wallet</h2><div class="sub">Tampil di undangan agar tamu bisa mengirim hadiah.</div></div>
                    <button type="button" wire:click="bukaRek" class="btn btn-secondary btn-sm shrink-0"><x-ikon nama="bt-tambah" :ukuran="14" /> Tambah</button>
                </div>

                @if ($rekening === [])
                    <div role="status">
                        <x-kosong judul="Belum ada rekening atau e-wallet" teks="Tambahkan rekening bank atau e-wallet agar tamu tahu ke mana hadiah dikirim." ikon="bt-bank">
                            <button type="button" wire:click="bukaRek" class="btn btn-primary btn-sm">Tambah rekening</button>
                        </x-kosong>
                    </div>
                @else
                    <div class="flex flex-col gap-3">
                        @foreach ($rekening as $r)
                            <article wire:key="rek-{{ $r['id'] }}" x-data="{{ $salin }}" class="flex flex-col gap-3 rounded-2xl border-2 border-line p-4">
                                <div class="flex items-center gap-3">
                                    <span aria-hidden="true" class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand"><x-ikon :nama="$r['jenis'] === 'ewallet' ? 'bt-dompet' : 'bt-bank'" :ukuran="20" /></span>
                                    <div class="min-w-0"><div class="truncate text-sm font-semibold text-ink">{{ $r['bank'] !== '' ? $r['bank'] : 'Tanpa nama bank' }}</div><div class="hint">{{ $r['jenis'] === 'ewallet' ? 'E-wallet' : 'Rekening bank' }}</div></div>
                                </div>
                                <div>
                                    <div class="text-xl leading-7 font-semibold tracking-wide text-ink tabular-nums [overflow-wrap:anywhere]">{{ $r['no'] !== '' ? $r['no'] : '-' }}</div>
                                    <div class="sub">a.n. {{ $r['an'] !== '' ? $r['an'] : '-' }}</div>
                                </div>
                                @if ($hapusRekId === $r['id'])
                                    <div role="alert" class="flex flex-wrap items-center gap-2 rounded-xl bg-[#fef3f2] px-3 py-2">
                                        <span class="min-w-0 flex-1 text-[13px] leading-5 font-medium text-[#b42318]">Hapus akun ini?</span>
                                        <button type="button" wire:click="batalHapusRek" class="btn btn-secondary btn-sm">Batal</button>
                                        <button type="button" wire:click="hapusRek" class="btn btn-danger-solid btn-sm">Hapus</button>
                                    </div>
                                @else
                                    <div class="flex flex-wrap gap-2">
                                        <button type="button" x-on:click="copy(@js(preg_replace('/\s+/', '', $r['no'])))" class="btn btn-secondary btn-sm" aria-label="Salin nomor {{ $r['bank'] }}"><x-ikon nama="bt-salin" :ukuran="14" /> <span x-text="salin ? 'Tersalin' : 'Salin nomor'">Salin nomor</span></button>
                                        <button type="button" wire:click="bukaRek({{ $r['id'] }})" class="btn btn-secondary btn-sm" aria-label="Edit {{ $r['bank'] }}"><x-ikon nama="bt-ubah" :ukuran="14" /> Edit</button>
                                        <button type="button" wire:click="tanyaHapusRek({{ $r['id'] }})" class="btn btn-danger btn-sm" aria-label="Hapus {{ $r['bank'] }}"><x-ikon nama="bt-hapus" :ukuran="14" /> Hapus</button>
                                    </div>
                                @endif
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>

            <div class="flex flex-col gap-5">
                <section aria-label="Alamat kado" class="kartu flex flex-col gap-4 p-5">
                    <div><h2 class="h2">Alamat kado fisik</h2><div class="sub">Untuk tamu yang ingin mengirim barang.</div></div>
                    @if ($alamat)
                        <div x-data="{{ $salin }}" class="flex flex-col gap-3">
                            <div class="rounded-xl bg-canvas p-3.5 text-sm leading-5">
                                <div class="font-semibold text-ink [overflow-wrap:anywhere]">{{ $alamat['penerima'] !== '' ? $alamat['penerima'] : '-' }}</div>
                                <div class="text-muted">{{ $alamat['hp'] !== '' ? $alamat['hp'] : 'Tanpa nomor telepon' }}</div>
                                <div class="mt-1 whitespace-pre-line text-body [overflow-wrap:anywhere]">{{ $alamat['alamat'] }}</div>
                            </div>
                            @if ($hapusAlamatTanya)
                                <div role="alert" class="flex flex-wrap items-center gap-2 rounded-xl bg-[#fef3f2] px-3 py-2">
                                    <span class="min-w-0 flex-1 text-[13px] leading-5 font-medium text-[#b42318]">Hapus alamat ini?</span>
                                    <button type="button" wire:click="batalHapusAlamat" class="btn btn-secondary btn-sm">Batal</button>
                                    <button type="button" wire:click="hapusAlamat" class="btn btn-danger-solid btn-sm">Hapus</button>
                                </div>
                            @else
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" x-on:click="copy(@js(implode("\n", array_filter([$alamat['penerima'], $alamat['hp'], $alamat['alamat']]))))" class="btn btn-secondary btn-sm"><x-ikon nama="bt-salin" :ukuran="14" /> <span x-text="salin ? 'Tersalin' : 'Salin alamat'">Salin alamat</span></button>
                                    <button type="button" wire:click="bukaAlamat" class="btn btn-secondary btn-sm"><x-ikon nama="bt-ubah" :ukuran="14" /> Edit</button>
                                    <button type="button" wire:click="tanyaHapusAlamat" class="btn btn-danger btn-sm"><x-ikon nama="bt-hapus" :ukuran="14" /> Hapus</button>
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="sub">Belum ada alamat. Tambahkan jika kamu ingin menerima kado fisik.</div>
                        <button type="button" wire:click="bukaAlamat" class="btn btn-secondary btn-sm self-start"><x-ikon nama="bt-tambah" :ukuran="14" /> Tambah alamat</button>
                    @endif
                </section>

                <section aria-label="Ucapan terima kasih" class="kartu flex flex-col gap-3 p-5">
                    <div><h2 class="h2">Ucapan terima kasih</h2><div class="sub">Pesan singkat untuk tamu yang memberi hadiah (opsional).</div></div>
                    <div class="flex flex-col gap-1.5">
                        <label for="hd-ut" class="label">Ucapan terima kasih</label>
                        <textarea id="hd-ut" class="inp" rows="3" maxlength="{{ \App\Livewire\HadiahDigital::MAKS_UCAPAN }}" placeholder="Terima kasih atas doa dan hadiahnya." wire:model.live.debounce.300ms="ucapanTerima" @error('ucapanTerima') aria-invalid="true" @enderror></textarea>
                        @error('ucapanTerima')<div role="alert" class="galat">{{ $message }}</div>@enderror
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="hint">{{ mb_strlen($ucapanTerima) }} / {{ \App\Livewire\HadiahDigital::MAKS_UCAPAN }} karakter</span>
                        <button type="button" wire:click="simpanUcapanTerima" class="btn btn-primary btn-sm">Simpan</button>
                    </div>
                </section>
            </div>
        </div>

        <section aria-label="Hadiah masuk" class="kartu flex flex-col">
            <div class="flex flex-col gap-4 p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div><h2 class="h2">Hadiah masuk</h2><div class="sub">Catatan manual hadiah yang kamu terima untuk undangan ini.</div></div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" wire:click="ekspor" wire:loading.attr="disabled" wire:target="ekspor" class="btn btn-secondary btn-sm"><x-ikon nama="bt-unduh" :ukuran="14" /> Ekspor CSV</button>
                        <button type="button" wire:click="bukaHadiah" class="btn btn-primary btn-sm"><x-ikon nama="bt-tambah" :ukuran="14" /> Catat hadiah</button>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <div class="rounded-xl bg-canvas p-3.5"><div class="text-[11px] leading-[14px] font-bold tracking-[0.08em] text-muted uppercase">Total terkonfirmasi</div><div class="mt-1 text-xl leading-7 font-semibold text-ink tabular-nums">{{ $rp($ringkas['uang']) }}</div></div>
                    <div class="rounded-xl bg-canvas p-3.5"><div class="text-[11px] leading-[14px] font-bold tracking-[0.08em] text-muted uppercase">Pemberi</div><div class="mt-1 text-xl leading-7 font-semibold text-ink tabular-nums">{{ $angka($ringkas['total']) }}</div></div>
                    <div class="rounded-xl bg-canvas p-3.5"><div class="text-[11px] leading-[14px] font-bold tracking-[0.08em] text-muted uppercase">Terkonfirmasi</div><div class="mt-1 text-xl leading-7 font-semibold text-ink tabular-nums">{{ $angka($ringkas['selesai']) }}</div></div>
                    <div class="rounded-xl bg-canvas p-3.5"><div class="text-[11px] leading-[14px] font-bold tracking-[0.08em] text-muted uppercase">Menunggu</div><div class="mt-1 text-xl leading-7 font-semibold text-ink tabular-nums">{{ $angka($ringkas['menunggu']) }}</div></div>
                </div>
                <div role="group" aria-label="Filter hadiah" class="flex flex-wrap gap-2">
                    @foreach ($chips as [$kunci, $label, $hk])
                        <button type="button" wire:click="pilihFilter('{{ $kunci }}')" aria-pressed="{{ $filter === $kunci ? 'true' : 'false' }}"
                                class="inline-flex min-h-8 items-center gap-1.5 rounded-full border px-3 text-[13px] leading-5 font-medium transition {{ $filter === $kunci ? 'border-brand bg-brand-50 text-brand' : 'border-line-strong bg-white text-nav hover:bg-gray-50' }}">
                            {{ $label }}<span class="tag">{{ $angka($ringkas[$hk]) }}</span>
                        </button>
                    @endforeach
                </div>
            </div>

            @if ($ringkas['total'] === 0)
                <div class="border-t border-line p-5" role="status">
                    <x-kosong judul="Belum ada hadiah tercatat" teks="Hadiah ditransfer langsung ke rekeningmu dan tidak tercatat otomatis. Catat hadiah yang kamu terima agar tidak terlewat saat mengucapkan terima kasih." ikon="hadiah">
                        <button type="button" wire:click="bukaHadiah" class="btn btn-primary btn-sm">Catat hadiah</button>
                    </x-kosong>
                </div>
            @elseif ($item->total() === 0)
                <div class="border-t border-line p-5" role="status">
                    <x-kosong judul="Tidak ada hadiah pada filter ini" teks="Pilih filter lain untuk melihat hadiah yang lain." ikon="bt-cari">
                        <button type="button" wire:click="pilihFilter('semua')" class="btn btn-secondary btn-sm">Tampilkan semua</button>
                    </x-kosong>
                </div>
            @else
                <div class="overflow-x-auto border-t border-line">
                    <table class="tabel min-w-[720px]">
                        <caption class="sr-only">Daftar hadiah masuk</caption>
                        <thead><tr><th>Pemberi</th><th>Lewat</th><th class="text-right">Nominal</th><th>Status</th><th>Pesan</th><th>Aksi</th></tr></thead>
                        <tbody>
                            @foreach ($item as $h)
                                <tr wire:key="hadiah-{{ $h->id }}">
                                    <td>
                                        <div class="flex items-center gap-2.5">
                                            <span aria-hidden="true" class="flex size-8 shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-bold text-brand">{{ $inisial($h->nama) }}</span>
                                            <span class="flex min-w-0 flex-col"><span class="font-semibold text-ink [overflow-wrap:anywhere]">{{ $h->nama }}</span><span class="hint">{{ $h->created_at?->locale('id')->diffForHumans() }}</span></span>
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap">{{ $h->via }}</td>
                                    <td class="text-right whitespace-nowrap tabular-nums">{{ $rp($h->nominal) }}</td>
                                    <td><x-pill :warna="$h->terkonfirmasi ? 'hijau' : 'kuning'">{{ $h->terkonfirmasi ? 'Terkonfirmasi' : 'Menunggu' }}</x-pill></td>
                                    <td class="max-w-56 [overflow-wrap:anywhere]">{{ $h->pesan ?: '–' }}</td>
                                    <td>
                                        @if ($hapusHadiahId === $h->id)
                                            <div role="alert" class="flex items-center gap-2">
                                                <button type="button" wire:click="batalHapusHadiah" class="btn btn-secondary btn-sm">Batal</button>
                                                <button type="button" wire:click="hapusHadiah" class="btn btn-danger-solid btn-sm">Hapus</button>
                                            </div>
                                        @else
                                            <div class="flex items-center gap-2">
                                                @unless ($h->terkonfirmasi)
                                                    <button type="button" wire:click="konfirmasi({{ $h->id }})" class="btn btn-secondary btn-sm whitespace-nowrap"><x-ikon nama="centang" :ukuran="14" /> Konfirmasi diterima</button>
                                                @endunless
                                                <button type="button" wire:click="tanyaHapusHadiah({{ $h->id }})" class="btn-icon" aria-label="Hapus catatan {{ $h->nama }}"><x-ikon nama="bt-hapus" :ukuran="15" /></button>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($item->lastPage() > 1)
                    <nav aria-label="Halaman hadiah" class="flex flex-wrap items-center justify-between gap-3 border-t border-line p-4">
                        <span class="hint">Menampilkan {{ $angka($item->firstItem()) }}–{{ $angka($item->lastItem()) }} dari {{ $angka($item->total()) }} hadiah</span>
                        <div class="flex items-center gap-2">
                            <button type="button" wire:click="ke({{ $item->currentPage() - 1 }})" @disabled($item->onFirstPage()) class="btn btn-secondary btn-sm"><x-ikon nama="bt-panah-kiri" :ukuran="14" /> Sebelumnya</button>
                            <span class="text-[13px] text-muted">{{ $item->currentPage() }} / {{ $item->lastPage() }}</span>
                            <button type="button" wire:click="ke({{ $item->currentPage() + 1 }})" @disabled(! $item->hasMorePages()) class="btn btn-secondary btn-sm">Berikutnya <x-ikon nama="bt-panah-kanan" :ukuran="14" /></button>
                        </div>
                    </nav>
                @endif
            @endif
        </section>

        <x-dialog wire:model="tampilRek" :judul="$rekId ? 'Edit rekening' : 'Tambah rekening'" lebar="max-w-[480px]">
            <div>
                <div class="lbl">Jenis</div>
                <div class="seg" role="group" aria-label="Jenis rekening">
                    <button type="button" wire:click="pilihJenis('bank')" aria-pressed="{{ $rJenis === 'bank' ? 'true' : 'false' }}">Rekening bank</button>
                    <button type="button" wire:click="pilihJenis('ewallet')" aria-pressed="{{ $rJenis === 'ewallet' ? 'true' : 'false' }}">E-wallet</button>
                </div>
            </div>
            @php
                $pilihanBank = $rJenis === 'ewallet' ? $ewallet : $bank;
                if ($rBank !== '' && ! in_array($rBank, $pilihanBank, true)) {
                    $pilihanBank[] = $rBank;
                }
            @endphp
            <div>
                <label for="rk-bank" class="lbl">{{ $rJenis === 'ewallet' ? 'E-wallet' : 'Bank' }}</label>
                <select id="rk-bank" class="inp" wire:model="rBank" @error('rBank') aria-invalid="true" @enderror>
                    @foreach ($pilihanBank as $o)<option value="{{ $o }}">{{ $o }}</option>@endforeach
                </select>
                @error('rBank')<div role="alert" class="galat mt-1">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="rk-no" class="lbl">{{ $rJenis === 'ewallet' ? 'Nomor e-wallet' : 'Nomor rekening' }}</label>
                <input id="rk-no" type="text" inputmode="numeric" class="inp" autocomplete="off" maxlength="40" placeholder="{{ $rJenis === 'ewallet' ? '081234567890' : '1234567890' }}" wire:model="rNo" @error('rNo') aria-invalid="true" @enderror>
                <div class="hint mt-1">Hanya angka. Spasi dan tanda strip otomatis dihapus.</div>
                @error('rNo')<div role="alert" class="galat mt-1">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="rk-an" class="lbl">Atas nama</label>
                <input id="rk-an" type="text" class="inp" autocomplete="off" maxlength="120" placeholder="Nama sesuai rekening" wire:model="rAn" @error('rAn') aria-invalid="true" @enderror>
                @error('rAn')<div role="alert" class="galat mt-1">{{ $message }}</div>@enderror
            </div>
            <x-slot:footer>
                <button type="button" x-on:click="buka = false" class="btn btn-secondary">Batal</button>
                <button type="button" wire:click="simpanRek" wire:loading.attr="disabled" wire:target="simpanRek" class="btn btn-primary">Simpan</button>
            </x-slot:footer>
        </x-dialog>

        <x-dialog wire:model="tampilAlamat" judul="Alamat kado" lebar="max-w-[480px]">
            <div>
                <label for="al-nama" class="lbl">Nama penerima</label>
                <input id="al-nama" type="text" class="inp" maxlength="120" placeholder="Nama penerima kado" wire:model="aNama" @error('aNama') aria-invalid="true" @enderror>
                @error('aNama')<div role="alert" class="galat mt-1">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="al-hp" class="lbl">Nomor telepon (opsional)</label>
                <input id="al-hp" type="text" inputmode="tel" class="inp" maxlength="30" placeholder="08123456789" wire:model="aHp" @error('aHp') aria-invalid="true" @enderror>
                @error('aHp')<div role="alert" class="galat mt-1">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="al-alamat" class="lbl">Alamat lengkap</label>
                <textarea id="al-alamat" class="inp" rows="3" maxlength="320" placeholder="Jalan, nomor, kelurahan, kota, kode pos" wire:model="aAlamat" @error('aAlamat') aria-invalid="true" @enderror></textarea>
                @error('aAlamat')<div role="alert" class="galat mt-1">{{ $message }}</div>@enderror
            </div>
            <x-slot:footer>
                <button type="button" x-on:click="buka = false" class="btn btn-secondary">Batal</button>
                <button type="button" wire:click="simpanAlamat" wire:loading.attr="disabled" wire:target="simpanAlamat" class="btn btn-primary">Simpan</button>
            </x-slot:footer>
        </x-dialog>

        <x-dialog wire:model="tampilHadiah" judul="Catat hadiah masuk" lebar="max-w-[480px]">
            <div>
                <label for="tm-nama" class="lbl">Nama pemberi</label>
                <input id="tm-nama" type="text" class="inp" maxlength="100" placeholder="Nama tamu" wire:model="tNama" @error('tNama') aria-invalid="true" @enderror>
                @error('tNama')<div role="alert" class="galat mt-1">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="tm-via" class="lbl">Diterima lewat</label>
                <select id="tm-via" class="inp" wire:model="tVia" @error('tVia') aria-invalid="true" @enderror>
                    @foreach ($data['via'] as $o)<option value="{{ $o }}">{{ $o }}</option>@endforeach
                </select>
                @error('tVia')<div role="alert" class="galat mt-1">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="tm-nominal" class="lbl">Nominal (Rp)</label>
                <input id="tm-nominal" type="text" inputmode="numeric" class="inp" maxlength="16" placeholder="500000" wire:model.live.debounce.300ms="tNominal" @error('tNominal') aria-invalid="true" @enderror>
                @php
                    $nilaiNominal = (int) preg_replace('/\D/', '', $tNominal);
                @endphp
                <div class="hint mt-1">{{ $nilaiNominal > 0 && $nilaiNominal <= \App\Livewire\HadiahDigital::MAKS_NOMINAL ? 'Terbaca sebagai '.$rp($nilaiNominal) : 'Ketik angka saja, misalnya 500000.' }}</div>
                @error('tNominal')<div role="alert" class="galat mt-1">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="tm-pesan" class="lbl">Pesan (opsional)</label>
                <input id="tm-pesan" type="text" class="inp" maxlength="220" placeholder="Pesan dari tamu" wire:model="tPesan" @error('tPesan') aria-invalid="true" @enderror>
                @error('tPesan')<div role="alert" class="galat mt-1">{{ $message }}</div>@enderror
            </div>
            <label class="flex cursor-pointer items-center gap-2.5 text-[13px] leading-5 font-medium text-ink">
                <input type="checkbox" wire:model="tDone" class="size-4 shrink-0 accent-[#1D4ED8]"> Sudah saya terima (terkonfirmasi)
            </label>
            <x-slot:footer>
                <button type="button" x-on:click="buka = false" class="btn btn-secondary">Batal</button>
                <button type="button" wire:click="simpanHadiah" wire:loading.attr="disabled" wire:target="simpanHadiah" class="btn btn-primary">Simpan</button>
            </x-slot:footer>
        </x-dialog>
    @endif
</div>
