// Entri Vite mode EDIT (Theme Creator): seluruh mesin (prelude, TCBase, runtime templat, sanitasi) + templat editor.
//   @vite(['resources/css/tema-mesin.css', 'resources/js/tema-edit.js'])
// Agen editor memakai: TCBase (logika editor), pasang (runtime), TEMPLAT_EDITOR (markup papan), dan semua fungsi pohon.
import * as Prelude from './tema/prelude.js';
import { TCBase } from './tema/tcbase.js';
import { DCLogic, pasang, kompilasiTemplat, evaluasi } from './tema/dc.js';
import * as Sanitasi from './tema/sanitasi.js';
import { sanitasiDoc, sanitasiRec, nilaiBidang, BATAS } from './tema/dokumen.js';
import { TEMPLAT_EDITOR } from './tema/templat-editor.js';
import { TEMPLAT_TAMU } from './tema/templat.js';
import { mulaiTamu, bangunTamu, TamuLogic } from './tema/tamu.js';
import { mulaiEditor, EditorLogic } from './tema/editor.js';

const api = {
  Prelude, TCBase, DCLogic, pasang, kompilasiTemplat, evaluasi, Sanitasi, sanitasiDoc, sanitasiRec, nilaiBidang, BATAS,
  TEMPLAT_EDITOR, TEMPLAT_TAMU, mulaiTamu, bangunTamu, TamuLogic, mulaiEditor, EditorLogic,
};
window.UndangyahTema = Object.assign(window.UndangyahTema || {}, api);
export default api;
export { Prelude, TCBase, DCLogic, pasang, kompilasiTemplat, evaluasi, Sanitasi, sanitasiDoc, sanitasiRec, nilaiBidang, BATAS, TEMPLAT_EDITOR, TEMPLAT_TAMU, mulaiTamu, bangunTamu, TamuLogic, mulaiEditor, EditorLogic };
