# language: it

Funzionalità: primo passo dello scrutinio del secondo periodo
  Per svolgere il primo passo dello scrutinio del secondo periodo
  Come utente staff
  Bisogna controllare visualizzazione della pagina
  Bisogna controllare visualizzazione con la classe articolata
  Utilizzando "_scrutinioS1Fixtures.yml"


Contesto: login utente staff
	Dato login utente con ruolo esatto "Staff"


################################################################################
# Bisogna controllare visualizzazione della pagina

Scenario: visualizzazione pagina passo 1
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Allora la sezione "#gs-main h2" contiene "Passo 1"
  E vedi la tabella non ordinata:
    | Docente                                                      |	Materia                                                       |	Presenza         |
    | @docente_curricolare_1:cognome+ +@docente_curricolare_1:nome | ?@materia_curricolare_1:nomeBreve?@materia_EDCIVICA:nomeBreve | Presente Assente |
    | @docente_curricolare_2:cognome+ +@docente_curricolare_2:nome | ?@materia_curricolare_2:nomeBreve?@materia_EDCIVICA:nomeBreve | Presente Assente |
    | @docente_curricolare_3:cognome+ +@docente_curricolare_3:nome | ?@materia_curricolare_3:nomeBreve?@materia_EDCIVICA:nomeBreve | Presente Assente |
    | @docente_curricolare_4:cognome+ +@docente_curricolare_4:nome | ?@materia_curricolare_4:nomeBreve?@materia_EDCIVICA:nomeBreve | Presente Assente |
    | @docente_curricolare_5:cognome+ +@docente_curricolare_5:nome | ?@materia_curricolare_5:nomeBreve?@materia_EDCIVICA:nomeBreve | Presente Assente |
    | @docente_religione_1:cognome+ +@docente_religione_1:nome     | ?@materia_RELIGIONE:nomeBreve?@materia_EDCIVICA:nomeBreve     | Presente Assente |
    | @docente_itp_1:cognome+ +@docente_itp_1:nome                 | ?@materia_itp_1:nomeBreve?@materia_EDCIVICA:nomeBreve         | Presente Assente |
    | @docente_itp_2:cognome+ +@docente_itp_2:nome                 | ?@materia_itp_1:nomeBreve?@materia_EDCIVICA:nomeBreve         | Presente Assente |
    | @docente_sostegno_1:cognome+ +@docente_sostegno_1:nome       | ?@materia_SOSTEGNO:nomeBreve?@materia_EDCIVICA:nomeBreve      | Presente Assente |
    | @docente_sostegno_2:cognome+ +@docente_sostegno_2:nome       | ?@materia_SOSTEGNO:nomeBreve?@materia_EDCIVICA:nomeBreve      | Presente Assente |
    | @docente_nocattedra_1:cognome+ +@docente_nocattedra_1:nome   | ?@materia_RELIGIONE:nomeBreve?@materia_EDCIVICA:nomeBreve     | Presente Assente |

Scenario: visualizzazione pagina con dati mancanti
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando inserisci "" nel campo "scrutinio_data"
  E inserisci "" nel campo "scrutinio_inizio"
  E selezioni opzione "Assente" da pulsanti radio "scrutinio_lista_{{@docente_curricolare_1:id}}_presenza"
  E click su "Conferma"
  Allora la sezione "#gs-modal-error .alert-danger" contiene "/Data dello scrutinio.*Ora di inizio.*segretario.*sostituto/ui"

Scenario: visualizzazione passo precedente
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "Annulla apertura scrutinio"
  Allora la sezione "#gs-main h2" contiene "Proposte di voto"

Scenario: visualizzazione passo successivo
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando selezioni opzione "@docente_curricolare_1:id" da lista "scrutinio_segretario"
  E click su "Conferma"
  Allora vedi la pagina "coordinatore_scrutinio" con parametri:
    | classe        | stato |
    | @classe_1A:id | 2     |
  E la sezione "#gs-main h2" contiene "Passo 2"

