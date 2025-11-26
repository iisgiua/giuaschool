<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Controller;


use App\Entity\Alunno;
use App\Entity\Avviso;
use App\Entity\Cattedra;
use App\Entity\Classe;
use App\Entity\ComunicazioneClasse;
use App\Entity\ComunicazioneUtente;
use App\Entity\Configurazione;
use App\Entity\Docente;
use App\Entity\Festivita;
use App\Entity\Materia;
use App\Entity\Orario;
use App\Entity\ScansioneOraria;
use App\Entity\Sede;
use App\Entity\Staff;
use App\Form\AvvisoFiltroType;
use App\Form\AvvisoType;
use App\Message\AvvisoMessage;
use App\MessageHandler\NotificaMessageHandler;
use App\Util\ComunicazioniUtil;
use App\Util\LogHandler;
use App\Util\RegistroUtil;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use IntlDateFormatter;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;


/**
 * AvvisiController - gestione degli avvisi
 *
 * @author Antonello Dessì
 */
class AvvisiController extends BaseController {

   /**
   * Gestione degli avvisi
   *
   * @param Request $request Pagina richiesta
   * @param ComunicazioniUtil $com Funzioni di utilità per la gestione delle comunicazioni
   * @param int $pagina Numero di pagina per l'elenco da visualizzare
   *
   * @return Response Pagina di risposta
   */
  #[Route(path: '/avvisi/gestione/{pagina}', name: 'avvisi_gestione', requirements: ['pagina' => '\d+'], defaults: ['pagina' => '0'], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_STAFF')]
  public function gestione(Request $request, ComunicazioniUtil $com, int $pagina): Response {
    // inizializza
    $info = [];
    // recupera criteri dalla sessione
    $criteri = [];
    $criteri['autore'] = $this->reqstack->getSession()->get('/APP/ROUTE/avvisi_gestione/autore', null);
    $criteri['tipo'] = $this->reqstack->getSession()->get('/APP/ROUTE/avvisi_gestione/tipo', 'C');
    $criteri['mese'] = $this->reqstack->getSession()->get('/APP/ROUTE/avvisi_gestione/mese', null);
    $criteri['oggetto'] = $this->reqstack->getSession()->get('/APP/ROUTE/avvisi_gestione/oggetto', '');
    $autore = ($criteri['autore'] ? $this->em->getRepository(Docente::class)->find($criteri['autore']) : null);
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $this->reqstack->getSession()->get('/APP/ROUTE/avvisi_gestione/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/avvisi_gestione/pagina', $pagina);
    }
    // form filtro
    $opzStaff = $this->em->getRepository(Staff::class)->opzioni();
    $opzTipi = ['label.avvisi_tipo_C' => 'C', 'label.avvisi_tipo_E' => 'E', 'label.avvisi_tipo_U' => 'U',
      'label.avvisi_tipo_A' => 'A', 'label.avvisi_tipo_I' => 'I'];
    $opzMesi = ['Settembre' => 9, 'Ottobre' => 10, 'Novembre' => 11 , 'Dicembre' => 12, 'Gennaio' => 1,
      'Febbraio' => 2, 'Marzo' => 3, 'Aprile' => 4, 'Maggio' => 5, 'Giugno' => 6, 'Luglio' => 7, 'Agosto' => 8];
    $form = $this->createForm(AvvisoFiltroType::class, null, ['form_mode' => 'gestione',
      'values' => [$autore, $opzStaff, $criteri['tipo'], $opzTipi, $criteri['mese'], $opzMesi,
      $criteri['oggetto']]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $criteri['autore'] = (is_object($form->get('autore')->getData()) ? $form->get('autore')->getData()->getId() : 0);
      $criteri['tipo'] = $form->get('tipo')->getData();
      $criteri['mese'] = $form->get('mese')->getData();
      $criteri['oggetto'] = $form->get('oggetto')->getData();
      $pagina = 1;
      $this->reqstack->getSession()->set('/APP/ROUTE/avvisi_gestione/autore', $criteri['autore']);
      $this->reqstack->getSession()->set('/APP/ROUTE/avvisi_gestione/tipo', $criteri['tipo']);
      $this->reqstack->getSession()->set('/APP/ROUTE/avvisi_gestione/mese', $criteri['mese']);
      $this->reqstack->getSession()->set('/APP/ROUTE/avvisi_gestione/oggetto', $criteri['oggetto']);
      $this->reqstack->getSession()->set('/APP/ROUTE/avvisi_gestione/pagina', $pagina);
    }
    // informazioni di visualizzazione
    $info['pagina'] = $pagina;
    $info['mesi'] = array_flip($opzMesi);
    // recupera dati
    $dati = $com->listaAvvisi($criteri, $pagina, $this->getUser());
    // mostra la pagina di risposta
    return $this->render('avvisi/gestione.html.twig', [
      'pagina_titolo' => 'page.avvisi_gestione',
      'form' => $form,
      'form_help' => null,
      'form_success' => null,
      'dati' => $dati,
      'info' => $info]);
  }

  /**
   * Aggiunge o modifica un avviso generico
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param MessageBusInterface $msg Gestione delle notifiche
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param ComunicazioniUtil $com Funzioni di utilità per la gestione delle comunicazioni
   * @param LogHandler $dblogger Gestore dei log su database
   * @param Avviso|null $avviso Avviso da modificare o valore nullo per crearne uno nuovo
   *
   * @return Response Pagina di risposta
   */
  #[Route(path: '/avvisi/edit/{avviso}', name: 'avvisi_edit', requirements: ['avviso' => '\d+'], defaults: ['avviso' => '0'], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_STAFF')]
  public function edit(Request $request, TranslatorInterface $trans, MessageBusInterface $msg, RegistroUtil $reg,
                       ComunicazioniUtil $com, LogHandler $dblogger,
                       #[MapEntity] ?Avviso $avviso=null
                       ): Response {
    // inizializza
    $dati = [];
    $var_sessione = '/APP/FILE/avvisi_edit/allegati';
    $dir = $this->getParameter('dir_avvisi').'/';
    // controlla azione
    $edit = false;
    if ($avviso) {
      // azione edit
      $edit = true;
    } else {
      // azione add
      $avviso = (new Avviso())
        ->setData(new DateTime('today'))
        ->setTipo('C');
      // se l'utente ha una sede, la imposta predefinita
      if ($this->getUser()->getSede()) {
        $avviso->addSede($this->getUser()->getSede());
      }
      $this->em->persist($avviso);
    }
    // controllo permessi
    if (!$com->azioneAvviso(($edit ? 'edit' : 'add'), $avviso->getData(), $this->getUser(),
        $edit ? $avviso : null)) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // imposta autore dell'avviso
    $avviso->setAutore($this->getUser());
    // legge file
    $allegati = [];
    if ($request->isMethod('POST')) {
      // pagina inviata
      foreach ($this->reqstack->getSession()->get($var_sessione, []) as $f) {
        if ($f['type'] != 'removed') {
          // aggiunge allegato
          $allegati[] = $f;
        }
      }
    } else {
      // pagina iniziale
      foreach ($avviso->getAllegati() as $k=>$file) {
        $allegati[$k]['type'] = 'existent';
        $allegati[$k]['name'] = $file->getTitolo();
        $allegati[$k]['temp'] = $file->getFile();
        $allegati[$k]['ext'] = $file->getEstensione();
        $allegati[$k]['size'] = $file->getDimensione();
      }
      // modifica dati sessione
      $this->reqstack->getSession()->remove($var_sessione);
      $this->reqstack->getSession()->set($var_sessione, $allegati);
      // elimina file temporanei
      $fs = new Filesystem();
      $finder = new Finder();
      $finder->files()->in($this->getParameter('dir_tmp'))->date('< 1 day ago');
      foreach ($finder as $f) {
        $fs->remove($f);
      }
    }
    // informazioni da visualizzare sui destinatari
    $dati = $com->infoDestinatari($avviso);
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
    $form = $this->createForm(AvvisoType::class, $avviso, ['form_mode' => 'generico',
      'return_url' => $this->generateUrl('avvisi_gestione'),
      'values' => [$opzioniSedi, ($avviso->getAnnotazioni()->count() > 0), $opzioniClassi, $opzioniMaterie,
      $opzioniClassi2]]);
    $form->handleRequest($request);
    if ($form->isSubmitted()) {
      $errore = $com->validaAvviso($avviso, $this->getUser(), $form, $reg, count($allegati) > 0);
      if ($errore) {
        // errore presente
        $form->addError(new FormError($trans->trans($errore)));
      }
      if ($form->isValid()) {
        // aggiunge documento e allegati
        $com->aggiungiAllegati($avviso, $dir, $this->reqstack->getSession()->get($var_sessione, []));
        // gestione destinatari
        if ($edit) {
          // cancella destinatari
          $com->cancellaDestinatari($avviso);
        }
        // imposta destinatari
        $com->impostaDestinatari($avviso);
        // annotazione
        $com->annotazioneAvviso($avviso, $edit, $form->get('creaAnnotazione')->getData());
        // ok: memorizzazione e log
        $dblogger->logAzione('AVVISI', $edit ? 'Modifica avviso generico' : 'Crea avviso generico');
        // notifica con attesa di mezzora
        $notifica = new AvvisoMessage($avviso->getId());
        if (!$edit || !NotificaMessageHandler::update($this->em, $notifica->getTag(), 'avviso', 1800)) {
          // inserisce avviso (nuovo o modificato) in coda notifiche
          $msg->dispatch($notifica, [new DelayStamp(1800000)]);
        }
        // redirezione
        return $this->redirectToRoute('avvisi_gestione');
      }
    }
    // mostra la pagina di risposta
    return $this->render('avvisi/edit.html.twig', [
      'pagina_titolo' => 'page.staff_avvisi',
      'form' => $form,
      'form_title' => ($edit ? 'title.modifica_avviso' : 'title.nuovo_avviso'),
      'allegati' => $allegati,
      'dati' => $dati]);
  }

