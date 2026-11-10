(function(App, $) {
  'use strict';

  const modalElement = document.getElementById('modal');
  const $logoutForm = $('#user_logout');

  if ($logoutForm.length) {
    $logoutForm.form({
      inputClassInvalid: 'is-invalid',
      successFunction: function() {
        location.assign('/user/login');
      },
      failFunction: function(form, $form, xhr) {
        App.showRequestError(xhr);
      }
    });
  }

  modalElement.addEventListener('modal:loaded', function() {
    const $emailForm = $(modalElement).find('#user_change_email');

    if ($emailForm.length) {
      $emailForm.form({
        refreshFunction: App.session.refresh,
        inputClassInvalid: 'is-invalid',
        messageScrollOffsetTop: false,
        readyFunction: function(form) {
          const focusEmail = function() {
            if ($emailForm[0].isConnected) {
              form.focus('email', true);
            }
          };

          if (modalElement.classList.contains('show')) {
            focusEmail();
          } else {
            modalElement.addEventListener('shown.bs.modal', focusEmail, {once: true});
          }
        },
        successFunction: function() {
          App.modal.open('/user/me/change/email/confirm', {
            size: 'sm',
            authenticated: true
          });
        },
        failFunction: function(form, $form, xhr) {
          App.showRequestError(xhr, true);
        }
      });
    }

    const $passwordForm = $(modalElement).find('#user_change_password');

    if ($passwordForm.length) {
      $passwordForm.form({
        refreshFunction: App.session.refresh,
        inputClassInvalid: 'is-invalid',
        messageScrollOffsetTop: false,
        readyFunction: function(form) {
          const focusPassword = function() {
            if ($passwordForm[0].isConnected) {
              form.focus('password_current', true);
            }
          };

          if (modalElement.classList.contains('show')) {
            focusPassword();
          } else {
            modalElement.addEventListener('shown.bs.modal', focusPassword, {once: true});
          }
        },
        successFunction: function() {
          alert('Heslo bylo změněno. Přihlaste se znovu.');
          location.assign('/user/login');
        },
        failFunction: function(form, $form, xhr) {
          form
            .reset('password_current')
            .reset('password_new')
            .reset('password_check')
            .focus('password_current', true);

          App.showRequestError(xhr, true);
        }
      });
    }

    const $challengeForm = $(modalElement).find('#challenge');

    if ($challengeForm.length) {
      $challengeForm.form({
        refreshFunction: App.session.refresh,
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
          alert('E-mailová adresa byla změněna.');
          location.reload();
        },
        failFunction: function(form, $form, xhr) {
          form
            .reset('code')
            .focus('code', true);

          App.showRequestError(xhr, true);
        }
      });
    }
  });
})(window.App, jQuery);
