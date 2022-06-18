<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2022 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2022
 */


namespace App\Controller;

use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use App\Util\CsvImporter;
use App\Util\LogHandler;
use App\Util\PdfManager;
use App\Util\StaffUtil;
use App\Form\AtaType;
use App\Form\ImportaCsvType;
use App\Form\RicercaType;
use App\Entity\Ata;
use App\Entity\Sede;


/**
 * AtaController - gestione ata
 */
class AtaController extends BaseController {

  /**
   * Importa ATA da file
   *
   * @param Request $request Pagina richiesta
   * @param SessionInterface $session Gestore delle sessioni
   * @param CsvImporter $importer Servizio per l'importazione dei dati da file CSV
   *
   * @return Response Pagina di risposta
   *
   * @Route("/ata/importa/", name="ata_importa",
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function importaAction(Request $request, SessionInterface $session, CsvImporter $importer): Response {
    // init
    $dati = [];
    $info = [];
    $var_sessione = '/APP/FILE/ata_importa';
    $fs = new FileSystem();
    if (!$request->isMethod('POST')) {
      // cancella dati sessione
      $session->remove($var_sessione);
      // elimina file temporanei
      $finder = new Finder();
      $finder->in($this->getParameter('dir_tmp'))->date('< 1 day ago');
      foreach ($finder as $f) {
        $fs->remove($f);
      }
    }
    // form
    $form = $this->createForm(ImportaCsvType::class, null, ['formMode' => 'ata']);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // trova file caricato
      $file = null;
      foreach ($session->get($var_sessione.'/file', []) as $f) {
        $file = new File($this->getParameter('dir_tmp').'/'.$f['temp']);
      }
      // importa file
      $dati = $importer->importaAta($file, $form);
      $dati = ($dati == null ? [] : $dati);
      // cancella dati sessione
      $session->remove($var_sessione);
    }
    // visualizza pagina
    return $this->renderHtml('ata', 'importa', $dati, $info, [$form->createView(),  'message.importa_ata']);
  }

  /**
   * Gestisce la modifica dei dati del personale ATA
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param int $pagina Numero di pagina per la lista degli utenti
   *
   * @return Response Pagina di risposta
   *
   * @Route("/ata/modifica/{pagina}", name="ata_modifica",
   *    requirements={"pagina": "\d+"},
   *    defaults={"pagina": 0},
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function modificaAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                 TranslatorInterface $trans, $pagina): Response {
    // init
    $dati = [];
    $info = [];
    // recupera criteri dalla sessione
    $criteri = array();
    $criteri['nome'] = $session->get('/APP/ROUTE/ata_modifica/nome', '');
    $criteri['cognome'] = $session->get('/APP/ROUTE/ata_modifica/cognome', '');
    $criteri['sede'] = $session->get('/APP/ROUTE/ata_modifica/sede', 0);
    $sede = ($criteri['sede'] > 0 ? $em->getRepository(Sede::class)->find($criteri['sede']) : 0);
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $session->get('/APP/ROUTE/ata_modifica/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $session->set('/APP/ROUTE/ata_modifica/pagina', $pagina);
    }
    // form di ricerca
    $lista_sedi = $em->getRepository(Sede::class)->findBy([], ['ordinamento' =>'ASC']);
    $lista_sedi[] = -1;
    $label_sede = $trans->trans('label.nessuna_sede');
    $form = $this->createForm(RicercaType::class, null, ['formMode' => 'ata',
      'dati' => [$criteri['cognome'], $criteri['nome'], $sede, $lista_sedi, $label_sede]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $criteri['nome'] = trim($form->get('nome')->getData());
      $criteri['cognome'] = trim($form->get('cognome')->getData());
      $criteri['sede'] = (is_object($form->get('sede')->getData()) ? $form->get('sede')->getData()->getId() :
        intval($form->get('sede')->getData()));
      $pagina = 1;
      $session->set('/APP/ROUTE/ata_modifica/nome', $criteri['nome']);
      $session->set('/APP/ROUTE/ata_modifica/cognome', $criteri['cognome']);
      $session->set('/APP/ROUTE/ata_modifica/sede', $criteri['sede']);
      $session->set('/APP/ROUTE/ata_modifica/pagina', $pagina);
    }
    // recupera dati
    $dati = $em->getRepository(Ata::class)->cerca($criteri, $pagina);
    $info['pagina'] = $pagina;
    // mostra la pagina di risposta
    return $this->renderHtml('ata', 'modifica', $dati, $info, [$form->createView()]);
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
  public function abilitaAction(EntityManagerInterface $em, $id, $abilita): Response {
    // controlla ata
    $ata = $em->getRepository(Ata::class)->find($id);
    if (!$ata) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // abilita o disabilita
    $ata->setAbilitato($abilita == 1);
    $em->flush();
    // messaggio
    $this->addFlash('success', 'message.update_ok');
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
   * @Route("/ata/edit/{id}", name="ata_modifica_edit",
   *    requirements={"id": "\d+"},
   *    defaults={"id": "0"},
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function modificaEditAction(Request $request, EntityManagerInterface $em, $id): Response {
    // controlla azione
    if ($id > 0) {
      // azione edit
      $ata = $em->getRepository(Ata::class)->find($id);
      if (!$ata) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
    } else {
      // azione add
      $ata = (new Ata())
        ->setAbilitato(true)
        ->setPassword('NOPASSWORD');
      $em->persist($ata);
    }
    // form
    $form = $this->createForm(AtaType::class, $ata, ['returnUrl' => $this->generateUrl('ata_modifica')]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // memorizza modifiche
      $em->flush();
      // messaggio
      $this->addFlash('success', 'message.update_ok');
      // redirect
      return $this->redirectToRoute('ata_modifica');
    }
    // mostra la pagina di risposta
    return $this->renderHtml('ata', 'modifica_edit', [], [], [$form->createView(), 'message.required_fields']);
   }

  /**
   * Genera una nuova password e la invia all'utente ATA
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param UserPasswordEncoderInterface $encoder Gestore della codifica delle password
   * @param SessionInterface $session Gestore delle sessioni
   * @param PdfManager $pdf Gestore dei documenti PDF
   * @param StaffUtil $staff Funzioni disponibili allo staff
   * @param MailerInterface $mailer Gestore della spedizione delle email
   * @param LoggerInterface $logger Gestore dei log su file
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $id ID dell'utente
   * @param string $tipo Tipo di creazione del documento [E=email, P=Pdf]
   *
   * @return Response Pagina di risposta
   *
   * @Route("/ata/password/{id}/{tipo}", name="ata_password",
   *    requirements={"id": "\d+", "tipo": "E|P"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function passwordAction(Request $request, EntityManagerInterface $em,
                                 UserPasswordEncoderInterface $encoder, SessionInterface $session,
                                 PdfManager $pdf, StaffUtil $staff, MailerInterface $mailer, LoggerInterface $logger,
                                 LogHandler $dblogger, $id, $tipo): Response {
    // controlla ata
    $ata = $em->getRepository(Ata::class)->find($id);
    if (!$ata) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // crea password
    $password = $staff->creaPassword(8);
    $ata->setPasswordNonCifrata($password);
    $pswd = $encoder->encodePassword($ata, $ata->getPasswordNonCifrata());
    $ata->setPassword($pswd);
    // memorizza su db
    $em->flush();
    // log azione
    $dblogger->logAzione('SICUREZZA', 'Generazione Password', array(
      'Username' => $ata->getUsername(),
      'Ruolo' => $ata->getRoles()[0],
      'ID' => $ata->getId()));
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
      'utente' => $ata));
    $pdf->createFromHtml($html);
    $doc = $pdf->getHandler()->Output('', 'S');
    if ($tipo == 'E') {
      // invia per email
      $message = (new Email())
        ->from(new Address($session->get('/CONFIG/ISTITUTO/email_notifiche'), $session->get('/CONFIG/ISTITUTO/intestazione_breve')))
        ->to($ata->getEmail())
        ->subject($session->get('/CONFIG/ISTITUTO/intestazione_breve')." - Credenziali di accesso al Registro Elettronico")
        ->text($this->renderView('email/credenziali.txt.twig'))
        ->html($this->renderView('email/credenziali.html.twig'))
        ->attach($doc, 'credenziali_registro.pdf', 'application/pdf');
      try {
        // invia email
        $mailer->send($message);
        $this->addFlash('success', 'message.credenziali_inviate');
      } catch (\Exception $err) {
        // errore di spedizione
        $logger->error('Errore di spedizione email delle credenziali ata.', array(
          'username' => $ata->getUsername(),
          'email' => $ata->getEmail(),
          'ip' => $request->getClientIp(),
          'errore' => $err->getMessage()));
        $this->addFlash('danger', 'exception.errore_invio_credenziali');
      }
      // redirezione
      return $this->redirectToRoute('ata_modifica');
    } else {
      // crea pdf e lo scarica
      $nomefile = 'credenziali-registro.pdf';
      $disposition = HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $nomefile);
      $response = new Response($doc);
      $response->headers->set('Content-Disposition', $disposition);
      return $response;
    }
  }

}
