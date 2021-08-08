# language: it

Funzionalità: Visualizzazione documenti dei docenti da parte dello staff
  Per visualizzare la lista dei documenti dei docenti presenti e mancanti
  Come utente staff
  Bisogna leggere cattedre e tipo di documento e mostrare lista
  Bisogna controllare filtro di visualizzazione
  Bisogna controllare memorizzazione dati di sessione
  Bisogna controllare accesso a pagina


Contesto: login staff senza nessuna cattedra
	Dato login utente con ruolo esatto "Staff"
  E modifica istanze di tipo "Cattedra":
    | attiva | #attiva |
    | si     | no      |


################################################################################
# Bisogna leggere cattedre e tipo di documento e mostrare lista

Scenario: visualizza lista cattedre corretta per i piani di lavoro
  Data ricerca istanze di tipo "Materia":
    | id  | tipo | nome        |
    | $m1 | R    |             |
    | $m2 | E    |             |
    | $m3 | S    |             |
    | $m4 |      | Informatica |
    | $m5 |      | Storia      |
    | $m6 |      | Matematica  |
  E ricerca istanze di tipo "Classe":
    | id  | anno | sezione |
    | $cl1 | 1    | B       |
    | $cl2 | 3    | B       |
    | $cl3 | 5    | B       |
  E istanze di tipo "Cattedra":
    | id  | attiva | classe | materia | tipo | docente |
    | $c1 | si     | $cl1   | $m1     | A    |         |
    | $c2 | si     | $cl2   | $m4     | I    | #logged |
    | $c3 | si     | $cl2   | $m4     | N    | #other  |
    | $c4 | si     | $cl3   | $m6     | N    |         |
    | $c5 | si     | $cl1   | $m2     | N    |         |
    | $c6 | si     | $cl2   | $m5     | P    |         |
    | $c7 | si     | $cl1   | $m3     | N    |         |
    | $c8 | no     | $cl2   | $m6     | N    |         |
  Quando pagina attiva "documenti_docenti"
  E selezioni opzione "Tutti" da lista "documento_filtro"
  E selezioni opzione "Piani" da lista "documento_tipo"
  E selezioni opzione "Tutte" da lista "documento_classe"
  E premi pulsante "Filtra"
  Allora vedi nella tabella le colonne:
    | classe e materia | docenti | documento |
  E vedi "3" righe nella tabella
  E vedi in più righe della tabella i dati:
    | classe e materia                                      | docenti                                                           | documento              |
    | $c1:classe,classe.corso,classe.sede,materia.nomeBreve | $c1:docente.nome,docente.cognome                                  | Documento non inserito |
    | $c2:classe,classe.corso,classe.sede,materia.nomeBreve | $c2:docente.nome,docente.cognome $c3:docente.nome,docente.cognome | Documento non inserito |
    | $c4:classe,classe.corso,classe.sede,materia.nomeBreve | $c4:docente.nome,docente.cognome                                  | Documento non inserito |

Scenario: visualizza lista cattedre corretta per i programmi
  Data ricerca istanze di tipo "Materia":
    | id  | tipo | nome        |
    | $m1 | R    |             |
    | $m2 | E    |             |
    | $m3 | S    |             |
    | $m4 |      | Informatica |
    | $m5 |      | Storia      |
    | $m6 |      | Matematica  |
  E ricerca istanze di tipo "Classe":
    | id  | anno | sezione |
    | $cl1 | 1    | B       |
    | $cl2 | 3    | B       |
    | $cl3 | 5    | B       |
  E istanze di tipo "Cattedra":
    | id  | attiva | classe | materia | tipo | docente |
    | $c1 | si     | $cl1   | $m1     | A    |         |
    | $c2 | si     | $cl2   | $m4     | I    | #logged |
    | $c3 | si     | $cl2   | $m4     | N    | #other  |
    | $c4 | si     | $cl3   | $m6     | N    |         |
    | $c5 | si     | $cl1   | $m2     | N    |         |
    | $c6 | si     | $cl2   | $m5     | P    |         |
    | $c7 | si     | $cl1   | $m3     | N    |         |
    | $c8 | no     | $cl2   | $m6     | N    |         |
  Quando pagina attiva "documenti_docenti"
  E selezioni opzione "Tutti" da lista "documento_filtro"
  E selezioni opzione "Programmi" da lista "documento_tipo"
  E selezioni opzione "Tutte" da lista "documento_classe"
  E premi pulsante "Filtra"
  Allora vedi nella tabella le colonne:
    | classe e materia | docenti | documento |
  E vedi "2" righe nella tabella
  E vedi in più righe della tabella i dati:
    | classe e materia                                      | docenti                                                           | documento              |
    | $c1:classe,classe.corso,classe.sede,materia.nomeBreve | $c1:docente.nome,docente.cognome                                  | Documento non inserito |
    | $c2:classe,classe.corso,classe.sede,materia.nomeBreve | $c2:docente.nome,docente.cognome $c3:docente.nome,docente.cognome | Documento non inserito |

