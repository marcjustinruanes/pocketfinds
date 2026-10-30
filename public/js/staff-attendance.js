(() => {
  'use strict';
  document.querySelectorAll('form[data-attendance-confirm]').forEach(form => {
    form.addEventListener('submit', event => {
      if (form.dataset.attendanceBusy) { event.preventDefault(); return; }
      if (!window.confirm(form.dataset.attendanceConfirm)) { event.preventDefault(); return; }
      form.dataset.attendanceBusy = 'true'; form.setAttribute('aria-busy','true');
      const button = event.submitter || form.querySelector('button');
      if (button) { button.dataset.attendanceLabel=button.textContent; button.disabled=true; button.textContent='Saving...'; }
    });
  });
  window.addEventListener('pageshow', () => document.querySelectorAll('[data-attendance-busy]').forEach(form => {
    delete form.dataset.attendanceBusy; form.removeAttribute('aria-busy');
    form.querySelectorAll('[data-attendance-label]').forEach(button=>{button.disabled=false;button.textContent=button.dataset.attendanceLabel;});
  }));
})();
