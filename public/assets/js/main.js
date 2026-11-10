(function(App) {
  'use strict';

  function markActiveNavigation()
  {
    const paths = new Set();
    let path = '';

    location.pathname.split('/').slice(1).forEach(function(part) {
      path += '/' + part;
      paths.add(path);
    });

    document.querySelectorAll('nav a[href]:not(.js-noactive), .nav a[href]:not(.js-noactive)').forEach(function(link) {
      if (paths.has(link.getAttribute('href'))) {
        link.classList.add('active', 'text-primary');
        link.setAttribute('aria-current', 'page');
      }
    });
  }

  document.addEventListener('click', function(event) {
    const link = event.target.closest('a[data-bs-show="modal"]');

    if (link) {
      event.preventDefault();

      App.modal.open(link.href, {
        size: link.dataset.size || 'md',
        authenticated: true
      });
    }
  });

  try {
    sessionStorage.removeItem(App.session.recoveryKey);
  } catch (_) {}

  markActiveNavigation();
})(window.App);
