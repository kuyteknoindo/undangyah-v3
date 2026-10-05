{{-- Dipakai di dalam elemen ber-x-data yang punya variabel `p` (isi kata sandi). --}}
<div x-data="{
        get skor() {
            const s = p || '';
            let n = 0;
            if (s.length >= 8) n++;
            if (s.length >= 12) n++;
            if (/[a-z]/.test(s) && /[A-Z]/.test(s)) n++;
            if (/\d/.test(s)) n++;
            if (/[^A-Za-z0-9]/.test(s)) n++;
            return s.length === 0 ? 0 : Math.min(4, Math.max(1, n - (s.length < 8 ? 1 : 0)));
        },
        get label() { return ['', 'Lemah', 'Cukup', 'Baik', 'Kuat'][this.skor]; },
        get warna() { return ['bg-line', 'bg-danger', 'bg-warning', 'bg-brand', 'bg-success'][this.skor]; },
     }" x-show="p && p.length > 0" x-cloak class="flex items-center gap-2" aria-live="polite">
    <div class="flex flex-1 gap-1" aria-hidden="true">
        <template x-for="i in 4"><span class="h-1 flex-1 rounded-full transition-colors" x-bind:class="i <= skor ? warna : 'bg-line'"></span></template>
    </div>
    <span class="text-xs font-medium text-muted" x-text="'Kekuatan: ' + label"></span>
</div>
