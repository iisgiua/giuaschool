# language: it

Funzionalità: visualizzazione esito scrutinio finale
  Per mostrare esito scrutinio finale
  Come utente genitore o alunno
  Bisogna controllare visualizzazione esito scrutinio
  Bisogna controllare visualizzazione esito scrutinio con la classe articolata
  Bisogna controllare documenti generati
  Utilizzando "_scrutinioFCFixtures.yml"


################################################################################
# Bisogna controllare visualizzazione esito scrutinio

Schema dello scenario: dati non presenti se visualizzazione non abilitata
	Data modifica istanza "@scrutinio_1A_F" con i dati:
    | visibile |
    | <data>   |
  E login utente "<utente>"
  Quando vai alla pagina "genitori_pagelle" con parametri:
    | periodo |
    | F       |
  Allora la sezione "#gs-main table caption" contiene "Primo Quadrimestre"
  Esempi:
    | utente                   | data                 |
    | @alunno_1A_1:username    | null                 |
    | @genitore1_1A_1:username | null                 |
    | @alunno_1A_1:username    | #dtm(1,1,2030,0,0,0) |
    | @genitore2_1A_2:username | #dtm(1,1,2030,0,0,0) |

Scenario: pagina dati per non ammesso per assenze
  Dato login utente "@alunno_1A_2:username"
  Quando vai alla pagina "genitori_pagelle" con parametri:
    | periodo |
    | F       |
  Allora la sezione "#gs-main div:nth-child(3)" contiene "#str(Presa) #str(visione) #dat()"
  E la sezione "#gs-main div:nth-child(4)" contiene "Scrutinio finale"
  E la sezione "#gs-main .alert-danger" contiene "ESCLUSO DALLO SCRUTINIO FINALE E NON AMMESSO"
  E vedi la tabella senza intestazioni:
    | 1                               |	2                        |
    | comunicazione di non ammissione | Scarica la comunicazione |
    | esiti della classe              | Scarica la comunicazione |

Scenario: pagina dati per alunno all'estero
  Dato login utente "@alunno_1A_6:username"
  Quando vai alla pagina "genitori_pagelle" con parametri:
    | periodo |
    | F       |
  Allora la sezione "#gs-main div:nth-child(3)" contiene "#str(Presa) #str(visione) #dat()"
  E la sezione "#gs-main div:nth-child(4)" contiene "Scrutinio finale"
  E la sezione "#gs-main .alert-warning" contiene "FREQUENTA ALL'ESTERO"
  E vedi la tabella senza intestazioni:
    | 1                               |	2                        |
    | esiti della classe              | Scarica la comunicazione |

Scenario: pagina dati per giudizio sospeso
  Dato login utente "@alunno_1A_1:username"
  Quando vai alla pagina "genitori_pagelle" con parametri:
    | periodo |
    | F       |
  Allora la sezione "#gs-main div:nth-child(3)" contiene "#str(Presa) #str(visione) #dat()"
  E la sezione "#gs-main div:nth-child(4)" contiene "Scrutinio finale"
  E la sezione "#gs-main .alert-warning" contiene "SOSPENSIONE DEL GIUDIZIO"
  E vedi la tabella "1":
    | Materia                     |	Voto                                             | Ore di assenza       |
    | Religione                   |	--                                               |                      |
    | @materia_curricolare_1:nome |	#cas(@voto_F_1A_0:unico,0,NC,@voto_F_1A_0:unico) | @voto_F_1A_0:assenze |
    | @materia_curricolare_2:nome |	#cas(@voto_F_1A_1:unico,0,NC,@voto_F_1A_1:unico) | @voto_F_1A_1:assenze |
    | @materia_curricolare_3:nome |	#cas(@voto_F_1A_2:unico,0,NC,@voto_F_1A_2:unico) | @voto_F_1A_2:assenze |
    | @materia_curricolare_4:nome |	#cas(@voto_F_1A_3:unico,0,NC,@voto_F_1A_3:unico) | @voto_F_1A_3:assenze |
    | @materia_itp_1:nome         |	#cas(@voto_F_1A_5:unico,0,NC,@voto_F_1A_5:unico) | @voto_F_1A_5:assenze |
    | @materia_curricolare_5:nome |	#cas(@voto_F_1A_4:unico,0,NC,@voto_F_1A_4:unico) | @voto_F_1A_4:assenze |
    | @materia_EDCIVICA:nome      |	#cas(@voto_F_1A_6:unico,2,NC,@voto_F_1A_6:unico) | @voto_F_1A_6:assenze |
    | @materia_CONDOTTA:nome      |	#cas(@voto_F_1A_7:unico,4,NC,@voto_F_1A_7:unico) |                      |
  E vedi la tabella "2" senza intestazioni:
    | 1                               |	2                        |
    | Comunicazione dei voti          | Scarica la comunicazione |
    | recupero del debito             | Scarica la comunicazione |
    | recupero autonomo delle carenze | Scarica la comunicazione |
    | esiti della classe              | Scarica la comunicazione |

