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

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormError;
use App\Entity\RichiestaColloquio;
use App\Entity\Alunno;
use App\Entity\Assenza;
use App\Entity\Entrata;
use App\Util\GenitoriUtil;
use App\Util\RegistroUtil;
use App\Util\BachecaUtil;
use App\Util\AgendaUtil;
use App\Util\LogHandler;


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
    $classe = $reg->classeInData($data_obj, $alunno);
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
      $errore = $trans->trans('exception.genitori_classe_nulla_data', ['sex' => $alunno->getSesso() == 'M' ? 'o' : 'a']);
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
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param GenitoriUtil $gen Funzioni di utilità per i genitori
   * @param $string $periodo Periodo dello scrutinio
   *
   * @return Response Pagina di risposta
   *
   * @Route("/genitori/pagelle/{periodo}", name="genitori_pagelle",
   *    requirements={"periodo": "P|S|F|I|1|2|0"},
   *    defaults={"periodo": "0"},
   *    methods={"GET"})
   *
   * @Security("is_granted('ROLE_GENITORE') or is_granted('ROLE_ALUNNO')")
   */
  public function pagelleAction(TranslatorInterface $trans, GenitoriUtil $gen,
                                 $periodo) {
    // inizializza variabili
    $errore = null;
    $dati = array();
    $lista_periodi = null;
    $info = array();
    $info['giudizi']['P']['R'] = [20 => 'NC', 21 => 'Insufficiente', 22 => 'Sufficiente', 23 => 'Buono', 24 => 'Distinto', 25 => 'Ottimo'];
    $info['giudizi']['1']['N'] = [30 => 'Non Classificato', 31 => 'Scarso', 32 => 'Insufficiente', 33 => 'Mediocre', 34 => 'Sufficiente', 35 => 'Discreto', 36 => 'Buono', 37 => 'Ottimo'];
    $info['giudizi']['1']['C'] = [40 => 'Non Classificata', 41 => 'Scorretta', 42 => 'Non sempre adeguata', 43 => 'Corretta'];
    $info['giudizi']['F']['R'] = [20 => 'NC', 21 => 'Insufficiente', 22 => 'Sufficiente', 23 => 'Buono', 24 => 'Distinto', 25 => 'Ottimo'];
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
    if ($classe) {
      // legge lista periodi
      $dati_periodi = $gen->pagelleAlunno($alunno);
      // seleziona scrutinio indicato o ultimo
      $scrutinio = (count($dati_periodi) > 0 ? $dati_periodi[0][1] : null);
      foreach ($dati_periodi as $per) {
        if ($per[0] == $periodo) {
          $scrutinio = $per[1];
          // periodo indicato è presente
          break;
        }
      }
      // lista periodi ammessi
      foreach ($dati_periodi as $per) {
        $lista_periodi[$per[0]] = $per[1]->getStato();
      }
      // visualizza pagella o lista periodi
      $periodo = null;
      if ($scrutinio) {
        // pagella
        $classe = $scrutinio->getClasse();
        $periodo = $scrutinio->getPeriodo();
        $dati = $gen->pagelle($classe, $alunno, $periodo);
      }
    } else {
      // nessuna classe
      $errore = $trans->trans('exception.genitori_classe_nulla', ['sex' => $alunno->getSesso() == 'M' ? 'o' : 'a']);
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
      $dati = $gen->colloqui($classe, $alunno);
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
   * @param GenitoriUtil $gen Funzioni di utilità per i genitori
   *
   * @return Response Pagina di risposta
   *
   * @Route("/genitori/colloqui/prenota/{colloquio}", name="genitori_colloqui_prenota")
   *    requirements={"colloquio": "\d+"},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_GENITORE")
   */
  public function colloquiPrenotaAction(Request $request, EntityManagerInterface $em, GenitoriUtil $gen,
                                         $colloquio) {
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
      $richiesta = $em->getRepository('App:RichiestaColloquio')->createQueryBuilder('rc')
        ->where('rc.colloquio=:colloquio AND rc.alunno=:alunno AND rc.data=:data AND rc.stato!=:stato')
        ->setParameters(['colloquio' => $colloquio, 'alunno' => $alunno,
          'data' => $form->get('data')->getData(), 'stato' => 'A'])
        ->getQuery()
        ->getArrayResult();
      if (!empty($richiesta)) {
        // esiste già richiesta
        $form->addError(new FormError($trans->trans('exception.colloqui_esiste')));
      } else {
        // nuova richiesta
        $richiesta = (new RichiestaColloquio)
          ->setData(\DateTime::createFromFormat('Y-m-d', $form->get('data')->getData()))
          ->setColloquio($colloquio)
          ->setAlunno($alunno)
          ->setStato('R');
        $em->persist($richiesta);
        // ok: memorizza dati
        $em->flush();
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
    ));
  }

  /**
   * Invia la disdetta per la richiesta di colloquio con un docente.
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param GenitoriUtil $gen Funzioni di utilità per i genitori
   *
   * @return Response Pagina di risposta
   *
   * @Route("/genitori/colloqui/disdetta/{richiesta}", name="genitori_colloqui_disdetta")
   *    requirements={"richiesta": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_GENITORE")
   */
  public function colloquiDisdettaAction(EntityManagerInterface $em, GenitoriUtil $gen, $richiesta) {
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
    $richiesta = $em->getRepository('App:RichiestaColloquio')->findBy(['id' => $richiesta, 'alunno' => $alunno]);
    if (empty($richiesta)) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // cancella richiesta
    $richiesta[0]
      ->setStato('A')
      ->setMessaggio(null);
    // ok: memorizza dati
    $em->flush();
    // redirezione
    return $this->redirectToRoute('genitori_colloqui');
  }

  /**
   * Visualizza gli avvisi destinati ai genitori
   *
   * @param SessionInterface $session Gestore delle sessioni
   * @param GenitoriUtil $gen Funzioni di utilità per i genitori
   * @param BachecaUtil $bac Funzioni di utilità per la gestione della bacheca
   * @param int $pagina Numero di pagina per l'elenco da visualizzare
   *
   * @return Response Pagina di risposta
   *
   * @Route("/genitori/avvisi/{pagina}", name="genitori_avvisi",
   *    requirements={"pagina": "\d+"},
   *    defaults={"pagina": "0"},
   *    methods={"GET"})
   *
   * @Security("is_granted('ROLE_GENITORE') or is_granted('ROLE_ALUNNO')")
   */
  public function avvisiAction(SessionInterface $session, GenitoriUtil $gen, BachecaUtil $bac, $pagina) {
    // inizializza variabili
    $dati = array();
    $dati['nuovi'] = array();
    $dati['lista'] = array();
    $maxPages = 1;
    $limite = 15;
    // recupera criteri dalla sessione
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $session->get('/APP/ROUTE/genitori_avvisi/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $session->set('/APP/ROUTE/genitori_avvisi/pagina', $pagina);
    }
    // dati accesso
    $ultimo_accesso = \DateTime::createFromFormat('d/m/Y H:i:s',
        ($session->get('/APP/UTENTE/ultimo_accesso') ? $session->get('/APP/UTENTE/ultimo_accesso') : '01/01/2018 00:00:00'));
    // legge l'alunno
    if ($this->getUser() instanceOf Alunno) {
      // utente è alunno
      $dati = $bac->bachecaAvvisiGenitoriAlunni($pagina, $limite, $this->getUser(), $ultimo_accesso);
      $maxPages = ceil($dati['lista']->count() / $limite);
    } else {
      // utente è genitore
      $alunno = $gen->alunno($this->getUser());
      if ($alunno) {
        $dati = $bac->bachecaAvvisiGenitori($pagina, $limite, $this->getUser(), $alunno, $ultimo_accesso);
        $maxPages = ceil($dati['lista']->count() / $limite);
      }
    }
    // mostra la pagina di risposta
    return $this->render('bacheca/avvisi_genitori.html.twig', array(
      'pagina_titolo' => 'page.genitori_avvisi',
      'page' => $pagina,
      'maxPages' => $maxPages,
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
    // visualizza pagina
    return $this->render('bacheca/scheda_avviso_genitori.html.twig', array(
      'dati' => $dati,
      'letto' => $letto,
    ));
  }

  /**
   * Conferma la lettura dell'avviso destinato ai genitori
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param BachecaUtil $bac Funzioni di utilità per la gestione della bacheca
   * @param int $id ID dell'avviso
   *
   * @return Response Pagina di risposta
   *
   * @Route("/genitori/avvisi/firma/{id}", name="genitori_avvisi_firma",
   *    requirements={"id": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_GENITORE")
   */
  public function avvisiFirmaAction(EntityManagerInterface $em, BachecaUtil $bac, $id) {
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
    // aggiorna firma
    if ($avviso->getDestinatariIndividuali() && !$letto) {
      $bac->letturaAvvisoGenitori($avviso, $this->getUser());
      // ok: memorizza dati
      $em->flush();
    }
    // redirect
    return $this->redirectToRoute('genitori_avvisi');
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
      $dati = $age->agendaEventiGenitoriAlunni($this->getUser(), $mese);
    } else {
      // utente è genitore
      $alunno = $gen->alunno($this->getUser());
      if ($alunno) {
        // recupera dati
        $dati = $age->agendaEventiGenitori($alunno, $mese);
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
  public function eventoDettagliAction(AgendaUtil $age, $data, $tipo) {
    // inizializza
    $dati = null;
    // data
    $data = \DateTime::createFromFormat('Y-m-d', $data);
    // legge dati
    if ($this->getUser() instanceOf Alunno) {
      // utente è alunno
      $dati = $age->dettagliEventoGenitoreAlunno($this->getUser(), $data, $tipo);
    } else {
      // utente è genitore
      $dati = $age->dettagliEventoGenitore($this->getUser()->getAlunno(), $data, $tipo);
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
  public function giustificaAssenzaAction(Request $request, EntityManagerInterface $em, GenitoriUtil $gen,
                                           LogHandler $dblogger, Assenza $assenza, $posizione) {
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
    // dati in formato stringa
    $formatter = new \IntlDateFormatter('it_IT', \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $info['data'] =  $formatter->format($assenza->getData());
    $info['classe'] = $alunno->getClasse()->getAnno().'ª '.$alunno->getClasse()->getSezione();
    $info['alunno'] = $alunno->getCognome().' '.$alunno->getNome();
    // form
    $assenza_old = clone $assenza;
    $form = $this->container->get('form.factory')->createNamedBuilder('giustifica_assenza', FormType::class, $assenza)
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
        'mapped' => false,
        'required' => true))
      ->add('motivazione', TextareaType::class, array('label' => null,
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
          $assenza
            ->setMotivazione(null)
            ->setGiustificato(null);
        } else {
          // aggiorna dati
          $assenza->setGiustificato(new \DateTime());
        }
        // ok: memorizza dati
        $em->flush();
        // log azione
        if ($form->get('delete')->isClicked()) {
          // cancella
          $dblogger->write($this->getUser(), $request->getClientIp(), 'ASSENZE', 'Elimina giustificazione online', __METHOD__, array(
            'Assenza' => $assenza->getId(),
            'Motivazione' => $assenza_old->getMotivazione(),
            'Giustificato' => $assenza_old->getGiustificato() ? $assenza_old->getGiustificato()->format('Y-m-d') : null,
            ));
        } else {
          // inserisce o modifica
          $dblogger->write($this->getUser(), $request->getClientIp(), 'ASSENZE', 'Giustificazione online', __METHOD__, array(
            'Assenza' => $assenza->getId(),
            'Motivazione' => $assenza_old->getMotivazione(),
            'Giustificato' => $assenza_old->getGiustificato() ? $assenza_old->getGiustificato()->format('Y-m-d') : null,
            ));
        }
      }
      // redirezione
      return $this->redirectToRoute('genitori_assenze', ['posizione' => $posizione]);
    }
    // visualizza pagina
    return $this->render('ruolo_genitore/giustifica_assenza.html.twig', array(
      'info' => $info,
      'form' => $form->createView(),
    ));
  }

  /**
   * Giustificazione online di un ritardo
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
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
  public function giustificaRitardoAction(Request $request, EntityManagerInterface $em, GenitoriUtil $gen,
                                           LogHandler $dblogger, Entrata $entrata, $posizione) {
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
        'required' => true))
      ->add('motivazione', TextareaType::class, array('label' => null,
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
          $entrata->setGiustificato(new \DateTime());
        }
        // ok: memorizza dati
        $em->flush();
        // log azione
        if ($form->get('delete')->isClicked()) {
          // cancella
          $dblogger->write($this->getUser(), $request->getClientIp(), 'ASSENZE', 'Elimina giustificazione online', __METHOD__, array(
            'Ritardo' => $entrata->getId(),
            'Motivazione' => $entrata_old->getMotivazione(),
            'Giustificato' => $entrata_old->getGiustificato() ? $entrata_old->getGiustificato()->format('Y-m-d') : null,
            ));
        } else {
          // inserisce o modifica
          $dblogger->write($this->getUser(), $request->getClientIp(), 'ASSENZE', 'Giustificazione online', __METHOD__, array(
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

}
