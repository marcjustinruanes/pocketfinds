(() => {
    const digits = value => value.replace(/\D/g, '').slice(0, 12);
    const format = value => digits(value).match(/.{1,3}/g)?.join('-') || '';
    if (typeof module !== 'undefined' && module.exports) module.exports = { format };
    if (typeof document === 'undefined') return;
    document.querySelectorAll('[data-tin]').forEach(field => {
        const apply = () => {
            const count = digits(field.value.slice(0, field.selectionStart ?? field.value.length)).length;
            field.value = format(field.value);
            const caret = Math.min(field.value.length, count + Math.floor(Math.max(0, count - 1) / 3));
            field.setSelectionRange(caret, caret);
        };
        field.value = format(field.value);
        field.addEventListener('input', apply);
        field.addEventListener('keydown', event => {
            const caret = field.selectionStart;
            if (event.key === 'Backspace' && caret === field.selectionEnd && caret > 0 && field.value[caret - 1] === '-') {
                event.preventDefault();
                field.value = field.value.slice(0, caret - 2) + field.value.slice(caret);
                field.setSelectionRange(caret - 2, caret - 2);
                field.dispatchEvent(new Event('input', { bubbles: true }));
            }
        });
    });
})();
