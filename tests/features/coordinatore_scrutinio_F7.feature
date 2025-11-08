# language: it

Funzionalità: settimo passo dello scrutinio finale
  Per svolgere il settimo passo dello scrutinio finale
  Come utente staff
  Bisogna controllare visualizzazione della pagina per le prime
  Bisogna controllare visualizzazione della pagina per le seconde
  Bisogna controllare visualizzazione della pagina per il triennio
  Bisogna controllare visualizzazione con la classe articolata
  Utilizzando "_scrutinioF7Fixtures.yml"


Contesto: login utente staff
	Dato login utente con ruolo esatto "Staff"


###############################################################################
# Bisogna controllare visualizzazione della pagina per le prime

Scenario: prime - visualizzazione pagina
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Allora la sezione "#gs-main .alert-success" contiene "Vai al passo successivo"
  E non vedi la tabella:
    | Alunno | Media | Certificazione |
  E non vedi la tabella:
    | Alunno | Media | Credito |
  E non vedi la tabella:
    | Alunno | Media | Credito anni precedenti | Credito |

Scenario: prime - passo precedente
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "passo precedente"
  Allora la sezione "#gs-main h2" contiene "Esito dello scrutinio"

Scenario: prime - passo successivo
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "Conferma"
  Allora vedi la pagina "coordinatore_scrutinio" con parametri:
    | classe        | stato |
    | @classe_1A:id | 8     |
  E la sezione "#gs-main h2" contiene "Comunicazioni"

Scenario: prime - memorizzazione dati e passo successivo
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_1A:id |
  Quando click su "Conferma"
  E click su "passo precedente"
  Allora la sezione "#gs-main .alert-success" contiene "Vai al passo successivo"


###############################################################################
# Bisogna controllare visualizzazione della pagina per le seconde

Scenario: seconde - visualizzazione pagina
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_2A:id |
  Allora la sezione "#gs-main h2" contiene "Certificazione competenze"
  E vedi nella tabella "1" le colonne:
    | Alunno | Media | Certificazione |
  E vedi la tabella "2" non ordinata senza intestazioni:
    | Alunno | Media | Certificazione |
    | @alunno_2A_2:cognome+ +@alunno_2A_2:nome | 6,00 | |
    | @alunno_2A_3:cognome+ +@alunno_2A_3:nome | 6,88 | |
    | @alunno_2A_4:cognome+ +@alunno_2A_4:nome | 7,63 | |
    | @alunno_2A_5:cognome+ +@alunno_2A_5:nome | 8,38 | |
    | @alunno_2A_6:cognome+ +@alunno_2A_6:nome | 9,13 | |

Scenario: seconde - visualizzazione riquadro inserimento certificazione
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_2A:id |
  Quando click su "Compila la certificazione" in sezione "#gs-main form table tbody tr" che contiene "@alunno_2A_2:cognome+ +@alunno_2A_2:nome"
  Allora la sezione "#gs-main #gs-modal-remote .modal-title.gs-h3" contiene "@alunno_2A_2:cognome+ +@alunno_2A_2:nome"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li:nth-child(1) div" contiene "/Religione.*Sufficiente/"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li:nth-child(2) div" contiene "/Italiano 6/"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li:nth-child(3) div" contiene "/Storia 6/"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li:nth-child(4) div" contiene "/Inglese 6/"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li:nth-child(5) div" contiene "/Matematica 6/"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li:nth-child(6) div" contiene "/Informatica 6/"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li:nth-child(7) div" contiene "/Sc\. motorie 6/"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li:nth-child(8) div" contiene "/Ed\. civica 6/"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li:nth-child(9) div" contiene "/Condotta 6/"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li:nth-child(10) div" contiene "/Media 6,00 Assenze 9,47%/"
  E la sezione "#gs-main #gs-modal-remote .modal-body > div > ul" contiene "/LIVELLO A - AVANZATO: .* LIVELLO B - INTERMEDIO: .* LIVELLO C - BASE: .* LIVELLO D - INIZIALE:/"
  E la sezione "#gs-main #gs-modal-remote .modal-body .form-group:nth-child(3) > label" contiene "Competenza alfabetica funzionale"
  E la sezione "#gs-main #gs-modal-remote .modal-body .form-group:nth-child(4) > label" contiene "Competenza multilinguistica"
  E la sezione "#gs-main #gs-modal-remote .modal-body .form-group:nth-child(4) div:nth-child(4)" contiene "Inglese:"
  E la sezione "#gs-main #gs-modal-remote .modal-body .form-group:nth-child(4) div:nth-child(6)" contiene "Francese:"
  E la sezione "#gs-main #gs-modal-remote .modal-body .form-group:nth-child(5) > label" contiene "Competenza matematica e competenza in scienze, tecnologie e ingegneria"
  E la sezione "#gs-main #gs-modal-remote .modal-body .form-group:nth-child(6) > label" contiene "Competenza digitale"
  E la sezione "#gs-main #gs-modal-remote .modal-body .form-group:nth-child(7) > label" contiene "Competenza personale, sociale e capacità di imparare a imparare"
  E la sezione "#gs-main #gs-modal-remote .modal-body .form-group:nth-child(8) > label" contiene "Competenza in materia di cittadinanza"
  E la sezione "#gs-main #gs-modal-remote .modal-body .form-group:nth-child(9) > label" contiene "Competenza imprenditoriale"
  E la sezione "#gs-main #gs-modal-remote .modal-body .form-group:nth-child(10) > label" contiene "Competenza in materia di consapevolezza ed espressione culturali"
  E la sezione "#gs-main #gs-modal-remote .modal-body .form-group:nth-child(11) > label" contiene "ha inoltre mostrato significative competenze nello svolgimento di attività scolastiche e/o extrascolastiche, relativamente a:"

