<?php

namespace App\Livewire\Concerns;

use App\Models\Tema;
use App\Models\Undangan;
use App\Services\IsiUndangan;
use App\Services\KatalogLagu;
use App\Services\KodeQr;
use App\Services\KuotaUndangan;
use App\Services\PenerbitUndangan;
use App\Support\Editor\Papan;
use App\Support\SkemaUndangan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;

/**
 * Model tampilan editor: port dari renderVals papan EditUndanganDesktop. Menyusun nilai yang dibutuhkan kanvas, panel kiri/kanan,
 * dan dialog dari state komponen (read-only, tidak mengubah state). Setiap pengikat peristiwa berupa string atribut
 * (`wire:click="..."`, `x-on:...`) yang sudah di-escape; aksi sebenarnya selalu divalidasi ulang di server.
 *
 * Penamaan kunci sengaja sama dengan papan (fld, ph, ss, tg, sw, prop, secProp, groups, ...) supaya markup hasil konversi papan
 * dapat dipakai apa adanya.
 */
trait TampilanEditor
{
    // ---------------------------------------------------------------- penulis atribut

    private function wc(string $metode, mixed ...$arg): string
    {
        return 'wire:click="'.e($metode.'('.implode(', ', array_map(fn ($a) => Papan::jsArg($a), $arg)).')').'"';
    }

    private function xc(string $js, string $acara = 'click'): string
    {
        return 'x-on:'.$acara.'="'.e($js).'"';
    }

    private function jsA(string $f, mixed ...$arg): string
    {
        return $f.'('.implode(', ', array_map(fn ($a) => Papan::jsArg($a), $arg)).')';
    }

    // ---------------------------------------------------------------- model tampilan

