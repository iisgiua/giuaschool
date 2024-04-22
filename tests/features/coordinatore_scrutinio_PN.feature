# language: it

Funzionalità: inizio scrutinio del primo periodo
  Per iniziare lo scrutinio del primo periodo
  Come utente staff
  Bisogna controllare visualizzazione della pagina
  Bisogna controllare visualizzazione con la classe articolata
  Utilizzando "_scrutinioPNFixtures.yml"


Contesto: login utente staff
	Dato login utente con ruolo esatto "Staff"


################################################################################
# Bisogna controllare visualizzazione della pagina

Scenario: visualizzazione pagina con voti mancanti
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Allora la sezione "#gs-main h2" contiene "Proposte di voto"
  E la sezione "#gs-modal-error .alert-danger" contiene "/Religione \/ Att\. alt\.: manca il voto.*Informatica: manca il voto/ui"
  E la sezione "#gs-main .alert-warning" non contiene "manca"
  E la sezione "#gs-main form #gs-button-start" non contiene "Apri lo scrutinio"

Scenario: visualizzazione pagina con dati mancanti
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "Chiudi"
  E click su "Inserisci i voti mancanti" con indice "2"
  E click su "Aggiungi"
  E scorri cursore di "-1" posizione
  E click su "Conferma"
  Allora la sezione "#gs-modal-error .alert-danger" contiene "Religione / Att. alt.: manca il voto"
  E la sezione "#gs-main .alert-warning" contiene "Informatica: manca la modalità del recupero"
  E la sezione "#gs-main form #gs-button-start" non contiene "Apri lo scrutinio"

Scenario: visualizzazione pagina con dati al completo
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "Chiudi"
  E click su "Inserisci i voti mancanti" con indice "2"
  E click su "Aggiungi"
  E scorri cursore di "1" posizione
  E click su "Conferma"
  E click su "Chiudi"
  E click su "Inserisci i voti mancanti"
  E click su "Aggiungi"
  E scorri cursore di "-2" posizioni
  E click su "Conferma"
  Allora la sezione "#gs-modal-error .alert-danger" non contiene "manca"
  E la sezione "#gs-main .alert-warning" non contiene "manca"
  E la sezione "#gs-main form #gs-button-start" contiene "Apri lo scrutinio"
  E vedi nella tabella "1" le colonne:
    | Alunno | Religione / Att. alt. | Italiano | Storia | Inglese | Matematica | Informatica | Sc. motorie | Ed. civica |
  E vedi la tabella "2" non ordinata senza intestazioni:
    | Alunno                                                     | Religione / Att. alt.                                                                                                        | Italiano                                                   | Storia                                                     | Inglese                                                    | Matematica                                                 | Informatica                                                | Sc. motorie                                                | Ed. civica |
    | @alunno_1A_1:cognome+ +@alunno_1A_1:nome                   | NA                                                                                                                           | #cas(@proposta_P_1A_0:unico,0,NC,@proposta_P_1A_0:unico)   | #cas(@proposta_P_1A_1:unico,0,NC,@proposta_P_1A_1:unico)   | #cas(@proposta_P_1A_2:unico,0,NC,@proposta_P_1A_2:unico)   | #cas(@proposta_P_1A_3:unico,0,NC,@proposta_P_1A_3:unico)   | #cas(@proposta_P_1A_5:unico,0,NC,@proposta_P_1A_5:unico)   | #cas(@proposta_P_1A_4:unico,0,NC,@proposta_P_1A_4:unico)   |            |
    | @alunno_1A_2:cognome+ +@alunno_1A_2:nome                   | #cas(@proposta_P_1A_16:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) | #cas(@proposta_P_1A_10:unico,0,NC,@proposta_P_1A_10:unico) | #cas(@proposta_P_1A_11:unico,0,NC,@proposta_P_1A_11:unico) | #cas(@proposta_P_1A_12:unico,0,NC,@proposta_P_1A_12:unico) | #cas(@proposta_P_1A_13:unico,0,NC,@proposta_P_1A_13:unico) | #cas(@proposta_P_1A_15:unico,0,NC,@proposta_P_1A_15:unico) | #cas(@proposta_P_1A_14:unico,0,NC,@proposta_P_1A_14:unico) |            |
    | @alunno_sostegno_1:cognome+ +@alunno_sostegno_1:nome       | #cas(@proposta_P_1A_26:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) | #cas(@proposta_P_1A_20:unico,0,NC,@proposta_P_1A_20:unico) | #cas(@proposta_P_1A_21:unico,0,NC,@proposta_P_1A_21:unico) | #cas(@proposta_P_1A_22:unico,0,NC,@proposta_P_1A_22:unico) | #cas(@proposta_P_1A_23:unico,0,NC,@proposta_P_1A_23:unico) | #cas(@proposta_P_1A_25:unico,0,NC,@proposta_P_1A_25:unico) | #cas(@proposta_P_1A_24:unico,0,NC,@proposta_P_1A_24:unico) |            |
    | @alunno_sostegno_2:cognome+ +@alunno_sostegno_2:nome       | #cas(@proposta_P_1A_36:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) | #cas(@proposta_P_1A_30:unico,0,NC,@proposta_P_1A_30:unico) | #cas(@proposta_P_1A_31:unico,0,NC,@proposta_P_1A_31:unico) | #cas(@proposta_P_1A_32:unico,0,NC,@proposta_P_1A_32:unico) | #cas(@proposta_P_1A_33:unico,0,NC,@proposta_P_1A_33:unico) | #cas(@proposta_P_1A_35:unico,0,NC,@proposta_P_1A_35:unico) | #cas(@proposta_P_1A_34:unico,0,NC,@proposta_P_1A_34:unico) |            |
    | @alunno_alternativa_1:cognome+ +@alunno_alternativa_1:nome | Insufficiente                                                                                                                | #cas(@proposta_P_1A_40:unico,0,NC,@proposta_P_1A_40:unico) | #cas(@proposta_P_1A_41:unico,0,NC,@proposta_P_1A_41:unico) | #cas(@proposta_P_1A_42:unico,0,NC,@proposta_P_1A_42:unico) | #cas(@proposta_P_1A_43:unico,0,NC,@proposta_P_1A_43:unico) | 7                                                          | #cas(@proposta_P_1A_44:unico,0,NC,@proposta_P_1A_44:unico) | --         |

