# language: it

Funzionalità: Inserimento proposte di voto per lo scrutinio del primo periodo
  Per inserire le proposte di voto dello scrutinio
  Come utente docente
  Bisogna inserire voti per la cattedra del docente
  Utilizzando "_scrutiniopropostePFixtures.yml"


################################################################################
# Bisogna inserire voti per la cattedra del docente

Schema dello scenario: Visualizza messaggio di errore per voti incompleti
  Dato login utente "<utente>"
  E pagina attiva "lezioni_scrutinio_proposte" con parametri:
    | cattedra   | classe   | periodo |
    | <cattedra> | <classe> | P       |
  Quando premi pulsante "Aggiungi" con indice "1"
  E premi pulsante "Conferma"
  Allora la sezione "#gs-main form #gs-errori" contiene "Manca il voto per uno o più alunni"
  Esempi:
    | utente                          | classe           | cattedra                 |
    | @docente_curricolare_1:username | @classe_1A:id    | @cattedra_1A_1:id        |
    | @docente_religione_1:username   | @classe_1A:id    | @cattedra_1A_6:id        |
    | @docente_itp_2:username         | @classe_3CAMB:id | @cattedra_3CAMB_1:id     |
    | @docente_curricolare_3:username | @classe_3CAMB:id | @cattedra_3C_3:id        |

Schema dello scenario: Visualizza messaggio di errore per dati incompleti
  Dato login utente "<utente>"
  E pagina attiva "lezioni_scrutinio_proposte" con parametri:
    | cattedra   | classe   | periodo |
    | <cattedra> | <classe> | P       |
  Quando premi pulsante "Aggiungi" con indice "1"
  E scorri cursore "1" di "-1" posizione
  E selezioni opzione "<recupero>" da lista "Recupero"
  E inserisci "<argomenti>" nel campo "Argomenti"
  E premi pulsante "Conferma"
  Allora la sezione "#gs-main form #gs-errori" contiene "<errore1>"
  E la sezione "#gs-main form #gs-errori" non contiene "<errore2>"
  Esempi:
    | utente                          | classe           | cattedra                 | recupero          | argomenti | errore1                                                | errore2                             |
    | @docente_curricolare_1:username | @classe_1A:id    | @cattedra_1A_1:id        |                   | Tutto     | /Manca la modalità del recupero .* Manca il voto/      | Mancano gli argomenti da recuperare |
    | @docente_curricolare_2:username | @classe_1A:id    | @cattedra_1A_civica_2:id | Corso di recupero |           | /Mancano gli argomenti da recuperare .* Manca il voto/ | Manca la modalità del recupero      |
    | @docente_itp_2:username         | @classe_3CAMB:id | @cattedra_3CAMB_1:id     |                   |           | /Manca la modalità del recupero .* Manca il voto/      |                                     |
    | @docente_curricolare_3:username | @classe_3CAMB:id | @cattedra_3C_3:id        | Corso di recupero |           | /Mancano gli argomenti da recuperare .* Manca il voto/ | Manca la modalità del recupero      |

Schema dello scenario: Visualizza messaggio di conferma per voti completi
  Dato login utente "<utente>"
  E pagina attiva "lezioni_scrutinio_proposte" con parametri:
    | cattedra   | classe   | periodo |
    | <cattedra> | <classe> | P       |
  Quando premi pulsante "Aggiungi" con indice "1"
  E premi pulsante "Aggiungi" con indice "2"
  E premi pulsante "Aggiungi" con indice "3"
  E premi pulsante "Aggiungi" con indice "4"
  E premi pulsante "Aggiungi" con indice "5"
  E premi pulsante "Aggiungi" con indice "6"
  E premi pulsante "Conferma"
  Allora la sezione "#gs-main .panel .alert-success" contiene "La modifica è stata memorizzata correttamente"
  Esempi:
    | utente                          | classe           | cattedra                 |
    | @docente_curricolare_1:username | @classe_1A:id    | @cattedra_1A_1:id        |
    | @docente_curricolare_2:username | @classe_1A:id    | @cattedra_1A_civica_2:id |
    | @docente_curricolare_3:username | @classe_3CAMB:id | @cattedra_3C_3:id        |

Scenario: Visualizza messaggio di conferma per voti completi - religione
  Dato login utente "@docente_religione_1:username"
  E pagina attiva "lezioni_scrutinio_proposte" con parametri:
    | cattedra          | classe        | periodo |
    | @cattedra_1A_6:id | @classe_1A:id | P       |
  Quando premi pulsante "Aggiungi" con indice "1"
  E premi pulsante "Aggiungi" con indice "2"
  E premi pulsante "Aggiungi" con indice "3"
  E premi pulsante "Aggiungi" con indice "4"
  E premi pulsante "Conferma"
  Allora la sezione "#gs-main .panel .alert-success" contiene "La modifica è stata memorizzata correttamente"

