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
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormError;
use App\Util\LogHandler;
use App\Util\RegistroUtil;
use App\Util\GenitoriUtil;
use App\Util\PdfManager;
use App\Form\VotoClasseType;
use App\Entity\Valutazione;
use App\Entity\Notifica;


/**
 * VotiController - gestione dei voti
 */
class VotiController extends AbstractController {

  /**
   * Quadro dei voti
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param int $cattedra Identificativo della cattedra (nullo se supplenza)
   * @param int $classe Identificativo della classe
   * @param string $periodo Periodo relativo allo scrutinio
   *
   * @return Response Pagina di risposta
   *
   * @Route("/lezioni/voti/quadro/{cattedra}/{classe}/{periodo}", name="lezioni_voti_quadro",
   *    requirements={"cattedra": "\d+", "classe": "\d+", "periodo": "1|2|3|0"},
   *    defaults={"cattedra": 0, "classe": 0, "periodo": 0},
   *    methods={"GET"})
   *
   * @Security("has_role('ROLE_DOCENTE')")
   */
  public function votiAction(Request $request, EntityManagerInterface $em, SessionInterface $session, RegistroUtil $reg,
                              $cattedra, $classe, $periodo) {
    // inizializza variabili
    $dati = array();
    $dati['alunni'] = array();
    $info = null;
    $azione_edit = false;
    $lista_periodi = null;
    // parametri cattedra/classe/periodo
    if ($cattedra == 0 && $classe == 0) {
      // recupera parametri da sessione
      $cattedra = $session->get('/APP/DOCENTE/cattedra_lezione');
      $classe = $session->get('/APP/DOCENTE/classe_lezione');
    }
    // controllo cattedra/supplenza
    if ($cattedra > 0) {
      // lezione in propria cattedra: controlla esistenza
      $cattedra = $em->getRepository('App:Cattedra')->findOneBy(['id' => $cattedra,
        'docente' => $this->getUser(), 'attiva' => 1]);
      if (!$cattedra) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      if ($cattedra->getMateria()->getTipo() == 'S') {
        // cattedra di sostegno: redirezione
        return $this->redirectToRoute('lezioni_voti_sostegno', ['cattedra' => $cattedra->getId()]);
      }
      // informazioni necessarie
      $classe = $cattedra->getClasse();
      $info['materia'] = $cattedra->getMateria()->getNomeBreve();
      $info['religione'] = ($cattedra->getMateria()->getTipo() == 'R');
      $info['alunno'] = $cattedra->getAlunno();
      // memorizza parametri in sessione
      $session->set('/APP/DOCENTE/cattedra_lezione', $cattedra->getId());
      $session->set('/APP/DOCENTE/classe_lezione', $classe->getId());
    } elseif ($classe > 0) {
      // supplenza
      $classe = $em->getRepository('App:Classe')->find($classe);
      if (!$classe) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $materia = $em->getRepository('App:Materia')->findOneByTipo('U');
      if (!$materia) {
        // errore
        throw $this->createNotFoundException('exception.invalid_params');
      }
      // informazioni necessarie
      $cattedra = null;
      $info['materia'] = $materia->getNomeBreve();
      $info['religione'] = false;
      $info['alunno'] = null;
    }
    if ($cattedra) {
      // periodo
      $lista_periodi = $reg->infoPeriodi();
      // seleziona periodo se non indicato
      if ($periodo == 0) {
        // seleziona periodo in base alla data
        if ($session->get('/APP/DOCENTE/data_lezione')) {
          // recupera data da sessione
          $data = \DateTime::createFromFormat('Y-m-d', $session->get('/APP/DOCENTE/data_lezione'));
        } else {
          // imposta data odierna
          $data = new \DateTime();
        }
        $periodo = $reg->periodo($data);
        if ($periodo) {
          $periodo = $periodo['periodo'];
        }
      }
      if ($periodo) {
        // dati periodo
        $inizio = \DateTime::createFromFormat('Y-m-d', $lista_periodi[$periodo]['inizio']);
        $fine = \DateTime::createFromFormat('Y-m-d', $lista_periodi[$periodo]['fine']);
        // controlla permessi
        if ($reg->azioneVoti($inizio, $this->getUser(), null, $classe, $cattedra->getMateria())) {
          // edit permesso
          $azione_edit = true;
        }
        // legge voti
        $dati = $reg->quadroVoti($inizio, $fine, $this->getUser(), $cattedra);
      }
    }
    // salva pagina visitata
    $route = ['name' => $request->get('_route'), 'param' => $request->get('_route_params')];
    $session->set('/APP/DOCENTE/menu_lezione', $route);
    // visualizza pagina
    return $this->render('lezioni/voti_quadro.html.twig', array(
      'pagina_titolo' => 'page.lezioni_voti',
      'cattedra' => $cattedra,
      'classe' => $classe,
      'info' => $info,
      'dati' => $dati,
      'edit' => $azione_edit,
      'lista_periodi' => $lista_periodi,
      'periodo' => $periodo,
    ));
  }

