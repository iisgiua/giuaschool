# language: it

Funzionalità: Inserimento proposte di voto per lo scrutinio
  Per inserire le proposte di voto dello scrutinio
  Come utente docente
  Bisogna inserire voti per cattedra di docente
  #-- Bisogna controllare filtro del periodo
  #-- Bisogna controllare visualizzazione di altri docenti con stessa cattedra
  Bisogna controllare accesso a pagina


Contesto: login docente senza cattedre
	Dato login utente con ruolo esatto "Docente"
  E modifica istanze di tipo "Cattedra":
    | docente | #attiva |
    | #logged | no      |
  E modifica istanze di tipo "DefinizioneScrutinio":
    | #periodo |
    | -        |


################################################################################
# Bisogna inserire voti per cattedra di docente

Schema dello scenario: Niente dati di recupero per le classi quinte per la cattedra del docente
  Data creazione istanze di tipo "DefinizioneScrutinio":
    | id   | periodo   | data   | dataProposte |
    | $ds1 | <periodo> | #dtm() | #dtm()       |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione | gruppo |
    | $cl1 | 5    | A       |        |
  E ricerca istanze di tipo "Materia":
    | id  | tipo           |
    | $m1 | <tipo_materia> |
  E istanze di tipo "Cattedra":
    | id  | docente | classe | materia | attiva | tipo |
    | $c1 | #logged | $cl1   | $m1     | si     | N    |
  E modifica istanze di tipo "Alunno":
    | classe | #abilitato | #classe |
    | $cl1   | no         | null    |
  E istanze di tipo "Alunno":
    | id   | classe | nome     | cognome | abilitato |
    | $a1  | $cl1   | Mario    | Rossi   | si        |
    | $a2  | $cl1   | Giuseppe | Verdi   | si        |
  E pagina attiva "lezioni_scrutinio_proposte" con parametri:
    | cattedra | classe  | periodo   |
    | $c1:id   | $cl1:id | <periodo> |
  Quando premi pulsante "Aggiungi" con indice "2"
  E scorri cursore di "<posizioni>" posizioni
  E premi pulsante "Conferma"
  E vai alla pagina "login_home"
  E vai alla pagina "lezioni_scrutinio_proposte" con parametri:
    | cattedra | classe  | periodo   |
    | $c1:id   | $cl1:id | <periodo> |
  Allora la sezione "#gs-main form #gs-form-collection li:contains('Verdi Giuseppe')" contiene "Voto <voto>"
  E la sezione "#gs-main form #gs-form-collection li:contains('Verdi Giuseppe') .form-group label" non contiene "Recupero"
  E la sezione "#gs-main form #gs-form-collection li:contains('Verdi Giuseppe') .form-group label" non contiene "Argomenti"
  Esempi:
    | periodo | tipo_materia | posizioni | voto |
    | F       | N            | -1        | 5    |
    | F       | N            | -2        | 4    |
    | F       | N            | -3        | 3    |
    | F       | N            | -4        | 2    |
    | F       | N            | -5        | 1    |
    | F       | N            | -6        | NC   |
    | F       | E            | -1        | 5    |
    | F       | E            | -2        | 4    |
    | F       | E            | -3        | 3    |
    | F       | E            | -4        | NC   |

Schema dello scenario: Visualizza messaggio di errore per voti incompleti
  Data creazione istanze di tipo "DefinizioneScrutinio":
    | id   | periodo   | data   | dataProposte |
    | $ds1 | <periodo> | #dtm() | #dtm()       |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione | gruppo |
    | $cl1 | 4    | A       |        |
  E ricerca istanze di tipo "Materia":
    | id  | tipo           |
    | $m1 | <tipo_materia> |
  E istanze di tipo "Cattedra":
    | id  | docente | classe | materia | attiva | tipo |
    | $c1 | #logged | $cl1   | $m1     | si     | N    |
  E modifica istanze di tipo "Alunno":
    | classe | #abilitato | #classe |
    | $cl1   | no         | null    |
  E istanze di tipo "Alunno":
    | id   | classe | nome     | cognome | abilitato | religione |
    | $a1  | $cl1   | Mario    | Rossi   | si        | S         |
    | $a2  | $cl1   | Giuseppe | Verdi   | si        | S         |
  E pagina attiva "lezioni_scrutinio_proposte" con parametri:
    | cattedra | classe  | periodo   |
    | $c1:id   | $cl1:id | <periodo> |
  Quando premi pulsante "Aggiungi" con indice "2"
  E scorri cursore di "1" posizione
  E premi pulsante "Conferma"
  Allora la sezione "#gs-main form #gs-errori" contiene "Manca il voto per uno o più alunni"
  Esempi:
    | periodo | tipo_materia |
    | P       | N            |
    | P       | R            |
    | P       | E            |
    | F       | N            |
    | F       | R            |
    | F       | E            |

