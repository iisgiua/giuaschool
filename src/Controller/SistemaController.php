<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Controller;

use App\Entity\Alunno;
use App\Entity\DefinizioneScrutinio;
use App\Entity\Docente;
use App\Entity\Provisioning;
use App\Entity\Scrutinio;
use App\Entity\StoricoEsito;
use App\Entity\StoricoVoto;
use App\Form\ConfigurazioneType;
use App\Form\ModuloType;
use App\Form\UtenteType;
use App\Util\ArchiviazioneUtil;
use App\Util\LogHandler;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;


/**
 * SistemaController - gestione parametri di sistema e funzioni di utlità
 *
 * @author Antonello Dessì
 */
class SistemaController extends BaseController {

  /**
   * Configura la visualizzazione di un banner sulle pagine principali.
   *
   * @param Request $request Pagina richiesta
   *
   * @return Response Pagina di risposta
   *
   * @Route("/sistema/banner/", name="sistema_banner",
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function bannerAction(Request $request): Response {
    // init
    $dati = [];
    $info = [];
    // legge parametri
    $banner_login = $this->em->getRepository('App\Entity\Configurazione')->getParametro('banner_login', '');
    $banner_home = $this->em->getRepository('App\Entity\Configurazione')->getParametro('banner_home', '');
    // form
    $form = $this->createForm(ConfigurazioneType::class, null, ['formMode' => 'banner',
      'dati' => [$banner_login, $banner_home]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // memorizza i parametri
      $this->em->getRepository('App\Entity\Configurazione')->setParametro('banner_login',
        $form->get('banner_login')->getData() ? $form->get('banner_login')->getData() : '');
      $this->em->getRepository('App\Entity\Configurazione')->setParametro('banner_home',
        $form->get('banner_home')->getData() ? $form->get('banner_home')->getData() : '');
    }
    // mostra la pagina di risposta
    return $this->renderHtml('sistema', 'banner', $dati, $info, [$form->createView(), 'message.banner']);
  }

  /**
   * Gestione della modalità manutenzione del registro
   *
   * @param Request $request Pagina richiesta
   *
   * @return Response Pagina di risposta
   *
   * @Route("/sistema/manutenzione/", name="sistema_manutenzione",
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function manutenzioneAction(Request $request): Response {
    // init
    $dati = [];
    $info = [];
    // informazioni passate alla pagina
    $info['logLevel'] = $request->server->get('LOG_LEVEL');
    // legge parametri
    $manutenzione_inizio = $this->em->getRepository('App\Entity\Configurazione')->getParametro('manutenzione_inizio', null);
    $manutenzione_fine = $this->em->getRepository('App\Entity\Configurazione')->getParametro('manutenzione_fine', null);
    if (!$manutenzione_inizio) {
      // non è impostata una manutenzione
      $manutenzione = false;
      $manutenzione_inizio = new \DateTime();
      $manutenzione_inizio->modify('+'.(10 - $manutenzione_inizio->format('i') % 10).' minutes');
      $manutenzione_fine = (clone $manutenzione_inizio)->modify('+30 minutes');
    } else {
      // è già impostata una manutenzione
      $manutenzione = true;
      $manutenzione_inizio = \DateTime::createFromFormat('Y-m-d H:i', $manutenzione_inizio);
      $manutenzione_fine = \DateTime::createFromFormat('Y-m-d H:i', $manutenzione_fine);
    }
    // form
    $form = $this->createForm(ConfigurazioneType::class, null, ['formMode' => 'manutenzione',
      'dati' => [$manutenzione, $manutenzione_inizio, clone $manutenzione_inizio,
        $manutenzione_fine, clone $manutenzione_fine]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // form inviato
      if ($form->get('manutenzione')->getData()) {
        // imposta manutenzione
        $param_inizio = $form->get('data_inizio')->getData().' '.
          $form->get('ora_inizio')->getData();
        $param_fine = $form->get('data_fine')->getData().' '.
          $form->get('ora_fine')->getData();
        if ($param_inizio > $param_fine) {
          // inverte l'ordine
          $temp = $param_inizio;
          $param_inizio = $param_fine;
          $param_fine = $temp;
        }
      } else {
        // cancella manutenzione
        $param_inizio = '';
        $param_fine = '';
      }
      // memorizza i parametri
      $this->em->getRepository('App\Entity\Configurazione')->setParametro('manutenzione_inizio', $param_inizio);
      $this->em->getRepository('App\Entity\Configurazione')->setParametro('manutenzione_fine', $param_fine);
    }
    // mostra la pagina di risposta
    return $this->renderHtml('sistema', 'manutenzione', $dati, $info, [$form->createView(), 'message.manutenzione']);
  }

  /**
   * Configurazione dei parametri dell'applicazione
   *
   * @param Request $request Pagina richiesta
   *
   * @return Response Pagina di risposta
   *
   * @Route("/sistema/parametri/", name="sistema_parametri",
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function parametriAction(Request $request): Response {
    // init
    $dati = [];
    $info = [];
    // legge parametri
    $parametri = $this->em->getRepository('App\Entity\Configurazione')->parametriConfigurazione();
    // form
    $form = $this->createForm(ConfigurazioneType::class, null, ['formMode' => 'parametri',
      'dati' => $parametri]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // memorizza modifiche
      $this->em->flush();
    }
    // mostra la pagina di risposta
    return $this->renderHtml('sistema', 'parametri', $dati, $info, [$form->createView(), 'message.parametri']);
  }

  /**
   * Cambia la password di un utente
   *
   * @param Request $request Pagina richiesta
   * @param UserPasswordHasherInterface $hasher Gestore della codifica delle password
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param ValidatorInterface $validator Gestore della validazione dei dati
   * @param LogHandler $dblogger Gestore dei log su database
   *
   * @return Response Pagina di risposta
   *
   * @Route("/sistema/password/", name="sistema_password",
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function passwordAction(Request $request, UserPasswordHasherInterface $hasher,
                                 TranslatorInterface $trans, ValidatorInterface $validator,
                                 LogHandler $dblogger): Response {
    // init
    $dati = [];
    $info = [];
    // form
    $form = $this->createForm(UtenteType::class, null, ['formMode' => 'password']);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // form inviato
      $username = $form->get('username')->getData();
      $user = $this->em->getRepository('App\Entity\Utente')->findOneByUsername($username);
      if (!$user || !$user->getAbilitato()) {
        // errore, utente non esiste o non abilitato
        $form->get('username')->addError(new FormError($trans->trans('exception.invalid_user')));
      } else {
        // validazione password
        $user->setPasswordNonCifrata($form->get('password')->getData());
        $errors = $validator->validate($user);
        if (count($errors) > 0) {
          // errore sulla password
          $form->get('password')->get('first')->addError(new FormError($errors[0]->getMessage()));
        } else {
          // codifica password
          $password = $hasher->hashPassword($user, $user->getPasswordNonCifrata());
          $user->setPassword($password);
          // provisioning
          if (($user instanceOf Docente) || ($user instanceOf Alunno)) {
            $provisioning = (new Provisioning())
              ->setUtente($user)
              ->setFunzione('passwordUtente')
              ->setDati(['password' => $user->getPasswordNonCifrata()]);
            $this->em->persist($provisioning);
          }
          // memorizza password
          $this->em->flush();
          // log azione
          $dblogger->logAzione('SICUREZZA', 'Cambio Password', array(
            'Username' => $user->getUsername(),
            'Ruolo' => $user->getRoles()[0],
            'ID' => $user->getId()
            ));
        }
      }
    }
    // mostra la pagina di risposta
    return $this->renderHtml('sistema', 'password', $dati, $info, [$form->createView(), 'message.password']);
  }

  /**
   * Impersona un altro utente
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param LogHandler $dblogger Gestore dei log su database
   *
   * @return Response Pagina di risposta
   *
   * @Route("/sistema/alias/", name="sistema_alias",
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function aliasAction(Request $request, TranslatorInterface $trans, LogHandler $dblogger): Response {
    // init
    $dati = [];
    $info = [];
    // form
    $form = $this->createForm(UtenteType::class, null, ['formMode' => 'alias']);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // form inviato
      $username = $form->get('username')->getData();
      $user = $this->em->getRepository('App\Entity\Utente')->findOneByUsername($username);
      if (!$user || !$user->getAbilitato()) {
        // errore, utente non esiste o non abilitato
        $form->get('username')->addError(new FormError($trans->trans('exception.invalid_user')));
      } else {
        // memorizza dati in sessione
        $this->reqstack->getSession()->set('/APP/UTENTE/tipo_accesso_reale', $this->reqstack->getSession()->get('/APP/UTENTE/tipo_accesso'));
        $this->reqstack->getSession()->set('/APP/UTENTE/ultimo_accesso_reale', $this->reqstack->getSession()->get('/APP/UTENTE/ultimo_accesso'));
        $this->reqstack->getSession()->set('/APP/UTENTE/username_reale', $this->getUser()->getUsername());
        $this->reqstack->getSession()->set('/APP/UTENTE/ruolo_reale', $this->getUser()->getRoles()[0]);
        $this->reqstack->getSession()->set('/APP/UTENTE/id_reale', $this->getUser()->getId());
        $this->reqstack->getSession()->set('/APP/UTENTE/ultimo_accesso',
          ($user->getUltimoAccesso() ? $user->getUltimoAccesso()->format('d/m/Y H:i:s') : null));
        $this->reqstack->getSession()->set('/APP/UTENTE/tipo_accesso', 'alias');
        // log azione
        $dblogger->logAzione('ACCESSO', 'Alias', array(
          'Username' => $user->getUsername(),
          'Ruolo' => $user->getRoles()[0],
          ));
        // impersona l'alias e fa il redirect alla home
        return $this->redirectToRoute('login_home', array('reload' => 'yes', '_alias' => $username));
      }
    }
    // mostra la pagina di risposta
    return $this->renderHtml('sistema', 'alias', $dati, $info, [$form->createView(), 'message.alias']);
  }

  /**
   * Disconnette l'alias in uso e ritorna all'utente iniziale
   *
   * @param Request $request Pagina richiesta
   * @param LogHandler $dblogger Gestore dei log su database
   *
   * @return Response Pagina di risposta
   *
   * @Route("/sistema/alias/exit", name="sistema_alias_exit",
   *    methods={"GET"})
   */
  public function aliasExitAction(Request $request, LogHandler $dblogger): Response  {
    // log azione
    $dblogger->logAzione('ACCESSO', 'Alias Exit', array(
      'Username' => $this->getUser()->getUsername(),
      'Ruolo' => $this->getUser()->getRoles()[0],
      'Username reale' => $this->reqstack->getSession()->get('/APP/UTENTE/username_reale'),
      'Ruolo reale' => $this->reqstack->getSession()->get('/APP/UTENTE/ruolo_reale'),
      'ID reale' => $this->reqstack->getSession()->get('/APP/UTENTE/id_reale')
      ));
    // ricarica dati in sessione
    $this->reqstack->getSession()->set('/APP/UTENTE/ultimo_accesso', $this->reqstack->getSession()->get('/APP/UTENTE/ultimo_accesso_reale'));
    $this->reqstack->getSession()->set('/APP/UTENTE/tipo_accesso', $this->reqstack->getSession()->get('/APP/UTENTE/tipo_accesso_reale'));
    $this->reqstack->getSession()->remove('/APP/UTENTE/lista_profili');
    $this->reqstack->getSession()->remove('/APP/UTENTE/profilo_usato');
    $this->reqstack->getSession()->remove('/APP/UTENTE/tipo_accesso_reale');
    $this->reqstack->getSession()->remove('/APP/UTENTE/ultimo_accesso_reale');
    $this->reqstack->getSession()->remove('/APP/UTENTE/username_reale');
    $this->reqstack->getSession()->remove('/APP/UTENTE/ruolo_reale');
    $this->reqstack->getSession()->remove('/APP/UTENTE/id_reale');
    // disconnette l'alias in uso e redirect alla home
    return $this->redirectToRoute('login_home', array('reload' => 'yes', '_alias' => '_exit'));
  }

