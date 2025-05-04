# language: it

Funzionalità: Aggiunge una nuova lezione al registro
  Per aggiungere una nuova lezione al registro
  Come utente docente
  Bisogna controllare la visualizzazione degli errori sui parametri
  Bisogna controllare l'inserimento di una nuova lezione
  Bisogna controllare l'aggiunta di una firma ad una lezione esistente
  Bisogna controllare l'inserimento di una lezione multiclasse
  Utilizzando "_cattedreFixtures.yml"


################################################################################
# Bisogna controllare la visualizzazione degli errori sui parametri

Scenario: Errore se la classe non esiste
  Dato login utente "@docente_curricolare_1:username"
  Quando vai alla pagina "lezioni_registro_add" con parametri:
    | cattedra          | classe | data       | ora |
    | @cattedra_1A_1:id | 99999  | 2023-02-01 | 1   |
  Allora vedi errore pagina "404"

Scenario: Errore se la cattedra non appartiene al docente
  Dato login utente "@docente_curricolare_1:username"
  Quando vai alla pagina "lezioni_registro_add" con parametri:
    | cattedra          | classe        | data       | ora |
    | @cattedra_1A_2:id | @classe_1A:id | 2023-02-01 | 1   |
  Allora vedi errore pagina "404"

Scenario: Errore se la data è un giorno festivo
  Dato login utente "@docente_curricolare_1:username"
  Quando vai alla pagina "lezioni_registro_add" con parametri:
    | cattedra          | classe        | data       | ora |
    | @cattedra_1A_1:id | @classe_1A:id | 2023-01-01 | 1   |
  Allora vedi errore pagina "404"

Scenario: Errore se l'azione non è permessa al docente
  Dato login utente "@docente_curricolare_1:username"
  Quando vai alla pagina "lezioni_registro_add" con parametri:
    | cattedra          | classe        | data       | ora |
    | @cattedra_1B_1:id | @classe_1B:id | 2023-01-31 | 1   |
  Allora vedi errore pagina "404"


################################################################################
# Bisogna controllare l'inserimento di una nuova lezione

Schema dello scenario: Controlla form di nuova lezione curricolare
  Dato login utente "<docente>"
  E pagina attiva "lezioni_registro_firme" con parametri:
    | cattedra   | classe   | data       | vista |
    | <cattedra> | <classe> | 2023-02-01 | G     |
  Quando vai alla pagina "lezioni_registro_add" con parametri:
    | cattedra   | classe   | data       | ora |
    | <cattedra> | <classe> | 2023-02-01 | 1   |
  Allora vedi errore pagina "200"
  E il campo "registro_add[tipoSostituzione]" non è presente
  E il campo "registro_add[materia]" non è presente
  E il campo "registro_add[moduloFormativo]" è presente
  Esempi:
    | docente                         | cattedra                 | classe           |
    | @docente_curricolare_1:username | @cattedra_1A_1:id        | @classe_1A:id    |
    | @docente_curricolare_1:username | @cattedra_1A_civica_1:id | @classe_1A:id    |
    | @docente_religione_1:username   | @cattedra_1A_6:id        | @classe_1A:id    |
    | @docente_itp_2:username         | @cattedra_1A_8:id        | @classe_1A:id    |
    | @docente_nocattedra_1:username  | @cattedra_1A_11:id       | @classe_1A:id    |
    | @docente_curricolare_1:username | @cattedra_3C_1:id        | @classe_3C:id    |
    | @docente_itp_3:username         | @cattedra_3CCHI_1:id     | @classe_3CCHI:id |

Scenario: Controlla form di nuova lezione di sostegno
  Dato login utente "@docente_sostegno_1:username"
  E pagina attiva "lezioni_registro_firme" con parametri:
    | cattedra          | classe        | data       | vista |
    | @cattedra_1A_9:id | @classe_1A:id | 2023-02-01 | G     |
  Quando vai alla pagina "lezioni_registro_add" con parametri:
    | cattedra          | classe        | data       | ora |
    | @cattedra_1A_9:id | @classe_1A:id | 2023-02-01 | 1   |
  Allora vedi errore pagina "200"
  E il campo "registro_add[tipoSostituzione]" non è presente
  E il campo "registro_add[materia]" non è presente
  E il campo "registro_add[moduloFormativo]" non è presente

