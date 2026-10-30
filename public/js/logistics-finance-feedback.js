(() => {
  'use strict';
  const messages = {
    'Save hub default': 'Save this prospective hub salary default? Individual overrides and generated obligations remain unchanged.',
    'Use hub default': 'Use this Staff member’s hub default from the next period? Historical salary obligations stay unchanged.',
    'Confirm generation': 'Create the eligible individual salary obligations shown in this preview? No payment is sent.',
    'Confirm preparation': 'Reserve verified Staff payroll funds for the eligible instructions shown? No payment is sent.',
    'Save salary rate': 'Save this monthly salary? Generated obligations retain their original amount. A revised rate applies from the next period.',
    'Generate period': 'Generate this salary obligation? This does not send or record payment.',
    'Record actual payment': 'Record salary already sent through GCash? Check the recipient, exact amount, actual transfer date, reference and proof. No money is sent by this action.',
    'Acknowledge receipt': 'Confirm you actually received this salary in your GCash account?',
    'Record actual payout': 'Record an actual GCash payment? Confirm the exact amount, recipient, reference and proof. This action records money already sent; it does not send money.',
    'Verify treasury receipt': 'Confirm actual receipt in the company account? Staff release alone is not proof the account received money.',
    'Prepare and reserve funds': 'Prepare this payout and reserve its available funds? No payment is sent by preparation.',
    'Save compensation rates': 'Apply these rates to future assignments? Existing assignment snapshots keep their original amounts.',
    'Save receiving account': 'Save this GCash receiving account? Existing payment instructions will keep their original account details.',
    'Update receiving account': 'Update the receiving account? Existing payment instructions will keep their original account details.',
    'Approve regional rate': 'Approve this regional rate? It will apply to future shipment handoffs for this region.',
    'Approve rate revision': 'Approve this rate revision? Existing obligations keep their snapshotted rate.',
    'Record verified contribution': 'Record a separately received company contribution? Check its reference and proof. Do not enter COD or shipping receipts again.'
  };
  document.querySelectorAll('.settlement-workspace form[method="POST"], .finance-overview form[method="POST"]').forEach(form => {
    form.addEventListener('submit', event => {
      if (event.defaultPrevented) return;
      if (form.dataset.financeBusy) { event.preventDefault(); return; }
      const button = event.submitter || form.querySelector('button');
      const label = button?.textContent.trim();
      let message = messages[label];
      if (form.elements.action?.value === 'verify') message = 'Verify this payment instruction after checking the recipient and funding? This does not send money.';
      if (form.elements.action?.value === 'cancel') message = 'Cancel this unsent instruction and release its reservation? Do not cancel a payment already sent externally.';
      if (message && !window.confirm(message)) { event.preventDefault(); return; }
      form.dataset.financeBusy = 'true';
      form.setAttribute('aria-busy', 'true');
      if (button) {
        button.dataset.financeLabel = label;
        button.disabled = true;
        button.textContent = 'Savingâ€¦';
      }
    });
  });
  window.addEventListener('pageshow', () => {
    document.querySelectorAll('[data-finance-busy]').forEach(form => {
      delete form.dataset.financeBusy;
      form.removeAttribute('aria-busy');
      form.querySelectorAll('[data-finance-label]').forEach(button => {
        button.disabled = false;
        button.textContent = button.dataset.financeLabel;
        delete button.dataset.financeLabel;
      });
    });
  });
})();
