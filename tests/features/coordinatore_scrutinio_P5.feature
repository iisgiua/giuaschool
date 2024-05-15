# language: it

Funzionalità: quinto passo dello scrutinio del primo periodo
  Per svolgere il quinto passo dello scrutinio del primo periodo
  Come utente staff
  Bisogna controllare visualizzazione della pagina
  Bisogna controllare visualizzazione con la classe articolata
  Utilizzando "_scrutinioP5Fixtures.yml"


Contesto: login utente staff
	Dato login utente con ruolo esatto "Staff"


################################################################################
# Bisogna controllare visualizzazione della pagina

Scenario: visualizzazione pagina passo 5
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Allora la sezione "#gs-main h2" contiene "Passo 5"
  E vedi la tabella non ordinata:
    | Alunno                                                     | Materie                                                            | Comunicazione |
    | @alunno_1A_1:cognome+ +@alunno_1A_1:nome                   | ?@materia_curricolare_1:nomeBreve?@materia_curricolare_2:nomeBreve |               |
    | @alunno_1A_2:cognome+ +@alunno_1A_2:nome                   | /.*/                                                               | COMPILATA     |
    | @alunno_sostegno_1:cognome+ +@alunno_sostegno_1:nome       | /.*/                                                               | COMPILATA     |
    | @alunno_sostegno_2:cognome+ +@alunno_sostegno_2:nome       | /.*/                                                               | COMPILATA     |
    | @alunno_alternativa_1:cognome+ +@alunno_alternativa_1:nome | /.*/                                                               | COMPILATA     |

Scenario: visualizzazione riquadro inserimento debiti
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "Compila la comunicazione" in sezione "#gs-main form table tbody tr" che contiene "@alunno_1A_1:cognome+ +@alunno_1A_1:nome"
  Allora la sezione "#gs-main #gs-modal-remote h3.modal-title" contiene "@alunno_1A_1:cognome+ +@alunno_1A_1:nome"
  E la sezione "#gs-main #gs-modal-remote .modal-body div" contiene "Debiti formativi"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection" contiene "?@materia_curricolare_1:nome?@materia_curricolare_2:nome"

Scenario: visualizzazione pagina con dati mancanti
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "Conferma"
  Allora la sezione "#gs-modal-error .alert-danger" contiene "Non è stata compilata la comunicazione"

Scenario: visualizzazione passo precedente
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "passo precedente"
  Allora la sezione "#gs-main h2" contiene "Passo 4"

Scenario: visualizzazione passo successivo
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "Compila la comunicazione" in sezione "#gs-main form table tbody tr" che contiene "@alunno_1A_1:cognome+ +@alunno_1A_1:nome"
  E selezioni opzione "Studio individuale" da lista "debiti_lista_0_recupero"
  E inserisci "Testo" nel campo "debiti_lista_0_debito"
  E selezioni opzione "Studio individuale" da lista "debiti_lista_1_recupero"
  E inserisci "Testo" nel campo "debiti_lista_1_debito"
  E click su "Conferma"
  E click su "Conferma"
  Allora vedi la pagina "coordinatore_scrutinio" con parametri:
    | classe        | stato |
    | @classe_1A:id | 6     |
  Allora la sezione "#gs-main h2" contiene "Passo 6"

Scenario: memorizzazione dati e passo successivo
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "Compila la comunicazione" in sezione "#gs-main form table tbody tr" che contiene "@alunno_1A_1:cognome+ +@alunno_1A_1:nome"
  E selezioni opzione "Studio individuale" da lista "debiti_lista_0_recupero"
  E inserisci "Primo testo" nel campo "debiti_lista_0_debito"
  E selezioni opzione "Studio individuale" da lista "debiti_lista_1_recupero"
  E inserisci "Secondo testo" nel campo "debiti_lista_1_debito"
  E click su "Conferma"
  E click su "Conferma"
  E click su "passo precedente"
  Allora vedi la tabella non ordinata:
    | Alunno                                                     | Materie                                                            | Comunicazione |
    | @alunno_1A_1:cognome+ +@alunno_1A_1:nome                   | ?@materia_curricolare_1:nomeBreve?@materia_curricolare_2:nomeBreve | COMPILATA     |
    | @alunno_1A_2:cognome+ +@alunno_1A_2:nome                   | /.*/                                                               | COMPILATA     |
    | @alunno_sostegno_1:cognome+ +@alunno_sostegno_1:nome       | /.*/                                                               | COMPILATA     |
    | @alunno_sostegno_2:cognome+ +@alunno_sostegno_2:nome       | /.*/                                                               | COMPILATA     |
    | @alunno_alternativa_1:cognome+ +@alunno_alternativa_1:nome | /.*/                                                               | COMPILATA     |

Scenario: memorizzazione dati e passo successivo con dettagli
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "Compila la comunicazione" in sezione "#gs-main form table tbody tr" che contiene "@alunno_1A_1:cognome+ +@alunno_1A_1:nome"
  E selezioni opzione "Studio individuale" da lista "debiti_lista_0_recupero"
  E inserisci "Primo testo" nel campo "debiti_lista_0_debito"
  E selezioni opzione "Studio individuale" da lista "debiti_lista_1_recupero"
  E inserisci "Secondo testo" nel campo "debiti_lista_1_debito"
  E click su "Conferma"
  E click su "Conferma"
  E click su "passo precedente"
  E click su "Modifica la comunicazione" in sezione "#gs-main form table tbody tr" che contiene "@alunno_1A_1:cognome+ +@alunno_1A_1:nome"
  Allora vedi opzione "Studio individuale" in lista "debiti_lista_0_recupero"
  E il campo "debiti_lista_0_debito" contiene "Primo testo"
  E vedi opzione "Studio individuale" in lista "debiti_lista_1_recupero"
  E il campo "debiti_lista_1_debito" contiene "Secondo testo"


################################################################################
# Bisogna controllare visualizzazione con la classe articolata

Scenario: visualizzazione classe articolata
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe           |
    | @classe_3CAMB:id |
  Allora la sezione "#gs-main h2" contiene "Passo 5"
  E vedi la tabella non ordinata:
    | Alunno                                               | Materie                                                            | Comunicazione |
    | @alunno_3CAMB_1:cognome+ +@alunno_3CAMB_1:nome       | ?@materia_curricolare_1:nomeBreve?@materia_curricolare_2:nomeBreve |               |
    | @alunno_3CAMB_2:cognome+ +@alunno_3CAMB_2:nome       | /.*/                                                               | COMPILATA     |
    | @alunno_sostegno_4:cognome+ +@alunno_sostegno_4:nome | /.*/                                                               | COMPILATA     |

Scenario: visualizzazione riquadro inserimento debiti
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe           |
    | @classe_3CAMB:id |
  Quando click su "Compila la comunicazione" in sezione "#gs-main form table tbody tr" che contiene "@alunno_3CAMB_1:cognome+ +@alunno_3CAMB_1:nome"
  Allora la sezione "#gs-main #gs-modal-remote h3.modal-title" contiene "@alunno_3CAMB_1:cognome+ +@alunno_3CAMB_1:nome"
  E la sezione "#gs-main #gs-modal-remote .modal-body div" contiene "Debiti formativi"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection" contiene "?@materia_curricolare_1:nome?@materia_curricolare_2:nome"