  /**
   * Gestione dei voti per le prove di classe
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $cattedra Identificativo della cattedra
   * @param string $tipo Tipo della valutazione (S,O,P)
   * @param string $data Data del giorno (AAAA-MM-GG)
   *
   * @return Response Pagina di risposta
   *
   * @Route("/lezioni/voti/classe/{cattedra}/{tipo}/{data}", name="lezioni_voti_classe",
   *    requirements={"cattedra": "\d+", "tipo": "S|O|P", "data": "\d\d\d\d-\d\d-\d\d"},
   *    defaults={"data": "0000-00-00"},
   *    methods={"GET","POST"})
   *
   * @Security("has_role('ROLE_DOCENTE')")
   */
  public function votiClasseAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                   TranslatorInterface $trans, RegistroUtil $reg, LogHandler $dblogger, $cattedra, $tipo, $data) {
    // inizializza
    $label = array();
    $visibile = true;
    $argomento = null;
    $elenco = null;
    // controllo cattedra
    $cattedra = $em->getRepository('App:Cattedra')->find($cattedra);
    if (!$cattedra) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // recupera classe
    $classe = $cattedra->getClasse();
    // controlla data
    if ($data == '0000-00-00') {
      // data non specificata
      $data = new \DateTime();
    } else {
      // data esistente
      $data = \DateTime::createFromFormat('Y-m-d', $data);
    }
    // elenco di alunni
    $elenco = $reg->elencoVoti($data, $this->getUser(), $classe, $cattedra->getMateria(), $tipo, $argomento, $visibile);
    $elenco_precedente = unserialize(serialize($elenco)); // clona oggetti
    // dati in formato stringa
    $label['materia'] = $cattedra->getMateria()->getNomeBreve();
    $label['classe'] = $classe->getAnno()."ª ".$classe->getSezione();
    $label['tipo'] = 'label.voti_'.$tipo;
    $label['festivi'] = $reg->listaFestivi();
    $label['inizio'] = \DateTime::createFromFormat('Y-m-d', $session->get('/CONFIG/SCUOLA/anno_inizio'))->format('d/m/Y');
    $label['fine'] = \DateTime::createFromFormat('Y-m-d', $session->get('/CONFIG/SCUOLA/anno_fine'))->format('d/m/Y');
    // form di inserimento
    $form = $this->container->get('form.factory')->createNamedBuilder('voti_classe', FormType::class)
      ->add('data', DateType::class, array('label' => 'label.data',
        'data' => $data,
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-picker'],
        'format' => 'dd/MM/yyyy',
        'mapped' => false,
        'required' => true))
      ->add('visibile', ChoiceType::class, array('label' => 'label.visibile_genitori',
        'data' => $visibile,
        'choices' => ['label.si' => true, 'label.no' => false],
        'expanded' => true,
        'multiple' => false,
        'label_attr' => ['class' => 'radio-inline'],
        'required' => true))
      ->add('argomento', TextareaType::class, array('label' => 'label.voto_argomento',
        'data' => $argomento,
        'trim' => true,
        'required' => false))
      ->add('lista', CollectionType::class, array('label' => false,
        'data' => $elenco,
        'entry_type' => VotoClasseType::class,
        'entry_options' => array('label' => false),
        ))
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start']))
      ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end',
        'onclick' => "location.href='".$this->generateUrl('lezioni_voti_quadro')."'"]))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted()) {
      // controllo data
      $errore = $reg->controlloData($form->get('data')->getData(), null);
      if ($errore) {
        // errore: festivo
        $form->get('data')->addError(new FormError($trans->trans('exception.data_festiva')));
      }
      // controlla lezione
      $lezione = $reg->lezioneCattedra($form->get('data')->getData(), $this->getUser(), $classe, $cattedra->getMateria());
      if (!$lezione) {
        // lezione non esiste
        $form->get('data')->addError(new FormError($trans->trans('exception.lezione_non_esiste',
          ['%materia%' => $cattedra->getMateria()->getNomeBreve()])));
      }
      // controlla permessi
      if (!$reg->azioneVoti($form->get('data')->getData(), $this->getUser(), null, $classe, $cattedra->getMateria())) {
        // errore: azione non permessa
        $form->addError(new FormError($trans->trans('exception.non_permesso_in_data')));
      }
      // controllo alunni
      $lista_alunni = $reg->alunniInData($form->get('data')->getData(), $classe);
      foreach ($form->get('lista')->getData() as $valutazione) {
        // controlla alunno
        if (!in_array($valutazione->getId(), $lista_alunni) &&
            ($valutazione->getVoto() > 0 || !empty($valutazione->getGiudizio()))) {
          // errore: alunno non presente in data
          $form->addError(new FormError($trans->trans('exception.alunno_no_classe_in_data',
            ['%alunno%' => $valutazione->getAlunno()])));
        }
      }
      if ($form->isValid()) {
        $log['create'] = array();
        $log['edit'] = array();
        $log['delete'] = array();
        foreach ($form->get('lista')->getData() as $key=>$valutazione) {
          // correzione voto
          if ($valutazione->getVoto() > 0 && $valutazione->getVoto() < 1) {
            $valutazione->setVoto(1);
          } elseif ($valutazione->getVoto() > 10) {
            $valutazione->setVoto(10);
          }
          // legge alunno
          $alunno = $em->getRepository('App:Alunno')->find($valutazione->getId());
          // legge vecchio voto
          $voto = ($elenco_precedente[$key]->getVotoId() ?
            $em->getRepository('App:Valutazione')->find($elenco_precedente[$key]->getVotoId()) : null);
          if (!$voto && ($valutazione->getVoto() > 0 || !empty($valutazione->getGiudizio()))) {
            // valutazione aggiunta
            $voto = (new Valutazione())
              ->setTipo($tipo)
              ->setVisibile($form->get('visibile')->getData())
              ->setMedia($form->get('visibile')->getData())
              ->setArgomento($form->get('argomento')->getData())
              ->setDocente($this->getUser())
              ->setLezione($lezione)
              ->setAlunno($alunno)
              ->setVoto($valutazione->getVoto())
              ->setGiudizio($valutazione->getGiudizio());
            $em->persist($voto);
            $log['create'][] = $voto;
          } elseif ($voto && $valutazione->getVoto() == 0 && empty($valutazione->getGiudizio())) {
            // valutazione cancellata
            $log['delete'][] = array($voto->getId(), $voto);
            $em->remove($voto);
          } elseif ($voto && ($elenco_precedente[$key]->getVoto() != $valutazione->getVoto() ||
                    $elenco_precedente[$key]->getGiudizio() != $valutazione->getGiudizio() ||
                    $argomento != $form->get('argomento')->getData() || $visibile != $form->get('visibile')->getData() ||
                    $voto->getLezione()->getId() != $lezione->getId())) {
            // valutazione modificata
            $log['edit'][] = array($voto->getId(), $voto->getVisibile(), $voto->getArgomento(),
              $voto->getLezione()->getId(), $voto->getVoto(), $voto->getGiudizio());
            $voto
              ->setVisibile($form->get('visibile')->getData())
              ->setMedia($form->get('visibile')->getData())
              ->setLezione($lezione)
              ->setArgomento($form->get('argomento')->getData())
              ->setVoto($valutazione->getVoto())
              ->setGiudizio($valutazione->getGiudizio());
          }
        }
        // ok: memorizza dati
        $em->flush();
        // log azione e notifica
        foreach ($log['create'] as $obj) {
          $notifica = (new Notifica())
            ->setOggettoNome('Valutazione')
            ->setOggettoId($obj->getId())
            ->setAzione('A');
          $em->persist($notifica);
        }
        foreach ($log['edit'] as $obj) {
          $notifica = (new Notifica())
            ->setOggettoNome('Valutazione')
            ->setOggettoId($obj[0])
            ->setAzione('E');
          $em->persist($notifica);
        }
        foreach ($log['delete'] as $obj) {
          $notifica = (new Notifica())
            ->setOggettoNome('Valutazione')
            ->setOggettoId($obj[0])
            ->setAzione('D');
          $em->persist($notifica);
        }
        $dblogger->write($this->getUser(), $request->getClientIp(), 'VOTI', 'Voti della classe', __METHOD__, array(
          'Tipo' => $tipo,
          'Voti creati' => implode(', ', array_map(function ($e) {
              return $e->getId();
            }, $log['create'])),
          'Voti modificati' => implode(', ', array_map(function ($e) {
              return '[Id: '.$e[0].', Visibile: '.$e[1].', Argomento: "'.$e[2].'"'.
                ', Lezione: '.$e[3].
                ', Voto: '.$e[4].', Giudizio: "'.$e[5].'"'.']';
            }, $log['edit'])),
          'Voti cancellati' => implode(', ', array_map(function ($e) {
              return '[Id: '.$e[0].', Tipo: '.$e[1]->getTipo().', Visibile: '.$e[1]->getVisibile().
                ', Argomento: "'.$e[1]->getArgomento().'", Docente: '.$e[1]->getDocente()->getId().
                ', Alunno: '.$e[1]->getAlunno()->getId().', Lezione: '.$e[1]->getLezione()->getId().
                ', Voto: '.$e[1]->getVoto().', Giudizio: "'.$e[1]->getGiudizio().'"'.']';
            }, $log['delete']))
          ));
        // redirezione
        return $this->redirectToRoute('lezioni_voti_quadro');
      }
    }
    // mostra la pagina di risposta
    return $this->render('lezioni/voti_classe_edit.html.twig', array(
      'pagina_titolo' => 'page.lezioni_voti',
      'form' => $form->createView(),
      'form_title' => 'title.voti_classe',
      'label' => $label,
    ));
  }

  /**
   * Gestione dei voti per l'alunno
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $cattedra Identificativo della cattedra
   * @param int $alunno Identificativo dell'alunno
   * @param string $tipo Tipo della valutazione (S,O,P)
   * @param int $id Identificativo del voto (0=nuovo)
   *
   * @return Response Pagina di risposta
   *
   * @Route("/lezioni/voti/alunno/{cattedra}/{alunno}/{tipo}/{id}", name="lezioni_voti_alunno",
   *    requirements={"cattedra": "\d+", "alunno": "\d+", "tipo": "S|O|P", "id": "\d+"},
   *    defaults={"id": "0"},
   *    methods={"GET","POST"})
   *
   * @Security("has_role('ROLE_DOCENTE')")
   */
  public function votiAlunnoAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                   TranslatorInterface $trans, RegistroUtil $reg, LogHandler $dblogger, $cattedra, $alunno, $tipo, $id) {
    // inizializza
    $label = array();
    // controllo cattedra
    $cattedra = $em->getRepository('App:Cattedra')->find($cattedra);
    if (!$cattedra) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // recupera classe
    $classe = $cattedra->getClasse();
    // controllo alunno
    $alunno = $em->getRepository('App:Alunno')->find($alunno);
    if (!$alunno) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo voto
    if ($id) {
      // legge voto
      $valutazione = $em->getRepository('App:Valutazione')->findOneBy(['id' => $id, 'alunno' => $alunno,
        'docente' => $this->getUser(), 'tipo' => $tipo]);
      if ($valutazione) {
        $valutazione_precedente = array($valutazione->getId(), $valutazione->getVisibile(), $valutazione->getArgomento(),
          $valutazione->getVoto(), $valutazione->getGiudizio(), $valutazione->getLezione()->getId());
        $data = $valutazione->getLezione()->getData();
      }
    }
    if (!$id || !$valutazione) {
      // aggiungi voto
      $valutazione = (new Valutazione())
        ->setTipo($tipo)
        ->setDocente($this->getUser())
        ->setAlunno($alunno)
        ->setVisibile(true);
      $em->persist($valutazione);
      $valutazione_precedente = null;
      $data = new \DateTime();
    }
    // dati in formato stringa
    $label['materia'] = $cattedra->getMateria()->getNomeBreve();
    $label['classe'] = $classe->getAnno()."ª ".$classe->getSezione();
    $label['tipo'] = 'label.voti_'.$tipo;
    $label['alunno'] = $alunno->getCognome().' '.$alunno->getNome().' ('.$alunno->getDataNascita()->format('d/m/Y').')';
    $label['bes'] = $alunno->getBes();
    $label['festivi'] = $reg->listaFestivi();
    $label['inizio'] = \DateTime::createFromFormat('Y-m-d', $session->get('/CONFIG/SCUOLA/anno_inizio'))->format('d/m/Y');
    $label['fine'] = \DateTime::createFromFormat('Y-m-d', $session->get('/CONFIG/SCUOLA/anno_fine'))->format('d/m/Y');
    // form di inserimento
    $form = $this->container->get('form.factory')->createNamedBuilder('voti_alunno', FormType::class, $valutazione)
      ->add('data', DateType::class, array('label' => 'label.data',
        'data' => $data,
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-picker'],
        'format' => 'dd/MM/yyyy',
        'mapped' => false,
        'required' => true))
      ->add('visibile', ChoiceType::class, array('label' => 'label.visibile_genitori',
        'choices' => ['label.si' => true, 'label.no' => false],
        'expanded' => true,
        'multiple' => false,
        'label_attr' => ['class' => 'radio-inline'],
        'required' => true))
      ->add('argomento', TextareaType::class, array('label' => 'label.voto_argomento',
        'trim' => true,
        'required' => false))
      ->add('voto', HiddenType::class)
      ->add('giudizio', TextareaType::class, array('label' => 'label.voto_giudizio',
        'trim' => true,
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start']));
    if ($valutazione_precedente) {
      $form = $form
        ->add('delete', SubmitType::class, array('label' => 'label.delete',
          'attr' => ['widget' => 'gs-button-inline', 'class' => 'btn-danger']));
    }
    $form = $form
      ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end',
        'onclick' => "location.href='".$this->generateUrl('lezioni_voti_quadro')."'"]))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted()) {
      // correzione voto
      if ($valutazione->getVoto() > 0 && $valutazione->getVoto() < 1) {
        $valutazione->setVoto(1);
      } elseif ($valutazione->getVoto() > 10) {
        $valutazione->setVoto(10);
      }
      // controlli
      if ($valutazione_precedente && $form->get('delete')->isClicked()) {
        // cancella voto
        $em->remove($valutazione);
      } else {
        // controllo data
        $errore = $reg->controlloData($form->get('data')->getData(), null);
        if ($errore) {
          // errore: festivo
          $form->get('data')->addError(new FormError($trans->trans('exception.data_festiva')));
        }
        // controlla lezione
        $lezione = $reg->lezioneCattedra($form->get('data')->getData(), $this->getUser(), $classe, $cattedra->getMateria());
        if (!$lezione) {
          // lezione non esiste
          $form->get('data')->addError(new FormError($trans->trans('exception.lezione_non_esiste',
            ['%materia%' => $cattedra->getMateria()->getNomeBreve()])));
        } else {
          // inserisce lezione
          $valutazione->setLezione($lezione);
        }
        // controlla permessi
        if (!$reg->azioneVoti($form->get('data')->getData(), $this->getUser(), $alunno, $classe, $cattedra->getMateria())) {
          // errore: azione non permessa
          $form->addError(new FormError($trans->trans('exception.non_permesso_in_data')));
        }
        // controlla voto
        if (empty($valutazione->getVoto()) && empty($valutazione->getGiudizio())) {
          // errore di validazione
          $form->addError(new FormError($trans->trans('exception.voto_vuoto')));
        }
      }
      if ($form->isValid()) {
        // crea o modifica voto
        $valutazione->setMedia($valutazione->getVisibile());
        // ok: memorizza dati
        $em->flush();
        // log azione e notifica
        $notifica = (new Notifica())
          ->setOggettoNome('Valutazione');
        $em->persist($notifica);
        if ($valutazione_precedente && $form->get('delete')->isClicked()) {
          // cancellazione
          $notifica->setAzione('D')->setOggettoId($valutazione_precedente[0]);
          $dblogger->write($this->getUser(), $request->getClientIp(), 'VOTI', 'Cancella voto', __METHOD__, array(
            'Id' => $valutazione_precedente[0],
            'Tipo' => $tipo,
            'Visibile' => $valutazione_precedente[1],
            'Argomento' => $valutazione_precedente[2],
            'Voto' => $valutazione_precedente[3],
            'Giudizio' => $valutazione_precedente[4],
            'Docente' => $valutazione->getDocente()->getId(),
            'Alunno' => $valutazione->getAlunno()->getId(),
            'Lezione' => $valutazione_precedente[5]
            ));
        } elseif ($valutazione_precedente && ($valutazione_precedente[3] != $valutazione->getVoto() ||
                  $valutazione_precedente[4] != $valutazione->getGiudizio() ||
                  $valutazione_precedente[2] != $valutazione->getArgomento() ||
                  $valutazione_precedente[1] != $valutazione->getVisibile())) {
          // modifica
          $notifica->setAzione('E')->setOggettoId($valutazione->getId());
          $dblogger->write($this->getUser(), $request->getClientIp(), 'VOTI', 'Modifica voto', __METHOD__, array(
            'Id' => $valutazione_precedente[0],
            'Visibile' => $valutazione_precedente[1],
            'Argomento' => $valutazione_precedente[2],
            'Voto' => $valutazione_precedente[3],
            'Giudizio' => $valutazione_precedente[4],
            'Lezione' => $valutazione_precedente[5]
            ));
        } elseif (!$valutazione_precedente) {
          // creazione
          $notifica->setAzione('A')->setOggettoId($valutazione->getId());
          $dblogger->write($this->getUser(), $request->getClientIp(), 'VOTI', 'Crea voto', __METHOD__, array(
            'Id' => $valutazione->getId()
            ));
        }
        // redirezione
        return $this->redirectToRoute('lezioni_voti_quadro');
      }
    }
    // mostra la pagina di risposta
    return $this->render('lezioni/voti_alunno_edit.html.twig', array(
      'pagina_titolo' => 'page.lezioni_voti',
      'form' => $form->createView(),
      'form_title' => 'title.voti_alunno',
      'label' => $label,
    ));
  }

  /**
   * Dettagli dei voti degli alunni
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param SessionInterface $session Gestore delle sessioni
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param int $cattedra Identificativo della cattedra (nullo se supplenza)
   * @param int $classe Identificativo della classe
   * @param int $alunno Identificativo dell'alunno (nullo se non ancora scelto)
   *
   * @return Response Pagina di risposta
   *
   * @Route("/lezioni/voti/dettagli/{cattedra}/{classe}/{alunno}", name="lezioni_voti_dettagli",
   *    requirements={"cattedra": "\d+", "classe": "\d+", "alunno": "\d+"},
   *    defaults={"cattedra": 0, "classe": 0, "alunno": 0},
   *    methods={"GET"})
   *
   * @Security("has_role('ROLE_DOCENTE')")
   */
  public function votiDettagliAction(Request $request, EntityManagerInterface $em, TranslatorInterface $trans,
                                      SessionInterface $session, RegistroUtil $reg, $cattedra, $classe, $alunno) {
    // inizializza variabili
    $info = null;
    $dati = null;
    // parametri cattedra/classe
    if ($cattedra == 0 && $classe == 0) {
      // recupera parametri da sessione
      $cattedra = $session->get('/APP/DOCENTE/cattedra_lezione');
      $classe = $session->get('/APP/DOCENTE/classe_lezione');
    } else {
      // memorizza su sessione
      $session->set('/APP/DOCENTE/cattedra_lezione', $cattedra);
      $session->set('/APP/DOCENTE/classe_lezione', $classe);
    }
    // parametro alunno
    if ($alunno > 0) {
      $alunno = $em->getRepository('App:Alunno')->find($alunno);
      if (!$alunno) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
    }
    // controllo cattedra/supplenza
    if ($cattedra > 0) {
      // lezione in propria cattedra: controlla esistenza
      $cattedra = $em->getRepository('App:Cattedra')->findOneBy(['id' => $cattedra,
        'docente' => $this->getUser(), 'attiva' => 1]);
      if (!$cattedra) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      // informazioni necessarie
      $classe = $cattedra->getClasse();
      $info['materia'] = $cattedra->getMateria()->getNomeBreve();
      $info['religione'] = ($cattedra->getMateria()->getTipo() == 'R');
      $info['alunno'] = $cattedra->getAlunno();
    } elseif ($classe > 0) {
      // supplenza
      $classe = $em->getRepository('App:Classe')->find($classe);
      if (!$classe) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
    }
    if ($cattedra) {
      // lista alunni
      $alunni = $em->getRepository('App:Alunno')->createQueryBuilder('a')
        ->select('a.id,a.nome,a.cognome,a.dataNascita,a.bes,a.note,a.religione')
        ->where('a.classe=:classe AND a.abilitato=:abilitato')
        ->setParameters(['classe' => $classe, 'abilitato' => 1])
        ->getQuery()
        ->getArrayResult();
      if ($alunno && array_search($alunno->getId(), array_column($alunni, 'id')) !== false) {
        // alunno indicato e presente in classe
        $info['alunno_scelto'] = $alunno->getCognome().' '.$alunno->getNome().' ('.
          $alunno->getDataNascita()->format('d/m/Y').')';
        $info['bes'] = $alunno->getBes();
        $info['note'] = $alunno->getNote();
      } else {
        // alunno non specificato o non presente in classe
        $info['alunno_scelto'] = $trans->trans('label.scegli_alunno');
        $alunno = null;
      }
      if ($alunno) {
        // recupera dati
        $dati = $reg->dettagliVoti($this->getUser(), $cattedra, $alunno);
      }
    }
    // salva pagina visitata
    $route = ['name' => $request->get('_route'), 'param' => $request->get('_route_params')];
    $session->set('/APP/DOCENTE/menu_lezione', $route);
    // visualizza pagina
    return $this->render('lezioni/voti_dettagli.html.twig', array(
      'pagina_titolo' => 'page.lezioni_voti_dettagli',
      'cattedra' => $cattedra,
      'classe' => $classe,
      'alunni' => $alunni,
      'idalunno' => ($alunno ? $alunno->getId() : 0),
      'info' => $info,
      'dati' => $dati,
    ));
  }

  /**
   * Dettagli dei voti di un alunno con sostegno
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param SessionInterface $session Gestore delle sessioni
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param GenitoriUtil $gen Funzioni di utilità per i genitori
   * @param int $cattedra Identificativo della cattedra
   * @param int $materia Identificativo della materia
   *
   * @return Response Pagina di risposta
   *
   * @Route("/lezioni/voti/sostegno/{cattedra}/{materia}", name="lezioni_voti_sostegno",
   *    requirements={"cattedra": "\d+", "materia": "\d+"},
   *    defaults={"cattedra": 0, "materia": 0},
   *    methods={"GET"})
   *
   * @Security("has_role('ROLE_DOCENTE')")
   */
  public function votiSostegnoAction(Request $request, EntityManagerInterface $em, TranslatorInterface $trans,
                                      SessionInterface $session, RegistroUtil $reg, GenitoriUtil $gen,
                                      $cattedra, $materia) {
    // inizializza variabili
    $materie = null;
    $info = null;
    $dati = null;
    // parametro cattedra
    if ($cattedra == 0) {
      // recupera parametri da sessione
      $cattedra = $session->get('/APP/DOCENTE/cattedra_lezione');
    }
    // controllo cattedra
    if ($cattedra > 0) {
      // lezione in propria cattedra: controlla esistenza
      $cattedra = $em->getRepository('App:Cattedra')->findOneBy(['id' => $cattedra,
        'docente' => $this->getUser(), 'attiva' => 1]);
      if (!$cattedra) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      // informazioni necessarie
      $classe = $cattedra->getClasse();
      $alunno = $cattedra->getAlunno();
      $info['materia'] = $cattedra->getMateria()->getNomeBreve();
    } else {
      // cattedra non specificata
      $classe = null;
      $alunno = null;
    }
    // parametro materia
    if ($materia > 0) {
      $materia = $em->getRepository('App:Materia')->find($materia);
      if (!$materia) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
    }
    if ($cattedra) {
      // lista materie
      $materie = $gen->materie($classe, false);
      if ($materia && array_search($materia->getId(), array_column($materie, 'id')) !== false) {
        // materia indicata e presente in cattedre di classe
        $info['materia_scelta'] = $materia->getNome();
      } else {
        // materia non specificata o non presente in cattedre di classe
        $info['materia_scelta'] = $trans->trans('label.scegli_materia');
        $materia = null;
      }
      if ($materia) {
      // recupera dati
        $dati = $gen->voti($classe, $materia, $alunno);
      }
    }
    // salva pagina visitata
    $route = ['name' => $request->get('_route'), 'param' => $request->get('_route_params')];
    $session->set('/APP/DOCENTE/menu_lezione', $route);
    // visualizza pagina
    return $this->render('lezioni/voti_sostegno.html.twig', array(
      'pagina_titolo' => 'page.lezioni_voti_dettagli',
      'cattedra' => $cattedra,
      'classe' => $classe,
      'alunno' => $alunno,
      'materie' => $materie,
      'idmateria' => ($materia ? $materia->getId() : 0),
      'info' => $info,
      'dati' => $dati,
    ));
  }

  /**
   * Stampa del quadro dei voti
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param PdfManager $pdf Gestore dei documenti PDF
   * @param int $cattedra Identificativo della cattedra (nullo se supplenza)
   * @param int $classe Identificativo della classe
   * @param string $data Data del giorno (AAAA-MM-GG)
   *
   * @return Response Pagina di risposta
   *
   * @Route("/lezioni/voti/stampa/{cattedra}/{classe}/{data}", name="lezioni_voti_stampa",
   *    requirements={"cattedra": "\d+", "classe": "\d+", "data": "\d\d\d\d-\d\d-\d\d"},
   *    defaults={"cattedra": 0, "classe": 0, "data": "0000-00-00"},
   *    methods={"GET"})
   *
   * @Security("has_role('ROLE_DOCENTE')")
   */
  public function votiStampaAction(EntityManagerInterface $em, SessionInterface $session, RegistroUtil $reg,
                                    PdfManager $pdf, $cattedra, $classe, $data) {
    // inizializza variabili
    $dati = null;
    // parametri cattedra/classe
    if ($cattedra == 0 && $classe == 0) {
      // recupera parametri da sessione
      $cattedra = $session->get('/APP/DOCENTE/cattedra_lezione');
      $classe = $session->get('/APP/DOCENTE/classe_lezione');
    } else {
      // memorizza su sessione
      $session->set('/APP/DOCENTE/cattedra_lezione', $cattedra);
      $session->set('/APP/DOCENTE/classe_lezione', $classe);
    }
    // parametro data
    if ($data == '0000-00-00') {
      // data non specificata
      if ($session->get('/APP/DOCENTE/data_lezione')) {
        // recupera data da sessione
        $data_obj = \DateTime::createFromFormat('Y-m-d', $session->get('/APP/DOCENTE/data_lezione'));
      } else {
        // imposta data odierna
        $data_obj = new \DateTime();
      }
    } else {
      // imposta data indicata
      $data_obj = \DateTime::createFromFormat('Y-m-d', $data);
    }
    // data in formato stringa
    $formatter = new \IntlDateFormatter('it_IT', \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $info['data_label'] =  $formatter->format($data_obj);
    // controllo cattedra
    $cattedra = $em->getRepository('App:Cattedra')->findOneBy(['id' => $cattedra,
      'docente' => $this->getUser(), 'attiva' => 1]);
    if (!$cattedra) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    if ($cattedra->getTipo() == 'S' || $cattedra->getMateria()->getTipo() == 'S') {
      // cattedra di sostegno: errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // informazioni necessarie
    $classe = $cattedra->getClasse();
    $info['materia'] = $cattedra->getMateria()->getNomeBreve();
    $info['religione'] = ($cattedra->getMateria()->getTipo() == 'R');
    // recupera dati
    $info['periodo'] = $reg->periodo($data_obj);
    $dati = $reg->quadroVoti($info['periodo']['inizio'], $info['periodo']['fine'], $this->getUser(), $cattedra);
    // crea documento PDF
    $pdf->configure("{{ app.session->get('/CONFIG/SCUOLA/intestazione_istituto') }}",
      'Voti della classe '.$classe->getAnno().'ª '.$classe->getSezione().' - '.$info['materia']);
    $html = $this->renderView('pdf/voti_quadro.html.twig', array(
      'classe' => $classe,
      'info' => $info,
      'dati' => $dati,
      ));
    $pdf->createFromHtml($html);
    // invia il documento
    $nomefile = 'voti-'.$classe->getAnno().$classe->getSezione().'-'.
      strtoupper(str_replace(' ', '-', $info['materia'])).'.pdf';
    return $pdf->send($nomefile);
  }

}
