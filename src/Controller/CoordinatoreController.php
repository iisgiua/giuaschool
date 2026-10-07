<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Controller;

use Exception;
use App\Entity\Alunno;
use App\Entity\CambioClasse;
use App\Entity\Classe;
use App\Entity\ModuloFormativo;
use App\Entity\Presenza;
use App\Entity\Preside;
use App\Entity\Staff;
use App\Form\FiltroType;
use App\Form\PresenzaType;
use App\Util\LogHandler;
use App\Util\PdfManager;
use App\Util\RegistroUtil;
use App\Util\StaffUtil;
use DateTime;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;


/**
 * CoordinatoreController - gestione delle funzioni per i coordinatori
 *
 * @author Antonello Dessì
 */
class CoordinatoreController extends BaseController {

  /**
   * Gestione delle funzioni coordinatore
   *
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/coordinatore', name: 'coordinatore', methods: ['GET'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function coordinatore(): Response {
    if (!($this->getUser() instanceOf Staff) && !($this->getUser() instanceOf Preside)) {
      // coordinatore
      $classi = array_values(array_filter(explode(',',
        (string) $this->reqstack->getSession()->get('/APP/DOCENTE/coordinatore')),
        fn ($classe) => $classe !== ''));
      if (count($classi) == 1) {
        // coordinatore di una sola classe: vai
        $this->reqstack->getSession()->set('/APP/DOCENTE/classe_coordinatore', $classi[0]);
        return $this->redirectToRoute('coordinatore_assenze', ['classe' => $classi[0]]);
      }
    }
    // staff/preside o coordinatore di più classi
    if ($this->reqstack->getSession()->get('/APP/DOCENTE/classe_coordinatore')) {
      // classe scelta, vai alle assenze
      return $this->redirectToRoute('coordinatore_assenze');
    } else {
      // nessuna classe scelta: la pagina delle assenze mostra il selettore
      return $this->redirectToRoute('coordinatore_assenze');
    }
  }

  /**
   * Memorizza la classe scelta nel selettore e torna alla pagina corrente
   *
   * Usato dal componente selettore-classe: la classe viene validata e
   * memorizzata in sessione, poi la pagina di provenienza viene ricaricata
   * senza il parametro classe, cosi' lo rilegge dalla sessione.
   *
   * @param RouterInterface $router Gestore delle rotte
   * @param Request $request Pagina richiesta
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/coordinatore/seleziona/', name: 'coordinatore_seleziona', methods: ['GET'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function seleziona(RouterInterface $router, Request $request): Response {
    // legge parametro
    $classe = $request->query->getInt('classe');
    // verifica e memorizza in sessione
    if ($classe > 0) {
      $classe = $this->em->getRepository(Classe::class)->find($classe);
      if (!$classe) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      // controllo accesso alla funzione
      $this->controllaAccessoClasse($classe);
      $this->reqstack->getSession()->set('/APP/DOCENTE/classe_coordinatore', $classe->getId());
    } else {
      // nessuna scelta
      $this->reqstack->getSession()->set('/APP/DOCENTE/classe_coordinatore', 0);
    }
    // torna alla pagina corrente, senza il parametro classe: lo rilegge dalla sessione
    $referer = $request->headers->get('referer');
    if ($referer) {
      try {
        $match = $router->match(Request::create($referer)->getPathInfo());
        if (isset($match['_route'])) {
          // variabili di sistema e classe rimossi: la pagina la rilegge dalla sessione
          $param = array_filter($match, fn($k) => !str_starts_with((string) $k, '_') &&
            !in_array($k, ['classe', 'alunno', 'cattedra']), ARRAY_FILTER_USE_KEY);
          return $this->redirectToRoute($match['_route'], $param);
        }
      } catch (Exception) {
        // rotta non riconosciuta: ricarica comunque la pagina di provenienza
        return $this->redirect($referer);
      }
    }
    // nessuna pagina precedente: vai alle assenze
    return $this->redirectToRoute('coordinatore_assenze');
  }

  /**
   * Mostra le note della classe.
   *
   * @param StaffUtil $staff Funzioni di utilità per lo staff
   * @param PdfManager $pdf Gestore dei documenti PDF
   * @param int $classe Identificativo della classe
   * @param string $tipo Tipo di risposta: visualizza HTML (V) o scarica documento PDF (P)
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/coordinatore/note/{classe}/{tipo}', name: 'coordinatore_note', requirements: ['classe' => '\d+', 'tipo' => 'V|P'], defaults: ['classe' => 0, 'tipo' => 'V'], methods: ['GET'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function note(StaffUtil $staff, PdfManager $pdf, int $classe, string $tipo): Response {
    // inizializza variabili
    $dati = null;
    // parametro classe
    if ($classe == 0) {
      // recupera parametri da sessione
      $classe = $this->reqstack->getSession()->get('/APP/DOCENTE/classe_coordinatore');
    } else {
      // memorizza su sessione
      $this->reqstack->getSession()->set('/APP/DOCENTE/classe_coordinatore', $classe);
    }
    // controllo classe
    if ($classe > 0) {
      $classe = $this->em->getRepository(Classe::class)->find($classe);
      if (!$classe) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      // controllo accesso alla funzione
      if (!($this->getUser() instanceOf Staff) && !($this->getUser() instanceOf Preside)) {
        // coordinatore
        $classi = explode(',', (string) $this->reqstack->getSession()->get('/APP/DOCENTE/coordinatore'));
        if (!in_array($classe->getId(), $classi)) {
          // errore
          throw $this->createNotFoundException('exception.invalid_params');
        }
      }
      // legge dati
      $dati = $staff->note($classe);
      // controlla tipo
      if ($tipo == 'P') {
        // crea documento PDF
        $pdf->configure($this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
          'Note disciplinari della classe '.$classe);
        $html = $this->renderView('pdf/note_classe.html.twig', [
          'classe' => $classe,
          'dati' => $dati]);
        $pdf->createFromHtml($html);
        // invia il documento
        $nomefile = 'note-'.$classe->getAnno().$classe->getSezione().$classe->getGruppo().'.pdf';
        return $pdf->send($nomefile);
      }
    }
    // visualizza pagina
    return $this->render('coordinatore/note.html.twig', [
      'pagina_titolo' => 'page.coordinatore_note',
      'classe' => $classe,
      'dati' => $dati]);
  }

  /**
   * Mostra le assenze della classe.
   *
   * @param StaffUtil $staff Funzioni di utilità per lo staff
   * @param PdfManager $pdf Gestore dei documenti PDF
   * @param int $classe Identificativo della classe
   * @param string $tipo Tipo di risposta: visualizza HTML (V) o scarica documento PDF (P)
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/coordinatore/assenze/{classe}/{tipo}', name: 'coordinatore_assenze', requirements: ['classe' => '\d+', 'tipo' => 'V|P'], defaults: ['classe' => 0, 'tipo' => 'V'], methods: ['GET'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function assenze(StaffUtil $staff, PdfManager $pdf, int $classe, string $tipo): Response {
    // inizializza variabili
    $dati = null;
    // parametro classe
    if ($classe == 0) {
      // recupera parametri da sessione
      $classe = $this->reqstack->getSession()->get('/APP/DOCENTE/classe_coordinatore');
    } else {
      // memorizza su sessione
      $this->reqstack->getSession()->set('/APP/DOCENTE/classe_coordinatore', $classe);
    }
    // controllo classe
    if ($classe > 0) {
      $classe = $this->em->getRepository(Classe::class)->find($classe);
      if (!$classe) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      // controllo accesso alla funzione
      if (!($this->getUser() instanceOf Staff) && !($this->getUser() instanceOf Preside)) {
        // coordinatore
        $classi = explode(',', (string) $this->reqstack->getSession()->get('/APP/DOCENTE/coordinatore'));
        if (!in_array($classe->getId(), $classi)) {
          // errore
          throw $this->createNotFoundException('exception.invalid_params');
        }
      }
      // legge dati
      $dati = $staff->assenze($classe);
      if ($tipo == 'P') {
        // crea documento PDF
        $pdf->configure($this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
          'Assenze della classe '.$classe);
        $html = $this->renderView('pdf/assenze_classe.html.twig', [
          'classe' => $classe,
          'dati' => $dati]);
        $pdf->createFromHtml($html);
        // invia il documento
        $nomefile = 'assenze-'.$classe->getAnno().$classe->getSezione().$classe->getGruppo().'.pdf';
        return $pdf->send($nomefile);
      }
    }
    // visualizza pagina
    return $this->render('coordinatore/assenze.html.twig', [
      'pagina_titolo' => 'page.coordinatore_assenze',
      'classe' => $classe,
      'dati' => $dati]);
  }

  /**
   * Mostra le medie dei voti della classe.
   *
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param StaffUtil $staff Funzioni di utilità per lo staff
   * @param PdfManager $pdf Gestore dei documenti PDF
   * @param int $classe Identificativo della classe
   * @param int $periodo Periodo relativo allo scrutinio
   * @param string $tipo Tipo di risposta: visualizza HTML (V) o scarica documento PDF (P)
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/coordinatore/voti/{classe}/{periodo}/{tipo}', name: 'coordinatore_voti', requirements: ['classe' => '\d+', 'periodo' => '1|2|3|0', 'tipo' => 'V|P'], defaults: ['classe' => 0, 'periodo' => 0, 'tipo' => 'V'], methods: ['GET'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function voti(RegistroUtil $reg, StaffUtil $staff, PdfManager $pdf, int $classe,
                       int $periodo, string $tipo): Response {
    // inizializza variabili
    $dati = null;
    $info = null;
    $listaPeriodi = null;
    $datiPeriodo = null;
    // parametro classe
    $classe = $this->recuperaClasseSessione($classe);
    // controllo classe
    if ($classe > 0) {
      $classe = $this->em->getRepository(Classe::class)->find($classe);
      if (!$classe) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $this->controllaAccessoClasse($classe);
    }
    if ($classe) {
      // periodo
      $listaPeriodi = $reg->infoPeriodi();
      // seleziona periodo se non indicato
      if ($periodo == 0) {
        // seleziona periodo in base alla data
        $datiPeriodo = $reg->periodo(new DateTime());
        $periodo = $datiPeriodo['periodo'];
      } else {
        $datiPeriodo = $listaPeriodi[$periodo];
      }
      // informazioni
      $info['classe'] = $classe;
      $info['lista'] = $listaPeriodi;
      $info['periodo'] = $periodo;
      // legge dati
      $dati = $staff->voti($classe, $datiPeriodo);
      // controlla tipo
      if ($tipo == 'P') {
        // crea documento PDF
        $pdf->configure($this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
          'Medie dei voti della classe '.$classe);
        $pdf->getHandler()->setPageOrientation('L', true, 20);
        $html = $this->renderView('pdf/voti_classe.html.twig', [
          'info' => $info,
          'dati' => $dati]);
        $pdf->createFromHtml($html);
        // invia il documento
        $nomefile = 'voti-'.$classe->getAnno().$classe->getSezione().$classe->getGruppo().'.pdf';
        return $pdf->send($nomefile);
      }
    }
    // visualizza pagina
    return $this->render('coordinatore/voti.html.twig', [
      'pagina_titolo' => 'page.coordinatore_voti',
      'info' => $info,
      'dati' => $dati]);
  }

  /**
   * Mostra la situazione dei singoli alunni.
   *
   * @param StaffUtil $staff Funzioni di utilità per lo staff
   * @param int $classe Identificativo della classe
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/coordinatore/situazione/{classe}', name: 'coordinatore_situazione', requirements: ['classe' => '\d+'], defaults: ['classe' => 0], methods: ['GET'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function situazione(StaffUtil $staff, int $classe): Response {
    // inizializza variabili
    $dati = null;
    // parametro classe
    if ($classe == 0) {
      // recupera parametri da sessione
      $classe = $this->reqstack->getSession()->get('/APP/DOCENTE/classe_coordinatore');
    } else {
      // memorizza su sessione
      $this->reqstack->getSession()->set('/APP/DOCENTE/classe_coordinatore', $classe);
    }
    // controllo classe
    if ($classe > 0) {
      $classe = $this->em->getRepository(Classe::class)->find($classe);
      if (!$classe) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      // controllo accesso alla funzione
      if (!($this->getUser() instanceOf Staff) && !($this->getUser() instanceOf Preside)) {
        // coordinatore
        $classi = explode(',', (string) $this->reqstack->getSession()->get('/APP/DOCENTE/coordinatore'));
        if (!in_array($classe->getId(), $classi)) {
          // errore
          throw $this->createNotFoundException('exception.invalid_params');
        }
      }
      // legge dati
      $dati = $staff->alunni($classe);
    }
    // visualizza pagina
    return $this->render('coordinatore/situazione.html.twig', [
      'pagina_titolo' => 'page.coordinatore_situazione',
      'classe' => $classe,
      'dati' => $dati]);
  }

  /**
   * Mostra la situazione di un singolo alunno.
   *
   * @param StaffUtil $staff Funzioni di utilità per lo staff
   * @param PdfManager $pdf Gestore dei documenti PDF
   * @param int $alunno Identificativo dell'alunno
   * @param string $tipo Tipo di informazioni da mostrare [V=voti,S=scrutini,A=assenze,N=note,O=osservazioni,T=tutto]
   * @param string $formato Formato della visualizzazione [H=html,P=pdf]
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/coordinatore/situazione/alunno/{alunno}/{tipo}/{formato}', name: 'coordinatore_situazione_alunno', requirements: ['alunno' => '\d+', 'tipo' => 'V|S|A|N|O|T', 'formato' => 'H|P'], methods: ['GET'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function situazioneAlunno(StaffUtil $staff, PdfManager $pdf, int $alunno, string $tipo,
                                   string $formato): Response {
    // inizializza variabili
    $dati = null;
    $info = null;
    // controllo alunno
    $alunno = $this->em->getRepository(Alunno::class)->find($alunno);
    if (!$alunno) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo classe
    $classe = $alunno->getClasse();
    if (!$classe) {
      $cambio = $this->em->getRepository(CambioClasse::class)->findOneBy(['alunno' => $alunno]);
      if (!$cambio) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $classe = $cambio->getClasse();
    }
    // controllo accesso alla funzione
    if (!($this->getUser() instanceOf Staff) && !($this->getUser() instanceOf Preside)) {
      // coordinatore
      $classi = explode(',', (string) $this->reqstack->getSession()->get('/APP/DOCENTE/coordinatore'));
      if (!in_array($classe->getId(), $classi)) {
        // errore
        throw $this->createNotFoundException('exception.invalid_params');
      }
    }
    // pagina di ritorno
    if ($this->getUser() instanceOf Staff) {
      $info['back'] = 'staff_studenti_situazione';
    } else {
      $info['back'] = 'coordinatore_situazione';
    }
    // legge dati
    $dati = $staff->situazione($alunno, $classe, $tipo);
    // controllo formato
    if ($formato == 'P') {
      // crea documento PDF
      $pdf->configure($this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
        'Situazione alunn'.($alunno->getSesso() == 'M' ? 'o' : 'a').' '.$alunno->getCognome().' '.$alunno->getNome());
      $html = $this->renderView('pdf/situazione_alunno.html.twig', [
        'classe' => $classe,
        'alunno' => $alunno,
        'dati' => $dati,
        'info' => $info]);
      $pdf->createFromHtml($html);
      // invia il documento
      $nomefile = 'situazione-alunno-'.$alunno->getCognome().'-'.$alunno->getNome();
      return $pdf->send($pdf->normalizzaNome($nomefile));
    }
    // visualizza pagina
    return $this->render('coordinatore/situazione_alunno.html.twig', [
      'pagina_titolo' => 'page.coordinatore_situazione',
      'classe' => $classe,
      'alunno' => $alunno,
      'tipo' => $tipo,
      'dati' => $dati,
      'info' => $info]);
  }

  /**
   * Gestione presenze fuori classe
   *
   * @param Request $request Pagina richiesta
   * @param int $classe Identificativo della classe
   * @param int $pagina Numero di pagina per l'elenco da visualizzare
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/coordinatore/presenze/{classe}/{pagina}', name: 'coordinatore_presenze', requirements: ['classe' => '\d+', 'pagina' => '\d+'], defaults: ['classe' => 0, 'pagina' => 0], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function presenze(Request $request, int $classe, int $pagina): Response {
    // init
    $dati = [];
    $info = [];
    $info['classe'] = null;
    $info['annoInizio'] = null;
    $info['annoFine'] = null;
    $info['configErrore'] = false;
    // parametro classe
    $classe = $this->recuperaClasseSessione($classe);
    // recupera criteri dalla sessione
    $criteri = [];
    $criteri['alunno'] = $this->reqstack->getSession()->get('/APP/ROUTE/coordinatore_presenze/alunno', 0);
    $criteri['inizio'] = $this->reqstack->getSession()->get('/APP/ROUTE/coordinatore_presenze/inizio', null);
    $criteri['fine'] = $this->reqstack->getSession()->get('/APP/ROUTE/coordinatore_presenze/fine', null);
    $alunno = ($criteri['alunno'] > 0 ?
      $this->em->getRepository(Alunno::class)->find($criteri['alunno']) : null);
    if ($criteri['inizio']) {
      $inizio = DateTime::createFromFormat('Y-m-d', $criteri['inizio']);
    } else {
      $inizio = new DateTime('tomorrow');
      $criteri['inizio'] = $inizio->format('Y-m-d');
    }
    if ($criteri['fine']) {
      $fine = DateTime::createFromFormat('Y-m-d', $criteri['fine']);
    } else {
      $annoFine = $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_fine');
      $fine = DateTime::createFromFormat('Y-m-d', $annoFine);
      if (!$fine) {
        $fine = null;
        $info['configErrore'] = true;
        $this->addFlash('warning', 'config.anno_fine_non_valido');
      }
      $criteri['fine'] = $fine ? $fine->format('Y-m-d') : '';
    }
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $this->reqstack->getSession()->get('/APP/ROUTE/coordinatore_presenze/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/coordinatore_presenze/pagina', $pagina);
    }
    if ($classe > 0) {
      // controllo classe
      $classe = $this->em->getRepository(Classe::class)->find($classe);
      if (!$classe) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $this->controllaAccessoClasse($classe);
      if ($info['configErrore']) {
        // se la configurazione è non parsabile, non avviamo la ricerca con un range non
        // vincolato; il messaggio flash avvisa l'utente e la funzionalità non viene
        // degradata in modo silente per tutti gli utenti con quel dato.
        $form = null;
      } else {
        // form di ricerca
        $opzioniAlunni = $this->em->getRepository(Alunno::class)->opzioni(true, true,
          $classe->getId());
        $form = $this->createForm(FiltroType::class, null, ['form_mode' => 'presenze',
          'values' => [$alunno, $opzioniAlunni, $inizio, $fine]]);
      }
      if ($form) {
        $form->handleRequest($request);
      }
      if ($form && $form->isSubmitted() && $form->isValid()) {
        // imposta criteri di ricerca
        $inizioData = $form->get('inizio')->getData();
        $fineData = $form->get('fine')->getData();
        if ($inizioData === null || $fineData === null) {
          // date non valide: i criteri restano quelli correnti e si avvisa l'utente
          $this->addFlash('warning', 'exception.invalid_params');
        } else {
          $criteri['alunno'] = (is_object($form->get('alunno')->getData()) ?
            $form->get('alunno')->getData()->getId() : 0);
          $criteri['inizio'] = $inizioData->format('Y-m-d');
          $criteri['fine'] = $fineData->format('Y-m-d');
          $pagina = 1;
          $this->reqstack->getSession()->set('/APP/ROUTE/coordinatore_presenze/alunno', $criteri['alunno']);
          $this->reqstack->getSession()->set('/APP/ROUTE/coordinatore_presenze/inizio', $criteri['inizio']);
          $this->reqstack->getSession()->set('/APP/ROUTE/coordinatore_presenze/fine', $criteri['fine']);
          $this->reqstack->getSession()->set('/APP/ROUTE/coordinatore_presenze/pagina', $pagina);
        }
      }
      // lista fuori classe (solo se la configurazione non è non parsabile)
      if (!$info['configErrore']) {
        $dati = $this->em->getRepository(Presenza::class)->fuoriClasse($classe, $criteri, $pagina);
      }
      // imposta informazioni
      $info['classe'] = $classe;
      $info['pagina'] = $pagina;
      $info['oggi'] = new DateTime('today');
      $dataYMD = $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_inizio');
      if ($dataYMD && strlen((string) $dataYMD) >= 10) {
        $info['annoInizio'] = substr((string) $dataYMD, 8, 2).'/'.substr((string) $dataYMD, 5, 2).'/'.substr((string) $dataYMD, 0, 4);
      } else {
        $info['annoInizio'] = '-';
      }
      $dataYMD = $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_fine');
      if ($dataYMD && strlen((string) $dataYMD) >= 10) {
        $info['annoFine'] = substr((string) $dataYMD, 8, 2).'/'.substr((string) $dataYMD, 5, 2).'/'.substr((string) $dataYMD, 0, 4);
      } else {
        $info['annoFine'] = '-';
      }
    }
    // mostra la pagina di risposta
    if ($info['configErrore']) {
      // se l'anno fine non è configurato correttamente, non cerchiamo con un range
      // artificialmente lontano; il messaggio flash avvisa l'utente.
      return $this->renderHtml('coordinatore', 'presenze', $dati, $info, []);
    }
    return $this->renderHtml('coordinatore', 'presenze', $dati, $info, [
      isset($form) ? $form->createView() : null]);
  }

  /**
   * Modifica una presenza fuori classe pianificata nel futuro
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $id Identificativo della presenza fuori classe
   * @param int $classe Identificativo della classe
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/coordinatore/presenze/edit/{id}/{classe}', name: 'coordinatore_presenze_edit', requirements: ['id' => '\d+', 'classe' => '\d+'], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function presenzeEdit(Request $request, TranslatorInterface $trans, RegistroUtil $reg,
                               LogHandler $dblogger, int $id, int $classe): Response {
    // init
    $dati = [];
    $info = [];
    // controlla presenza
    $presenza = $this->em->getRepository(Presenza::class)->find($id);
    if (!$presenza) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo classe
    $classe = $this->em->getRepository(Classe::class)->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo data futura
    $oggi = new DateTime('today');
    if ($presenza->getData() <= $oggi) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    $this->controllaAccessoClasse($classe);
    // imposta informazioni
    $dataYMD = $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_fine');
    if ($dataYMD && strlen((string) $dataYMD) >= 10) {
      $info['annoFine'] = substr((string) $dataYMD, 8, 2).'/'.substr((string) $dataYMD, 5, 2).'/'.substr((string) $dataYMD, 0, 4);
    } else {
      $info['annoFine'] = '-';
    }
    // form
    $opzioniAlunni = $this->em->getRepository(Alunno::class)->opzioni(true, true,
      $classe->getId());
    $form = $this->createForm(PresenzaType::class, $presenza, [
      'return_url' => $this->generateUrl('coordinatore_presenze'), 'form_mode' => 'edit',
      'values' => [$opzioniAlunni]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // controlla dati
      if ($form->get('data')->getData() <= $oggi) {
        // errore data non è futura
        $form->addError(new FormError($trans->trans('exception.presenze_data_non_futura')));
      }
      $this->controllaTipoOra($form, $trans);
      // controlla permessi
      if (!$reg->azionePresenze($form->get('data')->getData(), $this->getUser(),
          $form->get('alunno')->getData(), $classe)) {
        // errore: azione non permessa
        $form->addError(new FormError($trans->trans('exception.presenze_azione_non_permessa')));
      }
      if ($form->isValid()) {
        // ok: memorizzazione e log
        $dblogger->logAzione('PRESENZE', 'Modifica presenza');
        // messaggio
        $this->addFlash('success', 'message.update_ok');
        // redirect
        return $this->redirectToRoute('coordinatore_presenze');
      }
    }
    // mostra la pagina di risposta
    return $this->renderHtml('coordinatore', 'presenze_edit', $dati, $info, [$form->createView(),
      'message.required_fields']);
  }

  /**
   * Cancella una presenza fuori classe pianificata nel futuro
   *
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $id Identificativo della presenza fuori classe
   * @param int $classe Identificativo della classe
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/coordinatore/presenze/delete/{id}/{classe}', name: 'coordinatore_presenze_delete', requirements: ['id' => '\d+', 'classe' => '\d+'], methods: ['POST'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function presenzeDelete(Request $request, RegistroUtil $reg, LogHandler $dblogger, int $id,
                                 int $classe): Response {
    // valida token CSRF
    if (!$this->isCsrfTokenValid('delete', $request->request->get('_csrf_token'))) {
      throw $this->createNotFoundException('exception.invalid_token');
    }
    // controlla presenza
    $presenza = $this->em->getRepository(Presenza::class)->find($id);
    if (!$presenza) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo classe
    $classe = $this->em->getRepository(Classe::class)->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo data futura
    $oggi = new DateTime('today');
    if ($presenza->getData() <= $oggi) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo accesso alla funzione
    if (!($this->getUser() instanceOf Staff) && !($this->getUser() instanceOf Preside)) {
      // coordinatore
      $classi = explode(',', (string) $this->reqstack->getSession()->get('/APP/DOCENTE/coordinatore'));
      if (!in_array($classe->getId(), $classi)) {
        // errore
        throw $this->createNotFoundException('exception.invalid_params');
      }
    }
    // controlla permessi
    if (!$reg->azionePresenze($presenza->getData(), $this->getUser(), $presenza->getAlunno(), $classe)) {
      // errore: azione non permessa
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // cancella presenza
    $this->em->remove($presenza);
    // ok: memorizzazione e log
    $dblogger->logAzione('PRESENZE', 'Cancella presenza');
    // messaggio
    $this->addFlash('success', 'message.update_ok');
    // redirect
    return $this->redirectToRoute('coordinatore_presenze');
  }

  /**
   * Aggiunge nuove presenze fuori classe pianificate nel futuro
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $classe Identificativo della classe
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/coordinatore/presenze/add/{classe}', name: 'coordinatore_presenze_add', requirements: ['classe' => '\d+'], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function presenzeAdd(Request $request, TranslatorInterface $trans, RegistroUtil $reg,
                              LogHandler $dblogger, int $classe): Response {
    // init
    $dati = [];
    $info = [];
    // controllo classe
    $classe = $this->em->getRepository(Classe::class)->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    $this->controllaAccessoClasse($classe);
    // imposta informazioni
    $dataYMD = $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_fine');
    if ($dataYMD && strlen((string) $dataYMD) >= 10) {
      $info['annoFine'] = substr((string) $dataYMD, 8, 2).'/'.substr((string) $dataYMD, 5, 2).'/'.substr((string) $dataYMD, 0, 4);
    } else {
      $info['annoFine'] = '-';
    }
    // form
    $opzioniAlunni = $this->em->getRepository(Alunno::class)->opzioni(true, true,
      $classe->getId());
    $form = $this->createForm(PresenzaType::class, null, [
      'return_url' => $this->generateUrl('coordinatore_presenze'), 'form_mode' => 'add',
      'values' => [$opzioniAlunni]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // controlla dati
      $alunni = $form->get('alunni')->getData();
      $dataInizio = $form->get('data')->getData();
      $dataFine = $form->get('dataFine')->getData();
      $settimana = $form->get('settimana')->getData();
      if (count($alunni) == 0) {
        // errore alunni non indicati
        $form->addError(new FormError($trans->trans('exception.presenze_alunni_mancanti')));
      }
      $oggi = new DateTime('today');
      if ($dataInizio <= $oggi) {
        // errore data non è futura
        $form->addError(new FormError($trans->trans('exception.presenze_data_non_futura')));
      }
      if ($dataFine < $dataInizio) {
        // errore intervallo date
        $form->addError(new FormError($trans->trans('exception.presenze_intervallo_date')));
      }
      if (count($settimana) == 0) {
        // errore periodicità settimanale
        $form->addError(new FormError($trans->trans('exception.presenze_periodicita')));
      }
      $this->controllaTipoOra($form, $trans);
      // genera date
      $listaDate = $this->generaDatePresenze($reg, $dataInizio, $dataFine, $settimana, $classe);
      if (count($listaDate) == 0) {
        // errore nessuna data
        $form->addError(new FormError($trans->trans('exception.presenze_data_mancante')));
      }
      // controlla permessi (solo data inziale)
      foreach ($alunni as $alunno) {
        if (!empty($listaDate) &&
            !$reg->azionePresenze($listaDate[0], $this->getUser(), $alunno, $classe)) {
          // errore: azione non permessa
          $form->addError(new FormError($trans->trans('exception.presenze_azione_non_permessa')));
        }
      }
      if ($form->isValid()) {
        // ok: memorizzazione e log
        $this->creaPresenze($alunni, $listaDate, $form, $dblogger);
        // messaggio
        $this->addFlash('success', 'message.update_ok');
        // redirect
        return $this->redirectToRoute('coordinatore_presenze');
      }
    }
    // mostra la pagina di risposta
    return $this->renderHtml('coordinatore', 'presenze_add', $dati, $info, [$form->createView(),
      'message.required_fields']);
  }

  /**
   * Controllo moduli formativi svolti: situazione della classe
   *
   * @param Request $request Pagina richiesta
   * @param int $classe Identificativo della classe
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/coordinatore/moduliFormativi/{classe}', name: 'coordinatore_moduliFormativi', requirements: ['classe' => '\d+'], defaults: ['classe' => 0], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function moduliFormativi(Request $request, int $classe): Response {
    // init
    $dati = [];
    $info = [];
    // parametro classe
    if ($classe == 0) {
      // recupera parametri da sessione
      $classe = $this->reqstack->getSession()->get('/APP/DOCENTE/classe_coordinatore');
    } else {
      // memorizza su sessione
      $this->reqstack->getSession()->set('/APP/DOCENTE/classe_coordinatore', $classe);
    }
    // recupera criteri dalla sessione
    $criteri = [];
    $criteri['tipo'] = $this->reqstack->getSession()->get('/APP/ROUTE/coordinatore_moduliFormativi/tipo', null);
    $criteri['moduloFormativo'] = (int) $this->reqstack->getSession()->get('/APP/ROUTE/coordinatore_moduliFormativi/moduloFormativo', 0);
    // controllo classe
    if ($classe > 0) {
      $classe = $this->em->getRepository(Classe::class)->find($classe);
      if (!$classe) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      // controllo accesso alla funzione
      if (!($this->getUser() instanceOf Staff) && !($this->getUser() instanceOf Preside)) {
        // coordinatore
        $classi = explode(',', (string) $this->reqstack->getSession()->get('/APP/DOCENTE/coordinatore'));
        if (!in_array($classe->getId(), $classi)) {
          // errore
          throw $this->createNotFoundException('exception.invalid_params');
        }
      }
      // informazioni sulla pagina
      $info['classe'] = $classe;
      // form filtro
      $criteri['classe'] = $classe;
      $criteri['sede'] = $classe->getSede();
      $opzioniTipi = $this->em->getRepository(ModuloFormativo::class)->opzioniTipiClasse($classe);
      $opzioniModuli = $this->em->getRepository(ModuloFormativo::class)->opzioniModuliClasse($classe);
      $form = $this->createForm(FiltroType::class, null, ['form_mode' => 'moduliFormativi',
        'values' => [$criteri['tipo'], $opzioniTipi, $criteri['moduloFormativo'], $opzioniModuli]]);
      $form->handleRequest($request);
      if ($form->isSubmitted() && $form->isValid()) {
        // imposta criteri di ricerca
        $criteri['tipo'] = $form->get('tipo')->getData();
        $criteri['moduloFormativo'] = $form->get('moduloFormativo')->getData();
        $this->reqstack->getSession()->set('/APP/ROUTE/coordinatore_moduliFormativi/tipo', $criteri['tipo']);
        $this->reqstack->getSession()->set('/APP/ROUTE/coordinatore_moduliFormativi/moduloFormativo', $criteri['moduloFormativo']);
      }
      // legge dati
      $dati = $this->em->getRepository(ModuloFormativo::class)->cerca($criteri);
    }
    // mostra la pagina di risposta
    return $this->renderHtml('coordinatore', 'moduliFormativi', $dati, $info,
      [isset($form) ? $form->createView() : null]);
  }

  /**
   * Controllo moduli formativi svolti: situazione degli alunni
   *
   * @param int $classe Identificativo della classe
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/coordinatore/moduliFormativi/alunni/{classe}', name: 'coordinatore_moduliFormativi_alunni', requirements: ['classe' => '\d+'], defaults: ['classe' => 0], methods: ['GET'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function moduliFormativiAlunni(int $classe): Response {
    // init
    $dati = [];
    $info = [];
    // parametro classe
    if ($classe == 0) {
      // recupera parametri da sessione
      $classe = $this->reqstack->getSession()->get('/APP/DOCENTE/classe_coordinatore');
    } else {
      // memorizza su sessione
      $this->reqstack->getSession()->set('/APP/DOCENTE/classe_coordinatore', $classe);
    }
    // controllo classe
    if ($classe > 0) {
      $classe = $this->em->getRepository(Classe::class)->find($classe);
      if (!$classe) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      // controllo accesso alla funzione
      if (!($this->getUser() instanceOf Staff) && !($this->getUser() instanceOf Preside)) {
        // coordinatore
        $classi = explode(',', (string) $this->reqstack->getSession()->get('/APP/DOCENTE/coordinatore'));
        if (!in_array($classe->getId(), $classi)) {
          // errore
          throw $this->createNotFoundException('exception.invalid_params');
        }
      }
      // informazioni sulla pagina
      $info['classe'] = $classe;
      // legge dati
      $dati = $this->em->getRepository(ModuloFormativo::class)->alunni($classe);
    }
    // mostra la pagina di risposta
    return $this->renderHtml('coordinatore', 'moduliFormativi_alunni', $dati, $info, []);
  }

  /**
   * Recupera dalla sessione la classe di lavoro del coordinatore, se non indicata
   *
   * @param int $classe Identificativo della classe indicato nella pagina
   *
   * @return int Identificativo della classe di lavoro
   */
  private function recuperaClasseSessione(int $classe): int {
    if ($classe == 0) {
      // recupera parametri da sessione: 0 se non e' mai stata scelta, cosi'
      // chi non ha ancora scelto una classe atterra sulla pagina con il solo
      // selettore invece di prendere un TypeError
      return (int) $this->reqstack->getSession()->get('/APP/DOCENTE/classe_coordinatore', 0);
    }
    // memorizza su sessione
    $this->reqstack->getSession()->set('/APP/DOCENTE/classe_coordinatore', $classe);
    return $classe;
  }

