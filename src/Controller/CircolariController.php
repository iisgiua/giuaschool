<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Controller;

use App\Entity\Annotazione;
use App\Entity\Circolare;
use App\Entity\Classe;
use App\Entity\ComunicazioneClasse;
use App\Entity\ComunicazioneUtente;
use App\Entity\Materia;
use App\Entity\Sede;
use App\Entity\Staff;
use App\Form\CircolareFiltroType;
use App\Form\CircolareType;
use App\Message\CircolareMessage;
use App\MessageHandler\NotificaMessageHandler;
use App\Util\ComunicazioniUtil;
use App\Util\LogHandler;
use DateTime;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\FlushBatchHandlersStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;


/**
 * CircolariController - gestione delle circolari
 *
 * @author Antonello Dessì
 */
class CircolariController extends BaseController {

  /**
   * Aggiunge o modifica una circolare
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param ComunicazioniUtil $com Funzioni di utilità per le comunicazioni
   * @param LogHandler $dblogger Gestore dei log su database
   * @param Circolare|null $circolare Circolare da modificare o valore nullo
   *
   * @return Response Pagina di risposta
   */
  #[Route(path: '/circolari/edit/{circolare}', name: 'circolari_edit', requirements: ['circolare' => '\d+'], defaults: ['circolare' => '0'], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_STAFF')]
  public function edit(Request $request, TranslatorInterface $trans, ComunicazioniUtil $com,
                       LogHandler $dblogger, ?Circolare $circolare=null): Response {
    // inizializza
    $dati = [];
    $var_sessione = '/APP/FILE/circolari_edit/';
    $dir = $this->getParameter('dir_circolari');
    // controlla azione
    $edit = false;
    if ($circolare) {
      // azione edit
      $edit = true;
    } else {
      // azione add
      $numero = $this->em->getRepository(Circolare::class)->prossimoNumero();
      $circolare = (new Circolare())
        ->setData(new DateTime('today'))
        ->setNumero($numero)
        ->setStato('B');
      // se l'utente ha una sede, la imposta predefinita
      if ($this->getUser()->getSede()) {
        $circolare->addSede($this->getUser()->getSede());
      }
      $this->em->persist($circolare);
    }
    // imposta autore della circolare
    $circolare->setAutore($this->getUser());
    // controllo permessi
    if (!$com->azioneCircolare(($edit ? 'edit' : 'add'), $circolare->getData(), $this->getUser(),
        $edit ? $circolare : null)) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // legge file
    $documento = [];
    $allegati = [];
    if ($request->isMethod('POST')) {
      // pagina inviata
      foreach ($this->reqstack->getSession()->get($var_sessione.'documento', []) as $f) {
        if ($f['type'] != 'removed') {
          // aggiunge allegato
          $documento[] = $f;
        }
      }
      foreach ($this->reqstack->getSession()->get($var_sessione.'allegati', []) as $f) {
        if ($f['type'] != 'removed') {
          // aggiunge allegato
          $allegati[] = $f;
        }
      }
    } else {
      // pagina iniziale
      $cnt = 0;
      foreach ($circolare->getAllegati() as $file) {
        if ($cnt == 0) {
          $documento[0]['type'] = 'existent';
          $documento[0]['name'] = $file->getTitolo();
          $documento[0]['temp'] = $file->getFile();
          $documento[0]['ext'] = $file->getEstensione();
          $documento[0]['size'] = $file->getDimensione();
        } else {
          $allegati[$cnt - 1]['type'] = 'existent';
          $allegati[$cnt - 1]['name'] = $file->getTitolo();
          $allegati[$cnt - 1]['temp'] = $file->getFile();
          $allegati[$cnt - 1]['ext'] = $file->getEstensione();
          $allegati[$cnt - 1]['size'] = $file->getDimensione();
        }
        $cnt++;
      }
      // modifica dati sessione
      $this->reqstack->getSession()->remove($var_sessione.'documento');
      $this->reqstack->getSession()->remove($var_sessione.'allegati');
      $this->reqstack->getSession()->set($var_sessione.'documento', $documento);
      $this->reqstack->getSession()->set($var_sessione.'allegati', $allegati);
      // elimina file temporanei
      $fs = new Filesystem();
      $finder = new Finder();
      $finder->files()->in($this->getParameter('dir_tmp'))->date('< 1 day ago');
      foreach ($finder as $f) {
        $fs->remove($f);
      }
    }
    // informazioni da visualizzare sui destinatari
    $dati = $com->infoDestinatari($circolare);
    // form di inserimento
    $setSede = $this->getUser()->getSede() ? $this->getUser()->getSede()->getId() : null;
    if ($setSede) {
      $sede = $this->getUser()->getSede();
      $opzioniSedi[$sede->getNomeBreve()] = $sede;
    } else {
      $opzioniSedi = $this->em->getRepository(Sede::class)->opzioni();
    }
    $opzioniClassi = $this->em->getRepository(Classe::class)->opzioni($setSede, true, false, true);
    $opzioniMaterie = $this->em->getRepository(Materia::class)->opzioni(true, false);
    $opzioniClassi2 = $this->em->getRepository(Classe::class)->opzioni($setSede, false, true, true);
    $form = $this->createForm(CircolareType::class, $circolare, ['form_mode' => 'circolare',
      'return_url' => $this->generateUrl('circolari_gestione'),
      'values' => [$opzioniSedi, $opzioniClassi, $opzioniMaterie, $opzioniClassi2]]);
    $form->handleRequest($request);
    if ($form->isSubmitted()) {
      $errore = $com->validaCircolare($circolare, $this->getUser(), $documento);
      if ($errore) {
        // errore presente
        $form->addError(new FormError($trans->trans($errore)));
      }
      if ($form->isValid()) {
        // aggiunge documento e allegati
        $com->aggiungiAllegati($circolare, $dir, array_merge(
          $this->reqstack->getSession()->get($var_sessione.'documento', []),
          $this->reqstack->getSession()->get($var_sessione.'allegati', [])));
        // imposta nomi file
        $primo = true;
        foreach ($circolare->getAllegati() as $allegato) {
          if ($primo) {
            // nome documento principale
            $allegato->setTitolo('Circolare n. '.$circolare->getNumero());
            $allegato->setNome($com->normalizzaNome('Circolare-'.$circolare->getNumero()));
            $primo = false;
          } else {
            // nome allegati
            $prefisso = 'Circolare n. '.$circolare->getNumero().' - Allegato ';
            $nomefile = str_starts_with($allegato->getTitolo(), $prefisso) ?
              trim(substr($allegato->getTitolo(), strlen($prefisso))) : $allegato->getTitolo();
            $allegato->setTitolo($prefisso.$nomefile);
            $allegato->setNome($com->normalizzaNome('Circolare-'.$circolare->getNumero().'-Allegato-'.$nomefile));
          }
        }
        // ok: memorizzazione e log
        $dblogger->logAzione('CIRCOLARI', $edit ? 'Modifica circolare' : 'Crea circolare');
        // redirezione
        return $this->redirectToRoute('circolari_gestione');
      }
    }
    // mostra la pagina di risposta
    return $this->render('circolari/edit.html.twig', [
      'pagina_titolo' => 'page.staff_circolari',
      'form' => $form,
      'form_title' => ($edit ? 'title.modifica_circolare' : 'title.nuova_circolare'),
      'documento' => $documento,
      'allegati' => $allegati,
      'dati' => $dati]);
  }

