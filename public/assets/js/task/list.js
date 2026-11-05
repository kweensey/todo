$('.task_status').each(function() {
  const $form = $(this);

  $form.form({
    refreshFunction: refreshSession,
    failFunction: function() {
      $form
        .find('[name="completed"]')
        .prop('checked', function(_, checked) {
          return !checked;
        });
    },
    successFunction: function() {
      location.reload();
    }
  });
});

$('.task_status [name="completed"]').on('change', function() {
  $(this.form).trigger('submit');
});

$('.task_delete').each(function() {
  $(this).form({
    refreshFunction: refreshSession,
    successFunction: function() {
      location.reload();
    }
  });
});
