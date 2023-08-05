# language: it

Funzionalità: Gestione del registro delle lezioni
  Per controllare la gestione del registro delle lezioni
  Come utente docente
  Bisogna controllare la visualizzazione giornaliera delle lezioni 
  Bisogna controllare la visualizzazione giornaliera di note, annotazioni e assenze 
  Bisogna controllare la visualizzazione mensile 


################################################################################
# Bisogna controllare la visualizzazione giornaliera delle lezioni

Scenario: Vista giornaliera area comune con lezioni comuni, religione, sostegno, supplenza 
  Dato login utente "docente1"
  Quando pagina attiva "lezioni_registro_firme" con parametri:
    | cattedra          | classe        | data       | vista |
    | @cattedra_1_1A:id | @classe_1A:id | 2023-02-01 | G     |
  Allora vedi la tabella:
    | ora               | materia                     | docenti                                          | argomenti/attività            | azioni                |
    | 1ª: 08:20 - 09:20 | Italiano                    | ?@docente_1:nome,cognome?@docente_7:nome,cognome | @lezione_1:argomento,attivita | /^Modifica Cancella$/ |
    | 2ª: 09:20 - 10:10 | Matematica                  | @docente_2:nome,cognome                          | @lezione_2:argomento,attivita |                       |
    | 3ª: 10:10 - 11:00 | Sostegno                    | @docente_7:nome,cognome                          | @lezione_3:argomento,attivita | /^Aggiungi$/          |
    | 4ª: 11:00 - 12:00 | Gruppo: Mat. Alt. Religione | @docente_6:nome,cognome                          | @lezione_5:argomento,attivita |                       |
    |                   | Gruppo: N.A. Religione      | @docente_9:nome,cognome                          | @lezione_6:argomento,attivita |                       |
    |                   | Gruppo: Religione Religione | @docente_5:nome,cognome                          | @lezione_4:argomento,attivita |                       |
    | 5ª: 12:00 - 12:50 | Supplenza                   | @docente_8:nome,cognome                          | @lezione_7:argomento,attivita |                       |
    | 6ª: 12:50 - 13:50 |                             |                                                  |                               | /^Aggiungi$/          |

Schema dello scenario: Vista giornaliera area gruppi con lezioni di gruppo, comuni, compresenza, sostegno 
  Dato login utente "<docente>"
  Quando pagina attiva "lezioni_registro_firme" con parametri:
    | cattedra      | classe      | data       | vista |
    | <cattedra>:id | <classe>:id | 2023-02-01 | G     |
  Allora vedi la tabella:
    | ora               | materia                    | docenti                                          | argomenti/attività             | azioni       |
    | 1ª: 08:20 - 09:20 | Gruppo: 3C-AMB Informatica | @docente_3:nome,cognome                          | @lezione_12:argomento,attivita | <azione1>    |
    |                   | Gruppo: 3C-CHI Matematica  | ?@docente_2:nome,cognome?@docente_7:nome,cognome | @lezione_11:argomento,attivita |              |
    | 2ª: 09:20 - 10:10 | Italiano                   | @docente_1:nome,cognome                          | @lezione_13:argomento,attivita | <azione2>    |
    | 3ª: 10:10 - 11:00 | Gruppo: 3C-AMB Informatica | ?@docente_3:nome,cognome?@docente_4:nome,cognome | @lezione_14:argomento,attivita | <azione3>    |
    | 4ª: 11:00 - 12:00 | Sostegno                   | @docente_7:nome,cognome                          | @lezione_15:argomento,attivita | /^Aggiungi$/ |
    | 5ª: 12:00 - 12:50 | Gruppo: 3C-CHI Supplenza   | @docente_9:nome,cognome                          | @lezione_16:argomento,attivita | <azione4>    |
    | 6ª: 12:50 - 13:50 |                            |                                                  |                                | /^Aggiungi$/ |
  Esempi:
    | docente  | cattedra         | classe       | azione1               | azione2               | azione3               | azione4      | 
    | docente2 | @cattedra_2_3C-1 | @classe_3C-1 | /^Modifica Cancella$/ |                       | /^Aggiungi$/          |              |
    | docente3 | @cattedra_3_3C-2 | @classe_3C-2 | /^Modifica Cancella$/ |                       | /^Modifica Cancella$/ | /^Aggiungi$/ |
    | docente1 | @cattedra_1_3C   | @classe_3C   |                       | /^Modifica Cancella$/ |                       |              |


################################################################################
# Bisogna controllare la visualizzazione giornaliera di note, annotazioni e assenze

