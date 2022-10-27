# language: it

Funzionalità: Controllo sulla visualizzazione delle pagine del registro
  Per controllare la visualizzazione delle pagine del registro
  Come utente del ruolo previsto
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

Schema dello scenario: Controlla la visualizzazione delle pagine per gli utenti ATA
  Dato login utente con ruolo esatto "Ata"
  Quando vai alla pagina "<route>"
  Allora vedi pagina "<route>"
  Esempi:
    | route                      |
    | circolari_ata              |
    | bacheca_avvisi_ata         |
    | documenti_bacheca          |
    | utenti_profilo             |
    | utenti_email               |
    | utenti_password            |

Schema dello scenario: Controlla la visualizzazione delle pagine per gli utenti docenti
  Dato login utente con ruolo esatto "Docente"
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

Schema dello scenario: Controlla la visualizzazione delle pagine per gli utenti staff
  Dato login utente con ruolo esatto "Staff"
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
