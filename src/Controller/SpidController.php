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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\HeaderUtils;


/**
 * SpidController - gestione dell'autenticazione tramite identity provider SPID
 */
class SpidController extends AbstractController {


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
      'IdInfocert' => 'InfoCert S.p.A.',
      'IdIntesa' => 'IN.TE.S.A. S.p.A.',
      'IdLepida' => 'Lepida S.p.A.',
      'IdNamirial' => 'Namirial',
      'IdPoste' => 'Poste Italiane SpA',
      'IdSielte' => 'Sielte S.p.A.',
      'IdRegister' => 'Register.it S.p.A.',
      'IdTim' => 'TI Trust Technologies srl'];
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
  public function metadataAction(Request $request) {
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
   * @Route("/spid/login/{idp}", name="spid_login",
   *    methods={"GET"})
   */
  public function loginAction($idp) {
    $code = isset($this->idp[$idp]) ? $this->idp[$idp] : $idp;
    return $this->redirect('/spid-login.php?idp='.urlencode($code));
  }

  /**
   * Esegue il login sull'applicazione al termine dell'autenticazione SPID
   *
   * @Route("/spid/acs/{responseId}", name="spid_acs",
   *    methods={"GET"})
   */
  public function acsAction() {
    // la procedura viene eseguita nella classe SpidAuthenticator
  }

}