Scenario: visualizzazione passo successivo
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "Chiudi"
  E click su "Inserisci i voti mancanti" con indice "2"
  E click su "Aggiungi"
  E click su "Conferma"
  E click su "Chiudi"
  E click su "Inserisci i voti mancanti"
  E click su "Aggiungi"
  E click su "Conferma"
  E click su "Apri lo scrutinio"
  Allora vedi la pagina "coordinatore_scrutinio" con parametri:
    | classe        | stato |
    | @classe_1A:id | 1     |
  E la sezione "#gs-main h2" contiene "Passo 1"

Scenario: memorizzazione dati e passo successivo
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "Chiudi"
  E click su "Inserisci i voti mancanti" con indice "2"
  E click su "Aggiungi"
  E scorri cursore di "1" posizione
  E click su "Conferma"
  E click su "Chiudi"
  E click su "Inserisci i voti mancanti"
  E click su "Aggiungi"
  E scorri cursore di "-2" posizioni
  E click su "Conferma"
  E click su "Apri lo scrutinio"
  E click su "passo precedente"
  Allora la sezione "#gs-main h2" contiene "Proposte di voto"
  E vedi nella tabella "1" le colonne:
    | Alunno | Religione / Att. alt. | Italiano | Storia | Inglese | Matematica | Informatica | Sc. motorie | Ed. civica |
  E vedi la tabella "2" non ordinata senza intestazioni:
    | Alunno                                                     | Religione / Att. alt.                                                                                                        | Italiano                                                   | Storia                                                     | Inglese                                                    | Matematica                                                 | Informatica                                                | Sc. motorie                                                | Ed. civica |
    | @alunno_1A_1:cognome+ +@alunno_1A_1:nome                   | NA                                                                                                                           | #cas(@proposta_P_1A_0:unico,0,NC,@proposta_P_1A_0:unico)   | #cas(@proposta_P_1A_1:unico,0,NC,@proposta_P_1A_1:unico)   | #cas(@proposta_P_1A_2:unico,0,NC,@proposta_P_1A_2:unico)   | #cas(@proposta_P_1A_3:unico,0,NC,@proposta_P_1A_3:unico)   | #cas(@proposta_P_1A_5:unico,0,NC,@proposta_P_1A_5:unico)   | #cas(@proposta_P_1A_4:unico,0,NC,@proposta_P_1A_4:unico)   |            |
    | @alunno_1A_2:cognome+ +@alunno_1A_2:nome                   | #cas(@proposta_P_1A_16:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) | #cas(@proposta_P_1A_10:unico,0,NC,@proposta_P_1A_10:unico) | #cas(@proposta_P_1A_11:unico,0,NC,@proposta_P_1A_11:unico) | #cas(@proposta_P_1A_12:unico,0,NC,@proposta_P_1A_12:unico) | #cas(@proposta_P_1A_13:unico,0,NC,@proposta_P_1A_13:unico) | #cas(@proposta_P_1A_15:unico,0,NC,@proposta_P_1A_15:unico) | #cas(@proposta_P_1A_14:unico,0,NC,@proposta_P_1A_14:unico) |            |
    | @alunno_sostegno_1:cognome+ +@alunno_sostegno_1:nome       | #cas(@proposta_P_1A_26:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) | #cas(@proposta_P_1A_20:unico,0,NC,@proposta_P_1A_20:unico) | #cas(@proposta_P_1A_21:unico,0,NC,@proposta_P_1A_21:unico) | #cas(@proposta_P_1A_22:unico,0,NC,@proposta_P_1A_22:unico) | #cas(@proposta_P_1A_23:unico,0,NC,@proposta_P_1A_23:unico) | #cas(@proposta_P_1A_25:unico,0,NC,@proposta_P_1A_25:unico) | #cas(@proposta_P_1A_24:unico,0,NC,@proposta_P_1A_24:unico) |            |
    | @alunno_sostegno_2:cognome+ +@alunno_sostegno_2:nome       | #cas(@proposta_P_1A_36:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) | #cas(@proposta_P_1A_30:unico,0,NC,@proposta_P_1A_30:unico) | #cas(@proposta_P_1A_31:unico,0,NC,@proposta_P_1A_31:unico) | #cas(@proposta_P_1A_32:unico,0,NC,@proposta_P_1A_32:unico) | #cas(@proposta_P_1A_33:unico,0,NC,@proposta_P_1A_33:unico) | #cas(@proposta_P_1A_35:unico,0,NC,@proposta_P_1A_35:unico) | #cas(@proposta_P_1A_34:unico,0,NC,@proposta_P_1A_34:unico) |            |
    | @alunno_alternativa_1:cognome+ +@alunno_alternativa_1:nome | Insufficiente                                                                                                                | #cas(@proposta_P_1A_40:unico,0,NC,@proposta_P_1A_40:unico) | #cas(@proposta_P_1A_41:unico,0,NC,@proposta_P_1A_41:unico) | #cas(@proposta_P_1A_42:unico,0,NC,@proposta_P_1A_42:unico) | #cas(@proposta_P_1A_43:unico,0,NC,@proposta_P_1A_43:unico) | 7                                                          | #cas(@proposta_P_1A_44:unico,0,NC,@proposta_P_1A_44:unico) | --         |


