# language: it

Funzionalità: Visualizzazione cattedre per l'inserimento delle relazioni finali
  Per visualizzare la lista delle cattedre e delle relazioni inserite
  Come utente docente
  Bisogna leggere cattedre e relazioni del docente e mostrarli
  Bisogna controllare accesso a pagina


Contesto: login docente senza cattedre
	Dato login utente con ruolo esatto "Docente"
  E modifica istanze di tipo "Cattedra":
    | docente | #attiva |
    | #logged | no      |


################################################################################
# Bisogna leggere cattedre e relazioni di lavoro del docente e mostrarli

Scenario: visualizza solo lista cattedre utili per inserimento
  Data ricerca istanze di tipo "Materia":
    | id  | tipo | nome        |
    | $m1 | R    |             |
    | $m2 | E    |             |
    | $m3 | S    |             |
    | $m4 |      | Informatica |
  E istanze di tipo "Cattedra":
    | id  | docente | attiva | materia | tipo |
    | $c1 | #logged | si     | $m1     | A    |
    | $c2 | #logged | si     | $m4     | I    |
    | $c3 | #logged | si     | $m4     | N    |
    | $c4 | #logged | si     | $m2     | N    |
    | $c5 | #logged | si     | $m4     | P    |
    | $c6 | #logged | si     | $m3     | N    |
    | $c7 | #logged | no     | $m4     | N    |
  Quando pagina attiva "documenti_relazioni"
  Allora vedi nella tabella le colonne:
    | classe e materia | documento | azione |
  E vedi "4" righe nella tabella
  E vedi in più righe della tabella i dati:
    | classe e materia                                 | documento              | azione   |
    | $c1:classe,classe.corso,classe.sede,materia.nome | Documento non inserito | Aggiungi |
    | $c2:classe,classe.corso,classe.sede,materia.nome | Documento non inserito | Aggiungi |
    | $c3:classe,classe.corso,classe.sede,materia.nome | Documento non inserito | Aggiungi |
    | $c6:classe,classe.corso,classe.sede,materia.nome | Documento non inserito | Aggiungi |

Scenario: visualizza lista vuota cattedre docente
  Quando pagina attiva "documenti_relazioni"
  Allora la sezione "#gs-main" non contiene "/<table/i"
  Ma la sezione "#gs-main .alert" contiene "/Non è previsto il caricamento delle relazioni finali/i"

Scenario: visualizza lista cattedre docente con documenti
  Data ricerca istanze di tipo "Materia":
    | id  | tipo | nome        |
    | $m1 | N    |             |
    | $m2 |      | Informatica |
  E istanze di tipo "Cattedra":
    | id  | docente | attiva | materia | tipo |
    | $c1 | #logged | si     | $m1     | N    |
    | $c2 | #logged | si     | $m2     | N    |
  E istanze di tipo "Documento":
    | id  | classe     | materia     | tipo |
    | $d1 | $c2:classe | $c2:materia | R    |
  Quando pagina attiva "documenti_relazioni"
  Allora vedi nella tabella le colonne:
    | classe e materia | documento | azione |
  E vedi "2" righe nella tabella
  E vedi in più righe della tabella i dati:
    | classe e materia                                 | documento              | azione   |
    | $c1:classe,classe.corso,classe.sede,materia.nome | Documento non inserito | Aggiungi |
    | $c2:classe,classe.corso,classe.sede,materia.nome | Documento Excel        | Cancella |


################################################################################
# Bisogna controllare accesso a pagine

Scenario: mostra errore all'accesso pagina lista cattedre senza utente
  Dato logout utente
  Quando vai alla pagina "documenti_relazioni"
  Allora vedi pagina "login_form"

Schema dello scenario: mostra errore all'accesso pagina lista cattedre con altri utenti
  Dato logout utente
  E login utente con ruolo esatto <ruolo>
  Quando vai alla pagina "documenti_relazioni"
  Allora vedi errore pagina "403"
  Esempi:
    | ruolo          |
    | Amministratore |
    | Ata            |
    | Genitore       |
    | Alunno         |
    | Utente         |
