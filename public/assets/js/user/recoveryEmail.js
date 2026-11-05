$('#user_recovery_email').form({
  inputClassInvalid: 'is-invalid',
  readyFunction: function(form) {
    form.focus('email', true);
  },
  successFunction: function() {
    alert('Pokud účet existuje, odeslali jsme e-mail s pokyny k obnovení hesla.');
    location.href = '/user/login';
  },
  failFunction: function(form) {
    form
      .reset('email')
      .focus('email', true);
  }
});
