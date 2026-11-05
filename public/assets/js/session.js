// One refresh coordinator shared by normal pages and the 401 recovery document.
// A failed protected request triggers this; normal requests make no extra checks.
let refreshRequest = null;

function refreshSession()
{
  if (refreshRequest) {
    return refreshRequest;
  }

  if (!navigator.locks || !navigator.locks.request) {
    // Never issue an uncoordinated one-time refresh token in another tab.
    return Promise.reject(new Error('Cross-tab refresh coordination unavailable'));
  }

  refreshRequest = navigator.locks.request('todo-session-refresh', async function() {
    // Recheck INSIDE the lock. Another tab may already have rotated the cookie.
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

    if (!csrfCookie) {
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

  // Do not leave an unhandled rejected Promise from the cleanup handler.
  refreshRequest.then(
    () => { refreshRequest = null; },
    () => { refreshRequest = null; }
  );

  return refreshRequest;
}
