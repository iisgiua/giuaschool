# language: it

Funzionalità: secondo passo dello scrutinio finale
  Per svolgere il secondo passo dello scrutinio finale
  Come utente staff
  Bisogna controllare visualizzazione della pagina
  Bisogna controllare visualizzazione con la classe articolata
  Utilizzando "_scrutinioF2Fixtures.yml"


Contesto: login utente staff
	Dato login utente con ruolo esatto "Staff"


################################################################################
# Bisogna controllare visualizzazione della pagina

Scenario: visualizzazione pagina passo 2
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Allora la sezione "#gs-main h2" contiene "Passo 2"
  E la sezione "#gs-main #gs-alunni-estero" contiene "@alunno_1A_6:cognome+ +@alunno_1A_6:nome+ (+#dat(@alunno_1A_6:dataNascita)+)"
  E vedi la tabella non ordinata:
    | Alunno                                                                       |	Note                          |	Ore di assenza |
    | @alunno_1A_1:cognome+ +@alunno_1A_1:nome+ (+#dat(@alunno_1A_1:dataNascita)+) | Inserimento in data 02/12/2019 |                |
  E il campo "scrutinio_assenze_{{@alunno_1A_1:id}}" contiene "0"
  E la sezione "#gs-main #gs-alunni-no-scrutinabili" contiene "@alunno_1A_2:cognome+ +@alunno_1A_2:nome+ (+#dat(@alunno_1A_2:dataNascita)+), ore di assenza 315 "
  E la sezione "#gs-main #gs-alunni-no-scrutinabili" non contiene "@alunno_1A_1:cognome+ +@alunno_1A_1:nome"
  E la sezione "#gs-main #gs-alunni-no-scrutinabili" non contiene "@alunno_sostegno_1:cognome+ +@alunno_sostegno_1:nome"
  E la sezione "#gs-main #gs-alunni-no-scrutinabili" non contiene "@alunno_sostegno_2:cognome+ +@alunno_sostegno_2:nome"
  E la sezione "#gs-main #gs-alunni-no-scrutinabili" non contiene "@alunno_alternativa_1:cognome+ +@alunno_alternativa_1:nome"

Scenario: visualizzazione pagina passo 2 - aggiunta assenze
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando inserisci "100" nel campo "scrutinio_assenze_{{@alunno_1A_1:id}}"
  E click su "Aggiorna"
  Allora la sezione "#gs-main h2" contiene "Passo 2"
  E la sezione "#gs-main #gs-alunni-estero" contiene "@alunno_1A_6:cognome+ +@alunno_1A_6:nome+ (+#dat(@alunno_1A_6:dataNascita)+)"
  E vedi la tabella non ordinata:
    | Alunno                                                                       |	Note                          |	Ore di assenza |
    | @alunno_1A_1:cognome+ +@alunno_1A_1:nome+ (+#dat(@alunno_1A_1:dataNascita)+) | Inserimento in data 02/12/2019 |                |
  E il campo "scrutinio_assenze_{{@alunno_1A_1:id}}" contiene "100"
  E la sezione "#gs-main #gs-alunni-no-scrutinabili" contiene "@alunno_1A_2:cognome+ +@alunno_1A_2:nome+ (+#dat(@alunno_1A_2:dataNascita)+), ore di assenza 315 "
  E la sezione "#gs-main #gs-alunni-no-scrutinabili" contiene "@alunno_1A_1:cognome+ +@alunno_1A_1:nome+ (+#dat(@alunno_1A_1:dataNascita)+), ore di assenza 310 "
  E la sezione "#gs-main #gs-alunni-no-scrutinabili" non contiene "@alunno_sostegno_1:cognome+ +@alunno_sostegno_1:nome"
  E la sezione "#gs-main #gs-alunni-no-scrutinabili" non contiene "@alunno_sostegno_2:cognome+ +@alunno_sostegno_2:nome"
  E la sezione "#gs-main #gs-alunni-no-scrutinabili" non contiene "@alunno_alternativa_1:cognome+ +@alunno_alternativa_1:nome"

Scenario: visualizzazione pagina passo 2 - senza dati
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_2A:id |
  Allora la sezione "#gs-main h2" contiene "Passo 2"
  E la sezione "#gs-main" non contiene "anno all'estero"
  E la sezione "#gs-main" non contiene "alunni trasferiti in corso d'anno"
  E la sezione "#gs-main #gs-alunni-no-scrutinabili" contiene "NESSUNO"

Scenario: visualizzazione pagina con dati mancanti
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "Conferma"
  Allora la sezione "#gs-modal-error .alert-danger" contiene "/Non hai indicato se .* alunni sono scrutinabili/ui"

Scenario: visualizzazione pagina con dati mancanti - deroga senza motivazione
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando selezioni opzione "deroga" da pulsanti radio "scrutinio_lista_{{@alunno_1A_2:id}}_scrutinabile"
  E click su "Conferma"
  Allora la sezione "#gs-modal-error .alert-danger" contiene "/Non hai indicato le motivazioni della deroga/ui"

Scenario: visualizzazione passo precedente
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "passo precedente"
  Allora la sezione "#gs-main h2" contiene "Passo 1"

Scenario: visualizzazione passo successivo
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando inserisci "100" nel campo "scrutinio_assenze_{{@alunno_1A_1:id}}"
  E click su "Aggiorna"
  E selezioni opzione "limite" da pulsanti radio "scrutinio_lista_{{@alunno_1A_1:id}}_scrutinabile"
  E selezioni opzione "deroga" da pulsanti radio "scrutinio_lista_{{@alunno_1A_2:id}}_scrutinabile"
  E selezioni opzione "S" da lista "scrutinio_lista_{{@alunno_1A_2:id}}_testo"
  E click su "Conferma"
  Allora vedi la pagina "coordinatore_scrutinio" con parametri:
    | classe        | stato |
    | @classe_1A:id | 3     |
  E la sezione "#gs-main h2" contiene "Passo 3"
  E vedi la tabella "2" non ordinata senza intestazioni:
    | Alunno                                                     | Religione / Att. alt. | Italiano | Storia | Inglese | Matematica | Informatica | Sc. motorie | Ed. civica | Condotta | Media |
    | @alunno_1A_2:cognome+ +@alunno_1A_2:nome                   | /.*/                  | /.*/     | /.*/   | /.*/    | /.*/       | /.*/        | /.*/        | /.*/       | /.*/     | /.*/  |
    | @alunno_sostegno_1:cognome+ +@alunno_sostegno_1:nome       | /.*/                  | /.*/     | /.*/   | /.*/    | /.*/       | /.*/        | /.*/        | /.*/       | /.*/     | /.*/  |
    | @alunno_sostegno_2:cognome+ +@alunno_sostegno_2:nome       | /.*/                  | /.*/     | /.*/   | /.*/    | /.*/       | /.*/        | /.*/        | /.*/       | /.*/     | /.*/  |
    | @alunno_alternativa_1:cognome+ +@alunno_alternativa_1:nome | /.*/                  | /.*/     | /.*/   | /.*/    | /.*/       | /.*/        | /.*/        | /.*/       | /.*/     | /.*/  |

Scenario: memorizzazione dati e passo successivo
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando inserisci "100" nel campo "scrutinio_assenze_{{@alunno_1A_1:id}}"
  E click su "Aggiorna"
  E selezioni opzione "limite" da pulsanti radio "scrutinio_lista_{{@alunno_1A_1:id}}_scrutinabile"
  E selezioni opzione "deroga" da pulsanti radio "scrutinio_lista_{{@alunno_1A_2:id}}_scrutinabile"
  E selezioni opzione "S" da lista "scrutinio_lista_{{@alunno_1A_2:id}}_testo"
  E click su "Conferma"
  E click su "passo precedente"
  Allora la sezione "#gs-main h2" contiene "Passo 2"
  E la sezione "#gs-main #gs-alunni-estero" contiene "@alunno_1A_6:cognome+ +@alunno_1A_6:nome+ (+#dat(@alunno_1A_6:dataNascita)+)"
  E vedi la tabella non ordinata:
    | Alunno                                                                       |	Note                          |	Ore di assenza |
    | @alunno_1A_1:cognome+ +@alunno_1A_1:nome+ (+#dat(@alunno_1A_1:dataNascita)+) | Inserimento in data 02/12/2019 |                |
  E il campo "scrutinio_assenze_{{@alunno_1A_1:id}}" contiene "100"
  E la sezione "#gs-main #gs-alunni-no-scrutinabili" contiene "@alunno_1A_2:cognome+ +@alunno_1A_2:nome+ (+#dat(@alunno_1A_2:dataNascita)+), ore di assenza 315 "
  E la sezione "#gs-main #gs-alunni-no-scrutinabili" contiene "@alunno_1A_1:cognome+ +@alunno_1A_1:nome+ (+#dat(@alunno_1A_1:dataNascita)+), ore di assenza 310 "
  E la sezione "#gs-main #gs-alunni-no-scrutinabili" non contiene "@alunno_sostegno_1:cognome+ +@alunno_sostegno_1:nome"
  E la sezione "#gs-main #gs-alunni-no-scrutinabili" non contiene "@alunno_sostegno_2:cognome+ +@alunno_sostegno_2:nome"
  E la sezione "#gs-main #gs-alunni-no-scrutinabili" non contiene "@alunno_alternativa_1:cognome+ +@alunno_alternativa_1:nome"


################################################################################
# Bisogna controllare visualizzazione con la classe articolata

Scenario: visualizzazione classe articolata
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe           |
    | @classe_3CAMB:id |
  Allora la sezione "#gs-main h2" contiene "Passo 2"
  E la sezione "#gs-main" non contiene "anno all'estero"
  E la sezione "#gs-main" non contiene "alunni trasferiti in corso d'anno"
  E la sezione "#gs-main #gs-alunni-no-scrutinabili" contiene "NESSUNO"
