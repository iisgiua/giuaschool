# language: it

Funzionalità: Visualizzazione cattedre per l'inserimento dei programmi finali
  Per visualizzare la lista delle cattedre e dei programmi inseriti
  Come utente docente
  Bisogna leggere cattedre e programmi del docente e mostrarli
  Bisogna controllare accesso a pagina


Contesto: login docente senza cattedre
	Dato login utente con ruolo esatto "Docente"
  E modifica istanze di tipo "Cattedra":
    | docente | #attiva |
    | #logged | no      |


################################################################################
# Bisogna leggere cattedre e programmi del docente e mostrarli

Scenario: visualizza solo lista cattedre utili per inserimento
  Data ricerca istanze di tipo "Materia":
    | id  | tipo | nome        |
    | $m1 | R    |             |
    | $m2 | E    |             |
    | $m3 | S    |             |
    | $m4 |      | Informatica |
  E ricerca istanze di tipo "Classe":
    | id   | anno |
    | $cl1 | 5    |
    | $cl2 | 4    |
    | $cl3 | 3    |
  E istanze di tipo "Cattedra":
    | id  | docente | attiva | materia | classe | tipo |
    | $c1 | #logged | si     | $m1     | $cl2   | A    |
    | $c2 | #logged | si     | $m4     | $cl3   | I    |
    | $c3 | #logged | si     | $m4     | $cl2   | N    |
    | $c4 | #logged | si     | $m2     | $cl3   | N    |
    | $c5 | #logged | si     | $m4     | $cl2   | P    |
    | $c6 | #logged | si     | $m3     | $cl3   | N    |
    | $c7 | #logged | no     | $m4     | $cl2   | N    |
    | $c8 | #logged | si     | $m4     | $cl1   | I    |
  Quando pagina attiva "documenti_programmi"
  Allora vedi la tabella non ordinata:
    | classe e materia                                 | documento              | azione   |
    | $c1:classe,classe.corso,classe.sede,materia.nome | Documento non inserito | Aggiungi |
    | $c2:classe,classe.corso,classe.sede,materia.nome | Documento non inserito | Aggiungi |
    | $c3:classe,classe.corso,classe.sede,materia.nome | Documento non inserito | Aggiungi |

Scenario: visualizza lista vuota cattedre docente
  Quando pagina attiva "documenti_programmi"
  Allora non vedi la tabella:
    | classe e materia | documento | azione |
  Ma la sezione "#gs-main .alert" contiene "/Non è previsto il caricamento dei programmi svolti/i"

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
    | $d1 | $c2:classe | $c2:materia | P    |
  Quando pagina attiva "documenti_programmi"
  Allora vedi la tabella non ordinata:
    | classe e materia                                 | documento              | azione   |
    | $c1:classe,classe.corso,classe.sede,materia.nome | Documento non inserito | Aggiungi |
    | $c2:classe,classe.corso,classe.sede,materia.nome | Documento Excel        | Cancella |


################################################################################
# Bisogna controllare accesso a pagine

Scenario: mostra errore all'accesso pagina lista cattedre senza utente
  Dato logout utente
  Quando vai alla pagina "documenti_programmi"
  Allora vedi pagina "login_form"

Schema dello scenario: mostra errore all'accesso pagina lista cattedre con altri utenti
  Dato logout utente
  E login utente con ruolo esatto <ruolo>
  Quando vai alla pagina "documenti_programmi"
  Allora vedi errore pagina "403"
  Esempi:
    | ruolo          |
    | Amministratore |
    | Ata            |
    | Genitore       |
    | Alunno         |
    | Utente         |