Scenario: Vista giornaliera area comune con note, annotazioni e assenze/ritardi/uscite/FC
  Dato login utente "docente1"
  Quando pagina attiva "lezioni_registro_firme" con parametri:
    | cattedra          | classe        | data       | vista |
    | @cattedra_1_1A:id | @classe_1A:id | 2023-02-02 | G     |
  Allora la sezione "#gs-note > .list-group > div:nth-child(1)" contiene "@nota_1:testo,docente.nome,docente.cognome @nota_1:provvedimento,docenteProvvedimento.nome,docenteProvvedimento.cognome"
  E la sezione "#gs-note > .list-group > div:nth-child(2)" contiene "@nota_2:testo,docente.nome,docente.cognome @nota_2:provvedimento,docenteProvvedimento.nome,docenteProvvedimento.cognome"
  E la sezione "#gs-annotazioni > .list-group > div:nth-child(1)" contiene "@annotazione_1:testo,docente.nome,docente.cognome"
  E la sezione "#gs-assenti" contiene "@alunno3_1A:cognome,nome"
  E la sezione "#gs-ritardi" contiene "@alunno1_1A:cognome,nome"
  E la sezione "#gs-uscite" contiene "@alunno2_1A:cognome,nome"
  E la sezione "#gs-fuoriclasse" contiene "@alunno4_1A:cognome,nome @presenza_1:descrizione"

Schema dello scenario: Vista giornaliera gruppi con note, annotazioni e assenze/ritardi/uscite/FC
  Dato login utente "<docente>"
  Quando pagina attiva "lezioni_registro_firme" con parametri:
    | cattedra      | classe      | data       | vista |
    | <cattedra>:id | <classe>:id | 2023-02-02 | G     |
  Allora la sezione "#gs-note > .list-group > div:nth-child(1)" contiene "@nota_3:testo,docente.nome,docente.cognome @nota_3:provvedimento,docenteProvvedimento.nome,docenteProvvedimento.cognome"
  E la sezione "#gs-note > .list-group > div:nth-child(2)" contiene "@nota_4:testo,docente.nome,docente.cognome @nota_4:provvedimento,docenteProvvedimento.nome,docenteProvvedimento.cognome"
  E la sezione "#gs-note > .list-group > div:nth-child(3)" contiene "@nota_5:testo,docente.nome,docente.cognome @nota_5:provvedimento,docenteProvvedimento.nome,docenteProvvedimento.cognome"
  E la sezione "#gs-annotazioni > .list-group > div:nth-child(1)" contiene "@annotazione_2:testo,docente.nome,docente.cognome"
  E la sezione "#gs-annotazioni > .list-group > div:nth-child(2)" contiene "@annotazione_3:testo,docente.nome,docente.cognome"
  E la sezione "#gs-annotazioni > .list-group > div:nth-child(3)" contiene "@annotazione_4:testo,docente.nome,docente.cognome"
  E la sezione "#gs-assenti" contiene "<assenti>"
  E la sezione "#gs-ritardi" contiene "<ritardi>"
  E la sezione "#gs-uscite" contiene "<uscite>"
  E la sezione "#gs-fuoriclasse" contiene "<fc>"
  Esempi:
    | docente  | cattedra         | classe       | assenti                                                | ritardi                                                | uscite                                                 | fc                                                                                                     |
    | docente2 | @cattedra_2_3C-1 | @classe_3C-1 | @alunno1_3C-1:cognome,nome                             | @alunno2_3C-1:cognome,nome                             | @alunno3_3C-1:cognome,nome                             | @alunno4_3C-1:cognome,nome @presenza_2:descrizione                                                     |
    | docente3 | @cattedra_3_3C-2 | @classe_3C-2 | @alunno1_3C-2:cognome,nome                             | @alunno2_3C-2:cognome,nome                             | @alunno3_3C-2:cognome,nome                             | @alunno4_3C-2:cognome,nome @presenza_3:descrizione                                                     |
    | docente1 | @cattedra_1_3C   | @classe_3C   | ?@alunno1_3C-1:cognome,nome?@alunno1_3C-2:cognome,nome | ?@alunno2_3C-1:cognome,nome?@alunno2_3C-2:cognome,nome | ?@alunno3_3C-1:cognome,nome?@alunno3_3C-2:cognome,nome | ?@alunno4_3C-1:cognome,nome @presenza_2:descrizione?@alunno4_3C-2:cognome,nome @presenza_3:descrizione |


################################################################################
# Bisogna controllare la visualizzazione mensile 

Scenario: Vista mensile area comune con lezioni
  Dato login utente "docente1"
  Quando pagina attiva "lezioni_registro_firme" con parametri:
    | cattedra          | classe        | data       | vista |
    | @cattedra_1_1A:id | @classe_1A:id | 2023-02-01 | M     |
  Allora vedi nella riga "1" della tabella i dati:
    | data | eventi | 1ª       | 2ª         | 3ª       | 4ª                                                                | 5ª        | 6ª | 7ª | 8ª |
    | Me 1 |        | Italiano | Matematica | Sostegno | Religione / Att. alt. Religione / Att. alt. Religione / Att. alt. | Supplenza |    |    |    |

