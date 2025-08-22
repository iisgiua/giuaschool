# language: it

Funzionalità: Cancellazione di un documento inserito in precedenza
  Per cancellare un documento esistente
  Come utente docente
  Bisogna controllare prerequisiti per cancellazione di documento
  Bisogna poter cancellare un documento esistente e ritornare alla pagina di gestione
  Bisogna controllare accesso a pagina
  Utilizzando "_documentiFixtures.yml"


Contesto: login docente senza cattedre
	Dato login utente con ruolo esatto "Docente"
  E modifica istanze di tipo "Cattedra":
    | docente | #attiva |
    | #logged | no      |


################################################################################
# Bisogna controllare prerequisiti per cancellazione di documento

Scenario: visualizza errore per pagina cancellazione di documento non esistente
  Quando vai alla pagina "documenti_delete" con parametri:
    | documento |
    | 12345     |
  Allora vedi errore pagina "404"

Scenario: visualizza errore per cancellazione piano di lavoro di cattedra inesistente
  Data ricerca istanze di tipo "Materia":
    | id  | nome        |
    | $m1 | Informatica |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $cl1 | 1    | B       |
  E modifica istanze di tipo "Cattedra":
    | classe  | materia | #attiva |
    | $cl1    | $m1     | no      |
  E istanze di tipo "Documento":
    | id  | classe | materia | autore | tipo |
    | $d1 | $cl1   | $m1     | #other | L    |
  Quando vai alla pagina "documenti_delete" con parametri:
    | documento |
    | $d1:id    |
  Allora vedi errore pagina "404"

Scenario: visualizza errore per cancellazione piano di lavoro di cattedra altrui
  Data ricerca istanze di tipo "Materia":
    | id  | nome        |
    | $m1 | Informatica |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $cl1 | 1    | B       |
  E istanze di tipo "Cattedra":
    | id  | docente | attiva | classe | materia | tipo |
    | $c1 | #other  | si     | $cl1   | $m1     | N    |
  E istanze di tipo "Documento":
    | id  | classe | materia | autore | tipo |
    | $d1 | $cl1   | $m1     | #other | L    |
  Quando vai alla pagina "documenti_delete" con parametri:
    | documento |
    | $d1:id    |
  Allora vedi errore pagina "404"

Scenario: visualizza errore per cancellazione programma di cattedra inesistente
  Data ricerca istanze di tipo "Materia":
    | id  | nome        |
    | $m1 | Informatica |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $cl1 | 1    | B       |
  E modifica istanze di tipo "Cattedra":
    | classe  | materia | #attiva |
    | $cl1    | $m1     | no      |
  E istanze di tipo "Documento":
    | id  | classe | materia | autore | tipo |
    | $d1 | $cl1   | $m1     | #other | P    |
  Quando vai alla pagina "documenti_delete" con parametri:
    | documento |
    | $d1:id    |
  Allora vedi errore pagina "404"

Scenario: visualizza errore per cancellazione programma di cattedra altrui
  Data ricerca istanze di tipo "Materia":
    | id  | nome        |
    | $m1 | Informatica |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $cl1 | 1    | B       |
  E istanze di tipo "Cattedra":
    | id  | docente | attiva | classe | materia | tipo |
    | $c1 | #other  | si     | $cl1   | $m1     | N    |
  E istanze di tipo "Documento":
    | id  | classe | materia | autore | tipo |
    | $d1 | $cl1   | $m1     | #other | P    |
  Quando vai alla pagina "documenti_delete" con parametri:
    | documento |
    | $d1:id    |
  Allora vedi errore pagina "404"

Scenario: visualizza errore per cancellazione relazione di cattedra inesistente
  Data ricerca istanze di tipo "Materia":
    | id  | nome        |
    | $m1 | Informatica |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $cl1 | 1    | B       |
  E modifica istanze di tipo "Cattedra":
    | classe  | materia | #attiva |
    | $cl1    | $m1     | no      |
  E istanze di tipo "Documento":
    | id  | classe | materia | autore | tipo |
    | $d1 | $cl1   | $m1     | #other | R    |
  Quando vai alla pagina "documenti_delete" con parametri:
    | documento |
    | $d1:id    |
  Allora vedi errore pagina "404"

Scenario: visualizza errore per cancellazione relazione di cattedra altrui
  Data ricerca istanze di tipo "Materia":
    | id  | nome        |
    | $m1 | Informatica |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $cl1 | 1    | B       |
  E istanze di tipo "Cattedra":
    | id  | docente | attiva | classe | materia | tipo |
    | $c1 | #other  | si     | $cl1   | $m1     | N    |
  E istanze di tipo "Documento":
    | id  | classe | materia | autore | tipo |
    | $d1 | $cl1   | $m1     | #other | R    |
  Quando vai alla pagina "documenti_delete" con parametri:
    | documento |
    | $d1:id    |
  Allora vedi errore pagina "404"

