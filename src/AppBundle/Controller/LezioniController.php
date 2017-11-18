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

use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use AppBundle\Util\RegistroUtil;


/**
 * LezioniController - gestione delle lezioni
 */
class LezioniController extends Controller {

  /**
   * Gestione delle lezioni
   *
   * @param SessionInterface $session Gestore delle sessioni
   *
   * @return Response Pagina di risposta
   *
   * @Route("/lezioni/", name="lezioni")
   * @Method("GET")
   *
   * @Security("has_role('ROLE_DOCENTE')")
   */
  public function lezioniAction(SessionInterface $session) {
    if (!$session->get('/APP/DOCENTE/cattedra_lezione') && !$session->get('/APP/DOCENTE/classe_lezione')) {
      // scelta classe
      return $this->redirectToRoute('lezioni_classe');
    }
    if ($session->get('/APP/DOCENTE/menu_lezione')) {
      // vai all'ultima pagina visitata
      return $this->redirectToRoute($session->get('/APP/DOCENTE/menu_lezione')['name'], $session->get('/APP/DOCENTE/menu_lezione')['param']);
    } else {
      // vai al registro
      return $this->redirectToRoute('lezioni_registro_firme');
    }
  }

  /**
   * Gestione della scelta delle classi
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   *
   * @return Response Pagina di risposta
   *
   * @Route("/lezioni/classe/", name="lezioni_classe")
   * @Method("GET")
   *
   * @Security("has_role('ROLE_DOCENTE')")
   */
  public function classeAction(Request $request, EntityManagerInterface $em) {
    // lista cattedre
    $lista = $em->getRepository('AppBundle:Cattedra')->createQueryBuilder('c')
      ->join('c.classe', 'cl')
      ->join('c.materia', 'm')
      ->where('c.docente=:docente AND c.attiva=:attiva')
      ->orderBy('cl.sede,cl.anno,cl.sezione,m.nomeBreve', 'ASC')
      ->setParameters(['docente' => $this->getUser(), 'attiva' => 1])
      ->getQuery()
      ->getResult();
    // raggruppa per classi
    $cattedre = array();
    foreach ($lista as $c) {
      $cattedre[$c->getClasse()->getId()][] = $c;
    }
    // lista tutte le classi
    $lista = $em->getRepository('AppBundle:Classe')->createQueryBuilder('cl')
      ->orderBy('cl.sede,cl.sezione,cl.anno', 'ASC')
      ->getQuery()
      ->getResult();
    // raggruppa per sezione
    $classi = array();
    foreach ($lista as $c) {
      $classi[$c->getSezione()][] = $c;
    }
    // visualizza pagina
    return $this->render('lezioni/classe.html.twig', array(
      'pagina_titolo' => 'page.lezioni_classe',
      'cattedre' => $cattedre,
      'classi' => $classi,
    ));
  }