  /**
   * Controlla che l'utente sia autorizzato ad accedere ai dati della classe indicata
   *
   * @param Classe $classe Classe di cui controllare l'accesso
   */
  private function controllaAccessoClasse(Classe $classe): void {
    if (!($this->getUser() instanceOf Staff) && !($this->getUser() instanceOf Preside)) {
      // coordinatore
      $classi = explode(',', (string) $this->reqstack->getSession()->get('/APP/DOCENTE/coordinatore'));
      if (!in_array($classe->getId(), $classi)) {
        // errore
        throw $this->createNotFoundException('exception.invalid_params');
      }
    }
  }

  /**
   * Controlla la coerenza tra il tipo di ora indicato e gli orari inseriti nel form
   *
   * @param FormInterface $form Form di inserimento della presenza fuori classe
   * @param TranslatorInterface $trans Gestore delle traduzioni
   */
  private function controllaTipoOra(FormInterface $form, TranslatorInterface $trans): void {
    if (($form->get('oraTipo')->getData() == 'G' && (!empty($form->get('oraInizio')->getData()) ||
        !empty($form->get('oraFine')->getData()))) ||
        ($form->get('oraTipo')->getData() == 'F' && !empty($form->get('oraFine')->getData()))) {
      // errore tipo con dati errati
      $form->addError(new FormError($trans->trans('exception.presenze_tipo_ora_errato')));
    } elseif (($form->get('oraTipo')->getData() == 'F' && empty($form->get('oraInizio')->getData())) ||
        ($form->get('oraTipo')->getData() == 'I' && empty($form->get('oraInizio')->getData())) ||
        ($form->get('oraTipo')->getData() == 'I' && empty($form->get('oraFine')->getData()))) {
      // errore tipo con dati mancanti
      $form->addError(new FormError($trans->trans('exception.presenze_tipo_ora_mancante')));
    } elseif ($form->get('oraTipo')->getData() == 'I' &&
        $form->get('oraInizio')->getData() > $form->get('oraFine')->getData()) {
      // errore tipo con dati mancanti
      $form->addError(new FormError($trans->trans('exception.presenze_tipo_ora_errato')));
    }
  }