Scenario: pagina dati per ammissione
  Dato login utente "@alunno_sostegno_1:username"
  Quando vai alla pagina "genitori_pagelle" con parametri:
    | periodo |
    | F       |
  Allora la sezione "#gs-main div:nth-child(3)" contiene "#str(Presa) #str(visione) #dat()"
  E la sezione "#gs-main div:nth-child(4)" contiene "Scrutinio finale"
  E la sezione "#gs-main .alert-success" contiene "/Esito dello scrutinio: AMMESS/ui"
  E vedi la tabella "1":
    | Materia                     |	Voto                                                                                                                     | Ore di assenza        |
    | Religione                   |	#cas(@voto_F_1A_26:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) | @voto_F_1A_26:assenze |
    | @materia_curricolare_1:nome |	#cas(@voto_F_1A_20:unico,0,NC,@voto_F_1A_20:unico)                                                                       | @voto_F_1A_20:assenze |
    | @materia_curricolare_2:nome |	#cas(@voto_F_1A_21:unico,0,NC,@voto_F_1A_21:unico)                                                                       | @voto_F_1A_21:assenze |
    | @materia_curricolare_3:nome |	#cas(@voto_F_1A_22:unico,0,NC,@voto_F_1A_22:unico)                                                                       | @voto_F_1A_22:assenze |
    | @materia_curricolare_4:nome |	#cas(@voto_F_1A_23:unico,0,NC,@voto_F_1A_23:unico)                                                                       | @voto_F_1A_23:assenze |
    | @materia_itp_1:nome         |	#cas(@voto_F_1A_25:unico,0,NC,@voto_F_1A_25:unico)                                                                       | @voto_F_1A_25:assenze |
    | @materia_curricolare_5:nome |	#cas(@voto_F_1A_24:unico,0,NC,@voto_F_1A_24:unico)                                                                       | @voto_F_1A_24:assenze |
    | @materia_EDCIVICA:nome      |	#cas(@voto_F_1A_27:unico,2,NC,@voto_F_1A_27:unico)                                                                       | @voto_F_1A_27:assenze |
    | @materia_CONDOTTA:nome      |	#cas(@voto_F_1A_28:unico,4,NC,@voto_F_1A_28:unico)                                                                       |                       |
  E vedi la tabella "2" senza intestazioni:
    | 1                               |	2                        |
    | Comunicazione dei voti          | Scarica la comunicazione |
    | recupero autonomo delle carenze | Scarica la comunicazione |
    | esiti della classe              | Scarica la comunicazione |

Scenario: pagina dati per non ammissione
  Dato login utente "@alunno_alternativa_1:username"
  Quando vai alla pagina "genitori_pagelle" con parametri:
    | periodo |
    | F       |
  Allora la sezione "#gs-main div:nth-child(3)" contiene "#str(Presa) #str(visione) #dat()"
  E la sezione "#gs-main div:nth-child(4)" contiene "Scrutinio finale"
  E la sezione "#gs-main .alert-danger" contiene "/Esito dello scrutinio: NON AMMESS/ui"
  E vedi la tabella "1":
    | Materia                     |	Voto                                                                                                                     | Ore di assenza        |
    | Religione                   |	#cas(@voto_F_1A_46:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) | @voto_F_1A_46:assenze |
    | @materia_curricolare_1:nome |	#cas(@voto_F_1A_40:unico,0,NC,@voto_F_1A_40:unico)                                                                       | @voto_F_1A_40:assenze |
    | @materia_curricolare_2:nome |	#cas(@voto_F_1A_41:unico,0,NC,@voto_F_1A_41:unico)                                                                       | @voto_F_1A_41:assenze |
    | @materia_curricolare_3:nome |	#cas(@voto_F_1A_42:unico,0,NC,@voto_F_1A_42:unico)                                                                       | @voto_F_1A_42:assenze |
    | @materia_curricolare_4:nome |	#cas(@voto_F_1A_43:unico,0,NC,@voto_F_1A_43:unico)                                                                       | @voto_F_1A_43:assenze |
    | @materia_itp_1:nome         |	#cas(@voto_F_1A_45:unico,0,NC,@voto_F_1A_45:unico)                                                                       | @voto_F_1A_45:assenze |
    | @materia_curricolare_5:nome |	#cas(@voto_F_1A_44:unico,0,NC,@voto_F_1A_44:unico)                                                                       | @voto_F_1A_44:assenze |
    | @materia_EDCIVICA:nome      |	#cas(@voto_F_1A_47:unico,2,NC,@voto_F_1A_47:unico)                                                                       | @voto_F_1A_47:assenze |
    | @materia_CONDOTTA:nome      |	#cas(@voto_F_1A_48:unico,4,NC,@voto_F_1A_48:unico)                                                                       |                       |
  E vedi la tabella "2" senza intestazioni:
    | 1                               |	2                        |
    | Comunicazione di non ammissione | Scarica la comunicazione |
    | esiti della classe              | Scarica la comunicazione |

Scenario: pagina dati per ammissione classe terza
  Dato login utente "@alunno_3A_2:username"
  Quando vai alla pagina "genitori_pagelle" con parametri:
    | periodo |
    | F       |
  Allora la sezione "#gs-main div:nth-child(3)" contiene "#str(Presa) #str(visione) #dat()"
  E la sezione "#gs-main div:nth-child(4)" contiene "Scrutinio finale"
  E la sezione "#gs-main .alert-success" contiene "/Esito dello scrutinio: AMMESS/ui"
  E la sezione "#gs-main .alert-success" contiene "#str(Media) #med(@voto_F_3A_10:unico,@voto_F_3A_11:unico,@voto_F_3A_12:unico,@voto_F_3A_13:unico,@voto_F_3A_14:unico,@voto_F_3A_15:unico,@voto_F_3A_17:unico,@voto_F_3A_18:unico) #str(Credito) @esito_F_3A_2:credito"
  E vedi la tabella "1":
    | Materia                     |	Voto                                                                                                                     | Ore di assenza        |
    | Religione                   |	#cas(@voto_F_3A_16:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) | @voto_F_3A_16:assenze |
    | @materia_curricolare_1:nome |	#cas(@voto_F_3A_10:unico,0,NC,@voto_F_3A_10:unico)                                                                       | @voto_F_3A_10:assenze |
    | @materia_curricolare_2:nome |	#cas(@voto_F_3A_11:unico,0,NC,@voto_F_3A_11:unico)                                                                       | @voto_F_3A_11:assenze |
    | @materia_curricolare_3:nome |	#cas(@voto_F_3A_12:unico,0,NC,@voto_F_3A_12:unico)                                                                       | @voto_F_3A_12:assenze |
    | @materia_curricolare_4:nome |	#cas(@voto_F_3A_13:unico,0,NC,@voto_F_3A_13:unico)                                                                       | @voto_F_3A_13:assenze |
    | @materia_itp_1:nome         |	#cas(@voto_F_3A_15:unico,0,NC,@voto_F_3A_15:unico)                                                                       | @voto_F_3A_15:assenze |
    | @materia_curricolare_5:nome |	#cas(@voto_F_3A_14:unico,0,NC,@voto_F_3A_14:unico)                                                                       | @voto_F_3A_14:assenze |
    | @materia_EDCIVICA:nome      |	#cas(@voto_F_3A_17:unico,2,NC,@voto_F_3A_17:unico)                                                                       | @voto_F_3A_17:assenze |
    | @materia_CONDOTTA:nome      |	#cas(@voto_F_3A_18:unico,4,NC,@voto_F_3A_18:unico)                                                                       |                       |
  E vedi la tabella "2" senza intestazioni:
    | 1                               |	2                        |
    | Comunicazione dei voti          | Scarica la comunicazione |
    | esiti della classe              | Scarica la comunicazione |

