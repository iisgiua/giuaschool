# language: it

Funzionalità: settimo passo dello scrutinio finale
  Per svolgere il settimo passo dello scrutinio finale
  Come utente staff
  Bisogna controllare visualizzazione della pagina
  Bisogna controllare visualizzazione con la classe articolata
  Utilizzando "_scrutinioF7Fixtures.yml"


Contesto: login utente staff
	Dato login utente con ruolo esatto "Staff"


###############################################################################
# Bisogna controllare visualizzazione della pagina

Scenario: visualizzazione pagina passo 7
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Allora la sezione "#gs-main h2" contiene "Passo 7"
  E la sezione "#gs-main form > div:nth-child(3) > table > caption" contiene "Debiti formativi"
  E vedi la tabella "1" non ordinata:
    | Alunno                                   | Materie                                                                                                  | Comunicazione |
    | @alunno_1A_1:cognome+ +@alunno_1A_1:nome | @materia_curricolare_1:nomeBreve+, +@materia_curricolare_2:nomeBreve+, +@materia_curricolare_3:nomeBreve |               |
  E la sezione "#gs-main form > div:nth-child(4) > table > caption" contiene "Comunicazione carenze"
  E vedi la tabella "2" non ordinata:
    | Alunno                                               | Materie                                                              | Comunicazione |
    | @alunno_1A_1:cognome+ +@alunno_1A_1:nome             | @materia_curricolare_4:nomeBreve+, +@materia_curricolare_5:nomeBreve |               |
    | @alunno_sostegno_1:cognome+ +@alunno_sostegno_1:nome | @materia_itp_1:nomeBreve+, +@materia_curricolare_5:nomeBreve         |               |
    | @alunno_sostegno_2:cognome+ +@alunno_sostegno_2:nome | @materia_itp_1:nomeBreve+, +@materia_curricolare_5:nomeBreve         |               |

Scenario: visualizzazione pagina passo 7 per classi quinte
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_5A:id |
  Allora la sezione "#gs-main h2" contiene "Passo 7"
  E la sezione "#gs-main form > div:nth-child(3) > table > caption" non contiene "Debiti formativi"
  E non vedi la tabella:
    | Alunno | Materie | Comunicazione |
  E la sezione "#gs-main form > div:nth-child(4) > table > caption" non contiene "Comunicazione carenze"

Scenario: visualizzazione riquadro inserimento debiti
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "Compila la comunicazione dei debiti" in sezione "#gs-main form > div:nth-child(3) > table > tbody > tr" che contiene "@alunno_1A_1:cognome+ +@alunno_1A_1:nome"
  Allora la sezione "#gs-main #gs-modal-remote h3.modal-title" contiene "@alunno_1A_1:cognome+ +@alunno_1A_1:nome"
  E la sezione "#gs-main #gs-modal-remote .modal-body div" contiene "Debiti formativi"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection" contiene "?@materia_curricolare_1:nome?@materia_curricolare_2:nome?@materia_curricolare_3:nome"

Scenario: visualizzazione riquadro inserimento carenze
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "Compila la comunicazione delle carenze" in sezione "#gs-main form > div:nth-child(4) > table > tbody > tr" che contiene "@alunno_sostegno_1:cognome+ +@alunno_sostegno_1:nome"
  Allora la sezione "#gs-main #gs-modal-remote h3.modal-title" contiene "@alunno_sostegno_1:cognome+ +@alunno_sostegno_1:nome"
  E la sezione "#gs-main #gs-modal-remote .modal-body div" contiene "Carenze"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection" contiene "?@materia_itp_1:nome?@materia_curricolare_5:nome"

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
  Allora la sezione "#gs-main h2" contiene "Passo 6"

Scenario: visualizzazione passo successivo
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "Compila la comunicazione dei debiti" in sezione "#gs-main form > div:nth-child(3) > table > tbody > tr" che contiene "@alunno_1A_1:cognome+ +@alunno_1A_1:nome"
  E click su "Conferma"
  E click su "Compila la comunicazione delle carenze" in sezione "#gs-main form > div:nth-child(4) > table > tbody > tr" che contiene "@alunno_1A_1:cognome+ +@alunno_1A_1:nome"
  E click su "Conferma"
  E click su "Compila la comunicazione delle carenze" in sezione "#gs-main form > div:nth-child(4) > table > tbody > tr" che contiene "@alunno_sostegno_1:cognome+ +@alunno_sostegno_1:nome"
  E click su "Conferma"
  E click su "Compila la comunicazione delle carenze" in sezione "#gs-main form > div:nth-child(4) > table > tbody > tr" che contiene "@alunno_sostegno_2:cognome+ +@alunno_sostegno_2:nome"
  E click su "Conferma"
  E click su "Conferma"
  Allora vedi la pagina "coordinatore_scrutinio" con parametri:
    | classe        | stato |
    | @classe_1A:id | 8     |
  Allora la sezione "#gs-main h2" contiene "Passo 8"

