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
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use App\Util\LogHandler;
use App\Util\ScrutinioUtil;
use App\Util\RegistroUtil;
use App\Form\PropostaVotoType;
use App\Form\VotoScrutinioType;
use App\Form\PianoIntegrazioneType;
use App\Entity\Staff;
use App\Entity\Preside;
use App\Entity\Alunno;
use App\Entity\Assenza;
use App\Entity\DefinizioneScrutinio;
use App\Entity\DocumentoInterno;


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
   *    requirements={"cattedra": "\d+", "classe": "\d+", "periodo": "P|S|F|I|1|2|0|X"},
   *    defaults={"cattedra": 0, "classe": 0, "periodo": "0"},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function proposteAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                 TranslatorInterface $trans, ScrutinioUtil $scr, LogHandler $dblogger, $cattedra, $classe, $periodo) {
    // inizializza variabili
    $info = array();
    $lista_periodi = null;
    $form = null;
    $form_title = null;
    $elenco = array();
    $elenco['alunni'] = array();
    $valutazioni['P']['N'] = ['min' => 0, 'max' => 10, 'start' => 6, 'ticks' => '0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10', 'labels' => '"NC", 1, 2, 3, 4, 5, 6, 7, 8, 9, 10'];
    $valutazioni['P']['R'] = ['min' => 20, 'max' => 25, 'start' => 22, 'ticks' => '20, 21, 22, 23, 24, 25', 'labels' => '"NC", "Insuff.", "Suff.", "Buono", "Dist.", "Ottimo"'];
    $valutazioni['1']['N'] = ['min' => 30, 'max' => 37, 'start' => 34, 'ticks' => '30, 31, 32, 33, 34, 35, 36, 37', 'labels' => '"NC", "Scarso", "", "", "Suff.", "", "", "Ottimo"'];
    $valutazioni['1']['R'] = ['min' => 30, 'max' => 37, 'start' => 34, 'ticks' => '30, 31, 32, 33, 34, 35, 36, 37', 'labels' => '"NC", "Scarso", "", "", "Suff.", "", "", "Ottimo"'];
    $valutazioni['F']['N'] = ['min' => 0, 'max' => 10, 'start' => 6, 'ticks' => '0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10', 'labels' => '"NC", 1, 2, 3, 4, 5, 6, 7, 8, 9, 10'];
    $valutazioni['F']['R'] = ['min' => 20, 'max' => 26, 'start' => 22, 'ticks' => '20, 21, 22, 23, 24, 25, 26', 'labels' => '"NC", "", "Suff.", "", "Buono", "", "Ottimo"'];
    $valutazioni['I']['N'] = ['min' => 0, 'max' => 10, 'start' => 6, 'ticks' => '0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10', 'labels' => '"NC", 1, 2, 3, 4, 5, 6, 7, 8, 9, 10'];
    $valutazioni['I']['R'] = ['min' => 20, 'max' => 25, 'start' => 22, 'ticks' => '20, 21, 22, 23, 24, 25', 'labels' => '"NC", "Insuff.", "Suff.", "Buono", "Dist.", "Ottimo"'];
    $valutazioni['X']['N'] = $valutazioni['I']['N'];
    $valutazioni['X']['R'] = $valutazioni['I']['R'];
    $title['P']['N'] = 'message.proposte';
    $title['P']['R'] = 'message.proposte_religione';
    $title['1']['N'] = 'message.proposte_intermedia';
    $title['1']['R'] = 'message.proposte_religione';
    $title['F']['N'] = 'message.proposte_covid';
    $title['F']['R'] = 'message.proposte_religione_covid';
    $title['I']['N'] = 'message.proposte_non_previste';
    $title['I']['R'] = 'message.proposte_non_previste';
    $title['X']['N'] = 'message.proposte_non_previste';
    $title['X']['R'] = 'message.proposte_non_previste';
    $info['valutazioni'] = $valutazioni['P']['N'];
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
        $elenco = $scr->elencoProposte($this->getUser(), $classe, $cattedra->getMateria(), $periodo);
        if ($lista_periodi[$periodo] == 'N') {
          // è possibile inserire le proposte
          $proposte_prec = unserialize(serialize($elenco['proposte'])); // clona oggetti
          // opzioni di proposte
          $opzioni = ['label' => false,
            'data' => $elenco['proposte'],
            'entry_type' => PropostaVotoType::class,
            'entry_options' => array('label' => false)
            ];
          if ($cattedra->getMateria()->getTipo() == 'R') {
            // religione
            $form_title = $title[$periodo]['R'];
            $info['valutazioni'] = $valutazioni[$periodo]['R'];
            //-- $info['religione'] = true;
            //-- $opzioni['attr'] = ['no_recupero' => false];
          } else {
            // altre materie
            $form_title = $title[$periodo]['N'];
            $info['valutazioni'] = $valutazioni[$periodo]['N'];
            //-- if ($periodo == 'F' && $classe->getAnno() == 5) {
              //-- // scrutinio finale di una quinta: no recupero
              //-- $opzioni['attr'] = ['no_recupero' => false];
              //-- $form_title = 'message.proposte_quinte';
            //-- }
          }
          if ($periodo == 'F' && $classe->getAnno() == 5) {
            // scrutinio finale di una quinta: no recupero
            $opzioni['attr'] = ['no_recupero' => false];
            $form_title = 'message.proposte_quinte';
          }
          // form di inserimento
          $form = $this->container->get('form.factory')->createNamedBuilder('proposte', FormType::class)
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
              //-- } elseif ($prop->getUnico() < 6 && $prop->getRecupero() === null && !isset($opzioni['attr']['no_recupero'])) {
                //-- // manca tipo recupero
                //-- $errori[2] = 'exception.no_recupero';
              //-- } elseif ($prop->getUnico() < 6 && empty($prop->getDebito()) && !isset($opzioni['attr']['no_recupero'])) {
                //-- // manca argomenti debito
                //-- $errori[3] = 'exception.no_debito';
              } elseif ($prop->getUnico() == 0 || $prop->getUnico() == 20) {
                // mancano obiettivi recupero
                $errori[2] = 'exception.no_valutazione_NC';
              } elseif (($prop->getUnico() < 6 || ($prop->getUnico() >= 20 && $prop->getUnico() < 22)) && $prop->getDebito() === null && !isset($opzioni['attr']['no_recupero'])) {
                // mancano obiettivi recupero
                $errori[3] = 'exception.no_obiettivi_recupero';
              } elseif (($prop->getUnico() < 6 || ($prop->getUnico() >= 20 && $prop->getUnico() < 22)) && $prop->getDato('strategie') === null && !isset($opzioni['attr']['no_recupero'])) {
                // mancano strategie recupero
                $errori[4] = 'exception.no_strategie_recupero';
              //-- } elseif ($prop->getUnico() >= 30 && $prop->getRecupero() === null &&
                        //-- isset($elenco['debiti'][$alunno->getId()])) {
                //-- // periodo 1: manca indicazione sul recupero
                //-- $errori[2] = 'exception.no_recupero_eseguito';
              } else {
                // controllo su obiettivi
                $o = preg_replace('/\b(il|del|nel)\b/',' ', strtolower($prop->getDebito()));
                $o = preg_replace('/\W+/','', $o);
                if (in_array($o, ['programmasvolto', 'programmasvoltopentamestre', 'tuttoprogramma',
                    'tuttoprogrammasvolto', 'programmapentamestre', 'tuttoprogrammapentamestre'])) {
                  // testo non valido
                  $errori[5] = 'exception.invalidi_obiettivi_recupero';
                }
              }
              if ($proposte_prec[$key]->getUnico() === null && $prop->getUnico() !== null) {
                // proposta aggiunta
                $log['create'][] = $prop;
              } elseif ($proposte_prec[$key]->getUnico() != $prop->getUnico() ||
                        $proposte_prec[$key]->getRecupero() != $prop->getRecupero() ||
                        $proposte_prec[$key]->getDebito() != $prop->getDebito() ||
                        $proposte_prec[$key]->getDato('strategie') != $prop->getDato('strategie')) {
                // proposta modificata
                $log['edit'][] = $proposte_prec[$key];
                // aggiorna docente proposta
                $prop->setDocente($this->getUser());
              }
              if (($prop->getUnico() >= 6 && $prop->getUnico() <= 10) || $prop->getUnico() >= 22 || isset($opzioni['attr']['no_recupero'])) {
                // svuota campi inutili
                $prop->setDebito('');
                $prop->addDato('strategie', '');
              }
            }
            if ($classe->getAnno() != 5) {
              // legge PIA
              $documento = $em->getRepository('App:DocumentoInterno')->findOneBy(['tipo' => 'A',
                'classe' => $classe, 'materia' => $cattedra->getMateria()]);
              if (!$documento || ($documento->getDato('necessario') &&
                  (empty($documento->getDato('obiettivi')) || empty($documento->getDato('strategie'))))) {
                // documento da compilare
                $errori[10] = 'exception.invalido_piano_integrazione_scrutinio';
              }
            }
            // ok: memorizza dati
            $em->flush();
            // log azione
            $dblogger->write($this->getUser(), $request->getClientIp(), 'SCRUTINIO', 'Proposte', __METHOD__, array(
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
    $info['giudizi']['P']['R'] = [20 => 'NC', 21 => 'Insuff.', 22 => 'Suff.', 23 => 'Buono', 24 => 'Dist.', 25 => 'Ottimo'];
    $info['giudizi']['1'] = [30 => 'NC', 31 => 'Scarso', 32 => 'Insuff.', 33 => 'Mediocre', 34 => 'Suff.', 35 => 'Discreto', 36 => 'Buono', 37 => 'Ottimo'];
    $info['condotta']['1'] = [40 => 'NC', 41 => 'Scorretta', 42 => 'Non sempre adeguata', 43 => 'Corretta'];
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
   *    requirements={"classe": "\d+", "materia": "\d+", "periodo": "P|S|F|I|1|2", "posizione": "\d+"},
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
    $valutazioni['P']['N'] = ['min' => 0, 'max' => 10, 'start' => 6, 'ticks' => '0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10', 'labels' => '"NC", 1, 2, 3, 4, 5, 6, 7, 8, 9, 10'];
    $valutazioni['P']['R'] = ['min' => 20, 'max' => 25, 'start' => 22, 'ticks' => '20, 21, 22, 23, 24, 25', 'labels' => '"NC", "Insuff.", "Suff.", "Buono", "Dist.", "Ottimo"'];
    $valutazioni['F']['N'] = ['min' => 0, 'max' => 10, 'start' => 6, 'ticks' => '0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10', 'labels' => '"NC", 1, 2, 3, 4, 5, 6, 7, 8, 9, 10'];
    $valutazioni['F']['R'] = ['min' => 20, 'max' => 26, 'start' => 22, 'ticks' => '20, 21, 22, 23, 24, 25, 26', 'labels' => '"NC", "", "Suff.", "", "Buono", "", "Ottimo"'];
    $info['valutazioni'] = $valutazioni['P']['N'];
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
    $info['no_recupero'] = false;
    if ($materia->getTipo() == 'R') {
      // religione
      $info['valutazioni'] = $valutazioni[$periodo]['R'];
      //-- $info['no_recupero'] = true;
    } else {
      // altre materie
      $info['valutazioni'] = $valutazioni[$periodo]['N'];
      //-- if ($periodo == 'F' && $classe->getAnno() == 5) {
        //-- $info['no_recupero'] = true;
      //-- }
    }
    if ($periodo == 'F' && $classe->getAnno() == 5) {
      $info['no_recupero'] = true;
    }
    // elenco proposte/alunni
    $elenco = $scr->elencoProposte($this->getUser(), $classe, $materia, $periodo);
    foreach ($elenco['proposte'] as $k=>$p) {
      //-- if ($materia->getTipo() == 'R') {
        //-- // religione
        //-- if ($p->getUnico() !== null) {
          //-- // ok, non modificabile
          //-- unset($elenco['proposte'][$k]);
        //-- }
      //-- } else {
        //-- // altre materie
        //-- if ($p->getUnico() >= 6) {
          //-- // ok, non modificabile
          //-- unset($elenco['proposte'][$k]);
        //-- } elseif ($p->getUnico() >= 6 ||
            //-- (!$info['no_recupero'] && $p->getUnico() !== null && $p->getRecupero() !== null && $p->getDebito() !== null) ||
            //-- ($info['no_recupero'] && $p->getUnico() !== null)) {
          //-- // ok, non modificabile
          //-- unset($elenco['proposte'][$k]);
        //-- }
      //-- }
      if (($p->getUnico() >= 6 && $p->getUnico() <= 10) || $p->getUnico() >= 22) {
        // voto sufficiente, non modificabile
        unset($elenco['proposte'][$k]);
      } elseif ($p->getUnico() !== null && $info['no_recupero']) {
        // voto qualsiasi di quinte, non modificabile
        unset($elenco['proposte'][$k]);
      } elseif (!$info['no_recupero'] && $p->getUnico() !== null && $p->getDebito() !== null &&
                $p->getDato('strategie') !== null) {
        // voto insuff. con oiettivi e strategie, non modificabile
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
        } elseif ($proposte_prec[$key]->getUnico() != $prop->getUnico() ||
                  $proposte_prec[$key]->getRecupero() != $prop->getRecupero() ||
                  $proposte_prec[$key]->getDebito() != $prop->getDebito() ||
                  $proposte_prec[$key]->getDato('strategie') != $prop->getDato('strategie')) {
          // proposta modificata
          $log['edit'][] = $proposte_prec[$key];
          // aggiorna docente proposta
          $prop->setDocente($this->getUser());
        }
      }
      // ok: memorizza dati
      $em->flush();
      // log azione
      $dblogger->write($this->getUser(), $request->getClientIp(), 'SCRUTINIO', 'Proposte', __METHOD__, array(
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
   *    requirements={"classe": "\d+", "periodo": "P|S|F|I|1|2", "alunno": "\d+", "posizione": "\d+"},
   *    defaults={"posizione": 0},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function scrutinioCondottaAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                           TranslatorInterface $trans, ScrutinioUtil $scr, $classe, $periodo, $alunno, $posizione) {
    // inizializza variabili
    $info = array();
    $info['valutazioni'] = ['min' => 4, 'max' => 10, 'start' => 6, 'ticks' => '4, 5, 6, 7, 8, 9, 10', 'labels' => '"NC", 5, 6, 7, 8, 9, 10'];
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
        } elseif ($voto->getUnico() < $info['valutazioni']['min'] || $voto->getUnico() > $info['valutazioni']['max']) {
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
        } elseif ($voto->getDato('unanimita') === false && empty($voto->getDato('contrari_motivazione'))) {
          // mancano contrari
          $errore['exception.contrari_motivazione_condotta'] = true;
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
   *    requirements={"classe": "\d+", "materia": "\d+", "periodo": "P|S|F|I|1|2|X", "alunno": "\d+", "posizione": "\d+"},
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
    $valutazioni['P']['R'] = ['min' => 20, 'max' => 25, 'start' => 22, 'ticks' => '20, 21, 22, 23, 24, 25', 'labels' => '"NC", "Insuff.", "Suff.", "Buono", "Dist.", "Ottimo"'];
    $valutazioni['1']['N'] = ['min' => 30, 'max' => 37, 'start' => 34, 'ticks' => '30, 31, 32, 33, 34, 35, 36, 37', 'labels' => '"NC", "Scarso", "", "", "Suff.", "", "", "Ottimo"'];
    $valutazioni['1']['R'] = ['min' => 30, 'max' => 37, 'start' => 34, 'ticks' => '30, 31, 32, 33, 34, 35, 36, 37', 'labels' => '"NC", "Scarso", "", "", "Suff.", "", "", "Ottimo"'];
    $valutazioni['F']['N'] = ['min' => 0, 'max' => 10, 'start' => 6, 'ticks' => '0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10', 'labels' => '"NC", 1, 2, 3, 4, 5, 6, 7, 8, 9, 10'];
    $valutazioni['F']['R'] = ['min' => 20, 'max' => 26, 'start' => 22, 'ticks' => '20, 21, 22, 23, 24, 25, 26', 'labels' => '"NC", "", "Suff.", "", "Buono", "", "Ottimo"'];
    $valutazioni['I']['N'] = $valutazioni['F']['N'];
    $valutazioni['I']['R'] = $valutazioni['F']['R'];
    $valutazioni['X']['N'] = $valutazioni['F']['N'];
    $valutazioni['X']['R'] = $valutazioni['F']['R'];
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
    if ($periodo != $scrutinio['periodo']) {
      // errore
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // informazioni necessarie
    $info['materia'] = $materia->getNome();
    if ($materia->getTipo() == 'R') {
      // religione
      $info['valutazioni'] = $valutazioni[$periodo]['R'];
    } else {
      // altre materie
      $info['valutazioni'] = $valutazioni[$periodo]['N'];
    }
    // elenco voti/alunni
    $dati = $scr->elencoVoti($this->getUser(), $classe, $materia, $periodo);
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
    $form = $this->container->get('form.factory')->createNamedBuilder('voti', FormType::class)
      ->setAction($this->generateUrl('coordinatore_scrutinio_voti', ['classe' => $classe->getId(),
        'materia' => $materia->getId(), 'periodo' => $periodo, 'alunno' => $alunno, 'posizione' => $posizione]))
      ->add('lista', CollectionType::class, array('label' => false,
        'data' => $dati['voti'],
        'entry_type' => VotoScrutinioType::class,
        'entry_options' => array('label' => false, 'attr' => ['subType' => 'esito'] )))
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
        //-- } elseif ($voto->getUnico() < 6 && !$voto->getRecupero() && $periodo != 'I' && $periodo != 'X') {
          //-- // manca indicazione recupero
          //-- $errore['exception.no_recupero_scrutinio'] = true;
        //-- } elseif ($voto->getUnico() < 6 && !$voto->getDebito() && $periodo != 'I' && $periodo != 'X') {
          //-- // manca indicazione argomenti
          //-- $errore['exception.no_debito_scrutinio'] = true;
        } elseif ($voto->getUnico() >= 30 && $voto->getRecupero() === null &&
                  isset($dati['debiti'][$alunno->getId()])) {
          // manca indicazione sul recupero
          $errore['exception.no_recupero_debito_scrutinio'] = true;
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
    return $this->render('coordinatore/voti_'.$periodo.'.html.twig', array(
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
   *    requirements={"cattedra": "\d+", "classe": "\d+", "periodo": "P|S|F|I|1|2|A|X"},
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
    $info['giudizi']['P']['R'] = [20 => 'NC', 21 => 'Insuff.', 22 => 'Suff.', 23 => 'Buono', 24 => 'Dist.', 25 => 'Ottimo'];
    $info['giudizi']['1'] = [30 => 'NC', 31 => 'Scarso', 32 => 'Insuff.', 33 => 'Mediocre', 34 => 'Suff.', 35 => 'Discreto', 36 => 'Buono', 37 => 'Ottimo'];
    $info['giudizi']['F']['R'] = [20 => 'NC', 21 => 'Insuff.', 22 => 'Suff.', 23 => 'Discr.', 24 => 'Buono', 25 => 'Dist.', 26 => 'Ottimo'];
    $info['giudizi']['I']['R'] = [20 => 'NC', 21 => 'Insuff.', 22 => 'Suff.', 23 => 'Buono', 24 => 'Dist.', 25 => 'Ottimo'];
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
        $periodo = $scrutinio['periodo'];
      } elseif (!isset($lista_periodi[$periodo]) || $lista_periodi[$periodo] != 'C') {
        // periodo indicato non valido
        $periodo = null;
      }
      if ($periodo == 'I' || $periodo == 'X') {
        // voti
        $dati = $scr->quadroVoti($this->getUser(), $classe, 'I');
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
   * @Route("/coordinatore/scrutinio/esito/{alunno}/{periodo}/{posizione}", name="coordinatore_scrutinio_esito",
   *    requirements={"alunno": "\d+", "periodo": "P|S|F|I|1|2|X", "posizione": "\d+"},
   *    defaults={"posizione": 0},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
   public function scrutinioEsitoAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                        TranslatorInterface $trans, ScrutinioUtil $scr, $alunno, $periodo, $posizione) {
    // inizializza variabili
    $info = array();
    $valutazioni['F']['N'] = ['min' => 0, 'max' => 10, 'start' => 6, 'ticks' => '0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10', 'labels' => '"NC", 1, 2, 3, 4, 5, 6, 7, 8, 9, 10'];
    $valutazioni['F']['C'] = ['min' => 4, 'max' => 10, 'start' => 6, 'ticks' => '4, 5, 6, 7, 8, 9, 10', 'labels' => '"NC", 5, 6, 7, 8, 9, 10'];
    $valutazioni['F']['R'] = ['min' => 20, 'max' => 26, 'start' => 22, 'ticks' => '20, 21, 22, 23, 24, 25, 26', 'labels' => '"NC", "", "Suff.", "", "Buono", "", "Ottimo"'];
    $info['valutazioni'] = $valutazioni['F']['N'];
    $info['giudizi'] = $valutazioni['F']['R'];
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
    // elenco voti
    if ($periodo == 'I' || $periodo == 'X') {
      // scrutinio integrativo: solo voti insuff.
      $dati = $scr->elencoVotiAlunnoSospeso($this->getUser(), $alunno, $periodo);
    } else {
      // scrutinio finale: tutti i voti
      $dati = $scr->elencoVotiAlunno($this->getUser(), $alunno, $periodo);
    }
    // esiti possibili
    //-- $lista_esiti = array('label.esito_A' => 'A', 'label.esito_N' => 'N', 'label.esito_S' => 'S');
    $lista_esiti = array('label.esito_A' => 'A', 'label.esito_N' => 'N');
    if ($periodo == 'I') {
      // integrazione scrutinio finale
      $lista_esiti = array('label.esito_A' => 'A', 'label.esito_N' => 'N', 'label.esito_X' => 'X');
    } elseif ($periodo == 'X') {
      // rinvio integrazione scrutinio
      $lista_esiti = array('label.esito_A' => 'A', 'label.esito_N' => 'N');
    }
    // form di inserimento
    $form = $this->container->get('form.factory')->createNamedBuilder('esito', FormType::class)
      ->setAction($this->generateUrl('coordinatore_scrutinio_esito', ['alunno' => $alunno->getId(),
        'periodo' => $periodo, 'posizione' => $posizione]))
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
      ->add('contrari_motivazione', TextareaType::class, array('label' => false,
        'data' => isset($dati['esito']->getDati()['contrari_motivazione']) ? $dati['esito']->getDati()['contrari_motivazione'] : null,
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
      $nc_cont = 0;
      $voti_cont = 0;
      foreach ($form->get('lista')->getData() as $key=>$voto) {
        // controllo voto
        if ($voto->getUnico() === null || $voto->getUnico() < $valutazioni['F'][$voto->getMateria()->getTipo()]['min'] ||
            $voto->getUnico() > $valutazioni['F'][$voto->getMateria()->getTipo()]['max']) {
          // voto non ammesso o non presente
          $em->detach($voto);
          $errore['exception.no_voto_esito'] = true;
        //-- } elseif ($voto->getMateria()->getTipo() == 'R' && $voto->getUnico() < $valutazioni['F']['R']['start']) {
          //-- // voto religione insufficiente
          //-- $insuff_religione = true;
          //-- $insuff_cont++;
        //-- } elseif ($voto->getMateria()->getTipo() == 'C' && $voto->getUnico() < $valutazioni['F']['C']['start']) {
          // voto condotta insufficiente
          //-- $insuff_condotta = true;
          //-- $insuff_cont++;
        //-- } elseif ($voto->getUnico() < $valutazioni['F'][$voto->getMateria()->getTipo()]['start']) {
          //-- // voto insufficiente
          //-- $insuff_cont++;
        } elseif ($voto->getUnico() == 0 || $voto->getUnico() == 20 ||
                  ($voto->getUnico() == 4 && $voto->getMateria()->getTipo() == 'C')) {
          // NC
          $nc_cont++;
        } else {
          // voto diverso da NC
          $voti_cont++;
        }
        if ($voto->getMateria()->getTipo() == 'C') {
          // evita modifiche sulla condotta
          $em->detach($voto);
        }
      }
      if ($form->get('esito')->getData() === null) {
        // manca esito
        $errore['exception.manca_esito'] = true;
      }
      if ($form->get('unanimita')->getData() !== true && $form->get('esito')->getData() == 'N') {
        // delibera senza unanimità per non ammessi
        $errore['exception.manca_unanimita'] = true;
      } elseif ($form->get('unanimita')->getData() === null && $form->get('esito')->getData() != 'X') {
        // manca delibera
        $errore['exception.delibera_esito'] = true;
      } elseif ($form->get('unanimita')->getData() === false && empty($form->get('contrari')->getData()) &&
                $form->get('esito')->getData() != 'X') {
        // mancano contrari
        $errore['exception.contrari_esito'] = true;
      } elseif ($form->get('unanimita')->getData() === false && empty($form->get('contrari_motivazione')->getData()) &&
                $form->get('esito')->getData() != 'X') {
        // manca motivazione contrari
        $errore['exception.motivazione_contrari_esito'] = true;
      }
      if ($form->get('esito')->getData() == 'N' && !$form->get('giudizio')->getData()) {
        // manca giudizio di non ammissione
        $errore['exception.giudizio_esito'] = true;
      }
      //-- if ($form->get('esito')->getData() == 'X' && !$form->get('giudizio')->getData()) {
        //-- // manca giudizio
        //-- $errore['exception.motivo_scrutinio_rinviato'] = true;
      //-- }
      //-- if ($form->get('esito')->getData() == 'A' && $insuff_cont > 0 && $alunno->getClasse()->getAnno() != 5) {
        //-- // insufficienze con ammissione (escluse quinte)
        //-- $errore['exception.insufficienze_ammissione_esito'] = true;
      //-- }
      //-- if ($form->get('esito')->getData() == 'N' && $insuff_cont == 0) {
        //-- // solo sufficienze con non ammissione
        //-- $errore['exception.sufficienze_non_ammissione_esito'] = true;
      //-- }
      //-- if ($form->get('esito')->getData() == 'S' && $insuff_cont == 0) {
        //-- // solo sufficienze con sospensione
        //-- $errore['exception.sufficienze_sospensione_esito'] = true;
      //-- }
      //-- if ($form->get('esito')->getData() != 'N' && $insuff_religione && $alunno->getClasse()->getAnno() != 5) {
        //-- // insuff. religione incoerente con esito (escluse quinte)
        //-- $errore['exception.voto_religione_esito'] = true;
      //-- }
      //-- if ($form->get('esito')->getData() != 'N' && $insuff_condotta) {
        //-- // insuff. condotta incoerente con esito
        //-- $errore['exception.voto_condotta_esito'] = true;
      //-- }
      //-- if ($form->get('esito')->getData() == 'S' && $alunno->getClasse()->getAnno() == 5) {
        //-- // sospensione in quinta
        //-- $errore['exception.quinta_sospeso_esito'] = true;
      //-- }
      //-- if ($form->get('esito')->getData() == 'A' && $alunno->getClasse()->getAnno() == 5 && $insuff_cont > 1) {
        //-- // ammissione in quinta con più insufficienze
        //-- $errore['exception.insufficienze_ammissione_quinta'] = true;
      //-- }
      if ($form->get('esito')->getData() == 'A' && $nc_cont > 0) {
        // ammissione con NC
        $errore['exception.ammissione_con_NC'] = true;
      }
      // controllo eccezioni su non ammissione
      $classi_sblocca = $em->getRepository('App:Configurazione')->findOneByParametro('scrutinio_classi_sblocca');
      $classi_sblocca = ($classi_sblocca ? explode(',', $classi_sblocca->getValore()) : []);
      if ($form->get('esito')->getData() == 'N' && !in_array($alunno->getClasse()->getId(), $classi_sblocca)) {
        // controlla primo trimestre
        $voti_P = $em->getRepository('App:VotoScrutinio')->createQueryBuilder('vs')
          ->select('COUNT(vs.id)')
          ->join('vs.scrutinio', 's')
          ->join('vs.materia', 'm')
          ->where('vs.alunno=:alunno AND s.periodo=:trimestre AND ((m.tipo=:normale AND vs.unico>0) OR (m.tipo=:religione AND vs.unico>20) OR (m.tipo=:condotta AND vs.unico>4))')
          ->setParameters(['alunno' => $alunno, 'trimestre' => 'P', 'normale' => 'N', 'religione' => 'R', 'condotta' => 'C'])
          ->getQuery()
          ->getSingleScalarResult();
        // controlla valutazioni nel secondo pentamestre
        $voti_S = $em->getRepository('App:Valutazione')->createQueryBuilder('v')
          ->select('COUNT(v.id)')
          ->join('v.lezione', 'l')
          ->where('v.alunno=:alunno AND v.voto > 0 AND l.data > :inizio')
          ->setParameters(['alunno' => $alunno,
            'inizio' => \DateTime::createFromFormat('Y-m-d 00:00:00', $session->get('/CONFIG/SCUOLA/periodo1_fine')) ])
          ->getQuery()
          ->getSingleScalarResult();
        if ($alunno->getClasse()->getAnno() == 5) {
          // non ammissione per le quinte
          $errore['exception.non_ammissione_quinte'] = true;
        } elseif ($voti_P > 0) {
          // non ammissione con valutazioni al primo trimestre
          $errore['exception.non_ammissione_con_voti_P'] = true;
        } elseif ($voti_S > 0) {
          // non ammissione con valutazioni al secondo pentamestre
          $errore['exception.non_ammissione_con_voti_S'] = true;
        } elseif ($voti_cont > 0) {
          // non ammissione con voti
          $errore['exception.non_ammissione_con_voti'] = true;
        }
      }
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
      $valori['contrari_motivazione'] = $form->get('contrari_motivazione')->getData();
      $valori['giudizio'] = $form->get('giudizio')->getData();
      $dati['esito']->setDati($valori);
      $dati['esito']->setEsito($form->get('esito')->getData());
      // memorizza dati (anche se errati)
      $em->flush();
      // redirect
      return $this->redirectToRoute('coordinatore_scrutinio', ['classe' => $alunno->getClasse()->getId(),
        'posizione' => $posizione]);
    } elseif ($form->isSubmitted() && !$form->isValid()) {
      // mostra altri errori
      foreach ($form->getErrors() as $error) {
        $session->getFlashBag()->add('errore', $error->getMessage());
      }
      // redirect
      return $this->redirectToRoute('coordinatore_scrutinio', ['classe' => $alunno->getClasse()->getId()]);
    }
    // visualizza pagina
    return $this->render('coordinatore/esiti_'.$periodo.'.html.twig', array(
      'alunno' => $alunno,
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
   * @Route("/coordinatore/scrutinio/credito/{alunno}/{periodo}/{posizione}", name="coordinatore_scrutinio_credito",
   *    requirements={"alunno": "\d+", "periodo": "P|S|F|I|1|2|X", "posizione": "\d+"},
   *    defaults={"posizione": 0},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function scrutinioCreditoAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                          ScrutinioUtil $scr, $alunno, $periodo, $posizione) {
    // inizializza variabili
    $info = array();
    $valutazioni['F']['N'] = ['min' => 0, 'max' => 10, 'start' => 6, 'ticks' => '0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10', 'labels' => '"NC", 1, 2, 3, 4, 5, 6, 7, 8, 9, 10'];
    $valutazioni['F']['C'] = ['min' => 4, 'max' => 10, 'start' => 6, 'ticks' => '4, 5, 6, 7, 8, 9, 10', 'labels' => '"NC", 5, 6, 7, 8, 9, 10'];
    $valutazioni['F']['R'] = ['min' => 20, 'max' => 26, 'start' => 22, 'ticks' => '20, 21, 22, 23, 24, 25, 26', 'labels' => '"NC", "", "Suff.", "", "Buono", "", "Ottimo"'];
    $info['valutazioni'] = $valutazioni['F']['N'];
    $info['giudizi'] = $valutazioni['F']['R'];
    $credito = array();
    $credito[3] = [5 =>  6, 6 =>  7, 7 =>  8, 8 =>  9, 9 => 10, 10 => 11];
    $credito[4] = [5 =>  6, 6 =>  8, 7 =>  9, 8 => 10, 9 => 11, 10 => 12];
    $credito[5] = [4 =>  9, 5 => 11, 6 => 13, 7 => 15, 8 => 17, 9 => 19, 10 => 21];
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
    // elenco voti
    $dati = $scr->elencoVotiAlunno($this->getUser(), $alunno, $periodo);
    $valori = $dati['esito']->getDati();
    if ($alunno->getClasse()->getAnno() == 5) {
      // classe quinta
      $m = ($dati['esito']->getMedia() < 5 ? 4 : ($dati['esito']->getMedia() < 6 ? 5 : ceil($dati['esito']->getMedia())));
    } else {
      // classi terze/quarte
      $m = ($dati['esito']->getMedia() < 6 ? 5 : ceil($dati['esito']->getMedia()));
    }
    $dati['credito'] = $credito[$alunno->getClasse()->getAnno()][$m];
    // form di inserimento
    $form = $this->container->get('form.factory')->createNamedBuilder('credito', FormType::class)
      ->setAction($this->generateUrl('coordinatore_scrutinio_credito', ['alunno' => $alunno->getId(),
        'periodo' => $periodo, 'posizione' => $posizione]))
      ->add('creditoScolastico', ChoiceType::class, array('label' => 'label.credito_scolastico',
        'data' => isset($valori['creditoScolastico']) ? $valori['creditoScolastico'] : null,
        'choices' => ['label.criterio_credito_desc_F' => 'F', 'label.criterio_credito_desc_I' => 'I',
          'label.criterio_credito_desc_P' => 'P', 'label.criterio_credito_desc_O' => 'O'],
        'placeholder' => null,
        'expanded' => true,
        'multiple' => true,
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.submit'))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // modifica criteri
      $valori['creditoScolastico'] = $form->get('creditoScolastico')->getData();
      $valori['creditoMinimo'] = $dati['credito'];
      $dati['esito']->setDati($valori);
      // modifica credito
      $criteri_cont = 0;
      foreach ($dati['esito']->getDati()['creditoScolastico'] as $c) {
        // conta criteri selezionati
        if ($c != '') {
          $criteri_cont++;
        }
      }
      if ($criteri_cont >= 2) {
        $dati['esito']->setCredito($dati['credito'] + 1);
      } else {
        $dati['esito']->setCredito($dati['credito']);
      }
      // memorizza dati
      $em->flush();
      // redirect
      return $this->redirectToRoute('coordinatore_scrutinio', ['classe' => $alunno->getClasse()->getId(), 'posizione' => $posizione]);
    }
    // visualizza pagina
    return $this->render('coordinatore/crediti_'.$periodo.'.html.twig', array(
      'alunno' => $alunno,
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
   * @Route("/coordinatore/scrutinio/certificazione/{alunno}/{periodo}/{posizione}", name="coordinatore_scrutinio_certificazione",
   *    requirements={"alunno": "\d+", "periodo": "P|S|F|I|1|2|X", "posizione": "\d+"},
   *    defaults={"posizione": 0},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function scrutinioCertificazioneAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                                 TranslatorInterface $trans, ScrutinioUtil $scr, $alunno, $periodo, $posizione) {
    // inizializza variabili
    $info = array();
    $valutazioni['F']['N'] = ['min' => 0, 'max' => 10, 'start' => 6, 'ticks' => '0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10', 'labels' => '"NC", 1, 2, 3, 4, 5, 6, 7, 8, 9, 10'];
    $valutazioni['F']['C'] = ['min' => 4, 'max' => 10, 'start' => 6, 'ticks' => '4, 5, 6, 7, 8, 9, 10', 'labels' => '"NC", 5, 6, 7, 8, 9, 10'];
    $valutazioni['F']['R'] = ['min' => 20, 'max' => 26, 'start' => 22, 'ticks' => '20, 21, 22, 23, 24, 25, 26', 'labels' => '"NC", "", "Suff.", "", "Buono", "", "Ottimo"'];
    $info['valutazioni'] = $valutazioni['F']['N'];
    $info['giudizi'] = $valutazioni['F']['R'];
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
    // elenco voti
    $dati = $scr->elencoVotiAlunno($this->getUser(), $alunno, $periodo);
    $valori = $dati['esito']->getDati();
    // form di inserimento
    $form = $this->container->get('form.factory')->createNamedBuilder('certificazione', FormType::class)
      ->setAction($this->generateUrl('coordinatore_scrutinio_certificazione', ['alunno' => $alunno->getId(),
        'periodo' => $periodo, 'posizione' => $posizione]))
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
      return $this->redirectToRoute('coordinatore_scrutinio', ['classe' => $alunno->getClasse()->getId(),
        'posizione' => $posizione]);
    }
    // visualizza pagina
    return $this->render('coordinatore/certificazioni_'.$periodo.'.html.twig', array(
      'alunno' => $alunno,
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
   *    requirements={"alunno": "\d+", "periodo": "P|S|F|I|1|2", "posizione": "\d+"},
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
   *    requirements={"alunno": "\d+", "periodo": "P|S|F|I|1|2", "posizione": "\d+"},
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
   *    requirements={"classe": "\d+", "periodo": "P|S|F|I|1|2", "step": "\d+"},
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
    // controllo periodo
    $scrutinio_chiuso = $scr->scrutinioChiuso($classe);
    if ($periodo != $scrutinio_chiuso['periodo']) {
      // errore
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // legge definizione scrutinio e scrutinio
    $def = $em->getRepository('App:DefinizioneScrutinio')->findOneByPeriodo($periodo);
    $scrutinio = $em->getRepository('App:Scrutinio')->findOneBy(['periodo' => $periodo,
      'classe' => $classe, 'stato' => 'C']);
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
      // redirezione
      return $this->redirectToRoute('coordinatore_scrutinio', ['classe' => $classe->getId(), 'stato' => 'C']);
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
   *    requirements={"classe": "\d+", "periodo": "P|S|F|I|1|2", "alunno": "\d+"},
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
    $dblogger->write($this->getUser(), $request->getClientIp(), 'ASSENZE', 'Dichiara cessata frequenza', __METHOD__, array(
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
   *    requirements={"classe": "\d+", "periodo": "P|S|F|I|1|2", "alunno": "\d+"},
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
    $dblogger->write($this->getUser(), $request->getClientIp(), 'ASSENZE', 'Annulla dichiarazione cessata frequenza', __METHOD__, array(
      'Alunno' => $alunno->getId(),
      ));
    // redirezione
    return $this->redirectToRoute('coordinatore_scrutinio');
  }

  /**
   * Inserimento e modifica del Piano di Integrazione degli Apprendimenti - Pagina con il form
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param int $cattedra Identificativo della cattedra (nullo se supplenza)
   *
   * @return Response Pagina di risposta
   *
   * @Route("/lezioni/scrutinio/pia/{cattedra}", name="lezioni_scrutinio_pia",
   *    requirements={"cattedra": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
   public function pianoIntegrazioneAction(Request $request, EntityManagerInterface $em, $cattedra) {
    // inizializza variabili
    $info = array();
    // parametro cattedra
    $cattedra = $em->getRepository('App:Cattedra')->findOneBy(['id' => $cattedra,
      'docente' => $this->getUser(), 'attiva' => 1]);
    if (!$cattedra) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // recupera dati
    $documento = $em->getRepository('App:DocumentoInterno')->findOneBy(['tipo' => 'A',
      'classe' => $cattedra->getClasse(), 'materia' => $cattedra->getMateria()]);
    if (!$documento) {
      // crea nuovo documento
      $documento = (new DocumentoInterno())
        ->setTipo('A')
        ->setDocente($this->getUser())
        ->setClasse($cattedra->getClasse())
        ->setMateria($cattedra->getMateria());
    }
    // informazioni necessarie
    $info['cattedra'] = $cattedra->getId();
    $info['classe'] = $cattedra->getClasse()->getAnno().'ª '.$cattedra->getClasse()->getSezione();
    $info['materia'] = $cattedra->getMateria()->getNomeBreve();
    // form di inserimento
    $form = $this->createForm(PianoIntegrazioneType::class, $documento);
    // visualizza pagina
    return $this->render('lezioni/piano_integrazione.html.twig', array(
      'info' => $info,
      'form' => $form->createView(),
      'form_title' => 'title.piano_integrativo_apprendimenti',
    ));
  }

  /**
   * Inserimento e modifica del Piano di Integrazione degli Apprendimenti - Pagina di invio
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $cattedra Identificativo della cattedra (nullo se supplenza)
   *
   * @return JsonResponse Informazioni di risposta
   *
   * @Route("/lezioni/scrutinio/pia/invio/{cattedra}", name="lezioni_scrutinio_pia_invio",
   *    requirements={"cattedra": "\d+"},
   *    methods={"POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
   public function pianoIntegrazioneInvioAction(Request $request, EntityManagerInterface $em, TranslatorInterface $trans,
                                                LogHandler $dblogger, $cattedra) {
    // inizializza risposta
    $risposta = array();
    // parametro cattedra
    $cattedra = $em->getRepository('App:Cattedra')->findOneBy(['id' => $cattedra,
      'docente' => $this->getUser(), 'attiva' => 1]);
    if (!$cattedra) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // recupera dati
    $documento = $em->getRepository('App:DocumentoInterno')->findOneBy(['tipo' => 'A',
      'classe' => $cattedra->getClasse(), 'materia' => $cattedra->getMateria()]);
    if (!$documento) {
      // crea nuovo documento
      $documento = (new DocumentoInterno())
        ->setTipo('A')
        ->setDocente($this->getUser())
        ->setClasse($cattedra->getClasse())
        ->setMateria($cattedra->getMateria());
      $em->persist($documento);
      $log = null;
    } else {
      // memorizza dati per log
      $log['docente'] = $documento->getDocente()->getId();
      $log['necessario'] = $documento->getDato('necessario');
      $log['obiettivi'] = $documento->getDato('obiettivi');
      $log['strategie'] = $documento->getDato('strategie');
    }
    // legge dati
    $risposta['stato'] = 'ok';
    $obiettivi = trim($request->get('obiettivi'));
    $strategie = trim($request->get('strategie'));
    $necessario = $request->get('necessario');
    // controlla dati
    if ($necessario === null) {
      $risposta['stato'] = 'errore';
      $risposta['errore'] = $trans->trans('exception.no_inserito_integrazione');
      // azzera valori inutili
      $obiettivi = '';
      $strategie = '';
    } elseif ($necessario == 1 && empty($obiettivi)) {
      $risposta['stato'] = 'errore';
      $risposta['errore'] = $trans->trans('exception.no_obiettivi_integrazione');
    } elseif ($necessario == 1 && empty($strategie)) {
      $risposta['stato'] = 'errore';
      $risposta['errore'] = $trans->trans('exception.no_strategie_integrazione');
    } elseif ($necessario == 0) {
      // azzera valori inutili
      $obiettivi = '';
      $strategie = '';
    }
    // imposta dati
    $documento->setDocente($this->getUser());
    $documento->addDato('necessario', $necessario);
    $documento->addDato('obiettivi', $obiettivi);
    $documento->addDato('strategie', $strategie);
    // ok, memorizza dati
    $em->flush();
    // log azione
    if ($log) {
      // modifica
      $dblogger->write($this->getUser(), $request->getClientIp(), 'SCRUTINIO', 'Modifica Piano Integrazione Apprendimenti', __METHOD__, array(
        'ID' => $documento->getId(),
        'Docente' => $log['docente'],
        'Necessario' => $log['necessario'],
        'Obiettivi' => $log['obiettivi'],
        'Strategie' => $log['strategie']));
    } else {
      // inserimento
      $dblogger->write($this->getUser(), $request->getClientIp(), 'SCRUTINIO', 'Inserimento Piano Integrazione Apprendimenti', __METHOD__, array(
        'ID' => $documento->getId()));
    }
    // restituisce risposta
    return new JsonResponse($risposta);
   }

  /**
   * Gestione del PIA durante lo scrutinio
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param int $classe Identificativo della classe
   * @param int $posizione Posizione per lo scrolling verticale della finestra
   *
   * @return Response Pagina di risposta
   *
   * @Route("/coordinatore/scrutinio/PIA/{classe}/{posizione}", name="coordinatore_scrutinio_PIA",
   *    requirements={"classe": "\d+", "posizione": "\d+"},
   *    defaults={"posizione": 0},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function scrutinioPIAAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                     TranslatorInterface $trans, $classe, $posizione) {
    // controllo classe
    $classe = $em->getRepository('App:Classe')->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo scrutinio
    $scrutinio = $em->getRepository('App:Scrutinio')->findOneBy(['classe' => $classe, 'periodo' => 'F']);
    if (!$scrutinio) {
      // errore
      throw $this->createNotFoundException('exception.not_allowed');
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
    // legge materie
    $materie = $em->getRepository('App:Materia')->createQueryBuilder('m')
      ->join('App:Cattedra', 'c', 'WITH', 'c.materia=m.id')
      ->where('c.classe=:classe AND c.attiva=:attiva AND c.tipo=:tipo AND m.tipo!=:sostegno')
      ->orderBy('m.ordinamento', 'ASC')
      ->setParameters(['classe' => $classe, 'attiva' => 1, 'tipo' => 'N', 'sostegno' => 'S'])
      ->getQuery()
      ->getResult();
    foreach ($materie as $mat) {
      $dati['piani'][$mat->getId()] = null;
    }
    // legge piani
    $piani = $em->getRepository('App:DocumentoInterno')->createQueryBuilder('di')
      ->join('di.materia', 'm')
      ->where('di.tipo=:tipo AND di.classe=:classe')
      ->setParameters(['tipo' => 'A', 'classe' => $classe])
      ->getQuery()
      ->getResult();
    foreach ($piani as $p) {
      $dati['piani'][$p->getMateria()->getId()] = $p;
    }
    foreach ($materie as $mat) {
      if (!$dati['piani'][$mat->getId()]) {
        $dati['piani'][$mat->getId()] = (new DocumentoInterno())
          ->setTipo('A')
          ->setDocente($this->getUser())
          ->setClasse($classe)
          ->setMateria($mat);
        $em->persist($dati['piani'][$mat->getId()]);
      }
    }
    // form di inserimento
    $form = $this->container->get('form.factory')->createNamedBuilder('scrutinio_PIA', FormType::class)
      ->setAction($this->generateUrl('coordinatore_scrutinio_PIA', ['classe' => $classe->getId(),
        'posizione' => $posizione]))
      ->add('lista', CollectionType::class, array('label' => false,
        'data' => $dati['piani'],
        'entry_type' => PianoIntegrazioneType::class,
        'entry_options' => array('label' => false)))
      ->add('submit', SubmitType::class, array('label' => 'label.submit'))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // controlla errori
      $errore = array();
      foreach ($form->get('lista')->getData() as $key=>$piano) {
        if ($piano->getDato('necessario') === true && (empty($piano->getDato('obiettivi')) ||
            empty($piano->getDato('strategie')))) {
          // da completare
          $errore[] = $trans->trans('exception.invalido_piano_integrativo_scrutinio',
            ['%materia%' => $piano->getMateria()->getNomeBreve()]);
        } elseif ($piano->getDato('necessario') === false) {
          // azzera dati
          $piano->addDato('obiettivi', '');
          $piano->addDato('strategie', '');
        } elseif ($piano->getDato('necessario') === null) {
          // da inserire
          $errore[] = $trans->trans('exception.no_piano_integrativo_scrutinio',
            ['%materia%' => $piano->getMateria()->getNomeBreve()]);
        }
      }
      foreach ($errore as $msg) {
        $session->getFlashBag()->add('errore', $msg);
      }
      // modifica stato
      $scrutinio->addDato('statoPIA', count($errore) > 0 ? false : true);
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
    return $this->render('coordinatore/PIA.html.twig', array(
      'classe' => $classe,
      'dati' => $dati,
      'form' => $form->createView(),
    ));
  }

  /**
   * Gestione del PAI durante lo scrutinio
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param ScrutinioUtil $scr Funzioni di utilità per lo scrutinio
   * @param int $classe Identificativo della classe
   * @param int $alunno Identificativo dell'alunno
   * @param int $posizione Posizione per lo scrolling verticale della finestra
   *
   * @return Response Pagina di risposta
   *
   * @Route("/coordinatore/scrutinio/PAI/{classe}/{alunno}/{posizione}", name="coordinatore_scrutinio_PAI",
   *    requirements={"classe": "\d+", "alunno": "\d+", "posizione": "\d+"},
   *    defaults={"posizione": 0},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function scrutinioPAIAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                     TranslatorInterface $trans, ScrutinioUtil $scr, $classe, $alunno, $posizione) {
    // controllo classe
    $classe = $em->getRepository('App:Classe')->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo alunno
    $alunno = $em->getRepository('App:Alunno')->findOneBy(['id' => $alunno, 'abilitato' => 1, 'classe' => $classe]);
    if (!$alunno) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo scrutinio
    $scrutinio = $em->getRepository('App:Scrutinio')->findOneBy(['classe' => $classe, 'periodo' => 'F']);
    if (!$scrutinio) {
      // errore
      throw $this->createNotFoundException('exception.not_allowed');
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
    // legge materie
    $materie = $em->getRepository('App:Materia')->createQueryBuilder('m')
      ->join('App:Cattedra', 'c', 'WITH', 'c.materia=m.id')
      ->where('c.classe=:classe AND c.attiva=:attiva AND c.tipo=:tipo AND m.tipo!=:sostegno')
      ->orderBy('m.ordinamento', 'ASC')
      ->setParameters(['classe' => $classe, 'attiva' => 1, 'tipo' => 'N', 'sostegno' => 'S'])
      ->getQuery()
      ->getResult();
    foreach ($materie as $mat) {
      $dati['piani'][$mat->getId()] = null;
    }
    // legge i voti e PAI (solo ammessi)
    $voti = $em->getRepository('App:VotoScrutinio')->createQueryBuilder('vs')
      ->join('vs.scrutinio', 's')
      ->join('vs.materia', 'm')
      ->where('s.classe=:classe AND s.periodo=:periodo AND vs.unico IS NOT NULL AND vs.alunno=:alunno AND m.tipo!=:condotta')
      ->setParameters(['classe' => $classe, 'periodo' => 'F', 'alunno' => $alunno, 'condotta' => 'C'])
      ->getQuery()
      ->getResult();
    foreach ($voti as $v) {
      // inserisce voti
      if (($v->getMateria()->getTipo() == 'R' && $v->getUnico() < 22) ||
          ($v->getMateria()->getTipo() != 'R' && $v->getUnico() < 6)) {
        // solo materie insufficienti
        $dati['piani'][$v->getMateria()->getId()] = $v;
      }
    }
    foreach ($dati['piani'] as $mat=>$piano) {
      if (!$piano) {
        // elimina materia senza insufficienze
        unset($dati['piani'][$mat]);
      }
    }
    // form di inserimento
    $form = $this->container->get('form.factory')->createNamedBuilder('scrutinio_PAI', FormType::class)
      ->setAction($this->generateUrl('coordinatore_scrutinio_PAI', ['classe' => $classe->getId(),
        'alunno' => $alunno->getId(), 'posizione' => $posizione]))
      ->add('lista', CollectionType::class, array('label' => false,
        'data' => $dati['piani'],
        'entry_type' => VotoScrutinioType::class,
        'entry_options' => array('label' => false, 'attr' => ['subType' => 'debiti'])))
      ->add('submit', SubmitType::class, array('label' => 'label.submit'))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // controlla errori
      $errore = array();
      foreach ($form->get('lista')->getData() as $key=>$piano) {
        if (empty($piano->getDebito()) || empty($piano->getDato('strategie'))) {
          // da completare
          $errore[1] = $trans->trans('exception.invalido_piano_recupero_scrutinio',
            ['%alunno%' => $alunno->getCognome().' '.$alunno->getNome()]);
        } else {
          // controllo obiettivi
          $o = preg_replace('/\b(il|del|nel)\b/',' ', strtolower($piano->getDebito()));
          $o = preg_replace('/\W+/','', $o);
          if (in_array($o, ['programmasvolto', 'programmasvoltopentamestre', 'tuttoprogramma',
              'tuttoprogrammasvolto', 'programmapentamestre', 'tuttoprogrammapentamestre'])) {
            // testo non valido
            $errore[1] = $trans->trans('exception.invalido_piano_recupero_scrutinio',
              ['%alunno%' => $alunno->getCognome().' '.$alunno->getNome()]);
          }
        }
      }
      foreach ($errore as $msg) {
        $session->getFlashBag()->add('errore', $msg);
      }
      // modifica stato
      $stato_alunni = $scrutinio->getDato('statoPAI');
      $stato_alunni[$alunno->getId()] = (count($errore) > 0 ? false : true);
      $scrutinio->addDato('statoPAI', $stato_alunni);
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
    return $this->render('coordinatore/PAI.html.twig', array(
      'classe' => $classe,
      'alunno' => $alunno,
      'dati' => $dati,
      'form' => $form->createView(),
    ));
  }

}
