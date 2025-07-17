# language: it

Funzionalità: Inserimento dei documenti BES da parte del responsabile
  Per gestire l'inserimento dei documenti BES
  Come utente docente responsabile BES
  Bisogna controllare prerequisiti per inserimento documenti BES
  Bisogna caricare un documento da inserire come documento BES
  Bisogna controllare la codifica dei documenti
  Bisogna controllare accesso a pagina
  Utilizzando "_documentiFixtures.yml"


Contesto: login docente responsabile BES
	Dato login utente con ruolo esatto "Docente"
  E modifica utente connesso:
    | responsabileBes | responsabileBesSede |
    | si              | null                |


################################################################################
# Bisogna controllare prerequisiti per inserimento documenti BES

Scenario: visualizza pagina inserimento documento BES di nuovo alunno
  Quando pagina attiva "documenti_bes"
  E la sezione "#gs-main .alert" contiene "/Non sono presenti documenti/i"
  E click su "Aggiungi"
  Allora vedi la pagina "documenti_bes_add"
  E la sezione "#gs-main .panel-title" contiene "/Inserisci il documento relativo all'alunno BES/"
  E la sezione "#gs-main .panel-body form .form-group:nth-of-type(1) label" contiene "Classe"
  E la sezione "#gs-main .panel-body form .form-group:nth-of-type(2) label" contiene "Alunno"
  E la sezione "#gs-main .panel-body form .form-group:nth-of-type(3) label" contiene "Tipo di documenti"
  E la sezione "#gs-main .panel-body form .form-group:nth-of-type(3) select" contiene "certificazione"
  E la sezione "#gs-main .panel-body form .form-group:nth-of-type(3) select" contiene "Diagnosi"
  E la sezione "#gs-main .panel-body form .form-group:nth-of-type(3) select" contiene "P.D.P."
  E la sezione "#gs-main .panel-body form .form-group:nth-of-type(3) select" contiene "P.E.I."
  E la sezione "#gs-main .panel-body form .form-group:nth-of-type(4) label" contiene "Documento"
  E la sezione "#gs-main .panel-body form button:nth-of-type(1)" contiene "Conferma"
  E la sezione "#gs-main .panel-body form button:nth-of-type(2)" contiene "Annulla"

Schema dello scenario: visualizza pagina inserimento nuovo documento BES di alunno con altro documento
  Data ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $cl1 | 2    | B       |
  E ricerca istanze di tipo "Alunno":
    | id  | classe | abilitato |
    | $a1 | $cl1   | si        |
  E istanze di tipo "Documento":
    | id  | classe | alunno | tipo   |
    | $d1 | $cl1   | $a1    | <tipo> |
  Quando pagina attiva "documenti_bes"
  E click su "Aggiungi" con indice "2"
  Allora vedi la pagina "documenti_bes_add" con parametri:
    | alunno |
    | $a1:id |
  E la sezione "#gs-main .panel-title" contiene "/Inserisci il documento relativo all'alunno BES/"
  E la sezione "#gs-main .panel-body" contiene "$a1:classe,cognome,nome"
  E la sezione "#gs-main .panel-body form .form-group:nth-of-type(1) label" contiene "Tipo di documenti"
  E la sezione "#gs-main .panel-body form .form-group:nth-of-type(1) select" contiene "<opzione1>"
  E la sezione "#gs-main .panel-body form .form-group:nth-of-type(1) select" contiene "<opzione2>"
  E la sezione "#gs-main .panel-body form .form-group:nth-of-type(1) select" contiene "<opzione3>"
  Ma la sezione "#gs-main .panel-body form .form-group:nth-of-type(1) select" non contiene "<opzione4>"
  Ma la sezione "#gs-main .panel-body form .form-group:nth-of-type(1) select" non contiene "<opzione5>"
  E la sezione "#gs-main .panel-body form .form-group:nth-of-type(2) label" contiene "Documento"
  E la sezione "#gs-main .panel-body form button:nth-of-type(1)" contiene "Conferma"
  E la sezione "#gs-main .panel-body form button:nth-of-type(2)" contiene "Annulla"
  Ma la sezione "#gs-main .panel-body form label" non contiene "Classe"
  Ma la sezione "#gs-main .panel-body form label" non contiene "Alunno"
  Esempi:
    | tipo | opzione1 | opzione2       | opzione3       | opzione4       | opzione5       |
    | B    | P.E.I.   | P.D.P.         | certificazione | Diagnosi       | Diagnosi       |
    | C    | P.E.I.   | P.D.P.         | Diagnosi       | certificazione | certificazione |
    | H    | Diagnosi | certificazione | certificazione | P.D.P.         | P.E.I.         |
    | D    | Diagnosi | certificazione | certificazione | P.D.P.         | P.E.I.         |

