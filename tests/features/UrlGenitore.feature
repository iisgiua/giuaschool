# language: it

Funzionalità: Controllo sulla visualizzazione delle pagine del registro
  Per controllare la visualizzazione delle pagine del registro
  Come utente genitore
  Bisogna controllare che le pagine siano correttamente visualizzate
  Utilizzando "_testFixtures.yml"


################################################################################
# Bisogna controllare che le pagine siano correttamente visualizzate

Schema dello scenario: Controlla la visualizzazione delle pagine per gli utenti genitori
  Dato login utente con ruolo esatto "Genitore"
  Quando vai alla pagina "<route>"
  Allora vedi la pagina "<route>"
  Esempi:
    | route                      |
    | genitori_lezioni           |
    | genitori_argomenti         |
    | genitori_voti              |
    | genitori_assenze           |
    | genitori_note              |
    | genitori_osservazioni      |
    | genitori_deroghe           |
    | genitori_pagelle           |
    | colloqui_genitori          |
    | circolari_bacheca          |
    | avvisi_bacheca             |
    | documenti_bacheca          |
    | avvisi_agenda              |
    | utenti_profilo             |
    | utenti_email               |
    | utenti_password            |
