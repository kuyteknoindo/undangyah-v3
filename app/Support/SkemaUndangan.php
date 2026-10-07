<?php

namespace App\Support;

/**
 * Skema isian undangan, diekstrak dari desain desktop (EditUndanganDesktop.dc.html) ke
 * resources/skema/editor-undangan.json. Satu sumber untuk editor, renderer, validasi, dan tes.
 *
 * - bidang()   : 125 kunci tetap => {g: grup/bagian, label, v: nilai awal, kind: text|time|date|url|select|switch,
 *                rows, opt (opsional), canvas, hint, opts (pilihan select), + gaya kanvas: fs w ta c ff lh ls up ex}
 * - templat()  : daftar berulang (ev acara, ls love story, rd rundown, kd rekening, tp/tw turut mengundang, ak sosial, wr warna)
 *                => {g, min, f: [[kunci, label, awal, opsi], ...]}; nilai tersimpan di isi sebagai "{tpl}{id}_{kunci}" (mis. ev1_nama)
 *                dan urutan id di isi['_daftar'][tpl] = [1,2,...]
 * - awalDaftar(): isi awal tiap daftar; bagian(): definisi 18 bagian + urutan awal; daftarBagian(): bagian yang punya daftar berulang.
 */
class SkemaUndangan
{
    private static ?array $data = null;

    private static function muat(): array
    {
        return self::$data ??= json_decode((string) file_get_contents(resource_path('skema/editor-undangan.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return array<string,array<string,mixed>> */
    public static function bidang(): array
    {
        return self::muat()['M'];
    }

    /** @return array<string,array<string,mixed>> */
    public static function bidangBagian(string $bagian): array
    {
        return array_filter(self::bidang(), fn ($b) => $b['g'] === $bagian);
    }

    /** Nilai awal semua bidang tetap: kunci => teks. */
    public static function nilaiAwal(): array
    {
        return array_map(fn ($b) => $b['v'], self::bidang());
    }

    /** @return array<string,array<string,mixed>> */
    public static function templat(): array
    {
        return self::muat()['TPL'];
    }

    /** @return array<string,list<array{0:int,1:list<mixed>}>> */
    public static function awalDaftar(): array
    {
        return self::muat()['INIT'];
    }

    /** @return array<string,array{name:string,req?:bool,dark?:bool}> */
    public static function bagian(): array
    {
        return self::muat()['SECDEF'];
    }

    /** Urutan awal bagian (sampul di awal, penutup di akhir). @return list<string> */
    public static function urutanAwal(): array
    {
        return self::muat()['INIT_ORDER'];
    }

    /** @return list<string> bagian grup "Konten Undangan" */
    public static function konten(): array
    {
        return self::muat()['KONTEN'];
    }

    /** @return list<string> bagian grup "Fitur Tambahan" */
    public static function fitur(): array
    {
        return self::muat()['FITUR'];
    }

    /** Nama item daftar berulang untuk label (tpl => "acara", "cerita", ...). @return array<string,string> */
    public static function namaItem(): array
    {
        return self::muat()['ITEM_NAME'];
    }

    /** @return array<string,list<array{t:string,item:string,add:string,head?:string}>> */
    public static function daftarBagian(): array
    {
        return self::muat()['SL'];
    }

    /** Isi awal lengkap (bidang tetap + isian awal tiap item daftar + _daftar). */
    public static function isiAwal(): array
    {
        $isi = self::nilaiAwal();
        foreach (self::awalDaftar() as $tpl => $baris) {
            foreach ($baris as [$id, $nilai]) {
                foreach (self::templat()[$tpl]['f'] as $i => $f) {
                    $isi["{$tpl}{$id}_{$f[0]}"] = $nilai[$i] ?? '';
                }
                $isi['_daftar'][$tpl][] = $id;
            }
        }
        $isi['_daftar']['gl'] = [1, 2, 3, 4, 5, 6];

        return $isi;
    }
}
