<?php
/**
 * giua@school
 *
 * Copyright (c) 2017 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017
 */


namespace AppBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormError;
use AppBundle\Util\LogHandler;
use AppBundle\Util\RegistroUtil;
use AppBundle\Util\GenitoriUtil;
use AppBundle\Util\PdfManager;
use AppBundle\Form\VotoClasseType;
use AppBundle\Entity\Valutazione;


/**
 * VotiController - gestione dei voti
 */
class VotiController extends Controller {

  /**
   * Quadro dei voti
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param int $cattedra Identificativo della cattedra (nullo se supplenza)
   * @param int $classe Identificativo della classe
   * @param string $data Data del giorno (AAAA-MM-GG)
   *
   * @return Response Pagina di risposta
   *
   * @Route("/lezioni/voti/quadro/{cattedra}/{classe}/{data}", name="lezioni_voti_quadro",
   *    requirements={"cattedra": "\d+", "classe": "\d+", "data": "\d\d\d\d-\d\d-\d\d"},
   *    defaults={"cattedra": 0, "classe": 0, "data": "0000-00-00"})
   * @Method("GET")
   *
   * @Security("has_role('ROLE_DOCENTE')")
   */
  public function votiAction(Request $request, EntityManagerInterface $em, SessionInterface $session, RegistroUtil $reg,
                              $cattedra, $classe, $data) {
    // inizializza variabili
    $lista_festivi = null;
    $errore = null;
    $dati = null;
    $settimana = ['Domenica', 'Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato'];
    $mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    $lezione = null;
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
      // imposta data indicata e la memorizza in sessione
      $data_obj = \DateTime::createFromFormat('Y-m-d', $data);
      $session->set('/APP/DOCENTE/data_lezione', $data);
    }
    // data in formato stringa
    $formatter = new \IntlDateFormatter('it_IT', \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $info['data_label'] =  $formatter->format($data_obj);
    // controllo cattedra/supplenza
    if ($cattedra > 0) {
      // lezione in propria cattedra: controlla esistenza
      $cattedra = $em->getRepository('AppBundle:Cattedra')->findOneBy(['id' => $cattedra,
        'docente' => $this->getUser(), 'attiva' => 1]);
      if (!$cattedra) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      if ($cattedra->getTipo() == 'S' || $cattedra->getMateria()->getTipo() == 'S') {
        // cattedra di sostegno: redirezione
        return $this->redirectToRoute('lezioni_voti_sostegno', ['cattedra' => $cattedra->getId()]);
      }
      // informazioni necessarie
      $classe = $cattedra->getClasse();
      $info['materia'] = $cattedra->getMateria()->getNomeBreve();
      $info['religione'] = ($cattedra->getMateria()->getTipo() == 'R');
      $info['alunno'] = $cattedra->getAlunno();
    } elseif ($classe > 0) {
      // supplenza
      $classe = $em->getRepository('AppBundle:Classe')->find($classe);
      if (!$classe) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $materia = $em->getRepository('AppBundle:Materia')->findOneByTipo('U');
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
      // recupera festivi per calendario
      $lista_festivi = $reg->listaFestivi($classe->getSede());
      // controllo data
      $errore = $reg->controlloData($data_obj, $classe->getSede());
      if (!$errore) {
        // non festivo: recupera dati
        $info['periodo'] = $reg->periodo($data_obj);
        if ($cattedra->getTipo() != 'S' && $cattedra->getMateria()->getTipo() != 'S') {
          $lezione = $reg->lezioneCattedra($data_obj, $this->getUser(), $classe, $cattedra->getMateria());
          // controlla permessi
          if ($lezione && !$reg->azioneVoti($data_obj, $this->getUser(), null, $classe, $cattedra->getMateria())) {
            // azione non permessa
            $lezione = null;
          }
          $dati = $reg->quadroVoti($data_obj, $info['periodo']['inizio'], $info['periodo']['fine'], $this->getUser(), $cattedra);
        }
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
      'lezione' => $lezione,
      'data' => $data_obj->format('Y-m-d'),
      'settimana' => $settimana,
      'mesi' => $mesi,
      'errore' => $errore,
      'lista_festivi' => $lista_festivi,
      'info' => $info,
      'dati' => $dati,
    ));
  }

