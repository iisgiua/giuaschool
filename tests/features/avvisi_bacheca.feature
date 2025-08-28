# language: it

Funzionalità: Visualizzazione bacheca degli avvisi indirizzati all'utente
  Per visualizzare la lista dei documenti indirizzati all'utente
  Come utente
  Bisogna controllare visualizzazione della pagina
  Bisogna controllare memorizzazione dati di sessione
  Bisogna controllare accesso a pagina
  Utilizzando "_avvisiFixtures.yml"


################################################################################
# Bisogna controllare visualizzazione della pagina

Scenario: Visualizzazione standard per docenti
  Dato login utente "@docente_curricolare_1:username"
  Quando vai alla pagina "avvisi_bacheca"
  Allora vedi la tabella non ordinata:
    | stato      | data                                | sede                             | oggetto                         | azione     |
    | LETTO      | #dat(@avviso_U:data)                | @avviso_U:sedi[0]                | @avviso_U:titolo                | Visualizza |
    | LETTO      | #dat(@avviso_E:data)                | @avviso_E:sedi[0]                | @avviso_E:titolo                | Visualizza |
    | DA LEGGERE | #dat(@avviso_A:data)                | @avviso_A:sedi[0]                | @avviso_A:titolo                | Visualizza |
    | DA LEGGERE | #dat(@avviso_C:data)                | @avviso_C:sedi[0]                | @avviso_C:titolo                | Visualizza |
    | DA LEGGERE | #dat(@avviso_non_modificabile:data) | @avviso_non_modificabile:sedi[0] | @avviso_non_modificabile:titolo | Visualizza |

Scenario: Visualizzazione standard per ATA
  Dato login utente "@ata_A:username"
  Quando vai alla pagina "avvisi_bacheca"
  Allora vedi la tabella non ordinata:
    | stato      | data                 | sede              | oggetto          | azione     |
    | DA LEGGERE | #dat(@avviso_U:data) | @avviso_U:sedi[0] | @avviso_U:titolo | Visualizza |
    | DA LEGGERE | #dat(@avviso_E:data) | @avviso_E:sedi[0] | @avviso_E:titolo | Visualizza |
    | LETTO      | #dat(@avviso_A:data) | @avviso_A:sedi[0] | @avviso_A:titolo | Visualizza |

Scenario: Visualizzazione standard per genitori
  Dato login utente "@genitore1_2A_1:username"
  Quando vai alla pagina "avvisi_bacheca"
  Allora vedi la tabella non ordinata:
    | stato      | data                 | sede              | oggetto          | azione     |
    | LETTO      | #dat(@avviso_U:data) | @avviso_U:sedi[0] | @avviso_U:titolo | Visualizza |
    | DA LEGGERE | #dat(@avviso_I:data) | @avviso_I:sedi[0] | @avviso_I:titolo | Visualizza |

Scenario: Visualizzazione standard per alunni
  Dato login utente "@alunno_2A_1:username"
  Quando vai alla pagina "avvisi_bacheca"
  Allora vedi la tabella non ordinata:
    | stato      | data                 | sede              | oggetto          | azione     |
    | LETTO      | #dat(@avviso_U:data) | @avviso_U:sedi[0] | @avviso_U:titolo | Visualizza |
    | DA LEGGERE | #dat(@avviso_O:data) | @avviso_O:sedi[0] | @avviso_O:titolo | Visualizza |

Scenario: Visualizzazione con filtro di visualizzazione
  Dato login utente "@docente_curricolare_1:username"
  Quando vai alla pagina "avvisi_bacheca"
  E selezioni opzione "D" da lista "avviso_filtro_visualizza"
  Allora vedi la tabella non ordinata:
    | stato      | data                                | sede                             | oggetto                         | azione     |
    | DA LEGGERE | #dat(@avviso_A:data)                | @avviso_A:sedi[0]                | @avviso_A:titolo                | Visualizza |
    | DA LEGGERE | #dat(@avviso_C:data)                | @avviso_C:sedi[0]                | @avviso_C:titolo                | Visualizza |
    | DA LEGGERE | #dat(@avviso_non_modificabile:data) | @avviso_non_modificabile:sedi[0] | @avviso_non_modificabile:titolo | Visualizza |

