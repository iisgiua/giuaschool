# language: it

Funzionalità: Visualizzazione pagina per l'inserimento dei documenti BES
  Per visualizzare la lista dei documenti BES inseriti e permettere l'inserimento di nuovi
  Come utente docente responsabile BES
  Bisogna leggere documenti BES visibili al responsabile e mostrarli
  Bisogna controllare accesso a pagina


Contesto: login docente responsabile BES
	Dato login utente con ruolo esatto "Docente"
  E modifica utente connesso:
    | responsabileBes | responsabileBesSede |
    | si              | null                |


################################################################################
# Bisogna leggere documenti BES visibili al responsabile e mostrarli

Schema dello scenario: visualizza solo lista documenti della sede del responsabile
  Data ricerca istanze di tipo "Sede":
    | id  | citta     |
    | $s1 | Bergamo   |
    | $s2 | Grossetto |
  E modifica utente connesso:
    | responsabileBesSede |
    | $s1                 |
  E ricerca istanze di tipo "Classe":
    | id   | sede |
    | $cl1 | $s1  |
    | $cl2 | $s2  |
  E ricerca istanze di tipo "Alunno":
    | id  | classe | abilitato |
    | $a1 | $cl1   | si        |
    | $a2 | $cl2   | si        |
  E istanze di tipo "Documento":
    | id  | classe | alunno | tipo   |
    | $d1 | $cl1   | $a1    | <tipo> |
    | $d2 | $cl2   | $a2    | <tipo> |
  Quando pagina attiva "documenti_bes"
  Allora vedi la tabella:
    | alunno                   | documento       | azione            |
    | $a1 $cl1 $cl1:corso,sede | Documento Excel | Aggiungi Cancella |
  Esempi:
    | tipo |
    | B    |
    | H    |
    | D    |

Schema dello scenario: visualizza tutti i documenti per il responsabile della scuola
  Data ricerca istanze di tipo "Sede":
    | id  | citta     |
    | $s1 | Bergamo   |
    | $s2 | Grossetto |
  E ricerca istanze di tipo "Classe":
    | id   | sede |
    | $cl1 | $s1  |
    | $cl2 | $s2  |
  E ricerca istanze di tipo "Alunno":
    | id  | classe | abilitato |
    | $a1 | $cl1   | si        |
    | $a2 | $cl2   | si        |
  E istanze di tipo "Documento":
    | id  | classe | alunno | tipo   |
    | $d1 | $cl1   | $a1    | <tipo> |
    | $d2 | $cl2   | $a2    | <tipo> |
  Quando pagina attiva "documenti_bes"
  Allora vedi la tabella non ordinata:
    | alunno                   | documento       | azione            |
    | $a1 $cl1 $cl1:corso,sede | Documento Excel | Aggiungi Cancella |
    | $a2 $cl2 $cl2:corso,sede | Documento Pdf   | Aggiungi Cancella |
  Esempi:
    | tipo |
    | B    |
    | H    |
    | D    |

Scenario: visualizza lista vuota per i documenti BES
  Quando pagina attiva "documenti_bes"
  Allora non vedi la tabella:
    | alunno | documento | azione |
  Ma la sezione "#gs-main .alert" contiene "/Non sono presenti documenti/i"
  E pulsante "Aggiungi" attivo

Scenario: visualizza più documenti per alunno BES
  Data ricerca istanze di tipo "Alunno":
    | id  | abilitato |
    | $a1 | si        |
  E istanze di tipo "Documento":
    | id  | classe     | alunno | tipo |
    | $d1 | $a1:classe | $a1    | B    |
    | $d2 | $a1:classe | $a1    | H    |
  Quando pagina attiva "documenti_bes"
  Allora vedi la tabella non ordinata:
    | alunno                                  | documento       | azione   |
    | $a1 $a1:classe,classe.corso,classe.sede | Documento Excel | Cancella |
    | $a1 $a1:classe,classe.corso,classe.sede | Documento Pdf   | Cancella |


################################################################################
# Bisogna controllare accesso a pagina

Scenario: mostra errore all'accesso pagina inserimento documenti BES senza utente
  Dato logout utente
  Quando vai alla pagina "documenti_bes"
  Allora vedi pagina "login_form"

Schema dello scenario: mostra errore all'accesso pagina inserimento documenti BES con altri utenti
  Dato logout utente
  E login utente con ruolo esatto <ruolo>
  Quando vai alla pagina "documenti_bes"
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
  Quando vai alla pagina "documenti_bes"
  Allora vedi errore pagina "404"
