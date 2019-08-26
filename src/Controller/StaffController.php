<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2019 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2019
 */


namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Translation\TranslatorInterface;
use App\Entity\Annotazione;
use App\Entity\Colloquio;
use App\Entity\Avviso;
use App\Entity\Assenza;
use App\Entity\Entrata;
use App\Entity\Uscita;
use App\Entity\Notifica;
use App\Util\RegistroUtil;
use App\Util\StaffUtil;
use App\Util\LogHandler;
use App\Util\PdfManager;
use App\Util\BachecaUtil;
use App\Form\EntrataType;
use App\Form\UscitaType;
use App\Form\AppelloType;


/**
 * StaffController - funzioni per lo staff
 */
class StaffController extends AbstractController {

  /**
   * Gestione degli avvisi generici da parte dello staff
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
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
  public function avvisiAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                BachecaUtil $bac, $pagina) {
    // inizializza variabili
    $dati = null;
    // recupera criteri dalla sessione
    $search = array();
    $search['docente'] = $session->get('/APP/ROUTE/staff_avvisi/docente', 0);
    $search['destinatari'] = $session->get('/APP/ROUTE/staff_avvisi/destinatari', '');
    $search['classe'] = $session->get('/APP/ROUTE/staff_avvisi/classe', 0);
    $docente = ($search['docente'] > 0 ? $em->getRepository('App:Docente')->find($search['docente']) : 0);
    $classe = ($search['classe'] > 0 ? $em->getRepository('App:Classe')->find($search['classe']) : 0);
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $session->get('/APP/ROUTE/staff_avvisi/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $session->set('/APP/ROUTE/staff_avvisi/pagina', $pagina);
    }
    // form di ricerca
    $limite = 25;
    $form = $this->container->get('form.factory')->createNamedBuilder('staff_avvisi', FormType::class)
      ->add('docente', EntityType::class, array('label' => 'label.autore',
        'data' => $docente,
        'class' => 'App:Staff',
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
          'label.genitori' => 'G', 'label.alunni' => 'A'],
        'placeholder' => 'label.tutti_destinatari',
        'label_attr' => ['class' => 'sr-only'],
        'choice_attr' => function($val, $key, $index) {
            return ['class' => 'gs-no-placeholder'];
          },
        'attr' => ['class' => 'gs-placeholder'],
        'required' => false))
      ->add('classe', EntityType::class, array('label' => 'label.classe',
        'data' => $classe,
        'class' => 'App:Classe',
        'choice_label' => function ($obj) {
            return $obj->getAnno().'ª '.$obj->getSezione();
          },
        'placeholder' => 'label.qualsiasi_classe',
        'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('c')
              ->orderBy('c.anno,c.sezione', 'ASC');
          },
        'group_by' => function ($obj) {
            return $obj->getSede()->getCitta();
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
      $search['destinatari'] = ($form->get('destinatari')->getData() ? $form->get('destinatari')->getData() : '');
      $search['classe'] = (is_object($form->get('classe')->getData()) ? $form->get('classe')->getData()->getId() : 0);
      $pagina = 1;
      $session->set('/APP/ROUTE/staff_avvisi/docente', $search['docente']);
      $session->set('/APP/ROUTE/staff_avvisi/destinatari', $search['destinatari']);
      $session->set('/APP/ROUTE/staff_avvisi/classe', $search['classe']);
      $session->set('/APP/ROUTE/staff_avvisi/pagina', $pagina);
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
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param TranslatorInterface $trans Gestore delle traduzioni
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
  public function avvisiEditAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                    TranslatorInterface $trans, BachecaUtil $bac, RegistroUtil $reg, LogHandler $dblogger, $id) {
    // inizializza
    $var_sessione = '/APP/FILE/staff_avvisi_edit/files';
    $dir = $this->getParameter('dir_avvisi').'/';
    $fs = new FileSystem();
    // controlla azione
    if ($id > 0) {
      // azione edit
      $avviso = $em->getRepository('App:Avviso')->findOneBy(['id' => $id, 'tipo' => 'C']);
      if (!$avviso) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $avviso_old = clone $avviso;
    } else {
      // azione add
      $avviso = (new Avviso())
        ->setTipo('C')
        ->setDestinatariStaff(false)
        ->setDestinatariCoordinatori(false)
        ->setDestinatariDocenti(false)
        ->setDestinatariGenitori(false)
        ->setDestinatariAlunni(false)
        ->setDestinatariIndividuali(false);
      $em->persist($avviso);
    }
    // legge allegati
    $allegati = array();
    if ($request->isMethod('POST')) {
      // pagina inviata
      foreach ($session->get($var_sessione, []) as $f) {
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
      $session->remove($var_sessione);
      $session->set($var_sessione, $allegati);
      // elimina file temporanei
      $finder = new Finder();
      $finder->in($this->getParameter('dir_tmp'))->date('< 1 day ago');
      foreach ($finder as $f) {
        $fs->remove($f);
      }
    }
    // legge destinatari di filtri
    $dest_filtro = $bac->filtriAvviso($avviso);
    // imposta autore dell'avviso
    $avviso->setDocente($this->getUser());
    // imposta lettura non avvenuta (per avviso modificato)
    foreach ($dest_filtro['classi'] as $k=>$v) {
      $dest_filtro['classi'][$k]['lettoAlunni'] = null;
      $dest_filtro['classi'][$k]['lettoCoordinatore'] = null;
    }
    foreach ($dest_filtro['utenti'] as $k=>$v) {
      $dest_filtro['genitori'][$k]['letto'] = null;
    }
    // opzione scelta per staff
    $scelta_staff_filtro = 'N';
    $scelta_staff_sedi = array();
    if ($avviso->getDestinatariStaff()) {
      $scelta_staff_filtro = 'S';
      foreach (array_column($dest_filtro['sedi'], 'sede') as $s) {
        $sede = $em->getRepository('App:Sede')->find($s);
        if ($sede) {
          $scelta_staff_sedi[] = $sede;
        }
      }
    }
    // opzione scelta destinatari
    $scelta_destinatari = array();
    if ($avviso->getDestinatariCoordinatori()) {
      $scelta_destinatari[] = 'C';
    }
    if ($avviso->getDestinatariDocenti()) {
      $scelta_destinatari[] = 'D';
    }
    if ($avviso->getDestinatariGenitori()) {
      $scelta_destinatari[] = 'G';
    }
    if ($avviso->getDestinatariAlunni()) {
      $scelta_destinatari[] = 'A';
    }
    // opzione scelta filtro
    $scelta_filtro = 'N';
    $scelta_filtro_sedi = array();
    $scelta_filtro_classi = array();
    $scelta_filtro_individuale = array();
    $scelta_filtro_individuale_classe = null;
    if ($avviso->getDestinatariIndividuali()) {
      $scelta_filtro = 'I';
      foreach (array_column($dest_filtro['utenti'], 'alunno') as $a) {
        $alunno = $em->getRepository('App:Alunno')->find($a);
        if ($alunno) {
          $scelta_filtro_individuale[] = $alunno->getId();
          $scelta_filtro_individuale_classe = $alunno->getClasse();
        }
      }
    } elseif (!empty($scelta_destinatari)) {
      $scelta_filtro = 'C';
      foreach (array_column($dest_filtro['classi'], 'classe') as $c) {
        $classe = $em->getRepository('App:Classe')->find($c);
        if ($classe) {
          $scelta_filtro_classi[] = $classe;
        }
      }
    }
    // form di inserimento
    $form = $this->container->get('form.factory')->createNamedBuilder('avvisi_edit', FormType::class, $avviso)
      ->add('data', DateType::class, array('label' => 'label.data_evento',
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-picker'],
        'format' => 'dd/MM/yyyy',
        'required' => true))
      ->add('oggetto', TextType::class, array(
        'label' => 'label.oggetto',
        'required' => true))
      ->add('testo', TextareaType::class, array(
        'label' => 'label.testo',
        'attr' => array('rows' => '4'),
        'required' => true))
      ->add('creaAnnotazione', ChoiceType::class, array('label' => 'label.crea_annotazione',
        'data' => (count($avviso->getAnnotazioni()) > 0),
        'choices' => ['label.si' => true, 'label.no' => false],
        'expanded' => true,
        'multiple' => false,
        'label_attr' => ['class' => 'radio-inline'],
        'mapped' => false,
        'required' => true))
      ->add('destinatari', ChoiceType::class, array('label' => false,
        'data' => $scelta_destinatari,
        'choices' => ['label.coordinatori' => 'C', 'label.docenti' => 'D',
          'label.genitori' => 'G', 'label.leggere_in_classe' => 'A'],
        'expanded' => true,
        'multiple' => true,
        'label_attr' => ['class' => 'gs-mr-4 gs-checkbox-inline'],
        'mapped' => false,
        'required' => true))
      ->add('filtro', ChoiceType::class, array('label' => false,
        'data' => $scelta_filtro,
        'choices' => ['label.filtro_nessuno' => 'N', 'label.filtro_tutti' => 'T', 'label.filtro_sede' => 'S',
          'label.filtro_classe' => 'C', 'label.filtro_individuale' => 'I'],
        'expanded' => false,
        'multiple' => false,
        'mapped' => false,
        'required' => true))
      ->add('filtroSedi', EntityType::class, array('label' => false,
        'data' => $scelta_filtro_sedi,
        'class' => 'App:Sede',
        'choice_label' => function ($obj) {
            return $obj->getCitta();
          },
        'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('s')
              ->orderBy('s.principale', 'DESC')
              ->addOrderBy('s.citta', 'ASC');
          },
        'expanded' => true,
        'multiple' => true,
        'choice_translation_domain' => false,
        'label_attr' => ['class' => 'gs-pt-0 checkbox-split-vertical'],
        'mapped' => false,
        'required' => false))
      ->add('filtroClassi', EntityType::class, array('label' => false,
        'data' => $scelta_filtro_classi,
        'class' => 'App:Classe',
        'choice_label' => function ($obj) {
            return $obj->getAnno().'ª '.$obj->getSezione();
          },
        'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('c')
              ->orderBy('c.anno,c.sezione', 'ASC');
          },
        'group_by' => function ($obj) {
            return $obj->getSede()->getCitta();
          },
        'expanded' => true,
        'multiple' => true,
        'choice_translation_domain' => false,
        'label_attr' => ['class' => 'gs-pt-0 checkbox-grouped-4'],
        'mapped' => false,
        'required' => false))
      ->add('filtroIndividualeClasse', EntityType::class, array('label' => false,
        'data' => $scelta_filtro_individuale_classe,
        'class' => 'App:Classe',
        'choice_label' => function ($obj) {
            return $obj->getAnno().'ª '.$obj->getSezione().' - '.$obj->getCorso()->getNomeBreve();
          },
        'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('c')
              ->orderBy('c.anno,c.sezione', 'ASC');
          },
        'group_by' => function ($obj) {
            return $obj->getSede()->getCitta();
          },
        'expanded' => false,
        'multiple' => false,
        'placeholder' => 'label.scegli_classe',
        'choice_translation_domain' => false,
        'attr' => ['style' => 'width:auto'],
        'label_attr' => ['class' => 'gs-pt-0 checkbox-split-vertical'],
        'mapped' => false,
        'required' => false))
      ->add('filtroIndividuale',  HiddenType::class, array('label' => false,
        'data' => implode(',', $scelta_filtro_individuale),
        'mapped' => false,
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start']))
      ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end',
        'onclick' => "location.href='".$this->generateUrl('staff_avvisi')."'"]))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // recupera dati
      $val_staff_filtro = 'N';
      $val_staff_sedi = array();
      $val_destinatari = $form->get('destinatari')->getData();
      $val_filtro = $form->get('filtro')->getData();
      $val_filtro_id = array();
      $val_filtro_individuale_classe = $form->get('filtroIndividualeClasse')->getData();
      switch ($val_filtro) {
        case 'S':
          foreach ($form->get('filtroSedi')->getData() as $f) {
            $val_filtro_id[] = $f->getId();
          }
          break;
        case 'C':
          foreach ($form->get('filtroClassi')->getData() as $f) {
            $val_filtro_id[] = $f->getId();
          }
          break;
        case 'I':
          foreach (explode(',', $form->get('filtroIndividuale')->getData()) as $fid) {
            if ($val_filtro_individuale_classe &&
                $em->getRepository('App:Alunno')->findOneBy(
                  ['id' => $fid, 'abilitato' => 1, 'classe' => $val_filtro_individuale_classe->getId()])) {
              $val_filtro_id[] = $fid;
            }
          }
          break;
      }
      // controllo errori
      if ($val_staff_filtro == 'N' && count($val_destinatari) == 0) {
        // errore: nessun destinatario
        $form->addError(new FormError($trans->trans('exception.destinatari_mancanti')));
      }
      if ($val_staff_filtro == 'S' && count($val_staff_sedi) == 0) {
        // errore: nessun destinatario
        $form->addError(new FormError($trans->trans('exception.destinatari_filtro_mancanti')));
      }
      if (count($val_destinatari) > 0  && $val_filtro != 'T' && count($val_filtro_id) == 0) {
        // errore: filtro vuoto
        $form->addError(new FormError($trans->trans('exception.destinatari_filtro_mancanti')));
      }
      if ($form->get('creaAnnotazione')->getData() && count($val_destinatari) == 0 && $val_staff_filtro != 'N') {
        // errore: annotazione per solo staff
        $form->addError(new FormError($trans->trans('exception.annotazione_solo_staff')));
      }
      if ($form->get('creaAnnotazione')->getData() && count($allegati) > 0) {
        // errore: annotazione con allegati
        $form->addError(new FormError($trans->trans('exception.annotazione_con_file')));
      }
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
        foreach ($session->get($var_sessione, []) as $f) {
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
        // destinatari
        $log_destinatari = $bac->modificaFiltriAvviso($avviso, $dest_filtro, $val_staff_filtro, $val_staff_sedi,
          $val_destinatari, $val_filtro, $val_filtro_id);
        // annotazione
        $log_annotazioni['delete'] = array();
        if ($id) {
          // cancella annotazioni
          foreach ($avviso->getAnnotazioni() as $a) {
            $log_annotazioni['delete'][] = $a->getId();
            $em->remove($a);
          }
          $avviso->setAnnotazioni(new ArrayCollection());
        }
        if ($form->get('creaAnnotazione')->getData()) {
          // crea nuove annotazioni
          $bac->creaAnnotazione($avviso, $val_filtro, $val_filtro_id, $val_filtro_individuale_classe);
        }
        // ok: memorizza dati
        $em->flush();
        // log azione e notifica
        $notifica = (new Notifica())
          ->setOggettoNome('Avviso')
          ->setOggettoId($avviso->getId());
        $em->persist($notifica);
        if (!$id) {
          // nuovo
          $notifica->setAzione('A');
          $dblogger->write($this->getUser(), $request->getClientIp(), 'AVVISI', 'Crea avviso generico', __METHOD__, array(
            'Avviso' => $avviso->getId(),
            'Sedi aggiunte' => implode(', ', $log_destinatari['sedi']['add']),
            'Classi aggiunte' => implode(', ', $log_destinatari['classi']['add']),
            'Utenti aggiunti' => implode(', ', array_map(function ($a) {
                return $a['genitore'].'->'.$a['alunno'];
              }, $log_destinatari['utenti']['add'])),
            'Annotazioni' => implode(', ', array_map(function ($a) {
                return $a->getId();
              }, $avviso->getAnnotazioni()->toArray())),
            ));
        } else {
          // modifica
          $notifica->setAzione('E');
          $dblogger->write($this->getUser(), $request->getClientIp(), 'AVVISI', 'Modifica avviso generico', __METHOD__, array(
            'Id' => $avviso->getId(),
            'Data' => $avviso_old->getData()->format('d/m/Y'),
            'Oggetto' => $avviso_old->getOggetto(),
            'Testo' => $avviso_old->getTesto(),
            'Allegati' => $avviso_old->getAllegati(),
            'Destinatari staff' => $avviso_old->getDestinatariStaff(),
            'Destinatari coordinatori' => $avviso_old->getDestinatariCoordinatori(),
            'Destinatari docenti' => $avviso_old->getDestinatariDocenti(),
            'Destinatari genitori' => $avviso_old->getDestinatariGenitori(),
            'Destinatari alunni' => $avviso_old->getDestinatariAlunni(),
            'Destinatari individuali' => $avviso_old->getDestinatariIndividuali(),
            'Sedi cancellate' => implode(', ', $log_destinatari['sedi']['delete']),
            'Sedi aggiunte' => implode(', ', $log_destinatari['sedi']['add']),
            'Classi cancellate' => implode(', ', $log_destinatari['classi']['delete']),
            'Classi aggiunte' => implode(', ', $log_destinatari['classi']['add']),
            'Utenti cancellati' => implode(', ', array_map(function ($a) {
                return $a['genitore'].'->'.$a['alunno'];
              }, $log_destinatari['utenti']['delete'])),
            'Utenti aggiunti' => implode(', ', array_map(function ($a) {
                return $a['genitore'].'->'.$a['alunno'];
              }, $log_destinatari['utenti']['add'])),
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
    ));
  }

  /**
   * Mostra i dettagli di un avviso
   *
   * @param EntityManagerInterface $em Gestore delle entità
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
  public function avvisiDettagliAction(EntityManagerInterface $em, BachecaUtil $bac, $id) {
    // inizializza
    $dati = null;
    // controllo avviso
    $avviso = $em->getRepository('App:Avviso')->find($id);
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
   * @param EntityManagerInterface $em Gestore delle entità
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
  public function avvisiDeleteAction(Request $request, EntityManagerInterface $em, LogHandler $dblogger, BachecaUtil $bac,
                                      RegistroUtil $reg, $tipo, $id) {
    $dir = $this->getParameter('dir_avvisi').'/';
    $fs = new FileSystem();
    // controllo avviso
    $avviso = $em->getRepository('App:Avviso')->findOneBy(['id' => $id, 'tipo' => $tipo]);
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
      $em->remove($a);
    }
    // cancella destinatari
    $log_destinatari = $bac->eliminaFiltriAvviso($avviso);
    // cancella avviso
    $avviso_id = $avviso->getId();
    $em->remove($avviso);
    // ok: memorizza dati
    $em->flush();
    // cancella allegati
    foreach ($avviso->getAllegati() as $a) {
      $f = new File($dir.$a);
      $fs->remove($f);
    }
    // log azione e notifica
    $notifica = (new Notifica())
      ->setOggettoNome('Avviso')
      ->setOggettoId($avviso_id)
      ->setAzione('D');
    $em->persist($notifica);
    $dblogger->write($this->getUser(), $request->getClientIp(), 'AVVISI', 'Cancella avviso', __METHOD__, array(
      'Id' => $avviso_id,
      'Tipo' => $avviso->getTipo(),
      'Data' => $avviso->getData()->format('d/m/Y'),
      'Ora' => ($avviso->getOra() ? $avviso->getOra()->format('H:i') : null),
      'Ora fine' => ($avviso->getOraFine() ? $avviso->getOraFine()->format('H:i') : null),
      'Oggetto' => $avviso->getOggetto(),
      'Testo' => $avviso->getTesto(),
      'Allegati' => $avviso->getAllegati(),
      'Destinatari staff' => $avviso->getDestinatariStaff(),
      'Destinatari coordinatori' => $avviso->getDestinatariCoordinatori(),
      'Destinatari docenti' => $avviso->getDestinatariDocenti(),
      'Destinatari genitori' => $avviso->getDestinatariGenitori(),
      'Destinatari alunni' => $avviso->getDestinatariAlunni(),
      'Destinatari individuali' => $avviso->getDestinatariIndividuali(),
      'Sedi cancellate' => implode(', ', $log_destinatari['sedi']),
      'Classi cancellate' => implode(', ', $log_destinatari['classi']),
      'Utenti cancellati' => implode(', ', array_map(function ($a) {
          return $a['genitore'].'->'.$a['alunno'];
        }, $log_destinatari['utenti'])),
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
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param BachecaUtil $bac Funzioni di utilità per la gestione della bacheca
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
  public function avvisiOrarioAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                      BachecaUtil $bac, $tipo, $pagina) {
    // inizializza variabili
    $dati = null;
    // recupera criteri dalla sessione
    $search = array();
    $search['docente'] = $session->get('/APP/ROUTE/staff_avvisi_orario_'.$tipo.'/docente', 0);
    $search['classe'] = $session->get('/APP/ROUTE/staff_avvisi_orario_'.$tipo.'/classe', 0);
    $docente = ($search['docente'] > 0 ? $em->getRepository('App:Docente')->find($search['docente']) : 0);
    $classe = ($search['classe'] > 0 ? $em->getRepository('App:Classe')->find($search['classe']) : 0);
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $session->get('/APP/ROUTE/staff_avvisi_orario_'.$tipo.'/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $session->set('/APP/ROUTE/staff_avvisi_orario_'.$tipo.'/pagina', $pagina);
    }
    // form di ricerca
    $limite = 25;
    $form = $this->container->get('form.factory')->createNamedBuilder('staff_avvisi_orario', FormType::class)
      ->add('docente', EntityType::class, array('label' => 'label.autore',
        'data' => $docente,
        'class' => 'App:Staff',
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
      ->add('classe', EntityType::class, array('label' => 'label.classe',
        'data' => $classe,
        'class' => 'App:Classe',
        'choice_label' => function ($obj) {
            return $obj->getAnno().'ª '.$obj->getSezione();
          },
        'placeholder' => 'label.qualsiasi_classe',
        'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('c')
              ->orderBy('c.anno,c.sezione', 'ASC');
          },
        'group_by' => function ($obj) {
            return $obj->getSede()->getCitta();
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
      $search['classe'] = (is_object($form->get('classe')->getData()) ? $form->get('classe')->getData()->getId() : 0);
      $pagina = 1;
      $session->set('/APP/ROUTE/staff_avvisi_orario_'.$tipo.'/docente', $search['docente']);
      $session->set('/APP/ROUTE/staff_avvisi_orario_'.$tipo.'/classe', $search['classe']);
      $session->set('/APP/ROUTE/staff_avvisi_orario_'.$tipo.'/pagina', $pagina);
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
   * @param EntityManagerInterface $em Gestore delle entità
   * @param TranslatorInterface $trans Gestore delle traduzioni
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
  public function avvisiOrarioEditAction(Request $request, EntityManagerInterface $em, TranslatorInterface $trans, BachecaUtil $bac,
                                          RegistroUtil $reg, LogHandler $dblogger, $tipo, $id) {
    // controlla azione
    if ($id > 0) {
      // azione edit
      $avviso = $em->getRepository('App:Avviso')->findOneBy(['id' => $id, 'tipo' => $tipo]);
      if (!$avviso) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $avviso_old = clone $avviso;
    } else {
      // azione add
      $avviso = (new Avviso())
        ->setTipo($tipo)
        ->setDestinatariStaff(false)
        ->setDestinatariCoordinatori(false)
        ->setDestinatariDocenti(false)
        ->setDestinatariGenitori(true)
        ->setDestinatariAlunni(true)
        ->setDestinatariIndividuali(false)
        ->setData(new \DateTime('tomorrow'))
        ->setOra(\DateTime::createFromFormat('H:i', ($tipo == 'E' ? '09:20' : '12:50')))
        ->setOggetto($trans->trans($tipo == 'E' ? 'message.avviso_entrata_oggetto' :
          'message.avviso_uscita_oggetto'))
        ->setTesto($trans->trans($tipo == 'E' ? 'message.avviso_entrata_testo' :
          'message.avviso_uscita_testo'));
      $em->persist($avviso);
    }
    // legge destinatari di filtri
    $dest_filtro = $bac->filtriAvviso($avviso);
    // imposta autore dell'avviso
    $avviso->setDocente($this->getUser());
    // imposta lettura non avvenuta (per avviso modificato)
    foreach ($dest_filtro['classi'] as $k=>$v) {
      $dest_filtro['classi'][$k]['lettoAlunni'] = null;
      $dest_filtro['classi'][$k]['lettoCoordinatore'] = null;
    }
    // opzione scelta filtro
    $scelta_filtro = 'C';
    $scelta_filtro_sedi = array();
    $scelta_filtro_classi = array();
    foreach (array_column($dest_filtro['classi'], 'classe') as $c) {
      $classe = $em->getRepository('App:Classe')->find($c);
      if ($classe) {
        $scelta_filtro_classi[] = $classe;
      }
    }
    // form di inserimento
    $form = $this->container->get('form.factory')->createNamedBuilder('avvisi_orario_edit', FormType::class, $avviso)
      ->add('data', DateType::class, array('label' => 'label.data',
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-picker'],
        'format' => 'dd/MM/yyyy',
        'required' => true))
      ->add('ora', TimeType::class, array('label' => ($tipo == 'E' ? 'label.ora_entrata' : 'label.ora_uscita'),
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-picker'],
        'required' => true))
      ->add('testo', TextareaType::class, array(
        'label' => 'label.testo',
        'attr' => array('rows' => '4'),
        'required' => true))
      ->add('filtro', ChoiceType::class, array('label' => false,
        'data' => $scelta_filtro,
        'choices' => ['label.filtro_tutti' => 'T', 'label.filtro_sede' => 'S', 'label.filtro_classe' => 'C'],
        'expanded' => false,
        'multiple' => false,
        'mapped' => false,
        'required' => true))
      ->add('filtroSedi', EntityType::class, array('label' => false,
        'data' => $scelta_filtro_sedi,
        'class' => 'App:Sede',
        'choice_label' => function ($obj) {
            return $obj->getCitta();
          },
        'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('s')
              ->orderBy('s.principale', 'DESC')
              ->addOrderBy('s.citta', 'ASC');
          },
        'expanded' => true,
        'multiple' => true,
        'choice_translation_domain' => false,
        'label_attr' => ['class' => 'gs-pt-0 checkbox-split-vertical'],
        'mapped' => false,
        'required' => false))
      ->add('filtroClassi', EntityType::class, array('label' => 'ok_false',
        'data' => $scelta_filtro_classi,
        'class' => 'App:Classe',
        'choice_label' => function ($obj) {
            return $obj->getAnno().'ª '.$obj->getSezione();
          },
        'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('c')
              ->orderBy('c.anno,c.sezione', 'ASC');
          },
        'group_by' => function ($obj) {
            return $obj->getSede()->getCitta();
          },
        'expanded' => true,
        'multiple' => true,
        'choice_translation_domain' => false,
        'label_attr' => ['class' => 'gs-pt-0 checkbox-grouped-4'],
        'mapped' => false,
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start']))
      ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end',
        'onclick' => "location.href='".$this->generateUrl('staff_avvisi_orario', ['tipo' => $tipo])."'"]))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // recupera dati
      $val_staff_filtro = 'N';
      $val_staff_sedi = [];
      $val_destinatari = ['G', 'A'];
      $val_filtro = $form->get('filtro')->getData();
      $val_filtro_id = [];
      $val_filtro_individuale_classe = 0;
      switch ($val_filtro) {
        case 'S':
          foreach ($form->get('filtroSedi')->getData() as $f) {
            $val_filtro_id[] = $f->getId();
          }
          break;
        case 'C':
          foreach ($form->get('filtroClassi')->getData() as $f) {
            $val_filtro_id[] = $f->getId();
          }
          break;
      }
      // controllo errori
      if ($val_filtro != 'T' && count($val_filtro_id) == 0) {
        // errore: filtro vuoto
        $form->addError(new FormError($trans->trans('exception.destinatari_filtro_mancanti')));
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
        // destinatari
        $log_destinatari = $bac->modificaFiltriAvviso($avviso, $dest_filtro, $val_staff_filtro, $val_staff_sedi,
          $val_destinatari, $val_filtro, $val_filtro_id);
        // annotazione
        $log_annotazioni['delete'] = array();
        if ($id) {
          // cancella annotazioni
          foreach ($avviso->getAnnotazioni() as $a) {
            $log_annotazioni['delete'][] = $a->getId();
            $em->remove($a);
          }
          $avviso->setAnnotazioni(new ArrayCollection());
        }
        // crea nuove annotazioni
        $bac->creaAnnotazione($avviso, $val_filtro, $val_filtro_id, $val_filtro_individuale_classe);
        // ok: memorizza dati
        $em->flush();
        // log azione e notifica
        $notifica = (new Notifica())
          ->setOggettoNome('Avviso')
          ->setOggettoId($avviso->getId());
        $em->persist($notifica);
        if (!$id) {
          // nuovo
          $notifica->setAzione('A');
          $dblogger->write($this->getUser(), $request->getClientIp(), 'AVVISI', 'Crea avviso '.($tipo == 'E' ? 'entrata' : 'uscita'), __METHOD__, array(
            'Avviso' => $avviso->getId(),
            'Classi aggiunte' => implode(', ', $log_destinatari['classi']['add']),
            'Annotazioni' => implode(', ', array_map(function ($a) {
                return $a->getId();
              }, $avviso->getAnnotazioni()->toArray())),
            ));
        } else {
          // modifica
          $notifica->setAzione('E');
          $dblogger->write($this->getUser(), $request->getClientIp(), 'AVVISI', 'Modifica avviso '.($tipo == 'E' ? 'entrata' : 'uscita'), __METHOD__, array(
            'Id' => $avviso->getId(),
            'Data' => $avviso_old->getData()->format('d/m/Y'),
            'Ora' => $avviso_old->getOra()->format('H:i'),
            'Testo' => $avviso_old->getTesto(),
            'Classi cancellate' => implode(', ', $log_destinatari['classi']['delete']),
            'Classi aggiunte' => implode(', ', $log_destinatari['classi']['add']),
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
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
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
  public function avvisiAttivitaAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                        BachecaUtil $bac, $pagina) {
    // inizializza variabili
    $dati = null;
    // recupera criteri dalla sessione
    $search = array();
    $search['docente'] = $session->get('/APP/ROUTE/staff_avvisi_attivita/docente', 0);
    $search['classe'] = $session->get('/APP/ROUTE/staff_avvisi_attivita/classe', 0);
    $docente = ($search['docente'] > 0 ? $em->getRepository('App:Docente')->find($search['docente']) : 0);
    $classe = ($search['classe'] > 0 ? $em->getRepository('App:Classe')->find($search['classe']) : 0);
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $session->get('/APP/ROUTE/staff_avvisi_attivita/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $session->set('/APP/ROUTE/staff_avvisi_attivita/pagina', $pagina);
    }
    // form di ricerca
    $limite = 25;
    $form = $this->container->get('form.factory')->createNamedBuilder('staff_avvisi_attivita', FormType::class)
      ->add('docente', EntityType::class, array('label' => 'label.autore',
        'data' => $docente,
        'class' => 'App:Staff',
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
      ->add('classe', EntityType::class, array('label' => 'label.classe',
        'data' => $classe,
        'class' => 'App:Classe',
        'choice_label' => function ($obj) {
            return $obj->getAnno().'ª '.$obj->getSezione();
          },
        'placeholder' => 'label.qualsiasi_classe',
        'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('c')
              ->orderBy('c.anno,c.sezione', 'ASC');
          },
        'group_by' => function ($obj) {
            return $obj->getSede()->getCitta();
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
      $search['classe'] = (is_object($form->get('classe')->getData()) ? $form->get('classe')->getData()->getId() : 0);
      $pagina = 1;
      $session->set('/APP/ROUTE/staff_avvisi_attivita/docente', $search['docente']);
      $session->set('/APP/ROUTE/staff_avvisi_attivita/classe', $search['classe']);
      $session->set('/APP/ROUTE/staff_avvisi_attivita/pagina', $pagina);
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
   * @param EntityManagerInterface $em Gestore delle entità
   * @param TranslatorInterface $trans Gestore delle traduzioni
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
  public function avvisiAttivitaEditAction(Request $request, EntityManagerInterface $em, TranslatorInterface $trans, BachecaUtil $bac,
                                            RegistroUtil $reg, LogHandler $dblogger, $id) {
    // controlla azione
    if ($id > 0) {
      // azione edit
      $avviso = $em->getRepository('App:Avviso')->findOneBy(['id' => $id, 'tipo' => 'A']);
      if (!$avviso) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $avviso_old = clone $avviso;
    } else {
      // azione add
      $avviso = (new Avviso())
        ->setTipo('A')
        ->setDestinatariStaff(false)
        ->setDestinatariCoordinatori(false)
        ->setDestinatariDocenti(true)
        ->setDestinatariGenitori(true)
        ->setDestinatariAlunni(true)
        ->setDestinatariIndividuali(false)
        ->setData(new \DateTime('tomorrow'))
        ->setOra(\DateTime::createFromFormat('H:i', '08:20'))
        ->setOraFine(\DateTime::createFromFormat('H:i', '13:50'))
        ->setOggetto($trans->trans('message.avviso_attivita_oggetto'))
        ->setTesto($trans->trans('message.avviso_attivita_testo'));
      $em->persist($avviso);
    }
    // legge destinatari di filtri
    $dest_filtro = $bac->filtriAvviso($avviso);
    // imposta autore dell'avviso
    $avviso->setDocente($this->getUser());
    // imposta lettura non avvenuta (per avviso modificato)
    foreach ($dest_filtro['classi'] as $k=>$v) {
      $dest_filtro['classi'][$k]['lettoAlunni'] = null;
      $dest_filtro['classi'][$k]['lettoCoordinatore'] = null;
    }
    // opzione scelta filtro
    $scelta_filtro = 'C';
    $scelta_filtro_sedi = array();
    $scelta_filtro_classi = array();
    foreach (array_column($dest_filtro['classi'], 'classe') as $c) {
      $classe = $em->getRepository('App:Classe')->find($c);
      if ($classe) {
        $scelta_filtro_classi[] = $classe;
      }
    }
    // form di inserimento
    $form = $this->container->get('form.factory')->createNamedBuilder('avvisi_attivita_edit', FormType::class, $avviso)
      ->add('data', DateType::class, array('label' => 'label.data_evento',
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-picker'],
        'format' => 'dd/MM/yyyy',
        'required' => true))
      ->add('ora', TimeType::class, array('label' => 'label.ora_inizio',
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-picker'],
        'required' => true))
      ->add('oraFine', TimeType::class, array('label' => 'label.ora_fine',
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-picker'],
        'required' => true))
      ->add('testo', TextareaType::class, array(
        'label' => 'label.testo',
        'attr' => array('rows' => '4'),
        'required' => true))
      ->add('filtro', ChoiceType::class, array('label' => false,
        'data' => $scelta_filtro,
        'choices' => ['label.filtro_tutti' => 'T', 'label.filtro_sede' => 'S', 'label.filtro_classe' => 'C'],
        'expanded' => false,
        'multiple' => false,
        'mapped' => false,
        'required' => true))
      ->add('filtroSedi', EntityType::class, array('label' => false,
        'data' => $scelta_filtro_sedi,
        'class' => 'App:Sede',
        'choice_label' => function ($obj) {
            return $obj->getCitta();
          },
        'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('s')
              ->orderBy('s.principale', 'DESC')
              ->addOrderBy('s.citta', 'ASC');
          },
        'expanded' => true,
        'multiple' => true,
        'choice_translation_domain' => false,
        'label_attr' => ['class' => 'gs-pt-0 checkbox-split-vertical'],
        'mapped' => false,
        'required' => false))
      ->add('filtroClassi', EntityType::class, array('label' => false,
        'data' => $scelta_filtro_classi,
        'class' => 'App:Classe',
        'choice_label' => function ($obj) {
            return $obj->getAnno().'ª '.$obj->getSezione();
          },
        'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('c')
              ->orderBy('c.anno,c.sezione', 'ASC');
          },
        'group_by' => function ($obj) {
            return $obj->getSede()->getCitta();
          },
        'expanded' => true,
        'multiple' => true,
        'choice_translation_domain' => false,
        'label_attr' => ['class' => 'gs-pt-0 checkbox-grouped-4'],
        'mapped' => false,
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start']))
      ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end',
        'onclick' => "location.href='".$this->generateUrl('staff_avvisi_attivita')."'"]))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // recupera dati
      $val_staff_filtro = 'N';
      $val_staff_sedi = [];
      $val_destinatari = ['D', 'G', 'A'];
      $val_filtro = $form->get('filtro')->getData();
      $val_filtro_id = [];
      $val_filtro_individuale_classe = 0;
      switch ($val_filtro) {
        case 'S':
          foreach ($form->get('filtroSedi')->getData() as $f) {
            $val_filtro_id[] = $f->getId();
          }
          break;
        case 'C':
          foreach ($form->get('filtroClassi')->getData() as $f) {
            $val_filtro_id[] = $f->getId();
          }
          break;
      }
      // controllo errori
      if ($val_filtro != 'T' && count($val_filtro_id) == 0) {
        // errore: filtro vuoto
        $form->addError(new FormError($trans->trans('exception.destinatari_filtro_mancanti')));
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
        // destinatari
        $log_destinatari = $bac->modificaFiltriAvviso($avviso, $dest_filtro, $val_staff_filtro, $val_staff_sedi,
          $val_destinatari, $val_filtro, $val_filtro_id);
        // annotazione
        $log_annotazioni['delete'] = array();
        if ($id) {
          // cancella annotazioni
          foreach ($avviso->getAnnotazioni() as $a) {
            $log_annotazioni['delete'][] = $a->getId();
            $em->remove($a);
          }
          $avviso->setAnnotazioni(new ArrayCollection());
        }
        // crea nuove annotazioni
        $bac->creaAnnotazione($avviso, $val_filtro, $val_filtro_id, $val_filtro_individuale_classe);
        // ok: memorizza dati
        $em->flush();
        // log azione e notifica
        $notifica = (new Notifica())
          ->setOggettoNome('Avviso')
          ->setOggettoId($avviso->getId());
        $em->persist($notifica);
        if (!$id) {
          // nuovo
          $notifica->setAzione('A');
          $dblogger->write($this->getUser(), $request->getClientIp(), 'AVVISI', 'Crea avviso attività', __METHOD__, array(
            'Avviso' => $avviso->getId(),
            'Classi aggiunte' => implode(', ', $log_destinatari['classi']['add']),
            'Annotazioni' => implode(', ', array_map(function ($a) {
                return $a->getId();
              }, $avviso->getAnnotazioni()->toArray())),
            ));
        } else {
          // modifica
          $notifica->setAzione('E');
          $dblogger->write($this->getUser(), $request->getClientIp(), 'AVVISI', 'Modifica avviso attività', __METHOD__, array(
            'Id' => $avviso->getId(),
            'Data' => $avviso_old->getData()->format('d/m/Y'),
            'Ora inizio' => $avviso_old->getOra()->format('H:i'),
            'Ora fine' => $avviso_old->getOraFine()->format('H:i'),
            'Testo' => $avviso_old->getTesto(),
            'Classi cancellate' => implode(', ', $log_destinatari['classi']['delete']),
            'Classi aggiunte' => implode(', ', $log_destinatari['classi']['add']),
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
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
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
  public function avvisiIndividualiAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                           BachecaUtil $bac, $pagina) {
    // inizializza variabili
    $dati = null;
    // recupera criteri dalla sessione
    $search = array();
    $search['docente'] = $session->get('/APP/ROUTE/staff_avvisi_individuali/docente', 0);
    $search['classe_individuale'] = $session->get('/APP/ROUTE/staff_avvisi_individuali/classe_individuale', 0);
    $docente = ($search['docente'] > 0 ? $em->getRepository('App:Docente')->find($search['docente']) : 0);
    $classe = ($search['classe_individuale'] > 0 ? $em->getRepository('App:Classe')->find($search['classe_individuale']) : 0);
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $session->get('/APP/ROUTE/staff_avvisi_individuali/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $session->set('/APP/ROUTE/staff_avvisi_individuali/pagina', $pagina);
    }
    // form di ricerca
    $limite = 25;
    $form = $this->container->get('form.factory')->createNamedBuilder('staff_avvisi_individuali', FormType::class)
      ->add('docente', EntityType::class, array('label' => 'label.autore',
        'data' => $docente,
        'class' => 'App:Staff',
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
      ->add('classe_individuale', EntityType::class, array('label' => 'label.classe',
        'data' => $classe,
        'class' => 'App:Classe',
        'choice_label' => function ($obj) {
            return $obj->getAnno().'ª '.$obj->getSezione();
          },
        'placeholder' => 'label.qualsiasi_classe',
        'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('c')
              ->orderBy('c.anno,c.sezione', 'ASC');
          },
        'group_by' => function ($obj) {
            return $obj->getSede()->getCitta();
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
      $search['classe_individuale'] = (is_object($form->get('classe_individuale')->getData()) ?
        $form->get('classe_individuale')->getData()->getId() : 0);
      $pagina = 1;
      $session->set('/APP/ROUTE/staff_avvisi_individuali/docente', $search['docente']);
      $session->set('/APP/ROUTE/staff_avvisi_individuali/classe_individuale', $search['classe_individuale']);
      $session->set('/APP/ROUTE/staff_avvisi_individuali/pagina', $pagina);
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
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param TranslatorInterface $trans Gestore delle traduzioni
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
  public function avvisiIndividualiEditAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                               TranslatorInterface $trans, BachecaUtil $bac, RegistroUtil $reg, LogHandler $dblogger, $id) {
    // controlla azione
    if ($id > 0) {
      // azione edit
      $avviso = $em->getRepository('App:Avviso')->findOneBy(['id' => $id, 'tipo' => 'I']);
      if (!$avviso) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $avviso_old = clone $avviso;
    } else {
      // azione add
      $docente = ($this->getUser()->getSesso() == 'M' ? ' prof. ' : 'la prof.ssa ').
        $this->getUser()->getNome().' '.$this->getUser()->getCognome();
      $avviso = (new Avviso())
        ->setTipo('I')
        ->setDestinatariStaff(false)
        ->setDestinatariCoordinatori(false)
        ->setDestinatariDocenti(false)
        ->setDestinatariGenitori(true)
        ->setDestinatariAlunni(false)
        ->setDestinatariIndividuali(true)
        ->setOggetto($trans->trans('message.avviso_individuale_oggetto', ['docente' => $docente]))
        ->setData(new \DateTime('today'));
      $em->persist($avviso);
    }
    // legge destinatari di filtri
    $dest_filtro = $bac->filtriAvviso($avviso);
    // imposta autore dell'avviso
    $avviso->setDocente($this->getUser());
    // imposta lettura non avvenuta (per avviso modificato)
    foreach ($dest_filtro['utenti'] as $k=>$v) {
      $dest_filtro['genitori'][$k]['letto'] = null;
    }
    // opzione scelta filtro
    $scelta_filtro = 'I';
    $scelta_filtro_individuale = array();
    $scelta_filtro_individuale_classe = null;
    foreach (array_column($dest_filtro['utenti'], 'alunno') as $a) {
      $alunno = $em->getRepository('App:Alunno')->find($a);
      if ($alunno) {
        $scelta_filtro_individuale[] = $alunno->getId();
        $scelta_filtro_individuale_classe = $alunno->getClasse();
      }
    }
    // form di inserimento
    $form = $this->container->get('form.factory')->createNamedBuilder('avvisi_individuali_edit', FormType::class, $avviso)
      ->add('testo', TextareaType::class, array(
        'label' => 'label.testo',
        'attr' => array('rows' => '4'),
        'required' => true))
      ->add('filtroIndividualeClasse', EntityType::class, array('label' => false,
        'data' => $scelta_filtro_individuale_classe,
        'class' => 'App:Classe',
        'choice_label' => function ($obj) {
            return $obj->getAnno().'ª '.$obj->getSezione().' - '.$obj->getCorso()->getNomeBreve();
          },
        'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('c')
              ->orderBy('c.anno,c.sezione', 'ASC');
          },
        'group_by' => function ($obj) {
            return $obj->getSede()->getCitta();
          },
        'expanded' => false,
        'multiple' => false,
        'placeholder' => 'label.scegli_classe',
        'choice_translation_domain' => false,
        'attr' => ['style' => 'width:auto'],
        'label_attr' => ['class' => 'gs-pt-0 checkbox-split-vertical'],
        'mapped' => false,
        'required' => false))
      ->add('filtroIndividuale',  HiddenType::class, array('label' => false,
        'data' => implode(',', $scelta_filtro_individuale),
        'mapped' => false,
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start']))
      ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end',
        'onclick' => "location.href='".$this->generateUrl('staff_avvisi_individuali')."'"]))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // recupera dati
      $val_staff_filtro = 'N';
      $val_staff_sedi = [];
      $val_destinatari = ['G', 'I'];
      $val_filtro = 'I';
      $val_filtro_id = [];
      $val_filtro_individuale_classe = $form->get('filtroIndividualeClasse')->getData();
      foreach (explode(',', $form->get('filtroIndividuale')->getData()) as $fid) {
        if ($val_filtro_individuale_classe &&
            $em->getRepository('App:Alunno')->findOneBy(
              ['id' => $fid, 'abilitato' => 1, 'classe' => $val_filtro_individuale_classe->getId()])) {
          $val_filtro_id[] = $fid;
        }
      }
      // controllo errori
      if (count($val_filtro_id) == 0) {
        // errore: filtro vuoto
        $form->addError(new FormError($trans->trans('exception.destinatari_filtro_mancanti')));
      }
      // controllo permessi
      if (!$bac->azioneAvviso(($id > 0 ? 'edit' : 'add'), $avviso->getData(), $this->getUser(), ($id > 0 ? $avviso : null))) {
        // errore: avviso non permesso
        $form->addError(new FormError($trans->trans('exception.avviso_non_permesso')));
      }
      // modifica dati
      if ($form->isValid()) {
        // destinatari
        $log_destinatari = $bac->modificaFiltriAvviso($avviso, $dest_filtro, $val_staff_filtro, $val_staff_sedi,
          $val_destinatari, $val_filtro, $val_filtro_id);
        // ok: memorizza dati
        $em->flush();
        // log azione
        if (!$id) {
          // nuovo
          $dblogger->write($this->getUser(), $request->getClientIp(), 'AVVISI', 'Crea avviso individuale', __METHOD__, array(
            'Avviso' => $avviso->getId(),
            'Utenti aggiunti' => implode(', ', array_map(function ($a) {
                return $a['genitore'].'->'.$a['alunno'];
              }, $log_destinatari['utenti']['add'])),
            ));
        } else {
          // modifica
          $dblogger->write($this->getUser(), $request->getClientIp(), 'AVVISI', 'Modifica avviso individuale', __METHOD__, array(
            'Id' => $avviso->getId(),
            'Testo' => $avviso_old->getTesto(),
            'Utenti cancellati' => implode(', ', array_map(function ($a) {
                return $a['genitore'].'->'.$a['alunno'];
              }, $log_destinatari['utenti']['delete'])),
            'Utenti aggiunti' => implode(', ', array_map(function ($a) {
                return $a['genitore'].'->'.$a['alunno'];
              }, $log_destinatari['utenti']['add'])),
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
    ));
  }

  /**
   * Restituisce gli alunni della classe indicata
   *
   * @param EntityManagerInterface $em Gestore delle entità
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
  public function classeAjaxAction(EntityManagerInterface $em, $id) {
    $alunni = $em->getRepository('App:Alunno')->createQueryBuilder('a')
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
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
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
  public function studentiAutorizzaAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                           RegistroUtil $reg, StaffUtil $staff, $data, $pagina) {
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
      if ($session->get('/APP/ROUTE/staff_studenti_autorizza/data')) {
        // recupera data da sessione
        $data_obj = \DateTime::createFromFormat('Y-m-d', $session->get('/APP/ROUTE/staff_studenti_autorizza/data'));
      } else {
        // imposta data odierna
        $data_obj = new \DateTime();
      }
    } else {
      // imposta data indicata e la memorizza in sessione
      $data_obj = \DateTime::createFromFormat('Y-m-d', $data);
      $session->set('/APP/ROUTE/staff_studenti_autorizza/data', $data);
    }
    // data in formato stringa
    $formatter = new \IntlDateFormatter('it_IT', \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $info['data_label'] =  $formatter->format($data_obj);
    // data prec/succ
    $data_succ = (clone $data_obj);
    $data_succ = $em->getRepository('App:Festivita')->giornoSuccessivo($data_succ);
    $data_prec = (clone $data_obj);
    $data_prec = $em->getRepository('App:Festivita')->giornoPrecedente($data_prec);
    // recupera criteri dalla sessione
    $search = array();
    $search['nome'] = $session->get('/APP/ROUTE/staff_studenti_autorizza/nome', '');
    $search['cognome'] = $session->get('/APP/ROUTE/staff_studenti_autorizza/cognome', '');
    $search['classe'] = $session->get('/APP/ROUTE/staff_studenti_autorizza/classe', 0);
    $classe = ($search['classe'] > 0 ? $em->getRepository('App:Classe')->find($search['classe']) : 0);
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $session->get('/APP/ROUTE/staff_studenti_autorizza/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $session->set('/APP/ROUTE/staff_studenti_autorizza/pagina', $pagina);
    }
    // legge sede
    $sede = $this->getUser()->getSede();
    // form di ricerca
    $limite = 25;
    if ($sede) {
      // limita a classi di sede
      $classi = $em->getRepository('App:Classe')->findBy(['sede' => $sede], ['anno' =>'ASC', 'sezione' =>'ASC']);
    } else {
      // tutte le classi
      $classi = $em->getRepository('App:Classe')->findBy([], ['anno' =>'ASC', 'sezione' =>'ASC']);
    }
    $form = $this->container->get('form.factory')->createNamedBuilder('staff_studenti_autorizza', FormType::class)
      ->setAction($this->generateUrl('staff_studenti_autorizza', ['data' => $data]))
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
      ->add('classe', ChoiceType::class, array('label' => 'label.classe',
        'data' => $classe,
        'choices' => $classi,
        'choice_label' => function ($obj) {
            return $obj->getAnno().'ª '.$obj->getSezione().' - '.$obj->getCorso()->getNomeBreve();
          },
        'choice_value' => function ($obj) {
            return (is_object($obj)  ? $obj->getId() : $obj);
          },
        'group_by' => function ($obj) {
            return $obj->getSede()->getCitta();
          },
        'placeholder' => 'label.qualsiasi_classe',
        'choice_translation_domain' => false,
        'label_attr' => ['class' => 'sr-only'],
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.search'))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $search['nome'] = trim($form->get('nome')->getData());
      $search['cognome'] = trim($form->get('cognome')->getData());
      $search['classe'] = (is_object($form->get('classe')->getData()) ? $form->get('classe')->getData()->getId() : 0);
      $pagina = 1;
      $session->set('/APP/ROUTE/staff_studenti_autorizza/nome', $search['nome']);
      $session->set('/APP/ROUTE/staff_studenti_autorizza/cognome', $search['cognome']);
      $session->set('/APP/ROUTE/staff_studenti_autorizza/classe', $search['classe']);
      $session->set('/APP/ROUTE/staff_studenti_autorizza/pagina', $pagina);
    }
    // recupera periodo
    $info['periodo'] = $reg->periodo($data_obj);
    // recupera festivi per calendario
    $lista_festivi = $reg->listaFestivi(null);
    // controllo data
    $errore = $reg->controlloData($data_obj, null);
    if (!$errore) {
      // non festivo: recupera dati
      $lista = $em->getRepository('App:Alunno')->findClassEnabled($sede, $search, $pagina, $limite);
      $max_pagine = ceil($lista->count() / $limite);
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
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
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
  public function studentiAutorizzaEntrataAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                                  RegistroUtil $reg, TranslatorInterface $trans, LogHandler $dblogger,
                                                  $data, $classe, $alunno) {
    // inizializza
    $label = array();
    // controlla classe
    $classe = $em->getRepository('App:Classe')->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla alunno
    $alunno = $em->getRepository('App:Alunno')->findOneBy(['id' => $alunno, 'abilitato' => 1, 'classe' => $classe]);
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
    $entrata = $em->getRepository('App:Entrata')->findOneBy(['alunno' => $alunno, 'data' => $data_obj]);
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
      $ora = \DateTime::createFromFormat('H:i:s', $orario[0]['inizio']);
      $ora->modify('+60 minutes');
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
      $em->persist($entrata);
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
    $form = $this->createForm(EntrataType::class, $entrata, array('formMode' => 'staff'));
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
          $em->remove($entrata);
        } else {
          // controlla ritardo breve
          $inizio = \DateTime::createFromFormat('Y-m-d H:i:s', '1970-01-01 '.$orario[0]['inizio']);
          $inizio->modify('+' . $session->get('/CONFIG/SCUOLA/ritardo_breve', 0) . 'minutes');
          if ($form->get('ora')->getData() <= $inizio) {
            // ritardo breve: giustificazione automatica (non imposta docente)
            $entrata
              ->setRitardoBreve(true)
              ->setGiustificato($data_obj)
              ->setDocenteGiustifica(null)
              ->setValido(false);
          }
          // controlla se risulta assente
          $assenza = $em->getRepository('App:Assenza')->findOneBy(['alunno' => $alunno, 'data' => $data_obj]);
          if ($assenza) {
            // cancella assenza
            $id_assenza = $assenza->getId();
            $em->remove($assenza);
          }
        }
        // ok: memorizza dati
        $em->flush();
        // ricalcola ore assenze
        $reg->ricalcolaOreAlunno($data_obj, $alunno);
        // log azione
        if (isset($entrata_old) && isset($request->request->get('entrata')['delete'])) {
          // log cancella
          $dblogger->write($this->getUser(), $request->getClientIp(), 'ASSENZE', 'Cancella entrata', __METHOD__, array(
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
          $dblogger->write($this->getUser(), $request->getClientIp(), 'ASSENZE', 'Modifica entrata', __METHOD__, array(
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
          $dblogger->write($this->getUser(), $request->getClientIp(), 'ASSENZE', 'Crea entrata', __METHOD__, array(
            'Entrata' => $entrata->getId()
            ));
        }
        if (isset($id_assenza)) {
          // log cancella assenza
          $dblogger->write($this->getUser(), $request->getClientIp(), 'ASSENZE', 'Cancella assenza', __METHOD__, array(
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
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
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
  public function studentiAutorizzaUscitaAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                                 RegistroUtil $reg, TranslatorInterface $trans, LogHandler $dblogger,
                                                 $data, $classe, $alunno) {
    // inizializza
    $label = array();
    // controlla classe
    $classe = $em->getRepository('App:Classe')->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla alunno
    $alunno = $em->getRepository('App:Alunno')->findOneBy(['id' => $alunno, 'abilitato' => 1, 'classe' => $classe]);
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
    // controlla uscita
    $uscita = $em->getRepository('App:Uscita')->findOneBy(['alunno' => $alunno, 'data' => $data_obj]);
    if ($uscita) {
      // edit
      $uscita_old = clone $uscita;
      $uscita->setDocente($this->getUser());
    } else {
      // nuovo
      $ora = new \DateTime();
      if ($data != $ora->format('Y-m-d') || $ora->format('H:i:00') < $orario[0]['inizio'] ||
          $ora->format('H:i:00') > $orario[count($orario) - 1]['fine']) {
        // data non odierna o ora attuale fuori da orario
        $ora = \DateTime::createFromFormat('H:i:s', $orario[count($orario) - 1]['fine']);
        $ora->modify('-60 minutes');
      }
      $nota = $trans->trans('message.autorizza_uscita', [
        'sex' => ($alunno->getSesso() == 'M' ? 'o' : 'a'),
        'alunno' => $alunno->getCognome().' '.$alunno->getNome()]);
      $uscita = (new Uscita())
        ->setData($data_obj)
        ->setOra($ora)
        ->setAlunno($alunno)
        ->setNote($nota)
        ->setValido(true)
        ->setDocente($this->getUser());
      $em->persist($uscita);
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
    $form = $this->createForm(UscitaType::class, $uscita, array('formMode' => 'staff'));
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
          $em->remove($uscita);
        } else {
          // controlla se risulta assente
          $assenza = $em->getRepository('App:Assenza')->findOneBy(['alunno' => $alunno, 'data' => $data_obj]);
          if ($assenza) {
            // cancella assenza
            $id_assenza = $assenza->getId();
            $em->remove($assenza);
          }
        }
        // ok: memorizza dati
        $em->flush();
        // ricalcola ore assenze
        $reg->ricalcolaOreAlunno($data_obj, $alunno);
        // log azione
        if (isset($uscita_old) && isset($request->request->get('uscita')['delete'])) {
          // cancella
          $dblogger->write($this->getUser(), $request->getClientIp(), 'ASSENZE', 'Cancella uscita', __METHOD__, array(
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
          $dblogger->write($this->getUser(), $request->getClientIp(), 'ASSENZE', 'Modifica uscita', __METHOD__, array(
            'Uscita' => $uscita->getId(),
            'Ora' => $uscita_old->getOra()->format('H:i'),
            'Note' => $uscita_old->getNote(),
            'Valido' => $uscita_old->getValido(),
            'Docente' => $uscita_old->getDocente()->getId()
            ));
        } else {
          // nuovo
          $dblogger->write($this->getUser(), $request->getClientIp(), 'ASSENZE', 'Crea uscita', __METHOD__, array(
            'Uscita' => $uscita->getId()
            ));
        }
        if (isset($id_assenza)) {
          // cancella assenza
          $dblogger->write($this->getUser(), $request->getClientIp(), 'ASSENZE', 'Cancella assenza', __METHOD__, array(
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
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
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
  public function studentiDerogheAction(Request $request, EntityManagerInterface $em, SessionInterface $session, $pagina) {
    // recupera criteri dalla sessione
    $search = array();
    $search['nome'] = $session->get('/APP/ROUTE/staff_studenti_deroghe/nome', '');
    $search['cognome'] = $session->get('/APP/ROUTE/staff_studenti_deroghe/cognome', '');
    $search['classe'] = $session->get('/APP/ROUTE/staff_studenti_deroghe/classe', 0);
    $classe = ($search['classe'] > 0 ? $em->getRepository('App:Classe')->find($search['classe']) : 0);
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $session->get('/APP/ROUTE/staff_studenti_deroghe/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $session->set('/APP/ROUTE/staff_studenti_deroghe/pagina', $pagina);
    }
    // legge sede
    $sede = $this->getUser()->getSede();
    // form di ricerca
    $limite = 25;
    if ($sede) {
      // limita a classi di sede
      $classi = $em->getRepository('App:Classe')->findBy(['sede' => $sede], ['anno' =>'ASC', 'sezione' =>'ASC']);
    } else {
      // tutte le classi
      $classi = $em->getRepository('App:Classe')->findBy([], ['anno' =>'ASC', 'sezione' =>'ASC']);
    }
    $form = $this->container->get('form.factory')->createNamedBuilder('staff_studenti_deroghe', FormType::class)
      ->setAction($this->generateUrl('staff_studenti_deroghe'))
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
      ->add('classe', ChoiceType::class, array('label' => 'label.classe',
        'data' => $classe,
        'choices' => $classi,
        'choice_label' => function ($obj) {
            return $obj->getAnno().'ª '.$obj->getSezione().' - '.$obj->getCorso()->getNomeBreve();
          },
        'choice_value' => function ($obj) {
            return (is_object($obj)  ? $obj->getId() : $obj);
          },
        'group_by' => function ($obj) {
            return $obj->getSede()->getCitta();
          },
        'placeholder' => 'label.qualsiasi_classe',
        'choice_translation_domain' => false,
        'label_attr' => ['class' => 'sr-only'],
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.search'))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $search['nome'] = trim($form->get('nome')->getData());
      $search['cognome'] = trim($form->get('cognome')->getData());
      $search['classe'] = (is_object($form->get('classe')->getData()) ? $form->get('classe')->getData()->getId() : 0);
      $pagina = 1;
      $session->set('/APP/ROUTE/staff_studenti_deroghe/nome', $search['nome']);
      $session->set('/APP/ROUTE/staff_studenti_deroghe/cognome', $search['cognome']);
      $session->set('/APP/ROUTE/staff_studenti_deroghe/classe', $search['classe']);
      $session->set('/APP/ROUTE/staff_studenti_deroghe/pagina', $pagina);
    }
    // lista alunni
    $lista = $em->getRepository('App:Alunno')->findClassEnabled($sede, $search, $pagina, $limite);
    // mostra la pagina di risposta
    return $this->render('ruolo_staff/studenti_deroghe.html.twig', array(
      'pagina_titolo' => 'page.staff_deroghe',
      'form' => $form->createView(),
      'form_help' => null,
      'form_success' => null,
      'lista' => $lista,
      'page' => $pagina,
      'maxPages' => ceil($lista->count() / $limite),
    ));
  }

  /**
   * Modifica delle deroghe e annotazioni di un alunno
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
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
  public function studentiDerogheEditAction(Request $request, EntityManagerInterface $em, LogHandler $dblogger, $alunno) {
    // inizializza
    $label = null;
    // controlla alunno
    $alunno = $em->getRepository('App:Alunno')->findOneBy(['id' => $alunno, 'abilitato' => 1]);
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
      ->add('autorizzaEntrata', TextareaType::class, array('label' => 'label.autorizza_entrata',
        'required' => false))
      ->add('autorizzaUscita', TextareaType::class, array('label' => 'label.autorizza_uscita',
        'required' => false))
      ->add('note', TextareaType::class, array('label' => 'label.note',
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
        $em->flush();
        // log azione
        $dblogger->write($alunno, $request->getClientIp(), 'ALUNNO', 'Modifica deroghe', __METHOD__, array(
          'Username esecutore' => $this->getUser()->getUsername(),
          'Ruolo esecutore' => $this->getUser()->getRoles()[0],
          'ID esecutore' => $this->getUser()->getId(),
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
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
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
  public function studentiSituazioneAction(Request $request, EntityManagerInterface $em, SessionInterface $session, $pagina) {
    // recupera criteri dalla sessione
    $search = array();
    $search['nome'] = $session->get('/APP/ROUTE/staff_studenti_situazione/nome', '');
    $search['cognome'] = $session->get('/APP/ROUTE/staff_studenti_situazione/cognome', '');
    $search['classe'] = $session->get('/APP/ROUTE/staff_studenti_situazione/classe', 0);
    $classe = ($search['classe'] > 0 ? $em->getRepository('App:Classe')->find($search['classe']) : 0);
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $session->get('/APP/ROUTE/staff_studenti_situazione/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $session->set('/APP/ROUTE/staff_studenti_situazione/pagina', $pagina);
    }
    // legge sede
    $sede = $this->getUser()->getSede();
    // form di ricerca
    $limite = 25;
    if ($sede) {
      // limita a classi di sede
      $classi = $em->getRepository('App:Classe')->findBy(['sede' => $sede], ['anno' =>'ASC', 'sezione' =>'ASC']);
    } else {
      // tutte le classi
      $classi = $em->getRepository('App:Classe')->findBy([], ['anno' =>'ASC', 'sezione' =>'ASC']);
    }
    $form = $this->container->get('form.factory')->createNamedBuilder('staff_studenti_situazione', FormType::class)
      ->setAction($this->generateUrl('staff_studenti_situazione'))
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
      ->add('classe', ChoiceType::class, array('label' => 'label.classe',
        'data' => $classe,
        'choices' => $classi,
        'choice_label' => function ($obj) {
            return $obj->getAnno().'ª '.$obj->getSezione().' - '.$obj->getCorso()->getNomeBreve();
          },
        'choice_value' => function ($obj) {
            return (is_object($obj)  ? $obj->getId() : $obj);
          },
        'group_by' => function ($obj) {
            return $obj->getSede()->getCitta();
          },
        'placeholder' => 'label.qualsiasi_classe',
        'choice_translation_domain' => false,
        'label_attr' => ['class' => 'sr-only'],
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.search'))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $search['nome'] = trim($form->get('nome')->getData());
      $search['cognome'] = trim($form->get('cognome')->getData());
      $search['classe'] = (is_object($form->get('classe')->getData()) ? $form->get('classe')->getData()->getId() : 0);
      $pagina = 1;
      $session->set('/APP/ROUTE/staff_studenti_situazione/nome', $search['nome']);
      $session->set('/APP/ROUTE/staff_studenti_situazione/cognome', $search['cognome']);
      $session->set('/APP/ROUTE/staff_studenti_situazione/classe', $search['classe']);
      $session->set('/APP/ROUTE/staff_studenti_situazione/pagina', $pagina);
    }
    // lista alunni
    $lista = $em->getRepository('App:Alunno')->findClassEnabled($sede, $search, $pagina, $limite);
    // mostra la pagina di risposta
    return $this->render('ruolo_staff/studenti_situazione.html.twig', array(
      'pagina_titolo' => 'page.staff_situazione',
      'form' => $form->createView(),
      'form_help' => null,
      'form_success' => null,
      'lista' => $lista,
      'page' => $pagina,
      'maxPages' => ceil($lista->count() / $limite),
    ));
  }

  /**
   * Visualizza le ore dei colloqui individuali dei docenti
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param int $pagina Numero di pagina per la lista dei alunni
   *
   * @return Response Pagina di risposta
   *
   * @Route("/staff/docenti/colloqui/{pagina}", name="staff_docenti_colloqui",
   *    requirements={"pagina": "\d+"},
   *    defaults={"pagina": 0},
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_STAFF")
   */
  public function docentiColloquiAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                         $pagina) {
    $giorni_settimana = ['domenica', 'lunedì', 'martedì', 'mercoledì', 'giovedì', 'venerdì', 'sabato'];
    // recupera criteri dalla sessione
    $search = array();
    $search['docente'] = $session->get('/APP/ROUTE/staff_docenti_colloqui/docente', 0);
    $docente = ($search['docente'] > 0 ? $em->getRepository('App:Docente')->find($search['docente']) : 0);
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $session->get('/APP/ROUTE/staff_docenti_colloqui/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $session->set('/APP/ROUTE/staff_docenti_colloqui/pagina', $pagina);
    }
    // form di ricerca
    $limite = 25;
    $form = $this->container->get('form.factory')->createNamedBuilder('staff_docenti_colloqui', FormType::class)
      ->setAction($this->generateUrl('staff_docenti_colloqui'))
      ->add('docente', EntityType::class, array('label' => 'label.docente',
        'data' => $docente,
        'class' => 'App:Docente',
        'choice_label' => function ($obj) {
            return $obj->getCognome().' '.$obj->getNome();
          },
        'placeholder' => 'label.docente',
        'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('d')
              ->where('d NOT INSTANCE OF App:Preside AND d.abilitato=1')
              ->orderBy('d.cognome,d.nome', 'ASC');
          },
        'label_attr' => ['class' => 'sr-only'],
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.search'))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $search['docente'] = ($form->get('docente')->getData() ? $form->get('docente')->getData()->getId() : 0);
      $pagina = 1;
      $session->set('/APP/ROUTE/staff_docenti_colloqui/docente', $search['docente']);
      $session->set('/APP/ROUTE/staff_docenti_colloqui/pagina', $pagina);
    }
    // lista colloqui
    $lista = $em->getRepository('App:Colloquio')->findAll($search, $pagina, $limite);
    // mostra la pagina di risposta
    return $this->render('ruolo_staff/docenti_colloqui.html.twig', array(
      'pagina_titolo' => 'page.staff_colloqui',
      'form' => $form->createView(),
      'form_help' => null,
      'form_success' => null,
      'lista' => $lista,
      'page' => $pagina,
      'maxPages' => ceil($lista->count() / $limite),
      'giorni_settimana' => $giorni_settimana,
    ));
  }

  /**
   * Mostra le statistiche sulle ore di lezione svolte dai docenti
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
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
  public function docentiStatisticheAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                            TranslatorInterface $trans, StaffUtil $staff, PdfManager $pdf, $pagina) {
    // recupera criteri dalla sessione
    $creaPdf = false;
    $search = array();
    $search['docente'] = $session->get('/APP/ROUTE/staff_docenti_statistiche/docente', null);
    $search['inizio'] = $session->get('/APP/ROUTE/staff_docenti_statistiche/inizio', null);
    $search['fine'] = $session->get('/APP/ROUTE/staff_docenti_statistiche/fine', null);
    $docente = ($search['docente'] > 0 ? $em->getRepository('App:Docente')->find($search['docente']) :
      ($search['docente'] < 0 ? -1 : null));
    $inizio = ($search['inizio'] ? \DateTime::createFromFormat('Y-m-d', $search['inizio']) : new \DateTime());
    $fine = ($search['fine'] ? \DateTime::createFromFormat('Y-m-d', $search['fine']) : new \DateTime());
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $session->get('/APP/ROUTE/staff_docenti_statistiche/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $session->set('/APP/ROUTE/staff_docenti_statistiche/pagina', $pagina);
    }
    // form di ricerca
    $limite = 25;
    $docenti = $em->getRepository('App:Docente')->createQueryBuilder('d')
      ->where('d NOT INSTANCE OF App:Preside AND d.abilitato=:abilitato')
      ->orderBy('d.cognome,d.nome', 'ASC')
      ->setParameters(['abilitato' => 1])
      ->getQuery()
      ->getResult();
    $form = $this->container->get('form.factory')->createNamedBuilder('staff_docenti_statistiche', FormType::class)
      ->setAction($this->generateUrl('staff_docenti_statistiche'))
      ->add('docente', ChoiceType::class, array('label' => 'label.docente',
        'data' => $docente,
        'choices' => array_merge(['label.tutti_docenti' => -1], $docenti),
        'choice_label' => function ($obj, $val) {
            return (is_object($obj) ? $obj->getCognome().' '.$obj->getNome() :
              $trans->trans('label.tutti_docenti'));
          },
        'choice_value' => function ($obj) {
            return (is_object($obj) ? $obj->getId() : $obj);
          },
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
      $docente = ($search['docente'] > 0 ? $em->getRepository('App:Docente')->find($search['docente']) :
        ($search['docente'] < 0 ? -1 : null));
      $inizio = ($form->get('inizio')->getData() ? $form->get('inizio')->getData() : new \DateTime());
      $fine = ($form->get('fine')->getData() ? $form->get('fine')->getData() : new \DateTime());
      $pagina = 1;
      $session->set('/APP/ROUTE/staff_docenti_statistiche/docente', $search['docente']);
      $session->set('/APP/ROUTE/staff_docenti_statistiche/inizio', $search['inizio']);
      $session->set('/APP/ROUTE/staff_docenti_statistiche/fine', $search['fine']);
      $session->set('/APP/ROUTE/staff_docenti_statistiche/pagina', $pagina);
      $creaPdf = ($form->get('print')->isClicked());
    }
    // statistiche
    if ($creaPdf) {
      // crea PDF
      $lista = $staff->statisticheStampa($docente, $inizio, $fine);
      $pdf->configure($session->get('/CONFIG/SCUOLA/intestazione_istituto'),
        'Statistiche sulle ore di lezione dei docenti');
      $pdf->getHandler()->SetAutoPageBreak(true, 15);
      $pdf->getHandler()->SetFooterMargin(15);
      $pdf->getHandler()->setFooterFont(Array('helvetica', '', 9));
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
   * Mostra i programmi svolti inseriti dai docenti
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param StaffUtil $staff Funzioni di utilità per lo staff
   * @param int $pagina Numero di pagina per la lista visualizzata
   *
   * @return Response Pagina di risposta
   *
   * @Route("/staff/documenti/programmi/{pagina}", name="staff_documenti_programmi",
   *    requirements={"pagina": "\d+"},
   *    defaults={"pagina": 0},
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_STAFF")
   */
  public function documentiProgrammiAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                            StaffUtil $staff, $pagina) {
    // inizializza variabili
    $dati = null;
    // recupera criteri dalla sessione
    $search = array();
    $search['docente'] = $session->get('/APP/ROUTE/staff_documenti_programmi/docente', 0);
    $search['classe'] = $session->get('/APP/ROUTE/staff_documenti_programmi/classe', 0);
    $docente = ($search['docente'] > 0 ? $em->getRepository('App:Docente')->find($search['docente']) : 0);
    $classe = ($search['classe'] > 0 ? $em->getRepository('App:Classe')->find($search['classe']) : 0);
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $session->get('/APP/ROUTE/staff_documenti_programmi/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $session->set('/APP/ROUTE/staff_documenti_programmi/pagina', $pagina);
    }
    // form di ricerca
    $limite = 25;
    $form = $this->container->get('form.factory')->createNamedBuilder('staff_documenti_programmi', FormType::class)
      ->add('docente', EntityType::class, array('label' => 'label.autore',
        'data' => $docente,
        'class' => 'App:Docente',
        'choice_label' => function ($obj) {
            return $obj->getCognome().' '.$obj->getNome();
          },
        'placeholder' => 'label.tutti_docenti',
        'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('d')
              ->where('d NOT INSTANCE OF App:Preside AND d.abilitato=:abilitato')
              ->orderBy('d.cognome,d.nome', 'ASC')
              ->setParameters(['abilitato' => 1]);
          },
        'label_attr' => ['class' => 'sr-only'],
        'choice_attr' => function($val, $key, $index) {
            return ['class' => 'gs-no-placeholder'];
          },
        'attr' => ['class' => 'gs-placeholder'],
        'required' => false))
      ->add('classe', EntityType::class, array('label' => 'label.classe',
        'data' => $classe,
        'class' => 'App:Classe',
        'choice_label' => function ($obj) {
            return $obj->getAnno().'ª '.$obj->getSezione();
          },
        'placeholder' => 'label.qualsiasi_classe',
        'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('c')
              ->where('c.anno!=5')
              ->orderBy('c.anno,c.sezione', 'ASC');
          },
        'group_by' => function ($obj) {
            return $obj->getSede()->getCitta();
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
      $search['classe'] = (is_object($form->get('classe')->getData()) ? $form->get('classe')->getData()->getId() : 0);
      $pagina = 1;
      $session->set('/APP/ROUTE/staff_documenti_programmi/docente', $search['docente']);
      $session->set('/APP/ROUTE/staff_documenti_programmi/classe', $search['classe']);
      $session->set('/APP/ROUTE/staff_documenti_programmi/pagina', $pagina);
    }
    // recupera dati
    $dati = $staff->programmi($search, $pagina, $limite);
    // mostra la pagina di risposta
    return $this->render('ruolo_staff/documenti_programmi.html.twig', array(
      'pagina_titolo' => 'page.staff_programmi',
      'form' => $form->createView(),
      'form_help' => null,
      'form_success' => null,
      'page' => $pagina,
      'maxPages' => ceil($dati['lista']->count() / $limite),
      'dati' => $dati,
    ));
  }

  /**
   * Mostra le relazioni finali inserite dai docenti
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param StaffUtil $staff Funzioni di utilità per lo staff
   * @param int $pagina Numero di pagina per la lista visualizzata
   *
   * @return Response Pagina di risposta
   *
   * @Route("/staff/documenti/relazioni/{pagina}", name="staff_documenti_relazioni",
   *    requirements={"pagina": "\d+"},
   *    defaults={"pagina": 0},
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_STAFF")
   */
  public function documentiRelazioniAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                            StaffUtil $staff, $pagina) {
    // inizializza variabili
    $dati = null;
    // recupera criteri dalla sessione
    $search = array();
    $search['docente'] = $session->get('/APP/ROUTE/staff_documenti_relazioni/docente', 0);
    $search['classe'] = $session->get('/APP/ROUTE/staff_documenti_relazioni/classe', 0);
    $docente = ($search['docente'] > 0 ? $em->getRepository('App:Docente')->find($search['docente']) : 0);
    $classe = ($search['classe'] > 0 ? $em->getRepository('App:Classe')->find($search['classe']) : 0);
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $session->get('/APP/ROUTE/staff_documenti_relazioni/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $session->set('/APP/ROUTE/staff_documenti_relazioni/pagina', $pagina);
    }
    // form di ricerca
    $limite = 25;
    $form = $this->container->get('form.factory')->createNamedBuilder('staff_documenti_relazioni', FormType::class)
      ->add('docente', EntityType::class, array('label' => 'label.autore',
        'data' => $docente,
        'class' => 'App:Docente',
        'choice_label' => function ($obj) {
            return $obj->getCognome().' '.$obj->getNome();
          },
        'placeholder' => 'label.tutti_docenti',
        'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('d')
              ->where('d NOT INSTANCE OF App:Preside AND d.abilitato=:abilitato')
              ->orderBy('d.cognome,d.nome', 'ASC')
              ->setParameters(['abilitato' => 1]);
          },
        'label_attr' => ['class' => 'sr-only'],
        'choice_attr' => function($val, $key, $index) {
            return ['class' => 'gs-no-placeholder'];
          },
        'attr' => ['class' => 'gs-placeholder'],
        'required' => false))
      ->add('classe', EntityType::class, array('label' => 'label.classe',
        'data' => $classe,
        'class' => 'App:Classe',
        'choice_label' => function ($obj) {
            return $obj->getAnno().'ª '.$obj->getSezione();
          },
        'placeholder' => 'label.qualsiasi_classe',
        'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('c')
              ->where('c.anno!=5')
              ->orderBy('c.anno,c.sezione', 'ASC');
          },
        'group_by' => function ($obj) {
            return $obj->getSede()->getCitta();
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
      $search['classe'] = (is_object($form->get('classe')->getData()) ? $form->get('classe')->getData()->getId() : 0);
      $pagina = 1;
      $session->set('/APP/ROUTE/staff_documenti_relazioni/docente', $search['docente']);
      $session->set('/APP/ROUTE/staff_documenti_relazioni/classe', $search['classe']);
      $session->set('/APP/ROUTE/staff_documenti_relazioni/pagina', $pagina);
    }
    // recupera dati
    $dati = $staff->relazioni($search, $pagina, $limite);
    // mostra la pagina di risposta
    return $this->render('ruolo_staff/documenti_relazioni.html.twig', array(
      'pagina_titolo' => 'page.staff_relazioni',
      'form' => $form->createView(),
      'form_help' => null,
      'form_success' => null,
      'page' => $pagina,
      'maxPages' => ceil($dati['lista']->count() / $limite),
      'dati' => $dati,
    ));
  }

  /**
   * Mostra i piani di lavoro inserite dai docenti
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param StaffUtil $staff Funzioni di utilità per lo staff
   * @param int $pagina Numero di pagina per la lista visualizzata
   *
   * @return Response Pagina di risposta
   *
   * @Route("/staff/documenti/piani/{pagina}", name="staff_documenti_piani",
   *    requirements={"pagina": "\d+"},
   *    defaults={"pagina": 0},
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_STAFF")
   */
  public function documentiPianiAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                        StaffUtil $staff, $pagina) {
    // inizializza variabili
    $dati = null;
    // recupera criteri dalla sessione
    $search = array();
    $search['docente'] = $session->get('/APP/ROUTE/staff_documenti_piani/docente', 0);
    $search['classe'] = $session->get('/APP/ROUTE/staff_documenti_piani/classe', 0);
    $docente = ($search['docente'] > 0 ? $em->getRepository('App:Docente')->find($search['docente']) : 0);
    $classe = ($search['classe'] > 0 ? $em->getRepository('App:Classe')->find($search['classe']) : 0);
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $session->get('/APP/ROUTE/staff_documenti_piani/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $session->set('/APP/ROUTE/staff_documenti_piani/pagina', $pagina);
    }
    // form di ricerca
    $limite = 25;
    $form = $this->container->get('form.factory')->createNamedBuilder('staff_documenti_piani', FormType::class)
      ->add('docente', EntityType::class, array('label' => 'label.autore',
        'data' => $docente,
        'class' => 'App:Docente',
        'choice_label' => function ($obj) {
            return $obj->getCognome().' '.$obj->getNome();
          },
        'placeholder' => 'label.tutti_docenti',
        'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('d')
              ->where('d NOT INSTANCE OF App:Preside AND d.abilitato=:abilitato')
              ->orderBy('d.cognome,d.nome', 'ASC')
              ->setParameters(['abilitato' => 1]);
          },
        'label_attr' => ['class' => 'sr-only'],
        'choice_attr' => function($val, $key, $index) {
            return ['class' => 'gs-no-placeholder'];
          },
        'attr' => ['class' => 'gs-placeholder'],
        'required' => false))
      ->add('classe', EntityType::class, array('label' => 'label.classe',
        'data' => $classe,
        'class' => 'App:Classe',
        'choice_label' => function ($obj) {
            return $obj->getAnno().'ª '.$obj->getSezione();
          },
        'placeholder' => 'label.qualsiasi_classe',
        'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('c')
              ->orderBy('c.anno,c.sezione', 'ASC');
          },
        'group_by' => function ($obj) {
            return $obj->getSede()->getCitta();
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
      $search['classe'] = (is_object($form->get('classe')->getData()) ? $form->get('classe')->getData()->getId() : 0);
      $pagina = 1;
      $session->set('/APP/ROUTE/staff_documenti_piani/docente', $search['docente']);
      $session->set('/APP/ROUTE/staff_documenti_piani/classe', $search['classe']);
      $session->set('/APP/ROUTE/staff_documenti_piani/pagina', $pagina);
    }
    // recupera dati
    $dati = $staff->piani($search, $pagina, $limite);
    // mostra la pagina di risposta
    return $this->render('ruolo_staff/documenti_piani.html.twig', array(
      'pagina_titolo' => 'page.staff_piani',
      'form' => $form->createView(),
      'form_help' => null,
      'form_success' => null,
      'page' => $pagina,
      'maxPages' => ceil($dati['lista']->count() / $limite),
      'dati' => $dati,
    ));
  }

  /**
   * Mostra i documenti del 15 maggio inseriti dai docenti
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param StaffUtil $staff Funzioni di utilità per lo staff
   * @param int $pagina Numero di pagina per la lista visualizzata
   *
   * @return Response Pagina di risposta
   *
   * @Route("/staff/documenti/doc15/{pagina}", name="staff_documenti_doc15",
   *    requirements={"pagina": "\d+"},
   *    defaults={"pagina": 0},
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_STAFF")
   */
  public function documentiDoc15Action(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                        StaffUtil $staff, $pagina) {
    // inizializza variabili
    $dati = null;
    // recupera criteri dalla sessione
    $search = array();
    $search['classe'] = $session->get('/APP/ROUTE/staff_documenti_doc15/classe', 0);
    $classe = ($search['classe'] > 0 ? $em->getRepository('App:Classe')->find($search['classe']) : 0);
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $session->get('/APP/ROUTE/staff_documenti_doc15/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $session->set('/APP/ROUTE/staff_documenti_doc15/pagina', $pagina);
    }
    // form di ricerca
    $limite = 25;
    $form = $this->container->get('form.factory')->createNamedBuilder('staff_documenti_doc15', FormType::class)
      ->add('classe', EntityType::class, array('label' => 'label.classe',
        'data' => $classe,
        'class' => 'App:Classe',
        'choice_label' => function ($obj) {
            return $obj->getAnno().'ª '.$obj->getSezione();
          },
        'placeholder' => 'label.qualsiasi_classe',
        'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('c')
              ->where('c.anno=5')
              ->orderBy('c.anno,c.sezione', 'ASC');
          },
        'group_by' => function ($obj) {
            return $obj->getSede()->getCitta();
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
      $search['classe'] = (is_object($form->get('classe')->getData()) ? $form->get('classe')->getData()->getId() : 0);
      $pagina = 1;
      $session->set('/APP/ROUTE/staff_documenti_doc15/classe', $search['classe']);
      $session->set('/APP/ROUTE/staff_documenti_doc15/pagina', $pagina);
    }
    // recupera dati
    $dati = $staff->doc15($search, $pagina, $limite);
    // mostra la pagina di risposta
    return $this->render('ruolo_staff/documenti_doc15.html.twig', array(
      'pagina_titolo' => 'page.staff_doc15',
      'form' => $form->createView(),
      'form_help' => null,
      'form_success' => null,
      'page' => $pagina,
      'maxPages' => ceil($dati['lista']->count() / $limite),
      'dati' => $dati,
    ));
  }

  /**
   * Gestisce la generazione della password per gli alunni
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
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
  public function passwordAction(Request $request, EntityManagerInterface $em, SessionInterface $session, $pagina) {
    // recupera criteri dalla sessione
    $search = array();
    $search['nome'] = $session->get('/APP/ROUTE/staff_password/nome', '');
    $search['cognome'] = $session->get('/APP/ROUTE/staff_password/cognome', '');
    $search['classe'] = $session->get('/APP/ROUTE/staff_password/classe', 0);
    $classe = ($search['classe'] > 0 ? $em->getRepository('App:Classe')->find($search['classe']) : 0);
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $session->get('/APP/ROUTE/staff_password/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $session->set('/APP/ROUTE/staff_password/pagina', $pagina);
    }
    // legge sede
    $sede = $this->getUser()->getSede();
    // form di ricerca
    $limite = 25;
    if ($sede) {
      // limita a classi di sede
      $classi = $em->getRepository('App:Classe')->findBy(['sede' => $sede], ['anno' =>'ASC', 'sezione' =>'ASC']);
    } else {
      // tutte le classi
      $classi = $em->getRepository('App:Classe')->findBy([], ['anno' =>'ASC', 'sezione' =>'ASC']);
    }
    $form = $this->container->get('form.factory')->createNamedBuilder('staff_password', FormType::class)
      ->setAction($this->generateUrl('staff_password'))
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
      ->add('classe', ChoiceType::class, array('label' => 'label.classe',
        'data' => $classe,
        'choices' => $classi,
        'choice_label' => function ($obj) {
            return $obj->getAnno().'ª '.$obj->getSezione().' - '.$obj->getCorso()->getNomeBreve();
          },
        'choice_value' => function ($obj) {
            return (is_object($obj)  ? $obj->getId() : $obj);
          },
        'group_by' => function ($obj) {
            return $obj->getSede()->getCitta();
          },
        'placeholder' => 'label.qualsiasi_classe',
        'choice_translation_domain' => false,
        'label_attr' => ['class' => 'sr-only'],
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.search'))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $search['nome'] = trim($form->get('nome')->getData());
      $search['cognome'] = trim($form->get('cognome')->getData());
      $search['classe'] = (is_object($form->get('classe')->getData()) ? $form->get('classe')->getData()->getId() : 0);
      $pagina = 1;
      $session->set('/APP/ROUTE/staff_password/nome', $search['nome']);
      $session->set('/APP/ROUTE/staff_password/cognome', $search['cognome']);
      $session->set('/APP/ROUTE/staff_password/classe', $search['classe']);
      $session->set('/APP/ROUTE/staff_password/pagina', $pagina);
    }
    // lista alunni
    $lista = $em->getRepository('App:Alunno')->findClassEnabled($sede, $search, $pagina, $limite);
    // mostra la pagina di risposta
    return $this->render('ruolo_staff/password.html.twig', array(
      'pagina_titolo' => 'page.staff_password',
      'form' => $form->createView(),
      'form_help' => null,
      'form_success' => null,
      'lista' => $lista,
      'page' => $pagina,
      'maxPages' => ceil($lista->count() / $limite),
    ));
  }

  /**
   * Generazione della password degli alunni
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param UserPasswordEncoderInterface $encoder Gestore della codifica delle password
   * @param SessionInterface $session Gestore delle sessioni
   * @param LogHandler $dblogger Gestore dei log su database
   * @param PdfManager $pdf Gestore dei documenti PDF
   * @param int $alunno ID dell'alunno
   * @param int $classe ID della classe
   *
   * @return Response Pagina di risposta
   *
   * @Route("/staff/password/create/{classe}/{alunno}", name="staff_password_create",
   *    requirements={"alunno": "\d+", "classe": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_STAFF")
   */
  public function passwordCreateAction(Request $request, EntityManagerInterface $em,
                                        UserPasswordEncoderInterface $encoder, SessionInterface $session, LogHandler $dblogger,
                                        PdfManager $pdf, $classe, $alunno) {
    if ($classe > 0) {
      // controlla classe
      $classe = $em->getRepository('App:Classe')->find($classe);
      if (!$classe) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      // recupera alunni della classe
      $alunni = $em->getRepository('App:Alunno')->createQueryBuilder('a')
        ->where('a.classe=:classe AND a.abilitato=:abilitato')
        ->setParameters(['classe' => $classe, 'abilitato' => 1])
        ->orderBy('a.cognome,a.nome', 'ASC')
        ->getQuery()
        ->getResult();
      if (empty($alunni)) {
        // nessun alunno
        return $this->redirectToRoute('staff_password');
      } else {
        // alunni presenti
        $pwdchars = "abcdefghikmnopqrstuvwxyz123456789";
        // crea documento PDF
        $pdf->configure($session->get('/CONFIG/SCUOLA/intestazione_istituto'),
          'Credenziali di accesso al Registro Elettronico');
        foreach ($alunni as $alu) {
          // recupera genitori (anche più di uno)
          $genitori = $em->getRepository('App:Genitore')->findBy(['alunno' => $alu]);
          if (empty($genitori)) {
            // errore
            throw $this->createNotFoundException('exception.id_notfound');
          }
          // crea password
          $password = substr(str_shuffle($pwdchars), 0, 4).substr(str_shuffle($pwdchars), 0, 4);
          foreach ($genitori as $gen) {
            $gen->setPasswordNonCifrata($password);
            $pswd = $encoder->encodePassword($gen, $gen->getPasswordNonCifrata());
            $gen->setPassword($pswd);
          }
          // memorizza su db
          $em->flush();
          // log azione
          $dblogger->write($alu, $request->getClientIp(), 'SICUREZZA', 'Generazione Password', __METHOD__, array(
            'Username esecutore' => $this->getUser()->getUsername(),
            'Ruolo esecutore' => $this->getUser()->getRoles()[0],
            'ID esecutore' => $this->getUser()->getId()
            ));
          // contenuto in formato HTML
          $html = $this->renderView('pdf/credenziali_alunni.html.twig', array(
            'alunno' => $alu,
            'username' => $genitori[0]->getUsername(),
            'password' => $password,
            'sesso' => $alu->getSesso() == 'M' ? 'o' : 'a',
            ));
          $pdf->createFromHtml($html);
        }
        // invia il documento
        $nomefile = 'credenziali-registro-CLASSE-'.$classe->getAnno().$classe->getSezione().'.pdf';
        return $pdf->send($nomefile);
      }
    } elseif ($alunno > 0) {
      // controlla alunno
      $alunno = $em->getRepository('App:Alunno')->findOneBy(['id' => $alunno, 'abilitato' => 1]);
      if (!$alunno) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      // recupera genitori (anche più di uno)
      $genitori = $em->getRepository('App:Genitore')->findBy(['alunno' => $alunno]);
      if (empty($genitori)) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      // crea password
      $pwdchars = "abcdefghikmnopqrstuvwxyz123456789";
      $password = substr(str_shuffle($pwdchars), 0, 4).substr(str_shuffle($pwdchars), 0, 4);
      foreach ($genitori as $gen) {
        $gen->setPasswordNonCifrata($password);
        $pswd = $encoder->encodePassword($gen, $gen->getPasswordNonCifrata());
        $gen->setPassword($pswd);
      }
      // memorizza su db
      $em->flush();
      // log azione
      $dblogger->write($alunno, $request->getClientIp(), 'SICUREZZA', 'Generazione Password', __METHOD__, array(
        'Username esecutore' => $this->getUser()->getUsername(),
        'Ruolo esecutore' => $this->getUser()->getRoles()[0],
        'ID esecutore' => $this->getUser()->getId()
        ));
      // crea documento PDF
      $pdf->configure($session->get('/CONFIG/SCUOLA/intestazione_istituto'),
        'Credenziali di accesso al Registro Elettronico');
      // contenuto in formato HTML
      $html = $this->renderView('pdf/credenziali_alunni.html.twig', array(
        'alunno' => $alunno,
        'username' => $genitori[0]->getUsername(),
        'password' => $password,
        'sesso' => $alunno->getSesso() == 'M' ? 'o' : 'a',
        ));
      $pdf->createFromHtml($html);
      // invia il documento
      $nomealunno = preg_replace('/[^\w-]/', '', strtoupper($alunno->getCognome().'-'.$alunno->getNome()));
      $nomefile = 'credenziali-registro-'.$nomealunno.'.pdf';
      return $pdf->send($nomefile);
    } else {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
  }

  /**
   * Gestione delle assenze di classe
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
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
  public function studentiAssenzeAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                         RegistroUtil $reg, LogHandler $dblogger, $data, $classe) {
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
      if ($session->get('/APP/ROUTE/staff_studenti_assenze/data')) {
        // recupera data da sessione
        $data_obj = \DateTime::createFromFormat('Y-m-d', $session->get('/APP/ROUTE/staff_studenti_assenze/data'));
      } else {
        // imposta data odierna
        $data_obj = new \DateTime();
      }
    } else {
      // imposta data indicata e la memorizza in sessione
      $data_obj = \DateTime::createFromFormat('Y-m-d', $data);
      $session->set('/APP/ROUTE/staff_studenti_assenze/data', $data);
    }
    // data in formato stringa
    $formatter = new \IntlDateFormatter('it_IT', \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $info['data_label'] =  $formatter->format($data_obj);
    // data prec/succ
    $data_succ = (clone $data_obj);
    $data_succ = $em->getRepository('App:Festivita')->giornoSuccessivo($data_succ);
    $data_prec = (clone $data_obj);
    $data_prec = $em->getRepository('App:Festivita')->giornoPrecedente($data_prec);
    // recupera festivi per calendario
    $lista_festivi = $reg->listaFestivi(null);
    // controllo data
    $errore = $reg->controlloData($data_obj, null);
    // parametro classe (può essere null)
    if ($classe == 0) {
      // classe non specificata
      $classe = $session->get('/APP/ROUTE/staff_studenti_assenze/classe', 0);
    }
    $classe = $em->getRepository('App:Classe')->find($classe);
    // legge sede
    $sede = $this->getUser()->getSede();
    // form di ricerca
    if ($sede) {
      // limita a classi di sede
      $classi = $em->getRepository('App:Classe')->findBy(['sede' => $sede], ['anno' =>'ASC', 'sezione' =>'ASC']);
    } else {
      // tutte le classi
      $classi = $em->getRepository('App:Classe')->findBy([], ['anno' =>'ASC', 'sezione' =>'ASC']);
    }
    $form = $this->container->get('form.factory')->createNamedBuilder('staff_studenti_assenze', FormType::class)
      ->setMethod('GET')
      ->setAction($this->generateUrl('staff_studenti_assenze', ['data' => $data]))
      ->add('classe', ChoiceType::class, array('label' => 'label.classe',
        'data' => $classe,
        'choices' => $classi,
        'choice_label' => function ($obj) {
            return $obj->getAnno().'ª '.$obj->getSezione().' - '.$obj->getCorso()->getNomeBreve();
          },
        'choice_value' => function ($obj) {
            return (is_object($obj)  ? $obj->getId() : $obj);
          },
        'group_by' => function ($obj) {
            return $obj->getSede()->getCitta();
          },
        'placeholder' => 'label.scegli_classe',
        'choice_translation_domain' => false,
        'label_attr' => ['class' => 'sr-only'],
        'required' => true))
      ->add('submit', SubmitType::class, array('label' => 'label.visualizza'))
      ->getForm();
    $form->handleRequest($request);
    if ($classe) {
      // memorizza classe
      $session->set('/APP/ROUTE/staff_studenti_assenze/classe', $classe->getId());
      if (!$errore && $reg->azioneAssenze($data_obj, $this->getUser(), null, $classe, null)) {
        // elenco alunni
        $elenco = $reg->alunniInData($data_obj, $classe);
        // elenco assenze
        $assenti = $em->getRepository('App:Alunno')->createQueryBuilder('a')
          ->join('App:Assenza', 'ass', 'WITH', 'a.id=ass.alunno AND ass.data=:data')
          ->where('a.id IN (:elenco)')
          ->setParameters(['elenco' => $elenco, 'data' => $data_obj->format('Y-m-d')])
          ->getQuery()
          ->getResult();
        // form di inserimento
        $form_assenze = $this->container->get('form.factory')->createNamedBuilder('staff_studenti_assenze_appello', FormType::class)
          ->add('alunni', EntityType::class, array('label' => 'label.alunni_assenti',
            'data' => $assenti,
            'class' => 'App:Alunno',
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
            $em->persist($assenza);
            $log['assenza_create'][] = $assenza;
            // controlla esistenza ritardo
            $entrata = $em->getRepository('App:Entrata')->findOneBy(['alunno' => $alu, 'data' => $data_obj]);
            if ($entrata) {
              // rimuove ritardo
              $log['entrata_delete'][$entrata->getId()] = $entrata;
              $em->remove($entrata);
            }
            // controlla esistenza uscita
            $uscita = $em->getRepository('App:Uscita')->findOneBy(['alunno' => $alu, 'data' => $data_obj]);
            if ($uscita) {
              // rimuove uscita
              $log['uscita_delete'][$uscita->getId()] = $uscita;
              $em->remove($uscita);
            }
          }
          // cancella assenti
          $cancella_assenti = array_diff($assenti, $form_assenze->get('alunni')->getData());
          foreach ($cancella_assenti as $alu) {
            $assenza = $em->getRepository('App:Assenza')->findOneBy(['alunno' => $alu, 'data' => $data_obj]);
            if ($assenza) {
              // rimuove assenza
              $log['assenza_delete'][$assenza->getId()] = $assenza;
              $em->remove($assenza);
            }
          }
          // ok: memorizza dati
          $em->flush();
          // ricalcola ore assenze
          foreach (array_merge($nuovi_assenti, $cancella_assenti) as $alu) {
            $reg->ricalcolaOreAlunno($data_obj, $alu);
          }
          // log azione
          $dblogger->write($this->getUser(), $request->getClientIp(), 'ASSENZE', 'Gestione assenti', __METHOD__, array(
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
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
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
  public function studentiStatisticheAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                             RegistroUtil $reg, StaffUtil $staff, $data) {
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
      if ($session->get('/APP/ROUTE/staff_studenti_statistiche/data')) {
        // recupera data da sessione
        $data_obj = \DateTime::createFromFormat('Y-m-d', $session->get('/APP/ROUTE/staff_studenti_statistiche/data'));
      } else {
        // imposta data odierna
        $data_obj = new \DateTime();
      }
    } else {
      // imposta data indicata e la memorizza in sessione
      $data_obj = \DateTime::createFromFormat('Y-m-d', $data);
      $session->set('/APP/ROUTE/staff_studenti_statistiche/data', $data);
    }
    // data in formato stringa
    $formatter = new \IntlDateFormatter('it_IT', \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $info['data_label'] =  $formatter->format($data_obj);
    // data prec/succ
    $data_succ = (clone $data_obj);
    $data_succ = $em->getRepository('App:Festivita')->giornoSuccessivo($data_succ);
    $data_prec = (clone $data_obj);
    $data_prec = $em->getRepository('App:Festivita')->giornoPrecedente($data_prec);
    // recupera festivi per calendario
    $lista_festivi = $reg->listaFestivi(null);
    // controllo data
    $errore = $reg->controlloData($data_obj, null);
    // recupera criteri dalla sessione
    $search = array();
    $search['sede'] = $session->get('/APP/ROUTE/staff_studenti_statistiche/sede', 0);
    $sede = ($search['sede'] > 0 ? $em->getRepository('App:Sede')->find($search['sede']) : 0);
    $search['classe'] = $session->get('/APP/ROUTE/staff_studenti_statistiche/classe', 0);
    $classe = ($search['classe'] > 0 ? $em->getRepository('App:Classe')->find($search['classe']) : 0);
    // legge sede
    $sede_staff = $this->getUser()->getSede();
    // form di ricerca
    if ($sede_staff) {
      // limita a classi di sede
      $sedi = [$sede_staff];
      $classi = $em->getRepository('App:Classe')->findBy(['sede' => $sede_staff], ['anno' =>'ASC', 'sezione' =>'ASC']);
    } else {
      // tutte le classi
      $sedi = $em->getRepository('App:Sede')->findBy([], ['principale' =>'DESC']);
      $classi = $em->getRepository('App:Classe')->findBy([], ['anno' =>'ASC', 'sezione' =>'ASC']);
    }
    $form = $this->container->get('form.factory')->createNamedBuilder('staff_studenti_statistiche', FormType::class)
      ->setAction($this->generateUrl('staff_studenti_statistiche', ['data' => $data_obj->format('Y-m-d')]))
      ->add('sede', ChoiceType::class, array('label' => 'label.sede',
        'data' => $sede,
        'choices' => $sedi,
        'choice_label' => function ($obj) {
            return $obj->getCitta();
          },
        'choice_value' => function ($obj) {
            return (is_object($obj)  ? $obj->getId() : $obj);
          },
        'placeholder' => 'label.qualsiasi_sede',
        'choice_translation_domain' => false,
        'label_attr' => ['class' => 'sr-only'],
        'required' => false))
      ->add('classe', ChoiceType::class, array('label' => 'label.classe',
        'data' => $classe,
        'choices' => $classi,
        'choice_label' => function ($obj) {
            return $obj->getAnno().'ª '.$obj->getSezione().' - '.$obj->getCorso()->getNomeBreve();
          },
        'choice_value' => function ($obj) {
            return (is_object($obj)  ? $obj->getId() : $obj);
          },
        'group_by' => function ($obj) {
            return $obj->getSede()->getCitta();
          },
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
      $session->set('/APP/ROUTE/staff_studenti_statistiche/sede', $search['sede']);
      $session->set('/APP/ROUTE/staff_studenti_statistiche/classe', $search['classe']);
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

}
