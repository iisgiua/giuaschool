# language: it

Funzionalità: Visualizzazione bacheca dei documenti indirizzati all'utente
  Per visualizzare la lista dei documenti indirizzati all'utente
  Come utente
  Bisogna leggere documenti indirizzati all'utente e mostrare lista
  Bisogna controllare filtro di visualizzazione
  Bisogna controllare memorizzazione dati di sessione
  Bisogna controllare la codifica dei documenti
  Bisogna controllare accesso a pagina


################################################################################
# Bisogna controllare memorizzazione dati di sessione

Schema dello scenario: modifica filtri e controlla che siano memorizzati in sessione
  Dato login utente con ruolo esatto "<ruolo>"
  E modifica istanze di tipo "Cattedra":
    | attiva | #attiva |
    | si     | no      |
  E ricerca istanze di tipo "Classe":
    | id   |
    | $cl1 |
    | $cl2 |
  E creazione istanze di tipo "listaDestinatari":
    | id   |
    | $ld1 |
    | $ld2 |
  E creazione istanze di tipo "listaDestinatariUtente":
    | id    | listaDestinatari | utente  | letto                     |
    | $ldu1 | $ld1             | #logged | null                      |
    | $ldu2 | $ld2             | #logged | #dtm(11,11,2021,15,23,12) |
  E istanze di tipo "Documento":
    | id  | tipo    | listaDestinatari | classe | materia | alunno |
    | $d1 | <tipo>  | $ld1             | $cl1   | null    | null   |
    | $d2 | <tipo2> | $ld2             | $cl2   | null    | null   |
  E pagina attiva "documenti_bacheca"
  E selezioni opzione "<tipo_id>" da lista "documento_tipo"
  E inserisci "<titolo>" nel campo "documento_titolo"
  E premi pulsante "Filtra"
  Quando vai alla pagina "login_home"
  E vai alla pagina "documenti_bacheca"
  Allora vedi la tabella:
    | stato      | riferimento          | documento       | azione  |
    | DA LEGGERE | $cl1 $cl1:corso,sede | Documento Excel | Scarica |
  Esempi:
    | ruolo    | tipo | tipo2 | tipo_id    | titolo |
    | Docente  | L    | L     | Piani      | Excel  |
    | Docente  | L    | P     | Piani      |        |
    | Docente  | P    | P     | Programmi  | Excel  |
    | Docente  | P    | M     | Programmi  |        |
    | Docente  | M    | M     | 15 maggio  | Excel  |
    | Docente  | M    | B     | 15 maggio  |        |
    | Docente  | B    | B     | Diagnosi   | Excel  |
    | Docente  | B    | H     | Diagnosi   |        |
    | Docente  | H    | H     | P.E.I.     | Excel  |
    | Docente  | H    | D     | P.E.I.     |        |
    | Docente  | D    | D     | P.D.P.     | Excel  |
    | Docente  | D    | P     | P.D.P.     |        |
    | Docente  | G    | G     | Altro      | Excel  |
    | Docente  | G    | L     | Altro      |        |
    | Genitore | P    | P     | Programmi  | Excel  |
    | Genitore | P    | M     | Programmi  |        |
    | Genitore | M    | M     | 15 maggio  | Excel  |
    | Genitore | M    | B     | 15 maggio  |        |
    | Genitore | G    | G     | Altro      | Excel  |
    | Genitore | G    | L     | Altro      |        |
    | Alunno   | P    | P     | Programmi  | Excel  |
    | Alunno   | P    | M     | Programmi  |        |
    | Alunno   | M    | M     | 15 maggio  | Excel  |
    | Alunno   | M    | B     | 15 maggio  |        |
    | Alunno   | G    | G     | Altro      | Excel  |
    | Alunno   | G    | L     | Altro      |        |
    | Ata      | G    | G     | Da leggere | Excel  |


################################################################################
# Bisogna controllare la codifica dei documenti

Schema dello scenario: visualizza documento BES e controlla la sua codifica
  Dato login utente con ruolo esatto "Docente"
  E modifica utente connesso:
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
  E vedi pagina "documenti_bes"
  E ricerca istanze di tipo "Documento":
    | id  | tipo      | alunno  |
    | $d1 | <tipodoc> | $a1     |
  Quando pagina attiva "documenti_bacheca"
  Allora la sezione "#gs-main table tbody tr td button span.sr-only" contiene "$d1:cifrato"
  E vedi "/Michele Giua \(Castelsardo, 26 aprile 1889/" in file "archivio/classi/3A/riservato/<nome>-<alunno_file>.pdf" decodificato con "$d1:cifrato"
  Esempi:
    | tipo     | nome     | tipodoc | alunno                 | alunno_file                              |
    | Diagnosi | DIAGNOSI | B       | $a1:cognome+ +$a1:nome | {{#slg($a1:cognome)}}-{{#slg($a1:nome)}} |
    | P.E.I.   | PEI      | H       | $a1:cognome+ +$a1:nome | {{#slg($a1:cognome)}}-{{#slg($a1:nome)}} |
    | P.D.P.   | PDP      | D       | $a1:cognome+ +$a1:nome | {{#slg($a1:cognome)}}-{{#slg($a1:nome)}} |


################################################################################
# Bisogna controllare accesso a pagine

Scenario: mostra errore all'accesso alla pagina senza utente
  Quando vai alla pagina "documenti_alunni"
  Allora vedi pagina "login_form"