Scenario: seconde - visualizzazione pagina con dati mancanti
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_2A:id |
  Quando click su "Conferma"
  Allora la sezione "#gs-modal-error .alert-danger" contiene "?@alunno_2A_2:cognome+ +@alunno_2A_2:nome?@alunno_2A_3:cognome+ +@alunno_2A_3:nome?@alunno_2A_4:cognome+ +@alunno_2A_4:nome?@alunno_2A_5:cognome+ +@alunno_2A_5:nome?@alunno_2A_6:cognome+ +@alunno_2A_6:nome"

Scenario: seconde - visualizzazione passo precedente
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_2A:id |
  Quando click su "passo precedente"
  Allora la sezione "#gs-main h2" contiene "Esito dello scrutinio"

Scenario: seconde - visualizzazione passo successivo
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_2A:id |
  Quando click su "Compila la certificazione" in sezione "#gs-main form table tbody tr" che contiene "@alunno_2A_2:cognome+ +@alunno_2A_2:nome"
  E click su "Conferma"
  E click su "Compila la certificazione" in sezione "#gs-main form table tbody tr" che contiene "@alunno_2A_3:cognome+ +@alunno_2A_3:nome"
  E click su "Conferma"
  E click su "Compila la certificazione" in sezione "#gs-main form table tbody tr" che contiene "@alunno_2A_4:cognome+ +@alunno_2A_4:nome"
  E click su "Conferma"
  E click su "Compila la certificazione" in sezione "#gs-main form table tbody tr" che contiene "@alunno_2A_5:cognome+ +@alunno_2A_5:nome"
  E click su "Conferma"
  E click su "Compila la certificazione" in sezione "#gs-main form table tbody tr" che contiene "@alunno_2A_6:cognome+ +@alunno_2A_6:nome"
  E click su "Conferma"
  E click su "Conferma"
  Allora vedi la pagina "coordinatore_scrutinio" con parametri:
    | classe        | stato |
    | @classe_2A:id | 8     |
  E la sezione "#gs-main h2" contiene "Comunicazioni"

Scenario: seconde - memorizzazione dati e passo successivo
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_2A:id |
  Quando click su "Compila la certificazione" in sezione "#gs-main form table tbody tr" che contiene "@alunno_2A_2:cognome+ +@alunno_2A_2:nome"
  E click su "Conferma"
  E click su "Compila la certificazione" in sezione "#gs-main form table tbody tr" che contiene "@alunno_2A_3:cognome+ +@alunno_2A_3:nome"
  E click su "Conferma"
  E click su "Compila la certificazione" in sezione "#gs-main form table tbody tr" che contiene "@alunno_2A_4:cognome+ +@alunno_2A_4:nome"
  E click su "Conferma"
  E click su "Compila la certificazione" in sezione "#gs-main form table tbody tr" che contiene "@alunno_2A_5:cognome+ +@alunno_2A_5:nome"
  E click su "Conferma"
  E click su "Compila la certificazione" in sezione "#gs-main form table tbody tr" che contiene "@alunno_2A_6:cognome+ +@alunno_2A_6:nome"
  E click su "Conferma"
  E click su "Conferma"
  E click su "passo precedente"
  Allora la sezione "#gs-main h2" contiene "Certificazione competenze"
  E vedi la tabella "2" non ordinata senza intestazioni:
    | Alunno | Media | Certificazione |
    | @alunno_2A_2:cognome+ +@alunno_2A_2:nome | 6,00 | COMPILATA |
    | @alunno_2A_3:cognome+ +@alunno_2A_3:nome | 6,88 | COMPILATA |
    | @alunno_2A_4:cognome+ +@alunno_2A_4:nome | 7,63 | COMPILATA |
    | @alunno_2A_5:cognome+ +@alunno_2A_5:nome | 8,38 | COMPILATA |
    | @alunno_2A_6:cognome+ +@alunno_2A_6:nome | 9,13 | COMPILATA |

Scenario: seconde - memorizzazione dati e passo successivo con dettagli
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_2A:id |
  Quando click su "Compila la certificazione" in sezione "#gs-main form table tbody tr" che contiene "@alunno_2A_2:cognome+ +@alunno_2A_2:nome"
  E selezioni opzione "A" da lista "certificazione_competenza_alfabetica"
  E selezioni opzione "B" da lista "certificazione_competenza_linguistica1"
  E selezioni opzione "C" da lista "certificazione_competenza_linguistica2"
  E selezioni opzione "D" da lista "certificazione_competenza_matematica"
  E selezioni opzione "A" da lista "certificazione_competenza_digitale"
  E selezioni opzione "B" da lista "certificazione_competenza_personale"
  E selezioni opzione "C" da lista "certificazione_competenza_cittadinanza"
  E selezioni opzione "D" da lista "certificazione_competenza_imprenditoriale"
  E selezioni opzione "A" da lista "certificazione_competenza_culturale"
  E inserisci "Altra competenza" nel campo "certificazione_competenza_altro"
  E click su "Conferma"
  E click su "Compila la certificazione" in sezione "#gs-main form table tbody tr" che contiene "@alunno_2A_3:cognome+ +@alunno_2A_3:nome"
  E click su "Conferma"
  E click su "Compila la certificazione" in sezione "#gs-main form table tbody tr" che contiene "@alunno_2A_4:cognome+ +@alunno_2A_4:nome"
  E click su "Conferma"
  E click su "Compila la certificazione" in sezione "#gs-main form table tbody tr" che contiene "@alunno_2A_5:cognome+ +@alunno_2A_5:nome"
  E click su "Conferma"
  E click su "Compila la certificazione" in sezione "#gs-main form table tbody tr" che contiene "@alunno_2A_6:cognome+ +@alunno_2A_6:nome"
  E click su "Conferma"
  E click su "Conferma"
  E click su "passo precedente"
  E click su "Modifica la certificazione" in sezione "#gs-main form table tbody tr" che contiene "@alunno_2A_2:cognome+ +@alunno_2A_2:nome"
  Allora opzione "A" selezionata da lista "certificazione_competenza_alfabetica"
  E opzione "B" selezionata da lista "certificazione_competenza_linguistica1"
  E opzione "C" selezionata da lista "certificazione_competenza_linguistica2"
  E opzione "D" selezionata da lista "certificazione_competenza_matematica"
  E opzione "A" selezionata da lista "certificazione_competenza_digitale"
  E opzione "B" selezionata da lista "certificazione_competenza_personale"
  E opzione "C" selezionata da lista "certificazione_competenza_cittadinanza"
  E opzione "D" selezionata da lista "certificazione_competenza_imprenditoriale"
  E opzione "A" selezionata da lista "certificazione_competenza_culturale"
  E il campo "certificazione_competenza_altro" contiene "Altra competenza"


