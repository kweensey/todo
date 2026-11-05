$('#user_login').form({
  inputClassInvalid: 'is-invalid',
  readyFunction: function(form) {
    form.focus('email', true);
  },
  successFunction: function() {
    location.href = '/task';
  },
  failFunction: function(form) {
    form
      .reset('password')
      .focus('password', true);
  }
});
