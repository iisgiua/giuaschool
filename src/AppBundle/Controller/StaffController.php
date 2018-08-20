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
use Doctrine\Common\Collections\ArrayCollection;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use AppBundle\Entity\Annotazione;
use AppBundle\Entity\Colloquio;
use AppBundle\Entity\Avviso;
use AppBundle\Util\RegistroUtil;
use AppBundle\Util\StaffUtil;
use AppBundle\Util\LogHandler;
use AppBundle\Util\PdfManager;
use AppBundle\Util\BachecaUtil;


/**
 * StaffController - funzioni per lo staff
 */
class StaffController extends Controller {

  /**
   * Gestisce la generazione della password per gli alunni
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param int $pagina Numero di pagina per la lista dei alunni
   *
   * @return Response Pagina di risposta
   *
   * @Route("/staff/password/{pagina}", name="staff_password",
   *    requirements={"pagina": "\d+"},
   *    defaults={"pagina": 0})
   * @Method({"GET", "POST"})
   *
   * @Security("has_role('ROLE_STAFF')")
   */
  public function passwordAction(Request $request, EntityManagerInterface $em, SessionInterface $session, $pagina) {
    // recupera criteri dalla sessione
    $search = array();
    $search['nome'] = $session->get('/APP/ROUTE/staff_password/nome', '');
    $search['cognome'] = $session->get('/APP/ROUTE/staff_password/cognome', '');
    $search['classe'] = $session->get('/APP/ROUTE/staff_password/classe', 0);
    $classe = ($search['classe'] > 0 ? $em->getRepository('AppBundle:Classe')->find($search['classe']) : 0);
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $session->get('/APP/ROUTE/staff_password/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $session->set('/APP/ROUTE/staff_password/pagina', $pagina);
    }
    // legge sede
    $sede = $this->getUser()->getSede();
    // form di ricerca
    $limite = 25;
    if ($sede) {
      // limita a classi di sede
      $classi = $em->getRepository('AppBundle:Classe')->findBy(['sede' => $sede], ['anno' =>'ASC', 'sezione' =>'ASC']);
    } else {
      // tutte le classi
      $classi = $em->getRepository('AppBundle:Classe')->findBy([], ['anno' =>'ASC', 'sezione' =>'ASC']);
    }
    $form = $this->container->get('form.factory')->createNamedBuilder('staff_password', FormType::class)
      ->setAction($this->generateUrl('staff_password'))
      ->add('cognome', TextType::class, array('label' => 'label.cognome',
        'data' => $search['cognome'],
        'attr' => ['placeholder' => 'label.cognome'],
        'label_attr' => ['class' => 'sr-only'],
        'required' => false))
      ->add('nome', TextType::class, array('label' => 'label.nome',
        'data' => $search['nome'],
        'attr' => ['placeholder' => 'label.nome'],
        'label_attr' => ['class' => 'sr-only'],
        'required' => false))
      ->add('classe', ChoiceType::class, array('label' => 'label.classe',
        'data' => $classe,
        'choices' => $classi,
        'choice_label' => function ($obj) {
            return $obj->getAnno().'ª '.$obj->getSezione().' - '.$obj->getCorso()->getNomeBreve();
          },
        'choice_value' => function ($obj) {
            return (is_object($obj)  ? $obj->getId() : $obj);
          },
        'group_by' => function ($obj) {
            return $obj->getSede()->getCitta();
          },
        'placeholder' => 'label.qualsiasi_classe',
        'choice_translation_domain' => false,
        'label_attr' => ['class' => 'sr-only'],
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.search'))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $search['nome'] = trim($form->get('nome')->getData());
      $search['cognome'] = trim($form->get('cognome')->getData());
      $search['classe'] = (is_object($form->get('classe')->getData()) ? $form->get('classe')->getData()->getId() : 0);
      $pagina = 1;
      $session->set('/APP/ROUTE/staff_password/nome', $search['nome']);
      $session->set('/APP/ROUTE/staff_password/cognome', $search['cognome']);
      $session->set('/APP/ROUTE/staff_password/classe', $search['classe']);
      $session->set('/APP/ROUTE/staff_password/pagina', $pagina);
    }
    // lista alunni
    $lista = $em->getRepository('AppBundle:Alunno')->findClassEnabled($sede, $search, $pagina, $limite);
    // mostra la pagina di risposta
    return $this->render('ruolo_staff/password.html.twig', array(
      'pagina_titolo' => 'page.staff_password',
      'form' => $form->createView(),
      'form_help' => null,
      'form_success' => null,
      'lista' => $lista,
      'page' => $pagina,
      'maxPages' => ceil($lista->count() / $limite),
    ));
  }

  /**
   * Generazione della password degli alunni
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param UserPasswordEncoderInterface $encoder Gestore della codifica delle password
   * @param LogHandler $dblogger Gestore dei log su database
   * @param PdfManager $pdf Gestore dei documenti PDF
   * @param int $alunno ID dell'alunno
   * @param int $classe ID della classe
   *
   * @return Response Pagina di risposta
   *
   * @Route("/staff/password/create/{classe}/{alunno}", name="staff_password_create",
   *    requirements={"alunno": "\d+", "classe": "\d+"})
   * @Method({"GET"})
   *
   * @Security("has_role('ROLE_STAFF')")
   */
  public function passwordCreateAction(Request $request, EntityManagerInterface $em,
                                        UserPasswordEncoderInterface $encoder, LogHandler $dblogger,
                                        PdfManager $pdf, $classe, $alunno) {
    if ($classe > 0) {
      // controlla classe
      $classe = $em->getRepository('AppBundle:Classe')->find($classe);
      if (!$classe) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      // recupera alunni della classe
      $alunni = $em->getRepository('AppBundle:Alunno')->createQueryBuilder('a')
        ->where('a.classe=:classe AND a.abilitato=:abilitato')
        ->setParameters(['classe' => $classe, 'abilitato' => 1])
        ->orderBy('a.cognome,a.nome', 'ASC')
        ->getQuery()
        ->getResult();
      if (empty($alunni)) {
        // nessun alunno
        return $this->redirectToRoute('staff_password');
      } else {
        // alunni presenti
        $pwdchars = "abcdefghikmnopqrstuvwxyz123456789";
        // crea documento PDF
        $pdf->configure('Istituto di Istruzione Superiore',
          'Credenziali di accesso al Registro Elettronico');
        foreach ($alunni as $alu) {
          // recupera genitori (anche più di uno)
          $genitori = $em->getRepository('AppBundle:Genitore')->findBy(['alunno' => $alu]);
          if (empty($genitori)) {
            // errore
            throw $this->createNotFoundException('exception.id_notfound');
          }
          // crea password
          $password = substr(str_shuffle($pwdchars), 0, 4).substr(str_shuffle($pwdchars), 0, 4);
          foreach ($genitori as $gen) {
            $gen->setPasswordNonCifrata($password);
            $pswd = $encoder->encodePassword($gen, $gen->getPasswordNonCifrata());
            $gen->setPassword($pswd);
          }
          // memorizza su db
          $em->flush();
          // log azione
          $dblogger->write($alu, $request->getClientIp(), 'SICUREZZA', 'Generazione Password', __METHOD__, array(
            'Username esecutore' => $this->getUser()->getUsername(),
            'Ruolo esecutore' => $this->getUser()->getRoles()[0],
            'ID esecutore' => $this->getUser()->getId()
            ));
          // contenuto in formato HTML
          $html = $this->renderView('pdf/credenziali_alunni.html.twig', array(
            'alunno' => $alu,
            'username' => $genitori[0]->getUsername(),
            'password' => $password,
            'sesso' => $alu->getSesso() == 'M' ? 'o' : 'a',
            ));
          $pdf->createFromHtml($html);
        }
        // invia il documento
        $nomefile = 'credenziali-registro-'.$classe->getAnno().$classe->getSezione().'.pdf';
        return $pdf->send($nomefile);
      }
    } elseif ($alunno > 0) {
      // controlla alunno
      $alunno = $em->getRepository('AppBundle:Alunno')->findOneBy(['id' => $alunno, 'abilitato' => 1]);
      if (!$alunno) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      // recupera genitori (anche più di uno)
      $genitori = $em->getRepository('AppBundle:Genitore')->findBy(['alunno' => $alunno]);
      if (empty($genitori)) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      // crea password
      $pwdchars = "abcdefghikmnopqrstuvwxyz123456789";
      $password = substr(str_shuffle($pwdchars), 0, 4).substr(str_shuffle($pwdchars), 0, 4);
      foreach ($genitori as $gen) {
        $gen->setPasswordNonCifrata($password);
        $pswd = $encoder->encodePassword($gen, $gen->getPasswordNonCifrata());
        $gen->setPassword($pswd);
      }
      // memorizza su db
      $em->flush();
      // log azione
      $dblogger->write($alunno, $request->getClientIp(), 'SICUREZZA', 'Generazione Password', __METHOD__, array(
        'Username esecutore' => $this->getUser()->getUsername(),
        'Ruolo esecutore' => $this->getUser()->getRoles()[0],
        'ID esecutore' => $this->getUser()->getId()
        ));
      // crea documento PDF
      $pdf->configure('Istituto di Istruzione Superiore',
        'Credenziali di accesso al Registro Elettronico');
      // contenuto in formato HTML
      $html = $this->renderView('pdf/credenziali_alunni.html.twig', array(
        'alunno' => $alunno,
        'username' => $genitori[0]->getUsername(),
        'password' => $password,
        'sesso' => $alunno->getSesso() == 'M' ? 'o' : 'a',
        ));
      $pdf->createFromHtml($html);
      // invia il documento
      $nomealunno = preg_replace('/[^\w-]/', '', strtoupper($alunno->getCognome().'-'.$alunno->getNome()));
      $nomefile = 'credenziali-registro-'.$nomealunno.'.pdf';
      return $pdf->send($nomefile);
    } else {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
  }

  /**
   * Gestione dei ritardi e delle uscite anticipate
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param StaffUtil $staff Funzioni di utilità per lo staff
   * @param string $data Data per la gestione dei ritardi e delle uscita (AAAA-MM-GG)
   * @param int $pagina Numero di pagina per la lista dei alunni
   *
   * @return Response Pagina di risposta
   *
   * @Route("/staff/autorizza/{data}/{pagina}", name="staff_autorizza",
   *    requirements={"data": "\d\d\d\d-\d\d-\d\d", "pagina": "\d+"},
   *    defaults={"data": "0000-00-00", "pagina": "0"})
   * @Method({"GET","POST"})
   *
   * @Security("has_role('ROLE_STAFF')")
   */
  public function autorizzaAction(Request $request, EntityManagerInterface $em, SessionInterface $session, RegistroUtil $reg,
                                   StaffUtil $staff, $data, $pagina) {
    // inizializza variabili
    $lista_festivi = null;
    $errore = null;
    $dati = null;
    $info = null;
    $max_pagine = 1;
    $settimana = ['Domenica', 'Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato'];
    $mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    // parametro data
    if ($data == '0000-00-00') {
      // data non specificata
      if ($session->get('/APP/ROUTE/staff_autorizza/data')) {
        // recupera data da sessione
        $data_obj = \DateTime::createFromFormat('Y-m-d', $session->get('/APP/ROUTE/staff_autorizza/data'));
      } else {
        // imposta data odierna
        $data_obj = new \DateTime();
      }
    } else {
      // imposta data indicata e la memorizza in sessione
      $data_obj = \DateTime::createFromFormat('Y-m-d', $data);
      $session->set('/APP/ROUTE/staff_autorizza/data', $data);
    }
    // data in formato stringa
    $formatter = new \IntlDateFormatter('it_IT', \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $info['data_label'] =  $formatter->format($data_obj);
    // recupera criteri dalla sessione
    $search = array();
    $search['nome'] = $session->get('/APP/ROUTE/staff_autorizza/nome', '');
    $search['cognome'] = $session->get('/APP/ROUTE/staff_autorizza/cognome', '');
    $search['classe'] = $session->get('/APP/ROUTE/staff_autorizza/classe', 0);
    $classe = ($search['classe'] > 0 ? $em->getRepository('AppBundle:Classe')->find($search['classe']) : 0);
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $session->get('/APP/ROUTE/staff_autorizza/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $session->set('/APP/ROUTE/staff_autorizza/pagina', $pagina);
    }
    // legge sede
    $sede = $this->getUser()->getSede();
    // form di ricerca
    $limite = 25;
    if ($sede) {
      // limita a classi di sede
      $classi = $em->getRepository('AppBundle:Classe')->findBy(['sede' => $sede], ['anno' =>'ASC', 'sezione' =>'ASC']);
    } else {
      // tutte le classi
      $classi = $em->getRepository('AppBundle:Classe')->findBy([], ['anno' =>'ASC', 'sezione' =>'ASC']);
    }
    $form = $this->container->get('form.factory')->createNamedBuilder('staff_autorizza', FormType::class)
      ->setAction($this->generateUrl('staff_autorizza', ['data' => $data]))
      ->add('cognome', TextType::class, array('label' => 'label.cognome',
        'data' => $search['cognome'],
        'attr' => ['placeholder' => 'label.cognome'],
        'label_attr' => ['class' => 'sr-only'],
        'required' => false))
      ->add('nome', TextType::class, array('label' => 'label.nome',
        'data' => $search['nome'],
        'attr' => ['placeholder' => 'label.nome'],
        'label_attr' => ['class' => 'sr-only'],
        'required' => false))
      ->add('classe', ChoiceType::class, array('label' => 'label.classe',
        'data' => $classe,
        'choices' => $classi,
        'choice_label' => function ($obj) {
            return $obj->getAnno().'ª '.$obj->getSezione().' - '.$obj->getCorso()->getNomeBreve();
          },
        'choice_value' => function ($obj) {
            return (is_object($obj)  ? $obj->getId() : $obj);
          },
        'group_by' => function ($obj) {
            return $obj->getSede()->getCitta();
          },
        'placeholder' => 'label.qualsiasi_classe',
        'choice_translation_domain' => false,
        'label_attr' => ['class' => 'sr-only'],
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.search'))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $search['nome'] = trim($form->get('nome')->getData());
      $search['cognome'] = trim($form->get('cognome')->getData());
      $search['classe'] = (is_object($form->get('classe')->getData()) ? $form->get('classe')->getData()->getId() : 0);
      $pagina = 1;
      $session->set('/APP/ROUTE/staff_autorizza/nome', $search['nome']);
      $session->set('/APP/ROUTE/staff_autorizza/cognome', $search['cognome']);
      $session->set('/APP/ROUTE/staff_autorizza/classe', $search['classe']);
      $session->set('/APP/ROUTE/staff_autorizza/pagina', $pagina);
    }
    // recupera periodo
    $info['periodo'] = $reg->periodo($data_obj);
    // recupera festivi per calendario
    $lista_festivi = $reg->listaFestivi(null);
    // controllo data
    $errore = $reg->controlloData($data_obj, null);
    if (!$errore) {
      // non festivo: recupera dati
      $lista = $em->getRepository('AppBundle:Alunno')->findClassEnabled($sede, $search, $pagina, $limite);
      $max_pagine = ceil($lista->count() / $limite);
      $dati['lista'] = $staff->entrateUscite($info['periodo']['inizio'], $info['periodo']['fine'], $lista);
      $dati['azioni'] = $reg->azioneAssenze($data_obj, $this->getUser(), null, null, null);
    }
    // mostra la pagina di risposta
    return $this->render('ruolo_staff/autorizza.html.twig', array(
      'pagina_titolo' => 'page.staff_autorizza',
      'form' => $form->createView(),
      'form_help' => null,
      'form_success' => null,
      'page' => $pagina,
      'maxPages' => $max_pagine,
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
   * Gestisce l'inserimento di deroghe e annotazioni sugli alunni
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param int $pagina Numero di pagina per la lista dei alunni
   *
   * @return Response Pagina di risposta
   *
   * @Route("/staff/deroghe/{pagina}", name="staff_deroghe",
   *    requirements={"pagina": "\d+"},
   *    defaults={"pagina": 0})
   * @Method({"GET", "POST"})
   *
   * @Security("has_role('ROLE_STAFF')")
   */
  public function derogheAction(Request $request, EntityManagerInterface $em, SessionInterface $session, $pagina) {
    // recupera criteri dalla sessione
    $search = array();
    $search['nome'] = $session->get('/APP/ROUTE/staff_deroghe/nome', '');
    $search['cognome'] = $session->get('/APP/ROUTE/staff_deroghe/cognome', '');
    $search['classe'] = $session->get('/APP/ROUTE/staff_deroghe/classe', 0);
    $classe = ($search['classe'] > 0 ? $em->getRepository('AppBundle:Classe')->find($search['classe']) : 0);
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $session->get('/APP/ROUTE/staff_deroghe/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $session->set('/APP/ROUTE/staff_deroghe/pagina', $pagina);
    }
    // legge sede
    $sede = $this->getUser()->getSede();
    // form di ricerca
    $limite = 25;
    if ($sede) {
      // limita a classi di sede
      $classi = $em->getRepository('AppBundle:Classe')->findBy(['sede' => $sede], ['anno' =>'ASC', 'sezione' =>'ASC']);
    } else {
      // tutte le classi
      $classi = $em->getRepository('AppBundle:Classe')->findBy([], ['anno' =>'ASC', 'sezione' =>'ASC']);
    }
    $form = $this->container->get('form.factory')->createNamedBuilder('staff_deroghe', FormType::class)
      ->setAction($this->generateUrl('staff_deroghe'))
      ->add('cognome', TextType::class, array('label' => 'label.cognome',
        'data' => $search['cognome'],
        'attr' => ['placeholder' => 'label.cognome'],
        'label_attr' => ['class' => 'sr-only'],
        'required' => false))
      ->add('nome', TextType::class, array('label' => 'label.nome',
        'data' => $search['nome'],
        'attr' => ['placeholder' => 'label.nome'],
        'label_attr' => ['class' => 'sr-only'],
        'required' => false))
      ->add('classe', ChoiceType::class, array('label' => 'label.classe',
        'data' => $classe,
        'choices' => $classi,
        'choice_label' => function ($obj) {
            return $obj->getAnno().'ª '.$obj->getSezione().' - '.$obj->getCorso()->getNomeBreve();
          },
        'choice_value' => function ($obj) {
            return (is_object($obj)  ? $obj->getId() : $obj);
          },
        'group_by' => function ($obj) {
            return $obj->getSede()->getCitta();
          },
        'placeholder' => 'label.qualsiasi_classe',
        'choice_translation_domain' => false,
        'label_attr' => ['class' => 'sr-only'],
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.search'))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $search['nome'] = trim($form->get('nome')->getData());
      $search['cognome'] = trim($form->get('cognome')->getData());
      $search['classe'] = (is_object($form->get('classe')->getData()) ? $form->get('classe')->getData()->getId() : 0);
      $pagina = 1;
      $session->set('/APP/ROUTE/staff_deroghe/nome', $search['nome']);
      $session->set('/APP/ROUTE/staff_deroghe/cognome', $search['cognome']);
      $session->set('/APP/ROUTE/staff_deroghe/classe', $search['classe']);
      $session->set('/APP/ROUTE/staff_deroghe/pagina', $pagina);
    }
    // lista alunni
    $lista = $em->getRepository('AppBundle:Alunno')->findClassEnabled($sede, $search, $pagina, $limite);
    // mostra la pagina di risposta
    return $this->render('ruolo_staff/deroghe.html.twig', array(
      'pagina_titolo' => 'page.staff_deroghe',
      'form' => $form->createView(),
      'form_help' => null,
      'form_success' => null,
      'lista' => $lista,
      'page' => $pagina,
      'maxPages' => ceil($lista->count() / $limite),
    ));
  }

  /**
   * Modifica delle deroghe e annotazioni di un alunno
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $alunno ID dell'alunno
   *
   * @return Response Pagina di risposta
   *
   * @Route("/staff/deroghe/edit/{alunno}", name="staff_deroghe_edit",
   *    requirements={"alunno": "\d+"})
   * @Method({"GET", "POST"})
   *
   * @Security("has_role('ROLE_STAFF')")
   */
  public function derogheEditAction(Request $request, EntityManagerInterface $em, LogHandler $dblogger, $alunno) {
    // inizializza
    $label = null;
    // controlla alunno
    $alunno = $em->getRepository('AppBundle:Alunno')->findOneBy(['id' => $alunno, 'abilitato' => 1]);
    if (!$alunno) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // edit
    $alunno_old['autorizzaEntrata'] = $alunno->getAutorizzaEntrata();
    $alunno_old['autorizzaUscita'] = $alunno->getAutorizzaUscita();
    $alunno_old['note'] = $alunno->getNote();
    // dati in formato stringa
    $formatter = new \IntlDateFormatter('it_IT', \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $label['data'] =  $formatter->format(new \DateTime());
    $label['docente'] = $this->getUser()->getNome().' '.$this->getUser()->getCognome();
    $label['classe'] = $alunno->getClasse()->getAnno()."ª ".$alunno->getClasse()->getSezione();
    $label['alunno'] = $alunno->getCognome().' '.$alunno->getNome();
    // form di inserimento
    $form = $this->container->get('form.factory')->createNamedBuilder('deroga_edit', FormType::class, $alunno)
      ->add('autorizzaEntrata', TextareaType::class, array('label' => 'label.autorizza_entrata',
        'required' => false))
      ->add('autorizzaUscita', TextareaType::class, array('label' => 'label.autorizza_uscita',
        'required' => false))
      ->add('note', TextareaType::class, array('label' => 'label.note',
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start']))
      ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end',
        'onclick' => "location.href='".$this->generateUrl('staff_deroghe')."'"]))
      ->getForm();
    $form->handleRequest($request);
      if ($form->isSubmitted() && $form->isValid()) {
        // ok: memorizza dati
        $em->flush();
        // log azione
        $dblogger->write($alunno, $request->getClientIp(), 'ALUNNO', 'Modifica deroghe', __METHOD__, array(
          'Username esecutore' => $this->getUser()->getUsername(),
          'Ruolo esecutore' => $this->getUser()->getRoles()[0],
          'ID esecutore' => $this->getUser()->getId(),
          'Autorizza entrata' => $alunno_old['autorizzaEntrata'],
          'Autorizza uscita' => $alunno_old['autorizzaUscita'],
          'Note' => $alunno_old['note']
          ));
      // redirezione
      return $this->redirectToRoute('staff_deroghe');
    }
    // mostra la pagina di risposta
    return $this->render('ruolo_staff/deroga_edit.html.twig', array(
      'pagina_titolo' => 'title.deroghe',
      'form' => $form->createView(),
      'form_title' => 'title.deroghe',
      'label' => $label,
    ));
  }

  /**
   * Gestisce le ore dei colloqui individuali dei docenti
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param int $pagina Numero di pagina per la lista dei alunni
   *
   * @return Response Pagina di risposta
   *
   * @Route("/staff/colloqui/{pagina}", name="staff_colloqui",
   *    requirements={"pagina": "\d+"},
   *    defaults={"pagina": 0})
   * @Method({"GET", "POST"})
   *
   * @Security("has_role('ROLE_STAFF')")
   */
  public function colloquiAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                  $pagina) {
    $giorni_settimana = ['domenica', 'lunedì', 'martedì', 'mercoledì', 'giovedì', 'venerdì', 'sabato'];
    // recupera criteri dalla sessione
    $search = array();
    $search['docente'] = $session->get('/APP/ROUTE/staff_colloqui/docente', 0);
    $docente = ($search['docente'] > 0 ? $em->getRepository('AppBundle:Docente')->find($search['docente']) : 0);
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $session->get('/APP/ROUTE/staff_colloqui/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $session->set('/APP/ROUTE/staff_colloqui/pagina', $pagina);
    }
    // form di ricerca
    $limite = 25;
    $form = $this->container->get('form.factory')->createNamedBuilder('staff_colloqui', FormType::class)
      ->setAction($this->generateUrl('staff_colloqui'))
      ->add('docente', EntityType::class, array('label' => 'label.docente',
        'data' => $docente,
        'class' => 'AppBundle:Docente',
        'choice_label' => function ($obj) {
            return $obj->getCognome().' '.$obj->getNome();
          },
        'placeholder' => 'label.docente',
        'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('d')
              ->where('d NOT INSTANCE OF AppBundle:Preside AND d.abilitato=1')
              ->orderBy('d.cognome,d.nome', 'ASC');
          },
        'label_attr' => ['class' => 'sr-only'],
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.search'))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $search['docente'] = ($form->get('docente')->getData() ? $form->get('docente')->getData()->getId() : 0);
      $pagina = 1;
      $session->set('/APP/ROUTE/staff_colloqui/docente', $search['docente']);
      $session->set('/APP/ROUTE/staff_colloqui/pagina', $pagina);
    }
    // lista colloqui
    $lista = $em->getRepository('AppBundle:Colloquio')->findAll($search, $pagina, $limite);
    // mostra la pagina di risposta
    return $this->render('ruolo_staff/colloqui.html.twig', array(
      'pagina_titolo' => 'page.staff_colloqui',
      'form' => $form->createView(),
      'form_help' => null,
      'form_success' => null,
      'lista' => $lista,
      'page' => $pagina,
      'maxPages' => ceil($lista->count() / $limite),
      'giorni_settimana' => $giorni_settimana,
    ));
  }

  /**
   * Mostra la situazione degli alunni
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param int $pagina Numero di pagina per la lista dei alunni
   *
   * @return Response Pagina di risposta
   *
   * @Route("/staff/situazione/{pagina}", name="staff_situazione",
   *    requirements={"pagina": "\d+"},
   *    defaults={"pagina": 0})
   * @Method({"GET", "POST"})
   *
   * @Security("has_role('ROLE_STAFF')")
   */
  public function situazioneAction(Request $request, EntityManagerInterface $em, SessionInterface $session, $pagina) {
    // recupera criteri dalla sessione
    $search = array();
    $search['nome'] = $session->get('/APP/ROUTE/staff_situazione/nome', '');
    $search['cognome'] = $session->get('/APP/ROUTE/staff_situazione/cognome', '');
    $search['classe'] = $session->get('/APP/ROUTE/staff_situazione/classe', 0);
    $classe = ($search['classe'] > 0 ? $em->getRepository('AppBundle:Classe')->find($search['classe']) : 0);
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $session->get('/APP/ROUTE/staff_situazione/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $session->set('/APP/ROUTE/staff_situazione/pagina', $pagina);
    }
    // legge sede
    $sede = $this->getUser()->getSede();
    // form di ricerca
    $limite = 25;
    if ($sede) {
      // limita a classi di sede
      $classi = $em->getRepository('AppBundle:Classe')->findBy(['sede' => $sede], ['anno' =>'ASC', 'sezione' =>'ASC']);
    } else {
      // tutte le classi
      $classi = $em->getRepository('AppBundle:Classe')->findBy([], ['anno' =>'ASC', 'sezione' =>'ASC']);
    }
    $form = $this->container->get('form.factory')->createNamedBuilder('staff_situazione', FormType::class)
      ->setAction($this->generateUrl('staff_situazione'))
      ->add('cognome', TextType::class, array('label' => 'label.cognome',
        'data' => $search['cognome'],
        'attr' => ['placeholder' => 'label.cognome'],
        'label_attr' => ['class' => 'sr-only'],
        'required' => false))
      ->add('nome', TextType::class, array('label' => 'label.nome',
        'data' => $search['nome'],
        'attr' => ['placeholder' => 'label.nome'],
        'label_attr' => ['class' => 'sr-only'],
        'required' => false))
      ->add('classe', ChoiceType::class, array('label' => 'label.classe',
        'data' => $classe,
        'choices' => $classi,
        'choice_label' => function ($obj) {
            return $obj->getAnno().'ª '.$obj->getSezione().' - '.$obj->getCorso()->getNomeBreve();
          },
        'choice_value' => function ($obj) {
            return (is_object($obj)  ? $obj->getId() : $obj);
          },
        'group_by' => function ($obj) {
            return $obj->getSede()->getCitta();
          },
        'placeholder' => 'label.qualsiasi_classe',
        'choice_translation_domain' => false,
        'label_attr' => ['class' => 'sr-only'],
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.search'))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $search['nome'] = trim($form->get('nome')->getData());
      $search['cognome'] = trim($form->get('cognome')->getData());
      $search['classe'] = (is_object($form->get('classe')->getData()) ? $form->get('classe')->getData()->getId() : 0);
      $pagina = 1;
      $session->set('/APP/ROUTE/staff_situazione/nome', $search['nome']);
      $session->set('/APP/ROUTE/staff_situazione/cognome', $search['cognome']);
      $session->set('/APP/ROUTE/staff_situazione/classe', $search['classe']);
      $session->set('/APP/ROUTE/staff_situazione/pagina', $pagina);
    }
    // lista alunni
    $lista = $em->getRepository('AppBundle:Alunno')->findClassEnabled($sede, $search, $pagina, $limite);
    // mostra la pagina di risposta
    return $this->render('ruolo_staff/situazione.html.twig', array(
      'pagina_titolo' => 'page.staff_situazione',
      'form' => $form->createView(),
      'form_help' => null,
      'form_success' => null,
      'lista' => $lista,
      'page' => $pagina,
      'maxPages' => ceil($lista->count() / $limite),
    ));
  }

  /**
   * Mostra le statistiche sulle ore di lezione svolte dai docenti
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param StaffUtil $staff Funzioni di utilità per lo staff
   * @param int $pagina Numero di pagina per la lista visualizzata
   *
   * @return Response Pagina di risposta
   *
   * @Route("/staff/statistiche/{pagina}", name="staff_statistiche",
   *    requirements={"pagina": "\d+"},
   *    defaults={"pagina": 0})
   * @Method({"GET", "POST"})
   *
   * @Security("has_role('ROLE_STAFF')")
   */
  public function statisticheAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                     StaffUtil $staff, $pagina) {
    // recupera criteri dalla sessione
    $search = array();
    $search['docente'] = $session->get('/APP/ROUTE/staff_statistiche/docente', null);
    $search['inizio'] = $session->get('/APP/ROUTE/staff_statistiche/inizio', null);
    $search['fine'] = $session->get('/APP/ROUTE/staff_statistiche/fine', null);
    $docente = ($search['docente'] > 0 ? $em->getRepository('AppBundle:Docente')->find($search['docente']) :
      ($search['docente'] < 0 ? -1 : null));
    $inizio = ($search['inizio'] ? \DateTime::createFromFormat('Y-m-d', $search['inizio']) : new \DateTime());
    $fine = ($search['fine'] ? \DateTime::createFromFormat('Y-m-d', $search['fine']) : new \DateTime());
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $session->get('/APP/ROUTE/staff_statistiche/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $session->set('/APP/ROUTE/staff_statistiche/pagina', $pagina);
    }
    // form di ricerca
    $limite = 25;
    $docenti = $em->getRepository('AppBundle:Docente')->createQueryBuilder('d')
      ->where('d NOT INSTANCE OF AppBundle:Preside AND d.abilitato=:abilitato')
      ->orderBy('d.cognome,d.nome', 'ASC')
      ->setParameters(['abilitato' => 1])
      ->getQuery()
      ->getResult();
    $form = $this->container->get('form.factory')->createNamedBuilder('staff_statistiche', FormType::class)
      ->setAction($this->generateUrl('staff_statistiche'))
      ->add('docente', ChoiceType::class, array('label' => 'label.docente',
        'data' => $docente,
        'choices' => array_merge(['label.tutti_docenti' => -1], $docenti),
        'choice_label' => function ($obj, $val) {
            return (is_object($obj) ? $obj->getCognome().' '.$obj->getNome() :
              $this->get('translator')->trans('label.tutti_docenti'));
          },
        'choice_value' => function ($obj) {
            return (is_object($obj) ? $obj->getId() : $obj);
          },
        'placeholder' => 'label.scegli_docente',
        'choice_translation_domain' => false,
        'label_attr' => ['class' => 'sr-only'],
        'required' => false))
        ->add('inizio', DateType::class, array('label' => 'label.data_inizio',
          'data' => $inizio,
          'widget' => 'single_text',
          'html5' => false,
          'attr' => ['widget' => 'gs-picker'],
          'format' => 'dd/MM/yyyy',
          'required' => false))
        ->add('fine', DateType::class, array('label' => 'label.data_fine',
          'data' => $fine,
          'widget' => 'single_text',
          'html5' => false,
          'attr' => ['widget' => 'gs-picker'],
          'format' => 'dd/MM/yyyy',
          'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.search'))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $search['docente'] = (is_object($form->get('docente')->getData()) ? $form->get('docente')->getData()->getId() :
        ($form->get('docente')->getData() < 0 ? -1 : null));
      $search['inizio'] = ($form->get('inizio')->getData() ? $form->get('inizio')->getData()->format('Y-m-d') : 0);
      $search['fine'] = ($form->get('fine')->getData() ? $form->get('fine')->getData()->format('Y-m-d') : 0);
      $docente = ($search['docente'] > 0 ? $em->getRepository('AppBundle:Docente')->find($search['docente']) :
        ($search['docente'] < 0 ? -1 : null));
      $inizio = ($form->get('inizio')->getData() ? $form->get('inizio')->getData() : new \DateTime());
      $fine = ($form->get('fine')->getData() ? $form->get('fine')->getData() : new \DateTime());
      $pagina = 1;
      $session->set('/APP/ROUTE/staff_statistiche/docente', $search['docente']);
      $session->set('/APP/ROUTE/staff_statistiche/inizio', $search['inizio']);
      $session->set('/APP/ROUTE/staff_statistiche/fine', $search['fine']);
      $session->set('/APP/ROUTE/staff_statistiche/pagina', $pagina);
    }
    // statistiche
    $lista = $staff->statistiche($docente, $inizio, $fine, $pagina, $limite);
    // mostra la pagina di risposta
    return $this->render('ruolo_staff/statistiche.html.twig', array(
      'pagina_titolo' => 'page.staff_statistiche',
      'form' => $form->createView(),
      'lista' => $lista,
      'page' => $pagina,
      'maxPages' => ceil($lista->count() / $limite),
    ));
  }

  /**
   * Gestione degli avvisi generici da parte dello staff
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param BachecaUtil $bac Funzioni di utilità per la gestione della bacheca
   * @param int $pagina Numero di pagina per l'elenco da visualizzare
   *
   * @return Response Pagina di risposta
   *
   * @Route("/staff/avvisi/{pagina}", name="staff_avvisi",
   *    requirements={"pagina": "\d+"},
   *    defaults={"pagina": "0"})
   * @Method({"GET","POST"})
   *
   * @Security("has_role('ROLE_STAFF')")
   */
  public function avvisiAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                BachecaUtil $bac, $pagina) {
    // inizializza variabili
    $dati = null;
    // recupera criteri dalla sessione
    $search = array();
    $search['docente'] = $session->get('/APP/ROUTE/staff_avvisi/docente', 0);
    $search['destinatari'] = $session->get('/APP/ROUTE/staff_avvisi/destinatari', '');
    $search['classe'] = $session->get('/APP/ROUTE/staff_avvisi/classe', 0);
    $docente = ($search['docente'] > 0 ? $em->getRepository('AppBundle:Docente')->find($search['docente']) : 0);
    $classe = ($search['classe'] > 0 ? $em->getRepository('AppBundle:Classe')->find($search['classe']) : 0);
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $session->get('/APP/ROUTE/staff_avvisi/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $session->set('/APP/ROUTE/staff_avvisi/pagina', $pagina);
    }
    // form di ricerca
    $limite = 25;
    $form = $this->container->get('form.factory')->createNamedBuilder('staff_avvisi', FormType::class)
      ->add('docente', EntityType::class, array('label' => 'label.autore',
        'data' => $docente,
        'class' => 'AppBundle:Staff',
        'choice_label' => function ($obj) {
            return $obj->getCognome().' '.$obj->getNome();
          },
        'placeholder' => 'label.tutti_staff',
        'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('d')
              ->where('d.abilitato=:abilitato')
              ->orderBy('d.cognome,d.nome', 'ASC')
              ->setParameters(['abilitato' => 1]);
          },
        'label_attr' => ['class' => 'sr-only'],
        'choice_attr' => function($val, $key, $index) {
            return ['class' => 'gs-no-placeholder'];
          },
        'attr' => ['class' => 'gs-placeholder'],
        'required' => false))
      ->add('destinatari', ChoiceType::class, array('label' => 'label.destinatari',
        'data' => $search['destinatari'] ? $search['destinatari'] : '',
        'choices' => ['label.coordinatori' => 'C', 'label.docenti' => 'D',
          'label.genitori' => 'G', 'label.alunni' => 'A'],
        'placeholder' => 'label.tutti_destinatari',
        'label_attr' => ['class' => 'sr-only'],
        'choice_attr' => function($val, $key, $index) {
            return ['class' => 'gs-no-placeholder'];
          },
        'attr' => ['class' => 'gs-placeholder'],
        'required' => false))
      ->add('classe', EntityType::class, array('label' => 'label.classe',
        'data' => $classe,
        'class' => 'AppBundle:Classe',
        'choice_label' => function ($obj) {
            return $obj->getAnno().'ª '.$obj->getSezione();
          },
        'placeholder' => 'label.qualsiasi_classe',
        'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('c')
              ->orderBy('c.anno,c.sezione', 'ASC');
          },
        'group_by' => function ($obj) {
            return $obj->getSede()->getCitta();
          },
        'label_attr' => ['class' => 'sr-only'],
        'choice_attr' => function($val, $key, $index) {
            return ['class' => 'gs-no-placeholder'];
          },
        'attr' => ['class' => 'gs-placeholder'],
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.search'))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $search['docente'] = (is_object($form->get('docente')->getData()) ? $form->get('docente')->getData()->getId() : 0);
      $search['destinatari'] = ($form->get('destinatari')->getData() ? $form->get('destinatari')->getData() : '');
      $search['classe'] = (is_object($form->get('classe')->getData()) ? $form->get('classe')->getData()->getId() : 0);
      $pagina = 1;
      $session->set('/APP/ROUTE/staff_avvisi/docente', $search['docente']);
      $session->set('/APP/ROUTE/staff_avvisi/destinatari', $search['destinatari']);
      $session->set('/APP/ROUTE/staff_avvisi/classe', $search['classe']);
      $session->set('/APP/ROUTE/staff_avvisi/pagina', $pagina);
    }
    // recupera dati
    $dati = $bac->listaAvvisi($search, $pagina, $limite, $this->getUser(), 'C');
    // mostra la pagina di risposta
    return $this->render('ruolo_staff/avvisi.html.twig', array(
      'pagina_titolo' => 'page.staff_avvisi',
      'form' => $form->createView(),
      'form_help' => null,
      'form_success' => null,
      'page' => $pagina,
      'maxPages' => ceil($dati['lista']->count() / $limite),
      'dati' => $dati,
    ));
  }

  /**
   * Aggiunge o modifica un avviso
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param BachecaUtil $bac Funzioni di utilità per la gestione della bacheca
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $id Identificativo dell'avviso
   *
   * @return Response Pagina di risposta
   *
   * @Route("/staff/avvisi/edit/{id}", name="staff_avviso_edit",
   *    requirements={"id": "\d+"},
   *    defaults={"id": "0"})
   * @Method({"GET","POST"})
   *
   * @Security("has_role('ROLE_STAFF')")
   */
  public function avvisoEditAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                    BachecaUtil $bac, RegistroUtil $reg, LogHandler $dblogger, $id) {
    // inizializza
    $var_sessione = '/APP/FILE/staff_avviso_edit/files';
    $dir = $this->getParameter('kernel.project_dir').'/documenti/';
    $fs = new FileSystem();
    // controlla azione
    if ($id > 0) {
      // azione edit
      $avviso = $em->getRepository('AppBundle:Avviso')->findOneBy(['id' => $id, 'tipo' => 'C']);
      if (!$avviso) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $avviso_old = clone $avviso;
    } else {
      // azione add
      $avviso = (new Avviso())
        ->setTipo('C')
        ->setDestinatariStaff(false)
        ->setDestinatariCoordinatori(false)
        ->setDestinatariDocenti(false)
        ->setDestinatariGenitori(false)
        ->setDestinatariAlunni(false)
        ->setDestinatariIndividuali(false);
      $em->persist($avviso);
    }
    // legge allegati
    $allegati = array();
    if ($request->isMethod('POST')) {
      // pagina inviata
      foreach ($session->get($var_sessione, []) as $f) {
        if ($f['type'] != 'removed') {
          // aggiunge allegato
          $allegati[] = $f;
        }
      }
    } else {
      // pagina iniziale
      foreach ($avviso->getAllegati() as $k=>$a) {
        $f = new File($dir.'avvisi/'.$a);
        $allegati[$k]['type'] = 'existent';
        $allegati[$k]['temp'] = $avviso->getId().'-'.$k.'.ID';
        $allegati[$k]['name'] = $a;
        $allegati[$k]['size'] = $f->getSize();
      }
      // modifica dati sessione
      $session->remove($var_sessione);
      $session->set($var_sessione, $allegati);
      // elimina file temporanei
      $finder = new Finder();
      $finder->in($dir.'tmp')->date('< 1 day ago');
      foreach ($finder as $f) {
        $fs->remove($f);
      }
    }
    // legge destinatari di filtri
    $dest_filtro = $bac->filtriAvviso($avviso);
    // imposta autore dell'avviso
    $avviso->setDocente($this->getUser());
    // imposta lettura non avvenuta (per avviso modificato)
    foreach ($dest_filtro['classi'] as $k=>$v) {
      $dest_filtro['classi'][$k]['lettoAlunni'] = null;
      $dest_filtro['classi'][$k]['lettoCoordinatore'] = null;
    }
    foreach ($dest_filtro['utenti'] as $k=>$v) {
      $dest_filtro['genitori'][$k]['letto'] = null;
    }
    // opzione scelta per staff
    $scelta_staff_filtro = 'N';
    $scelta_staff_sedi = array();
    if ($avviso->getDestinatariStaff()) {
      $scelta_staff_filtro = 'S';
      foreach (array_column($dest_filtro['sedi'], 'sede') as $s) {
        $sede = $em->getRepository('AppBundle:Sede')->find($s);
        if ($sede) {
          $scelta_staff_sedi[] = $sede;
        }
      }
    }
    // opzione scelta destinatari
    $scelta_destinatari = array();
    if ($avviso->getDestinatariCoordinatori()) {
      $scelta_destinatari[] = 'C';
    }
    if ($avviso->getDestinatariDocenti()) {
      $scelta_destinatari[] = 'D';
    }
    if ($avviso->getDestinatariGenitori()) {
      $scelta_destinatari[] = 'G';
    }
    if ($avviso->getDestinatariAlunni()) {
      $scelta_destinatari[] = 'A';
    }
    // opzione scelta filtro
    $scelta_filtro = 'N';
    $scelta_filtro_sedi = array();
    $scelta_filtro_classi = array();
    $scelta_filtro_individuale = array();
    $scelta_filtro_individuale_classe = null;
    if ($avviso->getDestinatariIndividuali()) {
      $scelta_filtro = 'I';
      foreach (array_column($dest_filtro['utenti'], 'alunno') as $a) {
        $alunno = $em->getRepository('AppBundle:Alunno')->find($a);
        if ($alunno) {
          $scelta_filtro_individuale[] = $alunno->getId();
          $scelta_filtro_individuale_classe = $alunno->getClasse();
        }
      }
    } elseif (!empty($scelta_destinatari)) {
      $scelta_filtro = 'C';
      foreach (array_column($dest_filtro['classi'], 'classe') as $c) {
        $classe = $em->getRepository('AppBundle:Classe')->find($c);
        if ($classe) {
          $scelta_filtro_classi[] = $classe;
        }
      }
    }
    // form di inserimento
    $form = $this->container->get('form.factory')->createNamedBuilder('avviso_edit', FormType::class, $avviso)
      ->add('data', DateType::class, array('label' => 'label.data_evento',
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-picker'],
        'format' => 'dd/MM/yyyy',
        'required' => true))
      ->add('oggetto', TextType::class, array(
        'label' => 'label.oggetto',
        'required' => true))
      ->add('testo', TextareaType::class, array(
        'label' => 'label.testo',
        'attr' => array('rows' => '4'),
        'required' => true))
      ->add('creaAnnotazione', ChoiceType::class, array('label' => 'label.crea_annotazione',
        'data' => (count($avviso->getAnnotazioni()) > 0),
        'choices' => ['label.si' => true, 'label.no' => false],
        'expanded' => true,
        'multiple' => false,
        'label_attr' => ['class' => 'radio-inline'],
        'mapped' => false,
        'required' => true))
      //-- ->add('staffFiltro', ChoiceType::class, array('label' => false,
        //-- 'data' => $scelta_staff_filtro,
        //-- 'choices' => ['label.filtro_nessuno' => 'N', 'label.filtro_tutti' => 'T', 'label.filtro_sede' => 'S'],
        //-- 'expanded' => false,
        //-- 'multiple' => false,
        //-- 'mapped' => false,
        //-- 'required' => true))
      //-- ->add('staffSedi', EntityType::class, array('label' => false,
        //-- 'data' => $scelta_staff_sedi,
        //-- 'class' => 'AppBundle:Sede',
        //-- 'choice_label' => function ($obj) {
            //-- return $obj->getCitta();
          //-- },
        //-- 'query_builder' => function (EntityRepository $er) {
            //-- return $er->createQueryBuilder('s')
              //-- ->orderBy('s.principale', 'DESC')
              //-- ->addOrderBy('s.citta', 'ASC');
          //-- },
        //-- 'expanded' => true,
        //-- 'multiple' => true,
        //-- 'choice_translation_domain' => false,
        //-- 'label_attr' => ['class' => 'gs-pt-0 checkbox-split-vertical'],
        //-- 'mapped' => false,
        //-- 'required' => false))
      ->add('destinatari', ChoiceType::class, array('label' => false,
        'data' => $scelta_destinatari,
        'choices' => ['label.coordinatori' => 'C', 'label.docenti' => 'D',
          'label.genitori' => 'G', 'label.leggere_in_classe' => 'A'],
        'expanded' => true,
        'multiple' => true,
        'label_attr' => ['class' => 'gs-mr-4 gs-checkbox-inline'],
        'mapped' => false,
        'required' => true))
      ->add('filtro', ChoiceType::class, array('label' => false,
        'data' => $scelta_filtro,
        'choices' => ['label.filtro_nessuno' => 'N', 'label.filtro_tutti' => 'T', 'label.filtro_sede' => 'S',
          'label.filtro_classe' => 'C', 'label.filtro_individuale' => 'I'],
        'expanded' => false,
        'multiple' => false,
        'mapped' => false,
        'required' => true))
      ->add('filtroSedi', EntityType::class, array('label' => false,
        'data' => $scelta_filtro_sedi,
        'class' => 'AppBundle:Sede',
        'choice_label' => function ($obj) {
            return $obj->getCitta();
          },
        'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('s')
              ->orderBy('s.principale', 'DESC')
              ->addOrderBy('s.citta', 'ASC');
          },
        'expanded' => true,
        'multiple' => true,
        'choice_translation_domain' => false,
        'label_attr' => ['class' => 'gs-pt-0 checkbox-split-vertical'],
        'mapped' => false,
        'required' => false))
      ->add('filtroClassi', EntityType::class, array('label' => false,
        'data' => $scelta_filtro_classi,
        'class' => 'AppBundle:Classe',
        'choice_label' => function ($obj) {
            return $obj->getAnno().'ª '.$obj->getSezione();
          },
        'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('c')
              ->orderBy('c.anno,c.sezione', 'ASC');
          },
        'group_by' => function ($obj) {
            return $obj->getSede()->getCitta();
          },
        'expanded' => true,
        'multiple' => true,
        'choice_translation_domain' => false,
        'label_attr' => ['class' => 'gs-pt-0 checkbox-grouped-4'],
        'mapped' => false,
        'required' => false))
      ->add('filtroIndividualeClasse', EntityType::class, array('label' => false,
        'data' => $scelta_filtro_individuale_classe,
        'class' => 'AppBundle:Classe',
        'choice_label' => function ($obj) {
            return $obj->getAnno().'ª '.$obj->getSezione().' - '.$obj->getCorso()->getNomeBreve();
          },
        'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('c')
              ->orderBy('c.anno,c.sezione', 'ASC');
          },
        'group_by' => function ($obj) {
            return $obj->getSede()->getCitta();
          },
        'expanded' => false,
        'multiple' => false,
        'placeholder' => 'label.scegli_classe',
        'choice_translation_domain' => false,
        'attr' => ['style' => 'width:auto'],
        'label_attr' => ['class' => 'gs-pt-0 checkbox-split-vertical'],
        'mapped' => false,
        'required' => false))
      ->add('filtroIndividuale',  HiddenType::class, array('label' => false,
        'data' => implode(',', $scelta_filtro_individuale),
        'mapped' => false,
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start']))
      ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end',
        'onclick' => "location.href='".$this->generateUrl('staff_avvisi')."'"]))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // recupera dati
      //-- $val_staff_filtro = $form->get('staffFiltro')->getData();
      //-- $val_staff_sedi = $form->get('staffSedi')->getData();
      $val_staff_filtro = 'N';
      $val_staff_sedi = array();
      $val_destinatari = $form->get('destinatari')->getData();
      $val_filtro = $form->get('filtro')->getData();
      $val_filtro_id = array();
      $val_filtro_individuale_classe = $form->get('filtroIndividualeClasse')->getData();
      switch ($val_filtro) {
        case 'S':
          foreach ($form->get('filtroSedi')->getData() as $f) {
            $val_filtro_id[] = $f->getId();
          }
          break;
        case 'C':
          foreach ($form->get('filtroClassi')->getData() as $f) {
            $val_filtro_id[] = $f->getId();
          }
          break;
        case 'I':
          foreach (explode(',', $form->get('filtroIndividuale')->getData()) as $fid) {
            if ($val_filtro_individuale_classe &&
                $em->getRepository('AppBundle:Alunno')->findOneBy(
                  ['id' => $fid, 'abilitato' => 1, 'classe' => $val_filtro_individuale_classe->getId()])) {
              $val_filtro_id[] = $fid;
            }
          }
          break;
      }
      // controllo errori
      if ($val_staff_filtro == 'N' && count($val_destinatari) == 0) {
        // errore: nessun destinatario
        $form->addError(new FormError($this->get('translator')->trans('exception.destinatari_mancanti')));
      }
      if ($val_staff_filtro == 'S' && count($val_staff_sedi) == 0) {
        // errore: nessun destinatario
        $form->addError(new FormError($this->get('translator')->trans('exception.destinatari_filtro_mancanti')));
      }
      if (count($val_destinatari) > 0  && $val_filtro != 'T' && count($val_filtro_id) == 0) {
        // errore: filtro vuoto
        $form->addError(new FormError($this->get('translator')->trans('exception.destinatari_filtro_mancanti')));
      }
      if ($form->get('creaAnnotazione')->getData() && count($val_destinatari) == 0 && $val_staff_filtro != 'N') {
        // errore: annotazione per solo staff
        $form->addError(new FormError($this->get('translator')->trans('exception.annotazione_solo_staff')));
      }
      if ($form->get('creaAnnotazione')->getData() && count($session->get($var_sessione, [])) > 0) {
        // errore: annotazione con allegati
        $form->addError(new FormError($this->get('translator')->trans('exception.annotazione_con_file')));
      }
      // controllo data
      $errore = $reg->controlloData($form->get('data')->getData(), null);
      if ($errore) {
        // errore: festivo
        $form->addError(new FormError($this->get('translator')->trans('exception.data_festiva')));
      }
      // controllo permessi
      if (!$bac->azioneAvviso(($id > 0 ? 'edit' : 'add'), $avviso->getData(), $this->getUser(), ($id > 0 ? $avviso : null))) {
        // errore: avviso non permesso
        $form->addError(new FormError($this->get('translator')->trans('exception.avviso_non_permesso')));
      }
      if ($form->get('creaAnnotazione')->getData() &&
          !$reg->azioneAnnotazione('add', $avviso->getData(), $this->getUser(), null, null)) {
        // errore: nuova annotazione non permessa
        $form->addError(new FormError($this->get('translator')->trans('exception.annotazione_non_permessa')));
      }
      if (count($avviso->getAnnotazioni()) > 0) {
        $a = $avviso->getAnnotazioni()[0];
        if (!$reg->azioneAnnotazione('delete', $a->getData(), $this->getUser(), $a->getClasse(), $a)) {
          // errore: cancellazione annotazione non permessa
          $form->addError(new FormError($this->get('translator')->trans('exception.annotazione_non_permessa')));
        }
      }
      // modifica dati
      if ($form->isValid()) {
        // allegati
        foreach ($session->get($var_sessione, []) as $f) {
          if ($f['type'] == 'uploaded') {
            // aggiunge allegato
            $fs->rename($dir.'tmp/'.$f['temp'], $dir.'avvisi/'.$f['temp']);
            $avviso->addAllegato(new File($dir.'avvisi/'.$f['temp']));
          } elseif ($f['type'] == 'removed') {
            // rimuove allegato
            $avviso->removeAllegato(new File($dir.'avvisi/'.$f['name']));
            $fs->remove($dir.'avvisi/'.$f['name']);
          }
        }
        // destinatari
        $log_destinatari = $bac->modificaFiltriAvviso($avviso, $dest_filtro, $val_staff_filtro, $val_staff_sedi,
          $val_destinatari, $val_filtro, $val_filtro_id);
        // annotazione
        $log_annotazioni['delete'] = array();
        if ($id) {
          // cancella annotazioni
          foreach ($avviso->getAnnotazioni() as $a) {
            $log_annotazioni['delete'][] = $a->getId();
            $em->remove($a);
          }
          $avviso->setAnnotazioni(new ArrayCollection());
        }
        if ($form->get('creaAnnotazione')->getData()) {
          // crea nuove annotazioni
          $bac->creaAnnotazione($avviso, $val_filtro, $val_filtro_id, $val_filtro_individuale_classe);
        }
        // ok: memorizza dati
        $em->flush();
        // log azione
        if (!$id) {
          // nuovo
          $dblogger->write($this->getUser(), $request->getClientIp(), 'AVVISI', 'Crea avviso generico', __METHOD__, array(
            'Avviso' => $avviso->getId(),
            'Sedi aggiunte' => implode(', ', $log_destinatari['sedi']['add']),
            'Classi aggiunte' => implode(', ', $log_destinatari['classi']['add']),
            'Utenti aggiunti' => implode(', ', array_map(function ($a) {
                return $a['genitore'].'->'.$a['alunno'];
              }, $log_destinatari['utenti']['add'])),
            'Annotazioni' => implode(', ', array_map(function ($a) {
                return $a->getId();
              }, $avviso->getAnnotazioni()->toArray())),
            ));
        } else {
          // modifica
          $dblogger->write($this->getUser(), $request->getClientIp(), 'AVVISI', 'Modifica avviso generico', __METHOD__, array(
            'Id' => $avviso->getId(),
            'Data' => $avviso_old->getData()->format('d/m/Y'),
            'Oggetto' => $avviso_old->getOggetto(),
            'Testo' => $avviso_old->getTesto(),
            'Allegati' => $avviso_old->getAllegati(),
            'Destinatari staff' => $avviso_old->getDestinatariStaff(),
            'Destinatari coordinatori' => $avviso_old->getDestinatariCoordinatori(),
            'Destinatari docenti' => $avviso_old->getDestinatariDocenti(),
            'Destinatari genitori' => $avviso_old->getDestinatariGenitori(),
            'Destinatari alunni' => $avviso_old->getDestinatariAlunni(),
            'Destinatari individuali' => $avviso_old->getDestinatariIndividuali(),
            'Sedi cancellate' => implode(', ', $log_destinatari['sedi']['delete']),
            'Sedi aggiunte' => implode(', ', $log_destinatari['sedi']['add']),
            'Classi cancellate' => implode(', ', $log_destinatari['classi']['delete']),
            'Classi aggiunte' => implode(', ', $log_destinatari['classi']['add']),
            'Utenti cancellati' => implode(', ', array_map(function ($a) {
                return $a['genitore'].'->'.$a['alunno'];
              }, $log_destinatari['utenti']['delete'])),
            'Utenti aggiunti' => implode(', ', array_map(function ($a) {
                return $a['genitore'].'->'.$a['alunno'];
              }, $log_destinatari['utenti']['add'])),
            'Docente' => $avviso_old->getDocente()->getId(),
            'Annotazioni cancellate' => implode(', ', $log_annotazioni['delete']),
            'Annotazioni create' => implode(', ', array_map(function ($a) {
                return $a->getId();
              }, $avviso->getAnnotazioni()->toArray())),
            ));
        }
        // redirezione
        return $this->redirectToRoute('staff_avvisi');
      }
    }
    // mostra la pagina di risposta
    return $this->render('ruolo_staff/avviso_edit.html.twig', array(
      'pagina_titolo' => 'page.staff_avvisi',
      'form' => $form->createView(),
      'form_title' => ($id > 0 ? 'title.modifica_avviso' : 'title.nuovo_avviso'),
      'allegati' => $allegati,
    ));
  }

  /**
   * Mostra i dettagli di un avviso
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param BachecaUtil $bac Funzioni di utilità per la gestione della bacheca
   * @param int $id ID dell'avviso
   *
   * @return Response Pagina di risposta
   *
   * @Route("/staff/avvisi/dettagli/{id}", name="staff_avviso_dettagli",
   *    requirements={"id": "\d+"})
   * @Method("GET")
   *
   * @Security("has_role('ROLE_STAFF')")
   */
  public function avvisoDettagliAction(EntityManagerInterface $em, BachecaUtil $bac, $id) {
    // inizializza
    $dati = null;
    // controllo avviso
    $avviso = $em->getRepository('AppBundle:Avviso')->find($id);
    if (!$avviso) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // legge dati
    $dati = $bac->dettagliAvviso($avviso);
    // visualizza pagina
    return $this->render('ruolo_staff/scheda_avviso.html.twig', array(
      'dati' => $dati,
    ));
  }

  /**
   * Cancella avviso
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param LogHandler $dblogger Gestore dei log su database
   * @param BachecaUtil $bac Funzioni di utilità per la gestione della bacheca
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param string $tipo Tipo dell'avviso
   * @param int $id Identificativo dell'avviso
   *
   * @return Response Pagina di risposta
   *
   * @Route("/staff/avvisi/delete/{tipo}/{id}", name="staff_avviso_delete",
   *    requirements={"tipo": "U|E|V|A|I|C", "id": "\d+"})
   * @Method({"GET"})
   *
   * @Security("has_role('ROLE_STAFF')")
   */
  public function avvisoDeleteAction(Request $request, EntityManagerInterface $em, LogHandler $dblogger, BachecaUtil $bac,
                                      RegistroUtil $reg, $tipo, $id) {
    $dir = $this->getParameter('kernel.project_dir').'/documenti/avvisi/';
    $fs = new FileSystem();
    // controllo avviso
    $avviso = $em->getRepository('AppBundle:Avviso')->findOneBy(['id' => $id, 'tipo' => $tipo]);
    if (!$avviso) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo permessi
    if (!$bac->azioneAvviso('delete', $avviso->getData(), $this->getUser(), $avviso)) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    if (count($avviso->getAnnotazioni()) > 0) {
      $a = $avviso->getAnnotazioni()[0];
      if (!$reg->azioneAnnotazione('delete', $a->getData(), $this->getUser(), $a->getClasse(), $a)) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
    }
    // cancella annotazioni
    $log_annotazioni = array();
    foreach ($avviso->getAnnotazioni() as $a) {
      $log_annotazioni[] = $a->getId();
      $em->remove($a);
    }
    // cancella destinatari
    $log_destinatari = $bac->eliminaFiltriAvviso($avviso);
    // cancella avviso
    $avviso_id = $avviso->getId();
    $em->remove($avviso);
    // ok: memorizza dati
    $em->flush();
    // cancella allegati
    foreach ($avviso->getAllegati() as $a) {
      $f = new File($dir.$a);
      $fs->remove($f);
    }
    // log azione
    $dblogger->write($this->getUser(), $request->getClientIp(), 'AVVISI', 'Cancella avviso', __METHOD__, array(
      'Id' => $avviso_id,
      'Tipo' => $avviso->getTipo(),
      'Data' => $avviso->getData()->format('d/m/Y'),
      'Ora' => ($avviso->getOra() ? $avviso->getOra()->format('H:i') : null),
      'Ora fine' => ($avviso->getOraFine() ? $avviso->getOraFine()->format('H:i') : null),
      'Oggetto' => $avviso->getOggetto(),
      'Testo' => $avviso->getTesto(),
      'Allegati' => $avviso->getAllegati(),
      'Destinatari staff' => $avviso->getDestinatariStaff(),
      'Destinatari coordinatori' => $avviso->getDestinatariCoordinatori(),
      'Destinatari docenti' => $avviso->getDestinatariDocenti(),
      'Destinatari genitori' => $avviso->getDestinatariGenitori(),
      'Destinatari alunni' => $avviso->getDestinatariAlunni(),
      'Destinatari individuali' => $avviso->getDestinatariIndividuali(),
      'Sedi cancellate' => implode(', ', $log_destinatari['sedi']),
      'Classi cancellate' => implode(', ', $log_destinatari['classi']),
      'Utenti cancellati' => implode(', ', array_map(function ($a) {
          return $a['genitore'].'->'.$a['alunno'];
        }, $log_destinatari['utenti'])),
      'Docente' => $avviso->getDocente()->getId(),
      'Annotazioni' => implode(', ', $log_annotazioni),
      ));
    // redirezione
    if ($tipo == 'U' || $tipo == 'E') {
      // orario
      return $this->redirectToRoute('staff_avvisi_orario',  ['tipo' => $tipo]);
    } elseif ($tipo == 'A') {
      // attività
      return $this->redirectToRoute('staff_avvisi_attivita');
    } elseif ($tipo == 'I') {
      // attività
      return $this->redirectToRoute('staff_avvisi_individuali');
    } else {
      // avviso generico
      return $this->redirectToRoute('staff_avvisi');
    }
  }

  /**
   * Gestione degli avvisi sugli orari di ingresso o uscita
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param BachecaUtil $bac Funzioni di utilità per la gestione della bacheca
   * @param int $pagina Numero di pagina per l'elenco da visualizzare
   *
   * @return Response Pagina di risposta
   *
   * @Route("/staff/avvisi/orario/{tipo}/{pagina}", name="staff_avvisi_orario",
   *    requirements={"tipo": "E|U", "pagina": "\d+"},
   *    defaults={"pagina": "0"})
   * @Method({"GET","POST"})
   *
   * @Security("has_role('ROLE_STAFF')")
   */
  public function avvisiOrarioAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                      BachecaUtil $bac, $tipo, $pagina) {
    // inizializza variabili
    $dati = null;
    // recupera criteri dalla sessione
    $search = array();
    $search['docente'] = $session->get('/APP/ROUTE/staff_avvisi_orario_'.$tipo.'/docente', 0);
    $search['classe'] = $session->get('/APP/ROUTE/staff_avvisi_orario_'.$tipo.'/classe', 0);
    $docente = ($search['docente'] > 0 ? $em->getRepository('AppBundle:Docente')->find($search['docente']) : 0);
    $classe = ($search['classe'] > 0 ? $em->getRepository('AppBundle:Classe')->find($search['classe']) : 0);
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $session->get('/APP/ROUTE/staff_avvisi_orario_'.$tipo.'/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $session->set('/APP/ROUTE/staff_avvisi_orario_'.$tipo.'/pagina', $pagina);
    }
    // form di ricerca
    $limite = 25;
    $form = $this->container->get('form.factory')->createNamedBuilder('staff_avvisi_orario', FormType::class)
      ->add('docente', EntityType::class, array('label' => 'label.autore',
        'data' => $docente,
        'class' => 'AppBundle:Staff',
        'choice_label' => function ($obj) {
            return $obj->getCognome().' '.$obj->getNome();
          },
        'placeholder' => 'label.tutti_staff',
        'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('d')
              ->where('d.abilitato=:abilitato')
              ->orderBy('d.cognome,d.nome', 'ASC')
              ->setParameters(['abilitato' => 1]);
          },
        'label_attr' => ['class' => 'sr-only'],
        'choice_attr' => function($val, $key, $index) {
            return ['class' => 'gs-no-placeholder'];
          },
        'attr' => ['class' => 'gs-placeholder'],
        'required' => false))
      ->add('classe', EntityType::class, array('label' => 'label.classe',
        'data' => $classe,
        'class' => 'AppBundle:Classe',
        'choice_label' => function ($obj) {
            return $obj->getAnno().'ª '.$obj->getSezione();
          },
        'placeholder' => 'label.qualsiasi_classe',
        'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('c')
              ->orderBy('c.anno,c.sezione', 'ASC');
          },
        'group_by' => function ($obj) {
            return $obj->getSede()->getCitta();
          },
        'label_attr' => ['class' => 'sr-only'],
        'choice_attr' => function($val, $key, $index) {
            return ['class' => 'gs-no-placeholder'];
          },
        'attr' => ['class' => 'gs-placeholder'],
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.search'))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $search['docente'] = (is_object($form->get('docente')->getData()) ? $form->get('docente')->getData()->getId() : 0);
      $search['classe'] = (is_object($form->get('classe')->getData()) ? $form->get('classe')->getData()->getId() : 0);
      $pagina = 1;
      $session->set('/APP/ROUTE/staff_avvisi_orario_'.$tipo.'/docente', $search['docente']);
      $session->set('/APP/ROUTE/staff_avvisi_orario_'.$tipo.'/classe', $search['classe']);
      $session->set('/APP/ROUTE/staff_avvisi_orario_'.$tipo.'/pagina', $pagina);
    }
    // recupera dati
    $dati = $bac->listaAvvisi($search, $pagina, $limite, $this->getUser(), $tipo);
    // mostra la pagina di risposta
    return $this->render('ruolo_staff/avvisi_orario.html.twig', array(
      'pagina_titolo' => ($tipo == 'E' ? 'page.staff_avvisi_entrate' : 'page.staff_avvisi_uscite'),
      'form' => $form->createView(),
      'form_help' => null,
      'form_success' => null,
      'page' => $pagina,
      'maxPages' => ceil($dati['lista']->count() / $limite),
      'dati' => $dati,
      'tipo' => $tipo,
    ));
  }

  /**
   * Aggiunge o modifica un avviso sulla modifica di orario di ingresso o uscita
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param BachecaUtil $bac Funzioni di utilità per la gestione della bacheca
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param LogHandler $dblogger Gestore dei log su database
   * @param string $tipo Tipo di modifica dell'orario [E=entrata, U=uscita]
   * @param int $id Identificativo dell'avviso
   *
   * @return Response Pagina di risposta
   *
   * @Route("/staff/avvisi/orario/edit/{tipo}/{id}", name="staff_avviso_orario_edit",
   *    requirements={"tipo": "E|U", "id": "\d+"},
   *    defaults={"id": "0"})
   * @Method({"GET","POST"})
   *
   * @Security("has_role('ROLE_STAFF')")
   */
  public function avvisoOrarioEditAction(Request $request, EntityManagerInterface $em, BachecaUtil $bac,
                                          RegistroUtil $reg, LogHandler $dblogger, $tipo, $id) {
    // controlla azione
    if ($id > 0) {
      // azione edit
      $avviso = $em->getRepository('AppBundle:Avviso')->findOneBy(['id' => $id, 'tipo' => $tipo]);
      if (!$avviso) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $avviso_old = clone $avviso;
    } else {
      // azione add
      $avviso = (new Avviso())
        ->setTipo($tipo)
        ->setDestinatariStaff(false)
        ->setDestinatariCoordinatori(false)
        ->setDestinatariDocenti(true)
        ->setDestinatariGenitori(true)
        ->setDestinatariAlunni(true)
        ->setDestinatariIndividuali(false)
        ->setData(new \DateTime('tomorrow'))
        ->setOra(\DateTime::createFromFormat('H:i', ($tipo == 'E' ? '09:20' : '12:50')))
        ->setOggetto($this->get('translator')->trans($tipo == 'E' ? 'message.avviso_entrata_oggetto' :
          'message.avviso_uscita_oggetto'))
        ->setTesto($this->get('translator')->trans($tipo == 'E' ? 'message.avviso_entrata_testo' :
          'message.avviso_uscita_testo'));
      $em->persist($avviso);
    }
    // legge destinatari di filtri
    $dest_filtro = $bac->filtriAvviso($avviso);
    // imposta autore dell'avviso
    $avviso->setDocente($this->getUser());
    // imposta lettura non avvenuta (per avviso modificato)
    foreach ($dest_filtro['classi'] as $k=>$v) {
      $dest_filtro['classi'][$k]['lettoAlunni'] = null;
      $dest_filtro['classi'][$k]['lettoCoordinatore'] = null;
    }
    // opzione scelta filtro
    $scelta_filtro = 'C';
    $scelta_filtro_sedi = array();
    $scelta_filtro_classi = array();
    foreach (array_column($dest_filtro['classi'], 'classe') as $c) {
      $classe = $em->getRepository('AppBundle:Classe')->find($c);
      if ($classe) {
        $scelta_filtro_classi[] = $classe;
      }
    }
    // form di inserimento
    $form = $this->container->get('form.factory')->createNamedBuilder('avviso_orario_edit', FormType::class, $avviso)
      ->add('data', DateType::class, array('label' => 'label.data',
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-picker'],
        'format' => 'dd/MM/yyyy',
        'required' => true))
      ->add('ora', TimeType::class, array('label' => ($tipo == 'E' ? 'label.ora_entrata' : 'label.ora_uscita'),
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-picker'],
        'required' => true))
      ->add('testo', TextareaType::class, array(
        'label' => 'label.testo',
        'attr' => array('rows' => '4'),
        'required' => true))
      ->add('filtro', ChoiceType::class, array('label' => false,
        'data' => $scelta_filtro,
        'choices' => ['label.filtro_tutti' => 'T', 'label.filtro_sede' => 'S', 'label.filtro_classe' => 'C'],
        'expanded' => false,
        'multiple' => false,
        'mapped' => false,
        'required' => true))
      ->add('filtroSedi', EntityType::class, array('label' => false,
        'data' => $scelta_filtro_sedi,
        'class' => 'AppBundle:Sede',
        'choice_label' => function ($obj) {
            return $obj->getCitta();
          },
        'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('s')
              ->orderBy('s.principale', 'DESC')
              ->addOrderBy('s.citta', 'ASC');
          },
        'expanded' => true,
        'multiple' => true,
        'choice_translation_domain' => false,
        'label_attr' => ['class' => 'gs-pt-0 checkbox-split-vertical'],
        'mapped' => false,
        'required' => false))
      ->add('filtroClassi', EntityType::class, array('label' => 'ok_false',
        'data' => $scelta_filtro_classi,
        'class' => 'AppBundle:Classe',
        'choice_label' => function ($obj) {
            return $obj->getAnno().'ª '.$obj->getSezione();
          },
        'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('c')
              ->orderBy('c.anno,c.sezione', 'ASC');
          },
        'group_by' => function ($obj) {
            return $obj->getSede()->getCitta();
          },
        'expanded' => true,
        'multiple' => true,
        'choice_translation_domain' => false,
        'label_attr' => ['class' => 'gs-pt-0 checkbox-grouped-4'],
        'mapped' => false,
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start']))
      ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end',
        'onclick' => "location.href='".$this->generateUrl('staff_avvisi_orario', ['tipo' => $tipo])."'"]))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // recupera dati
      $val_staff_filtro = 'N';
      $val_staff_sedi = [];
      $val_destinatari = ['D', 'G', 'A'];
      $val_filtro = $form->get('filtro')->getData();
      $val_filtro_id = [];
      $val_filtro_individuale_classe = 0;
      switch ($val_filtro) {
        case 'S':
          foreach ($form->get('filtroSedi')->getData() as $f) {
            $val_filtro_id[] = $f->getId();
          }
          break;
        case 'C':
          foreach ($form->get('filtroClassi')->getData() as $f) {
            $val_filtro_id[] = $f->getId();
          }
          break;
      }
      // controllo errori
      if ($val_filtro != 'T' && count($val_filtro_id) == 0) {
        // errore: filtro vuoto
        $form->addError(new FormError($this->get('translator')->trans('exception.destinatari_filtro_mancanti')));
      }
      // controllo data
      $errore = $reg->controlloData($form->get('data')->getData(), null);
      if ($errore) {
        // errore: festivo
        $form->addError(new FormError($this->get('translator')->trans('exception.data_festiva')));
      }
      // controllo testo
      if (strpos($form->get('testo')->getData(), '%DATA%') === false) {
        // errore: testo senza campo data
        $form->addError(new FormError($this->get('translator')->trans('exception.campo_data_mancante')));
      }
      // controllo permessi
      if (!$bac->azioneAvviso(($id > 0 ? 'edit' : 'add'), $avviso->getData(), $this->getUser(), ($id > 0 ? $avviso : null))) {
        // errore: avviso non permesso
        $form->addError(new FormError($this->get('translator')->trans('exception.avviso_non_permesso')));
      }
      if (!$reg->azioneAnnotazione('add', $avviso->getData(), $this->getUser(), null, null)) {
        // errore: nuova annotazione non permessa
        $form->addError(new FormError($this->get('translator')->trans('exception.annotazione_non_permessa')));
      }
      if (count($avviso->getAnnotazioni()) > 0) {
        $a = $avviso->getAnnotazioni()[0];
        if (!$reg->azioneAnnotazione('delete', $a->getData(), $this->getUser(), $a->getClasse(), $a)) {
          // errore: cancellazione annotazione non permessa
          $form->addError(new FormError($this->get('translator')->trans('exception.annotazione_non_permessa')));
        }
      }
      // modifica dati
      if ($form->isValid()) {
        // destinatari
        $log_destinatari = $bac->modificaFiltriAvviso($avviso, $dest_filtro, $val_staff_filtro, $val_staff_sedi,
          $val_destinatari, $val_filtro, $val_filtro_id);
        // annotazione
        $log_annotazioni['delete'] = array();
        if ($id) {
          // cancella annotazioni
          foreach ($avviso->getAnnotazioni() as $a) {
            $log_annotazioni['delete'][] = $a->getId();
            $em->remove($a);
          }
          $avviso->setAnnotazioni(new ArrayCollection());
        }
        // crea nuove annotazioni
        $bac->creaAnnotazione($avviso, $val_filtro, $val_filtro_id, $val_filtro_individuale_classe);
        // ok: memorizza dati
        $em->flush();
        // log azione
        if (!$id) {
          // nuovo
          $dblogger->write($this->getUser(), $request->getClientIp(), 'AVVISI', 'Crea avviso '.($tipo == 'E' ? 'entrata' : 'uscita'), __METHOD__, array(
            'Avviso' => $avviso->getId(),
            'Classi aggiunte' => implode(', ', $log_destinatari['classi']['add']),
            'Annotazioni' => implode(', ', array_map(function ($a) {
                return $a->getId();
              }, $avviso->getAnnotazioni()->toArray())),
            ));
        } else {
          // modifica
          $dblogger->write($this->getUser(), $request->getClientIp(), 'AVVISI', 'Modifica avviso '.($tipo == 'E' ? 'entrata' : 'uscita'), __METHOD__, array(
            'Id' => $avviso->getId(),
            'Data' => $avviso_old->getData()->format('d/m/Y'),
            'Ora' => $avviso_old->getOra()->format('H:i'),
            'Testo' => $avviso_old->getTesto(),
            'Classi cancellate' => implode(', ', $log_destinatari['classi']['delete']),
            'Classi aggiunte' => implode(', ', $log_destinatari['classi']['add']),
            'Docente' => $avviso_old->getDocente()->getId(),
            'Annotazioni cancellate' => implode(', ', $log_annotazioni['delete']),
            'Annotazioni create' => implode(', ', array_map(function ($a) {
                return $a->getId();
              }, $avviso->getAnnotazioni()->toArray())),
            ));
        }
        // redirezione
        return $this->redirectToRoute('staff_avvisi_orario', ['tipo' => $tipo]);
      }
    }
    // mostra la pagina di risposta
    if ($id > 0) {
      $title = ($tipo == 'E' ? 'title.modifica_avviso_entrate' : 'title.modifica_avviso_uscite');
    } else {
      $title = ($tipo == 'E' ? 'title.nuovo_avviso_entrate' : 'title.nuovo_avviso_uscite');
    }
    return $this->render('ruolo_staff/avviso_orario_edit.html.twig', array(
      'pagina_titolo' => ($tipo == 'E' ? 'page.staff_avvisi_entrate' : 'page.staff_avvisi_uscite'),
      'form' => $form->createView(),
      'form_title' => $title,
    ));
  }

  /**
   * Restituisce gli alunni della classe indicata
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param int $id Identificativo della classe
   *
   * @return JsonResponse Informazioni di risposta
   *
   * @Route("/staff/classe/{id}", name="staff_classe",
   *    requirements={"id": "\d+"},
   *    defaults={"id": 0})
   * @Method("GET")
   *
   * @Security("has_role('ROLE_STAFF')")
   */
  public function classeAjaxAction(EntityManagerInterface $em, $id) {
    $alunni = $em->getRepository('AppBundle:Alunno')->createQueryBuilder('a')
      ->select("a.id,CONCAT(a.cognome,' ',a.nome) AS nome")
      ->where('a.classe=:classe AND a.abilitato=:abilitato')
      ->setParameters(['classe' => $id, 'abilitato' => 1])
      ->orderBy('a.cognome,a.nome', 'ASC')
      ->getQuery()
      ->getArrayResult();
    // restituisce dati
    return new JsonResponse($alunni);
  }

  /**
   * Gestione degli avvisi sulle attività
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param BachecaUtil $bac Funzioni di utilità per la gestione della bacheca
   * @param int $pagina Numero di pagina per l'elenco da visualizzare
   *
   * @return Response Pagina di risposta
   *
   * @Route("/staff/avvisi/attivita/{pagina}", name="staff_avvisi_attivita",
   *    requirements={"pagina": "\d+"},
   *    defaults={"pagina": "0"})
   * @Method({"GET","POST"})
   *
   * @Security("has_role('ROLE_STAFF')")
   */
  public function avvisiAttivitaAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                        BachecaUtil $bac, $pagina) {
    // inizializza variabili
    $dati = null;
    // recupera criteri dalla sessione
    $search = array();
    $search['docente'] = $session->get('/APP/ROUTE/staff_avvisi_attivita/docente', 0);
    $search['classe'] = $session->get('/APP/ROUTE/staff_avvisi_attivita/classe', 0);
    $docente = ($search['docente'] > 0 ? $em->getRepository('AppBundle:Docente')->find($search['docente']) : 0);
    $classe = ($search['classe'] > 0 ? $em->getRepository('AppBundle:Classe')->find($search['classe']) : 0);
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $session->get('/APP/ROUTE/staff_avvisi_attivita/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $session->set('/APP/ROUTE/staff_avvisi_attivita/pagina', $pagina);
    }
    // form di ricerca
    $limite = 25;
    $form = $this->container->get('form.factory')->createNamedBuilder('staff_avvisi_attivita', FormType::class)
      ->add('docente', EntityType::class, array('label' => 'label.autore',
        'data' => $docente,
        'class' => 'AppBundle:Staff',
        'choice_label' => function ($obj) {
            return $obj->getCognome().' '.$obj->getNome();
          },
        'placeholder' => 'label.tutti_staff',
        'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('d')
              ->where('d.abilitato=:abilitato')
              ->orderBy('d.cognome,d.nome', 'ASC')
              ->setParameters(['abilitato' => 1]);
          },
        'label_attr' => ['class' => 'sr-only'],
        'choice_attr' => function($val, $key, $index) {
            return ['class' => 'gs-no-placeholder'];
          },
        'attr' => ['class' => 'gs-placeholder'],
        'required' => false))
      ->add('classe', EntityType::class, array('label' => 'label.classe',
        'data' => $classe,
        'class' => 'AppBundle:Classe',
        'choice_label' => function ($obj) {
            return $obj->getAnno().'ª '.$obj->getSezione();
          },
        'placeholder' => 'label.qualsiasi_classe',
        'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('c')
              ->orderBy('c.anno,c.sezione', 'ASC');
          },
        'group_by' => function ($obj) {
            return $obj->getSede()->getCitta();
          },
        'label_attr' => ['class' => 'sr-only'],
        'choice_attr' => function($val, $key, $index) {
            return ['class' => 'gs-no-placeholder'];
          },
        'attr' => ['class' => 'gs-placeholder'],
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.search'))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $search['docente'] = (is_object($form->get('docente')->getData()) ? $form->get('docente')->getData()->getId() : 0);
      $search['classe'] = (is_object($form->get('classe')->getData()) ? $form->get('classe')->getData()->getId() : 0);
      $pagina = 1;
      $session->set('/APP/ROUTE/staff_avvisi_attivita/docente', $search['docente']);
      $session->set('/APP/ROUTE/staff_avvisi_attivita/classe', $search['classe']);
      $session->set('/APP/ROUTE/staff_avvisi_attivita/pagina', $pagina);
    }
    // recupera dati
    $dati = $bac->listaAvvisi($search, $pagina, $limite, $this->getUser(), 'A');
    // mostra la pagina di risposta
    return $this->render('ruolo_staff/avvisi_attivita.html.twig', array(
      'pagina_titolo' => 'page.staff_avvisi_attivita',
      'form' => $form->createView(),
      'form_help' => null,
      'form_success' => null,
      'page' => $pagina,
      'maxPages' => ceil($dati['lista']->count() / $limite),
      'dati' => $dati,
    ));
  }

  /**
   * Aggiunge o modifica un avviso per le attività
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param BachecaUtil $bac Funzioni di utilità per la gestione della bacheca
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param LogHandler $dblogger Gestore dei log su database
   *
   * @return Response Pagina di risposta
   *
   * @Route("/staff/avvisi/attivita/edit/{id}", name="staff_avviso_attivita_edit",
   *    requirements={"id": "\d+"},
   *    defaults={"id": "0"})
   * @Method({"GET","POST"})
   *
   * @Security("has_role('ROLE_STAFF')")
   */
  public function avvisoAttivitaEditAction(Request $request, EntityManagerInterface $em, BachecaUtil $bac,
                                            RegistroUtil $reg, LogHandler $dblogger, $id) {
    // controlla azione
    if ($id > 0) {
      // azione edit
      $avviso = $em->getRepository('AppBundle:Avviso')->findOneBy(['id' => $id, 'tipo' => 'A']);
      if (!$avviso) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $avviso_old = clone $avviso;
    } else {
      // azione add
      $avviso = (new Avviso())
        ->setTipo('A')
        ->setDestinatariStaff(false)
        ->setDestinatariCoordinatori(false)
        ->setDestinatariDocenti(true)
        ->setDestinatariGenitori(true)
        ->setDestinatariAlunni(true)
        ->setDestinatariIndividuali(false)
        ->setData(new \DateTime('tomorrow'))
        ->setOra(\DateTime::createFromFormat('H:i', '08:20'))
        ->setOraFine(\DateTime::createFromFormat('H:i', '13:50'))
        ->setOggetto($this->get('translator')->trans('message.avviso_attivita_oggetto'))
        ->setTesto($this->get('translator')->trans('message.avviso_attivita_testo'));
      $em->persist($avviso);
    }
    // legge destinatari di filtri
    $dest_filtro = $bac->filtriAvviso($avviso);
    // imposta autore dell'avviso
    $avviso->setDocente($this->getUser());
    // imposta lettura non avvenuta (per avviso modificato)
    foreach ($dest_filtro['classi'] as $k=>$v) {
      $dest_filtro['classi'][$k]['lettoAlunni'] = null;
      $dest_filtro['classi'][$k]['lettoCoordinatore'] = null;
    }
    // opzione scelta filtro
    $scelta_filtro = 'C';
    $scelta_filtro_sedi = array();
    $scelta_filtro_classi = array();
    foreach (array_column($dest_filtro['classi'], 'classe') as $c) {
      $classe = $em->getRepository('AppBundle:Classe')->find($c);
      if ($classe) {
        $scelta_filtro_classi[] = $classe;
      }
    }
    // form di inserimento
    $form = $this->container->get('form.factory')->createNamedBuilder('avviso_attivita_edit', FormType::class, $avviso)
      ->add('data', DateType::class, array('label' => 'label.data_evento',
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-picker'],
        'format' => 'dd/MM/yyyy',
        'required' => true))
      ->add('ora', TimeType::class, array('label' => 'label.ora_inizio',
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-picker'],
        'required' => true))
      ->add('oraFine', TimeType::class, array('label' => 'label.ora_fine',
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-picker'],
        'required' => true))
      ->add('testo', TextareaType::class, array(
        'label' => 'label.testo',
        'attr' => array('rows' => '4'),
        'required' => true))
      ->add('filtro', ChoiceType::class, array('label' => false,
        'data' => $scelta_filtro,
        'choices' => ['label.filtro_tutti' => 'T', 'label.filtro_sede' => 'S', 'label.filtro_classe' => 'C'],
        'expanded' => false,
        'multiple' => false,
        'mapped' => false,
        'required' => true))
      ->add('filtroSedi', EntityType::class, array('label' => false,
        'data' => $scelta_filtro_sedi,
        'class' => 'AppBundle:Sede',
        'choice_label' => function ($obj) {
            return $obj->getCitta();
          },
        'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('s')
              ->orderBy('s.principale', 'DESC')
              ->addOrderBy('s.citta', 'ASC');
          },
        'expanded' => true,
        'multiple' => true,
        'choice_translation_domain' => false,
        'label_attr' => ['class' => 'gs-pt-0 checkbox-split-vertical'],
        'mapped' => false,
        'required' => false))
      ->add('filtroClassi', EntityType::class, array('label' => false,
        'data' => $scelta_filtro_classi,
        'class' => 'AppBundle:Classe',
        'choice_label' => function ($obj) {
            return $obj->getAnno().'ª '.$obj->getSezione();
          },
        'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('c')
              ->orderBy('c.anno,c.sezione', 'ASC');
          },
        'group_by' => function ($obj) {
            return $obj->getSede()->getCitta();
          },
        'expanded' => true,
        'multiple' => true,
        'choice_translation_domain' => false,
        'label_attr' => ['class' => 'gs-pt-0 checkbox-grouped-4'],
        'mapped' => false,
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start']))
      ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end',
        'onclick' => "location.href='".$this->generateUrl('staff_avvisi_attivita')."'"]))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // recupera dati
      $val_staff_filtro = 'N';
      $val_staff_sedi = [];
      $val_destinatari = ['D', 'G', 'A'];
      $val_filtro = $form->get('filtro')->getData();
      $val_filtro_id = [];
      $val_filtro_individuale_classe = 0;
      switch ($val_filtro) {
        case 'S':
          foreach ($form->get('filtroSedi')->getData() as $f) {
            $val_filtro_id[] = $f->getId();
          }
          break;
        case 'C':
          foreach ($form->get('filtroClassi')->getData() as $f) {
            $val_filtro_id[] = $f->getId();
          }
          break;
      }
      // controllo errori
      if ($val_filtro != 'T' && count($val_filtro_id) == 0) {
        // errore: filtro vuoto
        $form->addError(new FormError($this->get('translator')->trans('exception.destinatari_filtro_mancanti')));
      }
      // controllo data
      $errore = $reg->controlloData($form->get('data')->getData(), null);
      if ($errore) {
        // errore: festivo
        $form->addError(new FormError($this->get('translator')->trans('exception.data_festiva')));
      }
      // controllo testo
      if (strpos($form->get('testo')->getData(), '%DATA%') === false) {
        // errore: testo senza campo data
        $form->addError(new FormError($this->get('translator')->trans('exception.campo_data_mancante')));
      }
      // controllo permessi
      if (!$bac->azioneAvviso(($id > 0 ? 'edit' : 'add'), $avviso->getData(), $this->getUser(), ($id > 0 ? $avviso : null))) {
        // errore: avviso non permesso
        $form->addError(new FormError($this->get('translator')->trans('exception.avviso_non_permesso')));
      }
      if (!$reg->azioneAnnotazione('add', $avviso->getData(), $this->getUser(), null, null)) {
        // errore: nuova annotazione non permessa
        $form->addError(new FormError($this->get('translator')->trans('exception.annotazione_non_permessa')));
      }
      if (count($avviso->getAnnotazioni()) > 0) {
        $a = $avviso->getAnnotazioni()[0];
        if (!$reg->azioneAnnotazione('delete', $a->getData(), $this->getUser(), $a->getClasse(), $a)) {
          // errore: cancellazione annotazione non permessa
          $form->addError(new FormError($this->get('translator')->trans('exception.annotazione_non_permessa')));
        }
      }
      // modifica dati
      if ($form->isValid()) {
        // destinatari
        $log_destinatari = $bac->modificaFiltriAvviso($avviso, $dest_filtro, $val_staff_filtro, $val_staff_sedi,
          $val_destinatari, $val_filtro, $val_filtro_id);
        // annotazione
        $log_annotazioni['delete'] = array();
        if ($id) {
          // cancella annotazioni
          foreach ($avviso->getAnnotazioni() as $a) {
            $log_annotazioni['delete'][] = $a->getId();
            $em->remove($a);
          }
          $avviso->setAnnotazioni(new ArrayCollection());
        }
        // crea nuove annotazioni
        $bac->creaAnnotazione($avviso, $val_filtro, $val_filtro_id, $val_filtro_individuale_classe);
        // ok: memorizza dati
        $em->flush();
        // log azione
        if (!$id) {
          // nuovo
          $dblogger->write($this->getUser(), $request->getClientIp(), 'AVVISI', 'Crea avviso attività', __METHOD__, array(
            'Avviso' => $avviso->getId(),
            'Classi aggiunte' => implode(', ', $log_destinatari['classi']['add']),
            'Annotazioni' => implode(', ', array_map(function ($a) {
                return $a->getId();
              }, $avviso->getAnnotazioni()->toArray())),
            ));
        } else {
          // modifica
          $dblogger->write($this->getUser(), $request->getClientIp(), 'AVVISI', 'Modifica avviso attività', __METHOD__, array(
            'Id' => $avviso->getId(),
            'Data' => $avviso_old->getData()->format('d/m/Y'),
            'Ora inizio' => $avviso_old->getOra()->format('H:i'),
            'Ora fine' => $avviso_old->getOraFine()->format('H:i'),
            'Testo' => $avviso_old->getTesto(),
            'Classi cancellate' => implode(', ', $log_destinatari['classi']['delete']),
            'Classi aggiunte' => implode(', ', $log_destinatari['classi']['add']),
            'Docente' => $avviso_old->getDocente()->getId(),
            'Annotazioni cancellate' => implode(', ', $log_annotazioni['delete']),
            'Annotazioni create' => implode(', ', array_map(function ($a) {
                return $a->getId();
              }, $avviso->getAnnotazioni()->toArray())),
            ));
        }
        // redirezione
        return $this->redirectToRoute('staff_avvisi_attivita');
      }
    }
    // mostra la pagina di risposta
    return $this->render('ruolo_staff/avviso_attivita_edit.html.twig', array(
      'pagina_titolo' => 'page.staff_avvisi_attivita',
      'form' => $form->createView(),
      'form_title' => ($id > 0 ? 'title.modifica_avviso_attivita' : 'title.nuovo_avviso_attivita'),
    ));
  }

  /**
   * Gestione degli avvisi individuali per i genitori
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param BachecaUtil $bac Funzioni di utilità per la gestione della bacheca
   * @param int $pagina Numero di pagina per l'elenco da visualizzare
   *
   * @return Response Pagina di risposta
   *
   * @Route("/staff/avvisi/individuali/{pagina}", name="staff_avvisi_individuali",
   *    requirements={"pagina": "\d+"},
   *    defaults={"pagina": "0"})
   * @Method({"GET","POST"})
   *
   * @Security("has_role('ROLE_STAFF')")
   */
  public function avvisiIndividualiAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                           BachecaUtil $bac, $pagina) {
    // inizializza variabili
    $dati = null;
    // recupera criteri dalla sessione
    $search = array();
    $search['docente'] = $session->get('/APP/ROUTE/staff_avvisi_individuali/docente', 0);
    $search['classe_individuale'] = $session->get('/APP/ROUTE/staff_avvisi_individuali/classe_individuale', 0);
    $docente = ($search['docente'] > 0 ? $em->getRepository('AppBundle:Docente')->find($search['docente']) : 0);
    $classe = ($search['classe_individuale'] > 0 ? $em->getRepository('AppBundle:Classe')->find($search['classe_individuale']) : 0);
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $session->get('/APP/ROUTE/staff_avvisi_individuali/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $session->set('/APP/ROUTE/staff_avvisi_individuali/pagina', $pagina);
    }
    // form di ricerca
    $limite = 25;
    $form = $this->container->get('form.factory')->createNamedBuilder('staff_avvisi_individuali', FormType::class)
      ->add('docente', EntityType::class, array('label' => 'label.autore',
        'data' => $docente,
        'class' => 'AppBundle:Staff',
        'choice_label' => function ($obj) {
            return $obj->getCognome().' '.$obj->getNome();
          },
        'placeholder' => 'label.tutti_staff',
        'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('d')
              ->where('d.abilitato=:abilitato')
              ->orderBy('d.cognome,d.nome', 'ASC')
              ->setParameters(['abilitato' => 1]);
          },
        'label_attr' => ['class' => 'sr-only'],
        'choice_attr' => function($val, $key, $index) {
            return ['class' => 'gs-no-placeholder'];
          },
        'attr' => ['class' => 'gs-placeholder'],
        'required' => false))
      ->add('classe_individuale', EntityType::class, array('label' => 'label.classe',
        'data' => $classe,
        'class' => 'AppBundle:Classe',
        'choice_label' => function ($obj) {
            return $obj->getAnno().'ª '.$obj->getSezione();
          },
        'placeholder' => 'label.qualsiasi_classe',
        'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('c')
              ->orderBy('c.anno,c.sezione', 'ASC');
          },
        'group_by' => function ($obj) {
            return $obj->getSede()->getCitta();
          },
        'label_attr' => ['class' => 'sr-only'],
        'choice_attr' => function($val, $key, $index) {
            return ['class' => 'gs-no-placeholder'];
          },
        'attr' => ['class' => 'gs-placeholder'],
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.search'))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $search['docente'] = (is_object($form->get('docente')->getData()) ? $form->get('docente')->getData()->getId() : 0);
      $search['classe_individuale'] = (is_object($form->get('classe_individuale')->getData()) ?
        $form->get('classe_individuale')->getData()->getId() : 0);
      $pagina = 1;
      $session->set('/APP/ROUTE/staff_avvisi_individuali/docente', $search['docente']);
      $session->set('/APP/ROUTE/staff_avvisi_individuali/classe_individuale', $search['classe_individuale']);
      $session->set('/APP/ROUTE/staff_avvisi_individuali/pagina', $pagina);
    }
    // recupera dati
    $dati = $bac->listaAvvisi($search, $pagina, $limite, $this->getUser(), 'I');
    // mostra la pagina di risposta
    return $this->render('ruolo_staff/avvisi_individuali.html.twig', array(
      'pagina_titolo' => 'page.staff_avvisi_individuali',
      'form' => $form->createView(),
      'form_help' => null,
      'form_success' => null,
      'page' => $pagina,
      'maxPages' => ceil($dati['lista']->count() / $limite),
      'dati' => $dati,
    ));
  }

  /**
   * Aggiunge o modifica un avviso individuale
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param BachecaUtil $bac Funzioni di utilità per la gestione della bacheca
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param LogHandler $dblogger Gestore dei log su database
   *
   * @return Response Pagina di risposta
   *
   * @Route("/staff/avvisi/individuali/edit/{id}", name="staff_avviso_individuale_edit",
   *    requirements={"id": "\d+"},
   *    defaults={"id": "0"})
   * @Method({"GET","POST"})
   *
   * @Security("has_role('ROLE_STAFF')")
   */
  public function avvisoIndividualeEditAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                               BachecaUtil $bac, RegistroUtil $reg, LogHandler $dblogger, $id) {
    // controlla azione
    if ($id > 0) {
      // azione edit
      $avviso = $em->getRepository('AppBundle:Avviso')->findOneBy(['id' => $id, 'tipo' => 'I']);
      if (!$avviso) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $avviso_old = clone $avviso;
    } else {
      // azione add
      $docente = ($this->getUser()->getSesso() == 'M' ? ' prof. ' : 'la prof.ssa ').
        $this->getUser()->getNome().' '.$this->getUser()->getCognome();
      $avviso = (new Avviso())
        ->setTipo('I')
        ->setDestinatariStaff(false)
        ->setDestinatariCoordinatori(false)
        ->setDestinatariDocenti(false)
        ->setDestinatariGenitori(true)
        ->setDestinatariAlunni(false)
        ->setDestinatariIndividuali(true)
        ->setOggetto($this->get('translator')->trans('message.avviso_individuale_oggetto', ['%docente%' => $docente]))
        ->setData(new \DateTime('today'));
      $em->persist($avviso);
    }
    // legge destinatari di filtri
    $dest_filtro = $bac->filtriAvviso($avviso);
    // imposta autore dell'avviso
    $avviso->setDocente($this->getUser());
    // imposta lettura non avvenuta (per avviso modificato)
    foreach ($dest_filtro['utenti'] as $k=>$v) {
      $dest_filtro['genitori'][$k]['letto'] = null;
    }
    // opzione scelta filtro
    $scelta_filtro = 'I';
    $scelta_filtro_individuale = array();
    $scelta_filtro_individuale_classe = null;
    foreach (array_column($dest_filtro['utenti'], 'alunno') as $a) {
      $alunno = $em->getRepository('AppBundle:Alunno')->find($a);
      if ($alunno) {
        $scelta_filtro_individuale[] = $alunno->getId();
        $scelta_filtro_individuale_classe = $alunno->getClasse();
      }
    }
    // form di inserimento
    $form = $this->container->get('form.factory')->createNamedBuilder('avviso_individuale_edit', FormType::class, $avviso)
      ->add('testo', TextareaType::class, array(
        'label' => 'label.testo',
        'attr' => array('rows' => '4'),
        'required' => true))
      ->add('filtroIndividualeClasse', EntityType::class, array('label' => false,
        'data' => $scelta_filtro_individuale_classe,
        'class' => 'AppBundle:Classe',
        'choice_label' => function ($obj) {
            return $obj->getAnno().'ª '.$obj->getSezione().' - '.$obj->getCorso()->getNomeBreve();
          },
        'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('c')
              ->orderBy('c.anno,c.sezione', 'ASC');
          },
        'group_by' => function ($obj) {
            return $obj->getSede()->getCitta();
          },
        'expanded' => false,
        'multiple' => false,
        'placeholder' => 'label.scegli_classe',
        'choice_translation_domain' => false,
        'attr' => ['style' => 'width:auto'],
        'label_attr' => ['class' => 'gs-pt-0 checkbox-split-vertical'],
        'mapped' => false,
        'required' => false))
      ->add('filtroIndividuale',  HiddenType::class, array('label' => false,
        'data' => implode(',', $scelta_filtro_individuale),
        'mapped' => false,
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start']))
      ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end',
        'onclick' => "location.href='".$this->generateUrl('staff_avvisi_individuali')."'"]))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // recupera dati
      $val_staff_filtro = 'N';
      $val_staff_sedi = [];
      $val_destinatari = ['G', 'I'];
      $val_filtro = 'I';
      $val_filtro_id = [];
      $val_filtro_individuale_classe = $form->get('filtroIndividualeClasse')->getData();
      foreach (explode(',', $form->get('filtroIndividuale')->getData()) as $fid) {
        if ($val_filtro_individuale_classe &&
            $em->getRepository('AppBundle:Alunno')->findOneBy(
              ['id' => $fid, 'abilitato' => 1, 'classe' => $val_filtro_individuale_classe->getId()])) {
          $val_filtro_id[] = $fid;
        }
      }
      // controllo errori
      if (count($val_filtro_id) == 0) {
        // errore: filtro vuoto
        $form->addError(new FormError($this->get('translator')->trans('exception.destinatari_filtro_mancanti')));
      }
      // controllo permessi
      if (!$bac->azioneAvviso(($id > 0 ? 'edit' : 'add'), $avviso->getData(), $this->getUser(), ($id > 0 ? $avviso : null))) {
        // errore: avviso non permesso
        $form->addError(new FormError($this->get('translator')->trans('exception.avviso_non_permesso')));
      }
      // modifica dati
      if ($form->isValid()) {
        // destinatari
        $log_destinatari = $bac->modificaFiltriAvviso($avviso, $dest_filtro, $val_staff_filtro, $val_staff_sedi,
          $val_destinatari, $val_filtro, $val_filtro_id);
        // ok: memorizza dati
        $em->flush();
        // log azione
        if (!$id) {
          // nuovo
          $dblogger->write($this->getUser(), $request->getClientIp(), 'AVVISI', 'Crea avviso individuale', __METHOD__, array(
            'Avviso' => $avviso->getId(),
            'Utenti aggiunti' => implode(', ', array_map(function ($a) {
                return $a['genitore'].'->'.$a['alunno'];
              }, $log_destinatari['utenti']['add'])),
            ));
        } else {
          // modifica
          $dblogger->write($this->getUser(), $request->getClientIp(), 'AVVISI', 'Modifica avviso individuale', __METHOD__, array(
            'Id' => $avviso->getId(),
            'Testo' => $avviso_old->getTesto(),
            'Utenti cancellati' => implode(', ', array_map(function ($a) {
                return $a['genitore'].'->'.$a['alunno'];
              }, $log_destinatari['utenti']['delete'])),
            'Utenti aggiunti' => implode(', ', array_map(function ($a) {
                return $a['genitore'].'->'.$a['alunno'];
              }, $log_destinatari['utenti']['add'])),
            'Docente' => $avviso_old->getDocente()->getId(),
            ));
        }
        // redirezione
        return $this->redirectToRoute('staff_avvisi_individuali');
      }
    }
    // mostra la pagina di risposta
    return $this->render('ruolo_staff/avviso_individuale_edit.html.twig', array(
      'pagina_titolo' => 'page.staff_avvisi_individuali',
      'form' => $form->createView(),
      'form_title' => ($id > 0 ? 'title.modifica_avviso_individuale' : 'title.nuovo_avviso_individuale'),
    ));
  }

  /**
   * Mostra i programmi svolti inseriti dai docenti
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param StaffUtil $staff Funzioni di utilità per lo staff
   * @param int $pagina Numero di pagina per la lista visualizzata
   *
   * @return Response Pagina di risposta
   *
   * @Route("/staff/programmi/{pagina}", name="staff_programmi",
   *    requirements={"pagina": "\d+"},
   *    defaults={"pagina": 0})
   * @Method({"GET", "POST"})
   *
   * @Security("has_role('ROLE_STAFF')")
   */
  public function programmiAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                   StaffUtil $staff, $pagina) {
    // inizializza variabili
    $dati = null;
    // recupera criteri dalla sessione
    $search = array();
    $search['docente'] = $session->get('/APP/ROUTE/staff_programmi/docente', 0);
    $search['classe'] = $session->get('/APP/ROUTE/staff_programmi/classe', 0);
    $docente = ($search['docente'] > 0 ? $em->getRepository('AppBundle:Docente')->find($search['docente']) : 0);
    $classe = ($search['classe'] > 0 ? $em->getRepository('AppBundle:Classe')->find($search['classe']) : 0);
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $session->get('/APP/ROUTE/staff_programmi/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $session->set('/APP/ROUTE/staff_programmi/pagina', $pagina);
    }
    // form di ricerca
    $limite = 25;
    $form = $this->container->get('form.factory')->createNamedBuilder('staff_programmi', FormType::class)
      ->add('docente', EntityType::class, array('label' => 'label.autore',
        'data' => $docente,
        'class' => 'AppBundle:Docente',
        'choice_label' => function ($obj) {
            return $obj->getCognome().' '.$obj->getNome();
          },
        'placeholder' => 'label.tutti_docenti',
        'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('d')
              ->where('d NOT INSTANCE OF AppBundle:Preside AND d.abilitato=:abilitato')
              ->orderBy('d.cognome,d.nome', 'ASC')
              ->setParameters(['abilitato' => 1]);
          },
        'label_attr' => ['class' => 'sr-only'],
        'choice_attr' => function($val, $key, $index) {
            return ['class' => 'gs-no-placeholder'];
          },
        'attr' => ['class' => 'gs-placeholder'],
        'required' => false))
      ->add('classe', EntityType::class, array('label' => 'label.classe',
        'data' => $classe,
        'class' => 'AppBundle:Classe',
        'choice_label' => function ($obj) {
            return $obj->getAnno().'ª '.$obj->getSezione();
          },
        'placeholder' => 'label.qualsiasi_classe',
        'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('c')
              ->where('c.anno!=5')
              ->orderBy('c.anno,c.sezione', 'ASC');
          },
        'group_by' => function ($obj) {
            return $obj->getSede()->getCitta();
          },
        'label_attr' => ['class' => 'sr-only'],
        'choice_attr' => function($val, $key, $index) {
            return ['class' => 'gs-no-placeholder'];
          },
        'attr' => ['class' => 'gs-placeholder'],
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.search'))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $search['docente'] = (is_object($form->get('docente')->getData()) ? $form->get('docente')->getData()->getId() : 0);
      $search['classe'] = (is_object($form->get('classe')->getData()) ? $form->get('classe')->getData()->getId() : 0);
      $pagina = 1;
      $session->set('/APP/ROUTE/staff_programmi/docente', $search['docente']);
      $session->set('/APP/ROUTE/staff_programmi/classe', $search['classe']);
      $session->set('/APP/ROUTE/staff_programmi/pagina', $pagina);
    }
    // recupera dati
    $dati = $staff->programmi($search, $pagina, $limite);
    // mostra la pagina di risposta
    return $this->render('ruolo_staff/programmi.html.twig', array(
      'pagina_titolo' => 'page.staff_programmi',
      'form' => $form->createView(),
      'form_help' => null,
      'form_success' => null,
      'page' => $pagina,
      'maxPages' => ceil($dati['lista']->count() / $limite),
      'dati' => $dati,
    ));
  }

  /**
   * Mostra le relazioni finali inserite dai docenti
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param StaffUtil $staff Funzioni di utilità per lo staff
   * @param int $pagina Numero di pagina per la lista visualizzata
   *
   * @return Response Pagina di risposta
   *
   * @Route("/staff/relazioni/{pagina}", name="staff_relazioni",
   *    requirements={"pagina": "\d+"},
   *    defaults={"pagina": 0})
   * @Method({"GET", "POST"})
   *
   * @Security("has_role('ROLE_STAFF')")
   */
  public function relazioniAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                   StaffUtil $staff, $pagina) {
    // inizializza variabili
    $dati = null;
    // recupera criteri dalla sessione
    $search = array();
    $search['docente'] = $session->get('/APP/ROUTE/staff_relazioni/docente', 0);
    $search['classe'] = $session->get('/APP/ROUTE/staff_relazioni/classe', 0);
    $docente = ($search['docente'] > 0 ? $em->getRepository('AppBundle:Docente')->find($search['docente']) : 0);
    $classe = ($search['classe'] > 0 ? $em->getRepository('AppBundle:Classe')->find($search['classe']) : 0);
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $session->get('/APP/ROUTE/staff_relazioni/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $session->set('/APP/ROUTE/staff_relazioni/pagina', $pagina);
    }
    // form di ricerca
    $limite = 25;
    $form = $this->container->get('form.factory')->createNamedBuilder('staff_relazioni', FormType::class)
      ->add('docente', EntityType::class, array('label' => 'label.autore',
        'data' => $docente,
        'class' => 'AppBundle:Docente',
        'choice_label' => function ($obj) {
            return $obj->getCognome().' '.$obj->getNome();
          },
        'placeholder' => 'label.tutti_docenti',
        'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('d')
              ->where('d NOT INSTANCE OF AppBundle:Preside AND d.abilitato=:abilitato')
              ->orderBy('d.cognome,d.nome', 'ASC')
              ->setParameters(['abilitato' => 1]);
          },
        'label_attr' => ['class' => 'sr-only'],
        'choice_attr' => function($val, $key, $index) {
            return ['class' => 'gs-no-placeholder'];
          },
        'attr' => ['class' => 'gs-placeholder'],
        'required' => false))
      ->add('classe', EntityType::class, array('label' => 'label.classe',
        'data' => $classe,
        'class' => 'AppBundle:Classe',
        'choice_label' => function ($obj) {
            return $obj->getAnno().'ª '.$obj->getSezione();
          },
        'placeholder' => 'label.qualsiasi_classe',
        'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('c')
              ->where('c.anno!=5')
              ->orderBy('c.anno,c.sezione', 'ASC');
          },
        'group_by' => function ($obj) {
            return $obj->getSede()->getCitta();
          },
        'label_attr' => ['class' => 'sr-only'],
        'choice_attr' => function($val, $key, $index) {
            return ['class' => 'gs-no-placeholder'];
          },
        'attr' => ['class' => 'gs-placeholder'],
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.search'))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $search['docente'] = (is_object($form->get('docente')->getData()) ? $form->get('docente')->getData()->getId() : 0);
      $search['classe'] = (is_object($form->get('classe')->getData()) ? $form->get('classe')->getData()->getId() : 0);
      $pagina = 1;
      $session->set('/APP/ROUTE/staff_relazioni/docente', $search['docente']);
      $session->set('/APP/ROUTE/staff_relazioni/classe', $search['classe']);
      $session->set('/APP/ROUTE/staff_relazioni/pagina', $pagina);
    }
    // recupera dati
    $dati = $staff->relazioni($search, $pagina, $limite);
    // mostra la pagina di risposta
    return $this->render('ruolo_staff/relazioni.html.twig', array(
      'pagina_titolo' => 'page.staff_relazioni',
      'form' => $form->createView(),
      'form_help' => null,
      'form_success' => null,
      'page' => $pagina,
      'maxPages' => ceil($dati['lista']->count() / $limite),
      'dati' => $dati,
    ));
  }

}