Scenario: memorizzazione dati e passo successivo
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "Compila la comunicazione dei debiti" in sezione "#gs-main form > div:nth-child(3) > table > tbody > tr" che contiene "@alunno_1A_1:cognome+ +@alunno_1A_1:nome"
  E click su "Conferma"
  E click su "Compila la comunicazione delle carenze" in sezione "#gs-main form > div:nth-child(4) > table > tbody > tr" che contiene "@alunno_1A_1:cognome+ +@alunno_1A_1:nome"
  E inserisci "" nel campo "carenze_lista_0_debito"
  E inserisci "" nel campo "carenze_lista_1_debito"
  E click su "Conferma"
  E click su "Compila la comunicazione delle carenze" in sezione "#gs-main form > div:nth-child(4) > table > tbody > tr" che contiene "@alunno_sostegno_1:cognome+ +@alunno_sostegno_1:nome"
  E inserisci "Qualcosa..." nel campo "carenze_lista_0_debito"
  E inserisci "" nel campo "carenze_lista_1_debito"
  E click su "Conferma"
  E click su "Compila la comunicazione delle carenze" in sezione "#gs-main form > div:nth-child(4) > table > tbody > tr" che contiene "@alunno_sostegno_2:cognome+ +@alunno_sostegno_2:nome"
  E inserisci "Qualcosa..." nel campo "carenze_lista_0_debito"
  E inserisci "Qualcosa..." nel campo "carenze_lista_1_debito"
  E click su "Conferma"
  E click su "Conferma"
  E click su "passo precedente"
  Allora vedi la tabella "1" non ordinata:
    | Alunno                                   | Materie                                                                                                  | Comunicazione |
    | @alunno_1A_1:cognome+ +@alunno_1A_1:nome | @materia_curricolare_1:nomeBreve+, +@materia_curricolare_2:nomeBreve+, +@materia_curricolare_3:nomeBreve | COMPILATA     |
  E vedi la tabella "2" non ordinata:
    | Alunno                                               | Materie                                                              | Comunicazione                                                          |
    | @alunno_1A_1:cognome+ +@alunno_1A_1:nome             | @materia_curricolare_4:nomeBreve+, +@materia_curricolare_5:nomeBreve | COMPILATA NESSUNA                                                      |
    | @alunno_sostegno_1:cognome+ +@alunno_sostegno_1:nome | @materia_itp_1:nomeBreve+, +@materia_curricolare_5:nomeBreve         | #str(COMPILATA)+ +@materia_itp_1:nomeBreve                                     |
    | @alunno_sostegno_2:cognome+ +@alunno_sostegno_2:nome | @materia_itp_1:nomeBreve+, +@materia_curricolare_5:nomeBreve         | #str(COMPILATA)+ +@materia_itp_1:nomeBreve+, +@materia_curricolare_5:nomeBreve |

Scenario: memorizzazione dati e passo successivo con dettagli debiti
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "Compila la comunicazione dei debiti" in sezione "#gs-main form > div:nth-child(3) > table > tbody > tr" che contiene "@alunno_1A_1:cognome+ +@alunno_1A_1:nome"
  E selezioni opzione "Studio individuale" da lista "debiti_lista_0_recupero"
  E inserisci "Primo testo..." nel campo "debiti_lista_0_debito"
  E selezioni opzione "Studio individuale" da lista "debiti_lista_1_recupero"
  E inserisci "Secondo testo..." nel campo "debiti_lista_1_debito"
  E selezioni opzione "Studio individuale" da lista "debiti_lista_2_recupero"
  E inserisci "Terzo testo..." nel campo "debiti_lista_2_debito"
  E click su "Conferma"
  E click su "Compila la comunicazione delle carenze" in sezione "#gs-main form > div:nth-child(4) > table > tbody > tr" che contiene "@alunno_1A_1:cognome+ +@alunno_1A_1:nome"
  E click su "Conferma"
  E click su "Compila la comunicazione delle carenze" in sezione "#gs-main form > div:nth-child(4) > table > tbody > tr" che contiene "@alunno_sostegno_1:cognome+ +@alunno_sostegno_1:nome"
  E click su "Conferma"
  E click su "Compila la comunicazione delle carenze" in sezione "#gs-main form > div:nth-child(4) > table > tbody > tr" che contiene "@alunno_sostegno_2:cognome+ +@alunno_sostegno_2:nome"
  E click su "Conferma"
  E click su "Conferma"
  E click su "passo precedente"
  E click su "Modifica la comunicazione dei debiti" in sezione "#gs-main form > div:nth-child(3) > table > tbody > tr" che contiene "@alunno_1A_1:cognome+ +@alunno_1A_1:nome"
  Allora opzione "Studio individuale" selezionata da lista "debiti_lista_0_recupero"
  E il campo "debiti_lista_0_debito" contiene "Primo testo..."
  E opzione "Studio individuale" selezionata da lista "debiti_lista_1_recupero"
  E il campo "debiti_lista_1_debito" contiene "Secondo testo..."
  E opzione "Studio individuale" selezionata da lista "debiti_lista_2_recupero"
  E il campo "debiti_lista_2_debito" contiene "Terzo testo..."