Scenario: Controlla form di nuova lezione di sostituzione su classe normale
  Dato login utente "@docente_itp_2:username"
  E pagina attiva "lezioni_registro_firme" con parametri:
    | cattedra | classe        | data       | vista |
    | 0        | @classe_1A:id | 2023-02-01 | G     |
  Quando vai alla pagina "lezioni_registro_add" con parametri:
    | cattedra | classe        | data       | ora |
    | 0        | @classe_1A:id | 2023-02-01 | 1   |
  Allora vedi errore pagina "200"
  E il campo "registro_add[tipoSostituzione]" è presente
  E il campo "registro_add[materia]" è presente
  E il campo "registro_add[moduloFormativo]" non è presente

Scenario: Controlla form di nuova lezione di sostituzione su classe articolata
  Dato login utente "@docente_itp_2:username"
  E pagina attiva "lezioni_registro_firme" con parametri:
    | cattedra | classe           | data       | vista |
    | 0        | @classe_3CCHI:id | 2023-02-01 | G     |
  Quando vai alla pagina "lezioni_registro_add" con parametri:
    | cattedra | classe           | data       | ora |
    | 0        | @classe_3CCHI:id | 2023-02-01 | 1   |
  Allora vedi errore pagina "200"
  E il campo "registro_add[tipoSostituzione]" non è presente
  E il campo "registro_add[materia]" è presente
  E il campo "registro_add[moduloFormativo]" non è presente

Schema dello scenario: Inserisce una nuova lezione curricolare
  Dato login utente "<docente>"
  E pagina attiva "lezioni_registro_firme" con parametri:
    | cattedra   | classe   | data       | vista |
    | <cattedra> | <classe> | 2023-02-01 | G     |
  Quando vai alla pagina "lezioni_registro_add" con parametri:
    | cattedra   | classe   | data       | ora |
    | <cattedra> | <classe> | 2023-02-01 | 1   |
  E selezioni opzione "<ore>" da lista "registro_add[fine]"
  E inserisci "Argomento svolto" nel campo "registro_add[argomento]"
  E inserisci "Attività svolta" nel campo "registro_add[attivita]"
  E click su "Conferma"
  Allora vedi la pagina "lezioni_registro_firme"
  E vedi la tabella:
    | ora               | materia    | docenti    | argomenti/attività                 | azioni            |
    | 1ª: 08:30 - 09:30 | <materia1> | <docente1> | Argomento svolto - Attività svolta | Modifica Cancella |
    | 2ª: 09:30 - 10:30 | <materia2> | <docente2> | <argomenti2>                       | <azioni2>         |
    | 3ª: 10:30 - 11:30 |            |            |                                    | Aggiungi          |
    | 4ª: 11:30 - 12:30 |            |            |                                    | Aggiungi          |
    | 5ª: 12:30 - 13:30 |            |            |                                    | Aggiungi          |
    | 6ª: 13:30 - 14:30 |            |            |                                    | Aggiungi          |
  Esempi:
    | docente                         | cattedra                 | classe           | ore | materia1                                                    | docente1                            | materia2                         | docente2                            | argomenti2                         | azioni2           |
    | @docente_curricolare_1:username | @cattedra_1A_1:id        | @classe_1A:id    | 2   | @cattedra_1A_1:materia.nomeBreve                            | @docente_curricolare_1:nome,cognome | @cattedra_1A_1:materia.nomeBreve | @docente_curricolare_1:nome,cognome | Argomento svolto - Attività svolta | Modifica Cancella |
    | @docente_curricolare_1:username | @cattedra_1A_civica_1:id | @classe_1A:id    | 1   | @cattedra_1A_civica_1:materia.nomeBreve                     | @docente_curricolare_1:nome,cognome |                                  |                                     |                                    | Aggiungi          |
    | @docente_religione_1:username   | @cattedra_1A_6:id        | @classe_1A:id    | 1   | #str(Gruppo:)+ Religione +@cattedra_1A_6:materia.nomeBreve  | @docente_religione_1:nome,cognome   |                                  |                                     |                                    | Aggiungi          |
    | @docente_itp_2:username         | @cattedra_1A_8:id        | @classe_1A:id    | 1   | @cattedra_1A_8:materia.nomeBreve                            | @docente_itp_2:nome,cognome         |                                  |                                     |                                    | Aggiungi          |
    | @docente_nocattedra_1:username  | @cattedra_1A_11:id       | @classe_1A:id    | 1   | #str(Gruppo:)+ Mat. Alt. +@cattedra_1A_11:materia.nomeBreve | @docente_nocattedra_1:nome,cognome  |                                  |                                     |                                    | Aggiungi          |
    | @docente_curricolare_1:username | @cattedra_3C_1:id        | @classe_3C:id    | 2   | @cattedra_3C_1:materia.nomeBreve                            | @docente_curricolare_1:nome,cognome | @cattedra_3C_1:materia.nomeBreve | @docente_curricolare_1:nome,cognome | Argomento svolto - Attività svolta | Modifica Cancella |
    | @docente_itp_3:username         | @cattedra_3CCHI_1:id     | @classe_3CCHI:id | 1   | #str(Gruppo:)+ 3C-CHI +@cattedra_3CCHI_1:materia.nomeBreve  | @docente_itp_3:nome,cognome         |                                  |                                     |                                    | Aggiungi          |

