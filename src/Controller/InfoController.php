<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Controller;

use App\Util\ConfigLoader;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Routing\Annotation\Route;


/**
 * InfoController - pagine informative
 *
 * @author Antonello Dessì
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
