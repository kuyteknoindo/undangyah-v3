{{--
    Kotak kode 6 digit. Mengisi properti Livewire `kode` dan memanggil aksi saat 6 digit lengkap.
    Pakai: <x-kode-otp aksi="verifikasi" :galat="$errors->first('kode')" />
--}}
@props(['aksi', 'galat' => null, 'panjang' => 6])
<div x-data="{
        n: {{ $panjang }},
        d: Array({{ $panjang }}).fill(''),
        sinkron() {
            const k = this.d.join('');
            $wire.set('kode', k, false);
            if (k.length === this.n) { $wire.{{ $aksi }}(); }
        },
        isi(i, e) {
            const v = e.target.value.replace(/\D/g, '');
            if (v.length > 1) { this.tempel(i, v); return; }
            this.d[i] = v;
            if (v && i < this.n - 1) { this.$refs['k' + (i + 1)].focus(); }
            this.sinkron();
        },
        tempel(i, teks) {
            teks.replace(/\D/g, '').slice(0, this.n - i).split('').forEach((c, j) => { this.d[i + j] = c; });
            this.$refs['k' + Math.min(this.n - 1, i + teks.replace(/\D/g, '').length)].focus();
            this.sinkron();
        },
        hapus(i, e) {
            if (e.key === 'Backspace' && !this.d[i] && i > 0) { this.d[i - 1] = ''; this.$refs['k' + (i - 1)].focus(); this.sinkron(); }
            if (e.key === 'ArrowLeft' && i > 0) { this.$refs['k' + (i - 1)].focus(); }
            if (e.key === 'ArrowRight' && i < this.n - 1) { this.$refs['k' + (i + 1)].focus(); }
        },
        kosong() { this.d = Array(this.n).fill(''); $wire.set('kode', '', false); this.$refs.k0.focus(); },
     }"
     x-on:otp-reset.window="kosong()" class="flex flex-col gap-2">
    <label for="kode-0" class="label">Kode {{ $panjang }} digit</label>
    <div class="flex justify-between gap-2" role="group" aria-label="Kode {{ $panjang }} digit">
        @for ($i = 0; $i < $panjang; $i++)
            <input id="kode-{{ $i }}" x-ref="k{{ $i }}" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="{{ $panjang }}"
                   @if ($i === 0) autocomplete="one-time-code" autofocus @else autocomplete="off" @endif
                   x-model="d[{{ $i }}]" x-on:input="isi({{ $i }}, $event)" x-on:keydown="hapus({{ $i }}, $event)" x-on:focus="$event.target.select()"
                   aria-label="Digit {{ $i + 1 }}"
                   class="h-12 w-full min-w-0 rounded-[10px] border bg-white text-center text-xl font-semibold text-ink shadow-[0_1px_2px_rgba(16,24,40,0.05)] outline-none focus:border-brand focus:shadow-[0_0_0_4px_rgba(29,78,216,0.12)] {{ $galat ? 'border-danger' : 'border-line-strong' }}">
        @endfor
    </div>
    @if ($galat)
        <div role="alert" class="flex items-center gap-1.5 text-xs leading-4 font-medium text-danger"><x-ikon nama="peringatan" :ukuran="14" /> {{ $galat }}</div>
    @endif
</div>