Scenario: Inserisce una nuova lezione di sostegno
  Dato login utente "@docente_sostegno_1:username"
  E pagina attiva "lezioni_registro_firme" con parametri:
    | cattedra          | classe        | data       | vista |
    | @cattedra_1A_9:id | @classe_1A:id | 2023-02-01 | G     |
  Quando vai alla pagina "lezioni_registro_add" con parametri:
    | cattedra          | classe        | data       | ora |
    | @cattedra_1A_9:id | @classe_1A:id | 2023-02-01 | 1   |
  E selezioni opzione "1" da lista "registro_add[fine]"
  E inserisci "Argomento svolto" nel campo "registro_add[argomento]"
  E inserisci "Attività svolta" nel campo "registro_add[attivita]"
  E click su "Conferma"
  Allora vedi la pagina "lezioni_registro_firme"
  E vedi la tabella:
    | ora               | materia                          | docenti                          | argomenti/attività                 | azioni            |
    | 1ª: 08:30 - 09:30 | @cattedra_1A_9:materia.nomeBreve | @docente_sostegno_1:nome,cognome |                                    | Modifica Cancella |
    | 2ª: 09:30 - 10:30 |                                  |                                  |                                    | Aggiungi          |
    | 3ª: 10:30 - 11:30 |                                  |                                  |                                    | Aggiungi          |
    | 4ª: 11:30 - 12:30 |                                  |                                  |                                    | Aggiungi          |
    | 5ª: 12:30 - 13:30 |                                  |                                  |                                    | Aggiungi          |
    | 6ª: 13:30 - 14:30 |                                  |                                  |                                    | Aggiungi          |

