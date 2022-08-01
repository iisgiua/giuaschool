<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\Behat;

use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Exception\ExpectationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\String\Slugger\SluggerInterface;


/**
 * Contesto con interazione con il browser
 *
 * @author Antonello Dessì
 */
class BrowserContext extends BaseContext {

  //==================== METODI DELLA CLASSE ====================

  /**
   * Inizializza le variabili per l'ambiente di test
   *
   * @param KernelInterface $kernel Gestore delle funzionalità http del kernel
   * @param EntityManagerInterface $em Gestore delle entità
   * @param RouterInterface $router Gestore delle URL
   * @param UserPasswordHasherInterface $hasher Gestore della codifica delle password
   * @param SluggerInterface $slugger Gestore della modifica delle stringhe in slug
   */
  public function __construct(KernelInterface $kernel, EntityManagerInterface $em, RouterInterface $router,
                              UserPasswordHasherInterface $hasher, SluggerInterface $slugger) {
    parent::__construct($kernel, $em, $router, $hasher, $slugger);
    $this->vars['sys']['logged'] = null;
  }

  /**
   * Va alla pagina indicata (anche con parametri) e controlla che sia attiva
   *  $pagina: nome della pagina
   *  $tabella: tabella con nomi dei campi ed i valori da assegnare
   *
   * @Given pagina attiva :pagina
   * @Given pagina attiva :pagina con parametri:
   */
  public function paginaAttiva($pagina, TableNode $tabella=null): void {
    $parametri = [];
    if ($tabella) {
      foreach ($tabella->getHash() as $row) {
        foreach ($row as $key=>$val) {
          $parametri[$key] = $this->convertText($val);
        }
      }
    }
    $url = $this->getMinkParameter('base_url').$this->router->generate($pagina, $parametri);
    $this->session->visit($url);
    $this->waitForPage();
    $this->assertPageStatus(200);
    $this->log('GOTO', 'Pagina: '.$pagina);
  }

  /**
   * Va alla pagina indicata (anche con parametri)
   *  $pagina: nome della pagina
   *  $tabella: tabella con nomi dei campi ed i valori da assegnare
   *
   * @When vai alla pagina :pagina
   * @When vai alla pagina :pagina con parametri:
   */
   public function vaiAllaPagina($pagina, TableNode $tabella=null): void {
     $parametri = [];
     if ($tabella) {
       foreach ($tabella->getHash() as $row) {
         foreach ($row as $key=>$val) {
           $parametri[$key] = $this->convertText($val);
         }
       }
     }
    $url = $this->getMinkParameter('base_url').$this->router->generate($pagina, $parametri);
    $this->session->visit($url);
    $this->waitForPage();
    $this->log('GOTO', 'Pagina: '.$pagina);
  }

  /**
   * Esegue il login dell'utente indicato (sceglie il primo profilo se più di uno)
   *  $username: nome utente
   *  $password: password dell'utente o null per password uguale alla username
   *
   * @Given login utente :valore
   * @Given login utente :valore con :password
   */
  public function loginUtente($valore, $password=null): void {
    $this->assertEmpty($this->vars['sys']['logged']);
    $user = $this->em->getRepository('App\Entity\Utente')->findOneByUsername($valore);
    $this->paginaAttiva('login_form');
    $this->assertTrue($user && $user->getUsername() == $valore);
    $this->session->getPage()->fillField('username', $valore);
    $this->session->getPage()->fillField('password', $password ? $password : $valore);
    $this->session->getPage()->pressButton('login');
    $this->waitForPage();
    $this->assertPageStatus(200);
    if ($this->session->getCurrentUrl() == $this->getMinkParameter('base_url').$this->router->generate('login_profilo')) {
      $this->selezioniOpzioneDaPulsantiRadio($user->getUsername(), 'login_profilo[profilo]');
      $this->clickSu('Conferma');
      $this->assertPageStatus(200);
    }
    $this->assertPageUrl($this->getMinkParameter('base_url').$this->router->generate('login_home'));
    $this->vars['sys']['logged'] = $user;
    $others = $this->em->getRepository('App\Entity\Utente')->createQueryBuilder('u')
      ->where('u.username!=:username AND u.codiceFiscale!=:codFiscale AND u INSTANCE OF '.get_class($user))
      ->setParameters(['username' => $user->getUsername(), 'codFiscale' => $user->getCodiceFiscale()])
      ->getQuery()
      ->getResult();
    $other = null;
    foreach ($others as $val) {
      if (get_class($val) == get_class($user)) {
        $other = $val;
        break;
      }
    }
    $this->vars['sys']['other'] = $other;
    $this->log('LOGIN', 'Username: '.$valore.' - Ruolo: '.$user->getRoles()[0]);
    $this->logDebug('Altro utente: '.($other ? $other->getUsername().' - Ruolo: '.$other->getRoles()[0] : null));
  }

