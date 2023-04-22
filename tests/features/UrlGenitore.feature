# language: it

Funzionalità: Controllo sulla visualizzazione delle pagine del registro
  Per controllare la visualizzazione delle pagine del registro
  Come utente genitore
  Bisogna controllare che le pagine siano correttamente visualizzate


################################################################################
# Bisogna controllare che le pagine siano correttamente visualizzate

Schema dello scenario: Controlla la visualizzazione delle pagine per gli utenti genitori
  Dato login utente con ruolo esatto "Genitore"
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $cl1 | 1    | A       |
  E istanze di tipo "Alunno":
    | id  | classe | abilitato |
    | $a1 | $cl1   | si        |
  E modifica istanze di tipo "Genitore"
    | username         | #alunno |
    | #logged.username | $a1     |
  Quando vai alla pagina "<route>"
  Allora vedi pagina "<route>"
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
    | circolari_genitori         |
    | genitori_avvisi            |
    | documenti_bacheca          |
    | genitori_eventi            |
    | utenti_profilo             |
    | utenti_email               |
    | utenti_password            |
