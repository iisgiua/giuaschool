<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2022 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2022
 */


namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormError;
use Symfony\Contracts\Translation\TranslatorInterface;
use App\Entity\Colloquio;
use App\Entity\RichiestaColloquio;
use App\Entity\ScansioneOraria;
use App\Util\RegistroUtil;
use App\Util\LogHandler;
use App\Form\ColloquioType;


/**
 * ColloquiController - gestione dei colloqui
 */
class ColloquiController extends AbstractController {

  /**
   * Visualizza le richieste di colloquio
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param RequestStack $reqstack Gestore dello stack delle variabili globali
   *
   * @return Response Pagina di risposta
   *
   * @Route("/colloqui", name="colloqui",
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function colloquiAction(EntityManagerInterface $em, RequestStack $reqstack) {
    // inizializza variabili
    $errore = null;
    $dati = null;
    $settimana = ['Domenica', 'Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato'];
    $mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    // controllo fine colloqui
    $fine = \DateTime::createFromFormat('Y-m-d H:i:s', $reqstack->getSession()->get('/CONFIG/SCUOLA/anno_fine').' 00:00:00');
    $fine->modify('-30 days');    // controllo fine
    $oggi = new \DateTime('today');
    if ($oggi > $fine) {
      // visualizza errore
      $errore = 'exception.colloqui_sospesi';
    } else {
      // legge richieste
      $dati['richieste'] = $em->getRepository('App\Entity\RichiestaColloquio')->colloquiDocente($this->getUser());
      $dati['ore'] = $em->getRepository('App\Entity\Colloquio')->oreNoSede($this->getUser());
      $dati['appuntamenti'] = $em->getRepository('App\Entity\RichiestaColloquio')->infoAppuntamenti($this->getUser());
    }
    // visualizza pagina
    return $this->render('colloqui/colloqui.html.twig', array(
      'pagina_titolo' => 'page.docenti_colloqui',
      'errore' => $errore,
      'dati' => $dati,
      'settimana' => $settimana,
      'mesi' => $mesi,
    ));
  }

  /**
   * Risponde ad una richiesta di colloquio
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param LogHandler $dblogger Gestore dei log su database
   * @param RichiestaColloquio $richiesta Richiesta di colloquio da modificare
   * @param string $azione Tipo di modifica da effettuare
   *
   * @return Response Pagina di risposta
   *
   * @Route("/colloqui/edit/{richiesta}/{azione}", name="colloqui_edit",
   *    requirements={"richiesta": "\d+", "azione": "C|N|X"},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function colloquiEditAction(Request $request, EntityManagerInterface $em, TranslatorInterface $trans,
                                     LogHandler $dblogger, RichiestaColloquio $richiesta, $azione) {
    // inizializza variabili
    $label = array();
    // controlla richiesta
    $richiesta = $em->getRepository('App\Entity\RichiestaColloquio')->find($richiesta);
    if (empty($richiesta)) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    $richiesta_old = array($richiesta->getStato(), $richiesta->getMessaggio());
    $colloquio = $richiesta->getColloquio();
    if ($colloquio->getDocente() != $this->getUser()) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // info
    $label['alunno'] = $richiesta->getAlunno()->getCognome().' '.$richiesta->getAlunno()->getNome();
    $label['classe'] = $richiesta->getAlunno()->getClasse()->getAnno().'ª '.$richiesta->getAlunno()->getClasse()->getSezione();
    $label['data'] = $richiesta->getAppuntamento()->format('d/m/Y');
    $label['ora_inizio'] = $richiesta->getAppuntamento()->format('G:i');
    $ora = clone $richiesta->getAppuntamento();
    $label['ora_fine'] = $ora->modify('+'.$richiesta->getDurata().' minutes')->format('G:i');
    // azione
    if ($azione == 'C') {
      // conferma colloquio
      $msg_required = true;
      $stato_disabled = true;
      $msg = 'L\'appuntamento è alle ore XX:XX; il colloquio avrà la durata di circa 10 minuti, in modo da consentire l\'incontro con diversi genitori.';
      $stato = 'C';
    } elseif ($azione == 'N') {
      // rifiuta colloquio
      $msg_required = true;
      $stato_disabled = true;
      $msg = '';
      $stato = 'N';
    } else {
      // modifica risposta
      $msg_required = true;
      $stato_disabled = false;
      $msg = $richiesta->getMessaggio();
      $stato = $richiesta->getStato();
    }
    // form di inserimento
    $form = $this->container->get('form.factory')->createNamedBuilder('colloqui_edit', FormType::class)
      ->add('stato', ChoiceType::class, array('label' => 'label.stato_colloquio',
        'data' => $stato,
        'choices'  => ['label.stato_colloquio_C' => 'C', 'label.stato_colloquio_N' => 'N'],
        'required' => true,
        'disabled' => $stato_disabled))
      ->add('messaggio', TextType::class, array(
        'data' => $msg,
        'label' => 'label.messaggio_colloquio',
        'trim' => true,
        'required' => $msg_required))
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start']))
      ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end',
        'onclick' => "location.href='".$this->generateUrl('colloqui')."'"]))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      if (strstr($form->get('messaggio')->getData(), 'XX:XX') !== FALSE) {
        // errore nel messaggio
        $form->addError(new FormError($trans->trans('exception.colloquio_ora_invalida')));
      } else {
        // tutto ok
        $richiesta
          ->setStato($form->get('stato')->getData())
          ->setMessaggio($form->get('messaggio')->getData());
        // memorizza dati
        $em->flush();
        // log azione
        $dblogger->logAzione('COLLOQUI', 'Risposta a richiesta', array(
          'RichiestaColloquio' => $richiesta->getId(),
          'Stato' => $richiesta_old[0],
          'Messaggio' => $richiesta_old[1]));
        // redirezione
        return $this->redirectToRoute('colloqui');
      }
    }
    // mostra la pagina di risposta
    return $this->render('colloqui/colloqui_edit.html.twig', array(
      'pagina_titolo' => 'page.colloqui_edit',
      'form' => $form->createView(),
      'form_title' => 'title.risposta_colloqui',
      'label' => $label,
    ));
  }

  /**
   * Gestione dell'inserimento dei giorni di colloquio
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param LogHandler $dblogger Gestore dei log su database
   *
   * @return Response Pagina di risposta
   *
   * @Route("/colloqui/gestione/", name="colloqui_gestione",
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function gestioneAction(Request $request, EntityManagerInterface $em, TranslatorInterface $trans,
                                 RegistroUtil $reg, LogHandler $dblogger) {
    $docente = $this->getUser();
    // determina operazione da eseguire
    $colloquio = $em->getRepository('App\Entity\Colloquio')->findOneByDocente($docente);
    if ($colloquio) {
      // modalità modifica
      $edit = true;
      $codice = $colloquio->getDato('codice');
      $old_colloquio = clone $colloquio;
    } else {
      // modalità inserimento
      $edit = false;
      $codice = 'aula-'.str_replace([' ', '\'', 'à', 'è', 'é', 'ì', 'ò', 'ù'], ['', '', 'a', 'e', 'e', 'i', 'o', 'u'],
        mb_strtolower($docente->getCognome())).'-'.mb_strtolower(substr($docente->getNome(), 0, 1));
      $colloquio = (new Colloquio())
        ->addDato('codice', $codice)
        ->setFrequenza('4')
        ->setGiorno(1)
        ->setOra(1)
        ->setDocente($docente);
      $em->persist($colloquio);
    }
    // determina lista orari
    $ore = $em->getRepository('App\Entity\ScansioneOraria')->orarioGiorno($colloquio->getGiorno(), $colloquio->getOrario());
    $lista_ore = array();
    foreach ($ore as $o) {
      $opzione = $o['ora'].': '.$o['inizio']->format('H:i').' - '.$o['fine']->format('H:i');
      $lista_ore[$opzione] = $o['ora'];
    }
    // determina lista ore aggiuntive
    $lista_aggiuntiva = array();
    $oggi = new \DateTime('today');
    foreach ($colloquio->getExtra() as $k=>$o) {
      if (substr($k, 0, 4) == 'date') {
        $dt = \DateTime::createFromFormat('d/m/Y H:i', $o.' 00:00');
        if ($dt >= $oggi) {
          $kt = 'time'.substr($k, 4);
          $lista_aggiuntiva[$k] = $o;
          $lista_aggiuntiva[$kt] = $colloquio->getExtra()[$kt];
        }
      }
    }
    // form di inserimento
    $form = $this->createForm(ColloquioType::class, $colloquio, ['formMode' => 'noSede',
      'returnUrl' => $this->generateUrl('colloqui'), 'dati' => [$codice, $lista_ore, $lista_aggiuntiva]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // controlla date aggiuntive
      $lista_date = array();
      $dt = '';
      foreach ($form->get('extra')->getData() as $k=>$v) {
        if (substr($k, 0, 4) == 'date') {
          $dt = $v;
        } else {
          $dt_obj = \DateTime::createFromFormat('d/m/Y H:i', $dt.' '.$v);
          if ($dt_obj) {
            // data corretta
            $lista_date[$dt_obj->format('YmdHi')] = array($dt, $v, $dt_obj);
          }
          $dt = '';
        }
      }
      // ordina date
      ksort($lista_date);
      // controlla festivi
      $date_aggiuntive = array();
      $n = 0;
      foreach ($lista_date as $v) {
        $errore = $reg->controlloData($v[2], null);
        if ($errore) {
          // errore: festivo
          $form->addError(new FormError($trans->trans('exception.data_festiva')));
          break;
        }
        $date_aggiuntive['date'.$n] = $v[0];
        $date_aggiuntive['time'.$n] = $v[1];
        $n++;
      }
      if ($form->isValid()) {
        // ok: memorizza dati
        $colloquio->setExtra($date_aggiuntive);
        $colloquio->addDato('codice', $form->get('codice')->getData());
        $em->flush();
        // log azione
        if ($edit) {
          // azione modifica
          $dblogger->logAzione('COLLOQUI', 'Modifica colloquio', array(
            'Colloquio' => $colloquio->getId(),
            'Frequenza' => $old_colloquio->getFrequenza(),
            'Giorno' => $old_colloquio->getGiorno(),
            'Ora' => $old_colloquio->getOra(),
            'Extra' => $colloquio->getExtra(),
            'Dati' => $colloquio->getDati(),
            'Note' => $old_colloquio->getNote()));
        } else {
          // azione inserimento
          $dblogger->logAzione('COLLOQUI', 'Inserimento colloquio', array(
            'Colloquio' => $colloquio->getId()));
        }
        // redirezione
        return $this->redirectToRoute('colloqui');
      }
    }
    // mostra la pagina di risposta
    return $this->render('colloqui/gestione.html.twig', array(
      'pagina_titolo' => 'page.gestione_colloqui',
      'form' => $form->createView(),
      'form_title' => 'title.gestione_colloqui',
    ));
  }

  /**
   * Blocca/sblocca le richieste di colloauo segnalando che non ci sono/ci sono posti disponibili
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param LogHandler $dblogger Gestore dei log su database
   * @param string $appuntamento Data e ora dell'appuntamento da bloccare/sbloccare
   * @param boolean $blocca Vero per bloccare le richieste di colloquio, falso altrimenti
   *
   * @return Response Pagina di risposta
   *
   * @Route("/colloqui/blocca/{colloquio}/{appuntamento}/{blocca}", name="colloqui_blocca",
   *    requirements={"appuntamento": "\d+-\d+-\d+-\d+-\d+", "blocca": "0|1"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function bloccaAction(Request $request, EntityManagerInterface $em, LogHandler $dblogger,
                               Colloquio $colloquio, $appuntamento, $blocca) {
    // controlla richiesta
    $data = \DateTime::createFromFormat('Y-m-d-G-i', $appuntamento);
    $richiesta = $em->getRepository('App\Entity\RichiestaColloquio')->findOneBy(['colloquio' => $colloquio,
      'appuntamento' => $data, 'stato' => ($blocca ? 'C' : 'X')]);
    if (!$richiesta) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    if ($blocca) {
      // aggiunge blocco
      $richiesta = (new RichiestaColloquio())
        ->setColloquio($colloquio)
        ->setAppuntamento($richiesta->getAppuntamento())
        ->setDurata($richiesta->getDurata())
        ->setStato('X');
      $em->persist($richiesta);
    } else {
      // rimuove blocco
      $em->remove($richiesta);
    }
    // memorizza modifica
    $em->flush();
    // log azione
    $dblogger->logAzione('COLLOQUI', 'Blocca richieste', array(
      'Colloquio' => $richiesta->getColloquio()->getId(),
      'Appuntamento' => $richiesta->getAppuntamento()->format('d/m/Y G:i'),
      'Durata' => $richiesta->getDurata(),
      'Blocca' => $blocca));
    // redirezione
    return $this->redirectToRoute('colloqui');
  }

}
