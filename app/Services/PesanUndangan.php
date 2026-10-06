<?php

namespace App\Services;

use App\Models\Pengaturan;
use App\Models\Tamu;
use App\Models\TemplatePesan;
use App\Models\Undangan;
use App\Models\User;
use App\Support\NomorWa;
use Illuminate\Support\Collection;

/**
 * Template pesan (bawaan + milik pengguna), salam, pengisian variabel, dan tautan WhatsApp (`wa.me`).
 * Tidak ada gateway: pengiriman = pengguna membuka tautan, lalu dicatat di `riwayat_kirim`.
 */
class PesanUndangan
{
    public const SAPAAN_UMUM = 'Bapak/Ibu/Saudara/i';

    public const MAKS_TEMPLATE = 30;

    public const MAKS_ISI = 2000;

    public const MAKS_NAMA = 60;

    public const MAKS_SUBJEK = 120;

    public const SARAN_WA = 1000;

    public const VARIABEL = ['{salam_pembuka}', '{nama}', '{judul}', '{mempelai}', '{link}', '{tanggal}', '{tempat}', '{salam_penutup}'];

    public const TEMPLATE_BAWAAN_UTAMA = 'b-formal';

    public const TEMPLATE_PENGINGAT = 'b-rsvp';

    /** @return array<string,array{nama:string,pendek:string,buka:string,tutup:string}> */
    public static function salam(): array
    {
        return [
            'umum' => ['nama' => 'Umum (netral)', 'pendek' => 'Umum', 'buka' => '', 'tutup' => ''],
            'islam' => ['nama' => 'Islam', 'pendek' => 'Islam', 'buka' => "Assalamu'alaikum Warahmatullahi Wabarakatuh,", 'tutup' => "Wassalamu'alaikum Warahmatullahi Wabarakatuh."],
            'kristen' => ['nama' => 'Kristen Protestan', 'pendek' => 'Kristen', 'buka' => 'Salam sejahtera dalam kasih Tuhan Yesus Kristus,', 'tutup' => 'Tuhan Yesus memberkati kita semua.'],
            'katolik' => ['nama' => 'Katolik', 'pendek' => 'Katolik', 'buka' => 'Salam damai Kristus,', 'tutup' => 'Semoga Tuhan memberkati kita semua.'],
            'hindu' => ['nama' => 'Hindu', 'pendek' => 'Hindu', 'buka' => 'Om Swastyastu,', 'tutup' => 'Om Shanti Shanti Shanti Om.'],
            'buddha' => ['nama' => 'Buddha', 'pendek' => 'Buddha', 'buka' => 'Namo Buddhaya,', 'tutup' => 'Semoga semua makhluk berbahagia. Sadhu, sadhu, sadhu.'],
            'konghucu' => ['nama' => 'Konghucu', 'pendek' => 'Konghucu', 'buka' => 'Salam Kebajikan,', 'tutup' => 'Wei De Dong Tian. Semoga Tian memberkati kita semua.'],
        ];
    }

    /** @return array<string,array{id:string,nama:string,kanal:string,subjek:?string,isi:string,bawaan:bool}> */
    public static function bawaan(): array
    {
        $daftar = [
            ['b-formal', 'Undangan Formal', "{salam_pembuka}\n\nKepada Yth. {nama},\n\nDengan penuh sukacita, kami mengundang Anda untuk hadir di pernikahan kami:\n\n{mempelai}\n{tanggal}\n{tempat}\n\nBuka undangan digital kami di sini:\n{link}\n\nMerupakan kehormatan bagi kami apabila Anda berkenan hadir dan memberikan doa restu.\n\n{salam_penutup}\n\nHormat kami,\n{mempelai}"],
            ['b-santai', 'Undangan Santai', "{salam_pembuka}\n\nHai {nama}, kami mau berbagi kabar bahagia. {mempelai} akan menikah dan kamu kami undang!\n\n{tanggal}\n{tempat}\n\nBuka undangannya di sini ya:\n{link}\n\nJangan lupa konfirmasi kehadiranmu lewat undangan. Ditunggu kehadirannya!\n\n{salam_penutup}"],
            ['b-rsvp', 'Pengingat RSVP', "{salam_pembuka}\n\nHalo {nama}, ini pengingat dari {mempelai}. Mohon konfirmasi kehadiran Anda lewat tautan berikut:\n{link}\n\nTerima kasih atas perhatiannya.\n\n{salam_penutup}"],
            ['b-terima', 'Ucapan Terima Kasih', "{salam_pembuka}\n\nHalo {nama}, terima kasih sudah hadir dan memberikan doa di hari bahagia kami. Kehadiran Anda sangat berarti bagi kami.\n\n{salam_penutup}\n\nSalam hangat,\n{mempelai}"],
        ];

        $hasil = [];
        foreach ($daftar as [$id, $nama, $isi]) {
            $hasil[$id] = ['id' => $id, 'nama' => $nama, 'kanal' => 'whatsapp', 'subjek' => null, 'isi' => $isi, 'bawaan' => true];
        }

        return $hasil;
    }

