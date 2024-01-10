# language: it

Funzionalità: visualizzazione pagelle per il primo periodo
  Per mostrare le pagelle del primo periodo
  Come utente genitore o alunno
  Bisogna controllare visualizzazione della pagella
  Bisogna controllare visualizzazione della pagella con la classe articolata
  Utilizzando "_scrutinioPCFixtures.yml"


################################################################################
# Bisogna controllare visualizzazione della pagella

Schema dello scenario: dati non presenti se visualizzazione non abilitata
	Data modifica istanza "@scrutinio_1A_P" con i dati:
    | visibile |
    | <data>   |
  E login utente "<utente>"
  Quando vai alla pagina "genitori_pagelle"
  Allora la sezione "#gs-main .alert-warning" contiene "Dati non disponibili"
  Esempi:
    | utente                   | data                 |
    | @alunno_1A_1:username    | null                 |
    | @genitore1_1A_1:username | null                 |
    | @alunno_1A_1:username    | #dtm(1,1,2030,0,0,0) |
    | @genitore2_1A_2:username | #dtm(1,1,2030,0,0,0) |

Scenario: pagina dati con visualizzazione abilitata
	Data modifica istanza "@scrutinio_1A_P" con i dati:
    | visibile             |
    | #dtm(1,1,2020,0,0,0) |
  E login utente "@alunno_1A_1:username"
  Quando vai alla pagina "genitori_pagelle"
  Allora la sezione "#gs-main table:nth-child(3) caption" contiene "Scrutinio del Primo Quadrimestre"
  E vedi la tabella "1":
    | Materia                     |	Voto                                                                                                                    | Ore di assenza       |
    | @materia_RELIGIONE:nome     |	#cas(@voto_P_1A_6:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) | @voto_P_1A_6:assenze |
    | @materia_curricolare_1:nome |	#cas(@voto_P_1A_0:unico,0,NC,@voto_P_1A_0:unico)                                                                        | @voto_P_1A_0:assenze |
    | @materia_curricolare_2:nome |	#cas(@voto_P_1A_1:unico,0,NC,@voto_P_1A_1:unico)                                                                        | @voto_P_1A_1:assenze |
    | @materia_curricolare_3:nome |	#cas(@voto_P_1A_2:unico,0,NC,@voto_P_1A_2:unico)                                                                        | @voto_P_1A_2:assenze |
    | @materia_curricolare_4:nome |	#cas(@voto_P_1A_3:unico,0,NC,@voto_P_1A_3:unico)                                                                        | @voto_P_1A_3:assenze |
    | @materia_itp_1:nome         |	#cas(@voto_P_1A_5:unico,0,NC,@voto_P_1A_5:unico)                                                                        | @voto_P_1A_5:assenze |
    | @materia_curricolare_5:nome |	#cas(@voto_P_1A_4:unico,0,NC,@voto_P_1A_4:unico)                                                                        | @voto_P_1A_4:assenze |
    | @materia_EDCIVICA:nome      |	#cas(@voto_P_1A_7:unico,2,NC,@voto_P_1A_7:unico)                                                                        | @voto_P_1A_7:assenze |
    | @materia_CONDOTTA:nome      |	#cas(@voto_P_1A_8:unico,4,NC,@voto_P_1A_8:unico)                                                                        |                      |
  E la sezione "#gs-main table:nth-child(4) caption" contiene "Recupero dei debiti formativi"
  E vedi la tabella "2":
    | Materia                     |	Argomenti da recuperare | Modalità di recupero |
    | @materia_curricolare_1:nome | Argomento...            | Studio individuale   |
    | @materia_curricolare_2:nome | Argomento...            | Studio individuale   |

