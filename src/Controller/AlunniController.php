<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2020 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2020
 */


namespace App\Controller;

use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use App\Util\CsvImporter;
use App\Util\LogHandler;
use App\Util\PdfManager;
use App\Util\StaffUtil;
use App\Form\RicercaType;
use App\Form\AlunnoType;
use App\Form\ImportaCsvType;
use App\Form\CambioClasseType;
use App\Entity\CambioClasse;
use App\Entity\Alunno;
use App\Entity\Genitore;
use App\Entity\Provisioning;


/**
 * AlunniController - gestione alunni e genitori
 */
class AlunniController extends BaseController {

  /**
   * Importa alunni e genitori da file
   *
   * @param Request $request Pagina richiesta
   * @param SessionInterface $session Gestore delle sessioni
   * @param CsvImporter $importer Servizio per l'importazione dei dati da file CSV
   *
   * @return Response Pagina di risposta
   *
   * @Route("/alunni/importa/", name="alunni_importa",
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function importaAction(Request $request, SessionInterface $session, CsvImporter $importer): Response {
    // init
    $dati = [];
    $info = [];
    $var_sessione = '/APP/FILE/alunni_importa';
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
    $form = $this->createForm(ImportaCsvType::class, null, ['formMode' => 'alunni']);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // trova file caricato
      $file = null;
      foreach ($session->get($var_sessione.'/file', []) as $f) {
        $file = new File($this->getParameter('dir_tmp').'/'.$f['temp']);
      }
      // importa file
      $dati = $importer->importaAlunni($file, $form);
      $dati = ($dati == null ? [] : $dati);
      // cancella dati sessione
      $session->remove($var_sessione);
    }
    // visualizza pagina
    return $this->renderHtml('alunni', 'importa', $dati, $info, [$form->createView(),  'message.importa_alunni']);
  }

  /**
   * Gestisce la modifica dei dati dei alunni
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
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
  public function modificaAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                 TranslatorInterface $trans, $pagina): Response {
    // init
    $dati = [];
    $info = [];
    // recupera criteri dalla sessione
    $criteri = array();
    $criteri['nome'] = $session->get('/APP/ROUTE/alunni_modifica/nome', '');
    $criteri['cognome'] = $session->get('/APP/ROUTE/alunni_modifica/cognome', '');
    $criteri['classe'] = $session->get('/APP/ROUTE/alunni_modifica/classe', 0);
    $classe = ($criteri['classe'] > 0 ? $em->getRepository('App:Classe')->find($criteri['classe']) : 0);
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $session->get('/APP/ROUTE/alunni_modifica/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $session->set('/APP/ROUTE/alunni_modifica/pagina', $pagina);
    }
    // form di ricerca
    $lista_classi = $em->getRepository('App:Classe')->findBy([], ['anno' =>'ASC', 'sezione' =>'ASC']);
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
      $session->set('/APP/ROUTE/alunni_modifica/nome', $criteri['nome']);
      $session->set('/APP/ROUTE/alunni_modifica/cognome', $criteri['cognome']);
      $session->set('/APP/ROUTE/alunni_modifica/classe', $criteri['classe']);
      $session->set('/APP/ROUTE/alunni_modifica/pagina', $pagina);
    }
    // lista alunni
    $dati = $em->getRepository('App:Alunno')->cerca($criteri, $pagina);
    $info['pagina'] = $pagina;
    // mostra la pagina di risposta
    return $this->renderHtml('alunni', 'modifica', $dati, $info, [$form->createView()]);
  }

  /**
   * Abilitazione o disabilitazione degli alunni
   *
   * @param EntityManagerInterface $em Gestore delle entità
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
  public function abilitaAction(EntityManagerInterface $em, $id, $abilita): Response {
    // controllo alunno
    $alunno = $em->getRepository('App:Alunno')->find($id);
    if (!$alunno) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // recupera genitori (anche più di uno)
    $genitori = $em->getRepository('App:Genitore')->findBy(['alunno' => $alunno]);
    // abilita o disabilita
    $alunno->setAbilitato($abilita == 1);
    foreach ($genitori as $gen) {
      $gen->setAbilitato($abilita == 1);
    }
    $em->flush();
    // messaggio
    $this->addFlash('success', 'message.update_ok');
    // redirezione
    return $this->redirectToRoute('alunni_modifica');
  }

  /**
   * Modifica i dati di un alunno e dei genitori
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
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
  public function modificaEditAction(Request $request, EntityManagerInterface $em, $id): Response {
    // controlla azione
    if ($id > 0) {
      // azione edit
      $alunno = $em->getRepository('App:Alunno')->find($id);
      if (!$alunno) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $genitori = $em->getRepository('App:Genitore')->findBy(['alunno' => $alunno]);
      $email = $genitori[0]->getEmail();
    } else {
      // azione add
      $alunno = (new Alunno())
        ->setAbilitato(true)
        ->setPassword('NOPASSWORD');
      $em->persist($alunno);
      $genitore = (new Genitore())
        ->setAbilitato(true)
        ->setAlunno($alunno)
        ->setPassword('NOPASSWORD');
      $em->persist($genitore);
      $genitori = [$genitore];
      $email = null;
    }
    // form
    $form = $this->createForm(AlunnoType::class, $alunno, ['returnUrl' => $this->generateUrl('alunni_modifica'),
      'dati' => [$email]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // mantiene ordine nei numeri di telefono
      $numtel = array();
      foreach ($form->get('numeriTelefono')->getData() as $tel) {
        if (trim($tel) != '') {
          $numtel[] = trim($tel);
        }
      }
      $alunno->setNumeriTelefono($numtel);
      // genitori
      foreach ($genitori as $gen) {
        $gen
          ->setNome($alunno->getNome())
          ->setCognome($alunno->getCognome())
          ->setSesso($alunno->getSesso());
      }
      // primo genitore
      $username_pos = strrpos($alunno->getUsername(), '.');
      $username = substr($alunno->getUsername(), 0, $username_pos).'.f'.
        substr($alunno->getUsername(), $username_pos + 2);
      $genitori[0]->setUsername($username);
      $genitori[0]->setEmail($form->get('email_genitore')->getData());
      // provisioning
      $provisioning = (new Provisioning())
        ->setUtente($alunno)
        ->setAzione($id ? 'E' : 'A')
        ->setFunzione($id ? 'ModificaUtente' : 'CreaUtente');
      $em->persist($provisioning);
      $provisioning = (new Provisioning())
        ->setUtente($genitori[0])
        ->setAzione($id ? 'E' : 'A')
        ->setFunzione($id ? 'ModificaUtente' : 'CreaUtente');
      $em->persist($provisioning);
      // memorizza modifiche
      $em->flush();
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
   * @param EntityManagerInterface $em Gestore delle entità
   * @param UserPasswordEncoderInterface $encoder Gestore della codifica delle password
   * @param SessionInterface $session Gestore delle sessioni
   * @param PdfManager $pdf Gestore dei documenti PDF
   * @param StaffUtil $staff Funzioni disponibili allo staff
   * @param \Swift_Mailer $mailer Gestore della spedizione delle email
   * @param LoggerInterface $logger Gestore dei log su file
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $id ID dell'utente
   * @param boolean $genitore Vero se si vuole cambiare la password del genitore, falso per la password dell'alunno
   * @param string $tipo Tipo di creazione del documento [E=email, P=Pdf]
   *
   * @return Response Pagina di risposta
   *
   * @Route("/alunni/password/{id}/{genitore}/{tipo}", name="alunni_password",
   *    requirements={"id": "\d+", "genitore": "0|1", "tipo": "E|P"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function passwordAction(Request $request, EntityManagerInterface $em,
                                 UserPasswordEncoderInterface $encoder, SessionInterface $session,
                                 PdfManager $pdf, StaffUtil $staff, \Swift_Mailer $mailer, LoggerInterface $logger,
                                 LogHandler $dblogger, $id, $genitore, $tipo): Response {
    // controlla alunno
    $alunno = $em->getRepository('App:Alunno')->find($id);
    if (!$alunno) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // crea password
    $password = $staff->creaPassword(8);
    if ($genitore) {
      // password genitore
      $genitori = $em->getRepository('App:Genitore')->findBy(['alunno' => $alunno]);
      $utente = $genitori[0];
    } else {
      // password alunno
      $utente = $alunno;
    }
    $utente->setPasswordNonCifrata($password);
    $pswd = $encoder->encodePassword($utente, $utente->getPasswordNonCifrata());
    $utente->setPassword($pswd);
    // memorizza su db
    $em->flush();
    // log azione e provisioning
    if (!$genitore) {
      $provisioning = (new Provisioning())
        ->setUtente($utente)
        ->setDati(['password' => $utente->getPasswordNonCifrata()])
        ->setAzione('E')
        ->setFunzione('PasswordUtente');
      $em->persist($provisioning);
    }
    // aggiunge log
    $dblogger->write($utente, $request->getClientIp(), 'SICUREZZA', 'Generazione Password', __METHOD__, array(
      'Username esecutore' => $this->getUser()->getUsername(),
      'Ruolo esecutore' => $this->getUser()->getRoles()[0],
      'ID esecutore' => $this->getUser()->getId()));
    // crea documento PDF
    $pdf->configure($session->get('/CONFIG/ISTITUTO/intestazione'),
      'Credenziali di accesso al Registro Elettronico');
    // contenuto in formato HTML
    $html = $this->renderView($genitore ? 'pdf/credenziali_profilo_genitori.html.twig' :
      'pdf/credenziali_profilo_alunni.html.twig', array(
        'alunno' => $alunno,
        'sesso' => ($alunno->getSesso() == 'M' ? 'o' : 'a'),
        'username' => $utente->getUsername(),
        'password' => $password));
    $pdf->createFromHtml($html);
    $doc = $pdf->getHandler()->Output('', 'S');
    if ($tipo == 'E') {
      // invia per email
      $message = (new \Swift_Message())
        ->setSubject($session->get('/CONFIG/ISTITUTO/intestazione_breve')." - Credenziali di accesso al Registro Elettronico")
        ->setFrom([$session->get('/CONFIG/ISTITUTO/email_notifiche') => $session->get('/CONFIG/ISTITUTO/intestazione_breve')])
        ->setTo([$utente->getEmail()])
        ->setBody($this->renderView('email/credenziali.html.twig'), 'text/html')
        ->addPart($this->renderView('email/credenziali.txt.twig'), 'text/plain')
        ->attach(new \Swift_Attachment($doc, 'credenziali_registro.pdf', 'application/pdf'));
      // invia mail
      if (!$mailer->send($message)) {
        // errore di spedizione
        $logger->error('Errore di spedizione email delle credenziali alunno/genitore.', array(
          'username' => $utente->getUsername(),
          'email' => $utente->getEmail(),
          'ip' => $request->getClientIp()));
        $this->addFlash('danger', 'exception.errore_invio_credenziali');
      } else {
        // tutto ok
        $this->addFlash('success', 'message.credenziali_inviate');
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
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
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
  public function classeAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                               TranslatorInterface $trans, $pagina): Response {
    // init
    $dati = [];
    $info = [];
    // recupera criteri dalla sessione
    $criteri = array();
    $criteri['nome'] = $session->get('/APP/ROUTE/alunni_classe/nome', '');
    $criteri['cognome'] = $session->get('/APP/ROUTE/alunni_classe/cognome', '');
    $criteri['classe'] = $session->get('/APP/ROUTE/alunni_classe/classe', 0);
    $classe = ($criteri['classe'] > 0 ? $em->getRepository('App:Classe')->find($criteri['classe']) : 0);
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $session->get('/APP/ROUTE/alunni_classe/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $session->set('/APP/ROUTE/alunni_classe/pagina', $pagina);
    }
    // form di ricerca
    $lista_classi = $em->getRepository('App:Classe')->findBy([], ['anno' =>'ASC', 'sezione' =>'ASC']);
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
      $session->set('/APP/ROUTE/alunni_classe/nome', $criteri['nome']);
      $session->set('/APP/ROUTE/alunni_classe/cognome', $criteri['cognome']);
      $session->set('/APP/ROUTE/alunni_classe/classe', $criteri['classe']);
      $session->set('/APP/ROUTE/alunni_classe/pagina', $pagina);
    }
    // lista cambi classe
    $dati = $em->getRepository('App:CambioClasse')->cerca($criteri, $pagina);
    $info['pagina'] = $pagina;
    // mostra la pagina di risposta
    return $this->renderHtml('alunni', 'classe', $dati, $info, [$form->createView()]);
  }

  /**
   * Modifica un cambio di classe di un alunno
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
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
  public function classeEditAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                   TranslatorInterface $trans, $id, $tipo): Response {
    $form_help = 'message.required_fields';
    // controlla azione
    if ($id > 0) {
      // azione edit
      $cambio = $em->getRepository('App:CambioClasse')->find($id);
      if (!$cambio) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
    } else {
      // azione add
      $cambio = new CambioClasse();
      $em->persist($cambio);
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
      $anno_inizio = \DateTime::createFromFormat('Y-m-d', $session->get('/CONFIG/SCUOLA/anno_inizio'));
      $anno_fine = \DateTime::createFromFormat('Y-m-d', $session->get('/CONFIG/SCUOLA/anno_fine'));
      if ($id == 0) {
        // solo nuovi dati
        $altro = $em->getRepository('App:CambioClasse')->findByAlunno($cambio->getAlunno());
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
          if ($em->getRepository('App:Valutazione')->numeroValutazioni($cambio->getAlunno(), $inizio, $fine) > 0) {
            // errore valutazioni presenti
            $form->addError(new FormError($trans->trans('exception.classe_valutazioni_presenti')));
          }
          if ($em->getRepository('App:Nota')->numeroNoteIndividuali($cambio->getAlunno(), $inizio, $fine) > 0) {
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
          if ($em->getRepository('App:Valutazione')->numeroValutazioni($cambio->getAlunno(), $data, $anno_fine) > 0) {
            // errore valutazioni presenti
            $form->addError(new FormError($trans->trans('exception.classe_valutazioni_presenti')));
          }
          if ($em->getRepository('App:Nota')->numeroNoteIndividuali($cambio->getAlunno(), $data, $anno_fine) > 0) {
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
          if ($em->getRepository('App:Valutazione')->numeroValutazioni($cambio->getAlunno(), $data, $anno_fine, $classe) > 0) {
            // errore valutazioni presenti
            $form->addError(new FormError($trans->trans('exception.classe_valutazioni_presenti')));
          }
          if ($em->getRepository('App:Nota')->numeroNoteIndividuali($cambio->getAlunno(), $data, $anno_fine, $classe) > 0) {
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
            $em->getRepository('App:Assenza')->elimina($cambio->getAlunno(), $inizio, $fine);
            $em->getRepository('App:Entrata')->elimina($cambio->getAlunno(), $inizio, $fine);
            $em->getRepository('App:Uscita')->elimina($cambio->getAlunno(), $inizio, $fine);
            $em->getRepository('App:AssenzaLezione')->elimina($cambio->getAlunno(), $inizio, $fine);
          }
        } elseif ($id == 0 && $tipo == 'T') {
          // trasferimento alunno
          $cambio
            ->setInizio($inizio)
            ->setFine($fine)
            ->setClasse($classe)
            ->setNote($note);
          $cambio->getAlunno()->setClasse(null);
          if ($form->get('cancella')->getData()) {
            // cancella ore di assenza incongrue
            $em->getRepository('App:Assenza')->elimina($cambio->getAlunno(), $data, $anno_fine);
            $em->getRepository('App:Entrata')->elimina($cambio->getAlunno(), $data, $anno_fine);
            $em->getRepository('App:Uscita')->elimina($cambio->getAlunno(), $data, $anno_fine);
            $em->getRepository('App:AssenzaLezione')->elimina($cambio->getAlunno(), $data, $anno_fine);
          }
        } elseif ($id == 0 && $tipo == 'S') {
          // cambio sezione alunno
          $cambio->getAlunno()->setClasse($cambio->getClasse());
          $cambio
            ->setInizio($inizio)
            ->setFine($fine)
            ->setClasse($classe)
            ->setNote($note);
          if ($form->get('cancella')->getData()) {
            // cancella ore di assenza incongrue
            $em->getRepository('App:Assenza')->elimina($cambio->getAlunno(), $data, $anno_fine);
            $em->getRepository('App:Entrata')->elimina($cambio->getAlunno(), $data, $anno_fine);
            $em->getRepository('App:Uscita')->elimina($cambio->getAlunno(), $data, $anno_fine);
            $em->getRepository('App:AssenzaLezione')->elimina($cambio->getAlunno(), $data, $anno_fine);
          }
        }
        // memorizza modifiche
        $em->flush();
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
   * @param EntityManagerInterface $em Gestore delle entità
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
  public function classeDeleteAction(EntityManagerInterface $em, $id): Response {
    $cambio = $em->getRepository('App:CambioClasse')->find($id);
    if (!$cambio) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // elimina il cambio classe
    $em->remove($cambio);
    $em->flush();
    // messaggio
    $this->addFlash('success', 'message.update_ok');
    // redirezione
    return $this->redirectToRoute('alunni_classe');
  }

  /**
   * Generazione e invio della password agli alunni o ai genitori
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param UserPasswordEncoderInterface $encoder Gestore della codifica delle password
   * @param SessionInterface $session Gestore delle sessioni
   * @param PdfManager $pdf Gestore dei documenti PDF
   * @param StaffUtil $staff Funzioni disponibili allo staff
   * @param \Swift_Mailer $mailer Gestore della spedizione delle email
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
  public function passwordFiltroAction(Request $request, EntityManagerInterface $em,
                                       UserPasswordEncoderInterface $encoder, SessionInterface $session,
                                       PdfManager $pdf, StaffUtil $staff, \Swift_Mailer $mailer,
                                       LoggerInterface $logger, LogHandler $dblogger, $genitore): Response {
    // recupera criteri dalla sessione
    $criteri = array();
    $criteri['nome'] = $session->get('/APP/ROUTE/alunni_modifica/nome', '');
    $criteri['cognome'] = $session->get('/APP/ROUTE/alunni_modifica/cognome', '');
    $criteri['classe'] = $session->get('/APP/ROUTE/alunni_modifica/classe', 0);
    $classe = ($criteri['classe'] > 0 ? $em->getRepository('App:Classe')->find($criteri['classe']) : 0);
    $pagina = $session->get('/APP/ROUTE/alunni_modifica/pagina', 1);
    // recupera dati
    $dati = $em->getRepository('App:Alunno')->cerca($criteri, $pagina);
    // crea documento PDF
    $pdf->configure($session->get('/CONFIG/ISTITUTO/intestazione'),
      'Credenziali di accesso al Registro Elettronico');
    // legge alunni
    foreach ($dati['lista'] as $alu) {
      // crea password
      $password = $staff->creaPassword(8);
      if ($genitore) {
        // password genitore
        $genitori = $em->getRepository('App:Genitore')->findBy(['alunno' => $alu['alunno']]);
        $utente = $genitori[0];
      } else {
        // password alunno
        $utente = $alu['alunno'];
      }
      $utente->setPasswordNonCifrata($password);
      $pswd = $encoder->encodePassword($utente, $utente->getPasswordNonCifrata());
      $utente->setPassword($pswd);
      // memorizza su db
      $em->flush();
      // log azione e provisioning
      if (!$genitore) {
        $provisioning = (new Provisioning())
          ->setUtente($utente)
          ->setDati(['password' => $utente->getPasswordNonCifrata()])
          ->setAzione('E')
          ->setFunzione('PasswordUtente');
        $em->persist($provisioning);
      }
      // log azione
      $dblogger->write($utente, $request->getClientIp(), 'SICUREZZA', 'Generazione Password', __METHOD__, array(
        'Username esecutore' => $this->getUser()->getUsername(),
        'Ruolo esecutore' => $this->getUser()->getRoles()[0],
        'ID esecutore' => $this->getUser()->getId()));
      // contenuto in formato HTML
      $html = $this->renderView($genitore ? 'pdf/credenziali_profilo_genitori.html.twig' :
        'pdf/credenziali_profilo_alunni.html.twig', array(
          'alunno' => $alu['alunno'],
          'sesso' => ($alu['alunno']->getSesso() == 'M' ? 'o' : 'a'),
          'username' => $utente->getUsername(),
          'password' => $password));
      $pdf->createFromHtml($html);
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
