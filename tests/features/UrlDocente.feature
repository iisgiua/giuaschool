# language: it

Funzionalità: Controllo sulla visualizzazione delle pagine del registro
  Per controllare la visualizzazione delle pagine del registro
  Come utente docente
  Bisogna controllare che le pagine siano correttamente visualizzate
  Utilizzando "_testFixtures.yml"


################################################################################
# Bisogna controllare che le pagine siano correttamente visualizzate

Schema dello scenario: Controlla la visualizzazione delle pagine per gli utenti docenti
  Dato login utente con ruolo esatto "Docente"
  Quando vai alla pagina "<route>"
  Allora vedi la pagina "<route>"
  Esempi:
    | route                      |
    | lezioni_classe             |
    | lezioni_registro_firme     |
    | lezioni_assenze_quadro     |
    | lezioni_note               |
    | lezioni_voti_quadro        |
    | lezioni_argomenti          |
    | lezioni_osservazioni       |
    | lezioni_scrutinio_proposte |
    | documenti_piani            |
    | documenti_programmi        |
    | documenti_relazioni        |
    | documenti_maggio           |
    | colloqui_richieste         |
    | colloqui_gestione          |
    | colloqui_storico           |
    | colloqui_edit              |
    | colloqui_create            |
    | circolari_docenti          |
    | bacheca_avvisi             |
    | documenti_bacheca          |
    | utenti_profilo             |
    | utenti_email               |
    | utenti_password            |