Scenario: visualizzazione comunicazione voti
	Data modifica istanza "@scrutinio_1A_P" con i dati:
    | visibile             |
    | #dtm(1,1,2020,0,0,0) |
  E login utente "@alunno_1A_1:username"
  Quando vai alla pagina "genitori_pagelle"
  E click su "Comunicazione dei voti"
  E analizzi PDF "archivio/scrutini/primo/1A/1A-pagella-primo-quadrimestre-{{@alunno_1A_1:id}}.pdf"
  Allora vedi testo "@alunno_1A_1:cognome @alunno_1A_1:nome" in PDF analizzato alla riga "3"
  E vedi testo "1ª A" in PDF analizzato alla riga "4"
  E vedi poi testo "#str(MATERIA) #str(VOTO) #str(ORE)" in PDF analizzato in una riga
  E vedi poi testo "@materia_RELIGIONE:nome #cas(@voto_P_1A_6:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) @voto_P_1A_6:assenze" in PDF analizzato in una riga
  E vedi poi testo "@materia_curricolare_1:nome #cas(@voto_P_1A_0:unico,0,NC,@voto_P_1A_0:unico) @voto_P_1A_0:assenze" in PDF analizzato in una riga
  E vedi poi testo "@materia_curricolare_2:nome #cas(@voto_P_1A_1:unico,0,NC,@voto_P_1A_1:unico) @voto_P_1A_1:assenze" in PDF analizzato in una riga
  E vedi poi testo "@materia_curricolare_3:nome #cas(@voto_P_1A_2:unico,0,NC,@voto_P_1A_2:unico) @voto_P_1A_2:assenze" in PDF analizzato in una riga
  E vedi poi testo "@materia_curricolare_4:nome #cas(@voto_P_1A_3:unico,0,NC,@voto_P_1A_3:unico) @voto_P_1A_3:assenze" in PDF analizzato in una riga
  E vedi poi testo "@materia_itp_1:nome #cas(@voto_P_1A_0:unico,0,NC,@voto_P_1A_5:unico) @voto_P_1A_5:assenze" in PDF analizzato in una riga
  E vedi poi testo "@materia_curricolare_5:nome #cas(@voto_P_1A_4:unico,0,NC,@voto_P_1A_4:unico) @voto_P_1A_4:assenze" in PDF analizzato in una riga
  E vedi poi testo "@materia_EDCIVICA:nome #cas(@voto_P_1A_7:unico,2,NC,@voto_P_1A_7:unico) @voto_P_1A_7:assenze" in PDF analizzato in una riga
  E vedi poi testo "@materia_CONDOTTA:nome #cas(@voto_P_1A_8:unico,4,NC,@voto_P_1A_8:unico)" in PDF analizzato in una riga

Scenario: visualizzazione comunicazione debiti
	Data modifica istanza "@scrutinio_1A_P" con i dati:
    | visibile             |
    | #dtm(1,1,2020,0,0,0) |
  E login utente "@alunno_1A_1:username"
  Quando vai alla pagina "genitori_pagelle"
  E click su "Comunicazione dei debiti"
  E analizzi PDF "archivio/scrutini/primo/1A/1A-debiti-primo-quadrimestre-{{@alunno_1A_1:id}}.pdf"
  Allora vedi testo "@alunno_1A_1:cognome @alunno_1A_1:nome" in PDF analizzato alla riga "3"
  E vedi testo "1ª A" in PDF analizzato alla riga "4"
  E vedi poi testo "#str(MATERIA) #str(VOTO) #str(Argomenti) #str(Modalità)" in PDF analizzato in una riga
  E vedi poi testo "@materia_curricolare_1:nome #cas(@voto_P_1A_0:unico,0,NC,@voto_P_1A_0:unico) #str(Argomento...) #str(Studio) #str(individuale)" in PDF analizzato in "2" righe
  E vedi poi testo "@materia_curricolare_2:nome #cas(@voto_P_1A_1:unico,0,NC,@voto_P_1A_1:unico) #str(Argomento...) #str(Studio) #str(individuale)" in PDF analizzato in "2" righe


################################################################################
# Bisogna controllare visualizzazione della pagella con la classe articolata

