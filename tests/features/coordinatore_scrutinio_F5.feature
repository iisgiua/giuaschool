# language: it

Funzionalità: quinto passo dello scrutinio finale
  Per svolgere il quinto passo dello scrutinio finale
  Come utente staff
  Bisogna controllare visualizzazione della pagina
  Bisogna controllare visualizzazione con la classe articolata
  Utilizzando "_scrutinioF5Fixtures.yml"


Contesto: login utente staff
	Dato login utente con ruolo esatto "Staff"


################################################################################
# Bisogna controllare visualizzazione della pagina

Scenario: visualizzazione pagina passo 5
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Allora la sezione "#gs-main h2" contiene "Passo 5"
  E vedi nella tabella "1" le colonne:
    | Alunno | Religione / Att. alt. | Italiano | Storia | Inglese | Matematica | Informatica | Sc. motorie | Ed. civica | Condotta | Media | Esito |
  E vedi la tabella "2" non ordinata senza intestazioni:
    | Alunno                                                     | Religione / Att. alt.                                                                                                    | Italiano                                           | Storia                                             | Inglese                                            | Matematica                                         | Informatica                                        | Sc. motorie                                        | Ed. civica                                         | Condotta                                           | Media                                                                                                                                                                 | Esito |
    | @alunno_1A_1:cognome+ +@alunno_1A_1:nome                   | NA                                                                                                                       | #cas(@voto_F_1A_0:unico,0,NC,@voto_F_1A_0:unico)   | #cas(@voto_F_1A_1:unico,0,NC,@voto_F_1A_1:unico)   | #cas(@voto_F_1A_2:unico,0,NC,@voto_F_1A_2:unico)   | #cas(@voto_F_1A_3:unico,0,NC,@voto_F_1A_3:unico)   | #cas(@voto_F_1A_5:unico,0,NC,@voto_F_1A_5:unico)   | #cas(@voto_F_1A_4:unico,0,NC,@voto_F_1A_4:unico)   | #cas(@voto_F_1A_6:unico,2,NC,@voto_F_1A_6:unico)   | #cas(@voto_F_1A_7:unico,4,NC,@voto_F_1A_7:unico)   | #med(@voto_F_1A_0:unico,@voto_F_1A_1:unico,@voto_F_1A_2:unico,@voto_F_1A_3:unico,@voto_F_1A_4:unico,@voto_F_1A_5:unico,@voto_F_1A_6:unico,@voto_F_1A_7:unico)         |       |
    | @alunno_sostegno_1:cognome+ +@alunno_sostegno_1:nome       | #cas(@voto_F_1A_26:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) | #cas(@voto_F_1A_20:unico,0,NC,@voto_F_1A_20:unico) | #cas(@voto_F_1A_21:unico,0,NC,@voto_F_1A_21:unico) | #cas(@voto_F_1A_22:unico,0,NC,@voto_F_1A_22:unico) | #cas(@voto_F_1A_23:unico,0,NC,@voto_F_1A_23:unico) | #cas(@voto_F_1A_25:unico,0,NC,@voto_F_1A_25:unico) | #cas(@voto_F_1A_24:unico,0,NC,@voto_F_1A_24:unico) | #cas(@voto_F_1A_27:unico,2,NC,@voto_F_1A_27:unico) | #cas(@voto_F_1A_28:unico,4,NC,@voto_F_1A_28:unico) | #med(@voto_F_1A_20:unico,@voto_F_1A_21:unico,@voto_F_1A_22:unico,@voto_F_1A_23:unico,@voto_F_1A_24:unico,@voto_F_1A_25:unico,@voto_F_1A_27:unico,@voto_F_1A_28:unico) |       |
    | @alunno_sostegno_2:cognome+ +@alunno_sostegno_2:nome       | #cas(@voto_F_1A_36:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) | #cas(@voto_F_1A_30:unico,0,NC,@voto_F_1A_30:unico) | #cas(@voto_F_1A_31:unico,0,NC,@voto_F_1A_31:unico) | #cas(@voto_F_1A_32:unico,0,NC,@voto_F_1A_32:unico) | #cas(@voto_F_1A_33:unico,0,NC,@voto_F_1A_33:unico) | #cas(@voto_F_1A_35:unico,0,NC,@voto_F_1A_35:unico) | #cas(@voto_F_1A_34:unico,0,NC,@voto_F_1A_34:unico) | #cas(@voto_F_1A_37:unico,2,NC,@voto_F_1A_37:unico) | #cas(@voto_F_1A_38:unico,4,NC,@voto_F_1A_38:unico) | #med(@voto_F_1A_30:unico,@voto_F_1A_31:unico,@voto_F_1A_32:unico,@voto_F_1A_33:unico,@voto_F_1A_34:unico,@voto_F_1A_35:unico,@voto_F_1A_37:unico,@voto_F_1A_38:unico) |       |
    | @alunno_alternativa_1:cognome+ +@alunno_alternativa_1:nome | #cas(@voto_F_1A_46:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) | #cas(@voto_F_1A_40:unico,0,NC,@voto_F_1A_40:unico) | #cas(@voto_F_1A_41:unico,0,NC,@voto_F_1A_41:unico) | #cas(@voto_F_1A_42:unico,0,NC,@voto_F_1A_42:unico) | #cas(@voto_F_1A_43:unico,0,NC,@voto_F_1A_43:unico) | #cas(@voto_F_1A_45:unico,0,NC,@voto_F_1A_45:unico) | #cas(@voto_F_1A_44:unico,0,NC,@voto_F_1A_44:unico) | #cas(@voto_F_1A_47:unico,2,NC,@voto_F_1A_47:unico) | #cas(@voto_F_1A_48:unico,4,NC,@voto_F_1A_48:unico) | #med(@voto_F_1A_40:unico,@voto_F_1A_41:unico,@voto_F_1A_42:unico,@voto_F_1A_43:unico,@voto_F_1A_44:unico,@voto_F_1A_45:unico,@voto_F_1A_47:unico,@voto_F_1A_48:unico) |       |

