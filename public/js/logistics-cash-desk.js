(() => {
  'use strict';
  const workspace = document.querySelector('.cash-workspace');
  if (!workspace) return;
  const transfer = workspace.querySelector('[data-cash-transfer]');
  if (transfer) {
    const origin = transfer.elements.hub_id;
    const destination = transfer.elements.destination_hub_id;
    const amount = transfer.elements.amount;
    const hint = transfer.querySelector('#transfer-balance');
    const money = cents => new Intl.NumberFormat('en-PH', {style: 'currency', currency: 'PHP'}).format(cents / 100);
    const update = () => {
      for (const option of destination.options) option.disabled = !!option.value && option.value === origin.value;
      destination.setCustomValidity(origin.value && origin.value === destination.value ? 'Choose a destination different from the origin hub.' : '');
      const selected = origin.selectedOptions[0];
      if (!origin.value) { hint.textContent = 'Select an origin to see recorded on-hand cash.'; return; }
      const cents = Number(selected.dataset.onHand);
      const requested = Math.round(Number(amount.value) * 100);
      hint.textContent = `Recorded on hand: ${money(cents)}.${requested > cents ? ' Requested amount exceeds current cash. Staff cannot release it unless sufficient cash is available.' : ' Staff will recheck this balance at release.'}`;
      hint.classList.toggle('cash-warning', requested > cents);
    };
    transfer.addEventListener('input', update);
    transfer.addEventListener('change', update);
    update();
  }
  const php = cents => new Intl.NumberFormat('en-PH', {style:'currency',currency:'PHP'}).format(cents / 100);
  workspace.querySelectorAll('form[data-remittance-rider]').forEach(form => {
    const preview = form.querySelector('[data-count-preview]');
    const update = () => {
      if (!form.elements.counted.value) { preview.textContent = 'Enter the actual cash counted, then check the collection allocations below.'; return; }
      const counted = Math.round(Number(form.elements.counted.value) * 100);
      const expected = Number(form.dataset.expectedCents);
      if (!Number.isFinite(counted)) return;
      const allocated = [...form.querySelectorAll('input[name^="allocations["]')].reduce((sum, input) => sum + Math.round(Number(input.value || 0) * 100), 0);
      const difference = counted - expected;
      preview.textContent = `${difference < 0 ? 'Shortage: '+php(-difference) : difference > 0 ? 'Overage: '+php(difference)+' (unallocated)' : 'Exact count: '+php(counted)}. Allocate ${php(Math.min(counted, expected))}; currently allocated ${php(allocated)}. This is a preview until saved.`;
      form.elements.notes.required = difference !== 0;
    };
    form.addEventListener('input', update);
    update();
  });
  workspace.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', event => {
      if (form.dataset.busy === 'true') { event.preventDefault(); return; }
      const button = event.submitter || form.querySelector('button');
      const label = button?.textContent.trim() || 'Submit';
      const messages = {
        'Authorize transfer': 'Authorize this transfer? No cash moves or is reserved until origin Hub Staff records physical release.',
        'Receive & Count Cash': 'Record this physical count and its allocations? Counts are final; a difference requires documented review.',
        'Confirm cash release': 'Confirm the cash was physically released? It will leave origin custody and await destination receipt.',
        'Confirm cash receipt': 'Confirm the cash was physically received and counted at your assigned hub?',
        'Confirm treasury cash release': 'Confirm this COD cash was physically released for the treasury handover?',
        'Document resolution': 'Document this decision? Cash amounts and outstanding liabilities will remain unchanged.',
        'Withdraw uncounted submission': 'Withdraw this uncounted submission? The collection remains outstanding.'
      };
      const message = label === 'Receive & Count Cash' && form.dataset.remittanceRider
        ? `Receive & Count Cash\nRider: ${form.dataset.remittanceRider}\nReceiving Staff: ${form.dataset.receivingStaff}\nSubmitted: ${php(Number(form.dataset.expectedCents))}\nActual count: ${php(Math.round(Number(form.elements.counted.value) * 100))}\n${form.querySelector('[data-count-preview]').textContent}\nSave this final count? Receipt time is recorded on successful save.`
        : messages[label];
      if (message && !window.confirm(message)) { event.preventDefault(); return; }
      form.dataset.busy = 'true';
      form.setAttribute('aria-busy', 'true');
      if (button) { button.dataset.cashLabel = label; button.disabled = true; button.textContent = 'Saving…'; }
    });
  });
  window.addEventListener('pageshow', () => {
    workspace.querySelectorAll('form[data-busy]').forEach(form => {
      delete form.dataset.busy;
      form.removeAttribute('aria-busy');
      form.querySelectorAll('[data-cash-label]').forEach(button => {
        button.disabled = false;
        button.textContent = button.dataset.cashLabel;
        delete button.dataset.cashLabel;
      });
    });
  });
})();
