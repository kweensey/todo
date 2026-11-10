(function(App) {
  'use strict';

  let refreshRequest = null;

  function refreshSession()
  {
    if (refreshRequest) {
      return refreshRequest;
    }

    if (! navigator.locks || ! navigator.locks.request) {
      return Promise.reject(
        new Error('Cross-tab refresh coordination unavailable')
      );
    }

    refreshRequest = navigator.locks.request('todo-session-refresh', async function() {
      const check = await fetch('/auth/session/check', {
        credentials: 'same-origin',
        cache: 'no-store'
      });

      if (check.status === 204) {
        return;
      }

      if (check.status !== 401) {
        throw new Error('Cannot check authentication state');
      }

      const csrfCookie = document.cookie
        .split('; ')
        .find(row => row.startsWith('__Host-csrf_token='));

      if (! csrfCookie) {
        throw new Error('Missing CSRF token');
      }

      const response = await fetch('/auth/session/refresh', {
        method: 'POST',
        credentials: 'same-origin',
        cache: 'no-store',
        headers: {
          'X-CSRF-Token': csrfCookie.slice('__Host-csrf_token='.length)
        }
      });

      if (response.status !== 204) {
        throw new Error('Session refresh failed');
      }
    });

    refreshRequest.then(
      () => { refreshRequest = null; },
      () => { refreshRequest = null; }
    );

    return refreshRequest;
  }

  App.session = {
    refresh: refreshSession,
    recoveryKey: 'todo-session-recovery-attempt'
  };
})(window.App = window.App || {});
