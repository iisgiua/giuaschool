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
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use AppBundle\Util\LogHandler;
use AppBundle\Util\RegistroUtil;


/**
 * ProcedureController - procedure di utilità
 */
class ProcedureController extends Controller {

  /**
   * Procedure di utilità
   *
   * @return Response Pagina di risposta
   *
   * @Route("/procedure/", name="procedure")
   * @Method("GET")
   *
   * @Security("has_role('ROLE_AMMINISTRATORE')")
   */
  public function procedureAction() {
    return $this->render('procedure/index.html.twig', array(
      'pagina_titolo' => 'page.procedure',
    ));
  }

  /**
   * Cambia la password di un utente
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param UserPasswordEncoderInterface $encoder Gestore della codifica delle password
   * @param LogHandler $dblogger Gestore dei log su database
   *
   * @return Response Pagina di risposta
   *
   * @Route("/procedure/password/", name="procedure_password")
   * @Method({"GET", "POST"})
   *
   * @Security("has_role('ROLE_AMMINISTRATORE')")
   */
  public function passwordAction(Request $request, EntityManagerInterface $em, UserPasswordEncoderInterface $encoder,
                                  LogHandler $dblogger) {
    // form
    $success = null;
    $form = $this->container->get('form.factory')->createNamedBuilder('procedure_password', FormType::class)
      ->add('username', TextType::class, array('label' => 'label.username', 'required' => true))
      ->add('password', RepeatedType::class, array(
        'type' => PasswordType::class,
        'invalid_message' => 'password.nomatch',
        'first_options' => array('label' => 'label.password'),
        'second_options' => array('label' => 'label.password2'),
        'required' => true
        ))
      ->add('submit', SubmitType::class, array('label' => 'label.submit'))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // form inviato
      $username = $form->get('username')->getData();
      $user = $em->getRepository('AppBundle:Utente')->findOneByUsername($username);
      if (!$user || !$user->getAbilitato()) {
        // errore, utente non esiste o non abilitato
        $form->addError(new FormError($this->get('translator')->trans('exception.invalid_user')));
      } else {
        // validazione password
        $user->setPasswordNonCifrata($form->get('password')->getData());
        $errors = $this->get('validator')->validate($user);
        if (count($errors) > 0) {
          $form->addError(new FormError($errors[0]->getMessage()));
        } else {
          // codifica password
          $password = $encoder->encodePassword($user, $user->getPasswordNonCifrata());
          $user->setPassword($password);
          // memorizza password
          $em->flush();
          $success = 'message.update_ok';
          // log azione
          $dblogger->write($user, $request->getClientIp(), 'SICUREZZA', 'Cambio Password da Amministrazione', __METHOD__, array(
            'Username esecutore' => $this->getUser()->getUsername(),
            'Ruolo esecutore' => $this->getUser()->getRoles()[0],
            'ID esecutore' => $this->getUser()->getId()
            ));
        }
      }
    }
    // mostra la pagina di risposta
    return $this->render('procedure/password.html.twig', array(
      'pagina_titolo' => 'page.password',
      'form' => $form->createView(),
      'form_title' => 'title.password',
      'form_help' => 'message.required_fields',
      'form_success' => $success,
      ));
  }

  /**
   * Impersona un altro utente
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param LogHandler $dblogger Gestore dei log su database
   *
   * @return Response Pagina di risposta
   *
   * @Route("/procedure/alias/", name="procedure_alias")
   * @Method({"GET", "POST"})
   *
   * @Security("has_role('ROLE_AMMINISTRATORE')")
   */
  public function aliasAction(Request $request, EntityManagerInterface $em, SessionInterface $session, LogHandler $dblogger) {
    // form per l'input dell'alias
    $form = $this->container->get('form.factory')->createNamedBuilder('procedure_alias', FormType::class)
      ->add('username', TextType::class, array('label' => 'label.username', 'required' => true))
      ->add('submit', SubmitType::class, array('label' => 'label.submit'))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // form inviato
      $username = $form->get('username')->getData();
      $user = $em->getRepository('AppBundle:Utente')->findOneByUsername($username);
      if (!$user || !$user->getAbilitato()) {
        // errore, utente non esiste o non abilitato
        $form->addError(new FormError($this->get('translator')->trans('exception.invalid_user')));
      } else {
        // memorizza dati in sessione
        $session->set('/APP/UTENTE/tipo_accesso_reale', $session->get('/APP/UTENTE/tipo_accesso'));
        $session->set('/APP/UTENTE/ultimo_accesso_reale', $session->get('/APP/UTENTE/ultimo_accesso'));
        $session->set('/APP/UTENTE/username_reale', $this->getUser()->getUsername());
        $session->set('/APP/UTENTE/ruolo_reale', $this->getUser()->getRoles()[0]);
        $session->set('/APP/UTENTE/id_reale', $this->getUser()->getId());
        $session->set('/APP/UTENTE/ultimo_accesso',
          ($user->getUltimoAccesso() ? $user->getUltimoAccesso()->format('d/m/Y H:i:s') : null));
        $session->set('/APP/UTENTE/tipo_accesso', 'alias');
        // cancella altri dati di sessione
        $session->remove('/APP/ROUTE');
        $session->remove('/APP/DOCENTE');
        // log azione
        $dblogger->write($user, $request->getClientIp(), 'ACCESSO', 'Alias', __METHOD__, array(
          'Username' => $user->getUsername(),
          'Ruolo' => $user->getRoles()[0],
          'Username reale' => $this->getUser()->getUsername(),
          'Ruolo reale' => $this->getUser()->getRoles()[0],
          'ID reale' => $this->getUser()->getId()
          ));
        // impersona l'alias e fa il redirect alla home
        return $this->redirectToRoute('home', array('_alias' => $username));
      }
    }
    // mostra la pagina di risposta
    return $this->render('procedure/alias.html.twig', array(
      'pagina_titolo' => 'page.alias',
      'form' => $form->createView(),
      'form_title' => 'title.alias',
      'form_help' => 'message.required_fields',
      'form_success' => null,
      ));
  }

  /**
   * Disconnette l'alias in uso e ritorna all'utente iniziale
   *
   * @param Request $request Pagina richiesta
   * @param SessionInterface $session Gestore delle sessioni
   * @param LogHandler $dblogger Gestore dei log su database
   *
   * @return Response Pagina di risposta
   *
   * @Route("/procedure/alias/exit", name="procedure_alias_exit")
   * @Method("GET")
   */
  public function aliasExitAction(Request $request, SessionInterface $session, LogHandler $dblogger) {
    // log azione
    $dblogger->write($this->getUser(), $request->getClientIp(), 'ACCESSO', 'Alias Exit', __METHOD__, array(
      'Username' => $this->getUser()->getUsername(),
      'Ruolo' => $this->getUser()->getRoles()[0],
      'Username reale' => $session->get('/APP/UTENTE/username_reale'),
      'Ruolo reale' => $session->get('/APP/UTENTE/ruolo_reale'),
      'ID reale' => $session->get('/APP/UTENTE/id_reale')
      ));
    // ricarica dati in sessione
    $session->set('/APP/UTENTE/ultimo_accesso', $session->get('/APP/UTENTE/ultimo_accesso_reale'));
    $session->set('/APP/UTENTE/tipo_accesso', $session->get('/APP/UTENTE/tipo_accesso_reale'));
    $session->remove('/APP/UTENTE/tipo_accesso_reale');
    $session->remove('/APP/UTENTE/ultimo_accesso_reale');
    $session->remove('/APP/UTENTE/username_reale');
    $session->remove('/APP/UTENTE/ruolo_reale');
    $session->remove('/APP/UTENTE/id_reale');
    // disconnette l'alias in uso e redirect alla home
    return $this->redirectToRoute('home', array('_alias' => '_exit'));
  }

  /**
   * Ricalcola le ore di assenza per un dato periodo
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   *
   * @return Response Pagina di risposta
   *
   * @Route("/procedure/ricalcola", name="procedure_ricalcola")
   * @Method({"GET", "POST"})
   *
   * @Security("has_role('ROLE_AMMINISTRATORE')")
   */
  public function ricalcolaAction(Request $request, EntityManagerInterface $em, RegistroUtil $reg) {
    // form
    $success = null;
    $form = $this->container->get('form.factory')->createNamedBuilder('procedure_ricalcola', FormType::class)
      ->add('inizio', DateType::class, array('label' => 'label.data_inizio',
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-picker'],
        'format' => 'dd/MM/yyyy',
        'required' => true))
      ->add('fine', DateType::class, array('label' => 'label.data_fine',
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-picker'],
        'format' => 'dd/MM/yyyy',
        'required' => true))
    ->add('submit', SubmitType::class, array('label' => 'label.submit'))
    ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // cancella assenze in intervallo di date (tutte le classi e alunni)
      $em->getConnection()
        ->prepare('DELETE FROM gs_assenza_lezione WHERE lezione_id IN (SELECT id FROM gs_lezione WHERE data BETWEEN :data1 AND :data2)')
        ->execute(['data1' => $form->get('inizio')->getData()->format('Y-m-d'),
          'data2' => $form->get('fine')->getData()->format('Y-m-d')]);
      // legge lezioni
      $lezioni = $em->getRepository('AppBundle:Lezione')->createQueryBuilder('l')
        ->where('l.data BETWEEN :data1 AND :data2')
        ->setParameters(['data1' => $form->get('inizio')->getData()->format('Y-m-d'),
          'data2' => $form->get('fine')->getData()->format('Y-m-d')])
        ->getQuery()
        ->getResult();
      // ricalcola ore di ogni lezione
      foreach ($lezioni as $l) {
        $reg->ricalcolaOreLezione($l->getData(), $l);
      }
      // ok
      $success = 'message.update_ok';
    }
    return $this->render('procedure/ricalcola.html.twig', array(
      'pagina_titolo' => 'page.ricalcola',
      'form' => $form->createView(),
      'form_title' => 'title.ricalcola',
      'form_help' => 'message.required_fields',
      'form_success' => $success,
      ));
  }

}

