(function(App, $) {
  'use strict';

  const $passwordForm = $('#user_recovery_password');

  if ($passwordForm.length) {
    $passwordForm.form({
      inputClassInvalid: 'is-invalid',
      readyFunction: function(form) {
        form.focus('password', true);
      },
      successFunction: function() {
        alert('Heslo bylo změněno. Nyní se můžete přihlásit.');
        location.assign('/user/login');
      },
      failFunction: function(form, $form, xhr) {
        form
          .reset('password')
          .reset('password_check')
          .focus('password', true);

        App.showRequestError(xhr);
      }
    });
  }
})(window.App, jQuery);
