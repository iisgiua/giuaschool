# language: it

Funzionalità: Visualizzazione bacheca dei documenti indirizzati all'utente
  Per visualizzare la lista dei documenti indirizzati all'utente
  Come utente
  Bisogna leggere documenti indirizzati all'utente e mostrare lista
  Bisogna controllare filtro di visualizzazione
  Bisogna controllare memorizzazione dati di sessione
  Bisogna controllare accesso a pagina


################################################################################
# Bisogna leggere documenti indirizzati all'utente e mostrare lista

Schema dello scenario: visualizza i documenti per l'utente connesso
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
    | id    | listaDestinatari | utente  | letto |
    | $ldu1 | $ld1             | #logged | null  |
    | $ldu2 | $ld1             | #other  | null  |
    | $ldu3 | $ld2             | #other  | null  |
  E istanze di tipo "Documento":
    | id  | tipo   | listaDestinatari | classe | materia | alunno |
    | $d1 | <tipo> | $ld1             | $cl1   | null    | null   |
    | $d2 | <tipo> | $ld2             | $cl2   | null    | null   |
  Quando pagina attiva "documenti_bacheca"
  E selezioni opzione "Tutti" da lista "documento_tipo"
  E inserisci "" nel campo "documento_titolo"
  E premi pulsante "Filtra"
  Allora vedi la tabella:
    | stato      | riferimento          | documento       | azione  |
    | DA LEGGERE | $cl1 $cl1:corso,sede | Documento Excel | Scarica |
  Esempi:
    | ruolo    | tipo |
    | Docente  | L    |
    | Docente  | P    |
    | Docente  | M    |
    | Docente  | B    |
    | Docente  | H    |
    | Docente  | D    |
    | Docente  | G    |
    | Genitore | P    |
    | Genitore | M    |
    | Genitore | G    |
    | Alunno   | P    |
    | Alunno   | M    |
    | Alunno   | G    |
    | Ata      | G    |

Schema dello scenario: visualizza lista vuota in assenza di documenti per l'utente connesso
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
    | id    | listaDestinatari | utente  | letto |
    | $ldu1 | $ld1             | #other  | null  |
    | $ldu2 | $ld2             | #other  | null  |
  E istanze di tipo "Documento":
    | id  | tipo   | listaDestinatari | classe | materia | alunno |
    | $d1 | <tipo> | $ld1             | $cl1   | null    | null   |
    | $d2 | <tipo> | $ld2             | $cl2   | null    | null   |
  Quando pagina attiva "documenti_bacheca"
  E selezioni opzione "Tutti" da lista "documento_tipo"
  E inserisci "" nel campo "documento_titolo"
  E premi pulsante "Filtra"
  Allora non vedi la tabella:
    | stato | riferimento | documento | azione |
  Ma la sezione "#gs-main .alert" contiene "/Non sono presenti documenti/i"
  Esempi:
    | ruolo    | tipo |
    | Docente  | L    |
    | Docente  | P    |
    | Docente  | M    |
    | Docente  | B    |
    | Docente  | H    |
    | Docente  | D    |
    | Docente  | G    |
    | Genitore | P    |
    | Genitore | M    |
    | Genitore | G    |
    | Alunno   | P    |
    | Alunno   | M    |
    | Alunno   | G    |
    | Ata      | G    |

Schema dello scenario: visualizza più file per documento di utente connesso
  Dato login utente con ruolo esatto "<ruolo>"
  E modifica istanze di tipo "Cattedra":
    | attiva | #attiva |
    | si     | no      |
  E ricerca istanze di tipo "Classe":
    | id   |
    | $cl1 |
  E creazione istanze di tipo "listaDestinatari":
    | id   |
    | $ld1 |
  E creazione istanze di tipo "listaDestinatariUtente":
    | id    | listaDestinatari | utente  | letto |
    | $ldu1 | $ld1             | #logged | null  |
  E creazione istanze di tipo "File":
    | id  | titolo  | nome    | estensione | file    | dimensione |
    | $f1 | Prova 1 | PROVA-1 | pdf        | PROVA-1 | 123456     |
    | $f2 | Prova 2 | PROVA-2 | pdf        | PROVA-2 | 654321     |
  E istanze di tipo "Documento":
    | id  | tipo   | listaDestinatari | classe | materia | alunno | allegati      |
    | $d1 | <tipo> | $ld1             | $cl1   | null    | null   | #arc($f1,$f2) |
  Quando pagina attiva "documenti_bacheca"
  E selezioni opzione "Tutti" da lista "documento_tipo"
  E inserisci "" nel campo "documento_titolo"
  E premi pulsante "Filtra"
  Allora vedi la tabella non ordinata:
    | stato      | riferimento          | documento | azione  |
    | DA LEGGERE | $cl1 $cl1:corso,sede | Prova 1   | Scarica |
    | DA LEGGERE | $cl1 $cl1:corso,sede | Prova 2   | Scarica |
  Esempi:
    | ruolo    | tipo |
    | Docente  | G    |
    | Genitore | G    |
    | Alunno   | G    |
    | Ata      | G    |


