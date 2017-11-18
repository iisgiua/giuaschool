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

use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use AppBundle\Util\RegistroUtil;


/**
 * StaffUtil - classe di utilità per le funzioni disponibili allo staff
 */
class StaffUtil {


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

  /**
   * @var RegistroUtil $regUtil Funzioni di utilità per il registro
   */
  private $regUtil;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Construttore
   *
   * @param RouterInterface $router Gestore delle URL
   * @param EntityManagerInterface $em Gestore delle entità
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param SessionInterface $session Gestore delle sessioni
   * @param RegistroUtil $regUtil Funzioni di utilità per il registro
   */
  public function __construct(RouterInterface $router, EntityManagerInterface $em, TranslatorInterface $trans,
                               SessionInterface $session, RegistroUtil $regUtil) {
    $this->router = $router;
    $this->em = $em;
    $this->trans = $trans;
    $this->session = $session;
    $this->regUtil = $regUtil;
  }

  /**
   * Restituisce dati degli alunni per la gestione dei ritardi e delle uscite
   *
   * @param \DateTime $inizio Data di inizio del periodo da considerare
   * @param \DateTime $fine Data di fine del periodo da considerare
   * @param Paginator $lista Lista degli alunni da considerare
   *
   * @return array Informazioni sui ritard/uscite come valori di array associativo
   */
  public function entrateUscite(\DateTime $inizio, \DateTime $fine, Paginator $lista) {
    $dati = array();
    // scansione della lista
    foreach ($lista as $a) {
      $alunno = array();
      $alunno['alunno_id'] = $a->getId();
      $alunno['nome'] = $a->getCognome().' '.$a->getNome().' ('.$a->getDataNascita()->format('d/m/Y').')';
      $alunno['classe_id'] = $a->getClasse()->getId();
      $alunno['classe'] = $a->getClasse()->getAnno().'ª '.$a->getClasse()->getSezione();
      // dati ritardi
      $entrate = $this->em->getRepository('AppBundle:Entrata')->createQueryBuilder('e')
        ->select('e.data,e.ora,e.note')
        ->where('e.valido=:valido AND e.alunno=:alunno AND e.data BETWEEN :inizio AND :fine')
        ->setParameters(['valido' => 1, 'alunno' => $a, 'inizio' => $inizio->format('Y-m-d'),
          'fine' => $fine->format('Y-m-d')])
        ->orderBy('e.data', 'DESC')
        ->getQuery()
        ->getArrayResult();
      $alunno['entrate'] = $entrate;
      // dati uscite
      $uscite = $this->em->getRepository('AppBundle:Uscita')->createQueryBuilder('u')
        ->select('u.data,u.ora,u.note')
        ->where('u.valido=:valido AND u.alunno=:alunno AND u.data BETWEEN :inizio AND :fine')
        ->setParameters(['valido' => 1, 'alunno' => $a, 'inizio' => $inizio->format('Y-m-d'),
          'fine' => $fine->format('Y-m-d')])
        ->orderBy('u.data', 'DESC')
        ->getQuery()
        ->getArrayResult();
      $alunno['uscite'] = $uscite;
      // aggiunge alunno
      $dati[] = $alunno;
    }
    // restituisce dati
    return $dati;
  }

}

