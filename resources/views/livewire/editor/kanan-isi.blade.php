<div class="flex flex-none items-center gap-2 border-b-2 border-[#e2e5e9] px-4 py-3">
    <h2 class="m-0 flex-1 text-base leading-[22px] font-bold">Isi</h2>
    <span class="rounded-lg bg-[#eef3fe] px-2 py-0.5 text-[11px] leading-4 font-bold text-brand">{{ $terpilih ? 'Bidang' : 'Bagian' }}</span>
    <button type="button" class="ed-ikbtn !size-9 border-0 max-lg:hidden" x-on:click="kanan = false" aria-label="Sembunyikan panel kanan" title="Sembunyikan panel kanan"><x-ikon nama="editor-panel-kanan" :ukuran="18" /></button>
</div>

<div class="ed-scroll flex flex-col gap-4">
    {{-- Kepala bagian + naik/turun/sembunyi --}}
    <div class="ed-kartu flex flex-col gap-3 p-3" wire:key="kepala-{{ $aktif }}">
        <div class="flex items-center gap-2.5">
            <span class="flex size-10 flex-none items-center justify-center rounded-xl bg-[#e3ecfd] text-brand"><x-ikon :nama="'editor-'.$aktif" :ukuran="20" /></span>
            <div class="min-w-0">
                <div class="truncate text-[15px] leading-5 font-bold">Bagian {{ $definisiAktif['name'] }}</div>
                <div class="text-xs leading-4 text-[#666c76]">{{ $jumlahKolom }} kolom{{ $bagianTersembunyi ? ' · disembunyikan' : '' }}</div>
            </div>
        </div>
        <div class="flex flex-wrap gap-1.5">
            <button type="button" class="ed-kecil" wire:click="geserBagian('{{ $aktif }}', -1)" @disabled(! $bisaNaik) aria-label="Naikkan bagian {{ $definisiAktif['name'] }}"><x-ikon nama="editor-naik" :ukuran="15" />Naik</button>
            <button type="button" class="ed-kecil" wire:click="geserBagian('{{ $aktif }}', 1)" @disabled(! $bisaTurun) aria-label="Turunkan bagian {{ $definisiAktif['name'] }}"><x-ikon nama="editor-turun" :ukuran="15" />Turun</button>
            <button type="button" class="ed-kecil" wire:click="toggleBagian('{{ $aktif }}')" @disabled(! $bisaSembunyi) aria-label="{{ $bagianTersembunyi ? 'Tampilkan' : 'Sembunyikan' }} bagian {{ $definisiAktif['name'] }}"
                    style="{{ $bagianTersembunyi ? 'background:#fff4cc;border-color:#fff4cc;color:#b25e00' : '' }}">
                <x-ikon :nama="$bagianTersembunyi ? 'mata-tutup' : 'mata'" :ukuran="15" />{{ $bagianTersembunyi ? 'Tampilkan' : 'Sembunyi' }}
            </button>
        </div>
        @if (! $bisaSembunyi)<div class="hint">Sampul selalu tampil.</div>@endif
    </div>

    @if ($adaFoto)
        @include('livewire.editor.foto')
    @endif

    @if ($jumlahKolom === 0)
        @if (! $adaFoto)<div class="rounded-2xl bg-[#f4f5f7] p-4 text-center text-[13px] text-[#666c76]">Bagian ini hanya berisi foto, jadi belum ada kolom teks.</div>@endif
    @else
        <div class="ed-label">Isi bagian · {{ $jumlahKolom }} kolom</div>
    @endif

    @foreach ($grup as $g)
        @if ($g['jenis'] === 'kepala')
            <div class="ed-label mt-1" wire:key="kepala-grup-{{ $aktif }}-{{ $loop->index }}">{{ $g['judul'] }}</div>
        @elseif ($g['jenis'] === 'tambah')
            <button type="button" wire:key="tambah-{{ $g['tpl'] }}" wire:click="tambahItem('{{ $g['tpl'] }}')"
                    class="flex min-h-12 w-full items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-[#bfd3ff] text-[13px] leading-[18px] font-bold tracking-[0.5px] text-brand uppercase hover:bg-brand-50">
                <x-ikon nama="tambah" :ukuran="16" />{{ $g['teks'] }}
            </button>
        @else
            @php
                $kunciLipat = $g['jenis'] === 'tetap' ? 'tetap' : $g['tpl'].$g['id'];
                $adaTerpilih = $terpilih !== null && in_array($terpilih, $g['kunci'], true);
                $awalBuka = $g['jenis'] === 'tetap' || $adaTerpilih || ($g['pertama'] ?? false) && ($g['terakhir'] ?? false);
            @endphp
            <div class="ed-kartu p-2.5" data-aktif="{{ $adaTerpilih ? '1' : '0' }}" wire:key="grup-{{ $aktif }}-{{ $kunciLipat }}"
                 x-data="{ buka: {{ $awalBuka ? 'true' : 'false' }} }"
                 x-effect="if (sel && $el.querySelector('[data-f=&quot;' + sel + '&quot;]')) buka = true">
                <div class="flex items-center gap-1">
                    <button type="button" class="flex min-h-9 min-w-0 flex-1 items-center gap-1.5 rounded-lg text-left" x-on:click="buka = !buka" x-bind:aria-expanded="buka" aria-label="Lipat atau buka {{ $g['judul'] }}">
                        <x-ikon nama="panah-kanan" :ukuran="16" class="transition-transform" x-bind:class="buka ? 'rotate-90' : ''" />
                        <span class="flex-none text-sm leading-5 font-bold">{{ $g['judul'] }}</span>
                        @if (! empty($g['ringkas']))<span class="truncate text-xs text-[#666c76]" x-show="!buka">{{ $g['ringkas'] }}</span>@endif
                    </button>
                    @if ($g['jenis'] === 'item')
                        <button type="button" class="ed-kecil ed-ikon" wire:click="geserItem('{{ $g['tpl'] }}', {{ $g['id'] }}, -1)" @disabled($g['pertama']) aria-label="Naikkan {{ $g['nama'] }}"><x-ikon nama="editor-naik" :ukuran="15" /></button>
                        <button type="button" class="ed-kecil ed-ikon" wire:click="geserItem('{{ $g['tpl'] }}', {{ $g['id'] }}, 1)" @disabled($g['terakhir']) aria-label="Turunkan {{ $g['nama'] }}"><x-ikon nama="editor-turun" :ukuran="15" /></button>
                        <button type="button" class="ed-kecil ed-ikon" wire:click="gandakanItem('{{ $g['tpl'] }}', {{ $g['id'] }})" aria-label="Gandakan {{ $g['nama'] }}" title="Gandakan"><x-ikon nama="editor-gandakan" :ukuran="15" /></button>
                        @if ($g['bisaHapus'])
                            <button type="button" class="ed-kecil ed-ikon text-[#d83a3a]" wire:click="hapusItem('{{ $g['tpl'] }}', {{ $g['id'] }})" wire:confirm="Hapus {{ $g['nama'] }}?" aria-label="Hapus {{ $g['nama'] }}"><x-ikon nama="editor-hapus" :ukuran="15" /></button>
                        @endif
                    @endif
                </div>
                <div class="mt-2 flex flex-col gap-3.5" x-show="buka" x-cloak>
                    @foreach ($g['kunci'] as $k)
                        @include('livewire.editor.bidang', ['k' => $k, 'meta' => $metaBidang[$k]])
                    @endforeach
                </div>
            </div>
        @endif
    @endforeach
</div>
