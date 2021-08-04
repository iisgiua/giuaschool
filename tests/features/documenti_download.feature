# language: it

Funzionalità:
  Per scaricare un documento inserito
  Come utente qualsiasi
  Bisogna controllare prerequisiti per lettura di documento
  Bisogna poter scaricare un documento esistente
  Bisogna controllare accesso a pagina


################################################################################
# Bisogna controllare prerequisiti per lettura di documento

Scenario: visualizza errore per scaricamento documento non esistente
  Dato login utente con ruolo "Utente"
  Quando vai alla pagina "documenti_download" con parametri:
    | nomeParam | valoreParam |
    | documento | 12345       |
  Allora vedi errore pagina 404

Scenario: visualizza errore per scaricamento piano di lavoro senza permesso di lettura
  Dato login utente con ruolo esatto "Docente"
  E modifica istanze di tipo "Cattedra":
    | docente | #attiva |
    | #logged | no      |
  E ricerca istanze di tipo "Materia":
    | id  | nome        |
    | $m1 | Informatica |
  E istanze di tipo "Cattedra":
    | id  | docente | attiva | materia | tipo |
    | $c1 | #other  | si     | $m1     | N    |
  E crea istanze di tipo "ListaDestinatari":
    | id  |
    | $ld |
  E istanze di tipo "Documento":
    | id  | classe     | materia     | docente | tipo | ListaDestinatari |
    | $d1 | $c1:classe | $c1:materia | #other  | L    | $ld              |
  Quando vai alla pagina "documenti_download" con parametri:
    | nomeParam | valoreParam |
    | documento | $d1:id      |
  Allora vedi errore pagina 404

Scenario: visualizza errore per scaricamento programma senza permesso di lettura
  Dato login utente con ruolo esatto "Docente"
  E modifica istanze di tipo "Cattedra":
    | docente | #attiva |
    | #logged | no      |
  E ricerca istanze di tipo "Materia":
    | id  | nome        |
    | $m1 | Informatica |
  E istanze di tipo "Cattedra":
    | id  | docente | attiva | materia | tipo |
    | $c1 | #other  | si     | $m1     | N    |
  E crea istanze di tipo "ListaDestinatari":
    | id  |
    | $ld |
  E istanze di tipo "Documento":
    | id  | classe     | materia     | docente | tipo | ListaDestinatari |
    | $d1 | $c1:classe | $c1:materia | #other  | P    | $ld              |
  Quando vai alla pagina "documenti_download" con parametri:
    | nomeParam | valoreParam |
    | documento | $d1:id      |
  Allora vedi errore pagina 404

Scenario: visualizza errore per scaricamento relazione senza permesso di lettura
  Dato login utente con ruolo esatto "Docente"
  E modifica istanze di tipo "Cattedra":
    | docente | #attiva |
    | #logged | no      |
  E ricerca istanze di tipo "Materia":
    | id  | nome        |
    | $m1 | Informatica |
  E istanze di tipo "Cattedra":
    | id  | docente | attiva | materia | tipo |
    | $c1 | #other  | si     | $m1     | N    |
  E crea istanze di tipo "ListaDestinatari":
    | id  |
    | $ld |
  E istanze di tipo "Documento":
    | id  | classe     | materia     | docente | tipo | ListaDestinatari |
    | $d1 | $c1:classe | $c1:materia | #other  | R    | $ld              |
  Quando vai alla pagina "documenti_download" con parametri:
    | nomeParam | valoreParam |
    | documento | $d1:id      |
  Allora vedi errore pagina 404

Scenario: visualizza errore per scaricamento documento del 15 maggio senza permesso di lettura
  Dato login utente con ruolo esatto "Docente"
  E modifica istanze di tipo "Cattedra":
    | docente | #attiva |
    | #logged | no      |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $c1  | 5    | A       |
  E modifica istanze di tipo "Classe":
    | coordinatore | anno | sezione | #coordinatore |
    | #logged      |      |         | null          |
    |              | 5    | A       | #other        |
  E crea istanze di tipo "ListaDestinatari":
    | id  |
    | $ld |
  E istanze di tipo "Documento":
    | id  | classe     | docente | tipo | ListaDestinatari |
    | $d1 | $c1        | #other  | M    | $ld              |
  Quando vai alla pagina "documenti_download" con parametri:
    | nomeParam | valoreParam |
    | documento | $d1:id      |
  Allora vedi errore pagina 404


################################################################################
# Bisogna poter scaricare un documento esistente

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

Scenario: scarica programma inserito
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
    | $d1 | $cl1   | $m1     | #logged | P    |
  E copia file "tests/data/documento-xlsx.xlsx" in "FILES/archivio/classi/2B/documento-xlsx.xlsx"
  Quando pagina attiva "documenti_programmi"
  E click su "Documento Excel"
  Allora file scaricato con nome "documento-excel-versione-1.xlsx" e dimensione "66812"

Scenario: scarica relazione inserita
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
    | $d1 | $cl1   | $m1     | #logged | R    |
  E copia file "tests/data/documento-xlsx.xlsx" in "FILES/archivio/classi/2B/documento-xlsx.xlsx"
  Quando pagina attiva "documenti_relazioni"
  E click su "Documento Excel"
  Allora file scaricato con nome "documento-excel-versione-1.xlsx" e dimensione "66812"

Scenario: scarica documento del 15 maggio inserito
  Dato login utente con ruolo esatto "Docente"
  E modifica istanze di tipo "Cattedra":
    | docente | #attiva |
    | #logged | no      |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $c1  | 5    | A       |
  E modifica istanze di tipo "Classe":
    | anno | sezione | #coordinatore |
    | 5    | A       | #logged       |
  E istanze di tipo "Documento":
    | id  | classe | docente | tipo |
    | $d1 | $c1    | #logged | M    |
  E copia file "tests/data/documento-xlsx.xlsx" in "FILES/archivio/classi/5A/documento-xlsx.xlsx"
  Quando pagina attiva "documenti_maggio"
  E click su "Documento Excel"
  Allora file scaricato con nome "documento-excel-versione-1.xlsx" e dimensione "66812"


################################################################################
# Bisogna controllare accesso a pagina

Scenario: accesso pagina scaricamento documenti senza utente
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
  E logout utente
  Quando vai alla pagina "documenti_download" con parametri:
    | nomeParam | valoreParam |
    | documento | $d1:id      |
  Allora vedi pagina "login_form"
