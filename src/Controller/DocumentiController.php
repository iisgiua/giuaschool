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

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Translation\TranslatorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use App\Entity\Documento;
use App\Util\LogHandler;
use App\Util\DocumentiUtil;


/**
 * DocumentiController - gestione dei documenti
 */
class DocumentiController extends AbstractController {

  /**
   * Visualizza i programmi svolti dei docenti
   *
   * @param DocumentiUtil $doc Funzioni di utilità per la gestione dei documenti di classe
   *
   * @return Response Pagina di risposta
   *
   * @Route("/documenti/programmi", name="documenti_programmi",
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function programmiAction(DocumentiUtil $doc) {
    // inizializza variabili
    $dati = null;
    // recupera dati
    $dati = $doc->programmi($this->getUser());
    // mostra la pagina di risposta
    return $this->render('documenti/programmi.html.twig', array(
      'pagina_titolo' => 'page.documenti_programmi',
      'dati' => $dati,
    ));
  }

  /**
   * Aggiunge o modifica un programma
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param DocumentiUtil $doc Funzioni di utilità per la gestione dei documenti di classe
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $classe Identificativo della classe
   * @param int $materia Identificativo della materia
   * @param int $id Identificativo del documento
   *
   * @return Response Pagina di risposta
   *
   * @Route("/documenti/programmi/edit/{classe}/{materia}/{id}", name="documenti_programma_edit",
   *    requirements={"classe": "\d+", "materia": "\d+", "id": "\d+"},
   *    defaults={"id": "0"},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function programmaEditAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                       TranslatorInterface $trans, DocumentiUtil $doc, LogHandler $dblogger, $classe, $materia, $id) {
    // inizializza
    $var_sessione = '/APP/FILE/documenti_programma_edit/files';
    $dir = $this->getParameter('dir_classi').'/';
    $fs = new FileSystem();
    $info = null;
    // controlla classe
    $classe = $em->getRepository('App:Classe')->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    $info['classe'] = $classe->getAnno().'ª '.$classe->getSezione();
    $dir_classe = $classe->getAnno().$classe->getSezione();
    // controlla materia
    $materia = $em->getRepository('App:Materia')->find($materia);
    if (!$materia) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    $info['materia'] = $materia->getNomeBreve();
    // controlla azione
    if ($id > 0) {
      // azione edit
      $documento = $em->getRepository('App:Documento')->findOneBy(['id' => $id, 'tipo' => 'P',
        'classe' => $classe, 'materia' => $materia]);
      if (!$documento) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $documento_old = clone $documento;
    } else {
      // azione add
      $documento = $em->getRepository('App:Documento')->findOneBy(['tipo' => 'P',
        'classe' => $classe, 'materia' => $materia]);
      if ($documento) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $documento = (new Documento())
        ->setTipo('P')
        ->setClasse($classe)
        ->setMateria($materia);
      $em->persist($documento);
    }
    // controllo permessi
    if (!$doc->azioneDocumento(($id > 0 ? 'edit' : 'add'), new \DateTime(), $this->getUser(), ($id > 0 ? $documento : null))) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // legge allegati
    $allegati = array();
    if ($request->isMethod('POST')) {
      // pagina inviata
      foreach ($session->get($var_sessione, []) as $f) {
        if ($f['type'] != 'removed') {
          // aggiunge allegato
          $allegati[] = $f;
        }
      }
    } else {
      // pagina iniziale
      if ($documento->getFile()) {
        $f = new File($dir.$dir_classe.'/'.$documento->getFile());
        $allegati[0]['type'] = 'existent';
        $allegati[0]['temp'] = $documento->getId().'-0.ID';
        $allegati[0]['name'] = $documento->getFile();
        $allegati[0]['size'] = $f->getSize();
      }
      // modifica dati sessione
      $session->remove($var_sessione);
      $session->set($var_sessione, $allegati);
      // elimina file temporanei
      $finder = new Finder();
      $finder->in($this->getParameter('dir_tmp'))->date('< 1 day ago');
      foreach ($finder as $f) {
        $fs->remove($f);
      }
    }
    // imposta docente
    $documento->setDocente($this->getUser());
    // form di inserimento
    $form = $this->container->get('form.factory')->createNamedBuilder('programma_edit', FormType::class)
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start']))
      ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end',
        'onclick' => "location.href='".$this->generateUrl('documenti_programmi')."'"]))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // controllo errori
      $f_cnt = 0;
      foreach ($session->get($var_sessione, []) as $f) {
        if ($f['type'] != 'removed') {
          $f_cnt++;
        }
      }
      if ($f_cnt < 1) {
        // errore: nessun file allegati
        $form->addError(new FormError($trans->trans('exception.file_mancante')));
      }
      // modifica dati
      if ($form->isValid()) {
        // directory allegati
        if (!$fs->exists($dir.$dir_classe)) {
          // crea directory
          $fs->mkdir($dir.$dir_classe);
        }
        // rimuove allegati
        foreach ($session->get($var_sessione, []) as $f) {
          if ($f['type'] == 'removed') {
            // rimuove allegato
            $fs->remove($dir.$dir_classe.'/'.$f['name']);
          }
        }
        // carica nuovi allegati
        foreach ($session->get($var_sessione, []) as $f) {
          if ($f['type'] == 'uploaded') {
            // conversione del documento
            $fl = $doc->convertiPDF(new File($this->getParameter('dir_tmp').'/'.$f['temp']));
            $m = strtoupper(preg_replace('/\W+/','-', $materia->getNomeBreve()));
            if (substr($m, -1) == '-') {
              $m = substr($m, 0, -1);
            }
            $nomefile = 'PROGRAMMA-'.$dir_classe.'-'.$m.'.'.$fl->guessExtension();
            $documento
              ->setFile($nomefile)
              ->setDimensione($fl->getSize())
              ->setMime($fl->getMimeType());
            // sposta e rinomina allegato
            $fs->rename($fl, $dir.$dir_classe.'/'.$nomefile);
          }
        }
        // ok: memorizza dati
        $em->flush();
        // log azione
        if (!$id) {
          // nuovo
          $dblogger->write($this->getUser(), $request->getClientIp(), 'DOCUMENTI', 'Inserisce programma svolto', __METHOD__, array(
            'Id' => $documento->getId(),
            ));
        } else {
          // modifica
          $dblogger->write($this->getUser(), $request->getClientIp(), 'DOCUMENTI', 'Modifica programma svolto', __METHOD__, array(
            'Id' => $documento->getId(),
            'File' => $documento_old->getFile(),
            'Docente' => $documento_old->getDocente()->getId(),
            'Classe' => $documento->getClasse()->getId(),
            'Materia' => $documento->getMateria()->getId(),
            ));
        }
        // redirezione
        return $this->redirectToRoute('documenti_programmi');
      }
    }
    // mostra la pagina di risposta
    return $this->render('documenti/programma_edit.html.twig', array(
      'pagina_titolo' => 'page.documenti_programmi',
      'form' => $form->createView(),
      'form_title' => ($id > 0 ? 'title.modifica_programma' : 'title.nuovo_programma'),
      'info' => $info,
      'allegati' => $allegati,
    ));
  }

  /**
   * Cancella documento del tipo indicato
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param LogHandler $dblogger Gestore dei log su database
   * @param DocumentiUtil $doc Funzioni di utilità per la gestione dei documenti di classe
   * @param string $tipo Tipo dell'avviso
   * @param int $id Identificativo dell'avviso
   *
   * @return Response Pagina di risposta
   *
   * @Route("/documenti/delete/{tipo}/{id}", name="documenti_delete",
   *    requirements={"tipo": "L|P|R|M", "id": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function documentoDeleteAction(Request $request, EntityManagerInterface $em, LogHandler $dblogger,
                                         DocumentiUtil $doc, $tipo, $id) {
    $dir = $this->getParameter('dir_classi').'/';
    $fs = new FileSystem();
    // controllo documento
    $documento = $em->getRepository('App:Documento')->findOneBy(['id' => $id, 'tipo' => $tipo]);
    if (!$documento) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo permessi
    if (!$doc->azioneDocumento('delete', new \DateTime(), $this->getUser(), $documento)) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // cancella documento
    $documento_id = $documento->getId();
    $em->remove($documento);
    // ok: memorizza dati
    $em->flush();
    // cancella allegati
    $dir_classe = $documento->getClasse()->getAnno().$documento->getClasse()->getSezione();
    $f = new File($dir.$dir_classe.'/'.$documento->getFile());
    $fs->remove($f);
    // log azione
    $dblogger->write($this->getUser(), $request->getClientIp(), 'DOCUMENTI', 'Cancella', __METHOD__, array(
      'Id' => $documento_id,
      'Tipo' => $documento->getTipo(),
      'File' => $documento->getFile(),
      'Docente' => $documento->getDocente()->getId(),
      'Classe' => $documento->getClasse()->getId(),
      'Materia' => ($documento->getMateria() ? $documento->getMateria()->getId() : null),
      ));
    // redirezione
    if ($tipo == 'L') {
      // piani di lavoro
      return $this->redirectToRoute('documenti_piani');
    } elseif ($tipo == 'P') {
      // programmi
      return $this->redirectToRoute('documenti_programmi');
    } elseif ($tipo == 'R') {
      // relazioni
      return $this->redirectToRoute('documenti_relazioni');
    } elseif ($tipo == 'M') {
      // documento 15 maggio
      return $this->redirectToRoute('documenti_doc15');
    }
  }

  /**
   * Visualizza le relazioni finali dei docenti
   *
   * @param DocumentiUtil $doc Funzioni di utilità per la gestione dei documenti di classe
   *
   * @return Response Pagina di risposta
   *
   * @Route("/documenti/relazioni", name="documenti_relazioni",
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function relazioniAction(DocumentiUtil $doc) {
    // inizializza variabili
    $dati = null;
    // recupera dati
    $dati = $doc->relazioni($this->getUser());
    // mostra la pagina di risposta
    return $this->render('documenti/relazioni.html.twig', array(
      'pagina_titolo' => 'page.documenti_relazioni',
      'dati' => $dati,
    ));
  }

  /**
   * Aggiunge o modifica una relazione
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param DocumentiUtil $doc Funzioni di utilità per la gestione dei documenti di classe
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $classe Identificativo della classe
   * @param int $materia Identificativo della materia
   * @param int $id Identificativo del documento
   *
   * @return Response Pagina di risposta
   *
   * @Route("/documenti/relazioni/edit/{classe}/{materia}/{id}", name="documenti_relazione_edit",
   *    requirements={"classe": "\d+", "materia": "\d+", "id": "\d+"},
   *    defaults={"id": "0"},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function relazioneEditAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                       TranslatorInterface $trans, DocumentiUtil $doc, LogHandler $dblogger, $classe, $materia, $id) {
    // inizializza
    $var_sessione = '/APP/FILE/documenti_relazione_edit/files';
    $dir = $this->getParameter('dir_classi').'/';
    $fs = new FileSystem();
    $info = null;
    // controlla classe
    $classe = $em->getRepository('App:Classe')->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    $info['classe'] = $classe->getAnno().'ª '.$classe->getSezione();
    $dir_classe = $classe->getAnno().$classe->getSezione();
    // controlla materia
    $materia = $em->getRepository('App:Materia')->find($materia);
    if (!$materia) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    $info['materia'] = $materia->getNomeBreve();
    // controlla azione
    if ($id > 0) {
      // azione edit
      $documento = $em->getRepository('App:Documento')->findOneBy(['id' => $id, 'tipo' => 'R',
        'classe' => $classe, 'materia' => $materia]);
      if (!$documento) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $documento_old = clone $documento;
    } else {
      // azione add
      $documento = $em->getRepository('App:Documento')->findOneBy(['tipo' => 'R',
        'classe' => $classe, 'materia' => $materia]);
      if ($documento) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $documento = (new Documento())
        ->setTipo('R')
        ->setClasse($classe)
        ->setMateria($materia);
      $em->persist($documento);
    }
    // controllo permessi
    if (!$doc->azioneDocumento(($id > 0 ? 'edit' : 'add'), new \DateTime(), $this->getUser(), ($id > 0 ? $documento : null))) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // legge allegati
    $allegati = array();
    if ($request->isMethod('POST')) {
      // pagina inviata
      foreach ($session->get($var_sessione, []) as $f) {
        if ($f['type'] != 'removed') {
          // aggiunge allegato
          $allegati[] = $f;
        }
      }
    } else {
      // pagina iniziale
      if ($documento->getFile()) {
        $f = new File($dir.$dir_classe.'/'.$documento->getFile());
        $allegati[0]['type'] = 'existent';
        $allegati[0]['temp'] = $documento->getId().'-0.ID';
        $allegati[0]['name'] = $documento->getFile();
        $allegati[0]['size'] = $f->getSize();
      }
      // modifica dati sessione
      $session->remove($var_sessione);
      $session->set($var_sessione, $allegati);
      // elimina file temporanei
      $finder = new Finder();
      $finder->in($this->getParameter('dir_tmp'))->date('< 1 day ago');
      foreach ($finder as $f) {
        $fs->remove($f);
      }
    }
    // imposta docente
    $documento->setDocente($this->getUser());
    // form di inserimento
    $form = $this->container->get('form.factory')->createNamedBuilder('relazione_edit', FormType::class)
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start']))
      ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end',
        'onclick' => "location.href='".$this->generateUrl('documenti_relazioni')."'"]))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // controllo errori
      $f_cnt = 0;
      foreach ($session->get($var_sessione, []) as $f) {
        if ($f['type'] != 'removed') {
          $f_cnt++;
        }
      }
      if ($f_cnt < 1) {
        // errore: nessun file allegati
        $form->addError(new FormError($trans->trans('exception.file_mancante')));
      }
      // modifica dati
      if ($form->isValid()) {
        // directory allegati
        if (!$fs->exists($dir.$dir_classe)) {
          // crea directory
          $fs->mkdir($dir.$dir_classe);
        }
        // rimuove allegati
        foreach ($session->get($var_sessione, []) as $f) {
          if ($f['type'] == 'removed') {
            // rimuove allegato
            $fs->remove($dir.$dir_classe.'/'.$f['name']);
          }
        }
        // carica nuovi allegati
        foreach ($session->get($var_sessione, []) as $f) {
          if ($f['type'] == 'uploaded') {
            // conversione del documento
            $fl = $doc->convertiPDF(new File($this->getParameter('dir_tmp').'/'.$f['temp']));
            $m = strtoupper(preg_replace('/\W+/','-', $materia->getNomeBreve()));
            if (substr($m, -1) == '-') {
              $m = substr($m, 0, -1);
            }
            $nomefile = 'RELAZIONE-'.$dir_classe.'-'.$m.'.'.$fl->guessExtension();
            $documento
              ->setFile($nomefile)
              ->setDimensione($fl->getSize())
              ->setMime($fl->getMimeType());
            // sposta e rinomina allegato
            $fs->rename($fl, $dir.$dir_classe.'/'.$nomefile);
          }
        }
        // ok: memorizza dati
        $em->flush();
        // log azione
        if (!$id) {
          // nuovo
          $dblogger->write($this->getUser(), $request->getClientIp(), 'DOCUMENTI', 'Inserisce relazione finale', __METHOD__, array(
            'Id' => $documento->getId(),
            ));
        } else {
          // modifica
          $dblogger->write($this->getUser(), $request->getClientIp(), 'DOCUMENTI', 'Modifica relazione finale', __METHOD__, array(
            'Id' => $documento->getId(),
            'File' => $documento_old->getFile(),
            'Docente' => $documento_old->getDocente()->getId(),
            'Classe' => $documento->getClasse()->getId(),
            'Materia' => $documento->getMateria()->getId(),
            ));
        }
        // redirezione
        return $this->redirectToRoute('documenti_relazioni');
      }
    }
    // mostra la pagina di risposta
    return $this->render('documenti/relazione_edit.html.twig', array(
      'pagina_titolo' => 'page.documenti_relazioni',
      'form' => $form->createView(),
      'form_title' => ($id > 0 ? 'title.modifica_relazione' : 'title.nuova_relazione'),
      'info' => $info,
      'allegati' => $allegati,
    ));
  }

  /**
   * Visualizza i piani di lavoro dei docenti
   *
   * @param DocumentiUtil $doc Funzioni di utilità per la gestione dei documenti di classe
   *
   * @return Response Pagina di risposta
   *
   * @Route("/documenti/piani", name="documenti_piani",
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function pianiAction(DocumentiUtil $doc) {
    // inizializza variabili
    $dati = null;
    // recupera dati
    $dati = $doc->piani($this->getUser());
    // mostra la pagina di risposta
    return $this->render('documenti/piani.html.twig', array(
      'pagina_titolo' => 'page.documenti_piani',
      'dati' => $dati,
    ));
  }

  /**
   * Aggiunge o modifica una relazione
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param DocumentiUtil $doc Funzioni di utilità per la gestione dei documenti di classe
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $classe Identificativo della classe
   * @param int $materia Identificativo della materia
   * @param int $id Identificativo del documento
   *
   * @return Response Pagina di risposta
   *
   * @Route("/documenti/piani/edit/{classe}/{materia}/{id}", name="documenti_piano_edit",
   *    requirements={"classe": "\d+", "materia": "\d+", "id": "\d+"},
   *    defaults={"id": "0"},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function pianoEditAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                   TranslatorInterface $trans, DocumentiUtil $doc, LogHandler $dblogger, $classe, $materia, $id) {
    // inizializza
    $var_sessione = '/APP/FILE/documenti_piano_edit/files';
    $dir = $this->getParameter('dir_classi').'/';
    $fs = new FileSystem();
    $info = null;
    // controlla classe
    $classe = $em->getRepository('App:Classe')->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    $info['classe'] = $classe->getAnno().'ª '.$classe->getSezione();
    $dir_classe = $classe->getAnno().$classe->getSezione();
    // controlla materia
    $materia = $em->getRepository('App:Materia')->find($materia);
    if (!$materia) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    $info['materia'] = $materia->getNomeBreve();
    // controlla azione
    if ($id > 0) {
      // azione edit
      $documento = $em->getRepository('App:Documento')->findOneBy(['id' => $id, 'tipo' => 'L',
        'classe' => $classe, 'materia' => $materia]);
      if (!$documento) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $documento_old = clone $documento;
    } else {
      // azione add
      $documento = $em->getRepository('App:Documento')->findOneBy(['tipo' => 'L',
        'classe' => $classe, 'materia' => $materia]);
      if ($documento) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $documento = (new Documento())
        ->setTipo('L')
        ->setClasse($classe)
        ->setMateria($materia);
      $em->persist($documento);
    }
    // controllo permessi
    if (!$doc->azioneDocumento(($id > 0 ? 'edit' : 'add'), new \DateTime(), $this->getUser(), ($id > 0 ? $documento : null))) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // legge allegati
    $allegati = array();
    if ($request->isMethod('POST')) {
      // pagina inviata
      foreach ($session->get($var_sessione, []) as $f) {
        if ($f['type'] != 'removed') {
          // aggiunge allegato
          $allegati[] = $f;
        }
      }
    } else {
      // pagina iniziale
      if ($documento->getFile()) {
        $f = new File($dir.$dir_classe.'/'.$documento->getFile());
        $allegati[0]['type'] = 'existent';
        $allegati[0]['temp'] = $documento->getId().'-0.ID';
        $allegati[0]['name'] = $documento->getFile();
        $allegati[0]['size'] = $f->getSize();
      }
      // modifica dati sessione
      $session->remove($var_sessione);
      $session->set($var_sessione, $allegati);
      // elimina file temporanei
      $finder = new Finder();
      $finder->in($this->getParameter('dir_tmp'))->date('< 1 day ago');
      foreach ($finder as $f) {
        $fs->remove($f);
      }
    }
    // imposta docente
    $documento->setDocente($this->getUser());
    // form di inserimento
    $form = $this->container->get('form.factory')->createNamedBuilder('piano_edit', FormType::class)
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start']))
      ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end',
        'onclick' => "location.href='".$this->generateUrl('documenti_piani')."'"]))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // controllo errori
      $f_cnt = 0;
      foreach ($session->get($var_sessione, []) as $f) {
        if ($f['type'] != 'removed') {
          $f_cnt++;
        }
      }
      if ($f_cnt < 1) {
        // errore: nessun file allegati
        $form->addError(new FormError($trans->trans('exception.file_mancante')));
      }
      // modifica dati
      if ($form->isValid()) {
        // directory allegati
        if (!$fs->exists($dir.$dir_classe)) {
          // crea directory
          $fs->mkdir($dir.$dir_classe);
        }
        // rimuove allegati
        foreach ($session->get($var_sessione, []) as $f) {
          if ($f['type'] == 'removed') {
            // rimuove allegato
            $fs->remove($dir.$dir_classe.'/'.$f['name']);
          }
        }
        // carica nuovi allegati
        foreach ($session->get($var_sessione, []) as $f) {
          if ($f['type'] == 'uploaded') {
            // conversione del documento
            $fl = $doc->convertiPDF(new File($this->getParameter('dir_tmp').'/'.$f['temp']));
            $m = strtoupper(preg_replace('/\W+/','-', $materia->getNomeBreve()));
            if (substr($m, -1) == '-') {
              $m = substr($m, 0, -1);
            }
            $nomefile = 'PIANO-DI-LAVORO-'.$dir_classe.'-'.$m.'.'.$fl->guessExtension();
            $documento
              ->setFile($nomefile)
              ->setDimensione($fl->getSize())
              ->setMime($fl->getMimeType());
            // sposta e rinomina allegato
            $fs->rename($fl, $dir.$dir_classe.'/'.$nomefile);
          }
        }
        // ok: memorizza dati
        $em->flush();
        // log azione
        if (!$id) {
          // nuovo
          $dblogger->write($this->getUser(), $request->getClientIp(), 'DOCUMENTI', 'Inserisce piano di lavoro', __METHOD__, array(
            'Id' => $documento->getId(),
            ));
        } else {
          // modifica
          $dblogger->write($this->getUser(), $request->getClientIp(), 'DOCUMENTI', 'Modifica piano di lavoro', __METHOD__, array(
            'Id' => $documento->getId(),
            'File' => $documento_old->getFile(),
            'Docente' => $documento_old->getDocente()->getId(),
            'Classe' => $documento->getClasse()->getId(),
            'Materia' => $documento->getMateria()->getId(),
            ));
        }
        // redirezione
        return $this->redirectToRoute('documenti_piani');
      }
    }
    // mostra la pagina di risposta
    return $this->render('documenti/piano_edit.html.twig', array(
      'pagina_titolo' => 'page.documenti_piani',
      'form' => $form->createView(),
      'form_title' => ($id > 0 ? 'title.modifica_piano' : 'title.nuovo_piano'),
      'info' => $info,
      'allegati' => $allegati,
    ));
  }

  /**
   * Visualizza i documenti dei Consigli di Classe
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param DocumentiUtil $doc Funzioni di utilità per la gestione dei documenti di classe
   * @param int $pagina Numero di pagina per la lista visualizzata
   *
   * @return Response Pagina di risposta
   *
   * @Route("/documenti/classi/{pagina}", name="documenti_classi",
   *    requirements={"pagina": "\d+"},
   *    defaults={"pagina": 0},
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function classiAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                DocumentiUtil $doc, $pagina) {
    // inizializza variabili
    $dati = null;
    $docente = $this->getUser();
    // recupera criteri dalla sessione
    $search = array();
    $search['tipo'] = $session->get('/APP/ROUTE/documenti_classi/tipo', '');
    $search['classe'] = $session->get('/APP/ROUTE/documenti_classi/classe', 0);
    $classe = ($search['classe'] > 0 ? $em->getRepository('App:Classe')->find($search['classe']) : 0);
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $session->get('/APP/ROUTE/documenti_classi/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $session->set('/APP/ROUTE/documenti_classi/pagina', $pagina);
    }
    // form di ricerca
    $limite = 20;
    $form = $this->container->get('form.factory')->createNamedBuilder('documenti_classi', FormType::class)
      ->add('tipo', ChoiceType::class, array('label' => 'label.tipo_documento',
        'data' => $search['tipo'] ? $search['tipo'] : '',
        'choices' => ['label.piani' => 'L', 'label.doc15' => 'M'],
        'placeholder' => 'label.tutti_documenti',
        'label_attr' => ['class' => 'sr-only'],
        'choice_attr' => function($val, $key, $index) {
            return ['class' => 'gs-no-placeholder'];
          },
        'attr' => ['class' => 'gs-placeholder'],
        'required' => false))
      ->add('classe', EntityType::class, array('label' => 'label.classe',
        'data' => $classe,
        'class' => 'App:Classe',
        'choice_label' => function ($obj) {
            return $obj->getAnno().'ª '.$obj->getSezione().' - '.$obj->getCorso()->getNomeBreve();
          },
        'placeholder' => 'label.qualsiasi_classe',
        'query_builder' => function (EntityRepository $er) use ($docente) {
            return $er->createQueryBuilder('c')
              ->join('App:Cattedra', 'ca', 'WITH', 'ca.classe=c.id')
              ->where('ca.docente=:docente')
              ->orderBy('c.anno,c.sezione', 'ASC')
              ->setParameters(['docente' => $docente]);
          },
        'group_by' => function ($obj) {
            return $obj->getSede()->getCitta();
          },
        'label_attr' => ['class' => 'sr-only'],
        'choice_attr' => function($val, $key, $index) {
            return ['class' => 'gs-no-placeholder'];
          },
        'attr' => ['class' => 'gs-placeholder'],
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.filtra'))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $search['tipo'] = $form->get('tipo')->getData();
      $search['classe'] = (is_object($form->get('classe')->getData()) ? $form->get('classe')->getData()->getId() : 0);
      $pagina = 1;
      $session->set('/APP/ROUTE/documenti_classi/tipo', $search['tipo']);
      $session->set('/APP/ROUTE/documenti_classi/classe', $search['classe']);
      $session->set('/APP/ROUTE/documenti_classi/pagina', $pagina);
    }
    // recupera dati
    $dati = $doc->classi($docente, $search, $pagina, $limite);
    // mostra la pagina di risposta
    return $this->render('documenti/classi.html.twig', array(
      'pagina_titolo' => 'page.documenti_classi',
      'form' => $form->createView(),
      'form_help' => null,
      'form_success' => null,
      'page' => $pagina,
      'maxPages' => ceil($dati['lista']->count() / $limite),
      'dati' => $dati,
    ));
  }

  /**
   * Visualizza i documenti del 15 maggio dei docenti
   *
   * @param DocumentiUtil $doc Funzioni di utilità per la gestione dei documenti di classe
   *
   * @return Response Pagina di risposta
   *
   * @Route("/documenti/doc15", name="documenti_doc15",
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function doc15Action(DocumentiUtil $doc) {
    // inizializza variabili
    $dati = null;
    // recupera dati
    $dati = $doc->doc15($this->getUser());
    // mostra la pagina di risposta
    return $this->render('documenti/doc15.html.twig', array(
      'pagina_titolo' => 'page.documenti_doc15',
      'dati' => $dati,
    ));
  }

  /**
   * Aggiunge o modifica un documento del 15 maggio
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param DocumentiUtil $doc Funzioni di utilità per la gestione dei documenti di classe
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $classe Identificativo della classe
   * @param int $id Identificativo del documento
   *
   * @return Response Pagina di risposta
   *
   * @Route("/documenti/doc15/edit/{classe}/{id}", name="documenti_doc15_edit",
   *    requirements={"classe": "\d+", "id": "\d+"},
   *    defaults={"id": "0"},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function doc15EditAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                   TranslatorInterface $trans, DocumentiUtil $doc, LogHandler $dblogger, $classe, $id) {
    // inizializza
    $var_sessione = '/APP/FILE/documenti_doc15_edit/files';
    $dir = $this->getParameter('dir_classi').'/';
    $fs = new FileSystem();
    $info = null;
    // controlla classe
    $classe = $em->getRepository('App:Classe')->findOneBy(['id' => $classe, 'coordinatore' => $this->getUser()]);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    $info['classe'] = $classe->getAnno().'ª '.$classe->getSezione();
    $dir_classe = $classe->getAnno().$classe->getSezione();
    // controlla azione
    if ($id > 0) {
      // azione edit
      $documento = $em->getRepository('App:Documento')->findOneBy(['id' => $id, 'tipo' => 'M',
        'classe' => $classe]);
      if (!$documento) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $documento_old = clone $documento;
    } else {
      // azione add
      $documento = $em->getRepository('App:Documento')->findOneBy(['tipo' => 'M',
        'classe' => $classe]);
      if ($documento) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $documento = (new Documento())
        ->setTipo('M')
        ->setClasse($classe);
      $em->persist($documento);
    }
    // controllo permessi
    if (!$doc->azioneDocumento(($id > 0 ? 'edit' : 'add'), new \DateTime(), $this->getUser(), ($id > 0 ? $documento : null))) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // legge allegati
    $allegati = array();
    if ($request->isMethod('POST')) {
      // pagina inviata
      foreach ($session->get($var_sessione, []) as $f) {
        if ($f['type'] != 'removed') {
          // aggiunge allegato
          $allegati[] = $f;
        }
      }
    } else {
      // pagina iniziale
      if ($documento->getFile()) {
        $f = new File($dir.$dir_classe.'/'.$documento->getFile());
        $allegati[0]['type'] = 'existent';
        $allegati[0]['temp'] = $documento->getId().'-0.ID';
        $allegati[0]['name'] = $documento->getFile();
        $allegati[0]['size'] = $f->getSize();
      }
      // modifica dati sessione
      $session->remove($var_sessione);
      $session->set($var_sessione, $allegati);
      // elimina file temporanei
      $finder = new Finder();
      $finder->in($this->getParameter('dir_tmp'))->date('< 1 day ago');
      foreach ($finder as $f) {
        $fs->remove($f);
      }
    }
    // imposta docente
    $documento->setDocente($this->getUser());
    // form di inserimento
    $form = $this->container->get('form.factory')->createNamedBuilder('doc15_edit', FormType::class)
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start']))
      ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end',
        'onclick' => "location.href='".$this->generateUrl('documenti_doc15')."'"]))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // controllo errori
      $f_cnt = 0;
      foreach ($session->get($var_sessione, []) as $f) {
        if ($f['type'] != 'removed') {
          $f_cnt++;
        }
      }
      if ($f_cnt < 1) {
        // errore: nessun file allegato
        $form->addError(new FormError($trans->trans('exception.file_mancante')));
      }
      // modifica dati
      if ($form->isValid()) {
        // directory allegati
        if (!$fs->exists($dir.$dir_classe)) {
          // crea directory
          $fs->mkdir($dir.$dir_classe);
        }
        // rimuove allegati
        foreach ($session->get($var_sessione, []) as $f) {
          if ($f['type'] == 'removed') {
            // rimuove allegato
            $fs->remove($dir.$dir_classe.'/'.$f['name']);
          }
        }
        // carica nuovi allegati
        foreach ($session->get($var_sessione, []) as $f) {
          if ($f['type'] == 'uploaded') {
            // conversione del documento
            $fl = $doc->convertiPDF(new File($this->getParameter('dir_tmp').'/'.$f['temp']));
            $nomefile = 'DOCUMENTO-15-MAGGIO-'.$dir_classe.'.'.$fl->guessExtension();
            $documento
              ->setFile($nomefile)
              ->setDimensione($fl->getSize())
              ->setMime($fl->getMimeType());
            // sposta e rinomina allegato
            $fs->rename($fl, $dir.$dir_classe.'/'.$nomefile);
          }
        }
        // ok: memorizza dati
        $em->flush();
        // log azione
        if (!$id) {
          // nuovo
          $dblogger->write($this->getUser(), $request->getClientIp(), 'DOCUMENTI', 'Inserisce documento 15 maggio', __METHOD__, array(
            'Id' => $documento->getId(),
            ));
        } else {
          // modifica
          $dblogger->write($this->getUser(), $request->getClientIp(), 'DOCUMENTI', 'Modifica documento 15 maggio', __METHOD__, array(
            'Id' => $documento->getId(),
            'File' => $documento_old->getFile(),
            'Docente' => $documento_old->getDocente()->getId(),
            'Classe' => $documento->getClasse()->getId(),
            'Materia' => ($documento->getMateria() ? $documento->getMateria()->getId() : null),
            ));
        }
        // redirezione
        return $this->redirectToRoute('documenti_doc15');
      }
    }
    // mostra la pagina di risposta
    return $this->render('documenti/doc15_edit.html.twig', array(
      'pagina_titolo' => 'page.documenti_doc15',
      'form' => $form->createView(),
      'form_title' => ($id > 0 ? 'title.modifica_doc15' : 'title.nuovo_doc15'),
      'info' => $info,
      'allegati' => $allegati,
    ));
  }

}
