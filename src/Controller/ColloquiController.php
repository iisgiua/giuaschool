<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Controller;

use Symfony\Component\Security\Http\Attribute\IsGranted;
use DateTime;
use App\Entity\Festivita;
use App\Entity\Docente;
use App\Entity\ScansioneOraria;
use App\Entity\Cattedra;
use App\Entity\Colloquio;
use App\Entity\RichiestaColloquio;
use App\Form\ColloquioType;
use App\Form\FiltroType;
use App\Form\PrenotazioneType;
use App\Form\RichiestaColloquioType;
use App\Util\ColloquiUtil;
use App\Util\LogHandler;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;


/**
 * ColloquiController - gestione dei colloqui
 *
 * @author Antonello Dessì
 */
class ColloquiController extends BaseController {

  /**
   * Visualizza le richieste di colloquio
   *
   * @return Response Pagina di risposta
   */
  #[Route(path: '/colloqui/richieste', name: 'colloqui_richieste', methods: ['GET'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function richieste(): Response {
    // inizializza
    $info = [];
    $dati = [];
    // controllo fine colloqui
    $oggi = new DateTime('today');
    $fine = DateTime::createFromFormat('Y-m-d H:i:s',
      $this->reqstack->getSession()->get('/CONFIG/SCUOLA/fine_colloqui').' 00:00:00');
    if ($oggi > $fine) {
      // visualizza errore
      $info['errore'] = 'exception.colloqui_sospesi';
    } else {
      // richieste valide
      $dati = $this->em->getRepository(Colloquio::class)->richiesteValide($this->getUser());
    }
    // pagina di risposta
    return $this->renderHtml('colloqui', 'richieste', $dati, $info);
  }

  /**
   * Visualizza le vecchie richieste di colloquio ricevute dal docente
   *
   * @return Response Pagina di risposta
   */
  #[Route(path: '/colloqui/storico', name: 'colloqui_storico', methods: ['GET'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function storico(): Response {
    // inizializza
    $info = [];
    $dati = [];
    // storico richieste
    $dati['storico'] = $this->em->getRepository(RichiestaColloquio::class)->storico($this->getUser());
    // pagina di risposta
    return $this->renderHtml('colloqui', 'storico', $dati, $info);
  }

  /**
   * Conferma una prenotazione per il colloquio
   *
   * @param Request $request Pagina richiesta
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $id Identificativo della richiesta di colloquio
   *
   * @return Response Pagina di risposta
   */
  #[Route(path: '/colloqui/conferma/{id}', name: 'colloqui_conferma', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function conferma(Request $request, LogHandler $dblogger, int $id): Response {
    // inizializza
    $info = [];
    $dati = [];
    // controlla richiesta
    $richiesta = $this->em->getRepository(RichiestaColloquio::class)->find($id);
    if (!$richiesta || $richiesta->getColloquio()->getDocente()->getId() != $this->getUser()->getId() ||
        !$richiesta->getColloquio()->getAbilitato() || $richiesta->getStato() != 'R') {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // informazioni per la visualizzazione
    $info['data'] = $richiesta->getColloquio()->getData();
    $info['tipo'] = $richiesta->getColloquio()->getTipo();
    $info['classe'] = ''.$richiesta->getAlunno()->getClasse();
    $info['alunno'] = ''.$richiesta->getAlunno();
    // form di inserimento
    $form = $this->createForm(RichiestaColloquioType::class, $richiesta, ['form_mode' => 'conferma']);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // modifica stato
      $richiesta->setStato('C');
      // ok: memorizzazione e log
      $dblogger->logAzione('COLLOQUI', 'Conferma richiesta');
      // redirezione
      return $this->redirectToRoute('colloqui_richieste');
    }
    // pagina di risposta
    return $this->renderHtml('colloqui', 'conferma', $dati, $info, [$form->createView(), 'message.conferma_colloquio']);
  }

  /**
   * Rifiuta una richiesta di prenotazione per il colloquio
   *
   * @param Request $request Pagina richiesta
   * @param LogHandler $dblogger Gestore dei log su database
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param int $id Identificativo della richiesta di colloquio
   *
   * @return Response Pagina di risposta
   */
  #[Route(path: '/colloqui/rifiuta/{id}', name: 'colloqui_rifiuta', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function rifiuta(Request $request, LogHandler $dblogger, TranslatorInterface $trans,
                          int $id): Response {
    // inizializza
    $info = [];
    $dati = [];
    // controlla richiesta
    $richiesta = $this->em->getRepository(RichiestaColloquio::class)->find($id);
    if (!$richiesta || $richiesta->getColloquio()->getDocente()->getId() != $this->getUser()->getId() ||
        !$richiesta->getColloquio()->getAbilitato() || $richiesta->getStato() != 'R') {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // informazioni per la visualizzazione
    $info['data'] = $richiesta->getColloquio()->getData();
    $info['tipo'] = $richiesta->getColloquio()->getTipo();
    $info['classe'] = ''.$richiesta->getAlunno()->getClasse();
    $info['alunno'] = ''.$richiesta->getAlunno();
    // form di inserimento
    $form = $this->createForm(RichiestaColloquioType::class, $richiesta, ['form_mode' => 'rifiuta']);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      if (empty($richiesta->getMessaggio())) {
        // errore: messaggio vuoto
        $form->addError(new FormError($trans->trans('exception.colloquio_no_messaggio')));
      } else {
        // modifica stato
        $richiesta->setStato('N');
        // ok: memorizzazione e log
        $dblogger->logAzione('COLLOQUI', 'Rifiuta richiesta');
        // redirezione
        return $this->redirectToRoute('colloqui_richieste');
      }
    }
    // pagina di risposta
    return $this->renderHtml('colloqui', 'rifiuta', $dati, $info, [$form->createView(), 'message.rifiuta_colloquio']);
  }

  /**
   * Modifica la risposta ad una richiesta di prenotazione per il colloquio
   *
   * @param Request $request Pagina richiesta
   * @param LogHandler $dblogger Gestore dei log su database
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param int $id Identificativo della richiesta di colloquio
   *
   * @return Response Pagina di risposta
   */
  #[Route(path: '/colloqui/modifica/{id}', name: 'colloqui_modifica', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function modifica(Request $request, LogHandler $dblogger, TranslatorInterface $trans,
                           int $id): Response {
    // inizializza
    $info = [];
    $dati = [];
    // controlla richiesta
    $richiesta = $this->em->getRepository(RichiestaColloquio::class)->find($id);
    if (!$richiesta || $richiesta->getColloquio()->getDocente()->getId() != $this->getUser()->getId() ||
        !$richiesta->getColloquio()->getAbilitato() || !in_array($richiesta->getStato(), ['C', 'N'], true)) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // informazioni per la visualizzazione
    $info['data'] = $richiesta->getColloquio()->getData();
    $info['tipo'] = $richiesta->getColloquio()->getTipo();
    $info['classe'] = ''.$richiesta->getAlunno()->getClasse();
    $info['alunno'] = ''.$richiesta->getAlunno();
    // form di inserimento
    $form = $this->createForm(RichiestaColloquioType::class, $richiesta, ['form_mode' => 'modifica']);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      if (empty($richiesta->getMessaggio())) {
        // errore: messaggio vuoto
        $form->addError(new FormError($trans->trans('exception.colloquio_no_messaggio')));
      } else {
        // ok: memorizzazione e log
        $dblogger->logAzione('COLLOQUI', 'Modifica richiesta');
        // redirezione
        return $this->redirectToRoute('colloqui_richieste');
      }
    }
    // pagina di risposta
    return $this->renderHtml('colloqui', 'modifica', $dati, $info, [$form->createView(), 'message.modifica_colloquio']);
  }

  /**
   * Gestione dell'inserimento dei giorni di colloquio
   *
   * @return Response Pagina di risposta
   */
  #[Route(path: '/colloqui/gestione/', name: 'colloqui_gestione', methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function gestione(): Response {
    // inizializza
    $info = [];
    $dati = [];
    $inizio = DateTime::createFromFormat('Y-m-d H:i:s',
      $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_inizio').' 00:00:00');
    // legge dati
    $dati = $this->em->getRepository(Colloquio::class)->ricevimenti($this->getUser(), $inizio);
    // pagina di risposta
    return $this->renderHtml('colloqui', 'gestione', $dati, $info);
  }

  /**
   * Crea o modifica i dati per un ricevimento del docente
   *
   * @param Request $request Pagina richiesta
   * @param ColloquiUtil $col Funzioni di utilità per i colloqui
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $id Identificativo del ricevimento esistente
   *
   * @return Response Pagina di risposta
   */
  #[Route(path: '/colloqui/edit/{id}', name: 'colloqui_edit', requirements: ['id' => '\d+'], defaults: ['id' => '0'], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function edit(Request $request, ColloquiUtil $col, TranslatorInterface $trans,
                       LogHandler $dblogger, int $id): Response {
    // inizializza
    $info = [];
    $dati = [];
    // controlla azione
    if ($id > 0) {
      // azione edit
      $oggi = new DateTime('today');
      $colloquio = $this->em->getRepository(Colloquio::class)->findOneBy(['id' => $id,
        'docente' => $this->getUser()]);
      if (!$colloquio || $colloquio->getData() < $oggi) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
    } else {
      // azione add
      $colloquio = (new Colloquio())
        ->setDocente($this->getUser())
        ->setData(new DateTime('today'))
        ->setInizio(new DateTime('08:30'))
        ->setFine(new DateTime('09:30'))
        ->setDurata(10);
      $this->em->persist($colloquio);
    }
    // informazioni per la visualizzazione
    $inizio = DateTime::createFromFormat('Y-m-d',
      $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_inizio'));
    $oggi = new DateTime('today');
    $info['inizio'] = $inizio > $oggi ? $inizio->format('d/m/Y') : $oggi->format('d/m/Y');
    $fine = DateTime::createFromFormat('Y-m-d H:i:s',
      $this->reqstack->getSession()->get('/CONFIG/SCUOLA/fine_colloqui').' 00:00:00');
    $info['fine'] = $fine->format('d/m/Y');
    $info['festivi'] = $this->em->getRepository(Festivita::class)->listaFestivi();
    // lista sedi
    $listaSedi = $this->em->getRepository(Docente::class)->sedi($this->getUser());
    if (isset($listaSedi[''])) {
      // elimina opzione vuota
      unset($listaSedi['']);
    }
    // lista sedi
    foreach ($listaSedi as $idSede) {
      $info['orario'][$idSede] = $this->em->getRepository(ScansioneOraria::class)->orarioSede($idSede);
    }
    $listaOre = [];
    for ($i = 1; $i <= 10; $i++) {
      $listaOre[$i] = $i;
    }
    // determina l'ora di lezione
    $info['ora'] = 1;
    if ($id > 0) {
      $giorno = $colloquio->getData()->format('w');
      $sede = $listaSedi[array_key_first($listaSedi)];
      $info['ora'] = 1;
      foreach ($info['orario'][$sede][$giorno] as $ora => $orario) {
        if ($orario->getInizio() == $colloquio->getInizio()) {
          $info['ora'] = $ora;
          break;
        }
      }
    }
    // form di inserimento
    $form = $this->createForm(ColloquioType::class, $colloquio, ['form_mode' => 'singolo',
      'values' => [$listaSedi, $listaOre]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // controlla data
      $data = $form->get('data')->getData();
      $sede = $form->get('sede')->getData();
      $ora = $form->get('ora')->getData();
      $giorno = $data->format('w');
      $oraInizio = $info['orario'][$sede][$giorno][$ora]->getInizio();
      $oraFine = $info['orario'][$sede][$giorno][$ora]->getFine();
      $colloquio->setInizio($oraInizio);
      $colloquio->setFine($oraFine);
      if ($this->em->getRepository(Festivita::class)->giornoFestivo($data) || $data < $oggi ||
          $data > $fine) {
        // errore: data non valida
        $form->addError(new FormError($trans->trans('exception.colloquio_data_invalida')));
      }
      // controlla se esite già
      if ($this->em->getRepository(Colloquio::class)->sovrapposizione($this->getUser(), $data,
          $inizio, $fine, $id)) {
        // errore: sovrapposizione
        $form->addError(new FormError($trans->trans('exception.colloquio_duplicato')));
      }
      // controlla link
      if ($colloquio->getTipo() == 'D') {
        $link = $colloquio->getLuogo();
        if (str_ends_with((string) $link, 'meet.google.com/') || str_ends_with((string) $link, 'meet.google.com')) {
          // errore: link non valido
          $form->addError(new FormError($trans->trans('exception.colloquio_link_invalido')));
        }
        if (!str_starts_with((string) $link, 'https://') && !str_starts_with((string) $link, 'http://')) {
          $colloquio->setLuogo('https://'.$link);
        }
      }
      if ($form->isValid()) {
        // clcola numero colloqui
        $colloquio->setNumero($col->numeroColloqui($colloquio));
        // ok: memorizzazione e log
        $dblogger->logAzione('COLLOQUI', $id ? 'Modifica ricevimento' : 'Aggiunge ricevimento');
        // redirezione
        return $this->redirectToRoute('colloqui_gestione');
      }
    }
    // pagina di risposta
    return $this->renderHtml('colloqui', 'edit', $dati, $info, [$form->createView(),
      'message.edit_ricevimento_singolo']);
  }

  /**
   * Abilita/dsabilita un ricevimento del docente.
   *
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $id Ricevimento da cancellare
   *
   * @return Response Pagina di risposta
   */
  #[Route(path: '/colloqui/enable/{id}/{stato}', name: 'colloqui_enable', requirements: ['id' => '\d+', 'stato' => '0|1'], methods: ['GET'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function enable(LogHandler $dblogger, int $id, int $stato): Response {
    // controlla colloquio
    $oggi = new DateTime('today');
    $colloquio = $this->em->getRepository(Colloquio::class)->findOneBy(['id' => $id,
      'docente' => $this->getUser()]);
    if (!$colloquio || $colloquio->getData() < $oggi) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla se presenti richieste
    if ($this->em->getRepository(Colloquio::class)->numeroRichieste($colloquio) > 0) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // abilita/disabilita colloquio
    $colloquio->setAbilitato($stato);
    // memorizzazione e log
    $dblogger->logAzione('COLLOQUI', 'Abilita/Disabilita ricevimento');
    // redirezione
    return $this->redirectToRoute('colloqui_gestione');
  }

  /**
   * Crea più ricevimenti periodici del docente
   *
   * @param Request $request Pagina richiesta
   * @param ColloquiUtil $col Funzioni di utilità per i colloqui
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param LogHandler $dblogger Gestore dei log su database
   *
   * @return Response Pagina di risposta
   */
  #[Route(path: '/colloqui/create', name: 'colloqui_create', methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function create(Request $request, ColloquiUtil $col, LogHandler $dblogger,
                         TranslatorInterface $trans): Response {
    // inizializza
    $info = [];
    $dati = [];
    // imposta colloquio fittizio
    $colloquio = (new Colloquio())
      ->setDocente($this->getUser())
      ->setData(new DateTime('today'))
      ->setInizio(new DateTime('08:30'))
      ->setFine(new DateTime('09:30'))
      ->setDurata(10);
    // lista sedi
    $listaSedi = $this->em->getRepository(Docente::class)->sedi($this->getUser());
    if (isset($listaSedi[''])) {
      // elimina opzione vuota
      unset($listaSedi['']);
    }
    // informazioni per la visualizzazione
    foreach ($listaSedi as $idSede) {
      $info['orario'][$idSede] = $this->em->getRepository(ScansioneOraria::class)->orarioSede($idSede);
    }
    $listaOre = [];
    for ($i = 1; $i <= 10; $i++) {
      $listaOre[$i] = $i;
    }
    // form di inserimento
    $form = $this->createForm(ColloquioType::class, $colloquio, ['form_mode' => 'periodico',
      'values' => [$listaSedi, $listaOre]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // legge dati
      $tipo = $form->get('tipo')->getData();
      $frequenza = $form->get('frequenza')->getData();
      $durata = $form->get('durata')->getData();
      $sede = $form->get('sede')->getData();
      $giorno = $form->get('giorno')->getData();
      $ora = $form->get('ora')->getData();
      $luogo = $form->get('luogo')->getData();
      $inizio = $info['orario'][$sede][$giorno][$ora]->getInizio();
      $fine = $info['orario'][$sede][$giorno][$ora]->getFine();
      // controlla link
      if ($form->get('tipo')->getData() == 'D') {
        if (str_ends_with((string) $luogo, 'meet.google.com/') || str_ends_with((string) $luogo, 'meet.google.com')) {
          // errore: link non valido
          $form->addError(new FormError($trans->trans('exception.colloquio_link_invalido')));
        }
        if (!str_starts_with((string) $luogo, 'https://') && !str_starts_with((string) $luogo, 'http://')) {
          $luogo = 'https://'.$luogo;
        }
      }
      if ($form->isValid()) {
        // genera date
        $avviso = $col->generaDate($this->getUser(), $tipo, $frequenza, $durata, $giorno, $inizio, $fine, $luogo);
        if (!empty($avviso)) {
          // mostra avviso
          $this->addFlash('avviso', $avviso);
        }
        // redirezione
        return $this->redirectToRoute('colloqui_gestione');
      }
    }
    // pagina di risposta
    return $this->renderHtml('colloqui', 'create', $dati, $info, [$form->createView(),
      'message.create_ricevimento_periodico']);
  }

  /**
   * Mostra le date di ricevimento dei docenti ai genitori.
   *
   * @param ColloquiUtil $col Funzioni di utilità per i colloqui
   * @param TranslatorInterface $trans Gestore delle traduzioni
   *
   * @return Response Pagina di risposta
   */
  #[Route(path: '/colloqui/genitori', name: 'colloqui_genitori', methods: ['GET'])]
  #[IsGranted('ROLE_GENITORE')]
  public function genitori(ColloquiUtil $col, TranslatorInterface $trans): Response {
    // inizializza
    $info = [];
    $dati = [];
    // controlla alunno
    $alunno = $this->getUser()->getAlunno();
    if (!$alunno || !$alunno->getAbilitato()) {
      // errore
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // controlla classe
    $classe = $alunno->getClasse();
    if ($classe) {
      // recupera dati
      $dati = $col->colloquiGenitori($classe, $alunno, $this->getUser());
    } else {
      // nessuna classe
      $info['errore'] = $trans->trans('exception.genitori_classe_nulla', ['sex' => $alunno->getSesso() == 'M' ? 'o' : 'a']);
    }
    // pagina di risposta
    return $this->renderHtml('colloqui', 'genitori', $dati, $info);
  }

  /**
   * Invia la disdetta del genitore alla richiesta di colloquio.
   *
   * @param LogHandler $dblogger Gestore dei log su database
   *
   * @return Response Pagina di risposta
   */
  #[Route(path: '/colloqui/disdetta/{id}', name: 'colloqui_disdetta')]
  #[IsGranted('ROLE_GENITORE')] // requirements={"id": "\d+"},
  public function disdetta(LogHandler $dblogger, int $id): Response {
    // controlla alunno
    $alunno = $this->getUser()->getAlunno();
    if (!$alunno || !$alunno->getAbilitato()) {
      // errore
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // controlla classe
    $classe = $alunno->getClasse();
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // controlla richiesta
    $richiesta = $this->em->getRepository(RichiestaColloquio::class)->findOneBy(['id' => $id,
      'alunno' => $alunno, 'stato' => ['R', 'C']]);
    if (!$richiesta) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // annulla richiesta
    $richiesta
      ->setStato('A')
      ->setMessaggio('')
      ->setGenitoreAnnulla($this->getUser());
    // ok: memorizzazione e log
    $dblogger->logAzione('COLLOQUI', 'Disdetta prenotazione');
    // redirezione
    return $this->redirectToRoute('colloqui_genitori');
  }

  /**
   * Invia la prenotazione per il colloquio con un docente.
   *
   * @param Request $request Pagina richiesta
   * @param ColloquiUtil $col Funzioni di utilità per i colloqui
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param LogHandler $dblogger Gestore dei log su database
   *
   * @return Response Pagina di risposta
   */
  #[Route(path: '/colloqui/prenota/{docente}', name: 'colloqui_prenota')]
  #[IsGranted('ROLE_GENITORE')] // requirements={"docente": "\d+"},
  public function prenota(Request $request, ColloquiUtil $col, TranslatorInterface $trans,
                          LogHandler $dblogger, int $docente): Response {
    // inizializza
    $info = [];
    $dati = [];
    // controlla docente
    $docente = $this->em->getRepository(Docente::class)->findOneBy(['id' => $docente, 'abilitato' => 1]);
    if (!$docente) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla alunno
    $alunno = $this->getUser()->getAlunno();
    if (!$alunno || !$alunno->getAbilitato()) {
      // errore
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // controlla classe
    $classe = $alunno->getClasse();
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // lista date
    $dati = $col->dateRicevimento($docente);
    // informazioni per la visualizzazione
    $info['docente'] = "".$docente;
    $cattedre = $this->em->getRepository(Cattedra::class)->cattedreDocente($docente, 'Q');
    $info['materie'] = [];
    foreach ($cattedre as $cattedra) {
      if ($cattedra->getClasse() == $classe) {
        $info['materie'][] = ($cattedra->getTipo() == 'I' ? 'Lab. ' : '').$cattedra->getMateria()->getNome();
      }
    }
    // form di inserimento
    $form = $this->createForm(PrenotazioneType::class, null, ['form_mode' => 'prenotazione',
      'values' => [$dati['lista']]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      $colloquioId = $form->get('data')->getData();
      // controlla duplicati
      $prenotazione = $this->em->getRepository(RichiestaColloquio::class)->findOneBy([
        'colloquio' => $colloquioId, 'alunno' => $alunno, 'stato' => ['R', 'C']]);
      if (!empty($prenotazione)) {
        // esiste già richiesta
        $form->addError(new FormError($trans->trans('exception.colloqui_esiste')));
      } else {
        // nuova richiesta
        $appuntamento = $this->em->getRepository(Colloquio::class)->
          nuovoAppuntamento($dati['validi'][$colloquioId]['ricevimento']);
        $richiesta = (new RichiestaColloquio)
          ->setColloquio($dati['validi'][$colloquioId]['ricevimento'])
          ->setAppuntamento($appuntamento)
          ->setAlunno($alunno)
          ->setStato('R')
          ->setGenitore($this->getUser());
        $this->em->persist($richiesta);
        // ok: memorizzazione e log
        $dblogger->logAzione('COLLOQUI', 'Nuova prenotazione');
        // redirezione
        return $this->redirectToRoute('colloqui_genitori');
      }
    }
    // pagina di risposta
    return $this->renderHtml('colloqui', 'prenota', $dati, $info, [$form->createView(),
      'message.colloqui_prenota']);
  }

  /**
   * Visualizza le ore dei colloqui individuali dei docenti
   *
   * @param Request $request Pagina richiesta
   * @param int $pagina Numero di pagina per la lista dei alunni
   *
   * @return Response Pagina di risposta
   */
  #[Route(path: '/colloqui/cerca/{pagina}', name: 'colloqui_cerca', requirements: ['pagina' => '\d+'], defaults: ['pagina' => 0], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_STAFF')]
  public function cerca(Request $request, int $pagina): Response {
    // inizializza
    $info = [];
    $dati = [];
    // criteri di ricerca
    $criteri = [];
    $docente = $this->em->getRepository(Docente::class)->find(
      (int) $this->reqstack->getSession()->get('/APP/ROUTE/colloqui_cerca/docente', 0));
    $criteri['docente'] = $docente ? $docente->getId() : 0;
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $this->reqstack->getSession()->get('/APP/ROUTE/colloqui_cerca/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/colloqui_cerca/pagina', $pagina);
    }
    // form di ricerca
    $opzioniDocenti = $this->em->getRepository(Docente::class)->opzioni();
    $form = $this->createForm(FiltroType::class, null, ['form_mode' => 'colloqui',
      'values' => [$docente, $opzioniDocenti]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $criteri['docente'] = ($form->get('docente')->getData() ? $form->get('docente')->getData()->getId() : 0);
      $pagina = 1;
      // memorizza in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/colloqui_cerca/docente', $criteri['docente']);
      $this->reqstack->getSession()->set('/APP/ROUTE/colloqui_cerca/pagina', $pagina);
    }
    // recupera dati
    $dati = $this->em->getRepository(Colloquio::class)->cerca($criteri, $pagina);
    // informazioni di visualizzazione
    $info['pagina'] = $pagina;
    // pagina di risposta
    return $this->renderHtml('colloqui', 'cerca', $dati, $info, [$form->createView()]);
  }

  /**
   * Cancella i ricevimenti del docente
   *
   * @param LogHandler $dblogger Gestore dei log su database
   * @param string $tipo Tipo di cancellazione [D=disabilitati, T=tutti]
   *
   * @return Response Pagina di risposta
   */
  #[Route(path: '/colloqui/delete/{tipo}', name: 'colloqui_delete', requirements: ['tipo' => 'D|T'], methods: ['GET'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function delete(LogHandler $dblogger, string $tipo): Response {
    // legge ricevimenti
    $inizio = DateTime::createFromFormat('Y-m-d H:i:s',
      $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_inizio').' 00:00:00');
    $ricevimenti = $this->em->getRepository(Colloquio::class)->cancellabili($this->getUser(),
      ($tipo == 'D' ? false : null));
    // controlla richieste e li elimina
    foreach ($ricevimenti as $ricevimento) {
      // cancella colloquio
      $this->em->remove($ricevimento);
      // memorizzazione e log
      $dblogger->logAzione('COLLOQUI', 'Cancella ricevimento');
    }
    // redirezione
    return $this->redirectToRoute('colloqui_gestione');
  }

}