  /**
   * Mostra gli argomenti e le attività delle lezioni svolte.
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param SessionInterface $session Gestore delle sessioni
   * @param int $cattedra Identificativo della cattedra
   * @param int $classe Identificativo della classe (supplenza)
   *
   * @return Response Pagina di risposta
   *
   * @Route("/lezioni/argomenti/{cattedra}/{classe}", name="lezioni_argomenti",
   *    requirements={"cattedra": "\d+", "classe": "\d+"},
   *    defaults={"cattedra": 0, "classe": 0})
   * @Method("GET")
   *
   * @Security("has_role('ROLE_DOCENTE')")
   */
  public function argomentiAction(Request $request, EntityManagerInterface $em, SessionInterface $session, RegistroUtil $reg,
                                   $cattedra, $classe) {
    // inizializza variabili
    $info = null;
    $dati = null;
    $template = 'lezioni/argomenti.html.twig';
    // parametri cattedra/classe
    if ($cattedra == 0 && $classe == 0) {
      // recupera parametri da sessione
      $cattedra = $session->get('/APP/DOCENTE/cattedra_lezione');
      $classe = $session->get('/APP/DOCENTE/classe_lezione');
    } else {
      // memorizza su sessione
      $session->set('/APP/DOCENTE/cattedra_lezione', $cattedra);
      $session->set('/APP/DOCENTE/classe_lezione', $classe);
    }
    // controllo cattedra/supplenza
    if ($cattedra > 0) {
      // lezione in propria cattedra: controlla esistenza
      $cattedra = $em->getRepository('AppBundle:Cattedra')->findOneBy(['id' => $cattedra,
        'docente' => $this->getUser(), 'attiva' => 1]);
      if (!$cattedra) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      // informazioni necessarie
      $classe = $cattedra->getClasse();
      $template = 'lezioni/argomenti'.($cattedra->getTipo() == 'S' ? '_sostegno' : '').'.html.twig';
      $info['materia'] = $cattedra->getMateria()->getNomeBreve();
      $info['alunno'] = $cattedra->getAlunno();
    } elseif ($classe > 0) {
      // supplenza
      $classe = $em->getRepository('AppBundle:Classe')->find($classe);
      if (!$classe) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $materia = $em->getRepository('AppBundle:Materia')->findOneByTipo('U');
      if (!$materia) {
        // errore
        throw $this->createNotFoundException('exception.invalid_params');
      }
      // informazioni necessarie
      $cattedra = null;
      $info['materia'] = $materia->getNomeBreve();
      $info['alunno'] = null;
    }
    if ($cattedra) {
      // recupera dati
      $dati = $reg->argomenti($cattedra);
    }
    // salva pagina visitata
    $route = ['name' => $request->get('_route'), 'param' => $request->get('_route_params')];
    $session->set('/APP/DOCENTE/menu_lezione', $route);
    // visualizza pagina
    return $this->render($template, array(
      'pagina_titolo' => 'page.lezioni_argomenti',
      'cattedra' => $cattedra,
      'classe' => $classe,
      'info' => $info,
      'dati' => $dati,
    ));
  }

  /**
   * Mostra il riepilogo mensile delle lezioni svolte.
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param int $cattedra Identificativo della cattedra
   * @param string $data Data del giorno da visualizzare (AAAA-MM-GG)
   *
   * @return Response Pagina di risposta
   *
   * @Route("/lezioni/argomenti/riepilogo/{cattedra}/{data}", name="lezioni_argomenti_riepilogo",
   *    requirements={"cattedra": "\d+", "data": "\d\d\d\d-\d\d-\d\d"},
   *    defaults={"data": "0000-00-00"})
   * @Method("GET")
   *
   * @Security("has_role('ROLE_DOCENTE')")
   */
  public function argomentiRiepilogoAction(EntityManagerInterface $em, SessionInterface $session,
                                            RegistroUtil $reg, $cattedra, $data) {
    // inizializza variabili
    $dati = null;
    $info = null;
    $template = 'lezioni/argomenti_riepilogo.html.twig';
    // parametro data
    if ($data == '0000-00-00') {
      // data non specificata
      if ($session->get('/APP/DOCENTE/data_lezione')) {
        // recupera data da sessione
        $data_obj = \DateTime::createFromFormat('Y-m-d', $session->get('/APP/DOCENTE/data_lezione'));
      } else {
        // imposta data odierna
        $data_obj = new \DateTime();
      }
    } else {
      // imposta data indicata (non la memorizza)
      $data_obj = \DateTime::createFromFormat('Y-m-d', $data);
    }
    // data in formato stringa
    $formatter = new \IntlDateFormatter('it_IT', \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT);
    $formatter->setPattern('MMMM yyyy');
    $info['data_label'] =  $formatter->format($data_obj);
    // lezione in propria cattedra: controlla esistenza
    $cattedra = $em->getRepository('AppBundle:Cattedra')->findOneBy(['id' => $cattedra,
      'docente' => $this->getUser(), 'attiva' => 1]);
    if (!$cattedra) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // informazioni necessarie
    $classe = $cattedra->getClasse();
    $template = 'lezioni/argomenti_riepilogo'.($cattedra->getTipo() == 'S' ? '_sostegno' : '').'.html.twig';
    $info['materia'] = $cattedra->getMateria()->getNomeBreve();
    $info['religione'] = ($cattedra->getMateria()->getTipo() == 'R');
    $info['alunno'] = $cattedra->getAlunno();
    // recupera dati
    $dati = $reg->riepilogo($data_obj, $cattedra);
    // visualizza pagina
    return $this->render($template, array(
      'pagina_titolo' => 'page.lezioni_riepilogo',
      'cattedra' => $cattedra,
      'classe' => $classe,
      'data' => $data_obj->format('Y-m-d'),
      'info' => $info,
      'dati' => $dati,
    ));
  }

}

