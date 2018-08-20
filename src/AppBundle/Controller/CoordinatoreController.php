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
use Doctrine\ORM\EntityRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
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
use AppBundle\Util\StaffUtil;
use AppBundle\Util\PdfManager;
use AppBundle\Util\RegistroUtil;
use AppBundle\Util\BachecaUtil;
use AppBundle\Util\LogHandler;
use AppBundle\Entity\Staff;
use AppBundle\Entity\Preside;
use AppBundle\Entity\Classe;
use AppBundle\Entity\Avviso;


/**
 * CoordinatoreController - gestione delle funzioni per i coordinatori
 */
class CoordinatoreController extends Controller {

  /**
   * Gestione delle funzioni coordinatore
   *
   * @param SessionInterface $session Gestore delle sessioni
   *
   * @return Response Pagina di risposta
   *
   * @Route("/coordinatore", name="coordinatore")
   * @Method("GET")
   *
   * @Security("has_role('ROLE_DOCENTE')")
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
   * @Route("/coordinatore/classe/", name="coordinatore_classe")
   * @Method("GET")
   *
   * @Security("has_role('ROLE_DOCENTE')")
   */
  public function classeAction(EntityManagerInterface $em, SessionInterface $session) {
    // lista classi coordinatore
    $classi = $em->getRepository('AppBundle:Classe')->createQueryBuilder('c')
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
        $lista = $em->getRepository('AppBundle:Classe')->createQueryBuilder('c')
          ->where('c.sede=:sede')
          ->orderBy('c.sede,c.sezione,c.anno', 'ASC')
          ->setParameters(['sede' => $this->getUser()->getSede()])
          ->getQuery()
          ->getResult();
      } else {
        // tutte le classi
        $lista = $em->getRepository('AppBundle:Classe')->createQueryBuilder('c')
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
   *    defaults={"classe": 0})
   * @Method("GET")
   *
   * @Security("has_role('ROLE_DOCENTE')")
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
      $classe = $em->getRepository('AppBundle:Classe')->find($classe);
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
   *    defaults={"classe": 0})
   * @Method("GET")
   *
   * @Security("has_role('ROLE_DOCENTE')")
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
      $classe = $em->getRepository('AppBundle:Classe')->find($classe);
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
   *    defaults={"classe": 0})
   * @Method("GET")
   *
   * @Security("has_role('ROLE_DOCENTE')")
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
      $classe = $em->getRepository('AppBundle:Classe')->find($classe);
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
   *    defaults={"classe": 0})
   * @Method("GET")
   *
   * @Security("has_role('ROLE_DOCENTE')")
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
      $classe = $em->getRepository('AppBundle:Classe')->find($classe);
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
   *    requirements={"alunno": "\d+", "tipo": "V|S|A|N|O|T", "formato": "H|P"})
   * @Method("GET")
   *
   * @Security("has_role('ROLE_DOCENTE')")
   */
  public function situazioneAlunnoAction(EntityManagerInterface $em, SessionInterface $session, StaffUtil $staff,
                                          PdfManager $pdf, $alunno, $tipo, $formato) {
    // inizializza variabili
    $dati = null;
    $info['giudizi']['P']['R'] = [20 => 'NC', 21 => 'Insufficiente', 22 => 'Sufficiente', 23 => 'Buono', 24 => 'Distinto', 25 => 'Ottimo'];
    // controllo alunno
    $alunno = $em->getRepository('AppBundle:Alunno')->find($alunno);
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
      $info['back'] = 'staff_situazione';
    } else {
      $info['back'] = 'coordinatore_situazione';
    }
    // legge dati
    $dati = $staff->situazione($alunno, $tipo);
    // controllo formato
    if ($formato == 'P') {
      // crea documento PDF
      $pdf->configure('Istituto di Istruzione Superiore',
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
   *    requirements={"classe": "\d+"})
   * @Method("GET")
   *
   * @Security("has_role('ROLE_DOCENTE')")
   */
  public function assenzeStampaAction(EntityManagerInterface $em, SessionInterface $session, StaffUtil $staff,
                                       PdfManager $pdf, $classe) {
    // inizializza variabili
    $dati = null;
    // controllo classe
    $classe = $em->getRepository('AppBundle:Classe')->find($classe);
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
    $pdf->configure('Istituto di Istruzione Superiore',
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
   *    requirements={"classe": "\d+"})
   * @Method("GET")
   *
   * @Security("has_role('ROLE_DOCENTE')")
   */
  public function noteStampaAction(EntityManagerInterface $em, SessionInterface $session, StaffUtil $staff,
                                    PdfManager $pdf, $classe) {
    // inizializza variabili
    $dati = null;
    // controllo classe
    $classe = $em->getRepository('AppBundle:Classe')->find($classe);
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
    $pdf->configure('Istituto di Istruzione Superiore',
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
   *    requirements={"classe": "\d+"})
   * @Method("GET")
   *
   * @Security("has_role('ROLE_DOCENTE')")
   */
  public function votiStampaAction(EntityManagerInterface $em, SessionInterface $session,
                                    StaffUtil $staff, PdfManager $pdf, $classe) {
    // inizializza variabili
    $dati = null;
    // controllo classe
    $classe = $em->getRepository('AppBundle:Classe')->find($classe);
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
    $pdf->configure('Istituto di Istruzione Superiore',
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
   *    defaults={"classe": 0, "pagina": "0"})
   * @Method("GET")
   *
   * @Security("has_role('ROLE_DOCENTE')")
   */
  public function avvisiAction(EntityManagerInterface $em, SessionInterface $session, BachecaUtil $bac,
                                $classe, $pagina) {
    // inizializza variabili
    $dati = null;
    $limite = 15;
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
      $classe = $em->getRepository('AppBundle:Classe')->find($classe);
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
   *    defaults={"id": "0"})
   * @Method({"GET","POST"})
   *
   * @Security("has_role('ROLE_DOCENTE')")
   */
  public function avvisoEditAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                    BachecaUtil $bac, RegistroUtil $reg, LogHandler $dblogger, $classe, $id) {
    // controllo classe
    $classe = $em->getRepository('AppBundle:Classe')->find($classe);
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
    // controlla azione
    if ($id > 0) {
      // azione edit
      $avviso = $em->getRepository('AppBundle:Avviso')->findOneBy(['id' => $id, 'tipo' => 'O']);
      if (!$avviso) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $avviso_old = clone $avviso;
    } else {
      // azione add
      $avviso = (new Avviso())
        ->setTipo('O')
        ->setDestinatariStaff(false)
        ->setDestinatariCoordinatori(false)
        ->setDestinatariDocenti(false)
        ->setDestinatariGenitori(false)
        ->setDestinatariAlunni(false)
        ->setDestinatariIndividuali(false)
        ->setOggetto($this->get('translator')->trans('message.avviso_coordinatore_oggetto',
          ['%classe%' => $classe->getAnno().'ª '.$classe->getSezione()]))
        ->setData(new \DateTime('today'));
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
    foreach ($dest_filtro['utenti'] as $k=>$v) {
      $dest_filtro['genitori'][$k]['letto'] = null;
    }
    // opzione scelta
    $scelta_destinatari = '';
    $scelta_filtro = 'N';
    $scelta_filtro_individuale = array();
    if ($avviso->getDestinatariDocenti()) {
      $scelta_destinatari = 'D';
      $scelta_filtro = 'T';
    }
    if ($avviso->getDestinatariGenitori()) {
      $scelta_destinatari = 'G';
      $scelta_filtro = 'T';
      if ($avviso->getDestinatariIndividuali()) {
        $scelta_filtro = 'I';
        foreach (array_column($dest_filtro['utenti'], 'alunno') as $a) {
          $alunno = $em->getRepository('AppBundle:Alunno')->find($a);
          if ($alunno) {
            $scelta_filtro_individuale[] = $alunno;
          }
        }
      }
    }
    // form di inserimento
    $form = $this->container->get('form.factory')->createNamedBuilder('avviso_edit', FormType::class, $avviso)
      ->add('testo', TextareaType::class, array(
        'label' => 'label.testo',
        'attr' => array('rows' => '4'),
        'required' => true))
      ->add('destinatari', ChoiceType::class, array('label' => false,
        'data' => $scelta_destinatari,
        'choices' => ['label.docenti_classe' => 'D', 'label.genitori_classe' => 'G'],
        'expanded' => true,
        'multiple' => false,
        'label_attr' => ['class' => 'radio-inline gs-mr-4'],
        'mapped' => false,
        'required' => true))
      ->add('filtro', ChoiceType::class, array('label' => false,
        'data' => $scelta_filtro,
        'choices' => ['label.filtro_nessuno' => 'N', 'label.filtro_tutti' => 'T', 'label.filtro_individuale' => 'I'],
        'expanded' => false,
        'multiple' => false,
        'mapped' => false,
        'required' => true))
      ->add('filtroIndividuale', EntityType::class, array('label' => false,
        'data' => $scelta_filtro_individuale,
        'class' => 'AppBundle:Alunno',
        'choice_label' => function ($obj) {
            return $obj->getCognome().' '.$obj->getNome();
          },
        'query_builder' => function (EntityRepository $er) use ($classe) {
            return $er->createQueryBuilder('a')
              ->where('a.classe=:classe AND a.abilitato=:abilitato')
              ->orderBy('a.cognome,a.nome', 'ASC')
              ->setParameters(['classe' => $classe, 'abilitato' => 1]);
          },
        'expanded' => true,
        'multiple' => true,
        'choice_translation_domain' => false,
        'attr' => ['style' => 'width:auto'],
        'label_attr' => ['class' => 'gs-pt-0 checkbox-split-vertical'],
        'mapped' => false,
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start']))
      ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end',
        'onclick' => "location.href='".$this->generateUrl('coordinatore_avvisi')."'"]))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // recupera dati
      $val_staff_filtro = 'N';
      $val_staff_sedi = array();
      $val_destinatari = $form->get('destinatari')->getData();
      $val_filtro = 'N';
      $val_filtro_id = array();
      if ($form->get('filtro')->getData() == 'T') {
        // docenti/genitori della classe
        $val_filtro = 'C';
        $val_filtro_id = [$classe->getId()];
      } elseif ($form->get('filtro')->getData() == 'I') {
        // genitori individuali
        $val_filtro = 'I';
        $val_filtro_id = $form->get('filtroIndividuale')->getData();
      }
      // controllo errori
      if (empty($val_destinatari) || $val_filtro == 'N') {
        // errore: nessun destinatario
        $form->addError(new FormError($this->get('translator')->trans('exception.destinatari_mancanti')));
      }
      if ($val_destinatari == 'G' && $val_filtro == 'I' && count($val_filtro_id) == 0) {
        // errore: filtro vuoto
        $form->addError(new FormError($this->get('translator')->trans('exception.destinatari_filtro_mancanti')));
      }
      // controllo permessi
      if (!$bac->azioneAvviso(($id > 0 ? 'edit' : 'add'), $avviso->getData(), $this->getUser(), ($id > 0 ? $avviso : null))) {
        // errore: avviso non permesso
        $form->addError(new FormError($this->get('translator')->trans('exception.avviso_non_permesso')));
      }
      // modifica dati
      if ($form->isValid()) {
        // destinatari
        $log_destinatari = $bac->modificaFiltriAvviso($avviso, $dest_filtro, $val_staff_filtro, $val_staff_sedi,
          [$val_destinatari], $val_filtro, $val_filtro_id);
        // ok: memorizza dati
        $em->flush();
        // log azione
        if (!$id) {
          // nuovo
          $dblogger->write($this->getUser(), $request->getClientIp(), 'AVVISI', 'Crea avviso coordinatore', __METHOD__, array(
            'Avviso' => $avviso->getId(),
            'Classi aggiunte' => implode(', ', $log_destinatari['classi']['add']),
            'Utenti aggiunti' => implode(', ', array_map(function ($a) {
                return $a['genitore'].'->'.$a['alunno'];
              }, $log_destinatari['utenti']['add'])),
            ));
        } else {
          // modifica
          $dblogger->write($this->getUser(), $request->getClientIp(), 'AVVISI', 'Modifica avviso coordinatore', __METHOD__, array(
            'Id' => $avviso->getId(),
            'Data' => $avviso_old->getData()->format('d/m/Y'),
            'Testo' => $avviso_old->getTesto(),
            'Destinatari docenti' => $avviso_old->getDestinatariDocenti(),
            'Destinatari genitori' => $avviso_old->getDestinatariGenitori(),
            'Destinatari individuali' => $avviso_old->getDestinatariIndividuali(),
            'Classi cancellate' => implode(', ', $log_destinatari['classi']['delete']),
            'Classi aggiunte' => implode(', ', $log_destinatari['classi']['add']),
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
        return $this->redirectToRoute('coordinatore_avvisi');
      }
    }
    // mostra la pagina di risposta
    return $this->render('coordinatore/avviso_edit.html.twig', array(
      'pagina_titolo' => 'page.coordinatore_avvisi',
      'form' => $form->createView(),
      'form_title' => ($id > 0 ? 'title.modifica_avviso_coordinatore' : 'title.nuovo_avviso_coordinatore'),
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
   *    requirements={"classe": "\d+", "id": "\d+"})
   * @Method("GET")
   *
   * @Security("has_role('ROLE_DOCENTE')")
   */
  public function avvisoDettagliAction(EntityManagerInterface $em, SessionInterface $session,
                                        BachecaUtil $bac, $classe, $id) {
    // inizializza
    $dati = null;
    // controllo avviso
    $avviso = $em->getRepository('AppBundle:Avviso')->find($id);
    if (!$avviso) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo classe
    $classe = $em->getRepository('AppBundle:Classe')->find($classe);
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
   *    requirements={"classe": "\d+", "id": "\d+"})
   * @Method({"GET"})
   *
   * @Security("has_role('ROLE_DOCENTE')")
   */
  public function avvisoDeleteAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                      LogHandler $dblogger, BachecaUtil $bac, RegistroUtil $reg, $classe, $id) {
    // controllo avviso
    $avviso = $em->getRepository('AppBundle:Avviso')->findOneBy(['id' => $id, 'tipo' => 'O']);
    if (!$avviso) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo classe
    $classe = $em->getRepository('AppBundle:Classe')->find($classe);
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
    // cancella destinatari
    $log_destinatari = $bac->eliminaFiltriAvviso($avviso);
    // cancella avviso
    $avviso_id = $avviso->getId();
    $em->remove($avviso);
    // ok: memorizza dati
    $em->flush();
    // log azione
    $dblogger->write($this->getUser(), $request->getClientIp(), 'AVVISI', 'Cancella avviso coordinatore', __METHOD__, array(
      'Id' => $avviso_id,
      'Data' => $avviso->getData()->format('d/m/Y'),
      'Testo' => $avviso->getTesto(),
      'Destinatari docenti' => $avviso->getDestinatariDocenti(),
      'Destinatari genitori' => $avviso->getDestinatariGenitori(),
      'Destinatari individuali' => $avviso->getDestinatariIndividuali(),
      'Classi cancellate' => implode(', ', $log_destinatari['classi']),
      'Utenti cancellati' => implode(', ', array_map(function ($a) {
          return $a['genitore'].'->'.$a['alunno'];
        }, $log_destinatari['utenti'])),
      'Docente' => $avviso->getDocente()->getId(),
      ));
    // redirezione
    return $this->redirectToRoute('coordinatore_avvisi');
  }

}

