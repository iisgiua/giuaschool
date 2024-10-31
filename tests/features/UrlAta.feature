# language: it

Funzionalità: Controllo sulla visualizzazione delle pagine del registro
  Per controllare la visualizzazione delle pagine del registro
  Come utente ATA
  Bisogna controllare che le pagine siano correttamente visualizzate
  Utilizzando "_testFixtures.yml"


################################################################################
# Bisogna controllare che le pagine siano correttamente visualizzate

Schema dello scenario: Controlla la visualizzazione delle pagine per gli utenti ATA
  Dato login utente con ruolo esatto "Ata"
  Quando vai alla pagina "<route>"
  Allora vedi la pagina "<route>"
  Esempi:
    | route                      |
    | circolari_ata              |
    | bacheca_avvisi_ata         |
    | documenti_bacheca          |
    | utenti_profilo             |
    | utenti_email               |
    | utenti_password            |
