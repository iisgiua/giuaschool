# language: it

Funzionalità: Inserimento proposte di voto per lo scrutinio
  Per inserire le proposte di voto dello scrutinio
  Come utente docente
  Bisogna inserire voti per cattedra di docente
  Bisogna controllare accesso a pagina
  Utilizzando "_testFixtures.yml"


Contesto: login docente senza cattedre
	Dato login utente con ruolo esatto "Docente"
  E modifica istanze di tipo "Cattedra":
    | docente | #attiva |
    | #logged | no      |
  E modifica istanze di tipo "DefinizioneScrutinio":
    | #periodo |
    | -        |


################################################################################
# Bisogna inserire voti per cattedra di docente

Schema dello scenario: Inserisce e memorizza i voti senza recupero per la cattedra del docente
  Data creazione istanze di tipo "DefinizioneScrutinio":
    | id   | periodo   | data   | dataProposte |
    | $ds1 | <periodo> | #dtm() | #dtm()       |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione | gruppo |
    | $cl1 | 4    | A       |        |
  E ricerca istanze di tipo "Materia":
    | id  | tipo           |
    | $m1 | <tipo_materia> |
  E istanze di tipo "Cattedra":
    | id  | docente | classe | materia | attiva | tipo |
    | $c1 | #logged | $cl1   | $m1     | si     | N    |
  E modifica istanze di tipo "Alunno":
    | classe | #abilitato | #classe |
    | $cl1   | no         | null    |
  E istanze di tipo "Alunno":
    | id   | classe | nome     | cognome | abilitato | religione |
    | $a1  | $cl1   | Mario    | Rossi   | si        | S         |
    | $a2  | $cl1   | Giuseppe | Verdi   | si        | S         |
  E pagina attiva "lezioni_scrutinio_proposte" con parametri:
    | cattedra | classe  | periodo   |
    | $c1:id   | $cl1:id | <periodo> |
  Quando premi pulsante "Aggiungi" con indice "2"
  E scorri cursore di "<posizioni>" posizioni
  E premi pulsante "Conferma"
  E vai alla pagina "login_home"
  E vai alla pagina "lezioni_scrutinio_proposte" con parametri:
    | cattedra | classe  | periodo   |
    | $c1:id   | $cl1:id | <periodo> |
  Allora la sezione "#gs-main form #gs-form-collection li:contains('Verdi Giuseppe')" contiene "Voto <voto>"
  E la sezione "#gs-main form #gs-form-collection li:contains('Verdi Giuseppe') .form-group label" non contiene "Recupero"
  E la sezione "#gs-main form #gs-form-collection li:contains('Verdi Giuseppe') .form-group label" non contiene "Argomenti"
  Esempi:
    | periodo | tipo_materia | posizioni | voto          |
    | P       | N            | 0         | 6             |
    | P       | N            | 1         | 7             |
    | P       | R            | -1        | Mediocre      |
    | P       | R            | 0         | Sufficiente   |
    | P       | R            | 1         | Discreto      |
    | P       | E            | 0         | 6             |
    | P       | E            | 1         | 7             |
    | S       | N            | 0         | 6             |
    | S       | N            | 1         | 7             |
    | S       | R            | -1        | Mediocre      |
    | S       | R            | 0         | Sufficiente   |
    | S       | R            | 1         | Discreto      |
    | S       | E            | 0         | 6             |
    | S       | E            | 1         | 7             |
    | F       | N            | 0         | 6             |
    | F       | N            | 1         | 7             |
    | F       | R            | -1        | Mediocre      |
    | F       | R            | 0         | Sufficiente   |
    | F       | R            | 1         | Discreto      |
    | F       | E            | 0         | 6             |
    | F       | E            | 1         | 7             |

