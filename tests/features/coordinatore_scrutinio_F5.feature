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

Schema dello scenario: visualizzazione pagina passo 5 per classi non quinte
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe   |
    | <classe> |
  Allora la sezione "#gs-main h2" contiene "Requisiti di ammissione all'esame"
  E la sezione "#gs-main .alert-success" contiene "Vai al passo successivo"
  Esempi:
    | classe        |
    | @classe_1A:id |
    | @classe_2A:id |
    | @classe_3A:id |
    | @classe_4A:id |

Scenario: visualizzazione pagina passo 5 per classi quinte
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_5A:id |
  Allora la sezione "#gs-main h2" contiene "Requisiti di ammissione all'esame"
  E la sezione "#gs-main form label" contiene "Alunni che hanno i requisiti di ammissione"
  E vedi opzione "T" in pulsanti radio "scrutinio[requisiti]"

Scenario: visualizzazione passo precedente
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "passo precedente"
  Allora la sezione "#gs-main h2" contiene "Voto di condotta"

Scenario: visualizzazione passo successivo
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "Conferma"
  Allora vedi la pagina "coordinatore_scrutinio" con parametri:
    | classe        | stato |
    | @classe_1A:id | 6     |
  Allora la sezione "#gs-main h2" contiene "Esito dello scrutinio"


################################################################################
# Bisogna controllare visualizzazione con la classe articolata

Scenario: visualizzazione pagina passo 5 per classe articolata
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe           |
    | @classe_3CAMB:id |
  Allora la sezione "#gs-main h2" contiene "Requisiti di ammissione all'esame"
  E la sezione "#gs-main .alert-success" contiene "Vai al passo successivo"

Scenario: visualizzazione passo successivo
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe           |
    | @classe_3CAMB:id |
  Quando click su "Conferma"
  Allora vedi la pagina "coordinatore_scrutinio" con parametri:
    | classe           | stato |
    | @classe_3CAMB:id | 6     |
  Allora la sezione "#gs-main h2" contiene "Esito dello scrutinio"
