/***** SCRIPT PERSONALIZZZATO *****/

$(document).ready(function() {
  // espande di default submenu attivo su mobile
  $('#gs-navbar-collapse-1').on('shown.bs.collapse', function () {
    $(this).find('.dropdown.active').addClass('open');
  })
  // gestisce toggle visibilità password
  $('.password-icon').on('click', function() {
    var $input = $(this).prev('input');
    var $visible = $(this).find('.password-icon-visible');
    var $invisible = $(this).find('.password-icon-invisible');

    if ($input.attr('type') === 'password') {
      $input.attr('type', 'text');
      $visible.addClass('d-none');
      $invisible.removeClass('d-none');
    } else {
      $input.attr('type', 'password');
      $visible.removeClass('d-none');
      $invisible.addClass('d-none');
    }
  });
  // dropdown-azioni nelle celle delle tabelle: il menu si apre verso il
  // basso e sta sopra la tabella. La cornice tonda (.table overflow hidden)
  // e il contenitore scrollabile (.table-responsive) lo taglierebbero:
  // mentre e' aperto si toglie il ritaglio, alla chiusura si ripristina
  $(document).on('show.bs.dropdown', '.gs-azioni-dropdown', function() {
    $(this).closest('.table-responsive').addClass('gs-dropdown-aperto');
    $(this).closest('table').addClass('gs-dropdown-aperto');
  });
  $(document).on('hidden.bs.dropdown', '.gs-azioni-dropdown', function() {
    $(this).closest('.table-responsive').removeClass('gs-dropdown-aperto');
    $(this).closest('table').removeClass('gs-dropdown-aperto');
  });
});

setTimeout(() => {
  document.location.reload();
}, 3600000);

function logoutGoogle() {
  var w = window.open('https://www.google.com/accounts/Logout?continue=https://appengine.google.com/_ah/logout','Logout','width=10,height=10,menubar=no,status=no,location=no,toolbar=no,scrollbars=no,top=200,left=200');
  setTimeout(function() {
    if (w) {
      w.close();
    }
    window.location="/logout";
  }, 3000);
}
