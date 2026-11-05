$('.user_invite, .user_block, .user_unblock, .user_delete').each(function() {
  $(this).form({
    refreshFunction: refreshSession,
    successFunction: function() {
      location.reload();
    }
  });
});