Schema dello scenario: dati non presenti se visualizzazione non abilitata
	Data modifica istanza "@scrutinio_3CAMB_P" con i dati:
    | visibile |
    | <data>   |
  E login utente "<utente>"
  Quando vai alla pagina "genitori_pagelle"
  Allora la sezione "#gs-main .alert-warning" contiene "Dati non disponibili"
  Esempi:
    | utente                      | data                 |
    | @alunno_3CAMB_1:username    | null                 |
    | @genitore1_3CAMB_1:username | null                 |
    | @alunno_3CAMB_1:username    | #dtm(1,1,2030,0,0,0) |
    | @genitore2_3CAMB_2:username | #dtm(1,1,2030,0,0,0) |

Scenario: pagina dati con visualizzazione abilitata per la classe articolata
	Data modifica istanza "@scrutinio_3CAMB_P" con i dati:
    | visibile             |
    | #dtm(1,1,2020,0,0,0) |
  E login utente "@alunno_3CAMB_1:username"
  Quando vai alla pagina "genitori_pagelle"
  Allora la sezione "#gs-main table:nth-child(3) caption" contiene "Scrutinio del Primo Quadrimestre"
  E vedi la tabella "1":
    | Materia                     |	Voto                                                                                                                       | Ore di assenza          |
    | @materia_RELIGIONE:nome     |	#cas(@voto_P_3CAMB_5:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) | @voto_P_3CAMB_5:assenze |
    | @materia_curricolare_1:nome |	#cas(@voto_P_3CAMB_0:unico,0,NC,@voto_P_3CAMB_0:unico)                                                                     | @voto_P_3CAMB_0:assenze |
    | @materia_curricolare_2:nome |	#cas(@voto_P_3CAMB_1:unico,0,NC,@voto_P_3CAMB_1:unico)                                                                     | @voto_P_3CAMB_1:assenze |
    | @materia_curricolare_3:nome |	#cas(@voto_P_3CAMB_2:unico,0,NC,@voto_P_3CAMB_2:unico)                                                                     | @voto_P_3CAMB_2:assenze |
    | @materia_curricolare_4:nome |	#cas(@voto_P_3CAMB_3:unico,0,NC,@voto_P_3CAMB_3:unico)                                                                     | @voto_P_3CAMB_3:assenze |
    | @materia_itp_2:nome         |	#cas(@voto_P_3CAMB_6:unico,0,NC,@voto_P_3CAMB_6:unico)                                                                     | @voto_P_3CAMB_6:assenze |
    | @materia_curricolare_5:nome |	#cas(@voto_P_3CAMB_4:unico,0,NC,@voto_P_3CAMB_4:unico)                                                                     | @voto_P_3CAMB_4:assenze |
    | @materia_EDCIVICA:nome      |	#cas(@voto_P_3CAMB_7:unico,2,NC,@voto_P_3CAMB_7:unico)                                                                     | @voto_P_3CAMB_7:assenze |
    | @materia_CONDOTTA:nome      |	#cas(@voto_P_3CAMB_8:unico,4,NC,@voto_P_3CAMB_8:unico)                                                                     |                         |
  E la sezione "#gs-main table:nth-child(4) caption" contiene "Recupero dei debiti formativi"
  E vedi la tabella "2":
    | Materia                     |	Argomenti da recuperare | Modalità di recupero |
    | @materia_curricolare_1:nome | Argomento...            | Studio individuale   |
    | @materia_curricolare_2:nome | Argomento...            | Studio individuale   |
    | @materia_itp_2:nome         | Argomento...            | Studio individuale   |

