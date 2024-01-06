# language: it

Funzionalità: ultimo passo dello scrutinio del primo periodo
  Per svolgere l'ultimo passo dello scrutinio del primo periodo
  Come utente staff
  Bisogna controllare visualizzazione della pagina
  Bisogna controllare visualizzazione con la classe articolata
  Bisogna controllare documenti generati
  Utilizzando "_scrutinioPCFixtures.yml"


Contesto: login utente staff
	Dato login utente con ruolo esatto "Staff"


################################################################################
# Bisogna controllare visualizzazione della pagina

Scenario: visualizzazione pagina passo finale
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Allora la sezione "#gs-main h2" contiene "Scrutinio chiuso"
  E vedi la tabella:
    | Documento                      | Note                                                          | Azioni                                                                                                                                                                                                                                                  |
    | Verbale                        | /duplice copia.*Deve firmare il presidente e il segretario/ui | Scarica                                                                                                                                                                                                                                                 |
    | Riepilogo voti                 | /duplice copia.*Devono firmare tutti i docenti/ui             | Scarica                                                                                                                                                                                                                                                 |
    | Foglio firme Registro dei voti | /unica copia.*Devono firmare tutti i docenti/ui               | Scarica                                                                                                                                                                                                                                                 |
    | Comunicazione Pagella          | mostrato direttamente ai genitori                             | ?@alunno_1A_1:cognome+ +@alunno_1A_1:nome?@alunno_1A_2:cognome+ +@alunno_1A_2:nome?@alunno_sostegno_1:cognome+ +@alunno_sostegno_1:nome?@alunno_sostegno_2:cognome+ +@alunno_sostegno_2:nome?@alunno_alternativa_1:cognome+ +@alunno_alternativa_1:nome |
    | Comunicazione Debiti           | mostrato direttamente ai genitori                             | @alunno_1A_1:cognome+ +@alunno_1A_1:nome                                                                                                                                                                                                                |

Scenario: visualizzazione passo precedente
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "Riapri lo scrutinio"
  Allora la sezione "#gs-main h2" contiene "Passo 6"

Scenario: visualizzazione procedura completa
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "Riapri lo scrutinio"
  E click su "passo precedente"
  E click su "passo precedente"
  E click su "passo precedente"
  E click su "passo precedente"
  E click su "passo precedente"
  E click su "Annulla apertura scrutinio"
  E click su "Apri lo scrutinio"
  E click su "Conferma"
  E click su "Conferma"
  E click su "Conferma"
  E click su "Conferma"
  E click su "Conferma"
  E click su "Conferma"
  E click su "Conferma" con indice "2"
  E click su "Chiudi lo scrutinio"
  Allora la sezione "#gs-main h2" contiene "Scrutinio chiuso"
  E vedi la tabella:
    | Documento                      | Note                                                          | Azioni                                                                                                                                                                                                                                                  |
    | Verbale                        | /duplice copia.*Deve firmare il presidente e il segretario/ui | Scarica                                                                                                                                                                                                                                                 |
    | Riepilogo voti                 | /duplice copia.*Devono firmare tutti i docenti/ui             | Scarica                                                                                                                                                                                                                                                 |
    | Foglio firme Registro dei voti | /unica copia.*Devono firmare tutti i docenti/ui               | Scarica                                                                                                                                                                                                                                                 |
    | Comunicazione Pagella          | mostrato direttamente ai genitori                             | ?@alunno_1A_1:cognome+ +@alunno_1A_1:nome?@alunno_1A_2:cognome+ +@alunno_1A_2:nome?@alunno_sostegno_1:cognome+ +@alunno_sostegno_1:nome?@alunno_sostegno_2:cognome+ +@alunno_sostegno_2:nome?@alunno_alternativa_1:cognome+ +@alunno_alternativa_1:nome |
    | Comunicazione Debiti           | mostrato direttamente ai genitori                             | @alunno_1A_1:cognome+ +@alunno_1A_1:nome                                                                                                                                                                                                                |


################################################################################
# Bisogna controllare visualizzazione con la classe articolata

