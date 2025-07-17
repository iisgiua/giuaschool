# language: it

Funzionalità: Inserimento dei documenti del 15 maggio dei docenti
  Per gestire l'inserimento dei documenti del 15 maggio
  Come utente docente
  Bisogna controllare prerequisiti per inserimento documenti del 15 maggio
  Bisogna caricare un documento da inserire come documento del 15 maggio
  Bisogna controllare accesso a pagina
  Utilizzando "_documentiFixtures.yml"


Contesto: login docente senza cattedre di coordinatore
	Dato login utente con ruolo esatto "Docente"
  E modifica istanze di tipo "Cattedra":
    | docente | #attiva |
    | #logged | no      |
  E modifica istanze di tipo "Classe":
    | coordinatore | #coordinatore |
    | #logged      | null          |


################################################################################
# Bisogna controllare prerequisiti per inserimento documenti del 15 maggio

Scenario: visualizza pagina inserimento documento 15 maggio non presente
  Data modifica istanze di tipo "Classe":
    | anno | sezione | #coordinatore |
    | 5    | A       | #logged       |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $c1  | 5    | A       |
  Quando pagina attiva "documenti_maggio"
  E click su "Aggiungi"
  Allora vedi la pagina "documenti_maggio_add" con parametri:
    | classe |
    | $c1:id |
  E la sezione "#gs-main .panel-title" contiene "/Inserisci il documento del 15 maggio/"
  E la sezione "#gs-main .panel-body" contiene "/Classe:\s*5ª A/"

Scenario: visualizza errore per pagina inserimento documento 15 maggio già inserito
  Data modifica istanze di tipo "Classe":
    | anno | sezione | #coordinatore |
    | 5    | A       | #logged       |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $c1  | 5    | A       |
  E istanze di tipo "Documento":
    | id  | classe     | docente | tipo |
    | $d1 | $c1        | #logged | M    |
  Quando vai alla pagina "documenti_maggio_add" con parametri:
    | classe |
    | $c1:id |
  Allora vedi errore pagina "404"

Schema dello scenario: visualizza errore per pagina inserimento di cattedra di coordinatore inesistente
  Data modifica istanze di tipo "Classe":
    | anno | sezione | #coordinatore |
    | 5    | A       | null          |
    | 2    | B       | #logged       |
    | 5    | B       | #other        |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $cl1 | 5    | A       |
    | $cl2 | 2    | B       |
    | $cl3 | 5    | B       |
  Quando vai alla pagina "documenti_maggio_add" con parametri:
    | classe      |
    | <classe>:id |
  Allora vedi errore pagina "404"
  Esempi:
    | classe |
    | $cl1   |
    | $cl2   |
    | $cl3   |


################################################################################
# Bisogna caricare un documento da inserire come documento del 15 maggio

Scenario: inserisce documento 15 maggio e lo visualizza su lista cattedre
  Data modifica istanze di tipo "Classe":
    | anno | sezione | #coordinatore |
    | 5    | A       | #logged       |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $c1  | 5    | A       |
  Quando pagina attiva "documenti_maggio_add" con parametri:
    | classe |
    | $c1:id |
  E alleghi file "documento-pdf.pdf" a dropzone
  E premi pulsante "Conferma"
  Allora vedi la pagina "documenti_maggio"
  E vedi la tabella:
    | classe                      | documento                 | azione   |
    | $c1:anno,sezione,corso,sede | /Documento 15 maggio.*5A/ | Cancella |

Scenario: annulla inserimento e torna a pagina lista cattedre senza modifiche
  Data modifica istanze di tipo "Classe":
    | anno | sezione | #coordinatore |
    | 5    | A       | #logged       |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $c1  | 5    | A       |
  Quando pagina attiva "documenti_maggio_add" con parametri:
    | classe |
    | $c1:id |
  E alleghi file "documento-pdf.pdf" a dropzone
  E premi pulsante "Annulla"
  Allora vedi la pagina "documenti_maggio"
  E vedi la tabella:
    | classe                      | documento              | azione   |
    | $c1:anno,sezione,corso,sede | Documento non inserito | Aggiungi |

Scenario: impedisce inserimento documento 15 maggio con più di un allegato
  Data modifica istanze di tipo "Classe":
    | anno | sezione | #coordinatore |
    | 5    | A       | #logged       |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $c1  | 5    | A       |
  Quando pagina attiva "documenti_maggio_add" con parametri:
    | classe |
    | $c1:id |
  E alleghi file "documento-pdf.pdf" a dropzone
  E alleghi file "documento-docx.docx" a dropzone
  Allora la sezione "#gs-main .dropzone .dz-error" contiene "/documento-docx\.docx.*Non puoi caricare altri file/i"

Scenario: impedisce inserimento documento 15 maggio senza allegato
  Data modifica istanze di tipo "Classe":
    | anno | sezione | #coordinatore |
    | 5    | A       | #logged       |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $c1  | 5    | A       |
  Quando pagina attiva "documenti_maggio_add" con parametri:
    | classe |
    | $c1:id |
  Allora pulsante "Conferma" inattivo


################################################################################
# Bisogna controllare accesso a pagina

Scenario: accesso pagina inserimento documento 15 maggio senza utente
  Data modifica istanze di tipo "Classe":
    | anno | sezione | #coordinatore |
    | 5    | A       | #logged       |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $c1  | 5    | A       |
  E logout utente
  Quando pagina attiva "documenti_maggio_add" con parametri:
    | classe |
    | $c1:id |
  Allora vedi la pagina "login_form"

Schema dello scenario: accesso pagina inserimento documento 15 maggio con altri utenti
  Data modifica istanze di tipo "Classe":
    | anno | sezione | #coordinatore |
    | 5    | A       | #logged       |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $c1  | 5    | A       |
  E logout utente
  E login utente con ruolo esatto <ruolo>
  Quando vai alla pagina "documenti_maggio_add" con parametri:
    | classe |
    | $c1:id |
  Allora vedi errore pagina "403"
  Esempi:
    | ruolo          |
    | Amministratore |
    | Ata            |
    | Genitore       |
    | Alunno         |
    | Utente         |