Scenario: pagina dati per ammissione classe quarta
  Dato login utente "@alunno_4A_2:username"
  Quando vai alla pagina "genitori_pagelle" con parametri:
    | periodo |
    | F       |
  Allora la sezione "#gs-main div:nth-child(3)" contiene "#str(Presa) #str(visione) #dat()"
  E la sezione "#gs-main div:nth-child(4)" contiene "Scrutinio finale"
  E la sezione "#gs-main .alert-success" contiene "/Esito dello scrutinio: AMMESS/ui"
  E la sezione "#gs-main .alert-success" contiene "#str(Media) #med(@voto_F_4A_10:unico,@voto_F_4A_11:unico,@voto_F_4A_12:unico,@voto_F_4A_13:unico,@voto_F_4A_14:unico,@voto_F_4A_15:unico,@voto_F_4A_17:unico,@voto_F_4A_18:unico) #str(Credito) @esito_F_4A_2:credito #str(precedente) @alunno_4A_2:credito3 #str(totale) #sum(@esito_F_4A_2:credito,@alunno_4A_2:credito3)"
  E vedi la tabella "1":
    | Materia                     |	Voto                                                                                                                     | Ore di assenza        |
    | Religione                   |	#cas(@voto_F_4A_16:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) | @voto_F_4A_16:assenze |
    | @materia_curricolare_1:nome |	#cas(@voto_F_4A_10:unico,0,NC,@voto_F_4A_10:unico)                                                                       | @voto_F_4A_10:assenze |
    | @materia_curricolare_2:nome |	#cas(@voto_F_4A_11:unico,0,NC,@voto_F_4A_11:unico)                                                                       | @voto_F_4A_11:assenze |
    | @materia_curricolare_3:nome |	#cas(@voto_F_4A_12:unico,0,NC,@voto_F_4A_12:unico)                                                                       | @voto_F_4A_12:assenze |
    | @materia_curricolare_4:nome |	#cas(@voto_F_4A_13:unico,0,NC,@voto_F_4A_13:unico)                                                                       | @voto_F_4A_13:assenze |
    | @materia_itp_1:nome         |	#cas(@voto_F_4A_15:unico,0,NC,@voto_F_4A_15:unico)                                                                       | @voto_F_4A_15:assenze |
    | @materia_curricolare_5:nome |	#cas(@voto_F_4A_14:unico,0,NC,@voto_F_4A_14:unico)                                                                       | @voto_F_4A_14:assenze |
    | @materia_EDCIVICA:nome      |	#cas(@voto_F_4A_17:unico,2,NC,@voto_F_4A_17:unico)                                                                       | @voto_F_4A_17:assenze |
    | @materia_CONDOTTA:nome      |	#cas(@voto_F_4A_18:unico,4,NC,@voto_F_4A_18:unico)                                                                       |                       |
  E vedi la tabella "2" senza intestazioni:
    | 1                               |	2                        |
    | Comunicazione dei voti          | Scarica la comunicazione |
    | esiti della classe              | Scarica la comunicazione |

Scenario: pagina dati per ammissione classe quinta
  Dato login utente "@alunno_5A_2:username"
  Quando vai alla pagina "genitori_pagelle" con parametri:
    | periodo |
    | F       |
  Allora la sezione "#gs-main div:nth-child(3)" contiene "#str(Presa) #str(visione) #dat()"
  E la sezione "#gs-main div:nth-child(4)" contiene "Scrutinio finale"
  E la sezione "#gs-main .alert-success" contiene "/Esito dello scrutinio: AMMESS/ui"
  E la sezione "#gs-main .alert-success" contiene "#str(Media) #med(@voto_F_5A_10:unico,@voto_F_5A_11:unico,@voto_F_5A_12:unico,@voto_F_5A_13:unico,@voto_F_5A_14:unico,@voto_F_5A_15:unico,@voto_F_5A_17:unico,@voto_F_5A_18:unico) #str(Credito) @esito_F_5A_2:credito #str(precedente) #sum(@alunno_5A_2:credito3,@alunno_5A_2:credito4) #str(totale) #sum(@esito_F_5A_2:credito,@alunno_5A_2:credito3,@alunno_5A_2:credito4)"
  E vedi la tabella "1":
    | Materia                     |	Voto                                                                                                                     | Ore di assenza        |
    | Religione                   |	#cas(@voto_F_5A_16:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) | @voto_F_5A_16:assenze |
    | @materia_curricolare_1:nome |	#cas(@voto_F_5A_10:unico,0,NC,@voto_F_5A_10:unico)                                                                       | @voto_F_5A_10:assenze |
    | @materia_curricolare_2:nome |	#cas(@voto_F_5A_11:unico,0,NC,@voto_F_5A_11:unico)                                                                       | @voto_F_5A_11:assenze |
    | @materia_curricolare_3:nome |	#cas(@voto_F_5A_12:unico,0,NC,@voto_F_5A_12:unico)                                                                       | @voto_F_5A_12:assenze |
    | @materia_curricolare_4:nome |	#cas(@voto_F_5A_13:unico,0,NC,@voto_F_5A_13:unico)                                                                       | @voto_F_5A_13:assenze |
    | @materia_itp_1:nome         |	#cas(@voto_F_5A_15:unico,0,NC,@voto_F_5A_15:unico)                                                                       | @voto_F_5A_15:assenze |
    | @materia_curricolare_5:nome |	#cas(@voto_F_5A_14:unico,0,NC,@voto_F_5A_14:unico)                                                                       | @voto_F_5A_14:assenze |
    | @materia_EDCIVICA:nome      |	#cas(@voto_F_5A_17:unico,2,NC,@voto_F_5A_17:unico)                                                                       | @voto_F_5A_17:assenze |
    | @materia_CONDOTTA:nome      |	#cas(@voto_F_5A_18:unico,4,NC,@voto_F_5A_18:unico)                                                                       |                       |
  E vedi la tabella "2" senza intestazioni:
    | 1                                | 2                        |
    | Elaborato di cittadinanza attiva | Scarica la comunicazione |
    | Comunicazione dei voti           | Scarica la comunicazione |
    | esiti della classe               | Scarica la comunicazione |


