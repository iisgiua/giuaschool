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


namespace AppBundle\Controller;

use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;


/**
 * InfoController - pagine informative
 */
class InfoController extends Controller {

  /**
   * Note legali
   *
   * @return Response Pagina di risposta
   *
   * @Route("/info/note-legali/", name="info_notelegali",
   *    methods={"GET"})
   */
  public function noteLegaliAction() {
    return $this->render('info/notelegali.html.twig', array(
      'pagina_titolo' => 'page.notelegali',
    ));
  }

  /**
   * Privacy
   *
   * @return Response Pagina di risposta
   *
   * @Route("/info/privacy/", name="info_privacy",
   *    methods={"GET"})
   */
  public function privacyAction() {
    return $this->render('info/privacy.html.twig', array(
      'pagina_titolo' => 'page.privacy',
    ));
  }




  /**
   * @Route("/temp/", name="temp",
   *    methods={"GET"})
   */
  public function tempAction() {
    return $this->render('temp.html.twig', array(
      'pagina_titolo' => 'page.privacy',
    ));
  }

}

