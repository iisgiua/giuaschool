# language: it

@noReset
Funzionalità: Controlla i passi successivi della procedura di aggiornamento del registro
  Per controllare i passi successivi della procedura di aggiornamento del registro
  Come utente pubblico
  Bisogna eseguire i passi successivi della procedura di aggiornamento e controllarne l'esito


################################################################################
# Bisogna eseguire i passi successivi della procedura di aggiornamento e controllarne l'esito

Schema dello scenario: esegue i passi successivi della procedura di aggiornamento
  Quando vai alla url "/install/update.php?step=<passo>&token=test"
  Allora vedi la url "/install/update.php"
  Allora la sezione "main .alert-success" contiene "<messaggio>"
  Esempi:
    | passo | messaggio       |
    | 2     | /correttamente/ |
    | 3     | /correttamente/ |
    | 4     | /correttamente/ |
    | 5     | /correttamente/ |
    | 6     | /con successo/  |