  /**
   * Esegue il login di un utente a caso del tipo indicato (comprese sottoclassi)
   *  $ruolo: nome ruolo (utente|alunno|genitore|ata|docente|staff|preside|amministratore)
   *
   * @Given login utente con ruolo :ruolo
   */
  public function loginUtenteConRuolo($ruolo): void {
    $class_name = ucfirst($ruolo);
    $user = $this->faker->randomElement($this->em->getRepository("App\\Entity\\".$class_name)->findBy(['abilitato' => 1]));
    $this->assertNotEmpty($user);
    $this->loginUtente($user->getUsername());
  }

  /**
   * Esegue il login di un utente a caso del tipo esatto indicato (escluse sottoclassi)
   *  $ruolo: nome ruolo (utente|alunno|genitore|ata|docente|staff|preside|amministratore)
   *
   * @Given login utente con ruolo esatto :ruolo
   */
  public function loginUtenteConRuoloEsatto($ruolo): void {
    $class_name = ucfirst($ruolo);
    $users = $this->em->getRepository('App\Entity\\'.$class_name)->findBy(['abilitato' => 1]);
    $this->assertNotEmpty($users);
    do {
      $user = $this->faker->randomElement($users);
    } while (get_class($user) != 'App\\Entity\\'.$class_name  &&
             get_class($user) != 'Proxies\\__CG__\\App\\Entity\\'.$class_name);
    $this->loginUtente($user->getUsername());
  }

  /**
   * Modifica l'istanza dell'utente attualmente collegato con i parametri indicati
   *  $tabella: tabella con nomi dei campi ed i valori da assegnare
   *
   * @Given modifica utente connesso:
   */
  public function modificaUtenteConnesso(TableNode $tabella): void {
    $this->assertNotEmpty($this->vars['sys']['logged']);
    foreach ($tabella->getHash() as $row) {
      foreach ($row as $key=>$val) {
        $value = $this->convertText($val);
        $this->vars['sys']['logged']->{'set'.ucfirst($key)}($value);
      }
    }
    $this->em->flush();
  }

