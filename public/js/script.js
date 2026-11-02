/***** SCRIPT PERSONALIZZZATO *****/

$(document).ready(function() {
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
  // barra strumenti di pagina (registro firme, agenda): resta su una riga
  // finche' navigazione, vista e azioni ci stanno davvero; quando il blocco
  // dei comandi va a capo la barra torna impilata a tutta larghezza
  // (.gs-toolbar-stacked), invece di lasciare un buco accanto alla data.
  // La misura si fa sempre da capo: prima si toglie la classe, altrimenti
  // la barra resterebbe impilata anche quando lo spazio torna a bastare.
  function adattaBarraStrumenti() {
    $('.gs-page-toolbar, .gs-assenze-toolbar').each(function() {
      var nav = this.querySelector('.gs-date-nav');
      var comandi = this.querySelector('.gs-toolbar-controls');

      if (!nav || !comandi) {
        return;
      }
      $(this).removeClass('gs-toolbar-stacked');
      if (comandi.getBoundingClientRect().top > nav.getBoundingClientRect().top + 1) {
        $(this).addClass('gs-toolbar-stacked');
      }
    });
  }

  adattaBarraStrumenti();
  $(window).on('load', adattaBarraStrumenti);
  var attesaBarra;
  $(window).on('resize', function() {
    clearTimeout(attesaBarra);
    attesaBarra = setTimeout(adattaBarraStrumenti, 100);
  });
});

setTimeout(() => {
  document.location.reload();
}, 3600000);
