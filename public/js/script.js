/***** SCRIPT PERSONALIZZZATO *****/

$(document).ready(function() {
  // espande di default submenu attivo su mobile
  $('#gs-navbar-collapse-1').on('shown.bs.collapse', function () {
    $(this).find('.dropdown.active').addClass('open');
  })
});
