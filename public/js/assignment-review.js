(() => {
 const root=document.querySelector('.assignment-review-page');if(!root)return;
 const dialogs=[...document.querySelectorAll('.assignment-review-dialog')];
 const open=id=>{const dialog=document.getElementById(id);if(!dialog||!dialogs.includes(dialog)||dialog.open)return;dialog.showModal();document.documentElement.classList.add('assignment-review-modal-open');};
 document.addEventListener('click',event=>{const trigger=event.target.closest('[data-assignment-review]');if(trigger){open(trigger.dataset.assignmentReview);return;}const close=event.target.closest('[data-assignment-close]');if(close)close.closest('dialog')?.close();});
 dialogs.forEach(dialog=>{dialog.addEventListener('close',()=>{if(!dialogs.some(item=>item.open))document.documentElement.classList.remove('assignment-review-modal-open');});dialog.addEventListener('click',event=>{if(event.target!==dialog)return;const box=dialog.getBoundingClientRect();if(event.clientX<box.left||event.clientX>box.right||event.clientY<box.top||event.clientY>box.bottom)dialog.close();});});
 if(root.dataset.reviewReopen){open(root.dataset.reviewReopen);const step=[...document.querySelectorAll('[data-assignment-id]')].find(item=>item.dataset.assignmentId===root.dataset.reviewAssignment);const details=step?.querySelector('.assignment-step-review');if(details){details.open=true;step.scrollIntoView({block:'nearest'});}}
})();
