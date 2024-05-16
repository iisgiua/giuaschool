# language: it

Funzionalità: ultimo passo dello scrutinio finale
  Per svolgere l'ultimo passo dello scrutinio finale
  Come utente staff
  Bisogna controllare visualizzazione della pagina
  Bisogna controllare visualizzazione con la classe articolata
  Bisogna controllare documenti generati
  Utilizzando "_scrutinioFCFixtures.yml"


Contesto: login utente staff
	Dato login utente con ruolo esatto "Staff"


################################################################################
# Bisogna controllare visualizzazione della pagina

Scenario: visualizzazione pagina passo finale
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Allora la sezione "#gs-main h2" contiene "Scrutinio chiuso"
  E la sezione "#gs-main table caption" contiene "Scrutinio finale"
  E vedi la tabella:
    | Documento                      | Note                                                          | Azioni                                                                                               |
    | Verbale                        | /duplice copia.*Deve firmare il presidente e il segretario/ui | Scarica                                                                                              |
    | Riepilogo voti                 | /duplice copia.*Devono firmare tutti i docenti/ui             | Scarica                                                                                              |
    | Foglio firme Registro dei voti | /unica copia.*Devono firmare tutti i docenti/ui               | Scarica                                                                                              |
    | Tabellone esiti                | pubblicato sul Registro Elettronico                           | Scarica                                                                                              |
    | Comunicazione Non Ammessi      | pubblicato sul Registro Elettronico                           | ?@alunno_1A_2:cognome+ +@alunno_1A_2:nome?@alunno_alternativa_1:cognome+ +@alunno_alternativa_1:nome |
    | Comunicazione Debiti           | pubblicato sul Registro Elettronico                           | @alunno_1A_1:cognome+ +@alunno_1A_1:nome                                                             |
    | Comunicazione Carenze          | pubblicato sul Registro Elettronico                           | ?@alunno_1A_1:cognome+ +@alunno_1A_1:nome?@alunno_sostegno_1:cognome+ +@alunno_sostegno_1:nome       |

Scenario: visualizzazione pagina passo finale per le classi seconde
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_2A:id |
  Allora la sezione "#gs-main h2" contiene "Scrutinio chiuso"
  E la sezione "#gs-main table caption" contiene "Scrutinio finale"
  E vedi la tabella:
    | Documento                      | Note                                                          | Azioni                                   |
    | Verbale                        | /duplice copia.*Deve firmare il presidente e il segretario/ui | Scarica                                  |
    | Riepilogo voti                 | /duplice copia.*Devono firmare tutti i docenti/ui             | Scarica                                  |
    | Foglio firme Registro dei voti | /unica copia.*Devono firmare tutti i docenti/ui               | Scarica                                  |
    | Certificazione competenze      | unica copia                                                   | Scarica                                  |
    | Tabellone esiti                | pubblicato sul Registro Elettronico                           | Scarica                                  |
    | Comunicazione Non Ammessi      | pubblicato sul Registro Elettronico                           | @alunno_2A_1:cognome+ +@alunno_2A_1:nome |
    | Comunicazione Debiti           | pubblicato sul Registro Elettronico                           | @alunno_2A_7:cognome+ +@alunno_2A_7:nome |
    | Comunicazione Carenze          | pubblicato sul Registro Elettronico                           | NESSUNA COMUNICAZIONE                    |

Scenario: visualizzazione passo precedente
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "Riapri lo scrutinio"
  Allora la sezione "#gs-main h2" contiene "Passo 8"

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
  E click su "Compila la comunicazione dei debiti" in sezione "#gs-main form > div:nth-child(3) > table > tbody > tr" che contiene "@alunno_1A_1:cognome+ +@alunno_1A_1:nome"
  E click su "Conferma"
  E click su "Compila la comunicazione delle carenze" in sezione "#gs-main form > div:nth-child(4) > table > tbody > tr" che contiene "@alunno_1A_1:cognome+ +@alunno_1A_1:nome"
  E click su "Conferma"
  E click su "Compila la comunicazione delle carenze" in sezione "#gs-main form > div:nth-child(4) > table > tbody > tr" che contiene "@alunno_sostegno_1:cognome+ +@alunno_sostegno_1:nome"
  E click su "Conferma"
  E click su "Compila la comunicazione delle carenze" in sezione "#gs-main form > div:nth-child(4) > table > tbody > tr" che contiene "@alunno_sostegno_2:cognome+ +@alunno_sostegno_2:nome"
  E click su "Conferma"
  E click su "Conferma"
  E click su "Chiudi lo scrutinio"
  Allora la sezione "#gs-main h2" contiene "Scrutinio chiuso"
  E vedi la tabella:
    | Documento                      | Note                                                          | Azioni                                                                                               |
    | Verbale                        | /duplice copia.*Deve firmare il presidente e il segretario/ui | Scarica                                                                                              |
    | Riepilogo voti                 | /duplice copia.*Devono firmare tutti i docenti/ui             | Scarica                                                                                              |
    | Foglio firme Registro dei voti | /unica copia.*Devono firmare tutti i docenti/ui               | Scarica                                                                                              |
    | Tabellone esiti                | pubblicato sul Registro Elettronico                           | Scarica                                                                                              |
    | Comunicazione Non Ammessi      | pubblicato sul Registro Elettronico                           | ?@alunno_1A_2:cognome+ +@alunno_1A_2:nome?@alunno_alternativa_1:cognome+ +@alunno_alternativa_1:nome |
    | Comunicazione Debiti           | pubblicato sul Registro Elettronico                           | @alunno_1A_1:cognome+ +@alunno_1A_1:nome                                                             |
    | Comunicazione Carenze          | pubblicato sul Registro Elettronico                           | ?@alunno_1A_1:cognome+ +@alunno_1A_1:nome?@alunno_sostegno_1:cognome+ +@alunno_sostegno_1:nome       |


################################################################################
# Bisogna controllare visualizzazione con la classe articolata

Scenario: visualizzazione classe articolata
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe           |
    | @classe_3CAMB:id |
  Allora la sezione "#gs-main h2" contiene "Scrutinio chiuso"
  E la sezione "#gs-main table caption" contiene "Scrutinio finale"
  E vedi la tabella:
    | Documento                      | Note                                                          | Azioni                                                                                         |
    | Verbale                        | /duplice copia.*Deve firmare il presidente e il segretario/ui | Scarica                                                                                        |
    | Riepilogo voti                 | /duplice copia.*Devono firmare tutti i docenti/ui             | Scarica                                                                                        |
    | Foglio firme Registro dei voti | /unica copia.*Devono firmare tutti i docenti/ui               | Scarica                                                                                        |
    | Tabellone esiti                | pubblicato sul Registro Elettronico                           | Scarica                                                                                        |
    | Comunicazione Non Ammessi      | pubblicato sul Registro Elettronico                           | @alunno_sostegno_4:cognome+ +@alunno_sostegno_4:nome                                           |
    | Comunicazione Debiti           | pubblicato sul Registro Elettronico                           | @alunno_3CAMB_1:cognome+ +@alunno_3CAMB_1:nome                                                 |
    | Comunicazione Carenze          | pubblicato sul Registro Elettronico                           | ?@alunno_3CAMB_1:cognome+ +@alunno_3CAMB_1:nome?@alunno_3CAMB_2:cognome+ +@alunno_3CAMB_2:nome |

Scenario: visualizzazione procedura completa
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe           |
    | @classe_3CAMB:id |
  Quando click su "Riapri lo scrutinio"
  E click su "passo precedente"
  E click su "passo precedente"
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
  E click su "Inserisci il credito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_3CAMB_2:cognome+ +@alunno_3CAMB_2:nome"
  E click su "Conferma"
  E click su "Conferma"
  E click su "Compila la comunicazione dei debiti" in sezione "#gs-main form > div:nth-child(3) > table > tbody > tr" che contiene "@alunno_3CAMB_1:cognome+ +@alunno_3CAMB_1:nome"
  E click su "Conferma"
  E click su "Compila la comunicazione delle carenze" in sezione "#gs-main form > div:nth-child(4) > table > tbody > tr" che contiene "@alunno_3CAMB_1:cognome+ +@alunno_3CAMB_1:nome"
  E click su "Conferma"
  E click su "Compila la comunicazione delle carenze" in sezione "#gs-main form > div:nth-child(4) > table > tbody > tr" che contiene "@alunno_3CAMB_2:cognome+ +@alunno_3CAMB_2:nome"
  E click su "Conferma"
  E click su "Conferma"
  E click su "Chiudi lo scrutinio"
  Allora la sezione "#gs-main h2" contiene "Scrutinio chiuso"
  E vedi la tabella:
    | Documento                      | Note                                                          | Azioni                                                                                         |
    | Verbale                        | /duplice copia.*Deve firmare il presidente e il segretario/ui | Scarica                                                                                        |
    | Riepilogo voti                 | /duplice copia.*Devono firmare tutti i docenti/ui             | Scarica                                                                                        |
    | Foglio firme Registro dei voti | /unica copia.*Devono firmare tutti i docenti/ui               | Scarica                                                                                        |
    | Tabellone esiti                | pubblicato sul Registro Elettronico                           | Scarica                                                                                        |
    | Comunicazione Non Ammessi      | pubblicato sul Registro Elettronico                           | @alunno_sostegno_4:cognome+ +@alunno_sostegno_4:nome                                           |
    | Comunicazione Debiti           | pubblicato sul Registro Elettronico                           | @alunno_3CAMB_1:cognome+ +@alunno_3CAMB_1:nome                                                 |
    | Comunicazione Carenze          | pubblicato sul Registro Elettronico                           | ?@alunno_3CAMB_1:cognome+ +@alunno_3CAMB_1:nome?@alunno_3CAMB_2:cognome+ +@alunno_3CAMB_2:nome |


################################################################################
# Bisogna controllare documenti generati

Scenario: controllo riepilogo dei voti
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "Scarica" in sezione "#gs-main table tbody tr" che contiene "Riepilogo voti"
  E analizzi PDF "archivio/scrutini/finale/1A/1A-scrutinio-finale-riepilogo-voti.pdf"
  Allora vedi testo "RIEPILOGO VOTI 1ª A" in PDF analizzato alla riga "1"
  E vedi testo "@alunno_1A_6:cognome,nome #noc() #str(ANNO)" in PDF analizzato in una riga
  E vedi poi testo "#dat(@alunno_1A_6:dataNascita) #noc() #str(ALL'ESTERO)" in PDF analizzato in una riga
  E vedi testo "@alunno_1A_1:cognome,nome #str(Voto) #cas(@voto_F_1A_0:unico,0,NC,@voto_F_1A_0:unico) #cas(@voto_F_1A_1:unico,0,NC,@voto_F_1A_1:unico) #cas(@voto_F_1A_2:unico,0,NC,@voto_F_1A_2:unico) #cas(@voto_F_1A_3:unico,0,NC,@voto_F_1A_3:unico) #cas(@voto_F_1A_5:unico,0,NC,@voto_F_1A_5:unico) #cas(@voto_F_1A_4:unico,0,NC,@voto_F_1A_4:unico) #cas(@voto_F_1A_6:unico,2,NC,@voto_F_1A_6:unico) #cas(@voto_F_1A_7:unico,4,NC,@voto_F_1A_7:unico) #str(SOSPENSIONE)" in PDF analizzato in una riga
  E vedi poi testo "#str(///) @voto_F_1A_0:assenze @voto_F_1A_1:assenze @voto_F_1A_2:assenze @voto_F_1A_3:assenze @voto_F_1A_5:assenze @voto_F_1A_4:assenze @voto_F_1A_6:assenze #med(@voto_F_1A_0:unico,@voto_F_1A_1:unico,@voto_F_1A_2:unico,@voto_F_1A_3:unico,@voto_F_1A_4:unico,@voto_F_1A_5:unico,@voto_F_1A_6:unico,@voto_F_1A_7:unico)" in PDF analizzato in una riga
  E vedi testo "@alunno_1A_2:cognome,nome #noc() #str(NON)" in PDF analizzato in una riga
  E vedi poi testo "#dat(@alunno_1A_2:dataNascita) #str(Ass.) @voto_F_1A_16:assenze @voto_F_1A_10:assenze @voto_F_1A_11:assenze @voto_F_1A_12:assenze @voto_F_1A_13:assenze @voto_F_1A_15:assenze @voto_F_1A_14:assenze @voto_F_1A_17:assenze" in PDF analizzato in una riga
  E vedi testo "@alunno_sostegno_1:cognome,nome #str(Voto) #cas(@voto_F_1A_26:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) #cas(@voto_F_1A_20:unico,0,NC,@voto_F_1A_20:unico) #cas(@voto_F_1A_21:unico,0,NC,@voto_F_1A_21:unico) #cas(@voto_F_1A_22:unico,0,NC,@voto_F_1A_22:unico) #cas(@voto_F_1A_23:unico,0,NC,@voto_F_1A_23:unico) #cas(@voto_F_1A_25:unico,0,NC,@voto_F_1A_25:unico) #cas(@voto_F_1A_24:unico,0,NC,@voto_F_1A_24:unico) #cas(@voto_F_1A_27:unico,2,NC,@voto_F_1A_27:unico) #cas(@voto_F_1A_28:unico,4,NC,@voto_F_1A_28:unico) #nos(NON)" in PDF analizzato in una riga
  E vedi poi testo "#dat(@alunno_sostegno_1:dataNascita) #str(Ass.) @voto_F_1A_26:assenze @voto_F_1A_20:assenze @voto_F_1A_21:assenze @voto_F_1A_22:assenze @voto_F_1A_23:assenze @voto_F_1A_25:assenze @voto_F_1A_24:assenze @voto_F_1A_27:assenze #med(@voto_F_1A_20:unico,@voto_F_1A_21:unico,@voto_F_1A_22:unico,@voto_F_1A_23:unico,@voto_F_1A_24:unico,@voto_F_1A_25:unico,@voto_F_1A_27:unico,@voto_F_1A_28:unico) #str(AMMESSO)" in PDF analizzato in una riga
  E vedi testo "@alunno_sostegno_2:cognome,nome #str(Voto) #cas(@voto_F_1A_36:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) #cas(@voto_F_1A_30:unico,0,NC,@voto_F_1A_30:unico) #cas(@voto_F_1A_31:unico,0,NC,@voto_F_1A_31:unico) #cas(@voto_F_1A_32:unico,0,NC,@voto_F_1A_32:unico) #cas(@voto_F_1A_33:unico,0,NC,@voto_F_1A_33:unico) #cas(@voto_F_1A_35:unico,0,NC,@voto_F_1A_35:unico) #cas(@voto_F_1A_34:unico,0,NC,@voto_F_1A_34:unico) #cas(@voto_F_1A_37:unico,2,NC,@voto_F_1A_37:unico) #cas(@voto_F_1A_38:unico,4,NC,@voto_F_1A_38:unico) #nos(NON)" in PDF analizzato in una riga
  E vedi poi testo "#dat(@alunno_sostegno_2:dataNascita) #str(Ass.) @voto_F_1A_36:assenze @voto_F_1A_30:assenze @voto_F_1A_31:assenze @voto_F_1A_32:assenze @voto_F_1A_33:assenze @voto_F_1A_35:assenze @voto_F_1A_34:assenze @voto_F_1A_37:assenze #med(@voto_F_1A_30:unico,@voto_F_1A_31:unico,@voto_F_1A_32:unico,@voto_F_1A_33:unico,@voto_F_1A_34:unico,@voto_F_1A_35:unico,@voto_F_1A_37:unico,@voto_F_1A_38:unico) #str(AMMESSO)" in PDF analizzato in una riga
  E vedi testo "@alunno_alternativa_1:cognome,nome #str(Voto) #cas(@voto_F_1A_46:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) #cas(@voto_F_1A_40:unico,0,NC,@voto_F_1A_40:unico) #cas(@voto_F_1A_41:unico,0,NC,@voto_F_1A_41:unico) #cas(@voto_F_1A_42:unico,0,NC,@voto_F_1A_42:unico) #cas(@voto_F_1A_43:unico,0,NC,@voto_F_1A_43:unico) #cas(@voto_F_1A_45:unico,0,NC,@voto_F_1A_45:unico) #cas(@voto_F_1A_44:unico,0,NC,@voto_F_1A_44:unico) #cas(@voto_F_1A_47:unico,2,NC,@voto_F_1A_47:unico) #cas(@voto_F_1A_48:unico,4,NC,@voto_F_1A_48:unico) #str(NON)" in PDF analizzato in una riga
  E vedi poi testo "#dat(@alunno_alternativa_1:dataNascita) #str(Ass.) @voto_F_1A_46:assenze @voto_F_1A_40:assenze @voto_F_1A_41:assenze @voto_F_1A_42:assenze @voto_F_1A_43:assenze @voto_F_1A_45:assenze @voto_F_1A_44:assenze @voto_F_1A_47:assenze #med(@voto_F_1A_40:unico,@voto_F_1A_41:unico,@voto_F_1A_42:unico,@voto_F_1A_43:unico,@voto_F_1A_44:unico,@voto_F_1A_45:unico,@voto_F_1A_47:unico,@voto_F_1A_48:unico)" in PDF analizzato in una riga
  E vedi poi testo "?#str(Bianchi)+ Maria?@docente_curricolare_2:cognome+ +@docente_curricolare_2:nome?@docente_curricolare_3:cognome+ +@docente_curricolare_3:nome?@docente_curricolare_4:cognome+ +@docente_curricolare_4:nome?@docente_curricolare_5:cognome+ +@docente_curricolare_5:nome?@docente_religione_1:cognome+ +@docente_religione_1:nome?@docente_itp_1:cognome+ +@docente_itp_1:nome?@docente_itp_2:cognome+ +@docente_itp_2:nome?@docente_sostegno_1:cognome+ +@docente_sostegno_1:nome?@docente_sostegno_2:cognome+ +@docente_sostegno_2:nome?@docente_nocattedra_1:cognome+ +@docente_nocattedra_1:nome" in PDF analizzato in "7" righe
  E vedi poi testo "/Data +11\/06\/2020 +Il Presidente +\(Prof.ssa Bianchi Maria\)/" in PDF analizzato in "2" righe