Schema dello scenario: Inserisce una nuova lezione di sostituzione in una classe normale
  Dato login utente "<docente>"
  E pagina attiva "lezioni_registro_firme" con parametri:
    | cattedra | classe   | data       | vista |
    | 0        | <classe> | 2023-02-01 | G     |
  Quando vai alla pagina "lezioni_registro_add" con parametri:
    | cattedra | classe   | data       | ora |
    | 0        | <classe> | 2023-02-01 | 1   |
  E selezioni opzione "2" da lista "registro_add[fine]"
  E selezioni opzione "<tipo>" da pulsanti radio "registro_add[tipoSostituzione]"
  E selezioni opzione "<materia>" da lista "registro_add[materia]"
  E inserisci "Argomento svolto" nel campo "registro_add[argomento]"
  E inserisci "Attività svolta" nel campo "registro_add[attivita]"
  E click su "Conferma"
  Allora vedi la pagina "lezioni_registro_firme"
  E vedi la tabella:
    | ora               | materia    | docenti    | argomenti/attività                 | azioni            |
    | 1ª: 08:30 - 09:30 | <materia1> | <docente1> | Argomento svolto - Attività svolta | Modifica Cancella |
    | 2ª: 09:30 - 10:30 | <materia1> | <docente1> | Argomento svolto - Attività svolta | Modifica Cancella |
    | 3ª: 10:30 - 11:30 |            |            |                                    | Aggiungi          |
    | 4ª: 11:30 - 12:30 |            |            |                                    | Aggiungi          |
    | 5ª: 12:30 - 13:30 |            |            |                                    | Aggiungi          |
    | 6ª: 13:30 - 14:30 |            |            |                                    | Aggiungi          |
  Esempi:
    | docente                 | classe           | tipo            | materia                       | materia1                          | docente1                    |
    | @docente_itp_3:username | @classe_1A:id    | Tutta la classe | Sostituzione                  | Sostituzione                      | @docente_itp_3:nome,cognome |
    | @docente_itp_3:username | @classe_1A:id    | Tutta la classe | Lingua e letteratura italiana | Italiano                          | @docente_itp_3:nome,cognome |
    | @docente_itp_3:username | @classe_1A:id    | Gruppo N.A.     | Sostituzione                  | #str(Gruppo:)+ N.A. +Sostituzione | @docente_itp_3:nome,cognome |

Schema dello scenario: Inserisce una nuova lezione di sostituzione in una classe articolata
  Dato login utente "<docente>"
  E pagina attiva "lezioni_registro_firme" con parametri:
    | cattedra | classe   | data       | vista |
    | 0        | <classe> | 2023-02-01 | G     |
  Quando vai alla pagina "lezioni_registro_add" con parametri:
    | cattedra | classe   | data       | ora |
    | 0        | <classe> | 2023-02-01 | 1   |
  E selezioni opzione "2" da lista "registro_add[fine]"
  E selezioni opzione "<materia>" da lista "registro_add[materia]"
  E inserisci "Argomento svolto" nel campo "registro_add[argomento]"
  E inserisci "Attività svolta" nel campo "registro_add[attivita]"
  E click su "Conferma"
  Allora vedi la pagina "lezioni_registro_firme"
  E vedi la tabella:
    | ora               | materia    | docenti    | argomenti/attività                 | azioni            |
    | 1ª: 08:30 - 09:30 | <materia1> | <docente1> | Argomento svolto - Attività svolta | Modifica Cancella |
    | 2ª: 09:30 - 10:30 | <materia1> | <docente1> | Argomento svolto - Attività svolta | Modifica Cancella |
    | 3ª: 10:30 - 11:30 |            |            |                                    | Aggiungi          |
    | 4ª: 11:30 - 12:30 |            |            |                                    | Aggiungi          |
    | 5ª: 12:30 - 13:30 |            |            |                                    | Aggiungi          |
    | 6ª: 13:30 - 14:30 |            |            |                                    | Aggiungi          |
  Esempi:
    | docente                 | classe           | materia                    | materia1                           | docente1                    |
    | @docente_itp_1:username | @classe_3CCHI:id | Sostituzione               | #str(Gruppo:)+ 3C-CHI Sostituzione | @docente_itp_1:nome,cognome |
    | @docente_itp_1:username | @classe_3CCHI:id | Scienze integrate: Chimica | #str(Gruppo:)+ 3C-CHI Chimica      | @docente_itp_1:nome,cognome |


################################################################################
# Bisogna controllare l'aggiunta di una firma ad una lezione esistente