Scenario: Visualizza messaggio di conferma per voti completi - classe articolata
  Dato login utente "@docente_itp_2:username"
  E pagina attiva "lezioni_scrutinio_proposte" con parametri:
    | cattedra             | classe           | periodo |
    | @cattedra_3CAMB_1:id | @classe_3CAMB:id | P       |
  Quando premi pulsante "Aggiungi" con indice "1"
  E premi pulsante "Aggiungi" con indice "2"
  E premi pulsante "Aggiungi" con indice "3"
  E premi pulsante "Conferma"
  Allora la sezione "#gs-main .panel .alert-success" contiene "La modifica è stata memorizzata correttamente"

Schema dello scenario: Inserisce e memorizza i voti senza recupero
  Dato login utente "<utente>"
  E pagina attiva "lezioni_scrutinio_proposte" con parametri:
    | cattedra   | classe   | periodo |
    | <cattedra> | <classe> | P       |
  Quando premi pulsante "Aggiungi" con indice "1"
  E scorri cursore di "<posizioni>" posizioni
  E premi pulsante "Conferma"
  E vai alla pagina "login_home"
  E vai alla pagina "lezioni_scrutinio_proposte" con parametri:
    | cattedra   | classe   | periodo |
    | <cattedra> | <classe> | P       |
  Allora la sezione "#gs-main #gs-dropdown-menu" contiene "Primo Quadrimestre"
  E la sezione "#gs-main form #gs-form-collection li:nth-child(1)" contiene "Voto <voto>"
  E la sezione "#gs-main form #gs-form-collection li:nth-child(1) .form-group label" non contiene "Recupero"
  E la sezione "#gs-main form #gs-form-collection li:nth-child(1) .form-group label" non contiene "Argomenti"
  Esempi:
    | utente                          | classe           | cattedra                 | posizioni | voto        |
    | @docente_curricolare_1:username | @classe_1A:id    | @cattedra_1A_1:id        | 0         | 6           |
    | @docente_curricolare_1:username | @classe_1A:id    | @cattedra_1A_1:id        | 1         | 7           |
    | @docente_religione_1:username   | @classe_1A:id    | @cattedra_1A_6:id        | 0         | Sufficiente |
    | @docente_religione_1:username   | @classe_1A:id    | @cattedra_1A_6:id        | 1         | Discreto    |
    | @docente_religione_1:username   | @classe_1A:id    | @cattedra_1A_6:id        | -1        | Mediocre    |
    | @docente_curricolare_2:username | @classe_1A:id    | @cattedra_1A_civica_2:id | 0         | 6           |
    | @docente_curricolare_2:username | @classe_1A:id    | @cattedra_1A_civica_2:id | 1         | 7           |
    | @docente_curricolare_3:username | @classe_3CAMB:id | @cattedra_3C_3:id        | 3         | 9           |
    | @docente_itp_2:username         | @classe_3CAMB:id | @cattedra_3CAMB_1:id     | 2         | 8           |

Schema dello scenario: Inserisce e memorizza i voti con recupero
  Dato login utente "<utente>"
  E pagina attiva "lezioni_scrutinio_proposte" con parametri:
    | cattedra   | classe   | periodo |
    | <cattedra> | <classe> | P       |
  Quando premi pulsante "Aggiungi" con indice "1"
  E scorri cursore di "<posizioni>" posizioni
  E selezioni opzione "<recupero>" da lista "Recupero"
  E inserisci "<argomenti>" nel campo "Argomenti"
  E premi pulsante "Conferma"
  E vai alla pagina "login_home"
  E vai alla pagina "lezioni_scrutinio_proposte" con parametri:
    | cattedra   | classe        | periodo   |
    | <cattedra> | @classe_1A:id | P         |
  Allora la sezione "#gs-main #gs-dropdown-menu" contiene "Primo Quadrimestre"
  E la sezione "#gs-main form #gs-form-collection li:nth-child(1)" contiene "Voto <voto>"
  E il campo "Recupero" contiene "<recupero_val>"
  E il campo "Argomenti" contiene "<argomenti>"
  Esempi:
    | utente                          | classe           | cattedra                 | posizioni | voto | recupero            | recupero_val | argomenti      |
    | @docente_curricolare_1:username | @classe_1A:id    | @cattedra_1A_1:id        | -1        | 5    | Sportello didattico | S            | Argomento      |
    | @docente_curricolare_2:username | @classe_1A:id    | @cattedra_1A_civica_2:id | -2        | 4    | Pausa didattica     | P            | Da recuperare  |
    | @docente_curricolare_3:username | @classe_3CAMB:id | @cattedra_3C_3:id        | -3        | 3    | Corso di recupero   | C            | Da fare        |
    | @docente_itp_2:username         | @classe_3CAMB:id | @cattedra_3CAMB_1:id     | -4        | 2    | Sportello didattico | S            | Tutto          |
