# language: it

Funzionalità: Gestione degli avvisi
  Per gestire gli avvisi in modo da poter inserire, modificare, cancellare le comunicazioni
  Come utente Staff
  Bisogna controllare visualizzazione della pagina
  Bisogna controllare memorizzazione dati di sessione
  Bisogna controllare accesso a pagina
  Utilizzando "_avvisiFixtures.yml"


Contesto: login docente Staff
  Dato login utente "@staff_1:username"


################################################################################
# Bisogna controllare visualizzazione della pagina

Scenario: Visualizzazione standard
  Data pagina attiva "avvisi_gestione"
  Quando selezioni opzione "" da lista "avviso_filtro_tipo"
  Allora vedi la pagina "avvisi_gestione"
  E pulsante "Generico" attivo
  E pulsante "Ingresso" attivo
  E pulsante "Uscita" attivo
  E pulsante "Attività" attivo
  E pulsante "Personale" attivo
  E vedi la tabella non ordinata:
    | Autore                                              | Tipo                    | Data                                | Oggetto                         | Sede                             | Destinatari                                        | Azione                       |
    | @avviso_C:autore.nome,autore.cognome                | Avviso generico         | #dat(@avviso_C:data)                | @avviso_C:titolo                | @avviso_C:sedi[0]                | /DOCENTI.*GENITORI.*ALUNNI.*CLASSI: 3A/            | Visualizza Modifica Cancella |
    | @avviso_C_allegato:autore.nome,autore.cognome       | Avviso generico         | #dat(@avviso_C_allegato:data)       | @avviso_C_allegato:titolo       | @avviso_C_allegato:sedi[0]       | ALUNNI                                             | Visualizza Modifica Cancella |
    | @avviso_non_modificabile:autore.nome,autore.cognome | Avviso generico         | #dat(@avviso_non_modificabile:data) | @avviso_non_modificabile:titolo | @avviso_non_modificabile:sedi[0] | DOCENTI                                            | Visualizza                   |
    | @avviso_U:autore.nome,autore.cognome                | Uscita anticipata       | #dat(@avviso_U:data)                | @avviso_U:titolo                | @avviso_U:sedi[0]                | /DOCENTI.*GENITORI.*ALUNNI.*CLASSI: 2A/            | Visualizza Modifica Cancella |
    | @avviso_E:autore.nome,autore.cognome                | Ingresso posticipato    | #dat(@avviso_E:data)                | @avviso_E:titolo                | @avviso_E:sedi[0]                | /DOCENTI.*GENITORI.*ALUNNI.*CLASSI: 3A/            | Visualizza Modifica Cancella |
    | @avviso_A:autore.nome,autore.cognome                | Svolgimento attività    | #dat(@avviso_A:data)                | @avviso_A:titolo                | @avviso_A:sedi[0]                | /DSGA.*ATA.*DOCENTI.*GENITORI.*ALUNNI.*CLASSI: 3B/ | Visualizza Modifica Cancella |
    | @avviso_I:autore.nome,autore.cognome                | Comunicazione personale | #dat(@avviso_I:data)                | @avviso_I:titolo                | @avviso_I:sedi[0]                | GENITORI                                           | Visualizza Modifica Cancella |

Scenario: Visualizzazione con filtro sul tipo avviso generico
  Data pagina attiva "avvisi_gestione"
  Quando selezioni opzione "C" da lista "avviso_filtro_tipo"
  Allora vedi la pagina "avvisi_gestione"
  E vedi la tabella non ordinata:
    | Autore                                              | Tipo            | Data                                | Oggetto                         | Sede                             | Destinatari                             | Azione                       |
    | @avviso_C:autore.nome,autore.cognome                | Avviso generico | #dat(@avviso_C:data)                | @avviso_C:titolo                | @avviso_C:sedi[0]                | /DOCENTI.*GENITORI.*ALUNNI.*CLASSI: 3A/ | Visualizza Modifica Cancella |
    | @avviso_C_allegato:autore.nome,autore.cognome       | Avviso generico | #dat(@avviso_C_allegato:data)       | @avviso_C_allegato:titolo       | @avviso_C_allegato:sedi[0]       | ALUNNI                                  | Visualizza Modifica Cancella |
    | @avviso_non_modificabile:autore.nome,autore.cognome | Avviso generico | #dat(@avviso_non_modificabile:data) | @avviso_non_modificabile:titolo | @avviso_non_modificabile:sedi[0] | DOCENTI                                 | Visualizza                   |

