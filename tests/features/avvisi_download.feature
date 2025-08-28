# language: it

Funzionalità:
  Per scaricare un allegato inseritodi un avviso
  Come utente
  Bisogna controllare prerequisiti di lettura di avviso
  Bisogna poter scaricare un allegato esistente
  Bisogna controllare accesso a pagina
  Utilizzando "_avvisiFixtures.yml"


################################################################################
# Bisogna controllare prerequisiti per lettura di avviso

Scenario: visualizza errore per scaricamento allegato di avviso non esistente
  Dato login utente "@docente_curricolare_1:username"
  Quando vai alla pagina "avvisi_download" con parametri:
    | avviso |
    | 123456 |
  Allora vedi errore pagina "404"

Scenario: visualizza errore per scaricamento allegato non esistente di avviso
  Dato login utente "@docente_curricolare_1:username"
  Quando vai alla pagina "avvisi_download" con parametri:
    | avviso       |
    | @avviso_C:id |
  Allora vedi errore pagina "404"

Scenario: visualizza errore per scaricamento allegato errato
  Dato login utente "@alunno_4A_1:username"
  Quando vai alla pagina "avvisi_download" con parametri:
    | avviso                | allegato |
    | @avviso_C_allegato:id | 123      |
  Allora vedi errore pagina "404"

Scenario: visualizza errore per scaricamento allegato senza permesso di lettura
  Dato login utente "@alunno_1A_1:username"
  Quando vai alla pagina "avvisi_download" con parametri:
    | avviso                |
    | @avviso_C_allegato:id |
  Allora vedi errore pagina "404"


################################################################################
# Bisogna poter scaricare un allegato esistente

Scenario: scarica da URL l'allegato di un avviso
  Dato login utente "@alunno_4A_1:username"
  E copia file "tests/data/documento-pdf.pdf" in "FILES/upload/avvisi/documento-pdf.pdf"
  Quando vai alla pagina "avvisi_download" con parametri:
    | avviso                | allegato | tipo |
    | @avviso_C_allegato:id | 0        | D    |
  Allora file scaricato con nome "Allegato-1.pdf" e dimensione "61514"

Scenario: scarica l'allegato di un avviso e segna la lettura dell'avviso
  Dato login utente "@alunno_4A_1:username"
  E copia file "tests/data/documento-pdf.pdf" in "FILES/upload/avvisi/documento-pdf.pdf"
  Quando vai alla pagina "avvisi_download" con parametri:
    | avviso                | allegato | tipo |
    | @avviso_C_allegato:id | 0        | D    |
  E vai alla pagina "avvisi_bacheca"
  Allora vedi la tabella non ordinata:
    | stato | data                          | sede                       | oggetto                   | azione     |
    | LETTO | #dat(@avviso_C_allegato:data) | @avviso_C_allegato:sedi[0] | @avviso_C_allegato:titolo | Visualizza |

Scenario: scarica da gestione l'allegato di un avviso
  Dato login utente "@staff_4:username"
  E pagina attiva "avvisi_gestione"
  Quando selezioni opzione "@staff_4:cognome" da lista "avviso_filtro_autore"
  E inserisci "@avviso_C_allegato:titolo" nel campo "oggetto"
  E premi pulsante "Filtra"
  E premi pulsante "Visualizza"
  E copia file "tests/data/documento-pdf.pdf" in "FILES/upload/avvisi/documento-pdf.pdf"
  E click su "Allegato 1" e scarica file con nome "Allegato-1.pdf" e dimensione "61514"

Scenario: scarica da bacheca l'allegato di un avviso
  Dato login utente "@alunno_4A_1:username"
  E pagina attiva "avvisi_bacheca"
  Quando premi pulsante "Visualizza"
  E copia file "tests/data/documento-pdf.pdf" in "FILES/upload/avvisi/documento-pdf.pdf"
  E click su "Scarica l'allegato" e scarica file con nome "Allegato-1.pdf" e dimensione "61514"
  E click su "Visualizza l'allegato" e scarica file con nome "Allegato-1.pdf" e dimensione "61514"

Scenario: scarica dal registro di classe l'allegato di un avviso
  Dato login utente "@docente_curricolare_1:username"
  E pagina attiva "lezioni_classe"
  Quando premi pulsante "4ª A"
  E vai alla pagina "lezioni_registro_firme" con parametri:
    | cattedra | classe        | data       | vista |
    | 0        | @classe_4A:id | 2023-04-01 | G     |
  E premi pulsante "Avviso"
  E copia file "tests/data/documento-pdf.pdf" in "FILES/upload/avvisi/documento-pdf.pdf"
  E click su "Scarica l'allegato" e scarica file con nome "Allegato-1.pdf" e dimensione "61514"
  E click su "Visualizza l'allegato" e scarica file con nome "Allegato-1.pdf" e dimensione "61514"


################################################################################
# Bisogna controllare accesso a pagina

Scenario: accesso pagina scaricamento senza utente
  Quando vai alla pagina "avvisi_download" con parametri:
    | avviso                | allegato | tipo |
    | @avviso_C_allegato:id | 0        | D    |
  Allora vedi la pagina "login_form"
