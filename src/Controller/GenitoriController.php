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

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Filesystem\Filesystem;
use App\Entity\RichiestaColloquio;
use App\Entity\Alunno;
use App\Entity\Genitore;
use App\Entity\Assenza;
use App\Entity\Entrata;
use App\Entity\Scrutinio;
use App\Util\GenitoriUtil;
use App\Util\RegistroUtil;
use App\Util\BachecaUtil;
use App\Util\AgendaUtil;
use App\Util\PdfManager;
use App\Util\LogHandler;
use App\Form\MessageType;


/**
 * GenitoriController - funzioni per i genitori
 */
class GenitoriController extends AbstractController {

  /**
   * Mostra lezioni svolte
   *
   * @param EntityManagerInterface $em Gestore delle entità
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
   *    defaults={"data": "0000-00-00"},
   *    methods={"GET"})
   *
   * @Security("is_granted('ROLE_GENITORE') or is_granted('ROLE_ALUNNO')")
   */
  public function lezioniAction(EntityManagerInterface $em, SessionInterface $session, TranslatorInterface $trans,
                                 GenitoriUtil $gen, RegistroUtil $reg, $data) {
    // inizializza variabili
    $lista_festivi = null;
    $errore = null;
    $dati = null;
    $info = null;
    $settimana = ['Domenica', 'Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato'];
    $mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    $data_succ = null;
    $data_prec = null;
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
    if ($this->getUser() instanceOf Alunno) {
      // utente è alunno
      $alunno = $this->getUser();
    } else {
      // utente è genitore
      $alunno = $gen->alunno($this->getUser());
      if (!$alunno) {
        // errore
        throw $this->createNotFoundException('exception.invalid_params');
      }
    }
    // legge la classe (può essere null)
    $classe = $alunno->getClasse();
    // data in formato stringa
    $formatter = new \IntlDateFormatter('it_IT', \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $info['data_label'] =  $formatter->format($data_obj);
    if ($classe) {
      // data prec/succ
      $data_succ = (clone $data_obj);
      $data_succ = $em->getRepository('App:Festivita')->giornoSuccessivo($data_succ);
      if ($data_succ && $data_succ->format('Y-m-d') > (new \DateTime())->format('Y-m-d')) {
        $data_succ = null;
      }
      $data_prec = (clone $data_obj);
      $data_prec = $em->getRepository('App:Festivita')->giornoPrecedente($data_prec);
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
      $errore = $trans->trans('exception.genitori_classe_nulla', ['sex' => $alunno->getSesso() == 'M' ? 'o' : 'a']);
      $lista_festivi = '[]';
    }
    // visualizza pagina
    return $this->render('ruolo_genitore/lezioni.html.twig', array(
      'pagina_titolo' => 'page.genitori_lezioni',
      'alunno' => $alunno,
      'classe' => $classe,
      'data' => $data_obj->format('Y-m-d'),
      'data_succ' => $data_succ,
      'data_prec' => $data_prec,
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
   *    defaults={"idmateria": 0},
   *    methods={"GET"})
   *
   * @Security("is_granted('ROLE_GENITORE') or is_granted('ROLE_ALUNNO')")
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
      $materia = $em->getRepository('App:Materia')->find($idmateria);
      if (!$materia) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
    } else {
      $materia = null;
    }
    // legge l'alunno
    if ($this->getUser() instanceOf Alunno) {
      // utente è alunno
      $alunno = $this->getUser();
    } else {
      // utente è genitore
      $alunno = $gen->alunno($this->getUser());
      if (!$alunno) {
        // errore
        throw $this->createNotFoundException('exception.invalid_params');
      }
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
      $errore = $trans->trans('exception.genitori_classe_nulla', ['sex' => $alunno->getSesso() == 'M' ? 'o' : 'a']);
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
   *    defaults={"idmateria": 0},
   *    methods={"GET"})
   *
   * @Security("is_granted('ROLE_GENITORE') or is_granted('ROLE_ALUNNO')")
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
      $materia = $em->getRepository('App:Materia')->find($idmateria);
      if (!$materia) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
    } else {
      $materia = null;
    }
    // legge l'alunno
    if ($this->getUser() instanceOf Alunno) {
      // utente è alunno
      $alunno = $this->getUser();
    } else {
      // utente è genitore
      $alunno = $gen->alunno($this->getUser());
      if (!$alunno) {
        // errore
        throw $this->createNotFoundException('exception.invalid_params');
      }
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
      $errore = $trans->trans('exception.genitori_classe_nulla', ['sex' => $alunno->getSesso() == 'M' ? 'o' : 'a']);
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
   * @param int $posizione Posizione per lo scrolling verticale della finestra
   *
   * @return Response Pagina di risposta
   *
   * @Route("/genitori/assenze/{posizione}", name="genitori_assenze",
   *    requirements={"posizione": "\d+"},
   *    defaults={"posizione": 0},
   *    methods={"GET"})
   *
   * @Security("is_granted('ROLE_GENITORE') or is_granted('ROLE_ALUNNO')")
   */
  public function assenzeAction(TranslatorInterface $trans, GenitoriUtil $gen, RegistroUtil $reg, $posizione) {
    // inizializza variabili
    $errore = null;
    $dati = null;
    // legge l'alunno
    if ($this->getUser() instanceOf Alunno) {
      // utente è alunno
      $alunno = $this->getUser();
    } else {
      // utente è genitore
      $alunno = $gen->alunno($this->getUser());
      if (!$alunno) {
        // errore
        throw $this->createNotFoundException('exception.invalid_params');
      }
    }
    // legge la classe (può essere null)
    $classe = $reg->classeInData(new \DateTime(), $alunno);
    if ($classe) {
      // recupera dati
      $dati = $gen->assenze($classe, $alunno);
      $dati['giustifica'] = $gen->giusticazioneOnline($this->getUser());
    } else {
      // nessuna classe
      $errore = $trans->trans('exception.genitori_classe_nulla', ['sex' => $alunno->getSesso() == 'M' ? 'o' : 'a']);
    }
    // visualizza pagina
    return $this->render('ruolo_genitore/assenze.html.twig', array(
      'pagina_titolo' => 'page.genitori_assenze',
      'alunno' => $alunno,
      'classe' => $classe,
      'errore' => $errore,
      'dati' => $dati,
      'posizione' => $posizione,
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
   * @Route("/genitori/note/", name="genitori_note",
   *    methods={"GET"})
   *
   * @Security("is_granted('ROLE_GENITORE') or is_granted('ROLE_ALUNNO')")
   */
  public function noteAction(TranslatorInterface $trans, GenitoriUtil $gen, RegistroUtil $reg) {
    // inizializza variabili
    $errore = null;
    $dati = null;
    // legge l'alunno
    if ($this->getUser() instanceOf Alunno) {
      // utente è alunno
      $alunno = $this->getUser();
    } else {
      // utente è genitore
      $alunno = $gen->alunno($this->getUser());
      if (!$alunno) {
        // errore
        throw $this->createNotFoundException('exception.invalid_params');
      }
    }
    // legge la classe (può essere null)
    $classe = $reg->classeInData(new \DateTime(), $alunno);
    if ($classe) {
      // recupera dati
      $dati = $gen->note($classe, $alunno);
    } else {
      // nessuna classe
      $errore = $trans->trans('exception.genitori_classe_nulla', ['sex' => $alunno->getSesso() == 'M' ? 'o' : 'a']);
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
   * @Route("/genitori/osservazioni/", name="genitori_osservazioni",
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_GENITORE")
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
      $errore = $trans->trans('exception.genitori_classe_nulla', ['sex' => $alunno->getSesso() == 'M' ? 'o' : 'a']);
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

  /**
   * Mostra le pagelle dell'alunno.
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param GenitoriUtil $gen Funzioni di utilità per i genitori
   * @param $string $periodo Periodo dello scrutinio
   *
   * @return Response Pagina di risposta
   *
   * @Route("/genitori/pagelle/{periodo}", name="genitori_pagelle",
   *    requirements={"periodo": "A|P|S|F|E|1|2|0"},
   *    defaults={"periodo": "0"},
   *    methods={"GET"})
   *
   * @Security("is_granted('ROLE_GENITORE') or is_granted('ROLE_ALUNNO')")
   */
  public function pagelleAction(EntityManagerInterface $em, TranslatorInterface $trans, GenitoriUtil $gen,
                                $periodo) {
    // inizializza variabili
    $errore = null;
    $dati = array();
    $lista_periodi = null;
    $info = array();
    // legge l'alunno
    if ($this->getUser() instanceOf Alunno) {
      // utente è alunno
      $alunno = $this->getUser();
    } else {
      // utente è genitore
      $alunno = $gen->alunno($this->getUser());
      if (!$alunno) {
        // errore
        throw $this->createNotFoundException('exception.invalid_params');
      }
    }
    // legge la classe (può essere null)
    $classe = $alunno->getClasse();
    // legge lista periodi
    $dati_periodi = $gen->pagelleAlunno($alunno);
    if (!empty($dati_periodi)) {
      // seleziona scrutinio indicato o ultimo
      $scrutinio = $dati_periodi[0][1];
      foreach ($dati_periodi as $per) {
        if ($per[0] == $periodo) {
          $scrutinio = $per[1];
          // periodo indicato è presente
          break;
        }
      }
      // lista periodi ammessi
      foreach ($dati_periodi as $per) {
        $lista_periodi[$per[0]] = ($per[1] instanceOf Scrutinio ? $per[1]->getStato() : 'C');
      }
      // visualizza pagella o lista periodi
      $periodo = null;
      if ($scrutinio) {
        // pagella
        $periodo = ($scrutinio instanceOf Scrutinio ? $scrutinio->getPeriodo() : 'A');
        $classe = $scrutinio->getClasse();
        if ($periodo == 'A') {
          // precedente A.S.
          $dati = $gen->pagellePrecedenti($alunno);
        } else {
          // altri periodi
          $dati = $gen->pagelle($classe, $alunno, $periodo);
        }
        // retrocompatibilità a.s. 21/22
        $info['giudizi']['P']['R'] = [20 => 'NC', 21 => 'Insufficiente', 22 => 'Sufficiente', 23 => 'Discreto', 24 => 'Buono', 25 => 'Distinto', 26 => 'Ottimo'];
        $info['giudizi']['S']['R'] = [20 => 'NC', 21 => 'Insufficiente', 22 => 'Sufficiente', 23 => 'Discreto', 24 => 'Buono', 25 => 'Distinto', 26 => 'Ottimo'];
        if (!in_array($periodo, ['P', 'S', 'A'])) {
          // legge dati valutazioni
          $dati['valutazioni'] = $em->getRepository('App:Scrutinio')
            ->findOneBy(['classe' => $classe, 'periodo' => $periodo, 'stato' => 'C'])
            ->getDato('valutazioni');
        }
      }
    } else {
      // nessun dato
      $errore = $trans->trans('exception.dati_non_presenti');
    }
    // visualizza pagina
    return $this->render('ruolo_genitore/pagelle.html.twig', array(
      'pagina_titolo' => 'page.genitori_pagelle',
      'alunno' => $alunno,
      'classe' => $classe,
      'errore' => $errore,
      'dati' => $dati,
      'info' => $info,
      'periodo' => $periodo,
      'lista_periodi' => $lista_periodi,
    ));
  }

  /**
   * Mostra le ore di colloquio dei docenti.
   *
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param GenitoriUtil $gen Funzioni di utilità per i genitori
   *
   * @return Response Pagina di risposta
   *
   * @Route("/genitori/colloqui", name="genitori_colloqui",
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_GENITORE")
   */
  public function colloquiAction(TranslatorInterface $trans, GenitoriUtil $gen) {
    // inizializza variabili
    $errore = null;
    $dati = null;
    $settimana = ['Domenica', 'Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato'];
    // legge l'alunno
    $alunno = $gen->alunno($this->getUser());
    if (!$alunno) {
      // errore
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // legge la classe (può essere null)
    $classe = $alunno->getClasse();
    if ($classe) {
      // recupera dati
      $dati = $gen->colloqui($classe, $alunno, $this->getUser());
    } else {
      // nessuna classe
      $errore = $trans->trans('exception.genitori_classe_nulla', ['sex' => $alunno->getSesso() == 'M' ? 'o' : 'a']);
    }
    // visualizza pagina
    return $this->render('ruolo_genitore/colloqui.html.twig', array(
      'pagina_titolo' => 'page.genitori_colloqui',
      'alunno' => $alunno,
      'classe' => $classe,
      'errore' => $errore,
      'dati' => $dati,
      'settimana' => $settimana,
    ));
  }

  /**
   * Invia la prenotazione per il colloquio con un docente.
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param GenitoriUtil $gen Funzioni di utilità per i genitori
   * @param LogHandler $dblogger Gestore dei log su database
   *
   * @return Response Pagina di risposta
   *
   * @Route("/genitori/colloqui/prenota/{colloquio}", name="genitori_colloqui_prenota")
   *    requirements={"colloquio": "\d+"},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_GENITORE")
   */
  public function colloquiPrenotaAction(Request $request, EntityManagerInterface $em, TranslatorInterface $trans,
                                        GenitoriUtil $gen, LogHandler $dblogger, $colloquio) {
    // inizializza variabili
    $dati['errore'] = null;
    $dati['lista'] = array();
    $label = array();
    // controlla colloquio
    $colloquio = $em->getRepository('App:Colloquio')->find($colloquio);
    if (!$colloquio) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // legge l'alunno
    $alunno = $gen->alunno($this->getUser());
    if (!$alunno) {
      // errore
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // legge la classe
    $classe = $alunno->getClasse();
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // lista date
    $dati = $gen->dateColloquio($colloquio);
    // info
    $label['docente'] = $colloquio->getDocente()->getCognome().' '.$colloquio->getDocente()->getNome();
    $label['materie'] = $gen->materieDocente($colloquio->getDocente(), $classe, $alunno);
    // form di inserimento
    $form = $this->container->get('form.factory')->createNamedBuilder('colloqui_prenota', FormType::class)
      ->add('data', ChoiceType::class, array('label' => 'label.data_colloquio',
        'choices' => $dati['lista'],
        'expanded' => true,
        'multiple' => false,
        'translation_domain' => false,
        'required' => true))
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start']))
      ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end',
        'onclick' => "location.href='".$this->generateUrl('genitori_colloqui')."'"]))
      ->getForm();
    $form->handleRequest($request);
    if (!$dati['errore'] && $form->isSubmitted() && $form->isValid()) {
      $data = explode('|', $form->get('data')->getData());
      $richiesta = $em->getRepository('App:RichiestaColloquio')->createQueryBuilder('rc')
        ->where('rc.colloquio=:colloquio AND rc.alunno=:alunno AND rc.appuntamento=:appuntamento AND rc.stato!=:stato')
        ->setParameters(['colloquio' => $colloquio, 'alunno' => $alunno,
          'appuntamento' => $data[0], 'stato' => 'A'])
        ->getQuery()
        ->getArrayResult();
      if (!empty($richiesta)) {
        // esiste già richiesta
        $form->addError(new FormError($trans->trans('exception.colloqui_esiste')));
      } else {
        // nuova richiesta
        $richiesta = (new RichiestaColloquio)
          ->setAppuntamento(\DateTime::createFromFormat('Y-m-d H:i', $data[0]))
          ->setDurata($data[1])
          ->setColloquio($colloquio)
          ->setAlunno($alunno)
          ->setStato('R')
          ->setGenitore($this->getUser());
        $em->persist($richiesta);
        // ok: memorizza dati
        $em->flush();
        // log azione
        $dblogger->logAzione('COLLOQUI', 'Richiesta colloquio', array(
          'ID' => $richiesta->getId()));
        // redirezione
        return $this->redirectToRoute('genitori_colloqui');
      }
    }
    // mostra la pagina di risposta
    return $this->render('ruolo_genitore/colloqui_prenota.html.twig', array(
      'pagina_titolo' => 'page.genitori_colloqui',
      'form' => $form->createView(),
      'form_title' => 'title.prenota_colloqui',
      'label' => $label,
      'errore' => $dati['errore'],
      'dati' => $dati,
    ));
  }

  /**
   * Invia la disdetta per la richiesta di colloquio con un docente.
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param GenitoriUtil $gen Funzioni di utilità per i genitori
   * @param LogHandler $dblogger Gestore dei log su database
   *
   * @return Response Pagina di risposta
   *
   * @Route("/genitori/colloqui/disdetta/{richiesta}", name="genitori_colloqui_disdetta")
   *    requirements={"richiesta": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_GENITORE")
   */
  public function colloquiDisdettaAction(Request $request, EntityManagerInterface $em, GenitoriUtil $gen,
                                         LogHandler $dblogger, $richiesta) {
    // legge l'alunno
    $alunno = $gen->alunno($this->getUser());
    if (!$alunno) {
      // errore
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // legge la classe
    $classe = $alunno->getClasse();
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // controlla richiesta
    $richiesta = $em->getRepository('App:RichiestaColloquio')->findOneBy(['id' => $richiesta, 'alunno' => $alunno]);
    if (!$richiesta) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // cancella richiesta
    $richiesta_old = clone $richiesta;
    $richiesta
      ->setStato('A')
      ->setMessaggio(null)
      ->setGenitoreAnnulla($this->getUser());
    // ok: memorizza dati
    $em->flush();
    // log azione
    $dblogger->logAzione('COLLOQUI', 'Disdetta colloquio', array(
      'ID' => $richiesta->getId(),
      'Stato' => $richiesta_old->getStato(),
      'Messaggio' => $richiesta_old->getMessaggio()));
    // redirezione
    return $this->redirectToRoute('genitori_colloqui');
  }

  /**
   * Visualizza gli avvisi destinati ai genitori
   *
   * @param Request $request Pagina richiesta
   * @param SessionInterface $session Gestore delle sessioni
   * @param BachecaUtil $bac Funzioni di utilità per la gestione della bacheca
   * @param int $pagina Numero di pagina per l'elenco da visualizzare
   *
   * @return Response Pagina di risposta
   *
   * @Route("/genitori/avvisi/{pagina}", name="genitori_avvisi",
   *    requirements={"pagina": "\d+"},
   *    defaults={"pagina": "0"},
   *    methods={"GET","POST"})
   *
   * @Security("is_granted('ROLE_GENITORE') or is_granted('ROLE_ALUNNO')")
   */
  public function avvisiAction(Request $request, SessionInterface $session, BachecaUtil $bac, $pagina) {
    // inizializza variabili
    $dati = null;
    $limite = 20;
    // recupera criteri dalla sessione
    $cerca = array();
    $cerca['visualizza'] = $session->get('/APP/ROUTE/genitori_avvisi/visualizza', 'T');
    $cerca['oggetto'] = $session->get('/APP/ROUTE/genitori_avvisi/oggetto', '');
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $session->get('/APP/ROUTE/genitori_avvisi/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $session->set('/APP/ROUTE/genitori_avvisi/pagina', $pagina);
    }
    // form di ricerca
    $form = $this->container->get('form.factory')->createNamedBuilder('bacheca_avvisi_genitori', FormType::class)
      ->add('visualizza', ChoiceType::class, array('label' => 'label.avvisi_filtro_visualizza',
        'data' => $cerca['visualizza'],
        'choices' => ['label.avvisi_da_leggere' => 'D', 'label.avvisi_tutti' => 'T'],
        'label_attr' => ['class' => 'sr-only'],
        'choice_attr' => function($val, $key, $index) {
            return ['class' => 'gs-no-placeholder'];
          },
        'attr' => ['class' => 'gs-placeholder'],
        'required' => true))
      ->add('oggetto', TextType::class, array('label' => 'label.avvisi_filtro_oggetto',
        'data' => $cerca['oggetto'],
        'attr' => ['placeholder' => 'label.oggetto', 'class' => 'gs-placeholder'],
        'label_attr' => ['class' => 'sr-only'],
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.search'))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $cerca['visualizza'] = $form->get('visualizza')->getData();
      $cerca['oggetto'] = $form->get('oggetto')->getData();
      $pagina = 1;
      $session->set('/APP/ROUTE/genitori_avvisi/visualizza', $cerca['visualizza']);
      $session->set('/APP/ROUTE/genitori_avvisi/oggetto', $cerca['oggetto']);
      $session->set('/APP/ROUTE/genitori_avvisi/pagina', $pagina);
    }
    // recupera dati
    $dati = $bac->bachecaAvvisi($cerca, $pagina, $limite, $this->getUser());
    // mostra la pagina di risposta
    return $this->render('bacheca/avvisi_genitori.html.twig', array(
      'pagina_titolo' => 'page.genitori_avvisi',
      'form' => $form->createView(),
      'form_help' => null,
      'form_success' => null,
      'page' => $pagina,
      'maxPages' => ceil($dati['lista']->count() / $limite),
      'dati' => $dati,
    ));
  }

  /**
   * Mostra i dettagli di un avviso destinato al genitore o all'alunno
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param BachecaUtil $bac Funzioni di utilità per la gestione della bacheca
   * @param int $id ID dell'avviso
   *
   * @return Response Pagina di risposta
   *
   * @Route("/genitori/avvisi/dettagli/{id}", name="genitori_avvisi_dettagli",
   *    requirements={"id": "\d+"},
   *    methods={"GET"})
   *
   * @Security("is_granted('ROLE_GENITORE') or is_granted('ROLE_ALUNNO')")
   */
  public function avvisiDettagliAction(EntityManagerInterface $em, BachecaUtil $bac, $id) {
    // inizializza
    $dati = null;
    $letto = null;
    // controllo avviso
    $avviso = $em->getRepository('App:Avviso')->find($id);
    if (!$avviso) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    if (!$bac->destinatario($avviso, $this->getUser(), $letto)) {
      // errore: non è destinatario dell'avviso
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // legge dati
    $dati = $bac->dettagliAvviso($avviso);
    // aggiorna lettura
    $bac->letturaAvviso($avviso, $this->getUser());
    // visualizza pagina
    return $this->render('bacheca/scheda_avviso_genitori.html.twig', array(
      'dati' => $dati,
      'letto' => $letto,
    ));
  }

  /**
   * Visualizza gli eventi destinati ai genitori o agli alunni
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param GenitoriUtil $gen Funzioni di utilità per i genitori
   * @param AgendaUtil $age Funzioni di utilità per la gestione dell'agenda
   * @param string $mese Anno e mese della pagina da visualizzare dell'agenda
   *
   * @return Response Pagina di risposta
   *
   * @Route("/genitori/eventi/{mese}", name="genitori_eventi",
   *    requirements={"mese": "\d\d\d\d-\d\d"},
   *    defaults={"mese": "0000-00"},
   *    methods={"GET"})
   *
   * @Security("is_granted('ROLE_GENITORE') or is_granted('ROLE_ALUNNO')")
   */
  public function eventiAction(EntityManagerInterface $em, SessionInterface $session, GenitoriUtil $gen,
                                AgendaUtil $age, $mese) {
    $dati = null;
    $info = null;
    // parametro data
    if ($mese == '0000-00') {
      // mese non specificato
      if ($session->get('/APP/ROUTE/genitori_eventi/mese')) {
        // recupera data da sessione
        $mese = \DateTime::createFromFormat('Y-m-d', $session->get('/APP/ROUTE/genitori_eventi/mese').'-01');
      } else {
        // imposta data odierna
        $mese = (new \DateTime())->modify('first day of this month');
      }
    } else {
      // imposta data indicata e la memorizza in sessione
      $mese = \DateTime::createFromFormat('Y-m-d', $mese.'-01');
      $session->set('/APP/ROUTE/genitori_eventi/mese', $mese->format('Y-m'));
    }
    // nome/url mese
    $formatter = new \IntlDateFormatter('it_IT', \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT);
    $formatter->setPattern('MMMM yyyy');
    $info['mese'] =  ucfirst($formatter->format($mese));
    // data prec/succ
    $data_inizio = \DateTime::createFromFormat('Y-m-d', $mese->format('Y-m-01'));
    $data_fine = clone $data_inizio;
    $data_fine->modify('last day of this month');
    $data_succ = (clone $data_fine);
    $data_succ = $em->getRepository('App:Festivita')->giornoSuccessivo($data_succ);
    $info['url_succ'] = ($data_succ ? $data_succ->format('Y-m') : null);
    $data_prec = (clone $data_inizio);
    $data_prec = $em->getRepository('App:Festivita')->giornoPrecedente($data_prec);
    $info['url_prec'] = ($data_prec ? $data_prec->format('Y-m') : null);
    // presentazione calendario
    $info['inizio'] = (intval($mese->format('w')) - 1);
    $m = clone $mese;
    $info['ultimo_giorno'] = $m->modify('last day of this month')->format('j');
    $info['fine'] = (intval($m->format('w')) == 0 ? 0 : 6 - intval($m->format('w')));
    // legge l'utente
    if ($this->getUser() instanceOf Alunno) {
      // utente è alunno
      $dati = $age->agendaEventiAlunni($this->getUser(), $mese);
    } else {
      // utente è genitore
      $alunno = $gen->alunno($this->getUser());
      if ($alunno) {
        // recupera dati
        $dati = $age->agendaEventiGenitori($this->getUser(), $alunno, $mese);
      }
    }
    // mostra la pagina di risposta
    return $this->render('agenda/eventi_genitori.html.twig', array(
      'pagina_titolo' => 'page.genitori_eventi',
      'mese' => $mese,
      'info' => $info,
      'dati' => $dati,
    ));
  }

  /**
   * Mostra i dettagli di un evento destinato ai genitori
   *
   * @param AgendaUtil $age Funzioni di utilità per la gestione dell'agenda
   * @param string $data Data dell'evento (AAAA-MM-GG)
   * @param string $tipo Tipo dell'evento
   *
   * @return Response Pagina di risposta
   *
   * @Route("/genitori/eventi/dettagli/{data}/{tipo}", name="genitori_eventi_dettagli",
   *    requirements={"data": "\d\d\d\d-\d\d-\d\d", "tipo": "C|A|V|P"},
   *    methods={"GET"})
   *
   * @Security("is_granted('ROLE_GENITORE') or is_granted('ROLE_ALUNNO')")
   */
  public function eventiDettagliAction(AgendaUtil $age, $data, $tipo) {
    // inizializza
    $dati = null;
    // data
    $data = \DateTime::createFromFormat('Y-m-d', $data);
    // legge dati
    if ($this->getUser() instanceOf Alunno) {
      // utente è alunno
      $dati = $age->dettagliEventoAlunno($this->getUser(), $data, $tipo);
    } else {
      // utente è genitore
      $dati = $age->dettagliEventoGenitore($this->getUser(), $this->getUser()->getAlunno(), $data, $tipo);
    }

    // visualizza pagina
    return $this->render('agenda/scheda_evento_genitori_'.$tipo.'.html.twig', array(
      'dati' => $dati,
      'data' => $data,
    ));
  }

  /**
   * Giustificazione online di un'assenza
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param PdfManager $pdf Gestore dei documenti PDF
   * @param GenitoriUtil $gen Funzioni di utilità per i genitori
   * @param LogHandler $dblogger Gestore dei log su database
   * @param Assenza $assenza Assenza da giustificare
   * @param int $posizione Posizione per lo scrolling verticale della finestra
   *
   * @return Response Pagina di risposta
   *
   * @Route("/genitori/giustifica/assenza/{assenza}/{posizione}", name="genitori_giustifica_assenza",
   *    requirements={"posizione": "\d+"},
   *    defaults={"posizione": 0},
   *    methods={"GET","POST"})
   *
   * @Security("is_granted('ROLE_GENITORE') or is_granted('ROLE_ALUNNO')")
   */
  public function giustificaAssenzaAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                          TranslatorInterface $trans, PdfManager $pdf, GenitoriUtil $gen,
                                          LogHandler $dblogger, Assenza $assenza, $posizione) {
    // inizializza
    $fs = new Filesystem();
    $info = array();
    $lista_motivazioni = array('label.giustifica_salute' => 1, 'label.giustifica_famiglia' => 2, 'label.giustifica_trasporto' => 3,
      'label.giustifica_sport' => 4, 'label.giustifica_connessione' => 5, 'label.giustifica_altro' => 9);
    // legge l'alunno
    if ($this->getUser() instanceOf Alunno) {
      // utente è alunno
      $alunno = $this->getUser();
    } else {
      // utente è genitore
      $alunno = $gen->alunno($this->getUser());
      if (!$alunno) {
        // errore
        throw $this->createNotFoundException('exception.invalid_params');
      }
    }
    // controlla assenza e possibilità di giustificare
    if ($assenza->getAlunno() !== $alunno || !$alunno->getAbilitato() || !$alunno->getClasse() ||
        !$gen->giusticazioneOnline($this->getUser()) || $assenza->getDocenteGiustifica()) {
      // errore
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // controlla permessi
    if (!$gen->azioneGiustifica($assenza->getData(), $alunno)) {
      // errore: azione non permessa
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // dati assenze
    if ($session->get('/CONFIG/SCUOLA/assenze_ore')) {
      // modalità assenze orarie
      $dati_assenze = $gen->raggruppaAssenzeOre($alunno);
    } else {
      // modalità assenze giornaliere
      $dati_assenze = $gen->raggruppaAssenze($alunno);
    }
    $data_str = $assenza->getData()->format('Y-m-d');
    $dich = null;
    foreach ($dati_assenze['gruppi'] as $per=>$ass) {
      foreach ($ass as $dt=>$a) {
        if ($dt == $data_str) {
          $info['assenza'] = $a['assenza'];
        }
        $dich = empty($dich) ? $a['assenza']['dichiarazione'] : $dich;
      }
    }
    if (!isset($info['assenza'])) {
      // errore: assenza non definita
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // dati in formato stringa
    $info['classe'] = $alunno->getClasse()->getAnno().'ª '.$alunno->getClasse()->getSezione();
    $info['alunno'] = $alunno->getCognome().' '.$alunno->getNome();
    // form
    $form = $this->container->get('form.factory')->createNamedBuilder('giustifica_assenza', FormType::class)
      ->setAction($this->generateUrl('genitori_giustifica_assenza', ['assenza' => $assenza->getId(), 'posizione' => $posizione]))
      ->add('tipo', ChoiceType::class, array('label' => 'label.motivazione_assenza',
        'choices' => $lista_motivazioni,
        'placeholder' => 'label.scelta_giustifica',
        'expanded' => false,
        'multiple' => false,
        'choice_attr' => function($val, $key, $index) {
            return ['class' => 'gs-no-placeholder'];
          },
        'attr' => ['class' => 'gs-placeholder'],
        'required' => false))
      ->add('motivazione', MessageType::class, array('label' => null,
        'data' => $info['assenza']['motivazione'],
        'trim' => true,
        'attr' => array('rows' => '3'),
        'required' => true));
    if ($session->get('/CONFIG/SCUOLA/assenze_dichiarazione')) {
      // dichiarazione NO-COVID
      $form = $form
        ->add('genitoreSesso', ChoiceType::class, array('label' => false,
          'data' => isset($info['assenza']['dichiarazione']['genitoreSesso']) ?
            $info['assenza']['dichiarazione']['genitoreSesso'] : (isset($dich['genitoreSesso']) ? $dich['genitoreSesso'] : null),
          'choices' => ['label.sottoscritto_M' => 'M', 'label.sottoscritto_F' => 'F'],
          'expanded' => false,
          'multiple' => false,
          'choice_attr' => function($val, $key, $index) {
              return ['class' => 'gs-no-placeholder'];
            },
          'attr' => ['class' => 'gs-placeholder gs-mr-3 gs-mb-2', 'style' => 'width:auto;display:inline;'],
          'required' => true))
        ->add('genitoreNome', TextType::class, array('label' => false,
          'data' => isset($info['assenza']['dichiarazione']['genitoreNome']) ?
            $info['assenza']['dichiarazione']['genitoreNome'] : (isset($dich['genitoreNome']) ? $dich['genitoreNome'] : null),
          'attr' => ['style' => 'width:auto;display:inline;', 'class' => 'gs-mr-3 gs-mb-2 gs-text-normal gs-strong',
            'placeholder' => $trans->trans('label.cognome_nome'), ],
          'required' => true))
        ->add('genitoreNascita', TextType::class, array('label' => 'label.data_nascita',
          'data' => (isset($info['assenza']['dichiarazione']['genitoreNascita']) && $info['assenza']['dichiarazione']['genitoreNascita']) ?
            $info['assenza']['dichiarazione']['genitoreNascita']->format('d/m/Y') :
            ((isset($dich['genitoreNascita']) && $dich['genitoreNascita']) ? $dich['genitoreNascita']->format('d/m/Y') : null),
          'attr' => ['style' => 'width:auto;display:inline;', 'class' => 'gs-mr-3 gs-mb-2 gs-text-normal gs-strong',
            'placeholder' => 'gg/mm/aaaa'],
          'required' => true))
        ->add('genitoreCitta', TextType::class, array('label' => false,
          'data' => isset($info['assenza']['dichiarazione']['genitoreCitta']) ?
            $info['assenza']['dichiarazione']['genitoreCitta'] : (isset($dich['genitoreCitta']) ? $dich['genitoreCitta'] : null),
          'attr' => ['style' => 'width:auto;display:inline;', 'class' => 'gs-mr-0 gs-mb-2 gs-text-normal gs-strong',
            'placeholder' => $trans->trans('label.luogo_nascita'), ],
          'required' => true))
        ->add('genitoreRuolo', ChoiceType::class, array('label' => false,
          'data' => isset($info['assenza']['dichiarazione']['genitoreRuolo']) ?
            $info['assenza']['dichiarazione']['genitoreRuolo'] : (isset($dich['genitoreRuolo']) ? $dich['genitoreRuolo'] : null),
          'choices' => ['label.genitore_ruolo_P' => 'P', 'label.genitore_ruolo_M' => 'M',
            'label.genitore_ruolo_T' => 'T'],
          'expanded' => false,
          'multiple' => false,
          'choice_attr' => function($val, $key, $index) {
              return ['class' => 'gs-no-placeholder'];
            },
          'attr' => ['class' => 'gs-placeholder gs-mr-3 gs-mb-2', 'style' => 'width:auto;display:inline;'],
          'required' => true))
        ->add('firma', CheckboxType::class, array('label' => 'label.sottoscrizione_dichiarazione_covid',
          'data' => $assenza->getGiustificato() != null,
          'label_attr' => ['class' => 'gs-big gs-strong'],
          'required' => true));
    }
    $form = $form
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' => ['class' => 'btn-primary']))
      ->add('delete', SubmitType::class, array('label' => 'label.delete',
        'attr' => ['class' => 'btn-danger']))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted()) {
      $errore = false;
      $motivazione = substr($form->get('motivazione')->getData(), 0, 255);
      if ($form->get('delete')->isClicked()) {
        // cancella dati
        $giustificato = null;
        $motivazione = null;
        $dichiarazione = array();
        $certificati = array();
      } elseif ($session->get('/CONFIG/SCUOLA/assenze_dichiarazione')) {
        // controlla campi
        $genitoreSesso = $form->get('genitoreSesso')->getData();
        $genitoreNome = strtoupper($form->get('genitoreNome')->getData());
        $genitoreNascita = \DateTime::createFromFormat('d/m/Y', $form->get('genitoreNascita')->getData());
        $genitoreCitta = strtoupper($form->get('genitoreCitta')->getData());
        $genitoreRuolo = $form->get('genitoreRuolo')->getData();
        $giustificato = null;
        $dichiarazione = array(
          'genitore' => ($this->getUser() instanceOf Genitore),
          'genitoreSesso' => $genitoreSesso,
          'genitoreNome' => $genitoreNome,
          'genitoreNascita' => $genitoreNascita,
          'genitoreCitta' => $genitoreCitta,
          'genitoreRuolo' => $genitoreRuolo);
        $certificati = array();
        if (empty($motivazione)) {
          // errore: motivazione assente
          $errore = true;
          $this->addFlash('errore', $trans->trans('exception.no_motivazione'));
        } elseif (($this->getUser() instanceOf Genitore) &&
            (empty($genitoreSesso) || empty($genitoreNome) || empty($genitoreCitta) || empty($genitoreRuolo))) {
          // errore: dichiarazione non compilata
          $errore = true;
          $this->addFlash('errore', $trans->trans('exception.dichiarazione_incompleta'));
        } elseif (($this->getUser() instanceOf Genitore) &&
            (empty($genitoreNascita) || $genitoreNascita->format('d/m/Y') != $form->get('genitoreNascita')->getData())) {
          // errore: data nascita non valida
          $errore = true;
          $this->addFlash('errore', $trans->trans('exception.data_invalida'));
        } elseif (!$form->get('firma')->getData()) {
          // errore: niente firma
          $errore = true;
          $this->addFlash('errore', $trans->trans('exception.no_firma_dichiarazione'));
        } else {
          // dati validi
          $giustificato = new \DateTime();
          // id documento
          $id_documento = 'AUTODICHIARAZIONE-'.$alunno->getId().'-'.$assenza->getId();
          // percorso PDF
          $percorso = $this->getParameter('dir_classi').'/'.
            $alunno->getClasse()->getAnno().$alunno->getClasse()->getSezione().'/certificati';
          if (!$fs->exists($percorso)) {
            // crea directory
            $fs->mkdir($percorso, 0775);
          }
          // crea pdf
          $pdf->configure($session->get('/CONFIG/ISTITUTO/intestazione'),
            'Autodichiarazione assenze no COVID');
          // contenuto in formato HTML
          $html = $this->renderView('pdf/autodichiarazione_nocovid.html.twig', array(
            'alunno' => $alunno,
            'dichiarazione' => $dichiarazione,
            'assenza' => $info['assenza'],
            'giustificato' => $giustificato,
            'id' => $id_documento));
          $pdf->createFromHtml($html);
          // salva il documento
          $pdf->save($percorso.'/'.$id_documento.'.pdf');
        }
      } else {
        // no autodichiarazione
        $giustificato = new \DateTime();
        $dichiarazione = array();
        $certificati = array();
      }
      // aggiorna dati
      $risultato = $em->getRepository('App:Assenza')->createQueryBuilder('ass')
        ->update()
        ->set('ass.modificato', ':modificato')
        ->set('ass.giustificato', ':giustificato')
        ->set('ass.motivazione', ':motivazione')
        ->set('ass.dichiarazione', ':dichiarazione')
        ->set('ass.certificati', ':certificati')
        ->set('ass.utenteGiustifica', ':utente')
        ->where('ass.id in (:ids)')
        ->setParameters(['modificato' => new \DateTime(), 'giustificato' => $giustificato,
          'motivazione' => $motivazione, 'dichiarazione' => serialize($dichiarazione),
          'certificati' => serialize($certificati), 'utente' => $this->getUser(),
          'ids' => explode(',', $info['assenza']['ids'])])
        ->getQuery()
        ->getResult();
      // memorizza dati
      $em->flush();
      // log azione
      if ($form->get('delete')->isClicked()) {
        // eliminazione
        $dblogger->logAzione('ASSENZE', 'Elimina giustificazione online', array(
          'ID' => $info['assenza']['ids'],
          'Giustificato' => $info['assenza']['giustificato'],
          'Motivazione' => $info['assenza']['motivazione'],
          'Dichiarazione' => $info['assenza']['dichiarazione'],
          'Certificati' => $info['assenza']['certificati']));
      } elseif (!$errore) {
        // inserimento o modifica
        $dblogger->logAzione('ASSENZE', 'Giustificazione online', array(
          'ID' => $info['assenza']['ids'],
          'Giustificato' => $info['assenza']['giustificato'],
          'Motivazione' => $info['assenza']['motivazione'],
          'Dichiarazione' => $info['assenza']['dichiarazione'],
          'Certificati' => $info['assenza']['certificati']));
      }
      // redirezione
      return $this->redirectToRoute('genitori_assenze', ['posizione' => $posizione]);
    }
    // visualizza pagina
    return $this->render('ruolo_genitore/giustifica_assenza.html.twig', array(
      'info' => $info,
      'alunno' => $alunno,
      'form' => $form->createView(),
    ));
  }

  /**
   * Giustificazione online di un ritardo
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param GenitoriUtil $gen Funzioni di utilità per i genitori
   * @param LogHandler $dblogger Gestore dei log su database
   * @param Entrata $entrata Ritardo da giustificare
   * @param int $posizione Posizione per lo scrolling verticale della finestra
   *
   * @return Response Pagina di risposta
   *
   * @Route("/genitori/giustifica/ritardo/{entrata}/{posizione}", name="genitori_giustifica_ritardo",
   *    requirements={"posizione": "\d+"},
   *    defaults={"posizione": 0},
   *    methods={"GET","POST"})
   *
   * @Security("is_granted('ROLE_GENITORE') or is_granted('ROLE_ALUNNO')")
   */
  public function giustificaRitardoAction(Request $request, EntityManagerInterface $em, TranslatorInterface $trans,
                                          GenitoriUtil $gen, LogHandler $dblogger, Entrata $entrata,
                                          $posizione) {
    // inizializza
    $info = array();
    $lista_motivazioni = array('label.giustifica_salute' => 1, 'label.giustifica_famiglia' => 2, 'label.giustifica_trasporto' => 3, 'label.giustifica_sport' => 4, 'label.giustifica_altro' => 9);
    // legge l'alunno
    if ($this->getUser() instanceOf Alunno) {
      // utente è alunno
      $alunno = $this->getUser();
    } else {
      // utente è genitore
      $alunno = $gen->alunno($this->getUser());
      if (!$alunno) {
        // errore
        throw $this->createNotFoundException('exception.invalid_params');
      }
    }
    // controlla assenza e possibilità di giustificare
    if ($entrata->getAlunno() !== $alunno || !$alunno->getAbilitato() || !$alunno->getClasse() ||
        !$gen->giusticazioneOnline($this->getUser()) || $entrata->getDocenteGiustifica() ||
        $entrata->getRitardoBreve()) {
      // errore
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // controlla permessi
    if (!$gen->azioneGiustifica($entrata->getData(), $alunno)) {
      // errore: azione non permessa
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // dati in formato stringa
    $formatter = new \IntlDateFormatter('it_IT', \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $info['data'] =  $formatter->format($entrata->getData());
    $info['ora'] =  $entrata->getOra()->format('H:i');
    $info['classe'] = $alunno->getClasse()->getAnno().'ª '.$alunno->getClasse()->getSezione();
    $info['alunno'] = $alunno->getCognome().' '.$alunno->getNome();
    $info['ritardo'] = $entrata;
    // form
    $entrata_old = clone $entrata;
    $form = $this->container->get('form.factory')->createNamedBuilder('giustifica_ritardo', FormType::class, $entrata)
      ->setAction($this->generateUrl('genitori_giustifica_ritardo', ['entrata' => $entrata->getId(), 'posizione' => $posizione]))
      ->add('tipo', ChoiceType::class, array('label' => 'label.motivazione_ritardo',
        'choices' => $lista_motivazioni,
        'placeholder' => 'label.scelta_giustifica',
        'expanded' => false,
        'multiple' => false,
        'choice_attr' => function($val, $key, $index) {
            return ['class' => 'gs-no-placeholder'];
          },
        'attr' => ['class' => 'gs-placeholder'],
        'mapped' => false,
        'required' => false))
      ->add('motivazione', MessageType::class, array('label' => null,
        'trim' => true,
        'attr' => array('rows' => '3'),
        'required' => true))
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' => ['class' => 'btn-primary']))
      ->add('delete', SubmitType::class, array('label' => 'label.delete',
        'attr' => ['class' => 'btn-danger']))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted()) {
      if ($form->get('submit')->isClicked() && empty($form->get('motivazione')->getData())) {
        // errore: motivazione assente
        $this->addFlash('error', $trans->trans('exception.no_motivazione'));
      } else {
        // dati validi
        if ($form->get('delete')->isClicked()) {
          // cancella
          $entrata
            ->setMotivazione(null)
            ->setGiustificato(null);
        } else {
          // aggiorna dati
          $entrata
            ->setMotivazione(substr($form->get('motivazione')->getData(), 0, 255))
            ->setGiustificato(new \DateTime())
            ->setUtenteGiustifica($this->getUser());
        }
        // ok: memorizza dati
        $em->flush();
        // log azione
        if ($form->get('delete')->isClicked()) {
          // cancella
          $dblogger->logAzione('ASSENZE', 'Elimina giustificazione online', array(
            'Ritardo' => $entrata->getId(),
            'Motivazione' => $entrata_old->getMotivazione(),
            'Giustificato' => $entrata_old->getGiustificato() ? $entrata_old->getGiustificato()->format('Y-m-d') : null,
            ));
        } else {
          // inserisce o modifica
          $dblogger->logAzione('ASSENZE', 'Giustificazione online', array(
            'Ritardo' => $entrata->getId(),
            'Motivazione' => $entrata_old->getMotivazione(),
            'Giustificato' => $entrata_old->getGiustificato() ? $entrata_old->getGiustificato()->format('Y-m-d') : null,
            ));
        }
      }
      // redirezione
      return $this->redirectToRoute('genitori_assenze', ['posizione' => $posizione]);
    }
    // visualizza pagina
    return $this->render('ruolo_genitore/giustifica_ritardo.html.twig', array(
      'info' => $info,
      'form' => $form->createView(),
    ));
  }

  /**
   * Mostra le deroghe autorizzate per l'alunno.
   *
   * @param GenitoriUtil $gen Funzioni di utilità per i genitori
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   *
   * @return Response Pagina di risposta
   *
   * @Route("/genitori/deroghe/", name="genitori_deroghe",
   *    methods={"GET"})
   *
   * @Security("is_granted('ROLE_GENITORE') or is_granted('ROLE_ALUNNO')")
   */
  public function derogheAction(GenitoriUtil $gen, RegistroUtil $reg) {
    // legge l'alunno
    if ($this->getUser() instanceOf Alunno) {
      // utente è alunno
      $alunno = $this->getUser();
    } else {
      // utente è genitore
      $alunno = $gen->alunno($this->getUser());
      if (!$alunno) {
        // errore
        throw $this->createNotFoundException('exception.invalid_params');
      }
    }
    // legge la classe (può essere null)
    $classe = $reg->classeInData(new \DateTime(), $alunno);
    // visualizza pagina
    return $this->render('ruolo_genitore/deroghe.html.twig', array(
      'pagina_titolo' => 'page.genitori_deroghe',
      'alunno' => $alunno,
      'classe' => $classe,
    ));
  }

}
