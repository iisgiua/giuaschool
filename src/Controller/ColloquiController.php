<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Controller;

use App\Entity\Colloquio;
use App\Entity\RichiestaColloquio;
use App\Form\ColloquioType;
use App\Form\FiltroType;
use App\Form\PrenotazioneType;
use App\Form\RichiestaColloquioType;
use App\Util\ColloquiUtil;
use App\Util\LogHandler;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
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
   *
   * @Route("/colloqui/richieste", name="colloqui_richieste",
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function richiesteAction(): Response {
    // inizializza
    $info = [];
    $dati = [];
    // controllo fine colloqui
    $oggi = new \DateTime('today');
    $fine = \DateTime::createFromFormat('Y-m-d H:i:s',
      $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_fine').' 00:00:00');
    $fine->modify('-30 days');
    if ($oggi > $fine) {
      // visualizza errore
      $info['errore'] = 'exception.colloqui_sospesi';
    } else {
      // richieste valide
      $dati = $this->em->getRepository('App\Entity\Colloquio')->richiesteValide($this->getUser());
    }
    // pagina di risposta
    return $this->renderHtml('colloqui', 'richieste', $dati, $info);
  }

  /**
   * Visualizza le vecchie richieste di colloquio ricevute dal docente
   *
   * @return Response Pagina di risposta
   *
   * @Route("/colloqui/storico", name="colloqui_storico",
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function storicoAction(): Response {
    // inizializza
    $info = [];
    $dati = [];
    // storico richieste
    $dati['storico'] = $this->em->getRepository('App\Entity\RichiestaColloquio')->storico($this->getUser());
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
   *
   * @Route("/colloqui/conferma/{id}", name="colloqui_conferma",
   *    requirements={"id": "\d+"},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function confermaAction(Request $request, LogHandler $dblogger, int $id): Response {
    // inizializza
    $info = [];
    $dati = [];
    // controlla richiesta
    $richiesta = $this->em->getRepository('App\Entity\RichiestaColloquio')->find($id);
    if (!$richiesta || $richiesta->getColloquio()->getDocente()->getId() != $this->getUser()->getId() ||
        !$richiesta->getColloquio()->getAbilitato() || $richiesta->getStato() != 'R') {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // salva vecchia richiesta
    $vecchiaRichiesta = clone $richiesta;
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
      $dblogger->logModifica('COLLOQUI', 'Conferma richiesta', $vecchiaRichiesta, $richiesta);
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
   *
   * @Route("/colloqui/rifiuta/{id}", name="colloqui_rifiuta",
   *    requirements={"id": "\d+"},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function rifiutaAction(Request $request, LogHandler $dblogger, TranslatorInterface $trans,
                                int $id): Response {
    // inizializza
    $info = [];
    $dati = [];
    // controlla richiesta
    $richiesta = $this->em->getRepository('App\Entity\RichiestaColloquio')->find($id);
    if (!$richiesta || $richiesta->getColloquio()->getDocente()->getId() != $this->getUser()->getId() ||
        !$richiesta->getColloquio()->getAbilitato() || $richiesta->getStato() != 'R') {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // salva vecchia richiesta
    $vecchiaRichiesta = clone $richiesta;
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
        $dblogger->logModifica('COLLOQUI', 'Rifiuta richiesta', $vecchiaRichiesta, $richiesta);
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
   *
   * @Route("/colloqui/modifica/{id}", name="colloqui_modifica",
   *    requirements={"id": "\d+"},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function modificaAction(Request $request, LogHandler $dblogger, TranslatorInterface $trans,
                                 int $id): Response {
    // inizializza
    $info = [];
    $dati = [];
    // controlla richiesta
    $richiesta = $this->em->getRepository('App\Entity\RichiestaColloquio')->find($id);
    if (!$richiesta || $richiesta->getColloquio()->getDocente()->getId() != $this->getUser()->getId() ||
        !$richiesta->getColloquio()->getAbilitato() || !in_array($richiesta->getStato(), ['C', 'N'], true)) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // salva vecchia richiesta
    $vecchiaRichiesta = clone $richiesta;
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
        $dblogger->logModifica('COLLOQUI', 'Modifica richiesta', $vecchiaRichiesta, $richiesta);
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
   * @param Request $request Pagina richiesta
   *
   * @return Response Pagina di risposta
   *
   * @Route("/colloqui/gestione/", name="colloqui_gestione",
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function gestioneAction(): Response {
    // inizializza
    $info = [];
    $dati = [];
    $inizio = \DateTime::createFromFormat('Y-m-d H:i:s',
      $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_inizio').' 00:00:00');
    // legge dati
    $dati = $this->em->getRepository('App\Entity\Colloquio')->ricevimenti($this->getUser(), $inizio);
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
   *
   * @Route("/colloqui/edit/{id}", name="colloqui_edit",
   *    requirements={"id": "\d+"},
   *    defaults={"id": "0"},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function editAction(Request $request, ColloquiUtil $col, TranslatorInterface $trans,
                             LogHandler $dblogger, int $id): Response {
    // inizializza
    $info = [];
    $dati = [];
    // controlla azione
    if ($id > 0) {
      // azione edit
      $oggi = new \DateTime('today');
      $colloquio = $this->em->getRepository('App\Entity\Colloquio')->findOneBy(['id' => $id,
        'docente' => $this->getUser()]);
      if (!$colloquio || $colloquio->getData() < $oggi) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $vecchioColloquio = clone $colloquio;
    } else {
      // azione add
      $colloquio = (new Colloquio())
        ->setDocente($this->getUser())
        ->setData(new \DateTime('today'))
        ->setInizio(new \DateTime('08:30'))
        ->setFine(new \DateTime('09:30'));
      $this->em->persist($colloquio);
    }
    // informazioni per la visualizzazione
    $inizio = \DateTime::createFromFormat('Y-m-d',
      $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_inizio'));
    $oggi = new \DateTime('today');
    $info['inizio'] = $inizio > $oggi ? $inizio->format('d/m/Y') : $oggi->format('d/m/Y');
    $fine = \DateTime::createFromFormat('Y-m-d H:i:s',
      $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_fine').' 00:00:00');
    $info['fine'] = $fine->modify('-30 days')->format('d/m/Y');
    $info['festivi'] = $this->em->getRepository('App\Entity\Festivita')->listaFestivi();
    // lista sedi
    $listaSedi = $this->em->getRepository('App\Entity\Docente')->sedi($this->getUser());
    // form di inserimento
    $form = $this->createForm(ColloquioType::class, $colloquio, ['form_mode' => 'singolo',
      'values' => [$listaSedi]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // controlla data
      $data = $form->get('data')->getData();
      if ($this->em->getRepository('App\Entity\Festivita')->giornoFestivo($data) || $data < $oggi ||
          $data > $fine) {
        // errore: data non valida
        $form->addError(new FormError($trans->trans('exception.colloquio_data_invalida')));
      }
      // controlla se esite già
      if ($this->em->getRepository('App\Entity\Colloquio')->sovrapposizione($this->getUser(), $data,
          $form->get('inizio')->getData(), $form->get('fine')->getData(), $id)) {
        // errore: sovrapposizione
        $form->addError(new FormError($trans->trans('exception.colloquio_duplicato')));
      }
      // controlla link
      if ($colloquio->getTipo() == 'D') {
        $link = $colloquio->getLuogo();
        if (substr($link, -16) == 'meet.google.com/' || substr($link, -15) == 'meet.google.com') {
          // errore: link non valido
          $form->addError(new FormError($trans->trans('exception.colloquio_link_invalido')));
        }
        if (substr($link, 0, 8) != 'https://' && substr($link, 0, 7) != 'http://') {
          $colloquio->setLuogo('https://'.$link);
        }
      }
      if ($form->isValid()) {
        // clcola numero colloqui
        $colloquio->setNumero($col->numeroColloqui($colloquio));
        // ok: memorizzazione e log
        if ($id) {
          $dblogger->logModifica('COLLOQUI', 'Modifica ricevimento', $vecchioColloquio, $colloquio);
        } else {
          $dblogger->logCreazione('COLLOQUI', 'Aggiunge ricevimento', $colloquio);
        }
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
   *
   * @Route("/colloqui/enable/{id}/{stato}", name="colloqui_enable",
   *    requirements={"id": "\d+", "stato": "0|1"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function enableAction(LogHandler $dblogger, int $id, int $stato): Response {
    // controlla colloquio
    $oggi = new \DateTime('today');
    $colloquio = $this->em->getRepository('App\Entity\Colloquio')->findOneBy(['id' => $id,
      'docente' => $this->getUser()]);
    if (!$colloquio || $colloquio->getData() < $oggi) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla se presenti richieste
    if ($this->em->getRepository('App\Entity\Colloquio')->numeroRichieste($colloquio) > 0) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // copia per log
    $vecchioColloquio = clone $colloquio;
    // abilita/disabilita colloquio
    $colloquio->setAbilitato($stato);
    // memorizzazione e log
    $dblogger->logModifica('COLLOQUI', 'Abilita/Disabilita ricevimento', $vecchioColloquio, $colloquio);
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
   *
   * @Route("/colloqui/create", name="colloqui_create",
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function createAction(Request $request, ColloquiUtil $col, LogHandler $dblogger,
                               TranslatorInterface $trans): Response {
    // inizializza
    $info = [];
    $dati = [];
    // imposta colloquio fittizio
    $colloquio = (new Colloquio())
      ->setDocente($this->getUser())
      ->setData(new \DateTime('today'))
      ->setInizio(new \DateTime('08:30'))
      ->setFine(new \DateTime('09:30'))
      ->setDurata(10);
    // lista sedi
    $listaSedi = $this->em->getRepository('App\Entity\Docente')->sedi($this->getUser());
    if (isset($listaSedi[''])) {
      // elimina opzione vuota
      unset($listaSedi['']);
    }
    // informazioni per la visualizzazione
    foreach ($listaSedi as $idSede) {
      $info['orario'][$idSede] = $this->em->getRepository('App\Entity\ScansioneOraria')->orarioSede($idSede);
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
        if (substr($luogo, -16) == 'meet.google.com/' || substr($luogo, -15) == 'meet.google.com') {
          // errore: link non valido
          $form->addError(new FormError($trans->trans('exception.colloquio_link_invalido')));
        }
        if (substr($luogo, 0, 8) != 'https://' && substr($luogo, 0, 7) != 'http://') {
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
   *
   * @Route("/colloqui/genitori", name="colloqui_genitori",
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_GENITORE")
   */
  public function genitoriAction(ColloquiUtil $col, TranslatorInterface $trans): Response {
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
   *
   * @return Response Pagina di risposta
   *
   * @Route("/colloqui/disdetta/{id}", name="colloqui_disdetta")
   *    requirements={"id": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_GENITORE")
   */
  public function disdettaAction(Request $request, LogHandler $dblogger, int $id): Response {
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
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // controlla richiesta
    $richiesta = $this->em->getRepository('App\Entity\RichiestaColloquio')->findOneBy(['id' => $id,
      'alunno' => $alunno, 'stato' => ['R', 'C']]);
    if (!$richiesta) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // annulla richiesta
    $vecchiaRichiesta = clone $richiesta;
    $richiesta
      ->setStato('A')
      ->setMessaggio('')
      ->setGenitoreAnnulla($this->getUser());
    // ok: memorizzazione e log
    $dblogger->logModifica('COLLOQUI', 'Disdetta prenotazione', $vecchiaRichiesta, $richiesta);
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
   *
   * @Route("/colloqui/prenota/{docente}", name="colloqui_prenota")
   *    requirements={"docente": "\d+"},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_GENITORE")
   */
  public function prenotaAction(Request $request, ColloquiUtil $col, TranslatorInterface $trans,
                                LogHandler $dblogger, int $docente): Response {
    // inizializza
    $info = [];
    $dati = [];
    // controlla docente
    $docente = $this->em->getRepository('App\Entity\Docente')->findOneBy(['id' => $docente, 'abilitato' => 1]);
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
    $cattedre = $this->em->getRepository('App\Entity\Cattedra')->cattedreDocente($docente, 'Q');
    $info['materie'] = [];
    foreach ($cattedre as $cattedra) {
      if ($cattedra->getClasse() == $classe) {
        $info['materie'][] = $cattedra->getMateria()->getNome();
      }
    }
    // form di inserimento
    $form = $this->createForm(PrenotazioneType::class, null, ['form_mode' => 'prenotazione',
      'values' => [$dati['lista']]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      $colloquioId = $form->get('data')->getData();
      // controlla duplicati
      $prenotazione = $this->em->getRepository('App\Entity\RichiestaColloquio')->findOneBy([
        'colloquio' => $colloquioId, 'alunno' => $alunno, 'stato' => ['R', 'C']]);
      if (!empty($prenotazione)) {
        // esiste già richiesta
        $form->addError(new FormError($trans->trans('exception.colloqui_esiste')));
      } else {
        // nuova richiesta
        $appuntamento = $this->em->getRepository('App\Entity\Colloquio')->
          nuovoAppuntamento($dati['validi'][$colloquioId]['ricevimento']);
        $richiesta = (new RichiestaColloquio)
          ->setColloquio($dati['validi'][$colloquioId]['ricevimento'])
          ->setAppuntamento($appuntamento)
          ->setAlunno($alunno)
          ->setStato('R')
          ->setGenitore($this->getUser());
        $this->em->persist($richiesta);
        // ok: memorizzazione e log
        $dblogger->logCreazione('COLLOQUI', 'Nuova prenotazione', $richiesta);
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
   *
   * @Route("/colloqui/cerca/{pagina}", name="colloqui_cerca",
   *    requirements={"pagina": "\d+"},
   *    defaults={"pagina": 0},
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_STAFF")
   */
  public function cercaAction(Request $request, int $pagina): Response {
    // inizializza
    $info = [];
    $dati = [];
    // criteri di ricerca
    $criteri = array();
    $docente = $this->em->getRepository('App\Entity\Docente')->find(
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
    $opzioniDocenti = $this->em->getRepository('App\Entity\Docente')->opzioni();
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
    $dati = $this->em->getRepository('App\Entity\Colloquio')->cerca($criteri, $pagina);
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
   *
   * @Route("/colloqui/delete/{tipo}", name="colloqui_delete",
   *    requirements={"tipo": "D|T"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function deleteAction(LogHandler $dblogger, string $tipo): Response {
    // legge ricevimenti
    $inizio = \DateTime::createFromFormat('Y-m-d H:i:s',
      $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_inizio').' 00:00:00');
    $ricevimenti = $this->em->getRepository('App\Entity\Colloquio')->cancellabili($this->getUser(),
      ($tipo == 'D' ? false : null));
    // controlla richieste e li elimina
    foreach ($ricevimenti as $ricevimento) {
      // copia per log
      $vecchioColloquio = clone $ricevimento;
      // cancella colloquio
      $this->em->remove($ricevimento);
      // memorizzazione e log
      $dblogger->logRimozione('COLLOQUI', 'Cancella ricevimento', $vecchioColloquio);
    }
    // redirezione
    return $this->redirectToRoute('colloqui_gestione');
  }

}