Scenario: controllo riepilogo dei voti - classe terza
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_3A:id |
  Quando click su "Scarica" in sezione "#gs-main table tbody tr" che contiene "Riepilogo voti"
  E analizzi PDF "archivio/scrutini/finale/3A/3A-scrutinio-finale-riepilogo-voti.pdf"
  Allora vedi testo "RIEPILOGO VOTI 3ª A" in PDF analizzato alla riga "1"
  E vedi testo "@alunno_3A_1:cognome,nome #str(Voto) #cas(@voto_F_3A_0:unico,0,NC,@voto_F_3A_0:unico) #cas(@voto_F_3A_1:unico,0,NC,@voto_F_3A_1:unico) #cas(@voto_F_3A_2:unico,0,NC,@voto_F_3A_2:unico) #cas(@voto_F_3A_3:unico,0,NC,@voto_F_3A_3:unico) #cas(@voto_F_3A_5:unico,0,NC,@voto_F_3A_5:unico) #cas(@voto_F_3A_4:unico,0,NC,@voto_F_3A_4:unico) #cas(@voto_F_3A_6:unico,0,NC,@voto_F_3A_6:unico) #cas(@voto_F_3A_7:unico,2,NC,@voto_F_3A_7:unico) #str(NON)" in PDF analizzato in una riga
  E vedi poi testo "#str(///) @voto_F_3A_0:assenze @voto_F_3A_1:assenze @voto_F_3A_2:assenze @voto_F_3A_3:assenze @voto_F_3A_5:assenze @voto_F_3A_4:assenze @voto_F_3A_6:assenze #med(@voto_F_3A_0:unico,@voto_F_3A_1:unico,@voto_F_3A_2:unico,@voto_F_3A_3:unico,@voto_F_3A_4:unico,@voto_F_3A_5:unico,@voto_F_3A_6:unico,@voto_F_3A_7:unico)" in PDF analizzato in una riga
  E vedi testo "@alunno_3A_2:cognome,nome #str(Voto) #cas(@voto_F_3A_16:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) #cas(@voto_F_3A_10:unico,0,NC,@voto_F_3A_10:unico) #cas(@voto_F_3A_11:unico,0,NC,@voto_F_3A_11:unico) #cas(@voto_F_3A_12:unico,0,NC,@voto_F_3A_12:unico) #cas(@voto_F_3A_13:unico,0,NC,@voto_F_3A_13:unico) #cas(@voto_F_3A_15:unico,0,NC,@voto_F_3A_15:unico) #cas(@voto_F_3A_14:unico,0,NC,@voto_F_3A_14:unico) #cas(@voto_F_3A_17:unico,0,NC,@voto_F_3A_17:unico) #cas(@voto_F_3A_18:unico,2,NC,@voto_F_3A_18:unico) #nos(NON)" in PDF analizzato in una riga
  E vedi poi testo "#dat(@alunno_3A_2:dataNascita) #str(Ass.) @voto_F_3A_16:assenze @voto_F_3A_10:assenze @voto_F_3A_11:assenze @voto_F_3A_12:assenze @voto_F_3A_13:assenze @voto_F_3A_15:assenze @voto_F_3A_14:assenze @voto_F_3A_17:assenze #str(7) #med(@voto_F_3A_10:unico,@voto_F_3A_11:unico,@voto_F_3A_12:unico,@voto_F_3A_13:unico,@voto_F_3A_14:unico,@voto_F_3A_15:unico,@voto_F_3A_17:unico,@voto_F_3A_18:unico)" in PDF analizzato in una riga
  E vedi testo "@alunno_3A_3:cognome,nome #str(Voto) #cas(@voto_F_3A_26:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) #cas(@voto_F_3A_20:unico,0,NC,@voto_F_3A_20:unico) #cas(@voto_F_3A_21:unico,0,NC,@voto_F_3A_21:unico) #cas(@voto_F_3A_22:unico,0,NC,@voto_F_3A_22:unico) #cas(@voto_F_3A_23:unico,0,NC,@voto_F_3A_23:unico) #cas(@voto_F_3A_25:unico,0,NC,@voto_F_3A_25:unico) #cas(@voto_F_3A_24:unico,0,NC,@voto_F_3A_24:unico) #cas(@voto_F_3A_27:unico,0,NC,@voto_F_3A_27:unico) #cas(@voto_F_3A_28:unico,2,NC,@voto_F_3A_28:unico) #nos(NON)" in PDF analizzato in una riga
  E vedi poi testo "#dat(@alunno_3A_3:dataNascita) #str(Ass.) @voto_F_3A_26:assenze @voto_F_3A_20:assenze @voto_F_3A_21:assenze @voto_F_3A_22:assenze @voto_F_3A_23:assenze @voto_F_3A_25:assenze @voto_F_3A_24:assenze @voto_F_3A_27:assenze #str(8) #med(@voto_F_3A_20:unico,@voto_F_3A_21:unico,@voto_F_3A_22:unico,@voto_F_3A_23:unico,@voto_F_3A_24:unico,@voto_F_3A_25:unico,@voto_F_3A_27:unico,@voto_F_3A_28:unico)" in PDF analizzato in una riga
  E vedi testo "@alunno_3A_4:cognome,nome #str(Voto) #cas(@voto_F_3A_36:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) #cas(@voto_F_3A_30:unico,0,NC,@voto_F_3A_30:unico) #cas(@voto_F_3A_31:unico,0,NC,@voto_F_3A_31:unico) #cas(@voto_F_3A_32:unico,0,NC,@voto_F_3A_32:unico) #cas(@voto_F_3A_33:unico,0,NC,@voto_F_3A_33:unico) #cas(@voto_F_3A_35:unico,0,NC,@voto_F_3A_35:unico) #cas(@voto_F_3A_34:unico,0,NC,@voto_F_3A_34:unico) #cas(@voto_F_3A_37:unico,0,NC,@voto_F_3A_37:unico) #cas(@voto_F_3A_38:unico,2,NC,@voto_F_3A_38:unico) #nos(NON)" in PDF analizzato in una riga
  E vedi poi testo "#dat(@alunno_3A_4:dataNascita) #str(Ass.) @voto_F_3A_36:assenze @voto_F_3A_30:assenze @voto_F_3A_31:assenze @voto_F_3A_32:assenze @voto_F_3A_33:assenze @voto_F_3A_35:assenze @voto_F_3A_34:assenze @voto_F_3A_37:assenze #str(10) #med(@voto_F_3A_30:unico,@voto_F_3A_31:unico,@voto_F_3A_32:unico,@voto_F_3A_33:unico,@voto_F_3A_34:unico,@voto_F_3A_35:unico,@voto_F_3A_37:unico,@voto_F_3A_38:unico)" in PDF analizzato in una riga
  E vedi testo "@alunno_3A_5:cognome,nome #str(Voto) #cas(@voto_F_3A_36:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) #cas(@voto_F_3A_40:unico,0,NC,@voto_F_3A_40:unico) #cas(@voto_F_3A_41:unico,0,NC,@voto_F_3A_41:unico) #cas(@voto_F_3A_42:unico,0,NC,@voto_F_3A_42:unico) #cas(@voto_F_3A_43:unico,0,NC,@voto_F_3A_43:unico) #cas(@voto_F_3A_45:unico,0,NC,@voto_F_3A_45:unico) #cas(@voto_F_3A_44:unico,0,NC,@voto_F_3A_44:unico) #cas(@voto_F_3A_47:unico,0,NC,@voto_F_3A_47:unico) #cas(@voto_F_3A_48:unico,2,NC,@voto_F_3A_48:unico) #nos(NON)" in PDF analizzato in una riga
  E vedi poi testo "#dat(@alunno_3A_5:dataNascita) #str(Ass.) @voto_F_3A_46:assenze @voto_F_3A_40:assenze @voto_F_3A_41:assenze @voto_F_3A_42:assenze @voto_F_3A_43:assenze @voto_F_3A_45:assenze @voto_F_3A_44:assenze @voto_F_3A_47:assenze #str(11) #med(@voto_F_3A_40:unico,@voto_F_3A_41:unico,@voto_F_3A_42:unico,@voto_F_3A_43:unico,@voto_F_3A_44:unico,@voto_F_3A_45:unico,@voto_F_3A_47:unico,@voto_F_3A_48:unico)" in PDF analizzato in una riga
  E vedi testo "@alunno_3A_6:cognome,nome #str(Voto) #cas(@voto_F_3A_56:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) #cas(@voto_F_3A_50:unico,0,NC,@voto_F_3A_50:unico) #cas(@voto_F_3A_51:unico,0,NC,@voto_F_3A_51:unico) #cas(@voto_F_3A_52:unico,0,NC,@voto_F_3A_52:unico) #cas(@voto_F_3A_53:unico,0,NC,@voto_F_3A_53:unico) #cas(@voto_F_3A_55:unico,0,NC,@voto_F_3A_55:unico) #cas(@voto_F_3A_54:unico,0,NC,@voto_F_3A_54:unico) #cas(@voto_F_3A_57:unico,0,NC,@voto_F_3A_57:unico) #cas(@voto_F_3A_58:unico,2,NC,@voto_F_3A_58:unico) #nos(NON)" in PDF analizzato in una riga
  E vedi poi testo "#dat(@alunno_3A_6:dataNascita) #str(Ass.) @voto_F_3A_56:assenze @voto_F_3A_50:assenze @voto_F_3A_51:assenze @voto_F_3A_52:assenze @voto_F_3A_53:assenze @voto_F_3A_55:assenze @voto_F_3A_54:assenze @voto_F_3A_57:assenze #str(12) #med(@voto_F_3A_50:unico,@voto_F_3A_51:unico,@voto_F_3A_52:unico,@voto_F_3A_53:unico,@voto_F_3A_54:unico,@voto_F_3A_55:unico,@voto_F_3A_57:unico,@voto_F_3A_58:unico)" in PDF analizzato in una riga
  E vedi testo "@alunno_3A_7:cognome,nome #str(Voto) #cas(@voto_F_3A_66:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) #cas(@voto_F_3A_60:unico,0,NC,@voto_F_3A_60:unico) #cas(@voto_F_3A_61:unico,0,NC,@voto_F_3A_61:unico) #cas(@voto_F_3A_62:unico,0,NC,@voto_F_3A_62:unico) #cas(@voto_F_3A_63:unico,0,NC,@voto_F_3A_63:unico) #cas(@voto_F_3A_65:unico,0,NC,@voto_F_3A_65:unico) #cas(@voto_F_3A_64:unico,0,NC,@voto_F_3A_64:unico) #cas(@voto_F_3A_67:unico,0,NC,@voto_F_3A_67:unico) #cas(@voto_F_3A_68:unico,2,NC,@voto_F_3A_68:unico) #str(SOSPENSIONE)" in PDF analizzato in una riga
  E vedi poi testo "#dat(@alunno_3A_7:dataNascita) #str(Ass.) @voto_F_3A_66:assenze @voto_F_3A_60:assenze @voto_F_3A_61:assenze @voto_F_3A_62:assenze @voto_F_3A_63:assenze @voto_F_3A_65:assenze @voto_F_3A_64:assenze @voto_F_3A_67:assenze #med(@voto_F_3A_60:unico,@voto_F_3A_61:unico,@voto_F_3A_62:unico,@voto_F_3A_63:unico,@voto_F_3A_64:unico,@voto_F_3A_65:unico,@voto_F_3A_67:unico,@voto_F_3A_68:unico)" in PDF analizzato in una riga
  E vedi poi testo "?@docente_curricolare_1:cognome+ +@docente_curricolare_1:nome?@docente_curricolare_2:cognome+ +@docente_curricolare_2:nome?@docente_curricolare_3:cognome+ +@docente_curricolare_3:nome?@docente_curricolare_4:cognome+ +@docente_curricolare_4:nome?@docente_curricolare_5:cognome+ +@docente_curricolare_5:nome?@docente_religione_1:cognome+ +@docente_religione_1:nome?@docente_itp_1:cognome+ +@docente_itp_1:nome" in PDF analizzato in "5" righe
  E vedi poi testo "#str(Data) #str(11/06/2020) #str(Presidente) @docente_curricolare_1:cognome,nome" in PDF analizzato in "2" righe