Scenario: visualizzazione classe articolata
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe           |
    | @classe_3CAMB:id |
  Allora la sezione "#gs-main h2" contiene "Scrutinio chiuso"
  E vedi la tabella:
    | Documento                      | Note                                                          | Azioni                                                                                                                                              |
    | Verbale                        | /duplice copia.*Deve firmare il presidente e il segretario/ui | Scarica                                                                                                                                             |
    | Riepilogo voti                 | /duplice copia.*Devono firmare tutti i docenti/ui             | Scarica                                                                                                                                             |
    | Foglio firme Registro dei voti | /unica copia.*Devono firmare tutti i docenti/ui               | Scarica                                                                                                                                             |
    | Comunicazione Pagella          | mostrato direttamente ai genitori                             | ?@alunno_3CAMB_1:cognome+ +@alunno_3CAMB_1:nome?@alunno_3CAMB_2:cognome+ +@alunno_3CAMB_2:nome?@alunno_sostegno_4:cognome+ +@alunno_sostegno_4:nome |
    | Comunicazione Debiti           | mostrato direttamente ai genitori                             | @alunno_3CAMB_1:cognome+ +@alunno_3CAMB_1:nome                                                                                                      |


################################################################################
# Bisogna controllare documenti generati

Scenario: controllo riepilogo dei voti
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "Scarica" in sezione "#gs-main table tbody tr" che contiene "Riepilogo voti"
  E analizzi PDF "archivio/scrutini/primo/1A/1A-riepilogo-voti-primo-quadrimestre.pdf"
  Allora vedi testo "RIEPILOGO VOTI 1ª A" in PDF analizzato alla riga "1"
  E vedi testo "@alunno_1A_1:cognome,nome #str(Voto) #cas(@voto_P_1A_6:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) #cas(@voto_P_1A_0:unico,0,NC,@voto_P_1A_0:unico) #cas(@voto_P_1A_1:unico,0,NC,@voto_P_1A_1:unico) #cas(@voto_P_1A_2:unico,0,NC,@voto_P_1A_2:unico) #cas(@voto_P_1A_3:unico,0,NC,@voto_P_1A_3:unico) #cas(@voto_P_1A_5:unico,0,NC,@voto_P_1A_5:unico) #cas(@voto_P_1A_4:unico,0,NC,@voto_P_1A_4:unico) #cas(@voto_P_1A_7:unico,2,NC,@voto_P_1A_7:unico) #cas(@voto_P_1A_8:unico,4,NC,@voto_P_1A_8:unico)" in PDF analizzato in una riga
  E vedi testo "#dat(@alunno_1A_1:dataNascita) #str(Ass.) @voto_P_1A_6:assenze @voto_P_1A_0:assenze @voto_P_1A_1:assenze @voto_P_1A_2:assenze @voto_P_1A_3:assenze @voto_P_1A_5:assenze @voto_P_1A_4:assenze @voto_P_1A_7:assenze #med(@voto_P_1A_0:unico,@voto_P_1A_1:unico,@voto_P_1A_2:unico,@voto_P_1A_3:unico,@voto_P_1A_4:unico,@voto_P_1A_5:unico,@voto_P_1A_7:unico,@voto_P_1A_8:unico)" in PDF analizzato in una riga
  E vedi testo "@alunno_1A_2:cognome,nome #str(Voto) #cas(@voto_P_1A_10:unico,0,NC,@voto_P_1A_10:unico) #cas(@voto_P_1A_11:unico,0,NC,@voto_P_1A_11:unico) #cas(@voto_P_1A_12:unico,0,NC,@voto_P_1A_12:unico) #cas(@voto_P_1A_13:unico,0,NC,@voto_P_1A_13:unico) #cas(@voto_P_1A_15:unico,0,NC,@voto_P_1A_15:unico) #cas(@voto_P_1A_14:unico,0,NC,@voto_P_1A_14:unico) #cas(@voto_P_1A_17:unico,2,NC,@voto_P_1A_17:unico) #cas(@voto_P_1A_18:unico,4,NC,@voto_P_1A_18:unico)" in PDF analizzato in una riga
  E vedi testo "#str(///) @voto_P_1A_10:assenze @voto_P_1A_11:assenze @voto_P_1A_12:assenze @voto_P_1A_13:assenze @voto_P_1A_15:assenze @voto_P_1A_14:assenze @voto_P_1A_17:assenze #med(@voto_P_1A_10:unico,@voto_P_1A_11:unico,@voto_P_1A_12:unico,@voto_P_1A_13:unico,@voto_P_1A_14:unico,@voto_P_1A_15:unico,@voto_P_1A_17:unico,@voto_P_1A_18:unico)" in PDF analizzato in una riga
  E vedi testo "@alunno_sostegno_1:cognome,nome #str(Voto) #cas(@voto_P_1A_26:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) #cas(@voto_P_1A_20:unico,0,NC,@voto_P_1A_20:unico) #cas(@voto_P_1A_21:unico,0,NC,@voto_P_1A_21:unico) #cas(@voto_P_1A_22:unico,0,NC,@voto_P_1A_22:unico) #cas(@voto_P_1A_23:unico,0,NC,@voto_P_1A_23:unico) #cas(@voto_P_1A_25:unico,0,NC,@voto_P_1A_25:unico) #cas(@voto_P_1A_24:unico,0,NC,@voto_P_1A_24:unico) #cas(@voto_P_1A_27:unico,2,NC,@voto_P_1A_27:unico) #cas(@voto_P_1A_28:unico,4,NC,@voto_P_1A_28:unico)" in PDF analizzato in una riga
  E vedi testo "#dat(@alunno_sostegno_1:dataNascita) #str(Ass.) @voto_P_1A_26:assenze @voto_P_1A_20:assenze @voto_P_1A_21:assenze @voto_P_1A_22:assenze @voto_P_1A_23:assenze @voto_P_1A_25:assenze @voto_P_1A_24:assenze @voto_P_1A_27:assenze #med(@voto_P_1A_20:unico,@voto_P_1A_21:unico,@voto_P_1A_22:unico,@voto_P_1A_23:unico,@voto_P_1A_24:unico,@voto_P_1A_25:unico,@voto_P_1A_27:unico,@voto_P_1A_28:unico)" in PDF analizzato in una riga
  E vedi testo "@alunno_sostegno_2:cognome,nome #str(Voto) #cas(@voto_P_1A_36:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) #cas(@voto_P_1A_30:unico,0,NC,@voto_P_1A_30:unico) #cas(@voto_P_1A_31:unico,0,NC,@voto_P_1A_31:unico) #cas(@voto_P_1A_32:unico,0,NC,@voto_P_1A_32:unico) #cas(@voto_P_1A_33:unico,0,NC,@voto_P_1A_33:unico) #cas(@voto_P_1A_35:unico,0,NC,@voto_P_1A_35:unico) #cas(@voto_P_1A_34:unico,0,NC,@voto_P_1A_34:unico) #cas(@voto_P_1A_37:unico,2,NC,@voto_P_1A_37:unico) #cas(@voto_P_1A_38:unico,4,NC,@voto_P_1A_38:unico)" in PDF analizzato in una riga
  E vedi testo "#dat(@alunno_sostegno_2:dataNascita) #str(Ass.) @voto_P_1A_36:assenze @voto_P_1A_30:assenze @voto_P_1A_31:assenze @voto_P_1A_32:assenze @voto_P_1A_33:assenze @voto_P_1A_35:assenze @voto_P_1A_34:assenze @voto_P_1A_37:assenze #med(@voto_P_1A_30:unico,@voto_P_1A_31:unico,@voto_P_1A_32:unico,@voto_P_1A_33:unico,@voto_P_1A_34:unico,@voto_P_1A_35:unico,@voto_P_1A_37:unico,@voto_P_1A_38:unico)" in PDF analizzato in una riga
  E vedi testo "@alunno_alternativa_1:cognome,nome #str(Voto) #cas(@voto_P_1A_46:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) #cas(@voto_P_1A_40:unico,0,NC,@voto_P_1A_40:unico) #cas(@voto_P_1A_41:unico,0,NC,@voto_P_1A_41:unico) #cas(@voto_P_1A_42:unico,0,NC,@voto_P_1A_42:unico) #cas(@voto_P_1A_43:unico,0,NC,@voto_P_1A_43:unico) #cas(@voto_P_1A_45:unico,0,NC,@voto_P_1A_45:unico) #cas(@voto_P_1A_44:unico,0,NC,@voto_P_1A_44:unico) #cas(@voto_P_1A_47:unico,2,NC,@voto_P_1A_47:unico) #cas(@voto_P_1A_48:unico,4,NC,@voto_P_1A_48:unico)" in PDF analizzato in una riga
  E vedi testo "#dat(@alunno_alternativa_1:dataNascita) #str(Ass.) @voto_P_1A_46:assenze @voto_P_1A_40:assenze @voto_P_1A_41:assenze @voto_P_1A_42:assenze @voto_P_1A_43:assenze @voto_P_1A_45:assenze @voto_P_1A_44:assenze @voto_P_1A_47:assenze #med(@voto_P_1A_40:unico,@voto_P_1A_41:unico,@voto_P_1A_42:unico,@voto_P_1A_43:unico,@voto_P_1A_44:unico,@voto_P_1A_45:unico,@voto_P_1A_47:unico,@voto_P_1A_48:unico)" in PDF analizzato in una riga
  E vedi testo "?#str(Bianchi)+ Maria?@docente_curricolare_2:cognome+ +@docente_curricolare_2:nome?@docente_curricolare_3:cognome+ +@docente_curricolare_3:nome?@docente_curricolare_4:cognome+ +@docente_curricolare_4:nome?@docente_curricolare_5:cognome+ +@docente_curricolare_5:nome?@docente_religione_1:cognome+ +@docente_religione_1:nome?@docente_itp_1:cognome+ +@docente_itp_1:nome?@docente_itp_2:cognome+ +@docente_itp_2:nome?@docente_sostegno_1:cognome+ +@docente_sostegno_1:nome?@docente_sostegno_2:cognome+ +@docente_sostegno_2:nome?@docente_nocattedra_1:cognome+ +@docente_nocattedra_1:nome" in PDF analizzato in "7" righe
  E vedi poi testo "/Data +01\/01\/2020 +Il Presidente +\(Prof.ssa Bianchi Maria\)/" in PDF analizzato in "2" righe

