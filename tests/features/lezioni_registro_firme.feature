# language: it

Funzionalità: Gestione del registro delle lezioni
  Per controllare la gestione del registro delle lezioni
  Come utente docente
  Bisogna controllare la visualizzazione giornaliera delle lezioni
  Bisogna controllare la visualizzazione giornaliera di note, annotazioni e assenze
  Bisogna controllare la visualizzazione mensile
  Utilizzando "_lezioniFixtures.yml"


################################################################################
# Bisogna controllare la visualizzazione giornaliera delle lezioni

Scenario: Vista giornaliera area comune con lezioni comuni, religione, sostegno, supplenza
  Dato login utente "@docente_nocattedra_2:username"
  Quando pagina attiva "lezioni_registro_firme" con parametri:
    | cattedra            | classe        | data       | vista |
    | @t_cattedra_1_1A:id | @classe_1A:id | 2023-02-01 | G     |
  Allora vedi la tabella:
    | ora               | materia                     | docenti                                                              | argomenti/attività              | azioni                |
    | 1ª: 08:30 - 09:30 | Italiano                    | ?@docente_nocattedra_2:nome,cognome?@docente_sostegno_1:nome,cognome | @t_lezione_1:argomento,attivita | /^Modifica Cancella$/ |
    | 2ª: 09:30 - 10:30 | Matematica                  | @docente_curricolare_3:nome,cognome                                  | @t_lezione_2:argomento,attivita | /^Aggiungi$/          |
    | 3ª: 10:30 - 11:30 | Sostegno                    | @docente_sostegno_2:nome,cognome                                     | @t_lezione_3:argomento,attivita | /^Aggiungi$/          |
    | 4ª: 11:30 - 12:30 | Gruppo: Mat. Alt. Religione | @docente_curricolare_5:nome,cognome                                  | @t_lezione_5:argomento,attivita | /^Aggiungi$/          |
    |                   | Gruppo: N.A. Religione      | @docente_curricolare_4:nome,cognome                                  | @t_lezione_6:argomento,attivita |                       |
    |                   | Gruppo: Religione Religione | @docente_religione_1:nome,cognome                                    | @t_lezione_4:argomento,attivita |                       |
    | 5ª: 12:30 - 13:30 | Supplenza                   | @docente_nocattedra_1:nome,cognome                                   | @t_lezione_7:argomento,attivita | /^Aggiungi$/          |
    | 6ª: 13:30 - 14:30 |                             |                                                                      |                                 | /^Aggiungi$/          |

Schema dello scenario: Vista giornaliera area gruppi con lezioni di gruppo, comuni, compresenza, sostegno
  Dato login utente "<docente>"
  Quando pagina attiva "lezioni_registro_firme" con parametri:
    | cattedra      | classe      | data       | vista |
    | <cattedra>:id | <classe>:id | 2023-02-01 | G     |
  Allora vedi la tabella:
    | ora               | materia                    | docenti                                                                 | argomenti/attività               | azioni       |
    | 1ª: 08:30 - 09:30 | Gruppo: 3C-AMB Informatica | @docente_nocattedra_3:nome,cognome                                      | @t_lezione_9:argomento,attivita  | <azione1>    |
    |                   | Gruppo: 3C-CHI Matematica  | ?@docente_curricolare_3:nome,cognome?@docente_sostegno_5:nome,cognome   | @t_lezione_8:argomento,attivita  |              |
    | 2ª: 09:30 - 10:30 | Italiano                   | @docente_nocattedra_2:nome,cognome                                      | @t_lezione_10:argomento,attivita | <azione2>    |
    | 3ª: 10:30 - 11:30 | Gruppo: 3C-AMB Informatica | ?@docente_nocattedra_3:nome,cognome?@docente_curricolare_4:nome,cognome | @t_lezione_11:argomento,attivita | <azione3>    |
    | 4ª: 11:30 - 12:30 | Sostegno                   | @docente_sostegno_3:nome,cognome                                        | @t_lezione_12:argomento,attivita | /^Aggiungi$/ |
    | 5ª: 12:30 - 13:30 | Gruppo: 3C-CHI Supplenza   | @docente_nocattedra_1:nome,cognome                                      | @t_lezione_13:argomento,attivita | /^Aggiungi$/ |
    | 6ª: 13:30 - 14:30 |                            |                                                                         |                                  | /^Aggiungi$/ |
  Esempi:
    | docente                         | cattedra            | classe        | azione1               | azione2               | azione3               |
    | @docente_curricolare_3:username | @t_cattedra_4_3CCHI | @classe_3CCHI | /^Modifica Cancella$/ | /^Aggiungi$/          | /^Aggiungi$/          |
    | @docente_nocattedra_3:username  | @t_cattedra_3_3CAMB | @classe_3CAMB | /^Modifica Cancella$/ | /^Aggiungi$/          | /^Modifica Cancella$/ |
    | @docente_nocattedra_2:username  | @t_cattedra_1_3C    | @classe_3C    | /^Aggiungi$/          | /^Modifica Cancella$/ | /^Aggiungi$/          |