Scenario: controllo riepilogo dei voti - classe quarta
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_4A:id |
  Quando click su "Scarica" in sezione "#gs-main table tbody tr" che contiene "Riepilogo voti"
  E analizzi PDF "archivio/scrutini/finale/4A/4A-scrutinio-finale-riepilogo-voti.pdf"
  Allora vedi testo "RIEPILOGO VOTI 4ª A" in PDF analizzato alla riga "1"
  E vedi testo "@alunno_4A_1:cognome,nome #str(Voto) #cas(@voto_F_4A_0:unico,0,NC,@voto_F_4A_0:unico) #cas(@voto_F_4A_1:unico,0,NC,@voto_F_4A_1:unico) #cas(@voto_F_4A_2:unico,0,NC,@voto_F_4A_2:unico) #cas(@voto_F_4A_3:unico,0,NC,@voto_F_4A_3:unico) #cas(@voto_F_4A_5:unico,0,NC,@voto_F_4A_5:unico) #cas(@voto_F_4A_4:unico,0,NC,@voto_F_4A_4:unico) #cas(@voto_F_4A_6:unico,0,NC,@voto_F_4A_6:unico) #cas(@voto_F_4A_7:unico,2,NC,@voto_F_4A_7:unico) #str(NON)" in PDF analizzato in una riga
  E vedi poi testo "#str(///) @voto_F_4A_0:assenze @voto_F_4A_1:assenze @voto_F_4A_2:assenze @voto_F_4A_3:assenze @voto_F_4A_5:assenze @voto_F_4A_4:assenze @voto_F_4A_6:assenze #med(@voto_F_4A_0:unico,@voto_F_4A_1:unico,@voto_F_4A_2:unico,@voto_F_4A_3:unico,@voto_F_4A_4:unico,@voto_F_4A_5:unico,@voto_F_4A_6:unico,@voto_F_4A_7:unico)" in PDF analizzato in una riga
  E vedi testo "@alunno_4A_2:cognome,nome #str(Voto) #cas(@voto_F_4A_16:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) #cas(@voto_F_4A_10:unico,0,NC,@voto_F_4A_10:unico) #cas(@voto_F_4A_11:unico,0,NC,@voto_F_4A_11:unico) #cas(@voto_F_4A_12:unico,0,NC,@voto_F_4A_12:unico) #cas(@voto_F_4A_13:unico,0,NC,@voto_F_4A_13:unico) #cas(@voto_F_4A_15:unico,0,NC,@voto_F_4A_15:unico) #cas(@voto_F_4A_14:unico,0,NC,@voto_F_4A_14:unico) #cas(@voto_F_4A_17:unico,0,NC,@voto_F_4A_17:unico) #cas(@voto_F_4A_18:unico,2,NC,@voto_F_4A_18:unico) #nos(NON)" in PDF analizzato in una riga
  E vedi poi testo "#dat(@alunno_4A_2:dataNascita) #str(Ass.) @voto_F_4A_16:assenze @voto_F_4A_10:assenze @voto_F_4A_11:assenze @voto_F_4A_12:assenze @voto_F_4A_13:assenze @voto_F_4A_15:assenze @voto_F_4A_14:assenze @voto_F_4A_17:assenze #str(8) @alunno_4A_2:credito3 #sum(8,@alunno_4A_2:credito3) #med(@voto_F_4A_10:unico,@voto_F_4A_11:unico,@voto_F_4A_12:unico,@voto_F_4A_13:unico,@voto_F_4A_14:unico,@voto_F_4A_15:unico,@voto_F_4A_17:unico,@voto_F_4A_18:unico)" in PDF analizzato in una riga
  E vedi testo "@alunno_4A_3:cognome,nome #str(Voto) #cas(@voto_F_4A_26:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) #cas(@voto_F_4A_20:unico,0,NC,@voto_F_4A_20:unico) #cas(@voto_F_4A_21:unico,0,NC,@voto_F_4A_21:unico) #cas(@voto_F_4A_22:unico,0,NC,@voto_F_4A_22:unico) #cas(@voto_F_4A_23:unico,0,NC,@voto_F_4A_23:unico) #cas(@voto_F_4A_25:unico,0,NC,@voto_F_4A_25:unico) #cas(@voto_F_4A_24:unico,0,NC,@voto_F_4A_24:unico) #cas(@voto_F_4A_27:unico,0,NC,@voto_F_4A_27:unico) #cas(@voto_F_4A_28:unico,2,NC,@voto_F_4A_28:unico) #nos(NON)" in PDF analizzato in una riga
  E vedi poi testo "#dat(@alunno_4A_3:dataNascita) #str(Ass.) @voto_F_4A_26:assenze @voto_F_4A_20:assenze @voto_F_4A_21:assenze @voto_F_4A_22:assenze @voto_F_4A_23:assenze @voto_F_4A_25:assenze @voto_F_4A_24:assenze @voto_F_4A_27:assenze #str(9) @alunno_4A_3:credito3 #sum(9,@alunno_4A_3:credito3) #med(@voto_F_4A_20:unico,@voto_F_4A_21:unico,@voto_F_4A_22:unico,@voto_F_4A_23:unico,@voto_F_4A_24:unico,@voto_F_4A_25:unico,@voto_F_4A_27:unico,@voto_F_4A_28:unico)" in PDF analizzato in una riga
  E vedi testo "@alunno_4A_4:cognome,nome #str(Voto) #cas(@voto_F_4A_36:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) #cas(@voto_F_4A_30:unico,0,NC,@voto_F_4A_30:unico) #cas(@voto_F_4A_31:unico,0,NC,@voto_F_4A_31:unico) #cas(@voto_F_4A_32:unico,0,NC,@voto_F_4A_32:unico) #cas(@voto_F_4A_33:unico,0,NC,@voto_F_4A_33:unico) #cas(@voto_F_4A_35:unico,0,NC,@voto_F_4A_35:unico) #cas(@voto_F_4A_34:unico,0,NC,@voto_F_4A_34:unico) #cas(@voto_F_4A_37:unico,0,NC,@voto_F_4A_37:unico) #cas(@voto_F_4A_38:unico,2,NC,@voto_F_4A_38:unico) #nos(NON)" in PDF analizzato in una riga
  E vedi poi testo "#dat(@alunno_4A_4:dataNascita) #str(Ass.) @voto_F_4A_36:assenze @voto_F_4A_30:assenze @voto_F_4A_31:assenze @voto_F_4A_32:assenze @voto_F_4A_33:assenze @voto_F_4A_35:assenze @voto_F_4A_34:assenze @voto_F_4A_37:assenze #str(11) @alunno_4A_4:credito3 #sum(11,@alunno_4A_4:credito3) #med(@voto_F_4A_30:unico,@voto_F_4A_31:unico,@voto_F_4A_32:unico,@voto_F_4A_33:unico,@voto_F_4A_34:unico,@voto_F_4A_35:unico,@voto_F_4A_37:unico,@voto_F_4A_38:unico)" in PDF analizzato in una riga
  E vedi testo "@alunno_4A_5:cognome,nome #str(Voto) #cas(@voto_F_4A_36:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) #cas(@voto_F_4A_40:unico,0,NC,@voto_F_4A_40:unico) #cas(@voto_F_4A_41:unico,0,NC,@voto_F_4A_41:unico) #cas(@voto_F_4A_42:unico,0,NC,@voto_F_4A_42:unico) #cas(@voto_F_4A_43:unico,0,NC,@voto_F_4A_43:unico) #cas(@voto_F_4A_45:unico,0,NC,@voto_F_4A_45:unico) #cas(@voto_F_4A_44:unico,0,NC,@voto_F_4A_44:unico) #cas(@voto_F_4A_47:unico,0,NC,@voto_F_4A_47:unico) #cas(@voto_F_4A_48:unico,2,NC,@voto_F_4A_48:unico) #nos(NON)" in PDF analizzato in una riga
  E vedi poi testo "#dat(@alunno_4A_5:dataNascita) #str(Ass.) @voto_F_4A_46:assenze @voto_F_4A_40:assenze @voto_F_4A_41:assenze @voto_F_4A_42:assenze @voto_F_4A_43:assenze @voto_F_4A_45:assenze @voto_F_4A_44:assenze @voto_F_4A_47:assenze #str(12) @alunno_4A_5:credito3 #sum(12,@alunno_4A_5:credito3) #med(@voto_F_4A_40:unico,@voto_F_4A_41:unico,@voto_F_4A_42:unico,@voto_F_4A_43:unico,@voto_F_4A_44:unico,@voto_F_4A_45:unico,@voto_F_4A_47:unico,@voto_F_4A_48:unico)" in PDF analizzato in una riga
  E vedi testo "@alunno_4A_6:cognome,nome #str(Voto) #cas(@voto_F_4A_56:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) #cas(@voto_F_4A_50:unico,0,NC,@voto_F_4A_50:unico) #cas(@voto_F_4A_51:unico,0,NC,@voto_F_4A_51:unico) #cas(@voto_F_4A_52:unico,0,NC,@voto_F_4A_52:unico) #cas(@voto_F_4A_53:unico,0,NC,@voto_F_4A_53:unico) #cas(@voto_F_4A_55:unico,0,NC,@voto_F_4A_55:unico) #cas(@voto_F_4A_54:unico,0,NC,@voto_F_4A_54:unico) #cas(@voto_F_4A_57:unico,0,NC,@voto_F_4A_57:unico) #cas(@voto_F_4A_58:unico,2,NC,@voto_F_4A_58:unico) #nos(NON)" in PDF analizzato in una riga
  E vedi poi testo "#dat(@alunno_4A_6:dataNascita) #str(Ass.) @voto_F_4A_56:assenze @voto_F_4A_50:assenze @voto_F_4A_51:assenze @voto_F_4A_52:assenze @voto_F_4A_53:assenze @voto_F_4A_55:assenze @voto_F_4A_54:assenze @voto_F_4A_57:assenze #str(13) @alunno_4A_6:credito3 #sum(13,@alunno_4A_6:credito3) #med(@voto_F_4A_50:unico,@voto_F_4A_51:unico,@voto_F_4A_52:unico,@voto_F_4A_53:unico,@voto_F_4A_54:unico,@voto_F_4A_55:unico,@voto_F_4A_57:unico,@voto_F_4A_58:unico)" in PDF analizzato in una riga
  E vedi testo "@alunno_4A_7:cognome,nome #str(Voto) #cas(@voto_F_4A_66:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) #cas(@voto_F_4A_60:unico,0,NC,@voto_F_4A_60:unico) #cas(@voto_F_4A_61:unico,0,NC,@voto_F_4A_61:unico) #cas(@voto_F_4A_62:unico,0,NC,@voto_F_4A_62:unico) #cas(@voto_F_4A_63:unico,0,NC,@voto_F_4A_63:unico) #cas(@voto_F_4A_65:unico,0,NC,@voto_F_4A_65:unico) #cas(@voto_F_4A_64:unico,0,NC,@voto_F_4A_64:unico) #cas(@voto_F_4A_67:unico,0,NC,@voto_F_4A_67:unico) #cas(@voto_F_4A_68:unico,2,NC,@voto_F_4A_68:unico) #str(SOSPENSIONE)" in PDF analizzato in una riga
  E vedi poi testo "#dat(@alunno_4A_7:dataNascita) #str(Ass.) @voto_F_4A_66:assenze @voto_F_4A_60:assenze @voto_F_4A_61:assenze @voto_F_4A_62:assenze @voto_F_4A_63:assenze @voto_F_4A_65:assenze @voto_F_4A_64:assenze @voto_F_4A_67:assenze #med(@voto_F_4A_60:unico,@voto_F_4A_61:unico,@voto_F_4A_62:unico,@voto_F_4A_63:unico,@voto_F_4A_64:unico,@voto_F_4A_65:unico,@voto_F_4A_67:unico,@voto_F_4A_68:unico)" in PDF analizzato in una riga
  E vedi poi testo "?@docente_curricolare_1:cognome+ +@docente_curricolare_1:nome?@docente_curricolare_2:cognome+ +@docente_curricolare_2:nome?@docente_curricolare_3:cognome+ +@docente_curricolare_3:nome?@docente_curricolare_4:cognome+ +@docente_curricolare_4:nome?@docente_curricolare_5:cognome+ +@docente_curricolare_5:nome?@docente_religione_1:cognome+ +@docente_religione_1:nome?@docente_itp_1:cognome+ +@docente_itp_1:nome" in PDF analizzato in "5" righe
  E vedi poi testo "#str(Data) #str(11/06/2020) #str(Presidente) @docente_curricolare_1:cognome,nome" in PDF analizzato in "2" righe

