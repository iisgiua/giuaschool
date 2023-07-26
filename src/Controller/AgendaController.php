<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Controller;

use App\Entity\Avviso;
use App\Entity\AvvisoUtente;
use App\Form\AvvisoType;
use App\Message\AvvisoMessage;
use App\MessageHandler\NotificaMessageHandler;
use App\Util\AgendaUtil;
use App\Util\BachecaUtil;
use App\Util\LogHandler;
use App\Util\RegistroUtil;
use Doctrine\Common\Collections\ArrayCollection;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;


/**
 * AgendaController - gestione dell'agenda
 *
 * @author Antonello Dessì
 */
class AgendaController extends BaseController {

  /**
   * Visualizza gli eventi destinati ai docenti
   *
   * @param AgendaUtil $age Funzioni di utilità per la gestione dell'agenda
   * @param string $mese Anno e mese della pagina da visualizzare dell'agenda
   *
   * @return Response Pagina di risposta
   *
   * @Route("/agenda/eventi/{mese}", name="agenda_eventi",
   *    requirements={"mese": "\d\d\d\d-\d\d"},
   *    defaults={"mese": "0000-00"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function eventiAction(AgendaUtil $age, string $mese): Response {
    $dati = null;
    $info = null;
    // parametro data
    if ($mese == '0000-00') {
      // mese non specificato
      if ($this->reqstack->getSession()->get('/APP/ROUTE/agenda_eventi/mese')) {
        // recupera data da sessione
        $mese = \DateTime::createFromFormat('Y-m-d', $this->reqstack->getSession()->get('/APP/ROUTE/agenda_eventi/mese').'-01');
      } else {
        // imposta data odierna
        $mese = (new \DateTime())->modify('first day of this month');
      }
    } else {
      // imposta data indicata e la memorizza in sessione
      $mese = \DateTime::createFromFormat('Y-m-d', $mese.'-01');
      $this->reqstack->getSession()->set('/APP/ROUTE/agenda_eventi/mese', $mese->format('Y-m'));
    }
    // nome/url mese
    $formatter = new \IntlDateFormatter('it_IT', \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT);
    $formatter->setPattern('MMMM yyyy');
    $info['mese'] =  ucfirst($formatter->format($mese));
    // data prec/succ
    $data_inizio = \DateTime::createFromFormat('Y-m-d', $mese->format('Y-m-01'));
    $data_fine = clone $data_inizio;
    $data_fine->modify('last day of this month');
    $data_succ = (clone $data_fine);
    $data_succ = $this->em->getRepository('App\Entity\Festivita')->giornoSuccessivo($data_succ);
    $info['url_succ'] = ($data_succ ? $data_succ->format('Y-m') : null);
    $data_prec = (clone $data_inizio);
    $data_prec = $this->em->getRepository('App\Entity\Festivita')->giornoPrecedente($data_prec);
    $info['url_prec'] = ($data_prec ? $data_prec->format('Y-m') : null);
    // presentazione calendario
    $info['inizio'] = (intval($mese->format('w')) - 1);
    $m = clone $mese;
    $info['ultimo_giorno'] = $m->modify('last day of this month')->format('j');
    $info['fine'] = (intval($m->format('w')) == 0 ? 0 : 6 - intval($m->format('w')));
    // recupera dati
    $dati = $age->agendaEventi($this->getUser(), $mese);
    // mostra la pagina di risposta
    return $this->render('agenda/eventi.html.twig', array(
      'pagina_titolo' => 'page.agenda_eventi',
      'mese' => $mese,
      'info' => $info,
      'dati' => $dati,
    ));
  }

  /**
   * Mostra i dettagli di un evento destinato al docente
   *
   * @param AgendaUtil $age Funzioni di utilità per la gestione dell'agenda
   * @param string $data Data dell'evento (AAAA-MM-GG)
   * @param string $tipo Tipo dell'evento
   *
   * @return Response Pagina di risposta
   *
   * @Route("/agenda/eventi/dettagli/{data}/{tipo}", name="agenda_eventi_dettagli",
   *    requirements={"data": "\d\d\d\d-\d\d-\d\d", "tipo": "C|A|V|P"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function eventiDettagliAction(AgendaUtil $age, string $data, string $tipo): Response {
    // inizializza
    $dati = null;
    // data
    $data = \DateTime::createFromFormat('Y-m-d', $data);
    // legge dati
    $dati = $age->dettagliEvento($this->getUser(), $data, $tipo);
    // visualizza pagina
    return $this->render('agenda/scheda_evento_'.$tipo.'.html.twig', array(
      'dati' => $dati,
      'data' => $data,
    ));
  }

  /**
   * Aggiunge o modifica una verifica
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param MessageBusInterface $msg Gestione delle notifiche
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param BachecaUtil $bac Funzioni di utilità per la gestione della bacheca
   * @param AgendaUtil $age Funzioni di utilità per la gestione dell'agenda
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $id Identificativo dell'avviso
   *
   * @return Response Pagina di risposta
   *
   * @Route("/agenda/verifica/edit/{id}", name="agenda_verifica_edit",
   *    requirements={"id": "\d+"},
   *    defaults={"id": "0"},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function verificaEditAction(Request $request, TranslatorInterface $trans, MessageBusInterface $msg,
                                     RegistroUtil $reg, BachecaUtil $bac, AgendaUtil $age,
                                     LogHandler $dblogger, int $id): Response {
    // inizializza
    $dati = array();
    $lista_festivi = null;
    $verifiche = array();
    $docente = $this->getUser();
    $materia_sostegno = null;
    if ($request->isMethod('GET')) {
      // inizializza sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/agenda_verifica_edit/conferma', 0);
    }
    // controlla azione
    if ($id > 0) {
      // azione edit
      $avviso = $this->em->getRepository('App\Entity\Avviso')->findOneBy(['id' => $id, 'tipo' => 'V']);
      if (!$avviso) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $avviso_old = clone $avviso;
      $materia_sostegno = $avviso->getMateria() ? $avviso->getMateria()->getId() : null;
    } else {
      // azione add
      $oggi = new \DateTime();
      $mese = $oggi;
      if ($this->reqstack->getSession()->get('/APP/ROUTE/agenda_eventi/mese')) {
        // recupera data da sessione
        $mese_sessione = \DateTime::createFromFormat('Y-m-d', $this->reqstack->getSession()->get('/APP/ROUTE/agenda_eventi/mese').'-01');
        if ($mese_sessione > $oggi) {
          // ultimo giorno di mese precedente
          $mese = $mese_sessione;
          $mese->modify('-1 day');
        }
      }
      $mese = $this->em->getRepository('App\Entity\Festivita')->giornoSuccessivo($mese);
      $avviso = (new Avviso())
        ->setTipo('V')
        ->setDestinatari(['G', 'A'])
        ->setData($mese)
        ->setOggetto('__TEMP__'); // valore temporaneo
      $this->em->persist($avviso);
    }
    // imposta autore dell'avviso
    $avviso->setDocente($docente);
    // recupera festivi per calendario
    $lista_festivi = $age->festivi();
    // form di inserimento
    $dati = $this->em->getRepository('App\Entity\Cattedra')->cattedreDocente($docente);
    $form = $this->createForm(AvvisoType::class, $avviso, ['form_mode' => 'verifica',
      'return_url' => $this->generateUrl('agenda_eventi'),
      'values' => [$dati['choice'], $materia_sostegno]]);
    $form->handleRequest($request);
    // visualizzazione filtri
    $dati['lista'] = '';
    if ($form->get('filtroTipo')->getData() == 'U') {
      $dati['lista'] = $this->em->getRepository('App\Entity\Alunno')->listaAlunni($form->get('filtro')->getData(), 'gs-filtro-');
    }
    if ($form->isSubmitted() && $form->isValid()) {
      // controllo errori
      if (!in_array($form->get('filtroTipo')->getData(), ['T', 'U'])) {
        // errore: tipo filtro non valido
        $form->addError(new FormError($trans->trans('exception.filtro_utente_nullo')));
      }
      if ($form->get('filtroTipo')->getData() == 'U' && empty(implode(',', $form->get('filtro')->getData()))) {
        // errore: filtro vuoto
        $form->addError(new FormError($trans->trans('exception.filtro_utente_nullo')));
      }
      // controllo data
      $errore = $reg->controlloData($form->get('data')->getData(), null);
      if ($errore) {
        // errore: festivo
        $form->addError(new FormError($trans->trans('exception.data_festiva')));
      }
      // controllo cattedra
      if (!$avviso->getCattedra()) {
        // errore: cattedra non specificata
        $form->addError(new FormError($trans->trans('exception.cattedra_mancante')));
      }
      // controllo sostegno
      $materia = null;
      if ($avviso->getCattedra() && $avviso->getCattedra()->getMateria()->getTipo() == 'S') {
        // legge materia scelta
        $materia = $this->em->getRepository('App\Entity\Cattedra')->findOneBy(['materia' => $form->get('materia_sostegno')->getData(),
          'classe' => $avviso->getCattedra()->getClasse(), 'attiva' => 1]);
        if (!$materia ||
            ($avviso->getCattedra()->getAlunno() && $avviso->getCattedra()->getAlunno()->getId() != $avviso->getFiltro()[0])) {
          $form->addError(new FormError($trans->trans('exception.cattedra_non_valida')));
        }
      }
      // controlla filtro
      $lista = array();
      $errore = false;
      if ($avviso->getFiltroTipo() == 'U') {
        $lista = $this->em->getRepository('App\Entity\Alunno')
          ->controllaAlunni([$avviso->getCattedra()->getClasse()->getSede()], $form->get('filtro')->getData(), $errore);
        if ($errore) {
          // utente non valido
          $form->addError(new FormError($trans->trans('exception.filtro_utenti_invalido', ['dest' => ''])));
        }
        $avviso->setFiltro($lista);
      }
      // controllo permessi
      if (!$age->azioneEvento(($id > 0 ? 'edit' : 'add'), $avviso->getData(), $this->getUser(), ($id > 0 ? $avviso : null))) {
        // errore: avviso non permesso
        $form->addError(new FormError($trans->trans('exception.avviso_non_permesso')));
      }
      if (!$reg->azioneAnnotazione('add', $avviso->getData(), $this->getUser(), null, null)) {
        // errore: nuova annotazione non permessa
        $form->addError(new FormError($trans->trans('exception.annotazione_non_permessa')));
      }
      if (count($avviso->getAnnotazioni()) > 0) {
        $a = $avviso->getAnnotazioni()[0];
        if (!$reg->azioneAnnotazione('delete', $a->getData(), $this->getUser(), $a->getClasse(), $a)) {
          // errore: cancellazione annotazione non permessa
          $form->addError(new FormError($trans->trans('exception.annotazione_non_permessa')));
        }
      }
      // modifica dati
      if ($form->isValid()) {
        // controllo verifiche esistenti
        $verifiche = $age->controlloVerifiche($avviso);
        $data_classe = $avviso->getData()->format('Y-m-d').'|'.$avviso->getCattedra()->getClasse()->getId();
        if (count($verifiche) > 0 && $this->reqstack->getSession()->get('/APP/ROUTE/agenda_verifica_edit/conferma', 0) != $data_classe) {
          // richiede conferma
          $this->reqstack->getSession()->set('/APP/ROUTE/agenda_verifica_edit/conferma', $data_classe);
        } else {
          // imposta sede
          $avviso->setSedi(new ArrayCollection([$avviso->getCattedra()->getClasse()->getSede()]));
          // verifica sostegno: aggiunge materia
          $avviso->setMateria($materia ? $materia->getMateria() : null);
          // oggetto
          $avviso->setOggetto($trans->trans('message.verifica_oggetto',
            ['materia' => $materia ? $materia->getMateria()->getNomeBreve() :
              $avviso->getCattedra()->getMateria()->getNomeBreve()]));
          // gestione destinatari
          if ($id) {
            // cancella destinatari precedenti e dati lettura
            $this->em->getRepository('App\Entity\AvvisoUtente')->createQueryBuilder('au')
              ->delete()
              ->where('au.avviso=:avviso')
              ->setParameters(['avviso' => $avviso])
              ->getQuery()
              ->execute();
          }
          if ($avviso->getFiltroTipo() == 'T') {
            // destinatari solo classe di cattedra
            $avviso->setFiltroTipo('C')->setFiltro([$avviso->getCattedra()->getClasse()->getId()]);
            $dest = $bac->destinatariAvviso($avviso);
            $avviso->setFiltroTipo('T')->setFiltro([]);
          } else {
            // destinatari utenti
            $dest = $bac->destinatariAvviso($avviso);
          }
          // imposta utenti
          foreach ($dest['utenti'] as $u) {
            $obj = (new AvvisoUtente())
              ->setAvviso($avviso)
              ->setUtente($this->em->getReference('App\Entity\Utente', $u));
            $this->em->persist($obj);
          }
          // annotazione
          $log_annotazioni['delete'] = array();
          if ($id) {
            // cancella annotazioni
            foreach ($avviso->getAnnotazioni() as $a) {
              $log_annotazioni['delete'][] = $a->getId();
              $this->em->remove($a);
            }
            $avviso->setAnnotazioni(new ArrayCollection());
          }
          // crea nuova annotazione
          $age->creaAnnotazione($avviso);
          // ok: memorizza dati
          $this->em->flush();
          // notifica con attesa di mezzora
          $notifica = new AvvisoMessage($avviso->getId());
          if (!$id || !NotificaMessageHandler::update($this->em, $notifica->getTag(), 'avviso', 1800)) {
            // inserisce avviso (nuovo o modificato) in coda notifiche
            $msg->dispatch($notifica, [new DelayStamp(1800000)]);
          }
          // log azione
          if (!$id) {
            // nuovo
            $dblogger->logAzione('AGENDA', 'Crea verifica', array(
              'Avviso' => $avviso->getId(),
              'Annotazioni' => implode(', ', array_map(function ($a) {
                  return $a->getId();
                }, $avviso->getAnnotazioni()->toArray())),
              ));
          } else {
            // modifica
            $dblogger->logAzione('AGENDA', 'Modifica verifica', array(
              'Avviso' => $avviso->getId(),
              'Data' => $avviso_old->getData()->format('d/m/Y'),
              'Cattedra' => $avviso_old->getCattedra()->getId(),
              'Materia' => $avviso_old->getMateria() ? $avviso_old->getMateria()->getId() : 0,
              'Testo' => $avviso_old->getTesto(),
              'Destinatari' => $avviso_old->getDestinatari(),
              'Filtro Tipo' => $avviso_old->getFiltroTipo(),
              'Filtro' => $avviso_old->getFiltro(),
              'Docente' => $avviso_old->getDocente()->getId(),
              'Annotazioni cancellate' => implode(', ', $log_annotazioni['delete']),
              'Annotazioni create' => implode(', ', array_map(function ($a) {
                  return $a->getId();
                }, $avviso->getAnnotazioni()->toArray())),
              ));
          }
          // redirezione
          return $this->redirectToRoute('agenda_eventi');
        }
      }
    }
    // mostra la pagina di risposta
    return $this->render('agenda/verifica_edit.html.twig', array(
      'pagina_titolo' => 'page.agenda_verifica',
      'form' => $form->createView(),
      'form_title' => ($id > 0 ? 'title.modifica_verifica' : 'title.nuova_verifica'),
      'verifiche' => $verifiche,
      'lista_festivi' => $lista_festivi,
      'dati' => $dati,
    ));
  }

  /**
   * Restituisce gli alunni della classe collegata alla cattedra indicata
   *
   * @param int $id Identificativo della cattedra
   *
   * @return JsonResponse Informazioni di risposta
   *
   * @Route("/agenda/cattedra/{id}", name="agenda_cattedra",
   *    requirements={"id": "\d+"},
   *    defaults={"id": 0},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function cattedraAjaxAction(int $id): JsonResponse {
    $alunni = $this->em->getRepository('App\Entity\Alunno')->createQueryBuilder('a')
      ->select("a.id,CONCAT(a.cognome,' ',a.nome) AS nome")
      ->join('App\Entity\Cattedra', 'c', 'WITH', 'c.classe=a.classe')
      ->where('a.abilitato=:abilitato AND c.id=:cattedra AND c.attiva=:attiva')
      ->setParameters(['abilitato' => 1, 'cattedra' => $id, 'attiva' => 1])
      ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
      ->getQuery()
      ->getArrayResult();
    // restituisce dati
    return new JsonResponse($alunni);
  }

  /**
   * Restituisce le materie della cattedra della classe indicata
   *
   * @param int $id Identificativo della classe
   *
   * @return JsonResponse Informazioni di risposta
   *
   * @Route("/agenda/classe/{id}", name="agenda_classe",
   *    requirements={"id": "\d+"},
   *    defaults={"id": 0},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function classeAjaxAction(int $id): JsonResponse {
    // solo cattedre attive e normali, no supplenza, no sostegno
    $cattedre = $this->em->getRepository('App\Entity\Cattedra')->createQueryBuilder('c')
      ->select('m.id,m.nome')
      ->join('c.materia', 'm')
      ->where('c.classe=:classe AND c.attiva=:attiva AND c.tipo=:tipo AND c.supplenza=:supplenza AND m.tipo!=:sostegno')
      ->setParameters(['classe' => $id, 'attiva' => 1, 'tipo' => 'N', 'supplenza' => 0, 'sostegno' => 'S'])
      ->orderBy('m.nomeBreve', 'ASC')
      ->getQuery()
      ->getArrayResult();
    // restituisce dati
    return new JsonResponse($cattedre);
  }

  /**
   * Cancella verifica
   *
   * @param Request $request Pagina richiesta
   * @param LogHandler $dblogger Gestore dei log su database
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param BachecaUtil $bac Funzioni di utilità per la gestione della bacheca
   * @param AgendaUtil $age Funzioni di utilità per la gestione dell'agenda
   * @param int $id Identificativo dell'avviso
   *
   * @return Response Pagina di risposta
   *
   * @Route("/agenda/verifica/delete/{id}", name="agenda_verifica_delete",
   *    requirements={"id": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function verificaDeleteAction(Request $request, LogHandler $dblogger, RegistroUtil $reg,
                                       BachecaUtil $bac, AgendaUtil $age, int $id): Response {
    // controllo avviso
    $avviso = $this->em->getRepository('App\Entity\Avviso')->findOneBy(['id' => $id, 'tipo' => 'V']);
    if (!$avviso) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo permessi
    if (!$age->azioneEvento('delete', $avviso->getData(), $this->getUser(), $avviso)) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    if (count($avviso->getAnnotazioni()) > 0) {
      $a = $avviso->getAnnotazioni()[0];
      if (!$reg->azioneAnnotazione('delete', $a->getData(), $this->getUser(), $a->getClasse(), $a)) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
    }
    // cancella annotazioni
    $log_annotazioni = array();
    foreach ($avviso->getAnnotazioni() as $a) {
      $log_annotazioni[] = $a->getId();
      $this->em->remove($a);
    }
    // cancella destinatari
    $this->em->getRepository('App\Entity\AvvisoUtente')->createQueryBuilder('au')
      ->delete()
      ->where('au.avviso=:avviso')
      ->setParameters(['avviso' => $avviso])
      ->getQuery()
      ->execute();
    // cancella avviso
    $avviso_id = $avviso->getId();
    $this->em->remove($avviso);
    // ok: memorizza dati
    $this->em->flush();
    // rimuove notifica
    NotificaMessageHandler::delete($this->em, (new AvvisoMessage($avviso_id))->getTag());
    // log azione
    $dblogger->logAzione('AGENDA', 'Cancella verifica', array(
      'Id' => $avviso_id,
      'Data' => $avviso->getData()->format('d/m/Y'),
      'Testo' => $avviso->getTesto(),
      'Cattedra' => $avviso->getCattedra()->getId(),
      'Materia' => $avviso->getMateria() ? $avviso->getMateria()->getId() : 0,
      'Filtro Tipo' => $avviso->getFiltroTipo(),
      'Filtro' => $avviso->getFiltro(),
      'Docente' => $avviso->getDocente()->getId(),
      'Annotazioni' => implode(', ', $log_annotazioni),
      ));
    // redirezione
    return $this->redirectToRoute('agenda_eventi');
  }

  /**
   * Aggiunge o modifica un compito per casa
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param MessageBusInterface $msg Gestione delle notifiche
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param BachecaUtil $bac Funzioni di utilità per la gestione della bacheca
   * @param AgendaUtil $age Funzioni di utilità per la gestione dell'agenda
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $id Identificativo dell'avviso
   *
   * @return Response Pagina di risposta
   *
   * @Route("/agenda/compito/edit/{id}", name="agenda_compito_edit",
   *    requirements={"id": "\d+"},
   *    defaults={"id": "0"},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function compitoEditAction(Request $request, TranslatorInterface $trans, MessageBusInterface $msg,
                                    RegistroUtil $reg, BachecaUtil $bac, AgendaUtil $age,
                                    LogHandler $dblogger, int $id): Response {
    // inizializza
    $dati = array();
    $lista_festivi = null;
    $compiti = array();
    $docente = $this->getUser();
    $materia_sostegno = null;
    if ($request->isMethod('GET')) {
      // inizializza sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/agenda_compito_edit/conferma', 0);
    }
    // controlla azione
    if ($id > 0) {
      // azione edit
      $avviso = $this->em->getRepository('App\Entity\Avviso')->findOneBy(['id' => $id, 'tipo' => 'P']);
      if (!$avviso) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $avviso_old = clone $avviso;
      $materia_sostegno = $avviso->getMateria() ? $avviso->getMateria()->getId() : null;
    } else {
      // azione add
      $oggi = new \DateTime();
      $mese = $oggi;
      if ($this->reqstack->getSession()->get('/APP/ROUTE/agenda_eventi/mese')) {
        // recupera data da sessione
        $mese_sessione = \DateTime::createFromFormat('Y-m-d', $this->reqstack->getSession()->get('/APP/ROUTE/agenda_eventi/mese').'-01');
        if ($mese_sessione > $oggi) {
          // ultimo giorno di mese precedente
          $mese = $mese_sessione;
          $mese->modify('-1 day');
        }
      }
      $mese = $this->em->getRepository('App\Entity\Festivita')->giornoSuccessivo($mese);
      $avviso = (new Avviso())
        ->setTipo('P')
        ->setDestinatari(['G', 'A'])
        ->setData($mese)
        ->setOggetto('__TEMP__'); // valore temporaneo
      $this->em->persist($avviso);
    }
    // imposta autore dell'avviso
    $avviso->setDocente($docente);
    // recupera festivi per calendario
    $lista_festivi = $age->festivi();
    // form di inserimento
    $dati = $this->em->getRepository('App\Entity\Cattedra')->cattedreDocente($docente);
    $form = $this->createForm(AvvisoType::class, $avviso, ['form_mode' => 'compito',
      'return_url' => $this->generateUrl('agenda_eventi'),
      'values' => [$dati['choice'], $materia_sostegno]]);
    $form->handleRequest($request);
    // visualizzazione filtri
    $dati['lista'] = '';
    if ($form->get('filtroTipo')->getData() == 'U') {
      $dati['lista'] = $this->em->getRepository('App\Entity\Alunno')->listaAlunni($form->get('filtro')->getData(), 'gs-filtro-');
    }
    if ($form->isSubmitted() && $form->isValid()) {
      // controllo errori
      if (!in_array($form->get('filtroTipo')->getData(), ['T', 'U'])) {
        // errore: tipo filtro non valido
        $form->addError(new FormError($trans->trans('exception.filtro_utente_nullo')));
      }
      if ($form->get('filtroTipo')->getData() == 'U' && empty(implode(',', $form->get('filtro')->getData()))) {
        // errore: filtro vuoto
        $form->addError(new FormError($trans->trans('exception.filtro_utente_nullo')));
      }
      // controllo data
      $errore = $reg->controlloData($form->get('data')->getData(), null);
      if ($errore) {
        // errore: festivo
        $form->addError(new FormError($trans->trans('exception.data_festiva')));
      }
      // controllo cattedra
      if (!$avviso->getCattedra()) {
        // errore: cattedra non specificata
        $form->addError(new FormError($trans->trans('exception.cattedra_mancante')));
      }
      // controllo sostegno
      $materia = null;
      if ($avviso->getCattedra() && $avviso->getCattedra()->getMateria()->getTipo() == 'S') {
        // legge materia scelta
        $materia = $this->em->getRepository('App\Entity\Cattedra')->findOneBy(['materia' => $form->get('materia_sostegno')->getData(),
          'classe' => $avviso->getCattedra()->getClasse(), 'attiva' => 1]);
        if (!$materia ||
            ($avviso->getCattedra()->getAlunno() && $avviso->getCattedra()->getAlunno()->getId() != $avviso->getFiltro()[0])) {
          $form->addError(new FormError($trans->trans('exception.cattedra_non_valida')));
        }
      }
      // controlla filtro
      $lista = array();
      $errore = false;
      if ($avviso->getFiltroTipo() == 'U') {
        $lista = $this->em->getRepository('App\Entity\Alunno')
          ->controllaAlunni([$avviso->getCattedra()->getClasse()->getSede()], $form->get('filtro')->getData(), $errore);
        if ($errore) {
          // utente non valido
          $form->addError(new FormError($trans->trans('exception.filtro_utenti_invalido', ['dest' => ''])));
        }
        $avviso->setFiltro($lista);
      }
      // controllo permessi
      if (!$age->azioneEvento(($id > 0 ? 'edit' : 'add'), $avviso->getData(), $this->getUser(), ($id > 0 ? $avviso : null))) {
        // errore: avviso non permesso
        $form->addError(new FormError($trans->trans('exception.avviso_non_permesso')));
      }
      // modifica dati
      if ($form->isValid()) {
        // controllo compiti esistenti
        $compiti = $age->controlloCompiti($avviso);
        $data_classe = $avviso->getData()->format('Y-m-d').'|'.$avviso->getCattedra()->getClasse()->getId();
        if (count($compiti) > 0 && $this->reqstack->getSession()->get('/APP/ROUTE/agenda_compito_edit/conferma', 0) != $data_classe) {
          // richiede conferma
          $this->reqstack->getSession()->set('/APP/ROUTE/agenda_compito_edit/conferma', $data_classe);
        } else {
          // imposta sede
          $avviso->setSedi(new ArrayCollection([$avviso->getCattedra()->getClasse()->getSede()]));
          // verifica sostegno: aggiunge materia
          $avviso->setMateria($materia ? $materia->getMateria() : null);
          // oggetto
          $avviso->setOggetto($trans->trans('message.compito_oggetto',
            ['materia' => $materia ? $materia->getMateria()->getNomeBreve() :
              $avviso->getCattedra()->getMateria()->getNomeBreve()]));
          // gestione destinatari
          if ($id) {
            // cancella destinatari precedenti e dati lettura
            $this->em->getRepository('App\Entity\AvvisoUtente')->createQueryBuilder('au')
              ->delete()
              ->where('au.avviso=:avviso')
              ->setParameters(['avviso' => $avviso])
              ->getQuery()
              ->execute();
          }
          $dest = $bac->destinatariAvviso($avviso);
          if ($avviso->getFiltroTipo() == 'T') {
            // destinatari solo classe di cattedra
            $avviso->setFiltroTipo('C')->setFiltro([$avviso->getCattedra()->getClasse()->getId()]);
            $dest = $bac->destinatariAvviso($avviso);
            $avviso->setFiltroTipo('T')->setFiltro([]);
          } else {
            // destinatari utenti
            $dest = $bac->destinatariAvviso($avviso);
          }
          // imposta utenti
          foreach ($dest['utenti'] as $u) {
            $obj = (new AvvisoUtente())
              ->setAvviso($avviso)
              ->setUtente($this->em->getReference('App\Entity\Utente', $u));
            $this->em->persist($obj);
          }
          // ok: memorizza dati
          $this->em->flush();
          // notifica con attesa di mezzora
          $notifica = new AvvisoMessage($avviso->getId());
          if (!$id || !NotificaMessageHandler::update($this->em, $notifica->getTag(), 'avviso', 1800)) {
            // inserisce avviso (nuovo o modificato) in coda notifiche
            $msg->dispatch($notifica, [new DelayStamp(1800000)]);
          }
          // log azione
          if (!$id) {
            // nuovo
            $dblogger->logAzione('AGENDA', 'Crea compito', array(
              'Avviso' => $avviso->getId(),
            ));
          } else {
            // modifica
            $dblogger->logAzione('AGENDA', 'Modifica compito', array(
              'Avviso' => $avviso->getId(),
              'Data' => $avviso_old->getData()->format('d/m/Y'),
              'Cattedra' => $avviso_old->getCattedra()->getId(),
              'Materia' => $avviso_old->getMateria() ? $avviso_old->getMateria()->getId() : 0,
              'Testo' => $avviso_old->getTesto(),
              'Destinatari' => $avviso_old->getDestinatari(),
              'Filtro Tipo' => $avviso_old->getFiltroTipo(),
              'Filtro' => $avviso_old->getFiltro(),
              'Docente' => $avviso_old->getDocente()->getId(),
            ));
          }
          // redirezione
          return $this->redirectToRoute('agenda_eventi');
        }
      }
    }
    // mostra la pagina di risposta
    return $this->render('agenda/compito_edit.html.twig', array(
      'pagina_titolo' => 'page.agenda_compito',
      'form' => $form->createView(),
      'form_title' => ($id > 0 ? 'title.modifica_compito' : 'title.nuovo_compito'),
      'compiti' => $compiti,
      'lista_festivi' => $lista_festivi,
      'dati' => $dati,
    ));
  }

  /**
   * Cancella compiti per casa
   *
   * @param Request $request Pagina richiesta
   * @param LogHandler $dblogger Gestore dei log su database
   * @param AgendaUtil $age Funzioni di utilità per la gestione dell'agenda
   * @param int $id Identificativo dell'avviso
   *
   * @return Response Pagina di risposta
   *
   * @Route("/agenda/compito/delete/{id}", name="agenda_compito_delete",
   *    requirements={"id": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function compitoDeleteAction(Request $request, LogHandler $dblogger, AgendaUtil $age, 
                                      int $id): Response {
    // controllo avviso
    $avviso = $this->em->getRepository('App\Entity\Avviso')->findOneBy(['id' => $id, 'tipo' => 'P']);
    if (!$avviso) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo permessi
    if (!$age->azioneEvento('delete', $avviso->getData(), $this->getUser(), $avviso)) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // cancella destinatari
    $this->em->getRepository('App\Entity\AvvisoUtente')->createQueryBuilder('au')
      ->delete()
      ->where('au.avviso=:avviso')
      ->setParameters(['avviso' => $avviso])
      ->getQuery()
      ->execute();
    // cancella avviso
    $avviso_id = $avviso->getId();
    $this->em->remove($avviso);
    // ok: memorizza dati
    $this->em->flush();
    // rimuove notifica
    NotificaMessageHandler::delete($this->em, (new AvvisoMessage($avviso_id))->getTag());
    // log azione
    $dblogger->logAzione('AGENDA', 'Cancella compito', array(
      'Avviso' => $avviso_id,
      'Data' => $avviso->getData()->format('d/m/Y'),
      'Testo' => $avviso->getTesto(),
      'Cattedra' => $avviso->getCattedra()->getId(),
      'Materia' => $avviso->getMateria() ? $avviso->getMateria()->getId() : 0,
      'Filtro Tipo' => $avviso->getFiltroTipo(),
      'Filtro' => $avviso->getFiltro(),
      'Docente' => $avviso->getDocente()->getId(),
      ));
    // redirezione
    return $this->redirectToRoute('agenda_eventi');
  }

}
