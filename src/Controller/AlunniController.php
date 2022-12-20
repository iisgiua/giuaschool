<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Controller;

use App\Entity\Alunno;
use App\Entity\CambioClasse;
use App\Entity\Genitore;
use App\Entity\Provisioning;
use App\Form\AlunnoGenitoreType;
use App\Form\CambioClasseType;
use App\Form\ImportaCsvType;
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
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;


/**
 * AlunniController - gestione alunni e genitori
 *
 * @author Antonello Dessì
 */
class AlunniController extends BaseController {

  /**
   * Importa alunni e genitori da file
   *
   * @param Request $request Pagina richiesta
   * @param CsvImporter $importer Servizio per l'importazione dei dati da file CSV
   *
   * @return Response Pagina di risposta
   *
   * @Route("/alunni/importa/", name="alunni_importa",
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function importaAction(Request $request, CsvImporter $importer): Response {
    // init
    $dati = [];
    $info = [];
    $var_sessione = '/APP/FILE/alunni_importa';
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
    $form = $this->createForm(ImportaCsvType::class, null, ['formMode' => 'alunni']);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // trova file caricato
      $file = null;
      foreach ($this->reqstack->getSession()->get($var_sessione.'/file', []) as $f) {
        $file = new File($this->getParameter('dir_tmp').'/'.$f['temp']);
      }
      // importa file
      $dati = $importer->importaAlunni($file, $form);
      $dati = ($dati == null ? [] : $dati);
      // cancella dati sessione
      $this->reqstack->getSession()->remove($var_sessione.'/file');
    }
    // visualizza pagina
    return $this->renderHtml('alunni', 'importa', $dati, $info, [$form->createView(),  'message.importa_alunni']);
  }

  /**
   * Gestisce la modifica dei dati dei alunni
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param int $pagina Numero di pagina per la lista visualizzata
   *
   * @Route("/alunni/modifica/{pagina}", name="alunni_modifica",
   *    requirements={"pagina": "\d+"},
   *    defaults={"pagina": 0},
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function modificaAction(Request $request, TranslatorInterface $trans, $pagina): Response {
    // init
    $dati = [];
    $info = [];
    // recupera criteri dalla sessione
    $criteri = array();
    $criteri['nome'] = $this->reqstack->getSession()->get('/APP/ROUTE/alunni_modifica/nome', '');
    $criteri['cognome'] = $this->reqstack->getSession()->get('/APP/ROUTE/alunni_modifica/cognome', '');
    $criteri['classe'] = $this->reqstack->getSession()->get('/APP/ROUTE/alunni_modifica/classe');
    $classe = ($criteri['classe'] > 0 ? $this->em->getRepository('App\Entity\Classe')->find($criteri['classe']) : null);
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $this->reqstack->getSession()->get('/APP/ROUTE/alunni_modifica/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/alunni_modifica/pagina', $pagina);
    }
    // form di ricerca
    $lista_classi = $this->em->getRepository('App\Entity\Classe')->findBy([], ['anno' =>'ASC', 'sezione' =>'ASC']);
    $lista_classi[] = -1;
    $label_classe = $trans->trans('label.nessuna_classe');
    $form = $this->createForm(RicercaType::class, null, ['formMode' => 'docenti-alunni',
      'dati' => [$criteri['cognome'], $criteri['nome'], $classe, $lista_classi, $label_classe]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $criteri['nome'] = trim($form->get('nome')->getData());
      $criteri['cognome'] = trim($form->get('cognome')->getData());
      $criteri['classe'] = (is_object($form->get('classe')->getData()) ? $form->get('classe')->getData()->getId() :
        intval($form->get('classe')->getData()));
      $pagina = 1;
      $this->reqstack->getSession()->set('/APP/ROUTE/alunni_modifica/nome', $criteri['nome']);
      $this->reqstack->getSession()->set('/APP/ROUTE/alunni_modifica/cognome', $criteri['cognome']);
      $this->reqstack->getSession()->set('/APP/ROUTE/alunni_modifica/classe', $criteri['classe']);
      $this->reqstack->getSession()->set('/APP/ROUTE/alunni_modifica/pagina', $pagina);
    }
    // lista alunni
    $dati = $this->em->getRepository('App\Entity\Alunno')->cerca($criteri, $pagina);
    $info['pagina'] = $pagina;
    // aggiunge dati dei genitori
    $dati['genitori'] = $this->em->getRepository('App\Entity\Genitore')->datiGenitoriPaginator($dati['lista']);
    // mostra la pagina di risposta
    return $this->renderHtml('alunni', 'modifica', $dati, $info, [$form->createView()]);
  }

  /**
   * Abilitazione o disabilitazione degli alunni
   *
   * @param int $id ID dell'utente
   * @param boolean $abilita Vero per abilitare, falso per disabilitare
   *
   * @return Response Pagina di risposta
   *
   * @Route("/alunni/abilita/{id}/{abilita}", name="alunni_abilita",
   *    requirements={"id": "\d+", "abilita": "0|1"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function abilitaAction($id, $abilita): Response {
    // controllo alunno
    $alunno = $this->em->getRepository('App\Entity\Alunno')->find($id);
    if (!$alunno) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // recupera genitori (anche più di uno)
    $genitori = $this->em->getRepository('App\Entity\Genitore')->findBy(['alunno' => $alunno]);
    // abilita o disabilita
    $alunno->setAbilitato($abilita == 1);
    foreach ($genitori as $gen) {
      $gen->setAbilitato($abilita == 1);
    }
    // provisioning
    $provisioning = (new Provisioning())
      ->setUtente($alunno)
      ->setFunzione('sospendeUtente')
      ->setDati(['sospeso' => !$abilita]);
    $this->em->persist($provisioning);
    // memorizza modifiche
    $this->em->flush();
    // messaggio
    $this->addFlash('success', 'message.update_ok');
    // redirezione
    return $this->redirectToRoute('alunni_modifica');
  }

  /**
   * Modifica i dati di un alunno e dei genitori
   *
   * @param Request $request Pagina richiesta
   * @param int $id ID dell'utente
   *
   * @return Response Pagina di risposta
   *
   * @Route("/alunni/modifica/edit/{id}", name="alunni_modifica_edit",
   *    requirements={"id": "\d+"},
   *    defaults={"id": "0"},
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function modificaEditAction(Request $request, $id): Response {
    // controlla azione
    if ($id > 0) {
      // azione edit
      $alunno = $this->em->getRepository('App\Entity\Alunno')->find($id);
      if (!$alunno) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $classe_old = $alunno->getClasse() ? $alunno->getClasse()->getId() : null;
      $alunno_old = array('cognome' => $alunno->getCognome(), 'nome' => $alunno->getNome(),
        'sesso' => $alunno->getSesso());
      // legge genitori nell'ordine corretto
      $username = substr($alunno->getUsername(), 0, -2).'f'.substr($alunno->getUsername(), -1);
      if ($alunno->getGenitori()[0]->getUsername() == $username) {
        $genitore1 = $alunno->getGenitori()[0];
        $genitore2 = isset($alunno->getGenitori()[1]) ? $alunno->getGenitori()[1] : null;
      } else {
        $genitore1 = $alunno->getGenitori()[1];
        $genitore2 = $alunno->getGenitori()[0];
      }
    } else {
      // azione add
      $alunno = (new Alunno())
        ->setAbilitato(true)
        ->setPassword('NOPASSWORD');
      $this->em->persist($alunno);
      $classe_old = null;
      // aggiunge genitori
      $genitore1 = (new Genitore())
        ->setAbilitato(true)
        ->setAlunno($alunno)
        ->setSesso('M')
        ->setPassword('NOPASSWORD');
      $this->em->persist($genitore1);
      $genitore2 = (new Genitore())
        ->setAbilitato(true)
        ->setAlunno($alunno)
        ->setSesso('F')
        ->setPassword('NOPASSWORD');
      $this->em->persist($genitore2);
    }
    // form
    $form = $this->createForm(AlunnoGenitoreType::class, $alunno, [
      'returnUrl' => $this->generateUrl('alunni_modifica'),'data' => [$alunno, $genitore1, $genitore2]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // controlla numeri di telefono genitore1
      $telefono = array();
      foreach ($genitore1->getNumeriTelefono() as $tel) {
        $tel = preg_replace('/[^+\d]/', '', $tel);
        $tel = (substr($tel, 0, 3) == '+39') ? substr($tel, 3) : $tel;
        if ($tel != '' && $tel != str_repeat('0', strlen($tel))) {
          $telefono[] = $tel;
        }
      }
      $genitore1->setNumeriTelefono($telefono);
      // controlla numeri di telefono genitore2
      $telefono = array();
      foreach ($genitore2->getNumeriTelefono() as $tel) {
        $tel = preg_replace('/[^+\d]/', '', $tel);
        $tel = (substr($tel, 0, 3) == '+39') ? substr($tel, 3) : $tel;
        if ($tel != '' && $tel != str_repeat('0', strlen($tel))) {
          $telefono[] = $tel;
        }
      }
      $genitore2->setNumeriTelefono($telefono);
      // provisioning
      if (!$id) {
        // crea alunno
        $provisioning = (new Provisioning())
          ->setUtente($alunno)
          ->setFunzione('creaUtente')
          ->setDati(['password' => 'NOPASSWORD']);
        $this->em->persist($provisioning);
      } elseif ($alunno->getCognome() != $alunno_old['cognome'] || $alunno->getNome() != $alunno_old['nome'] ||
                $alunno->getSesso() != $alunno_old['sesso']) {
        // modifica dati alunno
        $provisioning = (new Provisioning())
          ->setUtente($alunno)
          ->setFunzione('modificaUtente')
          ->setDati([]);
        $this->em->persist($provisioning);
      }
      if (!$classe_old && $alunno->getClasse()) {
        // aggiunge alunno a classe
        $provisioning = (new Provisioning())
          ->setUtente($alunno)
          ->setFunzione('aggiungeAlunnoClasse')
          ->setDati(['classe' => $alunno->getClasse()->getId()]);
        $this->em->persist($provisioning);
      } elseif ($classe_old && !$alunno->getClasse()) {
        // toglie alunno da classe
        $provisioning = (new Provisioning())
          ->setUtente($alunno)
          ->setFunzione('rimuoveAlunnoClasse')
          ->setDati(['classe' => $classe_old]);
        $this->em->persist($provisioning);
      } elseif ($alunno->getClasse() && $classe_old != $alunno->getClasse()->getId()) {
        // cambia classe ad alunno
        $provisioning = (new Provisioning())
          ->setUtente($alunno)
          ->setFunzione('modificaAlunnoClasse')
          ->setDati(['classe_origine' => $classe_old, 'classe_destinazione' => $alunno->getClasse()->getId()]);
        $this->em->persist($provisioning);
      }
      // memorizza modifiche
      $this->em->flush();
      // messaggio
      $this->addFlash('success', 'message.update_ok');
      // redirezione
      return $this->redirectToRoute('alunni_modifica');
    }
    // mostra la pagina di risposta
    return $this->renderHtml('alunni', 'modifica_edit', [], [], [$form->createView(), 'message.modifica_alunno']);
  }

  /**
   * Generazione e invio della password agli alunni o ai genitori
   *
   * @param Request $request Pagina richiesta
   * @param UserPasswordHasherInterface $hasher Gestore della codifica delle password
   * @param PdfManager $pdf Gestore dei documenti PDF
   * @param StaffUtil $staff Funzioni disponibili allo staff
   * @param MailerInterface $mailer Gestore della spedizione delle email
   * @param LoggerInterface $logger Gestore dei log su file
   * @param LogHandler $dblogger Gestore dei log su database
   * @param string $tipo Tipo di creazione del documento [E=email, P=Pdf]
   * @param string $username Username del genitore o alunno di cui si vuole cambiare la password
   *
   * @return Response Pagina di risposta
   *
   * @Route("/alunni/password/{tipo}/{username}", name="alunni_password",
   *    requirements={"tipo": "E|P"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function passwordAction(Request $request, UserPasswordHasherInterface $hasher,
                                 PdfManager $pdf, StaffUtil $staff, MailerInterface $mailer,
                                 LoggerInterface $logger, LogHandler $dblogger, $tipo,
                                 $username=null): Response {
    // controlla alunno
    $utente = $this->em->getRepository('App\Entity\Alunno')->findOneByUsername($username);
    if (!$utente) {
      // controlla genitore
      $utente = $this->em->getRepository('App\Entity\Genitore')->findOneByUsername($username);
      if (!$utente) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
    }
    // crea password
    $password = $staff->creaPassword(8);
    $utente->setPasswordNonCifrata($password);
    $pswd = $hasher->hashPassword($utente, $utente->getPasswordNonCifrata());
    $utente->setPassword($pswd);
    // provisioning
    if ($utente instanceOf Alunno) {
      $provisioning = (new Provisioning())
        ->setUtente($utente)
        ->setFunzione('passwordUtente')
        ->setDati(['password' => $utente->getPasswordNonCifrata()]);
      $this->em->persist($provisioning);
    }
    // memorizza su db
    $this->em->flush();
    // aggiunge log
    $dblogger->logAzione('SICUREZZA', 'Generazione Password', array(
      'Username' => $utente->getUsername(),
      'Ruolo' => $utente->getRoles()[0],
      'ID' => $utente->getId()));
    // crea documento PDF
    $pdf->configure($this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
      'Credenziali di accesso al Registro Elettronico');
    // contenuto in formato HTML
    if ($utente instanceOf Alunno) {
      $html = $this->renderView('pdf/credenziali_profilo_alunni.html.twig', array(
        'alunno' => $utente,
        'sesso' => ($utente->getSesso() == 'M' ? 'o' : 'a'),
        'username' => $utente->getUsername(),
        'password' => $password));
    } else {
      $html = $this->renderView('pdf/credenziali_profilo_genitori.html.twig', array(
        'alunno' => $utente->getAlunno(),
        'genitore' => $utente,
        'sesso' => ($utente->getAlunno()->getSesso() == 'M' ? 'o' : 'a'),
        'username' => $utente->getUsername(),
        'password' => $password));
    }
    $pdf->createFromHtml($html);
    $doc = $pdf->getHandler()->Output('', 'S');
    if ($tipo == 'E') {
      // invia password per email
      $message = (new Email())
        ->from(new Address($this->reqstack->getSession()->get('/CONFIG/ISTITUTO/email_notifiche'), $this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione_breve')))
        ->to($utente->getEmail())
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
        $logger->error('Errore di spedizione email delle credenziali alunno/genitore.', array(
          'username' => $utente->getUsername(),
          'email' => $utente->getEmail(),
          'ip' => $request->getClientIp(),
          'errore' => $err->getMessage()));
        $this->addFlash('danger', 'exception.errore_invio_credenziali');
      }
      // redirezione
      return $this->redirectToRoute('alunni_modifica');
    } else {
      // crea pdf e lo scarica
      $nomefile = 'credenziali-registro.pdf';
      $disposition = HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $nomefile);
      $response = new Response($doc);
      $response->headers->set('Content-Disposition', $disposition);
      return $response;
    }
  }

  /**
   * Gestione cambio classe
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param int $pagina Numero di pagina per la lista visualizzata
   *
   * @Route("/alunni/classe/{pagina}", name="alunni_classe",
   *    requirements={"pagina": "\d+"},
   *    defaults={"pagina": 0},
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function classeAction(Request $request, TranslatorInterface $trans, $pagina): Response {
    // init
    $dati = [];
    $info = [];
    // recupera criteri dalla sessione
    $criteri = array();
    $criteri['nome'] = $this->reqstack->getSession()->get('/APP/ROUTE/alunni_classe/nome', '');
    $criteri['cognome'] = $this->reqstack->getSession()->get('/APP/ROUTE/alunni_classe/cognome', '');
    $criteri['classe'] = $this->reqstack->getSession()->get('/APP/ROUTE/alunni_classe/classe');
    $classe = ($criteri['classe'] > 0 ? $this->em->getRepository('App\Entity\Classe')->find($criteri['classe']) : null);
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $this->reqstack->getSession()->get('/APP/ROUTE/alunni_classe/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/alunni_classe/pagina', $pagina);
    }
    // form di ricerca
    $lista_classi = $this->em->getRepository('App\Entity\Classe')->findBy([], ['anno' =>'ASC', 'sezione' =>'ASC']);
    $lista_classi[] = -1;
    $label_classe = $trans->trans('label.nessuna_classe');
    $form = $this->createForm(RicercaType::class, null, ['formMode' => 'docenti-alunni',
      'dati' => [$criteri['cognome'], $criteri['nome'], $classe, $lista_classi, $label_classe]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $criteri['nome'] = trim($form->get('nome')->getData());
      $criteri['cognome'] = trim($form->get('cognome')->getData());
      $criteri['classe'] = (is_object($form->get('classe')->getData()) ? $form->get('classe')->getData()->getId() :
        intval($form->get('classe')->getData()));
      $pagina = 1;
      $this->reqstack->getSession()->set('/APP/ROUTE/alunni_classe/nome', $criteri['nome']);
      $this->reqstack->getSession()->set('/APP/ROUTE/alunni_classe/cognome', $criteri['cognome']);
      $this->reqstack->getSession()->set('/APP/ROUTE/alunni_classe/classe', $criteri['classe']);
      $this->reqstack->getSession()->set('/APP/ROUTE/alunni_classe/pagina', $pagina);
    }
    // lista cambi classe
    $dati = $this->em->getRepository('App\Entity\CambioClasse')->cerca($criteri, $pagina);
    $info['pagina'] = $pagina;
    // mostra la pagina di risposta
    return $this->renderHtml('alunni', 'classe', $dati, $info, [$form->createView()]);
  }

  /**
   * Modifica un cambio di classe di un alunno
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param int $id ID del cambio classe
   * @param string $tipo Tipo di cambio classe [I=inserito,T=trasferito,S=sezione,A=altro]
   *
   * @return Response Pagina di risposta
   *
   * @Route("/alunni/classe/edit/{id}/{tipo}", name="alunni_classe_edit",
   *    requirements={"id": "\d+", "tipo": "I|T|S|A"},
   *    defaults={"id": "0", "tipo": "A"},
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function classeEditAction(Request $request, TranslatorInterface $trans, $id, $tipo): Response {
    $form_help = 'message.required_fields';
    // controlla azione
    if ($id > 0) {
      // azione edit
      $cambio = $this->em->getRepository('App\Entity\CambioClasse')->find($id);
      if (!$cambio) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
    } else {
      // azione add
      $cambio = new CambioClasse();
      $this->em->persist($cambio);
      // controlla tipo di cambio
      switch ($tipo) {
        case 'I':   // inserimento
          $form_help = 'message.classe_alunno_inserito';
          // dati fittizi temporanei
          $cambio->setFine(new \DateTime());
          break;
        case 'T':   // trasferimento
          $form_help = 'message.classe_alunno_trasferito';
          // dati fittizi temporanei
          $cambio->setInizio(new \DateTime());
          break;
        case 'S':   // cambio sezione
          $form_help = 'message.classe_alunno_sezione';
          // dati fittizi temporanei
          $cambio->setInizio(new \DateTime());
          break;
      }
    }
    // form
    $form = $this->createForm(CambioClasseType::class, $cambio, ['formMode' => $tipo,
      'returnUrl' => $this->generateUrl('alunni_classe')]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // validazione
      $anno_inizio = \DateTime::createFromFormat('Y-m-d', $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_inizio'));
      $anno_fine = \DateTime::createFromFormat('Y-m-d', $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_fine'));
      if ($id == 0) {
        // solo nuovi dati
        $altro = $this->em->getRepository('App\Entity\CambioClasse')->findByAlunno($cambio->getAlunno());
        if (count($altro) > 0) {
          // errore: altro cambio esistente
          $form->addError(new FormError($trans->trans('exception.cambio_classe_esistente')));
        }
        if ($tipo == 'I') {
          // inserimento alunno
          $data = $cambio->getInizio();
          $inizio = (clone $anno_inizio)->modify('first day of this month');
          $fine = (clone $data)->modify('-1 day');
          $note = $trans->trans('message.note_classe_alunno_inserito', ['date' => $data->format('d/m/Y')]);
          if ($cambio->getInizio() < $anno_inizio) {
            // errore sulla data
            $form->get('inizio')->addError(new FormError($trans->trans('exception.classe_inizio_invalido')));
          }
          if ($this->em->getRepository('App\Entity\Valutazione')->numeroValutazioni($cambio->getAlunno(), $inizio, $fine) > 0) {
            // errore valutazioni presenti
            $form->addError(new FormError($trans->trans('exception.classe_valutazioni_presenti')));
          }
          if ($this->em->getRepository('App\Entity\Nota')->numeroNoteIndividuali($cambio->getAlunno(), $inizio, $fine) > 0) {
            // errore note presenti
            $form->addError(new FormError($trans->trans('exception.classe_note_presenti')));
          }
        }
        if ($tipo == 'T') {
          // trasferimento alunno
          $data = $cambio->getFine();
          $classe = $cambio->getAlunno()->getClasse();
          $inizio = $anno_inizio;
          $fine = (clone $data)->modify('-1 day');
          $note = $trans->trans('message.note_classe_alunno_trasferito', ['date' => $data->format('d/m/Y')]);
          if ($cambio->getFine() > $anno_fine) {
            // errore sulla data
            $form->get('fine')->addError(new FormError($trans->trans('exception.classe_fine_invalido')));
          }
          if ($this->em->getRepository('App\Entity\Valutazione')->numeroValutazioni($cambio->getAlunno(), $data, $anno_fine) > 0) {
            // errore valutazioni presenti
            $form->addError(new FormError($trans->trans('exception.classe_valutazioni_presenti')));
          }
          if ($this->em->getRepository('App\Entity\Nota')->numeroNoteIndividuali($cambio->getAlunno(), $data, $anno_fine) > 0) {
            // errore note presenti
            $form->addError(new FormError($trans->trans('exception.classe_note_presenti')));
          }
        }
        if ($tipo == 'S') {
          // cambio sezione alunno
          $data = $cambio->getFine();
          $classe = $cambio->getAlunno()->getClasse();
          $inizio = $anno_inizio;
          $fine = (clone $data)->modify('-1 day');
          $note = $trans->trans('message.note_classe_alunno_sezione', ['date' => $data->format('d/m/Y')]);
          if ($cambio->getFine() > $anno_fine) {
            // errore sulla data
            $form->get('fine')->addError(new FormError($trans->trans('exception.classe_fine_invalido')));
          }
          if ($cambio->getClasse() == $cambio->getAlunno()->getClasse()) {
            // errore sulla classe
            $form->get('classe')->addError(new FormError($trans->trans('exception.classe_non_diversa')));
          }
          if ($this->em->getRepository('App\Entity\Valutazione')->numeroValutazioni($cambio->getAlunno(), $data, $anno_fine, $classe) > 0) {
            // errore valutazioni presenti
            $form->addError(new FormError($trans->trans('exception.classe_valutazioni_presenti')));
          }
          if ($this->em->getRepository('App\Entity\Nota')->numeroNoteIndividuali($cambio->getAlunno(), $data, $anno_fine, $classe) > 0) {
            // errore note presenti
            $form->addError(new FormError($trans->trans('exception.classe_note_presenti')));
          }
        }
      }
      if ($tipo == 'A' && $cambio->getInizio() > $cambio->getFine()) {
        // errore sulla data
        $form->get('inizio')->addError(new FormError($trans->trans('exception.classe_inizio_fine_invalido')));
      }
      // modifica
      if ($form->isValid()) {
        if ($id == 0 && $tipo == 'I') {
          // inserimento alunno
          $cambio
            ->setInizio($inizio)
            ->setFine($fine)
            ->setNote($note);
          if ($form->get('cancella')->getData()) {
            // cancella ore di assenza incongrue
            $this->em->getRepository('App\Entity\Assenza')->elimina($cambio->getAlunno(), $inizio, $fine);
            $this->em->getRepository('App\Entity\Entrata')->elimina($cambio->getAlunno(), $inizio, $fine);
            $this->em->getRepository('App\Entity\Uscita')->elimina($cambio->getAlunno(), $inizio, $fine);
            $this->em->getRepository('App\Entity\AssenzaLezione')->elimina($cambio->getAlunno(), $inizio, $fine);
          }
        } elseif ($id == 0 && $tipo == 'T') {
          // trasferimento alunno
          $cambio
            ->setInizio($inizio)
            ->setFine($fine)
            ->setClasse($classe)
            ->setNote($note);
          $cambio->getAlunno()->setClasse(null);
          // provisioning
          $provisioning = (new Provisioning())
            ->setUtente($cambio->getAlunno())
            ->setFunzione('rimuoveAlunnoClasse')
            ->setDati(['classe' => $classe->getId()]);
          $this->em->persist($provisioning);
          if ($form->get('cancella')->getData()) {
            // cancella ore di assenza incongrue
            $this->em->getRepository('App\Entity\Assenza')->elimina($cambio->getAlunno(), $data, $anno_fine);
            $this->em->getRepository('App\Entity\Entrata')->elimina($cambio->getAlunno(), $data, $anno_fine);
            $this->em->getRepository('App\Entity\Uscita')->elimina($cambio->getAlunno(), $data, $anno_fine);
            $this->em->getRepository('App\Entity\AssenzaLezione')->elimina($cambio->getAlunno(), $data, $anno_fine);
          }
        } elseif ($id == 0 && $tipo == 'S') {
          // cambio sezione alunno
          $cambio->getAlunno()->setClasse($cambio->getClasse());
          $cambio
            ->setInizio($inizio)
            ->setFine($fine)
            ->setClasse($classe)
            ->setNote($note);
          // provisioning
          $provisioning = (new Provisioning())
            ->setUtente($cambio->getAlunno())
            ->setFunzione('modificaAlunnoClasse')
            ->setDati(['classe_origine' => $classe->getId(),
              'classe_destinazione' => $cambio->getAlunno()->getClasse()->getId()]);
          $this->em->persist($provisioning);
          if ($form->get('cancella')->getData()) {
            // cancella ore di assenza incongrue
            $this->em->getRepository('App\Entity\Assenza')->elimina($cambio->getAlunno(), $data, $anno_fine);
            $this->em->getRepository('App\Entity\Entrata')->elimina($cambio->getAlunno(), $data, $anno_fine);
            $this->em->getRepository('App\Entity\Uscita')->elimina($cambio->getAlunno(), $data, $anno_fine);
            $this->em->getRepository('App\Entity\AssenzaLezione')->elimina($cambio->getAlunno(), $data, $anno_fine);
          }
        }
        // memorizza modifiche
        $this->em->flush();
        // messaggio
        $this->addFlash('success', 'message.update_ok');
        // redirezione
        return $this->redirectToRoute('alunni_classe');
      }
    }
    // mostra la pagina di risposta
    return $this->renderHtml('alunni', 'classe_edit', [], [], [$form->createView(), $form_help]);
  }

  /**
   * Cancella un cambio di classe di un alunno
   *
   * @param int $id ID del cambio classe
   *
   * @return Response Pagina di risposta
   *
   * @Route("/alunni/classe/delete/{id}", name="alunni_classe_delete",
   *    requirements={"id": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function classeDeleteAction($id): Response {
    $cambio = $this->em->getRepository('App\Entity\CambioClasse')->find($id);
    if (!$cambio) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // elimina il cambio classe
    $this->em->remove($cambio);
    $this->em->flush();
    // messaggio
    $this->addFlash('success', 'message.update_ok');
    // redirezione
    return $this->redirectToRoute('alunni_classe');
  }

  /**
   * Generazione e invio della password agli alunni o ai genitori
   *
   * @param Request $request Pagina richiesta
   * @param UserPasswordHasherInterface $hasher Gestore della codifica delle password
   * @param PdfManager $pdf Gestore dei documenti PDF
   * @param StaffUtil $staff Funzioni disponibili allo staff
   * @param LoggerInterface $logger Gestore dei log su file
   * @param LogHandler $dblogger Gestore dei log su database
   * @param boolean $genitore Vero se si vuole cambiare la password del genitore, falso per la password dell'alunno
   *
   * @return Response Pagina di risposta
   *
   * @Route("/alunni/passwordFiltro/{genitore}", name="alunni_passwordFiltro",
   *    requirements={"genitore": "0|1"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function passwordFiltroAction(Request $request, UserPasswordHasherInterface $hasher,
                                       PdfManager $pdf, StaffUtil $staff, LoggerInterface $logger,
                                       LogHandler $dblogger, $genitore): Response {
    // recupera criteri dalla sessione
    $criteri = array();
    $criteri['nome'] = $this->reqstack->getSession()->get('/APP/ROUTE/alunni_modifica/nome', '');
    $criteri['cognome'] = $this->reqstack->getSession()->get('/APP/ROUTE/alunni_modifica/cognome', '');
    $criteri['classe'] = $this->reqstack->getSession()->get('/APP/ROUTE/alunni_modifica/classe');
    $classe = ($criteri['classe'] > 0 ? $this->em->getRepository('App\Entity\Classe')->find($criteri['classe']) : null);
    $pagina = $this->reqstack->getSession()->get('/APP/ROUTE/alunni_modifica/pagina', 1);
    // recupera dati
    $dati = $this->em->getRepository('App\Entity\Alunno')->cerca($criteri, $pagina);
    $dati['genitori'] = $this->em->getRepository('App\Entity\Genitore')->datiGenitoriPaginator($dati['lista']);

    // crea documento PDF
    $pdf->configure($this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
      'Credenziali di accesso al Registro Elettronico');
    // legge alunni
    foreach ($dati['lista'] as $alu) {
      if ($genitore) {
        // password genitore
        $utenti = $this->em->getRepository('App\Entity\Genitore')->findBy(['alunno' => $alu]);
      } else {
        // password alunno
        $utenti = [$alu];
      }
      foreach ($utenti as $utente) {
        // crea password
        $password = $staff->creaPassword(8);
        $utente->setPasswordNonCifrata($password);
        $pswd = $hasher->hashPassword($utente, $utente->getPasswordNonCifrata());
        $utente->setPassword($pswd);
        // provisioning
        if (!$genitore) {
          $provisioning = (new Provisioning())
            ->setUtente($utente)
            ->setFunzione('passwordUtente')
            ->setDati(['password' => $utente->getPasswordNonCifrata()]);
          $this->em->persist($provisioning);
        }
        // memorizza su db
        $this->em->flush();
        // log azione
        $dblogger->logAzione('SICUREZZA', 'Generazione Password', array(
          'Username' => $utente->getUsername(),
          'Ruolo' => $utente->getRoles()[0],
          'ID' => $utente->getId()));
        // contenuto in formato HTML
        if ($genitore) {
          $html = $this->renderView('pdf/credenziali_profilo_genitori.html.twig', array(
            'alunno' => $utente->getAlunno(),
            'genitore' => $utente,
            'sesso' => ($utente->getAlunno()->getSesso() == 'M' ? 'o' : 'a'),
            'username' => $utente->getUsername(),
            'password' => $password));
        } else {
          $html = $this->renderView('pdf/credenziali_profilo_alunni.html.twig', array(
            'alunno' => $utente,
            'sesso' => ($utente->getSesso() == 'M' ? 'o' : 'a'),
            'username' => $utente->getUsername(),
            'password' => $password));
        }
        $pdf->createFromHtml($html);
      }
    }
    // crea pdf e lo scarica
    $doc = $pdf->getHandler()->Output('', 'S');
    $nomefile = 'credenziali-registro-'.($genitore ? 'genitori' : 'alunni').'.pdf';
    $disposition = HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $nomefile);
    $response = new Response($doc);
    $response->headers->set('Content-Disposition', $disposition);
    return $response;
  }

}