Scenario: controllo riepilogo dei voti per la classe articolata
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe           |
    | @classe_3CAMB:id |
  Quando click su "Scarica" in sezione "#gs-main table tbody tr" che contiene "Riepilogo voti"
  E analizzi PDF "archivio/scrutini/primo/3CAMB/3CAMB-riepilogo-voti-primo-quadrimestre.pdf"
  Allora vedi testo "RIEPILOGO VOTI 3ª C-AMB" in PDF analizzato alla riga "1"
  E vedi testo "@alunno_3CAMB_1:cognome,nome #str(Voto) #cas(@voto_P_3CAMB_5:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) #cas(@voto_P_3CAMB_0:unico,0,NC,@voto_P_3CAMB_0:unico) #cas(@voto_P_3CAMB_1:unico,0,NC,@voto_P_3CAMB_1:unico) #cas(@voto_P_3CAMB_2:unico,0,NC,@voto_P_3CAMB_2:unico) #cas(@voto_P_3CAMB_3:unico,0,NC,@voto_P_3CAMB_3:unico) #cas(@voto_P_3CAMB_6:unico,0,NC,@voto_P_3CAMB_6:unico) #cas(@voto_P_3CAMB_4:unico,0,NC,@voto_P_3CAMB_4:unico) #cas(@voto_P_3CAMB_7:unico,2,NC,@voto_P_3CAMB_7:unico) #cas(@voto_P_3CAMB_8:unico,4,NC,@voto_P_3CAMB_8:unico)" in PDF analizzato in una riga
  E vedi testo "#dat(@alunno_3CAMB_1:dataNascita) #str(Ass.) @voto_P_3CAMB_5:assenze @voto_P_3CAMB_0:assenze @voto_P_3CAMB_1:assenze @voto_P_3CAMB_2:assenze @voto_P_3CAMB_3:assenze @voto_P_3CAMB_6:assenze @voto_P_3CAMB_4:assenze @voto_P_3CAMB_7:assenze #med(@voto_P_3CAMB_0:unico,@voto_P_3CAMB_1:unico,@voto_P_3CAMB_2:unico,@voto_P_3CAMB_3:unico,@voto_P_3CAMB_4:unico,@voto_P_3CAMB_6:unico,@voto_P_3CAMB_7:unico,@voto_P_3CAMB_8:unico)" in PDF analizzato in una riga
  E vedi testo "@alunno_3CAMB_2:cognome,nome #str(Voto) #cas(@voto_P_3CAMB_10:unico,0,NC,@voto_P_3CAMB_10:unico) #cas(@voto_P_3CAMB_11:unico,0,NC,@voto_P_3CAMB_11:unico) #cas(@voto_P_3CAMB_12:unico,0,NC,@voto_P_3CAMB_12:unico) #cas(@voto_P_3CAMB_13:unico,0,NC,@voto_P_3CAMB_13:unico) #cas(@voto_P_3CAMB_16:unico,0,NC,@voto_P_3CAMB_16:unico) #cas(@voto_P_3CAMB_14:unico,0,NC,@voto_P_3CAMB_14:unico) #cas(@voto_P_3CAMB_17:unico,2,NC,@voto_P_3CAMB_17:unico) #cas(@voto_P_3CAMB_18:unico,4,NC,@voto_P_3CAMB_18:unico)" in PDF analizzato in una riga
  E vedi testo "#str(///) @voto_P_3CAMB_10:assenze @voto_P_3CAMB_11:assenze @voto_P_3CAMB_12:assenze @voto_P_3CAMB_13:assenze @voto_P_3CAMB_16:assenze @voto_P_3CAMB_14:assenze @voto_P_3CAMB_17:assenze #med(@voto_P_3CAMB_10:unico,@voto_P_3CAMB_11:unico,@voto_P_3CAMB_12:unico,@voto_P_3CAMB_13:unico,@voto_P_3CAMB_14:unico,@voto_P_3CAMB_16:unico,@voto_P_3CAMB_17:unico,@voto_P_3CAMB_18:unico)" in PDF analizzato in una riga
  E vedi testo "@alunno_sostegno_4:cognome,nome #str(Voto) #cas(@voto_P_3CAMB_25:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) #cas(@voto_P_3CAMB_20:unico,0,NC,@voto_P_3CAMB_20:unico) #cas(@voto_P_3CAMB_21:unico,0,NC,@voto_P_3CAMB_21:unico) #cas(@voto_P_3CAMB_22:unico,0,NC,@voto_P_3CAMB_22:unico) #cas(@voto_P_3CAMB_23:unico,0,NC,@voto_P_3CAMB_23:unico) #cas(@voto_P_3CAMB_26:unico,0,NC,@voto_P_3CAMB_26:unico) #cas(@voto_P_3CAMB_24:unico,0,NC,@voto_P_3CAMB_24:unico) #cas(@voto_P_3CAMB_27:unico,2,NC,@voto_P_3CAMB_27:unico) #cas(@voto_P_3CAMB_28:unico,4,NC,@voto_P_3CAMB_28:unico)" in PDF analizzato in una riga
  E vedi testo "#dat(@alunno_sostegno_4:dataNascita) #str(Ass.) @voto_P_3CAMB_25:assenze @voto_P_3CAMB_20:assenze @voto_P_3CAMB_21:assenze @voto_P_3CAMB_22:assenze @voto_P_3CAMB_23:assenze @voto_P_3CAMB_26:assenze @voto_P_3CAMB_24:assenze @voto_P_3CAMB_27:assenze #med(@voto_P_3CAMB_20:unico,@voto_P_3CAMB_21:unico,@voto_P_3CAMB_22:unico,@voto_P_3CAMB_23:unico,@voto_P_3CAMB_24:unico,@voto_P_3CAMB_26:unico,@voto_P_3CAMB_27:unico,@voto_P_3CAMB_28:unico)" in PDF analizzato in una riga
  E vedi testo "?@docente_curricolare_1:cognome+ +@docente_curricolare_1:nome?@docente_curricolare_2:cognome+ +@docente_curricolare_2:nome?@docente_curricolare_3:cognome+ +@docente_curricolare_3:nome?@docente_curricolare_4:cognome+ +@docente_curricolare_4:nome?@docente_curricolare_5:cognome+ +@docente_curricolare_5:nome?@docente_religione_1:cognome+ +@docente_religione_1:nome?@docente_itp_2:cognome+ +@docente_itp_2:nome" in PDF analizzato in "5" righe
  E vedi poi testo "#str(Data) #str(01/01/2020) #str(Presidente) @preside:nome,cognome" in PDF analizzato in "2" righe

