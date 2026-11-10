(function(App, $) {
  'use strict';

  const $emailForm = $('#user_recovery_email');

  if ($emailForm.length) {
    $emailForm.form({
      inputClassInvalid: 'is-invalid',
      readyFunction: function(form) {
        form.focus('email', true);
      },
      successFunction: function() {
        alert('Pokud účet existuje, odeslali jsme e-mail s pokyny k obnovení hesla.');
        location.assign('/user/login');
      },
      failFunction: function(form, $form, xhr) {
        form.focus('email', true);
        App.showRequestError(xhr);
      }
    });
  }
})(window.App, jQuery);
