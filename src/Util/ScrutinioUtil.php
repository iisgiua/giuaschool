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


namespace App\Util;

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
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\Form;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;
use App\Entity\Alunno;
use App\Entity\Classe;
use App\Entity\Materia;
use App\Entity\Docente;
use App\Entity\PropostaVoto;
use App\Entity\Scrutinio;
use App\Entity\VotoScrutinio;
use App\Entity\Staff;
use App\Entity\Preside;
use App\Entity\Esito;
use App\Entity\DefinizioneScrutinio;
use App\Util\LogHandler;
use App\Form\ScrutinioPresenza;
use App\Form\ScrutinioPresenzaType;
use App\Form\ScrutinioAssenza;
use App\Form\ScrutinioAssenzaType;
use App\Form\AlunnoType;


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
    $lista = array();
    // legge definizione scrutini
    $periodi = $this->em->getRepository('App:DefinizioneScrutinio')->createQueryBuilder('d')
      ->select('d.periodo,s.stato')
      ->leftJoin('App:Scrutinio', 's', 'WHERE', 's.periodo=d.periodo AND s.classe=:classe')
      ->where('d.dataProposte<=:data')
      ->setParameters(['data' => (new \DateTime())->format('Y-m-d'), 'classe' => $classe])
      ->orderBy('d.data', 'ASC')
      ->getQuery()
      ->getResult();
    foreach ($periodi as $p) {
      $lista[$p['periodo']] = ($p['stato'] ? $p['stato'] : 'N');
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
      $lista_alunni = $this->em->getRepository('App:Alunno')->createQueryBuilder('a')
        ->select('a.id')
        ->where('a.classe=:classe AND a.abilitato=:abilitato AND a.religione=:religione')
        ->setParameters(['classe' => $classe, 'abilitato' => 1, 'religione' => 'S'])
        ->getQuery()
        ->getScalarResult();
    } else {
      // non è religione: tutti gli alunni
      $lista_alunni = $this->em->getRepository('App:Alunno')->createQueryBuilder('a')
        ->select('a.id')
        ->where('a.classe=:classe AND a.abilitato=:abilitato')
        ->setParameters(['classe' => $classe, 'abilitato' => 1])
        ->getQuery()
        ->getScalarResult();
    }
    // legge i dati degli degli alunni
    $alunni = $this->em->getRepository('App:Alunno')->createQueryBuilder('a')
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
    $proposte = $this->em->getRepository('App:PropostaVoto')->createQueryBuilder('pv')
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
      $voti = $this->em->getRepository('App:VotoScrutinio')->createQueryBuilder('vs')
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
    $ris = null;
    // legge definizione scrutini
    $periodi = $this->em->getRepository('App:DefinizioneScrutinio')->createQueryBuilder('d')
      ->select('d.periodo,s.stato')
      ->leftJoin('App:Scrutinio', 's', 'WHERE', 's.periodo=d.periodo AND s.classe=:classe')
      ->where('d.data<=:data')
      ->setParameters(['data' => (new \DateTime())->format('Y-m-d'), 'classe' => $classe])
      ->orderBy('d.data', 'DESC')
      ->setMaxResults(1)
      ->getQuery()
      ->getOneOrNullResult();
    if ($periodi && $periodi['stato'] != 'C') {
      $ris = array(
        'periodo' => $periodi['periodo'],
        'stato' => ($periodi['stato'] ? $periodi['stato'] : 'N'));
    }
    // restituisce valori
    return $ris;
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
    $scrutinio = $this->em->getRepository('App:Scrutinio')->createQueryBuilder('s')
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
    } elseif ($periodo == 'I' || $periodo == 'X') {
      // scrutinio integrativo
      switch ($stato) {
        case 'N':
          // riepilogo
          if ($periodo == 'I') {
            $dati = $this->riepilogoSospesi($docente, $classe, $periodo);
          } else {
            $dati = $this->riepilogoRinviati($docente, $classe, $periodo);
          }
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
    } elseif ($periodo == 'I' || $periodo == 'X') {
      // scrutinio integrativo
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
    $scrutinio = $this->em->getRepository('App:Scrutinio')->createQueryBuilder('s')
      ->where('s.periodo=:periodo AND s.classe=:classe')
      ->setParameters(['periodo' => $periodo, 'classe' => $classe])
      ->setMaxResults(1)
      ->getQuery()
      ->getOneOrNullResult();
    if (!$scrutinio) {
      // stato iniziale
      $scrutinio = (new Scrutinio())
        ->setClasse($classe)
        ->setPeriodo($periodo)
        ->setStato('N');
      $this->em->persist($scrutinio);
      $this->em->flush();
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
    $alunni = $this->em->getRepository('App:Alunno')->createQueryBuilder('a')
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
    $materie = $this->em->getRepository('App:Materia')->createQueryBuilder('m')
      ->select('DISTINCT m.id,m.nome,m.nomeBreve,m.tipo')
      ->join('App:Cattedra', 'c', 'WHERE', 'c.materia=m.id')
      ->where('c.classe=:classe AND c.attiva=:attiva AND c.tipo=:tipo AND m.tipo!=:sostegno')
      ->orderBy('m.ordinamento', 'ASC')
      ->setParameters(['classe' => $classe, 'attiva' => 1, 'tipo' => 'N', 'sostegno' => 'S'])
      ->getQuery()
      ->getArrayResult();
    foreach ($materie as $mat) {
      $dati['materie'][$mat['id']] = $mat;
    }
    // legge le proposte di voto
    $proposte = $this->em->getRepository('App:PropostaVoto')->createQueryBuilder('pv')
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
      $voti = $this->em->getRepository('App:VotoScrutinio')->createQueryBuilder('vs')
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
    $dati_delibera = ['motivazione' => null, 'unanimita' => true, 'contrari' => null];
    foreach ($dati['alunni'] as $alunno=>$alu) {
      foreach ($dati['materie'] as $materia=>$mat) {
        // esclude alunni NA per religione
        if ($mat['tipo'] == 'N' || $alu['religione'] == 'S') {
          // calcola assenze di alunno
          $ore = $this->em->getRepository('App:AssenzaLezione')->createQueryBuilder('al')
            ->select('SUM(al.ore)')
            ->join('al.lezione', 'l')
            ->leftJoin('App:CambioClasse', 'cc', 'WHERE', 'cc.alunno=al.alunno AND l.data BETWEEN cc.inizio AND cc.fine')
            ->where('al.alunno=:alunno AND l.materia=:materia AND l.data BETWEEN :inizio AND :fine AND (l.classe=:classe OR l.classe=cc.classe)')
            ->setParameters(['alunno' => $alunno, 'materia' => $materia,
              'inizio' => $this->session->get('/CONFIG/SCUOLA/anno_inizio'),
              'fine' => $this->session->get('/CONFIG/SCUOLA/periodo1_fine'), 'classe' => $classe->getId()])
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
              'recupero' => $dati['proposte'][$alunno][$materia]['recupero'],
              'ore' => $ore,
              'dati' => serialize($dati_delibera)]);
        }
      }
    }
    // memorizza alunni
    $dati_scrutinio = $scrutinio->getDati();
    $dati_scrutinio['alunni'] = array_keys($dati['alunni']);
    $scrutinio->setDati($dati_scrutinio);
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
    $docenti = $this->em->getRepository('App:Cattedra')->createQueryBuilder('c')
      ->select('DISTINCT d.id,d.cognome,d.nome,m.nomeBreve,m.id AS materia_id,c.tipo,c.supplenza')
      ->join('c.materia', 'm')
      ->join('c.docente', 'd')
      ->where('c.classe=:classe AND c.attiva=:attiva AND c.tipo!=:tipo')
      ->orderBy('d.cognome,d.nome,m.ordinamento,m.nomeBreve', 'ASC')
      ->setParameters(['classe' => $classe, 'attiva' => 1, 'tipo' => 'P'])
      ->getQuery()
      ->getArrayResult();
    // elimina docente titolare se esiste supplente
    //-- $mat = array();
    //-- foreach ($docenti as $k=>$doc) {
      //-- if (!isset($mat[$doc['materia_id']][$doc['tipo']])) {
        //-- // memorizza docente di materia
        //-- $mat[$doc['materia_id']][$doc['tipo']] = $k;
      //-- } else {
        //-- // elimina titolare di cattedra
        //-- if ($doc['supplenza']) {
          //-- // cancella titolare e memorizza docente supplente
          //-- unset($docenti[$mat[$doc['materia_id']][$doc['tipo']]]);
          //-- $mat[$doc['materia_id']][$doc['tipo']] = $k;
        //-- } else {
          //-- // cancella titolare
          //-- unset($docenti[$k]);
        //-- }
      //-- }
    //-- }
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
    $scrutinio = $this->em->getRepository('App:Scrutinio')->createQueryBuilder('s')
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
        $this->session->getFlashBag()->add('errore', $this->trans->trans('exception.scrutinio_data'));
      }
      if (!$form->get('inizio')->getData()) {
        // ora non presente
        $this->session->getFlashBag()->add('errore', $this->trans->trans('exception.scrutinio_inizio'));
      }
      if ($form->get('presiede_ds')->getData() === null) {
        // presidente ds non presente
        $this->session->getFlashBag()->add('errore', $this->trans->trans('exception.scrutinio_presidente'));
      }
      if ($form->get('presiede_ds')->getData() === false && !$form->get('presiede_docente')->getData()) {
        // presidente docente non presente
        $this->session->getFlashBag()->add('errore', $this->trans->trans('exception.scrutinio_presidente'));
      }
      if (!$form->get('segretario')->getData()) {
        // segretario non presente
        $this->session->getFlashBag()->add('errore', $this->trans->trans('exception.scrutinio_segretario'));
      }
      // controlli sui presenti
      $errore_presenza = false;
      foreach ($form->get('lista')->getData() as $doc=>$val) {
        if (!$val || (!$val->getPresenza() && !$val->getSostituto())) {
          $errore_presenza = true;
        }
      }
      if ($errore_presenza) {
        // mancano indicazioni su presenti
        $this->session->getFlashBag()->add('errore', $this->trans->trans('exception.scrutinio_presenza'));
      }
      // se niente errori cambia stato
      if (!$this->session->getFlashBag()->has('errore')) {
        // imposta dati
        $scrutinio->setData($form->get('data')->getData());
        $scrutinio->setInizio($form->get('inizio')->getData());
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
    $alunni = $this->em->getRepository('App:Alunno')->createQueryBuilder('a')
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
    $materie = $this->em->getRepository('App:Materia')->createQueryBuilder('m')
      ->select('DISTINCT m.id,m.nome,m.nomeBreve,m.tipo')
      ->join('App:Cattedra', 'c', 'WHERE', 'c.materia=m.id')
      ->where('c.classe=:classe AND c.attiva=:attiva AND c.tipo=:tipo AND m.tipo!=:sostegno')
      ->orderBy('m.ordinamento', 'ASC')
      ->setParameters(['classe' => $classe, 'attiva' => 1, 'tipo' => 'N', 'sostegno' => 'S'])
      ->getQuery()
      ->getArrayResult();
    foreach ($materie as $mat) {
      $dati['materie'][$mat['id']] = $mat;
    }
    $condotta = $this->em->getRepository('App:Materia')->findOneByTipo('C');
    $dati['materie'][$condotta->getId()] = array(
      'id' => $condotta->getId(),
      'nome' => $condotta->getNome(),
      'nomeBreve' => $condotta->getNomeBreve(),
      'tipo' => $condotta->getTipo());
    // legge i voti
    $voti = $this->em->getRepository('App:VotoScrutinio')->createQueryBuilder('vs')
      ->join('vs.scrutinio', 's')
      ->where('s.classe=:classe AND s.periodo=:periodo AND vs.unico IS NOT NULL AND vs.alunno IN (:lista)')
      ->setParameters(['classe' => $classe, 'periodo' => $periodo, 'lista' => $lista])
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
      $voti = $this->em->getRepository('App:VotoScrutinio')->createQueryBuilder('vs')
        ->join('vs.scrutinio', 's')
        ->where('vs.unico<:suff AND s.classe=:classe AND s.periodo=:periodo AND s.stato=:stato')
        ->setParameters(['suff' => 6, 'classe' => $classe, 'periodo' => 'P', 'stato' => 'C'])
        ->getQuery()
        ->getResult();
      foreach ($voti as $v) {
        // inserisce proposte trovate
        $dati['debiti'][$v->getAlunno()->getId()][$v->getMateria()->getId()] = $v;
      }
    } elseif ($periodo == 'F' || $periodo == 'I' || $periodo == 'X') {
      // legge esiti
      $esiti = $this->em->getRepository('App:Esito')->createQueryBuilder('e')
        ->join('e.scrutinio', 's')
        ->where('e.alunno IN (:lista) AND s.classe=:classe AND s.periodo=:periodo AND s.stato=:stato')
        ->setParameters(['lista' => $lista, 'classe' => $classe, 'periodo' => $periodo, 'stato' => 'C'])
        ->getQuery()
        ->getResult();
      foreach ($esiti as $e) {
        $dati['esiti'][$e->getAlunno()->getId()] = $e;
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
    $scrutinio = $this->em->getRepository('App:Scrutinio')->createQueryBuilder('s')
      ->where('s.classe=:classe AND s.periodo=:periodo')
      ->setParameters(['classe' => $classe, 'periodo' => $periodo])
      ->setMaxResults(1)
      ->getQuery()
      ->getOneOrNullResult();
    // alunni della classe
    $lista_id = $this->alunniInScrutinio($classe, $periodo);
    $alunni = $this->em->getRepository('App:Alunno')->createQueryBuilder('a')
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
    $voti = $this->em->getRepository('App:VotoScrutinio')->createQueryBuilder('vs')
      ->where('vs.scrutinio=:scrutinio AND vs.materia=:materia AND vs.alunno IN (:lista)')
      ->setParameters(['scrutinio' => $scrutinio, 'materia' => $materia,
        'lista' => array_keys($elenco['alunni'])])
      ->getQuery()
      ->getResult();
    foreach ($voti as $v) {
      // inserisce voti
      $elenco['voti'][$v->getAlunno()->getId()] = $v;
    }
    // crea voti se non esistono (solo per la condotta)
    if ($materia->getTipo() == 'C') {
      foreach ($alunni as $alu) {
        // aggiunge nuovi voti nulli
        if (empty($elenco['voti'][$alu->getId()])) {
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
    }
    // debito formativo esistente
    if ($periodo == '1') {
      $voti = $this->em->getRepository('App:VotoScrutinio')->createQueryBuilder('vs')
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
    // inizializza messaggi di errore
    $this->session->getFlashBag()->clear();
    // legge condotta
    $condotta = $this->em->getRepository('App:Materia')->findOneByTipo('C');
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
      $this->session->getFlashBag()->add('errore', $this->trans->trans($msg));
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
    $scrutinio = $this->em->getRepository('App:Scrutinio')->createQueryBuilder('s')
      ->where('s.periodo=:periodo AND s.classe=:classe')
      ->setParameters(['periodo' => $periodo, 'classe' => $classe])
      ->setMaxResults(1)
      ->getQuery()
      ->getOneOrNullResult();
    // esiti
    if ($periodo == 'F' || $periodo == 'I' || $periodo == 'X') {
      $lista = $this->alunniInScrutinio($classe, $periodo);
      $esiti = $this->em->getRepository('App:Esito')->createQueryBuilder('e')
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
        $this->session->getFlashBag()->add('errore', $this->trans->trans('exception.scrutinio_fine'));
      }
      // se niente errori cambia stato
      if (!$this->session->getFlashBag()->has('errore')) {
        // imposta fine
        $scrutinio->setFine($form->get('fine')->getData());
        // imposta conferma verbale
        $def = $this->em->getRepository('App:DefinizioneScrutinio')->findOneByPeriodo('P');
        $dati_scrutinio = $scrutinio->getDati();
        foreach ($def->getStruttura() as $step=>$args) {
          $dati_scrutinio['verbale'][$step]['validazione'] = $args[1];
          $dati_scrutinio['verbale'][$step]['validato'] = !$args[1];
        }
        $scrutinio->setDati($dati_scrutinio);
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
    $percorso = $this->root.'/trimestre/'.$classe->getAnno().$classe->getSezione();
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
      $alunni = $this->em->getRepository('App:Alunno')->createQueryBuilder('a')
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
      $debiti = $this->em->getRepository('App:VotoScrutinio')->createQueryBuilder('vs')
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
      // verbale
      $def = $this->em->getRepository('App:DefinizioneScrutinio')->findOneByPeriodo($periodo);
      $scrutinio = $this->em->getRepository('App:Scrutinio')->findOneBy(
        ['classe' => $classe, 'periodo' => $periodo, 'stato' => 'C']);
      $dati_scrutinio = $scrutinio->getDati();
      $dati['verbale']['download'] = true;
      foreach ($dati_scrutinio['verbale'] as $step=>$args) {
        if ($args['validazione']) {
          $dati['verbale']['step'][$step] = $def->getStruttura()[$step][2];
          $dati['verbale']['validato'][$step] = $args['validato'];
          $dati['verbale']['download'] = ($dati['verbale']['download'] && $args['validato']);
        }
      }
    } elseif ($periodo == '1') {
      // dati alunni
      $alunni = $this->em->getRepository('App:Alunno')->createQueryBuilder('a')
        ->select('a.id,a.nome,a.cognome,a.dataNascita')
        ->where('a.id IN (:lista)')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
        ->setParameters(['lista' => $lista])
        ->getQuery()
        ->getResult();
      foreach ($alunni as $alu) {
        $dati['alunni'][$alu['id']] = $alu;
      }
    } elseif ($periodo == 'F') {
      // legge i non ammessi/non scrutinabili per assenze/non scrutinati
      $non_ammessi = $this->em->getRepository('App:Alunno')->createQueryBuilder('a')
        ->select('a.id')
        ->join('App:Esito', 'e', 'WHERE', 'e.alunno=a.id')
        ->join('e.scrutinio', 's')
        ->where('a.id IN (:lista) AND e.esito=:esito AND s.classe=:classe AND s.periodo=:periodo')
        ->setParameters(['lista' => $lista, 'esito' => 'N', 'classe' => $classe, 'periodo' => $periodo])
        ->getQuery()
        ->getArrayResult();
      $non_ammessi = array_column($non_ammessi, 'id');
      $scrutinio = $this->em->getRepository('App:Scrutinio')->createQueryBuilder('s')
        ->where('s.classe=:classe AND s.periodo=:periodo AND s.stato=:stato')
        ->setParameters(['classe' => $classe, 'periodo' => $periodo, 'stato' => 'C'])
        ->getQuery()
        ->getOneOrNullResult();
      $noscrut = ($scrutinio->getDato('no_scrutinabili') ? $scrutinio->getDato('no_scrutinabili') : []);
      foreach ($noscrut as $alu=>$ns) {
        if (!isset($ns['deroga'])) {
          $non_ammessi[] = $alu;
        }
      }
      $dati['non_ammessi'] = $this->em->getRepository('App:Alunno')->createQueryBuilder('a')
        ->select('a.id,a.nome,a.cognome,a.dataNascita')
        ->where('a.id IN (:lista)')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
        ->setParameters(['lista' => array_merge($non_ammessi,
          ($scrutinio->getDato('cessata_frequenza') == null ? [] : $scrutinio->getDato('cessata_frequenza')))])
        ->getQuery()
        ->getArrayResult();
      // legge i debiti
      $dati['debiti']  = $this->em->getRepository('App:Alunno')->createQueryBuilder('a')
        ->select('a.id,a.nome,a.cognome,a.dataNascita')
        ->join('App:Esito', 'e', 'WHERE', 'e.alunno=a.id')
        ->join('e.scrutinio', 's')
        ->where('a.id in (:lista) AND e.esito=:sospeso AND s.classe=:classe AND s.periodo=:periodo')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
        ->setParameters(['lista' => $lista, 'sospeso' => 'S', 'classe' => $classe, 'periodo' => $periodo])
        ->getQuery()
        ->getArrayResult();
      // legge le carenze
      $alunni = $this->em->getRepository('App:Alunno')->createQueryBuilder('a')
        ->select('a.id,a.nome,a.cognome,a.dataNascita,e.dati')
        ->join('App:Esito', 'e', 'WHERE', 'e.alunno=a.id')
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
      // verbale
      $def = $this->em->getRepository('App:DefinizioneScrutinio')->findOneByPeriodo($periodo);
      $scrutinio = $this->em->getRepository('App:Scrutinio')->findOneBy(
        ['classe' => $classe, 'periodo' => $periodo, 'stato' => 'C']);
      $dati_scrutinio = $scrutinio->getDati();
      $dati['verbale']['download'] = true;
      foreach ($dati_scrutinio['verbale'] as $step=>$args) {
        if ($args['validazione']) {
          $dati['verbale']['step'][$step] = $def->getStruttura()[$step][2];
          $dati['verbale']['validato'][$step] = $args['validato'];
          $dati['verbale']['download'] = ($dati['verbale']['download'] && $args['validato']);
        }
      }
    } elseif ($periodo == 'I' || $periodo == 'X') {
      // legge i non ammessi
      $dati['non_ammessi'] = $this->em->getRepository('App:Alunno')->createQueryBuilder('a')
        ->select('a.id,a.nome,a.cognome,a.dataNascita')
        ->join('App:Esito', 'e', 'WHERE', 'e.alunno=a.id')
        ->join('e.scrutinio', 's')
        ->where('a.id IN (:lista) AND e.esito=:esito AND s.classe=:classe AND s.periodo=:periodo')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
        ->setParameters(['lista' => $lista, 'esito' => 'N', 'classe' => $classe, 'periodo' => $periodo])
        ->getQuery()
        ->getArrayResult();
      // verbale
      $def = $this->em->getRepository('App:DefinizioneScrutinio')->findOneByPeriodo($periodo);
      $scrutinio = $this->em->getRepository('App:Scrutinio')->findOneBy(
        ['classe' => $classe, 'periodo' => $periodo, 'stato' => 'C']);
      $dati_scrutinio = $scrutinio->getDati();
      $dati['verbale']['download'] = true;
      foreach ($dati_scrutinio['verbale'] as $step=>$args) {
        if ($args['validazione']) {
          $dati['verbale']['step'][$step] = $def->getStruttura()[$step][2];
          $dati['verbale']['validato'][$step] = $args['validato'];
          $dati['verbale']['download'] = ($dati['verbale']['download'] && $args['validato']);
        }
      }
    }
    // controlla se attivare pulsante riapertura o no
    $scrutinio = $this->em->getRepository('App:Scrutinio')->createQueryBuilder('s')
      ->where('s.classe=:classe AND s.periodo=:periodo AND s.stato=:stato AND s.sincronizzazione IS NULL')
      ->setParameters(['classe' => $classe, 'periodo' => $periodo, 'stato' => 'C'])
      ->getQuery()
      ->getOneOrNullResult();
    $dati['precedente'] = ($docente instanceOf Staff) && $scrutinio;
    // controlla se attivare pulsante modifica verbale
    $dati['modifica'] = ($scrutinio !== null);
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
    $alunni = array();
    $scrutinio = $this->em->getRepository('App:Scrutinio')->findOneBy(['periodo' => $periodo, 'classe' => $classe]);
    if ($periodo == 'P' || $periodo == '1') {
      // solo gli alunni al momento dello scrutinio
      $alunni = $scrutinio->getDato('alunni');
    } elseif ($periodo == 'F') {
      // legge lista alunni scrutinabili
      return array_keys($scrutinio->getDato('scrutinabili'));
    } elseif ($periodo == 'I') {
      // legge lista alunni sospesi
      $sospesi = ($scrutinio ? $scrutinio->getDati()['sospesi'] : []);
      // restituisce lista di ID
      return $sospesi;
    } elseif ($periodo == 'X') {
      // legge lista alunni con scrutinio rinviato
      $rinviati = ($scrutinio ? $scrutinio->getDati()['rinviati'] : []);
      // restituisce lista di ID
      return $rinviati;
    }
    // restituisce lista di ID
    return $alunni;
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
    $dati_delibera = ['motivazione' => null, 'unanimita' => true, 'contrari' => null];
    foreach ($dati['alunni'] as $alunno=>$alu) {
      foreach ($dati['materie'] as $materia=>$mat) {
        // esclude alunni NA per religione
        if ($mat['tipo'] == 'N' || $alu['religione'] == 'S') {
          // calcola assenze di alunno
          $ore = $this->em->getRepository('App:AssenzaLezione')->createQueryBuilder('al')
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
              'dati' => serialize($dati_delibera)]);
        }
      }
    }
    // memorizza alunni
    $dati_scrutinio = $scrutinio->getDati();
    $dati_scrutinio['alunni'] = array_keys($dati['alunni']);
    $scrutinio->setDati($dati_scrutinio);
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
    // inizializza messaggi di errore
    $this->session->getFlashBag()->clear();
    // legge condotta
    $condotta = $this->em->getRepository('App:Materia')->findOneByTipo('C');
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
      $this->session->getFlashBag()->add('errore', $this->trans->trans($msg));
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
    $percorso = $this->root.'/val-intermedia/'.$classe->getAnno().$classe->getSezione();
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
    $dati_delibera = array('motivazione' => null, 'unanimita' => true, 'contrari' => null);
    $num = 0;
    foreach ($dati['alunni'] as $alunno=>$alu) {
      $alunno_obj = $this->em->getRepository('App:Alunno')->find($alunno);
      foreach ($dati['materie'] as $materia=>$mat) {
        $materia_obj = $this->em->getRepository('App:Materia')->find($materia);
        // esclude alunni NA per religione
        if ($mat['tipo'] == 'N' || $alu['religione'] == 'S') {
          // calcola assenze di alunno
          $ore = $this->em->getRepository('App:AssenzaLezione')->createQueryBuilder('al')
            ->select('SUM(al.ore)')
            ->join('al.lezione', 'l')
            ->leftJoin('App:CambioClasse', 'cc', 'WHERE', 'cc.alunno=al.alunno AND l.data BETWEEN cc.inizio AND cc.fine')
            ->where('al.alunno=:alunno AND l.materia=:materia AND l.data>:inizio AND l.data<=:fine AND (l.classe=:classe OR l.classe=cc.classe)')
            ->setParameters(['alunno' => $alunno, 'materia' => $materia,
              'inizio' => $this->session->get('/CONFIG/SCUOLA/periodo1_fine'),
              'fine' => $this->session->get('/CONFIG/SCUOLA/periodo2_fine'), 'classe' => $classe->getId()])
            ->getQuery()
            ->getSingleScalarResult();
          $ore = ($ore ? intval($ore) : 0);
          // inserisce voti e assenze
          $vs = (new VotoScrutinio())
            ->setScrutinio($scrutinio)
            ->setAlunno($alunno_obj)
            ->setMateria($materia_obj)
            ->setUnico($dati['proposte'][$alunno][$materia]['unico'])
            ->setDebito($dati['proposte'][$alunno][$materia]['debito'])
            ->setRecupero($dati['proposte'][$alunno][$materia]['recupero'])
            ->setAssenze($ore)
            ->setDati($dati_delibera);
          $this->em->persist($vs);
          $num++;
          if ($num % 20 == 0) {
            $this->em->flush();
          }
        }
      }
    }
    $this->em->flush();
    // memorizza alunni
    $dati_scrutinio = $scrutinio->getDati();
    $dati_scrutinio['alunni'] = array_keys($dati['alunni']);
    $scrutinio->setDati($dati_scrutinio);
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
        $this->session->getFlashBag()->add('errore', $this->trans->trans('exception.scrutinio_data'));
      }
      if (!$form->get('inizio')->getData()) {
        // ora non presente
        $this->session->getFlashBag()->add('errore', $this->trans->trans('exception.scrutinio_inizio'));
      }
      if ($form->get('presiede_ds')->getData() === null) {
        // presidente ds non presente
        $this->session->getFlashBag()->add('errore', $this->trans->trans('exception.scrutinio_presidente'));
      }
      if ($form->get('presiede_ds')->getData() === false && !$form->get('presiede_docente')->getData()) {
        // presidente docente non presente
        $this->session->getFlashBag()->add('errore', $this->trans->trans('exception.scrutinio_presidente'));
      }
      if (!$form->get('segretario')->getData()) {
        // segretario non presente
        $this->session->getFlashBag()->add('errore', $this->trans->trans('exception.scrutinio_segretario'));
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
        $this->session->getFlashBag()->add('errore', $this->trans->trans('exception.scrutinio_presenza'));
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
    $dati['cessata_frequenza'] = array();
    $dati['ritirati'] = array();
    // legge scrutinio finale e del primo trimestre
    $scrutinio_F = $this->em->getRepository('App:Scrutinio')->findOneBy(['periodo' => 'F', 'classe' => $classe]);
    $scrutinio_P = $this->em->getRepository('App:Scrutinio')->findOneBy(['periodo' => 'P', 'classe' => $classe]);
    if (!$scrutinio_F || !$scrutinio_P) {
      // errore
      return null;
    }
    // calcola limite assenze
    $dati['monteore'] = $classe->getOreSettimanali() * 33;
    $dati['maxassenze'] = intval($dati['monteore'] / 4);
    // lezioni dal 15 marzo
    $giorni_lezione = $this->lezioniDal15Marzo($classe);
    // calcola ore totali assenza alunni (compresi cambi classe in primo trimestre)
    $alunni = $this->em->getRepository('App:Alunno')->createQueryBuilder('a')
      ->select('a.id,a.cognome,a.nome,a.sesso,a.dataNascita,SUM(vs.assenze) AS ore')
      ->join('App:VotoScrutinio', 'vs', 'WHERE', 'vs.alunno=a.id')
      ->join('vs.scrutinio', 's')
      ->leftJoin('App:CambioClasse', 'cc', 'WHERE', 'cc.alunno=a.id')
      ->where('a.id IN (:alunni) AND (s.id IN (:scrutini) OR (s.classe=cc.classe AND s.periodo=:periodo))')
      ->groupBy('a.id')
      ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
      ->setParameters(['alunni' => $scrutinio_F->getDati()['alunni'], 'scrutini' => [$scrutinio_P, $scrutinio_F],
        'periodo' => 'P'])
      ->getQuery()
      ->getArrayResult();
    // legge dati scrutinio
    $scrutinio_dati = $scrutinio_F->getDati();
    $dati['forza_assenze'] = (empty($scrutinio_dati['forza_assenze']) ? [] : $scrutinio_dati['forza_assenze']);
    foreach ($alunni as $a) {
      // percentuale assenze
      $perc = $a['ore'] / $dati['monteore'] * 100;
      if ($a['ore'] <= $dati['maxassenze']) {
        // assenze entro limite
        $dati['alunni'][$a['id']] = $a;
        $dati['alunni'][$a['id']]['percentuale'] = $perc;
      } else {
        // assenze oltre il limite: non scrutinabile
        $dati['no_scrutinabili']['alunni'][$a['id']] = $a;
        $dati['no_scrutinabili']['alunni'][$a['id']]['percentuale'] = $perc;
        // controlla presenze
        $presenze = $this->presenzeDal15Marzo($a['id'], $giorni_lezione);
        if ($presenze['stato'] == 0) {
          // cessata frequenza
          $dati['cessata_frequenza'][$a['id']] = $a;
        } else {
          // superamento 25% assenze
          $dati['no_scrutinabili']['alunni'][$a['id']]['giorni_presenza'] = $presenze['giorni'];
          // crea oggetto per form
          $dati['no_scrutinabili']['form'][$a['id']] = (new ScrutinioAssenza())
            ->setAlunno($a['id'])
            ->setSesso($a['sesso']);
          // recupera dati esistenti
          if (isset($scrutinio_dati['no_scrutinabili'][$a['id']]['deroga'])) {
            // scrutinabile in deroga
            $dati['no_scrutinabili']['form'][$a['id']]
              ->setScrutinabile('D')
              ->setMotivazione($scrutinio_dati['no_scrutinabili'][$a['id']]['deroga']);
          } elseif (isset($scrutinio_dati['no_scrutinabili'][$a['id']])) {
            // non scrutinabile
            $dati['no_scrutinabili']['form'][$a['id']]
              ->setScrutinabile('A');
          }
        }
      }
    }
    // alunni ritirati/trasferiti/all'estero
    $alunni = $this->em->getRepository('App:Alunno')->createQueryBuilder('a')
      ->select('a.id,a.nome,a.cognome,a.sesso,a.dataNascita,a.bes,a.frequenzaEstero,cc.note')
      ->join('App:CambioClasse', 'cc', 'WHERE', 'cc.alunno=a.id')
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
   * Restituisce la lista dei giorni di lezione dal 15 Marzo a fine anno
   *
   * @param Classe $classe Classe per cui calcolare i giorni di lezione
   *
   * @return array Lista di date
   */
  public function lezioniDal15Marzo(Classe $classe) {
    // inizio e fine del periodo
    $inizio = \DateTime::createFromFormat('!Y-m-d',
      substr($this->session->get('/CONFIG/SCUOLA/anno_fine'), 0, 4).'-03-15');
    $fine = \DateTime::createFromFormat('!Y-m-d', $this->session->get('/CONFIG/SCUOLA/anno_fine'));
    // festivi
    $festivi = $this->em->getRepository('App:Festivita')->createQueryBuilder('f')
      ->select('f.data')
      ->where('f.tipo=:festivo AND f.data BETWEEN :inizio AND :fine AND (f.sede IS NULL OR f.sede=:sede)')
      ->orderBy('f.data', 'ASC')
      ->setParameters(['festivo' => 'F', 'inizio' => $inizio->format('Y-m-d'), 'fine' => $fine->format('Y-m-d'),
        'sede' => $classe->getSede()])
      ->getQuery()
      ->getScalarResult();
    $giorni_festivi = array_column($festivi, 'data');
    $giorni_settimana = array($this->session->get('/CONFIG/ACCESSO/giorni_festivi_istituto'));
    $altri_festivi = explode(',', $this->session->get('/CONFIG/ACCESSO/giorni_festivi_classi'));
    foreach($altri_festivi  as $f) {
      // formato <settimana>:<classe_anno><classe_sezione>
      if (strlen($f) > 0 && $classe->getAnno() == $f{2} && $classe->getSezione() == $f{3}) {
        $giorni_settimana[] = $f{0};
      }
    }
    // lezioni
    $data = clone $inizio;
    $giorni_lezione = array();
    while ($data <= $fine) {
      if (!in_array($data->format('Y-m-d'), $giorni_festivi) && !in_array($data->format('w'), $giorni_settimana)) {
        // giorno di lezione
        $giorni_lezione[] = $data->format('Y-m-d');
      }
      $data->modify('+1 day');
    }
    // restituisce lista
    return $giorni_lezione;
  }

  /**
   * Restituisce la lista dei giorni di presenza dell'alunno dal 15 Marzo a fine anno
   *
   * @param int $alunno_id ID alunno di cui calcolare le presenze
   * @param array $lezioni Lista delle date dei giorni di lezione
   *
   * @return array Dati formattati come un array associativo
   */
  public function presenzeDal15Marzo($alunno_id, $lezioni) {
    $dati = array();
    // inizio e fine del periodo
    $inizio = substr($this->session->get('/CONFIG/SCUOLA/anno_fine'), 0, 4).'-03-15';
    $fine = $this->session->get('/CONFIG/SCUOLA/anno_fine');
    // assenze
    $giorni_assenza = $this->em->getRepository('App:Assenza')->createQueryBuilder('a')
      ->select('a.data')
      ->where('a.alunno=:alunno AND a.data BETWEEN :inizio AND :fine')
      ->setParameters(['alunno' => $alunno_id, 'inizio' => $inizio, 'fine' => $fine])
      ->getQuery()
      ->getScalarResult();
    $giorni_assenza = array_column($giorni_assenza, 'data');
    // presenze
    $giorni_presenza_str = array_diff($lezioni, $giorni_assenza);
    $giorni_presenza_obj = array_map(function($d) { return \DateTime::createFromFormat('!Y-m-d', $d); }, $giorni_presenza_str);
    if (count($giorni_presenza_str) == 0) {
      // cessata frequenza
      $dati['stato'] = 0;
      $dati['giorni'] = array();
    } else {
      // controllo presenze cancellabili
      $giorni_note = $this->em->getRepository('App:Nota')->createQueryBuilder('n')
        ->select('n.data')
        ->join('n.alunni', 'a')
        ->where('a.id=:alunno AND n.tipo=:nota AND n.data IN (:date)')
        ->setParameters(['alunno' => $alunno_id, 'nota' => 'I', 'date' => $giorni_presenza_str])
        ->getQuery()
        ->getScalarResult();
      $giorni_entrate = $this->em->getRepository('App:Entrata')->createQueryBuilder('e')
        ->select('e.data')
        ->where('e.alunno=:alunno AND e.data IN (:date)')
        ->setParameters(['alunno' => $alunno_id, 'date' => $giorni_presenza_str])
        ->getQuery()
        ->getScalarResult();
      $giorni_uscite = $this->em->getRepository('App:Uscita')->createQueryBuilder('u')
        ->select('u.data')
        ->where('u.alunno=:alunno AND u.data IN (:date)')
        ->setParameters(['alunno' => $alunno_id, 'date' => $giorni_presenza_str])
        ->getQuery()
        ->getScalarResult();
      if (count($giorni_note)+count($giorni_entrate)+count($giorni_uscite) > 0) {
        // non si possono cancellare giorni di presenza
        $dati['stato'] = -1;
        $dati['giorni'] = array();
      } else {
        // è possibile cancellare giorni di presenza
        $dati['stato'] = 1;
        $dati['giorni'] = $giorni_presenza_obj;
      }
    }
    // restituisce dati
    return $dati;
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
        $this->session->getFlashBag()->add('errore', $this->trans->trans('exception.scrutinio_tipo_scrutinabile'));
      }
      if ($errore_motivazione) {
        // non inserita motivazione di deroga
        $this->session->getFlashBag()->add('errore', $this->trans->trans('exception.scrutinio_motivazione_deroga'));
      }
      if ($errore_alunni) {
        // lista alunni no scrutinabili errata
        $this->session->getFlashBag()->add('errore', $this->trans->trans('exception.scrutinio_lista_no_scrutinabili'));
      }
      // se niente errori cambia stato
      if (!$this->session->getFlashBag()->has('errore')) {
        // imposta dati
        $dati_scrutini = $scrutinio->getDati();
        $scrutinio->setDati(array());   // necessario per bug di aggiornamento
        $this->em->flush();             // necessario per bug di aggiornamento
        $dati_scrutini['monteore'] = $dati['monteore'];
        $dati_scrutini['maxassenze'] = $dati['maxassenze'];
        // dati alunni
        $dati_scrutini['cessata_frequenza'] = array_keys($dati['cessata_frequenza']);
        $dati_scrutini['ritirati'] = array_keys($dati['ritirati']);
        $dati_scrutini['scrutinabili'] = null;
        foreach ($dati['alunni'] as $alu=>$val) {
          $dati_scrutini['scrutinabili'][$alu]['ore'] = $val['ore'];
          $dati_scrutini['scrutinabili'][$alu]['percentuale'] = $val['percentuale'];
        }
        $dati_scrutini['no_scrutinabili'] = null;
        foreach ($form->get('lista')->getData() as $val) {
          $alu = $val->getAlunno();
          $dati_scrutini['no_scrutinabili'][$alu]['ore'] = $dati['no_scrutinabili']['alunni'][$alu]['ore'];
          $dati_scrutini['no_scrutinabili'][$alu]['percentuale'] = $dati['no_scrutinabili']['alunni'][$alu]['percentuale'];
          if ($val->getScrutinabile() == 'D') {
            // scrutinabili in deroga
            $dati_scrutini['no_scrutinabili'][$alu]['deroga'] = $val->getMotivazione();
            $dati_scrutini['scrutinabili'][$alu]['ore'] = $dati['no_scrutinabili']['alunni'][$alu]['ore'];
            $dati_scrutini['scrutinabili'][$alu]['percentuale'] = $dati['no_scrutinabili']['alunni'][$alu]['percentuale'];
          }
        }
        // aggiorna dati
        $scrutinio->setDati($dati_scrutini);
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
    $condotta = $this->em->getRepository('App:Materia')->findOneByTipo('C');
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
      $this->session->getFlashBag()->add('errore', $this->trans->trans($msg));
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
    $esiti = $this->em->getRepository('App:Esito')->createQueryBuilder('e')
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
    $scrutinio = $this->em->getRepository('App:Scrutinio')->createQueryBuilder('s')
      ->where('s.classe=:classe AND s.periodo=:periodo')
      ->setParameters(['classe' => $alunno->getClasse(), 'periodo' => $periodo])
      ->setMaxResults(1)
      ->getQuery()
      ->getOneOrNullResult();
    $dati['scrutinio'] = $scrutinio;
    // legge materie
    $materie = $this->em->getRepository('App:Materia')->createQueryBuilder('m')
      ->select('DISTINCT m.id,m.nome,m.nomeBreve,m.tipo')
      ->join('App:Cattedra', 'c', 'WHERE', 'c.materia=m.id')
      ->where('c.classe=:classe AND c.attiva=:attiva AND c.tipo=:tipo AND m.tipo!=:sostegno')
      ->orderBy('m.ordinamento', 'ASC')
      ->setParameters(['classe' => $alunno->getClasse(), 'attiva' => 1, 'tipo' => 'N', 'sostegno' => 'S'])
      ->getQuery()
      ->getArrayResult();
    foreach ($materie as $mat) {
      $dati['materie'][$mat['id']] = $mat;
    }
    $condotta = $this->em->getRepository('App:Materia')->findOneByTipo('C');
    $dati['materie'][$condotta->getId()] = array(
      'id' => $condotta->getId(),
      'nome' => $condotta->getNome(),
      'nomeBreve' => $condotta->getNomeBreve(),
      'tipo' => $condotta->getTipo());
    // legge i voti
    $voti = $this->em->getRepository('App:VotoScrutinio')->createQueryBuilder('vs')
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
    $esito = $this->em->getRepository('App:Esito')->findOneBy(['scrutinio' => $scrutinio, 'alunno' => $alunno]);
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
      $alunno = $this->em->getRepository('App:Alunno')->find($id);
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
      if ($dati['esito']->getEsito() == 'A' && $insuff_cont > 0 && $alunno->getClasse()->getAnno() != 5) {
        // insufficienze con ammissione (escluse quinte)
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
      if ($dati['esito']->getEsito() != 'N' && $insuff_religione && $alunno->getClasse()->getAnno() != 5) {
        // insuff. religione incoerente con esito (escluse quinte)
        $errore[] = $this->trans->trans('exception.voto_religione_esito', ['%sex%' => $sesso, '%alunno%' => $nome]);
      }
      if ($dati['esito']->getEsito() != 'N' && $insuff_condotta) {
        // insuff. condotta incoerente con esito
        $errore[] = $this->trans->trans('exception.voto_condotta_esito', ['%sex%' => $sesso, '%alunno%' => $nome]);
      }
      if ($dati['esito']->getEsito() == 'S' && $alunno->getClasse()->getAnno() == 5) {
        // sospensione in quinta
        $errore[] = $this->trans->trans('exception.quinta_sospeso_esito', ['%sex%' => $sesso, '%alunno%' => $nome]);
      }
      if ($dati['esito']->getEsito() == 'A' && $alunno->getClasse()->getAnno() == 5 && $insuff_cont > 1) {
        // ammissione in quinta con più insufficienze
        $errore[] = $this->trans->trans('exception.insufficienze_ammissione_quinta', ['%sex%' => $sesso, '%alunno%' => $nome]);
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
    $alunni = $this->em->getRepository('App:Alunno')->createQueryBuilder('a')
      ->select('a.id,a.nome,a.cognome,a.dataNascita,a.religione,a.bes,a.note,a.credito3,a.credito4,e.id AS esito')
      ->join('App:Esito', 'e', 'WHERE', 'a.id=e.alunno')
      ->join('e.scrutinio', 's')
      ->where('a.id in (:lista) AND e.esito=:ammesso AND s.classe=:classe AND s.periodo=:periodo')
      ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
      ->setParameters(['lista' => $lista, 'ammesso' => 'A', 'classe' => $classe, 'periodo' => $periodo])
      ->getQuery()
      ->getArrayResult();
    foreach ($alunni as $alu) {
      $dati['alunni'][$alu['id']] = $alu;
      // legge esito
      $dati['esiti'][$alu['id']] = $this->em->getRepository('App:Esito')->find($alu['esito']);
      // calcola medie se non presenti
      if (!$dati['esiti'][$alu['id']]->getMedia()) {
        // calcola media
        $media = $this->em->getRepository('App:VotoScrutinio')->createQueryBuilder('vs')
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
    $alunni = $this->em->getRepository('App:Alunno')->createQueryBuilder('a')
      ->select('a.id,a.nome,a.cognome,a.dataNascita,a.religione,a.bes,a.note,e.id AS esito')
      ->join('App:Esito', 'e', 'WHERE', 'a.id=e.alunno')
      ->join('e.scrutinio', 's')
      ->where('a.id in (:lista) AND e.esito=:ammesso AND s.classe=:classe AND s.periodo=:periodo')
      ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
      ->setParameters(['lista' => $lista, 'ammesso' => 'A', 'classe' => $classe, 'periodo' => $periodo])
      ->getQuery()
      ->getArrayResult();
    foreach ($alunni as $alu) {
      $dati['alunni'][$alu['id']] = $alu;
      // legge esito
      $dati['esiti'][$alu['id']] = $this->em->getRepository('App:Esito')->find($alu['esito']);
      // calcola medie se non presenti
      if (!$dati['esiti'][$alu['id']]->getMedia()) {
        // calcola media
        $media = $this->em->getRepository('App:VotoScrutinio')->createQueryBuilder('vs')
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
      $competenze = $this->em->getRepository('App:Esito')->createQueryBuilder('e')
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
      $crediti = $this->em->getRepository('App:Esito')->createQueryBuilder('e')
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
    $alunni = $this->em->getRepository('App:Alunno')->createQueryBuilder('a')
      ->select('a.id,a.nome,a.cognome,a.dataNascita,a.sesso,a.religione,a.bes,a.note,e.id AS esito,m.id AS materia_id,m.nomeBreve AS materia')
      ->join('App:Esito', 'e', 'WHERE', 'a.id=e.alunno')
      ->join('e.scrutinio', 's')
      ->join('App:VotoScrutinio', 'vs', 'WHERE', 'vs.scrutinio=s.id AND vs.alunno=a.id')
      ->join('vs.materia', 'm')
      ->where('a.id in (:lista) AND e.esito=:sospeso AND s.classe=:classe AND s.periodo=:periodo AND vs.unico<:suff AND m.tipo=:tipo')
      ->orderBy('a.cognome,a.nome,a.dataNascita,m.ordinamento', 'ASC')
      ->setParameters(['lista' => $lista, 'sospeso' => 'S', 'classe' => $classe, 'periodo' => $periodo, 'suff' => 6, 'tipo' => 'N'])
      ->getQuery()
      ->getArrayResult();
    foreach ($alunni as $alu) {
      $dati['debiti'][$alu['id']][$alu['materia_id']]  = $alu;
      // legge esito
      $dati['esiti'][$alu['id']] = $this->em->getRepository('App:Esito')->find($alu['esito']);
    }
    // carenze
    $alunni = $this->em->getRepository('App:Alunno')->createQueryBuilder('a')
      ->select('a.id,a.nome,a.cognome,a.dataNascita,a.sesso,a.religione,a.bes,a.note,e.id AS esito,m.id AS materia_id,m.nomeBreve AS materia')
      ->join('App:Esito', 'e', 'WHERE', 'a.id=e.alunno')
      ->join('e.scrutinio', 's')
      ->join('App:VotoScrutinio', 'vs', 'WHERE', 'vs.scrutinio=s.id AND vs.alunno=a.id')
      ->join('App:PropostaVoto', 'pv', 'WHERE', 'pv.classe=s.classe AND pv.periodo=s.periodo AND pv.alunno=a.id')
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
        $dati['esiti'][$alu['id']] = $this->em->getRepository('App:Esito')->find($alu['esito']);
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
    $esiti = $this->em->getRepository('App:Esito')->createQueryBuilder('e')
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
    $dati['debiti'] = $this->em->getRepository('App:VotoScrutinio')->createQueryBuilder('vs')
      ->join('vs.scrutinio', 's')
      ->join('vs.materia', 'm')
      ->join('App:Esito', 'e', 'WHERE', 'e.alunno=vs.alunno AND e.scrutinio=s.id')
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
    $dati['carenze'] = $this->em->getRepository('App:VotoScrutinio')->createQueryBuilder('vs')
      ->join('vs.scrutinio', 's')
      ->join('vs.materia', 'm')
      ->join('App:Esito', 'e', 'WHERE', 'e.alunno=vs.alunno AND e.scrutinio=s.id')
      ->join('App:PropostaVoto', 'pv', 'WHERE', 'pv.alunno=vs.alunno AND pv.classe=s.classe AND pv.periodo=s.periodo')
      ->where('vs.alunno=:alunno AND s.classe=:classe AND s.periodo=:periodo AND m.tipo=:tipo AND e.esito IN (:esiti) AND vs.materia=pv.materia AND pv.unico<:suff AND vs.unico>=:suff')
      ->orderBy('m.ordinamento', 'ASC')
      ->setParameters(['alunno' => $alunno, 'classe' => $alunno->getClasse(), 'periodo' => $periodo,
        'tipo' => 'N', 'esiti' => ['A','S'], 'suff' => 6])
      ->getQuery()
      ->getResult();
    // aggiunge proposte
    foreach ($dati['carenze'] as $voto) {
      $proposta = $this->em->getRepository('App:PropostaVoto')->createQueryBuilder('pv')
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
        $this->session->getFlashBag()->add('errore', $this->trans->trans('exception.scrutinio_fine'));
      }
      // se niente errori cambia stato
      if (!$this->session->getFlashBag()->has('errore')) {
        // imposta dati
        $scrutinio->setFine($form->get('fine')->getData());
        // imposta conferma verbale
        $def = $this->em->getRepository('App:DefinizioneScrutinio')->findOneByPeriodo('F');
        $dati_scrutinio = $scrutinio->getDati();
        foreach ($def->getStruttura() as $step=>$args) {
          $dati_scrutinio['verbale'][$step]['validazione'] = $args[1];
          $dati_scrutinio['verbale'][$step]['validato'] = !$args[1];
        }
        $scrutinio->setDati($dati_scrutinio);
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
    $percorso = $this->root.'/finale/'.$classe->getAnno().$classe->getSezione();
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
    // legge alunni scrutinati
    $lista = $this->alunniInScrutinio($classe, 'F');
    // considera solo alunni sospesi
    $sospesi = $this->em->getRepository('App:Esito')->createQueryBuilder('e')
      ->select('a.id,a.nome,a.cognome,a.dataNascita,a.religione,a.bes,a.note')
      ->join('e.scrutinio', 's')
      ->join('e.alunno', 'a')
      ->where('e.alunno in (:lista) AND e.esito=:sospeso AND s.classe=:classe AND s.periodo=:periodo')
      ->setParameters(['lista' => $lista, 'sospeso' => 'S', 'classe' => $classe, 'periodo' => 'F'])
      ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
      ->getQuery()
      ->getArrayResult();
    foreach ($sospesi as $alu) {
      $dati['alunni'][$alu['id']] = $alu;
    }
    // legge materie
    $materie = $this->em->getRepository('App:Materia')->createQueryBuilder('m')
      ->select('DISTINCT m.id,m.nome,m.nomeBreve,m.tipo')
      ->join('App:Cattedra', 'c', 'WHERE', 'c.materia=m.id')
      ->where('c.classe=:classe AND c.attiva=:attiva AND c.tipo=:tipo AND m.tipo!=:sostegno')
      ->orderBy('m.ordinamento', 'ASC')
      ->setParameters(['classe' => $classe, 'attiva' => 1, 'tipo' => 'N', 'sostegno' => 'S'])
      ->getQuery()
      ->getArrayResult();
    foreach ($materie as $mat) {
      $dati['materie'][$mat['id']] = $mat;
    }
    $condotta = $this->em->getRepository('App:Materia')->findOneByTipo('C');
    $dati['materie'][$condotta->getId()] = array(
      'id' => $condotta->getId(),
      'nome' => $condotta->getNome(),
      'nomeBreve' => $condotta->getNomeBreve(),
      'tipo' => $condotta->getTipo());
    // legge i voti dello scrutinio finale
    $voti = $this->em->getRepository('App:VotoScrutinio')->createQueryBuilder('vs')
      ->join('vs.scrutinio', 's')
      ->where('s.classe=:classe AND s.periodo=:periodo AND vs.alunno IN (:sospesi) AND vs.unico IS NOT NULL')
      ->setParameters(['classe' => $classe, 'periodo' => 'F', 'sospesi' => array_keys($dati['alunni'])])
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
  public function passaggioStato_I_N_1(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    $this->session->getFlashBag()->clear();
    // legge dati
    $dati = $this->riepilogoSospesi($docente, $classe, 'I');
    // inserimento voti
    $num = 0;
    foreach ($dati['alunni'] as $alunno=>$alu) {
      $alunno_obj = $this->em->getRepository('App:Alunno')->find($alunno);
      foreach ($dati['materie'] as $materia=>$mat) {
        $materia_obj = $this->em->getRepository('App:Materia')->find($materia);
        // esclude alunni NA per religione
        if ($mat['tipo'] != 'R' || $alu['religione'] == 'S') {
          // inserisce voti e assenze
          $vs = (new VotoScrutinio())
            ->setScrutinio($scrutinio)
            ->setAlunno($alunno_obj)
            ->setMateria($materia_obj)
            ->setUnico($dati['voti'][$alunno][$materia]['unico'])
            ->setRecupero($dati['voti'][$alunno][$materia]['unico'] < 6 ? $dati['voti'][$alunno][$materia]['recupero'] : null)
            ->setAssenze($dati['voti'][$alunno][$materia]['assenze'])
            ->setDati($dati['voti'][$alunno][$materia]['dati']);
          $this->em->persist($vs);
          $num++;
          if ($num % 20 == 0) {
            $this->em->flush();
          }
        }
      }
    }
    $this->em->flush();
    // legge assenze da scrutinio finale
    $scrutinio_F = $this->em->getRepository('App:Scrutinio')->findOneBy(['classe' => $classe, 'periodo' => 'F']);
    $scrutinabili = $scrutinio_F->getDato('scrutinabili');
    // memorizza dati alunni
    $dati_scrutinio = $scrutinio->getDati();
    $dati_scrutinio['sospesi'] = array_keys($dati['alunni']);
    $dati_scrutinio['scrutinabili'] = $scrutinabili;
    $scrutinio->setDati($dati_scrutinio);
    // aggiorna stato
    $scrutinio->setStato('1');
    $this->em->flush();
    // log
    $this->dblogger->write($docente, $request->getClientIp(), 'SCRUTINIO', 'Cambio stato', __METHOD__, array(
      'Scrutinio' => $scrutinio->getId(),
      'Classe' => $classe->getId(),
      'Periodo' => 'I',
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
  public function passaggioStato_I_1_N(Docente $docente, Request $request, Form $form,
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
      'Periodo' => 'I',
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
  public function passaggioStato_I_1_2(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // inizializza messaggi di errore
    $this->session->getFlashBag()->clear();
    // legge dati form
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // controlli
      if (!$form->get('data')->getData()) {
        // data non presente
        $this->session->getFlashBag()->add('errore', $this->trans->trans('exception.scrutinio_data'));
      }
      if (!$form->get('inizio')->getData()) {
        // ora non presente
        $this->session->getFlashBag()->add('errore', $this->trans->trans('exception.scrutinio_inizio'));
      }
      if ($form->get('presiede_ds')->getData() === null) {
        // presidente ds non presente
        $this->session->getFlashBag()->add('errore', $this->trans->trans('exception.scrutinio_presidente'));
      }
      if ($form->get('presiede_ds')->getData() === false && !$form->get('presiede_docente')->getData()) {
        // presidente docente non presente
        $this->session->getFlashBag()->add('errore', $this->trans->trans('exception.scrutinio_presidente'));
      }
      if (!$form->get('segretario')->getData()) {
        // segretario non presente
        $this->session->getFlashBag()->add('errore', $this->trans->trans('exception.scrutinio_segretario'));
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
        $this->session->getFlashBag()->add('errore', $this->trans->trans('exception.scrutinio_presenza'));
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
          'Periodo' => 'I',
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
  public function passaggioStato_I_2_1(Docente $docente, Request $request, Form $form,
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
      'Periodo' => 'I',
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
    $scrutinio = $this->em->getRepository('App:Scrutinio')->createQueryBuilder('s')
      ->where('s.classe=:classe AND s.periodo=:periodo')
      ->setParameters(['classe' => $alunno->getClasse(), 'periodo' => $periodo])
      ->setMaxResults(1)
      ->getQuery()
      ->getOneOrNullResult();
    $dati['scrutinio'] = $scrutinio;
    // legge materie
    $materie = $this->em->getRepository('App:Materia')->createQueryBuilder('m')
      ->select('DISTINCT m.id,m.nome,m.nomeBreve,m.tipo')
      ->join('App:Cattedra', 'c', 'WHERE', 'c.materia=m.id')
      ->where('c.classe=:classe AND c.attiva=:attiva AND c.tipo=:tipo AND m.tipo!=:sostegno')
      ->orderBy('m.ordinamento', 'ASC')
      ->setParameters(['classe' => $alunno->getClasse(), 'attiva' => 1, 'tipo' => 'N', 'sostegno' => 'S'])
      ->getQuery()
      ->getArrayResult();
    foreach ($materie as $mat) {
      $dati['materie'][$mat['id']] = $mat;
    }
    $condotta = $this->em->getRepository('App:Materia')->findOneByTipo('C');
    $dati['materie'][$condotta->getId()] = array(
      'id' => $condotta->getId(),
      'nome' => $condotta->getNome(),
      'nomeBreve' => $condotta->getNomeBreve(),
      'tipo' => $condotta->getTipo());
    // legge solo i voti con debito
    $voti = $this->em->getRepository('App:VotoScrutinio')->createQueryBuilder('vs')
      ->join('vs.materia', 'm')
      ->join('App:Scrutinio', 's', 'WHERE', 's.classe=:classe AND s.periodo=:periodo')
      ->join('App:VotoScrutinio', 'vsf', 'WHERE', 'vsf.scrutinio=s.id AND vsf.materia=m.id AND vsf.alunno=:alunno')
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
    $esito = $this->em->getRepository('App:Esito')->findOneBy(['scrutinio' => $scrutinio, 'alunno' => $alunno]);
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
  public function passaggioStato_I_2_3(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // inizializza
    $this->session->getFlashBag()->clear();
    $errore = array();
    $valutazioni['F']['N'] = ['min' => 0, 'max' => 10, 'start' => 6, 'ticks' => '0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10', 'labels' => '"NC", 1, 2, 3, 4, 5, 6, 7, 8, 9, 10'];
    $valutazioni['F']['C'] = ['min' => 4, 'max' => 10, 'start' => 6, 'ticks' => '4, 5, 6, 7, 8, 9, 10', 'labels' => '"NC", 5, 6, 7, 8, 9, 10'];
    $valutazioni['F']['R'] = ['min' => 20, 'max' => 25, 'start' => 22, 'ticks' => '20, 21, 22, 23, 24, 25', 'labels' => '"NC", "Insuff.", "Suff.", "Buono", "Dist.", "Ottimo"'];
    // alunni della classe
    $lista_id = $this->alunniInScrutinio($classe, 'I');
    foreach ($lista_id as $id) {
      // recupera alunno
      $alunno = $this->em->getRepository('App:Alunno')->find($id);
      $sesso = ($alunno->getSesso() == 'M' ? 'o' : 'a');
      $nome = $alunno->getCognome().' '.$alunno->getNome();
      // elenco voti dell'alunno
      $dati = $this->elencoVotiAlunnoSospeso($docente, $alunno, 'I');
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
      if ($dati['esito']->getDati()['unanimita'] === null && $dati['esito']->getEsito() != 'X') {
        // manca delibera
        $errore[] = $this->trans->trans('exception.delibera_esito', ['%sex%' => $sesso, '%alunno%' => $nome]);
      } elseif ($dati['esito']->getDati()['unanimita'] === false && !$dati['esito']->getDati()['contrari'] && $dati['esito']->getEsito() != 'X') {
        // mancano contrari
        $errore[] = $this->trans->trans('exception.contrari_esito', ['%sex%' => $sesso, '%alunno%' => $nome]);
      }
      if ($dati['esito']->getEsito() == 'N' && !$dati['esito']->getDati()['giudizio']) {
        // manca giudizio
        $errore[] = $this->trans->trans('exception.giudizio_esito', ['%sex%' => $sesso, '%alunno%' => $nome]);
      }
      if ($dati['esito']->getEsito() == 'X' && !$dati['esito']->getDati()['giudizio']) {
        // manca giudizio
        $errore[] = $this->trans->trans('exception.motivo_scrutinio_rinviato', ['%sex%' => $sesso, '%alunno%' => $nome]);
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
        'Periodo' => 'I',
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
  public function passaggioStato_I_3_2(Docente $docente, Request $request, Form $form,
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
      'Periodo' => 'I',
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
  public function passaggioStato_I_3_4(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // inizializza
    $errore = array();
    $this->session->getFlashBag()->clear();
    // alunni della classe
    $lista_id = $this->alunniInScrutinio($classe, 'I');
    // distingue per classe
    if ($classe->getAnno() == 2) {
      // competenze
      $competenze = $this->em->getRepository('App:Esito')->createQueryBuilder('e')
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
      $crediti = $this->em->getRepository('App:Esito')->createQueryBuilder('e')
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
        'Periodo' => 'I',
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
  public function passaggioStato_I_4_3(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // aggiorna stato
    $scrutinio->setStato('3');
    $this->em->flush();
    // log
    $this->dblogger->write($docente, $request->getClientIp(), 'SCRUTINIO', 'Cambio stato', __METHOD__, array(
      'Scrutinio' => $scrutinio->getId(),
      'Classe' => $classe->getId(),
      'Periodo' => 'I',
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
  public function passaggioStato_I_4_C(Docente $docente, Request $request, Form $form,
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
        // imposta conferma verbale
        $def = $this->em->getRepository('App:DefinizioneScrutinio')->findOneByPeriodo('I');
        $dati_scrutinio = $scrutinio->getDati();
        foreach ($def->getStruttura() as $step=>$args) {
          $dati_scrutinio['verbale'][$step]['validazione'] = $args[1];
          $dati_scrutinio['verbale'][$step]['validato'] = !$args[1];
        }
        $scrutinio->setDati($dati_scrutinio);
        // aggiorna stato
        $scrutinio->setStato('C');
        $this->em->flush();
        // log
        $this->dblogger->write($docente, $request->getClientIp(), 'SCRUTINIO', 'Cambio stato', __METHOD__, array(
          'Scrutinio' => $scrutinio->getId(),
          'Classe' => $classe->getId(),
          'Periodo' => 'I',
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
  public function passaggioStato_I_C_4(Docente $docente, Request $request, Form $form,
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
    $percorso = $this->root.'/integrativo/'.$classe->getAnno().$classe->getSezione();
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
      'Periodo' => 'I',
      'Stato iniziale' => 'C',
      'Stato finale' => '4',
      ));
    // ok
    return true;
  }

  /**
   * Recupera i dati per l'inserimento di un argomento per il verbale
   *
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param string $periodo Periodo dello scrutinio
   * @param DefinizioneScrutinio $def Definizione dello scrutinio in corso
   * @param Scrutinio $scrutinio Scrutinio in corso
   * @param array $args Argomenti aggiuntivi (array associativo)
   *
   * @return array Dati formattati come un array associativo
   */
  public function verbaleDatiArgomento(Classe $classe, $periodo, DefinizioneScrutinio $def, Scrutinio $scrutinio, $args) {
    // inizializza
    $dati = array();
    // info argomento
    $num_arg = $args[2]['argomento'];
    $dati['sezione'] = $args[2]['sezione'];
    $dati['argomento'] = $def->getArgomenti()[$num_arg];
    $dati['testo'] = isset($scrutinio->getDati()['argomento'][$num_arg]) ?
      $scrutinio->getDati()['argomento'][$num_arg] : '';
    // restituisce dati
    return $dati;
  }

  /**
   * Gestisce l'inserimento di un argomento per il verbale
   *
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param string $periodo Periodo dello scrutinio
   * @param FormBuilder $form Form per l'inserimento
   * @param array $dati Dati recuperati in precedenza
   * @param int $step Passo della struttura del verbale da modificare
   * @param array $args Argomenti aggiuntivi (array associativo)
   *
   * @return FormType|null Form usato nella pagina di inserimento
   */
  public function verbaleFormArgomento(Classe $classe, $periodo, FormBuilder $form, $dati, $step, $args) {
    // crea form
    $form
      ->setAction($this->router->generate('coordinatore_scrutinio_verbale',
        ['classe' => $classe->getId(), 'periodo' => $periodo, 'step' => $step]))
      ->add('testo', TextareaType::class, array('label' => false,
        'data' => $dati['testo'],
        'attr' => ['rows' => 6],
        'trim' => true,
        'required' => true))
      ->add('submit', SubmitType::class, array('label' => 'label.submit'));
    // restituisce form
    return $form->getForm();
  }

  /**
   * Valida l'inserimento di un argomento per il verbale
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Scrutinio $scrutinio Scrutinio in corso
   * @param FormBuilder $form Form per l'inserimento
   * @param int $step Passo della struttura del verbale da modificare
   * @param array $args Argomenti aggiuntivi (array associativo)
   */
  public function verbaleValidaArgomento(Docente $docente, Request $request, Scrutinio $scrutinio, Form $form, $step, $args) {
    // inizializza messaggi di errore
    $this->session->getFlashBag()->clear();
    // cancella verbale esistente
    $fs = new Filesystem();
    $percorso = null;
    if ($scrutinio->getPeriodo() == 'P') {
      // primo trimestre
      $percorso = $this->root.'/trimestre/'.$scrutinio->getClasse()->getAnno().$scrutinio->getClasse()->getSezione().'/'.
        $scrutinio->getClasse()->getAnno().$scrutinio->getClasse()->getSezione().'-primo-trimestre-verbale.pdf';
    } elseif ($scrutinio->getPeriodo() == 'F') {
      // scrutinio finale
      $percorso = $this->root.'/finale/'.$scrutinio->getClasse()->getAnno().$scrutinio->getClasse()->getSezione().'/'.
        $scrutinio->getClasse()->getAnno().$scrutinio->getClasse()->getSezione().'-scrutinio-finale-verbale.pdf';
    }
    if ($fs->exists($percorso)) {
      // elimina verbale
      $fs->remove($percorso);
    }
    // controlla form
    if ($form->isValid()) {
      // controlli
      if (empty($form->get('testo')->getData())) {
        // testo non presente
        $this->session->getFlashBag()->add('errore', $this->trans->trans('exception.verbale_argomento_vuoto'));
      }
      // se niente errori modifica dati
      if (!$this->session->getFlashBag()->has('errore')) {
        // modifica dati
        $testo = $form->get('testo')->getData();
        $num_arg = $args[2]['argomento'];
        $scrutinio_dati = $scrutinio->getDati();
        $scrutinio_dati['argomento'][$num_arg] = $testo;
        // imposta validazione
        $scrutinio_dati['verbale'][$step]['validato'] = true;
        // memorizza dati
        $scrutinio->setDati($scrutinio_dati);
        $this->em->flush();
        // log
        $this->dblogger->write($docente, $request->getClientIp(), 'SCRUTINIO', 'Modifica verbale', __METHOD__, array(
          'Scrutinio' => $scrutinio->getId(),
          'Classe' => $scrutinio->getClasse()->getId(),
          'Periodo' => $scrutinio->getPeriodo(),
          'Tipo' => 'Argomento',
          'Punto' => $num_arg,
          ));
      }
    } else {
      // imposta messaggi per eventuali altri errori del form
      foreach ($form->getErrors() as $error) {
        $this->session->getFlashBag()->add('errore', $error->getMessage());
      }
    }
  }

  /**
   * Recupera i dati per l'adeguamento ai nuovi crediti
   *
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param string $periodo Periodo dello scrutinio
   * @param DefinizioneScrutinio $def Definizione dello scrutinio in corso
   * @param Scrutinio $scrutinio Scrutinio in corso
   * @param array $args Argomenti aggiuntivi (array associativo)
   *
   * @return array Dati formattati come un array associativo
   */
  public function verbaleDatiNuoviCrediti(Classe $classe, $periodo, DefinizioneScrutinio $def, Scrutinio $scrutinio, $args) {
    // inizializza
    $dati = array();
    $dati['alunni'] = array();
    // solo per quarte e quinte
    if ($classe->getAnno() > 3) {
      // legge alunni
      $alunni_credito = ($def->getDati()['nuovi_crediti'] == null ? [] : $def->getDati()['nuovi_crediti']);
      $lista = $this->alunniInScrutinio($classe, $periodo);
      $alunni = $this->em->getRepository('App:Alunno')->createQueryBuilder('a')
        ->where('a.id in (:lista)')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
        ->setParameters(['lista' => array_intersect($lista, $alunni_credito)])
        ->getQuery()
        ->getResult();
      foreach ($alunni as $alu) {
        $dati['alunni'][$alu->getId()] = $alu;
        $dati['nuovicrediti'][$alu->getId()][0] = isset($scrutinio->getDati()['nuovicrediti'][$alu->getId()][0]) ?
          $scrutinio->getDati()['nuovicrediti'][$alu->getId()][0] : $alu->getCredito3() + $alu->getCredito4();
        $dati['nuovicrediti'][$alu->getId()][1] = isset($scrutinio->getDati()['nuovicrediti'][$alu->getId()][1]) ?
          $scrutinio->getDati()['nuovicrediti'][$alu->getId()][1] : '';
      }
    }
    // info argomento
    $num_arg = $args[2]['argomento'];
    $dati['sezione'] = $args[2]['sezione'];
    $dati['argomento'] = $def->getArgomenti()[$num_arg];
    // restituisce dati
    return $dati;
  }

  /**
   * Gestisce l'adeguamento ai nuovi crediti
   *
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param string $periodo Periodo dello scrutinio
   * @param FormBuilder $form Form per l'inserimento
   * @param array $dati Dati recuperati in precedenza
   * @param int $step Passo della struttura del verbale da modificare
   * @param array $args Argomenti aggiuntivi (array associativo)
   *
   * @return FormType|null Form usato nella pagina di inserimento
   */
  public function verbaleFormNuoviCrediti(Classe $classe, $periodo, FormBuilder $form, $dati, $step, $args) {
    // crea form
    $form
      ->setAction($this->router->generate('coordinatore_scrutinio_verbale',
        ['classe' => $classe->getId(), 'periodo' => $periodo, 'step' => $step]))
      ->add('credito', HiddenType::class, array('label' => false))
      ->add('motivazione', HiddenType::class, array('label' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.submit'));
    // restituisce form
    return $form->getForm();
  }

  /**
   * Valida l'adeguamento ai nuovi crediti
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Scrutinio $scrutinio Scrutinio in corso
   * @param FormBuilder $form Form per l'inserimento
   * @param int $step Passo della struttura del verbale da modificare
   * @param array $args Argomenti aggiuntivi (array associativo)
   */
  public function verbaleValidaNuoviCrediti(Docente $docente, Request $request, Scrutinio $scrutinio, Form $form, $step, $args) {
    // inizializza messaggi di errore
    $this->session->getFlashBag()->clear();
    // dati scrutinio
    $scrutinio_dati = $scrutinio->getDati();
    // cancella verbale/pagelle/documenti esistenti
    $fs = new Filesystem();
    $finder = new Finder();
    $percorso = $this->root.'/finale/'.$scrutinio->getClasse()->getAnno().$scrutinio->getClasse()->getSezione();
    $doc = $scrutinio->getClasse()->getAnno().$scrutinio->getClasse()->getSezione().'-*.pdf';
    if ($fs->exists($percorso)) {
      $finder->files()->in($percorso)->name($doc);
      foreach ($finder as $file) {
        // cancella pagella
        $fs->remove($file);
      }
    }
    // solo per quarte e quinte
    if ($scrutinio->getClasse()->getAnno() <= 3) {
      // niente credito
      $scrutinio_dati['verbale'][$step]['validato'] = true;
      // memorizza dati
      $scrutinio->setDati($scrutinio_dati);
      $this->em->flush();
      // log
      $this->dblogger->write($docente, $request->getClientIp(), 'SCRUTINIO', 'Modifica verbale', __METHOD__, array(
        'Scrutinio' => $scrutinio->getId(),
        'Classe' => $scrutinio->getClasse()->getId(),
        'Periodo' => 'F',
        'Tipo' => 'NuoviCrediti',
        'Punto' => $args[2]['argomento'],
        ));
    } else {
      // legge alunni
      $def = $this->em->getRepository('App:DefinizioneScrutinio')->findOneByPeriodo($scrutinio->getPeriodo());
      $alunni_credito = ($def->getDati()['nuovi_crediti'] == null ? [] : $def->getDati()['nuovi_crediti']);
      $lista = $this->alunniInScrutinio($scrutinio->getClasse(), $scrutinio->getPeriodo());
      $lista_alunni = array_intersect($lista, $alunni_credito);
      if (count($lista_alunni) == 0) {
        // nessun alunno coinvolto
        $scrutinio_dati['verbale'][$step]['validato'] = true;
        // memorizza dati
        $scrutinio->setDati($scrutinio_dati);
        $this->em->flush();
        // log
        $this->dblogger->write($docente, $request->getClientIp(), 'SCRUTINIO', 'Modifica verbale', __METHOD__, array(
          'Scrutinio' => $scrutinio->getId(),
          'Classe' => $scrutinio->getClasse()->getId(),
          'Periodo' => 'F',
          'Tipo' => 'NuoviCrediti',
          'Punto' => $args[2]['argomento'],
          ));
      } else {
        // controlla form
        if ($form->isValid()) {
          // legge alunni
          $alunni = $this->em->getRepository('App:Alunno')->createQueryBuilder('a')
            ->where('a.id in (:lista)')
            ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
            ->setParameters(['lista' => $lista_alunni])
            ->getQuery()
            ->getResult();
          $minimo = ($scrutinio->getClasse()->getAnno() == 4) ? 7 : 15;
          $massimo = ($scrutinio->getClasse()->getAnno() == 4) ? 12 : 25;
          $validato = true;
          foreach ($alunni as $alu) {
            $scrutinio_dati['nuovicrediti'][$alu->getId()][0] = intval($form->get('credito')->getData()[$alu->getId()]);
            $scrutinio_dati['nuovicrediti'][$alu->getId()][1] = trim($form->get('motivazione')->getData()[$alu->getId()]);
            if (($scrutinio_dati['nuovicrediti'][$alu->getId()][0] != 0 && $scrutinio_dati['nuovicrediti'][$alu->getId()][0] < $minimo) ||
                ($scrutinio->getClasse()->getAnno() == 5 && $scrutinio_dati['nuovicrediti'][$alu->getId()][0] < $minimo)) {
              // errore: credito sotto minimo
              $this->session->getFlashBag()->add('errore', $this->trans->trans('exception.nuovo_credito_minimo',
                ['%credito%' => $scrutinio_dati['nuovicrediti'][$alu->getId()][0]]));
              $validato = false;
            }
            if ($scrutinio_dati['nuovicrediti'][$alu->getId()][0] > $massimo) {
              // errore: credito sopra massimo
              $this->session->getFlashBag()->add('errore', $this->trans->trans('exception.nuovo_credito_massimo',
                ['%credito%' => $scrutinio_dati['nuovicrediti'][$alu->getId()][0]]));
              $validato = false;
            }
            if (strlen($scrutinio_dati['nuovicrediti'][$alu->getId()][1]) == 0) {
              // errore: motivazione assente
              $this->session->getFlashBag()->add('errore', $this->trans->trans('exception.nuovo_credito_motivazione'));
              $validato = false;
            }
            // imposta esito
            if ($validato) {
              $esito = $this->em->getRepository('App:Esito')->findOneBy(['alunno' => $alu, 'scrutinio' => $scrutinio]);
              $esito->setCreditoPrecedente($scrutinio_dati['nuovicrediti'][$alu->getId()][0]);
            }
          }
          // imposta validazione
          $scrutinio_dati['verbale'][$step]['validato'] = $validato;
          // memorizza dati
          $scrutinio->setDati($scrutinio_dati);
          $this->em->flush();
          // log
          $this->dblogger->write($docente, $request->getClientIp(), 'SCRUTINIO', 'Modifica verbale', __METHOD__, array(
            'Scrutinio' => $scrutinio->getId(),
            'Classe' => $scrutinio->getClasse()->getId(),
            'Periodo' => 'F',
            'Tipo' => 'NuoviCrediti',
            'Punto' => $args[2]['argomento'],
            ));
        } else {
          // imposta messaggi per eventuali altri errori del form
          foreach ($form->getErrors() as $error) {
            $this->session->getFlashBag()->add('errore', $error->getMessage());
          }
        }
      }
    }
  }

  /**
   * Restituisce la situazione dei voti dello scrutinio finale per gli alunni sospesi con scrutinio rinviato
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Classe $classe Classe relativa alle proposte di voto
   * @param string $periodo Periodo relativo allo scrutinio
   *
   * @return array Dati formattati come un array associativo
   */
  public function riepilogoRinviati(Docente $docente, Classe $classe, $periodo) {
    $dati = array();
    $dati['alunni'] = array();
    // legge alunni sospesi
    $lista = $this->alunniInScrutinio($classe, 'I');
    // considera solo alunni con scrutinio rinviato
    $rinviati = $this->em->getRepository('App:Esito')->createQueryBuilder('e')
      ->select('a.id,a.nome,a.cognome,a.dataNascita,a.religione,a.bes,a.note')
      ->join('e.scrutinio', 's')
      ->join('e.alunno', 'a')
      ->where('e.alunno in (:lista) AND e.esito=:rinviato AND s.classe=:classe AND s.periodo=:periodo')
      ->setParameters(['lista' => $lista, 'rinviato' => 'X', 'classe' => $classe, 'periodo' => 'I'])
      ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
      ->getQuery()
      ->getArrayResult();
    foreach ($rinviati as $alu) {
      $dati['alunni'][$alu['id']] = $alu;
    }
    // legge materie
    $materie = $this->em->getRepository('App:Materia')->createQueryBuilder('m')
      ->select('DISTINCT m.id,m.nome,m.nomeBreve,m.tipo')
      ->join('App:Cattedra', 'c', 'WHERE', 'c.materia=m.id')
      ->where('c.classe=:classe AND c.attiva=:attiva AND c.tipo=:tipo AND m.tipo!=:sostegno')
      ->orderBy('m.ordinamento', 'ASC')
      ->setParameters(['classe' => $classe, 'attiva' => 1, 'tipo' => 'N', 'sostegno' => 'S'])
      ->getQuery()
      ->getArrayResult();
    foreach ($materie as $mat) {
      $dati['materie'][$mat['id']] = $mat;
    }
    $condotta = $this->em->getRepository('App:Materia')->findOneByTipo('C');
    $dati['materie'][$condotta->getId()] = array(
      'id' => $condotta->getId(),
      'nome' => $condotta->getNome(),
      'nomeBreve' => $condotta->getNomeBreve(),
      'tipo' => $condotta->getTipo());
    // legge i voti dello scrutinio finale
    $voti = $this->em->getRepository('App:VotoScrutinio')->createQueryBuilder('vs')
      ->join('vs.scrutinio', 's')
      ->where('s.classe=:classe AND s.periodo=:periodo AND vs.alunno IN (:sospesi) AND vs.unico IS NOT NULL')
      ->setParameters(['classe' => $classe, 'periodo' => 'F', 'sospesi' => array_keys($dati['alunni'])])
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
   * Esegue il passaggio di stato N->1 per lo scrutinio del periodo X
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_X_N_1(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    $this->session->getFlashBag()->clear();
    // legge dati
    $dati = $this->riepilogoRinviati($docente, $classe, 'X');
    // inserimento voti
    $num = 0;
    foreach ($dati['alunni'] as $alunno=>$alu) {
      $alunno_obj = $this->em->getRepository('App:Alunno')->find($alunno);
      foreach ($dati['materie'] as $materia=>$mat) {
        $materia_obj = $this->em->getRepository('App:Materia')->find($materia);
        // esclude alunni NA per religione
        if ($mat['tipo'] != 'R' || $alu['religione'] == 'S') {
          // inserisce voti e assenze
          $vs = (new VotoScrutinio())
            ->setScrutinio($scrutinio)
            ->setAlunno($alunno_obj)
            ->setMateria($materia_obj)
            ->setUnico($dati['voti'][$alunno][$materia]['unico'])
            ->setRecupero($dati['voti'][$alunno][$materia]['unico'] < 6 ? $dati['voti'][$alunno][$materia]['recupero'] : null)
            ->setAssenze($dati['voti'][$alunno][$materia]['assenze'])
            ->setDati($dati['voti'][$alunno][$materia]['dati']);
          $this->em->persist($vs);
          $num++;
          if ($num % 20 == 0) {
            $this->em->flush();
          }
        }
      }
    }
    $this->em->flush();
    // legge assenze da scrutinio finale
    $scrutinio_F = $this->em->getRepository('App:Scrutinio')->findOneBy(['classe' => $classe, 'periodo' => 'F']);
    $scrutinabili = $scrutinio_F->getDato('scrutinabili');
    // memorizza dati alunni
    $dati_scrutinio = $scrutinio->getDati();
    $dati_scrutinio['rinviati'] = array_keys($dati['alunni']);
    $dati_scrutinio['scrutinabili'] = $scrutinabili;
    $scrutinio->setDati($dati_scrutinio);
    // aggiorna stato
    $scrutinio->setStato('1');
    $this->em->flush();
    // log
    $this->dblogger->write($docente, $request->getClientIp(), 'SCRUTINIO', 'Cambio stato', __METHOD__, array(
      'Scrutinio' => $scrutinio->getId(),
      'Classe' => $classe->getId(),
      'Periodo' => 'X',
      'Stato iniziale' => 'N',
      'Stato finale' => '1',
      ));
    // ok
    return true;
  }

  /**
   * Esegue il passaggio di stato 1->N per lo scrutinio del periodo indicato
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_X_1_N(Docente $docente, Request $request, Form $form,
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
      'Periodo' => 'X',
      'Stato iniziale' => '1',
      'Stato finale' => 'N',
      ));
    // ok
    return true;
  }

  /**
   * Esegue il passaggio di stato 1->2 per lo scrutinio del periodo indicato
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_X_1_2(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // inizializza messaggi di errore
    $this->session->getFlashBag()->clear();
    // legge dati form
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // controlli
      if (!$form->get('data')->getData()) {
        // data non presente
        $this->session->getFlashBag()->add('errore', $this->trans->trans('exception.scrutinio_data'));
      }
      if (!$form->get('inizio')->getData()) {
        // ora non presente
        $this->session->getFlashBag()->add('errore', $this->trans->trans('exception.scrutinio_inizio'));
      }
      if ($form->get('presiede_ds')->getData() === null) {
        // presidente ds non presente
        $this->session->getFlashBag()->add('errore', $this->trans->trans('exception.scrutinio_presidente'));
      }
      if ($form->get('presiede_ds')->getData() === false && !$form->get('presiede_docente')->getData()) {
        // presidente docente non presente
        $this->session->getFlashBag()->add('errore', $this->trans->trans('exception.scrutinio_presidente'));
      }
      if (!$form->get('segretario')->getData()) {
        // segretario non presente
        $this->session->getFlashBag()->add('errore', $this->trans->trans('exception.scrutinio_segretario'));
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
        $this->session->getFlashBag()->add('errore', $this->trans->trans('exception.scrutinio_presenza'));
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
          'Periodo' => 'X',
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
   * Esegue il passaggio di stato 2->1 per lo scrutinio del periodo indicato
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_X_2_1(Docente $docente, Request $request, Form $form,
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
      'Periodo' => 'X',
      'Stato iniziale' => '2',
      'Stato finale' => '1',
      ));
    // ok
    return true;
  }

  /**
   * Esegue il passaggio di stato 2->3 per lo scrutinio del periodo indicato
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_X_2_3(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // inizializza
    $this->session->getFlashBag()->clear();
    $errore = array();
    $valutazioni['F']['N'] = ['min' => 0, 'max' => 10, 'start' => 6, 'ticks' => '0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10', 'labels' => '"NC", 1, 2, 3, 4, 5, 6, 7, 8, 9, 10'];
    $valutazioni['F']['C'] = ['min' => 4, 'max' => 10, 'start' => 6, 'ticks' => '4, 5, 6, 7, 8, 9, 10', 'labels' => '"NC", 5, 6, 7, 8, 9, 10'];
    $valutazioni['F']['R'] = ['min' => 20, 'max' => 25, 'start' => 22, 'ticks' => '20, 21, 22, 23, 24, 25', 'labels' => '"NC", "Insuff.", "Suff.", "Buono", "Dist.", "Ottimo"'];
    // alunni della classe
    $lista_id = $this->alunniInScrutinio($classe, 'X');
    foreach ($lista_id as $id) {
      // recupera alunno
      $alunno = $this->em->getRepository('App:Alunno')->find($id);
      $sesso = ($alunno->getSesso() == 'M' ? 'o' : 'a');
      $nome = $alunno->getCognome().' '.$alunno->getNome();
      // elenco voti dell'alunno
      $dati = $this->elencoVotiAlunnoSospeso($docente, $alunno, 'X');
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
      if ($dati['esito']->getDati()['unanimita'] === null && $dati['esito']->getEsito() != 'X') {
        // manca delibera
        $errore[] = $this->trans->trans('exception.delibera_esito', ['%sex%' => $sesso, '%alunno%' => $nome]);
      } elseif ($dati['esito']->getDati()['unanimita'] === false && !$dati['esito']->getDati()['contrari'] && $dati['esito']->getEsito() != 'X') {
        // mancano contrari
        $errore[] = $this->trans->trans('exception.contrari_esito', ['%sex%' => $sesso, '%alunno%' => $nome]);
      }
      if ($dati['esito']->getEsito() == 'N' && !$dati['esito']->getDati()['giudizio']) {
        // manca giudizio
        $errore[] = $this->trans->trans('exception.giudizio_esito', ['%sex%' => $sesso, '%alunno%' => $nome]);
      }
      if ($dati['esito']->getEsito() == 'X' && !$dati['esito']->getDati()['giudizio']) {
        // manca giudizio
        $errore[] = $this->trans->trans('exception.motivo_scrutinio_rinviato', ['%sex%' => $sesso, '%alunno%' => $nome]);
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
        'Periodo' => 'X',
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
  public function passaggioStato_X_3_2(Docente $docente, Request $request, Form $form,
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
      'Periodo' => 'X',
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
  public function passaggioStato_X_3_4(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // inizializza
    $errore = array();
    $this->session->getFlashBag()->clear();
    // alunni della classe
    $lista_id = $this->alunniInScrutinio($classe, 'I');
    // distingue per classe
    if ($classe->getAnno() == 2) {
      // competenze
      $competenze = $this->em->getRepository('App:Esito')->createQueryBuilder('e')
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
      $crediti = $this->em->getRepository('App:Esito')->createQueryBuilder('e')
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
        'Periodo' => 'X',
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
  public function passaggioStato_X_4_3(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // aggiorna stato
    $scrutinio->setStato('3');
    $this->em->flush();
    // log
    $this->dblogger->write($docente, $request->getClientIp(), 'SCRUTINIO', 'Cambio stato', __METHOD__, array(
      'Scrutinio' => $scrutinio->getId(),
      'Classe' => $classe->getId(),
      'Periodo' => 'X',
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
  public function passaggioStato_X_4_C(Docente $docente, Request $request, Form $form,
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
        // imposta conferma verbale
        $def = $this->em->getRepository('App:DefinizioneScrutinio')->findOneByPeriodo('I');
        $dati_scrutinio = $scrutinio->getDati();
        foreach ($def->getStruttura() as $step=>$args) {
          $dati_scrutinio['verbale'][$step]['validazione'] = $args[1];
          $dati_scrutinio['verbale'][$step]['validato'] = !$args[1];
        }
        $scrutinio->setDati($dati_scrutinio);
        // aggiorna stato
        $scrutinio->setStato('C');
        $this->em->flush();
        // log
        $this->dblogger->write($docente, $request->getClientIp(), 'SCRUTINIO', 'Cambio stato', __METHOD__, array(
          'Scrutinio' => $scrutinio->getId(),
          'Classe' => $classe->getId(),
          'Periodo' => 'X',
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
  public function passaggioStato_X_C_4(Docente $docente, Request $request, Form $form,
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
    $percorso = $this->root.'/rinviato/'.$classe->getAnno().$classe->getSezione();
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
      'Periodo' => 'X',
      'Stato iniziale' => 'C',
      'Stato finale' => '4',
      ));
    // ok
    return true;
  }

}
