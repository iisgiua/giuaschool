<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2021 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2021
 */


namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Translation\TranslatorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use App\Util\LogHandler;
use App\Util\ScrutinioUtil;
use App\Util\RegistroUtil;
use App\Form\PropostaVotoType;
use App\Form\VotoScrutinioType;
use App\Entity\Staff;
use App\Entity\Preside;
use App\Entity\Alunno;
use App\Entity\Assenza;
use App\Entity\DefinizioneScrutinio;


/**
 * ScrutinioController - gestione degli scrutini
 */
class ScrutinioController extends AbstractController {

  /**
   * Gestione delle proposte di voto
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param ScrutinioUtil $scr Funzioni di utilità per lo scrutinio
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $cattedra Identificativo della cattedra (nullo se supplenza)
   * @param int $classe Identificativo della classe
   * @param string $periodo Periodo relativo allo scrutinio
   *
   * @return Response Pagina di risposta
   *
   * @Route("/lezioni/scrutinio/proposte/{cattedra}/{classe}/{periodo}", name="lezioni_scrutinio_proposte",
   *    requirements={"cattedra": "\d+", "classe": "\d+", "periodo": "P|S|F|E|1|2|0|X"},
   *    defaults={"cattedra": 0, "classe": 0, "periodo": "0"},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function proposteAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                 TranslatorInterface $trans, ScrutinioUtil $scr, LogHandler $dblogger,
                                 $cattedra, $classe, $periodo) {
    // inizializza variabili
    $info = array();
    $lista_periodi = null;
    $form = null;
    $form_title = null;
    $elenco = array();
    $elenco['alunni'] = array();
    $valutazioni['N'] = ['min' => 0, 'max' => 10, 'start' => 6, 'ticks' => '0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10', 'labels' => '"NC", 1, 2, 3, 4, 5, 6, 7, 8, 9, 10', 'format' => '"Non Classificato", 1, 2, 3, 4, 5, 6, 7, 8, 9, 10', 'format2' => '"NC", 1, 2, 3, 4, 5, 6, 7, 8, 9, 10'];
    $valutazioni['R'] = ['min' => 20, 'max' => 26, 'start' => 22, 'ticks' => '20, 21, 22, 23, 24, 25, 26', 'labels' => '"NC", "", "Suff.", "", "Buono", "", "Ottimo"', 'format' => '"Non Classificato", "Insufficiente", "Sufficiente", "Discreto", "Buono", "Distinto", "Ottimo"', 'format2' => '"NC", "Insufficiente", "Sufficiente", "Discreto", "Buono", "Distinto", "Ottimo"'];
    $valutazioni['E'] = ['min' => 3, 'max' => 10, 'start' => 6, 'ticks' => '3, 4, 5, 6, 7, 8, 9, 10', 'labels' => '"NC", 4, 5, 6, 7, 8, 9, 10', 'format' => '"Non Classificato", 4, 5, 6, 7, 8, 9, 10', 'format2' => '"NC", 4, 5, 6, 7, 8, 9, 10'];
    $title['P']['N'] = 'message.proposte';
    $title['P']['R'] = 'message.proposte_religione';
    $title['P']['E'] = 'message.proposte';
    $title['1']['N'] = 'message.proposte_intermedia';
    $title['1']['R'] = 'message.proposte_religione';
    $title['F']['N'] = 'message.proposte';
    $title['F']['R'] = 'message.proposte_religione';
    $title['F']['E'] = 'message.proposte';
    $title['E']['N'] = 'message.proposte_non_previste';
    $title['E']['R'] = 'message.proposte_non_previste';
    $title['E']['E'] = 'message.proposte_non_previste';
    $title['X']['N'] = 'message.proposte_non_previste';
    $title['X']['R'] = 'message.proposte_non_previste';
    $title['X']['E'] = 'message.proposte_non_previste';
    $info['valutazioni'] = $valutazioni['N'];
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
    // controllo cattedra/supplenza
    if ($cattedra > 0) {
      // lezione in propria cattedra: controlla esistenza
      $cattedra = $em->getRepository('App:Cattedra')->findOneBy(['id' => $cattedra,
        'docente' => $this->getUser(), 'attiva' => 1]);
      if (!$cattedra) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      if ($cattedra->getTipo() == 'P' || $cattedra->getMateria()->getTipo() == 'S') {
        // cattedra di potenziamento o sostegno: redirezione
        return $this->redirectToRoute('lezioni_scrutinio_svolto', ['cattedra' => $cattedra->getId(),
          'classe' => $cattedra->getClasse()->getId(), 'periodo' => $periodo]);
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
    }
    if ($cattedra) {
      // legge lista periodi
      $lista_periodi = $scr->periodi($classe);
      if ($periodo == '0') {
        // cerca periodo attivo
        $periodo = array_search('N', $lista_periodi);
      } elseif (!array_key_exists($periodo, $lista_periodi)) {
        // periodo indicato non valido
        $periodo = null;
      }
      if ($periodo) {
        // elenco proposte/alunni
        $elenco = $scr->elencoProposte($this->getUser(), $classe, $cattedra->getMateria(), $cattedra->getTipo(), $periodo);
        if ($lista_periodi[$periodo] == 'N') {
          // è possibile inserire le proposte
          $proposte_prec = unserialize(serialize($elenco['proposte'])); // clona oggetti
          // opzioni di proposte
          $opzioni = ['label' => false,
            'data' => $elenco['proposte'],
            'entry_type' => PropostaVotoType::class,
            'entry_options' => array('label' => false)];
          $info['valutazioni'] = $valutazioni[$cattedra->getMateria()->getTipo()];
          $form_title = $title[$periodo][$cattedra->getMateria()->getTipo()];
          if ($cattedra->getMateria()->getTipo() == 'R') {
            // religione
            $opzioni['attr'] = ['no_recupero' => true];
          }
          if ($periodo == 'F' && $classe->getAnno() == 5) {
            // scrutinio finale di una quinta: no recupero
            $opzioni['attr'] = ['no_recupero' => true];
            $form_title = 'message.proposte_quinte';
          }
          // form di inserimento
          $form = $this->container->get('form.factory')->createNamedBuilder('proposte', FormType::class)
            ->setAction($this->generateUrl('lezioni_scrutinio_proposte', [
              'cattedra' => $cattedra->getId(), 'classe' => $classe->getId(),
              'periodo' => $periodo]))
            ->add('lista', CollectionType::class, $opzioni)
            ->add('submit', SubmitType::class, array('label' => 'label.submit',
              'attr' => ['widget' => 'gs-button-start', 'class' => 'btn-primary']))
            ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
              'attr' => ['widget' => 'gs-button-end',
              'onclick' => "location.href='".$this->generateUrl('lezioni_scrutinio_proposte',
                ['cattedra' => $cattedra->getId(), 'classe' => $classe->getId()])."'"]))
            ->getForm();
          $form->handleRequest($request);
          if ($form->isSubmitted() && $form->isValid()) {
            // controlla errori
            $errori = [];
            $log['create'] = array();
            $log['edit'] = array();
            foreach ($form->get('lista')->getData() as $key=>$prop) {
              // controllo alunno
              $alunno = $em->getRepository('App:Alunno')->findOneBy(['id' => $prop->getAlunno()->getId(),
                'classe' => $classe->getId(), 'abilitato' => 1]);
              if (!$alunno) {
                // alunno non esiste, salta
                continue;
              }
              if ($prop->getUnico() === null) {
                // nessun voto
                $errori[0] = 'exception.no_voto';
                continue;
              } elseif ($prop->getUnico() < $info['valutazioni']['min'] || $prop->getUnico() > $info['valutazioni']['max']) {
                // voto non ammesso
                $errori[1] = 'exception.voto_errato';
                $prop = $proposte_prec[$key];
                continue;
              } elseif ($prop->getUnico() < 6 && $prop->getRecupero() === null && !isset($opzioni['attr']['no_recupero'])) {
                // manca tipo recupero
                  $errori[2] = 'exception.no_recupero';
              } elseif ($prop->getUnico() < 6 && empty($prop->getDebito()) && !isset($opzioni['attr']['no_recupero'])) {
                // manca argomenti debito
                if (($cattedra->getMateria()->getTipo() == 'N' && $prop->getUnico() > 0) || $prop->getUnico() > 3) {
                  // esclude NC da messaggio di errore
                  $errori[3] = 'exception.no_debito';
                }
              }
              if ($proposte_prec[$key]->getUnico() === null && $prop->getUnico() !== null) {
                // proposta aggiunta
                $log['create'][] = $prop;
              } elseif ($proposte_prec[$key]->getUnico() != $prop->getUnico() ||
                        $proposte_prec[$key]->getRecupero() != $prop->getRecupero() ||
                        $proposte_prec[$key]->getDebito() != $prop->getDebito()) {
                // proposta modificata
                $log['edit'][] = $proposte_prec[$key];
                // aggiorna docente proposta
                $prop->setDocente($this->getUser());
              }
              if (($prop->getUnico() >= 6 && $prop->getUnico() <= 10) || $prop->getUnico() >= 22 || isset($opzioni['attr']['no_recupero'])) {
                // svuota campi inutili
                $prop->setDebito('');
              }
            }
            // ok: memorizza dati
            $em->flush();
            // log azione
            $dblogger->logAzione('SCRUTINIO', 'Proposte', array(
              'Periodo' => $periodo,
              'Proposte inserite' => implode(', ', array_map(function ($e) {
                  return $e->getId();
                }, $log['create'])),
              'Proposte modificate' => implode(', ', array_map(function ($e) {
                  return '[Id: '.$e->getId().', Docente: '.$e->getDocente()->getId().', Voto: '.$e->getUnico().
                    ', Recupero: '.$e->getRecupero().', Debito: "'.$e->getDebito().'"'.
                    ', Strategie: "'.$e->getDato('strategie').'"]';
                }, $log['edit'])),
              ));
            // segnala errori
            foreach ($errori as $err) {
              // aggiunge errore
              $form->addError(new FormError($trans->trans($err)));
            }
          }
        } else {
          // non è possibile inserire le proposte
          $form_title = 'message.proposte_no';
        }
      }
    }
    // salva pagina visitata
    $route = ['name' => $request->get('_route'), 'param' => $request->get('_route_params')];
    $session->set('/APP/DOCENTE/menu_lezione', $route);
    // visualizza pagina
    return $this->render('lezioni/proposte_'.($periodo ? $periodo : 'P').'.html.twig', array(
      'pagina_titolo' => 'page.lezioni_proposte',
      'cattedra' => $cattedra,
      'classe' => $classe,
      'periodo' => $periodo,
      'lista_periodi' => $lista_periodi,
      'info' => $info,
      'proposte' => $elenco,
      'form' => ($form ? $form->createView() : null),
      'form_title' => $form_title,
    ));
  }

  /**
   * Gestione dello scrutinio della classe.
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param ScrutinioUtil $scr Funzioni di utilità per lo scrutinio
   * @param int $classe Identificativo della classe
   * @param string $stato Stato dello scrutinio (serve per passaggi tra stati)
   * @param string $periodo Periodo relativo allo scrutinio
   * @param int $posizione Posizione per lo scrolling verticale della finestra
   *
   * @return Response Pagina di risposta
   *
   * @Route("/coordinatore/scrutinio/{classe}/{stato}/{posizione}", name="coordinatore_scrutinio",
   *    requirements={"classe": "\d+", "stato": "N|C|\d", "posizione": "\d+"},
   *    defaults={"classe": 0, "stato": 0, "posizione": 0},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function scrutinioAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                   ScrutinioUtil $scr, $classe, $stato, $posizione) {
    // inizializza variabili
    $dati = null;
    $form = null;
    $template = 'coordinatore/scrutinio.html.twig';
    $info['giudizi']['P']['R'] = [20 => 'NC', 21 => 'Insuff.', 22 => 'Suff.', 23 => 'Discr.', 24 => 'Buono', 25 => 'Dist.', 26 => 'Ottimo'];
    $info['giudizi']['F']['R'] = [20 => 'NC', 21 => 'Insuff.', 22 => 'Suff.', 23 => 'Discr.', 24 => 'Buono', 25 => 'Dist.', 26 => 'Ottimo'];
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
      // legge scrutinio attivo
      $scrutinio = $scr->scrutinioAttivo($classe);
      if ($scrutinio) {
        // legge dati attuali
        $dati = $scr->datiScrutinio($this->getUser(), $classe, $scrutinio['periodo'], $scrutinio['stato']);
        $form = $this->container->get('form.factory')->createNamedBuilder('scrutinio', FormType::class);
        $form = $scr->formScrutinio($classe, $scrutinio['periodo'], $scrutinio['stato'], $form, $dati);
        // controllo stato
        if ($stato != '0' && $stato != $scrutinio['stato']) {
          // esegue passaggio di stato
          $scrutinio['stato'] = $scr->passaggioStato($this->getUser(), $request, $form,
            $classe, $scrutinio['periodo'], $stato);
          if ($scrutinio['stato'] === null) {
            // errore
            throw $this->createNotFoundException('exception.invalid_params');
          } elseif ($scrutinio['stato'] == $stato) {
            // passaggio avvenuto con successo, carico nuovi dati
            $dati = $scr->datiScrutinio($this->getUser(), $classe, $scrutinio['periodo'], $scrutinio['stato']);
            $form = $this->container->get('form.factory')->createNamedBuilder('scrutinio', FormType::class);
            $form = $scr->formScrutinio($classe, $scrutinio['periodo'], $scrutinio['stato'], $form, $dati);
          }
        }
        // imposta il template
        $template = 'coordinatore/scrutinio_'.$scrutinio['periodo'].'_'.$scrutinio['stato'].'.html.twig';
      } else {
        // scrutinio o chiuso o inesitente
        $scrutinio = $scr->scrutinioChiuso($classe);
        if (!$scrutinio) {
          // scrutinio non esiste
          $template = 'coordinatore/scrutinio_X_X.html.twig';
        } else {
          // legge i dati attuali
          $dati = $scr->datiScrutinio($this->getUser(), $classe, $scrutinio['periodo'], $scrutinio['stato']);
          $form = $this->container->get('form.factory')->createNamedBuilder('scrutinio', FormType::class);
          $form = $scr->formScrutinio($classe, $scrutinio['periodo'], $scrutinio['stato'], $form, $dati);
          // controllo stato
          if ($stato != '0' && $stato != $scrutinio['stato']) {
            // esegue passaggio di stato
            $scrutinio['stato'] = $scr->passaggioStato($this->getUser(), $request, $form,
              $classe, $scrutinio['periodo'], $stato);
            if ($scrutinio['stato'] === null) {
              // errore
              throw $this->createNotFoundException('exception.invalid_params');
            } elseif ($scrutinio['stato'] == $stato) {
              // passaggio avvenuto con successo, carico nuovi dati
              $dati = $scr->datiScrutinio($this->getUser(), $classe, $scrutinio['periodo'], $scrutinio['stato']);
              $form = $this->container->get('form.factory')->createNamedBuilder('scrutinio', FormType::class);
              $form = $scr->formScrutinio($classe, $scrutinio['periodo'], $scrutinio['stato'], $form, $dati);
            }
          }
          // imposta il template
          $template = 'coordinatore/scrutinio_'.$scrutinio['periodo'].'_'.$scrutinio['stato'].'.html.twig';
        }
      }
    }
    // visualizza pagina
    return $this->render($template, array(
      'pagina_titolo' => 'page.coordinatore_scrutinio',
      'classe' => $classe,
      'dati' => $dati,
      'info' => $info,
      'form' => ($form ? $form->createView() : null),
      'posizione' => $posizione,
    ));
  }

  /**
   * Gestione delle proposte di voto mancanti al momento dell'inizio dello scrutinio
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param ScrutinioUtil $scr Funzioni di utilità per lo scrutinio
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $classe Identificativo della classe
   * @param int $materia Identificativo della materia
   * @param string $periodo Periodo relativo allo scrutinio
   * @param int $posizione Posizione per lo scrolling verticale della finestra
   *
   * @return Response Pagina di risposta
   *
   * @Route("/coordinatore/scrutinio/proposte/{classe}/{materia}/{periodo}/{posizione}", name="coordinatore_scrutinio_proposte",
   *    requirements={"classe": "\d+", "materia": "\d+", "periodo": "P|S|F|E|1|2", "posizione": "\d+"},
   *    defaults={"posizione": 0},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function scrutinioProposteAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                          ScrutinioUtil $scr, LogHandler $dblogger, $classe, $materia, $periodo,
                                          $posizione) {
    // inizializza variabili
    $info = array();
    $valutazioni['N'] = ['min' => 0, 'max' => 10, 'start' => 6, 'ticks' => '0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10', 'labels' => '"NC", 1, 2, 3, 4, 5, 6, 7, 8, 9, 10'];
    $valutazioni['R'] = ['min' => 20, 'max' => 26, 'start' => 22, 'ticks' => '20, 21, 22, 23, 24, 25, 26', 'labels' => '"NC", "", "Suff.", "", "Buono", "", "Ottimo"'];
    $valutazioni['E'] = ['min' => 3, 'max' => 10, 'start' => 6, 'ticks' => '3, 4, 5, 6, 7, 8, 9, 10', 'labels' => '"NC", 4, 5, 6, 7, 8, 9, 10'];
    $info['valutazioni'] = $valutazioni['N'];
    $elenco = array();
    $elenco['alunni'] = array();
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
    // controllo materia
    $materia = $em->getRepository('App:Materia')->createQueryBuilder('m')
      ->join('App:Cattedra', 'c', 'WITH', 'c.materia=m.id')
      ->where('m.id=:materia AND c.classe=:classe AND c.attiva=:attiva AND c.tipo=:tipo')
      ->setParameters(['materia' => $materia, 'classe' => $classe, 'attiva' => 1, 'tipo' => 'N'])
      ->setMaxResults(1)
      ->getQuery()
      ->getOneOrNullResult();
    if (!$materia) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo periodo
    $scrutinio = $scr->scrutinioAttivo($classe);
    if (!$scrutinio || $periodo != $scrutinio['periodo']) {
      // errore
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // informazioni necessarie
    $info['materia'] = $materia->getNome();
    if ($materia->getTipo() == 'R') {
      // religione
      $info['valutazioni'] = $valutazioni['R'];
    } else {
      // altre materie
      $info['valutazioni'] = $valutazioni['N'];
    }
    // elenco proposte/alunni
    $elenco = $scr->elencoProposte($this->getUser(), $classe, $materia, '', $periodo);
    foreach ($elenco['proposte'] as $k=>$p) {
      if ($p->getUnico() !== null) {
        // proposta presente e non modificabile
        unset($elenco['proposte'][$k]);
      }
    }
    $proposte_prec = unserialize(serialize($elenco['proposte'])); // clona oggetti
    // form di inserimento
    $form = $this->container->get('form.factory')->createNamedBuilder('proposte', FormType::class)
      ->setAction($this->generateUrl('coordinatore_scrutinio_proposte', ['classe' => $classe->getId(),
        'materia' => $materia->getId(), 'periodo' => $periodo, 'posizione' => $posizione]))
      ->add('lista', CollectionType::class, array('label' => false,
        'data' => $elenco['proposte'],
        'entry_type' => PropostaVotoType::class,
        'entry_options' => array('label' => false)))
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' => ['class' => 'btn-primary']))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // controlla errori e log
      $log['create'] = array();
      $log['edit'] = array();
      foreach ($form->get('lista')->getData() as $key=>$prop) {
        // controllo alunno
        $alunno = $em->getRepository('App:Alunno')->findOneBy(['id' => $prop->getAlunno()->getId(),
          'classe' => $classe->getId(), 'abilitato' => 1]);
        if (!$alunno) {
          // alunno non esiste, salta
          $em->detach($prop);
          continue;
        } elseif ($prop->getUnico() < $info['valutazioni']['min'] || $prop->getUnico() > $info['valutazioni']['max']) {
          // voto non ammesso
          $em->detach($prop);
          continue;
        }
        // info log
        if ($proposte_prec[$key]->getUnico() === null && $prop->getUnico() !== null) {
          // proposta aggiunta
          $log['create'][] = $prop;
        } elseif ($proposte_prec[$key]->getUnico() != $prop->getUnico()) {
          // proposta modificata
          $log['edit'][] = $proposte_prec[$key];
          // aggiorna docente proposta
          $prop->setDocente($this->getUser());
        }
      }
      // ok: memorizza dati
      $em->flush();
      // log azione
      $dblogger->logAzione('SCRUTINIO', 'Proposte', array(
        'Periodo' => $periodo,
        'Proposte inserite' => implode(', ', array_map(function ($e) {
            return $e->getId();
          }, $log['create'])),
        'Proposte modificate' => implode(', ', array_map(function ($e) {
            return '[Id: '.$e->getId().', Docente: '.$e->getDocente()->getId().', Voto: '.$e->getUnico().
              ', Recupero: '.$e->getRecupero().', Debito: "'.$e->getDebito().'"]';
          }, $log['edit'])),
        ));
      // redirect
      return $this->redirectToRoute('coordinatore_scrutinio', ['classe' => $classe->getId(), 'posizione' => $posizione]);
    }
    // visualizza pagina
    return $this->render('coordinatore/proposte_'.$periodo.'.html.twig', array(
      'classe' => $classe,
      'info' => $info,
      'proposte' => $elenco,
      'form' => $form->createView(),
    ));
  }

  /**
   * Gestione dei voti di condotta durante lo scrutinio
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param ScrutinioUtil $scr Funzioni di utilità per lo scrutinio
   * @param int $classe Identificativo della classe
   * @param string $periodo Periodo relativo allo scrutinio
   * @param int $alunno ID del singolo alunno o zero per l'intera classe
   * @param int $posizione Posizione per lo scrolling verticale della finestra
   *
   * @return Response Pagina di risposta
   *
   * @Route("/coordinatore/scrutinio/condotta/{classe}/{periodo}/{alunno}/{posizione}", name="coordinatore_scrutinio_condotta",
   *    requirements={"classe": "\d+", "periodo": "P|S|F|E|1|2", "alunno": "\d+", "posizione": "\d+"},
   *    defaults={"posizione": 0},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function scrutinioCondottaAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                           TranslatorInterface $trans, ScrutinioUtil $scr, $classe, $periodo, $alunno, $posizione) {
    // inizializza variabili
    $info = array();
    $info['valutazioni'] = ['min' => 4, 'max' => 10, 'start' => 8, 'ticks' => '4, 5, 6, 7, 8, 9, 10', 'labels' => '"NC", 5, 6, 7, 8, 9, 10'];
    $dati = array();
    $dati['alunni'] = array();
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
    // controllo periodo
    $scrutinio_attivo = $scr->scrutinioAttivo($classe);
    if ($periodo != $scrutinio_attivo['periodo']) {
      // errore
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // legge condotta
    $condotta = $em->getRepository('App:Materia')->findOneByTipo('C');
    if (!$condotta) {
      // errore
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // elenco voti/alunni
    $dati = $scr->elencoVoti($this->getUser(), $classe, $condotta, $periodo);
    $scrutinio = $em->getRepository('App:Scrutinio')->findOneBy(['classe' => $classe, 'periodo' => $periodo]);
    $dati['assenze'] = $scrutinio->getDato('scrutinabili');
    if ($alunno > 0) {
      // singolo alunno
      foreach ($dati['voti'] as $key=>$val) {
        if ($key != $alunno) {
          // toglie altri alunni
          unset($dati['voti'][$key]);
        }
      }
    }
    // form di inserimento
    $form = $this->container->get('form.factory')->createNamedBuilder('condotta', FormType::class)
      ->setAction($this->generateUrl('coordinatore_scrutinio_condotta', ['classe' => $classe->getId(),
        'periodo' => $periodo, 'alunno' => $alunno, 'posizione' => $posizione]))
      ->add('lista', CollectionType::class, array('label' => false,
        'data' => $dati['voti'],
        'entry_type' => VotoScrutinioType::class,
        'entry_options' => array('label' => false, 'attr' => ['subType' => 'condotta'])))
      ->add('submit', SubmitType::class, array('label' => 'label.submit'))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // controlla errori
      $errore = array();
      foreach ($form->get('lista')->getData() as $key=>$voto) {
        // controllo alunno
        $alunno = $em->getRepository('App:Alunno')->find($voto->getAlunno()->getId());
        if (!$alunno || !in_array($alunno->getId(), array_keys($dati['voti']))) {
          // alunno non esiste, salta
          $em->detach($voto);
          continue;
        } elseif ($voto->getUnico() === null || $voto->getUnico() < $info['valutazioni']['min'] || $voto->getUnico() > $info['valutazioni']['max']) {
          // voto non ammesso
          $em->detach($voto);
          $errore['exception.voto_condotta'] = true;
        } elseif (!$voto->getDato('motivazione')) {
          // manca motivazione
          $errore['exception.motivazione_condotta'] = true;
        }
        if ($voto->getDato('unanimita') === null) {
          // manca delibera
          $errore['exception.delibera_condotta'] = true;
        } elseif ($voto->getDato('unanimita') === false && empty($voto->getDato('contrari'))) {
          // mancano contrari
          $errore['exception.contrari_condotta'] = true;
        }
      }
      foreach ($errore as $msg=>$v) {
        $session->getFlashBag()->add('errore', $trans->trans($msg));
      }
      // ok: memorizza dati (anche errati)
      $em->flush();
      // redirect
      return $this->redirectToRoute('coordinatore_scrutinio', ['classe' => $classe->getId(), 'posizione' => $posizione]);
    } elseif ($form->isSubmitted() && !$form->isValid()) {
      // mostra altri errori
      foreach ($form->getErrors() as $error) {
        $session->getFlashBag()->add('errore', $error->getMessage());
      }
      // redirect
      return $this->redirectToRoute('coordinatore_scrutinio', ['classe' => $classe->getId(), 'posizione' => $posizione]);
    }
    // visualizza pagina
    return $this->render('coordinatore/condotta_'.$periodo.'.html.twig', array(
      'classe' => $classe,
      'info' => $info,
      'dati' => $dati,
      'form' => $form->createView(),
    ));
  }

  /**
   * Gestione dei voti durante lo scrutinio
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param ScrutinioUtil $scr Funzioni di utilità per lo scrutinio
   * @param int $classe Identificativo della classe
   * @param int $materia Identificativo della materia
   * @param string $periodo Periodo relativo allo scrutinio
   * @param int $alunno ID del singolo alunno o zero per l'intera classe
   * @param int $posizione Posizione per lo scrolling verticale della finestra
   *
   * @return Response Pagina di risposta
   *
   * @Route("/coordinatore/scrutinio/voti/{classe}/{materia}/{periodo}/{alunno}/{posizione}", name="coordinatore_scrutinio_voti",
   *    requirements={"classe": "\d+", "materia": "\d+", "periodo": "P|S|F|E|1|2|X", "alunno": "\d+", "posizione": "\d+"},
   *    defaults={"posizione": 0},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function scrutinioVotiAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                       TranslatorInterface $trans, ScrutinioUtil $scr, $classe, $materia, $periodo, $alunno, $posizione) {
    // inizializza variabili
    $info = array();
    $valutazioni['P']['N'] = ['min' => 0, 'max' => 10, 'start' => 6, 'ticks' => '0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10', 'labels' => '"NC", 1, 2, 3, 4, 5, 6, 7, 8, 9, 10'];
    $valutazioni['P']['R'] = ['min' => 20, 'max' => 26, 'start' => 22, 'ticks' => '20, 21, 22, 23, 24, 25, 26', 'labels' => '"NC", "", "Suff.", "", "Buono", "", "Ottimo"'];
    $valutazioni['F']['R'] = ['min' => 20, 'max' => 26, 'start' => 22, 'ticks' => '20, 21, 22, 23, 24, 25, 26', 'labels' => '"NC", "", "Suff.", "", "Buono", "", "Ottimo"', 'format' => '"Non Classificato", "Insufficiente", "Sufficiente", "Discreto", "Buono", "Distinto", "Ottimo"', 'format2' => '"NC", "Insuff.", "Suff.", "Discreto", "Buono", "Distinto", "Ottimo"'];
    $valutazioni['F']['N'] = ['min' => 0, 'max' => 10, 'start' => 6, 'ticks' => '0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10', 'labels' => '"NC", 1, 2, 3, 4, 5, 6, 7, 8, 9, 10', 'format' => '"Non Classificato", 1, 2, 3, 4, 5, 6, 7, 8, 9, 10', 'format2' => '"NC", 1, 2, 3, 4, 5, 6, 7, 8, 9, 10'];
    $valutazioni['F']['E'] = ['min' => 3, 'max' => 10, 'start' => 6, 'ticks' => '3, 4, 5, 6, 7, 8, 9, 10', 'labels' => '"NC", 4, 5, 6, 7, 8, 9, 10', 'format' => '"Non Classificato", 4, 5, 6, 7, 8, 9, 10', 'format2' => '"NC", 4, 5, 6, 7, 8, 9, 10'];
    $valutazioni['E'] = $valutazioni['F'];
    $valutazioni['X'] = $valutazioni['F'];
    $info['valutazioni'] = $valutazioni['P']['N'];
    $dati = array();
    $dati['alunni'] = array();
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
    // controllo materia
    if ($periodo == 'X') {
      $materia = $em->getRepository('App:Materia')->find($materia);
    } else {
      $materia = $em->getRepository('App:Materia')->createQueryBuilder('m')
        ->join('App:Cattedra', 'c', 'WITH', 'c.materia=m.id')
        ->where('m.id=:materia AND c.classe=:classe AND c.attiva=:attiva AND c.tipo=:tipo')
        ->setParameters(['materia' => $materia, 'classe' => $classe, 'attiva' => 1, 'tipo' => 'N'])
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();
    }
    if (!$materia) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo periodo
    $scrutinio = $scr->scrutinioAttivo($classe);
    if ($periodo != $scrutinio['periodo']) {
      // errore
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // informazioni necessarie
    $info['materia'] = $materia->getNome();
    $info['valutazioni'] = $valutazioni[$periodo][$materia->getTipo()];
    // elenco voti/alunni
    if ($periodo == 'X') {
      $dati = $scr->elencoVotiRinviati($this->getUser(), $classe, $materia, $periodo);
    } else {
      $dati = $scr->elencoVoti($this->getUser(), $classe, $materia, $periodo);
    }
    if ($alunno > 0) {
      // singolo alunno
      foreach ($dati['voti'] as $key=>$val) {
        if ($key != $alunno) {
          // toglie altri alunni
          unset($dati['voti'][$key]);
        }
      }
    }
    // form di inserimento
    $tipo = ($periodo == 'P' ? 'debiti' : 'esito');
    $form = $this->container->get('form.factory')->createNamedBuilder('voti', FormType::class)
      ->setAction($this->generateUrl('coordinatore_scrutinio_voti', ['classe' => $classe->getId(),
        'materia' => $materia->getId(), 'periodo' => $periodo, 'alunno' => $alunno, 'posizione' => $posizione]))
      ->add('lista', CollectionType::class, array('label' => false,
        'data' => $dati['voti'],
        'entry_type' => VotoScrutinioType::class,
        'entry_options' => array('label' => false, 'attr' => ['subType' => $tipo] )))
      ->add('submit', SubmitType::class, array('label' => 'label.submit'))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // controlla errori
      $errore = array();
      foreach ($form->get('lista')->getData() as $key=>$voto) {
        // controllo alunno
        $alunno = $em->getRepository('App:Alunno')->find($voto->getAlunno()->getId());
        if (!$alunno || !in_array($alunno->getId(), array_keys($dati['voti']))) {
          // alunno non esiste, salta
          $em->detach($voto);
          continue;
        } elseif ($voto->getUnico() === null || $voto->getUnico() < $info['valutazioni']['min'] ||
                   $voto->getUnico() > $info['valutazioni']['max']) {
          // voto non ammesso o non presente
          $em->detach($voto);
          $errore['exception.no_voto_scrutinio'] = true;
        }
      }
      foreach ($errore as $msg=>$v) {
        $session->getFlashBag()->add('errore',
          $trans->trans($msg, ['materia' => $materia->getNomeBreve()]));
      }
      // memorizza dati (anche se errati)
      $em->flush();
      // redirect
      return $this->redirectToRoute('coordinatore_scrutinio', ['classe' => $classe->getId(), 'posizione' => $posizione]);
    } elseif ($form->isSubmitted() && !$form->isValid()) {
      // mostra altri errori
      foreach ($form->getErrors() as $error) {
        $session->getFlashBag()->add('errore', $error->getMessage());
      }
      // redirect
      return $this->redirectToRoute('coordinatore_scrutinio', ['classe' => $classe->getId(), 'posizione' => $posizione]);
    }
    // visualizza pagina
    return $this->render('coordinatore/voti_'.($periodo == 'X' ? 'E' : $periodo).'.html.twig', array(
      'classe' => $classe,
      'info' => $info,
      'dati' => $dati,
      'form' => $form->createView(),
    ));
  }

  /**
   * Visualizza i tabelloni di voto
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param ScrutinioUtil $scr Funzioni di utilità per lo scrutinio
   * @param int $cattedra Identificativo della cattedra (nullo se supplenza)
   * @param int $classe Identificativo della classe
   * @param string $periodo Periodo relativo allo scrutinio
   *
   * @return Response Pagina di risposta
   *
   * @Route("/lezioni/scrutinio/svolto/{cattedra}/{classe}/{periodo}", name="lezioni_scrutinio_svolto",
   *    requirements={"cattedra": "\d+", "classe": "\d+", "periodo": "P|S|F|E|1|2|A|X"},
   *    defaults={"cattedra": 0, "classe": 0, "periodo": "0"},
   *    methods="GET")
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function scrutinioSvoltoAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                         ScrutinioUtil $scr, $cattedra, $classe, $periodo) {
    // inizializza variabili
    $dati = array();
    $lista_periodi = null;
    $info = array();
    $info['giudizi']['P']['R'] = [20 => 'NC', 21 => 'Insuff.', 22 => 'Suff.', 23 => 'Discr.', 24 => 'Buono', 25 => 'Dist.', 26 => 'Ottimo'];
    $info['giudizi']['1'] = [30 => 'NC', 31 => 'Scarso', 32 => 'Insuff.', 33 => 'Mediocre', 34 => 'Suff.', 35 => 'Discreto', 36 => 'Buono', 37 => 'Ottimo'];
    $info['giudizi']['F']['R'] = [20 => 'NC', 21 => 'Insuff.', 22 => 'Suff.', 23 => 'Discr.', 24 => 'Buono', 25 => 'Dist.', 26 => 'Ottimo'];
    $info['giudizi']['E']['R'] = [20 => 'NC', 21 => 'Insuff.', 22 => 'Suff.', 23 => 'Discr.', 24 => 'Buono', 25 => 'Dist.', 26 => 'Ottimo'];
    $info['giudizi']['A']['R'] = $info['giudizi']['F']['R'];
    $info['condotta']['1'] = [40 => 'NC', 41 => 'Scorretta', 42 => 'Non sempre adeguata', 43 => 'Corretta'];
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
      $info['materia_id'] = $cattedra->getMateria()->getId();
      $info['materia_tipo'] = $cattedra->getMateria()->getTipo();
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
      // legge lista periodi
      $lista_periodi = $scr->periodi($classe);
      // aggiunde periodo per A.S. precedente
      $lista_periodi['A'] = 'C';
      if ($periodo == '0') {
        // cerca scrutinio chiuso
        $scrutinio = $scr->scrutinioChiuso($classe);
        $periodo = (isset($scrutinio['periodo']) ? $scrutinio['periodo'] : null);
      } elseif (!isset($lista_periodi[$periodo]) || $lista_periodi[$periodo] != 'C') {
        // periodo indicato non valido
        $periodo = null;
      }
      if ($periodo == 'E' || $periodo == 'X') {
        // voti
        $dati = $scr->quadroVoti($this->getUser(), $classe, 'E');
        if (isset($lista_periodi['X']) && $lista_periodi['X'] == 'C') {
          $dati['rinviati'] = $scr->quadroVoti($this->getUser(), $classe, 'X');
        }
      } elseif ($periodo == 'A') {
        // situazione precedente A.S.
        $dati = $scr->quadroVotiPrecedente($this->getUser(), $classe);
      } elseif ($periodo) {
        // voti
        $dati = $scr->quadroVoti($this->getUser(), $classe, $periodo);
      }
    }
    // salva pagina visitata
    $route = ['name' => $request->get('_route'), 'param' => $request->get('_route_params')];
    $session->set('/APP/DOCENTE/menu_lezione', $route);
    // visualizza pagina
    return $this->render('lezioni/tabellone.html.twig', array(
      'pagina_titolo' => 'page.lezioni_tabellone',
      'cattedra' => $cattedra,
      'classe' => $classe,
      'periodo' => $periodo,
      'lista_periodi' => $lista_periodi,
      'info' => $info,
      'dati' => $dati,
    ));
  }

  /**
   * Gestione dei giudizi di condotta durante la valutazione intermedia
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param ScrutinioUtil $scr Funzioni di utilità per lo scrutinio
   * @param int $classe Identificativo della classe
   * @param string $periodo Periodo relativo allo scrutinio
   * @param int $alunno ID del singolo alunno o zero per l'intera classe
   * @param int $posizione Posizione per lo scrolling verticale della finestra
   *
   * @return Response Pagina di risposta
   *
   * @Route("/coordinatore/scrutinio/condotta/giudizio/{classe}/{periodo}/{alunno}/{posizione}", name="coordinatore_scrutinio_condotta_giudizio",
   *    requirements={"classe": "\d+", "periodo": "1|2", "alunno": "\d+", "posizione": "\d+"},
   *    defaults={"posizione": 0},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function scrutinioCondottaGiudizioAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                                   TranslatorInterface $trans, ScrutinioUtil $scr, $classe, $periodo, $alunno, $posizione) {
    // inizializza variabili
    $info = array();
    $info['valutazioni'] = ['min' => 40, 'max' => 43, 'start' => 43, 'ticks' => '40, 41, 42, 43', 'labels' => '"NC", "Scorretta", "", "Corretta"'];
    $dati = array();
    $dati['alunni'] = array();
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
    // controllo periodo
    $scrutinio = $scr->scrutinioAttivo($classe);
    if ($periodo != $scrutinio['periodo']) {
      // errore
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // legge condotta
    $condotta = $em->getRepository('App:Materia')->findOneByTipo('C');
    if (!$condotta) {
      // errore
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // elenco voti/alunni
    $dati = $scr->elencoVoti($this->getUser(), $classe, $condotta, $periodo);
    if ($alunno > 0) {
      // singolo alunno
      foreach ($dati['voti'] as $key=>$val) {
        if ($key != $alunno) {
          // toglie altri alunni
          unset($dati['voti'][$key]);
        }
      }
    }
    // form di inserimento
    $form = $this->container->get('form.factory')->createNamedBuilder('condotta', FormType::class)
      ->setAction($this->generateUrl('coordinatore_scrutinio_condotta_giudizio', ['classe' => $classe->getId(),
        'periodo' => $periodo, 'alunno' => $alunno, 'posizione' => $posizione]))
      ->add('lista', CollectionType::class, array('label' => false,
        'data' => $dati['voti'],
        'entry_type' => VotoScrutinioType::class,
        'entry_options' => array('label' => false, 'attr' => ['subType' => 'condotta'])))
      ->add('submit', SubmitType::class, array('label' => 'label.submit'))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // controlla errori
      $errore = array();
      foreach ($form->get('lista')->getData() as $key=>$voto) {
        // controllo alunno
        $alunno = $em->getRepository('App:Alunno')->findOneBy(['id' => $voto->getAlunno()->getId(),
          'classe' => $classe->getId(), 'abilitato' => 1]);
        if (!$alunno) {
          // alunno non esiste, salta
          $em->detach($voto);
          continue;
        } elseif ($voto->getUnico() < $info['valutazioni']['min'] || $voto->getUnico() > $info['valutazioni']['max']) {
          // voto non ammesso
          $em->detach($voto);
          $errore['exception.voto_condotta'] = true;
        }
      }
      foreach ($errore as $msg=>$v) {
        $session->getFlashBag()->add('errore', $trans->trans($msg));
      }
      // ok: memorizza dati (anche errati)
      $em->flush();
      // redirect
      return $this->redirectToRoute('coordinatore_scrutinio', ['classe' => $classe->getId(), 'posizione' => $posizione]);
    } elseif ($form->isSubmitted() && !$form->isValid()) {
      // mostra altri errori
      foreach ($form->getErrors() as $error) {
        $session->getFlashBag()->add('errore', $error->getMessage());
      }
      // redirect
      return $this->redirectToRoute('coordinatore_scrutinio', ['classe' => $classe->getId(), 'posizione' => $posizione]);
    }
    // visualizza pagina
    return $this->render('coordinatore/condotta_'.$periodo.'.html.twig', array(
      'classe' => $classe,
      'info' => $info,
      'dati' => $dati,
      'form' => $form->createView(),
    ));
  }

  /**
   * Gestione dell'esito dello scrutinio
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param ScrutinioUtil $scr Funzioni di utilità per lo scrutinio
   * @param int $classe Identificativo della classe
   * @param string $periodo Periodo relativo allo scrutinio
   * @param int $posizione Posizione per lo scrolling verticale della finestra
   *
   * @return Response Pagina di risposta
   *
   * @Route("/coordinatore/scrutinio/esito/{alunno}/{periodo}/{classe}/{posizione}", name="coordinatore_scrutinio_esito",
   *    requirements={"alunno": "\d+", "periodo": "P|S|F|E|1|2|X", "posizione": "\d+", "classe": "\d+"},
   *    defaults={"posizione": 0, "classe": 0},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
   public function scrutinioEsitoAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                        TranslatorInterface $trans, ScrutinioUtil $scr, $alunno, $periodo,
                                        $classe, $posizione) {
    // inizializza variabili
    $info = array();
    $valutazioni['F']['N'] = ['min' => 0, 'max' => 10, 'start' => 6, 'ticks' => '0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10', 'labels' => '"NC", 1, 2, 3, 4, 5, 6, 7, 8, 9, 10'];
    $valutazioni['F']['C'] = ['min' => 4, 'max' => 10, 'start' => 6, 'ticks' => '4, 5, 6, 7, 8, 9, 10', 'labels' => '"NC", 5, 6, 7, 8, 9, 10'];
    $valutazioni['F']['E'] = ['min' => 3, 'max' => 10, 'start' => 6, 'ticks' => '3, 4, 5, 6, 7, 8, 9, 10', 'labels' => '"NC", 4, 5, 6, 7, 8, 9, 10'];
    $valutazioni['F']['R'] = ['min' => 20, 'max' => 26, 'start' => 22, 'ticks' => '20, 21, 22, 23, 24, 25, 26', 'labels' => '"NC", "", "Suff.", "", "Buono", "", "Ottimo"'];
    $info['valutazioni'] = $valutazioni['F']['N'];
    $info['giudizi'] = $valutazioni['F']['R'];
    $dati = array();
    // controllo alunno
    $alunno = $em->getRepository('App:Alunno')->findOneBy(['id' => $alunno, 'abilitato' => 1]);
    if (!$alunno) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo classe
    if ($periodo == 'X') {
      $classe = $em->getRepository('App:Classe')->find($classe);
    } else {
      $classe = $alunno->getClasse();
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
    // controllo periodo
    $lista_scrutinio = $scr->scrutinioAttivo($classe, true);
    if ($periodo != $lista_scrutinio['periodo']) {
      // errore
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // elenco voti
    if ($periodo == 'E') {
      // esame alunni sospesi: solo voti insuff.
      $dati = $scr->elencoVotiAlunnoSospeso($this->getUser(), $alunno, $periodo);
    } elseif ($periodo == 'X') {
      // esame alunni con scrutinio rinviato
      $dati = $scr->elencoVotiAlunnoRinviato($this->getUser(), $alunno, $classe, $periodo);
    } else {
      // scrutinio finale: tutti i voti
      $dati = $scr->elencoVotiAlunno($this->getUser(), $alunno, $periodo);
    }
    // esiti possibili
    $lista_esiti = array('label.esito_A' => 'A', 'label.esito_N' => 'N', 'label.esito_S' => 'S');
    if ($periodo == 'E') {
      // esame alunni sospesi
      $lista_esiti = array('label.esito_A' => 'A', 'label.esito_N' => 'N', 'label.esito_X' => 'X');
    } elseif ($periodo == 'X') {
      // rinvio esame alunni sospesi
      $lista_esiti = array('label.esito_A' => 'A', 'label.esito_N' => 'N');
    }
    // form di inserimento
    $form = $this->container->get('form.factory')->createNamedBuilder('esito', FormType::class)
      ->setAction($this->generateUrl('coordinatore_scrutinio_esito', ['alunno' => $alunno->getId(),
        'periodo' => $periodo, 'posizione' => $posizione, 'classe' => $classe->getId()]))
      ->add('lista', CollectionType::class, array('label' => false,
        'data' => $dati['voti'],
        'entry_type' => VotoScrutinioType::class,
        'entry_options' => array('label' => false, 'attr' => ['subType' => 'esito'] )))
      ->add('esito', ChoiceType::class, array('label' => false,
        'data' => $dati['esito']->getEsito(),
        'choices' => $lista_esiti,
        'placeholder' => 'label.scegli_esito',
        'expanded' => false,
        'multiple' => false,
        'choice_attr' => function($val, $key, $index) {
            return ['class' => 'gs-no-placeholder'];
          },
        'attr' => ['class' => 'gs-placeholder'],
        'required' => true))
      ->add('unanimita', ChoiceType::class, array('label' => false,
        'data' => $dati['esito']->getDati()['unanimita'],
        'choices' => ['label.votazione_unanimita' => true, 'label.votazione_maggioranza' => false],
        'placeholder' => null,
        'expanded' => true,
        'multiple' => false,
        'label_attr' => ['class' => 'radio-inline gs-mr-4'],
        'required' => true))
      ->add('giudizio', TextareaType::class, array('label' => false,
        'data' => $dati['esito']->getDati()['giudizio'],
        'trim' => true,
        'required' => false))
      ->add('contrari', TextType::class, array('label' => false,
        'data' => isset($dati['esito']->getDati()['contrari']) ? $dati['esito']->getDati()['contrari'] : null,
        'trim' => true,
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.submit'))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // controlla errori
      $errore = array();
      $insuff_cont = 0;
      $insuff_religione = false;
      $insuff_condotta = false;
      foreach ($form->get('lista')->getData() as $key=>$voto) {
        // controllo voto
        if ($voto->getUnico() === null || $voto->getUnico() < $valutazioni['F'][$voto->getMateria()->getTipo()]['min'] ||
            $voto->getUnico() > $valutazioni['F'][$voto->getMateria()->getTipo()]['max']) {
          // voto non ammesso o non presente
          $em->detach($voto);
          $errore['exception.no_voto_esito'] = true;
        } elseif ($voto->getMateria()->getTipo() == 'R' && $voto->getUnico() < 22) {
          // voto religione insufficiente
          $insuff_religione = true;
          $insuff_cont++;
        } elseif ($voto->getMateria()->getTipo() == 'C' && $voto->getUnico() < 6) {
          // voto condotta insufficiente
          $insuff_condotta = true;
          $insuff_cont++;
        } elseif ($voto->getUnico() < 6) {
          // voto insufficiente
          $insuff_cont++;
        }
      }
      if ($form->get('esito')->getData() === null) {
        // manca esito
        $errore['exception.manca_esito'] = true;
      } elseif ($form->get('unanimita')->getData() === null && $form->get('esito')->getData() != 'X') {
        // manca delibera
        $errore['exception.delibera_esito'] = true;
      } elseif ($form->get('unanimita')->getData() === false && empty($form->get('contrari')->getData()) &&
                $form->get('esito')->getData() != 'X') {
        // mancano contrari
        $errore['exception.contrari_esito'] = true;
      }
      if ($form->get('esito')->getData() == 'N' && empty($form->get('giudizio')->getData())) {
        // manca giudizio di non ammissione
        $errore['exception.giudizio_esito'] = true;
      }
      if ($form->get('esito')->getData() == 'X' && empty($form->get('giudizio')->getData())) {
        // manca giudizio
        $errore['exception.motivo_scrutinio_rinviato'] = true;
      }
      if ($form->get('esito')->getData() == 'A' && $insuff_cont > 0 && $classe->getAnno() != 5) {
        // insufficienze con ammissione (escluse quinte)
        $errore['exception.insufficienze_ammissione_esito'] = true;
      }
      if ($form->get('esito')->getData() == 'N' && $insuff_cont == 0) {
        // solo sufficienze con non ammissione
        $errore['exception.sufficienze_non_ammissione_esito'] = true;
      }
      if ($form->get('esito')->getData() == 'S' && $insuff_cont == 0) {
        // solo sufficienze con sospensione
        $errore['exception.sufficienze_sospensione_esito'] = true;
      }
      if ($form->get('esito')->getData() == 'S' && $insuff_religione) {
        // insuff. religione incoerente con esito sospeso
        $errore['exception.voto_religione_esito'] = true;
      }
      if ($form->get('esito')->getData() == 'S' && $insuff_condotta) {
        // insuff. condotta incoerente con esito
        $errore['exception.voto_condotta_esito'] = true;
      }
      if ($form->get('esito')->getData() == 'S' && $insuff_cont > 3) {
        // giudizio sospeso con più di 3 materie
        $errore['exception.num_materie_sospeso'] = true;
      }
      if ($form->get('esito')->getData() == 'S' && $classe->getAnno() == 5) {
        // sospensione in quinta
        $errore['exception.quinta_sospeso_esito'] = true;
      }
      if ($form->get('esito')->getData() == 'A' && $classe->getAnno() == 5 && $insuff_cont > 1) {
        // ammissione in quinta con più insufficienze
        $errore['exception.insufficienze_ammissione_quinta'] = true;
      } elseif ($form->get('esito')->getData() == 'A' && $classe->getAnno() == 5 &&
                $insuff_cont == 1 && empty($form->get('giudizio')->getData())) {
        // ammissione in quinta con una insufficienza ma senza motivazione
        $errore['exception.motivazione_ammissione_quinta'] = true;
      }
      // imposta eventuali messaggi di errore
      foreach ($errore as $msg=>$v) {
        $session->getFlashBag()->add('errore', $trans->trans($msg, [
          'sex' => ($alunno->getSesso() == 'M' ? 'o' : 'a'),
          'alunno' => $alunno->getCognome().' '.$alunno->getNome()]));
      }
      // legge valori
      $valori = $dati['esito']->getDati();
      // modifica esito
      $valori['unanimita'] = $form->get('unanimita')->getData();
      $valori['contrari'] = $form->get('contrari')->getData();
      $valori['giudizio'] = $form->get('giudizio')->getData();
      $dati['esito']->setDati($valori);
      $dati['esito']->setEsito($form->get('esito')->getData());
      // memorizza dati (anche se errati)
      $em->flush();
      // redirect
      return $this->redirectToRoute('coordinatore_scrutinio', ['classe' => $classe->getId(),
        'posizione' => $posizione]);
    } elseif ($form->isSubmitted() && !$form->isValid()) {
      // mostra altri errori
      foreach ($form->getErrors() as $error) {
        $session->getFlashBag()->add('errore', $error->getMessage());
      }
      // redirect
      return $this->redirectToRoute('coordinatore_scrutinio', ['classe' => $classe->getId()]);
    }
    // visualizza pagina
    return $this->render('coordinatore/esiti_'.($periodo == 'X' ? 'E' : $periodo).'.html.twig', array(
      'alunno' => $alunno,
      'classe' => $classe,
      'info' => $info,
      'dati' => $dati,
      'form' => $form->createView(),
    ));
  }

  /**
   * Gestione del credito
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param ScrutinioUtil $scr Funzioni di utilità per lo scrutinio
   * @param int $classe Identificativo della classe
   * @param string $periodo Periodo relativo allo scrutinio
   *
   * @return Response Pagina di risposta
   *
   * @Route("/coordinatore/scrutinio/credito/{alunno}/{periodo}/{classe}/{posizione}", name="coordinatore_scrutinio_credito",
   *    requirements={"alunno": "\d+", "periodo": "P|S|F|E|1|2|X", "posizione": "\d+", "classe": "\d+"},
   *    defaults={"posizione": 0, "classe": 0},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function scrutinioCreditoAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                          ScrutinioUtil $scr, $alunno, $periodo, $classe, $posizione) {
    // inizializza variabili
    $info = array();
    $valutazioni['F']['N'] = ['min' => 0, 'max' => 10, 'start' => 6, 'ticks' => '0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10', 'labels' => '"NC", 1, 2, 3, 4, 5, 6, 7, 8, 9, 10'];
    $valutazioni['F']['C'] = ['min' => 4, 'max' => 10, 'start' => 6, 'ticks' => '4, 5, 6, 7, 8, 9, 10', 'labels' => '"NC", 5, 6, 7, 8, 9, 10'];
    $valutazioni['F']['E'] = ['min' => 3, 'max' => 10, 'start' => 6, 'ticks' => '3, 4, 5, 6, 7, 8, 9, 10', 'labels' => '"NC", 4, 5, 6, 7, 8, 9, 10'];
    $valutazioni['F']['R'] = ['min' => 20, 'max' => 26, 'start' => 22, 'ticks' => '20, 21, 22, 23, 24, 25, 26', 'labels' => '"NC", "", "Suff.", "", "Buono", "", "Ottimo"'];
    $info['valutazioni'] = $valutazioni['F']['N'];
    $info['giudizi'] = $valutazioni['F']['R'];
    $credito = array();
    $credito[3] = [6 =>  7, 7 =>  8, 8 =>  9, 9 => 10, 10 => 11];
    $credito[4] = [6 =>  8, 7 =>  9, 8 => 10, 9 => 11, 10 => 12];
    $credito[5] = [5 => 11, 6 => 13, 7 => 15, 8 => 17, 9 => 19, 10 => 21];
    $dati = array();
    // controllo alunno
    $alunno = $em->getRepository('App:Alunno')->findOneBy(['id' => $alunno, 'abilitato' => 1]);
    if (!$alunno) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo classe
    if ($periodo == 'X') {
      $classe = $em->getRepository('App:Classe')->find($classe);
    } else {
      $classe = $alunno->getClasse();
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
    // controllo periodo
    $lista_scrutinio = $scr->scrutinioAttivo($classe);
    if ($periodo != $lista_scrutinio['periodo']) {
      // errore
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // elenco voti
    if ($periodo == 'X') {
      // esame alunni con scrutinio rinviato
      $dati = $scr->elencoVotiAlunnoRinviato($this->getUser(), $alunno, $classe, $periodo, true);
    } else {
      // scrutinio finale: tutti i voti
      $dati = $scr->elencoVotiAlunno($this->getUser(), $alunno, $periodo);
    }
    $valori = $dati['esito']->getDati();
    if ($classe->getAnno() == 5) {
      // classe quinta
      $m = ($dati['esito']->getMedia() < 6 ? 5 : ceil($dati['esito']->getMedia()));
    } else {
      // classe terza e quarta
      $m = ceil($dati['esito']->getMedia());
    }
    $dati['credito'] = $credito[$classe->getAnno()][$m];
    // credito per sospensione giudizio
    $creditoSospeso = false;
    if ($periodo == 'E' || $periodo == 'X') {
      foreach ($dati['voti'] as $voto) {
        if (!empty($voto->getDebito()) && $voto->getUnico() >= 7) {
          $creditoSospeso = true;
        }
      }
    }
    // form di inserimento
    $form = $this->container->get('form.factory')->createNamedBuilder('credito', FormType::class)
      ->setAction($this->generateUrl('coordinatore_scrutinio_credito', ['alunno' => $alunno->getId(),
        'periodo' => $periodo, 'posizione' => $posizione, 'classe' => $classe->getId()]))
      ->add('creditoScolastico', ChoiceType::class, array('label' => 'label.credito_scolastico',
        'data' => isset($valori['creditoScolastico']) ? $valori['creditoScolastico'] : null,
        'choices' => ['label.criterio_credito_desc_F' => 'F', 'label.criterio_credito_desc_I' => 'I',
          'label.criterio_credito_desc_P' => 'P', 'label.criterio_credito_desc_R' => 'R',
          'label.criterio_credito_desc_O' => 'O'],
        'placeholder' => null,
        'expanded' => true,
        'multiple' => true,
        'required' => false))
      ->add('creditoSospeso', HiddenType::class, array('label' => null,
        'data' => $creditoSospeso,
        'required' => false))
      ->add('creditoIntegrativo', ChoiceType::class, array('label' => 'label.credito_integrativo',
        'data' => isset($valori['creditoIntegrativo']) ? $valori['creditoIntegrativo'] : true,
        'choices' => ['label.credito_integrativo_si' => true, 'label.credito_integrativo_no' => false],
        'placeholder' => null,
        'expanded' => true,
        'multiple' => false,
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.submit'))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // modifica criteri
      $valori['creditoScolastico'] = $form->get('creditoScolastico')->getData();
      $valori['creditoMinimo'] = $dati['credito'];
      $valori['creditoIntegrativo'] = null;
      if (($classe->getAnno() == 4 && $alunno->getCredito3() == 6) ||
          ($classe->getAnno() == 5 && $alunno->getCredito4() == 6)) {
        $valori['creditoIntegrativo'] = $form->get('creditoIntegrativo')->getData();
      }
      $dati['esito']->setDati($valori);
      // modifica credito
      $criteri_cont = 0;
      foreach ($dati['esito']->getDati()['creditoScolastico'] as $c) {
        // conta criteri selezionati
        if ($c != '') {
          $criteri_cont++;
        }
      }
      if ($criteri_cont >= 2 && ($periodo == 'F' || $creditoSospeso)) {
        $dati['esito']->setCredito($dati['credito'] + 1);
      } else {
        $dati['esito']->setCredito($dati['credito']);
      }
      // memorizza dati
      $em->flush();
      // redirect
      return $this->redirectToRoute('coordinatore_scrutinio', ['classe' => $classe->getId(), 'posizione' => $posizione]);
    }
    // visualizza pagina
    return $this->render('coordinatore/crediti_'.($periodo == 'X' ? 'E' : $periodo).'.html.twig', array(
      'alunno' => $alunno,
      'classe' => $classe,
      'credito3' => (($periodo == 'X' && $classe->getAnno() == 4) ? $dati['esito']->getCreditoPrecedente() : $alunno->getCredito3()),
      'info' => $info,
      'dati' => $dati,
      'form' => $form->createView(),
    ));
  }

  /**
   * Compilazione della certificazione
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param ScrutinioUtil $scr Funzioni di utilità per lo scrutinio
   * @param int $classe Identificativo della classe
   * @param string $periodo Periodo relativo allo scrutinio
   * @param int $posizione Posizione per lo scrolling verticale della finestra
   *
   * @return Response Pagina di risposta
   *
   * @Route("/coordinatore/scrutinio/certificazione/{alunno}/{periodo}/{classe}/{posizione}", name="coordinatore_scrutinio_certificazione",
   *    requirements={"alunno": "\d+", "periodo": "P|S|F|E|1|2|X", "posizione": "\d+", "classe": "\d+"},
   *    defaults={"posizione": 0, "classe": 0},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function scrutinioCertificazioneAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                                 TranslatorInterface $trans, ScrutinioUtil $scr, $alunno, $periodo,
                                                 $classe, $posizione) {
    // inizializza variabili
    $info = array();
    $valutazioni['F']['N'] = ['min' => 0, 'max' => 10, 'start' => 6, 'ticks' => '0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10', 'labels' => '"NC", 1, 2, 3, 4, 5, 6, 7, 8, 9, 10'];
    $valutazioni['F']['C'] = ['min' => 4, 'max' => 10, 'start' => 6, 'ticks' => '4, 5, 6, 7, 8, 9, 10', 'labels' => '"NC", 5, 6, 7, 8, 9, 10'];
    $valutazioni['F']['E'] = ['min' => 3, 'max' => 10, 'start' => 6, 'ticks' => '3, 4, 5, 6, 7, 8, 9, 10', 'labels' => '"NC", 4, 5, 6, 7, 8, 9, 10'];
    $valutazioni['F']['R'] = ['min' => 20, 'max' => 26, 'start' => 22, 'ticks' => '20, 21, 22, 23, 24, 25, 26', 'labels' => '"NC", "", "Suff.", "", "Buono", "", "Ottimo"'];
    $info['valutazioni'] = $valutazioni['F']['N'];
    $info['giudizi'] = $valutazioni['F']['R'];
    $dati = array();
    // controllo alunno
    $alunno = $em->getRepository('App:Alunno')->findOneBy(['id' => $alunno, 'abilitato' => 1]);
    if (!$alunno) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo classe
    // controllo classe
    if ($periodo == 'X') {
      $classe = $em->getRepository('App:Classe')->find($classe);
    } else {
      $classe = $alunno->getClasse();
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
    // controllo periodo
    $lista_scrutinio = $scr->scrutinioAttivo($classe);
    if ($periodo != $lista_scrutinio['periodo']) {
      // errore
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // elenco voti
    if ($periodo == 'X') {
      // esame alunni con scrutinio rinviato
      $dati = $scr->elencoVotiAlunnoRinviato($this->getUser(), $alunno, $classe, $periodo, true);
    } else {
      // scrutinio finale: tutti i voti
      $dati = $scr->elencoVotiAlunno($this->getUser(), $alunno, $periodo);
    }
    $valori = $dati['esito']->getDati();
    // form di inserimento
    $form = $this->container->get('form.factory')->createNamedBuilder('certificazione', FormType::class)
      ->setAction($this->generateUrl('coordinatore_scrutinio_certificazione', ['alunno' => $alunno->getId(),
        'periodo' => $periodo, 'posizione' => $posizione, 'classe' => $classe->getId()]))
      ->add('certificazione_italiano', ChoiceType::class, array('label' => 'label.certificazione_italiano',
        'data' => isset($valori['certificazione_italiano']) ? $valori['certificazione_italiano'] : null,
        'choices' => ['label.certificazione_livello_B' => 'B', 'label.certificazione_livello_I' => 'I',
          'label.certificazione_livello_A' => 'A', 'label.certificazione_livello_N' => 'N'],
        'placeholder' => null,
        'expanded' => false,
        'multiple' => false,
        'choice_attr' => function($val, $key, $index) {
            return ['class' => 'gs-no-placeholder'];
          },
        'attr' => ['class' => 'gs-placeholder'],
        'required' => true))
      ->add('certificazione_italiano_motivazione', TextareaType::class, array('label' => false,
        'data' => isset($valori['certificazione_italiano_motivazione']) ? $valori['certificazione_italiano_motivazione'] : null,
        'trim' => true,
        'attr' => ['rows' => 4],
        'required' => false))
      ->add('certificazione_lingua', ChoiceType::class, array('label' => 'label.certificazione_lingua',
        'data' => isset($valori['certificazione_lingua']) ? $valori['certificazione_lingua'] : null,
        'choices' => ['label.certificazione_livello_B' => 'B', 'label.certificazione_livello_I' => 'I',
          'label.certificazione_livello_A' => 'A', 'label.certificazione_livello_N' => 'N'],
        'placeholder' => null,
        'expanded' => false,
        'multiple' => false,
        'choice_attr' => function($val, $key, $index) {
            return ['class' => 'gs-no-placeholder'];
          },
        'attr' => ['class' => 'gs-placeholder'],
        'required' => true))
      ->add('certificazione_lingua_motivazione', TextareaType::class, array('label' => false,
        'data' => isset($valori['certificazione_lingua_motivazione']) ? $valori['certificazione_lingua_motivazione'] : null,
        'trim' => true,
        'attr' => ['rows' => 4],
        'required' => false))
      ->add('certificazione_linguaggio', ChoiceType::class, array('label' => 'label.certificazione_linguaggio',
        'data' => isset($valori['certificazione_linguaggio']) ? $valori['certificazione_linguaggio'] : null,
        'choices' => ['label.certificazione_livello_B' => 'B', 'label.certificazione_livello_I' => 'I',
          'label.certificazione_livello_A' => 'A', 'label.certificazione_livello_N' => 'N'],
        'placeholder' => null,
        'expanded' => false,
        'multiple' => false,
        'choice_attr' => function($val, $key, $index) {
            return ['class' => 'gs-no-placeholder'];
          },
        'attr' => ['class' => 'gs-placeholder'],
        'required' => true))
      ->add('certificazione_linguaggio_motivazione', TextareaType::class, array('label' => false,
        'data' => isset($valori['certificazione_linguaggio_motivazione']) ? $valori['certificazione_linguaggio_motivazione'] : null,
        'trim' => true,
        'attr' => ['rows' => 4],
        'required' => false))
      ->add('certificazione_matematica', ChoiceType::class, array('label' => 'label.certificazione_matematica',
        'data' => isset($valori['certificazione_matematica']) ? $valori['certificazione_matematica'] : null,
        'choices' => ['label.certificazione_livello_B' => 'B', 'label.certificazione_livello_I' => 'I',
          'label.certificazione_livello_A' => 'A', 'label.certificazione_livello_N' => 'N'],
        'placeholder' => null,
        'expanded' => false,
        'multiple' => false,
        'choice_attr' => function($val, $key, $index) {
            return ['class' => 'gs-no-placeholder'];
          },
        'attr' => ['class' => 'gs-placeholder'],
        'required' => true))
      ->add('certificazione_matematica_motivazione', TextareaType::class, array('label' => false,
        'data' => isset($valori['certificazione_matematica_motivazione']) ? $valori['certificazione_matematica_motivazione'] : null,
        'trim' => true,
        'attr' => ['rows' => 4],
        'required' => false))
      ->add('certificazione_scienze', ChoiceType::class, array('label' => 'label.certificazione_scienze',
        'data' => isset($valori['certificazione_scienze']) ? $valori['certificazione_scienze'] : null,
        'choices' => ['label.certificazione_livello_B' => 'B', 'label.certificazione_livello_I' => 'I',
          'label.certificazione_livello_A' => 'A', 'label.certificazione_livello_N' => 'N'],
        'placeholder' => null,
        'expanded' => false,
        'multiple' => false,
        'choice_attr' => function($val, $key, $index) {
            return ['class' => 'gs-no-placeholder'];
          },
        'attr' => ['class' => 'gs-placeholder'],
        'required' => true))
      ->add('certificazione_scienze_motivazione', TextareaType::class, array('label' => false,
        'data' => isset($valori['certificazione_scienze_motivazione']) ? $valori['certificazione_scienze_motivazione'] : null,
        'trim' => true,
        'attr' => ['rows' => 4],
        'required' => false))
      ->add('certificazione_storia', ChoiceType::class, array('label' => 'label.certificazione_storia',
        'data' => isset($valori['certificazione_storia']) ? $valori['certificazione_storia'] : null,
        'choices' => ['label.certificazione_livello_B' => 'B', 'label.certificazione_livello_I' => 'I',
          'label.certificazione_livello_A' => 'A', 'label.certificazione_livello_N' => 'N'],
        'placeholder' => null,
        'expanded' => false,
        'multiple' => false,
        'choice_attr' => function($val, $key, $index) {
            return ['class' => 'gs-no-placeholder'];
          },
        'attr' => ['class' => 'gs-placeholder'],
        'required' => true))
      ->add('certificazione_storia_motivazione', TextareaType::class, array('label' => false,
        'data' => isset($valori['certificazione_storia_motivazione']) ? $valori['certificazione_storia_motivazione'] : null,
        'trim' => true,
        'attr' => ['rows' => 4],
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.submit'))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // controlla errori
      $err_motivazione = false;
      if ($form->get('certificazione_italiano')->getData() == 'N' &&
          $form->get('certificazione_italiano_motivazione')->getData() == '') {
        // motivazione non presente
        $err_motivazione = true;
      }
      if ($form->get('certificazione_lingua')->getData() == 'N' &&
          $form->get('certificazione_lingua_motivazione')->getData() == '') {
        // motivazione non presente
        $err_motivazione = true;
      }
      if ($form->get('certificazione_linguaggio')->getData() == 'N' &&
          $form->get('certificazione_linguaggio_motivazione')->getData() == '') {
        // motivazione non presente
        $err_motivazione = true;
      }
      if ($form->get('certificazione_matematica')->getData() == 'N' &&
          $form->get('certificazione_matematica_motivazione')->getData() == '') {
        // motivazione non presente
        $err_motivazione = true;
      }
      if ($form->get('certificazione_scienze')->getData() == 'N' &&
          $form->get('certificazione_scienze_motivazione')->getData() == '') {
        // motivazione non presente
        $err_motivazione = true;
      }
      if ($form->get('certificazione_storia')->getData() == 'N' &&
          $form->get('certificazione_storia_motivazione')->getData() == '') {
        // motivazione non presente
        $err_motivazione = true;
      }
      if ($err_motivazione) {
        // errore: motivazione non inserita
        $session->getFlashBag()->add('errore', $trans->trans('exception.no_motivazione_certificazione', [
          'sex' => ($alunno->getSesso() == 'M' ? 'o' : 'a'),
          'alunno' => $alunno->getCognome().' '.$alunno->getNome()]));
      }
      // modifica dati (anche se errati)
      $valori['certificazione'] = (!$err_motivazione);
      $valori['certificazione_italiano'] = $form->get('certificazione_italiano')->getData();
      $valori['certificazione_italiano_motivazione'] = $form->get('certificazione_italiano_motivazione')->getData();
      $valori['certificazione_lingua'] = $form->get('certificazione_lingua')->getData();
      $valori['certificazione_lingua_motivazione'] = $form->get('certificazione_lingua_motivazione')->getData();
      $valori['certificazione_linguaggio'] = $form->get('certificazione_linguaggio')->getData();
      $valori['certificazione_linguaggio_motivazione'] = $form->get('certificazione_linguaggio_motivazione')->getData();
      $valori['certificazione_matematica'] = $form->get('certificazione_matematica')->getData();
      $valori['certificazione_matematica_motivazione'] = $form->get('certificazione_matematica_motivazione')->getData();
      $valori['certificazione_scienze'] = $form->get('certificazione_scienze')->getData();
      $valori['certificazione_scienze_motivazione'] = $form->get('certificazione_scienze_motivazione')->getData();
      $valori['certificazione_storia'] = $form->get('certificazione_storia')->getData();
      $valori['certificazione_storia_motivazione'] = $form->get('certificazione_storia_motivazione')->getData();
      $dati['esito']->setDati($valori);
      // memorizza dati
      $em->flush();
      // redirect
      return $this->redirectToRoute('coordinatore_scrutinio', ['classe' => $classe->getId(),
        'posizione' => $posizione]);
    }
    // visualizza pagina
    return $this->render('coordinatore/certificazioni_'.($periodo == 'X' ? 'E' : $periodo).'.html.twig', array(
      'alunno' => $alunno,
      'classe' => $classe,
      'info' => $info,
      'dati' => $dati,
      'form' => $form->createView(),
    ));
  }

  /**
   * Compilazione della comunicazione dei debiti formativi
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param ScrutinioUtil $scr Funzioni di utilità per lo scrutinio
   * @param int $classe Identificativo della classe
   * @param string $periodo Periodo relativo allo scrutinio
   *
   * @return Response Pagina di risposta
   *
   * @Route("/coordinatore/scrutinio/debiti/{alunno}/{periodo}/{posizione}", name="coordinatore_scrutinio_debiti",
   *    requirements={"alunno": "\d+", "periodo": "P|S|F|E|1|2", "posizione": "\d+"},
   *    defaults={"posizione": 0},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function scrutinioDebitiAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                         TranslatorInterface $trans, ScrutinioUtil $scr, $alunno, $periodo, $posizione) {
    // inizializza variabili
    $info = array();
    $valutazioni['F']['N'] = ['min' => 0, 'max' => 10, 'start' => 6, 'ticks' => '0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10', 'labels' => '"NC", 1, 2, 3, 4, 5, 6, 7, 8, 9, 10'];
    $valutazioni['F']['E'] = ['min' => 3, 'max' => 10, 'start' => 6, 'ticks' => '3, 4, 5, 6, 7, 8, 9, 10', 'labels' => '"NC", 4, 5, 6, 7, 8, 9, 10'];
    $info['valutazioni'] = $valutazioni['F']['N'];
    $dati = array();
    // controllo alunno
    $alunno = $em->getRepository('App:Alunno')->findOneBy(['id' => $alunno, 'abilitato' => 1]);
    if (!$alunno || !$alunno->getClasse()) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo accesso alla funzione
    if (!($this->getUser() instanceOf Staff) && !($this->getUser() instanceOf Preside)) {
      // coordinatore
      $classi = explode(',', $session->get('/APP/DOCENTE/coordinatore'));
      if (!in_array($alunno->getClasse()->getId(), $classi)) {
        // errore
        throw $this->createNotFoundException('exception.invalid_params');
      }
    }
    // controllo periodo
    $lista_scrutinio = $scr->scrutinioAttivo($alunno->getClasse());
    if ($periodo != $lista_scrutinio['periodo']) {
      // errore
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // elenco debiti
    $dati = $scr->elencoDebitiAlunno($this->getUser(), $alunno, $periodo);
    // form di inserimento
    $form = $this->container->get('form.factory')->createNamedBuilder('debiti', FormType::class)
      ->setAction($this->generateUrl('coordinatore_scrutinio_debiti', ['alunno' => $alunno->getId(),
        'periodo' => $periodo, 'posizione' => $posizione]))
      ->add('lista', CollectionType::class, array('label' => false,
        'data' => $dati['debiti'],
        'entry_type' => VotoScrutinioType::class,
        'entry_options' => array('label' => false, 'attr' => ['subType' => 'debiti'])))
      ->add('submit', SubmitType::class, array('label' => 'label.submit'))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // controlla errori
      $errore = array();
      foreach ($form->get('lista')->getData() as $voto) {
        if (!$voto->getRecupero()) {
          $errore['exception.no_recupero_esito'] = true;
        }
        if (!$voto->getDebito()) {
          $errore['exception.no_debito_esito'] = true;
        }
      }
      // messaggi di errore
      foreach ($errore as $msg=>$val) {
        $session->getFlashBag()->add('errore', $trans->trans($msg, [
          'sex' => ($alunno->getSesso() == 'M' ? 'o' : 'a'),
          'alunno' => $alunno->getCognome().' '.$alunno->getNome()]));
      }
      // recupera esito
      $esito = $em->getRepository('App:Esito')->createQueryBuilder('e')
        ->join('e.scrutinio', 's')
        ->where('e.alunno=:alunno AND s.classe=:classe AND s.periodo=:periodo')
        ->setParameters(['alunno' => $alunno, 'classe' => $alunno->getClasse(), 'periodo' => $periodo])
        ->getQuery()
        ->setMaxResults(1)
        ->getOneOrNullResult();
      // modifica conferma
      $valori = $esito->getDati();
      if (count($errore) > 0) {
        // errore presente: non confermato
        $valori['debiti'] = false;
      } else {
        // nessun errore: confermato
        $valori['debiti'] = true;
      }
      $esito->setDati($valori);
      // memorizza dati
      $em->flush();
      // redirect
      return $this->redirectToRoute('coordinatore_scrutinio', ['classe' => $alunno->getClasse()->getId(), 'posizione' => $posizione]);
    }
    // visualizza pagina
    return $this->render('coordinatore/debiti_'.$periodo.'.html.twig', array(
      'alunno' => $alunno,
      'info' => $info,
      'dati' => $dati,
      'form' => $form->createView(),
    ));
  }

  /**
   * Compilazione della comunicazione delle carenze
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param ScrutinioUtil $scr Funzioni di utilità per lo scrutinio
   * @param int $classe Identificativo della classe
   * @param string $periodo Periodo relativo allo scrutinio
   *
   * @return Response Pagina di risposta
   *
   * @Route("/coordinatore/scrutinio/carenze/{alunno}/{periodo}/{posizione}", name="coordinatore_scrutinio_carenze",
   *    requirements={"alunno": "\d+", "periodo": "P|S|F|E|1|2", "posizione": "\d+"},
   *    defaults={"posizione": 0},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function scrutinioCarenzeAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                          ScrutinioUtil $scr, $alunno, $periodo, $posizione) {
    // inizializza variabili
    $info = array();
    $valutazioni['F']['N'] = ['min' => 0, 'max' => 10, 'start' => 6, 'ticks' => '0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10', 'labels' => '"NC", 1, 2, 3, 4, 5, 6, 7, 8, 9, 10'];
    $info['valutazioni'] = $valutazioni['F']['N'];
    $dati = array();
    // controllo alunno
    $alunno = $em->getRepository('App:Alunno')->findOneBy(['id' => $alunno, 'abilitato' => 1]);
    if (!$alunno || !$alunno->getClasse()) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo accesso alla funzione
    if (!($this->getUser() instanceOf Staff) && !($this->getUser() instanceOf Preside)) {
      // coordinatore
      $classi = explode(',', $session->get('/APP/DOCENTE/coordinatore'));
      if (!in_array($alunno->getClasse()->getId(), $classi)) {
        // errore
        throw $this->createNotFoundException('exception.invalid_params');
      }
    }
    // controllo periodo
    $lista_scrutinio = $scr->scrutinioAttivo($alunno->getClasse());
    if ($periodo != $lista_scrutinio['periodo']) {
      // errore
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // elenco carenze
    $dati = $scr->elencoCarenzeAlunno($this->getUser(), $alunno, $periodo);
    // form di inserimento
    $form = $this->container->get('form.factory')->createNamedBuilder('carenze', FormType::class)
      ->setAction($this->generateUrl('coordinatore_scrutinio_carenze', ['alunno' => $alunno->getId(),
        'periodo' => $periodo, 'posizione' => $posizione]))
      ->add('lista', CollectionType::class, array('label' => false,
        'data' => $dati['carenze'],
        'entry_type' => VotoScrutinioType::class,
        'entry_options' => array('label' => false, 'attr' => ['subType' => 'carenze'])))
      ->add('submit', SubmitType::class, array('label' => 'label.submit'))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // recupera esito
      $esito = $em->getRepository('App:Esito')->createQueryBuilder('e')
        ->join('e.scrutinio', 's')
        ->where('e.alunno=:alunno AND s.classe=:classe AND s.periodo=:periodo')
        ->setParameters(['alunno' => $alunno, 'classe' => $alunno->getClasse(), 'periodo' => $periodo])
        ->getQuery()
        ->setMaxResults(1)
        ->getOneOrNullResult();
      // legge valori
      $valori = $esito->getDati();
      // controlla carenze
      $valori['carenze_materie'] = array();
      foreach ($form->get('lista')->getData() as $voto) {
        if ($voto->getDebito()) {
          $valori['carenze_materie'][] = $voto->getMateria()->getNomeBreve();
        }
      }
      // conferma comunicazione
      $valori['carenze'] = true;
      $esito->setDati($valori);
      // memorizza dati
      $em->flush();
      // redirect
      return $this->redirectToRoute('coordinatore_scrutinio', ['classe' => $alunno->getClasse()->getId(), 'posizione' => $posizione]);
    }
    // visualizza pagina
    return $this->render('coordinatore/carenze_'.$periodo.'.html.twig', array(
      'alunno' => $alunno,
      'info' => $info,
      'dati' => $dati,
      'form' => $form->createView(),
    ));
  }

  /**
   * Gestione dello scrutinio della classe.
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param ScrutinioUtil $scr Funzioni di utilità per lo scrutinio
   * @param int $classe Identificativo della classe
   * @param string $periodo Periodo relativo allo scrutinio
   * @param int $step Passo della struttura del verbale da modificare
   *
   * @return Response Pagina di risposta
   *
   * @Route("/coordinatore/scrutinio/verbale/{classe}/{periodo}/{step}", name="coordinatore_scrutinio_verbale",
   *    requirements={"classe": "\d+", "periodo": "P|S|F|E|X|1|2", "step": "\d+"},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function verbaleAction(Request $request, EntityManagerInterface $em, SessionInterface $session, ScrutinioUtil $scr,
                                 $classe, $periodo, $step) {
    // inizializza variabili
    $dati = null;
    $form = null;
    // controllo classe
    $classe = $em->getRepository('App:Classe')->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // legge definizione scrutinio e scrutinio
    $def = $em->getRepository('App:DefinizioneScrutinio')->findOneByPeriodo($periodo);
    $scrutinio = $em->getRepository('App:Scrutinio')->findOneBy(['periodo' => $periodo,
      'classe' => $classe]);
    if (!$def || !$scrutinio) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo step
    if (empty($def->getStruttura()[$step][1])) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    $passo_verbale = $def->getStruttura()[$step];
    // controllo accesso alla funzione
    if (!($this->getUser() instanceOf Staff) && !($this->getUser() instanceOf Preside)) {
      // coordinatore
      $classi = explode(',', $session->get('/APP/DOCENTE/coordinatore'));
      if (!in_array($classe->getId(), $classi)) {
        // errore
        throw $this->createNotFoundException('exception.invalid_params');
      }
    }
    // esegue funzioni
    $func_dati = 'verbaleDati'.$passo_verbale[0];
    $func_form = 'verbaleForm'.$passo_verbale[0];
    $func_valida = 'verbaleValida'.$passo_verbale[0];
    $dati = $scr->$func_dati($classe, $periodo, $def, $scrutinio, $passo_verbale);
    $form = $this->container->get('form.factory')->createNamedBuilder('verbale', FormType::class,
      null, array('allow_extra_fields' => true));
    $form = $scr->$func_form($classe, $periodo, $form, $dati, $step, $passo_verbale);
    // legge dati form
    $form->handleRequest($request);
    if ($form->isSubmitted()) {
      // validazione
      $scr->$func_valida($this->getUser(), $request, $scrutinio, $form, $step, $passo_verbale);
      // se errori indica non validato
      if ($session->getFlashBag()->has('errore')) {
        // modifica validazione
        $scrutinio_dati = $scrutinio->getDati();
        $scrutinio_dati['verbale'][$step]['validato'] = false;
        // memorizza dati
        $scrutinio->setDati($scrutinio_dati);
        $em->flush();
      }
      // redirezione
      return $this->redirectToRoute('coordinatore_scrutinio', ['classe' => $classe->getId(),
        'stato' => $scrutinio->getStato()]);
    }
    // visualizza pagina
    return $this->render('coordinatore/verbale_'.strtolower($passo_verbale[0]).'.html.twig', array(
      'classe' => $classe,
      'dati' => $dati,
      'form' => ($form ? $form->createView() : null),
    ));
  }

  /**
   * Forza la cessata frequenza dal 15 marzo.
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param ScrutinioUtil $scr Funzioni di utilità per lo scrutinio
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $classe Identificativo della classe
   * @param string $periodo Periodo relativo allo scrutinio
   * @param int $alunno Identificativo dell'alunno
   *
   * @return Response Pagina di risposta
   *
   * @Route("/coordinatore/scrutinio/frequenza/{classe}/{periodo}/{alunno}", name="coordinatore_scrutinio_frequenza",
   *    requirements={"classe": "\d+", "periodo": "P|S|F|E|1|2", "alunno": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function scrutinioFrequenzaAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                            RegistroUtil $reg, ScrutinioUtil $scr, LogHandler $dblogger,
                                            $classe, $periodo, $alunno) {
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
    // controllo periodo
    $scrutinio_periodo = $scr->scrutinioAttivo($classe);
    if ($periodo != $scrutinio_periodo['periodo']) {
      // errore
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // controllo scrutinio
    $scrutinio = $em->getRepository('App:Scrutinio')->findOneBy(['classe' => $classe, 'periodo' => $periodo]);
    if (!$scrutinio) {
      // errore
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // controllo alunno
    $alunno = $em->getRepository('App:Alunno')->find($alunno);
    if (!$alunno) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // lezioni dal 15 marzo
    $giorni_lezione = $scr->lezioniDal15Marzo($classe);
    // controlla presenze
    $presenze = $scr->presenzeDal15Marzo($alunno->getId(), $giorni_lezione);
    if ($presenze['stato'] != 1) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // crea nuove assenze
    foreach ($presenze['giorni'] as $g) {
      $assenza = (new Assenza())
        ->setData($g)
        ->setAlunno($alunno)
        ->setDocente($this->getUser());
      $em->persist($assenza);
    }
    $giorni = array_map(function($d) { return $d->format('Y-m-d'); }, $presenze['giorni']);
    // memorizza date salvate
    $dati_scrutinio = $scrutinio->getDati();
    $dati_scrutinio['forza_assenze'][$alunno->getId()] = $giorni;
    $scrutinio->setDati($dati_scrutinio);
    // ok: memorizza dati
    $em->flush();
    // ricalcola ore assenza
    foreach ($presenze['giorni'] as $g) {
      $reg->ricalcolaOreAlunno($g, $alunno);
    }
    // log
    $dblogger->logAzione('ASSENZE', 'Dichiara cessata frequenza', array(
      'Alunno' => $alunno->getId(),
      'Assenze' => implode(', ', $giorni),
      ));
    // redirezione
    return $this->redirectToRoute('coordinatore_scrutinio');
  }

  /**
   * Annulla la cessata frequenza dal 15 marzo.
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param ScrutinioUtil $scr Funzioni di utilità per lo scrutinio
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $classe Identificativo della classe
   * @param string $periodo Periodo relativo allo scrutinio
   * @param int $alunno Identificativo dell'alunno
   *
   * @return Response Pagina di risposta
   *
   * @Route("/coordinatore/scrutinio/frequenza/annulla/{classe}/{periodo}/{alunno}", name="coordinatore_scrutinio_frequenza_annulla",
   *    requirements={"classe": "\d+", "periodo": "P|S|F|E|1|2", "alunno": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_STAFF")
   */
  public function scrutinioFrequenzaAnnullaAction(Request $request, EntityManagerInterface $em, RegistroUtil $reg,
                                                   ScrutinioUtil $scr, LogHandler $dblogger,
                                                   $classe, $periodo, $alunno) {
    // controllo classe
    $classe = $em->getRepository('App:Classe')->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo periodo
    $scrutinio_periodo = $scr->scrutinioAttivo($classe);
    if ($periodo != $scrutinio_periodo['periodo']) {
      // errore
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // controllo scrutinio
    $scrutinio = $em->getRepository('App:Scrutinio')->findOneBy(['classe' => $classe, 'periodo' => $periodo]);
    if (!$scrutinio) {
      // errore
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // controllo alunno
    $alunno = $em->getRepository('App:Alunno')->find($alunno);
    if (!$alunno) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // legge assenze
    $dati_scrutinio = $scrutinio->getDati();
    $lista_giorni = $dati_scrutinio['forza_assenze'][$alunno->getId()];
    $giorni = array_map(function($d) { return \DateTime::createFromFormat('!Y-m-d', $d); }, $lista_giorni);
    // elimina assenze
    foreach ($giorni as $g) {
      $assenza = $em->getRepository('App:Assenza')->findOneBy(['alunno' => $alunno, 'data' => $g]);
      $em->remove($assenza);
    }
    unset($dati_scrutinio['forza_assenze'][$alunno->getId()]);
    $scrutinio->setDati($dati_scrutinio);
    // ok: memorizza dati
    $em->flush();
    // ricalcola ore assenza
    foreach ($giorni as $g) {
      $reg->ricalcolaOreAlunno($g, $alunno);
    }
    // log
    $dblogger->logAzione('ASSENZE', 'Annulla dichiarazione cessata frequenza', array(
      'Alunno' => $alunno->getId(),
      ));
    // redirezione
    return $this->redirectToRoute('coordinatore_scrutinio');
  }

  /**
   * Gestione dei voti di ed. civica durante lo scrutinio
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param ScrutinioUtil $scr Funzioni di utilità per lo scrutinio
   * @param int $classe Identificativo della classe
   * @param string $periodo Periodo relativo allo scrutinio
   * @param int $alunno ID del singolo alunno o zero per l'intera classe
   * @param int $posizione Posizione per lo scrolling verticale della finestra
   *
   * @return Response Pagina di risposta
   *
   * @Route("/coordinatore/scrutinio/edcivica/{classe}/{periodo}/{alunno}/{posizione}", name="coordinatore_scrutinio_edcivica",
   *    requirements={"classe": "\d+", "periodo": "P|S|F|E|1|2", "alunno": "\d+", "posizione": "\d+"},
   *    defaults={"posizione": 0},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function scrutinioEdcivicaAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                           TranslatorInterface $trans, ScrutinioUtil $scr, $classe, $periodo, $alunno, $posizione) {
    // inizializza variabili
    $info = array();
    $info['valutazioni'] = ['min' => 3, 'max' => 10, 'start' => 6, 'ticks' => '3, 4, 5, 6, 7, 8, 9, 10', 'labels' => '"NC", 4, 5, 6, 7, 8, 9, 10', 'format' => '"Non Classificato", 4, 5, 6, 7, 8, 9, 10', 'format2' => '"NC", 4, 5, 6, 7, 8, 9, 10'];
    $dati = array();
    $dati['alunni'] = array();
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
    // controllo periodo
    $scrutinio = $scr->scrutinioAttivo($classe);
    if ($periodo != $scrutinio['periodo']) {
      // errore
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // legge ed civica
    $edcivica = $em->getRepository('App:Materia')->findOneByTipo('E');
    if (!$edcivica) {
      // errore
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // elenco voti/alunni
    $dati = $scr->elencoVoti($this->getUser(), $classe, $edcivica, $periodo);
    if ($alunno > 0) {
      // singolo alunno
      foreach ($dati['voti'] as $key=>$val) {
        if ($key != $alunno) {
          // toglie altri alunni
          unset($dati['voti'][$key]);
        }
      }
    }
    // legge proposte di voto
    $dati['proposte'] = $em->getRepository('App:PropostaVoto')->proposteEdCivica($classe, $periodo, array_keys($dati['voti']));
    foreach ($dati['proposte'] as $alu=>$prop) {
      if (isset($prop['debito']) && $dati['voti'][$alu]->getUnico() == null) {
        $dati['voti'][$alu]->setDebito($prop['debito']);
      }
    }
    // form di inserimento
    $tipo = ($periodo == 'P' ? 'debiti' : 'edcivica');
    $form = $this->container->get('form.factory')->createNamedBuilder('edcivica', FormType::class)
      ->setAction($this->generateUrl('coordinatore_scrutinio_edcivica', ['classe' => $classe->getId(),
        'periodo' => $periodo, 'alunno' => $alunno, 'posizione' => $posizione]))
      ->add('lista', CollectionType::class, array('label' => false,
        'data' => $dati['voti'],
        'entry_type' => VotoScrutinioType::class,
        'entry_options' => array('label' => false, 'attr' => ['subType' => $tipo])))
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' =>['class' => 'btn-primary']))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // controlla errori
      $errore = array();
      foreach ($form->get('lista')->getData() as $key=>$voto) {
        // controllo alunno
        $alunno = $em->getRepository('App:Alunno')->find($voto->getAlunno()->getId());
        if (!$alunno || !in_array($alunno->getId(), array_keys($dati['voti']))) {
          // alunno non esiste, salta
          $em->detach($voto);
          continue;
        } elseif ($voto->getUnico() === null || $voto->getUnico() < $info['valutazioni']['min'] || $voto->getUnico() > $info['valutazioni']['max']) {
          // voto non ammesso
          $em->detach($voto);
          $errore['exception.voto_edcivica'] = true;
        }
      }
      foreach ($errore as $msg=>$v) {
        $session->getFlashBag()->add('errore',
          $trans->trans($msg, ['materia' => $edcivica->getNomeBreve()]));
      }
      // ok: memorizza dati (anche errati)
      $em->flush();
      // redirect
      return $this->redirectToRoute('coordinatore_scrutinio', ['classe' => $classe->getId(), 'posizione' => $posizione]);
    } elseif ($form->isSubmitted() && !$form->isValid()) {
      // mostra altri errori
      foreach ($form->getErrors() as $error) {
        $session->getFlashBag()->add('errore', $error->getMessage());
      }
      // redirect
      return $this->redirectToRoute('coordinatore_scrutinio', ['classe' => $classe->getId(), 'posizione' => $posizione]);
    }
    // visualizza pagina
    return $this->render('coordinatore/edcivica_'.$periodo.'.html.twig', array(
      'classe' => $classe,
      'info' => $info,
      'dati' => $dati,
      'form' => $form->createView(),
    ));
  }

}
