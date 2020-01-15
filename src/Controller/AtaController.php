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

use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use App\Util\CsvImporter;
use App\Util\LogHandler;
use App\Util\PdfManager;
use App\Form\AtaType;


/**
 * AtaController - gestione ata
 */
class AtaController extends BaseController {

  /**
   * Importa ATA da file
   *
   * @param Request $request Pagina richiesta
   * @param CsvImporter $importer Servizio per l'importazione dei dati da file CSV
   *
   * @return Response Pagina di risposta
   *
   * @Route("/ata/importa/", name="ata_importa",
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function importaAction(Request $request, CsvImporter $importer) {
    $lista = null;
    // form
    $form = $this->container->get('form.factory')->createNamedBuilder('ata_importa', FormType::class)
      ->add('file', FileType::class, array('label' => 'label.csv_file',
        'required' => true
        ))
      ->add('onlynew', CheckboxType::class, array('label' => 'label.solo_nuovi',
        'required' => false
        ))
      ->add('submit', SubmitType::class, array('label' => 'label.submit'))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // importa file
      $file = $form->get('file')->getData();
      $lista = $importer->importaAta($file, $form);
    }
    // visualizza pagina
    return $this->renderHtml('ata', 'importa', $lista ? $lista : [], ['titolo' => 'title.importa_ata'],
      [$form->createView(),  'message.importa_ata']);
  }

  /**
   * Gestisce la modifica dei dati del personale ATA
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param int $pagina Numero di pagina per la lista degli utenti
   *
   * @Route("/ata/modifica/{pagina}", name="ata_modifica",
   *    requirements={"pagina": "\d+"},
   *    defaults={"pagina": 0},
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function modificaAction(Request $request, EntityManagerInterface $em, SessionInterface $session, $pagina) {
    // recupera criteri dalla sessione
    $search = array();
    $search['nome'] = $session->get('/APP/ROUTE/ata_modifica/nome', '');
    $search['cognome'] = $session->get('/APP/ROUTE/ata_modifica/cognome', '');
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $session->get('/APP/ROUTE/ata_modifica/page', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $session->set('/APP/ROUTE/ata_modifica/page', $pagina);
    }
    // form di ricerca
    $limite = 10;
    $form = $this->container->get('form.factory')->createNamedBuilder('ata_modifica', FormType::class)
      ->add('cognome', TextType::class, array('label' => 'label.cognome',
        'data' => $search['cognome'],
        'attr' => ['placeholder' => 'label.cognome'],
        'label_attr' => ['class' => 'sr-only'],
        'required' => false
        ))
      ->add('nome', TextType::class, array('label' => 'label.nome',
        'data' => $search['nome'],
        'attr' => ['placeholder' => 'label.nome'],
        'label_attr' => ['class' => 'sr-only'],
        'required' => false
        ))
      ->add('submit', SubmitType::class, array('label' => 'label.search'))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $search['nome'] = trim($form->get('nome')->getData());
      $search['cognome'] = trim($form->get('cognome')->getData());
      $pagina = 1;
      $session->set('/APP/ROUTE/ata_modifica/nome', $search['nome']);
      $session->set('/APP/ROUTE/ata_modifica/cognome', $search['cognome']);
      $session->set('/APP/ROUTE/ata_modifica/pagina', $pagina);
    }
    // lista
    $dati = $em->getRepository('App:ATA')->findAll($search, $pagina, $limite);
    // mostra la pagina di risposta
    return $this->render('ata/modifica.html.twig', array(
      'pagina_titolo' => 'page.modifica_ata',
      'form' => $form->createView(),
      'form_help' => null,
      'form_success' => null,
      'lista' => $dati,
      'page' => $pagina,
      'maxPages' => ceil($dati->count() / $limite),
    ));
  }

  /**
   * Abilitazione o disabilitazione degli utenti ATA
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param int $id ID dell'utente
   * @param int $abilita Valore 1 per abilitare, valore 0 per disabilitare
   *
   * @return Response Pagina di risposta
   *
   * @Route("/ata/abilita/{id}/{abilita}", name="ata_abilita",
   *    requirements={"id": "\d+", "abilita": "0|1"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function abilitaAction(EntityManagerInterface $em, $id, $abilita) {
    // controlla ata
    $ata = $em->getRepository('App:Ata')->find($id);
    if (!$ata) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // abilita o disabilita
    $ata->setAbilitato($abilita == 1);
    $em->flush();
    // redirezione
    return $this->redirectToRoute('ata_modifica');
  }

  /**
   * Modifica dei dati di un utente ATA
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param int $id ID dell'utente
   *
   * @return Response Pagina di risposta
   *
   * @Route("/ata/edit/{id}", name="ata_edit",
   *    requirements={"id": "\d+"},
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function editAction(Request $request, EntityManagerInterface $em, $id) {
    // controlla ata
    $ata = $em->getRepository('App:Ata')->find($id);
    if (!$ata) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // form
    $form = $this->createForm(AtaType::class, $ata, ['returnUrl' => $this->generateUrl('ata_modifica')]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // memorizza modifiche
      $em->flush();
      // redirect
      return $this->redirectToRoute('ata_modifica');
    }
    // mostra la pagina di risposta
    return $this->render('ata/edit.html.twig', array(
      'pagina_titolo' => 'page.modifica_ata',
      'form' => $form->createView(),
      'form_title' => 'title.modifica_ata',
      'form_help' => 'message.required_fields',
      'form_success' => null,
    ));
   }

  /**
   * Genera una nuova password e la invia all'utente ATA
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param UserPasswordEncoderInterface $encoder Gestore della codifica delle password
   * @param SessionInterface $session Gestore delle sessioni
   * @param PdfManager $pdf Gestore dei documenti PDF
   * @param \Swift_Mailer $mailer Gestore della spedizione delle email
   * @param LoggerInterface $logger Gestore dei log su file
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $id ID del docente
   *
   * @return Response Pagina di risposta
   *
   * @Route("/ata/password/{id}", name="ata_password",
   *    requirements={"id": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function passwordAction(Request $request, EntityManagerInterface $em, UserPasswordEncoderInterface $encoder, SessionInterface $session,
                                  PdfManager $pdf, \Swift_Mailer $mailer, LoggerInterface $logger, LogHandler $dblogger,
                                  $id) {
    // controlla ata
    $ata = $em->getRepository('App:Ata')->find($id);
    if (!$ata) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // crea password
    $pwdchars = "abcdefghikmnopqrstuvwxyz123456789";
    $password = substr(str_shuffle($pwdchars), 0, 4).substr(str_shuffle($pwdchars), 0, 4);
    $ata->setPasswordNonCifrata($password);
    $pswd = $encoder->encodePassword($ata, $ata->getPasswordNonCifrata());
    $ata->setPassword($pswd);
    // memorizza su db
    $em->flush();
    // log azione
    $dblogger->write($ata, $request->getClientIp(), 'SICUREZZA', 'Generazione Password', __METHOD__, array(
      'Username esecutore' => $this->getUser()->getUsername(),
      'Ruolo esecutore' => $this->getUser()->getRoles()[0],
      'ID esecutore' => $this->getUser()->getId()
      ));
    // crea documento PDF
    $pdf->configure($session->get('/CONFIG/ISTITUTO/intestazione'),
      'Credenziali di accesso al Registro Elettronico');
    // contenuto in formato HTML
    $html = $this->renderView('pdf/credenziali_ata.html.twig', array(
      'ata' => $ata,
      'password' => $password,
      ));
    $pdf->createFromHtml($html);
    $html = $this->renderView('pdf/credenziali_privacy.html.twig', array(
      'ata' => $ata,
      ));
    $pdf->createFromHtml($html);
    $doc = $pdf->getHandler()->Output('', 'S');
    // crea il messaggio
    $message = (new \Swift_Message())
      ->setSubject($session->get('/CONFIG/ISTITUTO/intestazione_breve')." - Credenziali di accesso al Registro Elettronico")
      ->setFrom([$session->get('/CONFIG/ISTITUTO/email_notifiche') => $session->get('/CONFIG/ISTITUTO/intestazione_breve')])
      ->setTo([$ata->getEmail()])
      ->setBody($this->renderView('email/credenziali_ata.html.twig'), 'text/html')
      ->addPart($this->renderView('email/credenziali_ata.txt.twig'), 'text/plain')
      ->attach(new \Swift_Attachment($doc, 'credenziali_registro.pdf', 'application/pdf'));
    // invia mail
    if (!$mailer->send($message)) {
      // errore di spedizione
      $logger->error('Errore di spedizione email delle credenziali ata.', array(
        'username' => $ata->getUsername(),
        'email' => $ata->getEmail(),
        'ip' => $request->getClientIp(),
        ));
      $this->addFlash('danger', 'exception.errore_invio_credenziali');
    } else {
      // tutto ok
      $this->addFlash('success', 'message.credenziali_inviate');
    }
    // redirezione
    return $this->redirectToRoute('ata_modifica');
  }

}
