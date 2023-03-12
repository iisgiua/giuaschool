<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Controller;

use App\Entity\Alunno;
use App\Entity\Docente;
use App\Form\NotificaType;
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
  public function emailAction(Request $request, ValidatorInterface $validator, LogHandler $dblogger) {
    $success = null;
    // controlli
    $idProvider = $this->reqstack->getSession()->get('/CONFIG/ACCESSO/id_provider');
    $idProviderTipo = $this->reqstack->getSession()->get('/CONFIG/ACCESSO/id_provider_tipo');
    if ($idProvider && $this->getUser()->controllaRuolo($idProviderTipo)) {
      // errore: cambio email non permesso per utenti Google
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // form
    $form = $this->container->get('form.factory')->createNamedBuilder('utenti_email', FormType::class)
      ->add('email', TextType::class, array('label' => 'label.email',
        'data' => substr($this->getUser()->getEmail(), -6) == '.local' ? '' : $this->getUser()->getEmail(),
        'required' => true))
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start', 'class' => 'btn-primary']))
      ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end',
        'onclick' => "location.href='".$this->generateUrl('utenti_profilo')."'"]))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      $vecchia_email = $this->getUser()->getEmail();
      // validazione
      $this->getUser()->setEmail($form->get('email')->getData());
      $errors = $validator->validate($this->getUser());
      if (count($errors) > 0) {
        $form->addError(new FormError($errors[0]->getMessage()));
      } else {
        // memorizza modifica
        $this->em->flush();
        // log azione
        $dblogger->logAzione('SICUREZZA', 'Cambio Email', array(
          'Precedente email' => $vecchia_email));
        // messaggio di successo
        $this->addFlash('success', 'message.update_ok');
        // redirezione
        return $this->redirectToRoute('utenti_profilo');
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
                                 LogHandler $dblogger) {
    $success = null;
    $errore = null;
    $form = null;
    // controllo accesso
    $idProvider = $this->reqstack->getSession()->get('/CONFIG/ACCESSO/id_provider');
    $idProviderTipo = $this->reqstack->getSession()->get('/CONFIG/ACCESSO/id_provider_tipo');
    if ($idProvider && $this->getUser()->controllaRuolo($idProviderTipo)) {
      // cambio password su Google
      $errore = 'exception.cambio_password_google';
    } elseif (substr($this->getUser()->getEmail(), -6) == '.local') {
      // utente senza email
      $errore = 'exception.cambio_password_noemail';
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
          'required' => true))
        ->add('submit', SubmitType::class, array('label' => 'label.submit',
          'attr' => ['widget' => 'gs-button-start', 'class' => 'btn-primary']))
        ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
          'attr' => ['widget' => 'gs-button-end',
          'onclick' => "location.href='".$this->generateUrl('utenti_profilo')."'"]))
        ->getForm();
      $form->handleRequest($request);
      if ($form->isSubmitted() && $form->isValid()) {
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
        if ($form->isValid()) {
          // codifica password
          $password = $hasher->hashPassword($this->getUser(), $psw);
          $this->getUser()->setPassword($password);
          // memorizza password
          $this->em->flush();
          // log azione
          $dblogger->logAzione('SICUREZZA', 'Cambio Password', array());
          // messaggio di successo
          $this->addFlash('success', 'message.update_ok');
          // redirezione
          return $this->redirectToRoute('utenti_profilo');
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
   * @IsGranted("ROLE_UTENTE")
   */
  public function otpAction(Request $request, TranslatorInterface $trans, OtpUtil $otp,
                            LogHandler $dblogger) {
    // inizializza
    $reset = false;
    $qrcode = null;
    $form = null;
    // controlla accesso
    $idProvider = $this->reqstack->getSession()->get('/CONFIG/ACCESSO/id_provider');
    $idProviderTipo = $this->reqstack->getSession()->get('/CONFIG/ACCESSO/id_provider_tipo');
    $otpTipo = $this->reqstack->getSession()->get('/CONFIG/ACCESSO/otp_tipo');
    if (($idProvider && $this->getUser()->controllaRuolo($idProviderTipo)) ||
        !$this->getUser()->controllaRuolo($otpTipo)) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // impostazione OTP
    if ($this->getUser()->getOtp()) {
      // risulta già associato
      $reset = true;
      // form reset OTP
      $form = $this->container->get('form.factory')->createNamedBuilder('utenti_otp', FormType::class)
        ->add('submit', SubmitType::class, array('label' => 'label.submit',
          'attr' => ['class' => 'btn btn-primary']))
        ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
          'attr' => ['onclick' => "location.href='".$this->generateUrl('utenti_profilo')."'"]))
        ->getForm();
      $form->handleRequest($request);
      if ($form->isSubmitted() && $form->isValid()) {
        // reset otp
        $this->getUser()->setOtp(null);
        $this->em->flush();
        // log azione
        $dblogger->logAzione('SICUREZZA', 'Disattivazione OTP', array());
        // messaggio di successo
        $this->addFlash('success', 'message.otp_disabilitato');
        // redirezione
        return $this->redirectToRoute('utenti_profilo');
      }
    } else {
      // prima associazione con un dispositivo
      if ($request->getMethod() == 'POST') {
        // legge token esistente
        $token = $this->reqstack->getSession()->get('/APP/ROUTE/utenti_otp/token');
      } else {
        // crea token
        $token = $otp->creaToken($this->getUser()->getUsername());
        $this->reqstack->getSession()->set('/APP/ROUTE/utenti_otp/token', $token);
      }
      // crea qrcode
      $qrcode = $otp->qrcode($this->getUser()->getUsername(), 'Registro Elettronico', $token);
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
          $this->getUser()->setOtp($token);
          $this->em->flush();
          // cancella sessione
          $this->reqstack->getSession()->set('/APP/ROUTE/utenti_otp/token', '');
          // log azione
          $dblogger->logAzione('SICUREZZA', 'Attivazione OTP', array());
          // messaggio di successo
          $this->addFlash('success', 'message.otp_abilitato');
          // redirezione
          return $this->redirectToRoute('utenti_profilo');
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
      'reset' => $reset,
      'qrcode' => $qrcode,
      ));
  }

  /**
   * Gestione delle impostazioni di notifica dell'utente connesso
   *
   * @param Request $request Pagina richiesta
   * @param LogHandler $dblogger Gestore dei log su database
   *
   * @return Response Pagina di risposta
   *
   * @Route("/utenti/notifiche/", name="utenti_notifiche",
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_UTENTE")
   */
  public function notificheAction(Request $request, LogHandler $dblogger) {
    // init
    $dati = [];
    $info = [];
    // legge dati
    $notifica = $this->getUser()->getNotifica();
    // form
    $form = $this->createForm(NotificaType::class, null, [
      'returnUrl' => $this->generateUrl('utenti_profilo'),
      'values' => [$notifica['tipo'], $notifica['abilitato']]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // modifica dati
      $notifica['tipo'] = $form->get('tipo')->getData();
      $notifica['abilitato'] = $form->get('abilitato')->getData();
      $this->getUser()->setNotifica($notifica);
      // log e memorizzazione
      $dblogger->logAzione('CONFIGURAZIONE', 'Notifiche', [$notifica]);
      // controlla configurazione
      if (($notifica['tipo'] == 'email' && (empty($this->getUser()->getEmail()) || substr($this->getUser()->getEmail(), -6) == '.local')) ||
          ($notifica['tipo'] == 'telegram' && empty($notifica['telegram_chat']))) {
        // redirect alla configurazione
        return $this->redirectToRoute('utenti_notifiche_configura');
      }
      // messaggio di successo
      $this->addFlash('success', 'message.update_ok');
      // redirezione
      return $this->redirectToRoute('utenti_profilo');
    }
    // visualizza pagina
    return $this->renderHtml('utenti', 'notifiche', $dati, $info, [$form->createView(),
      'message.utenti.notifiche']);
  }

  /**
   * Configura il canale usato per l'invio delle notifiche
   *
   * @return Response Pagina di risposta
   *
   * @Route("/utenti/notifiche/configura/", name="utenti_notifiche_configura",
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_UTENTE")
   */
  public function notificheConfiguraAction() {
    // init
    $dati = [];
    $info = [];
    // legge dati
    $notifica = $this->getUser()->getNotifica();
    if ($notifica['tipo'] == 'email' &&
        (empty($this->getUser()->getEmail()) || substr($this->getUser()->getEmail(), -6) == '.local')) {
      // imposta email
      $info['messaggio'] = 'message.notifiche_configura_email';
      $info['url'] = $this->generateUrl('utenti_email');
    } elseif ($notifica['tipo'] == 'telegram' && empty($notifica['telegram_chat'])) {
      // imposta informazioni
      $this->getUser()->creaToken();
      $this->em->flush();
      $token = base64_encode($this->getUser()->getToken().'#'.$this->getUser()->getUsername());
      $bot = $this->em->getRepository('App\Entity\Configurazione')->getParametro('telegram_bot');
      $info['messaggio'] = 'message.notifiche_configura_telegram';
      $info['url'] = 'https://t.me/'.$bot.'?start='.$token;
    } else {
      // errore
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // visualizza pagina
    return $this->renderHtml('utenti', 'notifiche_configura', $dati, $info, []);
  }

}
