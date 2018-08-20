<?php
/**
 * giua@school
 *
 * Copyright (c) 2017 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017
 */


namespace AppBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use AppBundle\Entity\Documento;
use AppBundle\Util\LogHandler;
use AppBundle\Util\DocumentiUtil;


/**
 * DocumentiController - gestione dei documenti
 */
class DocumentiController extends Controller {

  /**
   * Visualizza i programmi svolti dei docenti
   *
   * @param DocumentiUtil $doc Funzioni di utilità per la gestione dei documenti di classe
   *
   * @return Response Pagina di risposta
   *
   * @Route("/documenti/programmi", name="documenti_programmi")
   * @Method({"GET"})
   *
   * @Security("has_role('ROLE_DOCENTE')")
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
   *    defaults={"id": "0"})
   * @Method({"GET","POST"})
   *
   * @Security("has_role('ROLE_DOCENTE')")
   */
  public function programmaEditAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                       DocumentiUtil $doc, LogHandler $dblogger, $classe, $materia, $id) {
    // inizializza
    $var_sessione = '/APP/FILE/documenti_programma_edit/files';
    $dir = $this->getParameter('kernel.project_dir').'/documenti/';
    $fs = new FileSystem();
    $info = null;
    // controlla classe
    $classe = $em->getRepository('AppBundle:Classe')->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    $info['classe'] = $classe->getAnno().'ª '.$classe->getSezione();
    $dir_classe = $classe->getAnno().$classe->getSezione();
    // controlla materia
    $materia = $em->getRepository('AppBundle:Materia')->find($materia);
    if (!$materia) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    $info['materia'] = $materia->getNomeBreve();
    // controlla azione
    if ($id > 0) {
      // azione edit
      $documento = $em->getRepository('AppBundle:Documento')->findOneBy(['id' => $id, 'tipo' => 'P',
        'classe' => $classe, 'materia' => $materia]);
      if (!$documento) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $documento_old = clone $documento;
    } else {
      // azione add
      $documento = $em->getRepository('AppBundle:Documento')->findOneBy(['tipo' => 'P',
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
        $f = new File($dir.'classe/'.$dir_classe.'/'.$documento->getFile());
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
      $finder->in($dir.'tmp')->date('< 1 day ago');
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
        $form->addError(new FormError($this->get('translator')->trans('exception.file_mancante')));
      }
      // modifica dati
      if ($form->isValid()) {
        // directory allegati
        if (!$fs->exists($dir.'classe/'.$dir_classe)) {
          // crea directory
          $fs->mkdir($dir.'classe/'.$dir_classe);
        }
        // rimuove allegati
        foreach ($session->get($var_sessione, []) as $f) {
          if ($f['type'] == 'removed') {
            // rimuove allegato
            $fs->remove($dir.'classe/'.$dir_classe.'/'.$f['name']);
          }
        }
        // carica nuovi allegati
        foreach ($session->get($var_sessione, []) as $f) {
          if ($f['type'] == 'uploaded') {
            // conversione del documento
            $fl = $doc->convertiPDF(new File($dir.'tmp/'.$f['temp']));
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
            $fs->rename($fl, $dir.'classe/'.$dir_classe.'/'.$nomefile);
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
   *    requirements={"tipo": "P|R", "id": "\d+"})
   * @Method({"GET"})
   *
   * @Security("has_role('ROLE_DOCENTE')")
   */
  public function documentoDeleteAction(Request $request, EntityManagerInterface $em, LogHandler $dblogger,
                                         DocumentiUtil $doc, $tipo, $id) {
    $dir = $this->getParameter('kernel.project_dir').'/documenti/classe/';
    $fs = new FileSystem();
    // controllo documento
    $documento = $em->getRepository('AppBundle:Documento')->findOneBy(['id' => $id, 'tipo' => $tipo]);
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
      'Materia' => $documento->getMateria()->getId(),
      ));
    // redirezione
    if ($tipo == 'P') {
      // programmi
      return $this->redirectToRoute('documenti_programmi');
    } elseif ($tipo == 'R') {
      // relazioni
      return $this->redirectToRoute('documenti_relazioni');
    }
  }

  /**
   * Visualizza le relaziomni finali dei docenti
   *
   * @param DocumentiUtil $doc Funzioni di utilità per la gestione dei documenti di classe
   *
   * @return Response Pagina di risposta
   *
   * @Route("/documenti/relazioni", name="documenti_relazioni")
   * @Method({"GET"})
   *
   * @Security("has_role('ROLE_DOCENTE')")
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
   *    defaults={"id": "0"})
   * @Method({"GET","POST"})
   *
   * @Security("has_role('ROLE_DOCENTE')")
   */
  public function relazioneEditAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                       DocumentiUtil $doc, LogHandler $dblogger, $classe, $materia, $id) {
    // inizializza
    $var_sessione = '/APP/FILE/documenti_relazione_edit/files';
    $dir = $this->getParameter('kernel.project_dir').'/documenti/';
    $fs = new FileSystem();
    $info = null;
    // controlla classe
    $classe = $em->getRepository('AppBundle:Classe')->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    $info['classe'] = $classe->getAnno().'ª '.$classe->getSezione();
    $dir_classe = $classe->getAnno().$classe->getSezione();
    // controlla materia
    $materia = $em->getRepository('AppBundle:Materia')->find($materia);
    if (!$materia) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    $info['materia'] = $materia->getNomeBreve();
    // controlla azione
    if ($id > 0) {
      // azione edit
      $documento = $em->getRepository('AppBundle:Documento')->findOneBy(['id' => $id, 'tipo' => 'R',
        'classe' => $classe, 'materia' => $materia]);
      if (!$documento) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $documento_old = clone $documento;
    } else {
      // azione add
      $documento = $em->getRepository('AppBundle:Documento')->findOneBy(['tipo' => 'R',
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
        $f = new File($dir.'classe/'.$dir_classe.'/'.$documento->getFile());
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
      $finder->in($dir.'tmp')->date('< 1 day ago');
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
        $form->addError(new FormError($this->get('translator')->trans('exception.file_mancante')));
      }
      // modifica dati
      if ($form->isValid()) {
        // directory allegati
        if (!$fs->exists($dir.'classe/'.$dir_classe)) {
          // crea directory
          $fs->mkdir($dir.'classe/'.$dir_classe);
        }
        // rimuove allegati
        foreach ($session->get($var_sessione, []) as $f) {
          if ($f['type'] == 'removed') {
            // rimuove allegato
            $fs->remove($dir.'classe/'.$dir_classe.'/'.$f['name']);
          }
        }
        // carica nuovi allegati
        foreach ($session->get($var_sessione, []) as $f) {
          if ($f['type'] == 'uploaded') {
            // conversione del documento
            $fl = $doc->convertiPDF(new File($dir.'tmp/'.$f['temp']));
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
            $fs->rename($fl, $dir.'classe/'.$dir_classe.'/'.$nomefile);
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

}

