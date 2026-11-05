$('#user_register_email').form({
  inputClassInvalid: 'is-invalid',
  readyFunction: function(form) {
    form.focus('email', true);
  },
  successFunction: function() {
    const path = location.pathname.replace(/\/$/, '');

    $.get(path + '/confirm' + location.search, function(response) {
      modalToggle(response, 'sm');

      $('#challenge').form({
        messageScrollOffsetTop: false,
        inputClassInvalid: 'is-invalid',
        readyFunction: function(form) {
          form.focus('code', true);
        },
        successFunction: function() {
          location.href = path + '/password' + location.search;
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
