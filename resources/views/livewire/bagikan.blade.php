<div wire:init="muat" class="mx-auto flex max-w-6xl flex-col gap-5">
    @unless ($siap)
        <div role="status" aria-busy="true" class="flex flex-col gap-5">
            <span class="sr-only">Memuat halaman…</span>
            <div class="kartu flex flex-col gap-3 p-5"><span class="skel h-4 w-32"></span><span class="skel h-11 w-full max-w-md"></span></div>
            <div class="grid gap-5 grid-cols-[minmax(0,1fr)] lg:grid-cols-[minmax(0,1fr)_280px]">
                <div class="kartu flex flex-col gap-4 p-5"><span class="skel h-14 w-full"></span><span class="skel h-10 w-2/3"></span></div>
                <div class="kartu flex flex-col items-center gap-3 p-5"><span class="skel size-40"></span><span class="skel h-8 w-32"></span></div>
            </div>
            <x-skeleton mode="kartu" :jumlah="4" />
        </div>
    @elseif (! $undangan)
        <section class="kartu p-6 sm:p-8">
            <x-kosong judul="Belum ada undangan" teks="Buat undangan dulu, lalu bagikan link, kode QR, dan tautan pribadi untuk setiap tamu dari sini." ikon="bagikan">
                @if ($tautanBuat)<a href="{{ $tautanBuat }}" wire:navigate class="btn btn-primary"><x-ikon nama="tambah" :ukuran="16" /> Buat undangan</a>@endif
            </x-kosong>
        </section>
    @else
        <section aria-label="Pilih undangan" class="kartu flex flex-wrap items-end justify-between gap-4 p-4 sm:p-5">
            <div class="flex w-full max-w-md flex-col gap-1.5">
                <label for="bg-inv" class="label">Undangan</label>
                <select id="bg-inv" class="inp" wire:change="pilih($event.target.value)">
                    @foreach ($daftar as $u)
                        <option value="{{ $u->id }}" @selected($u->id === $undangan->id)>{{ $u->judul }} ({{ $u->sudahTerbit() ? 'Terbit' : 'Draf' }})</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center gap-2.5">
                <x-pill :warna="$terbit ? 'hijau' : 'kuning'">{{ $terbit ? 'Terbit' : 'Draf' }}</x-pill>
                <span class="sub">{{ $terbit ? 'Link sudah bisa dibuka tamu.' : 'Belum diterbitkan.' }}</span>
            </div>
        </section>

        @unless ($terbit)
            <div role="note" class="flex flex-wrap items-center gap-3 rounded-2xl border border-[#fedf89] bg-[#fffaeb] px-4 py-3 text-[13px] leading-5 font-medium text-[#b54708]">
                <x-ikon nama="peringatan" :ukuran="16" />
                <span class="min-w-0 flex-1">Undangan ini belum diterbitkan, jadi link belum bisa dibuka tamu. Terbitkan dulu di editor, lalu kembali ke sini untuk membagikannya.</span>
                @if ($tautanEditor)<a href="{{ $tautanEditor }}" wire:navigate class="btn btn-secondary btn-sm shrink-0">Buka editor</a>@endif
            </div>
        @endunless

        {{-- Link dan QR --}}
        <section aria-label="Link dan kode QR" class="grid grid-cols-[minmax(0,1fr)] items-start gap-5 lg:grid-cols-[minmax(0,1fr)_280px]">
            <div class="kartu flex flex-col gap-5 p-5"
                 x-data="{ salin: false, async copy() { const t = @js($link); try { await navigator.clipboard.writeText(t); } catch (e) { const a = document.createElement('textarea'); a.value = t; document.body.appendChild(a); a.select(); try { document.execCommand('copy'); } catch (e2) {} a.remove(); } this.salin = true; setTimeout(() => this.salin = false, 1800); }, bisaBagikan: typeof navigator !== 'undefined' && typeof navigator.share === 'function' }">
                <div class="flex items-center gap-3 rounded-xl bg-canvas p-3.5">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand"><x-ikon nama="template-tautan" :ukuran="18" /></span>
                    <div class="min-w-0 flex-1">
                        <div class="text-[11px] leading-[14px] font-bold tracking-[0.08em] text-muted uppercase">Link undangan utama</div>
                        <div class="mt-1 truncate text-sm leading-5 font-semibold text-ink">{{ $linkTampil !== '' ? $linkTampil : 'Belum ada alamat link' }}</div>
                    </div>
                    <button type="button" x-on:click="copy()" class="btn btn-secondary btn-sm shrink-0" @disabled($link === '')>
                        <x-ikon nama="bagikan-salin" :ukuran="14" /> <span x-text="salin ? 'Tersalin' : 'Salin'">Salin</span>
                    </button>
                </div>

                <div>
                    <div class="lbl">Bagikan lewat</div>
                    <div class="flex flex-wrap gap-2 {{ $terbit ? '' : 'pointer-events-none opacity-50' }}">
                        @php
                            $kanal = [
                                ['WhatsApp', 'W', '#0f7a3f', 'https://wa.me/?text='.rawurlencode($pesanUmum)],
                                ['Telegram', 'T', '#1a7aa8', 'https://t.me/share/url?url='.rawurlencode($link).'&text='.rawurlencode($pesanUmum)],
                                ['Email', 'E', '#666C76', 'mailto:?subject='.rawurlencode($judulSurel).'&body='.rawurlencode($pesanUmum)],
                                ['Facebook', 'f', '#1565d8', 'https://www.facebook.com/sharer/sharer.php?u='.rawurlencode($link)],
                                ['X', 'X', '#262A31', 'https://twitter.com/intent/tweet?text='.rawurlencode($pesanUmum).'&url='.rawurlencode($link)],
                            ];
                        @endphp
                        @foreach ($kanal as [$nama, $inisial, $warna, $href])
                            <a href="{{ $terbit ? $href : '#' }}" target="_blank" rel="noopener noreferrer" aria-label="Bagikan lewat {{ $nama }}" @unless ($terbit) tabindex="-1" aria-disabled="true" @endunless
                               class="inline-flex min-h-10 items-center gap-2 rounded-xl border border-line-strong bg-white py-1 pr-3.5 pl-1.5 text-[13px] font-semibold text-ink hover:bg-gray-50">
                                <span aria-hidden="true" class="flex size-7 items-center justify-center rounded-lg text-[13px] font-bold text-white" style="background:{{ $warna }}">{{ $inisial }}</span>{{ $nama }}
                            </a>
                        @endforeach
                        <button type="button" x-show="bisaBagikan" x-cloak aria-label="Bagikan lewat aplikasi lain" x-on:click="navigator.share({ title: @js($judulSurel), text: @js($pesanUmum), url: @js($link) }).catch(() => {})"
                                class="inline-flex min-h-10 items-center gap-2 rounded-xl border border-line-strong bg-white py-1 pr-3.5 pl-1.5 text-[13px] font-semibold text-ink hover:bg-gray-50 {{ $terbit ? '' : 'pointer-events-none opacity-50' }}">
                            <span class="flex size-7 items-center justify-center rounded-lg bg-gray-100 text-ink"><x-ikon nama="bagikan-lainnya" :ukuran="14" /></span>Lainnya
                        </button>
                    </div>
                    <div class="hint mt-2">Tombol ini membuka aplikasinya di tab baru dengan pesan dari template default (atur di tab Pesan). Nama tamu diganti sapaan umum.</div>
                </div>
            </div>

            <aside aria-label="Kode QR" class="kartu flex flex-col items-center gap-3 p-5">
                <div class="text-center">
                    <h2 class="h2">Kode QR</h2>
                    <div class="sub">Cetak di undangan fisik atau tampilkan di layar.</div>
                </div>
                @if ($qrUmum !== '')
                    <x-bagikan.qr :svg="$qrUmum" :berkas="'qr-'.$undangan->slug" />
                @else
                    <div role="alert" class="galat">Kode QR tidak bisa dibuat karena undangan ini belum punya alamat link.</div>
                @endif
            </aside>
        </section>

        {{-- Tab --}}
        <div role="group" aria-label="Bagian halaman" class="seg self-start">
            <button type="button" wire:click="ubahTab('tamu')" aria-pressed="{{ $tab === 'tamu' ? 'true' : 'false' }}" class="inline-flex items-center gap-1.5"><x-ikon nama="bagikan-tamu" :ukuran="15" /> Tamu</button>
            <button type="button" wire:click="ubahTab('pesan')" aria-pressed="{{ $tab === 'pesan' ? 'true' : 'false' }}" class="inline-flex items-center gap-1.5"><x-ikon nama="bagikan-pesan" :ukuran="15" /> Pesan</button>
        </div>

        @if ($tab === 'tamu')
            @if ($kandidat)
                <section aria-label="Kirim berikutnya" class="kartu flex flex-wrap items-center gap-3 border-brand-200 bg-brand-50/50 p-4">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-brand text-white"><x-ikon nama="bagikan-kirim" :ukuran="18" /></span>
                    <div class="min-w-0 flex-1 basis-48">
                        <div class="text-[11px] leading-[14px] font-bold tracking-[0.08em] text-muted uppercase">Kirim berikutnya{{ $terpilih ? ' (dari '.$terpilih.' tamu terpilih)' : '' }}</div>
                        <div class="truncate text-sm leading-5 font-semibold text-ink">{{ $kandidat->nama }}<span class="font-normal text-muted"> · {{ $kandidat->hp ? '+'.preg_replace('/^(\d{2})(\d{3})(\d{4})(\d*)$/', '$1 $2 $3 $4', $kandidat->hp) : 'belum ada nomor, pilih kontak sendiri' }}</span></div>
                        <div class="hint">{{ $sisaAntrean }} tamu belum dikirimi dalam antrean. Setelah WhatsApp terbuka, tamu ini ditandai dan antrean maju.</div>
                    </div>
                    <div class="flex shrink-0 gap-2">
                        <button type="button" wire:click="lewati({{ $kandidat->id }})" class="btn btn-secondary btn-sm"><x-ikon nama="bagikan-lewati" :ukuran="14" /> Lewati</button>
                        <a href="{{ $urlKandidat }}" target="_blank" rel="noopener noreferrer" wire:click="catatKirim({{ $kandidat->id }})" class="btn btn-primary btn-sm"><x-ikon nama="bagikan-wa" :ukuran="14" /> Buka WhatsApp</a>
                    </div>
                </section>
            @elseif ($terbit && $total > 0 && count($dilewati) > 0)
                <div role="status" class="kartu flex flex-wrap items-center justify-between gap-3 p-4 text-[13px] text-muted">
                    <span>Antrean selesai: semua tamu sudah dikirimi atau dilewati.</span>
                    <button type="button" wire:click="ulangiAntrean" class="btn btn-secondary btn-sm">Tampilkan yang dilewati lagi</button>
                </div>
            @endif

            <div class="grid grid-cols-[minmax(0,1fr)] items-start gap-5">
                <section aria-label="Daftar tamu" class="kartu min-w-0 overflow-hidden">
                    <div class="flex flex-col gap-3 border-b border-line p-4 sm:p-5">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <h2 class="h2">Daftar Tamu</h2>
                                <div class="sub">{{ $terfilter }}/{{ $total }} tamu · {{ $total ? $sudahKirim.' sudah dikirimi' : 'belum ada tamu' }}</div>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                @if ($tautanRsvp)<a href="{{ $tautanRsvp }}" wire:navigate class="btn btn-secondary btn-sm"><x-ikon nama="bagikan-tambah-tamu" :ukuran="14" /> Kelola tamu</a>@endif
                                @if ($tautanRiwayat)<a href="{{ $tautanRiwayat }}" wire:navigate class="btn btn-secondary btn-sm"><x-ikon nama="bagikan-riwayat" :ukuran="14" /> Riwayat kirim</a>@endif
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 rounded-xl bg-canvas px-3 py-2 text-[13px]">
                            <span class="text-muted"><x-ikon nama="bagikan-pesan" :ukuran="15" /></span>
                            <span class="min-w-0 flex-1">Template default: <b>{{ $labelDefault }}</b></span>
                            <button type="button" wire:click="ubahTab('pesan')" class="inline-flex min-h-6 items-center font-semibold text-brand">Ubah</button>
                        </div>
                        <div class="flex gap-2">
                            <div class="relative min-w-0 flex-1">
                                <label for="bg-cari" class="sr-only">Cari tamu</label>
                                <span class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-muted"><x-ikon nama="bagikan-cari" :ukuran="16" /></span>
                                <input id="bg-cari" type="search" class="inp pl-9" maxlength="80" placeholder="Cari nama, grup, atau nomor WhatsApp" wire:model.live.debounce.350ms="q" autocomplete="off">
                            </div>
                            <button type="button" wire:click="$toggle('filterBuka')" aria-expanded="{{ $filterBuka ? 'true' : 'false' }}" class="btn btn-secondary shrink-0 {{ $jumlahAktif ? 'border-brand text-brand' : '' }}">
                                <x-ikon nama="bagikan-saring" :ukuran="15" /> Filter @if ($jumlahAktif)<span class="rounded-full bg-brand px-1.5 text-[11px] text-white">{{ $jumlahAktif }}</span>@endif
                            </button>
                        </div>
                        @if ($filterBuka || $jumlahAktif)
                            <div class="flex flex-wrap items-end gap-4 rounded-xl border border-line p-3">
                                <div class="w-full sm:w-48">
                                    <label for="bg-fg" class="lbl">Grup</label>
                                    <select id="bg-fg" class="inp" wire:model.live="grup">
                                        <option value="">Semua grup</option>
                                        @foreach ($grupOpsi as $g)<option value="{{ $g }}">{{ $g }}</option>@endforeach
                                    </select>
                                </div>
                                <div>
                                    <div class="lbl">Status undangan</div>
                                    <div class="seg" role="group" aria-label="Saring status undangan">
                                        <button type="button" wire:click="aturStatus('semua')" aria-pressed="{{ $status === 'semua' ? 'true' : 'false' }}">Semua · {{ $total }}</button>
                                        <button type="button" wire:click="aturStatus('dibuka')" aria-pressed="{{ $status === 'dibuka' ? 'true' : 'false' }}">Sudah dibuka · {{ $jumlahDibuka }}</button>
                                        <button type="button" wire:click="aturStatus('belum')" aria-pressed="{{ $status === 'belum' ? 'true' : 'false' }}">Belum dibuka · {{ $total - $jumlahDibuka }}</button>
                                    </div>
                                </div>
                                @if ($jumlahAktif)<button type="button" wire:click="resetFilter" class="btn btn-ghost btn-sm">Reset filter</button>@endif
                            </div>
                        @endif
                    </div>

                    @if ($total === 0)
                        <x-kosong judul="Belum ada tamu" teks="Tambahkan tamu di halaman RSVP & Tamu. Setelah itu kamu bisa mengirim undangan lewat WhatsApp dengan nama masing-masing." ikon="bagikan-tamu">
                            @if ($tautanRsvp)<a href="{{ $tautanRsvp }}" wire:navigate class="btn btn-primary btn-sm">Tambah tamu</a>@endif
                        </x-kosong>
                    @elseif ($baris->isEmpty())
                        <x-kosong judul="Tamu tidak ditemukan" teks="Tidak ada tamu yang cocok dengan pencarian atau filter ini." ikon="bagikan-cari">
                            <button type="button" wire:click="resetFilter" class="btn btn-secondary btn-sm">Reset filter</button>
                        </x-kosong>
                    @else
                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line bg-gray-50 px-4 py-2 text-[13px] sm:px-5">
                            <label class="flex min-h-6 cursor-pointer items-center gap-2 font-medium"><input type="checkbox" wire:click="pilihSemuaTampil" @checked($baris->every(fn ($b) => in_array((string) $b['model']->id, array_map('strval', $sel), true))) class="size-4 rounded accent-brand">Pilih semua yang tampil</label>
                            <span class="flex items-center gap-3 text-muted">
                                {{ $terpilih }} dipilih
                                @if ($terpilih)
                                    <button type="button" wire:click="bukaTemplateTamu" class="btn btn-secondary btn-sm">Atur Template</button>
                                    <button type="button" wire:click="kosongkanPilihan" class="font-semibold text-brand">Kosongkan</button>
                                @endif
                            </span>
                        </div>
                        <ul class="m-0 list-none divide-y divide-line p-0">
                            @foreach ($baris as $b)
                                @php $t = $b['model']; @endphp
                                <li wire:key="tamu-{{ $t->id }}" class="flex flex-wrap items-center gap-x-3 gap-y-2 px-4 py-3 sm:px-5">
                                    <input type="checkbox" wire:model.live="sel" value="{{ $t->id }}" aria-label="Pilih {{ $t->nama }}" class="size-6 shrink-0 rounded accent-brand lg:size-4">
                                    <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-50 text-[13px] font-bold text-brand" aria-hidden="true">{{ mb_strtoupper(mb_substr(collect(preg_split('/\s+/u', trim($t->nama)))->take(2)->map(fn ($k) => mb_substr($k, 0, 1))->implode(''), 0, 2)) }}</span>
                                    <div class="min-w-0 flex-1 basis-40">
                                        <div class="truncate text-sm leading-5 font-semibold text-ink">{{ $t->nama }}</div>
                                        <div class="truncate text-xs leading-4 text-muted">{{ $t->grup ?: 'Tanpa grup' }} · {{ $t->hp ? '0'.substr($t->hp, 2) : 'Belum ada nomor' }}</div>
                                        @if ($t->dikirim_at)<div class="text-xs leading-4 font-medium text-[#067647]">Dibuka di WhatsApp · {{ $t->dikirim_at->locale('id')->diffForHumans() }}</div>@endif
                                    </div>
                                    <div class="flex flex-wrap gap-1.5">
                                        <x-pill :warna="$b['dibuka'] ? 'biru' : 'abu'">{{ $b['dibuka'] ? 'Sudah dibuka' : 'Belum dibuka' }}</x-pill>
                                        <x-pill :warna="$b['rsvp'] === 'hadir' ? 'hijau' : ($b['rsvp'] === 'tidak' ? 'merah' : 'abu')">{{ $b['rsvp'] === 'hadir' ? 'Hadir' : ($b['rsvp'] === 'tidak' ? 'Tidak hadir' : 'Belum RSVP') }}</x-pill>
                                    </div>
                                    <button type="button" wire:click="bukaTemplateTamu({{ $t->id }})" aria-label="Atur template pesan untuk {{ $t->nama }}" class="tag {{ $b['khusus'] ? '!bg-brand-50 !text-brand' : '' }} hover:opacity-80">Pesan: {{ $b['labelTemplate'] }}</button>
                                    <div class="flex flex-wrap items-center gap-1.5 sm:ml-auto"
                                         x-data="{ salin: false, async copy() { const t = @js($b['link']); try { await navigator.clipboard.writeText(t); } catch (e) { const a = document.createElement('textarea'); a.value = t; document.body.appendChild(a); a.select(); try { document.execCommand('copy'); } catch (e2) {} a.remove(); } this.salin = true; setTimeout(() => this.salin = false, 1800); } }">
                                        <button type="button" x-on:click="copy()" class="btn btn-secondary btn-sm" aria-label="Salin link untuk {{ $t->nama }}"><x-ikon nama="bagikan-salin" :ukuran="14" /> <span x-text="salin ? 'Tersalin' : 'Link'">Link</span></button>
                                        <button type="button" wire:click="bukaQr({{ $t->id }})" class="btn btn-secondary btn-sm" aria-label="Kode QR untuk {{ $t->nama }}"><x-ikon nama="bagikan-qr" :ukuran="14" /> QR</button>
                                        @if ($terbit)
                                            <a href="{{ $b['urlWa'] }}" target="_blank" rel="noopener noreferrer" wire:click="catatKirim({{ $t->id }})" aria-label="Kirim undangan ke {{ $t->nama }} lewat WhatsApp" class="btn btn-primary btn-sm">
                                                <x-ikon nama="bagikan-wa" :ukuran="14" /> {{ $t->dikirim_at ? 'Kirim ulang' : ($t->hp ? 'WA' : 'Pilih kontak') }}
                                            </a>
                                        @else
                                            <span class="tag">Terbitkan dulu</span>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                        <div class="flex flex-wrap items-center justify-between gap-2 border-t border-line px-4 py-3 text-[13px] text-muted sm:px-5">
                            <span>Menampilkan {{ $baris->count() }} dari {{ $terfilter }} tamu</span>
                            @if ($adaLagi)<button type="button" wire:click="lagi" class="btn btn-secondary btn-sm">Tampilkan lebih banyak</button>@endif
                        </div>
                    @endif
                </section>
            </div>
        @else
            <div class="grid grid-cols-[minmax(0,1fr)] items-start gap-5 lg:grid-cols-[minmax(0,1fr)_360px]">
                <section aria-label="Template pesan" class="kartu flex flex-col gap-4 p-5">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <h2 class="h2">Template Pesan</h2>
                            <div class="sub">Dipakai untuk semua tamu. Template tiap tamu bisa diatur sendiri di tab Tamu.</div>
                        </div>
                        @if ($tautanTemplate)<a href="{{ $tautanTemplate }}" wire:navigate class="btn btn-secondary btn-sm">Kelola template</a>@endif
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label for="bg-tpl" class="lbl">Template pesan</label>
                            <select id="bg-tpl" class="inp" wire:model.live="pTemplate">
                                @foreach ($daftarTemplate as $t)<option value="{{ $t['id'] }}">{{ $t['nama'] }}{{ $t['bawaan'] ? '' : ' (milikmu)' }}</option>@endforeach
                            </select>
                            @error('pTemplate')<div class="galat mt-1" role="alert">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label for="bg-salam" class="lbl">Salam pembuka &amp; penutup</label>
                            <select id="bg-salam" class="inp" wire:model.live="pSalam">
                                @foreach ($salam as $k => $s)<option value="{{ $k }}">{{ $s['nama'] }}</option>@endforeach
                            </select>
                            @error('pSalam')<div class="galat mt-1" role="alert">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="hint">{{ ($salam[$pSalam] ?? $salam['umum'])['buka'] === '' ? 'Tanpa salam pembuka dan penutup khusus.' : 'Pembuka: '.$salam[$pSalam]['buka'].' Penutup: '.$salam[$pSalam]['tutup'] }}</div>
                    <div>
                        <div class="lbl">Isi pesan</div>
                        <pre class="m-0 max-h-72 overflow-auto rounded-xl bg-canvas p-3.5 font-sans text-[13px] leading-5 whitespace-pre-wrap text-ink">{{ ($daftarTemplate[$pTemplate] ?? $daftarTemplate->first())['isi'] }}</pre>
                        <div class="hint mt-1.5">Variabel diisi otomatis: {{ implode(' ', \App\Services\PesanUndangan::VARIABEL) }}. Ubah isi di Kelola template.</div>
                    </div>
                    <div><button type="button" wire:click="simpanDefault" class="btn btn-primary"><x-ikon nama="bagikan-simpan" :ukuran="16" /> Simpan sebagai default</button></div>
                </section>

                <section aria-label="Pratinjau pesan" class="kartu flex flex-col gap-3 p-5">
                    <div>
                        <h2 class="h2">Pratinjau</h2>
                        <div class="sub">Contoh untuk {{ $namaPratinjau }}</div>
                    </div>
                    <div class="rounded-2xl rounded-bl-sm bg-[#e3f6d8] p-3.5 text-sm leading-[21px] break-words whitespace-pre-wrap text-ink">{{ $pratinjau }}</div>
                </section>
            </div>
        @endif

        {{-- Dialog template per tamu --}}
        <x-dialog wire:model="dialogTamu" :judul="$modalJumlah > 1 ? 'Template untuk '.$modalJumlah.' tamu' : 'Template untuk '.($modalNama ?? 'tamu')" sub='Pilih "Ikuti default" kalau sama dengan template default.' lebar="max-w-[460px]">
            <div>
                <label for="bg-mtpl" class="lbl">Template pesan</label>
                <select id="bg-mtpl" class="inp" wire:model.live="mTpl">
                    <option value="">Ikuti default</option>
                    @foreach ($daftarTemplate as $t)<option value="{{ $t['id'] }}">{{ $t['nama'] }}{{ $t['bawaan'] ? '' : ' (milikmu)' }}</option>@endforeach
                </select>
                @error('mTpl')<div class="galat mt-1" role="alert">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="bg-msalam" class="lbl">Salam (agama)</label>
                <select id="bg-msalam" class="inp" wire:model.live="mSalam">
                    <option value="">Ikuti default</option>
                    @foreach ($salam as $k => $s)<option value="{{ $k }}">{{ $s['nama'] }}</option>@endforeach
                </select>
                @error('mSalam')<div class="galat mt-1" role="alert">{{ $message }}</div>@enderror
                <div class="hint mt-1">Default saat ini: {{ $labelDefault }}</div>
            </div>
            @if ($modalPratinjau !== '')
                <div>
                    <div class="lbl">Pratinjau</div>
                    <div class="max-h-56 overflow-auto rounded-2xl rounded-bl-sm bg-[#e3f6d8] p-3 text-[13px] leading-5 break-words whitespace-pre-wrap text-ink">{{ $modalPratinjau }}</div>
                </div>
            @endif
            <x-slot:footer>
                <button type="button" class="btn btn-secondary" x-on:click="$wire.dialogTamu = false">Batal</button>
                <button type="button" class="btn btn-primary" wire:click="simpanTemplateTamu">Simpan</button>
            </x-slot:footer>
        </x-dialog>

        {{-- Dialog QR tamu --}}
        <x-dialog wire:model="dialogQr" :judul="'Kode QR '.($qrTamu['nama'] ?? 'tamu')" sub="QR berisi link pribadi tamu. Dipakai juga sebagai QR kehadiran di undangan." lebar="max-w-[400px]">
            @if ($qrTamu)
                <x-bagikan.qr :svg="$qrTamu['svg']" :berkas="$qrTamu['berkas']" />
                <div class="break-all text-center text-xs text-muted">{{ $qrTamu['link'] }}</div>
            @endif
        </x-dialog>
    @endif
</div>