Scenario: controllo foglio firme registro dei voti
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "Scarica" in sezione "#gs-main table tbody tr" che contiene "Foglio firme Registro dei voti"
  E analizzi PDF "archivio/scrutini/primo/1A/1A-firme-registro-primo-quadrimestre.pdf"
  Allora vedi testo "/FOGLIO FIRME REGISTRO +CLASSE 1ª A/" in PDF analizzato alla riga "1"
  E vedi testo "@materia_curricolare_1:nome @materia_EDCIVICA:nome #str(Bianchi) #str(Maria)" in PDF analizzato in una riga
  E vedi testo "@materia_curricolare_2:nome @materia_EDCIVICA:nome @docente_curricolare_2:cognome,nome" in PDF analizzato in una riga
  E vedi testo "@materia_curricolare_3:nome @materia_EDCIVICA:nome @docente_curricolare_3:cognome,nome" in PDF analizzato in una riga
  E vedi testo "@materia_curricolare_4:nome @materia_EDCIVICA:nome @docente_curricolare_4:cognome,nome" in PDF analizzato in una riga
  E vedi testo "@materia_curricolare_5:nome @materia_EDCIVICA:nome @docente_curricolare_5:cognome,nome" in PDF analizzato in una riga
  E vedi testo "@materia_RELIGIONE:nome+, Educazione?@docente_religione_1:cognome+ +@docente_religione_1:nome?@docente_nocattedra_1:cognome+ +@docente_nocattedra_1:nome" in PDF analizzato in una riga
  E vedi testo "@materia_itp_1:nome+, +@materia_EDCIVICA:nome?@docente_itp_1:cognome+ +@docente_itp_1:nome?@docente_itp_2:cognome+ +@docente_itp_2:nome" in PDF analizzato in una riga
  E vedi testo "@materia_SOSTEGNO:nome+, +@materia_EDCIVICA:nome?@docente_sostegno_1:cognome+ +@docente_sostegno_1:nome?@docente_sostegno_2:cognome+ +@docente_sostegno_2:nome" in PDF analizzato in una riga

