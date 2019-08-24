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
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;


/**
 * ScuolaController - gestione dei dati della scuola
 */
class ScuolaController extends AbstractController {

  /**
   * Gestione dei dati della scuola
   *
   * @return Response Pagina di risposta
   *
   * @Route("/scuola/", name="scuola",
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function scuolaAction() {
    return $this->render('scuola/index.html.twig', array(
      'pagina_titolo' => 'page.scuola',
    ));
  }

}