################################################################################
# Bisogna controllare la visualizzazione giornaliera di note, annotazioni e assenze

Scenario: Vista giornaliera area comune con note, annotazioni e assenze/ritardi/uscite/FC
  Dato login utente "@docente_nocattedra_2:username"
  Quando pagina attiva "lezioni_registro_firme" con parametri:
    | cattedra            | classe        | data       | vista |
    | @t_cattedra_1_1A:id | @classe_1A:id | 2023-02-02 | G     |
  Allora la sezione "#gs-note > .list-group > div:nth-child(1)" contiene "@t_nota_1:testo,docente.nome,docente.cognome @t_nota_1:provvedimento,docenteProvvedimento.nome,docenteProvvedimento.cognome"
  E la sezione "#gs-note > .list-group > div:nth-child(2)" contiene "@t_nota_2:testo,docente.nome,docente.cognome @t_nota_2:provvedimento,docenteProvvedimento.nome,docenteProvvedimento.cognome"
  E la sezione "#gs-annotazioni > .list-group > div:nth-child(1)" contiene "@t_annotazione_1:testo,docente.nome,docente.cognome"
  E la sezione "#gs-assenti" contiene "@alunno_sostegno_1:cognome,nome"
  E la sezione "#gs-ritardi" contiene "@alunno_1A_1:cognome,nome"
  E la sezione "#gs-uscite" contiene "@alunno_1A_2:cognome,nome"
  E la sezione "#gs-fuoriclasse" contiene "@alunno_sostegno_2:cognome,nome @t_presenza_1:descrizione"

Schema dello scenario: Vista giornaliera gruppi con note, annotazioni e assenze/ritardi/uscite/FC
  Dato login utente "<docente>"
  Quando pagina attiva "lezioni_registro_firme" con parametri:
    | cattedra      | classe      | data       | vista |
    | <cattedra>:id | <classe>:id | 2023-02-02 | G     |
  Allora la sezione "#gs-note > .list-group > div:nth-child(1)" contiene "@t_nota_3:testo,docente.nome,docente.cognome @t_nota_3:provvedimento,docenteProvvedimento.nome,docenteProvvedimento.cognome"
  E la sezione "#gs-note > .list-group > div:nth-child(2)" contiene "@t_nota_4:testo,docente.nome,docente.cognome @t_nota_4:provvedimento,docenteProvvedimento.nome,docenteProvvedimento.cognome"
  E la sezione "#gs-note > .list-group > div:nth-child(3)" contiene "@t_nota_5:testo,docente.nome,docente.cognome @t_nota_5:provvedimento,docenteProvvedimento.nome,docenteProvvedimento.cognome"
  E la sezione "#gs-annotazioni > .list-group > div:nth-child(1)" contiene "@t_annotazione_2:testo,docente.nome,docente.cognome"
  E la sezione "#gs-annotazioni > .list-group > div:nth-child(2)" contiene "@t_annotazione_3:testo,docente.nome,docente.cognome"
  E la sezione "#gs-annotazioni > .list-group > div:nth-child(3)" contiene "@t_annotazione_4:testo,docente.nome,docente.cognome"
  E la sezione "#gs-assenti" contiene "<assenti>"
  E la sezione "#gs-ritardi" contiene "<ritardi>"
  E la sezione "#gs-uscite" contiene "<uscite>"
  E la sezione "#gs-fuoriclasse" contiene "<fc>"
  Esempi:
    | docente                        | cattedra            | classe        | assenti                                                    | ritardi                                                    | uscite                                                     | fc                                                                                                                   |
    | @docente_nocattedra_2:username | @t_cattedra_2_3CCHI | @classe_3CCHI | @alunno_3CCHI_1:cognome,nome                               | @alunno_3CCHI_2:cognome,nome                               | @alunno_3CCHI_2:cognome,nome                               | @alunno_sostegno_3:cognome,nome @t_presenza_2:descrizione                                                            |
    | @docente_nocattedra_3:username | @t_cattedra_3_3CAMB | @classe_3CAMB | @alunno_3CAMB_1:cognome,nome                               | @alunno_3CAMB_2:cognome,nome                               | @alunno_3CAMB_2:cognome,nome                               | @alunno_sostegno_4:cognome,nome @t_presenza_3:descrizione                                                            |
    | @docente_nocattedra_2:username | @t_cattedra_1_3C    | @classe_3C    | ?@alunno_3CCHI_1:cognome,nome?@alunno_3CAMB_1:cognome,nome | ?@alunno_3CCHI_2:cognome,nome?@alunno_3CAMB_2:cognome,nome | ?@alunno_3CCHI_2:cognome,nome?@alunno_3CCHI_2:cognome,nome | ?@alunno_sostegno_3:cognome,nome @t_presenza_2:descrizione?@alunno_sostegno_4:cognome,nome @t_presenza_3:descrizione |


