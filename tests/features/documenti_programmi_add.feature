# language: it

Funzionalità: Inserimento dei programmi svolti dei docenti
  Per gestire l'inserimento dei programmi svolti
  Come utente docente
  Bisogna controllare prerequisiti per inserimento programma
  Bisogna caricare un documento da inserire come programma
  Bisogna controllare accesso a pagina
  Utilizzando "_documentiFixtures.yml"


Contesto: login docente senza cattedre
	Dato login utente con ruolo esatto "Docente"
  E modifica istanze di tipo "Cattedra":
    | docente | #attiva |
    | #logged | no      |


################################################################################
# Bisogna controllare prerequisiti per inserimento piano di lavoro

Scenario: visualizza pagina inserimento di programma non presente
  Data ricerca istanze di tipo "Materia":
    | id  | nome        |
    | $m1 | Informatica |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $cl1 | 1    | B       |
  E istanze di tipo "Cattedra":
    | id  | docente | attiva | materia | classe | tipo |
    | $c1 | #logged | si     | $m1     | $cl1    | N    |
  Quando pagina attiva "documenti_programmi"
  E click su "Aggiungi"
  Allora vedi pagina "documenti_programmi_add" con parametri:
    | classe  | materia |
    | $cl1:id | $m1:id  |
  E la sezione "#gs-main .panel-title" contiene "/Inserisci il programma svolto/"
  E la sezione "#gs-main .panel-body" contiene "/Classe:\s*1ª B\s*Materia:\s*Informatica/"

Scenario: visualizza errore per pagina inserimento di programma già inserito dal docente
  Date istanze di tipo "Cattedra":
    | id  | docente | attiva | tipo |
    | $c1 | #logged | si     | N    |
  E istanze di tipo "Documento":
    | id  | classe     | materia     | docente | tipo |
    | $d1 | $c1:classe | $c1:materia | #logged | P    |
  Quando vai alla pagina "documenti_programmi_add" con parametri:
    | classe        | materia        |
    | $c1:classe.id | $c1:materia.id |
  Allora vedi errore pagina "404"

Scenario: visualizza errore per pagina inserimento di programma già inserito da altri
  Data ricerca istanze di tipo "Materia":
    | id  | nome        |
    | $m1 | Informatica |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $cl1 | 1    | B       |
  E istanze di tipo "Cattedra":
    | id  | docente | attiva | classe | materia | tipo |
    | $c1 | #logged | si     | $cl1   | $m1     | N    |
    | $c2 | #other  | si     | $cl1   | $m1     | I    |
  E istanze di tipo "Documento":
    | id  | classe     | materia     | docente | tipo |
    | $d1 | $cl1       | $m1         | #other  | P    |
  Quando vai alla pagina "documenti_programmi_add" con parametri:
    | classe  | materia |
    | $cl1:id | $m1:id  |
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
  E creazione istanze di tipo "Cattedra":
    | id  | docente   | classe   | materia   | tipo   | attiva   |
    | $c1 | <docente> | <classe> | <materia> | <tipo> | <attiva> |
  Quando vai alla pagina "documenti_programmi_add" con parametri:
    | classe        | materia        |
    | $c1:classe.id | $c1:materia.id |
  Allora vedi errore pagina "404"
  Esempi:
    | docente | classe | materia | tipo | attiva |
    | #logged | $cl1   | $m1     | N    | no     |
    | #logged | $cl1   | $m2     | N    | si     |
    | #logged | $cl1   | $m3     | N    | si     |
    | #logged | $cl1   | $m1     | P    | si     |
    | #logged | $cl3   | $m1     | I    | si     |
    | #other  | $cl2   | $m1     | N    | si     |


################################################################################
# Bisogna caricare documento da inserire come piano di lavoro

Scenario: inserisce programma e lo visualizza su lista cattedre
  Data ricerca istanze di tipo "Materia":
    | id  | nome        |
    | $m1 | Informatica |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $cl1 | 1    | B       |
  E istanze di tipo "Cattedra":
    | id  | docente | attiva | materia | classe | tipo |
    | $c1 | #logged | si     | $m1     | $cl1   | N    |
  Quando pagina attiva "documenti_programmi_add" con parametri:
    | classe  | materia |
    | $cl1:id | $m1:id  |
  E alleghi file "documento-pdf.pdf" a dropzone
  E premi pulsante "Conferma"
  Allora vedi pagina "documenti_programmi"
  E vedi la tabella:
    | classe e materia                                 | documento                           | azione   |
    | $c1:classe,classe.corso,classe.sede,materia.nome | /Programma svolto.*1B.*Informatica/ | Cancella |
  E vedi file "archivio/classi/1B/PROGRAMMA-1B-INFORMATICA.pdf" di dimensione "61514"

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
  Quando pagina attiva "documenti_programmi_add" con parametri:
    | classe  | materia |
    | $cl1:id | $m1:id  |
  E alleghi file "documento-pdf.pdf" a dropzone
  E premi pulsante "Annulla"
  Allora vedi pagina "documenti_programmi"
  E vedi la tabella:
    | classe e materia                                 | documento              | azione   |
    | $c1:classe,classe.corso,classe.sede,materia.nome | Documento non inserito | Aggiungi |
  E non vedi file "archivio/classi/1B/PROGRAMMA-1B-INFORMATICA.pdf"

Scenario: impedisce inserimento programma con più di un allegato
  Data ricerca istanze di tipo "Materia":
    | id  | nome        |
    | $m1 | Informatica |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $cl1 | 1    | B       |
  E istanze di tipo "Cattedra":
    | id  | docente | attiva | materia | classe | tipo |
    | $c1 | #logged | si     | $m1     | $cl1   | N    |
  Quando pagina attiva "documenti_programmi_add" con parametri:
    | classe  | materia |
    | $cl1:id | $m1:id  |
  E alleghi file "documento-pdf.pdf" a dropzone
  E alleghi file "documento-docx.docx" a dropzone
  Allora la sezione "#gs-main .dropzone .dz-error" contiene "/documento-docx\.docx.*Non puoi caricare altri file/i"

Scenario: impedisce inserimento programma senza allegato
  Data ricerca istanze di tipo "Materia":
    | id  | nome        |
    | $m1 | Informatica |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $cl1 | 1    | B       |
  E istanze di tipo "Cattedra":
    | id  | docente | attiva | materia | classe | tipo |
    | $c1 | #logged | si     | $m1     | $cl1   | N    |
  Quando pagina attiva "documenti_programmi_add" con parametri:
    | classe  | materia |
    | $cl1:id | $m1:id  |
  Allora pulsante "Conferma" inattivo


################################################################################
# Bisogna controllare accesso a pagina

Scenario: accesso pagina inserimento programmi di lavoro senza utente
  Date istanze di tipo "Cattedra":
    | id  | docente | attiva | tipo |
    | $c1 | #logged | si     | N    |
  E logout utente
  Quando vai alla pagina "documenti_programmi_add" con parametri:
    | classe        | materia        |
    | $c1:classe.id | $c1:materia.id |
  Allora vedi pagina "login_form"

Schema dello scenario: accesso pagina inserimento programmi di lavoro con altri utenti
  Date istanze di tipo "Cattedra":
    | id  | docente | attiva | tipo |
    | $c1 | #logged | si     | N    |
  E logout utente
  E login utente con ruolo esatto <ruolo>
  Quando vai alla pagina "documenti_programmi_add" con parametri:
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