Schema dello scenario: Errore di inserimento di una firma su una lezione esistente
  Dato login utente "<docente>"
  E pagina attiva "lezioni_registro_firme" con parametri:
    | cattedra   | classe   | data   | vista |
    | <cattedra> | <classe> | <data> | G     |
  Quando vai alla pagina "lezioni_registro_add" con parametri:
    | cattedra   | classe   | data   | ora   |
    | <cattedra> | <classe> | <data> | <ora> |
  Allora vedi la pagina "lezioni_registro_firme"
  E la sezione "#gs-main .alert-danger" contiene "<errore>"
  Esempi:
    | docente                         | cattedra                    | classe           | data       | ora | errore                                                          |
    | @docente_curricolare_1:username | @cattedra_2A_1:id           | @classe_2A:id    | 2023-02-02 | 1   | /Non è possibile firmare .* 1ª .* stessa ora nella classe 1ª A/ |
    | @docente_curricolare_1:username | @cattedra_1A_1:id           | @classe_1A:id    | 2023-02-02 | 4   | /Non è possibile firmare .* 4ª .* incompatibile con la lezione/ |
    | @docente_curricolare_1:username | @cattedra_1A_1:id           | @classe_1A:id    | 2023-02-02 | 3   | /Non è possibile firmare .* 3ª .* incompatibile con la lezione/ |
    | @docente_curricolare_1:username | @cattedra_1A_1:id           | @classe_1A:id    | 2023-02-02 | 5   | /Non è possibile firmare .* 5ª .* incompatibile con la lezione/ |
    | @docente_itp_3:username         | 0                           | @classe_1A:id    | 2023-02-02 | 1   | /Non è possibile firmare .* 1ª .* incompatibile con la lezione/ |
    | @docente_curricolare_2:username | @cattedra_3C_2:id           | @classe_3C:id    | 2023-02-03 | 1   | /Non è possibile firmare .* 1ª .* incompatibile con la lezione/ |
    | @docente_curricolare_1:username | @cattedra_3C_1:id           | @classe_3C:id    | 2023-02-03 | 4   | /Non è possibile firmare .* 4ª .* incompatibile con la lezione/ |
    | @docente_itp_1:username         | 0                           | @classe_3CCHI:id | 2023-02-03 | 4   | /Non è possibile firmare .* 4ª .* incompatibile con la lezione/ |
    | @docente_curricolare_1:username | @cattedra_1A_1:id           | @classe_1A:id    | 2023-02-04 | 2   | /Non è possibile firmare .* 2ª .* incompatibile con la lezione/ |
    | @docente_curricolare_3:username | @cattedra_1A_3:id           | @classe_1A:id    | 2023-02-04 | 3   | /Non è possibile firmare .* 3ª .* incompatibile con la lezione/ |
    | @docente_curricolare_1:username | @cattedra_1A_civica_1:id    | @classe_1A:id    | 2023-02-04 | 3   | /Non è possibile firmare .* 3ª .* incompatibile con la lezione/ |
    | @docente_religione_1:username   | @cattedra_1A_6:id           | @classe_1A:id    | 2023-02-04 | 3   | /Non è possibile firmare .* 3ª .* incompatibile con la lezione/ |
    | @docente_nocattedra_1:username  | @cattedra_1A_11:id          | @classe_1A:id    | 2023-02-04 | 3   | /Non è possibile firmare .* 3ª .* incompatibile con la lezione/ |
    | @docente_curricolare_1:username | @cattedra_1A_1:id           | @classe_1A:id    | 2023-02-04 | 4   | /Non è possibile firmare .* 4ª .* incompatibile con la lezione/ |
    | @docente_curricolare_1:username | @cattedra_3C_1:id           | @classe_3C:id    | 2023-02-04 | 5   | /Non è possibile firmare .* 5ª .* incompatibile con la lezione/ |
    | @docente_itp_3:username         | @cattedra_3CCHI_1:id        | @classe_3CCHI:id | 2023-02-04 | 5   | /Non è possibile firmare .* 5ª .* incompatibile con la lezione/ |
    | @docente_itp_3:username         | @cattedra_3CCHI_1:id        | @classe_3CCHI:id | 2023-02-04 | 6   | /Non è possibile firmare .* 6ª .* incompatibile con la lezione/ |
    | @docente_itp_3:username         | @cattedra_3CCHI_civica_1:id | @classe_3CCHI:id | 2023-02-04 | 6   | /Non è possibile firmare .* 6ª .* incompatibile con la lezione/ |

