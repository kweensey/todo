(function(App, $) {
  'use strict';

  const $loginForm = $('#user_login');

  if ($loginForm.length) {
    $loginForm.form({
      inputClassInvalid: 'is-invalid',
      readyFunction: function(form) {
        form.focus('email', true);
      },
      successFunction: function() {
        location.assign('/task');
      },
      failFunction: function(form, $form, xhr) {
        form
          .reset('password')
          .focus('password', true);

        App.showRequestError(xhr);
      }
    });
  }
})(window.App, jQuery);
