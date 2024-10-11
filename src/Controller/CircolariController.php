<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Controller;

use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\ExpressionLanguage\Expression;
use DateTime;
use App\Entity\Sede;
use App\Entity\Classe;
use App\Entity\Materia;
use App\Entity\Utente;
use App\Entity\Alunno;
use App\Entity\Annotazione;
use App\Entity\Ata;
use App\Entity\Circolare;
use App\Entity\CircolareClasse;
use App\Entity\CircolareUtente;
use App\Entity\Docente;
use App\Entity\Genitore;
use App\Entity\Staff;
use App\Form\CircolareType;
use App\Message\CircolareMessage;
use App\MessageHandler\NotificaMessageHandler;
use App\Util\CircolariUtil;
use App\Util\LogHandler;
use App\Util\RegistroUtil;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\FlushBatchHandlersStamp;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;


/**
 * CircolariController - gestione delle circolari
 *
 * @author Antonello Dessì
 */
class CircolariController extends BaseController {

  /**
   * Aggiunge o modifica una circolare
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param CircolariUtil $circ Funzioni di utilità per le circolari
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $id Identificativo della circolare
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/circolari/edit/{id}', name: 'circolari_edit', requirements: ['id' => '\d+'], defaults: ['id' => '0'], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_STAFF')]
  public function edit(Request $request, TranslatorInterface $trans, RegistroUtil $reg,
                       CircolariUtil $circ, LogHandler $dblogger, int $id): Response {
    // inizializza
    $dati = [];
    $var_sessione = '/APP/FILE/circolari_edit/';
    $dir = $this->getParameter('dir_circolari').'/';
    $fs = new Filesystem();
    // controlla azione
    if ($id > 0) {
      // azione edit
      $circolare = $this->em->getRepository(Circolare::class)->findOneBy(['id' => $id]);
      if (!$circolare) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $circolare_old = clone $circolare;
    } else {
      // azione add
      $numero = $this->em->getRepository(Circolare::class)->prossimoNumero();
      $circolare = (new Circolare())
        ->setData(new DateTime('today'))
        ->setAnno((int) substr((string) $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_scolastico'), 0, 4))
        ->setNumero($numero);
      if ($this->getUser()->getSede()) {
        $circolare->addSedi($this->getUser()->getSede());
      }
      $this->em->persist($circolare);
    }
    // controllo permessi
    if (!$circ->azioneCircolare(($id > 0 ? 'edit' : 'add'), $circolare->getData(), $this->getUser(), ($id > 0 ? $circolare : null))) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // legge file
    $documento = [];
    $allegati = [];
    if ($request->isMethod('POST')) {
      // pagina inviata
      foreach ($this->reqstack->getSession()->get($var_sessione.'documento', []) as $f) {
        if ($f['type'] != 'removed') {
          // aggiunge allegato
          $documento[] = $f;
        }
      }
      foreach ($this->reqstack->getSession()->get($var_sessione.'allegati', []) as $f) {
        if ($f['type'] != 'removed') {
          // aggiunge allegato
          $allegati[] = $f;
        }
      }
    } else {
      // pagina iniziale
      if ($circolare->getDocumento()) {
        $f = new File($dir.$circolare->getDocumento());
        $documento[0]['type'] = 'existent';
        $documento[0]['temp'] = $circolare->getId().'.ID';
        $documento[0]['name'] = $f->getBasename('.'.$f->getExtension());
        $documento[0]['ext'] = $f->getExtension();
        $documento[0]['size'] = $f->getSize();
      }
      foreach ($circolare->getAllegati() as $k=>$a) {
        $f = new File($dir.$a);
        $allegati[$k]['type'] = 'existent';
        $allegati[$k]['temp'] = $circolare->getId().'-'.$k.'.ID';
        $allegati[$k]['name'] = $f->getBasename('.'.$f->getExtension());
        $allegati[$k]['ext'] = $f->getExtension();
        $allegati[$k]['size'] = $f->getSize();
      }
      // modifica dati sessione
      $this->reqstack->getSession()->remove($var_sessione.'documento');
      $this->reqstack->getSession()->remove($var_sessione.'allegati');
      $this->reqstack->getSession()->set($var_sessione.'documento', $documento);
      $this->reqstack->getSession()->set($var_sessione.'allegati', $allegati);
      // elimina file temporanei
      $finder = new Finder();
      $finder->in($this->getParameter('dir_tmp'))->date('< 1 day ago');
      foreach ($finder as $f) {
        $fs->remove($f);
      }
    }
    // form di inserimento
    $setSede = $this->getUser()->getSede() ? $this->getUser()->getSede()->getId() : null;
    if ($setSede) {
      $sede = $this->em->getRepository(Sede::class)->find($setSede);
      $opzioniSedi[$sede->getNomeBreve()] = $sede;
    } else {
      $opzioniSedi = $this->em->getRepository(Sede::class)->opzioni();
    }
    $opzioniClassi = $this->em->getRepository(Classe::class)->opzioni($setSede, true, false);
    $opzioniMaterie = $this->em->getRepository(Materia::class)->opzioni(true, false);
    $opzioniClassi2 = $this->em->getRepository(Classe::class)->opzioni($setSede, false);
    $form = $this->createForm(CircolareType::class, $circolare, [
      'return_url' => $this->generateUrl('circolari_gestione'),
      'values' => [$opzioniSedi, $opzioniClassi, $opzioniMaterie, $opzioniClassi2]]);
    $form->handleRequest($request);
    // visualizzazione filtro coordinatori
    $dati['coordinatori'] = ($form->get('coordinatori')->getData() == 'C' ?
      $this->em->getRepository(Classe::class)->listaClassi($form->get('filtroCoordinatori')->getData()) : '');
    $dati['docenti'] = ($form->get('docenti')->getData() == 'C' ?
      $this->em->getRepository(Classe::class)->listaClassi($form->get('filtroDocenti')->getData()) :
        ($form->get('docenti')->getData() == 'M' ?
        $this->em->getRepository(Materia::class)->listaMaterie($form->get('filtroDocenti')->getData()) :
          ($form->get('docenti')->getData() == 'U' ?
          $this->em->getRepository(Docente::class)->listaDocenti($form->get('filtroDocenti')->getData(), 'gs-filtroDocenti-') :'')));
    $dati['genitori'] = ($form->get('genitori')->getData() == 'C' ?
      $this->em->getRepository(Classe::class)->listaClassi($form->get('filtroGenitori')->getData()) :
        ($form->get('genitori')->getData() == 'U' ?
        $this->em->getRepository(Alunno::class)->listaAlunni($form->get('filtroGenitori')->getData(), 'gs-filtroGenitori-') :''));
    $dati['alunni'] = ($form->get('alunni')->getData() == 'C' ?
      $this->em->getRepository(Classe::class)->listaClassi($form->get('filtroAlunni')->getData()) :
        ($form->get('alunni')->getData() == 'U' ?
        $this->em->getRepository(Alunno::class)->listaAlunni($form->get('filtroAlunni')->getData(), 'gs-filtroAlunni-') :''));
    if ($form->isSubmitted()) {
      // lista sedi
      $sedi = [];
      foreach ($circolare->getSedi() as $s) {
        if (!$this->getUser()->getSede() || $this->getUser()->getSede() == $s) {
          // sede corretta
          $sedi[] = $s->getId();
        } else {
          // elimina sede
          $circolare->removeSedi($s);
        }
      }
      // controllo errori
      if (!$circolare->getData()) {
        // data non presente
        $form->addError(new FormError($trans->trans('exception.data_nulla')));
      }
      if (!$this->em->getRepository(Circolare::class)->controllaNumero($circolare)) {
        // numero presente
        $form->addError(new FormError($trans->trans('exception.circolare_numero_esiste')));
      }
      if (!$circolare->getOggetto()) {
        // oggetto non presente
        $form->addError(new FormError($trans->trans('exception.circolare_oggetto_nullo')));
      }
      if (count($sedi) == 0) {
        // sedi non definite
        $form->addError(new FormError($trans->trans('exception.circolare_sede_nulla')));
      }
      if (!$circolare->getDsga() && !$circolare->getAta() && $circolare->getCoordinatori() == 'N' &&
          $circolare->getDocenti() == 'N' && $circolare->getGenitori() == 'N' && $circolare->getAlunni() == 'N' &&
          empty($circolare->getAltri())) {
        // destinatari non definiti
        $form->addError(new FormError($trans->trans('exception.circolare_destinatari_nulli')));
      }
      if (count($documento) == 0) {
        // documento non inviato
        $form->addError(new FormError($trans->trans('exception.circolare_documento_nullo')));
      }
      // controlla filtro coordinatori
      $lista = [];
      $errore = false;
      if ($circolare->getCoordinatori() == 'C') {
        // controlla classi
        $lista = $this->em->getRepository(Classe::class)
          ->controllaClassi($sedi, $form->get('filtroCoordinatori')->getData(), $errore);
        if ($errore) {
          // classe non valida
          $form->addError(new FormError($trans->trans('exception.filtro_classi_invalido', ['dest' => 'dei Coordinatori'])));
        }
      }
      $circolare->setFiltroCoordinatori($lista);
      // controlla filtro docenti
      $lista = [];
      $errore = false;
      if ($circolare->getDocenti() == 'C') {
        // controlla classi
        $lista = $this->em->getRepository(Classe::class)
          ->controllaClassi($sedi, $form->get('filtroDocenti')->getData(), $errore);
        if ($errore) {
          // classe non valida
          $form->addError(new FormError($trans->trans('exception.filtro_classi_invalido', ['dest' => 'dei Docenti'])));
        }
      } elseif ($circolare->getDocenti() == 'M') {
        // controlla materie
        $lista = $this->em->getRepository(Materia::class)->controllaMaterie($form->get('filtroDocenti')->getData(), $errore);
        if ($errore) {
          // materia non valida
          $form->addError(new FormError($trans->trans('exception.filtro_materie_invalido')));
        }
      } elseif ($circolare->getDocenti() == 'U') {
        // controlla utenti
        $lista = $this->em->getRepository(Docente::class)
          ->controllaDocenti($sedi, $form->get('filtroDocenti')->getData(), $errore);
        if ($errore) {
          // utente non valido
          $form->addError(new FormError($trans->trans('exception.filtro_utenti_invalido', ['dest' => 'dei Docenti'])));
        }
      }
      $circolare->setFiltroDocenti($lista);
      // controlla filtro genitori
      $lista = [];
      $errore = false;
      if ($circolare->getGenitori() == 'C') {
        // controlla classi
        $lista = $this->em->getRepository(Classe::class)
          ->controllaClassi($sedi, $form->get('filtroGenitori')->getData(), $errore);
        if ($errore) {
          // classe non valida
          $form->addError(new FormError($trans->trans('exception.filtro_classi_invalido', ['dest' => 'dei Genitori'])));
        }
      } elseif ($circolare->getGenitori() == 'U') {
        // controlla utenti
        $lista = $this->em->getRepository(Alunno::class)
          ->controllaAlunni($sedi, $form->get('filtroGenitori')->getData(), $errore);
        if ($errore) {
          // utente non valido
          $form->addError(new FormError($trans->trans('exception.filtro_utenti_invalido', ['dest' => 'dei Genitori'])));
        }
      }
      $circolare->setFiltroGenitori($lista);
      // controlla filtro alunni
      $lista = [];
      $errore = false;
      if ($circolare->getAlunni() == 'C') {
        // controlla classi
        $lista = $this->em->getRepository(Classe::class)
          ->controllaClassi($sedi, $form->get('filtroAlunni')->getData(), $errore);
        if ($errore) {
          // classe non valida
          $form->addError(new FormError($trans->trans('exception.filtro_classi_invalido', ['dest' => 'degli Alunni'])));
        }
      } elseif ($circolare->getAlunni() == 'U') {
        // controlla utenti
        $lista = $this->em->getRepository(Alunno::class)
          ->controllaAlunni($sedi, $form->get('filtroAlunni')->getData(), $errore);
        if ($errore) {
          // utente non valido
          $form->addError(new FormError($trans->trans('exception.filtro_utenti_invalido', ['dest' => 'degli Alunni'])));
        }
      }
      $circolare->setFiltroAlunni($lista);
      // controlla altri
      $lista_altri = $circolare->getAltri();
      foreach ($lista_altri as $k=>$v) {
        $v = strtoupper(trim((string) $v));
        if (empty($v)) {
          unset($lista_altri[$k]);
        } else {
          $lista_altri[$k] = $v;
        }
      }
      if (count($lista_altri) != count($circolare->getAltri())) {
        // lista altri non valida
        $form->addError(new FormError($trans->trans('exception.lista_altri_invalida')));
      }
      $circolare->setAltri($lista_altri);
      // forza notifica SEMPRE
      $circolare->setNotifica(true);
      // forza firma MAI
      $circolare->setFirma(false);
      // modifica dati
      if ($form->isValid()) {
        // documento
        foreach ($this->reqstack->getSession()->get($var_sessione.'documento', []) as $f) {
          if ($f['type'] == 'uploaded') {
            // aggiunge documento
            $fs->rename($this->getParameter('dir_tmp').'/'.$f['temp'], $this->getParameter('dir_circolari').'/'.
              $f['temp'].'.'.$f['ext']);
            $circolare->setDocumento(new File($this->getParameter('dir_circolari').'/'.$f['temp'].'.'.$f['ext']));
          } elseif ($f['type'] == 'removed') {
            // rimuove documento
            $fs->remove($this->getParameter('dir_circolari').'/'.$f['name'].'.'.$f['ext']);
          }
        }
        // allegati
        foreach ($this->reqstack->getSession()->get($var_sessione.'allegati', []) as $f) {
          if ($f['type'] == 'uploaded') {
            // aggiunge allegato
            $fs->rename($this->getParameter('dir_tmp').'/'.$f['temp'], $this->getParameter('dir_circolari').'/'.
              $f['temp'].'.'.$f['ext']);
            $circolare->addAllegato(new File($this->getParameter('dir_circolari').'/'.$f['temp'].'.'.$f['ext']));
          } elseif ($f['type'] == 'removed') {
            // rimuove allegato
            $circolare->removeAllegato(new File($this->getParameter('dir_circolari').'/'.$f['name'].'.'.$f['ext']));
            $fs->remove($this->getParameter('dir_circolari').'/'.$f['name'].'.'.$f['ext']);
          }
        }
        // ok: memorizza dati
        $this->em->flush();
        // log azione
        if (!$id) {
          // nuovo
          $dblogger->logAzione('CIRCOLARI', 'Crea circolare', [
            'id' => $circolare->getId()]);
        } else {
          // modifica
          $dblogger->logAzione('CIRCOLARI', 'Modifica circolare', [
            'id' => $circolare->getId(),
            'Data' => $circolare_old->getData()->format('d/m/Y'),
            'Numero' => $circolare_old->getNumero(),
            'Oggetto' => $circolare_old->getOggetto(),
            'Documento' => $circolare_old->getDocumento(),
            'Allegati' => $circolare_old->getAllegati(),
            'Sedi' => implode(', ', array_map(fn($s) => $s->getId(), $circolare_old->getSedi()->toArray())),
            'Destinatari DSGA' => $circolare_old->getDsga(),
            'Destinatari ATA' => $circolare_old->getAta(),
            'Destinatari Coordinatori' => $circolare_old->getCoordinatori(),
            'Filtro Coordinatori' => $circolare_old->getFiltroCoordinatori(),
            'Destinatari Docenti' => $circolare_old->getDocenti(),
            'Filtro Docenti' => $circolare_old->getFiltroDocenti(),
            'Destinatari Genitori' => $circolare_old->getGenitori(),
            'Filtro Genitori' => $circolare_old->getFiltroGenitori(),
            'Destinatari Alunni' => $circolare_old->getAlunni(),
            'Filtro Alunni' => $circolare_old->getFiltroAlunni(),
            'Destinatari Altri' => $circolare_old->getAltri(),
            'Firma' => $circolare_old->getFirma(),
            'Pubblicata' => $circolare_old->getPubblicata()]);
        }
        // redirezione
        return $this->redirectToRoute('circolari_gestione');
      }
    }
    // mostra la pagina di risposta
    return $this->render('circolari/edit.html.twig', [
      'pagina_titolo' => 'page.staff_circolari',
      'form' => $form,
      'form_title' => ($id > 0 ? 'title.modifica_circolare' : 'title.nuova_circolare'),
      'documento' => $documento,
      'allegati' => $allegati,
      'dati' => $dati]);
  }

  /**
   * Cancella circolare
   *
   * @param LogHandler $dblogger Gestore dei log su database
   * @param CircolariUtil $circ Funzioni di utilità per le circolari
   * @param int $id Identificativo della circolare
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/circolari/delete/{id}', name: 'circolari_delete', requirements: ['id' => '\d+'], methods: ['GET'])]
  #[IsGranted('ROLE_STAFF')]
  public function delete(LogHandler $dblogger, CircolariUtil $circ,
                         int $id): Response {
    $dir = $this->getParameter('dir_circolari').'/';
    $fs = new Filesystem();
    // controllo circolare
    $circolare = $this->em->getRepository(Circolare::class)->find($id);
    if (!$circolare) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo permessi
    if (!$circ->azioneCircolare('delete', $circolare->getData(), $this->getUser(), $circolare)) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // cancella circolare
    $circolare_id = $circolare->getId();
    $circolare_sedi = implode(', ', array_map(fn($s) => $s->getId(), $circolare->getSedi()->toArray()));
    $this->em->remove($circolare);
    // ok: memorizza dati
    $this->em->flush();
    // cancella documento
    $f = new File($dir.$circolare->getDocumento());
    $fs->remove($f);
    // cancella allegati
    foreach ($circolare->getAllegati() as $a) {
      $f = new File($dir.$a);
      $fs->remove($f);
    }
    // log azione
    $dblogger->logAzione('CIRCOLARI', 'Cancella circolare', [
      'id' => $circolare_id,
      'Data' => $circolare->getData()->format('d/m/Y'),
      'Numero' => $circolare->getNumero(),
      'Oggetto' => $circolare->getOggetto(),
      'Documento' => $circolare->getDocumento(),
      'Allegati' => $circolare->getAllegati(),
      'Sedi' => $circolare_sedi,
      'Destinatari DSGA' => $circolare->getDsga(),
      'Destinatari ATA' => $circolare->getAta(),
      'Destinatari Coordinatori' => $circolare->getCoordinatori(),
      'Filtro Coordinatori' => $circolare->getFiltroCoordinatori(),
      'Destinatari Docenti' => $circolare->getDocenti(),
      'Filtro Docenti' => $circolare->getFiltroDocenti(),
      'Destinatari Genitori' => $circolare->getGenitori(),
      'Filtro Genitori' => $circolare->getFiltroGenitori(),
      'Destinatari Alunni' => $circolare->getAlunni(),
      'Filtro Alunni' => $circolare->getFiltroAlunni(),
      'Destinatari Altri' => $circolare->getAltri(),
      'Firma' => $circolare->getFirma(),
      'Pubblicata' => $circolare->getPubblicata()]);
    // redirezione
    return $this->redirectToRoute('circolari_gestione');
  }

  /**
   * Gestione delle circolari
   *
   * @param Request $request Pagina richiesta
   * @param CircolariUtil $circ Funzioni di utilità per le circolari
   * @param int $pagina Numero di pagina per l'elenco da visualizzare
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/circolari/gestione/{pagina}', name: 'circolari_gestione', requirements: ['pagina' => '\d+'], defaults: ['pagina' => '0'], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_STAFF')]
  public function gestione(Request $request, CircolariUtil $circ, int $pagina): Response {
    // inizializza variabili
    $dati = null;
    $limite = 20;
    // recupera criteri dalla sessione
    $search = [];
    $search['inizio'] = $this->reqstack->getSession()->get('/APP/ROUTE/circolari_gestione/inizio', null);
    $search['fine'] = $this->reqstack->getSession()->get('/APP/ROUTE/circolari_gestione/fine', null);
    $search['oggetto'] = $this->reqstack->getSession()->get('/APP/ROUTE/circolari_gestione/oggetto', '');
    if ($search['inizio']) {
      $inizio = DateTime::createFromFormat('Y-m-d', $search['inizio']);
    } else {
      $inizio = DateTime::createFromFormat('Y-m-d H:i', $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_inizio').' 00:00')
        ->modify('first day of this month');
      $search['inizio'] = $inizio->format('Y-m-d');
    }
    if ($search['fine']) {
      $fine = DateTime::createFromFormat('Y-m-d', $search['fine']);
    } else {
      $fine = new DateTime('tomorrow');
      $search['fine'] = $fine->format('Y-m-d');
    }
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $this->reqstack->getSession()->get('/APP/ROUTE/circolari_gestione/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/circolari_gestione/pagina', $pagina);
    }
    // form di ricerca
    $form = $this->container->get('form.factory')->createNamedBuilder('circolari_gestione', FormType::class)
      ->add('inizio', DateType::class, ['label' => 'label.data_inizio',
        'data' => $inizio,
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-picker'],
        'format' => 'dd/MM/yyyy',
        'required' => false])
      ->add('fine', DateType::class, ['label' => 'label.data_fine',
        'data' => $fine,
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-picker'],
        'format' => 'dd/MM/yyyy',
        'required' => false])
      ->add('oggetto', TextType::class, ['label' => 'label.oggetto',
        'data' => $search['oggetto'],
        'attr' => ['placeholder' => 'label.oggetto',],
        'label_attr' => ['class' => 'sr-only'],
        'required' => false])
      ->add('submit', SubmitType::class, ['label' => 'label.search'])
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $search['inizio'] = ($form->get('inizio')->getData() ? $form->get('inizio')->getData()->format('Y-m-d') : 0);
      $search['fine'] = ($form->get('fine')->getData() ? $form->get('fine')->getData()->format('Y-m-d') : 0);
      $search['oggetto'] = $form->get('oggetto')->getData();
      $pagina = 1;
      $this->reqstack->getSession()->set('/APP/ROUTE/circolari_gestione/inizio', $search['inizio']);
      $this->reqstack->getSession()->set('/APP/ROUTE/circolari_gestione/fine', $search['fine']);
      $this->reqstack->getSession()->set('/APP/ROUTE/circolari_gestione/oggetto', $search['oggetto']);
      $this->reqstack->getSession()->set('/APP/ROUTE/circolari_gestione/pagina', $pagina);
    }
    // recupera dati
    $dati = $circ->listaCircolari($search, $pagina, $limite, $this->getUser());
    // mostra la pagina di risposta
    return $this->render('circolari/gestione.html.twig', [
      'pagina_titolo' => 'page.circolari_gestione',
      'form' => $form->createView(),
      'form_help' => null,
      'form_success' => null,
      'page' => $pagina,
      'maxPages' => ceil($dati['lista']->count() / $limite),
      'dati' => $dati]);
  }

  /**
   * Pubblica la circolare o ne rimuove la pubblicazione
   *
   * @param MessageBusInterface $msg Gestione delle notifiche
   * @param LogHandler $dblogger Gestore dei log su database
   * @param CircolariUtil $circ Funzioni di utilità per le circolari
   * @param int $pubblica Valore 1 se si vuole pubblicare la circolare, 0 per togliere la pubblicazione
   * @param int $id Identificativo della circolare
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/circolari/publish/{pubblica}/{id}', name: 'circolari_publish', requirements: ['pubblica' => '0|1', 'id' => '\d+'], methods: ['GET'])]
  #[IsGranted('ROLE_STAFF')]
  public function publish(MessageBusInterface $msg, LogHandler $dblogger,
                          CircolariUtil $circ, int $pubblica, int $id): Response {
    // controllo circolare
    $circolare = $this->em->getRepository(Circolare::class)->find($id);
    if (!$circolare) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo permessi
    if (!$circ->azioneCircolare(($pubblica ? 'publish' : 'unpublish'), $circolare->getData(), $this->getUser(), $circolare)) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    if ($pubblica) {
      // aggiunge destinatari
      $dest = $circ->destinatari($circolare);
      // imposta utenti
      foreach ($dest['utenti'] as $u) {
        $obj = (new CircolareUtente())
          ->setCircolare($circolare)
          ->setUtente($this->em->getReference(Utente::class, $u));
        $this->em->persist($obj);
      }
      // imposta classi
      foreach ($dest['classi'] as $c) {
        $obj = (new CircolareClasse())
          ->setCircolare($circolare)
          ->setClasse($this->em->getReference(Classe::class, $c));
        $this->em->persist($obj);
      }
    } else {
      // rimuove destinatari
      $query = $this->em->getRepository(CircolareUtente::class)->createQueryBuilder('ce')
        ->delete()
        ->where('ce.circolare=:circolare')
        ->setParameters(['circolare' => $circolare])
        ->getQuery()
        ->execute();
      $query = $this->em->getRepository(CircolareClasse::class)->createQueryBuilder('cc')
        ->delete()
        ->where('cc.circolare=:circolare')
        ->setParameters(['circolare' => $circolare])
        ->getQuery()
        ->execute();
    }
    // pubblica
    $circolare->setPubblicata($pubblica);
    // ok: memorizza dati
    $this->em->flush();
    // log azione e notifica
    if ($pubblica) {
      // pubblicazione
      $oraNotifica = explode(':', (string) $this->reqstack->getSession()->get('/CONFIG/SCUOLA/notifica_circolari'));
      $tm = (new DateTime('today'))->setTime($oraNotifica[0], $oraNotifica[1]);
      if ($tm < new DateTime()) {
        // ora invio è già passata: inserisce in coda per domani
        $tm->modify('+1 day');
      }
      $msg->dispatch(new CircolareMessage($circolare->getId()),
        [DelayStamp::delayUntil($tm), new FlushBatchHandlersStamp(true)]);
      $dblogger->logAzione('CIRCOLARI', 'Pubblica circolare', [
        'Circolare ID' => $circolare->getId()]);
    } else {
      // rimuove pubblicazione
      NotificaMessageHandler::delete($this->em, (new CircolareMessage($circolare->getId()))->getTag());
      $dblogger->logAzione('CIRCOLARI', 'Rimuove pubblicazione circolare', [
        'Circolare ID' => $circolare->getId()]);
    }
    // redirezione
    return $this->redirectToRoute('circolari_gestione');
  }

  /**
   * Mostra i dettagli di una circolare
   *
   * @param CircolariUtil $circ Funzioni di utilità per le circolari
   * @param int $id ID della circolare
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/circolari/dettagli/gestione/{id}', name: 'circolari_dettagli_gestione', requirements: ['id' => '\d+'], methods: ['GET'])]
  #[IsGranted('ROLE_STAFF')]
  public function dettagliGestione(CircolariUtil $circ, int $id): Response {
    // inizializza
    $mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    $dati = null;
    // controllo circolare
    $circolare = $this->em->getRepository(Circolare::class)->find($id);
    if (!$circolare || !$circ->permessoLettura($circolare, $this->getUser())) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // legge dati
    $dati = $circ->dettagli($circolare);
    // visualizza pagina
    return $this->render('circolari/scheda_dettagli_gestione.html.twig', [
      'circolare' => $circolare,
      'mesi' => $mesi,
      'dati' => $dati]);
  }

  /**
   * Esegue il download di un documento di una circolare.
   *
   * @param CircolariUtil $circ Funzioni di utilità per le circolari
   * @param int $id ID della circolare
   * @param int $doc Numero del documento (0 per la circolare, 1.. per gli allegati)
   * @param string $tipo Tipo di risposta (V=visualizza, D=download)
   *
   * @return Response Documento inviato in risposta
   *
   */
  #[Route(path: '/circolari/download/{id}/{doc}/{tipo}', name: 'circolari_download', requirements: ['id' => '\d+', 'doc' => '\d+', 'tipo' => 'V|D'], defaults: ['tipo' => 'V'], methods: ['GET'])]
  #[IsGranted('ROLE_UTENTE')]
  public function download(CircolariUtil $circ, int $id, int $doc, string $tipo): Response {
    $dir = $this->getParameter('dir_circolari').'/';
    // controllo circolare
    $circolare = $this->em->getRepository(Circolare::class)->find($id);
    if (!$circolare) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo documenti
    if ($doc < 0 || $doc > count($circolare->getAllegati())) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo permessi
    if (!$circ->permessoLettura($circolare, $this->getUser())) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // file
    $nome = 'circolare-'.$circolare->getNumero();
    if ($doc == 0) {
      // documento principale
      $file = new File($dir.$circolare->getDocumento());
    } else {
      // allegato
      $file = new File($dir.$circolare->getAllegati()[$doc - 1]);
      $nome .= '-allegato-'.$doc;
    }
    $nome .= '.'.$file->getExtension();
    // segna lettura implicita
    if ($doc == 0 && !$circolare->getFirma()) {
      // dati di lettura implicita
      $cu = $this->em->getRepository(CircolareUtente::class)->findOneBy(['circolare' => $circolare,
        'utente' => $this->getUser()]);
      if ($cu && !$cu->getLetta()) {
        // imposta lettura
        $cu->setLetta(new DateTime());
        // memorizza dati
        $this->em->flush();
      }
    }
    // invia il documento
    return $this->file($file, $nome, ($tipo == 'V' ? ResponseHeaderBag::DISPOSITION_INLINE :
      ResponseHeaderBag::DISPOSITION_ATTACHMENT));
  }

