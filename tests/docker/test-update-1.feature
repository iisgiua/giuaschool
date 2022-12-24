# language: it

@noReset
Funzionalità: Controlla il passo iniziale della procedura di aggiornamento del registro
  Per controllare il passo iniziale della procedura di aggiornamento del registro
  Come utente pubblico
  Bisogna eseguire il passo 1 della procedura di aggiornamento e controllarne l'esito


################################################################################
# Bisogna eseguire il passo 1 della procedura di aggiornamento e controllarne l'esito

Scenario: esegue il passo 1 della procedura di aggiornamento
  Quando vai alla url "/install/update.php?step=1&token=test"
  Allora la sezione "main .alert-success" contiene "/correttamente/"
