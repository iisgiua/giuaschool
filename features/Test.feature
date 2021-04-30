# language: it

Funzionalità: Procedura di test Behat
  Per provare funzionalità di test Behat
  Come utente anonimo o staff
  Bisogna verificare la corrispondenza della pagina visualizzata

  Scenario: Test1 - utente anonimo
    Data pagina attiva "login_form"
    Quando vai al link "Privacy"
    Allora vedi pagina "info_privacy"

  Scenario: Test2 - utente staff
    Dato login utente con ruolo esatto "staff"
    E pagina attiva "agenda_eventi" con parametri:
      |nomeParam  |valoreParam|
      |mese       |2021-02   |
    Quando vai al link "Privacy"
    Allora vedi pagina "info_privacy"
