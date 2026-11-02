# language: it

Funzionalità: Navigazione dei voti di sostegno
  Per verificare i collegamenti tra dettagli e medie dei voti di sostegno
  Come utente docente di sostegno
  Bisogna controllare che la cattedra sia mantenuta nei collegamenti
  Bisogna controllare il selettore della classe in assenza di cattedra
  Utilizzando "_cattedreFixtures.yml"


################################################################################
# Bisogna controllare che la cattedra sia mantenuta nei collegamenti

Scenario: Il link Medie voti mantiene la cattedra di sostegno
  Dato login utente "@docente_sostegno_1:username"
  Quando vai alla pagina "lezioni_voti_sostegno" con parametri:
    | cattedra          | materia |
    | @cattedra_1A_9:id | 0       |
  Allora vedi la pagina "lezioni_voti_sostegno" con parametri:
    | cattedra          | materia |
    | @cattedra_1A_9:id | 0       |
  E premi pulsante "Medie voti"
  Allora vedi la pagina "lezioni_voti_quadro_sostegno" con parametri:
    | cattedra          |
    | @cattedra_1A_9:id |

Scenario: I link dei periodi e Dettagli voti mantengono la cattedra di sostegno
  Dato login utente "@docente_sostegno_1:username"
  Quando vai alla pagina "lezioni_voti_quadro_sostegno" con parametri:
    | cattedra          | periodo |
    | @cattedra_1A_9:id | 2       |
  Allora vedi la pagina "lezioni_voti_quadro_sostegno" con parametri:
    | cattedra          | periodo |
    | @cattedra_1A_9:id | 2       |
  E la sezione "#gs-main #gs-dropdown-menu" contiene "Secondo Quadrimestre"
  E premi pulsante "Dettagli voti"
  Allora vedi la pagina "lezioni_voti_sostegno" con parametri:
    | cattedra          |
    | @cattedra_1A_9:id |


################################################################################
# Bisogna controllare il selettore della classe in assenza di cattedra

Scenario: Senza cattedra viene mostrato il selettore della classe
  Dato login utente "@docente_sostegno_1:username"
  Quando vai alla pagina "lezioni_voti_quadro_sostegno"
  Allora vedi la pagina "lezioni_voti_quadro_sostegno"
  E la sezione "#gs-main" contiene "Devi prima scegliere la classe o la materia"