Scenario: visualizza lista cattedre corretta per le relazioni
  Data ricerca istanze di tipo "Materia":
    | id  | tipo | nome        |
    | $m1 | R    |             |
    | $m2 | E    |             |
    | $m3 | S    |             |
    | $m4 |      | Informatica |
    | $m5 |      | Storia      |
    | $m6 |      | Matematica  |
  E ricerca istanze di tipo "Classe":
    | id  | anno | sezione |
    | $cl1 | 1    | B       |
    | $cl2 | 3    | B       |
    | $cl3 | 5    | B       |
  E ricerca istanze di tipo "Alunno":
    | id   | classe | abilitato | sesso |
    | $a1  | $cl1   | si        |       |
    | $a2  | $cl3   | si        | M     |
    | $a3  | $cl3   | si        | F     |
  E istanze di tipo "Cattedra":
    | id   | attiva | classe | materia | tipo | docente | alunno |
    | $c1  | si     | $cl1   | $m1     | A    |         |        |
    | $c2  | si     | $cl2   | $m4     | I    | #logged |        |
    | $c3  | si     | $cl2   | $m4     | N    | #other  |        |
    | $c4  | si     | $cl3   | $m6     | N    |         |        |
    | $c5  | si     | $cl1   | $m2     | N    |         |        |
    | $c6  | si     | $cl2   | $m5     | P    |         |        |
    | $c7  | si     | $cl1   | $m3     | N    | #logged | $a1    |
    | $c8  | si     | $cl1   | $m3     | N    | #other  | $a1    |
    | $c9  | no     | $cl2   | $m6     | N    |         |        |
    | $c10 | si     | $cl3   | $m3     | N    | #other  | $a2    |
    | $c11 | si     | $cl3   | $m3     | N    | #other  | $a3    |
  Quando pagina attiva "documenti_docenti"
  E selezioni opzione "Tutti" da lista "documento_filtro"
  E selezioni opzione "Relazioni" da lista "documento_tipo"
  E selezioni opzione "Tutte" da lista "documento_classe"
  E premi pulsante "Filtra"
  Allora vedi nella tabella le colonne:
    | classe e materia | docenti | documento |
  E vedi "5" righe nella tabella
  E vedi in più righe della tabella i dati:
    | classe e materia                                                                  | docenti                                                           | documento              |
    | $c1:classe,classe.corso,classe.sede,materia.nomeBreve                             | $c1:docente.nome,docente.cognome                                  | Documento non inserito |
    | $c2:classe,classe.corso,classe.sede,materia.nomeBreve                             | $c2:docente.nome,docente.cognome $c3:docente.nome,docente.cognome | Documento non inserito |
    | $c7:classe,classe.corso,classe.sede,materia.nomeBreve,alunno.cognome,alunno.nome  | $c7:docente.nome,docente.cognome $c8:docente.nome,docente.cognome | Documento non inserito |
    | $c10:classe,classe.corso,classe.sede,materia.nomeBreve,alunno.cognome,alunno.nome | $c10:docente.nome,docente.cognome                                 | Documento non inserito |
    | $c11:classe,classe.corso,classe.sede,materia.nomeBreve,alunno.cognome,alunno.nome | $c11:docente.nome,docente.cognome                                 | Documento non inserito |