Schema dello scenario: Visualizza messaggio di errore per dati incompleti
  Data creazione istanze di tipo "DefinizioneScrutinio":
    | id   | periodo   | data   | dataProposte |
    | $ds1 | <periodo> | #dtm() | #dtm()       |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione | gruppo |
    | $cl1 | 4    | A       |        |
  E ricerca istanze di tipo "Materia":
    | id  | tipo           |
    | $m1 | <tipo_materia> |
  E istanze di tipo "Cattedra":
    | id  | docente | classe | materia | attiva | tipo |
    | $c1 | #logged | $cl1   | $m1     | si     | N    |
  E modifica istanze di tipo "Alunno":
    | classe | #abilitato | #classe |
    | $cl1   | no         | null    |
  E istanze di tipo "Alunno":
    | id   | classe | nome     | cognome | abilitato |
    | $a1  | $cl1   | Mario    | Rossi   | si        |
    | $a2  | $cl1   | Giuseppe | Verdi   | si        |
  E pagina attiva "lezioni_scrutinio_proposte" con parametri:
    | cattedra | classe  | periodo   |
    | $c1:id   | $cl1:id | <periodo> |
  Quando premi pulsante "Aggiungi" con indice "1"
  E premi pulsante "Aggiungi" con indice "2"
  E scorri cursore "1" di "1" posizione
  E scorri cursore "2" di "-1" posizione
  E selezioni opzione "<recupero>" da lista "Recupero"
  E inserisci "<argomenti>" nel campo "Argomenti"
  E premi pulsante "Conferma"
  Allora la sezione "#gs-main form #gs-errori" non contiene "Manca il voto per uno o più alunni"
  E la sezione "#gs-main form #gs-errori" contiene "<errore>"
  Esempi:
    | periodo | tipo_materia | recupero          | argomenti | errore                                                                          |
    | P       | N            |                   |           | Manca la modalità del recupero per uno o più alunni con voto insufficiente      |
    | P       | N            |                   | Tutto     | Manca la modalità del recupero per uno o più alunni con voto insufficiente      |
    | P       | N            | Corso di recupero |           | Mancano gli argomenti da recuperare per uno o più alunni con voto insufficiente |
    | P       | E            |                   |           | Manca la modalità del recupero per uno o più alunni con voto insufficiente      |
    | P       | E            |                   | Tutto     | Manca la modalità del recupero per uno o più alunni con voto insufficiente      |
    | P       | E            | Corso di recupero |           | Mancano gli argomenti da recuperare per uno o più alunni con voto insufficiente |
    | F       | N            |                   |           | Manca la modalità del recupero per uno o più alunni con voto insufficiente      |
    | F       | N            |                   | Tutto     | Manca la modalità del recupero per uno o più alunni con voto insufficiente      |
    | F       | N            | Corso di recupero |           | Mancano gli argomenti da recuperare per uno o più alunni con voto insufficiente |
    | F       | E            |                   |           | Manca la modalità del recupero per uno o più alunni con voto insufficiente      |
    | F       | E            |                   | Tutto     | Manca la modalità del recupero per uno o più alunni con voto insufficiente      |
    | F       | E            | Corso di recupero |           | Mancano gli argomenti da recuperare per uno o più alunni con voto insufficiente |

