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
use Doctrine\ORM\EntityRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use AppBundle\Entity\OsservazioneAlunno;
use AppBundle\Entity\OsservazioneClasse;
use AppBundle\Util\LogHandler;
use AppBundle\Util\RegistroUtil;


/**
 * OsservazioniController - gestione delle osservazioni sugli alunni
 */
class OsservazioniController extends Controller {

  /**
   * Gestione delle osservazioni sugli alunni
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param int $cattedra Identificativo della cattedra
   * @param int $classe Identificativo della classe (supplenza)
   * @param string $data Data del giorno da visualizzare (AAAA-MM-GG)
   *
   * @return Response Pagina di risposta
   *
   * @Route("/lezioni/osservazioni/{cattedra}/{classe}/{data}", name="lezioni_osservazioni",
   *    requirements={"cattedra": "\d+", "classe": "\d+", "data": "\d\d\d\d-\d\d-\d\d"},
   *    defaults={"cattedra": 0, "classe": 0, "data": "0000-00-00"})
   * @Method("GET")
   *
   * @Security("has_role('ROLE_DOCENTE')")
   */
  public function osservazioniAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
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
      $cattedra = $session->get('/APP/DOCENTE/cattedra_lezione');
      $classe = $session->get('/APP/DOCENTE/classe_lezione');
    } else {
      // memorizza su sessione
      $session->set('/APP/DOCENTE/cattedra_lezione', $cattedra);
      $session->set('/APP/DOCENTE/classe_lezione', $classe);
    }
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
      // imposta data indicata e la memorizza in sessione
      $data_obj = \DateTime::createFromFormat('Y-m-d', $data);
      $session->set('/APP/DOCENTE/data_lezione', $data);
    }
    // data in formato stringa
    $formatter = new \IntlDateFormatter('it_IT', \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $info['data_label'] =  $formatter->format($data_obj);
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
      $info['materia'] = $cattedra->getMateria()->getNomeBreve();
      $info['alunno'] = $cattedra->getAlunno();
    } elseif ($classe > 0) {
      // supplenza
      $classe = $em->getRepository('AppBundle:Classe')->find($classe);
      if (!$classe) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      // informazioni necessarie
      $cattedra = null;
    }
    if ($cattedra) {
      // recupera festivi per calendario
      $lista_festivi = $reg->listaFestivi($classe->getSede());
      // controllo data
      $errore = $reg->controlloData($data_obj, $classe->getSede());
      if (!$errore) {
        // non festivo: recupera dati
        $dati = $reg->osservazioni($data_obj, $this->getUser(), $cattedra);
      }
    }
    // salva pagina visitata
    $route = ['name' => $request->get('_route'), 'param' => $request->get('_route_params')];
    $session->set('/APP/DOCENTE/menu_lezione', $route);
    // visualizza pagina
    return $this->render('lezioni/osservazioni.html.twig', array(
      'pagina_titolo' => 'page.lezioni_osservazioni',
      'cattedra' => $cattedra,
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
   *    defaults={"id": 0})
   * @Method({"GET","POST"})
   *
   * @Security("has_role('ROLE_DOCENTE')")
   */
  public function osservazioneEditAction(Request $request, EntityManagerInterface $em, RegistroUtil $reg,
                                          LogHandler $dblogger, $cattedra, $data, $id) {
    // inizializza
    $label = array();
    // controlla cattedra
    $cattedra = $em->getRepository('AppBundle:Cattedra')->findOneBy(['id' => $cattedra,
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
      $osservazione = $em->getRepository('AppBundle:OsservazioneAlunno')->findOneBy(['id' => $id,
        'data' => $data_obj, 'cattedra' => $cattedra]);
      if (!$osservazione) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $osservazione_old['testo'] = $osservazione->getTesto();
      $osservazione_old['alunno'] = $osservazione->getAlunno();
    } else {
      // azione add
      $osservazione = (new OsservazioneAlunno())
        ->setData($data_obj)
        ->setCattedra($cattedra);
      if ($cattedra->getTipo() == 'S') {
        // cattedra di sostegno
        $osservazione->setAlunno($cattedra->getAlunno());
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
    $form = $this->container->get('form.factory')->createNamedBuilder('osservazione_edit', FormType::class, $osservazione);
    if ($cattedra->getTipo() != 'S') {
      // non è cattedra di sostegno
      $form = $form
        ->add('alunno', EntityType::class, array('label' => 'label.alunno',
          'class' => 'AppBundle:Alunno',
          'choice_label' => function ($obj) {
              return $obj->getCognome().' '.$obj->getNome().' ('.$obj->getDataNascita()->format('d/m/Y').')';
            },
          'query_builder' => function (EntityRepository $er) use ($cattedra) {
              return $er->createQueryBuilder('a')
                ->where('a.classe=:classe and a.abilitato=:abilitato')
                ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
                ->setParameters(['classe' => $cattedra->getClasse(), 'abilitato' => 1]);
            },
          'expanded' => true,
          'multiple' => false,
          'label_attr' => ['class' => 'gs-pt-1 checkbox-split-vertical'],
          'required' => true));
     }
     $form = $form
      ->add('testo', TextareaType::class, array('label' => 'label.testo',
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
        $dblogger->write($this->getUser(), $request->getClientIp(), 'REGISTRO', 'Crea osservazione', __METHOD__, array(
          'Id' => $osservazione->getId()
          ));
      } else {
        // modifica
        $dblogger->write($this->getUser(), $request->getClientIp(), 'REGISTRO', 'Modifica osservazione', __METHOD__, array(
          'Id' => $osservazione->getId(),
          'Testo' => $osservazione_old['testo'],
          'Alunno' => $osservazione_old['alunno']->getId()
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
   *    requirements={"id": "\d+"})
   * @Method({"GET"})
   *
   * @Security("has_role('ROLE_DOCENTE')")
   */
  public function osservazioneDeleteAction(Request $request, EntityManagerInterface $em, RegistroUtil $reg,
                                            LogHandler $dblogger, $id) {
    // controlla osservazione
    $osservazione = $em->getRepository('AppBundle:OsservazioneAlunno')->find($id);
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
    $dblogger->write($this->getUser(), $request->getClientIp(), 'REGISTRO', 'Cancella osservazione', __METHOD__, array(
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
   * @param SessionInterface $session Gestore delle sessioni
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param int $cattedra Identificativo della cattedra
   * @param int $classe Identificativo della classe (supplenza)
   * @param string $data Data del giorno da visualizzare (AAAA-MM-GG)
   *
   * @return Response Pagina di risposta
   *
   * @Route("/lezioni/osservazioni/personali/{cattedra}/{classe}/{data}", name="lezioni_osservazioni_personali",
   *    requirements={"cattedra": "\d+", "classe": "\d+", "data": "\d\d\d\d-\d\d-\d\d"},
   *    defaults={"cattedra": 0, "classe": 0, "data": "0000-00-00"})
   * @Method("GET")
   *
   * @Security("has_role('ROLE_DOCENTE')")
   */
  public function osservazioniPersonaliAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
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
      $cattedra = $session->get('/APP/DOCENTE/cattedra_lezione');
      $classe = $session->get('/APP/DOCENTE/classe_lezione');
    } else {
      // memorizza su sessione
      $session->set('/APP/DOCENTE/cattedra_lezione', $cattedra);
      $session->set('/APP/DOCENTE/classe_lezione', $classe);
    }
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
      // imposta data indicata e la memorizza in sessione
      $data_obj = \DateTime::createFromFormat('Y-m-d', $data);
      $session->set('/APP/DOCENTE/data_lezione', $data);
    }
    // data in formato stringa
    $formatter = new \IntlDateFormatter('it_IT', \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $info['data_label'] =  $formatter->format($data_obj);
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
      $info['materia'] = $cattedra->getMateria()->getNomeBreve();
      $info['alunno'] = $cattedra->getAlunno();
    } elseif ($classe > 0) {
      // supplenza
      $classe = $em->getRepository('AppBundle:Classe')->find($classe);
      if (!$classe) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      // informazioni necessarie
      $cattedra = null;
    }
    if ($cattedra) {
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
    $session->set('/APP/DOCENTE/menu_lezione', $route);
    // visualizza pagina
    return $this->render('lezioni/osservazioni_personali.html.twig', array(
      'pagina_titolo' => 'page.lezioni_osservazioni_personali',
      'cattedra' => $cattedra,
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
   *    defaults={"id": 0})
   * @Method({"GET","POST"})
   *
   * @Security("has_role('ROLE_DOCENTE')")
   */
  public function osservazionePersonaleEditAction(Request $request, EntityManagerInterface $em, RegistroUtil $reg,
                                                   LogHandler $dblogger, $cattedra, $data, $id) {
    // inizializza
    $label = array();
    // controlla cattedra
    $cattedra = $em->getRepository('AppBundle:Cattedra')->findOneBy(['id' => $cattedra,
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
      $osservazione = $em->getRepository('AppBundle:OsservazioneClasse')->findOneBy(['id' => $id,
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
      ->add('testo', TextareaType::class, array('label' => 'label.testo',
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
        $dblogger->write($this->getUser(), $request->getClientIp(), 'REGISTRO', 'Crea osservazione personale', __METHOD__, array(
          'Id' => $osservazione->getId()
          ));
      } else {
        // modifica
        $dblogger->write($this->getUser(), $request->getClientIp(), 'REGISTRO', 'Modifica osservazione personale', __METHOD__, array(
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
   *    requirements={"id": "\d+"})
   * @Method({"GET"})
   *
   * @Security("has_role('ROLE_DOCENTE')")
   */
  public function osservazionePersonaleDeleteAction(Request $request, EntityManagerInterface $em, RegistroUtil $reg,
                                            LogHandler $dblogger, $id) {
    // controlla osservazione
    $osservazione = $em->getRepository('AppBundle:OsservazioneClasse')->find($id);
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
    $dblogger->write($this->getUser(), $request->getClientIp(), 'REGISTRO', 'Cancella osservazione personale', __METHOD__, array(
      'Osservazione' => $osservazione_id,
      'Cattedra' => $osservazione->getCattedra()->getId(),
      'Data' => $osservazione->getData()->format('Y-m-d'),
      'Testo' => $osservazione->getTesto(),
      ));
    // redirezione
    return $this->redirectToRoute('lezioni_osservazioni_personali');
  }

}

