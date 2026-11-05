$('#user_change_email').form({
  refreshFunction: refreshSession,
  messageScrollOffsetTop: false,
  inputClassInvalid: 'is-invalid',
  readyFunction: function(form) {
    form.focus('email', true);
  },
  successFunction: function() {
    $.get('/user/me/change/email/confirm', function(response) {
      modalToggle(response, 'sm');

      $('#challenge').form({
        refreshFunction: refreshSession,
        messageScrollOffsetTop: false,
        inputClassInvalid: 'is-invalid',
        readyFunction: function(form) {
          form.focus('code', true);
        },
        successFunction: function() {
          location.reload();
        },
        failFunction: function(form) {
          form
            .reset('code')
            .focus('code', true);
        }
      });
    });
  }
});