Schema dello scenario: Inserisce una nuova firma su una lezione esistente
  Dato login utente "<docente>"
  E pagina attiva "lezioni_registro_firme" con parametri:
    | cattedra   | classe   | data   | vista |
    | <cattedra> | <classe> | <data> | G     |
  Quando vai alla pagina "lezioni_registro_add" con parametri:
    | cattedra   | classe   | data   | ora   |
    | <cattedra> | <classe> | <data> | <ora> |
  E inserisci "Argomento svolto" nel campo "registro_add[argomento]"
  E inserisci "Attività svolta" nel campo "registro_add[attivita]"
  E click su "Conferma"
  Allora vedi la pagina "lezioni_registro_firme"
  E vedi nella riga "<ora>" della tabella i dati:
    | ora      | materia   | docenti   | argomenti/attività | azioni            |
    | <orario> | <materia> | <docenti> | <argomenti>        | Modifica Cancella |
  Esempi:
    | docente                         | cattedra           | classe        | data       | ora | orario            | materia     | docenti                                                                 | argomenti                          |
    # | @docente_nocattedra_2:username  | @cattedra_1A_12:id | @classe_1A:id | 2023-02-02 | 1   | 1ª: 08:30 - 09:30 | Italiano    | ?@docente_nocattedra_2:nome,cognome?@docente_curricolare_1:nome,cognome | Argomento svolto - Attività svolta |
    # | @docente_itp_1:username         | @cattedra_1A_7:id  | @classe_1A:id | 2023-02-02 | 5   | 5ª: 12:30 - 13:30 | Informatica | ?@docente_itp_1:nome,cognome?@docente_itp_2:nome,cognome                | Argomento svolto - Attività svolta |
    # | @docente_itp_2:username         | @cattedra_1A_8:id  | @classe_1A:id | 2023-02-02 | 4   | 4ª: 11:30 - 12:30 | Informatica | ?@docente_itp_1:nome,cognome?@docente_itp_2:nome,cognome                | Argomento svolto - Attività svolta |
    # | @docente_sostegno_1:username    | @cattedra_1A_9:id  | @classe_1A:id | 2023-02-02 | 1   | 1ª: 08:30 - 09:30 | Italiano    | ?@docente_sostegno_1:nome,cognome?@docente_curricolare_1:nome,cognome   | @lezione_1A_1:argomento,attivita   |
    # | @docente_curricolare_1:username | @cattedra_1A_1:id  | @classe_1A:id | 2023-02-04 | 1   | 1ª: 08:30 - 09:30 | Italiano    | ?@docente_sostegno_1:nome,cognome?@docente_curricolare_1:nome,cognome   | Argomento svolto - Attività svolta |
    | @docente_sostegno_1:username    | @cattedra_1A_9:id  | @classe_1A:id | 2023-02-04 | 2   | 2ª: 09:30 - 10:30 | Sostituzione    | ?@docente_sostegno_1:nome,cognome?@docente_itp_3:nome,cognome   | @lezione_1A_12:argomento,attivita   |
    | @docente_sostegno_1:username    | @cattedra_1A_9:id  | @classe_1A:id | 2023-02-04 | 3   | 3ª: 10:30 - 11:30 | @lezione_1A_13:materia.nomeBreve+ (Sostituzione)    | ?@docente_sostegno_1:nome,cognome?@docente_itp_3:nome,cognome   | @lezione_1A_13:argomento,attivita   |

