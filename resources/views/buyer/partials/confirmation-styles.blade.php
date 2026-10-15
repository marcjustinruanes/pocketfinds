<style id="buyer-confirmation-styles">
.buyer-pages .pf-confirm { box-sizing: border-box; width: min(440px, 100%); max-height: calc(100dvh - 32px); padding: 0; overflow: auto; border: 1px solid #eadfe6; border-radius: 16px; background: #fff; color: #302733; box-shadow: 0 16px 56px rgb(36 20 32 / 20%); }
.buyer-pages dialog.pf-confirm { margin: auto; width: min(440px, calc(100% - 32px)); }
dialog.pf-confirm::backdrop { background: rgb(27 22 32 / 48%); }
.buyer-pages .pf-confirm > h3 { margin: 0; padding: 24px 24px 10px; font-size: 20px; font-weight: 650; line-height: 1.4; }
.buyer-pages .pf-confirm > p { margin: 0; padding: 0 24px 24px; font-size: 14px; line-height: 1.65; color: #695e6c; overflow-wrap: anywhere; }
.buyer-pages .pf-confirm .modal-head { padding: 20px 24px 10px; align-items: center; border-bottom: 0; }
.buyer-pages .pf-confirm .modal-head h3 { font-size: 20px; line-height: 1.4; }
.buyer-pages .pf-confirm .modal-body { padding: 0 24px 24px; font-size: 14px; line-height: 1.65; color: #695e6c; }
.buyer-pages .pf-confirm :is(.modal-foot,.cart-confirm-actions) { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 10px; margin: 0; padding: 16px 24px; border-top: 1px solid #eee5eb; background: #fdfafd; }
.buyer-pages .pf-confirm .btn { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; min-width: 108px; padding: 10px 16px; font-size: 14px; line-height: 1.4; border-radius: 8px; }
.buyer-pages .pf-confirm .btn-outline { background: #fff; border: 1px solid #dcd2dc; color: #463b48; }
.buyer-pages .pf-confirm .btn-danger { background: #fff1f1; border: 1px solid #efb5bb; color: #b32938; }
.buyer-pages .pf-confirm .btn-danger:hover { background: #ffe3e6; border-color: #c63c4c; }
.buyer-pages .pf-confirm .btn-outline:hover { background: #f3edf1; }
.buyer-pages .pf-confirm button:focus-visible { outline: 3px solid #1b6b91; outline-offset: 3px; }
.buyer-pages .pf-confirm button:not(:disabled):active { transform: translateY(1px); }
.buyer-pages .pf-confirm button:disabled { opacity: .6; cursor: wait; }
.pf-confirm .confirmation-details { margin: 0 24px 20px; padding: 12px; max-height: 180px; overflow: auto; white-space: pre-wrap; overflow-wrap: anywhere; background: #faf5f8; border: 1px solid #eadfe6; border-radius: 8px; font: inherit; font-size: 13px; line-height: 1.6; }
@media(max-width:420px) { .buyer-pages .pf-confirm :is(.modal-foot,.cart-confirm-actions) { padding: 14px 16px; } .buyer-pages .pf-confirm .btn { flex: 1; min-width: 0; } .buyer-pages .pf-confirm .modal-head { padding: 18px 16px 10px; } .buyer-pages .pf-confirm .modal-body { padding: 0 16px 20px; } .buyer-pages .pf-confirm > h3 { padding: 20px 16px 10px; } .buyer-pages .pf-confirm > p { padding: 0 16px 20px; } }
</style>
