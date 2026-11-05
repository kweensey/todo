(function() {
  'use strict';

  const key = 'todo-session-recovery-attempt';
  const destination = location.pathname + location.search + location.hash;

  // One attempt per navigation. A successful normal page clears this marker.
  // If sessionStorage is unavailable, fail closed rather than risk a loop.
  try {
    if (sessionStorage.getItem(key) === destination) {
      location.replace('/user/login');
      return;
    }

    sessionStorage.setItem(key, destination);
  } catch (_) {
    location.replace('/user/login');
    return;
  }

  refreshSession().then(
    () => location.replace(destination),
    () => location.replace('/user/login')
  );
})();
