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


namespace AppBundle\Util;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\Form;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;
use AppBundle\Entity\Alunno;
use AppBundle\Entity\Classe;
use AppBundle\Entity\Materia;
use AppBundle\Entity\Docente;
use AppBundle\Entity\PropostaVoto;
use AppBundle\Entity\Scrutinio;
use AppBundle\Entity\VotoScrutinio;
use AppBundle\Entity\Staff;
use AppBundle\Entity\Preside;
use AppBundle\Entity\Esito;
use AppBundle\Util\LogHandler;
use AppBundle\Form\ScrutinioPresenza;
use AppBundle\Form\ScrutinioPresenzaType;
use AppBundle\Form\ScrutinioAssenza;
use AppBundle\Form\ScrutinioAssenzaType;


/**
 * ScrutinioUtil - classe di utilità per le funzioni per la gestione dello scrutinio
 */
class ScrutinioUtil {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var RouterInterface $router Gestore delle URL
   */
  private $router;

  /**
   * @var EntityManagerInterface $em Gestore delle entità
   */
  private $em;

  /**
   * @var TranslatorInterface $trans Gestore delle traduzioni
   */
  private $trans;

  /**
   * @var SessionInterface $session Gestore delle sessioni
   */
  private $session;

  /**
   * @var LogHandler $dblogger Gestore dei log su database
   */
  private $dblogger;

  /**
   * @var string $root Directory principale dell'applicazione
   */
  private $root;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Construttore
   *
   * @param RouterInterface $router Gestore delle URL
   * @param EntityManagerInterface $em Gestore delle entità
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param SessionInterface $session Gestore delle sessioni
   * @param LogHandler $dblogger Gestore dei log su database
   * @param string $root Directory principale dell'applicazione
   */
  public function __construct(RouterInterface $router, EntityManagerInterface $em, TranslatorInterface $trans,
                               SessionInterface $session, LogHandler $dblogger, $root) {
    $this->router = $router;
    $this->em = $em;
    $this->trans = $trans;
    $this->session = $session;
    $this->dblogger = $dblogger;
    $this->root = $root;
  }

  /**
   * Restituisce la lista dei periodi inseriti per lo scrutinio
   *
   * @param Classe $classe Classe di cui leggere i periodi attivi dello scrutinio
   *
   * @return array Dati formattati come un array associativo
   */
  public function periodi(Classe $classe) {
    // legge periodi per classe
    $periodi = $this->em->getRepository('AppBundle:Scrutinio')->createQueryBuilder('s')
      ->select('s.periodo,s.stato')
      ->where('s.classe=:classe')
      ->setParameters(['classe' => $classe])
      ->getQuery()
      ->getArrayResult();
    $lista = array();
    foreach ($periodi as $p) {
      $lista[$p['periodo']] = $p['stato'];
    }
    // restituisce valori
    return $lista;
  }

  /**
   * Restituisce la lista delle proposte di voto dello scrutinio
   *
   * @param Docente $docente Docente che inserisce le proposte di voto
   * @param Classe $classe Classe relativa alle proposte di voto
   * @param Materia $materia Materia relativa alle proposte di voto
   * @param string $periodo Periodo relativo allo scrutinio
   *
   * @return array Dati formattati come un array associativo
   */
  public function elencoProposte(Docente $docente, Classe $classe, Materia $materia, $periodo) {
    $elenco = array();
    // alunni della classe
    if ($materia->getTipo() == 'R') {
      // religione: solo alunni che si avvalgono
      $lista_alunni = $this->em->getRepository('AppBundle:Alunno')->createQueryBuilder('a')
        ->select('a.id')
        ->where('a.classe=:classe AND a.abilitato=:abilitato AND a.religione=:religione')
        ->setParameters(['classe' => $classe, 'abilitato' => 1, 'religione' => 'S'])
        ->getQuery()
        ->getScalarResult();
    } else {
      // non è religione: tutti gli alunni
      $lista_alunni = $this->em->getRepository('AppBundle:Alunno')->createQueryBuilder('a')
        ->select('a.id')
        ->where('a.classe=:classe AND a.abilitato=:abilitato')
        ->setParameters(['classe' => $classe, 'abilitato' => 1])
        ->getQuery()
        ->getScalarResult();
    }
    // legge i dati degli degli alunni
    $alunni = $this->em->getRepository('AppBundle:Alunno')->createQueryBuilder('a')
      ->where('a.id IN (:alunni)')
      ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
      ->setParameters(['alunni' => $lista_alunni])
      ->getQuery()
      ->getResult();
    foreach ($alunni as $alu) {
      $elenco['alunni'][$alu->getId()] = [$alu->getCognome(), $alu->getNome(), $alu->getDataNascita(), $alu->getBes(), $alu->getNote()];
      $elenco['proposte'][$alu->getId()] = null;
    }
    // legge le proposte di voto
    $proposte = $this->em->getRepository('AppBundle:PropostaVoto')->createQueryBuilder('pv')
      ->where('pv.alunno IN (:alunni) AND pv.classe=:classe AND pv.materia=:materia AND pv.periodo=:periodo')
      ->setParameters(['alunni' => $lista_alunni, 'classe' => $classe, 'materia' => $materia, 'periodo' => $periodo])
      ->getQuery()
      ->getResult();
    foreach ($proposte as $p) {
      // inserisce proposte trovate
      $elenco['proposte'][$p->getAlunno()->getId()] = $p;
    }
    foreach ($alunni as $alu) {
      // aggiunge proposte vuote
      if (!$elenco['proposte'][$alu->getId()]) {
        $elenco['proposte'][$alu->getId()] = (new PropostaVoto)
          ->setAlunno($alu)
          ->setClasse($classe)
          ->setMateria($materia)
          ->setDocente($docente)
          ->setPeriodo($periodo);
        $this->em->persist($elenco['proposte'][$alu->getId()]);
      }
    }
    // debito formativo esistente
    if ($periodo == '1') {
      $voti = $this->em->getRepository('AppBundle:VotoScrutinio')->createQueryBuilder('vs')
        ->join('vs.scrutinio', 's')
        ->where('vs.unico<:suff AND vs.alunno IN (:alunni) AND vs.materia=:materia AND s.classe=:classe AND s.periodo=:periodo AND s.stato=:stato')
        ->setParameters(['suff' => 6, 'alunni' => $lista_alunni, 'classe' => $classe, 'materia' => $materia,
          'periodo' => 'P', 'stato' => 'C'])
        ->getQuery()
        ->getResult();
      foreach ($voti as $v) {
        // inserisce proposte trovate
        $elenco['debiti'][$v->getAlunno()->getId()] = $v;
      }
    }
    // restituisce elenco
    return $elenco;
  }

  /**
   * Restituisce il periodo e lo stato per lo scrutinio attivo della classe
   *
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   *
   * @return array Dati formattati come un array associativo
   */
  public function scrutinioAttivo(Classe $classe) {
    // legge periodi per classe (può essercene solo uno attivo per classe)
    $scrutinio = $this->em->getRepository('AppBundle:Scrutinio')->createQueryBuilder('s')
      ->select('s.periodo,s.stato')
      ->where('s.classe=:classe AND s.stato!=:stato')
      ->setParameters(['classe' => $classe, 'stato' => 'C'])
      ->getQuery()
      ->getOneOrNullResult();
    // restituisce valori
    return $scrutinio;
  }

  /**
   * Restituisce lo scrutinio chiuso più recente della classe
   *
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   *
   * @return array Dati formattati come un array associativo
   */
  public function scrutinioChiuso(Classe $classe) {
    // legge periodi per classe
    $scrutinio = $this->em->getRepository('AppBundle:Scrutinio')->createQueryBuilder('s')
      ->select('s.periodo,s.stato')
      ->where('s.classe=:classe AND s.stato=:stato')
      ->setParameters(['classe' => $classe, 'stato' => 'C'])
      ->orderBy('s.data', 'DESC')
      ->setMaxResults(1)
      ->getQuery()
      ->getOneOrNullResult();
    // restituisce valori
    return $scrutinio;
  }

