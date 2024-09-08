<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Controller;

use App\Form\NotificaType;
use App\Util\LogHandler;
use App\Util\OtpUtil;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
   */
  #[Route(path: '/utenti/profilo/', name: 'utenti_profilo', methods: ['GET'])]
  #[IsGranted('ROLE_UTENTE')]
  public function profilo(): Response {
    // mostra la pagina di risposta
    return $this->render('utenti/profilo.html.twig', [
      'pagina_titolo' => 'page.utenti_profilo']);
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
   */
  #[Route(path: '/utenti/email/', name: 'utenti_email', methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_UTENTE')]
  public function email(Request $request, ValidatorInterface $validator,
                        LogHandler $dblogger): Response {
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
      ->add('email', TextType::class, ['label' => 'label.email',
	      'data' => str_ends_with((string) $this->getUser()->getEmail(), '.local') ? '' : $this->getUser()->getEmail(),
        'required' => true])
      ->add('submit', SubmitType::class, ['label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start', 'class' => 'btn-primary']])
      ->add('cancel', ButtonType::class, ['label' => 'label.cancel',
	      'attr' => ['widget' => 'gs-button-end',
          'onclick' => "location.href='".$this->generateUrl('utenti_profilo')."'"]])
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
        $dblogger->logAzione('SICUREZZA', 'Cambio Email', [
          'Precedente email' => $vecchia_email]);
        // messaggio di successo
        $this->addFlash('success', 'message.update_ok');
        // redirezione
        return $this->redirectToRoute('utenti_profilo');
      }
    }
    // mostra la pagina di risposta
    return $this->render('utenti/email.html.twig', [
      'pagina_titolo' => 'page.utenti_email',
      'form' => $form->createView(),
      'form_title' => 'title.modifica_email',
      'form_help' => 'message.modifica_email',
      'form_success' => $success]);
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
   */
  #[Route(path: '/utenti/password/', name: 'utenti_password', methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_UTENTE')]
  public function password(Request $request, UserPasswordHasherInterface $hasher,
                           TranslatorInterface $trans, ValidatorInterface $validator,
                           LogHandler $dblogger): Response {
    $success = null;
    $errore = null;
    $form = null;
    // controllo accesso
    $idProvider = $this->reqstack->getSession()->get('/CONFIG/ACCESSO/id_provider');
    $idProviderTipo = $this->reqstack->getSession()->get('/CONFIG/ACCESSO/id_provider_tipo');
    if ($idProvider && $this->getUser()->controllaRuolo($idProviderTipo)) {
      // cambio password su Google
      $errore = 'exception.cambio_password_google';
    } elseif (str_ends_with((string) $this->getUser()->getEmail(), '.local')) {
      // utente senza email
      $errore = 'exception.cambio_password_noemail';
    } else {
      // form
      $form = $this->container->get('form.factory')->createNamedBuilder('utenti_password', FormType::class)
        ->add('current_password', PasswordType::class, ['label' => 'label.current_password',
	        'required' => true])
        ->add('password', RepeatedType::class, [
          'type' => PasswordType::class,
          'invalid_message' => 'password.nomatch',
          'first_options' => ['label' => 'label.new_password'],
          'second_options' => ['label' => 'label.new_password2'],
          'required' => true])
        ->add('submit', SubmitType::class, ['label' => 'label.submit',
          'attr' => ['widget' => 'gs-button-start', 'class' => 'btn-primary']])
        ->add('cancel', ButtonType::class, ['label' => 'label.cancel',
	        'attr' => ['widget' => 'gs-button-end',
            'onclick' => "location.href='".$this->generateUrl('utenti_profilo')."'"]])
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
        $minuscole = preg_match('/[a-z]+/', (string) $psw);
        $maiuscole = preg_match('/[A-Z]+/', (string) $psw);
        $cifre = preg_match('/\d+/', (string) $psw);
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
          $dblogger->logAzione('SICUREZZA', 'Cambio Password', []);
          // messaggio di successo
          $this->addFlash('success', 'message.update_ok');
          // redirezione
          return $this->redirectToRoute('utenti_profilo');
        }
      }
    }
    // mostra la pagina di risposta
    return $this->render('utenti/password.html.twig', [
      'pagina_titolo' => 'page.utenti_password',
      'form' => ($form ? $form->createView() : null),
      'form_title' => 'title.modifica_password',
      'form_help' => 'message.modifica_password',
      'form_success' => $success,
      'errore' => $errore]);
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
   */
  #[Route(path: '/utenti/otp/', name: 'utenti_otp', methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_UTENTE')]
  public function otp(Request $request, TranslatorInterface $trans, OtpUtil $otp,
                      LogHandler $dblogger): Response {
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
        ->add('submit', SubmitType::class, ['label' => 'label.submit',
	        'attr' => ['class' => 'btn btn-primary']])
        ->add('cancel', ButtonType::class, ['label' => 'label.cancel',
	        'attr' => ['onclick' => "location.href='".$this->generateUrl('utenti_profilo')."'"]])
        ->getForm();
      $form->handleRequest($request);
      if ($form->isSubmitted() && $form->isValid()) {
        // reset otp
        $this->getUser()->setOtp(null);
        $this->em->flush();
        // log azione
        $dblogger->logAzione('SICUREZZA', 'Disattivazione OTP', []);
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
        $token = $otp->creaToken($this->getUser()->getUserIdentifier());
        $this->reqstack->getSession()->set('/APP/ROUTE/utenti_otp/token', $token);
      }
      // crea qrcode
      $qrcode = $otp->qrcode($this->getUser()->getUserIdentifier(), 'Registro Elettronico', $token);
      // form inserimeno OTP
      $form = $this->container->get('form.factory')->createNamedBuilder('utenti_otp', FormType::class)
        ->add('otp', TextType::class, ['label' => 'label.otp',
          'attr' => ['class' => 'gs-ml-2'],
          'trim' => true,
          'required' => true])
        ->add('submit', SubmitType::class, ['label' => 'label.submit',
          'attr' => ['class' => 'btn btn-primary']])
        ->add('cancel', ButtonType::class, ['label' => 'label.cancel',
	        'attr' => ['onclick' => "location.href='".$this->generateUrl('utenti_profilo')."'"]])
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
          $dblogger->logAzione('SICUREZZA', 'Attivazione OTP', []);
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
    return $this->render('utenti/otp.html.twig', [
      'pagina_titolo' => 'page.utenti_otp',
      'form' => ($form ? $form->createView() : null),
      'form_help' => null,
      'form_success' => null,
      'reset' => $reset,
      'qrcode' => $qrcode]);
  }

  /**
   * Gestione delle impostazioni di notifica dell'utente connesso
   *
   * @param Request $request Pagina richiesta
   * @param LogHandler $dblogger Gestore dei log su database
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/utenti/notifiche/', name: 'utenti_notifiche', methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_UTENTE')]
  public function notifiche(Request $request, LogHandler $dblogger): Response {
    // init
    $dati = [];
    $info = [];
    // legge dati
    $notifica = $this->getUser()->getNotifica();
    // controlla configurazione telegram
    $bot = $this->em->getRepository(\App\Entity\Configurazione::class)->getParametro('telegram_bot');
    if (empty($bot) && $notifica['tipo'] == 'telegram') {
      // elimina notifica telegram
      $notifica['tipo'] = 'email';
    }
    // form
    $form = $this->createForm(NotificaType::class, null, [
      'return_url' => $this->generateUrl('utenti_profilo'),
      'values' => [$notifica['tipo'], empty($bot), $notifica['abilitato']]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // modifica dati
      $nuovaNotifica = $notifica;
      $nuovaNotifica['tipo'] = $form->get('tipo')->getData();
      if (empty($bot) && $nuovaNotifica['tipo'] == 'telegram') {
        // elimina notifica telegram
        $nuovaNotifica['tipo'] = 'email';
        unset($nuovaNotifica['telegram_chat']);
      } elseif ($notifica['tipo'] == 'telegram' && $nuovaNotifica['tipo'] != 'telegram') {
        // resetta chat Telegram
        unset($nuovaNotifica['telegram_chat']);
      }
      $nuovaNotifica['abilitato'] = $form->get('abilitato')->getData();
      $this->getUser()->setNotifica($nuovaNotifica);
      // log e memorizzazione
      $dblogger->logAzione('CONFIGURAZIONE', 'Notifiche', [$nuovaNotifica]);
      // controlla configurazione
      if (($nuovaNotifica['tipo'] == 'email' && (empty($this->getUser()->getEmail()) ||
           str_ends_with((string) $this->getUser()->getEmail(), '.local'))) ||
          ($nuovaNotifica['tipo'] == 'telegram' && empty($nuovaNotifica['telegram_chat']))) {
        // redirect alla configurazione
        return $this->redirectToRoute('utenti_notifiche_configura');
      }
      // messaggio di successo
      $this->addFlash('success', 'message.update_ok');
      // redirezione
      return $this->redirectToRoute('utenti_profilo');
    }
    // visualizza pagina
    return $this->render('utenti/notifiche.html.twig', [
      'pagina_titolo' => 'page.utenti.notifiche',
      'titolo' => 'title.utenti.notifiche',
      'dati' => $dati,
      'info' => $info,
      'form' => [$form->createView(), 'message.utenti.notifiche']]);
  }

  /**
   * Configura il canale usato per l'invio delle notifiche
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/utenti/notifiche/configura/', name: 'utenti_notifiche_configura', methods: ['GET'])]
  #[IsGranted('ROLE_UTENTE')]
  public function notificheConfigura(): Response {
    // init
    $dati = [];
    $info = [];
    // legge dati
    $notifica = $this->getUser()->getNotifica();
    if ($notifica['tipo'] == 'email' &&
        (empty($this->getUser()->getEmail()) || str_ends_with((string) $this->getUser()->getEmail(), '.local'))) {
      // imposta email
      $info['messaggio'] = 'message.notifiche_configura_email';
      $info['url'] = $this->generateUrl('utenti_email');
    } elseif ($notifica['tipo'] == 'telegram' && empty($notifica['telegram_chat'])) {
      // imposta informazioni
      $this->getUser()->creaToken();
      $this->em->flush();
      $token = base64_encode($this->getUser()->getToken().'#'.$this->getUser()->getUserIdentifier());
      $bot = $this->em->getRepository(\App\Entity\Configurazione::class)->getParametro('telegram_bot');
      $info['messaggio'] = 'message.notifiche_configura_telegram';
      $info['url'] = 'https://t.me/'.$bot.'?start='.$token;
    } else {
      // errore
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // visualizza pagina
    return $this->render('utenti/notifiche_configura.html.twig', [
      'pagina_titolo' => 'page.utenti.notifiche',
      'titolo' => 'title.utenti.notifiche_configura',
      'dati' => $dati,
      'info' => $info,
      'form' => []]);
  }

}