  /**
   * Cancella circolare
   *
   * @param LogHandler $dblogger Gestore dei log su database
   * @param ComunicazioniUtil $com Funzioni di utilità per le circolari
   * @param Circolare $circolare Circolare da eliminare
   *
   * @return Response Pagina di risposta
   */
  #[Route(path: '/circolari/delete/{circolare}', name: 'circolari_delete', requirements: ['circolare' => '\d+'], methods: ['GET'])]
  #[IsGranted('ROLE_STAFF')]
  public function delete(LogHandler $dblogger, ComunicazioniUtil $com, Circolare $circolare): Response {
    // controllo permessi
    if (!$com->azioneCircolare('delete', $circolare->getData(), $this->getUser(), $circolare)) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // cancella documento e allegati
    foreach ($circolare->getAllegati() as $allegato) {
      $this->em->remove($allegato);
    }
    // cancella circolare
    $this->em->remove($circolare);
    // memorizzazione e log
    $dblogger->logAzione('CIRCOLARI', 'Cancella circolare');
    // cancella file
    $dir = $this->getParameter('dir_circolari');
    foreach ($circolare->getAllegati() as $allegato) {
      unlink($dir.'/'.$allegato->getFile().'.'.$allegato->getEstensione());
    }
    // redirezione
    return $this->redirectToRoute('circolari_gestione');
  }

