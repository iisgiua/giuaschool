# language: it

Funzionalità: procedura covid lato staff
  Per gestire la procedura alunno uscito per sintomi riconducibili al Covid
  Come utente staff
  Bisogna segnare alunno con procedura covid attiva
    mettere avviso per genitori/alunno
      alunno potrà rientrare solo in presenza del certificato medico
    mettere avviso per coordinatore/docenti
      i docenti della prima ora dovranno accertarsi che l'alunno X mostri il certificato medico
  Bisogna visualizzare alunni con procedura covid attiva
  Bisogna chiudere procedura covid
    confermare presenza certificato medico e inserire annotazione
      alunno ammesso in classe con certificato medico

  Scenario: Test1
  	Dato utente staff
    Quando va a pagina "staff_avvisi_attivita"

  #-- Contesto:
  	#-- Dato utente staff


  #-- Scenario: Visualizza alunni con procedura covid attiva
    #-- Quando va a pagina "/staff/studenti/covid"
    #-- Allora visualizza alunni con procedura covid attiva

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
    #-- E inserisce ora uscita
    #-- Allora imposta procedura covid per alunno attiva
    #-- E inserisce avviso per genitori/alunno
    #-- E inserisce avviso per docenti/coordinatore
