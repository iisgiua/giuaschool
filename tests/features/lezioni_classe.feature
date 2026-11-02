# language: it

Funzionalità: Gestione della scelta delle classi
  Per selezionare una classe
  Come utente docente
  Bisogna visualizzare le classi disponibili per le sostituzioni
  Bisogna visualizzare il registro delle cattedre e classi disponibili
  Utilizzando "_lezioniFixtures.yml"


################################################################################
# Bisogna visualizzare le classi disponibili per le sostituzioni

Scenario: Visualizzazione classi per docente senza cattedra
  Dato login utente "@docente_nocattedra_1:username"
  Quando pagina attiva "lezioni_registro_firme"
  Allora la sezione "#gs-main .alert-warning" contiene "Devi prima scegliere la classe o la materia"
  E la lista "gs-selettore-classe-sede" contiene l'opzione "Grossetto"
  E la lista "gs-selettore-classe-sede" contiene l'opzione "Bergamo"
  E la lista "gs-selettore-classe" contiene l'opzione "1º A"
  E la lista "gs-selettore-classe" contiene l'opzione "3º C"
  E la lista "gs-selettore-classe" contiene l'opzione "3º C-AMB"
  E la lista "gs-selettore-classe" contiene l'opzione "3º C-CHI"
  E la lista "gs-selettore-classe" contiene l'opzione "1º B"

Scenario: Visualizzazione classi per docente con cattedra
  Dato login utente "@docente_nocattedra_2:username"
  Quando pagina attiva "lezioni_registro_firme"
  Allora la lista "gs-selettore-classe" contiene l'opzione "1º A"
  E la lista "gs-selettore-classe" contiene l'opzione "3º C"
  E la lista "gs-selettore-classe" contiene l'opzione "3º C-CHI"


################################################################################
# Bisogna visualizzare il registro delle cattedre e classi disponibili

Scenario: Visualizza registro sostituzione classe
  Dato login utente "@docente_nocattedra_1:username"
  Quando pagina attiva "lezioni_registro_firme"
  E selezioni opzione "1º A" da lista "gs-selettore-classe"
  Allora la sezione "#gs-main h1" contiene "Registro della classe 1ª A"
  E la sezione "#gs-main h2" contiene "Sostituzione"

Scenario: Visualizza registro cattedra classe
  Dato login utente "@docente_nocattedra_2:username"
  Quando pagina attiva "lezioni_registro_firme" con parametri:
    | cattedra            |
    | @t_cattedra_1_1A:id |
  Allora la sezione "#gs-main h1" contiene "Registro della classe 1ª A"
  E la sezione "#gs-main h2" contiene "Italiano"

Scenario: Visualizza registro cattedra gruppo classe
  Dato login utente "@docente_nocattedra_2:username"
  Quando pagina attiva "lezioni_registro_firme" con parametri:
    | cattedra               |
    | @t_cattedra_2_3CCHI:id |
  Allora la sezione "#gs-main h1" contiene "Registro della classe 3ª C-CHI"
  E la sezione "#gs-main h2" contiene "Storia"
