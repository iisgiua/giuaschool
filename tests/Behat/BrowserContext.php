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

use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouterInterface;
use Doctrine\ORM\EntityManagerInterface;
use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Exception\ExpectationException;


/**
 * Contesto con interazione con il browser
 */
class BrowserContext extends BaseContext {

  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * Utente che ha effettuato il login
   *
   * @var Utente $loggedUser Utente che ha effettuato il login
   */
  protected $loggedUser;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Inizializza le variabili per l'ambiente di test
   *
   * @param KernelInterface $kernel Gestore delle funzionalità http del kernel
   * @param EntityManagerInterface $em Gestore delle entità
   * @param RouterInterface $router Gestore delle URL
   */
  public function __construct(KernelInterface $kernel, EntityManagerInterface $em, RouterInterface $router) {
    parent::__construct($kernel, $em, $router);
    $this->loggedUser = null;
  }

  /**
   * Va alla pagina indicata (anche con parametri)
   *  $pagina: nome della pagina
   *  $parametri: array associativo dei parametri (presi da trasformazione di tabella)
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
   *  $username: nome utente
   *  $password: password dell'utente o null per password uguale alla username
   *
   * @Given login utente :username
   * @Given login utente :username con :password
   */
  public function loginUtente($username, $password=null): void {
    if (!$this->loggedUser) {
      $this->loggedUser = $this->em->getRepository('App:Utente')->findOneByUsername($username);
    }
    $this->assertTrue($this->loggedUser && $this->loggedUser->getUsername() == $username);
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
   *  $ruolo: nome ruolo (utente|alunno|genitore|ata|docente|staff|preside|amministratore)
   *
   * @Given login utente con ruolo :ruolo
   */
  public function loginUtenteConRuolo($ruolo): void {
    $this->assertEmpty($this->loggedUser);
    $class_name = ucfirst(strtolower($ruolo));
    $utente = $this->faker->randomElement($this->em->getRepository('App:'.$class_name)->findBy([]));
    $this->assertNotEmpty($utente);
    $this->loggedUser = $utente;
    $this->loginUtente($utente->getUsername());
  }

  /**
   * Esegue il login di un utente a caso del tipo esatto indicato (escluse sottoclassi)
   *  $ruolo: nome ruolo (utente|alunno|genitore|ata|docente|staff|preside|amministratore)
   *
   * @Given login utente con ruolo esatto :ruolo
   */
  public function loginUtenteConRuoloEsatto($ruolo): void {
    $this->assertEmpty($this->loggedUser);
    $class_name = ucfirst(strtolower($ruolo));
    do {
      $utente = $this->faker->randomElement($this->em->getRepository('App:'.$class_name)->findBy([]));
      $this->assertNotEmpty($utente);
    } while (get_class($utente) != 'App\\Entity\\'.$class_name);
    $this->loggedUser = $utente;
    $this->loginUtente($utente->getUsername());
  }

  /**
   * Modifica l'istanza dell'utente attualmente collegato con i parametri indicati
   *  $parametri: array associativo dei parametri (presi da trasformazione di tabella)
   *
   * @Given modifica utente attuale con parametri:
   */
  public function modificaUtenteAttualeConParametri($parametri): void {
    $this->assertNotEmpty($this->loggedUser);
    foreach ($parametri as $key=>$val) {
      $this->loggedUser->{'set'.ucfirst(strtolower($key))}($val);
    }
    $this->em->flush();
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
   *  $link: testo del link o presente negli attributi id|title o alt (se c'è immagine)
   *
   * @When vai al link :link
   */
  public function vaiAlLink($link): void {
    $this->session->getPage()->clickLink($link);
    $this->waitForPage();
  }

  /**
   * Controlla che la pagina attuale sia quella indicata
   *  $pagina: nome della pagina
   *
   * @Then vedi pagina :pagina
   */
  public function vediPagina($pagina): void {
    $this->assertPageStatus(200);
    $this->assertPageUrl($this->getMinkParameter('base_url').$this->router->generate($pagina));
  }

  /**
   * Controlla che la sezione individuata univocamente dal selettore css contenga il testo indicato
   *  $selettore: selettore css che individua la sezione o elemento in cui cercare il testo
   *  $ricerca: testo da cercare come espressione regolare
   *
   * @Then la sezione :selettore contiene :ricerca
   */
  public function laSezioneIndicataContiene($selettore, $ricerca): void {
    $sezione = $this->session->getPage()->find('css', $selettore);
    $this->assertTrue($sezione && preg_match($ricerca, $sezione->getText()));
  }

  /**
   * Controlla che la sezione individuata univocamente dal selettore css non contenga il testo indicato
   *  $selettore: selettore css che individua la sezione o elemento in cui cercare il testo
   *  $ricerca: testo da cercare come espressione regolare
   *
   * @Then la sezione :selettore non contiene :ricerca
   */
  public function laSezioneIndicataNonContiene($selettore, $ricerca): void {
    $sezione = $this->session->getPage()->find('css', $selettore);
    $this->assertFalse($sezione && preg_match($ricerca, $sezione->getText()));
  }

  /**
   * Controlla che la tabella indicata abbia il numero di righe specificato
   *  $numero: numero di righe della tabella
   *  $indice: indice progressivo delle tabelli presenti nel contenuto della pagina (parte da 1)
   *
   * @Then vedi :numero righe nella tabella :indice
   * @Then vedi :numero riga nella tabella :indice
   * @Then vedi :numero righe nella tabella
   * @Then vedi :numero riga nella tabella
   */
  public function vediNumeroRigheNellaTabellaIndicata($numero, $indice=1): void {
    $tabelle = $this->session->getPage()->findAll('css', '#gs-main table');
    $this->assertNotEmpty($tabelle[$indice - 1]);
    $righe = $tabelle[$indice - 1]->findAll('css', 'tbody tr');
    $this->assertEquals($numero, count($righe));
  }

  /**
   * Controlla che la tabella indicata abbia le intestazioni delle colonne specificate
   *  $indice: indice progressivo delle tabelli presenti nel contenuto della pagina (parte da 1)
   *  $colonne: i campi dell'unica riga corrispondono alle intestazioni delle colonne della tabella
   *
   * @Then vedi nella tabella :indice le colonne:
   * @Then vedi nella tabella le colonne:
   */
  public function vediNellaTabellaIndicataLeColonne($indice=1, TableNode $colonne): void {
    $tabelle = $this->session->getPage()->findAll('css', '#gs-main table');
    $this->assertNotEmpty($tabelle[$indice - 1]);
    $intestazioni = $tabelle[$indice - 1]->findAll('css', 'thead tr th');
    $this->assertEquals(count($intestazioni), count($colonne->getRow(0)));
    foreach ($colonne->getRow(0) as $key=>$val) {
      $this->assertEquals(strtolower(trim($val)), strtolower(trim($intestazioni[$key]->getText())));
    }
  }

  /**
   * Controlla che nella tabella e riga indicata i dati corrispondano a quelli specificati
   *  $numero: numero di riga dei dati della tabella (parte da 1)
   *  $indice: indice progressivo delle tabelli presenti nel contenuto della pagina (parte da 1)
   *  $dati: i campi corrispondono ai dati da cercare nelle colonne indicate
   *
   * @Then vedi nella riga :numero della tabella :indice i dati:
   * @Then vedi nella riga :numero della tabella i dati:
   */
  public function vediNellaRigaNumeroDellaTabellaIndicataIDati($numero, $indice=1, TableNode $dati): void {
    $tabelle = $this->session->getPage()->findAll('css', '#gs-main table');
    $this->assertNotEmpty($tabelle[$indice - 1]);
    $intestazioni = $tabelle[$indice - 1]->findAll('css', 'thead tr th');
    $this->assertNotEmpty($intestazioni);
    $intestazioni_nomi = array_map(function($v){ return strtolower(trim($v->getText())); }, $intestazioni);
    $righe = $tabelle[$indice - 1]->findAll('css', 'tbody tr');
    $this->assertNotEmpty($righe[$numero - 1]);
    $colonne = $righe[$numero - 1]->findAll('css', 'td');
    $this->assertNotEmpty($colonne);
    foreach ($dati->getHash()[0] as $key=>$val) {
      $this->assertArrayContains(strtolower($key), $intestazioni_nomi);
      $cella = $colonne[array_search(strtolower($key), $intestazioni_nomi)]->getText();
      $this->assertTrue(preg_match($this->convertSearch($val), $cella));
    }
  }

  /**
   * Controlla che in una riga qualsiasi della tabella indicata i dati corrispondano a quelli specificati
   *  $indice: indice progressivo delle tabelli presenti nel contenuto della pagina (parte da 1)
   *  $dati: i campi corrispondono ai dati da cercare nelle colonne indicate
   *
   * @Then vedi in una riga della tabella :indice i dati:
   * @Then vedi in una riga della tabella i dati:
   */
  public function vediInUnaRigaDellaTabellaIndicataIDati($indice=1, TableNode $dati): void {
    $tabelle = $this->session->getPage()->findAll('css', '#gs-main table');
    $this->assertNotEmpty($tabelle[$indice - 1]);
    $intestazioni = $tabelle[$indice - 1]->findAll('css', 'thead tr th');
    $this->assertNotEmpty($intestazioni);
    $intestazioni_nomi = array_map(function($v){ return strtolower(trim($v->getText())); }, $intestazioni);
    $righe = $tabelle[$indice - 1]->findAll('css', 'tbody tr');
    $this->assertNotEmpty($righe);
    foreach ($righe as $riga) {
      $colonne = $riga->findAll('css', 'td');
      $this->assertNotEmpty($colonne);
      $trovato = true;
      foreach ($dati->getHash()[0] as $key=>$val) {
        $this->assertArrayContains(strtolower($key), $intestazioni_nomi);
        $cella = $colonne[array_search(strtolower($key), $intestazioni_nomi)]->getText();
        if (!preg_match($this->convertSearch($val), $cella)) {
          $trovato = false;
          break;
        }
      }
      if ($trovato) {
        break;
      }
    }
    $this->assertTrue($trovato);
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
