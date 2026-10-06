<div class="ed-label">Identitas undangan</div>
<div>
    <label for="s-judul" class="mb-1.5 block text-[13px] leading-[18px] font-bold">Nama undangan</label>
    <input id="s-judul" type="text" class="ed-fld" maxlength="120" wire:model.blur="judul" aria-invalid="{{ $galatJudul !== '' ? 'true' : 'false' }}">
    @if ($galatJudul !== '')<div class="galat mt-1" role="alert">{{ $galatJudul }}</div>@endif
</div>
<div>
    <label for="s-slug" class="mb-1.5 block text-[13px] leading-[18px] font-bold">Link undangan</label>
    <div class="flex items-center gap-1 rounded-[14px] border-2 border-[#e2e5e9] bg-[#f4f5f7] pl-3 focus-within:border-brand focus-within:bg-white">
        <span class="max-w-[110px] truncate text-[13px] text-[#666c76]">{{ parse_url((string) config('app.url'), PHP_URL_HOST) }}/</span>
        <input id="s-slug" type="text" class="h-10 min-w-0 flex-1 border-0 bg-transparent px-1 text-sm font-medium outline-none" maxlength="60" wire:model.blur="slug" @disabled($terbit)
               aria-invalid="{{ $galatSlug !== '' ? 'true' : 'false' }}" aria-describedby="s-slug-info">
    </div>
    @if ($galatSlug !== '')<div class="galat mt-1" role="alert">{{ $galatSlug }}</div>@endif
    <div id="s-slug-info" class="hint mt-1">
        @if ($terbit) Link sudah aktif. Untuk mengganti, buka Terbitkan ulang; link lama berhenti bekerja.
        @else Huruf kecil, angka, dan tanda hubung (3 sampai 60 karakter). @endif
    </div>
</div>
<div class="flex items-center justify-between rounded-2xl bg-[#f4f5f7] px-3 py-2.5">
    <span class="text-[13px] font-bold">Jenis acara</span>
    <span class="text-[13px] text-[#454b55]">{{ $u->jenis }}</span>
</div>
<p class="m-0 text-xs leading-[17px] text-[#666c76]">Perubahan di sini langsung terlihat pada judul editor dan link yang disalin.</p>
