(() => {
    const select = document.querySelector('[name="seller_type"]');
    const sections = [...document.querySelectorAll('[data-seller-document]')];
    if (!select || !sections.length) return;
    const sync = () => sections.forEach(section => {
        const visible = section.dataset.sellerDocument.split(',').includes(select.value);
        section.hidden = !visible;
        section.querySelectorAll('input[type="file"]').forEach(input => {
            input.disabled = !visible;
            input.required = visible && section.dataset.documentOnFile !== 'true';
            input.setCustomValidity('');
        });
    });
    const validate = () => {
        let valid = true;
        sections.forEach(section => section.querySelectorAll('input[type="file"]').forEach(input => {
            if (input.disabled) return;
            const file = input.files[0];
            input.setCustomValidity(file && file.size > 5 * 1024 * 1024 ? 'Maximum file size is 5 MB.' : (file && !/\.(jpe?g|png|pdf)$/i.test(file.name) ? 'Upload a JPG, PNG or PDF file.' : ''));
            if (!input.checkValidity()) { if (valid) input.reportValidity(); valid = false; }
        }));
        return valid;
    };
    select.addEventListener('change', sync);
    sections.forEach(section => {
        const input=section.querySelector('input[type="file"]'), box=section.querySelector('.seller-upload-box');
        if(!box || !input)return;
        const image=box.querySelector('img'), pdf=box.querySelector('.seller-upload-pdf'), pdfIcon=box.querySelector('.seller-upload-pdf-icon'), idle=box.querySelector('.seller-upload-idle');
        const enlarge=box.querySelector('.seller-upload-enlarge'), replace=box.querySelector('.seller-upload-replace');
        let url=null;
        input.addEventListener('change',()=>{
            if(url){URL.revokeObjectURL(url);url=null;}
            const file=input.files[0];
            const valid=file && file.size<=5*1024*1024 && /\.(jpe?g|png|pdf)$/i.test(file.name);
            image.hidden=pdf.hidden=enlarge.hidden=replace.hidden=!valid;idle.hidden=!!valid;pdfIcon.setAttribute('hidden','');
            if(!valid){image.removeAttribute('src');return;}
            url=URL.createObjectURL(file);
            const isPdf=/\.pdf$/i.test(file.name);
            pdfIcon.toggleAttribute('hidden',!isPdf);
            image.hidden=isPdf;pdf.hidden=false;
            pdf.classList.toggle('seller-upload-image-name',!isPdf);
            pdf.textContent=file.name;pdf.title=file.name;
            if(!isPdf)image.src=url;
            enlarge.onclick=event=>{
                event.stopPropagation();
                if(isPdf && typeof window.showPdfLightbox==='function')window.showPdfLightbox(url);
                else if(!isPdf && typeof window.openLightbox==='function')window.openLightbox(image.id);
                else window.open(url,'_blank','noopener');
            };
        });
        replace.addEventListener('click',event=>{event.stopPropagation();input.click();});
    });
    sections.forEach(section => section.addEventListener('change', validate));
    select.form?.addEventListener('submit', event => { if (!validate()) event.preventDefault(); });
    window.sellerDocumentInputs = { validate };
    sync();
})();