    /** Milik pengguna lebih dulu (terbaru di atas), lalu bawaan. Kunci = id ("u-12" atau "b-formal"). */
    public function daftar(User|int $user): Collection
    {
        $id = $user instanceof User ? $user->id : $user;
        $milik = TemplatePesan::where('user_id', $id)->orderByDesc('id')->get()->mapWithKeys(fn (TemplatePesan $t) => ['u-'.$t->id => [
            'id' => 'u-'.$t->id, 'nama' => $t->nama, 'kanal' => $t->kanal, 'subjek' => $t->subjek, 'isi' => $t->isi, 'bawaan' => false,
        ]]);

        return $milik->concat(self::bawaan())->keyBy('id');
    }

    /** @return array{template:string,salam:string} */
    public function pengaturan(User|int $user, ?Collection $daftar = null): array
    {
        $id = $user instanceof User ? $user->id : $user;
        $simpan = (array) Pengaturan::ambil('pesan_u'.$id, []);
        $daftar ??= $this->daftar($id);
        $templateId = (string) ($simpan['template'] ?? '');
        $salam = (string) ($simpan['salam'] ?? '');

        return [
            'template' => isset($daftar[$templateId]) && $daftar[$templateId]['kanal'] === 'whatsapp' ? $templateId : self::TEMPLATE_BAWAAN_UTAMA,
            'salam' => isset(self::salam()[$salam]) ? $salam : 'umum',
        ];
    }

    public function simpanPengaturan(User|int $user, ?string $template = null, ?string $salam = null): void
    {
        $id = $user instanceof User ? $user->id : $user;
        $sekarang = $this->pengaturan($id);
        Pengaturan::simpan('pesan_u'.$id, [
            'template' => $template ?? $sekarang['template'],
            'salam' => $salam ?? $sekarang['salam'],
        ]);
    }

    public static function linkUndangan(Undangan $u, ?string $kodeTamu = null): string
    {
        if (! $u->slug) {
            return '';
        }
        $url = route('undangan.publik', $u->slug);

        return $kodeTamu ? $url.'?to='.$kodeTamu : $url;
    }

    public static function tanggal(Undangan $u): string
    {
        return $u->tanggal_acara ? $u->tanggal_acara->copy()->locale('id')->translatedFormat('l, j F Y') : '';
    }

    /** Isi variabel. Pembersihan akhir merapikan baris kosong akibat variabel yang kosong. */
    public static function isi(string $teks, Undangan $u, string $nama, string $salam = 'umum', ?string $kodeTamu = null): string
    {
        $s = self::salam()[$salam] ?? self::salam()['umum'];
        $tukar = [
            '{salam_pembuka}' => $s['buka'],
            '{salam_penutup}' => $s['tutup'],
            '{mempelai}' => (string) $u->judul,
            '{judul}' => (string) $u->judul,
            '{nama}' => $nama,
            '{link}' => self::linkUndangan($u, $kodeTamu),
            '{tanggal}' => self::tanggal($u),
            '{tempat}' => (string) $u->lokasi_ringkas,
        ];
        // Satu lintasan agar isi variabel (mis. nama tamu berisi "{link}") tidak ikut diganti ulang.
        $hasil = preg_replace_callback('/\{(salam_pembuka|salam_penutup|mempelai|judul|nama|link|tanggal|tempat)\}/', fn (array $m) => $tukar[$m[0]], $teks) ?? $teks;
        $hasil = preg_replace('/[ \t]+(\r?\n)/', '$1', $hasil) ?? $hasil;
        $hasil = preg_replace('/(\r?\n){3,}/', "\n\n", $hasil) ?? $hasil;

        return trim($hasil);
    }

    /** Variabel tak dikenal di dalam kurung kurawal (mis. salah ketik) untuk peringatan di formulir. @return list<string> */
    public static function variabelAsing(string $teks): array
    {
        preg_match_all('/\{[a-z_]+\}/i', $teks, $m);

        return array_values(array_unique(array_diff($m[0], self::VARIABEL)));
    }

    /** Pesan untuk satu tamu: template khusus tamu bila ada, jika tidak template default pengguna. */
    public function untukTamu(Undangan $u, Tamu $tamu, Collection $daftar, array $pengaturan, string $jenis = 'undangan'): string
    {
        $templateId = $jenis === 'pengingat' ? self::TEMPLATE_PENGINGAT : ($tamu->template_pesan ?: $pengaturan['template']);
        $template = $daftar[$templateId] ?? null;
        if (! $template || $template['kanal'] !== 'whatsapp') {
            $template = $daftar[$pengaturan['template']] ?? self::bawaan()[self::TEMPLATE_BAWAAN_UTAMA];
        }
        $salam = isset(self::salam()[(string) $tamu->salam]) ? (string) $tamu->salam : $pengaturan['salam'];

        return self::isi($template['isi'], $u, $tamu->nama, $salam, $tamu->kode);
    }

    /** `wa.me` dengan nomor (62...) atau tanpa nomor (pengguna memilih kontak sendiri). */
    public static function urlWa(?string $hp, string $pesan): string
    {
        $inti = NomorWa::ringkas($hp);

        return 'https://wa.me/'.($inti !== '' ? '62'.$inti : '').'?text='.rawurlencode($pesan);
    }
}
