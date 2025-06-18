# language: it

Funzionalità: terzo passo dello scrutinio finale
  Per svolgere il terzo passo dello scrutinio finale
  Come utente staff
  Bisogna controllare visualizzazione della pagina
  Bisogna controllare visualizzazione con la classe articolata
  Utilizzando "_scrutinioF3Fixtures.yml"


Contesto: login utente staff
	Dato login utente con ruolo esatto "Staff"


################################################################################
# Bisogna controllare visualizzazione della pagina

Scenario: visualizzazione pagina passo 3
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Allora la sezione "#gs-main h2" contiene "Voto di Educazione civica"
  E vedi nella tabella "1" le colonne:
    | Alunno | Religione / Att. alt. | Italiano | Storia | Inglese | Matematica | Informatica | Sc. motorie | Ed. civica | Condotta | Media |
  E vedi la tabella "2" non ordinata senza intestazioni:
    | Alunno                                                     | Religione / Att. alt.                                                                                                    | Italiano                                           | Storia                                             | Inglese                                            | Matematica                                         | Informatica                                        | Sc. motorie                                        | Ed. civica | Condotta | Media                                                                                                                         |
    | @alunno_1A_1:cognome+ +@alunno_1A_1:nome                   | NA                                                                                                                       | #cas(@voto_F_1A_0:unico,0,NC,@voto_F_1A_0:unico)   | #cas(@voto_F_1A_1:unico,0,NC,@voto_F_1A_1:unico)   | #cas(@voto_F_1A_2:unico,0,NC,@voto_F_1A_2:unico)   | #cas(@voto_F_1A_3:unico,0,NC,@voto_F_1A_3:unico)   | #cas(@voto_F_1A_5:unico,0,NC,@voto_F_1A_5:unico)   | #cas(@voto_F_1A_4:unico,0,NC,@voto_F_1A_4:unico)   |            | --       | #med(@voto_F_1A_0:unico,@voto_F_1A_1:unico,@voto_F_1A_2:unico,@voto_F_1A_3:unico,@voto_F_1A_4:unico,@voto_F_1A_5:unico)       |
    | @alunno_sostegno_1:cognome+ +@alunno_sostegno_1:nome       | #cas(@voto_F_1A_26:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) | #cas(@voto_F_1A_20:unico,0,NC,@voto_F_1A_20:unico) | #cas(@voto_F_1A_21:unico,0,NC,@voto_F_1A_21:unico) | #cas(@voto_F_1A_22:unico,0,NC,@voto_F_1A_22:unico) | #cas(@voto_F_1A_23:unico,0,NC,@voto_F_1A_23:unico) | #cas(@voto_F_1A_25:unico,0,NC,@voto_F_1A_25:unico) | #cas(@voto_F_1A_24:unico,0,NC,@voto_F_1A_24:unico) |            | --       | #med(@voto_F_1A_20:unico,@voto_F_1A_21:unico,@voto_F_1A_22:unico,@voto_F_1A_23:unico,@voto_F_1A_24:unico,@voto_F_1A_25:unico) |
    | @alunno_sostegno_2:cognome+ +@alunno_sostegno_2:nome       | #cas(@voto_F_1A_36:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) | #cas(@voto_F_1A_30:unico,0,NC,@voto_F_1A_30:unico) | #cas(@voto_F_1A_31:unico,0,NC,@voto_F_1A_31:unico) | #cas(@voto_F_1A_32:unico,0,NC,@voto_F_1A_32:unico) | #cas(@voto_F_1A_33:unico,0,NC,@voto_F_1A_33:unico) | #cas(@voto_F_1A_35:unico,0,NC,@voto_F_1A_35:unico) | #cas(@voto_F_1A_34:unico,0,NC,@voto_F_1A_34:unico) |            | --       | #med(@voto_F_1A_30:unico,@voto_F_1A_31:unico,@voto_F_1A_32:unico,@voto_F_1A_33:unico,@voto_F_1A_34:unico,@voto_F_1A_35:unico) |
    | @alunno_alternativa_1:cognome+ +@alunno_alternativa_1:nome | #cas(@voto_F_1A_46:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) | #cas(@voto_F_1A_40:unico,0,NC,@voto_F_1A_40:unico) | #cas(@voto_F_1A_41:unico,0,NC,@voto_F_1A_41:unico) | #cas(@voto_F_1A_42:unico,0,NC,@voto_F_1A_42:unico) | #cas(@voto_F_1A_43:unico,0,NC,@voto_F_1A_43:unico) | #cas(@voto_F_1A_45:unico,0,NC,@voto_F_1A_45:unico) | #cas(@voto_F_1A_44:unico,0,NC,@voto_F_1A_44:unico) |            | --       | #med(@voto_F_1A_40:unico,@voto_F_1A_41:unico,@voto_F_1A_42:unico,@voto_F_1A_43:unico,@voto_F_1A_44:unico,@voto_F_1A_45:unico) |

