<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Controller;

use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Entity\Classe;
use Exception;
use App\Entity\Staff;
use App\Entity\Sede;
use App\Entity\Materia;
use App\Entity\Alunno;
use App\Entity\Cattedra;
use App\Entity\Docente;
use App\Entity\Provisioning;
use App\Form\CattedraType;
use App\Form\DocenteType;
use App\Form\ImportaCsvType;
use App\Form\ModuloType;
use App\Form\RicercaType;
use App\Util\CsvImporter;
use App\Util\LogHandler;
use App\Util\PdfManager;
use App\Util\StaffUtil;
use Psr\Log\LoggerInterface;
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
 * DocentiController - gestione docenti
 *
 * @author Antonello Dessì
 */
class DocentiController extends BaseController {

  /**
   * Importa docenti da file
   *
   * @param Request $request Pagina richiesta
   * @param CsvImporter $importer Servizio per l'importazione dei dati da file CSV
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/docenti/importa/', name: 'docenti_importa', methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_AMMINISTRATORE')]
  public function importa(Request $request, CsvImporter $importer): Response {
    // init
    $dati = [];
    $info = [];
    $var_sessione = '/APP/FILE/docenti_importa';
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
    $form = $this->createForm(ImportaCsvType::class, null, ['form_mode' => 'docenti']);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // trova file caricato
      $file = null;
      foreach ($this->reqstack->getSession()->get($var_sessione.'/file', []) as $f) {
        $file = new File($this->getParameter('dir_tmp').'/'.$f['temp']);
      }
      // importa file
      switch ($form->get('tipo')->getData()) {
        case 'U':
          // importa utenti
          $dati = $importer->importaDocenti($form, $file);
          break;
        case 'C':
          // importa cattedre
          $dati = $importer->importaCattedre($form, $file);
          break;
        case 'O':
          // importa orario
          $dati = $importer->importaOrario($form, $file);
          break;
      }
      $dati = ($dati == null ? [] : $dati);
      // cancella dati sessione
      $this->reqstack->getSession()->remove($var_sessione.'/file');
    }
    // visualizza pagina
    return $this->renderHtml('docenti', 'importa', $dati, $info, [$form->createView(),  'message.importa_docenti']);
  }

  /**
   * Gestisce la modifica dei dati dei docenti
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param int $pagina Numero di pagina per la lista visualizzata
   *
   */
  #[Route(path: '/docenti/modifica/{pagina}', name: 'docenti_modifica', requirements: ['pagina' => '\d+'], defaults: ['pagina' => 0], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_AMMINISTRATORE')]
  public function modifica(Request $request, TranslatorInterface $trans, int $pagina): Response {
    // init
    $dati = [];
    $info = [];
    // recupera criteri dalla sessione
    $criteri = [];
    $criteri['classe'] = (int) $this->reqstack->getSession()->get('/APP/ROUTE/docenti_modifica/classe');
    $classe = ($criteri['classe'] > 0 ? $this->em->getRepository(Classe::class)->find($criteri['classe']) : $criteri['classe']);
    $criteri['cognome'] = $this->reqstack->getSession()->get('/APP/ROUTE/docenti_modifica/cognome', '');
    $criteri['nome'] = $this->reqstack->getSession()->get('/APP/ROUTE/docenti_modifica/nome', '');
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $this->reqstack->getSession()->get('/APP/ROUTE/docenti_modifica/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/docenti_modifica/pagina', $pagina);
    }
    // form di ricerca
    $opzioniClassi = $this->em->getRepository(Classe::class)->opzioni(null, false);
    $opzioniClassi[$trans->trans('label.nessuna_classe')] = -1;
    $form = $this->createForm(RicercaType::class, null, ['form_mode' => 'docenti-alunni',
      'values' => [$classe, $opzioniClassi, $criteri['cognome'], $criteri['nome']]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $criteri['classe'] = is_object($form->get('classe')->getData()) ?
        $form->get('classe')->getData()->getId() : ((int) $form->get('classe')->getData());
      $criteri['cognome'] = trim((string) $form->get('cognome')->getData());
      $criteri['nome'] = trim((string) $form->get('nome')->getData());
      $pagina = 1;
      $this->reqstack->getSession()->set('/APP/ROUTE/docenti_modifica/classe', $criteri['classe']);
      $this->reqstack->getSession()->set('/APP/ROUTE/docenti_modifica/cognome', $criteri['cognome']);
      $this->reqstack->getSession()->set('/APP/ROUTE/docenti_modifica/nome', $criteri['nome']);
      $this->reqstack->getSession()->set('/APP/ROUTE/docenti_modifica/pagina', $pagina);
    }
    // lista docenti
    $dati = $this->em->getRepository(Docente::class)->cerca($criteri, $pagina);
    $info['pagina'] = $pagina;
    // mostra la pagina di risposta
    return $this->renderHtml('docenti', 'modifica', $dati, $info, [$form->createView()]);
  }

