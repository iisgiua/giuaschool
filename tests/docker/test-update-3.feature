# language: it

Funzionalità: Smoke test
  Per eseguire un test delle funzionalità generali
  Come utente pubblico o dei ruoli previsti
  Bisogna poter caricare le principali pagine pubbliche
  Bisogna poter effettuare il login per tutti i ruoli previsti
  Bisogna poter effettuare un download
  Bisogna poter effettuare un upload
  Bisogna poter inserire un voto
  Utilizzando "_documentiFixtures.yml"


################################################################################
# Bisogna poter caricare le principali pagine pubbliche

Schema dello scenario: carica una pagina pubblica
  Quando vai alla pagina "<pagina>"
  Allora vedi la pagina "<pagina>"
  E la sezione "<sezione>" contiene "<testo>"
  Esempi:
    | pagina         | sezione                | testo                                 |
    | info_privacy   | #gs-main h1            | /Informativa sulla Privacy/           |
    | info_credits   | #gs-main h1            | /Credits/                             |
    | login_recovery | #gs-main .panel-title  | /Recupero della password di accesso/  |


################################################################################
# Bisogna poter effettuare il login per tutti i ruoli previsti

Schema dello scenario: effettua il login con il ruolo indicato
  Quando login utente con ruolo esatto "<ruolo>"
  Allora vedi la pagina "login_home"
  E la sezione "<sezione>" contiene "<testo>"
  Esempi:
    | ruolo          | sezione                    | testo                                 |
    | Amministratore | #gs-main .panel-title span | #logged:username+ +(Amministratore)   |
    | Ata            | #gs-main .panel-title span | #logged:username+ +(Personale ATA)    |
    | Docente        | #gs-main .panel-title span | #logged:username+ +(Docente)          |
    | Staff          | #gs-main .panel-title span | #logged:username+ +(Staff)            |
    | Preside        | #gs-main .panel-title span | #logged:username+ +(Preside)          |
    | Alunno         | #gs-main .panel-title span | #logged:username+ +(Studente)         |
    | Genitore       | #gs-main .panel-title span | #logged:username+ +(Genitore)         |


################################################################################
# Bisogna poter effettuare un download

Scenario: scarica piano di lavoro inserito
  Dato login utente con ruolo esatto "Docente"
  E modifica istanze di tipo "Cattedra":
    | docente | #attiva |
    | #logged | no      |
  E ricerca istanze di tipo "Materia":
    | id  | nome        |
    | $m1 | Informatica |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $cl1 | 2    | B       |
  E istanze di tipo "Cattedra":
    | id  | docente | attiva | materia | classe | tipo |
    | $c1 | #logged | si     | $m1     | $cl1   | N    |
  E istanze di tipo "Documento":
    | id  | classe | materia | docente | tipo |
    | $d1 | $cl1   | $m1     | #logged | L    |
  E copia file "tests/data/documento-xlsx.xlsx" in "FILES/archivio/classi/2B/documento-xlsx.xlsx"
  Quando pagina attiva "documenti_piani"
  E click su "Documento Excel"
  Allora file scaricato con nome "documento-excel-versione-1.xlsx" e dimensione "66812"


################################################################################
# Bisogna poter effettuare un upload

Scenario: inserisce piano di lavoro e lo visualizza su lista cattedre
  Dato login utente con ruolo esatto "Docente"
  E modifica istanze di tipo "Cattedra":
    | docente | #attiva |
    | #logged | no      |
  E ricerca istanze di tipo "Materia":
    | id  | nome        |
    | $m1 | Informatica |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $cl1 | 1    | B       |
  E istanze di tipo "Cattedra":
    | id  | docente | attiva | materia | classe | tipo |
    | $c1 | #logged | si     | $m1     | $cl1   | N    |
  Quando pagina attiva "documenti_piani_add" con parametri:
    | classe  | materia |
    | $cl1:id | $m1:id  |
  E alleghi file "documento-pdf.pdf" a dropzone
  E premi pulsante "Conferma"
  Allora vedi la pagina "documenti_piani"
  E vedi la tabella:
    | classe e materia                                 | documento                          | azione   |
    | $c1:classe,classe.corso,classe.sede,materia.nome | /Piano di lavoro.*1B.*Informatica/ | Cancella |
  E vedi file "archivio/classi/1B/PIANO-1B-INFORMATICA.pdf" di dimensione "61514"


################################################################################
# Bisogna poter inserire un voto

Schema dello scenario: Inserisce e memorizza le proposte di voto
  Dato login utente con ruolo esatto "Docente"
  E modifica istanze di tipo "Cattedra":
    | docente | #attiva |
    | #logged | no      |
  E modifica istanze di tipo "DefinizioneScrutinio":
    | #periodo |
    | -        |
  E creazione istanze di tipo "DefinizioneScrutinio":
    | id   | periodo   | data   | dataProposte |
    | $ds1 | <periodo> | #dtm() | #dtm()       |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $cl1 | 4    | A       |
    | $cl2 | 5    | A       |
  E ricerca istanze di tipo "Materia":
    | id  | tipo           |
    | $m1 | <tipo_materia> |
  E istanze di tipo "Cattedra":
    | id  | docente | classe | materia | attiva | tipo |
    | $c1 | #logged | $cl1   | $m1     | si     | N    |
  E modifica istanze di tipo "Alunno":
    | classe | #classe |
    | $cl1   | $cl2    |
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
  Esempi:
    | periodo | tipo_materia | posizioni | voto          |
    | P       | N            | -2        | 4             |
    | P       | R            | 0         | Sufficiente   |
    | P       | E            | 2         | 8             |
    | F       | N            | 2         | 8             |
    | F       | R            | 1         | Discreto      |
    | F       | E            | -2        | 4             |