###############################################################################
# Bisogna controllare visualizzazione della pagina per il triennio

Scenario: terze - visualizzazione pagina
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_3A:id |
  Allora la sezione "#gs-main h2" contiene "Attribuzione crediti"
  E vedi nella tabella "1" le colonne:
    | Alunno | Media | Credito |
  E vedi la tabella "2" non ordinata senza intestazioni:
    | Alunno | Media | Credito |
    | @alunno_3A_2:cognome+ +@alunno_3A_2:nome | 6,00 | |
    | @alunno_3A_3:cognome+ +@alunno_3A_3:nome | 6,88 | |
    | @alunno_3A_4:cognome+ +@alunno_3A_4:nome | 7,63 | |
    | @alunno_3A_5:cognome+ +@alunno_3A_5:nome | 8,38 | |
    | @alunno_3A_6:cognome+ +@alunno_3A_6:nome | 9,13 | |

Scenario: quarte - visualizzazione pagina
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_4A:id |
  Allora la sezione "#gs-main h2" contiene "Attribuzione crediti"
  E vedi nella tabella "1" le colonne:
    | Alunno | Media | Credito anni precedenti | Credito |
  E vedi la tabella "2" non ordinata senza intestazioni:
    | Alunno | Media | Credito anni precedenti | Credito |
    | @alunno_4A_2:cognome+ +@alunno_4A_2:nome | 6,00 | @alunno_4A_2:credito3 | |
    | @alunno_4A_3:cognome+ +@alunno_4A_3:nome | 6,88 | @alunno_4A_3:credito3 | |
    | @alunno_4A_4:cognome+ +@alunno_4A_4:nome | 7,63 | @alunno_4A_4:credito3 | |
    | @alunno_4A_5:cognome+ +@alunno_4A_5:nome | 8,38 | @alunno_4A_5:credito3 | |
    | @alunno_4A_6:cognome+ +@alunno_4A_6:nome | 9,13 | @alunno_4A_6:credito3 | |

Scenario: quinte - visualizzazione pagina
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_5A:id |
  Allora la sezione "#gs-main h2" contiene "Attribuzione crediti"
  E vedi nella tabella "1" le colonne:
    | Alunno | Media | Credito anni precedenti | Credito |
  E vedi la tabella "2" non ordinata senza intestazioni:
    | Alunno | Media | Credito anni precedenti | Credito |
    | @alunno_5A_2:cognome+ +@alunno_5A_2:nome | 6,00 | #sum(@alunno_5A_2:credito3,@alunno_5A_2:credito4) | |
    | @alunno_5A_3:cognome+ +@alunno_5A_3:nome | 6,88 | #sum(@alunno_5A_3:credito3,@alunno_5A_3:credito4) | |
    | @alunno_5A_4:cognome+ +@alunno_5A_4:nome | 7,63 | #sum(@alunno_5A_4:credito3,@alunno_5A_4:credito4) | |
    | @alunno_5A_5:cognome+ +@alunno_5A_5:nome | 8,38 | #sum(@alunno_5A_5:credito3,@alunno_5A_5:credito4) | |
    | @alunno_5A_6:cognome+ +@alunno_5A_6:nome | 9,13 | #sum(@alunno_5A_6:credito3,@alunno_5A_6:credito4) | |
    | @alunno_5A_7:cognome+ +@alunno_5A_7:nome | 5,88 | #sum(@alunno_5A_7:credito3,@alunno_5A_7:credito4) | |

Scenario: terze - visualizzazione riquadro inserimento credito
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_3A:id |
  Quando click su "Inserisci il credito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_3A_2:cognome+ +@alunno_3A_2:nome"
  Allora la sezione "#gs-main #gs-modal-remote .modal-title.gs-h3" contiene "@alunno_3A_2:cognome+ +@alunno_3A_2:nome"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li:nth-child(1) div" contiene "/Religione.*Sufficiente/"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li:nth-child(2) div" contiene "/Italiano 6/"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li:nth-child(3) div" contiene "/Storia 6/"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li:nth-child(4) div" contiene "/Inglese 6/"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li:nth-child(5) div" contiene "/Matematica 6/"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li:nth-child(6) div" contiene "/Informatica 6/"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li:nth-child(7) div" contiene "/Sc\. motorie 6/"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li:nth-child(8) div" contiene "/Ed\. civica 6/"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li:nth-child(9) div" contiene "/Condotta 6/"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li:nth-child(10) div" contiene "/Media 6,00 Assenze 9,47%/"
  E la sezione "#gs-main #gs-modal-remote .modal-body .form-group:nth-child(2)" non contiene "/Credito scolastico Frequenza assidua Interesse e impegno .* partecipazione alla FSL .* partecipazione alle lezioni della Religione .* Organi Collegiali/"
  E la sezione "#gs-main #gs-modal-remote .modal-body .form-group:nth-child(2)" contiene "/condotta inferiore al nove/"
  E la sezione "#gs-main #gs-modal-remote .modal-body .form-group:nth-child(3)" contiene "/intervallo: 7 - 8/"