Scenario: controllo foglio firme registro dei voti per la classe articolata
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe           |
    | @classe_3CAMB:id |
  Quando click su "Scarica" in sezione "#gs-main table tbody tr" che contiene "Foglio firme Registro dei voti"
  E analizzi PDF "archivio/scrutini/primo/3CAMB/3CAMB-firme-registro-primo-quadrimestre.pdf"
  Allora vedi testo "/FOGLIO FIRME REGISTRO +CLASSE 3ª C-AMB/" in PDF analizzato alla riga "1"
  E vedi testo "@materia_curricolare_1:nome @materia_EDCIVICA:nome @docente_curricolare_1:cognome,nome" in PDF analizzato in una riga
  E vedi testo "@materia_curricolare_2:nome @materia_EDCIVICA:nome @docente_curricolare_2:cognome,nome" in PDF analizzato in una riga
  E vedi testo "@materia_curricolare_3:nome @materia_EDCIVICA:nome @docente_curricolare_3:cognome,nome" in PDF analizzato in una riga
  E vedi testo "@materia_curricolare_4:nome @materia_EDCIVICA:nome @docente_curricolare_4:cognome,nome" in PDF analizzato in una riga
  E vedi testo "@materia_curricolare_5:nome @materia_EDCIVICA:nome @docente_curricolare_5:cognome,nome" in PDF analizzato in una riga
  E vedi testo "@materia_RELIGIONE:nome+, Educazione?@docente_religione_1:cognome+ +@docente_religione_1:nome" in PDF analizzato in una riga
  E vedi testo "@materia_itp_2:nome @materia_EDCIVICA:nome @docente_itp_2:cognome,nome" in PDF analizzato in una riga