Schema dello scenario: Vista mensile area a gruppi con lezioni
  Dato login utente "<docente>"
  Quando pagina attiva "lezioni_registro_firme" con parametri:
    | cattedra      | classe      | data       | vista |
    | <cattedra>:id | <classe>:id | 2023-02-01 | M     |
  Allora vedi nella riga "1" della tabella i dati:
    | data | eventi | 1ª                     | 2ª       | 3ª          | 4ª       | 5ª        | 6ª | 7ª | 8ª |
    | Me 1 |        | Informatica Matematica | Italiano | Informatica | Sostegno | Supplenza |    |    |    |
  Esempi:
    | docente  | cattedra         | classe       |
    | docente2 | @cattedra_2_3C-1 | @classe_3C-1 |
    | docente3 | @cattedra_3_3C-2 | @classe_3C-2 |
    | docente1 | @cattedra_1_3C   | @classe_3C   |

Schema dello scenario: Vista mensile area a gruppi con note
  Dato login utente "<docente>"
  Quando pagina attiva "lezioni_registro_firme" con parametri:
    | cattedra      | classe      | data       | vista |
    | <cattedra>:id | <classe>:id | 2023-02-01 | M     |
  E premi pulsante "Note disciplinari" con indice "2"
  E la sezione "#gs-modal-info-body > .list-group-item:nth-child(1)" contiene "@nota_3:testo,docente.nome,docente.cognome @nota_3:provvedimento,docenteProvvedimento.nome,docenteProvvedimento.cognome"
  E la sezione "#gs-modal-info-body > .list-group-item:nth-child(2)" contiene "@nota_4:testo,docente.nome,docente.cognome @nota_4:provvedimento,docenteProvvedimento.nome,docenteProvvedimento.cognome"
  E la sezione "#gs-modal-info-body > .list-group-item:nth-child(3)" contiene "@nota_5:testo,docente.nome,docente.cognome @nota_5:provvedimento,docenteProvvedimento.nome,docenteProvvedimento.cognome"
  Esempi:
    | docente  | cattedra         | classe       |
    | docente2 | @cattedra_2_3C-1 | @classe_3C-1 |
    | docente3 | @cattedra_3_3C-2 | @classe_3C-2 |
    | docente1 | @cattedra_1_3C   | @classe_3C   |

Schema dello scenario: Vista mensile area a gruppi con annotazioni
  Dato login utente "<docente>"
  Quando pagina attiva "lezioni_registro_firme" con parametri:
    | cattedra      | classe      | data       | vista |
    | <cattedra>:id | <classe>:id | 2023-02-01 | M     |
  E premi pulsante "Annotazioni sul registro"
  E la sezione "#gs-modal-info-body > .list-group-item:nth-child(1)" contiene "@annotazione_2:testo,docente.nome,docente.cognome"
  E la sezione "#gs-modal-info-body > .list-group-item:nth-child(2)" contiene "@annotazione_3:testo,docente.nome,docente.cognome"
  E la sezione "#gs-modal-info-body > .list-group-item:nth-child(3)" contiene "@annotazione_4:testo,docente.nome,docente.cognome"
  Esempi:
    | docente  | cattedra         | classe       |
    | docente2 | @cattedra_2_3C-1 | @classe_3C-1 |
    | docente3 | @cattedra_3_3C-2 | @classe_3C-2 |
    | docente1 | @cattedra_1_3C   | @classe_3C   |

Schema dello scenario: Vista mensile area a gruppi con dettagli lezione
  Dato login utente "<docente>"
  Quando pagina attiva "lezioni_registro_firme" con parametri:
    | cattedra      | classe      | data       | vista |
    | <cattedra>:id | <classe>:id | 2023-02-01 | M     |
  E premi pulsante "Matematica"
  E la sezione "#gs-modal-info-body > .list-group-item:nth-child(1)" contiene "@docente_2:nome,cognome @lezione_11:argomento,attivita"
  E la sezione "#gs-modal-info-body > .list-group-item:nth-child(2)" contiene "@alunno4_3C-1:cognome,nome @docente_7:nome,cognome @firma_sostegno_3:argomento,attivita"
  Esempi:
    | docente  | cattedra         | classe       |
    | docente2 | @cattedra_2_3C-1 | @classe_3C-1 |
    | docente3 | @cattedra_3_3C-2 | @classe_3C-2 |
    | docente1 | @cattedra_1_3C   | @classe_3C   |


