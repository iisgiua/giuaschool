<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Controller;

use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\ExpressionLanguage\Expression;
use DateTime;
use IntlDateFormatter;
use App\Entity\Festivita;
use App\Entity\Materia;
use App\Entity\Configurazione;
use App\Entity\Esito;
use App\Entity\Avviso;
use App\Entity\Alunno;
use App\Entity\Assenza;
use App\Entity\Entrata;
use App\Entity\Scrutinio;
use App\Entity\Uscita;
use App\Form\MessageType;
use App\Util\AgendaUtil;
use App\Util\BachecaUtil;
use App\Util\GenitoriUtil;
use App\Util\LogHandler;
use App\Util\RegistroUtil;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;


/**
 * GenitoriController - funzioni per i genitori
 *
 * @author Antonello Dessì
 */
class GenitoriController extends BaseController {

  /**
   * Mostra lezioni svolte
   *
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param GenitoriUtil $gen Funzioni di utilità per i genitori
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param string $data Data del giorno da visualizzare (AAAA-MM-GG)
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/genitori/lezioni/{data}', name: 'genitori_lezioni', requirements: ['data' => '\d\d\d\d-\d\d-\d\d'], defaults: ['data' => '0000-00-00'], methods: ['GET'])]
  #[IsGranted(attribute: new Expression("is_granted('ROLE_GENITORE') or is_granted('ROLE_ALUNNO')"))]
  public function lezioni(TranslatorInterface $trans, GenitoriUtil $gen, RegistroUtil $reg,
                          string $data): Response {
    // inizializza variabili
    $lista_festivi = null;
    $errore = null;
    $dati = null;
    $info = null;
    $settimana = ['Domenica', 'Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato'];
    $mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    $data_succ = null;
    $data_prec = null;
    // parametro data
    if ($data == '0000-00-00') {
      // data non specificata
      if ($this->reqstack->getSession()->get('/APP/GENITORE/data_lezione')) {
        // recupera data da sessione
        $data_obj = DateTime::createFromFormat('Y-m-d', $this->reqstack->getSession()->get('/APP/GENITORE/data_lezione'));
      } else {
        // imposta data odierna
        $data_obj = new DateTime();
      }
    } else {
      // imposta data indicata e la memorizza in sessione
      $data_obj = DateTime::createFromFormat('Y-m-d', $data);
      $this->reqstack->getSession()->set('/APP/GENITORE/data_lezione', $data);
    }
    // legge l'alunno
    if ($this->getUser() instanceOf Alunno) {
      // utente è alunno
      $alunno = $this->getUser();
    } else {
      // utente è genitore
      $alunno = $gen->alunno($this->getUser());
      if (!$alunno) {
        // errore
        throw $this->createNotFoundException('exception.invalid_params');
      }
    }
    // legge la classe (può essere null)
    $classe = $alunno->getClasse();
    // data in formato stringa
    $formatter = new IntlDateFormatter('it_IT', IntlDateFormatter::SHORT, IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $info['data_label'] =  $formatter->format($data_obj);
    if ($classe) {
      // data prec/succ
      $data_succ = (clone $data_obj);
      $data_succ = $this->em->getRepository(Festivita::class)->giornoSuccessivo($data_succ);
      if ($data_succ && $data_succ->format('Y-m-d') > (new DateTime())->format('Y-m-d')) {
        $data_succ = null;
      }
      $data_prec = (clone $data_obj);
      $data_prec = $this->em->getRepository(Festivita::class)->giornoPrecedente($data_prec);
      // recupera festivi per calendario
      $lista_festivi = $reg->listaFestivi($classe->getSede());
      // controllo data
      $errore = $reg->controlloData($data_obj, $classe->getSede());
      if (!$errore) {
        // non festivo: recupera dati
        $dati = $gen->lezioni($data_obj, $classe, $alunno);
      }
    } else {
      // nessuna classe
      $errore = $trans->trans('exception.genitori_classe_nulla', ['sex' => $alunno->getSesso() == 'M' ? 'o' : 'a']);
      $lista_festivi = '[]';
    }
    // visualizza pagina
    return $this->render('ruolo_genitore/lezioni.html.twig', [
      'pagina_titolo' => 'page.genitori_lezioni',
      'alunno' => $alunno,
      'classe' => $classe,
      'data' => $data_obj->format('Y-m-d'),
      'data_succ' => $data_succ,
      'data_prec' => $data_prec,
      'settimana' => $settimana,
      'mesi' => $mesi,
      'errore' => $errore,
      'lista_festivi' => $lista_festivi,
      'info' => $info,
      'dati' => $dati]);
  }

  /**
   * Mostra gli argomenti e le attività delle lezioni svolte.
   *
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param GenitoriUtil $gen Funzioni di utilità per i genitori
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param int $idmateria Identificatore materia da visualizzare
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/genitori/argomenti/{idmateria}', name: 'genitori_argomenti', requirements: ['idmateria' => '\d+'], defaults: ['idmateria' => 0], methods: ['GET'])]
  #[IsGranted(attribute: new Expression("is_granted('ROLE_GENITORE') or is_granted('ROLE_ALUNNO')"))]
  public function argomenti(TranslatorInterface $trans, GenitoriUtil $gen,
                            RegistroUtil $reg, int $idmateria): Response {
    // inizializza variabili
    $template = 'ruolo_genitore/argomenti.html.twig';
    $errore = null;
    $materie = null;
    $info = null;
    $dati = null;
    // parametro materia
    if ($idmateria > 0) {
      $materia = $this->em->getRepository(Materia::class)->find($idmateria);
      if (!$materia) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
    } else {
      $materia = null;
    }
    // legge l'alunno
    if ($this->getUser() instanceOf Alunno) {
      // utente è alunno
      $alunno = $this->getUser();
    } else {
      // utente è genitore
      $alunno = $gen->alunno($this->getUser());
      if (!$alunno) {
        // errore
        throw $this->createNotFoundException('exception.invalid_params');
      }
    }
    // legge la classe
    $classe = $reg->classeInData(new DateTime(), $alunno);
    if ($classe) {
      // lista materie
      $materie = $gen->materie($classe, ($alunno->getBes() == 'H'));
      if ($materia && array_search($idmateria, array_column($materie, 'id')) !== false) {
        // materia indicate e presente in cattedre di classe
        $info['materia'] = $materia->getNome();
        // recupera dati
        if ($materia->getTipo() == 'S') {
          // sostegno
          $dati = $gen->argomentiSostegno($classe, $alunno);
          $template = 'ruolo_genitore/argomenti_sostegno.html.twig';
        } else {
          // materia curricolare
          $dati = $gen->argomenti($classe, $materia, $alunno);
        }
      } else {
        // materia non specificata o non presente in cattedre di classe
        $info['materia'] = $trans->trans('label.scelta_materia');
      }
    } else {
      // nessuna classe
      $errore = $trans->trans('exception.genitori_classe_nulla', ['sex' => $alunno->getSesso() == 'M' ? 'o' : 'a']);
    }
    // visualizza pagina
    return $this->render($template, [
      'pagina_titolo' => 'page.genitori_argomenti',
      'idmateria' => $idmateria,
      'alunno' => $alunno,
      'classe' => $classe,
      'errore' => $errore,
      'materie' => $materie,
      'info' => $info,
      'dati' => $dati]);
  }

  /**
   * Mostra le valutazioni dell'alunno.
   *
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param GenitoriUtil $gen Funzioni di utilità per i genitori
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param int $idmateria Identificatore materia da visualizzare
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/genitori/voti/{idmateria}', name: 'genitori_voti', requirements: ['idmateria' => '\d+'], defaults: ['idmateria' => 0], methods: ['GET'])]
  #[IsGranted(attribute: new Expression("is_granted('ROLE_GENITORE') or is_granted('ROLE_ALUNNO')"))]
  public function voti(TranslatorInterface $trans, GenitoriUtil $gen,
                       RegistroUtil $reg, int $idmateria): Response {
    // inizializza variabili
    $errore = null;
    $materie = null;
    $info = null;
    $dati = null;
    $template = 'ruolo_genitore/voti.html.twig';
    // parametro materia
    if ($idmateria > 0) {
      $materia = $this->em->getRepository(Materia::class)->find($idmateria);
      if (!$materia) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
    } else {
      $materia = null;
    }
    // legge l'alunno
    if ($this->getUser() instanceOf Alunno) {
      // utente è alunno
      $alunno = $this->getUser();
    } else {
      // utente è genitore
      $alunno = $gen->alunno($this->getUser());
      if (!$alunno) {
        // errore
        throw $this->createNotFoundException('exception.invalid_params');
      }
    }
    // legge la classe (può essere null)
    $classe = $reg->classeInData(new DateTime(), $alunno);
    if ($classe) {
      // lista materie
      $materie = $gen->materie($classe, false);
      $materie = array_merge(
        [['id' => 0, 'nomeBreve' => $trans->trans('label.ogni_materia')]],
        $materie);
      if ($materia && array_search($idmateria, array_column($materie, 'id')) !== false) {
        // materia indicate e presente in cattedre di classe
        $info['materia'] = $materia->getNome();
        $template = 'ruolo_genitore/voti_materia.html.twig';
      } else {
        // materia non specificata o non presente in cattedre di classe
        $info['materia'] = $trans->trans('label.ogni_materia');
      }
      // recupera dati
      $dati = $gen->voti($classe, $materia, $alunno);
    } else {
      // nessuna classe
      $errore = $trans->trans('exception.genitori_classe_nulla', ['sex' => $alunno->getSesso() == 'M' ? 'o' : 'a']);
    }
    // visualizza pagina
    return $this->render($template, [
      'pagina_titolo' => 'page.genitori_voti',
      'idmateria' => $idmateria,
      'alunno' => $alunno,
      'classe' => $classe,
      'errore' => $errore,
      'materie' => $materie,
      'info' => $info,
      'dati' => $dati]);
  }

  /**
   * Mostra le assenze dell'alunno.
   *
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param GenitoriUtil $gen Funzioni di utilità per i genitori
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param int $posizione Posizione per lo scrolling verticale della finestra
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/genitori/assenze/{posizione}', name: 'genitori_assenze', requirements: ['posizione' => '\d+'], defaults: ['posizione' => 0], methods: ['GET'])]
  #[IsGranted(attribute: new Expression("is_granted('ROLE_GENITORE') or is_granted('ROLE_ALUNNO')"))]
  public function assenze(TranslatorInterface $trans, GenitoriUtil $gen, RegistroUtil $reg,
                          int $posizione): Response {
    // inizializza variabili
    $errore = null;
    $dati = null;
    // legge l'alunno
    if ($this->getUser() instanceOf Alunno) {
      // utente è alunno
      $alunno = $this->getUser();
    } else {
      // utente è genitore
      $alunno = $gen->alunno($this->getUser());
      if (!$alunno) {
        // errore
        throw $this->createNotFoundException('exception.invalid_params');
      }
    }
    // legge la classe (può essere null)
    $classe = $reg->classeInData(new DateTime(), $alunno);
    if ($classe) {
      // recupera dati
      $dati = $gen->assenze($classe, $alunno);
      $dati['giustifica'] = $gen->giusticazioneOnline($this->getUser());
    } else {
      // nessuna classe
      $errore = $trans->trans('exception.genitori_classe_nulla', ['sex' => $alunno->getSesso() == 'M' ? 'o' : 'a']);
    }
    // visualizza pagina
    return $this->render('ruolo_genitore/assenze.html.twig', [
      'pagina_titolo' => 'page.genitori_assenze',
      'alunno' => $alunno,
      'classe' => $classe,
      'errore' => $errore,
      'dati' => $dati,
      'posizione' => $posizione]);
  }

  /**
   * Mostra le note dell'alunno.
   *
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param GenitoriUtil $gen Funzioni di utilità per i genitori
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/genitori/note/', name: 'genitori_note', methods: ['GET'])]
  #[IsGranted(attribute: new Expression("is_granted('ROLE_GENITORE') or is_granted('ROLE_ALUNNO')"))]
  public function note(TranslatorInterface $trans, GenitoriUtil $gen, RegistroUtil $reg): Response {
    // inizializza variabili
    $errore = null;
    $dati = null;
    // legge l'alunno
    if ($this->getUser() instanceOf Alunno) {
      // utente è alunno
      $alunno = $this->getUser();
    } else {
      // utente è genitore
      $alunno = $gen->alunno($this->getUser());
      if (!$alunno) {
        // errore
        throw $this->createNotFoundException('exception.invalid_params');
      }
    }
    // legge la classe (può essere null)
    $classe = $reg->classeInData(new DateTime(), $alunno);
    if ($classe) {
      // recupera dati
      $dati = $gen->note($classe, $alunno);
    } else {
      // nessuna classe
      $errore = $trans->trans('exception.genitori_classe_nulla', ['sex' => $alunno->getSesso() == 'M' ? 'o' : 'a']);
    }
    // visualizza pagina
    return $this->render('ruolo_genitore/note.html.twig', [
      'pagina_titolo' => 'page.genitori_note',
      'alunno' => $alunno,
      'classe' => $classe,
      'errore' => $errore,
      'dati' => $dati]);
  }

  /**
   * Mostra le osservazioni dei docenti.
   *
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param GenitoriUtil $gen Funzioni di utilità per i genitori
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/genitori/osservazioni/', name: 'genitori_osservazioni', methods: ['GET'])]
  #[IsGranted('ROLE_GENITORE')]
  public function osservazioni(TranslatorInterface $trans, GenitoriUtil $gen,
                               RegistroUtil $reg): Response {
    // inizializza variabili
    $errore = null;
    $dati = null;
    // legge l'alunno
    $alunno = $gen->alunno($this->getUser());
    if (!$alunno) {
      // errore
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // legge la classe (può essere null)
    $classe = $reg->classeInData(new DateTime(), $alunno);
    if ($classe) {
      // recupera dati
      $dati = $gen->osservazioni($alunno);
    } else {
      // nessuna classe
      $errore = $trans->trans('exception.genitori_classe_nulla', ['sex' => $alunno->getSesso() == 'M' ? 'o' : 'a']);
    }
    // visualizza pagina
    return $this->render('ruolo_genitore/osservazioni.html.twig', [
      'pagina_titolo' => 'page.genitori_osservazioni',
      'alunno' => $alunno,
      'classe' => $classe,
      'errore' => $errore,
      'dati' => $dati]);
  }

  /**
   * Mostra le pagelle dell'alunno.
   *
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param GenitoriUtil $gen Funzioni di utilità per i genitori
   * @param string $periodo Periodo dello scrutinio
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/genitori/pagelle/{periodo}', name: 'genitori_pagelle', requirements: ['periodo' => 'A|P|S|F|G|R|X'], defaults: ['periodo' => '0'], methods: ['GET'])]
  #[IsGranted(attribute: new Expression("is_granted('ROLE_GENITORE') or is_granted('ROLE_ALUNNO')"))]
  public function pagelle(TranslatorInterface $trans, GenitoriUtil $gen, string $periodo): Response {
    // inizializza variabili
    $errore = null;
    $dati = [];
    $lista_periodi = null;
    $info = [];
    // legge l'alunno
    if ($this->getUser() instanceOf Alunno) {
      // utente è alunno
      $alunno = $this->getUser();
    } else {
      // utente è genitore
      $alunno = $gen->alunno($this->getUser());
      if (!$alunno) {
        // errore
        throw $this->createNotFoundException('exception.invalid_params');
      }
    }
    // legge la classe (può essere null)
    $classe = $alunno->getClasse();
    // legge lista periodi
    $dati_periodi = $gen->pagelleAlunno($alunno, $classe);
    if (!empty($dati_periodi)) {
      // seleziona scrutinio indicato o ultimo
      $scrutinio = $dati_periodi[0][1];
      foreach ($dati_periodi as $per) {
        if ($per[0] == $periodo) {
          $scrutinio = $per[1];
          // periodo indicato è presente
          break;
        }
      }
      // lista periodi ammessi
      foreach ($dati_periodi as $per) {
        $lista_periodi[$per[0]] = ($per[1] instanceOf Scrutinio ? $per[1]->getStato() : 'C');
      }
      // visualizza pagella o lista periodi
      $periodo = null;
      if ($scrutinio) {
        // pagella
        $periodo = ($scrutinio instanceOf Scrutinio ? $scrutinio->getPeriodo() : 'A');
        $classe = $scrutinio->getClasse();
        if ($periodo == 'A') {
          // precedente A.S.
          $dati = $gen->pagellePrecedenti($alunno);
          // legge valutazioni da configurazione
          $valutazioni['R'] = unserialize($this->em->getRepository(Configurazione::class)->getParametro('voti_finali_R'));
          $valutazioni['E'] = unserialize($this->em->getRepository(Configurazione::class)->getParametro('voti_finali_E'));
          $valutazioni['N'] = unserialize($this->em->getRepository(Configurazione::class)->getParametro('voti_finali_N'));
          $valutazioni['C'] = unserialize($this->em->getRepository(Configurazione::class)->getParametro('voti_finali_C'));
          $listaValori = explode(',', (string) $valutazioni['R']['valori']);
          $listaVoti = explode(',', (string) $valutazioni['R']['votiAbbr']);
          foreach ($listaValori as $key=>$val) {
            $valutazioni['R']['lista'][$val] = trim($listaVoti[$key], '"');
          }
          $listaValori = explode(',', (string) $valutazioni['E']['valori']);
          $listaVoti = explode(',', (string) $valutazioni['E']['votiAbbr']);
          foreach ($listaValori as $key=>$val) {
            $valutazioni['E']['lista'][$val] = trim($listaVoti[$key], '"');
          }
          $listaValori = explode(',', (string) $valutazioni['N']['valori']);
          $listaVoti = explode(',', (string) $valutazioni['N']['votiAbbr']);
          foreach ($listaValori as $key=>$val) {
            $valutazioni['N']['lista'][$val] = trim($listaVoti[$key], '"');
          }
          $listaValori = explode(',', (string) $valutazioni['C']['valori']);
          $listaVoti = explode(',', (string) $valutazioni['C']['votiAbbr']);
          foreach ($listaValori as $key=>$val) {
            $valutazioni['C']['lista'][$val] = trim($listaVoti[$key], '"');
          }
          $dati['valutazioni'] = $valutazioni;
        } else {
          // altri periodi
          $dati = $gen->pagelle($classe, $alunno, $periodo);
          // legge valutazioni da scrutinio
          $dati['valutazioni'] = $this->em->getRepository(Scrutinio::class)
            ->findOneBy(['classe' => $classe, 'periodo' => $periodo, 'stato' => 'C'])
            ->getDato('valutazioni');
          // imposta presa visione
          $this->em->getRepository(Esito::class)->presaVisione($dati['esito'], $this->getUser());
        }
      }
    } else {
      // nessun dato
      $errore = $trans->trans('exception.dati_non_presenti');
    }
    // visualizza pagina
    return $this->render('ruolo_genitore/pagelle.html.twig', [
      'pagina_titolo' => 'page.genitori_pagelle',
      'alunno' => $alunno,
      'classe' => $classe,
      'errore' => $errore,
      'dati' => $dati,
      'info' => $info,
      'periodo' => $periodo,
      'lista_periodi' => $lista_periodi]);
  }

  /**
   * Visualizza gli avvisi destinati ai genitori
   *
   * @param Request $request Pagina richiesta
   * @param BachecaUtil $bac Funzioni di utilità per la gestione della bacheca
   * @param int $pagina Numero di pagina per l'elenco da visualizzare
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/genitori/avvisi/{pagina}', name: 'genitori_avvisi', requirements: ['pagina' => '\d+'], defaults: ['pagina' => '0'], methods: ['GET', 'POST'])]
  #[IsGranted(attribute: new Expression("is_granted('ROLE_GENITORE') or is_granted('ROLE_ALUNNO')"))]
  public function avvisi(Request $request, BachecaUtil $bac, int $pagina): Response {
    // inizializza variabili
    $dati = null;
    $limite = 20;
    // recupera criteri dalla sessione
    $cerca = [];
    $cerca['visualizza'] = $this->reqstack->getSession()->get('/APP/ROUTE/genitori_avvisi/visualizza', 'T');
    $cerca['oggetto'] = $this->reqstack->getSession()->get('/APP/ROUTE/genitori_avvisi/oggetto', '');
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $this->reqstack->getSession()->get('/APP/ROUTE/genitori_avvisi/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/genitori_avvisi/pagina', $pagina);
    }
    // form di ricerca
    $form = $this->container->get('form.factory')->createNamedBuilder('bacheca_avvisi_genitori', FormType::class)
      ->add('visualizza', ChoiceType::class, ['label' => 'label.avvisi_filtro_visualizza',
        'data' => $cerca['visualizza'],
        'choices' => ['label.avvisi_da_leggere' => 'D', 'label.avvisi_tutti' => 'T'],
        'label_attr' => ['class' => 'sr-only'],
        'choice_attr' => fn() => ['class' => 'gs-no-placeholder'],
        'attr' => ['class' => 'gs-placeholder'],
        'required' => true])
      ->add('oggetto', TextType::class, ['label' => 'label.avvisi_filtro_oggetto',
        'data' => $cerca['oggetto'],
        'attr' => ['placeholder' => 'label.oggetto', 'class' => 'gs-placeholder'],
        'label_attr' => ['class' => 'sr-only'],
        'required' => false])
      ->add('submit', SubmitType::class, ['label' => 'label.search'])
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $cerca['visualizza'] = $form->get('visualizza')->getData();
      $cerca['oggetto'] = $form->get('oggetto')->getData();
      $pagina = 1;
      $this->reqstack->getSession()->set('/APP/ROUTE/genitori_avvisi/visualizza', $cerca['visualizza']);
      $this->reqstack->getSession()->set('/APP/ROUTE/genitori_avvisi/oggetto', $cerca['oggetto']);
      $this->reqstack->getSession()->set('/APP/ROUTE/genitori_avvisi/pagina', $pagina);
    }
    // recupera dati
    $dati = $bac->bachecaAvvisi($cerca, $pagina, $limite, $this->getUser());
    // mostra la pagina di risposta
    return $this->render('bacheca/avvisi_genitori.html.twig', [
      'pagina_titolo' => 'page.genitori_avvisi',
      'form' => $form->createView(),
      'form_help' => null,
      'form_success' => null,
      'page' => $pagina,
      'maxPages' => ceil($dati['lista']->count() / $limite),
      'dati' => $dati]);
  }

  /**
   * Mostra i dettagli di un avviso destinato al genitore o all'alunno
   *
   * @param BachecaUtil $bac Funzioni di utilità per la gestione della bacheca
   * @param int $id ID dell'avviso
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/genitori/avvisi/dettagli/{id}', name: 'genitori_avvisi_dettagli', requirements: ['id' => '\d+'], methods: ['GET'])]
  #[IsGranted(attribute: new Expression("is_granted('ROLE_GENITORE') or is_granted('ROLE_ALUNNO')"))]
  public function avvisiDettagli(BachecaUtil $bac, int $id): Response {
    // inizializza
    $dati = null;
    // controllo avviso
    $avviso = $this->em->getRepository(Avviso::class)->find($id);
    if (!$avviso) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    if (!$bac->destinatario($avviso, $this->getUser())) {
      // errore: non è destinatario dell'avviso
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // legge dati
    $dati = $bac->dettagliAvviso($avviso);
    // aggiorna lettura
    $bac->letturaAvviso($avviso, $this->getUser());
    // visualizza pagina
    return $this->render('bacheca/scheda_avviso_genitori.html.twig', [
      'dati' => $dati]);
  }

  /**
   * Visualizza gli eventi destinati ai genitori o agli alunni
   *
   * @param GenitoriUtil $gen Funzioni di utilità per i genitori
   * @param AgendaUtil $age Funzioni di utilità per la gestione dell'agenda
   * @param string $mese Anno e mese della pagina da visualizzare dell'agenda
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/genitori/eventi/{mese}', name: 'genitori_eventi', requirements: ['mese' => '\d\d\d\d-\d\d'], defaults: ['mese' => '0000-00'], methods: ['GET'])]
  #[IsGranted(attribute: new Expression("is_granted('ROLE_GENITORE') or is_granted('ROLE_ALUNNO')"))]
  public function eventi(GenitoriUtil $gen, AgendaUtil $age, string $mese): Response {
    $dati = null;
    $info = null;
    // parametro data
    if ($mese == '0000-00') {
      // mese non specificato
      if ($this->reqstack->getSession()->get('/APP/ROUTE/genitori_eventi/mese')) {
        // recupera data da sessione
        $mese = DateTime::createFromFormat('Y-m-d', $this->reqstack->getSession()->get('/APP/ROUTE/genitori_eventi/mese').'-01');
      } else {
        // imposta data odierna
        $mese = (new DateTime())->modify('first day of this month');
      }
    } else {
      // imposta data indicata e la memorizza in sessione
      $mese = DateTime::createFromFormat('Y-m-d', $mese.'-01');
      $this->reqstack->getSession()->set('/APP/ROUTE/genitori_eventi/mese', $mese->format('Y-m'));
    }
    // nome/url mese
    $formatter = new IntlDateFormatter('it_IT', IntlDateFormatter::SHORT, IntlDateFormatter::SHORT);
    $formatter->setPattern('MMMM yyyy');
    $info['mese'] =  ucfirst($formatter->format($mese));
    // data prec/succ
    $data_inizio = DateTime::createFromFormat('Y-m-d', $mese->format('Y-m-01'));
    $data_fine = clone $data_inizio;
    $data_fine->modify('last day of this month');
    $data_succ = (clone $data_fine);
    $data_succ = $this->em->getRepository(Festivita::class)->giornoSuccessivo($data_succ);
    $info['url_succ'] = ($data_succ ? $data_succ->format('Y-m') : null);
    $data_prec = (clone $data_inizio);
    $data_prec = $this->em->getRepository(Festivita::class)->giornoPrecedente($data_prec);
    $info['url_prec'] = ($data_prec ? $data_prec->format('Y-m') : null);
    // presentazione calendario
    $info['inizio'] = (intval($mese->format('w')) - 1);
    $m = clone $mese;
    $info['ultimo_giorno'] = $m->modify('last day of this month')->format('j');
    $info['fine'] = (intval($m->format('w')) == 0 ? 0 : 6 - intval($m->format('w')));
    // legge l'utente
    if ($this->getUser() instanceOf Alunno) {
      // utente è alunno
      $dati = $age->agendaEventiAlunni($this->getUser(), $mese);
    } else {
      // utente è genitore
      $alunno = $gen->alunno($this->getUser());
      if ($alunno) {
        // recupera dati
        $dati = $age->agendaEventiGenitori($this->getUser(), $alunno, $mese);
      }
    }
    // mostra la pagina di risposta
    return $this->render('agenda/eventi_genitori.html.twig', [
      'pagina_titolo' => 'page.genitori_eventi',
      'mese' => $mese,
      'info' => $info,
      'dati' => $dati]);
  }

  /**
   * Mostra i dettagli di un evento destinato ai genitori
   *
   * @param AgendaUtil $age Funzioni di utilità per la gestione dell'agenda
   * @param string $data Data dell'evento (AAAA-MM-GG)
   * @param string $tipo Tipo dell'evento
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/genitori/eventi/dettagli/{data}/{tipo}', name: 'genitori_eventi_dettagli', requirements: ['data' => '\d\d\d\d-\d\d-\d\d', 'tipo' => 'C|A|V|P'], methods: ['GET'])]
  #[IsGranted(attribute: new Expression("is_granted('ROLE_GENITORE') or is_granted('ROLE_ALUNNO')"))]
  public function eventiDettagli(AgendaUtil $age, string $data, string $tipo): Response {
    // inizializza
    $dati = null;
    // data
    $data = DateTime::createFromFormat('Y-m-d', $data);
    // legge dati
    if ($this->getUser() instanceOf Alunno) {
      // utente è alunno
      $dati = $age->dettagliEventoAlunno($this->getUser(), $data, $tipo);
    } else {
      // utente è genitore
      $dati = $age->dettagliEventoGenitore($this->getUser(), $this->getUser()->getAlunno(), $data, $tipo);
    }
    // visualizza pagina
    return $this->render('agenda/scheda_evento_genitori_'.$tipo.'.html.twig', [
      'dati' => $dati,
      'data' => $data]);
  }

  /**
   * Giustificazione online di un'assenza
   *
   * @param Request $request Pagina richiesta
   * @param GenitoriUtil $gen Funzioni di utilità per i genitori
   * @param LogHandler $dblogger Gestore dei log su database
   * @param Assenza $assenza Assenza da giustificare
   * @param int $posizione Posizione per lo scrolling verticale della finestra
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/genitori/giustifica/assenza/{assenza}/{posizione}', name: 'genitori_giustifica_assenza', requirements: ['posizione' => '\d+'], defaults: ['posizione' => 0], methods: ['GET', 'POST'])]
  #[IsGranted(attribute: new Expression("is_granted('ROLE_GENITORE') or is_granted('ROLE_ALUNNO')"))]
  public function giustificaAssenza(Request $request, GenitoriUtil $gen, LogHandler $dblogger,
                                    Assenza $assenza, int $posizione): Response {
    // inizializza
    $fs = new Filesystem();
    $info = [];
    $lista_motivazioni = ['label.giustifica_salute' => 1, 'label.giustifica_famiglia' => 2, 'label.giustifica_trasporto' => 3,
      'label.giustifica_sport' => 4, 'label.giustifica_altro' => 9];
    // legge l'alunno
    if ($this->getUser() instanceOf Alunno) {
      // utente è alunno
      $alunno = $this->getUser();
    } else {
      // utente è genitore
      $alunno = $gen->alunno($this->getUser());
      if (!$alunno) {
        // errore
        throw $this->createNotFoundException('exception.invalid_params');
      }
    }
    // controlla assenza e possibilità di giustificare
    if ($assenza->getAlunno() !== $alunno || !$alunno->getAbilitato() || !$alunno->getClasse() ||
        !$gen->giusticazioneOnline($this->getUser()) || $assenza->getDocenteGiustifica()) {
      // errore
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // controlla permessi
    if (!$gen->azioneGiustifica($assenza->getData(), $alunno)) {
      // errore: azione non permessa
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // dati assenze
    $dati_assenze = $gen->raggruppaAssenze($alunno);
    $data_str = $assenza->getData()->format('Y-m-d');
    $dich = null;
    foreach ($dati_assenze['gruppi'] as $per=>$ass) {
      foreach ($ass as $dt=>$a) {
        if ($dt == $data_str) {
          $info['assenza'] = $a['assenza'];
        }
        $dich = empty($dich) ? $a['assenza']['dichiarazione'] : $dich;
      }
    }
    if (!isset($info['assenza'])) {
      // errore: assenza non definita
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // dati in formato stringa
    $info['classe'] = ''.$alunno->getClasse();
    $info['alunno'] = $alunno->getCognome().' '.$alunno->getNome();
    // form
    $form = $this->container->get('form.factory')->createNamedBuilder('giustifica_assenza', FormType::class)
      ->setAction($this->generateUrl('genitori_giustifica_assenza', ['assenza' => $assenza->getId(), 'posizione' => $posizione]))
      ->add('tipo', ChoiceType::class, ['label' => 'label.motivazione_assenza',
        'choices' => $lista_motivazioni,
        'placeholder' => 'label.scelta_giustifica',
        'expanded' => false,
        'multiple' => false,
        'choice_attr' => fn() => ['class' => 'gs-no-placeholder'],
        'attr' => ['class' => 'gs-placeholder'],
        'required' => false])
      ->add('motivazione', MessageType::class, ['label' => null,
        'data' => $info['assenza']['motivazione'],
        'trim' => true,
        'attr' => ['rows' => '3'],
        'required' => true])
      ->add('submit', SubmitType::class, ['label' => 'label.submit',
        'attr' => ['class' => 'btn-primary']])
      ->add('delete', SubmitType::class, ['label' => 'label.delete',
        'attr' => ['class' => 'btn-danger']])
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted()) {
      $errore = false;
      $motivazione = substr((string) $form->get('motivazione')->getData(), 0, 255);
      if ($form->get('delete')->isClicked()) {
        // cancella dati
        $giustificato = null;
        $motivazione = null;
        $dichiarazione = [];
        $certificati = [];
      } else {
        // no autodichiarazione
        $giustificato = new DateTime();
        $dichiarazione = [];
        $certificati = [];
      }
      // aggiorna dati
      $risultato = $this->em->getRepository(Assenza::class)->createQueryBuilder('ass')
        ->update()
        ->set('ass.modificato', ':modificato')
        ->set('ass.giustificato', ':giustificato')
        ->set('ass.motivazione', ':motivazione')
        ->set('ass.dichiarazione', ':dichiarazione')
        ->set('ass.certificati', ':certificati')
        ->set('ass.utenteGiustifica', ':utente')
        ->where('ass.id in (:ids)')
        ->setParameters(['modificato' => new DateTime(), 'giustificato' => $giustificato,
          'motivazione' => $motivazione, 'dichiarazione' => serialize($dichiarazione),
          'certificati' => serialize($certificati), 'utente' => $this->getUser(),
          'ids' => explode(',', (string) $info['assenza']['ids'])])
        ->getQuery()
        ->getResult();
      // memorizza dati
      $this->em->flush();
      // log azione
      if ($form->get('delete')->isClicked()) {
        // eliminazione
        $dblogger->logAzione('ASSENZE', 'Elimina giustificazione online', [
          'ID' => $info['assenza']['ids'],
          'Giustificato' => $info['assenza']['giustificato'],
          'Motivazione' => $info['assenza']['motivazione'],
          'Dichiarazione' => $info['assenza']['dichiarazione'],
          'Certificati' => $info['assenza']['certificati']]);
      } elseif (!$errore) {
        // inserimento o modifica
        $dblogger->logAzione('ASSENZE', 'Giustificazione online', [
          'ID' => $info['assenza']['ids'],
          'Giustificato' => $info['assenza']['giustificato'],
          'Motivazione' => $info['assenza']['motivazione'],
          'Dichiarazione' => $info['assenza']['dichiarazione'],
          'Certificati' => $info['assenza']['certificati']]);
      }
      // redirezione
      return $this->redirectToRoute('genitori_assenze', ['posizione' => $posizione]);
    }
    // visualizza pagina
    return $this->render('ruolo_genitore/giustifica_assenza.html.twig', [
      'info' => $info,
      'alunno' => $alunno,
      'form' => $form->createView()]);
  }

  /**
   * Giustificazione online di un ritardo
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param GenitoriUtil $gen Funzioni di utilità per i genitori
   * @param LogHandler $dblogger Gestore dei log su database
   * @param Entrata $entrata Ritardo da giustificare
   * @param int $posizione Posizione per lo scrolling verticale della finestra
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/genitori/giustifica/ritardo/{entrata}/{posizione}', name: 'genitori_giustifica_ritardo', requirements: ['posizione' => '\d+'], defaults: ['posizione' => 0], methods: ['GET', 'POST'])]
  #[IsGranted(attribute: new Expression("is_granted('ROLE_GENITORE') or is_granted('ROLE_ALUNNO')"))]
  public function giustificaRitardo(Request $request, TranslatorInterface $trans, GenitoriUtil $gen,
                                    LogHandler $dblogger, Entrata $entrata,
                                    int $posizione): Response {
    // inizializza
    $info = [];
    $lista_motivazioni = ['label.giustifica_salute' => 1, 'label.giustifica_famiglia' => 2, 'label.giustifica_trasporto' => 3, 'label.giustifica_sport' => 4, 'label.giustifica_altro' => 9];
    // legge l'alunno
    if ($this->getUser() instanceOf Alunno) {
      // utente è alunno
      $alunno = $this->getUser();
    } else {
      // utente è genitore
      $alunno = $gen->alunno($this->getUser());
      if (!$alunno) {
        // errore
        throw $this->createNotFoundException('exception.invalid_params');
      }
    }
    // controlla assenza e possibilità di giustificare
    if ($entrata->getAlunno() !== $alunno || !$alunno->getAbilitato() || !$alunno->getClasse() ||
        !$gen->giusticazioneOnline($this->getUser()) || $entrata->getDocenteGiustifica() ||
        $entrata->getRitardoBreve()) {
      // errore
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // controlla permessi
    if (!$gen->azioneGiustifica($entrata->getData(), $alunno)) {
      // errore: azione non permessa
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // dati in formato stringa
    $formatter = new IntlDateFormatter('it_IT', IntlDateFormatter::SHORT, IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $info['data'] =  $formatter->format($entrata->getData());
    $info['ora'] =  $entrata->getOra()->format('H:i');
    $info['classe'] = ''.$alunno->getClasse();
    $info['alunno'] = $alunno->getCognome().' '.$alunno->getNome();
    $info['ritardo'] = $entrata;
    // form
    $entrata_old = clone $entrata;
    $form = $this->container->get('form.factory')->createNamedBuilder('giustifica_ritardo', FormType::class, $entrata)
      ->setAction($this->generateUrl('genitori_giustifica_ritardo', ['entrata' => $entrata->getId(), 'posizione' => $posizione]))
      ->add('tipo', ChoiceType::class, ['label' => 'label.motivazione_ritardo',
        'choices' => $lista_motivazioni,
        'placeholder' => 'label.scelta_giustifica',
        'expanded' => false,
        'multiple' => false,
        'choice_attr' => fn() => ['class' => 'gs-no-placeholder'],
        'attr' => ['class' => 'gs-placeholder'],
        'mapped' => false,
        'required' => false])
      ->add('motivazione', MessageType::class, ['label' => null,
        'trim' => true,
        'attr' => ['rows' => '3'],
        'required' => true])
      ->add('submit', SubmitType::class, ['label' => 'label.submit',
        'attr' => ['class' => 'btn-primary']])
      ->add('delete', SubmitType::class, ['label' => 'label.delete',
        'attr' => ['class' => 'btn-danger']])
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted()) {
      if ($form->get('submit')->isClicked() && empty($form->get('motivazione')->getData())) {
        // errore: motivazione assente
        $this->addFlash('error', $trans->trans('exception.no_motivazione'));
      } else {
        // dati validi
        if ($form->get('delete')->isClicked()) {
          // cancella
          $entrata
            ->setMotivazione(null)
            ->setGiustificato(null);
        } else {
          // aggiorna dati
          $entrata
            ->setMotivazione(substr((string) $form->get('motivazione')->getData(), 0, 255))
            ->setGiustificato(new DateTime())
            ->setUtenteGiustifica($this->getUser());
        }
        // ok: memorizza dati
        $this->em->flush();
        // log azione
        if ($form->get('delete')->isClicked()) {
          // cancella
          $dblogger->logAzione('ASSENZE', 'Elimina giustificazione online', [
            'Ritardo' => $entrata->getId(),
            'Motivazione' => $entrata_old->getMotivazione(),
            'Giustificato' => $entrata_old->getGiustificato() ? $entrata_old->getGiustificato()->format('Y-m-d') : null]);
        } else {
          // inserisce o modifica
          $dblogger->logAzione('ASSENZE', 'Giustificazione online', [
            'Ritardo' => $entrata->getId(),
            'Motivazione' => $entrata_old->getMotivazione(),
            'Giustificato' => $entrata_old->getGiustificato() ? $entrata_old->getGiustificato()->format('Y-m-d') : null]);
        }
      }
      // redirezione
      return $this->redirectToRoute('genitori_assenze', ['posizione' => $posizione]);
    }
    // visualizza pagina
    return $this->render('ruolo_genitore/giustifica_ritardo.html.twig', [
      'info' => $info,
      'form' => $form->createView()]);
  }

  /**
   * Giustificazione online di un'uscita anticipata
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param GenitoriUtil $gen Funzioni di utilità per i genitori
   * @param LogHandler $dblogger Gestore dei log su database
   * @param Uscita $uscita Uscita anticipata da giustificare
   * @param int $posizione Posizione per lo scrolling verticale della finestra
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/genitori/giustifica/uscita/{uscita}/{posizione}', name: 'genitori_giustifica_uscita', requirements: ['posizione' => '\d+'], defaults: ['posizione' => 0], methods: ['GET', 'POST'])]
  #[IsGranted(attribute: new Expression("is_granted('ROLE_GENITORE') or is_granted('ROLE_ALUNNO')"))]
  public function giustificaUscita(Request $request, TranslatorInterface $trans, GenitoriUtil $gen,
                                   LogHandler $dblogger, Uscita $uscita, int $posizione): Response {
    // inizializza
    $info = [];
    $lista_motivazioni = ['label.giustifica_salute' => 1, 'label.giustifica_famiglia' => 2, 'label.giustifica_trasporto' => 3, 'label.giustifica_sport' => 4, 'label.giustifica_altro' => 9];
    // legge l'alunno
    if ($this->getUser() instanceOf Alunno) {
      // utente è alunno
      $alunno = $this->getUser();
    } else {
      // utente è genitore
      $alunno = $gen->alunno($this->getUser());
      if (!$alunno) {
        // errore
        throw $this->createNotFoundException('exception.invalid_params');
      }
    }
    // controlla assenza e possibilità di giustificare
    if ($uscita->getAlunno() !== $alunno || !$alunno->getAbilitato() || !$alunno->getClasse() ||
        !$gen->giusticazioneOnline($this->getUser()) || $uscita->getDocenteGiustifica()) {
      // errore
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // controlla permessi
    if (!$gen->azioneGiustifica($uscita->getData(), $alunno)) {
      // errore: azione non permessa
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // dati in formato stringa
    $formatter = new IntlDateFormatter('it_IT', IntlDateFormatter::SHORT, IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $info['data'] =  $formatter->format($uscita->getData());
    $info['ora'] =  $uscita->getOra()->format('H:i');
    $info['classe'] = ''.$alunno->getClasse();
    $info['alunno'] = $alunno->getCognome().' '.$alunno->getNome();
    $info['uscita'] = $uscita;
    // form
    $uscita_old = clone $uscita;
    $form = $this->container->get('form.factory')->createNamedBuilder('giustifica_uscita', FormType::class, $uscita)
      ->setAction($this->generateUrl('genitori_giustifica_uscita', ['uscita' => $uscita->getId(), 'posizione' => $posizione]))
      ->add('tipo', ChoiceType::class, ['label' => 'label.motivazione_ritardo',
        'choices' => $lista_motivazioni,
        'placeholder' => 'label.scelta_giustifica',
        'expanded' => false,
        'multiple' => false,
        'choice_attr' => fn() => ['class' => 'gs-no-placeholder'],
        'attr' => ['class' => 'gs-placeholder'],
        'mapped' => false,
        'required' => false])
      ->add('motivazione', MessageType::class, ['label' => null,
        'trim' => true,
        'attr' => ['rows' => '3'],
        'required' => true])
      ->add('submit', SubmitType::class, ['label' => 'label.submit',
        'attr' => ['class' => 'btn-primary']])
      ->add('delete', SubmitType::class, ['label' => 'label.delete',
        'attr' => ['class' => 'btn-danger']])
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted()) {
      if ($form->get('submit')->isClicked() && empty($form->get('motivazione')->getData())) {
        // errore: motivazione assente
        $this->addFlash('error', $trans->trans('exception.no_motivazione'));
      } else {
        // dati validi
        if ($form->get('delete')->isClicked()) {
          // cancella
          $uscita
            ->setMotivazione(null)
            ->setGiustificato(null);
        } else {
          // aggiorna dati
          $uscita
            ->setMotivazione(substr((string) $form->get('motivazione')->getData(), 0, 255))
            ->setGiustificato(new DateTime())
            ->setUtenteGiustifica($this->getUser());
        }
        // ok: memorizza dati
        $this->em->flush();
        // log azione
        if ($form->get('delete')->isClicked()) {
          // cancella
          $dblogger->logAzione('ASSENZE', 'Elimina giustificazione online', [
            'Uscita' => $uscita->getId(),
            'Motivazione' => $uscita_old->getMotivazione(),
            'Giustificato' => $uscita_old->getGiustificato() ? $uscita_old->getGiustificato()->format('Y-m-d') : null]);
        } else {
          // inserisce o modifica
          $dblogger->logAzione('ASSENZE', 'Giustificazione online', [
            'Uscita' => $uscita->getId(),
            'Motivazione' => $uscita_old->getMotivazione(),
            'Giustificato' => $uscita_old->getGiustificato() ? $uscita_old->getGiustificato()->format('Y-m-d') : null]);
        }
      }
      // redirezione
      return $this->redirectToRoute('genitori_assenze', ['posizione' => $posizione]);
    }
    // visualizza pagina
    return $this->render('ruolo_genitore/giustifica_uscita.html.twig', [
      'info' => $info,
      'form' => $form->createView()]);
  }

  /**
   * Mostra le deroghe autorizzate per l'alunno.
   *
   * @param GenitoriUtil $gen Funzioni di utilità per i genitori
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   *
   * @return Response Pagina di risposta
   *
   */
  #[Route(path: '/genitori/deroghe/', name: 'genitori_deroghe', methods: ['GET'])]
  #[IsGranted(attribute: new Expression("is_granted('ROLE_GENITORE') or is_granted('ROLE_ALUNNO')"))]
  public function deroghe(GenitoriUtil $gen, RegistroUtil $reg): Response {
    // legge l'alunno
    if ($this->getUser() instanceOf Alunno) {
      // utente è alunno
      $alunno = $this->getUser();
    } else {
      // utente è genitore
      $alunno = $gen->alunno($this->getUser());
      if (!$alunno) {
        // errore
        throw $this->createNotFoundException('exception.invalid_params');
      }
    }
    // legge la classe (può essere null)
    $classe = $reg->classeInData(new DateTime(), $alunno);
    // visualizza pagina
    return $this->render('ruolo_genitore/deroghe.html.twig', [
      'pagina_titolo' => 'page.genitori_deroghe',
      'alunno' => $alunno,
      'classe' => $classe]);
  }

}
