# language: it

Funzionalità: Visualizzazione pagina per l'inserimento dei documenti BES
  Per visualizzare la lista dei documenti BES inseriti e permettere l'inserimento di nuovi
  Come utente docente responsabile BES
  Bisogna leggere documenti BES visibili al responsabile e mostrarli
  Bisogna controllare filtro di visualizzazione
  Bisogna controllare memorizzazione dati di sessione
  Bisogna controllare la codifica dei documenti
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
    | classe               | alunno | documento       | azione            |
    | $cl1 $cl1:corso,sede | $a1    | Documento Excel | Aggiungi Cancella |
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
    | classe               | alunno | documento       | azione            |
    | $cl1 $cl1:corso,sede | $a1    | Documento Excel | Aggiungi Cancella |
    | $cl2 $cl2:corso,sede | $a2    | Documento Pdf   | Aggiungi Cancella |
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
    | classe                              | alunno | documento       | azione   |
    | $a1:classe,classe.corso,classe.sede | $a1    | Documento Excel | Cancella |
    | $a1:classe,classe.corso,classe.sede | $a1    | Documento PDf   | Cancella |


################################################################################
# Bisogna controllare filtro di visualizzazione

Schema dello scenario: visualizza filtri classi e tipo documenti
  Data ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $cl1 | 1    | B       |
    | $cl2 | 3    | B       |
  E istanze di tipo "Alunno":
    | id  | classe | abilitato |
    | $a1 | $cl1   | si        |
    | $a2 | $cl2   | si        |
  E istanze di tipo "Documento":
    | id  | classe | alunno | tipo    |
    | $d1 | $cl1   | $a1    | <tipo>  |
    | $d2 | $cl2   | $a2    | <tipo2> |
  Quando pagina attiva "documenti_bes"
  E selezioni opzione "<tipo_id>" da lista "documento_tipo"
  E selezioni opzione "<classe_id>" da lista "documento_classe"
  E premi pulsante "Filtra"
  Allora vedi la tabella:
    | classe   | alunno   | documento   | azione            |
    | <classe> | <alunno> | <documento> | Aggiungi Cancella |
  Esempi:
    | tipo | tipo2 | tipo_id  | classe_id  | classe               | alunno | documento       |
    | B    | B     | Tutti    | $cl1:id    | $cl1 $cl1:corso,sede | $a1    | Documento Excel |
    | B    | B     | Tutti    | $cl2:id    | $cl2 $cl2:corso,sede | $a2    | Documento PDF   |
    | B    | D     | Diagnosi | Tutte      | $cl1 $cl1:corso,sede | $a1    | Documento Excel |
    | H    | H     | Tutti    | $cl1:id    | $cl1 $cl1:corso,sede | $a1    | Documento Excel |
    | H    | H     | Tutti    | $cl2:id    | $cl2 $cl2:corso,sede | $a2    | Documento PDF   |
    | H    | B     | P.E.I.   | Tutte      | $cl1 $cl1:corso,sede | $a1    | Documento Excel |
    | D    | D     | Tutti    | $cl1:id    | $cl1 $cl1:corso,sede | $a1    | Documento Excel |
    | D    | D     | Tutti    | $cl2:id    | $cl2 $cl2:corso,sede | $a2    | Documento PDF   |
    | D    | B     | P.D.P.   | Tutte      | $cl1 $cl1:corso,sede | $a1    | Documento Excel |

Schema dello scenario: visualizza solo documenti di sede del responsabile
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
  E selezioni opzione "Tutti" da lista "documento_tipo"
  E selezioni opzione "Tutte" da lista "documento_classe"
  E premi pulsante "Filtra"
  Allora vedi la tabella:
    | classe               | alunno | documento       | azione            |
    | $cl1 $cl1:corso,sede | $a1    | Documento Excel | Aggiungi Cancella |
  Esempi:
    | tipo |
    | B    |
    | H    |
    | D    |


################################################################################
# Bisogna controllare memorizzazione dati di sessione

Schema dello scenario: modifica filtri e controlla che siano memorizzati in sessione
  Data ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $cl1 | 1    | B       |
    | $cl2 | 3    | B       |
  E ricerca istanze di tipo "Alunno":
    | id  | classe | abilitato |
    | $a1 | $cl1   | si        |
    | $a2 | $cl2   | si        |
  E istanze di tipo "Documento":
    | id  | classe | alunno | tipo    |
    | $d1 | $cl1   | $a1    | <tipo>  |
    | $d2 | $cl2   | $a2    | <tipo2> |
  E pagina attiva "documenti_bes"
  E opzione "<tipo_id>" selezionata da lista "documento_tipo"
  E opzione "<classe_id>" selezionata da lista "documento_classe"
  E premuto pulsante "Filtra"
  Quando vai alla pagina "login_home"
  E vai alla pagina "documenti_bes"
  Allora vedi la tabella:
    | classe               | alunno | documento       | azione            |
    | $cl1 $cl1:corso,sede | $a1    | Documento Excel | Aggiungi Cancella |
  Esempi:
    | tipo | tipo2 | tipo_id  | classe_id |
    | B    | B     | Diagnosi | $cl1:id   |
    | B    | H     | Diagnosi | Tutte     |
    | H    | H     | P.E.I.   | $cl1:id   |
    | H    | B     | P.E.I.   | Tutte     |
    | D    | D     | P.D.P.   | $cl1:id   |
    | D    | B     | P.D.P.   | Tutte     |


################################################################################
# Bisogna controllare la codifica dei documenti

Schema dello scenario: visualizza documento BES e controlla la sua codifica
  Data ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $cl1 | 3    | A       |
  E ricerca istanze di tipo "Alunno":
    | id  | classe |
    | $a1 | $cl1   |
  E istanze di tipo "Cattedra":
    | id  | docente | classe | attiva | tipo |
    | $c1 | #logged | $cl1   | si     | N    |
  E pagina attiva "documenti_bes_add"
  E selezioni opzione "3A" da lista "documento_classe"
  E selezioni opzione "<alunno>" da pulsanti radio "documento_alunnoIndividuale"
  E selezioni opzione "<tipo>" da lista "documento_tipo"
  E alleghi file "documento-pdf.pdf" a dropzone
  E premi pulsante "Conferma"
  Quando pagina attiva "documenti_bes"
  E ricerca istanze di tipo "Documento":
    | id  | tipo      | alunno  |
    | $d1 | <tipodoc> | $a1     |
  Allora la sezione "#gs-main table tbody tr td button span.sr-only" contiene "$d1:cifrato"
  E vedi "/Michele Giua \(Castelsardo, 26 aprile 1889/" in file "archivio/classi/3A/riservato/<nome>-<alunno_file>.pdf" decodificato con "$d1:cifrato"
  Esempi:
    | tipo     | nome     | tipodoc | alunno                 | alunno_file                              |
    | Diagnosi | DIAGNOSI | B       | $a1:cognome+ +$a1:nome | {{#slg($a1:cognome)}}-{{#slg($a1:nome)}} |
    | P.E.I.   | PEI      | H       | $a1:cognome+ +$a1:nome | {{#slg($a1:cognome)}}-{{#slg($a1:nome)}} |
    | P.D.P.   | PDP      | D       | $a1:cognome+ +$a1:nome | {{#slg($a1:cognome)}}-{{#slg($a1:nome)}} |


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