Schema dello scenario: impedisce visualizzazione pagina inserimento quando documenti BES già inseriti
  Data ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $cl1 | 2    | B       |
  E ricerca istanze di tipo "Alunno":
    | id  | classe | abilitato |
    | $a1 | $cl1   | si        |
  E istanze di tipo "Documento":
    | id  | classe | alunno | tipo   |
    | $d1 | $cl1   | $a1    | B      |
    | $d2 | $cl1   | $a1    | C      |
    | $d3 | $cl1   | $a1    | <tipo> |
  Quando pagina attiva "documenti_bes"
  Allora vedi la tabella non ordinata:
    | classe                              | alunno | documento       | azione            |
    | $a1:classe,classe.corso,classe.sede | $a1    | Documento Word  | Archivia Cancella |
    |                                     |        | Documento Excel | Archivia Cancella |
    |                                     |        | Documento Pdf   | Archivia Cancella |
  Esempi:
    | tipo |
    | H    |
    | D    |

Schema dello scenario: visualizza errore per pagina inserimento documenti BES già inseriti
  Data ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $cl1 | 2    | B       |
  E ricerca istanze di tipo "Alunno":
    | id  | classe | abilitato |
    | $a1 | $cl1   | si        |
  E istanze di tipo "Documento":
    | id  | classe | alunno | tipo   |
    | $d1 | $cl1   | $a1    | B      |
    | $d2 | $cl1   | $a1    | C      |
    | $d3 | $cl1   | $a1    | <tipo> |
  Quando vai alla pagina "documenti_bes_add" con parametri:
    | alunno |
    | $a1:id |
  Allora vedi errore pagina "404"
  Esempi:
    | tipo |
    | H    |
    | D    |


################################################################################
# Bisogna caricare un documento da inserire come documento BES

Schema dello scenario: inserisce documento BES e lo visualizza su pagina inserimenti
  Data ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $cl1 | 3    | A       |
  E ricerca istanze di tipo "Alunno":
    | id  | classe |
    | $a1 | $cl1   |
  Quando pagina attiva "documenti_bes_add"
  E selezioni opzione "3A" da lista "documento_classe"
  E selezioni opzione "<alunno>" da pulsanti radio "documento_alunnoIndividuale"
  E selezioni opzione "<tipo>" da lista "documento_tipo"
  E alleghi file "documento-pdf.pdf" a dropzone
  E premi pulsante "Conferma"
  Allora vedi la pagina "documenti_bes"
  E vedi la tabella:
    | classe | alunno           | documento | azione                     |
    | /3ª A/ | $a1:cognome,nome | <tipo>    | Aggiungi Archivia Cancella |
  Esempi:
    | tipo           | alunno                 |
    | certificazione | $a1:cognome+ +$a1:nome |
    | Diagnosi       | $a1:cognome+ +$a1:nome |
    | P.E.I.         | $a1:cognome+ +$a1:nome |
    | P.D.P.         | $a1:cognome+ +$a1:nome |

Schema dello scenario: annulla inserimento e torna a pagina inserimenti senza modifiche
  Data ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $cl1 | 3    | A       |
  E ricerca istanze di tipo "Alunno":
    | id  | classe |
    | $a1 | $cl1   |
  Quando pagina attiva "documenti_bes_add"
  E selezioni opzione "3A" da lista "documento_classe"
  E selezioni opzione "<alunno>" da pulsanti radio "documento_alunnoIndividuale"
  E selezioni opzione "<tipo>" da lista "documento_tipo"
  E alleghi file "documento-pdf.pdf" a dropzone
  E premi pulsante "Annulla"
  Allora vedi la pagina "documenti_bes"
  E non vedi la tabella:
    | classe | alunno | documento | azione |
  Ma la sezione "#gs-main .alert" contiene "/Non sono presenti documenti/i"
  Esempi:
    | tipo           | alunno                 |
    | certificazione | $a1:cognome+ +$a1:nome |
    | Diagnosi       | $a1:cognome+ +$a1:nome |
    | P.E.I.         | $a1:cognome+ +$a1:nome |
    | P.D.P.         | $a1:cognome+ +$a1:nome |

Scenario: errore inserimento documento BES senza selezione classe
  Quando pagina attiva "documenti_bes_add"
  E selezioni opzione "Diagnosi" da lista "documento_tipo"
  E alleghi file "documento-pdf.pdf" a dropzone
  E premi pulsante "Conferma"
  Allora vedi la pagina "documenti_bes_add" con errore "422"
  E la sezione "#gs-main form .alert" contiene "/Non hai indicato l'alunno/i"