  /**
   * Gestione dei voti per le prove di classe
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $cattedra Identificativo della cattedra (nullo se supplenza)
   * @param int $classe Identificativo della classe
   * @param string $data Data del giorno (AAAA-MM-GG)
   * @param string $tipo Tipo della valutazione (S,O,P)
   *
   * @return Response Pagina di risposta
   *
   * @Route("/lezioni/voti/classe/{cattedra}/{data}/{tipo}", name="lezioni_voti_classe",
   *    requirements={"cattedra": "\d+", "data": "\d\d\d\d-\d\d-\d\d", "tipo": "S|O|P"})
   * @Method({"GET","POST"})
   *
   * @Security("has_role('ROLE_DOCENTE')")
   */
  public function votiClasseAction(Request $request, EntityManagerInterface $em, RegistroUtil $reg, LogHandler $dblogger,
                                    $cattedra, $data, $tipo) {
    // inizializza
    $label = array();
    // controllo cattedra
    $cattedra = $em->getRepository('AppBundle:Cattedra')->find($cattedra);
    if (!$cattedra) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // recupera classe
    $classe = $cattedra->getClasse();
    // controlla data
    $data_obj = \DateTime::createFromFormat('Y-m-d', $data);
    $errore = $reg->controlloData($data_obj, $classe->getSede());
    if ($errore) {
      // errore: festivo
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // controlla lezione
    $lezione = $reg->lezioneCattedra($data_obj, $this->getUser(), $classe, $cattedra->getMateria());
    if (!$lezione) {
      // lezione non esiste
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // controlla permessi
    if (!$reg->azioneVoti($data_obj, $this->getUser(), null, $classe, $cattedra->getMateria())) {
      // errore: azione non permessa
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // elenco di alunni
    $elenco = $reg->elencoVoto($data_obj, $this->getUser(), $classe, $cattedra->getMateria(), $tipo, $argomento, $visibile);
    $elenco_precedente = unserialize(serialize($elenco)); // clona oggetti
    // dati in formato stringa
    $formatter = new \IntlDateFormatter('it_IT', \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $label['data'] =  $formatter->format($data_obj);
    $label['docente'] = $this->getUser()->getNome().' '.$this->getUser()->getCognome();
    $label['classe'] = $classe->getAnno()."ª ".$classe->getSezione();
    $label['tipo'] = 'label.voti_'.$tipo;
    // form di inserimento
    $form = $this->container->get('form.factory')->createNamedBuilder('voti_classe', FormType::class)
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
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta voti
      $log['create'] = array();
      $log['edit'] = array();
      $log['delete'] = array();
      foreach ($form->get('lista')->getData() as $key=>$voto) {
        $alunno = $em->getRepository('AppBundle:Alunno')->find($voto->getId());
        if (!$alunno) {
          // alunno non esiste, salta
          continue;
        }
        if (!$elenco_precedente[$key]->getVotoId() && ($voto->getVoto() > 0 || !empty($voto->getGiudizio()))) {
          // valutazione aggiunta
          $valutazione = (new Valutazione())
            ->setTipo($tipo)
            ->setVisibile($form->get('visibile')->getData())
            ->setMedia($form->get('visibile')->getData())
            ->setArgomento($form->get('argomento')->getData())
            ->setDocente($this->getUser())
            ->setLezione($lezione)
            ->setAlunno($alunno)
            ->setVoto($voto->getVoto())
            ->setGiudizio($voto->getGiudizio());
          $em->persist($valutazione);
          $log['create'][] = $valutazione;
        } elseif ($elenco_precedente[$key]->getVotoId() && $voto->getVoto() == 0 && empty($voto->getGiudizio())) {
          // valutazione cancellata
          $valutazione = $em->getRepository('AppBundle:Valutazione')->find($elenco_precedente[$key]->getVotoId());
          if (!$valutazione) {
            // valutazione non esiste, salta
            continue;
          }
          $log['delete'][] = array($valutazione->getId(), $valutazione);
          $em->remove($valutazione);
        } elseif ($elenco_precedente[$key]->getVotoId() && ($elenco_precedente[$key]->getVoto() != $voto->getVoto() ||
                  $elenco_precedente[$key]->getGiudizio() != $voto->getGiudizio() ||
                  $argomento != $form->get('argomento')->getData() || $visibile != $form->get('visibile')->getData())) {
          // valutazione modificata
          $valutazione = $em->getRepository('AppBundle:Valutazione')->find($elenco_precedente[$key]->getVotoId());
          if (!$valutazione) {
            // valutazione non esiste, salta
            continue;
          }
          $log['edit'][] = array($valutazione->getId(), $valutazione->getVisibile(), $valutazione->getArgomento(),
            $valutazione->getVoto(), $valutazione->getGiudizio());
          $valutazione
            ->setVisibile($form->get('visibile')->getData())
            ->setMedia($form->get('visibile')->getData())
            ->setArgomento($form->get('argomento')->getData())
            ->setVoto($voto->getVoto())
            ->setGiudizio($voto->getGiudizio());
        }
      }
      // ok: memorizza dati
      $em->flush();
      // log azione
      $dblogger->write($this->getUser(), $request->getClientIp(), 'VOTI', 'Voti della classe', __METHOD__, array(
        'Data' => $data,
        'Tipo' => $tipo,
        'Lezione' => $lezione->getId(),
        'Voti creati' => implode(', ', array_map(function ($e) {
            return $e->getId();
          }, $log['create'])),
        'Voti modificati' => implode(', ', array_map(function ($e) {
            return '[Id: '.$e[0].', Visibile: '.$e[1].', Argomento: "'.$e[2].'"'.
              ', Voto: '.$e[3].', Giudizio: "'.$e[4].'"'.']';
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
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $cattedra Identificativo della cattedra (nullo se supplenza)
   * @param int $classe Identificativo della classe
   * @param string $data Data del giorno (AAAA-MM-GG)
   * @param string $tipo Tipo della valutazione (S,O,P)
   *
   * @return Response Pagina di risposta
   *
   * @Route("/lezioni/voti/alunno/{cattedra}/{alunno}/{data}/{tipo}", name="lezioni_voti_alunno",
   *    requirements={"cattedra": "\d+", "alunno": "\d+", "data": "\d\d\d\d-\d\d-\d\d", "tipo": "S|O|P"})
   * @Method({"GET","POST"})
   *
   * @Security("has_role('ROLE_DOCENTE')")
   */
  public function votiAlunnoAction(Request $request, EntityManagerInterface $em, RegistroUtil $reg, LogHandler $dblogger,
                                    $cattedra, $alunno, $data, $tipo) {
    // inizializza
    $label = array();
    // controllo cattedra
    $cattedra = $em->getRepository('AppBundle:Cattedra')->find($cattedra);
    if (!$cattedra) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // recupera classe
    $classe = $cattedra->getClasse();
    // controllo alunno
    $alunno = $em->getRepository('AppBundle:Alunno')->find($alunno);
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
    // controlla lezione
    $lezione = $reg->lezioneCattedra($data_obj, $this->getUser(), $classe, $cattedra->getMateria());
    if (!$lezione) {
      // lezione non esiste
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // controlla permessi
    if (!$reg->azioneVoti($data_obj, $this->getUser(), $alunno, $classe, $cattedra->getMateria())) {
      // errore: azione non permessa
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // recupera voti di alunno
    $valutazione = $reg->alunnoVoto($data_obj, $this->getUser(), $alunno, $lezione, $tipo);
    if ($valutazione) {
      $valutazione_precedente = array($valutazione->getId(), $valutazione->getVisibile(), $valutazione->getArgomento(),
        $valutazione->getVoto(), $valutazione->getGiudizio());
      $voto_int = intval($valutazione->getVoto() + 0.25);
      $voto_dec = $valutazione->getVoto() - intval($valutazione->getVoto());
      $label['voto'] = $voto_int.($voto_dec == 0.25 ? '+' : ($voto_dec == 0.75 ? '-' : ($voto_dec == 0.5 ? '½' : '')));
    } else {
      // aggiungi voto
      $valutazione = (new Valutazione())
        ->setTipo($tipo)
        ->setDocente($this->getUser())
        ->setLezione($lezione)
        ->setAlunno($alunno)
        ->setVisibile(true);
      $em->persist($valutazione);
      $valutazione_precedente = null;
      $label['voto'] = '--';
    }
    // dati in formato stringa
    $formatter = new \IntlDateFormatter('it_IT', \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $label['data'] =  $formatter->format($data_obj);
    $label['docente'] = $this->getUser()->getNome().' '.$this->getUser()->getCognome();
    $label['classe'] = $classe->getAnno()."ª ".$classe->getSezione();
    $label['tipo'] = 'label.voti_'.$tipo;
    $label['alunno'] = $alunno->getCognome().' '.$alunno->getNome().' ('.$alunno->getDataNascita()->format('d/m/Y').')';
    $label['bes'] = $alunno->getBes();
    // form di inserimento
    $form = $this->container->get('form.factory')->createNamedBuilder('voti_alunno', FormType::class, $valutazione)
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
    if ($form->isSubmitted() && $form->isValid()) {
      if ($valutazione_precedente && $form->get('delete')->isClicked()) {
        // cancella voto
        $em->remove($valutazione);
      } elseif (empty($valutazione->getVoto()) && empty($valutazione->getGiudizio())) {
        // errore di validazione
        $form->addError(new FormError($this->get('translator')->trans('exception.voto_vuoto')));
      }
      if ($form->isValid()) {
        // crea o modifica voto
        $valutazione->setMedia($valutazione->getVisibile());
        // ok: memorizza dati
        $em->flush();
        // log azione
        if ($valutazione_precedente && $form->get('delete')->isClicked()) {
          // log cancellazione
          $dblogger->write($this->getUser(), $request->getClientIp(), 'VOTI', 'Cancella voto', __METHOD__, array(
            'Id' => $valutazione_precedente[0],
            'Tipo' => $tipo,
            'Visibile' => $valutazione_precedente[1],
            'Argomento' => $valutazione_precedente[2],
            'Voto' => $valutazione_precedente[3],
            'Giudizio' => $valutazione_precedente[4],
            'Docente' => $valutazione->getDocente()->getId(),
            'Alunno' => $valutazione->getAlunno()->getId(),
            'Lezione' => $valutazione->getLezione()->getId()
            ));
        } elseif ($valutazione_precedente) {
          // log modifica
          $dblogger->write($this->getUser(), $request->getClientIp(), 'VOTI', 'Modifica voto', __METHOD__, array(
            'Id' => $valutazione_precedente[0],
            'Visibile' => $valutazione_precedente[1],
            'Argomento' => $valutazione_precedente[2],
            'Voto' => $valutazione_precedente[3],
            'Giudizio' => $valutazione_precedente[4]
            ));
        } else {
          // log creazione
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
   *    defaults={"cattedra": 0, "classe": 0, "alunno": 0})
   * @Method("GET")
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
      $alunno = $em->getRepository('AppBundle:Alunno')->find($alunno);
      if (!$alunno) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
    }
    // controllo cattedra/supplenza
    if ($cattedra > 0) {
      // lezione in propria cattedra: controlla esistenza
      $cattedra = $em->getRepository('AppBundle:Cattedra')->findOneBy(['id' => $cattedra,
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
      $classe = $em->getRepository('AppBundle:Classe')->find($classe);
      if (!$classe) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
    }
    if ($cattedra) {
      // lista alunni
      $alunni = $em->getRepository('AppBundle:Alunno')->createQueryBuilder('a')
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
   *    defaults={"cattedra": 0, "materia": 0})
   * @Method("GET")
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
      $cattedra = $em->getRepository('AppBundle:Cattedra')->findOneBy(['id' => $cattedra,
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
      $materia = $em->getRepository('AppBundle:Materia')->find($materia);
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
   *
   * @return Response Pagina di risposta
   *
   * @Route("/lezioni/voti/stampa/{cattedra}/{classe}", name="lezioni_voti_stampa",
   *    requirements={"cattedra": "\d+", "classe": "\d+"},
   *    defaults={"cattedra": 0, "classe": 0})
   * @Method("GET")
   *
   * @Security("has_role('ROLE_DOCENTE')")
   */
  public function votiStampaAction(EntityManagerInterface $em, SessionInterface $session, RegistroUtil $reg,
                                    PdfManager $pdf, $cattedra, $classe) {
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
    // data in formato stringa
    $data_obj = new \DateTime('today');
    $formatter = new \IntlDateFormatter('it_IT', \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $info['data_label'] =  $formatter->format($data_obj);
    // controllo cattedra
    $cattedra = $em->getRepository('AppBundle:Cattedra')->findOneBy(['id' => $cattedra,
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
    $dati = $reg->quadroVotiStampa($info['periodo']['inizio'], $info['periodo']['fine'], $this->getUser(), $cattedra);
    // crea documento PDF
    $pdf->configure('Istituto di Istruzione Superiore',
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

