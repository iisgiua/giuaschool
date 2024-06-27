# language: it

Funzionalità: Inserimento proposte di voto per lo scrutinio finale
  Per inserire le proposte di voto dello scrutinio
  Come utente docente
  Bisogna inserire voti per la cattedra del docente
  Utilizzando "_scrutinioproposteGFixtures.yml"


################################################################################
# Bisogna inserire voti per la cattedra del docente

Schema dello scenario: Visualizza messaggio di errore per voti incompleti
  Dato login utente "<utente>"
  E pagina attiva "lezioni_scrutinio_proposte" con parametri:
    | cattedra   | classe   | periodo |
    | <cattedra> | <classe> | G       |
  Quando premi pulsante "Conferma"
  Allora la sezione "#gs-main form #gs-errori" contiene "Manca il voto per uno o più alunni"
  Esempi:
    | utente                          | classe           | cattedra          |
    | @docente_curricolare_1:username | @classe_1A:id    | @cattedra_1A_1:id |
    | @docente_curricolare_2:username | @classe_3CAMB:id | @cattedra_3C_2:id |

Schema dello scenario: Visualizza messaggio proposte non previste
  Dato login utente "<utente>"
  Quando vai alla pagina "lezioni_scrutinio_proposte" con parametri:
    | cattedra   | classe   | periodo |
    | <cattedra> | <classe> | G       |
  Allora la sezione "#gs-main .panel .panel-title" contiene "Non è previsto l'inserimento di proposte"
  Esempi:
    | utente                          | classe           | cattedra          |
    | @docente_curricolare_4:username | @classe_1A:id    | @cattedra_1A_4:id |
    | @docente_religione_1:username   | @classe_1A:id    | @cattedra_1A_6:id |
    | @docente_curricolare_4:username | @classe_3CAMB:id | @cattedra_3C_4:id |
    | @docente_curricolare_1:username | @classe_5A:id    | @cattedra_5A_1:id |

Scenario: Visualizza messaggio di conferma per voti completi
  Dato login utente "@docente_curricolare_1:username"
  E pagina attiva "lezioni_scrutinio_proposte" con parametri:
    | cattedra          | classe        | periodo |
    | @cattedra_1A_1:id | @classe_1A:id | G       |
  Quando premi pulsante "Aggiungi"
  E premi pulsante "Conferma"
  Allora la sezione "#gs-main .panel .alert-success" contiene "La modifica è stata memorizzata correttamente"

Scenario: Visualizza messaggio di conferma per voti completi - classe articolata
  Dato login utente "@docente_curricolare_2:username"
  E pagina attiva "lezioni_scrutinio_proposte" con parametri:
    | cattedra          | classe           | periodo |
    | @cattedra_3C_2:id | @classe_3CAMB:id | G       |
  Quando premi pulsante "Aggiungi" con indice "1"
  Quando premi pulsante "Aggiungi" con indice "2"
  E premi pulsante "Conferma"
  Allora la sezione "#gs-main .panel .alert-success" contiene "La modifica è stata memorizzata correttamente"

Schema dello scenario: Inserisce e memorizza i voti senza recupero
  Dato login utente "<utente>"
  E pagina attiva "lezioni_scrutinio_proposte" con parametri:
    | cattedra   | classe   | periodo |
    | <cattedra> | <classe> | G       |
  Quando premi pulsante "Aggiungi" con indice "1"
  E scorri cursore di "<posizioni>" posizioni
  E premi pulsante "Conferma"
  E vai alla pagina "login_home"
  E vai alla pagina "lezioni_scrutinio_proposte" con parametri:
    | cattedra   | classe   | periodo |
    | <cattedra> | <classe> | G       |
  Allora la sezione "#gs-main #gs-dropdown-menu" contiene "Scrutinio esami giudizio sospeso"
  E la sezione "#gs-main form #gs-form-collection li:nth-child(1)" contiene "Voto <voto>"
  E la sezione "#gs-main form #gs-form-collection li:nth-child(1) .form-group label" non contiene "Recupero"
  E la sezione "#gs-main form #gs-form-collection li:nth-child(1) .form-group label" non contiene "Argomenti"
  Esempi:
    | utente                          | classe           | cattedra          | posizioni | voto |
    | @docente_curricolare_1:username | @classe_1A:id    | @cattedra_1A_1:id | 0         | 6    |
    | @docente_curricolare_1:username | @classe_1A:id    | @cattedra_1A_1:id | -1        | 5    |
    | @docente_curricolare_2:username | @classe_3CAMB:id | @cattedra_3C_2:id | 3         | 9    |