Scenario: memorizzazione dati e passo successivo
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando inserisci "01/01/2020" nel campo "scrutinio_data"
  E inserisci "10:30" nel campo "scrutinio_inizio"
  E selezioni opzione "Assente" da pulsanti radio "scrutinio_lista_{{@docente_curricolare_1:id}}_presenza"
  E selezioni opzione "F" da lista "scrutinio_lista_{{@docente_curricolare_1:id}}_sessoSostituto"
  E inserisci "Bianchi Maria" nel campo "scrutinio_lista_{{@docente_curricolare_1:id}}_sostituto"
  E inserisci "999" nel campo "scrutinio_lista_{{@docente_curricolare_1:id}}_surrogaProtocollo"
  E inserisci "31/12/2019" nel campo "scrutinio_lista_{{@docente_curricolare_1:id}}_surrogaData"
  E selezioni opzione "Il docente" da pulsanti radio "scrutinio_presiede_ds"
  E selezioni opzione "@docente_curricolare_1:id" da lista "scrutinio_presiede_docente"
  E selezioni opzione "@docente_curricolare_2:id" da lista "scrutinio_segretario"
  E click su "Conferma"
  E click su "passo precedente"
  Allora la sezione "#gs-main h2" contiene "Passo 1"
  E il campo "scrutinio_data" contiene "01/01/2020"
  E il campo "scrutinio_inizio" contiene "10:30"
  E il campo "scrutinio[lista][{{@docente_curricolare_1:id}}][presenza]" contiene "0"
  E il campo "scrutinio_lista_{{@docente_curricolare_1:id}}_sessoSostituto" contiene "F"
  E il campo "scrutinio_lista_{{@docente_curricolare_1:id}}_sostituto" contiene "Bianchi Maria"
  E il campo "scrutinio_lista_{{@docente_curricolare_1:id}}_surrogaProtocollo" contiene "999"
  E il campo "scrutinio_lista_{{@docente_curricolare_1:id}}_surrogaData" contiene "31/12/2019"
  E il campo "scrutinio[presiede_ds]" contiene "0"
  E il campo "scrutinio_presiede_docente" contiene "@docente_curricolare_1:id"
  E il campo "scrutinio_segretario" contiene "@docente_curricolare_2:id"


################################################################################
# Bisogna controllare visualizzazione con la classe articolata

Scenario: visualizzazione classe articolata
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe           |
    | @classe_3CAMB:id |
  Allora la sezione "#gs-main h2" contiene "Passo 1"
  E vedi la tabella non ordinata:
    | Docente                                                      |	Materia                                                       |	Presenza         |
    | @docente_curricolare_1:cognome+ +@docente_curricolare_1:nome | ?@materia_curricolare_1:nomeBreve?@materia_EDCIVICA:nomeBreve | Presente Assente |
    | @docente_curricolare_2:cognome+ +@docente_curricolare_2:nome | ?@materia_curricolare_2:nomeBreve?@materia_EDCIVICA:nomeBreve | Presente Assente |
    | @docente_curricolare_3:cognome+ +@docente_curricolare_3:nome | ?@materia_curricolare_3:nomeBreve?@materia_EDCIVICA:nomeBreve | Presente Assente |
    | @docente_curricolare_4:cognome+ +@docente_curricolare_4:nome | ?@materia_curricolare_4:nomeBreve?@materia_EDCIVICA:nomeBreve | Presente Assente |
    | @docente_curricolare_5:cognome+ +@docente_curricolare_5:nome | ?@materia_curricolare_5:nomeBreve?@materia_EDCIVICA:nomeBreve | Presente Assente |
    | @docente_religione_1:cognome+ +@docente_religione_1:nome     | ?@materia_RELIGIONE:nomeBreve?@materia_EDCIVICA:nomeBreve     | Presente Assente |
    | @docente_itp_2:cognome+ +@docente_itp_2:nome                 | ?@materia_itp_2:nomeBreve?@materia_EDCIVICA:nomeBreve         | Presente Assente |
