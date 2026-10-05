{{-- Hitung mundur kirim ulang. `detik` = sisa jeda dari server. --}}
@props(['detik' => 0, 'aksi' => 'kirimUlang'])
<div x-data="{ sisa: {{ (int) $detik }}, t: null,
        mulai() { clearInterval(this.t); this.t = setInterval(() => { if (this.sisa > 0) this.sisa--; }, 1000); },
        label() { const m = Math.floor(this.sisa / 60), s = String(this.sisa % 60).padStart(2, '0'); return m + ':' + s; } }"
     x-init="mulai()" x-on:kode-dikirim.window="sisa = $event.detail.detik ?? 60" class="text-center text-[13px] leading-5 text-muted">
    <template x-if="sisa > 0"><span>Kirim ulang kode dalam <span class="font-semibold text-ink" x-text="label()"></span></span></template>
    <template x-if="sisa <= 0">
        <button type="button" wire:click="{{ $aksi }}" wire:loading.attr="disabled" class="font-semibold text-brand hover:underline">Kirim ulang kode</button>
    </template>
</div>
