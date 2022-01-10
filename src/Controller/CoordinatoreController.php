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
use Doctrine\ORM\EntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Translation\TranslatorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormError;
use App\Util\StaffUtil;
use App\Util\PdfManager;
use App\Util\RegistroUtil;
use App\Util\BachecaUtil;
use App\Util\LogHandler;
use App\Entity\Staff;
use App\Entity\Preside;
use App\Entity\Classe;
use App\Entity\Avviso;
use App\Entity\AvvisoClasse;
use App\Entity\AvvisoUtente;
use App\Entity\Notifica;
use App\Entity\Annotazione;
use App\Form\MessageType;
use App\Form\AvvisoType;


/**
 * CoordinatoreController - gestione delle funzioni per i coordinatori
 */
class CoordinatoreController extends AbstractController {

  /**
   * Gestione delle funzioni coordinatore
   *
   * @param SessionInterface $session Gestore delle sessioni
   *
   * @return Response Pagina di risposta
   *
   * @Route("/coordinatore", name="coordinatore",
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function coordinatoreAction(SessionInterface $session) {
    if (!($this->getUser() instanceOf Staff) && !($this->getUser() instanceOf Preside)) {
      // coordinatore
      $classi = explode(',', $session->get('/APP/DOCENTE/coordinatore'));
      if (count($classi) == 1) {
        // coordinatore di una sola classe: vai
        $session->set('/APP/DOCENTE/classe_coordinatore', $classi[0]);
        return $this->redirectToRoute('coordinatore_assenze', ['classe' => $classi[0]]);
      }
    }
    // staff/preside o coordinatore di più classi
    if ($session->get('/APP/DOCENTE/classe_coordinatore')) {
      // classe scelta, vai alle assenze
      return $this->redirectToRoute('coordinatore_assenze');
    } else {
      // scelta classe
      return $this->redirectToRoute('coordinatore_classe');
    }
  }

  /**
   * Gestione della scelta delle classi
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   *
   * @return Response Pagina di risposta
   *
   * @Route("/coordinatore/classe/", name="coordinatore_classe",
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function classeAction(EntityManagerInterface $em, SessionInterface $session) {
    // lista classi coordinatore
    $classi = $em->getRepository('App:Classe')->createQueryBuilder('c')
      ->where('c.id IN (:lista)')
      ->orderBy('c.sede,c.anno,c.sezione', 'ASC')
      ->setParameters(['lista' => explode(',', $session->get('/APP/DOCENTE/coordinatore'))])
      ->getQuery()
      ->getResult();
    // lista tutte le classi
    $tutte = array();
    if ($this->getUser() instanceOf Staff) {
      if ($this->getUser()->getSede()) {
        // solo classi della sede
        $lista = $em->getRepository('App:Classe')->createQueryBuilder('c')
          ->where('c.sede=:sede')
          ->orderBy('c.sede,c.sezione,c.anno', 'ASC')
          ->setParameters(['sede' => $this->getUser()->getSede()])
          ->getQuery()
          ->getResult();
      } else {
        // tutte le classi
        $lista = $em->getRepository('App:Classe')->createQueryBuilder('c')
          ->orderBy('c.sede,c.sezione,c.anno', 'ASC')
          ->getQuery()
          ->getResult();
      }
      // raggruppa per sezione
      foreach ($lista as $c) {
        $tutte[$c->getSezione()][] = $c;
      }
    }
    // visualizza pagina
    return $this->render('coordinatore/classe.html.twig', array(
      'pagina_titolo' => 'page.coordinatore_classe',
      'classi' => $classi,
      'tutte' => $tutte,
    ));
  }

  /**
   * Mostra le note della classe.
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param StaffUtil $staff Funzioni di utilità per lo staff
   * @param SessionInterface $session Gestore delle sessioni
   * @param int $classe Identificativo della classe
   *
   * @return Response Pagina di risposta
   *
   * @Route("/coordinatore/note/{classe}", name="coordinatore_note",
   *    requirements={"classe": "\d+"},
   *    defaults={"classe": 0},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function noteAction(EntityManagerInterface $em, StaffUtil $staff, SessionInterface $session, $classe) {
    // inizializza variabili
    $dati = null;
    // parametro classe
    if ($classe == 0) {
      // recupera parametri da sessione
      $classe = $session->get('/APP/DOCENTE/classe_coordinatore');
    } else {
      // memorizza su sessione
      $session->set('/APP/DOCENTE/classe_coordinatore', $classe);
    }
    // controllo classe
    if ($classe > 0) {
      $classe = $em->getRepository('App:Classe')->find($classe);
      if (!$classe) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      // controllo accesso alla funzione
      if (!($this->getUser() instanceOf Staff) && !($this->getUser() instanceOf Preside)) {
        // coordinatore
        $classi = explode(',', $session->get('/APP/DOCENTE/coordinatore'));
        if (!in_array($classe->getId(), $classi)) {
          // errore
          throw $this->createNotFoundException('exception.invalid_params');
        }
      }
      // legge dati
      $dati = $staff->note($classe);
    }
    // visualizza pagina
    return $this->render('coordinatore/note.html.twig', array(
      'pagina_titolo' => 'page.coordinatore_note',
      'classe' => $classe,
      'dati' => $dati,
    ));
  }

  /**
   * Mostra le assenze della classe.
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param StaffUtil $staff Funzioni di utilità per lo staff
   * @param SessionInterface $session Gestore delle sessioni
   * @param int $classe Identificativo della classe
   *
   * @return Response Pagina di risposta
   *
   * @Route("/coordinatore/assenze/{classe}", name="coordinatore_assenze",
   *    requirements={"classe": "\d+"},
   *    defaults={"classe": 0},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function assenzeAction(EntityManagerInterface $em, StaffUtil $staff, SessionInterface $session, $classe) {
    // inizializza variabili
    $dati = null;
    // parametro classe
    if ($classe == 0) {
      // recupera parametri da sessione
      $classe = $session->get('/APP/DOCENTE/classe_coordinatore');
    } else {
      // memorizza su sessione
      $session->set('/APP/DOCENTE/classe_coordinatore', $classe);
    }
    // controllo classe
    if ($classe > 0) {
      $classe = $em->getRepository('App:Classe')->find($classe);
      if (!$classe) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      // controllo accesso alla funzione
      if (!($this->getUser() instanceOf Staff) && !($this->getUser() instanceOf Preside)) {
        // coordinatore
        $classi = explode(',', $session->get('/APP/DOCENTE/coordinatore'));
        if (!in_array($classe->getId(), $classi)) {
          // errore
          throw $this->createNotFoundException('exception.invalid_params');
        }
      }
      // legge dati
      $dati = $staff->assenze($classe);
    }
    // visualizza pagina
    return $this->render('coordinatore/assenze.html.twig', array(
      'pagina_titolo' => 'page.coordinatore_assenze',
      'classe' => $classe,
      'dati' => $dati,
    ));
  }

  /**
   * Mostra le medie dei voti della classe.
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param StaffUtil $staff Funzioni di utilità per lo staff
   * @param SessionInterface $session Gestore delle sessioni
   * @param int $classe Identificativo della classe
   *
   * @return Response Pagina di risposta
   *
   * @Route("/coordinatore/voti/{classe}", name="coordinatore_voti",
   *    requirements={"classe": "\d+"},
   *    defaults={"classe": 0},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function votiAction(EntityManagerInterface $em, StaffUtil $staff, SessionInterface $session, $classe) {
    // inizializza variabili
    $dati = null;
    // parametro classe
    if ($classe == 0) {
      // recupera parametri da sessione
      $classe = $session->get('/APP/DOCENTE/classe_coordinatore');
    } else {
      // memorizza su sessione
      $session->set('/APP/DOCENTE/classe_coordinatore', $classe);
    }
    // controllo classe
    if ($classe > 0) {
      $classe = $em->getRepository('App:Classe')->find($classe);
      if (!$classe) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      // controllo accesso alla funzione
      if (!($this->getUser() instanceOf Staff) && !($this->getUser() instanceOf Preside)) {
        // coordinatore
        $classi = explode(',', $session->get('/APP/DOCENTE/coordinatore'));
        if (!in_array($classe->getId(), $classi)) {
          // errore
          throw $this->createNotFoundException('exception.invalid_params');
        }
      }
      // legge dati
      $dati = $staff->voti($classe);
    }
    // visualizza pagina
    return $this->render('coordinatore/voti.html.twig', array(
      'pagina_titolo' => 'page.coordinatore_voti',
      'classe' => $classe,
      'dati' => $dati,
    ));
  }

  /**
   * Mostra la situazione dei singoli alunni.
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param StaffUtil $staff Funzioni di utilità per lo staff
   * @param SessionInterface $session Gestore delle sessioni
   * @param int $classe Identificativo della classe
   *
   * @return Response Pagina di risposta
   *
   * @Route("/coordinatore/situazione/{classe}", name="coordinatore_situazione",
   *    requirements={"classe": "\d+"},
   *    defaults={"classe": 0},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function situazioneAction(EntityManagerInterface $em, StaffUtil $staff, SessionInterface $session,
                                    $classe) {
    // inizializza variabili
    $dati = null;
    // parametro classe
    if ($classe == 0) {
      // recupera parametri da sessione
      $classe = $session->get('/APP/DOCENTE/classe_coordinatore');
    } else {
      // memorizza su sessione
      $session->set('/APP/DOCENTE/classe_coordinatore', $classe);
    }
    // controllo classe
    if ($classe > 0) {
      $classe = $em->getRepository('App:Classe')->find($classe);
      if (!$classe) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      // controllo accesso alla funzione
      if (!($this->getUser() instanceOf Staff) && !($this->getUser() instanceOf Preside)) {
        // coordinatore
        $classi = explode(',', $session->get('/APP/DOCENTE/coordinatore'));
        if (!in_array($classe->getId(), $classi)) {
          // errore
          throw $this->createNotFoundException('exception.invalid_params');
        }
      }
      // legge dati
      $dati = $staff->alunni($classe);
    }
    // visualizza pagina
    return $this->render('coordinatore/situazione.html.twig', array(
      'pagina_titolo' => 'page.coordinatore_situazione',
      'classe' => $classe,
      'dati' => $dati,
    ));
  }

  /**
   * Mostra la situazione di un singolo alunno.
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param StaffUtil $staff Funzioni di utilità per lo staff
   * @param PdfManager $pdf Gestore dei documenti PDF
   * @param int $alunno Identificativo dell'alunno
   * @param string $tipo Tipo di informazioni da mostrare [V=voti,S=scrutini,A=assenze,N=note,O=osservazioni,T=tutto]
   * @param string $formato Formato della visualizzazione [H=html,P=pdf]
   *
   * @return Response Pagina di risposta
   *
   * @Route("/coordinatore/situazione/alunno/{alunno}/{tipo}/{formato}", name="coordinatore_situazione_alunno",
   *    requirements={"alunno": "\d+", "tipo": "V|S|A|N|O|T", "formato": "H|P"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function situazioneAlunnoAction(EntityManagerInterface $em, SessionInterface $session, StaffUtil $staff,
                                          PdfManager $pdf, $alunno, $tipo, $formato) {
    // inizializza variabili
    $dati = null;
    $info['giudizi']['P']['R'] = [20 => 'NC', 21 => 'Insufficiente', 22 => 'Sufficiente', 23 => 'Discreto', 24 => 'Buono', 25 => 'Distinto', 26 => 'Ottimo'];
    $info['giudizi']['1'] = [30 => 'NC', 31 => 'Scarso', 32 => 'Insufficiente', 33 => 'Mediocre', 34 => 'Sufficiente', 35 => 'Discreto', 36 => 'Buono', 37 => 'Ottimo'];
    $info['condotta']['1'] = [40 => 'NC', 41 => 'Scorretta', 42 => 'Non sempre adeguata', 43 => 'Corretta'];
    $info['giudizi']['F']['R'] = [20 => 'NC', 21 => 'Insufficiente', 22 => 'Sufficiente', 23 => 'Discreto', 24 => 'Buono', 25 => 'Distinto', 26 => 'Ottimo'];
    // controllo alunno
    $alunno = $em->getRepository('App:Alunno')->find($alunno);
    if (!$alunno) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo classe
    $classe = $alunno->getClasse();
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo accesso alla funzione
    if (!($this->getUser() instanceOf Staff) && !($this->getUser() instanceOf Preside)) {
      // coordinatore
      $classi = explode(',', $session->get('/APP/DOCENTE/coordinatore'));
      if (!in_array($classe->getId(), $classi)) {
        // errore
        throw $this->createNotFoundException('exception.invalid_params');
      }
    }
    // pagina di ritorno
    if ($this->getUser() instanceOf Staff) {
      $info['back'] = 'staff_studenti_situazione';
    } else {
      $info['back'] = 'coordinatore_situazione';
    }
    // legge dati
    $dati = $staff->situazione($alunno, $tipo);
    // controllo formato
    if ($formato == 'P') {
      // crea documento PDF
      $pdf->configure($session->get('/CONFIG/ISTITUTO/intestazione'),
        'Situazione alunn'.($alunno->getSesso() == 'M' ? 'o' : 'a').' '.$alunno->getCognome().' '.$alunno->getNome());
      $html = $this->renderView('pdf/situazione_alunno.html.twig', array(
        'classe' => $classe,
        'alunno' => $alunno,
        'dati' => $dati,
        'info' => $info,
        ));
      $pdf->createFromHtml($html);
      // invia il documento
      $nomefile = 'situazione-alunno-'.
        strtoupper(str_replace(' ', '-', $alunno->getCognome().'-'.$alunno->getNome())).'.pdf';
      return $pdf->send($nomefile);
    } else {
      // visualizza pagina
      return $this->render('coordinatore/situazione_alunno.html.twig', array(
        'pagina_titolo' => 'page.coordinatore_situazione',
        'classe' => $classe,
        'alunno' => $alunno,
        'tipo' => $tipo,
        'dati' => $dati,
        'info' => $info,
      ));
    }
  }

  /**
   * Stampa le assenze della classe.
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param StaffUtil $staff Funzioni di utilità per lo staff
   * @param PdfManager $pdf Gestore dei documenti PDF
   * @param int $classe Identificativo della classe
   *
   * @return Response Pagina di risposta
   *
   * @Route("/coordinatore/assenze/stampa/{classe}", name="coordinatore_assenze_stampa",
   *    requirements={"classe": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function assenzeStampaAction(EntityManagerInterface $em, SessionInterface $session, StaffUtil $staff,
                                       PdfManager $pdf, $classe) {
    // inizializza variabili
    $dati = null;
    // controllo classe
    $classe = $em->getRepository('App:Classe')->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo accesso alla funzione
    if (!($this->getUser() instanceOf Staff) && !($this->getUser() instanceOf Preside)) {
      // coordinatore
      $classi = explode(',', $session->get('/APP/DOCENTE/coordinatore'));
      if (!in_array($classe->getId(), $classi)) {
        // errore
        throw $this->createNotFoundException('exception.invalid_params');
      }
    }
    // legge dati
    $dati = $staff->assenze($classe);
    // crea documento PDF
    $pdf->configure($session->get('/CONFIG/ISTITUTO/intestazione'),
      'Assenze della classe '.$classe->getAnno().'ª '.$classe->getSezione());
    $html = $this->renderView('pdf/assenze_classe.html.twig', array(
      'classe' => $classe,
      'dati' => $dati,
      ));
    $pdf->createFromHtml($html);
    // invia il documento
    $nomefile = 'assenze-'.$classe->getAnno().$classe->getSezione().'.pdf';
    return $pdf->send($nomefile);
  }

  /**
   * Stampa le note della classe.
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param StaffUtil $staff Funzioni di utilità per lo staff
   * @param PdfManager $pdf Gestore dei documenti PDF
   * @param int $classe Identificativo della classe
   *
   * @return Response Pagina di risposta
   *
   * @Route("/coordinatore/note/stampa/{classe}", name="coordinatore_note_stampa",
   *    requirements={"classe": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function noteStampaAction(EntityManagerInterface $em, SessionInterface $session, StaffUtil $staff,
                                    PdfManager $pdf, $classe) {
    // inizializza variabili
    $dati = null;
    // controllo classe
    $classe = $em->getRepository('App:Classe')->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo accesso alla funzione
    if (!($this->getUser() instanceOf Staff) && !($this->getUser() instanceOf Preside)) {
      // coordinatore
      $classi = explode(',', $session->get('/APP/DOCENTE/coordinatore'));
      if (!in_array($classe->getId(), $classi)) {
        // errore
        throw $this->createNotFoundException('exception.invalid_params');
      }
    }
    // legge dati
    $dati = $staff->note($classe);
    // crea documento PDF
    $pdf->configure($session->get('/CONFIG/ISTITUTO/intestazione'),
      'Note disciplinari della classe '.$classe->getAnno().'ª '.$classe->getSezione());
    $html = $this->renderView('pdf/note_classe.html.twig', array(
      'classe' => $classe,
      'dati' => $dati,
      ));
    $pdf->createFromHtml($html);
    // invia il documento
    $nomefile = 'note-'.$classe->getAnno().$classe->getSezione().'.pdf';
    return $pdf->send($nomefile);
  }

  /**
   * Stampa le medie dei voti della classe.
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param StaffUtil $staff Funzioni di utilità per lo staff
   * @param PdfManager $pdf Gestore dei documenti PDF
   * @param int $classe Identificativo della classe
   *
   * @return Response Pagina di risposta
   *
   * @Route("/coordinatore/voti/stampa/{classe}", name="coordinatore_voti_stampa",
   *    requirements={"classe": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function votiStampaAction(EntityManagerInterface $em, SessionInterface $session,
                                    StaffUtil $staff, PdfManager $pdf, $classe) {
    // inizializza variabili
    $dati = null;
    // controllo classe
    $classe = $em->getRepository('App:Classe')->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo accesso alla funzione
    if (!($this->getUser() instanceOf Staff) && !($this->getUser() instanceOf Preside)) {
      // coordinatore
      $classi = explode(',', $session->get('/APP/DOCENTE/coordinatore'));
      if (!in_array($classe->getId(), $classi)) {
        // errore
        throw $this->createNotFoundException('exception.invalid_params');
      }
    }
    // legge dati
    $dati = $staff->voti($classe);
    // crea documento PDF
    $pdf->configure($session->get('/CONFIG/ISTITUTO/intestazione'),
      'Medie dei voti della classe '.$classe->getAnno().'ª '.$classe->getSezione());
    $pdf->getHandler()->setPageOrientation('L', true, 20);
    $html = $this->renderView('pdf/voti_classe.html.twig', array(
      'classe' => $classe,
      'dati' => $dati,
      ));
    $pdf->createFromHtml($html);
    // invia il documento
    $nomefile = 'voti-'.$classe->getAnno().$classe->getSezione().'.pdf';
    return $pdf->send($nomefile);
  }

  /**
   * Gestione degli avvisi per la classe.
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param BachecaUtil $bac Funzioni di utilità per la gestione della bacheca
   * @param int $classe Identificativo della classe
   * @param int $pagina Numero di pagina per l'elenco da visualizzare
   *
   * @return Response Pagina di risposta
   *
   * @Route("/coordinatore/avvisi/{classe}/{pagina}", name="coordinatore_avvisi",
   *    requirements={"classe": "\d+", "pagina": "\d+"},
   *    defaults={"classe": 0, "pagina": "0"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function avvisiAction(EntityManagerInterface $em, SessionInterface $session, BachecaUtil $bac,
                               $classe, $pagina) {
    // inizializza variabili
    $dati = null;
    $limite = 20;
    $maxPages = 1;
    // parametro classe
    if ($classe == 0) {
      // recupera parametri da sessione
      $classe = $session->get('/APP/DOCENTE/classe_coordinatore');
    } else {
      // memorizza su sessione
      $session->set('/APP/DOCENTE/classe_coordinatore', $classe);
    }
    // parametro pagina
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $session->get('/APP/ROUTE/coordinatore_avvisi/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $session->set('/APP/ROUTE/coordinatore_avvisi/pagina', $pagina);
    }
    // controllo classe
    if ($classe > 0) {
      $classe = $em->getRepository('App:Classe')->find($classe);
      if (!$classe) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      // controllo accesso alla funzione
      if (!($this->getUser() instanceOf Staff) && !($this->getUser() instanceOf Preside)) {
        // coordinatore
        $classi = explode(',', $session->get('/APP/DOCENTE/coordinatore'));
        if (!in_array($classe->getId(), $classi)) {
          // errore
          throw $this->createNotFoundException('exception.invalid_params');
        }
      }
      // recupera dati
      $dati = $bac->listaAvvisiCoordinatore($pagina, $limite, $this->getUser(), $classe);
      $maxPages = ceil($dati['lista']->count() / $limite);
    }
    // visualizza pagina
    return $this->render('coordinatore/avvisi.html.twig', array(
      'pagina_titolo' => 'page.coordinatore_avvisi',
      'classe' => $classe,
      'dati' => $dati,
      'page' => $pagina,
      'maxPages' => $maxPages,
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
   * @param int $classe Identificativo della classe
   * @param int $id Identificativo dell'avviso
   *
   * @return Response Pagina di risposta
   *
   * @Route("/coordinatore/avvisi/edit/{classe}/{id}", name="coordinatore_avviso_edit",
   *    requirements={"classe": "\d+", "id": "\d+"},
   *    defaults={"id": "0"},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function avvisoEditAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                   TranslatorInterface $trans, BachecaUtil $bac, RegistroUtil $reg,
                                   LogHandler $dblogger, $classe, $id) {
    // controllo classe
    $classe = $em->getRepository('App:Classe')->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    $dati['classe'] = $classe;
    // controllo accesso alla funzione
    if (!($this->getUser() instanceOf Staff) && !($this->getUser() instanceOf Preside)) {
      // coordinatore
      $classi = explode(',', $session->get('/APP/DOCENTE/coordinatore'));
      if (!in_array($classe->getId(), $classi)) {
        // errore
        throw $this->createNotFoundException('exception.invalid_params');
      }
    }
    // controlla azione
    if ($id > 0) {
      // azione edit
      $avviso = $em->getRepository('App:Avviso')->findOneBy(['id' => $id, 'tipo' => 'O']);
      if (!$avviso) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $avviso_old = clone $avviso;
    } else {
      // azione add
      $avviso = (new Avviso())
        ->setTipo('O')
        ->setOggetto($trans->trans('message.avviso_coordinatore_oggetto',
          ['classe' => $classe->getAnno().'ª '.$classe->getSezione()]))
        ->setData(new \DateTime('today'))
        ->addSede($classe->getSede());
      $em->persist($avviso);
      // imposta classe tramite cattedra
      $cattedra = $em->getRepository('App:Cattedra')->findOneBy(['attiva' => 1, 'classe' => $classe]);
      $avviso->setCattedra($cattedra);
    }
    // imposta autore dell'avviso
    $avviso->setDocente($this->getUser());
    // form di inserimento
    $form = $this->createForm(AvvisoType::class, $avviso, ['formMode' => 'coordinatore',
      'returnUrl' => $this->generateUrl('coordinatore_avvisi'),
      'dati' => [(count($avviso->getAnnotazioni()) > 0)]]);
    $form->handleRequest($request);
    // visualizzazione filtri
    $dati['lista'] = '';
    if ($form->get('filtroTipo')->getData() == 'U') {
      $dati['lista'] = $em->getRepository('App:Alunno')->listaAlunni($form->get('filtro')->getData(), 'gs-filtro-');
    }
    if ($form->isSubmitted()) {
      // controllo errori
      if (!$avviso->getDestinatari()) {
        // destinatari non definiti
        $form->addError(new FormError($trans->trans('exception.destinatari_mancanti')));
      }
      if ($form->get('filtroTipo')->getData() == 'U' && empty(implode(',', $form->get('filtro')->getData()))) {
        // errore: filtro vuoto
        $form->addError(new FormError($trans->trans('exception.filtro_utente_nullo')));
      }
      // controlla filtro
      $lista = array();
      $errore = false;
      if ($avviso->getFiltroTipo() == 'U') {
        $lista = $em->getRepository('App:Alunno')
          ->controllaAlunni([$classe->getSede()], $form->get('filtro')->getData(), $errore);
        if ($errore) {
          // utente non valido
          $form->addError(new FormError($trans->trans('exception.filtro_utenti_invalido', ['dest' => ''])));
        }
        $avviso->setFiltro($lista);
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
        // gestione destinatari
        if ($id) {
          // cancella destinatari precedenti e dati lettura
          $em->getRepository('App:AvvisoUtente')->createQueryBuilder('au')
            ->delete()
            ->where('au.avviso=:avviso')
            ->setParameters(['avviso' => $avviso])
            ->getQuery()
            ->execute();
          $em->getRepository('App:AvvisoClasse')->createQueryBuilder('ac')
            ->delete()
            ->where('ac.avviso=:avviso')
            ->setParameters(['avviso' => $avviso])
            ->getQuery()
            ->execute();
        }
        if ($avviso->getFiltroTipo() == 'T') {
          // destinatari solo classe corrente
          $avviso->setFiltroTipo('C')->setFiltro([$classe->getId()]);
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
            ->setUtente($em->getReference('App:Utente', $u));
          $em->persist($obj);
        }
        // imposta classe
        foreach ($dest['classi'] as $c) {
          $obj = (new AvvisoClasse())
            ->setAvviso($avviso)
            ->setClasse($em->getReference('App:Classe', $c));
          $em->persist($obj);
        }
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
          $testo = $bac->testoAvviso($avviso);
          $a = (new Annotazione())
            ->setData($avviso->getData())
            ->setTesto($testo)
            ->setVisibile(false)
            ->setAvviso($avviso)
            ->setClasse($classe)
            ->setDocente($avviso->getDocente());
          $em->persist($a);
          $avviso->addAnnotazione($a);
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
          $dblogger->logAzione('AVVISI', 'Crea avviso coordinatore', array(
            'Avviso' => $avviso->getId(),
            'Annotazioni' => implode(', ', array_map(function ($a) {
                return $a->getId();
              }, $avviso->getAnnotazioni()->toArray())),
          ));
        } else {
          // modifica
          $notifica->setAzione('E');
          $dblogger->logAzione('AVVISI', 'Modifica avviso coordinatore', array(
            'Id' => $avviso->getId(),
            'Testo' => $avviso_old->getTesto(),
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
        return $this->redirectToRoute('coordinatore_avvisi');
      }
    }
    // mostra la pagina di risposta
    return $this->render('coordinatore/avviso_edit.html.twig', array(
      'pagina_titolo' => 'page.coordinatore_avvisi',
      'form' => $form->createView(),
      'form_title' => ($id > 0 ? 'title.modifica_avviso_coordinatore' : 'title.nuovo_avviso_coordinatore'),
      'dati' => $dati,
    ));
  }

  /**
   * Mostra i dettagli di un avviso
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param BachecaUtil $bac Funzioni di utilità per la gestione della bacheca
   * @param int $classe Identificativo della classe
   * @param int $id ID dell'avviso
   *
   * @return Response Pagina di risposta
   *
   * @Route("/coordinatore/avvisi/dettagli/{classe}/{id}", name="coordinatore_avviso_dettagli",
   *    requirements={"classe": "\d+", "id": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function avvisoDettagliAction(EntityManagerInterface $em, SessionInterface $session,
                                       BachecaUtil $bac, $classe, $id) {
    // inizializza
    $dati = null;
    // controllo avviso
    $avviso = $em->getRepository('App:Avviso')->find($id);
    if (!$avviso) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo classe
    $classe = $em->getRepository('App:Classe')->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo accesso alla funzione
    if (!($this->getUser() instanceOf Staff) && !($this->getUser() instanceOf Preside)) {
      // coordinatore
      $classi = explode(',', $session->get('/APP/DOCENTE/coordinatore'));
      if (!in_array($classe->getId(), $classi)) {
        // errore
        throw $this->createNotFoundException('exception.invalid_params');
      }
    }
    if (!$bac->permessoLettura($avviso, $this->getUser())) {
      // errore: non è autore dell'avviso
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // legge dati
    $dati = $bac->dettagliAvviso($avviso);
    // visualizza pagina
    return $this->render('coordinatore/scheda_avviso.html.twig', array(
      'dati' => $dati,
    ));
  }

  /**
   * Cancella avviso
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param LogHandler $dblogger Gestore dei log su database
   * @param BachecaUtil $bac Funzioni di utilità per la gestione della bacheca
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param int $classe Identificativo della classe
   * @param int $id Identificativo dell'avviso
   *
   * @return Response Pagina di risposta
   *
   * @Route("/coordinatore/avvisi/delete/{classe}/{id}", name="coordinatore_avviso_delete",
   *    requirements={"classe": "\d+", "id": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function avvisoDeleteAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                     LogHandler $dblogger, BachecaUtil $bac, RegistroUtil $reg, $classe, $id) {
    // controllo avviso
    $avviso = $em->getRepository('App:Avviso')->findOneBy(['id' => $id, 'tipo' => 'O']);
    if (!$avviso) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo classe
    $classe = $em->getRepository('App:Classe')->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo accesso alla funzione
    if (!($this->getUser() instanceOf Staff) && !($this->getUser() instanceOf Preside)) {
      // coordinatore
      $classi = explode(',', $session->get('/APP/DOCENTE/coordinatore'));
      if (!in_array($classe->getId(), $classi)) {
        // errore
        throw $this->createNotFoundException('exception.invalid_params');
      }
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
    $em->getRepository('App:AvvisoUtente')->createQueryBuilder('au')
      ->delete()
      ->where('au.avviso=:avviso')
      ->setParameters(['avviso' => $avviso])
      ->getQuery()
      ->execute();
    $em->getRepository('App:AvvisoClasse')->createQueryBuilder('ac')
      ->delete()
      ->where('ac.avviso=:avviso')
      ->setParameters(['avviso' => $avviso])
      ->getQuery()
      ->execute();
    // cancella avviso
    $avviso_id = $avviso->getId();
    $em->remove($avviso);
    // ok: memorizza dati
    $em->flush();
    // log azione e notifica
    $notifica = (new Notifica())
      ->setOggettoNome('Avviso')
      ->setOggettoId($avviso_id)
      ->setAzione('D');
    $em->persist($notifica);
    $dblogger->logAzione('AVVISI', 'Cancella avviso coordinatore', array(
      'Id' => $avviso_id,
      'Data' => $avviso->getData()->format('d/m/Y'),
      'Testo' => $avviso->getTesto(),
      'Destinatari' => $avviso->getDestinatari(),
      'Filtro Tipo' => $avviso->getFiltroTipo(),
      'Filtro' => $avviso->getFiltro(),
      'Classe' => $avviso->getCattedra()->getCLasse()->getId(),
      'Docente' => $avviso->getDocente()->getId(),
      'Annotazioni' => implode(', ', $log_annotazioni),
      ));
    // redirezione
    return $this->redirectToRoute('coordinatore_avvisi');
  }

}
