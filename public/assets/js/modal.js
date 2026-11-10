(function(App) {
  'use strict';

  const modalElement = document.getElementById('modal');
  const dialog = modalElement.querySelector('.modal-dialog');
  const modal = bootstrap.Modal.getOrCreateInstance(modalElement);

  let latestOpen = 0;

  async function load(url, authenticated, canRefresh = true)
  {
    try {
      const response = await fetch(url, {credentials: 'same-origin'});

      if (! response.ok) {
        throw response;
      }

      return await response.text();
    } catch (error) {
      if (! authenticated || error.status !== 401 || ! canRefresh) {
        throw error;
      }

      try {
        await App.session.refresh();
      } catch (_) {
        throw error;
      }

      return load(url, authenticated, false);
    }
  }

  async function open(url, {size = 'md', authenticated = false} = {})
  {
    const requestId = ++latestOpen;

    try {
      const html = await load(url, authenticated);

      if (requestId !== latestOpen) {
        return false;
      }

      dialog.classList.remove('modal-sm', 'modal-md', 'modal-lg', 'modal-xl');

      if (['sm', 'lg', 'xl'].includes(size)) {
        dialog.classList.add('modal-' + size);
      }

      dialog.innerHTML = html;

      modalElement.dispatchEvent(
        new Event('modal:loaded')
      );

      modal.show();

      return true;
    } catch (error) {
      if (requestId !== latestOpen) {
        return false;
      }

      if (authenticated && error.status === 401) {
        location.assign('/user/login');
      } else {
        alert('Formulář se nepodařilo načíst. Zkuste to prosím znovu.');
      }

      return false;
    }
  }

  App.modal = {open};
})(window.App);
