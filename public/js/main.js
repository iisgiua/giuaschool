/***** SCRIPT PERSONALIZZZATO (pagine amministratore) *****/

/* Data e orario non devono restare aperti insieme:
   - aprendo un datepicker si chiude lo spinner orario eventualmente aperto;
   - aprendo lo spinner orario si chiude il datepicker aperto.
   Bootstrap Italia ferma la propagazione del click sia sul pulsante orario sia
   su quello del calendario: il datepicker non si chiude da solo al click
   esterno, quindi lo chiudiamo qui. Gli id dei campi arrivano come secondo
   argomento degli eventi ab.datepicker.opened/closed. */
$(document).ready(function () {
  // chiude tutti gli spinner orari aperti usando il pulsante orario di BI,
  // cosi' il valore impostato viene riportato nel campo prima della chiusura
  function chiudiSpinner() {
    $('.spinner-control.is-open').each(function () {
      var pulsante = $(this).closest('.time-spinner').find('.btn-time');
      if (pulsante.length) {
        pulsante.trigger('click');
      }
    });
  }

  // id del campo datepicker attualmente aperto
  var campoDataAperto = null;

  // All'apertura Bootstrap Italia mette a fuoco la griglia del calendario: per
  // mostrarla il browser scorre l'area che la contiene e la pagina sembra
  // spostarsi (il campo si muove, a volte anche fuori schermo). Memorizzo lo
  // scorrimento subito prima dell'apertura e lo ripristino appena e' aperto.
  var scorrimento = null;

  // Un elemento e' scorrevole se il CSS non lascia l'overflow visibile
  // (area contenuti, corpo delle modali, ...).
  function scorrevole(elemento) {
    var stile = window.getComputedStyle(elemento);
    return stile.overflowX !== 'visible' || stile.overflowY !== 'visible';
  }

  function leggiScorrimento(idCampo) {
    var posizioni = [];
    var nodo = idCampo ? document.getElementById(idCampo) : null;
    while (nodo && nodo !== document.body) {
      if (scorrevole(nodo)) {
        posizioni.push({ elemento: nodo, sinistra: nodo.scrollLeft, alto: nodo.scrollTop });
      }
      nodo = nodo.parentElement;
    }
    posizioni.push({ elemento: null, sinistra: window.scrollX, alto: window.scrollY });
    return posizioni;
  }

  function ripristinaScorrimento() {
    if (!scorrimento) {
      return;
    }
    var posizioni = scorrimento;
    var applica = function () {
      posizioni.forEach(function (posizione) {
        if (posizione.elemento) {
          posizione.elemento.scrollLeft = posizione.sinistra;
          posizione.elemento.scrollTop = posizione.alto;
        } else {
          window.scrollTo(posizione.sinistra, posizione.alto);
        }
      });
    };
    // il browser scorre subito e lo rifa' nel frame successivo: ripristino
    // all'apertura e di nuovo al frame seguente
    applica();
    requestAnimationFrame(applica);
    scorrimento = null;
  }

  $(document).on('ab.datepicker.opening', function (evento, id) {
    scorrimento = leggiScorrimento(id);
  });

  $(document).on('ab.datepicker.opened', function (evento, id) {
    chiudiSpinner();
    campoDataAperto = id || null;
    ripristinaScorrimento();
  });

  $(document).on('ab.datepicker.closed', function () {
    campoDataAperto = null;
    scorrimento = null;
  });

  // Il click sul pulsante orario di BI chiama stopPropagation(), quindi un
  // handler delegato su document non riceverebbe l'evento: ascolto in fase di
  // capture, che scatta prima e non viene bloccata.
  document.addEventListener('click', function (evento) {
    if (!campoDataAperto) {
      return;
    }
    if ($(evento.target).closest('.it-timepicker-wrapper .btn-time').length) {
      var campo = document.getElementById(campoDataAperto);
      if (campo) {
        $(campo).datepicker('hide');
      }
      campoDataAperto = null;
    }
  }, true);
});