Scenario: Visualizzazione con filtro sul tipo uscita anticipata
  Data pagina attiva "avvisi_gestione"
  Quando selezioni opzione "U" da lista "avviso_filtro_tipo"
  Allora vedi la pagina "avvisi_gestione"
  E vedi la tabella non ordinata:
    | Autore                               | Tipo              | Data                 | Oggetto          | Sede              | Destinatari                                        | Azione                       |
    | @avviso_U:autore.nome,autore.cognome | Uscita anticipata | #dat(@avviso_U:data) | @avviso_U:titolo | @avviso_U:sedi[0] | /DSGA.*ATA.*DOCENTI.*GENITORI.*ALUNNI.*CLASSI: 2A/ | Visualizza Modifica Cancella |

Scenario: Visualizzazione con filtro sul tipo ingresso posticipato
  Data pagina attiva "avvisi_gestione"
  Quando selezioni opzione "E" da lista "avviso_filtro_tipo"
  Allora vedi la pagina "avvisi_gestione"
  E vedi la tabella non ordinata:
    | Autore                               | Tipo                 | Data                 | Oggetto          | Sede              | Destinatari                                        | Azione                       |
    | @avviso_E:autore.nome,autore.cognome | Ingresso posticipato | #dat(@avviso_E:data) | @avviso_E:titolo | @avviso_E:sedi[0] | /DSGA.*ATA.*DOCENTI.*GENITORI.*ALUNNI.*CLASSI: 3A/ | Visualizza Modifica Cancella |

Scenario: Visualizzazione con filtro sul tipo svolgimento attività
  Data pagina attiva "avvisi_gestione"
  Quando selezioni opzione "A" da lista "avviso_filtro_tipo"
  Allora vedi la pagina "avvisi_gestione"
  E vedi la tabella non ordinata:
    | Autore                               | Tipo                 | Data                 | Oggetto          | Sede              | Destinatari                                        | Azione                       |
    | @avviso_A:autore.nome,autore.cognome | Svolgimento attività | #dat(@avviso_A:data) | @avviso_A:titolo | @avviso_A:sedi[0] | /DSGA.*ATA.*DOCENTI.*GENITORI.*ALUNNI.*CLASSI: 3B/ | Visualizza Modifica Cancella |

Scenario: Visualizzazione con filtro sul tipo comunicazione personale
  Data pagina attiva "avvisi_gestione"
  Quando selezioni opzione "I" da lista "avviso_filtro_tipo"
  Allora vedi la pagina "avvisi_gestione"
  E vedi la tabella non ordinata:
    | Autore                               | Tipo                    | Data                 | Oggetto          | Sede              | Destinatari | Azione                       |
    | @avviso_I:autore.nome,autore.cognome | Comunicazione personale | #dat(@avviso_I:data) | @avviso_I:titolo | @avviso_I:sedi[0] | GENITORI    | Visualizza Modifica Cancella |

Scenario: Visualizzazione con filtro per autore
  Data pagina attiva "avvisi_gestione"
  Quando selezioni opzione "@staff_1:cognome" da lista "avviso_filtro_autore"
  E selezioni opzione "" da lista "avviso_filtro_tipo"
  Allora vedi la pagina "avvisi_gestione"
  E vedi la tabella non ordinata:
    | Autore                               | Tipo                 | Data                 | Oggetto          | Sede              | Destinatari                             | Azione                       |
    | @avviso_U:autore.nome,autore.cognome | Uscita anticipata    | #dat(@avviso_U:data) | @avviso_U:titolo | @avviso_U:sedi[0] | /DOCENTI.*GENITORI.*ALUNNI.*CLASSI: 2A/ | Visualizza Modifica Cancella |
    | @avviso_E:autore.nome,autore.cognome | Ingresso posticipato | #dat(@avviso_E:data) | @avviso_E:titolo | @avviso_E:sedi[0] | /DOCENTI.*GENITORI.*ALUNNI.*CLASSI: 3A/ | Visualizza Modifica Cancella |

Scenario: Visualizzazione con filtro per mese
  Data pagina attiva "avvisi_gestione"
  Quando selezioni opzione "6" da lista "avviso_filtro_mese"
  Allora vedi la pagina "avvisi_gestione"
  E vedi nella tabella i dati:
    | Autore                                              | Tipo            | Data                                | Oggetto                         | Sede                             | Destinatari | Azione     |
    | @avviso_non_modificabile:autore.nome,autore.cognome | Avviso generico | #dat(@avviso_non_modificabile:data) | @avviso_non_modificabile:titolo | @avviso_non_modificabile:sedi[0] | DOCENTI     | Visualizza |

