<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Controller;

use App\Entity\Ata;
use App\Form\AtaType;
use App\Form\ImportaCsvType;
use App\Form\ModuloType;
use App\Form\RicercaType;
use App\Util\CsvImporter;
use App\Util\LogHandler;
use App\Util\PdfManager;
use App\Util\StaffUtil;
use Psr\Log\LoggerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;


/**
 * AtaController - gestione ata
 *
 * @author Antonello Dessì
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
  public function importaAction(Request $request, CsvImporter $importer): Response {
    // init
    $dati = [];
    $info = [];
    $var_sessione = '/APP/FILE/ata_importa';
    $fs = new Filesystem();
    if (!$request->isMethod('POST')) {
      // cancella dati sessione
      $this->reqstack->getSession()->remove($var_sessione.'/file');
      // elimina file temporanei
      $finder = new Finder();
      $finder->in($this->getParameter('dir_tmp'))->date('< 1 day ago');
      foreach ($finder as $f) {
        $fs->remove($f);
      }
    }
    // form
    $form = $this->createForm(ImportaCsvType::class, null, ['form_mode' => 'ata']);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // trova file caricato
      $file = null;
      foreach ($this->reqstack->getSession()->get($var_sessione.'/file', []) as $f) {
        $file = new File($this->getParameter('dir_tmp').'/'.$f['temp']);
      }
      // importa file
      $dati = $importer->importaAta($file, $form);
      $dati = ($dati == null ? [] : $dati);
      // cancella dati sessione
      $this->reqstack->getSession()->remove($var_sessione.'/file');
    }
    // visualizza pagina
    return $this->renderHtml('ata', 'importa', $dati, $info, [$form->createView(),  'message.importa_ata']);
  }

  /**
   * Gestisce la modifica dei dati del personale ATA
   *
   * @param Request $request Pagina richiesta
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
  public function modificaAction(Request $request, TranslatorInterface $trans, int $pagina): Response {
    // init
    $dati = [];
    $info = [];
    // recupera criteri dalla sessione
    $criteri = array();
    $criteri['sede'] = (int) $this->reqstack->getSession()->get('/APP/ROUTE/ata_modifica/sede');
    $sede = ($criteri['sede'] > 0 ? $this->em->getRepository('App\Entity\Sede')->find($criteri['sede']) : $criteri['sede']);
    $criteri['cognome'] = $this->reqstack->getSession()->get('/APP/ROUTE/ata_modifica/cognome', '');
    $criteri['nome'] = $this->reqstack->getSession()->get('/APP/ROUTE/ata_modifica/nome', '');
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $this->reqstack->getSession()->get('/APP/ROUTE/ata_modifica/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/ata_modifica/pagina', $pagina);
    }
    // form di ricerca
    $opzioniSedi = $this->em->getRepository('App\Entity\Sede')->opzioni();
    $opzioniSedi[$trans->trans('label.nessuna_sede')] = -1;
    $form = $this->createForm(RicercaType::class, null, ['form_mode' => 'ata',
      'values' => [$sede, $opzioniSedi, $criteri['cognome'], $criteri['nome']]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $criteri['sede'] = is_object($form->get('sede')->getData()) ?
        $form->get('sede')->getData()->getId() : ((int) $form->get('sede')->getData());
      $criteri['cognome'] = trim($form->get('cognome')->getData());
      $criteri['nome'] = trim($form->get('nome')->getData());
      $pagina = 1;
      $this->reqstack->getSession()->set('/APP/ROUTE/ata_modifica/sede', $criteri['sede']);
      $this->reqstack->getSession()->set('/APP/ROUTE/ata_modifica/cognome', $criteri['cognome']);
      $this->reqstack->getSession()->set('/APP/ROUTE/ata_modifica/nome', $criteri['nome']);
      $this->reqstack->getSession()->set('/APP/ROUTE/ata_modifica/pagina', $pagina);
    }
    // recupera dati
    $dati = $this->em->getRepository('App\Entity\Ata')->cerca($criteri, $pagina);
    $info['pagina'] = $pagina;
    // mostra la pagina di risposta
    return $this->renderHtml('ata', 'modifica', $dati, $info, [$form->createView()]);
  }

  /**
   * Abilitazione o disabilitazione degli utenti ATA
   *
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
  public function abilitaAction(int $id, int $abilita): Response {
    // controlla ata
    $ata = $this->em->getRepository('App\Entity\Ata')->find($id);
    if (!$ata) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // abilita o disabilita
    $ata->setAbilitato($abilita == 1);
    $this->em->flush();
    // messaggio
    $this->addFlash('success', 'message.update_ok');
    // redirezione
    return $this->redirectToRoute('ata_modifica');
  }

  /**
   * Modifica dei dati di un utente ATA
   *
   * @param Request $request Pagina richiesta
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
  public function modificaEditAction(Request $request, int $id): Response {
    // controlla azione
    if ($id > 0) {
      // azione edit
      $ata = $this->em->getRepository('App\Entity\Ata')->find($id);
      if (!$ata) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
    } else {
      // azione add
      $ata = (new Ata())
        ->setAbilitato(true)
        ->setPassword('NOPASSWORD');
      $this->em->persist($ata);
    }
    // form
    $opzioniSedi = $this->em->getRepository('App\Entity\Sede')->opzioni();
    $form = $this->createForm(AtaType::class, $ata, ['return_url' => $this->generateUrl('ata_modifica'),
      'values' => [$opzioniSedi]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // memorizza modifiche
      $this->em->flush();
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
   * @param UserPasswordHasherInterface $hasher Gestore della codifica delle password
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
  public function passwordAction(Request $request, UserPasswordHasherInterface $hasher,
                                 PdfManager $pdf, StaffUtil $staff, MailerInterface $mailer,
                                 LoggerInterface $logger, LogHandler $dblogger, int $id,
                                 string $tipo): Response {
    // controlla ata
    $ata = $this->em->getRepository('App\Entity\Ata')->find($id);
    if (!$ata) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // crea password
    $password = $staff->creaPassword(8);
    $ata->setPasswordNonCifrata($password);
    $pswd = $hasher->hashPassword($ata, $ata->getPasswordNonCifrata());
    $ata->setPassword($pswd);
    // memorizza su db
    $this->em->flush();
    // log azione
    $dblogger->logAzione('SICUREZZA', 'Generazione Password', array(
      'Username' => $ata->getUsername(),
      'Ruolo' => $ata->getRoles()[0],
      'ID' => $ata->getId()));
    // crea documento PDF
    $pdf->configure($this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
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
    if ($tipo == 'E') {
      // invia per email
      $doc = $pdf->getHandler()->Output('', 'S');
      $message = (new Email())
        ->from(new Address($this->reqstack->getSession()->get('/CONFIG/ISTITUTO/email_notifiche'), $this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione_breve')))
        ->to($ata->getEmail())
        ->subject($this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione_breve')." - Credenziali di accesso al Registro Elettronico")
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
      // scarica il PDF
      $nomefile = 'credenziali-registro.pdf';
      return $pdf->send($nomefile);
    }
  }

  /**
   * Gestione inserimento dei rappresentanti ATA
   *
   * @param Request $request Pagina richiesta
   * @param int $pagina Numero di pagina per la lista visualizzata
   *
   * @return Response Pagina di risposta
   *
   * @Route("/ata/rappresentanti/{pagina}", name="ata_rappresentanti",
   *    requirements={"pagina": "\d+"},
   *    defaults={"pagina": 0},
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function rappresentantiAction(Request $request, int $pagina): Response {
    // init
    $dati = [];
    $info = [];
    // recupera criteri dalla sessione
    $criteri = array();
    $criteri['tipo'] = $this->reqstack->getSession()->get('/APP/ROUTE/ata_rappresentanti/tipo', '');
    $criteri['cognome'] = $this->reqstack->getSession()->get('/APP/ROUTE/ata_rappresentanti/cognome', '');
    $criteri['nome'] = $this->reqstack->getSession()->get('/APP/ROUTE/ata_rappresentanti/nome', '');
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $this->reqstack->getSession()->get('/APP/ROUTE/ata_rappresentanti/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/ata_rappresentanti/pagina', $pagina);
    }
    // form di ricerca
    $listaTipi = ['label.rappresentante_I' => 'I', 'label.rappresentante_R' => 'R'];
    $form = $this->createForm(RicercaType::class, null, ['form_mode' => 'rappresentanti',
      'values' => [$criteri['tipo'], $listaTipi, $criteri['cognome'], $criteri['nome']]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $criteri['tipo'] = $form->get('tipo')->getData();
      $criteri['cognome'] = $form->get('cognome')->getData();
      $criteri['nome'] = $form->get('nome')->getData();
      $pagina = 1;
      $this->reqstack->getSession()->set('/APP/ROUTE/ata_rappresentanti/tipo', $criteri['tipo']);
      $this->reqstack->getSession()->set('/APP/ROUTE/ata_rappresentanti/cognome', $criteri['cognome']);
      $this->reqstack->getSession()->set('/APP/ROUTE/ata_rappresentanti/nome', $criteri['nome']);
      $this->reqstack->getSession()->set('/APP/ROUTE/ata_rappresentanti/pagina', $pagina);
    }
    // lista rappresentanti
    $dati = $this->em->getRepository('App\Entity\Ata')->rappresentanti($criteri, $pagina);
    // mostra la pagina di risposta
    $info['pagina'] = $pagina;
    return $this->renderHtml('ata', 'rappresentanti', $dati, $info, [$form->createView()]);
  }

  /**
   * Modifica i dati di un rappresentante ATA
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param int $id ID dell'utente
   *
   * @return Response Pagina di risposta
   *
   * @Route("/ata/rappresentanti/edit/{id}", name="ata_rappresentanti_edit",
   *    requirements={"id": "\d+"},
   *    defaults={"id": "0"},
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function rappresentantiEditAction(Request $request, TranslatorInterface $trans,
                                           int $id): Response {
    // controlla azione
    if ($id > 0) {
      // azione edit
      $utente = $this->em->getRepository('App\Entity\Ata')->find($id);
      if (!$utente) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $tipi = $utente->getRappresentante();
      $listaUtenti = [$utente];
    } else {
      // azione add
      $utente = null;
      $tipi = array();
      $listaUtenti = $this->em->getRepository('App\Entity\Ata')->findBy(['abilitato' => 1,
          'rappresentante' => ['']], ['cognome' => 'ASC', 'nome' => 'ASC']);
    }
    // form
    $listaTipi = ['label.rappresentante_I' => 'I', 'label.rappresentante_R' => 'R'];
    $form = $this->createForm(ModuloType::class, null, ['form_mode' => 'rappresentanti',
      'return_url' => $this->generateUrl('ata_rappresentanti'),
      'values' => [$utente, $listaUtenti, $tipi, $listaTipi]]);
    $form->handleRequest($request);
    if ($form->isSubmitted()) {
      // controlla tipi
      $nuoviTipi = $form->get('tipi')->getData();
      if (empty($nuoviTipi)) {
        // errore
        $form->addError(new FormError($trans->trans('exception.tipi_rappresentante_vuoto')));
      }
      if ($form->isValid()) {
        // controlli ok
        if (!$utente) {
          // modifica
          $utente = $form->get('utente')->getData();
        }
        // imposta tipo rappresentante
        $utente->setRappresentante($nuoviTipi);
        // memorizza dati
        $this->em->flush();
        // messaggio
        $this->addFlash('success', 'message.update_ok');
        // redirect
        return $this->redirectToRoute('ata_rappresentanti');
      }
    }
    // mostra la pagina di risposta
    return $this->renderHtml('ata', 'rappresentanti_edit', [], [],
      [$form->createView(), 'message.required_fields']);
  }

  /**
   * Elimina un rappresentante
   *
   * @param int $id ID dell'utente
   *
   * @return Response Pagina di risposta
   *
   * @Route("/ata/rappresentanti/delete/{id}", name="ata_rappresentanti_delete",
   *    requirements={"id": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function rappresentantiDeleteAction(int $id): Response {
    // controlla utente
    $utente = $this->em->getRepository('App\Entity\Ata')->find($id);
    if (!$utente) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // toglie il ruolo di rappresentante
    $utente->setRappresentante(['']);
    // memorizza dati
    $this->em->flush();
    // messaggio
    $this->addFlash('success', 'message.update_ok');
    // redirezione
    return $this->redirectToRoute('ata_rappresentanti');
  }

}
