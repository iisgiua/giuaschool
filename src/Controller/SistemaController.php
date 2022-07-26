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

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Dotenv\Dotenv;
use Doctrine\Bundle\DoctrineBundle\ConnectionFactory;
use App\Kernel;
use App\Form\ConfigurazioneType;
use App\Form\UtenteType;
use App\Form\ModuloType;
use App\Util\LogHandler;
use App\Util\ArchiviazioneUtil;
use App\Entity\Istituto;
use App\Entity\Sede;
use App\Entity\Corso;
use App\Entity\Materia;
use App\Entity\Classe;
use App\Entity\Preside;
use App\Entity\Staff;
use App\Entity\Docente;
use App\Entity\Ata;
use App\Entity\Alunno;
use App\Entity\Genitore;
use App\Entity\StoricoEsito;
use App\Entity\StoricoVoto;
use App\Entity\Documento;
use App\Entity\Provisioning;
use App\Entity\Scrutinio;
use App\Entity\Configurazione;
use App\Entity\Utente;


/**
 * SistemaController - gestione parametri di sistema e funzioni di utlità
 */
class SistemaController extends BaseController {

  /**
   * Configura la visualizzazione di un banner sulle pagine principali.
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   *
   * @return Response Pagina di risposta
   *
   * @Route("/sistema/banner/", name="sistema_banner",
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function bannerAction(Request $request, EntityManagerInterface $em): Response {
    // init
    $dati = [];
    $info = [];
    // legge parametri
    $banner_login = $em->getRepository('App\Entity\Configurazione')->getParametro('banner_login', '');
    $banner_home = $em->getRepository('App\Entity\Configurazione')->getParametro('banner_home', '');
    // form
    $form = $this->createForm(ConfigurazioneType::class, null, ['formMode' => 'banner',
      'dati' => [$banner_login, $banner_home]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // memorizza i parametri
      $em->getRepository('App\Entity\Configurazione')->setParametro('banner_login',
        $form->get('banner_login')->getData() ? $form->get('banner_login')->getData() : '');
      $em->getRepository('App\Entity\Configurazione')->setParametro('banner_home',
        $form->get('banner_home')->getData() ? $form->get('banner_home')->getData() : '');
    }
    // mostra la pagina di risposta
    return $this->renderHtml('sistema', 'banner', $dati, $info, [$form->createView(), 'message.banner']);
  }

  /**
   * Gestione della modalità manutenzione del registro
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   *
   * @return Response Pagina di risposta
   *
   * @Route("/sistema/manutenzione/", name="sistema_manutenzione",
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function manutenzioneAction(Request $request, EntityManagerInterface $em): Response {
    // init
    $dati = [];
    $info = [];
    // informazioni passate alla pagina
    $info['logLevel'] = $request->server->get('LOG_LEVEL');
    // legge parametri
    $manutenzione_inizio = $em->getRepository('App\Entity\Configurazione')->getParametro('manutenzione_inizio', null);
    $manutenzione_fine = $em->getRepository('App\Entity\Configurazione')->getParametro('manutenzione_fine', null);
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
        $param_inizio = $form->get('data_inizio')->getData()->format('Y-m-d').' '.
          $form->get('ora_inizio')->getData()->format('H:i');
        $param_fine = $form->get('data_fine')->getData()->format('Y-m-d').' '.
          $form->get('ora_fine')->getData()->format('H:i');
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
      $em->getRepository('App\Entity\Configurazione')->setParametro('manutenzione_inizio', $param_inizio);
      $em->getRepository('App\Entity\Configurazione')->setParametro('manutenzione_fine', $param_fine);
    }
    // mostra la pagina di risposta
    return $this->renderHtml('sistema', 'manutenzione', $dati, $info, [$form->createView(), 'message.manutenzione']);
  }

  /**
   * Configurazione dei parametri dell'applicazione
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   *
   * @return Response Pagina di risposta
   *
   * @Route("/sistema/parametri/", name="sistema_parametri",
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function parametriAction(Request $request, EntityManagerInterface $em): Response {
    // init
    $dati = [];
    $info = [];
    // legge parametri
    $parametri = $em->getRepository('App\Entity\Configurazione')->parametriConfigurazione();
    // form
    $form = $this->createForm(ConfigurazioneType::class, null, ['formMode' => 'parametri',
      'dati' => $parametri]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // memorizza modifiche
      $em->flush();
    }
    // mostra la pagina di risposta
    return $this->renderHtml('sistema', 'parametri', $dati, $info, [$form->createView(), 'message.parametri']);
  }

  /**
   * Cambia la password di un utente
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
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
  public function passwordAction(Request $request, EntityManagerInterface $em,
                                 UserPasswordHasherInterface $hasher, TranslatorInterface $trans,
                                 ValidatorInterface $validator, LogHandler $dblogger): Response {
    // init
    $dati = [];
    $info = [];
    // form
    $form = $this->createForm(UtenteType::class, null, ['formMode' => 'password']);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // form inviato
      $username = $form->get('username')->getData();
      $user = $em->getRepository('App\Entity\Utente')->findOneByUsername($username);
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
            $em->persist($provisioning);
          }
          // memorizza password
          $em->flush();
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
   * @param EntityManagerInterface $em Gestore delle entità
   * @param RequestStack $reqstack Gestore dello stack delle variabili globali
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
  public function aliasAction(Request $request, EntityManagerInterface $em, RequestStack $reqstack,
                              TranslatorInterface $trans, LogHandler $dblogger): Response {
    // init
    $dati = [];
    $info = [];
    // form
    $form = $this->createForm(UtenteType::class, null, ['formMode' => 'alias']);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // form inviato
      $username = $form->get('username')->getData();
      $user = $em->getRepository('App\Entity\Utente')->findOneByUsername($username);
      if (!$user || !$user->getAbilitato()) {
        // errore, utente non esiste o non abilitato
        $form->get('username')->addError(new FormError($trans->trans('exception.invalid_user')));
      } else {
        // memorizza dati in sessione
        $reqstack->getSession()->set('/APP/UTENTE/tipo_accesso_reale', $reqstack->getSession()->get('/APP/UTENTE/tipo_accesso'));
        $reqstack->getSession()->set('/APP/UTENTE/ultimo_accesso_reale', $reqstack->getSession()->get('/APP/UTENTE/ultimo_accesso'));
        $reqstack->getSession()->set('/APP/UTENTE/username_reale', $this->getUser()->getUsername());
        $reqstack->getSession()->set('/APP/UTENTE/ruolo_reale', $this->getUser()->getRoles()[0]);
        $reqstack->getSession()->set('/APP/UTENTE/id_reale', $this->getUser()->getId());
        $reqstack->getSession()->set('/APP/UTENTE/ultimo_accesso',
          ($user->getUltimoAccesso() ? $user->getUltimoAccesso()->format('d/m/Y H:i:s') : null));
        $reqstack->getSession()->set('/APP/UTENTE/tipo_accesso', 'alias');
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
   * @param RequestStack $reqstack Gestore dello stack delle variabili globali
   * @param LogHandler $dblogger Gestore dei log su database
   *
   * @return Response Pagina di risposta
   *
   * @Route("/sistema/alias/exit", name="sistema_alias_exit",
   *    methods={"GET"})
   */
  public function aliasExitAction(Request $request, RequestStack $reqstack, LogHandler $dblogger): Response  {
    // log azione
    $dblogger->logAzione('ACCESSO', 'Alias Exit', array(
      'Username' => $this->getUser()->getUsername(),
      'Ruolo' => $this->getUser()->getRoles()[0],
      'Username reale' => $reqstack->getSession()->get('/APP/UTENTE/username_reale'),
      'Ruolo reale' => $reqstack->getSession()->get('/APP/UTENTE/ruolo_reale'),
      'ID reale' => $reqstack->getSession()->get('/APP/UTENTE/id_reale')
      ));
    // ricarica dati in sessione
    $reqstack->getSession()->set('/APP/UTENTE/ultimo_accesso', $reqstack->getSession()->get('/APP/UTENTE/ultimo_accesso_reale'));
    $reqstack->getSession()->set('/APP/UTENTE/tipo_accesso', $reqstack->getSession()->get('/APP/UTENTE/tipo_accesso_reale'));
    $reqstack->getSession()->remove('/APP/UTENTE/tipo_accesso_reale');
    $reqstack->getSession()->remove('/APP/UTENTE/ultimo_accesso_reale');
    $reqstack->getSession()->remove('/APP/UTENTE/username_reale');
    $reqstack->getSession()->remove('/APP/UTENTE/ruolo_reale');
    $reqstack->getSession()->remove('/APP/UTENTE/id_reale');
    // disconnette l'alias in uso e redirect alla home
    return $this->redirectToRoute('login_home', array('reload' => 'yes', '_alias' => '_exit'));
  }

