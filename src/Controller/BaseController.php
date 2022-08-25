<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Controller;

use App\Entity\MenuOpzione;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;


/**
 * BaseController - funzioni di utilità per i controller
 *
 * @author Antonello Dessì
 */
class BaseController extends AbstractController {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var EntityManagerInterface $em Gestore delle entità
   */
  protected $em;

  /**
   * @var RequestStack $reqstack Gestore dello stack delle variabili globali
   */
  protected $reqstack;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param RequestStack $reqstack Gestore dello stack delle variabili globali
   */
  public function __construct(EntityManagerInterface $em, RequestStack $reqstack) {
    $this->em = $em;
    $this->reqstack = $reqstack;
  }

  /**
   * Visualizza una pagina HTML, creando la breadcrumb.
   *
   * @param string $categoria Categoria a cui appartiene la pagina
   * @param string $azione Azione svolta dalla pagina
   * @param array $dati Lista di dati tabellari da passare alla vista
   * @param array $info Lista di informazioni singole da passare alla vista
   * @param array $form Oggetto form e messaggi da passare alla vista
   *
   * @return Response Pagina di risposta
   */
  protected function renderHtml(string $categoria, string $azione, array $dati=[],
                                array $info=[], array $form=[]): Response {
    list($azione_principale) = explode('_', $azione);
    // legge breadcrumb
    $breadcrumb = $this->em->getRepository('App\Entity\MenuOpzione')->breadcrumb($categoria.'_'.$azione_principale,
      $this->getUser(), $this->reqstack);
    // restituisce vista
    $tema = $this->reqstack->getSession()->get('/APP/APP/tema', '');
    return $this->render($tema.'/'.$categoria.'/'.$azione.'.html.twig', array(
      'pagina_titolo' => 'page.'.$categoria.'.'.$azione_principale,
      'titolo' => 'title.'.$categoria.'.'.$azione,
      'breadcrumb' => $breadcrumb,
      'dati' => $dati,
      'info' => $info,
      'form' => $form,
    ));
  }

}
