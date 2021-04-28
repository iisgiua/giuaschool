<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2021 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2021
 */


namespace App\Tests\Behat;

use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Exception\ExpectationException;


/**
 * Contesto con interazione con il browser
 */
class BrowserContext extends BaseContext {

  //==================== METODI DELLA CLASSE ====================

  /**
   * Trasforma una tabella in un array associativo per i parametri
   *
   * @Transform table:nomeParam,valoreParam
   */
  public function trasformaArray(TableNode $urlParams): array {
    $params = array();
    foreach ($urlParams->getHash() as $row) {
      $params[$row['nomeParam']] = $row['valoreParam'];
    }
    return $params;
  }

  /**
   * Va alla pagina indicata (anche con parametri)
   *
   * @Given pagina attiva :pagina
   * @Given pagina attiva :pagina con parametri:
   */
  public function paginaAttiva($pagina, $parametri=[]): void {
    $url = $this->getMinkParameter('base_url').$this->router->generate($pagina, $parametri);
    $this->session->visit($url);
    $this->waitForPage();
    $this->assertPageStatus(200);
    $this->assertPageUrl($url);
  }

  /**
   * Esegue il login dell'utente indicato
   *
   * @Given login utente :username
   * @Given login utente :username con :password
   */
  public function loginUtente($username, $password=null): void {
    $this->paginaAttiva('login_form');
    $this->session->getPage()->fillField('username', $username);
    $this->session->getPage()->fillField('password', $password ? $password : $username);
    $this->session->getPage()->pressButton('login');
    $this->waitForPage();
    $this->assertPageStatus(200);
    $this->assertPageUrl($this->getMinkParameter('base_url').$this->router->generate('login_home'));
  }

  /**
   * Esegue il login di un utente a caso del tipo indicato (comprese sottoclassi)
   *
   * @Given login utente con ruolo :ruolo
   */
  public function loginUtenteConRuolo($ruolo): void {
    $class_name = ucfirst(strtolower($ruolo));
    $utente = $this->faker->randomElement($this->em->getRepository('App:'.$class_name)->findBy([]));
    $this->assertNotEmpty($utente);
    $this->loginUtente($utente->getUsername());
  }

  /**
   * Esegue il login di un utente a caso del tipo esatto indicato (escluse sottoclassi)
   *
   * @Given login utente con ruolo esatto :ruolo
   */
  public function loginUtenteConRuoloEsatto($ruolo): void {
    $class_name = ucfirst(strtolower($ruolo));
    do {
      $utente = $this->faker->randomElement($this->em->getRepository('App:'.$class_name)->findBy([]));
      $this->assertNotEmpty($utente);
    } while (get_class($utente) != 'App\\Entity\\'.$class_name);
    $this->loginUtente($utente->getUsername());
  }

  /**
   * Ricarica la pagina corrente
   *
   * @When ricarichi la pagina
   * @When ricarichi la pagina dal browser
   */
  public function ricarichiLaPagina(): void {
    $this->session->reload();
    $this->waitForPage();
  }

  /**
   * Torna alla pagina precedente nella cronologia
   *
   * @When vai alla pagina precedente
   * @When vai alla pagina precedente dal browser
   */
  public function vaiAllaPaginaPrecedente(): void {
    $this->session->back();
    $this->waitForPage();
  }

  /**
   * Va alla pagina successiva nella cronologia
   *
   * @When vai alla pagina successiva
   * @When vai alla pagina successiva dal browser
   */
  public function vaiAllaPaginaSuccessiva(): void {
    $this->session->forward();
    $this->waitForPage();
  }

  /**
   * Clicca sul link indicato tramite testo|id|title|alt
   *
   * @When vai al link :link
   */
  public function vaiAlLink($link): void {
    $this->session->getPage()->clickLink($link);
    $this->waitForPage();
  }

  /**
   * Controlla che la pagina attuale sia quella indicata
   *
   * @Then vedi pagina :pagina
   */
  public function vediPagina($pagina): void {
    $this->assertPageStatus(200);
    $this->assertPageUrl($this->getMinkParameter('base_url').$this->router->generate($pagina));
  }


  //==================== METODI PROTETTI DELLA CLASSE ====================

  /**
   * Controlla che l'URL indicata corrisponda alla pagina corrente o lancia un'eccezione
   *
   * @param string $url Indirizzo da controllare
   * @param string $message Messaggio di errore
   */
  protected function assertPageUrl($url, $message=null): void {
    if ($url != $this->session->getCurrentUrl()) {
      $info = $this->trace();
      $msg = ($message ? $message : 'Failed asserting that URL is the address of the current page').$info."\n".
        '+++ Expected: '.var_export($url, true)."\n".
        '+++ Actual: '.var_export($this->session->getCurrentUrl(), true)."\n";
      throw new ExpectationException($msg, $this->session);
    }
  }

  /**
   * Controlla che il codice di stato indicato corrisponda a quello della pagina corrente o lancia un'eccezione
   *
   * @param int $status Codice di stato della pagina
   * @param string $message Messaggio di errore
   */
  protected function assertPageStatus($status, $message=null): void {
    if ($status != $this->session->getStatusCode()) {
      $info = $this->trace();
      $msg = ($message ? $message : 'Failed asserting that value is the status code of the current page').$info."\n".
        '+++ Expected: '.var_export($status, true)."\n".
        '+++ Actual: '.var_export($this->session->getStatusCode(), true)."\n";
      throw new ExpectationException($msg, $this->session);
    }
  }

  /**
   * Aspetta il caricamento completo della pagina
   *
   */
  protected function waitForPage(): void {
    $this->session->wait(30000, "document.readyState === 'complete'");
  }

}