################################################################################
# Bisogna controllare filtro di visualizzazione

Schema dello scenario: visualizza filtro tipo documenti
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
    | id    | listaDestinatari | utente  | letto |
    | $ldu1 | $ld1             | #logged | null  |
    | $ldu2 | $ld2             | #logged | null  |
  E istanze di tipo "Documento":
    | id  | tipo    | listaDestinatari | classe | materia | alunno |
    | $d1 | <tipo>  | $ld1             | $cl1   | null    | null   |
    | $d2 | <tipo2> | $ld2             | $cl2   | null    | null   |
  Quando pagina attiva "documenti_bacheca"
  E selezioni opzione "<tipo_id>" da lista "documento_tipo"
  E inserisci "" nel campo "documento_titolo"
  E premi pulsante "Filtra"
  Allora vedi la tabella:
    | stato      | riferimento          | documento       | azione  |
    | DA LEGGERE | $cl1 $cl1:corso,sede | Documento Excel | Scarica |
  Esempi:
    | ruolo    | tipo | tipo2 | tipo_id   |
    | Docente  | L    | P     | Piani     |
    | Docente  | P    | M     | Programmi |
    | Docente  | M    | B     | 15 maggio |
    | Docente  | B    | H     | Diagnosi  |
    | Docente  | H    | D     | P.E.I.    |
    | Docente  | D    | G     | P.D.P.    |
    | Docente  | G    | L     | Altro     |
    | Genitore | P    | G     | Programmi |
    | Genitore | M    | P     | 15 maggio |
    | Genitore | G    | M     | Altro     |
    | Alunno   | P    | G     | Programmi |
    | Alunno   | M    | P     | 15 maggio |
    | Alunno   | G    | M     | Altro     |

Schema dello scenario: visualizza filtro tipo documenti per stato da leggere
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
    | id  | tipo   | listaDestinatari | classe | materia | alunno |
    | $d1 | <tipo> | $ld1             | $cl1   | null    | null   |
    | $d2 | <tipo> | $ld2             | $cl2   | null    | null   |
  Quando pagina attiva "documenti_bacheca"
  E selezioni opzione "Da leggere" da lista "documento_tipo"
  E inserisci "" nel campo "documento_titolo"
  E premi pulsante "Filtra"
  Allora vedi la tabella:
    | stato      | riferimento          | documento       | azione  |
    | DA LEGGERE | $cl1 $cl1:corso,sede | Documento Excel | Scarica |
  Esempi:
    | ruolo    | tipo |
    | Docente  | L    |
    | Docente  | P    |
    | Docente  | M    |
    | Docente  | B    |
    | Docente  | H    |
    | Docente  | D    |
    | Docente  | G    |
    | Genitore | P    |
    | Genitore | M    |
    | Genitore | G    |
    | Alunno   | P    |
    | Alunno   | M    |
    | Alunno   | G    |
    | Ata      | G    |

Schema dello scenario: visualizza filtro titolo documenti
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
    | id  | tipo   | listaDestinatari | classe | materia | alunno |
    | $d1 | <tipo> | $ld1             | $cl1   | null    | null   |
    | $d2 | <tipo> | $ld2             | $cl2   | null    | null   |
  Quando pagina attiva "documenti_bacheca"
  E selezioni opzione "Tutti" da lista "documento_tipo"
  E inserisci "Excel" nel campo "documento_titolo"
  E premi pulsante "Filtra"
  Allora vedi la tabella:
    | stato      | riferimento          | documento       | azione  |
    | DA LEGGERE | $cl1 $cl1:corso,sede | Documento Excel | Scarica |
  Esempi:
    | ruolo    | tipo |
    | Docente  | L    |
    | Docente  | P    |
    | Docente  | M    |
    | Docente  | B    |
    | Docente  | H    |
    | Docente  | D    |
    | Docente  | G    |
    | Genitore | P    |
    | Genitore | M    |
    | Genitore | G    |
    | Alunno   | P    |
    | Alunno   | M    |
    | Alunno   | G    |
    | Ata      | G    |


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
# Bisogna controllare accesso a pagine

Scenario: mostra errore all'accesso alla pagina senza utente
  Quando vai alla pagina "documenti_alunni"
  Allora vedi pagina "login_form"