Scenario: visualizzazione riquadro inserimento lista voti Ed.Civica
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "Modifica le valutazioni" in sezione "#gs-main form table thead th" che contiene "Ed. civica"
  Allora la sezione "#gs-main #gs-modal-remote .modal-title.gs-h4" contiene "Educazione civica"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection" contiene "?@alunno_1A_1:cognome+ +@alunno_1A_1:nome?@alunno_sostegno_1:cognome+ +@alunno_sostegno_1:nome?@alunno_sostegno_2:cognome+ +@alunno_sostegno_2:nome?@alunno_alternativa_1:cognome+ +@alunno_alternativa_1:nome"

Scenario: visualizzazione riquadro inserimento lista voti Condotta
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "Modifica le valutazioni" in sezione "#gs-main form table thead th" che contiene "Condotta"
  Allora la sezione "#gs-main #gs-modal-remote .modal-title.gs-h4" contiene "Condotta"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection" contiene "?@alunno_1A_1:cognome+ +@alunno_1A_1:nome?@alunno_sostegno_1:cognome+ +@alunno_sostegno_1:nome?@alunno_sostegno_2:cognome+ +@alunno_sostegno_2:nome?@alunno_alternativa_1:cognome+ +@alunno_alternativa_1:nome"

Scenario: visualizzazione riquadro inserimento lista voti Religione
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "Modifica le valutazioni" in sezione "#gs-main form table thead th" che contiene "Religione / Att. alt."
  Allora la sezione "#gs-main #gs-modal-remote .modal-title.gs-h4" contiene "Religione Cattolica"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection" contiene "?@alunno_sostegno_1:cognome+ +@alunno_sostegno_1:nome?@alunno_sostegno_2:cognome+ +@alunno_sostegno_2:nome?@alunno_alternativa_1:cognome+ +@alunno_alternativa_1:nome"

Scenario: visualizzazione riquadro inserimento lista voti altra materia
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "Modifica le valutazioni" in sezione "#gs-main form table thead th" che contiene "@materia_curricolare_1:nomeBreve"
  Allora la sezione "#gs-main #gs-modal-remote .modal-title.gs-h4" contiene "@materia_curricolare_1:nome"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection" contiene "?@alunno_1A_1:cognome+ +@alunno_1A_1:nome?@alunno_sostegno_1:cognome+ +@alunno_sostegno_1:nome?@alunno_sostegno_2:cognome+ +@alunno_sostegno_2:nome?@alunno_alternativa_1:cognome+ +@alunno_alternativa_1:nome"

Scenario: visualizzazione riquadro inserimento voto singolo Ed.Civica
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "Modifica la valutazione di Ed. Civica" in sezione "#gs-main form table tbody tr" che contiene "@alunno_sostegno_1:cognome+ +@alunno_sostegno_1:nome"
  Allora la sezione "#gs-main #gs-modal-remote .modal-title.gs-h4" contiene "Educazione civica"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li div" contiene "@alunno_sostegno_1:cognome+ +@alunno_sostegno_1:nome"

