# language: it

Funzionalità: Inserimento delle relazioni finali dei docenti
  Per gestire l'inserimento dele relazioni finali
  Come utente docente
  Bisogna controllare prerequisiti per inserimento relazione
  Bisogna caricare un documento da inserire come relazione
  Bisogna controllare accesso a pagina


Contesto: login docente senza cattedre
	Dato login utente con ruolo esatto "Docente"
  E modifica istanze di tipo "Cattedra":
    | docente | #attiva |
    | #logged | no      |


################################################################################
# Bisogna controllare prerequisiti per inserimento relazione

Scenario: visualizza pagina inserimento di relazione non presente
  Data ricerca istanze di tipo "Materia":
    | id  | nome        |
    | $m1 | Informatica |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $cl1 | 1    | B       |
  E istanze di tipo "Cattedra":
    | id  | docente | attiva | materia | classe | tipo |
    | $c1 | #logged | si     | $m1     | $cl1   | N    |
  Quando pagina attiva "documenti_relazioni"
  E click su "Aggiungi"
  Allora vedi pagina "documenti_relazioni_add" con parametri:
    | classe  | materia |
    | $cl1:id | $m1:id  |
  E la sezione "#gs-main .panel-title" contiene "/Inserisci la relazione finale/"
  E la sezione "#gs-main .panel-body" contiene "/Classe:\s*1ª B\s*Materia:\s*Informatica/"

Scenario: visualizza pagina inserimento di relazione non presente per sostegno
  Data ricerca istanze di tipo "Materia":
    | id  | tipo |
    | $m1 | S    |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $cl1 | 1    | B       |
  E istanze di tipo "Alunno":
    | id   | classe | bes | cognome | nome  |
    | $a1  | $cl1   | H   | Rossi   | Mario |
  E istanze di tipo "Cattedra":
    | id  | docente | attiva | materia | classe | Alunno | tipo |
    | $c1 | #logged | si     | $m1     | $cl1   | $a1    | N    |
  Quando pagina attiva "documenti_relazioni"
  E click su "Aggiungi"
  Allora vedi pagina "documenti_relazioni_add" con parametri:
    | classe  | materia | alunno |
    | $cl1:id | $m1:id  | $a1:id |
  E la sezione "#gs-main .panel-title" contiene "/Inserisci la relazione finale/"
  E la sezione "#gs-main .panel-body" contiene "/Classe:\s*1ª B\s*Materia:\s*Sostegno - Rossi Mario/"

Scenario: visualizza errore per pagina inserimento di relazione già inserita dal docente
  Data ricerca istanze di tipo "Materia":
    | id  | nome        |
    | $m1 | Informatica |
  E istanze di tipo "Cattedra":
    | id  | docente | attiva | tipo |
    | $c1 | #logged | si     | N    |
  E istanze di tipo "Documento":
    | id  | classe     | materia     | docente | tipo |
    | $d1 | $c1:classe | $c1:materia | #logged | R    |
  Quando vai alla pagina "documenti_relazioni_add" con parametri:
    | classe        | materia        |
    | $c1:classe.id | $c1:materia.id |
  Allora vedi errore pagina "404"

Scenario: visualizza errore per pagina inserimento di relazione già inserita da altri
  Data ricerca istanze di tipo "Materia":
    | id  | tipo |
    | $m1 | S    |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $cl1 | 1    | B       |
  E istanze di tipo "Alunno":
    | id   | classe | bes | cognome | nome     |
    | $a1  | $cl1   | H   | Rossi   | Mario    |
    | $a2  | $cl1   | H   | Verdi   | Giuseppe |
  E istanze di tipo "Cattedra":
    | id  | docente | attiva | classe | materia | alunno | tipo |
    | $c1 | #logged | si     | $cl1   | $m1     | $a1    |  N   |
    | $c2 | #other  | si     | $cl1   | $m1     | $a2    |  N   |
  E istanze di tipo "Documento":
    | id  | classe     | materia | alunno | docente | tipo |
    | $d1 | $cl1       | $m1     | $a2    | #other  | R    |
  Quando vai alla pagina "documenti_relazioni_add" con parametri:
    | classe  | materia | alunno |
    | $cl1:id | $m1:id  | $a2:id |
  Allora vedi errore pagina "404"

Schema dello scenario: visualizza errore per pagina inserimento di cattedra inesistente
  Data ricerca istanze di tipo "Materia":
    | id  | nome        | tipo |
    | $m1 | Informatica |      |
    | $m2 |             | S    |
    | $m3 |             | E    |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $cl1 | 1    | B       |
    | $cl2 | 2    | B       |
    | $cl3 | 5    | B       |
  E crea istanze di tipo "Cattedra":
    | id  | docente   | classe   | materia   | tipo   | attiva   |
    | $c1 | <docente> | <classe> | <materia> | <tipo> | <attiva> |
  Quando vai alla pagina "documenti_relazioni_add" con parametri:
    | classe        | materia        |
    | $c1:classe.id | $c1:materia.id |
  Allora vedi errore pagina "404"
  Esempi:
    | docente | classe | materia | tipo | attiva |
    | #logged | $cl1   | $m1     | N    | no     |
    | #logged | $cl1   | $m3     | N    | si     |
    | #logged | $cl1   | $m1     | P    | si     |
    | #logged | $cl3   | $m1     | I    | si     |
    | #other  | $cl2   | $m1     | N    | si     |


################################################################################
# Bisogna caricare documento da inserire come relazione