################################################################################
# Bisogna controllare visualizzazione esito scrutinio con la classe articolata

Schema dello scenario: dati non presenti se visualizzazione non abilitata su classe articolata
	Data modifica istanza "@scrutinio_3CAMB_F" con i dati:
    | visibile |
    | <data>   |
  E login utente "<utente>"
  Quando vai alla pagina "genitori_pagelle" con parametri:
    | periodo |
    | F       |
  Allora la sezione "#gs-main table caption" contiene "Primo Quadrimestre"
  Esempi:
    | utente                      | data                 |
    | @alunno_3CAMB_1:username    | null                 |
    | @genitore1_3CAMB_1:username | null                 |
    | @alunno_3CAMB_1:username    | #dtm(1,1,2030,0,0,0) |
    | @genitore2_3CAMB_2:username | #dtm(1,1,2030,0,0,0) |

Scenario: pagina dati per giudizio sospeso su classe articolata
  Dato login utente "@alunno_3CAMB_1:username"
  Quando vai alla pagina "genitori_pagelle" con parametri:
    | periodo |
    | F       |
  Allora la sezione "#gs-main div:nth-child(3)" contiene "#str(Presa) #str(visione) #dat()"
  E la sezione "#gs-main div:nth-child(4)" contiene "Scrutinio finale"
  E la sezione "#gs-main .alert-warning" contiene "SOSPENSIONE DEL GIUDIZIO"
  E vedi la tabella "1":
    | Materia                     |	Voto                                                   | Ore di assenza          |
    | Religione                   |	--                                                     |                         |
    | @materia_curricolare_1:nome |	#cas(@voto_F_3CAMB_0:unico,0,NC,@voto_F_3CAMB_0:unico) | @voto_F_3CAMB_0:assenze |
    | @materia_curricolare_2:nome |	#cas(@voto_F_3CAMB_1:unico,0,NC,@voto_F_3CAMB_1:unico) | @voto_F_3CAMB_1:assenze |
    | @materia_curricolare_3:nome |	#cas(@voto_F_3CAMB_2:unico,0,NC,@voto_F_3CAMB_2:unico) | @voto_F_3CAMB_2:assenze |
    | @materia_curricolare_4:nome |	#cas(@voto_F_3CAMB_3:unico,0,NC,@voto_F_3CAMB_3:unico) | @voto_F_3CAMB_3:assenze |
    | @materia_itp_2:nome         |	#cas(@voto_F_3CAMB_5:unico,0,NC,@voto_F_3CAMB_5:unico) | @voto_F_3CAMB_5:assenze |
    | @materia_curricolare_5:nome |	#cas(@voto_F_3CAMB_4:unico,0,NC,@voto_F_3CAMB_4:unico) | @voto_F_3CAMB_4:assenze |
    | @materia_EDCIVICA:nome      |	#cas(@voto_F_3CAMB_6:unico,2,NC,@voto_F_3CAMB_6:unico) | @voto_F_3CAMB_6:assenze |
    | @materia_CONDOTTA:nome      |	#cas(@voto_F_3CAMB_7:unico,4,NC,@voto_F_3CAMB_7:unico) |                         |
  E vedi la tabella "2" senza intestazioni:
    | 1                               |	2                        |
    | Comunicazione dei voti          | Scarica la comunicazione |
    | recupero del debito             | Scarica la comunicazione |
    | recupero autonomo delle carenze | Scarica la comunicazione |
    | esiti della classe              | Scarica la comunicazione |

Scenario: pagina dati per non ammissione su classe articolata
  Dato login utente "@alunno_sostegno_4:username"
  Quando vai alla pagina "genitori_pagelle" con parametri:
    | periodo |
    | F       |
  Allora la sezione "#gs-main div:nth-child(3)" contiene "#str(Presa) #str(visione) #dat()"
  E la sezione "#gs-main div:nth-child(4)" contiene "Scrutinio finale"
  E la sezione "#gs-main .alert-danger" contiene "/Esito dello scrutinio: NON AMMESS/ui"
  E vedi la tabella "1":
    | Materia                     |	Voto                                                                                                                        | Ore di assenza          |
    | Religione                   |	#cas(@voto_F_3CAMB_25:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) | @voto_F_3CAMB_25:assenze |
    | @materia_curricolare_1:nome |	#cas(@voto_F_3CAMB_20:unico,0,NC,@voto_F_3CAMB_20:unico)                                                                    | @voto_F_3CAMB_20:assenze |
    | @materia_curricolare_2:nome |	#cas(@voto_F_3CAMB_21:unico,0,NC,@voto_F_3CAMB_21:unico)                                                                    | @voto_F_3CAMB_21:assenze |
    | @materia_curricolare_3:nome |	#cas(@voto_F_3CAMB_22:unico,0,NC,@voto_F_3CAMB_22:unico)                                                                    | @voto_F_3CAMB_22:assenze |
    | @materia_curricolare_4:nome |	#cas(@voto_F_3CAMB_23:unico,0,NC,@voto_F_3CAMB_23:unico)                                                                    | @voto_F_3CAMB_23:assenze |
    | @materia_itp_2:nome         |	#cas(@voto_F_3CAMB_26:unico,0,NC,@voto_F_3CAMB_26:unico)                                                                    | @voto_F_3CAMB_26:assenze |
    | @materia_curricolare_5:nome |	#cas(@voto_F_3CAMB_24:unico,0,NC,@voto_F_3CAMB_24:unico)                                                                    | @voto_F_3CAMB_24:assenze |
    | @materia_EDCIVICA:nome      |	#cas(@voto_F_3CAMB_27:unico,2,NC,@voto_F_3CAMB_27:unico)                                                                    | @voto_F_3CAMB_27:assenze |
    | @materia_CONDOTTA:nome      |	#cas(@voto_F_3CAMB_28:unico,4,NC,@voto_F_3CAMB_28:unico)                                                                    |                         |
  E vedi la tabella "2" senza intestazioni:
    | 1                               |	2                        |
    | Comunicazione di non ammissione | Scarica la comunicazione |
    | esiti della classe              | Scarica la comunicazione |

