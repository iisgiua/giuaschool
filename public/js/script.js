/***** SCRIPT PERSONALIZZZATO *****/

$(document).ready(function() {
  // espande di default submenu attivo su mobile
  $('#gs-navbar-collapse-1').on('shown.bs.collapse', function () {
    $(this).find('.dropdown.active').addClass('open');
  })
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
