<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2022 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2022
 */


namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Util\ConfigLoader;


/**
 * InfoController - pagine informative
 */
class InfoController extends BaseController {

  /**
   * Note legali
   *
   * @param ConfigLoader $config Gestore della configurazione su database
   *
   * @return Response Pagina di risposta
   *
   * @Route("/info/note-legali/", name="info_noteLegali",
   *    methods={"GET"})
   */
  public function noteLegaliAction(ConfigLoader $config) {
    // carica configurazione di sistema
    $config->carica();
    return $this->renderHtml('info', 'noteLegali');
  }

  /**
   * Privacy
   *
   * @param ConfigLoader $config Gestore della configurazione su database
   *
   * @return Response Pagina di risposta
   *
   * @Route("/info/privacy/", name="info_privacy",
   *    methods={"GET"})
   */
  public function privacyAction(ConfigLoader $config) {
    // carica configurazione di sistema
    $config->carica();
    return $this->renderHtml('info', 'privacy');
  }

  /**
   * Cookie
   *
   * @param ConfigLoader $config Gestore della configurazione su database
   *
   * @return Response Pagina di risposta
   *
   * @Route("/info/cookie/", name="info_cookie",
   *    methods={"GET"})
   */
  public function cookieAction(ConfigLoader $config) {
    // carica configurazione di sistema
    $config->carica();
    return $this->renderHtml('info', 'cookie');
  }

  /**
   * Credits
   *
   * @param ConfigLoader $config Gestore della configurazione su database
   *
   * @return Response Pagina di risposta
   *
   * @Route("/info/credits/", name="info_credits",
   *    methods={"GET"})
   */
  public function creditsAction(ConfigLoader $config) {
    // carica configurazione di sistema
    $config->carica();
    return $this->renderHtml('info', 'credits');
  }

}
