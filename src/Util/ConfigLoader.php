<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2019 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2019
 */


namespace App\Util;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Attribute\NamespacedAttributeBag;
use Symfony\Component\Security\Core\Security;
use App\Entity\Configurazione;
use App\Entity\Istituto;
use App\Entity\Docente;
use App\Entity\Amministratore;


/**
 * ConfigLoader - classe di utilità per il caricamento dei parametri dal db nella sessione corrente
 */
class ConfigLoader {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var EntityManagerInterface $em Gestore delle entità
   */
  private $em;

  /**
   * @var SessionInterface $session Gestore delle sessioni
   */
  private $session;

  /**
   * @var Security $security Gestore dell'autenticazione degli utenti
   */
  private $security;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Construttore
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param Security $security Gestore dell'autenticazione degli utenti
   */
  public function __construct(EntityManagerInterface $em, SessionInterface $session,
                              Security $security) {
    $this->em = $em;
    $this->session = $session;
    $this->security = $security;
  }

  /**
   * Legge la configurazione relativa alla categoria indicata e la ricarica nella sessione
   *
   * @param string $categoria Categoria della configurazione
   */
  public function load($categoria) {
    // rimuove la configurazione esistente
    $this->session->remove('/CONFIG/'.$categoria);
    if ($categoria == 'ISTITUTO') {
      // carica dati dall'entità Istituto
      $this->caricaIstituto();
    } else {
      // carica dati dall'entità Configurazione
      $list = $this->em->getRepository('App:Configurazione')->findByCategoria($categoria);
      foreach ($list as $item) {
        $this->session->set('/CONFIG/'.$categoria.'/'.$item->getParametro(), $item->getValore());
      }
    }
  }

  /**
   * Legge tutta la configurazione e la memorizza nella sessione
   */
  public function loadAll() {
    // rimuove la configurazione esistente
    $this->session->remove('/CONFIG');
    // carica dati dall'entità Configurazione
    $list = $this->em->getRepository('App:Configurazione')->findAll();
    foreach ($list as $item) {
      $this->session->set('/CONFIG/'.$item->getCategoria().'/'.$item->getParametro(), $item->getValore());
    }
    // carica dati dell'utente
    $this->caricaUtente();
    // carica dati dall'entità Istituto
    $this->caricaIstituto();
    // carica i menu
    $this->caricaMenu();
    // carica il tema
    $this->caricaTema();
}

  /**
   * Carica la configurazione dall'entità Istituto
   */
  private function caricaIstituto() {
    $istituto = $this->em->getRepository('App:Istituto')->findAll();
    if (count($istituto) > 0) {
      $this->session->set('/CONFIG/ISTITUTO/tipo', $istituto[0]->getTipo());
      $this->session->set('/CONFIG/ISTITUTO/tipo_sigla', $istituto[0]->getTipoSigla());
      $this->session->set('/CONFIG/ISTITUTO/nome', $istituto[0]->getNome());
      $this->session->set('/CONFIG/ISTITUTO/nome_breve', $istituto[0]->getNomeBreve());
      $this->session->set('/CONFIG/ISTITUTO/intestazione', $istituto[0]->getIntestazione());
      $this->session->set('/CONFIG/ISTITUTO/intestazione_breve', $istituto[0]->getIntestazioneBreve());
      $this->session->set('/CONFIG/ISTITUTO/email', $istituto[0]->getEmail());
      $this->session->set('/CONFIG/ISTITUTO/pec', $istituto[0]->getPec());
      $this->session->set('/CONFIG/ISTITUTO/url_sito', $istituto[0]->getUrlSito());
      $this->session->set('/CONFIG/ISTITUTO/url_registro', $istituto[0]->getUrlRegistro());
      $this->session->set('/CONFIG/ISTITUTO/firma_preside', $istituto[0]->getFirmaPreside());
      $this->session->set('/CONFIG/ISTITUTO/email_amministratore', $istituto[0]->getEmailAmministratore());
      $this->session->set('/CONFIG/ISTITUTO/email_notifiche', $istituto[0]->getEmailNotifiche());
    }
  }

  /**
   * Carica la struttura dei menu visibili dall'utente collegato
   */
  private function caricaMenu() {
    // legge utente connesso (null se utente non autenticato)
    $utente = $this->security->getUser();
    // legge menu esistenti
    $lista_menu = $this->em->getRepository('App:Menu')->listaMenu();
    foreach ($lista_menu as $m) {
      $menu = $this->em->getRepository('App:Menu')->menu($m['selettore'], $utente, $this->session);
      $this->session->set('/CONFIG/MENU/'.$m['selettore'], $menu);
    }
  }

  /**
   * Carica il tema CSS per l'utente collegato
   */
  private function caricaTema() {
    $tema = '';
    // legge impostazione tema dell'utente connesso
    $utente = $this->security->getUser();
    //-- if ($utente && ($utente instanceOf Amministratore ||
        //-- (isset($utente->getNotifica()['tema']) && $utente->getNotifica()['tema'] == 'new'))) {
    if ($utente && ($utente instanceOf Amministratore)) {
      // imposta il nuovo tema
      $tema = 'tema-new';
    }
    // imposta tema
    $this->session->set('/APP/APP/tema', $tema);
  }

  /**
   * Carica nella sessione alcune informazioni sull'utente
   */
  private function caricaUtente() {
    // legge utente connesso
    $utente = $this->security->getUser();
    if ($utente instanceOf Docente) {
      // dati coordinatore
      $classi = $this->em->getRepository('App:Classe')->createQueryBuilder('c')
        ->select('c.id')
        ->where('c.coordinatore=:docente')
        ->setParameters(['docente' => $utente])
        ->getQuery()
        ->getArrayResult();
      $lista = implode(',', array_column($classi, 'id'));
      $this->session->set('/APP/DOCENTE/coordinatore', $lista);
    }
  }

}
