$('#user_change_password').form({
  refreshFunction: refreshSession,
  messageScrollOffsetTop: false,
  inputClassInvalid: 'is-invalid',
  readyFunction: function(form) {
    form.focus('password_current', true);
  },
  successFunction: function() {
    alert('Heslo bylo změněno. Přihlaste se znovu.');
    location.href = '/user/login';
  },
  failFunction: function(form) {
    form
      .reset('password_current')
      .reset('password_new')
      .reset('password_check')
      .focus('password_current', true);
  }
});
