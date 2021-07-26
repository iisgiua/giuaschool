# language: it

Funzionalità: Gestione dell'inserimento dei Piani Di Lavoro
  Per gestire l'inserimento dei Piani di Lavoro
  Come utente docente
  Bisogna visualizzare lista cattedre docente con Piani di Lavoro inseriti o no
  Bisogna inserire un nuovo Piano di Lavoro su cattedra di docente
  Bisogna modificare un Piano di Lavoro esistente su cattedra di docente
  Bisogna cancellare un Piano di Lavoro esistente su cattedra di docente


  Contesto:
  	Dato login utente con ruolo "Docente"


################################################################################
# Bisogna visualizzare lista cattedre docente con Piani di Lavoro inseriti o no

  Scenario: visualizza lista cattedre docente per inserimento
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
    Quando pagina attiva "documenti_piani"
    Allora vedi nella tabella le colonne:
      | classe e materia | documento | azione |
    E vedi almeno "3" righe nella tabella
    E vedi in più righe della tabella i dati:
      | classe e materia                                 | documento              | azione   |
      | $c1:classe,classe.corso,classe.sede,materia.nome | Documento non inserito | Aggiungi |
      | $c2:classe,classe.corso,classe.sede,materia.nome | Documento non inserito | Aggiungi |
      | $c3:classe,classe.corso,classe.sede,materia.nome | Documento non inserito | Aggiungi |

  Scenario: visualizza lista vuota cattedre docente
    Data modifica istanze di tipo "Cattedra":
      | docente | #attiva |
      | #logged | no      |
    Quando pagina attiva "documenti_piani"
    Allora la sezione "#gs-main" non contiene "/<table/i"
    Ma la sezione "#gs-main .alert" contiene "/Non è previsto il caricamento dei piani di lavoro/i"

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
      | $d1 | $c2:classe | $c2:materia | L    |
    Quando pagina attiva "documenti_piani"
    Allora vedi nella tabella le colonne:
      | classe e materia | documento | azione |
    E vedi almeno "2" righe nella tabella
    E vedi in più righe della tabella i dati:
      | classe e materia                                 | documento              | azione   |
      | $c1:classe,classe.corso,classe.sede,materia.nome | Documento non inserito | Aggiungi |
      | $c2:classe,classe.corso,classe.sede,materia.nome | Documento Excel | Modifica Cancella |

  Scenario: visualizzazione lista cattedre docente senza utente
    Dato logout utente
    Quando vai alla pagina "documenti_piani"
    Allora vedi pagina "login_form"

  Schema dello scenario: visualizzazione lista cattedre docente con altri utenti
    Dato logout utente
    E login utente con ruolo <ruolo>
    Quando vai alla pagina "documenti_piani"
    Allora vedi errore pagina "403"
    Esempi:
      | ruolo          |
      | Amministratore |
      | Ata            |
      | Genitore       |
      | Alunno         |
      | Utente         |


################################################################################
# Bisogna inserire un nuovo Piano di Lavoro su cattedra di docente
