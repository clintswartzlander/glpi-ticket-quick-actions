(function () {
  'use strict';

  var handlerFlag = '__glpiQuickactionsClickHandlerRegistered';
  if (window[handlerFlag]) {
    return;
  }
  window[handlerFlag] = true;

  document.addEventListener('click', function (event) {
    if (!(event.target instanceof Element)) {
      return;
    }

    var button = event.target.closest('button[data-quickactions-control="true"]');
    if (!button || button.dataset.quickactionsBusy === 'true') {
      return;
    }

    event.preventDefault();
    var confirmation = button.dataset.quickactionsConfirmation;
    if (confirmation && !window.confirm(confirmation)) {
      button.disabled = false;
      button.removeAttribute('aria-busy');
      delete button.dataset.quickactionsBusy;
      return;
    }

    button.dataset.quickactionsBusy = 'true';
    button.disabled = true;
    button.setAttribute('aria-busy', 'true');

    var container = button.closest('#itil-object-container');
    var csrfInput = container
      ? container.querySelector('input[name="_glpi_csrf_token"]')
      : null;

    if (!csrfInput) {
      csrfInput = document.querySelector(
        'form input[name="_glpi_csrf_token"]'
      );
    }

    if (!csrfInput || csrfInput.value.trim() === '') {
      button.disabled = false;
      button.removeAttribute('aria-busy');
      delete button.dataset.quickactionsBusy;
      console.error('Quick Actions: GLPI CSRF token not found.');
      return;
    }

    var form = document.createElement('form');
    form.method = 'post';
    form.action = button.dataset.quickactionsEndpoint;
    form.hidden = true;
    form.setAttribute('aria-hidden', 'true');

    var fields = {
      _glpi_csrf_token: csrfInput.value,
      tickets_id: button.dataset.quickactionsTicketId,
      action: button.dataset.quickactionsAction
    };

    Object.keys(fields).forEach(function (name) {
      var input = document.createElement('input');
      input.type = 'hidden';
      input.name = name;
      input.value = fields[name];
      form.appendChild(input);
    });

    document.body.appendChild(form);
    form.submit();

    // Navigation normally replaces the document. Removing on the next task also
    // keeps the current DOM clean if submission is intercepted by the browser.
    window.setTimeout(function () {
      form.remove();
    }, 0);
  });
}());