Schema dello scenario: Inserisce e memorizza i voti con recupero per la cattedra del docente
  Data creazione istanze di tipo "DefinizioneScrutinio":
    | id   | periodo   | data   | dataProposte |
    | $ds1 | <periodo> | #dtm() | #dtm()       |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione | gruppo |
    | $cl1 | 4    | A       |        |
  E ricerca istanze di tipo "Materia":
    | id  | tipo           |
    | $m1 | <tipo_materia> |
  E istanze di tipo "Cattedra":
    | id  | docente | classe | materia | attiva | tipo |
    | $c1 | #logged | $cl1   | $m1     | si     | N    |
  E modifica istanze di tipo "Alunno":
    | classe | #abilitato | #classe |
    | $cl1   | no         | null    |
  E istanze di tipo "Alunno":
    | id   | classe | nome     | cognome | abilitato |
    | $a1  | $cl1   | Mario    | Rossi   | si        |
    | $a2  | $cl1   | Giuseppe | Verdi   | si        |
  E pagina attiva "lezioni_scrutinio_proposte" con parametri:
    | cattedra | classe  | periodo   |
    | $c1:id   | $cl1:id | <periodo> |
  Quando premi pulsante "Aggiungi" con indice "2"
  E scorri cursore di "<posizioni>" posizioni
  E selezioni opzione "<recupero>" da lista "Recupero"
  E inserisci "<argomenti>" nel campo "Argomenti"
  E premi pulsante "Conferma"
  E vai alla pagina "login_home"
  E vai alla pagina "lezioni_scrutinio_proposte" con parametri:
    | cattedra | classe  | periodo   |
    | $c1:id   | $cl1:id | <periodo> |
  Allora la sezione "#gs-main form #gs-form-collection li:contains('Verdi Giuseppe')" contiene "Voto <voto>"
  E il campo "Recupero" contiene "<recupero_val>"
  E il campo "Argomenti" contiene "<argomenti>"
  Esempi:
    | periodo | tipo_materia | posizioni | voto | recupero            | recupero_val | argomenti           |
    | P       | N            | -1        | 5    | Sportello didattico | S            | Qualcosina.         |
    | P       | E            | -1        | 5    | Pausa didattica     | P            | Da recuperare       |
    | S       | N            | -1        | 5    | Sportello didattico | S            | Qualcosina.         |
    | S       | E            | -1        | 5    | Pausa didattica     | P            | Da recuperare       |
    | F       | N            | -1        | 5    | Studio individuale  | A            | Qualcosina.         |
    | F       | E            | -1        | 5    | Corso di recupero   | C            | Da recuperare       |

Schema dello scenario: Modifica voti esistenti per la cattedra del docente
  Data creazione istanze di tipo "DefinizioneScrutinio":
    | id   | periodo   | data   | dataProposte |
    | $ds1 | <periodo> | #dtm() | #dtm()       |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione | gruppo |
    | $cl1 | 4    | A       |        |
  E ricerca istanze di tipo "Materia":
    | id  | tipo           |
    | $m1 | <tipo_materia> |
  E istanze di tipo "Cattedra":
    | id  | docente | classe | materia | attiva | tipo |
    | $c1 | #logged | $cl1   | $m1     | si     | N    |
  E modifica istanze di tipo "Alunno":
    | classe | #abilitato | #classe |
    | $cl1   | no         | null    |
  E istanze di tipo "Alunno":
    | id   | classe | nome     | cognome | abilitato | religione |
    | $a1  | $cl1   | Mario    | Rossi   | si        | S         |
    | $a2  | $cl1   | Giuseppe | Verdi   | si        | S         |
  E creazione istanze di tipo "PropostaVoto":
    | id   | alunno | classe | materia | docente | periodo   | unico  |
    | $pv1 | $a2    | $cl1   | $m1     | #logged | <periodo> | <voto> |
  E pagina attiva "lezioni_scrutinio_proposte" con parametri:
    | cattedra | classe  | periodo   |
    | $c1:id   | $cl1:id | <periodo> |
  Quando scorri cursore di "<posizioni>" posizioni
  E premi pulsante "Conferma"
  E vai alla pagina "login_home"
  E vai alla pagina "lezioni_scrutinio_proposte" con parametri:
    | cattedra | classe  | periodo   |
    | $c1:id   | $cl1:id | <periodo> |
  Allora la sezione "#gs-main form #gs-form-collection li:contains('Verdi Giuseppe')" contiene "Voto <voto_nuovo>"
  Esempi:
    | periodo | tipo_materia | posizioni | voto | voto_nuovo  |
    | P       | N            | -1        | 6    | 5           |
    | P       | N            | 1         | 8    | 9           |
    | P       | R            | -1        | 23   | Mediocre    |
    | P       | R            | 1         | 23   | Discreto    |
    | P       | E            | -1        | 6    | 5           |
    | P       | E            | 1         | 8    | 9           |
    | S       | N            | -1        | 6    | 5           |
    | S       | N            | 1         | 8    | 9           |
    | S       | R            | -1        | 23   | Mediocre    |
    | S       | R            | 1         | 23   | Discreto    |
    | S       | E            | -1        | 6    | 5           |
    | S       | E            | 1         | 8    | 9           |
    | F       | N            | -1        | 6    | 5           |
    | F       | N            | 1         | 8    | 9           |
    | F       | R            | -1        | 23   | Mediocre    |
    | F       | R            | 1         | 23   | Discreto    |
    | F       | E            | -1        | 6    | 5           |
    | F       | E            | 1         | 8    | 9           |

