# language: it

Funzionalità: nono passo dello scrutinio finale
  Per svolgere il nono passo dello scrutinio finale
  Come utente staff
  Bisogna controllare visualizzazione della pagina
  Bisogna controllare visualizzazione con la classe articolata
  Utilizzando "_scrutinioF9Fixtures.yml"


Contesto: login utente staff
	Dato login utente con ruolo esatto "Staff"


################################################################################
# Bisogna controllare visualizzazione della pagina

Scenario: visualizzazione pagina passo 9
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Allora la sezione "#gs-main h2" contiene "Verbale e chiusura"
  E la sezione "#gs-main form > div:nth-child(2) > div:nth-child(1)" contiene "Punto secondo. Situazioni particolari da segnalare"
  E la sezione "#gs-main form .alert-success > div:nth-child(1)" contiene ""

Scenario: visualizzazione riquadro inserimento dati
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "Conferma"
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
  Allora la sezione "#gs-main h2" contiene "Comunicazioni"

Scenario: visualizzazione passo successivo
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando inserisci "5" nel campo "scrutinio_numeroVerbale"
  E inserisci "11:30" nel campo "scrutinio_fine"
  E click su "Conferma"
  E inserisci "Testo del secondo punto del verbale" nel campo "verbale_testo"
  E click su "Conferma" con indice "2"
  E click su "Chiudi lo scrutinio"
  Allora vedi la pagina "coordinatore_scrutinio" con parametri:
    | classe        | stato |
    | @classe_1A:id | C     |
  E la sezione "#gs-main h2" contiene "Scrutinio chiuso"

Scenario: memorizzazione dati e passo successivo
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando inserisci "3" nel campo "scrutinio_numeroVerbale"
  E inserisci "11:30" nel campo "scrutinio_fine"
  E click su "Conferma"
  E inserisci "Testo del secondo punto del verbale" nel campo "verbale_testo"
  E click su "Conferma" con indice "2"
  E click su "Chiudi lo scrutinio"
  E click su "passo precedente"
  Allora la sezione "#gs-main h2" contiene "Verbale e chiusura"
  E il campo "scrutinio_numeroVerbale" contiene "3"
  E il campo "scrutinio_fine" contiene "11:30"
  E la sezione "#gs-main form .alert-success > div:nth-child(1)" contiene "Testo del secondo punto del verbale"


################################################################################
# Bisogna controllare visualizzazione con la classe articolata

Scenario: visualizzazione classe articolata
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe           |
    | @classe_3CAMB:id |
  Allora la sezione "#gs-main h2" contiene "Verbale e chiusura"
  E la sezione "#gs-main form > div:nth-child(2) > div:nth-child(1)" contiene "Punto secondo. Situazioni particolari da segnalare"
  E la sezione "#gs-main form .alert-success > div:nth-child(1)" contiene ""

Scenario: visualizzazione riquadro inserimento debiti per la classe articolata
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe           |
    | @classe_3CAMB:id |
  Quando click su "Conferma"
  Allora la sezione "#gs-main #gs-modal-remote h3.modal-title" contiene "Punto secondo all'ordine del giorno"
  E la sezione "#gs-main #gs-modal-remote h4.modal-title" contiene "Situazioni particolari da segnalare"