Scenario: memorizzazione dati e passo successivo con dettagli carenze
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "Compila la comunicazione dei debiti" in sezione "#gs-main form > div:nth-child(3) > table > tbody > tr" che contiene "@alunno_1A_1:cognome+ +@alunno_1A_1:nome"
  E click su "Conferma"
  E click su "Compila la comunicazione delle carenze" in sezione "#gs-main form > div:nth-child(4) > table > tbody > tr" che contiene "@alunno_1A_1:cognome+ +@alunno_1A_1:nome"
  E inserisci "" nel campo "carenze_lista_0_debito"
  E inserisci "" nel campo "carenze_lista_1_debito"
  E click su "Conferma"
  E click su "Compila la comunicazione delle carenze" in sezione "#gs-main form > div:nth-child(4) > table > tbody > tr" che contiene "@alunno_sostegno_1:cognome+ +@alunno_sostegno_1:nome"
  E inserisci "Qualcosa..." nel campo "carenze_lista_0_debito"
  E inserisci "" nel campo "carenze_lista_1_debito"
  E click su "Conferma"
  E click su "Compila la comunicazione delle carenze" in sezione "#gs-main form > div:nth-child(4) > table > tbody > tr" che contiene "@alunno_sostegno_2:cognome+ +@alunno_sostegno_2:nome"
  E inserisci "Qualcosa..." nel campo "carenze_lista_0_debito"
  E inserisci "Qualcosa..." nel campo "carenze_lista_1_debito"
  E click su "Conferma"
  E click su "Conferma"
  E click su "passo precedente"
  E click su "Modifica la comunicazione delle carenze" in sezione "#gs-main form > div:nth-child(4) > table > tbody > tr" che contiene "@alunno_sostegno_2:cognome+ +@alunno_sostegno_2:nome"
  Allora il campo "carenze_lista_0_debito" contiene "Qualcosa..."
  E il campo "carenze_lista_1_debito" contiene "Qualcosa..."


###############################################################################
# Bisogna controllare visualizzazione con la classe articolata

Scenario: visualizzazione pagina passo 7 per la classe articolata
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe           |
    | @classe_3CAMB:id |
  Allora la sezione "#gs-main h2" contiene "Passo 7"
  E la sezione "#gs-main form > div:nth-child(3) > table > caption" contiene "Debiti formativi"
  E vedi la tabella "1" non ordinata:
    | Alunno                                   | Materie                                                                                                  | Comunicazione |
    | @alunno_3CAMB_1:cognome+ +@alunno_3CAMB_1:nome | @materia_curricolare_1:nomeBreve+, +@materia_curricolare_2:nomeBreve+, +@materia_curricolare_3:nomeBreve |               |
  E la sezione "#gs-main form > div:nth-child(4) > table > caption" contiene "Comunicazione carenze"
  E vedi la tabella "2" non ordinata:
    | Alunno                                               | Materie                                                              | Comunicazione |
    | @alunno_3CAMB_1:cognome+ +@alunno_3CAMB_1:nome       | @materia_curricolare_4:nomeBreve+, +@materia_curricolare_5:nomeBreve |               |
    | @alunno_3CAMB_2:cognome+ +@alunno_3CAMB_2:nome       | @materia_itp_2:nomeBreve+, +@materia_curricolare_5:nomeBreve         |               |