Scenario: quarte - visualizzazione riquadro inserimento credito
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_4A:id |
  Quando click su "Inserisci il credito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_4A_2:cognome+ +@alunno_4A_2:nome"
  Allora la sezione "#gs-main #gs-modal-remote .modal-title.gs-h3" contiene "@alunno_4A_2:cognome+ +@alunno_4A_2:nome"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li:nth-child(1) div" contiene "/Religione.*Sufficiente/"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li:nth-child(2) div" contiene "/Italiano 6/"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li:nth-child(3) div" contiene "/Storia 6/"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li:nth-child(4) div" contiene "/Inglese 6/"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li:nth-child(5) div" contiene "/Matematica 6/"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li:nth-child(6) div" contiene "/Informatica 6/"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li:nth-child(7) div" contiene "/Sc\. motorie 6/"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li:nth-child(8) div" contiene "/Ed\. civica 6/"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li:nth-child(9) div" contiene "/Condotta 6/"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li:nth-child(10) div" contiene "/Media 6,00 Assenze 9,47%/"
  E la sezione "#gs-main #gs-modal-remote .modal-body .form-group:nth-child(2)" non contiene "/Credito scolastico Frequenza assidua Interesse e impegno .* partecipazione alla FSL .* partecipazione alle lezioni della Religione .* Organi Collegiali/"
  E la sezione "#gs-main #gs-modal-remote .modal-body .form-group:nth-child(2)" contiene "/condotta inferiore al nove/"
  E la sezione "#gs-main #gs-modal-remote .modal-body .form-group:nth-child(3)" contiene "/intervallo: 8 - 9/"

Scenario: quinte - visualizzazione riquadro inserimento credito
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_5A:id |
  Quando click su "Inserisci il credito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_5A_2:cognome+ +@alunno_5A_2:nome"
  Allora la sezione "#gs-main #gs-modal-remote .modal-title.gs-h3" contiene "@alunno_5A_2:cognome+ +@alunno_5A_2:nome"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li:nth-child(1) div" contiene "/Religione.*Sufficiente/"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li:nth-child(2) div" contiene "/Italiano 6/"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li:nth-child(3) div" contiene "/Storia 6/"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li:nth-child(4) div" contiene "/Inglese 6/"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li:nth-child(5) div" contiene "/Matematica 6/"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li:nth-child(6) div" contiene "/Informatica 6/"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li:nth-child(7) div" contiene "/Sc\. motorie 6/"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li:nth-child(8) div" contiene "/Ed\. civica 6/"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li:nth-child(9) div" contiene "/Condotta 6/"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li:nth-child(10) div" contiene "/Media 6,00 Assenze 9,47%/"
  E la sezione "#gs-main #gs-modal-remote .modal-body .form-group:nth-child(2)" non contiene "/Credito scolastico Frequenza assidua Interesse e impegno .* partecipazione alla FSL .* partecipazione alle lezioni della Religione .* Organi Collegiali/"
  E la sezione "#gs-main #gs-modal-remote .modal-body .form-group:nth-child(2)" contiene "/condotta inferiore al nove/"
  E la sezione "#gs-main #gs-modal-remote .modal-body .form-group:nth-child(3)" contiene "/intervallo: 9 - 10/"

Scenario: quinte - visualizzazione riquadro inserimento credito con insufficienza
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_5A:id |
  Quando click su "Inserisci il credito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_5A_7:cognome+ +@alunno_5A_7:nome"
  Allora la sezione "#gs-main #gs-modal-remote .modal-title.gs-h3" contiene "@alunno_5A_7:cognome+ +@alunno_5A_7:nome"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li:nth-child(1) div" contiene "/Religione.*Sufficiente/"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li:nth-child(2) div" contiene "/Italiano 6/"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li:nth-child(3) div" contiene "/Storia 6/"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li:nth-child(4) div" contiene "/Inglese 6/"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li:nth-child(5) div" contiene "/Matematica 6/"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li:nth-child(6) div" contiene "/Informatica 6/"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li:nth-child(7) div" contiene "/Sc\. motorie 5/"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li:nth-child(8) div" contiene "/Ed\. civica 6/"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li:nth-child(9) div" contiene "/Condotta 6/"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li:nth-child(10) div" contiene "/Media 5,88 Assenze 9,47%/"
  E la sezione "#gs-main #gs-modal-remote .modal-body .form-group:nth-child(2)" non contiene "/Credito scolastico Frequenza assidua Interesse e impegno .* partecipazione alla FSL .* partecipazione alle lezioni della Religione .* Organi Collegiali/"
  E la sezione "#gs-main #gs-modal-remote .modal-body .form-group:nth-child(2)" contiene "/condotta inferiore al nove/"
  E la sezione "#gs-main #gs-modal-remote .modal-body .form-group:nth-child(3)" contiene "/intervallo: 7 - 8/"

Scenario: terze - visualizzazione pagina con dati mancanti
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_3A:id |
  Quando click su "Conferma"
  Allora la sezione "#gs-modal-error .alert-danger" contiene "?@alunno_3A_2:cognome+ +@alunno_3A_2:nome?@alunno_3A_3:cognome+ +@alunno_3A_3:nome?@alunno_3A_4:cognome+ +@alunno_3A_4:nome?@alunno_3A_5:cognome+ +@alunno_3A_5:nome?@alunno_3A_6:cognome+ +@alunno_3A_6:nome"

