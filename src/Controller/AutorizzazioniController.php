<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Controller;

use App\Entity\Classe;
use App\Entity\DefinizioneAutorizzazione;
use App\Entity\Richiesta;
use App\Entity\Sede;
use App\Form\DefinizioneAutorizzazioneType;
use App\Form\FiltroType;
use App\Form\RichiestaType;
use App\Util\LogHandler;
use App\Util\RichiesteUtil;
use DateTime;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;


/**
 * AutorizzazioniController - gestione dei dati delle autorizzazioni
 *
 * @author Antonello Dessì
 */
class AutorizzazioniController extends BaseController {

  /**
   * Gestione delle autorizzazioni per le attività
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param int $pagina Numero di pagina per l'elenco da visualizzare
   *
   * @return Response Pagina di risposta
   */
  #[Route(path: '/autorizzazioni/lista', name: 'autorizzazioni_lista', requirements: ['pagina' => '\d+'], defaults: ['pagina' => '0'], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_STAFF')]
  public function lista(Request $request, TranslatorInterface $trans, int $pagina): Response {
    // inizializza
    $info = [];
    // recupera criteri dalla sessione
    $criteri = [];
    $criteri['sede'] = $this->reqstack->getSession()->get('/APP/ROUTE/autorizzazioni_lista/sede', 0);
    $criteri['classe'] = $this->reqstack->getSession()->get('/APP/ROUTE/autorizzazioni_lista/classe', 0);
    $criteri['tipo'] = $this->reqstack->getSession()->get('/APP/ROUTE/autorizzazioni_lista/tipo', null);
    $criteri['mese'] = $this->reqstack->getSession()->get('/APP/ROUTE/autorizzazioni_lista/mese', null);
    $criteri['nome'] = $this->reqstack->getSession()->get('/APP/ROUTE/autorizzazioni_lista/nome', '');
    $sede = ($criteri['sede'] > 0 ? $this->em->getRepository(Sede::class)->find($criteri['sede']) : null);
    $classe = ($criteri['classe'] > 0 ? $this->em->getRepository(Classe::class)->find($criteri['classe']) : null);
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $this->reqstack->getSession()->get('/APP/ROUTE/autorizzazioni_lista/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/autorizzazioni_lista/pagina', $pagina);
    }
    // filtro sedi
    if ($this->getUser()->getSede()) {
      // sede definita
      $sede = $this->getUser()->getSede();
      $criteri['sede'] = $sede->getId();
      $opzSedi[$sede->getNomeBreve()] = $sede;
    } else {
      // crea lista
      $opzSedi = $this->em->getRepository(Sede::class)->opzioni();
      $opzSedi= array_merge([$trans->trans('label.qualsiasi_sede') => 0], $opzSedi);
    }
    // cambio sede
    foreach ($opzSedi as $s) {
      if (is_object($s)) {
        $info['sedi'][$s->getId()] = $s->getNomeBreve();
      }
    }
    // filtro classi
    $opzioniClassi = $this->em->getRepository(Classe::class)->opzioni(
      $this->getUser()->getSede() ? $this->getUser()->getSede()->getId() : null, false);
    // filtro mesi
    $opzMesi = ['Settembre' => 9, 'Ottobre' => 10, 'Novembre' => 11 , 'Dicembre' => 12, 'Gennaio' => 1,
      'Febbraio' => 2, 'Marzo' => 3, 'Aprile' => 4, 'Maggio' => 5, 'Giugno' => 6, 'Luglio' => 7, 'Agosto' => 8];
    // form filtro
    $form = $this->createForm(FiltroType::class, null, ['form_mode' => 'autorizzazioni',
      'values' => [$sede, $opzSedi, $classe, $opzioniClassi, $criteri['tipo'], $criteri['mese'], $opzMesi,
      $criteri['nome']]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $criteri['sede'] = (is_object($form->get('sede')->getData()) ? $form->get('sede')->getData()->getId() : 0);
      $criteri['classe'] = (is_object($form->get('classe')->getData()) ? $form->get('classe')->getData()->getId() : 0);
      $criteri['tipo'] = $form->get('tipo')->getData();
      $criteri['mese'] = $form->get('mese')->getData();
      $criteri['nome'] = $form->get('nome')->getData();
      $pagina = 1;
      $this->reqstack->getSession()->set('/APP/ROUTE/autorizzazioni_lista/sede', $criteri['sede']);
      $this->reqstack->getSession()->set('/APP/ROUTE/autorizzazioni_lista/classe', $criteri['classe']);
      $this->reqstack->getSession()->set('/APP/ROUTE/autorizzazioni_lista/tipo', $criteri['tipo']);
      $this->reqstack->getSession()->set('/APP/ROUTE/autorizzazioni_lista/mese', $criteri['mese']);
      $this->reqstack->getSession()->set('/APP/ROUTE/autorizzazioni_lista/nome', $criteri['nome']);
      $this->reqstack->getSession()->set('/APP/ROUTE/autorizzazioni_lista/pagina', $pagina);
    }
    // informazioni di visualizzazione
    $info['pagina'] = $pagina;
    // recupera dati
    $dati = $this->em->getRepository(DefinizioneAutorizzazione::class)->listaGestione($criteri, $pagina);
    // mostra la pagina di risposta
    return $this->renderHtml('autorizzazioni', 'lista', $dati, $info, [$form->createView()]);
   }

