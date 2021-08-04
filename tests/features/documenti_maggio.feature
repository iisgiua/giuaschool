# language: it

Funzionalità: Visualizzazione cattedre di coordinatore per l'inserimento dei documenti del 15 maggio
  Per visualizzare la lista delle cattedre di coordinatore e dei documenti del 15 maggio inseriti
  Come utente docente
  Bisogna leggere cattedre di coordinatore e documenti del 15 maggio del docente e mostrarli
  Bisogna controllare accesso a pagina


Contesto: login docente senza cattedre di coordinatore
	Dato login utente con ruolo esatto "Docente"
  E modifica istanze di tipo "Cattedra":
    | docente | #attiva |
    | #logged | no      |
  E modifica istanze di tipo "Classe":
    | coordinatore | #coordinatore |
    | #logged      | null          |


################################################################################
# Bisogna leggere cattedre di coordinatore e documenti del 15 maggio del docente e mostrarli

Scenario: visualizza solo lista cattedre di coordinatore per inserimento
  Data modifica istanze di tipo "Classe":
    | anno | sezione | #coordinatore |
    | 5    | A       | #logged       |
    | 4    | A       | #logged       |
  Quando pagina attiva "documenti_maggio"
  Allora vedi nella tabella le colonne:
    | classe | documento | azione |
  E vedi "1" righe nella tabella
  E vedi in una riga della tabella i dati:
    | classe  | documento              | azione   |
    | 5ª A    | Documento non inserito | Aggiungi |

Scenario: visualizza lista vuota cattedre di coordinatore
  Quando pagina attiva "documenti_maggio"
  Allora la sezione "#gs-main" non contiene "/<table/i"
  Ma la sezione "#gs-main .alert" contiene "/Non è previsto il caricamento del documento del 15 maggio/i"

Scenario: visualizza lista cattedre di coordinatore con documenti
  Data modifica istanze di tipo "Classe":
    | anno | sezione | #coordinatore |
    | 5    | A       | #logged       |
    | 5    | B       | #logged       |
  E ricerca istanze di tipo "Classe":
    | id  | anno | sezione |
    | $c1 | 5    | A       |
    | $c2 | 5    | B       |
  E istanze di tipo "Documento":
    | id  | classe | tipo |
    | $d1 | $c1    | M    |
  Quando pagina attiva "documenti_maggio"
  Allora vedi nella tabella le colonne:
    | classe | documento | azione |
  E vedi "2" righe nella tabella
  E vedi in più righe della tabella i dati:
    | classe                      | documento              | azione   |
    | $c1:anno,sezione,corso,sede | Documento Excel        | Cancella |
    | $c2:anno,sezione,corso,sede | Documento non inserito | Aggiungi |


################################################################################
# Bisogna controllare accesso a pagine

Scenario: mostra errore all'accesso pagina lista cattedre senza utente
  Dato logout utente
  Quando vai alla pagina "documenti_maggio"
  Allora vedi pagina "login_form"

Schema dello scenario: mostra errore all'accesso pagina lista cattedre con altri utenti
  Dato logout utente
  E login utente con ruolo esatto <ruolo>
  Quando vai alla pagina "documenti_maggio"
  Allora vedi errore pagina "403"
  Esempi:
    | ruolo          |
    | Amministratore |
    | Ata            |
    | Genitore       |
    | Alunno         |
    | Utente         |