Scenario: visualizzazione riquadro inserimento lista voti
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "Inserisci le valutazioni"
  Allora la sezione "#gs-main #gs-modal-remote .modal-title.gs-h4" contiene "Educazione civica"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection" contiene "?@alunno_1A_1:cognome+ +@alunno_1A_1:nome?@alunno_sostegno_1:cognome+ +@alunno_sostegno_1:nome?@alunno_sostegno_2:cognome+ +@alunno_sostegno_2:nome?@alunno_alternativa_1:cognome+ +@alunno_alternativa_1:nome"

Scenario: visualizzazione riquadro inserimento voto singolo
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "Inserisci la valutazione" in sezione "#gs-main form table tbody tr" che contiene "@alunno_sostegno_1:cognome+ +@alunno_sostegno_1:nome"
  Allora la sezione "#gs-main #gs-modal-remote .modal-title.gs-h4" contiene "Educazione civica"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li div" contiene "@alunno_sostegno_1:cognome+ +@alunno_sostegno_1:nome"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li div .label" contiene "#mdc(@proposta_F_1A_27:unico,@proposta_F_1A_28:unico,@proposta_F_1A_29:unico)"

Scenario: visualizzazione riquadro inserimento voto singolo con dettagli
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "Inserisci la valutazione" in sezione "#gs-main form table tbody tr" che contiene "@alunno_sostegno_1:cognome+ +@alunno_sostegno_1:nome"
  E click su "Visualizza"
  Allora la sezione "#gs-main #gs-modal-remote #gs-form-collection li .gs-show-hide-item" contiene "?@docente_curricolare_1:nome+ +@docente_curricolare_1:cognome+ +#cas(@proposta_F_1A_27:unico,2,NC,@proposta_F_1A_27:unico)?@docente_curricolare_2:nome+ +@docente_curricolare_2:cognome+ +#cas(@proposta_F_1A_28:unico,2,NC,@proposta_F_1A_28:unico)?@docente_curricolare_3:nome+ +@docente_curricolare_3:cognome+ +#cas(@proposta_F_1A_29:unico,2,NC,@proposta_F_1A_29:unico)"

Scenario: visualizzazione pagina con dati mancanti
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "Conferma"
  Allora la sezione "#gs-modal-error .alert-danger" contiene "Manca il voto"

Scenario: visualizzazione passo precedente
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "passo precedente"
  Allora la sezione "#gs-main h2" contiene "Controllo del limite di assenze"

Scenario: visualizzazione passo successivo
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "Inserisci le valutazioni"
  E click su "Aggiungi" con indice "4"
  E click su "Aggiungi" con indice "3"
  E click su "Aggiungi" con indice "2"
  E click su "Aggiungi" con indice "1"
  E click su "Conferma"
  E click su "Conferma"
  Allora vedi la pagina "coordinatore_scrutinio" con parametri:
    | classe        | stato |
    | @classe_1A:id | 4     |
  Allora la sezione "#gs-main h2" contiene "Voto di condotta"

