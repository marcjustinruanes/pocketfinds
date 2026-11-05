(() => {
    const format = value => {
        const compact=value.toUpperCase().replace(/[^A-Z0-9]/g,'');
        if(!/^[A-Z]/.test(compact))return '';
        const prefix=compact[0];
        const raw=prefix+compact.slice(prefix.length).replace(/\D/g,'').slice(0,10),first=prefix.length+2,second=first+2;
        return raw.slice(0,first)+(raw.length>first?'-'+raw.slice(first,second):'')+(raw.length>second?'-'+raw.slice(second):'');
    };
    if(typeof module!=='undefined')module.exports={format};
    if(typeof document==='undefined')return;
    document.querySelectorAll('input[name="license_number"]').forEach(input=>{
        input.maxLength=13;input.pattern='[A-Z][0-9]{2}-[0-9]{2}-[0-9]{6}';input.autocapitalize='characters';input.spellcheck=false;
        input.placeholder='A12-34-123456';
        input.addEventListener('input',()=>{
            const caret=input.selectionStart,count=input.value.slice(0,caret).replace(/[^a-z0-9]/gi,'').length;
            input.value=format(input.value);
            let position=0,seen=0;while(position<input.value.length&&seen<count){if(/[A-Z0-9]/.test(input.value[position]))seen++;position++;}
            if(document.activeElement===input)input.setSelectionRange(position,position);
        },true);
    });
})();
