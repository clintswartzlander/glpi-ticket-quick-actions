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
    button.dataset.quickactionsBusy = 'true';
    button.disabled = true;
    button.setAttribute('aria-busy', 'true');

    var form = document.createElement('form');
    form.method = 'post';
    form.action = button.dataset.quickactionsEndpoint;
    form.hidden = true;
    form.setAttribute('aria-hidden', 'true');

    var fields = {
      _glpi_csrf_token: button.dataset.quickactionsCsrfToken,
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
