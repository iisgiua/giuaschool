# language: it

Funzionalità: Visualizzazione bacheca dei documenti indirizzati all'utente
  Per visualizzare la lista dei documenti indirizzati all'utente
  Come utente
  Bisogna leggere documenti indirizzati all'utente e mostrare lista
  Bisogna controllare filtro di visualizzazione
  Bisogna controllare memorizzazione dati di sessione
  Bisogna controllare la codifica dei documenti
  Bisogna controllare accesso a pagina
  Utilizzando "_documentiFixtures.yml"


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
  E istanze di tipo "Documento":
    | id  | tipo   | classe | materia | alunno |
    | $d1 | <tipo> | $cl1   | null    | null   |
    | $d2 | <tipo> | $cl2   | null    | null   |
  E creazione istanze di tipo "ComunicazioneUtente":
    | id   | comunicazione | utente  | letto |
    | $cu1 | $d1           | #logged | null  |
    | $cu2 | $d1           | #other  | null  |
    | $cu3 | $d2           | #other  | null  |
  Quando pagina attiva "documenti_bacheca"
  E selezioni opzione "Tutti" da lista "documento_visualizza"
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
    | Docente  | C    |
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
  E istanze di tipo "Documento":
    | id  | tipo   | classe | materia | alunno |
    | $d1 | <tipo> | $cl1   | null    | null   |
    | $d2 | <tipo> | $cl2   | null    | null   |
  E creazione istanze di tipo "ComunicazioneUtente":
    | id   | comunicazione | utente  | letto |
    | $cu1 | $d1           | #other  | null  |
    | $cu2 | $d2           | #other  | null  |
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
    | Docente  | C    |
    | Docente  | H    |
    | Docente  | D    |
    | Docente  | G    |
    | Genitore | P    |
    | Genitore | M    |
    | Genitore | G    |
    | Alunno   | P    |
    | Alunno   | M    |
    | Alunno   | G    |

Schema dello scenario: visualizza più file per documento di utente connesso
  Dato login utente con ruolo esatto "<ruolo>"
  E modifica istanze di tipo "Cattedra":
    | attiva | #attiva |
    | si     | no      |
  E ricerca istanze di tipo "Classe":
    | id   |
    | $cl1 |
  E istanze di tipo "Documento":
    | id  | tipo   | classe | materia | alunno |
    | $d1 | <tipo> | $cl1   | null    | null   |
  E creazione istanze di tipo "ComunicazioneUtente":
    | id   | comunicazione | utente  | letto |
    | $cu1 | $d1           | #logged | null  |
  E creazione istanze di tipo "Allegato":
    | id  | titolo  | nome    | estensione | file    | dimensione | comunicazione |
    | $f1 | Prova 1 | PROVA-1 | pdf        | PROVA-1 | 123456     | $d1           |
  Quando pagina attiva "documenti_bacheca"
  E selezioni opzione "Tutti" da lista "documento_tipo"
  E inserisci "" nel campo "documento_titolo"
  E premi pulsante "Filtra"
  Allora vedi nella tabella i dati:
    | stato      | riferimento          | documento       | azione  |
    | DA LEGGERE | $cl1 $cl1:corso,sede | Documento Excel | Scarica |
    |            |                      | Prova 1         | Scarica |
  Esempi:
    | ruolo    | tipo |
    | Docente  | G    |
    | Genitore | G    |
    | Alunno   | G    |


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
  E istanze di tipo "Documento":
    | id  | tipo    | classe | materia | alunno |
    | $d1 | <tipo>  | $cl1   | null    | null   |
    | $d2 | <tipo2> | $cl2   | null    | null   |
  E creazione istanze di tipo "ComunicazioneUtente":
    | id   | comunicazione | utente  | letto |
    | $cu1 | $d1           | #logged | null  |
    | $cu2 | $d2           | #logged | null  |
  Quando pagina attiva "documenti_bacheca"
  E selezioni opzione "<tipo_id>" da lista "documento_tipo"
  E inserisci "" nel campo "documento_titolo"
  E premi pulsante "Filtra"
  Allora vedi la tabella:
    | stato      | riferimento          | documento       | azione  |
    | DA LEGGERE | $cl1 $cl1:corso,sede | Documento Excel | Scarica |
  Esempi:
    | ruolo    | tipo | tipo2 | tipo_id         |
    | Docente  | L    | P     | Piani           |
    | Docente  | P    | M     | Programmi       |
    | Docente  | M    | B     | 15 maggio       |
    | Docente  | B    | H     | Diagnosi        |
    | Docente  | C    | H     | certificazione  |
    | Docente  | H    | D     | P.E.I.          |
    | Docente  | D    | G     | P.D.P.          |
    | Docente  | G    | L     | Altro           |
    | Genitore | P    | G     | Programmi       |
    | Genitore | M    | P     | 15 maggio       |
    | Genitore | G    | M     | Altro           |
    | Alunno   | P    | G     | Programmi       |
    | Alunno   | M    | P     | 15 maggio       |
    | Alunno   | G    | M     | Altro           |

Schema dello scenario: visualizza filtro tipo documenti per stato da leggere
  Dato login utente con ruolo esatto "<ruolo>"
  E modifica istanze di tipo "Cattedra":
    | attiva | #attiva |
    | si     | no      |
  E ricerca istanze di tipo "Classe":
    | id   |
    | $cl1 |
    | $cl2 |
  E istanze di tipo "Documento":
    | id  | tipo   | classe | materia | alunno |
    | $d1 | <tipo> | $cl1   | null    | null   |
    | $d2 | <tipo> | $cl2   | null    | null   |
  E creazione istanze di tipo "ComunicazioneUtente":
    | id   | comunicazione | utente  | letto                     |
    | $cu1 | $d1           | #logged | null                      |
    | $cu2 | $d2           | #logged | #dtm(11,11,2021,15,23,12) |
  Quando pagina attiva "documenti_bacheca"
  E selezioni opzione "Da leggere" da lista "documento_visualizza"
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
    | Docente  | C    |
    | Docente  | H    |
    | Docente  | D    |
    | Docente  | G    |
    | Genitore | P    |
    | Genitore | M    |
    | Genitore | G    |
    | Alunno   | P    |
    | Alunno   | M    |
    | Alunno   | G    |

Schema dello scenario: visualizza filtro titolo documenti
  Dato login utente con ruolo esatto "<ruolo>"
  E modifica istanze di tipo "Cattedra":
    | attiva | #attiva |
    | si     | no      |
  E ricerca istanze di tipo "Classe":
    | id   |
    | $cl1 |
    | $cl2 |
  E istanze di tipo "Documento":
    | id  | tipo   | classe | materia | alunno |
    | $d1 | <tipo> | $cl1   | null    | null   |
    | $d2 | <tipo> | $cl2   | null    | null   |
  E creazione istanze di tipo "ComunicazioneUtente":
    | id   | comunicazione | utente  | letto                     |
    | $cu1 | $d1           | #logged | null                      |
    | $cu2 | $d2           | #logged | #dtm(11,11,2021,15,23,12) |
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
    | Docente  | C    |
    | Docente  | H    |
    | Docente  | D    |
    | Docente  | G    |
    | Genitore | P    |
    | Genitore | M    |
    | Genitore | G    |
    | Alunno   | P    |
    | Alunno   | M    |
    | Alunno   | G    |