Scenario: visualizzazione riquadro inserimento voto singolo Condotta
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "Modifica la valutazione della Condotta" in sezione "#gs-main form table tbody tr" che contiene "@alunno_sostegno_1:cognome+ +@alunno_sostegno_1:nome"
  Allora la sezione "#gs-main #gs-modal-remote .modal-title.gs-h4" contiene "Condotta"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li div" contiene "@alunno_sostegno_1:cognome+ +@alunno_sostegno_1:nome"

Scenario: visualizzazione riquadro inserimento voto singolo Religione
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "Modifica la valutazione della materia" in sezione "#gs-main form table tbody tr" che contiene "@alunno_sostegno_1:cognome+ +@alunno_sostegno_1:nome" con indice "1"
  Allora la sezione "#gs-main #gs-modal-remote .modal-title.gs-h4" contiene "Religione Cattolica"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li div" contiene "@alunno_sostegno_1:cognome+ +@alunno_sostegno_1:nome"

Scenario: visualizzazione riquadro inserimento voto singolo altra materia
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "Modifica la valutazione della materia" in sezione "#gs-main form table tbody tr" che contiene "@alunno_sostegno_1:cognome+ +@alunno_sostegno_1:nome" con indice "2"
  Allora la sezione "#gs-main #gs-modal-remote .modal-title.gs-h4" contiene "@materia_curricolare_1:nome"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li div" contiene "@alunno_sostegno_1:cognome+ +@alunno_sostegno_1:nome"

Scenario: visualizzazione pagina con dati mancanti - contrari a condotta
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "Modifica la valutazione della Condotta" in sezione "#gs-main form table tbody tr" che contiene "@alunno_1A_1:cognome+ +@alunno_1A_1:nome"
  E selezioni opzione "Maggioranza" da pulsanti radio "condotta_lista_{{@alunno_1A_1:id}}_unanimita"
  E click su "Conferma"
  Allora la sezione "#gs-modal-error .alert-danger" contiene "Non sono stati indicati i docenti contrari"

Scenario: visualizzazione pagina con dati mancanti - tutti senza esito
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "Conferma"
  Allora la sezione "#gs-modal-error .alert-danger" contiene "?@alunno_1A_1:cognome+ +@alunno_1A_1:nome?@alunno_sostegno_1:cognome+ +@alunno_sostegno_1:nome?@alunno_sostegno_2:cognome+ +@alunno_sostegno_2:nome?@alunno_alternativa_1:cognome+ +@alunno_alternativa_1:nome"

Scenario: visualizzazione pagina con dati mancanti - contrari a esito
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "esito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_sostegno_1:cognome+ +@alunno_sostegno_1:nome"
  E selezioni opzione "A" da lista "esito_esito"
  E selezioni opzione "Maggioranza" da pulsanti radio "esito_unanimita"
  E click su "Conferma"
  Allora la sezione "#gs-modal-error .alert-danger" contiene "#str(nome dei docenti contrari all'esito dell'alunn)+#cas(@alunno_sostegno_1:sesso,M,o ,a )+@alunno_sostegno_1:cognome+ +@alunno_sostegno_1:nome"

Scenario: visualizzazione pagina con dati mancanti - ammesso con insufficienze
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_2A:id |
  Quando click su "esito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_2A_7:cognome+ +@alunno_2A_7:nome"
  E selezioni opzione "A" da lista "esito_esito"
  E click su "Conferma"
  Allora la sezione "#gs-modal-error .alert-danger" contiene "#str(insufficienze con un esito di ammissione per l'alunn)+#cas(@alunno_2A_7:sesso,M,o ,a )+@alunno_2A_7:cognome+ +@alunno_2A_7:nome"

Scenario: visualizzazione pagina con dati mancanti - non ammesso senza insufficienze
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_2A:id |
  Quando click su "esito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_2A_2:cognome+ +@alunno_2A_2:nome"
  E selezioni opzione "N" da lista "esito_esito"
  E click su "Conferma"
  Allora la sezione "#gs-modal-error .alert-danger" contiene "#str(solo voti sufficienti con un esito di non ammissione per l'alunn)+#cas(@alunno_2A_2:sesso,M,o ,a )+@alunno_2A_2:cognome+ +@alunno_2A_2:nome"

Scenario: visualizzazione pagina con dati mancanti - sospeso senza insufficienze
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_2A:id |
  Quando click su "esito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_2A_2:cognome+ +@alunno_2A_2:nome"
  E selezioni opzione "S" da lista "esito_esito"
  E click su "Conferma"
  Allora la sezione "#gs-modal-error .alert-danger" contiene "#str(solo voti sufficienti con un giudizio sospeso per l'alunn)+#cas(@alunno_2A_2:sesso,M,o ,a )+@alunno_2A_2:cognome+ +@alunno_2A_2:nome"

