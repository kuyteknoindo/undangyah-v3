@php
    $nama = ['Jenis acara', 'Detail acara', 'Desain'];
    $kepala = [
        1 => ['Acara apa yang akan kamu rayakan?', 'Pilih jenis acara. Saat ini tersedia untuk pernikahan, yang lain segera hadir.'],
        2 => ['Ceritakan acaranya', 'Isi data dasar dulu. Detail lainnya bisa dilengkapi di editor.'],
        3 => ['Pilih desain undangan', 'Pilih tema awal. Bisa diganti kapan saja.'],
    ];
    $jenis = [
        ['Pernikahan', 'Akad & resepsi', 'buat-hati', true],
        ['Ulang Tahun', 'Anak & dewasa', 'buat-kue', false],
        ['Khitanan', 'Syukuran khitan', 'buat-bulan', false],
        ['Syukuran', 'Rumah, aqiqah, dll', 'buat-rumah', false],
        ['Reuni', 'Alumni & keluarga', 'buat-orang', false],
        ['Lainnya', 'Acara lain', 'buat-titik', false],
    ];
    $judulPv = trim($judul) !== '' ? trim($judul) : 'Raka & Sinta';
    $tanggalPv = $tanggalTitik;
@endphp
<div class="mx-auto flex max-w-6xl flex-col gap-5">
    <ol aria-label="Langkah" class="flex flex-wrap gap-3">
        @foreach ($nama as $i => $label)
            @php
                $n = $i + 1;
                $aktif = $langkah === $n;
                $selesai = $langkah > $n;
            @endphp
            <li @if ($aktif) aria-current="step" @endif
                class="box-border flex min-h-[52px] min-w-[150px] flex-1 items-center gap-2.5 rounded-2xl border-2 px-4 {{ $aktif ? 'border-brand bg-brand-100 text-brand' : ($selesai ? 'border-line bg-white text-body' : 'border-line bg-white text-muted') }}">
                <span class="flex size-[26px] shrink-0 items-center justify-center rounded-full text-xs font-bold {{ $aktif || $selesai ? 'bg-brand text-white' : 'bg-line text-muted' }}">
                    @if ($selesai)
                        <x-ikon nama="centang" :ukuran="14" />
                    @else
                        {{ $n }}
                    @endif
                </span>
                <span class="text-sm font-semibold">{{ $label }}</span>
            </li>
        @endforeach
    </ol>

    <div class="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_320px]">
        <section class="kartu p-5 sm:p-7" aria-labelledby="judul-langkah">
            <div class="mb-5">
                <h2 id="judul-langkah" class="text-xl leading-7 font-semibold tracking-tight text-ink">{{ $kepala[$langkah][0] }}</h2>
                <div class="sub mt-1">{{ $kepala[$langkah][1] }}</div>
            </div>

            @if ($langkah === 1 && $penuh)
                <div role="alert" class="mb-5 rounded-2xl border border-[#fedf89] bg-warning-50 p-4">
                    <div class="text-sm font-semibold text-warning">Kuota undangan habis</div>
                    <div class="mt-1 text-[13px] leading-5 text-body">Paketmu mengizinkan {{ $batas }} undangan dan semuanya sudah terpakai ({{ $terpakai }}). Upgrade paket untuk membuat undangan baru.</div>
                    @if (Route::has('billing'))
                        <a href="{{ route('billing') }}" wire:navigate class="btn btn-secondary btn-sm mt-3">Upgrade paket</a>
                    @endif
                </div>
            @endif

            @if ($langkah === 1)
                <div role="group" aria-label="Pilih jenis acara" class="grid gap-3 sm:grid-cols-2">
                    @foreach ($jenis as [$nm, $ket, $ikon, $siap])
                        <button type="button" @disabled(! $siap) @if ($siap) aria-pressed="true" @endif
                                class="box-border flex min-h-[76px] w-full items-center gap-3.5 rounded-[20px] border-2 p-3.5 text-left transition {{ $siap ? 'border-brand bg-[#F2F6FF] shadow-[0_4px_0_#BFD3FF]' : 'cursor-not-allowed border-line bg-white opacity-60 shadow-[0_4px_0_#E2E5E9]' }}">
                            <span class="flex size-11 shrink-0 items-center justify-center rounded-full {{ $siap ? 'bg-brand text-white' : 'bg-brand-100 text-brand-dark' }}"><x-ikon :nama="$ikon" :ukuran="22" /></span>
                            <span class="flex min-w-0 flex-1 flex-col">
                                <span class="text-[15px] leading-5 font-semibold text-ink">{{ $nm }}</span>
                                <span class="text-[13px] leading-[18px] text-muted">{{ $ket }}</span>
                            </span>
                            @if ($siap)
                                <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-brand text-white"><x-ikon nama="centang" :ukuran="14" /></span>
                            @else
                                <span class="tag shrink-0">Segera hadir</span>
                            @endif
                        </button>
                    @endforeach
                </div>
                <div class="mt-4 flex items-start gap-2 rounded-xl bg-canvas p-3 text-[13px] leading-5 text-muted">
                    <x-ikon nama="peringatan" :ukuran="16" class="mt-0.5" /> Saat ini undangan baru tersedia untuk pernikahan. Jenis acara lainnya sedang kami siapkan.
                </div>
            @elseif ($langkah === 2)
                <form wire:submit="lanjut" class="flex flex-col gap-4" novalidate>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-field id="b-pria" label="Nama panggilan mempelai pria (opsional)" placeholder="Contoh: Raka" wire:model.live.debounce.400ms="pria" maxlength="40" :galat="$errors->first('pria')" />
                        <x-field id="b-wanita" label="Nama panggilan mempelai wanita (opsional)" placeholder="Contoh: Sinta" wire:model.live.debounce.400ms="wanita" maxlength="40" :galat="$errors->first('wanita')" />
                    </div>
                    <div>
                        <x-field id="b-judul" label="Judul undangan" placeholder="Contoh: Raka & Sinta" wire:model.live.debounce.400ms="judul" maxlength="120" :galat="$errors->first('judul')" />
                        @unless ($errors->has('judul') || $judulDiubah)
                            <div class="hint mt-1.5">Terisi otomatis dari nama mempelai, boleh diubah.</div>
                        @endunless
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-field id="b-tanggal" label="Tanggal acara" type="date" wire:model.live="tanggal" :galat="$errors->first('tanggal')" />
                        <x-field id="b-waktu" label="Waktu (WIB)" type="time" wire:model="waktu" :galat="$errors->first('waktu')" />
                    </div>
                    <x-field id="b-lokasi" label="Lokasi (opsional)" placeholder="Contoh: Grand Ballroom, Jakarta" wire:model="lokasi" maxlength="150" :galat="$errors->first('lokasi')" />
                    <div>
                        <x-field id="b-slug" label="Link undangan" awalan="app.undangyah.com/" wire:model.live.debounce.500ms="slug" maxlength="60" autocomplete="off" autocapitalize="none" spellcheck="false" :galat="$errors->first('slug')" />
                        @if ($slugTersedia && ! $errors->has('slug'))
                            <div class="mt-1.5 flex items-center gap-1.5 text-xs leading-4 font-medium text-success"><x-ikon nama="centang" :ukuran="14" /> Link tersedia</div>
                        @elseif (! $errors->has('slug'))
                            <div class="hint mt-1.5">Dibuat otomatis dari judul. Huruf kecil, angka, dan tanda hubung saja.</div>
                        @endif
                    </div>
                    <button type="submit" class="sr-only" tabindex="-1" aria-hidden="true">Teruskan</button>
                </form>
            @else
                @if ($tema->isEmpty())
                    <div role="status" class="rounded-xl bg-canvas p-4 text-sm text-muted">Belum ada tema yang diterbitkan. Coba lagi nanti.</div>
                @endif
                <div role="group" aria-label="Pilih desain" class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                    @foreach ($tema as $t)
                        @php
                            $kunci = $terkunci[$t->id] ?? false;
                            $pilih = $temaId === $t->id;
                        @endphp
                        <div wire:key="tema-{{ $t->id }}" class="flex flex-col gap-1.5">
                            <button type="button" wire:click="pilihTema({{ $t->id }})" @disabled($kunci) aria-pressed="{{ $pilih ? 'true' : 'false' }}"
                                    aria-label="Tema {{ $t->nama }}{{ $kunci ? ', terkunci, perlu paket '.$t->tier : '' }}"
                                    class="relative block w-full rounded-[20px] border-2 bg-white p-1 text-left {{ $pilih ? 'border-brand' : 'border-line' }} {{ $kunci ? 'cursor-not-allowed opacity-70' : '' }}">
                                <x-buat-undangan.sampul :tema="$t" :judul="$judulPv" :tanggal="$tanggalPv" />
                                @if ($pilih)
                                    <span class="absolute top-2.5 right-2.5 flex size-6 items-center justify-center rounded-full bg-brand text-white"><x-ikon nama="centang" :ukuran="14" /></span>
                                @endif
                                @if ($kunci)
                                    <span class="absolute top-2.5 right-2.5 flex size-6 items-center justify-center rounded-full bg-gray-900 text-white"><x-ikon nama="buat-kunci" :ukuran="13" /></span>
                                @endif
                            </button>
                            <div class="flex items-center justify-between gap-2 px-1">
                                <span class="truncate text-[13px] leading-[18px] font-semibold text-ink">{{ $t->nama }}</span>
                                <span class="shrink-0 rounded-md px-1.5 text-[11px] leading-4 font-semibold" style="{{ \App\Support\PratinjauTema::TIER[$t->tier] ?? \App\Support\PratinjauTema::TIER['Gratis'] }}">{{ $t->tier }}</span>
                            </div>
                            @if ($kunci)
                                <div class="hint px-1">Perlu paket {{ $t->tier }}.
                                    @if (Route::has('billing'))
                                        <a href="{{ route('billing') }}" wire:navigate class="font-semibold text-brand">Upgrade</a>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
                <div class="hint mt-4">Tema bisa diganti kapan saja lewat menu Template Desain atau di editor.</div>
            @endif

            @if ($galatUmum !== '')
                <div role="alert" class="mt-4 flex items-center gap-1.5 rounded-xl bg-danger-50 px-3 py-2.5 text-[13px] leading-5 font-medium text-danger">
                    <x-ikon nama="peringatan" :ukuran="16" /> {{ $galatUmum }}
                </div>
            @endif

            <div class="mt-6 flex items-center justify-between gap-3 border-t border-line pt-4">
                <div>
                    @if ($langkah === 1)
                        <a href="{{ route('beranda') }}" wire:navigate class="btn btn-secondary">Batal</a>
                    @else
                        <button type="button" wire:click="kembali" class="btn btn-secondary"><x-ikon nama="buat-kembali" :ukuran="16" /> Kembali</button>
                    @endif
                </div>
                <div>
                    @if ($langkah < 3)
                        <button type="button" wire:click="lanjut" wire:loading.attr="disabled" wire:target="lanjut" class="btn btn-primary" @disabled($langkah === 1 && $penuh)>
                            Lanjut <x-ikon nama="buat-lanjut" :ukuran="16" />
                        </button>
                    @else
                        <button type="button" wire:click="buat" wire:loading.attr="disabled" wire:target="buat" class="btn btn-primary" @disabled(! $dipilih)>
                            <span wire:loading.remove wire:target="buat" class="inline-flex items-center gap-2"><x-ikon nama="centang" :ukuran="16" /> Buat undangan</span>
                            <span wire:loading wire:target="buat">Membuat undangan...</span>
                        </button>
                    @endif
                </div>
            </div>
        </section>

        <aside aria-label="Pratinjau tampilan" class="kartu p-4 lg:sticky lg:top-24">
            <div class="mb-3 flex items-center justify-between gap-2">
                <div class="text-[13px] leading-[18px] font-semibold text-ink">Pratinjau</div>
                <span class="tag">{{ $dipilih?->nama ?? '-' }}</span>
            </div>
            @if ($dipilih)
                <div class="mx-auto max-w-[240px] lg:max-w-none">
                    <x-buat-undangan.sampul :tema="$dipilih" :judul="$judulPv" :tanggal="$tanggalPv" :besar="true" />
                </div>
            @else
                <div class="rounded-xl bg-canvas p-6 text-center text-[13px] text-muted">Belum ada tema dipilih.</div>
            @endif
            <div class="hint mt-3">Contoh tampilan sampul. Isi lengkap diatur di editor.</div>
        </aside>
    </div>
</div>
