# language: it

Funzionalità: Procedura richiesta certificato
  Per gestire la procedura di richiesta certificato di rientro a scuola
  Come utente staff
  Bisogna segnare alunno con richiesta di certificato
    - mettere avviso per genitore/alunno:
      "alunno potrà rientrare solo in presenza del certificato medico"
    - mettere avviso per coordinatore/docenti:
      "i docenti della prima ora dovranno accertarsi che l'alunno X mostri il certificato medico"
  Bisogna visualizzare alunni con richiesta di certificato
  Bisogna togliere segnalazione di richiesta certificato
    - confermare presenza certificato medico e inserire annotazione:
      "alunno ammesso in classe con certificato medico"


  Contesto:
  	Dato login utente con ruolo "Staff"


  Scenario: Visualizza alunni con richiesta di certificato per staff di intera scuola
    Data modifica utente attuale con parametri:
      | nomeParam   | valoreParam   |
      | sede        | null          |
    Date istanze di tipo "Alunno":
      | id        | richiestaCertificato  |
      | $1        | si                    |
      | $2        | si                    |
      | $3        | no                    |
    E pagina attiva "staff_studenti_certificato"
    Allora vedi nella tabella le colonne:
      | alunno | classe | sede | azione |
    E vedi "2" righe nella tabella
    E vedi in una riga della tabella i dati:
      | alunno          | classe        | sede            |
      | $1:cognome,nome | $1:classe     | $1:classe.sede  |
    E vedi in una riga della tabella i dati:
      | alunno          | classe        | sede            |
      | $2:cognome,nome | $2:classe     | $2:classe.sede  |

  Scenario: Visualizza alunni con richiesta di certificato per staff di una sede
    Date istanze di tipo "Sede":
      | id        |
      | $s1       |
      | $s2       |
    Date istanze di tipo "Classe":
      | id        | sede  |
      | $c1       | $s1   |
      | $c2       | $s2   |
    Date istanze di tipo "Alunno":
      | id        | richiestaCertificato  | classe |
      | $1        | si                    | $c1    |
      | $2        | si                    | $c2    |
      | $3        | no                    | $c1    |
    Data modifica utente attuale con parametri:
      | nomeParam   | valoreParam   |
      | sede        | $s1          |
    E pagina attiva "staff_studenti_certificato"
    Allora vedi nella tabella le colonne:
      | alunno | classe | sede | azione |
    E vedi "1" riga nella tabella
    E vedi nella riga "1" della tabella i dati:
      | alunno          | classe        | sede            |
      | $1:cognome,nome | $1:classe     | $1:classe.sede  |







  #-- Scenario: Chiudi procedura covid
    #-- Dato utente staff
    #-- Quando va a pagina "staff/studenti/covid"
    #-- E alunno è presente in lista alunni con procedura covid attiva
    #-- E clicca su pulsante "certificato medico acquisito" di alunno
    #-- Allora inserisce annotazione su controllo certificato medico
    #-- E chiude procedura covid per alunno

  #-- Scenario: Imposta alunno con procedura covid attiva
    #-- Dato utente staff
    #-- Quando va a pagina "staff/studenti/covid"
    #-- E clicca su pulsante "aggiungi alunno covid"
    #-- Allora va a pagina "staff/studenti/covid/aggiungi"

  #-- Scenario: Imposta alunno con procedura covid attiva da pagina aggiungi
    #-- Dato utente staff
    #-- Quando va a pagina "staff/studenti/covid/aggiungi"
    #-- E sceglie alunno
    #-- E inserisce avviso per docenti/coordinatore
    #-- E inserisce ora uscita
    #-- Allora imposta procedura covid per alunno attiva
    #-- E inserisce avviso per genitori/alunno
