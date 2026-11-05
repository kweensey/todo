$('#task_edit').form({
  refreshFunction: refreshSession,
  successFunction: function() {
    location.reload();
  }
});