Schema dello scenario: Inserisce una nuova firma di supplenza su una lezione esistente
  Dato login utente "<docente>"
  E pagina attiva "lezioni_registro_firme" con parametri:
    | cattedra | classe   | data   | vista |
    | 0        | <classe> | <data> | G     |
  Quando vai alla pagina "lezioni_registro_add" con parametri:
    | cattedra | classe   | data   | ora   |
    | 0        | <classe> | <data> | <ora> |
  E selezioni opzione "<materia>" da lista "registro_add[materia]"
  E inserisci "Argomento svolto" nel campo "registro_add[argomento]"
  E inserisci "Attività svolta" nel campo "registro_add[attivita]"
  E click su "Conferma"
  Allora vedi la pagina "lezioni_registro_firme"
  E vedi nella riga "<ora>" della tabella i dati:
    | ora      | materia    | docenti   | argomenti/attività | azioni            |
    | <orario> | <materia1> | <docenti> | <argomenti>        | Modifica Cancella |
  Esempi:
    | docente                         | classe           | data       | ora | orario            | materia                    | materia1                  | docenti                                                          | argomenti                          |
    | @docente_curricolare_1:username | @classe_1A:id    | 2023-02-04 | 2   | 2ª: 09:30 - 10:30 | Matematica                 | Matematica (Sostituzione) | ?@docente_itp_3:nome,cognome?@docente_curricolare_1:nome,cognome | Argomento svolto - Attività svolta |
    | @docente_curricolare_1:username | @classe_1A:id    | 2023-02-04 | 3   | 3ª: 10:30 - 11:30 | Sostituzione               | Sostituzione              | ?@docente_itp_3:nome,cognome?@docente_curricolare_1:nome,cognome | Argomento svolto - Attività svolta |
    | @docente_itp_3:username         | @classe_3CCHI:id | 2023-02-04 | 5   | 5ª: 12:30 - 13:30 | Scienze integrate: Chimica | Chimica (Sostituzione)    | ?@docente_itp_3:nome,cognome?@docente_itp_1:nome,cognome         | Argomento svolto - Attività svolta |
    | @docente_itp_3:username         | @classe_3CCHI:id | 2023-02-04 | 6   | 6ª: 13:30 - 14:30 | Sostituzione               | Sostituzione              | ?@docente_itp_3:nome,cognome?@docente_itp_1:nome,cognome         | Argomento svolto - Attività svolta |


################################################################################
# Bisogna controllare l'inserimento di una lezione multiclasse

Schema dello scenario: Inserisce una nuova lezione di supplenza in contemporanea su un'altra classe
  Dato login utente "<docente>"
  E pagina attiva "lezioni_registro_firme" con parametri:
    | cattedra | classe   | data   | vista |
    | 0        | <classe> | <data> | G     |
  Quando vai alla pagina "lezioni_registro_add" con parametri:
    | cattedra | classe   | data   | ora   |
    | 0        | <classe> | <data> | <ora> |
  E selezioni opzione "<tipo>" da pulsanti radio "registro_add[tipoSostituzione]"
  E inserisci "Argomento svolto" nel campo "registro_add[argomento]"
  E inserisci "Attività svolta" nel campo "registro_add[attivita]"
  E click su "Conferma"
  Allora vedi la pagina "lezioni_registro_firme"
  E vedi nella riga "<ora>" della tabella i dati:
    | ora      | materia   | docenti   | argomenti/attività | azioni            |
    | <orario> | <materia> | <docenti> | <argomenti>        | Modifica Cancella |
  Esempi:
    | docente                 | classe        | data       | tipo            | ora | orario            | materia                | docenti                     | argomenti                          |
    | @docente_itp_3:username | @classe_2A:id | 2023-02-04 | Tutta la classe | 2   | 2ª: 09:30 - 10:30 | Sostituzione           | @docente_itp_3:nome,cognome | Argomento svolto - Attività svolta |
    | @docente_itp_3:username | @classe_2A:id | 2023-02-04 | Tutta la classe | 3   | 3ª: 10:30 - 11:30 | Inglese (Sostituzione) | @docente_itp_3:nome,cognome | Argomento svolto - Attività svolta |
    | @docente_itp_3:username | @classe_2A:id | 2023-02-04 | Gruppo N.A.     | 4   | 4ª: 11:30 - 12:30 | Sostituzione           | @docente_itp_3:nome,cognome | Argomento svolto - Attività svolta |
    | @docente_itp_1:username | @classe_2A:id | 2023-02-04 | Tutta la classe | 5   | 5ª: 12:30 - 13:30 | Sostituzione           | @docente_itp_1:nome,cognome | Argomento svolto - Attività svolta |
    | @docente_itp_1:username | @classe_2A:id | 2023-02-04 | Tutta la classe | 6   | 6ª: 13:30 - 14:30 | Chimica (Sostituzione) | @docente_itp_1:nome,cognome | Argomento svolto - Attività svolta |
