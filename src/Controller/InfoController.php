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
use App\Util\ConfigLoader;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;


/**
 * InfoController - pagine informative
 */
class InfoController extends AbstractController {

  /**
   * Note legali
   *
   * @param ConfigLoader $config Gestore della configurazione su database
   *
   * @return Response Pagina di risposta
   *
   * @Route("/info/note-legali/", name="info_notelegali",
   *    methods={"GET"})
   */
  public function noteLegaliAction(ConfigLoader $config, EntityManagerInterface $em, SessionInterface $session) {

// =====================
    //-- $ruolo = ($this->getUser() ? $this->getUser()->getRoles()[0] : 'NESSUNO');
    //-- $main = $em->getRepository('App:Menu')->findOneBy(['ruolo' => $ruolo, 'selettore' => 'main']);
    //-- $dati = array();
    //-- $mega = false;
    //-- foreach ($main->getOpzioni() as $opt) {
      //-- $sub = null;
      //-- $suburl = null;
      //-- $disab = $opt->getDisabilitato();
      //-- $megasub = false;
      //-- if ($opt->getSottoMenu()) {
        //-- $megasub = $opt->getSottoMenu()->getMega();
        //-- $mega = ($mega || $megasub);
        //-- $sub = array();
        //-- $suburl = array();
        //-- $sub2 = null;
        //-- $sub2url = null;
        //-- foreach ($opt->getSottoMenu()->getOpzioni() as $subopt) {
          //-- if ($subopt->getSottoMenu()) {
            //-- $sub2 = array();
            //-- $sub2url = array();
            //-- foreach ($subopt->getSottoMenu()->getOpzioni() as $sub2opt) {
              //-- $sub2[] = array(
                //-- 'nome' => $sub2opt->getNome(),
                //-- 'desc' => $sub2opt->getDescrizione(),
                //-- 'url' => $sub2opt->getUrl(),
                //-- 'disab' => $sub2opt->getDisabilitato(),
                //-- 'icona' => $sub2opt->getIcona(),
                //-- 'sub' => null,
                //-- 'suburl' => null);
              //-- $sub2url[] = $sub2opt->getUrl();
            //-- }
          //-- }
          //-- $sub[] = array(
            //-- 'nome' => $subopt->getNome(),
            //-- 'desc' => $subopt->getDescrizione(),
            //-- 'url' => $subopt->getUrl(),
            //-- 'disab' => $subopt->getDisabilitato(),
            //-- 'icona' => $subopt->getIcona(),
            //-- 'sub' => $sub2,
            //-- 'suburl' => $sub2url);
          //-- $suburl[] = ($sub2 ? $sub2url : $subopt->getUrl());
        //-- }
        //-- // abilita funzioni specifiche
        //-- if ($ruolo == 'ROLE_ATA' && $opt->getSottoMenu()->getSelettore() == 'segreteria' &&
            //-- $this->getUser()->getSegreteria()) {
          //-- // abilita funzioni di segreteria per gli ATA
          //-- $disab = false;
        //-- } elseif ($ruolo == 'ROLE_DOCENTE' && $opt->getSottoMenu()->getSelettore() == 'coordinatore' &&
            //-- $session->get('/APP/DOCENTE/coordinatore')) {
          //-- // abilita funzioni di coordinatore per i docenti
          //-- $disab = false;
        //-- }
      //-- }
      //-- $dati['main']['opzioni'][] = array(
        //-- 'nome' => $opt->getNome(),
        //-- 'desc' => $opt->getDescrizione(),
        //-- 'url' => $opt->getUrl(),
        //-- 'disab' => $disab,
        //-- 'icona' => $opt->getIcona(),
        //-- 'mega' => $megasub,
        //-- 'sub' => $sub,
        //-- 'suburl' => $suburl
      //-- );
    //-- }
    //-- $dati['main']['nome'] = $main->getNome();
    //-- $dati['main']['desc'] = $main->getDescrizione();
    //-- $dati['main']['disab'] = $main->getDisabilitato();
    //-- $dati['main']['icona'] = $main->getIcona();
    //-- $dati['main']['mega'] = $mega;
// =====================


      // carica configurazione di sistema
    $config->loadAll();

  //-- $session->set('/CONFIG/MENU/main', $dati['main']);

    return $this->render('info/notelegali.html.twig', array(
      'pagina_titolo' => 'page.notelegali',
    ));
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
    $config->loadAll();
    return $this->render('info/privacy.html.twig', array(
      'pagina_titolo' => 'page.privacy',
    ));
  }





/**
 * @Route("/temp", name="temp",
 *    methods={"GET"})
 */
public function tempAction(EntityManagerInterface $em) {

//-- $scrutini = $em->getRepository('App:Scrutinio')->findBy(['periodo' => 'F',
  //-- 'stato' => 'C']);
//-- print("<pre>");
//-- print("CLASSE, COGNOME, NOME, ESITO\n");
//-- foreach ($scrutini as $scrut) {
  //-- $cessata_frequenza = $scrut->getDato('cessata_frequenza');
  //-- $alunni = $em->getRepository('App:Alunno')->findBy(['id' => $cessata_frequenza]);
  //-- foreach ($alunni as $alu) {
    //-- print($scrut->getClasse().", ".$alu->getCognome().", ".$alu->getNome().", NON SCRUTINATO per cessata frequenza\n");
  //-- }
  //-- $scrutinabili = ($scrut->getDato('scrutinabili') ?  $scrut->getDato('scrutinabili') : []);
  //-- $no_scrutinabili = ($scrut->getDato('no_scrutinabili') ? $scrut->getDato('no_scrutinabili') : []);
  //-- $alunni = $em->getRepository('App:Alunno')->findBy(['id' => $no_scrutinabili]);
  //-- foreach ($no_scrutinabili as $key => $noscr) {
    //-- if (!isset($scrutinabili[$key])) {
      //-- $alu = $em->getRepository('App:Alunno')->find($key);
      //-- print($scrut->getClasse().", ".$alu->getCognome().", ".$alu->getNome().", NON AMMESSO per superamento limite assenze\n");
    //-- }
  //-- }

//-- }
//-- print("</pre>");
//-- die;

  // carica configurazione di sistema
  return $this->render('info/privacy.html.twig', array(
    'pagina_titolo' => 'page.privacy',
  ));
}


}
