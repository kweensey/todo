(function(App, $) {
  'use strict';

  const modalElement = document.getElementById('modal');

  $(document).on('change.todoTaskStatus', '.task_status [name="completed"]', function() {
    $(this.form).trigger('submit');
  });

  $('.task_status').each(function() {
    $(this).form({
      inputClassInvalid: 'is-invalid',
      refreshFunction: App.session.refresh,
      failFunction: function(form, $form, xhr) {
        $form
          .find('[name="completed"]')
          .prop('checked', function() {
            return this.defaultChecked;
          });

        App.showRequestError(xhr, true);
      },
      successFunction: function() {
        location.reload();
      }
    });
  });

  $('.task_delete').each(function() {
    this.addEventListener('submit', function(event) {
      if (! confirm('Opravdu chcete smazat tento úkol?')) {
        event.preventDefault();
        event.stopImmediatePropagation();
      }
    }, true);

    $(this).form({
      inputClassInvalid: 'is-invalid',
      refreshFunction: App.session.refresh,
      successFunction: function() {
        location.reload();
      },
      failFunction: function(form, $form, xhr) {
        App.showRequestError(xhr, true);
      }
    });
  });

  modalElement.addEventListener('modal:loaded', function() {
    const $editForm = $(modalElement).find('#task_edit');

    if ($editForm.length) {
      $editForm.form({
        inputClassInvalid: 'is-invalid',
        messageScrollOffsetTop: false,
        refreshFunction: App.session.refresh,
        readyFunction: function(form) {
          const focusTitle = function() {
            if ($editForm[0].isConnected) {
              form.focus('title', true);
            }
          };

          if (modalElement.classList.contains('show')) {
            focusTitle();
          } else {
            modalElement.addEventListener('shown.bs.modal', focusTitle, {once: true});
          }
        },
        successFunction: function() {
          alert('Úkol byl uložen.');
          location.reload();
        },
        failFunction: function(form, $form, xhr) {
          App.showRequestError(xhr, true);
        }
      });
    }
  });
})(window.App, jQuery);
