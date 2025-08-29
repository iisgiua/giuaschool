<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Controller;

use App\Entity\Alunno;
use App\Entity\Assenza;
use App\Entity\Classe;
use App\Entity\DefinizioneRichiesta;
use App\Entity\Genitore;
use App\Entity\Presenza;
use App\Entity\Richiesta;
use App\Entity\Sede;
use App\Entity\Uscita;
use App\Form\FiltroType;
use App\Form\RichiestaType;
use App\Form\UscitaType;
use App\Util\LogHandler;
use App\Util\RegistroUtil;
use App\Util\RichiesteUtil;
use DateTime;
use IntlDateFormatter;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;


/**
 * RichiesteController - gestione delle richieste
 *
 * @author Antonello Dessì
 */
class RichiesteController extends BaseController {

  /**
   * Lista dei mpoduli di richiesta utilizzabili dall'utente
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/richieste/lista', name: 'richieste_lista', methods: ['GET'])]
  #[IsGranted(attribute: new Expression("is_granted('ROLE_GENITORE') or is_granted('ROLE_ALUNNO')"))]
  public function lista(): Response {
    // inizializza
    $info = [];
    // recupera dati
    $dati = $this->em->getRepository(DefinizioneRichiesta::class)->lista($this->getUser());
    // pagina di risposta
    return $this->renderHtml('richieste', 'lista', $dati, $info);
  }

  /**
   * Crea una nuova richiesta
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param RichiesteUtil $ric Funzioni di utilità per la gestione dei moduli di richiesta
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $modulo Identificativo del modulo di richiesta
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/richieste/add/{modulo}', name: 'richieste_add', requirements: ['modulo' => '\d+'], methods: ['GET', 'POST'])]
  #[IsGranted(attribute: new Expression("is_granted('ROLE_GENITORE') or is_granted('ROLE_ALUNNO')"))]
  public function add(Request $request, TranslatorInterface $trans,
                      RichiesteUtil $ric, LogHandler $dblogger, int $modulo): Response {
    // inizializza
    $info = [];
    $dati = [];
    $varSessione = '/APP/FILE/richieste_add/files';
    if ($request->isMethod('GET')) {
      // inizializza sessione per allegati
      $this->reqstack->getSession()->set($varSessione, []);
    }
    $utente = ($this->getUser() instanceOf Genitore) ? $this->getUser()->getAlunno() : $this->getUser();
    // controlla modulo richiesta
    $definizioneRichiesta = $this->em->getRepository(DefinizioneRichiesta::class)->findOneBy([
      'id' => $modulo, 'abilitata' => 1]);
    if (!$definizioneRichiesta) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla sede
    $sedi = $utente->getCodiceRuolo() == 'A' ? [$utente->getClasse()->getSede()->getId()] :
      ($utente->getCodiceRuolo() == 'G' ? [$utente->getAlunno()->getClasse()->getSede()->getId()] : []);
    if ($definizioneRichiesta->getSede() &&
        !in_array($definizioneRichiesta->getSede()->getId(), $sedi, true)) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla accesso a modulo richiesta
    if (!$this->getUser()->controllaRuoloFunzione($definizioneRichiesta->getRichiedenti())) {
      // errore: azione non permessa
      throw $this->createNotFoundException('exception.not_allowed');
    }
    if ($definizioneRichiesta->getUnica()) {
      // controlla se esiste già una richiesta
      $altraRichiesta = $this->em->getRepository(Richiesta::class)->findOneBy([
        'definizioneRichiesta' => $modulo, 'utente' => $utente, 'stato' => ['I', 'G']]);
      if ($altraRichiesta) {
        // errore: esiste già altra richiesta
        throw $this->createNotFoundException('exception.not_allowed');
      }
    }
    // crea richiesta
    $richiesta = (new Richiesta())
      ->setDefinizioneRichiesta($definizioneRichiesta)
      ->setUtente($utente)
      ->setClasse($utente->getClasse());
    $this->em->persist($richiesta);
    // informazioni per la visualizzazione
    $info['modulo'] = '@data/moduli/'.$definizioneRichiesta->getModulo();
    $info['allegati'] = $definizioneRichiesta->getAllegati();
    $info['gestione'] = $definizioneRichiesta->getGestione();
    // form di inserimento
    $form = $this->createForm(RichiestaType::class, null, ['form_mode' => 'add',
      'values' => [$definizioneRichiesta->getCampi(), $definizioneRichiesta->getUnica()]]);
    $form->handleRequest($request);
    if ($form->isSubmitted()) {
      $invio = new DateTime();
      $valori = [];
      // controllo errori
      foreach ($definizioneRichiesta->getCampi() as $nome => $campo) {
        if ($form->get($nome)->getData() === null && $campo[1]) {
          // campo obbligatorio vuoto
          $form->addError(new FormError($trans->trans('exception.campo_obbligatorio_vuoto')));
        } else {
          // memorizza valore
          $valori[$nome] = $form->get($nome)->getData();
        }
      }
      if (!$definizioneRichiesta->getUnica()) {
        // controllo data
        if ($form->get('data')->getData() === null) {
          // campo data vuoto
          $form->addError(new FormError($trans->trans('exception.campo_data_vuoto')));
        } else {
          // controlla se richiesta esiste già per la data
          $altra = $this->em->getRepository(Richiesta::class)->findOneBy([
            'definizioneRichiesta' => $modulo, 'utente' => $utente, 'stato' => ['I', 'G'],
            'data' => $form->get('data')->getData()]);
          if ($altra) {
            // richiesta già presente
            $form->addError(new FormError($trans->trans('exception.richiesta_esistente')));
          }
          if ($definizioneRichiesta->getGestione()) {
            // controlla scadenza
            $oraScadenza = $this->reqstack->getSession()->get('/CONFIG/SCUOLA/scadenza_invio_richiesta');
            $scadenza = clone ($form->get('data')->getData());
            $scadenza->modify('-1 day +'.substr((string) $oraScadenza, 0, 2).' hour +'.substr((string) $oraScadenza, 3, 2).' minute');
            if ($invio > $scadenza) {
              // richiesta inviata oltre i termini
              $form->addError(new FormError($trans->trans('exception.richiesta_ora_invio', [
                'ora' => $oraScadenza])));
            }
          }
        }
      }
      // controlla allegati
      $allegatiTemp = $this->reqstack->getSession()->get($varSessione, []);
      if (count($allegatiTemp) < $info['allegati']) {
        $form->addError(new FormError($trans->trans('exception.modulo_allegati_mancanti')));
        $this->reqstack->getSession()->remove($varSessione);
      }
      if ($form->isValid()) {
        // data richiesta
        $data = $definizioneRichiesta->getUnica() ? null : $form->get('data')->getData();
        // crea documento PDF
        [$documento, $documentoId] = $ric->creaPdf($definizioneRichiesta, $utente,
          $utente->getClasse(), $valori, $data, $invio);
        // imposta eventuali allegati
        $allegati = $ric->impostaAllegati($utente, $utente->getClasse(), $documentoId, $allegatiTemp);
        $this->reqstack->getSession()->remove($varSessione);
        // ok: memorizzazione e log
        $richiesta
          ->setValori($valori)
          ->setDocumento($documento)
          ->setAllegati($allegati)
          ->setInviata($invio)
          ->setGestita(null)
          ->setData($data)
          ->setStato('I')
          ->setMessaggio('');
        $dblogger->logAzione('RICHIESTE', 'Invio richiesta');
        // redirezione
        return $this->redirectToRoute('richieste_lista');
      }
    }
    // pagina di risposta
    return $this->renderHtml('richieste', 'add', $dati, $info, [$form->createView(),
     $definizioneRichiesta->getGestione() ? 'message.richieste_add' : 'message.modulo_add']);
  }

  /**
   * Annulla una richiesta inviata. Azione eseguita dal richiedente.
   *
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $id Richiesta da annullare
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/richieste/delete/{id}', name: 'richieste_delete', requirements: ['id' => '\d+'], methods: ['GET'])]
  #[IsGranted(attribute: new Expression("is_granted('ROLE_GENITORE') or is_granted('ROLE_ALUNNO')"))]
  public function delete(LogHandler $dblogger, int $id): Response {
    // inizializza
    $utente = $this->getUser() instanceOf Genitore ? $this->getUser()->getAlunno() : $this->getUser();
    // controlla richiesta
    $richiesta = $this->em->getRepository(Richiesta::class)->findOneBy(['id' => $id,
      'utente' => $utente, 'stato' => ['I', 'G']]);
    if (!$richiesta) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla accesso a modulo richiesta
    if (!$this->getUser()->controllaRuoloFunzione($richiesta->getDefinizioneRichiesta()->getRichiedenti())) {
      // errore: azione non permessa
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // controlla se richiesta multipla già gestita
    if (!$richiesta->getDefinizioneRichiesta()->getUnica() && $richiesta->getStato() == 'G') {
      // errore: richiesta gestita
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // cambia stato
    $richiesta
      ->setInviata(new DateTime())
      ->setGestita(null)
      ->setStato('A');
    // memorizzazione e log
    $dblogger->logAzione('RICHIESTE', 'Annulla richiesta');
    // redirezione
    return $this->redirectToRoute('richieste_lista');
  }

  /**
   * Scarica il documento del modulo di richiesta o uno degli allegati
   *
   * @param int $id Identificativo della richiesta
   * @param int $documento Indica il documento da scaricare: 0=modulo di richiesta, 1...=allegato indicato
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/richieste/download/{id}/{documento}', name: 'richieste_download', requirements: ['id' => '\d+', 'documento' => '\d+'], defaults: ['documento' => '0'], methods: ['GET'])]
  #[IsGranted('ROLE_UTENTE')]
  public function download(int $id, int $documento): Response {
    // controlla richiesta
    $richiesta = $this->em->getRepository(Richiesta::class)->find($id);
    if (!$richiesta) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla accesso
    if ($this->getUser()->controllaRuoloFunzione($richiesta->getDefinizioneRichiesta()->getRichiedenti())) {
      // utente tra i richiedenti
      $utente = $this->getUser() instanceOf Genitore ? $this->getUser()->getAlunno() : $this->getUser();
      if (($richiesta->getUtente() != $utente && !$utente->controllaRuolo('DS')) ||
          !in_array($richiesta->getStato(), ['I', 'G'], true)) {
        // errore: richiesta non accessibile al richiedente
        throw $this->createNotFoundException('exception.not_allowed');
      }
    } elseif (!$this->getUser()->controllaRuoloFunzione($richiesta->getDefinizioneRichiesta()->getDestinatari())) {
      // errore: utente non ammesso
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // controlla allegati
    if ($documento > 0 && $documento > count($richiesta->getAllegati())) {
      // errore: numero allegati
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // invia file
    $percorso = $this->getParameter('kernel.project_dir').'/FILES/archivio/classi/'.
      $richiesta->getClasse()->getAnno().$richiesta->getClasse()->getSezione().
      $richiesta->getClasse()->getGruppo().'/documenti/';
    if ($documento == 0) {
      // modulo di richiesta
      $nomefile = $richiesta->getDocumento();
    } else {
      // allegato
      $nomefile = $richiesta->getAllegati()[$documento - 1];
    }
    // invia il file
    return $this->file($percorso.$nomefile, $nomefile, ResponseHeaderBag::DISPOSITION_ATTACHMENT);
  }

  /**
   * Convalida la richiesta di uscita anticipata
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param LogHandler $dblogger Gestore dei log su database
   * @param string $data Data del giorno (AAAA-MM-GG)
   * @param int $alunno Identificativo dell'alunno
   * @param int $richiesta Identificativo della richiesta di uscita anticipata da convalidare (se esiste)
   * @param int $posizione Posizione per lo scrolling verticale della finestra
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/richieste/uscita/{data}/{alunno}/{richiesta}/{posizione}', name: 'richieste_uscita', requirements: ['data' => '\d\d\d\d-\d\d-\d\d', 'alunno' => '\d+', 'richiesta' => '\d+', 'posizione' => '\d+'], defaults: ['posizione' => '0'], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function uscita(Request $request, TranslatorInterface $trans, RegistroUtil $reg,
                         LogHandler $dblogger, string $data, int $alunno, int $richiesta,
                         int $posizione): Response {
    // inizializza
    $info = [];
    $dati = [];
    // controlla alunno
    $alunno = $this->em->getRepository(Alunno::class)->findOneBy(['id' => $alunno]);
    if (!$alunno || !$alunno->getClasse()) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla data
    $data = DateTime::createFromFormat('Y-m-d', $data);
    $errore = $reg->controlloData($data, $alunno->getClasse()->getSede());
    if ($errore) {
      // errore: festivo
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // controlla richiesta
    $richiesta = $this->em->getRepository(Richiesta::class)->findOneBy(['id' => $richiesta,
      'utente' => $alunno, 'data' => $data]);
    if ($richiesta && (!in_array($richiesta->getStato(), ['I', 'G'], true) ||
        $richiesta->getDefinizioneRichiesta()->getUnica() ||
        $richiesta->getDefinizioneRichiesta()->getTipo() != 'U' ||
        !$richiesta->getDefinizioneRichiesta()->getGestione() ||
        !$richiesta->getDefinizioneRichiesta()->getAbilitata())) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // legge prima/ultima ora
    $orario = $reg->orarioInData($data, $alunno->getClasse()->getSede());
    // controlla uscita
    $uscita = $this->em->getRepository(Uscita::class)->findOneBy(['alunno' => $alunno,
      'data' => $data]);
    if ($uscita) {
      // edit
      $uscitaOld = clone $uscita;
      // elimina autorizzazione/giustificazione
      $uscita
        ->setDocente($this->getUser())
        ->setGiustificato(null)
        ->setDocenteGiustifica(null);
      $chiediGiustificazione = !$uscitaOld->getDocenteGiustifica();
    } else {
      // nuovo
      $nota = $trans->trans('message.autorizza_uscita_richiesta', ['sex' => ($alunno->getSesso() == 'M' ? 'o' : 'a'),
        'alunno' => $alunno->getCognome().' '.$alunno->getNome()]);
      // imposta ora
      if ($richiesta) {
        $ora = $richiesta->getValori()['ora'];
      } else {
        $ora = new DateTime();
        if ($data->format('Y-m-d') != date('Y-m-d') || $ora->format('H:i:00') < $orario[0]['inizio'] ||
            $ora->format('H:i:00') > $orario[count($orario) - 1]['fine']) {
          // data non odierna o ora attuale fuori da orario
          $ora = DateTime::createFromFormat('H:i:s', $orario[count($orario) - 1]['fine']);
        }
      }
      $uscita = (new Uscita())
        ->setData($data)
        ->setAlunno($alunno)
        ->setValido(false)
        ->setDocente($this->getUser())
        ->setOra($ora)
        ->setNote($nota);
      $this->em->persist($uscita);
      $chiediGiustificazione = false;
    }
    // controlla permessi
    if (!$reg->azioneAssenze($data, $this->getUser(), $alunno, $alunno->getClasse(), null)) {
      // errore: azione non permessa
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // info da visualizzare
    $formatter = new IntlDateFormatter('it_IT', IntlDateFormatter::SHORT, IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $info['data'] =  $formatter->format($data);
    $info['docente'] = $this->getUser()->getNome().' '.$this->getUser()->getCognome();
    $info['classe'] = ''.$alunno->getClasse();
    $info['alunno'] = $alunno->getCognome().' '.$alunno->getNome();
    $info['delete'] = isset($uscitaOld);
    $info['posizione'] = $posizione;
    $dati['richiesta'] = $richiesta;
    // form di inserimento
    $form = $this->createForm(UscitaType::class, $uscita, ['form_mode' => $richiesta ? 'richiesta' : 'staff',
      'values' => [$chiediGiustificazione]]);
    $form->handleRequest($request);
    if ($form->isSubmitted()) {
      $presenza = $this->em->getRepository(Presenza::class)->findOneBy(['alunno' => $alunno,
        'data' => $data]);
      $mode = isset($request->request->all()['uscita']['delete']) ? 'DELETE' : 'EDIT';
      if (!isset($uscitaOld) && $mode == 'DELETE') {
        // uscita non esiste, niente da fare
        return $this->redirectToRoute('lezioni_assenze_quadro', ['posizione' => $posizione]);
      } elseif ($form->get('ora')->getData()->format('H:i:00') < $orario[0]['inizio'] ||
                $form->get('ora')->getData()->format('H:i:00') >= $orario[count($orario) - 1]['fine']) {
        // ora fuori dai limiti
        $form->get('ora')->addError(new FormError($trans->trans('field.time', [], 'validators')));
      } elseif ($presenza && !$presenza->getOraFine()) {
        // errore coerenza fc con uscita
        $form->addError(new FormError($trans->trans('exception.presenze_giorno_uscita_incoerente')));
      } elseif ($presenza && $presenza->getOraFine() &&
                $presenza->getOraFine() > $form->get('ora')->getData()) {
        // errore coerenza fc con orario uscita
        $form->addError(new FormError($trans->trans('exception.presenze_uscita_incoerente')));
      } elseif ($form->isValid()) {
        if (isset($uscitaOld) && $mode == 'DELETE') {
          // cancella uscita esistente
          $uscitaId = $uscita->getId();
          $this->em->remove($uscita);
        } else {
          // controlla se risulta assente
          $assenza = $this->em->getRepository(Assenza::class)->findOneBy(['data' => $data,
            'alunno' => $alunno]);
          if ($assenza) {
            // cancella assenza
            $assenzaId = $assenza->getId();
            $this->em->remove($assenza);
          }
        }
        if ($richiesta) {
          // gestione autorizzazione
          $richiesta->setStato(isset($uscitaId) ? 'I' : 'G');
        }
        if ($richiesta || $form->get('giustificazione')->getData() === false) {
          // gestione autorizzazione
          $uscita
            ->setGiustificato(new DateTime('today'))
            ->setDocenteGiustifica($this->getUser());
        }
        // ok: memorizza dati
        $this->em->flush();
        // ricalcola ore assenze
        $reg->ricalcolaOreAlunno($data, $alunno);
        // log azione
        if (isset($uscitaOld) && $mode == 'DELETE') {
          // cancella
          $dblogger->logAzione('ASSENZE', 'Cancella uscita');
        } elseif (isset($uscitaOld)) {
          // modifica
          $dblogger->logAzione('ASSENZE', 'Modifica uscita');
        } else {
          // nuovo
          $dblogger->logAzione('ASSENZE', 'Crea uscita');
        }
        if (isset($assenzaId)) {
          // cancella assenza
          $dblogger->logAzione('ASSENZE', 'Cancella assenza');
        }
        // redirezione
        return $this->redirectToRoute('lezioni_assenze_quadro', ['posizione' => $posizione]);
      }
    }
    // pagina di risposta
    return $this->renderHtml('richieste', 'uscita', $dati, $info, [$form->createView()]);
  }

  /**
   * Gestione delle richieste
   *
   * @param Request $request Pagina richiesta
   * @param int $pagina Numero di pagina per la lista visualizzata
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/richieste/gestione/{pagina}', name: 'richieste_gestione', requirements: ['pagina' => '\d+'], defaults: ['pagina' => '0'], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_STAFF')]
  public function gestione(Request $request, int $pagina): Response {
    // inizializza
    $info = [];
    $info['sedi'] = [];
    $dati = [];
    // criteri di ricerca
    $criteri = [];
    $criteri['tipo'] = $this->reqstack->getSession()->get('/APP/ROUTE/richieste_gestione/tipo', '');
    $criteri['stato'] = $this->reqstack->getSession()->get('/APP/ROUTE/richieste_gestione/stato', 'I');
    $sede = $this->em->getRepository(Sede::class)->find(
      (int) $this->reqstack->getSession()->get('/APP/ROUTE/richieste_gestione/sede', 0));
    $criteri['sede'] = $sede ? $sede->getId() : 0;
    $classe = $this->em->getRepository(Classe::class)->find(
      (int) $this->reqstack->getSession()->get('/APP/ROUTE/richieste_gestione/classe', 0));
    $criteri['classe'] = $classe ? $classe->getId() : 0;
    $criteri['residenza'] = $this->reqstack->getSession()->get('/APP/ROUTE/richieste_gestione/residenza', '');
    $criteri['cognome'] = $this->reqstack->getSession()->get('/APP/ROUTE/richieste_gestione/cognome', '');
    $criteri['nome'] = $this->reqstack->getSession()->get('/APP/ROUTE/richieste_gestione/nome', '');
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $this->reqstack->getSession()->get('/APP/ROUTE/richieste_gestione/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/richieste_gestione/pagina', $pagina);
    }
    // lista sedi
    if ($this->getUser()->getSede()) {
      // sede definita
      $sede = $this->em->getRepository(Sede::class)->find($this->getUser()->getSede());
      $criteri['sede'] = $sede->getId();
      $opzioniSedi[$sede->getNomeBreve()] = $sede;
    } else {
      // crea lista
      $opzioniSedi = $this->em->getRepository(Sede::class)->opzioni();
      if (!$criteri['sede']) {
        // definisce sempre una sede
        $sede = $opzioniSedi[array_key_first($opzioniSedi)];
        $criteri['sede'] = $sede->getId();
      }
    }
    // cambio sede
    foreach ($opzioniSedi as $s) {
      $info['sedi'][$s->getId()] = $s->getNomeBreve();
    }
    // form filtro
    $opzioniClassi = $this->em->getRepository(Classe::class)->opzioni(
      $this->getUser()->getSede() ? $this->getUser()->getSede()->getId() : null);
    $form = $this->createForm(FiltroType::class, null, ['form_mode' => 'richieste',
      'values' => [$criteri['tipo'], $criteri['stato'], $sede, $opzioniSedi, $classe,
      $opzioniClassi, $criteri['residenza'], $criteri['cognome'], $criteri['nome']]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $criteri['tipo'] = $form->get('tipo')->getData();
      $criteri['stato'] = $form->get('stato')->getData();
      $criteri['sede'] = $form->get('sede')->getData() ? $form->get('sede')->getData()->getId() : 0;
      $criteri['classe'] = $form->get('classe')->getData() ? $form->get('classe')->getData()->getId() : 0;
      $criteri['residenza'] = $form->get('residenza')->getData();
      $criteri['cognome'] = $form->get('cognome')->getData();
      $criteri['nome'] = $form->get('nome')->getData();
      $pagina = 1;
      // memorizza in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/richieste_gestione/tipo', $criteri['tipo']);
      $this->reqstack->getSession()->set('/APP/ROUTE/richieste_gestione/stato', $criteri['stato']);
      $this->reqstack->getSession()->set('/APP/ROUTE/richieste_gestione/sede', $criteri['sede']);
      $this->reqstack->getSession()->set('/APP/ROUTE/richieste_gestione/classe', $criteri['classe']);
      $this->reqstack->getSession()->set('/APP/ROUTE/richieste_gestione/residenza', $criteri['residenza']);
      $this->reqstack->getSession()->set('/APP/ROUTE/richieste_gestione/cognome', $criteri['cognome']);
      $this->reqstack->getSession()->set('/APP/ROUTE/richieste_gestione/nome', $criteri['nome']);
      $this->reqstack->getSession()->set('/APP/ROUTE/richieste_gestione/pagina', $pagina);
    }
    // recupera dati
    $dati = $this->em->getRepository(Richiesta::class)->lista($this->getUser(), $criteri, $pagina);
    // informazioni di visualizzazione
    $info['pagina'] = $pagina;
    // pagina di risposta
    return $this->renderHtml('richieste', 'gestione', $dati, $info, [$form->createView()]);
  }

  /**
   * Rimuove una richiesta. Azione eseguita dal destinatario.
   *
   * @param Request $request Pagina richiesta
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $id Richiesta da rimuovere
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/richieste/remove/{id}', name: 'richieste_remove', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_STAFF')]
  public function remove(Request $request, LogHandler $dblogger, int $id): Response {
    // inizializza
    $info = [];
    $dati = [];
    // controlla richiesta
    $richiesta = $this->em->getRepository(Richiesta::class)->find($id);
    if (!$richiesta || $richiesta->getStato() == 'R') {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla accesso a modulo richiesta
    if (!$this->getUser()->controllaRuoloFunzione($richiesta->getDefinizioneRichiesta()->getDestinatari())) {
      // errore: azione non permessa
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // controlla sede
    if ($this->getUser()->getSede() &&
        $richiesta->getClasse()->getSede() != $this->getUser()->getSede()) {
      // errore: richiesta di sede non permessa
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // informazioni
    $info['richiesta'] = $richiesta;
    // form di gestione
    $form = $this->createForm(RichiestaType::class, null, ['form_mode' => 'remove',
      'values' => [$richiesta->getMessaggio()]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
        // cambia stato
        $richiesta
          ->setGestita(new DateTime())
          ->setStato('R')
          ->setMessaggio($form->get('messaggio')->getData());
      // memorizzazione e log
      $dblogger->logAzione('RICHIESTE', 'Rimuove richiesta');
      // redirezione
      return $this->redirectToRoute('richieste_gestione');
    }
    // pagina di risposta
    return $this->renderHtml('richieste', 'remove', $dati, $info, [$form->createView(),  'message.richieste_remove']);
  }

  /**
   * Gestione di una richiesta. Azione eseguita dal destinatario.
   *
   * @param Request $request Pagina richiesta
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $id Richiesta da rimuovere
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/richieste/manage/{id}', name: 'richieste_manage', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_STAFF')]
  public function manage(Request $request, LogHandler $dblogger, int $id): Response {
    // inizializza
    $info = [];
    $dati = [];
    // controlla richiesta
    $richiesta = $this->em->getRepository(Richiesta::class)->find($id);
    if (!$richiesta) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla accesso a modulo richiesta
    if (!$this->getUser()->controllaRuoloFunzione($richiesta->getDefinizioneRichiesta()->getDestinatari())) {
      // errore: azione non permessa
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // controlla sede
    if ($this->getUser()->getSede() &&
        $richiesta->getClasse()->getSede() != $this->getUser()->getSede()) {
      // errore: richiesta di sede non permessa
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // legge deroga
    $tipo = $richiesta->getDefinizioneRichiesta()->getTipo();
    $deroga = ($tipo == 'E' ? $richiesta->getUtente()->getAutorizzaEntrata() :
      ($tipo == 'D' ? $richiesta->getUtente()->getAutorizzaUscita() : ''));
    // informazioni
    $info['richiesta'] = $richiesta;
    // form di gestione
    $form = $this->createForm(RichiestaType::class, null, [
      'form_mode' => $tipo == 'E' ? 'manageEntrata' : ($tipo == 'D' ? 'manageUscita' : 'manage'),
      'values' => [$deroga, $richiesta->getMessaggio()]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // gestione deroghe
      if ($tipo == 'E') {
        $derogaVecchia = $richiesta->getUtente()->getAutorizzaEntrata();
        $richiesta->getUtente()->setAutorizzaEntrata($form->get('deroga')->getData());
      } elseif ($tipo == 'D') {
        $derogaVecchia = $richiesta->getUtente()->getAutorizzaUscita();
        $richiesta->getUtente()->setAutorizzaUscita($form->get('deroga')->getData());
      }
      // cambia stato
      $richiestaVecchiaStato = $richiesta->getStato();
      $richiesta
        ->setGestita(new DateTime())
        ->setStato('G')
        ->setMessaggio($form->get('messaggio')->getData());
      // memorizzazione e log
      $dblogger->logAzione('RICHIESTE', 'Gestisce richiesta');
      if (isset($derogaVecchia)) {
        $dblogger->logAzione('ALUNNO', 'Modifica deroghe');
      }
      // controlla unicità
      if ($richiesta->getDefinizioneRichiesta()->getUnica() && $richiestaVecchiaStato == 'R') {
        // richiesta gestita deve essere una sola
        $this->em->getRepository(Richiesta::class)->createQueryBuilder('r')
          ->update()
          ->set('r.stato', ':rimossa')
          ->where('r.definizioneRichiesta=:modulo AND r.utente=:alunno AND r.stato=:gestita AND r.id!=:richiesta')
          ->setParameter('rimossa', 'R')
          ->setParameter('modulo', $richiesta->getDefinizioneRichiesta())
          ->setParameter('alunno', $richiesta->getUtente())
          ->setParameter('gestita', 'G')
          ->setParameter('richiesta', $richiesta->getId())
          ->getQuery()
          ->getResult();
      }
      // redirezione
      return $this->redirectToRoute('richieste_gestione');
    }
    // pagina di risposta
    return $this->renderHtml('richieste', 'manage', $dati, $info, [$form->createView(),  'message.richieste_manage']);
  }

  /**
   * Lista dei mpoduli utilizzabili per la classe
   *
   * @param Classe $classe Classe a cui ci si riferisce
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/richieste/classe/{classe}', name: 'richieste_classe', requirements: ['classe' => '\d+'], methods: ['GET'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function classe(
                         #[MapEntity] Classe $classe
                         ): Response {
    // inizializza
    $info = [];
    // recupera dati
    $dati = $this->em->getRepository(DefinizioneRichiesta::class)->listaClasse($classe);
    foreach ($dati['richieste'] as $modulo => $lista) {
      if (!empty($lista['nuove'])) {
        foreach ($lista['nuove'] as $key => $richiesta) {
          $dati['richieste'][$modulo]['nuove'][$key]['delete'] =
            ($this->getUser()->getId() == $richiesta['utente_id']);
        }
      }
    }
    // informazioni
    $info['classe'] = $classe;
    // pagina di risposta
    return $this->renderHtml('richieste', 'classe', $dati, $info);
  }

  /**
   * Annulla un modulo inviato. Azione eseguita dal richiedente.
   *
   * @param LogHandler $dblogger Gestore dei log su database
   * @param Classe $classe Classe a cui è riferito il modulo
   * @param int $id Richiesta da annullare
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/richieste/classe/delete/{classe}/{id}', name: 'richieste_classe_delete', requirements: ['classe' => '\d+', 'id' => '\d+'], methods: ['GET'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function classeDelete(LogHandler $dblogger,
                               #[MapEntity] Classe $classe,
                               int $id): Response {
    // controlla richiesta
    $criteri = $this->getUser()->controllaRuolo('D') ? ['id' => $id, 'stato' => ['I', 'G']] :
      ['id' => $id, 'utente' => $this->getUser(), 'stato' => ['I', 'G']];
    $richiesta = $this->em->getRepository(Richiesta::class)->findOneBy($criteri);
    if (!$richiesta) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla accesso a modulo richiesta
    if (!$this->getUser()->controllaRuoloFunzione($richiesta->getDefinizioneRichiesta()->getRichiedenti())) {
      // errore: azione non permessa
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // controlla se richiesta multipla già gestita
    if ($richiesta->getDefinizioneRichiesta()->getGestione() &&
        !$richiesta->getDefinizioneRichiesta()->getUnica() && $richiesta->getStato() == 'G') {
      // errore: richiesta gestita
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // cambia stato
    $richiesta
      ->setInviata(new DateTime())
      ->setGestita(null)
      ->setStato('A')
      ->setMessaggio('');
    // memorizzazione e log
    $dblogger->logAzione('RICHIESTE', 'Annulla richiesta');
    // redirezione
    return $this->redirectToRoute('richieste_classe', ['classe'=> $classe->getId()]);
  }

  /**
   * Crea un nuovo modulo per la classe
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param RichiesteUtil $ric Funzioni di utilità per la gestione dei moduli
   * @param LogHandler $dblogger Gestore dei log su database
   * @param Classe $classe Classe a cui è riferito il modulo
   * @param int $modulo Identificativo del modulo di richiesta
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/richieste/classe/add/{classe}/{modulo}', name: 'richieste_classe_add', requirements: ['modulo' => '\d+'], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function classeAdd(Request $request, TranslatorInterface $trans, RichiesteUtil $ric, LogHandler $dblogger,
                            #[MapEntity] Classe $classe,
                            int $modulo): Response {
    // inizializza
    $info = [];
    $dati = [];
    $varSessione = '/APP/FILE/richieste_classe_add/files';
    if ($request->isMethod('GET')) {
      // inizializza sessione per allegati
      $this->reqstack->getSession()->set($varSessione, []);
    }
    $utente = ($this->getUser() instanceOf Genitore) ? $this->getUser()->getAlunno() : $this->getUser();
    // controlla modulo richiesta
    $definizioneRichiesta = $this->em->getRepository(DefinizioneRichiesta::class)->findOneBy([
      'id' => $modulo, 'abilitata' => 1]);
    if (!$definizioneRichiesta) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla sede
    $sedi = [$classe->getSede()->getId()];
    if ($definizioneRichiesta->getSede() &&
        !in_array($definizioneRichiesta->getSede()->getId(), $sedi, true)) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla accesso a modulo richiesta
    if (!$this->getUser()->controllaRuoloFunzione($definizioneRichiesta->getRichiedenti())) {
      // errore: azione non permessa
      throw $this->createNotFoundException('exception.not_allowed');
    }
    if ($definizioneRichiesta->getUnica()) {
      // controlla se esiste già una richiesta
      $altraRichiesta = $this->em->getRepository(Richiesta::class)->findOneBy([
        'definizioneRichiesta' => $modulo, 'classe' => $classe, 'stato' => ['I', 'G']]);
      if ($altraRichiesta) {
        // errore: esiste già altra richiesta
        throw $this->createNotFoundException('exception.not_allowed');
      }
    }
    // crea richiesta
    $richiesta = (new Richiesta())
      ->setDefinizioneRichiesta($definizioneRichiesta)
      ->setUtente($utente)
      ->setClasse($classe);
    $this->em->persist($richiesta);
    // informazioni per la visualizzazione
    $info['modulo'] = '@data/moduli/'.$definizioneRichiesta->getModulo();
    $info['allegati'] = $definizioneRichiesta->getAllegati();
    $info['classe'] = $classe;
    $info['valore_classe'] = $classe.' - '.$classe->getSede()->getNomeBreve();
    $info['valore_data'] = (new DateTime())->format('Y-m-d');
    // form di inserimento
    $form = $this->createForm(RichiestaType::class, null, ['form_mode' => 'add',
      'values' => [$definizioneRichiesta->getCampi(), $definizioneRichiesta->getUnica()]]);
    $form->handleRequest($request);
    if ($form->isSubmitted()) {
      $invio = new DateTime();
      $valori = [];
      // controllo errori
      foreach ($definizioneRichiesta->getCampi() as $nome => $campo) {
        if ($form->get($nome)->getData() === null && $campo[1]) {
          // campo obbligatorio vuoto
          $form->addError(new FormError($trans->trans('exception.campo_obbligatorio_vuoto')));
        } else {
          // memorizza valore
          $valori[$nome] = $form->get($nome)->getData();
        }
      }
      if (!$definizioneRichiesta->getUnica()) {
        // controllo data
        if ($form->get('data')->getData() === null) {
          // campo data vuoto
          $form->addError(new FormError($trans->trans('exception.campo_data_vuoto')));
        } elseif (!$definizioneRichiesta->getGestione() && $form->get('data')->getData()->format('Y-m-d') > $info['valore_data']) {
          // per i moduli la data è sempre quella odierna
          $form->addError(new FormError($trans->trans('exception.campo_data_successivo_oggi')));
        } else {
          // controlla se richiesta esiste già per la data
          $altra = $this->em->getRepository(Richiesta::class)->findOneBy([
            'definizioneRichiesta' => $modulo, 'stato' => ['I', 'G'], 'classe' => $classe,
            'data' => $form->get('data')->getData()]);
          if ($altra) {
            // richiesta già presente
            $form->addError(new FormError($trans->trans('exception.richiesta_esistente')));
          }
          if ($definizioneRichiesta->getGestione()) {
            // controlla scadenza
            $oraScadenza = $this->reqstack->getSession()->get('/CONFIG/SCUOLA/scadenza_invio_richiesta');
            $scadenza = clone ($form->get('data')->getData());
            $scadenza->modify('-1 day +'.substr((string) $oraScadenza, 0, 2).' hour +'.substr((string) $oraScadenza, 3, 2).' minute');
            if ($invio > $scadenza) {
              // richiesta inviata oltre i termini
              $form->addError(new FormError($trans->trans('exception.richiesta_ora_invio', [
                'ora' => $oraScadenza])));
            }
          }
        }
      }
      // controlla allegati
      $allegatiTemp = $this->reqstack->getSession()->get($varSessione, []);
      if (count($allegatiTemp) < $info['allegati']) {
        $form->addError(new FormError($trans->trans('exception.modulo_allegati_mancanti')));
        $this->reqstack->getSession()->remove($varSessione);
      }
      if ($form->isValid()) {
        // data richiesta
        $data = $definizioneRichiesta->getUnica() ? null : $form->get('data')->getData();
        // crea documento PDF
        [$documento, $documentoId] = $ric->creaPdf($definizioneRichiesta, $utente, $classe,
          $valori, $data, $invio);
        // imposta eventuali allegati
        $allegati = $ric->impostaAllegati($utente, $classe, $documentoId, $allegatiTemp);
        $this->reqstack->getSession()->remove($varSessione);
        // ok: memorizzazione e log
        $richiesta
          ->setValori($valori)
          ->setDocumento($documento)
          ->setAllegati($allegati)
          ->setInviata($invio)
          ->setGestita(null)
          ->setData($data)
          ->setStato('I')
          ->setMessaggio('');
        $dblogger->logAzione('RICHIESTE', 'Invio richiesta');
        // redirezione
        return $this->redirectToRoute('richieste_classe', ['classe'=> $classe->getId()]);
      }
    }
    // pagina di risposta
    return $this->renderHtml('richieste', 'classe_add', $dati, $info, [$form->createView(),  'message.richieste_classe_add']);
  }

  /**
   * Visualizza i moduli di evacuazione
   *
   * @param Request $request Pagina richiesta
   * @param string $formato Formato della visualizzazione [H=html,C=csv]
   * @param int $pagina Numero di pagina per la lista visualizzata
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/richieste/modulo/evacuazione/{formato}/{pagina}', name: 'richieste_modulo_evacuazione', requirements: ['formato' => 'H|C', 'pagina' => '\d+'], defaults: ['formato' => 'H', 'pagina' => '0'], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_STAFF')]
  public function moduloEvacuazione(Request $request, string $formato, int $pagina): Response {
    // inizializza
    $info = [];
    $info['sedi'] = [];
    $dati = [];
    // criteri di ricerca
    $criteri = [];
    $sede = $this->em->getRepository(Sede::class)->find(
      (int) $this->reqstack->getSession()->get('/APP/ROUTE/richieste_modulo_evacuazione/sede', 0));
    $criteri['sede'] = $sede ? $sede->getId() : 0;
    $classe = $this->em->getRepository(Classe::class)->find(
      (int) $this->reqstack->getSession()->get('/APP/ROUTE/richieste_modulo_evacuazione/classe', 0));
    $criteri['classe'] = $classe ? $classe->getId() : 0;
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $this->reqstack->getSession()->get('/APP/ROUTE/richieste_modulo_evacuazione/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/richieste_modulo_evacuazione/pagina', $pagina);
    }
    // lista sedi
    if ($this->getUser()->getSede()) {
      // sede definita
      $sede = $this->em->getRepository(Sede::class)->find($this->getUser()->getSede());
      $criteri['sede'] = $sede->getId();
      $opzioniSedi[$sede->getNomeBreve()] = $sede;
    } else {
      // crea lista
      $opzioniSedi = $this->em->getRepository(Sede::class)->opzioni();
    }
    // cambio sede
    foreach ($opzioniSedi as $s) {
      $info['sedi'][$s->getId()] = $s->getNomeBreve();
    }
    // form filtro
    $opzioniClassi = $this->em->getRepository(Classe::class)->opzioni(
      $this->getUser()->getSede() ? $this->getUser()->getSede()->getId() : null);
    $form = $this->createForm(FiltroType::class, null, ['form_mode' => 'evacuazione',
      'values' => [$sede, $opzioniSedi, $classe, $opzioniClassi]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $criteri['sede'] = $form->get('sede')->getData() ? $form->get('sede')->getData()->getId() : 0;
      $criteri['classe'] = $form->get('classe')->getData() ? $form->get('classe')->getData()->getId() : 0;
      $pagina = 1;
      // memorizza in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/richieste_modulo_evacuazione/sede', $criteri['sede']);
      $this->reqstack->getSession()->set('/APP/ROUTE/richieste_modulo_evacuazione/classe', $criteri['classe']);
      $this->reqstack->getSession()->set('/APP/ROUTE/richieste_modulo_evacuazione/pagina', $pagina);
    }
    // recupera dati
    $dati = $this->em->getRepository(Richiesta::class)->listaClasse($this->getUser(), 'V',
      $criteri, $formato == 'C' ? -1 : $pagina);
    // informazioni di visualizzazione
    $info['pagina'] = $pagina;
    // pagina di risposta
    if ($formato == 'C') {
      // crea documento CSV
      $csv = $this->renderView('richieste/modulo_evacuazione.csv.twig', [
        'dati' => $dati,
        'info' => $info]);
      // invia il documento
      $nomefile = 'prove-evacuazione.csv';
      $response = new Response($csv);
      $disposition = HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $nomefile);
      $response->headers->set('Content-Disposition', $disposition);
      $response->headers->set('Content-Type', 'text/csv');
      return $response;
    }
    // visualizza pagina HTML
    return $this->renderHtml('richieste', 'modulo_evacuazione', $dati, $info, [$form->createView()]);
  }

  /**
   * Visualizza i moduli presenti
   *
   * @param Request $request Pagina richiesta
   * @param string $formato Formato della visualizzazione [H=html,C=csv]
   * @param int $pagina Numero di pagina per la lista visualizzata
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/richieste/modulo/lista/{formato}/{pagina}', name: 'richieste_modulo_lista', requirements: ['formato' => 'H|C', 'pagina' => '\d+'], defaults: ['formato' => 'H', 'pagina' => '0'], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_STAFF')]
  public function moduloLista(Request $request, string $formato, int $pagina): Response {
    // inizializza
    $info = [];
    $info['sedi'] = [];
    $dati = [];
    // criteri di ricerca
    $criteri = [];
    $criteri['tipo'] = $this->reqstack->getSession()->get('/APP/ROUTE/richieste_modulo_lista/tipo', '');
    $sede = $this->em->getRepository(Sede::class)->find(
      (int) $this->reqstack->getSession()->get('/APP/ROUTE/richieste_modulo_lista/sede', 0));
    $criteri['sede'] = $sede ? $sede->getId() : 0;
    $classe = $this->em->getRepository(Classe::class)->find(
      (int) $this->reqstack->getSession()->get('/APP/ROUTE/richieste_modulo_lista/classe', 0));
    $criteri['classe'] = $classe ? $classe->getId() : 0;
    $criteri['cognome'] = $this->reqstack->getSession()->get('/APP/ROUTE/richieste_modulo_lista/cognome', '');
    $criteri['nome'] = $this->reqstack->getSession()->get('/APP/ROUTE/richieste_modulo_lista/nome', '');
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $this->reqstack->getSession()->get('/APP/ROUTE/richieste_modulo_lista/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/richieste_modulo_lista/pagina', $pagina);
    }
    // lista tipi
    $opzioniTipi = $this->em->getRepository(DefinizioneRichiesta::class)
      ->opzioniModuli($this->getUser());
    // lista sedi
    if ($this->getUser()->getSede()) {
      // sede definita
      $sede = $this->em->getRepository(Sede::class)->find($this->getUser()->getSede());
      $criteri['sede'] = $sede->getId();
      $opzioniSedi[$sede->getNomeBreve()] = $sede;
    } else {
      // crea lista
      $opzioniSedi = $this->em->getRepository(Sede::class)->opzioni();
    }
    // cambio sede
    foreach ($opzioniSedi as $s) {
      $info['sedi'][$s->getId()] = $s->getNomeBreve();
    }
    // lista classi
    $opzioniClassi = $this->em->getRepository(Classe::class)->opzioni(
      $this->getUser()->getSede() ? $this->getUser()->getSede()->getId() : null);
    // form filtro
    $form = $this->createForm(FiltroType::class, null, ['form_mode' => 'moduli',
      'values' => [$criteri['tipo'], $opzioniTipi, $sede, $opzioniSedi, $classe, $opzioniClassi,
      $criteri['cognome'], $criteri['nome']]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $criteri['tipo'] = $form->get('tipo')->getData();
      $criteri['sede'] = $form->get('sede')->getData() ? $form->get('sede')->getData()->getId() : 0;
      $criteri['classe'] = $form->get('classe')->getData() ? $form->get('classe')->getData()->getId() : 0;
      $criteri['cognome'] = $form->get('cognome')->getData();
      $criteri['nome'] = $form->get('nome')->getData();
      $pagina = 1;
      // memorizza in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/richieste_modulo_lista/tipo', $criteri['tipo']);
      $this->reqstack->getSession()->set('/APP/ROUTE/richieste_modulo_lista/sede', $criteri['sede']);
      $this->reqstack->getSession()->set('/APP/ROUTE/richieste_modulo_lista/classe', $criteri['classe']);
      $this->reqstack->getSession()->set('/APP/ROUTE/richieste_modulo_lista/cognome', $criteri['cognome']);
      $this->reqstack->getSession()->set('/APP/ROUTE/richieste_modulo_lista/nome', $criteri['nome']);
      $this->reqstack->getSession()->set('/APP/ROUTE/richieste_modulo_lista/pagina', $pagina);
    }
    // recupera dati
    $dati = $this->em->getRepository(Richiesta::class)
      ->listaModuliAlunni($this->getUser(), $criteri, $formato == 'C' ? -1 : $pagina);
    // informazioni di visualizzazione
    $info['pagina'] = $pagina;
    // pagina di risposta
    if ($formato == 'C') {
      // crea documento CSV
      $csv = $this->renderView('richieste/modulo_lista.csv.twig', [
        'dati' => $dati,
        'info' => $info]);
      // invia il documento
      $nomefile = 'modulo.csv';
      $response = new Response($csv);
      $disposition = HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $nomefile);
      $response->headers->set('Content-Disposition', $disposition);
      $response->headers->set('Content-Type', 'text/csv');
      return $response;
    }
    // visualizza pagina HTML
    return $this->renderHtml('richieste', 'modulo_lista', $dati, $info, [$form->createView()]);
  }

}