################################################################################
# Bisogna controllare la visualizzazione mensile

Scenario: Vista mensile area comune con lezioni
  Dato login utente "@docente_nocattedra_2:username"
  Quando pagina attiva "lezioni_registro_firme" con parametri:
    | cattedra            | classe        | data       | vista |
    | @t_cattedra_1_1A:id | @classe_1A:id | 2023-02-01 | M     |
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
    | docente                        | cattedra            | classe        |
    | @docente_nocattedra_2:username | @t_cattedra_2_3CCHI | @classe_3CCHI |
    | @docente_nocattedra_3:username | @t_cattedra_3_3CAMB | @classe_3CAMB |
    | @docente_nocattedra_2:username | @t_cattedra_1_3C    | @classe_3C    |

Schema dello scenario: Vista mensile area a gruppi con note
  Dato login utente "<docente>"
  Quando pagina attiva "lezioni_registro_firme" con parametri:
    | cattedra      | classe      | data       | vista |
    | <cattedra>:id | <classe>:id | 2023-02-01 | M     |
  E premi pulsante "Note disciplinari" con indice "2"
  E la sezione "#gs-modal-info-body > .list-group-item:nth-child(1)" contiene "@t_nota_3:testo,docente.nome,docente.cognome @t_nota_3:provvedimento,docenteProvvedimento.nome,docenteProvvedimento.cognome"
  E la sezione "#gs-modal-info-body > .list-group-item:nth-child(2)" contiene "@t_nota_4:testo,docente.nome,docente.cognome @t_nota_4:provvedimento,docenteProvvedimento.nome,docenteProvvedimento.cognome"
  E la sezione "#gs-modal-info-body > .list-group-item:nth-child(3)" contiene "@t_nota_5:testo,docente.nome,docente.cognome @t_nota_5:provvedimento,docenteProvvedimento.nome,docenteProvvedimento.cognome"
  Esempi:
    | docente                        | cattedra            | classe        |
    | @docente_nocattedra_2:username | @t_cattedra_2_3CCHI | @classe_3CCHI |
    | @docente_nocattedra_3:username | @t_cattedra_3_3CAMB | @classe_3CAMB |
    | @docente_nocattedra_2:username | @t_cattedra_1_3C    | @classe_3C    |

Schema dello scenario: Vista mensile area a gruppi con annotazioni
  Dato login utente "<docente>"
  Quando pagina attiva "lezioni_registro_firme" con parametri:
    | cattedra      | classe      | data       | vista |
    | <cattedra>:id | <classe>:id | 2023-02-01 | M     |
  E premi pulsante "Annotazioni sul registro"
  E la sezione "#gs-modal-info-body > .list-group-item:nth-child(1)" contiene "@t_annotazione_2:testo,docente.nome,docente.cognome"
  E la sezione "#gs-modal-info-body > .list-group-item:nth-child(2)" contiene "@t_annotazione_3:testo,docente.nome,docente.cognome"
  E la sezione "#gs-modal-info-body > .list-group-item:nth-child(3)" contiene "@t_annotazione_4:testo,docente.nome,docente.cognome"
  Esempi:
    | docente                        | cattedra            | classe        |
    | @docente_nocattedra_2:username | @t_cattedra_2_3CCHI | @classe_3CCHI |
    | @docente_nocattedra_3:username | @t_cattedra_3_3CAMB | @classe_3CAMB |
    | @docente_nocattedra_2:username | @t_cattedra_1_3C    | @classe_3C    |

Schema dello scenario: Vista mensile area a gruppi con dettagli lezione
  Dato login utente "<docente>"
  Quando pagina attiva "lezioni_registro_firme" con parametri:
    | cattedra      | classe      | data       | vista |
    | <cattedra>:id | <classe>:id | 2023-02-01 | M     |
  E premi pulsante "Matematica"
  E la sezione "#gs-modal-info-body > .list-group-item:nth-child(1)" contiene "@docente_curricolare_3:nome,cognome @t_lezione_8:argomento,attivita"
  E la sezione "#gs-modal-info-body > .list-group-item:nth-child(2)" contiene "@alunno_sostegno_3:cognome,nome @docente_sostegno_5:nome,cognome @t_firma_sostegno_3:argomento,attivita"
  Esempi:
    | docente                        | cattedra            | classe        |
    | @docente_nocattedra_2:username | @t_cattedra_2_3CCHI | @classe_3CCHI |
    | @docente_nocattedra_3:username | @t_cattedra_3_3CAMB | @classe_3CAMB |
    | @docente_nocattedra_2:username | @t_cattedra_1_3C    | @classe_3C    |
