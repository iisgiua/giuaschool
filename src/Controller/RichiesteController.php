<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Controller;

use App\Entity\DefinizioneRichiesta;
use App\Entity\Genitore;
use App\Entity\Richiesta;
use App\Form\RichiestaType;
use App\Util\LogHandler;
use App\Util\RichiesteUtil;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
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
   * @Route("/richieste/lista", name="richieste_lista",
   *    methods={"GET"})
   *
   * @Security("is_granted('ROLE_GENITORE') or is_granted('ROLE_ALUNNO')")
   */
  public function listaAction(): Response {
    // inizializza
    $info = [];
    // recupera dati
    $dati = $this->em->getRepository('App\Entity\DefinizioneRichiesta')->lista($this->getUser());
    // pagina di risposta
    return $this->renderHtml('richieste', 'lista', $dati, $info);
  }

  /**
   * Crea una nuova richiesta
   *
   * @param Request $request Pagina richiesta
   * @param RequestStack $reqstack Gestore dello stack delle variabili globali
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param RichiesteUtil $ric Funzioni di utilità per la gestione dei moduli di richiesta
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $modulo Identificativo del modulo di richiesta
   *
   * @return Response Pagina di risposta
   *
   * @Route("/richieste/add/{modulo}", name="richieste_add",
   *    requirements={"modulo": "\d+"},
   *    methods={"GET","POST"})
   *
   * @Security("is_granted('ROLE_GENITORE') or is_granted('ROLE_ALUNNO')")
   */
  public function addAction(Request $request, RequestStack $reqstack, TranslatorInterface $trans,
                            RichiesteUtil $ric, LogHandler $dblogger, int $modulo): Response {
    // inizializza
    $info = [];
    $dati = [];
    $varSessione = '/APP/FILE/richieste_add/files';
    if ($request->isMethod('GET')) {
      // inizializza sessione per allegati
      $reqstack->getSession()->set($varSessione, []);
    }
    $utente = $this->getUser() instanceOf Genitore ? $this->getUser()->getAlunno() : $this->getUser();
    // controlla modulo richiesta
    $definizioneRichiesta = $this->em->getRepository('App\Entity\DefinizioneRichiesta')->findOneBy([
      'id' => $modulo, 'abilitata' => 1]);
    if (!$definizioneRichiesta) {
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
      $altraRichiesta = $this->em->getRepository('App\Entity\Richiesta')->findOneBy([
        'definizioneRichiesta' => $modulo, 'utente' => $utente, 'stato' => ['I', 'G']]);
      if ($altraRichiesta) {
        // errore: esiste già altra richiesta
        throw $this->createNotFoundException('exception.not_allowed');
      }
    }
    // crea richiesta
    $richiesta = (new Richiesta())
      ->setDefinizioneRichiesta($definizioneRichiesta)
      ->setUtente($utente);
    $this->em->persist($richiesta);
    // informazioni per la visualizzazione
    $info['modulo'] = 'PERSONALI/moduli/'.$definizioneRichiesta->getModulo();
    $info['allegati'] = $definizioneRichiesta->getAllegati();
    // form di inserimento
    $form = $this->createForm(RichiestaType::class, null, ['returnUrl' => $this->generateUrl('richieste_lista'),
      'dati' => [$definizioneRichiesta->getCampi()]]);
    $form->handleRequest($request);
    if ($form->isSubmitted()) {
      $invio = new \DateTime();
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
      // controlla allegati
      $allegatiTemp = $reqstack->getSession()->get($varSessione, []);
      if (count($allegatiTemp) < $info['allegati']) {
        $form->addError(new FormError($trans->trans('exception.modulo_allegati_mancanti')));
        $reqstack->getSession()->remove($varSessione);
      }
      if ($form->isValid()) {
        // crea documento PDF
        list($documento, $documentoId) = $ric->creaPdf($definizioneRichiesta, $utente, $valori, $invio);
        // imposta eventuali allegati
        $allegati = $ric->impostaAllegati($utente, $documentoId, $allegatiTemp);
        $reqstack->getSession()->remove($varSessione);
        // ok: memorizzazione e log
        $richiesta
          ->setValori($valori)
          ->setDocumento($documento)
          ->setAllegati($allegati)
          ->setInviata($invio)
          ->setGestita(null)
          ->setStato('I')
          ->setMessaggio('');
        $dblogger->logCreazione('RICHIESTE', 'Invio richiesta', $richiesta);
        // redirezione
        return $this->redirectToRoute('richieste_lista');
      }
    }
    // pagina di risposta
    return $this->renderHtml('richieste', 'add', $dati, $info, [$form->createView(),  'message.richieste_add']);
  }

  /**
   * Annulla una richiesta inviata
   *
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $id Richiesta da annullare
   *
   * @return Response Pagina di risposta
   *
   * @Route("/richieste/delete/{id}", name="richieste_delete",
   *    requirements={"id": "\d+"},
   *    methods={"GET"})
   *
   * @Security("is_granted('ROLE_GENITORE') or is_granted('ROLE_ALUNNO')")
   */
  public function deleteAction(LogHandler $dblogger, int $id): Response {
    // inizializza
    $utente = $this->getUser() instanceOf Genitore ? $this->getUser()->getAlunno() : $this->getUser();
    // controlla richiesta
    $richiesta = $this->em->getRepository('App\Entity\Richiesta')->findOneBy(['id' => $id,
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
    // cambia stato
    $richiestaVecchia = clone $richiesta;
    $richiesta
      ->setInviata(new \DateTime())
      ->setGestita(null)
      ->setStato('A')
      ->setMessaggio('');
    // memorizzazione e log
    $dblogger->logModifica('RICHIESTE', 'Annulla richiesta', $richiestaVecchia, $richiesta);
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
   * @Route("/richieste/download/{id}/{documento}", name="richieste_download",
   *    requirements={"id": "\d+", "documento": "\d+"},
   *    defaults={"documento": "0"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_UTENTE")
   */
  public function downloadAction(int $id, int $documento): Response {
    // controlla richiesta
    $richiesta = $this->em->getRepository('App\Entity\Richiesta')->find($id);
    if (!$richiesta) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla accesso
    if ($this->getUser()->controllaRuoloFunzione($richiesta->getDefinizioneRichiesta()->getRichiedenti())) {
      // utente tra i richiedenti
      $utente = $this->getUser() instanceOf Genitore ? $this->getUser()->getAlunno() : $this->getUser();
      if ($richiesta->getUtente() != $utente || !in_array($richiesta->getStato(), ['I', 'G'], true)) {
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
      $richiesta->getUtente()->getClasse()->getAnno().$richiesta->getUtente()->getClasse()->getSezione().
      '/documenti/';
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
   * Scarica il documento del modulo di richiesta o uno degli allegati
   *
   //-- * @param int $id Identificativo della richiesta
   //-- * @param int $documento Indica il documento da scaricare: 0=modulo di richiesta, 1...=allegato indicato
   *
   * @return Response Pagina di risposta
   *
   * @Route("/richieste/gestione", name="richieste_gestione",
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_STAFF")
   */
  public function gestioneAction(): Response {
    // inizializza
    $info = [];
    // recupera dati
    $dati = $this->em->getRepository('App\Entity\DefinizioneRichiesta')->gestione($this->getUser());
    // pagina di risposta
    return $this->renderHtml('richieste', 'gestione', $dati, $info);
  }

}
