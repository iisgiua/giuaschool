<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Controller;

use App\Entity\Annotazione;
use App\Entity\Avviso;
use App\Entity\AvvisoUtente;
use App\Entity\Firma;
use App\Entity\FirmaSostegno;
use App\Entity\Lezione;
use App\Entity\Nota;
use App\Form\MessageType;
use App\Message\AvvisoMessage;
use App\MessageHandler\NotificaMessageHandler;
use App\Util\BachecaUtil;
use App\Util\LogHandler;
use App\Util\RegistroUtil;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;


/**
 * RegistroController - gestione del registro
 *
 * @author Antonello Dessì
 */
class RegistroController extends BaseController
{

  /**
   * Gestione del registro delle lezioni
   *
   * @param Request $request Pagina richiesta
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param BachecaUtil $bac Funzioni di utilità per la gestione della bacheca
   * @param int $cattedra Identificativo della cattedra
   * @param int $classe Identificativo della classe (supplenza)
   * @param string $data Data del giorno da visualizzare (AAAA-MM-GG)
   * @param string $vista Tipo di vista del registro (giornaliera/mensile)
   *
   * @return Response Pagina di risposta
   *
   * @Route("/lezioni/registro/firme/{cattedra}/{classe}/{data}/{vista}", name="lezioni_registro_firme",
   *    requirements={"cattedra": "\d+", "classe": "\d+", "data": "\d\d\d\d-\d\d-\d\d", "vista": "G|M"},
   *    defaults={"cattedra": 0, "classe": 0, "data": "0000-00-00", "vista": "G"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function firmeAction(Request $request, RegistroUtil $reg, BachecaUtil $bac,
                              int $cattedra, int $classe, string $data, string $vista): Response {
    // inizializza variabili
    $lista_festivi = null;
    $errore = null;
    $dati = null;
    $num_avvisi = 0;
    $lista_circolari = array();
    $assenti = null;
    $settimana = ['Domenica', 'Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato'];
    $mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
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
        $dataObj = \DateTime::createFromFormat('Y-m-d', $this->reqstack->getSession()->get('/APP/DOCENTE/data_lezione'));
      } else {
        // imposta data odierna
        $dataObj = new \DateTime();
      }
    } else {
      // imposta data indicata e la memorizza in sessione
      $dataObj = \DateTime::createFromFormat('Y-m-d', $data);
      $this->reqstack->getSession()->set('/APP/DOCENTE/data_lezione', $data);
    }
    // data in formato stringa
    $formatter = new \IntlDateFormatter('it_IT', \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $info['data_label'] = $formatter->format($dataObj);
    // data inizio e fine vista
    if ($vista == 'M') {
      // vista mensile
      $data_inizio = \DateTime::createFromFormat('Y-m-d', $dataObj->format('Y-m-01'));
      $data_fine = clone $data_inizio;
      $data_fine->modify('last day of this month');
    } else {
      // vista giornaliera
      $data_inizio = $dataObj;
      $data_fine = $dataObj;
      $data_succ = null;
      $data_prec = null;
    }
    // controllo cattedra/supplenza
    if ($cattedra > 0) {
      // lezione in propria cattedra: controlla esistenza
      $cattedra = $this->em->getRepository('App\Entity\Cattedra')->findOneBy(['id' => $cattedra,
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
      $classe = $this->em->getRepository('App\Entity\Classe')->find($classe);
      if (!$classe) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $materia = $this->em->getRepository('App\Entity\Materia')->findOneByTipo('U');
      if (!$materia) {
        // errore
        throw $this->createNotFoundException('exception.invalid_params');
      }
      // informazioni necessarie
      $cattedra = null;
      $info['materia'] = $materia->getNomeBreve();
      $info['alunno'] = null;
    }
    if ($classe) {
      // data prec/succ
      $data_succ = (clone $data_fine);
      $data_succ = $this->em->getRepository('App\Entity\Festivita')->giornoSuccessivo($data_succ);
      $data_prec = (clone $data_inizio);
      $data_prec = $this->em->getRepository('App\Entity\Festivita')->giornoPrecedente($data_prec);
      // recupera festivi per calendario
      $lista_festivi = $reg->listaFestivi($classe->getSede());
      // controllo data
      $errore = $reg->controlloData($dataObj, $classe->getSede());
      if (!$errore) {
        // non festivo
        $oggi = new \DateTime();
        $adesso = $oggi->format('H:i');
        if ($oggi->format('w') != 0 &&
            $adesso >= $this->em->getRepository('App\Entity\ScansioneOraria')->inizioLezioni($oggi, $classe->getSede()) &&
            $adesso <= $this->em->getRepository('App\Entity\ScansioneOraria')->fineLezioni($oggi, $classe->getSede())) {
          // avvisi alla classe
          $num_avvisi = $bac->bachecaNumeroAvvisiAlunni($classe);
          $lista_circolari = $this->em->getRepository('App\Entity\Circolare')->listaCircolariClasse($classe);
        }
        // recupera dati
        $dati = $reg->tabellaFirmeVista($data_inizio, $data_fine, $this->getUser(), $classe, $cattedra);
        if ($vista == 'G') {
          // dati sugli assenti
          $assenti = $reg->listaAssenti($data_inizio, $classe);
        }
      }
    }
    // salva pagina visitata
    $route = ['name' => $request->get('_route'), 'param' => $request->get('_route_params')];
    $this->reqstack->getSession()->set('/APP/DOCENTE/menu_lezione', $route);
    // visualizza pagina
    return $this->render('lezioni/registro_firme_'.$vista.'.html.twig', array(
      'pagina_titolo' => 'page.lezioni_registro',
      'cattedra' => $cattedra,
      'classe' => $classe,
      'data' => $dataObj->format('Y-m-d'),
      'data_inizio' => $data_inizio->format('d/m/Y'),
      'data_fine' => $data_fine->format('d/m/Y'),
      'data_succ' => $data_succ,
      'data_prec' => $data_prec,
      'settimana' => $settimana,
      'mesi' => $mesi,
      'errore' => $errore,
      'lista_festivi' => $lista_festivi,
      'info' => $info,
      'dati' => $dati,
      'assenti' => $assenti,
      'avvisi' => $num_avvisi,
      'circolari' => $lista_circolari,
    ));
  }

  /**
   * Aggiunge firma e lezione al registro
   *
   * @param Request $request Pagina richiesta
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $cattedra Identificativo della cattedra (se nulla è supplenza)
   * @param int $classe Identificativo della classe
   * @param string $data Data del giorno
   * @param int $ora Ora di lezione del giorno
   *
   * @return Response Pagina di risposta
   *
   * @Route("/lezioni/registro/add/{cattedra}/{classe}/{data}/{ora}", name="lezioni_registro_add",
   *    requirements={"cattedra": "\d+", "classe": "\d+", "data": "\d\d\d\d-\d\d-\d\d", "ora": "\d+"},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function addAction(Request $request, RegistroUtil $reg, LogHandler $dblogger, int $cattedra,
                            int $classe, string $data, int $ora): Response {
    // inizializza
    $label = array();
    // controlla classe
    $classe = $this->em->getRepository('App\Entity\Classe')->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla cattedra
    if ($cattedra > 0) {
      // lezioni di una cattedra esistente
      $cattedra = $this->em->getRepository('App\Entity\Cattedra')->findOneBy(['id' => $cattedra,
        'docente' => $this->getUser(), 'classe' => $classe, 'attiva' => 1]);
      if (!$cattedra) {
        // errore: non esiste la cattedra
        throw $this->createNotFoundException('exception.invalid_params');
      }
      $materia = $cattedra->getMateria();
    } else {
      // supplenza
      $cattedra = null;
      $materia = $this->em->getRepository('App\Entity\Materia')->findOneByTipo('U');
      if (!$materia) {
        // errore: dati inconsistenti
        throw $this->createNotFoundException('exception.invalid_params');
      }
    }
    // controlla data
    $dataObj = \DateTime::createFromFormat('Y-m-d', $data);
    $errore = $reg->controlloData($dataObj, $classe->getSede());
    if ($errore) {
      // errore: festivo
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // legge lezioni e firme esistenti
    $docentiId = [];
    $firmeLezioni = [];
    $lezioni = $this->em->getRepository('App\Entity\Lezione')->createQueryBuilder('l')
      ->join('l.classe', 'c')
      ->where('l.data=:data AND l.ora=:ora AND c.anno=:anno AND c.sezione=:sezione')
      ->setParameters(['data' => $data, 'ora' => $ora, 'anno' => $classe->getAnno(),
        'sezione' => $classe->getSezione()])
      ->orderBy('l.gruppo')
      ->getQuery()
      ->getResult();
    foreach ($lezioni as $lezione) {
      // legge firme
      $gruppo = $lezione->getTipoGruppo().':'.$lezione->getGruppo();
      $firme = $this->em->getRepository('App\Entity\Firma')->createQueryBuilder('f')
        ->join('f.docente', 'd')
        ->where('f.lezione=:lezione')
        ->setParameters(['lezione' => $lezione])
        ->getQuery()
        ->getResult();
      // docenti
      $firmeLezioni[$gruppo] = $firme;
      foreach ($firme as $f) {
        $docentiId[$gruppo][] = $f->getDocente()->getId();
      }
    }
    // controlla permessi
    if (!$reg->azioneLezione('add', $dataObj, $this->getUser(), $classe, $docentiId)) {
      // errore: azione non permessa
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // controlla nuova lezione
    $controllo = $reg->controllaNuovaLezione($cattedra, $this->getUser(), $classe, $materia, $dataObj,
      $ora, $lezioni, $firmeLezioni);
    if (!empty($controllo['errore'])) {
      // mostra messaggio di errore
      $this->addFlash('danger', $controllo['errore']);
      return $this->redirectToRoute('lezioni_registro_firme');
    }
    // dati in formato stringa
    $formatter = new \IntlDateFormatter('it_IT', \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $label['data'] = $formatter->format($dataObj);
    $label['docente'] = $this->getUser()->getNome().' '.$this->getUser()->getCognome();
    $label['classe'] = ''.$classe;
    $label['materia'] = $materia->getNomeBreve();
    if ($cattedra && $materia->getTipo() == 'S' && $cattedra->getAlunno()) {
      // sostegno
      $label['materia'] .= ' ('.$cattedra->getAlunno()->getCognome().' '.$cattedra->getAlunno()->getNome().')';
    }
    $dati = $reg->lezioneOreConsecutive($dataObj, $ora, $this->getUser(), $classe, $materia);
    $label['inizio'] = $dati['inizio'];
    $oraFine = $dati['fine'];
    // form di inserimento
    $form = $this->container->get('form.factory')->createNamedBuilder('registro_add', FormType::class)
      ->add('fine', ChoiceType::class, array('label' => 'label.ora_fine',
        'choices'  => $oraFine,
        'translation_domain' => false,
        'required' => true));
    if (empty($classe->getGruppo()) && !$cattedra) {
      // area comune: supplenza su gruppi religione
      $opzioni = $controllo['supplenza'];
      $form = $form
        ->add('tipoSupplenza', ChoiceType::class, ['label' => 'label.tipo_supplenza',
          'data' => in_array('T', $opzioni, true) ? 'T' : null,
          'choices' => $opzioni,
          'expanded' => true,
          'label_attr' => ['class' => 'radio-inline col-sm-2'],
          'required' => true]);
    }
    $form = $form
      ->add('argomento', MessageType::class, array(
        'label' => ($materia->getTipo() == 'S' ? 'label.argomenti_sostegno' : 'label.argomenti'),
        'data' => empty($controllo['compresenza']) ? '' : $controllo['compresenza']->getArgomento(),
        'trim' => true,
        'required' => false))
      ->add('attivita', MessageType::class, array(
        'label' => ($materia->getTipo() == 'S' ? 'label.attivita_sostegno' : 'label.attivita'),
        'data' => empty($controllo['compresenza']) ? '' : $controllo['compresenza']->getAttivita(),
        'trim' => true,
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start']))
      ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end', 'onclick' => "location.href='".$this->generateUrl('lezioni_registro_firme')."'"]))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // legge gruppo e tipo
      if (!$cattedra && empty($classe->getGruppo())) {
        // supplenza
        $tipoGruppo = $form->get('tipoSupplenza')->getData() == 'T' ? 'N' : 'R';
        $gruppo = $tipoGruppo == 'N' ? '' : $form->get('tipoSupplenza')->getData();
      } elseif ($cattedra && $cattedra->getMateria()->getTipo() == 'R') {
        // religione
        $tipoGruppo = 'R';
        $gruppo = $cattedra->getTipo() == 'N' ? 'S' : 'A';
      } else {
        // altro
        $tipoGruppo = $classe->getGruppo() ? 'C' : 'N';
        $gruppo = $classe->getGruppo() ?? '';
      }
      // modifica lezioni esistenti
      $trasformazione = $reg->trasformaNuovaLezione($cattedra, $materia, $tipoGruppo, $gruppo,
        $controllo, $lezioni, $firmeLezioni);
      // ciclo per ore successive
      for ($numOra = $ora; $numOra <= $form->get('fine')->getData(); $numOra++) {
        if ($numOra > $ora || empty($trasformazione['lezione'])) {
          // nuova lezione
          $lezione = (new Lezione())
            ->setData($dataObj)
            ->setOra($numOra)
            ->setClasse($classe)
            ->setGruppo($gruppo)
            ->setTipoGruppo($tipoGruppo)
            ->setMateria($materia);
          if ($materia->getTipo() == 'S') {
            // nuova lezione di sostegno: sempre senza gruppi
            $classeComune = $classe;
            if (!empty($classe->getGruppo())) {
              $classeComune = $this->em->getRepository('App\Entity\Classe')->createQueryBuilder('c')
                ->where("c.anno=:anno AND c.sezione=:sezione AND (c.gruppo='' OR c.gruppo IS NULL)")
                ->setParameters(['anno' => $classe->getAnno(), 'sezione' => $classe->getSezione()])
                ->getQuery()
                ->setMaxResults(1)
                ->getOneOrNullResult();
            }
            $lezione
              ->setClasse($classeComune)
              ->setGruppo('')
              ->setTipoGruppo('N');
          } else {
            // lezione curricolare: aggiunge argomenti/attività
            $lezione
              ->setArgomento($form->get('argomento')->getData())
              ->setAttivita($form->get('attivita')->getData());
          }
          $this->em->persist($lezione);
          if ($numOra == $ora && !empty($trasformazione['modifica'])) {
            foreach ($trasformazione['modifica'] as $prop => $val) {
              $lezione->{'set'.$prop}($val);
            }
          }
          $trasformazione['log']['crea'][] = $lezione;
        } elseif ($numOra == $ora) {
          // lezione modificata da firmare
          $lezione = $trasformazione['lezione'];
          if ($materia->getTipo() != 'S') {
            // modifica argomento/attività
            $logModifica = clone $lezione;
            if (!empty($trasformazione['log']['modifica'])) {
              foreach ($trasformazione['log']['modifica'] as $ogg) {
                if ($ogg instanceOf Lezione) {
                  $logModifica = null;
                  break;
                }
              }
            }
            $lezione->setArgomento($form->get('argomento')->getData());
            $lezione->setAttivita($form->get('attivita')->getData());
            if ($logModifica) {
              $trasformazione['log']['modifica'][] = [$logModifica, $lezione];
            }
          }
        }
        // crea firma
        if ($materia->getTipo() == 'S') {
          // sostegno
          $firma = (new FirmaSostegno())
            ->setLezione($lezione)
            ->setDocente($this->getUser())
            ->setAlunno($cattedra->getAlunno())
            ->setArgomento($form->get('argomento')->getData())
            ->setAttivita($form->get('attivita')->getData());
        } else {
          // lezione curricolare
          $firma = (new Firma())
            ->setLezione($lezione)
            ->setDocente($this->getUser());
        }
        $this->em->persist($firma);
        $trasformazione['log']['crea'][] = $firma;
        // ok: memorizza dati
        $this->em->flush();
        // ricalcola ore assenza
        if ($numOra > $ora || empty($trasformazione['lezione'])) {
          // assenze di lezione aggiunta
          $reg->ricalcolaOreLezione($dataObj, $lezione);
        } elseif ($numOra == $ora && !empty($trasformazione['assenze'])) {
          // assenze di lezioni modificate
          foreach ($trasformazione['assenze'] as $assenza) {
            $reg->ricalcolaOreLezione($dataObj, $assenza);
          }
        }
        // log azione
        $dblogger->logAzione('REGISTRO', 'Crea Lezione');
        foreach ($trasformazione['log']['crea'] as $ogg) {
          $dblogger->logCreazione('REGISTRO',
            'Crea '.(($ogg instanceof Lezione) ? 'lezione' : 'firma'), $ogg);
        }
        foreach (($trasformazione['log']['modifica'] ?? []) as $ogg) {
          $dblogger->logModifica('REGISTRO',
            'Modifica '.(($ogg[0] instanceof Lezione) ? 'lezione' : 'firma'), $ogg[0], $ogg[1]);
        }
        $trasformazione['log'] = [];
      }
      // ok, redirezione
      return $this->redirectToRoute('lezioni_registro_firme');
    }
    // mostra la pagina di risposta
    return $this->render('lezioni/registro_add.html.twig', array(
      'pagina_titolo' => 'page.lezioni_registro',
      'form' => $form->createView(),
      'form_title' => 'title.nuova_lezione',
      'label' => $label,
    ));
  }

  /**
   * Modifica firma e lezione del registro
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $cattedra Identificativo della cattedra (se nulla è supplenza)
   * @param int $classe Identificativo della classe
   * @param string $data Data del giorno
   * @param int $ora Ora di lezione del giorno
   *
   * @return Response Pagina di risposta
   *
   * @Route("/lezioni/registro/edit/{cattedra}/{classe}/{data}/{ora}", name="lezioni_registro_edit",
   *    requirements={"cattedra": "\d+", "classe": "\d+", "data": "\d\d\d\d-\d\d-\d\d", "ora": "\d+"},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function editAction(Request $request, TranslatorInterface $trans, RegistroUtil $reg,
                             LogHandler $dblogger, int $cattedra, int $classe, string $data,
                             int $ora): Response {
    // inizializza
    $label = array();
    // controlla classe
    $classe = $this->em->getRepository('App\Entity\Classe')->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla cattedra
    if ($cattedra > 0) {
      // lezioni di una cattedra esistente
      $cattedra = $this->em->getRepository('App\Entity\Cattedra')->findOneBy(['id' => $cattedra,
        'docente' => $this->getUser(), 'classe' => $classe, 'attiva' => 1]);
      if (!$cattedra) {
        // errore: non esiste la cattedra
        throw $this->createNotFoundException('exception.invalid_params');
      }
      $materia = $cattedra->getMateria();
    } else {
      // supplenza
      $cattedra = null;
      $materia = $this->em->getRepository('App\Entity\Materia')->findOneByTipo('U');
      if (!$materia) {
        // errore: dati inconsistenti
        throw $this->createNotFoundException('exception.invalid_params');
      }
    }
    // controlla data
    $dataObj = \DateTime::createFromFormat('Y-m-d', $data);
    $errore = $reg->controlloData($dataObj, $classe->getSede());
    if ($errore) {
      // errore: festivo
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // legge lezioni e firme esistenti
    $firmaDocente = null;
    $lezioneDocente = null;
    $tipoLezione = null;
    $docentiId = [];
    $firmeLezioni = [];
    $lezioni = $this->em->getRepository('App\Entity\Lezione')->createQueryBuilder('l')
      ->join('l.classe', 'c')
      ->where('l.data=:data AND l.ora=:ora AND c.anno=:anno AND c.sezione=:sezione')
      ->setParameters(['data' => $data, 'ora' => $ora, 'anno' => $classe->getAnno(),
        'sezione' => $classe->getSezione()])
      ->orderBy('l.gruppo')
      ->getQuery()
      ->getResult();
    foreach ($lezioni as $lezione) {
      // legge firme
      $gruppo = $lezione->getTipoGruppo().':'.$lezione->getGruppo();
      $firme = $this->em->getRepository('App\Entity\Firma')->createQueryBuilder('f')
        ->join('f.docente', 'd')
        ->where('f.lezione=:lezione')
        ->setParameters(['lezione' => $lezione])
        ->getQuery()
        ->getResult();
      // docenti
      $firmeLezioni[$gruppo] = $firme;
      foreach ($firme as $firma) {
        $docentiId[$gruppo][] = $firma->getDocente()->getId();
        if ($this->getUser()->getId() == $firma->getDocente()->getId()) {
          // lezione firmata dal docente
          $firmaDocente = $firma;
          $lezioneDocente = $firma->getLezione();
          $tipoLezione = $gruppo;
        }
      }
    }
    // controlla esistenza di lezione/firma
    if (empty($lezioni) || empty($firmaDocente) || empty($lezioneDocente) || empty($tipoLezione)) {
      // errore: lezione/firma non esiste
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // controlla permessi
    if (!$reg->azioneLezione('edit', $dataObj, $this->getUser(), $classe, $docentiId)) {
      // errore: azione non permessa
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // dati in formato stringa
    $formatter = new \IntlDateFormatter('it_IT', \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $label['data'] = $formatter->format($dataObj);
    $label['docente'] = $this->getUser()->getNome().' '.$this->getUser()->getCognome();
    $label['classe'] = ''.$lezioneDocente->getClasse();
    $label['materia'] = $lezioneDocente->getMateria()->getNomeBreve();
    $label['ora'] = $lezioneDocente->getOra();
    if ($cattedra && $materia->getTipo() == 'S' && $cattedra->getAlunno()) {
      // sostegno
      $label['materia'] .= ' ('.$cattedra->getAlunno()->getCognome().' '.$cattedra->getAlunno()->getNome().')';
    }
    // form di modifica
    $altreMaterie = [];
    $altriGruppi = [];
    $form = $this->container->get('form.factory')->createNamedBuilder('registro_edit', FormType::class);
    if (($firmaDocente instanceOf FirmaSostegno) && empty($firmaDocente->getAlunno())) {
      // permette cambio gruppo
      $tipoGruppo = $lezioneDocente->getTipoGruppo();
      if ($tipoGruppo == 'C') {
        // gruppi classe
        $lista = $this->em->getRepository('App\Entity\Classe')->gruppi($classe, false);
        foreach ($lista as $gruppo) {
          $altriGruppi[$classe->getAnno().$classe->getSezione().'-'.$gruppo] = $gruppo;
        }
        $form = $form
          ->add('gruppo', ChoiceType::class, array('label' => 'label.classe_gruppo',
            'data' => $lezioneDocente->getGruppo(),
            'choices' => $altriGruppi,
            'expanded' => true,
            'choice_translation_domain' => false,
            'label_attr' => ['class' => 'radio-inline col-sm-2'],
            'required' => true));
      } elseif ($tipoGruppo == 'R') {
        // gruppi religione
        $cattedreReligione = $this->em->getRepository('App\Entity\Cattedra')->createQueryBuilder('c')
          ->select('DISTINCT c.tipo')
          ->join('c.materia', 'm')
          ->where("c.attiva=1 AND m.tipo='R' AND c.classe=:classe")
          ->setParameters(['classe' => $classe])
          ->getQuery()
          ->getSingleColumnResult();
        if (in_array('N', $cattedreReligione, true)) {
          // inserisce gruppo religione
          $altriGruppi['label.gruppo_religione_S'] = 'S';
        }
        if (in_array('A', $cattedreReligione, true)) {
          // inserisce gruppo religione
          $altriGruppi['label.gruppo_religione_A'] = 'A';
        }
        $form = $form
          ->add('gruppo', ChoiceType::class, array('label' => 'label.classe_gruppo',
            'data' => $lezioneDocente->getGruppo(),
            'choices' => $altriGruppi,
            'expanded' => true,
            'choice_translation_domain' => true,
            'label_attr' => ['class' => 'radio-inline col-sm-2'],
            'required' => true));
      }
    } elseif (in_array($lezioneDocente->getMateria()->getTipo(), ['N', 'E'], true)) {
      // permette cambio materia
      $altreMaterie = $this->em->getRepository('App\Entity\Cattedra')->altreMaterie($this->getUser(),
        $lezioneDocente->getClasse(), $lezioneDocente->getMateria(), $firmeLezioni[$tipoLezione]);
      if (count($altreMaterie['cattedre']) > 1) {
        $form = $form
          ->add('materia', ChoiceType::class, array('label' => 'label.materia',
            'data' => $altreMaterie['selezionato'],
            'choices' => $altreMaterie['cattedre'],
            'choice_translation_domain' => false,
            'disabled' => false,
            'required' => true));
      }
    }
    $form = $form
      ->add('argomenti', MessageType::class, array(
        'label' => ($firmaDocente instanceOf FirmaSostegno) ? 'label.argomenti_sostegno' : 'label.argomenti',
        'data' => ($firmaDocente instanceOf FirmaSostegno) ? $firmaDocente->getArgomento() : $lezioneDocente->getArgomento(),
        'trim' => true,
        'required' => false))
      ->add('attivita', MessageType::class, array(
        'label' => ($firmaDocente instanceOf FirmaSostegno) ? 'label.attivita_sostegno' : 'label.attivita',
        'data' => ($firmaDocente instanceOf FirmaSostegno) ? $firmaDocente->getAttivita() : $lezioneDocente->getAttivita(),
        'trim' => true,
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start']))
      ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end', 'onclick' => "location.href='".$this->generateUrl('lezioni_registro_firme')."'"]))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      $vecchiaLezione = clone $lezioneDocente;
      $vecchiaFirma = clone $firmaDocente;
      // modifica argomento/attività
      if ($firmaDocente instanceOf FirmaSostegno) {
        $firmaDocente
          ->setArgomento($form->get('argomenti')->getData())
          ->setAttivita($form->get('attivita')->getData());
        $log['modifica'] = [$vecchiaFirma, $firmaDocente];
      } else {
        $lezioneDocente
          ->setArgomento($form->get('argomenti')->getData())
          ->setAttivita($form->get('attivita')->getData());
        $log['modifica'] = [$vecchiaLezione, $lezioneDocente];
      }
      // altre modifiche
      if (count($altreMaterie['cattedre']) > 1) {
        // materie diverse su cattedre curricolari (escluso religione)
        $cattedra = $this->em->getRepository('App\Entity\Cattedra')->find($form->get('materia')->getData());
        $materia = $cattedra->getMateria();
        if ($materia->getId() != $lezioneDocente->getMateria()->getId()) {
          // controlla voti
          $voti = $this->em->getRepository('App\Entity\Valutazione')->findBy(['lezione' => $lezioneDocente,
            'docente' => $this->getUser()]);
          if (count($voti) > 0) {
            // altra lezione (stessa data/classe/gruppo/materia)
            $altraLezione = $this->em->getRepository('App\Entity\Lezione')->createQueryBuilder('l')
              ->join('App\Entity\Firma', 'f', 'WITH', 'l.id=f.lezione')
              ->where('l.id!=:id AND l.data=:data AND l.classe=:classe AND l.gruppo=:gruppo AND l.tipoGruppo=:tipoGruppo AND l.materia=:materia AND f.docente=:docente')
              ->setParameters(['id' => $lezioneDocente, 'data' => $data, 'classe' => $classe,
                'gruppo' => $lezioneDocente->getGruppo(), 'tipoGruppo' => $lezioneDocente->getTipoGruppo(),
                'materia' => $lezioneDocente->getMateria(), 'docente' => $this->getUser()])
              ->setMaxResults(1)
              ->getQuery()
              ->getOneOrNullResult();
            if (!$altraLezione) {
              // errore: voti presenti
              $this->addFlash('danger', $trans->trans('message.modifica_lezione_con_voti'));
              return $this->redirectToRoute('lezioni_registro_firme');
            }
            foreach ($voti as $v) {
              // sposta voti su altra lezione
              $v->setLezione($altraLezione);
            }
          }
          // modifica materia
          $lezioneDocente->setMateria($materia);
        }
      } elseif (count($altriGruppi) > 1 &&
                $lezioneDocente->getGruppo() != $form->get('gruppo')->getData()) {
        // cambio gruppo
        $nuovoGruppo = $form->get('gruppo')->getData();
        if (count($firmeLezioni[$tipoGruppo.':'.$lezioneDocente->getGruppo()]) == 1) {
          // unica firma: elimina lezione
          $this->em->remove($lezioneDocente);
          $log['cancella'] = $vecchiaLezione;
        }
        if (empty($firmeLezioni[$tipoGruppo.':'.$nuovoGruppo])) {
          // gruppo non esiste: crea lezione
          $nuovaLezione = clone $lezioneDocente;
          $sostegno = $this->em->getRepository('App\Entity\Materia')->findOneBy(['tipo' => 'S']);
          $nuovaLezione->setMateria($sostegno)->setGruppo($nuovoGruppo)->setArgomento('')->setAttivita('');
          if ($tipoGruppo == 'C') {
            // cambio classe
            $nuovaClasse = $this->em->getRepository('App\Entity\Classe')->findOneBy([
              'anno' => $classe->getAnno(), 'sezione' => $classe->getSezione(), 'gruppo' => $nuovoGruppo]);
            $nuovaLezione->setClasse($nuovaClasse);
          }
          $this->em->persist($nuovaLezione);
          $log['crea'] = $nuovaLezione;
        } else {
          // gruppo esiste
          $nuovaLezione = $firmeLezioni[$tipoGruppo.':'.$nuovoGruppo][0]->getLezione();
        }
        // modifica firma
        $firmaDocente->setlezione($nuovaLezione);
      }
      // elimina ore assenze
      if (!empty($log['cancella'])) {
        $this->em->getRepository('App\Entity\AssenzaLezione')->createQueryBuilder('al')
          ->delete()
          ->where('al.lezione=:lezione')
          ->setParameters(['lezione' => $log['cancella']->getId()])
          ->getQuery()
          ->execute();
      }
      // ok: memorizza dati
      $this->em->flush();
      // ricalcola assenze di lezione
      if (!empty($log['crea'])) {
        $reg->ricalcolaOreLezione($dataObj, $log['crea']);
      }
      // log azione
      $dblogger->logAzione('REGISTRO', 'Modifica Lezione');
      $dblogger->logModifica('REGISTRO',
        'Modifica ' . (($log['modifica'][0] instanceOf Lezione) ? 'lezione' : 'firma'),
        $log['modifica'][0], $log['modifica'][1]);
      if (!empty($log['cancella'])) {
        $dblogger->logRimozione('REGISTRO', 'Cancella lezione', $log['cancella']);
      }
      if (!empty($log['crea'])) {
        $dblogger->logCreazione('REGISTRO', 'Crea lezione', $log['crea']);
      }
      // redirezione
      return $this->redirectToRoute('lezioni_registro_firme');
    }
    // mostra la pagina di risposta
    return $this->render('lezioni/registro_edit.html.twig', array(
      'pagina_titolo' => 'page.lezioni_registro',
      'form' => $form->createView(),
      'form_title' => 'title.modifica_lezione',
      'label' => $label,
    ));
  }

  /**
   * Cancella firma e lezione dal registro
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $classe Identificativo della classe
   * @param string $data Data del giorno
   * @param int $ora Ora di lezione del giorno
   *
   * @return Response Pagina di risposta
   *
   * @Route("/lezioni/registro/delete/{classe}/{data}/{ora}", name="lezioni_registro_delete",
   *    requirements={"classe": "\d+", "data": "\d\d\d\d-\d\d-\d\d", "ora": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function deleteAction(Request $request, TranslatorInterface $trans, RegistroUtil $reg,
                               LogHandler $dblogger, int $classe, string $data, int $ora): Response {
    // controlla classe
    $classe = $this->em->getRepository('App\Entity\Classe')->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla data
    $dataObj = \DateTime::createFromFormat('Y-m-d', $data);
    $errore = $reg->controlloData($dataObj, $classe->getSede());
    if ($errore) {
      // errore: festivo
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // legge lezioni e firme esistenti
    $firmaDocente = null;
    $lezioneDocente = null;
    $firmeSostegno = 0;
    $firmeNoSostegno = 0;
    $docentiId = [];
    $firmeLezioni = [];
    $lezioni = $this->em->getRepository('App\Entity\Lezione')->createQueryBuilder('l')
      ->join('l.classe', 'c')
      ->where('l.data=:data AND l.ora=:ora AND c.anno=:anno AND c.sezione=:sezione')
      ->setParameters(['data' => $data, 'ora' => $ora, 'anno' => $classe->getAnno(),
        'sezione' => $classe->getSezione()])
      ->orderBy('l.gruppo')
      ->getQuery()
      ->getResult();
    foreach ($lezioni as $lezione) {
      // legge firme
      $gruppo = $lezione->getTipoGruppo().':'.$lezione->getGruppo();
      $firme = $this->em->getRepository('App\Entity\Firma')->createQueryBuilder('f')
        ->join('f.docente', 'd')
        ->where('f.lezione=:lezione')
        ->setParameters(['lezione' => $lezione])
        ->getQuery()
        ->getResult();
      // docenti
      $firmeLezioni[$gruppo] = $firme;
      foreach ($firme as $firma) {
        $docentiId[$gruppo][] = $firma->getDocente()->getId();
        if ($this->getUser()->getId() == $firma->getDocente()->getId()) {
          // lezione firmata dal docente
          $firmaDocente = $firma;
          $lezioneDocente = $firma->getLezione();
        } elseif ($firma instanceOf FirmaSostegno) {
          $firmeSostegno++;
        } else {
          $firmeNoSostegno++;
        }
      }
    }
    // controlla esistenza di lezione/firma
    if (empty($lezioni) || empty($firmaDocente) || empty($lezioneDocente)) {
      // errore: lezione/firma non esiste
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // controlla permessi
    if (!$reg->azioneLezione('delete', $dataObj, $this->getUser(), $classe, $docentiId)) {
      // errore: azione non permessa
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // controlla voti
    $voti = $this->em->getRepository('App\Entity\Valutazione')->findBy(['lezione' => $lezioneDocente,
      'docente' => $this->getUser()]);
    if (count($voti) > 0) {
      // altra lezione (stessa data/classe/gruppo/materia)
      $altraLezione = $this->em->getRepository('App\Entity\Lezione')->createQueryBuilder('l')
        ->join('App\Entity\Firma', 'f', 'WITH', 'l.id=f.lezione')
        ->where('l.id!=:id AND l.data=:data AND l.classe=:classe AND l.gruppo=:gruppo AND l.tipoGruppo=:tipoGruppo AND l.materia=:materia AND f.docente=:docente')
        ->setParameters(['id' => $lezioneDocente, 'data' => $data, 'classe' => $classe,
          'gruppo' => $lezioneDocente->getGruppo(), 'tipoGruppo' => $lezioneDocente->getTipoGruppo(),
          'materia' => $lezioneDocente->getMateria(), 'docente' => $this->getUser()])
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();
      if (!$altraLezione) {
        // errore: voti presenti
        $this->addFlash('danger', $trans->trans('message.cancella_lezione_con_voti'));
        return $this->redirectToRoute('lezioni_registro_firme');
      }
      foreach ($voti as $v) {
        // sposta voti su altra lezione
        $v->setLezione($altraLezione);
      }
    }
    // imposta tipo lezione cancellata
    $tipoLezione = $lezioneDocente->getTipoGruppo().':'.$lezioneDocente->getGruppo();
    // cancella firma
    $log['cancella'][] = (clone $firmaDocente->setLezione(clone $lezioneDocente));
    $this->em->remove($firmaDocente);
    if (($firmeSostegno + $firmeNoSostegno) == 0) {
      // unica firma presente: cancella lezione
      $log['cancella'][] = clone $lezioneDocente;
      $this->em->remove($lezioneDocente);
    } elseif ($firmaDocente instanceOf FirmaSostegno) {
      // altre firme presenti e firma da cancellare di sostegno
      if ($tipoLezione[0] != 'N' && count($firmeLezioni[$tipoLezione]) == 1) {
        // lezioni su gruppi e firma da cancellare unica in gruppo: cancella lezione
        $log['cancella'][] = clone $lezioneDocente;
        $this->em->remove($lezioneDocente);
      }
    } else {
      // altre firme presenti e firma da cancellare curricolare
      $sostegno = $this->em->getRepository('App\Entity\Materia')->findOneByTipo('S');
      if ($tipoLezione == 'N:') {
        // niente gruppi
        if ($firmeNoSostegno == 0) {
          // solo firme sostegno: modifica lezione in sostegno
          $vecchiaLezione = clone $lezioneDocente;
          $lezioneDocente->setMateria($sostegno)->setArgomento('')->setAttivita('');
          $log['modifica'][] = [$vecchiaLezione, $lezioneDocente];
        }
      } else {
        // lezioni su gruppi
        if ($firmeNoSostegno > 0 && count($firmeLezioni[$tipoLezione]) == 1) {
          // firma da cancellare unica in gruppo: cancella lezione
          $log['cancella'][] = clone $lezioneDocente;
          $this->em->remove($lezioneDocente);
        } elseif ($firmeNoSostegno > 0) {
          // controlla se nel gruppo è rimasto solo sostegno
          $soloSostegno = true;
          foreach ($firmeLezioni[$tipoLezione] as $firma) {
            if (!($firma instanceOf FirmaSostegno) && $firma->getId() != $firmaDocente->getId()) {
              $soloSostegno = false;
              break;
            }
          }
          if ($soloSostegno) {
            // gruppo con solo sostegno: modifica lezione
            $vecchiaLezione = clone $lezioneDocente;
            $lezioneDocente->setMateria($sostegno)->setArgomento('')->setAttivita('');
            $log['modifica'][] = [$vecchiaLezione, $lezioneDocente];
          }
        } elseif ($firmeNoSostegno == 0) {
          // solo firme sostegno: modifica lezione in sostegno
          $vecchiaLezione = clone $lezioneDocente;
          $nuovaClasse = $this->em->getRepository('App\Entity\Classe')->createQueryBuilder('c')
            ->where("c.anno=:anno AND c.sezione=:sezione AND (c.gruppo='' OR c.gruppo IS NULL)")
            ->setParameters(['anno' => $classe->getAnno(), 'sezione' => $classe->getSezione()])
            ->getQuery()
            ->setMaxResults(1)
            ->getOneOrNullResult();
          $lezioneDocente->setClasse($nuovaClasse)->setTipoGruppo('N')->setGruppo('')
            ->setMateria($sostegno)->setArgomento('')->setAttivita('');
          $log['modifica'][] = [$vecchiaLezione, $lezioneDocente];
          // rimuove assenti
          $assenti = true;
          $this->em->getRepository('App\Entity\AssenzaLezione')->createQueryBuilder('al')
            ->delete()
            ->where('al.lezione IN (:lezioni)')
            ->setParameters(['lezioni' => array_map(fn($l) => $l->getId(), $lezioni)])
            ->getQuery()
            ->execute();
          // cancella altri gruppi
          foreach ($firmeLezioni as $tipoGruppo => $firme) {
            if ($tipoGruppo != $tipoLezione) {
              $vecchiaLezione = $firme[0]->getlezione();
              foreach ($firme as $firma) {
                $vecchiaFirma = (clone $firma)->setLezione(clone $vecchiaLezione);
                $firma->setLezione($lezioneDocente);
                $log['modifica'][] = [$vecchiaFirma, $firma];
              }
              $log['cancella'][] = clone $vecchiaLezione;
              $this->em->remove($vecchiaLezione);
            }
          }
        }
      }
    }
    // cancella assenti da lezione
    foreach (array_filter($log['cancella'], fn($o) => ($o instanceOf Lezione)) as $lezione) {
      $this->em->getRepository('App\Entity\AssenzaLezione')->createQueryBuilder('al')
        ->delete()
        ->where('al.lezione=:lezione')
        ->setParameters(['lezione' => $lezione->getId()])
        ->getQuery()
        ->execute();
    }
    // ok: memorizza dati
    $this->em->flush();
    if (!empty($assenti)) {
      // ricalcola assenze di lezione
      $reg->ricalcolaOreLezione($dataObj, $lezioneDocente);
    }
    // log azione
    $dblogger->logAzione('REGISTRO', 'Cancella Lezione');
    foreach ($log['cancella'] as $ogg) {
      $dblogger->logRimozione('REGISTRO',
        'Cancella ' . (($ogg instanceOf Lezione) ? 'lezione' : 'firma'), $ogg);
    }
    foreach (($log['modifica'] ?? []) as $ogg) {
      $dblogger->logModifica('REGISTRO',
        'Modifica ' . (($ogg[0] instanceOf Lezione) ? 'lezione' : 'firma'), $ogg[0], $ogg[1]);
    }
    // redirezione
    return $this->redirectToRoute('lezioni_registro_firme');
  }

  /**
   * Aggiunge o modifica una annotazione al registro
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param MessageBusInterface $msg Gestione delle notifiche
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param BachecaUtil $bac Funzioni di utilità per la gestione della bacheca
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $classe Identificativo della classe
   * @param string $data Data del giorno
   * @param int $id Identificativo della annotazione (se nullo aggiunge)
   *
   * @return Response Pagina di risposta
   *
   * @Route("/lezioni/registro/annotazione/edit/{classe}/{data}/{id}", name="lezioni_registro_annotazione_edit",
   *    requirements={"classe": "\d+", "data": "\d\d\d\d-\d\d-\d\d", "id": "\d+"},
   *    defaults={"id": 0},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function annotazioneEditAction(Request $request, TranslatorInterface $trans,
                                        MessageBusInterface $msg, RegistroUtil $reg, BachecaUtil $bac,
                                        LogHandler $dblogger, int $classe, string $data,
                                        int $id): Response {
    // inizializza
    $label = array();
    $dest_filtro = [];
    // controlla classe
    $classe = $this->em->getRepository('App\Entity\Classe')->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla data
    $dataObj = \DateTime::createFromFormat('Y-m-d', $data);
    $errore = $reg->controlloData($dataObj, $classe->getSede());
    if ($errore) {
      // errore: festivo
      throw $this->createNotFoundException('exception.invalid_params');
    }
    if ($id > 0) {
      // azione edit, controlla annotazione
      $annotazione = $this->em->getRepository('App\Entity\Annotazione')->findOneBy(['id' => $id,
        'data' => $dataObj, 'classe' => $classe]);
      if (!$annotazione) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $annotazione_old = clone $annotazione;
      if ($annotazione->getAvviso()) {
        $dest_filtro = $annotazione->getAvviso()->getFiltro();
      }
    } else {
      // azione add
      $annotazione = (new Annotazione())
        ->setData($dataObj)
        ->setClasse($classe)
        ->setVisibile(false);
      $this->em->persist($annotazione);
    }
    // imposta autore dell'annotazione
    $annotazione->setDocente($this->getUser());
    // controlla permessi
    if (!$reg->azioneAnnotazione(($id > 0 ? 'edit' : 'add'), $dataObj, $this->getUser(), $classe, ($id > 0 ? $annotazione : null))) {
      // errore: azione non permessa
      throw $this->createNotFoundException('exception.not_allowed');
    }
    if ($annotazione->getAvviso() && !$annotazione->getVisibile()) {
      // errore: creato da gestione avvisi (staff/coordinatore)
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // dati in formato stringa
    $formatter = new \IntlDateFormatter('it_IT', \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $label['data'] = $formatter->format($dataObj);
    $label['docente'] = $this->getUser()->getNome().' '.$this->getUser()->getCognome();
    $label['classe'] = ''.$classe;
    // lista alunni della classe
    $listaAlunni = $reg->alunniInData(new \DateTime(), $classe);
    // opzione scelta filtro
    $alunni = array();
    if (!empty($dest_filtro)) {
      foreach ($dest_filtro as $id) {
        $alunni[] = $this->em->getRepository('App\Entity\Alunno')->find($id);
      }
    }
    // form di inserimento
    $form = $this->container->get('form.factory')->createNamedBuilder('annotazione_edit', FormType::class, $annotazione)
      ->add('testo', MessageType::class, array(
        'label' => 'label.testo',
        'trim' => true,
        'required' => true))
      ->add('visibile', ChoiceType::class, array('label' => false,
        'choices' => ['label.si' => true, 'label.no' => false],
        'expanded' => true,
        'multiple' => false,
        'label_attr' => ['class' => 'radio-inline'],
        'required' => true))
      ->add('filtroIndividuale', EntityType::class, array('label' => false,
        'data' => $alunni,
        'class' => 'App\Entity\Alunno',
        'choice_label' => function ($obj) {
            return $obj->getCognome().' '.$obj->getNome().' ('.$obj->getDataNascita()->format('d/m/Y').')';
          },
        'query_builder' => function (EntityRepository $er) use ($listaAlunni) {
            return $er->createQueryBuilder('a')
              ->where('a.id IN (:lista)')
              ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
              ->setParameters(['lista' => $listaAlunni]);
          },
        'expanded' => true,
        'multiple' => true,
        'placeholder' => false,
        'label_attr' => ['class' => 'gs-pt-0 gs-ml-3 checkbox-split-vertical'],
        'required' => false,
        'mapped' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start']))
      ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end',
        'onclick' => "location.href='".$this->generateUrl('lezioni_registro_firme')."'"]))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // recupera dati
      $val_filtro_alunni = $form->get('filtroIndividuale')->getData();
      $val_filtro_alunni_id = array();
      foreach ($val_filtro_alunni as $alu) {
        $val_filtro_alunni_id[] = $alu->getId();
      }
      // controllo errori
      if ($annotazione->getVisibile() && empty($val_filtro_alunni)) {
        // errore: filtro vuoto
        $form->addError(new FormError($trans->trans('exception.destinatari_mancanti')));
      }
      // controllo permessi
      if ($annotazione->getVisibile()) {
        // permessi avviso
        if (!$bac->azioneAvviso('add', $dataObj, $this->getUser(), null)) {
          // errore: azione non permessa
          $form->addError(new FormError($trans->trans('exception.notifica_non_permessa')));
        }
      }
      if ($annotazione->getAvviso()) {
        if (!$bac->azioneAvviso('delete', $dataObj, $this->getUser(), $annotazione->getAvviso())) {
          // errore: cancellazione non permessa
          $form->addError(new FormError($trans->trans('exception.notifica_non_permessa')));
        }
      }
      // modifica dati
      if ($form->isValid()) {
        // cancella avviso
        $log_avviso = null;
        $log_avviso_utenti = null;
        if ($annotazione->getAvviso()) {
          $log_avviso = $annotazione->getAvviso()->getId();
          $log_avviso_utenti = $annotazione->getAvviso()->getFiltro();
          // cancella destinatari precedenti e dati lettura
          $this->em->getRepository('App\Entity\AvvisoUtente')->createQueryBuilder('au')
            ->delete()
            ->where('au.avviso=:avviso')
            ->setParameters(['avviso' => $annotazione->getAvviso()])
            ->getQuery()
            ->execute();
          // cancella avviso
          $this->em->remove($annotazione->getAvviso());
          $annotazione->setAvviso(null);
        }
        // crea avviso
        $avviso = null;
        if ($annotazione->getVisibile()) {
          // nuovo avviso
          $docente = ($this->getUser()->getSesso() == 'M' ? ' prof. ' : 'la prof.ssa ').
            $this->getUser()->getNome().' '.$this->getUser()->getCognome();
          $avviso = (new Avviso())
            ->setTipo('D')
            ->setDestinatari(['G'])
            ->setSedi(new ArrayCollection([$classe->getSede()]))
            ->setFiltroTipo('U')
            ->setFiltro($val_filtro_alunni_id)
            ->setData($annotazione->getData())
            ->setOggetto($trans->trans('message.avviso_individuale_oggetto', ['docente' => $docente]))
            ->setTesto($annotazione->getTesto())
            ->setDocente($this->getUser())
            ->addAnnotazioni($annotazione);
          $this->em->persist($avviso);
          $annotazione->setAvviso($avviso);
          // destinatari
          $dest = $bac->destinatariAvviso($avviso);
          // imposta utenti
          foreach ($dest['utenti'] as $u) {
            $obj = (new AvvisoUtente())
              ->setAvviso($avviso)
              ->setUtente($this->em->getReference('App\Entity\Utente', $u));
            $this->em->persist($obj);
          }
        }
        // ok: memorizza dati
        $this->em->flush();
        // notifica con attesa di mezzora
        if ($log_avviso) {
          NotificaMessageHandler::delete($this->em, (new AvvisoMessage($log_avviso))->getTag());
        }
        if ($avviso) {
          $notifica = new AvvisoMessage($avviso->getId());
          $msg->dispatch($notifica, [new DelayStamp(1800000)]);
        }
        // log azione
        if (!$id) {
          // nuovo
          $dblogger->logAzione('REGISTRO', 'Crea annotazione', array(
            'Annotazione' => $annotazione->getId(),
            'Avviso creato' => ($annotazione->getAvviso() ? $annotazione->getAvviso()->getId() : null),
            ));
        } else {
          // modifica
          $dblogger->logAzione('REGISTRO', 'Modifica annotazione', array(
            'Annotazione' => $annotazione->getId(),
            'Docente' => $annotazione_old->getDocente()->getId(),
            'Testo' => $annotazione_old->getTesto(),
            'Visibile' => $annotazione_old->getVisibile(),
            'Avviso creato' => ($annotazione->getAvviso() ? $annotazione->getAvviso()->getId() : null),
            'Avviso cancellato' => $log_avviso,
            'Utenti avviso cancellati' => $log_avviso_utenti,
            ));
        }
        // redirezione
        return $this->redirectToRoute('lezioni_registro_firme');
      }
    }
    // mostra la pagina di risposta
    return $this->render('lezioni/annotazione_edit.html.twig', array(
      'pagina_titolo' => 'page.lezioni_registro',
      'form' => $form->createView(),
      'form_title' => ($id > 0 ? 'title.modifica_annotazione' : 'title.nuova_annotazione'),
      'label' => $label,
    ));
  }

  /**
   * Cancella annotazione dal registro
   *
   * @param Request $request Pagina richiesta
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param BachecaUtil $bac Funzioni di utilità per la gestione della bacheca
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $id Identificativo dell'annotazione
   *
   * @return Response Pagina di risposta
   *
   * @Route("/lezioni/registro/annotazione/delete/{id}", name="lezioni_registro_annotazione_delete",
   *    requirements={"id": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function annotazioneDeleteAction(Request $request, RegistroUtil $reg, BachecaUtil $bac,
                                          LogHandler $dblogger, int $id): Response {
    // controlla annotazione
    $annotazione = $this->em->getRepository('App\Entity\Annotazione')->find($id);
    if (!$annotazione) {
      // annotazione non esiste, niente da fare
      return $this->redirectToRoute('lezioni_registro_firme');
    }
    // controlla permessi
    if (!$reg->azioneAnnotazione('delete', $annotazione->getData(), $this->getUser(), $annotazione->getClasse(), $annotazione)) {
      // errore: azione non permessa
      throw $this->createNotFoundException('exception.not_allowed');
    }
    if ($annotazione->getAvviso() &&
        !$bac->azioneAvviso('delete', $annotazione->getData(), $this->getUser(), $annotazione->getAvviso())) {
      // errore: azione non permessa
      throw $this->createNotFoundException('exception.not_allowed');
    }
    if ($annotazione->getAvviso() && !$annotazione->getVisibile()) {
      // errore: creato da gestione avvisi (staff/coordinatore)
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // cancella avviso
    $log_avviso = null;
    $log_avviso_utenti = null;
    if ($annotazione->getAvviso()) {
      $log_avviso = $annotazione->getAvviso()->getId();
      $log_avviso_utenti = $annotazione->getAvviso()->getFiltro();
      // cancella destinatari precedenti e dati lettura
      $this->em->getRepository('App\Entity\AvvisoUtente')->createQueryBuilder('au')
        ->delete()
        ->where('au.avviso=:avviso')
        ->setParameters(['avviso' => $annotazione->getAvviso()])
        ->getQuery()
        ->execute();
      // cancella avviso
      $this->em->remove($annotazione->getAvviso());
      $annotazione->setAvviso(null);
    }
    // cancella annotazione
    $annotazione_id = $annotazione->getId();
    $this->em->remove($annotazione);
    // ok: memorizza dati
    $this->em->flush();
    // rimuove notifica
    if ($log_avviso) {
      NotificaMessageHandler::delete($this->em, (new AvvisoMessage($log_avviso))->getTag());
    }
    // log azione
    $dblogger->logAzione('REGISTRO', 'Cancella annotazione', array(
      'Annotazione' => $annotazione_id,
      'Classe' => $annotazione->getClasse()->getId(),
      'Docente' => $annotazione->getDocente()->getId(),
      'Data' => $annotazione->getData()->format('Y-m-d'),
      'Testo' => $annotazione->getTesto(),
      'Visibile' => $annotazione->getVisibile(),
      'Avviso cancellato' => $log_avviso,
      'Utenti cancellati' => $log_avviso_utenti));
    // redirezione
    return $this->redirectToRoute('lezioni_registro_firme');
  }

  /**
   * Aggiunge o modifica una nota disciplinare
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $classe Identificativo della classe
   * @param string $data Data del giorno
   * @param int $id Identificativo della nota (se nullo aggiunge)
   *
   * @return Response Pagina di risposta
   *
   * @Route("/lezioni/registro/nota/edit/{classe}/{data}/{id}", name="lezioni_registro_nota_edit",
   *    requirements={"classe": "\d+", "data": "\d\d\d\d-\d\d-\d\d", "id": "\d+"},
   *    defaults={"id": 0},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function notaEditAction(Request $request, TranslatorInterface $trans, RegistroUtil $reg,
                                 LogHandler $dblogger, int $classe, string $data, int $id): Response {
    // inizializza
    $label = array();
    $docente_staff = in_array('ROLE_STAFF', $this->getUser()->getRoles());
    // controlla classe
    $classe = $this->em->getRepository('App\Entity\Classe')->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla data
    $dataObj = \DateTime::createFromFormat('Y-m-d', $data);
    $errore = $reg->controlloData($dataObj, $classe->getSede());
    if ($errore) {
      // errore: festivo
      throw $this->createNotFoundException('exception.invalid_params');
    }
    if ($id > 0) {
      // azione edit, controlla nota
      $nota = $this->em->getRepository('App\Entity\Nota')->findOneBy(['id' => $id,
        'data' => $dataObj, 'classe' => $classe]);
      if (!$nota) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $nota_old['testo'] = $nota->getTesto();
      $nota_old['provvedimento'] = $nota->getProvvedimento();
      $nota_old['docenteProvvedimento'] = $nota->getDocenteProvvedimento() ? $nota->getDocenteProvvedimento()->getId() : null;
      $nota_old['tipo'] = $nota->getTipo();
      $alunni_id = '';
      foreach ($nota->getAlunni() as $alu) {
        $alunni_id .= ','.$alu->getId();
      }
      $alunni_id = substr($alunni_id, 1);
      $nota_old['alunni'] = $alunni_id;
    } else {
      // azione add
      $nota = (new Nota())
        ->setTipo('C')
        ->setData($dataObj)
        ->setClasse($classe)
        ->setDocente($this->getUser());
    }
    // controlla permessi
    if (!$reg->azioneNota(($id > 0 ? 'edit' : 'add'), $dataObj, $this->getUser(), $classe, ($id > 0 ? $nota : null))) {
      // errore: azione non permessa
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // dati in formato stringa
    $formatter = new \IntlDateFormatter('it_IT', \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $label['data'] = $formatter->format($dataObj);
    $label['docente'] = $this->getUser()->getNome().' '.$this->getUser()->getCognome();
    $label['classe'] = ''.$classe;
    // lista alunni della classe
    $listaAlunni = $reg->alunniInData($dataObj, $classe);
    // form di inserimento
    $form = $this->container->get('form.factory')->createNamedBuilder('nota_edit', FormType::class, $nota)
      ->add('tipo', ChoiceType::class, array('label' => 'label.tipo_nota',
        'choices' => ['label.nota_classe' => 'C', 'label.nota_individuale' => 'I'],
        'expanded' => true,
        'multiple' => false,
        'disabled' => false,
        'label_attr' => ['class' => 'radio-inline'],
        'required' => true))
      ->add('alunni', EntityType::class, array('label' => 'label.alunni',
        'class' => 'App\Entity\Alunno',
        'choice_label' => function ($obj) {
            return $obj->getCognome().' '.$obj->getNome().' ('.$obj->getDataNascita()->format('d/m/Y').')';
          },
        'query_builder' => function (EntityRepository $er) use ($listaAlunni) {
          return $er->createQueryBuilder('a')
            ->where('a.id IN (:lista)')
            ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
            ->setParameters(['lista' => $listaAlunni]);
          },
        'expanded' => true,
        'multiple' => true,
        'disabled' => false,
        'label_attr' => ['class' => 'gs-pt-1 checkbox-split-vertical'],
        'required' => true))
      ->add('testo', MessageType::class, array('label' => 'label.testo',
        'trim' => true,
        'disabled' => false,
        'required' => true));
    if ($docente_staff) {
      // docente è dello staff
      $form->add('provvedimento', MessageType::class, array('label' => 'label.provvedimento',
        'trim' => true,
        'required' => false));
    }
    $form = $form
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start']))
      ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end',
          'onclick' => "location.href='".$this->generateUrl('lezioni_registro_firme')."'"]))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // valida tipo
      if ($nota->getTipo() == 'I') {
        if (count($nota->getAlunni()) == 0) {
          $form->get('alunni')->addError(new FormError($trans->trans('field.notblank', [], 'validators')));
        }
      } else {
        // nota di classe
        $nota->setAlunni(new ArrayCollection());
        // valida testo: errore se contiene nomi di alunni
        $nome = $reg->contieneNomiAlunni($dataObj, $classe, $nota->getTesto());
        if ($nome) {
          // errore
          $form->get('testo')->addError(
            new FormError($trans->trans('exception.nota_con_nome', ['nome' => $nome])));
        }
      }
      if ($form->isValid()) {
        // imposta valori
        if ($docente_staff) {
          // docente è dello staff
          $nota->setDocenteProvvedimento($nota->getProvvedimento() == '' ? null : $this->getUser());
        }
        if (!$id) {
          // nuovo
          $this->em->persist($nota);
        }
        // ok: memorizza dati
        $this->em->flush();
        // log azione
        if (!$id) {
          // nuovo
          $dblogger->logAzione('REGISTRO', 'Crea nota', array('Nota' => $nota->getId()));
        } else {
          // modifica
          $dblogger->logAzione('REGISTRO', 'Modifica nota', array(
            'Nota' => $nota->getId(),
            'Testo' => $nota_old['testo'],
            'Provvedimento' => $nota_old['provvedimento'],
            'Docente provvedimento' => $nota_old['docenteProvvedimento'],
            'Tipo nota' => $nota_old['tipo'],
            'Alunni' => $nota_old['alunni']
            ));
        }
        // messaggio
        if (!$docente_staff) {
          $this->addFlash('danger', 'message.nota_edit_temporizzato');
        }
        // redirezione
        return $this->redirectToRoute('lezioni_registro_firme');
      }
    }
    // mostra la pagina di risposta
    return $this->render('lezioni/nota_edit.html.twig', array(
      'pagina_titolo' => 'page.lezioni_registro',
      'form' => $form->createView(),
      'form_title' => ($id > 0 ? 'title.modifica_nota' : 'title.nuova_nota'),
      'label' => $label,
    ));
  }

  /**
   * Cancella nota disciplinare dal registro
   *
   * @param Request $request Pagina richiesta
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $id Identificativo della nota disciplinare
   *
   * @return Response Pagina di risposta
   *
   * @Route("/lezioni/registro/nota/delete/{id}", name="lezioni_registro_nota_delete",
   *    requirements={"id": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function notaDeleteAction(Request $request, RegistroUtil $reg, LogHandler $dblogger,
                                   int $id): Response {
    // controlla nota
    $nota = $this->em->getRepository('App\Entity\Nota')->find($id);
    if (!$nota) {
      // nota non esiste, niente da fare
      return $this->redirectToRoute('lezioni_registro_firme');
    }
    // controlla permessi
    if (!$reg->azioneNota('delete', $nota->getData(), $this->getUser(), $nota->getClasse(), $nota)) {
      // errore: azione non permessa
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // cancella nota
    $nota_id = $nota->getId();
    $alunni_id = '';
    foreach ($nota->getAlunni() as $alu) {
      $alunni_id .= ','.$alu->getId();
    }
    $alunni_id = substr($alunni_id, 1);
    $this->em->remove($nota);
    // ok: memorizza dati
    $this->em->flush();
    // log azione
    $dblogger->logAzione('REGISTRO', 'Cancella nota', array(
      'Nota' => $nota_id,
      'Classe' => $nota->getClasse()->getId(),
      'Docente' => $nota->getDocente()->getId(),
      'Data' => $nota->getData()->format('Y-m-d'),
      'Testo' => $nota->getTesto(),
      'Provvedimento' => $nota->getProvvedimento(),
      'Docente provvedimento' => ($nota->getDocenteProvvedimento() ? $nota->getDocenteProvvedimento()->getId() : null),
      'Tipo nota' => $nota->getTipo(),
      'Alunni' => $alunni_id
      ));
    // redirezione
    return $this->redirectToRoute('lezioni_registro_firme');
  }

}