Scenario: controllo verbale
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "Scarica" in sezione "#gs-main table tbody tr" che contiene "Verbale"
  E analizzi PDF "archivio/scrutini/primo/1A/1A-scrutinio-primo-quadrimestre.pdf"
  Allora vedi testo "Verbale n. 3" in PDF analizzato alla riga "2"
  E vedi testo "SCRUTINIO DEL PRIMO QUADRIMESTRE" in PDF analizzato alla riga "3"
  E vedi testo "1ª A" in PDF analizzato alla riga "4"
  E vedi testo "@docente_curricolare_2:cognome+ +@docente_curricolare_2:nome?@materia_curricolare_2:nome?@materia_EDCIVICA:nome" in PDF analizzato in "2" righe
  E vedi testo "@docente_curricolare_3:cognome+ +@docente_curricolare_3:nome?@materia_curricolare_3:nome?@materia_EDCIVICA:nome" in PDF analizzato in "2" righe
  E vedi testo "@docente_curricolare_4:cognome+ +@docente_curricolare_4:nome?@materia_curricolare_4:nome?@materia_EDCIVICA:nome" in PDF analizzato in "2" righe
  E vedi testo "@docente_curricolare_5:cognome+ +@docente_curricolare_5:nome?@materia_curricolare_5:nome?@materia_EDCIVICA:nome" in PDF analizzato in "2" righe
  E vedi testo "@docente_religione_1:cognome+ +@docente_religione_1:nome?@materia_RELIGIONE:nome?@materia_EDCIVICA:nome" in PDF analizzato in "2" righe
  E vedi testo "@docente_nocattedra_1:cognome+ +@docente_nocattedra_1:nome?@materia_RELIGIONE:nome?@materia_EDCIVICA:nome" in PDF analizzato in "2" righe
  E vedi testo "@docente_itp_1:cognome+ +@docente_itp_1:nome?@materia_itp_1:nome?@materia_EDCIVICA:nome" in PDF analizzato in "2" righe
  E vedi testo "@docente_itp_2:cognome+ +@docente_itp_2:nome?@materia_itp_1:nome?@materia_EDCIVICA:nome" in PDF analizzato in "2" righe
  E vedi testo "@docente_sostegno_1:cognome+ +@docente_sostegno_1:nome?@materia_SOSTEGNO:nome?@materia_EDCIVICA:nome" in PDF analizzato in "2" righe
  E vedi testo "@docente_sostegno_2:cognome+ +@docente_sostegno_2:nome?@materia_SOSTEGNO:nome?@materia_EDCIVICA:nome" in PDF analizzato in "2" righe
  E vedi poi testo "#str(assenti) @docente_curricolare_1:cognome,nome #str(sostituito) #str(Bianchi) #str(Maria) @materia_curricolare_1:nome @materia_EDCIVICA:nome" in PDF analizzato in "3" righe
  E vedi testo "@alunno_1A_1:cognome,nome @voto_P_1A_8:unico #str(UNANIMITÀ)" in PDF analizzato in una riga
  E vedi testo "@alunno_1A_2:cognome,nome @voto_P_1A_18:unico #str(UNANIMITÀ)" in PDF analizzato in una riga
  E vedi testo "@alunno_sostegno_1:cognome,nome @voto_P_1A_28:unico #str(MAGGIORANZA) #str(Contrari) #str(Tomba)" in PDF analizzato in "4" righe
  E vedi testo "@alunno_sostegno_2:cognome,nome @voto_P_1A_38:unico #str(MAGGIORANZA) #str(Contrari) #str(Tomba)" in PDF analizzato in "4" righe
  E vedi testo "@alunno_alternativa_1:cognome,nome @voto_P_1A_48:unico #str(UNANIMITÀ)" in PDF analizzato in una riga
  E vedi poi testo "@scrutinio_1A_P:dati[argomento][2]" in PDF analizzato in una riga
  E vedi poi testo "#str(Segretario) #str(Presidente) @docente_curricolare_2:cognome,nome #str(Bianchi) #str(Maria)" in PDF analizzato in "2" righe

