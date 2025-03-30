<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Controller;

use App\Entity\Alunno;
use App\Entity\Avviso;
use App\Entity\AvvisoUtente;
use App\Entity\Cattedra;
use App\Entity\Classe;
use App\Entity\Festivita;
use App\Entity\Utente;
use App\Form\AvvisoType;
use App\Message\AvvisoMessage;
use App\MessageHandler\NotificaMessageHandler;
use App\Util\AgendaUtil;
use App\Util\BachecaUtil;
use App\Util\LogHandler;
use App\Util\RegistroUtil;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use IntlDateFormatter;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
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
   */
  #[Route(path: '/agenda/eventi/{mese}/{classe}', name: 'agenda_eventi', requirements: ['mese' => '\d\d\d\d-\d\d', 'classe' => '-?\d+'], defaults: ['mese' => '0000-00', 'classe' => 0], methods: ['GET'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function eventi(AgendaUtil $age, string $mese, int $classe): Response {
    $dati = [];
    $info = [];
    // parametro data
    if ($mese == '0000-00') {
      // mese non specificato
      if ($this->reqstack->getSession()->get('/APP/ROUTE/agenda_eventi/mese')) {
        // recupera data da sessione
        $mese = DateTime::createFromFormat('Y-m-d', $this->reqstack->getSession()->get('/APP/ROUTE/agenda_eventi/mese').'-01');
      } else {
        // imposta data odierna
        $mese = (new DateTime())->modify('first day of this month');
      }
    } else {
      // imposta data indicata e la memorizza in sessione
      $mese = DateTime::createFromFormat('Y-m-d', $mese.'-01');
      $this->reqstack->getSession()->set('/APP/ROUTE/agenda_eventi/mese', $mese->format('Y-m'));
    }
    // parametro classe
    if ($classe == 0) {
      // recupera classe da sessione
      $classe = (int) $this->reqstack->getSession()->get('/APP/ROUTE/agenda_eventi/classe', -1);
    }
    if ($classe > 0) {
      // controlla esistenza classe
      $classe = $this->em->getRepository(Classe::class)->find($classe);
      if (!$classe) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      // salva classe in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/agenda_eventi/classe', $classe->getId());
    } else {
      // visualizzazione normale
      $classe = 0;
      $this->reqstack->getSession()->set('/APP/ROUTE/agenda_eventi/classe', -1);
    }
    // nome/url mese
    $formatter = new IntlDateFormatter('it_IT', IntlDateFormatter::SHORT, IntlDateFormatter::SHORT);
    $formatter->setPattern('MMMM yyyy');
    $info['mese'] =  ucfirst($formatter->format($mese));
    // data prec/succ
    $data_inizio = DateTime::createFromFormat('Y-m-d', $mese->format('Y-m-01'));
    $data_fine = clone $data_inizio;
    $data_fine->modify('last day of this month');
    $data_succ = (clone $data_fine);
    $data_succ = $this->em->getRepository(Festivita::class)->giornoSuccessivo($data_succ);
    $info['url_succ'] = ($data_succ ? $data_succ->format('Y-m') : null);
    $data_prec = (clone $data_inizio);
    $data_prec = $this->em->getRepository(Festivita::class)->giornoPrecedente($data_prec);
    $info['url_prec'] = ($data_prec ? $data_prec->format('Y-m') : null);
    // presentazione calendario
    $info['inizio'] = (int) $mese->format('w') - 1;
    $m = clone $mese;
    $info['ultimo_giorno'] = $m->modify('last day of this month')->format('j');
    $info['fine'] = (int) $m->format('w') == 0 ? 0 : 6 - (int) $m->format('w');
    // recupera dati
    if ($classe) {
      // visualizzazione verifiche di classe
      $info['classe'] = ''.$classe;
      $info['classeId'] = $classe->getId();
      $dati = $this->em->getRepository(Avviso::class)->verificheClasse($classe, $mese);
    } else {
      // visualizzazione normale
      $info['classe'] = null;
      $info['classeId'] = 0;
      $dati = $age->agendaEventi($this->getUser(), $mese);
    }
    // festività
    $festivi = $this->em->getRepository(Festivita::class)->createQueryBuilder('f')
      ->where('f.sede IS NULL AND f.tipo=:tipo AND MONTH(f.data)=:mese')
      ->setParameter('tipo', 'F')
      ->setParameter('mese', $mese->format('n'))
      ->orderBy('f.data', 'ASC')
      ->getQuery()
      ->getResult();
    foreach ($festivi as $f) {
      $dati[(int) $f->getData()->format('j')]['festivo'] = 1;
    }
    // filtro classi
    $classi = $this->em->getRepository(Cattedra::class)->cattedreDocente($this->getUser(), 'Q');
    foreach ($classi as $c) {
      $dati['filtro'][$c->getClasse()->getId()] = ''.$c->getClasse();
    }
    // azione add
    if ($age->azioneEvento('add', new DateTime(), $this->getUser(), null)) {
      // pulsante add
      $dati['azioni']['add'] = 1;
    }
    // mostra la pagina di risposta
    return $this->render('agenda/eventi.html.twig', [
      'pagina_titolo' => 'page.agenda_eventi',
      'mese' => $mese,
      'info' => $info,
      'dati' => $dati]);
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
   */
  #[Route(path: '/agenda/eventi/dettagli/{data}/{tipo}', name: 'agenda_eventi_dettagli', requirements: ['data' => '\d\d\d\d-\d\d-\d\d', 'tipo' => 'C|A|V|P'], methods: ['GET'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function eventiDettagli(AgendaUtil $age, string $data, string $tipo): Response {
    // inizializza
    $dati = null;
    // data
    $data = DateTime::createFromFormat('Y-m-d', $data);
    // legge dati
    $dati = $age->dettagliEvento($this->getUser(), $data, $tipo);
    // visualizza pagina
    return $this->render('agenda/scheda_evento_'.$tipo.'.html.twig', [
      'dati' => $dati,
      'data' => $data]);
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
   */
  #[Route(path: '/agenda/verifica/edit/{id}', name: 'agenda_verifica_edit', requirements: ['id' => '\d+'], defaults: ['id' => '0'], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function verificaEdit(Request $request, TranslatorInterface $trans, MessageBusInterface $msg,
                               RegistroUtil $reg, BachecaUtil $bac, AgendaUtil $age,
                               LogHandler $dblogger, int $id): Response {
    // inizializza
    $dati = [];
    $lista_festivi = null;
    $verifiche = [];
    $docente = $this->getUser();
    $materia_sostegno = null;
    if ($request->isMethod('GET')) {
      // inizializza sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/agenda_verifica_edit/conferma', 0);
    }
    // controlla azione
    if ($id > 0) {
      // azione edit
      $avviso = $this->em->getRepository(Avviso::class)->findOneBy(['id' => $id, 'tipo' => 'V']);
      if (!$avviso) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $avviso_old = clone $avviso;
      $materia_sostegno = $avviso->getMateria() ? $avviso->getMateria()->getId() : null;
    } else {
      // azione add
      $oggi = new DateTime();
      $mese = $oggi;
      if ($this->reqstack->getSession()->get('/APP/ROUTE/agenda_eventi/mese')) {
        // recupera data da sessione
        $mese_sessione = DateTime::createFromFormat('Y-m-d', $this->reqstack->getSession()->get('/APP/ROUTE/agenda_eventi/mese').'-01');
        if ($mese_sessione > $oggi) {
          // ultimo giorno di mese precedente
          $mese = $mese_sessione;
          $mese->modify('-1 day');
        }
      }
      $mese = $this->em->getRepository(Festivita::class)->giornoSuccessivo($mese);
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
    $dati = $this->em->getRepository(Cattedra::class)->cattedreDocente($docente);
    $form = $this->createForm(AvvisoType::class, $avviso, ['form_mode' => 'verifica',
      'return_url' => $this->generateUrl('agenda_eventi'),
      'values' => [$dati['choice'], $materia_sostegno]]);
    $form->handleRequest($request);
    // visualizzazione filtri
    $dati['lista'] = '';
    if ($form->get('filtroTipo')->getData() == 'U') {
      $dati['lista'] = $this->em->getRepository(Alunno::class)->listaAlunni($form->get('filtro')->getData(), 'gs-filtro-');
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
        $materia = $this->em->getRepository(Cattedra::class)->createQueryBuilder('c')
          ->join('c.classe', 'cl')
          ->where("c.tipo='N' AND c.materia=:materia AND c.attiva=1 AND cl.anno=:anno AND cl.sezione=:sezione AND (cl.gruppo=:gruppo OR cl.gruppo='' OR cl.gruppo IS NULL)")
          ->setParameter('materia', $form->get('materia_sostegno')->getData())
          ->setParameter('anno', $avviso->getCattedra()->getClasse()->getAnno())
          ->setParameter('sezione', $avviso->getCattedra()->getClasse()->getSezione())
          ->setParameter('gruppo', $avviso->getCattedra()->getClasse()->getGruppo())
          ->getQuery()
          ->getOneOrNullResult();
          if (!$materia ||
            ($avviso->getCattedra()->getAlunno() && $avviso->getCattedra()->getAlunno()->getId() != $avviso->getFiltro()[0])) {
              $form->addError(new FormError($trans->trans('exception.cattedra_non_valida')));
        }
      }
      // controlla filtro
      $lista = [];
      $errore = false;
      if ($avviso->getFiltroTipo() == 'U') {
        $lista = $this->em->getRepository(Alunno::class)
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
            $this->em->getRepository(AvvisoUtente::class)->createQueryBuilder('au')
              ->delete()
              ->where('au.avviso=:avviso')
              ->setParameter('avviso', $avviso)
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
              ->setUtente($this->em->getReference(Utente::class, $u));
            $this->em->persist($obj);
          }
          // annotazione
          $log_annotazioni['delete'] = [];
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
            $dblogger->logAzione('AGENDA', 'Crea verifica', [
              'Avviso' => $avviso->getId(),
              'Annotazioni' => implode(', ', array_map(fn($a) => $a->getId(), $avviso->getAnnotazioni()->toArray()))]);
          } else {
            // modifica
            $dblogger->logAzione('AGENDA', 'Modifica verifica', [
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
              'Annotazioni create' => implode(', ', array_map(fn($a) => $a->getId(), $avviso->getAnnotazioni()->toArray()))]);
          }
          // redirezione
          return $this->redirectToRoute('agenda_eventi');
        }
      }
    }
    // mostra la pagina di risposta
    return $this->render('agenda/verifica_edit.html.twig', [
      'pagina_titolo' => 'page.agenda_verifica',
      'form' => $form,
      'form_title' => ($id > 0 ? 'title.modifica_verifica' : 'title.nuova_verifica'),
      'verifiche' => $verifiche,
      'lista_festivi' => $lista_festivi,
      'dati' => $dati]);
  }

  /**
   * Restituisce gli alunni della classe collegata alla cattedra indicata
   *
   * @param int $id Identificativo della cattedra
   *
   * @return JsonResponse Informazioni di risposta
   *
   */
  #[Route(path: '/agenda/cattedra/{id}', name: 'agenda_cattedra', requirements: ['id' => '\d+'], defaults: ['id' => 0], methods: ['GET'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function cattedraAjax(int $id): JsonResponse {
    $alunni = $this->em->getRepository(Alunno::class)->createQueryBuilder('a')
      ->select("a.id,CONCAT(a.cognome,' ',a.nome) AS nome")
      ->join(Cattedra::class, 'c', 'WITH', 'c.classe=a.classe')
      ->where('a.abilitato=:abilitato AND c.id=:cattedra AND c.attiva=:attiva')
      ->setParameter('abilitato', 1)
      ->setParameter('cattedra', $id)
      ->setParameter('attiva', 1)
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
   */
  #[Route(path: '/agenda/classe/{id}', name: 'agenda_classe', requirements: ['id' => '\d+'], defaults: ['id' => 0], methods: ['GET'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function classeAjax(int $id): JsonResponse {
    // solo cattedre attive e normali, no sostegno, no ed.civ.
    $classe = $this->em->getRepository(Classe::class)->find($id);
    $cattedre = $this->em->getRepository(Cattedra::class)->createQueryBuilder('c')
      ->select('DISTINCT m.id,m.nome,m.nomeBreve')
      ->join('c.materia', 'm')
      ->join('c.classe', 'cl')
      ->where("cl.anno=:anno AND cl.sezione=:sezione AND (cl.gruppo=:gruppo OR cl.gruppo IS NULL) AND c.attiva=1 AND c.tipo='N' AND m.tipo!='S' AND m.tipo!='E'")
      ->setParameter('anno', $classe->getAnno())
      ->setParameter('sezione', $classe->getSezione())
      ->setParameter('gruppo', $classe->getGruppo())
      ->orderBy('m.nomeBreve', 'ASC')
      ->getQuery()
      ->getArrayResult();
    // restituisce dati
    return new JsonResponse($cattedre);
  }

  /**
   * Cancella verifica
   *
   * @param LogHandler $dblogger Gestore dei log su database
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param BachecaUtil $bac Funzioni di utilità per la gestione della bacheca
   * @param AgendaUtil $age Funzioni di utilità per la gestione dell'agenda
   * @param int $id Identificativo dell'avviso
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/agenda/verifica/delete/{id}', name: 'agenda_verifica_delete', requirements: ['id' => '\d+'], methods: ['GET'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function verificaDelete(LogHandler $dblogger, RegistroUtil $reg,
                                 BachecaUtil $bac, AgendaUtil $age, int $id): Response {
    // controllo avviso
    $avviso = $this->em->getRepository(Avviso::class)->findOneBy(['id' => $id, 'tipo' => 'V']);
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
    $log_annotazioni = [];
    foreach ($avviso->getAnnotazioni() as $a) {
      $log_annotazioni[] = $a->getId();
      $this->em->remove($a);
    }
    // cancella destinatari
    $this->em->getRepository(AvvisoUtente::class)->createQueryBuilder('au')
      ->delete()
      ->where('au.avviso=:avviso')
      ->setParameter('avviso', $avviso)
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
    $dblogger->logAzione('AGENDA', 'Cancella verifica', [
      'Id' => $avviso_id,
      'Data' => $avviso->getData()->format('d/m/Y'),
      'Testo' => $avviso->getTesto(),
      'Cattedra' => $avviso->getCattedra()->getId(),
      'Materia' => $avviso->getMateria() ? $avviso->getMateria()->getId() : 0,
      'Filtro Tipo' => $avviso->getFiltroTipo(),
      'Filtro' => $avviso->getFiltro(),
      'Docente' => $avviso->getDocente()->getId(),
      'Annotazioni' => implode(', ', $log_annotazioni)]);
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
   */
  #[Route(path: '/agenda/compito/edit/{id}', name: 'agenda_compito_edit', requirements: ['id' => '\d+'], defaults: ['id' => '0'], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function compitoEdit(Request $request, TranslatorInterface $trans, MessageBusInterface $msg,
                              RegistroUtil $reg, BachecaUtil $bac, AgendaUtil $age,
                              LogHandler $dblogger, int $id): Response {
    // inizializza
    $dati = [];
    $lista_festivi = null;
    $compiti = [];
    $docente = $this->getUser();
    $materia_sostegno = null;
    if ($request->isMethod('GET')) {
      // inizializza sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/agenda_compito_edit/conferma', 0);
    }
    // controlla azione
    if ($id > 0) {
      // azione edit
      $avviso = $this->em->getRepository(Avviso::class)->findOneBy(['id' => $id, 'tipo' => 'P']);
      if (!$avviso) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $avviso_old = clone $avviso;
      $materia_sostegno = $avviso->getMateria() ? $avviso->getMateria()->getId() : null;
    } else {
      // azione add
      $oggi = new DateTime();
      $mese = $oggi;
      if ($this->reqstack->getSession()->get('/APP/ROUTE/agenda_eventi/mese')) {
        // recupera data da sessione
        $mese_sessione = DateTime::createFromFormat('Y-m-d', $this->reqstack->getSession()->get('/APP/ROUTE/agenda_eventi/mese').'-01');
        if ($mese_sessione > $oggi) {
          // ultimo giorno di mese precedente
          $mese = $mese_sessione;
          $mese->modify('-1 day');
        }
      }
      $mese = $this->em->getRepository(Festivita::class)->giornoSuccessivo($mese);
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
    $dati = $this->em->getRepository(Cattedra::class)->cattedreDocente($docente);
    $form = $this->createForm(AvvisoType::class, $avviso, ['form_mode' => 'compito',
      'return_url' => $this->generateUrl('agenda_eventi'),
      'values' => [$dati['choice'], $materia_sostegno]]);
    $form->handleRequest($request);
    // visualizzazione filtri
    $dati['lista'] = '';
    if ($form->get('filtroTipo')->getData() == 'U') {
      $dati['lista'] = $this->em->getRepository(Alunno::class)->listaAlunni($form->get('filtro')->getData(), 'gs-filtro-');
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
        $materia = $this->em->getRepository(Cattedra::class)->findOneBy(['materia' => $form->get('materia_sostegno')->getData(),
          'classe' => $avviso->getCattedra()->getClasse(), 'attiva' => 1]);
        if (!$materia ||
            ($avviso->getCattedra()->getAlunno() && $avviso->getCattedra()->getAlunno()->getId() != $avviso->getFiltro()[0])) {
          $form->addError(new FormError($trans->trans('exception.cattedra_non_valida')));
        }
      }
      // controlla filtro
      $lista = [];
      $errore = false;
      if ($avviso->getFiltroTipo() == 'U') {
        $lista = $this->em->getRepository(Alunno::class)
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
            $this->em->getRepository(AvvisoUtente::class)->createQueryBuilder('au')
              ->delete()
              ->where('au.avviso=:avviso')
              ->setParameter('avviso', $avviso)
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
              ->setUtente($this->em->getReference(Utente::class, $u));
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
            $dblogger->logAzione('AGENDA', 'Crea compito', [
              'Avviso' => $avviso->getId()]);
          } else {
            // modifica
            $dblogger->logAzione('AGENDA', 'Modifica compito', [
              'Avviso' => $avviso->getId(),
              'Data' => $avviso_old->getData()->format('d/m/Y'),
              'Cattedra' => $avviso_old->getCattedra()->getId(),
              'Materia' => $avviso_old->getMateria() ? $avviso_old->getMateria()->getId() : 0,
              'Testo' => $avviso_old->getTesto(),
              'Destinatari' => $avviso_old->getDestinatari(),
              'Filtro Tipo' => $avviso_old->getFiltroTipo(),
              'Filtro' => $avviso_old->getFiltro(),
              'Docente' => $avviso_old->getDocente()->getId()]);
          }
          // redirezione
          return $this->redirectToRoute('agenda_eventi');
        }
      }
    }
    // mostra la pagina di risposta
    return $this->render('agenda/compito_edit.html.twig', [
      'pagina_titolo' => 'page.agenda_compito',
      'form' => $form,
      'form_title' => ($id > 0 ? 'title.modifica_compito' : 'title.nuovo_compito'),
      'compiti' => $compiti,
      'lista_festivi' => $lista_festivi,
      'dati' => $dati]);
  }

  /**
   * Cancella compiti per casa
   *
   * @param LogHandler $dblogger Gestore dei log su database
   * @param AgendaUtil $age Funzioni di utilità per la gestione dell'agenda
   * @param int $id Identificativo dell'avviso
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/agenda/compito/delete/{id}', name: 'agenda_compito_delete', requirements: ['id' => '\d+'], methods: ['GET'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function compitoDelete(LogHandler $dblogger, AgendaUtil $age,
                                int $id): Response {
    // controllo avviso
    $avviso = $this->em->getRepository(Avviso::class)->findOneBy(['id' => $id, 'tipo' => 'P']);
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
    $this->em->getRepository(AvvisoUtente::class)->createQueryBuilder('au')
      ->delete()
      ->where('au.avviso=:avviso')
      ->setParameter('avviso', $avviso)
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
    $dblogger->logAzione('AGENDA', 'Cancella compito', [
      'Avviso' => $avviso_id,
      'Data' => $avviso->getData()->format('d/m/Y'),
      'Testo' => $avviso->getTesto(),
      'Cattedra' => $avviso->getCattedra()->getId(),
      'Materia' => $avviso->getMateria() ? $avviso->getMateria()->getId() : 0,
      'Filtro Tipo' => $avviso->getFiltroTipo(),
      'Filtro' => $avviso->getFiltro(),
      'Docente' => $avviso->getDocente()->getId()]);
    // redirezione
    return $this->redirectToRoute('agenda_eventi');
  }

  /**
   * Mostra i dettagli delle verifiche di una classe
   *
   * @param AgendaUtil $age Funzioni di utilità per la gestione dell'agenda
   * @param BachecaUtil $bac Funzioni di utilità per la gestione della bacheca
   * @param string $data Data dell'evento (AAAA-MM-GG)
   * @param int $classe Identificatore della classe
   *
   * @return Response Pagina di risposta
   */
  #[Route(path: '/agenda/verifiche/dettagli/{data}/{classe}', name: 'agenda_eventi_verifiche', requirements: ['data' => '\d\d\d\d-\d\d-\d\d', 'classe' => '\d+'], methods: ['GET'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function eventiVerifiche(BachecaUtil $bac, AgendaUtil $age, string $data, int $classe): Response {
    // inizializza
    $dati = [];
    // data
    $data = DateTime::createFromFormat('Y-m-d', $data);
    // controllo classe
    $classe = $this->em->getRepository(Classe::class)->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // legge dati
    $verifiche = $this->em->getRepository(Avviso::class)->dettagliVerificheClasse($classe, $data);
    foreach ($verifiche as $k => $v) {
      $dati['verifiche'][$k] = $bac->dettagliAvviso($v);
      // edit
      if ($age->azioneEvento('edit', $v->getData(), $this->getUser(), $v)) {
        // pulsante edit
        $dati['verifiche'][$k]['azioni']['edit'] = 1;
      }
      // delete
      if ($age->azioneEvento('delete', $v->getData(), $this->getUser(), $v)) {
        // pulsante delete
        $dati['verifiche'][$k]['azioni']['delete'] = 1;
      }
    }
    // visualizza pagina
    return $this->render('agenda/scheda_evento_V.html.twig', [
      'dati' => $dati,
      'data' => $data]);
  }

}