Scenario: memorizzazione dati e passo successivo
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "Inserisci le valutazioni"
  E click su "Aggiungi" con indice "4"
  E click su "Aggiungi" con indice "3"
  E click su "Aggiungi" con indice "2"
  E click su "Aggiungi" con indice "1"
  E scorri cursore "1" di "2" posizioni
  E scorri cursore "2" di "2" posizioni
  E scorri cursore "3" di "2" posizioni
  E scorri cursore "4" di "2" posizioni
  E click su "Conferma"
  E click su "Conferma"
  E click su "passo precedente"
  Allora la sezione "#gs-main h2" contiene "Voto di Educazione civica"
  E vedi nella tabella "1" le colonne:
    | Alunno | Religione / Att. alt. | Italiano | Storia | Inglese | Matematica | Informatica | Sc. motorie | Ed. civica | Condotta | Media |
  E vedi la tabella "2" non ordinata senza intestazioni:
    | Alunno                                                     | Religione / Att. alt.                                                                                                    | Italiano                                           | Storia                                             | Inglese                                            | Matematica                                         | Informatica                                        | Sc. motorie                                        | Ed. civica | Condotta | Media                                                                                                                           |
    | @alunno_1A_1:cognome+ +@alunno_1A_1:nome                   | NA                                                                                                                       | #cas(@voto_F_1A_0:unico,0,NC,@voto_F_1A_0:unico)   | #cas(@voto_F_1A_1:unico,0,NC,@voto_F_1A_1:unico)   | #cas(@voto_F_1A_2:unico,0,NC,@voto_F_1A_2:unico)   | #cas(@voto_F_1A_3:unico,0,NC,@voto_F_1A_3:unico)   | #cas(@voto_F_1A_5:unico,0,NC,@voto_F_1A_5:unico)   | #cas(@voto_F_1A_4:unico,0,NC,@voto_F_1A_4:unico)   | 8          | --       | #med(@voto_F_1A_0:unico,@voto_F_1A_1:unico,@voto_F_1A_2:unico,@voto_F_1A_3:unico,@voto_F_1A_4:unico,@voto_F_1A_5:unico,8)       |
    | @alunno_sostegno_1:cognome+ +@alunno_sostegno_1:nome       | #cas(@voto_F_1A_26:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) | #cas(@voto_F_1A_20:unico,0,NC,@voto_F_1A_20:unico) | #cas(@voto_F_1A_21:unico,0,NC,@voto_F_1A_21:unico) | #cas(@voto_F_1A_22:unico,0,NC,@voto_F_1A_22:unico) | #cas(@voto_F_1A_23:unico,0,NC,@voto_F_1A_23:unico) | #cas(@voto_F_1A_25:unico,0,NC,@voto_F_1A_25:unico) | #cas(@voto_F_1A_24:unico,0,NC,@voto_F_1A_24:unico) | 8          | --       | #med(@voto_F_1A_20:unico,@voto_F_1A_21:unico,@voto_F_1A_22:unico,@voto_F_1A_23:unico,@voto_F_1A_24:unico,@voto_F_1A_25:unico,8) |
    | @alunno_sostegno_2:cognome+ +@alunno_sostegno_2:nome       | #cas(@voto_F_1A_36:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) | #cas(@voto_F_1A_30:unico,0,NC,@voto_F_1A_30:unico) | #cas(@voto_F_1A_31:unico,0,NC,@voto_F_1A_31:unico) | #cas(@voto_F_1A_32:unico,0,NC,@voto_F_1A_32:unico) | #cas(@voto_F_1A_33:unico,0,NC,@voto_F_1A_33:unico) | #cas(@voto_F_1A_35:unico,0,NC,@voto_F_1A_35:unico) | #cas(@voto_F_1A_34:unico,0,NC,@voto_F_1A_34:unico) | 8          | --       | #med(@voto_F_1A_30:unico,@voto_F_1A_31:unico,@voto_F_1A_32:unico,@voto_F_1A_33:unico,@voto_F_1A_34:unico,@voto_F_1A_35:unico,8) |
    | @alunno_alternativa_1:cognome+ +@alunno_alternativa_1:nome | #cas(@voto_F_1A_46:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) | #cas(@voto_F_1A_40:unico,0,NC,@voto_F_1A_40:unico) | #cas(@voto_F_1A_41:unico,0,NC,@voto_F_1A_41:unico) | #cas(@voto_F_1A_42:unico,0,NC,@voto_F_1A_42:unico) | #cas(@voto_F_1A_43:unico,0,NC,@voto_F_1A_43:unico) | #cas(@voto_F_1A_45:unico,0,NC,@voto_F_1A_45:unico) | #cas(@voto_F_1A_44:unico,0,NC,@voto_F_1A_44:unico) | 8          | --       | #med(@voto_F_1A_40:unico,@voto_F_1A_41:unico,@voto_F_1A_42:unico,@voto_F_1A_43:unico,@voto_F_1A_44:unico,@voto_F_1A_45:unico,8) |