Scenario: controllo verbale classe articolata
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe           |
    | @classe_3CAMB:id |
  Quando click su "Scarica" in sezione "#gs-main table tbody tr" che contiene "Verbale"
  E analizzi PDF "archivio/scrutini/primo/3CAMB/3CAMB-scrutinio-primo-quadrimestre.pdf"
  Allora vedi testo "Verbale n. 3" in PDF analizzato alla riga "2"
  E vedi testo "SCRUTINIO DEL PRIMO QUADRIMESTRE" in PDF analizzato alla riga "3"
  E vedi testo "3ª C-AMB" in PDF analizzato alla riga "4"
  E vedi testo "@docente_curricolare_1:cognome+ +@docente_curricolare_1:nome?@materia_curricolare_1:nome?@materia_EDCIVICA:nome" in PDF analizzato in "2" righe
  E vedi testo "@docente_curricolare_2:cognome+ +@docente_curricolare_2:nome?@materia_curricolare_2:nome?@materia_EDCIVICA:nome" in PDF analizzato in "2" righe
  E vedi testo "@docente_curricolare_3:cognome+ +@docente_curricolare_3:nome?@materia_curricolare_3:nome?@materia_EDCIVICA:nome" in PDF analizzato in "2" righe
  E vedi testo "@docente_curricolare_4:cognome+ +@docente_curricolare_4:nome?@materia_curricolare_4:nome?@materia_EDCIVICA:nome" in PDF analizzato in "2" righe
  E vedi testo "@docente_curricolare_5:cognome+ +@docente_curricolare_5:nome?@materia_curricolare_5:nome?@materia_EDCIVICA:nome" in PDF analizzato in "2" righe
  E vedi testo "@docente_religione_1:cognome+ +@docente_religione_1:nome?@materia_RELIGIONE:nome?@materia_EDCIVICA:nome" in PDF analizzato in "2" righe
  E vedi testo "@docente_itp_2:cognome+ +@docente_itp_2:nome?@materia_itp_2:nome?@materia_EDCIVICA:nome" in PDF analizzato in "2" righe
  E vedi testo "@alunno_3CAMB_1:cognome,nome @voto_P_3CAMB_8:unico #str(UNANIMITÀ)" in PDF analizzato in una riga
  E vedi testo "@alunno_3CAMB_2:cognome,nome @voto_P_3CAMB_18:unico #str(UNANIMITÀ)" in PDF analizzato in una riga
  E vedi testo "@alunno_sostegno_4:cognome,nome @voto_P_3CAMB_28:unico #str(UNANIMITÀ)" in PDF analizzato in "4" righe
  E vedi poi testo "@scrutinio_3CAMB_P:dati[argomento][2]" in PDF analizzato in una riga
  E vedi poi testo "#str(Segretario) #str(Presidente) @docente_curricolare_1:cognome,nome @preside:nome,cognome" in PDF analizzato in "2" righe

