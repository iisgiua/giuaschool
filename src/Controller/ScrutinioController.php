<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Controller;

use App\Entity\Preside;
use App\Entity\Staff;
use App\Form\MessageType;
use App\Form\PropostaVotoType;
use App\Form\VotoScrutinioType;
use App\Util\LogHandler;
use App\Util\ScrutinioUtil;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;


/**
 * ScrutinioController - gestione degli scrutini
 *
 * @author Antonello Dessì
 */
class ScrutinioController extends BaseController {

  /**
   * Gestione delle proposte di voto
   *
   * @param Request $request Pagina richiesta
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
   *    requirements={"cattedra": "\d+", "classe": "\d+", "periodo": "P|S|F|G|R|X"},
   *    defaults={"cattedra": 0, "classe": 0, "periodo": "0"},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function proposteAction(Request $request, TranslatorInterface $trans, ScrutinioUtil $scr,
                                 LogHandler $dblogger, $cattedra, $classe, $periodo) {
    // inizializza variabili
    $info = [];
    $lista_periodi = null;
    $form = null;
    $form_title = null;
    $elenco = array();
    $elenco['alunni'] = array();
    $title['P']['N'] = 'message.proposte';
    $title['P']['R'] = 'message.proposte_religione';
    $title['P']['E'] = 'message.proposte';
    $title['S']['N'] = 'message.proposte';
    $title['S']['R'] = 'message.proposte_religione';
    $title['S']['E'] = 'message.proposte';
    $title['F']['N'] = 'message.proposte';
    $title['F']['R'] = 'message.proposte_religione';
    $title['F']['E'] = 'message.proposte';
    $title['G']['N'] = 'message.proposte_non_previste';
    $title['G']['R'] = 'message.proposte_non_previste';
    $title['G']['E'] = 'message.proposte_non_previste';
    $title['X']['N'] = 'message.proposte_non_previste';
    $title['X']['R'] = 'message.proposte_non_previste';
    $title['X']['E'] = 'message.proposte_non_previste';
    $valutazioni['R'] = unserialize($this->em->getRepository('App\Entity\Configurazione')->getParametro('voti_finali_R'));
    $valutazioni['E'] = unserialize($this->em->getRepository('App\Entity\Configurazione')->getParametro('voti_finali_E'));
    $valutazioni['N'] = unserialize($this->em->getRepository('App\Entity\Configurazione')->getParametro('voti_finali_N'));
    // crea lista voti
    $listaValori = explode(',', $valutazioni['R']['valori']);
    $listaVoti = explode(',', $valutazioni['R']['votiAbbr']);
    foreach ($listaValori as $key=>$val) {
      $valutazioni['R']['lista'][$val] = trim($listaVoti[$key], '"');
    }
    $listaValori = explode(',', $valutazioni['E']['valori']);
    $listaVoti = explode(',', $valutazioni['E']['votiAbbr']);
    foreach ($listaValori as $key=>$val) {
      $valutazioni['E']['lista'][$val] = trim($listaVoti[$key], '"');
    }
    $listaValori = explode(',', $valutazioni['N']['valori']);
    $listaVoti = explode(',', $valutazioni['N']['votiAbbr']);
    foreach ($listaValori as $key=>$val) {
      $valutazioni['N']['lista'][$val] = trim($listaVoti[$key], '"');
    }
    // valore predefinito
    $info['valutazioni'] = $valutazioni['N'];
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
    // controllo cattedra/supplenza
    if ($cattedra > 0) {
      // lezione in propria cattedra: controlla esistenza
      $cattedra = $this->em->getRepository('App\Entity\Cattedra')->findOneBy(['id' => $cattedra,
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
      $info['valutazioni'] = $valutazioni[$cattedra->getMateria()->getTipo()];
      // imposta sessione
      $this->reqstack->getSession()->set('/APP/ROUTE/lezioni_scrutinio_proposte/valutazioni', $info['valutazioni']);
    } elseif ($classe > 0) {
      // supplenza
      $classe = $this->em->getRepository('App\Entity\Classe')->find($classe);
      if (!$classe) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
    }
    if ($cattedra) {
      // legge lista periodi
      $lista_periodi = $scr->periodi($classe);
      // rimuove eventuali scrutini di giudizio sospeso e supplettivi
      unset($lista_periodi['G']);
      unset($lista_periodi['R']);
      unset($lista_periodi['X']);
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
              $alunno = $this->em->getRepository('App\Entity\Alunno')->findOneBy(['id' => $prop->getAlunno()->getId(),
                'classe' => $classe->getId(), 'abilitato' => 1]);
              if (!$alunno) {
                // alunno non esiste, salta
                continue;
              }
              if ($prop->getUnico() === null) {
                // nessun voto
                $errori[0] = 'exception.no_voto';
                continue;
              }
              if ($prop->getUnico() < $info['valutazioni']['min']) {
                // corregge voto min
                $form->get('lista')->getData()[$key]->setUnico($info['valutazioni']['min']);
              } elseif ($prop->getUnico() > $info['valutazioni']['max']) {
                // corregge voto max
                $form->get('lista')->getData()[$key]->setUnico($info['valutazioni']['max']);
              }
              if ($prop->getUnico() < $info['valutazioni']['suff'] && $prop->getRecupero() === null && !isset($opzioni['attr']['no_recupero'])) {
                // manca tipo recupero
                  $errori[2] = 'exception.no_recupero';
              } elseif ($prop->getUnico() < $info['valutazioni']['suff'] && empty($prop->getDebito()) && !isset($opzioni['attr']['no_recupero'])) {
                // manca argomenti debito
                if ($prop->getUnico() > $info['valutazioni']['min']) {
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
              if ($prop->getUnico() >= $info['valutazioni']['suff'] || isset($opzioni['attr']['no_recupero'])) {
                // svuota campi inutili
                $prop->setDebito('');
              }
            }
            // ok: memorizza dati
            $this->em->flush();
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
    $this->reqstack->getSession()->set('/APP/DOCENTE/menu_lezione', $route);
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
  public function scrutinioAction(Request $request, ScrutinioUtil $scr, $classe, $stato, $posizione) {
    // inizializza variabili
    $dati = null;
    $form = null;
    $template = 'coordinatore/scrutinio.html.twig';
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
        $template = 'coordinatore/scrutinio_'.(in_array($scrutinio['periodo'], ['R', 'X']) ? 'G' : $scrutinio['periodo']).
          '_'.$scrutinio['stato'].'.html.twig';
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
          $template = 'coordinatore/scrutinio_'.(in_array($scrutinio['periodo'], ['R', 'X']) ? 'G' : $scrutinio['periodo']).
            '_'.$scrutinio['stato'].'.html.twig';
        }
      }
    }
    // visualizza pagina
    return $this->render($template, array(
      'pagina_titolo' => 'page.coordinatore_scrutinio',
      'classe' => $classe,
      'dati' => $dati,
      'form' => ($form ? $form->createView() : null),
      'posizione' => $posizione,
    ));
  }

  /**
   * Gestione delle proposte di voto mancanti al momento dell'inizio dello scrutinio
   *
   * @param Request $request Pagina richiesta
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
   *    requirements={"classe": "\d+", "materia": "\d+", "periodo": "P|S|F|G|R|X", "posizione": "\d+"},
   *    defaults={"posizione": 0},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function scrutinioProposteAction(Request $request, ScrutinioUtil $scr, LogHandler $dblogger,
                                          $classe, $materia, $periodo, $posizione) {
    // inizializza variabili
    $info = array();
    $elenco = array();
    $elenco['alunni'] = array();
    $valutazioni['R'] = unserialize($this->em->getRepository('App\Entity\Configurazione')->getParametro('voti_finali_R'));
    $valutazioni['E'] = unserialize($this->em->getRepository('App\Entity\Configurazione')->getParametro('voti_finali_E'));
    $valutazioni['N'] = unserialize($this->em->getRepository('App\Entity\Configurazione')->getParametro('voti_finali_N'));
    // crea lista voti
    $listaValori = explode(',', $valutazioni['R']['valori']);
    $listaVoti = explode(',', $valutazioni['R']['votiAbbr']);
    foreach ($listaValori as $key=>$val) {
      $valutazioni['R']['lista'][$val] = trim($listaVoti[$key], '"');
    }
    $listaValori = explode(',', $valutazioni['E']['valori']);
    $listaVoti = explode(',', $valutazioni['E']['votiAbbr']);
    foreach ($listaValori as $key=>$val) {
      $valutazioni['E']['lista'][$val] = trim($listaVoti[$key], '"');
    }
    $listaValori = explode(',', $valutazioni['N']['valori']);
    $listaVoti = explode(',', $valutazioni['N']['votiAbbr']);
    foreach ($listaValori as $key=>$val) {
      $valutazioni['N']['lista'][$val] = trim($listaVoti[$key], '"');
    }
    // valore predefinito
    $info['valutazioni'] = $valutazioni['N'];
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
    // controllo materia
    $materia = $this->em->getRepository('App\Entity\Materia')->createQueryBuilder('m')
      ->join('App\Entity\Cattedra', 'c', 'WITH', 'c.materia=m.id')
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
    $info['valutazioni'] = $valutazioni[$materia->getTipo()];
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
        $alunno = $this->em->getRepository('App\Entity\Alunno')->findOneBy(['id' => $prop->getAlunno()->getId(),
          'classe' => $classe->getId(), 'abilitato' => 1]);
        if (!$alunno) {
          // alunno non esiste, salta
          $this->em->detach($prop);
          continue;
        } elseif ($prop->getUnico() < $info['valutazioni']['min'] || $prop->getUnico() > $info['valutazioni']['max']) {
          // voto non ammesso
          $this->em->detach($prop);
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
      $this->em->flush();
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
   *    requirements={"classe": "\d+", "periodo": "P|S|F", "alunno": "\d+", "posizione": "\d+"},
   *    defaults={"posizione": 0},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function scrutinioCondottaAction(Request $request, TranslatorInterface $trans, ScrutinioUtil $scr,
                                          $classe, $periodo, $alunno, $posizione) {
    // inizializza variabili
    $dati = array();
    $dati['alunni'] = array();
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
    // controllo periodo
    $scrutinio_attivo = $scr->scrutinioAttivo($classe);
    if ($periodo != $scrutinio_attivo['periodo']) {
      // errore
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // legge condotta
    $condotta = $this->em->getRepository('App\Entity\Materia')->findOneByTipo('C');
    if (!$condotta) {
      // errore
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // elenco voti/alunni
    $dati = $scr->elencoVoti($this->getUser(), $classe, $condotta, $periodo);
    $scrutinio = $this->em->getRepository('App\Entity\Scrutinio')->findOneBy(['classe' => $classe, 'periodo' => $periodo]);
    $dati['assenze'] = $scrutinio->getDato('scrutinabili');
    $dati['valutazioni'] = $scrutinio->getDato('valutazioni')['C'];
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
        $alunno = $this->em->getRepository('App\Entity\Alunno')->find($voto->getAlunno()->getId());
        if (!$alunno || !in_array($alunno->getId(), array_keys($dati['voti']))) {
          // alunno non esiste, salta
          $this->em->detach($voto);
          continue;
        } elseif ($voto->getUnico() === null || $voto->getUnico() < $dati['valutazioni']['min'] || $voto->getUnico() > $dati['valutazioni']['max']) {
          // voto non ammesso
          $this->em->detach($voto);
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
        $this->reqstack->getSession()->getFlashBag()->add('errore', $trans->trans($msg));
      }
      // ok: memorizza dati (anche errati)
      $this->em->flush();
      // redirect
      return $this->redirectToRoute('coordinatore_scrutinio', ['classe' => $classe->getId(), 'posizione' => $posizione]);
    } elseif ($form->isSubmitted() && !$form->isValid()) {
      // mostra altri errori
      foreach ($form->getErrors() as $error) {
        $this->reqstack->getSession()->getFlashBag()->add('errore', $error->getMessage());
      }
      // redirect
      return $this->redirectToRoute('coordinatore_scrutinio', ['classe' => $classe->getId(), 'posizione' => $posizione]);
    }
    // visualizza pagina
    return $this->render('coordinatore/condotta_'.$periodo.'.html.twig', array(
      'periodo' => $periodo,
      'classe' => $classe,
      'dati' => $dati,
      'form' => $form->createView(),
    ));
  }

  /**
   * Gestione dei voti durante lo scrutinio
   *
   * @param Request $request Pagina richiesta
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
   *    requirements={"classe": "\d+", "materia": "\d+", "periodo": "P|S|F|G|R|X", "alunno": "\d+", "posizione": "\d+"},
   *    defaults={"posizione": 0},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function scrutinioVotiAction(Request $request, TranslatorInterface $trans, ScrutinioUtil $scr,
                                      $classe, $materia, $periodo, $alunno, $posizione) {
    // inizializza variabili
    $info = array();
    $dati = array();
    $dati['alunni'] = array();
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
    // controllo materia
    if ($periodo == 'X') {
      $materia = $this->em->getRepository('App\Entity\Materia')->find($materia);
    } else {
      // scrutini altri periodi
      $materia = $this->em->getRepository('App\Entity\Materia')->createQueryBuilder('m')
        ->join('App\Entity\Cattedra', 'c', 'WITH', 'c.materia=m.id')
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
    $info['materiaTipo'] = $materia->getTipo();
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
    // dati valutazioni
    $dati['valutazioni'] = $this->em->getRepository('App\Entity\Scrutinio')
      ->findOneBy(['classe' => $classe, 'periodo' => $periodo])
      ->getDato('valutazioni');
    // form di inserimento
    $tipo = (($periodo == 'P' || $periodo == 'S') ? 'debiti' : 'esito');
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
        $alunno = $this->em->getRepository('App\Entity\Alunno')->find($voto->getAlunno()->getId());
        if (!$alunno || !in_array($alunno->getId(), array_keys($dati['voti']))) {
          // alunno non esiste, salta
          $this->em->detach($voto);
          continue;
        } elseif ($voto->getUnico() === null || $voto->getUnico() < $dati['valutazioni'][$materia->getTipo()]['min'] ||
                   $voto->getUnico() > $dati['valutazioni'][$materia->getTipo()]['max']) {
          // voto non ammesso o non presente
          $this->em->detach($voto);
          $errore['exception.no_voto_scrutinio'] = true;
        }
      }
      foreach ($errore as $msg=>$v) {
        $this->reqstack->getSession()->getFlashBag()->add('errore',
          $trans->trans($msg, ['materia' => $materia->getNomeBreve()]));
      }
      // memorizza dati (anche se errati)
      $this->em->flush();
      // redirect
      return $this->redirectToRoute('coordinatore_scrutinio', ['classe' => $classe->getId(), 'posizione' => $posizione]);
    } elseif ($form->isSubmitted() && !$form->isValid()) {
      // mostra altri errori
      foreach ($form->getErrors() as $error) {
        $this->reqstack->getSession()->getFlashBag()->add('errore', $error->getMessage());
      }
      // redirect
      return $this->redirectToRoute('coordinatore_scrutinio', ['classe' => $classe->getId(), 'posizione' => $posizione]);
    }
    // visualizza pagina
    return $this->render('coordinatore/voti_'.(in_array($periodo, ['R', 'X']) ? 'G' : $periodo).'.html.twig', array(
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
   * @param ScrutinioUtil $scr Funzioni di utilità per lo scrutinio
   * @param int $cattedra Identificativo della cattedra (nullo se supplenza)
   * @param int $classe Identificativo della classe
   * @param string $periodo Periodo relativo allo scrutinio
   *
   * @return Response Pagina di risposta
   *
   * @Route("/lezioni/scrutinio/svolto/{cattedra}/{classe}/{periodo}", name="lezioni_scrutinio_svolto",
   *    requirements={"cattedra": "\d+", "classe": "\d+", "periodo": "P|S|F|G|R|X|A"},
   *    defaults={"cattedra": 0, "classe": 0, "periodo": "0"},
   *    methods="GET")
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function scrutinioSvoltoAction(Request $request, ScrutinioUtil $scr, $cattedra, $classe, $periodo) {
    // inizializza variabili
    $dati = array();
    $lista_periodi = null;
    $info = array();
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
      $info['materia_id'] = $cattedra->getMateria()->getId();
      $info['materia_tipo'] = $cattedra->getMateria()->getTipo();
      $info['alunno'] = $cattedra->getAlunno();
    } elseif ($classe > 0) {
      // supplenza
      $classe = $this->em->getRepository('App\Entity\Classe')->find($classe);
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
      if ($periodo == 'G' || $periodo == 'R' || $periodo == 'X') {
        // voti
        $dati = $scr->quadroVoti($this->getUser(), $classe, 'G');
        if (isset($lista_periodi['R']) && $lista_periodi['R'] == 'C') {
          $dati['rinviati'] = $scr->quadroVoti($this->getUser(), $classe, 'R');
        }
        $periodo = 'G';
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
    $this->reqstack->getSession()->set('/APP/DOCENTE/menu_lezione', $route);
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
   * Gestione dell'esito dello scrutinio
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param ScrutinioUtil $scr Funzioni di utilità per lo scrutinio
   * @param int $classe Identificativo della classe
   * @param string $periodo Periodo relativo allo scrutinio
   * @param int $posizione Posizione per lo scrolling verticale della finestra
   *
   * @return Response Pagina di risposta
   *
   * @Route("/coordinatore/scrutinio/esito/{alunno}/{periodo}/{classe}/{posizione}", name="coordinatore_scrutinio_esito",
   *    requirements={"alunno": "\d+", "periodo": "P|S|F|G|R|X", "posizione": "\d+", "classe": "\d+"},
   *    defaults={"posizione": 0, "classe": 0},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
   public function scrutinioEsitoAction(Request $request, TranslatorInterface $trans, ScrutinioUtil $scr,
                                        $alunno, $periodo, $classe, $posizione) {
    // inizializza variabili
    $dati = array();
    // controllo alunno
    $alunno = $this->em->getRepository('App\Entity\Alunno')->findOneBy(['id' => $alunno, 'abilitato' => 1]);
    if (!$alunno) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo classe
    if ($periodo == 'X') {
      $classe = $this->em->getRepository('App\Entity\Classe')->find($classe);
    } else {
      $classe = $alunno->getClasse();
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
    // controllo periodo
    $lista_scrutinio = $scr->scrutinioAttivo($classe, true);
    if ($periodo != $lista_scrutinio['periodo']) {
      // errore
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // elenco voti
    if ($periodo == 'G' || $periodo == 'R') {
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
    if ($periodo == 'G') {
      // esame alunni sospesi
      $lista_esiti = array('label.esito_A' => 'A', 'label.esito_N' => 'N', 'label.esito_X' => 'X');
    } elseif ($periodo == 'R' || $periodo == 'X') {
      // rinvio esame alunni sospesi
      $lista_esiti = array('label.esito_A' => 'A', 'label.esito_N' => 'N');
    }
    // legge dati valutazioni
    $dati['valutazioni'] = $this->em->getRepository('App\Entity\Scrutinio')
      ->findOneBy(['classe' => $classe, 'periodo' => $periodo])
      ->getDato('valutazioni');
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
      ->add('giudizio', MessageType::class, array('label' => false,
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
        if ($voto->getUnico() === null || $voto->getUnico() < $dati['valutazioni'][$voto->getMateria()->getTipo()]['min'] ||
            $voto->getUnico() > $dati['valutazioni'][$voto->getMateria()->getTipo()]['max']) {
          // voto non ammesso o non presente
          $this->em->detach($voto);
          $errore['exception.no_voto_esito'] = true;
        } elseif ($voto->getMateria()->getTipo() == 'R' && $voto->getUnico() < $dati['valutazioni']['R']['suff']) {
          // voto religione insufficiente
          $insuff_religione = true;
          $insuff_cont++;
        } elseif ($voto->getMateria()->getTipo() == 'C' && $voto->getUnico() < $dati['valutazioni']['C']['suff']) {
          // voto condotta insufficiente
          $insuff_condotta = true;
          $insuff_cont++;
        } elseif ($voto->getUnico() < $dati['valutazioni'][$voto->getMateria()->getTipo()]['suff']) {
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
      } elseif ($form->get('esito')->getData() == 'A' && $classe->getAnno() == 5 &&
                $insuff_condotta) {
        // ammissione in quinta con una insufficienza in condotta
        $errore['exception.voto_condotta_esito'] = true;
      }
      // imposta eventuali messaggi di errore
      foreach ($errore as $msg=>$v) {
        $this->reqstack->getSession()->getFlashBag()->add('errore', $trans->trans($msg, [
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
      $this->em->flush();
      // redirect
      return $this->redirectToRoute('coordinatore_scrutinio', ['classe' => $classe->getId(),
        'posizione' => $posizione]);
    } elseif ($form->isSubmitted() && !$form->isValid()) {
      // mostra altri errori
      foreach ($form->getErrors() as $error) {
        $this->reqstack->getSession()->getFlashBag()->add('errore', $error->getMessage());
      }
      // redirect
      return $this->redirectToRoute('coordinatore_scrutinio', ['classe' => $classe->getId(),
        'posizione' => $posizione]);
    }
    // visualizza pagina
    return $this->render('coordinatore/esiti_'.(in_array($periodo, ['R', 'X']) ? 'G' : $periodo).'.html.twig', array(
      'alunno' => $alunno,
      'classe' => $classe,
      'dati' => $dati,
      'form' => $form->createView(),
    ));
  }

  /**
   * Gestione del credito
   *
   * @param Request $request Pagina richiesta
   * @param ScrutinioUtil $scr Funzioni di utilità per lo scrutinio
   * @param int $alunno Identificativo dell'alunno
   * @param string $periodo Periodo relativo allo scrutinio
   * @param int $classe Identificativo della classe
   * @param int $posizione Posizione per lo scrolling verticale della finestra
   *
   * @return Response Pagina di risposta
   *
   * @Route("/coordinatore/scrutinio/credito/{alunno}/{periodo}/{classe}/{posizione}", name="coordinatore_scrutinio_credito",
   *    requirements={"alunno": "\d+", "periodo": "P|S|F|G|R|X", "posizione": "\d+", "classe": "\d+"},
   *    defaults={"posizione": 0, "classe": 0},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function scrutinioCreditoAction(Request $request, ScrutinioUtil $scr, $alunno,
                                         $periodo, $classe, $posizione) {
    // inizializza variabili
    $credito = array();
    $credito[3] = [6 =>  7, 7 =>  8, 8 =>  9, 9 => 10, 10 => 11];
    $credito[4] = [6 =>  8, 7 =>  9, 8 => 10, 9 => 11, 10 => 12];
    $credito[5] = [5 =>  7, 6 =>  9, 7 => 10, 8 => 11,  9 => 13, 10 => 14];
    $dati = array();
    // controllo alunno
    $alunno = $this->em->getRepository('App\Entity\Alunno')->findOneBy(['id' => $alunno, 'abilitato' => 1]);
    if (!$alunno) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo classe
    if ($periodo == 'X') {
      $classe = $this->em->getRepository('App\Entity\Classe')->find($classe);
    } else {
      $classe = $alunno->getClasse();
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
    if ($periodo == 'G' || $periodo == 'R' || $periodo == 'X') {
      foreach ($dati['voti'] as $voto) {
        if (!empty($voto->getDebito()) && $voto->getUnico() >= 7) {
          $creditoSospeso = true;
        }
      }
    }
    // legge dati valutazioni
    $dati['valutazioni'] = $this->em->getRepository('App\Entity\Scrutinio')
      ->findOneBy(['classe' => $classe, 'periodo' => $periodo])
      ->getDato('valutazioni');
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
      if ($criteri_cont >= 2 && ($periodo == 'F' || $creditoSospeso)) {
        $dati['esito']->setCredito($dati['credito'] + 1);
      } else {
        $dati['esito']->setCredito($dati['credito']);
      }
      // memorizza dati
      $this->em->flush();
      // redirect
      return $this->redirectToRoute('coordinatore_scrutinio', ['classe' => $classe->getId(), 'posizione' => $posizione]);
    }
    // visualizza pagina
    return $this->render('coordinatore/crediti_'.(in_array($periodo, ['R', 'X']) ? 'G' : $periodo).'.html.twig', array(
      'alunno' => $alunno,
      'classe' => $classe,
      'credito3' => (($periodo == 'X' && $classe->getAnno() == 4) ? $dati['esito']->getCreditoPrecedente() : $alunno->getCredito3()),
      'dati' => $dati,
      'form' => $form->createView(),
    ));
  }

  /**
   * Compilazione della certificazione
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param ScrutinioUtil $scr Funzioni di utilità per lo scrutinio
   * @param int $classe Identificativo della classe
   * @param string $periodo Periodo relativo allo scrutinio
   * @param int $posizione Posizione per lo scrolling verticale della finestra
   *
   * @return Response Pagina di risposta
   *
   * @Route("/coordinatore/scrutinio/certificazione/{alunno}/{periodo}/{classe}/{posizione}", name="coordinatore_scrutinio_certificazione",
   *    requirements={"alunno": "\d+", "periodo": "P|S|F|G|R|X", "posizione": "\d+", "classe": "\d+"},
   *    defaults={"posizione": 0, "classe": 0},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function scrutinioCertificazioneAction(Request $request, TranslatorInterface $trans,
                                                ScrutinioUtil $scr, $alunno, $periodo, $classe, $posizione) {
    // inizializza variabili
    $dati = array();
    // controllo alunno
    $alunno = $this->em->getRepository('App\Entity\Alunno')->findOneBy(['id' => $alunno, 'abilitato' => 1]);
    if (!$alunno) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo classe
    if ($periodo == 'X') {
      $classe = $this->em->getRepository('App\Entity\Classe')->find($classe);
    } else {
      $classe = $alunno->getClasse();
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
    // legge dati valutazioni
    $dati['valutazioni'] = $this->em->getRepository('App\Entity\Scrutinio')
      ->findOneBy(['classe' => $classe, 'periodo' => $periodo])
      ->getDato('valutazioni');
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
      ->add('certificazione_italiano_motivazione', MessageType::class, array('label' => false,
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
      ->add('certificazione_lingua_motivazione', MessageType::class, array('label' => false,
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
      ->add('certificazione_linguaggio_motivazione', MessageType::class, array('label' => false,
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
      ->add('certificazione_matematica_motivazione', MessageType::class, array('label' => false,
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
      ->add('certificazione_scienze_motivazione', MessageType::class, array('label' => false,
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
      ->add('certificazione_storia_motivazione', MessageType::class, array('label' => false,
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
        $this->reqstack->getSession()->getFlashBag()->add('errore', $trans->trans('exception.no_motivazione_certificazione', [
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
      $this->em->flush();
      // redirect
      return $this->redirectToRoute('coordinatore_scrutinio', ['classe' => $classe->getId(),
        'posizione' => $posizione]);
    }
    // visualizza pagina
    return $this->render('coordinatore/certificazioni_'.(in_array($periodo, ['R', 'X']) ? 'G' : $periodo).'.html.twig', array(
      'alunno' => $alunno,
      'classe' => $classe,
      'dati' => $dati,
      'form' => $form->createView(),
    ));
  }

  /**
   * Compilazione della comunicazione dei debiti formativi
   *
   * @param Request $request Pagina richiesta
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param ScrutinioUtil $scr Funzioni di utilità per lo scrutinio
   * @param int $alunno Identificativo dell'alunno
   * @param string $periodo Periodo relativo allo scrutinio
   * @param int $posizione Posizione per lo scrolling verticale della finestra
   *
   * @return Response Pagina di risposta
   *
   * @Route("/coordinatore/scrutinio/debiti/{alunno}/{periodo}/{posizione}", name="coordinatore_scrutinio_debiti",
   *    requirements={"alunno": "\d+", "periodo": "P|S|F", "posizione": "\d+"},
   *    defaults={"posizione": 0},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function scrutinioDebitiAction(Request $request, TranslatorInterface $trans, ScrutinioUtil $scr,
                                        $alunno, $periodo, $posizione) {
    // inizializza variabili
    $dati = array();
    // controllo alunno
    $alunno = $this->em->getRepository('App\Entity\Alunno')->findOneBy(['id' => $alunno, 'abilitato' => 1]);
    if (!$alunno || !$alunno->getClasse()) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo accesso alla funzione
    if (!($this->getUser() instanceOf Staff) && !($this->getUser() instanceOf Preside)) {
      // coordinatore
      $classi = explode(',', $this->reqstack->getSession()->get('/APP/DOCENTE/coordinatore'));
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
    // legge dati valutazioni
    $dati['valutazioni'] = $this->em->getRepository('App\Entity\Scrutinio')
      ->findOneBy(['classe' => $alunno->getClasse(), 'periodo' => $periodo])
      ->getDato('valutazioni');
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
        $this->reqstack->getSession()->getFlashBag()->add('errore', $trans->trans($msg, [
          'sex' => ($alunno->getSesso() == 'M' ? 'o' : 'a'),
          'alunno' => $alunno->getCognome().' '.$alunno->getNome()]));
      }
      if ($periodo != 'P' && $periodo != 'S') {
        // recupera esito
        $esito = $this->em->getRepository('App\Entity\Esito')->createQueryBuilder('e')
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
      }
      // memorizza dati
      $this->em->flush();
      // redirect
      return $this->redirectToRoute('coordinatore_scrutinio', ['classe' => $alunno->getClasse()->getId(), 'posizione' => $posizione]);
    }
    // visualizza pagina
    return $this->render('coordinatore/debiti_'.$periodo.'.html.twig', array(
      'alunno' => $alunno,
      'dati' => $dati,
      'form' => $form->createView(),
    ));
  }

  /**
   * Compilazione della comunicazione delle carenze
   *
   * @param Request $request Pagina richiesta
   * @param ScrutinioUtil $scr Funzioni di utilità per lo scrutinio
   * @param int $alunno Identificativo dell'alunno
   * @param string $periodo Periodo relativo allo scrutinio
   * @param int $posizione Posizione per lo scrolling verticale della finestra
   *
   * @return Response Pagina di risposta
   *
   * @Route("/coordinatore/scrutinio/carenze/{alunno}/{periodo}/{posizione}", name="coordinatore_scrutinio_carenze",
   *    requirements={"alunno": "\d+", "periodo": "P|S|F", "posizione": "\d+"},
   *    defaults={"posizione": 0},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function scrutinioCarenzeAction(Request $request, ScrutinioUtil $scr, $alunno, $periodo, $posizione) {
    // inizializza variabili
    $dati = array();
    // controllo alunno
    $alunno = $this->em->getRepository('App\Entity\Alunno')->findOneBy(['id' => $alunno, 'abilitato' => 1]);
    if (!$alunno || !$alunno->getClasse()) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo accesso alla funzione
    if (!($this->getUser() instanceOf Staff) && !($this->getUser() instanceOf Preside)) {
      // coordinatore
      $classi = explode(',', $this->reqstack->getSession()->get('/APP/DOCENTE/coordinatore'));
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
    // legge dati valutazioni
    $dati['valutazioni'] = $this->em->getRepository('App\Entity\Scrutinio')
      ->findOneBy(['classe' => $alunno->getClasse(), 'periodo' => $periodo])
      ->getDato('valutazioni');
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
      $esito = $this->em->getRepository('App\Entity\Esito')->createQueryBuilder('e')
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
      $this->em->flush();
      // redirect
      return $this->redirectToRoute('coordinatore_scrutinio', ['classe' => $alunno->getClasse()->getId(), 'posizione' => $posizione]);
    }
    // visualizza pagina
    return $this->render('coordinatore/carenze_'.$periodo.'.html.twig', array(
      'alunno' => $alunno,
      'dati' => $dati,
      'form' => $form->createView(),
    ));
  }

  /**
   * Gestione dello scrutinio della classe.
   *
   * @param Request $request Pagina richiesta
   * @param ScrutinioUtil $scr Funzioni di utilità per lo scrutinio
   * @param int $classe Identificativo della classe
   * @param string $periodo Periodo relativo allo scrutinio
   * @param int $step Passo della struttura del verbale da modificare
   *
   * @return Response Pagina di risposta
   *
   * @Route("/coordinatore/scrutinio/verbale/{classe}/{periodo}/{step}", name="coordinatore_scrutinio_verbale",
   *    requirements={"classe": "\d+", "periodo": "P|S|F|G|R|X", "step": "\d+"},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function verbaleAction(Request $request, ScrutinioUtil $scr, $classe, $periodo, $step) {
    // inizializza variabili
    $dati = null;
    $form = null;
    // controllo classe
    $classe = $this->em->getRepository('App\Entity\Classe')->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // legge definizione scrutinio e scrutinio
    $def = $this->em->getRepository('App\Entity\DefinizioneScrutinio')->findOneByPeriodo($periodo);
    $scrutinio = $this->em->getRepository('App\Entity\Scrutinio')->findOneBy(['periodo' => $periodo,
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
      $classi = explode(',', $this->reqstack->getSession()->get('/APP/DOCENTE/coordinatore'));
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
      if ($this->reqstack->getSession()->getFlashBag()->has('errore')) {
        // modifica validazione
        $scrutinio_dati = $scrutinio->getDati();
        $scrutinio_dati['verbale'][$step]['validato'] = false;
        // memorizza dati
        $scrutinio->setDati($scrutinio_dati);
        $this->em->flush();
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
   * Gestione dei voti di ed. civica durante lo scrutinio
   *
   * @param Request $request Pagina richiesta
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
   *    requirements={"classe": "\d+", "periodo": "P|S|F|G|R|X", "alunno": "\d+", "posizione": "\d+"},
   *    defaults={"posizione": 0},
   *    methods={"GET","POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function scrutinioEdcivicaAction(Request $request, TranslatorInterface $trans, ScrutinioUtil $scr,
                                          $classe, $periodo, $alunno, $posizione) {
    // inizializza variabili
    $dati = array();
    $dati['alunni'] = array();
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
    // controllo periodo
    $scrutinio = $scr->scrutinioAttivo($classe);
    if ($periodo != $scrutinio['periodo']) {
      // errore
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // legge ed civica
    $edcivica = $this->em->getRepository('App\Entity\Materia')->findOneByTipo('E');
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
    $dati['proposte'] = $this->em->getRepository('App\Entity\PropostaVoto')->proposteEdCivica($classe, $periodo, array_keys($dati['voti']));
    foreach ($dati['proposte'] as $alu=>$prop) {
      if (isset($prop['debito']) && $dati['voti'][$alu]->getUnico() === null) {
        $dati['voti'][$alu]->setDebito($prop['debito']);
      }
    }
    // legge dati valutazioni
    $dati['valutazioni'] = $this->em->getRepository('App\Entity\Scrutinio')
      ->findOneBy(['classe' => $classe, 'periodo' => $periodo])
      ->getDato('valutazioni')['E'];
    // form di inserimento
    $tipo = (($periodo == 'P' || $periodo == 'S') ? 'debiti' : 'edcivica');
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
        $alunno = $this->em->getRepository('App\Entity\Alunno')->find($voto->getAlunno()->getId());
        if (!$alunno || !in_array($alunno->getId(), array_keys($dati['voti']))) {
          // alunno non esiste, salta
          $this->em->detach($voto);
          continue;
        } elseif ($voto->getUnico() === null || $voto->getUnico() < $dati['valutazioni']['min'] || $voto->getUnico() > $dati['valutazioni']['max']) {
          // voto non ammesso
          $this->em->detach($voto);
          $errore['exception.voto_edcivica'] = true;
        }
      }
      foreach ($errore as $msg=>$v) {
        $this->reqstack->getSession()->getFlashBag()->add('errore',
          $trans->trans($msg, ['materia' => $edcivica->getNomeBreve()]));
      }
      // ok: memorizza dati (anche errati)
      $this->em->flush();
      // redirect
      return $this->redirectToRoute('coordinatore_scrutinio', ['classe' => $classe->getId(), 'posizione' => $posizione]);
    } elseif ($form->isSubmitted() && !$form->isValid()) {
      // mostra altri errori
      foreach ($form->getErrors() as $error) {
        $this->reqstack->getSession()->getFlashBag()->add('errore', $error->getMessage());
      }
      // redirect
      return $this->redirectToRoute('coordinatore_scrutinio', ['classe' => $classe->getId(), 'posizione' => $posizione]);
    }
    // visualizza pagina
    return $this->render('coordinatore/edcivica_'.$periodo.'.html.twig', array(
      'classe' => $classe,
      'dati' => $dati,
      'form' => $form->createView(),
    ));
  }

  /**
   * Aggiorna alcuni dati dello scrutinio.
   *
   * @param Request $request Pagina richiesta
   * @param ScrutinioUtil $scr Funzioni di utilità per lo scrutinio
   * @param int $scrutinio Identificativo dello scrutinio
   *
   * @return JsonResponse Informazioni di risposta
   *
   * @Route("/coordinatore/scrutinio/aggiorna/{scrutinio}", name="coordinatore_scrutinio_aggiorna",
   *    requirements={"scrutinio": "\d+"},
   *    methods={"POST"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function scrutinioAggiornaAction(Request $request, ScrutinioUtil $scr, $scrutinio) {
    $risposta = ['status' => 'ok'];
    // controllo scrutinio
    $scrutinio = $this->em->getRepository('App\Entity\Scrutinio')->find($scrutinio);
    if (!$scrutinio) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    $scrutinioAttivo = $scr->scrutinioAttivo($scrutinio->getClasse());
    if (!$scrutinioAttivo || $scrutinio->getPeriodo() != $scrutinioAttivo['periodo']) {
      // errore
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // controllo accesso alla funzione
    if (!($this->getUser() instanceOf Staff) && !($this->getUser() instanceOf Preside)) {
      // coordinatore
      $classi = explode(',', $this->reqstack->getSession()->get('/APP/DOCENTE/coordinatore', []));
      if (!in_array($scrutinio->getClasse()->getId(), $classi)) {
        // errore
        throw $this->createNotFoundException('exception.invalid_params');
      }
    }
    // modifica dati
    foreach ($request->request->all() as $key => $value) {
      // modifica solo i campi previsti
      switch ($key) {
        case 'numeroVerbale':
          if ($value > 0) {
            $datiScrutinio = $scrutinio->getDati();
            $datiScrutinio['numeroVerbale'] = (int) $value;
            $scrutinio->setDati($datiScrutinio);
          }
          break;
        case 'fine':
          $ora = new \DateTime($value);
          $scrutinio->setFine($ora);
          break;
      }
    }
    $this->em->flush();
    // restituisce dati
    return new JsonResponse($risposta);
  }

}
