# language: it

Funzionalità: Controllo sulla visualizzazione delle pagine del registro
  Per controllare la visualizzazione delle pagine del registro
  Come utente preside
  Bisogna controllare che le pagine siano correttamente visualizzate


################################################################################
# Bisogna controllare che le pagine siano correttamente visualizzate

Schema dello scenario: Controlla la visualizzazione delle pagine per l'utente preside
  Dato login utente con ruolo esatto "Preside"
  Quando vai alla pagina "<route>"
  Allora vedi pagina "<route>"
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
    | circolari_docenti          |
    | bacheca_avvisi             |
    | documenti_bacheca          |
    | staff_avvisi               |
    | staff_avvisi_attivita      |
    | staff_avvisi_individuali   |
    | circolari_gestione         |
    | staff_studenti_autorizza   |
    | staff_studenti_assenze     |
    | staff_studenti_deroghe     |
    | staff_studenti_situazione  |
    | staff_studenti_statistiche |
    | documenti_alunni           |
    | colloqui_cerca             |
    | staff_docenti_statistiche  |
    | documenti_docenti          |
    | staff_password             |
    | utenti_profilo             |
    | utenti_email               |
    | utenti_password            |
