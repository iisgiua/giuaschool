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
Use Symfony\Component\HttpFoundation\Session\Attribute\NamespacedAttributeBag;
use App\Entity\Configurazione;


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


  //==================== METODI DELLA CLASSE ====================

  /**
   * Construttore
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   */
  public function __construct(EntityManagerInterface $em, SessionInterface $session) {
    $this->em = $em;
    $this->session = $session;
  }

  /**
   * Legge la configurazione relativa alla categoria indicata e la memorizza nella sessione
   *
   * @param string $categoria Categoria della configurazione
   */
  public function load($categoria) {
    $this->session->remove('/CONFIG/'.$categoria);
    $list = $this->em->getRepository('App:Configurazione')->findByCategoria($categoria);
    foreach ($list as $item) {
      $this->session->set('/CONFIG/'.$categoria.'/'.$item->getParametro(), $item->getValore());
    }
  }

  /**
   * Legge tutta la configurazione e la memorizza nella sessione
   */
  public function loadAll() {
    $this->session->remove('/CONFIG');
    $list = $this->em->getRepository('App:Configurazione')->findAll();
    foreach ($list as $item) {
      $this->session->set('/CONFIG/'.$item->getCategoria().'/'.$item->getParametro(), $item->getValore());
    }
  }

}

