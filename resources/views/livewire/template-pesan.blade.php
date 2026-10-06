<div wire:init="muat" class="mx-auto flex max-w-6xl flex-col gap-5">
    @unless ($siap)
        <div role="status" aria-busy="true" class="flex flex-col gap-5">
            <span class="sr-only">Memuat halaman…</span>
            <div class="kartu flex flex-col gap-3 p-5"><span class="skel h-4 w-32"></span><span class="skel h-11 w-full max-w-md"></span></div>
            <div class="grid gap-5 grid-cols-[minmax(0,1fr)] lg:grid-cols-[minmax(0,1fr)_360px]">
                <x-skeleton mode="kartu" :jumlah="4" />
                <div class="kartu flex flex-col gap-3 p-5"><span class="skel h-4 w-32"></span><span class="skel h-40 w-full"></span></div>
            </div>
        </div>
    @else
        @if ($contoh)
            <div role="note" class="flex flex-wrap items-center gap-3 rounded-2xl border border-[#b2ccff] bg-brand-50 px-4 py-3 text-[13px] leading-5 text-[#1a3fa8]">
                <x-ikon nama="bagikan-info" :ukuran="16" />
                <span class="min-w-0 flex-1"><b>Data contoh.</b> Kamu belum punya undangan, jadi pratinjau memakai data contoh. Template tetap bisa dibuat sekarang.</span>
                @if ($tautanBuat)<a href="{{ $tautanBuat }}" wire:navigate class="btn btn-secondary btn-sm shrink-0">Buat undangan</a>@endif
            </div>
        @else
            <section aria-label="Pilih undangan" class="kartu flex flex-wrap items-end justify-between gap-4 p-4 sm:p-5">
                <div class="flex w-full max-w-md flex-col gap-1.5">
                    <label for="tp-inv" class="label">Undangan untuk pratinjau</label>
                    <select id="tp-inv" class="inp" wire:change="pilih($event.target.value)">
                        @foreach ($daftarUndangan as $u)
                            <option value="{{ $u->id }}" @selected($u->id === $undangan->id)>{{ $u->judul }} ({{ $u->sudahTerbit() ? 'Terbit' : 'Draf' }})</option>
                        @endforeach
                    </select>
                </div>
                <span class="sub">Data pratinjau (nama, tanggal, tempat, link) diambil dari undangan ini.</span>
            </section>
        @endif

        <div class="grid grid-cols-[minmax(0,1fr)] items-start gap-5 lg:grid-cols-[minmax(0,1fr)_360px]">
            <div class="flex min-w-0 flex-col gap-4">
                <div class="kartu flex flex-col gap-3 p-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="relative min-w-0 flex-1 basis-56">
                            <label for="tp-cari" class="sr-only">Cari template</label>
                            <span class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-muted"><x-ikon nama="bagikan-cari" :ukuran="16" /></span>
                            <input id="tp-cari" type="search" class="inp pl-9" maxlength="80" placeholder="Cari nama atau isi template..." wire:model.live.debounce.300ms="q" autocomplete="off">
                        </div>
                        <div class="seg" role="group" aria-label="Filter kanal">
                            @foreach (['semua' => 'Semua', 'whatsapp' => 'WhatsApp', 'email' => 'Email'] as $k => $label)
                                <button type="button" wire:click="$set('kanal', '{{ $k }}')" aria-pressed="{{ $kanal === $k ? 'true' : 'false' }}">{{ $label }} <span class="text-muted">{{ $hitung[$k] }}</span></button>
                            @endforeach
                        </div>
                        <button type="button" wire:click="bukaBaru" class="btn btn-primary"><x-ikon nama="tambah" :ukuran="16" /> Buat template baru</button>
                    </div>
                    <div class="hint">Template default dipakai di halaman Bagikan Undangan. Isi pesan memakai variabel {{ implode(', ', \App\Services\PesanUndangan::VARIABEL) }} (salam mengikuti pilihan di halaman Bagikan Undangan).</div>
                </div>

                @php
                    $blokKartu = [['Template buatanmu', $milik, $jumlahMilik, true], ['Template bawaan', $bawaan, $jumlahBawaan, false]];
                @endphp
                @foreach ($blokKartu as [$judulBlok, $daftarBlok, $jumlahBlok, $punyaku])
                    <div class="px-1 text-[12px] leading-4 font-bold tracking-[0.08em] text-muted uppercase">{{ $judulBlok }} ({{ $jumlahBlok }})</div>
                    @if ($punyaku && $jumlahMilik === 0)
                        <div class="kartu">
                            <x-kosong judul="Belum ada template buatanmu" teks="Buat template sendiri, misalnya untuk keluarga besar atau teman kantor. Template bawaan di bawah bisa diduplikat lalu diubah." ikon="bagikan-pesan">
                                <button type="button" wire:click="bukaBaru" class="btn btn-primary btn-sm">Buat template baru</button>
                            </x-kosong>
                        </div>
                    @endif
                    @foreach ($daftarBlok as $t)
                        <article wire:key="tpl-{{ $t['id'] }}" class="kartu flex flex-col gap-2.5 p-4 {{ $terpilih['id'] === $t['id'] ? 'border-2 !border-brand' : '' }}">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-sm leading-5 font-semibold text-ink">{{ $t['nama'] }}</span>
                                <x-pill :warna="$t['kanal'] === 'email' ? 'abu' : 'hijau'">{{ $t['kanal'] === 'email' ? 'Email' : 'WhatsApp' }}</x-pill>
                                @if ($t['default'])<span class="tag !bg-brand-50 !text-brand"><x-ikon nama="bagikan-bintang" :ukuran="11" class="mr-1" />Default</span>@endif
                                @if ($terpilih['id'] === $t['id'])<span class="tag">Dipratinjau</span>@endif
                            </div>
                            <div class="line-clamp-2 text-[13px] leading-5 break-words text-muted">{{ $t['ringkas'] }}</div>
                            <div class="hint">{{ $t['panjang'] }} karakter</div>
                            @if ($hapusId === $t['id'])
                                <div role="alert" class="flex flex-wrap items-center gap-2 rounded-xl bg-[#fef3f2] px-3 py-2 text-[13px] font-medium text-[#b42318]">
                                    <span class="flex-1">Hapus template ini? Tamu yang memakainya kembali mengikuti default.</span>
                                    <button type="button" wire:click="batalHapus" class="btn btn-secondary btn-sm">Batal</button>
                                    <button type="button" wire:click="hapus" class="btn btn-danger-solid btn-sm">Hapus</button>
                                </div>
                            @else
                                <div class="flex flex-wrap items-center gap-2">
                                    <button type="button" wire:click="pratinjau('{{ $t['id'] }}')" class="btn btn-secondary btn-sm"><x-ikon nama="bagikan-lihat" :ukuran="14" /> Pratinjau</button>
                                    @unless ($t['bawaan'])<button type="button" wire:click="bukaEdit('{{ $t['id'] }}')" class="btn btn-secondary btn-sm"><x-ikon nama="bagikan-edit" :ukuran="14" /> Edit</button>@endunless
                                    @if ($t['bawaan'])<button type="button" wire:click="duplikat('{{ $t['id'] }}')" class="btn btn-secondary btn-sm"><x-ikon nama="bagikan-duplikat" :ukuran="14" /> Duplikat &amp; ubah</button>@endif
                                    @if ($t['kanal'] === 'whatsapp' && ! $t['default'])<button type="button" wire:click="jadikanDefault('{{ $t['id'] }}')" class="btn btn-ghost btn-sm">Jadikan default</button>@endif
                                    @unless ($t['bawaan'])<button type="button" wire:click="mintaHapus('{{ $t['id'] }}')" class="btn-icon ml-auto" aria-label="Hapus {{ $t['nama'] }}"><x-ikon nama="bagikan-hapus" :ukuran="16" /></button>@endunless
                                </div>
                            @endif
                        </article>
                    @endforeach
                @endforeach

                @if ($milik->isEmpty() && $bawaan->isEmpty())
                    <div class="kartu" role="status">
                        <x-kosong judul="Tidak ada template yang cocok" teks="Coba kata kunci lain atau ubah filter kanal." ikon="bagikan-cari">
                            <button type="button" wire:click="resetFilter" class="btn btn-secondary btn-sm">Reset filter</button>
                        </x-kosong>
                    </div>
                @endif
            </div>

            <aside aria-label="Pratinjau pesan" class="kartu flex flex-col gap-3 p-5 lg:sticky lg:top-4"
                   x-data="{ salin: false, async copy() { const t = @js(($email && $subjek !== '' ? 'Subjek: '.$subjek."\n\n" : '').$teks); try { await navigator.clipboard.writeText(t); } catch (e) { const a = document.createElement('textarea'); a.value = t; document.body.appendChild(a); a.select(); try { document.execCommand('copy'); } catch (e2) {} a.remove(); } this.salin = true; setTimeout(() => this.salin = false, 1800); } }">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="h2">Pratinjau</h2>
                    <x-pill :warna="$email ? 'abu' : 'hijau'">{{ $email ? 'Email' : 'WhatsApp' }}</x-pill>
                </div>
                <div class="sub"><b>{{ $terpilih['nama'] }}</b>: data dari {{ $contoh ? 'undangan contoh' : 'undangan '.$undangan->judul }}.</div>
                <div>
                    <label for="pv-nama" class="lbl">Nama tamu contoh</label>
                    <input id="pv-nama" type="text" class="inp" maxlength="60" wire:model.live.debounce.400ms="pvNama" autocomplete="off">
                </div>
                @if ($email)
                    <div class="rounded-xl bg-canvas px-3 py-2">
                        <div class="text-[11px] leading-[14px] font-bold tracking-[0.08em] text-muted uppercase">Subjek</div>
                        <div class="text-[13px] leading-5 break-words text-ink">{{ $subjek }}</div>
                    </div>
                @endif
                <div class="max-h-[360px] overflow-auto text-sm leading-[21px] break-words whitespace-pre-wrap text-ink {{ $email ? 'rounded-2xl bg-canvas p-4' : 'rounded-2xl rounded-bl-sm bg-[#e3f6d8] p-3.5' }}">{{ $teks }}</div>
                @php $panjang = mb_strlen($teks); @endphp
                <div class="hint {{ ! $email && $panjang > \App\Services\PesanUndangan::SARAN_WA ? '!text-[#b42318]' : '' }}">{{ $panjang }} karakter</div>
                @if (! $email && $panjang > \App\Services\PesanUndangan::SARAN_WA)
                    <div role="alert" class="galat">Pesan ini lebih dari 1.000 karakter. Persingkat agar tautan WhatsApp tetap aman dibuka.</div>
                @endif
                <div class="flex flex-wrap gap-2">
                    <button type="button" x-on:click="copy()" class="btn btn-secondary btn-sm"><x-ikon nama="bagikan-salin" :ukuran="14" /> <span x-text="salin ? 'Tersalin' : 'Salin pesan'">Salin pesan</span></button>
                    @if ($email)
                        <a href="{{ $urlSurel }}" class="btn btn-secondary btn-sm"><x-ikon nama="bagikan-email" :ukuran="14" /> Buka di Email</a>
                    @else
                        <a href="{{ $urlWa }}" target="_blank" rel="noopener noreferrer" class="btn btn-primary btn-sm"><x-ikon nama="bagikan-wa" :ukuran="14" /> Buka di WhatsApp</a>
                    @endif
                </div>
                @if ($tautanBagikan)<a href="{{ $tautanBagikan }}" wire:navigate class="inline-flex min-h-6 items-center text-[13px] font-semibold text-brand">Kirim ke tamu di Bagikan Undangan</a>@endif
            </aside>
        </div>

        <x-dialog wire:model="dialogForm" :judul="$mId ? 'Edit template' : 'Template baru'" lebar="max-w-[620px]">
            <div>
                <label for="tpl-nama" class="lbl">Nama template</label>
                <input id="tpl-nama" type="text" class="inp" maxlength="{{ \App\Services\PesanUndangan::MAKS_NAMA }}" placeholder="Contoh: Undangan Keluarga" wire:model="mNama" @error('mNama') aria-invalid="true" @enderror>
                @error('mNama')<div class="galat mt-1" role="alert">{{ $message }}</div>@enderror
            </div>
            <div>
                <div class="lbl">Kanal</div>
                <div class="seg" role="group" aria-label="Kanal pesan">
                    <button type="button" wire:click="setKanal('whatsapp')" aria-pressed="{{ $mKanal === 'whatsapp' ? 'true' : 'false' }}">WhatsApp</button>
                    <button type="button" wire:click="setKanal('email')" aria-pressed="{{ $mKanal === 'email' ? 'true' : 'false' }}">Email</button>
                </div>
            </div>
            @if ($mKanal === 'email')
                <div>
                    <label for="tpl-subj" class="lbl">Subjek email</label>
                    <input id="tpl-subj" type="text" class="inp" maxlength="{{ \App\Services\PesanUndangan::MAKS_SUBJEK }}" placeholder="Undangan: {judul}" wire:model.live.debounce.400ms="mSubjek" @error('mSubjek') aria-invalid="true" @enderror>
                    @error('mSubjek')<div class="galat mt-1" role="alert">{{ $message }}</div>@enderror
                </div>
            @endif
            <div x-data="{
                    sisip(teks) {
                        const el = this.$refs.isi; const a = el.selectionStart ?? el.value.length; const b = el.selectionEnd ?? a;
                        el.value = el.value.slice(0, a) + teks + el.value.slice(b);
                        el.dispatchEvent(new Event('input', { bubbles: true }));
                        this.$nextTick(() => { el.focus(); el.setSelectionRange(a + teks.length, a + teks.length); });
                    },
                 }">
                <div class="mb-1.5 flex flex-wrap items-center justify-between gap-2">
                    <label for="tpl-isi" class="label">Isi pesan</label>
                    <select aria-label="Sisipkan salam" class="inp !min-h-8 !w-auto !py-0 text-[13px]" x-on:change="if ($event.target.value) { $wire.sisipSalam($event.target.value); $event.target.value = ''; }">
                        <option value="">Sisipkan salam...</option>
                        @foreach ($salam as $k => $s)@if ($s['buka'] !== '')<option value="{{ $k }}">{{ $s['nama'] }}</option>@endif @endforeach
                    </select>
                </div>
                <textarea id="tpl-isi" x-ref="isi" rows="9" class="inp" wire:model.live.debounce.400ms="mIsi" @error('mIsi') aria-invalid="true" @enderror></textarea>
                <div class="mt-2 flex flex-wrap items-center gap-1.5">
                    <span class="hint">Sisipkan:</span>
                    @foreach (\App\Services\PesanUndangan::VARIABEL as $v)
                        <button type="button" x-on:click="sisip('{{ $v }}')" class="tag hover:bg-gray-200" aria-label="Sisipkan {{ $v }}">{{ $v }}</button>
                    @endforeach
                </div>
                <div class="mt-1.5 flex flex-wrap justify-between gap-2">
                    <span class="hint {{ mb_strlen($mIsi) > \App\Services\PesanUndangan::MAKS_ISI ? '!text-[#b42318]' : '' }}">{{ mb_strlen($mIsi) }} / {{ \App\Services\PesanUndangan::MAKS_ISI }} karakter</span>
                </div>
                @error('mIsi')<div class="galat mt-1" role="alert">{{ $message }}</div>@enderror
                @if (trim($mIsi) !== '' && ! str_contains($mIsi, '{link}'))
                    <div class="hint mt-1 !text-[#b54708]">Tambahkan {link} agar tamu bisa membuka undangan dari pesan ini.</div>
                @endif
                @if ($mKanal === 'whatsapp' && mb_strlen($mIsi) > \App\Services\PesanUndangan::SARAN_WA && mb_strlen($mIsi) <= \App\Services\PesanUndangan::MAKS_ISI)
                    <div class="hint mt-1 !text-[#b54708]">Pesan WhatsApp sebaiknya tidak lebih dari 1.000 karakter agar tautan tetap aman dibuka.</div>
                @endif
                @if ($asingForm)
                    <div class="hint mt-1 !text-[#b54708]">Variabel tidak dikenal akan tampil apa adanya: {{ implode(', ', $asingForm) }}.</div>
                @endif
            </div>
            <div>
                <div class="lbl">Pratinjau dengan data undangan terpilih</div>
                <div class="max-h-48 overflow-auto rounded-2xl rounded-bl-sm bg-[#e3f6d8] p-3 text-[13px] leading-5 break-words whitespace-pre-wrap text-ink">{{ $cuplikanForm !== '' ? $cuplikanForm : 'Isi pesan dulu untuk melihat pratinjau.' }}</div>
            </div>
            <x-slot:footer>
                <button type="button" class="btn btn-secondary" x-on:click="$wire.dialogForm = false">Batal</button>
                <button type="button" class="btn btn-primary" wire:click="simpan" wire:loading.attr="disabled" wire:target="simpan">Simpan template</button>
            </x-slot:footer>
        </x-dialog>
    @endif
</div>