Scenario: Visualizzazione con filtro per mese
  Dato login utente "@docente_curricolare_3:username"
  Quando vai alla pagina "avvisi_bacheca"
  E selezioni opzione "6" da lista "avviso_filtro_mese"
  E aspetti chiamata AJAX sia completata
  Allora vedi la pagina "avvisi_bacheca"
  E vedi nella tabella i dati:
    | stato      | data                                | sede                             | oggetto                         | azione     |
    | DA LEGGERE | #dat(@avviso_non_modificabile:data) | @avviso_non_modificabile:sedi[0] | @avviso_non_modificabile:titolo | Visualizza |

Scenario: Visualizzazione con filtro per oggetto
  Dato login utente "@ata_A:username"
  Quando vai alla pagina "avvisi_bacheca"
  E inserisci "@avviso_A:titolo" nel campo "oggetto"
  E premi pulsante "Filtra"
  Allora vedi la pagina "avvisi_bacheca"
  E vedi la tabella non ordinata:
    | stato | data                 | sede              | oggetto          | azione     |
    | LETTO | #dat(@avviso_A:data) | @avviso_A:sedi[0] | @avviso_A:titolo | Visualizza |

Scenario: Visualizzazione con filtri combinati
  Dato login utente "@docente_curricolare_3:username"
  Quando vai alla pagina "avvisi_bacheca"
  E selezioni opzione "D" da lista "avviso_filtro_visualizza"
  E selezioni opzione "6" da lista "avviso_filtro_mese"
  E inserisci "@avviso_non_modificabile:titolo" nel campo "oggetto"
  E premi pulsante "Filtra"
  Allora vedi la pagina "avvisi_bacheca"
  E vedi la tabella non ordinata:
    | stato      | data                                | sede                             | oggetto                         | azione     |
    | DA LEGGERE | #dat(@avviso_non_modificabile:data) | @avviso_non_modificabile:sedi[0] | @avviso_non_modificabile:titolo | Visualizza |

Scenario: Visualizzazione lista vuota
  Dato login utente "@genitore1_2A_1:username"
  Quando vai alla pagina "avvisi_bacheca"
  E selezioni opzione "D" da lista "avviso_filtro_visualizza"
  E inserisci "---PROVA---" nel campo "oggetto"
  E premi pulsante "Filtra"
  Allora vedi la pagina "avvisi_bacheca"
  E la sezione "#gs-main .alert" contiene "Nessun avviso"
  E non vedi la tabella:
    | stato | data | sede | oggetto | azione |


################################################################################
# Bisogna controllare memorizzazione dati di sessione

Scenario: Navigazione con filtro persistente
  Dato login utente "@docente_curricolare_3:username"
  Quando vai alla pagina "avvisi_bacheca"
  E selezioni opzione "D" da lista "avviso_filtro_visualizza"
  E selezioni opzione "6" da lista "avviso_filtro_mese"
  E inserisci "@avviso_non_modificabile:titolo" nel campo "oggetto"
  E premi pulsante "Filtra"
  E vai alla pagina "login_home"
  E vai alla pagina "avvisi_bacheca"
  Allora vedi la pagina "avvisi_bacheca"
  E vedi la tabella non ordinata:
    | stato      | data                                | sede                             | oggetto                         | azione     |
    | DA LEGGERE | #dat(@avviso_non_modificabile:data) | @avviso_non_modificabile:sedi[0] | @avviso_non_modificabile:titolo | Visualizza |
  E vedi opzione "D" in lista "avviso_filtro_visualizza"
  E vedi opzione "6" in lista "avviso_filtro_mese"
  E il campo "oggetto" contiene "@avviso_non_modificabile:titolo"


################################################################################
# Bisogna controllare accesso a pagina

Scenario: accesso pagina senza utente
  Quando vai alla pagina "avvisi_bacheca"
  Allora vedi la pagina "login_form"
