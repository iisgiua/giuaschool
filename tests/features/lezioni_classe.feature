# language: it

Funzionalità: Gestione della scelta delle classi
  Per selezionare una classe
  Come utente docente
  Bisogna visualizzare le cattedre e le classi disponibili
  Bisogna visualizzare il registro delle cattedre e classi disponibili
  Utilizzando "_lezioniFixtures.yml"


################################################################################
# Bisogna visualizzare le cattedre e le classi disponibili

Scenario: Visualizzazione classi per docente senza cattedra
  Dato login utente "@docente_nocattedra_1:username"
  Quando pagina attiva "lezioni_classe"
  Allora vedi "0" righe nella tabella "1"
  E vedi la tabella "2":
    | sede      | sezione | classe                           |
    | Grossetto | A       | 1ª A 2ª A 3ª A 4ª A 5ª A         |
    | Grossetto | C       | 1ª C 2ª C 3ª C 3ª C-AMB 3ª C-CHI |
    | Bergamo   | B       | 1ª B 2ª B 3ª B 4ª B 5ª B         |

Scenario: Visualizzazione classi per docente con cattedra
  Dato login utente "@docente_nocattedra_2:username"
  Quando pagina attiva "lezioni_classe"
  Allora vedi la tabella "1":
    | sede      | indirizzo                    | classe e materia               |
    | Grossetto | Ist. Tecn. Inf. Telecom.     | 1ª A - Inglese 1ª A - Italiano |
    | Grossetto | Ist. Tecn. Art. Chimica Mat. | 3ª C - Italiano                |
    | Grossetto | Ist. Tecn. Art. Chimica Mat. | 3ª C-CHI - Inglese             |
    | Bergamo   | Liceo scienze Applicate      | 1ª B - Italiano                |
  E vedi la tabella "2":
    | sede      | sezione | classe                           |
    | Grossetto | A       | 1ª A 2ª A 3ª A 4ª A 5ª A         |
    | Grossetto | C       | 1ª C 2ª C 3ª C 3ª C-AMB 3ª C-CHI |
    | Bergamo   | B       | 1ª B 2ª B 3ª B 4ª B 5ª B         |


################################################################################
# Bisogna visualizzare il registro delle cattedre e classi disponibili

Scenario: Visualizza registro supplenza classe
  Dato login utente "@docente_nocattedra_1:username"
  Quando pagina attiva "lezioni_classe"
  E premi pulsante "1ª A"
  Allora vedi la pagina "lezioni_registro_firme" con parametri:
    | cattedra | classe        |
    | 0        | @classe_1A:id |
  E la sezione "#gs-main h1" contiene "Registro della classe 1ª A"
  E la sezione "#gs-main h2" contiene "Supplenza"

Scenario: Visualizza registro cattedra classe
  Dato login utente "@docente_nocattedra_2:username"
  Quando pagina attiva "lezioni_classe"
  E premi pulsante "1ª A - Italiano"
  Allora vedi la pagina "lezioni_registro_firme" con parametri:
    | cattedra            |
    | @t_cattedra_1_1A:id |
  E la sezione "#gs-main h1" contiene "Registro della classe 1ª A"
  E la sezione "#gs-main h2" contiene "Italiano"

Scenario: Visualizza registro cattedra gruppo classe
  Dato login utente "@docente_nocattedra_2:username"
  Quando pagina attiva "lezioni_classe"
  E premi pulsante "3ª C-CHI - Inglese"
  Allora vedi la pagina "lezioni_registro_firme" con parametri:
    | cattedra               |
    | @t_cattedra_2_3CCHI:id |
  E la sezione "#gs-main h1" contiene "Registro della classe 3ª C-CHI"
  E la sezione "#gs-main h2" contiene "Inglese"