    /**
     * @param  array<string,mixed>  $isiPenuh  hasil susunIsi() (termasuk _foto/_latarTetap)
     * @return array<string,mixed>
     */
    private function vm(Undangan $u, ?Tema $tema, array $isiPenuh): array
    {
        $th = Papan::tokenTema($tema);
        $iso = app(IsiUndangan::class);
        $d = $this->isi;
        $st = $this->gaya;
        $hid = array_fill_keys($this->sembunyi, true);
        $lists = $this->daftar;
        $order = $this->bagian['urutan'];
        $hidSec = array_fill_keys($this->bagian['sembunyi'], true);
        $secDef = SkemaUndangan::bagian();
        $itemNama = SkemaUndangan::namaItem();
        $tpl = SkemaUndangan::templat();
        $aktif = $this->bagianAktif;
        $terpilih = $this->terpilih;
        // sel papan: "sec:<id>" | "photo:<slot>" | kunci bidang
        $sel = $terpilih === null ? 'sec:'.$aktif : (str_starts_with($terpilih, 'foto:') ? 'photo:'.substr($terpilih, 5) : $terpilih);
        $hf = (string) $th['hf'];
        $gset = $this->undangan()->isi['_galeri'] ?? [];
        $gset = ['layout' => in_array($gset['layout'] ?? '', array_keys(self::LAYOUT_GALERI), true) ? $gset['layout'] : 'grid', 'video' => is_string($gset['video'] ?? null) ? trim($gset['video']) : ''];

        $tok = fn (string $c) => match ($c) {
            'ink' => $th['ink'], 'mut' => $th['mut'], 'acc' => $th['acc'], 'onA' => $th['onA'], 'onAcc' => $th['onAcc'], 'btnTx' => $th['btnTx'],
            default => $c,
        };
        $dark = fn (string $g) => ! empty($secDef[$g]['dark']) && $th['coverDark'];
        $val = fn (string $k) => trim((string) ($d[$k] ?? ''));

        $sty = function (string $k) use ($st, $sel, $hid, $th, $hf, $tok, $dark): string {
            $m = Papan::meta($k);
            $o = $st[$k] ?? [];
            $fs = isset($o['fs']) ? (int) $o['fs'] : (int) $m['fs'];
            $bold = ! array_key_exists('b', $o) ? (int) $m['w'] : ($o['b'] ? 700 : 400);
            $ta = $o['ta'] ?? $m['ta'];
            $col = $o['c'] ?? $tok((string) $m['c']);
            $t = 'position:relative;width:100%;box-sizing:border-box;border:0;background:transparent;margin:0;padding:3px 6px;min-width:0;'
                .'font-family:'.($m['ff'] === 'serif' ? $hf : Papan::SANS).';font-size:'.$fs.'px;line-height:'.(int) round($fs * (float) $m['lh']).'px;font-weight:'.$bold.';'
                .'letter-spacing:'.$m['ls'].';text-align:'.$ta.';color:'.$col.';'.(! empty($m['up']) ? 'text-transform:uppercase;' : '')
                .(! empty($m['rows']) ? 'resize:none;overflow:hidden;display:block;' : '');
            $t .= strtr((string) $m['ex'], ['%tint%' => $th['tint'], '%acc%' => $th['acc'], '%dep%' => $th['dep'], '%btn%' => $th['btn']]);
            if ($sel === $k) {
                $t .= 'outline:2px solid '.($dark((string) $m['g']) ? '#FFFFFF' : '#1D4ED8').';outline-offset:1px;';
            }
            if (isset($hid[$k])) {
                $t .= 'display:none;';
            }

            return $t;
        };

        $mk = function (string $k, ?int $n = null) use ($d, $sty): array {
            $m = Papan::meta($k);

            return [
                'id' => 'e-'.$k, 'val' => (string) ($d[$k] ?? ''), 'lab' => $m['label'].($n ? ' '.$n : ''), 'ph' => $m['label'], 'rows' => (int) $m['rows'],
                'sty' => $sty($k),
                'chg' => 'data-k="'.e($k).'" '.$this->xc('kotor($el)', 'input').' '.$this->xc('kirim($el, '.Papan::jsArg($k).')', 'change'),
                'foc' => $this->xc($this->jsA('pilih', $k, 0), 'focus'),
            ];
        };
        $fld = [];
        foreach (SkemaUndangan::bidang() as $k => $b) {
            if (($b['canvas'] ?? true) !== false) {
                $fld[$k] = $mk($k);
            }
        }

        // ---------- foto
        $foto = $this->fotoTersimpan();
        $latar = $this->bgf();
        $medias = $this->mediaDirujuk($foto, is_array($this->undangan()->isi['_latarTetap'] ?? null) ? $this->undangan()->isi['_latarTetap'] : []);
        $infoSlot = function (string $slot) use ($foto, $medias): array {
            $f = $foto[$slot] ?? null;

            return $this->infoFoto($f, is_array($f) && isset($f['mid']) ? $medias->get((int) $f['mid']) : null);
        };
        $labelSlot = fn (string $slot) => self::SLOT_TETAP[$slot] ?? 'Foto galeri '.(((int) array_search((int) substr($slot, 1), $lists['gl'] ?? [], true)) + 1);

        $mkPh = function (string $slot, string $kind) use ($infoSlot, $labelSlot, $sel, $th, $gset, $lists): array {
            $label = $labelSlot($slot);
            $li = $infoSlot($slot);
            $has = $li['ada'];
            $on = $sel === 'photo:'.$slot;
            $onDark = $kind === 'cover' && $th['coverDark'];
            $isGal = $kind === 'gallery';
            $lay = $gset['layout'];
            $ci = $isGal ? (int) array_search((int) substr($slot, 1), $lists['gl'], true) : 0;
            $cr = $li['crop'];
            $rt = $cr ? Papan::kunciRasio($cr['ratio']) : null;
            $rad = '14px';
            $wrap = 'position:relative;width:100%;';
            $asp = '';
            if ($kind === 'cover') {
                $rad = '120px 120px 24px 24px';
                $wrap = 'position:relative;flex:none;width:200px;height:250px;margin-bottom:10px;';
            } elseif ($kind === 'person') {
                $rad = '56px';
                $wrap = 'position:relative;flex:none;width:112px;height:112px;margin-bottom:10px;';
            } elseif ($kind === 'round') {
                $rad = '70px';
                $wrap = 'position:relative;flex:none;width:140px;height:140px;margin-bottom:10px;';
            } elseif ($kind === 'banner') {
                $rad = '0';
                $asp = 'aspect-ratio:4 / 3;';
            } elseif ($isGal) {
                if ($lay === 'masonry') {
                    $wrap = 'position:relative;width:100%;break-inside:avoid;margin-bottom:8px;';
                    $asp = 'aspect-ratio:'.($rt ?: Papan::MASONRY[$ci % 6]).' / 1;';
                } elseif ($lay === 'slider') {
                    $wrap = 'position:relative;flex:none;width:74%;scroll-snap-align:center;';
                    $asp = 'aspect-ratio:'.($rt ?: 0.8).' / 1;';
                } else {
                    $asp = 'aspect-ratio:1 / 1;';
                }
            }
            $fixedH = in_array($kind, ['cover', 'person', 'round'], true);
            $bg = $has ? ($li['warna'] ?? $th['tint']) : ($onDark ? 'rgba(255,255,255,0.14)' : $th['tint']);
            $btn = 'position:relative;width:100%;'.($fixedH ? 'height:100%;' : $asp).'box-sizing:border-box;overflow:hidden;padding:0;border-radius:'.$rad.';background:'.$bg.';'
                .($has ? 'border:0;color:#454B55;' : ($onDark ? 'border:2px dashed rgba(255,255,255,0.6);color:#FFFFFF;' : 'border:2px dashed '.$th['acc'].'66;color:'.$th['acc'].';'))
                .($on ? 'outline:3px solid '.($onDark ? '#FFFFFF' : '#1D4ED8').';outline-offset:'.($kind === 'banner' ? '-3px' : '3px').';' : '');
            $bar = 'position:absolute;display:flex;justify-content:center;align-items:center;gap:6px;z-index:2;'
                .($kind === 'cover' ? 'left:0;right:0;bottom:14px;' : (in_array($kind, ['person', 'round'], true) ? 'left:-24px;right:-24px;bottom:-8px;' : ($kind === 'banner' ? 'left:0;right:0;bottom:14px;' : 'left:2px;right:2px;bottom:6px;gap:4px;')));
            $num = $isGal ? (int) substr($slot, 1) : 0;

            return [
                'id' => $slot, 'dom' => 'ph-'.$slot, 'label' => $label, 'name' => $has ? $li['nama'] : '', 'has' => $has, 'empty' => ! $has, 'hasImg' => $has, 'ghost' => false,
                'src' => $has ? $li['src'] : '', 'color' => $has ? ($li['warna'] ?? $th['tint']) : $th['tint'], 'imgSty' => Papan::gayaGambar($cr),
                'sel' => $on, 'wrap' => $wrap, 'btn' => $btn, 'bar' => $bar, 'isGal' => $isGal, 'libId' => $li['ref'] ?? '',
                'aria' => $label.($has ? ' — '.$li['nama'].', klik untuk memilih' : ' — kosong, klik untuk menambah'),
                'actShort' => $has ? 'Ganti' : 'Pilih',
                'rmLabel' => $isGal ? 'Hapus '.mb_strtolower($label).' dari galeri' : 'Lepas '.mb_strtolower($label),
                'pick' => $this->xc($this->jsA('pilihFoto', $slot, false)), 'change' => $this->wc('bukaFoto', $slot),
                'remove' => $isGal ? $this->wc('hapusSlotGaleri', $num) : $this->wc('hapusFoto', $slot),
                'clear' => $this->wc('hapusFoto', $slot), 'delSlot' => $this->wc('hapusSlotGaleri', $num), 'num' => $num,
                'bisaCrop' => $has && $slot !== 'share' && ($li['ref'] ?? null) !== null,
            ];
        };

        $persons = [
            ['ini' => mb_strtoupper(mb_substr((string) (($d['priaP'] ?? '') !== '' ? $d['priaP'] : '?'), 0, 1)), 'nama' => $fld['priaNama'], 'ortu' => $fld['priaOrtu'], 'ig' => $fld['priaIg'], 'photo' => $mkPh('pria', 'person')],
            ['ini' => mb_strtoupper(mb_substr((string) (($d['wanitaP'] ?? '') !== '' ? $d['wanitaP'] : '?'), 0, 1)), 'nama' => $fld['wanitaNama'], 'ortu' => $fld['wanitaOrtu'], 'ig' => $fld['wanitaIg'], 'photo' => $mkPh('wanita', 'person')],
        ];

        // ---------- item berulang di kanvas
        $build = function (string $t, callable $fn) use ($lists, $tpl, $itemNama, $mk): array {
            $hasil = [];
            foreach ($lists[$t] ?? [] as $i => $id) {
                $K = fn (string $f) => $t.$id.'_'.$f;
                $n = $i + 1;
                $F = fn (string $f) => $mk($K($f), $n);
                $hasil[] = array_merge([
                    'id' => $id, 'num' => $n, 'canRemove' => count($lists[$t]) > $tpl[$t]['min'], 'remove' => $this->wc('hapusItem', $t, $id),
                    'dup' => $this->wc('gandakanItem', $t, $id), 'dupLabel' => 'Gandakan '.$itemNama[$t].' '.$n,
                ], $fn($F, $K, $n, $i));
            }

            return $hasil;
        };

        $events = $build('ev', function ($F, $K, $n) use ($val, $hid, $d) {
            $link = $val($K('maps'));
            $ok = Papan::isUrl($link);

            return [
                'nama' => $F('nama'), 'tgl' => $F('tgl'), 'jam' => $F('jam'), 'lok' => $F('lokasi'), 'almt' => $F('alamat'), 'mapsBtn' => $F('mapsBtn'),
                'mapsText' => $ok ? Papan::urlPendek($link) : 'Tambah link Google Maps',
                'mapsSty' => ($ok ? 'background:#D7FFB8;color:#3C7A00;' : 'background:#FFF4CC;color:#B25E00;').(isset($hid[$K('mapsBtn')]) ? 'display:none;' : ''),
                'mapsAria' => $ok ? 'Link Google Maps acara '.$n.': '.$link.'. Klik untuk mengubah.' : 'Tambah link Google Maps untuk acara '.$n,
                'mapsPick' => $this->xc($this->jsA('pilih', $K('maps'), 3)), 'rmLabel' => 'Hapus acara '.$n,
            ];
        });
        $stories = $build('ls', fn ($F, $K, $n, $i) => ['y' => $F('y'), 't' => $F('t'), 'b' => $F('b'), 'lineSty' => $i === count($lists['ls']) - 1 ? 'display:none;' : '', 'rmLabel' => 'Hapus cerita '.$n]);
        $rundown = $build('rd', fn ($F, $K, $n, $i) => ['t' => $F('t'), 'l' => $F('l'), 'lineSty' => $i === count($lists['rd']) - 1 ? '' : 'border-bottom:2px solid #E2E5E9;', 'rmLabel' => 'Hapus sesi '.$n]);
        $gifts = $build('kd', fn ($F, $K, $n) => ['bank' => $F('bank'), 'no' => $F('no'), 'atas' => $F('atas'), 'rmLabel' => 'Hapus rekening '.$n]);
        $famP = $build('tp', fn ($F, $K, $n) => ['nama' => $F('nama'), 'ket' => $F('ket'), 'rmLabel' => 'Hapus nama keluarga pria '.$n]);
        $famW = $build('tw', fn ($F, $K, $n) => ['nama' => $F('nama'), 'ket' => $F('ket'), 'rmLabel' => 'Hapus nama keluarga wanita '.$n]);
        $platSoc = array_column(Papan::PLAT_SOC, 1, 0);
        $accts = $build('ak', fn ($F, $K, $n) => ['user' => $F('username'), 'plat' => $platSoc[$val($K('platform'))] ?? 'Platform', 'pickPlat' => $this->xc($this->jsA('pilih', $K('platform'), 3)), 'rmLabel' => 'Hapus akun '.$n]);
        $colors = $build('wr', function ($F, $K, $n) use ($val) {
            $hx = $val($K('hex'));
            $okc = (bool) preg_match('/^#[0-9a-f]{6}$/i', $hx);

            return [
                'nama' => $F('nama'),
                'dotSty' => 'flex:none;width:56px;height:56px;border-radius:28px;box-sizing:border-box;border:2px solid #E2E5E9;background:'.($okc ? $hx : '#CCCCCC').';',
                'dotLabel' => 'Ubah kode warna '.$n.' ('.($okc ? $hx : 'belum valid').')', 'pickDot' => $this->xc($this->jsA('pilih', $K('hex'), 3)), 'rmLabel' => 'Hapus warna '.$n,
            ];
        });

        // ---------- bagian khusus
        $lagu = null;
        foreach (KatalogLagu::opsiEditor() as [$kode, $label]) {
            if ($kode !== '' && $kode === $val('musLagu')) {
                $bagianLabel = explode(' · ', $label);
                $lagu = [$bagianLabel[0], implode(' · ', array_slice($bagianLabel, 1))];
            }
        }
        $autoOn = $val('musAuto') === '1';
        $mu = [
            'title' => $lagu ? $lagu[0] : 'Belum memilih lagu', 'meta' => $lagu ? $lagu[1] : 'Pilih lagu atau tempel link YouTube',
            'autoOn' => $autoOn, 'autoText' => 'Putar otomatis: '.($autoOn ? 'Aktif' : 'Mati'), 'autoSty' => $autoOn ? 'background:#D7FFB8;color:#3C7A00;' : 'background:#ECEEF1;color:#666C76;',
            'toggleAuto' => $this->wc('setBidang', 'musAuto', $autoOn ? '0' : '1'), 'pickLagu' => $this->xc($this->jsA('pilih', 'musLagu', 3)),
            'hasLink' => Papan::isUrl($d['musLink'] ?? ''), 'linkText' => Papan::urlPendek($d['musLink'] ?? ''), 'pickLink' => $this->xc($this->jsA('pilih', 'musLink', 3)),
        ];
        $lvOk = Papan::isUrl($d['lvLink'] ?? '');
        $platLs = array_column(Papan::PLAT_LS, 1, 0);
        $platName = $platLs[$val('lvPlat')] ?? 'Pilih platform';
        $lvWhen = implode(' · ', array_filter([Papan::fmtTanggal($val('lvTgl')), Papan::fmtJam($val('lvMulai')) !== '' ? Papan::fmtJam($val('lvMulai')).' WIB' : '']));
        $lv = [
            'plat' => $platName, 'when' => $lvWhen ?: 'Atur tanggal dan jam siaran', 'btnOn' => $val('lvBtn') === '1', 'btnOff' => $val('lvBtn') !== '1',
            'btnText' => 'Tonton di '.$platName, 'ok' => $lvOk, 'no' => ! $lvOk, 'href' => $lvOk ? $val('lvLink') : '#',
            'pickLink' => $this->xc($this->jsA('pilih', 'lvLink', 3)), 'pickWhen' => $this->xc($this->jsA('pilih', 'lvTgl', 3)),
            'pickPlat' => $this->xc($this->jsA('pilih', 'lvPlat', 3)), 'pickBtn' => $this->xc($this->jsA('pilih', 'lvBtn', 3)),
        ];
        $gcHref = Papan::gcalUrl($d);
        $gcOn = $val('gcAktif') === '1';
        $gcWhen = implode(' · ', array_filter([Papan::fmtTanggal($val('gcTgl')), (Papan::fmtJam($val('gcMulai')) !== '' && Papan::fmtJam($val('gcSelesai')) !== '') ? Papan::fmtJam($val('gcMulai')).' – '.Papan::fmtJam($val('gcSelesai')).' WIB' : '']));
        $rsv = [
            'labelNama' => $val('rsLabelNama'), 'labelWa' => $val('rsLabelWa'), 'labelHadir' => $val('rsLabelHadir'), 'labelJumlah' => $val('rsLabelJumlah'), 'labelPesan' => $val('rsLabelPesan'),
            'phNama' => $val('rsPhNama'), 'phWa' => $val('rsPhWa'), 'phPesan' => $val('rsPhPesan'), 'btn' => $val('rsBtn') ?: 'Kirim', 'wsBtn' => $val('wsBtn') ?: 'Kirim',
            'qrLabel' => $val('qrLabel'), 'hasQrLabel' => $val('qrLabel') !== '', 'tmNama' => $val('tmNama') ?: 'Nama tamu', 'tmAlamat' => $val('tmAlamat'), 'hasAlamat' => $val('tmAlamat') !== '',
            'tmKode' => $val('tmKode'), 'hasKode' => $val('tmKode') !== '', 'tmSuffix' => $val('tmSuffix'), 'hasSuffix' => $val('tmSuffix') !== '', 'tmPre' => $val('sapaan'),
            'catatan' => $val('rsCatatan'), 'hasCatatan' => $val('rsCatatan') !== '',
        ];
        $gc = [
            'when' => $gcWhen ?: 'Atur tanggal dan jam acara', 'on' => $gcOn, 'off' => ! $gcOn, 'linkOk' => $gcOn && $gcHref !== '', 'linkNo' => $gcOn && $gcHref === '', 'href' => $gcHref ?: '#',
            'pickWhen' => $this->xc($this->jsA('pilih', 'gcTgl', 3)), 'pickAktif' => $this->xc($this->jsA('pilih', 'gcAktif', 3)),
        ];
        $sc = ['hashOn' => $val('scHashOn') === '1', 'noAcct' => $accts === []];

        // ---------- bagian & urutan
        $activeSec = $aktif;
        $ss = [];
        $tg = [];
        $sw = [];
        foreach ($order as $i => $id) {
            $off = isset($hidSec[$id]);
            $nm = $secDef[$id]['name'];
            $sw[$id] = [
                'toggle' => $this->wc('toggleBagian', $id), 'track' => $off ? '#9AA0A9' : '#58CC02', 'knob' => $off ? '3px' : '17px', 'on' => ! $off, 'name' => $nm,
                'aria' => ($off ? 'Tampilkan bagian ' : 'Sembunyikan bagian ').$nm, 'pillSty' => $sel === 'sec:'.$id ? 'opacity:1;' : '',
                'barSty' => 'order:'.$i.';'.($off ? '' : 'display:none;'),
            ];
            $ss[$id] = 'position:relative;order:'.$i.';'.($off ? 'display:none!important;' : '').($sel === 'sec:'.$id ? 'outline:3px solid #1D4ED8;outline-offset:-3px;' : '');
            $tg[$id] = ['pick' => $this->xc($this->jsA('pilihBagianKe', $id)), 'sty' => $sel === 'sec:'.$id ? 'opacity:1;' : ''];
        }
        $phCount = fn (string $g) => match ($g) {
            'sampul' => 1, 'foto' => 3, 'mempelai' => 2, 'social' => 1, 'gallery' => count($lists['gl']), default => 0,
        };
        $photoHas = fn (string $slot) => $infoSlot($slot)['ada'];

        $status = fn (string $id) => $iso->status($id, $isiPenuh, $lists, $this->batasGaleri());
        $mkRow = function (string $id, int $pos, int $total, bool $movable) use ($status, $secDef, $hidSec, $activeSec): array {
            $sd = $status($id);
            $def = $secDef[$id];
            $hiddenSec = isset($hidSec[$id]);
            $text = $sd['done'] ? (! empty($def['req']) ? 'Lengkap' : 'Terisi') : ($sd['any'] ? 'Belum lengkap' : 'Belum diisi');

            return [
                'id' => $id, 'name' => $def['name'], 'iconD' => Papan::IKON_BAGIAN[$id], 'badge' => empty($def['req']) && $id !== 'sampul' && $id !== 'penutup', 'movable' => $movable, 'pos' => $pos, 'total' => $total,
                'text' => $text.($hiddenSec ? ' · disembunyikan' : ''), 'textColor' => $sd['done'] ? '#3C7A00' : ($sd['any'] ? '#B25E00' : '#666C76'),
                'nameColor' => $activeSec === $id ? '#1D4ED8' : '#262A31', 'iconBg' => $sd['done'] ? '#D7FFB8' : '#F4F5F7', 'done' => $sd['done'], 'notDone' => ! $sd['done'],
                'rowSty' => $hiddenSec ? 'opacity:0.6;' : '',
                'gripLabel' => 'Geser urutan '.$def['name'].', posisi '.$pos.' dari '.$total.'. Pakai panah atas atau bawah untuk memindahkan.',
                'pick' => $this->xc($this->jsA('pilihBagianKe', $id)),
                'gripDown' => $this->xc('gripDown($event, '.Papan::jsArg($id).')', 'pointerdown'), 'gripMove' => $this->xc('gripMove($event)', 'pointermove'),
                'gripUp' => $this->xc('gripUp()', 'pointerup').' '.$this->xc('gripUp()', 'pointercancel'), 'gripKey' => $this->xc('gripKey($event, '.Papan::jsArg($id).')', 'keydown'),
            ];
        };
        $inGroup = fn (string $g) => array_values(array_filter($order, fn ($x) => in_array($x, $g === 'konten' ? SkemaUndangan::konten() : SkemaUndangan::fitur(), true)));
        $kontenIds = $inGroup('konten');
        $fiturIds = $inGroup('fitur');
        $kontenRows = [];
        foreach ($kontenIds as $i => $id) {
            $kontenRows[] = $mkRow($id, $i + 1, count($kontenIds), true);
        }
        $fiturRows = [];
        foreach ($fiturIds as $i => $id) {
            $fiturRows[] = $mkRow($id, $i + 1, count($fiturIds), true);
        }
        $pinRows = [$mkRow('sampul', 1, 1, false), $mkRow('penutup', 1, 1, false)];

        // ---------- properti (gaya teks dikendalikan Alpine dari data atribut elemen)
        $sid = $activeSec;
        $hiddenS = isset($hidSec[$sid]);
        $nP = $phCount($sid);
        $gm = in_array($sid, SkemaUndangan::konten(), true) ? $kontenIds : (in_array($sid, SkemaUndangan::fitur(), true) ? $fiturIds : []);
        $si = array_search($sid, $gm, true);
        $totalTeks = count($iso->kunciBagian($sid, $lists));
        $secProp = [
            'name' => $secDef[$sid]['name'] ?? '', 'iconD' => Papan::IKON_BAGIAN[$sid] ?? '',
            'sub' => $totalTeks.' teks'.($nP ? ' · '.$nP.' foto' : '').($hiddenS ? ' · disembunyikan' : ''),
            'toggleHide' => $this->wc('toggleBagian', $sid), 'hidden' => $hiddenS, 'shown' => ! $hiddenS,
            'hideDis' => $sid === 'sampul', 'hideShort' => $hiddenS ? 'Tampilkan' : 'Sembunyi', 'hideSty' => $hiddenS ? 'background:#FFF4CC;color:#B25E00;border-color:#FFF4CC;' : '',
            'hideLabel' => ($hiddenS ? 'Tampilkan bagian ' : 'Sembunyikan bagian ').($secDef[$sid]['name'] ?? ''),
            'up' => $this->wc('geserBagian', $sid, -1), 'down' => $this->wc('geserBagian', $sid, 1),
            'upDis' => $si === false || $si <= 0, 'downDis' => $si === false || $si === count($gm) - 1,
        ];

        // baris foto
        $hasPhotos = in_array($sid, ['sampul', 'foto', 'mempelai', 'social', 'gallery'], true);
        $rowSlots = match ($sid) {
            'sampul' => ['cover'], 'foto' => ['cover', 'latar', 'penutup'], 'mempelai' => ['pria', 'wanita'], 'social' => ['share'],
            'gallery' => array_map(fn ($i) => 'g'.$i, $lists['gl']), default => [],
        };
        $photoRows = [];
        foreach ($rowSlots as $i => $slot) {
            $ph = $mkPh($slot, 'gallery');
            $isGal = $ph['isGal'];
            $photoRows[] = [
                'id' => $slot, 'label' => $isGal ? 'Foto '.($i + 1) : $ph['label'], 'name' => $ph['name'], 'has' => $ph['has'], 'empty' => $ph['empty'], 'hasImg' => $ph['hasImg'], 'ghost' => false,
                'src' => $ph['src'], 'color' => $ph['color'], 'imgSty' => 'position:absolute;top:0;left:0;width:100%;height:100%;object-fit:cover;display:block;',
                'sel' => $ph['sel'], 'isGal' => $isGal, 'showOps' => $ph['sel'] || ! $isGal, 'showGalMove' => $isGal,
                'status' => $ph['has'] ? $ph['name'] : 'Belum ada foto', 'changeText' => $ph['has'] ? 'Ganti' : 'Pilih',
                'cardSty' => $ph['sel'] ? 'border:2px solid #1D4ED8;background:#F3F6FF;' : 'border:2px solid #E2E5E9;background:#FFFFFF;',
                'noUp' => ! ($isGal && $i > 0), 'noDown' => ! ($isGal && $i < count($rowSlots) - 1),
                'up' => $this->wc('geserFotoGaleri', $ph['num'], -1), 'down' => $this->wc('geserFotoGaleri', $ph['num'], 1),
                'upLabel' => 'Naikkan foto '.($i + 1), 'downLabel' => 'Turunkan foto '.($i + 1),
                'pick' => $this->xc($this->jsA('pilihFoto', $slot, true)), 'change' => $ph['change'],
                'canCrop' => $ph['bisaCrop'], 'crop' => $this->wc('bukaCrop', $ph['libId']), 'cropLabel' => 'Potong '.$ph['name'],
                'canClear' => $ph['has'] && ! $isGal, 'clear' => $ph['clear'], 'clearLabel' => 'Lepas '.mb_strtolower($ph['label']),
                'canDel' => $isGal, 'delSlot' => $ph['delSlot'], 'delLabel' => 'Hapus foto '.($i + 1).' dari galeri',
                'thumbLabel' => 'Tampilkan '.$ph['label'].' di canvas',
            ];
        }
        $onS = 'background:#1D4ED8;color:#FFFFFF;box-shadow:0 3px 0 #1A3FA8;border:2px solid #1D4ED8;';
        $offS = 'background:#FFFFFF;color:#262A31;border:2px solid #E2E5E9;';
        $layouts = array_map(fn ($l) => [
            'label' => $l[1], 'on' => $gset['layout'] === $l[0], 'sty' => $gset['layout'] === $l[0] ? $onS : $offS, 'pick' => $this->wc('aturLayoutGaleri', $l[0]),
        ], Papan::TATA_LETAK);
        $vOk = Papan::isUrl($gset['video']);
        $vv = $gset['video'];

        // form isi bagian
        $mkField = function (string $k) use ($d, $sel, $hid, $iso): array {
            $m = $iso->meta($k, $this->daftar);
            $mg = Papan::meta($k);
            $kd = $m['kind'];
            $on = $sel === $k;
            $v0 = (string) ($d[$k] ?? '');
            $v = trim($v0);
            $ok = $kd === 'url' && Papan::isUrl($v);
            $area = $kd === 'text' && $m['rows'] >= 2;
            $opts = [];
            if ($kd === 'select') {
                foreach ((array) $m['opts'] as $o) {
                    $opts[] = [
                        'v' => $o[0], 'l' => $iso->isiLabel((string) $o[1], $d), 'sel' => (string) $o[0] === $v0, 'pick' => $this->wc('setBidang', $k, (string) $o[0]),
                        'sty' => (string) $o[0] === $v0 ? 'background:#E3ECFD;border:2px solid #1D4ED8;color:#1A3FA8;' : 'background:#FFFFFF;border:2px solid #E2E5E9;color:#262A31;',
                    ];
                }
            }
            $galat = $this->galatBidang[$k] ?? null;

            return [
                'id' => 'f-'.$k, 'label' => $m['label'], 'isOpt' => $m['opt'] && $kd !== 'switch', 'ph' => $kd === 'url' ? 'https://…' : $m['label'], 'val' => $v0,
                'isInput' => $kd === 'text' && ! $area, 'isArea' => $area, 'isUrl' => $kd === 'url', 'isSelect' => $kd === 'select', 'isSwitch' => $kd === 'switch',
                'isDate' => $kd === 'date', 'isTime' => $kd === 'time', 'isColor' => $kd === 'color',
                'opts' => $opts, 'swOn' => $v0 === '1', 'swText' => $v0 === '1' ? 'Aktif' : 'Mati', 'swTrack' => $v0 === '1' ? '#1D4ED8' : '#AEB3BB', 'swLeft' => $v0 === '1' ? '21px' : '3px',
                'toggleSw' => $this->wc('setBidang', $k, $v0 === '1' ? '0' : '1'),
                'colorVal' => preg_match('/^#[0-9a-f]{6}$/i', $v) ? $v : '#888888',
                'rows' => max(2, (int) $m['rows']),
                'urlOk' => $ok, 'urlBad' => $kd === 'url' && $v !== '' && ! $ok, 'urlEmpty' => $kd === 'url' && $v === '', 'urlHint' => $m['hint'] ?? '',
                'urlHintSty' => $m['opt'] ? 'color:#666C76;' : 'color:#B25E00;', 'href' => $ok ? $v : '#',
                'sty' => ($on ? 'border-color:#1D4ED8;background:#FFFFFF;' : '').(isset($hid[$k]) ? 'opacity:0.55;' : ''),
                'chg' => 'data-k="'.e($k).'" '.$this->xc('kotor($el)', 'input').' '.$this->xc('kirim($el, '.Papan::jsArg($k).')', 'change'),
                'foc' => $this->xc($this->jsA('pilih', $k, $m['canvas'] ? 2 : 0), 'focus'),
                'canHide' => (bool) $m['canvas'], 'shown' => ! isset($hid[$k]), 'hiddenNow' => isset($hid[$k]), 'eyeColor' => isset($hid[$k]) ? '#9AA0A9' : '#454B55',
                'hideLabel' => (isset($hid[$k]) ? 'Tampilkan ' : 'Sembunyikan ').$m['label'].' di undangan', 'toggle' => $this->wc('toggleBidang', $k),
                'galat' => $galat,
            ];
        };
        $groups = [];
        $G0 = [
            'isFields' => false, 'isHead' => false, 'isAdd' => false, 'isItem' => false, 'hasTitle' => false, 'hasTools' => false, 'title' => '', 'head' => '', 'cardSty' => '', 'canRemove' => false,
            'upDis' => true, 'downDis' => true, 'up' => '', 'down' => '', 'remove' => '', 'dup' => '', 'dupLabel' => '', 'rmLabel' => '', 'upLabel' => '', 'downLabel' => '', 'fields' => [],
            'add' => '', 'addText' => '', 'summary' => '', 'fk' => '', 'defOpen' => true, 'nm' => '',
        ];
        $foldKeys = [];
        $sk = array_keys(SkemaUndangan::bidangBagian($sid));
        if ($sk) {
            $groups[] = array_merge($G0, ['isFields' => true, 'hasTitle' => true, 'title' => 'Teks bagian', 'fk' => 'static', 'defOpen' => true, 'nm' => 'teks bagian', 'fields' => array_map($mkField, $sk)]);
        }
        foreach (SkemaUndangan::daftarBagian()[$sid] ?? [] as $sl) {
            $arr = $lists[$sl['t']] ?? [];
            if (isset($sl['head'])) {
                $groups[] = array_merge($G0, ['isHead' => true, 'head' => $sl['head'].' · '.count($arr)]);
            }
            foreach ($arr as $i => $id) {
                $ks = $iso->kunciItem($sl['t'], $id);
                $inGrp = $terpilih !== null && in_array($terpilih, $ks, true);
                $nm = mb_strtolower($sl['item']).' '.($i + 1);
                $fk = $sl['t'].$id;
                $foldKeys[] = $fk;
                $sm = collect($ks)->filter(fn ($k) => ($mm = $iso->meta($k, $lists)) && $mm['canvas'] && $mm['kind'] === 'text')->map(fn ($k) => trim((string) ($d[$k] ?? '')))->filter()->take(2)->implode(' · ');
                $groups[] = array_merge($G0, [
                    'isFields' => true, 'isItem' => true, 'hasTitle' => true, 'hasTools' => true, 'title' => $sl['item'].' '.($i + 1), 'fields' => array_map($mkField, $ks),
                    'fk' => $fk, 'defOpen' => $inGrp || count($arr) <= 1, 'nm' => $nm, 'summary' => mb_strlen($sm) > 40 ? mb_substr($sm, 0, 39).'…' : $sm,
                    'cardSty' => 'border:2px solid '.($inGrp ? '#1D4ED8' : '#E2E5E9').';border-radius:16px;padding:8px 12px;',
                    'up' => $this->wc('geserItem', $sl['t'], $id, -1), 'down' => $this->wc('geserItem', $sl['t'], $id, 1), 'upDis' => $i === 0, 'downDis' => $i === count($arr) - 1,
                    'upLabel' => 'Naikkan '.$nm, 'downLabel' => 'Turunkan '.$nm, 'dup' => $this->wc('gandakanItem', $sl['t'], $id), 'dupLabel' => 'Gandakan '.$nm,
                    'canRemove' => count($arr) > $tpl[$sl['t']]['min'], 'remove' => $this->wc('hapusItem', $sl['t'], $id), 'rmLabel' => 'Hapus '.$nm,
                ]);
            }
            $groups[] = array_merge($G0, ['isAdd' => true, 'addText' => $sl['add'], 'add' => $this->wc('tambahItem', $sl['t'])]);
        }
        $formCount = array_sum(array_map(fn ($g) => count($g['fields']), $groups));

        // ---------- pemilih gambar & potong
        $pustaka = $this->dialogFoto ? $this->dataPustaka() : null;
        $slotMode = $this->modeFoto === 'slot' || $this->modeFoto === 'bgf' || $this->modeFoto === 'bgs';
        $namaSlotPilih = match (true) {
            $this->modeFoto === 'bgf' => 'gambar background fixed',
            $this->modeFoto === 'bgs' => 'slide '.substr((string) $this->slotFoto, 3),
            $this->slotFoto !== null && $this->slotFoto !== '' && $this->slotSah((string) $this->slotFoto) => mb_strtolower($labelSlot((string) $this->slotFoto)),
            default => 'slot',
        };
        $nPilih = count($this->pilihanFoto);
        $pickedText = $slotMode
            ? ($nPilih ? '1 gambar dipilih untuk '.$namaSlotPilih : 'Pilih satu gambar untuk '.$namaSlotPilih)
            : ($nPilih ? $nPilih.' gambar dipilih' : 'Pilih satu atau lebih gambar untuk ditambahkan ke galeri');
        $pickItems = [];
        foreach ($pustaka['item'] ?? [] as $it) {
            $on = in_array($it['nilai'], $this->pilihanFoto, true);
            $ref = is_int($it['nilai']) ? 'm'.$it['nilai'] : $it['nilai'];
            $pickItems[] = [
                'id' => $it['id'], 'nama' => $it['nama'], 'on' => $on, 'hasImg' => (bool) $it['thumb'], 'ghost' => ! $it['thumb'], 'src' => $it['thumb'],
                'imgSty' => Papan::gayaGambar($this->potongTersimpan()[$ref] ?? null), 'color' => $it['warna'] ?? '#E3ECFD', 'ukuran' => $it['ukuran'],
                'boxSty' => 'position:relative;display:flex;width:100%;aspect-ratio:1 / 1;border-radius:16px;overflow:hidden;align-items:center;justify-content:center;color:#454B55;background:'.($it['warna'] ?? '#E3ECFD').';'.($on ? 'box-shadow:0 0 0 3px #FFFFFF,0 0 0 5px #1D4ED8;' : ''),
                'nameSty' => 'display:block;font-size:11px;line-height:13px;font-weight:'.($on ? 700 : 500).';color:'.($on ? '#1D4ED8' : '#666C76').';overflow:hidden;text-overflow:ellipsis;white-space:nowrap;',
                'label' => $it['nama'].($on ? ', dipilih' : ''), 'pick' => $this->wc('pilihFoto', $it['nilai']), 'remove' => $this->wc('hapusDariPustaka', $it['nilai']), 'crop' => $this->wc('bukaCrop', $ref),
                'removeLabel' => 'Hapus '.$it['nama'].' dari gallery', 'cropLabel' => 'Potong '.$it['nama'],
            ];
        }
        $crop = $this->dataCrop();

        // ---------- panel kiri
        $tabs = array_map(fn ($t) => ['label' => $t[1], 'key' => $t[0]], [['bagian', 'Bagian'], ['tema', 'Tema'], ['setting', 'Setting']]);
        $semuaTema = Tema::terbit()->urut()->get();
        $kuota = app(KuotaUndangan::class);
        $themeCards = [];
        foreach ($semuaTema as $t) {
            $k = Papan::tokenTema($t);
            $on = $u->tema_id === $t->id || ($u->tema_id === null && $tema && $tema->id === $t->id);
            $terkunci = $kuota->temaTerkunci($t, auth()->user());
            $themeCards[] = [
                'id' => $t->id, 'nama' => $t->nama, 'tier' => $t->tier, 'tierSty' => Papan::TIER_GAYA[$t->tier] ?? '', 'isOn' => $on, 'terkunci' => $terkunci,
                'head' => ['jakarta' => 'Sans Modern', 'script' => 'Script Elegan'][$t->font_judul] ?? 'Serif Klasik',
                'aria' => 'Pakai tema '.$t->nama.', '.$t->tier.($on ? ', sedang dipakai' : '').($terkunci ? ', terkunci di paketmu' : ''),
                'cardSty' => $on ? 'background:#E3ECFD;border:2px solid #1D4ED8;box-shadow:0 3px 0 #BFD3FF;' : 'background:#FFFFFF;border:2px solid #E2E5E9;',
                'pvPage' => $k['page'], 'pvCov' => $k['cov'], 'pvOnP' => $k['onA'], 'pvSurf' => $k['bgB'], 'pvAcc' => $k['acc'], 'pvInk' => $k['ink'], 'pvFam' => $k['hf'],
                'pvArch' => $k['coverDark'] ? 'rgba(255,255,255,0.22)' : 'rgba(16,32,58,0.1)', 'pick' => $this->wc('pilihTema', $t->id),
            ];
        }
        $curTheme = $tema;
        $curThemeNama = $curTheme?->nama ?? 'belum ada';
        $curThemeTier = $curTheme?->tier ?? '';

        // ---------- Background Fixed
        $supported = $this->temaMendukungBgf();
        $bgf = $latar;
        $bgPaint = function (?array $info) use ($th): string {
            return $info && $info['ada'] ? "url('".$info['src']."') center/cover no-repeat" : $th['tint'];
        };
        $bgInfo = fn (mixed $f) => $this->infoFoto($f, is_array($f) && isset($f['mid']) ? $medias->get((int) $f['mid']) : null);
        $bgImg = $bgInfo(isset($bgf['src']) ? ['src' => $bgf['src'], 'mid' => $bgf['mid'] ?? null, 'sid' => $bgf['sid'] ?? null] : null);
        $bgVidSrc = $bgf['vsrc'] !== '' ? $bgf['vsrc'] : (Papan::isUrl($bgf['vurl']) ? trim($bgf['vurl']) : '');
        $bgSlotIds = ['bgs1', 'bgs2', 'bgs3', 'bgs4'];
        $slideInfo = [];
        foreach ($bgSlotIds as $k) {
            $slideInfo[$k] = $bgInfo($bgf[$k] ?? null);
        }
        $bgFilled = array_values(array_filter($bgSlotIds, fn ($k) => $slideInfo[$k]['ada']));
        $bgHasMedia = $bgf['kind'] === 'image' ? $bgImg['ada'] : ($bgf['kind'] === 'video' ? $bgVidSrc !== '' : $bgFilled !== []);
        $bgShow = $supported && $bgf['on'] && $bgHasMedia;
        $bgBl = $bgf['blur'];
        $bgOc = $bgf['oc'] !== '' ? $bgf['oc'] : $th['page'];
        $bgSlideCount = count($bgFilled);
        $bgSlides = [];
        foreach ($bgFilled as $i => $k) {
            $base = 'position:absolute;top:0;left:0;width:100%;height:100%;background:'.$bgPaint($slideInfo[$k]).';';
            $bgSlides[] = ['sty' => $bgSlideCount < 2 ? $base : $base.'animation:bgfs'.$bgSlideCount.' '.($bgSlideCount * $bgf['sd']).'s infinite;animation-delay:'.($i === 0 ? 0 : -(($bgSlideCount - $i) * $bgf['sd'])).'s;'];
        }
        $kindLabel = ['image' => 'Gambar', 'video' => 'Video', 'slide' => 'Slideshow'][$bgf['kind']];
        $thumbBg = $bgf['kind'] === 'image' ? $bgPaint($bgImg) : ($bgf['kind'] === 'slide' ? $bgPaint($bgFilled ? $slideInfo[$bgFilled[0]] : null) : '#262A31');
        $bgKinds = array_map(fn ($t) => [
            'label' => $t[1], 'on' => $bgf['kind'] === $t[0], 'pick' => $this->xc('$wire.aturBgf({kind: '.Papan::jsArg($t[0]).'})'),
            'sty' => $bgf['kind'] === $t[0] ? 'background:#1D4ED8;color:#FFFFFF;box-shadow:0 3px 0 #1A3FA8;' : 'background:#FFFFFF;border:2px solid #E2E5E9;',
        ], [['image', 'Gambar'], ['video', 'Video'], ['slide', 'Slideshow']]);
        $slotBox = 'position:relative;width:100%;aspect-ratio:16 / 10;box-sizing:border-box;border-radius:14px;overflow:hidden;display:flex;align-items:center;justify-content:center;';
        $bgSlots = [];
        foreach ($bgSlotIds as $i => $k) {
            $li = $slideInfo[$k];
            $bgSlots[] = [
                'label' => 'Slide '.($i + 1), 'has' => $li['ada'], 'empty' => ! $li['ada'],
                'aria' => 'Slide '.($i + 1).($li['ada'] ? ' — '.$li['nama'].', klik untuk mengganti' : ' — kosong, klik untuk memilih'),
                'sty' => $slotBox.($li['ada'] ? 'background:'.$bgPaint($li).';' : 'border:2px dashed #1D4ED8;background:#F3F6FF;'),
                'pick' => $this->wc('bukaFotoBgs', $i + 1), 'clear' => $this->xc('$wire.aturBgf({lepasSlide: '.($i + 1).'})'),
            ];
        }
        $zoom = 100;
        $bgVm = [
            'bgfShow' => $bgShow, 'bgfH' => 800, 'bgfTop' => 28,
            'frameCls' => $bgShow ? 'bgfon' : '',
            'bgfWrap' => 'position:absolute;'.($bgBl > 0 ? 'top:-30px;left:-30px;width:calc(100% + 60px);height:calc(100% + 60px);' : 'top:0;left:0;width:100%;height:100%;').'overflow:hidden;opacity:'.($bgf['op'] / 100).';'.($bgBl > 0 ? 'filter:blur('.$bgBl.'px);' : ''),
            'bgfOv' => 'position:absolute;top:0;left:0;width:100%;height:100%;background:'.$bgOc.';opacity:'.($bgf['oo'] / 100).';',
            'bgfIsVideo' => $bgShow && $bgf['kind'] === 'video', 'bgfVideoSrc' => $bgVidSrc, 'bgfIsImg' => $bgShow && $bgf['kind'] === 'image',
            'bgfImgSty' => 'position:absolute;top:0;left:0;width:100%;height:100%;background:'.$bgPaint($bgImg).';',
            'bgfSlides' => $bgShow && $bgf['kind'] === 'slide' ? $bgSlides : [],
            'bgfStatus' => ! $bgf['on'] ? 'Nonaktif · atur gambar, video, atau slideshow' : ($bgHasMedia ? 'Aktif · '.$kindLabel.($bgf['kind'] === 'slide' ? ' ('.$bgSlideCount.' foto)' : '') : 'Aktif · belum ada '.mb_strtolower($kindLabel).' dipilih'),
            'bgfStatusShort' => ! $bgf['on'] ? 'Nonaktif' : 'Aktif', 'bgfKindLabel' => $kindLabel,
            'bgfAvail' => $supported, 'bgfThumbOn' => $supported && $bgf['on'] && $bgHasMedia,
            'bgfThumb' => 'position:relative;width:100%;height:72px;border-radius:14px;overflow:hidden;background:'.$thumbBg.';box-shadow:0 0 0 2px #E2E5E9;',
            'bgfThumbTag' => 'position:absolute;left:8px;bottom:8px;background:rgba(19,31,36,0.7);color:#FFFFFF;border-radius:8px;padding:3px 8px;font-size:11px;line-height:14px;font-weight:700',
            'openBg' => $this->wc('bukaBgf'), 'closeBg' => $this->wc('tutupBgf'), 'toggleBg' => $this->xc('$wire.aturBgf({on: '.($bgf['on'] ? 'false' : 'true').'})'), 'resetBg' => $this->wc('hapusBgf'),
            'bgOpen' => $supported && $this->dialogBgf, 'bgErr' => $this->galatBgf, 'hasBgErr' => $this->galatBgf !== '',
            'bgfOn' => $bgf['on'], 'bgfSwBg' => $bgf['on'] ? '#1D4ED8' : '#AEB3BB', 'bgfSwLeft' => $bgf['on'] ? '21px' : '3px', 'bgfSwText' => $bgf['on'] ? 'Aktif' : 'Mati',
            'bgfSupSty' => 'border-radius:14px;padding:10px 12px;font-size:12px;line-height:17px;font-weight:600;background:#E3ECFD;color:#1A3FA8;',
            'bgfSupText' => 'Tema '.$curThemeNama.' mendukung Background Fixed: section yang tertaut otomatis memakai media ini.',
            'bgKinds' => $bgKinds, 'bgfIsVideoTab' => $bgf['kind'] === 'video', 'bgfIsSlideTab' => $bgf['kind'] === 'slide', 'bgfIsImgTab' => $bgf['kind'] === 'image',
            'bgImg' => [
                'sty' => $slotBox.'aspect-ratio:16 / 9;'.($bgImg['ada'] ? 'background:'.$bgPaint($bgImg).';' : 'border:2px dashed #1D4ED8;background:#F3F6FF;'),
                'empty' => ! $bgImg['ada'], 'has' => $bgImg['ada'], 'act' => $bgImg['ada'] ? 'Ganti gambar' : 'Pilih gambar',
                'aria' => $bgImg['ada'] ? 'Gambar background — '.$bgImg['nama'].', klik untuk mengganti' : 'Pilih gambar background',
            ],
            'pickBgImg' => $this->wc('bukaFotoBgf'), 'clearBgImg' => $this->xc('$wire.aturBgf({hapusGambar: true})'),
            'bgVidLabel' => $bgf['vsrc'] !== '' ? 'Ganti video · '.($bgf['vname'] ?: 'video') : 'Unggah video dari perangkat', 'bgVurl' => $bgf['vurl'],
            'bgHasVid' => $bgVidSrc !== '', 'clearBgVid' => $this->xc('$wire.aturBgf({hapusVideo: true})'),
            'bgSlots' => $bgSlots, 'bgSd' => $bgf['sd'], 'bgSdText' => $bgf['sd'].' detik',
            'bgOp' => $bgf['op'], 'bgOpText' => $bgf['op'].'%', 'bgBl' => $bgBl, 'bgBlText' => $bgBl.' px', 'bgOo' => $bgf['oo'], 'bgOoText' => $bgf['oo'].'%',
            'bgOcVal' => preg_match('/^#[0-9a-f]{6}$/i', $bgOc) ? $bgOc : '#FFFFFF',
            'bgOcAuto' => $this->xc('$wire.aturBgf({oc: \'\'})'),
        ];

        // ---------- terbit
        $penerbit = app(PenerbitUndangan::class);
        $terbit = $u->sudahTerbit();
        $pubDirty = $terbit && $u->ada_perubahan;
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'app.undangyah.com';
        $slugKini = (string) $u->slug;
        $pubUrl = rtrim((string) config('app.url'), '/').'/'.$slugKini;
        $qr = $this->dialogTerbit && $this->tahapTerbit === 'selesai' ? KodeQr::svg($pubUrl, 168) : '';
        $jam = $u->diterbitkan_at ? Carbon::parse($u->diterbitkan_at)->timezone(config('app.timezone'))->format('H:i') : '';
        $pubVm = [
            'pubOpen' => $this->dialogTerbit, 'pubClose' => $this->wc('tutupTerbit'), 'pubForm' => $this->tahapTerbit !== 'selesai', 'pubDone' => $this->tahapTerbit === 'selesai',
            'pubErr' => trim($this->galatSlug !== '' ? $this->galatSlug : $this->galatTerbit), 'hasPubErr' => $this->galatSlug !== '' || $this->galatTerbit !== '',
            'pubBusy' => false, 'pubGoDis' => false, 'pubSlugIn' => $this->slugTerbit,
            'pubGoText' => $terbit ? 'Terbitkan ulang' : 'Terbitkan sekarang', 'pubThemeName' => $curThemeNama, 'pubDirty' => $pubDirty, 'pubNotDirty' => ! $pubDirty,
            'pubPublished' => $terbit, 'pubOld' => $terbit && $pubDirty, 'pubUrlText' => $host.'/'.$slugKini, 'pubAt' => $jam !== '' ? 'Diterbitkan pukul '.$jam : '',
            'pubUrl' => $pubUrl, 'pubHref' => $pubUrl, 'pubWa' => 'https://wa.me/?text='.rawurlencode('Dengan hormat, kami mengundang Anda ke pernikahan '.$u->judul.'. Buka undangan digital kami: '.$pubUrl.'?to=Nama%20Tamu'),
            'qrSvg' => $qr, 'qrDown' => 'data:image/svg+xml;charset=utf-8,'.rawurlencode(KodeQr::svg($pubUrl, 512)), 'qrName' => 'qr-'.$slugKini.'.svg',
            'hasQr' => $qr !== '',
        ];
        $blokir = $this->dialogTerbit ? $penerbit->blokir($u) : [];
        $saran = $this->dialogTerbit ? $penerbit->peringatan($u) : [];
        $issues = [];
        foreach ($blokir as $b) {
            $issues[] = ['text' => $b['teks'], 'isBlock' => true, 'isWarn' => false, 'go' => $this->isuAksi($b['kunci'])];
        }
        foreach ($saran as $s) {
            $issues[] = ['text' => $s['teks'], 'isBlock' => false, 'isWarn' => true, 'go' => $this->isuAksi($s['kunci'])];
        }
        $issVm = ['pubIssues' => $issues, 'hasIssues' => $issues !== [], 'hasBlock' => $blokir !== [], 'issHead' => $blokir ? 'Perlu diperbaiki sebelum terbit' : 'Disarankan dilengkapi (boleh tetap terbit)'];

        // ---------- mode kanvas
        $cvTam = $this->mode === 'tamu';
        $cvVm = [
            'cvTamu' => $cvTam, 'cvEditOn' => ! $cvTam, 'cvEditSty' => $cvTam ? 'display:none;' : 'display:flex;', 'zoomHide' => $cvTam ? 'display:none!important;' : '',
            'cvSetEdit' => $this->wc('setMode', 'edit'), 'cvSetTamu' => $this->wc('setMode', 'tamu'),
            'cvBtnE' => $cvTam ? 'background:#FFFFFF;color:#454B55;' : 'background:#1D4ED8;color:#FFFFFF;', 'cvBtnT' => $cvTam ? 'background:#1D4ED8;color:#FFFFFF;' : 'background:#FFFFFF;color:#454B55;',
            'cvFrameSrc' => $cvTam && Route::has('pratinjau.bingkai') ? route('pratinjau.bingkai', ['undangan' => $u->id, 'v' => 'draf', 'r' => (int) ($u->draf_disimpan_at?->timestamp ?? 0)]) : '',
        ];
        $galWrap = $gset['layout'] === 'masonry' ? 'width:100%;columns:2;column-gap:8px;' : ($gset['layout'] === 'slider' ? 'width:100%;display:flex;gap:10px;overflow-x:auto;scroll-snap-type:x mandatory;padding:4px 2px 8px;' : 'width:100%;display:grid;grid-template-columns:repeat(3, minmax(0, 1fr));gap:8px;');

        $gal = array_map(fn ($n) => $mkPh('g'.$n, 'gallery'), $lists['gl']);
        $rings = (bool) ($this->pengaturan['lingkaran'] ?? true);
        $jumlahTema = $semuaTema->count();

        return [
            'judul' => $this->judul, 'slug' => $this->slug, 'published' => $terbit, 'notPublished' => ! $terbit,
            'publishText' => $terbit ? ($pubDirty ? 'Terbitkan ulang' : 'Link & QR') : 'Terbitkan',
            'togglePublish' => $this->wc('bukaTerbit'),
            ...$pubVm,
            'undo' => $this->wc('urungkan'), 'redo' => $this->wc('ulangi'), 'noUndo' => $this->jmlUndo < 1, 'noRedo' => $this->jmlUlang < 1,
            ...$bgVm, ...$cvVm, ...$issVm,
            'tabs' => $tabs, 'themeCount' => $jumlahTema, 'curThemeName' => $curThemeNama, 'curThemeTier' => $curThemeTier,
            'bgNote' => '', 'noThemes' => $jumlahTema === 0,
            'themeTiers' => array_map(fn ($x) => ['label' => $x], ['Semua', ...Tema::TIER]),
            'rings' => $rings, 'ringsBg' => $rings ? '#1D4ED8' : '#AEB3BB', 'ringsLeft' => $rings ? '21px' : '3px', 'ringsText' => $rings ? 'Aktif' : 'Mati', 'toggleRings' => $this->wc('toggleLingkaran'),
            'themeCards' => $themeCards,
            'c' => ['acc' => $th['acc'], 'dep' => $th['dep'], 'tint' => $th['tint'], 'bgB' => $th['bgB'], 'hf' => $hf, 'page' => $th['page'], 'cov' => $th['cov'], 'onA' => $th['onA'], 'onAcc' => $th['onAcc'], 'ink' => $th['ink']],
            'ss' => $ss, 'tg' => $tg, 'sw' => $sw, 'fld' => $fld, 'persons' => $persons, 'rsv' => $rsv,
            'kontenRows' => $kontenRows, 'fiturRows' => $fiturRows, 'pinRows' => $pinRows,
            'events' => $events, 'stories' => $stories, 'rundown' => $rundown, 'gifts' => $gifts, 'famP' => $famP, 'famW' => $famW, 'accts' => $accts, 'colors' => $colors, 'mu' => $mu, 'lv' => $lv, 'gc' => $gc, 'sc' => $sc,
            'noStories' => $stories === [], 'noRundown' => $rundown === [], 'noGifts' => $gifts === [], 'noFamP' => $famP === [], 'noFamW' => $famW === [], 'noColors' => $colors === [],
            'addEv' => $this->wc('tambahItem', 'ev'), 'addLs' => $this->wc('tambahItem', 'ls'), 'addRd' => $this->wc('tambahItem', 'rd'), 'addKd' => $this->wc('tambahItem', 'kd'),
            'addTp' => $this->wc('tambahItem', 'tp'), 'addTw' => $this->wc('tambahItem', 'tw'), 'addAk' => $this->wc('tambahItem', 'ak'), 'addWr' => $this->wc('tambahItem', 'wr'),
            'addSty' => 'color:'.$th['acc'].';border:2px dashed '.$th['acc'].'66;',
            'ph' => ['cover' => $mkPh('cover', 'cover'), 'latar' => $mkPh('latar', 'banner'), 'penutup' => $mkPh('penutup', 'round')],
            'gal' => $gal, 'galWrap' => $galWrap, 'noGal' => $lists['gl'] === [], 'galSlider' => $gset['layout'] === 'slider', 'openAddPhotos' => $this->wc('bukaGaleri'),
            'hasVideo' => $vOk, 'videoHref' => $vOk ? $vv : '#', 'videoShort' => Papan::urlPendek($vv),
            'secProp' => $secProp, 'groups' => $groups, 'formCount' => $formCount, 'hasForm' => (bool) array_filter($groups, fn ($g) => $g['isFields']),
            'foldKeys' => $foldKeys, 'hasFoldAll' => count($foldKeys) > 1,
            'galHint' => count($lists['gl']) < 6 ? 'Disarankan minimal 6 foto. Saat ini '.count($lists['gl']).' foto.' : 'Sudah '.count($lists['gl']).' foto, cukup untuk galeri.',
            'galHintSty' => count($lists['gl']) < 6 ? 'color:#B25E00;' : 'color:#3C7A00;',
            'hasPhotos' => $hasPhotos, 'isGalleryPanel' => $sid === 'gallery', 'galCount' => count($lists['gl']), 'photoRows' => $photoRows, 'photoCount' => count($rowSlots),
            'layouts' => $layouts, 'video' => $gset['video'], 'videoBad' => $vv !== '' && ! $vOk, 'videoEmpty' => $vv === '', 'videoOkP' => $vOk,
            'addPhotos' => $this->wc('bukaGaleri'),
            'hasErr' => $this->galatFoto !== [], 'phErr' => implode(' ', $this->galatFoto),
            'pickOpen' => $this->dialogFoto, 'closePicker' => $this->wc('tutupFoto'),
            'tabGallery' => $this->tabFoto === 'gallery', 'tabUpload' => $this->tabFoto === 'upload', 'tabDrive' => $this->tabFoto === 'drive',
            'pickTabs' => array_map(fn ($t) => ['label' => $t[1], 'on' => $this->tabFoto === $t[0], 'off' => $this->tabFoto !== $t[0], 'pick' => $this->wc('setTabFoto', $t[0])], [['gallery', 'Gallery'], ['upload', 'Upload'], ['drive', 'Drive']]),
            'query' => $this->cariFoto,
            'kats' => array_map(fn ($k) => ['label' => $k, 'on' => $this->katFoto === $k, 'off' => $this->katFoto !== $k, 'sty' => $this->katFoto === $k ? $onS : $offS, 'pick' => $this->wc('setKatFoto', $k)], Papan::KATEGORI),
            'pickItems' => $pickItems, 'noItems' => $pickItems === [], 'hasUpNote' => $this->catatanUnggah !== '', 'upNote' => $this->catatanUnggah,
            'hasRemoved' => $this->hapusTertunda !== null, 'removedText' => $this->hapusTertunda ? $this->hapusTertunda['nama'].' dihapus dari gallery.' : '', 'undoRemove' => $this->wc('urungkanHapusPustaka'),
            'pickedText' => $pickedText, 'hasPicked' => $nPilih > 0, 'noPicked' => $nPilih === 0, 'confirmPick' => $this->wc('terapkanPilihan'),
            'pickBtnText' => $slotMode ? 'Pilih Gambar' : ($nPilih ? 'Tambahkan '.$nPilih.' foto' : 'Tambahkan foto'),
            'pickSub' => $slotMode ? 'Pilih satu gambar dari gallery atau unggah baru' : 'Pilih banyak gambar sekaligus untuk galeri',
            'pustaka' => $pustaka, 'crop' => $crop,
            'pubDraft' => $this->wc('jadikanDraf'), 'pubOpenAgain' => $this->wc('ulangTerbit'), 'pubGo' => $this->wc('terbitkan'),
        ];
    }

