/***** NOTIFICHE FLASH *****/
/* I messaggi di sessione vengono resi come notifiche ancorate al bordo
   inferiore: non occupano posto nel flusso, quindi non spostano il contenuto
   e non fanno saltare la pagina. Qui ci sono due comportamenti:

   1. chiusura: col pulsante X, sempre disponibile; in aggiunta le conferme di
      successo si chiudono da sole dopo AUTO_CHIUSURA millisecondi, cosi' la
      conferma del salvataggio non resta li' a coprire il contenuto. Gli errori
      non si autochiudono: un errore che sparisce da solo si perde.

   2. posizione di scorrimento: all'invio di un form il browser riparte
      dall'alto. Salviamo la posizione prima dell'invio e la ripristiniamo al
      caricamento, cosi' si resta dove si era lasciato. */

(function () {
  'use strict';

  var REGIONE = '.gs-flash-region';
  var FLASH = '.gs-flash';
  var PULSANTE_CHIUSURA = '[data-gs-flash-chiudi]';
  var AUTO_CHIUSURA = 6000;      /* successo: 6 secondi */
  var DURATA_CHIUSURA = 250;     /* deve corrispondere a .gs-flash-chiusura in main.css */
  var CHIAVE_POSIZIONE = 'gs:posizioneScorrimento';

  /* ---------------------------------------------------------- chiusura */

  function svuotaRegione() {
    var regione = document.querySelector(REGIONE);
    if (!regione) { return; }
    if (regione.querySelector(FLASH)) { return; }
    if (regione.parentNode) { regione.parentNode.removeChild(regione); }
  }

  function chiudi(notifica, attendere) {
    if (!notifica || notifica.getAttribute('data-gs-flash-chiusa') === '1') { return; }
    notifica.setAttribute('data-gs-flash-chiusa', '1');
    /* l'animazione di uscita va aggiunta in un secondo frame: aggiungendola
       insieme a .gs-flash si annullerebbe a vicenda l'animazione di entrata */
    window.setTimeout(function () {
      notifica.classList.add('gs-flash-chiusura');
    }, 20);
    window.setTimeout(function () {
      if (notifica.parentNode) { notifica.parentNode.removeChild(notifica); }
      svuotaRegione();
    }, attendere || (20 + DURATA_CHIUSURA));
  }

  function collegaChiusura(notifica) {
    var pulsante = notifica.querySelector(PULSANTE_CHIUSURA);
    if (pulsante) {
      pulsante.addEventListener('click', function () { chiudi(notifica); });
    }
    /* solo i tipi che si autochiudono: gli errori restano fino a chiusura manuale */
    if (notifica.getAttribute('data-gs-flash-autochiusura') === '1') {
      window.setTimeout(function () { chiudi(notifica); }, AUTO_CHIUSURA);
    }
  }

  function inizializza() {
    var notifiche = document.querySelectorAll(FLASH);
    Array.prototype.forEach.call(notifiche, collegaChiusura);

    /* delegazione: copre anche le notifiche aggiunte dopo il caricamento */
    document.addEventListener('click', function (evento) {
      var bersaglio = evento.target;
      if (!bersaglio || !bersaglio.closest) { return; }
      var pulsante = bersaglio.closest(PULSANTE_CHIUSURA);
      if (!pulsante) { return; }
      chiudi(pulsante.closest(FLASH));
    });
  }

  /* ------------------------------------------- posizione di scorrimento */

  function posizioneScorrimento() {
    return window.scrollY || window.pageYOffset || document.documentElement.scrollTop || 0;
  }

  /* letta una volta sola e tenuta qui: il valore serve sia al ripristino
     immediato sia a quello successivo al caricamento delle risorse */
  var posizioneDaRipristinare = null;

  function leggiPosizione() {
    if (posizioneDaRipristinare !== null) { return; }
    var salvata;
    try {
      salvata = sessionStorage.getItem(CHIAVE_POSIZIONE);
      /* consumata subito: se la pagina si ricaricasse due volte di fila non
         deve ripartire dalla stessa posizione una seconda volta */
      sessionStorage.removeItem(CHIAVE_POSIZIONE);
    } catch (e) {
      /* navigazione privata o storage non disponibile: si rinuncia */
      return;
    }
    var y = parseInt(salvata, 10);
    if (!isNaN(y) && y > 0) { posizioneDaRipristinare = y; }
  }

  function ripristinaPosizione() {
    leggiPosizione();
    if (posizioneDaRipristinare === null) { return; }
    /* il ripristino automatico del browser riporterebbe comunque la pagina in
       alto: si prende il controllo e si gestisce la posizione da soli */
    if ('scrollRestoration' in history) { history.scrollRestoration = 'manual'; }
    window.scrollTo(0, posizioneDaRipristinare);
    /* immagini e tabelle possono spostare il layout dopo il primo ripristino */
    window.setTimeout(function () {
      window.scrollTo(0, posizioneDaRipristinare);
    }, 120);
  }

  function inizializzaScorrimento() {
    /* in cattura per vedere i form anche se un handler li ferma */
    document.addEventListener('submit', function () {
      try {
        sessionStorage.setItem(CHIAVE_POSIZIONE, String(posizioneScorrimento()));
      } catch (e) { /* senza storage la pagina tornera' in alto: niente altro da fare */ }
    }, true);

    ripristinaPosizione();
    window.addEventListener('load', ripristinaPosizione);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      inizializza();
      inizializzaScorrimento();
    });
  } else {
    inizializza();
    inizializzaScorrimento();
  }
})();