Scenario: visualizza lista cattedre corretta per i documenti del 15 maggio
  Data modifica istanze di tipo "Classe":
    | anno | sezione | #coordinatore |
    | 1    | B       | #other       |
    | 5    |         | null         |
    | 5    | B       | #other       |
    | 5    | C       | #logged      |
  E ricerca istanze di tipo "Classe":
    | id  | anno | sezione |
    | $cl1 | 1    | B      |
    | $cl2 | 5    | B      |
    | $cl3 | 5    | C      |
  E ricerca istanze di tipo "Materia":
    | id  | tipo | nome        |
    | $m1 |      | Informatica |
    | $m2 |      | Matematica  |
  E istanze di tipo "Cattedra":
    | id  | attiva | classe | materia | tipo | docente |
    | $c1 | si     | $cl1   | $m1     | N    | #other  |
    | $c2 | si     | $cl2   | $m1     | I    | #other  |
    | $c3 | si     | $cl3   | $m2     | N    | #logged |
  Quando pagina attiva "documenti_docenti"
  E selezioni opzione "Tutti" da lista "documento_filtro"
  E selezioni opzione "15 maggio" da lista "documento_tipo"
  E selezioni opzione "Tutte" da lista "documento_classe"
  E premi pulsante "Filtra"
  Allora vedi nella tabella le colonne:
    | classe | documento |
  E vedi "2" righe nella tabella
  E vedi in più righe della tabella i dati:
    | classe                              | documento              |
    | $c2:classe,classe.corso,classe.sede | Documento non inserito |
    | $c3:classe,classe.corso,classe.sede | Documento non inserito |

Schema dello scenario: visualizza lista vuota cattedre
  Quando pagina attiva "documenti_docenti"
  E selezioni opzione "Tutti" da lista "documento_filtro"
  E selezioni opzione "<tipo>" da lista "documento_tipo"
  E selezioni opzione "Tutte" da lista "documento_classe"
  E premi pulsante "Filtra"
  Allora la sezione "#gs-main" non contiene "/<table/i"
  Ma la sezione "#gs-main .alert" contiene "/Non sono presenti documenti/i"
  Esempi:
    | tipo      |
    | Piani     |
    | Programmi |
    | Relazioni |
    | 15 maggio |

Schema dello scenario: visualizza lista cattedre piani/programmi/relazioni con documenti
  Data ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $cl1 | 1    | B       |
  E ricerca istanze di tipo "Materia":
    | id  | nome        |
    | $m1 | Informatica |
  E istanze di tipo "Cattedra":
    | id  | docente | attiva | materia | classe | tipo |
    | $c1 | #other  | si     | $m1     | $cl1   | N    |
  E istanze di tipo "Documento":
    | id  | classe     | materia     | tipo   |
    | $d1 | $c1:classe | $c1:materia | <tipo> |
  Quando pagina attiva "documenti_docenti"
  E selezioni opzione "Tutti" da lista "documento_filtro"
  E selezioni opzione "<nome_tipo>" da lista "documento_tipo"
  E selezioni opzione "Tutte" da lista "documento_classe"
  E premi pulsante "Filtra"
  Allora vedi nella tabella le colonne:
    | classe e materia | docenti | documento |
  E vedi "1" riga nella tabella
  E vedi in una riga della tabella i dati:
    | classe e materia                                      | docenti                          | documento       |
    | $c1:classe,classe.corso,classe.sede,materia.nomeBreve | $c1:docente.nome,docente.cognome | Documento Excel |
  Esempi:
    | tipo | nome_tipo |
    | L    | Piani     |
    | P    | Programmi |
    | R    | Relazioni |

Scenario: visualizza lista cattedre relazioni con documenti per sostegno di docenti diversi su stesso alunno
  Data ricerca istanze di tipo "Materia":
    | id  | tipo | nome        |
    | $m1 | S    |             |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $cl1 | 1    | B       |
  E ricerca istanze di tipo "Alunno":
    | id   | classe | abilitato |
    | $a1  | $cl1   | si        |
  E istanze di tipo "Cattedra":
    | id  | attiva | classe | materia | tipo | docente | alunno |
    | $c1 | si     | $cl1   | $m1     | N    | #logged | $a1    |
    | $c2 | si     | $cl1   | $m1     | N    | #other  | $a1    |
  E istanze di tipo "Documento":
    | id  | classe | materia | alunno | docente | tipo |
    | $d1 | $cl1   | $m1     | $a1    | #logged | R    |
    | $d2 | $cl1   | $m1     | $a1    | #other  | R    |
  Quando pagina attiva "documenti_docenti"
  E selezioni opzione "Tutti" da lista "documento_filtro"
  E selezioni opzione "Relazioni" da lista "documento_tipo"
  E selezioni opzione "Tutte" da lista "documento_classe"
  E premi pulsante "Filtra"
  Allora vedi nella tabella le colonne:
    | classe e materia | docenti | documento |
  E vedi "1" riga nella tabella
  E vedi in una riga della tabella i dati:
    | classe e materia                                                                 | docenti                                                           | documento                        |
    | $c1:classe,classe.corso,classe.sede,materia.nomeBreve,alunno.cognome,alunno.nome | $c1:docente.nome,docente.cognome $c2:docente.nome,docente.cognome | /Documento PDF.*Documento Excel/ |