Scenario: quarte - visualizzazione pagina con dati mancanti
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_4A:id |
  Quando click su "Conferma"
  Allora la sezione "#gs-modal-error .alert-danger" contiene "?@alunno_4A_2:cognome+ +@alunno_4A_2:nome?@alunno_4A_3:cognome+ +@alunno_4A_3:nome?@alunno_4A_4:cognome+ +@alunno_4A_4:nome?@alunno_4A_5:cognome+ +@alunno_4A_5:nome?@alunno_4A_6:cognome+ +@alunno_4A_6:nome"

Scenario: quinte - visualizzazione pagina con dati mancanti
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_5A:id |
  Quando click su "Conferma"
  Allora la sezione "#gs-modal-error .alert-danger" contiene "?@alunno_5A_2:cognome+ +@alunno_5A_2:nome?@alunno_5A_3:cognome+ +@alunno_5A_3:nome?@alunno_5A_4:cognome+ +@alunno_5A_4:nome?@alunno_5A_5:cognome+ +@alunno_5A_5:nome?@alunno_5A_6:cognome+ +@alunno_5A_6:nome?@alunno_5A_7:cognome+ +@alunno_5A_7:nome"

Scenario: terze - visualizzazione passo precedente
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_3A:id |
  Quando click su "passo precedente"
  Allora la sezione "#gs-main h2" contiene "Esito dello scrutinio"

Scenario: quarte - visualizzazione passo precedente
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_4A:id |
  Quando click su "passo precedente"
  Allora la sezione "#gs-main h2" contiene "Esito dello scrutinio"

Scenario: quinte - visualizzazione passo precedente
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_5A:id |
  Quando click su "passo precedente"
  Allora la sezione "#gs-main h2" contiene "Esito dello scrutinio"

Scenario: terze - visualizzazione passo successivo
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_3A:id |
  Quando click su "Inserisci il credito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_3A_2:cognome+ +@alunno_3A_2:nome"
  E click su "Conferma"
  E click su "Inserisci il credito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_3A_3:cognome+ +@alunno_3A_3:nome"
  E click su "Conferma"
  E click su "Inserisci il credito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_3A_4:cognome+ +@alunno_3A_4:nome"
  E click su "Conferma"
  E click su "Inserisci il credito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_3A_5:cognome+ +@alunno_3A_5:nome"
  E click su "Conferma"
  E click su "Inserisci il credito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_3A_6:cognome+ +@alunno_3A_6:nome"
  E click su "Conferma"
  E click su "Conferma"
  Allora vedi la pagina "coordinatore_scrutinio" con parametri:
    | classe        | stato |
    | @classe_3A:id | 8     |
  E la sezione "#gs-main h2" contiene "Comunicazioni"

Scenario: quarte - visualizzazione passo successivo
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_4A:id |
  Quando click su "Inserisci il credito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_4A_2:cognome+ +@alunno_4A_2:nome"
  E click su "Conferma"
  E click su "Inserisci il credito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_4A_3:cognome+ +@alunno_4A_3:nome"
  E click su "Conferma"
  E click su "Inserisci il credito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_4A_4:cognome+ +@alunno_4A_4:nome"
  E click su "Conferma"
  E click su "Inserisci il credito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_4A_5:cognome+ +@alunno_4A_5:nome"
  E click su "Conferma"
  E click su "Inserisci il credito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_4A_6:cognome+ +@alunno_4A_6:nome"
  E click su "Conferma"
  E click su "Conferma"
  Allora vedi la pagina "coordinatore_scrutinio" con parametri:
    | classe        | stato |
    | @classe_4A:id | 8     |
  E la sezione "#gs-main h2" contiene "Comunicazioni"

Scenario: quinte - visualizzazione passo successivo
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_5A:id |
  Quando click su "Inserisci il credito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_5A_2:cognome+ +@alunno_5A_2:nome"
  E click su "Conferma"
  E click su "Inserisci il credito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_5A_3:cognome+ +@alunno_5A_3:nome"
  E click su "Conferma"
  E click su "Inserisci il credito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_5A_4:cognome+ +@alunno_5A_4:nome"
  E click su "Conferma"
  E click su "Inserisci il credito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_5A_5:cognome+ +@alunno_5A_5:nome"
  E click su "Conferma"
  E click su "Inserisci il credito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_5A_6:cognome+ +@alunno_5A_6:nome"
  E click su "Conferma"
  E click su "Inserisci il credito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_5A_7:cognome+ +@alunno_5A_7:nome"
  E click su "Conferma"
  E click su "Conferma"
  Allora vedi la pagina "coordinatore_scrutinio" con parametri:
    | classe        | stato |
    | @classe_5A:id | 8     |
  E la sezione "#gs-main h2" contiene "Comunicazioni"

Scenario: terze - memorizzazione dati e passo successivo - credito minimo
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_3A:id |
  Quando click su "Inserisci il credito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_3A_2:cognome+ +@alunno_3A_2:nome"
  E click su "Conferma"
  E click su "Inserisci il credito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_3A_3:cognome+ +@alunno_3A_3:nome"
  E click su "Conferma"
  E click su "Inserisci il credito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_3A_4:cognome+ +@alunno_3A_4:nome"
  E click su "Conferma"
  E click su "Inserisci il credito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_3A_5:cognome+ +@alunno_3A_5:nome"
  E click su "Conferma"
  E click su "Inserisci il credito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_3A_6:cognome+ +@alunno_3A_6:nome"
  E click su "Conferma"
  E click su "Conferma"
  E click su "passo precedente"
  Allora la sezione "#gs-main h2" contiene "Attribuzione crediti"
  E vedi la tabella "2" non ordinata senza intestazioni:
    | Alunno | Media | Credito |
    | @alunno_3A_2:cognome+ +@alunno_3A_2:nome | 6,00 | 7  |
    | @alunno_3A_3:cognome+ +@alunno_3A_3:nome | 6,88 | 8  |
    | @alunno_3A_4:cognome+ +@alunno_3A_4:nome | 7,63 | 9  |
    | @alunno_3A_5:cognome+ +@alunno_3A_5:nome | 8,38 | 10 |
    | @alunno_3A_6:cognome+ +@alunno_3A_6:nome | 9,13 | 11 |

