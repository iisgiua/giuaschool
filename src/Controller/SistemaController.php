<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Controller;

use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Entity\Configurazione;
use DateTime;
use App\Entity\Utente;
use App\Entity\Classe;
use Doctrine\DBAL\ArrayParameterType;
use App\Entity\Esito;
use App\Entity\StoricoEsito;
use App\Entity\Materia;
use App\Entity\Cattedra;
use App\Entity\VotoScrutinio;
use App\Entity\CambioClasse;
use App\Entity\Circolare;
use App\Entity\CircolareUtente;
use App\Entity\Avviso;
use App\Entity\AvvisoUtente;
use App\Entity\Preside;
use App\Entity\Documento;
use Exception;
use App\Entity\Alunno;
use App\Entity\DefinizioneScrutinio;
use App\Entity\Docente;
use App\Entity\Provisioning;
use App\Entity\Scrutinio;
use App\Form\ConfigurazioneType;
use App\Form\ModuloType;
use App\Form\UtenteType;
use App\Util\ArchiviazioneUtil;
use App\Util\LogHandler;
use App\Util\TelegramManager;
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
use Symfony\Component\Routing\Attribute\Route;
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
   */
  #[Route(path: '/sistema/banner/', name: 'sistema_banner', methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_AMMINISTRATORE')]
  public function banner(Request $request): Response {
    // init
    $dati = [];
    $info = [];
    // legge parametri
    $bannerLogin = $this->em->getRepository(Configurazione::class)->getParametro('banner_login', '');
    $bannerHome = $this->em->getRepository(Configurazione::class)->getParametro('banner_home', '');
    // form
    $form = $this->createForm(ConfigurazioneType::class, null, ['form_mode' => 'banner',
      'values' => [$bannerLogin, $bannerHome]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // memorizza i parametri
      $this->em->getRepository(Configurazione::class)->setParametro('banner_login',
        $form->get('banner_login')->getData() ?: '');
      $this->em->getRepository(Configurazione::class)->setParametro('banner_home',
        $form->get('banner_home')->getData() ?: '');
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
   */
  #[Route(path: '/sistema/manutenzione/', name: 'sistema_manutenzione', methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_AMMINISTRATORE')]
  public function manutenzione(Request $request): Response {
    // init
    $dati = [];
    $info = [];
    // informazioni passate alla pagina
    $info['logLevel'] = $request->server->get('LOG_LEVEL');
    // legge parametri
    $manutenzione_inizio = $this->em->getRepository(Configurazione::class)->getParametro('manutenzione_inizio', null);
    $manutenzione_fine = $this->em->getRepository(Configurazione::class)->getParametro('manutenzione_fine', null);
    if (!$manutenzione_inizio) {
      // non è impostata una manutenzione
      $manutenzione = false;
      $manutenzione_inizio = new DateTime();
      $manutenzione_inizio->modify('+'.(10 - $manutenzione_inizio->format('i') % 10).' minutes');
      $manutenzione_fine = (clone $manutenzione_inizio)->modify('+30 minutes');
    } else {
      // è già impostata una manutenzione
      $manutenzione = true;
      $manutenzione_inizio = DateTime::createFromFormat('Y-m-d H:i', $manutenzione_inizio);
      $manutenzione_fine = DateTime::createFromFormat('Y-m-d H:i', $manutenzione_fine);
    }
    // form
    $form = $this->createForm(ConfigurazioneType::class, null, ['form_mode' => 'manutenzione',
      'values' => [$manutenzione, $manutenzione_inizio, clone $manutenzione_inizio,
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
      $this->em->getRepository(Configurazione::class)->setParametro('manutenzione_inizio', $param_inizio);
      $this->em->getRepository(Configurazione::class)->setParametro('manutenzione_fine', $param_fine);
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
   */
  #[Route(path: '/sistema/parametri/', name: 'sistema_parametri', methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_AMMINISTRATORE')]
  public function parametri(Request $request): Response {
    // init
    $dati = [];
    $info = [];
    // legge parametri
    $parametri = $this->em->getRepository(Configurazione::class)->parametriConfigurazione();
    // form
    $form = $this->createForm(ConfigurazioneType::class, null, ['form_mode' => 'parametri',
      'values' => [$parametri]]);
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
   */
  #[Route(path: '/sistema/password/', name: 'sistema_password', methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_AMMINISTRATORE')]
  public function password(Request $request, UserPasswordHasherInterface $hasher,
                           TranslatorInterface $trans, ValidatorInterface $validator,
                           LogHandler $dblogger): Response {
    // init
    $dati = [];
    $info = [];
    // form
    $form = $this->createForm(UtenteType::class, null, ['form_mode' => 'password']);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // form inviato
      $username = $form->get('username')->getData();
      $user = $this->em->getRepository(Utente::class)->findOneByUsername($username);
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
          $dblogger->logAzione('SICUREZZA', 'Cambio Password', [
            'Username' => $user->getUsername(),
            'Ruolo' => $user->getRoles()[0],
            'ID' => $user->getId()]);
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
   */
  #[Route(path: '/sistema/alias/', name: 'sistema_alias', methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_AMMINISTRATORE')]
  public function alias(Request $request, TranslatorInterface $trans,
                        LogHandler $dblogger): Response {
    // init
    $dati = [];
    $info = [];
    // form
    $form = $this->createForm(UtenteType::class, null, ['form_mode' => 'alias']);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // form inviato
      $username = $form->get('username')->getData();
      $user = $this->em->getRepository(Utente::class)->findOneByUsername($username);
      if (!$user || !$user->getAbilitato()) {
        // errore, utente non esiste o non abilitato
        $form->get('username')->addError(new FormError($trans->trans('exception.invalid_user')));
      } else {
        // memorizza dati in sessione
        $this->reqstack->getSession()->set('/APP/UTENTE/tipo_accesso_reale', $this->reqstack->getSession()->get('/APP/UTENTE/tipo_accesso'));
        $this->reqstack->getSession()->set('/APP/UTENTE/ultimo_accesso_reale', $this->reqstack->getSession()->get('/APP/UTENTE/ultimo_accesso'));
        $this->reqstack->getSession()->set('/APP/UTENTE/username_reale', $this->getUser()->getUserIdentifier());
        $this->reqstack->getSession()->set('/APP/UTENTE/ruolo_reale', $this->getUser()->getRoles()[0]);
        $this->reqstack->getSession()->set('/APP/UTENTE/id_reale', $this->getUser()->getId());
        $this->reqstack->getSession()->set('/APP/UTENTE/ultimo_accesso',
          ($user->getUltimoAccesso() ? $user->getUltimoAccesso()->format('d/m/Y H:i:s') : null));
        $this->reqstack->getSession()->set('/APP/UTENTE/tipo_accesso', 'alias');
        // log azione
        $dblogger->logAzione('ACCESSO', 'Alias', [
          'Username' => $user->getUsername(),
          'Ruolo' => $user->getRoles()[0]]);
        // impersona l'alias e fa il redirect alla home
        return $this->redirectToRoute('login_home', ['reload' => 'yes', '_alias' => $username]);
      }
    }
    // mostra la pagina di risposta
    return $this->renderHtml('sistema', 'alias', $dati, $info, [$form->createView(), 'message.alias']);
  }

  /**
   * Disconnette l'alias in uso e ritorna all'utente iniziale
   *
   * @param LogHandler $dblogger Gestore dei log su database
   *
   * @return Response Pagina di risposta
   */
  #[Route(path: '/sistema/alias/exit', name: 'sistema_alias_exit', methods: ['GET'])]
  public function aliasExit(LogHandler $dblogger): Response  {
    // log azione
    $dblogger->logAzione('ACCESSO', 'Alias Exit', [
      'Username' => $this->getUser()->getUserIdentifier(),
      'Ruolo' => $this->getUser()->getRoles()[0],
      'Username reale' => $this->reqstack->getSession()->get('/APP/UTENTE/username_reale'),
      'Ruolo reale' => $this->reqstack->getSession()->get('/APP/UTENTE/ruolo_reale'),
      'ID reale' => $this->reqstack->getSession()->get('/APP/UTENTE/id_reale')]);
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
    return $this->redirectToRoute('login_home', ['reload' => 'yes', '_alias' => '_exit']);
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
   */
  #[Route(path: '/sistema/nuovo/{step}', name: 'sistema_nuovo', requirements: ['step' => '\d+'], defaults: ['step' => '0'], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_AMMINISTRATORE')]
  public function nuovo(Request $request, TranslatorInterface $trans, KernelInterface $kernel,
                        int $step): Response {
    // init
    $dati = [];
    $info = [];
    $info['nuovoAnno'] = (int) (new DateTime())->format('Y');
    $info['vecchioAnno'] = $info['nuovoAnno'] - 1;
    // form
    $form = $this->createForm(ModuloType::class, null, ['form_mode' => 'nuovo', 'values' => [$step],
      'action_url' => $this->generateUrl('sistema_nuovo', ['step' => $step + 1])]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      $fs = new Filesystem();
      $finder = new Finder();
      $path = $this->getParameter('kernel.project_dir').'/FILES';
      $connection = $this->em->getConnection();
      // assicura che lo script non sia interrotto
      ini_set('max_execution_time', 0);
      switch($step) {
        case 1: // pulizia iniziale db
          // cancella tabelle
          $sqlCommands = [
            "SET FOREIGN_KEY_CHECKS = 0;",
            "TRUNCATE TABLE gs_annotazione;",
            "TRUNCATE TABLE gs_assenza;",
            "TRUNCATE TABLE gs_assenza_lezione;",
            "TRUNCATE TABLE gs_colloquio;",
            "TRUNCATE TABLE gs_definizione_consiglio;",
            "TRUNCATE TABLE gs_definizione_richiesta;",
            "TRUNCATE TABLE gs_deroga_assenza;",
            "TRUNCATE TABLE gs_entrata;",
            "TRUNCATE TABLE gs_festivita;",
            "TRUNCATE TABLE gs_firma;",
            "TRUNCATE TABLE gs_lezione;",
            "TRUNCATE TABLE gs_log;",
            "TRUNCATE TABLE gs_messenger_messages;",
            "TRUNCATE TABLE gs_modulo_formativo;",
            "TRUNCATE TABLE gs_nota;",
            "TRUNCATE TABLE gs_nota_alunno;",
            "TRUNCATE TABLE gs_orario;",
            "TRUNCATE TABLE gs_orario_docente;",
            "TRUNCATE TABLE gs_osservazione;",
            "TRUNCATE TABLE gs_presenza;",
            "TRUNCATE TABLE gs_proposta_voto;",
            "TRUNCATE TABLE gs_provisioning;",
            "TRUNCATE TABLE gs_raggruppamento;",
            "TRUNCATE TABLE gs_raggruppamento_alunno;",
            "TRUNCATE TABLE gs_richiesta;",
            "TRUNCATE TABLE gs_richiesta_colloquio;",
            "TRUNCATE TABLE gs_scansione_oraria;",
            "TRUNCATE TABLE gs_spid;",
            "TRUNCATE TABLE gs_storico_esito;",
            "TRUNCATE TABLE gs_storico_voto;",
            "TRUNCATE TABLE gs_uscita;",
            "TRUNCATE TABLE gs_valutazione;",
            "SET FOREIGN_KEY_CHECKS = 1;"];
          foreach ($sqlCommands as $sql) {
            $connection->executeStatement($sql);
          }
          // pulisce classi da coordinatori e segretari
          $this->em->getRepository(Classe::class)->createQueryBuilder('c')
            ->update()
            ->set('c.coordinatore', ':nessuno')
            ->set('c.segretario', ':nessuno')
            ->setParameter('nessuno', null)
            ->getQuery()
            ->execute();
          // cancella dati annuali alunni
          $this->em->getRepository(Alunno::class)->createQueryBuilder('a')
            ->update()
            ->set('a.autorizzaEntrata', ':no')
            ->set('a.autorizzaUscita', ':no')
            ->set('a.note', ':no')
            ->set('a.frequenzaEstero', ':falso')
            ->setParameter('no', null)
            ->setParameter('falso', 0)
            ->getQuery()
            ->getResult();
          // messaggio finale
          $this->addFlash('success', 'message.tutte_operazioni_ok');
          break;
        case 2: // gestione esiti
          // conserva temporaneamente alunni abilitati ma senza esito finale
          $sqlCommands = [
            "UPDATE gs_utente a ".
            "  SET a.abilitato = 2 ".
            "  WHERE a.ruolo = 'ALU' AND a.abilitato = 1 ".
            "  AND NOT EXISTS (".
            "    SELECT e.id FROM gs_esito e, gs_scrutinio s ".
            "    WHERE e.scrutinio_id = s.id AND e.alunno_id = a.id AND s.periodo='F');"];
          foreach ($sqlCommands as $sql) {
            $connection->executeStatement($sql);
          }
          // scrutini finali
          $scrutini = $this->em->getRepository(Scrutinio::class)->createQueryBuilder('s')
            ->where('s.periodo=:finale')
            ->setParameter('finale', 'F')
            ->getQuery()
            ->getResult();
          foreach ($scrutini as $scrutinio) {
            // non ammessi per assenze
            $noScrutinabili = array_keys(array_filter($scrutinio->getDato('no_scrutinabili') ?? [],
              fn($v) => empty($v['deroga'])));
            if (!empty($noScrutinabili)) {
              $sql = "INSERT INTO gs_storico_esito (creato, modificato, alunno_id, classe, esito, periodo, media, credito, credito_precedente, dati) ".
                "SELECT NOW(), NOW(), a.id, :classe, 'L', 'F', 0, 0, 0, 'a:0:{}' ".
                "FROM gs_utente a ".
                "WHERE a.id IN (:lista) AND a.ruolo = 'ALU' AND a.abilitato = 1;";
              $connection->executeStatement($sql, [
                'classe' => $scrutinio->getClasse()->getAnno().$scrutinio->getClasse()->getSezione().
                  (empty($scrutinio->getClasse()->getGruppo()) ? '' : $scrutinio->getClasse()->getGruppo()),
                'lista' => $noScrutinabili], ['lista' => ArrayParameterType::INTEGER]);
            }
            // anno all'estero
            $estero = $scrutinio->getDato('estero') ?? [];
            if (!empty($estero)) {
              $sql = "INSERT INTO gs_storico_esito (creato, modificato, alunno_id, classe, esito, periodo, media, credito, credito_precedente, dati) ".
                "SELECT NOW(), NOW(), a.id, :classe, 'E', 'F', 0, 0, 0, 'a:0:{}' ".
                "FROM gs_utente a ".
                "WHERE a.id IN (:lista) AND a.ruolo = 'ALU' AND a.abilitato = 1;";
              $connection->executeStatement($sql, [
                'classe' => $scrutinio->getClasse()->getAnno().$scrutinio->getClasse()->getSezione().
                  (empty($scrutinio->getClasse()->getGruppo()) ? '' : $scrutinio->getClasse()->getGruppo()),
                'lista' => $estero], ['lista' => ArrayParameterType::INTEGER]);
            }
            // alunni scrutinati
            $scrutinabili = array_keys($scrutinio->getDato('scrutinabili') ?? []);
            $sql = "INSERT INTO gs_storico_esito (creato, modificato, alunno_id, classe, esito, periodo, media, credito, credito_precedente, dati) ".
              "SELECT NOW(), NOW(), a.id, CONCAT(c.anno, c.sezione, IF((c.gruppo IS NULL OR c.gruppo=''), '', c.gruppo)), e.esito, 'F', e.media, e.credito, e.credito_precedente, e.dati ".
              "FROM gs_esito e, gs_utente a, gs_scrutinio s, gs_classe c ".
              "WHERE e.alunno_id = a.id AND e.scrutinio_id = s.id AND s.classe_id = c.id ".
              "AND a.id IN (:lista) AND a.ruolo = 'ALU' AND a.abilitato = 1 ".
              "AND s.id = :scrutinio ".
              "AND e.esito IN ('A', 'N') ".
              "AND (c.anno != 5 OR e.esito = 'N');";
            $connection->executeStatement($sql, ['lista' => $scrutinabili,
              'scrutinio' => $scrutinio->getId()],
              ['lista' => ArrayParameterType::INTEGER]);
            $sql = "INSERT INTO gs_storico_voto (creato, modificato, storico_esito_id, materia_id, voto, carenze, dati) ".
              "SELECT NOW(), NOW(), (SELECT id FROM gs_storico_esito WHERE alunno_id=a.id), ".
              "  vs.materia_id, vs.unico, '', 'a:0:{}' ".
              "FROM gs_esito e, gs_utente a, gs_scrutinio s, gs_classe c, gs_voto_scrutinio vs ".
              "WHERE e.alunno_id = a.id AND e.scrutinio_id = s.id AND s.classe_id = c.id ".
              "AND vs.scrutinio_id = s.id AND vs.alunno_id = a.id ".
              "AND a.id IN (:lista) AND a.ruolo = 'ALU' AND a.abilitato = 1 ".
              "AND s.id = :scrutinio ".
              "AND e.esito IN ('A', 'N') ".
              "AND (c.anno != 5 OR e.esito = 'N');";
            $connection->executeStatement($sql, ['lista' => $scrutinabili,
              'scrutinio' => $scrutinio->getId()],
              ['lista' => ArrayParameterType::INTEGER]);
          }
          // scrutini sospesi
          $sql = "INSERT INTO gs_storico_esito (creato, modificato, alunno_id, classe, esito, periodo, media, credito, credito_precedente, dati) ".
            "SELECT NOW(), NOW(), a.id, CONCAT(c.anno, c.sezione, IF((c.gruppo IS NULL OR c.gruppo=''), '', c.gruppo)), e.esito, 'G', e.media, e.credito, e.credito_precedente, e.dati ".
            "FROM gs_esito e, gs_utente a, gs_scrutinio s, gs_classe c ".
            "WHERE e.alunno_id = a.id AND e.scrutinio_id = s.id AND s.classe_id = c.id ".
            "AND a.ruolo = 'ALU' AND a.abilitato = 1 ".
            "AND e.esito IN ('A', 'N') AND s.periodo IN ('G', 'R');";
          $connection->executeStatement($sql);
          $sql = "INSERT INTO gs_storico_voto (creato, modificato, storico_esito_id, materia_id, voto, carenze, dati) ".
            "SELECT NOW(), NOW(), (SELECT id FROM gs_storico_esito WHERE alunno_id=a.id), ".
            "  vs.materia_id, vs.unico, '', 'a:0:{}' ".
            "FROM gs_esito e, gs_utente a, gs_scrutinio s, gs_classe c, gs_voto_scrutinio vs ".
            "WHERE e.alunno_id = a.id AND e.scrutinio_id = s.id AND s.classe_id = c.id ".
            "AND vs.scrutinio_id = s.id AND vs.alunno_id = a.id ".
            "AND a.ruolo = 'ALU' AND a.abilitato = 1 ".
            "AND e.esito IN ('A', 'N') AND s.periodo IN ('G', 'R');";
          $connection->executeStatement($sql);
          // esiti scrutini rinviati al nuovo A.S.
          $sql = "INSERT INTO gs_storico_esito (creato, modificato, alunno_id, classe, esito, periodo, media, credito, credito_precedente, dati) ".
            "SELECT NOW(), NOW(), a.id, CONCAT(c.anno, c.sezione, IF((c.gruppo IS NULL OR c.gruppo=''), '', c.gruppo)), e.esito, 'X', e.media, e.credito, ".
            "  e.credito_precedente, e.dati ".
            "FROM gs_esito e, gs_utente a, gs_scrutinio s, gs_classe c, gs_esito e2, gs_scrutinio s2 ".
            "WHERE e.alunno_id = a.id AND e.scrutinio_id = s.id AND s.classe_id = c.id AND e2.alunno_id = a.id AND e2.scrutinio_id = s2.id AND s2.classe_id = c.id ".
            "AND a.ruolo = 'ALU' AND a.abilitato = 1 ".
            "AND e.esito = 'S' AND s.periodo = 'F' AND e2.esito = 'X' AND s2.periodo = 'G' ".
            "AND NOT EXISTS (SELECT ee.id FROM gs_esito ee, gs_scrutinio ss WHERE ee.alunno_id = e.alunno_id AND ee.scrutinio_id=ss.id AND ee.esito IN ('A', 'N') AND ss.periodo = 'R');";
          $connection->executeStatement($sql);
          $sql = "INSERT INTO gs_storico_voto (creato, modificato, storico_esito_id, materia_id, voto, carenze, dati) ".
            "SELECT NOW(), NOW(), (SELECT id FROM gs_storico_esito WHERE alunno_id=a.id), ".
            "  vs.materia_id, vs.unico, '', 'a:0:{}' ".
            "FROM gs_esito e, gs_utente a, gs_scrutinio s, gs_classe c, gs_esito e2, gs_scrutinio s2, gs_voto_scrutinio vs ".
            "WHERE e.alunno_id = a.id AND e.scrutinio_id = s.id AND s.classe_id = c.id AND e2.alunno_id = a.id AND e2.scrutinio_id = s2.id AND s2.classe_id = c.id ".
            "AND vs.scrutinio_id = s.id AND vs.alunno_id = a.id ".
            "AND a.ruolo = 'ALU' AND a.abilitato = 1 ".
            "AND e.esito = 'S' AND s.periodo = 'F' AND e2.esito = 'X' AND s2.periodo = 'G' ".
            "AND NOT EXISTS (SELECT ee.id FROM gs_esito ee, gs_scrutinio ss WHERE ee.alunno_id = e.alunno_id AND ee.scrutinio_id=ss.id AND ee.esito IN ('A', 'N') AND ss.periodo = 'R');";
          $connection->executeStatement($sql);
          // aggiunge dati carenze/debiti per ammessi
          $sql = "UPDATE gs_storico_voto sv ".
            "INNER JOIN gs_storico_esito se ON sv.storico_esito_id = se.id ".
            "INNER JOIN gs_esito e ON e.alunno_id = se.alunno_id ".
            "INNER JOIN gs_scrutinio s ON s.id = e.scrutinio_id ".
            "INNER JOIN gs_voto_scrutinio vs ON vs.scrutinio_id = s.id AND vs.alunno_id = se.alunno_id ".
            "INNER JOIN gs_materia m ON m.id = vs.materia_id AND m.id = sv.materia_id ".
            "SET sv.carenze = vs.debito, sv.dati= 'a:1:{s:7:\"carenza\";s:1:\"C\";}' ".
            "WHERE s.periodo = 'F' AND se.esito IN ('A', 'S') ".
            "AND e.dati LIKE CONCAT('%s:15:\"carenze_materie\";%\"', m.nome_breve, '\"%');";
          $connection->executeStatement($sql);
          $sql = "UPDATE gs_storico_voto sv ".
            "INNER JOIN gs_storico_esito se ON sv.storico_esito_id = se.id ".
            "INNER JOIN gs_esito e ON e.alunno_id = se.alunno_id ".
            "INNER JOIN gs_scrutinio s ON s.id = e.scrutinio_id ".
            "INNER JOIN gs_voto_scrutinio vs ON vs.scrutinio_id = s.id AND vs.alunno_id = se.alunno_id ".
            "INNER JOIN gs_materia m ON m.id = vs.materia_id AND m.id = sv.materia_id ".
            "SET sv.carenze = vs.debito, sv.dati= 'a:1:{s:7:\"carenza\";s:1:\"D\";}' ".
            "WHERE s.periodo = 'F' AND e.esito = 'S' AND se.esito IN ('A', 'S') ".
            "AND vs.unico < 6;";
          $connection->executeStatement($sql);
          // dati scrutini rinviati al nuovo A.S.
          $datiScrutinio = [];
          $scrutini = $this->em->getRepository(Scrutinio::class)->createQueryBuilder('s')
            ->join(Esito::class, 'e', 'WITH', 'e.scrutinio=s.id')
            ->join(StoricoEsito::class, 'se', 'WITH', 'se.alunno=e.alunno')
            ->where('s.periodo=:periodo AND se.periodo=:rinviato')
            ->setParameter('periodo', 'F')
            ->setParameter('rinviato', 'X')
            ->getQuery()
            ->getResult();
          foreach ($scrutini as $scrutinio) {
            $dati = [];
            // dati materie
            $materie = $this->em->getRepository(Materia::class)->createQueryBuilder('m')
              ->select('DISTINCT m.id,m.ordinamento')
              ->join(Cattedra::class, 'c', 'WITH', 'c.materia=m.id')
              ->join('c.classe', 'cl')
              ->where("c.attiva=1 AND c.tipo='N' AND cl.anno=:anno AND cl.sezione=:sezione AND (cl.gruppo=:gruppo OR cl.gruppo='' OR cl.gruppo IS NULL)")
              ->orderBy('m.ordinamento', 'ASC')
              ->setParameter('anno', $scrutinio->getClasse()->getAnno())
              ->setParameter('sezione', $scrutinio->getClasse()->getSezione())
              ->setParameter('gruppo', $scrutinio->getClasse()->getGruppo())
              ->getQuery()
              ->getArrayResult();
            $dati['materie'] = array_map(fn($m) => $m['id'], $materie);
            $condotta = $this->em->getRepository(Materia::class)->findOneByTipo('C');
            $dati['materie'][] = $condotta->getId();
            // dati alunni
            $alunni = $this->em->getRepository(Alunno::class)->createQueryBuilder('a')
              ->join(StoricoEsito::class, 'se', 'WITH', 'se.alunno=a.id')
              ->where('se.periodo=:rinviato AND a.classe=:classe')
              ->setParameter('rinviato', 'X')
              ->setParameter('classe', $scrutinio->getClasse())
              ->getQuery()
              ->getResult();
            foreach ($alunni as $alunno) {
              $dati['alunni'][] = $alunno->getId();
              $dati['religione'][$alunno->getId()] = $alunno->getReligione();
              $dati['bes'][$alunno->getId()] = $alunno->getBes();
              $dati['credito3'][$alunno->getId()] = $alunno->getCredito3();
              $dati['scrutinabili'][$alunno->getId()] = $scrutinio->getDato('scrutinabili')[$alunno->getId()];
              // voti e assenze alunno
              $voti = $this->em->getRepository(VotoScrutinio::class)->createQueryBuilder('vs')
                ->where('vs.scrutinio=:scrutinio AND vs.alunno=:alunno')
                ->setParameter('scrutinio', $scrutinio)
                ->setParameter('alunno', $alunno)
                ->getQuery()
                ->getResult();
              foreach ($voti as $voto) {
                $dati['voti'][$alunno->getId()][$voto->getMateria()->getId()]['unico'] = $voto->getUnico();
                $dati['voti'][$alunno->getId()][$voto->getMateria()->getId()]['assenze'] = $voto->getAssenze();
              }
            }
            // dati docenti
            $docenti = $this->em->getRepository(Cattedra::class)->createQueryBuilder('c')
              ->select('d.id,d.cognome,d.nome,d.sesso,c.tipo,m.id AS m_id')
              ->join('c.docente', 'd')
              ->join('c.materia', 'm')
              ->join('c.classe', 'cl')
              ->where("c.attiva=1 AND c.tipo!='P' AND cl.anno=:anno AND cl.sezione=:sezione AND (cl.gruppo=:gruppo OR cl.gruppo='' OR cl.gruppo IS NULL)")
              ->orderBy('d.cognome,d.nome,m.ordinamento', 'ASC')
              ->setParameter('anno', $scrutinio->getClasse()->getAnno())
              ->setParameter('sezione', $scrutinio->getClasse()->getSezione())
              ->setParameter('gruppo', $scrutinio->getClasse()->getGruppo())
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
          // svuota tabelle non più necessarie
          $connection = $this->em->getConnection();
          $sqlCommands = [
            "SET FOREIGN_KEY_CHECKS = 0;",
            "TRUNCATE TABLE gs_cattedra;",
            "TRUNCATE TABLE gs_esito;",
            "TRUNCATE TABLE gs_scrutinio;",
            "TRUNCATE TABLE gs_voto_scrutinio;",
            "SET FOREIGN_KEY_CHECKS = 1;"];
          foreach ($sqlCommands as $sql) {
            $connection->executeStatement($sql);
          }
          // aggiunge scrutini rinviati
          foreach ($datiScrutinio as $dati) {
            $classe = $this->em->getRepository(Classe::class)->find($dati['classe']);
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
              ->setData(new DateTime('today'))
              ->setDataProposte(new DateTime('today'))
              ->setPeriodo('X')
              ->setArgomenti($argomenti)
              ->setStruttura($struttura);
            $this->em->persist($defScrutinio);
          }
          $this->em->flush();
          // gestione alunni promossi
          $sql = "UPDATE gs_utente a ".
            "INNER JOIN gs_storico_esito se ON se.alunno_id = a.id ".
            "INNER JOIN gs_classe c ON c.id = a.classe_id ".
            "SET a.classe_id = (SELECT id FROM gs_classe WHERE anno = c.anno + 1 AND sezione = c.sezione AND ((c.gruppo IS NULL AND gruppo IS NULL) OR (c.gruppo IS NOT NULL AND c.gruppo = gruppo))) ".
            "WHERE a.ruolo = 'ALU' AND se.esito IN ('A', 'E');";
          $connection->executeStatement($sql);
          // svuota directory temp
          $finder->files()->in($path.'/tmp')->depth('== 0')->notName('.gitkeep');
          $fs->remove($finder);
          // elenco alunni in uscita
          $alunni = $this->em->getRepository(Alunno::class)->createQueryBuilder('a')
            ->leftJoin(StoricoEsito::class, 'se', 'WITH', 'se.alunno=a.id')
            ->where('a.abilitato=1 AND se.id IS NULL')
            ->getQuery()
            ->getResult();
          // crea file CSV per info alunni in uscita
          $fh = fopen($path.'/tmp/alunni_in_uscita.csv', 'w');
          $dati = ['username', 'email', 'classe'];
          fputcsv($fh, $dati);
          foreach($alunni as $alunno) {
            $dati = [$alunno->getUsername(), $alunno->getEmail(), $alunno->getClasse() ?? '--'];
            fputcsv($fh, $dati);
          }
          fclose($fh);
          // disabilita alunni/genitori in uscita
          $sqlCommands = [
            "UPDATE gs_utente a ".
            "  SET a.abilitato = 0 ".
            "  WHERE a.ruolo = 'ALU' AND a.abilitato = 1 ".
            "  AND NOT EXISTS (SELECT id FROM gs_storico_esito WHERE alunno_id = a.id);",
            "UPDATE gs_utente g ".
            "  INNER JOIN gs_utente a ON a.id = g.alunno_id ".
            "  SET g.abilitato = 0, g.alunno_id = null ".
            "  WHERE g.ruolo = 'GEN' AND a.ruolo = 'ALU' AND a.abilitato = 0;"];
          foreach ($sqlCommands as $sql) {
            $connection->executeStatement($sql);
          }
          // evita che vengano cancellati alunni abilitati ma senza esito finale
          $sqlCommands = [
            "UPDATE gs_utente a ".
            "  SET a.abilitato = 1 ".
            "  WHERE a.ruolo = 'ALU' AND a.abilitato = 2 "];
          foreach ($sqlCommands as $sql) {
            $connection->executeStatement($sql);
          }
          // messaggio finale
          $this->addFlash('success', 'message.tutte_operazioni_ok');
          break;
        case 3: // gestione circolari
          // crea nuova directory
          $fs->mkdir($path.'/upload/circolari/'.$info['vecchioAnno'], 0770);
          // legge circolari pubblicate prima del 1/9 e non già modificate
          $circolari = $this->em->getRepository(Circolare::class)->createQueryBuilder('c')
            ->where('c.anno=:anno AND c.pubblicata=:si AND c.data<:inizio AND c.documento NOT LIKE :modificato')
            ->setParameter('anno', $info['vecchioAnno'])
            ->setParameter('si', 1)
            ->setParameter('inizio', $info['nuovoAnno'].'-09-01')
            ->setParameter('modificato', $info['vecchioAnno'].'/%')
            ->getQuery()
            ->getResult();
          // modifica path e sposta file
          foreach ($circolari as $circolare) {
            // sposta file documento
            $file = $path.'/upload/circolari/'.$circolare->getDocumento();
            $fs->rename($file,
              $path.'/upload/circolari/'.$info['vecchioAnno'].'/'.$circolare->getDocumento());
            // modifica path allegati
            $allegati = $circolare->getAllegati();
            $nuoviAllegati = [];
            foreach ($allegati as $allegato) {
              $file = $path.'/upload/circolari/'.$allegato;
              $nuoviAllegati[] = $info['vecchioAnno'].'/'.$allegato;
              $fs->rename($file, $path.'/upload/circolari/'.$info['vecchioAnno'].'/'.$allegato);
            }
            // modifica path su db
            $this->em->getRepository(Circolare::class)->createQueryBuilder('c')
            ->update()
            ->set('c.documento', "CONCAT(:anno,'/',c.documento)")
            ->set('c.allegati', ':allegati')
            ->where('c.id=:id')
            ->setParameter('anno', $info['vecchioAnno'])
            ->setParameter('allegati', serialize($nuoviAllegati))
            ->setParameter('id', $circolare->getId())
            ->getQuery()
            ->execute();
          }
          // controlla presenza di circolari dal 1/9 in poi
          $nuoveCircolari = $this->em->getRepository(Circolare::class)->createQueryBuilder('c')
            ->where('c.anno=:anno AND c.pubblicata=:si AND c.data>=:inizio')
            ->setParameter('anno', $info['vecchioAnno'])
            ->setParameter('si', 1)
            ->setParameter('inizio', $info['nuovoAnno'].'-09-01')
            ->orderBy('c.numero', 'ASC')
            ->getQuery()
            ->getResult();
          // circolari per il nuovo A.S.
          $num = 1;
          $dati['sede'] = [];
          $dati['utente'] = [];
          foreach ($nuoveCircolari as $circolare) {
            // nuova numerazione
            $circolare->setNumero($num)->setAnno($info['nuovoAnno']);
            $num++;
            // conserva dati sedi per nuove circolari
            foreach ($circolare->getSedi() as $sede) {
              $dati['sede'][] = ['circolare' => $circolare->getId(), 'sede' => $sede->getId()];
            }
            // conserva dati utenti per nuove circolari
            $utenti = $this->em->getRepository(CircolareUtente::class)->createQueryBuilder('cu')
              ->select('(cu.circolare) AS circolare,(cu.utente) AS utente,cu.letta,cu.confermata')
              ->join('cu.utente', 'u')
              ->where('cu.circolare=:circolare AND u.abilitato=1')
              ->setParameter('circolare', $circolare->getId())
              ->getQuery()
              ->getScalarResult();
            $dati['utente'] = array_merge($dati['utente'], $utenti);
          }
          $this->em->flush();
          // svuota tabelle dati destinatari
          $sqlCommands = [
            "SET FOREIGN_KEY_CHECKS = 0;",
            "TRUNCATE TABLE gs_circolare_classe;",
            "TRUNCATE TABLE gs_circolare_sede;",
            "TRUNCATE TABLE gs_circolare_utente;",
            "SET FOREIGN_KEY_CHECKS = 1;"];
          foreach ($sqlCommands as $sql) {
            $connection->executeStatement($sql);
          }
          // riscrive dati sede per nuove circolari
          $sql = "INSERT INTO gs_circolare_sede (circolare_id, sede_id) ".
            "VALUES (:circolare, :sede);";
          foreach ($dati['sede'] as $sede) {
            $connection->executeStatement($sql, $sede);
          }
          // riscrive dati utenti per nuove circolari
          $sql = "INSERT INTO gs_circolare_utente (creato, modificato, circolare_id, utente_id, letta, confermata) ".
            "VALUES (NOW(), NOW(), :circolare, :utente, :letta, :confermata);";
          foreach ($dati['utente'] as $utente) {
            $connection->executeStatement($sql, $utente);
          }
          // svuota directory di archiviazione circolari
          $fs->remove($path.'/archivio/circolari');
          $fs->appendToFile($path.'/archivio/circolari/.gitkeep', '');
          // messaggio finale
          $this->addFlash('success', 'message.tutte_operazioni_ok');
          break;
        case 4: // gestione avvisi
          // crea nuova directory
          $fs->mkdir($path.'/upload/avvisi/'.$info['vecchioAnno'], 0770);
          // legge avvisi prima del 1/9 e non già modificati
          $avvisi = $this->em->getRepository(Avviso::class)->createQueryBuilder('a')
            ->where("a.anno=0 AND a.data<:inizio AND a.tipo IN ('C', 'A')")
            ->setParameter('inizio', $info['nuovoAnno'].'-09-01')
            ->getQuery()
            ->getResult();
          // modifica path e sposta file
          foreach ($avvisi as $avviso) {
            // modifica path allegati
            $allegati = $avviso->getAllegati();
            $nuoviAllegati = [];
            foreach ($allegati as $allegato) {
              $file = $path.'/upload/avvisi/'.$allegato;
              $nuoviAllegati[] = $info['vecchioAnno'].'/'.$allegato;
              $fs->rename($file, $path.'/upload/avvisi/'.$info['vecchioAnno'].'/'.$allegato);
            }
            // modifica path su db
            $this->em->getRepository(Avviso::class)->createQueryBuilder('a')
              ->update()
              ->set('a.anno', ':anno')
              ->set('a.allegati', ':allegati')
              ->where('a.id=:id')
              ->setParameter('anno', $info['vecchioAnno'])
              ->setParameter('allegati', serialize($nuoviAllegati))
              ->setParameter('id', $avviso->getId())
              ->getQuery()
              ->execute();
          }
          // controlla presenza di avvisi dal 1/9 (esclusi quelli su cattedre che sono azzerate)
          $nuoviAvvisi = $this->em->getRepository(Avviso::class)->createQueryBuilder('a')
            ->where('a.data>=:inizio AND a.cattedra IS NULL')
            ->setParameter('inizio', $info['nuovoAnno'].'-09-01')
            ->getQuery()
            ->getResult();
          // avvisi per il nuovo A.S.
          $nuoviFile = [];
          $dati['sede'] = [];
          $dati['utente'] = [];
          foreach ($nuoviAvvisi as $avviso) {
            // conserva allegati
            $nuoviFile = array_merge($nuoviFile, $avviso->getAllegati());
            // conserva dati sedi per nuovi avvisi
            foreach ($avviso->getSedi() as $sede) {
              $dati['sede'][] = ['avviso' => $avviso->getId(), 'sede' => $sede->getId()];
            }
            // conserva dati utenti per nuovi avvisi
            $utenti = $this->em->getRepository(AvvisoUtente::class)->createQueryBuilder('au')
              ->select('(au.avviso) AS avviso,(au.utente) AS utente,au.letto')
              ->join('au.utente', 'u')
              ->where('au.avviso=:avviso AND u.abilitato=1')
              ->setParameter('avviso', $avviso->getId())
              ->getQuery()
              ->getScalarResult();
            $dati['utente'] = array_merge($dati['utente'], $utenti);
          }
          // svuota tabelle dati destinatari
          $sqlCommands = [
            "SET FOREIGN_KEY_CHECKS = 0;",
            "TRUNCATE TABLE gs_avviso_classe;",
            "TRUNCATE TABLE gs_avviso_sede;",
            "TRUNCATE TABLE gs_avviso_utente;",
            "SET FOREIGN_KEY_CHECKS = 1;"];
          foreach ($sqlCommands as $sql) {
            $connection->executeStatement($sql);
          }
          // cancella vecchi avvisi
          $this->em->getRepository(Avviso::class)->createQueryBuilder('a')
            ->delete()
            ->where('(a.data<:data AND a.anno=0) OR (a.data>=:data AND a.cattedra IS NOT NULL)')
            ->setParameter('data', $info['nuovoAnno'].'-09-01')
            ->getQuery()
            ->execute();
          // riscrive dati sede per nuovi avvisi
          $sql = "INSERT INTO gs_avviso_sede (avviso_id, sede_id) ".
            "VALUES (:avviso, :sede);";
          foreach ($dati['sede'] as $sede) {
            $connection->executeStatement($sql, $sede);
          }
          // riscrive dati utenti per nuovi avvisi
          $sql = "INSERT INTO gs_avviso_utente (creato, modificato, avviso_id, utente_id, letto) ".
            "VALUES (NOW(), NOW(), :avviso, :utente, :letto);";
          foreach ($dati['utente'] as $utente) {
            $connection->executeStatement($sql, $utente);
          }
          // cancella vecchi allegati
          $finder->files()->in($path.'/upload/avvisi')->depth('== 0')->notName($nuoviFile);
          $fs->remove($finder);
          // sostituisce docente disabilitato
          $preside = $this->em->getRepository(Preside::class)->findOneBy([]);
          $sql = "UPDATE gs_avviso a ".
            "INNER JOIN gs_utente d ON d.id = a.docente_id ".
            "SET a.docente_id = :preside ".
            "WHERE d.abilitato = 0;";
          $connection->executeStatement($sql, ['preside' => $preside->getId()]);
          // messaggio finale
          $this->addFlash('success', 'message.tutte_operazioni_ok');
          break;
        case 5: // gestione documenti
          // directory da svuotare
          $finder->in($path.'/upload/documenti')->notName('.gitkeep');
          $fs->remove($finder);
          // crea nuova directory
          $fs->mkdir($path.'/upload/documenti/riservato', 0770);
          $fs->mkdir($path.'/upload/documenti/'.$info['vecchioAnno'], 0770);
          $fs->mkdir($path.'/upload/documenti/'.$info['vecchioAnno'].'/riservato', 0770);
          // gestione documenti BES: alunni abilitati
          $documenti = $this->em->getRepository(Documento::class)->createQueryBuilder('d')
            ->select('d as doc, se.classe')
            ->join('d.alunno', 'a')
            ->join(StoricoEsito::class, 'se', 'WITH', 'se.alunno = a.id')
            ->where('d.tipo IN (:tipi)')
            ->setParameter('tipi', ['B', 'D', 'H', 'C'])
            ->getQuery()
            ->getResult();
          foreach ($documenti as $documento) {
            // vecchio percorso
            $file = $documento['doc']->getAllegati()[0]->getFile().'.'.
              $documento['doc']->getAllegati()[0]->getEstensione();
            $percorso1 = $documento['classe'].'/riservato/';
            // rimuove dati della classe
            $documento['doc']->setClasse(null);
            $documento['doc']->getListaDestinatari()->setFiltroDocenti([0]);
            if ($fs->exists($path.'/archivio/classi/'.$percorso1.$file)) {
              // sposta documento
              $nomefile = md5(uniqid()).'-'.random_int(1, 1000);
              $fs->rename($path.'/archivio/classi/'.$percorso1.$file,
                $path.'/upload/documenti/riservato/'.$nomefile.'.'.$documento['doc']->getAllegati()[0]->getEstensione());
              $documento['doc']->getAllegati()[0]->setFile($nomefile);
            } else {
              // cerca eventuale cambio sezione
              $trasferito = $this->em->getRepository(CambioClasse::class)->createQueryBuilder('cc')
                ->join('cc.alunno', 'a')
                ->where('a.id=:alunno AND cc.classe IS NOT NULL')
                ->setParameter('alunno', $documento['doc']->getAlunno())
                ->getQuery()
                ->getOneOrNullResult();
              if ($trasferito) {
                $percorso1 = $trasferito->getClasse()->getAnno().$trasferito->getClasse()->getSezione().
                  $trasferito->getClasse()->getGruppo().'/riservato/';
                if ($fs->exists($path.'/archivio/classi/'.$percorso1.$file)) {
                  // sposta documento
                  $nomefile = md5(uniqid()).'-'.random_int(1, 1000);
                  $fs->rename($path.'/archivio/classi/'.$percorso1.$file,
                    $path.'/upload/documenti/riservato/'.$nomefile.'.'.$documento['doc']->getAllegati()[0]->getEstensione());
                  $documento['doc']->getAllegati()[0]->setFile($nomefile);
                } else {
                  // segna per la cancellazione
                  $documento['doc']->setTipo('*');
                  $documento['doc']->setAlunno(null);
                }
              } else {
                // segna per la cancellazione
                $documento['doc']->setTipo('*');
                $documento['doc']->setAlunno(null);
              }
            }
          }
          $this->em->flush();
          // gestione documenti BES: altri alunni
          $documenti = $this->em->getRepository(Documento::class)->createQueryBuilder('d')
            ->join('d.alunno', 'a')
            ->leftJoin(StoricoEsito::class, 'se', 'WITH', 'se.alunno = a.id')
            ->where('d.tipo IN (:tipi) AND se.id IS NULL')
            ->setParameter('tipi', ['B', 'D', 'H', 'C'])
            ->getQuery()
            ->getResult();
          foreach ($documenti as $documento) {
            // vecchio percorso
            $file = $documento->getAllegati()[0]->getFile().'.'.$documento->getAllegati()[0]->getEstensione();
            $percorso1 = '/riservato/';
            if ($documento->getAlunno()->getClasse()) {
              // alunno abilitato
              $percorso1 = $documento->getAlunno()->getClasse()->getAnno().$documento->getAlunno()->getClasse()->getSezione().
                $documento->getAlunno()->getClasse()->getGruppo().'/riservato/';
            } else {
              // alunno trasferito
              $trasferito = $this->em->getRepository(CambioClasse::class)->createQueryBuilder('cc')
                ->join('cc.alunno', 'a')
                ->where('a.id=:alunno AND cc.classe IS NOT NULL')
                ->setParameter('alunno', $documento->getAlunno())
                ->getQuery()
                ->getOneOrNullResult();
              if ($trasferito) {
                $percorso1 = $trasferito->getClasse()->getAnno().$trasferito->getClasse()->getSezione().
                  $trasferito->getClasse()->getGruppo().'/riservato/';
              }
            }
            // rimuove dati della classe
            $documento->setClasse(null);
            $documento->getListaDestinatari()->setFiltroDocenti([0]);
            if ($fs->exists($path.'/archivio/classi/'.$percorso1.$file)) {
              // sposta documento
              $nomefile = md5(uniqid()).'-'.random_int(1, 1000);
              $fs->rename($path.'/archivio/classi/'.$percorso1.$file,
                $path.'/upload/documenti/'.$info['vecchioAnno'].'/riservato/'.$nomefile.'.'.$documento->getAllegati()[0]->getEstensione());
              $documento->getAllegati()[0]->setFile($nomefile);
              $documento->setStato('A');
              $documento->setAnno($info['vecchioAnno']);
              $documento->setTitolo($documento->getAlunno()->getCognome().' '.$documento->getAlunno()->getNome().
                ' - C.F. '.$documento->getAlunno()->getCodiceFiscale());
            } else {
              // segna per la cancellazione
              $documento->setTipo('*');
            }
            // elimina il riferimento all'alunno disabilitato
            $documento->setAlunno(null);
          }
          $this->em->flush();
          // cancella dati documenti
          $sqlCommands = [
            "SET FOREIGN_KEY_CHECKS = 0;",
            "TRUNCATE TABLE gs_lista_destinatari_classe;",
            "TRUNCATE TABLE gs_lista_destinatari_sede;",
            "TRUNCATE TABLE gs_lista_destinatari_utente;",
            "TRUNCATE TABLE gs_cambio_classe;",
            "SET FOREIGN_KEY_CHECKS = 1;",
            "DELETE df FROM gs_documento_file df ".
            "  INNER JOIN gs_documento d ON d.id = df.documento_id ".
            "  WHERE d.tipo NOT IN ('B', 'D', 'H', 'C') OR d.tipo='*';",
            "DELETE d FROM gs_documento d ".
            "  WHERE NOT EXISTS (SELECT file_id FROM gs_documento_file WHERE documento_id = d.id);",
            "DELETE f FROM gs_file f ".
            "  WHERE NOT EXISTS (SELECT documento_id FROM gs_documento_file WHERE file_id = f.id);",
            "DELETE ld FROM gs_lista_destinatari ld ".
            "  WHERE NOT EXISTS (SELECT id FROM gs_documento WHERE lista_destinatari_id = ld.id);"];
          foreach ($sqlCommands as $sql) {
            $connection->executeStatement($sql);
          }
          // sostituisce docente disabilitato
          $preside = $this->em->getRepository(Preside::class)->findOneBy([]);
          $sql = "UPDATE gs_documento doc ".
            "INNER JOIN gs_utente d ON d.id = doc.docente_id ".
            "SET doc.docente_id = :preside ".
            "WHERE d.abilitato = 0;";
          $connection->executeStatement($sql, ['preside' => $preside->getId()]);
          // svuota archivio documenti di classe
          $finder = new Finder();
          $finder->in($path.'/archivio/classi')->notName('.gitkeep');
          $fs->remove($finder);
          // messaggio finale
          $this->addFlash('success', 'message.tutte_operazioni_ok');
          break;
        case 6: // gestione documenti scrutini
          // svuota storico
          $fs->remove($path.'/archivio/scrutini/storico');
          $fs->mkdir($path.'/archivio/scrutini/storico', 0770);
          // sposta documenti in storico
          $finder = new Finder();
          $finder->directories()->in($path.'/archivio/scrutini')->depth('== 0')
            ->name(['finale', 'giudizio-sospeso', 'rinviato']);
          foreach ($finder as $dir) {
            $finder2 = new Finder();
            $finder2->files()->in($dir->getPathname())->depth('== 1')
              ->name(['*-certificazioni.pdf', '*-riepilogo-voti.pdf', '*-verbale.pdf']);
            foreach ($finder2 as $file) {
              $classe = $file->getPathInfo()->getFilename();
              $fs->mkdir($path.'/archivio/scrutini/storico/'.$classe, 0770);
              $fs->rename($file, $path.'/archivio/scrutini/storico/'.$classe.'/'.$file->getFilename());
            }
          }
          // svuota archivio scrutinio
          $finder = new Finder();
          $finder->directories()->in($path.'/archivio/scrutini')->depth('== 0')->exclude('storico');
          foreach ($finder as $dir) {
            $finder2 = new Finder();
            $finder2->directories()->in($dir->getPathname())->depth('== 0');
            foreach ($finder2 as $dir2) {
              $fs->remove($dir2);
            }
            $fs->remove($dir);
          }
          // messaggio finale
          $this->addFlash('success', 'message.tutte_operazioni_ok');
          break;
        case 7: // rimozione utenti
          // rimuove utenti disabilitati
          $sqlCommands = [
            "DELETE u FROM gs_utente u ".
            "  WHERE u.abilitato = 0;"];
          foreach ($sqlCommands as $sql) {
            $connection->executeStatement($sql);
          }
          // messaggio finale
          $this->addFlash('success', 'message.tutte_operazioni_ok');
          break;
        case 8: // pulizia finale
          // svuota archivio registri
          $fs->remove($path.'/archivio/registri');
          $fs->appendToFile($path.'/archivio/registri/.gitkeep', '');
          // parametro nuovo anno
          $this->em->getRepository(Configurazione::class)->setParametro('anno_scolastico',
            $info['nuovoAnno'].'/'.(1 + $info['nuovoAnno']));
          // cancella cache
          $commands = [
            new ArrayInput(['command' => 'cache:clear', '--no-warmup' => true, '-n' => true, '-q' => true]),
            new ArrayInput(['command' => 'doctrine:cache:clear-query', '-n' => true, '-q' => true]),
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
          if ($status == 0) {
            // messaggio finale
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
   */
  #[Route(path: '/sistema/archivia/', name: 'sistema_archivia', methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_AMMINISTRATORE')]
  public function archivia(Request $request, TranslatorInterface $trans,
                           ArchiviazioneUtil $arch): Response {
    // init
    $dati = [];
    $info = [];
    $docenti = $this->em->getRepository(Docente::class)->createQueryBuilder('d')
      ->join(Cattedra::class, 'c', 'WITH', 'c.docente=d.id')
      ->join('c.materia', 'm')
      ->where('m.tipo IN (:tipi)')
      ->orderBy('d.cognome,d.nome', 'ASC')
      ->setParameter('tipi', ['N', 'R', 'E'])
      ->getQuery()
      ->getResult();
    $listaDocenti = [];
    foreach ($docenti as $docente) {
      $nome = $docente->getCognome().' '.$docente->getNome().' ('.
        $docente->getUsername().')';
      $listaDocenti[$nome] = $docente;
    }
    $sostegno = $this->em->getRepository(Docente::class)->createQueryBuilder('d')
      ->join(Cattedra::class, 'c', 'WITH', 'c.docente=d.id')
      ->join('c.materia', 'm')
      ->where('m.tipo=:tipo')
      ->orderBy('d.cognome,d.nome', 'ASC')
      ->setParameter('tipo', 'S')
      ->getQuery()
      ->getResult();
    $listaSostegno = [];
    foreach ($sostegno as $docente) {
      $nome = $docente->getCognome().' '.$docente->getNome().' ('.
        $docente->getUsername().')';
      $listaSostegno[$nome] = $docente;
    }
    $classi = $this->em->getRepository(Classe::class)->createQueryBuilder('c')
      ->join('c.sede', 's')
      ->where("c.gruppo IS NULL OR c.gruppo=''")
      ->orderBy('s.ordinamento,c.anno,c.sezione,c.gruppo')
      ->getQuery()
      ->getResult();
    $listaClassi = [];
    foreach ($classi as $classe) {
      $nome = $classe->getAnno().$classe->getSezione().' '.$classe->getCorso()->getNomeBreve().
        ' - '.$classe->getSede()->getNomeBreve();
      $listaClassi[$nome] = $classe;
    }
    $listaCircolari = $this->em->getRepository(Circolare::class)->createQueryBuilder('c')
      ->where('c.pubblicata=:si AND c.anno=:anno')
      ->orderBy('c.numero', 'ASC')
      ->setParameter('si', 1)
      ->setParameter('anno', (int) substr((string) $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_scolastico'), 0, 4))
      ->getQuery()
      ->getResult();
    // form
    $form = $this->createForm(ModuloType::class, null, ['form_mode' => 'archivia',
      'values' => [$listaDocenti, $listaSostegno, $listaClassi, $listaCircolari]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // assicura che lo script non sia interrotto
      ini_set('max_execution_time', 0);
      // aumenta il limite di memoria\
      ini_set('memory_limit', '1G');
      // form inviato
      $tipo = $form->get('tipo')->getData();
      $selezione = $form->get('selezione')->getData();
      $docente = $form->get('docente')->getData();
      $sostegno = $form->get('sostegno')->getData();
      $classe = $form->get('classe')->getData();
      $circolare = $form->get('circolare')->getData();
      // controllo errori
      if ($selezione != 'T') {
        if ($tipo == 'D' && !$docente) {
          // docente non definito
          $form->addError(new FormError($trans->trans('exception.no_docente')));
        }
        if ($tipo == 'S' && !$sostegno) {
          // docente di sostegno non definito
          $form->addError(new FormError($trans->trans('exception.no_sostegno')));
        }
        if ($tipo == 'C' && !$classe) {
          // classe non definita
          $form->addError(new FormError($trans->trans('exception.no_classe')));
        }
        if ($tipo == 'U' && !$classe) {
          // classe non definita
          $form->addError(new FormError($trans->trans('exception.no_classe')));
        }
        if ($tipo == 'R' && !$circolare) {
          // circolare non definita
          $form->addError(new FormError($trans->trans('exception.no_circolare')));
        }
      }
      if ($form->isValid()) {
        // no errori
        switch ($tipo) {
          case 'D':   // registro docenti
            if ($selezione == 'S') {
              // crea registro
              $arch->registroDocente($docente);
            } elseif ($selezione == 'T') {
              // crea tutti i registri
              $arch->tuttiRegistriDocente($listaDocenti);
            } else {
              $key = $docente->getCognome().' '.$docente->getNome().' ('.$docente->getUsername().')';
              $pos = array_search($key, array_keys($listaDocenti), true);
              $lista = array_slice($listaDocenti, $pos, null, true);
              $arch->tuttiRegistriDocente($lista);
            }
            break;
          case 'S':   // registro sostegno
            if ($selezione == 'S') {
              // crea registro
              $arch->registroSostegno($sostegno);
            } elseif ($selezione == 'T') {
              // crea tutti i registri
              $arch->tuttiRegistriSostegno($listaSostegno);
            } else {
              $key = $sostegno->getCognome().' '.$sostegno->getNome().' ('.$sostegno->getUsername().')';
              $pos = array_search($key, array_keys($listaSostegno), true);
              $lista = array_slice($listaSostegno, $pos, null, true);
              $arch->tuttiRegistriSostegno($lista);
            }
            break;
          case 'C':   // registro classe
            if ($selezione == 'S') {
              // crea registro
              $arch->registroClasse($classe);
            } elseif ($selezione == 'T') {
              // crea tutti i registri
              $arch->tuttiRegistriClasse($listaClassi);
            } else {
              $key = $classe->getAnno().$classe->getSezione().' '.$classe->getCorso()->getNomeBreve().
                ' - '.$classe->getSede()->getNomeBreve();
              $pos = array_search($key, array_keys($listaClassi));
              $lista = array_slice($listaClassi, $pos, null, true);
              $arch->tuttiRegistriClasse($lista);
            }
            break;
          case 'U':   // documenti scrutinio
            if ($selezione == 'S') {
              // crea registro
              $arch->scrutinioClasse($classe);
            } elseif ($selezione == 'T') {
              // crea tutti i documenti
              $arch->tuttiScrutiniClasse($listaClassi);
            } else {
              $key = $classe->getAnno().$classe->getSezione().' '.$classe->getCorso()->getNomeBreve().
                ' - '.$classe->getSede()->getNomeBreve();
              $pos = array_search($key, array_keys($listaClassi));
              $lista = array_slice($listaClassi, $pos, null, true);
              $arch->tuttiScrutiniClasse($lista);
            }
            break;
          case 'R':   // archivio circolari
            if ($selezione == 'S') {
              // crea registro
              $numCircolari = $arch->documentoCircolare($circolare);
            } elseif ($selezione == 'T') {
              // crea tutti i documenti
              $numCircolari = $arch->tuttiDocumentiCircolari($listaCircolari);
            } else {
              $id = $circolare->getId();
              $pos = array_search($id, array_map(fn($o) => $o->getId(), $listaCircolari), true);
              $lista = array_slice($listaCircolari, $pos);
              // crea sottoinsieme dei documenti
              $numCircolari = $arch->tuttiDocumentiCircolari($lista);
            }
            if ($numCircolari > 0) {
              // circolari create
              $this->addFlash('success', 'Sono state archiviate '.$numCircolari.' circolari.');
            } else {
              // nessuna circolare archiviata
              $this->addFlash('warning', 'Non è stata archiviata nessuna circolare.');
            }
            break;
        }
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
   */
  #[Route(path: '/sistema/manutenzione/cache/', name: 'sistema_manutenzione_cache', methods: ['GET'])]
  #[IsGranted('ROLE_AMMINISTRATORE')]
  public function manutenzioneCache(TranslatorInterface $trans, KernelInterface $kernel): Response {
    // assicura che lo script non sia interrotto
    ini_set('max_execution_time', 0);
    // comandi per la pulizia della cache del database
    $commands = [
      new ArrayInput(['command' => 'cache:clear', '--no-warmup' => true, '-n' => true, '-q' => true]),
      new ArrayInput(['command' => 'doctrine:cache:clear-result', '--flush' => true, '-n' => true, '-q' => true]),
      new ArrayInput(['command' => 'doctrine:cache:clear-metadata', '--flush' => true, '-n' => true, '-q' => true]),
      new ArrayInput(['command' => 'doctrine:cache:clear-query', '-n' => true, '-q' => true]),
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
   */
  #[Route(path: '/sistema/manutenzione/logout/', name: 'sistema_manutenzione_logout', methods: ['GET'])]
  #[IsGranted('ROLE_AMMINISTRATORE')]
  public function manutenzioneLogout(Request $request): Response {
    // assicura che lo script non sia interrotto
    ini_set('max_execution_time', 0);
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
   */
  #[Route(path: '/sistema/manutenzione/log/', name: 'sistema_manutenzione_log', methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_AMMINISTRATORE')]
  public function manutenzioneLog(Request $request): Response {
    // init
    $dati = [];
    $info = [];
    // imposta data e ora corrente
    $data = new DateTime('today');
    $ora = new DateTime('now');
    // form
    $form = $this->createForm(ModuloType::class, null, ['form_mode' => 'log',
      'return_url' => $this->generateUrl('sistema_manutenzione'), 'values' => [$data, $ora]]);
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
        mb_strtolower((string) $request->server->get('APP_ENV')).'-'.$dt.'.log';
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
          $response = new Response(implode('', $msgs));
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
   */
  #[Route(path: '/sistema/manutenzione/debug/', name: 'sistema_manutenzione_debug', methods: ['GET'])]
  #[IsGranted('ROLE_AMMINISTRATORE')]
  public function manutenzioneDebug(Request $request, TranslatorInterface $trans,
                                    KernelInterface $kernel): Response {
    // assicura che lo script non sia interrotto
    ini_set('max_execution_time', 0);
    // imposta nuovo livello di log
    $logLevel = ($request->server->get('LOG_LEVEL') == 'warning') ? 'debug' : 'warning';
    // legge .env
    $envPath = $this->getParameter('kernel.project_dir').'/.env';
    $envData = file($envPath);
    // modifica impostazione
    foreach ($envData as $row=>$text) {
      if (str_starts_with($text, 'LOG_LEVEL')) {
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
   * @param int $step Passo della procedura
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/sistema/aggiorna/{step}', name: 'sistema_aggiorna', requirements: ['step' => '\d+'], defaults: ['step' => '0'], methods: ['GET'])]
  #[IsGranted('ROLE_AMMINISTRATORE')]
  public function aggiorna(int $step): Response {
    // inizializza
    $dati = [];
    $info = [];
    // assicura che lo script non sia interrotto
    ini_set('max_execution_time', 0);
    // esegue passi
    $info['step'] = 0;
    $info['prossimo'] = 0;
    switch($step) {
      case 0:   // controlli iniziali
        // legge ultima versione
        $url = 'https://github.com/iisgiua/giuaschool-docs/raw/master/_data/version.yml';
        $pagina = file_get_contents($url);
        preg_match('/^tag:\s*([0-9\.]+)$/m', $pagina, $trovati);
        if (count($trovati) != 2) {
          // errore recupero versione
          $info['tipo'] = 'danger';
          $info['messaggio'] = 'exception.aggiornamento_no_versione';
          break;
        }
        $nuovaVersione = $trovati[1];
        // legge ultima build
        $url = 'https://github.com/iisgiua/giuaschool-docs/raw/master/_data/build.yml';
        $pagina = file_get_contents($url);
        preg_match('/^tag:\s*(.*)$/m', $pagina, $trovati);
        $nuovaBuild = $trovati[1] ?? '0';
        // controlla versione
        $versione = $this->em->getRepository(Configurazione::class)->getParametro('versione', '0');
        $build = $this->em->getRepository(Configurazione::class)->getParametro('versione_build', '0');
        if (version_compare($nuovaVersione, $versione, '<')) {
          // sistema già aggiornato
          $info['tipo'] = 'info';
          $info['messaggio'] = 'message.sistema_aggiornato';
          break;
        } elseif (version_compare($nuovaVersione, $versione, '=')) {
          // controlla build
          if ($nuovaBuild == '0' || $nuovaBuild == $build) {
            $info['tipo'] = 'info';
            $info['messaggio'] = 'message.sistema_aggiornato';
            break;
          }
          // aggiornamento di build
          $dati['versione'] = $nuovaVersione;
          $dati['build'] = $nuovaBuild;
        } else {
          // aggiornamento di versione
          $dati['versione'] = $nuovaVersione;
          $dati['build'] = '0';
        }
        // aggiornamento presente
        if (!extension_loaded('zip')) {
          // zip non supportato
          $info['tipo'] = 'danger';
          $info['messaggio'] = 'exception.aggiornamento_zip_non_presente';
          break;
        }
        // controlla esistenza file
        $file = dirname(__DIR__).'/Install/v'.$nuovaVersione.($dati['build'] == '0' ? '' : '-build').'.ok';
        if (file_exists($file)) {
          // file già scaricato: salta il passo successivo
          if ($dati['build'] == '0') {
            $info['tipo'] = 'success';
            $info['messaggio'] = 'message.aggiornamento_scaricato';
          } else {
            $info['tipo'] = 'warning';
            $info['messaggio'] = 'message.aggiornamento_build_scaricato';
          }
          $info['prossimo'] = 2;
        } else {
          // file da scaricare
          if ($dati['build'] == '0') {
            $info['tipo'] = 'success';
            $info['messaggio'] = 'message.aggiornamento_possibile';
          } else {
            $info['tipo'] = 'warning';
            $info['messaggio'] = 'message.aggiornamento_build_possibile';
          }
          $info['prossimo'] = 1;
        }
        $this->reqstack->getSession()->set('/APP/ROUTE/sistema_aggiorna/versione', $dati['versione']);
        $this->reqstack->getSession()->set('/APP/ROUTE/sistema_aggiorna/build', $dati['build']);
        break;
      case 1:   // scarica file
        $nuovaVersione = $this->reqstack->getSession()->get('/APP/ROUTE/sistema_aggiorna/versione');
        $dati['versione'] = $nuovaVersione;
        $nuovaBuild = $this->reqstack->getSession()->get('/APP/ROUTE/sistema_aggiorna/build');
        $dati['build'] = $nuovaBuild;
        if ($dati['build'] == '0') {
          // aggiornamento di versione
          $url = 'https://github.com/iisgiua/giuaschool/releases/download/v'.$nuovaVersione.
            '/giuaschool-release-v'.$nuovaVersione.'.zip';
          $info['tipo'] = 'success';
          $info['messaggio'] = 'message.aggiornamento_scaricato';
        } else {
          // aggiornamento di build
          $url = 'https://github.com/iisgiua/giuaschool/releases/download/update-v'.$nuovaVersione.
            '/giuaschool-update-v'.$nuovaVersione.'.zip';
          $info['tipo'] = 'warning';
          $info['messaggio'] = 'message.aggiornamento_build_scaricato';
          $nuovaVersione .= '-build';
        }
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
        $info['prossimo'] = 2;
        break;
      case 2:   // installazione
        // salva dati per l'installazione
        $nuovaVersione = $this->reqstack->getSession()->get('/APP/ROUTE/sistema_aggiorna/versione');
        $nuovaBuild = $this->reqstack->getSession()->get('/APP/ROUTE/sistema_aggiorna/build');
        $token = bin2hex(random_bytes(16));
        $contenuto = 'token="'.$token.'"'."\n".
          'version="'.$nuovaVersione.'"'."\n".
          'build="'.$nuovaBuild.'"'."\n";
        file_put_contents(dirname(__DIR__, 2).'/.gs-updating', $contenuto);
        // reindirizza a pagina di installazione
        $urlPath = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? 'https' : 'http').
          '://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
        $urlPath = substr($urlPath, 0, - strlen('/sistema/aggiorna/2'));
        return $this->redirect($urlPath."/install/update.php?token=$token&step=1");
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
   */
  #[Route(path: '/sistema/email', name: 'sistema_email', methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_AMMINISTRATORE')]
  public function email(Request $request, TranslatorInterface $trans, MailerInterface $mailer,
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
    $info['user'] = (str_ends_with($info['server'], 'smtp')) ? ($dsn['user'] ?? '') : '';
    $info['password'] = (str_ends_with($info['server'], 'smtp')) ? ($dsn['pass'] ?? '') : '';
    $info['host'] = $info['server'] == 'smtp' ? ($dsn['host'] ?? '') : '';
    $info['port'] = $info['server'] == 'smtp' ? ($dsn['port'] ?? null) : null;
    $info['email'] = '';
    // form
    $form = $this->createForm(ModuloType::class, null, ['form_mode' => 'email', 'values' => [
      $info['server'], $info['user'], $info['password'], $info['host'], $info['port']]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // legge dati form
      $server = $form->get('server')->getData();
      $user = $form->get('user')->getData();
      $password = $form->get('password')->getData();
      $host = $form->get('host')->getData();
      $port = $form->get('port')->getData();
      $email = $form->get('email')->getData();
      if (empty($this->reqstack->getSession()->get('/APP/ROUTE/sistema_email/invio'))) {
        // controlla errori
        if (!$server || !in_array($server, ['smtp', 'sendmail', 'gmail+smtp', 'php'], true)) {
          $form->addError(new FormError($trans->trans('exception.mailserver_no_server')));
        }
        if (str_ends_with((string) $server, 'smtp') && !$user) {
          $form->addError(new FormError($trans->trans('exception.mailserver_no_user')));
        }
        if (str_ends_with((string) $server, 'smtp') && !$password) {
          $form->addError(new FormError($trans->trans('exception.mailserver_no_password')));
        }
        if ($server == 'smtp' && !$host) {
          $form->addError(new FormError($trans->trans('exception.mailserver_no_host')));
        }
        if ($server == 'smtp' && !$port) {
          $form->addError(new FormError($trans->trans('exception.mailserver_no_port')));
        }
        if (!$email) {
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
            $info['email'] = $email;
            $this->reqstack->getSession()->set('/APP/ROUTE/sistema_email/invio', $email);
          }
        }
      } else {
        // spedisce mail di test
        $text = "Questo è il testo dell'email.\n".
          "La mail è stata spedita dall'applicazione giua@school per verificare il corretto recapito della posta elettronica.\n\n".
          "Allegato:\n - il file di testo della licenza AGPL.\n";
        $html = "<p><strong>Questo è il testo dell'email.</strong></p>".
          "<p><em>La mail è stata spedita dall'applicazione <strong>giua@school</strong> per verificare il corretto recapito della posta elettronica.</em></p>".
          "<p>Allegato:</p><ul><li>il file di testo della licenza AGPL.</li></ul>";
        // invia per email
        $message = (new Email())
          ->from($this->reqstack->getSession()->get('/CONFIG/ISTITUTO/email_notifiche'))
          ->to($email)
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
        } catch (Exception $e) {
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

  /**
   * Configura per l'invio delle notifiche via Telegram.
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param TelegramManager $telegram Gestore delle comunicazioni tramite Telegram
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/sistema/telegram', name: 'sistema_telegram', methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_AMMINISTRATORE')]
  public function telegram(Request $request, TranslatorInterface $trans,
                           TelegramManager $telegram): Response {
    // inizializza
    $dati = [];
    $info = [];
    // legge configurazione
    $info['bot'] = $this->em->getRepository(Configurazione::class)->getParametro('telegram_bot');
    $info['token'] = $this->em->getRepository(Configurazione::class)->getParametro('telegram_token');
    // form
    $form = $this->createForm(ModuloType::class, null, ['form_mode' => 'telegram', 'values' => [
      $info['bot'], $info['token']]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // legge dati form
      $bot = $form->get('bot')->getData() ?? '';
      $token = empty($bot) ? '' : $form->get('token')->getData();
      // controlli
      if (empty($token) && !empty($bot)) {
        // errore
        $form->addError(new FormError($trans->trans('exception.telegram_no_token')));
      }
      if ($form->isValid()) {
        // cancella webhook esistente
        $ris = $telegram->deleteWebhook();
        if (isset($ris['error'])) {
          // errore
          $form->addError(new FormError($trans->trans('exception.telegram_webhook',
            ['errore' => $ris['error']])));
        } else {
          // memorizza dati
          $this->em->getRepository(Configurazione::class)->setParametro('telegram_bot', $bot);
          $this->em->getRepository(Configurazione::class)->setParametro('telegram_token', $token);
          // nuovo webhook
          $ris = $telegram->setWebhook();
          if (isset($ris['error'])) {
            // errore
            $this->em->getRepository(Configurazione::class)->setParametro('telegram_bot', '');
            $this->em->getRepository(Configurazione::class)->setParametro('telegram_token', '');
            $form->addError(new FormError($trans->trans('exception.telegram_webhook',
              ['errore' => $ris['error']])));
          } else {
            // configurazione riuscita
            $this->addFlash('success', 'message.telegram_webhook_ok');
          }
        }
      }
    }
    // mostra la pagina di risposta
    return $this->renderHtml('sistema', 'telegram', $dati, $info, [$form->createView(),
      'message.sistema_telegram']);
  }

}
