<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Controller;

use App\Entity\Alunno;
use App\Entity\Docente;
use App\Util\LogHandler;
use App\Util\OtpUtil;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;


/**
 * UtentiController - gestione utenti generici
 *
 * @author Antonello Dessì
 */
class UtentiController extends BaseController {

  /**
   * Mostra il profilo dell'utente connesso
   *
   * @return Response Pagina di risposta
   *
   * @Route("/utenti/profilo/", name="utenti_profilo",
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_UTENTE")
   */
  public function profiloAction() {
    // mostra la pagina di risposta
    return $this->render('utenti/profilo.html.twig', array(
      'pagina_titolo' => 'page.utenti_profilo',
    ));
  }

  /**
   * Modifica l'email del profilo dell'utente connesso
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param ValidatorInterface $validator Gestore della validazione dei dati
   * @param LogHandler $dblogger Gestore dei log su database
   *
   * @return Response Pagina di risposta
   *
   * @Route("/utenti/email/", name="utenti_email",
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_UTENTE")
   */
  public function emailAction(Request $request, TranslatorInterface $trans, ValidatorInterface $validator,
                              LogHandler $dblogger) {
    $success = null;
    // form
    $form = $this->container->get('form.factory')->createNamedBuilder('utenti_email', FormType::class)
      ->add('email', TextType::class, array('label' => 'label.email',
        'data' => substr($this->getUser()->getEmail(), -6) == '.local' ? '' : $this->getUser()->getEmail(),
        'required' => true))
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start']))
      ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end',
        'onclick' => "location.href='".$this->generateUrl('utenti_profilo')."'"]))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      $vecchia_email = $this->getUser()->getEmail();
      // legge configurazione: id_provider
      $id_provider = $this->reqstack->getSession()->get('/CONFIG/ACCESSO/id_provider');
      // validazione
      $this->getUser()->setEmail($form->get('email')->getData());
      $errors = $validator->validate($this->getUser());
      if (count($errors) > 0) {
        $form->addError(new FormError($errors[0]->getMessage()));
      } elseif ($id_provider && ($this->getUser() instanceOf Docente || $this->getUser() instanceOf Alunno)) {
        // errore: docente/staff/preside/alunno
        $form->addError(new FormError($trans->trans('exception.invalid_user_type_recovery')));
      } else {
        // memorizza modifica
        $this->em->flush();
        $success = 'message.update_ok';
        // log azione
        $dblogger->logAzione('SICUREZZA', 'Cambio Email', array(
          'Precedente email' => $vecchia_email
          ));
      }
    }
    // mostra la pagina di risposta
    return $this->render('utenti/email.html.twig', array(
      'pagina_titolo' => 'page.utenti_email',
      'form' => $form->createView(),
      'form_title' => 'title.modifica_email',
      'form_help' => 'message.modifica_email',
      'form_success' => $success,
    ));
  }

  /**
   * Modifica la password dell'utente connesso
   *
   * @param Request $request Pagina richiesta
   * @param UserPasswordHasherInterface $hasher Gestore della codifica delle password
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param ValidatorInterface $validator Gestore della validazione dei dati
   * @param OtpUtil $otp Gestione del codice OTP
   * @param LogHandler $dblogger Gestore dei log su database
   *
   * @return Response Pagina di risposta
   *
   * @Route("/utenti/password/", name="utenti_password",
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_UTENTE")
   */
  public function passwordAction(Request $request, UserPasswordHasherInterface $hasher,
                                 TranslatorInterface $trans, ValidatorInterface $validator,
                                 OtpUtil $otp, LogHandler $dblogger) {
    $success = null;
    $errore = null;
    $form = null;
    // controllo accesso
    if (($this->getUser() instanceOf Docente) && !$this->getUser()->getOtp()) {
      // docente senza OTP
      $errore = 'exception.docente_cambio_password';
    } elseif (substr($this->getUser()->getEmail(), -6) == '.local') {
      // altro utente senza email
      $errore = 'exception.utente_cambio_password';
    } else {
      // form
      $form = $this->container->get('form.factory')->createNamedBuilder('utenti_password', FormType::class)
        ->add('current_password', PasswordType::class, array('label' => 'label.current_password',
          'required' => true))
        ->add('password', RepeatedType::class, array(
          'type' => PasswordType::class,
          'invalid_message' => 'password.nomatch',
          'first_options' => array('label' => 'label.new_password'),
          'second_options' => array('label' => 'label.new_password2'),
          'required' => true));
      if ($this->getUser() instanceOf Docente) {
        $form = $form
          ->add('otp', TextType::class, array('label' => 'label.otp',
            'attr' => ['class' => 'gs-ml-2'],
            'trim' => true,
            'required' => true));
      }
      $form = $form
        ->add('submit', SubmitType::class, array('label' => 'label.submit',
          'attr' => ['widget' => 'gs-button-start']))
        ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
          'attr' => ['widget' => 'gs-button-end',
          'onclick' => "location.href='".$this->generateUrl('utenti_profilo')."'"]))
        ->getForm();
      $form->handleRequest($request);
      if ($form->isSubmitted() && $form->isValid()) {
        // legge configurazione: id_provider
        $id_provider = $this->reqstack->getSession()->get('/CONFIG/ACCESSO/id_provider');
        if ($id_provider && ($this->getUser() instanceOf Docente || $this->getUser() instanceOf Alunno)) {
          // errore: docente/staff/preside/alunno
          $form->addError(new FormError($trans->trans('exception.invalid_user_type_recovery')));
        }
        // controllo password esistente
        if (!$hasher->isPasswordValid($this->getUser(), $form->get('current_password')->getData())) {
          // vecchia password errata
          $form->get('current_password')->addError(
            new FormError($trans->trans('password.wrong', [], 'validators')));
        }
        // validazione nuova password
        $psw = $form->get('password')->getData();
        $minuscole = preg_match('/[a-z]+/', $psw);
        $maiuscole = preg_match('/[A-Z]+/', $psw);
        $cifre = preg_match('/\d+/', $psw);
        $this->getUser()->setPasswordNonCifrata($psw);
        $errors = $validator->validate($this->getUser());
        if (count($errors) > 0) {
          // nuova password non valida
          $form->get('password')['first']->addError(new FormError($errors[0]->getMessage()));
        } elseif (!$minuscole || !$maiuscole || !$cifre) {
          // errore di formato
          $form->get('password')['first']->addError(
            new FormError($trans->trans('exception.formato_password')));
        }
        // validazione OTP
        if ($this->getUser() instanceOf Docente) {
          $codice = $form->get('otp')->getData();
          if (!$otp->controllaOtp($this->getUser()->getOtp(), $codice)) {
            // errore codice OTP
            $form->get('otp')->addError(new FormError($trans->trans('exception.otp_errato')));
          } elseif ($this->getUser()->getUltimoOtp() == $codice) {
            // otp riusato (replay attack)
            $form->get('otp')->addError(new FormError($trans->trans('exception.otp_errato')));
          }
        }
        if ($form->isValid()) {
          // codifica password
          $password = $hasher->hashPassword($this->getUser(), $psw);
          $this->getUser()->setPassword($password);
          if ($this->getUser() instanceOf Docente) {
            // memorizza ultimo OTP
            $this->getUser()->setUltimoOtp($codice);
          }
          // memorizza password
          $this->em->flush();
          $success = 'message.update_ok';
          // log azione
          $dblogger->logAzione('SICUREZZA', 'Cambio Password', array(
            ));
        }
      }
    }
    // mostra la pagina di risposta
    return $this->render('utenti/password.html.twig', array(
      'pagina_titolo' => 'page.utenti_password',
      'form' => ($form ? $form->createView() : null),
      'form_title' => 'title.modifica_password',
      'form_help' => 'message.modifica_password',
      'form_success' => $success,
      'errore' => $errore,
    ));
  }

  /**
   * Abilita i docenti all'uso dell'OTP.
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param OtpUtil $otp Gestione del codice OTP
   * @param LogHandler $dblogger Gestore dei log su database
   *
   * @return Response Pagina di risposta
   *
   * @Route("/utenti/otp/", name="utenti_otp",
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function otpAction(Request $request, TranslatorInterface $trans, OtpUtil $otp,
                            LogHandler $dblogger) {
    // inizializza
    $docente = $this->getUser();
    $msg = null;
    $qrcode = null;
    $form = null;
    // legge configurazione: id_provider
    $id_provider = $this->reqstack->getSession()->get('/CONFIG/ACCESSO/id_provider');
    if ($id_provider) {
      // errore: docente/staff/preside/alunno
      $msg = array('tipo' => 'danger', 'messaggio' => 'exception.invalid_user_type_recovery');
    } elseif ($docente->getOtp()) {
      // risulta già associato
      $msg = array('tipo' => 'warning', 'messaggio' => 'exception.otp_associato');
    } else {
      // prima associazione con un dispositivo
      if ($request->getMethod() == 'POST') {
        // legge token esistente
        $token = $this->reqstack->getSession()->get('/APP/ROUTE/utenti_otp/token');
      } else {
        // crea token
        $token = $otp->creaToken($docente->getUsername());
        $this->reqstack->getSession()->set('/APP/ROUTE/utenti_otp/token', $token);
      }
      // crea qrcode
      $qrcode = $otp->qrcode($docente->getUsername(), 'Registro Elettronico', $token);
      // form inserimeno OTP
      $form = $this->container->get('form.factory')->createNamedBuilder('utenti_otp', FormType::class)
        ->add('otp', TextType::class, array('label' => 'label.otp',
          'attr' => ['class' => 'gs-ml-2'],
          'trim' => true,
          'required' => true))
        ->add('submit', SubmitType::class, array('label' => 'label.submit',
          'attr' => ['class' => 'btn btn-primary']))
        ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
          'attr' => ['onclick' => "location.href='".$this->generateUrl('utenti_profilo')."'"]))
        ->getForm();
      $form->handleRequest($request);
      if ($form->isSubmitted() && $form->isValid()) {
        // controllo codice OTP
        if ($otp->controllaOtp($token, $form->get('otp')->getData())) {
          // ok, abilita otp
          $docente->setOtp($token);
          $this->em->flush();
          // cancella sessione
          $this->reqstack->getSession()->set('/APP/ROUTE/utenti_otp/token', '');
          // messaggio di successo
          $msg = array('tipo' => 'success', 'messaggio' => 'message.otp_abilitato');
          // log azione
          $dblogger->logAzione('SICUREZZA', 'Attivazione OTP', array(
            ));
        } else {
          // errore
          $form->addError(new FormError($trans->trans('exception.otp_errato')));
        }
      }
    }
    // mostra la pagina di risposta
    return $this->render('utenti/otp.html.twig', array(
      'pagina_titolo' => 'page.utenti_otp',
      'form' => ($form ? $form->createView() : null),
      'form_help' => null,
      'form_success' => null,
      'msg' => $msg,
      'qrcode' => $qrcode,
      ));
  }

}