  /**
   * Gestione delle circolari
   *
   * @param Request $request Pagina richiesta
   * @param ComunicazioniUtil $com Funzioni di utilità per le comunicazioni
   * @param int $pagina Numero di pagina per l'elenco da visualizzare
   *
   * @return Response Pagina di risposta
   */
  #[Route(path: '/circolari/gestione/{pagina}', name: 'circolari_gestione', requirements: ['pagina' => '\d+'], defaults: ['pagina' => '0'], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_STAFF')]
  public function gestione(Request $request, ComunicazioniUtil $com, int $pagina): Response {
    // recupera criteri dalla sessione
    $criteri = [];
    $criteri['inizio'] = $this->reqstack->getSession()->get('/APP/ROUTE/circolari_gestione/inizio', null);
    $criteri['fine'] = $this->reqstack->getSession()->get('/APP/ROUTE/circolari_gestione/fine', null);
    $criteri['oggetto'] = $this->reqstack->getSession()->get('/APP/ROUTE/circolari_gestione/oggetto', '');
    if ($criteri['inizio']) {
      $inizio = DateTime::createFromFormat('Y-m-d', $criteri['inizio']);
    } else {
      $inizio = DateTime::createFromFormat('Y-m-d H:i', $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_inizio').' 00:00')
        ->modify('first day of this month');
      $criteri['inizio'] = $inizio->format('Y-m-d');
    }
    if ($criteri['fine']) {
      $fine = DateTime::createFromFormat('Y-m-d', $criteri['fine']);
    } else {
      $fine = new DateTime('tomorrow');
      $criteri['fine'] = $fine->format('Y-m-d');
    }
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $this->reqstack->getSession()->get('/APP/ROUTE/circolari_gestione/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/circolari_gestione/pagina', $pagina);
    }
    // form filtro
    $form = $this->createForm(CircolareFiltroType::class, null, ['form_mode' => 'gestione',
      'values' => [$inizio, $fine, $criteri['oggetto']]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $criteri['inizio'] = ($form->get('inizio')->getData() ? $form->get('inizio')->getData()->format('Y-m-d') : 0);
      $criteri['fine'] = ($form->get('fine')->getData() ? $form->get('fine')->getData()->format('Y-m-d') : 0);
      $criteri['oggetto'] = $form->get('oggetto')->getData();
      $pagina = 1;
      // memorizza in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/circolari_gestione/inizio', $criteri['inizio']);
      $this->reqstack->getSession()->set('/APP/ROUTE/circolari_gestione/fine', $criteri['fine']);
      $this->reqstack->getSession()->set('/APP/ROUTE/circolari_gestione/oggetto', $criteri['oggetto']);
      $this->reqstack->getSession()->set('/APP/ROUTE/circolari_gestione/pagina', $pagina);
    }
    // recupera dati
    $dati = $com->listaCircolari($criteri, $pagina, $this->getUser());
    // informazioni di visualizzazione
    $info['pagina'] = $pagina;
    // mostra la pagina di risposta
    return $this->render('circolari/gestione.html.twig', [
      'pagina_titolo' => 'page.circolari_gestione',
      'form' => $form,
      'form_help' => null,
      'form_success' => null,
      'dati' => $dati,
      'info' => $info]);
  }

  /**
   * Pubblica la circolare o ne rimuove la pubblicazione (la mette in bozza)
   *
   * @param MessageBusInterface $msg Gestione delle notifiche
   * @param LogHandler $dblogger Gestore dei log su database
   * @param ComunicazioniUtil $com Funzioni di utilità per le circolari
   * @param Circolare $circolare Circolare da pubblicare o da mettere in bozza
   * @param int $pubblica Valore 1 per pubblicare la circolare, 0 per togliere la pubblicazione
   *
   * @return Response Pagina di risposta
   */
  #[Route(path: '/circolari/publish/{circolare}/{pubblica}', name: 'circolari_publish', requirements: ['circolare' => '\d+', 'pubblica' => '0|1'], methods: ['GET'])]
  #[IsGranted('ROLE_STAFF')]
  public function publish(MessageBusInterface $msg, LogHandler $dblogger,
                          ComunicazioniUtil $com, Circolare $circolare, int $pubblica): Response {
    // controllo permessi
    if (!$com->azioneCircolare(($pubblica ? 'publish' : 'unpublish'), $circolare->getData(), $this->getUser(), $circolare)) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    if ($pubblica) {
      // pubblica la circolare
      $circolare->setStato('P');
      // imposta destinatari
      $com->impostaDestinatari($circolare);
    } else {
      // mette in bozza la circolare
      $circolare->setStato('B');
      // cancella destinatari
      $com->cancellaDestinatari($circolare);
    }
    // ok: memorizza dati e log
    $dblogger->logAzione('CIRCOLARI', $pubblica ? 'Pubblica circolare' : 'Rimuove pubblicazione circolare');
    // notifica
    if ($pubblica) {
      // inserisce notifica
      $oraNotifica = explode(':', (string) $this->reqstack->getSession()->get('/CONFIG/SCUOLA/notifica_circolari'));
      $tm = (new DateTime('today'))->setTime($oraNotifica[0], $oraNotifica[1]);
      if ($tm < new DateTime()) {
        // ora invio è già passata: inserisce in coda per domani
        $tm->modify('+1 day');
      }
      $msg->dispatch(new CircolareMessage($circolare->getId()),
        [DelayStamp::delayUntil($tm), new FlushBatchHandlersStamp(true)]);
    } else {
      // rimuove notifica
      NotificaMessageHandler::delete($this->em, (new CircolareMessage($circolare->getId()))->getTag());
    }
    // redirezione
    return $this->redirectToRoute('circolari_gestione');
  }

  /**
   * Mostra i dettagli di una circolare
   *
   * @param ComunicazioniUtil $com Funzioni di utilità per le comunicazioni
   * @param Circolare $circolare Circolare di cui fornire informazioni
   *
   * @return Response Pagina di risposta
   */
  #[Route(path: '/circolari/dettagli/gestione/{circolare}', name: 'circolari_dettagli_gestione', requirements: ['circolare' => '\d+'], methods: ['GET'])]
  #[IsGranted('ROLE_STAFF')]
  public function dettagliGestione(ComunicazioniUtil $com, Circolare $circolare): Response {
    // inizializza
    $mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    // legge dati
    $dati = $com->dettagli($circolare);
    // visualizza pagina
    return $this->render('circolari/scheda_dettagli_gestione.html.twig', [
      'circolare' => $circolare,
      'mesi' => $mesi,
      'dati' => $dati]);
  }

  /**
   * Esegue il download di un documento di una circolare.
   *
   * @param ComunicazioniUtil $com Funzioni di utilità per le circolari
   * @param Circolare $circolare Circolare da scaricare
   * @param int $allegato Numero dell'allegato (0 per la circolare, 1.. per gli allegati)
   * @param string $tipo Tipo di risposta (V=visualizza, D=download)
   *
   * @return Response Documento inviato in risposta
   */
  #[Route(path: '/circolari/download/{circolare}/{allegato}/{tipo}', name: 'circolari_download', requirements: ['circolare' => '\d+', 'allegato' => '\d+', 'tipo' => 'V|D'], defaults: ['allegato' => 0,'tipo' => 'V'], methods: ['GET'])]
  #[IsGranted('ROLE_UTENTE')]
  public function download(ComunicazioniUtil $com, Circolare $circolare, int $allegato, string $tipo): Response {
    // controllo allegati
    if ($allegato < 0 || $allegato >= count($circolare->getAllegati())) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo permesso lettura
    if (!$com->permessoLettura($this->getUser(), $circolare)) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // segna lettura e memorizza su db
    $com->leggeUtente($this->getUser(), $circolare);
    $this->em->flush();
    // invia il file
    $file = $circolare->getAllegati()[$allegato];
    $dir = $this->getParameter('dir_circolari').($circolare->getStato() == 'A' ? '/'.$circolare->getAnno() : '');
    $nomefile = $dir.'/'.$file->getFile().'.'.$file->getEstensione();
    return $this->file($nomefile, $file->getNome().'.'.$file->getEstensione(),
      ($tipo == 'V' ? ResponseHeaderBag::DISPOSITION_INLINE : ResponseHeaderBag::DISPOSITION_ATTACHMENT));
  }

  /**
   * Conferma la lettura della circolare da parte dell'utente
   *
   * @param Circolare $circolare Circolare da firmare
   *
   * @return Response Pagina di risposta
   */
  #[Route(path: '/circolari/firma/{circolare}', name: 'circolari_firma', requirements: ['circolare' => '\d+'], methods: ['GET'])]
  #[IsGranted('ROLE_UTENTE')]
  public function firma(Circolare $circolare): Response {
    // firma
    $this->em->getRepository(ComunicazioneUtente::class)->firma($circolare, $this->getUser());
    // redirect
    return $this->redirectToRoute('circolari_bacheca');
  }

  /**
   * Visualizza le circolari in bacheca
   *
   * @param Request $request Pagina richiesta
   * @param int $pagina Numero di pagina per l'elenco da visualizzare
   *
   * @return Response Pagina di risposta
   */
  #[Route(path: '/circolari/bacheca/{pagina}', name: 'circolari_bacheca', requirements: ['pagina' => '\d+'], defaults: ['pagina' => '0'], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_UTENTE')]
  public function bacheca(Request $request, int $pagina): Response {
    // inizializza
    $mesi = ['Settembre' => 9, 'Ottobre' => 10, 'Novembre' => 11 , 'Dicembre' => 12, 'Gennaio' => 1,
      'Febbraio' => 2, 'Marzo' => 3, 'Aprile' => 4, 'Maggio' => 5, 'Giugno' => 6, 'Luglio' => 7, 'Agosto' => 8];
    $info = [];
    // recupera criteri dalla sessione
    $criteri = [];
    $criteri['visualizza'] = $this->reqstack->getSession()->get('/APP/ROUTE/circolari_bacheca/visualizza',
      ($this->getUser() instanceof Staff) ? 'T' : 'P');
    $criteri['mese'] = $this->reqstack->getSession()->get('/APP/ROUTE/circolari_bacheca/mese', null);
    $criteri['oggetto'] = $this->reqstack->getSession()->get('/APP/ROUTE/circolari_bacheca/oggetto', '');
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $this->reqstack->getSession()->get('/APP/ROUTE/circolari_bacheca/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/circolari_bacheca/pagina', $pagina);
    }
    // lista visualizzazione
    $listaVisualizzazione = ($this->getUser() instanceOf Staff) ?
      ['label.circolari_da_leggere' => 'D', 'label.circolari_proprie' => 'P', 'label.circolari_tutte' => 'T'] :
      ['label.circolari_da_leggere' => 'D', 'label.circolari_proprie' => 'P'];
    // form filtro
    $form = $this->createForm(CircolareFiltroType::class, null, ['form_mode' => 'bacheca',
      'values' => [$criteri['visualizza'], $listaVisualizzazione, $criteri['mese'], $mesi, $criteri['oggetto']]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricriteri
      $criteri['visualizza'] = $form->get('visualizza')->getData();
      $criteri['mese'] = $form->get('mese')->getData();
      $criteri['oggetto'] = $form->get('oggetto')->getData();
      $pagina = 1;
      $this->reqstack->getSession()->set('/APP/ROUTE/circolari_bacheca/visualizza', $criteri['visualizza']);
      $this->reqstack->getSession()->set('/APP/ROUTE/circolari_bacheca/mese', $criteri['mese']);
      $this->reqstack->getSession()->set('/APP/ROUTE/circolari_bacheca/oggetto', $criteri['oggetto']);
      $this->reqstack->getSession()->set('/APP/ROUTE/circolari_bacheca/pagina', $pagina);
    }
    // informazioni di visualizzazione
    $info['pagina'] = $pagina;
    $info['mesi'] = array_flip($mesi);
    // legge le circolari
    $dati = $this->em->getRepository(Circolare::class)->lista($criteri, $pagina, $this->getUser());
    // mostra la pagina di risposta
    return $this->render('circolari/bacheca.html.twig', [
      'pagina_titolo' => 'page.circolari_bacheca',
      'form' => $form,
      'form_help' => null,
      'form_success' => null,
      'dati' => $dati,
      'info' => $info]);
  }

  /**
   * Conferma la lettura della circolare alla classe
   *
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param Classe $classe Classe a cui è destinata la circolare
   * @param Circolare $circolare Circolare da firmare
   *
   * @return Response Pagina di risposta
   */
  #[Route(path: '/circolari/firma/classe/{classe}/{circolare}', name: 'circolari_firma_classe', requirements: ['classe' => '\d+', 'circolare' => '\d+'], defaults: ['circolare' => 0], methods: ['GET'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function firmaClasse(TranslatorInterface $trans, Classe $classe, ?Circolare $circolare=null): Response {
    $numeri = [];
    // lista circolari da firmare
    $circolari = $circolare ? [$circolare] :
      $this->em->getRepository(Circolare::class)->listaCircolariClasse($classe);
    // firma
    foreach ($circolari as $c) {
      // segna lettura
      $this->em->getRepository(ComunicazioneClasse::class)->firmaClasse($classe, $c);
      $numeri[] = $c->getNumero();
    }
    // lista circolari firmate
    sort($numeri);
    $lista = implode(', ', $numeri);
    // testo annotazione
    $testo = $trans->trans('message.registro_lettura_circolare', ['num' => count($numeri), 'circolari' => $lista]);
    // crea annotazione
    $a = (new Annotazione())
      ->setData(new DateTime('today'))
      ->setTesto($testo)
      ->setVisibile(false)
      ->setClasse($classe)
      ->setDocente($this->getUser());
    $this->em->persist($a);
    // memorizza su db
    $this->em->flush();
    // redirect
    return $this->redirectToRoute('lezioni');
  }

