@props(['id' => null])
<div @if ($id) id="{{ $id }}" @endif role="alert" class="au-galat"><x-ikon nama="peringatan" :ukuran="14" /> {{ $slot }}</div>