Scenario: visualizzazione pagina con dati mancanti - sospeso con più di 3 insufficienze
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_2A:id |
  Quando click su "esito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_2A_1:cognome+ +@alunno_2A_1:nome"
  E selezioni opzione "S" da lista "esito_esito"
  E click su "Conferma"
  Allora la sezione "#gs-modal-error .alert-danger" contiene "#str(sospeso il giudizio con più di tre materie per l'alunn)+#cas(@alunno_2A_1:sesso,M,o ,a )+@alunno_2A_1:cognome+ +@alunno_2A_1:nome"

Scenario: visualizzazione pagina con dati mancanti - sospeso con religione insufficiente
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_2A:id |
  Quando click su "esito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_2A_7:cognome+ +@alunno_2A_7:nome"
  E scorri cursore "1" di "-1" posizioni
  E selezioni opzione "S" da lista "esito_esito"
  E click su "Conferma"
  Allora la sezione "#gs-modal-error .alert-danger" contiene "#str(voto insufficiente di religione non è coerente con l'esito dell'alunn)+#cas(@alunno_2A_7:sesso,M,o ,a )+@alunno_2A_7:cognome+ +@alunno_2A_7:nome"

Scenario: visualizzazione pagina con dati mancanti - quinta ammesso con più di 1 insufficienza
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_5A:id |
  Quando click su "esito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_5A_7:cognome+ +@alunno_5A_7:nome"
  E scorri cursore "2" di "-1" posizioni
  E selezioni opzione "A" da lista "esito_esito"
  E click su "Conferma"
  Allora la sezione "#gs-modal-error .alert-danger" contiene "#str(più di una insufficienza con un esito di ammissione per l'alunn)+#cas(@alunno_5A_7:sesso,M,o ,a )+@alunno_5A_7:cognome+ +@alunno_5A_7:nome"

Scenario: visualizzazione pagina con dati mancanti - quinta ammesso con insufficiente senza motivazione
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_5A:id |
  Quando click su "esito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_5A_7:cognome+ +@alunno_5A_7:nome"
  E selezioni opzione "A" da lista "esito_esito"
  E inserisci "" nel campo "esito_giudizio"
  E click su "Conferma"
  Allora la sezione "#gs-modal-error .alert-danger" contiene "#str(motivazione dell'ammissione con una insufficienza per l'alunn)+#cas(@alunno_5A_7:sesso,M,o ,a )+@alunno_5A_7:cognome+ +@alunno_5A_7:nome"

Scenario: visualizzazione passo precedente
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "passo precedente"
  Allora la sezione "#gs-main h2" contiene "Passo 4"

Scenario: visualizzazione passo successivo
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_2A:id |
  Quando click su "esito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_2A_1:cognome+ +@alunno_2A_1:nome"
  E selezioni opzione "N" da lista "esito_esito"
  E click su "Conferma"
  E click su "esito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_2A_2:cognome+ +@alunno_2A_2:nome"
  E selezioni opzione "A" da lista "esito_esito"
  E click su "Conferma"
  E click su "esito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_2A_3:cognome+ +@alunno_2A_3:nome"
  E selezioni opzione "A" da lista "esito_esito"
  E click su "Conferma"
  E click su "esito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_2A_4:cognome+ +@alunno_2A_4:nome"
  E selezioni opzione "A" da lista "esito_esito"
  E click su "Conferma"
  E click su "esito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_2A_5:cognome+ +@alunno_2A_5:nome"
  E selezioni opzione "A" da lista "esito_esito"
  E click su "Conferma"
  E click su "esito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_2A_6:cognome+ +@alunno_2A_6:nome"
  E selezioni opzione "A" da lista "esito_esito"
  E click su "Conferma"
  E click su "esito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_2A_7:cognome+ +@alunno_2A_7:nome"
  E selezioni opzione "S" da lista "esito_esito"
  E click su "Conferma"
  E click su "Conferma"
  Allora vedi la pagina "coordinatore_scrutinio" con parametri:
    | classe        | stato |
    | @classe_2A:id | 6     |
  Allora la sezione "#gs-main h2" contiene "Passo 6"

