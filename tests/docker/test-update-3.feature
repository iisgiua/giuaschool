# language: it

@noReset
Funzionalità: Smoke test
  Per eseguire un test delle funzionalità generali
  Come utente pubblico o dei ruoli previsti
  Bisogna caricare le principali pagine pubbliche
  Bisogna effettuare il login per tutti i ruoli previsti
  Bisogna effettuare effettuare un download
  Bisogna effettuare effettuare un upload
  Bisogna effettuare effettuare una conversione in PDF
  Bisogna inserire un voto


################################################################################
# Bisogna caricare le principali pagine pubbliche

Schema dello scenario: carica una pagina pubblica
  Quando vai alla pagina "<pagina>"
  Allora vedi la pagina "<pagina>"
  #-- Allora la sezione "main .alert-success" contiene "<messaggio>"
  Esempi:
    | pagina         | titolo       |
    | info_privacy   | /correttamente/ |
    | info_credits   | /correttamente/ |
    | login_recovery | /correttamente/ |
