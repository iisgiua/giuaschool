# language: it

Funzionalità: Controllo sulla visualizzazione delle pagine del registro
  Per controllare la visualizzazione delle pagine del registro
  Come utente del ruolo previsto
  Bisogna controllare che le pagine siano correttamente visualizzate


################################################################################
# Bisogna controllare che le pagine siano correttamente visualizzate

Schema dello scenario: Controlla la visualizzazione delle pagine per gli utenti definiti
  Dato login utente con ruolo esatto "<ruolo>"
  Quando vai alla pagina "<route>"
  Allora vedi pagina "<route>"
  Esempi:
    | ruolo    | route                      |
    | Genitore | genitori_lezioni           |
    | Genitore | genitori_argomenti         |
    | Genitore | genitori_voti              |
    | Genitore | genitori_assenze           |
    | Genitore | genitori_note              |
    | Genitore | genitori_osservazioni      |
    | Genitore | genitori_deroghe           |
    | Genitore | genitori_pagelle           |
    | Genitore | colloqui_genitori          |
    | Genitore | circolari_genitori         |
    | Genitore | genitori_avvisi            |
    | Genitore | documenti_bacheca          |
    | Genitore | genitori_eventi            |
    | Genitore | utenti_profilo             |
    | Genitore | utenti_email               |
    | Genitore | utenti_password            |
    | Alunno   | genitori_lezioni           |
    | Alunno   | genitori_argomenti         |
    | Alunno   | genitori_voti              |
    | Alunno   | genitori_assenze           |
    | Alunno   | genitori_note              |
    | Alunno   | genitori_deroghe           |
    | Alunno   | genitori_pagelle           |
    | Alunno   | circolari_genitori         |
    | Alunno   | genitori_avvisi            |
    | Alunno   | documenti_bacheca          |
    | Alunno   | genitori_eventi            |
    | Alunno   | utenti_profilo             |
    | Alunno   | utenti_email               |
    | Alunno   | utenti_password            |
    | Ata      | circolari_ata              |
    | Ata      | bacheca_avvisi_ata         |
    | Ata      | documenti_bacheca          |
    | Ata      | utenti_profilo             |
    | Ata      | utenti_email               |
    | Ata      | utenti_password            |
    | Docente  | lezioni_classe             |
    | Docente  | lezioni_registro_firme     |
    | Docente  | lezioni_assenze_quadro     |
    | Docente  | lezioni_note               |
    | Docente  | lezioni_voti_quadro        |
    | Docente  | lezioni_argomenti          |
    | Docente  | lezioni_osservazioni       |
    | Docente  | lezioni_scrutinio_proposte |
    | Docente  | documenti_piani            |
    | Docente  | documenti_programmi        |
    | Docente  | documenti_relazioni        |
    | Docente  | documenti_maggio           |
    | Docente  | colloqui_richieste         |
    | Docente  | colloqui_gestione          |
    | Docente  | colloqui_storico           |
    | Docente  | colloqui_edit              |
    | Docente  | colloqui_create            |
    | Docente  | circolari_docenti          |
    | Docente  | bacheca_avvisi             |
    | Docente  | documenti_bacheca          |
    | Docente  | utenti_profilo             |
    | Docente  | utenti_email               |
    | Docente  | utenti_password            |
    | Staff    | lezioni_classe             |
    | Staff    | lezioni_registro_firme     |
    | Staff    | lezioni_assenze_quadro     |
    | Staff    | lezioni_note               |
    | Staff    | lezioni_voti_quadro        |
    | Staff    | lezioni_argomenti          |
    | Staff    | lezioni_osservazioni       |
    | Staff    | lezioni_scrutinio_proposte |
    | Staff    | documenti_piani            |
    | Staff    | documenti_programmi        |
    | Staff    | documenti_relazioni        |
    | Staff    | documenti_maggio           |
    | Staff    | circolari_docenti          |
    | Staff    | bacheca_avvisi             |
    | Staff    | documenti_bacheca          |
    | Staff    | staff_avvisi               |
    | Staff    | staff_avvisi_attivita      |
    | Staff    | staff_avvisi_individuali   |
    | Staff    | circolari_gestione         |
    | Staff    | staff_studenti_autorizza   |
    | Staff    | staff_studenti_assenze     |
    | Staff    | staff_studenti_deroghe     |
    | Staff    | staff_studenti_situazione  |
    | Staff    | staff_studenti_statistiche |
    | Staff    | documenti_alunni           |
    | Staff    | colloqui_cerca             |
    | Staff    | staff_docenti_statistiche  |
    | Staff    | documenti_docenti          |
    | Staff    | staff_password             |
    | Staff    | utenti_profilo             |
    | Staff    | utenti_email               |
    | Staff    | utenti_password            |
    | Preside  | lezioni_classe             |
    | Preside  | lezioni_registro_firme     |
    | Preside  | lezioni_assenze_quadro     |
    | Preside  | lezioni_note               |
    | Preside  | lezioni_voti_quadro        |
    | Preside  | lezioni_argomenti          |
    | Preside  | lezioni_osservazioni       |
    | Preside  | lezioni_scrutinio_proposte |
    | Preside  | documenti_piani            |
    | Preside  | documenti_programmi        |
    | Preside  | documenti_relazioni        |
    | Preside  | documenti_maggio           |
    | Preside  | circolari_docenti          |
    | Preside  | bacheca_avvisi             |
    | Preside  | documenti_bacheca          |
    | Preside  | staff_avvisi               |
    | Preside  | staff_avvisi_attivita      |
    | Preside  | staff_avvisi_individuali   |
    | Preside  | circolari_gestione         |
    | Preside  | staff_studenti_autorizza   |
    | Preside  | staff_studenti_assenze     |
    | Preside  | staff_studenti_deroghe     |
    | Preside  | staff_studenti_situazione  |
    | Preside  | staff_studenti_statistiche |
    | Preside  | documenti_alunni           |
    | Preside  | colloqui_cerca             |
    | Preside  | staff_docenti_statistiche  |
    | Preside  | documenti_docenti          |
    | Preside  | staff_password             |
    | Preside  | utenti_profilo             |
    | Preside  | utenti_email               |
    | Preside  | utenti_password            |
