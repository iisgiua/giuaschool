# language: it

Funzionalità: Inserimento dei documenti BES da parte del responsabile
  Per gestire l'inserimento dei documenti BES
  Come utente docente responsabile BES
  Bisogna controllare prerequisiti per inserimento documenti BES
  Bisogna caricare un documento da inserire come documento BES
  Bisogna controllare accesso a pagina


Contesto: login docente responsabile BES
	Dato login utente con ruolo esatto "Docente"
  E modifica utente connesso:
    | responsabileBes | responsabileBesSede |
    | si              | null                |


################################################################################
# Bisogna controllare prerequisiti per inserimento documenti BES
@debug
Scenario: visualizza pagina inserimento documento BES di nuovo alunno
  Quando pagina attiva "documenti_bes"
  E click su "Aggiungi"
  Allora vedi pagina "documenti_bes_add"

  #-- E la sezione "#gs-main .panel-title" contiene "/Inserisci il documento del 15 maggio/"
  #-- E la sezione "#gs-main .panel-body" contiene "/Classe:\s*5ª A/"

#-- Scenario: visualizza pagina inserimento nuovo documento BES di alunno con altro documento

#-- Scenario: visualizza errore per pagina inserimento documento 15 maggio già inserito
  #-- Data modifica istanze di tipo "Classe":
    #-- | anno | sezione | #coordinatore |
    #-- | 5    | A       | #logged       |
  #-- E ricerca istanze di tipo "Classe":
    #-- | id   | anno | sezione |
    #-- | $c1  | 5    | A       |
  #-- E istanze di tipo "Documento":
    #-- | id  | classe     | docente | tipo |
    #-- | $d1 | $c1        | #logged | M    |
  #-- Quando vai alla pagina "documenti_bes_add" con parametri:
    #-- | classe |
    #-- | $c1:id |
  #-- Allora vedi errore pagina 404

#-- Schema dello scenario: visualizza errore per pagina inserimento di cattedra di coordinatore inesistente
  #-- Data modifica istanze di tipo "Classe":
    #-- | anno | sezione | #coordinatore |
    #-- | 5    | A       | null          |
    #-- | 2    | B       | #logged       |
    #-- | 5    | B       | #other        |
  #-- E ricerca istanze di tipo "Classe":
    #-- | id   | anno | sezione |
    #-- | $cl1 | 5    | A       |
    #-- | $cl2 | 2    | B       |
    #-- | $cl3 | 5    | B       |
  #-- Quando vai alla pagina "documenti_bes_add" con parametri:
    #-- | classe      |
    #-- | <classe>:id |
  #-- Allora vedi errore pagina 404
  #-- Esempi:
    #-- | classe |
    #-- | $cl1   |
    #-- | $cl2   |
    #-- | $cl3   |


################################################################################
# Bisogna caricare un documento da inserire come documento BES

#-- Scenario: inserisce documento 15 maggio e lo visualizza su lista cattedre
  #-- Data modifica istanze di tipo "Classe":
    #-- | anno | sezione | #coordinatore |
    #-- | 5    | A       | #logged       |
  #-- E ricerca istanze di tipo "Classe":
    #-- | id   | anno | sezione |
    #-- | $c1  | 5    | A       |
  #-- Quando pagina attiva "documenti_bes_add" con parametri:
    #-- | classe |
    #-- | $c1:id |
  #-- E alleghi file "documento-pdf.pdf" a dropzone
  #-- E premi pulsante "Conferma"
  #-- Allora vedi pagina "documenti_bes"
  #-- E vedi nella tabella le colonne:
    #-- | classe | documento | azione |
  #-- E vedi "1" riga nella tabella
  #-- E vedi in una riga della tabella i dati:
    #-- | classe                      | documento                 | azione   |
    #-- | $c1:anno,sezione,corso,sede | /Documento 15 maggio.*5A/ | Cancella |
  #-- E vedi file "archivio/classi/5A/DOCUMENTO-15-MAGGIO-5A.pdf" di dimensione "61514"