Scenario: visualizzazione passo successivo - classe quinta
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_5A:id |
  Quando click su "esito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_5A_1:cognome+ +@alunno_5A_1:nome"
  E selezioni opzione "N" da lista "esito_esito"
  E click su "Conferma"
  E click su "esito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_5A_2:cognome+ +@alunno_5A_2:nome"
  E selezioni opzione "A" da lista "esito_esito"
  E click su "Conferma"
  E click su "esito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_5A_3:cognome+ +@alunno_5A_3:nome"
  E selezioni opzione "A" da lista "esito_esito"
  E click su "Conferma"
  E click su "esito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_5A_4:cognome+ +@alunno_5A_4:nome"
  E selezioni opzione "A" da lista "esito_esito"
  E click su "Conferma"
  E click su "esito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_5A_5:cognome+ +@alunno_5A_5:nome"
  E selezioni opzione "A" da lista "esito_esito"
  E click su "Conferma"
  E click su "esito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_5A_6:cognome+ +@alunno_5A_6:nome"
  E selezioni opzione "A" da lista "esito_esito"
  E click su "Conferma"
  E click su "esito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_5A_7:cognome+ +@alunno_5A_7:nome"
  E selezioni opzione "A" da lista "esito_esito"
  E click su "Conferma"
  E click su "Conferma"
  Allora vedi la pagina "coordinatore_scrutinio" con parametri:
    | classe        | stato |
    | @classe_5A:id | 6     |
  Allora la sezione "#gs-main h2" contiene "Passo 6"

Scenario: memorizzazione dati e passo successivo
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "esito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_1A_1:cognome+ +@alunno_1A_1:nome"
  E scorri cursore "1" di "-10" posizioni
  E scorri cursore "2" di "-10" posizioni
  E scorri cursore "3" di "-10" posizioni
  E scorri cursore "4" di "-10" posizioni
  E scorri cursore "5" di "-10" posizioni
  E scorri cursore "6" di "-10" posizioni
  E scorri cursore "7" di "-10" posizioni
  E selezioni opzione "N" da lista "esito_esito"
  E click su "Conferma"
  Quando click su "esito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_sostegno_1:cognome+ +@alunno_sostegno_1:nome"
  E scorri cursore "1" di "10" posizioni
  E scorri cursore "2" di "10" posizioni
  E scorri cursore "3" di "10" posizioni
  E scorri cursore "4" di "10" posizioni
  E scorri cursore "5" di "10" posizioni
  E scorri cursore "6" di "10" posizioni
  E scorri cursore "7" di "10" posizioni
  E scorri cursore "8" di "10" posizioni
  E selezioni opzione "A" da lista "esito_esito"
  E click su "Conferma"
  Quando click su "esito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_sostegno_2:cognome+ +@alunno_sostegno_2:nome"
  E scorri cursore "1" di "10" posizioni
  E scorri cursore "2" di "10" posizioni
  E scorri cursore "3" di "10" posizioni
  E scorri cursore "4" di "10" posizioni
  E scorri cursore "5" di "10" posizioni
  E scorri cursore "6" di "10" posizioni
  E scorri cursore "7" di "10" posizioni
  E scorri cursore "8" di "10" posizioni
  E selezioni opzione "A" da lista "esito_esito"
  E click su "Conferma"
  Quando click su "esito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_alternativa_1:cognome+ +@alunno_alternativa_1:nome"
  E scorri cursore "1" di "10" posizioni
  E scorri cursore "2" di "10" posizioni
  E scorri cursore "3" di "10" posizioni
  E scorri cursore "4" di "10" posizioni
  E scorri cursore "5" di "10" posizioni
  E scorri cursore "6" di "10" posizioni
  E scorri cursore "7" di "-10" posizioni
  E scorri cursore "8" di "10" posizioni
  E selezioni opzione "S" da lista "esito_esito"
  E click su "Conferma"
  E click su "Modifica le valutazioni" in sezione "#gs-main form table thead th" che contiene "Condotta"
  E scorri cursore "1" di "10" posizioni
  E scorri cursore "2" di "10" posizioni
  E scorri cursore "3" di "10" posizioni
  E scorri cursore "4" di "10" posizioni
  E click su "Conferma"
  E click su "Conferma"
  E click su "passo precedente"
  Allora la sezione "#gs-main h2" contiene "Passo 5"
  E vedi la tabella "2" non ordinata senza intestazioni:
    | Alunno                                                     | Religione / Att. alt. | Italiano | Storia | Inglese | Matematica | Informatica | Sc. motorie | Ed. civica | Condotta | Media | Esito |
    | @alunno_1A_1:cognome+ +@alunno_1A_1:nome                   | NA                    | NC       | NC     | NC      | NC         | NC          | NC          | NC         | 10       | 1,25  |       |
    | @alunno_sostegno_1:cognome+ +@alunno_sostegno_1:nome       | Ottimo                | 10       | 10     | 10      | 10         | 10          | 10          | 10         | 10       | 10,00 |       |
    | @alunno_sostegno_2:cognome+ +@alunno_sostegno_2:nome       | Ottimo                | 10       | 10     | 10      | 10         | 10          | 10          | 10         | 10       | 10,00 |       |
    | @alunno_alternativa_1:cognome+ +@alunno_alternativa_1:nome | Ottimo                | 10       | 10     | 10      | 10         | 10          | NC          | 10         | 10       | 8,75  |       |