Scenario: controllo riepilogo dei voti - classe quinta
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_5A:id |
  Quando click su "Scarica" in sezione "#gs-main table tbody tr" che contiene "Riepilogo voti"
  E analizzi PDF "archivio/scrutini/finale/5A/5A-scrutinio-finale-riepilogo-voti.pdf"
  Allora vedi testo "RIEPILOGO VOTI 5ª A" in PDF analizzato alla riga "1"
  E vedi testo "@alunno_5A_1:cognome,nome #str(Voto) #cas(@voto_F_5A_0:unico,0,NC,@voto_F_5A_0:unico) #cas(@voto_F_5A_1:unico,0,NC,@voto_F_5A_1:unico) #cas(@voto_F_5A_2:unico,0,NC,@voto_F_5A_2:unico) #cas(@voto_F_5A_3:unico,0,NC,@voto_F_5A_3:unico) #cas(@voto_F_5A_5:unico,0,NC,@voto_F_5A_5:unico) #cas(@voto_F_5A_4:unico,0,NC,@voto_F_5A_4:unico) #cas(@voto_F_5A_6:unico,0,NC,@voto_F_5A_6:unico) #cas(@voto_F_5A_7:unico,2,NC,@voto_F_5A_7:unico) #str(NON)" in PDF analizzato in una riga
  E vedi poi testo "#str(///) @voto_F_5A_0:assenze @voto_F_5A_1:assenze @voto_F_5A_2:assenze @voto_F_5A_3:assenze @voto_F_5A_5:assenze @voto_F_5A_4:assenze @voto_F_5A_6:assenze #med(@voto_F_5A_0:unico,@voto_F_5A_1:unico,@voto_F_5A_2:unico,@voto_F_5A_3:unico,@voto_F_5A_4:unico,@voto_F_5A_5:unico,@voto_F_5A_6:unico,@voto_F_5A_7:unico)" in PDF analizzato in una riga
  E vedi testo "@alunno_5A_2:cognome,nome #str(Voto) #cas(@voto_F_5A_16:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) #cas(@voto_F_5A_10:unico,0,NC,@voto_F_5A_10:unico) #cas(@voto_F_5A_11:unico,0,NC,@voto_F_5A_11:unico) #cas(@voto_F_5A_12:unico,0,NC,@voto_F_5A_12:unico) #cas(@voto_F_5A_13:unico,0,NC,@voto_F_5A_13:unico) #cas(@voto_F_5A_15:unico,0,NC,@voto_F_5A_15:unico) #cas(@voto_F_5A_14:unico,0,NC,@voto_F_5A_14:unico) #cas(@voto_F_5A_17:unico,0,NC,@voto_F_5A_17:unico) #cas(@voto_F_5A_18:unico,2,NC,@voto_F_5A_18:unico) #nos(NON)" in PDF analizzato in una riga
  E vedi poi testo "#dat(@alunno_5A_2:dataNascita) #str(Ass.) @voto_F_5A_16:assenze @voto_F_5A_10:assenze @voto_F_5A_11:assenze @voto_F_5A_12:assenze @voto_F_5A_13:assenze @voto_F_5A_15:assenze @voto_F_5A_14:assenze @voto_F_5A_17:assenze #str(9) #sum(@alunno_5A_2:credito3,@alunno_5A_2:credito4) #sum(9,@alunno_5A_2:credito3,@alunno_5A_2:credito4) #med(@voto_F_5A_10:unico,@voto_F_5A_11:unico,@voto_F_5A_12:unico,@voto_F_5A_13:unico,@voto_F_5A_14:unico,@voto_F_5A_15:unico,@voto_F_5A_17:unico,@voto_F_5A_18:unico)" in PDF analizzato in una riga
  E vedi testo "@alunno_5A_3:cognome,nome #str(Voto) #cas(@voto_F_5A_26:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) #cas(@voto_F_5A_20:unico,0,NC,@voto_F_5A_20:unico) #cas(@voto_F_5A_21:unico,0,NC,@voto_F_5A_21:unico) #cas(@voto_F_5A_22:unico,0,NC,@voto_F_5A_22:unico) #cas(@voto_F_5A_23:unico,0,NC,@voto_F_5A_23:unico) #cas(@voto_F_5A_25:unico,0,NC,@voto_F_5A_25:unico) #cas(@voto_F_5A_24:unico,0,NC,@voto_F_5A_24:unico) #cas(@voto_F_5A_27:unico,0,NC,@voto_F_5A_27:unico) #cas(@voto_F_5A_28:unico,2,NC,@voto_F_5A_28:unico) #nos(NON)" in PDF analizzato in una riga
  E vedi poi testo "#dat(@alunno_5A_3:dataNascita) #str(Ass.) @voto_F_5A_26:assenze @voto_F_5A_20:assenze @voto_F_5A_21:assenze @voto_F_5A_22:assenze @voto_F_5A_23:assenze @voto_F_5A_25:assenze @voto_F_5A_24:assenze @voto_F_5A_27:assenze #str(10) #sum(@alunno_5A_3:credito3,@alunno_5A_3:credito4) #sum(10,@alunno_5A_3:credito3,@alunno_5A_3:credito4) #med(@voto_F_5A_20:unico,@voto_F_5A_21:unico,@voto_F_5A_22:unico,@voto_F_5A_23:unico,@voto_F_5A_24:unico,@voto_F_5A_25:unico,@voto_F_5A_27:unico,@voto_F_5A_28:unico)" in PDF analizzato in una riga
  E vedi testo "@alunno_5A_4:cognome,nome #str(Voto) #cas(@voto_F_5A_36:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) #cas(@voto_F_5A_30:unico,0,NC,@voto_F_5A_30:unico) #cas(@voto_F_5A_31:unico,0,NC,@voto_F_5A_31:unico) #cas(@voto_F_5A_32:unico,0,NC,@voto_F_5A_32:unico) #cas(@voto_F_5A_33:unico,0,NC,@voto_F_5A_33:unico) #cas(@voto_F_5A_35:unico,0,NC,@voto_F_5A_35:unico) #cas(@voto_F_5A_34:unico,0,NC,@voto_F_5A_34:unico) #cas(@voto_F_5A_37:unico,0,NC,@voto_F_5A_37:unico) #cas(@voto_F_5A_38:unico,2,NC,@voto_F_5A_38:unico) #nos(NON)" in PDF analizzato in una riga
  E vedi poi testo "#dat(@alunno_5A_4:dataNascita) #str(Ass.) @voto_F_5A_36:assenze @voto_F_5A_30:assenze @voto_F_5A_31:assenze @voto_F_5A_32:assenze @voto_F_5A_33:assenze @voto_F_5A_35:assenze @voto_F_5A_34:assenze @voto_F_5A_37:assenze #str(12) #sum(@alunno_5A_4:credito3,@alunno_5A_4:credito4) #sum(12,@alunno_5A_4:credito3,@alunno_5A_4:credito4) #med(@voto_F_5A_30:unico,@voto_F_5A_31:unico,@voto_F_5A_32:unico,@voto_F_5A_33:unico,@voto_F_5A_34:unico,@voto_F_5A_35:unico,@voto_F_5A_37:unico,@voto_F_5A_38:unico)" in PDF analizzato in una riga
  E vedi testo "@alunno_5A_5:cognome,nome #str(Voto) #cas(@voto_F_5A_36:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) #cas(@voto_F_5A_40:unico,0,NC,@voto_F_5A_40:unico) #cas(@voto_F_5A_41:unico,0,NC,@voto_F_5A_41:unico) #cas(@voto_F_5A_42:unico,0,NC,@voto_F_5A_42:unico) #cas(@voto_F_5A_43:unico,0,NC,@voto_F_5A_43:unico) #cas(@voto_F_5A_45:unico,0,NC,@voto_F_5A_45:unico) #cas(@voto_F_5A_44:unico,0,NC,@voto_F_5A_44:unico) #cas(@voto_F_5A_47:unico,0,NC,@voto_F_5A_47:unico) #cas(@voto_F_5A_48:unico,2,NC,@voto_F_5A_48:unico) #nos(NON)" in PDF analizzato in una riga
  E vedi poi testo "#dat(@alunno_5A_5:dataNascita) #str(Ass.) @voto_F_5A_46:assenze @voto_F_5A_40:assenze @voto_F_5A_41:assenze @voto_F_5A_42:assenze @voto_F_5A_43:assenze @voto_F_5A_45:assenze @voto_F_5A_44:assenze @voto_F_5A_47:assenze #str(14) #sum(@alunno_5A_5:credito3,@alunno_5A_5:credito4) #sum(14,@alunno_5A_5:credito3,@alunno_5A_5:credito4) #med(@voto_F_5A_40:unico,@voto_F_5A_41:unico,@voto_F_5A_42:unico,@voto_F_5A_43:unico,@voto_F_5A_44:unico,@voto_F_5A_45:unico,@voto_F_5A_47:unico,@voto_F_5A_48:unico)" in PDF analizzato in una riga
  E vedi testo "@alunno_5A_6:cognome,nome #str(Voto) #cas(@voto_F_5A_56:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) #cas(@voto_F_5A_50:unico,0,NC,@voto_F_5A_50:unico) #cas(@voto_F_5A_51:unico,0,NC,@voto_F_5A_51:unico) #cas(@voto_F_5A_52:unico,0,NC,@voto_F_5A_52:unico) #cas(@voto_F_5A_53:unico,0,NC,@voto_F_5A_53:unico) #cas(@voto_F_5A_55:unico,0,NC,@voto_F_5A_55:unico) #cas(@voto_F_5A_54:unico,0,NC,@voto_F_5A_54:unico) #cas(@voto_F_5A_57:unico,0,NC,@voto_F_5A_57:unico) #cas(@voto_F_5A_58:unico,2,NC,@voto_F_5A_58:unico) #nos(NON)" in PDF analizzato in una riga
  E vedi poi testo "#dat(@alunno_5A_6:dataNascita) #str(Ass.) @voto_F_5A_56:assenze @voto_F_5A_50:assenze @voto_F_5A_51:assenze @voto_F_5A_52:assenze @voto_F_5A_53:assenze @voto_F_5A_55:assenze @voto_F_5A_54:assenze @voto_F_5A_57:assenze #str(15) #sum(@alunno_5A_6:credito3,@alunno_5A_6:credito4) #sum(15,@alunno_5A_6:credito3,@alunno_5A_6:credito4) #med(@voto_F_5A_50:unico,@voto_F_5A_51:unico,@voto_F_5A_52:unico,@voto_F_5A_53:unico,@voto_F_5A_54:unico,@voto_F_5A_55:unico,@voto_F_5A_57:unico,@voto_F_5A_58:unico)" in PDF analizzato in una riga
  E vedi testo "@alunno_5A_7:cognome #str(Voto) #cas(@voto_F_5A_66:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) #cas(@voto_F_5A_60:unico,0,NC,@voto_F_5A_60:unico) #cas(@voto_F_5A_61:unico,0,NC,@voto_F_5A_61:unico) #cas(@voto_F_5A_62:unico,0,NC,@voto_F_5A_62:unico) #cas(@voto_F_5A_63:unico,0,NC,@voto_F_5A_63:unico) #cas(@voto_F_5A_65:unico,0,NC,@voto_F_5A_65:unico) #cas(@voto_F_5A_64:unico,0,NC,@voto_F_5A_64:unico) #cas(@voto_F_5A_67:unico,0,NC,@voto_F_5A_67:unico) #cas(@voto_F_5A_68:unico,2,NC,@voto_F_5A_68:unico) #nos(NON)" in PDF analizzato in una riga
  E vedi poi testo "#str(Ass.) @voto_F_5A_66:assenze @voto_F_5A_60:assenze @voto_F_5A_61:assenze @voto_F_5A_62:assenze @voto_F_5A_63:assenze @voto_F_5A_65:assenze @voto_F_5A_64:assenze @voto_F_5A_67:assenze #str(7) #sum(@alunno_5A_7:credito3,@alunno_5A_7:credito4) #sum(7,@alunno_5A_7:credito3,@alunno_5A_7:credito4) #med(@voto_F_5A_60:unico,@voto_F_5A_61:unico,@voto_F_5A_62:unico,@voto_F_5A_63:unico,@voto_F_5A_64:unico,@voto_F_5A_65:unico,@voto_F_5A_67:unico,@voto_F_5A_68:unico)" in PDF analizzato in una riga
  E vedi poi testo "?@docente_curricolare_1:cognome+ +@docente_curricolare_1:nome?@docente_curricolare_2:cognome+ +@docente_curricolare_2:nome?@docente_curricolare_3:cognome+ +@docente_curricolare_3:nome?@docente_curricolare_4:cognome+ +@docente_curricolare_4:nome?@docente_curricolare_5:cognome+ +@docente_curricolare_5:nome?@docente_religione_1:cognome+ +@docente_religione_1:nome?@docente_itp_1:cognome+ +@docente_itp_1:nome" in PDF analizzato in "5" righe
  E vedi poi testo "#str(Data) #str(11/06/2020) #str(Presidente) @docente_curricolare_1:cognome,nome" in PDF analizzato in "2" righe

Scenario: controllo riepilogo dei voti per la classe articolata
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe           |
    | @classe_3CAMB:id |
  Quando click su "Scarica" in sezione "#gs-main table tbody tr" che contiene "Riepilogo voti"
  E analizzi PDF "archivio/scrutini/finale/3CAMB/3CAMB-scrutinio-finale-riepilogo-voti.pdf"
  Allora vedi testo "RIEPILOGO VOTI 3ª C-AMB" in PDF analizzato alla riga "1"
  E vedi testo "@alunno_3CAMB_1:cognome,nome #str(Voto) #cas(@voto_F_3CAMB_0:unico,0,NC,@voto_F_3CAMB_0:unico) #cas(@voto_F_3CAMB_1:unico,0,NC,@voto_F_3CAMB_1:unico) #cas(@voto_F_3CAMB_2:unico,0,NC,@voto_F_3CAMB_2:unico) #cas(@voto_F_3CAMB_3:unico,0,NC,@voto_F_3CAMB_3:unico) #cas(@voto_F_3CAMB_5:unico,0,NC,@voto_F_3CAMB_5:unico) #cas(@voto_F_3CAMB_4:unico,0,NC,@voto_F_3CAMB_4:unico) #cas(@voto_F_3CAMB_6:unico,0,NC,@voto_F_3CAMB_6:unico) #cas(@voto_F_3CAMB_7:unico,2,NC,@voto_F_3CAMB_7:unico) #str(SOSPENSIONE)" in PDF analizzato in una riga
  E vedi poi testo "#str(///) @voto_F_3CAMB_0:assenze @voto_F_3CAMB_1:assenze @voto_F_3CAMB_2:assenze @voto_F_3CAMB_3:assenze @voto_F_3CAMB_5:assenze @voto_F_3CAMB_4:assenze @voto_F_3CAMB_6:assenze #med(@voto_F_3CAMB_0:unico,@voto_F_3CAMB_1:unico,@voto_F_3CAMB_2:unico,@voto_F_3CAMB_3:unico,@voto_F_3CAMB_4:unico,@voto_F_3CAMB_5:unico,@voto_F_3CAMB_6:unico,@voto_F_3CAMB_7:unico)" in PDF analizzato in una riga
  E vedi testo "@alunno_3CAMB_2:cognome,nome #str(Voto) #cas(@voto_F_3CAMB_15:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) #cas(@voto_F_3CAMB_10:unico,0,NC,@voto_F_3CAMB_10:unico) #cas(@voto_F_3CAMB_11:unico,0,NC,@voto_F_3CAMB_11:unico) #cas(@voto_F_3CAMB_12:unico,0,NC,@voto_F_3CAMB_12:unico) #cas(@voto_F_3CAMB_13:unico,0,NC,@voto_F_3CAMB_13:unico) #cas(@voto_F_3CAMB_16:unico,0,NC,@voto_F_3CAMB_16:unico) #cas(@voto_F_3CAMB_14:unico,0,NC,@voto_F_3CAMB_14:unico) #cas(@voto_F_3CAMB_17:unico,0,NC,@voto_F_3CAMB_17:unico) #cas(@voto_F_3CAMB_18:unico,2,NC,@voto_F_3CAMB_18:unico) #nos(NON)" in PDF analizzato in una riga
  E vedi poi testo "#dat(@alunno_3CAMB_2:dataNascita) #str(Ass.) @voto_F_3CAMB_15:assenze @voto_F_3CAMB_10:assenze @voto_F_3CAMB_11:assenze @voto_F_3CAMB_12:assenze @voto_F_3CAMB_13:assenze @voto_F_3CAMB_16:assenze @voto_F_3CAMB_14:assenze @voto_F_3CAMB_17:assenze #str(10) #med(@voto_F_3CAMB_10:unico,@voto_F_3CAMB_11:unico,@voto_F_3CAMB_12:unico,@voto_F_3CAMB_13:unico,@voto_F_3CAMB_14:unico,@voto_F_3CAMB_16:unico,@voto_F_3CAMB_17:unico,@voto_F_3CAMB_18:unico)" in PDF analizzato in una riga
  E vedi testo "@alunno_sostegno_4:cognome,nome #str(Voto) #cas(@voto_F_3CAMB_25:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) #cas(@voto_F_3CAMB_20:unico,0,NC,@voto_F_3CAMB_20:unico) #cas(@voto_F_3CAMB_21:unico,0,NC,@voto_F_3CAMB_21:unico) #cas(@voto_F_3CAMB_22:unico,0,NC,@voto_F_3CAMB_22:unico) #cas(@voto_F_3CAMB_23:unico,0,NC,@voto_F_3CAMB_23:unico) #cas(@voto_F_3CAMB_26:unico,0,NC,@voto_F_3CAMB_26:unico) #cas(@voto_F_3CAMB_24:unico,0,NC,@voto_F_3CAMB_24:unico) #cas(@voto_F_3CAMB_27:unico,0,NC,@voto_F_3CAMB_27:unico) #cas(@voto_F_3CAMB_28:unico,2,NC,@voto_F_3CAMB_28:unico) #str(NON)" in PDF analizzato in una riga
  E vedi poi testo "#dat(@alunno_sostegno_4:dataNascita) #str(Ass.) @voto_F_3CAMB_25:assenze @voto_F_3CAMB_20:assenze @voto_F_3CAMB_21:assenze @voto_F_3CAMB_22:assenze @voto_F_3CAMB_23:assenze @voto_F_3CAMB_26:assenze @voto_F_3CAMB_24:assenze @voto_F_3CAMB_27:assenze #med(@voto_F_3CAMB_20:unico,@voto_F_3CAMB_21:unico,@voto_F_3CAMB_22:unico,@voto_F_3CAMB_23:unico,@voto_F_3CAMB_24:unico,@voto_F_3CAMB_26:unico,@voto_F_3CAMB_27:unico,@voto_F_3CAMB_28:unico)" in PDF analizzato in una riga
  E vedi poi testo "?@docente_curricolare_1:cognome+ +@docente_curricolare_1:nome?@docente_curricolare_2:cognome+ +@docente_curricolare_2:nome?@docente_curricolare_3:cognome+ +@docente_curricolare_3:nome?@docente_curricolare_4:cognome+ +@docente_curricolare_4:nome?@docente_curricolare_5:cognome+ +@docente_curricolare_5:nome?@docente_religione_1:cognome+ +@docente_religione_1:nome?@docente_itp_2:cognome+ +@docente_itp_2:nome" in PDF analizzato in "5" righe
  E vedi poi testo "#str(Data) #str(11/06/2020) #str(Presidente) @preside:nome,cognome" in PDF analizzato in "2" righe

Scenario: controllo foglio firme registro dei voti
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "Scarica" in sezione "#gs-main table tbody tr" che contiene "Foglio firme Registro dei voti"
  E analizzi PDF "archivio/scrutini/finale/1A/1A-scrutinio-finale-firme-registro.pdf"
  Allora vedi testo "/FOGLIO FIRME REGISTRO +CLASSE 1ª A/" in PDF analizzato alla riga "1"
  E vedi testo "@materia_curricolare_1:nome @materia_EDCIVICA:nome #str(Bianchi) #str(Maria)" in PDF analizzato in una riga
  E vedi testo "@materia_curricolare_2:nome @materia_EDCIVICA:nome @docente_curricolare_2:cognome,nome" in PDF analizzato in una riga
  E vedi testo "@materia_curricolare_3:nome @materia_EDCIVICA:nome @docente_curricolare_3:cognome,nome" in PDF analizzato in una riga
  E vedi testo "@materia_curricolare_4:nome @materia_EDCIVICA:nome @docente_curricolare_4:cognome,nome" in PDF analizzato in una riga
  E vedi testo "@materia_curricolare_5:nome @materia_EDCIVICA:nome @docente_curricolare_5:cognome,nome" in PDF analizzato in una riga
  E vedi testo "#str(Religione) #str(Educazione)?@docente_religione_1:cognome+ +@docente_religione_1:nome?@docente_nocattedra_1:cognome+ +@docente_nocattedra_1:nome" in PDF analizzato in una riga
  E vedi testo "@materia_itp_1:nome+, +@materia_EDCIVICA:nome?@docente_itp_1:cognome+ +@docente_itp_1:nome?@docente_itp_2:cognome+ +@docente_itp_2:nome" in PDF analizzato in una riga
  E vedi testo "@materia_SOSTEGNO:nome+, +@materia_EDCIVICA:nome?@docente_sostegno_1:cognome+ +@docente_sostegno_1:nome?@docente_sostegno_2:cognome+ +@docente_sostegno_2:nome" in PDF analizzato in una riga

