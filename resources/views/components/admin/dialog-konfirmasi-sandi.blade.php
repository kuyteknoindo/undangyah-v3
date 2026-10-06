{{--
    Dialog konfirmasi kata sandi admin: SATU tampilan untuk semua aksi sensitif. Dipakai bersama trait
    App\Livewire\Concerns\MintaKonfirmasiSandi, mis.:
    <x-admin.dialog-konfirmasi-sandi :label="$tertundaLabel" :pesan="$tertundaPesan" />
--}}
@props(['label' => '', 'pesan' => ''])
<x-dialog wire:model="tampilKonfirmasiSandi" judul="Konfirmasi kata sandi" sub="{{ $label }}" lebar="max-w-[460px]">
    <div class="flex flex-col gap-3" wire:key="konfirmasi-sandi-isi">
        <p class="m-0 text-sm leading-6 text-gray-700">{{ $pesan }}</p>
        <x-field id="sandi-admin" label="Kata sandi" ikon="gembok" :sandi="true" autocomplete="current-password" wire:model="sandiAdmin"
                 wire:keydown.enter="konfirmasiSandi" :galat="$errors->first('sandiAdmin')" />
    </div>
    <x-slot:footer>
        <button type="button" class="btn btn-secondary" wire:click="batalKonfirmasiSandi">Batal</button>
        <button type="button" class="btn btn-primary" wire:click="konfirmasiSandi" wire:loading.attr="disabled" wire:target="konfirmasiSandi"><x-ikon nama="gembok" :ukuran="16" /> Konfirmasi</button>
    </x-slot:footer>
</x-dialog>
