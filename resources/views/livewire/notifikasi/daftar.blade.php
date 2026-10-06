<div class="mx-auto flex max-w-6xl flex-col gap-6 lg:flex-row lg:items-start">
    <section class="kartu min-w-0 flex-1 overflow-hidden" aria-label="Daftar notifikasi">
        <div role="group" aria-label="Filter notifikasi" class="flex flex-wrap gap-2 border-b border-line px-4 py-4 sm:px-5">
            @foreach ($filters as [$kunci, $label, $jumlah])
                @php $on = $aktif === $kunci; @endphp
                <button type="button" wire:click="pilihFilter('{{ $kunci }}')" aria-pressed="{{ $on ? 'true' : 'false' }}"
                        class="inline-flex min-h-10 items-center gap-2 rounded-full border-2 py-0 pr-1.5 pl-3.5 text-[13px] leading-5 font-semibold transition {{ $on ? 'border-brand bg-brand-100 text-brand' : 'border-line bg-white text-nav hover:bg-gray-50' }}">
                    {{ $label }}
                    <span class="inline-flex h-6 min-w-6 items-center justify-center rounded-full px-1.5 text-xs tabular-nums {{ $on ? 'bg-brand text-white' : 'bg-gray-100 text-nav' }}">{{ $jumlah }}</span>
                </button>
            @endforeach
        </div>

        {{-- Kerangka saat filter berganti --}}
        <div wire:loading.block wire:target="pilihFilter" class="hidden" role="status" aria-busy="true">
            <span class="sr-only">Memuat notifikasi…</span>
            @for ($i = 0; $i < 4; $i++)
                <div class="flex items-start gap-3.5 border-b border-line px-4 py-4 sm:px-5">
                    <span class="skel size-11 shrink-0 rounded-full"></span>
                    <div class="flex flex-1 flex-col gap-2.5 pt-1">
                        <span class="skel h-3.5 w-1/3"></span>
                        <span class="skel h-3 w-4/5"></span>
                        <span class="skel h-3 w-1/4"></span>
                    </div>
                </div>
            @endfor
        </div>

        <div wire:loading.remove wire:target="pilihFilter">
            @foreach ($grup as $judulGrup => $baris)
                <div class="px-4 pt-4 pb-1.5 text-[11px] leading-[14px] font-bold tracking-[0.08em] text-muted uppercase sm:px-5">{{ $judulGrup }}</div>
                @foreach ($baris as $n)
                    @php
                        [$kind, $bg, $fg, $cta, $ikon] = $n->jenis();
                        $dibaca = $n->sudahDibaca();
                        $tautan = \App\Models\Notifikasi::tautanAman($n->tautan);
                    @endphp
                    <article wire:key="n-{{ $n->id }}" class="flex flex-wrap items-start gap-x-3.5 gap-y-1 border-b border-line px-4 py-4 sm:flex-nowrap sm:px-5 {{ $dibaca ? 'bg-white' : 'bg-[#F5F8FF]' }}">
                        <span aria-hidden="true" class="flex size-11 shrink-0 items-center justify-center rounded-full" style="background:{{ $bg }};color:{{ $fg }}"><x-ikon :nama="$ikon" :ukuran="22" /></span>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="text-sm leading-[19px] font-bold text-ink [overflow-wrap:anywhere]">{{ $n->judul }}</span>
                                @unless ($dibaca)
                                    <span role="img" aria-label="Belum dibaca" class="size-2.5 shrink-0 rounded-full bg-brand"></span>
                                @endunless
                            </div>
                            <div class="mt-0.5 text-[13px] leading-[19px] font-medium text-nav [overflow-wrap:anywhere]">{{ $n->isi }}</div>
                            <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1.5">
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs leading-4 font-bold" style="background:{{ $bg }};color:{{ $fg }}">{{ $kind }}</span>
                                <span class="text-xs leading-4 font-semibold text-muted">{{ \App\Livewire\Notifikasi\Daftar::waktu($n->created_at) }}</span>
                                @if ($tautan)
                                    <a href="{{ $tautan }}" wire:click.prevent="buka({{ $n->id }})" class="inline-flex min-h-8 items-center text-xs leading-4 font-bold text-brand hover:underline">{{ $cta }}</a>
                                @endif
                            </div>
                        </div>
                        <div class="-mt-1 flex w-full shrink-0 justify-end gap-1 sm:mt-0 sm:w-auto">
                            @if ($dibaca)
                                <button type="button" wire:click="tandaiBelumDibaca({{ $n->id }})" aria-label="Tandai belum dibaca" title="Tandai belum dibaca" class="flex size-11 items-center justify-center rounded-full text-muted hover:bg-gray-100"><x-ikon nama="notif-ulang" :ukuran="18" /></button>
                            @else
                                <button type="button" wire:click="tandaiDibaca({{ $n->id }})" aria-label="Tandai dibaca" title="Tandai dibaca" class="flex size-11 items-center justify-center rounded-full text-muted hover:bg-gray-100"><x-ikon nama="centang" :ukuran="20" /></button>
                            @endif
                            <button type="button" wire:click="hapus({{ $n->id }})" aria-label="Hapus notifikasi" title="Hapus" class="flex size-11 items-center justify-center rounded-full text-muted hover:bg-gray-100"><x-ikon nama="notif-hapus" :ukuran="18" /></button>
                        </div>
                    </article>
                @endforeach
            @endforeach

            @if ($kosongSemua)
                <x-kosong judul="Belum ada notifikasi" teks="RSVP, ucapan tamu, dan kabar soal paket serta pengiriman akan muncul di sini." ikon="lonceng" class="py-12">
                    <a href="{{ route('beranda') }}" wire:navigate class="btn btn-secondary">Ke Beranda</a>
                </x-kosong>
            @elseif ($kosongFilter)
                <x-kosong judul="Tidak ada notifikasi untuk filter ini" teks="Pilih filter lain atau tampilkan semuanya." ikon="lonceng">
                    <button type="button" wire:click="pilihFilter('semua')" class="btn btn-secondary">Tampilkan semua</button>
                </x-kosong>
            @endif

            @if ($sisa > 0)
                <div class="px-5 pt-3.5 pb-[18px] text-center">
                    <button type="button" wire:click="muatLagi" wire:loading.attr="disabled" wire:target="muatLagi" class="btn btn-secondary min-h-11">Muat lebih banyak ({{ $sisa }})</button>
                </div>
            @endif
        </div>
    </section>

    <aside aria-label="Ringkasan" class="flex min-w-0 flex-col gap-4 lg:w-[300px] lg:shrink-0">
        <div class="kartu flex flex-col gap-3.5 p-5">
            <div class="text-[11px] leading-[14px] font-bold tracking-[0.08em] text-muted uppercase">Ringkasan</div>
            <div class="flex gap-2.5">
                <div class="flex-1 rounded-2xl bg-brand-100 p-3 text-center">
                    <div class="text-2xl leading-7 font-bold text-brand tabular-nums" data-ringkasan="belum">{{ $jumlahBelum }}</div>
                    <div class="text-[11px] leading-[14px] font-bold text-brand-dark">Belum dibaca</div>
                </div>
                <div class="flex-1 rounded-2xl bg-canvas p-3 text-center">
                    <div class="text-2xl leading-7 font-bold text-ink tabular-nums" data-ringkasan="total">{{ $jumlahTotal }}</div>
                    <div class="text-[11px] leading-[14px] font-bold text-muted">Total</div>
                </div>
            </div>
            @if ($jumlahBelum > 0)
                <button type="button" wire:click="tandaiSemuaDibaca" class="btn btn-primary w-full">Tandai semua dibaca</button>
            @else
                <button type="button" disabled class="btn btn-primary w-full">Tandai semua dibaca</button>
            @endif
            @if ($jumlahSudah > 0)
                <button type="button" wire:click="hapusYangDibaca" class="btn btn-secondary w-full">Hapus yang sudah dibaca ({{ $jumlahSudah }})</button>
            @else
                <button type="button" disabled class="btn btn-secondary w-full">Hapus yang sudah dibaca</button>
            @endif
        </div>

        <div class="kartu flex flex-col gap-2.5 p-5">
            <div class="text-[11px] leading-[14px] font-bold tracking-[0.08em] text-muted uppercase">Sumber notifikasi</div>
            <div class="text-[13px] leading-[19px] font-medium text-nav">RSVP dan ucapan dari undangan yang sudah kamu terbitkan masuk otomatis ke daftar ini.</div>
            <div class="-mx-2.5 flex flex-col gap-0.5">
                @foreach ([['rsvp', 'RSVP & Tamu', 'rsvp'], ['buku-tamu', 'Buku Tamu', 'buku'], ['billing', 'Paket & Billing', 'billing']] as [$rute, $label, $ikon])
                    <a href="{{ route($rute) }}" wire:navigate class="flex min-h-11 items-center gap-2.5 rounded-xl px-2.5 text-[13px] font-bold text-brand hover:bg-brand-50"><x-ikon :nama="$ikon" :ukuran="16" />{{ $label }}</a>
                @endforeach
            </div>
        </div>
    </aside>

    @if ($pesan !== '')
        <div role="status" aria-live="polite" x-data x-init="setTimeout(() => $wire.tutupPesan(), 8000)"
             class="fixed bottom-6 left-1/2 z-[70] flex max-w-[calc(100%-32px)] -translate-x-1/2 items-center gap-2 rounded-2xl bg-body py-1.5 pr-1.5 pl-[18px] text-[13px] leading-[18px] font-semibold text-white shadow-[0_12px_30px_rgba(19,31,36,0.35)]">
            <span>{{ $pesan }}</span>
            @if ($urungkan !== [])
                <button type="button" wire:click="batalkanHapus" class="min-h-11 px-3 font-bold tracking-wide text-brand-200 uppercase">Urungkan</button>
            @endif
            <button type="button" wire:click="tutupPesan" aria-label="Tutup" class="flex size-11 items-center justify-center text-gray-400 hover:text-white"><x-ikon nama="tutup" :ukuran="16" /></button>
        </div>
    @endif
</div>