  /**
   * Genera l'elenco delle date delle presenze fuori classe indicate nell'intervallo
   *
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param mixed $dataInizio Data iniziale dell'intervallo
   * @param mixed $dataFine Data finale dell'intervallo
   * @param array $settimana Giorni della settimana selezionati
   * @param Classe $classe Classe di riferimento
   *
   * @return array Lista delle date delle presenze da creare
   */
  private function generaDatePresenze(RegistroUtil $reg, mixed $dataInizio, mixed $dataFine, array $settimana,
                                      Classe $classe): array {
    $listaDate = [];
    while ($dataInizio <= $dataFine) {
      $giorno = $dataInizio->format('w');
      if (in_array($giorno, $settimana, true) && !$reg->controlloData($dataInizio, $classe->getSede())) {
        // data presente in settimana
        $listaDate[] = clone $dataInizio;
      }
      // data successiva
      $dataInizio->modify('+1 day');
    }
    return $listaDate;
  }

  /**
   * Crea le presenze fuori classe indicate, saltando quelle già esistenti
   *
   * @param array $alunni Lista degli alunni interessati
   * @param array $listaDate Lista delle date delle presenze
   * @param FormInterface $form Form di inserimento della presenza fuori classe
   * @param LogHandler $dblogger Gestore dei log su database
   */
  private function creaPresenze(array $alunni, array $listaDate, FormInterface $form, LogHandler $dblogger): void {
    foreach ($alunni as $alunno) {
      foreach ($listaDate as $data) {
        if ($this->em->getRepository(Presenza::class)->findOneBy(['alunno' => $alunno,
            'data' => $data])) {
          // salta fuori classe esistente
          continue;
        }
        $presenza = (new Presenza())
          ->setData($data)
          ->setOraInizio($form->get('oraInizio')->getData())
          ->setOraFine($form->get('oraFine')->getData())
          ->setTipo($form->get('tipo')->getData())
          ->setDescrizione($form->get('descrizione')->getData())
          ->setAlunno($alunno);
        $this->em->persist($presenza);
        $dblogger->logAzione('PRESENZE', 'Aggiunge presenza');
      }
    }
  }

}