Scenario: pagina dati per ammissione su classe articolata
  Dato login utente "@alunno_3CAMB_2:username"
  Quando vai alla pagina "genitori_pagelle" con parametri:
    | periodo |
    | F       |
  Allora la sezione "#gs-main div:nth-child(3)" contiene "#str(Presa) #str(visione) #dat()"
  E la sezione "#gs-main div:nth-child(4)" contiene "Scrutinio finale"
  E la sezione "#gs-main .alert-success" contiene "/Esito dello scrutinio: AMMESS/ui"
  E la sezione "#gs-main .alert-success" contiene "#str(Media) #med(@voto_F_3CAMB_10:unico,@voto_F_3CAMB_11:unico,@voto_F_3CAMB_12:unico,@voto_F_3CAMB_13:unico,@voto_F_3CAMB_14:unico,@voto_F_3CAMB_16:unico,@voto_F_3CAMB_17:unico,@voto_F_3CAMB_18:unico) #str(Credito) @esito_F_3CAMB_2:credito"
  E vedi la tabella "1":
    | Materia                     |	Voto                                                                                                                        | Ore di assenza           |
    | Religione                   |	#cas(@voto_F_3CAMB_15:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) | @voto_F_3CAMB_15:assenze |
    | @materia_curricolare_1:nome |	#cas(@voto_F_3CAMB_10:unico,0,NC,@voto_F_3CAMB_10:unico)                                                                    | @voto_F_3CAMB_10:assenze |
    | @materia_curricolare_2:nome |	#cas(@voto_F_3CAMB_11:unico,0,NC,@voto_F_3CAMB_11:unico)                                                                    | @voto_F_3CAMB_11:assenze |
    | @materia_curricolare_3:nome |	#cas(@voto_F_3CAMB_12:unico,0,NC,@voto_F_3CAMB_12:unico)                                                                    | @voto_F_3CAMB_12:assenze |
    | @materia_curricolare_4:nome |	#cas(@voto_F_3CAMB_13:unico,0,NC,@voto_F_3CAMB_13:unico)                                                                    | @voto_F_3CAMB_13:assenze |
    | @materia_itp_2:nome         |	#cas(@voto_F_3CAMB_16:unico,0,NC,@voto_F_3CAMB_16:unico)                                                                    | @voto_F_3CAMB_16:assenze |
    | @materia_curricolare_5:nome |	#cas(@voto_F_3CAMB_14:unico,0,NC,@voto_F_3CAMB_14:unico)                                                                    | @voto_F_3CAMB_14:assenze |
    | @materia_EDCIVICA:nome      |	#cas(@voto_F_3CAMB_17:unico,2,NC,@voto_F_3CAMB_17:unico)                                                                    | @voto_F_3CAMB_17:assenze |
    | @materia_CONDOTTA:nome      |	#cas(@voto_F_3CAMB_18:unico,4,NC,@voto_F_3CAMB_18:unico)                                                                    |                          |
  E vedi la tabella "2" senza intestazioni:
    | 1                               |	2                        |
    | Comunicazione dei voti          | Scarica la comunicazione |
    | recupero autonomo delle carenze | Scarica la comunicazione |
    | esiti della classe              | Scarica la comunicazione |


################################################################################
# Bisogna controllare documenti generati

Scenario: visualizzazione comunicazione voti
  Dato login utente "@alunno_sostegno_1:username"
  Quando vai alla pagina "genitori_pagelle" con parametri:
    | periodo |
    | F       |
  E click su "Scarica la comunicazione" in sezione "#gs-main table tbody tr" che contiene "Comunicazione dei voti"
  E analizzi PDF "archivio/scrutini/finale/1A/1A-scrutinio-finale-voti-{{@alunno_sostegno_1:id}}.pdf"
  Allora vedi testo "@alunno_sostegno_1:cognome @alunno_sostegno_1:nome" in PDF analizzato alla riga "3"
  E vedi testo "1ª A" in PDF analizzato alla riga "4"
  E vedi poi testo "#str(MATERIA) #str(VOTO) #str(ORE)" in PDF analizzato in una riga
  E vedi poi testo "#str(Religione) #cas(@voto_F_1A_26:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) @voto_F_1A_26:assenze" in PDF analizzato in una riga
  E vedi poi testo "@materia_curricolare_1:nome #cas(@voto_F_1A_20:unico,0,NC,@voto_F_1A_20:unico) @voto_F_1A_20:assenze" in PDF analizzato in una riga
  E vedi poi testo "@materia_curricolare_2:nome #cas(@voto_F_1A_21:unico,0,NC,@voto_F_1A_21:unico) @voto_F_1A_21:assenze" in PDF analizzato in una riga
  E vedi poi testo "@materia_curricolare_3:nome #cas(@voto_F_1A_22:unico,0,NC,@voto_F_1A_22:unico) @voto_F_1A_22:assenze" in PDF analizzato in una riga
  E vedi poi testo "@materia_curricolare_4:nome #cas(@voto_F_1A_23:unico,0,NC,@voto_F_1A_23:unico) @voto_F_1A_23:assenze" in PDF analizzato in una riga
  E vedi poi testo "@materia_itp_1:nome #cas(@voto_F_1A_25:unico,0,NC,@voto_F_1A_25:unico) @voto_F_1A_25:assenze" in PDF analizzato in una riga
  E vedi poi testo "@materia_curricolare_5:nome #cas(@voto_F_1A_24:unico,0,NC,@voto_F_1A_24:unico) @voto_F_1A_24:assenze" in PDF analizzato in una riga
  E vedi poi testo "@materia_EDCIVICA:nome #cas(@voto_F_1A_27:unico,2,NC,@voto_F_1A_27:unico) @voto_F_1A_27:assenze" in PDF analizzato in una riga
  E vedi poi testo "@materia_CONDOTTA:nome #cas(@voto_F_1A_28:unico,4,NC,@voto_F_1A_28:unico)" in PDF analizzato in una riga
  E vedi poi testo "#dat(@scrutinio_1A_F:data) #str(Dirigente) @preside:nome,cognome" in PDF analizzato in "2" righe

