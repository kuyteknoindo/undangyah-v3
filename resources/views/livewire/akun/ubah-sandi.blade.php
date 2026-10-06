<div class="mx-auto grid max-w-5xl gap-5 grid-cols-[minmax(0,1fr)] lg:grid-cols-[minmax(0,1fr)_320px] lg:items-start" x-data="{ get p() { return $wire.sandiBaru || ''; } }">
    <section aria-label="Kata sandi" class="kartu flex flex-col gap-4 p-5 sm:p-6">
        <div>
            <h2 class="h2">Ganti kata sandi</h2>
            <p class="sub m-0 mt-1">{{ $terakhir }}</p>
        </div>

        @if ($catatan !== '')
            <div role="status" class="flex items-start gap-2 rounded-xl bg-success-50 px-4 py-3 text-sm leading-5 font-medium text-success">
                <x-ikon nama="centang" :ukuran="18" class="mt-0.5" />
                <span class="flex-1">{{ $catatan }}</span>
                <button type="button" wire:click="$set('catatan', '')" aria-label="Tutup pesan" class="text-success/70 hover:text-success"><x-ikon nama="tutup" :ukuran="16" /></button>
            </div>
        @endif

        <form wire:submit="ubah" class="flex flex-col gap-4" novalidate>
            <x-field id="sp-lama" label="Kata sandi saat ini" ikon="gembok" :sandi="true" wire:model="sandiLama" autocomplete="current-password" placeholder="Masukkan kata sandi saat ini" :galat="$errors->first('sandiLama')" />

            <hr class="m-0 border-line">

            <div class="flex flex-col gap-2">
                <x-field id="sp-baru" label="Kata sandi baru" ikon="gembok" :sandi="true" wire:model="sandiBaru" autocomplete="new-password" placeholder="Buat kata sandi baru" :galat="$errors->first('sandiBaru')" />
                <x-kekuatan-sandi />
            </div>

            <x-field id="sp-ulang" label="Ulangi kata sandi baru" ikon="gembok" :sandi="true" wire:model="sandiKonfirmasi" autocomplete="new-password" placeholder="Ulangi kata sandi baru" :galat="$errors->first('sandiKonfirmasi')" />

            <div class="flex flex-wrap items-center justify-end gap-2 pt-1">
                <button type="button" class="btn btn-secondary" wire:click="bersihkan"
                        x-show="$wire.sandiLama || $wire.sandiBaru || $wire.sandiKonfirmasi" x-cloak>Bersihkan</button>
                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="ubah"
                        x-bind:disabled="!($wire.sandiLama || $wire.sandiBaru || $wire.sandiKonfirmasi)">
                    <span wire:loading.remove wire:target="ubah">Ubah Kata Sandi</span>
                    <span wire:loading wire:target="ubah">Menyimpan...</span>
                </button>
            </div>
        </form>

        <p class="hint m-0">Lupa kata sandi saat ini? Keluar dulu, lalu pilih <strong class="font-semibold">Lupa kata sandi?</strong> di halaman Masuk.</p>
    </section>

    <aside aria-label="Panduan kata sandi" class="flex flex-col gap-5">
        <div class="kartu flex flex-col gap-3 p-5">
            <div class="flex items-center gap-2.5">
                <span class="flex size-8 items-center justify-center rounded-lg bg-brand-50 text-brand"><x-ikon nama="akun-terverifikasi" :ukuran="16" /></span>
                <h2 class="h2">Syarat kata sandi baru</h2>
            </div>
            <div class="flex flex-col gap-2">
                @foreach ([
                    ['Minimal 8 karakter', 'p.length >= 8'],
                    ['Kombinasi huruf dan angka', '/[A-Za-z]/.test(p) && /\\d/.test(p)'],
                    ['Huruf besar dan kecil (disarankan)', '/[A-Z]/.test(p) && /[a-z]/.test(p)'],
                ] as [$label, $syarat])
                    <div class="flex items-center gap-2.5 text-[13px] leading-5 text-muted" x-bind:class="{{ $syarat }} ? '!text-success' : ''">
                        <span class="flex size-[18px] shrink-0 items-center justify-center rounded-full bg-[#c3ccda] text-white" x-bind:class="{{ $syarat }} ? '!bg-[#58a700]' : ''">
                            <span x-show="{{ $syarat }}" x-cloak><x-ikon nama="centang" :ukuran="12" /></span>
                        </span>
                        {{ $label }}
                    </div>
                @endforeach
            </div>
        </div>

        <div class="kartu flex flex-col gap-2 p-5">
            <h2 class="h2">Tips keamanan</h2>
            <ul class="m-0 flex list-disc flex-col gap-1.5 pl-5 text-[13px] leading-5 text-muted">
                <li>Pakai kata sandi yang tidak dipakai di layanan lain.</li>
                <li>Campur huruf besar, kecil, angka, dan simbol.</li>
                <li>Jangan bagikan kata sandi atau kode verifikasi ke siapa pun.</li>
            </ul>
        </div>
    </aside>
</div>