Scenario: controllo foglio firme registro dei voti per la classe articolata
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe           |
    | @classe_3CAMB:id |
  Quando click su "Scarica" in sezione "#gs-main table tbody tr" che contiene "Foglio firme Registro dei voti"
  E analizzi PDF "archivio/scrutini/finale/3CAMB/3CAMB-scrutinio-finale-firme-registro.pdf"
  Allora vedi testo "/FOGLIO FIRME REGISTRO +CLASSE 3ª C-AMB/" in PDF analizzato alla riga "1"
  E vedi testo "@materia_curricolare_1:nome @materia_EDCIVICA:nome @docente_curricolare_1:cognome,nome" in PDF analizzato in una riga
  E vedi testo "@materia_curricolare_2:nome @materia_EDCIVICA:nome @docente_curricolare_2:cognome,nome" in PDF analizzato in una riga
  E vedi testo "@materia_curricolare_3:nome @materia_EDCIVICA:nome @docente_curricolare_3:cognome,nome" in PDF analizzato in una riga
  E vedi testo "@materia_curricolare_4:nome @materia_EDCIVICA:nome @docente_curricolare_4:cognome,nome" in PDF analizzato in una riga
  E vedi testo "@materia_curricolare_5:nome @materia_EDCIVICA:nome @docente_curricolare_5:cognome,nome" in PDF analizzato in una riga
  E vedi testo "#str(Religione) #str(Educazione)?@docente_religione_1:cognome+ +@docente_religione_1:nome" in PDF analizzato in una riga
  E vedi testo "@materia_itp_2:nome @materia_EDCIVICA:nome @docente_itp_2:cognome,nome" in PDF analizzato in una riga

Scenario: controllo verbale
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "Scarica" in sezione "#gs-main table tbody tr" che contiene "Verbale"
  E analizzi PDF "archivio/scrutini/finale/1A/1A-scrutinio-finale-verbale.pdf"
  Allora vedi testo "Verbale n. 5" in PDF analizzato alla riga "2"
  E vedi testo "SCRUTINIO FINALE" in PDF analizzato alla riga "3"
  E vedi testo "1ª A" in PDF analizzato alla riga "4"
  E vedi poi testo "#str(11) #str(Giugno) #str(2020) #str(ore) #tim(@scrutinio_1A_F:inizio)" in PDF analizzato in una riga
  E imposti segnalibro PDF
  E vedi da segnalibro il testo "@docente_curricolare_2:cognome+ +@docente_curricolare_2:nome?@materia_curricolare_2:nome?@materia_EDCIVICA:nome" in PDF analizzato in "2" righe
  E vedi da segnalibro il testo "@docente_curricolare_3:cognome+ +@docente_curricolare_3:nome?@materia_curricolare_3:nome?@materia_EDCIVICA:nome" in PDF analizzato in "2" righe
  E vedi da segnalibro il testo "@docente_curricolare_4:cognome+ +@docente_curricolare_4:nome?@materia_curricolare_4:nome?@materia_EDCIVICA:nome" in PDF analizzato in "2" righe
  E vedi da segnalibro il testo "@docente_curricolare_5:cognome+ +@docente_curricolare_5:nome?@materia_curricolare_5:nome?@materia_EDCIVICA:nome" in PDF analizzato in "2" righe
  E vedi da segnalibro il testo "@docente_religione_1:cognome+ +@docente_religione_1:nome?#str(Religione)?@materia_EDCIVICA:nome" in PDF analizzato in "2" righe
  E vedi da segnalibro il testo "@docente_nocattedra_1:cognome+ +@docente_nocattedra_1:nome?#str(Religione)?@materia_EDCIVICA:nome" in PDF analizzato in "2" righe
  E vedi da segnalibro il testo "@docente_itp_1:cognome+ +@docente_itp_1:nome?@materia_itp_1:nome?@materia_EDCIVICA:nome" in PDF analizzato in "2" righe
  E vedi da segnalibro il testo "@docente_itp_2:cognome+ +@docente_itp_2:nome?@materia_itp_1:nome?@materia_EDCIVICA:nome" in PDF analizzato in "2" righe
  E vedi da segnalibro il testo "@docente_sostegno_1:cognome+ +@docente_sostegno_1:nome?@materia_SOSTEGNO:nome?@materia_EDCIVICA:nome" in PDF analizzato in "2" righe
  E vedi da segnalibro il testo "@docente_sostegno_2:cognome+ +@docente_sostegno_2:nome?@materia_SOSTEGNO:nome?@materia_EDCIVICA:nome" in PDF analizzato in "2" righe
  E vedi poi testo "#str(assenti) @docente_curricolare_1:cognome,nome #str(Bianchi) #str(Maria) @materia_curricolare_1:nome @materia_EDCIVICA:nome" in PDF analizzato in "3" righe
  E vedi poi testo "gli alunni che presentano un numero di assenze superiore al 25% dell’orario annuale" in PDF analizzato in una riga
  E imposti segnalibro PDF
  E vedi da segnalibro il testo "@alunno_1A_1:cognome,nome #dat(@alunno_1A_1:dataNascita) #str(1089) #str(272) #str(310)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_1A_2:cognome,nome #dat(@alunno_1A_2:dataNascita) #str(1089) #str(272) #str(315)" in PDF analizzato in una riga
  E vedi poi testo "ammette allo scrutinio, nonostante il superamento del limite di assenze" in PDF analizzato in una riga
  E imposti segnalibro PDF
  E vedi da segnalibro il testo "@alunno_1A_1:cognome,nome #dat(@alunno_1A_1:dataNascita) #str(salute)" in PDF analizzato in una riga
  E vedi poi testo "esclusione dallo scrutinio e la non ammissione" in PDF analizzato in una riga
  E vedi poi testo "@alunno_1A_2:cognome,nome #dat(@alunno_1A_2:dataNascita)" in PDF analizzato in una riga
  E vedi poi testo "@alunno_1A_6:cognome,nome #dat(@alunno_1A_6:dataNascita) #str(frequenta) #str(all'estero)" in PDF analizzato in "2" righe
  E vedi poi testo "di 6 alunni iscritti alla classe, sono da scrutinare 4 alunni" in PDF analizzato in una riga
  E vedi poi testo "voto di comportamento" in PDF analizzato in una riga
  E imposti segnalibro PDF
  E vedi da segnalibro il testo "@alunno_1A_1:cognome,nome @voto_F_1A_7:unico #str(MAGGIORANZA) #str(Contrari) @docente_curricolare_1:cognome" in PDF analizzato in "4" righe
  E vedi da segnalibro il testo "@alunno_sostegno_1:cognome,nome @voto_F_1A_28:unico #str(UNANIMITÀ)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_sostegno_2:cognome,nome @voto_F_1A_38:unico #str(UNANIMITÀ)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_alternativa_1:cognome,nome @voto_F_1A_48:unico #str(UNANIMITÀ)" in PDF analizzato in una riga
  E vedi poi testo "ammessi alla classe successiva" in PDF analizzato in una riga
  E imposti segnalibro PDF
  E vedi da segnalibro il testo "@alunno_sostegno_1:cognome,nome #dat(@alunno_sostegno_1:dataNascita) #str(UNANIMITÀ)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_sostegno_2:cognome,nome #dat(@alunno_sostegno_2:dataNascita) #str(UNANIMITÀ)" in PDF analizzato in una riga
  E vedi poi testo "sospende la formulazione del giudizio finale" in PDF analizzato in una riga
  E imposti segnalibro PDF
  E vedi da segnalibro il testo "@alunno_1A_1:cognome,nome #dat(@alunno_1A_1:dataNascita) #str(maggioranza) #str(Contrari) @docente_curricolare_1:cognome" in PDF analizzato in "2" righe
  E vedi poi testo "@materia_curricolare_1:nome @voto_F_1A_0:unico #str(Argomento) #str(Corso)" in PDF analizzato in una riga
  E vedi poi testo "@materia_curricolare_2:nome @voto_F_1A_1:unico #str(Argomento) #str(Corso)" in PDF analizzato in una riga
  E vedi poi testo "@materia_curricolare_3:nome @voto_F_1A_2:unico #str(Argomento) #str(Corso)" in PDF analizzato in una riga
  E vedi poi testo "dichiara non ammessi" in PDF analizzato in una riga
  E vedi poi testo "@alunno_alternativa_1:cognome,nome #str(UNANIMITÀ) #str(Motivazione)" in PDF analizzato in una riga
  E vedi poi testo "/Iscritti: 6\s+Scrutinati: 4\s+Non scrutinati: 2\s+AMMESSI: 2\s+NON AMMESSI: 1\s+GIUDIZIO SOSPESO: 1/ui" in PDF analizzato in "6" righe
  E vedi poi testo "Testo verbale passo 2..." in PDF analizzato in una riga
  E vedi poi testo "#tim(@scrutinio_1A_F:fine)" in PDF analizzato in una riga
  E vedi poi testo "#str(Segretario) #str(Presidente) @docente_curricolare_2:cognome,nome #str(Bianchi) #str(Maria)" in PDF analizzato in "2" righe

Scenario: controllo verbale classe terza
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_3A:id |
  Quando click su "Scarica" in sezione "#gs-main table tbody tr" che contiene "Verbale"
  E analizzi PDF "archivio/scrutini/finale/3A/3A-scrutinio-finale-verbale.pdf"
  Allora vedi testo "Verbale n. 5" in PDF analizzato alla riga "2"
  E vedi testo "SCRUTINIO FINALE" in PDF analizzato alla riga "3"
  E vedi testo "3ª A" in PDF analizzato alla riga "4"
  E vedi poi testo "#str(11) #str(Giugno) #str(2020) #str(ore) #tim(@scrutinio_3A_F:inizio)" in PDF analizzato in una riga
  E imposti segnalibro PDF
  E vedi da segnalibro il testo "@docente_curricolare_1:cognome+ +@docente_curricolare_1:nome?@materia_curricolare_1:nome?@materia_EDCIVICA:nome" in PDF analizzato in "2" righe
  E vedi da segnalibro il testo "@docente_curricolare_2:cognome+ +@docente_curricolare_2:nome?@materia_curricolare_2:nome?@materia_EDCIVICA:nome" in PDF analizzato in "2" righe
  E vedi da segnalibro il testo "@docente_curricolare_3:cognome+ +@docente_curricolare_3:nome?@materia_curricolare_3:nome?@materia_EDCIVICA:nome" in PDF analizzato in "2" righe
  E vedi da segnalibro il testo "@docente_curricolare_4:cognome+ +@docente_curricolare_4:nome?@materia_curricolare_4:nome?@materia_EDCIVICA:nome" in PDF analizzato in "2" righe
  E vedi da segnalibro il testo "@docente_curricolare_5:cognome+ +@docente_curricolare_5:nome?@materia_curricolare_5:nome?@materia_EDCIVICA:nome" in PDF analizzato in "2" righe
  E vedi da segnalibro il testo "@docente_religione_1:cognome+ +@docente_religione_1:nome?#str(Religione)?@materia_EDCIVICA:nome" in PDF analizzato in "2" righe
  E vedi da segnalibro il testo "@docente_itp_1:cognome+ +@docente_itp_1:nome?@materia_itp_1:nome?@materia_EDCIVICA:nome" in PDF analizzato in "2" righe
  E vedi poi testo "Nessun docente risulta assente" in PDF analizzato in una riga
  E vedi poi testo "Tutti gli alunni rientrano nei limiti di assenze previsti dalla normativa" in PDF analizzato in una riga
  E vedi poi testo "di 7 alunni iscritti alla classe, sono da scrutinare 7 alunni" in PDF analizzato in una riga
  E vedi poi testo "voto di comportamento" in PDF analizzato in una riga
  E imposti segnalibro PDF
  E vedi da segnalibro il testo "@alunno_3A_1:cognome,nome @voto_F_3A_7:unico #str(UNANIMITÀ)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_3A_2:cognome,nome @voto_F_3A_18:unico #str(UNANIMITÀ)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_3A_3:cognome,nome @voto_F_3A_28:unico #str(UNANIMITÀ)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_3A_4:cognome,nome @voto_F_3A_38:unico #str(UNANIMITÀ)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_3A_5:cognome,nome @voto_F_3A_48:unico #str(UNANIMITÀ)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_3A_6:cognome,nome @voto_F_3A_58:unico #str(UNANIMITÀ)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_3A_7:cognome,nome @voto_F_3A_68:unico #str(UNANIMITÀ)" in PDF analizzato in una riga
  E vedi poi testo "ammessi alla classe successiva" in PDF analizzato in una riga
  E imposti segnalibro PDF
  E vedi da segnalibro il testo "@alunno_3A_2:cognome,nome #dat(@alunno_3A_2:dataNascita) #str(UNANIMITÀ)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_3A_3:cognome,nome #dat(@alunno_3A_3:dataNascita) #str(UNANIMITÀ)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_3A_4:cognome,nome #dat(@alunno_3A_4:dataNascita) #str(UNANIMITÀ)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_3A_5:cognome,nome #dat(@alunno_3A_5:dataNascita) #str(UNANIMITÀ)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_3A_6:cognome,nome #dat(@alunno_3A_6:dataNascita) #str(UNANIMITÀ)" in PDF analizzato in una riga
  E vedi poi testo "attribuzione del credito scolastico" in PDF analizzato in una riga
  E imposti segnalibro PDF
  E vedi da segnalibro il testo "@alunno_3A_2:cognome,nome #med(@voto_F_3A_10:unico,@voto_F_3A_11:unico,@voto_F_3A_12:unico,@voto_F_3A_13:unico,@voto_F_3A_14:unico,@voto_F_3A_15:unico,@voto_F_3A_17:unico,@voto_F_3A_18:unico) @esito_F_3A_2:credito" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_3A_3:cognome,nome #med(@voto_F_3A_20:unico,@voto_F_3A_21:unico,@voto_F_3A_22:unico,@voto_F_3A_23:unico,@voto_F_3A_24:unico,@voto_F_3A_25:unico,@voto_F_3A_27:unico,@voto_F_3A_28:unico) @esito_F_3A_3:credito" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_3A_4:cognome,nome #med(@voto_F_3A_30:unico,@voto_F_3A_31:unico,@voto_F_3A_32:unico,@voto_F_3A_33:unico,@voto_F_3A_34:unico,@voto_F_3A_35:unico,@voto_F_3A_37:unico,@voto_F_3A_38:unico) @esito_F_3A_4:credito" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_3A_5:cognome,nome #med(@voto_F_3A_40:unico,@voto_F_3A_41:unico,@voto_F_3A_42:unico,@voto_F_3A_43:unico,@voto_F_3A_44:unico,@voto_F_3A_45:unico,@voto_F_3A_47:unico,@voto_F_3A_48:unico) @esito_F_3A_5:credito" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_3A_6:cognome,nome #med(@voto_F_3A_50:unico,@voto_F_3A_51:unico,@voto_F_3A_52:unico,@voto_F_3A_53:unico,@voto_F_3A_54:unico,@voto_F_3A_55:unico,@voto_F_3A_57:unico,@voto_F_3A_58:unico) @esito_F_3A_6:credito" in PDF analizzato in una riga
  E vedi poi testo "sospende la formulazione del giudizio finale" in PDF analizzato in una riga
  E imposti segnalibro PDF
  E vedi da segnalibro il testo "@alunno_3A_7:cognome,nome #dat(@alunno_3A_7:dataNascita) #str(unanimità)" in PDF analizzato in "2" righe
  E vedi poi testo "@materia_curricolare_5:nome @voto_F_3A_5:unico #str(Studio)" in PDF analizzato in una riga
  E vedi poi testo "dichiara non ammessi" in PDF analizzato in una riga
  E vedi poi testo "@alunno_3A_1:cognome,nome #str(MAGGIORANZA) #str(Motivazione) #dat(@alunno_3A_1:dataNascita) #str(Contrari) @docente_curricolare_1:cognome" in PDF analizzato in "2" righe
  E vedi poi testo "/Iscritti: 7.*Scrutinati: 7\s+Non scrutinati: 0\s+AMMESSI: 5\s+NON AMMESSI: 1\s+GIUDIZIO SOSPESO: 1/ui" in PDF analizzato in "7" righe
  E vedi poi testo "/si segnala quanto segue:\s+Testo verbale passo 2\.\.\./ui" in PDF analizzato in "2" righe
  E vedi poi testo "#tim(@scrutinio_3A_F:fine)" in PDF analizzato in una riga
  E vedi poi testo "#str(Segretario) #str(Presidente) @docente_curricolare_2:cognome,nome @docente_curricolare_1:cognome,nome" in PDF analizzato in "2" righe