Scenario: visualizza lista cattedre relazioni con documenti per sostegno stesso docente su alunni diversi
  Data ricerca istanze di tipo "Materia":
    | id  | tipo | nome        |
    | $m1 | S    |             |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $cl1 | 1    | B       |
  E ricerca istanze di tipo "Alunno":
    | id   | classe | abilitato | sesso |
    | $a1  | $cl1   | si        | M     |
    | $a2  | $cl1   | si        | F     |
  E istanze di tipo "Cattedra":
    | id  | attiva | classe | materia | tipo | docente | alunno |
    | $c1 | si     | $cl1   | $m1     | N    | #other  | $a1    |
    | $c2 | si     | $cl1   | $m1     | N    | #other  | $a2    |
  E istanze di tipo "Documento":
    | id  | classe | materia | alunno | docente | tipo |
    | $d1 | $cl1   | $m1     | $a1    | #other  | R    |
    | $d2 | $cl1   | $m1     | $a2    | #other  | R    |
  Quando pagina attiva "documenti_docenti"
  E selezioni opzione "Tutti" da lista "documento_filtro"
  E selezioni opzione "Relazioni" da lista "documento_tipo"
  E selezioni opzione "Tutte" da lista "documento_classe"
  E premi pulsante "Filtra"
  Allora vedi nella tabella le colonne:
    | classe e materia | docenti | documento |
  E vedi "2" riga nella tabella
  E vedi in più righe della tabella i dati:
    | classe e materia                                                                 | docenti                          | documento       |
    | $c1:classe,classe.corso,classe.sede,materia.nomeBreve,alunno.cognome,alunno.nome | $c1:docente.nome,docente.cognome | Documento Excel |
    | $c2:classe,classe.corso,classe.sede,materia.nomeBreve,alunno.cognome,alunno.nome | $c2:docente.nome,docente.cognome | Documento PDF   |

Scenario: visualizza lista cattedre documenti del 15 maggio con documenti
  Data modifica istanze di tipo "Classe":
    | anno | sezione | #coordinatore |
    | 5    | B       | #other       |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $cl1 | 5    | B       |
  E ricerca istanze di tipo "Materia":
    | id  | nome        |
    | $m1 | Informatica |
  E istanze di tipo "Cattedra":
    | id  | docente | attiva | materia | classe | tipo |
    | $c1 | #other  | si     | $m1     | $cl1   | N    |
  E istanze di tipo "Documento":
    | id  | classe     | tipo |
    | $d1 | $c1:classe | M    |
  Quando pagina attiva "documenti_docenti"
  E selezioni opzione "Tutti" da lista "documento_filtro"
  E selezioni opzione "15 maggio" da lista "documento_tipo"
  E selezioni opzione "Tutte" da lista "documento_classe"
  E premi pulsante "Filtra"
  Allora vedi nella tabella le colonne:
    | classe | documento |
  E vedi "1" riga nella tabella
  E vedi in una riga della tabella i dati:
    | classe                              | documento       |
    | $c1:classe,classe.corso,classe.sede | Documento Excel |


################################################################################
# Bisogna controllare filtro di visualizzazione

Schema dello scenario: visualizza filtro documenti presenti/mancanti
  Data ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $cl1 | 1    | B       |
    | $cl2 | 3    | B       |
  E ricerca istanze di tipo "Materia":
    | id  | nome        |
    | $m1 | Informatica |
    | $m2 | Storia      |
  E istanze di tipo "Cattedra":
    | id  | docente | attiva | materia | classe | tipo |
    | $c1 | #other  | si     | $m1     | $cl1   | N    |
    | $c2 | #logged | si     | $m2     | $cl2   | N    |
  E istanze di tipo "Documento":
    | id  | classe     | materia     | tipo   |
    | $d1 | $c1:classe | $c1:materia | <tipo> |
  Quando pagina attiva "documenti_docenti"
  E selezioni opzione "<filtro>" da lista "documento_filtro"
  E selezioni opzione "<nome_tipo>" da lista "documento_tipo"
  E selezioni opzione "Tutte" da lista "documento_classe"
  E premi pulsante "Filtra"
  Allora vedi nella tabella le colonne:
    | classe e materia | docenti | documento |
  E vedi "1" riga nella tabella
  E vedi in una riga della tabella i dati:
    | classe e materia                                             | docenti                                 | documento   |
    | <cattedra>:classe,classe.corso,classe.sede,materia.nomeBreve | <cattedra>:docente.nome,docente.cognome | <documento> |
  Esempi:
    | tipo | nome_tipo | filtro   | documento              | cattedra |
    | L    | Piani     | presenti | Documento Excel        | $c1      |
    | L    | Piani     | mancanti | Documento non inserito | $c2      |
    | P    | Programmi | presenti | Documento Excel        | $c1      |
    | P    | Programmi | mancanti | Documento non inserito | $c2      |
    | R    | Relazioni | presenti | Documento Excel        | $c1      |
    | R    | Relazioni | mancanti | Documento non inserito | $c2      |

