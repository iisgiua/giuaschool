<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Controller;

use Symfony\Component\Security\Http\Attribute\IsGranted;
use DateTime;
use IntlDateFormatter;
use App\Entity\Cattedra;
use App\Entity\Classe;
use App\Entity\Festivita;
use App\Entity\Alunno;
use App\Entity\OsservazioneAlunno;
use App\Entity\OsservazioneClasse;
use App\Form\MessageType;
use App\Util\LogHandler;
use App\Util\RegistroUtil;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


/**
 * OsservazioniController - gestione delle osservazioni sugli alunni
 *
 * @author Antonello Dessì
 */
class OsservazioniController extends BaseController {

  /**
   * Gestione delle osservazioni sugli alunni
   *
   * @param Request $request Pagina richiesta
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param int $cattedra Identificativo della cattedra
   * @param int $classe Identificativo della classe (supplenza)
   * @param string $data Data del giorno da visualizzare (AAAA-MM-GG)
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/lezioni/osservazioni/{cattedra}/{classe}/{data}', name: 'lezioni_osservazioni', requirements: ['cattedra' => '\d+', 'classe' => '\d+', 'data' => '\d\d\d\d-\d\d-\d\d'], defaults: ['cattedra' => 0, 'classe' => 0, 'data' => '0000-00-00'], methods: ['GET'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function osservazioni(Request $request, RegistroUtil $reg, int $cattedra, int $classe,
                               string $data): Response {
    // inizializza variabili
    $lista_festivi = null;
    $errore = null;
    $settimana = ['Domenica', 'Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato'];
    $mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    $info = [];
    $info['sostegno'] = false;
    $dati = null;
    $template = 'lezioni/osservazioni.html.twig';
    $data_succ = null;
    $data_prec = null;
    // parametri cattedra/classe
    if ($cattedra == 0 && $classe == 0) {
      // recupera parametri da sessione
      $cattedra = $this->reqstack->getSession()->get('/APP/DOCENTE/cattedra_lezione');
      $classe = $this->reqstack->getSession()->get('/APP/DOCENTE/classe_lezione');
    } else {
      // memorizza su sessione
      $this->reqstack->getSession()->set('/APP/DOCENTE/cattedra_lezione', $cattedra);
      $this->reqstack->getSession()->set('/APP/DOCENTE/classe_lezione', $classe);
    }
    // parametro data
    if ($data == '0000-00-00') {
      // data non specificata
      if ($this->reqstack->getSession()->get('/APP/DOCENTE/data_lezione')) {
        // recupera data da sessione
        $data_obj = DateTime::createFromFormat('Y-m-d', $this->reqstack->getSession()->get('/APP/DOCENTE/data_lezione'));
      } else {
        // imposta data odierna
        $data_obj = new DateTime();
      }
    } else {
      // imposta data indicata e la memorizza in sessione
      $data_obj = DateTime::createFromFormat('Y-m-d', $data);
      $this->reqstack->getSession()->set('/APP/DOCENTE/data_lezione', $data);
    }
    // data in formato stringa
    $formatter = new IntlDateFormatter('it_IT', IntlDateFormatter::SHORT, IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $info['data_label'] =  $formatter->format($data_obj);
    // controllo cattedra/supplenza
    if ($cattedra > 0) {
      // lezione in propria cattedra: controlla esistenza
      $cattedra = $this->em->getRepository(Cattedra::class)->findOneBy(['id' => $cattedra,
        'docente' => $this->getUser(), 'attiva' => 1]);
      if (!$cattedra) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      // informazioni necessarie
      $classe = $cattedra->getClasse();
      $info['materia'] = $cattedra->getMateria()->getNomeBreve();
      $info['alunno'] = $cattedra->getAlunno();
    } elseif ($classe > 0) {
      // supplenza
      $classe = $this->em->getRepository(Classe::class)->find($classe);
      if (!$classe) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      // informazioni necessarie
      $cattedra = null;
    }
    if ($cattedra) {
      // recupera dati
      if ($cattedra->getMateria()->getTipo() == 'S') {
        $dati = $reg->osservazioniSostegno($data_obj, $this->getUser(), $cattedra);
        $info['sostegno'] = true;
        $template = 'lezioni/osservazioni_sostegno.html.twig';
      } else {
        $dati = $reg->osservazioni($data_obj, $this->getUser(), $cattedra);
      }
      // data prec/succ
      $data_succ = (clone $data_obj);
      $data_succ = $this->em->getRepository(Festivita::class)->giornoSuccessivo($data_succ);
      $data_prec = (clone $data_obj);
      $data_prec = $this->em->getRepository(Festivita::class)->giornoPrecedente($data_prec);
      // recupera festivi per calendario
      $lista_festivi = $reg->listaFestivi($classe->getSede());
      // controllo data
      $errore = $reg->controlloData($data_obj, $classe->getSede());
      if ($errore) {
        unset($dati['add']);
      }
    }
    // salva pagina visitata
    $route = ['name' => $request->get('_route'), 'param' => $request->get('_route_params')];
    $this->reqstack->getSession()->set('/APP/DOCENTE/menu_lezione', $route);
    // visualizza pagina
    return $this->render($template, [
      'pagina_titolo' => 'page.lezioni_osservazioni',
      'cattedra' => $cattedra,
      'classe' => $classe,
      'data' => $data_obj->format('Y-m-d'),
      'data_succ' => $data_succ,
      'data_prec' => $data_prec,
      'settimana' => $settimana,
      'mesi' => $mesi,
      'errore' => $errore,
      'lista_festivi' => $lista_festivi,
      'info' => $info,
      'dati' => $dati]);
  }

  /**
   * Aggiunge o modifica un'osservazione su un alunno
   *
   * @param Request $request Pagina richiesta
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $cattedra Identificativo della cattedra
   * @param string $data Data del giorno
   * @param int $id Identificativo dell'osservazione (se nullo aggiunge)
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/lezioni/osservazioni/edit/{cattedra}/{data}/{id}', name: 'lezioni_osservazioni_edit', requirements: ['cattedra' => '\d+', 'data' => '\d\d\d\d-\d\d-\d\d', 'id' => '\d+'], defaults: ['id' => 0], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function osservazioneEdit(Request $request, RegistroUtil $reg,
                                   LogHandler $dblogger, int $cattedra, string $data,
                                   int $id): Response {
    // inizializza
    $label = [];
    // controlla cattedra
    $cattedra = $this->em->getRepository(Cattedra::class)->findOneBy(['id' => $cattedra,
      'docente' => $this->getUser(), 'attiva' => 1]);
    if (!$cattedra) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla data
    $data_obj = DateTime::createFromFormat('Y-m-d', $data);
    $errore = $reg->controlloData($data_obj, $cattedra->getClasse()->getSede());
    if ($errore) {
      // errore: festivo
      throw $this->createNotFoundException('exception.invalid_params');
    }
    if ($id > 0) {
      // azione edit, controlla ossservazione
      $osservazione = $this->em->getRepository(OsservazioneAlunno::class)->findOneBy(['id' => $id,
        'data' => $data_obj]);
      if (!$osservazione) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $osservazione_old['testo'] = $osservazione->getTesto();
      $osservazione_old['alunno'] = $osservazione->getAlunno();
      $osservazione_old['cattedra'] = $osservazione->getCattedra();
      $osservazione
        ->setCattedra($cattedra);
    } else {
      // azione add
      $osservazione = (new OsservazioneAlunno())
        ->setData($data_obj)
        ->setCattedra($cattedra);
      if ($cattedra->getMateria()->getTipo() == 'S') {
        // cattedra di sostegno
        if ($cattedra->getAlunno()) {
          $osservazione->setAlunno($cattedra->getAlunno());
        }
      }
    }
    // controlla permessi
    if (!$reg->azioneOsservazione(($id > 0 ? 'edit' : 'add'), $data_obj, $this->getUser(),
                                   $cattedra->getClasse(), ($id > 0 ? $osservazione : null))) {
      // errore: azione non permessa
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // dati in formato stringa
    $formatter = new IntlDateFormatter('it_IT', IntlDateFormatter::SHORT, IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $label['data'] =  $formatter->format($data_obj);
    $label['docente'] = $this->getUser()->getNome().' '.$this->getUser()->getCognome();
    $label['classe'] = ''.$cattedra->getClasse();
    // lista alunni della classe
    $listaAlunni = $reg->alunniInData($data_obj, $cattedra->getClasse());
    // form di inserimento
    $religione = ($cattedra->getMateria()->getTipo() == 'R' && $cattedra->getTipo() == 'A') ? 'A' :
      ($cattedra->getMateria()->getTipo() == 'R' ? 'S' : '');
    $form = $this->container->get('form.factory')->createNamedBuilder('osservazione_edit', FormType::class, $osservazione)
      ->add('alunno', EntityType::class, ['label' => 'label.alunno',
        'class' => Alunno::class,
        'choice_label' => fn($obj) => $obj->getCognome().' '.$obj->getNome().' ('.$obj->getDataNascita()->format('d/m/Y').')',
        'query_builder' => fn(EntityRepository $er) => $er->createQueryBuilder('a')
          ->where('a.id IN (:lista)'.
            ($religione ? " and a.religione='".$religione."'" : ''))
          ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
          ->setParameter('lista', $listaAlunni),
        'expanded' => true,
        'multiple' => false,
        'label_attr' => ['class' => 'gs-pt-1 checkbox-split-vertical'],
        'required' => true])
      ->add('testo', MessageType::class, ['label' => 'label.testo',
        'trim' => true,
        'required' => true])
      ->add('submit', SubmitType::class, ['label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start']])
      ->add('cancel', ButtonType::class, ['label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end',
        'onclick' => "location.href='".$this->generateUrl('lezioni_osservazioni')."'"]])
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      if (!$id) {
        // nuovo
        $this->em->persist($osservazione);
      }
      // ok: memorizza dati
      $this->em->flush();
      // log azione
      if (!$id) {
        // nuovo
        $dblogger->logAzione('REGISTRO', 'Crea osservazione', [
          'Id' => $osservazione->getId()]);
      } else {
        // modifica
        $dblogger->logAzione('REGISTRO', 'Modifica osservazione', [
          'Id' => $osservazione->getId(),
          'Testo' => $osservazione_old['testo'],
          'Alunno' => $osservazione_old['alunno']->getId(),
          'Cattedra' => $osservazione_old['cattedra']->getId()]);
      }
      // redirezione
      return $this->redirectToRoute('lezioni_osservazioni');
    }
    // mostra la pagina di risposta
    return $this->render('lezioni/osservazione_edit.html.twig', [
      'pagina_titolo' => 'page.lezioni_osservazioni',
      'form' => $form->createView(),
      'form_title' => ($id > 0 ? 'title.modifica_osservazione' : 'title.nuova_osservazione'),
      'label' => $label,
      'alunno' => $cattedra->getAlunno()]);
  }

  /**
   * Cancella un'osservazione su un alunno
   *
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $id Identificativo dell'osservazione
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/lezioni/osservazioni/delete/{id}', name: 'lezioni_osservazioni_delete', requirements: ['id' => '\d+'], methods: ['GET'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function osservazioneDelete(RegistroUtil $reg, LogHandler $dblogger, int $id): Response {
    // controlla osservazione
    $osservazione = $this->em->getRepository(OsservazioneAlunno::class)->find($id);
    if (!$osservazione) {
      // non esiste, niente da fare
      return $this->redirectToRoute('lezioni_osservazioni');
    }
    // controlla permessi
    if (!$reg->azioneOsservazione('delete', $osservazione->getData(), $this->getUser(),
                                  $osservazione->getCattedra()->getClasse(), $osservazione)) {
      // errore: azione non permessa
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // cancella osservazione
    $osservazione_id = $osservazione->getId();
    $this->em->remove($osservazione);
    // ok: memorizza dati
    $this->em->flush();
    // log azione
    $dblogger->logAzione('REGISTRO', 'Cancella osservazione', [
      'Osservazione' => $osservazione_id,
      'Cattedra' => $osservazione->getCattedra()->getId(),
      'Alunno' => $osservazione->getAlunno()->getId(),
      'Data' => $osservazione->getData()->format('Y-m-d'),
      'Testo' => $osservazione->getTesto()]);
    // redirezione
    return $this->redirectToRoute('lezioni_osservazioni');
  }

  /**
   * Gestione delle osservazioni personali
   *
   * @param Request $request Pagina richiesta
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param int $cattedra Identificativo della cattedra
   * @param int $classe Identificativo della classe (supplenza)
   * @param string $data Data del giorno da visualizzare (AAAA-MM-GG)
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/lezioni/osservazioni/personali/{cattedra}/{classe}/{data}', name: 'lezioni_osservazioni_personali', requirements: ['cattedra' => '\d+', 'classe' => '\d+', 'data' => '\d\d\d\d-\d\d-\d\d'], defaults: ['cattedra' => 0, 'classe' => 0, 'data' => '0000-00-00'], methods: ['GET'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function osservazioniPersonali(Request $request, RegistroUtil $reg,
                                        int $cattedra, int $classe, string $data): Response {
    // inizializza variabili
    $lista_festivi = null;
    $errore = null;
    $settimana = ['Domenica', 'Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato'];
    $mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    $info = null;
    $dati = null;
    // parametri cattedra/classe
    if ($cattedra == 0 && $classe == 0) {
      // recupera parametri da sessione
      $cattedra = $this->reqstack->getSession()->get('/APP/DOCENTE/cattedra_lezione');
      $classe = $this->reqstack->getSession()->get('/APP/DOCENTE/classe_lezione');
    } else {
      // memorizza su sessione
      $this->reqstack->getSession()->set('/APP/DOCENTE/cattedra_lezione', $cattedra);
      $this->reqstack->getSession()->set('/APP/DOCENTE/classe_lezione', $classe);
    }
    // parametro data
    if ($data == '0000-00-00') {
      // data non specificata
      if ($this->reqstack->getSession()->get('/APP/DOCENTE/data_lezione')) {
        // recupera data da sessione
        $data_obj = DateTime::createFromFormat('Y-m-d', $this->reqstack->getSession()->get('/APP/DOCENTE/data_lezione'));
      } else {
        // imposta data odierna
        $data_obj = new DateTime();
      }
    } else {
      // imposta data indicata e la memorizza in sessione
      $data_obj = DateTime::createFromFormat('Y-m-d', $data);
      $this->reqstack->getSession()->set('/APP/DOCENTE/data_lezione', $data);
    }
    // data in formato stringa
    $formatter = new IntlDateFormatter('it_IT', IntlDateFormatter::SHORT, IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $info['data_label'] =  $formatter->format($data_obj);
    // controllo cattedra/supplenza
    if ($cattedra > 0) {
      // lezione in propria cattedra: controlla esistenza
      $cattedra = $this->em->getRepository(Cattedra::class)->findOneBy(['id' => $cattedra,
        'docente' => $this->getUser(), 'attiva' => 1]);
      if (!$cattedra) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      // informazioni necessarie
      $classe = $cattedra->getClasse();
      $info['materia'] = $cattedra->getMateria()->getNomeBreve();
      $info['alunno'] = $cattedra->getAlunno();
    } elseif ($classe > 0) {
      // supplenza
      $classe = $this->em->getRepository(Classe::class)->find($classe);
      if (!$classe) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      // informazioni necessarie
      $cattedra = null;
    }
    if ($cattedra) {
      // data prec/succ
      $data_succ = (clone $data_obj);
      $data_succ = $this->em->getRepository(Festivita::class)->giornoSuccessivo($data_succ);
      $data_prec = (clone $data_obj);
      $data_prec = $this->em->getRepository(Festivita::class)->giornoPrecedente($data_prec);
      // recupera festivi per calendario
      $lista_festivi = $reg->listaFestivi($classe->getSede());
      // controllo data
      $errore = $reg->controlloData($data_obj, $classe->getSede());
      if (!$errore) {
        // non festivo: recupera dati
        $dati = $reg->osservazioniPersonali($data_obj, $this->getUser(), $cattedra);
      }
    }
    // salva pagina visitata
    $route = ['name' => $request->get('_route'), 'param' => $request->get('_route_params')];
    $this->reqstack->getSession()->set('/APP/DOCENTE/menu_lezione', $route);
    // visualizza pagina
    return $this->render('lezioni/osservazioni_personali.html.twig', [
      'pagina_titolo' => 'page.lezioni_osservazioni_personali',
      'cattedra' => $cattedra,
      'classe' => $classe,
      'data' => $data_obj->format('Y-m-d'),
      'data_succ' => $data_succ,
      'data_prec' => $data_prec,
      'settimana' => $settimana,
      'mesi' => $mesi,
      'errore' => $errore,
      'lista_festivi' => $lista_festivi,
      'info' => $info,
      'dati' => $dati]);
  }

  /**
   * Aggiunge o modifica un'osservazione personale
   *
   * @param Request $request Pagina richiesta
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $cattedra Identificativo della cattedra
   * @param string $data Data del giorno
   * @param int $id Identificativo dell'osservazione (se nullo aggiunge)
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/lezioni/osservazioni/personali/edit/{cattedra}/{data}/{id}', name: 'lezioni_osservazioni_personali_edit', requirements: ['cattedra' => '\d+', 'data' => '\d\d\d\d-\d\d-\d\d', 'id' => '\d+'], defaults: ['id' => 0], methods: ['GET', 'POST'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function osservazionePersonaleEdit(Request $request, RegistroUtil $reg,
                                            LogHandler $dblogger, int $cattedra, string $data,
                                            int $id): Response {
    // inizializza
    $label = [];
    // controlla cattedra
    $cattedra = $this->em->getRepository(Cattedra::class)->findOneBy(['id' => $cattedra,
      'docente' => $this->getUser(), 'attiva' => 1]);
    if (!$cattedra) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla data
    $data_obj = DateTime::createFromFormat('Y-m-d', $data);
    $errore = $reg->controlloData($data_obj, $cattedra->getClasse()->getSede());
    if ($errore) {
      // errore: festivo
      throw $this->createNotFoundException('exception.invalid_params');
    }
    if ($id > 0) {
      // azione edit, controlla ossservazione
      $osservazione = $this->em->getRepository(OsservazioneClasse::class)->findOneBy(['id' => $id,
        'data' => $data_obj, 'cattedra' => $cattedra]);
      if (!$osservazione) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $osservazione_old['testo'] = $osservazione->getTesto();
    } else {
      // azione add
      $osservazione = (new OsservazioneClasse())
        ->setData($data_obj)
        ->setCattedra($cattedra);
    }
    // controlla permessi
    if (!$reg->azioneOsservazione(($id > 0 ? 'edit' : 'add'), $data_obj, $this->getUser(),
                                   $cattedra->getClasse(), ($id > 0 ? $osservazione : null))) {
      // errore: azione non permessa
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // dati in formato stringa
    $formatter = new IntlDateFormatter('it_IT', IntlDateFormatter::SHORT, IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $label['data'] =  $formatter->format($data_obj);
    $label['docente'] = $this->getUser()->getNome().' '.$this->getUser()->getCognome();
    $label['classe'] = ''.$cattedra->getClasse();
    // form di inserimento
    $form = $this->container->get('form.factory')->createNamedBuilder('osservazione_personale_edit', FormType::class, $osservazione)
      ->add('testo', MessageType::class, ['label' => 'label.testo',
        'trim' => true,
        'required' => true])
      ->add('submit', SubmitType::class, ['label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start']])
      ->add('cancel', ButtonType::class, ['label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end',
          'onclick' => "location.href='".$this->generateUrl('lezioni_osservazioni_personali')."'"]])
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      if (!$id) {
        // nuovo
        $this->em->persist($osservazione);
      }
      // ok: memorizza dati
      $this->em->flush();
      // log azione
      if (!$id) {
        // nuovo
        $dblogger->logAzione('REGISTRO', 'Crea osservazione personale', [
          'Id' => $osservazione->getId()]);
      } else {
        // modifica
        $dblogger->logAzione('REGISTRO', 'Modifica osservazione personale', [
          'Id' => $osservazione->getId(),
          'Testo' => $osservazione_old['testo']]);
      }
      // redirezione
      return $this->redirectToRoute('lezioni_osservazioni_personali');
    }
    // mostra la pagina di risposta
    return $this->render('lezioni/osservazione_edit.html.twig', [
      'pagina_titolo' => 'page.lezioni_osservazioni_personali',
      'form' => $form->createView(),
      'form_title' => ($id > 0 ? 'title.modifica_osservazione_personale' : 'title.nuova_osservazione_personale'),
      'label' => $label,
      'alunno' => null]);
  }

  /**
   * Cancella un'osservazione personale
   *
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $id Identificativo dell'osservazione
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/lezioni/osservazioni/personali/delete/{id}', name: 'lezioni_osservazioni_personali_delete', requirements: ['id' => '\d+'], methods: ['GET'])]
  #[IsGranted('ROLE_DOCENTE')]
  public function osservazionePersonaleDelete(RegistroUtil $reg,
                                              LogHandler $dblogger, int $id): Response {
    // controlla osservazione
    $osservazione = $this->em->getRepository(OsservazioneClasse::class)->find($id);
    if (!$osservazione) {
      // non esiste, niente da fare
      return $this->redirectToRoute('lezioni_osservazioni_personali');
    }
    // controlla permessi
    if (!$reg->azioneOsservazione('delete', $osservazione->getData(), $this->getUser(),
                                  $osservazione->getCattedra()->getClasse(), $osservazione)) {
      // errore: azione non permessa
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // cancella osservazione
    $osservazione_id = $osservazione->getId();
    $this->em->remove($osservazione);
    // ok: memorizza dati
    $this->em->flush();
    // log azione
    $dblogger->logAzione('REGISTRO', 'Cancella osservazione personale', [
      'Osservazione' => $osservazione_id,
      'Cattedra' => $osservazione->getCattedra()->getId(),
      'Data' => $osservazione->getData()->format('Y-m-d'),
      'Testo' => $osservazione->getTesto()]);
    // redirezione
    return $this->redirectToRoute('lezioni_osservazioni_personali');
  }

}