Scenario: visualizzazione comunicazione voti per la classe articolata
	Dato login utente "@alunno_3CAMB_1:username"
  Quando vai alla pagina "genitori_pagelle" con parametri:
    | periodo |
    | F       |
  E click su "Scarica la comunicazione" in sezione "#gs-main table tbody tr" che contiene "Comunicazione dei voti"
  E analizzi PDF "archivio/scrutini/finale/3CAMB/3CAMB-scrutinio-finale-voti-{{@alunno_3CAMB_1:id}}.pdf"
  Allora vedi testo "@alunno_3CAMB_1:cognome @alunno_3CAMB_1:nome" in PDF analizzato alla riga "3"
  E vedi testo "3ª C-AMB" in PDF analizzato alla riga "4"
  E vedi poi testo "#str(MATERIA) #str(VOTO) #str(ORE)" in PDF analizzato in una riga
  E vedi poi testo "#str(Religione) #noc()" in PDF analizzato in una riga
  E vedi poi testo "@materia_curricolare_1:nome #cas(@voto_F_3CAMB_0:unico,0,NC,@voto_F_3CAMB_0:unico) @voto_F_3CAMB_0:assenze" in PDF analizzato in una riga
  E vedi poi testo "@materia_curricolare_2:nome #cas(@voto_F_3CAMB_1:unico,0,NC,@voto_F_3CAMB_1:unico) @voto_F_3CAMB_1:assenze" in PDF analizzato in una riga
  E vedi poi testo "@materia_curricolare_3:nome #cas(@voto_F_3CAMB_2:unico,0,NC,@voto_F_3CAMB_2:unico) @voto_F_3CAMB_2:assenze" in PDF analizzato in una riga
  E vedi poi testo "@materia_curricolare_4:nome #cas(@voto_F_3CAMB_3:unico,0,NC,@voto_F_3CAMB_3:unico) @voto_F_3CAMB_3:assenze" in PDF analizzato in una riga
  E vedi poi testo "@materia_itp_2:nome #cas(@voto_F_3CAMB_5:unico,0,NC,@voto_F_3CAMB_5:unico) @voto_F_3CAMB_5:assenze" in PDF analizzato in una riga
  E vedi poi testo "@materia_curricolare_5:nome #cas(@voto_F_3CAMB_4:unico,0,NC,@voto_F_3CAMB_4:unico) @voto_F_3CAMB_4:assenze" in PDF analizzato in una riga
  E vedi poi testo "@materia_EDCIVICA:nome #cas(@voto_F_3CAMB_6:unico,2,NC,@voto_F_3CAMB_6:unico) @voto_F_3CAMB_6:assenze" in PDF analizzato in una riga
  E vedi poi testo "@materia_CONDOTTA:nome #cas(@voto_F_3CAMB_7:unico,4,NC,@voto_F_3CAMB_7:unico)" in PDF analizzato in una riga
  E vedi poi testo "#dat(@scrutinio_3CAMB_F:data) #str(Dirigente) @preside:nome,cognome" in PDF analizzato in "2" righe

Scenario: visualizzazione comunicazione debiti
  Dato login utente "@alunno_1A_1:username"
  Quando vai alla pagina "genitori_pagelle" con parametri:
    | periodo |
    | F       |
  E click su "Scarica la comunicazione" in sezione "#gs-main table tbody tr" che contiene "Comunicazione per il recupero del debito formativo"
  E analizzi PDF "archivio/scrutini/finale/1A/1A-scrutinio-finale-debiti-{{@alunno_1A_1:id}}.pdf"
  Allora vedi testo "@alunno_1A_1:cognome @alunno_1A_1:nome" in PDF analizzato alla riga "3"
  E vedi testo "1ª A" in PDF analizzato alla riga "4"
  E vedi poi testo "SOSPENSIONE DEL GIUDIZIO" in PDF analizzato in "2" righe
  E imposti segnalibro PDF
  E vedi da segnalibro il testo "#str(MATERIA) #str(VOTO) #str(Argomenti) #str(Modalità)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@materia_curricolare_1:nome #cas(@voto_F_1A_0:unico,0,NC,@voto_F_1A_0:unico) #str(Argomento) #str(Corso)" in PDF analizzato in "2" righe
  E vedi da segnalibro il testo "@materia_curricolare_2:nome #cas(@voto_F_1A_1:unico,0,NC,@voto_F_1A_1:unico) #str(Argomento) #str(Corso)" in PDF analizzato in "2" righe
  E vedi da segnalibro il testo "@materia_curricolare_3:nome #cas(@voto_F_1A_2:unico,0,NC,@voto_F_1A_2:unico) #str(Argomento) #str(Corso)" in PDF analizzato in "2" righe
  E vedi poi testo "#dat(@scrutinio_1A_F:data) #str(Dirigente) @preside:nome,cognome" in PDF analizzato in "2" righe

Scenario: visualizzazione comunicazione debiti per la classe articolata
  Dato login utente "@alunno_3CAMB_1:username"
  Quando vai alla pagina "genitori_pagelle" con parametri:
    | periodo |
    | F       |
  E click su "Scarica la comunicazione" in sezione "#gs-main table tbody tr" che contiene "Comunicazione per il recupero del debito formativo"
  E analizzi PDF "archivio/scrutini/finale/3CAMB/3CAMB-scrutinio-finale-debiti-{{@alunno_3CAMB_1:id}}.pdf"
  Allora vedi testo "@alunno_3CAMB_1:cognome @alunno_3CAMB_1:nome" in PDF analizzato alla riga "3"
  E vedi testo "3ª C-AMB" in PDF analizzato alla riga "4"
  E vedi poi testo "SOSPENSIONE DEL GIUDIZIO" in PDF analizzato in "2" righe
  E vedi poi testo "#str(MATERIA) #str(VOTO) #str(Argomenti) #str(Modalità)" in PDF analizzato in una riga
  E vedi poi testo "@materia_curricolare_1:nome #cas(@voto_F_3CAMB_0:unico,0,NC,@voto_F_3CAMB_0:unico) #str(Argomento) #str(Corso)" in PDF analizzato in "2" righe
  E vedi poi testo "@materia_curricolare_2:nome #cas(@voto_F_3CAMB_1:unico,0,NC,@voto_F_3CAMB_1:unico) #str(Argomento) #str(Corso)" in PDF analizzato in "2" righe
  E vedi poi testo "@materia_curricolare_3:nome #cas(@voto_F_3CAMB_2:unico,0,NC,@voto_F_3CAMB_2:unico) #str(Argomento) #str(Corso)" in PDF analizzato in "2" righe
  E vedi poi testo "#dat(@scrutinio_1A_F:data) #str(Dirigente) @preside:nome,cognome" in PDF analizzato in "2" righe