Schema dello scenario: Modifica dati recupero esistenti per la cattedra del docente
  Data creazione istanze di tipo "DefinizioneScrutinio":
    | id   | periodo   | data   | dataProposte |
    | $ds1 | <periodo> | #dtm() | #dtm()       |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione | gruppo |
    | $cl1 | 4    | A       |        |
  E ricerca istanze di tipo "Materia":
    | id  | tipo           |
    | $m1 | <tipo_materia> |
  E istanze di tipo "Cattedra":
    | id  | docente | classe | materia | attiva | tipo |
    | $c1 | #logged | $cl1   | $m1     | si     | N    |
  E modifica istanze di tipo "Alunno":
    | classe | #abilitato | #classe |
    | $cl1   | no         | null    |
  E istanze di tipo "Alunno":
    | id   | classe | nome     | cognome | abilitato |
    | $a1  | $cl1   | Mario    | Rossi   | si        |
    | $a2  | $cl1   | Giuseppe | Verdi   | si        |
  E creazione istanze di tipo "PropostaVoto":
    | id   | alunno | classe | materia | docente | periodo   | unico  | recupero | debito |
    | $pv1 | $a2    | $cl1   | $m1     | #logged | <periodo> | <voto> | C        | Tutto. |
  E pagina attiva "lezioni_scrutinio_proposte" con parametri:
    | cattedra | classe  | periodo   |
    | $c1:id   | $cl1:id | <periodo> |
  Quando selezioni opzione "<recupero>" da lista "Recupero"
  E inserisci "<argomenti>" nel campo "Argomenti"
  E premi pulsante "Conferma"
  E vai alla pagina "login_home"
  E vai alla pagina "lezioni_scrutinio_proposte" con parametri:
    | cattedra | classe  | periodo   |
    | $c1:id   | $cl1:id | <periodo> |
  Allora la sezione "#gs-main form #gs-form-collection li:contains('Verdi Giuseppe')" contiene "Voto <voto>"
  E il campo "Recupero" contiene "<recupero_val>"
  E il campo "Argomenti" contiene "<argomenti>"
  Esempi:
    | periodo | tipo_materia | voto | recupero           | recupero_val | argomenti              |
    | P       | N            | 5    | Studio individuale | A            | Da recuperare qualcosa |
    | P       | E            | 5    | Studio individuale | A            | Da recuperare qualcosa |
    | S       | N            | 5    | Studio individuale | A            | Da recuperare qualcosa |
    | S       | E            | 5    | Studio individuale | A            | Da recuperare qualcosa |
    | F       | N            | 5    | Studio individuale | A            | Da recuperare qualcosa |
    | F       | E            | 5    | Studio individuale | A            | Da recuperare qualcosa |