  /**
   * Restituisce i dati necessari per lo scrutinio
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param string $periodo Periodo dello scrutinio
   * @param string $stato Stato dello scrutinio
   *
   * @return array Dati formattati come un array associativo
   */
  public function datiScrutinio(Docente $docente, Classe $classe, $periodo, $stato) {
    $dati = array();
    if ($periodo == 'P') {
      // primo trimestre
      switch ($stato) {
        case 'N':
          // proposte di voto
          $dati = $this->quadroProposte($docente, $classe, $periodo);
          break;
        case '1':
          // presenze docenti
          $dati = $this->presenzeDocenti($docente, $classe, $periodo);
          break;
        case '2':
          // condotta
          $dati = $this->quadroVoti($docente, $classe, $periodo);
          break;
        case '3':
          // esito
          $dati = $this->quadroVoti($docente, $classe, $periodo);
          break;
        case '4':
          // riepilogo
          $dati = $this->riepilogo($docente, $classe, $periodo);
          break;
        case 'C':
          // chiusura
          $dati = $this->chiusura($docente, $classe, $periodo);
          break;
      }
    } elseif ($periodo == '1') {
      // valutazione intermedia
      switch ($stato) {
        case 'N':
          // proposte di voto
          $dati = $this->quadroProposte($docente, $classe, $periodo);
          break;
        case '1':
          // condotta
          $dati = $this->quadroVoti($docente, $classe, $periodo);
          break;
        case '2':
          // esito
          $dati = $this->quadroVoti($docente, $classe, $periodo);
          break;
        case '3':
          // riepilogo
          $dati = $this->riepilogo($docente, $classe, $periodo);
          break;
        case 'C':
          // chiusura
          $dati = $this->chiusura($docente, $classe, $periodo);
          break;
      }
    } elseif ($periodo == 'F') {
      // scrutinio finale
      switch ($stato) {
        case 'N':
          // proposte di voto
          $dati = $this->quadroProposte($docente, $classe, $periodo);
          break;
        case '1':
          // presenze docenti
          $dati = $this->presenzeDocenti($docente, $classe, $periodo);
          break;
        case '2':
          // controllo assenze
          $dati = $this->controlloAssenze($docente, $classe, $periodo);
          break;
        case '3':
          // condotta
          $dati = $this->quadroVoti($docente, $classe, $periodo);
          break;
        case '4':
          // esito
          $dati = $this->quadroEsiti($docente, $classe, $periodo);
          break;
        case '5':
          // credito o competenze
          if ($classe->getAnno() == 2) {
            // competenze
            $dati = $this->quadroCompetenze($docente, $classe, $periodo);
          } elseif ($classe->getAnno() != 1) {
            // crediti
            $dati = $this->quadroCrediti($docente, $classe, $periodo);
          }
          break;
        case '6':
          // debiti e carenze
          if ($classe->getAnno() != 5) {
            // escluse le quinte
            $dati = $this->quadroComunicazioni($docente, $classe, $periodo);
          }
          break;
        case '7':
          // riepilogo
          $dati = $this->riepilogo($docente, $classe, $periodo);
          break;
        case 'C':
          // chiusura
          $dati = $this->chiusura($docente, $classe, $periodo);
          break;
      }
    } elseif ($periodo == 'R') {
      // ripresa scrutinio finale
      switch ($stato) {
        case 'N':
          // riepilogo
          $dati = $this->riepilogoSospesi($docente, $classe, $periodo);
          break;
        case '1':
          // presenze docenti
          $dati = $this->presenzeDocenti($docente, $classe, $periodo);
          break;
        case '2':
          // esito
          $dati = $this->quadroEsiti($docente, $classe, $periodo);
          break;
        case '3':
          // credito o competenze
          if ($classe->getAnno() == 2) {
            // competenze
            $dati = $this->quadroCompetenze($docente, $classe, $periodo);
          } elseif ($classe->getAnno() != 1) {
            // crediti
            $dati = $this->quadroCrediti($docente, $classe, $periodo);
          }
          break;
        case '4':
          // riepilogo
          $dati = $this->riepilogo($docente, $classe, $periodo);
          break;
        case 'C':
          // chiusura
          $dati = $this->chiusura($docente, $classe, $periodo);
          break;
      }
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Gestisce un form usato nello scrutinio
   *
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param string $periodo Periodo dello scrutinio
   * @param string $stato Stato dello scrutinio
   * @param FormBuilder $form Form per lo scrutinio
   * @param array $dati Dati dello scrutinio
   *
   * @return FormType|null Form usato nella pagina corrente dello scrutinio
   */
  public function formScrutinio(Classe $classe, $periodo, $stato, FormBuilder $form, $dati) {
    if ($periodo == 'P') {
      // primo trimestre
      switch ($stato) {
        case 'N':
          // inizio
          $form->setAction($this->router->generate('coordinatore_scrutinio',
              ['classe' => $classe->getId(), 'stato' => '1']));
          break;
        case '1':
          // presenze docenti
          $form = $this->presenzeDocentiForm($classe, $periodo, $form, $dati);
          break;
        case '2':
          // condotta
          $form->setAction($this->router->generate('coordinatore_scrutinio',
              ['classe' => $classe->getId(), 'stato' => '3']));
          break;
        case '3':
          // esito
          $form->setAction($this->router->generate('coordinatore_scrutinio',
              ['classe' => $classe->getId(), 'stato' => '4']));
          break;
        case '4':
          // riepilogo
          $form = $this->riepilogoForm($classe, $periodo, $form, $dati);
          break;
      }
    } elseif ($periodo == '1') {
      // valutazione intermedia
      switch ($stato) {
        case 'N':
          // inizio
          $form->setAction($this->router->generate('coordinatore_scrutinio',
              ['classe' => $classe->getId(), 'stato' => '1']));
          break;
        case '1':
          // condotta
          $form->setAction($this->router->generate('coordinatore_scrutinio',
              ['classe' => $classe->getId(), 'stato' => '2']));
          break;
        case '2':
          // esito
          $form->setAction($this->router->generate('coordinatore_scrutinio',
              ['classe' => $classe->getId(), 'stato' => '3']));
          break;
        case '3':
          // riepilogo
          $form = $this->riepilogoForm($classe, $periodo, $form, $dati);
          break;
      }
    } elseif ($periodo == 'F') {
      // scrutinio finale
      switch ($stato) {
        case 'N':
          // inizio
          $form->setAction($this->router->generate('coordinatore_scrutinio',
              ['classe' => $classe->getId(), 'stato' => '1']));
          break;
        case '1':
          // presenze docenti
          $form = $this->presenzeDocentiForm($classe, $periodo, $form, $dati);
          break;
        case '2':
          // controllo assenze
          $form = $this->controlloAssenzeForm($classe, $periodo, $form, $dati);
          break;
        case '3':
          // condotta
          $form->setAction($this->router->generate('coordinatore_scrutinio',
              ['classe' => $classe->getId(), 'stato' => '4']));
          break;
        case '4':
          // esito
          $form->setAction($this->router->generate('coordinatore_scrutinio',
              ['classe' => $classe->getId(), 'stato' => '5']));
          break;
        case '5':
          // credito o competenze
          $form->setAction($this->router->generate('coordinatore_scrutinio',
              ['classe' => $classe->getId(), 'stato' => '6']));
          break;
        case '6':
          // debiti e carenze
          $form->setAction($this->router->generate('coordinatore_scrutinio',
              ['classe' => $classe->getId(), 'stato' => '7']));
          break;
        case '7':
          // riepilogo
          $form = $this->riepilogoForm($classe, $periodo, $form, $dati);
          break;
      }
    } elseif ($periodo == 'R') {
      // ripresa scrutinio finale
      switch ($stato) {
        case 'N':
          // inizio
          $form->setAction($this->router->generate('coordinatore_scrutinio',
              ['classe' => $classe->getId(), 'stato' => '1']));
          break;
        case '1':
          // presenze docenti
          $form = $this->presenzeDocentiForm($classe, $periodo, $form, $dati);
          break;
        case '2':
          // esito
          $form->setAction($this->router->generate('coordinatore_scrutinio',
              ['classe' => $classe->getId(), 'stato' => '3']));
          break;
        case '3':
          // credito o competenze
          $form->setAction($this->router->generate('coordinatore_scrutinio',
              ['classe' => $classe->getId(), 'stato' => '4']));
          break;
        case '4':
          // riepilogo
          $form = $this->riepilogoForm($classe, $periodo, $form, $dati);
          break;
      }
    }
    // restituisce il form
    return $form->getForm();
  }

  /**
   * Esegue il passaggio di stato indicato per lo scrutinio
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param string $periodo Periodo dello scrutinio
   * @param string $stato Nuovo stato dello scrutinio
   *
   * @return string Stato attuale dello scrutinio
   */
  public function passaggioStato(Docente $docente, Request $request, Form $form, Classe $classe, $periodo, $stato) {
    // legge scrutinio
    $scrutinio = $this->em->getRepository('AppBundle:Scrutinio')->createQueryBuilder('s')
      ->where('s.periodo=:periodo AND s.classe=:classe')
      ->setParameters(['periodo' => $periodo, 'classe' => $classe])
      ->setMaxResults(1)
      ->getQuery()
      ->getOneOrNullResult();
    if (!$scrutinio) {
      // errore
      return null;
    }
    // esegue funzione di passaggio stato (se esiste)
    $func = 'passaggioStato_'.$periodo.'_'.$scrutinio->getStato().'_'.$stato;
    if (method_exists($this, $func) && $this->$func($docente, $request, $form, $classe, $scrutinio)) {
      // ok
      return $stato;
    }
    // restituisce stato attuale (nessuna modifica)
    return $scrutinio->getStato();
  }

  /**
   * Restituisce le proposte di voto dello scrutinio
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Classe $classe Classe relativa alle proposte di voto
   * @param string $periodo Periodo relativo allo scrutinio
   *
   * @return array Dati formattati come un array associativo
   */
  public function quadroProposte(Docente $docente, Classe $classe, $periodo) {
    $dati = array();
    // legge alunni
    $alunni = $this->em->getRepository('AppBundle:Alunno')->createQueryBuilder('a')
      ->select('a.id,a.nome,a.cognome,a.dataNascita,a.religione,a.bes,a.note')
      ->where('a.classe=:classe AND a.abilitato=:abilitato')
      ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
      ->setParameters(['classe' => $classe, 'abilitato' => 1])
      ->getQuery()
      ->getResult();
    foreach ($alunni as $alu) {
      $dati['alunni'][$alu['id']] = $alu;
    }
    // legge materie
    $materie = $this->em->getRepository('AppBundle:Materia')->createQueryBuilder('m')
      ->select('DISTINCT m.id,m.nome,m.nomeBreve,m.tipo')
      ->join('AppBundle:Cattedra', 'c', 'WHERE', 'c.materia=m.id')
      ->where('c.classe=:classe AND c.attiva=:attiva AND c.tipo=:tipo')
      ->orderBy('m.ordinamento', 'ASC')
      ->setParameters(['classe' => $classe, 'attiva' => 1, 'tipo' => 'N'])
      ->getQuery()
      ->getArrayResult();
    foreach ($materie as $mat) {
      $dati['materie'][$mat['id']] = $mat;
    }
    // legge le proposte di voto
    $proposte = $this->em->getRepository('AppBundle:PropostaVoto')->createQueryBuilder('pv')
      ->where('pv.classe=:classe AND pv.periodo=:periodo AND pv.unico IS NOT NULL')
      ->setParameters(['classe' => $classe, 'periodo' => $periodo])
      ->getQuery()
      ->getResult();
    foreach ($proposte as $p) {
      // inserisce proposte trovate
      $dati['proposte'][$p->getAlunno()->getId()][$p->getMateria()->getId()] = array(
        'id' => $p->getId(),
        'unico' => $p->getUnico(),
        'debito' => $p->getDebito(),
        'recupero' => $p->getRecupero(),
        'docente' => $p->getDocente()->getId());
    }
    // debito formativo esistente
    if ($periodo == '1') {
      $voti = $this->em->getRepository('AppBundle:VotoScrutinio')->createQueryBuilder('vs')
        ->join('vs.scrutinio', 's')
        ->where('vs.unico<:suff AND s.classe=:classe AND s.periodo=:periodo AND s.stato=:stato')
        ->setParameters(['suff' => 6, 'classe' => $classe, 'periodo' => 'P', 'stato' => 'C'])
        ->getQuery()
        ->getResult();
      foreach ($voti as $v) {
        // inserisce proposte trovate
        $dati['debiti'][$v->getAlunno()->getId()][$v->getMateria()->getId()] = $v;
      }
    }
    // controlli
    foreach ($dati['alunni'] as $a=>$alu) {
      foreach ($dati['materie'] as $m=>$mat) {
        if ($mat['tipo'] == 'R') {
          // religione
          if ($alu['religione'] == 'S' && !isset($dati['proposte'][$a][$m])) {
            // mancano valutazioni
            $dati['errori'][$m] = 1;
          }
        } else {
          // altre materie
          $no_recupero = ($periodo == 'F' && $classe->getAnno() == 5);
          if (!isset($dati['proposte'][$a][$m])) {
            // mancano valutazioni
            $dati['errori'][$m] = 1;
          } elseif ((!isset($dati['errori'][$m]) || $dati['errori'][$m] == 3) && !$no_recupero &&
                     $dati['proposte'][$a][$m]['unico'] < 6 && $dati['proposte'][$a][$m]['recupero'] === null) {
            // mancano recuperi
            $dati['errori'][$m] = 2;
          } elseif (!isset($dati['errori'][$m]) && !$no_recupero && $dati['proposte'][$a][$m]['unico'] < 6 &&
                     $dati['proposte'][$a][$m]['debito'] === null) {
            // mancano debiti
            $dati['errori'][$m] = 3;
          } elseif (!isset($dati['errori'][$m]) && $dati['proposte'][$a][$m]['unico'] >= 30 &&
                    $dati['proposte'][$a][$m]['recupero'] === null && isset($dati['debiti'][$a][$m])) {
            // manca indicazione sul recupero
            $dati['errori'][$m] = 4;
          }
        }
      }
    }
    // imposta avvisi
    foreach ($dati['materie'] as $m=>$mat) {
      if (isset($dati['errori'][$m])) {
        switch ($dati['errori'][$m]) {
          case 1:
            // mancano valutazioni
            $this->session->getFlashBag()->add('errore', $this->trans->trans('exception.no_voto_scrutinio',
              ['%materia%' => $mat['nomeBreve']]));
            break;
          case 2:
            // mancano recuperi
            $this->session->getFlashBag()->add('avviso', $this->trans->trans('exception.no_recupero_scrutinio',
              ['%materia%' => $mat['nomeBreve']]));
            break;
          case 3:
            // mancano debiti
            $this->session->getFlashBag()->add('avviso', $this->trans->trans('exception.no_debito_scrutinio',
              ['%materia%' => $mat['nomeBreve']]));
            break;
          case 4:
            // mancano indicazione recupero debito
            $this->session->getFlashBag()->add('avviso', $this->trans->trans('exception.no_recupero_debito_scrutinio',
              ['%materia%' => $mat['nomeBreve']]));
            break;
        }
      }
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Esegue il passaggio di stato N->1 per lo scrutinio del periodo P
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_P_N_1(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // controlli sulle proposte
    $this->session->getFlashBag()->clear();
    $dati = $this->quadroProposte($docente, $classe, 'P');
    if (isset($dati['errori']) && in_array(1, array_values($dati['errori']))) {
      // mancano valutazioni
      return false;
    }
    $this->session->getFlashBag()->clear();
    // conteggio assenze e inserimento voti
    $dati_scrutinio = ['motivazione' => null, 'unanimita' => true, 'contrari' => null];
    foreach ($dati['alunni'] as $alunno=>$alu) {
      foreach ($dati['materie'] as $materia=>$mat) {
        // esclude alunni NA per religione
        if ($mat['tipo'] == 'N' || $alu['religione'] == 'S') {
          // calcola assenze di alunno
          $ore = $this->em->getRepository('AppBundle:AssenzaLezione')->createQueryBuilder('al')
            ->select('SUM(al.ore)')
            ->join('al.lezione', 'l')
            ->where('al.alunno=:alunno AND l.classe=:classe AND l.materia=:materia AND l.data BETWEEN :inizio AND :fine')
            ->setParameters(['alunno' => $alunno, 'classe' => $classe->getId(), 'materia' => $materia,
              'inizio' => $this->session->get('/CONFIG/SCUOLA/anno_inizio'),
              'fine' => $this->session->get('/CONFIG/SCUOLA/periodo1_fine')])
            ->getQuery()
            ->getSingleScalarResult();
          $ore = ($ore ? intval($ore) : 0);
          // inserisce voti e assenze
          $this->em->getConnection()
            ->prepare('INSERT INTO gs_voto_scrutinio '.
              '(scrutinio_id, alunno_id, materia_id, modificato, unico, debito, recupero, assenze, dati) '.
              'VALUES (:scrutinio,:alunno,:materia,NOW(),:unico,:debito,:recupero,:ore,:dati)')
            ->execute(['scrutinio' => $scrutinio->getId(), 'alunno' => $alunno, 'materia' => $materia,
              'unico' => $dati['proposte'][$alunno][$materia]['unico'],
              'debito' => $dati['proposte'][$alunno][$materia]['debito'],
              'recupero' => $dati['proposte'][$alunno][$materia]['recupero'], 'ore' => $ore,
              'dati' => serialize($dati_scrutinio)]);
        }
      }
    }
   // aggiorna stato
    $scrutinio->setStato('1');
    $this->em->flush();
    // log
    $this->dblogger->write($docente, $request->getClientIp(), 'SCRUTINIO', 'Cambio stato', __METHOD__, array(
      'Scrutinio' => $scrutinio->getId(),
      'Classe' => $classe->getId(),
      'Periodo' => 'P',
      'Stato iniziale' => 'N',
      'Stato finale' => '1',
      ));
    // ok
    return true;
  }

  /**
   * Esegue il passaggio di stato 1->N per lo scrutinio del periodo P
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_P_1_N(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // controlla se docente fa parte di staff
    if (!($docente instanceOf Staff)) {
      // errore
      return false;
    }
    // inizializza messaggi di errore
    $this->session->getFlashBag()->clear();
    // cancella assenze e voti
    $this->em->getConnection()
      ->prepare("DELETE FROM gs_voto_scrutinio WHERE scrutinio_id=:scrutinio AND materia_id NOT IN (SELECT id FROM gs_materia WHERE tipo='C')")
      ->execute(['scrutinio' => $scrutinio->getId()]);
   // aggiorna stato
    $scrutinio->setStato('N');
    $this->em->flush();
    // log
    $this->dblogger->write($docente, $request->getClientIp(), 'SCRUTINIO', 'Cambio stato', __METHOD__, array(
      'Scrutinio' => $scrutinio->getId(),
      'Classe' => $classe->getId(),
      'Periodo' => 'P',
      'Stato iniziale' => '1',
      'Stato finale' => 'N',
      ));
    // ok
    return true;
  }

  /**
   * Gestione dell'inizio dello scrutinio e delle presenze dei docenti
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Classe $classe Classe relativa alle proposte di voto
   * @param string $periodo Periodo relativo allo scrutinio
   *
   * @return array Dati formattati come un array associativo
   */
  public function presenzeDocenti(Docente $docente, Classe $classe, $periodo) {
    $dati = array();
    // legge docenti del CdC (esclusi potenziamento)
    $docenti = $this->em->getRepository('AppBundle:Cattedra')->createQueryBuilder('c')
      ->select('DISTINCT d.id,d.cognome,d.nome,m.nomeBreve,c.tipo')
      ->join('c.materia', 'm')
      ->join('c.docente', 'd')
      ->where('c.classe=:classe AND c.attiva=:attiva AND c.tipo!=:tipo')
      ->orderBy('d.cognome,d.nome,m.ordinamento,m.nomeBreve', 'ASC')
      ->setParameters(['classe' => $classe, 'attiva' => 1, 'tipo' => 'P'])
      ->getQuery()
      ->getArrayResult();
    foreach ($docenti as $doc) {
      // dati per la visualizzazione della pagina
      $dati['docenti'][$doc['id']][] = $doc;
      $dati['form']['docenti'][$doc['cognome'].' '.$doc['nome'].' (o suo sostituto)'] = $doc['id'];
      // impostazione iniziale dei dati del form
      $dati['scrutinio']['presenze'][$doc['id']] = (new ScrutinioPresenza())
        ->setDocente($doc['id'])
        ->setPresenza(true);
    }
    // legge dati scrutinio
    $scrutinio = $this->em->getRepository('AppBundle:Scrutinio')->createQueryBuilder('s')
      ->where('s.periodo=:periodo AND s.classe=:classe')
      ->setParameters(['periodo' => $periodo, 'classe' => $classe])
      ->setMaxResults(1)
      ->getQuery()
      ->getOneOrNullResult();
    if (!$scrutinio) {
      // errore
      return null;
    }
    // imposta data/ora
    $dati['scrutinio']['data'] = $scrutinio->getData() ? $scrutinio->getData() : new \DateTime();
    $ora = \DateTime::createFromFormat('H:i', date('H').':'.((intval(date('i')) < 25) ? '00' : '30'));
    $dati['scrutinio']['inizio'] = $scrutinio->getInizio() ? $scrutinio->getInizio() : $ora;
    // imposta altri valori
    $valori = $scrutinio->getDati();
    $dati['scrutinio']['presiede_ds'] = isset($valori['presiede_ds']) ? $valori['presiede_ds'] : true;
    $dati['scrutinio']['presiede_docente'] = isset($valori['presiede_docente']) ? $valori['presiede_docente'] : null;
    $dati['scrutinio']['segretario'] = isset($valori['segretario']) ? $valori['segretario'] : null;
    // imposta presenze
    if (isset($valori['presenze'])) {
      foreach ($valori['presenze'] as $doc=>$pres) {
        if (isset($dati['scrutinio']['presenze'][$doc]) && $pres) {
          $dati['scrutinio']['presenze'][$doc] = $pres;
        }
      }
    }
    // controlla se attivare pulsante precedente o no
    $dati['precedente'] = ($docente instanceOf Staff);
    // restituisce dati
    return $dati;
  }

  /**
   * Gestione del form per le presenze dei docenti
   *
   * @param Classe $classe Classe relativa alle proposte di voto
   * @param string $periodo Periodo relativo allo scrutinio
   * @param FormBuilder $form Form per lo scrutinio
   * @param array $dati Dati passati al form
   *
   * @return FormType|null Form usato nella pagina corrente dello scrutinio
   */
  public function presenzeDocentiForm(Classe $classe, $periodo, FormBuilder $form, $dati) {
    // crea form
    $form
      ->setAction($this->router->generate('coordinatore_scrutinio',
        ['classe' => $classe->getId(), 'stato' => '2']))
      ->add('data', DateType::class, array('label' => false,
        'data'=> $dati['scrutinio']['data'],
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-picker'],
        'format' => 'dd/MM/yyyy',
        'required' => true))
      ->add('inizio', TimeType::class, array('label' => false,
        'data'=> $dati['scrutinio']['inizio'],
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-picker'],
        'required' => true))
      ->add('lista', CollectionType::class, array('label' => false,
        'data' => $dati['scrutinio']['presenze'],
        'entry_type' => ScrutinioPresenzaType::class,
        'entry_options' => array('label' => false),
        ))
      ->add('presiede_ds', ChoiceType::class, array('label' => false,
        'data' => $dati['scrutinio']['presiede_ds'],
        'choices' => ['label.scrutinio_presiede_ds' => true, 'label.scrutinio_presiede_docente' => false],
        'expanded' => true,
        'multiple' => false,
        'label_attr' => ['class' => 'radio-inline gs-pt-0 gs-mr-5'],
        'required' => true))
      ->add('presiede_docente', ChoiceType::class, array('label' => false,
        'data' => $dati['scrutinio']['presiede_docente'],
        'choices' => $dati['form']['docenti'],
        'translation_domain' => false,
        'placeholder' => $this->trans->trans('label.scegli_docente'),
        'expanded' => false,
        'multiple' => false,
        'required' => false))
      ->add('segretario', ChoiceType::class, array('label' => false,
        'data' => $dati['scrutinio']['segretario'],
        'choices' => $dati['form']['docenti'],
        'translation_domain' => false,
        'placeholder' => $this->trans->trans('label.scegli_docente'),
        'expanded' => false,
        'multiple' => false,
        'required' => true));
    // restituisce form
    return $form;
  }

  /**
   * Esegue il passaggio di stato 1->2 per lo scrutinio del periodo P
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_P_1_2(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // inizializza messaggi di errore
    $this->session->getFlashBag()->clear();
    // legge dati form
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // controlli
      if (!$form->get('data')->getData()) {
        // data non presente
        $this->session->getFlashBag()->add('errore', 'exception.scrutinio_data');
      }
      if (!$form->get('inizio')->getData()) {
        // ora non presente
        $this->session->getFlashBag()->add('errore', 'exception.scrutinio_inizio');
      }
      if ($form->get('presiede_ds')->getData() === null) {
        // presidente ds non presente
        $this->session->getFlashBag()->add('errore', 'exception.scrutinio_presidente');
      }
      if ($form->get('presiede_ds')->getData() === false && !$form->get('presiede_docente')->getData()) {
        // presidente docente non presente
        $this->session->getFlashBag()->add('errore', 'exception.scrutinio_presidente');
      }
      if (!$form->get('segretario')->getData()) {
        // segretario non presente
        $this->session->getFlashBag()->add('errore', 'exception.scrutinio_segretario');
      }
      // controlli sui presenti
      $errore_presenza = false;
      foreach ($form->get('lista')->getData() as $doc=>$val) {
        if (!$val || (!$val->getPresenza() && !$val->getSostituto())) {
          $errore_presenza = true;
        }
      }
      if ($errore_presenza) {
        // segretario non presente
        $this->session->getFlashBag()->add('errore', 'exception.scrutinio_presenza');
      }
      // se niente errori cambia stato
      if (!$this->session->getFlashBag()->has('errore')) {
        // imposta dati
        $scrutinio->setData($form->get('data')->getData());
        $scrutinio->setInizio($form->get('inizio')->getData());
        //-- $valori = array(
          //-- 'presenze' => $form->get('lista')->getData(),
          //-- 'presiede_ds' => $form->get('presiede_ds')->getData(),
          //-- 'presiede_docente' => $form->get('presiede_docente')->getData(),
          //-- 'segretario' => $form->get('segretario')->getData());
        $scrutinio->setDati(array());
        $this->em->flush();
        $scrutinio->addDato('presenze', $form->get('lista')->getData());
        $scrutinio->addDato('presiede_ds', $form->get('presiede_ds')->getData());
        $scrutinio->addDato('presiede_docente', $form->get('presiede_docente')->getData());
        $scrutinio->addDato('segretario', $form->get('segretario')->getData());
        // aggiorna stato
        $scrutinio->setStato('2');
        $this->em->flush();
        // log
        $this->dblogger->write($docente, $request->getClientIp(), 'SCRUTINIO', 'Cambio stato', __METHOD__, array(
          'Scrutinio' => $scrutinio->getId(),
          'Classe' => $classe->getId(),
          'Periodo' => 'P',
          'Stato iniziale' => '1',
          'Stato finale' => '2',
          ));
        // ok
        return true;
      }
    } else {
      // imposta messaggi per eventuali altri errori del form
      foreach ($form->getErrors() as $error) {
        $this->session->getFlashBag()->add('errore', $error->getMessage());
      }
    }
    // errore
    return false;
  }

  /**
   * Esegue il passaggio di stato 2->1 per lo scrutinio del periodo P
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_P_2_1(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // inizializza messaggi di errore
    $this->session->getFlashBag()->clear();
   // aggiorna stato
    $scrutinio->setStato('1');
    $this->em->flush();
    // log
    $this->dblogger->write($docente, $request->getClientIp(), 'SCRUTINIO', 'Cambio stato', __METHOD__, array(
      'Scrutinio' => $scrutinio->getId(),
      'Classe' => $classe->getId(),
      'Periodo' => 'P',
      'Stato iniziale' => '2',
      'Stato finale' => '1',
      ));
    // ok
    return true;
  }

  /**
   * Restituisce la situazione dei voti dello scrutinio
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Classe $classe Classe relativa alle proposte di voto
   * @param string $periodo Periodo relativo allo scrutinio
   *
   * @return array Dati formattati come un array associativo
   */
  public function quadroVoti(Docente $docente, Classe $classe, $periodo) {
    $dati = array();
    // legge alunni
    $lista = $this->alunniInScrutinio($classe, $periodo);
    $alunni = $this->em->getRepository('AppBundle:Alunno')->createQueryBuilder('a')
      ->select('a.id,a.nome,a.cognome,a.dataNascita,a.religione,a.bes,a.note')
      ->where('a.id in (:lista)')
      ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
      ->setParameters(['lista' => $lista])
      ->getQuery()
      ->getResult();
    foreach ($alunni as $alu) {
      $dati['alunni'][$alu['id']] = $alu;
    }
    // legge materie
    $materie = $this->em->getRepository('AppBundle:Materia')->createQueryBuilder('m')
      ->select('DISTINCT m.id,m.nome,m.nomeBreve,m.tipo')
      ->join('AppBundle:Cattedra', 'c', 'WHERE', 'c.materia=m.id')
      ->where('c.classe=:classe AND c.attiva=:attiva AND c.tipo=:tipo')
      ->orderBy('m.ordinamento', 'ASC')
      ->setParameters(['classe' => $classe, 'attiva' => 1, 'tipo' => 'N'])
      ->getQuery()
      ->getArrayResult();
    foreach ($materie as $mat) {
      $dati['materie'][$mat['id']] = $mat;
    }
    $condotta = $this->em->getRepository('AppBundle:Materia')->findOneByTipo('C');
    $dati['materie'][$condotta->getId()] = array(
      'id' => $condotta->getId(),
      'nome' => $condotta->getNome(),
      'nomeBreve' => $condotta->getNomeBreve(),
      'tipo' => $condotta->getTipo());
    // legge i voti
    $voti = $this->em->getRepository('AppBundle:VotoScrutinio')->createQueryBuilder('vs')
      ->join('vs.scrutinio', 's')
      ->where('s.classe=:classe AND s.periodo=:periodo AND vs.unico IS NOT NULL')
      ->setParameters(['classe' => $classe, 'periodo' => $periodo])
      ->getQuery()
      ->getResult();
    $somma = array();
    foreach ($voti as $v) {
      // inserisce voti
      $dati['voti'][$v->getAlunno()->getId()][$v->getMateria()->getId()] = array(
        'id' => $v->getId(),
        'unico' => $v->getUnico(),
        'recupero' => $v->getRecupero(),
        'debito' => $v->getDebito());
      if ($v->getMateria()->getMedia()) {
        // esclude religione dalla media
        if (!isset($somma[$v->getAlunno()->getId()])) {
          $somma[$v->getAlunno()->getId()] =
            ($v->getMateria()->getTipo() == 'C' && $v->getUnico() == 4) ? 0 : $v->getUnico();
          $numero[$v->getAlunno()->getId()] = 1;
        } else {
          $somma[$v->getAlunno()->getId()] +=
            ($v->getMateria()->getTipo() == 'C' && $v->getUnico() == 4) ? 0 : $v->getUnico();
          $numero[$v->getAlunno()->getId()]++;
        }
      }
    }
    // calcola medie
    foreach ($somma as $alu=>$s) {
      $dati['medie'][$alu] = number_format($somma[$alu] / $numero[$alu], 2, ',', null);
    }
    // debito formativo esistente
    if ($periodo == '1') {
      $voti = $this->em->getRepository('AppBundle:VotoScrutinio')->createQueryBuilder('vs')
        ->join('vs.scrutinio', 's')
        ->where('vs.unico<:suff AND s.classe=:classe AND s.periodo=:periodo AND s.stato=:stato')
        ->setParameters(['suff' => 6, 'classe' => $classe, 'periodo' => 'P', 'stato' => 'C'])
        ->getQuery()
        ->getResult();
      foreach ($voti as $v) {
        // inserisce proposte trovate
        $dati['debiti'][$v->getAlunno()->getId()][$v->getMateria()->getId()] = $v;
      }
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Restituisce la lista dei voti dello scrutinio per la materia indicata
   *
   * @param Docente $docente Docente che inserisce le proposte di voto
   * @param Classe $classe Classe relativa alle proposte di voto
   * @param Materia $materia Materia relativa alle proposte di voto
   * @param string $periodo Periodo relativo allo scrutinio
   *
   * @return array Dati formattati come un array associativo
   */
  public function elencoVoti(Docente $docente, Classe $classe, Materia $materia, $periodo) {
    $elenco = array();
    $elenco['voti'] = array();
    // legge scrutinio
    $scrutinio = $this->em->getRepository('AppBundle:Scrutinio')->createQueryBuilder('s')
      ->where('s.classe=:classe AND s.periodo=:periodo')
      ->setParameters(['classe' => $classe, 'periodo' => $periodo])
      ->setMaxResults(1)
      ->getQuery()
      ->getOneOrNullResult();
    // alunni della classe
    $lista_id = $this->alunniInScrutinio($classe, $periodo);
    $alunni = $this->em->getRepository('AppBundle:Alunno')->createQueryBuilder('a')
      ->where('a.id IN (:lista)')
      ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
      ->setParameters(['lista' => $lista_id])
      ->getQuery()
      ->getResult();
    foreach ($alunni as $alu) {
      // salta chi non si avvale in religione
      if ($materia->getTipo() != 'R' || $alu->getReligione() == 'S') {
        $elenco['alunni'][$alu->getId()] = [$alu->getCognome(), $alu->getNome(), $alu->getDataNascita()];
        // inserisce voto nullo (conserva ordine)
        $elenco['voti'][$alu->getId()] = null;
      }
    }
    // legge i voti
    $voti = $this->em->getRepository('AppBundle:VotoScrutinio')->createQueryBuilder('vs')
      ->where('vs.scrutinio=:scrutinio AND vs.materia=:materia')
      ->setParameters(['scrutinio' => $scrutinio, 'materia' => $materia])
      ->getQuery()
      ->getResult();
    foreach ($voti as $v) {
      // inserisce voti
      $elenco['voti'][$v->getAlunno()->getId()] = $v;
    }
    // crea voti se non esistono
    foreach ($alunni as $alu) {
      // aggiunge nuovi voti nulli
      if (!isset($elenco['voti'][$alu->getId()])) {
        $elenco['voti'][$alu->getId()] = (new VotoScrutinio)
          ->setScrutinio($scrutinio)
          ->setMateria($materia)
          ->setAlunno($alu)
          ->setAssenze(0)
          ->addDato('motivazione', null)
          ->addDato('unanimita', true)
          ->addDato('contrari', null);
        $this->em->persist($elenco['voti'][$alu->getId()]);
      }
    }
    // debito formativo esistente
    if ($periodo == '1') {
      $voti = $this->em->getRepository('AppBundle:VotoScrutinio')->createQueryBuilder('vs')
        ->join('vs.scrutinio', 's')
        ->where('vs.unico<:suff AND vs.materia=:materia AND s.classe=:classe AND s.periodo=:periodo AND s.stato=:stato')
        ->setParameters(['suff' => 6, 'classe' => $classe, 'materia' => $materia, 'periodo' => 'P', 'stato' => 'C'])
        ->getQuery()
        ->getResult();
      foreach ($voti as $v) {
        // inserisce proposte trovate
        $elenco['debiti'][$v->getAlunno()->getId()] = $v;
      }
    }
    // restituisce elenco
    return $elenco;
  }

  /**
   * Esegue il passaggio di stato 2->3 per lo scrutinio del periodo P
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_P_2_3(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // legge condotta
    $condotta = $this->em->getRepository('AppBundle:Materia')->findOneByTipo('C');
    // elenco voti/alunni
    $dati = $this->elencoVoti($docente, $classe, $condotta, 'P');
    // controlla errori
    $errore = array();
    foreach ($dati['voti'] as $alunno=>$voto) {
      if (!$voto->getUnico()) {
        // voto non presente
        $errore['exception.voto_condotta'] = true;
      } elseif (!$voto->getDato('motivazione') && $voto->getUnico() > 4) {
        // manca motivazione
        $errore['exception.motivazione_condotta'] = true;
      }
      if ($voto->getDato('unanimita') === null) {
        // manca delibera
        $errore['exception.delibera_condotta'] = true;
      } elseif ($voto->getDato('unanimita') === false && !$voto->getDato('contrari')) {
        // mancano contrari
        $errore['exception.contrari_condotta'] = true;
      }
    }
    if (empty($errore)) {
      // aggiorna stato
      $scrutinio->setStato('3');
      $this->em->flush();
      // log
      $this->dblogger->write($docente, $request->getClientIp(), 'SCRUTINIO', 'Cambio stato', __METHOD__, array(
        'Scrutinio' => $scrutinio->getId(),
        'Classe' => $classe->getId(),
        'Periodo' => 'P',
        'Stato iniziale' => '2',
        'Stato finale' => '3',
        ));
      // ok
      return true;
    }
    // imposta messaggi di errore
    foreach ($errore as $msg=>$v) {
      $this->session->getFlashBag()->add('errore', $msg);
    }
    // errori presenti
    return false;
  }

  /**
   * Esegue il passaggio di stato 3->2 per lo scrutinio del periodo P
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_P_3_2(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // aggiorna stato
    $scrutinio->setStato('2');
    $this->em->flush();
    // log
    $this->dblogger->write($docente, $request->getClientIp(), 'SCRUTINIO', 'Cambio stato', __METHOD__, array(
      'Scrutinio' => $scrutinio->getId(),
      'Classe' => $classe->getId(),
      'Periodo' => 'P',
      'Stato iniziale' => '3',
      'Stato finale' => '2',
      ));
    // ok
    return true;
  }

  /**
   * Esegue il passaggio di stato 3->4 per lo scrutinio del periodo P
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_P_3_4(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // inizializza messaggi di errore
    $this->session->getFlashBag()->clear();
    // legge voti
    $dati = $this->quadroVoti($docente, $classe, 'P');
    // controlli
    $errori = array();
    foreach ($dati['alunni'] as $a=>$alu) {
      foreach ($dati['materie'] as $m=>$mat) {
        if ($mat['tipo'] == 'R') {
          // religione
          if ($alu['religione'] == 'S' && !isset($dati['voti'][$a][$m])) {
            // mancano valutazioni
            $errori[$m] = 1;
          }
        } elseif ($mat['tipo'] == 'N') {
          // altre materie (esclusa condotta)
          if (!isset($dati['voti'][$a][$m]['unico'])) {
            // mancano valutazioni
            $errori[$m] = 1;
          } elseif ((!isset($errori[$m]) || $errori[$m] == 3) &&
                     $dati['voti'][$a][$m]['unico'] < 6 && !$dati['voti'][$a][$m]['recupero']) {
            // mancano recuperi
            $errori[$m] = 2;
          } elseif (!isset($errori[$m]) && $dati['voti'][$a][$m]['unico'] < 6 && !$dati['voti'][$a][$m]['debito']) {
            // mancano debiti
            $errori[$m] = 3;
          }
        }
      }
    }
    if (empty($errori)) {
     // aggiorna stato
      $scrutinio->setStato('4');
      $this->em->flush();
      // log
      $this->dblogger->write($docente, $request->getClientIp(), 'SCRUTINIO', 'Cambio stato', __METHOD__, array(
        'Scrutinio' => $scrutinio->getId(),
        'Classe' => $classe->getId(),
        'Periodo' => 'P',
        'Stato iniziale' => '3',
        'Stato finale' => '4',
        ));
      // ok
      return true;
    }
    // imposta avvisi
    foreach ($dati['materie'] as $m=>$mat) {
      if (isset($errori[$m])) {
        switch ($errori[$m]) {
          case 1:
            // mancano valutazioni
            $this->session->getFlashBag()->add('errore', $this->trans->trans('exception.no_voto_scrutinio',
              ['%materia%' => $mat['nomeBreve']]));
            break;
          case 2:
            // mancano recuperi
            $this->session->getFlashBag()->add('errore', $this->trans->trans('exception.no_recupero_scrutinio',
              ['%materia%' => $mat['nomeBreve']]));
            break;
          case 3:
            // mancano debiti
            $this->session->getFlashBag()->add('errore', $this->trans->trans('exception.no_debito_scrutinio',
              ['%materia%' => $mat['nomeBreve']]));
            break;
        }
      }
    }
    // errore
    return false;
  }

  /**
   * Esegue il passaggio di stato 4->3 per lo scrutinio del periodo P
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_P_4_3(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // inizializza messaggi di errore
    $this->session->getFlashBag()->clear();
   // aggiorna stato
    $scrutinio->setStato('3');
    $this->em->flush();
    // log
    $this->dblogger->write($docente, $request->getClientIp(), 'SCRUTINIO', 'Cambio stato', __METHOD__, array(
      'Scrutinio' => $scrutinio->getId(),
      'Classe' => $classe->getId(),
      'Periodo' => 'P',
      'Stato iniziale' => '4',
      'Stato finale' => '3',
      ));
    // ok
    return true;
  }

  /**
   * Restituisce la situazione del riepilogo finale dello scrutinio
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Classe $classe Classe relativa alle proposte di voto
   * @param string $periodo Periodo relativo allo scrutinio
   *
   * @return array Dati formattati come un array associativo
   */
  public function riepilogo(Docente $docente, Classe $classe, $periodo) {
    // legge voti
    $dati = $this->quadroVoti($docente, $classe, $periodo);
    // legge dati scrutinio
    $scrutinio = $this->em->getRepository('AppBundle:Scrutinio')->createQueryBuilder('s')
      ->where('s.periodo=:periodo AND s.classe=:classe')
      ->setParameters(['periodo' => $periodo, 'classe' => $classe])
      ->setMaxResults(1)
      ->getQuery()
      ->getOneOrNullResult();
    // esiti
    if ($periodo == 'F' || $periodo == 'R') {
      $lista = $this->alunniInScrutinio($classe, $periodo);
      $esiti = $this->em->getRepository('AppBundle:Esito')->createQueryBuilder('e')
        ->where('e.alunno IN (:lista) AND e.scrutinio=:scrutinio')
        ->setParameters(['lista' => $lista, 'scrutinio' => $scrutinio])
        ->getQuery()
        ->getResult();
      foreach ($esiti as $e) {
        $dati['esiti'][$e->getAlunno()->getId()] = $e;
      }
    }
    if ($periodo != '1') {
      // legge ora finale
      $ora = \DateTime::createFromFormat('H:i', date('H').':'.((intval(date('i')) < 25) ? '00' : '30'));
      $dati['scrutinio']['fine'] = $scrutinio->getFine() ? $scrutinio->getFine() : $ora;
    } else {
      // legge data
      $dati['scrutinio']['data'] = $scrutinio->getData() ? $scrutinio->getData() : new \DateTime('today');
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Gestione del form per il riepilogo finale
   *
   * @param Classe $classe Classe relativa alle proposte di voto
   * @param string $periodo Periodo relativo allo scrutinio
   * @param FormBuilder $form Form per lo scrutinio
   * @param array $dati Dati passati al form
   *
   * @return FormType|null Form usato nella pagina corrente dello scrutinio
   */
  public function riepilogoForm(Classe $classe, $periodo, FormBuilder $form, $dati) {
    // crea form
    $form
      ->setAction($this->router->generate('coordinatore_scrutinio',
        ['classe' => $classe->getId(), 'stato' => 'C']));
    if ($periodo != '1') {
      $form->add('fine', TimeType::class, array('label' => false,
        'data'=> $dati['scrutinio']['fine'],
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-picker'],
        'required' => true));
    } else {
      $form->add('data', DateType::class, array('label' => false,
        'data'=> $dati['scrutinio']['data'],
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-picker'],
        'format' => 'dd/MM/yyyy',
        'required' => true));
    }
    // restituisce form
    return $form;
  }

  /**
   * Esegue il passaggio di stato 4->C per lo scrutinio del periodo P
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_P_4_C(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // inizializza messaggi di errore
    $this->session->getFlashBag()->clear();
    // legge dati form
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // controlli
      if (!$form->get('fine')->getData()) {
        // ora non presente
        $this->session->getFlashBag()->add('errore', 'exception.scrutinio_fine');
      }
      // se niente errori cambia stato
      if (!$this->session->getFlashBag()->has('errore')) {
        // imposta dati
        $scrutinio->setFine($form->get('fine')->getData());
        // aggiorna stato
        $scrutinio->setStato('C');
        $this->em->flush();
        // log
        $this->dblogger->write($docente, $request->getClientIp(), 'SCRUTINIO', 'Cambio stato', __METHOD__, array(
          'Scrutinio' => $scrutinio->getId(),
          'Classe' => $classe->getId(),
          'Periodo' => 'P',
          'Stato iniziale' => '4',
          'Stato finale' => 'C',
          ));
        // ok
        return true;
      }
    } else {
      // imposta messaggi per eventuali altri errori del form
      foreach ($form->getErrors() as $error) {
        $this->session->getFlashBag()->add('errore', $error->getMessage());
      }
    }
    // errore
    return false;
  }

  /**
   * Esegue il passaggio di stato C->4 per lo scrutinio del periodo P
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_P_C_4(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // controlla se è possibile riapertura
    if (!($docente instanceOf Staff) || $scrutinio->getSincronizzazione()) {
      // errore
      return false;
    }
    // inizializza messaggi di errore
    $this->session->getFlashBag()->clear();
    // rinomina documenti di classe
    $fs = new Filesystem();
    $finder = new Finder();
    $percorso = $this->root.$this->session->get('/CONFIG/SISTEMA/dir_scrutini').'/trimestre/'.
      $classe->getAnno().$classe->getSezione();
    $num = 0;
    while ($fs->exists($percorso.'/BACKUP.'.$num)) {
      $num++;
    }
    $fs->mkdir($percorso.'/BACKUP.'.$num, 0775);
    $finder->files()->in($percorso)->depth('== 0');
    foreach ($finder as $file) {
      // sposta in directory
      $fs->rename($file->getRealPath(), $percorso.'/BACKUP.'.$num.'/'.$file->getBasename());
    }
    // aggiorna stato
    $scrutinio->setStato('4');
    $this->em->flush();
    // log
    $this->dblogger->write($docente, $request->getClientIp(), 'SCRUTINIO', 'Cambio stato', __METHOD__, array(
      'Scrutinio' => $scrutinio->getId(),
      'Classe' => $classe->getId(),
      'Periodo' => 'P',
      'Stato iniziale' => 'C',
      'Stato finale' => '4',
      ));
    // ok
    return true;
  }

  /**
   * Restituisce la documentazione dopo la chiusura dello scrutinio
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Classe $classe Classe relativa alle proposte di voto
   * @param string $periodo Periodo relativo allo scrutinio
   *
   * @return array Dati formattati come un array associativo
   */
  public function chiusura(Docente $docente, Classe $classe, $periodo) {
    $dati = array();
    // legge alunni
    $lista = $this->alunniInScrutinio($classe, $periodo);
    if ($periodo == 'P') {
      // dati alunni
      $alunni = $this->em->getRepository('AppBundle:Alunno')->createQueryBuilder('a')
        ->select('a.id,a.nome,a.cognome,a.dataNascita')
        ->where('a.id IN (:lista)')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
        ->setParameters(['lista' => $lista])
        ->getQuery()
        ->getResult();
      foreach ($alunni as $alu) {
        $dati['alunni'][$alu['id']] = $alu;
      }
      // legge i debiti
      $debiti = $this->em->getRepository('AppBundle:VotoScrutinio')->createQueryBuilder('vs')
        ->select('DISTINCT a.id,a.nome,a.cognome,a.dataNascita')
        ->join('vs.scrutinio', 's')
        ->join('vs.materia', 'm')
        ->join('vs.alunno', 'a')
        ->where('s.classe=:classe AND s.periodo=:periodo AND m.tipo=:tipo AND vs.unico IS NOT NULL AND vs.unico<:suff AND a.id IN (:lista)')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
        ->setParameters(['suff' => 6, 'classe' => $classe, 'periodo' => $periodo, 'tipo' => 'N', 'lista' => $lista])
        ->getQuery()
        ->getResult();
      $dati['debiti'] = array();
      foreach ($debiti as $deb) {
        $dati['debiti'][$deb['id']] = $deb;
      }
    } elseif ($periodo == 'F') {
      // legge i non ammessi (anche per frequenza)
      $non_ammessi = $this->em->getRepository('AppBundle:Alunno')->createQueryBuilder('a')
        ->select('a.id')
        ->join('AppBundle:Esito', 'e', 'WHERE', 'e.alunno=a.id')
        ->join('e.scrutinio', 's')
        ->where('a.id IN (:lista) AND e.esito=:esito AND s.classe=:classe AND s.periodo=:periodo')
        ->setParameters(['lista' => $lista, 'esito' => 'N', 'classe' => $classe, 'periodo' => $periodo])
        ->getQuery()
        ->getArrayResult();
      $non_ammessi = array_column($non_ammessi, 'id');
      $scrutinio = $this->em->getRepository('AppBundle:Scrutinio')->createQueryBuilder('s')
        ->where('s.classe=:classe AND s.periodo=:periodo AND s.stato=:stato')
        ->setParameters(['classe' => $classe, 'periodo' => $periodo, 'stato' => 'C'])
        ->getQuery()
        ->getOneOrNullResult();
      $noscrut = ($scrutinio->getDato('no_scrutinabili') ? $scrutinio->getDato('no_scrutinabili') : []);
      foreach ($noscrut as $alu) {
        if ($scrutinio->getDato('alunni')[$alu]['no_deroga'] == 'A') {
          $non_ammessi[] = $alu;
        }
      }
      $dati['non_ammessi'] = $this->em->getRepository('AppBundle:Alunno')->createQueryBuilder('a')
        ->select('a.id,a.nome,a.cognome,a.dataNascita')
        ->where('a.id IN (:lista)')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
        ->setParameters(['lista' => $non_ammessi])
        ->getQuery()
        ->getArrayResult();
      // legge i debiti
      $dati['debiti']  = $this->em->getRepository('AppBundle:Alunno')->createQueryBuilder('a')
        ->select('a.id,a.nome,a.cognome,a.dataNascita')
        ->join('AppBundle:Esito', 'e', 'WHERE', 'e.alunno=a.id')
        ->join('e.scrutinio', 's')
        ->where('a.id in (:lista) AND e.esito=:sospeso AND s.classe=:classe AND s.periodo=:periodo')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
        ->setParameters(['lista' => $lista, 'sospeso' => 'S', 'classe' => $classe, 'periodo' => $periodo])
        ->getQuery()
        ->getArrayResult();
      // legge le carenze
      $alunni = $this->em->getRepository('AppBundle:Alunno')->createQueryBuilder('a')
        ->select('a.id,a.nome,a.cognome,a.dataNascita,e.dati')
        ->join('AppBundle:Esito', 'e', 'WHERE', 'e.alunno=a.id')
        ->join('e.scrutinio', 's')
        ->where('a.id IN (:lista) AND e.esito IN (:esiti) AND s.classe=:classe AND s.periodo=:periodo')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
        ->setParameters(['lista' => $lista, 'esiti' => ['A', 'S'], 'classe' => $classe, 'periodo' => $periodo])
        ->getQuery()
        ->getArrayResult();
      $dati['carenze'] = array();
      foreach ($alunni as $a) {
        if (isset($a['dati']['carenze']) && isset($a['dati']['carenze_materie']) &&
            $a['dati']['carenze'] && count($a['dati']['carenze_materie']) > 0) {
          $dati['carenze'][] = $a;
        }
      }
    } elseif ($periodo == 'R') {
      // legge i non ammessi
      $dati['non_ammessi'] = $this->em->getRepository('AppBundle:Alunno')->createQueryBuilder('a')
        ->select('a.id,a.nome,a.cognome,a.dataNascita')
        ->join('AppBundle:Esito', 'e', 'WHERE', 'e.alunno=a.id')
        ->join('e.scrutinio', 's')
        ->where('a.id IN (:lista) AND e.esito=:esito AND s.classe=:classe AND s.periodo=:periodo')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
        ->setParameters(['lista' => $lista, 'esito' => 'N', 'classe' => $classe, 'periodo' => $periodo])
        ->getQuery()
        ->getArrayResult();
    }
    // controlla se attivare pulsante riapertura o no
    $scrutinio = $this->em->getRepository('AppBundle:Scrutinio')->createQueryBuilder('s')
      ->where('s.classe=:classe AND s.periodo=:periodo AND s.stato=:stato AND s.sincronizzazione IS NULL')
      ->setParameters(['classe' => $classe, 'periodo' => $periodo, 'stato' => 'C'])
      ->getQuery()
      ->getOneOrNullResult();
    $dati['precedente'] = ($docente instanceOf Staff) && $scrutinio;
    return $dati;
  }

  /**
   * Restituisce la lista degli alunni per lo scrutinio indicato
   *
   * @param Classe $classe Classe scolastica
   * @param string $periodo Periodo relativo allo scrutinio
   *
   * @return array Lista degli ID degli alunni
   */
  public function alunniInScrutinio(Classe $classe, $periodo) {
    if ($periodo == 'P') {
      // ultimo giorno del primo trimestre
      $data = $this->session->get('/CONFIG/SCUOLA/periodo1_fine');
    } elseif ($periodo == '1') {
      // data attuale
      $data = (new \DateTime())->format('Y-m-d');
    } elseif ($periodo == 'F') {
      // legge lista alunni scrutinabili
      $scrutinio = $this->em->getRepository('AppBundle:Scrutinio')->createQueryBuilder('s')
        ->where('s.periodo=:periodo AND s.classe=:classe')
        ->setParameters(['periodo' => $periodo, 'classe' => $classe])
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();
      $valori = $scrutinio->getDati();
      // restituisce lista di ID
      return $valori['scrutinabili'];
    } elseif ($periodo == 'R') {
      // legge lista alunni sospesi
      $scrutinio = $this->em->getRepository('AppBundle:Scrutinio')->createQueryBuilder('s')
        ->where('s.periodo=:periodo AND s.classe=:classe AND s.stato=:stato')
        ->setParameters(['periodo' => 'F', 'classe' => $classe, 'stato' => 'C'])
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();
      $scrutinati = $scrutinio->getDati()['scrutinabili'];
      $sospesi = $this->em->getRepository('AppBundle:Esito')->createQueryBuilder('e')
        ->select('(e.alunno)')
        ->where('e.alunno IN (:lista) AND e.scrutinio=:scrutinio AND e.esito=:sospeso')
        ->setParameters(['lista' => $scrutinati, 'scrutinio' => $scrutinio, 'sospeso' => 'S'])
        ->getQuery()
        ->getArrayResult();
      // restituisce lista di ID
      return array_map('current', $sospesi);
    }
    // aggiunge alunni attuali che non hanno fatto cambiamenti di classe in quella data
    $cambio = $this->em->getRepository('AppBundle:CambioClasse')->createQueryBuilder('cc')
      ->where('cc.alunno=a.id AND :data BETWEEN cc.inizio AND cc.fine')
      ->andWhere('cc.classe IS NULL OR cc.classe!=:classe');
    $alunni = $this->em->getRepository('AppBundle:Alunno')->createQueryBuilder('a')
      ->select('a.id')
      ->where('a.classe=:classe AND a.abilitato=:abilitato AND NOT EXISTS ('.$cambio->getDQL().')')
      ->setParameters(['data' => $data, 'classe' => $classe, 'abilitato' => 1])
      ->getQuery()
      ->getScalarResult();
    // aggiunge altri alunni con cambiamento nella classe in quella data
    $alunni2 = $this->em->getRepository('AppBundle:Alunno')->createQueryBuilder('a')
      ->select('a.id')
      ->join('AppBundle:CambioClasse', 'cc', 'WHERE', 'a.id=cc.alunno')
      ->where(':data BETWEEN cc.inizio AND cc.fine AND cc.classe=:classe AND a.abilitato=:abilitato')
      ->setParameters(['data' => $data, 'classe' => $classe, 'abilitato' => 1])
      ->getQuery()
      ->getScalarResult();
    $alunni = array_merge($alunni, $alunni2);
    // restituisce lista di ID
    $alunni_id = array_map('current', $alunni);
    return $alunni_id;
  }

  /**
   * Esegue il passaggio di stato N->1 per lo scrutinio del periodo 1
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_1_N_1(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    $dati = $this->quadroProposte($docente, $classe, '1');
    $this->session->getFlashBag()->clear();
    // conteggio assenze e inserimento voti
    $dati_scrutinio = ['motivazione' => null, 'unanimita' => true, 'contrari' => null];
    foreach ($dati['alunni'] as $alunno=>$alu) {
      foreach ($dati['materie'] as $materia=>$mat) {
        // esclude alunni NA per religione
        if ($mat['tipo'] == 'N' || $alu['religione'] == 'S') {
          // calcola assenze di alunno
          $ore = $this->em->getRepository('AppBundle:AssenzaLezione')->createQueryBuilder('al')
            ->select('SUM(al.ore)')
            ->join('al.lezione', 'l')
            ->where('al.alunno=:alunno AND l.classe=:classe AND l.materia=:materia AND l.data>:inizio')
            ->setParameters(['alunno' => $alunno, 'classe' => $classe->getId(), 'materia' => $materia,
              'inizio' => $this->session->get('/CONFIG/SCUOLA/periodo1_fine')])
            ->getQuery()
            ->getSingleScalarResult();
          $ore = ($ore ? intval($ore) : 0);
          // inserisce voti e assenze
          $voto = (isset($dati['proposte'][$alunno][$materia]['unico']) ? $dati['proposte'][$alunno][$materia]['unico'] : null);
          $recupero = (isset($dati['proposte'][$alunno][$materia]['recupero']) ? $dati['proposte'][$alunno][$materia]['recupero'] : null);
          $this->em->getConnection()
            ->prepare('INSERT INTO gs_voto_scrutinio '.
              '(scrutinio_id, alunno_id, materia_id, modificato, unico, debito, recupero, assenze, dati) '.
              'VALUES (:scrutinio,:alunno,:materia,NOW(),:unico,:debito,:recupero,:ore,:dati)')
            ->execute(['scrutinio' => $scrutinio->getId(), 'alunno' => $alunno, 'materia' => $materia,
              'unico' => $voto, 'debito' => null, 'recupero' => $recupero, 'ore' => $ore,
              'dati' => serialize($dati_scrutinio)]);
        }
      }
    }
   // aggiorna stato
    $scrutinio->setStato('1');
    $this->em->flush();
    // log
    $this->dblogger->write($docente, $request->getClientIp(), 'SCRUTINIO', 'Cambio stato', __METHOD__, array(
      'Scrutinio' => $scrutinio->getId(),
      'Classe' => $classe->getId(),
      'Periodo' => '1',
      'Stato iniziale' => 'N',
      'Stato finale' => '1',
      ));
    // ok
    return true;
  }

  /**
   * Esegue il passaggio di stato 1->N per lo scrutinio del periodo 1
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_1_1_N(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // controlla se docente fa parte di staff
    if (!($docente instanceOf Staff)) {
      // errore
      return false;
    }
    // inizializza messaggi di errore
    $this->session->getFlashBag()->clear();
    // cancella assenze e voti
    $this->em->getConnection()
      ->prepare("DELETE FROM gs_voto_scrutinio WHERE scrutinio_id=:scrutinio AND materia_id NOT IN (SELECT id FROM gs_materia WHERE tipo='C')")
      ->execute(['scrutinio' => $scrutinio->getId()]);
    // aggiorna stato
    $scrutinio->setStato('N');
    $this->em->flush();
    // log
    $this->dblogger->write($docente, $request->getClientIp(), 'SCRUTINIO', 'Cambio stato', __METHOD__, array(
      'Scrutinio' => $scrutinio->getId(),
      'Classe' => $classe->getId(),
      'Periodo' => '1',
      'Stato iniziale' => '1',
      'Stato finale' => 'N',
      ));
    // ok
    return true;
  }

  /**
   * Esegue il passaggio di stato 1->2 per lo scrutinio del periodo 1
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_1_1_2(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // legge condotta
    $condotta = $this->em->getRepository('AppBundle:Materia')->findOneByTipo('C');
    // elenco voti/alunni
    $dati = $this->elencoVoti($docente, $classe, $condotta, '1');
    // controlla errori
    $errore = array();
    foreach ($dati['voti'] as $alunno=>$voto) {
      if (!$voto->getUnico()) {
        // voto non presente
        $errore['exception.voto_condotta'] = true;
      }
    }
    if (empty($errore)) {
      // aggiorna stato
      $scrutinio->setStato('2');
      $this->em->flush();
      // log
      $this->dblogger->write($docente, $request->getClientIp(), 'SCRUTINIO', 'Cambio stato', __METHOD__, array(
        'Scrutinio' => $scrutinio->getId(),
        'Classe' => $classe->getId(),
        'Periodo' => '1',
        'Stato iniziale' => '1',
        'Stato finale' => '2',
        ));
      // ok
      return true;
    }
    // imposta messaggi di errore
    foreach ($errore as $msg=>$v) {
      $this->session->getFlashBag()->add('errore', $msg);
    }
    // errori presenti
    return false;
  }

  /**
   * Esegue il passaggio di stato 2->1 per lo scrutinio del periodo 1
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_1_2_1(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // aggiorna stato
    $scrutinio->setStato('1');
    $this->em->flush();
    // log
    $this->dblogger->write($docente, $request->getClientIp(), 'SCRUTINIO', 'Cambio stato', __METHOD__, array(
      'Scrutinio' => $scrutinio->getId(),
      'Classe' => $classe->getId(),
      'Periodo' => '1',
      'Stato iniziale' => '2',
      'Stato finale' => '1',
      ));
    // ok
    return true;
  }

  /**
   * Esegue il passaggio di stato 2->3 per lo scrutinio del periodo 1
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_1_2_3(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // inizializza messaggi di errore
    $this->session->getFlashBag()->clear();
    // legge voti
    $dati = $this->quadroVoti($docente, $classe, '1');
    // controlli
    $errori = array();
    foreach ($dati['alunni'] as $a=>$alu) {
      foreach ($dati['materie'] as $m=>$mat) {
        if ($mat['tipo'] == 'R') {
          // religione
          if ($alu['religione'] == 'S' && !isset($dati['voti'][$a][$m])) {
            // mancano valutazioni
            $errori[$m] = 1;
          }
        } elseif ($mat['tipo'] == 'N') {
          // altre materie (esclusa condotta)
          if (!isset($dati['voti'][$a][$m]['unico'])) {
            // mancano valutazioni
            $errori[$m] = 1;
          } elseif (!isset($errori[$m]) && !$dati['voti'][$a][$m]['recupero'] && isset($dati['debiti'][$a][$m])) {
            // manca indicazione sul recupero
            $errori[$m] = 2;
          }
        }
      }
    }
    if (empty($errori)) {
     // aggiorna stato
      $scrutinio->setStato('3');
      $this->em->flush();
      // log
      $this->dblogger->write($docente, $request->getClientIp(), 'SCRUTINIO', 'Cambio stato', __METHOD__, array(
        'Scrutinio' => $scrutinio->getId(),
        'Classe' => $classe->getId(),
        'Periodo' => '1',
        'Stato iniziale' => '2',
        'Stato finale' => '3',
        ));
      // ok
      return true;
    }
    // imposta avvisi
    foreach ($dati['materie'] as $m=>$mat) {
      if (isset($errori[$m])) {
        switch ($errori[$m]) {
          case 1:
            // mancano valutazioni
            $this->session->getFlashBag()->add('errore', $this->trans->trans('exception.no_voto_scrutinio',
              ['%materia%' => $mat['nomeBreve']]));
            break;
          case 2:
            // mancano recuperi
            $this->session->getFlashBag()->add('errore', $this->trans->trans('exception.no_recupero_debito_scrutinio',
              ['%materia%' => $mat['nomeBreve']]));
            break;
        }
      }
    }
    // errore
    return false;
  }

  /**
   * Esegue il passaggio di stato 3->C per lo scrutinio del periodo 1
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_1_3_C(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // inizializza messaggi di errore
    $this->session->getFlashBag()->clear();
    // legge dati form
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // controlli
      if (!$form->get('data')->getData()) {
        // data non presente
        $this->session->getFlashBag()->add('errore', 'exception.scrutinio_data');
      }
      // se niente errori cambia stato
      if (!$this->session->getFlashBag()->has('errore')) {
        // imposta dati
        $scrutinio->setData($form->get('data')->getData());
        $scrutinio->setVisibile((new \DateTime())->add(new \DateInterval('PT1H')));
        // aggiorna stato
        $scrutinio->setStato('C');
        $this->em->flush();
        // log
        $this->dblogger->write($docente, $request->getClientIp(), 'SCRUTINIO', 'Cambio stato', __METHOD__, array(
          'Scrutinio' => $scrutinio->getId(),
          'Classe' => $classe->getId(),
          'Periodo' => '1',
          'Stato iniziale' => '3',
          'Stato finale' => 'C',
          ));
        // ok
        return true;
      }
    } else {
      // imposta messaggi per eventuali altri errori del form
      foreach ($form->getErrors() as $error) {
        $this->session->getFlashBag()->add('errore', $error->getMessage());
      }
    }
    // errore
    return false;
  }

  /**
   * Esegue il passaggio di stato 3->2 per lo scrutinio del periodo 1
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_1_3_2(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // inizializza messaggi di errore
    $this->session->getFlashBag()->clear();
    // aggiorna stato
    $scrutinio->setStato('2');
    $this->em->flush();
    // log
    $this->dblogger->write($docente, $request->getClientIp(), 'SCRUTINIO', 'Cambio stato', __METHOD__, array(
      'Scrutinio' => $scrutinio->getId(),
      'Classe' => $classe->getId(),
      'Periodo' => '1',
      'Stato iniziale' => '3',
      'Stato finale' => '2',
      ));
    // ok
    return true;
  }

  /**
   * Esegue il passaggio di stato C->3 per lo scrutinio del periodo 1
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_1_C_3(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // controlla se è possibile riapertura
    if (!($docente instanceOf Staff) || $scrutinio->getSincronizzazione()) {
      // errore
      return false;
    }
    // inizializza messaggi di errore
    $this->session->getFlashBag()->clear();
    // rinomina documenti di classe
    $fs = new Filesystem();
    $finder = new Finder();
    $percorso = $this->root.$this->session->get('/CONFIG/SISTEMA/dir_scrutini').'/val-intermedia/'.
      $classe->getAnno().$classe->getSezione();
    $num = 0;
    while ($fs->exists($percorso.'/BACKUP.'.$num)) {
      $num++;
    }
    $fs->mkdir($percorso.'/BACKUP.'.$num, 0775);
    $finder->files()->in($percorso)->depth('== 0');
    foreach ($finder as $file) {
      // sposta in directory
      $fs->rename($file->getRealPath(), $percorso.'/BACKUP.'.$num.'/'.$file->getBasename());
    }
    // aggiorna stato
    $scrutinio->setStato('3');
    $this->em->flush();
    // log
    $this->dblogger->write($docente, $request->getClientIp(), 'SCRUTINIO', 'Cambio stato', __METHOD__, array(
      'Scrutinio' => $scrutinio->getId(),
      'Classe' => $classe->getId(),
      'Periodo' => '1',
      'Stato iniziale' => 'C',
      'Stato finale' => '3',
      ));
    // ok
    return true;
  }

  /**
   * Esegue il passaggio di stato N->1 per lo scrutinio del periodo F
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_F_N_1(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // controlli sulle proposte
    $this->session->getFlashBag()->clear();
    $dati = $this->quadroProposte($docente, $classe, 'F');
    if (isset($dati['errori']) && in_array(1, array_values($dati['errori']))) {
      // mancano valutazioni
      return false;
    }
    $this->session->getFlashBag()->clear();
    // conteggio assenze e inserimento voti
    $dati_scrutinio = ['motivazione' => null, 'unanimita' => true, 'contrari' => null];
    foreach ($dati['alunni'] as $alunno=>$alu) {
      foreach ($dati['materie'] as $materia=>$mat) {
        // esclude alunni NA per religione
        if ($mat['tipo'] == 'N' || $alu['religione'] == 'S') {
          // calcola assenze di alunno
          $ore = $this->em->getRepository('AppBundle:AssenzaLezione')->createQueryBuilder('al')
            ->select('SUM(al.ore)')
            ->join('al.lezione', 'l')
            ->where('al.alunno=:alunno AND l.classe=:classe AND l.materia=:materia AND l.data>:inizio AND l.data<=:fine')
            ->setParameters(['alunno' => $alunno, 'classe' => $classe->getId(), 'materia' => $materia,
              'inizio' => $this->session->get('/CONFIG/SCUOLA/periodo1_fine'),
              'fine' => $this->session->get('/CONFIG/SCUOLA/periodo2_fine')])
            ->getQuery()
            ->getSingleScalarResult();
          $ore = ($ore ? intval($ore) : 0);
          // inserisce voti e assenze
          $this->em->getConnection()
            ->prepare('INSERT INTO gs_voto_scrutinio '.
              '(scrutinio_id, alunno_id, materia_id, modificato, unico, debito, recupero, assenze, dati) '.
              'VALUES (:scrutinio,:alunno,:materia,NOW(),:unico,:debito,:recupero,:ore,:dati)')
            ->execute(['scrutinio' => $scrutinio->getId(), 'alunno' => $alunno, 'materia' => $materia,
              'unico' => $dati['proposte'][$alunno][$materia]['unico'],
              'debito' => $dati['proposte'][$alunno][$materia]['debito'],
              'recupero' => $dati['proposte'][$alunno][$materia]['recupero'], 'ore' => $ore,
              'dati' => serialize($dati_scrutinio)]);
        }
      }
    }
    // aggiorna stato
    $scrutinio->setStato('1');
    $this->em->flush();
    // log
    $this->dblogger->write($docente, $request->getClientIp(), 'SCRUTINIO', 'Cambio stato', __METHOD__, array(
      'Scrutinio' => $scrutinio->getId(),
      'Classe' => $classe->getId(),
      'Periodo' => 'F',
      'Stato iniziale' => 'N',
      'Stato finale' => '1',
      ));
    // ok
    return true;
  }

  /**
   * Esegue il passaggio di stato 1->N per lo scrutinio del periodo F
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_F_1_N(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // controlla se docente fa parte di staff
    if (!($docente instanceOf Staff)) {
      // errore
      return false;
    }
    // inizializza messaggi di errore
    $this->session->getFlashBag()->clear();
    // cancella assenze e voti
    $this->em->getConnection()
      ->prepare("DELETE FROM gs_voto_scrutinio WHERE scrutinio_id=:scrutinio AND materia_id NOT IN (SELECT id FROM gs_materia WHERE tipo='C')")
      ->execute(['scrutinio' => $scrutinio->getId()]);
    // aggiorna stato
    $scrutinio->setStato('N');
    $this->em->flush();
    // log
    $this->dblogger->write($docente, $request->getClientIp(), 'SCRUTINIO', 'Cambio stato', __METHOD__, array(
      'Scrutinio' => $scrutinio->getId(),
      'Classe' => $classe->getId(),
      'Periodo' => 'F',
      'Stato iniziale' => '1',
      'Stato finale' => 'N',
      ));
    // ok
    return true;
  }

  /**
   * Esegue il passaggio di stato 1->2 per lo scrutinio del periodo F
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_F_1_2(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // inizializza messaggi di errore
    $this->session->getFlashBag()->clear();
    // legge dati form
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // controlli
      if (!$form->get('data')->getData()) {
        // data non presente
        $this->session->getFlashBag()->add('errore', 'exception.scrutinio_data');
      }
      if (!$form->get('inizio')->getData()) {
        // ora non presente
        $this->session->getFlashBag()->add('errore', 'exception.scrutinio_inizio');
      }
      if ($form->get('presiede_ds')->getData() === null) {
        // presidente ds non presente
        $this->session->getFlashBag()->add('errore', 'exception.scrutinio_presidente');
      }
      if ($form->get('presiede_ds')->getData() === false && !$form->get('presiede_docente')->getData()) {
        // presidente docente non presente
        $this->session->getFlashBag()->add('errore', 'exception.scrutinio_presidente');
      }
      if (!$form->get('segretario')->getData()) {
        // segretario non presente
        $this->session->getFlashBag()->add('errore', 'exception.scrutinio_segretario');
      }
      // controlli sui presenti
      $errore_presenza = false;
      foreach ($form->get('lista')->getData() as $doc=>$val) {
        if (!$val || (!$val->getPresenza() && !$val->getSostituto())) {
          $errore_presenza = true;
        }
      }
      if ($errore_presenza) {
        // docente non presente
        $this->session->getFlashBag()->add('errore', 'exception.scrutinio_presenza');
      }
      // se niente errori cambia stato
      if (!$this->session->getFlashBag()->has('errore')) {
        // imposta dati
        $scrutinio->setData($form->get('data')->getData());
        $scrutinio->setInizio($form->get('inizio')->getData());
        $valori = $scrutinio->getDati();
        $scrutinio->setDati(array());   // necessario per bug di aggiornamento
        $this->em->flush();             // necessario per bug di aggiornamento
        $valori['presenze'] = $form->get('lista')->getData();
        $valori['presiede_ds'] = $form->get('presiede_ds')->getData();
        $valori['presiede_docente'] = $form->get('presiede_docente')->getData();
        $valori['segretario'] = $form->get('segretario')->getData();
        $scrutinio->setDati($valori);
        // aggiorna stato
        $scrutinio->setStato('2');
        $this->em->flush();
        // log
        $this->dblogger->write($docente, $request->getClientIp(), 'SCRUTINIO', 'Cambio stato', __METHOD__, array(
          'Scrutinio' => $scrutinio->getId(),
          'Classe' => $classe->getId(),
          'Periodo' => 'F',
          'Stato iniziale' => '1',
          'Stato finale' => '2',
          ));
        // ok
        return true;
      }
    } else {
      // imposta messaggi per eventuali altri errori del form
      foreach ($form->getErrors() as $error) {
        $this->session->getFlashBag()->add('errore', $error->getMessage());
      }
    }
    // errore
    return false;
  }

  /**
   * Esegue il passaggio di stato 2->1 per lo scrutinio del periodo F
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_F_2_1(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // inizializza messaggi di errore
    $this->session->getFlashBag()->clear();
    // aggiorna stato
    $scrutinio->setStato('1');
    $this->em->flush();
    // log
    $this->dblogger->write($docente, $request->getClientIp(), 'SCRUTINIO', 'Cambio stato', __METHOD__, array(
      'Scrutinio' => $scrutinio->getId(),
      'Classe' => $classe->getId(),
      'Periodo' => 'F',
      'Stato iniziale' => '2',
      'Stato finale' => '1',
      ));
    // ok
    return true;
  }

  /**
   * Restituisce i dati sulle assenze degli alunni
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Classe $classe Classe relativa alle proposte di voto
   * @param string $periodo Periodo relativo allo scrutinio
   *
   * @return array Dati formattati come un array associativo
   */
  public function controlloAssenze(Docente $docente, Classe $classe, $periodo) {
    $dati = array();
    $dati['alunni'] = array();
    $dati['no_scrutinabili']['alunni'] = array();
    $dati['no_scrutinabili']['form'] = array();
    $dati['ritirati'] = array();
    // legge scrutinio finale
    $scrutinio_F = $this->em->getRepository('AppBundle:Scrutinio')->createQueryBuilder('s')
      ->where('s.periodo=:periodo AND s.classe=:classe')
      ->setParameters(['periodo' => 'F', 'classe' => $classe])
      ->setMaxResults(1)
      ->getQuery()
      ->getOneOrNullResult();
    if (!$scrutinio_F) {
      // errore
      return null;
    }
    // legge scrutinio primo trimestre
    $scrutinio_P = $this->em->getRepository('AppBundle:Scrutinio')->createQueryBuilder('s')
      ->where('s.periodo=:periodo AND s.classe=:classe')
      ->setParameters(['periodo' => 'P', 'classe' => $classe])
      ->setMaxResults(1)
      ->getQuery()
      ->getOneOrNullResult();
    if (!$scrutinio_P) {
      // errore
      return null;
    }
    // calcola limite assenze
    $dati['monteore'] = $classe->getOreSettimanali() * 33;
    $dati['maxassenze'] = intval($dati['monteore'] / 4);
    // calcola giorni dal 15 marzo a fine scuola
    $inizio = \DateTime::createFromFormat('!Y-m-d',
      substr($this->session->get('/CONFIG/SCUOLA/anno_fine'), 0, 4).'-03-15');
    $fine = \DateTime::createFromFormat('!Y-m-d', $this->session->get('/CONFIG/SCUOLA/anno_fine'));
    $festivi = $this->em->getRepository('AppBundle:Festivita')->createQueryBuilder('f')
      ->select('f.data')
      ->where('f.tipo=:festivo AND f.data BETWEEN :inizio AND :fine AND (f.sede IS NULL OR f.sede=:sede)')
      ->orderBy('f.data', 'ASC')
      ->setParameters(['festivo' => 'F', 'inizio' => $inizio->format('Y-m-d'), 'fine' => $fine->format('Y-m-d'),
        'sede' => $classe->getSede()])
      ->getQuery()
      ->getArrayResult();
    $giorni_festivi = array();
    foreach ($festivi as $f) {
      $giorni_festivi[] = $f['data']->format('Y-m-d');
    }
    $giorni = 0;
    $data = clone $inizio;
    while ($data <= $fine) {
      if ($data->format('w') == 0 || in_array($data->format('Y-m-d'), $giorni_festivi) ||
          ($classe->getAnno() == 1 && $classe->getSezione() == 'R' && $data->format('w') == 6)) {
        // festivo (per 1R anche sabato è festivo!!)
      } else {
        // giorno di lezione
        $giorni++;
      }
      $data->modify('+1 day');
    }
    $dati['giorni_finali'] = $giorni;
    // calcola ore totali assenza alunni
    $alunni = $this->em->getRepository('AppBundle:Alunno')->createQueryBuilder('a')
      ->select('a.id,a.cognome,a.nome,a.sesso,a.dataNascita,a.bes,SUM(vs.assenze) AS ore')
      ->join('AppBundle:VotoScrutinio', 'vs', 'WHERE', 'vs.alunno=a.id')
      ->where('a.classe=:classe AND a.abilitato=:abilitato AND vs.scrutinio IN (:scrutini)')
      ->groupBy('a.id')
      ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
      ->setParameters(['classe' => $classe, 'abilitato' => 1, 'scrutini' => [$scrutinio_P, $scrutinio_F]])
      ->getQuery()
      ->getArrayResult();
    foreach ($alunni as $a) {
      // percentuale assenze
      $perc = $a['ore'] / $dati['monteore'] * 100;
      if ($perc <= 25) {
        // assenze entro limite
        $dati['alunni'][$a['id']] = $a;
        $dati['alunni'][$a['id']]['percentuale'] = $perc;
      } else {
        // assenze oltre il limite: non scrutinabile
        $dati['no_scrutinabili']['alunni'][$a['id']] = $a;
        $dati['no_scrutinabili']['alunni'][$a['id']]['percentuale'] = $perc;
        $dati['no_scrutinabili']['alunni'][$a['id']]['giorni_finali'] =
          $this->em->getRepository('AppBundle:Assenza')->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.alunno=:alunno AND a.data BETWEEN :inizio AND :fine')
            ->setParameters(['alunno' => $a['id'], 'inizio' => $inizio->format('Y-m-d'),
              'fine' => $fine->format('Y-m-d')])
            ->getQuery()
            ->getSingleScalarResult();
        // crea oggetto per form
        $dati['no_scrutinabili']['form'][$a['id']] = (new ScrutinioAssenza())
          ->setAlunno($a['id'])
          ->setSesso($a['sesso']);
        // recupera dati esistenti
        $valori = $scrutinio_F->getDati();
        if (isset($valori['alunni'][$a['id']]['deroga'])) {
          // scrutinabile in deroga
          $dati['no_scrutinabili']['form'][$a['id']]
            ->setScrutinabile('D')
            ->setMotivazione($valori['alunni'][$a['id']]['deroga']);
        } elseif (isset($valori['alunni'][$a['id']]['no_deroga'])) {
          // non scrutinabile
          $dati['no_scrutinabili']['form'][$a['id']]
            ->setScrutinabile($valori['alunni'][$a['id']]['no_deroga']);
        }
      }
    }
    // alunni ritirati/trasferiti/all'estero
    $alunni = $this->em->getRepository('AppBundle:Alunno')->createQueryBuilder('a')
      ->select('a.id,a.nome,a.cognome,a.sesso,a.dataNascita,a.bes,a.frequenzaEstero,cc.note')
      ->join('AppBundle:CambioClasse', 'cc', 'WHERE', 'cc.alunno=a.id')
      ->where('a.classe IS NULL AND a.abilitato=:abilitato AND cc.classe=:classe')
      ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
      ->setParameters(['abilitato' => 1, 'classe' => $classe])
      ->getQuery()
      ->getArrayResult();
    foreach ($alunni as $a) {
      $dati['ritirati'][$a['id']] = $a;
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Gestione del form per il controllo delle assenze
   *
   * @param Classe $classe Classe relativa allo scrutinio
   * @param string $periodo Periodo relativo allo scrutinio
   * @param FormBuilder $form Form per lo scrutinio
   * @param array $dati Dati passati al form
   *
   * @return FormType|null Form usato nella pagina corrente dello scrutinio
   */
  public function controlloAssenzeForm(Classe $classe, $periodo, FormBuilder $form, $dati) {
    // crea form
    $form
      ->setAction($this->router->generate('coordinatore_scrutinio',
        ['classe' => $classe->getId(), 'stato' => '3']))
      ->add('lista', CollectionType::class, array('label' => false,
        'data' => $dati['no_scrutinabili']['form'],
        'entry_type' => ScrutinioAssenzaType::class,
        'entry_options' => array('label' => false),
        ));
    // restituisce form
    return $form;
  }

  /**
   * Esegue il passaggio di stato 2->3 per lo scrutinio del periodo F
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_F_2_3(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // inizializza
    $this->session->getFlashBag()->clear();
    // legge dati assenze
    $dati = $this->controlloAssenze($docente, $classe, 'F');
    // legge dati form
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // controlli
      $errore_scrutinabile = false;
      $errore_motivazione = false;
      $errore_alunni = false;
      foreach ($form->get('lista')->getData() as $val) {
        if (!$val || !array_key_exists($val->getAlunno(), $dati['no_scrutinabili']['alunni'])) {
          // lista alunni no scrutinabili errata
          $errore_alunni = true;
        } elseif (!$val->getScrutinabile()) {
          // non inserito se scrutinabile
          $errore_scrutinabile = true;
        } elseif ($val->getScrutinabile() == 'D' && !$val->getMotivazione()) {
          // non inserita motivazione di deroga
          $errore_motivazione = true;
        }
      }
      if ($errore_scrutinabile) {
        // non inserito se scrutinabile
        $this->session->getFlashBag()->add('errore', 'exception.scrutinio_tipo_scrutinabile');
      }
      if ($errore_motivazione) {
        // non inserita motivazione di deroga
        $this->session->getFlashBag()->add('errore', 'exception.scrutinio_motivazione_deroga');
      }
      if ($errore_alunni || count($dati['no_scrutinabili']['alunni']) != count($form->get('lista')->getData())) {
        // lista alunni no scrutinabili errata
        $this->session->getFlashBag()->add('errore', 'exception.scrutinio_lista_no_scrutinabili');
      }
      // se niente errori cambia stato
      if (!$this->session->getFlashBag()->has('errore')) {
        // imposta dati
        $valori = $scrutinio->getDati();
        $scrutinio->setDati(array());   // necessario per bug di aggiornamento
        $this->em->flush();             // necessario per bug di aggiornamento
        $valori['monteore'] = $dati['monteore'];
        $valori['maxassenze'] = $dati['maxassenze'];
        // dati alunni scrutinabili e non scrutinabili
        $valori['scrutinabili'] = null;
        $valori['no_scrutinabili'] = null;
        $valori['alunni'] = null;
        foreach ($dati['alunni'] as $alu=>$a) {
          $valori['scrutinabili'][] = $alu;
          $valori['alunni'][$alu]['ore'] = $a['ore'];
          $valori['alunni'][$alu]['percentuale'] = $a['percentuale'];
        }
        foreach ($form->get('lista')->getData() as $val) {
          $alu = $val->getAlunno();
          $valori['alunni'][$alu]['ore'] = $dati['no_scrutinabili']['alunni'][$alu]['ore'];
          $valori['alunni'][$alu]['percentuale'] = $dati['no_scrutinabili']['alunni'][$alu]['percentuale'];
          if ($val->getScrutinabile() == 'D') {
            // scrutinabili in deroga
            $valori['scrutinabili'][] = $alu;
            $valori['alunni'][$alu]['deroga'] = $val->getMotivazione();
          } else {
            // no scrutinabili
            $valori['no_scrutinabili'][] = $alu;
            $valori['alunni'][$alu]['no_deroga'] = $val->getScrutinabile();
          }
        }
        // dati alunni ritirati/trasferiti/all'estero
        $valori['ritirati'] = null;
        foreach ($dati['ritirati'] as $alu=>$a) {
          $valori['ritirati'][] = $alu;
        }
        // aggiorna dati
        $scrutinio->setDati($valori);
        // aggiorna stato
        $scrutinio->setStato('3');
        $this->em->flush();
        // log
        $this->dblogger->write($docente, $request->getClientIp(), 'SCRUTINIO', 'Cambio stato', __METHOD__, array(
          'Scrutinio' => $scrutinio->getId(),
          'Classe' => $classe->getId(),
          'Periodo' => 'F',
          'Stato iniziale' => '2',
          'Stato finale' => '3',
          ));
        // ok
        return true;
      }
    } else {
      // imposta messaggi per eventuali altri errori del form
      foreach ($form->getErrors() as $error) {
        $this->session->getFlashBag()->add('errore', $error->getMessage());
      }
    }
    // errore
    return false;
  }

  /**
   * Esegue il passaggio di stato 3->2 per lo scrutinio del periodo F
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_F_3_2(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // aggiorna stato
    $scrutinio->setStato('2');
    $this->em->flush();
    // log
    $this->dblogger->write($docente, $request->getClientIp(), 'SCRUTINIO', 'Cambio stato', __METHOD__, array(
      'Scrutinio' => $scrutinio->getId(),
      'Classe' => $classe->getId(),
      'Periodo' => 'F',
      'Stato iniziale' => '3',
      'Stato finale' => '2',
      ));
    // ok
    return true;
  }

  /**
   * Esegue il passaggio di stato 3->4 per lo scrutinio del periodo F
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_F_3_4(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // inizializza
    $this->session->getFlashBag()->clear();
    // legge condotta
    $condotta = $this->em->getRepository('AppBundle:Materia')->findOneByTipo('C');
    // elenco voti/alunni
    $dati = $this->elencoVoti($docente, $classe, $condotta, 'F');
    // controlla errori
    $errore = array();
    foreach ($dati['voti'] as $alunno=>$voto) {
      if (!$voto->getUnico()) {
        // voto non presente
        $errore['exception.voto_condotta'] = true;
      } elseif (!$voto->getDato('motivazione') && $voto->getUnico() > 4) {
        // manca motivazione
        $errore['exception.motivazione_condotta'] = true;
      }
      if ($voto->getDato('unanimita') === null) {
        // manca delibera
        $errore['exception.delibera_condotta'] = true;
      } elseif ($voto->getDato('unanimita') === false && !$voto->getDato('contrari')) {
        // mancano contrari
        $errore['exception.contrari_condotta'] = true;
      }
    }
    if (empty($errore)) {
      // aggiorna stato
      $scrutinio->setStato('4');
      $this->em->flush();
      // log
      $this->dblogger->write($docente, $request->getClientIp(), 'SCRUTINIO', 'Cambio stato', __METHOD__, array(
        'Scrutinio' => $scrutinio->getId(),
        'Classe' => $classe->getId(),
        'Periodo' => 'F',
        'Stato iniziale' => '3',
        'Stato finale' => '4',
        ));
      // ok
      return true;
    }
    // imposta messaggi di errore
    foreach ($errore as $msg=>$v) {
      $this->session->getFlashBag()->add('errore', $msg);
    }
    // errori presenti
    return false;
  }

  /**
   * Esegue il passaggio di stato 4->3 per lo scrutinio del periodo F
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_F_4_3(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // aggiorna stato
    $scrutinio->setStato('3');
    $this->em->flush();
    // log
    $this->dblogger->write($docente, $request->getClientIp(), 'SCRUTINIO', 'Cambio stato', __METHOD__, array(
      'Scrutinio' => $scrutinio->getId(),
      'Classe' => $classe->getId(),
      'Periodo' => 'F',
      'Stato iniziale' => '4',
      'Stato finale' => '3',
      ));
    // ok
    return true;
  }

  /**
   * Restituisce la situazione dei voti dello scrutinio
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Classe $classe Classe relativa alle proposte di voto
   * @param string $periodo Periodo relativo allo scrutinio
   *
   * @return array Dati formattati come un array associativo
   */
  public function quadroEsiti(Docente $docente, Classe $classe, $periodo) {
    $dati = array();
    // legge voti
    $dati = $this->quadroVoti($docente, $classe, $periodo);
    // legge esiti
    $esiti = $this->em->getRepository('AppBundle:Esito')->createQueryBuilder('e')
      ->join('e.scrutinio', 's')
      ->where('s.classe=:classe AND s.periodo=:periodo')
      ->setParameters(['classe' => $classe, 'periodo' => $periodo])
      ->getQuery()
      ->getResult();
    foreach ($esiti as $e) {
      // inserisce esiti
      $dati['esiti'][$e->getAlunno()->getId()] = array(
        'id' => $e->getId(),
        'esito' => $e->getEsito());
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Restituisce la lista dei voti dello scrutinio per l'alunno indicato
   *
   * @param Docente $docente Docente che esegue la lettura
   * @param Alunno $alunno Alunno di cui restituire i voti
   * @param string $periodo Periodo relativo allo scrutinio
   *
   * @return array Dati formattati come un array associativo
   */
  public function elencoVotiAlunno(Docente $docente, Alunno $alunno, $periodo) {
    $dati = array();
    $dati['voti'] = array();
    // alunni della classe
    $lista_id = $this->alunniInScrutinio($alunno->getClasse(), $periodo);
    if (!in_array($alunno->getId(), $lista_id)) {
      // errore: alunno non previsto
      return null;
    }
    // legge scrutinio
    $scrutinio = $this->em->getRepository('AppBundle:Scrutinio')->createQueryBuilder('s')
      ->where('s.classe=:classe AND s.periodo=:periodo')
      ->setParameters(['classe' => $alunno->getClasse(), 'periodo' => $periodo])
      ->setMaxResults(1)
      ->getQuery()
      ->getOneOrNullResult();
    $dati['scrutinio'] = $scrutinio;
    // legge materie
    $materie = $this->em->getRepository('AppBundle:Materia')->createQueryBuilder('m')
      ->select('DISTINCT m.id,m.nome,m.nomeBreve,m.tipo')
      ->join('AppBundle:Cattedra', 'c', 'WHERE', 'c.materia=m.id')
      ->where('c.classe=:classe AND c.attiva=:attiva AND c.tipo=:tipo')
      ->orderBy('m.ordinamento', 'ASC')
      ->setParameters(['classe' => $alunno->getClasse(), 'attiva' => 1, 'tipo' => 'N'])
      ->getQuery()
      ->getArrayResult();
    foreach ($materie as $mat) {
      $dati['materie'][$mat['id']] = $mat;
    }
    $condotta = $this->em->getRepository('AppBundle:Materia')->findOneByTipo('C');
    $dati['materie'][$condotta->getId()] = array(
      'id' => $condotta->getId(),
      'nome' => $condotta->getNome(),
      'nomeBreve' => $condotta->getNomeBreve(),
      'tipo' => $condotta->getTipo());
    // legge i voti
    $voti = $this->em->getRepository('AppBundle:VotoScrutinio')->createQueryBuilder('vs')
      ->join('vs.materia', 'm')
      ->where('vs.scrutinio=:scrutinio AND vs.alunno=:alunno')
      ->orderBy('m.ordinamento', 'ASC')
      ->setParameters(['scrutinio' => $scrutinio, 'alunno' => $alunno])
      ->getQuery()
      ->getResult();
    foreach ($voti as $v) {
      // inserisce voti
      $dati['voti'][$v->getMateria()->getId()] = $v;
    }
    // legge esito
    $esito = $this->em->getRepository('AppBundle:Esito')->findOneBy(['scrutinio' => $scrutinio, 'alunno' => $alunno]);
    if (!$esito) {
      // crea nuovo esito
      $dati_esito = array(
        'unanimita' => true,
        'contrari' => null,
        'giudizio' => null);
      $esito = (new Esito())
        ->setScrutinio($scrutinio)
        ->setAlunno($alunno)
        ->setDati($dati_esito);
      $this->em->persist($esito);
    }
    $dati['esito'] = $esito;
    // restituisce dati
    return $dati;
  }

  /**
   * Esegue il passaggio di stato 4->5 per lo scrutinio del periodo F
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_F_4_5(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // inizializza
    $this->session->getFlashBag()->clear();
    $errore = array();
    $valutazioni['F']['N'] = ['min' => 0, 'max' => 10, 'start' => 6, 'ticks' => '0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10', 'labels' => '"NC", 1, 2, 3, 4, 5, 6, 7, 8, 9, 10'];
    $valutazioni['F']['C'] = ['min' => 4, 'max' => 10, 'start' => 6, 'ticks' => '4, 5, 6, 7, 8, 9, 10', 'labels' => '"NC", 5, 6, 7, 8, 9, 10'];
    $valutazioni['F']['R'] = ['min' => 20, 'max' => 25, 'start' => 22, 'ticks' => '20, 21, 22, 23, 24, 25', 'labels' => '"NC", "Insuff.", "Suff.", "Buono", "Dist.", "Ottimo"'];
    // alunni della classe
    $lista_id = $this->alunniInScrutinio($classe, 'F');
    foreach ($lista_id as $id) {
      // recupera alunno
      $alunno = $this->em->getRepository('AppBundle:Alunno')->find($id);
      $sesso = ($alunno->getSesso() == 'M' ? 'o' : 'a');
      $nome = $alunno->getCognome().' '.$alunno->getNome();
      // elenco voti dell'alunno
      $dati = $this->elencoVotiAlunno($docente, $alunno, 'F');
      // controlla errori
      $no_voto = 0;
      $insuff_cont = 0;
      $insuff_religione = false;
      $insuff_condotta = false;
      foreach ($dati['voti'] as $key=>$voto) {
        // controllo voto
        if ($voto->getUnico() === null || $voto->getUnico() < $valutazioni['F'][$voto->getMateria()->getTipo()]['min'] ||
            $voto->getUnico() > $valutazioni['F'][$voto->getMateria()->getTipo()]['max']) {
          // voto non ammesso o non presente
          $no_voto++;
        } elseif ($voto->getMateria()->getTipo() == 'R' && $voto->getUnico() < $valutazioni['F']['R']['start']) {
          // voto religione insufficiente
          $insuff_religione = true;
          $insuff_cont++;
        } elseif ($voto->getMateria()->getTipo() == 'C' && $voto->getUnico() < $valutazioni['F']['C']['start']) {
          // voto condotta insufficiente
          $insuff_condotta = true;
          $insuff_cont++;
        } elseif ($voto->getUnico() < $valutazioni['F'][$voto->getMateria()->getTipo()]['start']) {
          // voto insufficiente
          $insuff_cont++;
        }
      }
      if ($no_voto > 0) {
        // voti non presenti
        $errore[] = $this->trans->trans('exception.no_voto_esito', ['%sex%' => $sesso, '%alunno%' => $nome]);
      }
      if ($dati['esito']->getEsito() === null) {
        // manca esito
        $errore[] = $this->trans->trans('exception.manca_esito', ['%sex%' => $sesso, '%alunno%' => $nome]);
      }
      if ($dati['esito']->getDati()['unanimita'] === null) {
        // manca delibera
        $errore[] = $this->trans->trans('exception.delibera_esito', ['%sex%' => $sesso, '%alunno%' => $nome]);
      } elseif ($dati['esito']->getDati()['unanimita'] === false && !$dati['esito']->getDati()['contrari']) {
        // mancano contrari
        $errore[] = $this->trans->trans('exception.contrari_esito', ['%sex%' => $sesso, '%alunno%' => $nome]);
      }
      if ($dati['esito']->getEsito() == 'N' && !$dati['esito']->getDati()['giudizio']) {
        // manca giudizio
        $errore[] = $this->trans->trans('exception.giudizio_esito', ['%sex%' => $sesso, '%alunno%' => $nome]);
      }
      if ($dati['esito']->getEsito() == 'A' && $insuff_cont > 0) {
        // insufficienze con ammissione
        $errore[] = $this->trans->trans('exception.insufficienze_ammissione_esito', ['%sex%' => $sesso, '%alunno%' => $nome]);
      }
      if ($dati['esito']->getEsito() == 'N' && $insuff_cont == 0) {
        // solo sufficienze con non ammissione
        $errore[] = $this->trans->trans('exception.sufficienze_non_ammissione_esito', ['%sex%' => $sesso, '%alunno%' => $nome]);
      }
      if ($dati['esito']->getEsito() == 'S' && $insuff_cont == 0) {
        // solo sufficienze con sospensione
        $errore[] = $this->trans->trans('exception.sufficienze_sospensione_esito', ['%sex%' => $sesso, '%alunno%' => $nome]);
      }
      if ($dati['esito']->getEsito() != 'N' && $insuff_religione) {
        // insuff. religione incoerente con esito
        $errore[] = $this->trans->trans('exception.voto_religione_esito', ['%sex%' => $sesso, '%alunno%' => $nome]);
      }
      if ($dati['esito']->getEsito() != 'N' && $insuff_condotta) {
        // insuff. condotta incoerente con esito
        $errore[] = $this->trans->trans('exception.voto_condotta_esito', ['%sex%' => $sesso, '%alunno%' => $nome]);
      }
      if ($dati['esito']->getEsito() == 'S' && $alunno->getClasse()->getAnno() == 5) {
        // sospensione in quinta
        $errore[] = $this->trans->trans('exception.exception.quinta_sospeso_esito', ['%sex%' => $sesso, '%alunno%' => $nome]);
      }
    }
    if (empty($errore)) {
      // aggiorna stato
      $scrutinio->setStato('5');
      $this->em->flush();
      // log
      $this->dblogger->write($docente, $request->getClientIp(), 'SCRUTINIO', 'Cambio stato', __METHOD__, array(
        'Scrutinio' => $scrutinio->getId(),
        'Classe' => $classe->getId(),
        'Periodo' => 'F',
        'Stato iniziale' => '4',
        'Stato finale' => '5',
        ));
      // ok
      return true;
    }
    // imposta messaggi di errore
    foreach ($errore as $msg) {
      $this->session->getFlashBag()->add('errore', $msg);
    }
    // errori presenti
    return false;
  }

  /**
   * Esegue il passaggio di stato 5->4 per lo scrutinio del periodo F
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_F_5_4(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // cancella medie
    $this->em->getConnection()
      ->prepare("UPDATE gs_esito SET media=NULL,credito=NULL WHERE scrutinio_id=:scrutinio")
      ->execute(['scrutinio' => $scrutinio->getId()]);
    // aggiorna stato
    $scrutinio->setStato('4');
    $this->em->flush();
    // log
    $this->dblogger->write($docente, $request->getClientIp(), 'SCRUTINIO', 'Cambio stato', __METHOD__, array(
      'Scrutinio' => $scrutinio->getId(),
      'Classe' => $classe->getId(),
      'Periodo' => 'F',
      'Stato iniziale' => '5',
      'Stato finale' => '4',
      ));
    // ok
    return true;
  }

  /**
   * Restituisce il quadro per i crediti
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Classe $classe Classe relativa alle proposte di voto
   * @param string $periodo Periodo relativo allo scrutinio
   *
   * @return array Dati formattati come un array associativo
   */
  public function quadroCrediti(Docente $docente, Classe $classe, $periodo) {
    $dati = array();
    // legge alunni
    $lista = $this->alunniInScrutinio($classe, $periodo);
    $alunni = $this->em->getRepository('AppBundle:Alunno')->createQueryBuilder('a')
      ->select('a.id,a.nome,a.cognome,a.dataNascita,a.religione,a.bes,a.note,a.credito3,a.credito4,e.id AS esito')
      ->join('AppBundle:Esito', 'e', 'WHERE', 'a.id=e.alunno')
      ->join('e.scrutinio', 's')
      ->where('a.id in (:lista) AND e.esito=:ammesso AND s.classe=:classe AND s.periodo=:periodo')
      ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
      ->setParameters(['lista' => $lista, 'ammesso' => 'A', 'classe' => $classe, 'periodo' => $periodo])
      ->getQuery()
      ->getArrayResult();
    foreach ($alunni as $alu) {
      $dati['alunni'][$alu['id']] = $alu;
      // legge esito
      $dati['esiti'][$alu['id']] = $this->em->getRepository('AppBundle:Esito')->find($alu['esito']);
      // calcola medie se non presenti
      if (!$dati['esiti'][$alu['id']]->getMedia()) {
        // calcola media
        $media = $this->em->getRepository('AppBundle:VotoScrutinio')->createQueryBuilder('vs')
          ->select('AVG(vs.unico)')
          ->join('vs.scrutinio', 's')
          ->join('vs.materia', 'm')
          ->where('vs.alunno=:alunno AND s.classe=:classe AND s.periodo=:periodo AND m.media=:media')
          ->setParameters(['alunno' => $alu['id'], 'classe' => $classe, 'periodo' => $periodo, 'media' => 1])
          ->getQuery()
          ->getSingleScalarResult();
        $dati['esiti'][$alu['id']]->setMedia($media);
        $dati['esiti'][$alu['id']]->setCreditoPrecedente($classe->getAnno() == 3 ? 0 :
          ($classe->getAnno() == 4 ? $alu['credito3'] : $alu['credito3'] + $alu['credito4']));
        if ($periodo == 'R') {
          // il credito è sempre minimo di banda
          $credito = array();
          $credito[3] = [6 => 3, 7 => 4, 8 => 5, 9 => 6, 10 => 7];
          $credito[4] = [6 => 3, 7 => 4, 8 => 5, 9 => 6, 10 => 7];
          $dati['esiti'][$alu['id']]->setCredito($credito[$classe->getAnno()][ceil($media)]);
        }
        $this->em->flush();
      }
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Restituisce il quadro per le competenze
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Classe $classe Classe relativa alle proposte di voto
   * @param string $periodo Periodo relativo allo scrutinio
   *
   * @return array Dati formattati come un array associativo
   */
  public function quadroCompetenze(Docente $docente, Classe $classe, $periodo) {
    $dati = array();
    // legge alunni
    $lista = $this->alunniInScrutinio($classe, $periodo);
    $alunni = $this->em->getRepository('AppBundle:Alunno')->createQueryBuilder('a')
      ->select('a.id,a.nome,a.cognome,a.dataNascita,a.religione,a.bes,a.note,e.id AS esito')
      ->join('AppBundle:Esito', 'e', 'WHERE', 'a.id=e.alunno')
      ->join('e.scrutinio', 's')
      ->where('a.id in (:lista) AND e.esito=:ammesso AND s.classe=:classe AND s.periodo=:periodo')
      ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
      ->setParameters(['lista' => $lista, 'ammesso' => 'A', 'classe' => $classe, 'periodo' => $periodo])
      ->getQuery()
      ->getArrayResult();
    foreach ($alunni as $alu) {
      $dati['alunni'][$alu['id']] = $alu;
      // legge esito
      $dati['esiti'][$alu['id']] = $this->em->getRepository('AppBundle:Esito')->find($alu['esito']);
      // calcola medie se non presenti
      if (!$dati['esiti'][$alu['id']]->getMedia()) {
        // calcola media
        $media = $this->em->getRepository('AppBundle:VotoScrutinio')->createQueryBuilder('vs')
          ->select('AVG(vs.unico)')
          ->join('vs.scrutinio', 's')
          ->join('vs.materia', 'm')
          ->where('vs.alunno=:alunno AND s.classe=:classe AND s.periodo=:periodo AND m.media=:media')
          ->setParameters(['alunno' => $alu['id'], 'classe' => $classe, 'periodo' => $periodo, 'media' => 1])
          ->getQuery()
          ->getSingleScalarResult();
        $dati['esiti'][$alu['id']]->setMedia($media);
        $this->em->flush();
      }
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Esegue il passaggio di stato 5->6 per lo scrutinio del periodo F
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_F_5_6(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // inizializza
    $errore = array();
    $this->session->getFlashBag()->clear();
    // alunni della classe
    $lista_id = $this->alunniInScrutinio($classe, 'F');
    // distingue per classe
    if ($classe->getAnno() == 2) {
      // competenze
      $competenze = $this->em->getRepository('AppBundle:Esito')->createQueryBuilder('e')
        ->select('a.cognome,a.nome,a.sesso,a.dataNascita,e.dati')
        ->join('e.alunno', 'a')
        ->where('e.scrutinio=:scrutinio AND e.alunno IN (:lista) AND e.esito=:ammesso')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
        ->setParameters(['scrutinio' => $scrutinio, 'lista' => $lista_id, 'ammesso' => 'A'])
        ->getQuery()
        ->getArrayResult();
      foreach ($competenze as $c) {
        if (!isset($c['dati']['certificazione']) || !$c['dati']['certificazione']) {
          $nome = $c['cognome'].' '.$c['nome'];
          $sesso = ($c['sesso'] == 'M' ? 'o' : 'a');
          $errore[] = $this->trans->trans('exception.no_certificazione_esito', ['%sex%' => $sesso, '%alunno%' => $nome]);
        }
      }
    } elseif ($classe->getAnno() != 1) {
      // crediti
      $crediti = $this->em->getRepository('AppBundle:Esito')->createQueryBuilder('e')
        ->select('a.cognome,a.nome,a.sesso,a.dataNascita')
        ->join('e.alunno', 'a')
        ->where('e.scrutinio=:scrutinio AND e.alunno IN (:lista) AND e.esito=:ammesso AND e.credito IS NULL')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
        ->setParameters(['scrutinio' => $scrutinio, 'lista' => $lista_id, 'ammesso' => 'A'])
        ->getQuery()
        ->getArrayResult();
      foreach ($crediti as $c) {
        $nome = $c['cognome'].' '.$c['nome'];
        $sesso = ($c['sesso'] == 'M' ? 'o' : 'a');
        $errore[] = $this->trans->trans('exception.no_credito_esito', ['%sex%' => $sesso, '%alunno%' => $nome]);
      }
    }
    if (empty($errore)) {
      // aggiorna stato
      $scrutinio->setStato('6');
      $this->em->flush();
      // log
      $this->dblogger->write($docente, $request->getClientIp(), 'SCRUTINIO', 'Cambio stato', __METHOD__, array(
        'Scrutinio' => $scrutinio->getId(),
        'Classe' => $classe->getId(),
        'Periodo' => 'F',
        'Stato iniziale' => '5',
        'Stato finale' => '6',
        ));
      // ok
      return true;
    }
    // imposta messaggi di errore
    foreach ($errore as $msg) {
      $this->session->getFlashBag()->add('errore', $msg);
    }
    // errori presenti
    return false;
  }

  /**
   * Restituisce il quadro per le gestione delle comunicazioni (debiti e carenze)
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Classe $classe Classe relativa alle proposte di voto
   * @param string $periodo Periodo relativo allo scrutinio
   *
   * @return array Dati formattati come un array associativo
   */
  public function quadroComunicazioni(Docente $docente, Classe $classe, $periodo) {
    $dati = array();
    $dati['debiti'] = array();
    $dati['carenze'] = array();
    $dati['esiti'] = array();
    // legge alunni
    $lista = $this->alunniInScrutinio($classe, $periodo);
    // debiti
    $alunni = $this->em->getRepository('AppBundle:Alunno')->createQueryBuilder('a')
      ->select('a.id,a.nome,a.cognome,a.dataNascita,a.sesso,a.religione,a.bes,a.note,e.id AS esito,m.id AS materia_id,m.nomeBreve AS materia')
      ->join('AppBundle:Esito', 'e', 'WHERE', 'a.id=e.alunno')
      ->join('e.scrutinio', 's')
      ->join('AppBundle:VotoScrutinio', 'vs', 'WHERE', 'vs.scrutinio=s.id AND vs.alunno=a.id')
      ->join('vs.materia', 'm')
      ->where('a.id in (:lista) AND e.esito=:sospeso AND s.classe=:classe AND s.periodo=:periodo AND vs.unico<:suff AND m.tipo=:tipo')
      ->orderBy('a.cognome,a.nome,a.dataNascita,m.ordinamento', 'ASC')
      ->setParameters(['lista' => $lista, 'sospeso' => 'S', 'classe' => $classe, 'periodo' => $periodo, 'suff' => 6, 'tipo' => 'N'])
      ->getQuery()
      ->getArrayResult();
    foreach ($alunni as $alu) {
      $dati['debiti'][$alu['id']][$alu['materia_id']]  = $alu;
      // legge esito
      $dati['esiti'][$alu['id']] = $this->em->getRepository('AppBundle:Esito')->find($alu['esito']);
    }
    // carenze
    $alunni = $this->em->getRepository('AppBundle:Alunno')->createQueryBuilder('a')
      ->select('a.id,a.nome,a.cognome,a.dataNascita,a.sesso,a.religione,a.bes,a.note,e.id AS esito,m.id AS materia_id,m.nomeBreve AS materia')
      ->join('AppBundle:Esito', 'e', 'WHERE', 'a.id=e.alunno')
      ->join('e.scrutinio', 's')
      ->join('AppBundle:VotoScrutinio', 'vs', 'WHERE', 'vs.scrutinio=s.id AND vs.alunno=a.id')
      ->join('AppBundle:PropostaVoto', 'pv', 'WHERE', 'pv.classe=s.classe AND pv.periodo=s.periodo AND pv.alunno=a.id')
      ->join('vs.materia', 'm')
      ->where('a.id in (:lista) AND e.esito IN (:esiti) AND s.classe=:classe AND s.periodo=:periodo AND vs.materia=pv.materia AND pv.unico<:suff AND vs.unico>=:suff AND m.tipo=:tipo')
      ->orderBy('a.cognome,a.nome,a.dataNascita,m.ordinamento', 'ASC')
      ->setParameters(['lista' => $lista, 'esiti' => ['A', 'S'], 'classe' => $classe, 'periodo' => $periodo,
        'suff' => 6, 'tipo' => 'N'])
      ->getQuery()
      ->getArrayResult();
    foreach ($alunni as $alu) {
      $dati['carenze'][$alu['id']][$alu['materia_id']] = $alu;
      if (!isset($dati['esiti'][$alu['id']])) {
        // legge esito
        $dati['esiti'][$alu['id']] = $this->em->getRepository('AppBundle:Esito')->find($alu['esito']);
      }
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Esegue il passaggio di stato 6->5 per lo scrutinio del periodo F
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_F_6_5(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // legge alunni
    $lista = $this->alunniInScrutinio($classe, 'F');
    // legge esiti
    $esiti = $this->em->getRepository('AppBundle:Esito')->createQueryBuilder('e')
      ->where('e.alunno IN (:lista) AND e.scrutinio=:scrutinio AND e.esito IN (:esiti)')
      ->setParameters(['lista' => $lista, 'scrutinio' => $scrutinio, 'esiti' => ['A','S']])
      ->getQuery()
      ->getResult();
    // cancella conferme comunicazioni
    foreach ($esiti as $e) {
      $valori = $e->getDati();
      $valori['debiti'] = false;
      $valori['carenze'] = false;
      $e->setDati($valori);
    }
    // aggiorna stato
    $scrutinio->setStato('5');
    $this->em->flush();
    // log
    $this->dblogger->write($docente, $request->getClientIp(), 'SCRUTINIO', 'Cambio stato', __METHOD__, array(
      'Scrutinio' => $scrutinio->getId(),
      'Classe' => $classe->getId(),
      'Periodo' => 'F',
      'Stato iniziale' => '6',
      'Stato finale' => '5',
      ));
    // ok
    return true;
  }

  /**
   * Restituisce i dati dei debiti per l'alunno indicato
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Alunno $alunno Alunno dello scrutinio
   * @param string $periodo Periodo relativo allo scrutinio
   *
   * @return array Dati formattati come un array associativo
   */
  public function elencoDebitiAlunno(Docente $docente, Alunno $alunno, $periodo) {
    $dati = array();
    // alunni della classe
    $lista_id = $this->alunniInScrutinio($alunno->getClasse(), $periodo);
    if (!in_array($alunno->getId(), $lista_id)) {
      // errore: alunno non previsto
      return null;
    }
    $dati['debiti'] = $this->em->getRepository('AppBundle:VotoScrutinio')->createQueryBuilder('vs')
      ->join('vs.scrutinio', 's')
      ->join('vs.materia', 'm')
      ->join('AppBundle:Esito', 'e', 'WHERE', 'e.alunno=vs.alunno AND e.scrutinio=s.id')
      ->where('vs.alunno=:alunno AND vs.unico<:suff AND s.classe=:classe AND s.periodo=:periodo AND m.tipo=:tipo AND e.esito=:sospeso')
      ->orderBy('m.ordinamento', 'ASC')
      ->setParameters(['alunno' => $alunno, 'suff' => 6, 'classe' => $alunno->getClasse(), 'periodo' => $periodo,
        'tipo' => 'N', 'sospeso' => 'S'])
      ->getQuery()
      ->getResult();
    // restituisce dati
    return $dati;
  }

  /**
   * Restituisce i dati delle carenze per l'alunno indicato
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Alunno $alunno Alunno dello scrutinio
   * @param string $periodo Periodo relativo allo scrutinio
   *
   * @return array Dati formattati come un array associativo
   */
  public function elencoCarenzeAlunno(Docente $docente, Alunno $alunno, $periodo) {
    $dati = array();
    // alunni della classe
    $lista_id = $this->alunniInScrutinio($alunno->getClasse(), $periodo);
    if (!in_array($alunno->getId(), $lista_id)) {
      // errore: alunno non previsto
      return null;
    }
    // legge carenze
    $dati['carenze'] = $this->em->getRepository('AppBundle:VotoScrutinio')->createQueryBuilder('vs')
      ->join('vs.scrutinio', 's')
      ->join('vs.materia', 'm')
      ->join('AppBundle:Esito', 'e', 'WHERE', 'e.alunno=vs.alunno AND e.scrutinio=s.id')
      ->join('AppBundle:PropostaVoto', 'pv', 'WHERE', 'pv.alunno=vs.alunno AND pv.classe=s.classe AND pv.periodo=s.periodo')
      ->where('vs.alunno=:alunno AND s.classe=:classe AND s.periodo=:periodo AND m.tipo=:tipo AND e.esito IN (:esiti) AND vs.materia=pv.materia AND pv.unico<:suff AND vs.unico>=:suff')
      ->orderBy('m.ordinamento', 'ASC')
      ->setParameters(['alunno' => $alunno, 'classe' => $alunno->getClasse(), 'periodo' => $periodo,
        'tipo' => 'N', 'esiti' => ['A','S'], 'suff' => 6])
      ->getQuery()
      ->getResult();
    // aggiunge proposte
    foreach ($dati['carenze'] as $voto) {
      $proposta = $this->em->getRepository('AppBundle:PropostaVoto')->createQueryBuilder('pv')
        ->join('pv.materia', 'm')
        ->where('pv.alunno=:alunno AND pv.classe=:classe AND pv.periodo=:periodo AND m.tipo=:tipo AND m.id=:materia')
        ->setParameters(['alunno' => $alunno, 'classe' => $alunno->getClasse(), 'periodo' => $periodo,
          'tipo' => 'N', 'materia' => $voto->getMateria()])
        ->getQuery()
        ->setMaxResults(1)
        ->getOneOrNullResult();
      $dati['proposte'][$voto->getMateria()->getId()] = $proposta->getUnico();
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Esegue il passaggio di stato 6->7 per lo scrutinio del periodo F
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_F_6_7(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // inizializza
    $errore = array();
    $this->session->getFlashBag()->clear();
    // distingue per classe
    if ($classe->getAnno() != 5) {
      // legge comunicazioni
      $dati = $this->quadroComunicazioni($docente, $classe, 'F');
      // controllo debiti
      foreach ($dati['debiti'] as $alu=>$d) {
        $valori = $dati['esiti'][$alu]->getDati();
        if (!isset($valori['debiti']) || !$valori['debiti']) {
          foreach ($d as $mat=>$v) {
            $nome = $v['cognome'].' '.$v['nome'];
            $sesso = ($v['sesso'] == 'M' ? 'o' : 'a');
            $errore[] = $this->trans->trans('exception.no_comunicazione_debiti', ['%sex%' => $sesso, '%alunno%' => $nome]);
            break;
          }
        }
      }
      // controllo carenze
      foreach ($dati['carenze'] as $alu=>$d) {
        $valori = $dati['esiti'][$alu]->getDati();
        if (!isset($valori['carenze']) || !$valori['carenze']) {
          foreach ($d as $mat=>$v) {
            $nome = $v['cognome'].' '.$v['nome'];
            $sesso = ($v['sesso'] == 'M' ? 'o' : 'a');
            $errore[] = $this->trans->trans('exception.no_comunicazione_carenze', ['%sex%' => $sesso, '%alunno%' => $nome]);
            break;
          }
        }
      }
    }
    if (empty($errore)) {
      // aggiorna stato
      $scrutinio->setStato('7');
      $this->em->flush();
      // log
      $this->dblogger->write($docente, $request->getClientIp(), 'SCRUTINIO', 'Cambio stato', __METHOD__, array(
        'Scrutinio' => $scrutinio->getId(),
        'Classe' => $classe->getId(),
        'Periodo' => 'F',
        'Stato iniziale' => '6',
        'Stato finale' => '7',
        ));
      // ok
      return true;
    }
    // imposta messaggi di errore
    foreach ($errore as $msg) {
      $this->session->getFlashBag()->add('errore', $msg);
    }
    // errori presenti
    return false;
  }

  /**
   * Esegue il passaggio di stato 7->6 per lo scrutinio del periodo F
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_F_7_6(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // aggiorna stato
    $scrutinio->setStato('6');
    $this->em->flush();
    // log
    $this->dblogger->write($docente, $request->getClientIp(), 'SCRUTINIO', 'Cambio stato', __METHOD__, array(
      'Scrutinio' => $scrutinio->getId(),
      'Classe' => $classe->getId(),
      'Periodo' => 'F',
      'Stato iniziale' => '7',
      'Stato finale' => '6',
      ));
    // ok
    return true;
  }

  /**
   * Esegue il passaggio di stato 7->C per lo scrutinio del periodo P
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_F_7_C(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // inizializza messaggi di errore
    $this->session->getFlashBag()->clear();
    // legge dati form
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // controlli
      if (!$form->get('fine')->getData()) {
        // ora non presente
        $this->session->getFlashBag()->add('errore', 'exception.scrutinio_fine');
      }
      // se niente errori cambia stato
      if (!$this->session->getFlashBag()->has('errore')) {
        // imposta dati
        $scrutinio->setFine($form->get('fine')->getData());
        // aggiorna stato
        $scrutinio->setStato('C');
        $this->em->flush();
        // log
        $this->dblogger->write($docente, $request->getClientIp(), 'SCRUTINIO', 'Cambio stato', __METHOD__, array(
          'Scrutinio' => $scrutinio->getId(),
          'Classe' => $classe->getId(),
          'Periodo' => 'F',
          'Stato iniziale' => '7',
          'Stato finale' => 'C',
          ));
        // ok
        return true;
      }
    } else {
      // imposta messaggi per eventuali altri errori del form
      foreach ($form->getErrors() as $error) {
        $this->session->getFlashBag()->add('errore', $error->getMessage());
      }
    }
    // errore
    return false;
  }

  /**
   * Esegue il passaggio di stato C->7 per lo scrutinio del periodo F
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_F_C_7(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // controlla se è possibile riapertura
    if (!($docente instanceOf Staff) || $scrutinio->getSincronizzazione()) {
      // errore
      return false;
    }
    // inizializza messaggi di errore
    $this->session->getFlashBag()->clear();
    // rinomina documenti di classe
    $fs = new Filesystem();
    $finder = new Finder();
    $percorso = $this->root.$this->session->get('/CONFIG/SISTEMA/dir_scrutini').'/finale/'.
      $classe->getAnno().$classe->getSezione();
    $num = 0;
    while ($fs->exists($percorso.'/BACKUP.'.$num)) {
      $num++;
    }
    $fs->mkdir($percorso.'/BACKUP.'.$num, 0775);
    $finder->files()->in($percorso)->depth('== 0');
    foreach ($finder as $file) {
      // sposta in directory
      $fs->rename($file->getRealPath(), $percorso.'/BACKUP.'.$num.'/'.$file->getBasename());
    }
    // aggiorna stato
    $scrutinio->setStato('7');
    $this->em->flush();
    // log
    $this->dblogger->write($docente, $request->getClientIp(), 'SCRUTINIO', 'Cambio stato', __METHOD__, array(
      'Scrutinio' => $scrutinio->getId(),
      'Classe' => $classe->getId(),
      'Periodo' => 'F',
      'Stato iniziale' => 'C',
      'Stato finale' => '7',
      ));
    // ok
    return true;
  }

  /**
   * Restituisce la situazione dei voti dello scrutinio finale per gli alunni sospesi
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Classe $classe Classe relativa alle proposte di voto
   * @param string $periodo Periodo relativo allo scrutinio
   *
   * @return array Dati formattati come un array associativo
   */
  public function riepilogoSospesi(Docente $docente, Classe $classe, $periodo) {
    $dati = array();
    $dati['alunni'] = array();
    // legge alunni
    $lista = $this->alunniInScrutinio($classe, $periodo);
    $alunni = $this->em->getRepository('AppBundle:Alunno')->createQueryBuilder('a')
      ->select('a.id,a.nome,a.cognome,a.dataNascita,a.religione,a.bes,a.note')
      ->where('a.id in (:lista)')
      ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
      ->setParameters(['lista' => $lista])
      ->getQuery()
      ->getResult();
    foreach ($alunni as $alu) {
      $dati['alunni'][$alu['id']] = $alu;
    }
    // legge materie
    $materie = $this->em->getRepository('AppBundle:Materia')->createQueryBuilder('m')
      ->select('DISTINCT m.id,m.nome,m.nomeBreve,m.tipo')
      ->join('AppBundle:Cattedra', 'c', 'WHERE', 'c.materia=m.id')
      ->where('c.classe=:classe AND c.attiva=:attiva AND c.tipo=:tipo')
      ->orderBy('m.ordinamento', 'ASC')
      ->setParameters(['classe' => $classe, 'attiva' => 1, 'tipo' => 'N'])
      ->getQuery()
      ->getArrayResult();
    foreach ($materie as $mat) {
      $dati['materie'][$mat['id']] = $mat;
    }
    $condotta = $this->em->getRepository('AppBundle:Materia')->findOneByTipo('C');
    $dati['materie'][$condotta->getId()] = array(
      'id' => $condotta->getId(),
      'nome' => $condotta->getNome(),
      'nomeBreve' => $condotta->getNomeBreve(),
      'tipo' => $condotta->getTipo());
    // legge i voti dello scrutinio finale
    $voti = $this->em->getRepository('AppBundle:VotoScrutinio')->createQueryBuilder('vs')
      ->join('vs.scrutinio', 's')
      ->where('s.classe=:classe AND s.periodo=:periodo AND vs.alunno IN (:lista) AND vs.unico IS NOT NULL')
      ->setParameters(['classe' => $classe, 'periodo' => 'F', 'lista' => $lista])
      ->getQuery()
      ->getResult();
    $somma = array();
    foreach ($voti as $v) {
      // inserisce voti
      $dati['voti'][$v->getAlunno()->getId()][$v->getMateria()->getId()] = array(
        'id' => $v->getId(),
        'unico' => $v->getUnico(),
        'recupero' => $v->getRecupero(),
        'debito' => $v->getDebito(),
        'assenze' => $v->getAssenze(),
        'dati' => $v->getDati());
      if ($v->getMateria()->getMedia()) {
        // esclude religione dalla media
        if (!isset($somma[$v->getAlunno()->getId()])) {
          $somma[$v->getAlunno()->getId()] =
            ($v->getMateria()->getTipo() == 'C' && $v->getUnico() == 4) ? 0 : $v->getUnico();
          $numero[$v->getAlunno()->getId()] = 1;
        } else {
          $somma[$v->getAlunno()->getId()] +=
            ($v->getMateria()->getTipo() == 'C' && $v->getUnico() == 4) ? 0 : $v->getUnico();
          $numero[$v->getAlunno()->getId()]++;
        }
      }
    }
    // calcola medie
    foreach ($somma as $alu=>$s) {
      $dati['medie'][$alu] = number_format($somma[$alu] / $numero[$alu], 2, ',', null);
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Esegue il passaggio di stato N->1 per lo scrutinio del periodo R
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_R_N_1(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    $this->session->getFlashBag()->clear();
    // legge dati
    $dati = $this->riepilogoSospesi($docente, $classe, 'R');
    // inserimento voti
    foreach ($dati['alunni'] as $alunno=>$alu) {
      foreach ($dati['materie'] as $materia=>$mat) {
        // esclude alunni NA per religione
        if ($mat['tipo'] != 'R' || $alu['religione'] == 'S') {
          // inserisce voti e assenze
          $this->em->getConnection()
            ->prepare('INSERT INTO gs_voto_scrutinio '.
              '(scrutinio_id, alunno_id, materia_id, modificato, unico, debito, recupero, assenze, dati) '.
              'VALUES (:scrutinio,:alunno,:materia,NOW(),:unico,:debito,:recupero,:assenze,:dati)')
            ->execute(['scrutinio' => $scrutinio->getId(), 'alunno' => $alunno, 'materia' => $materia,
              'unico' => $dati['voti'][$alunno][$materia]['unico'],
              'debito' => $dati['voti'][$alunno][$materia]['debito'],
              'recupero' => $dati['voti'][$alunno][$materia]['recupero'],
              'assenze' => $dati['voti'][$alunno][$materia]['assenze'],
              'dati' => serialize($dati['voti'][$alunno][$materia]['dati'])]);
        }
      }
    }
    // legge dati alunni
    $scrutinio_finale = $this->em->getRepository('AppBundle:Scrutinio')->createQueryBuilder('s')
      ->where('s.periodo=:periodo AND s.classe=:classe')
      ->setParameters(['periodo' => 'F', 'classe' => $classe])
      ->setMaxResults(1)
      ->getQuery()
      ->getOneOrNullResult();
    $dati_alunni = $scrutinio_finale->getDati()['alunni'];
    // aggiorna dati alunni
    $valori = $scrutinio->getDati();
    $valori['alunni'] = $dati_alunni;
    $scrutinio->setDati($valori);
    // aggiorna stato
    $scrutinio->setStato('1');
    $this->em->flush();
    // log
    $this->dblogger->write($docente, $request->getClientIp(), 'SCRUTINIO', 'Cambio stato', __METHOD__, array(
      'Scrutinio' => $scrutinio->getId(),
      'Classe' => $classe->getId(),
      'Periodo' => 'R',
      'Stato iniziale' => 'N',
      'Stato finale' => '1',
      ));
    // ok
    return true;
  }

  /**
   * Esegue il passaggio di stato 1->N per lo scrutinio del periodo F
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_R_1_N(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // controlla se docente fa parte di staff
    if (!($docente instanceOf Staff)) {
      // errore
      return false;
    }
    // inizializza messaggi di errore
    $this->session->getFlashBag()->clear();
    // cancella voti
    $this->em->getConnection()
      ->prepare("DELETE FROM gs_voto_scrutinio WHERE scrutinio_id=:scrutinio")
      ->execute(['scrutinio' => $scrutinio->getId()]);
    // aggiorna stato
    $scrutinio->setStato('N');
    $this->em->flush();
    // log
    $this->dblogger->write($docente, $request->getClientIp(), 'SCRUTINIO', 'Cambio stato', __METHOD__, array(
      'Scrutinio' => $scrutinio->getId(),
      'Classe' => $classe->getId(),
      'Periodo' => 'R',
      'Stato iniziale' => '1',
      'Stato finale' => 'N',
      ));
    // ok
    return true;
  }

  /**
   * Esegue il passaggio di stato 1->2 per lo scrutinio del periodo R
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_R_1_2(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // inizializza messaggi di errore
    $this->session->getFlashBag()->clear();
    // legge dati form
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // controlli
      if (!$form->get('data')->getData()) {
        // data non presente
        $this->session->getFlashBag()->add('errore', 'exception.scrutinio_data');
      }
      if (!$form->get('inizio')->getData()) {
        // ora non presente
        $this->session->getFlashBag()->add('errore', 'exception.scrutinio_inizio');
      }
      if ($form->get('presiede_ds')->getData() === null) {
        // presidente ds non presente
        $this->session->getFlashBag()->add('errore', 'exception.scrutinio_presidente');
      }
      if ($form->get('presiede_ds')->getData() === false && !$form->get('presiede_docente')->getData()) {
        // presidente docente non presente
        $this->session->getFlashBag()->add('errore', 'exception.scrutinio_presidente');
      }
      if (!$form->get('segretario')->getData()) {
        // segretario non presente
        $this->session->getFlashBag()->add('errore', 'exception.scrutinio_segretario');
      }
      // controlli sui presenti
      $errore_presenza = false;
      foreach ($form->get('lista')->getData() as $doc=>$val) {
        if (!$val || (!$val->getPresenza() && !$val->getSostituto())) {
          $errore_presenza = true;
        }
      }
      if ($errore_presenza) {
        // docente non presente
        $this->session->getFlashBag()->add('errore', 'exception.scrutinio_presenza');
      }
      // se niente errori cambia stato
      if (!$this->session->getFlashBag()->has('errore')) {
        // imposta dati
        $scrutinio->setData($form->get('data')->getData());
        $scrutinio->setInizio($form->get('inizio')->getData());
        $valori = $scrutinio->getDati();
        $scrutinio->setDati(array());   // necessario per bug di aggiornamento
        $this->em->flush();             // necessario per bug di aggiornamento
        $valori['presenze'] = $form->get('lista')->getData();
        $valori['presiede_ds'] = $form->get('presiede_ds')->getData();
        $valori['presiede_docente'] = $form->get('presiede_docente')->getData();
        $valori['segretario'] = $form->get('segretario')->getData();
        $scrutinio->setDati($valori);
        // aggiorna stato
        $scrutinio->setStato('2');
        $this->em->flush();
        // log
        $this->dblogger->write($docente, $request->getClientIp(), 'SCRUTINIO', 'Cambio stato', __METHOD__, array(
          'Scrutinio' => $scrutinio->getId(),
          'Classe' => $classe->getId(),
          'Periodo' => 'R',
          'Stato iniziale' => '1',
          'Stato finale' => '2',
          ));
        // ok
        return true;
      }
    } else {
      // imposta messaggi per eventuali altri errori del form
      foreach ($form->getErrors() as $error) {
        $this->session->getFlashBag()->add('errore', $error->getMessage());
      }
    }
    // errore
    return false;
  }

  /**
   * Esegue il passaggio di stato 2->1 per lo scrutinio del periodo F
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_R_2_1(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // inizializza messaggi di errore
    $this->session->getFlashBag()->clear();
    // aggiorna stato
    $scrutinio->setStato('1');
    $this->em->flush();
    // log
    $this->dblogger->write($docente, $request->getClientIp(), 'SCRUTINIO', 'Cambio stato', __METHOD__, array(
      'Scrutinio' => $scrutinio->getId(),
      'Classe' => $classe->getId(),
      'Periodo' => 'R',
      'Stato iniziale' => '2',
      'Stato finale' => '1',
      ));
    // ok
    return true;
  }

  /**
   * Restituisce la lista dei voti dello scrutinio per l'alunno indicato
   *
   * @param Docente $docente Docente che esegue la lettura
   * @param Alunno $alunno Alunno di cui restituire i voti
   * @param string $periodo Periodo relativo allo scrutinio
   *
   * @return array Dati formattati come un array associativo
   */
  public function elencoVotiAlunnoSospeso(Docente $docente, Alunno $alunno, $periodo) {
    $dati = array();
    $dati['voti'] = array();
    // alunni della classe
    $lista_id = $this->alunniInScrutinio($alunno->getClasse(), $periodo);
    if (!in_array($alunno->getId(), $lista_id)) {
      // errore: alunno non previsto
      return null;
    }
    // legge scrutinio
    $scrutinio = $this->em->getRepository('AppBundle:Scrutinio')->createQueryBuilder('s')
      ->where('s.classe=:classe AND s.periodo=:periodo')
      ->setParameters(['classe' => $alunno->getClasse(), 'periodo' => $periodo])
      ->setMaxResults(1)
      ->getQuery()
      ->getOneOrNullResult();
    $dati['scrutinio'] = $scrutinio;
    // legge materie
    $materie = $this->em->getRepository('AppBundle:Materia')->createQueryBuilder('m')
      ->select('DISTINCT m.id,m.nome,m.nomeBreve,m.tipo')
      ->join('AppBundle:Cattedra', 'c', 'WHERE', 'c.materia=m.id')
      ->where('c.classe=:classe AND c.attiva=:attiva AND c.tipo=:tipo')
      ->orderBy('m.ordinamento', 'ASC')
      ->setParameters(['classe' => $alunno->getClasse(), 'attiva' => 1, 'tipo' => 'N'])
      ->getQuery()
      ->getArrayResult();
    foreach ($materie as $mat) {
      $dati['materie'][$mat['id']] = $mat;
    }
    $condotta = $this->em->getRepository('AppBundle:Materia')->findOneByTipo('C');
    $dati['materie'][$condotta->getId()] = array(
      'id' => $condotta->getId(),
      'nome' => $condotta->getNome(),
      'nomeBreve' => $condotta->getNomeBreve(),
      'tipo' => $condotta->getTipo());
    // legge solo i voti con debito
    $voti = $this->em->getRepository('AppBundle:VotoScrutinio')->createQueryBuilder('vs')
      ->join('vs.materia', 'm')
      ->join('AppBundle:Scrutinio', 's', 'WHERE', 's.classe=:classe AND s.periodo=:periodo')
      ->join('AppBundle:VotoScrutinio', 'vsf', 'WHERE', 'vsf.scrutinio=s.id AND vsf.materia=m.id AND vsf.alunno=:alunno')
      ->where('vs.scrutinio=:scrutinio AND vs.alunno=:alunno AND vsf.unico<:suff')
      ->orderBy('m.ordinamento', 'ASC')
      ->setParameters(['scrutinio' => $scrutinio, 'alunno' => $alunno,
        'classe' => $alunno->getClasse(), 'periodo' => 'F', 'suff' => 6])
      ->getQuery()
      ->getResult();
    foreach ($voti as $v) {
      // inserisce voti
      $dati['voti'][$v->getMateria()->getId()] = $v;
    }
    // legge esito
    $esito = $this->em->getRepository('AppBundle:Esito')->findOneBy(['scrutinio' => $scrutinio, 'alunno' => $alunno]);
    if (!$esito) {
      // crea nuovo esito
      $dati_esito = array(
        'unanimita' => true,
        'contrari' => null,
        'giudizio' => null);
      $esito = (new Esito())
        ->setScrutinio($scrutinio)
        ->setAlunno($alunno)
        ->setDati($dati_esito);
      $this->em->persist($esito);
    }
    $dati['esito'] = $esito;
    // restituisce dati
    return $dati;
  }

  /**
   * Esegue il passaggio di stato 2->3 per lo scrutinio del periodo R
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_R_2_3(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // inizializza
    $this->session->getFlashBag()->clear();
    $errore = array();
    $valutazioni['F']['N'] = ['min' => 0, 'max' => 10, 'start' => 6, 'ticks' => '0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10', 'labels' => '"NC", 1, 2, 3, 4, 5, 6, 7, 8, 9, 10'];
    $valutazioni['F']['C'] = ['min' => 4, 'max' => 10, 'start' => 6, 'ticks' => '4, 5, 6, 7, 8, 9, 10', 'labels' => '"NC", 5, 6, 7, 8, 9, 10'];
    $valutazioni['F']['R'] = ['min' => 20, 'max' => 25, 'start' => 22, 'ticks' => '20, 21, 22, 23, 24, 25', 'labels' => '"NC", "Insuff.", "Suff.", "Buono", "Dist.", "Ottimo"'];
    // alunni della classe
    $lista_id = $this->alunniInScrutinio($classe, 'R');
    foreach ($lista_id as $id) {
      // recupera alunno
      $alunno = $this->em->getRepository('AppBundle:Alunno')->find($id);
      $sesso = ($alunno->getSesso() == 'M' ? 'o' : 'a');
      $nome = $alunno->getCognome().' '.$alunno->getNome();
      // elenco voti dell'alunno
      $dati = $this->elencoVotiAlunnoSospeso($docente, $alunno, 'R');
      // controlla errori
      $no_voto = 0;
      $insuff_cont = 0;
      $insuff_religione = false;
      $insuff_condotta = false;
      foreach ($dati['voti'] as $key=>$voto) {
        // controllo voto
        if ($voto->getUnico() === null || $voto->getUnico() < $valutazioni['F'][$voto->getMateria()->getTipo()]['min'] ||
            $voto->getUnico() > $valutazioni['F'][$voto->getMateria()->getTipo()]['max']) {
          // voto non ammesso o non presente
          $no_voto++;
        } elseif ($voto->getMateria()->getTipo() == 'R' && $voto->getUnico() < $valutazioni['F']['R']['start']) {
          // voto religione insufficiente
          $insuff_religione = true;
          $insuff_cont++;
        } elseif ($voto->getMateria()->getTipo() == 'C' && $voto->getUnico() < $valutazioni['F']['C']['start']) {
          // voto condotta insufficiente
          $insuff_condotta = true;
          $insuff_cont++;
        } elseif ($voto->getUnico() < $valutazioni['F'][$voto->getMateria()->getTipo()]['start']) {
          // voto insufficiente
          $insuff_cont++;
        }
      }
      if ($no_voto > 0) {
        // voti non presenti
        $errore[] = $this->trans->trans('exception.no_voto_esito', ['%sex%' => $sesso, '%alunno%' => $nome]);
      }
      if ($dati['esito']->getEsito() === null) {
        // manca esito
        $errore[] = $this->trans->trans('exception.manca_esito', ['%sex%' => $sesso, '%alunno%' => $nome]);
      }
      if ($dati['esito']->getDati()['unanimita'] === null) {
        // manca delibera
        $errore[] = $this->trans->trans('exception.delibera_esito', ['%sex%' => $sesso, '%alunno%' => $nome]);
      } elseif ($dati['esito']->getDati()['unanimita'] === false && !$dati['esito']->getDati()['contrari']) {
        // mancano contrari
        $errore[] = $this->trans->trans('exception.contrari_esito', ['%sex%' => $sesso, '%alunno%' => $nome]);
      }
      if ($dati['esito']->getEsito() == 'N' && !$dati['esito']->getDati()['giudizio']) {
        // manca giudizio
        $errore[] = $this->trans->trans('exception.giudizio_esito', ['%sex%' => $sesso, '%alunno%' => $nome]);
      }
      if ($dati['esito']->getEsito() == 'A' && $insuff_cont > 0) {
        // insufficienze con ammissione
        $errore[] = $this->trans->trans('exception.insufficienze_ammissione_esito', ['%sex%' => $sesso, '%alunno%' => $nome]);
      }
      if ($dati['esito']->getEsito() == 'N' && $insuff_cont == 0) {
        // solo sufficienze con non ammissione
        $errore[] = $this->trans->trans('exception.sufficienze_non_ammissione_esito', ['%sex%' => $sesso, '%alunno%' => $nome]);
      }
      if ($dati['esito']->getEsito() == 'S' && $insuff_cont == 0) {
        // solo sufficienze con sospensione
        $errore[] = $this->trans->trans('exception.sufficienze_sospensione_esito', ['%sex%' => $sesso, '%alunno%' => $nome]);
      }
      if ($dati['esito']->getEsito() != 'N' && $insuff_religione) {
        // insuff. religione incoerente con esito
        $errore[] = $this->trans->trans('exception.voto_religione_esito', ['%sex%' => $sesso, '%alunno%' => $nome]);
      }
      if ($dati['esito']->getEsito() != 'N' && $insuff_condotta) {
        // insuff. condotta incoerente con esito
        $errore[] = $this->trans->trans('exception.voto_condotta_esito', ['%sex%' => $sesso, '%alunno%' => $nome]);
      }
      if ($dati['esito']->getEsito() == 'S' && $alunno->getClasse()->getAnno() == 5) {
        // sospensione in quinta
        $errore[] = $this->trans->trans('exception.exception.quinta_sospeso_esito', ['%sex%' => $sesso, '%alunno%' => $nome]);
      }
    }
    if (empty($errore)) {
      // aggiorna stato
      $scrutinio->setStato('3');
      $this->em->flush();
      // log
      $this->dblogger->write($docente, $request->getClientIp(), 'SCRUTINIO', 'Cambio stato', __METHOD__, array(
        'Scrutinio' => $scrutinio->getId(),
        'Classe' => $classe->getId(),
        'Periodo' => 'R',
        'Stato iniziale' => '2',
        'Stato finale' => '3',
        ));
      // ok
      return true;
    }
    // imposta messaggi di errore
    foreach ($errore as $msg) {
      $this->session->getFlashBag()->add('errore', $msg);
    }
    // errori presenti
    return false;
  }

  /**
   * Esegue il passaggio di stato 3->2 per lo scrutinio del periodo R
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_R_3_2(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // cancella medie
    $this->em->getConnection()
      ->prepare("UPDATE gs_esito SET media=NULL,credito=NULL WHERE scrutinio_id=:scrutinio")
      ->execute(['scrutinio' => $scrutinio->getId()]);
    // aggiorna stato
    $scrutinio->setStato('2');
    $this->em->flush();
    // log
    $this->dblogger->write($docente, $request->getClientIp(), 'SCRUTINIO', 'Cambio stato', __METHOD__, array(
      'Scrutinio' => $scrutinio->getId(),
      'Classe' => $classe->getId(),
      'Periodo' => 'R',
      'Stato iniziale' => '3',
      'Stato finale' => '2',
      ));
    // ok
    return true;
  }

  /**
   * Esegue il passaggio di stato 5->6 per lo scrutinio del periodo F
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_R_3_4(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // inizializza
    $errore = array();
    $this->session->getFlashBag()->clear();
    // alunni della classe
    $lista_id = $this->alunniInScrutinio($classe, 'R');
    // distingue per classe
    if ($classe->getAnno() == 2) {
      // competenze
      $competenze = $this->em->getRepository('AppBundle:Esito')->createQueryBuilder('e')
        ->select('a.cognome,a.nome,a.sesso,a.dataNascita,e.dati')
        ->join('e.alunno', 'a')
        ->where('e.scrutinio=:scrutinio AND e.alunno IN (:lista) AND e.esito=:ammesso')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
        ->setParameters(['scrutinio' => $scrutinio, 'lista' => $lista_id, 'ammesso' => 'A'])
        ->getQuery()
        ->getArrayResult();
      foreach ($competenze as $c) {
        if (!isset($c['dati']['certificazione']) || !$c['dati']['certificazione']) {
          $nome = $c['cognome'].' '.$c['nome'];
          $sesso = ($c['sesso'] == 'M' ? 'o' : 'a');
          $errore[] = $this->trans->trans('exception.no_certificazione_esito', ['%sex%' => $sesso, '%alunno%' => $nome]);
        }
      }
    } elseif ($classe->getAnno() != 1) {
      // crediti
      $crediti = $this->em->getRepository('AppBundle:Esito')->createQueryBuilder('e')
        ->select('a.cognome,a.nome,a.sesso,a.dataNascita')
        ->join('e.alunno', 'a')
        ->where('e.scrutinio=:scrutinio AND e.alunno IN (:lista) AND e.esito=:ammesso AND e.credito IS NULL')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
        ->setParameters(['scrutinio' => $scrutinio, 'lista' => $lista_id, 'ammesso' => 'A'])
        ->getQuery()
        ->getArrayResult();
      foreach ($crediti as $c) {
        $nome = $c['cognome'].' '.$c['nome'];
        $sesso = ($c['sesso'] == 'M' ? 'o' : 'a');
        $errore[] = $this->trans->trans('exception.no_credito_esito', ['%sex%' => $sesso, '%alunno%' => $nome]);
      }
    }
    if (empty($errore)) {
      // aggiorna stato
      $scrutinio->setStato('4');
      $this->em->flush();
      // log
      $this->dblogger->write($docente, $request->getClientIp(), 'SCRUTINIO', 'Cambio stato', __METHOD__, array(
        'Scrutinio' => $scrutinio->getId(),
        'Classe' => $classe->getId(),
        'Periodo' => 'R',
        'Stato iniziale' => '3',
        'Stato finale' => '4',
        ));
      // ok
      return true;
    }
    // imposta messaggi di errore
    foreach ($errore as $msg) {
      $this->session->getFlashBag()->add('errore', $msg);
    }
    // errori presenti
    return false;
  }

  /**
   * Esegue il passaggio di stato 4->3 per lo scrutinio del periodo R
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_R_4_3(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // aggiorna stato
    $scrutinio->setStato('3');
    $this->em->flush();
    // log
    $this->dblogger->write($docente, $request->getClientIp(), 'SCRUTINIO', 'Cambio stato', __METHOD__, array(
      'Scrutinio' => $scrutinio->getId(),
      'Classe' => $classe->getId(),
      'Periodo' => 'R',
      'Stato iniziale' => '4',
      'Stato finale' => '3',
      ));
    // ok
    return true;
  }

  /**
   * Esegue il passaggio di stato 4->C per lo scrutinio del periodo R
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_R_4_C(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // inizializza messaggi di errore
    $this->session->getFlashBag()->clear();
    // legge dati form
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // controlli
      if (!$form->get('fine')->getData()) {
        // ora non presente
        $this->session->getFlashBag()->add('errore', 'exception.scrutinio_fine');
      }
      // se niente errori cambia stato
      if (!$this->session->getFlashBag()->has('errore')) {
        // imposta dati
        $scrutinio->setFine($form->get('fine')->getData());
        // aggiorna stato
        $scrutinio->setStato('C');
        $this->em->flush();
        // log
        $this->dblogger->write($docente, $request->getClientIp(), 'SCRUTINIO', 'Cambio stato', __METHOD__, array(
          'Scrutinio' => $scrutinio->getId(),
          'Classe' => $classe->getId(),
          'Periodo' => 'R',
          'Stato iniziale' => '4',
          'Stato finale' => 'C',
          ));
        // ok
        return true;
      }
    } else {
      // imposta messaggi per eventuali altri errori del form
      foreach ($form->getErrors() as $error) {
        $this->session->getFlashBag()->add('errore', $error->getMessage());
      }
    }
    // errore
    return false;
  }

  /**
   * Esegue il passaggio di stato C->4 per lo scrutinio del periodo R
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_R_C_4(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // controlla se è possibile riapertura
    if (!($docente instanceOf Staff) || $scrutinio->getSincronizzazione()) {
      // errore
      return false;
    }
    // inizializza messaggi di errore
    $this->session->getFlashBag()->clear();
    // rinomina documenti di classe
    $fs = new Filesystem();
    $finder = new Finder();
    $percorso = $this->root.$this->session->get('/CONFIG/SISTEMA/dir_scrutini').'/ripresa/'.
      $classe->getAnno().$classe->getSezione();
    $num = 0;
    while ($fs->exists($percorso.'/BACKUP.'.$num)) {
      $num++;
    }
    $fs->mkdir($percorso.'/BACKUP.'.$num, 0775);
    $finder->files()->in($percorso)->depth('== 0');
    foreach ($finder as $file) {
      // sposta in directory
      $fs->rename($file->getRealPath(), $percorso.'/BACKUP.'.$num.'/'.$file->getBasename());
    }
    // aggiorna stato
    $scrutinio->setStato('4');
    $this->em->flush();
    // log
    $this->dblogger->write($docente, $request->getClientIp(), 'SCRUTINIO', 'Cambio stato', __METHOD__, array(
      'Scrutinio' => $scrutinio->getId(),
      'Classe' => $classe->getId(),
      'Periodo' => 'R',
      'Stato iniziale' => 'C',
      'Stato finale' => '4',
      ));
    // ok
    return true;
  }

}
