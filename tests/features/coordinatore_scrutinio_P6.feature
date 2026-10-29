# language: it

Funzionalità: sesto passo dello scrutinio del primo periodo
  Per svolgere il sesto passo dello scrutinio del primo periodo
  Come utente staff
  Bisogna controllare visualizzazione della pagina
  Bisogna controllare visualizzazione con la classe articolata
  Utilizzando "_scrutinioP6Fixtures.yml"


Contesto: login utente staff
	Dato login utente con ruolo esatto "Staff"


################################################################################
# Bisogna controllare visualizzazione della pagina

Scenario: visualizzazione pagina passo 6
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Allora la sezione "#gs-main h2" contiene "Passo 6"
  E la sezione "#gs-main .gs-verbale .gs-verbale-sezione .gs-verbale-titolo" contiene "Punto secondo. Situazioni particolari da segnalare"
  E la sezione "#gs-main .gs-verbale .gs-verbale-contenuto" contiene "Nessun testo inserito"

Scenario: visualizzazione riquadro inserimento dati
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "Scrivi"
  Allora la sezione "#gs-main #gs-modal-remote h3.modal-title" contiene "Punto secondo all'ordine del giorno"
  E la sezione "#gs-main #gs-modal-remote h4.modal-title" contiene "Situazioni particolari da segnalare"

Scenario: visualizzazione pagina con dati mancanti
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando inserisci "" nel campo "scrutinio_fine"
  E click su "Chiudi lo scrutinio"
  Allora la sezione "#gs-modal-error .alert-danger" contiene "/Ora della fine.*Numero del verbale.*inserire o confermare/ui"

Scenario: visualizzazione passo precedente
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "passo precedente"
  Allora la sezione "#gs-main h2" contiene "Passo 5"

Scenario: visualizzazione passo successivo
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando inserisci "3" nel campo "scrutinio_numeroVerbale"
  E inserisci "11:30" nel campo "scrutinio_fine"
  E click su "Scrivi"
  E inserisci "Testo del secondo punto del verbale" nel campo "verbale_testo"
  E click su "Conferma"
  E click su "Chiudi lo scrutinio"
  Allora vedi la pagina "coordinatore_scrutinio" con parametri:
    | classe        | stato |
    | @classe_1A:id | C     |
  Allora la sezione "#gs-main h2" contiene "Scrutinio chiuso"

Scenario: memorizzazione dati e passo successivo
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando inserisci "3" nel campo "scrutinio_numeroVerbale"
  E inserisci "11:30" nel campo "scrutinio_fine"
  E click su "Scrivi"
  E inserisci "Testo del secondo punto del verbale" nel campo "verbale_testo"
  E click su "Conferma"
  E click su "Chiudi lo scrutinio"
  E click su "passo precedente"
  Allora la sezione "#gs-main h2" contiene "Passo 6"
  E il campo "scrutinio_numeroVerbale" contiene "3"
  E il campo "scrutinio_fine" contiene "11:30"
  E la sezione "#gs-main .gs-verbale .gs-verbale-contenuto" contiene "Testo del secondo punto del verbale"


################################################################################
# Bisogna controllare visualizzazione con la classe articolata

Scenario: visualizzazione classe articolata
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe           |
    | @classe_3CAMB:id |
  Allora la sezione "#gs-main h2" contiene "Passo 6"
  E la sezione "#gs-main .gs-verbale .gs-verbale-sezione .gs-verbale-titolo" contiene "Punto secondo. Situazioni particolari da segnalare"
  E la sezione "#gs-main .gs-verbale .gs-verbale-contenuto" contiene "Nessun testo inserito"

Scenario: visualizzazione riquadro inserimento debiti
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe           |
    | @classe_3CAMB:id |
  Quando click su "Scrivi"
  Allora la sezione "#gs-main #gs-modal-remote h3.modal-title" contiene "Punto secondo all'ordine del giorno"
  E la sezione "#gs-main #gs-modal-remote h4.modal-title" contiene "Situazioni particolari da segnalare"
