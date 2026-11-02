# language: it

Funzionalità: verifiche di regressione per lo scrutinio
  Per evitare errori 500 su richieste non valide
  Come utente staff
  Bisogna controllare le materie di sostegno e i dati malformati
  Utilizzando "_scrutinioP4Fixtures.yml"


Contesto: login utente staff
  Dato login utente con ruolo esatto "Staff"


Scenario: materia di sostegno non accessibile nelle proposte
  Quando vai alla pagina "coordinatore_scrutinio_proposte" con parametri:
    | classe        | materia              | periodo | posizione |
    | @classe_1A:id | @materia_SOSTEGNO:id | P       | 0         |
  Allora vedi errore pagina 404

Scenario: materia di sostegno non accessibile nei voti
  Quando vai alla pagina "coordinatore_scrutinio_voti" con parametri:
    | classe        | materia              | periodo | alunno | posizione |
    | @classe_1A:id | @materia_SOSTEGNO:id | P       | 0      | 0         |
  Allora vedi errore pagina 404

Scenario: POST su materia di sostegno non genera errore 500
  Quando invio una richiesta POST alla pagina "coordinatore_scrutinio_voti" con parametri:
    | classe        | materia              | periodo | alunno | posizione |
    | @classe_1A:id | @materia_SOSTEGNO:id | P       | 0      | 0         |
  Allora vedi errore pagina 404

Scenario: valore alunno non valido non genera errore 500
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "Modifica le valutazioni della materia" in sezione "#gs-main form table thead th" che contiene "Italiano"
  Allora la sezione "#gs-main #gs-modal-remote .modal-title.gs-h4" contiene "Lingua e letteratura italiana"
  E inserisci "999999" nel campo nascosto "voti_lista_{{@alunno_1A_1:id}}_alunno"
  E click su "Conferma"
  Allora vedi la pagina "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
