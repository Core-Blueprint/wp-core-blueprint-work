(() => {
  'use strict';

  const model = document.querySelector('[data-cb-work-pricing-model]');
  const recurring = document.querySelector('[data-cb-work-recurring-period]');
  const taxMode = document.querySelector('[data-cb-work-tax-mode]');
  const taxRate = document.querySelector('[data-cb-work-tax-rate]');

  const sync = () => {
    if (model && recurring) {
      recurring.hidden = model.value !== 'recurring';
    }
    if (taxMode && taxRate) {
      taxRate.hidden = taxMode.value === 'exempt';
    }
  };

  model?.addEventListener('change', sync);
  taxMode?.addEventListener('change', sync);
  sync();
})();
