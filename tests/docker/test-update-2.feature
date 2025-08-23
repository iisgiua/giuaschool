# language: it

@noReset
Funzionalità: Controlla i passi successivi della procedura di aggiornamento del registro
  Per controllare i passi successivi della procedura di aggiornamento del registro
  Come utente pubblico
  Bisogna eseguire i passi successivi della procedura di aggiornamento e controllarne l'esito


################################################################################
# Bisogna eseguire i passi successivi della procedura di aggiornamento e controllarne l'esito

Schema dello scenario: esegue i passi successivi della procedura di aggiornamento
  Quando vai alla url "/install/update.php?token=test&step=<passo>"
  Allora vedi la url "/install/update.php?token=test&step=<passo>"
  E la sezione "main .alert" contiene "<messaggio>"
  Esempi:
    | passo | messaggio                                       |
    | 2     | /correttamente/                                 |
    | 3     | /Il sistema soddisfa tutti i requisiti tecnici/ |
    | 4     | /correttamente/                                 |
    | 5     | /correttamente/                                 |

Scenario: esegue passo di migrazione dati finché termina
  Quando vai alla url "/install/update.php?token=test&step=6" e premi "CONTINUA" finché "main .alert" contiene "/correttamente/"
  Allora vedi la url "/install/update.php?token=test&step=7"
  E la sezione "main .alert" contiene "/correttamente/"

Scenario: esegue passo il passo finale della procedura di aggiornamento
  Quando vai alla url "/install/update.php?token=test&step=8"
  Allora vedi la url "/install/update.php?token=test&step=8"
  E la sezione "main .alert" contiene "/successo/"