# Scenario: terze - memorizzazione dati e passo successivo - credito massimo
#   Data pagina attiva "coordinatore_scrutinio" con parametri:
#     | classe        |
#     | @classe_3A:id |
#   Quando click su "Inserisci il credito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_3A_2:cognome+ +@alunno_3A_2:nome"
#   E selezioni opzione "F" da checkbox "credito_creditoScolastico"
#   E selezioni opzione "I" da checkbox "credito_creditoScolastico"
#   E click su "Conferma"
#   E click su "Inserisci il credito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_3A_3:cognome+ +@alunno_3A_3:nome"
#   E selezioni opzione "P" da checkbox "credito_creditoScolastico"
#   E selezioni opzione "R" da checkbox "credito_creditoScolastico"
#   E click su "Conferma"
#   E click su "Inserisci il credito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_3A_4:cognome+ +@alunno_3A_4:nome"
#   E selezioni opzione "O" da checkbox "credito_creditoScolastico"
#   E selezioni opzione "F" da checkbox "credito_creditoScolastico"
#   E click su "Conferma"
#   E click su "Inserisci il credito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_3A_5:cognome+ +@alunno_3A_5:nome"
#   E selezioni opzione "I" da checkbox "credito_creditoScolastico"
#   E selezioni opzione "R" da checkbox "credito_creditoScolastico"
#   E click su "Conferma"
#   E click su "Inserisci il credito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_3A_6:cognome+ +@alunno_3A_6:nome"
#   E selezioni opzione "I" da checkbox "credito_creditoScolastico"
#   E selezioni opzione "P" da checkbox "credito_creditoScolastico"
#   E selezioni opzione "R" da checkbox "credito_creditoScolastico"
#   E click su "Conferma"
#   E click su "Conferma"
#   E click su "passo precedente"
#   Allora la sezione "#gs-main h2" contiene "Attribuzione crediti"
#   E vedi la tabella "2" non ordinata senza intestazioni:
#     | Alunno | Media | Credito |
#     | @alunno_3A_2:cognome+ +@alunno_3A_2:nome | 6,00 | 8  |
#     | @alunno_3A_3:cognome+ +@alunno_3A_3:nome | 6,88 | 9  |
#     | @alunno_3A_4:cognome+ +@alunno_3A_4:nome | 7,63 | 10 |
#     | @alunno_3A_5:cognome+ +@alunno_3A_5:nome | 8,38 | 11 |
#     | @alunno_3A_6:cognome+ +@alunno_3A_6:nome | 9,13 | 12 |

Scenario: quarte - memorizzazione dati e passo successivo - credito minimo
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_4A:id |
  Quando click su "Inserisci il credito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_4A_2:cognome+ +@alunno_4A_2:nome"
  E click su "Conferma"
  E click su "Inserisci il credito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_4A_3:cognome+ +@alunno_4A_3:nome"
  E click su "Conferma"
  E click su "Inserisci il credito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_4A_4:cognome+ +@alunno_4A_4:nome"
  E click su "Conferma"
  E click su "Inserisci il credito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_4A_5:cognome+ +@alunno_4A_5:nome"
  E click su "Conferma"
  E click su "Inserisci il credito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_4A_6:cognome+ +@alunno_4A_6:nome"
  E click su "Conferma"
  E click su "Conferma"
  E click su "passo precedente"
  Allora la sezione "#gs-main h2" contiene "Attribuzione crediti"
  E vedi la tabella "2" non ordinata senza intestazioni:
    | Alunno | Media | Credito anni precedenti | Credito |
    | @alunno_4A_2:cognome+ +@alunno_4A_2:nome | 6,00 | @alunno_4A_2:credito3 | 8  |
    | @alunno_4A_3:cognome+ +@alunno_4A_3:nome | 6,88 | @alunno_4A_3:credito3 | 9  |
    | @alunno_4A_4:cognome+ +@alunno_4A_4:nome | 7,63 | @alunno_4A_4:credito3 | 10 |
    | @alunno_4A_5:cognome+ +@alunno_4A_5:nome | 8,38 | @alunno_4A_5:credito3 | 11 |
    | @alunno_4A_6:cognome+ +@alunno_4A_6:nome | 9,13 | @alunno_4A_6:credito3 | 12 |