    /** Pengikat klik untuk butir "Perlu diperbaiki"/"Disarankan": menutup dialog lalu menuju bidang/bagian yang dimaksud. */
    private function isuAksi(string $kunci): string
    {
        return match (true) {
            $kunci === 'judul' => $this->xc("tab = 'setting'; \$wire.tutupTerbit()"),
            $kunci === 'tema' => $this->xc("tab = 'tema'; \$wire.tutupTerbit()"),
            $kunci === 'tamu' => $this->xc('$wire.tutupTerbit()'),
            str_starts_with($kunci, 'sec:') => $this->xc("\$wire.tutupTerbit(); pilihBagianKe('".substr($kunci, 4)."')"),
            default => $this->xc("\$wire.tutupTerbit(); \$wire.setMode('edit'); tab = 'bagian'; pilih('".$kunci."', 1)"),
        };
    }

    /** @return array<string,mixed>|null data dialog potong gambar (foto yang dipotong + nilai awal), atau null bila tertutup */
    private function dataCrop(): ?array
    {
        if (! $this->dialogCrop || $this->refCrop === null) {
            return null;
        }
        $entri = $this->entriDariRef($this->refCrop);
        if ($entri === null) {
            return null;
        }
        $nama = isset($entri['mid']) ? (string) \App\Models\Media::milik(auth()->user())->whereKey($entri['mid'])->value('nama') : (string) (\App\Support\Editor\PustakaBawaan::ITEM[$this->refCrop]['nama'] ?? $this->refCrop);
        $crop = Papan::normalCrop($this->potongTersimpan()[$this->refCrop] ?? null) ?? Papan::CROP_AWAL;
        $warna = \App\Support\Editor\PustakaBawaan::ITEM[$this->refCrop]['color'] ?? '#E3ECFD';

        return ['ref' => $this->refCrop, 'nama' => $nama, 'src' => $entri['src'], 'crop' => $crop, 'warna' => $warna];
    }
}
