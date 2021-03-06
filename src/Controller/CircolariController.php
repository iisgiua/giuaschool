<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2021 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2021
 */


namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use App\Entity\Annotazione;
use App\Entity\Genitore;
use App\Entity\Alunno;
use App\Entity\Docente;
use App\Entity\Staff;
use App\Entity\Ata;
use App\Entity\Circolare;
use App\Entity\CircolareUtente;
use App\Entity\CircolareClasse;
use App\Entity\Notifica;
use App\Form\CircolareType;
use App\Util\RegistroUtil;
use App\Util\CircolariUtil;
use App\Util\LogHandler;


/**
 * CircolariController - gestione delle circolari
 */
class CircolariController extends AbstractController {

  /**
   * Aggiunge o modifica una circolare
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param CircolariUtil $circ Funzioni di utilità per le circolari
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $id Identificativo della circolare
   *
   * @return Response Pagina di risposta
   *
   * @Route("/circolari/edit/{id}", name="circolari_edit",
   *    requirements={"id": "\d+"},
   *    defaults={"id": "0"},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_STAFF")
   */
  public function editAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                              TranslatorInterface $trans, RegistroUtil $reg, CircolariUtil $circ, LogHandler $dblogger, $id) {
    // inizializza
    $dati = array();
    $var_sessione = '/APP/FILE/circolari_edit/';
    $dir = $this->getParameter('dir_circolari').'/';
    $fs = new FileSystem();
    // controlla azione
    if ($id > 0) {
      // azione edit
      $circolare = $em->getRepository('App:Circolare')->findOneBy(['id' => $id]);
      if (!$circolare) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $circolare_old = clone $circolare;
    } else {
      // azione add
      $numero = $em->getRepository('App:Circolare')->prossimoNumero();
      $circolare = (new Circolare())
        ->setData(new \DateTime('today'))
        ->setNumero($numero);
      if ($this->getUser()->getSede()) {
        $circolare->addSede($this->getUser()->getSede());
      }
      $em->persist($circolare);
    }
    // controllo permessi
    if (!$circ->azioneCircolare(($id > 0 ? 'edit' : 'add'), $circolare->getData(), $this->getUser(), ($id > 0 ? $circolare : null))) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // legge file
    $documento = array();
    $allegati = array();
    if ($request->isMethod('POST')) {
      // pagina inviata
      foreach ($session->get($var_sessione.'documento', []) as $f) {
        if ($f['type'] != 'removed') {
          // aggiunge allegato
          $documento[] = $f;
        }
      }
      foreach ($session->get($var_sessione.'allegati', []) as $f) {
        if ($f['type'] != 'removed') {
          // aggiunge allegato
          $allegati[] = $f;
        }
      }
    } else {
      // pagina iniziale
      if ($circolare->getDocumento()) {
        $f = new File($dir.$circolare->getDocumento());
        $documento[0]['type'] = 'existent';
        $documento[0]['temp'] = $circolare->getId().'.ID';
        $documento[0]['name'] = $f->getBasename('.'.$f->getExtension());
        $documento[0]['ext'] = $f->getExtension();
        $documento[0]['size'] = $f->getSize();
      }
      foreach ($circolare->getAllegati() as $k=>$a) {
        $f = new File($dir.$a);
        $allegati[$k]['type'] = 'existent';
        $allegati[$k]['temp'] = $circolare->getId().'-'.$k.'.ID';
        $allegati[$k]['name'] = $f->getBasename('.'.$f->getExtension());
        $allegati[$k]['ext'] = $f->getExtension();
        $allegati[$k]['size'] = $f->getSize();
      }
      // modifica dati sessione
      $session->remove($var_sessione.'documento');
      $session->remove($var_sessione.'allegati');
      $session->set($var_sessione.'documento', $documento);
      $session->set($var_sessione.'allegati', $allegati);
      // elimina file temporanei
      $finder = new Finder();
      $finder->in($this->getParameter('dir_tmp'))->date('< 1 day ago');
      foreach ($finder as $f) {
        $fs->remove($f);
      }
    }
    // form di inserimento
    $form = $this->createForm(CircolareType::class, $circolare, ['returnUrl' => $this->generateUrl('circolari_gestione'),
      'setSede' => ($this->getUser()->getSede() ? $this->getUser()->getSede()->getId() : null)]);
    $form->handleRequest($request);
    // visualizzazione filtro coordinatori
    $dati['coordinatori'] = ($form->get('coordinatori')->getData() == 'C' ?
      $em->getRepository('App:Classe')->listaClassi($form->get('filtroCoordinatori')->getData()) : '');
    $dati['docenti'] = ($form->get('docenti')->getData() == 'C' ?
      $em->getRepository('App:Classe')->listaClassi($form->get('filtroDocenti')->getData()) :
        ($form->get('docenti')->getData() == 'M' ?
        $em->getRepository('App:Materia')->listaMaterie($form->get('filtroDocenti')->getData()) :
          ($form->get('docenti')->getData() == 'U' ?
          $em->getRepository('App:Docente')->listaDocenti($form->get('filtroDocenti')->getData(), 'gs-filtroDocenti-') :'')));
    $dati['genitori'] = ($form->get('genitori')->getData() == 'C' ?
      $em->getRepository('App:Classe')->listaClassi($form->get('filtroGenitori')->getData()) :
        ($form->get('genitori')->getData() == 'U' ?
        $em->getRepository('App:Alunno')->listaAlunni($form->get('filtroGenitori')->getData(), 'gs-filtroGenitori-') :''));
    $dati['alunni'] = ($form->get('alunni')->getData() == 'C' ?
      $em->getRepository('App:Classe')->listaClassi($form->get('filtroAlunni')->getData()) :
        ($form->get('alunni')->getData() == 'U' ?
        $em->getRepository('App:Alunno')->listaAlunni($form->get('filtroAlunni')->getData(), 'gs-filtroAlunni-') :''));
    if ($form->isSubmitted()) {
      // lista sedi
      $sedi = array();
      foreach ($circolare->getSedi() as $s) {
        if (!$this->getUser()->getSede() || $this->getUser()->getSede() == $s) {
          // sede corretta
          $sedi[] = $s->getId();
        } else {
          // elimina sede
          $circolare->removeSede($s);
        }
      }
      // controllo errori
      if (!$circolare->getData()) {
        // data non presente
        $form->addError(new FormError($trans->trans('exception.data_nulla')));
      }
      if (!$em->getRepository('App:Circolare')->controllaNumero($circolare)) {
        // numero presente
        $form->addError(new FormError($trans->trans('exception.circolare_numero_esiste')));
      }
      if (!$circolare->getOggetto()) {
        // oggetto non presente
        $form->addError(new FormError($trans->trans('exception.circolare_oggetto_nullo')));
      }
      if (count($sedi) == 0) {
        // sedi non definite
        $form->addError(new FormError($trans->trans('exception.circolare_sede_nulla')));
      }
      if (!$circolare->getDsga() && !$circolare->getAta() && $circolare->getCoordinatori() == 'N' &&
          $circolare->getDocenti() == 'N' && $circolare->getGenitori() == 'N' && $circolare->getAlunni() == 'N' &&
          empty($circolare->getAltri())) {
        // destinatari non definiti
        $form->addError(new FormError($trans->trans('exception.circolare_destinatari_nulli')));
      }
      if (count($documento) == 0) {
        // documento non inviato
        $form->addError(new FormError($trans->trans('exception.circolare_documento_nullo')));
      }
      // controlla filtro coordinatori
      $lista = array();
      $errore = false;
      if ($circolare->getCoordinatori() == 'C') {
        // controlla classi
        $lista = $em->getRepository('App:Classe')
          ->controllaClassi($sedi, $form->get('filtroCoordinatori')->getData(), $errore);
        if ($errore) {
          // classe non valida
          $form->addError(new FormError($trans->trans('exception.filtro_classi_invalido', ['dest' => 'dei Coordinatori'])));
        }
      }
      $circolare->setFiltroCoordinatori($lista);
      // controlla filtro docenti
      $lista = array();
      $errore = false;
      if ($circolare->getDocenti() == 'C') {
        // controlla classi
        $lista = $em->getRepository('App:Classe')
          ->controllaClassi($sedi, $form->get('filtroDocenti')->getData(), $errore);
        if ($errore) {
          // classe non valida
          $form->addError(new FormError($trans->trans('exception.filtro_classi_invalido', ['dest' => 'dei Docenti'])));
        }
      } elseif ($circolare->getDocenti() == 'M') {
        // controlla materie
        $lista = $em->getRepository('App:Materia')->controllaMaterie($form->get('filtroDocenti')->getData(), $errore);
        if ($errore) {
          // materia non valida
          $form->addError(new FormError($trans->trans('exception.filtro_materie_invalido')));
        }
      } elseif ($circolare->getDocenti() == 'U') {
        // controlla utenti
        $lista = $em->getRepository('App:Docente')
          ->controllaDocenti($sedi, $form->get('filtroDocenti')->getData(), $errore);
        if ($errore) {
          // utente non valido
          $form->addError(new FormError($trans->trans('exception.filtro_utenti_invalido', ['dest' => 'dei Docenti'])));
        }
      }
      $circolare->setFiltroDocenti($lista);
      // controlla filtro genitori
      $lista = array();
      $errore = false;
      if ($circolare->getGenitori() == 'C') {
        // controlla classi
        $lista = $em->getRepository('App:Classe')
          ->controllaClassi($sedi, $form->get('filtroGenitori')->getData(), $errore);
        if ($errore) {
          // classe non valida
          $form->addError(new FormError($trans->trans('exception.filtro_classi_invalido', ['dest' => 'dei Genitori'])));
        }
      } elseif ($circolare->getGenitori() == 'U') {
        // controlla utenti
        $lista = $em->getRepository('App:Alunno')
          ->controllaAlunni($sedi, $form->get('filtroGenitori')->getData(), $errore);
        if ($errore) {
          // utente non valido
          $form->addError(new FormError($trans->trans('exception.filtro_utenti_invalido', ['dest' => 'dei Genitori'])));
        }
      }
      $circolare->setFiltroGenitori($lista);
      // controlla filtro alunni
      $lista = array();
      $errore = false;
      if ($circolare->getAlunni() == 'C') {
        // controlla classi
        $lista = $em->getRepository('App:Classe')
          ->controllaClassi($sedi, $form->get('filtroAlunni')->getData(), $errore);
        if ($errore) {
          // classe non valida
          $form->addError(new FormError($trans->trans('exception.filtro_classi_invalido', ['dest' => 'degli Alunni'])));
        }
      } elseif ($circolare->getAlunni() == 'U') {
        // controlla utenti
        $lista = $em->getRepository('App:Alunno')
          ->controllaAlunni($sedi, $form->get('filtroAlunni')->getData(), $errore);
        if ($errore) {
          // utente non valido
          $form->addError(new FormError($trans->trans('exception.filtro_utenti_invalido', ['dest' => 'degli Alunni'])));
        }
      }
      $circolare->setFiltroAlunni($lista);
      // controlla altri
      $lista_altri = $circolare->getAltri();
      foreach ($lista_altri as $k=>$v) {
        $v = strtoupper(trim($v));
        if (empty($v)) {
          unset($lista_altri[$k]);
        } else {
          $lista_altri[$k] = $v;
        }
      }
      if (count($lista_altri) != count($circolare->getAltri())) {
        // lista altri non valida
        $form->addError(new FormError($trans->trans('exception.lista_altri_invalida')));
      }
      $circolare->setAltri($lista_altri);
      // forza notifica SEMPRE
      $circolare->setNotifica(true);
      // forza firma MAI
      $circolare->setFirma(false);
      // modifica dati
      if ($form->isValid()) {
        // documento
        foreach ($session->get($var_sessione.'documento', []) as $f) {
          if ($f['type'] == 'uploaded') {
            // aggiunge documento
            $fs->rename($this->getParameter('dir_tmp').'/'.$f['temp'], $this->getParameter('dir_circolari').'/'.
              $f['temp'].'.'.$f['ext']);
            $circolare->setDocumento(new File($this->getParameter('dir_circolari').'/'.$f['temp'].'.'.$f['ext']));
          } elseif ($f['type'] == 'removed') {
            // rimuove documento
            $fs->remove($this->getParameter('dir_circolari').'/'.$f['name'].'.'.$f['ext']);
          }
        }
        // allegati
        foreach ($session->get($var_sessione.'allegati', []) as $f) {
          if ($f['type'] == 'uploaded') {
            // aggiunge allegato
            $fs->rename($this->getParameter('dir_tmp').'/'.$f['temp'], $this->getParameter('dir_circolari').'/'.
              $f['temp'].'.'.$f['ext']);
            $circolare->addAllegato(new File($this->getParameter('dir_circolari').'/'.$f['temp'].'.'.$f['ext']));
          } elseif ($f['type'] == 'removed') {
            // rimuove allegato
            $circolare->removeAllegato(new File($this->getParameter('dir_circolari').'/'.$f['name'].'.'.$f['ext']));
            $fs->remove($this->getParameter('dir_circolari').'/'.$f['name'].'.'.$f['ext']);
          }
        }
        // ok: memorizza dati
        $em->flush();
        // log azione
        if (!$id) {
          // nuovo
          $dblogger->write($this->getUser(), $request->getClientIp(), 'CIRCOLARI', 'Crea circolare', __METHOD__, array(
            'id' => $circolare->getId(),
            ));
        } else {
          // modifica
          $dblogger->write($this->getUser(), $request->getClientIp(), 'CIRCOLARI', 'Modifica circolare', __METHOD__, array(
            'id' => $circolare->getId(),
            'Data' => $circolare_old->getData()->format('d/m/Y'),
            'Numero' => $circolare_old->getNumero(),
            'Oggetto' => $circolare_old->getOggetto(),
            'Documento' => $circolare_old->getDocumento(),
            'Allegati' => $circolare_old->getAllegati(),
            'Sedi' => implode(', ', array_map(function ($s) { return $s->getId(); }, $circolare_old->getSedi()->toArray())),
            'Destinatari DSGA' => $circolare_old->getDsga(),
            'Destinatari ATA' => $circolare_old->getAta(),
            'Destinatari Coordinatori' => $circolare_old->getCoordinatori(),
            'Filtro Coordinatori' => $circolare_old->getFiltroCoordinatori(),
            'Destinatari Docenti' => $circolare_old->getDocenti(),
            'Filtro Docenti' => $circolare_old->getFiltroDocenti(),
            'Destinatari Genitori' => $circolare_old->getGenitori(),
            'Filtro Genitori' => $circolare_old->getFiltroGenitori(),
            'Destinatari Alunni' => $circolare_old->getAlunni(),
            'Filtro Alunni' => $circolare_old->getFiltroAlunni(),
            'Destinatari Altri' => $circolare_old->getAltri(),
            'Destinatari Liste Distribuzione' => implode(', ', array_map(function ($l) { return $l->getId(); }, $circolare_old->getListeDistribuzione()->toArray())),
            'Firma' => $circolare_old->getFirma(),
            'Pubblicata' => $circolare_old->getPubblicata(),
            ));
        }
        // redirezione
        return $this->redirectToRoute('circolari_gestione');
      }
    }
    // mostra la pagina di risposta
    return $this->render('circolari/edit.html.twig', array(
      'pagina_titolo' => 'page.staff_circolari',
      'form' => $form->createView(),
      'form_title' => ($id > 0 ? 'title.modifica_circolare' : 'title.nuova_circolare'),
      'documento' => $documento,
      'allegati' => $allegati,
      'dati' => $dati,
    ));
  }

  /**
   * Cancella circolare
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param LogHandler $dblogger Gestore dei log su database
   * @param CircolariUtil $circ Funzioni di utilità per le circolari
   * @param int $id Identificativo dell'avviso
   *
   * @return Response Pagina di risposta
   *
   * @Route("/circolari/delete/{id}", name="circolari_delete",
   *    requirements={"id": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_STAFF")
   */
  public function deleteAction(Request $request, EntityManagerInterface $em, LogHandler $dblogger,
                                CircolariUtil $circ, $id) {
    $dir = $this->getParameter('dir_circolari').'/';
    $fs = new FileSystem();
    // controllo circolare
    $circolare = $em->getRepository('App:Circolare')->find($id);
    if (!$circolare) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo permessi
    if (!$circ->azioneCircolare('delete', $circolare->getData(), $this->getUser(), $circolare)) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // cancella circolare
    $circolare_id = $circolare->getId();
    $circolare_sedi = implode(', ', array_map(function ($s) { return $s->getId(); }, $circolare->getSedi()->toArray()));
    $circolare_liste = implode(', ', array_map(function ($l) { return $l->getId(); }, $circolare->getListeDistribuzione()->toArray()));
    $em->remove($circolare);
    // ok: memorizza dati
    $em->flush();
    // cancella documento
    $f = new File($dir.$circolare->getDocumento());
    $fs->remove($f);
    // cancella allegati
    foreach ($circolare->getAllegati() as $a) {
      $f = new File($dir.$a);
      $fs->remove($f);
    }
    // log azione
    $dblogger->write($this->getUser(), $request->getClientIp(), 'CIRCOLARI', 'Cancella circolare', __METHOD__, array(
      'id' => $circolare_id,
      'Data' => $circolare->getData()->format('d/m/Y'),
      'Numero' => $circolare->getNumero(),
      'Oggetto' => $circolare->getOggetto(),
      'Documento' => $circolare->getDocumento(),
      'Allegati' => $circolare->getAllegati(),
      'Sedi' => $circolare_sedi,
      'Destinatari DSGA' => $circolare->getDsga(),
      'Destinatari ATA' => $circolare->getAta(),
      'Destinatari Coordinatori' => $circolare->getCoordinatori(),
      'Filtro Coordinatori' => $circolare->getFiltroCoordinatori(),
      'Destinatari Docenti' => $circolare->getDocenti(),
      'Filtro Docenti' => $circolare->getFiltroDocenti(),
      'Destinatari Genitori' => $circolare->getGenitori(),
      'Filtro Genitori' => $circolare->getFiltroGenitori(),
      'Destinatari Alunni' => $circolare->getAlunni(),
      'Filtro Alunni' => $circolare->getFiltroAlunni(),
      'Destinatari Altri' => $circolare->getAltri(),
      'Destinatari Liste Distribuzione' => $circolare_liste,
      'Firma' => $circolare->getFirma(),
      'Pubblicata' => $circolare->getPubblicata(),
      ));
    // redirezione
    return $this->redirectToRoute('circolari_gestione');
  }

  /**
   * Gestione delle circolari
   *
   * @param Request $request Pagina richiesta
   * @param SessionInterface $session Gestore delle sessioni
   * @param CircolariUtil $circ Funzioni di utilità per le circolari
   * @param int $pagina Numero di pagina per l'elenco da visualizzare
   *
   * @return Response Pagina di risposta
   *
   * @Route("/circolari/gestione/{pagina}", name="circolari_gestione",
   *    requirements={"pagina": "\d+"},
   *    defaults={"pagina": "0"},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_STAFF")
   */
  public function gestioneAction(Request $request, SessionInterface $session, CircolariUtil $circ, $pagina) {
    // inizializza variabili
    $dati = null;
    $limite = 20;
    // recupera criteri dalla sessione
    $search = array();
    $search['inizio'] = $session->get('/APP/ROUTE/circolari_gestione/inizio', null);
    $search['fine'] = $session->get('/APP/ROUTE/circolari_gestione/fine', null);
    $search['oggetto'] = $session->get('/APP/ROUTE/circolari_gestione/oggetto', '');
    if ($search['inizio']) {
      $inizio = \DateTime::createFromFormat('Y-m-d', $search['inizio']);
    } else {
      $inizio = \DateTime::createFromFormat('Y-m-d H:i', $session->get('/CONFIG/SCUOLA/anno_inizio').' 00:00')
        ->modify('first day of this month');
      $search['inizio'] = $inizio->format('Y-m-d');
    }
    if ($search['fine']) {
      $fine = \DateTime::createFromFormat('Y-m-d', $search['fine']);
    } else {
      $fine = new \DateTime('tomorrow');
      $search['fine'] = $fine->format('Y-m-d');
    }
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $session->get('/APP/ROUTE/circolari_gestione/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $session->set('/APP/ROUTE/circolari_gestione/pagina', $pagina);
    }
    // form di ricerca
    $form = $this->container->get('form.factory')->createNamedBuilder('circolari_gestione', FormType::class)
      ->add('inizio', DateType::class, array('label' => 'label.data_inizio',
        'data' => $inizio,
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-picker'],
        'format' => 'dd/MM/yyyy',
        'required' => false))
      ->add('fine', DateType::class, array('label' => 'label.data_fine',
        'data' => $fine,
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-picker'],
        'format' => 'dd/MM/yyyy',
        'required' => false))
      ->add('oggetto', TextType::class, array('label' => 'label.oggetto',
        'data' => $search['oggetto'],
        'attr' => ['placeholder' => 'label.oggetto',],
        'label_attr' => ['class' => 'sr-only'],
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.search'))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $search['inizio'] = ($form->get('inizio')->getData() ? $form->get('inizio')->getData()->format('Y-m-d') : 0);
      $search['fine'] = ($form->get('fine')->getData() ? $form->get('fine')->getData()->format('Y-m-d') : 0);
      $search['oggetto'] = $form->get('oggetto')->getData();
      $pagina = 1;
      $session->set('/APP/ROUTE/circolari_gestione/inizio', $search['inizio']);
      $session->set('/APP/ROUTE/circolari_gestione/fine', $search['fine']);
      $session->set('/APP/ROUTE/circolari_gestione/oggetto', $search['oggetto']);
      $session->set('/APP/ROUTE/circolari_gestione/pagina', $pagina);
    }
    // recupera dati
    $dati = $circ->listaCircolari($search, $pagina, $limite, $this->getUser());
    // mostra la pagina di risposta
    return $this->render('circolari/gestione.html.twig', array(
      'pagina_titolo' => 'page.circolari_gestione',
      'form' => $form->createView(),
      'form_help' => null,
      'form_success' => null,
      'page' => $pagina,
      'maxPages' => ceil($dati['lista']->count() / $limite),
      'dati' => $dati,
    ));
  }

  /**
   * Pubblica la circolare o ne rimuove la pubblicazione
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param LogHandler $dblogger Gestore dei log su database
   * @param CircolariUtil $circ Funzioni di utilità per le circolari
   * @param bool $pubblica Vero se si vuole pubblicare la circolare, falso per togliere la pubblicazione
   * @param int $id Identificativo dell'avviso
   *
   * @return Response Pagina di risposta
   *
   * @Route("/circolari/publish/{pubblica}/{id}", name="circolari_publish",
   *    requirements={"pubblica": "0|1", "id": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_STAFF")
   */
  public function publishAction(Request $request, EntityManagerInterface $em, LogHandler $dblogger,
                                 CircolariUtil $circ, $pubblica, $id) {
    // controllo circolare
    $circolare = $em->getRepository('App:Circolare')->find($id);
    if (!$circolare) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo permessi
    if (!$circ->azioneCircolare(($pubblica ? 'publish' : 'unpublish'), $circolare->getData(), $this->getUser(), $circolare)) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    if ($pubblica) {
      // aggiunge destinatari
      $dest = $circ->destinatari($circolare);
      // imposta utenti
      foreach ($dest['utenti'] as $u) {
        $obj = (new CircolareUtente())
          ->setCircolare($circolare)
          ->setUtente($em->getReference('App:Utente', $u));
        $em->persist($obj);
      }
      // imposta classi
      foreach ($dest['classi'] as $c) {
        $obj = (new CircolareClasse())
          ->setCircolare($circolare)
          ->setClasse($em->getReference('App:Classe', $c));
        $em->persist($obj);
      }
    } else {
      // rimuove destinatari
      $query = $em->getRepository('App:CircolareUtente')->createQueryBuilder('ce')
        ->delete()
        ->where('ce.circolare=:circolare')
        ->setParameters(['circolare' => $circolare])
        ->getQuery()
        ->execute();
      $query = $em->getRepository('App:CircolareClasse')->createQueryBuilder('cc')
        ->delete()
        ->where('cc.circolare=:circolare')
        ->setParameters(['circolare' => $circolare])
        ->getQuery()
        ->execute();
    }
    // pubblica
    $circolare->setPubblicata($pubblica);
    // ok: memorizza dati
    $em->flush();
    // log azione e notifica
    $notifica = (new Notifica())
      ->setOggettoNome('Circolare')
      ->setOggettoId($circolare->getId());
    $em->persist($notifica);
    if ($pubblica) {
      // pubblicazione
      $notifica->setAzione('A');
      $dblogger->write($this->getUser(), $request->getClientIp(), 'CIRCOLARI', 'Pubblica circolare', __METHOD__, array(
        'Circolare ID' => $circolare->getId()));
    } else {
      // rimuove pubblicazione
      $notifica->setAzione('D');
      $dblogger->write($this->getUser(), $request->getClientIp(), 'CIRCOLARI', 'Rimuove pubblicazione circolare', __METHOD__, array(
        'Circolare ID' => $circolare->getId()));
    }
    // redirezione
    return $this->redirectToRoute('circolari_gestione');
  }

  /**
   * Mostra i dettagli di una circolare
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param CircolariUtil $circ Funzioni di utilità per le circolari
   * @param int $id ID della circolare
   *
   * @return Response Pagina di risposta
   *
   * @Route("/circolari/dettagli/gestione/{id}", name="circolari_dettagli_gestione",
   *    requirements={"id": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_STAFF")
   */
  public function dettagliGestioneAction(EntityManagerInterface $em, CircolariUtil $circ, $id) {
    // inizializza
    $mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    $dati = null;
    // controllo circolare
    $circolare = $em->getRepository('App:Circolare')->find($id);
    if (!$circolare || !$circ->permessoLettura($circolare, $this->getUser())) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // legge dati
    $dati = $circ->dettagli($circolare);
    // visualizza pagina
    return $this->render('circolari/scheda_dettagli_gestione.html.twig', array(
      'circolare' => $circolare,
      'mesi' => $mesi,
      'dati' => $dati,
    ));
  }

  /**
   * Esegue il download di un documento di una circolare.
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param CircolariUtil $circ Funzioni di utilità per le circolari
   * @param int $id ID della circolare
   * @param int $doc Numero del documento (0 per la circolare, 1.. per gli allegati)
   * @param string $tipo Tipo di risposta (V=visualizza, D=download)
   *
   * @return Response Documento inviato in risposta
   *
   * @Route("/circolari/download/{id}/{doc}/{tipo}", name="circolari_download",
   *    requirements={"id": "\d+", "doc": "\d+", "tipo": "V|D"},
   *    defaults={"tipo": "V"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_UTENTE")
   */
  public function downloadAction(EntityManagerInterface $em, CircolariUtil $circ, $id, $doc, $tipo) {
    $dir = $this->getParameter('dir_circolari').'/';
    // controllo circolare
    $circolare = $em->getRepository('App:Circolare')->find($id);
    if (!$circolare) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo documenti
    if ($doc < 0 || $doc > count($circolare->getAllegati())) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo permessi
    if (!$circ->permessoLettura($circolare, $this->getUser())) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // file
    $nome = 'circolare-'.$circolare->getNumero();
    if ($doc == 0) {
      // documento principale
      $file = new File($dir.$circolare->getDocumento());
    } else {
      // allegato
      $file = new File($dir.$circolare->getAllegati()[$doc - 1]);
      $nome .= '-allegato-'.$doc;
    }
    $nome .= '.'.$file->getExtension();
    // segna lettura implicita
    if ($doc == 0 && !$circolare->getFirma()) {
      // dati di lettura implicita
      $cu = $em->getRepository('App:CircolareUtente')->findOneBy(['circolare' => $circolare,
        'utente' => $this->getUser()]);
      if ($cu && !$cu->getLetta()) {
        // imposta lettura
        $cu->setLetta(new \DateTime());
        // memorizza dati
        $em->flush();
      }
    }
    // invia il documento
    return $this->file($file, $nome, ($tipo == 'V' ? ResponseHeaderBag::DISPOSITION_INLINE :
      ResponseHeaderBag::DISPOSITION_ATTACHMENT));
  }

  /**
   * Visualizza le circolari destinate ai genitori/alunni.
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param int $pagina Numero di pagina per l'elenco da visualizzare
   *
   * @return Response Pagina di risposta
   *
   * @Route("/circolari/genitori/{pagina}", name="circolari_genitori",
   *    requirements={"pagina": "\d+"},
   *    defaults={"pagina": "0"},
   *    methods={"GET","POST"})
   *
   * @Security("is_granted('ROLE_GENITORE') or is_granted('ROLE_ALUNNO')")
   */
  public function genitoriAction(Request $request, EntityManagerInterface $em,
                                 SessionInterface $session, $pagina) {
    // inizializza
    $limite = 20;
    $mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    // crea lista mesi
    $anno_inizio = substr($session->get('/CONFIG/SCUOLA/anno_inizio', '2000'), 0, 4);
    $anno_fine = $anno_inizio + 1;
    $lista_mesi = array();
    for ($i=9; $i<=12; $i++) {
      $lista_mesi[$mesi[$i].' '.$anno_inizio] = $anno_inizio.'-'.substr('0'.$i, -2);
    }
    for ($i=1; $i<=8; $i++) {
      $lista_mesi[$mesi[$i].' '.$anno_fine] = $anno_fine.'-0'.$i;
    }
    // recupera criteri dalla sessione
    $cerca = array();
    $cerca['visualizza'] = $session->get('/APP/ROUTE/circolari_genitori/visualizza', 'P');
    $cerca['mese'] = $session->get('/APP/ROUTE/circolari_genitori/mese', null);
    $cerca['oggetto'] = $session->get('/APP/ROUTE/circolari_genitori/oggetto', '');
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $session->get('/APP/ROUTE/circolari_genitori/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $session->set('/APP/ROUTE/circolari_genitori/pagina', $pagina);
    }
    // form di ricerca
    $form = $this->container->get('form.factory')->createNamedBuilder('circolari_genitori', FormType::class)
      ->add('visualizza', ChoiceType::class, array('label' => 'label.circolari_filtro_visualizza',
        'data' => $cerca['visualizza'],
        'choices' => ['label.circolari_da_leggere' => 'D', 'label.circolari_tutte' => 'P'],
        'label_attr' => ['class' => 'sr-only'],
        'choice_attr' => function($val, $key, $index) {
            return ['class' => 'gs-no-placeholder'];
          },
        'attr' => ['class' => 'gs-placeholder'],
        'required' => true))
      ->add('mese', ChoiceType::class, array('label' => 'label.filtro_mese',
        'data' => $cerca['mese'],
        'choices' => $lista_mesi,
        'placeholder' => 'label.circolari_tutte',
        'choice_translation_domain' => false,
        'label_attr' => ['class' => 'sr-only'],
        'choice_attr' => function($val, $key, $index) {
            return ['class' => 'gs-no-placeholder'];
          },
        'attr' => ['class' => 'gs-placeholder'],
        'required' => false))
      ->add('oggetto', TextType::class, array('label' => 'label.circolari_filtro_oggetto',
        'data' => $cerca['oggetto'],
        'attr' => ['placeholder' => 'label.oggetto', 'class' => 'gs-placeholder'],
        'label_attr' => ['class' => 'sr-only'],
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.search'))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $cerca['visualizza'] = $form->get('visualizza')->getData();
      $cerca['mese'] = $form->get('mese')->getData();
      $cerca['oggetto'] = $form->get('oggetto')->getData();
      $pagina = 1;
      $session->set('/APP/ROUTE/circolari_genitori/visualizza', $cerca['visualizza']);
      $session->set('/APP/ROUTE/circolari_genitori/mese', $cerca['mese']);
      $session->set('/APP/ROUTE/circolari_genitori/oggetto', $cerca['oggetto']);
      $session->set('/APP/ROUTE/circolari_genitori/pagina', $pagina);
    }
    // legge le circolari
    $dati = $em->getRepository('App:Circolare')->lista($cerca, $pagina, $limite, $this->getUser());
    // mostra la pagina di risposta
    return $this->render('circolari/genitori.html.twig', array(
      'pagina_titolo' => 'page.circolari_genitori',
      'form' => $form->createView(),
      'form_help' => null,
      'form_success' => null,
      'page' => $pagina,
      'maxPages' => ceil($dati['lista']->count() / $limite),
      'dati' => $dati,
      'mesi' => $mesi,
    ));
  }

  /**
   * Mostra i dettagli di una circolare ai destinatari
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param CircolariUtil $circ Funzioni di utilità per le circolari
   * @param int $id ID della circolare
   *
   * @return Response Pagina di risposta
   *
   * @Route("/circolari/dettagli/destinatari/{id}", name="circolari_dettagli_destinatari",
   *    requirements={"id": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_UTENTE")
   */
  public function dettagliDestinatariAction(EntityManagerInterface $em, CircolariUtil $circ, $id) {
    // inizializza
    $mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    $dati = null;
    // controllo circolare
    $circolare = $em->getRepository('App:Circolare')->findOneBy(['id' => $id, 'pubblicata' => 1]);
    if (!$circolare || !$circ->permessoLettura($circolare, $this->getUser())) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // dati destinatario
    $cu = $em->getRepository('App:CircolareUtente')->findOneBy(['circolare' => $circolare,
      'utente' => $this->getUser()]);
    // visualizza pagina
    return $this->render('circolari/scheda_dettagli_destinatari.html.twig', array(
      'circolare' => $circolare,
      'circolare_utente' => $cu,
      'mesi' => $mesi,
    ));
  }

  /**
   * Mostra i dettagli di una circolare allo staff
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param CircolariUtil $circ Funzioni di utilità per le circolari
   * @param int $id ID della circolare
   *
   * @return Response Pagina di risposta
   *
   * @Route("/circolari/dettagli/staff/{id}", name="circolari_dettagli_staff",
   *    requirements={"id": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_STAFF")
   */
  public function dettagliStaffAction(EntityManagerInterface $em, CircolariUtil $circ, $id) {
    // inizializza
    $mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    $dati = null;
    // controllo circolare
    $circolare = $em->getRepository('App:Circolare')->findOneBy(['id' => $id, 'pubblicata' => 1]);
    if (!$circolare || !$circ->permessoLettura($circolare, $this->getUser())) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // dati circolare
    $dati = $circ->dettagli($circolare);
    // dati destinatario
    $cu = $em->getRepository('App:CircolareUtente')->findOneBy(['circolare' => $circolare,
      'utente' => $this->getUser()]);
    // visualizza pagina
    return $this->render('circolari/scheda_dettagli_staff.html.twig', array(
      'circolare' => $circolare,
      'circolare_utente' => $cu,
      'mesi' => $mesi,
      'dati' => $dati,
    ));
  }

  /**
   * Conferma la lettura della circolare da parte dell'utente
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param CircolariUtil $circ Funzioni di utilità per le circolari
   * @param int $id ID della circolare
   *
   * @return Response Pagina di risposta
   *
   * @Route("/circolari/firma/{id}", name="circolari_firma",
   *    requirements={"id": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_UTENTE")
   */
  public function firmaAction(EntityManagerInterface $em, CircolariUtil $circ, $id) {
    // controllo circolare
    $circolare = $em->getRepository('App:Circolare')->findOneBy(['id' => $id, 'pubblicata' => 1]);
    if (!$circolare || !$circ->permessoLettura($circolare, $this->getUser())) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // firma
    $em->getRepository('App:Circolare')->firma($circolare, $this->getUser());
    // redirect
    if ($this->getUser() instanceOf Genitore || $this->getUser() instanceOf Alunno) {
      // genitori/alunni
      return $this->redirectToRoute('circolari_genitori');
    } elseif ($this->getUser() instanceOf Docente) {
      // docente/staff
      return $this->redirectToRoute('circolari_docenti');
    } elseif ($this->getUser() instanceOf Ata) {
      // ata
      return $this->redirectToRoute('circolari_ata');
    }
  }

  /**
   * Visualizza le circolari destinate ai docenti (se staff tutte).
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param CircolariUtil $circ Funzioni di utilità per le circolari
   * @param int $pagina Numero di pagina per l'elenco da visualizzare
   *
   * @return Response Pagina di risposta
   *
   * @Route("/circolari/docenti/{pagina}", name="circolari_docenti",
   *    requirements={"pagina": "\d+"},
   *    defaults={"pagina": "0"},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function docentiAction(Request $request, EntityManagerInterface $em,
                                SessionInterface $session, CircolariUtil $circ, $pagina) {
    // inizializza
    $limite = 20;
    $mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    // crea lista mesi
    $anno_inizio = substr($session->get('/CONFIG/SCUOLA/anno_inizio', '2000'), 0, 4);
    $anno_fine = $anno_inizio + 1;
    $lista_mesi = array();
    for ($i=9; $i<=12; $i++) {
      $lista_mesi[$mesi[$i].' '.$anno_inizio] = $anno_inizio.'-'.substr('0'.$i, -2);
    }
    for ($i=1; $i<=8; $i++) {
      $lista_mesi[$mesi[$i].' '.$anno_fine] = $anno_fine.'-0'.$i;
    }
    // recupera criteri dalla sessione
    $cerca = array();
    $cerca['visualizza'] = $session->get('/APP/ROUTE/circolari_docenti/visualizza',
      ($this->getUser() instanceOf Staff ? 'T' : 'P'));
    $cerca['mese'] = $session->get('/APP/ROUTE/circolari_docenti/mese', null);
    $cerca['oggetto'] = $session->get('/APP/ROUTE/circolari_docenti/oggetto', '');
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $session->get('/APP/ROUTE/circolari_docenti/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $session->set('/APP/ROUTE/circolari_docenti/pagina', $pagina);
    }
    // form di ricerca
    $form = $this->container->get('form.factory')->createNamedBuilder('circolari_docenti', FormType::class)
      ->add('visualizza', ChoiceType::class, array('label' => 'label.circolari_filtro_visualizza',
        'data' => $cerca['visualizza'],
        'choices' => ['label.circolari_da_leggere' => 'D', 'label.circolari_proprie' => 'P',
          'label.circolari_tutte' => 'T'],
        'label_attr' => ['class' => 'sr-only'],
        'choice_attr' => function($val, $key, $index) {
            return ['class' => 'gs-no-placeholder'];
          },
        'attr' => ['class' => 'gs-placeholder'],
        'required' => true))
      ->add('mese', ChoiceType::class, array('label' => 'label.filtro_mese',
        'data' => $cerca['mese'],
        'choices' => $lista_mesi,
        'placeholder' => 'label.circolari_tutte',
        'choice_translation_domain' => false,
        'label_attr' => ['class' => 'sr-only'],
        'choice_attr' => function($val, $key, $index) {
            return ['class' => 'gs-no-placeholder'];
          },
        'attr' => ['class' => 'gs-placeholder'],
        'required' => false))
      ->add('oggetto', TextType::class, array('label' => 'label.circolari_filtro_oggetto',
        'data' => $cerca['oggetto'],
        'attr' => ['placeholder' => 'label.oggetto', 'class' => 'gs-placeholder'],
        'label_attr' => ['class' => 'sr-only'],
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.search'))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $cerca['visualizza'] = $form->get('visualizza')->getData();
      $cerca['mese'] = $form->get('mese')->getData();
      $cerca['oggetto'] = $form->get('oggetto')->getData();
      $pagina = 1;
      $session->set('/APP/ROUTE/circolari_docenti/visualizza', $cerca['visualizza']);
      $session->set('/APP/ROUTE/circolari_docenti/mese', $cerca['mese']);
      $session->set('/APP/ROUTE/circolari_docenti/oggetto', $cerca['oggetto']);
      $session->set('/APP/ROUTE/circolari_docenti/pagina', $pagina);
    }
    // legge le circolari
    $dati = $em->getRepository('App:Circolare')->lista($cerca, $pagina, $limite, $this->getUser());
    if ($this->getUser() instanceOf Staff) {
      // legge dettagli su circolari
      foreach ($dati['lista'] as $c) {
        $dati['info'][$c->getId()] = $circ->dettagli($c);
      }
    }
    // mostra la pagina di risposta
    return $this->render(($this->getUser() instanceOf Staff ? 'circolari/staff.html.twig' : 'circolari/docenti.html.twig'), array(
      'pagina_titolo' => 'page.circolari_docenti',
      'form' => $form->createView(),
      'form_help' => null,
      'form_success' => null,
      'page' => $pagina,
      'maxPages' => ceil($dati['lista']->count() / $limite),
      'dati' => $dati,
      'mesi' => $mesi,
    ));
  }

  /**
   * Visualizza le circolari destinate al personale ATA.
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param int $pagina Numero di pagina per l'elenco da visualizzare
   *
   * @return Response Pagina di risposta
   *
   * @Route("/circolari/ata/{pagina}", name="circolari_ata",
   *    requirements={"pagina": "\d+"},
   *    defaults={"pagina": "0"},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_ATA")
   */
  public function ataAction(Request $request, EntityManagerInterface $em,
                            SessionInterface $session, $pagina) {
    // inizializza
    $limite = 20;
    $mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    // crea lista mesi
    $anno_inizio = substr($session->get('/CONFIG/SCUOLA/anno_inizio', '2000'), 0, 4);
    $anno_fine = $anno_inizio + 1;
    $lista_mesi = array();
    for ($i=9; $i<=12; $i++) {
      $lista_mesi[$mesi[$i].' '.$anno_inizio] = $anno_inizio.'-'.substr('0'.$i, -2);
    }
    for ($i=1; $i<=8; $i++) {
      $lista_mesi[$mesi[$i].' '.$anno_fine] = $anno_fine.'-0'.$i;
    }
    // recupera criteri dalla sessione
    $cerca = array();
    $cerca['visualizza'] = $session->get('/APP/ROUTE/circolari_ata/visualizza', 'T');
    $cerca['mese'] = $session->get('/APP/ROUTE/circolari_ata/mese', null);
    $cerca['oggetto'] = $session->get('/APP/ROUTE/circolari_ata/oggetto', '');
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $session->get('/APP/ROUTE/circolari_ata/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $session->set('/APP/ROUTE/circolari_ata/pagina', $pagina);
    }
    // form di ricerca
    $form = $this->container->get('form.factory')->createNamedBuilder('circolari_ata', FormType::class)
      ->add('visualizza', ChoiceType::class, array('label' => 'label.circolari_filtro_visualizza',
        'data' => $cerca['visualizza'],
        'choices' => ['label.circolari_da_leggere' => 'D', 'label.circolari_proprie' => 'P',
          'label.circolari_tutte' => 'T'],
        'label_attr' => ['class' => 'sr-only'],
        'choice_attr' => function($val, $key, $index) {
            return ['class' => 'gs-no-placeholder'];
          },
        'attr' => ['class' => 'gs-placeholder'],
        'required' => true))
      ->add('mese', ChoiceType::class, array('label' => 'label.filtro_mese',
        'data' => $cerca['mese'],
        'choices' => $lista_mesi,
        'placeholder' => 'label.circolari_tutte',
        'choice_translation_domain' => false,
        'label_attr' => ['class' => 'sr-only'],
        'choice_attr' => function($val, $key, $index) {
            return ['class' => 'gs-no-placeholder'];
          },
        'attr' => ['class' => 'gs-placeholder'],
        'required' => false))
      ->add('oggetto', TextType::class, array('label' => 'label.circolari_filtro_oggetto',
        'data' => $cerca['oggetto'],
        'attr' => ['placeholder' => 'label.oggetto', 'class' => 'gs-placeholder'],
        'label_attr' => ['class' => 'sr-only'],
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.search'))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $cerca['visualizza'] = $form->get('visualizza')->getData();
      $cerca['mese'] = $form->get('mese')->getData();
      $cerca['oggetto'] = $form->get('oggetto')->getData();
      $pagina = 1;
      $session->set('/APP/ROUTE/circolari_ata/visualizza', $cerca['visualizza']);
      $session->set('/APP/ROUTE/circolari_ata/mese', $cerca['mese']);
      $session->set('/APP/ROUTE/circolari_ata/oggetto', $cerca['oggetto']);
      $session->set('/APP/ROUTE/circolari_ata/pagina', $pagina);
    }
    // legge le circolari
    $dati = $em->getRepository('App:Circolare')->lista($cerca, $pagina, $limite, $this->getUser());
    // mostra la pagina di risposta
    return $this->render('circolari/ata.html.twig', array(
      'pagina_titolo' => 'page.circolari_ata',
      'form' => $form->createView(),
      'form_help' => null,
      'form_success' => null,
      'page' => $pagina,
      'maxPages' => ceil($dati['lista']->count() / $limite),
      'dati' => $dati,
      'mesi' => $mesi,
    ));
  }

  /**
   * Mostra le circolari destinate agli alunni della classe
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param int $classe ID della classe
   *
   * @return Response Pagina di risposta
   *
   * @Route("/circolari/classi/{classe}", name="circolari_classi",
   *    requirements={"classe": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function classiAction(EntityManagerInterface $em, $classe) {
    // inizializza
    $dati = null;
    // controllo classe
    $classe = $em->getRepository('App:Classe')->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // legge dati
    $dati = $em->getRepository('App:Circolare')->circolariClasse($classe);
    // visualizza pagina
    return $this->render('circolari/scheda_dettagli_classe.html.twig', array(
      'dati' => $dati,
      'classe' => $classe,
    ));
  }

  /**
   * Conferma la lettura della circolare alla classe
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $classe ID della classe
   * @param int $id ID della circolare (0 indica tutte)
   *
   * @return Response Pagina di risposta
   *
   * @Route("/circolari/firma/classe/{classe}/{id}", name="circolari_firma_classe",
   *    requirements={"classe": "\d+", "id": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function firmaClasseAction(Request $request, EntityManagerInterface $em, TranslatorInterface $trans, LogHandler $dblogger,
                                     $classe, $id) {
    // controllo classe
    $classe = $em->getRepository('App:Classe')->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // firma
    $firme = $em->getRepository('App:Circolare')->firmaClasse($classe, $id);
    if (count($firme) > 0) {
      // lista circolari
      $lista = implode(', ', array_map(function ($c) { return $c->getNumero(); }, $firme));
      // testo annotazione
      $testo = $trans->trans('message.registro_lettura_circolare',
        ['num' => count($firme), 'circolari' => $lista]);
      // crea annotazione
      $a = (new Annotazione())
        ->setData(new \DateTime('today'))
        ->setTesto($testo)
        ->setVisibile(false)
        ->setClasse($classe)
        ->setDocente($this->getUser());
      $em->persist($a);
      $em->flush();
      // log
      $dblogger->write($this->getUser(), $request->getClientIp(), 'CIRCOLARI', 'Lettura in classe', __METHOD__, array(
        'Annotazione' => $a->getId(),
        'Circolari' => $lista,
        ));
    }
    // redirect
    return $this->redirectToRoute('lezioni');
  }

}