Schema dello scenario: visualizza filtro documenti presenti/mancanti per documento del 15 maggio
  Data modifica istanze di tipo "Classe":
    | anno | sezione | #coordinatore |
    | 5    | B       | #other        |
    | 5    | C       | #logged       |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $cl1 | 5    | B       |
    | $cl2 | 5    | C       |
  E ricerca istanze di tipo "Materia":
    | id  | nome        |
    | $m1 | Informatica |
  E istanze di tipo "Cattedra":
    | id  | docente | attiva | materia | classe | tipo |
    | $c1 | #other  | si     | $m1     | $cl1   | N    |
    | $c2 | #logged | si     | $m1     | $cl2   | N    |
  E istanze di tipo "Documento":
    | id  | classe     | tipo |
    | $d1 | $c1:classe | M    |
  Quando pagina attiva "documenti_docenti"
  E selezioni opzione "<filtro>" da lista "documento_filtro"
  E selezioni opzione "15 maggio" da lista "documento_tipo"
  E selezioni opzione "Tutte" da lista "documento_classe"
  E premi pulsante "Filtra"
  Allora vedi nella tabella le colonne:
    | classe | documento |
  E vedi "1" riga nella tabella
  E vedi in una riga della tabella i dati:
    | classe                                     | documento   |
    | <cattedra>:classe,classe.corso,classe.sede | <documento> |
  Esempi:
    | filtro   | documento              | cattedra |
    | presenti | Documento Excel        | $c1      |
    | mancanti | Documento non inserito | $c2      |

Schema dello scenario: visualizza filtro classi documenti
  Data modifica utente attuale con parametri:
    | nomeParam   | valoreParam   |
    | sede        | null           |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $cl1 | 1    | B       |
    | $cl2 | 3    | B       |
  E ricerca istanze di tipo "Materia":
    | id  | nome        |
    | $m1 | Informatica |
    | $m2 | Storia      |
  E istanze di tipo "Cattedra":
    | id  | docente | attiva | materia | classe | tipo |
    | $c1 | #other  | si     | $m1     | $cl1   | N    |
    | $c2 | #logged | si     | $m2     | $cl2   | N    |
  E istanze di tipo "Documento":
    | id  | classe     | materia     | tipo   |
    | $d1 | $c1:classe | $c1:materia | <tipo> |
  Quando pagina attiva "documenti_docenti"
  E selezioni opzione "Tutti" da lista "documento_filtro"
  E selezioni opzione "<nome_tipo>" da lista "documento_tipo"
  E selezioni opzione "<classe>" da lista "documento_classe"
  E premi pulsante "Filtra"
  Allora vedi nella tabella le colonne:
    | classe e materia | docenti | documento |
  E vedi "1" riga nella tabella
  E vedi in una riga della tabella i dati:
    | classe e materia                                             | docenti                                 | documento   |
    | <cattedra>:classe,classe.corso,classe.sede,materia.nomeBreve | <cattedra>:docente.nome,docente.cognome | <documento> |
  Esempi:
    | tipo | nome_tipo | classe  | documento              | cattedra |
    | L    | Piani     | $cl1:id | Documento Excel        | $c1      |
    | L    | Piani     | $cl2:id | Documento non inserito | $c2      |
    | P    | Programmi | $cl1:id | Documento Excel        | $c1      |
    | P    | Programmi | $cl2:id | Documento non inserito | $c2      |
    | R    | Relazioni | $cl1:id | Documento Excel        | $c1      |
    | R    | Relazioni | $cl2:id | Documento non inserito | $c2      |

