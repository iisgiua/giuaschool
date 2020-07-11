<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2020 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2020
 */


namespace App\Controller;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use App\Entity\Annotazione;
use App\Entity\Avviso;
use App\Entity\Firma;
use App\Entity\FirmaSostegno;
use App\Entity\Lezione;
use App\Entity\Nota;
use App\Entity\Staff;
use App\Util\LogHandler;
use App\Util\RegistroUtil;
use App\Util\BachecaUtil;
use App\Form\MessageType;


/**
 * RegistroController - gestione del registro
 */
class RegistroController extends AbstractController {

  /**
   * Gestione del registro delle lezioni
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param BachecaUtil $bac Funzioni di utilità per la gestione della bacheca
   * @param int $cattedra Identificativo della cattedra
   * @param int $classe Identificativo della classe (supplenza)
   * @param string $data Data del giorno da visualizzare (AAAA-MM-GG)
   * @param string $vista Tipo di vista del registro (giorno/settimana/mese)
   *
   * @return Response Pagina di risposta
   *
   * @Route("/lezioni/registro/firme/{cattedra}/{classe}/{data}/{vista}", name="lezioni_registro_firme",
   *    requirements={"cattedra": "\d+", "classe": "\d+", "data": "\d\d\d\d-\d\d-\d\d", "vista": "G|S|M"},
   *    defaults={"cattedra": 0, "classe": 0, "data": "0000-00-00", "vista": "G"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function firmeAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                              RegistroUtil $reg, BachecaUtil $bac, $cattedra, $classe, $data, $vista) {
    // inizializza variabili
    $lista_festivi = null;
    $errore = null;
    $dati = null;
    $annotazioni = null;
    $num_avvisi = 0;
    $lista_circolari = array();
    $note = null;
    $assenti = null;
    $settimana = ['Domenica', 'Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato'];
    $mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    $data_succ = null;
    $data_prec = null;
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
    // data inizio e fine vista
    if ($vista == 'S') {
      // vista settimanale
      $data_inizio = clone $data_obj;
      $data_inizio->modify('this week');
      $data_fine = clone $data_inizio;
      $data_fine->modify('+5 days');
    } elseif ($vista == 'M') {
      // vista mensile
      $data_inizio = \DateTime::createFromFormat('Y-m-d', $data_obj->format('Y-m-01'));
      $data_fine = clone $data_inizio;
      $data_fine->modify('last day of this month');
    } else {
      // vista giornaliera
      $data_inizio = $data_obj;
      $data_fine = $data_obj;
      $data_succ = null;
      $data_prec = null;
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
      $info['alunno'] = $cattedra->getAlunno();
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
      $info['alunno'] = null;
    }
    if ($classe) {
      // data prec/succ
      $data_succ = (clone $data_fine);
      $data_succ = $em->getRepository('App:Festivita')->giornoSuccessivo($data_succ);
      $data_prec = (clone $data_inizio);
      $data_prec = $em->getRepository('App:Festivita')->giornoPrecedente($data_prec);
      // recupera festivi per calendario
      $lista_festivi = $reg->listaFestivi($classe->getSede());
      // controllo data
      $errore = $reg->controlloData($data_obj, $classe->getSede());
      if (!$errore) {
        // non festivo
        $oggi = new \DateTime();
        $adesso = $oggi->format('H:i');
        if ($oggi->format('w') != 0 &&
            $adesso >= $em->getRepository('App:ScansioneOraria')->inizioLezioni($oggi, $classe->getSede()) &&
            $adesso <= $em->getRepository('App:ScansioneOraria')->fineLezioni($oggi, $classe->getSede())) {
          // avvisi alla classe
          $num_avvisi = $bac->bachecaNumeroAvvisiAlunni($classe);
          $lista_circolari = $em->getRepository('App:Circolare')->listaCircolariClasse($classe);
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
    $session->set('/APP/DOCENTE/menu_lezione', $route);
    // visualizza pagina
    return $this->render('lezioni/registro_firme_'.$vista.'.html.twig', array(
      'pagina_titolo' => 'page.lezioni_registro',
      'cattedra' => $cattedra,
      'classe' => $classe,
      'data' => $data_obj->format('Y-m-d'),
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
   * @param EntityManagerInterface $em Gestore delle entità
   * @param ValidatorInterface $validator Gestore della validazione dei dati
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
  public function addAction(Request $request, EntityManagerInterface $em, ValidatorInterface $validator, RegistroUtil $reg, LogHandler $dblogger,
                             $cattedra, $classe, $data, $ora) {
    // inizializza
    $label = array();
    // controlla classe
    $classe = $em->getRepository('App:Classe')->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla cattedra
    if ($cattedra > 0) {
      // lezioni di una cattedra esistente
      $cattedra = $em->getRepository('App:Cattedra')->findOneBy(['id' => $cattedra,
        'docente' => $this->getUser(), 'classe' => $classe, 'attiva' => 1]);
      if (!$cattedra) {
        // errore: non esiste la cattedra
        throw $this->createNotFoundException('exception.invalid_params');
      }
      $materia = $cattedra->getMateria();
    } else {
      // supplenza
      $cattedra = null;
      $materia = $em->getRepository('App:Materia')->findOneByTipo('U');
      if (!$materia) {
        // errore: dati inconsistenti
        throw $this->createNotFoundException('exception.invalid_params');
      }
    }
    // controlla data
    $data_obj = \DateTime::createFromFormat('Y-m-d', $data);
    $errore = $reg->controlloData($data_obj, $classe->getSede());
    if ($errore) {
      // errore: festivo
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // controlla permessi
    $perm = $reg->azioneLezione('add', $data_obj, $ora, $this->getUser(), $classe, $materia);
    if ($perm === null) {
      // errore: lezione esiste già (ignora)
      return $this->redirectToRoute('lezioni_registro_firme');
    } elseif (!$perm) {
      // errore: azione non permessa
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // controlla non esistenza di lezione
    $lezione = $em->getRepository('App:Lezione')->findOneBy(['classe' => $classe, 'data' => $data_obj,
      'ora' => $ora]);
    if ($lezione) {
      // lezione esiste, niente da fare
      return $this->redirectToRoute('lezioni_registro_firme');
    }
    // dati in formato stringa
    $formatter = new \IntlDateFormatter('it_IT', \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $label['data'] =  $formatter->format($data_obj);
    $label['docente'] = $this->getUser()->getNome().' '.$this->getUser()->getCognome();
    $label['classe'] = $classe->getAnno()."ª ".$classe->getSezione();
    $label['materia'] = $materia->getNomeBreve();
    if ($cattedra && $materia->getTipo() == 'S' && $cattedra->getAlunno()) {
      // sostegno
      $label['materia'] .= ' ('.$cattedra->getAlunno()->getCognome().' '.$cattedra->getAlunno()->getNome().')';
    }
    $dati = $reg->lezioneOreConsecutive($data_obj, $ora, $this->getUser(), $classe, $materia);
    $label['inizio'] = $dati['inizio'];
    $ora_fine = $dati['fine'];
    // form di inserimento
    $form = $this->container->get('form.factory')->createNamedBuilder('registro_add', FormType::class)
      ->add('fine', ChoiceType::class, array('label' => 'label.ora_fine',
        'choices'  => $ora_fine,
        'translation_domain' => false,
        'required' => true))
      ->add('argomenti', MessageType::class, array(
        'label' => ($materia->getTipo() == 'S' ? 'label.argomenti_sostegno' : 'label.argomenti'),
        'trim' => true,
        'required' => false))
      ->add('attivita', MessageType::class, array(
        'label' => ($materia->getTipo() == 'S' ? 'label.attivita_sostegno' : 'label.attivita'),
        'trim' => true,
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start']))
      ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end', 'onclick' => "location.href='".$this->generateUrl('lezioni_registro_firme')."'"]))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // ciclo per ore successive
      for ($num_ora = $ora; $num_ora <= $form->get('fine')->getData(); $num_ora++) {
        $lezione = (new Lezione())
          ->setData($data_obj)
          ->setOra($num_ora)
          ->setClasse($classe)
          ->setMateria($materia);
        if ($materia->getTipo() != 'S') {
          // lezione normale
          $lezione
            ->setArgomento($form->get('argomenti')->getData())
            ->setAttivita($form->get('attivita')->getData());
        }
        $em->persist($lezione);
        // validazione lezione
        $errore = $validator->validate($lezione);
        if (count($errore) > 0) {
          // errore, esce dal ciclo
          $form->addError(new FormError($errore[0]->getMessage()));
          break;
        }
        // crea firma
        if ($materia->getTipo() == 'S') {
          // sostegno
          $firma = (new FirmaSostegno())
            ->setLezione($lezione)
            ->setDocente($this->getUser())
            ->setAlunno($cattedra->getAlunno())
            ->setArgomento($form->get('argomenti')->getData())
            ->setAttivita($form->get('attivita')->getData());
        } else {
          // lezione normale
          $firma = (new Firma())
            ->setLezione($lezione)
            ->setDocente($this->getUser());
        }
        $em->persist($firma);
        // validazione firma
        $errore = $validator->validate($firma);
        if (count($errore) > 0) {
          // errore, esce dal ciclo
          $form->addError(new FormError($errore[0]->getMessage()));
          break;
        }
        // ok: memorizza dati
        $em->flush();
        // ricalcola ore assenza
        $reg->ricalcolaOreLezione($data_obj, $lezione);
        // log azione
        $dblogger->write($this->getUser(), $request->getClientIp(), 'REGISTRO', 'Crea lezione', __METHOD__, array(
          'Lezione' => $lezione->getId(),
          'Firma' => $firma->getId()
          ));
      }
      if (count($errore) == 0) {
        // ok, redirezione
        return $this->redirectToRoute('lezioni_registro_firme');
      }
    }
    // mostra la pagina di risposta
    return $this->render('lezioni/registro_edit.html.twig', array(
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
   * @param EntityManagerInterface $em Gestore delle entità
   * @param ValidatorInterface $validator Gestore della validazione dei dati
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
  public function editAction(Request $request, EntityManagerInterface $em, ValidatorInterface $validator, RegistroUtil $reg, LogHandler $dblogger,
                              $cattedra, $classe, $data, $ora) {
    // inizializza
    $label = array();
    // controlla classe
    $classe = $em->getRepository('App:Classe')->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla cattedra
    if ($cattedra > 0) {
      // lezioni di una cattedra esistente
      $cattedra = $em->getRepository('App:Cattedra')->findOneBy(['id' => $cattedra,
        'docente' => $this->getUser(), 'classe' => $classe, 'attiva' => 1]);
      if (!$cattedra) {
        // errore: non esiste la cattedra
        throw $this->createNotFoundException('exception.invalid_params');
      }
      $materia = $cattedra->getMateria();
    } else {
      // supplenza
      $cattedra = null;
      $materia = $em->getRepository('App:Materia')->findOneByTipo('U');
      if (!$materia) {
        // errore: dati inconsistenti
        throw $this->createNotFoundException('exception.invalid_params');
      }
    }
    // controlla data
    $data_obj = \DateTime::createFromFormat('Y-m-d', $data);
    $errore = $reg->controlloData($data_obj, $classe->getSede());
    if ($errore) {
      // errore: festivo
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // controlla esistenza di lezione
    $lezione = $em->getRepository('App:Lezione')->findOneBy(['classe' => $classe, 'data' => $data_obj,
      'ora' => $ora]);
    if (!$lezione) {
      // errore: lezione non esiste
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // controlla firme di lezione
    $firme = $em->getRepository('App:Firma')->findByLezione($lezione);
    if (count($firme) == 0) {
      // errore: firme non esistono
      throw $this->createNotFoundException('exception.invalid_params');
    }
    $lista_firme = array();
    $firma_docente = null;
    foreach ($firme as $f) {
      $lista_firme[] = $f->getDocente()->getId();
      if ($f->getDocente()->getId() == $this->getUser()->getId()) {
        $firma_docente = $f;
      }
    }
    // controlla permessi
    if (!$reg->azioneLezione('edit', $data_obj, $ora, $this->getUser(), $classe, $materia, $lezione, $lista_firme)) {
      // errore: azione non permessa
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // dati in formato stringa
    $formatter = new \IntlDateFormatter('it_IT', \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $label['data'] =  $formatter->format($data_obj);
    $label['docente'] = $this->getUser()->getNome().' '.$this->getUser()->getCognome();
    $label['classe'] = $classe->getAnno()."ª ".$classe->getSezione();
    $label['materia'] = $materia->getNomeBreve();
    if ($cattedra && $materia->getTipo() == 'S' && $cattedra->getAlunno()) {
      // sostegno
      $label['materia'] .= ' ('.$cattedra->getAlunno()->getCognome().' '.$cattedra->getAlunno()->getNome().')';
    }
    $dati = $reg->lezioneOreConsecutive($data_obj, $ora, $this->getUser(), $classe, $materia);
    $label['inizio'] = $dati['inizio'];
    $ora_fine =  $dati['fine'];
    // form di inserimento
    $form = $this->container->get('form.factory')->createNamedBuilder('registro_edit', FormType::class)
      ->add('fine', ChoiceType::class, array('label' => 'label.ora_fine',
        'choices'  => $ora_fine,
        'translation_domain' => false,
        'disabled' => true,
        'required' => true))
      ->add('argomenti', MessageType::class, array(
        'data' => ($materia->getTipo() == 'S' ? (($firma_docente && $firma_docente instanceof FirmaSostegno) ? $firma_docente->getArgomento() : '') : $lezione->getArgomento()),
        'label' => ($materia->getTipo() == 'S' ? 'label.argomenti_sostegno' : 'label.argomenti'),
        'trim' => true,
        'required' => false))
      ->add('attivita', MessageType::class, array(
        'data' => ($materia->getTipo() == 'S' ? (($firma_docente && $firma_docente instanceof FirmaSostegno) ? $firma_docente->getAttivita() : '') : $lezione->getAttivita()),
        'label' => ($materia->getTipo() == 'S' ? 'label.attivita_sostegno' : 'label.attivita'),
        'trim' => true,
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start']))
      ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end', 'onclick' => "location.href='".$this->generateUrl('lezioni_registro_firme')."'"]))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      if ($materia->getTipo() == 'S') {
        // sostegno
        if (!$firma_docente) {
          // aggiunge firma
          $argomenti_old = '';
          $attivita_old = '';
          $firma = (new FirmaSostegno())
            ->setLezione($lezione)
            ->setDocente($this->getUser())
            ->setAlunno($cattedra->getAlunno())
            ->setArgomento($form->get('argomenti')->getData())
            ->setAttivita($form->get('attivita')->getData());
          $em->persist($firma);
        } else {
          // modifica dati
          $argomenti_old = $firma_docente->getArgomento();
          $attivita_old = $firma_docente->getAttivita();
          $firma = $firma_docente;
          $firma
            ->setAlunno($cattedra->getAlunno())
            ->setArgomento($form->get('argomenti')->getData())
            ->setAttivita($form->get('attivita')->getData());
        }
      } else {
        // normale
        $argomenti_old = $lezione->getArgomento();
        $attivita_old = $lezione->getAttivita();
        // aggiorna lezione (eventualmente cambia materia)
        $lezione
          ->setArgomento($form->get('argomenti')->getData())
          ->setAttivita($form->get('attivita')->getData())
          ->setMateria($materia);
        if (!$firma_docente) {
          // aggiunge firma
          $firma = (new Firma())
            ->setLezione($lezione)
            ->setDocente($this->getUser());
          $em->persist($firma);
        } else {
          $firma = $firma_docente;
        }
      }
      // validazione lezione
      $errore = $validator->validate($lezione);
      if (count($errore) > 0) {
        // errore
        $form->addError(new FormError($errore[0]->getMessage()));
      } else {
        // validazione firma
        $errore = $validator->validate($firma);
        if (count($errore) > 0) {
          // errore
          $form->addError(new FormError($errore[0]->getMessage()));
        } else {
          // ok: memorizza dati
          $em->flush();
          // log azione
          $dblogger->write($this->getUser(), $request->getClientIp(), 'REGISTRO', 'Modifica lezione', __METHOD__, array(
            'Lezione' => $lezione->getId(),
            'Firma' => $firma->getId(),
            'Argomento' => $argomenti_old,
            'Attivita' =>  $attivita_old
            ));
          // redirezione
          return $this->redirectToRoute('lezioni_registro_firme');
        }
      }
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
   * @param EntityManagerInterface $em Gestore delle entità
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
  public function deleteAction(Request $request, EntityManagerInterface $em, RegistroUtil $reg, LogHandler $dblogger,
                                $classe, $data, $ora) {
    // controlla classe
    $classe = $em->getRepository('App:Classe')->find($classe);
    if (!$classe) {
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
    // controlla esistenza di lezione
    $lezione = $em->getRepository('App:Lezione')->findOneBy(['classe' => $classe, 'data' => $data_obj,
      'ora' => $ora]);
    if (!$lezione) {
      // lezione non esiste, niente da fare
      return $this->redirectToRoute('lezioni_registro_firme');
    }
    // controlla firme di lezione
    $firme = $em->getRepository('App:Firma')->findByLezione($lezione);
    if (count($firme) == 0) {
      // errore: firme non esistono
      throw $this->createNotFoundException('exception.invalid_params');
    }
    $lista_firme = array();
    $firma_docente = null;
    $num_sostegno = 0;
    foreach ($firme as $f) {
      $lista_firme[] = $f->getDocente()->getId();
      if ($f->getDocente()->getId() == $this->getUser()->getId()) {
        $firma_docente = $f;
      } elseif ($f instanceof FirmaSostegno) {
        $num_sostegno++;
      }
    }
    // controlla permessi
    if (!$reg->azioneLezione('delete', $data_obj, $ora, $this->getUser(), $classe, $lezione->getMateria(), $lezione, $lista_firme)) {
      // errore: azione non permessa
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // controlla voti
    $voti = $em->getRepository('App:Valutazione')->findBy(['lezione' => $lezione, 'docente' => $this->getUser()]);
    if (count($voti) > 0) {
      // altra lezione
      $altra_lezione = $em->getRepository('App:Lezione')->createQueryBuilder('l')
        ->join('App:Firma', 'f', 'WITH', 'l.id=f.lezione')
        ->where('l.id!=:id AND l.data=:data AND l.classe=:classe AND l.materia=:materia AND f.docente=:docente')
        ->setParameters(['id' => $lezione, 'data' => $data, 'classe' => $classe,
          'materia' => $lezione->getMateria(), 'docente' => $this->getUser()])
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();
      if (!$altra_lezione) {
        // errore: dati inconsistenti
        throw $this->createNotFoundException('exception.invalid_params');
      }
      foreach ($voti as $v) {
        $v->setLezione($altra_lezione);
      }
    }
    // cancella firma
    $firma_docente_id = $firma_docente->getId();
    $firma_cancellata = null;
    if ($firma_docente instanceof FirmaSostegno) {
      $firma_cancellata['argomento'] = $firma_docente->getArgomento();
      $firma_cancellata['attivita'] = $firma_docente->getAttivita();
    }
    $em->remove($firma_docente);
    // controlla firme rimaste
    $lezione_id = $lezione->getId();
    $lezione_cancellata = null;
    if (count($lista_firme) == 1) {
      // solo firma docente: cancella intera lezione
      $lezione_cancellata['materia'] = $lezione->getMateria()->getId();
      $lezione_cancellata['argomento'] = $lezione->getArgomento();
      $lezione_cancellata['attivita'] = $lezione->getAttivita();
      // cancella assenze lezione
      $assenze_lezione = $em->getRepository('App:AssenzaLezione')->findByLezione($lezione);
      foreach ($assenze_lezione as $asslez) {
        $em->remove($asslez);
      }
      // cancella lezione
      $em->remove($lezione);
    } elseif ($lezione->getMateria()->getTipo() != 'S' && (count($lista_firme) - 1) == $num_sostegno) {
      // rimaste solo firme sostegno: cambia materia e resetta argomento/attività
      $lezione_cancellata['materia'] = $lezione->getMateria()->getId();
      $lezione_cancellata['argomento'] = $lezione->getArgomento();
      $lezione_cancellata['attivita'] = $lezione->getAttivita();
      $materia = $em->getRepository('App:Materia')->findOneByTipo('S');
      if (!$materia) {
        // errore: dati inconsistenti
        throw $this->createNotFoundException('exception.invalid_params');
      }
      $lezione
        ->setMateria($materia)
        ->setArgomento('')
        ->setAttivita('');
    }
    // ok: memorizza dati
    $em->flush();
    // log azione
    if ($lezione_cancellata) {
      // intera lezione
      $dblogger->write($this->getUser(), $request->getClientIp(), 'REGISTRO', 'Cancella firma e lezione', __METHOD__, array(
        'Lezione' => $lezione_id,
        'Firma' => $firma_docente_id,
        'Classe' => $classe->getId(),
        'Data' => $data,
        'Ora' => $ora,
        'Materia' => $lezione_cancellata['materia'],
        'Argomento' => $lezione_cancellata['argomento'],
        'Attività' =>  $lezione_cancellata['attivita'],
        'Argomento sostegno' => ($firma_cancellata ? $firma_cancellata['argomento'] : ''),
        'Attività sostegno' =>  ($firma_cancellata ? $firma_cancellata['attivita'] : '')
        ));
    } else {
      // solo firma
      $dblogger->write($this->getUser(), $request->getClientIp(), 'REGISTRO', 'Cancella firma', __METHOD__, array(
        'Lezione' => $lezione_id,
        'Firma' => $firma_docente_id,
        'Argomento sostegno' => ($firma_cancellata ? $firma_cancellata['argomento'] : ''),
        'Attività sostegno' =>  ($firma_cancellata ? $firma_cancellata['attivita'] : '')
        ));
    }
    // redirezione
    return $this->redirectToRoute('lezioni_registro_firme');
  }

  /**
   * Aggiunge o modifica una annotazione al registro
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param TranslatorInterface $trans Gestore delle traduzioni
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
  public function annotazioneEditAction(Request $request, EntityManagerInterface $em, TranslatorInterface $trans, RegistroUtil $reg, BachecaUtil $bac,
                                         LogHandler $dblogger, $classe, $data, $id) {
    // inizializza
    $label = array();
    $dest_filtro['sedi'] = [];
    $dest_filtro['classi'] = [];
    $dest_filtro['utenti'] = [];
    // controlla classe
    $classe = $em->getRepository('App:Classe')->find($classe);
    if (!$classe) {
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
    if ($id > 0) {
      // azione edit, controlla annotazione
      $annotazione = $em->getRepository('App:Annotazione')->findOneBy(['id' => $id,
        'data' => $data_obj, 'classe' => $classe]);
      if (!$annotazione) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      if ($annotazione->getAvviso()) {
        $dest_filtro = $bac->filtriAvviso($annotazione->getAvviso());
      }
      $annotazione_old = clone $annotazione;
    } else {
      // azione add
      $annotazione = (new Annotazione())
        ->setData($data_obj)
        ->setClasse($classe)
        ->setVisibile(false);
      $em->persist($annotazione);
    }
    // imposta autore dell'annotazione
    $annotazione->setDocente($this->getUser());
    // controlla permessi
    if (!$reg->azioneAnnotazione(($id > 0 ? 'edit' : 'add'), $data_obj, $this->getUser(), $classe, ($id > 0 ? $annotazione : null))) {
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
    $label['data'] =  $formatter->format($data_obj);
    $label['docente'] = $this->getUser()->getNome().' '.$this->getUser()->getCognome();
    $label['classe'] = $classe->getAnno()."ª ".$classe->getSezione();
    // opzione scelta filtro
    $alunni = array();
    if (!empty($dest_filtro['utenti'])) {
      foreach ($dest_filtro['utenti'] as $id) {
        $alunni[] = $em->getRepository('App:Alunno')->find($id['alunno']);
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
        'class' => 'App:Alunno',
        'choice_label' => function ($obj) {
            return $obj->getCognome().' '.$obj->getNome().' ('.$obj->getDataNascita()->format('d/m/Y').')';
          },
        'query_builder' => function (EntityRepository $er) use ($classe) {
            return $er->createQueryBuilder('a')
              ->where('a.classe=:classe and a.abilitato=:abilitato')
              ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
              ->setParameters(['classe' => $classe, 'abilitato' => 1]);
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
        if (!$bac->azioneAvviso('add', $data_obj, $this->getUser(), null)) {
          // errore: azione non permessa
          $form->addError(new FormError($trans->trans('exception.notifica_non_permessa')));
        }
      }
      if ($annotazione->getAvviso()) {
        if (!$bac->azioneAvviso('delete', $data_obj, $this->getUser(), $annotazione->getAvviso())) {
          // errore: cancellazione non permessa
          $form->addError(new FormError($trans->trans('exception.notifica_non_permessa')));
        }
      }
      // modifica dati
      if ($form->isValid()) {
        // cancella avviso
        $log_avviso = null;
        if ($annotazione->getAvviso()) {
          $log_avviso = $annotazione->getAvviso()->getId();
          $log_destinatari_delete = $bac->eliminaFiltriAvviso($annotazione->getAvviso());
          $em->remove($annotazione->getAvviso());
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
            ->setDestinatariStaff(false)
            ->setDestinatariCoordinatori(false)
            ->setDestinatariDocenti(false)
            ->setDestinatariGenitori(true)
            ->setDestinatariAlunni(false)
            ->setDestinatariIndividuali(true)
            ->setData($annotazione->getData())
            ->setOggetto($trans->trans('message.avviso_individuale_oggetto', ['docente' => $docente]))
            ->setTesto($annotazione->getTesto())
            ->setDocente($this->getUser())
            ->addAnnotazione($annotazione);
          $em->persist($avviso);
          $annotazione->setAvviso($avviso);
          // destinatari
          $dest_filtro['utenti'] = array();
          $log_destinatari = $bac->modificaFiltriAvviso($avviso, $dest_filtro, 'N', [], ['G'], 'I',
            $val_filtro_alunni_id);
        }
        // ok: memorizza dati
        $em->flush();
        // log azione
        if (!$id) {
          // nuovo
          $dblogger->write($this->getUser(), $request->getClientIp(), 'REGISTRO', 'Crea annotazione', __METHOD__, array(
            'Annotazione' => $annotazione->getId(),
            'Avviso creato' => ($annotazione->getAvviso() ? $annotazione->getAvviso()->getId() : null),
            'Utenti aggiunti' => implode(', ', array_map(function ($a) {
                return $a['genitore'].'->'.$a['alunno'];
              }, (isset($log_destinatari['utenti']['add']) ? $log_destinatari['utenti']['add'] : []))),
            ));
        } else {
          // modifica
          if (isset($log_destinatari_delete)) {
            $log_destinatari['utenti']['delete'] = $log_destinatari_delete['utenti'];
          }
          $dblogger->write($this->getUser(), $request->getClientIp(), 'REGISTRO', 'Modifica annotazione', __METHOD__, array(
            'Annotazione' => $annotazione->getId(),
            'Docente' => $annotazione_old->getDocente()->getId(),
            'Testo' => $annotazione_old->getTesto(),
            'Visibile' => $annotazione_old->getVisibile(),
            'Avviso creato' => ($annotazione->getAvviso() ? $annotazione->getAvviso()->getId() : null),
            'Avviso cancellato' => $log_avviso,
            'Utenti aggiunti' => implode(', ', array_map(function ($a) {
                return $a['genitore'].'->'.$a['alunno'];
              }, (isset($log_destinatari['utenti']['add']) ? $log_destinatari['utenti']['add'] : []))),
            'Utenti cancellati' => implode(', ', array_map(function ($a) {
                return $a['genitore'].'->'.$a['alunno'];
              }, (isset($log_destinatari['utenti']['delete']) ? $log_destinatari['utenti']['delete'] : []))),
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
   * @param EntityManagerInterface $em Gestore delle entità
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
  public function annotazioneDeleteAction(Request $request, EntityManagerInterface $em, RegistroUtil $reg, BachecaUtil $bac,
                                           LogHandler $dblogger, $id) {
    // controlla annotazione
    $annotazione = $em->getRepository('App:Annotazione')->find($id);
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
    if ($annotazione->getAvviso()) {
      $log_avviso = $annotazione->getAvviso()->getId();
      $log_destinatari = $bac->eliminaFiltriAvviso($annotazione->getAvviso());
      $em->remove($annotazione->getAvviso());
      $annotazione->setAvviso(null);
    }
    // cancella annotazione
    $annotazione_id = $annotazione->getId();
    $em->remove($annotazione);
    // ok: memorizza dati
    $em->flush();
    // log azione
    $dblogger->write($this->getUser(), $request->getClientIp(), 'REGISTRO', 'Cancella annotazione', __METHOD__, array(
      'Annotazione' => $annotazione_id,
      'Classe' => $annotazione->getClasse()->getId(),
      'Docente' => $annotazione->getDocente()->getId(),
      'Data' => $annotazione->getData()->format('Y-m-d'),
      'Testo' => $annotazione->getTesto(),
      'Visibile' => $annotazione->getVisibile(),
      'Avviso cancellato' => isset($log_avviso) ? $log_avviso : null,
      'Utenti cancellati' => implode(', ', array_map(function ($a) {
          return $a['genitore'].'->'.$a['alunno'];
        }, (isset($log_destinatari['utenti']) ? $log_destinatari['utenti'] : []))),
      ));
    // redirezione
    return $this->redirectToRoute('lezioni_registro_firme');
  }

  /**
   * Aggiunge o modifica una nota disciplinare
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
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
  public function notaEditAction(Request $request, EntityManagerInterface $em, TranslatorInterface $trans, RegistroUtil $reg, LogHandler $dblogger,
                                  $classe, $data, $id) {
    // inizializza
    $label = array();
    $docente_staff = in_array('ROLE_STAFF', $this->getUser()->getRoles());
    // controlla classe
    $classe = $em->getRepository('App:Classe')->find($classe);
    if (!$classe) {
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
    if ($id > 0) {
      // azione edit, controlla nota
      $nota = $em->getRepository('App:Nota')->findOneBy(['id' => $id,
        'data' => $data_obj, 'classe' => $classe]);
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
        ->setData($data_obj)
        ->setClasse($classe)
        ->setDocente($this->getUser());
      $disabilitato = false;
    }
    // controlla permessi
    if (!$reg->azioneNota(($id > 0 ? 'edit' : 'add'), $data_obj, $this->getUser(), $classe, ($id > 0 ? $nota : null))) {
      // errore: azione non permessa
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // dati in formato stringa
    $formatter = new \IntlDateFormatter('it_IT', \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $label['data'] =  $formatter->format($data_obj);
    $label['docente'] = $this->getUser()->getNome().' '.$this->getUser()->getCognome();
    $label['classe'] = $classe->getAnno()."ª ".$classe->getSezione();
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
        'class' => 'App:Alunno',
        'choice_label' => function ($obj) {
            return $obj->getCognome().' '.$obj->getNome().' ('.$obj->getDataNascita()->format('d/m/Y').')';
          },
        'query_builder' => function (EntityRepository $er) use ($classe) {
            return $er->createQueryBuilder('a')
              ->where('a.classe=:classe and a.abilitato=:abilitato')
              ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
              ->setParameters(['classe' => $classe, 'abilitato' => 1]);
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
        $nome = $reg->contieneNomiAlunni($data_obj, $classe, $nota->getTesto());
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
          $em->persist($nota);
        }
        // ok: memorizza dati
        $em->flush();
        // log azione
        if (!$id) {
          // nuovo
          $dblogger->write($this->getUser(), $request->getClientIp(), 'REGISTRO', 'Crea nota', __METHOD__, array(
            'Nota' => $nota->getId()
            ));
        } else {
          // modifica
          $dblogger->write($this->getUser(), $request->getClientIp(), 'REGISTRO', 'Modifica nota', __METHOD__, array(
            'Nota' => $nota->getId(),
            'Testo' => $nota_old['testo'],
            'Provvedimento' => $nota_old['provvedimento'],
            'Docente provvedimento' => $nota_old['docenteProvvedimento'],
            'Tipo nota' => $nota_old['tipo'],
            'Alunni' => $nota_old['alunni']
            ));
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
   * @param EntityManagerInterface $em Gestore delle entità
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
  public function notaDeleteAction(Request $request, EntityManagerInterface $em, RegistroUtil $reg, LogHandler $dblogger,
                                    $id) {
    // controlla nota
    $nota = $em->getRepository('App:Nota')->find($id);
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
    $em->remove($nota);
    // ok: memorizza dati
    $em->flush();
    // log azione
    $dblogger->write($this->getUser(), $request->getClientIp(), 'REGISTRO', 'Cancella nota', __METHOD__, array(
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
