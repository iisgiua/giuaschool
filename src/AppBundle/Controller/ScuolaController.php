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


namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;


/**
 * ScuolaController - gestione dei dati della scuola
 */
class ScuolaController extends Controller {

  /**
   * Gestione dei dati della scuola
   *
   * @return Response Pagina di risposta
   *
   * @Route("/scuola/", name="scuola")
   * @Method("GET")
   *
   * @Security("has_role('ROLE_AMMINISTRATORE')")
   */
  public function scuolaAction() {
    return $this->render('scuola/index.html.twig', array(
      'pagina_titolo' => 'page.scuola',
    ));
  }

}