Schema dello scenario: visualizza filtro classi documenti del 15 maggio
  Data modifica utente attuale con parametri:
    | nomeParam | valoreParam |
    | sede      | null         |
  E modifica istanze di tipo "Classe":
    | anno | sezione | #coordinatore |
    | 5    | A       | #other        |
    | 5    | C       | #logged       |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $cl1 | 5    | A       |
    | $cl2 | 5    | C       |
  E ricerca istanze di tipo "Materia":
    | id  | nome        |
    | $m1 | Informatica |
  E istanze di tipo "Cattedra":
    | id  | docente | attiva | materia | classe | tipo |
    | $c1 | #other  | si     | $m1     | $cl1   | N    |
    | $c2 | #logged | si     | $m1     | $cl2   | N    |
  E istanze di tipo "Documento":
    | id  | classe     | tipo |
    | $d1 | $c1:classe | M    |
  Quando pagina attiva "documenti_docenti"
  E selezioni opzione "Tutti" da lista "documento_filtro"
  E selezioni opzione "15 maggio" da lista "documento_tipo"
  E selezioni opzione "<classe>" da lista "documento_classe"
  E premi pulsante "Filtra"
  Allora vedi nella tabella le colonne:
    | classe | documento |
  E vedi "1" riga nella tabella
  E vedi in una riga della tabella i dati:
    | classe                                     | documento   |
    | <cattedra>:classe,classe.corso,classe.sede | <documento> |
  Esempi:
    | classe  | documento              | cattedra |
    | $cl1:id | Documento Excel        | $c1      |
    | $cl2:id | Documento non inserito | $c2      |


################################################################################
# Bisogna controllare memorizzazione dati di sessione

Schema dello scenario: modifica filtri e controlla che siano memorizzati in sessione
  Data modifica utente attuale con parametri:
    | nomeParam | valoreParam |
    | sede      | null         |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $cl1 | 1    | B       |
    | $cl2 | 3    | B       |
  E ricerca istanze di tipo "Materia":
    | id  | nome        |
    | $m1 | Informatica |
    | $m2 | Storia      |
  E istanze di tipo "Cattedra":
    | id  | docente | attiva | materia | classe | tipo |
    | $c1 | #other  | si     | $m1     | $cl1   | N    |
    | $c2 | #logged | si     | $m2     | $cl2   | N    |
  E istanze di tipo "Documento":
    | id  | classe     | materia     | tipo   |
    | $d1 | $c1:classe | $c1:materia | <tipo> |
  E pagina attiva "documenti_docenti"
  E selezionata opzione "<filtro>" da lista "documento_filtro"
  E selezionata opzione "<nome_tipo>" da lista "documento_tipo"
  E selezionata opzione "<classe>" da lista "documento_classe"
  E premuto pulsante "Filtra"
  Quando vai alla pagina "login_home"
  E vai alla pagina "documenti_docenti"
  Allora vedi nella tabella le colonne:
    | classe e materia | docenti | documento |
  E vedi "1" riga nella tabella
  E vedi in una riga della tabella i dati:
    | classe e materia                                             | docenti                                 | documento   |
    | <cattedra>:classe,classe.corso,classe.sede,materia.nomeBreve | <cattedra>:docente.nome,docente.cognome | <documento> |
  Esempi:
    | tipo | nome_tipo | filtro   | classe  | documento              | cattedra |
    | P    | Programmi | presenti | $cl1:id | Documento Excel        | $c1      |
    | P    | Programmi | mancanti | $cl2:id | Documento non inserito | $c2      |
    | R    | Relazioni | presenti | Tutte   | Documento Excel        | $c1      |
    | R    | Relazioni | Tutti    | $cl2:id | Documento non inserito | $c2      |


################################################################################
# Bisogna controllare accesso a pagine

Scenario: mostra errore all'accesso alla pagina senza utente
  Dato logout utente
  Quando vai alla pagina "documenti_docenti"
  Allora vedi pagina "login_form"

Schema dello scenario: mostra errore all'accesso alla pagina con altri utenti
  Dato logout utente
  E login utente con ruolo esatto <ruolo>
  Quando vai alla pagina "documenti_docenti"
  Allora vedi errore pagina "403"
  Esempi:
    | ruolo          |
    | Amministratore |
    | Docente        |
    | Ata            |
    | Genitore       |
    | Alunno         |
    | Utente         |