  /**
   * Abilitazione o disabilitazione dei docenti
   *
   * @param int $id ID dell'utente
   * @param int $abilita Valore 1 per abilitare, valore 0 per disabilitare
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/docenti/abilita/{id}/{abilita}', name: 'docenti_abilita', requirements: ['id' => '\d+', 'abilita' => '0|1'], methods: ['GET'])]
  #[IsGranted('ROLE_AMMINISTRATORE')]
  public function abilita(int $id, int $abilita): Response {
    // controllo docente
    $docente = $this->em->getRepository(Docente::class)->find($id);
    if (!$docente) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // abilita o disabilita
    $docente->setAbilitato($abilita == 1);
    // provisioning
    $provisioning = (new Provisioning())
      ->setUtente($docente)
      ->setFunzione('sospendeUtente')
      ->setDati(['sospeso' => !$abilita]);
    $this->em->persist($provisioning);
    // memorizza modifiche
    $this->em->flush();
    // messaggio
    $this->addFlash('success', 'message.update_ok');
    // redirezione
    return $this->redirectToRoute('docenti_modifica');
  }

  /**
   * Modifica dei dati di un docente
   *
   * @param Request $request Pagina richiesta
   * @param int $id ID dell'utente
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/docenti/modifica/edit/{id}', name: 'docenti_modifica_edit', requirements: ['id' => '\d+'], defaults: ['id' => '0'], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_AMMINISTRATORE')]
  public function modificaEdit(Request $request, int $id): Response {
    // controlla azione
    if ($id > 0) {
      // azione edit
      $docente = $this->em->getRepository(Docente::class)->find($id);
      if (!$docente) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $docente_old = ['cognome' => $docente->getCognome(), 'nome' => $docente->getNome(),
        'sesso' => $docente->getSesso()];
    } else {
      // azione add
      $docente = (new Docente())
        ->setAbilitato(true)
        ->setPassword('NOPASSWORD');
      $this->em->persist($docente);
    }
    // form
    $form = $this->createForm(DocenteType::class, $docente, ['return_url' => $this->generateUrl('docenti_modifica')]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // provisioning
      if (!$id) {
        // crea docente
        $provisioning = (new Provisioning())
          ->setUtente($docente)
          ->setFunzione('creaUtente')
          ->setDati(['password' => 'NOPASSWORD']);
        $this->em->persist($provisioning);
      } elseif ($docente->getCognome() != $docente_old['cognome'] || $docente->getNome() != $docente_old['nome'] ||
                $docente->getSesso() != $docente_old['sesso']) {
        // modifica dati docente
        $provisioning = (new Provisioning())
          ->setUtente($docente)
          ->setFunzione('modificaUtente')
          ->setDati([]);
        $this->em->persist($provisioning);
      }
      // memorizza modifiche
      $this->em->flush();
      // messaggio
      $this->addFlash('success', 'message.update_ok');
      // redirect
      return $this->redirectToRoute('docenti_modifica');
    }
    // mostra la pagina di risposta
    return $this->renderHtml('docenti', 'modifica_edit', [], [], [$form->createView(), 'message.required_fields']);
  }

  /**
   * Genera una nuova password e la invia all'utente docente
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
   */
  #[Route(path: '/docenti/password/{id}/{tipo}', name: 'docenti_password', requirements: ['id' => '\d+', 'tipo' => 'E|P'], methods: ['GET'])]
  #[IsGranted('ROLE_AMMINISTRATORE')]
  public function password(Request $request, UserPasswordHasherInterface $hasher,
                           PdfManager $pdf, StaffUtil $staff, MailerInterface $mailer,
                           LoggerInterface $logger, LogHandler $dblogger, int $id,
                           string $tipo): Response {
    // controlla docente
    $docente = $this->em->getRepository(Docente::class)->find($id);
    if (!$docente) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // crea password
    $password = $staff->creaPassword(10);
    $docente->setPasswordNonCifrata($password);
    $pswd = $hasher->hashPassword($docente, $docente->getPasswordNonCifrata());
    $docente->setPassword($pswd);
    // provisioning
    $provisioning = (new Provisioning())
      ->setUtente($docente)
      ->setFunzione('passwordUtente')
      ->setDati(['password' => $docente->getPasswordNonCifrata()]);
    $this->em->persist($provisioning);
    // memorizza su db
    $this->em->flush();
    // log azione
    $dblogger->logAzione('SICUREZZA', 'Generazione Password', [
      'Username' => $docente->getUsername(),
      'Ruolo' => $docente->getRoles()[0],
      'ID' => $docente->getId()]);
    // crea documento PDF
    $pdf->configure($this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
      'Credenziali di accesso al Registro Elettronico');
    // contenuto in formato HTML
    $html = $this->renderView('pdf/credenziali_docenti.html.twig', [
      'docente' => $docente,
      'password' => $password]);
    $pdf->createFromHtml($html);
    $html = $this->renderView('pdf/credenziali_privacy.html.twig', [
      'utente' => $docente]);
    $pdf->createFromHtml($html);
    if ($tipo == 'E') {
      // invia per email
      $doc = $pdf->getHandler()->Output('', 'S');
      $message = (new Email())
        ->from(new Address($this->reqstack->getSession()->get('/CONFIG/ISTITUTO/email_notifiche'), $this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione_breve')))
        ->to($docente->getEmail())
        ->subject($this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione_breve')." - Credenziali di accesso al Registro Elettronico")
        ->text($this->renderView('email/credenziali.txt.twig'))
        ->html($this->renderView('email/credenziali.html.twig'))
        ->attach($doc, 'credenziali_registro.pdf', 'application/pdf');
      try {
        // invia email
        $mailer->send($message);
        $this->addFlash('success', 'message.credenziali_inviate');
      } catch (Exception $err) {
        // errore di spedizione
        $logger->error('Errore di spedizione email delle credenziali docente.', [
          'username' => $docente->getUsername(),
          'email' => $docente->getEmail(),
          'ip' => $request->getClientIp(),
          'errore' => $err->getMessage()]);
        $this->addFlash('danger', 'exception.errore_invio_credenziali');
      }
      // redirezione
      return $this->redirectToRoute('docenti_modifica');
    } else {
      // scarica PDF
      $nomefile = 'credenziali-registro.pdf';
      return $pdf->send($nomefile);
    }
  }

  /**
   * Reset della funzione OTP per i docenti
   *
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $id ID dell'utente
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/docenti/otp/{id}', name: 'docenti_reset', requirements: ['id' => '\d+'], methods: ['GET'])]
  #[IsGranted('ROLE_AMMINISTRATORE')]
  public function reset(LogHandler $dblogger, int $id): Response {
    // controlla docente
    $docente = $this->em->getRepository(Docente::class)->find($id);
    if (!$docente) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // reset OTP
    $docente->setOtp(null);
    $this->em->flush();
    // log azione
    $dblogger->logAzione('SICUREZZA', 'Reset OTP', [
      'Username' => $docente->getUsername(),
      'Ruolo' => $docente->getRoles()[0],
      'ID' => $docente->getId()]);
    // messaggio ok
    $this->addFlash('success', 'message.credenziali_inviate');
    // redirezione
    return $this->redirectToRoute('docenti_modifica');
  }

  /**
   * Gestione dell'assegnamento del ruolo di staff
   *
   * @param Request $request Pagina richiesta
   * @param int $pagina Numero di pagina per la lista visualizzata
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/docenti/staff/{pagina}', name: 'docenti_staff', requirements: ['pagina' => '\d+'], defaults: ['pagina' => 0], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_AMMINISTRATORE')]
  public function staff(Request $request, int $pagina): Response {
    // init
    $dati = [];
    $info = [];
    // recupera criteri dalla sessione
    $criteri = [];
    $criteri['cognome'] = $this->reqstack->getSession()->get('/APP/ROUTE/docenti_staff/cognome', '');
    $criteri['nome'] = $this->reqstack->getSession()->get('/APP/ROUTE/docenti_staff/nome', '');
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $this->reqstack->getSession()->get('/APP/ROUTE/docenti_staff/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/docenti_staff/pagina', $pagina);
    }
    // form di ricerca
    $form = $this->createForm(RicercaType::class, null, ['form_mode' => 'utenti',
      'values' => [$criteri['cognome'], $criteri['nome']]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $criteri['cognome'] = trim((string) $form->get('cognome')->getData());
      $criteri['nome'] = trim((string) $form->get('nome')->getData());
      $pagina = 1;
      $this->reqstack->getSession()->set('/APP/ROUTE/docenti_staff/cognome', $criteri['cognome']);
      $this->reqstack->getSession()->set('/APP/ROUTE/docenti_staff/nome', $criteri['nome']);
      $this->reqstack->getSession()->set('/APP/ROUTE/docenti_staff/pagina', $pagina);
    }
    // lista staff
    $dati = $this->em->getRepository(Staff::class)->cerca($criteri, $pagina);
    $info['pagina'] = $pagina;
    // mostra la pagina di risposta
    return $this->renderHtml('docenti', 'staff', $dati, $info, [$form->createView()]);
  }

  /**
   * Modifica dei dati di configurazione dello staff
   *
   * @param Request $request Pagina richiesta
   * @param int $id ID dell'utente
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/docenti/staff/edit/{id}', name: 'docenti_staff_edit', requirements: ['id' => '\d+'], defaults: ['id' => '0'], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_AMMINISTRATORE')]
  public function staffEdit(Request $request, int $id): Response {
    // controlla azione
    if ($id > 0) {
      // azione edit
      $staff = $this->em->getRepository(Staff::class)->find($id);
      if (!$staff) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $sede = $staff->getSede();
    } else {
      // azione add
      $staff = null;
      $sede = null;
    }
    // form
    $opzioniDocenti = $this->em->getRepository(Docente::class)->opzioni();
    $opzioniSedi = $this->em->getRepository(Sede::class)->opzioni();
    $form = $this->createForm(ModuloType::class, null, ['form_mode' => 'staff',
      'return_url' => $this->generateUrl('docenti_staff'), 'values' => [$staff, $opzioniDocenti,
        $sede, $opzioniSedi]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      if ($staff) {
        // modifica
        $staff->setSede($form->get('sede')->getData());
        $this->em->flush();
      } else {
        // nuovo
        $docente_id = $form->get('docente')->getData()->getId();
        $sede_id = ($form->get('sede')->getData() ? $form->get('sede')->getData()->getId() : null);
        // cambia ruolo in staff
        $sql = "UPDATE gs_utente SET modificato=NOW(),ruolo=:ruolo,sede_id=:sede WHERE id=:id";
        $params = ['ruolo' => 'STA', 'sede' => $sede_id, 'id' => $docente_id];
        $this->em->getConnection()->prepare($sql)->executeStatement($params);
      }
      // messaggio
      $this->addFlash('success', 'message.update_ok');
      // redirect
      return $this->redirectToRoute('docenti_staff');
    }
    // mostra la pagina di risposta
    return $this->renderHtml('docenti', 'staff_edit', [], [], [$form->createView(), 'message.required_fields']);
  }

  /**
   * Cancellazione del componente dello staff
   *
   * @param int $id ID dell'utente
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/docenti/staff/delete/{id}', name: 'docenti_staff_delete', requirements: ['id' => '\d+'], methods: ['GET'])]
  #[IsGranted('ROLE_AMMINISTRATORE')]
  public function staffDelete(int $id): Response {
    // controlla utente staff
    $staff = $this->em->getRepository(Staff::class)->find($id);
    if (!$staff) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // toglie il ruolo di staff
    $sql = "UPDATE gs_utente SET modificato=NOW(),ruolo=:ruolo,sede_id=:sede WHERE id=:id";
    $params = ['ruolo' => 'DOC', 'sede' => null, 'id' => $staff->getId()];
    $this->em->getConnection()->prepare($sql)->executeStatement($params);
    // messaggio
    $this->addFlash('success', 'message.update_ok');
    // redirezione
    return $this->redirectToRoute('docenti_staff');
  }

  /**
   * Gestione dell'assegnamento del ruolo di coordinatore
   *
   * @param Request $request Pagina richiesta
   * @param int $pagina Numero di pagina per la lista visualizzata
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/docenti/coordinatori/{pagina}', name: 'docenti_coordinatori', requirements: ['pagina' => '\d+'], defaults: ['pagina' => 0], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_AMMINISTRATORE')]
  public function coordinatori(Request $request, int $pagina): Response {
    // init
    $dati = [];
    $info = [];
    // recupera criteri dalla sessione
    $criteri = [];
    $criteri['classe'] = (int) $this->reqstack->getSession()->get('/APP/ROUTE/docenti_coordinatori/classe');
    $classe = ($criteri['classe'] > 0 ? $this->em->getRepository(Classe::class)->find($criteri['classe']) : null);
    $criteri['cognome'] = $this->reqstack->getSession()->get('/APP/ROUTE/docenti_coordinatori/cognome', '');
    $criteri['nome'] = $this->reqstack->getSession()->get('/APP/ROUTE/docenti_coordinatori/nome', '');
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $this->reqstack->getSession()->get('/APP/ROUTE/docenti_coordinatori/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/docenti_coordinatori/pagina', $pagina);
    }
    // form di ricerca
    $opzioniClassi = $this->em->getRepository(Classe::class)->opzioni(null, false);
    $form = $this->createForm(RicercaType::class, null, ['form_mode' => 'docenti-alunni',
      'values' => [$classe, $opzioniClassi, $criteri['cognome'], $criteri['nome']]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $criteri['classe'] = (is_object($form->get('classe')->getData()) ? $form->get('classe')->getData()->getId() :
        intval($form->get('classe')->getData()));
      $criteri['cognome'] = trim((string) $form->get('cognome')->getData());
      $criteri['nome'] = trim((string) $form->get('nome')->getData());
      $pagina = 1;
      $this->reqstack->getSession()->set('/APP/ROUTE/docenti_coordinatori/classe', $criteri['classe']);
      $this->reqstack->getSession()->set('/APP/ROUTE/docenti_coordinatori/cognome', $criteri['cognome']);
      $this->reqstack->getSession()->set('/APP/ROUTE/docenti_coordinatori/nome', $criteri['nome']);
      $this->reqstack->getSession()->set('/APP/ROUTE/docenti_coordinatori/pagina', $pagina);
    }
    // lista coordinatori
    $dati = $this->em->getRepository(Classe::class)->cercaCoordinatori($criteri, $pagina);
    $info['pagina'] = $pagina;
    // mostra la pagina di risposta
    return $this->renderHtml('docenti', 'coordinatori', $dati, $info, [$form->createView()]);
  }

  /**
   * Modifica dei dati di configurazione del coordinatore di classe
   *
   * @param Request $request Pagina richiesta
   * @param int $id ID della classe
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/docenti/coordinatori/edit/{id}', name: 'docenti_coordinatori_edit', requirements: ['id' => '\d+'], defaults: ['id' => '0'], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_AMMINISTRATORE')]
  public function coordinatoriEdit(Request $request, int $id): Response {
    // controlla azione
    if ($id > 0) {
      // azione edit
      $classe = $this->em->getRepository(Classe::class)->find($id);
      if (!$classe) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $docente = $classe->getCoordinatore();
      $classe_old = $classe->getId();
      $docente_old = $docente->getId();
    } else {
      // azione add
      $classe = null;
      $docente = null;
    }
    // form
    $opzioniClassi = $this->em->getRepository(Classe::class)->opzioni(null, false);
    $opzioniDocenti = $this->em->getRepository(Docente::class)->opzioni();
    $form = $this->createForm(ModuloType::class, null, ['form_mode' => 'coordinatori',
      'return_url' => $this->generateUrl('docenti_coordinatori'),
      'values' => [$classe, $opzioniClassi, $docente, $opzioniDocenti]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      $classe = $form->get('classe')->getData();
      $docente = $form->get('docente')->getData();
      $classe->setCoordinatore($docente);
      // provisioning
      if (!$id) {
        // aggiunge coordinatore
        $provisioning = (new Provisioning())
          ->setUtente($docente)
          ->setFunzione('aggiungeCoordinatore')
          ->setDati(['docente' => $docente->getId(), 'classe' => $classe->getId()]);
        $this->em->persist($provisioning);
      } elseif ($docente->getId() != $docente_old || $classe->getId() != $classe_old) {
        // modifica dati docente
        $provisioning = (new Provisioning())
          ->setUtente($docente)
          ->setFunzione('modificaCoordinatore')
          ->setDati(['docente' => $docente->getId(), 'classe' => $classe->getId(),
            'docente_prec' => $docente_old, 'classe_prec' => $classe_old]);
        $this->em->persist($provisioning);
      }
      // memorizza
      $this->em->flush();
      // messaggio
      $this->addFlash('success', 'message.update_ok');
      // redirect
      return $this->redirectToRoute('docenti_coordinatori');
    }
    // mostra la pagina di risposta
    return $this->renderHtml('docenti', 'coordinatori_edit', [], [], [$form->createView(), 'message.required_fields']);
  }

  /**
   * Gestione della cancellazione del ruolo di coordinatore
   *
   * @param int $id ID della classe
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/docenti/coordinatori/delete/{id}', name: 'docenti_coordinatori_delete', requirements: ['id' => '\d+'], methods: ['GET'])]
  #[IsGranted('ROLE_AMMINISTRATORE')]
  public function coordinatoriDelete(int $id): Response {
    // controlla classe
    $classe = $this->em->getRepository(Classe::class)->find($id);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // toglie coordinatore
    $docente_old = $classe->getCoordinatore();
    $classe->setCoordinatore(null);
    // provisioning
    $provisioning = (new Provisioning())
      ->setUtente($docente_old)
      ->setFunzione('rimuoveCoordinatore')
      ->setDati(['docente' => $docente_old->getId(), 'classe' => $classe->getId()]);
    $this->em->persist($provisioning);
    // memorizza
    $this->em->flush();
    // messaggio
    $this->addFlash('success', 'message.update_ok');
    // redirezione
    return $this->redirectToRoute('docenti_coordinatori');
  }

  /**
   * Gestione dell'assegnamento del ruolo di segretario
   *
   * @param Request $request Pagina richiesta
   * @param int $pagina Numero di pagina per la lista visualizzata
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/docenti/segretari/{pagina}', name: 'docenti_segretari', requirements: ['pagina' => '\d+'], defaults: ['pagina' => 0], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_AMMINISTRATORE')]
  public function segretari(Request $request, int $pagina): Response {
    // init
    $dati = [];
    $info = [];
    // recupera criteri dalla sessione
    $criteri = [];
    $criteri['classe'] = (int) $this->reqstack->getSession()->get('/APP/ROUTE/docenti_segretari/classe');
    $classe = ($criteri['classe'] > 0 ? $this->em->getRepository(Classe::class)->find($criteri['classe']) : null);
    $criteri['cognome'] = $this->reqstack->getSession()->get('/APP/ROUTE/docenti_segretari/cognome', '');
    $criteri['nome'] = $this->reqstack->getSession()->get('/APP/ROUTE/docenti_segretari/nome', '');
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $this->reqstack->getSession()->get('/APP/ROUTE/docenti_segretari/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/docenti_segretari/pagina', $pagina);
    }
    // form di ricerca
    $opzioniClassi = $this->em->getRepository(Classe::class)->opzioni(null, false);
    $form = $this->createForm(RicercaType::class, null, ['form_mode' => 'docenti-alunni',
      'values' => [$classe, $opzioniClassi, $criteri['cognome'], $criteri['nome']]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $criteri['classe'] = (is_object($form->get('classe')->getData()) ? $form->get('classe')->getData()->getId() :
        intval($form->get('classe')->getData()));
      $criteri['cognome'] = trim((string) $form->get('cognome')->getData());
      $criteri['nome'] = trim((string) $form->get('nome')->getData());
      $pagina = 1;
      $this->reqstack->getSession()->set('/APP/ROUTE/docenti_segretari/classe', $criteri['classe']);
      $this->reqstack->getSession()->set('/APP/ROUTE/docenti_segretari/cognome', $criteri['cognome']);
      $this->reqstack->getSession()->set('/APP/ROUTE/docenti_segretari/nome', $criteri['nome']);
      $this->reqstack->getSession()->set('/APP/ROUTE/docenti_segretari/pagina', $pagina);
    }
    // lista segretari
    $dati = $this->em->getRepository(Classe::class)->cercaSegretari($criteri, $pagina);
    $info['pagina'] = $pagina;
    // mostra la pagina di risposta
    return $this->renderHtml('docenti', 'segretari', $dati, $info, [$form->createView()]);
  }

  /**
   * Modifica dei dati di configurazione del segretario di classe
   *
   * @param Request $request Pagina richiesta
   * @param int $id ID della classe
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/docenti/segretari/edit/{id}', name: 'docenti_segretari_edit', requirements: ['id' => '\d+'], defaults: ['id' => '0'], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_AMMINISTRATORE')]
  public function segretariEdit(Request $request, int $id): Response {
    // controlla azione
    if ($id > 0) {
      // azione edit
      $classe = $this->em->getRepository(Classe::class)->find($id);
      if (!$classe) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $docente = $classe->getSegretario();
      $classe_old = $classe->getId();
      $docente_old = $docente->getId();
    } else {
      // azione add
      $classe = null;
      $docente = null;
    }
    // form
    $opzioniClassi = $this->em->getRepository(Classe::class)->opzioni(null, false);
    $opzioniDocenti = $this->em->getRepository(Docente::class)->opzioni();
    $form = $this->createForm(ModuloType::class, null, ['form_mode' => 'coordinatori',
      'return_url' => $this->generateUrl('docenti_segretari'),
      'values' => [$classe, $opzioniClassi, $docente, $opzioniDocenti]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      $classe = $form->get('classe')->getData();
      $docente = $form->get('docente')->getData();
      $classe->setSegretario($docente);
      // provisioning
      if (!$id) {
        // aggiunge coordinatore
        $provisioning = (new Provisioning())
          ->setUtente($docente)
          ->setFunzione('aggiungeCoordinatore')
          ->setDati(['docente' => $docente->getId(), 'classe' => $classe->getId()]);
        $this->em->persist($provisioning);
      } elseif ($docente->getId() != $docente_old || $classe->getId() != $classe_old) {
        // modifica dati docente
        $provisioning = (new Provisioning())
          ->setUtente($docente)
          ->setFunzione('modificaCoordinatore')
          ->setDati(['docente' => $docente->getId(), 'classe' => $classe->getId(),
            'docente_prec' => $docente_old, 'classe_prec' => $classe_old]);
        $this->em->persist($provisioning);
      }
      // memorizza
      $this->em->flush();
      // messaggio
      $this->addFlash('success', 'message.update_ok');
      // redirect
      return $this->redirectToRoute('docenti_segretari');
    }
    // mostra la pagina di risposta
    return $this->renderHtml('docenti', 'segretari_edit', [], [], [$form->createView(), 'message.required_fields']);
  }

  /**
   * Gestione della cancellazione del ruolo di segretario
   *
   * @param int $id ID dell'utente
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/docenti/segretari/delete/{id}', name: 'docenti_segretari_delete', requirements: ['id' => '\d+'], methods: ['GET'])]
  #[IsGranted('ROLE_AMMINISTRATORE')]
  public function segretariDelete(int $id): Response {
    // controlla classe
    $classe = $this->em->getRepository(Classe::class)->find($id);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // toglie coordinatore
    $docente_old = $classe->getSegretario();
    $classe->setSegretario(null);
    // provisioning
    $provisioning = (new Provisioning())
      ->setUtente($docente_old)
      ->setFunzione('rimuoveCoordinatore')
      ->setDati(['docente' => $docente_old->getId(), 'classe' => $classe->getId()]);
    $this->em->persist($provisioning);
    // memorizza
    $this->em->flush();
    // messaggio
    $this->addFlash('success', 'message.update_ok');
    // redirezione
    return $this->redirectToRoute('docenti_segretari');
  }

  /**
   * Gestisce la modifica delle cattedre dei docenti
   *
   * @param Request $request Pagina richiesta
   * @param int $pagina Numero di pagina per la lista visualizzata
   *
   */
  #[Route(path: '/docenti/cattedre/{pagina}', name: 'docenti_cattedre', requirements: ['pagina' => '\d+'], defaults: ['pagina' => 0], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_AMMINISTRATORE')]
  public function cattedre(Request $request, int $pagina): Response {
    // init
    $dati = [];
    $info = [];
    // recupera criteri dalla sessione
    $criteri = [];
    $criteri['classe'] = (int) $this->reqstack->getSession()->get('/APP/ROUTE/docenti_cattedre/classe');
    $criteri['materia'] = (int) $this->reqstack->getSession()->get('/APP/ROUTE/docenti_cattedre/materia');
    $criteri['docente'] = (int) $this->reqstack->getSession()->get('/APP/ROUTE/docenti_cattedre/docente');
    $classe = ($criteri['classe'] > 0 ? $this->em->getRepository(Classe::class)->find($criteri['classe']) : null);
    $materia = ($criteri['materia'] > 0 ? $this->em->getRepository(Materia::class)->find($criteri['materia']) : null);
    $docente = ($criteri['docente'] > 0 ? $this->em->getRepository(Docente::class)->find($criteri['docente']) : null);
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $this->reqstack->getSession()->get('/APP/ROUTE/docenti_cattedre/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/docenti_cattedre/pagina', $pagina);
    }
    // form di ricerca
    $opzioniClassi = $this->em->getRepository(Classe::class)->opzioni(null, false);
    $opzioniMaterie = $this->em->getRepository(Materia::class)->opzioni(true, false);
    $opzioniDocenti = $this->em->getRepository(Docente::class)->opzioni();
    $form = $this->createForm(RicercaType::class, null, ['form_mode' => 'cattedre',
      'values' => [$classe, $opzioniClassi, $materia, $opzioniMaterie, $docente, $opzioniDocenti]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $criteri['classe'] = ($form->get('classe')->getData() ? $form->get('classe')->getData()->getId() : null);
      $criteri['materia'] = ($form->get('materia')->getData() ? $form->get('materia')->getData()->getId() : null);
      $criteri['docente'] = ($form->get('docente')->getData() ? $form->get('docente')->getData()->getId() : null);
      $pagina = 1;
      $this->reqstack->getSession()->set('/APP/ROUTE/docenti_cattedre/classe', $criteri['classe']);
      $this->reqstack->getSession()->set('/APP/ROUTE/docenti_cattedre/materia', $criteri['materia']);
      $this->reqstack->getSession()->set('/APP/ROUTE/docenti_cattedre/docente', $criteri['docente']);
      $this->reqstack->getSession()->set('/APP/ROUTE/docenti_cattedre/pagina', $pagina);
    }
    // lista cattedre
    $dati = $this->em->getRepository(Cattedra::class)->cerca($criteri, $pagina);
    $info['pagina'] = $pagina;
    // mostra la pagina di risposta
    return $this->renderHtml('docenti', 'cattedre', $dati, $info, [$form->createView()]);
  }

  /**
   * Crea o modifica una cattedra di un docente
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param int $id ID della cattedra
   *
   */
  #[Route(path: '/docenti/cattedre/edit/{id}', name: 'docenti_cattedre_edit', requirements: ['id' => '\d+'], defaults: ['id' => '0'], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_AMMINISTRATORE')]
  public function cattedreEdit(Request $request, TranslatorInterface $trans, int $id): Response {
    // controlla azione
    if ($id > 0) {
      // azione edit
      $cattedra = $this->em->getRepository(Cattedra::class)->find($id);
      if (!$cattedra) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $cattedra_old = ['docente' => $cattedra->getDocente()->getId(),
        'classe' => $cattedra->getClasse()->getId(), 'materia' => $cattedra->getMateria()->getId()];
    } else {
      // azione add
      $cattedra = (new Cattedra())
        ->setAttiva(true);
      $this->em->persist($cattedra);
    }
    // form
    $opzioniClassi = $this->em->getRepository(Classe::class)->opzioni(null, false);
    $opzioniMaterie = $this->em->getRepository(Materia::class)->opzioni(true, false);
    $opzioniSostegno = $this->em->getRepository(Alunno::class)->opzioniSostegno();
    $opzioniDocenti = $this->em->getRepository(Docente::class)->opzioni();
    $form = $this->createForm(CattedraType::class, $cattedra, [
      'return_url' => $this->generateUrl('docenti_cattedre'),
      'values' => [$opzioniClassi, $opzioniMaterie, $opzioniSostegno, $opzioniDocenti]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      if ($cattedra->getMateria()->getTipo() == 'S') {
        // sostegno
        if ($cattedra->getAlunno() && $cattedra->getAlunno()->getClasse() != $cattedra->getClasse()) {
          // classe diversa da quella di alunno
          $form->get('classe')->addError(new FormError($trans->trans('exception.classe_errata')));
        }
      } else {
        // materia non è sostegno, nessun alunno deve essere presente
        $cattedra->setAlunno(null);
      }
      if ($id == 0) {
        // controlla esistenza di cattedra
        $lista = $this->em->getRepository(Cattedra::class)->findBy([
          'docente' => $cattedra->getDocente(),
          'classe' => $cattedra->getClasse(),
          'materia' => $cattedra->getMateria(),
          'alunno' => $cattedra->getAlunno()]);
        if (count($lista) > 0) {
          // cattedra esiste già
          $form->addError(new FormError($trans->trans('exception.cattedra_esiste')));
        }
      }
      if ($form->isValid()) {
        // memorizza dati
        $this->em->flush();
        // provisioning
        if (!$id) {
          // crea cattedra
          $provisioning = (new Provisioning())
            ->setUtente($cattedra->getDocente())
            ->setFunzione('aggiungeCattedra')
            ->setDati(['cattedra' => $cattedra->getId()]);
          $this->em->persist($provisioning);
        } elseif ($cattedra->getDocente()->getId() != $cattedra_old['docente'] ||
                  $cattedra->getClasse()->getId() != $cattedra_old['classe'] ||
                  $cattedra->getMateria()->getId() != $cattedra_old['materia']) {
          // modifica dati docente
          $provisioning = (new Provisioning())
            ->setUtente($cattedra->getDocente())
            ->setFunzione('modificaCattedra')
            ->setDati(['cattedra' => $cattedra->getId(), 'docente' => $cattedra_old['docente'],
              'classe' => $cattedra_old['classe'], 'materia' => $cattedra_old['materia']]);
          $this->em->persist($provisioning);
        }
        $this->em->flush();
        // messaggio
        $this->addFlash('success', 'message.update_ok');
        // redirect
        return $this->redirectToRoute('docenti_cattedre');
      }
    }
    // mostra la pagina di risposta
    return $this->renderHtml('docenti', 'cattedre_edit', [], [], [$form->createView(), 'message.required_fields']);
  }

  /**
   * Abilitazione o disabilitazione delle cattedre
   *
   * @param int $id ID della cattedra
   * @param int $abilita Valore 1 per abilitare, valore 0 per disabilitare
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/docenti/cattedre/abilita/{id}/{abilita}', name: 'docenti_cattedre_abilita', requirements: ['id' => '\d+', 'abilita' => '0|1'], methods: ['GET'])]
  #[IsGranted('ROLE_AMMINISTRATORE')]
  public function cattedreEnable(int $id, int $abilita): Response {
    // controllo cattedra
    $cattedra = $this->em->getRepository(Cattedra::class)->find($id);
    if (!$cattedra) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // abilita o disabilita
    $cattedra->setAttiva($abilita == 1);
    // provisioning
    if ($abilita) {
      // aggiunge cattedra
      $provisioning = (new Provisioning())
        ->setUtente($cattedra->getDocente())
        ->setFunzione('aggiungeCattedra')
        ->setDati(['cattedra' => $cattedra->getId()]);
      $this->em->persist($provisioning);
    } else {
      // rimuove cattedra
      $provisioning = (new Provisioning())
        ->setUtente($cattedra->getDocente())
        ->setFunzione('rimuoveCattedra')
        ->setDati(['docente' => $cattedra->getDocente()->getId(), 'classe' => $cattedra->getClasse()->getId(),
          'materia' => $cattedra->getMateria()->getId()]);
      $this->em->persist($provisioning);
    }
    // memorizza dati
    $this->em->flush();
    // messaggio
    $this->addFlash('success', 'message.update_ok');
    // redirezione
    return $this->redirectToRoute('docenti_cattedre');
  }

  /**
   * Configurazione dei responsabili BES
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param int $pagina Numero di pagina per la lista visualizzata
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/docenti/responsabiliBes/{pagina}', name: 'docenti_responsabiliBes', requirements: ['pagina' => '\d+'], defaults: ['pagina' => 0], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_AMMINISTRATORE')]
  public function responsabiliBes(Request $request, TranslatorInterface $trans, int $pagina): Response {
    // init
    $dati = [];
    $info = [];
    // recupera criteri dalla sessione
    $criteri = [];
    $criteri['sede'] = (int) $this->reqstack->getSession()->get('/APP/ROUTE/docenti_responsabiliBes/sede');
    $sede = ($criteri['sede'] > 0 ? $this->em->getRepository(Sede::class)->find($criteri['sede']) : $criteri['sede']);
    $criteri['cognome'] = $this->reqstack->getSession()->get('/APP/ROUTE/docenti_responsabiliBes/cognome', '');
    $criteri['nome'] = $this->reqstack->getSession()->get('/APP/ROUTE/docenti_responsabiliBes/nome', '');
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $this->reqstack->getSession()->get('/APP/ROUTE/docenti_responsabiliBes/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/docenti_responsabiliBes/pagina', $pagina);
    }
    // form di ricerca
    $opzioniSedi = $this->em->getRepository(Sede::class)->opzioni();
    $opzioniSedi[$trans->trans('label.tutte_sedi')] = -1;
    $form = $this->createForm(RicercaType::class, null, ['form_mode' => 'ata',
      'values' => [$sede, $opzioniSedi, $criteri['cognome'], $criteri['nome']]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $criteri['sede'] = (is_object($form->get('sede')->getData()) ? $form->get('sede')->getData()->getId() :
        intval($form->get('sede')->getData()));
      $criteri['cognome'] = trim((string) $form->get('cognome')->getData());
      $criteri['nome'] = trim((string) $form->get('nome')->getData());
      $pagina = 1;
      $this->reqstack->getSession()->set('/APP/ROUTE/docenti_responsabiliBes/sede', $criteri['sede']);
      $this->reqstack->getSession()->set('/APP/ROUTE/docenti_responsabiliBes/cognome', $criteri['cognome']);
      $this->reqstack->getSession()->set('/APP/ROUTE/docenti_responsabiliBes/nome', $criteri['nome']);
      $this->reqstack->getSession()->set('/APP/ROUTE/docenti_responsabiliBes/pagina', $pagina);
    }
    // lista responsabili
    $dati = $this->em->getRepository(Docente::class)->responsabiliBes($criteri, $pagina);
    $info['pagina'] = $pagina;
    // mostra la pagina di risposta
    return $this->renderHtml('docenti', 'responsabiliBes', $dati, $info, [$form->createView()]);
  }

  /**
   * Modifica i dati di configurazione dei responsabili BES
   *
   * @param Request $request Pagina richiesta
   * @param int $id ID dell'utente
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/docenti/responsabiliBes/edit/{id}', name: 'docenti_responsabiliBes_edit', requirements: ['id' => '\d+'], defaults: ['id' => '0'], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_AMMINISTRATORE')]
  public function responsabiliBesEdit(Request $request, int $id): Response {
    // controlla azione
    if ($id > 0) {
      // azione edit
      $docente = $this->em->getRepository(Docente::class)->find($id);
      if (!$docente) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $sede = $docente->getResponsabileBesSede();
    } else {
      // azione add
      $docente = null;
      $sede = null;
    }
    // form
    $opzioniSedi = $this->em->getRepository(Sede::class)->opzioni();
    $opzioniDocenti = $this->em->getRepository(Docente::class)->opzioni();
    $form = $this->createForm(ModuloType::class, null, ['form_mode' => 'staff',
      'return_url' => $this->generateUrl('docenti_responsabiliBes'),
      'values' => [$docente, $opzioniDocenti, $sede, $opzioniSedi]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      if ($docente) {
        // modifica
        $docente->setResponsabileBesSede($form->get('sede')->getData());
      } else {
        // nuovo
        $docente = $form->get('docente')->getData();
        $docente->setResponsabileBes(true);
        $docente->setResponsabileBesSede($form->get('sede')->getData());
      }
      // memorizza dati
      $this->em->flush();
      // messaggio
      $this->addFlash('success', 'message.update_ok');
      // redirect
      return $this->redirectToRoute('docenti_responsabiliBes');
    }
    // mostra la pagina di risposta
    return $this->renderHtml('docenti', 'responsabiliBes_edit', [], [], [$form->createView(),
      'message.required_fields']);
  }

  /**
   * Cancellazione del responsabile BES
   *
   * @param int $id ID dell'utente
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/docenti/responsabiliBes/delete/{id}', name: 'docenti_responsabiliBes_delete', requirements: ['id' => '\d+'], methods: ['GET'])]
  #[IsGranted('ROLE_AMMINISTRATORE')]
  public function responsabiliBesDelete(int $id): Response {
    // controlla utente
    $docente = $this->em->getRepository(Docente::class)->find($id);
    if (!$docente) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // toglie il ruolo di responsabile BES
    $docente->setResponsabileBes(false);
    $docente->setResponsabileBesSede(null);
    // memorizza dati
    $this->em->flush();
    // messaggio
    $this->addFlash('success', 'message.update_ok');
    // redirezione
    return $this->redirectToRoute('docenti_responsabiliBes');
  }

  /**
   * Gestione inserimento dei rappresentanti docenti
   *
   * @param Request $request Pagina richiesta
   * @param int $pagina Numero di pagina per la lista visualizzata
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/docenti/rappresentanti/{pagina}', name: 'docenti_rappresentanti', requirements: ['pagina' => '\d+'], defaults: ['pagina' => 0], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_AMMINISTRATORE')]
  public function rappresentanti(Request $request, int $pagina): Response {
    // init
    $dati = [];
    $info = [];
    // recupera criteri dalla sessione
    $criteri = [];
    $criteri['tipo'] = $this->reqstack->getSession()->get('/APP/ROUTE/docenti_rappresentanti/tipo', '');
    $criteri['cognome'] = $this->reqstack->getSession()->get('/APP/ROUTE/docenti_rappresentanti/cognome', '');
    $criteri['nome'] = $this->reqstack->getSession()->get('/APP/ROUTE/docenti_rappresentanti/nome', '');
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $this->reqstack->getSession()->get('/APP/ROUTE/docenti_rappresentanti/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/docenti_rappresentanti/pagina', $pagina);
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
      $this->reqstack->getSession()->set('/APP/ROUTE/docenti_rappresentanti/tipo', $criteri['tipo']);
      $this->reqstack->getSession()->set('/APP/ROUTE/docenti_rappresentanti/cognome', $criteri['cognome']);
      $this->reqstack->getSession()->set('/APP/ROUTE/docenti_rappresentanti/nome', $criteri['nome']);
      $this->reqstack->getSession()->set('/APP/ROUTE/docenti_rappresentanti/pagina', $pagina);
    }
    // lista rappresentanti
    $dati = $this->em->getRepository(Docente::class)->rappresentanti($criteri, $pagina);
    // mostra la pagina di risposta
    $info['pagina'] = $pagina;
    return $this->renderHtml('docenti', 'rappresentanti', $dati, $info, [$form->createView()]);
  }

  /**
   * Modifica i dati di un rappresentante dei docenti
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param int $id ID dell'utente
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/docenti/rappresentanti/edit/{id}', name: 'docenti_rappresentanti_edit', requirements: ['id' => '\d+'], defaults: ['id' => '0'], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_AMMINISTRATORE')]
  public function rappresentantiEdit(Request $request, TranslatorInterface $trans,
                                     int $id): Response {
    // controlla azione
    if ($id > 0) {
      // azione edit
      $utente = $this->em->getRepository(Docente::class)->find($id);
      if (!$utente) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $tipi = $utente->getRappresentante();
      $listaUtenti = [$utente];
    } else {
      // azione add
      $utente = null;
      $tipi = [];
      $listaUtenti = $this->em->getRepository(Docente::class)->opzioni();
    }
    // form
    $listaTipi = ['label.rappresentante_I' => 'I', 'label.rappresentante_R' => 'R'];
    $form = $this->createForm(ModuloType::class, null, ['form_mode' => 'rappresentanti',
      'return_url' => $this->generateUrl('docenti_rappresentanti'),
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
        return $this->redirectToRoute('docenti_rappresentanti');
      }
    }
    // mostra la pagina di risposta
    return $this->renderHtml('docenti', 'rappresentanti_edit', [], [],
      [$form->createView(), 'message.required_fields']);
  }

  /**
   * Elimina un rappresentante
   *
   * @param int $id ID dell'utente
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/docenti/rappresentanti/delete/{id}', name: 'docenti_rappresentanti_delete', requirements: ['id' => '\d+'], methods: ['GET'])]
  #[IsGranted('ROLE_AMMINISTRATORE')]
  public function rappresentantiDelete(int $id): Response {
    // controlla utente
    $utente = $this->em->getRepository(Docente::class)->find($id);
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
    return $this->redirectToRoute('docenti_rappresentanti');
  }

  /**
   * Modifica dei dati del responsabile della sicurezza (un solo utente possibile)
   *
   * @param Request $request Pagina richiesta
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/docenti/rspp', name: 'docenti_rspp', methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_AMMINISTRATORE')]
  public function rspp(Request $request): Response {
    // init
    $dati = [];
    $info = [];
    // legge dati
    $rspp = $this->em->getRepository(Docente::class)->findOneBy(['rspp' => 1]);
    // form
    $opzioniDocenti = $this->em->getRepository(Docente::class)->opzioni();
    $form = $this->createForm(ModuloType::class, null, ['form_mode' => 'rspp',
      'return_url' => $this->generateUrl('docenti_rspp'),
      'values' => [$rspp, $opzioniDocenti]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // cancella precedente rspp
      if ($rspp) {
        $rspp->setRspp(false);
      }
      // imposta nuovo rspp
      $docente = $form->get('docente')->getData();
      if ($docente) {
        $docente->setRspp(true);
      }
      // memorizza modifiche
      $this->em->flush();
    }
    // mostra la pagina di risposta
    return $this->renderHtml('docenti', 'rspp', $dati, $info, [$form->createView(), 'message.required_fields']);
  }

}
