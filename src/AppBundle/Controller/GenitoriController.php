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
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Translation\TranslatorInterface;
use AppBundle\Util\GenitoriUtil;
use AppBundle\Util\RegistroUtil;


/**
 * GenitoriController - funzioni per i genitori
 */
class GenitoriController extends Controller {

  /**
   * Mostra lezioni svolte
   *
   * @param SessionInterface $session Gestore delle sessioni
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param GenitoriUtil $gen Funzioni di utilità per i genitori
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param string $data Data del giorno da visualizzare (AAAA-MM-GG)
   *
   * @return Response Pagina di risposta
   *
   * @Route("/genitori/lezioni/{data}", name="genitori_lezioni",
   *    requirements={"data": "\d\d\d\d-\d\d-\d\d"},
   *    defaults={"data": "0000-00-00"})
   * @Method("GET")
   *
   * @Security("has_role('ROLE_GENITORE')")
   */
  public function lezioniAction(SessionInterface $session, TranslatorInterface $trans, GenitoriUtil $gen,
                                 RegistroUtil $reg, $data) {
    // inizializza variabili
    $lista_festivi = null;
    $errore = null;
    $dati = null;
    $info = null;
    $settimana = ['Domenica', 'Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato'];
    $mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    // parametro data
    if ($data == '0000-00-00') {
      // data non specificata
      if ($session->get('/APP/GENITORE/data_lezione')) {
        // recupera data da sessione
        $data_obj = \DateTime::createFromFormat('Y-m-d', $session->get('/APP/GENITORE/data_lezione'));
      } else {
        // imposta data odierna
        $data_obj = new \DateTime();
      }
    } else {
      // imposta data indicata e la memorizza in sessione
      $data_obj = \DateTime::createFromFormat('Y-m-d', $data);
      $session->set('/APP/GENITORE/data_lezione', $data);
    }
    // legge l'alunno
    $alunno = $gen->alunno($this->getUser());
    if (!$alunno) {
      // errore
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // legge la classe (può essere null)
    $classe = $reg->classeInData($data_obj, $alunno);
    // data in formato stringa
    $formatter = new \IntlDateFormatter('it_IT', \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $info['data_label'] =  $formatter->format($data_obj);
    if ($classe) {
      // recupera festivi per calendario
      $lista_festivi = $reg->listaFestivi($classe->getSede());
      // controllo data
      $errore = $reg->controlloData($data_obj, $classe->getSede());
      if (!$errore) {
        // non festivo: recupera dati
        $dati = $gen->lezioni($data_obj, $classe, $alunno);
      }
    } else {
      // nessuna classe
      $errore = $trans->trans('exception.genitori_classe_nulla_data', ['%sex%' => $alunno->getSesso() == 'M' ? 'o' : 'a']);
      $lista_festivi = '[]';
    }
    // visualizza pagina
    return $this->render('ruolo_genitore/lezioni.html.twig', array(
      'pagina_titolo' => 'page.genitori_lezioni',
      'alunno' => $alunno,
      'classe' => $classe,
      'data' => $data_obj->format('Y-m-d'),
      'settimana' => $settimana,
      'mesi' => $mesi,
      'errore' => $errore,
      'lista_festivi' => $lista_festivi,
      'info' => $info,
      'dati' => $dati,
    ));
  }

  /**
   * Mostra gli argomenti e le attività delle lezioni svolte.
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param GenitoriUtil $gen Funzioni di utilità per i genitori
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param int $idmateria Identificatore materia da visualizzare
   *
   * @return Response Pagina di risposta
   *
   * @Route("/genitori/argomenti/{idmateria}", name="genitori_argomenti",
   *    requirements={"idmateria": "\d+"},
   *    defaults={"idmateria": 0})
   *
   * @Method("GET")
   *
   * @Security("has_role('ROLE_GENITORE')")
   */
  public function argomentiAction(EntityManagerInterface $em, TranslatorInterface $trans, GenitoriUtil $gen,
                                   RegistroUtil $reg, $idmateria) {
    // inizializza variabili
    $template = 'ruolo_genitore/argomenti.html.twig';
    $errore = null;
    $materie = null;
    $info = null;
    $dati = null;
    // parametro materia
    if ($idmateria > 0) {
      $materia = $em->getRepository('AppBundle:Materia')->find($idmateria);
      if (!$materia) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
    } else {
      $materia = null;
    }
    // legge l'alunno
    $alunno = $gen->alunno($this->getUser());
    if (!$alunno) {
      // errore
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // legge la classe (può essere null)
    $classe = $reg->classeInData(new \DateTime(), $alunno);
    if ($classe) {
      // lista materie
      $materie = $gen->materie($classe, ($alunno->getBes() == 'H'));
      if ($materia && array_search($idmateria, array_column($materie, 'id')) !== false) {
        // materia indicate e presente in cattedre di classe
        $info['materia'] = $materia->getNome();
        // recupera dati
        if ($materia->getTipo() == 'S') {
          // sostegno
          $dati = $gen->argomentiSostegno($classe, $alunno);
          $template = 'ruolo_genitore/argomenti_sostegno.html.twig';
        } else {
          // materia curricolare
          $dati = $gen->argomenti($classe, $materia, $alunno);
        }
      } else {
        // materia non specificata o non presente in cattedre di classe
        $info['materia'] = $trans->trans('label.scelta_materia');
      }
    } else {
      // nessuna classe
      $errore = $trans->trans('exception.genitori_classe_nulla', ['%sex%' => $alunno->getSesso() == 'M' ? 'o' : 'a']);
    }
    // visualizza pagina
    return $this->render($template, array(
      'pagina_titolo' => 'page.genitori_argomenti',
      'idmateria' => $idmateria,
      'alunno' => $alunno,
      'classe' => $classe,
      'errore' => $errore,
      'materie' => $materie,
      'info' => $info,
      'dati' => $dati,
    ));
  }

  /**
   * Mostra le valutazioni dell'alunno.
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param GenitoriUtil $gen Funzioni di utilità per i genitori
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param int $idmateria Identificatore materia da visualizzare
   *
   * @return Response Pagina di risposta
   *
   * @Route("/genitori/voti/{idmateria}", name="genitori_voti",
   *    requirements={"idmateria": "\d+"},
   *    defaults={"idmateria": 0})
   *
   * @Method("GET")
   *
   * @Security("has_role('ROLE_GENITORE')")
   */
  public function votiAction(EntityManagerInterface $em, TranslatorInterface $trans, GenitoriUtil $gen,
                              RegistroUtil $reg, $idmateria) {
    // inizializza variabili
    $errore = null;
    $materie = null;
    $info = null;
    $dati = null;
    $template = 'ruolo_genitore/voti.html.twig';
    // parametro materia
    if ($idmateria > 0) {
      $materia = $em->getRepository('AppBundle:Materia')->find($idmateria);
      if (!$materia) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
    } else {
      $materia = null;
    }
    // legge l'alunno
    $alunno = $gen->alunno($this->getUser());
    if (!$alunno) {
      // errore
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // legge la classe (può essere null)
    $classe = $reg->classeInData(new \DateTime(), $alunno);
    if ($classe) {
      // lista materie
      $materie = $gen->materie($classe, false);
      $materie = array_merge(
        [array('id' => 0, 'nomeBreve' => $trans->trans('label.ogni_materia'))],
        $materie);
      if ($materia && array_search($idmateria, array_column($materie, 'id')) !== false) {
        // materia indicate e presente in cattedre di classe
        $info['materia'] = $materia->getNome();
        $template = 'ruolo_genitore/voti_materia.html.twig';
      } else {
        // materia non specificata o non presente in cattedre di classe
        $info['materia'] = $trans->trans('label.ogni_materia');
      }
      // recupera dati
      $dati = $gen->voti($classe, $materia, $alunno);
    } else {
      // nessuna classe
      $errore = $trans->trans('exception.genitori_classe_nulla', ['%sex%' => $alunno->getSesso() == 'M' ? 'o' : 'a']);
    }
    // visualizza pagina
    return $this->render($template, array(
      'pagina_titolo' => 'page.genitori_voti',
      'idmateria' => $idmateria,
      'alunno' => $alunno,
      'classe' => $classe,
      'errore' => $errore,
      'materie' => $materie,
      'info' => $info,
      'dati' => $dati,
    ));
  }

  /**
   * Mostra le assenze dell'alunno.
   *
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param GenitoriUtil $gen Funzioni di utilità per i genitori
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   *
   * @return Response Pagina di risposta
   *
   * @Route("/genitori/assenze/", name="genitori_assenze")
   * @Method("GET")
   *
   * @Security("has_role('ROLE_GENITORE')")
   */
  public function assenzeAction(TranslatorInterface $trans, GenitoriUtil $gen, RegistroUtil $reg) {
    // inizializza variabili
    $errore = null;
    $dati = null;
    // legge l'alunno
    $alunno = $gen->alunno($this->getUser());
    if (!$alunno) {
      // errore
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // legge la classe (può essere null)
    $classe = $reg->classeInData(new \DateTime(), $alunno);
    if ($classe) {
      // recupera dati
      $dati = $gen->assenze($classe, $alunno);
    } else {
      // nessuna classe
      $errore = $trans->trans('exception.genitori_classe_nulla', ['%sex%' => $alunno->getSesso() == 'M' ? 'o' : 'a']);
    }
    // visualizza pagina
    return $this->render('ruolo_genitore/assenze.html.twig', array(
      'pagina_titolo' => 'page.genitori_assenze',
      'alunno' => $alunno,
      'classe' => $classe,
      'errore' => $errore,
      'dati' => $dati,
    ));
  }

  /**
   * Mostra le note dell'alunno.
   *
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param GenitoriUtil $gen Funzioni di utilità per i genitori
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   *
   * @return Response Pagina di risposta
   *
   * @Route("/genitori/note/", name="genitori_note")
   * @Method("GET")
   *
   * @Security("has_role('ROLE_GENITORE')")
   */
  public function noteAction(TranslatorInterface $trans, GenitoriUtil $gen, RegistroUtil $reg) {
    // inizializza variabili
    $errore = null;
    $dati = null;
    // legge l'alunno
    $alunno = $gen->alunno($this->getUser());
    if (!$alunno) {
      // errore
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // legge la classe (può essere null)
    $classe = $reg->classeInData(new \DateTime(), $alunno);
    if ($classe) {
      // recupera dati
      $dati = $gen->note($classe, $alunno);
    } else {
      // nessuna classe
      $errore = $trans->trans('exception.genitori_classe_nulla', ['%sex%' => $alunno->getSesso() == 'M' ? 'o' : 'a']);
    }
    // visualizza pagina
    return $this->render('ruolo_genitore/note.html.twig', array(
      'pagina_titolo' => 'page.genitori_note',
      'alunno' => $alunno,
      'classe' => $classe,
      'errore' => $errore,
      'dati' => $dati,
    ));
  }

  /**
   * Mostra le osservazioni dei docenti.
   *
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param GenitoriUtil $gen Funzioni di utilità per i genitori
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   *
   * @return Response Pagina di risposta
   *
   * @Route("/genitori/osservazioni/", name="genitori_osservazioni")
   * @Method("GET")
   *
   * @Security("has_role('ROLE_GENITORE')")
   */
  public function osservazioniAction(TranslatorInterface $trans, GenitoriUtil $gen, RegistroUtil $reg) {
    // inizializza variabili
    $errore = null;
    $dati = null;
    // legge l'alunno
    $alunno = $gen->alunno($this->getUser());
    if (!$alunno) {
      // errore
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // legge la classe (può essere null)
    $classe = $reg->classeInData(new \DateTime(), $alunno);
    if ($classe) {
      // recupera dati
      $dati = $gen->osservazioni($alunno);
    } else {
      // nessuna classe
      $errore = $trans->trans('exception.genitori_classe_nulla', ['%sex%' => $alunno->getSesso() == 'M' ? 'o' : 'a']);
    }
    // visualizza pagina
    return $this->render('ruolo_genitore/osservazioni.html.twig', array(
      'pagina_titolo' => 'page.genitori_osservazioni',
      'alunno' => $alunno,
      'classe' => $classe,
      'errore' => $errore,
      'dati' => $dati,
    ));
  }

}