  /**
   * Modifica i dati di una autorizzazione
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param LogHandler $dblogger Gestore dei log su database
   * @param DefinizioneAutorizzazione|null $autorizzazione Modulo di autorizzazione da modificare, se nullo crea nuovo modulo
   *
   * @return Response Pagina di risposta
   */
  #[Route(path: '/autorizzazioni/edit/{autorizzazione}', name: 'autorizzazioni_edit', requirements: ['autorizzazione' => '\d+'], defaults: ['autorizzazione' => '0'], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_STAFF')]
  public function edit(Request $request, TranslatorInterface $trans, LogHandler $dblogger,
                       #[MapEntity] ?DefinizioneAutorizzazione $autorizzazione
                       ): Response {
    // init
    $dati = [];
    $info = [];
    // controlla azione
    if ($autorizzazione) {
      // azione edit
      $edit = true;
      $sede = $autorizzazione->getDati()['luogo_sede'] ?? 0;
      $sede = $this->em->getRepository(Sede::class)->find($sede);
    } else {
      // azione add
      $edit = false;
      $sede = null;
      $dataInizio = (new DateTime('tomorrow'))->modify('+8 hours');
      $dataFine = (new DateTime('tomorrow'))->modify('+10 hours');
      $autorizzazione = (new DefinizioneAutorizzazione())
        ->setInizio($dataInizio)
        ->setFine($dataFine)
        ->setAbilitata((false));
      $this->em->persist($autorizzazione);
    }
    // informazioni di visualizzazione
    $info['classi'] = $autorizzazione->getClassi();
    // form
    $opzioniSedi = $this->em->getRepository(Sede::class)->opzioni();
    $opzioniClassi = $this->em->getRepository(Classe::class)->opzioni(null, true, false);
    $form = $this->createForm(DefinizioneAutorizzazioneType::class, $autorizzazione, [
      'return_url' => $this->generateUrl('autorizzazioni_lista'),
      'values' => [$autorizzazione->getInizio(), $autorizzazione->getFine(),
       $sede, $opzioniSedi, $opzioniClassi]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // controllo intervallo date
      $inizio = $form->get('inizio')->getData()->setTime(
        (int) $form->get('inizio_ora')->getData()->format('H'),
        (int) $form->get('inizio_ora')->getData()->format('i'), 0);
      $autorizzazione->setInizio($inizio);
      $fine = (clone $inizio)->setTime(
        (int) $form->get('fine_ora')->getData()->format('H'),
        (int) $form->get('fine_ora')->getData()->format('i'), 0);
      $autorizzazione->setFine($fine);
      if ($inizio >= $fine) {
        // errore: intervallo non valido
        $form->addError(new FormError($trans->trans('exception.autorizzazione_intervallo_date_invalido')));
      }
      // controlla classi
      if ($request->request->get('tutti')) {
        // tutte le classi
        $autorizzazione->setClassi([]);
      } elseif (count($form->get('classi')->getData()) == 0) {
        // errore: nessuna classe selezionata
        $form->addError(new FormError($trans->trans('exception.autorizzazione_nessuna_classe')));
      } else {
        // imposta classi
        $listaClassi = array_map(fn($o) => $o->getId(), $form->get('classi')->getData());
        $autorizzazione->setClassi($listaClassi);
      }
      // controllo descrizione
      if (empty($autorizzazione->getDati()['descrizione'])) {
        // errore: descrizione non specificata
        $form->addError(new FormError($trans->trans('exception.autorizzazione_no_descrizione')));
      }
      if ($autorizzazione->getTipo() == 'U' || $autorizzazione->getTipo() == 'V') {
        // controllo uscita/visita
        if (empty($autorizzazione->getDati()['destinazione'])) {
          // errore: destinazione non specificata
          $form->addError(new FormError($trans->trans('exception.autorizzazione_no_destinazione')));
        }
        if (empty($autorizzazione->getDati()['svolgimento'])) {
          // errore: modalità di svolgimento non specificata
          $form->addError(new FormError($trans->trans('exception.autorizzazione_no_svolgimento')));
        }
        if (empty($autorizzazione->getDati()['accompagnatori'])) {
          // errore: accompagnatori non specificati
          $form->addError(new FormError($trans->trans('exception.autorizzazione_no_accompagnatori')));
        }
        if (empty($autorizzazione->getDati()['partenza'])) {
          // errore: partenza non specificata
          $form->addError(new FormError($trans->trans('exception.autorizzazione_no_partenza')));
        }
        if (empty($autorizzazione->getDati()['rientro'])) {
          // errore: rientro non specificato
          $form->addError(new FormError($trans->trans('exception.autorizzazione_no_rientro')));
        }
      } else {
        // controllo evento/conferenza/attività
        $sede = $form->get('luogo_sede')->getData();
        if (empty($sede)) {
          // errore: sede non specificata
          $form->addError(new FormError($trans->trans('exception.autorizzazione_no_luogo_sede')));
        } else {
          // imposta sede
          $dati = $autorizzazione->getDati();
          $dati['luogo_sede'] = $sede->getId();
          $autorizzazione->setDati($dati);
        }
        if (empty($autorizzazione->getDati()['luogo_aula'])) {
          // errore: aula non specificata
          $form->addError(new FormError($trans->trans('exception.autorizzazione_no_luogo_aula')));
        }
      }
      if ($form->isValid()) {
        // imposta template
        $autorizzazione->setModulo('definizione_autorizzazione_'.$autorizzazione->getTipo().'.html.twig');
        // memorizza dati
        $this->em->flush();
        // log azione
        $dblogger->logAzione('AUTORIZZAZIONI', $edit ? 'Modifica un modulo di autorizzazione' :
          'Crea un modulo di autorizzazione');
        // redirect
        return $this->redirectToRoute('autorizzazioni_lista');
      }
    }
    // mostra la pagina di risposta
    return $this->renderHtml('autorizzazioni', 'edit', $dati, $info, [$form->createView(),
      'message.autorizzazioni_edit']);
  }

  /**
   * Abilitazione o disabilitazione dei moduli di autorizzazione
   *
   * @param LogHandler $dblogger Gestore dei log su database
   * @param DefinizioneAutorizzazione $autorizzazione Modulo di autorizzazione da abilitare/disabilitare
   * @param int $abilita Valore 1 per abilitare, valore 0 per disabilitare
   *
   * @return Response Pagina di risposta
   */
  #[Route(path: '/autorizzazioni/abilita/{autorizzazione}/{abilita}', name: 'autorizzazioni_abilita', requirements: ['autorizzazione' => '\d+', 'abilita' => '0|1'], methods: ['GET'])]
  #[IsGranted('ROLE_STAFF')]
  public function abilita(LogHandler $dblogger,
                          #[MapEntity] DefinizioneAutorizzazione $autorizzazione,
                          int $abilita): Response {
    // abilita o disabilita
    $autorizzazione->setAbilitata($abilita == 1);
    // memorizza modifiche
    $this->em->flush();
    // log azione
    $dblogger->logAzione('AUTORIZZAZIONI', $autorizzazione->getAbilitata() ?
      'Abilitazione del modulo di autorizzazione' : 'Disabilitazione del modulo di autorizzazione');
    // redirezione
    return $this->redirectToRoute('autorizzazioni_lista');
  }

  /**
   * Visualizza l'anteprima di un modulo di autorizzazione
   *
   * @param Environment $tpl Gestione template
   * @param DefinizioneAutorizzazione $autorizzazione Modulo di autorizzazione da visualizzare in anteprima
   *
   * @return Response Pagina di risposta
   */
  #[Route(path: '/autorizzazioni/anteprima/{autorizzazione}', name: 'autorizzazioni_anteprima', requirements: ['autorizzazione' => '\d+'], methods: ['GET'])]
  #[IsGranted('ROLE_STAFF')]
  public function anteprima(Environment $tpl,
                            #[MapEntity] DefinizioneAutorizzazione $autorizzazione
                            ): Response {
    // legge template
    $percorso = $this->getParameter('kernel.project_dir').'/PERSONAL/data/autorizzazioni/';
    $template = file_get_contents($percorso.$autorizzazione->getModulo());
    // toglie dati personali
    $template = preg_replace('/\{\{\s*app\.[^\}]*\}\}/', '<strong>***</strong>', $template);
    // crea pagina HTML
    $listaSedi = $this->em->getRepository(Sede::class)->lista();
    $templateTwig = $tpl->createTemplate($template);
    $html = $tpl->render($templateTwig, ['autorizzazione' => $autorizzazione, 'ruolo' => 'GN',
      'sedi' => $listaSedi]);
    // mostra la pagina di risposta
    return new Response($html);
  }

  /**
   * Inserisce una autorizzazione ad una attività
   *
   * @param Request $request Pagina richiesta
   * @param RichiesteUtil $ric Funzioni di utilità per la gestione dei moduli di richiesta
   * @param LogHandler $dblogger Gestore dei log su database
   * @param DefinizioneAutorizzazione $modulo Modulo dell'autorizzazione'
   *
   * @return Response Pagina di risposta
   */
  #[Route(path: '/autorizzazioni/autorizza/{modulo}', name: 'autorizzazioni_autorizza', requirements: ['modulo' => '\d+'], methods: ['GET', 'POST'])]
  #[IsGranted(attribute: new Expression("is_granted('ROLE_GENITORE') or is_granted('ROLE_ALUNNO')"))]
  public function autorizza(Request $request, RichiesteUtil $ric, LogHandler $dblogger,
                            #[MapEntity] DefinizioneAutorizzazione $modulo
                            ): Response {
    // inizializza
    $info = [];
    $dati = [];
    $utente = $this->getUser();
    // controlla modulo
    if (!$modulo->getAbilitata() || (new DateTime('today')) > $modulo->getInizio()) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla sede
    $classe = $utente->getCodiceRuolo() == 'A' ? $utente->getClasse() :
      ($utente->getCodiceRuolo() == 'G' ? $utente->getAlunno()->getClasse() : null);
    $sedi = $classe ? [$classe->getSede()->getId()] : [];
    if ($modulo->getSede() && !in_array($modulo->getSede()->getId(), $sedi, true)) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla classe
    if (!empty($modulo->getClassi()) && (!$classe || !in_array($classe->getId(), $modulo->getClassi()))) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla accesso a modulo richiesta
    if (!$this->getUser()->controllaRuoloFunzione($modulo->getRichiedenti())) {
      // errore: azione non permessa
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // trova autorizzazioni firmate
    $esistenti = $this->em->getRepository(Richiesta::class)->autorizzazioni($modulo,
      $utente->getCodiceRuolo() == 'G' ? $utente->getAlunno() : $utente);
    // controlla se esiste già una risposta dell'utente
    foreach ($esistenti as $altra) {
      if ($altra->getUtente()->getId() == $utente->getId()) {
        // errore: esiste già altra risposta
        throw $this->createNotFoundException('exception.not_allowed');
      }
    }
    // crea autorizzazione
    $autorizzazione = (new Richiesta())
      ->setDefinizioneRichiesta($modulo)
      ->setUtente($utente)
      ->setClasse($classe);
    $this->em->persist($autorizzazione);
    // informazioni per la visualizzazione
    $info['modulo'] = '@data/autorizzazioni/'.$modulo->getModulo();
    $info['ruolo'] = $utente->getCodiceRuolo() == 'G' ? 'GN' :
      ($utente->controllaRuoloFunzione('AM') ? 'AM' : '');
    $info['autorizzazione'] = $modulo;
    $info['sedi'] = $this->em->getRepository(Sede::class)->lista();
    // form di inserimento
    $form = $this->createForm(RichiestaType::class, null, ['form_mode' => 'add',
      'values' => [$modulo->getCampi(), $modulo->getUnica()]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      $invio = new DateTime();
      // memorizza risposta
      $autorizzazione
        ->setInviata($invio)
        ->setGestita(null)
        ->setStato('I')
        ->setMessaggio('');
      $this->em->flush();
      // crea documento PDF
      if (count($esistenti) == 0) {
        // crea una nuova autorizzazione
        $documento = $ric->autorizzazionePdf($modulo, $info['ruolo'], $info['sedi'], $autorizzazione->getId(),
          $classe, $invio);
        $msgLog = 'Invio autorizzazione';
      } else {
        // firma un'autorizzazione esistente
        $documento = $ric->firmaAutorizzazionePdf($esistenti[0], $info['ruolo'], $info['sedi'], $autorizzazione->getId(), $classe, $invio);
        $msgLog = 'Firma autorizzazione';
      }
      // ok: memorizzazione e log
      $autorizzazione
        ->setDocumento($documento);
      $dblogger->logAzione('AUTORIZZAZIONI', $msgLog);
      // redirezione
      return $this->redirectToRoute('richieste_lista');
    }
    // pagina di risposta
    return $this->renderHtml('autorizzazioni', 'add', $dati, $info, [$form->createView(),
      'message.autorizzazioni_autorizza']);
  }

  /**
   * Mostra i dettagli delle autorizzazioni inviate
   *
  //  * @param RichiesteUtil $ric Funzioni di utilità per la gestione dei moduli di richiesta
   * @param DefinizioneAutorizzazione $modulo Modulo dell'autorizzazione'
   *
   * @return Response Pagina di risposta
   */
  #[Route(path: '/autorizzazioni/dettagli/{modulo}', name: 'autorizzazioni_dettagli', requirements: ['modulo' => '\d+'], methods: ['GET'])]
  #[IsGranted('ROLE_STAFF')]
  public function dettagli(
        // Request $request, RichiesteUtil $ric, LogHandler $dblogger,
                            #[MapEntity] DefinizioneAutorizzazione $modulo
                            ): Response {
    // inizializza
    $info = [];
    $dati = [];

    $dati = $this->em->getRepository(Richiesta::class)->autorizzazioniDettagli($modulo);

    // pagina di risposta
    return $this->renderHtml('autorizzazioni', 'dettagli', $dati, $info);
  }

}
