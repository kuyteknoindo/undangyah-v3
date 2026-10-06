@php
    $label = 'text-[11px] leading-[14px] font-bold tracking-[0.08em] text-muted uppercase';
    $ponsel = $perangkat !== 'tablet';
    $status = [
        'terbit' => ['hijau', 'Terbit', $versiAktif === 'draf' ? 'Menampilkan draf terakhir dari editor.' : 'Tampilan yang dilihat tamu.'],
        'draf' => ['kuning', 'Draf', 'Dari draf editor, belum diterbitkan.'],
        'kosong' => ['kuning', 'Draf kosong', 'Belum ada isi yang tersimpan.'],
        'contoh' => ['biru', 'Contoh', 'Undangan contoh dari tim undangyah.'],
    ][$mode] ?? null;
    $catatan = match (true) {
        $mode === 'terbit' && $versiAktif === 'terbit' => 'Pratinjau tidak mencatat kunjungan, RSVP, maupun ucapan.',
        $mode === 'draf' || ($mode === 'terbit' && $versiAktif === 'draf') => 'RSVP dan ucapan tidak dicatat di pratinjau draf.',
        default => '',
    };
    $adaBingkai = $bingkai !== null && in_array($mode, ['terbit', 'draf', 'contoh'], true);
@endphp
<div class="mx-auto flex max-w-6xl flex-col gap-5">

    @if ($mode === 'tidak-ada')
        <section aria-labelledby="pv-kosong" class="kartu p-6 sm:p-10">
            <div class="mx-auto flex max-w-xl flex-col items-center text-center">
                <span class="flex size-14 items-center justify-center rounded-full bg-brand-50 text-brand"><x-ikon nama="mata" :ukuran="26" /></span>
                <h2 id="pv-kosong" class="mt-3 text-lg leading-7 font-semibold text-ink">Belum ada undangan</h2>
                <p class="sub mt-1 max-w-md">Belum ada undangan untuk dipratinjau. Buat undangan, lalu lihat tampilannya seperti tamu.</p>
                <div class="mt-4 flex flex-wrap justify-center gap-2.5">
                    <a href="{{ $tautanBuat }}" wire:navigate class="btn btn-primary"><x-ikon nama="tambah" :ukuran="16" /> Buat undangan</a>
                    <button type="button" wire:click="tampilkanContoh" class="btn btn-secondary"><x-ikon nama="mata" :ukuran="16" /> Lihat contoh</button>
                </div>
                <ol class="mt-6 grid w-full gap-3 text-left sm:grid-cols-3">
                    @foreach ([['Pilih tema', 'Dari koleksi template'], ['Isi data acara', 'Nama, tanggal, lokasi'], ['Bagikan ke tamu', 'Link pribadi & WhatsApp']] as $i => [$j, $s])
                        <li class="flex items-start gap-2.5 rounded-xl border border-line p-3">
                            <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-bold text-brand">{{ $i + 1 }}</span>
                            <span class="flex flex-col"><span class="text-[13px] leading-[18px] font-semibold text-ink">{{ $j }}</span><span class="hint">{{ $s }}</span></span>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>
    @else
        @if ($mode === 'contoh')
            <div role="note" class="flex flex-wrap items-center gap-x-3 gap-y-2 rounded-xl border border-brand-200 bg-brand-50 px-4 py-3 text-[13px] leading-5 text-brand">
                <x-ikon nama="pratinjau-info" :ukuran="16" />
                <span class="min-w-0 flex-1"><b>Mode contoh.</b> Kamu sedang melihat undangan contoh. Data ini hanya ilustrasi dan tidak tersimpan.</span>
                <a href="{{ $tautanBuat }}" wire:navigate class="font-semibold underline">Buat undangan</a>
                <button type="button" wire:click="sembunyikanContoh" class="font-semibold underline">Sembunyikan contoh</button>
            </div>
        @endif

        <div class="grid items-start gap-5 lg:grid-cols-[340px_minmax(0,1fr)]">
            {{-- Pengaturan --}}
            <section aria-label="Pengaturan pratinjau" class="kartu flex flex-col gap-5 p-5">
                @if ($u)
                    <div>
                        <label for="pk-inv" class="lbl">Undangan</label>
                        <select id="pk-inv" class="inp" wire:change="pilihUndangan($event.target.value)">
                            @foreach ($daftar as $d)
                                <option value="{{ $d->id }}" @selected($d->id === $u->id)>{{ $d->judul }}{{ $d->status === 'terbit' ? '' : ' (draf)' }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div>
                    <div id="pv-dev-l" class="{{ $label }}">Perangkat</div>
                    <div class="seg mt-2" role="group" aria-labelledby="pv-dev-l">
                        <button type="button" wire:click="pilihPerangkat('ponsel')" aria-pressed="{{ $ponsel ? 'true' : 'false' }}" class="inline-flex items-center gap-1.5"><x-ikon nama="pratinjau-ponsel" :ukuran="15" />Ponsel</button>
                        <button type="button" wire:click="pilihPerangkat('tablet')" aria-pressed="{{ $ponsel ? 'false' : 'true' }}" class="inline-flex items-center gap-1.5"><x-ikon nama="pratinjau-tablet" :ukuran="15" />Tablet</button>
                    </div>
                </div>

                @if ($mode === 'terbit' && $adaDraf)
                    <div>
                        <div id="pv-ver-l" class="{{ $label }}">Versi</div>
                        <div class="seg mt-2" role="group" aria-labelledby="pv-ver-l">
                            <button type="button" wire:click="pilihVersi('terbit')" aria-pressed="{{ $versiAktif === 'terbit' ? 'true' : 'false' }}">Terbit</button>
                            <button type="button" wire:click="pilihVersi('draf')" aria-pressed="{{ $versiAktif === 'draf' ? 'true' : 'false' }}">Draf terbaru</button>
                        </div>
                    </div>
                @endif

                @if ($status)
                    <div class="flex flex-col gap-3 border-t border-line pt-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <x-pill :warna="$status[0]" data-status>{{ $status[1] }}</x-pill>
                            <span class="sub">{{ $status[2] }}</span>
                        </div>

                        @if ($tautanHost)
                            <div class="rounded-lg bg-gray-50 px-3 py-2 text-[13px] leading-5 break-all text-body" data-tautan>{{ $tautanHost }}{{ $mode === 'terbit' ? '' : ' (belum terbit)' }}</div>
                        @endif

                        <div class="flex flex-wrap gap-2">
                            @if ($adaBingkai)
                                <a href="{{ $bingkai }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm"><x-ikon nama="pratinjau-buka" :ukuran="14" /> Buka di tab baru</a>
                            @else
                                <button type="button" disabled class="btn btn-secondary btn-sm"><x-ikon nama="pratinjau-buka" :ukuran="14" /> Buka di tab baru</button>
                            @endif

                            @if ($mode === 'terbit' && $tautanTamu)
                                <button type="button" x-data="{ ok: false, async salin() {
                                        const t = @js($tautanTamu);
                                        try { await navigator.clipboard.writeText(t); } catch (e) {
                                            const a = document.createElement('textarea'); a.value = t; a.setAttribute('readonly', ''); a.style.position = 'fixed'; a.style.opacity = '0';
                                            document.body.appendChild(a); a.select(); try { document.execCommand('copy'); } catch (e2) {} a.remove();
                                        }
                                        this.ok = true; setTimeout(() => this.ok = false, 1800);
                                    } }" x-on:click="salin()" class="btn btn-secondary btn-sm" aria-live="polite">
                                    <x-ikon nama="pratinjau-salin" :ukuran="14" /> <span x-text="ok ? 'Tersalin' : 'Salin link'">Salin link</span>
                                </button>
                            @elseif ($mode !== 'contoh')
                                <button type="button" disabled class="btn btn-secondary btn-sm"><x-ikon nama="pratinjau-salin" :ukuran="14" /> Salin link</button>
                            @endif

                            @if ($u)
                                <a href="{{ $tautanEdit }}" wire:navigate class="btn btn-secondary btn-sm"><x-ikon nama="edit" :ukuran="14" /> Edit undangan</a>
                                <a href="{{ $tautanBagikan }}" wire:navigate class="btn btn-secondary btn-sm"><x-ikon nama="bagikan" :ukuran="14" /> Bagikan</a>
                            @endif
                        </div>

                        @if ($catatan !== '')
                            <div role="note" class="flex items-start gap-2 rounded-lg bg-[#fffaeb] px-3 py-2 text-xs leading-[17px] text-[#b54708]">
                                <x-ikon nama="pratinjau-info" :ukuran="14" class="mt-0.5" /><span>{{ $catatan }}</span>
                            </div>
                        @endif
                    </div>
                @endif
            </section>

            {{-- Pratinjau --}}
            <section aria-label="Pratinjau" class="flex min-w-0 flex-col items-center gap-3">
                @if ($adaBingkai)
                    <div class="max-w-full" style="{{ $ponsel
                        ? 'width:412px;box-sizing:border-box;padding:10px;border-radius:48px;background:#262A31;box-shadow:0 8px 0 #1A1D22,0 24px 40px rgba(0,0,0,0.18);'
                        : 'width:720px;box-sizing:border-box;padding:14px;border-radius:32px;background:#262A31;box-shadow:0 8px 0 #1A1D22,0 24px 40px rgba(0,0,0,0.18);' }}">
                        <iframe wire:key="bingkai-{{ $u?->id ?? 'contoh' }}-{{ $versiAktif }}-{{ $rev }}" src="{{ $bingkai }}" title="Pratinjau undangan {{ $u?->judul ?? 'contoh' }}"
                            sandbox="allow-popups allow-popups-to-escape-sandbox" loading="lazy"
                            style="{{ $ponsel
                                ? 'display:block;width:100%;height:780px;border:0;border-radius:38px;background:#FFFFFF;'
                                : 'display:block;width:100%;height:760px;border:0;border-radius:20px;background:#FFFFFF;' }}"></iframe>
                    </div>
                    <button type="button" wire:click="muatUlang" class="btn btn-secondary btn-sm"><x-ikon nama="pratinjau-muat" :ukuran="14" /> Muat ulang pratinjau</button>
                @else
                    <div role="status" class="kartu w-full">
                        <x-kosong judul="Isi undangan belum tersimpan" teks="Undangan ini baru dibuat dan belum punya isi. Buka editor, lengkapi mempelai dan acara, lalu kembali ke sini untuk melihat pratinjaunya." ikon="mata">
                            <a href="{{ $tautanEdit }}" wire:navigate class="btn btn-primary btn-sm"><x-ikon nama="edit" :ukuran="14" /> Buka editor</a>
                            <a href="{{ $tautanBuat }}" wire:navigate class="btn btn-secondary btn-sm"><x-ikon nama="tambah" :ukuran="14" /> Buat undangan</a>
                        </x-kosong>
                    </div>
                @endif
            </section>
        </div>
    @endif
</div>