Scenario: controllo verbale classe quarta
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_4A:id |
  Quando click su "Scarica" in sezione "#gs-main table tbody tr" che contiene "Verbale"
  E analizzi PDF "archivio/scrutini/finale/4A/4A-scrutinio-finale-verbale.pdf"
  Allora vedi testo "Verbale n. 5" in PDF analizzato alla riga "2"
  E vedi testo "SCRUTINIO FINALE" in PDF analizzato alla riga "3"
  E vedi testo "4ª A" in PDF analizzato alla riga "4"
  E vedi poi testo "#str(11) #str(Giugno) #str(2020) #str(ore) #tim(@scrutinio_4A_F:inizio)" in PDF analizzato in una riga
  E imposti segnalibro PDF
  E vedi da segnalibro il testo "@docente_curricolare_1:cognome+ +@docente_curricolare_1:nome?@materia_curricolare_1:nome?@materia_EDCIVICA:nome" in PDF analizzato in "2" righe
  E vedi da segnalibro il testo "@docente_curricolare_2:cognome+ +@docente_curricolare_2:nome?@materia_curricolare_2:nome?@materia_EDCIVICA:nome" in PDF analizzato in "2" righe
  E vedi da segnalibro il testo "@docente_curricolare_3:cognome+ +@docente_curricolare_3:nome?@materia_curricolare_3:nome?@materia_EDCIVICA:nome" in PDF analizzato in "2" righe
  E vedi da segnalibro il testo "@docente_curricolare_4:cognome+ +@docente_curricolare_4:nome?@materia_curricolare_4:nome?@materia_EDCIVICA:nome" in PDF analizzato in "2" righe
  E vedi da segnalibro il testo "@docente_curricolare_5:cognome+ +@docente_curricolare_5:nome?@materia_curricolare_5:nome?@materia_EDCIVICA:nome" in PDF analizzato in "2" righe
  E vedi da segnalibro il testo "@docente_religione_1:cognome+ +@docente_religione_1:nome?#str(Religione)?@materia_EDCIVICA:nome" in PDF analizzato in "2" righe
  E vedi da segnalibro il testo "@docente_itp_1:cognome+ +@docente_itp_1:nome?@materia_itp_1:nome?@materia_EDCIVICA:nome" in PDF analizzato in "2" righe
  E vedi poi testo "Nessun docente risulta assente" in PDF analizzato in una riga
  E vedi poi testo "Tutti gli alunni rientrano nei limiti di assenze previsti dalla normativa" in PDF analizzato in una riga
  E vedi poi testo "di 7 alunni iscritti alla classe, sono da scrutinare 7 alunni" in PDF analizzato in una riga
  E vedi poi testo "voto di comportamento" in PDF analizzato in una riga
  E imposti segnalibro PDF
  E vedi da segnalibro il testo "@alunno_4A_1:cognome,nome @voto_F_4A_7:unico #str(UNANIMITÀ)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_4A_2:cognome,nome @voto_F_4A_18:unico #str(UNANIMITÀ)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_4A_3:cognome,nome @voto_F_4A_28:unico #str(UNANIMITÀ)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_4A_4:cognome,nome @voto_F_4A_38:unico #str(UNANIMITÀ)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_4A_5:cognome,nome @voto_F_4A_48:unico #str(UNANIMITÀ)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_4A_6:cognome,nome @voto_F_4A_58:unico #str(UNANIMITÀ)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_4A_7:cognome,nome @voto_F_4A_68:unico #str(UNANIMITÀ)" in PDF analizzato in una riga
  E vedi poi testo "ammessi alla classe successiva" in PDF analizzato in una riga
  E imposti segnalibro PDF
  E vedi da segnalibro il testo "@alunno_4A_2:cognome,nome #dat(@alunno_4A_2:dataNascita) #str(UNANIMITÀ)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_4A_3:cognome,nome #dat(@alunno_4A_3:dataNascita) #str(UNANIMITÀ)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_4A_4:cognome,nome #dat(@alunno_4A_4:dataNascita) #str(UNANIMITÀ)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_4A_5:cognome,nome #dat(@alunno_4A_5:dataNascita) #str(UNANIMITÀ)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_4A_6:cognome,nome #dat(@alunno_4A_6:dataNascita) #str(UNANIMITÀ)" in PDF analizzato in una riga
  E vedi poi testo "attribuzione del credito scolastico" in PDF analizzato in una riga
  E imposti segnalibro PDF
  E vedi da segnalibro il testo "@alunno_4A_2:cognome,nome #med(@voto_F_4A_10:unico,@voto_F_4A_11:unico,@voto_F_4A_12:unico,@voto_F_4A_13:unico,@voto_F_4A_14:unico,@voto_F_4A_15:unico,@voto_F_4A_17:unico,@voto_F_4A_18:unico) @esito_F_4A_2:credito @alunno_4A_2:credito3 #sum(@alunno_4A_2:credito3,@esito_F_4A_2:credito)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_4A_3:cognome,nome #med(@voto_F_4A_20:unico,@voto_F_4A_21:unico,@voto_F_4A_22:unico,@voto_F_4A_23:unico,@voto_F_4A_24:unico,@voto_F_4A_25:unico,@voto_F_4A_27:unico,@voto_F_4A_28:unico) @esito_F_4A_3:credito @alunno_4A_3:credito3 #sum(@alunno_4A_3:credito3,@esito_F_4A_3:credito)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_4A_4:cognome,nome #med(@voto_F_4A_30:unico,@voto_F_4A_31:unico,@voto_F_4A_32:unico,@voto_F_4A_33:unico,@voto_F_4A_34:unico,@voto_F_4A_35:unico,@voto_F_4A_37:unico,@voto_F_4A_38:unico) @esito_F_4A_4:credito @alunno_4A_4:credito3 #sum(@alunno_4A_4:credito3,@esito_F_4A_4:credito)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_4A_5:cognome,nome #med(@voto_F_4A_40:unico,@voto_F_4A_41:unico,@voto_F_4A_42:unico,@voto_F_4A_43:unico,@voto_F_4A_44:unico,@voto_F_4A_45:unico,@voto_F_4A_47:unico,@voto_F_4A_48:unico) @esito_F_4A_5:credito @alunno_4A_5:credito3 #sum(@alunno_4A_5:credito3,@esito_F_4A_5:credito)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_4A_6:cognome,nome #med(@voto_F_4A_50:unico,@voto_F_4A_51:unico,@voto_F_4A_52:unico,@voto_F_4A_53:unico,@voto_F_4A_54:unico,@voto_F_4A_55:unico,@voto_F_4A_57:unico,@voto_F_4A_58:unico) @esito_F_4A_6:credito @alunno_4A_6:credito3 #sum(@alunno_4A_6:credito3,@esito_F_4A_6:credito)" in PDF analizzato in una riga
  E vedi poi testo "sospende la formulazione del giudizio finale" in PDF analizzato in una riga
  E imposti segnalibro PDF
  E vedi da segnalibro il testo "@alunno_4A_7:cognome,nome #dat(@alunno_4A_7:dataNascita) #str(unanimità)" in PDF analizzato in "2" righe
  E vedi poi testo "@materia_curricolare_5:nome @voto_F_4A_5:unico #str(Studio)" in PDF analizzato in una riga
  E vedi poi testo "dichiara non ammessi" in PDF analizzato in una riga
  E vedi poi testo "@alunno_4A_1:cognome,nome #str(MAGGIORANZA) #str(Motivazione) #dat(@alunno_4A_1:dataNascita) #str(Contrari) @docente_curricolare_1:cognome" in PDF analizzato in "2" righe
  E vedi poi testo "/Iscritti: 7.*Scrutinati: 7\s+Non scrutinati: 0\s+AMMESSI: 5\s+NON AMMESSI: 1\s+GIUDIZIO SOSPESO: 1/ui" in PDF analizzato in "7" righe
  E vedi poi testo "/si segnala quanto segue:\s+Testo verbale passo 2\.\.\./ui" in PDF analizzato in "2" righe
  E vedi poi testo "#tim(@scrutinio_4A_F:fine)" in PDF analizzato in una riga
  E vedi poi testo "#str(Segretario) #str(Presidente) @docente_curricolare_2:cognome,nome @docente_curricolare_1:cognome,nome" in PDF analizzato in "2" righe

Scenario: controllo verbale classe quinta
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_5A:id |
  Quando click su "Scarica" in sezione "#gs-main table tbody tr" che contiene "Verbale"
  E analizzi PDF "archivio/scrutini/finale/5A/5A-scrutinio-finale-verbale.pdf"
  Allora vedi testo "Verbale n. 5" in PDF analizzato alla riga "2"
  E vedi testo "SCRUTINIO FINALE" in PDF analizzato alla riga "3"
  E vedi testo "5ª A" in PDF analizzato alla riga "4"
  E vedi poi testo "#str(11) #str(Giugno) #str(2020) #str(ore) #tim(@scrutinio_5A_F:inizio)" in PDF analizzato in una riga
  E imposti segnalibro PDF
  E vedi da segnalibro il testo "@docente_curricolare_1:cognome+ +@docente_curricolare_1:nome?@materia_curricolare_1:nome?@materia_EDCIVICA:nome" in PDF analizzato in "2" righe
  E vedi da segnalibro il testo "@docente_curricolare_2:cognome+ +@docente_curricolare_2:nome?@materia_curricolare_2:nome?@materia_EDCIVICA:nome" in PDF analizzato in "2" righe
  E vedi da segnalibro il testo "@docente_curricolare_3:cognome+ +@docente_curricolare_3:nome?@materia_curricolare_3:nome?@materia_EDCIVICA:nome" in PDF analizzato in "2" righe
  E vedi da segnalibro il testo "@docente_curricolare_4:cognome+ +@docente_curricolare_4:nome?@materia_curricolare_4:nome?@materia_EDCIVICA:nome" in PDF analizzato in "2" righe
  E vedi da segnalibro il testo "@docente_curricolare_5:cognome+ +@docente_curricolare_5:nome?@materia_curricolare_5:nome?@materia_EDCIVICA:nome" in PDF analizzato in "2" righe
  E vedi da segnalibro il testo "@docente_religione_1:cognome+ +@docente_religione_1:nome?#str(Religione)?@materia_EDCIVICA:nome" in PDF analizzato in "2" righe
  E vedi da segnalibro il testo "@docente_itp_1:cognome+ +@docente_itp_1:nome?@materia_itp_1:nome?@materia_EDCIVICA:nome" in PDF analizzato in "2" righe
  E vedi poi testo "Nessun docente risulta assente" in PDF analizzato in una riga
  E vedi poi testo "Tutti gli alunni rientrano nei limiti di assenze previsti dalla normativa" in PDF analizzato in una riga
  E vedi poi testo "di 7 alunni iscritti alla classe, sono da scrutinare 7 alunni" in PDF analizzato in una riga
  E vedi poi testo "voto di comportamento" in PDF analizzato in una riga
  E imposti segnalibro PDF
  E vedi da segnalibro il testo "@alunno_5A_1:cognome,nome @voto_F_5A_7:unico #str(UNANIMITÀ)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_5A_2:cognome,nome @voto_F_5A_18:unico #str(UNANIMITÀ)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_5A_3:cognome,nome @voto_F_5A_28:unico #str(UNANIMITÀ)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_5A_4:cognome,nome @voto_F_5A_38:unico #str(UNANIMITÀ)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_5A_5:cognome,nome @voto_F_5A_48:unico #str(UNANIMITÀ)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_5A_6:cognome,nome @voto_F_5A_58:unico #str(UNANIMITÀ)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_5A_7:cognome,nome @voto_F_5A_68:unico #str(UNANIMITÀ)" in PDF analizzato in una riga
  E vedi poi testo "ammessi all'Esame di Stato, per avere riportato almeno sei" in PDF analizzato in una riga
  E imposti segnalibro PDF
  E vedi da segnalibro il testo "@alunno_5A_2:cognome,nome #dat(@alunno_5A_2:dataNascita) #str(UNANIMITÀ)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_5A_3:cognome,nome #dat(@alunno_5A_3:dataNascita) #str(UNANIMITÀ)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_5A_4:cognome,nome #dat(@alunno_5A_4:dataNascita) #str(UNANIMITÀ)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_5A_5:cognome,nome #dat(@alunno_5A_5:dataNascita) #str(UNANIMITÀ)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_5A_6:cognome,nome #dat(@alunno_5A_6:dataNascita) #str(UNANIMITÀ)" in PDF analizzato in una riga
  E vedi poi testo "ammessi all'Esame di Stato, pur in presenza di una votazione inferiore a sei" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_5A_7:cognome,nome #str(UNANIMITÀ) #str(Motivazione)" in PDF analizzato in una riga
  E vedi poi testo "attribuzione del credito scolastico" in PDF analizzato in una riga
  E imposti segnalibro PDF
  E vedi da segnalibro il testo "@alunno_5A_2:cognome,nome #med(@voto_F_5A_10:unico,@voto_F_5A_11:unico,@voto_F_5A_12:unico,@voto_F_5A_13:unico,@voto_F_5A_14:unico,@voto_F_5A_15:unico,@voto_F_5A_17:unico,@voto_F_5A_18:unico) @esito_F_5A_2:credito #sum(@alunno_5A_2:credito3,@alunno_5A_2:credito4) #sum(@alunno_5A_2:credito3,@alunno_5A_2:credito4,@esito_F_5A_2:credito)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_5A_3:cognome,nome #med(@voto_F_5A_20:unico,@voto_F_5A_21:unico,@voto_F_5A_22:unico,@voto_F_5A_23:unico,@voto_F_5A_24:unico,@voto_F_5A_25:unico,@voto_F_5A_27:unico,@voto_F_5A_28:unico) @esito_F_5A_3:credito #sum(@alunno_5A_3:credito3,@alunno_5A_3:credito4) #sum(@alunno_5A_3:credito3,@alunno_5A_3:credito4,@esito_F_5A_3:credito)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_5A_4:cognome,nome #med(@voto_F_5A_30:unico,@voto_F_5A_31:unico,@voto_F_5A_32:unico,@voto_F_5A_33:unico,@voto_F_5A_34:unico,@voto_F_5A_35:unico,@voto_F_5A_37:unico,@voto_F_5A_38:unico) @esito_F_5A_4:credito #sum(@alunno_5A_4:credito3,@alunno_5A_4:credito4) #sum(@alunno_5A_4:credito3,@alunno_5A_4:credito4,@esito_F_5A_4:credito)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_5A_5:cognome,nome #med(@voto_F_5A_40:unico,@voto_F_5A_41:unico,@voto_F_5A_42:unico,@voto_F_5A_43:unico,@voto_F_5A_44:unico,@voto_F_5A_45:unico,@voto_F_5A_47:unico,@voto_F_5A_48:unico) @esito_F_5A_5:credito #sum(@alunno_5A_5:credito3,@alunno_5A_5:credito4) #sum(@alunno_5A_5:credito3,@alunno_5A_5:credito4,@esito_F_5A_5:credito)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_5A_6:cognome,nome #med(@voto_F_5A_50:unico,@voto_F_5A_51:unico,@voto_F_5A_52:unico,@voto_F_5A_53:unico,@voto_F_5A_54:unico,@voto_F_5A_55:unico,@voto_F_5A_57:unico,@voto_F_5A_58:unico) @esito_F_5A_6:credito #sum(@alunno_5A_6:credito3,@alunno_5A_6:credito4) #sum(@alunno_5A_6:credito3,@alunno_5A_6:credito4,@esito_F_5A_6:credito)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_5A_7:cognome,nome #med(@voto_F_5A_60:unico,@voto_F_5A_61:unico,@voto_F_5A_62:unico,@voto_F_5A_63:unico,@voto_F_5A_64:unico,@voto_F_5A_65:unico,@voto_F_5A_67:unico,@voto_F_5A_68:unico) @esito_F_5A_7:credito #sum(@alunno_5A_7:credito3,@alunno_5A_7:credito4) #sum(@alunno_5A_7:credito3,@alunno_5A_7:credito4,@esito_F_5A_7:credito)" in PDF analizzato in una riga
  E vedi poi testo "dichiara non ammessi" in PDF analizzato in una riga
  E vedi poi testo "@alunno_5A_1:cognome,nome #str(MAGGIORANZA) #str(Motivazione) #dat(@alunno_5A_1:dataNascita) #str(Contrari) @docente_curricolare_1:cognome" in PDF analizzato in "2" righe
  E vedi poi testo "/Iscritti: 7.*Scrutinati: 7\s+Non scrutinati: 0\s+AMMESSI: 6\s+NON AMMESSI: 1/ui" in PDF analizzato in "7" righe
  E vedi poi testo "/si segnala quanto segue:\s+Testo verbale passo 2\.\.\./ui" in PDF analizzato in "2" righe
  E vedi poi testo "#tim(@scrutinio_5A_F:fine)" in PDF analizzato in una riga
  E vedi poi testo "#str(Segretario) #str(Presidente) @docente_curricolare_2:cognome,nome @docente_curricolare_1:cognome,nome" in PDF analizzato in "2" righe