  /**
   * Importa i dati dal precedente A.S.
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param ConnectionFactory $connessioneDB Gestore delle connessioni su database
   *
   * @return Response Pagina di risposta
   *
   * @Route("/sistema/importa/", name="sistema_importa",
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */

  public function importaAction(Request $request, EntityManagerInterface $em,
                                ConnectionFactory $connessioneDB, TranslatorInterface $trans): Response {
    // init
    $dati = [];
    $info = [];
    // form
    $form = $this->createForm(ModuloType::class, null, ['formMode' => 'importa']);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // connessione al database
      $params = $em->getConnection()->getParams();
      unset($params['url']);
      $params['dbname'] = $form->get('database')->getData();
      $conn = $connessioneDB->createConnection($params);
      try {
        $conn->connect();
      } catch (\Exception $e) {
        // errore sul database
        $form->get('database')->addError(new FormError($trans->trans('exception.database_error')));
      }
      // directory dell'Applicazione
      $dir = rtrim($form->get('directory')->getData(), '/').'/FILES/archivio/scrutini/';
      if (!is_dir($dir)) {
        // errore sul percorso principale
        $form->get('directory')->addError(new FormError($trans->trans('exception.directory_error')));
      }
      if ($form->isValid()) {
        // assicura che lo script non sia interrotto
        ini_set('max_execution_time', 0);
        // importa istituto/sedi
        if (in_array('I', $form->get('dati')->getData())) {
          // dati istituto
          $sql = "SELECT * FROM gs_istituto";
          $stmt = $conn->prepare($sql);
          $stmt->execute();
          $istituto_old = $stmt->fetch();
          $istituto = (new Istituto())
            ->setTipo($istituto_old['tipo'])
            ->setTipoSigla($istituto_old['tipo_sigla'])
            ->setNome($istituto_old['nome'])
            ->setNomeBreve($istituto_old['nome_breve'])
            ->setEmail($istituto_old['email'])
            ->setPec($istituto_old['pec'])
            ->setUrlRegistro($istituto_old['url_registro'])
            ->setUrlSito($istituto_old['url_sito'])
            ->setFirmaPreside($istituto_old['firma_preside'])
            ->setEmailAmministratore($istituto_old['email_amministratore'])
            ->setEmailNotifiche($istituto_old['email_notifiche']);
          $em->persist($istituto);
          // dati sedi
          $sql = "SELECT * FROM gs_sede ORDER BY ordinamento";
          $stmt = $conn->prepare($sql);
          $stmt->execute();
          foreach ($stmt->fetchAll() as $sede_old) {
            $sede = (new Sede())
              ->setNome($sede_old['nome'])
              ->setNomeBreve($sede_old['nome_breve'])
              ->setCitta($sede_old['citta'])
              ->setIndirizzo1(isset($sede_old['indirizzo1']) ? $sede_old['indirizzo1'] : $sede_old['indirizzo'])
              ->setIndirizzo2(isset($sede_old['indirizzo2']) ? $sede_old['indirizzo2'] : $sede_old['citta'])
              ->setTelefono($sede_old['telefono'])
              ->setOrdinamento($sede_old['ordinamento']);
            $em->persist($sede);
          }
          // memorizza dati
          $em->flush();
        }
        // importa corsi/materie
        if (in_array('C', $form->get('dati')->getData())) {
          // dati corsi
          $sql = "SELECT * FROM gs_corso ORDER BY nome";
          $stmt = $conn->prepare($sql);
          $stmt->execute();
          foreach ($stmt->fetchAll() as $corso_old) {
            $corso = (new Corso())
              ->setNome($corso_old['nome'])
              ->setNomeBreve($corso_old['nome_breve']);
            $em->persist($corso);
          }
          // dati materie
          $sql = "SELECT * FROM gs_materia ORDER BY nome";
          $stmt = $conn->prepare($sql);
          $stmt->execute();
          foreach ($stmt->fetchAll() as $materia_old) {
            $materia = (new Materia())
              ->setNome($materia_old['nome'])
              ->setNomeBreve($materia_old['nome_breve'])
              ->setTipo($materia_old['tipo'])
              ->setValutazione($materia_old['valutazione'])
              ->setMedia($materia_old['media'])
              ->setOrdinamento($materia_old['ordinamento']);
            $em->persist($materia);
          }
          // memorizza dati
          $em->flush();
        }
        // importa classi
        if (in_array('L', $form->get('dati')->getData())) {
          // dati classi
          $sql = "SELECT cl.*,c.nome AS c_nome,s.nome AS s_nome
            FROM gs_classe as cl,gs_sede as s,gs_corso as c
            WHERE cl.sede_id=s.id AND cl.corso_id=c.id
            ORDER BY sezione,anno";
          $stmt = $conn->prepare($sql);
          $stmt->execute();
          foreach ($stmt->fetchAll() as $classe_old) {
            $sede = $em->getRepository('App\Entity\Sede')->findOneByNome($classe_old['s_nome']);
            $corso = $em->getRepository('App\Entity\Corso')->findOneByNome($classe_old['c_nome']);
            $classe = (new Classe())
              ->setSede($sede)
              ->setCorso($corso)
              ->setAnno($classe_old['anno'])
              ->setSezione($classe_old['sezione'])
              ->setOreSettimanali($classe_old['ore_settimanali']);
            $em->persist($classe);
          }
          // memorizza dati
          $em->flush();
        }
        // importa alunni/genitori
        if (in_array('A', $form->get('dati')->getData())) {
          // dati alunni con esito finale
          $sql = "SELECT a.*,g.username AS g_username,g.password AS g_password,g.email AS g_email,
              cl.anno,cl.sezione,e.esito
            FROM gs_utente AS a,gs_classe AS cl,gs_utente AS g,gs_esito e
            WHERE a.ruolo=:alunno AND a.abilitato=:abilitato AND a.classe_id=cl.id
            AND g.ruolo=:genitore AND g.alunno_id=a.id
            AND e.alunno_id=a.id AND e.esito IN ('A', 'N', 'E')
            ORDER BY a.cognome,a.nome,a.data_nascita";
          $stmt = $conn->prepare($sql);
          $stmt->execute(['alunno' => 'ALU', 'abilitato' => 1, 'genitore' => 'GEN']);
          $lista1 = $stmt->fetchAll();
          // dati alunni con scrutinio rimandato
          $sql = "SELECT a.*,g.username AS g_username,g.password AS g_password,g.email AS g_email,
              cl.anno,cl.sezione,e.esito
            FROM gs_utente AS a,gs_classe AS cl,gs_utente AS g,gs_esito e
            WHERE a.ruolo=:alunno AND a.abilitato=:abilitato AND a.classe_id=cl.id
            AND g.ruolo=:genitore AND g.alunno_id=a.id
            AND e.alunno_id=a.id AND e.esito=:rinviato
            AND NOT EXISTS (SELECT e1.id FROM gs_esito AS e1 WHERE e1.alunno_id=a.id AND e1.esito IN ('A', 'N', 'E'))
            ORDER BY a.cognome,a.nome,a.data_nascita";
          $stmt = $conn->prepare($sql);
          $stmt->execute(['alunno' => 'ALU', 'abilitato' => 1, 'genitore' => 'GEN', 'rinviato' => 'X']);
          $lista2 = $stmt->fetchAll();
          // dati alunni non ammessi per assenze o all'estero
          $assenze = [];
          $estero = [];
          $sql = "SELECT *
            FROM gs_scrutinio
            WHERE periodo=:finale";
          $stmt = $conn->prepare($sql);
          $stmt->execute(['finale' => 'F']);
          foreach ($stmt->fetchAll() as $sc) {
            $dati = unserialize($sc['dati']);
            $noscrut = (isset($dati['no_scrutinabili']) ? $dati['no_scrutinabili'] : []);
            foreach ($noscrut as $alu=>$ns) {
              if (!isset($ns['deroga'])) {
                $assenze[] = $alu;
              }
            }
            $noscrut = (isset($dati['cessata_frequenza']) ? $dati['cessata_frequenza'] : []);
            foreach ($noscrut as $alu=>$ns) {
              $assenze[] = $alu;
            }
            $altro = (isset($dati['estero']) ? $dati['estero'] : []);
            foreach ($altro as $alu) {
              $estero[] = $alu;
            }
          }
          $alunni = implode(',', $assenze);
          $sql = "SELECT a.*,g.username AS g_username,g.password AS g_password,g.email AS g_email,
              cl.anno,cl.sezione,'L' AS esito
            FROM gs_utente AS a,gs_classe AS cl,gs_utente AS g
            WHERE a.ruolo=:alunno AND a.classe_id=cl.id
            AND g.ruolo=:genitore AND g.alunno_id=a.id
            AND a.id IN ($alunni)
            ORDER BY a.cognome,a.nome,a.data_nascita";
          $stmt = $conn->prepare($sql);
          $stmt->execute(['alunno' => 'ALU', 'genitore' => 'GEN']);
          $lista3 = $stmt->fetchAll();
          $alunni = implode(',', $estero);
          $sql = "SELECT a.*,g.username AS g_username,g.password AS g_password,g.email AS g_email,
              cl.anno,cl.sezione,'E' AS esito
            FROM gs_utente AS a,gs_classe AS cl,gs_utente AS g,gs_cambio_classe AS cc
            WHERE a.ruolo=:alunno AND a.classe_id IS NULL
            AND cc.alunno_id=a.id AND cc.classe_id=cl.id
            AND g.ruolo=:genitore AND g.alunno_id=a.id
            AND a.id IN ($alunni)
            ORDER BY a.cognome,a.nome,a.data_nascita";
          $stmt = $conn->prepare($sql);
          $stmt->execute(['alunno' => 'ALU', 'genitore' => 'GEN']);
          $lista4 = $stmt->fetchAll();
          // inserisce alunni/genitori
          foreach (array_merge($lista1, $lista2, $lista3, $lista4) as $utente_old) {
            if ($utente_old['esito'] == 'A' && $utente_old['anno'] == 5) {
              // sono esclusi alunni di quinta ammessi
              continue;
            }
            // crea alunno
            $alunno = (new Alunno())
              ->setUsername($utente_old['username'])
              ->setPassword($utente_old['password'])
              ->setEmail($utente_old['email'])
              ->setAbilitato(true)
              ->setNome($utente_old['nome'])
              ->setCognome($utente_old['cognome'])
              ->setSesso($utente_old['sesso'])
              ->setDataNascita(\DateTime::createFromFormat('Y-m-d', $utente_old['data_nascita']))
              ->setComuneNascita($utente_old['comune_nascita'])
              ->setCodiceFiscale($utente_old['codice_fiscale'])
              ->setCitta($utente_old['citta'])
              ->setIndirizzo($utente_old['indirizzo'])
              ->setNumeriTelefono(unserialize($utente_old['numeri_telefono']));
            $em->persist($alunno);
            // crea genitore
            $genitore = (new Genitore())
              ->setAlunno($alunno)
              ->setUsername($utente_old['g_username'])
              ->setPassword($utente_old['g_password'])
              ->setEmail($utente_old['g_email'])
              ->setAbilitato(true)
              ->setNome($utente_old['nome'])
              ->setCognome($utente_old['cognome'])
              ->setSesso($utente_old['sesso']);
            $em->persist($genitore);
          }
          // memorizza dati
          $em->flush();
        }
        // importa personale
        if (in_array('P', $form->get('dati')->getData())) {
          // dati preside
          $sql = "SELECT * FROM gs_utente WHERE ruolo=:ruolo AND abilitato=:abilitato ORDER BY cognome,nome";
          $stmt = $conn->prepare($sql);
          $stmt->execute(['ruolo' => 'PRE', 'abilitato' => 1]);
          foreach ($stmt->fetchAll() as $utente_old) {
            $preside = (new Preside())
              ->setOtp($utente_old['otp'])
              ->setUsername($utente_old['username'])
              ->setPassword($utente_old['password'])
              ->setEmail($utente_old['email'])
              ->setAbilitato(true)
              ->setNome($utente_old['nome'])
              ->setCognome($utente_old['cognome'])
              ->setSesso($utente_old['sesso'])
              ->setDataNascita($utente_old['data_nascita'])
              ->setComuneNascita($utente_old['comune_nascita'])
              ->setCodiceFiscale($utente_old['codice_fiscale'])
              ->setCitta($utente_old['citta'])
              ->setIndirizzo($utente_old['indirizzo'])
              ->setNumeriTelefono(unserialize($utente_old['numeri_telefono']));
            $em->persist($preside);
          }
          // dati staff
          $sql = "SELECT u.*,s.nome AS s_nome
            FROM gs_utente AS u LEFT JOIN gs_sede AS s ON u.sede_id=s.id
            WHERE u.ruolo=:ruolo AND u.abilitato=:abilitato
            ORDER BY cognome,nome";
          $stmt = $conn->prepare($sql);
          $stmt->execute(['ruolo' => 'STA', 'abilitato' => 1]);
          foreach ($stmt->fetchAll() as $utente_old) {
            $sede = $em->getRepository('App\Entity\Sede')->findOneByNome($utente_old['s_nome']);
            $staff = (new Staff())
              ->setSede($sede)
              ->setOtp($utente_old['otp'])
              ->setUsername($utente_old['username'])
              ->setPassword($utente_old['password'])
              ->setEmail($utente_old['email'])
              ->setAbilitato(true)
              ->setNome($utente_old['nome'])
              ->setCognome($utente_old['cognome'])
              ->setSesso($utente_old['sesso'])
              ->setDataNascita($utente_old['data_nascita'])
              ->setComuneNascita($utente_old['comune_nascita'])
              ->setCodiceFiscale($utente_old['codice_fiscale'])
              ->setCitta($utente_old['citta'])
              ->setIndirizzo($utente_old['indirizzo'])
              ->setNumeriTelefono(unserialize($utente_old['numeri_telefono']));
            $em->persist($staff);
          }
          // dati docenti
          $sql = "SELECT *
            FROM gs_utente AS u
            WHERE u.ruolo=:ruolo AND u.abilitato=:abilitato
            ORDER BY cognome,nome";
          $stmt = $conn->prepare($sql);
          $stmt->execute(['ruolo' => 'DOC', 'abilitato' => 1]);
          foreach ($stmt->fetchAll() as $utente_old) {
            $docente = (new Docente())
              ->setOtp($utente_old['otp'])
              ->setUsername($utente_old['username'])
              ->setPassword($utente_old['password'])
              ->setEmail($utente_old['email'])
              ->setAbilitato(true)
              ->setNome($utente_old['nome'])
              ->setCognome($utente_old['cognome'])
              ->setSesso($utente_old['sesso'])
              ->setDataNascita($utente_old['data_nascita'])
              ->setComuneNascita($utente_old['comune_nascita'])
              ->setCodiceFiscale($utente_old['codice_fiscale'])
              ->setCitta($utente_old['citta'])
              ->setIndirizzo($utente_old['indirizzo'])
              ->setNumeriTelefono(unserialize($utente_old['numeri_telefono']));
            $em->persist($docente);
          }
          // dati ATA
          $sql = "SELECT u.*,s.nome AS s_nome
            FROM gs_utente AS u LEFT JOIN gs_sede AS s ON u.sede_id=s.id
            WHERE u.ruolo=:ruolo AND u.abilitato=:abilitato
            ORDER BY cognome,nome";
          $stmt = $conn->prepare($sql);
          $stmt->execute(['ruolo' => 'ATA', 'abilitato' => 1]);
          foreach ($stmt->fetchAll() as $utente_old) {
            $sede = $em->getRepository('App\Entity\Sede')->findOneByNome($utente_old['s_nome']);
            $ata = (new Ata())
              ->setTipo($utente_old['tipo'])
              ->setSegreteria($utente_old['segreteria'])
              ->setSede($sede)
              ->setUsername($utente_old['username'])
              ->setPassword($utente_old['password'])
              ->setEmail($utente_old['email'])
              ->setAbilitato(true)
              ->setNome($utente_old['nome'])
              ->setCognome($utente_old['cognome'])
              ->setSesso($utente_old['sesso'])
              ->setDataNascita($utente_old['data_nascita'])
              ->setComuneNascita($utente_old['comune_nascita'])
              ->setCodiceFiscale($utente_old['codice_fiscale'])
              ->setCitta($utente_old['citta'])
              ->setIndirizzo($utente_old['indirizzo'])
              ->setNumeriTelefono(unserialize($utente_old['numeri_telefono']));
            $em->persist($ata);
          }
          // memorizza dati
          $em->flush();
        }
        // importa esiti
        if (in_array('E', $form->get('dati')->getData())) {
          // alunni esistenti nel nuovo sistema
          $fs = new Filesystem();
          $alunni = $em->getRepository('App\Entity\Alunno')->createQueryBuilder('a')
            ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
            ->getQuery()
            ->getResult();
          // recupera dati scrutinio
          foreach ($alunni as $alu) {
            // legge esito e voti di alunno
            $classeNome = null;
            $sql = "SELECT a.id AS a_id,c.anno,c.sezione,e.esito,e.credito,e.credito_precedente,e.dati AS e_dati,s.periodo,vs.unico,vs.debito,vs.dati,m.nome AS m_nome
              FROM gs_utente AS a,gs_classe AS c,gs_esito AS e,gs_scrutinio AS s,
                gs_voto_scrutinio AS vs,gs_materia AS m
              WHERE a.ruolo=:alunno AND a.abilitato=:abilitato AND a.codice_fiscale=:codfis AND a.classe_id=c.id
              AND e.alunno_id=a.id AND e.esito IN ('A', 'N', 'E') AND e.scrutinio_id=s.id
              AND vs.scrutinio_id=s.id AND vs.alunno_id=a.id AND vs.materia_id=m.id";
            $stmt = $conn->prepare($sql);
            $stmt->execute(['alunno' => 'ALU', 'abilitato' => 1, 'codfis' => $alu->getCodiceFiscale()]);
            $listaVoti = $stmt->fetchAll();
            if (empty($listaVoti)) {
              // cerca scrutinio rinviato
              $sql = "SELECT a.id AS a_id,c.anno,c.sezione,e.esito,e.credito,e.credito_precedente,e.dati AS e_dati,s.periodo,vs.unico,vs.debito,vs.dati,m.nome AS m_nome
                FROM gs_utente AS a,gs_classe AS c,gs_esito AS e,gs_scrutinio AS s,
                  gs_voto_scrutinio AS vs,gs_materia AS m
                WHERE a.ruolo=:alunno AND a.abilitato=:abilitato AND a.codice_fiscale=:codfis AND a.classe_id=c.id
                AND e.alunno_id=a.id AND e.esito=:rinviato AND e.scrutinio_id=s.id
                AND vs.scrutinio_id=s.id AND vs.alunno_id=a.id AND vs.materia_id=m.id";
              $stmt = $conn->prepare($sql);
              $stmt->execute(['alunno' => 'ALU', 'abilitato' => 1, 'codfis' => $alu->getCodiceFiscale(),
                'rinviato' => 'X']);
              $listaVoti = $stmt->fetchAll();
            }
            $primo = true;
            $media = 0;
            $numMedia = 0;
            $esito = null;
            foreach ($listaVoti as $scrutinio) {
              if ($primo) {
                // imposta esito
                $datiEsito = unserialize($scrutinio['e_dati']);
                $creditoPrec = $scrutinio['credito_precedente'];
                if ($scrutinio['anno'] > 3 && $scrutinio['esito'] == 'A' &&
                    isset($datiEsito['creditoIntegrativo']) && $datiEsito['creditoIntegrativo']) {
                  // aggiunge credito integrativo
                  $creditoPrec++;
                }
                $esito = (new StoricoEsito())
                  ->setClasse($scrutinio['anno'].$scrutinio['sezione'])
                  ->setEsito($scrutinio['esito'])
                  ->setCredito($scrutinio['credito'])
                  ->setCreditoPrecedente($creditoPrec)
                  ->setPeriodo($scrutinio['periodo'])
                  ->setAlunno($alu);
                $em->persist($esito);
                $classeNome = $scrutinio['anno'].$scrutinio['sezione'];
                $primo = false;
              }
              // imposta voti
              $materia = $em->getRepository('App\Entity\Materia')->findOneByNome($scrutinio['m_nome']);
              $dati = unserialize($scrutinio['dati']);
              $votoDati = array();
              $carenze = null;
              if ($scrutinio['anno'] < 5 && in_array($scrutinio['esito'], ['A', 'X'])) {
                // escluse quinte e non ammessi
                $sql = "SELECT vs.unico,vs.debito,vs.dati
                  FROM gs_utente AS a,gs_esito AS e,gs_scrutinio AS s,
                    gs_voto_scrutinio AS vs,gs_materia AS m
                  WHERE a.id=:alunno
                  AND e.alunno_id=a.id AND e.esito=:sospeso AND e.scrutinio_id=s.id
                  AND s.periodo=:finale AND m.nome=:materia AND vs.unico<6
                  AND vs.scrutinio_id=s.id AND vs.alunno_id=a.id AND vs.materia_id=m.id";
                $stmt = $conn->prepare($sql);
                $stmt->execute(['alunno' => $scrutinio['a_id'], 'sospeso' => 'S',
                  'finale' => 'F', 'materia' => $materia->getNome()]);
                $lista = $stmt->fetchAll();
                if (count($lista) == 1) {
                  // debito presente
                  $carenze = $lista[0]['debito'];
                  $votoDati['carenza'] = 'D';
                } else {
                  // carenze,
                  $esiti = "'S','A'";
                  $sql = "SELECT vs.unico,vs.debito,vs.dati,e.dati AS e_dati
                    FROM gs_utente AS a,gs_esito AS e,gs_scrutinio AS s,
                      gs_voto_scrutinio AS vs,gs_materia AS m
                    WHERE a.id=:alunno
                    AND e.alunno_id=a.id AND e.esito IN ($esiti) AND e.scrutinio_id=s.id
                    AND s.periodo=:finale AND m.nome=:materia
                    AND vs.scrutinio_id=s.id AND vs.alunno_id=a.id AND vs.materia_id=m.id";
                  $stmt = $conn->prepare($sql);
                  $stmt->execute(['alunno' => $scrutinio['a_id'], 'finale' => 'F',
                    'materia' => $materia->getNome()]);
                  $lista = $stmt->fetchAll();
                  $esitoDati = unserialize($lista[0]['e_dati']);
                  if (isset($esitoDati['carenze']) && isset($esitoDati['carenze_materie']) &&
                      in_array($materia->getNomeBreve(), $esitoDati['carenze_materie'])) {
                    $carenze = $lista[0]['debito'];
                    $votoDati['carenza'] = 'C';
                  }
                }
              }
              $voto = (new StoricoVoto())
                ->setVoto($scrutinio['unico'])
                ->setCarenze($carenze)
                ->setDati($votoDati)
                ->setStoricoEsito($esito)
                ->setMateria($materia);
              $em->persist($voto);
              if ($materia->getMedia()) {
                $numMedia++;
                if (($materia->getTipo() == 'C' && $scrutinio['unico'] == 4) ||
                    ($materia->getTipo() == 'E' && $scrutinio['unico'] == 3)) {
                  // NC equivale a 0
                  continue;
                }
                $media += $scrutinio['unico'];
              }
            }
            // imposta media
            if ($esito && $numMedia) {
              $esito->setMedia($media / $numMedia);
            }
            if (!$esito) {
              // dati alunni non ammessi per assenze o all'estero
              $sql = "SELECT s.dati,a.id AS a_id,c.anno,c.sezione
                FROM gs_utente AS a,gs_scrutinio AS s,gs_classe AS c,gs_cambio_classe AS cc
                WHERE a.codice_fiscale=:alunno AND (a.classe_id=c.id OR (cc.classe_id=c.id AND cc.alunno_id=a.id))
                AND s.classe_id=c.id AND s.periodo=:finale";
              $stmt = $conn->prepare($sql);
              $stmt->execute(['alunno' => $alu->getCodiceFiscale(), 'finale' => 'F']);
              $lista = $stmt->fetchAll();
              if (!empty($lista)) {
                $idAlu = $lista[0]['a_id'];
                $dati = unserialize($lista[0]['dati']);
                $tipoEsito = null;
                if (isset($dati['no_scrutinabili'][$idAlu]) &&
                    !isset($dati['no_scrutinabili'][$idAlu]['deroga'])) {
                  $tipoEsito = 'L';
                } elseif (isset($dati['cessata_frequenza']) && in_array($idAlu, $dati['cessata_frequenza'])) {
                  $tipoEsito = 'R';
                } elseif (isset($dati['estero']) && in_array($idAlu, $dati['estero'])) {
                  $tipoEsito = 'E';
                }
                if ($tipoEsito) {
                  $esito = (new StoricoEsito())
                    ->setClasse($lista[0]['anno'].$lista[0]['sezione'])
                    ->setEsito($tipoEsito)
                    ->setPeriodo('F')
                    ->setAlunno($alu);
                  $em->persist($esito);
                  $classeNome = $lista[0]['anno'].$lista[0]['sezione'];
                }
              } else {
                // errore: alunno non trovato
                dump($alu);
                die('ERRORE');
              }
            }
            // copia documenti dello scrutinio
            $percorso = $this->getParameter('dir_scrutini').'/storico/'.$classeNome;
            if (!$fs->exists($percorso)) {
              // crea directory
              $fs->mkdir($percorso, 0775);
            }
            // riepilogo voti
            $documento = $classeNome.'-scrutinio-finale-riepilogo-voti.pdf';
            $percorso_old = $dir.'finale/'.$classeNome;
            if ($fs->exists($percorso_old.'/'.$documento)) {
              $fs->copy($percorso_old.'/'.$documento, $percorso.'/'.$documento, false);
            }
            $documento = $classeNome.'-scrutinio-sospesi-riepilogo-voti.pdf';
            $percorso_old = $dir.'esami/'.$classeNome;
            if ($fs->exists($percorso_old.'/'.$documento)) {
              $fs->copy($percorso_old.'/'.$documento, $percorso.'/'.$documento, false);
            }
            $documento = $classeNome.'-scrutinio-rinviato-riepilogo-voti.pdf';
            $percorso_old = $dir.'rinviati/'.$classeNome;
            if ($fs->exists($percorso_old.'/'.$documento)) {
              $fs->copy($percorso_old.'/'.$documento, $percorso.'/'.$documento, false);
            }
            // verbale
            $documento = $classeNome.'-scrutinio-finale-verbale.pdf';
            $percorso_old = $dir.'finale/'.$classeNome;
            if ($fs->exists($percorso_old.'/'.$documento)) {
              $fs->copy($percorso_old.'/'.$documento, $percorso.'/'.$documento, false);
            }
            $documento = $classeNome.'-scrutinio-sospesi-verbale.pdf';
            $percorso_old = $dir.'esami/'.$classeNome;
            if ($fs->exists($percorso_old.'/'.$documento)) {
              $fs->copy($percorso_old.'/'.$documento, $percorso.'/'.$documento, false);
            }
            $documento = $classeNome.'-scrutinio-rinviato-verbale.pdf';
            $percorso_old = $dir.'rinviati/'.$classeNome;
            if ($fs->exists($percorso_old.'/'.$documento)) {
              $fs->copy($percorso_old.'/'.$documento, $percorso.'/'.$documento, false);
            }
          }
          // memorizza dati
          $em->flush();
        }
        // importa scrutini rinviati
        if (in_array('X', $form->get('dati')->getData())) {
          // legge scrutini rinviati
          $sql = "SELECT s.*,c.anno,c.sezione
            FROM gs_scrutinio AS s,gs_classe AS c
            WHERE s.classe_id=c.id AND s.periodo=:giudizio AND s.id IN
              (SELECT scrutinio_id FROM gs_esito WHERE esito=:rinviato)";
          $stmt = $conn->prepare($sql);
          $stmt->execute(['giudizio' => 'G', 'rinviato' => 'X']);
          $scrutini = $stmt->fetchAll();
          foreach ($scrutini as $scrutinio) {
            $classe = $em->getRepository('App\Entity\Classe')->findOneBy(['anno' => $scrutinio['anno'],
              'sezione' => $scrutinio['sezione']]);
            $dati = [];
            // dati scrutinio svolto
            $dati['scrutinio']['data'] = $scrutinio['data'];
            // dati materie
            $sql = "SELECT DISTINCT m.*
              FROM gs_materia AS m,gs_cattedra AS c
              WHERE c.materia_id=m.id AND c.classe_id=:classe AND c.attiva=:attiva AND c.tipo=:tipo";
            $stmt = $conn->prepare($sql);
            $stmt->execute(['classe' => $scrutinio['classe_id'], 'attiva' => 1, 'tipo' => 'N']);
            $materie = $stmt->fetchAll();
            $sql = "SELECT *
              FROM gs_materia
              WHERE tipo=:condotta";
            $stmt = $conn->prepare($sql);
            $stmt->execute(['condotta' => 'C']);
            $materie = array_merge($materie, $stmt->fetchAll());
            $trasformaMateria = [];
            foreach ($materie as $materia) {
              $mat = $em->getRepository('App\Entity\Materia')->findOneByNome($materia['nome']);
              $trasformaMateria[$materia['id']] = $mat->getId();
            }
            $dati['materie'] = array_values($trasformaMateria);
            // dati alunni
            $sql = "SELECT a.*
              FROM gs_esito AS e,gs_utente AS a
              WHERE e.alunno_id=a.id
              AND e.scrutinio_id=:scrutinio AND e.esito=:rinviato";
            $stmt = $conn->prepare($sql);
            $stmt->execute(['scrutinio' => $scrutinio['id'], 'rinviato' => 'X']);
            $alunni = $stmt->fetchAll();
            $dati['alunni'] = [];
            $datiScrutinabili = unserialize($scrutinio['dati'])['scrutinabili'];
            foreach ($alunni as $alunno) {
              $alu = $em->getRepository('App\Entity\Alunno')->findOneByCodiceFiscale($alunno['codice_fiscale']);
              $dati['alunni'][] = $alu->getId();
              $dati['religione'][$alu->getId()] = $alunno['religione'];
              $dati['credito3'][$alu->getId()] = $alunno['credito3'];
              $dati['scrutinabili'][$alu->getId()] = $datiScrutinabili[$alunno['id']];
              // legge voti e assenze
              $sql = "SELECT *
                FROM gs_voto_scrutinio
                WHERE scrutinio_id=:scrutinio AND alunno_id=:alunno";
              $stmt = $conn->prepare($sql);
              $stmt->execute(['scrutinio' => $scrutinio['id'], 'alunno' => $alunno['id']]);
              $voti = $stmt->fetchAll();
              foreach ($voti as $voto) {
                $dati['voti'][$alu->getId()][$trasformaMateria[$voto['materia_id']]]['unico'] = $voto['unico'];
                $dati['voti'][$alu->getId()][$trasformaMateria[$voto['materia_id']]]['assenze'] = $voto['assenze'];
              }
            }
            // dati docenti
            $sql = "SELECT d.id,d.cognome,d.nome,d.sesso,c.tipo,m.id AS m_id
              FROM gs_cattedra AS c,gs_utente AS d,gs_materia AS m
              WHERE c.classe_id=:classe AND c.attiva=:attiva AND c.tipo!=:tipo
              AND c.docente_id=d.id AND c.materia_id=m.id
              ORDER BY d.cognome,d.nome,m.ordinamento";
            $stmt = $conn->prepare($sql);
            $stmt->execute(['classe' => $scrutinio['classe_id'], 'attiva' => 1, 'tipo' => 'P']);
            $docenti = $stmt->fetchAll();
            foreach ($docenti as $docente) {
              $dati['docenti'][$docente['id']]['cognome'] = $docente['cognome'];
              $dati['docenti'][$docente['id']]['nome'] = $docente['nome'];
              $dati['docenti'][$docente['id']]['sesso'] = $docente['sesso'];
              $dati['docenti'][$docente['id']]['cattedre'][] = ['tipo' => $docente['tipo'],
                'materia' => $trasformaMateria[$docente['m_id']]];
            }
            // crea scrutinio e inserisce dati
            $scrutinioRinviato = (new Scrutinio())
              ->setClasse($classe)
              ->setPeriodo('X')
              ->setStato('N')
              ->setDati($dati);
            $em->persist($scrutinioRinviato);
          }
          // memorizza dati
          $em->flush();
        }
      }
    }
    // mostra la pagina di risposta
    return $this->renderHtml('sistema', 'importa', $dati, $info, [$form->createView(), 'message.importa']);
  }

  /**
   * Gestione dell'archiviazione dei registri in PDF
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
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
  public function archiviaAction(Request $request, EntityManagerInterface $em, TranslatorInterface $trans,
                                 ArchiviazioneUtil $arch): Response {
    // init
    $dati = [];
    $info = [];
    $lista_docenti = $em->getRepository('App\Entity\Docente')->createQueryBuilder('d')
      ->join('App\Entity\Cattedra', 'c', 'WITH', 'c.docente=d.id')
      ->join('c.materia', 'm')
      ->where('m.tipo IN (:tipi)')
      ->orderBy('d.cognome,d.nome', 'ASC')
      ->setParameters(['tipi' => ['N', 'R', 'E']])
      ->getQuery()
      ->getResult();
    $lista_sostegno = $em->getRepository('App\Entity\Docente')->createQueryBuilder('d')
      ->join('App\Entity\Cattedra', 'c', 'WITH', 'c.docente=d.id')
      ->join('c.materia', 'm')
      ->where('m.tipo=:tipo')
      ->orderBy('d.cognome,d.nome', 'ASC')
      ->setParameters(['tipo' => 'S'])
      ->getQuery()
      ->getResult();
    $lista_classi = $em->getRepository('App\Entity\Classe')->createQueryBuilder('c')
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
   *
   * @return Response Pagina di risposta
   *
   * @Route("/sistema/manutenzione/cache/", name="sistema_manutenzione_cache",
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function manutenzioneCacheAction(TranslatorInterface $trans): Response {
    // comandi per la pulizia della cache del database
    $commands = [
      new ArrayInput(['command' => 'doctrine:cache:clear-query', '--flush' => null, '-q' => null]),
      new ArrayInput(['command' => 'doctrine:cache:clear-result', '--flush' => null, '-q' => null]),
      //-- new ArrayInput(['command' => 'cache:clear', '-q' => null]),
    ];
    // esegue comandi
    $kernel = new Kernel('prod', false);
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
      // cancella cache
      $dir = $this->getParameter('kernel.cache_dir');
      $this->fileDelete($dir);
      // esecuzione senza errori
      $this->addFlash('success', 'message.svuota_cache_ok');
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
      $inizio = '['.$dt.' '.$tm.':00]';
      $fine = '['.$dt.' '.$form->get('ora')->getData()->modify('+1 hour')->format('H:i').':00]';
      // nome file
      $nomefile = $this->getParameter('kernel.project_dir').'/var/log/app_'.
        mb_strtolower($request->server->get('APP_ENV')).'-'.$dt.'.log';
      if (file_exists($nomefile)) {
        $fl = fopen($nomefile, "r");
        while (($riga = fgets($fl)) !== false) {
          // legge una riga
          $tag = substr($riga, 0, 21);
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
   * @return Response Pagina di risposta
   *
   * @Route("/sistema/manutenzione/debug/", name="sistema_manutenzione_debug",
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function manutenzioneDebugAction(Request $request): Response {
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
    // cancella cache (solo file principali)
    $dir = $this->getParameter('kernel.cache_dir');
    $finder = new Finder();
    $finder->files()->in($dir);
    foreach ($finder as $file) {
      unlink($file->getRealPath());
    }
    // messaggio
    $this->addFlash('success', 'message.modifica_log_level_ok');
    // redirect
    return $this->redirectToRoute('sistema_manutenzione');
  }


  //==================== METODI PRIVATI DELLA CLASSE ====================

  /**
   * Cancella i file e le sottodirectory del percorso indicato
   *
   * @param string $dir Percorso della directory da cancellare
   */
  private function fileDelete($dir) {
    foreach(glob($dir . '/*') as $file) {
      if ($file == '.' || $file == '..') {
        // salta
        continue;
      } elseif(is_dir($file)) {
        // rimuove directory e suo contenuto
        $this->fileDelete($file);
        rmdir($file);
      } else {
        // rimuove file
        unlink($file);
      }
    }
  }

}
