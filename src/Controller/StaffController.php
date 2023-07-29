<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Controller;

use App\Entity\Alunno;
use App\Entity\Assenza;
use App\Entity\Avviso;
use App\Entity\AvvisoClasse;
use App\Entity\AvvisoUtente;
use App\Entity\Entrata;
use App\Entity\Provisioning;
use App\Entity\Uscita;
use App\Form\AvvisoType;
use App\Form\EntrataType;
use App\Form\MessageType;
use App\Form\UscitaType;
use App\Message\AvvisoMessage;
use App\MessageHandler\NotificaMessageHandler;
use App\Util\BachecaUtil;
use App\Util\LogHandler;
use App\Util\PdfManager;
use App\Util\RegistroUtil;
use App\Util\StaffUtil;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityRepository;
use Psr\Log\LoggerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;


/**
 * StaffController - funzioni per lo staff
 *
 * @author Antonello Dessì
 */
class StaffController extends BaseController {

  /**
   * Gestione degli avvisi generici da parte dello staff
   *
   * @param Request $request Pagina richiesta
   * @param BachecaUtil $bac Funzioni di utilità per la gestione della bacheca
   * @param int $pagina Numero di pagina per l'elenco da visualizzare
   *
   * @return Response Pagina di risposta
   *
   * @Route("/staff/avvisi/{pagina}", name="staff_avvisi",
   *    requirements={"pagina": "\d+"},
   *    defaults={"pagina": "0"},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_STAFF")
   */
  public function avvisiAction(Request $request, BachecaUtil $bac, int $pagina): Response {
    // inizializza variabili
    $dati = null;
    // recupera criteri dalla sessione
    $search = array();
    $search['docente'] = $this->reqstack->getSession()->get('/APP/ROUTE/staff_avvisi/docente');
    $search['destinatari'] = $this->reqstack->getSession()->get('/APP/ROUTE/staff_avvisi/destinatari', '');
    $search['classe'] = $this->reqstack->getSession()->get('/APP/ROUTE/staff_avvisi/classe');
    $docente = ($search['docente'] > 0 ? $this->em->getRepository('App\Entity\Docente')->find($search['docente']) : null);
    $classe = ($search['classe'] > 0 ? $this->em->getRepository('App\Entity\Classe')->find($search['classe']) : null);
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $this->reqstack->getSession()->get('/APP/ROUTE/staff_avvisi/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_avvisi/pagina', $pagina);
    }
    // form di ricerca
    $limite = 20;
    $opzioniClassi = $this->em->getRepository('App\Entity\Classe')->opzioni();
    $form = $this->container->get('form.factory')->createNamedBuilder('staff_avvisi', FormType::class)
      ->add('docente', EntityType::class, array('label' => 'label.autore',
        'data' => $docente,
        'class' => 'App\Entity\Staff',
        'choice_label' => function ($obj) {
            return $obj->getCognome().' '.$obj->getNome();
          },
        'placeholder' => 'label.tutti_staff',
        'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('d')
              ->where('d.abilitato=:abilitato')
              ->orderBy('d.cognome,d.nome', 'ASC')
              ->setParameters(['abilitato' => 1]);
          },
        'label_attr' => ['class' => 'sr-only'],
        'choice_attr' => function($val, $key, $index) {
            return ['class' => 'gs-no-placeholder'];
          },
        'attr' => ['class' => 'gs-placeholder'],
        'required' => false))
      ->add('destinatari', ChoiceType::class, array('label' => 'label.destinatari',
        'data' => $search['destinatari'] ? $search['destinatari'] : '',
        'choices' => ['label.coordinatori' => 'C', 'label.docenti' => 'D',
          'label.genitori' => 'G', 'label.alunni' => 'A', 'label.rappresentanti_R' => 'R',
          'label.rappresentanti_I' => 'I', 'label.rappresentanti_L' => 'L',
          'label.rappresentanti_S' => 'S', 'label.rappresentanti_P' => 'P',
          'label.dsga' => 'E', 'label.ata' => 'T'],
        'placeholder' => 'label.tutti_destinatari',
        'label_attr' => ['class' => 'sr-only'],
        'choice_attr' => function($val, $key, $index) {
            return ['class' => 'gs-no-placeholder'];
          },
        'attr' => ['class' => 'gs-placeholder'],
        'required' => false))
      ->add('classe', ChoiceType::class, array('label' => 'label.classe',
        'data' => $classe,
        'choices' => $opzioniClassi,
        'placeholder' => 'label.qualsiasi_classe',
        'choice_value' => 'id',
        'label_attr' => ['class' => 'sr-only'],
        'choice_attr' => function($val, $key, $index) {
            return ['class' => 'gs-no-placeholder'];
          },
        'attr' => ['class' => 'gs-placeholder'],
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.search'))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $search['docente'] = (is_object($form->get('docente')->getData()) ? $form->get('docente')->getData()->getId() : 0);
      $search['destinatari'] = ($form->get('destinatari')->getData() ? $form->get('destinatari')->getData() : '');
      $search['classe'] = (is_object($form->get('classe')->getData()) ? $form->get('classe')->getData()->getId() : 0);
      $pagina = 1;
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_avvisi/docente', $search['docente']);
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_avvisi/destinatari', $search['destinatari']);
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_avvisi/classe', $search['classe']);
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_avvisi/pagina', $pagina);
    }
    // recupera dati
    $dati = $bac->listaAvvisi($search, $pagina, $limite, $this->getUser(), 'C');
    // mostra la pagina di risposta
    return $this->render('ruolo_staff/avvisi.html.twig', array(
      'pagina_titolo' => 'page.staff_avvisi',
      'form' => $form->createView(),
      'form_help' => null,
      'form_success' => null,
      'page' => $pagina,
      'maxPages' => ceil($dati['lista']->count() / $limite),
      'dati' => $dati,
    ));
  }

  /**
   * Aggiunge o modifica un avviso
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param MessageBusInterface $msg Gestione delle notifiche
   * @param BachecaUtil $bac Funzioni di utilità per la gestione della bacheca
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $id Identificativo dell'avviso
   *
   * @return Response Pagina di risposta
   *
   * @Route("/staff/avvisi/edit/{id}", name="staff_avvisi_edit",
   *    requirements={"id": "\d+"},
   *    defaults={"id": "0"},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_STAFF")
   */
  public function avvisiEditAction(Request $request, TranslatorInterface $trans, MessageBusInterface $msg,
                                   BachecaUtil $bac, RegistroUtil $reg, LogHandler $dblogger, 
                                   int $id): Response {
    // inizializza
    $dati = array();
    $var_sessione = '/APP/FILE/staff_avvisi_edit/files';
    $dir = $this->getParameter('dir_avvisi').'/';
    $fs = new Filesystem();
    // controlla azione
    if ($id > 0) {
      // azione edit
      $avviso = $this->em->getRepository('App\Entity\Avviso')->findOneBy(['id' => $id, 'tipo' => 'C']);
      if (!$avviso) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $avviso_old = clone $avviso;
      $avviso_sedi_old = $avviso->getSedi()->toArray();
    } else {
      // azione add
      $avviso = (new Avviso())
        ->setTipo('C');
      if ($this->getUser()->getSede()) {
        $avviso->addSedi($this->getUser()->getSede());
      }
      $this->em->persist($avviso);
    }
    // imposta autore dell'avviso
    $avviso->setDocente($this->getUser());
    // legge allegati
    $allegati = array();
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
      foreach ($avviso->getAllegati() as $k=>$a) {
        $f = new File($dir.$a);
        $allegati[$k]['type'] = 'existent';
        $allegati[$k]['temp'] = $avviso->getId().'-'.$k.'.ID';
        $allegati[$k]['name'] = $a;
        $allegati[$k]['size'] = $f->getSize();
      }
      // modifica dati sessione
      $this->reqstack->getSession()->remove($var_sessione);
      $this->reqstack->getSession()->set($var_sessione, $allegati);
      // elimina file temporanei
      $finder = new Finder();
      $finder->in($this->getParameter('dir_tmp'))->date('< 1 day ago');
      foreach ($finder as $f) {
        $fs->remove($f);
      }
    }
    // form di inserimento
    if ($this->getUser()->getSede()) {
      $opzioniSedi[$this->getUser()->getSede()->getNomeBreve()] = $this->getUser()->getSede();
    } else {
      $opzioniSedi = $this->em->getRepository('App\Entity\Sede')->opzioni();
    }
    $opzioniClassi = $this->em->getRepository('App\Entity\Classe')->opzioni(
      $this->getUser()->getSede() ? $this->getUser()->getSede()->getId() : null, true, false);
    $opzioniMaterie = $this->em->getRepository('App\Entity\Materia')->opzioni(true, false);
    $form = $this->createForm(AvvisoType::class, $avviso, ['form_mode' => 'generico',
      'return_url' => $this->generateUrl('staff_avvisi'),
      'values' => [(count($avviso->getAnnotazioni()) > 0), $opzioniSedi, $opzioniClassi,
      $opzioniMaterie, $opzioniClassi]]);
    $form->handleRequest($request);
    // visualizzazione filtri
    $dati['lista'] = '';
    if ($form->get('filtroTipo')->getData() == 'C') {
      $dati['lista'] = $this->em->getRepository('App\Entity\Classe')->listaClassi($form->get('filtro')->getData());
    } elseif ($form->get('filtroTipo')->getData() == 'M') {
      $dati['lista'] = $this->em->getRepository('App\Entity\Materia')->listaMaterie($form->get('filtro')->getData());
    } elseif ($form->get('filtroTipo')->getData() == 'U') {
      $dati['lista'] = $this->em->getRepository('App\Entity\Alunno')->listaAlunni($form->get('filtro')->getData(), 'gs-filtro-');
    }
    if ($form->isSubmitted()) {
      // lista sedi
      $sedi = array();
      foreach ($avviso->getSedi() as $s) {
        if (!$this->getUser()->getSede() || $this->getUser()->getSede() == $s) {
          // sede corretta
          $sedi[] = $s->getId();
        } else {
          // elimina sede
          $avviso->removeSedi($s);
        }
      }
      // controllo errori
      if (count($sedi) == 0) {
        // sedi non definite
        $form->addError(new FormError($trans->trans('exception.avviso_sede_nulla')));
      }
      if (!$avviso->getDestinatariAta() && !$avviso->getDestinatari()) {
        // destinatari non definiti
        $form->addError(new FormError($trans->trans('exception.avviso_destinatari_nulli')));
      }
      if ($form->get('creaAnnotazione')->getData() && $avviso->getDestinatariAta() && !$avviso->getDestinatari()) {
        // errore: annotazione con destinatari ATA
        $form->addError(new FormError($trans->trans('exception.annotazione_solo_ata')));
      }
      if ($form->get('creaAnnotazione')->getData() && count($allegati) > 0) {
        // errore: annotazione con allegati
        $form->addError(new FormError($trans->trans('exception.annotazione_con_file')));
      }
      // controlla filtro
      $lista = array();
      $errore = false;
      if ($avviso->getFiltroTipo() == 'C') {
        // controlla classi
        $lista = $this->em->getRepository('App\Entity\Classe')
          ->controllaClassi($sedi, $form->get('filtro')->getData(), $errore);
        if ($errore) {
          // classe non valida
          $form->addError(new FormError($trans->trans('exception.filtro_classi_invalido', ['dest' => ''])));
        }
      } elseif ($avviso->getFiltroTipo() == 'M') {
        // controlla materie
        $lista = $this->em->getRepository('App\Entity\Materia')->controllaMaterie($form->get('filtro')->getData(), $errore);
        if ($errore) {
          // materia non valida
          $form->addError(new FormError($trans->trans('exception.filtro_materie_invalido')));
        }
      } elseif ($avviso->getFiltroTipo() == 'U') {
        // controlla utenti
        $lista = $this->em->getRepository('App\Entity\Alunno')
          ->controllaAlunni($sedi, $form->get('filtro')->getData(), $errore);
        if ($errore) {
          // utente non valido
          $form->addError(new FormError($trans->trans('exception.filtro_utenti_invalido', ['dest' => ''])));
        }
      }
      $avviso->setFiltro($lista);
      // controllo permessi
      if (!$bac->azioneAvviso(($id > 0 ? 'edit' : 'add'), $avviso->getData(), $this->getUser(), ($id > 0 ? $avviso : null))) {
        // errore: avviso non permesso
        $form->addError(new FormError($trans->trans('exception.avviso_non_permesso')));
      }
      if ($form->get('creaAnnotazione')->getData() &&
          !$reg->azioneAnnotazione('add', $avviso->getData(), $this->getUser(), null, null)) {
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
        // allegati
        foreach ($this->reqstack->getSession()->get($var_sessione, []) as $f) {
          if ($f['type'] == 'uploaded') {
            // aggiunge allegato
            $fs->rename($this->getParameter('dir_tmp').'/'.$f['temp'], $this->getParameter('dir_avvisi').'/'.$f['temp']);
            $avviso->addAllegato(new File($this->getParameter('dir_avvisi').'/'.$f['temp']));
          } elseif ($f['type'] == 'removed') {
            // rimuove allegato
            $avviso->removeAllegato(new File($this->getParameter('dir_avvisi').'/'.$f['name']));
            $fs->remove($this->getParameter('dir_avvisi').'/'.$f['name']);
          }
        }
        // gestione destinatari
        if ($id) {
          // cancella destinatari precedenti e dati lettura
          $this->em->getRepository('App\Entity\AvvisoUtente')->createQueryBuilder('au')
            ->delete()
            ->where('au.avviso=:avviso')
            ->setParameters(['avviso' => $avviso])
            ->getQuery()
            ->execute();
          $this->em->getRepository('App\Entity\AvvisoClasse')->createQueryBuilder('ac')
            ->delete()
            ->where('ac.avviso=:avviso')
            ->setParameters(['avviso' => $avviso])
            ->getQuery()
            ->execute();
        }
        $dest = $bac->destinatariAvviso($avviso);
        // imposta utenti
        foreach ($dest['utenti'] as $u) {
          $obj = (new AvvisoUtente())
            ->setAvviso($avviso)
            ->setUtente($this->em->getReference('App\Entity\Utente', $u));
          $this->em->persist($obj);
        }
        // imposta classi
        foreach ($dest['classi'] as $c) {
          $obj = (new AvvisoClasse())
            ->setAvviso($avviso)
            ->setClasse($this->em->getReference('App\Entity\Classe', $c));
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
        if ($form->get('creaAnnotazione')->getData()) {
          // crea nuove annotazioni
          $bac->creaAnnotazione($avviso, $dest['sedi']);
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
          $dblogger->logAzione('AVVISI', 'Crea avviso generico', array(
            'Avviso' => $avviso->getId(),
            'Annotazioni' => implode(', ', array_map(function ($a) {
                return $a->getId();
              }, $avviso->getAnnotazioni()->toArray())),
            ));
        } else {
          // modifica
          $dblogger->logAzione('AVVISI', 'Modifica avviso generico', array(
            'Id' => $avviso->getId(),
            'Data' => $avviso_old->getData()->format('d/m/Y'),
            'Oggetto' => $avviso_old->getOggetto(),
            'Testo' => $avviso_old->getTesto(),
            'Allegati' => $avviso_old->getAllegati(),
            'Sedi' => implode(', ', array_map(function ($s) { return $s->getId(); }, $avviso_sedi_old)),
            'Destinatari ATA' => $avviso_old->getDestinatariAta(),
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
        return $this->redirectToRoute('staff_avvisi');
      }
    }
    // mostra la pagina di risposta
    return $this->render('ruolo_staff/avvisi_edit.html.twig', array(
      'pagina_titolo' => 'page.staff_avvisi',
      'form' => $form->createView(),
      'form_title' => ($id > 0 ? 'title.modifica_avviso' : 'title.nuovo_avviso'),
      'allegati' => $allegati,
      'dati' => $dati,
    ));
  }

  /**
   * Mostra i dettagli di un avviso
   *
   * @param BachecaUtil $bac Funzioni di utilità per la gestione della bacheca
   * @param int $id ID dell'avviso
   *
   * @return Response Pagina di risposta
   *
   * @Route("/staff/avvisi/dettagli/{id}", name="staff_avvisi_dettagli",
   *    requirements={"id": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_STAFF")
   */
  public function avvisiDettagliAction(BachecaUtil $bac, int $id): Response {
    // inizializza
    $dati = null;
    // controllo avviso
    $avviso = $this->em->getRepository('App\Entity\Avviso')->find($id);
    if (!$avviso) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // legge dati
    $dati = $bac->dettagliAvviso($avviso);
    // visualizza pagina
    return $this->render('ruolo_staff/scheda_avviso.html.twig', array(
      'dati' => $dati,
    ));
  }

  /**
   * Cancella avviso
   *
   * @param Request $request Pagina richiesta
   * @param LogHandler $dblogger Gestore dei log su database
   * @param BachecaUtil $bac Funzioni di utilità per la gestione della bacheca
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param string $tipo Tipo dell'avviso
   * @param int $id Identificativo dell'avviso
   *
   * @return Response Pagina di risposta
   *
   * @Route("/staff/avvisi/delete/{tipo}/{id}", name="staff_avvisi_delete",
   *    requirements={"tipo": "U|E|V|A|I|C", "id": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_STAFF")
   */
  public function avvisiDeleteAction(Request $request, LogHandler $dblogger, BachecaUtil $bac,
                                     RegistroUtil $reg, string $tipo, int $id): Response {
    $dir = $this->getParameter('dir_avvisi').'/';
    $fs = new Filesystem();
    // controllo avviso
    $avviso = $this->em->getRepository('App\Entity\Avviso')->findOneBy(['id' => $id, 'tipo' => $tipo]);
    if (!$avviso) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo permessi
    if (!$bac->azioneAvviso('delete', $avviso->getData(), $this->getUser(), $avviso)) {
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
    $this->em->getRepository('App\Entity\AvvisoClasse')->createQueryBuilder('ac')
      ->delete()
      ->where('ac.avviso=:avviso')
      ->setParameters(['avviso' => $avviso])
      ->getQuery()
      ->execute();
    // cancella avviso
    $avviso_id = $avviso->getId();
    $avviso_sedi = $avviso->getSedi()->toArray();
    $this->em->remove($avviso);
    // ok: memorizza dati
    $this->em->flush();
    // cancella allegati
    foreach ($avviso->getAllegati() as $a) {
      $f = new File($dir.$a);
      $fs->remove($f);
    }
    // rimuove notifica
    NotificaMessageHandler::delete($this->em, (new AvvisoMessage($avviso_id))->getTag());
    // log azione
    $dblogger->logAzione('AVVISI', 'Cancella avviso', array(
      'Id' => $avviso_id,
      'Tipo' => $avviso->getTipo(),
      'Data' => $avviso->getData()->format('d/m/Y'),
      'Ora' => ($avviso->getOra() ? $avviso->getOra()->format('H:i') : null),
      'Ora fine' => ($avviso->getOraFine() ? $avviso->getOraFine()->format('H:i') : null),
      'Oggetto' => $avviso->getOggetto(),
      'Testo' => $avviso->getTesto(),
      'Allegati' => $avviso->getAllegati(),
      'Sedi' => implode(', ', array_map(function ($s) { return $s->getId(); }, $avviso_sedi)),
      'Destinatari ATA' => $avviso->getDestinatariAta(),
      'Destinatari' => $avviso->getDestinatari(),
      'Filtro Tipo' => $avviso->getFiltroTipo(),
      'Filtro' => $avviso->getFiltro(),
      'Docente' => $avviso->getDocente()->getId(),
      'Annotazioni' => implode(', ', $log_annotazioni),
      ));
    // redirezione
    if ($tipo == 'U' || $tipo == 'E') {
      // orario
      return $this->redirectToRoute('staff_avvisi_orario',  ['tipo' => $tipo]);
    } elseif ($tipo == 'A') {
      // attività
      return $this->redirectToRoute('staff_avvisi_attivita');
    } elseif ($tipo == 'I') {
      // attività
      return $this->redirectToRoute('staff_avvisi_individuali');
    } else {
      // avviso generico
      return $this->redirectToRoute('staff_avvisi');
    }
  }

  /**
   * Gestione degli avvisi sugli orari di ingresso o uscita
   *
   * @param Request $request Pagina richiesta
   * @param BachecaUtil $bac Funzioni di utilità per la gestione della bacheca
   * @param string $tipo Tipo di avviso [E=orario entrata, U=orario uscita]
   * @param int $pagina Numero di pagina per l'elenco da visualizzare
   *
   * @return Response Pagina di risposta
   *
   * @Route("/staff/avvisi/orario/{tipo}/{pagina}", name="staff_avvisi_orario",
   *    requirements={"tipo": "E|U", "pagina": "\d+"},
   *    defaults={"pagina": "0"},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_STAFF")
   */
  public function avvisiOrarioAction(Request $request, BachecaUtil $bac, string $tipo, 
                                     int $pagina): Response {
    // inizializza variabili
    $dati = null;
    // recupera criteri dalla sessione
    $search = array();
    $search['docente'] = $this->reqstack->getSession()->get('/APP/ROUTE/staff_avvisi_orario_'.$tipo.'/docente');
    $search['classe'] = $this->reqstack->getSession()->get('/APP/ROUTE/staff_avvisi_orario_'.$tipo.'/classe');
    $docente = ($search['docente'] > 0 ? $this->em->getRepository('App\Entity\Docente')->find($search['docente']) : null);
    $classe = ($search['classe'] > 0 ? $this->em->getRepository('App\Entity\Classe')->find($search['classe']) : null);
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $this->reqstack->getSession()->get('/APP/ROUTE/staff_avvisi_orario_'.$tipo.'/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_avvisi_orario_'.$tipo.'/pagina', $pagina);
    }
    // form di ricerca
    $limite = 20;
    $opzioniClassi = $this->em->getRepository('App\Entity\Classe')->opzioni();
    $form = $this->container->get('form.factory')->createNamedBuilder('staff_avvisi_orario', FormType::class)
      ->add('docente', EntityType::class, array('label' => 'label.autore',
        'data' => $docente,
        'class' => 'App\Entity\Staff',
        'choice_label' => function ($obj) {
            return $obj->getCognome().' '.$obj->getNome();
          },
        'placeholder' => 'label.tutti_staff',
        'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('d')
              ->where('d.abilitato=:abilitato')
              ->orderBy('d.cognome,d.nome', 'ASC')
              ->setParameters(['abilitato' => 1]);
          },
        'label_attr' => ['class' => 'sr-only'],
        'choice_attr' => function($val, $key, $index) {
            return ['class' => 'gs-no-placeholder'];
          },
        'attr' => ['class' => 'gs-placeholder'],
        'required' => false))
      ->add('classe', ChoiceType::class, array('label' => 'label.classe',
        'data' => $classe,
        'choices' => $opzioniClassi,
        'placeholder' => 'label.qualsiasi_classe',
        'choice_translation_domain' => false,
        'choice_value' => 'id',
        'label_attr' => ['class' => 'sr-only'],
        'choice_attr' => function($val, $key, $index) {
            return ['class' => 'gs-no-placeholder'];
          },
        'attr' => ['class' => 'gs-placeholder'],
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.search'))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $search['docente'] = (is_object($form->get('docente')->getData()) ? $form->get('docente')->getData()->getId() : 0);
      $search['classe'] = (is_object($form->get('classe')->getData()) ? $form->get('classe')->getData()->getId() : 0);
      $pagina = 1;
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_avvisi_orario_'.$tipo.'/docente', $search['docente']);
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_avvisi_orario_'.$tipo.'/classe', $search['classe']);
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_avvisi_orario_'.$tipo.'/pagina', $pagina);
    }
    // recupera dati
    $dati = $bac->listaAvvisi($search, $pagina, $limite, $this->getUser(), $tipo);
    // mostra la pagina di risposta
    return $this->render('ruolo_staff/avvisi_orario.html.twig', array(
      'pagina_titolo' => ($tipo == 'E' ? 'page.staff_avvisi_entrate' : 'page.staff_avvisi_uscite'),
      'form' => $form->createView(),
      'form_help' => null,
      'form_success' => null,
      'page' => $pagina,
      'maxPages' => ceil($dati['lista']->count() / $limite),
      'dati' => $dati,
      'tipo' => $tipo,
    ));
  }

  /**
   * Aggiunge o modifica un avviso sulla modifica di orario di ingresso o uscita
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param MessageBusInterface $msg Gestione delle notifiche
   * @param BachecaUtil $bac Funzioni di utilità per la gestione della bacheca
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param LogHandler $dblogger Gestore dei log su database
   * @param string $tipo Tipo di modifica dell'orario [E=entrata, U=uscita]
   * @param int $id Identificativo dell'avviso
   *
   * @return Response Pagina di risposta
   *
   * @Route("/staff/avvisi/orario/edit/{tipo}/{id}", name="staff_avvisi_orario_edit",
   *    requirements={"tipo": "E|U", "id": "\d+"},
   *    defaults={"id": "0"},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_STAFF")
   */
  public function avvisiOrarioEditAction(Request $request, TranslatorInterface $trans,
                                         MessageBusInterface $msg, BachecaUtil $bac,
                                         RegistroUtil $reg, LogHandler $dblogger, string $tipo, 
                                         int $id): Response {
    // controlla azione
    if ($id > 0) {
      // azione edit
      $avviso = $this->em->getRepository('App\Entity\Avviso')->findOneBy(['id' => $id, 'tipo' => $tipo]);
      if (!$avviso) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $avviso_old = clone $avviso;
      $avviso_sedi_old = $avviso->getSedi()->toArray();
    } else {
      // legge ora predefinita
      if ($tipo == 'E') {
        // inizio seconda ora di lunedì su orario di oggi 
        $ora_predefinita = $this->em->getRepository('App\Entity\ScansioneOraria')->createQueryBuilder('so')
          ->select('so.inizio')
          ->join('so.orario', 'o')
          ->join('o.sede', 's')
          ->where(':data BETWEEN o.inizio AND o.fine AND so.giorno=:giorno AND so.ora=:ora')
          ->orderBy('s.ordinamento', 'ASC')
          ->setParameters(['data' => (new \DateTime())->format('Y-m-d'), 'giorno' => 1, 'ora' => 2])
          ->setMaxResults(1)
          ->getQuery()
          ->getSingleScalarResult();
      } else {
        // inizio ultima ora di lunedì su orario di oggi 
        $ora_predefinita = $this->em->getRepository('App\Entity\ScansioneOraria')->createQueryBuilder('so')
          ->select('so.inizio')
          ->join('so.orario', 'o')
          ->join('o.sede', 's')
          ->where(':data BETWEEN o.inizio AND o.fine AND so.giorno=:giorno ')
          ->orderBy('s.ordinamento', 'ASC')
          ->addOrderBy('so.ora', 'DESC')
          ->setParameters(['data' => (new \DateTime())->format('Y-m-d'), 'giorno' => 1])
          ->setMaxResults(1)
          ->getQuery()
          ->getSingleScalarResult();
      }
      // azione add
      $avviso = (new Avviso())
        ->setTipo($tipo)
        ->setDestinatariAta(['D','A'])
        ->setDestinatari(['G','A','D'])
        ->setFiltroTipo('C')
        ->setData(new \DateTime('tomorrow'))
        ->setOra(\DateTime::createFromFormat('H:i:00', $ora_predefinita))
        ->setOggetto($trans->trans($tipo == 'E' ? 'message.avviso_entrata_oggetto' :
          'message.avviso_uscita_oggetto'))
        ->setTesto($trans->trans($tipo == 'E' ? 'message.avviso_entrata_testo' :
          'message.avviso_uscita_testo'));
      if ($this->getUser()->getSede()) {
        $avviso->addSedi($this->getUser()->getSede());
      }
      $this->em->persist($avviso);
    }
    // imposta autore dell'avviso
    $avviso->setDocente($this->getUser());
    // form di inserimento
    if ($this->getUser()->getSede()) {
      $opzioniSedi[$this->getUser()->getSede()->getNomeBreve()] = $this->getUser()->getSede();
    } else {
      $opzioniSedi = $this->em->getRepository('App\Entity\Sede')->opzioni();
    }    
    $opzioniClassi = $this->em->getRepository('App\Entity\Classe')->opzioni(
      $this->getUser()->getSede() ? $this->getUser()->getSede()->getId() : null, true, false);
    $form = $this->createForm(AvvisoType::class, $avviso, ['form_mode' => 'orario',
      'return_url' => $this->generateUrl('staff_avvisi_orario', ['tipo' => $tipo]),
      'values' => [$tipo, $opzioniSedi, $opzioniClassi]]);
    $form->handleRequest($request);
    if ($form->isSubmitted()) {
      // lista sedi
      $sedi = array();
      foreach ($avviso->getSedi() as $s) {
        if (!$this->getUser()->getSede() || $this->getUser()->getSede() == $s) {
          // sede corretta
          $sedi[] = $s->getId();
        } else {
          // elimina sede
          $avviso->removeSedi($s);
        }
      }
      // controllo errori
      if (count($sedi) == 0) {
        // sedi non definite
        $form->addError(new FormError($trans->trans('exception.avviso_sede_nulla')));
      }
      if (empty($form->get('classi')->getData())) {
        // errore: filtro vuoto
        $form->addError(new FormError($trans->trans('exception.filtro_classe_nullo')));
      }
      // controllo data
      $errore = $reg->controlloData($form->get('data')->getData(), null);
      if ($errore) {
        // errore: festivo
        $form->addError(new FormError($trans->trans('exception.data_festiva')));
      }
      // controllo testo
      if (strpos($form->get('testo')->getData(), '{DATA}') === false) {
        // errore: testo senza campo data
        $form->addError(new FormError($trans->trans('exception.campo_data_mancante')));
      }
      if (strpos($form->get('testo')->getData(), '{ORA}') === false) {
        // errore: testo senza campo ora
        $form->addError(new FormError($trans->trans('exception.campo_ora_mancante')));
      }
      // controlla filtro
      $errore = false;
      $lista_classi = array_map(function ($o) { return $o->getId(); },
        $form->get('classi')->getData());
      $lista = $this->em->getRepository('App\Entity\Classe')->controllaClassi($sedi, $lista_classi, $errore);
      if ($errore) {
        // classe non valida
        $form->addError(new FormError($trans->trans('exception.filtro_classi_invalido', ['dest' => ''])));
      }
      $avviso->setFiltro($lista);
      // controllo permessi
      if (!$bac->azioneAvviso(($id > 0 ? 'edit' : 'add'), $avviso->getData(), $this->getUser(), ($id > 0 ? $avviso : null))) {
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
        // gestione destinatari
        if ($id) {
          // cancella destinatari precedenti e dati lettura
          $this->em->getRepository('App\Entity\AvvisoUtente')->createQueryBuilder('au')
            ->delete()
            ->where('au.avviso=:avviso')
            ->setParameters(['avviso' => $avviso])
            ->getQuery()
            ->execute();
          $this->em->getRepository('App\Entity\AvvisoClasse')->createQueryBuilder('ac')
            ->delete()
            ->where('ac.avviso=:avviso')
            ->setParameters(['avviso' => $avviso])
            ->getQuery()
            ->execute();
        }
        $dest = $bac->destinatariAvviso($avviso);
        // imposta utenti
        foreach ($dest['utenti'] as $u) {
          $obj = (new AvvisoUtente())
            ->setAvviso($avviso)
            ->setUtente($this->em->getReference('App\Entity\Utente', $u));
          $this->em->persist($obj);
        }
        // imposta classi
        foreach ($dest['classi'] as $c) {
          $obj = (new AvvisoClasse())
            ->setAvviso($avviso)
            ->setClasse($this->em->getReference('App\Entity\Classe', $c));
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
        // crea nuove annotazioni
        $bac->creaAnnotazione($avviso, $dest['sedi']);
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
          $dblogger->logAzione('AVVISI', 'Crea avviso '.($tipo == 'E' ? 'entrata' : 'uscita'), array(
            'Avviso' => $avviso->getId(),
            'Annotazioni' => implode(', ', array_map(function ($a) {
                return $a->getId();
              }, $avviso->getAnnotazioni()->toArray())),
            ));
        } else {
          // modifica
          $dblogger->logAzione('AVVISI', 'Modifica avviso '.($tipo == 'E' ? 'entrata' : 'uscita'), array(
            'Id' => $avviso->getId(),
            'Data' => $avviso_old->getData()->format('d/m/Y'),
            'Ora' => $avviso_old->getOra()->format('H:i'),
            'Testo' => $avviso_old->getTesto(),
            'Sedi' => implode(', ', array_map(function ($s) { return $s->getId(); }, $avviso_sedi_old)),
            'Filtro' => $avviso_old->getFiltro(),
            'Docente' => $avviso_old->getDocente()->getId(),
            'Annotazioni cancellate' => implode(', ', $log_annotazioni['delete']),
            'Annotazioni create' => implode(', ', array_map(function ($a) {
                return $a->getId();
              }, $avviso->getAnnotazioni()->toArray())),
            ));
        }
        // redirezione
        return $this->redirectToRoute('staff_avvisi_orario', ['tipo' => $tipo]);
      }
    }
    // mostra la pagina di risposta
    if ($id > 0) {
      $title = ($tipo == 'E' ? 'title.modifica_avviso_entrate' : 'title.modifica_avviso_uscite');
    } else {
      $title = ($tipo == 'E' ? 'title.nuovo_avviso_entrate' : 'title.nuovo_avviso_uscite');
    }
    return $this->render('ruolo_staff/avvisi_orario_edit.html.twig', array(
      'pagina_titolo' => ($tipo == 'E' ? 'page.staff_avvisi_entrate' : 'page.staff_avvisi_uscite'),
      'form' => $form->createView(),
      'form_title' => $title,
    ));
  }

  /**
   * Gestione degli avvisi sulle attività
   *
   * @param Request $request Pagina richiesta
   * @param BachecaUtil $bac Funzioni di utilità per la gestione della bacheca
   * @param int $pagina Numero di pagina per l'elenco da visualizzare
   *
   * @return Response Pagina di risposta
   *
   * @Route("/staff/avvisi/attivita/{pagina}", name="staff_avvisi_attivita",
   *    requirements={"pagina": "\d+"},
   *    defaults={"pagina": "0"},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_STAFF")
   */
  public function avvisiAttivitaAction(Request $request, BachecaUtil $bac, int $pagina): Response {
    // inizializza variabili
    $dati = null;
    // recupera criteri dalla sessione
    $search = array();
    $search['docente'] = $this->reqstack->getSession()->get('/APP/ROUTE/staff_avvisi_attivita/docente');
    $search['classe'] = $this->reqstack->getSession()->get('/APP/ROUTE/staff_avvisi_attivita/classe');
    $docente = ($search['docente'] > 0 ? $this->em->getRepository('App\Entity\Docente')->find($search['docente']) : null);
    $classe = ($search['classe'] > 0 ? $this->em->getRepository('App\Entity\Classe')->find($search['classe']) : null);
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $this->reqstack->getSession()->get('/APP/ROUTE/staff_avvisi_attivita/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_avvisi_attivita/pagina', $pagina);
    }
    // form di ricerca
    $limite = 20;
    $opzioniClassi = $this->em->getRepository('App\Entity\Classe')->opzioni();
    $form = $this->container->get('form.factory')->createNamedBuilder('staff_avvisi_attivita', FormType::class)
      ->add('docente', EntityType::class, array('label' => 'label.autore',
        'data' => $docente,
        'class' => 'App\Entity\Staff',
        'choice_label' => function ($obj) {
            return $obj->getCognome().' '.$obj->getNome();
          },
        'placeholder' => 'label.tutti_staff',
        'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('d')
              ->where('d.abilitato=:abilitato')
              ->orderBy('d.cognome,d.nome', 'ASC')
              ->setParameters(['abilitato' => 1]);
          },
        'label_attr' => ['class' => 'sr-only'],
        'choice_attr' => function($val, $key, $index) {
            return ['class' => 'gs-no-placeholder'];
          },
        'attr' => ['class' => 'gs-placeholder'],
        'required' => false))
      ->add('classe', ChoiceType::class, array('label' => 'label.classe',
        'data' => $classe,
        'choices' => $opzioniClassi,
        'placeholder' => 'label.qualsiasi_classe',
        'choice_translation_domain' => false,
        'choice_value' => 'id',
        'label_attr' => ['class' => 'sr-only'],
        'choice_attr' => function($val, $key, $index) {
            return ['class' => 'gs-no-placeholder'];
          },
        'attr' => ['class' => 'gs-placeholder'],
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.search'))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $search['docente'] = (is_object($form->get('docente')->getData()) ? $form->get('docente')->getData()->getId() : 0);
      $search['classe'] = (is_object($form->get('classe')->getData()) ? $form->get('classe')->getData()->getId() : 0);
      $pagina = 1;
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_avvisi_attivita/docente', $search['docente']);
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_avvisi_attivita/classe', $search['classe']);
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_avvisi_attivita/pagina', $pagina);
    }
    // recupera dati
    $dati = $bac->listaAvvisi($search, $pagina, $limite, $this->getUser(), 'A');
    // mostra la pagina di risposta
    return $this->render('ruolo_staff/avvisi_attivita.html.twig', array(
      'pagina_titolo' => 'page.staff_avvisi_attivita',
      'form' => $form->createView(),
      'form_help' => null,
      'form_success' => null,
      'page' => $pagina,
      'maxPages' => ceil($dati['lista']->count() / $limite),
      'dati' => $dati,
    ));
  }

  /**
   * Aggiunge o modifica un avviso per le attività
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param MessageBusInterface $msg Gestione delle notifiche
   * @param BachecaUtil $bac Funzioni di utilità per la gestione della bacheca
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param LogHandler $dblogger Gestore dei log su database
   *
   * @return Response Pagina di risposta
   *
   * @Route("/staff/avvisi/attivita/edit/{id}", name="staff_avvisi_attivita_edit",
   *    requirements={"id": "\d+"},
   *    defaults={"id": "0"},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_STAFF")
   */
  public function avvisiAttivitaEditAction(Request $request, TranslatorInterface $trans,
                                           MessageBusInterface $msg, BachecaUtil $bac,
                                           RegistroUtil $reg, LogHandler $dblogger, int $id): Response {
    // controlla azione
    if ($id > 0) {
      // azione edit
      $avviso = $this->em->getRepository('App\Entity\Avviso')->findOneBy(['id' => $id, 'tipo' => 'A']);
      if (!$avviso) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $avviso_old = clone $avviso;
      $avviso_sedi_old = $avviso->getSedi()->toArray();
    } else {
      // azione add
      $avviso = (new Avviso())
        ->setTipo('A')
        ->setDestinatariAta(['D', 'A'])
        ->setDestinatari(['D', 'G', 'A'])
        ->setFiltroTipo('C')
        ->setData(new \DateTime('tomorrow'))
        ->setOra(\DateTime::createFromFormat('H:i', '08:20'))
        ->setOraFine(\DateTime::createFromFormat('H:i', '13:50'))
        ->setOggetto($trans->trans('message.avviso_attivita_oggetto'))
        ->setTesto($trans->trans('message.avviso_attivita_testo'));
      if ($this->getUser()->getSede()) {
        $avviso->addSedi($this->getUser()->getSede());
      }
      $this->em->persist($avviso);
    }
    // imposta autore dell'avviso
    $avviso->setDocente($this->getUser());
    // form di inserimento
    if ($this->getUser()->getSede()) {
      $opzioniSedi[$this->getUser()->getSede()->getNomeBreve()] = $this->getUser()->getSede();
    } else {
      $opzioniSedi = $this->em->getRepository('App\Entity\Sede')->opzioni();
    }    
    $opzioniClassi = $this->em->getRepository('App\Entity\Classe')->opzioni(
      $this->getUser()->getSede() ? $this->getUser()->getSede()->getId() : null, true, false);
    $form = $this->createForm(AvvisoType::class, $avviso, ['form_mode' => 'attivita',
      'return_url' => $this->generateUrl('staff_avvisi_attivita'),
      'values' => [$opzioniSedi, $opzioniClassi]]);
    $form->handleRequest($request);
    if ($form->isSubmitted()) {
      // lista sedi
      $sedi = array();
      foreach ($avviso->getSedi() as $s) {
        if (!$this->getUser()->getSede() || $this->getUser()->getSede() == $s) {
          // sede corretta
          $sedi[] = $s->getId();
        } else {
          // elimina sede
          $avviso->removeSedi($s);
        }
      }
      // controllo errori
      if (count($sedi) == 0) {
        // sedi non definite
        $form->addError(new FormError($trans->trans('exception.avviso_sede_nulla')));
      }
      if (empty($form->get('classi')->getData())) {
        // errore: filtro vuoto
        $form->addError(new FormError($trans->trans('exception.filtro_classe_nullo')));
      }
      // controllo data
      $errore = $reg->controlloData($form->get('data')->getData(), null);
      if ($errore) {
        // errore: festivo
        $form->addError(new FormError($trans->trans('exception.data_festiva')));
      }
      // controllo testo
      if (strpos($form->get('testo')->getData(), '{DATA}') === false) {
        // errore: testo senza campo data
        $form->addError(new FormError($trans->trans('exception.campo_data_mancante')));
      }
      if (strpos($form->get('testo')->getData(), '{INIZIO}') === false) {
        // errore: testo senza campo ora
        $form->addError(new FormError($trans->trans('exception.campo_ora_inizio_mancante')));
      }
      if (strpos($form->get('testo')->getData(), '{FINE}') === false) {
        // errore: testo senza campo ora
        $form->addError(new FormError($trans->trans('exception.campo_ora_fine_mancante')));
      }
      // controlla filtro
      $errore = false;
      $lista_classi = array_map(function ($o) { return $o->getId(); },
        $form->get('classi')->getData());
      $lista = $this->em->getRepository('App\Entity\Classe')->controllaClassi($sedi, $lista_classi, $errore);
      if ($errore) {
        // classe non valida
        $form->addError(new FormError($trans->trans('exception.filtro_classi_invalido', ['dest' => ''])));
      }
      $avviso->setFiltro($lista);
      // controllo permessi
      if (!$bac->azioneAvviso(($id > 0 ? 'edit' : 'add'), $avviso->getData(), $this->getUser(), ($id > 0 ? $avviso : null))) {
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
        // gestione destinatari
        if ($id) {
          // cancella destinatari precedenti e dati lettura
          $this->em->getRepository('App\Entity\AvvisoUtente')->createQueryBuilder('au')
            ->delete()
            ->where('au.avviso=:avviso')
            ->setParameters(['avviso' => $avviso])
            ->getQuery()
            ->execute();
          $this->em->getRepository('App\Entity\AvvisoClasse')->createQueryBuilder('ac')
            ->delete()
            ->where('ac.avviso=:avviso')
            ->setParameters(['avviso' => $avviso])
            ->getQuery()
            ->execute();
        }
        $dest = $bac->destinatariAvviso($avviso);
        // imposta utenti
        foreach ($dest['utenti'] as $u) {
          $obj = (new AvvisoUtente())
            ->setAvviso($avviso)
            ->setUtente($this->em->getReference('App\Entity\Utente', $u));
          $this->em->persist($obj);
        }
        // imposta classi
        foreach ($dest['classi'] as $c) {
          $obj = (new AvvisoClasse())
            ->setAvviso($avviso)
            ->setClasse($this->em->getReference('App\Entity\Classe', $c));
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
        // crea nuove annotazioni
        $bac->creaAnnotazione($avviso, $dest['sedi']);
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
          $dblogger->logAzione('AVVISI', 'Crea avviso attività', array(
            'Avviso' => $avviso->getId(),
            'Annotazioni' => implode(', ', array_map(function ($a) {
                return $a->getId();
              }, $avviso->getAnnotazioni()->toArray())),
            ));
        } else {
          // modifica
          $dblogger->logAzione('AVVISI', 'Modifica avviso attività', array(
            'Id' => $avviso->getId(),
            'Data' => $avviso_old->getData()->format('d/m/Y'),
            'Ora inizio' => $avviso_old->getOra()->format('H:i'),
            'Ora fine' => $avviso_old->getOraFine()->format('H:i'),
            'Testo' => $avviso_old->getTesto(),
            'Sedi' => implode(', ', array_map(function ($s) { return $s->getId(); }, $avviso_sedi_old)),
            'Filtro' => $avviso_old->getFiltro(),
            'Docente' => $avviso_old->getDocente()->getId(),
            'Annotazioni cancellate' => implode(', ', $log_annotazioni['delete']),
            'Annotazioni create' => implode(', ', array_map(function ($a) {
                return $a->getId();
              }, $avviso->getAnnotazioni()->toArray())),
            ));
        }
        // redirezione
        return $this->redirectToRoute('staff_avvisi_attivita');
      }
    }
    // mostra la pagina di risposta
    return $this->render('ruolo_staff/avvisi_attivita_edit.html.twig', array(
      'pagina_titolo' => 'page.staff_avvisi_attivita',
      'form' => $form->createView(),
      'form_title' => ($id > 0 ? 'title.modifica_avviso_attivita' : 'title.nuovo_avviso_attivita'),
    ));
  }

  /**
   * Gestione degli avvisi individuali per i genitori
   *
   * @param Request $request Pagina richiesta
   * @param BachecaUtil $bac Funzioni di utilità per la gestione della bacheca
   * @param int $pagina Numero di pagina per l'elenco da visualizzare
   *
   * @return Response Pagina di risposta
   *
   * @Route("/staff/avvisi/individuali/{pagina}", name="staff_avvisi_individuali",
   *    requirements={"pagina": "\d+"},
   *    defaults={"pagina": "0"},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_STAFF")
   */
  public function avvisiIndividualiAction(Request $request, BachecaUtil $bac, int $pagina): Response {
    // inizializza variabili
    $dati = null;
    // recupera criteri dalla sessione
    $search = array();
    $search['docente'] = $this->reqstack->getSession()->get('/APP/ROUTE/staff_avvisi_individuali/docente');
    $docente = ($search['docente'] > 0 ? $this->em->getRepository('App\Entity\Docente')->find($search['docente']) : null);
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $this->reqstack->getSession()->get('/APP/ROUTE/staff_avvisi_individuali/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_avvisi_individuali/pagina', $pagina);
    }
    // form di ricerca
    $limite = 20;
    $form = $this->container->get('form.factory')->createNamedBuilder('staff_avvisi_individuali', FormType::class)
      ->add('docente', EntityType::class, array('label' => 'label.autore',
        'data' => $docente,
        'class' => 'App\Entity\Staff',
        'choice_label' => function ($obj) {
            return $obj->getCognome().' '.$obj->getNome();
          },
        'placeholder' => 'label.tutti_staff',
        'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('d')
              ->where('d.abilitato=:abilitato')
              ->orderBy('d.cognome,d.nome', 'ASC')
              ->setParameters(['abilitato' => 1]);
          },
        'label_attr' => ['class' => 'sr-only'],
        'choice_attr' => function($val, $key, $index) {
            return ['class' => 'gs-no-placeholder'];
          },
        'attr' => ['class' => 'gs-placeholder'],
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.search'))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $search['docente'] = (is_object($form->get('docente')->getData()) ? $form->get('docente')->getData()->getId() : 0);
      $pagina = 1;
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_avvisi_individuali/docente', $search['docente']);
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_avvisi_individuali/pagina', $pagina);
    }
    // recupera dati
    $dati = $bac->listaAvvisi($search, $pagina, $limite, $this->getUser(), 'I');
    // mostra la pagina di risposta
    return $this->render('ruolo_staff/avvisi_individuali.html.twig', array(
      'pagina_titolo' => 'page.staff_avvisi_individuali',
      'form' => $form->createView(),
      'form_help' => null,
      'form_success' => null,
      'page' => $pagina,
      'maxPages' => ceil($dati['lista']->count() / $limite),
      'dati' => $dati,
    ));
  }

  /**
   * Aggiunge o modifica un avviso individuale
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param MessageBusInterface $msg Gestione delle notifiche
   * @param BachecaUtil $bac Funzioni di utilità per la gestione della bacheca
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param LogHandler $dblogger Gestore dei log su database
   *
   * @return Response Pagina di risposta
   *
   * @Route("/staff/avvisi/individuali/edit/{id}", name="staff_avvisi_individuali_edit",
   *    requirements={"id": "\d+"},
   *    defaults={"id": "0"},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_STAFF")
   */
  public function avvisiIndividualiEditAction(Request $request, TranslatorInterface $trans,
                                              MessageBusInterface $msg, BachecaUtil $bac,
                                              RegistroUtil $reg, LogHandler $dblogger, int $id): Response {
    // controlla azione
    if ($id > 0) {
      // azione edit
      $avviso = $this->em->getRepository('App\Entity\Avviso')->findOneBy(['id' => $id, 'tipo' => 'I']);
      if (!$avviso) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $avviso_old = clone $avviso;
      $avviso_sedi_old = $avviso->getSedi()->toArray();
    } else {
      // azione add
      $docente = ($this->getUser()->getSesso() == 'M' ? ' prof. ' : 'la prof.ssa ').
        $this->getUser()->getNome().' '.$this->getUser()->getCognome();
      $avviso = (new Avviso())
        ->setTipo('I')
        ->setDestinatari(['G'])
        ->setFiltroTipo('U')
        ->setOggetto($trans->trans('message.avviso_individuale_oggetto', ['docente' => $docente]))
        ->setData(new \DateTime('today'));
      if ($this->getUser()->getSede()) {
        $avviso->addSedi($this->getUser()->getSede());
      }
      $this->em->persist($avviso);
    }
    // imposta autore dell'avviso
    $avviso->setDocente($this->getUser());
    // form di inserimento
    if ($this->getUser()->getSede()) {
      $opzioniSedi[$this->getUser()->getSede()->getNomeBreve()] = $this->getUser()->getSede();
    } else {
      $opzioniSedi = $this->em->getRepository('App\Entity\Sede')->opzioni();
    }    
    $opzioniClassi = $this->em->getRepository('App\Entity\Classe')->opzioni(
      $this->getUser()->getSede() ? $this->getUser()->getSede()->getId() : null, true, false);
    $form = $this->createForm(AvvisoType::class, $avviso, ['form_mode' => 'individuale',
      'return_url' => $this->generateUrl('staff_avvisi_individuali'),
      'values' => [$opzioniSedi, $opzioniClassi]]);
    $form->handleRequest($request);
    $dati['lista'] = $this->em->getRepository('App\Entity\Alunno')->listaAlunni($form->get('filtro')->getData(), 'gs-filtro-');
    if ($form->isSubmitted()) {
      // lista sedi
      $sedi = array();
      foreach ($avviso->getSedi() as $s) {
        if (!$this->getUser()->getSede() || $this->getUser()->getSede() == $s) {
          // sede corretta
          $sedi[] = $s->getId();
        } else {
          // elimina sede
          $avviso->removeSedi($s);
        }
      }
      if (count($sedi) == 0) {
        // sedi non definite
        $form->addError(new FormError($trans->trans('exception.avviso_sede_nulla')));
      }
      if (empty(implode(',', $form->get('filtro')->getData()))) {
        // errore: filtro vuoto
        $form->addError(new FormError($trans->trans('exception.filtro_utente_nullo')));
      }
      // controlla filtro
      $errore = false;
      $lista = $this->em->getRepository('App\Entity\Alunno')
        ->controllaAlunni($sedi, $form->get('filtro')->getData(), $errore);
      if ($errore) {
        // utente non valido
        $form->addError(new FormError($trans->trans('exception.filtro_utenti_invalido', ['dest' => ''])));
      }
      $avviso->setFiltro($lista);
      // controllo permessi
      if (!$bac->azioneAvviso(($id > 0 ? 'edit' : 'add'), $avviso->getData(), $this->getUser(), ($id > 0 ? $avviso : null))) {
        // errore: avviso non permesso
        $form->addError(new FormError($trans->trans('exception.avviso_non_permesso')));
      }
      // modifica dati
      if ($form->isValid()) {
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
          $dblogger->logAzione('AVVISI', 'Crea avviso individuale', array(
            'Avviso' => $avviso->getId(),
            ));
        } else {
          // modifica
          $dblogger->logAzione('AVVISI', 'Modifica avviso individuale', array(
            'Id' => $avviso->getId(),
            'Testo' => $avviso_old->getTesto(),
            'Sedi' => implode(', ', array_map(function ($s) { return $s->getId(); }, $avviso_sedi_old)),
            'Filtro' => $avviso_old->getFiltro(),
            'Docente' => $avviso_old->getDocente()->getId(),
            ));
        }
        // redirezione
        return $this->redirectToRoute('staff_avvisi_individuali');
      }
    }
    // mostra la pagina di risposta
    return $this->render('ruolo_staff/avvisi_individuali_edit.html.twig', array(
      'pagina_titolo' => 'page.staff_avvisi_individuali',
      'form' => $form->createView(),
      'form_title' => ($id > 0 ? 'title.modifica_avviso_individuale' : 'title.nuovo_avviso_individuale'),
      'dati' => $dati,
    ));
  }

  /**
   * Restituisce gli alunni della classe indicata
   *
   * @param int $id Identificativo della classe
   *
   * @return JsonResponse Informazioni di risposta
   *
   * @Route("/staff/classe/{id}", name="staff_classe",
   *    requirements={"id": "\d+"},
   *    defaults={"id": 0},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_STAFF")
   */
  public function classeAjaxAction(int $id): JsonResponse {
    $alunni = $this->em->getRepository('App\Entity\Alunno')->createQueryBuilder('a')
      ->select("a.id,CONCAT(a.cognome,' ',a.nome) AS nome")
      ->where('a.classe=:classe AND a.abilitato=:abilitato')
      ->setParameters(['classe' => $id, 'abilitato' => 1])
      ->orderBy('a.cognome,a.nome', 'ASC')
      ->getQuery()
      ->getArrayResult();
    // restituisce dati
    return new JsonResponse($alunni);
  }

  /**
   * Gestione dei ritardi e delle uscite anticipate
   *
   * @param Request $request Pagina richiesta
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param StaffUtil $staff Funzioni di utilità per lo staff
   * @param string $data Data per la gestione dei ritardi e delle uscita (AAAA-MM-GG)
   * @param int $pagina Numero di pagina per la lista dei alunni
   *
   * @return Response Pagina di risposta
   *
   * @Route("/staff/studenti/autorizza/{data}/{pagina}", name="staff_studenti_autorizza",
   *    requirements={"data": "\d\d\d\d-\d\d-\d\d", "pagina": "\d+"},
   *    defaults={"data": "0000-00-00", "pagina": "0"},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_STAFF")
   */
  public function studentiAutorizzaAction(Request $request, RegistroUtil $reg, StaffUtil $staff,
                                          string $data, int $pagina): Response {
    // inizializza variabili
    $lista_festivi = null;
    $errore = null;
    $dati = null;
    $info = null;
    $max_pagine = 1;
    $settimana = ['Domenica', 'Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato'];
    $mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    $data_succ = null;
    $data_prec = null;
    // parametro data
    if ($data == '0000-00-00') {
      // data non specificata
      if ($this->reqstack->getSession()->get('/APP/ROUTE/staff_studenti_autorizza/data')) {
        // recupera data da sessione
        $data_obj = \DateTime::createFromFormat('Y-m-d', $this->reqstack->getSession()->get('/APP/ROUTE/staff_studenti_autorizza/data'));
      } else {
        // imposta data odierna
        $data_obj = new \DateTime();
      }
    } else {
      // imposta data indicata e la memorizza in sessione
      $data_obj = \DateTime::createFromFormat('Y-m-d', $data);
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_studenti_autorizza/data', $data);
    }
    // data in formato stringa
    $formatter = new \IntlDateFormatter('it_IT', \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $info['data_label'] =  $formatter->format($data_obj);
    // data prec/succ
    $data_succ = (clone $data_obj);
    $data_succ = $this->em->getRepository('App\Entity\Festivita')->giornoSuccessivo($data_succ);
    $data_prec = (clone $data_obj);
    $data_prec = $this->em->getRepository('App\Entity\Festivita')->giornoPrecedente($data_prec);
    // recupera criteri dalla sessione
    $search = array();
    $search['classe'] = $this->reqstack->getSession()->get('/APP/ROUTE/staff_studenti_autorizza/classe');
    $classe = ($search['classe'] > 0 ? $this->em->getRepository('App\Entity\Classe')->find($search['classe']) : null);
    $search['cognome'] = $this->reqstack->getSession()->get('/APP/ROUTE/staff_studenti_autorizza/cognome', '');
    $search['nome'] = $this->reqstack->getSession()->get('/APP/ROUTE/staff_studenti_autorizza/nome', '');
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $this->reqstack->getSession()->get('/APP/ROUTE/staff_studenti_autorizza/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_studenti_autorizza/pagina', $pagina);
    }
    // legge sede
    $sede = $this->getUser()->getSede();
    // form di ricerca
    $limite = 20;
    $opzioniClassi = $this->em->getRepository('App\Entity\Classe')->opzioni(
      $this->getUser()->getSede() ? $this->getUser()->getSede()->getId() : null, false);
    $form = $this->container->get('form.factory')->createNamedBuilder('staff_studenti_autorizza', FormType::class)
      ->setAction($this->generateUrl('staff_studenti_autorizza', ['data' => $data]))
      ->add('classe', ChoiceType::class, array('label' => 'label.classe',
        'data' => $classe,
        'choices' => $opzioniClassi,
        'choice_value' => 'id',
        'placeholder' => 'label.qualsiasi_classe',
        'choice_translation_domain' => false,
        'label_attr' => ['class' => 'sr-only'],
        'required' => false))
      ->add('cognome', TextType::class, array('label' => 'label.cognome',
        'data' => $search['cognome'],
        'attr' => ['placeholder' => 'label.cognome'],
        'label_attr' => ['class' => 'sr-only'],
        'required' => false))
      ->add('nome', TextType::class, array('label' => 'label.nome',
        'data' => $search['nome'],
        'attr' => ['placeholder' => 'label.nome'],
        'label_attr' => ['class' => 'sr-only'],
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.search'))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $search['classe'] = (is_object($form->get('classe')->getData()) ? $form->get('classe')->getData()->getId() : 0);
      $search['cognome'] = trim($form->get('cognome')->getData());
      $search['nome'] = trim($form->get('nome')->getData());
      $pagina = 1;
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_studenti_autorizza/classe', $search['classe']);
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_studenti_autorizza/cognome', $search['cognome']);
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_studenti_autorizza/nome', $search['nome']);
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_studenti_autorizza/pagina', $pagina);
    }
    // recupera periodo
    $info['periodo'] = $reg->periodo($data_obj);
    // recupera festivi per calendario
    $lista_festivi = $reg->listaFestivi(null);
    // controllo data
    $errore = $reg->controlloData($data_obj, null);
    if (!$errore) {
      // non festivo: recupera dati
      $lista = $this->em->getRepository('App\Entity\Alunno')->findClassEnabled($sede, $search, $pagina, $limite);
      $max_pagine = ceil($lista->count() / $limite);
      $dati['genitori'] = $this->em->getRepository('App\Entity\Genitore')->datiGenitoriPaginator($lista);
      $dati['lista'] = $staff->entrateUscite($info['periodo']['inizio'], $info['periodo']['fine'], $lista);
      $dati['azioni'] = $reg->azioneAssenze($data_obj, $this->getUser(), null, null, null);
    }
    // mostra la pagina di risposta
    return $this->render('ruolo_staff/studenti_autorizza.html.twig', array(
      'pagina_titolo' => 'page.staff_autorizza',
      'form' => $form->createView(),
      'form_help' => null,
      'form_success' => null,
      'page' => $pagina,
      'maxPages' => $max_pagine,
      'data' => $data_obj->format('Y-m-d'),
      'data_succ' => $data_succ,
      'data_prec' => $data_prec,
      'settimana' => $settimana,
      'mesi' => $mesi,
      'errore' => $errore,
      'lista_festivi' => $lista_festivi,
      'info' => $info,
      'dati' => $dati,
    ));
  }

  /**
   * Aggiunge, modifica o elimina un ritardo
   *
   * @param Request $request Pagina richiesta
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param LogHandler $dblogger Gestore dei log su database
   * @param string $data Data del giorno (AAAA-MM-GG)
   * @param int $classe Identificativo della classe
   * @param int $alunno Identificativo dell'alunno
   *
   * @return Response Pagina di risposta
   *
   * @Route("/staff/studenti/autorizza/entrata/{data}/{classe}/{alunno}", name="staff_studenti_autorizza_entrata",
   *    requirements={"data": "\d\d\d\d-\d\d-\d\d", "classe": "\d+", "alunno": "\d+"},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_STAFF")
   */
  public function studentiAutorizzaEntrataAction(Request $request, RegistroUtil $reg,
                                                 TranslatorInterface $trans, LogHandler $dblogger,
                                                 string $data, int $classe, int $alunno): Response {
    // inizializza
    $label = array();
    // controlla classe
    $classe = $this->em->getRepository('App\Entity\Classe')->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla alunno
    $alunno = $this->em->getRepository('App\Entity\Alunno')->findOneBy(['id' => $alunno, 'abilitato' => 1, 'classe' => $classe]);
    if (!$alunno) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla data
    $data_obj = \DateTime::createFromFormat('Y-m-d', $data);
    $errore = $reg->controlloData($data_obj, $classe->getSede());
    if ($errore) {
      // errore: festivo
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // legge prima/ultima ora
    $orario = $reg->orarioInData($data_obj, $classe->getSede());
    // controlla entrata
    $entrata = $this->em->getRepository('App\Entity\Entrata')->findOneBy(['alunno' => $alunno, 'data' => $data_obj]);
    if ($entrata) {
      // edit
      $entrata_old = clone $entrata;
      // elimina giustificazione
      $entrata
        ->setDocente($this->getUser())
        ->setRitardoBreve(false)
        ->setGiustificato(null)
        ->setDocenteGiustifica(null);
    } else {
      // nuovo
      $ora = \DateTime::createFromFormat('H:i:s', $orario[0]['fine']);
      $nota = $trans->trans('message.autorizza_ritardo', [
        'sex' => ($alunno->getSesso() == 'M' ? 'o' : 'a'),
        'alunno' => $alunno->getCognome().' '.$alunno->getNome()]);
      $entrata = (new Entrata())
        ->setData($data_obj)
        ->setOra($ora)
        ->setNote($nota)
        ->setAlunno($alunno)
        ->setValido(true)
        ->setDocente($this->getUser());
      $this->em->persist($entrata);
    }
    // controlla permessi
    if (!$reg->azioneAssenze($data_obj, $this->getUser(), $alunno, $classe, null)) {
      // errore: azione non permessa
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // dati in formato stringa
    $formatter = new \IntlDateFormatter('it_IT', \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $label['data'] =  $formatter->format($data_obj);
    $label['docente'] = $this->getUser()->getNome().' '.$this->getUser()->getCognome();
    $label['classe'] = $classe->getAnno()."ª ".$classe->getSezione();
    $label['alunno'] = $alunno->getCognome().' '.$alunno->getNome();
    // form di inserimento
    $form = $this->createForm(EntrataType::class, $entrata, array('form_mode' => 'staff'));
    $form->handleRequest($request);
    if ($form->isSubmitted()) {
      if (!isset($entrata_old) && isset($request->request->get('entrata')['delete'])) {
        // ritardo non esiste, niente da fare
        return $this->redirectToRoute('staff_studenti_autorizza');
      } elseif ($form->get('ora')->getData()->format('H:i:00') <= $orario[0]['inizio'] ||
                $form->get('ora')->getData()->format('H:i:00') > $orario[count($orario) - 1]['fine']) {
        // ora fuori dai limiti
        $form->get('ora')->addError(new FormError($trans->trans('field.time', [], 'validators')));
      } elseif ($form->isValid()) {
        if (isset($entrata_old) && isset($request->request->get('entrata')['delete'])) {
          // cancella ritardo esistente
          $id_entrata = $entrata->getId();
          $this->em->remove($entrata);
        } else {
          // controlla ritardo breve
          $inizio = \DateTime::createFromFormat('Y-m-d H:i:s', '1970-01-01 '.$orario[0]['inizio']);
          $inizio->modify('+' . $this->reqstack->getSession()->get('/CONFIG/SCUOLA/ritardo_breve', 0) . 'minutes');
          if ($form->get('ora')->getData() <= $inizio) {
            // ritardo breve: giustificazione automatica (non imposta docente)
            $entrata
              ->setRitardoBreve(true)
              ->setGiustificato($data_obj)
              ->setDocenteGiustifica(null)
              ->setValido(false);
          }
          // controlla se risulta assente
          $assenza = $this->em->getRepository('App\Entity\Assenza')->findOneBy(['alunno' => $alunno, 'data' => $data_obj]);
          if ($assenza) {
            // cancella assenza
            $id_assenza = $assenza->getId();
            $this->em->remove($assenza);
          }
        }
        // ok: memorizza dati
        $this->em->flush();
        // ricalcola ore assenze
        $reg->ricalcolaOreAlunno($data_obj, $alunno);
        // log azione
        if (isset($entrata_old) && isset($request->request->get('entrata')['delete'])) {
          // log cancella
          $dblogger->logAzione('ASSENZE', 'Cancella entrata', array(
            'Entrata' => $id_entrata,
            'Alunno' => $entrata->getAlunno()->getId(),
            'Data' => $entrata->getData()->format('Y-m-d'),
            'Ora' => $entrata->getOra()->format('H:i'),
            'Note' => $entrata->getNote(),
            'Valido' => $entrata->getValido(),
            'Giustificato' => ($entrata->getGiustificato() ? $entrata->getGiustificato()->format('Y-m-d') : null),
            'Docente' => $entrata->getDocente()->getId(),
            'DocenteGiustifica' => ($entrata->getDocenteGiustifica() ? $entrata->getDocenteGiustifica()->getId() : null)
            ));
        } elseif (isset($entrata_old)) {
          // log modifica
          $dblogger->logAzione('ASSENZE', 'Modifica entrata', array(
            'Entrata' => $entrata->getId(),
            'Ora' => $entrata_old->getOra()->format('H:i'),
            'Note' => $entrata_old->getNote(),
            'Valido' => $entrata_old->getValido(),
            'Giustificato' => ($entrata_old->getGiustificato() ? $entrata_old->getGiustificato()->format('Y-m-d') : null),
            'Docente' => $entrata_old->getDocente()->getId(),
            'DocenteGiustifica' => ($entrata_old->getDocenteGiustifica() ? $entrata_old->getDocenteGiustifica()->getId() : null)
            ));
        } else {
          // log nuovo
          $dblogger->logAzione('ASSENZE', 'Crea entrata', array(
            'Entrata' => $entrata->getId()
            ));
        }
        if (isset($id_assenza)) {
          // log cancella assenza
          $dblogger->logAzione('ASSENZE', 'Cancella assenza', array(
            'Assenza' => $id_assenza,
            'Alunno' => $assenza->getAlunno()->getId(),
            'Data' => $assenza->getData()->format('Y-m-d'),
            'Giustificato' => ($assenza->getGiustificato() ? $assenza->getGiustificato()->format('Y-m-d') : null),
            'Docente' => $assenza->getDocente()->getId(),
            'DocenteGiustifica' => ($assenza->getDocenteGiustifica() ? $assenza->getDocenteGiustifica()->getId() : null)
            ));
        }
        // redirezione
        return $this->redirectToRoute('staff_studenti_autorizza');
      }
    }
    // mostra la pagina di risposta
    return $this->render('ruolo_staff/studenti_autorizza_entrata.html.twig', array(
      'pagina_titolo' => 'page.staff_autorizza',
      'form' => $form->createView(),
      'form_title' => (isset($entrata_old) ? 'title.modifica_entrata' : 'title.nuova_entrata'),
      'label' => $label,
      'btn_delete' => isset($entrata_old),
    ));
  }

  /**
   * Aggiunge, modifica o elimina un'uscita anticipata
   *
   * @param Request $request Pagina richiesta
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param LogHandler $dblogger Gestore dei log su database
   * @param string $data Data del giorno (AAAA-MM-GG)
   * @param int $classe Identificativo della classe
   * @param int $alunno Identificativo dell'alunno
   *
   * @return Response Pagina di risposta
   *
   * @Route("/staff/studenti/autorizza/uscita/{data}/{classe}/{alunno}", name="staff_studenti_autorizza_uscita",
   *    requirements={"data": "\d\d\d\d-\d\d-\d\d", "classe": "\d+", "alunno": "\d+"},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_STAFF")
   */
  public function studentiAutorizzaUscitaAction(Request $request, RegistroUtil $reg,
                                                TranslatorInterface $trans, LogHandler $dblogger,
                                                string $data, int $classe, int $alunno): Response {
    // inizializza
    $label = array();
    // controlla classe
    $classe = $this->em->getRepository('App\Entity\Classe')->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla alunno
    $alunno = $this->em->getRepository('App\Entity\Alunno')->findOneBy(['id' => $alunno, 'abilitato' => 1, 'classe' => $classe]);
    if (!$alunno) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla data
    $data_obj = \DateTime::createFromFormat('Y-m-d', $data);
    $errore = $reg->controlloData($data_obj, $classe->getSede());
    if ($errore) {
      // errore: festivo
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // controlla richiesta
    if ($this->reqstack->getSession()->get('/CONFIG/SCUOLA/gestione_uscite') == 'A') {
      // gestione uscita con autorizzazione
      $richiesta = $this->em->getRepository('App\Entity\Richiesta')
        ->richiestaAlunno('U', $alunno->getId(), $data_obj);
      if ($richiesta && (!in_array($richiesta->getStato(), ['I', 'G'], true) ||
          $richiesta->getDefinizioneRichiesta()->getUnica() ||
          !$richiesta->getDefinizioneRichiesta()->getAbilitata())) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
    } else {
      // gestione uscita con giustificazione
      $richiesta = null;
    }
    // legge prima/ultima ora
    $orario = $reg->orarioInData($data_obj, $classe->getSede());
    // controlla uscita
    $uscita = $this->em->getRepository('App\Entity\Uscita')->findOneBy(['alunno' => $alunno, 'data' => $data_obj]);
    if ($uscita) {
      // edit
      $uscita_old = clone $uscita;
      $uscita
        ->setDocente($this->getUser())
        ->setGiustificato(null)
        ->setDocenteGiustifica(null);
      $chiediGiustificazione = !$uscita_old->getDocenteGiustifica();
    } else {
      // nuovo
      if ($richiesta) {
        $ora = $richiesta->getValori()['ora'];
      } else {
        $ora = new \DateTime();
        if ($data != $ora->format('Y-m-d') || $ora->format('H:i:00') < $orario[0]['inizio'] ||
            $ora->format('H:i:00') > $orario[count($orario) - 1]['fine']) {
          // data non odierna o ora attuale fuori da orario
          $ora = \DateTime::createFromFormat('H:i:s', $orario[count($orario) - 1]['inizio']);
        }
      }
      $msg = $richiesta ? 'message.autorizza_uscita_richiesta' :
        ($alunno->controllaRuoloFunzione('AM') ? 'message.autorizza_uscita_maggiorenne' : 'message.autorizza_uscita');
      $nota = $trans->trans($msg, ['sex' => ($alunno->getSesso() == 'M' ? 'o' : 'a'),
        'alunno' => $alunno->getCognome().' '.$alunno->getNome()]);
      $uscita = (new Uscita())
        ->setData($data_obj)
        ->setOra($ora)
        ->setAlunno($alunno)
        ->setNote($nota)
        ->setValido(true)
        ->setDocente($this->getUser());
      $this->em->persist($uscita);
      $chiediGiustificazione = false;
    }
    // controlla permessi
    if (!$reg->azioneAssenze($data_obj, $this->getUser(), $alunno, $classe, null)) {
      // errore: azione non permessa
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // dati in formato stringa
    $formatter = new \IntlDateFormatter('it_IT', \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $label['data'] =  $formatter->format($data_obj);
    $label['docente'] = $this->getUser()->getNome().' '.$this->getUser()->getCognome();
    $label['classe'] = $classe->getAnno()."ª ".$classe->getSezione();
    $label['alunno'] = $alunno->getCognome().' '.$alunno->getNome();
    $label['richiesta'] = $richiesta;
    // form di inserimento
    $form = $this->createForm(UscitaType::class, $uscita, array(
      'form_mode' => $richiesta ? 'richiesta' : 'staff',
      'values' => [$chiediGiustificazione]));
    $form->handleRequest($request);
    if ($form->isSubmitted()) {
      if (!isset($uscita_old) && isset($request->request->get('uscita')['delete'])) {
        // ritardo non esiste, niente da fare
        return $this->redirectToRoute('staff_studenti_autorizza');
      } elseif ($form->get('ora')->getData()->format('H:i:00') < $orario[0]['inizio'] ||
                $form->get('ora')->getData()->format('H:i:00') >= $orario[count($orario) - 1]['fine']) {
        // ora fuori dai limiti
        $form->get('ora')->addError(new FormError($trans->trans('field.time', [], 'validators')));
      } elseif ($form->isValid()) {
        if (isset($uscita_old) && isset($request->request->get('uscita')['delete'])) {
          // cancella uscita esistente
          $id_uscita = $uscita->getId();
          $this->em->remove($uscita);
        } else {
          // controlla se risulta assente
          $assenza = $this->em->getRepository('App\Entity\Assenza')->findOneBy(['alunno' => $alunno, 'data' => $data_obj]);
          if ($assenza) {
            // cancella assenza
            $id_assenza = $assenza->getId();
            $this->em->remove($assenza);
          }
        }
        if ($richiesta) {
          // gestione richiesta
          $richiesta->setStato(isset($id_uscita) ? 'I' : 'G');
        }
        if ($richiesta || $form->get('giustificazione')->getData() === false) {
          // gestione autorizzazione
          $uscita
            ->setGiustificato(new \DateTime('today'))
            ->setDocenteGiustifica($this->getUser());
        }
        // ok: memorizza dati
        $this->em->flush();
        // ricalcola ore assenze
        $reg->ricalcolaOreAlunno($data_obj, $alunno);
        // log azione
        if (isset($uscita_old) && isset($request->request->get('uscita')['delete'])) {
          // cancella
          $dblogger->logAzione('ASSENZE', 'Cancella uscita', array(
            'Uscita' => $id_uscita,
            'Alunno' => $uscita->getAlunno()->getId(),
            'Data' => $uscita->getData()->format('Y-m-d'),
            'Ora' => $uscita->getOra()->format('H:i'),
            'Note' => $uscita->getNote(),
            'Valido' => $uscita->getValido(),
            'Docente' => $uscita->getDocente()->getId()
            ));
        } elseif (isset($uscita_old)) {
          // modifica
          $dblogger->logAzione('ASSENZE', 'Modifica uscita', array(
            'Uscita' => $uscita->getId(),
            'Ora' => $uscita_old->getOra()->format('H:i'),
            'Note' => $uscita_old->getNote(),
            'Valido' => $uscita_old->getValido(),
            'Docente' => $uscita_old->getDocente()->getId()
            ));
        } else {
          // nuovo
          $dblogger->logAzione('ASSENZE', 'Crea uscita', array(
            'Uscita' => $uscita->getId()
            ));
        }
        if (isset($id_assenza)) {
          // cancella assenza
          $dblogger->logAzione('ASSENZE', 'Cancella assenza', array(
            'Assenza' => $id_assenza,
            'Alunno' => $assenza->getAlunno()->getId(),
            'Data' => $assenza->getData()->format('Y-m-d'),
            'Giustificato' => ($assenza->getGiustificato() ? $assenza->getGiustificato()->format('Y-m-d') : null),
            'Docente' => $assenza->getDocente()->getId(),
            'DocenteGiustifica' => ($assenza->getDocenteGiustifica() ? $assenza->getDocenteGiustifica()->getId() : null)
            ));
        }
        // redirezione
        return $this->redirectToRoute('staff_studenti_autorizza');
      }
    }
    // mostra la pagina di risposta
    return $this->render('ruolo_staff/studenti_autorizza_uscita.html.twig', array(
      'pagina_titolo' => 'page.staff_autorizza',
      'form' => $form->createView(),
      'form_title' => (isset($uscita_old) ? 'title.modifica_uscita' : 'title.nuova_uscita'),
      'label' => $label,
      'btn_delete' => isset($uscita_old),
    ));
  }

  /**
   * Gestisce l'inserimento di deroghe e annotazioni sugli alunni
   *
   * @param Request $request Pagina richiesta
   * @param int $pagina Numero di pagina per la lista dei alunni
   *
   * @return Response Pagina di risposta
   *
   * @Route("/staff/studenti/deroghe/{pagina}", name="staff_studenti_deroghe",
   *    requirements={"pagina": "\d+"},
   *    defaults={"pagina": 0},
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_STAFF")
   */
  public function studentiDerogheAction(Request $request, int $pagina): Response {
    $dati = array();
    // recupera criteri dalla sessione
    $search = array();
    $search['classe'] = $this->reqstack->getSession()->get('/APP/ROUTE/staff_studenti_deroghe/classe');
    $classe = ($search['classe'] > 0 ? $this->em->getRepository('App\Entity\Classe')->find($search['classe']) : null);
    $search['cognome'] = $this->reqstack->getSession()->get('/APP/ROUTE/staff_studenti_deroghe/cognome', '');
    $search['nome'] = $this->reqstack->getSession()->get('/APP/ROUTE/staff_studenti_deroghe/nome', '');
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $this->reqstack->getSession()->get('/APP/ROUTE/staff_studenti_deroghe/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_studenti_deroghe/pagina', $pagina);
    }
    // legge sede
    $sede = $this->getUser()->getSede();
    // form di ricerca
    $limite = 20;
    $opzioniClassi = $this->em->getRepository('App\Entity\Classe')->opzioni(
      $this->getUser()->getSede() ? $this->getUser()->getSede()->getId() : null, false);
    $form = $this->container->get('form.factory')->createNamedBuilder('staff_studenti_deroghe', FormType::class)
      ->setAction($this->generateUrl('staff_studenti_deroghe'))
      ->add('classe', ChoiceType::class, array('label' => 'label.classe',
        'data' => $classe,
        'choices' => $opzioniClassi,
        'choice_value' => 'id',
        'placeholder' => 'label.qualsiasi_classe',
        'choice_translation_domain' => false,
        'label_attr' => ['class' => 'sr-only'],
        'required' => false))
      ->add('cognome', TextType::class, array('label' => 'label.cognome',
        'data' => $search['cognome'],
        'attr' => ['placeholder' => 'label.cognome'],
        'label_attr' => ['class' => 'sr-only'],
        'required' => false))
      ->add('nome', TextType::class, array('label' => 'label.nome',
        'data' => $search['nome'],
        'attr' => ['placeholder' => 'label.nome'],
        'label_attr' => ['class' => 'sr-only'],
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.search'))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $search['classe'] = (is_object($form->get('classe')->getData()) ? $form->get('classe')->getData()->getId() : 0);
      $search['cognome'] = trim($form->get('cognome')->getData());
      $search['nome'] = trim($form->get('nome')->getData());
      $pagina = 1;
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_studenti_deroghe/classe', $search['classe']);
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_studenti_deroghe/cognome', $search['cognome']);
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_studenti_deroghe/nome', $search['nome']);
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_studenti_deroghe/pagina', $pagina);
    }
    // lista alunni
    $lista['lista'] = $this->em->getRepository('App\Entity\Alunno')->findClassEnabled($sede, $search, $pagina, $limite);
    $lista['genitori'] = $this->em->getRepository('App\Entity\Genitore')->datiGenitoriPaginator($lista['lista']);
    // mostra la pagina di risposta
    return $this->render('ruolo_staff/studenti_deroghe.html.twig', array(
      'pagina_titolo' => 'page.staff_deroghe',
      'form' => $form->createView(),
      'form_help' => null,
      'form_success' => null,
      'lista' => $lista,
      'dati' => $dati,
      'page' => $pagina,
      'maxPages' => ceil($lista['lista']->count() / $limite),
    ));
  }

  /**
   * Modifica delle deroghe e annotazioni di un alunno
   *
   * @param Request $request Pagina richiesta
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $alunno ID dell'alunno
   *
   * @return Response Pagina di risposta
   *
   * @Route("/staff/studenti/deroghe/edit/{alunno}", name="staff_studenti_deroghe_edit",
   *    requirements={"alunno": "\d+"},
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_STAFF")
   */
  public function studentiDerogheEditAction(Request $request, LogHandler $dblogger, int $alunno): Response {
    // inizializza
    $label = null;
    // controlla alunno
    $alunno = $this->em->getRepository('App\Entity\Alunno')->findOneBy(['id' => $alunno, 'abilitato' => 1]);
    if (!$alunno) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // edit
    $alunno_old['autorizzaEntrata'] = $alunno->getAutorizzaEntrata();
    $alunno_old['autorizzaUscita'] = $alunno->getAutorizzaUscita();
    $alunno_old['note'] = $alunno->getNote();
    // dati in formato stringa
    $formatter = new \IntlDateFormatter('it_IT', \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $label['data'] =  $formatter->format(new \DateTime());
    $label['docente'] = $this->getUser()->getNome().' '.$this->getUser()->getCognome();
    $label['classe'] = $alunno->getClasse()->getAnno()."ª ".$alunno->getClasse()->getSezione();
    $label['alunno'] = $alunno->getCognome().' '.$alunno->getNome();
    // form di inserimento
    $form = $this->container->get('form.factory')->createNamedBuilder('deroga_edit', FormType::class, $alunno)
      ->add('autorizzaEntrata', MessageType::class, array('label' => 'label.autorizza_entrata',
        'required' => false))
      ->add('autorizzaUscita', MessageType::class, array('label' => 'label.autorizza_uscita',
        'required' => false))
      ->add('note', MessageType::class, array('label' => 'label.note',
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start']))
      ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end',
        'onclick' => "location.href='".$this->generateUrl('staff_studenti_deroghe')."'"]))
      ->getForm();
    $form->handleRequest($request);
      if ($form->isSubmitted() && $form->isValid()) {
        // ok: memorizza dati
        $this->em->flush();
        // log azione
        $dblogger->logAzione('ALUNNO', 'Modifica deroghe', array(
          'Username' => $alunno->getUsername(),
          'Ruolo' => $alunno->getRoles()[0],
          'ID' => $alunno->getId(),
          'Autorizza entrata' => $alunno_old['autorizzaEntrata'],
          'Autorizza uscita' => $alunno_old['autorizzaUscita'],
          'Note' => $alunno_old['note']
          ));
      // redirezione
      return $this->redirectToRoute('staff_studenti_deroghe');
    }
    // mostra la pagina di risposta
    return $this->render('ruolo_staff/studenti_deroghe_edit.html.twig', array(
      'pagina_titolo' => 'title.deroghe',
      'form' => $form->createView(),
      'form_title' => 'title.deroghe',
      'label' => $label,
    ));
  }

  /**
   * Mostra la situazione degli alunni
   *
   * @param Request $request Pagina richiesta
   * @param int $pagina Numero di pagina per la lista dei alunni
   *
   * @return Response Pagina di risposta
   *
   * @Route("/staff/studenti/situazione/{pagina}", name="staff_studenti_situazione",
   *    requirements={"pagina": "\d+"},
   *    defaults={"pagina": 0},
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_STAFF")
   */
  public function studentiSituazioneAction(Request $request, int $pagina): Response {
    $dati = array();
    // recupera criteri dalla sessione
    $search = array();
    $search['classe'] = $this->reqstack->getSession()->get('/APP/ROUTE/staff_studenti_situazione/classe');
    $classe = ($search['classe'] > 0 ? $this->em->getRepository('App\Entity\Classe')->find($search['classe']) : null);
    $search['cognome'] = $this->reqstack->getSession()->get('/APP/ROUTE/staff_studenti_situazione/cognome', '');
    $search['nome'] = $this->reqstack->getSession()->get('/APP/ROUTE/staff_studenti_situazione/nome', '');
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $this->reqstack->getSession()->get('/APP/ROUTE/staff_studenti_situazione/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_studenti_situazione/pagina', $pagina);
    }
    // legge sede
    $sede = $this->getUser()->getSede();
    // form di ricerca
    $limite = 20;
    $opzioniClassi = $this->em->getRepository('App\Entity\Classe')->opzioni(
      $this->getUser()->getSede() ? $this->getUser()->getSede()->getId() : null, false);
    $form = $this->container->get('form.factory')->createNamedBuilder('staff_studenti_situazione', FormType::class)
      ->setAction($this->generateUrl('staff_studenti_situazione'))
      ->add('classe', ChoiceType::class, array('label' => 'label.classe',
        'data' => $classe,
        'choices' => $opzioniClassi,
        'choice_value' => 'id',
        'placeholder' => 'label.qualsiasi_classe',
        'choice_translation_domain' => false,
        'label_attr' => ['class' => 'sr-only'],
        'required' => false))
      ->add('cognome', TextType::class, array('label' => 'label.cognome',
        'data' => $search['cognome'],
        'attr' => ['placeholder' => 'label.cognome'],
        'label_attr' => ['class' => 'sr-only'],
        'required' => false))
      ->add('nome', TextType::class, array('label' => 'label.nome',
        'data' => $search['nome'],
        'attr' => ['placeholder' => 'label.nome'],
        'label_attr' => ['class' => 'sr-only'],
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.search'))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $search['classe'] = (is_object($form->get('classe')->getData()) ? $form->get('classe')->getData()->getId() : 0);
      $search['cognome'] = trim($form->get('cognome')->getData());
      $search['nome'] = trim($form->get('nome')->getData());
      $pagina = 1;
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_studenti_situazione/classe', $search['classe']);
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_studenti_situazione/cognome', $search['cognome']);
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_studenti_situazione/nome', $search['nome']);
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_studenti_situazione/pagina', $pagina);
    }
    // lista alunni
    $lista['lista'] = $this->em->getRepository('App\Entity\Alunno')->findClassEnabled($sede, $search, $pagina, $limite);
    $lista['genitori'] = $this->em->getRepository('App\Entity\Genitore')->datiGenitoriPaginator($lista['lista']);
    // mostra la pagina di risposta
    return $this->render('ruolo_staff/studenti_situazione.html.twig', array(
      'pagina_titolo' => 'page.staff_situazione',
      'form' => $form->createView(),
      'form_help' => null,
      'form_success' => null,
      'lista' => $lista,
      'dati' => $dati,
      'page' => $pagina,
      'maxPages' => ceil($lista['lista']->count() / $limite),
    ));
  }

  /**
   * Mostra le statistiche sulle ore di lezione svolte dai docenti
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param StaffUtil $staff Funzioni di utilità per lo staff
   * @param PdfManager $pdf Gestore dei documenti PDF
   * @param int $pagina Numero di pagina per la lista visualizzata
   *
   * @return Response Pagina di risposta
   *
   * @Route("/staff/docenti/statistiche/{pagina}", name="staff_docenti_statistiche",
   *    requirements={"pagina": "\d+"},
   *    defaults={"pagina": 0},
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_STAFF")
   */
  public function docentiStatisticheAction(Request $request, TranslatorInterface $trans,
                                           StaffUtil $staff, PdfManager $pdf, int $pagina): Response {
    // recupera criteri dalla sessione
    $creaPdf = false;
    $search = array();
    $search['docente'] = $this->reqstack->getSession()->get('/APP/ROUTE/staff_docenti_statistiche/docente', null);
    $search['inizio'] = $this->reqstack->getSession()->get('/APP/ROUTE/staff_docenti_statistiche/inizio', null);
    $search['fine'] = $this->reqstack->getSession()->get('/APP/ROUTE/staff_docenti_statistiche/fine', null);
    $docente = ($search['docente'] > 0 ? $this->em->getRepository('App\Entity\Docente')->find($search['docente']) :
      ($search['docente'] < 0 ? -1 : null));
    $inizio = ($search['inizio'] ? \DateTime::createFromFormat('Y-m-d', $search['inizio']) : new \DateTime());
    $fine = ($search['fine'] ? \DateTime::createFromFormat('Y-m-d', $search['fine']) : new \DateTime());
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $this->reqstack->getSession()->get('/APP/ROUTE/staff_docenti_statistiche/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_docenti_statistiche/pagina', $pagina);
    }
    // form di ricerca
    $limite = 20;
    $opzioniDocenti = $this->em->getRepository('App\Entity\Docente')->opzioni();
    $opzioniDocenti[$trans->trans('label.tutti_docenti')] = -1;
    $form = $this->container->get('form.factory')->createNamedBuilder('staff_docenti_statistiche', FormType::class)
      ->setAction($this->generateUrl('staff_docenti_statistiche'))
      ->add('docente', ChoiceType::class, array('label' => 'label.docente',
        'data' => $docente,
        'choices' => $opzioniDocenti,
        'choice_value' => 'id',
        'placeholder' => 'label.scegli_docente',
        'label_attr' => ['class' => 'sr-only'],
        'choice_attr' => function($val, $key, $index) {
            return ['class' => 'gs-no-placeholder'];
          },
        'attr' => ['class' => 'gs-placeholder'],
        'choice_translation_domain' => false,
        'required' => false))
      ->add('inizio', DateType::class, array('label' => 'label.data_inizio',
        'data' => $inizio,
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-picker'],
        'format' => 'dd/MM/yyyy',
        'required' => false))
      ->add('fine', DateType::class, array('label' => 'label.data_fine',
        'data' => $fine,
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-picker'],
        'format' => 'dd/MM/yyyy',
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.search'))
      ->add('print', SubmitType::class, array('label' => 'label.stampa',
        'attr' => ['class' => 'btn-success']))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $search['docente'] = (is_object($form->get('docente')->getData()) ? $form->get('docente')->getData()->getId() :
        ($form->get('docente')->getData() < 0 ? -1 : null));
      $search['inizio'] = ($form->get('inizio')->getData() ? $form->get('inizio')->getData()->format('Y-m-d') : 0);
      $search['fine'] = ($form->get('fine')->getData() ? $form->get('fine')->getData()->format('Y-m-d') : 0);
      $docente = ($search['docente'] > 0 ? $this->em->getRepository('App\Entity\Docente')->find($search['docente']) :
        ($search['docente'] < 0 ? -1 : null));
      $inizio = ($form->get('inizio')->getData() ? $form->get('inizio')->getData() : new \DateTime());
      $fine = ($form->get('fine')->getData() ? $form->get('fine')->getData() : new \DateTime());
      $pagina = 1;
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_docenti_statistiche/docente', $search['docente']);
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_docenti_statistiche/inizio', $search['inizio']);
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_docenti_statistiche/fine', $search['fine']);
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_docenti_statistiche/pagina', $pagina);
      $creaPdf = ($form->get('print')->isClicked());
    }
    // statistiche
    if ($creaPdf) {
      // crea PDF
      $lista = $staff->statisticheStampa($docente, $inizio, $fine);
      $pdf->configure($this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
        'Statistiche sulle ore di lezione dei docenti');
      $pdf->getHandler()->SetAutoPageBreak(true, 15);
      $pdf->getHandler()->SetFooterMargin(15);
      $pdf->getHandler()->setFooterFont(array('helvetica', '', 9));
      $pdf->getHandler()->setFooterData(array(0,0,0), array(255,255,255));
      $pdf->getHandler()->setPrintFooter(true);
      $html = $this->renderView('pdf/statistiche_docenti.html.twig', array(
        'lista' => $lista,
        'search' => $search,
        ));
      $pdf->createFromHtml($html);
      // invia il documento
      $nomefile = 'statistiche-docenti.pdf';
      return $pdf->send($nomefile);
    } else {
      // mostra la pagina di risposta
      $lista = $staff->statistiche($docente, $inizio, $fine, $pagina, $limite);
      return $this->render('ruolo_staff/docenti_statistiche.html.twig', array(
        'pagina_titolo' => 'page.staff_statistiche',
        'form' => $form->createView(),
        'lista' => $lista,
        'page' => $pagina,
        'maxPages' => ceil($lista->count() / $limite),
      ));
    }
  }

  /**
   * Gestisce la generazione della password per gli alunni
   *
   * @param Request $request Pagina richiesta
   * @param int $pagina Numero di pagina per la lista dei alunni
   *
   * @return Response Pagina di risposta
   *
   * @Route("/staff/password/{pagina}", name="staff_password",
   *    requirements={"pagina": "\d+"},
   *    defaults={"pagina": 0},
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_STAFF")
   */
  public function passwordAction(Request $request, int $pagina): Response {
    // recupera criteri dalla sessione
    $search = array();
    $search['classe'] = $this->reqstack->getSession()->get('/APP/ROUTE/staff_password/classe');
    $classe = ($search['classe'] > 0 ? $this->em->getRepository('App\Entity\Classe')->find($search['classe']) : null);
    $search['cognome'] = $this->reqstack->getSession()->get('/APP/ROUTE/staff_password/cognome', '');
    $search['nome'] = $this->reqstack->getSession()->get('/APP/ROUTE/staff_password/nome', '');
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $this->reqstack->getSession()->get('/APP/ROUTE/staff_password/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_password/pagina', $pagina);
    }
    // legge sede
    $sede = $this->getUser()->getSede();
    // form di ricerca
    $limite = 20;
    $opzioniClassi = $this->em->getRepository('App\Entity\Classe')->opzioni(
      $this->getUser()->getSede() ? $this->getUser()->getSede()->getId() : null, false);
    $form = $this->container->get('form.factory')->createNamedBuilder('staff_password', FormType::class)
      ->setAction($this->generateUrl('staff_password'))
      ->add('classe', ChoiceType::class, array('label' => 'label.classe',
        'data' => $classe,
        'choices' => $opzioniClassi,
        'choice_value' => 'id',
        'placeholder' => 'label.qualsiasi_classe',
        'choice_translation_domain' => false,
        'label_attr' => ['class' => 'sr-only'],
        'required' => false))
      ->add('cognome', TextType::class, array('label' => 'label.cognome',
        'data' => $search['cognome'],
        'attr' => ['placeholder' => 'label.cognome'],
        'label_attr' => ['class' => 'sr-only'],
        'required' => false))
      ->add('nome', TextType::class, array('label' => 'label.nome',
        'data' => $search['nome'],
        'attr' => ['placeholder' => 'label.nome'],
        'label_attr' => ['class' => 'sr-only'],
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.search'))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $search['classe'] = (is_object($form->get('classe')->getData()) ? $form->get('classe')->getData()->getId() : 0);
      $search['cognome'] = trim($form->get('cognome')->getData());
      $search['nome'] = trim($form->get('nome')->getData());
      $pagina = 1;
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_password/classe', $search['classe']);
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_password/cognome', $search['cognome']);
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_password/nome', $search['nome']);
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_password/pagina', $pagina);
    }
    // lista alunni
    $lista['lista'] = $this->em->getRepository('App\Entity\Alunno')->findClassEnabled($sede, $search, $pagina, $limite);
    $lista['genitori'] = $this->em->getRepository('App\Entity\Genitore')->datiGenitoriPaginator($lista['lista']);
    // mostra la pagina di risposta
    return $this->render('ruolo_staff/password.html.twig', array(
      'pagina_titolo' => 'page.staff_password',
      'form' => $form->createView(),
      'form_help' => null,
      'form_success' => null,
      'lista' => $lista,
      'page' => $pagina,
      'maxPages' => ceil($lista['lista']->count() / $limite),
    ));
  }

  /**
   * Generazione della password degli alunni
   *
   * @param Request $request Pagina richiesta
   * @param UserPasswordHasherInterface $hasher Gestore della codifica delle password
   * @param StaffUtil $staff Funzioni di utilità per lo staff
   * @param LogHandler $dblogger Gestore dei log su database
   * @param LoggerInterface $logger Gestore dei log su file
   * @param PdfManager $pdf Gestore dei documenti PDF
   * @param MailerInterface $mailer Gestore della spedizione delle email
   * @param string $tipo Indica se inviare l'email (E) o scaricare il PDF (P)
   * @param string $username Identificativo dell'utente
   *
   * @return Response Pagina di risposta
   *
   * @Route("/staff/password/create/{tipo}/{username}", name="staff_password_create",
   *    requirements={"tipo": "E|P"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_STAFF")
   */
  public function passwordCreateAction(Request $request, UserPasswordHasherInterface $hasher,
                                       StaffUtil $staff, LogHandler $dblogger, LoggerInterface $logger ,
                                       PdfManager $pdf, MailerInterface $mailer, string $tipo, 
                                       string $username = null): Response {
     // controlla alunno
     $utente = $this->em->getRepository('App\Entity\Alunno')->findOneByUsername($username);
     if (!$utente) {
       // controlla genitore
       $utente = $this->em->getRepository('App\Entity\Genitore')->findOneByUsername($username);
       if (!$utente) {
         // errore
         throw $this->createNotFoundException('exception.id_notfound');
       }
     }
    // crea password
    $password = $staff->creaPassword(8);
    $utente->setPasswordNonCifrata($password);
    $pswd = $hasher->hashPassword($utente, $utente->getPasswordNonCifrata());
    $utente->setPassword($pswd);
    // provisioning
    if ($utente instanceOf Alunno) {
      $provisioning = (new Provisioning())
        ->setUtente($utente)
        ->setFunzione('passwordUtente')
        ->setDati(['password' => $utente->getPasswordNonCifrata()]);
      $this->em->persist($provisioning);
    }
    // memorizza su db
    $this->em->flush();
    // log azione
    $dblogger->logAzione('SICUREZZA', 'Generazione Password', array(
      'Username' => $utente->getUsername(),
      'Ruolo' => $utente->getRoles()[0],
      'ID' => $utente->getId()));
    // crea documento PDF
    $pdf->configure($this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
      'Credenziali di accesso al Registro Elettronico');
    // contenuto in formato HTML
    if ($utente instanceOf Alunno) {
      $html = $this->renderView('pdf/credenziali_profilo_alunni.html.twig', array(
        'alunno' => $utente,
        'sesso' => ($utente->getSesso() == 'M' ? 'o' : 'a'),
        'username' => $utente->getUsername(),
        'password' => $password));
    } else {
      $html = $this->renderView('pdf/credenziali_profilo_genitori.html.twig', array(
        'alunno' => $utente->getAlunno(),
        'genitore' => $utente,
        'sesso' => ($utente->getAlunno()->getSesso() == 'M' ? 'o' : 'a'),
        'username' => $utente->getUsername(),
        'password' => $password));
    }
    $pdf->createFromHtml($html);
    if ($tipo == 'E') {
      // invia password per email
      $doc = $pdf->getHandler()->Output('', 'S');
      $message = (new Email())
        ->from(new Address($this->reqstack->getSession()->get('/CONFIG/ISTITUTO/email_notifiche'), $this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione_breve')))
        ->to($utente->getEmail())
        ->subject($this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione_breve')." - Credenziali di accesso al Registro Elettronico")
        ->text($this->renderView('email/credenziali.txt.twig'))
        ->html($this->renderView('email/credenziali.html.twig'))
        ->attach($doc, 'credenziali_registro.pdf', 'application/pdf');
      try {
        // invia email
        $mailer->send($message);
        $this->addFlash('success', 'message.credenziali_inviate');
      } catch (\Exception $err) {
        // errore di spedizione
        $logger->error('Errore di spedizione email delle credenziali alunno/genitore.', array(
          'username' => $utente->getUsername(),
          'email' => $utente->getEmail(),
          'ip' => $request->getClientIp(),
          'errore' => $err->getMessage()));
        $this->addFlash('danger', 'exception.errore_invio_credenziali');
      }
      // redirezione
      return $this->redirectToRoute('staff_password');
    } else {
      // crea pdf e lo scarica
      $nomefile = 'credenziali-registro.pdf';
      return $pdf->send($nomefile);
    }
  }

  /**
   * Gestione delle assenze di classe
   *
   * @param Request $request Pagina richiesta
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param LogHandler $dblogger Gestore dei log su database
   * @param string $data Data per la gestione delle assenze (AAAA-MM-GG)
   * @param int $classe Identificativo della classe
   *
   * @return Response Pagina di risposta
   *
   * @Route("/staff/studenti/assenze/{data}/{classe}", name="staff_studenti_assenze",
   *    requirements={"data": "\d\d\d\d-\d\d-\d\d", "classe": "\d+"},
   *    defaults={"data": "0000-00-00", "classe": "0"},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_STAFF")
   */
  public function studentiAssenzeAction(Request $request, RegistroUtil $reg, LogHandler $dblogger,
                                        string $data, int $classe): Response {
    // inizializza variabili
    $lista_festivi = null;
    $errore = null;
    $info = null;
    $data_succ = null;
    $data_prec = null;
    $form_assenze = null;
    // parametro data
    if ($data == '0000-00-00') {
      // data non specificata
      if ($this->reqstack->getSession()->get('/APP/ROUTE/staff_studenti_assenze/data')) {
        // recupera data da sessione
        $data_obj = \DateTime::createFromFormat('Y-m-d', $this->reqstack->getSession()->get('/APP/ROUTE/staff_studenti_assenze/data'));
      } else {
        // imposta data odierna
        $data_obj = new \DateTime();
      }
    } else {
      // imposta data indicata e la memorizza in sessione
      $data_obj = \DateTime::createFromFormat('Y-m-d', $data);
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_studenti_assenze/data', $data);
    }
    // data in formato stringa
    $formatter = new \IntlDateFormatter('it_IT', \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $info['data_label'] =  $formatter->format($data_obj);
    // data prec/succ
    $data_succ = (clone $data_obj);
    $data_succ = $this->em->getRepository('App\Entity\Festivita')->giornoSuccessivo($data_succ);
    $data_prec = (clone $data_obj);
    $data_prec = $this->em->getRepository('App\Entity\Festivita')->giornoPrecedente($data_prec);
    // recupera festivi per calendario
    $lista_festivi = $reg->listaFestivi(null);
    // controllo data
    $errore = $reg->controlloData($data_obj, null);
    // parametro classe (può essere null)
    if ($classe == 0) {
      // classe non specificata
      $classe = $this->reqstack->getSession()->get('/APP/ROUTE/staff_studenti_assenze/classe', 0);
    }
    $classe = $this->em->getRepository('App\Entity\Classe')->find($classe);
    // legge sede
    $sede = $this->getUser()->getSede();
    // form di ricerca
    $opzioniClassi = $this->em->getRepository('App\Entity\Classe')->opzioni(
      $this->getUser()->getSede() ? $this->getUser()->getSede()->getId() : null, false);
    $form = $this->container->get('form.factory')->createNamedBuilder('staff_studenti_assenze', FormType::class)
      ->setMethod('GET')
      ->setAction($this->generateUrl('staff_studenti_assenze', ['data' => $data]))
      ->add('classe', ChoiceType::class, array('label' => 'label.classe',
        'data' => $classe,
        'choices' => $opzioniClassi,
        'choice_value' => 'id',
        'placeholder' => 'label.qualsiasi_classe',
        'choice_translation_domain' => false,
        'label_attr' => ['class' => 'sr-only'],
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.visualizza'))
      ->getForm();
    $form->handleRequest($request);
    if ($classe) {
      // memorizza classe
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_studenti_assenze/classe', $classe->getId());
      if (!$errore && $reg->azioneAssenze($data_obj, $this->getUser(), null, $classe, null)) {
        // elenco alunni
        $elenco = $reg->alunniInData($data_obj, $classe);
        // elenco assenze
        $assenti = $this->em->getRepository('App\Entity\Alunno')->createQueryBuilder('a')
          ->join('App\Entity\Assenza', 'ass', 'WITH', 'a.id=ass.alunno AND ass.data=:data')
          ->where('a.id IN (:elenco)')
          ->setParameters(['elenco' => $elenco, 'data' => $data_obj->format('Y-m-d')])
          ->getQuery()
          ->getResult();
        // form di inserimento
        $form_assenze = $this->container->get('form.factory')->createNamedBuilder('staff_studenti_assenze_appello', FormType::class)
          ->add('alunni', EntityType::class, array('label' => 'label.alunni_assenti',
            'data' => $assenti,
            'class' => 'App\Entity\Alunno',
            'choice_label' => function ($obj) {
                return $obj->getCognome().' '.$obj->getNome().' ('.
                  $obj->getDataNascita()->format('d/m/Y').')';
              },
            'query_builder' => function (EntityRepository $er) use ($elenco) {
                return $er->createQueryBuilder('a')
                  ->where('a.id IN (:elenco)')
                  ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
                  ->setParameters(['elenco' => $elenco]);
              },
            'label_attr' => ['class' => 'gs-pt-0 checkbox-split-vertical'],
            'expanded' => true,
            'multiple' => true,
            'required' => false))
          ->add('submit', SubmitType::class, array('label' => 'label.submit',
            'attr' => ['class' => 'btn-primary'],
            ))
          ->getForm();
        $form_assenze->handleRequest($request);
        if ($form_assenze->isSubmitted() && $form_assenze->isValid()) {
          $log['assenza_create'] = array();
          $log['assenza_delete'] = array();
          $log['entrata_delete'] = array();
          $log['uscita_delete'] = array();
          // aggiunge assenti
          $nuovi_assenti = array_diff($form_assenze->get('alunni')->getData(), $assenti);
          foreach ($nuovi_assenti as $alu) {
            // inserisce nuova assenza
            $assenza = (new Assenza())
              ->setData($data_obj)
              ->setAlunno($alu)
              ->setDocente($this->getUser());
            $this->em->persist($assenza);
            $log['assenza_create'][] = $assenza;
            // controlla esistenza ritardo
            $entrata = $this->em->getRepository('App\Entity\Entrata')->findOneBy(['alunno' => $alu, 'data' => $data_obj]);
            if ($entrata) {
              // rimuove ritardo
              $log['entrata_delete'][$entrata->getId()] = $entrata;
              $this->em->remove($entrata);
            }
            // controlla esistenza uscita
            $uscita = $this->em->getRepository('App\Entity\Uscita')->findOneBy(['alunno' => $alu, 'data' => $data_obj]);
            if ($uscita) {
              // rimuove uscita
              $log['uscita_delete'][$uscita->getId()] = $uscita;
              $this->em->remove($uscita);
            }
          }
          // cancella assenti
          $cancella_assenti = array_diff($assenti, $form_assenze->get('alunni')->getData());
          foreach ($cancella_assenti as $alu) {
            $assenza = $this->em->getRepository('App\Entity\Assenza')->findOneBy(['alunno' => $alu, 'data' => $data_obj]);
            if ($assenza) {
              // rimuove assenza
              $log['assenza_delete'][$assenza->getId()] = $assenza;
              $this->em->remove($assenza);
            }
          }
          // ok: memorizza dati
          $this->em->flush();
          // ricalcola ore assenze
          foreach (array_merge($nuovi_assenti, $cancella_assenti) as $alu) {
            $reg->ricalcolaOreAlunno($data_obj, $alu);
          }
          // log azione
          $dblogger->logAzione('ASSENZE', 'Gestione assenti', array(
            'Data' => $data,
            'Assenze create' => implode(', ', array_map(function ($e) {
                return $e->getId();
              }, $log['assenza_create'])),
            'Assenze cancellate' => implode(', ', array_map(function ($k,$e) {
                return '[Assenza: '.$k.
                  ', Alunno: '.$e->getAlunno()->getId().
                  ', Giustificato: '.($e->getGiustificato() ? $e->getGiustificato()->format('Y-m-d') : '').
                  ', Docente: '.$e->getDocente()->getId().
                  ', DocenteGiustifica: '.($e->getDocenteGiustifica() ? $e->getDocenteGiustifica()->getId() : '').']';
              }, array_keys($log['assenza_delete']), $log['assenza_delete'])),
            'Entrate cancellate' => implode(', ', array_map(function ($k,$e) {
                return '[Entrata: '.$k.
                  ', Alunno: '.$e->getAlunno()->getId().
                  ', Ora: '.$e->getOra()->format('H:i').
                  ', Note: "'.$e->getNote().'"'.
                  ', Giustificato: '.($e->getGiustificato() ? $e->getGiustificato()->format('Y-m-d') : '').
                  ', Docente: '.$e->getDocente()->getId().
                  ', DocenteGiustifica: '.($e->getDocenteGiustifica() ? $e->getDocenteGiustifica()->getId() : '').']';
              }, array_keys($log['entrata_delete']), $log['entrata_delete'])),
            'Uscite cancellate' => implode(', ', array_map(function ($k,$e) {
                return '[Uscita: '.$k.
                  ', Alunno: '.$e->getAlunno()->getId().
                  ', Ora: '.$e->getOra()->format('H:i').
                  ', Note: "'.$e->getNote().'"'.
                  ', Docente: '.$e->getDocente()->getId();
              }, array_keys($log['uscita_delete']), $log['uscita_delete']))
            ));
        }
      }
    }
    // mostra la pagina di risposta
    return $this->render('ruolo_staff/studenti_assenze.html.twig', array(
      'pagina_titolo' => 'page.staff_assenze',
      'form' => $form->createView(),
      'form_help' => null,
      'form_success' => null,
      'classe' => $classe,
      'data' => $data_obj->format('Y-m-d'),
      'data_succ' => $data_succ,
      'data_prec' => $data_prec,
      'errore' => $errore,
      'lista_festivi' => $lista_festivi,
      'info' => $info,
      'form_assenze' => ($form_assenze ? $form_assenze->createView() : null),
    ));
  }

  /**
   * Visualizza statistiche sulle presenze in classe
   *
   * @param Request $request Pagina richiesta
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param StaffUtil $staff Funzioni di utilità per lo staff
   * @param string $data Data per la gestione delle assenze (AAAA-MM-GG)
   *
   * @return Response Pagina di risposta
   *
   * @Route("/staff/studenti/statistiche/{data}", name="staff_studenti_statistiche",
   *    requirements={"data": "\d\d\d\d-\d\d-\d\d"},
   *    defaults={"data": "0000-00-00"},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_STAFF")
   */
  public function studentiStatisticheAction(Request $request, RegistroUtil $reg, StaffUtil $staff, 
                                            string $data): Response {
    // inizializza variabili
    $lista_festivi = null;
    $errore = null;
    $dati = null;
    $info = null;
    $data_succ = null;
    $data_prec = null;
    // parametro data
    if ($data == '0000-00-00') {
      // data non specificata
      if ($this->reqstack->getSession()->get('/APP/ROUTE/staff_studenti_statistiche/data')) {
        // recupera data da sessione
        $data_obj = \DateTime::createFromFormat('Y-m-d', $this->reqstack->getSession()->get('/APP/ROUTE/staff_studenti_statistiche/data'));
      } else {
        // imposta data odierna
        $data_obj = new \DateTime();
      }
    } else {
      // imposta data indicata e la memorizza in sessione
      $data_obj = \DateTime::createFromFormat('Y-m-d', $data);
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_studenti_statistiche/data', $data);
    }
    // data in formato stringa
    $formatter = new \IntlDateFormatter('it_IT', \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $info['data_label'] =  $formatter->format($data_obj);
    // data prec/succ
    $data_succ = (clone $data_obj);
    $data_succ = $this->em->getRepository('App\Entity\Festivita')->giornoSuccessivo($data_succ);
    $data_prec = (clone $data_obj);
    $data_prec = $this->em->getRepository('App\Entity\Festivita')->giornoPrecedente($data_prec);
    // recupera festivi per calendario
    $lista_festivi = $reg->listaFestivi(null);
    // controllo data
    $errore = $reg->controlloData($data_obj, null);
    // recupera criteri dalla sessione
    $search = array();
    $search['sede'] = $this->reqstack->getSession()->get('/APP/ROUTE/staff_studenti_statistiche/sede');
    $sede = ($search['sede'] > 0 ? $this->em->getRepository('App\Entity\Sede')->find($search['sede']) : null);
    $search['classe'] = $this->reqstack->getSession()->get('/APP/ROUTE/staff_studenti_statistiche/classe');
    $classe = ($search['classe'] > 0 ? $this->em->getRepository('App\Entity\Classe')->find($search['classe']) : null);
    // legge sede
    $sede_staff = $this->getUser()->getSede();
    // form di ricerca
    if ($this->getUser()->getSede()) {
      $opzioniSedi[$this->getUser()->getSede()->getNomeBreve()] = $this->getUser()->getSede();
    } else {
      $opzioniSedi = $this->em->getRepository('App\Entity\Sede')->opzioni();
    }
    foreach ($opzioniSedi as $s) {
      $info['sedi'][$s->getId()] = $s->getNomeBreve();
    }
    $opzioniClassi = $this->em->getRepository('App\Entity\Classe')->opzioni(
      $this->getUser()->getSede() ? $this->getUser()->getSede()->getId() : null, false);
    $form = $this->container->get('form.factory')->createNamedBuilder('staff_studenti_statistiche', FormType::class)
      ->setAction($this->generateUrl('staff_studenti_statistiche', ['data' => $data_obj->format('Y-m-d')]))
      ->add('sede', ChoiceType::class, array('label' => 'label.sede',
        'data' => $sede,
        'choices' => $opzioniSedi,
        'choice_value' => 'id',
        'placeholder' => 'label.qualsiasi_sede',
        'choice_translation_domain' => false,
        'label_attr' => ['class' => 'sr-only'],
        'required' => false))
      ->add('classe', ChoiceType::class, array('label' => 'label.classe',
        'data' => $classe,
        'choices' => $opzioniClassi,
        'choice_value' => 'id',
        'placeholder' => 'label.qualsiasi_classe',
        'choice_translation_domain' => false,
        'label_attr' => ['class' => 'sr-only'],
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.visualizza'))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid() && !$errore) {
      // imposta criteri di ricerca
      $search['sede'] = (is_object($form->get('sede')->getData()) ? $form->get('sede')->getData()->getId() : 0);
      $search['classe'] = (is_object($form->get('classe')->getData()) ? $form->get('classe')->getData()->getId() : 0);
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_studenti_statistiche/sede', $search['sede']);
      $this->reqstack->getSession()->set('/APP/ROUTE/staff_studenti_statistiche/classe', $search['classe']);
      // recupera dati
      $dati = $staff->statisticheAlunni($data_obj, $search);
    }
    // mostra la pagina di risposta
    return $this->render('ruolo_staff/studenti_statistiche.html.twig', array(
      'pagina_titolo' => 'page.staff_statistiche',
      'form' => $form->createView(),
      'form_help' => null,
      'form_success' => null,
      'data' => $data_obj->format('Y-m-d'),
      'data_succ' => $data_succ,
      'data_prec' => $data_prec,
      'errore' => $errore,
      'lista_festivi' => $lista_festivi,
      'info' => $info,
      'dati' => $dati,
    ));
  }

  /**
   * Gestione delle richieste dei certificati medici
   *
   //-- * @param Request $request Pagina richiesta
   //-- * @param EntityManagerInterface $this->em Gestore delle entità
   //-- * @param RequestStack $this->reqstack Gestore dello stack delle variabili globali
   //-- * @param RegistroUtil $reg Funzioni di utilità per il registro
   //-- * @param LogHandler $dblogger Gestore dei log su database
   //-- * @param string $data Data per la gestione delle assenze (AAAA-MM-GG)
   //-- * @param int $classe Identificativo della classe
   *
   * @return Response Pagina di risposta
   *
   * @Route("/staff/studenti/certificato", name="staff_studenti_certificato",
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_STAFF")
   */
  public function studentiCertificatoAction(Request $request
  //-- ,
                                         //-- RegistroUtil $reg, LogHandler $dblogger, $data, $classe
): Response {
    // init
    $dati = array();
    // legge alunni con richiesta certificato
    $dati = $this->em->getRepository('App\Entity\Alunno')->richiestaCertificato($this->getUser()->getSede());


    // mostra la pagina di risposta
    return $this->render('ruolo_staff/studenti_certificato.html.twig', array(
      'pagina_titolo' => 'page.staff_certificato',
      'dati' => $dati,
    ));
 }

}