################################################################################
# Bisogna controllare visualizzazione con la classe articolata

Scenario: visualizzazione pagina passo 5 per classe articolata
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe           |
    | @classe_3CAMB:id |
  Allora la sezione "#gs-main h2" contiene "Passo 5"
  E vedi nella tabella "1" le colonne:
    | Alunno | Religione / Att. alt. | Italiano | Storia | Inglese | Matematica | Fisica | Sc. motorie | Ed. civica | Condotta | Media | Esito |
  E vedi la tabella "2" non ordinata senza intestazioni:
    | Alunno                                               | Religione / Att. alt.                                                                                                       | Italiano                                                 | Storia                                                   | Inglese                                                  | Matematica                                               | Fisica                                                   | Sc. motorie                                              | Ed. civica                                               | Condotta                                                 | Media                                                                                                                                                                                         | Esito |
    | @alunno_3CAMB_1:cognome+ +@alunno_3CAMB_1:nome       | NA                                                                                                                          | #cas(@voto_F_3CAMB_0:unico,0,NC,@voto_F_3CAMB_0:unico)   | #cas(@voto_F_3CAMB_1:unico,0,NC,@voto_F_3CAMB_1:unico)   | #cas(@voto_F_3CAMB_2:unico,0,NC,@voto_F_3CAMB_2:unico)   | #cas(@voto_F_3CAMB_3:unico,0,NC,@voto_F_3CAMB_3:unico)   | #cas(@voto_F_3CAMB_5:unico,0,NC,@voto_F_3CAMB_5:unico)   | #cas(@voto_F_3CAMB_4:unico,0,NC,@voto_F_3CAMB_4:unico)   | #cas(@voto_F_3CAMB_6:unico,2,NC,@voto_F_3CAMB_6:unico)   | #cas(@voto_F_3CAMB_7:unico,2,NC,@voto_F_3CAMB_7:unico)   | #med(@voto_F_3CAMB_0:unico,@voto_F_3CAMB_1:unico,@voto_F_3CAMB_2:unico,@voto_F_3CAMB_3:unico,@voto_F_3CAMB_4:unico,@voto_F_3CAMB_5:unico,@voto_F_3CAMB_6:unico,@voto_F_3CAMB_7:unico)         |       |
    | @alunno_3CAMB_2:cognome+ +@alunno_3CAMB_2:nome       | #cas(@voto_F_3CAMB_15:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) | #cas(@voto_F_3CAMB_10:unico,0,NC,@voto_F_3CAMB_10:unico) | #cas(@voto_F_3CAMB_11:unico,0,NC,@voto_F_3CAMB_11:unico) | #cas(@voto_F_3CAMB_12:unico,0,NC,@voto_F_3CAMB_12:unico) | #cas(@voto_F_3CAMB_13:unico,0,NC,@voto_F_3CAMB_13:unico) | #cas(@voto_F_3CAMB_16:unico,0,NC,@voto_F_3CAMB_16:unico) | #cas(@voto_F_3CAMB_14:unico,0,NC,@voto_F_3CAMB_14:unico) | #cas(@voto_F_3CAMB_17:unico,2,NC,@voto_F_3CAMB_17:unico) | #cas(@voto_F_3CAMB_18:unico,2,NC,@voto_F_3CAMB_18:unico) | #med(@voto_F_3CAMB_10:unico,@voto_F_3CAMB_11:unico,@voto_F_3CAMB_12:unico,@voto_F_3CAMB_13:unico,@voto_F_3CAMB_14:unico,@voto_F_3CAMB_16:unico,@voto_F_3CAMB_17:unico,@voto_F_3CAMB_18:unico) |       |
    | @alunno_sostegno_4:cognome+ +@alunno_sostegno_4:nome | #cas(@voto_F_3CAMB_25:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) | #cas(@voto_F_3CAMB_20:unico,0,NC,@voto_F_3CAMB_20:unico) | #cas(@voto_F_3CAMB_21:unico,0,NC,@voto_F_3CAMB_21:unico) | #cas(@voto_F_3CAMB_22:unico,0,NC,@voto_F_3CAMB_22:unico) | #cas(@voto_F_3CAMB_23:unico,0,NC,@voto_F_3CAMB_23:unico) | #cas(@voto_F_3CAMB_26:unico,0,NC,@voto_F_3CAMB_26:unico) | #cas(@voto_F_3CAMB_24:unico,0,NC,@voto_F_3CAMB_24:unico) | #cas(@voto_F_3CAMB_27:unico,2,NC,@voto_F_3CAMB_27:unico) | #cas(@voto_F_3CAMB_18:unico,2,NC,@voto_F_3CAMB_28:unico) | #med(@voto_F_3CAMB_20:unico,@voto_F_3CAMB_21:unico,@voto_F_3CAMB_22:unico,@voto_F_3CAMB_23:unico,@voto_F_3CAMB_24:unico,@voto_F_3CAMB_26:unico,@voto_F_3CAMB_27:unico,@voto_F_3CAMB_28:unico) |       |