# Scenario: quarte - memorizzazione dati e passo successivo - credito massimo
#   Data pagina attiva "coordinatore_scrutinio" con parametri:
#     | classe        |
#     | @classe_4A:id |
#   Quando click su "Inserisci il credito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_4A_2:cognome+ +@alunno_4A_2:nome"
#   E selezioni opzione "I" da checkbox "credito_creditoScolastico"
#   E selezioni opzione "R" da checkbox "credito_creditoScolastico"
#   E click su "Conferma"
#   E click su "Inserisci il credito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_4A_3:cognome+ +@alunno_4A_3:nome"
#   E selezioni opzione "F" da checkbox "credito_creditoScolastico"
#   E selezioni opzione "I" da checkbox "credito_creditoScolastico"
#   E click su "Conferma"
#   E click su "Inserisci il credito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_4A_4:cognome+ +@alunno_4A_4:nome"
#   E selezioni opzione "P" da checkbox "credito_creditoScolastico"
#   E selezioni opzione "R" da checkbox "credito_creditoScolastico"
#   E click su "Conferma"
#   E click su "Inserisci il credito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_4A_5:cognome+ +@alunno_4A_5:nome"
#   E selezioni opzione "O" da checkbox "credito_creditoScolastico"
#   E selezioni opzione "F" da checkbox "credito_creditoScolastico"
#   E click su "Conferma"
#   E click su "Inserisci il credito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_4A_6:cognome+ +@alunno_4A_6:nome"
#   E selezioni opzione "I" da checkbox "credito_creditoScolastico"
#   E selezioni opzione "P" da checkbox "credito_creditoScolastico"
#   E selezioni opzione "R" da checkbox "credito_creditoScolastico"
#   E click su "Conferma"
#   E click su "Conferma"
#   E click su "passo precedente"
#   Allora la sezione "#gs-main h2" contiene "Attribuzione crediti"
#   E vedi la tabella "2" non ordinata senza intestazioni:
#     | Alunno | Media | Credito anni precedenti | Credito |
#     | @alunno_4A_2:cognome+ +@alunno_4A_2:nome | 6,00 | @alunno_4A_2:credito3 | 9  |
#     | @alunno_4A_3:cognome+ +@alunno_4A_3:nome | 6,88 | @alunno_4A_3:credito3 | 10 |
#     | @alunno_4A_4:cognome+ +@alunno_4A_4:nome | 7,63 | @alunno_4A_4:credito3 | 11 |
#     | @alunno_4A_5:cognome+ +@alunno_4A_5:nome | 8,38 | @alunno_4A_5:credito3 | 12 |
#     | @alunno_4A_6:cognome+ +@alunno_4A_6:nome | 9,13 | @alunno_4A_6:credito3 | 13 |

Scenario: quinte - memorizzazione dati e passo successivo - credito minimo
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe        |
    | @classe_5A:id |
  Quando click su "Inserisci il credito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_5A_2:cognome+ +@alunno_5A_2:nome"
  E click su "Conferma"
  E click su "Inserisci il credito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_5A_3:cognome+ +@alunno_5A_3:nome"
  E click su "Conferma"
  E click su "Inserisci il credito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_5A_4:cognome+ +@alunno_5A_4:nome"
  E click su "Conferma"
  E click su "Inserisci il credito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_5A_5:cognome+ +@alunno_5A_5:nome"
  E click su "Conferma"
  E click su "Inserisci il credito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_5A_6:cognome+ +@alunno_5A_6:nome"
  E click su "Conferma"
  E click su "Inserisci il credito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_5A_7:cognome+ +@alunno_5A_7:nome"
  E click su "Conferma"
  E click su "Conferma"
  E click su "passo precedente"
  Allora la sezione "#gs-main h2" contiene "Attribuzione crediti"
  E vedi la tabella "2" non ordinata senza intestazioni:
    | Alunno | Media | Credito anni precedenti | Credito |
    | @alunno_5A_2:cognome+ +@alunno_5A_2:nome | 6,00 | #sum(@alunno_5A_2:credito3,@alunno_5A_2:credito4) | 9  |
    | @alunno_5A_3:cognome+ +@alunno_5A_3:nome | 6,88 | #sum(@alunno_5A_3:credito3,@alunno_5A_3:credito4) | 10 |
    | @alunno_5A_4:cognome+ +@alunno_5A_4:nome | 7,63 | #sum(@alunno_5A_4:credito3,@alunno_5A_4:credito4) | 11 |
    | @alunno_5A_5:cognome+ +@alunno_5A_5:nome | 8,38 | #sum(@alunno_5A_5:credito3,@alunno_5A_5:credito4) | 13 |
    | @alunno_5A_6:cognome+ +@alunno_5A_6:nome | 9,13 | #sum(@alunno_5A_6:credito3,@alunno_5A_6:credito4) | 14 |
    | @alunno_5A_7:cognome+ +@alunno_5A_7:nome | 5,88 | #sum(@alunno_5A_7:credito3,@alunno_5A_7:credito4) | 7  |