Scenario: visualizza errore per cancellazione documento del 15 maggio di cattedra inesistente
  Data modifica istanze di tipo "Classe":
    | anno | sezione | #coordinatore |
    | 5    | A       | null          |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $c1  | 5    | A       |
  E istanze di tipo "Documento":
    | id  | classe | autore | tipo |
    | $d1 | $c1    | #other | M    |
  Quando vai alla pagina "documenti_delete" con parametri:
    | documento |
    | $d1:id    |
  Allora vedi errore pagina "404"

Scenario: visualizza errore per cancellazione documento del 15 maggio di cattedra altrui
  Data modifica istanze di tipo "Classe":
    | anno | sezione | #coordinatore |
    | 5    | A       | #other        |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $c1  | 5    | A       |
  E istanze di tipo "Documento":
    | id  | classe | autore | tipo |
    | $d1 | $c1    | #other | M    |
  Quando vai alla pagina "documenti_delete" con parametri:
    | documento |
    | $d1:id    |
  Allora vedi errore pagina "404"

Scenario: visualizza errore per cancellazione documento BES da non responsabile BES
  Data modifica utente connesso:
    | responsabileBes |
    | no              |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $cl1 | 3    | A       |
  E ricerca istanze di tipo "Alunno":
    | id  | classe | abilitato |
    | $a1 | $cl1   | si        |
  E istanze di tipo "Documento":
    | id  | classe | alunno | tipo | autore |
    | $d1 | $cl1   | $a1    | B    | #other |
  Quando vai alla pagina "documenti_delete" con parametri:
    | documento |
    | $d1:id    |
  Allora vedi errore pagina "404"

Scenario: visualizza errore per cancellazione documento BES da responsabile BES di altra sede
  Data ricerca istanze di tipo "Sede":
    | id  | citta     |
    | $s1 | Bergamo   |
    | $s2 | Grossetto |
  E modifica utente connesso:
    | responsabileBes | responsabileBesSede |
    | si              | $s1                 |
  E ricerca istanze di tipo "Classe":
    | id   | sede |
    | $cl1 | $s2  |
  E ricerca istanze di tipo "Alunno":
    | id  | classe | abilitato |
    | $a1 | $cl1   | si        |
  E istanze di tipo "Documento":
    | id  | classe | alunno | tipo | autore |
    | $d1 | $cl1   | $a1    | B    | #other |
  Quando vai alla pagina "documenti_delete" con parametri:
    | documento |
    | $d1:id    |
  Allora vedi errore pagina "404"


################################################################################
# Bisogna poter cancellare un documento esistente e ritornare alla pagina di gestione

Scenario: cancella piano di lavoro inserito in precedenza e torna alla visualizzazione cattedre
  Data ricerca istanze di tipo "Materia":
    | id  | nome        |
    | $m1 | Informatica |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $cl1 | 1    | B       |
  E istanze di tipo "Cattedra":
    | id  | docente | attiva | materia | classe | tipo |
    | $c1 | #logged | si     | $m1     | $cl1   | N    |
  E istanze di tipo "Documento":
    | id  | classe | materia | autore  | tipo |
    | $d1 | $cl1   | $m1     | #logged | L    |
  E copia file "tests/data/documento-xlsx.xlsx" in "FILES/archivio/classi/1B/documento-xlsx.xlsx"
  Quando pagina attiva "documenti_delete" con parametri:
    | documento |
    | $d1:id    |
  Allora pagina attiva "documenti_piani"
  E vedi la tabella:
    | classe e materia                                 | documento              | azione   |
    | $c1:classe,classe.corso,classe.sede,materia.nome | Documento non inserito | Aggiungi |

Scenario: inserisce e poi cancella piano di lavoro
  Data ricerca istanze di tipo "Materia":
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
  E pagina attiva "documenti_piani"
  E premi pulsante "Cancella"
  E premi pulsante "Continua"
  Allora pagina attiva "documenti_piani"
  E vedi la tabella:
    | classe e materia                                 | documento              | azione   |
    | $c1:classe,classe.corso,classe.sede,materia.nome | Documento non inserito | Aggiungi |