Scenario: errore inserimento documento BES senza selezione alunno
  Quando pagina attiva "documenti_bes_add"
  E selezioni opzione "3A" da lista "documento_classe"
  E selezioni opzione "Diagnosi" da lista "documento_tipo"
  E alleghi file "documento-pdf.pdf" a dropzone
  E premi pulsante "Conferma"
  Allora vedi la pagina "documenti_bes_add" con errore "422"
  E la sezione "#gs-main form .alert" contiene "/Non hai indicato l'alunno/i"

Scenario: errore inserimento documento BES senza selezione tipo documento
  Data ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $cl1 | 3    | A       |
  E ricerca istanze di tipo "Alunno":
    | id  | classe |
    | $a1 | $cl1   |
  Quando pagina attiva "documenti_bes_add"
  E selezioni opzione "3A" da lista "documento_classe"
  E selezioni opzione "$a1:cognome+ +$a1:nome" da pulsanti radio "documento_alunnoIndividuale"
  E alleghi file "documento-pdf.pdf" a dropzone
  E premi pulsante "Conferma"
  Allora vedi la pagina "documenti_bes_add" con errore "422"
  E la sezione "#gs-main form .alert" contiene "/Non hai indicato il tipo di documento/i"

Scenario: impedisce inserimento documento BES con più di un allegato
  Data ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $cl1 | 3    | A       |
  E ricerca istanze di tipo "Alunno":
    | id  | classe |
    | $a1 | $cl1   |
  Quando pagina attiva "documenti_bes_add"
  E selezioni opzione "3A" da lista "documento_classe"
  E selezioni opzione "$a1:cognome+ +$a1:nome" da pulsanti radio "documento_alunnoIndividuale"
  E selezioni opzione "Diagnosi" da lista "documento_tipo"
  E alleghi file "documento-pdf.pdf" a dropzone
  E alleghi file "documento-docx.docx" a dropzone
  Allora la sezione "#gs-main .dropzone .dz-error" contiene "/documento-docx\.docx.*Non puoi caricare altri file/i"

Scenario: impedisce inserimento documento BES senza allegato
  Data ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $cl1 | 3    | A       |
  E ricerca istanze di tipo "Alunno":
    | id  | classe |
    | $a1 | $cl1   |
  Quando pagina attiva "documenti_bes_add"
  E selezioni opzione "3A" da lista "documento_classe"
  E selezioni opzione "$a1:cognome+ +$a1:nome" da pulsanti radio "documento_alunnoIndividuale"
  E selezioni opzione "Diagnosi" da lista "documento_tipo"
  Allora pulsante "Conferma" inattivo


################################################################################
# Bisogna controllare la codifica dei documenti

Schema dello scenario: inserisce documento BES e controlla la sua codifica
  Data pagina attiva "documenti_bes_add"
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $cl1 | 3    | A       |
  E ricerca istanze di tipo "Alunno":
    | id  | classe |
    | $a1 | $cl1   |
  Quando selezioni opzione "3A" da lista "documento_classe"
  E selezioni opzione "<alunno>" da pulsanti radio "documento_alunnoIndividuale"
  E selezioni opzione "<tipo>" da lista "documento_tipo"
  E alleghi file "documento-pdf.pdf" a dropzone
  E premi pulsante "Conferma"
  E vedi la pagina "documenti_bes"
  E ricerca istanze di tipo "Documento":
    | id  | tipo      | alunno  |
    | $d1 | <tipodoc> | $a1     |
  Allora la sezione "#gs-main table tbody tr td button span.sr-only" contiene "$d1:cifrato"
  E vedi "/Michele Giua \(Castelsardo, 26 aprile 1889/" in PDF "upload/documenti/riservato/{{$d1:allegati[0].file}}.pdf" con password "$d1:cifrato"
  Esempi:
    | tipo           | tipodoc | alunno                 |
    | certificazione | C       | $a1:cognome+ +$a1:nome |
    | Diagnosi       | B       | $a1:cognome+ +$a1:nome |
    | P.E.I.         | H       | $a1:cognome+ +$a1:nome |
    | P.D.P.         | D       | $a1:cognome+ +$a1:nome |


################################################################################
# Bisogna controllare accesso a pagina

Scenario: mostra errore all'accesso pagina inserimento documenti BES senza utente
  Dato logout utente
  Quando vai alla pagina "documenti_bes_add"
  Allora vedi la pagina "login_form"

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
