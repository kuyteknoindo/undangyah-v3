{{-- Pemberitahuan singkat. Dari Livewire: $this->dispatch('toast', pesan: 'Tersimpan', tipe: 'sukses'|'galat'|'info'). --}}
<div x-data="{
        daftar: [], n: 0,
        tambah(d) { const id = ++this.n; this.daftar.push({ id, pesan: d.pesan ?? '', tipe: d.tipe ?? 'info' }); setTimeout(() => this.hapus(id), d.lama ?? 4000); },
        hapus(id) { this.daftar = this.daftar.filter(t => t.id !== id); },
     }" x-on:toast.window="tambah($event.detail)"
     class="pointer-events-none fixed right-4 bottom-4 z-[90] flex flex-col gap-2 sm:right-6 sm:bottom-6" aria-live="polite" aria-atomic="false">
    <template x-for="t in daftar" x-bind:key="t.id">
        <div role="status" class="pointer-events-auto flex max-w-sm items-center gap-2.5 rounded-xl bg-gray-900 px-4 py-3 text-sm leading-5 font-medium text-white shadow-[0_12px_24px_rgba(16,24,40,0.25)]" x-transition>
            <span class="size-2 shrink-0 rounded-full" x-bind:class="{ 'bg-green-400': t.tipe === 'sukses', 'bg-red-400': t.tipe === 'galat', 'bg-blue-300': t.tipe === 'info' }"></span>
            <span x-text="t.pesan"></span>
            <button type="button" class="-mr-1 ml-1 text-white/70 hover:text-white" x-on:click="hapus(t.id)" aria-label="Tutup">&times;</button>
        </div>
    </template>
</div>
@if (session('toast'))
    <div x-data x-init="$nextTick(() => window.dispatchEvent(new CustomEvent('toast', { detail: @js(session('toast')) })))"></div>
@endif