Scenario: cancella programma inserito in precedenza e torna alla visualizzazione cattedre
  Data ricerca istanze di tipo "Materia":
    | id  | nome        |
    | $m1 | Informatica |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $cl1 | 1    | B       |
  E istanze di tipo "Cattedra":
    | id  | docente | attiva | materia | classe | tipo |
    | $c1 | #logged | si     | $m1     | $cl1   | N    |
  E istanze di tipo "Documento":
    | id  | classe | materia | autore  | tipo |
    | $d1 | $cl1   | $m1     | #logged | P    |
  E copia file "tests/data/documento-xlsx.xlsx" in "FILES/archivio/classi/1B/documento-xlsx.xlsx"
  Quando pagina attiva "documenti_delete" con parametri:
    | documento |
    | $d1:id    |
  Allora pagina attiva "documenti_programmi"
  E vedi la tabella:
    | classe e materia                                 | documento              | azione   |
    | $c1:classe,classe.corso,classe.sede,materia.nome | Documento non inserito | Aggiungi |

Scenario: inserisce e poi cancella programma
  Data ricerca istanze di tipo "Materia":
    | id  | nome        |
    | $m1 | Informatica |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $cl1 | 1    | B       |
  E istanze di tipo "Cattedra":
    | id  | docente | attiva | materia | classe | tipo |
    | $c1 | #logged | si     | $m1     | $cl1   | N    |
  Quando pagina attiva "documenti_programmi_add" con parametri:
    | classe  | materia |
    | $cl1:id | $m1:id  |
  E alleghi file "documento-pdf.pdf" a dropzone
  E premi pulsante "Conferma"
  E pagina attiva "documenti_programmi"
  E premi pulsante "Cancella"
  E premi pulsante "Continua"
  Allora pagina attiva "documenti_programmi"
  E vedi la tabella:
    | classe e materia                                 | documento              | azione   |
    | $c1:classe,classe.corso,classe.sede,materia.nome | Documento non inserito | Aggiungi |

Scenario: cancella relazione inserita in precedenza e torna alla visualizzazione cattedre
  Data ricerca istanze di tipo "Materia":
    | id  | nome        |
    | $m1 | Informatica |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $cl1 | 1    | B       |
  E istanze di tipo "Cattedra":
    | id  | docente | attiva | materia | classe | tipo |
    | $c1 | #logged | si     | $m1     | $cl1   | N    |
  E istanze di tipo "Documento":
    | id  | classe | materia | autore  | tipo |
    | $d1 | $cl1   | $m1     | #logged | R    |
  E copia file "tests/data/documento-xlsx.xlsx" in "FILES/archivio/classi/1B/documento-xlsx.xlsx"
  Quando pagina attiva "documenti_delete" con parametri:
    | documento |
    | $d1:id    |
  Allora pagina attiva "documenti_relazioni"
  E vedi la tabella:
    | classe e materia                                 | documento              | azione   |
    | $c1:classe,classe.corso,classe.sede,materia.nome | Documento non inserito | Aggiungi |

Scenario: inserisce e poi cancella relazione
  Data ricerca istanze di tipo "Materia":
    | id  | nome        |
    | $m1 | Informatica |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $cl1 | 1    | B       |
  E istanze di tipo "Cattedra":
    | id  | docente | attiva | materia | classe | tipo |
    | $c1 | #logged | si     | $m1     | $cl1   | N    |
  Quando pagina attiva "documenti_relazioni_add" con parametri:
    | classe  | materia |
    | $cl1:id | $m1:id  |
  E alleghi file "documento-pdf.pdf" a dropzone
  E premi pulsante "Conferma"
  E pagina attiva "documenti_relazioni"
  E premi pulsante "Cancella"
  E premi pulsante "Continua"
  Allora pagina attiva "documenti_relazioni"
  E vedi la tabella:
    | classe e materia                                 | documento              | azione   |
    | $c1:classe,classe.corso,classe.sede,materia.nome | Documento non inserito | Aggiungi |

Scenario: cancella documento 15 maggio inserito in precedenza e torna alla visualizzazione cattedre
  Data modifica istanze di tipo "Classe":
    | anno | sezione | #coordinatore |
    | 5    | A       | #logged       |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $c1  | 5    | A       |
  E istanze di tipo "Documento":
    | id  | classe | autore  | tipo |
    | $d1 | $c1    | #logged | M    |
  E copia file "tests/data/documento-xlsx.xlsx" in "FILES/archivio/classi/5A/documento-xlsx.xlsx"
  Quando vai alla pagina "documenti_delete" con parametri:
    | documento |
    | $d1:id    |
  Allora pagina attiva "documenti_maggio"
  E vedi la tabella:
    | classe                      | documento              | azione   |
    | $c1:anno,sezione,corso,sede | Documento non inserito | Aggiungi |

