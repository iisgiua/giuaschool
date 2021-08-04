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
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Translation\TranslatorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use App\Entity\Documento;
use App\Entity\ListaDestinatari;
use App\Entity\File;
use App\Entity\Classe;
use App\Entity\Materia;
use App\Entity\Alunno;
use App\Form\DocumentoType;
use App\Util\LogHandler;
use App\Util\DocumentiUtil;


/**
 * DocumentiController - gestione dei documenti
 */
class DocumentiController extends AbstractController {

  /**
   * Gestione inserimento dei programmi svolti dei docenti
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
    // recupera dati
    $dati = $doc->programmiDocente($this->getUser());
    // mostra la pagina di risposta
    return $this->render('documenti/programmi.html.twig', array(
      'pagina_titolo' => 'page.documenti_programmi',
      'dati' => $dati));
  }

  /**
   * Aggiunge un programma svolto
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param DocumentiUtil $doc Funzioni di utilità per la gestione dei documenti di classe
   * @param LogHandler $dblogger Gestore dei log su database
   * @param Classe $classe Classe di riferimento per il documento
   * @param Materia $materia Materia di riferimento per il documento
   *
   * @return Response Pagina di risposta
   *
   * @Route("/documenti/programmi/add/{classe}/{materia}", name="documenti_programmi_add",
   *    requirements={"classe": "\d+", "materia": "\d+"},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function programmiAddAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                     TranslatorInterface $trans, DocumentiUtil $doc, LogHandler $dblogger,
                                     Classe $classe, Materia $materia) {
    // inizializza
    $info = [];
    $varSessione = '/APP/FILE/documenti_programmi_add/files';
    if ($request->isMethod('GET')) {
      // inizializza sessione per allegati
      $session->set($varSessione, []);
    }
    // controlla azione
    $documentoEsistente = $em->getRepository('App:Documento')->findOneBy(['tipo' => 'P',
      'classe' => $classe, 'materia' => $materia]);
    if ($documentoEsistente) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // crea documento
    $documento = (new Documento())
      ->setTipo('P')
      ->setDocente($this->getUser())
      ->setClasse($classe)
      ->setMateria($materia)
      ->setListaDestinatari(new ListaDestinatari());
    $em->persist($documento);
    // controllo permessi
    if (!$doc->azioneDocumento('add', $this->getUser(), $documento)) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // informazioni da visualizzare
    $info['classe'] = $documento->getClasse()->getAnno().'ª '.$documento->getClasse()->getSezione();
    $info['materia'] = $documento->getMateria()->getNomeBreve();
    // form di inserimento
    $form = $this->createForm(DocumentoType::class, $documento, [
      'returnUrl' => $this->generateUrl('documenti_programmi'), 'formMode' => 'P']);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // controllo errori
      $allegati = $session->get($varSessione, []);
      if (count($allegati) < 1) {
        $form->addError(new FormError($trans->trans('exception.file_mancante')));
      } else {
        // imposta destinatari
        $doc->impostaDestinatari($documento);
        // conversione pfd
        list($file, $estensione) = $doc->convertePdf($allegati[0]['temp']);
        // imposta allegato
        $doc->impostaUnAllegato($documento, $file, $estensione, $allegati[0]['size']);
        // rimuove sessione con gli allegati
        $session->remove($varSessione);
        // ok: memorizzazione e log
        $dblogger->logCreazione('DOCUMENTI', 'Inserimento programma svolto', $documento);
        // redirezione
        return $this->redirectToRoute('documenti_programmi');
      }
    }
    // mostra la pagina di risposta
    return $this->render('documenti/programmi_add.html.twig', array(
      'pagina_titolo' => 'page.documenti_programmi',
      'form' => $form->createView(),
      'form_title' => 'title.nuovo_programma',
      'info' => $info));
  }

  /**
   * Cancella il documento indicato
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param LogHandler $dblogger Gestore dei log su database
   * @param DocumentiUtil $doc Funzioni di utilità per la gestione dei documenti
   * @param Documento $documento Documento da cancellare
   *
   * @return Response Pagina di risposta
   *
   * @Route("/documenti/delete/{documento}", name="documenti_delete",
   *    requirements={"documento": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function deleteAction(EntityManagerInterface $em, LogHandler $dblogger, DocumentiUtil $doc,
                               Documento $documento) {
    // controllo permessi
    if (!$doc->azioneDocumento('delete', $this->getUser(), $documento)) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // copia per log
    $vecchioDocumento = clone $documento;
    // cancella documento
    $em->remove($documento);
    // cancella lista destinatari
    $doc->cancellaDestinatari($documento);
    // cancella allegati
    foreach ($documento->getAllegati() as $allegato) {
      $em->remove($allegato);
    }
    // memorizzazione e log
    $dblogger->logRimozione('DOCUMENTI', 'Cancella documento', $vecchioDocumento);
    // cancella file
    $dir = $doc->documentoDir($documento);
    foreach ($documento->getAllegati() as $allegato) {
      unlink($dir.'/'.$allegato->getFile().'.'.$allegato->getEstensione());
    }
    // redirezione
    switch ($documento->getTipo()) {
      case 'P':
        // programmi finali
        $pagina = 'documenti_programmi';
        break;
      case 'R':
        // relazioni finali
        $pagina = 'documenti_relazioni';
        break;
      case 'M':
        // documento 15 maggio
        $pagina = 'documenti_maggio';
        break;
      default:
        // piani di lavoro
        $pagina = 'documenti_piani';
    }
    return $this->redirectToRoute($pagina);
  }

  /**
   * Gestione inserimento delle relazioni finali dei docenti
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
    // recupera dati
    $dati = $doc->relazioniDocente($this->getUser());
    // mostra la pagina di risposta
    return $this->render('documenti/relazioni.html.twig', array(
      'pagina_titolo' => 'page.documenti_relazioni',
      'dati' => $dati));
  }

  /**
   * Aggiunge una nuova relazione
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param DocumentiUtil $doc Funzioni di utilità per la gestione dei documenti di classe
   * @param LogHandler $dblogger Gestore dei log su database
   * @param Classe $classe Classe di riferimento per il documento
   * @param Materia $materia Materia di riferimento per il documento
   * @param Alunno $alunno Alunno di riferimento per il documento
   *
   * @return Response Pagina di risposta
   *
   * @Route("/documenti/relazioni/add/{classe}/{materia}/{alunno}", name="documenti_relazioni_add",
   *    requirements={"classe": "\d+", "materia": "\d+", "alunno": "\d+"},
   *    defaults={"alunno": "0"},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function relazioniAddAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                     TranslatorInterface $trans, DocumentiUtil $doc, LogHandler $dblogger,
                                     Classe $classe, Materia $materia, Alunno $alunno=null) {
    // inizializza
    $info = [];
    $varSessione = '/APP/FILE/documenti_relazioni_add/files';
    if ($request->isMethod('GET')) {
      // inizializza sessione per allegati
      $session->set($varSessione, []);
    }
    // controlla azione
    $documentoEsistente = $em->getRepository('App:Documento')->findOneBy(['tipo' => 'R',
      'classe' => $classe, 'materia' => $materia, 'alunno' => $alunno]);
    if ($documentoEsistente) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // crea documento
    $documento = (new Documento())
      ->setTipo('R')
      ->setDocente($this->getUser())
      ->setClasse($classe)
      ->setMateria($materia)
      ->setAlunno($alunno)
      ->setListaDestinatari(new ListaDestinatari());
    $em->persist($documento);
    // controllo permessi
    if (!$doc->azioneDocumento('add', $this->getUser(), $documento)) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // informazioni da visualizzare
    $info['classe'] = $documento->getClasse()->getAnno().'ª '.$documento->getClasse()->getSezione();
    $info['materia'] = $documento->getMateria()->getNomeBreve().($documento->getAlunno() ?
      ' - '.$documento->getAlunno()->getCognome().' '.$documento->getAlunno()->getNome() : '');
    // form di inserimento
    $form = $this->createForm(DocumentoType::class, $documento, [
      'returnUrl' => $this->generateUrl('documenti_relazioni'), 'formMode' => 'R']);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // controllo errori
      $allegati = $session->get($varSessione, []);
      if (count($allegati) < 1) {
        $form->addError(new FormError($trans->trans('exception.file_mancante')));
      } else {
        // imposta destinatari
        $doc->impostaDestinatari($documento);
        // conversione pfd
        list($file, $estensione) = $doc->convertePdf($allegati[0]['temp']);
        // imposta allegato
        $doc->impostaUnAllegato($documento, $file, $estensione, $allegati[0]['size']);
        // rimuove sessione con gli allegati
        $session->remove($varSessione);
        // ok: memorizzazione e log
        $dblogger->logCreazione('DOCUMENTI', 'Inserimento relazione finale', $documento);
        // redirezione
        return $this->redirectToRoute('documenti_relazioni');
      }
    }
    // mostra la pagina di risposta
    return $this->render('documenti/relazioni_add.html.twig', array(
      'pagina_titolo' => 'page.documenti_relazioni',
      'form' => $form->createView(),
      'form_title' => 'title.nuova_relazione',
      'info' => $info));
  }

  /**
   * Gestione inserimento dei piani di lavoro dei docenti
   *
   * @param DocumentiUtil $doc Funzioni di utilità per la gestione dei documenti
   *
   * @return Response Pagina di risposta
   *
   * @Route("/documenti/piani", name="documenti_piani",
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function pianiAction(DocumentiUtil $doc) {
    // recupera dati
    $dati = $doc->pianiDocente($this->getUser());
    // mostra la pagina di risposta
    return $this->render('documenti/piani.html.twig', array(
      'pagina_titolo' => 'page.documenti_piani',
      'dati' => $dati));
  }

  /**
   * Aggiunge un nuovo piano di lavoro
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param DocumentiUtil $doc Funzioni di utilità per la gestione dei documenti di classe
   * @param LogHandler $dblogger Gestore dei log su database
   * @param Classe $classe Classe di riferimento per il documento
   * @param Materia $materia Materia di riferimento per il documento
   *
   * @return Response Pagina di risposta
   *
   * @Route("/documenti/piani/add/{classe}/{materia}", name="documenti_piani_add",
   *    requirements={"classe": "\d+", "materia": "\d+"},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function pianiAddAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                 TranslatorInterface $trans, DocumentiUtil $doc, LogHandler $dblogger,
                                 CLasse $classe, Materia $materia) {
    // inizializza
    $info = [];
    $varSessione = '/APP/FILE/documenti_piani_add/files';
    if ($request->isMethod('GET')) {
      // inizializza sessione per allegati
      $session->set($varSessione, []);
    }
    // controlla azione
    $documentoEsistente = $em->getRepository('App:Documento')->findOneBy(['tipo' => 'L',
      'classe' => $classe, 'materia' => $materia]);
    if ($documentoEsistente) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // crea documento
    $documento = (new Documento())
      ->setTipo('L')
      ->setDocente($this->getUser())
      ->setClasse($classe)
      ->setMateria($materia)
      ->setListaDestinatari(new ListaDestinatari());
    $em->persist($documento);
    // controllo permessi
    if (!$doc->azioneDocumento('add', $this->getUser(), $documento)) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // informazioni da visualizzare
    $info['classe'] = $documento->getClasse()->getAnno().'ª '.$documento->getClasse()->getSezione();
    $info['materia'] = $documento->getMateria()->getNomeBreve();
    // form di inserimento
    $form = $this->createForm(DocumentoType::class, $documento, [
      'returnUrl' => $this->generateUrl('documenti_piani'), 'formMode' => 'L']);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // controllo errori
      $allegati = $session->get($varSessione, []);
      if (count($allegati) < 1) {
        $form->addError(new FormError($trans->trans('exception.file_mancante')));
      } else {
        // imposta destinatari
        $doc->impostaDestinatari($documento);
        // conversione pfd
        list($file, $estensione) = $doc->convertePdf($allegati[0]['temp']);
        // imposta allegato
        $doc->impostaUnAllegato($documento, $file, $estensione, $allegati[0]['size']);
        // rimuove sessione con gli allegati
        $session->remove($varSessione);
        // ok: memorizzazione e log
        $dblogger->logCreazione('DOCUMENTI', 'Inserimento piano di lavoro', $documento);
        // redirezione
        return $this->redirectToRoute('documenti_piani');
      }
    }
    // mostra la pagina di risposta
    return $this->render('documenti/piani_add.html.twig', array(
      'pagina_titolo' => 'page.documenti_piani',
      'form' => $form->createView(),
      'form_title' => 'title.nuovo_piano',
      'info' => $info));
  }

  /**
   * Gestione inserimento dei documenti del 15 maggio
   *
   * @param DocumentiUtil $doc Funzioni di utilità per la gestione dei documenti di classe
   *
   * @return Response Pagina di risposta
   *
   * @Route("/documenti/maggio", name="documenti_maggio",
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function maggioAction(DocumentiUtil $doc) {
    // recupera dati
    $dati = $doc->maggioDocente($this->getUser());
    // mostra la pagina di risposta
    return $this->render('documenti/maggio.html.twig', array(
      'pagina_titolo' => 'page.documenti_maggio',
      'dati' => $dati));
  }

  /**
   * Aggiunge un documento del 15 maggio
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param DocumentiUtil $doc Funzioni di utilità per la gestione dei documenti di classe
   * @param LogHandler $dblogger Gestore dei log su database
   * @param Classe $classe Classe di riferimento per il documento
   *
   * @return Response Pagina di risposta
   *
   * @Route("/documenti/maggio/add/{classe}", name="documenti_maggio_add",
   *    requirements={"classe": "\d+"},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function maggioAddAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                  TranslatorInterface $trans, DocumentiUtil $doc, LogHandler $dblogger,
                                  Classe $classe) {
    // inizializza
    $info = [];
    $varSessione = '/APP/FILE/documenti_maggio_add/files';
    if ($request->isMethod('GET')) {
      // inizializza sessione per allegati
      $session->set($varSessione, []);
    }
    // controlla azione
    $documentoEsistente = $em->getRepository('App:Documento')->findOneBy(['tipo' => 'M',
      'classe' => $classe]);
    if ($documentoEsistente) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // crea documento
    $documento = (new Documento())
      ->setTipo('M')
      ->setDocente($this->getUser())
      ->setClasse($classe)
      ->setListaDestinatari(new ListaDestinatari());
    $em->persist($documento);
    // controllo permessi
    if (!$doc->azioneDocumento('add', $this->getUser(), $documento)) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // informazioni da visualizzare
    $info['classe'] = $documento->getClasse()->getAnno().'ª '.$documento->getClasse()->getSezione();
    // form di inserimento
    $form = $this->createForm(DocumentoType::class, $documento, [
      'returnUrl' => $this->generateUrl('documenti_maggio'), 'formMode' => 'M']);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // controllo errori
      $allegati = $session->get($varSessione, []);
      if (count($allegati) < 1) {
        $form->addError(new FormError($trans->trans('exception.file_mancante')));
      } else {
        // imposta destinatari
        $doc->impostaDestinatari($documento);
        // conversione pfd
        list($file, $estensione) = $doc->convertePdf($allegati[0]['temp']);
        // imposta allegato
        $doc->impostaUnAllegato($documento, $file, $estensione, $allegati[0]['size']);
        // rimuove sessione con gli allegati
        $session->remove($varSessione);
        // ok: memorizzazione e log
        $dblogger->logCreazione('DOCUMENTI', 'Inserimento documento del 15 maggio', $documento);
        // redirezione
        return $this->redirectToRoute('documenti_maggio');
      }
    }
    // mostra la pagina di risposta
    return $this->render('documenti/maggio_add.html.twig', array(
      'pagina_titolo' => 'page.documenti_maggio',
      'form' => $form->createView(),
      'form_title' => 'title.nuovo_maggio',
      'info' => $info));
  }

  /**
   * Scarica uno degli allegati al documento indicato
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param DocumentiUtil $doc Funzioni di utilità per la gestione dei documenti di classe
   * @param Documento $documento Documento a cui appartiene l'allegato
   * @param File|null $allegato Allegato da scaricare, o null per il primo del documento
   *
   * @return Response Pagina di risposta
   *
   * @Route("/documenti/download/{documento}/{allegato}", name="documenti_download",
   *    requirements={"documento": "\d+", "allegato": "\d+"},
   *    defaults={"allegato": "0"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_UTENTE")
   */
  public function downloadAction(EntityManagerInterface $em, DocumentiUtil $doc,
                                 Documento $documento, File $allegato=null) {
    // controlla allegato
    if ($allegato && !$documento->getAllegati()->contains($allegato)) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    if (!$allegato) {
      // prende il primo allegato
      $allegato = $documento->getAllegati()[0];
    }
    // controllo permesso lettura
    if (!$doc->permessoLettura($this->getUser(), $documento)) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // segna lettura e memorizza su db
    $doc->leggeUtente($this->getUser(), $documento);
    $em->flush();
    // invia il file
    return $this->file($doc->documentoDir($documento).'/'.$allegato->getFile().'.'.$allegato->getEstensione(),
      $allegato->getNome().'.'.$allegato->getEstensione(), ResponseHeaderBag::DISPOSITION_ATTACHMENT);
  }

}
