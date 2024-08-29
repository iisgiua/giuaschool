<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Controller;

use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


/**
 * SpidController - gestione dell'autenticazione tramite identity provider SPID
 *
 * @author Antonello Dessì
 */
class SpidController extends BaseController {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var array $idp Lista IDP per lo SPID
   */
  private $idp;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   */
  public function __construct() {
    // inizializza
    $this->idp = [
      'IdAruba' => 'ArubaPEC S.p.A.',
      'IdEtna' => 'EtnaHitech S.C.p.A.',
      'IdInfocert' => 'InfoCert S.p.A.',
      'IdLepida' => 'Lepida S.p.A.',
      'IdNamirial' => 'Namirial',
      'IdPoste' => 'Poste Italiane SpA',
      'IdSielte' => 'Sielte S.p.A.',
      'IdRegister' => 'Register.it S.p.A.',
      'IdTeamsystem' => 'TeamSystem s.p.a.',
      'IdTim' => 'TI Trust Technologies srl',
      'IdInfocamere' => 'InfoCamere S.C.p.A.',
      'IdIntesiGroup' => 'Intesi Group S.p.A.'];
    }

  /**
   * Invia il file XML con i metadata per il service provider SPID
   * NB: la url "/metadata" è un requisito SPID
   *
   * @param Request $request Pagina richiesta
   *
   * @return Response Pagina di risposta
   *
   * @Route("/metadata", name="spid_metadata",
   *    methods={"GET"})
   */
  public function metadata(Request $request): Response {
    if ($request->query->get('debug') == 'yes') {
      // rigenera metadata
      return $this->redirect('/spid/module.php/saml/sp/metadata.php/service');
    }
    // legge file metadata
    $xml = file_get_contents($this->getParameter('kernel.project_dir').'/config/metadata/registro-spid.xml');
    $response = new Response($xml);
    // invia metadata
    $response->headers->set('Content-Type', 'application/samlmetadata+xml');
    $disposition = HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, 'metadata.xml');
    $response->headers->set('Content-Disposition', $disposition);
    return $response;
  }

  /**
   * Inizia la procedura di login tramite SPID
   *
   * @param string $idp Nome idendificativo dell'Identity Provider
   *
   * @Route("/spid/login/{idp}", name="spid_login",
   *    methods={"GET"})
   */
  public function login(string $idp): Response {
    $code = $this->idp[$idp] ?? $idp;
    return $this->redirect('/spid-login.php?idp='.urlencode((string) $code));
  }

  /**
   * Esegue il login sull'applicazione al termine dell'autenticazione SPID
   *
   * @Route("/spid/acs/{responseId}", name="spid_acs",
   *    methods={"GET"})
   */
  public function acs() {
    // la procedura viene eseguita nella classe SpidAuthenticator
  }

}
