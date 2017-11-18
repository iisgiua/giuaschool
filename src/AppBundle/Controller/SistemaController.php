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
 * SistemaController - gestione configurazione di sistema
 */
class SistemaController extends Controller {

  /**
   * Configurazione dei dati di sistema
   *
   * @return Response Pagina di risposta
   *
   * @Route("/sistema/", name="sistema")
   * @Method("GET")
   *
   * @Security("has_role('ROLE_AMMINISTRATORE')")
   */
  public function sistemaAction() {
    return $this->render('sistema/index.html.twig', array(
      'pagina_titolo' => 'page.sistema',
    ));
  }

}

