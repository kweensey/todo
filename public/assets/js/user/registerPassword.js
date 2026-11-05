$('#user_register_password').form({
  inputClassInvalid: 'is-invalid',
  readyFunction: function(form) {
    form.focus('password', true);
  },
  successFunction: function() {
    alert('Registrace dokončena. Nyní se můžete přihlásit.');
    location.href = '/user/login';
  },
  failFunction: function(form) {
    form
      .reset('password')
      .reset('password_check')
      .focus('password', true);
  }
});
