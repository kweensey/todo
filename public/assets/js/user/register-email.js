(function(App, $) {
  'use strict';

  const path = location.pathname.replace(/\/$/, '');
  const modalElement = document.getElementById('modal');
  const $emailForm = $('#user_register_email');

  if ($emailForm.length) {
    $emailForm.form({
      inputClassInvalid: 'is-invalid',
      readyFunction: function(form) {
        form.focus('email', true);
      },
      successFunction: function() {
        App.modal.open(
          path + '/confirm' + location.search,
          {size: 'sm'}
        );
      },
      failFunction: function(form, $form, xhr) {
        App.showRequestError(xhr);
      }
    });
  }

  modalElement.addEventListener('modal:loaded', function() {
    const $challengeForm = $(modalElement).find('#challenge');

    if ($challengeForm.length) {
      $challengeForm.form({
        inputClassInvalid: 'is-invalid',
        messageScrollOffsetTop: false,
        readyFunction: function(form) {
          const focusCode = function() {
            if ($challengeForm[0].isConnected) {
              form.focus('code', true);
            }
          };

          if (modalElement.classList.contains('show')) {
            focusCode();
          } else {
            modalElement.addEventListener('shown.bs.modal', focusCode, {once: true});
          }
        },
        successFunction: function() {
          location.assign(path + '/password' + location.search);
        },
        failFunction: function(form, $form, xhr) {
          form
            .reset('code')
            .focus('code', true);

          App.showRequestError(xhr);
        }
      });
    }
  });
})(window.App, jQuery);