Scenario: visualizzazione comunicazione voti
	Data modifica istanza "@scrutinio_3CAMB_P" con i dati:
    | visibile             |
    | #dtm(1,1,2020,0,0,0) |
  E login utente "@alunno_3CAMB_1:username"
  Quando vai alla pagina "genitori_pagelle"
  E click su "Comunicazione dei voti"
  E analizzi PDF "archivio/scrutini/primo/3CAMB/3CAMB-pagella-primo-quadrimestre-{{@alunno_3CAMB_1:id}}.pdf"
  Allora vedi testo "@alunno_3CAMB_1:cognome @alunno_3CAMB_1:nome" in PDF analizzato alla riga "3"
  E vedi testo "3ª C-AMB" in PDF analizzato alla riga "4"
  E vedi poi testo "#str(MATERIA) #str(VOTO) #str(ORE)" in PDF analizzato in una riga
  E vedi poi testo "@materia_RELIGIONE:nome #cas(@voto_P_3CAMB_5:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) @voto_P_3CAMB_5:assenze" in PDF analizzato in una riga
  E vedi poi testo "@materia_curricolare_1:nome #cas(@voto_P_3CAMB_0:unico,0,NC,@voto_P_3CAMB_0:unico) @voto_P_3CAMB_0:assenze" in PDF analizzato in una riga
  E vedi poi testo "@materia_curricolare_2:nome #cas(@voto_P_3CAMB_1:unico,0,NC,@voto_P_3CAMB_1:unico) @voto_P_3CAMB_1:assenze" in PDF analizzato in una riga
  E vedi poi testo "@materia_curricolare_3:nome #cas(@voto_P_3CAMB_2:unico,0,NC,@voto_P_3CAMB_2:unico) @voto_P_3CAMB_2:assenze" in PDF analizzato in una riga
  E vedi poi testo "@materia_curricolare_4:nome #cas(@voto_P_3CAMB_3:unico,0,NC,@voto_P_3CAMB_3:unico) @voto_P_3CAMB_3:assenze" in PDF analizzato in una riga
  E vedi poi testo "@materia_itp_2:nome #cas(@voto_P_3CAMB_6:unico,0,NC,@voto_P_3CAMB_6:unico) @voto_P_3CAMB_6:assenze" in PDF analizzato in una riga
  E vedi poi testo "@materia_curricolare_5:nome #cas(@voto_P_3CAMB_4:unico,0,NC,@voto_P_3CAMB_4:unico) @voto_P_3CAMB_4:assenze" in PDF analizzato in una riga
  E vedi poi testo "@materia_EDCIVICA:nome #cas(@voto_P_3CAMB_7:unico,2,NC,@voto_P_3CAMB_7:unico) @voto_P_3CAMB_7:assenze" in PDF analizzato in una riga
  E vedi poi testo "@materia_CONDOTTA:nome #cas(@voto_P_3CAMB_8:unico,4,NC,@voto_P_3CAMB_8:unico)" in PDF analizzato in una riga

Scenario: visualizzazione comunicazione voti
	Data modifica istanza "@scrutinio_3CAMB_P" con i dati:
    | visibile             |
    | #dtm(1,1,2020,0,0,0) |
  E login utente "@alunno_3CAMB_1:username"
  Quando vai alla pagina "genitori_pagelle"
  E click su "Comunicazione dei debiti"
  E analizzi PDF "archivio/scrutini/primo/3CAMB/3CAMB-debiti-primo-quadrimestre-{{@alunno_3CAMB_1:id}}.pdf"
  Allora vedi testo "@alunno_3CAMB_1:cognome @alunno_3CAMB_1:nome" in PDF analizzato alla riga "3"
  E vedi testo "3ª C-AMB" in PDF analizzato alla riga "4"
  E vedi poi testo "#str(MATERIA) #str(VOTO) #str(Argomenti) #str(Modalità)" in PDF analizzato in una riga
  E vedi poi testo "@materia_curricolare_1:nome #cas(@voto_P_3CAMB_0:unico,0,NC,@voto_P_3CAMB_0:unico) #str(Argomento...) #str(Studio) #str(individuale)" in PDF analizzato in "2" righe
  E vedi poi testo "@materia_curricolare_2:nome #cas(@voto_P_3CAMB_1:unico,0,NC,@voto_P_3CAMB_1:unico) #str(Argomento...) #str(Studio) #str(individuale)" in PDF analizzato in "2" righe
  E vedi poi testo "@materia_itp_2:nome #cas(@voto_P_3CAMB_6:unico,0,NC,@voto_P_3CAMB_6:unico) #str(Argomento...) #str(Studio) #str(individuale)" in PDF analizzato in "2" righe