################################################################################
# Bisogna controllare visualizzazione con la classe articolata

Scenario: visualizzazione classe articolata con dati mancanti
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe           |
    | @classe_3CAMB:id |
  Allora la sezione "#gs-main h2" contiene "Proposte di voto"
  E la sezione "#gs-modal-error .alert-danger" contiene "/Religione \/ Att\. alt\.: manca il voto.*Fisica: manca il voto.*Sc\. motorie: manca il voto/ui"
  E la sezione "#gs-main .alert-warning" non contiene "manca"
  E la sezione "#gs-main form #gs-button-start" non contiene "Apri lo scrutinio"

Scenario: visualizzazione classe articolata con dati al completo
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe           |
    | @classe_3CAMB:id |
  Quando click su "Chiudi"
  E click su "Inserisci i voti mancanti" con indice "2"
  E click su "Aggiungi"
  E scorri cursore di "2" posizione
  E click su "Conferma"
  E click su "Chiudi"
  E click su "Inserisci i voti mancanti" con indice "2"
  E click su "Aggiungi"
  E scorri cursore di "4" posizioni
  E click su "Conferma"
  E click su "Chiudi"
  E click su "Inserisci i voti mancanti"
  E click su "Aggiungi"
  E scorri cursore di "-1" posizioni
  E click su "Conferma"
  Allora la sezione "#gs-modal-error .alert-danger" non contiene "manca"
  E la sezione "#gs-main .alert-warning" non contiene "manca"
  E la sezione "#gs-main form #gs-button-start" contiene "Apri lo scrutinio"
  E vedi nella tabella "1" le colonne:
    | Alunno | Religione / Att. alt. | Italiano | Storia | Inglese | Matematica | Fisica | Sc. motorie | Ed. civica |
  E vedi la tabella "2" non ordinata senza intestazioni:
    | Alunno                                               | Religione / Att. alt.                                                                                                           | Italiano                                                         | Storia                                                           | Inglese                                                          | Matematica                                                       | Fisica                                                           | Sc. motorie                                                      | Ed. civica |
    | @alunno_3CAMB_1:cognome+ +@alunno_3CAMB_1:nome       | NA                                                                                                                              | #cas(@proposta_P_3CAMB_0:unico,0,NC,@proposta_P_3CAMB_0:unico)   | #cas(@proposta_P_3CAMB_1:unico,0,NC,@proposta_P_3CAMB_1:unico)   | #cas(@proposta_P_3CAMB_2:unico,0,NC,@proposta_P_3CAMB_2:unico)   | #cas(@proposta_P_3CAMB_3:unico,0,NC,@proposta_P_3CAMB_3:unico)   | #cas(@proposta_P_3CAMB_5:unico,0,NC,@proposta_P_3CAMB_5:unico)   | #cas(@proposta_P_3CAMB_4:unico,0,NC,@proposta_P_3CAMB_4:unico)   |            |
    | @alunno_3CAMB_2:cognome+ +@alunno_3CAMB_2:nome       | #cas(@proposta_P_3CAMB_15:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) | #cas(@proposta_P_3CAMB_10:unico,0,NC,@proposta_P_3CAMB_10:unico) | #cas(@proposta_P_3CAMB_11:unico,0,NC,@proposta_P_3CAMB_11:unico) | #cas(@proposta_P_3CAMB_12:unico,0,NC,@proposta_P_3CAMB_12:unico) | #cas(@proposta_P_3CAMB_13:unico,0,NC,@proposta_P_3CAMB_13:unico) | #cas(@proposta_P_3CAMB_16:unico,0,NC,@proposta_P_3CAMB_16:unico) | #cas(@proposta_P_3CAMB_14:unico,0,NC,@proposta_P_3CAMB_14:unico) |            |
    | @alunno_sostegno_4:cognome+ +@alunno_sostegno_4:nome | Mediocre                                                                                                                        | #cas(@proposta_P_3CAMB_20:unico,0,NC,@proposta_P_3CAMB_20:unico) | #cas(@proposta_P_3CAMB_21:unico,0,NC,@proposta_P_3CAMB_21:unico) | #cas(@proposta_P_3CAMB_22:unico,0,NC,@proposta_P_3CAMB_22:unico) | #cas(@proposta_P_3CAMB_23:unico,0,NC,@proposta_P_3CAMB_23:unico) | 8                                                                | 10                                                               | --         |
