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
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use AppBundle\Entity\Annotazione;
use AppBundle\Util\RegistroUtil;
use AppBundle\Util\StaffUtil;
use AppBundle\Util\LogHandler;
use AppBundle\Util\PdfManager;


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
    $limite = 30;
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
        $pdf->configure('Istituto di Istruzione Superiore "NOME"',
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
      $pdf->configure('Istituto di Istruzione Superiore "NOME"',
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
    $limite = 30;
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
    $limite = 30;
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
   * Gestione delle annotazioni sul registro
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param string $data Data per la gestione dei ritardi e delle uscita (AAAA-MM-GG)
   * @param int $pagina Numero di pagina per la lista dei alunni
   *
   * @return Response Pagina di risposta
   *
   * @Route("/staff/annotazioni/{data}/{pagina}", name="staff_annotazioni",
   *    requirements={"data": "\d\d\d\d-\d\d-\d\d", "pagina": "\d+"},
   *    defaults={"data": "0000-00-00", "pagina": "0"})
   * @Method({"GET","POST"})
   *
   * @Security("has_role('ROLE_STAFF')")
   */
  public function annotazioniAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                     RegistroUtil $reg, $data, $pagina) {
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
      if ($session->get('/APP/ROUTE/staff_annotazioni/data')) {
        // recupera data da sessione
        $data_obj = \DateTime::createFromFormat('Y-m-d', $session->get('/APP/ROUTE/staff_annotazioni/data'));
      } else {
        // imposta data odierna
        $data_obj = new \DateTime();
      }
    } else {
      // imposta data indicata e la memorizza in sessione
      $data_obj = \DateTime::createFromFormat('Y-m-d', $data);
      $session->set('/APP/ROUTE/staff_annotazioni/data', $data);
    }
    // data in formato stringa
    $formatter = new \IntlDateFormatter('it_IT', \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $info['data_label'] =  $formatter->format($data_obj);
    // recupera criteri dalla sessione
    $search = array();
    $search['classe'] = $session->get('/APP/ROUTE/staff_annotazioni/classe', 0);
    $classe = ($search['classe'] > 0 ? $em->getRepository('AppBundle:Classe')->find($search['classe']) : 0);
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $session->get('/APP/ROUTE/staff_annotazioni/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $session->set('/APP/ROUTE/staff_annotazioni/pagina', $pagina);
    }
    // legge sede
    $sede = $this->getUser()->getSede();
    // form di ricerca
    $limite = 30;
    if ($sede) {
      // limita a classi di sede
      $classi = $em->getRepository('AppBundle:Classe')->findBy(['sede' => $sede], ['anno' =>'ASC', 'sezione' =>'ASC']);
    } else {
      // tutte le classi
      $classi = $em->getRepository('AppBundle:Classe')->findBy([], ['anno' =>'ASC', 'sezione' =>'ASC']);
    }
    $form = $this->container->get('form.factory')->createNamedBuilder('staff_annotazioni', FormType::class)
      ->setAction($this->generateUrl('staff_annotazioni', ['data' => $data]))
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
      $search['classe'] = (is_object($form->get('classe')->getData()) ? $form->get('classe')->getData()->getId() : 0);
      $pagina = 1;
      $session->set('/APP/ROUTE/staff_annotazioni/classe', $search['classe']);
      $session->set('/APP/ROUTE/staff_annotazioni/pagina', $pagina);
    }
    // recupera festivi per calendario
    $lista_festivi = $reg->listaFestivi(null);
    // controllo data
    $errore = $reg->controlloData($data_obj, null);
    if (!$errore) {
      // non festivo: recupera dati
      $search['data'] = $data_obj->format('Y-m-d');
      $lista = $em->getRepository('AppBundle:Annotazione')->listaStaff($sede, $search, $pagina, $limite);
      $max_pagine = ceil($lista->count() / $limite);
      $dati['lista'] = $lista;
      foreach ($lista as $k=>$a) {
        // controlla azioni
        if ($reg->azioneAnnotazione('edit', $a->getData(), $this->getUser(), $a->getClasse(), $a)) {
          // pulsante edit
          $dati['azioni'][$k]['edit'] = 1;
        }
        if ($reg->azioneAnnotazione('delete', $a->getData(), $this->getUser(), $a->getClasse(), $a)) {
          // pulsante delete
          $dati['azioni'][$k]['delete'] = 1;
        }
      }
      if ($reg->azioneAnnotazione('add', $data_obj, $this->getUser(), null, null)) {
        // pulsante add
        $dati['azioni']['add'] = 1;
      }
    }
    // mostra la pagina di risposta
    return $this->render('ruolo_staff/annotazioni.html.twig', array(
      'pagina_titolo' => 'page.staff_annotazioni',
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
   * Aggiunge una annotazione al registro a più classi
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param LogHandler $dblogger Gestore dei log su database
   * @param string $data Data del giorno
   *
   * @return Response Pagina di risposta
   *
   * @Route("/staff/annotazioni/add/{data}", name="staff_annotazione_add",
   *    requirements={"data": "\d\d\d\d-\d\d-\d\d"})
   * @Method({"GET","POST"})
   *
   * @Security("has_role('ROLE_STAFF')")
   */
  public function annotazioneAddAction(Request $request, EntityManagerInterface $em, RegistroUtil $reg,
                                        LogHandler $dblogger, $data) {
    // inizializza
    $label = array();
    // controlla data
    $data_obj = \DateTime::createFromFormat('Y-m-d', $data);
    $errore = $reg->controlloData($data_obj, null);
    if ($errore) {
      // errore: festivo
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // controlla permessi
    if (!$reg->azioneAnnotazione('add', $data_obj, $this->getUser(), null, null)) {
      // errore: azione non permessa
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // dati in formato stringa
    $formatter = new \IntlDateFormatter('it_IT', \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $label['data'] =  $formatter->format($data_obj);
    $label['docente'] = $this->getUser()->getNome().' '.$this->getUser()->getCognome();
    // legge sede
    $sede = $this->getUser()->getSede();
    // form di inserimento
    $form = $this->container->get('form.factory')->createNamedBuilder('annotazione_add', FormType::class)
      ->add('classi', EntityType::class, array('label' => 'label.classi',
        'class' => 'AppBundle:Classe',
        'choice_label' => function ($obj) {
            return $obj->getAnno().'ª '.$obj->getSezione().' - '.$obj->getCorso()->getNomeBreve().' - '.
              $obj->getSede()->getCitta();
          },
        'query_builder' => function (EntityRepository $er) use ($sede) {
            $qb = $er->createQueryBuilder('c')
              ->orderBy('c.anno,c.sezione', 'ASC');
            if ($sede) {
              $qb
                ->where('c.sede=:sede')
                ->setParameters(['sede' => $sede]);
            }
            return $qb;
          },
        'expanded' => true,
        'multiple' => true,
        'label_attr' => ['class' => 'gs-pt-1 checkbox-split-vertical'],
        'required' => true))
      ->add('testo', TextareaType::class, array(
        'label' => 'label.testo',
        'trim' => true,
        'required' => true))
      ->add('visibile', ChoiceType::class, array('label' => 'label.visibile_genitori',
        'choices' => ['label.si' => true, 'label.no' => false],
        'expanded' => true,
        'multiple' => false,
        'label_attr' => ['class' => 'radio-inline'],
        'required' => true))
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start']))
      ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end',
        'onclick' => "location.href='".$this->generateUrl('staff_annotazioni')."'"]))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // valida classi
      if (count($form->get('classi')->getData()) == 0) {
        $form->get('classi')->addError(new FormError($this->get('translator')->trans('field.notblank', [], 'validators')));
      } else {
        // verifica data di sede
        foreach ($form->get('classi')->getData() as $c) {
          $errore = $reg->controlloData($data_obj, $c->getSede());
          if ($errore) {
            // errore: festivo
            $form->get('classi')->addError(new FormError($this->get('translator')->trans('exception.festivo_per_classi')));
            break;
          }
        }
      }
      if ($form->isValid()) {
        foreach ($form->get('classi')->getData() as $c) {
          // aggiunge annotazione per classe
          $annotazione = (new Annotazione())
            ->setData($data_obj)
            ->setClasse($c)
            ->setDocente($this->getUser())
            ->setTesto($form->get('testo')->getData())
            ->setVisibile($form->get('visibile')->getData());
          $em->persist($annotazione);
          // ok: memorizza dati
          $em->flush();
          // log azione
          $dblogger->write($this->getUser(), $request->getClientIp(), 'REGISTRO', 'Crea annotazione', __METHOD__, array(
            'Annotazione' => $annotazione->getId()
            ));
        }
        // redirezione
        return $this->redirectToRoute('staff_annotazioni');
      }
    }
    // mostra la pagina di risposta
    return $this->render('ruolo_staff/annotazione_add.html.twig', array(
      'pagina_titolo' => 'page.staff_annotazioni',
      'form' => $form->createView(),
      'form_title' => 'title.nuova_annotazione',
      'label' => $label,
    ));
  }

}