Scenario: inserisce e poi cancella documento del 15 maggio
  Data modifica istanze di tipo "Classe":
    | anno | sezione | #coordinatore |
    | 5    | A       | #logged       |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $c1  | 5    | A       |
  Quando pagina attiva "documenti_maggio_add" con parametri:
    | classe |
    | $c1:id |
  E alleghi file "documento-pdf.pdf" a dropzone
  E premi pulsante "Conferma"
  E pagina attiva "documenti_maggio"
  E premi pulsante "Cancella"
  E premi pulsante "Continua"
  Allora pagina attiva "documenti_maggio"
  E vedi la tabella:
    | classe                      | documento              | azione   |
    | $c1:anno,sezione,corso,sede | Documento non inserito | Aggiungi |

Schema dello scenario: cancella documento BES inserito in precedenza e torna alla pagina di inserimento
  E modifica utente connesso:
    | responsabileBes | responsabileBesSede |
    | si              | null                |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $cl1 | 3    | A       |
  E ricerca istanze di tipo "Alunno":
    | id  | classe | abilitato |
    | $a1 | $cl1   | si        |
  E istanze di tipo "Documento":
    | id  | classe | alunno | tipo   |
    | $d1 | $cl1   | $a1    | <tipo> |
  Quando vai alla pagina "documenti_delete" con parametri:
    | documento |
    | $d1:id    |
  Allora pagina attiva "documenti_bes"
  E non vedi la tabella:
    | classe | alunno | documento | azione |
  Ma la sezione "#gs-main .alert" contiene "/Non sono presenti documenti/i"
  E pulsante "Aggiungi" attivo
  Esempi:
    | tipo |
    | B    |
    | C    |
    | H    |
    | D    |

Schema dello scenario: inserisce e poi cancella documento BES
  Data modifica utente connesso:
    | responsabileBes | responsabileBesSede |
    | si              | null                |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $cl1 | 3    | A       |
  E ricerca istanze di tipo "Alunno":
    | id  | classe |
    | $a1 | $cl1   |
  Quando pagina attiva "documenti_bes_add"
  E selezioni opzione "3A" da lista "documento_classe"
  E selezioni opzione "<alunno>" da pulsanti radio "documento_alunnoIndividuale"
  E selezioni opzione "<tipo>" da lista "documento_tipo"
  E alleghi file "documento-pdf.pdf" a dropzone
  E premi pulsante "Conferma"
  E vedi la pagina "documenti_bes"
  E vedi la tabella:
    | classe | alunno           | documento | azione                     |
    | /3ª A/ | $a1:cognome,nome | <tipo>    | Aggiungi Archivia Cancella |
  E premi pulsante "Cancella"
  E premi pulsante "Continua"
  Allora pagina attiva "documenti_bes"
  E non vedi la tabella:
    | classe | alunno | documento | azione |
  E la sezione "#gs-main .alert" contiene "/Non sono presenti documenti/i"
  Esempi:
    | tipo           | alunno                 |
    | certificazione | $a1:cognome+ +$a1:nome |
    | Diagnosi       | $a1:cognome+ +$a1:nome |
    | P.E.I.         | $a1:cognome+ +$a1:nome |
    | P.D.P.         | $a1:cognome+ +$a1:nome |


################################################################################
# Bisogna controllare accesso a pagina

Scenario: accesso pagina cancellazione documenti senza utente
  Data ricerca istanze di tipo "Materia":
    | id  | nome        |
    | $m1 | Informatica |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $cl1 | 1    | B       |
  E istanze di tipo "Cattedra":
    | id  | docente | attiva | materia | classe | tipo |
    | $c1 | #logged | si     | $m1     | $cl1   | N    |
  E istanze di tipo "Documento":
    | id  | classe | materia | autore  | tipo |
    | $d1 | $cl1   | $m1     | #logged | L    |
  E logout utente
  Quando vai alla pagina "documenti_delete" con parametri:
    | documento |
    | $d1:id    |
  Allora vedi la pagina "login_form"

Schema dello scenario: accesso pagina cancellazione documenti con altri utenti
  Data ricerca istanze di tipo "Materia":
    | id  | nome        |
    | $m1 | Informatica |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $cl1 | 1    | B       |
  E istanze di tipo "Cattedra":
    | id  | docente | attiva | materia | classe | tipo |
    | $c1 | #logged | si     | $m1     | $cl1   | N    |
  E istanze di tipo "Documento":
    | id  | classe | materia | autore  | tipo |
    | $d1 | $cl1   | $m1     | #logged | L    |
  E logout utente
  E login utente con ruolo esatto <ruolo>
  Quando vai alla pagina "documenti_delete" con parametri:
    | documento |
    | $d1:id    |
  Allora vedi errore pagina "403"
  Esempi:
    | ruolo          |
    | Amministratore |
    | Ata            |
    | Genitore       |
    | Alunno         |
    | Utente         |