#-- Scenario: annulla inserimento e torna a pagina lista cattedre senza modifiche
  #-- Data modifica istanze di tipo "Classe":
    #-- | anno | sezione | #coordinatore |
    #-- | 5    | A       | #logged       |
  #-- E ricerca istanze di tipo "Classe":
    #-- | id   | anno | sezione |
    #-- | $c1  | 5    | A       |
  #-- Quando pagina attiva "documenti_bes_add" con parametri:
    #-- | classe |
    #-- | $c1:id |   * @Then vedi :numero righe nella tabella
   * @Then vedi :numero riga nella tabella
   */
  public function vediNumeroRigheNellaTabellaIndicata($numero, $indice=1): void {
  public function vediRigheNellaTabella($numero, $indice=1): void {
    $tabelle = $this->session->getPage()->findAll('css', '#gs-main table');
    $this->assertNotEmpty($tabelle[$indice - 1]);
    $righe = $tabelle[$indice - 1]->findAll('css', 'tbody tr');
  #-- E alleghi file "documento-pdf.pdf" a dropzone
  #-- E premi pulsante "Annulla"
  #-- Allora vedi pagina "documenti_bes"
  #-- E vedi nella tabella le colonne:
    #-- | classe | documento | azione |
  #-- E vedi "1" riga nella tabella
  #-- E vedi in una riga della tabella i dati:
    #-- | classe                      | documento              | azione   |
    #-- | $c1:anno,sezione,corso,sede | Documento non inserito | Aggiungi |

#-- Scenario: impedisce inserimento documento 15 maggio con più di un allegato
  #-- Data modifica istanze di tipo "Classe":
    #-- | anno | sezione | #coordinatore |
    #-- | 5    | A       | #logged       |
  #-- E ricerca istanze di tipo "Classe":
    #-- | id   | anno | sezione |
    #-- | $c1  | 5    | A       |
  #-- Quando pagina attiva "documenti_bes_add" con parametri:
    #-- | classe |
    #-- | $c1:id |
  #-- E alleghi file "documento-pdf.pdf" a dropzone
  #-- E alleghi file "documento-docx.docx" a dropzone
  #-- Allora la sezione "#gs-main .dropzone .dz-error" contiene "/documento-docx\.docx.*Non puoi caricare altri file/i"

#-- Scenario: impedisce inserimento documento 15 maggio senza allegato
  #-- Data modifica istanze di tipo "Classe":
    #-- | anno | sezione | #coordinatore |
    #-- | 5    | A       | #logged       |
  #-- E ricerca istanze di tipo "Classe":
    #-- | id   | anno | sezione |
    #-- | $c1  | 5    | A       |
  #-- Quando pagina attiva "documenti_bes_add" con parametri:
    #-- | classe |
    #-- | $c1:id |
  #-- Allora pulsante "Conferma" inattivo


################################################################################
# Bisogna controllare accesso a pagina

Scenario: mostra errore all'accesso pagina inserimento documenti BES senza utente
  Dato logout utente
  Quando vai alla pagina "documenti_bes_add"
  Allora vedi pagina "login_form"

Schema dello scenario: mostra errore all'accesso pagina inserimento documenti BES con altri utenti
  Dato logout utente
  E login utente con ruolo esatto <ruolo>
  Quando vai alla pagina "documenti_bes_add"
  Allora vedi errore pagina "403"
  Esempi:
    | ruolo          |
    | Amministratore |
    | Ata            |
    | Genitore       |
    | Alunno         |
    | Utente         |

Scenario: mostra errore all'accesso pagina inserimento documenti BES con docente non autorizzato
  Data modifica utente connesso:
    | responsabileBes |
    | no              |
  Quando vai alla pagina "documenti_bes_add"
  Allora vedi errore pagina "404"
