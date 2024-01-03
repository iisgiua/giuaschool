# language: it

Funzionalità: Visualizzazione documenti degli alunni da parte dello staff
  Per visualizzare la lista dei documenti degli alunni
  Come utente staff
  Bisogna leggere documenti degli alunni BES e mostrare lista
  Bisogna controllare filtro di visualizzazione
  Bisogna controllare memorizzazione dati di sessione
  Bisogna controllare la codifica dei documenti
  Bisogna controllare accesso a pagina
  Utilizzando "_documentiFixtures.yml"


Contesto: login staff di scuola
	Dato login utente con ruolo esatto "Staff"
  E modifica utente connesso:
    | sede |
    | null |


################################################################################
# Bisogna leggere documenti degli alunni BES e mostrare lista

Schema dello scenario: visualizza tutti i documenti per lo staff della scuola
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
  Quando pagina attiva "documenti_alunni"
  E selezioni opzione "Tutti" da lista "documento_tipo"
  E selezioni opzione "Tutte" da lista "documento_classe"
  E premi pulsante "Filtra"
  Allora vedi la tabella non ordinata:
    | classe               | alunno | documento       |
    | $cl1 $cl1:corso,sede | $a1    | Documento Excel |
    | $cl2 $cl2:corso,sede | $a2    | Documento Pdf   |
  Esempi:
    | tipo |
    | B    |
    | H    |
    | D    |

Scenario: visualizza lista vuota per i documenti BES
  Quando pagina attiva "documenti_alunni"
  E selezioni opzione "Tutti" da lista "documento_tipo"
  E selezioni opzione "Tutte" da lista "documento_classe"
  E premi pulsante "Filtra"
  Allora non vedi la tabella:
    | classe | alunno | documento |
  Ma la sezione "#gs-main .alert" contiene "/Non sono presenti documenti/i"

Scenario: visualizza più documenti per alunno BES
  Data ricerca istanze di tipo "Alunno":
    | id  | abilitato |
    | $a1 | si        |
  E istanze di tipo "Documento":
    | id  | classe     | alunno | tipo |
    | $d1 | $a1:classe | $a1    | B    |
    | $d2 | $a1:classe | $a1    | H    |
  Quando pagina attiva "documenti_alunni"
  E selezioni opzione "Tutti" da lista "documento_tipo"
  E selezioni opzione "Tutte" da lista "documento_classe"
  E premi pulsante "Filtra"
  Allora vedi la tabella non ordinata:
    | classe                              | alunno | documento       |
    | $a1:classe,classe.corso,classe.sede | $a1    | Documento Excel |
    |                                     |        | Documento Pdf   |


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
  Quando pagina attiva "documenti_alunni"
  E selezioni opzione "<tipo_id>" da lista "documento_tipo"
  E selezioni opzione "<classe_id>" da lista "documento_classe"
  E premi pulsante "Filtra"
  Allora vedi la tabella:
    | classe   | alunno   | documento   |
    | <classe> | <alunno> | <documento> |
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

Schema dello scenario: visualizza solo documenti di sede dello staff
  Data ricerca istanze di tipo "Sede":
    | id  | citta     |
    | $s1 | Bergamo   |
    | $s2 | Grossetto |
  E modifica utente connesso:
    | sede |
    | $s1  |
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
  Quando pagina attiva "documenti_alunni"
  E selezioni opzione "Tutti" da lista "documento_tipo"
  E selezioni opzione "Tutte" da lista "documento_classe"
  E premi pulsante "Filtra"
  Allora vedi la tabella:
    | classe               | alunno | documento       |
    | $cl1 $cl1:corso,sede | $a1    | Documento Excel |
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
  E pagina attiva "documenti_alunni"
  E opzione "<tipo_id>" selezionata da lista "documento_tipo"
  E opzione "<classe_id>" selezionata da lista "documento_classe"
  E premuto pulsante "Filtra"
  Quando vai alla pagina "login_home"
  E vai alla pagina "documenti_alunni"
  Allora vedi la tabella:
    | classe               | alunno | documento       |
    | $cl1 $cl1:corso,sede | $a1    | Documento Excel |
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
  Data modifica utente connesso:
    | responsabileBes | responsabileBesSede |
    | si              | null                |
  E ricerca istanze di tipo "Classe":
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
  Quando pagina attiva "documenti_alunni"
  E ricerca istanze di tipo "Documento":
    | id  | tipo      | alunno  |
    | $d1 | <tipodoc> | $a1     |
  Allora la sezione "#gs-main table tbody tr td button span.sr-only" contiene "$d1:cifrato"
  E vedi "/Michele Giua \(Castelsardo, 26 aprile 1889/" in PDF "archivio/classi/3A/riservato/<nome>-<alunno_file>.pdf" con password "$d1:cifrato"
  Esempi:
    | tipo     | nome     | tipodoc | alunno                 | alunno_file                              |
    | Diagnosi | DIAGNOSI | B       | $a1:cognome+ +$a1:nome | {{#slg($a1:cognome)}}-{{#slg($a1:nome)}} |
    | P.E.I.   | PEI      | H       | $a1:cognome+ +$a1:nome | {{#slg($a1:cognome)}}-{{#slg($a1:nome)}} |
    | P.D.P.   | PDP      | D       | $a1:cognome+ +$a1:nome | {{#slg($a1:cognome)}}-{{#slg($a1:nome)}} |


################################################################################
# Bisogna controllare accesso a pagine

Scenario: mostra errore all'accesso alla pagina senza utente
  Dato logout utente
  Quando vai alla pagina "documenti_alunni"
  Allora vedi pagina "login_form"

Schema dello scenario: mostra errore all'accesso alla pagina con altri utenti
  Dato logout utente
  E login utente con ruolo esatto "<ruolo>"
  Quando vai alla pagina "documenti_alunni"
  Allora vedi errore pagina "403"
  Esempi:
    | ruolo          |
    | Amministratore |
    | Docente        |
    | Ata            |
    | Genitore       |
    | Alunno         |
    | Utente         |
