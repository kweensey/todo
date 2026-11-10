(function(App) {
  'use strict';

  function showRequestError(xhr, authenticated = false)
  {
    if (xhr.status === 422) {
      return;
    }

    if (authenticated && xhr.status === 401) {
      location.assign('/user/login');
      return;
    }

    const messages = {
      0: 'Nepodařilo se spojit se serverem.',
      401: 'Ověření selhalo.',
      403: 'Požadavek byl zamítnut.',
      409: 'Požadavek nelze dokončit.',
      429: 'Příliš mnoho pokusů. Zkuste to prosím později.'
    };

    let message = messages[xhr.status] || 'Požadavek se nepodařilo dokončit. Zkuste to prosím znovu.';

    if (xhr.status === 409 || xhr.status === 429) {
      const contentType = xhr.getResponseHeader('Content-Type') || '';

      if (/^text\/plain(?:;|$)/i.test(contentType)) {
        message = xhr.responseText.trim() || message;
      }
    }

    alert(message);
  }

  App.showRequestError = showRequestError;
})(window.App);
