// Mode EDIT: dipasang ulang pada langkah berikutnya (lihat docs/tema-mesin.md).
import { TCBase } from './tcbase.js';
import { pasang } from './dc.js';
import { TEMPLAT_EDITOR } from './templat-editor.js';

export class EditorLogic extends TCBase {}
export function mulaiEditor(wadah, opsi) {
  wadah.classList.add('tema-akar');
  const h = pasang(EditorLogic, TEMPLAT_EDITOR, wadah, opsi || {});
  return { logic: h.logic, hancurkan: () => h.hancurkan() };
}