Scenario: visualizzazione riquadro inserimento debiti per la classe articolata
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe           |
    | @classe_3CAMB:id |
  Quando click su "Compila la comunicazione dei debiti" in sezione "#gs-main form > div:nth-child(3) > table > tbody > tr" che contiene "@alunno_3CAMB_1:cognome+ +@alunno_3CAMB_1:nome"
  Allora la sezione "#gs-main #gs-modal-remote h3.modal-title" contiene "@alunno_3CAMB_1:cognome+ +@alunno_3CAMB_1:nome"
  E la sezione "#gs-main #gs-modal-remote .modal-body div" contiene "Debiti formativi"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection" contiene "?@materia_curricolare_1:nome?@materia_curricolare_2:nome?@materia_curricolare_3:nome"

Scenario: visualizzazione riquadro inserimento carenze per la classe articolata
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe           |
    | @classe_3CAMB:id |
  Quando click su "Compila la comunicazione delle carenze" in sezione "#gs-main form > div:nth-child(4) > table > tbody > tr" che contiene "@alunno_3CAMB_2:cognome+ +@alunno_3CAMB_2:nome"
  Allora la sezione "#gs-main #gs-modal-remote h3.modal-title" contiene "@alunno_3CAMB_2:cognome+ +@alunno_3CAMB_2:nome"
  E la sezione "#gs-main #gs-modal-remote .modal-body div" contiene "Carenze"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection" contiene "?@materia_itp_2:nome?@materia_curricolare_5:nome"

Scenario: visualizzazione pagina con dati mancanti per la classe articolata
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe           |
    | @classe_3CAMB:id |
  Quando click su "Conferma"
  Allora la sezione "#gs-modal-error .alert-danger" contiene "Non è stata compilata la comunicazione"

Scenario: visualizzazione passo precedente per la classe articolata
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe           |
    | @classe_3CAMB:id |
  Quando click su "passo precedente"
  Allora la sezione "#gs-main h2" contiene "Passo 6"

Scenario: visualizzazione passo successivo per la classe articolata
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe           |
    | @classe_3CAMB:id |
  Quando click su "Compila la comunicazione dei debiti" in sezione "#gs-main form > div:nth-child(3) > table > tbody > tr" che contiene "@alunno_3CAMB_1:cognome+ +@alunno_3CAMB_1:nome"
  E click su "Conferma"
  E click su "Compila la comunicazione delle carenze" in sezione "#gs-main form > div:nth-child(4) > table > tbody > tr" che contiene "@alunno_3CAMB_1:cognome+ +@alunno_3CAMB_1:nome"
  E click su "Conferma"
  E click su "Compila la comunicazione delle carenze" in sezione "#gs-main form > div:nth-child(4) > table > tbody > tr" che contiene "@alunno_3CAMB_2:cognome+ +@alunno_3CAMB_2:nome"
  E click su "Conferma"
  E click su "Conferma"
  Allora vedi la pagina "coordinatore_scrutinio" con parametri:
    | classe           | stato |
    | @classe_3CAMB:id | 8     |
  Allora la sezione "#gs-main h2" contiene "Passo 8"

Scenario: memorizzazione dati e passo successivo per la classe articolata
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe           |
    | @classe_3CAMB:id |
  Quando click su "Compila la comunicazione dei debiti" in sezione "#gs-main form > div:nth-child(3) > table > tbody > tr" che contiene "@alunno_3CAMB_1:cognome+ +@alunno_3CAMB_1:nome"
  E click su "Conferma"
  E click su "Compila la comunicazione delle carenze" in sezione "#gs-main form > div:nth-child(4) > table > tbody > tr" che contiene "@alunno_3CAMB_1:cognome+ +@alunno_3CAMB_1:nome"
  E inserisci "" nel campo "carenze_lista_0_debito"
  E inserisci "" nel campo "carenze_lista_1_debito"
  E click su "Conferma"
  E click su "Compila la comunicazione delle carenze" in sezione "#gs-main form > div:nth-child(4) > table > tbody > tr" che contiene "@alunno_3CAMB_2:cognome+ +@alunno_3CAMB_2:nome"
  E inserisci "" nel campo "carenze_lista_0_debito"
  E inserisci "Qualcosa..." nel campo "carenze_lista_1_debito"
  E click su "Conferma"
  E click su "Conferma"
  E click su "passo precedente"
  Allora vedi la tabella "1" non ordinata:
    | Alunno                                         | Materie                                                                                                  | Comunicazione |
    | @alunno_3CAMB_1:cognome+ +@alunno_3CAMB_1:nome | @materia_curricolare_1:nomeBreve+, +@materia_curricolare_2:nomeBreve+, +@materia_curricolare_3:nomeBreve | COMPILATA     |
  E vedi la tabella "2" non ordinata:
    | Alunno                                         | Materie                                                              | Comunicazione                                      |
    | @alunno_3CAMB_1:cognome+ +@alunno_3CAMB_1:nome | @materia_curricolare_4:nomeBreve+, +@materia_curricolare_5:nomeBreve | COMPILATA NESSUNA                                  |
    | @alunno_3CAMB_2:cognome+ +@alunno_3CAMB_2:nome | @materia_itp_2:nomeBreve+, +@materia_curricolare_5:nomeBreve         | #str(COMPILATA)+ +@materia_curricolare_5:nomeBreve |

