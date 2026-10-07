{{-- Gaya papan artifact (Paket & Billing, Checkout, Konfirmasi, Bantuan, Hubungi, Syarat/Privasi): kelas `bl-*` di dalam `.bl-root`. Nilai disalin dari <helmet> papan. --}}
<style>
.bl-root{font-family:-apple-system,BlinkMacSystemFont,'SF Pro Text','SF Pro Display','Inter',system-ui,sans-serif;color:#262A31}
:where(.bl-root) button{font:inherit;color:inherit;border:0;background:none;padding:0;cursor:pointer;text-align:inherit}
:where(.bl-root) a{color:inherit;text-decoration:none}
:where(.bl-root) input,:where(.bl-root) select,:where(.bl-root) textarea{font:inherit;color:#262A31;transition:border-color 120ms,background-color 120ms,box-shadow 120ms}
:where(.bl-root) input::placeholder,:where(.bl-root) textarea::placeholder{color:#666C76}
:where(.bl-root) button,:where(.bl-root) a[href]{transition:transform 120ms cubic-bezier(.22,1,.36,1),box-shadow 120ms cubic-bezier(.22,1,.36,1),background-color 120ms,filter 120ms}
@media (hover:hover){.bl-root button:not(:disabled):hover,.bl-root a[href]:hover{filter:brightness(1.04)}.bl-root .bl-rowh:hover{background:#FAFBFC}}
.bl-root button:not(:disabled):active,.bl-root a[href]:active{transform:scale(.97)}
.bl-root [style*="0 4px 0 #"]:not(:disabled):active{transform:translateY(4px)!important;box-shadow:0 0 0 0 transparent!important}
.bl-root button:disabled{cursor:not-allowed}
.bl-root input:focus,.bl-root select:focus,.bl-root textarea:focus{border-color:#1D4ED8!important;background-color:#FFFFFF!important;outline:none}
.bl-root .bl-fld:focus-within{background:#FFFFFF!important;border-color:#1D4ED8!important;box-shadow:0 0 0 4px rgba(29,78,216,0.12)}
.bl-root button:focus-visible,.bl-root a:focus-visible{outline:3px solid #BFD3FF;outline-offset:2px}
@keyframes bl-pop{0%{opacity:0;transform:scale(.9) translateY(14px)}60%{opacity:1;transform:scale(1.02)}100%{opacity:1;transform:none}}
@keyframes bl-rise{0%{opacity:0;transform:translateY(-10px) scale(.98)}100%{opacity:1;transform:none}}
@keyframes bl-chip{0%{transform:scale(.92)}60%{transform:scale(1.05)}100%{transform:scale(1)}}
@keyframes bl-shake{0%,100%{transform:translateX(0)}20%{transform:translateX(-6px)}40%{transform:translateX(5px)}60%{transform:translateX(-3px)}80%{transform:translateX(2px)}}
@keyframes bl-sk{0%{background-position:200% 0}100%{background-position:-200% 0}}
.bl-dlg{animation:bl-pop 420ms cubic-bezier(.34,1.56,.64,1) both}
.bl-root [aria-pressed="true"]{animation:bl-chip .32s cubic-bezier(.34,1.56,.64,1)}
.bl-root [role="alert"]{animation:bl-shake .45s ease-in-out}
.bl-root [aria-live="polite"]{animation:bl-rise .4s cubic-bezier(.34,1.56,.64,1) both}
@media (prefers-reduced-motion:reduce){.bl-dlg,.bl-root [aria-pressed="true"],.bl-root [role="alert"],.bl-root [aria-live="polite"]{animation:none}}
.bl-card{background:#FFFFFF;border:2px solid #E2E5E9;border-radius:24px;box-sizing:border-box}
.bl-btn{min-height:44px;padding:0 18px;border-radius:16px;box-sizing:border-box;display:inline-flex;align-items:center;justify-content:center;gap:8px;font-size:13px;line-height:18px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;white-space:nowrap}
.bl-btn-p{background:#1D4ED8;color:#FFFFFF;box-shadow:0 4px 0 #1A3FA8}
.bl-btn-s{background:#FFFFFF;color:#1D4ED8;border:2px solid #E2E5E9;box-shadow:0 4px 0 #E2E5E9}
.bl-btn-d{background:#FFFFFF;color:#EA2B2B;border:2px solid #FFB2B2;box-shadow:0 4px 0 #FFB2B2}
.bl-btn-off{background:#E2E5E9;color:#AEB3BB;box-shadow:none;cursor:not-allowed}
.bl-chip{display:inline-flex;align-items:center;gap:6px;border-radius:999px;padding:4px 10px;font-size:12px;line-height:16px;font-weight:700}
.bl-tbl{width:100%;border-collapse:collapse;font-size:13px;line-height:18px}
.bl-tbl th{text-align:left;padding:10px 14px;font-size:11px;line-height:14px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#666C76;border-bottom:2px solid #E2E5E9;white-space:nowrap}
.bl-tbl td{padding:12px 14px;border-bottom:1px solid #E2E5E9;vertical-align:middle}
</style>
<style>
/* penyusutan kolom papan (selektor <main [style*=...]> di papan, di sini dibatasi ke .bl-root) */
@media (max-width:900px){
.bl-root [style*="repeat(4, minmax(0, 1fr))"],.bl-root [style*="repeat(4,minmax(0,1fr))"],.bl-root [style*="repeat(5, minmax(0, 1fr))"]{grid-template-columns:repeat(2,minmax(0,1fr))!important}
.bl-root [style*=".6fr) minmax"],.bl-root [style*="2fr) minmax"],.bl-root [style*=".5fr) minmax"],.bl-root [style*=".15fr) minmax"],.bl-root [style*="3fr) minmax"]{grid-template-columns:minmax(0,1fr)!important}
}
@media (max-width:720px){
.bl-root [style*="repeat(3, minmax(0, 1fr))"],.bl-root [style*="repeat(3,minmax(0,1fr))"]{grid-template-columns:minmax(0,1fr)!important}
}
@media (max-width:480px){
.bl-root [style*="repeat(4, minmax(0, 1fr))"],.bl-root [style*="repeat(4,minmax(0,1fr))"],.bl-root [style*="repeat(2, minmax(0, 1fr))"],.bl-root [style*="repeat(2,minmax(0,1fr))"]{grid-template-columns:minmax(0,1fr)!important}
}
</style>