Scenario: visualizzazione riquadro inserimento lista voti Ed.Civica per classe articolata
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe           |
    | @classe_3CAMB:id |
  Quando click su "Modifica le valutazioni" in sezione "#gs-main form table thead th" che contiene "Ed. civica"
  Allora la sezione "#gs-main #gs-modal-remote .modal-title.gs-h4" contiene "Educazione civica"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection" contiene "?@alunno_3CAMB_1:cognome+ +@alunno_3CAMB_1:nome?@alunno_3CAMB_2:cognome+ +@alunno_3CAMB_2:nome?@alunno_sostegno_4:cognome+ +@alunno_sostegno_4:nome"

Scenario: visualizzazione riquadro inserimento lista voti Condotta per classe articolata
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe           |
    | @classe_3CAMB:id |
  Quando click su "Modifica le valutazioni" in sezione "#gs-main form table thead th" che contiene "Condotta"
  Allora la sezione "#gs-main #gs-modal-remote .modal-title.gs-h4" contiene "Condotta"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection" contiene "?@alunno_3CAMB_1:cognome+ +@alunno_3CAMB_1:nome?@alunno_3CAMB_2:cognome+ +@alunno_3CAMB_2:nome?@alunno_sostegno_4:cognome+ +@alunno_sostegno_4:nome"

Scenario: visualizzazione riquadro inserimento lista voti Religione per classe articolata
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe           |
    | @classe_3CAMB:id |
  Quando click su "Modifica le valutazioni" in sezione "#gs-main form table thead th" che contiene "Religione / Att. alt."
  Allora la sezione "#gs-main #gs-modal-remote .modal-title.gs-h4" contiene "Religione Cattolica"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection" contiene "?@alunno_3CAMB_2:cognome+ +@alunno_3CAMB_2:nome?@alunno_sostegno_4:cognome+ +@alunno_sostegno_4:nome"

Scenario: visualizzazione riquadro inserimento lista voti altra materia per classe articolata
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe           |
    | @classe_3CAMB:id |
  Quando click su "Modifica le valutazioni" in sezione "#gs-main form table thead th" che contiene "@materia_curricolare_1:nomeBreve"
  Allora la sezione "#gs-main #gs-modal-remote .modal-title.gs-h4" contiene "@materia_curricolare_1:nome"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection" contiene "?@alunno_3CAMB_1:cognome+ +@alunno_3CAMB_1:nome?@alunno_3CAMB_2:cognome+ +@alunno_3CAMB_2:nome?@alunno_sostegno_4:cognome+ +@alunno_sostegno_4:nome"

Scenario: visualizzazione riquadro inserimento voto singolo Ed.Civica per classe articolata
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe           |
    | @classe_3CAMB:id |
  Quando click su "Modifica la valutazione di Ed. Civica" in sezione "#gs-main form table tbody tr" che contiene "@alunno_sostegno_4:cognome+ +@alunno_sostegno_4:nome"
  Allora la sezione "#gs-main #gs-modal-remote .modal-title.gs-h4" contiene "Educazione civica"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li div" contiene "@alunno_sostegno_4:cognome+ +@alunno_sostegno_4:nome"

Scenario: visualizzazione riquadro inserimento voto singolo Condotta per classe articolata
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe           |
    | @classe_3CAMB:id |
  Quando click su "Modifica la valutazione della Condotta" in sezione "#gs-main form table tbody tr" che contiene "@alunno_sostegno_4:cognome+ +@alunno_sostegno_4:nome"
  Allora la sezione "#gs-main #gs-modal-remote .modal-title.gs-h4" contiene "Condotta"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li div" contiene "@alunno_sostegno_4:cognome+ +@alunno_sostegno_4:nome"

