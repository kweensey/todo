(function(App, $) {
  'use strict';

  const modalElement = document.getElementById('modal');
  const userActionMessages = {
    user_invite: {
      confirm: 'Odeslat tomuto uživateli pozvánku?',
      success: 'Pozvánka byla odeslána.'
    },
    user_block: {
      confirm: 'Opravdu chcete zablokovat tohoto uživatele?',
      success: 'Uživatel byl zablokován.'
    },
    user_unblock: {
      confirm: 'Opravdu chcete odblokovat tohoto uživatele?',
      success: 'Uživatel byl odblokován.'
    },
    user_delete: {
      confirm: 'Opravdu chcete smazat tohoto uživatele?',
      success: 'Uživatel byl smazán.'
    }
  };

  Object.entries(userActionMessages).forEach(function([className, messages]) {
    $('.' + className).each(function() {
      this.addEventListener('submit', function(event) {
        if (! confirm(messages.confirm)) {
          event.preventDefault();
          event.stopImmediatePropagation();
        }
      }, true);

      $(this).form({
        inputClassInvalid: 'is-invalid',
        refreshFunction: App.session.refresh,
        successFunction: function() {
          alert(messages.success);
          location.reload();
        },
        failFunction: function(form, $form, xhr) {
          App.showRequestError(xhr, true);
        }
      });
    });
  });

  modalElement.addEventListener('modal:loaded', function() {
    const $editForm = $(modalElement).find('#user_edit');

    if ($editForm.length) {
      $editForm.form({
        inputClassInvalid: 'is-invalid',
        messageScrollOffsetTop: false,
        refreshFunction: App.session.refresh,
        readyFunction: function(form) {
          const focusEmail = function() {
            if ($editForm[0].isConnected && form.input('email')[0].value === '') {
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
          alert('Uživatel byl uložen.');
          location.reload();
        },
        failFunction: function(form, $form, xhr) {
          App.showRequestError(xhr, true);
        }
      });
    }
  });
})(window.App, jQuery);
