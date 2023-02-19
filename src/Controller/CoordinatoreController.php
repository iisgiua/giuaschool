<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Controller;

use App\Entity\Annotazione;
use App\Entity\Avviso;
use App\Entity\AvvisoClasse;
use App\Entity\AvvisoUtente;
use App\Entity\Presenza;
use App\Entity\Preside;
use App\Entity\Staff;
use App\Form\AvvisoType;
use App\Form\FiltroType;
use App\Form\PresenzaType;
use App\Util\BachecaUtil;
use App\Util\LogHandler;
use App\Util\PdfManager;
use App\Util\RegistroUtil;
use App\Util\StaffUtil;
use Doctrine\Common\Collections\ArrayCollection;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;


/**
 * CoordinatoreController - gestione delle funzioni per i coordinatori
 *
 * @author Antonello Dessì
 */
class CoordinatoreController extends BaseController {

  /**
   * Gestione delle funzioni coordinatore
   *
   *
   * @return Response Pagina di risposta
   *
   * @Route("/coordinatore", name="coordinatore",
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function coordinatoreAction() {
    if (!($this->getUser() instanceOf Staff) && !($this->getUser() instanceOf Preside)) {
      // coordinatore
      $classi = explode(',', $this->reqstack->getSession()->get('/APP/DOCENTE/coordinatore'));
      if (count($classi) == 1) {
        // coordinatore di una sola classe: vai
        $this->reqstack->getSession()->set('/APP/DOCENTE/classe_coordinatore', $classi[0]);
        return $this->redirectToRoute('coordinatore_assenze', ['classe' => $classi[0]]);
      }
    }
    // staff/preside o coordinatore di più classi
    if ($this->reqstack->getSession()->get('/APP/DOCENTE/classe_coordinatore')) {
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
   *
   * @return Response Pagina di risposta
   *
   * @Route("/coordinatore/classe/", name="coordinatore_classe",
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function classeAction() {
    // lista classi coordinatore
    $classi = $this->em->getRepository('App\Entity\Classe')->createQueryBuilder('c')
      ->where('c.id IN (:lista)')
      ->orderBy('c.sede,c.anno,c.sezione', 'ASC')
      ->setParameters(['lista' => explode(',', $this->reqstack->getSession()->get('/APP/DOCENTE/coordinatore'))])
      ->getQuery()
      ->getResult();
    // lista tutte le classi
    $tutte = array();
    if ($this->getUser() instanceOf Staff) {
      if ($this->getUser()->getSede()) {
        // solo classi della sede
        $lista = $this->em->getRepository('App\Entity\Classe')->createQueryBuilder('c')
          ->where('c.sede=:sede')
          ->orderBy('c.sede,c.sezione,c.anno', 'ASC')
          ->setParameters(['sede' => $this->getUser()->getSede()])
          ->getQuery()
          ->getResult();
      } else {
        // tutte le classi
        $lista = $this->em->getRepository('App\Entity\Classe')->createQueryBuilder('c')
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
   * @param StaffUtil $staff Funzioni di utilità per lo staff
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
  public function noteAction(StaffUtil $staff, $classe) {
    // inizializza variabili
    $dati = null;
    // parametro classe
    if ($classe == 0) {
      // recupera parametri da sessione
      $classe = $this->reqstack->getSession()->get('/APP/DOCENTE/classe_coordinatore');
    } else {
      // memorizza su sessione
      $this->reqstack->getSession()->set('/APP/DOCENTE/classe_coordinatore', $classe);
    }
    // controllo classe
    if ($classe > 0) {
      $classe = $this->em->getRepository('App\Entity\Classe')->find($classe);
      if (!$classe) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      // controllo accesso alla funzione
      if (!($this->getUser() instanceOf Staff) && !($this->getUser() instanceOf Preside)) {
        // coordinatore
        $classi = explode(',', $this->reqstack->getSession()->get('/APP/DOCENTE/coordinatore'));
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
   * @param StaffUtil $staff Funzioni di utilità per lo staff
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
  public function assenzeAction(StaffUtil $staff, $classe) {
    // inizializza variabili
    $dati = null;
    // parametro classe
    if ($classe == 0) {
      // recupera parametri da sessione
      $classe = $this->reqstack->getSession()->get('/APP/DOCENTE/classe_coordinatore');
    } else {
      // memorizza su sessione
      $this->reqstack->getSession()->set('/APP/DOCENTE/classe_coordinatore', $classe);
    }
    // controllo classe
    if ($classe > 0) {
      $classe = $this->em->getRepository('App\Entity\Classe')->find($classe);
      if (!$classe) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      // controllo accesso alla funzione
      if (!($this->getUser() instanceOf Staff) && !($this->getUser() instanceOf Preside)) {
        // coordinatore
        $classi = explode(',', $this->reqstack->getSession()->get('/APP/DOCENTE/coordinatore'));
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
   * @param StaffUtil $staff Funzioni di utilità per lo staff
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
  public function votiAction(StaffUtil $staff, $classe) {
    // inizializza variabili
    $dati = null;
    // parametro classe
    if ($classe == 0) {
      // recupera parametri da sessione
      $classe = $this->reqstack->getSession()->get('/APP/DOCENTE/classe_coordinatore');
    } else {
      // memorizza su sessione
      $this->reqstack->getSession()->set('/APP/DOCENTE/classe_coordinatore', $classe);
    }
    // controllo classe
    if ($classe > 0) {
      $classe = $this->em->getRepository('App\Entity\Classe')->find($classe);
      if (!$classe) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      // controllo accesso alla funzione
      if (!($this->getUser() instanceOf Staff) && !($this->getUser() instanceOf Preside)) {
        // coordinatore
        $classi = explode(',', $this->reqstack->getSession()->get('/APP/DOCENTE/coordinatore'));
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
   * @param StaffUtil $staff Funzioni di utilità per lo staff
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
  public function situazioneAction(StaffUtil $staff, $classe) {
    // inizializza variabili
    $dati = null;
    // parametro classe
    if ($classe == 0) {
      // recupera parametri da sessione
      $classe = $this->reqstack->getSession()->get('/APP/DOCENTE/classe_coordinatore');
    } else {
      // memorizza su sessione
      $this->reqstack->getSession()->set('/APP/DOCENTE/classe_coordinatore', $classe);
    }
    // controllo classe
    if ($classe > 0) {
      $classe = $this->em->getRepository('App\Entity\Classe')->find($classe);
      if (!$classe) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      // controllo accesso alla funzione
      if (!($this->getUser() instanceOf Staff) && !($this->getUser() instanceOf Preside)) {
        // coordinatore
        $classi = explode(',', $this->reqstack->getSession()->get('/APP/DOCENTE/coordinatore'));
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
  public function situazioneAlunnoAction(StaffUtil $staff, PdfManager $pdf, $alunno, $tipo, $formato) {
    // inizializza variabili
    $dati = null;
    $info['giudizi']['P']['R'] = [20 => 'NC', 21 => 'Insufficiente', 22 => 'Sufficiente', 23 => 'Discreto', 24 => 'Buono', 25 => 'Distinto', 26 => 'Ottimo'];
    $info['giudizi']['1'] = [30 => 'NC', 31 => 'Scarso', 32 => 'Insufficiente', 33 => 'Mediocre', 34 => 'Sufficiente', 35 => 'Discreto', 36 => 'Buono', 37 => 'Ottimo'];
    $info['condotta']['1'] = [40 => 'NC', 41 => 'Scorretta', 42 => 'Non sempre adeguata', 43 => 'Corretta'];
    $info['giudizi']['F']['R'] = [20 => 'NC', 21 => 'Insufficiente', 22 => 'Sufficiente', 23 => 'Discreto', 24 => 'Buono', 25 => 'Distinto', 26 => 'Ottimo'];
    // controllo alunno
    $alunno = $this->em->getRepository('App\Entity\Alunno')->find($alunno);
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
      $classi = explode(',', $this->reqstack->getSession()->get('/APP/DOCENTE/coordinatore'));
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
      $pdf->configure($this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
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
  public function assenzeStampaAction(StaffUtil $staff, PdfManager $pdf, $classe) {
    // inizializza variabili
    $dati = null;
    // controllo classe
    $classe = $this->em->getRepository('App\Entity\Classe')->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo accesso alla funzione
    if (!($this->getUser() instanceOf Staff) && !($this->getUser() instanceOf Preside)) {
      // coordinatore
      $classi = explode(',', $this->reqstack->getSession()->get('/APP/DOCENTE/coordinatore'));
      if (!in_array($classe->getId(), $classi)) {
        // errore
        throw $this->createNotFoundException('exception.invalid_params');
      }
    }
    // legge dati
    $dati = $staff->assenze($classe);
    // crea documento PDF
    $pdf->configure($this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
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
  public function noteStampaAction(StaffUtil $staff, PdfManager $pdf, $classe) {
    // inizializza variabili
    $dati = null;
    // controllo classe
    $classe = $this->em->getRepository('App\Entity\Classe')->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo accesso alla funzione
    if (!($this->getUser() instanceOf Staff) && !($this->getUser() instanceOf Preside)) {
      // coordinatore
      $classi = explode(',', $this->reqstack->getSession()->get('/APP/DOCENTE/coordinatore'));
      if (!in_array($classe->getId(), $classi)) {
        // errore
        throw $this->createNotFoundException('exception.invalid_params');
      }
    }
    // legge dati
    $dati = $staff->note($classe);
    // crea documento PDF
    $pdf->configure($this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
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
  public function votiStampaAction(StaffUtil $staff, PdfManager $pdf, $classe) {
    // inizializza variabili
    $dati = null;
    // controllo classe
    $classe = $this->em->getRepository('App\Entity\Classe')->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo accesso alla funzione
    if (!($this->getUser() instanceOf Staff) && !($this->getUser() instanceOf Preside)) {
      // coordinatore
      $classi = explode(',', $this->reqstack->getSession()->get('/APP/DOCENTE/coordinatore'));
      if (!in_array($classe->getId(), $classi)) {
        // errore
        throw $this->createNotFoundException('exception.invalid_params');
      }
    }
    // legge dati
    $dati = $staff->voti($classe);
    // crea documento PDF
    $pdf->configure($this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
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
  public function avvisiAction(BachecaUtil $bac, $classe, $pagina) {
    // inizializza variabili
    $dati = null;
    $limite = 20;
    $maxPages = 1;
    // parametro classe
    if ($classe == 0) {
      // recupera parametri da sessione
      $classe = $this->reqstack->getSession()->get('/APP/DOCENTE/classe_coordinatore');
    } else {
      // memorizza su sessione
      $this->reqstack->getSession()->set('/APP/DOCENTE/classe_coordinatore', $classe);
    }
    // parametro pagina
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $this->reqstack->getSession()->get('/APP/ROUTE/coordinatore_avvisi/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/coordinatore_avvisi/pagina', $pagina);
    }
    // controllo classe
    if ($classe > 0) {
      $classe = $this->em->getRepository('App\Entity\Classe')->find($classe);
      if (!$classe) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      // controllo accesso alla funzione
      if (!($this->getUser() instanceOf Staff) && !($this->getUser() instanceOf Preside)) {
        // coordinatore
        $classi = explode(',', $this->reqstack->getSession()->get('/APP/DOCENTE/coordinatore'));
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
  public function avvisoEditAction(Request $request, TranslatorInterface $trans, BachecaUtil $bac,
                                   RegistroUtil $reg, LogHandler $dblogger, $classe, $id) {
    // controllo classe
    $classe = $this->em->getRepository('App\Entity\Classe')->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    $dati['classe'] = $classe;
    // controllo accesso alla funzione
    if (!($this->getUser() instanceOf Staff) && !($this->getUser() instanceOf Preside)) {
      // coordinatore
      $classi = explode(',', $this->reqstack->getSession()->get('/APP/DOCENTE/coordinatore'));
      if (!in_array($classe->getId(), $classi)) {
        // errore
        throw $this->createNotFoundException('exception.invalid_params');
      }
    }
    // controlla azione
    if ($id > 0) {
      // azione edit
      $avviso = $this->em->getRepository('App\Entity\Avviso')->findOneBy(['id' => $id, 'tipo' => 'O']);
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
        ->addSedi($classe->getSede());
      $this->em->persist($avviso);
      // imposta classe tramite cattedra
      $cattedra = $this->em->getRepository('App\Entity\Cattedra')->findOneBy(['attiva' => 1, 'classe' => $classe]);
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
      $dati['lista'] = $this->em->getRepository('App\Entity\Alunno')->listaAlunni($form->get('filtro')->getData(), 'gs-filtro-');
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
        $lista = $this->em->getRepository('App\Entity\Alunno')
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
            ->setUtente($this->em->getReference('App\Entity\Utente', $u));
          $this->em->persist($obj);
        }
        // imposta classe
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
          $testo = $bac->testoAvviso($avviso);
          $a = (new Annotazione())
            ->setData($avviso->getData())
            ->setTesto($testo)
            ->setVisibile(false)
            ->setAvviso($avviso)
            ->setClasse($classe)
            ->setDocente($avviso->getDocente());
          $this->em->persist($a);
          $avviso->addAnnotazioni($a);
        }
        // ok: memorizza dati
        $this->em->flush();
        // log azione
        if (!$id) {
          // nuovo
          $dblogger->logAzione('AVVISI', 'Crea avviso coordinatore', array(
            'Avviso' => $avviso->getId(),
            'Annotazioni' => implode(', ', array_map(function ($a) {
                return $a->getId();
              }, $avviso->getAnnotazioni()->toArray())),
          ));
        } else {
          // modifica
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
  public function avvisoDettagliAction(BachecaUtil $bac, $classe, $id) {
    // inizializza
    $dati = null;
    // controllo avviso
    $avviso = $this->em->getRepository('App\Entity\Avviso')->find($id);
    if (!$avviso) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo classe
    $classe = $this->em->getRepository('App\Entity\Classe')->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo accesso alla funzione
    if (!($this->getUser() instanceOf Staff) && !($this->getUser() instanceOf Preside)) {
      // coordinatore
      $classi = explode(',', $this->reqstack->getSession()->get('/APP/DOCENTE/coordinatore'));
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
  public function avvisoDeleteAction(Request $request, LogHandler $dblogger, BachecaUtil $bac,
                                     RegistroUtil $reg, $classe, $id) {
    // controllo avviso
    $avviso = $this->em->getRepository('App\Entity\Avviso')->findOneBy(['id' => $id, 'tipo' => 'O']);
    if (!$avviso) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo classe
    $classe = $this->em->getRepository('App\Entity\Classe')->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo accesso alla funzione
    if (!($this->getUser() instanceOf Staff) && !($this->getUser() instanceOf Preside)) {
      // coordinatore
      $classi = explode(',', $this->reqstack->getSession()->get('/APP/DOCENTE/coordinatore'));
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
    $this->em->remove($avviso);
    // ok: memorizza dati
    $this->em->flush();
    // log azione
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

  /**
   * Gestione presenze fuori classe
   *
   * @param Request $request Pagina richiesta
   * @param int $classe Identificativo della classe
   * @param int $pagina Numero di pagina per l'elenco da visualizzare
   *
   * @return Response Pagina di risposta
   *
   * @Route("/coordinatore/presenze/{classe}/{pagina}", name="coordinatore_presenze",
   *    requirements={"classe": "\d+", "pagina": "\d+"},
   *    defaults={"classe": 0, "pagina": 0},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function presenzeAction(Request $request, int $classe, int $pagina): Response {
    // init
    $dati = [];
    $info = [];
    $info['classe'] = null;
    $info['annoInizio'] = null;
    $info['annoFine'] = null;
    // parametro classe
    if ($classe == 0) {
      // recupera parametri da sessione
      $classe = $this->reqstack->getSession()->get('/APP/DOCENTE/classe_coordinatore');
    } else {
      // memorizza su sessione
      $this->reqstack->getSession()->set('/APP/DOCENTE/classe_coordinatore', $classe);
    }
    // recupera criteri dalla sessione
    $criteri = array();
    $criteri['alunno'] = $this->reqstack->getSession()->get('/APP/ROUTE/coordinatore_presenze/alunno', 0);
    $criteri['inizio'] = $this->reqstack->getSession()->get('/APP/ROUTE/coordinatore_presenze/inizio', null);
    $criteri['fine'] = $this->reqstack->getSession()->get('/APP/ROUTE/coordinatore_presenze/fine', null);
    $alunno = ($criteri['alunno'] > 0 ?
      $this->em->getRepository('App\Entity\Alunno')->find($criteri['alunno']) : null);
    if ($criteri['inizio']) {
      $inizio = \DateTime::createFromFormat('Y-m-d', $criteri['inizio']);
    } else {
      $inizio = new \DateTime('tomorrow');
      $criteri['inizio'] = $inizio->format('Y-m-d');
    }
    if ($criteri['fine']) {
      $fine = \DateTime::createFromFormat('Y-m-d', $criteri['fine']);
    } else {
      $fine = \DateTime::createFromFormat('Y-m-d',
        $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_fine'));
      $criteri['fine'] = $fine->format('Y-m-d');
    }
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $this->reqstack->getSession()->get('/APP/ROUTE/coordinatore_presenze/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/coordinatore_presenze/pagina', $pagina);
    }
    if ($classe > 0) {
      // controllo classe
      $classe = $this->em->getRepository('App\Entity\Classe')->find($classe);
      if (!$classe) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      // controllo accesso alla funzione
      if (!($this->getUser() instanceOf Staff) && !($this->getUser() instanceOf Preside)) {
        // coordinatore
        $classi = explode(',', $this->reqstack->getSession()->get('/APP/DOCENTE/coordinatore'));
        if (!in_array($classe->getId(), $classi)) {
          // errore
          throw $this->createNotFoundException('exception.invalid_params');
        }
      }
      // form di ricerca
      $form = $this->createForm(FiltroType::class, null, ['formMode' => 'presenze',
        'values' => [$alunno, $classe->getId(), $inizio, $fine]]);
      $form->handleRequest($request);
      if ($form->isSubmitted() && $form->isValid()) {
        // imposta criteri di ricerca
        $criteri['alunno'] = (is_object($form->get('alunno')->getData()) ?
          $form->get('alunno')->getData()->getId() : 0);
        $criteri['inizio'] = $form->get('inizio')->getData()->format('Y-m-d');
        $criteri['fine'] = $form->get('fine')->getData()->format('Y-m-d');
        $pagina = 1;
        $this->reqstack->getSession()->set('/APP/ROUTE/coordinatore_presenze/alunno', $criteri['alunno']);
        $this->reqstack->getSession()->set('/APP/ROUTE/coordinatore_presenze/inizio', $criteri['inizio']);
        $this->reqstack->getSession()->set('/APP/ROUTE/coordinatore_presenze/fine', $criteri['fine']);
        $this->reqstack->getSession()->set('/APP/ROUTE/coordinatore_presenze/pagina', $pagina);
      }
      // lista fuori classe
      $dati = $this->em->getRepository('App\Entity\Presenza')->fuoriClasse($classe, $criteri, $pagina);
      // imposta informazioni
      $info['classe'] = $classe;
      $info['pagina'] = $pagina;
      $info['oggi'] = new \DateTime('today');
      $dataYMD = $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_inizio');
      $info['annoInizio'] = substr($dataYMD, 8, 2).'/'.substr($dataYMD, 5, 2).'/'.substr($dataYMD, 0, 4);
      $dataYMD = $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_fine');
      $info['annoFine'] = substr($dataYMD, 8, 2).'/'.substr($dataYMD, 5, 2).'/'.substr($dataYMD, 0, 4);
    }
    // mostra la pagina di risposta
    return $this->renderHtml('coordinatore', 'presenze', $dati, $info, [
      isset($form) ? $form->createView() : null]);
  }

  /**
   * Modifica una presenza fuori classe pianificata nel futuro
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $id Identificativo della presenza fuori classe
   * @param int $classe Identificativo della classe
   *
   * @return Response Pagina di risposta
   *
   * @Route("/coordinatore/presenze/edit/{id}/{classe}", name="coordinatore_presenze_edit",
   *    requirements={"id": "\d+", "classe": "\d+"},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function presenzeEditAction(Request $request, TranslatorInterface $trans, RegistroUtil $reg,
                                     LogHandler $dblogger, int $id, int $classe): Response {
    // init
    $dati = [];
    $info = [];
    // controlla presenza
    $presenza = $this->em->getRepository('App\Entity\Presenza')->find($id);
    if (!$presenza) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    $vecchiaPresenza = clone $presenza;
    // controllo classe
    $classe = $this->em->getRepository('App\Entity\Classe')->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo data futura
    $oggi = new \DateTime('today');
    if ($presenza->getData() <= $oggi) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo accesso alla funzione
    if (!($this->getUser() instanceOf Staff) && !($this->getUser() instanceOf Preside)) {
      // coordinatore
      $classi = explode(',', $this->reqstack->getSession()->get('/APP/DOCENTE/coordinatore'));
      if (!in_array($classe->getId(), $classi)) {
        // errore
        throw $this->createNotFoundException('exception.invalid_params');
      }
    }
    // imposta informazioni
    $dataYMD = $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_fine');
    $info['annoFine'] = substr($dataYMD, 8, 2).'/'.substr($dataYMD, 5, 2).'/'.substr($dataYMD, 0, 4);
    // form
    $form = $this->createForm(PresenzaType::class, $presenza, [
      'returnUrl' => $this->generateUrl('coordinatore_presenze'), 'formMode' => 'edit',
      'values' => [$classe->getId()]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // controlla dati
      if ($form->get('data')->getData() <= $oggi) {
        // errore data non è futura
        $form->addError(new FormError($trans->trans('exception.presenze_data_non_futura')));
      }
      if (($form->get('oraTipo')->getData() == 'G' && (!empty($form->get('oraInizio')->getData()) ||
          !empty($form->get('oraFine')->getData()))) ||
          ($form->get('oraTipo')->getData() == 'F' && !empty($form->get('oraFine')->getData()))) {
        // errore tipo con dati errati
        $form->addError(new FormError($trans->trans('exception.presenze_tipo_ora_errato')));
      } elseif (($form->get('oraTipo')->getData() == 'F' && empty($form->get('oraInizio')->getData())) ||
          ($form->get('oraTipo')->getData() == 'I' && empty($form->get('oraInizio')->getData())) ||
          ($form->get('oraTipo')->getData() == 'I' && empty($form->get('oraFine')->getData()))) {
        // errore tipo con dati mancanti
        $form->addError(new FormError($trans->trans('exception.presenze_tipo_ora_mancante')));
      } elseif ($form->get('oraTipo')->getData() == 'I' &&
          $form->get('oraInizio')->getData() > $form->get('oraFine')->getData()) {
        // errore tipo con dati mancanti
        $form->addError(new FormError($trans->trans('exception.presenze_tipo_ora_errato')));
      }
      // controlla permessi
      if (!$reg->azionePresenze($form->get('data')->getData(), $this->getUser(),
          $form->get('alunno')->getData(), $classe)) {
        // errore: azione non permessa
        $form->addError(new FormError($trans->trans('exception.presenze_azione_non_permessa')));
      }
      if ($form->isValid()) {
        // ok: memorizzazione e log
        $dblogger->logModifica('PRESENZE', 'Modifica presenza', $vecchiaPresenza, $presenza);
        // messaggio
        $this->addFlash('success', 'message.update_ok');
        // redirect
        return $this->redirectToRoute('coordinatore_presenze');
      }
    }
    // mostra la pagina di risposta
    return $this->renderHtml('coordinatore', 'presenze_edit', $dati, $info, [$form->createView(),
      'message.required_fields']);
  }

  /**
   * Cancella una presenza fuori classe pianificata nel futuro
   *
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $id Identificativo della presenza fuori classe
   * @param int $classe Identificativo della classe
   *
   * @return Response Pagina di risposta
   *
   * @Route("/coordinatore/presenze/delete/{id}/{classe}", name="coordinatore_presenze_delete",
   *    requirements={"id": "\d+", "classe": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function presenzeDeleteAction(RegistroUtil $reg, LogHandler $dblogger, int $id,
                                       int $classe): Response {
    // controlla presenza
    $presenza = $this->em->getRepository('App\Entity\Presenza')->find($id);
    if (!$presenza) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    $vecchiaPresenza = clone $presenza;
    // controllo classe
    $classe = $this->em->getRepository('App\Entity\Classe')->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo data futura
    $oggi = new \DateTime('today');
    if ($presenza->getData() <= $oggi) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo accesso alla funzione
    if (!($this->getUser() instanceOf Staff) && !($this->getUser() instanceOf Preside)) {
      // coordinatore
      $classi = explode(',', $this->reqstack->getSession()->get('/APP/DOCENTE/coordinatore'));
      if (!in_array($classe->getId(), $classi)) {
        // errore
        throw $this->createNotFoundException('exception.invalid_params');
      }
    }
    // controlla permessi
    if (!$reg->azionePresenze($presenza->getData(), $this->getUser(), $presenza->getAlunno(), $classe)) {
      // errore: azione non permessa
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // cancella presenza
    $this->em->remove($presenza);
    // ok: memorizzazione e log
    $dblogger->logRimozione('PRESENZE', 'Cancella presenza', $vecchiaPresenza);
    // messaggio
    $this->addFlash('success', 'message.update_ok');
    // redirect
    return $this->redirectToRoute('coordinatore_presenze');
  }

  /**
   * Aggiunge nuove presenze fuori classe pianificate nel futuro
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $classe Identificativo della classe
   *
   * @return Response Pagina di risposta
   *
   * @Route("/coordinatore/presenze/add/{classe}", name="coordinatore_presenze_add",
   *    requirements={"classe": "\d+"},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function presenzeAddAction(Request $request, TranslatorInterface $trans, RegistroUtil $reg,
                                    LogHandler $dblogger, int $classe): Response {
    // init
    $dati = [];
    $info = [];
    // controllo classe
    $classe = $this->em->getRepository('App\Entity\Classe')->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo accesso alla funzione
    if (!($this->getUser() instanceOf Staff) && !($this->getUser() instanceOf Preside)) {
      // coordinatore
      $classi = explode(',', $this->reqstack->getSession()->get('/APP/DOCENTE/coordinatore'));
      if (!in_array($classe->getId(), $classi)) {
        // errore
        throw $this->createNotFoundException('exception.invalid_params');
      }
    }
    // imposta informazioni
    $dataYMD = $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_fine');
    $info['annoFine'] = substr($dataYMD, 8, 2).'/'.substr($dataYMD, 5, 2).'/'.substr($dataYMD, 0, 4);
    // form
    $form = $this->createForm(PresenzaType::class, null, [
      'returnUrl' => $this->generateUrl('coordinatore_presenze'), 'formMode' => 'add',
      'values' => [$classe->getId()]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // controlla dati
      $alunni = $form->get('alunni')->getData();
      $dataInizio = $form->get('data')->getData();
      $dataFine = $form->get('dataFine')->getData();
      $settimana = $form->get('settimana')->getData();
      if (count($alunni) == 0) {
        // errore alunni non indicati
        $form->addError(new FormError($trans->trans('exception.presenze_alunni_mancanti')));
      }
      $oggi = new \DateTime('today');
      if ($dataInizio <= $oggi) {
        // errore data non è futura
        $form->addError(new FormError($trans->trans('exception.presenze_data_non_futura')));
      }
      if ($dataFine < $dataInizio) {
        // errore intervallo date
        $form->addError(new FormError($trans->trans('exception.presenze_intervallo_date')));
      }
      if (count($settimana) == 0) {
        // errore periodicità settimanale
        $form->addError(new FormError($trans->trans('exception.presenze_periodicita')));
      }
      if (($form->get('oraTipo')->getData() == 'G' && (!empty($form->get('oraInizio')->getData()) ||
          !empty($form->get('oraFine')->getData()))) ||
          ($form->get('oraTipo')->getData() == 'F' && !empty($form->get('oraFine')->getData()))) {
        // errore tipo con dati errati
        $form->addError(new FormError($trans->trans('exception.presenze_tipo_ora_errato')));
      } elseif (($form->get('oraTipo')->getData() == 'F' && empty($form->get('oraInizio')->getData())) ||
          ($form->get('oraTipo')->getData() == 'I' && empty($form->get('oraInizio')->getData())) ||
          ($form->get('oraTipo')->getData() == 'I' && empty($form->get('oraFine')->getData()))) {
        // errore tipo con dati mancanti
        $form->addError(new FormError($trans->trans('exception.presenze_tipo_ora_mancante')));
      } elseif ($form->get('oraTipo')->getData() == 'I' &&
          $form->get('oraInizio')->getData() > $form->get('oraFine')->getData()) {
        // errore tipo con dati mancanti
        $form->addError(new FormError($trans->trans('exception.presenze_tipo_ora_errato')));
      }
      // genera date
      $listaDate = [];
      while ($dataInizio <= $dataFine) {
        $giorno = $dataInizio->format('w');
        if (in_array($giorno, $settimana, true) && !$reg->controlloData($dataInizio, $classe->getSede())) {
          // data presente in settimana
          $listaDate[] = clone $dataInizio;
        }
        // data successiva
        $dataInizio->modify('+1 day');
      }
      if (count($listaDate) == 0) {
        // errore nessuna data
        $form->addError(new FormError($trans->trans('exception.presenze_data_mancante')));
      }
      // controlla permessi (solo data inziale)
      foreach ($alunni as $alunno) {
        if (!empty($listaDate) &&
            !$reg->azionePresenze($listaDate[0], $this->getUser(), $alunno, $classe)) {
          // errore: azione non permessa
          $form->addError(new FormError($trans->trans('exception.presenze_azione_non_permessa')));
        }
      }
      if ($form->isValid()) {
        // ok: memorizzazione e log
        foreach ($alunni as $alunno) {
          foreach ($listaDate as $data) {
            if ($this->em->getRepository('App\Entity\Presenza')->findOneBy(['alunno' => $alunno,
                'data' => $data])) {
              // salta fuori classe esistente
              continue;
            }
            $presenza = (new Presenza())
              ->setData($data)
              ->setOraInizio($form->get('oraInizio')->getData())
              ->setOraFine($form->get('oraFine')->getData())
              ->setTipo($form->get('tipo')->getData())
              ->setDescrizione($form->get('descrizione')->getData())
              ->setAlunno($alunno);
            $this->em->persist($presenza);
            $dblogger->logCreazione('PRESENZE', 'Aggiunge presenza', $presenza);
          }
        }
        // messaggio
        $this->addFlash('success', 'message.update_ok');
        // redirect
        return $this->redirectToRoute('coordinatore_presenze');
      }
    }
    // mostra la pagina di risposta
    return $this->renderHtml('coordinatore', 'presenze_add', $dati, $info, [$form->createView(),
      'message.required_fields']);
  }

}