Scenario: visualizzazione riquadro inserimento voto singolo Religione per classe articolata
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe           |
    | @classe_3CAMB:id |
  Quando click su "Modifica la valutazione della materia" in sezione "#gs-main form table tbody tr" che contiene "@alunno_sostegno_4:cognome+ +@alunno_sostegno_4:nome" con indice "1"
  Allora la sezione "#gs-main #gs-modal-remote .modal-title.gs-h4" contiene "Religione Cattolica"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li div" contiene "@alunno_sostegno_4:cognome+ +@alunno_sostegno_4:nome"

Scenario: visualizzazione riquadro inserimento voto singolo altra materia per classe articolata
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe           |
    | @classe_3CAMB:id |
  Quando click su "Modifica la valutazione della materia" in sezione "#gs-main form table tbody tr" che contiene "@alunno_sostegno_4:cognome+ +@alunno_sostegno_4:nome" con indice "2"
  Allora la sezione "#gs-main #gs-modal-remote .modal-title.gs-h4" contiene "@materia_curricolare_1:nome"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li div" contiene "@alunno_sostegno_4:cognome+ +@alunno_sostegno_4:nome"
@debug
Scenario: visualizzazione passo successivo per classe articolata
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe           |
    | @classe_3CAMB:id |
  Quando click su "esito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_3CAMB_1:cognome+ +@alunno_3CAMB_1:nome"
  E scorri cursore "1" di "-10" posizioni
  E scorri cursore "2" di "-10" posizioni
  E scorri cursore "3" di "-10" posizioni
  E scorri cursore "4" di "-10" posizioni
  E scorri cursore "5" di "-10" posizioni
  E scorri cursore "6" di "-10" posizioni
  E scorri cursore "7" di "-10" posizioni
  E selezioni opzione "N" da lista "esito_esito"
  E click su "Conferma"
  Quando click su "esito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_3CAMB_2:cognome+ +@alunno_3CAMB_2:nome"
  E scorri cursore "1" di "10" posizioni
  E scorri cursore "2" di "10" posizioni
  E scorri cursore "3" di "10" posizioni
  E scorri cursore "4" di "10" posizioni
  E scorri cursore "5" di "10" posizioni
  E scorri cursore "6" di "10" posizioni
  E scorri cursore "7" di "10" posizioni
  E scorri cursore "8" di "10" posizioni
  E selezioni opzione "A" da lista "esito_esito"
  E click su "Conferma"
  Quando click su "esito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_sostegno_4:cognome+ +@alunno_sostegno_4:nome"
  E scorri cursore "1" di "10" posizioni
  E scorri cursore "2" di "10" posizioni
  E scorri cursore "3" di "10" posizioni
  E scorri cursore "4" di "10" posizioni
  E scorri cursore "5" di "10" posizioni
  E scorri cursore "6" di "10" posizioni
  E scorri cursore "7" di "-10" posizioni
  E scorri cursore "8" di "10" posizioni
  E selezioni opzione "S" da lista "esito_esito"
  E click su "Conferma"
  E click su "Modifica le valutazioni" in sezione "#gs-main form table thead th" che contiene "Condotta"
  E scorri cursore "1" di "10" posizioni
  E scorri cursore "2" di "10" posizioni
  E scorri cursore "3" di "10" posizioni
  E click su "Conferma"
  E click su "Conferma"
  E click su "passo precedente"
  Allora la sezione "#gs-main h2" contiene "Passo 5"
  E vedi la tabella "2" non ordinata senza intestazioni:
    | Alunno                                               | Religione / Att. alt. | Italiano | Storia | Inglese | Matematica | Informatica | Sc. motorie | Ed. civica | Condotta | Media | Esito |
    | @alunno_3CAMB_1:cognome+ +@alunno_3CAMB_1:nome       | NA                    | NC       | NC     | NC      | NC         | NC          | NC          | NC         | 10       | 1,25  |       |
    | @alunno_3CAMB_2:cognome+ +@alunno_3CAMB_2:nome       | Ottimo                | 10       | 10     | 10      | 10         | 10          | 10          | 10         | 10       | 10,00 |       |
    | @alunno_sostegno_4:cognome+ +@alunno_sostegno_4:nome | Ottimo                | 10       | 10     | 10      | 10         | 10          | NC          | 10         | 10       | 8,75  |       |
