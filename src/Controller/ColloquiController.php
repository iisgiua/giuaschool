<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Controller;

use Throwable;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use DateTime;
use Doctrine\DBAL\LockMode;
use App\Entity\Festivita;
use App\Entity\Docente;
use App\Entity\ScansioneOraria;
use App\Entity\Cattedra;
use App\Entity\Colloquio;
use App\Entity\RichiestaColloquio;
use App\Entity\Sede;
use App\Form\ColloquioType;
use App\Form\FiltroType;
use App\Form\PrenotazioneType;
use App\Form\RichiestaColloquioType;
use App\Util\ColloquiUtil;
use App\Util\LogHandler;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
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
    $annoInizio = $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_inizio');
    $inizio = DateTime::createFromFormat('Y-m-d H:i:s', $annoInizio.' 00:00:00');
    if (!$inizio) {
      // dato di configurazione invalido: nessun ricevimento da mostrare
      return $this->renderHtml('colloqui', 'gestione', $dati, $info);
    }
    // legge dati
    $dati = $this->em->getRepository(Colloquio::class)->ricevimenti($this->getUser(), null, $inizio);
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
    $oggi = new DateTime('today');
    // informazioni per la visualizzazione
    $annoInizio = $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_inizio');
    $inizio = DateTime::createFromFormat('Y-m-d', $annoInizio);
    if (!$inizio) {
      $info['inizio'] = $oggi->format('d/m/Y');
    } else {
      $info['inizio'] = $inizio > $oggi ? $inizio->format('d/m/Y') : $oggi->format('d/m/Y');
    }
    $fineColloqui = $this->reqstack->getSession()->get('/CONFIG/SCUOLA/fine_colloqui');
    $fine = DateTime::createFromFormat('Y-m-d H:i:s', $fineColloqui.' 00:00:00');
    if (!$fine) {
      $info['fine'] = '-';
    } else {
      $info['fine'] = $fine->format('d/m/Y');
    }
    $info['festivi'] = $this->em->getRepository(Festivita::class)->listaFestivi();
    // lista sedi
    $listaSedi = $this->em->getRepository(Sede::class)->sedi($this->getUser());
    // controlla azione
    if ($id > 0) {
      // azione edit
      $colloquio = $this->em->getRepository(Colloquio::class)->findOneBy(['id' => $id,
        'docente' => $this->getUser()]);
      if (!$colloquio || $colloquio->getData() < $oggi) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
    } else {
      // azione add
      $data = $oggi;
      if ($this->em->getRepository(Festivita::class)->giornoFestivo($data)) {
        // se festivo sposta al giorno successivo
        $data = $this->em->getRepository(Festivita::class)->giornoSuccessivo($data);
      }
      $sede = array_values($listaSedi)[0];
      $oraInizio = DateTime::createFromFormat('H:i',
        $this->em->getRepository(ScansioneOraria::class)->inizioLezioni($data, $sede));
      $oraFine = (clone $oraInizio)->modify('+1 hour');
      // impostazione di default
      $colloquio = (new Colloquio())
        ->setDocente($this->getUser())
        ->setSede($sede)
        ->setData($data)
        ->setInizio($oraInizio)
        ->setFine($oraFine)
        ->setDurata(10);
      $this->em->persist($colloquio);
    }
    // form di inserimento
    $form = $this->createForm(ColloquioType::class, $colloquio, ['form_mode' => 'singolo',
      'values' => [$listaSedi]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // controlla data
      if ($this->em->getRepository(Festivita::class)->giornoFestivo($colloquio->getData()) ||
          $colloquio->getData() < $oggi || $colloquio->getData() > $fine) {
        // errore: data non valida
        $form->addError(new FormError($trans->trans('exception.colloquio_data_invalida')));
      }
      // controlla orari e link
      $this->controllaOrariLink($form, $trans, $colloquio, $listaSedi);
      // controlla se esite già
      if ($this->em->getRepository(Colloquio::class)->sovrapposizione($this->getUser(), $colloquio->getData(),
          $colloquio->getInizio(), $colloquio->getFine(), $id)) {
        // errore: sovrapposizione
        $form->addError(new FormError($trans->trans('exception.colloquio_duplicato')));
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
   * Controlla la congruenza degli orari del colloquio e, se previsto, il link alla riunione
   *
   * @param FormInterface $form Form di inserimento del colloquio
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param Colloquio $colloquio Colloquio da controllare
   * @param array $listaSedi Lista delle sedi del docente
   */
  private function controllaOrariLink(FormInterface $form, TranslatorInterface $trans, Colloquio $colloquio,
                                      array $listaSedi): void {
    if ($colloquio->getInizio() >= $colloquio->getFine()) {
      // errore: orario inizio non valido
      $form->addError(new FormError($trans->trans('exception.colloquio_ora_incongruente')));
    }
    $orari = $this->em->getRepository(ScansioneOraria::class)->inizioFineLezioni($colloquio->getData(),
        array_map(fn($s) => $s->getId(), array_values($listaSedi)));
    if ($colloquio->getInizio()->format('H:i:00') < $orari['inizio']) {
      // errore: orario inizio non valido
      $form->addError(new FormError($trans->trans('exception.colloquio_ora_inizio_invalida')));
    }
    if ($colloquio->getFine()->format('H:i:00') > $orari['fine']) {
      // errore: orario fine non valido
      $form->addError(new FormError($trans->trans('exception.colloquio_ora_fine_invalida')));
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
  }

  /**
   * Abilita/dsabilita un ricevimento del docente.
   *
   * @param Request $request Pagina richiesta
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $id Ricevimento da cancellare
   * @param int $stato Nuovo stato di abilitazione del ricevimento
   *
   * @return Response Pagina di risposta
   */
  #[Route(path: '/colloqui/enable/{id}/{stato}', name: 'colloqui_enable', requirements: ['id' => '\d+', 'stato' => '0|1'], methods: ['POST'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function enable(Request $request, LogHandler $dblogger, int $id, int $stato): Response {
    // valida token CSRF
    if (!$this->isCsrfTokenValid('delete', $request->request->get('_csrf_token'))) {
      throw $this->createNotFoundException('exception.invalid_token');
    }
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
    $oggi = new DateTime('today');
    // lista sedi
    $listaSedi = $this->em->getRepository(Sede::class)->sedi($this->getUser());
    // imposta colloquio fittizio
    $data = $oggi;
    if ($this->em->getRepository(Festivita::class)->giornoFestivo($data)) {
      // se festivo sposta al giorno successivo
      $data = $this->em->getRepository(Festivita::class)->giornoSuccessivo($data);
    }
    $sede = array_values($listaSedi)[0];
    $oraInizio = DateTime::createFromFormat('H:i',
      $this->em->getRepository(ScansioneOraria::class)->inizioLezioni($data, $sede));
    $oraFine = (clone $oraInizio)->modify('+1 hour');
    $colloquio = (new Colloquio())
      ->setDocente($this->getUser())
      ->setSede($sede)
      ->setData($data)
      ->setInizio($oraInizio)
      ->setFine($oraFine)
      ->setDurata(10);
    // form di inserimento
    $form = $this->createForm(ColloquioType::class, $colloquio, ['form_mode' => 'periodico',
      'values' => [$listaSedi]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // legge dati
      $frequenza = $form->get('frequenza')->getData();
      $giorno = $form->get('giorno')->getData();
      // controlla orari e link
      $this->controllaOrariLink($form, $trans, $colloquio, $listaSedi);
      if ($form->isValid()) {
        // genera date
        $avviso = $col->generaDate($this->getUser(), $colloquio->getTipo(), $frequenza, $colloquio->getDurata(),
          $giorno, $colloquio->getInizio(), $colloquio->getFine(), $colloquio->getLuogo(), $colloquio->getSede());
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
   * @param Request $request Pagina richiesta
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $id Richiesta di colloquio da annullare
   *
   * @return Response Pagina di risposta
   */
  #[Route(path: '/colloqui/disdetta/{id}', name: 'colloqui_disdetta', requirements: ['id' => '\d+'], methods: ['POST'])]
  #[IsGranted('ROLE_GENITORE')]
  public function disdetta(Request $request, LogHandler $dblogger, int $id): Response {
    // valida token CSRF
    if (!$this->isCsrfTokenValid('delete', $request->request->get('_csrf_token'))) {
      throw $this->createNotFoundException('exception.invalid_token');
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
  #[Route(path: '/colloqui/prenota/{docente}', name: 'colloqui_prenota', requirements: ['docente' => '\d+'])]
  #[IsGranted('ROLE_GENITORE')]
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
    $dati = $col->dateRicevimento($docente, $classe->getSede());
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
      $colloquioId = (int) $form->get('data')->getData();
      // esegue la prenotazione in transazione, con lock in scrittura sul ricevimento: cosi'
      // due richieste concorrenti non possono occupare lo stesso posto o superare la capienza
      $conn = $this->em->getConnection();
      $conn->beginTransaction();
      try {
        // rilegge il ricevimento con lock in scrittura (serializza le prenotazioni concorrenti)
        $colloquio = $this->em->getRepository(Colloquio::class)->createQueryBuilder('c')
          ->where('c.id = :colloquio')
          ->setParameter('colloquio', $colloquioId)
          ->getQuery()
          ->setLockMode(LockMode::PESSIMISTIC_WRITE)
          ->getOneOrNullResult();
        // controlla esistenza e stato del ricevimento
        if (!$colloquio || !$colloquio->getAbilitato()) {
          throw $this->createNotFoundException('exception.id_notfound');
        }
        // ricontrolla i duplicati dopo il lock
        $prenotazione = $this->em->getRepository(RichiestaColloquio::class)->findOneBy([
          'colloquio' => $colloquio, 'alunno' => $alunno, 'stato' => ['R', 'C']]);
        if (!empty($prenotazione)) {
          // esiste già richiesta
          $conn->rollBack();
          $form->addError(new FormError($trans->trans('exception.colloqui_esiste')));
        } elseif ($this->em->getRepository(Colloquio::class)->numeroRichieste($colloquio) >= $colloquio->getNumero()) {
          // tutti i posti del ricevimento sono occupati
          $conn->rollBack();
          $form->addError(new FormError($trans->trans('exception.colloqui_esauriti')));
        } else {
          // calcola l'appuntamento e memorizza la richiesta
          $appuntamento = $this->em->getRepository(Colloquio::class)->nuovoAppuntamento($colloquio);
          $richiesta = (new RichiestaColloquio)
            ->setColloquio($colloquio)
            ->setAppuntamento($appuntamento)
            ->setAlunno($alunno)
            ->setStato('R')
            ->setGenitore($this->getUser());
          $this->em->persist($richiesta);
          // ok: memorizzazione e log
          $dblogger->logAzione('COLLOQUI', 'Nuova prenotazione');
          // conferma transazione
          $conn->commit();
          // redirezione
          return $this->redirectToRoute('colloqui_genitori');
        }
      } catch (Throwable $e) {
        // annulla la transazione in caso di errore
        if ($conn->isTransactionActive()) {
          $conn->rollBack();
        }
        throw $e;
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
  #[Route(path: '/colloqui/delete/{tipo}', name: 'colloqui_delete', requirements: ['tipo' => 'D|T'], methods: ['POST'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function delete(Request $request, LogHandler $dblogger, string $tipo): Response {
    // valida token CSRF
    if (!$this->isCsrfTokenValid('delete', $request->request->get('_csrf_token'))) {
      throw $this->createNotFoundException('exception.invalid_token');
    }
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
