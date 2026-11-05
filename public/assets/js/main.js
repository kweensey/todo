const modal = new bootstrap.Modal(document.getElementById('modal'));

function modalToggle(body, size = 'md')
{
  $('#modal .modal-dialog')
    .removeClass('modal-sm modal-md modal-lg modal-xl')
    .addClass('modal-' + size)
    .html(body);

  modal.show();
}

$(document).on('click', 'a[data-bs-show="modal"]', function(event) {
  event.preventDefault();

  const size = $(this).data('size') || 'md';

  loadModalWithRefresh(this.href, size);
});

function activeClass()
{
  let path = '';

  window.location.pathname.split('/').slice(1).forEach(function(part) {
    path += '/' + part;

    $('nav [href="' + path + '"]:not(.js-noactive), .nav [href="' + path + '"]:not(.js-noactive)')
      .addClass('active text-primary')
      .attr('aria-current', 'page');
  });
}

function loadModalWithRefresh(url, size, canRefresh = true)
{
  $.get(url)
    .done(function(response) {
      modalToggle(response, size);
    })
    .fail(function(xhr) {
      if (xhr.status !== 401) {
        return;
      }

      if (! canRefresh) {
        location.assign('/user/login');

        return;
      }

      refreshSession().then(
        () => loadModalWithRefresh(url, size, false),
        () => location.assign('/user/login')
      );
    });
}

try {
  sessionStorage.removeItem('todo-session-recovery-attempt');
} catch (_) {
  // sessionStorage unavailable
}

$('#user_logout').form({
  successFunction: function() {
    location.href = '/user/login';
  }
});

activeClass();