Scenario: visualizzazione comunicazione carenze
  Dato login utente "@alunno_sostegno_1:username"
  Quando vai alla pagina "genitori_pagelle" con parametri:
    | periodo |
    | F       |
  E click su "Scarica la comunicazione" in sezione "#gs-main table tbody tr" che contiene "Comunicazione per il recupero autonomo delle carenze"
  E analizzi PDF "archivio/scrutini/finale/1A/1A-scrutinio-finale-carenze-{{@alunno_sostegno_1:id}}.pdf"
  Allora vedi testo "@alunno_sostegno_1:cognome @alunno_sostegno_1:nome" in PDF analizzato alla riga "3"
  E vedi testo "1ª A" in PDF analizzato alla riga "4"
  E vedi poi testo "Comunicazione per il recupero autonomo" in PDF analizzato in una riga
  E vedi poi testo "#str(MATERIA) #str(Argomenti)" in PDF analizzato in una riga
  E imposti segnalibro PDF
  E vedi da segnalibro il testo "@materia_curricolare_5:nome #str(Argomento)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@materia_itp_1:nome #str(Argomento)" in PDF analizzato in una riga
  E vedi poi testo "#dat(@scrutinio_1A_F:data) #str(Dirigente) @preside:nome,cognome" in PDF analizzato in "2" righe

Scenario: visualizzazione comunicazione carenze per la classe articolata
  Dato login utente "@alunno_3CAMB_1:username"
  Quando vai alla pagina "genitori_pagelle" con parametri:
    | periodo |
    | F       |
  E click su "Scarica la comunicazione" in sezione "#gs-main table tbody tr" che contiene "Comunicazione per il recupero autonomo delle carenze"
  E analizzi PDF "archivio/scrutini/finale/3CAMB/3CAMB-scrutinio-finale-carenze-{{@alunno_3CAMB_1:id}}.pdf"
  Allora vedi testo "@alunno_3CAMB_1:cognome @alunno_3CAMB_1:nome" in PDF analizzato alla riga "3"
  E vedi testo "3ª C-AMB" in PDF analizzato alla riga "4"
  E vedi poi testo "Comunicazione per il recupero autonomo" in PDF analizzato in una riga
  E vedi poi testo "#str(MATERIA) #str(Argomenti)" in PDF analizzato in una riga
  E vedi poi testo "@materia_curricolare_4:nome #str(Argomento)" in PDF analizzato in una riga
  E vedi poi testo "#dat(@scrutinio_3CAMB_F:data) #str(Dirigente) @preside:nome,cognome" in PDF analizzato in "2" righe

Scenario: visualizzazione comunicazione non ammessi
  Dato login utente "@alunno_alternativa_1:username"
  Quando vai alla pagina "genitori_pagelle" con parametri:
    | periodo |
    | F       |
  E click su "Scarica la comunicazione" in sezione "#gs-main table tbody tr" che contiene "Comunicazione di non ammissione"
  E analizzi PDF "archivio/scrutini/finale/1A/1A-scrutinio-finale-non-ammesso-{{@alunno_alternativa_1:id}}.pdf"
  Allora vedi testo "@alunno_alternativa_1:cognome @alunno_alternativa_1:nome" in PDF analizzato alla riga "3"
  E vedi testo "1ª A" in PDF analizzato alla riga "4"
  E vedi poi testo "ha deliberato la NON AMMISSIONE alla classe successiva" in PDF analizzato in una riga
  E vedi poi testo "Motivazione per la non ammissione" in PDF analizzato in una riga
  E vedi poi testo "#str(MATERIA) #str(VOTO) #str(ASSENZA)" in PDF analizzato in una riga
  E imposti segnalibro PDF
  E vedi da segnalibro il testo "@materia_curricolare_1:nome #cas(@voto_F_1A_40:unico,0,NC,@voto_F_1A_40:unico) @voto_F_1A_40:assenze" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@materia_curricolare_2:nome #cas(@voto_F_1A_41:unico,0,NC,@voto_F_1A_41:unico) @voto_F_1A_41:assenze" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@materia_curricolare_3:nome #cas(@voto_F_1A_42:unico,0,NC,@voto_F_1A_42:unico) @voto_F_1A_42:assenze" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@materia_curricolare_4:nome #cas(@voto_F_1A_43:unico,0,NC,@voto_F_1A_43:unico) @voto_F_1A_43:assenze" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@materia_curricolare_5:nome #cas(@voto_F_1A_44:unico,0,NC,@voto_F_1A_44:unico) @voto_F_1A_44:assenze" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@materia_itp_1:nome #cas(@voto_F_1A_45:unico,0,NC,@voto_F_1A_45:unico) @voto_F_1A_45:assenze" in PDF analizzato in una riga
  E vedi da segnalibro il testo "#str(Religione) #cas(@voto_F_1A_46:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) @voto_F_1A_46:assenze" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@materia_EDCIVICA:nome #cas(@voto_F_1A_47:unico,2,NC,@voto_F_1A_47:unico) @voto_F_1A_47:assenze" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@materia_CONDOTTA:nome #cas(@voto_F_1A_48:unico,4,NC,@voto_F_1A_48:unico)" in PDF analizzato in una riga
  E vedi poi testo "#dat(@scrutinio_1A_F:data) #str(Dirigente) @preside:nome,cognome" in PDF analizzato in "2" righe

