/***** SCRIPT PERSONALIZZZATO *****/

$(document).ready(function() {
  // espande di default submenu attivo su mobile
  $('#gs-navbar-collapse-1').on('shown.bs.collapse', function () {
    $(this).find('.dropdown.active').addClass('open');
  });
  // forza chiamata POST per link
  $('.gs-post-link').click(postLink);
});

setTimeout(() => {
  document.location.reload();
}, 3600000);

function logoutGoogle() {
  var w = window.open('https://www.google.com/accounts/Logout?continue=https://appengine.google.com/_ah/logout','Logout','width=10,height=10,menubar=no,status=no,location=no,toolbar=no,scrollbars=no,top=200,left=200,noopener');
  setTimeout(function() {
    if (w) {
      w.close();
    }
    window.location="/logout";
  }, 3000);
}

// forza chiamata POST per link
function postLink(e) {
  var el = $(this);
  var url = el.attr('href') || el.attr('data-href');
  if (!url || url === '#') {
    return;
  }
  e.preventDefault();
  // crea il form e l'input CSRF al volo, poi esegue il submit
  $('<form>', {
    action: url,
    method: 'POST',
    style: 'display: none;'
  }).append($('<input>', {
    type: 'hidden',
    name: '_csrf_token',
    value: el.attr('data-csrf') || ''
  })).appendTo('body').submit();
}