################################################################################
# Bisogna controllare visualizzazione con la classe articolata

Scenario: visualizzazione classe articolata
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe           |
    | @classe_3CAMB:id |
  Allora la sezione "#gs-main h2" contiene "Voto di Educazione civica"
  E vedi nella tabella "1" le colonne:
    | Alunno | Religione / Att. alt. | Italiano | Storia | Inglese | Matematica | Fisica | Sc. motorie | Ed. civica | Condotta | Media |
  E vedi la tabella "2" non ordinata senza intestazioni:
    | Alunno                                               | Religione / Att. alt.                                                                                                       | Italiano                                                 | Storia                                                   | Inglese                                                  | Matematica                                               | Fisica                                                   | Sc. motorie                                              | Ed. civica | Condotta | Media                                                                                                                                           |
    | @alunno_3CAMB_1:cognome+ +@alunno_3CAMB_1:nome       | NA                                                                                                                          | #cas(@voto_F_3CAMB_0:unico,0,NC,@voto_F_3CAMB_0:unico)   | #cas(@voto_F_3CAMB_1:unico,0,NC,@voto_F_3CAMB_1:unico)   | #cas(@voto_F_3CAMB_2:unico,0,NC,@voto_F_3CAMB_2:unico)   | #cas(@voto_F_3CAMB_3:unico,0,NC,@voto_F_3CAMB_3:unico)   | #cas(@voto_F_3CAMB_5:unico,0,NC,@voto_F_3CAMB_5:unico)   | #cas(@voto_F_3CAMB_4:unico,0,NC,@voto_F_3CAMB_4:unico)   |            | --       | #med(@voto_F_3CAMB_0:unico,@voto_F_3CAMB_1:unico,@voto_F_3CAMB_2:unico,@voto_F_3CAMB_3:unico,@voto_F_3CAMB_4:unico,@voto_F_3CAMB_5:unico)       |
    | @alunno_3CAMB_2:cognome+ +@alunno_3CAMB_2:nome       | #cas(@voto_F_3CAMB_15:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) | #cas(@voto_F_3CAMB_10:unico,0,NC,@voto_F_3CAMB_10:unico) | #cas(@voto_F_3CAMB_11:unico,0,NC,@voto_F_3CAMB_11:unico) | #cas(@voto_F_3CAMB_12:unico,0,NC,@voto_F_3CAMB_12:unico) | #cas(@voto_F_3CAMB_13:unico,0,NC,@voto_F_3CAMB_13:unico) | #cas(@voto_F_3CAMB_16:unico,0,NC,@voto_F_3CAMB_16:unico) | #cas(@voto_F_3CAMB_14:unico,0,NC,@voto_F_3CAMB_14:unico) |            | --       | #med(@voto_F_3CAMB_10:unico,@voto_F_3CAMB_11:unico,@voto_F_3CAMB_12:unico,@voto_F_3CAMB_13:unico,@voto_F_3CAMB_14:unico,@voto_F_3CAMB_16:unico) |
    | @alunno_sostegno_4:cognome+ +@alunno_sostegno_4:nome | #cas(@voto_F_3CAMB_25:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) | #cas(@voto_F_3CAMB_20:unico,0,NC,@voto_F_3CAMB_20:unico) | #cas(@voto_F_3CAMB_21:unico,0,NC,@voto_F_3CAMB_21:unico) | #cas(@voto_F_3CAMB_22:unico,0,NC,@voto_F_3CAMB_22:unico) | #cas(@voto_F_3CAMB_23:unico,0,NC,@voto_F_3CAMB_23:unico) | #cas(@voto_F_3CAMB_26:unico,0,NC,@voto_F_3CAMB_26:unico) | #cas(@voto_F_3CAMB_24:unico,0,NC,@voto_F_3CAMB_24:unico) |            | --       | #med(@voto_F_3CAMB_20:unico,@voto_F_3CAMB_21:unico,@voto_F_3CAMB_22:unico,@voto_F_3CAMB_23:unico,@voto_F_3CAMB_24:unico,@voto_F_3CAMB_26:unico) |