  /**
   * Esegue il logout dell'utente connesso
   *
   * @Given logout utente
   */
  public function logoutUtente(): void {
    $this->assertNotEmpty($this->vars['sys']['logged']);
    $this->paginaAttiva('logout');
    $this->waitForPage();
    $this->assertPageStatus(200);
    $this->assertPageUrl($this->getMinkParameter('base_url').$this->router->generate('login_form'));
    $user = $this->vars['sys']['logged'];
    $this->vars['sys']['logged'] = null;
    $this->vars['sys']['other'] = null;
    $this->log('LOGOUT', 'Username: '.$user->getUsername().' - Ruolo: '.$user->getRoles()[0]);
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
   *  $tabella: tabella con nomi dei campi ed i valori da assegnare
   *
   * @Then vedi pagina :pagina
   * @Then vedi pagina :pagina con parametri:
   */
  public function vediPagina($pagina, TableNode $tabella=null): void {
    $this->assertPageStatus(200);
    $parametri = [];
    if ($tabella) {
      foreach ($tabella->getHash() as $row) {
        foreach ($row as $key=>$val) {
          $parametri[$key] = $this->convertText($val);
        }
      }
    }
    $this->assertPageUrl($this->getMinkParameter('base_url').$this->router->generate($pagina, $parametri));
    $this->log('SHOW', 'Pagina: '.$pagina);
  }

  /**
   * Controlla che la pagina attuale sia quella indicata
   *  $error: codice di errore
   *
   * @Then vedi errore pagina
   * @Then vedi errore pagina :error
   */
  public function vediErrorePagina($error=null): void {
    if ($error) {
      $this->assertPageStatus($error);
    } else {
      $this->assertTrue($this->session->getStatusCode() >= 400);
    }
  }

  /**
   * Controlla che la sezione individuata univocamente dal selettore css contenga il testo indicato
   *  $selettore: selettore css che individua la sezione o elemento in cui cercare il testo
   *  $ricerca: testo da cercare come espressione regolare
   *
   * @Then la sezione :selettore contiene :ricerca
   */
  public function laSezioneContiene($selettore, $ricerca): void {
    $sezione = $this->session->getPage()->find('css', $selettore);
    $this->logDebug('laSezioneContiene -> '.$ricerca.' | '. $sezione->getText());
    $this->assertTrue($sezione && $sezione->isVisible() && preg_match($ricerca, $sezione->getText()));
  }

  /**
   * Controlla che la sezione individuata univocamente dal selettore css non contenga il testo indicato
   *  $selettore: selettore css che individua la sezione o elemento in cui cercare il testo
   *  $ricerca: testo da cercare come espressione regolare
   *
   * @Then la sezione :selettore non contiene :ricerca
   */
  public function laSezioneNonContiene($selettore, $ricerca): void {
    $sezione = $this->session->getPage()->find('css', $selettore);
    $this->assertFalse($sezione && $sezione->isVisible() && preg_match($ricerca, $sezione->getText()));
  }

  /**
   * Controlla che la tabella indicata abbia il numero di righe specificato
   *  $numero: numero di righe della tabella
   *  $indice: indice progressivo delle tabelle presenti nel contenuto della pagina (parte da 1)
   *
   * @Then vedi :numero righe nella tabella :indice
   * @Then vedi :numero riga nella tabella :indice
   * @Then vedi :numero righe nella tabella
   * @Then vedi :numero riga nella tabella
   */
  public function vediRigheNellaTabella($numero, $indice=1): void {
    $tabelle = $this->session->getPage()->findAll('css', '#gs-main table');
    $this->assertNotEmpty($tabelle[$indice - 1]);
    $righe = $tabelle[$indice - 1]->findAll('css', 'tbody tr');
    $this->assertEquals($numero, count($righe));
  }

  /**
   * Controlla che la tabella indicata abbia almeno il numero di righe specificato
   *  $numero: numero di righe della tabella
   *  $indice: indice progressivo delle tabelle presenti nel contenuto della pagina (parte da 1)
   *
   * @Then vedi almeno :numero righe nella tabella :indice
   * @Then vedi almeno :numero riga nella tabella :indice
   * @Then vedi almeno :numero righe nella tabella
   * @Then vedi almeno :numero riga nella tabella
   */
  public function vediAlmenoRigheNellaTabella($numero, $indice=1): void {
    $tabelle = $this->session->getPage()->findAll('css', '#gs-main table');
    $this->assertNotEmpty($tabelle[$indice - 1]);
    $righe = $tabelle[$indice - 1]->findAll('css', 'tbody tr');
    $this->assertTrue($numero <= count($righe));
  }

  /**
   * Controlla che la tabella indicata abbia al massimo il numero di righe specificato
   *  $numero: numero di righe della tabella
   *  $indice: indice progressivo delle tabelle presenti nel contenuto della pagina (parte da 1)
   *
   * @Then vedi al massimo :numero righe nella tabella :indice
   * @Then vedi al massimo :numero riga nella tabella :indice
   * @Then vedi al massimo :numero righe nella tabella
   * @Then vedi al massimo :numero riga nella tabella
   */
  public function vediAlMassimoRigheNellaTabella($numero, $indice=1): void {
    $tabelle = $this->session->getPage()->findAll('css', '#gs-main table');
    $this->assertNotEmpty($tabelle[$indice - 1]);
    $righe = $tabelle[$indice - 1]->findAll('css', 'tbody tr');
    $this->assertTrue($numero >= count($righe));
  }

  /**
   * Controlla che la tabella indicata abbia le intestazioni delle colonne specificate
   *  $indice: indice progressivo delle tabelle presenti nel contenuto della pagina (parte da 1)
   *  $colonne: i campi dell'unica riga corrispondono alle intestazioni delle colonne della tabella
   *
   * @Then vedi nella tabella :indice le colonne:
   * @Then vedi nella tabella le colonne:
   */
  public function vediNellaTabellaLeColonne($indice=1, TableNode $colonne): void {
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
   *  $indice: indice progressivo delle tabelle presenti nel contenuto della pagina (parte da 1)
   *  $dati: i campi corrispondono ai dati da cercare nelle colonne indicate
   *
   * @Then vedi nella riga :numero della tabella :indice i dati:
   * @Then vedi nella riga :numero della tabella i dati:
   */
  public function vediNellaRigaDellaTabellaIDati($numero, $indice=1, TableNode $dati): void {
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
      $cerca = $this->convertSearch($val);
      $this->logDebug('vediNellaRigaDellaTabellaIDati -> '.$cerca.' | '.$cella);
      $this->assertTrue(preg_match($cerca, $cella));
    }
  }

  /**
   * Controlla che in una riga qualsiasi della tabella indicata i dati corrispondano a quelli specificati
   * NB: non funziona se si usa nella tabella COLSPAN o ROWSPAN
   *  $indice: indice progressivo delle tabelle presenti nel contenuto della pagina (parte da 1)
   *  $dati: i campi corrispondono ai dati da cercare nelle colonne indicate
   *
   * @Then vedi nella tabella :indice i dati:
   * @Then vedi nella tabella i dati:
   */
  public function vediNellaTabellaIDati($indice=1, TableNode $dati): void {
    $tabelle = $this->session->getPage()->findAll('css', '#gs-main table');
    $this->assertNotEmpty($tabelle[$indice - 1]);
    list($intestazione, $valori) = $this->parseTable($tabelle[$indice - 1]);
    $datiIntestazioni = array_keys($dati->getHash()[0]);
    $this->assertNotEmpty($datiIntestazioni);
    $colonne = [];
    foreach ($datiIntestazioni as $nome) {
      $trovato = false;
      foreach ($intestazione as $col=>$val) {
        if (strtolower($nome) == strtolower($val)) {
          $colonne[$nome] = $col;
          $trovato = true;
          break;
        }
      }
      $this->assertTrue($trovato, "Table header is different");
    }
    $datiValori = $dati->getHash();
    $this->assertNotEmpty($datiValori);
    foreach ($datiValori as $idx=>$rdati) {
      $trovato = false;
      foreach ($valori as $ri=>$rval) {
        $trovato = true;
        foreach ($rdati as $nome=>$val) {
          $cerca = $this->convertSearch($val);
          $this->logDebug('vediNellaTabellaIDati ['.$idx.','.$nome.'] -> '.$cerca.' | '.$valori[$ri][$colonne[$nome]]);
          if (!preg_match($cerca, $valori[$ri][$colonne[$nome]])) {
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
  }

  /**
   * Clicca su link o pulsante per eseguire azione
   *  $testo: testo del link o pulsante, o presente negli attributi id|name|title|alt|value
   *  $indice: indice progressivo dei pulsanti presenti nel contenuto della pagina (parte da 1)
   *
   * @Given premuto pulsante :testo
   * @Given premuto pulsante :testo con indice :indice
   * @When premi pulsante :testo
   * @When premi pulsante :testo con indice :indice
   * @When click su :testo
   * @When click su :testo con indice :indice
   */
  public function clickSu($testo, $indice=1): void {
    $links = $this->session->getPage()->findAll('named', array('link_or_button', $testo));
    $this->assertNotEmpty($links[$indice - 1]);
    $links[$indice - 1]->click();
    // attesa per completare le modifiche sulla pagina
    sleep(1);
    $this->waitForPage();
  }

  /**
   * Controlla che sia stato scaricato il file indicato
   *  $testoParam: nome assegnato al file (con parametri)
   *  $dimensione: lunghezza del file in byte
   *
   * @Then file scaricato con nome :testoParam
   * @Then file scaricato con nome :testoParam e dimensione :dimensione
   */
  public function fileScaricatoConNomeEDimensione($testoParam, $dimensione=null): void {
    $this->assertPageStatus(200);
    $headers = $this->session->getResponseHeaders();
    $this->assertTrue(preg_match("/^attachment;\s*filename=(.*)$/i", $headers['Content-Disposition'], $data));
    $this->assertTrue($data[1] == $testoParam && ($dimensione === null || $headers['Content-Length'] == $dimensione));
    $this->log('DOWNLOAD', 'File: '.$data[1].' ['.$headers['Content-Length'].' byte]');
  }

  /**
   * Va alla URL indicata
   *  $testoParam: url della pagina, può contenere variabili con sintassi {{$nome}} o {{#nome}} o {{@nome}}
   *
   * @When vai alla url :testoParam
   */
  public function vaiAllaUrl($testoParam): void {
    $url = $this->getMinkParameter('base_url').$testoParam;
    $this->session->visit($url);
    $this->waitForPage();
    $this->log('GOTO', 'Url: '.$url);
  }

  /**
   * Carica un file tramite dropzone
   *  $testoParam: nome del file presente nella direcotry tests/data (con parrametri)
   *  $dz: percors CSS per la dropzone
   *
   * @When alleghi file :testoParam a dropzone
   * @When alleghi file :testoParam a dropzone :dz
   */
  public function alleghiFileADropzone($testoParam, $dz='.dropzone'): void {
    $nomefile = $this->kernel->getProjectDir().'/tests/data/'.$testoParam;
    $this->assertTrue(file_exists($nomefile.'.base64'));
    $data = file_get_contents($nomefile.'.base64');
    $js = 'data = "'.$data.'";'.
      'arrayBuffer = Uint8Array.from(window.atob(data), c => c.charCodeAt(0));'.
      'file = new File([arrayBuffer], "'.$testoParam.'");'.
      'Dropzone.forElement("'.$dz.'").addFile(file);';
    $this->session->executeScript($js);
    // attesa per completare le modifiche sulla pagina
    sleep(1);
    $this->log('UPLOAD', 'File: '.$testoParam);
  }

  /**
   * Controlla l'esistenza di un file
   *  $testoParam: nome del file con percorso relativo alla directory FILES (con inserimento parametri)
   *  $dimensione: dimensione del file in byte
   *
   * @Then vedi file :testoParam
   * @Then vedi file :testoParam di dimensione :dimensione
   */
  public function vediFile($testoParam, $dimensione=null): void {
    $nomefile = $this->kernel->getProjectDir().'/FILES/'.$testoParam;
    $this->assertTrue(file_exists($nomefile) && ($dimensione === null || filesize($nomefile) == $dimensione));
    $this->files[] = 'FILES/'.$testoParam;
  }

  /**
   * Controlla la non esistenza di un file
   *  $testoParam: nome del file con percorso relativo alla directory FILES (con parametri)
   *
   * @Then non vedi file :testoParam
   */
  public function nonVediFile($testoParam): void {
    $nomefile = $this->kernel->getProjectDir().'/FILES/'.$testoParam;
    $this->assertFalse(file_exists($nomefile));
  }

  /**
   * Decodifica un file PDF e controlla la presenza del testo indicato
   *  $ricerca: testo da cercare nel file
   *  $testoParam: nome del file con percorso relativo alla directory FILES (con parametri)
   *  $valore: password per la decodifica
   *
   * @Then vedi :ricerca in file :testoParam decodificato con :valore
   */
  public function vediInFileDecodificato($ricerca, $testoParam, $valore): void {
    $nomefile = $this->kernel->getProjectDir().'/FILES/'.$testoParam;
    $convertito = substr($nomefile, 0, -3).'txt';
    $testo = null;
    try {
      $proc = new Process(['/usr/bin/pdftotext', '-upw', $valore, $nomefile, $convertito]);
      $proc->setTimeout(0);
      $proc->run();
      if ($proc->isSuccessful() && file_exists($convertito)) {
        // conversione ok
        $testo = file_get_contents($convertito);
      }
    } catch (\Exception $err) {
      // errore: evita eccezione
    }
    $this->assertTrue($testo && preg_match($ricerca, $testo));
    $this->files[] = 'FILES/'.$testoParam;
    $this->files[] = 'FILES/'.substr($testoParam, 0, -3).'txt';
  }

  /**
   * Controlla che il pulsante indicato sia abiliato
   *  $button: testo del pulsante o presente negli attributi id|title|name o alt (se c'è immagine)
   *
   * @Then pulsante :nome attivo
   */
  public function pulsanteAttivo($button): void {
    $element = $this->session->getPage()->findButton($button);
    $this->assertFalse($element->getAttribute('disabled'));
  }

  /**
   * Controlla che il pulsante indicato sia disabiliato
   *  $button: testo del pulsante o presente negli attributi id|title|name o alt (se c'è immagine)
   *
   * @Then pulsante :nome inattivo
   */
  public function pulsanteInattivo($button): void {
    $element = $this->session->getPage()->findButton($button);
    $this->assertTrue($element->getAttribute('disabled'));
  }

  /**
   * Seleziona opzione da lista di scelta tramite SELECT
   *  $valore: testo o valore dell'opzione
   *  $lista: lista identifica tramite attributo id|name|label
   *
   * @Given opzione :valore selezionata da lista :lista
   * @When selezioni opzione :valore da lista :lista
   */
  public function selezioniOpzioneDaLista($valore, $lista): void {
    $field = $this->session->getPage()->findField($lista);
    if (!$field || !$field->isVisible()) {
      $labels = $this->session->getPage()->findAll('css', 'label:contains("'.$lista.'")');
      foreach ($labels as $lab) {
        if (!$lab->isVisible()) {
          continue;
        }
        $id = $lab->getAttribute('for');
        $field = $this->session->getPage()->find('css', 'SELECT[id="'.$id.'"]');
        if ($field) {
          break;
        }
      }
    }
    $this->assertNotEmpty($field);
    $option = $field->find('named', ['option', $valore]);
    $this->assertNotEmpty($option);
    $option->click();
    // attesa per completare le modifiche sulla pagina
    sleep(1);
  }

  /**
   * Seleziona opzione da lista di scelta tramite RADIO BUTTON
   *  $valore: testo o valore dell'opzione
   *  $lista: lista identifica tramite attributo id|name
   *
   * @Given opzione :valore selezionata da pulsanti radio :lista
   * @When selezioni opzione :valore da pulsanti radio :lista
   */
  public function selezioniOpzioneDaPulsantiRadio($valore, $lista): void {
    $options = $this->session->getPage()->findAll('named', ['radio', $valore]);
    $this->assertNotEmpty($options);
    $option = null;
    foreach ($options as $opt) {
      $id = $opt->getAttribute('id');
      $name = $opt->getAttribute('name');
      if (preg_match('/^'.preg_quote($lista).'_\d+$/i', $id) || strtolower($lista) == strtolower($name)) {
        $option = $opt;
        break;
      }
    }
    $this->assertNotEmpty($option);
    $option->click();
    // attesa per completare le modifiche sulla pagina
    sleep(1);
  }

  /**
   * Controlla che la tabella indicata abbia le intestazioni e i dati corrispondenti a quelli specificati
   *  $indice: indice progressivo delle tabelle presenti nel contenuto della pagina (parte da 1)
   *  $dati: intestazione e dati da confrontare con la tabella indicata
   *
   * @Then vedi la tabella :indice:
   * @Then vedi la tabella:
   */
  public function vediLaTabella($indice=1, TableNode $dati): void {
    $tabelle = $this->session->getPage()->findAll('css', '#gs-main table');
    $this->assertNotEmpty($tabelle[$indice - 1]);
    list($intestazione, $valori) = $this->parseTable($tabelle[$indice - 1]);
    // controlla intestazioni
    $datiIntestazioni = array_keys($dati->getHash()[0]);
    $this->assertEquals(count($datiIntestazioni), count($intestazione), 'Table header has different column number');
    foreach ($datiIntestazioni as $i=>$nome) {
      $this->assertEquals(strtolower($nome), strtolower($intestazione[$i]), 'Table header is different');
    }
    // controlla dati
    $datiValori = $dati->getHash();
    $this->assertEquals(count($datiValori), count($valori), 'Table row count is different');
    foreach ($datiValori as $ri=>$riga) {
      foreach (array_values($riga) as $co=>$val) {
        $cerca = $this->convertSearch($val);
        $this->logDebug('vediLaTabella ['.$ri.','.$co.'] -> '.$cerca.' | '.$valori[$ri][$co]);
        $this->assertTrue(preg_match($cerca, $valori[$ri][$co]),
          'Table cell ['.($ri + 1).', '.($co + 1).'] is different');
      }
    }
  }

  /**
   * Controlla che la tabella indicata abbia le intestazioni e i dati corrispondenti a quelli specificati,
   * ma non considera l'ordine delle righe
   *  $indice: indice progressivo delle tabelle presenti nel contenuto della pagina (parte da 1)
   *  $dati: intestazione e dati da confrontare con la tabella indicata
   *
   * @Then vedi la tabella :indice non ordinata:
   * @Then vedi la tabella non ordinata:
   */
  public function vediLaTabellaNonOrdinata($indice=1, TableNode $dati): void {
    $tabelle = $this->session->getPage()->findAll('css', '#gs-main table');
    $this->assertNotEmpty($tabelle[$indice - 1]);
    list($intestazione, $valori) = $this->parseTable($tabelle[$indice - 1]);
    // controlla intestazioni
    $datiIntestazioni = array_keys($dati->getHash()[0]);
    $this->assertEquals(count($datiIntestazioni), count($intestazione), 'Table header has different column number');
    foreach ($datiIntestazioni as $i=>$nome) {
      $this->assertEquals(strtolower($nome), strtolower($intestazione[$i]), 'Table header is different');
    }
    // controlla dati
    $this->assertEquals(count($dati->getHash()), count($valori), 'Table row count is different');
    $righeTrovate = [];
    for ($ri = 0; $ri < count($valori); $ri++) {
      foreach ($dati->getHash() as $idx=>$riga) {
        if (in_array($dati->getRowLine($idx), $righeTrovate)) {
          $trovato = false;
          continue;
        }
        $trovato = true;
        foreach (array_values($riga) as $co=>$val) {
          if (!preg_match($this->convertSearch($val), $valori[$ri][$co])) {
            $trovato = false;
            break;
          }
        }
        if ($trovato) {
          break;
        }
      }
      $this->assertTrue($trovato, 'Table row '.($ri + 1).' not found');
      $righeTrovate[] = $dati->getRowLine($idx);
    }
  }

  /**
   * Controlla che la tabella con le intestazioni indicate non sia presente nella pagina
   *  $dati: intestazione della tabella da controllare
   *
   * @Then non vedi la tabella:
   */
  public function nonVediLaTabella(TableNode $dati): void {
    $tabelle = $this->session->getPage()->findAll('css', '#gs-main table');
    if (!empty($tabelle)) {
      foreach ($tabelle as $tabella) {
        list($intestazione, $valori) = $this->parseTable($tabella);
        $trovato = true;
        foreach ($dati->getRows()[0] as $i=>$nome) {
          if (strtolower($nome) != strtolower($intestazione[$i])) {
            $trovato = false;
            break;
          }
        }
        $this->assertFalse($trovato);
      }
    }
  }

  /**
   * Inserisce un valore in un campo di testo specificato
   *  $valore: testo da inserire nel campo
   *  $campo: campo identificato da attributi id|name o label
   *
   * @When inserisci :valore nel campo :campo
   */
  public function inserisciNelCampo($valore, $campo): void {
    $fields = $this->session->getPage()->findAll('named', ['field', $campo]);
    $this->assertNotEmpty($fields);
    $field = null;
    foreach ($fields as $f) {
      if (!$f->isVisible()) {
        continue;
      }
      $field = $f;
      break;
    }
    $this->assertNotEmpty($field);
    $field->setValue($valore);
  }

  /**
   * Clicca su link o pulsante per eseguire azione
   *  $indice: indice progressivo dei cursori presenti nel contenuto della pagina (parte da 1)
   *  $pos: numero di posizioni di far scorrere il cursore (+ a destra, - a sinistra)
   *
   * @When scorri cursore di :pos posizione
   * @When scorri cursore di :pos posizioni
   * @When scorri cursore :indice di :pos posizione
   * @When scorri cursore :indice di :pos posizioni
   */
  public function scorreCursore($indice=1, $pos): void {
    $sliders = $this->session->getPage()->findAll('css', 'form div.slider');
    $this->assertNotEmpty($sliders[$indice - 1]);
    $handle = $sliders[$indice - 1]->find('css', '.min-slider-handle');
    $this->assertNotEmpty($handle);
    $vmin = $handle->getAttribute('aria-valuemin');
    $vmax = $handle->getAttribute('aria-valuemax');
    $val = $handle->getAttribute('aria-valuenow') - $vmin + $pos;
    $val = ($val < 0 ? 0 : ($val > ($vmax - $vmin) ? ($vmax - $vmin) : $val));
    $element = $sliders[$indice - 1]->find('css', '.slider-tick-container > .slider-tick:nth-child('.($val + 1).')');
    $this->assertNotEmpty($element);
    $element->click();
  }

  /**
   * Controlla che il valore impostato nel campo del form sia uguale a quello indicato
   *  $campo: campo del form identificato tramite attributo id|name|label
   *  $valore: testo o valore presente nel campo del form
   *
   * @Then il campo :campo contiene :valore
   */
  public function campoContiene($campo, $valore): void {
    $field = $this->session->getPage()->findField($campo);
    if (!$field || !$field->isVisible()) {
      $labels = $this->session->getPage()->findAll('css', 'label:contains("'.$campo.'")');
      foreach ($labels as $lab) {
        if (!$lab->isVisible()) {
          continue;
        }
        $id = $lab->getAttribute('for');
        $field = $this->session->getPage()->find('css', '*[id="'.$id.'"]');
        if ($field) {
          break;
        }
      }
    }
    $this->assertTrue($field && strtolower($field->getValue()) == strtolower($valore));
  }

  /**
   * Controlla che il valore impostato nel campo del form sia diverso da quello indicato
   *  $campo: campo del form identificato tramite attributo id|name|label
   *  $valore: testo o valore del campo del form
   *
   * @Then il campo :campo non contiene :valore
   */
  public function campoNonContiene($campo, $valore): void {
    $field = $this->session->getPage()->findField($campo);
    if (!$field || !$field->isVisible()) {
      $labels = $this->session->getPage()->findAll('css', 'label:contains("'.$campo.'")');
      foreach ($labels as $lab) {
        if (!$lab->isVisible()) {
          continue;
        }
        $id = $lab->getAttribute('for');
        $field = $this->session->getPage()->find('css', '*[id="'.$id.'"]');
        if ($field) {
          break;
        }
      }
    }
    $this->assertTrue($field && strtolower($field->getValue()) != strtolower($valore));
  }


  //==================== METODI PROTETTI DELLA CLASSE ====================

  /**
   * Controlla che l'URL indicata corrisponda alla pagina corrente o lancia un'eccezione
   *
   * @param string $url Indirizzo da controllare
   * @param string $message Messaggio di errore
   */
  protected function assertPageUrl($url, $message=null): void {
    $current = $this->session->getCurrentUrl();
    if (strpos($current, '?') !== false) {
      $current = substr($current, 0, strpos($current, '?'));
    }
    if ($url != $current) {
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

  /**
   * Restituisce una lista con le intestazioni e i dati della tabella
   * NB: viene gestito COLSPAN e ROWSPAN, ma non la presenza di entrambi su stessa cella
   *
   * @param NodeElement $table Tabella da cui estrarre i dati
   *
   * @return array Lista con intestazione e dati della tabella
   */
  protected function parseTable($table): array {
    // intestazione (considera solo prima riga)
    $header = $this->parseTableRow($table->findAll('xpath', '/thead/tr[1]/th'));
    $this->assertNotEmpty($header);
    // contenuto
    $bodyRows = $table->findAll('xpath', '/tbody/tr');
    $body = [];
    $rowspan = [];
    foreach ($bodyRows as $bodyRow) {
      $row = $this->parseTableRow($bodyRow->findAll('xpath', '/td'), $rowspan);
      $this->assertTrue(count($header) == count($row));
      $body[] = $row;
    }
    return array($header, $body);
  }

  /**
   * Restituisce un vettore con i dati presenti nella riga della tabella
   * NB: viene gestito COLSPAN e ROWSPAN, ma non la presenza di entrambi su stessa cella
   *
   * @param array $cellList Lista delle celle della tabella
   * @param array $rowspan Conserva indicazioni di ROWSPAN (valori modificati da funzione)
   *
   * @return array Lista dei valori presenti nelle celle
   */
  protected function parseTableRow($cellList, &$rowspan=[]): array {
    $row = [];
    $col = 0;
    foreach ($cellList as $cell) {
      while (isset($rowspan[$col]) && $rowspan[$col]['num']) {
        // replica contenuto colonna
        $row[$col] = $rowspan[$col]['text'];
        $rowspan[$col]['num']--;
        if ($rowspan[$col]['num'] == 0) {
          unset($rowspan[$col]);
        }
        $col++;
      }
      $text = trim(preg_replace('/\s+/', ' ', $cell->getText()));
      if ($cell->hasAttribute('rowspan')) {
        $rspan = (int) $cell->getAttribute('rowspan');
        if ($rspan > 1) {
          $rowspan[$col]['text'] = $text;
          $rowspan[$col]['num'] = $rspan - 1;
        }
      }
      $row[$col++] = $text;
      if ($cell->hasAttribute('colspan')) {
        $colspan = (int) $cell->getAttribute('colspan');
        for ($i = 1; $i < $colspan; $i++) {
          // replica contenuto colonna
          $row[$col++] = $text;
        }
      }
    }
    return $row;
  }

public function test($p):self {
  $this->logDebug($p);
  $this->logDebug("SIIIIIIIIIIIIIIIIIIIIIIIIIIIIIIIIIIIIIIIIIIIIIIIIII");
  return $this;
}

}
