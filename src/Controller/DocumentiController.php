<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Controller;

use App\Entity\Alunno;
use App\Entity\Classe;
use App\Entity\Docente;
use App\Entity\Documento;
use App\Entity\File;
use App\Entity\Genitore;
use App\Entity\ListaDestinatari;
use App\Entity\Materia;
use App\Form\DocumentoType;
use App\Util\DocumentiUtil;
use App\Util\LogHandler;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;


/**
 * DocumentiController - gestione dei documenti
 *
 * @author Antonello Dessì
 */
class DocumentiController extends BaseController {

  /**
   * Gestione inserimento dei programmi svolti dei docenti
   *
   * @param DocumentiUtil $doc Funzioni di utilità per la gestione dei documenti di classe
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/documenti/programmi', name: 'documenti_programmi', methods: ['GET'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function programmi(DocumentiUtil $doc): Response {
    $programmiQuinte = $this->reqstack->getSession()->get('/CONFIG/SCUOLA/programmi_quinte') == 'S';
    // recupera dati
    $dati = $doc->programmiDocente($this->getUser(), $programmiQuinte);
    // mostra la pagina di risposta
    return $this->render('documenti/programmi.html.twig', [
      'pagina_titolo' => 'page.documenti_programmi',
      'dati' => $dati]);
  }

  /**
   * Aggiunge un programma svolto
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param DocumentiUtil $doc Funzioni di utilità per la gestione dei documenti di classe
   * @param LogHandler $dblogger Gestore dei log su database
   * @param Classe $classe Classe di riferimento per il documento
   * @param Materia $materia Materia di riferimento per il documento
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/documenti/programmi/add/{classe}/{materia}', name: 'documenti_programmi_add', requirements: ['classe' => '\d+', 'materia' => '\d+'], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function programmiAdd(Request $request, TranslatorInterface $trans, DocumentiUtil $doc,
                               LogHandler $dblogger, Classe $classe, Materia $materia): Response {
    // inizializza
    $info = [];
    $programmiQuinte = $this->reqstack->getSession()->get('/CONFIG/SCUOLA/programmi_quinte') == 'S';
    $varSessione = '/APP/FILE/documenti_programmi_add/files';
    if ($request->isMethod('GET')) {
      // inizializza sessione per allegati
      $this->reqstack->getSession()->set($varSessione, []);
    }
    // controlla azione
    $documentoEsistente = $this->em->getRepository(Documento::class)->findOneBy(['tipo' => 'P',
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
    $this->em->persist($documento);
    // controllo permessi
    if (!$doc->azioneDocumento('add', $this->getUser(), $documento, $programmiQuinte)) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // informazioni da visualizzare
    $info['classe'] = ''.$documento->getClasse();
    $info['materia'] = $documento->getMateria()->getNomeBreve();
    // form di inserimento
    $form = $this->createForm(DocumentoType::class, null, [
      'return_url' => $this->generateUrl('documenti_programmi'), 'form_mode' => 'P']);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // controllo errori
      $allegati = $this->reqstack->getSession()->get($varSessione, []);
      if (count($allegati) < 1) {
        $form->addError(new FormError($trans->trans('exception.file_mancante')));
      } else {
        // imposta destinatari
        $doc->impostaDestinatari($documento);
        // conversione pfd
        [$file, $estensione] = $doc->convertePdf($allegati[0]['temp']);
        // imposta allegato
        $doc->impostaUnAllegato($documento, $file, $estensione, $allegati[0]['size']);
        // rimuove sessione con gli allegati
        $this->reqstack->getSession()->remove($varSessione);
        // ok: memorizzazione e log
        $dblogger->logCreazione('DOCUMENTI', 'Inserimento programma svolto', $documento);
        // redirezione
        return $this->redirectToRoute('documenti_programmi');
      }
    }
    // mostra la pagina di risposta
    return $this->render('documenti/programmi_add.html.twig', [
      'pagina_titolo' => 'page.documenti_programmi',
      'form' => $form,
      'form_title' => 'title.nuovo_programma',
      'info' => $info]);
  }

  /**
   * Cancella il documento indicato
   *
   * @param LogHandler $dblogger Gestore dei log su database
   * @param DocumentiUtil $doc Funzioni di utilità per la gestione dei documenti
   * @param Documento $documento Documento da cancellare
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/documenti/delete/{documento}', name: 'documenti_delete', requirements: ['documento' => '\d+'], methods: ['GET'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function delete(LogHandler $dblogger, DocumentiUtil $doc, Documento $documento): Response {
    // controllo permessi
    if (!$doc->azioneDocumento('delete', $this->getUser(), $documento)) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // copia per log
    $vecchioDocumento = clone $documento;
    // cancella documento
    $this->em->remove($documento);
    // cancella lista destinatari
    $doc->cancellaDestinatari($documento);
    // cancella allegati
    foreach ($documento->getAllegati() as $allegato) {
      $this->em->remove($allegato);
    }
    // memorizzazione e log
    $dblogger->logRimozione('DOCUMENTI', 'Cancella documento', $vecchioDocumento);
    // cancella file
    foreach ($documento->getAllegati() as $allegato) {
      $dir = $doc->documentoDir($documento);
      if (!file_exists($dir.'/'.$allegato->getFile().'.'.$allegato->getEstensione())) {
        // compatibilità con vecchi documenti
        $dir = $this->getParameter('kernel.project_dir').'/FILES/archivio/classi/'.
          $documento->getAlunno()->getClasse()->getAnno().$documento->getAlunno()->getClasse()->getSezione().
          $documento->getAlunno()->getClasse()->getGruppo().'/riservato/';
      }
      unlink($dir.'/'.$allegato->getFile().'.'.$allegato->getEstensione());
    }
    // redirezione
    $pagina = match ($documento->getTipo()) {
        'P' => 'documenti_programmi',
        'R' => 'documenti_relazioni',
        'M' => 'documenti_maggio',
        'B', 'H', 'D', 'C' => $documento->getStato() == 'A' ? 'documenti_archivio_bes' : 'documenti_bes',
        default => 'documenti_piani',
    };
    return $this->redirectToRoute($pagina);
  }

  /**
   * Gestione inserimento delle relazioni finali dei docenti
   *
   * @param DocumentiUtil $doc Funzioni di utilità per la gestione dei documenti di classe
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/documenti/relazioni', name: 'documenti_relazioni', methods: ['GET'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function relazioni(DocumentiUtil $doc): Response {
    // recupera dati
    $dati = $doc->relazioniDocente($this->getUser());
    // mostra la pagina di risposta
    return $this->render('documenti/relazioni.html.twig', [
      'pagina_titolo' => 'page.documenti_relazioni',
      'dati' => $dati]);
  }

  /**
   * Aggiunge una nuova relazione
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param DocumentiUtil $doc Funzioni di utilità per la gestione dei documenti di classe
   * @param LogHandler $dblogger Gestore dei log su database
   * @param Classe $classe Classe di riferimento per il documento
   * @param Materia $materia Materia di riferimento per il documento
   * @param Alunno $alunno Alunno di riferimento per il documento
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/documenti/relazioni/add/{classe}/{materia}/{alunno}', name: 'documenti_relazioni_add', requirements: ['classe' => '\d+', 'materia' => '\d+', 'alunno' => '\d+'], defaults: ['alunno' => '0'], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function relazioniAdd(Request $request, TranslatorInterface $trans, DocumentiUtil $doc,
                               LogHandler $dblogger, Classe $classe, Materia $materia,
                               Alunno $alunno=null): Response {
    // inizializza
    $info = [];
    $varSessione = '/APP/FILE/documenti_relazioni_add/files';
    if ($request->isMethod('GET')) {
      // inizializza sessione per allegati
      $this->reqstack->getSession()->set($varSessione, []);
    }
    // controlla azione
    $documentoEsistente = $this->em->getRepository(Documento::class)->findOneBy(['tipo' => 'R',
      'classe' => $classe, 'materia' => $materia, 'alunno' => $alunno, 'docente' => $this->getUser()]);
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
    $this->em->persist($documento);
    // controllo permessi
    if (!$doc->azioneDocumento('add', $this->getUser(), $documento)) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // informazioni da visualizzare
    $info['classe'] = ''.$documento->getClasse();
    $info['materia'] = $documento->getMateria()->getNomeBreve().($documento->getAlunno() ?
      ' - '.$documento->getAlunno()->getCognome().' '.$documento->getAlunno()->getNome() : '');
    // form di inserimento
    $form = $this->createForm(DocumentoType::class, null, [
      'return_url' => $this->generateUrl('documenti_relazioni'), 'form_mode' => 'R']);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // controllo errori
      $allegati = $this->reqstack->getSession()->get($varSessione, []);
      if (count($allegati) < 1) {
        $form->addError(new FormError($trans->trans('exception.file_mancante')));
      } else {
        // imposta destinatari
        $doc->impostaDestinatari($documento);
        // conversione pfd
        [$file, $estensione] = $doc->convertePdf($allegati[0]['temp']);
        // imposta allegato
        $doc->impostaUnAllegato($documento, $file, $estensione, $allegati[0]['size']);
        // rimuove sessione con gli allegati
        $this->reqstack->getSession()->remove($varSessione);
        // ok: memorizzazione e log
        $dblogger->logCreazione('DOCUMENTI', 'Inserimento relazione finale', $documento);
        // redirezione
        return $this->redirectToRoute('documenti_relazioni');
      }
    }
    // mostra la pagina di risposta
    return $this->render('documenti/relazioni_add.html.twig', [
      'pagina_titolo' => 'page.documenti_relazioni',
      'form' => $form,
      'form_title' => 'title.nuova_relazione',
      'info' => $info]);
  }

  /**
   * Gestione inserimento dei piani di lavoro dei docenti
   *
   * @param DocumentiUtil $doc Funzioni di utilità per la gestione dei documenti
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/documenti/piani', name: 'documenti_piani', methods: ['GET'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function piani(DocumentiUtil $doc): Response {
    // recupera dati
    $dati = $doc->pianiDocente($this->getUser());
    // mostra la pagina di risposta
    return $this->render('documenti/piani.html.twig', [
      'pagina_titolo' => 'page.documenti_piani',
      'dati' => $dati]);
  }

  /**
   * Aggiunge un nuovo piano di lavoro
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param DocumentiUtil $doc Funzioni di utilità per la gestione dei documenti di classe
   * @param LogHandler $dblogger Gestore dei log su database
   * @param Classe $classe Classe di riferimento per il documento
   * @param Materia $materia Materia di riferimento per il documento
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/documenti/piani/add/{classe}/{materia}', name: 'documenti_piani_add', requirements: ['classe' => '\d+', 'materia' => '\d+'], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function pianiAdd(Request $request, TranslatorInterface $trans, DocumentiUtil $doc,
                           LogHandler $dblogger, Classe $classe, Materia $materia): Response {
    // inizializza
    $info = [];
    $varSessione = '/APP/FILE/documenti_piani_add/files';
    if ($request->isMethod('GET')) {
      // inizializza sessione per allegati
      $this->reqstack->getSession()->set($varSessione, []);
    }
    // controlla azione
    $documentoEsistente = $this->em->getRepository(Documento::class)->findOneBy(['tipo' => 'L',
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
    $this->em->persist($documento);
    // controllo permessi
    if (!$doc->azioneDocumento('add', $this->getUser(), $documento)) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // informazioni da visualizzare
    $info['classe'] = ''.$documento->getClasse();
    $info['materia'] = $documento->getMateria()->getNomeBreve();
    // form di inserimento
    $form = $this->createForm(DocumentoType::class, null, [
      'return_url' => $this->generateUrl('documenti_piani'), 'form_mode' => 'L']);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // controllo errori
      $allegati = $this->reqstack->getSession()->get($varSessione, []);
      if (count($allegati) < 1) {
        $form->addError(new FormError($trans->trans('exception.file_mancante')));
      } else {
        // imposta destinatari
        $doc->impostaDestinatari($documento);
        // conversione pfd
        [$file, $estensione] = $doc->convertePdf($allegati[0]['temp']);
        // imposta allegato
        $doc->impostaUnAllegato($documento, $file, $estensione, $allegati[0]['size']);
        // rimuove sessione con gli allegati
        $this->reqstack->getSession()->remove($varSessione);
        // ok: memorizzazione e log
        $dblogger->logCreazione('DOCUMENTI', 'Inserimento piano di lavoro', $documento);
        // redirezione
        return $this->redirectToRoute('documenti_piani');
      }
    }
    // mostra la pagina di risposta
    return $this->render('documenti/piani_add.html.twig', [
      'pagina_titolo' => 'page.documenti_piani',
      'form' => $form,
      'form_title' => 'title.nuovo_piano',
      'info' => $info]);
  }

  /**
   * Gestione inserimento dei documenti del 15 maggio
   *
   * @param DocumentiUtil $doc Funzioni di utilità per la gestione dei documenti di classe
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/documenti/maggio', name: 'documenti_maggio', methods: ['GET'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function maggio(DocumentiUtil $doc): Response {
    // recupera dati
    $dati = $doc->maggioDocente($this->getUser());
    // mostra la pagina di risposta
    return $this->render('documenti/maggio.html.twig', [
      'pagina_titolo' => 'page.documenti_maggio',
      'dati' => $dati]);
  }

  /**
   * Aggiunge un documento del 15 maggio
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param DocumentiUtil $doc Funzioni di utilità per la gestione dei documenti di classe
   * @param LogHandler $dblogger Gestore dei log su database
   * @param Classe $classe Classe di riferimento per il documento
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/documenti/maggio/add/{classe}', name: 'documenti_maggio_add', requirements: ['classe' => '\d+'], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function maggioAdd(Request $request, TranslatorInterface $trans, DocumentiUtil $doc,
                            LogHandler $dblogger, Classe $classe): Response {
    // inizializza
    $info = [];
    $varSessione = '/APP/FILE/documenti_maggio_add/files';
    if ($request->isMethod('GET')) {
      // inizializza sessione per allegati
      $this->reqstack->getSession()->set($varSessione, []);
    }
    // controlla azione
    $documentoEsistente = $this->em->getRepository(Documento::class)->findOneBy(['tipo' => 'M',
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
    $this->em->persist($documento);
    // controllo permessi
    if (!$doc->azioneDocumento('add', $this->getUser(), $documento)) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // informazioni da visualizzare
    $info['classe'] = ''.$documento->getClasse();
    // form di inserimento
    $form = $this->createForm(DocumentoType::class, null, [
      'return_url' => $this->generateUrl('documenti_maggio'), 'form_mode' => 'M']);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // controllo errori
      $allegati = $this->reqstack->getSession()->get($varSessione, []);
      if (count($allegati) < 1) {
        $form->addError(new FormError($trans->trans('exception.file_mancante')));
      } else {
        // imposta destinatari
        $doc->impostaDestinatari($documento);
        // conversione pfd
        [$file, $estensione] = $doc->convertePdf($allegati[0]['temp']);
        // imposta allegato
        $doc->impostaUnAllegato($documento, $file, $estensione, $allegati[0]['size']);
        // rimuove sessione con gli allegati
        $this->reqstack->getSession()->remove($varSessione);
        // ok: memorizzazione e log
        $dblogger->logCreazione('DOCUMENTI', 'Inserimento documento del 15 maggio', $documento);
        // redirezione
        return $this->redirectToRoute('documenti_maggio');
      }
    }
    // mostra la pagina di risposta
    return $this->render('documenti/maggio_add.html.twig', [
      'pagina_titolo' => 'page.documenti_maggio',
      'form' => $form,
      'form_title' => 'title.nuovo_maggio',
      'info' => $info]);
  }

  /**
   * Scarica uno degli allegati al documento indicato
   *
   * @param DocumentiUtil $doc Funzioni di utilità per la gestione dei documenti di classe
   * @param Documento $documento Documento a cui appartiene l'allegato
   * @param File|null $allegato Allegato da scaricare, o null per il primo del documento
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/documenti/download/{documento}/{allegato}', name: 'documenti_download', requirements: ['documento' => '\d+', 'allegato' => '\d+'], defaults: ['allegato' => '0'], methods: ['GET'])]
  #[IsGranted('ROLE_UTENTE')]
  public function download(DocumentiUtil $doc, Documento $documento,
                           File $allegato = null): Response {
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
    $this->em->flush();
    // invia il file
    $nomefile = $doc->documentoDir($documento).'/'.$allegato->getFile().'.'.$allegato->getEstensione();
    if (!file_exists($nomefile)) {
      // compatibilità con vecchi documenti
      $nomefile = $this->getParameter('kernel.project_dir').'/FILES/archivio/classi/'.
        $documento->getAlunno()->getClasse()->getAnno().$documento->getAlunno()->getClasse()->getSezione().
        $documento->getAlunno()->getClasse()->getGruppo().'/riservato/'.
        $allegato->getFile().'.'.$allegato->getEstensione();
    }
    return $this->file($nomefile, $allegato->getNome().'.'.$allegato->getEstensione(),
      ResponseHeaderBag::DISPOSITION_ATTACHMENT);
  }

  /**
   * Visualizza i documenti dei docenti
   *
   * @param Request $request Pagina richiesta
   * @param DocumentiUtil $doc Funzioni di utilità per la gestione dei documenti di classe
   * @param int $pagina Numero di pagina per la lista visualizzata
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/documenti/docenti/{pagina}', name: 'documenti_docenti', requirements: ['pagina' => '\d+'], defaults: ['pagina' => 0], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_STAFF')]
  public function docenti(Request $request, DocumentiUtil $doc, int $pagina): Response {
    // recupera criteri dalla sessione
    $criteri = [];
    $criteri['filtro'] = $this->reqstack->getSession()->get('/APP/ROUTE/documenti_docenti/filtro', 'D');
    $criteri['tipo'] = $this->reqstack->getSession()->get('/APP/ROUTE/documenti_docenti/tipo', 'L');
    $criteri['classe'] = $this->em->getRepository(Classe::class)->find(
      (int) $this->reqstack->getSession()->get('/APP/ROUTE/documenti_docenti/classe', 0));
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $this->reqstack->getSession()->get('/APP/ROUTE/documenti_docenti/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/documenti_docenti/pagina', $pagina);
    }
    // form filtro
    $opzioniClassi = $this->em->getRepository(Classe::class)->opzioni(
      $this->getUser()->getSede() ? $this->getUser()->getSede()->getId() : null, false);
    $form = $this->createForm(DocumentoType::class, null, ['form_mode' => 'docenti',
      'values' => [$criteri['filtro'], $criteri['tipo'], $criteri['classe'], $opzioniClassi]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $criteri['filtro'] = $form->get('filtro')->getData();
      $criteri['tipo'] = $form->get('tipo')->getData();
      $criteri['classe'] = $form->get('classe')->getData();
      $pagina = 1;
      // memorizza in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/documenti_docenti/filtro', $criteri['filtro']);
      $this->reqstack->getSession()->set('/APP/ROUTE/documenti_docenti/tipo', $criteri['tipo']);
      $this->reqstack->getSession()->set('/APP/ROUTE/documenti_docenti/classe',
        is_object($criteri['classe']) ? $criteri['classe']->getId() : null);
      $this->reqstack->getSession()->set('/APP/ROUTE/documenti_docenti/pagina', $pagina);
    }
    // recupera dati
    $dati = $doc->docenti($criteri, $pagina, $this->getUser()->getSede());
    // informazioni di visualizzazione
    $info['pagina'] = $pagina;
    $info['tipo'] = $criteri['tipo'];
    // mostra la pagina di risposta
    return $this->render('documenti/docenti.html.twig', [
      'pagina_titolo' => 'page.documenti_docenti',
      'form' => $form,
      'form_success' => null,
      'form_help' => null,
      'dati' => $dati,
      'info' => $info]);
  }

  /**
   * Gestione inserimento dei documenti per gli alunni BES
   *
   * @param Request $request Pagina richiesta
   * @param DocumentiUtil $doc Funzioni di utilità per la gestione dei documenti di classe
   * @param int $pagina Numero di pagina per la lista visualizzata
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/documenti/bes/{pagina}', name: 'documenti_bes', requirements: ['pagina' => '\d+'], defaults: ['pagina' => 0], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function bes(Request $request, DocumentiUtil $doc, int $pagina): Response {
    // controlla accesso a funzione
    if (!$this->getUser()->getResponsabileBes()) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // recupera pagina dalla sessione
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $this->reqstack->getSession()->get('/APP/ROUTE/documenti_bes/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/documenti_bes/pagina', $pagina);
    }
    // recupera criteri dalla sessione
    $criteri = [];
    $criteri['tipo'] = $this->reqstack->getSession()->get('/APP/ROUTE/documenti_bes/tipo', '');
    $criteri['classe'] = $this->em->getRepository(Classe::class)->find(
      (int) $this->reqstack->getSession()->get('/APP/ROUTE/documenti_bes/classe', 0));
    // form filtro
    $opzioniClassi = $this->em->getRepository(Classe::class)->opzioni(
      $this->getUser()->getResponsabileBesSede() ? $this->getUser()->getResponsabileBesSede()->getId() : null, false);
    $form = $this->createForm(DocumentoType::class, null, ['form_mode' => 'alunni',
      'values' => [$criteri['tipo'], $criteri['classe'], $opzioniClassi]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $criteri['tipo'] = $form->get('tipo')->getData();
      $criteri['classe'] = $form->get('classe')->getData();
      $pagina = 1;
      // memorizza in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/documenti_bes/tipo', $criteri['tipo']);
      $this->reqstack->getSession()->set('/APP/ROUTE/documenti_bes/classe',
        is_object($criteri['classe']) ? $criteri['classe']->getId() : null);
      $this->reqstack->getSession()->set('/APP/ROUTE/documenti_bes/pagina', $pagina);
    }
    // recupera dati
    $dati = $doc->besDocente($criteri, $this->getUser(), $pagina);
    $info['pagina'] = $pagina;
    // mostra la pagina di risposta
    return $this->render('documenti/bes.html.twig', [
      'pagina_titolo' => 'page.documenti_bes',
      'form' => $form,
      'form_success' => null,
      'form_help' => null,
      'dati' => $dati,
      'info' => $info]);
  }

  /**
   * Gestione inserimento dei documenti per gli alunni BES
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param DocumentiUtil $doc Funzioni di utilità per la gestione dei documenti di classe
   * @param LogHandler $dblogger Gestore dei log su database
   * @param Alunno $alunno Alunno di riferimento per il documento
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/documenti/bes/add/{alunno}', name: 'documenti_bes_add', requirements: ['alunno' => '\d+'], defaults: ['alunno' => 0], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function besAdd(Request $request, TranslatorInterface $trans, DocumentiUtil $doc,
                         LogHandler $dblogger, Alunno $alunno = null): Response {
    // inizializza
    $info = [];
    $classe = null;
    $varSessione = '/APP/FILE/documenti_bes_add/files';
    if ($request->isMethod('GET')) {
      // inizializza sessione per allegati
      $this->reqstack->getSession()->set($varSessione, []);
    }
    // controlla accesso a funzione
    if (!$this->getUser()->getResponsabileBes()) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla alunno
    if ($alunno && (!$alunno->getAbilitato() || !$alunno->getClasse())) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla azione
    $listaTipi = ['H', 'D', 'B', 'C'];
    if ($alunno) {
      $documentiEsistenti = $this->em->getRepository(Documento::class)->findBy(['alunno' => $alunno,
        'stato' => 'P']);
      $tipiEsistenti = [];
      foreach ($documentiEsistenti as $des) {
        $tipiEsistenti[] = $des->getTipo();
        // toglie anche tipo incompatibile (o PEI o PDP)
        $tipiEsistenti[] = ($des->getTipo() == 'H' ? 'D' : ($des->getTipo() == 'D' ? 'H' : null));
      }
      $listaTipi = array_diff($listaTipi, $tipiEsistenti);
      if (empty($listaTipi)) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
    }
    // informazioni da visualizzare
    if ($alunno) {
      $classe = $alunno->getClasse();
      $info['classe'] = ''.$classe;
      $info['alunno'] = $alunno->getCognome().' '.$alunno->getNome();
      $info['sesso'] = $alunno->getSesso();
    }
    // crea documento
    $documento = (new Documento())
      ->setTipo(array_values($listaTipi)[0])
      ->setDocente($this->getUser())
      ->setAlunno($alunno)
      ->setListaDestinatari(new ListaDestinatari());
    $this->em->persist($documento);
    // controllo permessi
    if (!$doc->azioneDocumento('add', $this->getUser(), $documento)) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // form di inserimento
    $opzioniClassi = null;
    if (!$alunno) {
      $opzioniClassi = $this->em->getRepository(Classe::class)->opzioni(
        $this->getUser()->getResponsabileBesSede() ? $this->getUser()->getResponsabileBesSede()->getId() : null, false);
    }
    $opzioniTipi = [];
    foreach ($listaTipi as $opt) {
      $opzioniTipi['label.documenti_bes_'.$opt] = $opt;
    }
    $form = $this->createForm(DocumentoType::class, null, [
      'return_url' => $this->generateUrl('documenti_bes'), 'form_mode' => $documento->getTipo(),
      'values' => [$opzioniClassi, $opzioniTipi]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // controllo errori
      $allegati = $this->reqstack->getSession()->get($varSessione, []);
      $tipo = $form->get('tipo')->getData();
      $alunnoIndividuale = $alunno ? null :
        $this->em->getRepository(Alunno::class)->findOneBy(['abilitato' => 1,
        'id' => $form->get('alunno')->getData()]);
      if (!$alunno) {
        $documentiEsistenti = $this->em->getRepository(Documento::class)->findBy(['alunno' => $alunnoIndividuale,
          'stato' => 'P']);
        $tipiEsistenti = [];
        foreach ($documentiEsistenti as $des) {
          if ($des->getTipo() == 'H' || $des->getTipo() == 'D') {
            // PEI o PDP
            $tipiEsistenti[] = 'H';
            $tipiEsistenti[] = 'D';
          } else {
            // altro tipo
            $tipiEsistenti[] = $des->getTipo();
          }
        }
        $listaTipi = array_diff($listaTipi, $tipiEsistenti);
      }
      if (count($allegati) < 1) {
        // errore: numero allegati
        $form->addError(new FormError($trans->trans('exception.file_mancante')));
      }
      if (empty($tipo)) {
        // errore: tipo mancante
        $form->addError(new FormError($trans->trans('exception.documento_tipo_mancante')));
      }
      if (!$alunno && (empty($alunnoIndividuale) || empty($alunnoIndividuale->getClasse()))) {
        // errore: alunno mancante o non abilitato e iscritto
        $form->addError(new FormError($trans->trans('exception.documento_alunno_mancante')));
      }
      if (!$alunno && $alunnoIndividuale && $alunnoIndividuale->getClasse() &&
          $this->getUser()->getResponsabileBesSede() &&
          $alunnoIndividuale->getClasse()->getSede() != $this->getUser()->getResponsabileBesSede()) {
        // errore: alunno di sede non ammessa
        $form->addError(new FormError($trans->trans('exception.documento_alunno_mancante')));
      }
      if (!$alunno && $tipo && $alunnoIndividuale && !in_array($tipo, $listaTipi)) {
        // errore: documento già presente
        $form->addError(new FormError($trans->trans('exception.documento_esistente')));
      }
      if ($form->isValid()) {
        // imposta documento
        $documento->setTipo($tipo);
        if (!$alunno) {
          $documento->setAlunno($alunnoIndividuale);
        }
        // imposta destinatari
        $doc->impostaDestinatari($documento);
        // conversione pfd
        [$file, $estensione] = $doc->convertePdf($allegati[0]['temp']);
        // imposta allegato
        $doc->impostaUnAllegato($documento, $file, $estensione, $allegati[0]['size']);
        // protegge documento
        if ($doc->codificaDocumento($documento)) {
          // rimuove sessione con gli allegati
          $this->reqstack->getSession()->remove($varSessione);
          // ok: memorizzazione e log
          $dblogger->logCreazione('DOCUMENTI', 'Inserimento documento BES', $documento);
          // redirezione
          return $this->redirectToRoute('documenti_bes');
        }
        // errore di codifica: rimuove file
        $form->addError(new FormError($trans->trans('exception.documento_errore_codifica')));
        $file = $documento->getAllegati()[0];
        unlink($doc->documentoDir($documento).'/'.$file->getFile().'.'.$file->getEstensione());
      }
    }
    // mostra la pagina di risposta
    return $this->render('documenti/bes_add.html.twig', [
      'pagina_titolo' => 'page.documenti_bes',
      'form' => $form,
      'form_title' => 'title.nuovo_documento_bes',
      'info' => $info]);
  }

  /**
   * Visualizza i documenti degli alunni
   *
   * @param Request $request Pagina richiesta
   * @param DocumentiUtil $doc Funzioni di utilità per la gestione dei documenti di classe
   * @param int $pagina Numero di pagina per la lista visualizzata
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/documenti/alunni/{pagina}', name: 'documenti_alunni', requirements: ['pagina' => '\d+'], defaults: ['pagina' => 0], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_STAFF')]
  public function alunni(Request $request, DocumentiUtil $doc, int $pagina): Response {
    // recupera criteri dalla sessione
    $criteri = [];
    $criteri['tipo'] = $this->reqstack->getSession()->get('/APP/ROUTE/documenti_alunni/tipo', '');
    $criteri['classe'] = $this->em->getRepository(Classe::class)->find(
      (int) $this->reqstack->getSession()->get('/APP/ROUTE/documenti_alunni/classe', 0));
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $this->reqstack->getSession()->get('/APP/ROUTE/documenti_alunni/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/documenti_alunni/pagina', $pagina);
    }
    // form filtro
    $opzioniClassi = $this->em->getRepository(Classe::class)->opzioni(
      $this->getUser()->getSede() ? $this->getUser()->getSede()->getId() : null, false);
    $form = $this->createForm(DocumentoType::class, null, ['form_mode' => 'alunni',
      'values' => [$criteri['tipo'], $criteri['classe'], $opzioniClassi]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $criteri['tipo'] = $form->get('tipo')->getData();
      $criteri['classe'] = $form->get('classe')->getData();
      $pagina = 1;
      // memorizza in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/documenti_alunni/tipo', $criteri['tipo']);
      $this->reqstack->getSession()->set('/APP/ROUTE/documenti_alunni/classe',
        is_object($criteri['classe']) ? $criteri['classe']->getId() : null);
      $this->reqstack->getSession()->set('/APP/ROUTE/documenti_alunni/pagina', $pagina);
    }
    // recupera dati
    $dati = $doc->alunni($criteri, $pagina, $this->getUser()->getSede());
    // informazioni di visualizzazione
    $info['pagina'] = $pagina;
    // mostra la pagina di risposta
    return $this->render('documenti/alunni.html.twig', [
      'pagina_titolo' => 'page.documenti_alunni',
      'form' => $form,
      'form_success' => null,
      'form_help' => null,
      'dati' => $dati,
      'info' => $info]);
  }

   /**
    * Visualizza documenti destinati all'utente
    *
    * @param Request $request Pagina richiesta
    * @param int $pagina Numero di pagina per la lista visualizzata
    *
    * @return Response Pagina di risposta
    *
    */
   #[Route(path: '/documenti/bacheca/{pagina}', name: 'documenti_bacheca', requirements: ['pagina' => '\d+'], defaults: ['pagina' => 0], methods: ['GET', 'POST'])]
   #[IsGranted('ROLE_UTENTE')]
   public function bacheca(Request $request, int $pagina): Response {
     // recupera criteri dalla sessione
     $criteri = [];
     $criteri['tipo'] = $this->reqstack->getSession()->get('/APP/ROUTE/documenti_bacheca/tipo', '');
     $criteri['titolo'] = $this->reqstack->getSession()->get('/APP/ROUTE/documenti_bacheca/titolo', '');
     if ($pagina == 0) {
       // pagina non definita: la cerca in sessione
       $pagina = $this->reqstack->getSession()->get('/APP/ROUTE/documenti_bacheca/pagina', 1);
     } else {
       // pagina specificata: la conserva in sessione
       $this->reqstack->getSession()->set('/APP/ROUTE/documenti_bacheca/pagina', $pagina);
     }
     // opzioni tipi predefiniti
     $opzioni = ['label.documenti_da_leggere' => 'X'];
     if ($this->getUser() instanceOf Docente) {
       // tipi per docenti
       $opzioni = ['label.documenti_da_leggere' => 'X', 'label.piani' => 'L', 'label.programmi' => 'P',
         'label.maggio' => 'M', 'label.documenti_bes_B' => 'B', 'label.documenti_bes_C' => 'C',
         'label.documenti_bes_D' => 'D', 'label.documenti_bes_H' => 'H', 'label.documenti_generici' => 'G'];
     } elseif (($this->getUser() instanceOf Genitore) || ($this->getUser() instanceOf Alunno)) {
       // tipi per genitori/alunni
       $opzioni = ['label.documenti_da_leggere' => 'X', 'label.programmi' => 'P',
         'label.maggio' => 'M', 'label.documenti_generici' => 'G'];
     }
     // form filtro
     $form = $this->createForm(DocumentoType::class, null, ['form_mode' => 'bacheca',
       'values' => [$criteri['tipo'], $opzioni, $criteri['titolo']]]);
     $form->handleRequest($request);
     if ($form->isSubmitted() && $form->isValid()) {
       // imposta criteri di ricerca
       $criteri['tipo'] = $form->get('tipo')->getData();
       $criteri['titolo'] = $form->get('titolo')->getData();
       $pagina = 1;
       // memorizza in sessione
       $this->reqstack->getSession()->set('/APP/ROUTE/documenti_bacheca/tipo', $criteri['tipo']);
       $this->reqstack->getSession()->set('/APP/ROUTE/documenti_bacheca/titolo', $criteri['titolo']);
       $this->reqstack->getSession()->set('/APP/ROUTE/documenti_bacheca/pagina', $pagina);
     }
     // recupera dati
     $dati = $this->em->getRepository(Documento::class)->lista($criteri, $this->getUser(), $pagina);
     // informazioni di visualizzazione
     $info['pagina'] = $pagina;
     // mostra la pagina di risposta
     return $this->render('documenti/bacheca.html.twig', [
      'pagina_titolo' => 'page.documenti_bacheca',
      'form' => $form,
      'form_success' => null,
      'form_help' => null,
      'dati' => $dati,
      'info' => $info]);
   }