Scenario: controllo pagella
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "{{@alunno_1A_1:cognome}} {{@alunno_1A_1:nome}}" in sezione "#gs-main table tbody tr" che contiene "Comunicazione Pagella"
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

Scenario: controllo pagella per classe articolata
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe           |
    | @classe_3CAMB:id |
  Quando click su "{{@alunno_3CAMB_1:cognome}} {{@alunno_3CAMB_1:nome}}" in sezione "#gs-main table tbody tr" che contiene "Comunicazione Pagella"
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

Scenario: controllo debiti
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "{{@alunno_1A_1:cognome}} {{@alunno_1A_1:nome}}" in sezione "#gs-main table tbody tr" che contiene "Comunicazione Debiti"
  E analizzi PDF "archivio/scrutini/primo/1A/1A-debiti-primo-quadrimestre-{{@alunno_1A_1:id}}.pdf"
  Allora vedi testo "@alunno_1A_1:cognome @alunno_1A_1:nome" in PDF analizzato alla riga "3"
  E vedi testo "1ª A" in PDF analizzato alla riga "4"
  E vedi poi testo "#str(MATERIA) #str(VOTO) #str(Argomenti) #str(Modalità)" in PDF analizzato in una riga
  E vedi poi testo "@materia_curricolare_1:nome #cas(@voto_P_1A_0:unico,0,NC,@voto_P_1A_0:unico) #str(Argomento...) #str(Studio) #str(individuale)" in PDF analizzato in "2" righe
  E vedi poi testo "@materia_curricolare_2:nome #cas(@voto_P_1A_1:unico,0,NC,@voto_P_1A_1:unico) #str(Argomento...) #str(Studio) #str(individuale)" in PDF analizzato in "2" righe

Scenario: controllo debiti per classe articolata
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe           |
    | @classe_3CAMB:id |
  Quando click su "{{@alunno_3CAMB_1:cognome}} {{@alunno_3CAMB_1:nome}}" in sezione "#gs-main table tbody tr" che contiene "Comunicazione Debiti"
  E analizzi PDF "archivio/scrutini/primo/3CAMB/3CAMB-debiti-primo-quadrimestre-{{@alunno_3CAMB_1:id}}.pdf"
  Allora vedi testo "@alunno_3CAMB_1:cognome @alunno_3CAMB_1:nome" in PDF analizzato alla riga "3"
  E vedi testo "3ª C-AMB" in PDF analizzato alla riga "4"
  E vedi poi testo "#str(MATERIA) #str(VOTO) #str(Argomenti) #str(Modalità)" in PDF analizzato in una riga
  E vedi poi testo "@materia_curricolare_1:nome #cas(@voto_P_3CAMB_0:unico,0,NC,@voto_P_3CAMB_0:unico) #str(Argomento...) #str(Studio) #str(individuale)" in PDF analizzato in "2" righe
  E vedi poi testo "@materia_curricolare_2:nome #cas(@voto_P_3CAMB_1:unico,0,NC,@voto_P_3CAMB_1:unico) #str(Argomento...) #str(Studio) #str(individuale)" in PDF analizzato in "2" righe
  E vedi poi testo "@materia_itp_2:nome #cas(@voto_P_3CAMB_6:unico,0,NC,@voto_P_3CAMB_6:unico) #str(Argomento...) #str(Studio) #str(individuale)" in PDF analizzato in "2" righe
