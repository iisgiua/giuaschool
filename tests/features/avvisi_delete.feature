# language: it

Funzionalità: Cancellazione di un avviso
  Per cancellare un avviso esistente
  Come utente docente
  Bisogna controllare prerequisiti per cancellazione di avviso
  Bisogna poter cancellare un avviso esistente
  Bisogna controllare accesso a pagina
  Utilizzando "_avvisiFixtures.yml"


################################################################################
# Bisogna controllare prerequisiti per cancellazione di avviso

Scenario: visualizza errore per cancellazione di avviso non esistente
  Dato login utente "@staff_4:username"
  Quando vai alla pagina "avvisi_delete" con parametri:
    | avviso |
    | 123456 |
  Allora vedi errore pagina "404"

Scenario: visualizza errore per cancellazione di avviso senza permessi
  Dato login utente "@docente_curricolare_1:username"
  Quando vai alla pagina "avvisi_delete" con parametri:
    | avviso       |
    | @avviso_E:id |
  Allora vedi errore pagina "404"

Scenario: visualizza errore per cancellazione di avviso con annotazione senza permessi
  Dato login utente "@staff_4:username"
  Quando vai alla pagina "avvisi_delete" con parametri:
    | avviso                      |
    | @avviso_non_modificabile:id |
  Allora vedi errore pagina "404"


################################################################################
# Bisogna poter cancellare un avviso esistente

Scenario: cancella avviso direttamente da URL
  Dato login utente "@staff_4:username"
  Quando vai alla pagina "avvisi_delete" con parametri:
    | avviso       |
    | @avviso_C:id |
  Allora vedi la pagina "avvisi_gestione"

Schema dello scenario: cancella avviso da gestione
  Dato login utente "@staff_4:username"
  E pagina attiva "avvisi_gestione"
  Quando selezioni opzione "<tipo>" da lista "avviso_filtro_tipo"
  E click su "Cancella" in sezione "#gs-main table tbody tr" che contiene "<titolo>"
  E click su "Continua"
  Allora vedi la pagina "avvisi_gestione"
  E la sezione "#gs-main table tbody" non contiene "<titolo>"
  Esempi:
    | tipo | titolo           |
    | C    | @avviso_C:titolo |
    | E    | @avviso_E:titolo |
    | U    | @avviso_U:titolo |
    | A    | @avviso_A:titolo |
    | I    | @avviso_I:titolo |

Scenario: cancella avviso da coordinatore
  Dato login utente "@staff_4:username"
  E pagina attiva "coordinatore_classe"
  Quando click su "2ª A"
  E vai alla pagina "avvisi_coordinatore"
  E click su "Cancella" in sezione "#gs-main table tbody tr" che contiene "@avviso_O:titolo"
  E click su "Continua"
  Allora vedi la pagina "avvisi_coordinatore"
  E la sezione "#gs-main table tbody" non contiene "@avviso_O:titolo"

Scenario: cancella avviso verifica da agenda
  Dato login utente "@docente_curricolare_1:username"
  E pagina attiva "avvisi_agenda" con parametri:
    | mese    |
    | 2023-03 |
  Quando click su "Verifiche"
  E click su "Cancella"
  E click su "Continua"
  Allora vedi la pagina "avvisi_agenda"
  E la sezione "#gs-main table tbody" contiene "1 Compiti 2"

Scenario: cancella avviso compito da agenda
  Dato login utente "@docente_curricolare_1:username"
  E pagina attiva "avvisi_agenda" con parametri:
    | mese    |
    | 2023-03 |
  Quando click su "Compiti"
  E click su "Cancella"
  E click su "Continua"
  Allora vedi la pagina "avvisi_agenda"
  E la sezione "#gs-main table tbody" contiene "1 Verifiche 2"

Scenario: cancella avviso con allegato
  Dato login utente "@staff_4:username"
  E copia file "tests/data/documento-pdf.pdf" in "FILES/upload/avvisi/documento-pdf.pdf"
  Quando vai alla pagina "avvisi_delete" con parametri:
    | avviso                |
    | @avviso_C_allegato:id |
  Allora vedi la pagina "avvisi_gestione"
  E non vedi file "upload/avvisi/documento-pdf.pdf"


################################################################################
# Bisogna controllare accesso a pagina

Scenario: accesso pagina cancellazione senza utente
  Quando vai alla pagina "avvisi_delete" con parametri:
    | avviso       |
    | @avviso_C:id |
  Allora vedi la pagina "login_form"

Schema dello scenario: accesso pagina cancellazione con altri utenti
  Dato login utente con ruolo esatto <ruolo>
  Quando vai alla pagina "avvisi_delete" con parametri:
    | avviso       |
    | @avviso_C:id |
  Allora vedi errore pagina "403"
  Esempi:
    | ruolo          |
    | Amministratore |
    | Ata            |
    | Genitore       |
    | Alunno         |
    | Utente         |