Scenario: controllo verbale classe articolata
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe           |
    | @classe_3CAMB:id |
  Quando click su "Scarica" in sezione "#gs-main table tbody tr" che contiene "Verbale"
  E analizzi PDF "archivio/scrutini/finale/3CAMB/3CAMB-scrutinio-finale-verbale.pdf"
  Allora vedi testo "Verbale n. 5" in PDF analizzato alla riga "2"
  E vedi testo "SCRUTINIO FINALE" in PDF analizzato alla riga "3"
  E vedi testo "3ª C-AMB" in PDF analizzato alla riga "4"
  E vedi poi testo "#str(11) #str(Giugno) #str(2020) #str(ore) #tim(@scrutinio_3CAMB_F:inizio)" in PDF analizzato in una riga
  E imposti segnalibro PDF
  E vedi da segnalibro il testo "@docente_curricolare_1:cognome+ +@docente_curricolare_1:nome?@materia_curricolare_1:nome?@materia_EDCIVICA:nome" in PDF analizzato in "2" righe
  E vedi da segnalibro il testo "@docente_curricolare_2:cognome+ +@docente_curricolare_2:nome?@materia_curricolare_2:nome?@materia_EDCIVICA:nome" in PDF analizzato in "2" righe
  E vedi da segnalibro il testo "@docente_curricolare_3:cognome+ +@docente_curricolare_3:nome?@materia_curricolare_3:nome?@materia_EDCIVICA:nome" in PDF analizzato in "2" righe
  E vedi da segnalibro il testo "@docente_curricolare_4:cognome+ +@docente_curricolare_4:nome?@materia_curricolare_4:nome?@materia_EDCIVICA:nome" in PDF analizzato in "2" righe
  E vedi da segnalibro il testo "@docente_curricolare_5:cognome+ +@docente_curricolare_5:nome?@materia_curricolare_5:nome?@materia_EDCIVICA:nome" in PDF analizzato in "2" righe
  E vedi da segnalibro il testo "@docente_religione_1:cognome+ +@docente_religione_1:nome?#str(Religione)?@materia_EDCIVICA:nome" in PDF analizzato in "2" righe
  E vedi da segnalibro il testo "@docente_itp_2:cognome+ +@docente_itp_2:nome?@materia_itp_2:nome?@materia_EDCIVICA:nome" in PDF analizzato in "2" righe
  E vedi poi testo "Nessun docente risulta assente" in PDF analizzato in una riga
  E vedi poi testo "Tutti gli alunni rientrano nei limiti di assenze previsti dalla normativa" in PDF analizzato in una riga
  E vedi poi testo "di 3 alunni iscritti alla classe, sono da scrutinare 3 alunni" in PDF analizzato in una riga
  E vedi poi testo "voto di comportamento" in PDF analizzato in una riga
  E imposti segnalibro PDF
  E vedi da segnalibro il testo "@alunno_3CAMB_1:cognome,nome @voto_F_3CAMB_7:unico #str(MAGGIORANZA) #str(Contrari) @docente_curricolare_1:cognome" in PDF analizzato in "4" righe
  E vedi da segnalibro il testo "@alunno_3CAMB_2:cognome,nome @voto_F_3CAMB_18:unico #str(UNANIMITÀ)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_sostegno_4:cognome,nome @voto_F_3CAMB_28:unico #str(UNANIMITÀ)" in PDF analizzato in una riga
  E vedi poi testo "ammessi alla classe successiva" in PDF analizzato in una riga
  E imposti segnalibro PDF
  E vedi da segnalibro il testo "@alunno_3CAMB_2:cognome,nome #dat(@alunno_3CAMB_2:dataNascita) #str(UNANIMITÀ)" in PDF analizzato in una riga
  E vedi poi testo "attribuzione del credito scolastico" in PDF analizzato in una riga
  E imposti segnalibro PDF
  E vedi da segnalibro il testo "@alunno_3CAMB_2:cognome,nome #med(@voto_F_3CAMB_10:unico,@voto_F_3CAMB_11:unico,@voto_F_3CAMB_12:unico,@voto_F_3CAMB_13:unico,@voto_F_3CAMB_14:unico,@voto_F_3CAMB_16:unico,@voto_F_3CAMB_17:unico,@voto_F_3CAMB_18:unico) @esito_F_3CAMB_2:credito" in PDF analizzato in una riga
  E vedi poi testo "sospende la formulazione del giudizio finale" in PDF analizzato in una riga
  E imposti segnalibro PDF
  E vedi da segnalibro il testo "@alunno_3CAMB_1:cognome,nome #dat(@alunno_3CAMB_1:dataNascita) #str(maggioranza) #str(Contrari) @docente_curricolare_1:cognome" in PDF analizzato in "2" righe
  E vedi poi testo "@materia_curricolare_1:nome @voto_F_3CAMB_0:unico #str(Argomento) #str(Corso)" in PDF analizzato in una riga
  E vedi poi testo "@materia_curricolare_2:nome @voto_F_3CAMB_1:unico #str(Argomento) #str(Corso)" in PDF analizzato in una riga
  E vedi poi testo "@materia_curricolare_3:nome @voto_F_3CAMB_2:unico #str(Argomento) #str(Corso)" in PDF analizzato in una riga
  E vedi poi testo "dichiara non ammessi" in PDF analizzato in una riga
  E vedi poi testo "@alunno_sostegno_4:cognome,nome #str(UNANIMITÀ) #str(Motivazione) #dat(@alunno_sostegno_4:dataNascita)" in PDF analizzato in "2" righe
  E vedi poi testo "/Iscritti: 3.*Scrutinati: 3\s+Non scrutinati: 0\s+AMMESSI: 1\s+NON AMMESSI: 1\s+GIUDIZIO SOSPESO: 1/ui" in PDF analizzato in "7" righe
  E vedi poi testo "/si segnala quanto segue:\s+Testo verbale passo 2\.\.\./ui" in PDF analizzato in "2" righe
  E vedi poi testo "#tim(@scrutinio_3CAMB_F:fine)" in PDF analizzato in una riga
  E vedi poi testo "#str(Segretario) #str(Presidente) @docente_curricolare_1:cognome,nome @preside:nome,cognome" in PDF analizzato in "2" righe

Scenario: controllo tabellone esiti
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "Scarica" in sezione "#gs-main table tbody tr" che contiene "Tabellone esiti"
  E analizzi PDF "archivio/scrutini/finale/1A/1A-scrutinio-finale-tabellone-esiti.pdf"
  Allora vedi testo "CLASSE 1ª A" in PDF analizzato alla riga "2"
  E vedi testo "SCRUTINIO FINALE" in PDF analizzato alla riga "3"
  E imposti segnalibro PDF
  E vedi da segnalibro il testo "@alunno_1A_6:cognome,nome #str(ALL'ESTERO)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_1A_1:cognome,nome #str(SOSPESO)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_1A_2:cognome,nome #str(NON)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_sostegno_1:cognome,nome #nos(NON) #nos(SOSPESO)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_sostegno_2:cognome,nome #nos(NON) #nos(SOSPESO)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_alternativa_1:cognome,nome #str(NON)" in PDF analizzato in una riga
  E vedi poi testo "#dat(@scrutinio_1A_F:data) #str(Dirigente) @preside:nome,cognome" in PDF analizzato in "2" righe

Scenario: controllo tabellone esiti classe terza
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_3A:id |
  Quando click su "Scarica" in sezione "#gs-main table tbody tr" che contiene "Tabellone esiti"
  E analizzi PDF "archivio/scrutini/finale/3A/3A-scrutinio-finale-tabellone-esiti.pdf"
  Allora vedi testo "CLASSE 3ª A" in PDF analizzato alla riga "2"
  E vedi testo "SCRUTINIO FINALE" in PDF analizzato alla riga "3"
  E imposti segnalibro PDF
  E vedi da segnalibro il testo "@alunno_3A_1:cognome,nome #noc() #str(NON)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_3A_2:cognome,nome @esito_F_3A_2:credito #nos(NON) #nos(SOSPESO)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_3A_3:cognome,nome @esito_F_3A_3:credito #nos(NON) #nos(SOSPESO)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_3A_4:cognome,nome @esito_F_3A_4:credito #nos(NON) #nos(SOSPESO)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_3A_5:cognome,nome @esito_F_3A_5:credito #nos(NON) #nos(SOSPESO)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_3A_6:cognome,nome @esito_F_3A_6:credito #nos(NON) #nos(SOSPESO)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_3A_7:cognome,nome #noc() #str(SOSPESO)" in PDF analizzato in una riga
  E vedi poi testo "#dat(@scrutinio_1A_F:data) #str(Dirigente) @preside:nome,cognome" in PDF analizzato in "2" righe

Scenario: controllo tabellone esiti classe quarta
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_4A:id |
  Quando click su "Scarica" in sezione "#gs-main table tbody tr" che contiene "Tabellone esiti"
  E analizzi PDF "archivio/scrutini/finale/4A/4A-scrutinio-finale-tabellone-esiti.pdf"
  Allora vedi testo "CLASSE 4ª A" in PDF analizzato alla riga "2"
  E vedi testo "SCRUTINIO FINALE" in PDF analizzato alla riga "3"
  E imposti segnalibro PDF
  E vedi da segnalibro il testo "@alunno_4A_1:cognome,nome #noc() #str(NON)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_4A_2:cognome,nome @esito_F_4A_2:credito @alunno_4A_2:credito3 #sum(@esito_F_4A_2:credito,@alunno_4A_2:credito3) #nos(NON) #nos(SOSPESO)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_4A_3:cognome,nome @esito_F_4A_3:credito @alunno_4A_3:credito3 #sum(@esito_F_4A_3:credito,@alunno_4A_3:credito3) #nos(NON) #nos(SOSPESO)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_4A_4:cognome,nome @esito_F_4A_4:credito @alunno_4A_4:credito3 #sum(@esito_F_4A_4:credito,@alunno_4A_4:credito3) #nos(NON) #nos(SOSPESO)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_4A_5:cognome,nome @esito_F_4A_5:credito @alunno_4A_5:credito3 #sum(@esito_F_4A_5:credito,@alunno_4A_5:credito3) #nos(NON) #nos(SOSPESO)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_4A_6:cognome,nome @esito_F_4A_6:credito @alunno_4A_6:credito3 #sum(@esito_F_4A_6:credito,@alunno_4A_6:credito3) #nos(NON) #nos(SOSPESO)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_4A_7:cognome,nome #noc() #str(SOSPESO)" in PDF analizzato in una riga
  E vedi poi testo "#dat(@scrutinio_1A_F:data) #str(Dirigente) @preside:nome,cognome" in PDF analizzato in "2" righe

Scenario: controllo tabellone esiti classe quinta
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_5A:id |
  Quando click su "Scarica" in sezione "#gs-main table tbody tr" che contiene "Tabellone esiti"
  E analizzi PDF "archivio/scrutini/finale/5A/5A-scrutinio-finale-tabellone-esiti.pdf"
  Allora vedi testo "CLASSE 5ª A" in PDF analizzato alla riga "2"
  E vedi testo "SCRUTINIO FINALE" in PDF analizzato alla riga "3"
  E imposti segnalibro PDF
  E vedi da segnalibro il testo "@alunno_5A_1:cognome,nome #noc() #str(NON)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_5A_2:cognome,nome @esito_F_5A_2:credito #sum(@alunno_5A_2:credito3,@alunno_5A_2:credito4) #sum(@esito_F_5A_2:credito,@alunno_5A_2:credito3,@alunno_5A_2:credito4) #nos(NON) #nos(SOSPESO)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_5A_3:cognome,nome @esito_F_5A_3:credito #sum(@alunno_5A_3:credito3,@alunno_5A_3:credito4) #sum(@esito_F_5A_3:credito,@alunno_5A_3:credito3,@alunno_5A_3:credito4) #nos(NON) #nos(SOSPESO)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_5A_4:cognome,nome @esito_F_5A_4:credito #sum(@alunno_5A_4:credito3,@alunno_5A_4:credito4) #sum(@esito_F_5A_4:credito,@alunno_5A_4:credito3,@alunno_5A_4:credito4) #nos(NON) #nos(SOSPESO)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_5A_5:cognome,nome @esito_F_5A_5:credito #sum(@alunno_5A_5:credito3,@alunno_5A_5:credito4) #sum(@esito_F_5A_5:credito,@alunno_5A_5:credito3,@alunno_5A_5:credito4) #nos(NON) #nos(SOSPESO)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_5A_6:cognome,nome @esito_F_5A_6:credito #sum(@alunno_5A_6:credito3,@alunno_5A_6:credito4) #sum(@esito_F_5A_6:credito,@alunno_5A_6:credito3,@alunno_5A_6:credito4) #nos(NON) #nos(SOSPESO)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_5A_7:cognome,nome @esito_F_5A_7:credito #sum(@alunno_5A_7:credito3,@alunno_5A_7:credito4) #sum(@esito_F_5A_7:credito,@alunno_5A_7:credito3,@alunno_5A_7:credito4) #nos(NON) #nos(SOSPESO)" in PDF analizzato in una riga
  E vedi poi testo "#dat(@scrutinio_1A_F:data) #str(Dirigente) @preside:nome,cognome" in PDF analizzato in "2" righe

Scenario: controllo tabellone esiti classe articolata
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe           |
    | @classe_3CAMB:id |
  Quando click su "Scarica" in sezione "#gs-main table tbody tr" che contiene "Tabellone esiti"
  E analizzi PDF "archivio/scrutini/finale/3CAMB/3CAMB-scrutinio-finale-tabellone-esiti.pdf"
  Allora vedi testo "CLASSE 3ª C-AMB" in PDF analizzato alla riga "2"
  E vedi testo "SCRUTINIO FINALE" in PDF analizzato alla riga "3"
  E imposti segnalibro PDF
  E vedi da segnalibro il testo "@alunno_3CAMB_1:cognome,nome #noc() #str(SOSPESO)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_3CAMB_2:cognome,nome @esito_F_3CAMB_2:credito #nos(NON) #nos(SOSPESO)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@alunno_sostegno_4:cognome,nome #noc() #str(NON)" in PDF analizzato in una riga
  E vedi poi testo "#dat(@scrutinio_1A_F:data) #str(Dirigente) @preside:nome,cognome" in PDF analizzato in "2" righe

Scenario: controllo debiti
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "{{@alunno_1A_1:cognome}} {{@alunno_1A_1:nome}}" in sezione "#gs-main table tbody tr" che contiene "Comunicazione Debiti"
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