  /**
   * Visualizza le circolari dell'archivio degli anni passati.
   *
   * @param Request $request Pagina richiesta
   * @param int $pagina Numero di pagina per l'elenco da visualizzare
   *
   * @return Response Pagina di risposta
   */
  #[Route(path: '/circolari/archivio/{pagina}', name: 'circolari_archivio', requirements: ['pagina' => '\d+'], defaults: ['pagina' => '0'], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_STAFF')]
  public function archivio(Request $request, int $pagina): Response {
    // inizializza
    $mesi = ['Settembre' => 9, 'Ottobre' => 10, 'Novembre' => 11 , 'Dicembre' => 12, 'Gennaio' => 1,
      'Febbraio' => 2, 'Marzo' => 3, 'Aprile' => 4, 'Maggio' => 5, 'Giugno' => 6, 'Luglio' => 7, 'Agosto' => 8];
    $info = [];
    // recupera criteri dalla sessione
    $criteri = [];
    $criteri['anno'] = $this->reqstack->getSession()->get('/APP/ROUTE/circolari_archivio/anno', null);
    $criteri['mese'] = $this->reqstack->getSession()->get('/APP/ROUTE/circolari_archivio/mese', null);
    $criteri['oggetto'] = $this->reqstack->getSession()->get('/APP/ROUTE/circolari_archivio/oggetto', '');
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $this->reqstack->getSession()->get('/APP/ROUTE/circolari_archivio/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/circolari_archivio/pagina', $pagina);
    }
    // crea lista anni
    $anni = $this->em->getRepository(Circolare::class)->anniScolastici();
    if (empty($criteri['anno']) && count($anni) > 0) {
      $criteri['anno'] = array_values($anni)[0];
    }
    // form filtro
    $form = $this->createForm(CircolareFiltroType::class, null, ['form_mode' => 'archivio',
      'values' => [$criteri['anno'], $anni, $criteri['mese'], $mesi, $criteri['oggetto']]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $criteri['anno'] = $form->get('anno')->getData();
      $criteri['mese'] = $form->get('mese')->getData();
      $criteri['oggetto'] = $form->get('oggetto')->getData();
      $pagina = 1;
      $this->reqstack->getSession()->set('/APP/ROUTE/circolari_archivio/anno', $criteri['anno']);
      $this->reqstack->getSession()->set('/APP/ROUTE/circolari_archivio/mese', $criteri['mese']);
      $this->reqstack->getSession()->set('/APP/ROUTE/circolari_archivio/oggetto', $criteri['oggetto']);
      $this->reqstack->getSession()->set('/APP/ROUTE/circolari_archivio/pagina', $pagina);
    }
    // informazioni di visualizzazione
    $info['pagina'] = $pagina;
    $info['mesi'] = $mesi;
    // legge le circolari
    $criteri['visualizza'] = 'T';
    $dati = $this->em->getRepository(Circolare::class)->lista($criteri, $pagina, $this->getUser());
    // mostra la pagina di risposta
    return $this->render('circolari/archivio.html.twig', [
      'pagina_titolo' => 'page.circolari_archivio',
      'form' => $form,
      'form_help' => null,
      'form_success' => null,
      'dati' => $dati,
      'info' => $info]);
  }

}
