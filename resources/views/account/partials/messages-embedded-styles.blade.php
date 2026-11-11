<style>
html,body{margin:0;padding:0;width:100%;height:100%;background:#fff;overflow:hidden}
body{display:flex;flex-direction:column}
body>.gcash-context{flex:none;margin:0;border-radius:0;max-height:120px;overflow:auto}
body.buyer-pages>.chat-shell{flex:1 1 0;min-height:0!important;max-height:none!important;width:100%;height:100%!important;margin:0;border:0;border-radius:0;grid-template-columns:210px minmax(0,1fr)!important}
body.buyer-pages>.chat-shell>.chat-main{min-width:0;width:100%}
body.chat-embedded>.chat-shell>.chat-list{display:block!important;min-width:0}
body.chat-embedded>.chat-shell>.chat-main{display:flex!important}
body.chat-embedded>.chat-shell .chat-mobile-back{display:none}
@media(max-width:520px){
 body.chat-embedded>.chat-shell{grid-template-columns:minmax(0,1fr)!important}
 body.chat-embedded>.chat-shell[data-has-conversation="true"]>.chat-list{display:none!important}
 body.chat-embedded>.chat-shell[data-has-conversation="false"]>.chat-main{display:none!important}
 body.chat-embedded>.chat-shell .chat-mobile-back{display:inline-flex}
}
</style>