Scenario: inserisce relazione e la visualizza su lista cattedre
  Data ricerca istanze di tipo "Materia":
    | id  | nome        |
    | $m1 | Informatica |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $cl1 | 1    | B       |
  E istanze di tipo "Cattedra":
    | id  | docente | attiva | materia | classe | tipo |
    | $c1 | #logged | si     | $m1     | $cl1   | N    |
  Quando pagina attiva "documenti_relazioni_add" con parametri:
    | classe  | materia |
    | $cl1:id | $m1:id  |
  E alleghi file "documento-pdf.pdf" a dropzone
  E premi pulsante "Conferma"
  Allora vedi pagina "documenti_relazioni"
  E vedi la tabella:
    | classe e materia                                 | documento                           | azione   |
    | $c1:classe,classe.corso,classe.sede,materia.nome | /Relazione finale.*1B.*Informatica/ | Cancella |
  E vedi file "archivio/classi/1B/RELAZIONE-1B-INFORMATICA.pdf" di dimensione "61514"

Scenario: inserisce relazione di sostegno e la visualizza su lista cattedre
  Data ricerca istanze di tipo "Materia":
    | id  | tipo |
    | $m1 | S    |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $cl1 | 1    | B       |
  E istanze di tipo "Alunno":
    | id   | classe | bes | cognome | nome     |
    | $a1  | $cl1   | H   | Rossi   | Mario    |
  E istanze di tipo "Cattedra":
    | id  | docente | attiva | materia | classe | alunno | tipo |
    | $c1 | #logged | si     | $m1     | $cl1   | $a1    | N    |
  Quando pagina attiva "documenti_relazioni_add" con parametri:
    | classe  | materia | alunno |
    | $cl1:id | $m1:id  | $a1:id |
  E alleghi file "documento-pdf.pdf" a dropzone
  E premi pulsante "Conferma"
  Allora vedi pagina "documenti_relazioni"
  E vedi la tabella:
    | classe e materia                                 | documento                                      | azione   |
    | $c1:classe,classe.corso,classe.sede,materia.nome | /Relazione finale.*1B.*Sostegno - Rossi Mario/ | Cancella |
  E vedi file "archivio/classi/1B/RELAZIONE-1B-SOSTEGNO-ROSSI-MARIO.pdf" di dimensione "61514"

Scenario: annulla inserimento e torna a pagina lista cattedre senza modifiche
  Data ricerca istanze di tipo "Materia":
    | id  | nome        |
    | $m1 | Informatica |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $cl1 | 1    | B       |
  E istanze di tipo "Cattedra":
    | id  | docente | attiva | materia | classe | tipo |
    | $c1 | #logged | si     | $m1     | $cl1   | N    |
  Quando pagina attiva "documenti_relazioni_add" con parametri:
    | classe  | materia |
    | $cl1:id | $m1:id  |
  E alleghi file "documento-pdf.pdf" a dropzone
  E premi pulsante "Annulla"
  Allora vedi pagina "documenti_relazioni"
  E vedi la tabella:
    | classe e materia                                 | documento              | azione   |
    | $c1:classe,classe.corso,classe.sede,materia.nome | Documento non inserito | Aggiungi |
  E non vedi file "archivio/classi/1B/RELAZIONE-1B-INFORMATICA.pdf"

Scenario: impedisce inserimento relazione con più di un allegato
  Data ricerca istanze di tipo "Materia":
    | id  | nome        |
    | $m1 | Informatica |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $cl1 | 1    | B       |
  E istanze di tipo "Cattedra":
    | id  | docente | attiva | materia | classe | tipo |
    | $c1 | #logged | si     | $m1     | $cl1   | N    |
  Quando pagina attiva "documenti_relazioni_add" con parametri:
    | classe  | materia |
    | $cl1:id | $m1:id  |
  E alleghi file "documento-pdf.pdf" a dropzone
  E alleghi file "documento-docx.docx" a dropzone
  Allora la sezione "#gs-main .dropzone .dz-error" contiene "/documento-docx\.docx.*Non puoi caricare altri file/i"

Scenario: impedisce inserimento relazione senza allegato
  Data ricerca istanze di tipo "Materia":
    | id  | nome        |
    | $m1 | Informatica |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $cl1 | 1    | B       |
  E istanze di tipo "Cattedra":
    | id  | docente | attiva | materia | classe | tipo |
    | $c1 | #logged | si     | $m1     | $cl1   | N    |
  Quando pagina attiva "documenti_relazioni_add" con parametri:
    | classe  | materia |
    | $cl1:id | $m1:id  |
  Allora pulsante "Conferma" inattivo


################################################################################
# Bisogna controllare accesso a pagina

Scenario: accesso pagina inserimento relazioni senza utente
  Date istanze di tipo "Cattedra":
    | id  | docente | attiva | tipo |
    | $c1 | #logged | si     | N    |
  E logout utente
  Quando vai alla pagina "documenti_relazioni_add" con parametri:
    | classe        | materia        |
    | $c1:classe.id | $c1:materia.id |
  Allora vedi pagina "login_form"

Schema dello scenario: accesso pagina inserimento relazioni con altri utenti
  Date istanze di tipo "Cattedra":
    | id  | docente | attiva | tipo |
    | $c1 | #logged | si     | N    |
  E logout utente
  E login utente con ruolo esatto <ruolo>
  Quando vai alla pagina "documenti_relazioni_add" con parametri:
    | classe        | materia        |
    | $c1:classe.id | $c1:materia.id |
  Allora vedi errore pagina "403"
  Esempi:
    | ruolo          |
    | Amministratore |
    | Ata            |
    | Genitore       |
    | Alunno         |
    | Utente         |