# Scenario: quinte - memorizzazione dati e passo successivo - credito massimo
#   Data pagina attiva "coordinatore_scrutinio" con parametri:
#     | classe        |
#     | @classe_5A:id |
#   Quando click su "Inserisci il credito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_5A_2:cognome+ +@alunno_5A_2:nome"
#   E selezioni opzione "I" da checkbox "credito_creditoScolastico"
#   E selezioni opzione "R" da checkbox "credito_creditoScolastico"
#   E click su "Conferma"
#   E click su "Inserisci il credito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_5A_3:cognome+ +@alunno_5A_3:nome"
#   E selezioni opzione "F" da checkbox "credito_creditoScolastico"
#   E selezioni opzione "I" da checkbox "credito_creditoScolastico"
#   E click su "Conferma"
#   E click su "Inserisci il credito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_5A_4:cognome+ +@alunno_5A_4:nome"
#   E selezioni opzione "O" da checkbox "credito_creditoScolastico"
#   E selezioni opzione "F" da checkbox "credito_creditoScolastico"
#   E click su "Conferma"
#   E click su "Inserisci il credito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_5A_5:cognome+ +@alunno_5A_5:nome"
#   E selezioni opzione "P" da checkbox "credito_creditoScolastico"
#   E selezioni opzione "R" da checkbox "credito_creditoScolastico"
#   E click su "Conferma"
#   E click su "Inserisci il credito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_5A_6:cognome+ +@alunno_5A_6:nome"
#   E selezioni opzione "I" da checkbox "credito_creditoScolastico"
#   E selezioni opzione "P" da checkbox "credito_creditoScolastico"
#   E selezioni opzione "R" da checkbox "credito_creditoScolastico"
#   E click su "Conferma"
#   E click su "Inserisci il credito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_5A_7:cognome+ +@alunno_5A_7:nome"
#   E click su "Conferma"
#   E click su "Conferma"
#   E click su "passo precedente"
#   Allora la sezione "#gs-main h2" contiene "Attribuzione crediti"
#   E vedi la tabella "2" non ordinata senza intestazioni:
#     | Alunno | Media | Credito anni precedenti | Credito |
#     | @alunno_5A_2:cognome+ +@alunno_5A_2:nome | 6,00 | #sum(@alunno_5A_2:credito3,@alunno_5A_2:credito4) | 10 |
#     | @alunno_5A_3:cognome+ +@alunno_5A_3:nome | 6,88 | #sum(@alunno_5A_3:credito3,@alunno_5A_3:credito4) | 11 |
#     | @alunno_5A_4:cognome+ +@alunno_5A_4:nome | 7,63 | #sum(@alunno_5A_4:credito3,@alunno_5A_4:credito4) | 12 |
#     | @alunno_5A_5:cognome+ +@alunno_5A_5:nome | 8,38 | #sum(@alunno_5A_5:credito3,@alunno_5A_5:credito4) | 14 |
#     | @alunno_5A_6:cognome+ +@alunno_5A_6:nome | 9,13 | #sum(@alunno_5A_6:credito3,@alunno_5A_6:credito4) | 15 |
#     | @alunno_5A_7:cognome+ +@alunno_5A_7:nome | 5,88 | #sum(@alunno_5A_7:credito3,@alunno_5A_7:credito4) | 7  |


###############################################################################
# Bisogna controllare visualizzazione con la classe articolata

Scenario: classe articolata - visualizzazione pagina
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe           |
    | @classe_3CAMB:id |
  Allora la sezione "#gs-main h2" contiene "Attribuzione crediti"
  E vedi nella tabella "1" le colonne:
    | Alunno | Media | Credito |
  E vedi la tabella "2" non ordinata senza intestazioni:
    | Alunno | Media | Credito |
    | @alunno_3CAMB_2:cognome+ +@alunno_3CAMB_2:nome | 8,13 | |

Scenario: classe articolata - visualizzazione riquadro inserimento credito
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe           |
    | @classe_3CAMB:id |
  Quando click su "Inserisci il credito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_3CAMB_2:cognome+ +@alunno_3CAMB_2:nome"
  Allora la sezione "#gs-main #gs-modal-remote .modal-title.gs-h3" contiene "@alunno_3CAMB_2:cognome+ +@alunno_3CAMB_2:nome"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li:nth-child(1) div" contiene "/Religione.*Sufficiente/"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li:nth-child(2) div" contiene "/Italiano 10/"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li:nth-child(3) div" contiene "/Storia 9/"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li:nth-child(4) div" contiene "/Inglese 8/"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li:nth-child(5) div" contiene "/Matematica 7/"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li:nth-child(6) div" contiene "/Fisica 7/"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li:nth-child(7) div" contiene "/Sc\. motorie 7/"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li:nth-child(8) div" contiene "/Ed\. civica 8/"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li:nth-child(9) div" contiene "/Condotta 9/"
  E la sezione "#gs-main #gs-modal-remote #gs-form-collection li:nth-child(10) div" contiene "/Media 8,13 Assenze 9,47%/"
  E la sezione "#gs-main #gs-modal-remote .modal-body .form-group:nth-child(2)" contiene "/Credito scolastico Frequenza assidua Interesse e impegno .* partecipazione alla FSL .* partecipazione alle lezioni della Religione .* Organi Collegiali/"
  E la sezione "#gs-main #gs-modal-remote .modal-body .form-group:nth-child(3)" contiene "/intervallo: 10 - 11/"

Scenario: classe articolata - visualizzazione pagina con dati mancanti
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe           |
    | @classe_3CAMB:id |
  Quando click su "Conferma"
  Allora la sezione "#gs-modal-error .alert-danger" contiene "?@alunno_3CAMB_2:cognome+ +@alunno_3CAMB_2:nome"

Scenario: classe articolata - visualizzazione passo precedente
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe           |
    | @classe_3CAMB:id |
  Quando click su "passo precedente"
  Allora la sezione "#gs-main h2" contiene "Esito dello scrutinio"

Scenario: classe articolata - visualizzazione passo successivo
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe           |
    | @classe_3CAMB:id |
  Quando click su "Inserisci il credito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_3CAMB_2:cognome+ +@alunno_3CAMB_2:nome"
  E click su "Conferma"
  E click su "Conferma"
  Allora vedi la pagina "coordinatore_scrutinio" con parametri:
    | classe           | stato |
    | @classe_3CAMB:id | 8     |
  E la sezione "#gs-main h2" contiene "Comunicazioni"

Scenario: classe articolata - memorizzazione dati e passo successivo
  Data pagina attiva "coordinatore_scrutinio" con parametri:
    | classe           |
    | @classe_3CAMB:id |
  Quando click su "Inserisci il credito" in sezione "#gs-main form table tbody tr" che contiene "@alunno_3CAMB_2:cognome+ +@alunno_3CAMB_2:nome"
  E selezioni opzione "P" da checkbox "credito_creditoScolastico"
  E click su "Conferma"
  E click su "Conferma"
  E click su "passo precedente"
  Allora la sezione "#gs-main h2" contiene "Attribuzione crediti"
  E vedi la tabella "2" non ordinata senza intestazioni:
    | Alunno | Media | Credito |
    | @alunno_3CAMB_2:cognome+ +@alunno_3CAMB_2:nome | 8,13 | 10 |