Scenario: visualizzazione riquadro inserimento lista voti per classe articolata
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe           |
    | @classe_3CAMB:id |
  Quando click su "Inserisci le valutazioni"
  Allora la sezione "#gs-main #gs-modal-remote .modal-title.gs-h4" contiene "Educazione civica"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection" contiene "?@alunno_3CAMB_1:cognome+ +@alunno_3CAMB_1:nome?@alunno_3CAMB_2:cognome+ +@alunno_3CAMB_2:nome?@alunno_sostegno_4:cognome+ +@alunno_sostegno_4:nome"

Scenario: visualizzazione riquadro inserimento voto singolo per classe articolata
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe           |
    | @classe_3CAMB:id |
  Quando click su "Inserisci la valutazione" in sezione "#gs-main form table tbody tr" che contiene "@alunno_3CAMB_2:cognome+ +@alunno_3CAMB_2:nome"
  Allora la sezione "#gs-main #gs-modal-remote .modal-title.gs-h4" contiene "Educazione civica"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li div" contiene "@alunno_3CAMB_2:cognome+ +@alunno_3CAMB_2:nome"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li div .label" contiene "#mdc(@proposta_F_3CAMB_17:unico,@proposta_F_3CAMB_18:unico,@proposta_F_3CAMB_19:unico)"

Scenario: visualizzazione riquadro inserimento voto singolo con dettagli
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe           |
    | @classe_3CAMB:id |
  Quando click su "Inserisci la valutazione" in sezione "#gs-main form table tbody tr" che contiene "@alunno_3CAMB_2:cognome+ +@alunno_3CAMB_2:nome"
  E click su "Visualizza"
  Allora la sezione "#gs-main #gs-modal-remote #gs-form-collection li .gs-show-hide-item" contiene "?@docente_itp_2:nome+ +@docente_itp_2:cognome+ +#cas(@proposta_F_3CAMB_17:unico,2,NC,@proposta_F_3CAMB_17:unico)?@docente_curricolare_2:nome+ +@docente_curricolare_2:cognome+ +#cas(@proposta_F_3CAMB_18:unico,2,NC,@proposta_F_3CAMB_18:unico)?@docente_curricolare_3:nome+ +@docente_curricolare_3:cognome+ +#cas(@proposta_F_3CAMB_19:unico,2,NC,@proposta_F_3CAMB_19:unico)"
