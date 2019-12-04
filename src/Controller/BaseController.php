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


namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;


/**
 * BaseController - funzioni di utilità per i controller
 */
class BaseController extends AbstractController {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var EntityManagerInterface $em Gestore delle entità
   */
  private $em;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Construttore
   *
   * @param EntityManagerInterface $em Gestore delle entità
   */
  public function __construct(EntityManagerInterface $em) {
    $this->em = $em;
  }

  /**
   * Visualizza una pagina HTML, creando la breadcrumb.
   *
   * @param string $categoria Categoria a cui appartiene la pagina
   * @param string $azione Azione svolta dalla pagina
   * @param array $dati Lista di dati da passare alla vista
   * @param array $info Lista di informazioni da passare alla vista
   *
   * @return Response Pagina di risposta
   */
  protected function renderHtml(string $categoria, string $azione, array $dati=[], array $info=[]): Response {
    // legge breadcrumb
    $breadcrumb = $this->em->getRepository('App:MenuOpzione')->breadcrumb($categoria.'_'.$azione, $this->getUser());
    // restituisce vista
    $tema = $this->get('session')->get('/APP/APP/tema', '');
    return $this->render($tema.'/'.$categoria.'/'.$azione.'.html.twig', array(
      'pagina_titolo' => 'page.'.$categoria.'.'.$azione,
      'breadcrumb' => $breadcrumb,
      'dati' => $dati,
      'info' => $info,
    ));
  }

}
