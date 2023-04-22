# language: it

Funzionalità: Controllo sulla visualizzazione delle pagine del registro
  Per controllare la visualizzazione delle pagine del registro
  Come utente alunno
  Bisogna controllare che le pagine siano correttamente visualizzate


################################################################################
# Bisogna controllare che le pagine siano correttamente visualizzate

Schema dello scenario: Controlla la visualizzazione delle pagine per gli utenti alunni
  Dato login utente con ruolo esatto "Alunno"
  E istanze di tipo "Genitore":
    | id  | abilitato | alunno  |
    | $g1 | si        | #logged |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $cl1 | 1    | A       |
  E modifica istanze di tipo "Alunno"
    | username         | #classe |
    | #logged.username | $cl1    |
  Quando vai alla pagina "<route>"
  Allora vedi pagina "<route>"
  Esempi:
    | route                      |
    | genitori_lezioni           |
    | genitori_argomenti         |
    | genitori_voti              |
    | genitori_assenze           |
    | genitori_note              |
    | genitori_deroghe           |
    | genitori_pagelle           |
    | circolari_genitori         |
    | genitori_avvisi            |
    | documenti_bacheca          |
    | genitori_eventi            |
    | utenti_profilo             |
    | utenti_email               |
    | utenti_password            |