Scenario: visualizzazione comunicazione non ammessi per la classe articolata
  Dato login utente "@alunno_sostegno_4:username"
  Quando vai alla pagina "genitori_pagelle" con parametri:
    | periodo |
    | F       |
  E click su "Scarica la comunicazione" in sezione "#gs-main table tbody tr" che contiene "Comunicazione di non ammissione"
  E analizzi PDF "archivio/scrutini/finale/3CAMB/3CAMB-scrutinio-finale-non-ammesso-{{@alunno_sostegno_4:id}}.pdf"
  Allora vedi testo "@alunno_sostegno_4:cognome @alunno_sostegno_4:nome" in PDF analizzato alla riga "3"
  E vedi testo "3ª C-AMB" in PDF analizzato alla riga "4"
  E vedi poi testo "ha deliberato la NON AMMISSIONE alla classe successiva" in PDF analizzato in una riga
  E vedi poi testo "Motivazione per la non ammissione" in PDF analizzato in una riga
  E vedi poi testo "#str(MATERIA) #str(VOTO) #str(ASSENZA)" in PDF analizzato in una riga
  E imposti segnalibro PDF
  E vedi da segnalibro il testo "@materia_curricolare_1:nome #cas(@voto_F_3CAMB_20:unico,0,NC,@voto_F_3CAMB_20:unico) @voto_F_3CAMB_20:assenze" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@materia_curricolare_2:nome #cas(@voto_F_3CAMB_21:unico,0,NC,@voto_F_3CAMB_21:unico) @voto_F_3CAMB_21:assenze" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@materia_curricolare_3:nome #cas(@voto_F_3CAMB_22:unico,0,NC,@voto_F_3CAMB_22:unico) @voto_F_3CAMB_22:assenze" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@materia_curricolare_4:nome #cas(@voto_F_3CAMB_23:unico,0,NC,@voto_F_3CAMB_23:unico) @voto_F_3CAMB_23:assenze" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@materia_curricolare_5:nome #cas(@voto_F_3CAMB_24:unico,0,NC,@voto_F_3CAMB_24:unico) @voto_F_3CAMB_24:assenze" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@materia_itp_2:nome #cas(@voto_F_3CAMB_26:unico,0,NC,@voto_F_3CAMB_26:unico) @voto_F_3CAMB_26:assenze" in PDF analizzato in una riga
  E vedi da segnalibro il testo "#str(Religione) #cas(@voto_F_3CAMB_25:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) @voto_F_3CAMB_25:assenze" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@materia_EDCIVICA:nome #cas(@voto_F_3CAMB_27:unico,2,NC,@voto_F_3CAMB_27:unico) @voto_F_3CAMB_27:assenze" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@materia_CONDOTTA:nome #cas(@voto_F_3CAMB_28:unico,4,NC,@voto_F_3CAMB_28:unico)" in PDF analizzato in una riga
  E vedi poi testo "#dat(@scrutinio_3CAMB_F:data) #str(Dirigente) @preside:nome,cognome" in PDF analizzato in "2" righe

Scenario: visualizzazione comunicazione non ammessi per assenze
  Dato login utente "@alunno_1A_2:username"
  Quando vai alla pagina "genitori_pagelle" con parametri:
    | periodo |
    | F       |
  E click su "Scarica la comunicazione" in sezione "#gs-main table tbody tr" che contiene "Comunicazione di non ammissione"
  E analizzi PDF "archivio/scrutini/finale/1A/1A-scrutinio-finale-non-ammesso-{{@alunno_1A_2:id}}.pdf"
  Allora vedi testo "@alunno_1A_2:cognome @alunno_1A_2:nome" in PDF analizzato alla riga "3"
  E vedi testo "1ª A" in PDF analizzato alla riga "4"
  E vedi poi testo "/esclusione dell'alunn.*dallo\s+scrutinio\s+e\s+pertanto\s+la\s+sua\s+NON\s+AMMISSIONE/ui" in PDF analizzato in "2" righe
  E vedi poi testo "#dat(@scrutinio_1A_F:data) #str(Dirigente) @preside:nome,cognome" in PDF analizzato in "2" righe

Scenario: visualizzazione tabellone esiti
  Dato login utente "@alunno_1A_1:username"
  Quando vai alla pagina "genitori_pagelle" con parametri:
    | periodo |
    | F       |
  E click su "Scarica la comunicazione" in sezione "#gs-main table tbody tr" che contiene "Tabellone degli esiti della classe"
  E analizzi PDF "archivio/scrutini/finale/1A/1A-scrutinio-finale-tabellone-esiti.pdf"
  Allora vedi testo "CLASSE 1ª A" in PDF analizzato alla riga "2"
  E vedi testo "SCRUTINIO FINALE" in PDF analizzato alla riga "3"
  E imposti segnalibro PDF
  E vedi da segnalibro il testo "@alunno_1A_6:cognome,nome #str(ALL'ESTERO)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_1A_1:cognome,nome #str(SOSPENSIONE)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_1A_2:cognome,nome #str(NON)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_sostegno_1:cognome,nome #nos(NON) #nos(SOSPENSIONE)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_sostegno_2:cognome,nome #nos(NON) #nos(SOSPENSIONE)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_alternativa_1:cognome,nome #str(NON)" in PDF analizzato in una riga
  E vedi poi testo "#dat(@scrutinio_1A_F:data) #str(Dirigente) @preside:nome,cognome" in PDF analizzato in "2" righe

Scenario: visualizzazione tabellone esiti per la classe articolata
  Dato login utente "@alunno_3CAMB_1:username"
  Quando vai alla pagina "genitori_pagelle" con parametri:
    | periodo |
    | F       |
  E click su "Scarica la comunicazione" in sezione "#gs-main table tbody tr" che contiene "Tabellone degli esiti della classe"
  E analizzi PDF "archivio/scrutini/finale/3CAMB/3CAMB-scrutinio-finale-tabellone-esiti.pdf"
  Allora vedi testo "CLASSE 3ª C-AMB" in PDF analizzato alla riga "2"
  E vedi testo "SCRUTINIO FINALE" in PDF analizzato alla riga "3"
  E imposti segnalibro PDF
  E vedi da segnalibro il testo "@alunno_3CAMB_1:cognome,nome #noc() #str(SOSPENSIONE)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_3CAMB_2:cognome,nome #noc() #nos(NON) #nos(SOSPENSIONE)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_sostegno_4:cognome,nome #noc() #str(NON)" in PDF analizzato in una riga
  E vedi poi testo "#dat(@scrutinio_1A_F:data) #str(Dirigente) @preside:nome,cognome" in PDF analizzato in "2" righe