Scenario: memorizzazione dati e passo successivo con dettagli debiti per la classe articolata
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe           |
    | @classe_3CAMB:id |
  Quando click su "Compila la comunicazione dei debiti" in sezione "#gs-main form > div:nth-child(3) > table > tbody > tr" che contiene "@alunno_3CAMB_1:cognome+ +@alunno_3CAMB_1:nome"
  E selezioni opzione "Studio individuale" da lista "debiti_lista_0_recupero"
  E inserisci "Primo testo..." nel campo "debiti_lista_0_debito"
  E selezioni opzione "Studio individuale" da lista "debiti_lista_1_recupero"
  E inserisci "Secondo testo..." nel campo "debiti_lista_1_debito"
  E selezioni opzione "Studio individuale" da lista "debiti_lista_2_recupero"
  E inserisci "Terzo testo..." nel campo "debiti_lista_2_debito"
  E click su "Conferma"
  E click su "Compila la comunicazione delle carenze" in sezione "#gs-main form > div:nth-child(4) > table > tbody > tr" che contiene "@alunno_3CAMB_1:cognome+ +@alunno_3CAMB_1:nome"
  E click su "Conferma"
  E click su "Compila la comunicazione delle carenze" in sezione "#gs-main form > div:nth-child(4) > table > tbody > tr" che contiene "@alunno_3CAMB_2:cognome+ +@alunno_3CAMB_2:nome"
  E click su "Conferma"
  E click su "Conferma"
  E click su "passo precedente"
  E click su "Modifica la comunicazione dei debiti" in sezione "#gs-main form > div:nth-child(3) > table > tbody > tr" che contiene "@alunno_3CAMB_1:cognome+ +@alunno_3CAMB_1:nome"
  Allora opzione "Studio individuale" selezionata da lista "debiti_lista_0_recupero"
  E il campo "debiti_lista_0_debito" contiene "Primo testo..."
  E opzione "Studio individuale" selezionata da lista "debiti_lista_1_recupero"
  E il campo "debiti_lista_1_debito" contiene "Secondo testo..."
  E opzione "Studio individuale" selezionata da lista "debiti_lista_2_recupero"
  E il campo "debiti_lista_2_debito" contiene "Terzo testo..."

Scenario: memorizzazione dati e passo successivo con dettagli carenze per la classe articolata
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe           |
    | @classe_3CAMB:id |
  Quando click su "Compila la comunicazione dei debiti" in sezione "#gs-main form > div:nth-child(3) > table > tbody > tr" che contiene "@alunno_3CAMB_1:cognome+ +@alunno_3CAMB_1:nome"
  E click su "Conferma"
  E click su "Compila la comunicazione delle carenze" in sezione "#gs-main form > div:nth-child(4) > table > tbody > tr" che contiene "@alunno_3CAMB_1:cognome+ +@alunno_3CAMB_1:nome"
  E inserisci "" nel campo "carenze_lista_0_debito"
  E inserisci "" nel campo "carenze_lista_1_debito"
  E click su "Conferma"
  E click su "Compila la comunicazione delle carenze" in sezione "#gs-main form > div:nth-child(4) > table > tbody > tr" che contiene "@alunno_3CAMB_2:cognome+ +@alunno_3CAMB_2:nome"
  E inserisci "Qualcosa..." nel campo "carenze_lista_0_debito"
  E inserisci "" nel campo "carenze_lista_1_debito"
  E click su "Conferma"
  E click su "Conferma"
  E click su "passo precedente"
  E click su "Modifica la comunicazione delle carenze" in sezione "#gs-main form > div:nth-child(4) > table > tbody > tr" che contiene "@alunno_3CAMB_2:cognome+ +@alunno_3CAMB_2:nome"
  Allora il campo "carenze_lista_0_debito" contiene "Qualcosa..."
  E il campo "carenze_lista_1_debito" contiene ""