Scenario: controllo debiti per classe articolata
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe           |
    | @classe_3CAMB:id |
  Quando click su "{{@alunno_3CAMB_1:cognome}} {{@alunno_3CAMB_1:nome}}" in sezione "#gs-main table tbody tr" che contiene "Comunicazione Debiti"
  E analizzi PDF "archivio/scrutini/finale/3CAMB/3CAMB-scrutinio-finale-debiti-{{@alunno_3CAMB_1:id}}.pdf"
  Allora vedi testo "@alunno_3CAMB_1:cognome @alunno_3CAMB_1:nome" in PDF analizzato alla riga "3"
  E vedi testo "3ª C-AMB" in PDF analizzato alla riga "4"
  E vedi poi testo "SOSPENSIONE DEL GIUDIZIO" in PDF analizzato in "2" righe
  E vedi poi testo "#str(MATERIA) #str(VOTO) #str(Argomenti) #str(Modalità)" in PDF analizzato in una riga
  E vedi poi testo "@materia_curricolare_1:nome #cas(@voto_F_3CAMB_0:unico,0,NC,@voto_F_3CAMB_0:unico) #str(Argomento) #str(Corso)" in PDF analizzato in "2" righe
  E vedi poi testo "@materia_curricolare_2:nome #cas(@voto_F_3CAMB_1:unico,0,NC,@voto_F_3CAMB_1:unico) #str(Argomento) #str(Corso)" in PDF analizzato in "2" righe
  E vedi poi testo "@materia_curricolare_3:nome #cas(@voto_F_3CAMB_2:unico,0,NC,@voto_F_3CAMB_2:unico) #str(Argomento) #str(Corso)" in PDF analizzato in "2" righe
  E vedi poi testo "#dat(@scrutinio_1A_F:data) #str(Dirigente) @preside:nome,cognome" in PDF analizzato in "2" righe

Scenario: controllo carenze
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "{{@alunno_sostegno_1:cognome}} {{@alunno_sostegno_1:nome}}" in sezione "#gs-main table tbody tr" che contiene "Comunicazione Carenze"
  E analizzi PDF "archivio/scrutini/finale/1A/1A-scrutinio-finale-carenze-{{@alunno_sostegno_1:id}}.pdf"
  Allora vedi testo "@alunno_sostegno_1:cognome @alunno_sostegno_1:nome" in PDF analizzato alla riga "3"
  E vedi testo "1ª A" in PDF analizzato alla riga "4"
  E vedi poi testo "Comunicazione per il recupero autonomo" in PDF analizzato in una riga
  E vedi poi testo "#str(MATERIA) #str(Argomenti)" in PDF analizzato in una riga
  E imposti segnalibro PDF
  E vedi da segnalibro il testo "@materia_curricolare_5:nome #str(Argomento)" in PDF analizzato in una riga
  E vedi da segnalibro il testo "@materia_itp_1:nome #str(Argomento)" in PDF analizzato in una riga
  E vedi poi testo "#dat(@scrutinio_1A_F:data) #str(Dirigente) @preside:nome,cognome" in PDF analizzato in "2" righe

Scenario: controllo carenze per classe articolata
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe           |
    | @classe_3CAMB:id |
  Quando click su "{{@alunno_3CAMB_1:cognome}} {{@alunno_3CAMB_1:nome}}" in sezione "#gs-main table tbody tr" che contiene "Comunicazione Carenze"
  E analizzi PDF "archivio/scrutini/finale/3CAMB/3CAMB-scrutinio-finale-carenze-{{@alunno_3CAMB_1:id}}.pdf"
  Allora vedi testo "@alunno_3CAMB_1:cognome @alunno_3CAMB_1:nome" in PDF analizzato alla riga "3"
  E vedi testo "3ª C-AMB" in PDF analizzato alla riga "4"
  E vedi poi testo "Comunicazione per il recupero autonomo" in PDF analizzato in una riga
  E vedi poi testo "#str(MATERIA) #str(Argomenti)" in PDF analizzato in una riga
  E vedi poi testo "@materia_curricolare_4:nome #str(Argomento)" in PDF analizzato in una riga
  E vedi poi testo "#dat(@scrutinio_3CAMB_F:data) #str(Dirigente) @preside:nome,cognome" in PDF analizzato in "2" righe

Scenario: controllo non ammessi
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "{{@alunno_alternativa_1:cognome}} {{@alunno_alternativa_1:nome}}" in sezione "#gs-main table tbody tr" che contiene "Comunicazione Non Ammessi"
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

Scenario: controllo non ammessi per assenze
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "{{@alunno_1A_2:cognome}} {{@alunno_1A_2:nome}}" in sezione "#gs-main table tbody tr" che contiene "Comunicazione Non Ammessi"
  E analizzi PDF "archivio/scrutini/finale/1A/1A-scrutinio-finale-non-ammesso-{{@alunno_1A_2:id}}.pdf"
  Allora vedi testo "@alunno_1A_2:cognome @alunno_1A_2:nome" in PDF analizzato alla riga "3"
  E vedi testo "1ª A" in PDF analizzato alla riga "4"
  E vedi poi testo "/esclusione dell'alunn.*dallo scrutinio e pertanto la sua NON AMMISSIONE/ui" in PDF analizzato in "2" righe
  E vedi poi testo "#dat(@scrutinio_1A_F:data) #str(Dirigente) @preside:nome,cognome" in PDF analizzato in "2" righe

Scenario: controllo non ammessi per la classe articolata
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe           |
    | @classe_3CAMB:id |
  Quando click su "{{@alunno_sostegno_4:cognome}} {{@alunno_sostegno_4:nome}}" in sezione "#gs-main table tbody tr" che contiene "Comunicazione Non Ammessi"
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

Scenario: controllo pagella
  Data pagina attiva "pagelle_alunno" con parametri:
    | classe        | alunno                | tipo | periodo |
    | @classe_1A:id | @alunno_sostegno_1:id | P    | F       |
  Quando analizzi PDF "archivio/scrutini/finale/1A/1A-scrutinio-finale-voti-{{@alunno_sostegno_1:id}}.pdf"
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

Scenario: controllo pagella classe terza
  Data pagina attiva "pagelle_alunno" con parametri:
    | classe        | alunno          | tipo | periodo |
    | @classe_3A:id | @alunno_3A_3:id | P    | F       |
  Quando analizzi PDF "archivio/scrutini/finale/3A/3A-scrutinio-finale-voti-{{@alunno_3A_3:id}}.pdf"
  Allora vedi testo "@alunno_3A_3:cognome @alunno_3A_3:nome" in PDF analizzato alla riga "3"
  E vedi testo "3ª A" in PDF analizzato alla riga "4"
  E vedi poi testo "#str(MATERIA) #str(VOTO) #str(ORE)" in PDF analizzato in una riga
  E vedi poi testo "#str(Religione) #cas(@voto_F_3A_26:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) @voto_F_3A_26:assenze" in PDF analizzato in una riga
  E vedi poi testo "@materia_curricolare_1:nome #cas(@voto_F_3A_20:unico,0,NC,@voto_F_3A_20:unico) @voto_F_3A_20:assenze" in PDF analizzato in una riga
  E vedi poi testo "@materia_curricolare_2:nome #cas(@voto_F_3A_21:unico,0,NC,@voto_F_3A_21:unico) @voto_F_3A_21:assenze" in PDF analizzato in una riga
  E vedi poi testo "@materia_curricolare_3:nome #cas(@voto_F_3A_22:unico,0,NC,@voto_F_3A_22:unico) @voto_F_3A_22:assenze" in PDF analizzato in una riga
  E vedi poi testo "@materia_curricolare_4:nome #cas(@voto_F_3A_23:unico,0,NC,@voto_F_3A_23:unico) @voto_F_3A_23:assenze" in PDF analizzato in una riga
  E vedi poi testo "@materia_itp_1:nome #cas(@voto_F_3A_25:unico,0,NC,@voto_F_3A_25:unico) @voto_F_3A_25:assenze" in PDF analizzato in una riga
  E vedi poi testo "@materia_curricolare_5:nome #cas(@voto_F_3A_24:unico,0,NC,@voto_F_3A_24:unico) @voto_F_3A_24:assenze" in PDF analizzato in una riga
  E vedi poi testo "@materia_EDCIVICA:nome #cas(@voto_F_3A_27:unico,2,NC,@voto_F_3A_27:unico) @voto_F_3A_27:assenze" in PDF analizzato in una riga
  E vedi poi testo "@materia_CONDOTTA:nome #cas(@voto_F_3A_28:unico,4,NC,@voto_F_3A_28:unico)" in PDF analizzato in una riga
  E vedi poi testo "#str(Media) #med(@voto_F_3A_20:unico,@voto_F_3A_21:unico,@voto_F_3A_22:unico,@voto_F_3A_23:unico,@voto_F_3A_24:unico,@voto_F_3A_25:unico,@voto_F_3A_27:unico,@voto_F_3A_28:unico) #str(Credito) @esito_F_3A_3:credito" in PDF analizzato in una riga
  E vedi poi testo "#dat(@scrutinio_3A_F:data) #str(Dirigente) @preside:nome,cognome" in PDF analizzato in "2" righe

Scenario: controllo pagella classe quarta
  Data pagina attiva "pagelle_alunno" con parametri:
    | classe        | alunno          | tipo | periodo |
    | @classe_4A:id | @alunno_4A_3:id | P    | F       |
  Quando analizzi PDF "archivio/scrutini/finale/4A/4A-scrutinio-finale-voti-{{@alunno_4A_3:id}}.pdf"
  Allora vedi testo "@alunno_4A_3:cognome @alunno_4A_3:nome" in PDF analizzato alla riga "3"
  E vedi testo "4ª A" in PDF analizzato alla riga "4"
  E vedi poi testo "#str(MATERIA) #str(VOTO) #str(ORE)" in PDF analizzato in una riga
  E vedi poi testo "#str(Religione) #cas(@voto_F_4A_26:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) @voto_F_4A_26:assenze" in PDF analizzato in una riga
  E vedi poi testo "@materia_curricolare_1:nome #cas(@voto_F_4A_20:unico,0,NC,@voto_F_4A_20:unico) @voto_F_4A_20:assenze" in PDF analizzato in una riga
  E vedi poi testo "@materia_curricolare_2:nome #cas(@voto_F_4A_21:unico,0,NC,@voto_F_4A_21:unico) @voto_F_4A_21:assenze" in PDF analizzato in una riga
  E vedi poi testo "@materia_curricolare_3:nome #cas(@voto_F_4A_22:unico,0,NC,@voto_F_4A_22:unico) @voto_F_4A_22:assenze" in PDF analizzato in una riga
  E vedi poi testo "@materia_curricolare_4:nome #cas(@voto_F_4A_23:unico,0,NC,@voto_F_4A_23:unico) @voto_F_4A_23:assenze" in PDF analizzato in una riga
  E vedi poi testo "@materia_itp_1:nome #cas(@voto_F_4A_25:unico,0,NC,@voto_F_4A_25:unico) @voto_F_4A_25:assenze" in PDF analizzato in una riga
  E vedi poi testo "@materia_curricolare_5:nome #cas(@voto_F_4A_24:unico,0,NC,@voto_F_4A_24:unico) @voto_F_4A_24:assenze" in PDF analizzato in una riga
  E vedi poi testo "@materia_EDCIVICA:nome #cas(@voto_F_4A_27:unico,2,NC,@voto_F_4A_27:unico) @voto_F_4A_27:assenze" in PDF analizzato in una riga
  E vedi poi testo "@materia_CONDOTTA:nome #cas(@voto_F_4A_28:unico,4,NC,@voto_F_4A_28:unico)" in PDF analizzato in una riga
  E vedi poi testo "#str(Media) #med(@voto_F_4A_20:unico,@voto_F_4A_21:unico,@voto_F_4A_22:unico,@voto_F_4A_23:unico,@voto_F_4A_24:unico,@voto_F_4A_25:unico,@voto_F_4A_27:unico,@voto_F_4A_28:unico) #str(Credito) @esito_F_4A_3:credito #str(precedente) @alunno_4A_3:credito3" in PDF analizzato in una riga
  E vedi poi testo "#dat(@scrutinio_4A_F:data) #str(Dirigente) @preside:nome,cognome" in PDF analizzato in "2" righe

Scenario: controllo pagella classe quinta
  Data pagina attiva "pagelle_alunno" con parametri:
    | classe        | alunno          | tipo | periodo |
    | @classe_5A:id | @alunno_5A_3:id | P    | F       |
  Quando analizzi PDF "archivio/scrutini/finale/5A/5A-scrutinio-finale-voti-{{@alunno_5A_3:id}}.pdf"
  Allora vedi testo "@alunno_5A_3:cognome @alunno_5A_3:nome" in PDF analizzato alla riga "3"
  E vedi testo "5ª A" in PDF analizzato alla riga "4"
  E vedi poi testo "#str(MATERIA) #str(VOTO) #str(ORE)" in PDF analizzato in una riga
  E vedi poi testo "#str(Religione) #cas(@voto_F_5A_26:unico,20:21:22:23:24:25:26:27,NC:Insufficiente:Mediocre:Sufficiente:Discreto:Buono:Distinto:Ottimo,0) @voto_F_5A_26:assenze" in PDF analizzato in una riga
  E vedi poi testo "@materia_curricolare_1:nome #cas(@voto_F_5A_20:unico,0,NC,@voto_F_5A_20:unico) @voto_F_5A_20:assenze" in PDF analizzato in una riga
  E vedi poi testo "@materia_curricolare_2:nome #cas(@voto_F_5A_21:unico,0,NC,@voto_F_5A_21:unico) @voto_F_5A_21:assenze" in PDF analizzato in una riga
  E vedi poi testo "@materia_curricolare_3:nome #cas(@voto_F_5A_22:unico,0,NC,@voto_F_5A_22:unico) @voto_F_5A_22:assenze" in PDF analizzato in una riga
  E vedi poi testo "@materia_curricolare_4:nome #cas(@voto_F_5A_23:unico,0,NC,@voto_F_5A_23:unico) @voto_F_5A_23:assenze" in PDF analizzato in una riga
  E vedi poi testo "@materia_itp_1:nome #cas(@voto_F_5A_25:unico,0,NC,@voto_F_5A_25:unico) @voto_F_5A_25:assenze" in PDF analizzato in una riga
  E vedi poi testo "@materia_curricolare_5:nome #cas(@voto_F_5A_24:unico,0,NC,@voto_F_5A_24:unico) @voto_F_5A_24:assenze" in PDF analizzato in una riga
  E vedi poi testo "@materia_EDCIVICA:nome #cas(@voto_F_5A_27:unico,2,NC,@voto_F_5A_27:unico) @voto_F_5A_27:assenze" in PDF analizzato in una riga
  E vedi poi testo "@materia_CONDOTTA:nome #cas(@voto_F_5A_28:unico,4,NC,@voto_F_5A_28:unico)" in PDF analizzato in una riga
  E vedi poi testo "#str(Media) #med(@voto_F_5A_20:unico,@voto_F_5A_21:unico,@voto_F_5A_22:unico,@voto_F_5A_23:unico,@voto_F_5A_24:unico,@voto_F_5A_25:unico,@voto_F_5A_27:unico,@voto_F_5A_28:unico) #str(Credito) @esito_F_5A_3:credito #str(precedente) #sum(@alunno_5A_3:credito3,@alunno_5A_3:credito4)" in PDF analizzato in una riga
  E vedi poi testo "#dat(@scrutinio_5A_F:data) #str(Dirigente) @preside:nome,cognome" in PDF analizzato in "2" righe

Scenario: controllo pagella per classe articolata
  Data pagina attiva "pagelle_alunno" con parametri:
    | classe           | alunno             | tipo | periodo |
    | @classe_3CAMB:id | @alunno_3CAMB_1:id | P    | F       |
  Quando analizzi PDF "archivio/scrutini/finale/3CAMB/3CAMB-scrutinio-finale-voti-{{@alunno_3CAMB_1:id}}.pdf"
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
