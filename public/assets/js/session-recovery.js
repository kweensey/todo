(function(App) {
  'use strict';

  const key = App.session.recoveryKey;
  const destination = location.pathname + location.search + location.hash;

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

  App.session.refresh().then(
    () => location.replace(destination),
    () => location.replace('/user/login')
  );
})(window.App);