  /**
   * Mostra i dettagli di un avviso
   *
   * @param ComunicazioniUtil $com Funzioni di utilità per le comunicazioni
   * @param Avviso $avviso Avviso di cui fornire informazioni
   *
   * @return Response Pagina di risposta
   */
  #[Route(path: '/avvisi/dettagli/gestione/{avviso}', name: 'avvisi_dettagli_gestione', requirements: ['avviso' => '\d+'], methods: ['GET'])]
  #[IsGranted('ROLE_STAFF')]
  public function dettagliGestione(ComunicazioniUtil $com,
                                   #[MapEntity] Avviso $avviso
                                   ): Response {
    // inizializza
    $mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    // legge dati
    $dati = $com->dettagli($avviso);
    // visualizza pagina
    return $this->render('avvisi/scheda_dettagli_gestione.html.twig', [
      'avviso' => $avviso,
      'mesi' => $mesi,
      'dati' => $dati]);
  }

  /**
   * Cancella avviso
   *
   * @param ComunicazioniUtil $com Funzioni di utilità per la gestione delle comunicazioni
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param LogHandler $dblogger Gestore dei log su database
   * @param Avviso $avviso Avviso da cancellare
   *
   * @return Response Pagina di risposta
   */
  #[Route(path: '/avvisi/delete/{avviso}', name: 'avvisi_delete', requirements: ['avviso' => '\d+'], methods: ['GET'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function delete(ComunicazioniUtil $com, RegistroUtil $reg, LogHandler $dblogger,
                         #[MapEntity] Avviso $avviso
                         ): Response {
    // controllo permessi
    if (!$com->azioneAvviso('delete', $avviso->getData(), $this->getUser(), $avviso)) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    if ($avviso->getAnnotazioni()->count() > 0) {
      $a = $avviso->getAnnotazioni()[0];
      if (!$reg->azioneAnnotazione('delete', $a->getData(), $this->getUser(), $a->getClasse(), $a)) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
    }
    // conserva tipo avviso
    $tipo = $avviso->getTipo();
    // cancella destinatari
    $com->cancellaDestinatari($avviso);
    // cancella annotazioni
    foreach ($avviso->getAnnotazioni() as $annotazione) {
      $this->em->remove($annotazione);
    }
    // cancella allegati
    foreach ($avviso->getAllegati() as $allegato) {
      $this->em->remove($allegato);
    }
    // cancella avviso
    $avvisoVecchioId = $avviso->getId();
    $this->em->remove($avviso);
    // memorizzazione e log
    $dblogger->logAzione('AVVISI', 'Cancella avviso');
    // cancella file
    $dir = $this->getParameter('dir_avvisi');
    foreach ($avviso->getAllegati() as $allegato) {
      unlink($dir.'/'.$allegato->getFile().'.'.$allegato->getEstensione());
    }
    // rimuove notifica
    NotificaMessageHandler::delete($this->em, (new AvvisoMessage($avvisoVecchioId))->getTag());
    // redirezione
    return $this->redirectToRoute($tipo == 'O' ? 'avvisi_coordinatore' :
      ($tipo == 'V' || $tipo == 'P' ? 'avvisi_agenda' : 'avvisi_gestione'));
  }

  /**
   * Aggiunge o modifica un avviso per le classi: modifica di orario di ingresso o di uscita, segnalazione attività
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param MessageBusInterface $msg Gestione delle notifiche
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param ComunicazioniUtil $com Funzioni di utilità per la gestione delle comunicazioni
   * @param LogHandler $dblogger Gestore dei log su database
   * @param string $tipo Tipo di avviso per le classi [E=entrata, U=uscita, A=attivita]
   * @param Avviso|null $avviso Avviso da modificare o valore nullo per crearne uno nuovo
   *
   * @return Response Pagina di risposta
   */
  #[Route(path: '/avvisi/edit/classi/{tipo}/{avviso}', name: 'avvisi_edit_classi', requirements: ['tipo' => 'E|U|A', 'avviso' => '\d+'], defaults: ['avviso' => '0'], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_STAFF')]
  public function editClassi(Request $request, TranslatorInterface $trans, MessageBusInterface $msg,
                             RegistroUtil $reg, ComunicazioniUtil $com, LogHandler $dblogger, string $tipo,
                             #[MapEntity] ?Avviso $avviso=null
                             ): Response {
    // controlla azione
    $edit = false;
    $valori = [];
    if ($avviso) {
      // azione edit
      $edit = true;
      $tipo = $avviso->getTipo();
      switch ($tipo) {
        case 'E':
          $valori['ora'] = DateTime::CreateFromFormat('H:i', $avviso->getSostituzioni()['{ora}']);
          $valori['note'] = $avviso->getSostituzioni()['{note}'];
          break;
        case 'U':
          $valori['ora'] = DateTime::CreateFromFormat('H:i', $avviso->getSostituzioni()['{ora}']);
          $valori['note'] = $avviso->getSostituzioni()['{note}'];
          break;
        case 'A':
          $valori['inizio'] = DateTime::CreateFromFormat('H:i', $avviso->getSostituzioni()['{inizio}']);
          $valori['fine'] = DateTime::CreateFromFormat('H:i', $avviso->getSostituzioni()['{fine}']);
          $valori['attivita'] = $avviso->getSostituzioni()['{attivita}'];
          break;
      }
    } else {
      // azione add
      $sede = $this->getUser()->getSede() ??
        $this->em->getRepository(Sede::class)->findOneBy([], ['ordinamento' => 'ASC']);
      $data = $this->em->getRepository(Festivita::class)->giornoSuccessivo(new DateTime('today'));
      $orario = $this->em->getRepository(ScansioneOraria::class)->orarioGiorno($data->format('w'),
        $this->em->getRepository(Orario::class)->orarioSede($sede));
      switch ($tipo) {
        case 'E':
          $titolo = 'message.avviso_entrata_oggetto';
          $testo = 'message.avviso_entrata_testo';
          $valori['ora'] = $orario[1]['inizio'] ?? new DateTime('9:30');  // inizio seconda ora
          $valori['note'] = '';
          break;
        case 'U':
          $titolo = 'message.avviso_uscita_oggetto';
          $testo = 'message.avviso_uscita_testo';
          $valori['ora'] = end($orario)['inizio'] ?? new DateTime('13:30');  // inizio ultima ora
          $valori['note'] = '';
          break;
        case 'A':
          $titolo = 'message.avviso_attivita_oggetto';
          $testo = 'message.avviso_attivita_testo';
          $valori['inizio'] = $orario[0]['inizio'] ?? new DateTime('8:30');  // inizio prima ora
          $valori['fine'] = end($orario)['fine'] ?? new DateTime('14:30');  // fine ultima ora
          $valori['attivita'] = '';
          break;
      }
      $avviso = (new Avviso())
        ->setTipo($tipo)
        ->setData($data)
        ->setTitolo($trans->trans($titolo))
        ->setTesto($trans->trans($testo))
        ->setSpeciali('D')  // DSGA
        ->setAta('ATC')     // ATA
        ->setDocenti('C')   // DOCENTI classe
        ->setGenitori('C')  // GENITORI classe
        ->setAlunni('C');   // ALUNNI classe
      // se l'utente ha una sede, la imposta predefinita
      if ($this->getUser()->getSede()) {
        $avviso->addSede($this->getUser()->getSede());
      }
      $this->em->persist($avviso);
    }
    // controllo permessi
    if (!$com->azioneAvviso(($edit ? 'edit' : 'add'), $avviso->getData(), $this->getUser(),
        $edit ? $avviso : null)) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // imposta autore dell'avviso
    $avviso->setAutore($this->getUser());
    // form di inserimento
    $setSede = $this->getUser()->getSede() ? $this->getUser()->getSede()->getId() : null;
    if ($setSede) {
      $sede = $this->getUser()->getSede();
      $opzioniSedi[$sede->getNomeBreve()] = $sede;
    } else {
      $opzioniSedi = $this->em->getRepository(Sede::class)->opzioni();
    }
    $opzioniClassi = $this->em->getRepository(Classe::class)->opzioni($setSede, true, false, true);
    $form = $this->createForm(AvvisoType::class, $avviso, ['form_mode' => $tipo == 'A' ? 'attivita' : 'orario',
      'return_url' => $this->generateUrl('avvisi_gestione'),
      'values' => [$tipo, $valori, $opzioniSedi, $opzioniClassi]]);
    $form->handleRequest($request);
    if ($form->isSubmitted()) {
      // imposta classi
      $listaClassi = array_map(fn($o) => $o->getId(), $form->get('classi')->getData());
      $avviso->setFiltroDocenti($listaClassi);
      $avviso->setFiltroGenitori($listaClassi);
      $avviso->setFiltroAlunni($listaClassi);
      // imposta sostituzioni
      $sostituzioni = [];
      $sostituzioni['{data}'] = $avviso->getData() ? $avviso->getData()->format('d/m/Y') : '';
      switch ($tipo) {
        case 'E':
        case 'U':
          $sostituzioni['{ora}'] = $form->get('ora')->getData() ? $form->get('ora')->getData()->format('H:i') : '';
          $sostituzioni['{note}'] = $form->get('note')->getData() ?? '';
          break;
        case 'A':
          $sostituzioni['{inizio}'] = $form->get('inizio')->getData() ? $form->get('inizio')->getData()->format('H:i') : '';
          $sostituzioni['{fine}'] = $form->get('fine')->getData() ? $form->get('fine')->getData()->format('H:i') : '';
          $sostituzioni['{attivita}'] = $form->get('attivita')->getData() ?? '';
          break;
      }
      $avviso->setSostituzioni($sostituzioni);
      // controlla errori
      $errore = $com->validaAvvisoClassi($avviso, $this->getUser(), $reg);
      if ($errore) {
        // errore presente
        $form->addError(new FormError($trans->trans($errore)));
      }
      // modifica dati
      if ($form->isValid()) {
        // gestione destinatari
        if ($edit) {
          // cancella destinatari
          $com->cancellaDestinatari($avviso);
        }
        // imposta destinatari
        $com->impostaDestinatari($avviso);
        // annotazione
        $com->annotazioneAvviso($avviso, $edit, true);
        // ok: memorizzazione e log
        $messaggio = ($edit ? 'Modifica' : 'Crea').' avviso '.
          ($tipo == 'A' ? 'attività' : ($tipo == 'E' ? 'entrata' : 'uscita'));
        $dblogger->logAzione('AVVISI', $messaggio);
        // notifica con attesa di mezzora
        $notifica = new AvvisoMessage($avviso->getId());
        if (!$edit || !NotificaMessageHandler::update($this->em, $notifica->getTag(), 'avviso', 1800)) {
          // inserisce avviso (nuovo o modificato) in coda notifiche
          $msg->dispatch($notifica, [new DelayStamp(1800000)]);
        }
        // redirezione
        return $this->redirectToRoute('avvisi_gestione');
      }
    }
    // mostra la pagina di risposta
    switch ($tipo) {
      case 'E':
        $pagina = 'edit_orario.html.twig';
        $paginaTitolo = 'page.staff_avvisi_entrate';
        $paginaForm = ($edit ? 'title.modifica_avviso_entrate' : 'title.nuovo_avviso_entrate');
        break;
      case 'U':
        $pagina = 'edit_orario.html.twig';
        $paginaTitolo = 'page.staff_avvisi_uscite';
        $paginaForm = ($edit ? 'title.modifica_avviso_uscite' : 'title.nuovo_avviso_uscite');
        break;
      case 'A':
        $pagina = 'edit_attivita.html.twig';
        $paginaTitolo = 'page.staff_avvisi_attivita';
        $paginaForm = ($edit ? 'title.modifica_avviso_attivita' : 'title.nuovo_avviso_attivita');
        break;
    }
    return $this->render('avvisi/'.$pagina, [
      'pagina_titolo' => $paginaTitolo,
      'form' => $form,
      'form_title' => $paginaForm,
      'tpl' => $avviso->getTesto()]);
  }

  /**
   * Aggiunge o modifica un avviso personale
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param MessageBusInterface $msg Gestione delle notifiche
   * @param ComunicazioniUtil $com Funzioni di utilità per la gestione delle comunicazioni
   * @param LogHandler $dblogger Gestore dei log su database
   * @param Avviso|null $avviso Avviso da modificare o valore nullo per crearne uno nuovo
   *
   * @return Response Pagina di risposta
   */
  #[Route(path: '/avvisi/edit/personali/{avviso}', name: 'avvisi_edit_personali', requirements: ['avviso' => '\d+'], defaults: ['avviso' => '0'], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_STAFF')]
  public function editPersonali(Request $request, TranslatorInterface $trans, MessageBusInterface $msg,
                                ComunicazioniUtil $com, LogHandler $dblogger,
                                #[MapEntity] ?Avviso $avviso=null
                                ): Response {
    // controlla azione
    $edit = false;
    if ($avviso) {
      // azione edit
      $edit = true;
    } else {
      // azione add
      $docente = ($this->getUser()->getSesso() == 'M' ? ' ' : 'la ').$this->getUser();
      $avviso = (new Avviso())
        ->setTipo('I')
        ->setData(new DateTime('today'))
        ->setGenitori('U')
        ->setTitolo($trans->trans('message.avviso_individuale_oggetto', ['docente' => $docente]));
      if ($this->getUser()->getSede()) {
        $avviso->addSede($this->getUser()->getSede());
      }
      $this->em->persist($avviso);
    }
    // controllo permessi
    if (!$com->azioneAvviso(($edit ? 'edit' : 'add'), $avviso->getData(), $this->getUser(),
        $edit ? $avviso : null)) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // imposta autore dell'avviso
    $avviso->setAutore($this->getUser());
    // informazioni da visualizzare sui destinatari
    $dati = $com->infoDestinatari($avviso);
    // form di inserimento
    $setSede = $this->getUser()->getSede() ? $this->getUser()->getSede()->getId() : null;
    if ($setSede) {
      $sede = $this->getUser()->getSede();
      $opzioniSedi[$sede->getNomeBreve()] = $sede;
    } else {
      $opzioniSedi = $this->em->getRepository(Sede::class)->opzioni();
    }
    $opzioniClassi = $this->em->getRepository(Classe::class)->opzioni($setSede, false, true, true);
    $form = $this->createForm(AvvisoType::class, $avviso, ['form_mode' => 'personale',
      'return_url' => $this->generateUrl('avvisi_gestione'),
      'values' => [$opzioniSedi, $opzioniClassi]]);
    $form->handleRequest($request);
    if ($form->isSubmitted()) {
      $errore = $com->validaAvvisoPersonali($avviso, $this->getUser());
      if ($errore) {
        // errore presente
        $form->addError(new FormError($trans->trans($errore)));
      }
      if ($form->isValid()) {
        // gestione destinatari
        if ($edit) {
          // cancella destinatari
          $com->cancellaDestinatari($avviso);
        }
        // imposta destinatari
        $com->impostaDestinatari($avviso);
        // ok: memorizzazione e log
        $dblogger->logAzione('AVVISI', $edit ? 'Modifica comunicazione personale' : 'Crea comunicazione personale');
        // notifica con attesa di mezzora
        $notifica = new AvvisoMessage($avviso->getId());
        if (!$edit || !NotificaMessageHandler::update($this->em, $notifica->getTag(), 'avviso', 1800)) {
          // inserisce avviso (nuovo o modificato) in coda notifiche
          $msg->dispatch($notifica, [new DelayStamp(1800000)]);
        }
        // redirezione
        return $this->redirectToRoute('avvisi_gestione');
      }
    }
    // mostra la pagina di risposta
    return $this->render('avvisi/edit_personali.html.twig', [
      'pagina_titolo' => 'page.staff_avvisi_individuali',
      'form' => $form,
      'form_title' => ($edit ? 'title.modifica_avviso_individuale' : 'title.nuovo_avviso_individuale'),
      'dati' => $dati]);
  }

  /**
   * Archivio degli avvisi degli anni precedenti
   *
   * @param Request $request Pagina richiesta
   * @param int $pagina Numero di pagina per l'elenco da visualizzare
   *
   * @return Response Pagina di risposta
   */
  #[Route(path: '/avvisi/archivio/{pagina}', name: 'avvisi_archivio', requirements: ['pagina' => '\d+'], defaults: ['pagina' => '0'], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_STAFF')]
  public function archivio(Request $request, int $pagina): Response {
    // inizializza
    $mesi = ['Settembre' => 9, 'Ottobre' => 10, 'Novembre' => 11 , 'Dicembre' => 12, 'Gennaio' => 1,
      'Febbraio' => 2, 'Marzo' => 3, 'Aprile' => 4, 'Maggio' => 5, 'Giugno' => 6, 'Luglio' => 7, 'Agosto' => 8];
    $info = [];
    // recupera criteri dalla sessione
    $criteri = [];
    $criteri['anno'] = $this->reqstack->getSession()->get('/APP/ROUTE/avvisi_archivio/anno');
    $criteri['autore'] = $this->reqstack->getSession()->get('/APP/ROUTE/avvisi_archivio/autore', null);
    $criteri['tipo'] = $this->reqstack->getSession()->get('/APP/ROUTE/avvisi_archivio/tipo', null);
    $criteri['mese'] = $this->reqstack->getSession()->get('/APP/ROUTE/avvisi_archivio/mese', null);
    $criteri['oggetto'] = $this->reqstack->getSession()->get('/APP/ROUTE/avvisi_archivio/oggetto', '');
    $autore = ($criteri['autore'] ? $this->em->getRepository(Docente::class)->find($criteri['autore']) : null);
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $this->reqstack->getSession()->get('/APP/ROUTE/avvisi_archivio/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/avvisi_archivio/pagina', $pagina);
    }
    // crea lista anni
    $anni = $this->em->getRepository(Avviso::class)->anniScolastici();
    if (empty($criteri['anno']) && count($anni) > 0) {
      $criteri['anno'] = array_values($anni)[0];
    }
    // form filtro
    $opzStaff = $this->em->getRepository(Staff::class)->opzioni();
    $opzTipi = ['label.avvisi_tipo_C' => 'C', 'label.avvisi_tipo_E' => 'E', 'label.avvisi_tipo_U' => 'U',
      'label.avvisi_tipo_A' => 'A', 'label.avvisi_tipo_I' => 'I'];
    $form = $this->createForm(AvvisoFiltroType::class, null, ['form_mode' => 'archivio',
      'values' => [$criteri['anno'], $anni, $autore, $opzStaff, $criteri['tipo'], $opzTipi,
      $criteri['mese'], $mesi, $criteri['oggetto']]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $criteri['anno'] = $form->get('anno')->getData();
      $criteri['autore'] = (is_object($form->get('autore')->getData()) ? $form->get('autore')->getData()->getId() : 0);
      $criteri['tipo'] = $form->get('tipo')->getData();
      $criteri['mese'] = $form->get('mese')->getData();
      $criteri['oggetto'] = $form->get('oggetto')->getData();
      $pagina = 1;
      $this->reqstack->getSession()->set('/APP/ROUTE/avvisi_archivio/anno', $criteri['anno']);
      $this->reqstack->getSession()->set('/APP/ROUTE/avvisi_archivio/autore', $criteri['autore']);
      $this->reqstack->getSession()->set('/APP/ROUTE/avvisi_archivio/tipo', $criteri['tipo']);
      $this->reqstack->getSession()->set('/APP/ROUTE/avvisi_archivio/mese', $criteri['mese']);
      $this->reqstack->getSession()->set('/APP/ROUTE/avvisi_archivio/oggetto', $criteri['oggetto']);
      $this->reqstack->getSession()->set('/APP/ROUTE/avvisi_archivio/pagina', $pagina);
    }
    // informazioni di visualizzazione
    $info['pagina'] = $pagina;
    $info['mesi'] = $mesi;
    // recupera dati
    $dati = $this->em->getRepository(Avviso::class)->listaArchivio($criteri, $pagina);
    // mostra la pagina di risposta
    return $this->render('avvisi/archivio.html.twig', [
      'pagina_titolo' => 'page.staff_avvisi_archivio',
      'form' => $form,
      'form_help' => null,
      'form_success' => null,
      'dati' => $dati,
      'info' => $info]);
  }

  /**
   * Visualizza gli avvisi in bacheca
   *
   * @param Request $request Pagina richiesta
   * @param int $pagina Numero di pagina per l'elenco da visualizzare
   *
   * @return Response Pagina di risposta
   */
  #[Route(path: '/avvisi/bacheca/{pagina}', name: 'avvisi_bacheca', requirements: ['pagina' => '\d+'], defaults: ['pagina' => '0'], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_UTENTE')]
  public function bacheca(Request $request, int $pagina): Response {
    // inizializza
    $mesi = ['Settembre' => 9, 'Ottobre' => 10, 'Novembre' => 11 , 'Dicembre' => 12, 'Gennaio' => 1,
      'Febbraio' => 2, 'Marzo' => 3, 'Aprile' => 4, 'Maggio' => 5, 'Giugno' => 6, 'Luglio' => 7, 'Agosto' => 8];
    $info = [];
    // recupera criteri dalla sessione
    $criteri = [];
    $criteri['visualizza'] = $this->reqstack->getSession()->get('/APP/ROUTE/avvisi_bacheca/visualizza', 'P');
    $criteri['mese'] = $this->reqstack->getSession()->get('/APP/ROUTE/avvisi_bacheca/mese', null);
    $criteri['oggetto'] = $this->reqstack->getSession()->get('/APP/ROUTE/avvisi_bacheca/oggetto', '');
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $this->reqstack->getSession()->get('/APP/ROUTE/avvisi_bacheca/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/avvisi_bacheca/pagina', $pagina);
    }
    // lista visualizzazione
    $listaVisualizzazione = ['label.avvisi_da_leggere' => 'D', 'label.avvisi_tutti' => 'P'];
    // form filtro
    $form = $this->createForm(AvvisoFiltroType::class, null, ['form_mode' => 'bacheca',
      'values' => [$criteri['visualizza'], $listaVisualizzazione, $criteri['mese'], $mesi, $criteri['oggetto']]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $criteri['visualizza'] = $form->get('visualizza')->getData();
      $criteri['mese'] = $form->get('mese')->getData();
      $criteri['oggetto'] = $form->get('oggetto')->getData();
      $pagina = 1;
      $this->reqstack->getSession()->set('/APP/ROUTE/avvisi_bacheca/visualizza', $criteri['visualizza']);
      $this->reqstack->getSession()->set('/APP/ROUTE/avvisi_bacheca/mese', $criteri['mese']);
      $this->reqstack->getSession()->set('/APP/ROUTE/avvisi_bacheca/oggetto', $criteri['oggetto']);
      $this->reqstack->getSession()->set('/APP/ROUTE/avvisi_bacheca/pagina', $pagina);
    }
    // informazioni di visualizzazione
    $info['pagina'] = $pagina;
    $info['mesi'] = array_flip($mesi);
    // recupera dati
    $dati = $this->em->getRepository(Avviso::class)->listaBacheca($criteri, $pagina, $this->getUser());
    // mostra la pagina di risposta
    return $this->render(
      'avvisi/bacheca.html.twig', ['pagina_titolo' => 'page.bacheca_avvisi',
      'form' => $form,
      'form_help' => null,
      'form_success' => null,
      'dati' => $dati,
      'info' => $info]);
  }

  /**
   * Mostra gli avvisi destinati alle classi
   *
   * @param Classe $classe Classe di riferimento
   *
   * @return Response Pagina di risposta
   */
  #[Route(path: '/avvisi/classe/{classe}', name: 'avvisi_classe', requirements: ['classe' => '\d+'], methods: ['GET'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function classe(
                         #[MapEntity] Classe $classe
                         ): Response {
    // inizializza
    $mesi = ['Settembre' => 9, 'Ottobre' => 10, 'Novembre' => 11 , 'Dicembre' => 12, 'Gennaio' => 1,
      'Febbraio' => 2, 'Marzo' => 3, 'Aprile' => 4, 'Maggio' => 5, 'Giugno' => 6, 'Luglio' => 7, 'Agosto' => 8];
    $info = [];
    // informazioni di visualizzazione
    $info['classe'] = $classe;
    $info['mesi'] = array_flip($mesi);
    // legge dati
    $dati = $this->em->getRepository(Avviso::class)->classe($classe);
    // visualizza pagina
    return $this->render('avvisi/scheda_classe.html.twig', [
      'dati' => $dati,
	    'info' => $info]);
  }

  /**
   * Conferma la lettura dell'avviso destinato alla classe
   *
   * @param Classe $classe Classe di riferimento
   * @param Avviso|null $avviso Avviso per lettura di un singolo avviso o valore nullo per tutti gli avvisi della classe
   *
   * @return Response Pagina di risposta
   */
  #[Route(path: '/avvisi/classe/firma/{classe}/{avviso}', name: 'avvisi_classe_firma', requirements: ['classe' => '\d+', 'avviso' => '\d+'], defaults: ['avviso' => 0], methods: ['GET'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function classeFirma(
                              #[MapEntity] Classe $classe,
                              #[MapEntity] ?Avviso $avviso=null
                              ): Response {
    // inizializza
    $lista = [];
    // controlla avvisi da firmare
    if ($avviso) {
      // solo avviso indicato
      $lista = [$avviso];
    } else {
      // tutti gli avvisi della classe
      $lista = $this->em->getRepository(Avviso::class)->classe($classe);
    }
    // firma avvisi
    foreach ($lista as $av) {
      $lista = $this->em->getRepository(ComunicazioneClasse::class)->firmaClasse($classe, $av);
    }
    // redirect
    return $this->redirectToRoute('lezioni');
  }

   /**
   * Esegue il download di un allegato di un avviso.
   *
   * @param ComunicazioniUtil $com Funzioni di utilità per le circolari
   * @param Avviso $avviso Avviso con allegati
   * @param int $allegato Numero dell'allegato (0 per il primo)
   * @param string $tipo Tipo di risposta (V=visualizza, D=download)
   *
   * @return Response Documento inviato in risposta
   */
  #[Route(path: '/avvisi/download/{avviso}/{allegato}/{tipo}', name: 'avvisi_download', requirements: ['avviso' => '\d+', 'allegato' => '\d+', 'tipo' => 'V|D'], defaults: ['allegato' => 0,'tipo' => 'V'], methods: ['GET'])]
  #[IsGranted('ROLE_UTENTE')]
  public function download(ComunicazioniUtil $com,
                           #[MapEntity] Avviso $avviso,
                           int $allegato, string $tipo): Response {
    // controllo allegato
    if ($allegato < 0 || $allegato >= count($avviso->getAllegati())) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo permesso lettura
    if (!$com->permessoLettura($this->getUser(), $avviso)) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // segna lettura e memorizza su db
    $com->leggeUtente($this->getUser(), $avviso);
    $this->em->flush();
    // invia il file
    $file = $avviso->getAllegati()[$allegato];
    $dir = $this->getParameter('dir_avvisi').($avviso->getStato() == 'A' ? '/'.$avviso->getAnno() : '');
    $nomefile = $dir.'/'.$file->getFile().'.'.$file->getEstensione();
    return $this->file($nomefile, $file->getNome().'.'.$file->getEstensione(),
      ($tipo == 'V' ? ResponseHeaderBag::DISPOSITION_INLINE : ResponseHeaderBag::DISPOSITION_ATTACHMENT));
  }

  /**
   * Conferma la lettura dell'avviso da parte dell'utente
   *
   * @param Avviso $avviso Avviso in lettura
   *
   * @return JsonResponse Informazioni di risposta
   */
  #[Route(path: '/avvisi/legge/{avviso}', name: 'avvisi_legge', requirements: ['avviso' => '\d+'], defaults: ['avviso' => '0'], methods: ['GET'])]
  #[IsGranted('ROLE_UTENTE')]
  public function legge(
                        #[MapEntity] ?Avviso $avviso=null
                        ): JsonResponse {
    // firma
    $this->em->getRepository(ComunicazioneUtente::class)->legge($avviso, $this->getUser());
    // restituisce dati
    return new JsonResponse(['stato' => 'ok']);
  }

   /**
   * Gestione degli avvisi inseriti dal coordinatore.
   *
   * @param ComunicazioniUtil $com Funzioni di utilità per la gestione delle comunicazioni
   * @param int $pagina Numero di pagina per l'elenco da visualizzare
   *
   * @return Response Pagina di risposta
   */
  #[Route(path: '/avvisi/coordinatore/{pagina}', name: 'avvisi_coordinatore', requirements: ['pagina' => '\d+'], defaults: ['pagina' => '0'], methods: ['GET'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function coordinatore(ComunicazioniUtil $com, int $pagina): Response {
    // inizializza
    $dati = [];
    $info = [];
    // parametro pagina
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $this->reqstack->getSession()->get('/APP/ROUTE/avvisi_coordinatore/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/avvisi_coordinatore/pagina', $pagina);
    }
    // parametro classe
    $classe = $this->reqstack->getSession()->get('/APP/DOCENTE/classe_coordinatore');
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
        $classi = explode(',', (string) $this->reqstack->getSession()->get('/APP/DOCENTE/coordinatore', ''));
        if (!in_array($classe->getId(), $classi)) {
          // errore
          throw $this->createNotFoundException('exception.invalid_params');
        }
      }
      // recupera dati
      $dati = $com->listaAvvisiCoordinatore($pagina, $this->getUser(), $classe);
    }
    // informazioni di visualizzazione
    $info['pagina'] = $pagina;
    $info['classe'] = $classe;
    // visualizza pagina
    return $this->render('avvisi/coordinatore.html.twig', [
      'pagina_titolo' => 'page.coordinatore_avvisi',
      'dati' => $dati,
      'info' => $info]);
  }

  /**
   * Aggiunge o modifica un avviso inserito dal coordinatore
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param MessageBusInterface $msg Gestione delle notifiche
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param ComunicazioniUtil $com Funzioni di utilità per la gestione delle comunicazioni
   * @param LogHandler $dblogger Gestore dei log su database
   * @param Avviso|null $avviso Avviso da modificare o valore nullo per crearne uno nuovo
   *
   * @return Response Pagina di risposta
   */
  #[Route(path: '/avvisi/edit/coordinatore/{avviso}', name: 'avvisi_edit_coordinatore', requirements: ['avviso' => '\d+'], defaults: ['avviso' => '0'], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function editCoordinatore(Request $request, TranslatorInterface $trans, MessageBusInterface $msg,
                                   RegistroUtil $reg, ComunicazioniUtil $com, LogHandler $dblogger,
                                   #[MapEntity] ?Avviso $avviso=null
                                   ): Response {
    $info = [];
    // parametro classe
    $classe = $this->reqstack->getSession()->get('/APP/DOCENTE/classe_coordinatore');
    // controllo classe
    $classe = $this->em->getRepository(Classe::class)->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo accesso alla funzione
    if (!($this->getUser() instanceOf Staff) && !($this->getUser() instanceOf Preside)) {
      // coordinatore
      $classi = explode(',', (string) $this->reqstack->getSession()->get('/APP/DOCENTE/coordinatore', ''));
      if (!in_array($classe->getId(), $classi)) {
        // errore
        throw $this->createNotFoundException('exception.invalid_params');
      }
    }
    // controlla azione
    $edit = false;
    if ($avviso) {
      // azione edit
      $edit = true;
      if ($avviso->getTipo() != 'O') {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      // destinatari solo di classe
      if ($avviso->getDocenti() == 'C') {
        $avviso->setDocenti('T');
        $avviso->setFiltroDocenti([]);
      }
      if ($avviso->getGenitori() == 'C') {
        $avviso->setGenitori('T');
        $avviso->setFiltroGenitori([]);
      }
      if ($avviso->getAlunni() == 'C') {
        $avviso->setAlunni('T');
        $avviso->setFiltroAlunni([]);
      }
    } else {
      // azione add
      $avviso = (new Avviso())
        ->setTipo('O')
        ->setData(new DateTime('today'))
        ->setTitolo($trans->trans('message.avviso_coordinatore_oggetto', ['classe' => ''.$classe]))
        ->addSede($classe->getSede())
        ->setClasse($classe);
      $this->em->persist($avviso);
    }
    // controllo permessi
    if (!$com->azioneAvviso(($edit ? 'edit' : 'add'), $avviso->getData(), $this->getUser(),
        $edit ? $avviso : null)) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // imposta autore dell'avviso
    $avviso->setAutore($this->getUser());
    // informazioni da visualizzare sui destinatari
    $dati = $com->infoDestinatari($avviso);
    // form di inserimento
    $form = $this->createForm(AvvisoType::class, $avviso, ['form_mode' => 'coordinatore',
      'return_url' => $this->generateUrl('avvisi_coordinatore'),
      'values' => [($avviso->getAnnotazioni()->count() > 0)]]);
    $form->handleRequest($request);
    if ($form->isSubmitted()) {
      // destinatari solo di classe
      if ($avviso->getDocenti() == 'T') {
        $avviso->setDocenti('C');
        $avviso->setFiltroDocenti([$classe->getId()]);
      }
      if ($avviso->getGenitori() == 'T') {
        $avviso->setGenitori('C');
        $avviso->setFiltroGenitori([$classe->getId()]);
      }
      if ($avviso->getAlunni() == 'T') {
        $avviso->setAlunni('C');
        $avviso->setFiltroAlunni([$classe->getId()]);
      }
      // controlla errori
      $errore = $com->validaAvviso($avviso, $this->getUser(), $form, $reg, false);
      if ($errore) {
        // errore presente
        $form->addError(new FormError($trans->trans($errore)));
      }
      if ($form->isValid()) {
        // gestione destinatari
        if ($edit) {
          // cancella destinatari
          $com->cancellaDestinatari($avviso);
        }
        // imposta destinatari
        $com->impostaDestinatari($avviso);
        // annotazione
        $com->annotazioneAvviso($avviso, $edit, $form->get('creaAnnotazione')->getData());
        // ok: memorizzazione e log
        $dblogger->logAzione('AVVISI', $edit ? 'Modifica avviso coordinatore' : 'Crea avviso coordinatore');
        // notifica con attesa di mezzora
        $notifica = new AvvisoMessage($avviso->getId());
        if (!$edit || !NotificaMessageHandler::update($this->em, $notifica->getTag(), 'avviso', 1800)) {
          // inserisce avviso (nuovo o modificato) in coda notifiche
          $msg->dispatch($notifica, [new DelayStamp(1800000)]);
        }
        // redirezione
        return $this->redirectToRoute('avvisi_coordinatore');
      }
    }
    // info di visualizzazione
    $info['classe'] = $classe;
    // mostra la pagina di risposta
    return $this->render('avvisi/edit_coordinatore.html.twig', [
      'pagina_titolo' => 'page.coordinatore_avvisi',
      'form' => $form,
      'form_title' => ($edit ? 'title.modifica_avviso_coordinatore' : 'title.nuovo_avviso_coordinatore'),
      'info' => $info,
      'dati' => $dati]);
  }

  /**
   * Aggiunge o modifica una verifica o un compito
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param MessageBusInterface $msg Gestione delle notifiche
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param ComunicazioniUtil $com Funzioni di utilità per la gestione delle comunicazioni
   * @param LogHandler $dblogger Gestore dei log su database
   * @param string $tipo Tipo di avviso (V=verifica, P=compito)
   * @param Avviso|null $avviso Avviso da modificare o valore nullo per crearne uno nuovo
   *
   * @return Response Pagina di risposta
   */
  #[Route(path: '/avvisi/edit/agenda/{tipo}/{avviso}', name: 'avvisi_edit_agenda', requirements: ['tipo' => 'V|P', 'avviso' => '\d+'], defaults: ['avviso' => '0'], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function editAgenda(Request $request, TranslatorInterface $trans, MessageBusInterface $msg,
                             RegistroUtil $reg, ComunicazioniUtil $com, LogHandler $dblogger,
                             string $tipo,
                             #[MapEntity] ?Avviso $avviso=null
                             ): Response {
    // inizializza conferma
    if ($request->isMethod('GET')) {
      $this->reqstack->getSession()->set('/APP/ROUTE/avvisi_edit_agenda/conferma', 0);
    }
    // controlla azione
    $edit = false;
    if ($avviso) {
      // azione edit
      $edit = true;
      if ($avviso->getTipo() != $tipo) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
    } else {
      // azione add
      $oggi = new DateTime('today');
      $meseTesto = $this->reqstack->getSession()->get('/APP/ROUTE/avvisi_agenda/mese', '2000-01').'-01';
      if ($meseTesto > $oggi->format('Y-m-d')) {
        // data futura: imposta ultimo giorno di mese precedente
        $mese = (DateTime::createFromFormat('Y-m-d', $meseTesto))
          ->modify('-1 day');
      } else {
        // data odierna
        $mese = $oggi;
      }
      // va al primo giorno utile
      $mese = $this->em->getRepository(Festivita::class)->giornoSuccessivo($mese);
      $avviso = (new Avviso())
        ->setTipo($tipo)
        ->setData($mese)
        ->setTitolo('__TEMP__');  // valore temporaneo per evitare errori di validazione
      $this->em->persist($avviso);
    }
    // controllo permessi
    if (!$com->azioneAvviso(($edit ? 'edit' : 'add'), $avviso->getData(), $this->getUser(),
        $edit ? $avviso : null)) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // imposta autore dell'avviso
    $avviso->setAutore($this->getUser());
    // recupera festivi per calendario
    $info['festivi'] = $this->em->getRepository(Festivita::class)->listaFestivi();
    // visualizzazione filtri
    $info['lista'] = '';
    if ($avviso->getGenitori() == 'U') {
      $info['lista'] = $this->em->getRepository(Alunno::class)->listaAlunni($avviso->getFiltroGenitori(), 'gs-filtro-');
    }
    // form di inserimento
    $dati = $this->em->getRepository(Cattedra::class)->cattedreDocente($this->getUser());
    $form = $this->createForm(AvvisoType::class, $avviso, ['form_mode' => $tipo == 'V' ? 'verifica' : 'compito',
      'return_url' => $this->generateUrl('avvisi_agenda'), 'values' => [$dati['choice']]]);
    $form->handleRequest($request);
    if ($form->isSubmitted()) {
      // imposta dati
      $avviso->setAlunni($avviso->getGenitori());
      if ($avviso->getGenitori() == 'C') {
        if ($avviso->getCattedra()) {
          // gestisce classi articolate
          $filtroClassi = [$avviso->getCattedra()->getClasse()->getId()];
          $articolate = $this->em->getRepository(Classe::class)->classiArticolate($filtroClassi);
          foreach ($articolate as $articolata) {
            if (empty($articolata['comune'])) {
              // se classe comune aggiunge tutti i gruppi
              $filtroClassi = array_merge($filtroClassi, $articolata['gruppi']);
            }
          }
          $avviso->setFiltroGenitori($filtroClassi);
        } else {
          // nessuna classe (non dovrebbe succedere)
          $avviso->setFiltroGenitori([]);
        }
      }
      $avviso->setFiltroAlunni($avviso->getFiltroGenitori());
      $avviso->setSedi(new ArrayCollection($avviso->getCattedra() ?
        [$avviso->getCattedra()->getClasse()->getSede()] : []));
      $avviso->setTitolo($trans->trans($tipo == 'V' ? 'message.verifica_oggetto' : 'message.compito_oggetto',
        ['materia' => $avviso->getMateria() ? $avviso->getMateria()->getNomeBreve() :
        ($avviso->getCattedra() ? $avviso->getCattedra()->getMateria()->getNomeBreve() : '')]));
      // controllo errori
      $errore = $com->validaAvvisoAgenda($avviso, $this->getUser(), $reg);
      if ($errore) {
        // errore presente
        $form->addError(new FormError($trans->trans($errore)));
      }
      if ($form->isValid()) {
        // controllo verifiche/compiti esistenti
        $info['previsti'] = $this->em->getRepository(Avviso::class)->previsti($avviso);
        $dataClasse = $avviso->getData()->format('Y-m-d').'|'.$avviso->getCattedra()->getClasse()->getId();
        if (count($info['previsti']) > 0 &&
            $this->reqstack->getSession()->get('/APP/ROUTE/avvisi_edit_agenda/conferma', 0) != $dataClasse) {
          // richiede conferma
          $this->reqstack->getSession()->set('/APP/ROUTE/avvisi_edit_agenda/conferma', $dataClasse);
        } else {
          // gestione destinatari
          if ($edit) {
            // cancella destinatari
            $com->cancellaDestinatari($avviso);
          }
          // imposta destinatari
          $com->impostaDestinatari($avviso);
          // annotazione
          if ($tipo == 'V') {
            // annotazione solo per le verifiche
            $com->annotazioneAvviso($avviso, $edit, true, true);
          }
          // ok: memorizzazione e log
          $messaggio = ($edit ? 'Modifica' : 'Crea').' '.($tipo == 'V' ? 'verifica' : 'compito');
          $dblogger->logAzione('AVVISI', $messaggio);
          // notifica con attesa di mezzora
          $notifica = new AvvisoMessage($avviso->getId());
          if (!$edit || !NotificaMessageHandler::update($this->em, $notifica->getTag(), 'avviso', 1800)) {
            // inserisce avviso (nuovo o modificato) in coda notifiche
            $msg->dispatch($notifica, [new DelayStamp(1800000)]);
          }
          // redirezione
          return $this->redirectToRoute('avvisi_agenda');
        }
      }
    }
    // informazioni per la visualizzazione
    $info['tipo'] = $tipo;
    // mostra la pagina di risposta
    return $this->render('avvisi/edit_agenda.html.twig', [
      'pagina_titolo' => $tipo == 'V' ? 'page.agenda_verifica' : 'page.agenda_compito',
      'form' => $form,
      'form_title' => ($edit ? ($tipo == 'V' ? 'title.modifica_verifica' : 'title.modifica_compito') :
          ($tipo == 'V' ? 'title.nuova_verifica' : 'title.nuovo_compito')),
      'info' => $info,
      'dati' => $dati]);
  }

  /**
   * Visualizza gli eventi destinati ai docenti
   *
   * @param ComunicazioniUtil $com Funzioni di utilità per la gestione delle comunicazioni
   * @param string $mese Anno e mese della pagina da visualizzare dell'agenda
   * @param string $visualizzazione Tipo di visualizzazione [P=eventi personali, V=verifiche di classe]
   * @param Classe|null $classe Classe di riferimento per la visualizzazione delle verifiche
   *
   * @return Response Pagina di risposta
   */
  #[Route(path: '/avvisi/agenda/{mese}/{visualizzazione}/{classe}', name: 'avvisi_agenda', requirements: ['mese' => '\d\d\d\d-\d\d', 'visualizzazione' => 'P|V', 'classe' => '\d+'], defaults: ['mese' => '0000-00', 'visualizzazione' => 'P', 'classe' => 0], methods: ['GET'])]
  #[IsGranted('ROLE_UTENTE')]
  public function agenda(ComunicazioniUtil $com, string $mese, string $visualizzazione,
                         #[MapEntity] ?Classe $classe=null
                         ): Response {
    $dati = [];
    $info = [];
    // parametro data
    if ($mese == '0000-00') {
      // mese non specificato
      if ($this->reqstack->getSession()->get('/APP/ROUTE/avvisi_agenda/mese')) {
        // recupera data da sessione
        $mese = DateTime::createFromFormat('Y-m-d',
          $this->reqstack->getSession()->get('/APP/ROUTE/avvisi_agenda/mese').'-01');
      } else {
        // imposta data odierna
        $mese = (new DateTime())->modify('first day of this month');
      }
    } else {
      // imposta data indicata e la memorizza in sessione
      $mese = DateTime::createFromFormat('Y-m-d', $mese.'-01');
      $this->reqstack->getSession()->set('/APP/ROUTE/avvisi_agenda/mese', $mese->format('Y-m'));
    }
    // parametro classe
    if (!$classe) {
      // recupera classe da sessione
      $classe = $this->em->getRepository(Classe::class)->find(
        (int) $this->reqstack->getSession()->get('/APP/ROUTE/avvisi_agenda/classe'));
    }
    if ($visualizzazione == 'V' && !$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    if ($classe) {
      // salva classe in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/avvisi_agenda/classe', $classe->getId());
    }
    // nome mese
    $formatter = new IntlDateFormatter('it_IT', IntlDateFormatter::SHORT, IntlDateFormatter::SHORT);
    $formatter->setPattern('MMMM yyyy');
    $info['nome_mese'] = ucfirst($formatter->format($mese));
    $info['url_mese'] = $mese->format('Y-m');
    // data prec/succ
    $meseFine = (clone $mese)->modify('last day of this month');
    $dataSucc = $this->em->getRepository(Festivita::class)->giornoSuccessivo($meseFine);
    $info['url_succ'] = ($dataSucc ? $dataSucc->format('Y-m') : '');
    $dataPrec = (clone $mese);
    $dataPrec = $this->em->getRepository(Festivita::class)->giornoPrecedente($dataPrec);
    $info['url_prec'] = ($dataPrec ? $dataPrec->format('Y-m') : '');
    // dati presentazione calendario
    $info['inizio'] = (int) $mese->format('w') - 1;
    $m = clone $mese;
    $info['ultimo_giorno'] = $m->modify('last day of this month')->format('j');
    $info['fine'] = (int) $m->format('w') == 0 ? 0 : 6 - (int) $m->format('w');
    // info di visualizzazione
    $info['visualizzazione'] = $visualizzazione;
    if ($visualizzazione == 'V') {
      // visualizzazione verifiche di classe
      $info['classe'] = ''.$classe;
      $info['classeId'] = $classe->getId();
      $dati = $this->em->getRepository(Avviso::class)->numeroVerificheClasse($classe, $mese);
    } else {
      // visualizzazione normale
      $info['classe'] = null;
      $info['classeId'] = 0;
      $dati = $this->em->getRepository(Avviso::class)->agendaEventi($this->getUser(), $mese);
    }
    // festività
    $dati['festivi'] = $this->em->getRepository(Festivita::class)->listaMese($mese);
    // funzionalità per docenti
    if ($this->getUser() instanceOf Docente) {
      // filtro classi
      $dati['filtro'] = [];
      $classi = $this->em->getRepository(Cattedra::class)->cattedreDocente($this->getUser(), 'Q');
      foreach ($classi as $c) {
        $dati['filtro'][$c->getClasse()->getId()] = ''.$c->getClasse();
      }
      // azione add
      $fineAnno = $this->em->getRepository(Configurazione::class)->getParametro('anno_fine', '2000-01-01');
      if ($com->azioneAvviso('add', DateTime::createFromFormat('Y-m-d', $fineAnno), $this->getUser(), null)) {
        // pulsante add
        $dati['azioni']['add'] = 1;
      }
    }
    // mostra la pagina di risposta
    return $this->render('avvisi/agenda.html.twig', [
      'pagina_titolo' => 'page.agenda_eventi',
      'info' => $info,
      'dati' => $dati]);
  }

  /**
   * Mostra i dettagli degli eventi di una classe
   *
   * @param ComunicazioniUtil $com Funzioni di utilità per la gestione delle comunicazioni
   * @param string $data Data dell'evento (formato: AAAA-MM-GG)
   * @param string $tipo Tipo dell'evento [V=verifiche proprie, S=verifiche classe, P=compiti, A=attività, Q=colloqui]
   * @param Classe|null $classe Classe di riferimento
   *
   * @return Response Pagina di risposta
   */
  #[Route(path: '/avvisi/dettagli/agenda/{data}/{tipo}/{classe}', name: 'avvisi_dettagli_agenda', requirements: ['data' => '\d\d\d\d-\d\d-\d\d', 'tipo' => 'V|S|P|A|Q', 'classe' => '\d+'], defaults: ['classe' => 0], methods: ['GET'])]
  #[IsGranted('ROLE_UTENTE')]
  public function dettagliAgenda(ComunicazioniUtil $com, string $data, string $tipo,
                                 #[MapEntity] ?Classe $classe=null
                                 ): Response {
    // inizializza
    $dati = [];
    $info = [];
    // imposta data
    $data = DateTime::createFromFormat('Y-m-d', $data);
    // controllo classe
    if ($tipo == 'S' && !$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // legge dati
    $dati = $this->em->getRepository(Avviso::class)->listaAgendaEventi($data, $tipo, $this->getuser(), $classe);
    // aggiunge azioni
    if ($this->getuser() instanceOf Docente && in_array($tipo, ['V', 'S', 'P'])) {
      foreach ($dati['eventi'] as $k => $evento) {
        // pulsante edit
        if ($com->azioneAvviso('edit', $evento->getData(), $this->getUser(), $evento)) {
          $dati['azioni'][$k]['edit'] = 1;
        }
        // pulsante delete
        if ($com->azioneAvviso('delete', $evento->getData(), $this->getUser(), $evento)) {
          $dati['azioni'][$k]['delete'] = 1;
        }
      }
    }
    // inserisce conferma di lettura
    if ($tipo != 'Q') {
      foreach ($dati['eventi'] as $evento) {
        // firma
        $this->em->getRepository(ComunicazioneUtente::class)->legge($evento, $this->getUser());
      }
    }
    // informazioni di visualizzazione
    $info['mesi'] = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto',
      'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    $info['data'] = $data;
    $info['tipo'] = $tipo;
    $info['classe'] = $classe;
    // visualizza pagina
    return $this->render('avvisi/scheda_dettagli_agenda.html.twig', [
      'info' => $info,
      'dati' => $dati]);
  }

}
