<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use App\Entity\OsservazioneAlunno;
use App\Entity\OsservazioneClasse;
use App\Entity\Cattedra;
use App\Entity\Classe;
use App\Entity\Festivita;
use App\Util\LogHandler;
use App\Util\RegistroUtil;
use App\Form\MessageType;


/**
 * OsservazioniController - gestione delle osservazioni sugli alunni
 *
 * @author Antonello Dessì
 */
class OsservazioniController extends AbstractController {

  /**
   * Gestione delle osservazioni sugli alunni
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param RequestStack $reqstack Gestore dello stack delle variabili globali
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param int $cattedra Identificativo della cattedra
   * @param int $classe Identificativo della classe (supplenza)
   * @param string $data Data del giorno da visualizzare (AAAA-MM-GG)
   *
   * @return Response Pagina di risposta
   *
   * @Route("/lezioni/osservazioni/{cattedra}/{classe}/{data}", name="lezioni_osservazioni",
   *    requirements={"cattedra": "\d+", "classe": "\d+", "data": "\d\d\d\d-\d\d-\d\d"},
   *    defaults={"cattedra": 0, "classe": 0, "data": "0000-00-00"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function osservazioniAction(Request $request, EntityManagerInterface $em, RequestStack $reqstack,
                                      RegistroUtil $reg, $cattedra, $classe, $data) {
    // inizializza variabili
    $lista_festivi = null;
    $errore = null;
    $settimana = ['Domenica', 'Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato'];
    $mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    $info = array();
    $info['sostegno'] = false;
    $dati = null;
    $template = 'lezioni/osservazioni.html.twig';
    $data_succ = null;
    $data_prec = null;
    // parametri cattedra/classe
    if ($cattedra == 0 && $classe == 0) {
      // recupera parametri da sessione
      $cattedra = $reqstack->getSession()->get('/APP/DOCENTE/cattedra_lezione');
      $classe = $reqstack->getSession()->get('/APP/DOCENTE/classe_lezione');
    } else {
      // memorizza su sessione
      $reqstack->getSession()->set('/APP/DOCENTE/cattedra_lezione', $cattedra);
      $reqstack->getSession()->set('/APP/DOCENTE/classe_lezione', $classe);
    }
    // parametro data
    if ($data == '0000-00-00') {
      // data non specificata
      if ($reqstack->getSession()->get('/APP/DOCENTE/data_lezione')) {
        // recupera data da sessione
        $data_obj = \DateTime::createFromFormat('Y-m-d', $reqstack->getSession()->get('/APP/DOCENTE/data_lezione'));
      } else {
        // imposta data odierna
        $data_obj = new \DateTime();
      }
    } else {
      // imposta data indicata e la memorizza in sessione
      $data_obj = \DateTime::createFromFormat('Y-m-d', $data);
      $reqstack->getSession()->set('/APP/DOCENTE/data_lezione', $data);
    }
    // data in formato stringa
    $formatter = new \IntlDateFormatter('it_IT', \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $info['data_label'] =  $formatter->format($data_obj);
    // controllo cattedra/supplenza
    if ($cattedra > 0) {
      // lezione in propria cattedra: controlla esistenza
      $cattedra = $em->getRepository('App\Entity\Cattedra')->findOneBy(['id' => $cattedra,
        'docente' => $this->getUser(), 'attiva' => 1]);
      if (!$cattedra) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      // informazioni necessarie
      $classe = $cattedra->getClasse();
      $info['materia'] = $cattedra->getMateria()->getNomeBreve();
      $info['alunno'] = $cattedra->getAlunno();
    } elseif ($classe > 0) {
      // supplenza
      $classe = $em->getRepository('App\Entity\Classe')->find($classe);
      if (!$classe) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      // informazioni necessarie
      $cattedra = null;
    }
    if ($cattedra) {
      // recupera dati
      if ($cattedra->getMateria()->getTipo() == 'S') {
        $dati = $reg->osservazioniSostegno($data_obj, $this->getUser(), $cattedra);
        $info['sostegno'] = true;
        $template = 'lezioni/osservazioni_sostegno.html.twig';
      } else {
        $dati = $reg->osservazioni($data_obj, $this->getUser(), $cattedra);
      }
      // data prec/succ
      $data_succ = (clone $data_obj);
      $data_succ = $em->getRepository('App\Entity\Festivita')->giornoSuccessivo($data_succ);
      $data_prec = (clone $data_obj);
      $data_prec = $em->getRepository('App\Entity\Festivita')->giornoPrecedente($data_prec);
      // recupera festivi per calendario
      $lista_festivi = $reg->listaFestivi($classe->getSede());
      // controllo data
      $errore = $reg->controlloData($data_obj, $classe->getSede());
      if ($errore) {
        unset($dati['add']);
      }
    }
    // salva pagina visitata
    $route = ['name' => $request->get('_route'), 'param' => $request->get('_route_params')];
    $reqstack->getSession()->set('/APP/DOCENTE/menu_lezione', $route);
    // visualizza pagina
    return $this->render($template, array(
      'pagina_titolo' => 'page.lezioni_osservazioni',
      'cattedra' => $cattedra,
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
   * Aggiunge o modifica un'osservazione su un alunno
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $cattedra Identificativo della cattedra
   * @param string $data Data del giorno
   * @param int $id Identificativo dell'osservazione (se nullo aggiunge)
   *
   * @return Response Pagina di risposta
   *
   * @Route("/lezioni/osservazioni/edit/{cattedra}/{data}/{id}", name="lezioni_osservazioni_edit",
   *    requirements={"cattedra": "\d+", "data": "\d\d\d\d-\d\d-\d\d", "id": "\d+"},
   *    defaults={"id": 0},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function osservazioneEditAction(Request $request, EntityManagerInterface $em, RegistroUtil $reg,
                                          LogHandler $dblogger, $cattedra, $data, $id) {
    // inizializza
    $label = array();
    // controlla cattedra
    $cattedra = $em->getRepository('App\Entity\Cattedra')->findOneBy(['id' => $cattedra,
      'docente' => $this->getUser(), 'attiva' => 1]);
    if (!$cattedra) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla data
    $data_obj = \DateTime::createFromFormat('Y-m-d', $data);
    $errore = $reg->controlloData($data_obj, $cattedra->getClasse()->getSede());
    if ($errore) {
      // errore: festivo
      throw $this->createNotFoundException('exception.invalid_params');
    }
    if ($id > 0) {
      // azione edit, controlla ossservazione
      $osservazione = $em->getRepository('App\Entity\OsservazioneAlunno')->findOneBy(['id' => $id,
        'data' => $data_obj]);
      if (!$osservazione) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $osservazione_old['testo'] = $osservazione->getTesto();
      $osservazione_old['alunno'] = $osservazione->getAlunno();
      $osservazione_old['cattedra'] = $osservazione->getCattedra();
      $osservazione
        ->setCattedra($cattedra);
    } else {
      // azione add
      $osservazione = (new OsservazioneAlunno())
        ->setData($data_obj)
        ->setCattedra($cattedra);
      if ($cattedra->getMateria()->getTipo() == 'S') {
        // cattedra di sostegno
        if ($cattedra->getAlunno()) {
          $osservazione->setAlunno($cattedra->getAlunno());
        }
      }
    }
    // controlla permessi
    if (!$reg->azioneOsservazione(($id > 0 ? 'edit' : 'add'), $data_obj, $this->getUser(),
                                   $cattedra->getClasse(), ($id > 0 ? $osservazione : null))) {
      // errore: azione non permessa
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // dati in formato stringa
    $formatter = new \IntlDateFormatter('it_IT', \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $label['data'] =  $formatter->format($data_obj);
    $label['docente'] = $this->getUser()->getNome().' '.$this->getUser()->getCognome();
    $label['classe'] = $cattedra->getClasse()->getAnno()."ª ".$cattedra->getClasse()->getSezione();
    // form di inserimento
    $religione = ($cattedra->getMateria()->getTipo() == 'R' && $cattedra->getTipo() == 'A') ? 'A' :
      ($cattedra->getMateria()->getTipo() == 'R' ? 'S' : '');
    $form = $this->container->get('form.factory')->createNamedBuilder('osservazione_edit', FormType::class, $osservazione)
      ->add('alunno', EntityType::class, array('label' => 'label.alunno',
        'class' => 'App\Entity\Alunno',
        'choice_label' => function ($obj) {
            return $obj->getCognome().' '.$obj->getNome().' ('.$obj->getDataNascita()->format('d/m/Y').')';
          },
        'query_builder' => function (EntityRepository $er) use ($cattedra,$religione) {
            return $er->createQueryBuilder('a')
              ->where('a.classe=:classe and a.abilitato=:abilitato'.
                ($religione ? " and a.religione='".$religione."'" : ''))
              ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
              ->setParameters(['classe' => $cattedra->getClasse(), 'abilitato' => 1]);
          },
        'expanded' => true,
        'multiple' => false,
        'label_attr' => ['class' => 'gs-pt-1 checkbox-split-vertical'],
        'required' => true))
      ->add('testo', MessageType::class, array('label' => 'label.testo',
        'trim' => true,
        'required' => true))
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start']))
      ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end',
        'onclick' => "location.href='".$this->generateUrl('lezioni_osservazioni')."'"]))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      if (!$id) {
        // nuovo
        $em->persist($osservazione);
      }
      // ok: memorizza dati
      $em->flush();
      // log azione
      if (!$id) {
        // nuovo
        $dblogger->logAzione('REGISTRO', 'Crea osservazione', array(
          'Id' => $osservazione->getId()
          ));
      } else {
        // modifica
        $dblogger->logAzione('REGISTRO', 'Modifica osservazione', array(
          'Id' => $osservazione->getId(),
          'Testo' => $osservazione_old['testo'],
          'Alunno' => $osservazione_old['alunno']->getId(),
          'Cattedra' => $osservazione_old['cattedra']->getId()
          ));
      }
      // redirezione
      return $this->redirectToRoute('lezioni_osservazioni');
    }
    // mostra la pagina di risposta
    return $this->render('lezioni/osservazione_edit.html.twig', array(
      'pagina_titolo' => 'page.lezioni_osservazioni',
      'form' => $form->createView(),
      'form_title' => ($id > 0 ? 'title.modifica_osservazione' : 'title.nuova_osservazione'),
      'label' => $label,
      'alunno' => $cattedra->getAlunno()
    ));
  }

  /**
   * Cancella un'osservazione su un alunno
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $id Identificativo dell'osservazione
   *
   * @return Response Pagina di risposta
   *
   * @Route("/lezioni/osservazioni/delete/{id}", name="lezioni_osservazioni_delete",
   *    requirements={"id": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function osservazioneDeleteAction(Request $request, EntityManagerInterface $em, RegistroUtil $reg,
                                            LogHandler $dblogger, $id) {
    // controlla osservazione
    $osservazione = $em->getRepository('App\Entity\OsservazioneAlunno')->find($id);
    if (!$osservazione) {
      // non esiste, niente da fare
      return $this->redirectToRoute('lezioni_osservazioni');
    }
    // controlla permessi
    if (!$reg->azioneOsservazione('delete', $osservazione->getData(), $this->getUser(),
                                  $osservazione->getCattedra()->getClasse(), $osservazione)) {
      // errore: azione non permessa
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // cancella osservazione
    $osservazione_id = $osservazione->getId();
    $em->remove($osservazione);
    // ok: memorizza dati
    $em->flush();
    // log azione
    $dblogger->logAzione('REGISTRO', 'Cancella osservazione', array(
      'Osservazione' => $osservazione_id,
      'Cattedra' => $osservazione->getCattedra()->getId(),
      'Alunno' => $osservazione->getAlunno()->getId(),
      'Data' => $osservazione->getData()->format('Y-m-d'),
      'Testo' => $osservazione->getTesto(),
      ));
    // redirezione
    return $this->redirectToRoute('lezioni_osservazioni');
  }

  /**
   * Gestione delle osservazioni personali
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param RequestStack $reqstack Gestore dello stack delle variabili globali
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param int $cattedra Identificativo della cattedra
   * @param int $classe Identificativo della classe (supplenza)
   * @param string $data Data del giorno da visualizzare (AAAA-MM-GG)
   *
   * @return Response Pagina di risposta
   *
   * @Route("/lezioni/osservazioni/personali/{cattedra}/{classe}/{data}", name="lezioni_osservazioni_personali",
   *    requirements={"cattedra": "\d+", "classe": "\d+", "data": "\d\d\d\d-\d\d-\d\d"},
   *    defaults={"cattedra": 0, "classe": 0, "data": "0000-00-00"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function osservazioniPersonaliAction(Request $request, EntityManagerInterface $em, RequestStack $reqstack,
                                               RegistroUtil $reg, $cattedra, $classe, $data) {
    // inizializza variabili
    $lista_festivi = null;
    $errore = null;
    $settimana = ['Domenica', 'Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato'];
    $mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    $info = null;
    $dati = null;
    // parametri cattedra/classe
    if ($cattedra == 0 && $classe == 0) {
      // recupera parametri da sessione
      $cattedra = $reqstack->getSession()->get('/APP/DOCENTE/cattedra_lezione');
      $classe = $reqstack->getSession()->get('/APP/DOCENTE/classe_lezione');
    } else {
      // memorizza su sessione
      $reqstack->getSession()->set('/APP/DOCENTE/cattedra_lezione', $cattedra);
      $reqstack->getSession()->set('/APP/DOCENTE/classe_lezione', $classe);
    }
    // parametro data
    if ($data == '0000-00-00') {
      // data non specificata
      if ($reqstack->getSession()->get('/APP/DOCENTE/data_lezione')) {
        // recupera data da sessione
        $data_obj = \DateTime::createFromFormat('Y-m-d', $reqstack->getSession()->get('/APP/DOCENTE/data_lezione'));
      } else {
        // imposta data odierna
        $data_obj = new \DateTime();
      }
    } else {
      // imposta data indicata e la memorizza in sessione
      $data_obj = \DateTime::createFromFormat('Y-m-d', $data);
      $reqstack->getSession()->set('/APP/DOCENTE/data_lezione', $data);
    }
    // data in formato stringa
    $formatter = new \IntlDateFormatter('it_IT', \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $info['data_label'] =  $formatter->format($data_obj);
    // controllo cattedra/supplenza
    if ($cattedra > 0) {
      // lezione in propria cattedra: controlla esistenza
      $cattedra = $em->getRepository('App\Entity\Cattedra')->findOneBy(['id' => $cattedra,
        'docente' => $this->getUser(), 'attiva' => 1]);
      if (!$cattedra) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      // informazioni necessarie
      $classe = $cattedra->getClasse();
      $info['materia'] = $cattedra->getMateria()->getNomeBreve();
      $info['alunno'] = $cattedra->getAlunno();
    } elseif ($classe > 0) {
      // supplenza
      $classe = $em->getRepository('App\Entity\Classe')->find($classe);
      if (!$classe) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      // informazioni necessarie
      $cattedra = null;
    }
    if ($cattedra) {
      // data prec/succ
      $data_succ = (clone $data_obj);
      $data_succ = $em->getRepository('App\Entity\Festivita')->giornoSuccessivo($data_succ);
      $data_prec = (clone $data_obj);
      $data_prec = $em->getRepository('App\Entity\Festivita')->giornoPrecedente($data_prec);
      // recupera festivi per calendario
      $lista_festivi = $reg->listaFestivi($classe->getSede());
      // controllo data
      $errore = $reg->controlloData($data_obj, $classe->getSede());
      if (!$errore) {
        // non festivo: recupera dati
        $dati = $reg->osservazioniPersonali($data_obj, $this->getUser(), $cattedra);
      }
    }
    // salva pagina visitata
    $route = ['name' => $request->get('_route'), 'param' => $request->get('_route_params')];
    $reqstack->getSession()->set('/APP/DOCENTE/menu_lezione', $route);
    // visualizza pagina
    return $this->render('lezioni/osservazioni_personali.html.twig', array(
      'pagina_titolo' => 'page.lezioni_osservazioni_personali',
      'cattedra' => $cattedra,
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
   * Aggiunge o modifica un'osservazione personale
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $cattedra Identificativo della cattedra
   * @param string $data Data del giorno
   * @param int $id Identificativo dell'osservazione (se nullo aggiunge)
   *
   * @return Response Pagina di risposta
   *
   * @Route("/lezioni/osservazioni/personali/edit/{cattedra}/{data}/{id}", name="lezioni_osservazioni_personali_edit",
   *    requirements={"cattedra": "\d+", "data": "\d\d\d\d-\d\d-\d\d", "id": "\d+"},
   *    defaults={"id": 0},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function osservazionePersonaleEditAction(Request $request, EntityManagerInterface $em, RegistroUtil $reg,
                                                   LogHandler $dblogger, $cattedra, $data, $id) {
    // inizializza
    $label = array();
    // controlla cattedra
    $cattedra = $em->getRepository('App\Entity\Cattedra')->findOneBy(['id' => $cattedra,
      'docente' => $this->getUser(), 'attiva' => 1]);
    if (!$cattedra) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla data
    $data_obj = \DateTime::createFromFormat('Y-m-d', $data);
    $errore = $reg->controlloData($data_obj, $cattedra->getClasse()->getSede());
    if ($errore) {
      // errore: festivo
      throw $this->createNotFoundException('exception.invalid_params');
    }
    if ($id > 0) {
      // azione edit, controlla ossservazione
      $osservazione = $em->getRepository('App\Entity\OsservazioneClasse')->findOneBy(['id' => $id,
        'data' => $data_obj, 'cattedra' => $cattedra]);
      if (!$osservazione) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $osservazione_old['testo'] = $osservazione->getTesto();
    } else {
      // azione add
      $osservazione = (new OsservazioneClasse())
        ->setData($data_obj)
        ->setCattedra($cattedra);
    }
    // controlla permessi
    if (!$reg->azioneOsservazione(($id > 0 ? 'edit' : 'add'), $data_obj, $this->getUser(),
                                   $cattedra->getClasse(), ($id > 0 ? $osservazione : null))) {
      // errore: azione non permessa
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // dati in formato stringa
    $formatter = new \IntlDateFormatter('it_IT', \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $label['data'] =  $formatter->format($data_obj);
    $label['docente'] = $this->getUser()->getNome().' '.$this->getUser()->getCognome();
    $label['classe'] = $cattedra->getClasse()->getAnno()."ª ".$cattedra->getClasse()->getSezione();
    // form di inserimento
    $form = $this->container->get('form.factory')->createNamedBuilder('osservazione_personale_edit', FormType::class, $osservazione)
      ->add('testo', MessageType::class, array('label' => 'label.testo',
        'trim' => true,
        'required' => true))
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start']))
      ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end',
        'onclick' => "location.href='".$this->generateUrl('lezioni_osservazioni_personali')."'"]))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      if (!$id) {
        // nuovo
        $em->persist($osservazione);
      }
      // ok: memorizza dati
      $em->flush();
      // log azione
      if (!$id) {
        // nuovo
        $dblogger->logAzione('REGISTRO', 'Crea osservazione personale', array(
          'Id' => $osservazione->getId()
          ));
      } else {
        // modifica
        $dblogger->logAzione('REGISTRO', 'Modifica osservazione personale', array(
          'Id' => $osservazione->getId(),
          'Testo' => $osservazione_old['testo'],
          ));
      }
      // redirezione
      return $this->redirectToRoute('lezioni_osservazioni_personali');
    }
    // mostra la pagina di risposta
    return $this->render('lezioni/osservazione_edit.html.twig', array(
      'pagina_titolo' => 'page.lezioni_osservazioni_personali',
      'form' => $form->createView(),
      'form_title' => ($id > 0 ? 'title.modifica_osservazione_personale' : 'title.nuova_osservazione_personale'),
      'label' => $label,
      'alunno' => null,
    ));
  }

  /**
   * Cancella un'osservazione personale
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $id Identificativo dell'osservazione
   *
   * @return Response Pagina di risposta
   *
   * @Route("/lezioni/osservazioni/personali/delete/{id}", name="lezioni_osservazioni_personali_delete",
   *    requirements={"id": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function osservazionePersonaleDeleteAction(Request $request, EntityManagerInterface $em, RegistroUtil $reg,
                                            LogHandler $dblogger, $id) {
    // controlla osservazione
    $osservazione = $em->getRepository('App\Entity\OsservazioneClasse')->find($id);
    if (!$osservazione) {
      // non esiste, niente da fare
      return $this->redirectToRoute('lezioni_osservazioni_personali');
    }
    // controlla permessi

    if (!$reg->azioneOsservazione('delete', $osservazione->getData(), $this->getUser(),
                                  $osservazione->getCattedra()->getClasse(), $osservazione)) {
      // errore: azione non permessa
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // cancella osservazione
    $osservazione_id = $osservazione->getId();
    $em->remove($osservazione);
    // ok: memorizza dati
    $em->flush();
    // log azione
    $dblogger->logAzione('REGISTRO', 'Cancella osservazione personale', array(
      'Osservazione' => $osservazione_id,
      'Cattedra' => $osservazione->getCattedra()->getId(),
      'Data' => $osservazione->getData()->format('Y-m-d'),
      'Testo' => $osservazione->getTesto(),
      ));
    // redirezione
    return $this->redirectToRoute('lezioni_osservazioni_personali');
  }

}
