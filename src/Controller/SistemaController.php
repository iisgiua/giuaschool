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

use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;


/**
 * SistemaController - gestione configurazione di sistema
 */
class SistemaController extends AbstractController {

  /**
   * Configurazione dei dati di sistema
   *
   * @return Response Pagina di risposta
   *
   * @Route("/sistema/", name="sistema",
   *    methods={"GET"})
   *
   * @Security("has_role('ROLE_AMMINISTRATORE')")
   */
  public function sistemaAction() {
    return $this->render('sistema/index.html.twig', array(
      'pagina_titolo' => 'page.sistema',
    ));
  }

}