Schema dello scenario: Visualizza messaggio di conferma per voti completi
  Data creazione istanze di tipo "DefinizioneScrutinio":
    | id   | periodo   | data   | dataProposte |
    | $ds1 | <periodo> | #dtm() | #dtm()       |
  E ricerca istanze di tipo "Classe":
    | id   | anno   | sezione | gruppo |
    | $cl1 | <anno> | A       |        |
  E ricerca istanze di tipo "Materia":
    | id  | tipo           |
    | $m1 | <tipo_materia> |
  E istanze di tipo "Cattedra":
    | id  | docente | classe | materia | attiva | tipo |
    | $c1 | #logged | $cl1   | $m1     | si     | N    |
  E modifica istanze di tipo "Alunno":
    | classe | #abilitato | #classe |
    | $cl1   | no         | null    |
  E istanze di tipo "Alunno":
    | id   | classe | nome     | cognome | abilitato | religione |
    | $a1  | $cl1   | Mario    | Rossi   | si        | S         |
    | $a2  | $cl1   | Giuseppe | Verdi   | si        | S         |
  E pagina attiva "lezioni_scrutinio_proposte" con parametri:
    | cattedra | classe  | periodo   |
    | $c1:id   | $cl1:id | <periodo> |
  Quando premi pulsante "Aggiungi" con indice "2"
  E premi pulsante "Aggiungi" con indice "1"
  E scorri cursore "1" di "<posizione>" posizione
  E premi pulsante "Conferma"
  Allora la sezione "#gs-main .panel .alert-success" contiene "La modifica è stata memorizzata correttamente"
  Esempi:
    | periodo | anno | tipo_materia | posizione |
    | P       | 4    | N            | 1         |
    | P       | 4    | R            | -1        |
    | P       | 4    | E            | 1         |
    | F       | 4    | N            | 1         |
    | F       | 4    | R            | -1        |
    | F       | 4    | E            | 1         |
    | F       | 5    | N            | -1        |
    | F       | 5    | E            | -1        |

Schema dello scenario: Visualizza messaggio di conferma per dati completi
  Data creazione istanze di tipo "DefinizioneScrutinio":
    | id   | periodo   | data   | dataProposte |
    | $ds1 | <periodo> | #dtm() | #dtm()       |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione | gruppo |
    | $cl1 | 4    | A       |        |
  E ricerca istanze di tipo "Materia":
    | id  | tipo           |
    | $m1 | <tipo_materia> |
  E istanze di tipo "Cattedra":
    | id  | docente | classe | materia | attiva | tipo |
    | $c1 | #logged | $cl1   | $m1     | si     | N    |
  E modifica istanze di tipo "Alunno":
    | classe | #abilitato | #classe |
    | $cl1   | no         | null    |
  E istanze di tipo "Alunno":
    | id   | classe | nome     | cognome | abilitato |
    | $a1  | $cl1   | Mario    | Rossi   | si        |
    | $a2  | $cl1   | Giuseppe | Verdi   | si        |
  E pagina attiva "lezioni_scrutinio_proposte" con parametri:
    | cattedra | classe  | periodo   |
    | $c1:id   | $cl1:id | <periodo> |
  Quando premi pulsante "Aggiungi" con indice "1"
  E premi pulsante "Aggiungi" con indice "2"
  E scorri cursore "1" di "1" posizione
  E scorri cursore "2" di "-1" posizione
  E selezioni opzione "Corso di recupero" da lista "Recupero"
  E inserisci "Tutto" nel campo "Argomenti"
  E premi pulsante "Conferma"
  Allora la sezione "#gs-main .panel .alert-success" contiene "La modifica è stata memorizzata correttamente"
  Esempi:
    | periodo | tipo_materia |
    | P       | N            |
    | P       | E            |
    | F       | N            |
    | F       | E            |


################################################################################
# Bisogna controllare accesso a pagine

Scenario: mostra errore all'accesso alla pagina senza utente
  Dato logout utente
  Quando vai alla pagina "lezioni_scrutinio_proposte"
  Allora vedi pagina "login_form"

Schema dello scenario: mostra errore all'accesso alla pagina con altri utenti
  Dato logout utente
  E login utente con ruolo esatto "<ruolo>"
  Quando vai alla pagina "lezioni_scrutinio_proposte"
  Allora vedi errore pagina "403"
  Esempi:
    | ruolo          |
    | Amministratore |
    | Ata            |
    | Genitore       |
    | Alunno         |
    | Utente         |