Scenario: Visualizzazione con filtro per oggetto
  Data pagina attiva "avvisi_gestione"
  Quando selezioni opzione "" da lista "avviso_filtro_tipo"
  E inserisci "@avviso_A:titolo" nel campo "oggetto"
  E premi pulsante "Filtra"
  Allora vedi la pagina "avvisi_gestione"
  E vedi la tabella non ordinata:
    | Autore                               | Tipo                 | Data                 | Oggetto          | Sede              | Destinatari                                        | Azione                       |
    | @avviso_A:autore.nome,autore.cognome | Svolgimento attività | #dat(@avviso_A:data) | @avviso_A:titolo | @avviso_A:sedi[0] | /DSGA.*ATA.*DOCENTI.*GENITORI.*ALUNNI.*CLASSI: 3B/ | Visualizza Modifica Cancella |

Scenario: Visualizzazione con filtri combinati
  Data pagina attiva "avvisi_gestione"
  Quando selezioni opzione "C" da lista "avviso_filtro_tipo"
  E selezioni opzione "@staff_4:cognome" da lista "avviso_filtro_autore"
  E selezioni opzione "6" da lista "avviso_filtro_mese"
  E inserisci "@avviso_non_modificabile:titolo" nel campo "oggetto"
  E premi pulsante "Filtra"
  Allora vedi la pagina "avvisi_gestione"
  E vedi nella tabella i dati:
    | Autore                                              | Tipo            | Data                                | Oggetto                         | Sede                             | Destinatari | Azione     |
    | @avviso_non_modificabile:autore.nome,autore.cognome | Avviso generico | #dat(@avviso_non_modificabile:data) | @avviso_non_modificabile:titolo | @avviso_non_modificabile:sedi[0] | DOCENTI     | Visualizza |

Scenario: Visualizzazione lista vuota
  Data pagina attiva "avvisi_gestione"
  Quando selezioni opzione "A" da lista "avviso_filtro_tipo"
  E selezioni opzione "@staff_4:cognome" da lista "avviso_filtro_autore"
  E premi pulsante "Filtra"
  Allora vedi la pagina "avvisi_gestione"
  E vedi "0" righe nella tabella


################################################################################
# Bisogna controllare memorizzazione dati di sessione

Scenario: Navigazione con filtro persistente
  Dato pagina attiva "avvisi_gestione"
  Quando selezioni opzione "C" da lista "avviso_filtro_tipo"
  E selezioni opzione "@staff_4:cognome" da lista "avviso_filtro_autore"
  E selezioni opzione "6" da lista "avviso_filtro_mese"
  E inserisci "@avviso_non_modificabile:titolo" nel campo "oggetto"
  E premi pulsante "Filtra"
  E vai alla pagina "login_home"
  E vai alla pagina "avvisi_gestione"
  Allora vedi la pagina "avvisi_gestione"
  E vedi nella tabella i dati:
    | Autore                                              | Tipo            | Data                                | Oggetto                         | Sede                             | Destinatari | Azione     |
    | @avviso_non_modificabile:autore.nome,autore.cognome | Avviso generico | #dat(@avviso_non_modificabile:data) | @avviso_non_modificabile:titolo | @avviso_non_modificabile:sedi[0] | DOCENTI     | Visualizza |
  E vedi opzione "C" in lista "avviso_filtro_tipo"
  E vedi opzione "@staff_4:cognome" in lista "avviso_filtro_autore"
  E vedi opzione "6" in lista "avviso_filtro_mese"
  E il campo "oggetto" contiene "@avviso_non_modificabile:titolo"


################################################################################
# Bisogna controllare accesso a pagina

Scenario: accesso pagina senza utente
  Dato logout utente
  Quando vai alla pagina "avvisi_gestione"
  Allora vedi la pagina "login_form"

Schema dello scenario: accesso pagina con altri utenti
  Dato logout utente
  E login utente con ruolo esatto <ruolo>
  Quando vai alla pagina "avvisi_gestione"
  Allora vedi errore pagina "403"
  Esempi:
    | ruolo          |
    | Amministratore |
    | Ata            |
    | Genitore       |
    | Alunno         |
    | Utente         |
