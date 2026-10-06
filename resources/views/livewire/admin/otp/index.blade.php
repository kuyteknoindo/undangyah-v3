@php
    $angka = fn (int|float $n): string => number_format($n, 0, ',', '.');
    $nadaKelas = ['sukses' => 'bg-[#ECFDF3] text-[#067647]', 'galat' => 'bg-[#FEF3F2] text-[#B42318]', 'info' => 'bg-[#EEF3FE] text-[#1D4ED8]'];
    $dariBawaan = $tersimpan === $bawaan;
@endphp
<div class="mx-auto flex max-w-6xl flex-col gap-5">
    <p class="sub m-0 max-w-2xl">Aturan kode verifikasi yang dikirim lewat email saat pengguna mendaftar atau mengatur ulang kata sandi. Perubahan langsung berlaku untuk kode berikutnya.</p>

    <section aria-labelledby="st-h" class="kartu flex flex-wrap items-center gap-4 p-5">
        <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-[#ECFDF3] text-[#067647]"><x-ikon nama="admin-otp" :ukuran="22" /></span>
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
                <h2 id="st-h" class="h2">Verifikasi OTP</h2>
                <x-pill warna="hijau">Aktif</x-pill>
                <span class="tag">Kanal: email</span>
            </div>
            <div class="sub mt-0.5">Kode {{ $tersimpan['panjang'] }} digit, berlaku {{ $tersimpan['berlaku_menit'] }} menit, {{ $tersimpan['maks_salah'] }} kali salah lalu terkunci, jeda kirim ulang {{ $tersimpan['jeda_detik'] }} detik, maksimal {{ $tersimpan['maks_per_jam'] }} kiriman per jam per email.</div>
        </div>
        <div class="hint">{{ $adaTersimpan && $diubahAt ? 'Terakhir disimpan '.$diubahAt->locale('id')->translatedFormat('j M Y, H.i') : 'Memakai nilai bawaan' }}</div>
    </section>

    <div class="grid gap-5 grid-cols-[minmax(0,1fr)] lg:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)]">
        <div class="flex flex-col gap-5">
            <section aria-labelledby="kd-h" class="kartu flex flex-col gap-5 p-5">
                <div>
                    <h2 id="kd-h" class="h2">Kode</h2>
                    <div class="sub">Panjang, masa berlaku, dan batas percobaan.</div>
                </div>

                <div>
                    <div id="len-l" class="lbl">Panjang kode</div>
                    <div class="seg flex-wrap" role="group" aria-labelledby="len-l">
                        @for ($n = $rentang['panjang'][1]; $n <= $rentang['panjang'][2]; $n++)
                            <button type="button" wire:click="pilihPanjang({{ $n }})" aria-pressed="{{ (string) $n === $panjang ? 'true' : 'false' }}">{{ $n }} digit</button>
                        @endfor
                    </div>
                    <div class="hint mt-1.5">Makin panjang makin aman (kode 6 digit punya sejuta kemungkinan); makin pendek makin cepat diketik. Pilihan 4-8 digit.</div>
                    @error('panjang')<div role="alert" class="galat mt-1">{{ $message }}</div>@enderror
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="o-exp" class="lbl">Berlaku selama</label>
                        <div class="flex items-center gap-2"><input id="o-exp" type="number" inputmode="numeric" min="{{ $rentang['berlaku_menit'][1] }}" max="{{ $rentang['berlaku_menit'][2] }}" wire:model.live.debounce.400ms="berlakuMenit" class="inp" aria-describedby="o-exp-h" @error('berlakuMenit') aria-invalid="true" @enderror><span class="sub shrink-0">menit</span></div>
                        <div id="o-exp-h" class="hint mt-1">{{ $rentang['berlaku_menit'][1] }}-{{ $rentang['berlaku_menit'][2] }} menit. Terlalu lama memperbesar risiko.</div>
                        @error('berlakuMenit')<div role="alert" class="galat mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label for="o-tries" class="lbl">Maksimal percobaan salah</label>
                        <div class="flex items-center gap-2"><input id="o-tries" type="number" inputmode="numeric" min="{{ $rentang['maks_salah'][1] }}" max="{{ $rentang['maks_salah'][2] }}" wire:model.live.debounce.400ms="maksSalah" class="inp" aria-describedby="o-tries-h" @error('maksSalah') aria-invalid="true" @enderror><span class="sub shrink-0">kali</span></div>
                        <div id="o-tries-h" class="hint mt-1">{{ $rentang['maks_salah'][1] }}-{{ $rentang['maks_salah'][2] }} kali, lalu kode terkunci.</div>
                        @error('maksSalah')<div role="alert" class="galat mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label for="o-cool" class="lbl">Jeda kirim ulang</label>
                        <div class="flex items-center gap-2"><input id="o-cool" type="number" inputmode="numeric" min="{{ $rentang['jeda_detik'][1] }}" max="{{ $rentang['jeda_detik'][2] }}" wire:model.live.debounce.400ms="jedaDetik" class="inp" aria-describedby="o-cool-h" @error('jedaDetik') aria-invalid="true" @enderror><span class="sub shrink-0">detik</span></div>
                        <div id="o-cool-h" class="hint mt-1">{{ $rentang['jeda_detik'][1] }}-{{ $rentang['jeda_detik'][2] }} detik, lebih singkat dari masa berlaku.</div>
                        @error('jedaDetik')<div role="alert" class="galat mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label for="o-rate" class="lbl">Batas permintaan per jam</label>
                        <div class="flex items-center gap-2"><input id="o-rate" type="number" inputmode="numeric" min="{{ $rentang['maks_per_jam'][1] }}" max="{{ $rentang['maks_per_jam'][2] }}" wire:model.live.debounce.400ms="maksPerJam" class="inp" aria-describedby="o-rate-h" @error('maksPerJam') aria-invalid="true" @enderror><span class="sub shrink-0">/ jam</span></div>
                        <div id="o-rate-h" class="hint mt-1">{{ $rentang['maks_per_jam'][1] }}-{{ $rentang['maks_per_jam'][2] }} per email dan tujuan. Melindungi dari penyalahgunaan.</div>
                        @error('maksPerJam')<div role="alert" class="galat mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-[#eef0f3] pt-4">
                    <div class="flex items-center gap-2">
                        <x-pill :warna="$kotor ? 'kuning' : ($dariBawaan ? 'abu' : 'hijau')">{{ $kotor ? 'Belum disimpan' : ($dariBawaan ? 'Bawaan' : 'Tersimpan') }}</x-pill>
                        <span class="hint">{{ $kotor ? 'Ada perubahan yang belum disimpan.' : 'Sesuai pengaturan berlaku.' }}</span>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" wire:click="bukaReset" class="btn btn-secondary btn-sm">Reset ke bawaan</button>
                        <button type="button" wire:click="batalkan" @disabled(! $kotor) class="btn btn-secondary btn-sm">Batalkan perubahan</button>
                        <button type="button" wire:click="simpan" wire:loading.attr="disabled" wire:target="simpan" class="btn btn-primary">Simpan</button>
                    </div>
                </div>
            </section>

            <section aria-labelledby="uj-h" class="kartu flex flex-col gap-4 p-5">
                <div>
                    <h2 id="uj-h" class="h2">Uji OTP</h2>
                    <div class="sub">Kirim kode uji ke emailmu sendiri ({{ $emailAdmin }}) memakai aturan yang tersimpan, lalu coba verifikasi. Kode tidak ditampilkan di halaman ini.</div>
                </div>
                @if ($hasilUji !== '')
                    <div role="status" class="rounded-[10px] px-3 py-2.5 text-[13px] leading-[18px] font-medium {{ $nadaKelas[$nadaUji] ?? $nadaKelas['info'] }}">{{ $hasilUji }}</div>
                @endif
                <div class="flex flex-wrap items-end gap-3">
                    <button type="button" wire:click="kirimUji" wire:loading.attr="disabled" wire:target="kirimUji" class="btn btn-secondary"><x-ikon nama="surel" :ukuran="16" /> {{ $adaKodeUji ? 'Kirim ulang kode uji' : 'Kirim kode uji' }}</button>
                    @if ($adaKodeUji)
                        <form wire:submit="periksaUji" class="flex flex-1 flex-wrap items-end gap-2">
                            <div class="min-w-[160px] flex-1">
                                <label for="o-try" class="lbl">Masukkan kode</label>
                                <input id="o-try" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="{{ $rentang['panjang'][2] }}" wire:model="kodeUji" placeholder="{{ $tersimpan['panjang'] }} digit" class="inp">
                            </div>
                            <button type="submit" class="btn btn-primary">Verifikasi</button>
                        </form>
                    @endif
                </div>
                <div class="hint">Batas wajar tetap berlaku: jeda {{ $tersimpan['jeda_detik'] }} detik antar pengiriman dan paling banyak 5 uji per 10 menit.</div>
            </section>
        </div>

        <aside class="flex flex-col gap-5">
            <section aria-labelledby="sx-h" class="kartu flex flex-col gap-3 p-5">
                <div>
                    <h2 id="sx-h" class="h2">Statistik OTP</h2>
                    <div class="sub">Dari kode yang masih tersimpan (kode lama dibersihkan otomatis sehari setelah kedaluwarsa). Isi kode tidak pernah dibaca.</div>
                </div>
                <dl class="m-0 grid grid-cols-2 gap-3">
                    @foreach ([['Dibuat 24 jam terakhir', $stat['dibuat24']], ['Aktif sekarang', $stat['aktif']], ['Terpakai (berhasil)', $stat['terpakai']], ['Kedaluwarsa', $stat['kedaluwarsa']], ['Terkunci', $stat['terkunci']], ['Pernah salah', $stat['salah']]] as [$label, $nilai])
                        <div class="rounded-[10px] border border-line px-3 py-2.5">
                            <dt class="hint">{{ $label }}</dt>
                            <dd class="m-0 text-xl leading-7 font-semibold tabular-nums text-ink">{{ $angka($nilai) }}</dd>
                        </div>
                    @endforeach
                </dl>
                <div class="hint">Verifikasi email: {{ $angka($stat['verifikasi']) }} · Atur ulang sandi: {{ $angka($stat['reset']) }} · Total tersimpan: {{ $angka($stat['total']) }}</div>
            </section>

            <section aria-labelledby="pv-h" class="kartu flex flex-col gap-3 p-5">
                <div>
                    <h2 id="pv-h" class="h2">Contoh kode</h2>
                    <div class="sub">Tampilan kode pada email dengan aturan tersimpan (angka contoh).</div>
                </div>
                <div class="flex justify-center rounded-xl bg-[#EEF3FE] px-4 py-4 text-[28px] leading-8 font-bold tracking-[0.3em] text-brand" aria-label="Contoh kode {{ $tersimpan['panjang'] }} digit">{{ $contoh }}</div>
                <div class="hint">Kode berlaku {{ $tersimpan['berlaku_menit'] }} menit. Aturan di kiri berlaku untuk Daftar, Verifikasi Email, dan Lupa Sandi.</div>
            </section>
        </aside>
    </div>

    <x-dialog wire:model="tampilReset" judul="Kembalikan ke bawaan?" lebar="max-w-[480px]">
        <p class="sub m-0">Semua isian kembali ke nilai bawaan dan langsung disimpan: {{ $bawaan['panjang'] }} digit, berlaku {{ $bawaan['berlaku_menit'] }} menit, {{ $bawaan['maks_salah'] }} percobaan, jeda {{ $bawaan['jeda_detik'] }} detik, {{ $bawaan['maks_per_jam'] }} permintaan per jam.</p>
        <x-slot:footer>
            <button type="button" wire:click="$set('tampilReset', false)" class="btn btn-secondary">Batal</button>
            <button type="button" wire:click="resetBawaan" class="btn btn-primary">Reset dan simpan</button>
        </x-slot:footer>
    </x-dialog>
</div>