  /**
   * Visualizza le circolari destinate ai genitori/alunni.
   *
   * @param Request $request Pagina richiesta
   * @param int $pagina Numero di pagina per l'elenco da visualizzare
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/circolari/genitori/{pagina}', name: 'circolari_genitori', requirements: ['pagina' => '\d+'], defaults: ['pagina' => '0'], methods: ['GET', 'POST'])]
  #[IsGranted(attribute: new Expression("is_granted('ROLE_GENITORE') or is_granted('ROLE_ALUNNO')"))]
  public function genitori(Request $request, int $pagina): Response {
    // inizializza
    $limite = 20;
    $mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    // crea lista mesi
    $anno_inizio = substr((string) $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_inizio', '2000'), 0, 4);
    $anno_fine = $anno_inizio + 1;
    $lista_mesi = [];
    for ($i=9; $i<=12; $i++) {
      $lista_mesi[$mesi[$i].' '.$anno_inizio] = $i;
    }
    for ($i=1; $i<=8; $i++) {
      $lista_mesi[$mesi[$i].' '.$anno_fine] = $i;
    }
    // recupera criteri dalla sessione
    $cerca = [];
    $cerca['visualizza'] = $this->reqstack->getSession()->get('/APP/ROUTE/circolari_genitori/visualizza', 'P');
    $cerca['mese'] = $this->reqstack->getSession()->get('/APP/ROUTE/circolari_genitori/mese', null);
    $cerca['oggetto'] = $this->reqstack->getSession()->get('/APP/ROUTE/circolari_genitori/oggetto', '');
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $this->reqstack->getSession()->get('/APP/ROUTE/circolari_genitori/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/circolari_genitori/pagina', $pagina);
    }
    // form di ricerca
    $form = $this->container->get('form.factory')->createNamedBuilder('circolari_genitori', FormType::class)
      ->add('visualizza', ChoiceType::class, ['label' => 'label.circolari_filtro_visualizza',
        'data' => $cerca['visualizza'],
        'choices' => ['label.circolari_da_leggere' => 'D', 'label.circolari_tutte' => 'P'],
        'label_attr' => ['class' => 'sr-only'],
        'choice_attr' => fn() => ['class' => 'gs-no-placeholder'],
        'attr' => ['class' => 'gs-placeholder'],
        'required' => true])
      ->add('mese', ChoiceType::class, ['label' => 'label.filtro_mese',
        'data' => $cerca['mese'],
        'choices' => $lista_mesi,
        'placeholder' => 'label.circolari_tutte',
        'choice_translation_domain' => false,
        'label_attr' => ['class' => 'sr-only'],
        'choice_attr' => fn() => ['class' => 'gs-no-placeholder'],
        'attr' => ['class' => 'gs-placeholder'],
        'required' => false])
      ->add('oggetto', TextType::class, ['label' => 'label.circolari_filtro_oggetto',
        'data' => $cerca['oggetto'],
        'attr' => ['placeholder' => 'label.oggetto', 'class' => 'gs-placeholder'],
        'label_attr' => ['class' => 'sr-only'],
        'required' => false])
      ->add('submit', SubmitType::class, ['label' => 'label.search'])
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $cerca['visualizza'] = $form->get('visualizza')->getData();
      $cerca['mese'] = $form->get('mese')->getData();
      $cerca['oggetto'] = $form->get('oggetto')->getData();
      $pagina = 1;
      $this->reqstack->getSession()->set('/APP/ROUTE/circolari_genitori/visualizza', $cerca['visualizza']);
      $this->reqstack->getSession()->set('/APP/ROUTE/circolari_genitori/mese', $cerca['mese']);
      $this->reqstack->getSession()->set('/APP/ROUTE/circolari_genitori/oggetto', $cerca['oggetto']);
      $this->reqstack->getSession()->set('/APP/ROUTE/circolari_genitori/pagina', $pagina);
    }
    // legge le circolari
    $dati = $this->em->getRepository(Circolare::class)->lista($cerca, $pagina, $limite, $this->getUser());
    // mostra la pagina di risposta
    return $this->render('circolari/genitori.html.twig', [
      'pagina_titolo' => 'page.circolari_genitori',
      'form' => $form->createView(),
      'form_help' => null,
      'form_success' => null,
      'page' => $pagina,
      'maxPages' => ceil($dati['lista']->count() / $limite),
      'dati' => $dati,
      'mesi' => $mesi]);
  }

  /**
   * Mostra i dettagli di una circolare ai destinatari
   *
   * @param CircolariUtil $circ Funzioni di utilità per le circolari
   * @param int $id ID della circolare
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/circolari/dettagli/destinatari/{id}', name: 'circolari_dettagli_destinatari', requirements: ['id' => '\d+'], methods: ['GET'])]
  #[IsGranted('ROLE_UTENTE')]
  public function dettagliDestinatari(CircolariUtil $circ, int $id): Response {
    // inizializza
    $mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    $dati = null;
    // controllo circolare
    $circolare = $this->em->getRepository(Circolare::class)->findOneBy(['id' => $id, 'pubblicata' => 1]);
    if (!$circolare || !$circ->permessoLettura($circolare, $this->getUser())) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // dati destinatario
    $cu = $this->em->getRepository(CircolareUtente::class)->findOneBy(['circolare' => $circolare,
      'utente' => $this->getUser()]);
    // visualizza pagina
    return $this->render('circolari/scheda_dettagli_destinatari.html.twig', [
      'circolare' => $circolare,
      'circolare_utente' => $cu,
      'mesi' => $mesi]);
  }

  /**
   * Mostra i dettagli di una circolare allo staff
   *
   * @param CircolariUtil $circ Funzioni di utilità per le circolari
   * @param int $id ID della circolare
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/circolari/dettagli/staff/{id}', name: 'circolari_dettagli_staff', requirements: ['id' => '\d+'], methods: ['GET'])]
  #[IsGranted('ROLE_STAFF')]
  public function dettagliStaff(CircolariUtil $circ, int $id): Response {
    // inizializza
    $mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    $dati = null;
    // controllo circolare
    $circolare = $this->em->getRepository(Circolare::class)->findOneBy(['id' => $id, 'pubblicata' => 1]);
    if (!$circolare || !$circ->permessoLettura($circolare, $this->getUser())) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // dati circolare
    $dati = $circ->dettagli($circolare);
    // dati destinatario
    $cu = $this->em->getRepository(CircolareUtente::class)->findOneBy(['circolare' => $circolare,
      'utente' => $this->getUser()]);
    // visualizza pagina
    return $this->render('circolari/scheda_dettagli_staff.html.twig', [
      'circolare' => $circolare,
      'circolare_utente' => $cu,
      'mesi' => $mesi,
      'dati' => $dati]);
  }

  /**
   * Conferma la lettura della circolare da parte dell'utente
   *
   * @param CircolariUtil $circ Funzioni di utilità per le circolari
   * @param int $id ID della circolare
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/circolari/firma/{id}', name: 'circolari_firma', requirements: ['id' => '\d+'], methods: ['GET'])]
  #[IsGranted('ROLE_UTENTE')]
  public function firma(CircolariUtil $circ, int $id): Response {
    // controllo circolare
    $circolare = $this->em->getRepository(Circolare::class)->findOneBy(['id' => $id, 'pubblicata' => 1]);
    if (!$circolare || !$circ->permessoLettura($circolare, $this->getUser())) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // firma
    $this->em->getRepository(Circolare::class)->firma($circolare, $this->getUser());
    // redirect
    if ($this->getUser() instanceOf Genitore || $this->getUser() instanceOf Alunno) {
      // genitori/alunni
      return $this->redirectToRoute('circolari_genitori');
    } elseif ($this->getUser() instanceOf Docente) {
      // docente/staff
      return $this->redirectToRoute('circolari_docenti');
    } elseif ($this->getUser() instanceOf Ata) {
      // ata
      return $this->redirectToRoute('circolari_ata');
    } else {
      // errore: non previsto
      throw $this->createNotFoundException('exception.id_notfound');
    }
  }

  /**
   * Visualizza le circolari destinate ai docenti (se staff tutte).
   *
   * @param Request $request Pagina richiesta
   * @param CircolariUtil $circ Funzioni di utilità per le circolari
   * @param int $pagina Numero di pagina per l'elenco da visualizzare
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/circolari/docenti/{pagina}', name: 'circolari_docenti', requirements: ['pagina' => '\d+'], defaults: ['pagina' => '0'], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function docenti(Request $request, CircolariUtil $circ, int $pagina): Response {
    // inizializza
    $limite = 20;
    $mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    // recupera criteri dalla sessione
    $cerca = [];
    $cerca['anno'] = $this->reqstack->getSession()->get('/APP/ROUTE/circolari_docenti/anno',
      substr((string) $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_inizio', '2000'), 0, 4));
    $cerca['visualizza'] = $this->reqstack->getSession()->get('/APP/ROUTE/circolari_docenti/visualizza',
      ($this->getUser() instanceOf Staff ? 'T' : 'P'));
    $cerca['mese'] = $this->reqstack->getSession()->get('/APP/ROUTE/circolari_docenti/mese', null);
    $cerca['oggetto'] = $this->reqstack->getSession()->get('/APP/ROUTE/circolari_docenti/oggetto', '');
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $this->reqstack->getSession()->get('/APP/ROUTE/circolari_docenti/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/circolari_docenti/pagina', $pagina);
    }
    // crea lista anni
    $lista_anni = $this->em->getRepository(Circolare::class)->anniScolastici();
    // crea lista mesi
    $lista_mesi = [];
    for ($i=9; $i<=12; $i++) {
      $lista_mesi[$mesi[$i]] = $i;
    }
    for ($i=1; $i<=8; $i++) {
      $lista_mesi[$mesi[$i]] = $i;
    }
    // form di ricerca
    $form = $this->container->get('form.factory')->createNamedBuilder('circolari_docenti', FormType::class)
      ->add('anno', ChoiceType::class, ['label' => 'label.filtro_anno_scolastico',
      'data' => $cerca['anno'],
      'choices' => $lista_anni,
      'choice_translation_domain' => false,
      'label_attr' => ['class' => 'sr-only'],
      'choice_attr' => fn() => ['class' => 'gs-no-placeholder'],
      'attr' => ['class' => 'gs-placeholder'],
      'required' => true])
      ->add('visualizza', ChoiceType::class, ['label' => 'label.circolari_filtro_visualizza',
        'data' => $cerca['visualizza'],
        'choices' => ['label.circolari_da_leggere' => 'D', 'label.circolari_proprie' => 'P',
          'label.circolari_tutte' => 'T'],
          'label_attr' => ['class' => 'sr-only'],
          'choice_attr' => fn() => ['class' => 'gs-no-placeholder'],
          'attr' => ['class' => 'gs-placeholder'],
          'required' => true])
      ->add('mese', ChoiceType::class, ['label' => 'label.filtro_mese',
        'data' => $cerca['mese'],
        'choices' => $lista_mesi,
        'placeholder' => 'label.circolari_tutte',
        'choice_translation_domain' => false,
        'label_attr' => ['class' => 'sr-only'],
        'choice_attr' => fn() => ['class' => 'gs-no-placeholder'],
        'attr' => ['class' => 'gs-placeholder'],
        'required' => false])
      ->add('oggetto', TextType::class, ['label' => 'label.circolari_filtro_oggetto',
        'data' => $cerca['oggetto'],
        'attr' => ['placeholder' => 'label.oggetto', 'class' => 'gs-placeholder'],
        'label_attr' => ['class' => 'sr-only'],
        'required' => false])
      ->add('submit', SubmitType::class, ['label' => 'label.filtra',
        'attr' => ['class' => 'btn-primary']])
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $cerca['anno'] = $form->get('anno')->getData();
      $cerca['visualizza'] = $form->get('visualizza')->getData();
      $cerca['mese'] = $form->get('mese')->getData();
      $cerca['oggetto'] = $form->get('oggetto')->getData();
      $pagina = 1;
      $this->reqstack->getSession()->set('/APP/ROUTE/circolari_docenti/anno', $cerca['anno']);
      $this->reqstack->getSession()->set('/APP/ROUTE/circolari_docenti/visualizza', $cerca['visualizza']);
      $this->reqstack->getSession()->set('/APP/ROUTE/circolari_docenti/mese', $cerca['mese']);
      $this->reqstack->getSession()->set('/APP/ROUTE/circolari_docenti/oggetto', $cerca['oggetto']);
      $this->reqstack->getSession()->set('/APP/ROUTE/circolari_docenti/pagina', $pagina);
    }
    // legge le circolari
    $dati = $this->em->getRepository(Circolare::class)->lista($cerca, $pagina, $limite, $this->getUser());
    $dati['annoCorrente'] = count($lista_anni) > 0 ? array_values($lista_anni)[0] : '';
    if ($this->getUser() instanceOf Staff) {
      // legge dettagli su circolari
      foreach ($dati['lista'] as $c) {
        $dati['info'][$c->getId()] = $circ->dettagli($c);
      }
    }
    // mostra la pagina di risposta
    return $this->render(($this->getUser() instanceOf Staff ? 'circolari/staff.html.twig' : 'circolari/docenti.html.twig'), [
      'pagina_titolo' => 'page.circolari_docenti',
      'form' => $form->createView(),
      'form_help' => null,
      'form_success' => null,
      'page' => $pagina,
      'maxPages' => ceil($dati['lista']->count() / $limite),
      'dati' => $dati,
      'mesi' => $mesi]);
  }

  /**
   * Visualizza le circolari destinate al personale ATA.
   *
   * @param Request $request Pagina richiesta
   * @param int $pagina Numero di pagina per l'elenco da visualizzare
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/circolari/ata/{pagina}', name: 'circolari_ata', requirements: ['pagina' => '\d+'], defaults: ['pagina' => '0'], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_ATA')]
  public function ata(Request $request, int $pagina): Response {
    // inizializza
    $limite = 20;
    $mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    // recupera criteri dalla sessione
    $cerca = [];
    $cerca['anno'] = $this->reqstack->getSession()->get('/APP/ROUTE/circolari_ata/anno',
      substr((string) $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_inizio', '2000'), 0, 4));
    $cerca['visualizza'] = $this->reqstack->getSession()->get('/APP/ROUTE/circolari_ata/visualizza', 'T');
    $cerca['mese'] = $this->reqstack->getSession()->get('/APP/ROUTE/circolari_ata/mese', null);
    $cerca['oggetto'] = $this->reqstack->getSession()->get('/APP/ROUTE/circolari_ata/oggetto', '');
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $this->reqstack->getSession()->get('/APP/ROUTE/circolari_ata/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/circolari_ata/pagina', $pagina);
    }
    // crea lista anni
    $lista_anni = $this->em->getRepository(Circolare::class)->anniScolastici();
    // crea lista mesi
    $lista_mesi = [];
    for ($i=9; $i<=12; $i++) {
      $lista_mesi[$mesi[$i]] = $i;
    }
    for ($i=1; $i<=8; $i++) {
      $lista_mesi[$mesi[$i]] = $i;
    }
    // form di ricerca
    $form = $this->container->get('form.factory')->createNamedBuilder('circolari_ata', FormType::class)
      ->add('anno', ChoiceType::class, ['label' => 'label.filtro_anno_scolastico',
        'data' => $cerca['anno'],
        'choices' => $lista_anni,
        'choice_translation_domain' => false,
        'label_attr' => ['class' => 'sr-only'],
        'choice_attr' => fn() => ['class' => 'gs-no-placeholder'],
        'attr' => ['class' => 'gs-placeholder'],
        'required' => true])
      ->add('visualizza', ChoiceType::class, ['label' => 'label.circolari_filtro_visualizza',
        'data' => $cerca['visualizza'],
        'choices' => ['label.circolari_da_leggere' => 'D', 'label.circolari_proprie' => 'P',
          'label.circolari_tutte' => 'T'],
        'label_attr' => ['class' => 'sr-only'],
        'choice_attr' => fn() => ['class' => 'gs-no-placeholder'],
        'attr' => ['class' => 'gs-placeholder'],
        'required' => true])
      ->add('mese', ChoiceType::class, ['label' => 'label.filtro_mese',
        'data' => $cerca['mese'],
        'choices' => $lista_mesi,
        'placeholder' => 'label.circolari_tutte',
        'choice_translation_domain' => false,
        'label_attr' => ['class' => 'sr-only'],
        'choice_attr' => fn() => ['class' => 'gs-no-placeholder'],
        'attr' => ['class' => 'gs-placeholder'],
        'required' => false])
      ->add('oggetto', TextType::class, ['label' => 'label.circolari_filtro_oggetto',
        'data' => $cerca['oggetto'],
        'attr' => ['placeholder' => 'label.oggetto', 'class' => 'gs-placeholder'],
        'label_attr' => ['class' => 'sr-only'],
        'required' => false])
      ->add('submit', SubmitType::class, ['label' => 'label.search'])
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $cerca['anno'] = $form->get('anno')->getData();
      $cerca['visualizza'] = $form->get('visualizza')->getData();
      $cerca['mese'] = $form->get('mese')->getData();
      $cerca['oggetto'] = $form->get('oggetto')->getData();
      $pagina = 1;
      $this->reqstack->getSession()->set('/APP/ROUTE/circolari_ata/anno', $cerca['anno']);
      $this->reqstack->getSession()->set('/APP/ROUTE/circolari_ata/visualizza', $cerca['visualizza']);
      $this->reqstack->getSession()->set('/APP/ROUTE/circolari_ata/mese', $cerca['mese']);
      $this->reqstack->getSession()->set('/APP/ROUTE/circolari_ata/oggetto', $cerca['oggetto']);
      $this->reqstack->getSession()->set('/APP/ROUTE/circolari_ata/pagina', $pagina);
    }
    // legge le circolari
    $dati = $this->em->getRepository(Circolare::class)->lista($cerca, $pagina, $limite, $this->getUser());
    $dati['annoCorrente'] = count($lista_anni) > 0 ? array_values($lista_anni)[0] : '';
    // mostra la pagina di risposta
    return $this->render('circolari/ata.html.twig', [
      'pagina_titolo' => 'page.circolari_ata',
      'form' => $form->createView(),
      'form_help' => null,
      'form_success' => null,
      'page' => $pagina,
      'maxPages' => ceil($dati['lista']->count() / $limite),
      'dati' => $dati,
      'mesi' => $mesi]);
  }

  /**
   * Mostra le circolari destinate agli alunni della classe
   *
   * @param int $classe ID della classe
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/circolari/classi/{classe}', name: 'circolari_classi', requirements: ['classe' => '\d+'], methods: ['GET'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function classi(int $classe): Response {
    // inizializza
    $dati = null;
    // controllo classe
    $classe = $this->em->getRepository(Classe::class)->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // legge dati
    $dati = $this->em->getRepository(Circolare::class)->circolariClasse($classe);
    // visualizza pagina
    return $this->render('circolari/scheda_dettagli_classe.html.twig', [
      'dati' => $dati,
      'classe' => $classe]);
  }

  /**
   * Conferma la lettura della circolare alla classe
   *
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $classe ID della classe
   * @param int $id ID della circolare (0 indica tutte)
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/circolari/firma/classe/{classe}/{id}', name: 'circolari_firma_classe', requirements: ['classe' => '\d+', 'id' => '\d+'], methods: ['GET'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function firmaClasse(TranslatorInterface $trans, LogHandler $dblogger,
                              int $classe, int $id): Response {
    // controllo classe
    $classe = $this->em->getRepository(Classe::class)->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // firma
    $firme = $this->em->getRepository(Circolare::class)->firmaClasse($classe, $id);
    if (count($firme) > 0) {
      // lista circolari
      $lista = implode(', ', array_map(fn($c) => $c->getNumero(), $firme));
      // testo annotazione
      $testo = $trans->trans('message.registro_lettura_circolare',
        ['num' => count($firme), 'circolari' => $lista]);
      // crea annotazione
      $a = (new Annotazione())
        ->setData(new DateTime('today'))
        ->setTesto($testo)
        ->setVisibile(false)
        ->setClasse($classe)
        ->setDocente($this->getUser());
      $this->em->persist($a);
      $this->em->flush();
      // log
      $dblogger->logAzione('CIRCOLARI', 'Lettura in classe', [
        'Annotazione' => $a->getId(),
        'Circolari' => $lista]);
    }
    // redirect
    return $this->redirectToRoute('lezioni');
  }

}
