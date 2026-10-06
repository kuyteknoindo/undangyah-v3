@php
    use App\Support\WarnaAvatar;
    $peta = collect(WarnaAvatar::DAFTAR)->map(fn ($w) => $w[1]);
@endphp
<div class="mx-auto grid max-w-5xl gap-5 lg:grid-cols-[minmax(0,1fr)_300px] lg:items-start"
     x-data="{
        warna: @js($peta),
        kendala: '',
        get kotor() {
            const a = $wire.asli || {};
            return $wire.nama !== a.nama || $wire.panggilan !== a.panggilan || $wire.bio !== a.bio || $wire.avatarWarna !== a.avatarWarna || !!$wire.foto || $wire.hapusFoto;
        },
        get ini() {
            const k = ($wire.nama || '').trim().split(/\s+/).filter(Boolean);
            return (k.slice(0, 2).map((x) => x[0]).join('') || '?').toUpperCase();
        },
        pilihBerkas(e) {
            const f = e.target.files && e.target.files[0];
            this.kendala = '';
            if (!f) return;
            if (!/^image\/(jpeg|png|webp)$/.test(f.type)) { this.kendala = 'File harus berupa gambar (JPG atau PNG).'; e.target.value = ''; return; }
            if (f.size > 5 * 1024 * 1024) { this.kendala = 'Ukuran file maksimal 5 MB.'; e.target.value = ''; return; }
            $wire.upload('foto', f, () => { e.target.value = ''; }, () => { this.kendala = 'Foto gagal diunggah. Coba lagi.'; e.target.value = ''; });
        },
     }">

    <div class="flex min-w-0 flex-col gap-5">
        <section aria-label="Foto profil" class="kartu flex flex-col gap-5 p-5 sm:p-6">
            <div>
                <h2 class="h2">Foto profil</h2>
                <div class="sub">Tampil di bilah atas, menu, dan profil.</div>
            </div>

            <div class="flex flex-wrap items-center gap-5">
                <span class="relative inline-flex size-24 shrink-0 items-center justify-center overflow-hidden rounded-full text-3xl font-bold text-white" x-bind:style="{ backgroundColor: warna[$wire.avatarWarna] || '#1D4ED8' }">
                    @if ($fotoUrl)
                        <img src="{{ $fotoUrl }}" alt="Foto profil" class="size-full object-cover">
                    @else
                        <span x-text="ini">{{ $user->inisial() }}</span>
                    @endif
                </span>
                <div class="flex min-w-0 flex-col gap-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <label for="ep-file" class="btn btn-secondary btn-sm cursor-pointer focus-within:outline-2"><x-ikon nama="akun-unggah" :ukuran="16" /> Unggah Foto</label>
                        <input id="ep-file" type="file" accept="image/png,image/jpeg,image/webp" class="sr-only" x-on:change="pilihBerkas($event)">
                        @if ($fotoUrl)
                            <button type="button" class="btn btn-danger btn-sm" wire:click="hapusFotoSekarang"><x-ikon nama="akun-hapus" :ukuran="16" /> Hapus foto</button>
                        @endif
                        <span wire:loading wire:target="foto" class="text-xs font-medium text-muted">Mengunggah...</span>
                    </div>
                    <div class="hint">JPG atau PNG, maksimal 5 MB. Foto dipotong persegi dan diperkecil otomatis.</div>
                </div>
            </div>

            <div x-show="kendala" x-cloak role="alert" class="flex items-center gap-1.5 rounded-[10px] bg-danger-50 px-3 py-2.5 text-[13px] leading-[18px] font-medium text-danger">
                <x-ikon nama="peringatan" :ukuran="16" /> <span x-text="kendala"></span>
            </div>
            @error('foto')
                <div role="alert" class="flex items-center gap-1.5 rounded-[10px] bg-danger-50 px-3 py-2.5 text-[13px] leading-[18px] font-medium text-danger">
                    <x-ikon nama="peringatan" :ukuran="16" /> {{ $message }}
                </div>
            @enderror

            <div class="flex flex-col gap-2.5">
                <div class="text-[13px] leading-[18px] font-medium text-gray-700">Atau pilih warna inisial</div>
                <div role="group" aria-label="Warna inisial" class="flex flex-wrap gap-2.5">
                    @foreach (WarnaAvatar::DAFTAR as $kunci => [$label, $hex])
                        <button type="button" aria-label="Warna {{ $label }}" x-bind:aria-pressed="$wire.avatarWarna === '{{ $kunci }}'" x-bind:aria-label="'Warna {{ $label }}' + ($wire.avatarWarna === '{{ $kunci }}' ? ', dipilih' : '')"
                                x-on:click="$wire.avatarWarna = '{{ $kunci }}'"
                                class="flex size-11 items-center justify-center rounded-full border-[3px] border-white text-white shadow-[0_0_0_2px_#e2e5e9] transition-shadow"
                                x-bind:class="$wire.avatarWarna === '{{ $kunci }}' ? '!shadow-[0_0_0_2px_#bfd3ff] !border-brand' : ''"
                                style="background-color: {{ $hex }}">
                            <span x-show="$wire.avatarWarna === '{{ $kunci }}'" x-cloak><x-ikon nama="centang" :ukuran="18" /></span>
                        </button>
                    @endforeach
                </div>
            </div>
        </section>

        <section aria-label="Tentang kamu" class="kartu flex flex-col gap-4 p-5 sm:p-6">
            <h2 class="h2">Tentang kamu</h2>

            <div>
                <label for="ep-nama" class="lbl">Nama tampilan</label>
                <input id="ep-nama" type="text" wire:model="nama" class="inp" maxlength="100" autocomplete="name" placeholder="Nama yang tampil di profil" @error('nama') aria-invalid="true" @enderror>
                @error('nama')<div role="alert" class="galat mt-1.5">{{ $message }}</div>@enderror
            </div>

            <div>
                <label for="ep-pang" class="lbl">Panggilan <span class="ml-1 font-normal text-subtle">Opsional</span></label>
                <input id="ep-pang" type="text" wire:model="panggilan" class="inp" maxlength="20" placeholder="Contoh: Raka" @error('panggilan') aria-invalid="true" @enderror>
                <div class="hint mt-1.5">Nama pendek untuk sapaan, misalnya "Selamat pagi, Raka".</div>
                @error('panggilan')<div role="alert" class="galat mt-1">{{ $message }}</div>@enderror
            </div>

            <div>
                <label for="ep-bio" class="lbl">Bio <span class="ml-1 font-normal text-subtle">Opsional</span></label>
                <textarea id="ep-bio" wire:model="bio" class="inp" maxlength="160" placeholder="Ceritakan sedikit tentang kamu" @error('bio') aria-invalid="true" @enderror></textarea>
                <div class="hint mt-1.5 text-right" x-text="($wire.bio || '').length + '/160 karakter'">{{ mb_strlen($bio) }}/160 karakter</div>
                @error('bio')<div role="alert" class="galat mt-1">{{ $message }}</div>@enderror
            </div>
        </section>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('akun.informasi') }}" wire:navigate class="inline-flex items-center gap-1 text-[13px] leading-5 font-semibold text-brand hover:underline">Ubah email atau nomor WhatsApp <x-ikon nama="panah-kanan" :ukuran="16" /></a>
            <div class="flex items-center gap-2">
                <span x-show="kotor" x-cloak class="mr-1 inline-flex items-center gap-1.5 text-xs font-medium text-warning"><span class="size-1.5 rounded-full bg-warning"></span> Belum disimpan</span>
                <button type="button" class="btn btn-secondary" x-show="kotor" x-cloak wire:click="batalkan">Batalkan</button>
                <button type="button" class="btn btn-primary" wire:click="simpan" wire:loading.attr="disabled" wire:target="simpan,foto" x-bind:disabled="!kotor">
                    <span wire:loading.remove wire:target="simpan">Simpan</span>
                    <span wire:loading wire:target="simpan">Menyimpan...</span>
                </button>
            </div>
        </div>
    </div>

    <aside aria-label="Pratinjau profil" class="flex flex-col gap-3 lg:sticky lg:top-6">
        <div class="kartu flex flex-col items-center gap-3 p-5 text-center">
            <div class="self-start text-[11px] leading-4 font-semibold tracking-[0.06em] text-muted uppercase">Pratinjau</div>
            <span class="inline-flex size-20 items-center justify-center overflow-hidden rounded-full text-2xl font-bold text-white" x-bind:style="{ backgroundColor: warna[$wire.avatarWarna] || '#1D4ED8' }">
                @if ($fotoUrl)
                    <img src="{{ $fotoUrl }}" alt="" class="size-full object-cover">
                @else
                    <span x-text="ini">{{ $user->inisial() }}</span>
                @endif
            </span>
            <div class="min-w-0 max-w-full">
                <div class="truncate text-base leading-6 font-semibold text-ink" x-text="($wire.nama || '').trim() || 'Nama kamu'">{{ $user->name }}</div>
                <div class="truncate text-[13px] leading-[18px] text-muted">{{ $user->email }}</div>
                <p x-show="($wire.bio || '').trim()" x-cloak class="m-0 mt-2 text-[13px] leading-5 break-words text-body" x-text="($wire.bio || '').trim()"></p>
            </div>
        </div>
        <p class="m-0 px-1 text-xs leading-[18px] text-muted">Email dan nomor WhatsApp diubah dari halaman Informasi Akun karena perlu verifikasi.</p>
    </aside>
</div>