  /**
   * Effettua il passaggio al nuovo A.S.
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param KernelInterface $kernel Gestore delle funzionalità http del kernel
   * @param int $step Passo della procedura
   *
   * @return Response Pagina di risposta
   *
   * @Route("/sistema/nuovo/{step}", name="sistema_nuovo",
   *    requirements={"step": "\d+"},
   *    defaults={"step": "0"},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function nuovoAction(Request $request, TranslatorInterface $trans, KernelInterface $kernel,
                              $step): Response {
    // init
    $dati = [];
    $info = [];
    $info['nuovoAnno'] = (int) (new \DateTime())->format('Y');
    $info['vecchioAnno'] = $info['nuovoAnno'] - 1;
    // form
    $form = $this->createForm(ModuloType::class, null, ['formMode' => 'nuovo', 'step' => $step,
      'actionUrl' => $this->generateUrl('sistema_nuovo', ['step' => $step + 1])]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      $fs = new Filesystem();
      $finder = new Finder();
      $path = $this->getParameter('kernel.project_dir').'/FILES';
      // assicura che lo script non sia interrotto
      ini_set('max_execution_time', 0);
      switch($step) {
        case 1: // pulizia file
          // directory da svuotare
          $finder->files()->in($path.'/tmp')->in($path.'/upload/documenti')->depth('== 0')->notName('.gitkeep');;
          $fs->remove($finder);
          $finder = new Finder();
          $finder->files()->in($path.'/upload/avvisi')->depth('== 0')->notName('.gitkeep')
            ->date('< '.$info['nuovoAnno'].'-09-01');
          // sposta circolari in directory dell'anno
          $fs->mkdir($path.'/upload/circolari/'.$info['vecchioAnno'], 0770);
          $finder = new Finder();
          $finder->files()->in($path.'/upload/circolari')->depth('== 0')->notName('.gitkeep');
          foreach ($finder as $file) {
            $fs->rename($file, $path.'/upload/circolari/'.$info['vecchioAnno'].'/'.$file->getFilename());
          }
          // sposta documenti BES
          $fs->mkdir($path.'/upload/documenti/riservati', 0770);
          $finder = new Finder();
          $finder->directories()->in($path.'/archivio/classi')->name('riservato');
          foreach ($finder as $dir) {
            $finder2 = new Finder();
            $finder2->files()->in($dir->getPathname());
            foreach ($finder2 as $file) {
              $fs->rename($file, $path.'/upload/documenti/riservati/'.$file->getFilename());
            }
          }
          // sposta documenti scrutini
          $fs->remove([$path.'/archivio/scrutini/storico']);
          $fs->mkdir($path.'/archivio/scrutini/storico', 0770);
          $finder = new Finder();
          $finder->directories()->in($path.'/archivio/scrutini')->depth('== 0')
            ->name(['finale', 'giudizio-sospeso', 'rinviato']);
          foreach ($finder as $dir) {
            $finder2 = new Finder();
            $finder2->files()->in($dir->getPathname())->depth('== 1')
              ->name(['*-riepilogo-voti.pdf', '*-verbale.pdf']);
            foreach ($finder2 as $file) {
              $classe = $file->getPathInfo()->getFilename();
              $fs->mkdir($path.'/archivio/scrutini/storico/'.$classe, 0770);
              $fs->rename($file, $path.'/archivio/scrutini/storico/'.$classe.'/'.$file->getFilename());
            }
          }
          // elimina directory di archiviazione
          $fs->remove([$path.'/archivio/circolari', $path.'/archivio/classi', $path.'/archivio/registri']);
          $fs->appendToFile($path.'/archivio/circolari/.gitkeep', '');
          $fs->appendToFile($path.'/archivio/classi/.gitkeep', '');
          $fs->appendToFile($path.'/archivio/registri/.gitkeep', '');
          $finder = new Finder();
          $finder->directories()->in($path.'/archivio/scrutini')->depth('== 0')->exclude('storico');
          foreach ($finder as $dir) {
            $fs->remove([$dir->getPathname()]);
          }
          $this->addFlash('success', 'message.tutte_operazioni_ok');
          break;
        case 2: // pulizia tabelle
          // cancella tabelle
          $connection = $this->em->getConnection();
          $sqlCommands = [
            "SET FOREIGN_KEY_CHECKS = 0;",
            "TRUNCATE gs_annotazione;",
            "TRUNCATE gs_assenza;",
            "TRUNCATE gs_assenza_lezione;",
            "TRUNCATE gs_avviso_classe;",
            "TRUNCATE gs_avviso_sede;",
            "TRUNCATE gs_cambio_classe;",
            "TRUNCATE gs_circolare_classe;",
            "TRUNCATE gs_circolare_sede;",
            "TRUNCATE gs_colloquio;",
            "TRUNCATE gs_definizione_consiglio;",
            "TRUNCATE gs_deroga_assenza;",
            "TRUNCATE gs_entrata;",
            "TRUNCATE gs_festivita;",
            "TRUNCATE gs_firma;",
            "TRUNCATE gs_firma_circolare;",
            "TRUNCATE gs_lezione;",
            "TRUNCATE gs_log;",
            "TRUNCATE gs_nota;",
            "TRUNCATE gs_nota_alunno;",
            "TRUNCATE gs_orario;",
            "TRUNCATE gs_orario_docente;",
            "TRUNCATE gs_osservazione;",
            "TRUNCATE gs_proposta_voto;",
            "TRUNCATE gs_provisioning;",
            "TRUNCATE gs_richiesta_colloquio;",
            "TRUNCATE gs_scansione_oraria;",
            "TRUNCATE gs_spid;",
            "TRUNCATE gs_storico_esito;",
            "TRUNCATE gs_storico_voto;",
            "TRUNCATE gs_uscita;",
            "TRUNCATE gs_valutazione;",
            "SET FOREIGN_KEY_CHECKS = 1;"
          ];
          foreach ($sqlCommands as $sql) {
            $connection->prepare($sql)->execute();
          }
          // elimina destinatari per vecchie circolari
          $sql = $this->em->getRepository('App\Entity\Circolare')->createQueryBuilder('c')
            ->select('c.id')
            ->where('c.anno=:anno')
            ->getDql();
          $this->em->getRepository('App\Entity\CircolareUtente')->createQueryBuilder('cu')
            ->delete()
            ->where('cu.circolare NOT IN ('.$sql.')')
            ->setParameters(['anno' => $info['nuovoAnno']])
            ->getQuery()
            ->getResult();
          $sql = $this->em->getRepository('App\Entity\Utente')->createQueryBuilder('u')
            ->select('u.id')
            ->where('u.abilitato=:no')
            ->getDql();
          $this->em->getRepository('App\Entity\CircolareUtente')->createQueryBuilder('cu')
            ->delete()
            ->where('cu.utente IN ('.$sql.')')
            ->setParameters(['no' => 0])
            ->getQuery()
            ->getResult();
          // conserva avvisi dal 01/09
          $sql = $this->em->getRepository('App\Entity\Avviso')->createQueryBuilder('a')
            ->select('a.id')
            ->where('a.data>=:data')
            ->getDql();
          $this->em->getRepository('App\Entity\AvvisoUtente')->createQueryBuilder('au')
            ->delete()
            ->where('au.avviso NOT IN ('.$sql.')')
            ->setParameters(['data' => $info['nuovoAnno'].'-09-01'])
            ->getQuery()
            ->getResult();
          $sql = $this->em->getRepository('App\Entity\Utente')->createQueryBuilder('u')
            ->select('u.id')
            ->where('u.abilitato=:no')
            ->getDql();
          $this->em->getRepository('App\Entity\AvvisoUtente')->createQueryBuilder('au')
            ->delete()
            ->where('au.utente IN ('.$sql.')')
            ->setParameters(['no' => 0])
            ->getQuery()
            ->getResult();
          $this->em->getRepository('App\Entity\Avviso')->createQueryBuilder('a')
            ->delete()
            ->where('a.data<:data')
            ->setParameters(['data' => $info['nuovoAnno'].'-09-01'])
            ->getQuery()
            ->getResult();
          // pulisce classi da coordinatori e segretari
          $this->em->getRepository('App\Entity\Classe')->createQueryBuilder('c')
            ->update()
            ->set('c.coordinatore', ':nessuno')
            ->set('c.segretario', ':nessuno')
            ->setParameters(['nessuno' => null])
            ->getQuery()
            ->getResult();
          $this->addFlash('success', 'message.tutte_operazioni_ok');
          break;
        case 3: // gestione tabelle particolari
          // gestione circolari
          $this->em->getRepository('App\Entity\Circolare')->createQueryBuilder('c')
            ->update()
            ->set('c.documento', "CONCAT(:anno,'/',c.documento)")
            ->where('c.anno=:anno and c.pubblicata=:si')
            ->setParameters(['anno' => $info['vecchioAnno'], 'si' => 1])
            ->getQuery()
            ->getResult();
          $circolari = $this->em->getRepository('App\Entity\Circolare')->createQueryBuilder('c')
            ->where('c.anno=:anno and c.pubblicata=:si')
            ->setParameters(['anno' => $info['vecchioAnno'], 'si' => 1])
            ->getQuery()
            ->getResult();
          foreach ($circolari as $circolare) {
            $allegati = $circolare->getAllegati();
            if (!empty($allegati)) {
              $nuoviAllegati = [];
              foreach ($allegati as $allegato) {
                $nuoviAllegati[] = $info['vecchioAnno'].'/'.$allegato;
              }
              $circolare->setAllegati($nuoviAllegati);
            }
          }
          $this->em->flush();
          $this->em->clear();
          // gestione documenti BES
          $documenti = $this->em->getRepository('App\Entity\Documento')->createQueryBuilder('d')
            ->where('d.tipo IN (:tipi)')
            ->setParameters(['tipi' => ['B', 'D', 'H']])
            ->getQuery()
            ->getResult();
          // crea report temporaneo per reinstallazione documenti BES
          $fh = fopen($path.'/upload/documenti/riservati.csv', 'w');
          $header = ['alunno', 'tipo', 'cifrato', 'titolo', 'nome', 'estensione', 'dimensione', 'file'];
          fputcsv($fh, $header);
          foreach ($documenti as $documento) {
            $allegato = $documento->getAllegati()[0];
            $record = [
              $documento->getAlunno()->getId(),
              $documento->getTipo(),
              $documento->getCifrato(),
              $allegato->getTitolo(),
              $allegato->getNome(),
              $allegato->getEstensione(),
              $allegato->getDimensione(),
              $allegato->getFile()];
            fputcsv($fh, $record);
          }
          fclose($fh);
          $connection = $this->em->getConnection();
          $sqlCommands = [
            "SET FOREIGN_KEY_CHECKS = 0;",
            "TRUNCATE gs_documento;",
            "TRUNCATE gs_documento_file;",
            "TRUNCATE gs_file;",
            "TRUNCATE gs_lista_destinatari;",
            "TRUNCATE gs_lista_destinatari_classe;",
            "TRUNCATE gs_lista_destinatari_sede;",
            "TRUNCATE gs_lista_destinatari_utente;",
            "SET FOREIGN_KEY_CHECKS = 1;"];
          foreach ($sqlCommands as $sql) {
            $connection->prepare($sql)->execute();
          }
          // rimozione utenti disabilitati
          $this->em->getRepository('App\Entity\Cattedra')->createQueryBuilder('c')
            ->update()
            ->set('c.alunno', ':nullo')
            ->setParameters(['nullo' => NULL])
            ->getQuery()
            ->getResult();
          $subquery = $this->em->getRepository('App\Entity\Docente')->createQueryBuilder('d')
              ->select('d.id')
              ->where('d.abilitato=:no')
              ->getDql();
          $this->em->getRepository('App\Entity\Cattedra')->createQueryBuilder('c')
            ->delete()
            ->where('c.docente IN ('.$subquery.')')
            ->setParameters(['no' => 0])
            ->getQuery()
            ->getResult();
          $this->em->getRepository('App\Entity\Docente')->createQueryBuilder('d')
            ->delete()
            ->where('d.abilitato=:no')
            ->setParameters(['no' => 0])
            ->getQuery()
            ->getResult();
          $this->em->getRepository('App\Entity\Ata')->createQueryBuilder('a')
            ->delete()
            ->where('a.abilitato=:no')
            ->setParameters(['no' => 0])
            ->getQuery()
            ->getResult();
          $this->em->getRepository('App\Entity\Genitore')->createQueryBuilder('g')
            ->delete()
            ->where('g.abilitato=:no')
            ->setParameters(['no' => 0])
            ->getQuery()
            ->getResult();
          $subquery = $this->em->getRepository('App\Entity\Alunno')->createQueryBuilder('a')
              ->select('a.id')
              ->where('a.abilitato=:no')
              ->getDql();
          $this->em->getRepository('App\Entity\Esito')->createQueryBuilder('e')
            ->delete()
            ->where('e.alunno IN ('.$subquery.')')
            ->setParameters(['no' => 0])
            ->getQuery()
            ->getResult();
          $this->em->getRepository('App\Entity\VotoScrutinio')->createQueryBuilder('vs')
            ->delete()
            ->where('vs.alunno IN ('.$subquery.')')
            ->setParameters(['no' => 0])
            ->getQuery()
            ->getResult();
          $this->em->getRepository('App\Entity\Alunno')->createQueryBuilder('a')
            ->delete()
            ->where('a.abilitato=:no')
            ->setParameters(['no' => 0])
            ->getQuery()
            ->getResult();
          $this->addFlash('success', 'message.tutte_operazioni_ok');
          break;
        case 4: // gestione esiti
          // scrutini finali
          $alunniSospesi = [];
          $alunniAmmessi = [];
          $scrutini = $this->em->getRepository('App\Entity\Scrutinio')->createQueryBuilder('s')
            ->where('s.periodo=:finale')
            ->setParameters(['finale' => 'F'])
            ->getQuery()
            ->getResult();
          foreach ($scrutini as $scrutinio) {
            // non ammessi per assenze
            $noScrutinabili = array_keys(array_filter($scrutinio->getDato('no_scrutinabili') ?? [],
              function($v) {
                return empty($v['deroga']);
              }));
            foreach ($noScrutinabili as $alu) {
              $alunno = $this->em->getRepository('App\Entity\Alunno')->find($alu);
              $esito = (new StoricoEsito())
                ->setClasse($scrutinio->getClasse()->getAnno().$scrutinio->getClasse()->getSezione())
                ->setEsito('L')
                ->setPeriodo('F')
                ->setAlunno($alunno);
              $this->em->persist($esito);
            }
            // anno all'estero
            $estero = $scrutinio->getDato('estero') ?? [];
            foreach ($estero as $alu) {
              $alunno = $this->em->getRepository('App\Entity\Alunno')->find($alu);
              $esito = (new StoricoEsito())
                ->setClasse($scrutinio->getClasse()->getAnno().$scrutinio->getClasse()->getSezione())
                ->setEsito('E')
                ->setPeriodo('F')
                ->setAlunno($alunno);
              $this->em->persist($esito);
            }
            // alunni scrutinati
            $scrutinabili = array_keys($scrutinio->getDato('scrutinabili') ?? []);
            foreach ($scrutinabili as $alu) {
              $esitoScrutinio = $this->em->getRepository('App\Entity\Esito')->findOneBy(['alunno' => $alu,
                'scrutinio' => $scrutinio]);
              if ($esitoScrutinio && $esitoScrutinio->getEsito() == 'S') {
                // sospesi
                $alunniSospesi[$alu]['carenze'] = $esitoScrutinio->getDati()['carenze_materie'] ?? [];
                $votiScrutinio = $this->em->getRepository('App\Entity\VotoScrutinio')->createQueryBuilder('vs')
                  ->where('vs.alunno=:alunno AND vs.scrutinio=:scrutinio AND vs.unico < 6')
                  ->setParameters(['alunno' => $alu, 'scrutinio' => $scrutinio])
                  ->getQuery()
                  ->getResult();
                foreach ($votiScrutinio as $vs) {
                  $alunniSospesi[$alu]['debiti'][] = $vs->getMateria()->getNomeBreve();
                }
              } elseif ($esitoScrutinio &&
                        ($scrutinio->getClasse()->getAnno() != 5 || $esitoScrutinio->getEsito() == 'N')) {
                // ammessi e non ammessi
                if ($esitoScrutinio->getEsito() == 'A') {
                  $alunniAmmessi[] = $alu;
                }
                $esito = (new StoricoEsito())
                  ->setClasse($scrutinio->getClasse()->getAnno().$scrutinio->getClasse()->getSezione())
                  ->setEsito($esitoScrutinio->getEsito())
                  ->setPeriodo('F')
                  ->setAlunno($esitoScrutinio->getAlunno())
                  ->setMedia($esitoScrutinio->getMedia())
                  ->setCredito($esitoScrutinio->getCredito())
                  ->setCreditoPrecedente($esitoScrutinio->getCreditoPrecedente())
                  ->setDati($esitoScrutinio->getDati());
                $this->em->persist($esito);
                // legge voti
                $carenzeMaterie = $esitoScrutinio->getDati()['carenze_materie'] ?? [];
                $votiScrutinio = $this->em->getRepository('App\Entity\VotoScrutinio')->findBy(['alunno' => $alu,
                  'scrutinio' => $scrutinio]);
                foreach($votiScrutinio as $vs) {
                  $carenze = '';
                  $datiVoto = [];
                  if ($esitoScrutinio->getEsito() == 'A' && !empty($carenzeMaterie) &&
                      in_array($vs->getMateria()->getNomeBreve(), $carenzeMaterie, true)) {
                    $carenze = $vs->getDebito();
                    $datiVoto['carenza'] = 'C';
                  }
                  $voto = (new StoricoVoto())
                    ->setStoricoEsito($esito)
                    ->setMateria($vs->getMateria())
                    ->setVoto($vs->getUnico())
                    ->setCarenze($carenze)
                    ->setDati($datiVoto);
                  $this->em->persist($voto);
                }
              }
            }
          }
          $this->em->flush();
          $this->em->clear();
          // scrutini sospesi
          $esitiScrutini = $this->em->getRepository('App\Entity\Esito')->createQueryBuilder('e')
            ->join('e.scrutinio', 's')
            ->where('e.alunno IN (:alunni) AND e.esito IN (:esiti) AND s.periodo IN (:periodi)')
            ->setParameters(['alunni' => array_keys($alunniSospesi), 'esiti' => ['A', 'N'],
              'periodi' => ['G', 'R']])
            ->getQuery()
            ->getResult();
          foreach ($esitiScrutini as $es) {
            // ammessi e non ammessi
            if ($es->getEsito() == 'A') {
              $alunniAmmessi[] = $es->getAlunno()->getId();
            }
            $esito = (new StoricoEsito())
              ->setClasse($es->getScrutinio()->getClasse()->getAnno().$es->getScrutinio()->getClasse()->getSezione())
              ->setEsito($es->getEsito())
              ->setPeriodo('G')
              ->setAlunno($es->getAlunno())
              ->setMedia($es->getMedia())
              ->setCredito($es->getCredito())
              ->setCreditoPrecedente($es->getCreditoPrecedente())
              ->setDati($es->getDati());
            $this->em->persist($esito);
            // legge voti
            $votiScrutinio = $this->em->getRepository('App\Entity\VotoScrutinio')->findBy([
              'alunno' => $es->getAlunno(), 'scrutinio' => $es->getScrutinio()]);
            foreach($votiScrutinio as $vs) {
              $carenze = '';
              $datiVoto = [];
              if ($esitoScrutinio->getEsito() == 'A') {
                if (in_array($vs->getMateria()->getNomeBreve(),
                    $alunniSospesi[$es->getAlunno()->getId()]['debiti'], true)) {
                  $carenze = $vs->getDebito();
                  $datiVoto['carenza'] = 'D';
                } elseif (in_array($vs->getMateria()->getNomeBreve(),
                          $alunniSospesi[$es->getAlunno()->getId()]['carenze'], true)) {
                  $carenze = $vs->getDebito();
                  $datiVoto['carenza'] = 'C';
                }
              }
              $voto = (new StoricoVoto())
                ->setStoricoEsito($esito)
                ->setMateria($vs->getMateria())
                ->setVoto($vs->getUnico())
                ->setCarenze($carenze)
                ->setDati($datiVoto);
              $this->em->persist($voto);
            }
            unset($alunniSospesi[$es->getAlunno()->getId()]);
          }
          $this->em->flush();
          $this->em->clear();
          // esiti scrutini rinviati
          $esitiScrutini = $this->em->getRepository('App\Entity\Esito')->createQueryBuilder('e')
            ->join('e.scrutinio', 's')
            ->where('e.alunno IN (:alunni) AND s.periodo=:periodo')
            ->setParameters(['alunni' => array_keys($alunniSospesi), 'periodo' => 'F'])
            ->getQuery()
            ->getResult();
          foreach ($esitiScrutini as $es) {
            $esito = (new StoricoEsito())
              ->setClasse($es->getScrutinio()->getClasse()->getAnno().$es->getScrutinio()->getClasse()->getSezione())
              ->setEsito($es->getEsito())
              ->setPeriodo('X')
              ->setAlunno($es->getAlunno())
              ->setMedia($es->getMedia())
              ->setCredito($es->getCredito())
              ->setCreditoPrecedente($es->getCreditoPrecedente())
              ->setDati($es->getDati());
            $this->em->persist($esito);
            // legge voti
            $votiScrutinio = $this->em->getRepository('App\Entity\VotoScrutinio')->findBy([
              'alunno' => $es->getAlunno(), 'scrutinio' => $es->getScrutinio()]);
            foreach($votiScrutinio as $vs) {
              $voto = (new StoricoVoto())
                ->setStoricoEsito($esito)
                ->setMateria($vs->getMateria())
                ->setVoto($vs->getUnico())
                ->setCarenze('')
                ->setDati([]);
              $this->em->persist($voto);
            }
          }
          $this->em->flush();
          $this->em->clear();
          // scrutini rinviati al successivo A.S.
          $datiScrutinio = [] ;
          $scrutini = $this->em->getRepository('App\Entity\Scrutinio')->createQueryBuilder('s')
            ->join('App\Entity\Esito', 'e', 'WITH', 'e.scrutinio=s.id')
            ->where('s.periodo=:periodo AND e.esito=:esito')
            ->setParameters(['periodo' => 'G', 'esito' => 'X'])
            ->getQuery()
            ->getResult();
          foreach ($scrutini as $scrutinio) {
            $dati = [];
            // dati materie
            $materie = $this->em->getRepository('App\Entity\Materia')->createQueryBuilder('m')
              ->select('DISTINCT m.id')
              ->join('App\Entity\Cattedra', 'c', 'WITH', 'c.materia=m.id')
              ->where('c.classe=:classe AND c.attiva=:attiva AND c.tipo=:tipo')
              ->orderBy('m.ordinamento', 'ASC')
              ->setParameters(['classe' => $scrutinio->getClasse(), 'attiva' => 1, 'tipo' => 'N'])
              ->getQuery()
              ->getArrayResult();
            $dati['materie'] = array_map(fn($m) => $m['id'], $materie);
            $condotta = $this->em->getRepository('App\Entity\Materia')->findOneByTipo('C');
            $dati['materie'][] = $condotta->getId();
            // dati alunni
            $alunni = $this->em->getRepository('App\Entity\Alunno')->createQueryBuilder('a')
              ->join('App\Entity\Esito', 'e', 'WITH', 'e.alunno=a.id')
              ->where('e.scrutinio=:scrutinio AND e.esito=:esito')
              ->setParameters(['scrutinio' => $scrutinio, 'esito' => 'X'])
              ->getQuery()
              ->getResult();
            foreach ($alunni as $alunno) {
              $dati['alunni'][] = $alunno->getId();
              $dati['religione'][$alunno->getId()] = $alunno->getReligione();
              $dati['bes'][$alunno->getId()] = $alunno->getBes();
              $dati['credito3'][$alunno->getId()] = $alunno->getCredito3();
              $dati['scrutinabili'][$alunno->getId()] = $scrutinio->getDato('scrutinabili')[$alunno->getId()];
              // voti e assenze alunno
              $voti = $this->em->getRepository('App\Entity\VotoScrutinio')->createQueryBuilder('vs')
                ->where('vs.scrutinio=:scrutinio AND vs.alunno=:alunno')
                ->setParameters(['scrutinio' => $scrutinio, 'alunno' => $alunno])
                ->getQuery()
                ->getResult();
              foreach ($voti as $voto) {
                $dati['voti'][$alunno->getId()][$voto->getMateria()->getId()]['unico'] = $voto->getUnico();
                $dati['voti'][$alunno->getId()][$voto->getMateria()->getId()]['assenze'] = $voto->getAssenze();
              }
            }
            // dati docenti
            $docenti = $this->em->getRepository('App\Entity\Cattedra')->createQueryBuilder('c')
              ->select('d.id,d.cognome,d.nome,d.sesso,c.tipo,m.id AS m_id')
              ->join('c.docente', 'd')
              ->join('c.materia', 'm')
              ->where('c.classe=:classe AND c.attiva=:attiva AND c.tipo!=:tipo')
              ->orderBy('d.cognome,d.nome,m.ordinamento', 'ASC')
              ->setParameters(['classe' => $scrutinio->getClasse(), 'attiva' => 1, 'tipo' => 'P'])
              ->getQuery()
              ->getArrayResult();
            foreach ($docenti as $docente) {
              $dati['docenti'][$docente['id']]['cognome'] = $docente['cognome'];
              $dati['docenti'][$docente['id']]['nome'] = $docente['nome'];
              $dati['docenti'][$docente['id']]['sesso'] = $docente['sesso'];
              $dati['docenti'][$docente['id']]['cattedre'][] = ['tipo' => $docente['tipo'],
                'materia' =>$docente['m_id']];
            }
            // memorizza dati scrutinio
            $datiScrutinio[] = ['classe' => $scrutinio->getClasse()->getId(), 'dati' => $dati];
          }
          // gestione alunni promossi
          $alunni = $this->em->getRepository('App\Entity\Alunno')->createQueryBuilder('a')
            ->join('a.classe', 'c')
            ->where('a.id IN (:alunni)')
            ->setParameters(['alunni' => $alunniAmmessi])
            ->getQuery()
            ->getResult();
          foreach ($alunni as $alunno) {
            // imposta nuova classe (o null se non esiste)
            $classe = $this->em->getRepository('App\Entity\Classe')->findOneBy([
              'anno' => 1 + $alunno->getClasse()->getAnno(), 'sezione' => $alunno->getClasse()->getSezione()]);
            $alunno->setClasse($classe);
          }
          $this->em->flush();
          $this->em->clear();
          // gestione alunni in uscita
          $alunni = $this->em->getRepository('App\Entity\Alunno')->createQueryBuilder('a')
            ->join('a.classe', 'c')
            ->join('App\Entity\Scrutinio', 's', 'WITH', 's.classe=c.id AND s.periodo=:periodo')
            ->join('App\Entity\Esito', 'e', 'WITH', 'e.alunno=a.id AND e.scrutinio=s.id')
            ->leftJoin('App\Entity\StoricoEsito', 'se', 'WITH', 'se.alunno=a.id')
            ->where('c.anno=:quinta AND e.esito=:ammesso AND se.id IS NULL')
            ->setParameters(['periodo' => 'F', 'quinta' => 5, 'ammesso' => 'A'])
            ->getQuery()
            ->getResult();
          $alunniEliminati = array_map(function($o) { return $o->getId(); }, $alunni);
          $genitori = $this->em->getRepository('App\Entity\Genitore')->createQueryBuilder('g')
            ->join('g.alunno', 'a')
            ->where('a.id IN (:lista)')
            ->setParameters(['lista' => $alunniEliminati])
            ->getQuery()
            ->getResult();
          $genitoriEliminati = array_map(function($o) { return $o->getId(); }, $genitori);
          // svuota tabelle non più necessarie (ed evita conflitti con comandi successivi)
          $connection = $this->em->getConnection();
          $sqlCommands = [
            "SET FOREIGN_KEY_CHECKS = 0;",
            "TRUNCATE gs_cattedra;",
            "TRUNCATE gs_esito;",
            "TRUNCATE gs_scrutinio;",
            "TRUNCATE gs_voto_scrutinio;",
            "SET FOREIGN_KEY_CHECKS = 1;"];
          foreach ($sqlCommands as $sql) {
            $connection->prepare($sql)->execute();
          }
          // elimina utenti
          $this->em->getRepository('App\Entity\CircolareUtente')->createQueryBuilder('cu')
            ->delete()
            ->where('cu.utente IN (:lista)')
            ->setParameters(['lista' => array_merge($genitoriEliminati, $alunniEliminati)])
            ->getQuery()
            ->getResult();
          $this->em->getRepository('App\Entity\AvvisoUtente')->createQueryBuilder('au')
            ->delete()
            ->where('au.utente IN (:lista)')
            ->setParameters(['lista' => array_merge($genitoriEliminati, $alunniEliminati)])
            ->getQuery()
            ->getResult();
          $this->em->getRepository('App\Entity\Genitore')->createQueryBuilder('g')
            ->delete()
            ->where('g.id IN (:lista)')
            ->setParameters(['lista' => $genitoriEliminati])
            ->getQuery()
            ->getResult();
          $this->em->getRepository('App\Entity\Alunno')->createQueryBuilder('a')
            ->delete()
            ->where('a.id IN (:lista)')
            ->setParameters(['lista' => $alunniEliminati])
            ->getQuery()
            ->getResult();
          // aggiunge scrutini rinviati
          foreach ($datiScrutinio as $dati) {
            $classe = $this->em->getRepository('App\Entity\Classe')->find($dati['classe']);
            $scrutinioRinviato = (new Scrutinio())
              ->setClasse($classe)
              ->setPeriodo('X')
              ->setStato('N')
              ->setDati($dati['dati']);
            $this->em->persist($scrutinioRinviato);
          }
          if (count($datiScrutinio) > 0) {
            // crea definizione scrutinio rinviato
            $argomenti[1] = $trans->trans('label.verbale_scrutinio_X');
            $argomenti[2] = $trans->trans('label.verbale_situazioni_particolari');
            $struttura[1] = ['ScrutinioInizio', false, []];
            $struttura[2] = ['ScrutinioSvolgimento', false, ['sezione' => 'Punto primo', 'argomento' => 1]];
            $struttura[3] = ['Argomento', true, ['sezione' => 'Punto secondo', 'argomento' => 2,
              'obbligatorio' => false, 'inizio' => '', 'seVuoto' => '', 'default' => '', 'fine' => '']];
            $struttura[4] = ['ScrutinioFine', false, []];
            $defScrutinio = (new DefinizioneScrutinio())
              ->setData(new \DateTime('today'))
              ->setDataProposte(new \DateTime('today'))
              ->setPeriodo('X')
              ->setArgomenti($argomenti)
              ->setStruttura($struttura);
            $this->em->persist($defScrutinio);
          }
          // memorizza dati
          $this->em->flush();
          $this->em->clear();
          // cancella deroghe alunni anno precedente
          $this->em->getRepository('App\Entity\Alunno')->createQueryBuilder('a')
            ->update()
            ->set('a.autorizzaEntrata', ':no')
            ->set('a.autorizzaUscita', ':no')
            ->setParameters(['no' => null])
            ->getQuery()
            ->getResult();
          // parametro nuovo anno
          $this->em->getRepository('App\Entity\Configurazione')->setParametro('anno_scolastico',
            $info['nuovoAnno'].'/'.(1 + $info['nuovoAnno']));
          $this->addFlash('success', 'message.tutte_operazioni_ok');
          break;
        case 5:
          // cancella cache
          $command = new ArrayInput(['command' => 'cache:clear', '--no-warmup' => true, '-n' => true, '-q' => true]);
          // esegue comando
          $application = new Application($kernel);
          $application->setAutoExit(false);
          $output = new BufferedOutput();
          $status = $application->run($command, $output);
          if ($status != 0) {
            // errore nell'esecuzione del comando
            $content = $output->fetch();
            $this->addFlash('danger', $trans->trans('exception.svuota_cache', ['errore' => $content]));
          } else {
            // messaggio ok
            $this->addFlash('success', 'message.tutte_operazioni_ok');
          }
          break;
      }
    }
    // mostra la pagina di risposta
    return $this->renderHtml('sistema', 'nuovo', $dati, $info, [$form->createView(),
      'message.nuovo_anno_'.$step]);
  }

  /**
   * Gestione dell'archiviazione dei registri in PDF
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param ArchiviazioneUtil $arch Funzioni di utilità per l'archiviazione
   *
   * @return Response Pagina di risposta
   *
   * @Route("/sistema/archivia/", name="sistema_archivia",
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function archiviaAction(Request $request, TranslatorInterface $trans,
                                 ArchiviazioneUtil $arch): Response {
    // init
    $dati = [];
    $info = [];
    $lista_docenti = $this->em->getRepository('App\Entity\Docente')->createQueryBuilder('d')
      ->join('App\Entity\Cattedra', 'c', 'WITH', 'c.docente=d.id')
      ->join('c.materia', 'm')
      ->where('m.tipo IN (:tipi)')
      ->orderBy('d.cognome,d.nome', 'ASC')
      ->setParameters(['tipi' => ['N', 'R', 'E']])
      ->getQuery()
      ->getResult();
    $lista_sostegno = $this->em->getRepository('App\Entity\Docente')->createQueryBuilder('d')
      ->join('App\Entity\Cattedra', 'c', 'WITH', 'c.docente=d.id')
      ->join('c.materia', 'm')
      ->where('m.tipo=:tipo')
      ->orderBy('d.cognome,d.nome', 'ASC')
      ->setParameters(['tipo' => 'S'])
      ->getQuery()
      ->getResult();
    $lista_classi = $this->em->getRepository('App\Entity\Classe')->createQueryBuilder('c')
      ->orderBy('c.anno,c.sezione', 'ASC')
      ->getQuery()
      ->getResult();
    $label_docenti = $trans->trans('label.tutti_docenti');
    $label_classi = $trans->trans('label.tutte_classi');
    // form
    $form = $this->createForm(ModuloType::class, null, ['formMode' => 'archivia',
      'dati' => [$lista_docenti, $lista_sostegno, $lista_classi, $label_docenti, $label_classi]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // form inviato
      $docente = $form->get('docente')->getData();
      $sostegno = $form->get('sostegno')->getData();
      $classe = $form->get('classe')->getData();
      $scrutinio = $form->get('scrutinio')->getData();
      $circolare = ($form->get('circolare')->getData() === true);
      // assicura che lo script non sia interrotto
      ini_set('max_execution_time', 0);
      // registro docenti
      if (is_object($docente)) {
        // crea registro
        $arch->registroDocente($docente);
      } elseif ($docente === -1) {
        // crea tutti i registri
        $arch->tuttiRegistriDocente($lista_docenti);
      }
      // registro sostegno
      if (is_object($sostegno)) {
        // crea registro
        $arch->registroSostegno($sostegno);
      } elseif ($sostegno === -1) {
        // crea tutti i registri
        $arch->tuttiRegistriSostegno($lista_sostegno);
      }
      // registro classe
      if (is_object($classe)) {
        // crea registro
        $arch->registroClasse($classe);
      } elseif ($classe === -1) {
        // crea tutti i registri
        $arch->tuttiRegistriClasse($lista_classi);
      }
      // documenti scrutinio
      if (is_object($scrutinio)) {
        // crea documenti per la classe
        $arch->scrutinioClasse($scrutinio);
      } elseif ($scrutinio === -1) {
        // crea documenti per tutte le classi
        $arch->tuttiScrutiniClasse($lista_classi);
      }
      // archivio circolari
      if ($circolare) {
        // crea archivio delle circolari
        $arch->archivioCircolari();
      }
    }
    // mostra la pagina di risposta
    return $this->renderHtml('sistema', 'archivia', $dati, $info, [$form->createView(), 'message.archivia']);
  }

  /**
   * Cancella la cache di sistema
   *
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param KernelInterface $kernel Gestore delle funzionalità http del kernel
   *
   * @return Response Pagina di risposta
   *
   * @Route("/sistema/manutenzione/cache/", name="sistema_manutenzione_cache",
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function manutenzioneCacheAction(TranslatorInterface $trans, KernelInterface $kernel): Response {
    // assicura che lo script non sia interrotto
    ini_set('max_execution_time', 0);
    // comandi per la pulizia della cache del database
    $commands = [
      new ArrayInput(['command' => 'cache:clear', '--no-warmup' => true, '-n' => true, '-q' => true]),
      new ArrayInput(['command' => 'doctrine:cache:clear-query', '--flush' => true, '-n' => true, '-q' => true]),
      new ArrayInput(['command' => 'doctrine:cache:clear-result', '--flush' => true, '-n' => true, '-q' => true]),
    ];
    // esegue comandi
    $application = new Application($kernel);
    $application->setAutoExit(false);
    $output = new BufferedOutput();
    foreach ($commands as $com) {
      $status = $application->run($com, $output);
      if ($status != 0) {
        // errore nell'esecuzione del comando
        $content = $output->fetch();
        $this->addFlash('danger', $trans->trans('exception.svuota_cache', ['errore' => $content]));
        break;
      }
    }
    // redirect
    return $this->redirectToRoute('sistema_manutenzione');
  }

  /**
   * Effettua il logout forzato degli utenti (tranne amministratore)
   *
   * @param Request $request Pagina richiesta
   *
   * @return Response Pagina di risposta
   *
   * @Route("/sistema/manutenzione/logout/", name="sistema_manutenzione_logout",
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function manutenzioneLogoutAction(Request $request): Response {
    // nome del file di sessione in uso
    $mySession = 'sess_'.$request->cookies->get('PHPSESSID');
    // elimina le sessioni tranne quella corrente
    $dir = $this->getParameter('kernel.project_dir').'/var/sessions/'.$request->server->get('APP_ENV');
    $finder = new Finder();
    $finder->files()->in($dir)->notName([$mySession]);
    foreach ($finder as $file) {
      unlink($file->getRealPath());
    }
    // messaggio
    $this->addFlash('success', 'message.logout_utenti_ok');
    // redirect
    return $this->redirectToRoute('sistema_manutenzione');
  }

  /**
   * Estrae il log degli errori
   *
   * @param Request $request Pagina richiesta
   *
   * @return Response Pagina di risposta
   *
   * @Route("/sistema/manutenzione/log/", name="sistema_manutenzione_log",
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function manutenzioneLogAction(Request $request): Response {
    // init
    $dati = [];
    $info = [];
    // imposta data e ora corrente
    $data = new \DateTime('today');
    $ora = new \DateTime('now');
    // form
    $form = $this->createForm(ModuloType::class, null, ['formMode' => 'log',
      'returnUrl' => $this->generateUrl('sistema_manutenzione'), 'dati' => [$data, $ora]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      $msgs = [];
      // imposta data, ora inizio e ora fine
      $dt = $form->get('data')->getData()->format('Y-m-d');
      $tm = $form->get('ora')->getData()->format('H:i');
      $inizio = '['.$dt.'T'.$tm.':00';
      $fine = '['.$dt.'T'.$form->get('ora')->getData()->modify('+1 hour')->format('H:i').':00';
      // nome file
      $nomefile = $this->getParameter('kernel.project_dir').'/var/log/app_'.
        mb_strtolower($request->server->get('APP_ENV')).'-'.$dt.'.log';
      if (file_exists($nomefile)) {
        $fl = fopen($nomefile, "r");
        while (($riga = fgets($fl)) !== false) {
          // legge una riga
          $tag = substr($riga, 0, 20);
          if ($tag >= $inizio && $tag <= $fine) {
            // estrae messaggio;
            $msgs[] = $riga;
          }
        }
        fclose($fl);
        if (!empty($msgs)) {
          // sono presenti messaggi
          $logfile = $dt.'_'.str_replace(':', '-', $tm).'.log';
          $disposition = HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $logfile);
          $response = new Response(implode($msgs));
          $response->headers->set('Content-Type', 'text/plain');
          $response->headers->set('Content-Disposition', $disposition);
          // invia il file
          return $response;
        }
      }
      // errore: nessun messaggio
      $this->addFlash('danger', 'exception.log_errori_vuoto');
    }
    // mostra la pagina di risposta
    return $this->renderHtml('sistema', 'manutenzione_log', $dati, $info, [$form->createView(), 'message.log_errori']);
  }

  /**
   * Imposta le informazioni di debug nel log di sistema
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param KernelInterface $kernel Gestore delle funzionalità http del kernel
   *
   * @return Response Pagina di risposta
   *
   * @Route("/sistema/manutenzione/debug/", name="sistema_manutenzione_debug",
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function manutenzioneDebugAction(Request $request, TranslatorInterface $trans,
                                          KernelInterface $kernel): Response {
    // imposta nuovo livello di log
    $logLevel = ($request->server->get('LOG_LEVEL') == 'warning') ? 'debug' : 'warning';
    // legge .env
    $envPath = $this->getParameter('kernel.project_dir').'/.env';
    $envData = file($envPath);
    // modifica impostazione
    foreach ($envData as $row=>$text) {
      if (substr($text, 0 , 9) == 'LOG_LEVEL') {
        // modifica valore
        $envData[$row] = "LOG_LEVEL='".$logLevel."'\n";
        break;
      }
    }
    // scrive nuovo .env
    unlink($envPath);
    file_put_contents($envPath, $envData);
    // cancella cache
    $command = new ArrayInput(['command' => 'cache:clear', '--no-warmup' => true, '-n' => true, '-q' => true]);
    // esegue comando
    $application = new Application($kernel);
    $application->setAutoExit(false);
    $output = new BufferedOutput();
    $status = $application->run($command, $output);
    if ($status != 0) {
      // errore nell'esecuzione del comando
      $content = $output->fetch();
      $this->addFlash('danger', $trans->trans('exception.svuota_cache', ['errore' => $content]));
    } else {
      // messaggio ok
      $this->addFlash('success', 'message.modifica_log_level_ok');
    }
    // redirect
    return $this->redirectToRoute('sistema_manutenzione');
  }

  /**
   * Esegue l'aggiornamento a una nuova versione
   *
   * @param Request $request Pagina richiesta
   * @param int $step Passo della procedura
   *
   * @return Response Pagina di risposta
   *
   * @Route("/sistema/aggiorna/{step}", name="sistema_aggiorna",
   *    requirements={"step": "\d+"},
   *    defaults={"step": "0"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function aggiornaAction(Request $request, int $step): Response {
    // inizializza
    $dati = [];
    $info = [];
    // assicura che lo script non sia interrotto
    ini_set('max_execution_time', 0);
    $info['step'] = 0;
    $info['prossimo'] = 0;
    // esegue passi
    switch($step) {
      case 0:   // controlli iniziali
        $url = 'https://github.com/iisgiua/giuaschool-docs/raw/master/_data/version.yml';
        $pagina = file_get_contents($url);
        preg_match('/^tag:\s*([0-9\.]+)$/m', $pagina, $trovati);
        if (count($trovati) != 2) {
          // errore recupero versione
          $info['tipo'] = 'danger';
          $info['messaggio'] = 'exception.aggiornamento_no_versione';
          break;
        }
        // controlla versione
        $nuovaVersione = $trovati[1];
        $versione = $this->em->getRepository('App\Entity\Configurazione')->getParametro('versione');
        if (version_compare($nuovaVersione, $versione, '<=')) {
          // sistema già aggiornato
          $info['tipo'] = 'info';
          $info['messaggio'] = 'message.sistema_aggiornato';
          break;
        }
        // nuova versione presente
        $dati['versione'] = $nuovaVersione;
        if (!extension_loaded('zip')) {
          // zip non supportato
          $info['tipo'] = 'danger';
          $info['messaggio'] = 'exception.aggiornamento_zip_non_presente';
          break;
        }
        // controlla esistenza file
        $file = dirname(__DIR__).'/Install/v'.$nuovaVersione.'.ok';
        if (file_exists($file)) {
          // file già scaricato: salta il passo successivo
          $info['tipo'] = 'success';
          $info['messaggio'] = 'message.aggiornamento_scaricato';
          $info['prossimo'] = 2;
        } else {
          // file da scaricare
          $info['tipo'] = 'success';
          $info['messaggio'] = 'message.aggiornamento_possibile';
          $info['prossimo'] = 1;
        }
        $this->reqstack->getSession()->set('/APP/ROUTE/sistema_aggiorna/versione', $nuovaVersione);
        break;
      case 1:   // scarica file
        $nuovaVersione = $this->reqstack->getSession()->get('/APP/ROUTE/sistema_aggiorna/versione');
        $dati['versione'] = $nuovaVersione;
        $url = 'https://github.com/iisgiua/giuaschool/releases/download/v'.$nuovaVersione.
          '/giuaschool-release-v'.$nuovaVersione.'.zip';
        $file = dirname(__DIR__).'/Install/v'.$nuovaVersione.'.zip';
        // scarica file
        $bytes = file_put_contents($file, file_get_contents($url));
        if ($bytes == 0) {
          $info['tipo'] = 'danger';
          $info['messaggio'] = 'exception.aggiornamento_errore_file';
          break;
        }
        // conferma scaricamento
        $file = dirname(__DIR__).'/Install/v'.$nuovaVersione.'.ok';
        file_put_contents($file, '');
        $info['tipo'] = 'success';
        $info['messaggio'] = 'message.aggiornamento_scaricato';
        $info['prossimo'] = 2;
        break;
      case 2:   // installazione
        // salva dati per l'installazione
        $nuovaVersione = $this->reqstack->getSession()->get('/APP/ROUTE/sistema_aggiorna/versione');
        $token = bin2hex(random_bytes(16));
        $contenuto = 'token="'.$token.'"'."\n".
          'version="'.$nuovaVersione.'"'."\n";
        file_put_contents(dirname(dirname(__DIR__)).'/.gs-updating', $contenuto);
        // reindirizza a pagina di installazione
        $urlPath = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? 'https' : 'http').
          '://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
        $urlPath = substr($urlPath, 0, - strlen('/sistema/aggiorna/2'));
        return $this->redirect($urlPath."/install/update.php?token=$token&step=1");
        break;
    }
    // mostra la pagina di risposta
    return $this->renderHtml('sistema', 'aggiorna', $dati, $info);
  }

  /**
   * Configura il server per l'invio delle email
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param MailerInterface $mailer Gestore per l'invio delle email
   * @param KernelInterface $kernel Gestore delle funzionalità http del kernel
   *
   * @return Response Pagina di risposta
   *
   * @Route("/sistema/email", name="sistema_email",
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function emailAction(Request $request, TranslatorInterface $trans, MailerInterface $mailer,
                              KernelInterface $kernel): Response {
    // inizializza
    $dati = [];
    $info = [];
    if ($request->isMethod('GET')) {
      // inizializza sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/sistema_email/invio', '');
    }
    // legge .env
    $envPath = $this->getParameter('kernel.project_dir').'/.env';
    $envData = parse_ini_file($envPath);
    // estrae configurazione
    $dsn = parse_url($envData['MAILER_DSN'] ?? '');
    $info['server'] = (in_array($dsn['scheme'] ?? '', ['smtp', 'sendmail', 'gmail+smtp', 'php'], true) ?
      $dsn['scheme'] : '');
    $info['user'] = (substr($info['server'], -4) == 'smtp') ? ($dsn['user'] ?? '') : '';
    $info['password'] = (substr($info['server'], -4) == 'smtp') ? ($dsn['pass'] ?? '') : '';
    $info['host'] = $info['server'] == 'smtp' ? ($dsn['host'] ?? '') : '';
    $info['port'] = $info['server'] == 'smtp' ? ($dsn['port'] ?? null) : null;
    $info['email'] = '';
    // form
    $form = $this->createForm(ModuloType::class, null, ['formMode' => 'email', 'dati' => $info]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // legge dati form
      $server = $form->get('server')->getData();
      $user = $form->get('user')->getData();
      $password = $form->get('password')->getData();
      $host = $form->get('host')->getData();
      $port = $form->get('port')->getData();
      $this->email = $form->get('email')->getData();
      if (empty($this->reqstack->getSession()->get('/APP/ROUTE/sistema_email/invio'))) {
        // controlla errori
        if (!$server || !in_array($server, ['smtp', 'sendmail', 'gmail+smtp', 'php'], true)) {
          $form->addError(new FormError($trans->trans('exception.mailserver_no_server')));
        }
        if (substr($server, -4) == 'smtp' && !$user) {
          $form->addError(new FormError($trans->trans('exception.mailserver_no_user')));
        }
        if (substr($server, -4) == 'smtp' && !$password) {
          $form->addError(new FormError($trans->trans('exception.mailserver_no_password')));
        }
        if ($server == 'smtp' && !$host) {
          $form->addError(new FormError($trans->trans('exception.mailserver_no_host')));
        }
        if ($server == 'smtp' && !$port) {
          $form->addError(new FormError($trans->trans('exception.mailserver_no_port')));
        }
        if (!$this->email) {
          $form->addError(new FormError($trans->trans('exception.mailserver_no_email')));
        }
        if ($form->isValid()) {
          // imposta il DSN
          if ($server == 'sendmail' || $server == 'php') {
            $dsn = $server.'://default';
          } else {
            $dsn = $server.'://'.$user.':'.$password.'@'.
              ($server == 'smtp' ? $host.':'.$port : 'default');
          }
          // legge .env
          $envData = file($envPath);
          // modifica impostazione
          foreach ($envData as $row => $text) {
            if (preg_match('/^\s*MAILER_DSN\s*=/', $text)) {
              // modifica valore
              $envData[$row] = "MAILER_DSN='".$dsn."'\n";
              break;
            }
          }
          // scrive nuovo .env
          unlink($envPath);
          file_put_contents($envPath, $envData);
          // cancella cache e ricarica .env
          $command = new ArrayInput(['command' => 'cache:clear', '--no-warmup' => true, '-n' => true, '-q' => true]);
          // esegue comando
          $application = new Application($kernel);
          $application->setAutoExit(false);
          $output = new BufferedOutput();
          $status = $application->run($command, $output);
          if ($status != 0) {
            // errore nell'esecuzione del comando
            $form->addError(new FormError($trans->trans('exception.mailserver_svuota_cache',
              ['errore' => $output->fetch()])));
          } else {
            // ok: imposta sessione
            $info['email'] = $this->email;
            $this->reqstack->getSession()->set('/APP/ROUTE/sistema_email/invio', $this->email);
          }
        }
      } else {
        // spedisce mail di test
        $text = "Questa è il testo dell'email.\n".
          "La mail è stata spedita dall'applicazione giua@school per verificare il corretto recapito della posta elettronica.\n\n".
          "Allegato:\n - il file di testo della licenza AGPL.\n";
        $html = "<p><strong>Questa è il testo dell'email.</strong></p>".
          "<p><em>La mail è stata spedita dall'applicazione <strong>giua@school</strong> per verificare il corretto recapito della posta elettronica.</em></p>".
          "<p>Allegato:</p><ul><li>il file di testo della licenza AGPL.</li></ul>";
        // invia per email
        $message = (new Email())
          ->from($this->reqstack->getSession()->get('/CONFIG/ISTITUTO/email_notifiche'))
          ->to($this->email)
          ->subject('[TEST] giua@school - Invio email di prova')
          ->text($text)
          ->html($html)
          ->attachFromPath($this->getParameter('kernel.project_dir').'/LICENSE',
            'LICENSE.txt', 'text/plain');
        try {
          // invia email
          $mailer->send($message);
          // invio riuscito
          $this->addFlash('success', 'message.mailserver_email_test_ok');
        } catch (\Exception $e) {
          // errore sull'invio dell'email
          $form->addError(new FormError($trans->trans('exception.mailserver_email_test',
            ['errore' => $e->getMessage()])));
        }
        // resetta sessione
        $this->reqstack->getSession()->set('/APP/ROUTE/sistema_email/invio', '');
      }
    }
    // mostra la pagina di risposta
    return $this->renderHtml('sistema', 'email', $dati, $info, [$form->createView(),
      'message.sistema_email']);
  }

}