  /**
   * Gestione dei documenti archiviati per gli alunni BES
   *
   * @param Request $request Pagina richiesta
   * @param DocumentiUtil $doc Funzioni di utilità per la gestione dei documenti di classe
   * @param int $pagina Numero di pagina per la lista visualizzata
   *
   * @return Response Pagina di risposta
   */
  #[Route(path: '/documenti/archivio/bes/{pagina}', name: 'documenti_archivio_bes', requirements: ['pagina' => '\d+'], defaults: ['pagina' => 0], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function archivioBes(Request $request, DocumentiUtil $doc, int $pagina): Response {
    // controlla accesso a funzione
    if (!$this->getUser()->getResponsabileBes()) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // recupera pagina dalla sessione
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $this->reqstack->getSession()->get('/APP/ROUTE/documenti_archivio_bes/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/documenti_archivio_bes/pagina', $pagina);
    }
    // recupera criteri dalla sessione
    $criteri = [];
    $criteri['anno'] = $this->reqstack->getSession()->get('/APP/ROUTE/documenti_archivio_bes/anno', '');
    $criteri['tipo'] = $this->reqstack->getSession()->get('/APP/ROUTE/documenti_archivio_bes/tipo', '');
    $criteri['cognome'] = $this->reqstack->getSession()->get('/APP/ROUTE/documenti_archivio_bes/cognome', '');
    $criteri['nome'] = $this->reqstack->getSession()->get('/APP/ROUTE/documenti_archivio_bes/nome', '');
    $criteri['codice_fiscale'] = $this->reqstack->getSession()->get('/APP/ROUTE/documenti_archivio_bes/codice_fiscale', '');
    // form filtro
    $listaAnni = $this->em->getRepository(Documento::class)->archivioBesAnni();
    if (empty($criteri['anno']) || !in_array($criteri['anno'], $listaAnni)) {
      // anno non definito: lo imposta al primo disponibile
      $criteri['anno'] = reset($listaAnni);
    }
    $form = $this->createForm(DocumentoType::class, null, ['form_mode' => 'archivio_bes',
      'values' => [$criteri['anno'], $listaAnni, $criteri['tipo'], $criteri['cognome'], $criteri['nome'],
      $criteri['codice_fiscale']]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $criteri['anno'] = $form->get('anno')->getData();
      $criteri['tipo'] = $form->get('tipo')->getData();
      $criteri['nome'] = $form->get('nome')->getData();
      $criteri['cognome'] = $form->get('cognome')->getData();
      $criteri['codice_fiscale'] = $form->get('codice_fiscale')->getData();
      $pagina = 1;
      // memorizza in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/documenti_archivio_bes/anno', $criteri['anno']);
      $this->reqstack->getSession()->set('/APP/ROUTE/documenti_archivio_bes/tipo', $criteri['tipo']);
      $this->reqstack->getSession()->set('/APP/ROUTE/documenti_archivio_bes/cognome', $criteri['cognome']);
      $this->reqstack->getSession()->set('/APP/ROUTE/documenti_archivio_bes/nome', $criteri['nome']);
      $this->reqstack->getSession()->set('/APP/ROUTE/documenti_archivio_bes/codice_fiscale',
        $criteri['codice_fiscale']);
      $this->reqstack->getSession()->set('/APP/ROUTE/documenti_archivio_bes/pagina', $pagina);
    }
    // recupera dati
    $dati = $doc->archivioBes($criteri, $this->getUser(), $pagina);
    $info['pagina'] = $pagina;
    // mostra la pagina di risposta
    return $this->render('documenti/archivio_bes.html.twig', [
      'pagina_titolo' => 'page.documenti_bes',
      'form' => $form,
      'form_success' => null,
      'form_help' => null,
      'dati' => $dati,
      'info' => $info]);
  }

  /**
   * Gestione ripristino dei documenti BES archiviati
   *
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param DocumentiUtil $doc Funzioni di utilità per la gestione dei documenti di classe
   * @param LogHandler $dblogger Gestore dei log su database
   * @param Documento $documento Documento da ripristinare
   *
   * @return Response Pagina di risposta
   */
  #[Route(path: '/documenti/bes/restore/{documento}', name: 'documenti_bes_restore', requirements: ['documento' => '\d+'], methods: ['GET'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function besRestore(TranslatorInterface $trans, DocumentiUtil $doc, LogHandler $dblogger,
                             Documento $documento): Response {
    // controlla accesso a funzione
    if (!$this->getUser()->getResponsabileBes()) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla alunno
    $codiceFiscale = trim(substr($documento->getTitolo(), strpos($documento->getTitolo(), '- C.F. ') + 7));
    $alunno = $this->em->getRepository(Alunno::class)->findOneBy(['codiceFiscale' => $codiceFiscale,
      'abilitato' => 1]);
    if (!$alunno) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla se esiste già un documento dello stesso tipo
    $documentoEsistente = $this->em->getRepository(Documento::class)->findOneBy(['alunno' => $alunno,
      'tipo' => $documento->getTipo(), 'stato' => 'P']);
    if ($documentoEsistente) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo permessi
    if (!$doc->azioneDocumento('edit', $this->getUser(), $documento)) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    if ($alunno->getClasse() && $this->getUser()->getResponsabileBesSede() &&
        $alunno->getClasse()->getSede() != $this->getUser()->getResponsabileBesSede()) {
      // errore: alunno di sede non ammessa
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // imposta documento
    $vecchioDocumento = clone $documento;
    $documento
      ->setDocente($this->getUser())
      ->setAlunno($alunno)
      ->setStato('P')
      ->setTitolo('')
      ->setAnno(0);
    // cancella lista destinatari
    $doc->cancellaDestinatari($documento);
    // imposta destinatari
    $doc->impostaDestinatari($documento);
    // ok: memorizzazione e log
    $dblogger->logModifica ('DOCUMENTI', 'Ripristino documento BES', $vecchioDocumento, $documento);
    // sposta file
    $fs = new FileSystem();
    $nomefileVecchio = $this->getParameter('kernel.project_dir').'/FILES/upload/documenti/'.
      $vecchioDocumento->getAnno().'/riservato/'.$documento->getAllegati()[0]->getFile().'.'.
      $documento->getAllegati()[0]->getEstensione();
    $nomefile = $this->getParameter('kernel.project_dir').'/FILES/upload/documenti/riservato/'.
      $documento->getAllegati()[0]->getFile().'.'.$documento->getAllegati()[0]->getEstensione();
    $fs->rename($nomefileVecchio, $nomefile);
    // messaggio ok
    $this->addFlash('success', $trans->trans('message.documento_bes_ripristinato', [
      'sex' => $alunno->getSesso() == 'F' ? 'a' : 'o', 'alunno' => $alunno->getCognome().' '.$alunno->getNome()]));
    // redirezione
    return $this->redirectToRoute('documenti_archivio_bes');
  }

  /**
   * Gestione archiviazione dei documenti BES
   *
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param DocumentiUtil $doc Funzioni di utilità per la gestione dei documenti di classe
   * @param LogHandler $dblogger Gestore dei log su database
   * @param Documento $documento Documento da archiviare
   *
   * @return Response Pagina di risposta
   */
  #[Route(path: '/documenti/bes/archive/{documento}', name: 'documenti_bes_archive', requirements: ['documento' => '\d+'], methods: ['GET'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function besArchive(TranslatorInterface $trans, DocumentiUtil $doc, LogHandler $dblogger,
                             Documento $documento): Response {
    // controlla accesso a funzione
    if (!$this->getUser()->getResponsabileBes()) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla alunno
    if (!$documento->getAlunno()) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo permessi
    if (!$doc->azioneDocumento('edit', $this->getUser(), $documento)) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    if ($documento->getAlunno()->getClasse() && $this->getUser()->getResponsabileBesSede() &&
        $documento->getAlunno()->getClasse()->getSede() != $this->getUser()->getResponsabileBesSede()) {
      // errore: alunno di sede non ammessa
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // imposta documento
    $vecchioDocumento = clone $documento;
    $documento
      ->setDocente($this->getUser())
      ->setAlunno(null)
      ->setStato('A')
      ->setTitolo($vecchioDocumento->getAlunno()->getCognome().' '.
        $vecchioDocumento->getAlunno()->getNome().' - C.F. '.
        $vecchioDocumento->getAlunno()->getCodiceFiscale())
      ->setAnno((int) substr($this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_scolastico'), 0, 4));
    // cancella lista destinatari
    $doc->cancellaDestinatari($documento);
    // imposta destinatari
    $destinatari = new ListaDestinatari();
    $this->em->persist($destinatari);
    $documento->setListaDestinatari($destinatari);
    // ok: memorizzazione e log
    $dblogger->logModifica ('DOCUMENTI', 'Archiviazione documento BES', $vecchioDocumento, $documento);
    // sposta file
    $fs = new FileSystem();
    if (!$fs->exists($this->getParameter('kernel.project_dir').'/FILES/upload/documenti/riservato/'.$documento->getAnno())) {
      // crea cartella dell'archivio per l'anno indicato
      $fs->mkdir($this->getParameter('kernel.project_dir').'/FILES/upload/documenti/'.$documento->getAnno(), 0770);
      $fs->mkdir($this->getParameter('kernel.project_dir').'/FILES/upload/documenti/'.$documento->getAnno().'/riservato', 0770);
    }
    $nomefileVecchio = $this->getParameter('kernel.project_dir').'/FILES/upload/documenti/riservato/'.
      $documento->getAllegati()[0]->getFile().'.'.$documento->getAllegati()[0]->getEstensione();
    $nomefile = $this->getParameter('kernel.project_dir').'/FILES/upload/documenti/'.
      $documento->getAnno().'/riservato/'.$documento->getAllegati()[0]->getFile().'.'.
      $documento->getAllegati()[0]->getEstensione();
    $fs->rename($nomefileVecchio, $nomefile);
    // messaggio ok
    $this->addFlash('success', $trans->trans('message.documento_bes_archiviato', [
      'sex' => $vecchioDocumento->getAlunno()->getSesso() == 'F' ? 'a' : 'o',
      'alunno' => $vecchioDocumento->getAlunno()->getCognome().' '.$vecchioDocumento->getAlunno()->getNome()]));
    // redirezione
    return $this->redirectToRoute('documenti_bes');
  }

}
