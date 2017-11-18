<?php
/**
 * giua@school
 *
 * Copyright (c) 2017 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017
 */


namespace AppBundle\Util;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use AppBundle\Entity\Utente;
use AppBundle\Entity\Genitore;
use AppBundle\Entity\Classe;


/**
 * NotificheUtil - classe di utilità per le funzioni sulle notifiche
 */
class NotificheUtil {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var RouterInterface $router Gestore delle URL
   */
  private $router;

  /**
   * @var EntityManagerInterface $em Gestore delle entità
   */
  private $em;

  /**
   * @var TranslatorInterface $trans Gestore delle traduzioni
   */
  private $trans;

  /**
   * @var SessionInterface $session Gestore delle sessioni
   */
  private $session;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Construttore
   *
   * @param RouterInterface $router Gestore delle URL
   * @param EntityManagerInterface $em Gestore delle entità
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param SessionInterface $session Gestore delle sessioni
   */
  public function __construct(RouterInterface $router, EntityManagerInterface $em, TranslatorInterface $trans,
                               SessionInterface $session) {
    $this->router = $router;
    $this->em = $em;
    $this->trans = $trans;
    $this->session = $session;
  }

  /**
   * Restituisce le notifiche da mostrare in home
   *
   * @param Utente $utente Utente a cui sono destinate le notifiche
   *
   * @return array Dati restituiti come array associativo
   */
  public function notificheHome(Utente $utente) {
    $dati = null;
    if ($utente instanceof Genitore) {
      // notifiche per i genitori
      $alunno = $this->em->getRepository('AppBundle:Alunno')->createQueryBuilder('a')
        ->join('a.classe', 'c')
        ->join('AppBundle:Genitore', 'g', 'WHERE', 'a.id=g.alunno')
        ->where('g.id=:genitore AND a.abilitato=:abilitato AND g.abilitato=:abilitato')
        ->setParameters(['genitore' => $utente, 'abilitato' => 1])
        ->getQuery()
        ->getOneOrNullResult();
      if ($alunno) {
        // legge le annotazioni dalla data di oggi in poi
        $dati = $this->annotazioni(new \DateTime(), $alunno->getClasse());
      }
    }
    return $dati;
  }

  /**
   * Restituisce le annotazioni dalla data indicata in poi, relative alla classe indicata
   *
   * @param \DateTime $data Data del giorno di lezione
   * @param Classe $classe Classe della lezione
   *
   * @return array Dati restituiti come array associativo
   */
  public function annotazioni(\DateTime $data, Classe $classe) {
    // legge annotazioni
    $annotazioni = $this->em->getRepository('AppBundle:Annotazione')->createQueryBuilder('a')
      ->select('a.data,a.testo')
      ->where('a.data>=:data AND a.classe=:classe AND a.visibile=:visibile')
      ->orderBy('a.data', 'ASC')
      ->setParameters(['data' => $data->format('Y-m-d'), 'classe' => $classe, 'visibile' => 1])
      ->getQuery()
      ->getArrayResult();
    // restituisce dati
    return $annotazioni;
  }

}

