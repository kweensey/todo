$('#user_edit').form({
  refreshFunction: refreshSession,
  inputClassInvalid: 'is-invalid',
  successFunction: function() {
    location.reload();
  }
});
