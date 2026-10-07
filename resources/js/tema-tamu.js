// Entri Vite halaman TAMU (/{slug}): hanya mesin render + interaksi publik. Pemakaian di Blade:
//   @vite(['resources/css/tema-mesin.css', 'resources/js/tema-tamu.js'])
//   lalu: window.UndangyahTema.mulaiTamu(elemen, { rec, q, aksi })  (lihat docs/tema-mesin.md)
import { mulaiTamu, bangunTamu } from './tema/tamu.js';

window.UndangyahTema = Object.assign(window.UndangyahTema || {}, { mulaiTamu, bangunTamu });
export { mulaiTamu, bangunTamu };
