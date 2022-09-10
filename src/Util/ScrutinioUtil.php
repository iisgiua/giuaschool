<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Util;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
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
use App\Entity\Assenza;
use App\Entity\AssenzaLezione;
use App\Entity\Cattedra;
use App\Entity\Configurazione;
use App\Entity\Entrata;
use App\Entity\Festivita;
use App\Entity\Nota;
use App\Entity\StoricoVoto;
use App\Entity\Uscita;
use App\Util\LogHandler;
use App\Form\ScrutinioPresenza;
use App\Form\ScrutinioPresenzaType;
use App\Form\ScrutinioAssenza;
use App\Form\ScrutinioAssenzaType;
use App\Form\MessageType;


/**
 * ScrutinioUtil - classe di utilità per le funzioni per la gestione dello scrutinio
 *
 * @author Antonello Dessì
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
   * @var RequestStack $reqstack Gestore dello stack delle variabili globali
   */
  private $reqstack;

  /**
   * @var LogHandler $dblogger Gestore dei log su database
   */
  private $dblogger;

  /**
   * @var string $root Directory principale dell'applicazione
   */
  private $root;

  /**
   * @var array $directory Lista delle directory relative ai diversi scrutini
   */
  private $directory;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param RouterInterface $router Gestore delle URL
   * @param EntityManagerInterface $em Gestore delle entità
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param RequestStack $reqstack Gestore dello stack delle variabili globali
   * @param LogHandler $dblogger Gestore dei log su database
   * @param string $root Directory principale dell'applicazione
   */
  public function __construct(RouterInterface $router, EntityManagerInterface $em, TranslatorInterface $trans,
                               RequestStack $reqstack, LogHandler $dblogger, $root) {
    $this->router = $router;
    $this->em = $em;
    $this->trans = $trans;
    $this->reqstack = $reqstack;
    $this->dblogger = $dblogger;
    $this->root = $root;
    // imposta directory per gli scrutini
    $this->directory = array(
      'P' => 'primo',
      'S' => 'secondo',
      'F' => 'finale',
      'G' => 'giudizio-sospeso',
      'R' => 'rinviato',
      'X' => 'rinviato-as-precedente');
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
    $periodi = $this->em->getRepository('App\Entity\DefinizioneScrutinio')->createQueryBuilder('d')
      ->select('d.periodo,s.stato')
      ->leftJoin('App\Entity\Scrutinio', 's', 'WITH', 's.periodo=d.periodo AND s.classe=:classe')
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
   * @param string $tipo Tipo della cattedra di religione (N=religione, A=att.alt, NULL=entrambe)
   * @param string $periodo Periodo relativo allo scrutinio
   *
   * @return array Dati formattati come un array associativo
   */
  public function elencoProposte(Docente $docente, Classe $classe, Materia $materia, $tipo, $periodo) {
    $elenco = array();
    // alunni della classe
    if ($materia->getTipo() == 'R') {
      // religione/att.alt.: solo alunni che si avvalgono
      $lista_alunni = $this->em->getRepository('App\Entity\Alunno')->createQueryBuilder('a')
        ->select('a.id')
        ->where('a.classe=:classe AND a.abilitato=:abilitato AND a.religione IN (:religione)')
        ->setParameters(['classe' => $classe, 'abilitato' => 1,
          'religione' => $tipo ? ($tipo == 'N' ? ['S'] : ['A']) : ['S', 'A']])
        ->getQuery()
        ->getScalarResult();
    } else {
      // non è religione: tutti gli alunni
      $lista_alunni = $this->em->getRepository('App\Entity\Alunno')->createQueryBuilder('a')
        ->select('a.id')
        ->where('a.classe=:classe AND a.abilitato=:abilitato')
        ->setParameters(['classe' => $classe, 'abilitato' => 1])
        ->getQuery()
        ->getScalarResult();
    }
    // legge i dati degli degli alunni
    $alunni = $this->em->getRepository('App\Entity\Alunno')->createQueryBuilder('a')
      ->where('a.id IN (:alunni)')
      ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
      ->setParameters(['alunni' => $lista_alunni])
      ->getQuery()
      ->getResult();
    foreach ($alunni as $alu) {
      $elenco['alunni'][$alu->getId()] = [$alu->getCognome(), $alu->getNome(), $alu->getDataNascita(), $alu->getBes(), $alu->getNoteBes()];
      $elenco['proposte'][$alu->getId()] = null;
    }
    // legge le proposte di voto
    $proposte = $this->em->getRepository('App\Entity\PropostaVoto')->createQueryBuilder('pv')
      ->where('pv.alunno IN (:alunni) AND pv.classe=:classe AND pv.materia=:materia AND pv.periodo=:periodo')
      ->setParameters(['alunni' => $lista_alunni, 'classe' => $classe, 'materia' => $materia, 'periodo' => $periodo]);
    if ($materia->getTipo() == 'E') {
      // ed.civica: proposta differente per docente
      $proposte = $proposte
        ->andWhere('pv.docente=:docente')
        ->setParameter('docente', $docente);
    }
    $proposte = $proposte
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
    // restituisce elenco
    return $elenco;
  }

  /**
   * Restituisce il periodo e lo stato per lo scrutinio attivo della classe
   *
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param boolean $dataProposte Vero per considerare la data delle proposte come apertura
   *
   * @return array Dati formattati come un array associativo
   */
  public function scrutinioAttivo(Classe $classe, $dataProposte=false) {
    $ris = null;
    // data di attivazione
    $dataAttivazione = ($dataProposte ? 'd.data' : 'd.dataProposte');
    // legge definizione scrutini
    $periodi = $this->em->getRepository('App\Entity\DefinizioneScrutinio')->createQueryBuilder('d')
      ->select('d.periodo,s.stato')
      ->leftJoin('App\Entity\Scrutinio', 's', 'WITH', 's.periodo=d.periodo AND s.classe=:classe')
      ->where($dataAttivazione.'<=:data')
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
    $scrutinio = $this->em->getRepository('App\Entity\Scrutinio')->createQueryBuilder('s')
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
    if ($periodo == 'P' || $periodo == 'S') {
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
          // ed.civica
          $dati = $this->quadroVoti($docente, $classe, $periodo);
          break;
        case '3':
          // condotta
          $dati = $this->quadroVoti($docente, $classe, $periodo);
          break;
        case '4':
          // esito
          $dati = $this->quadroVoti($docente, $classe, $periodo);
          break;
        case '5':
          // verbale
          $dati = $this->verbale($docente, $classe, $periodo);
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
          // ed.civica
          $dati = $this->quadroVoti($docente, $classe, $periodo);
          break;
        case '4':
          // condotta
          $dati = $this->quadroVoti($docente, $classe, $periodo);
          break;
        case '5':
          // esito
          $dati = $this->quadroVoti($docente, $classe, $periodo);
          break;
        case '6':
          // credito o competenze
          if ($classe->getAnno() == 2) {
            // competenze
            $dati = $this->quadroCompetenze($docente, $classe, $periodo);
          } elseif ($classe->getAnno() != 1) {
            // crediti
            $dati = $this->quadroCrediti($docente, $classe, $periodo);
          }
          break;
        case '7':
          // debiti e carenze
          if ($classe->getAnno() != 5) {
            // debiti e carenze, escluse le quinte
            $dati = $this->quadroComunicazioni($docente, $classe, $periodo);
          } else {
            // crediti convertiti
            $dati = $this->quadroCrediti($docente, $classe, $periodo);
          }
          break;
        case '8':
          // verbale e fine
          $dati = $this->verbale($docente, $classe, $periodo);
          break;
        case 'C':
          // chiusura
          $dati = $this->chiusura($docente, $classe, $periodo);
          break;
      }
    } elseif ($periodo == 'G' || $periodo == 'R' || $periodo == 'X') {
      // esame sospesi
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
          $dati = $this->quadroVoti($docente, $classe, $periodo);
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
          // verbale e fine
          $dati = $this->verbale($docente, $classe, $periodo);
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
    if ($periodo == 'P' || $periodo == 'S') {
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
          // ed.civica
          $form->setAction($this->router->generate('coordinatore_scrutinio',
              ['classe' => $classe->getId(), 'stato' => '3']));
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
          // verbale
          $form = $this->verbaleForm($classe, $periodo, $form, $dati);
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
          // ed.civica
          $form->setAction($this->router->generate('coordinatore_scrutinio',
              ['classe' => $classe->getId(), 'stato' => '4']));
          break;
        case '4':
          // condotta
          $form->setAction($this->router->generate('coordinatore_scrutinio',
              ['classe' => $classe->getId(), 'stato' => '5']));
          break;
        case '5':
          // esito
          $form->setAction($this->router->generate('coordinatore_scrutinio',
              ['classe' => $classe->getId(), 'stato' => '6']));
          break;
        case '6':
          // credito o competenze
          $form->setAction($this->router->generate('coordinatore_scrutinio',
              ['classe' => $classe->getId(), 'stato' => '7']));
          break;
        case '7':
          // debiti e carenze
          $form->setAction($this->router->generate('coordinatore_scrutinio',
              ['classe' => $classe->getId(), 'stato' => '8']));
          break;
        case '8':
          // verbale e fine
          $form = $this->verbaleForm($classe, $periodo, $form, $dati);
          break;
      }
    } elseif ($periodo == 'G' || $periodo == 'R' || $periodo == 'X') {
      // esame sospesi
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
          // verbale e fine
          $form = $this->verbaleForm($classe, $periodo, $form, $dati);
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
    $scrutinio = $this->em->getRepository('App\Entity\Scrutinio')->createQueryBuilder('s')
      ->where('s.periodo=:periodo AND s.classe=:classe')
      ->setParameters(['periodo' => $periodo, 'classe' => $classe])
      ->setMaxResults(1)
      ->getQuery()
      ->getOneOrNullResult();
    if (!$scrutinio) {
      $visibile = null;
      $definizione = $this->em->getRepository('App\Entity\DefinizioneScrutinio')->findOneByPeriodo($periodo);
      if ($definizione) {
        $visibile = $definizione->getClassiVisibili()[$classe->getAnno()];
      }
      // stato iniziale
      $scrutinio = (new Scrutinio())
        ->setClasse($classe)
        ->setPeriodo($periodo)
        ->setStato('N')
        ->setVisibile($visibile);
      $this->em->persist($scrutinio);
      $this->em->flush();
    }
    // esegue funzione di passaggio stato (se esiste)
    $func = 'passaggioStato_'.(in_array($periodo, ['R', 'X']) ? 'G' : $periodo).
      '_'.$scrutinio->getStato().'_'.$stato;
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
    if ($periodo == 'P') {
      // alunni in classe alla data di fine periodo
      $data = \DateTime::createFromFormat('Y-m-d', $this->reqstack->getSession()->get('/CONFIG/SCUOLA/periodo1_fine'));
      $alunni = $this->em->getRepository('App\Entity\Alunno')->alunniInData($data, $classe);
    } elseif ($periodo == 'S') {
      // alunni in classe alla data di fine periodo
      $data = \DateTime::createFromFormat('Y-m-d', $this->reqstack->getSession()->get('/CONFIG/SCUOLA/periodo2_fine'));
      $alunni = $this->em->getRepository('App\Entity\Alunno')->alunniInData($data, $classe);
    } else {
      // alunni in classe alla data odierna
      $alunni = $this->em->getRepository('App\Entity\Alunno')->createQueryBuilder('a')
        ->select('a.id,a.nome,a.cognome,a.dataNascita,a.religione,a.bes')
        ->where('a.classe=:classe AND a.abilitato=:abilitato')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
        ->setParameters(['classe' => $classe, 'abilitato' => 1])
        ->getQuery()
        ->getResult();
    }
    foreach ($alunni as $alu) {
      $dati['alunni'][$alu['id']] = $alu;
    }
    // legge materie
    $materie = $this->em->getRepository('App\Entity\Materia')->createQueryBuilder('m')
      ->select('DISTINCT m.id,m.nome,m.nomeBreve,m.tipo')
      ->join('App\Entity\Cattedra', 'c', 'WITH', 'c.materia=m.id')
      ->where('c.classe=:classe AND c.attiva=:attiva AND c.tipo=:tipo AND m.tipo!=:sostegno AND m.tipo!=:civica')
      ->orderBy('m.ordinamento', 'ASC')
      ->setParameters(['classe' => $classe, 'attiva' => 1, 'tipo' => 'N', 'sostegno' => 'S', 'civica' => 'E'])
      ->getQuery()
      ->getArrayResult();
    foreach ($materie as $mat) {
      $dati['materie'][$mat['id']] = $mat;
    }
    // legge le proposte di voto
    $proposte = $this->em->getRepository('App\Entity\PropostaVoto')->createQueryBuilder('pv')
      ->join('pv.materia', 'm')
      ->where('pv.classe=:classe AND pv.periodo=:periodo AND pv.unico IS NOT NULL AND m.tipo!=:civica')
      ->setParameters(['classe' => $classe, 'periodo' => $periodo, 'civica' => 'E'])
      ->getQuery()
      ->getResult();
    foreach ($proposte as $p) {
      // inserisce proposte trovate
      $dati['proposte'][$p->getAlunno()->getId()][$p->getMateria()->getId()] = array(
        'id' => $p->getId(),
        'unico' => $p->getUnico(),
        'debito' => $p->getDebito(),
        'recupero' => $p->getRecupero(),
        'docente' => $p->getDocente()->getId(),
        'dati' => $p->getDati());
    }
    // legge le proposte di voto per ed.civica
    $proposte = $this->em->getRepository('App\Entity\PropostaVoto')->createQueryBuilder('pv')
      ->join('pv.materia', 'm')
      ->join('pv.docente', 'd')
      ->where('pv.classe=:classe AND pv.periodo=:periodo AND pv.unico IS NOT NULL AND m.tipo=:civica')
      ->setParameters(['classe' => $classe, 'periodo' => $periodo, 'civica' => 'E'])
      ->orderBy('d.cognome,d.nome')
      ->getQuery()
      ->getResult();
    foreach ($proposte as $p) {
      // inserisce proposte trovate
      $dati['civica'][$p->getAlunno()->getId()][$p->getDocente()->getId()] = array(
        'id' => $p->getId(),
        'unico' => $p->getUnico(),
        'debito' => $p->getDebito(),
        'recupero' => $p->getRecupero(),
        'docente' => $p->getDocente()->getNome().' '.$p->getDocente()->getCognome(),
        'dati' => $p->getDati());
    }
    // legge lista valutazioni
    $valutazioni['R'] = unserialize($this->em->getRepository('App\Entity\Configurazione')->getParametro('voti_finali_R'));
    $valutazioni['E'] = unserialize($this->em->getRepository('App\Entity\Configurazione')->getParametro('voti_finali_E'));
    $valutazioni['C'] = unserialize($this->em->getRepository('App\Entity\Configurazione')->getParametro('voti_finali_C'));
    $valutazioni['N'] = unserialize($this->em->getRepository('App\Entity\Configurazione')->getParametro('voti_finali_N'));
    //-- // retrocompatibilità per A.S 21/22
    //-- if ($periodo == 'P' || $periodo == 'S') {
      //-- $valutazioni['R'] = [
        //-- 'min' => 20,
        //-- 'max' => 26,
        //-- 'suff' => 22,
        //-- 'med' => 22,
        //-- 'valori' => '20,21,22,23,24,25,26',
        //-- 'etichette' => '"NC","","Suff.","","Buono","","Ottimo"',
        //-- 'voti' => '"Non Classificato","Insufficiente","Sufficiente","Discreto","Buono","Distinto","Ottimo"',
        //-- 'votiAbbr' => '"NC","Insufficiente","Sufficiente","Discreto","Buono","Distinto","Ottimo"'];
      //-- $valutazioni['E'] = [
        //-- 'min' => 3,
        //-- 'max' => 10,
        //-- 'suff' => 6,
        //-- 'med' => 5,
        //-- 'valori' => '3,4,5,6,7,8,9,10',
        //-- 'etichette' => '"NC",4,5,6,7,8,9,10',
        //-- 'voti' => '"Non Classificato",4,5,6,7,8,9,10',
        //-- 'votiAbbr' => '"NC",4,5,6,7,8,9,10'];
    //-- }
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
    $listaValori = explode(',', $valutazioni['C']['valori']);
    $listaVoti = explode(',', $valutazioni['C']['votiAbbr']);
    foreach ($listaValori as $key=>$val) {
      $valutazioni['C']['lista'][$val] = trim($listaVoti[$key], '"');
    }
    $listaValori = explode(',', $valutazioni['N']['valori']);
    $listaVoti = explode(',', $valutazioni['N']['votiAbbr']);
    foreach ($listaValori as $key=>$val) {
      $valutazioni['N']['lista'][$val] = trim($listaVoti[$key], '"');
    }
    $dati['valutazioni'] = $valutazioni;
    // controlli
    foreach ($dati['alunni'] as $a=>$alu) {
      foreach ($dati['materie'] as $m=>$mat) {
        // tutte le materie
        $no_recupero = (($periodo == 'F' && $classe->getAnno() == 5) || $mat['tipo'] == 'R');
        if (!isset($dati['proposte'][$a][$m]) && $mat['tipo'] == 'R' && !in_array($alu['religione'], ['S', 'A'])) {
          // religione NA, non fa niente
        } elseif (!isset($dati['proposte'][$a][$m]) && $mat['tipo'] != 'E') {
          // mancano valutazioni (esclusa ed.civica)
          $dati['errori'][$m] = 1;
        } elseif ((!isset($dati['errori'][$m]) || $dati['errori'][$m] == 3) && !$no_recupero &&
                   $dati['proposte'][$a][$m]['unico'] < $dati['valutazioni']['N']['suff'] &&
                   $dati['proposte'][$a][$m]['recupero'] === null) {
          // manca modalità recupero
          $dati['errori'][$m] = 2;
        } elseif (!isset($dati['errori'][$m]) && !$no_recupero &&
                  $dati['proposte'][$a][$m]['unico'] < $dati['valutazioni']['N']['suff'] &&
                  $dati['proposte'][$a][$m]['debito'] === null) {
          // mancano argomenti debito
          $dati['errori'][$m] = 3;
        }
      }
    }
    // imposta avvisi
    $defScrutinio = $this->em->getRepository('App\Entity\DefinizioneScrutinio')->findOneByPeriodo($periodo);
    $oggi = new \DateTime();
    $dati['modifica'] = ($oggi >= $defScrutinio->getData());
    $dati['blocco'] = !$dati['modifica'];
    foreach ($dati['materie'] as $m=>$mat) {
      if (isset($dati['errori'][$m])) {
        switch ($dati['errori'][$m]) {
          case 1:
            // mancano valutazioni
            $this->reqstack->getSession()->getFlashBag()->add('errore', $this->trans->trans('exception.no_voto_scrutinio',
              ['materia' => $mat['nomeBreve']]));
            $dati['blocco'] = true;
            break;
          case 2:
            // manca modalità recupero
            $this->reqstack->getSession()->getFlashBag()->add('avviso', $this->trans->trans('exception.no_recupero_scrutinio',
              ['materia' => $mat['nomeBreve']]));
            break;
          case 3:
            // mancano debiti
            $this->reqstack->getSession()->getFlashBag()->add('avviso', $this->trans->trans('exception.no_debito_scrutinio',
              ['materia' => $mat['nomeBreve']]));
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
    $this->reqstack->getSession()->getFlashBag()->clear();
    $dati = $this->quadroProposte($docente, $classe, 'P');
    if (isset($dati['errori']) && in_array(1, array_values($dati['errori']))) {
      // mancano valutazioni
      return false;
    }
    $this->reqstack->getSession()->getFlashBag()->clear();
    // alunni con voto  in scrutinio
    $alunni_esistenti = $this->em->getRepository('App\Entity\VotoScrutinio')->alunni($scrutinio);
    // materia ed. civica
    $edcivica = $this->em->getRepository('App\Entity\Materia')->findOneByTipo('E');
    $dati['materie'][$edcivica->getId()] = ['id' => $edcivica->getId(), 'nome' => $edcivica->getNome(),
      'nomeBreve' => $edcivica->getNomeBreve(), 'tipo' => $edcivica->getTipo()];
    // conteggio assenze e inserimento voti
    $dati_delibera = serialize(['motivazione' => null, 'unanimita' => true, 'contrari' => null]);
    foreach ($dati['alunni'] as $alunno=>$alu) {
      foreach ($dati['materie'] as $materia=>$mat) {
        // esclude alunni NA per religione
        if (in_array($mat['tipo'], ['N', 'E']) || in_array($alu['religione'], ['S', 'A'])) {
          // calcola assenze di alunno
          $ore = $this->em->getRepository('App\Entity\AssenzaLezione')->createQueryBuilder('al')
            ->select('SUM(al.ore)')
            ->join('al.lezione', 'l')
            ->leftJoin('App\Entity\CambioClasse', 'cc', 'WITH', 'cc.alunno=al.alunno AND l.data BETWEEN cc.inizio AND cc.fine')
            ->where('al.alunno=:alunno AND l.materia=:materia AND l.data BETWEEN :inizio AND :fine AND (l.classe=:classe OR l.classe=cc.classe)')
            ->setParameters(['alunno' => $alunno, 'materia' => $materia,
              'inizio' => $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_inizio'),
              'fine' => $this->reqstack->getSession()->get('/CONFIG/SCUOLA/periodo1_fine'), 'classe' => $classe->getId()])
            ->getQuery()
            ->getSingleScalarResult();
          $ore = ($ore ? ((int) $ore) : 0);
          // inserisce voti e assenze
          if (array_key_exists($alunno, $alunni_esistenti) && in_array($materia, $alunni_esistenti[$alunno])) {
            // aggiorna dati esistenti
            $this->em->getRepository('App\Entity\VotoScrutinio')->createQueryBuilder('vs')
              ->update()
              ->set('vs.modificato', ':modificato')
              ->set('vs.assenze', ':assenze')
              ->where('vs.scrutinio=:scrutinio AND vs.alunno=:alunno AND vs.materia=:materia')
              ->setParameters(['scrutinio' => $scrutinio, 'alunno' => $alunno, 'materia' => $materia,
                'modificato' => new \DateTime(), 'assenze' => $ore])
              ->getQuery()
              ->getResult();
          } else {
            // inserisce nuovi dati
            if ($mat['tipo'] == 'E') {
              // ed.Civica non ha proposte
              $dati['proposte'][$alunno][$materia]['unico'] = null;
              $dati['proposte'][$alunno][$materia]['debito'] = null;
              $dati['proposte'][$alunno][$materia]['recupero'] = null;
            }
            $this->em->getConnection()
              ->prepare('INSERT INTO gs_voto_scrutinio '.
                '(scrutinio_id, alunno_id, materia_id, creato, modificato, unico, debito, recupero, assenze, dati) '.
                'VALUES (:scrutinio,:alunno,:materia,NOW(),NOW(),:unico,:debito,:recupero,:ore,:dati)')
              ->execute(['scrutinio' => $scrutinio->getId(), 'alunno' => $alunno, 'materia' => $materia,
                'unico' => $dati['proposte'][$alunno][$materia]['unico'],
                'debito' => $dati['proposte'][$alunno][$materia]['debito'],
                'recupero' => $dati['proposte'][$alunno][$materia]['recupero'],
                'ore' => $ore,
                'dati' => $dati_delibera]);
          }
        }
      }
    }
    // memorizza alunni
    $dati_scrutinio = $scrutinio->getDati();
    $dati_scrutinio['alunni'] = array_keys($dati['alunni']);
    $dati_scrutinio['valutazioni'] = $dati['valutazioni'];
    $scrutinio->setDati($dati_scrutinio);
    // aggiorna stato
    $scrutinio->setStato('1');
    $this->em->flush();
    // log
    $this->dblogger->logAzione('SCRUTINIO', 'Cambio stato', array(
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
    $this->reqstack->getSession()->getFlashBag()->clear();
   // aggiorna stato
    $scrutinio->setStato('N');
    $this->em->flush();
    // log
    $this->dblogger->logAzione('SCRUTINIO', 'Cambio stato', array(
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
    // periodo dello scrutinio
    $dati['periodo'] = $periodo;
    // legge dati scrutinio
    $scrutinio = $this->em->getRepository('App\Entity\Scrutinio')->createQueryBuilder('s')
      ->where('s.periodo=:periodo AND s.classe=:classe')
      ->setParameters(['periodo' => $periodo, 'classe' => $classe])
      ->setMaxResults(1)
      ->getQuery()
      ->getOneOrNullResult();
    if (!$scrutinio) {
      // errore
      return null;
    }
    // legge docenti del CdC (esclusi potenziamento)
    if ($periodo == 'X') {
      // scrutinio rinviato da prec. A.S. (legge dati da scrutinio)
      $materie = $this->em->getRepository('App\Entity\Materia')->createQueryBuilder('m')
        ->select('m.id,m.nome,m.nomeBreve,m.tipo')
        ->where('m.id IN (:lista)')
        ->orderBy('m.ordinamento', 'ASC')
        ->setParameters(['lista' => $scrutinio->getDato('materie')])
        ->getQuery()
        ->getArrayResult();
      foreach ($materie as $mat) {
        $dati['materie'][$mat['id']] = $mat;
      }
      foreach ($scrutinio->getDato('docenti') as $id=>$docente) {
        foreach ($docente['cattedre'] as $cattedra) {
          $docenti[] = [
            'id' => $id,
            'cognome' => $docente['cognome'],
            'nome' => $docente['nome'],
            'sesso' => $docente['sesso'],
            'tipo' => $cattedra['tipo'],
            'nomeBreve' => $dati['materie'][$cattedra['materia']]['nomeBreve']];
        }
      }
    } else {
      // altri periodi
      $docenti = $this->em->getRepository('App\Entity\Cattedra')->docentiScrutinio($classe);
    }
    foreach ($docenti as $doc) {
      // dati per la visualizzazione della pagina
      $dati['docenti'][$doc['id']][] = $doc;
      $dati['form']['docenti'][$doc['cognome'].' '.$doc['nome'].' (o suo sostituto)'] = $doc['id'];
      // impostazione iniziale dei dati del form
      $dati['scrutinio']['presenze'][$doc['id']] = (new ScrutinioPresenza())
        ->setDocente($doc['id'])
        ->setPresenza(true);
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
    $this->reqstack->getSession()->getFlashBag()->clear();
    // legge dati form
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // controlli
      if (!$form->get('data')->getData()) {
        // data non presente
        $this->reqstack->getSession()->getFlashBag()->add('errore', $this->trans->trans('exception.scrutinio_data'));
      }
      if (!$form->get('inizio')->getData()) {
        // ora non presente
        $this->reqstack->getSession()->getFlashBag()->add('errore', $this->trans->trans('exception.scrutinio_inizio'));
      }
      if ($form->get('presiede_ds')->getData() === null) {
        // presidente ds non presente
        $this->reqstack->getSession()->getFlashBag()->add('errore', $this->trans->trans('exception.scrutinio_presidente'));
      }
      if ($form->get('presiede_ds')->getData() === false && !$form->get('presiede_docente')->getData()) {
        // presidente docente non presente
        $this->reqstack->getSession()->getFlashBag()->add('errore', $this->trans->trans('exception.scrutinio_presidente'));
      }
      if (!$form->get('segretario')->getData()) {
        // segretario non presente
        $this->reqstack->getSession()->getFlashBag()->add('errore', $this->trans->trans('exception.scrutinio_segretario'));
      }
      // controlli sui presenti
      $errore_presenza = false;
      foreach ($form->get('lista')->getData() as $doc=>$val) {
        if (!$val || (!$val->getPresenza() && (!$val->getSostituto() || !$val->getSurrogaProtocollo() ||
            !$val->getSurrogaData()))) {
          $errore_presenza = true;
        }
      }
      if ($errore_presenza) {
        // docente non presente
        $this->reqstack->getSession()->getFlashBag()->add('errore', $this->trans->trans('exception.scrutinio_presenza'));
      }
      // se niente errori cambia stato
      if (!$this->reqstack->getSession()->getFlashBag()->has('errore')) {
        // dati docenti
        $docenti = $this->em->getRepository('App\Entity\Cattedra')->docentiScrutinio($classe);
        // memorizza dati docenti e materie
        $dati_docenti = array();
        foreach ($docenti as $doc) {
          $dati_docenti[$doc['id']][$doc['materia_id']] = $doc['tipo'];
        }
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
        $valori['docenti'] = $dati_docenti;
        $scrutinio->setDati($valori);
        // aggiorna stato
        $scrutinio->setStato('2');
        $this->em->flush();
        // log
        $this->dblogger->logAzione('SCRUTINIO', 'Cambio stato', array(
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
        $this->reqstack->getSession()->getFlashBag()->add('errore', $error->getMessage());
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
    $this->reqstack->getSession()->getFlashBag()->clear();
    // aggiorna stato
    $scrutinio->setStato('1');
    $this->em->flush();
    // log
    $this->dblogger->logAzione('SCRUTINIO', 'Cambio stato', array(
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
    // periodo dello scrutinio
    $dati['periodo'] = $periodo;
    // legge dati scrutinio
    $scrutinio = $this->em->getRepository('App\Entity\Scrutinio')->findOneBy(['classe' => $classe,
      'periodo' => $periodo]);
    if (!$scrutinio) {
      // errore
      return $dati;
    }
    // valutazioni
    $dati['valutazioni'] = $scrutinio->getDato('valutazioni');
    // legge alunni
    $lista = $this->alunniInScrutinio($classe, $periodo);
    $alunni = $this->em->getRepository('App\Entity\Alunno')->createQueryBuilder('a')
      ->select('a.id,a.nome,a.cognome,a.dataNascita,a.religione,a.bes')
      ->where('a.id in (:lista)')
      ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
      ->setParameters(['lista' => $lista])
      ->getQuery()
      ->getArrayResult();
    foreach ($alunni as $alu) {
      $dati['alunni'][$alu['id']] = $alu;
      if ($periodo == 'X') {
        // scrutinio rinviato da prec. A.S. (legge dati da scrutinio)
        $dati['alunni'][$alu['id']]['religione'] = $scrutinio->getDato('religione')[$alu['id']];
        $dati['alunni'][$alu['id']]['bes'] = $scrutinio->getDato('bes')[$alu['id']];
      }
    }
    // legge materie
    if ($periodo == 'X') {
      // scrutinio rinviato da prec. A.S. (legge dati da scrutinio)
      $materie = $this->em->getRepository('App\Entity\Materia')->createQueryBuilder('m')
        ->select('m.id,m.nome,m.nomeBreve,m.tipo,m.media')
        ->where('m.id IN (:lista) AND m.tipo!=:sostegno')
        ->orderBy('m.ordinamento', 'ASC')
        ->setParameters(['lista' => $scrutinio->getDato('materie'), 'sostegno' => 'S'])
        ->getQuery()
        ->getArrayResult();
      foreach ($materie as $mat) {
        $dati['materie'][$mat['id']] = $mat;
      }
    } else {
      // scrutini altri periodi
      $materie = $this->em->getRepository('App\Entity\Materia')->createQueryBuilder('m')
        ->select('DISTINCT m.id,m.nome,m.nomeBreve,m.tipo')
        ->join('App\Entity\Cattedra', 'c', 'WITH', 'c.materia=m.id')
        ->where('c.classe=:classe AND c.attiva=:attiva AND c.tipo=:tipo AND m.tipo!=:sostegno')
        ->orderBy('m.ordinamento', 'ASC')
        ->setParameters(['classe' => $classe, 'attiva' => 1, 'tipo' => 'N', 'sostegno' => 'S'])
        ->getQuery()
        ->getArrayResult();
      foreach ($materie as $mat) {
        $dati['materie'][$mat['id']] = $mat;
      }
      $condotta = $this->em->getRepository('App\Entity\Materia')->findOneByTipo('C');
      $dati['materie'][$condotta->getId()] = array(
        'id' => $condotta->getId(),
        'nome' => $condotta->getNome(),
        'nomeBreve' => $condotta->getNomeBreve(),
        'tipo' => $condotta->getTipo());
    }
    // legge i voti
    $voti = $this->em->getRepository('App\Entity\VotoScrutinio')->createQueryBuilder('vs')
      ->join('vs.scrutinio', 's')
      ->where('s.classe=:classe AND s.periodo=:periodo AND vs.unico IS NOT NULL AND vs.alunno IN (:lista)')
      ->setParameters(['classe' => $classe, 'periodo' => $periodo, 'lista' => $lista])
      ->getQuery()
      ->getResult();
    $somma = array();
    $numero = array();
    foreach ($voti as $v) {
      // inserisce voti
      $dati['voti'][$v->getAlunno()->getId()][$v->getMateria()->getId()] = array(
        'id' => $v->getId(),
        'unico' => $v->getUnico(),
        'recupero' => $v->getRecupero(),
        'debito' => $v->getDebito(),
        'dati' => $v->getDati());
      if ($v->getMateria()->getMedia()) {
        // calcolo medie
        if (!isset($somma[$v->getAlunno()->getId()])) {
          $somma[$v->getAlunno()->getId()] = 0;
          $numero[$v->getAlunno()->getId()] = 0;
        }
        $somma[$v->getAlunno()->getId()] +=
          ($v->getUnico() == $dati['valutazioni'][$v->getMateria()->getTipo()]['min']) ? 0 : $v->getUnico();
        $numero[$v->getAlunno()->getId()]++;
      }
    }
    // calcola medie
    foreach ($somma as $alu=>$s) {
      $dati['medie'][$alu] = number_format($somma[$alu] / $numero[$alu], 2, ',', null);
    }
    // esiti
    if ($periodo != 'P' && $periodo != 'S') {
      // legge esiti
      $esiti = $this->em->getRepository('App\Entity\Esito')->createQueryBuilder('e')
        ->join('e.scrutinio', 's')
        ->where('e.alunno IN (:lista) AND s.classe=:classe AND s.periodo=:periodo')
        ->setParameters(['lista' => $lista, 'classe' => $classe, 'periodo' => $periodo])
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
    $scrutinio = $this->em->getRepository('App\Entity\Scrutinio')->createQueryBuilder('s')
      ->where('s.classe=:classe AND s.periodo=:periodo')
      ->setParameters(['classe' => $classe, 'periodo' => $periodo])
      ->setMaxResults(1)
      ->getQuery()
      ->getOneOrNullResult();
    // alunni della classe
    $lista_id = $this->alunniInScrutinio($classe, $periodo);
    $alunni = $this->em->getRepository('App\Entity\Alunno')->createQueryBuilder('a')
      ->where('a.id IN (:lista)')
      ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
      ->setParameters(['lista' => $lista_id])
      ->getQuery()
      ->getResult();
    foreach ($alunni as $alu) {
      // salta chi non si avvale in religione
      if ($materia->getTipo() != 'R' || in_array($alu->getReligione(), ['S', 'A'])) {
        $elenco['alunni'][$alu->getId()] = [$alu->getCognome(), $alu->getNome(), $alu->getDataNascita()];
        // inserisce voto nullo (conserva ordine)
        $elenco['voti'][$alu->getId()] = null;
      }
    }
    // legge i voti
    $voti = $this->em->getRepository('App\Entity\VotoScrutinio')->createQueryBuilder('vs')
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
            ->addDato('contrari', null)
            ->addDato('contrari_motivazione', null);
          $this->em->persist($elenco['voti'][$alu->getId()]);
        }
      }
    }
    // restituisce elenco
    return $elenco;
  }

  /**
   * Restituisce la lista dei voti dello scrutinio rinviato per la materia indicata
   *
   * @param Docente $docente Docente che inserisce le proposte di voto
   * @param Classe $classe Classe relativa alle proposte di voto
   * @param Materia $materia Materia relativa alle proposte di voto
   * @param string $periodo Periodo relativo allo scrutinio
   *
   * @return array Dati formattati come un array associativo
   */
  public function elencoVotiRinviati(Docente $docente, Classe $classe, Materia $materia, $periodo) {
    $elenco = array();
    $elenco['voti'] = array();
    // legge scrutinio
    $scrutinio = $this->em->getRepository('App\Entity\Scrutinio')->findOneBy(['classe' => $classe, 'periodo' => $periodo]);
    // alunni della classe
    $alunni = $this->em->getRepository('App\Entity\Alunno')->createQueryBuilder('a')
      ->select('a.id,a.nome,a.cognome,a.dataNascita')
      ->where('a.id IN (:lista)')
      ->setParameters(['lista' => $scrutinio->getDato('alunni')])
      ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
      ->getQuery()
      ->getArrayResult();
    foreach ($alunni as $alu) {
      // salta chi non si avvale in religione
      if ($materia->getTipo() != 'R' || in_array($scrutinio->getDato('religione')[$alu['id']], ['S', 'A'])) {
        $elenco['alunni'][$alu['id']] = [$alu['cognome'], $alu['nome'], $alu['dataNascita']];
        // inserisce voto nullo (conserva ordine)
        $elenco['voti'][$alu['id']] = null;
      }
    }
    // legge i voti
    $voti = $this->em->getRepository('App\Entity\VotoScrutinio')->createQueryBuilder('vs')
      ->where('vs.scrutinio=:scrutinio AND vs.materia=:materia AND vs.alunno IN (:lista)')
      ->setParameters(['scrutinio' => $scrutinio, 'materia' => $materia,
        'lista' => array_keys($elenco['alunni'])])
      ->getQuery()
      ->getResult();
    foreach ($voti as $v) {
      // inserisce voti
      $elenco['voti'][$v->getAlunno()->getId()] = $v;
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
    $this->reqstack->getSession()->getFlashBag()->clear();
    // legge ed.civica
    $edcivica = $this->em->getRepository('App\Entity\Materia')->findOneByTipo('E');
    // elenco voti/alunni
    $dati = $this->elencoVoti($docente, $classe, $edcivica, 'P');
    // controlla errori
    $errore = array();
    foreach ($dati['voti'] as $alunno=>$voto) {
      if ($voto->getUnico() === null) {
        // voto non presente
        $errore['exception.voto_edcivica'] = true;
      }
    }
    // imposta messaggi di errore
    foreach ($errore as $msg=>$v) {
      $this->reqstack->getSession()->getFlashBag()->add('errore',
          $this->trans->trans($msg, ['materia' => $edcivica->getNomeBreve()]));
    }
    if (empty($errore)) {
      // aggiorna stato
      $scrutinio->setStato('3');
      $this->em->flush();
      // log
      $this->dblogger->logAzione('SCRUTINIO', 'Cambio stato', array(
        'Scrutinio' => $scrutinio->getId(),
        'Classe' => $classe->getId(),
        'Periodo' => 'P',
        'Stato iniziale' => '2',
        'Stato finale' => '3',
        ));
      // ok
      return true;
    }
    // errori presenti
    return false;
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
    $this->reqstack->getSession()->getFlashBag()->clear();
    // legge condotta
    $condotta = $this->em->getRepository('App\Entity\Materia')->findOneByTipo('C');
    // elenco voti/alunni
    $dati = $this->elencoVoti($docente, $classe, $condotta, 'P');
    // controlla errori
    $errore = array();
    foreach ($dati['voti'] as $alunno=>$voto) {
      if ($voto->getUnico() === null) {
        // voto non presente
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
    if (empty($errore)) {
      // aggiorna stato
      $scrutinio->setStato('4');
      $this->em->flush();
      // log
      $this->dblogger->logAzione('SCRUTINIO', 'Cambio stato', array(
        'Scrutinio' => $scrutinio->getId(),
        'Classe' => $classe->getId(),
        'Periodo' => 'P',
        'Stato iniziale' => '3',
        'Stato finale' => '4',
        ));
      // ok
      return true;
    }
    // imposta messaggi di errore
    foreach ($errore as $msg=>$v) {
      $this->reqstack->getSession()->getFlashBag()->add('errore', $this->trans->trans($msg));
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
    // inizializza messaggi di errore
    $this->reqstack->getSession()->getFlashBag()->clear();
    // aggiorna stato
    $scrutinio->setStato('2');
    $this->em->flush();
    // log
    $this->dblogger->logAzione('SCRUTINIO', 'Cambio stato', array(
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
  public function passaggioStato_P_4_5(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // inizializza messaggi di errore
    $this->reqstack->getSession()->getFlashBag()->clear();
    // legge voti
    $dati = $this->quadroVoti($docente, $classe, 'P');
    // controlli
    $errori = array();
    foreach ($dati['alunni'] as $a=>$alu) {
      foreach ($dati['materie'] as $m=>$mat) {
        if ($mat['tipo'] == 'R') {
          // religione
          if (in_array($alu['religione'], ['S', 'A']) && !isset($dati['voti'][$a][$m])) {
            // mancano valutazioni
            $errori[$m] = 1;
          }
        } elseif (in_array($mat['tipo'], ['N', 'E'])) {
          // altre materie (esclusa condotta, compresa ed.civica)
          if (!isset($dati['voti'][$a][$m]['unico'])) {
            // mancano valutazioni
            $errori[$m] = 1;
          //-- } elseif ((!isset($errori[$m]) || $errori[$m] == 3) &&
                     //-- $dati['voti'][$a][$m]['unico'] < 6 && !$dati['voti'][$a][$m]['recupero']) {
            //-- // mancano recuperi
            //-- $errori[$m] = 2;
          //-- } elseif (!isset($errori[$m]) && $dati['voti'][$a][$m]['unico'] < 6 && !$dati['voti'][$a][$m]['debito']) {
            // mancano debiti
            //-- $errori[$m] = 3;
          }
        } else {
          // condotta
          if (!isset($dati['voti'][$a][$m]['unico'])) {
            // mancano valutazioni
            $errori[$m] = 1;
          } elseif (!isset($errori[$m]) && empty($dati['voti'][$a][$m]['dati']['motivazione'])) {
            // manca motivazione
            $errori[$m] = 11;
          } elseif (!isset($errori[$m]) && $dati['voti'][$a][$m]['dati']['unanimita'] === null) {
            // manca delibera
            $errori[$m] = 12;
          } elseif (!isset($errori[$m]) && $dati['voti'][$a][$m]['dati']['unanimita'] === false &&
                    empty($dati['voti'][$a][$m]['dati']['contrari'])) {
            // mancano contrari
            $errori[$m] = 13;
          }
        }
      }
    }
    if (empty($errori)) {
      // legge definizione scrutinio e verbale
      $def = $this->em->getRepository('App\Entity\DefinizioneScrutinio')->findOneByPeriodo('P');
      $scrutinio_dati = $scrutinio->getDati();
      foreach ($def->getStruttura() as $step=>$args) {
        if ($args[0] == 'Argomento') {
          // resetta validazione
          $scrutinio_dati['verbale'][$step]['validato'] = false;
        }
      }
      // memorizza dati scrutinio
      $scrutinio->setDati($scrutinio_dati);
      // aggiorna stato
      $scrutinio->setStato('5');
      $this->em->flush();
      // log
      $this->dblogger->logAzione('SCRUTINIO', 'Cambio stato', array(
        'Scrutinio' => $scrutinio->getId(),
        'Classe' => $classe->getId(),
        'Periodo' => 'P',
        'Stato iniziale' => '4',
        'Stato finale' => '5',
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
            $this->reqstack->getSession()->getFlashBag()->add('errore', $this->trans->trans('exception.no_voto_scrutinio',
              ['materia' => $mat['nomeBreve']]));
            break;
          case 2:
            // mancano recuperi
            $this->reqstack->getSession()->getFlashBag()->add('errore', $this->trans->trans('exception.no_recupero_scrutinio',
              ['materia' => $mat['nomeBreve']]));
            break;
          case 3:
            // mancano debiti
            $this->reqstack->getSession()->getFlashBag()->add('errore', $this->trans->trans('exception.no_debito_scrutinio',
              ['materia' => $mat['nomeBreve']]));
            break;
          case 11:
            // manca motivazione
            $this->reqstack->getSession()->getFlashBag()->add('errore', $this->trans->trans('exception.motivazione_condotta'));
            break;
          case 12:
            // manca delibera
            $this->reqstack->getSession()->getFlashBag()->add('errore', $this->trans->trans('exception.delibera_condotta'));
            break;
          case 13:
            // mancano contrari
            $this->reqstack->getSession()->getFlashBag()->add('errore', $this->trans->trans('exception.contrari_condotta'));
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
    $this->reqstack->getSession()->getFlashBag()->clear();
    // aggiorna stato
    $scrutinio->setStato('3');
    $this->em->flush();
    // log
    $this->dblogger->logAzione('SCRUTINIO', 'Cambio stato', array(
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
  public function passaggioStato_P_5_4(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // inizializza messaggi di errore
    $this->reqstack->getSession()->getFlashBag()->clear();
    // aggiorna stato
    $scrutinio->setStato('4');
    $this->em->flush();
    // log
    $this->dblogger->logAzione('SCRUTINIO', 'Cambio stato', array(
      'Scrutinio' => $scrutinio->getId(),
      'Classe' => $classe->getId(),
      'Periodo' => 'P',
      'Stato iniziale' => '5',
      'Stato finale' => '4',
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
    $scrutinio = $this->em->getRepository('App\Entity\Scrutinio')->createQueryBuilder('s')
      ->where('s.periodo=:periodo AND s.classe=:classe')
      ->setParameters(['periodo' => $periodo, 'classe' => $classe])
      ->setMaxResults(1)
      ->getQuery()
      ->getOneOrNullResult();
    // esiti
    if ($periodo == 'F' || $periodo == 'G' || $periodo == 'X') {
      $lista = $this->alunniInScrutinio($classe, $periodo);
      $esiti = $this->em->getRepository('App\Entity\Esito')->createQueryBuilder('e')
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
  public function passaggioStato_P_5_C(Docente $docente, Request $request, Form $form,
                                       Classe $classe, Scrutinio $scrutinio) {
    // inizializza messaggi di errore
    $this->reqstack->getSession()->getFlashBag()->clear();
    // legge dati form
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // controlli
      if (!$form->get('fine')->getData()) {
        // ora non presente
        $this->reqstack->getSession()->getFlashBag()->add('errore', $this->trans->trans('exception.scrutinio_fine'));
      }
      // controlla validazione argomenti
      $def = $this->em->getRepository('App\Entity\DefinizioneScrutinio')->findOneByPeriodo('P');
      if (!isset($scrutinio->getDati()['verbale'])) {
        // errore di validazione
        $this->reqstack->getSession()->getFlashBag()->add('errore', $this->trans->trans('exception.verbale_argomento_mancante',
          ['sezione' => '']));
      } else {
        foreach ($scrutinio->getDati()['verbale'] as $step=>$args) {
          // solo elementi da validare
          if (isset($args['validato']) && !$args['validato']) {
            // errore di validazione
            $this->reqstack->getSession()->getFlashBag()->add('errore', $this->trans->trans('exception.verbale_argomento_mancante',
              ['sezione' => $def->getStruttura()[$step][2]['sezione']]));
          }
        }
      }
      // se niente errori cambia stato
      if (!$this->reqstack->getSession()->getFlashBag()->has('errore')) {
        // imposta ora fine
        $scrutinio->setFine($form->get('fine')->getData());
        // aggiorna stato
        $scrutinio->setStato('C');
        $this->em->flush();
        // log
        $this->dblogger->logAzione('SCRUTINIO', 'Cambio stato', array(
          'Scrutinio' => $scrutinio->getId(),
          'Classe' => $classe->getId(),
          'Periodo' => 'P',
          'Stato iniziale' => '5',
          'Stato finale' => 'C',
          ));
        // ok
        return true;
      }
    } else {
      // imposta messaggi per eventuali altri errori del form
      foreach ($form->getErrors() as $error) {
        $this->reqstack->getSession()->getFlashBag()->add('errore', $error->getMessage());
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
  public function passaggioStato_P_C_5(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // controlla se è possibile riapertura
    if (!($docente instanceOf Staff) || $scrutinio->getSincronizzazione()) {
      // errore
      return false;
    }
    // inizializza messaggi di errore
    $this->reqstack->getSession()->getFlashBag()->clear();
    // rinomina documenti di classe
    $fs = new Filesystem();
    $finder = new Finder();
    $percorso = $this->root.'/'.$this->directory['P'].'/'.$classe->getAnno().$classe->getSezione();
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
    $scrutinio->setStato('5');
    $this->em->flush();
    // log
    $this->dblogger->logAzione('SCRUTINIO', 'Cambio stato', array(
      'Scrutinio' => $scrutinio->getId(),
      'Classe' => $classe->getId(),
      'Periodo' => 'P',
      'Stato iniziale' => 'C',
      'Stato finale' => '5',
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
    // periodo dello scrutinio
    $dati['periodo'] = $periodo;
    // legge alunni
    $lista = $this->alunniInScrutinio($classe, $periodo);
    if ($periodo == 'P' || $periodo == 'S') {
      // dati alunni
      $alunni = $this->em->getRepository('App\Entity\Alunno')->createQueryBuilder('a')
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
      $debiti = $this->em->getRepository('App\Entity\VotoScrutinio')->createQueryBuilder('vs')
        ->select('DISTINCT a.id,a.nome,a.cognome,a.dataNascita')
        ->join('vs.scrutinio', 's')
        ->join('vs.materia', 'm')
        ->join('vs.alunno', 'a')
        ->where('s.classe=:classe AND s.periodo=:periodo AND m.tipo IN (:tipo) AND vs.unico IS NOT NULL AND vs.unico<:suff AND a.id IN (:lista)')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
        ->setParameters(['suff' => 6, 'classe' => $classe, 'periodo' => $periodo, 'tipo' => ['N', 'E'], 'lista' => $lista])
        ->getQuery()
        ->getResult();
      $dati['debiti'] = array();
      foreach ($debiti as $deb) {
        $dati['debiti'][$deb['id']] = $deb;
      }
    } elseif ($periodo == 'F') {
      // legge i non ammessi/non scrutinati per assenze
      $non_ammessi = $this->em->getRepository('App\Entity\Alunno')->createQueryBuilder('a')
        ->select('a.id')
        ->join('App\Entity\Esito', 'e', 'WITH', 'e.alunno=a.id')
        ->join('e.scrutinio', 's')
        ->where('a.id IN (:lista) AND e.esito=:esito AND s.classe=:classe AND s.periodo=:periodo')
        ->setParameters(['lista' => $lista, 'esito' => 'N', 'classe' => $classe, 'periodo' => $periodo])
        ->getQuery()
        ->getArrayResult();
      $non_ammessi = array_column($non_ammessi, 'id');
      $scrutinio = $this->em->getRepository('App\Entity\Scrutinio')->createQueryBuilder('s')
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
      $dati['non_ammessi'] = $this->em->getRepository('App\Entity\Alunno')->createQueryBuilder('a')
        ->select('a.id,a.nome,a.cognome,a.dataNascita')
        ->where('a.id IN (:lista)')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
        ->setParameters(['lista' => array_merge($non_ammessi,
          ($scrutinio->getDato('cessata_frequenza') == null ? [] : $scrutinio->getDato('cessata_frequenza')))])
        ->getQuery()
        ->getArrayResult();
      // legge i debiti
      $dati['debiti']  = $this->em->getRepository('App\Entity\Alunno')->createQueryBuilder('a')
        ->select('a.id,a.nome,a.cognome,a.dataNascita')
        ->join('App\Entity\Esito', 'e', 'WITH', 'e.alunno=a.id')
        ->join('e.scrutinio', 's')
        ->where('a.id in (:lista) AND e.esito=:sospeso AND s.classe=:classe AND s.periodo=:periodo')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
        ->setParameters(['lista' => $lista, 'sospeso' => 'S', 'classe' => $classe, 'periodo' => $periodo])
        ->getQuery()
        ->getArrayResult();
      // legge le carenze
      $alunni = $this->em->getRepository('App\Entity\Alunno')->createQueryBuilder('a')
        ->select('a.id,a.nome,a.cognome,a.dataNascita,e.dati')
        ->join('App\Entity\Esito', 'e', 'WITH', 'e.alunno=a.id')
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
    } elseif ($periodo == 'G' || $periodo == 'R' || $periodo == 'X') {
      // legge i non ammessi
      $dati['non_ammessi'] = $this->em->getRepository('App\Entity\Alunno')->createQueryBuilder('a')
        ->select('a.id,a.nome,a.cognome,a.dataNascita')
        ->join('App\Entity\Esito', 'e', 'WITH', 'e.alunno=a.id')
        ->join('e.scrutinio', 's')
        ->where('a.id IN (:lista) AND e.esito=:esito AND s.classe=:classe AND s.periodo=:periodo')
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
        ->setParameters(['lista' => $lista, 'esito' => 'N', 'classe' => $classe, 'periodo' => $periodo])
        ->getQuery()
        ->getArrayResult();
    }
    // legge ammessi
    $dati['ammessi'] = $this->em->getRepository('App\Entity\Alunno')->createQueryBuilder('a')
      ->select('COUNT(a.id)')
      ->join('App\Entity\Esito', 'e', 'WITH', 'e.alunno=a.id')
      ->join('e.scrutinio', 's')
      ->where('a.id IN (:lista) AND e.esito=:esito AND s.classe=:classe AND s.periodo=:periodo')
      ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
      ->setParameters(['lista' => $lista, 'esito' => 'A', 'classe' => $classe, 'periodo' => $periodo])
      ->getQuery()
      ->getSingleScalarResult();
    // controlla se attivare pulsante riapertura o no
    $scrutinio = $this->em->getRepository('App\Entity\Scrutinio')->createQueryBuilder('s')
      ->where('s.classe=:classe AND s.periodo=:periodo AND s.stato=:stato AND (s.sincronizzazione IS NULL OR s.sincronizzazione=:vuota)')
      ->setParameters(['classe' => $classe, 'periodo' => $periodo, 'stato' => 'C', 'vuota' => ''])
      ->getQuery()
      ->getOneOrNullResult();
    $dati['precedente'] = ($docente instanceOf Staff) && $scrutinio;
    // restituisce dati
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
    $scrutinio = $this->em->getRepository('App\Entity\Scrutinio')->findOneBy(['periodo' => $periodo, 'classe' => $classe]);
    if ($periodo == 'P' || $periodo == 'S') {
      // solo gli alunni al momento dello scrutinio
      $alunni = $scrutinio->getDato('alunni');
    } elseif ($periodo == 'F') {
      // legge lista alunni scrutinabili
      return array_keys($scrutinio->getDato('scrutinabili'));
    } elseif ($periodo == 'G' || $periodo == 'R') {
      // legge lista alunni sospesi
      $sospesi = ($scrutinio ? $scrutinio->getDati()['sospesi'] : []);
      // restituisce lista di ID
      return $sospesi;
    } elseif ($periodo == 'X') {
      // legge lista alunni con scrutinio rinviato prec. A.S.
      $rinviati = $scrutinio->getDato('alunni');
      // restituisce lista di ID
      return $rinviati;
    }
    // restituisce lista di ID
    return $alunni;
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
    $this->reqstack->getSession()->getFlashBag()->clear();
    $dati = $this->quadroProposte($docente, $classe, 'F');
    if (isset($dati['errori']) && in_array(1, array_values($dati['errori']))) {
      // mancano valutazioni
      return false;
    }
    $this->reqstack->getSession()->getFlashBag()->clear();
    // alunni con voto in scrutinio
    $alunni_esistenti = $this->em->getRepository('App\Entity\VotoScrutinio')->alunni($scrutinio);
    // materia ed. civica
    $edcivica = $this->em->getRepository('App\Entity\Materia')->findOneByTipo('E');
    $dati['materie'][$edcivica->getId()] = ['id' => $edcivica->getId(), 'nome' => $edcivica->getNome(),
      'nomeBreve' => $edcivica->getNomeBreve(), 'tipo' => $edcivica->getTipo()];
    // conteggio assenze e inserimento voti
    $dati_delibera = serialize(array('motivazione' => null, 'unanimita' => true, 'contrari' => null));
    foreach ($dati['alunni'] as $alunno=>$alu) {
      foreach ($dati['materie'] as $materia=>$mat) {
        // esclude alunni NA per religione
        if (in_array($mat['tipo'], ['N', 'E']) || in_array($alu['religione'], ['S', 'A'])) {
          // calcola assenze di alunno
          $ore = $this->em->getRepository('App\Entity\AssenzaLezione')->createQueryBuilder('al')
            ->select('SUM(al.ore)')
            ->join('al.lezione', 'l')
            ->leftJoin('App\Entity\CambioClasse', 'cc', 'WITH', 'cc.alunno=al.alunno AND l.data BETWEEN cc.inizio AND cc.fine')
            ->where('al.alunno=:alunno AND l.materia=:materia AND l.data>:inizio AND l.data<=:fine AND (l.classe=:classe OR l.classe=cc.classe)')
            ->setParameters(['alunno' => $alunno, 'materia' => $materia,
              'inizio' => (empty($this->reqstack->getSession()->get('/CONFIG/SCUOLA/periodo3_nome')) ? $this->reqstack->getSession()->get('/CONFIG/SCUOLA/periodo1_fine') : $this->reqstack->getSession()->get('/CONFIG/SCUOLA/periodo2_fine')),
              'fine' => $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_fine'), 'classe' => $classe->getId()])
            ->getQuery()
            ->getSingleScalarResult();
          $ore = ($ore ? ((int) $ore) : 0);
          // controllo ore assenze mat.alt. firmata con altra materia
          if ($mat['tipo'] == 'R' && $alu['religione'] == 'A') {
            $ore2 = $this->em->getRepository('App\Entity\AssenzaLezione')->createQueryBuilder('al')
              ->select('SUM(al.ore)')
              ->join('al.lezione', 'l')
              ->leftJoin('App\Entity\CambioClasse', 'cc', 'WITH', 'cc.alunno=al.alunno AND l.data BETWEEN cc.inizio AND cc.fine')
              ->join('App\Entity\Firma', 'f', 'WITH', 'l.id=f.lezione')
              ->join('App\Entity\Cattedra', 'c', 'WITH', 'c.attiva=:attiva AND c.docente=f.docente AND (c.classe=:classe OR c.classe=cc.classe) AND c.materia=:materia AND c.tipo=:alternativa')
              ->where('al.alunno=:alunno AND l.materia!=:materia AND l.data>:inizio AND l.data<=:fine AND (l.classe=:classe OR l.classe=cc.classe)')
              ->setParameters(['alunno' => $alunno, 'materia' => $materia,
                'inizio' => (empty($this->reqstack->getSession()->get('/CONFIG/SCUOLA/periodo3_nome')) ? $this->reqstack->getSession()->get('/CONFIG/SCUOLA/periodo1_fine') : $this->reqstack->getSession()->get('/CONFIG/SCUOLA/periodo2_fine')),
                'fine' => $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_fine'), 'classe' => $classe->getId(),
                'attiva' => 1, 'alternativa' => 'A'])
              ->getQuery()
              ->getSingleScalarResult();
            $ore += ($ore2 ? ((int) $ore2) : 0);
          }
          // inserisce voti e assenze
          if (array_key_exists($alunno, $alunni_esistenti) && in_array($materia, $alunni_esistenti[$alunno])) {
            // aggiorna dati esistenti
            $this->em->getRepository('App\Entity\VotoScrutinio')->createQueryBuilder('vs')
              ->update()
              ->set('vs.modificato', ':modificato')
              ->set('vs.assenze', ':assenze')
              ->where('vs.scrutinio=:scrutinio AND vs.alunno=:alunno AND vs.materia=:materia')
              ->setParameters(['scrutinio' => $scrutinio, 'alunno' => $alunno, 'materia' => $materia,
                'modificato' => new \DateTime(), 'assenze' => $ore])
              ->getQuery()
              ->getResult();
          } else {
            // inserisce nuovi dati
            if ($mat['tipo'] == 'E') {
              // ed.Civica non ha proposte
              $dati['proposte'][$alunno][$materia]['unico'] = null;
              $dati['proposte'][$alunno][$materia]['debito'] = null;
              $dati['proposte'][$alunno][$materia]['recupero'] = null;
            }
            $this->em->getConnection()
              ->prepare('INSERT INTO gs_voto_scrutinio '.
                '(scrutinio_id, alunno_id, materia_id, creato, modificato, unico, debito, recupero, assenze, dati) '.
                'VALUES (:scrutinio,:alunno,:materia,NOW(),NOW(),:unico,:debito,:recupero,:assenze,:dati)')
              ->execute(['scrutinio' => $scrutinio->getId(), 'alunno' => $alunno, 'materia' => $materia,
                'unico' => $dati['proposte'][$alunno][$materia]['unico'],
                'debito' => $dati['proposte'][$alunno][$materia]['debito'],
                'recupero' => $dati['proposte'][$alunno][$materia]['recupero'],
                'assenze' => $ore,
                'dati' => $dati_delibera]);
          }
        }
      }
    }
    $this->em->flush();
    // memorizza alunni
    $dati_scrutinio = $scrutinio->getDati();
    $dati_scrutinio['alunni'] = array_keys($dati['alunni']);
    $dati_scrutinio['valutazioni'] = $dati['valutazioni'];
    $scrutinio->setDati($dati_scrutinio);
    // aggiorna stato
    $scrutinio->setStato('1');
    $this->em->flush();
    // log
    $this->dblogger->logAzione('SCRUTINIO', 'Cambio stato', array(
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
    $this->reqstack->getSession()->getFlashBag()->clear();
    // aggiorna stato
    $scrutinio->setStato('N');
    $this->em->flush();
    // log
    $this->dblogger->logAzione('SCRUTINIO', 'Cambio stato', array(
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
    $this->reqstack->getSession()->getFlashBag()->clear();
    // legge dati form
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // controlli
      if (!$form->get('data')->getData()) {
        // data non presente
        $this->reqstack->getSession()->getFlashBag()->add('errore', $this->trans->trans('exception.scrutinio_data'));
      }
      if (!$form->get('inizio')->getData()) {
        // ora non presente
        $this->reqstack->getSession()->getFlashBag()->add('errore', $this->trans->trans('exception.scrutinio_inizio'));
      }
      if ($form->get('presiede_ds')->getData() === null) {
        // presidente ds non presente
        $this->reqstack->getSession()->getFlashBag()->add('errore', $this->trans->trans('exception.scrutinio_presidente'));
      }
      if ($form->get('presiede_ds')->getData() === false && !$form->get('presiede_docente')->getData()) {
        // presidente docente non presente
        $this->reqstack->getSession()->getFlashBag()->add('errore', $this->trans->trans('exception.scrutinio_presidente'));
      }
      if (!$form->get('segretario')->getData()) {
        // segretario non presente
        $this->reqstack->getSession()->getFlashBag()->add('errore', $this->trans->trans('exception.scrutinio_segretario'));
      }
      // controlli sui presenti
      $errore_presenza = false;
      foreach ($form->get('lista')->getData() as $doc=>$val) {
        if (!$val || (!$val->getPresenza() && (!$val->getSostituto() || !$val->getSurrogaProtocollo() ||
            !$val->getSurrogaData()))) {
          $errore_presenza = true;
        }
      }
      if ($errore_presenza) {
        // docente non presente
        $this->reqstack->getSession()->getFlashBag()->add('errore', $this->trans->trans('exception.scrutinio_presenza'));
      }
      // se niente errori cambia stato
      if (!$this->reqstack->getSession()->getFlashBag()->has('errore')) {
        // dati docenti
        $docenti = $this->em->getRepository('App\Entity\Cattedra')->docentiScrutinio($classe);
        // memorizza dati docenti e materie
        $dati_docenti = array();
        foreach ($docenti as $doc) {
          $dati_docenti[$doc['id']][$doc['materia_id']] = $doc['tipo'];
        }
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
        $valori['docenti'] = $dati_docenti;
        $scrutinio->setDati($valori);
        // aggiorna stato
        $scrutinio->setStato('2');
        $this->em->flush();
        // log
        $this->dblogger->logAzione('SCRUTINIO', 'Cambio stato', array(
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
        $this->reqstack->getSession()->getFlashBag()->add('errore', $error->getMessage());
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
    // aggiorna stato
    $scrutinio->setStato('1');
    $this->em->flush();
    // log
    $this->dblogger->logAzione('SCRUTINIO', 'Cambio stato', array(
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
    $dati['estero'] = array();
    // legge scrutinio finale e intermedi
    $scrutinio_F = $this->em->getRepository('App\Entity\Scrutinio')->findOneBy(['periodo' => 'F', 'classe' => $classe]);
    $scrutinio_S = $this->em->getRepository('App\Entity\Scrutinio')->findOneBy(['periodo' => 'S', 'classe' => $classe]);
    $scrutinio_P = $this->em->getRepository('App\Entity\Scrutinio')->findOneBy(['periodo' => 'P', 'classe' => $classe]);
    if (!$scrutinio_F || !$scrutinio_P) {
      // errore
      return null;
    }
    $listaScrutini = $scrutinio_S ? [$scrutinio_P, $scrutinio_S, $scrutinio_F] : [$scrutinio_P, $scrutinio_F];
    // calcola limite assenze
    $dati['monteore'] = $classe->getOreSettimanali() * 33;
    $dati['maxassenze'] = (int) ($dati['monteore'] / 4);
    // calcola ore totali assenza alunni (compresi cambi classe in periodi intermedi)
    $alunni = $this->em->getRepository('App\Entity\Alunno')->createQueryBuilder('a')
      ->select('a.id,a.cognome,a.nome,a.sesso,a.dataNascita,SUM(vs.assenze) AS ore')
      ->join('App\Entity\VotoScrutinio', 'vs', 'WITH', 'vs.alunno=a.id')
      ->join('vs.scrutinio', 's')
      ->leftJoin('App\Entity\CambioClasse', 'cc', 'WITH', 'cc.alunno=a.id')
      ->where('a.id IN (:alunni) AND (s.id IN (:scrutini) OR (s.classe=cc.classe AND s.periodo IN (:periodi)))')
      ->groupBy('a.id')
      ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
      ->setParameters(['alunni' => $scrutinio_F->getDati()['alunni'], 'scrutini' => $listaScrutini,
        'periodi' => ['P', 'S']])
      ->getQuery()
      ->getArrayResult();
    // legge dati scrutinio
    $scrutinio_dati = $scrutinio_F->getDati();
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
    // alunni all'estero
    $alunni = $this->em->getRepository('App\Entity\Alunno')->createQueryBuilder('a')
      ->select('a.id,a.nome,a.cognome,a.sesso,a.dataNascita,a.bes,cc.note')
      ->join('App\Entity\CambioClasse', 'cc', 'WITH', 'cc.alunno=a.id AND cc.classe=:classe')
      ->where('a.frequenzaEstero=:estero AND a.classe IS NULL AND a.abilitato=:abilitato')
      ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
      ->setParameters(['classe' => $classe, 'estero' => 1, 'abilitato' => 0])
      ->getQuery()
      ->getArrayResult();
    foreach ($alunni as $a) {
      $dati['estero'][$a['id']] = $a;
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
      substr($this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_fine'), 0, 4).'-03-15');
    $fine = \DateTime::createFromFormat('!Y-m-d', $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_fine'));
    // festivi
    $festivi = $this->em->getRepository('App\Entity\Festivita')->createQueryBuilder('f')
      ->select('f.data')
      ->where('f.tipo=:festivo AND f.data BETWEEN :inizio AND :fine AND (f.sede IS NULL OR f.sede=:sede)')
      ->orderBy('f.data', 'ASC')
      ->setParameters(['festivo' => 'F', 'inizio' => $inizio->format('Y-m-d'), 'fine' => $fine->format('Y-m-d'),
        'sede' => $classe->getSede()])
      ->getQuery()
      ->getScalarResult();
    $giorni_festivi = array_column($festivi, 'data');
    $giorni_settimana = array($this->reqstack->getSession()->get('/CONFIG/SCUOLA/giorni_festivi_istituto'));
    $altri_festivi = explode(',', $this->reqstack->getSession()->get('/CONFIG/SCUOLA/giorni_festivi_classi'));
    foreach($altri_festivi  as $f) {
      // formato <settimana>:<classe_anno><classe_sezione>
      if (strlen($f) > 0 && $classe->getAnno() == $f[2] && $classe->getSezione() == $f[3]) {
        $giorni_settimana[] = $f[0];
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
    $inizio = substr($this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_fine'), 0, 4).'-03-15';
    $fine = $this->reqstack->getSession()->get('/CONFIG/SCUOLA/anno_fine');
    // assenze
    $giorni_assenza = $this->em->getRepository('App\Entity\Assenza')->createQueryBuilder('a')
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
      $giorni_note = $this->em->getRepository('App\Entity\Nota')->createQueryBuilder('n')
        ->select('n.data')
        ->join('n.alunni', 'a')
        ->where('a.id=:alunno AND n.tipo=:nota AND n.data IN (:date)')
        ->setParameters(['alunno' => $alunno_id, 'nota' => 'I', 'date' => $giorni_presenza_str])
        ->getQuery()
        ->getScalarResult();
      $giorni_entrate = $this->em->getRepository('App\Entity\Entrata')->createQueryBuilder('e')
        ->select('e.data')
        ->where('e.alunno=:alunno AND e.data IN (:date)')
        ->setParameters(['alunno' => $alunno_id, 'date' => $giorni_presenza_str])
        ->getQuery()
        ->getScalarResult();
      $giorni_uscite = $this->em->getRepository('App\Entity\Uscita')->createQueryBuilder('u')
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
    $this->reqstack->getSession()->getFlashBag()->clear();
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
        $this->reqstack->getSession()->getFlashBag()->add('errore', $this->trans->trans('exception.scrutinio_tipo_scrutinabile'));
      }
      if ($errore_motivazione) {
        // non inserita motivazione di deroga
        $this->reqstack->getSession()->getFlashBag()->add('errore', $this->trans->trans('exception.scrutinio_motivazione_deroga'));
      }
      if ($errore_alunni) {
        // lista alunni no scrutinabili errata
        $this->reqstack->getSession()->getFlashBag()->add('errore', $this->trans->trans('exception.scrutinio_lista_no_scrutinabili'));
      }
      // se niente errori cambia stato
      if (!$this->reqstack->getSession()->getFlashBag()->has('errore')) {
        // imposta dati
        $dati_scrutini = $scrutinio->getDati();
        $scrutinio->setDati(array());   // necessario per bug di aggiornamento
        $this->em->flush();             // necessario per bug di aggiornamento
        $dati_scrutini['monteore'] = $dati['monteore'];
        $dati_scrutini['maxassenze'] = $dati['maxassenze'];
        // dati alunni
        $dati_scrutini['estero'] = array_keys($dati['estero']);
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
        $this->dblogger->logAzione('SCRUTINIO', 'Cambio stato', array(
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
        $this->reqstack->getSession()->getFlashBag()->add('errore', $error->getMessage());
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
    $this->dblogger->logAzione('SCRUTINIO', 'Cambio stato', array(
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
    // inizializza messaggi di errore
    $this->reqstack->getSession()->getFlashBag()->clear();
    // legge ed.civica
    $edcivica = $this->em->getRepository('App\Entity\Materia')->findOneByTipo('E');
    // elenco voti/alunni
    $dati = $this->elencoVoti($docente, $classe, $edcivica, 'F');
    // controlla errori
    $errore = array();
    foreach ($dati['voti'] as $alunno=>$voto) {
      if ($voto->getUnico() === null) {
        // voto non presente
        $errore['exception.voto_edcivica'] = true;
      }
    }
    // imposta messaggi di errore
    foreach ($errore as $msg=>$v) {
      $this->reqstack->getSession()->getFlashBag()->add('errore',
          $this->trans->trans($msg, ['materia' => $edcivica->getNomeBreve()]));
    }
    if (empty($errore)) {
      // aggiorna stato
      $scrutinio->setStato('4');
      $this->em->flush();
      // log
      $this->dblogger->logAzione('SCRUTINIO', 'Cambio stato', array(
        'Scrutinio' => $scrutinio->getId(),
        'Classe' => $classe->getId(),
        'Periodo' => 'F',
        'Stato iniziale' => '3',
        'Stato finale' => '4',
      ));
      // ok
      return true;
    }
    // errori presenti
    return false;
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
  public function passaggioStato_F_4_5(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // inizializza messaggi di errore
    $this->reqstack->getSession()->getFlashBag()->clear();
    // legge condotta
    $condotta = $this->em->getRepository('App\Entity\Materia')->findOneByTipo('C');
    // elenco voti/alunni
    $dati = $this->elencoVoti($docente, $classe, $condotta, 'F');
    // controlla errori
    $errore = array();
    foreach ($dati['voti'] as $alunno=>$voto) {
      if (!$voto->getUnico()) {
        // voto non presente
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
    // imposta eventuali messaggi di errore
    foreach ($errore as $msg=>$v) {
      $this->reqstack->getSession()->getFlashBag()->add('errore', $this->trans->trans($msg));
    }
    if (empty($errore)) {
      // aggiorna stato
      $scrutinio->setStato('5');
      $this->em->flush();
      // log
      $this->dblogger->logAzione('SCRUTINIO', 'Cambio stato', array(
        'Scrutinio' => $scrutinio->getId(),
        'Classe' => $classe->getId(),
        'Periodo' => 'F',
        'Stato iniziale' => '4',
        'Stato finale' => '5',
        ));
      // ok
      return true;
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
    $this->dblogger->logAzione('SCRUTINIO', 'Cambio stato', array(
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
    $scrutinio = $this->em->getRepository('App\Entity\Scrutinio')->createQueryBuilder('s')
      ->where('s.classe=:classe AND s.periodo=:periodo')
      ->setParameters(['classe' => $alunno->getClasse(), 'periodo' => $periodo])
      ->setMaxResults(1)
      ->getQuery()
      ->getOneOrNullResult();
    $dati['scrutinio'] = $scrutinio;
    // legge materie
    $materie = $this->em->getRepository('App\Entity\Materia')->createQueryBuilder('m')
      ->select('DISTINCT m.id,m.nome,m.nomeBreve,m.tipo')
      ->join('App\Entity\Cattedra', 'c', 'WITH', 'c.materia=m.id')
      ->where('c.classe=:classe AND c.attiva=:attiva AND c.tipo=:tipo AND m.tipo!=:sostegno')
      ->orderBy('m.ordinamento', 'ASC')
      ->setParameters(['classe' => $alunno->getClasse(), 'attiva' => 1, 'tipo' => 'N', 'sostegno' => 'S'])
      ->getQuery()
      ->getArrayResult();
    foreach ($materie as $mat) {
      $dati['materie'][$mat['id']] = $mat;
    }
    $condotta = $this->em->getRepository('App\Entity\Materia')->findOneByTipo('C');
    $dati['materie'][$condotta->getId()] = array(
      'id' => $condotta->getId(),
      'nome' => $condotta->getNome(),
      'nomeBreve' => $condotta->getNomeBreve(),
      'tipo' => $condotta->getTipo());
    // legge i voti
    $voti = $this->em->getRepository('App\Entity\VotoScrutinio')->createQueryBuilder('vs')
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
    $esito = $this->em->getRepository('App\Entity\Esito')->findOneBy(['scrutinio' => $scrutinio, 'alunno' => $alunno]);
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
  public function passaggioStato_F_5_6(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // inizializza
    $this->reqstack->getSession()->getFlashBag()->clear();
    $errore = array();
    $valutazioni = $scrutinio->getDato('valutazioni');
    // alunni della classe
    $lista_id = $this->alunniInScrutinio($classe, 'F');
    $errore_condotta = array();
    foreach ($lista_id as $id) {
      // recupera alunno
      $alunno = $this->em->getRepository('App\Entity\Alunno')->find($id);
      $sesso = ($alunno->getSesso() == 'M' ? 'o' : 'a');
      $nome = $alunno->getCognome().' '.$alunno->getNome();
      // elenco voti dell'alunno
      $dati = $this->elencoVotiAlunno($docente, $alunno, 'F');
      $mediaSomma = 0;
      $mediaNum = 0;
      // controlla errori
      $no_voto = 0;
      $insuff_cont = 0;
      $insuff_religione = false;
      $insuff_condotta = false;
      foreach ($dati['voti'] as $key=>$voto) {
        // controllo voto
        if ($voto->getUnico() === null || $voto->getUnico() < $valutazioni[$voto->getMateria()->getTipo()]['min'] ||
            $voto->getUnico() > $valutazioni[$voto->getMateria()->getTipo()]['max']) {
          // voto non ammesso o non presente
          $no_voto++;
        } elseif ($voto->getMateria()->getTipo() == 'R' && $voto->getUnico() < $valutazioni['R']['suff']) {
          // voto religione insufficiente
          $insuff_religione = true;
          $insuff_cont++;
        } elseif ($voto->getMateria()->getTipo() == 'C' && $voto->getUnico() < $valutazioni['C']['suff']) {
          // voto condotta insufficiente
          $insuff_condotta = true;
          $insuff_cont++;
        } elseif ($voto->getUnico() < $valutazioni[$voto->getMateria()->getTipo()]['suff']) {
          // voto insufficiente
          $insuff_cont++;
        }
        // controlli sulla condotta
        if ($voto->getMateria()->getTipo() == 'C') {
          if (!$voto->getDato('motivazione')) {
            // manca motivazione
            $errore_condotta['exception.motivazione_condotta'] = true;
          }
          if ($voto->getDato('unanimita') === null) {
            // manca delibera
            $errore_condotta['exception.delibera_condotta'] = true;
          } elseif ($voto->getDato('unanimita') === false && empty($voto->getDato('contrari'))) {
            // mancano contrari
            $errore_condotta['exception.contrari_condotta'] = true;
          }
        }
        // calcolo media
        if ($voto->getMateria()->getMedia()) {
          // somma voti
          $mediaSomma +=
            ($voto->getUnico() == $valutazioni[$voto->getMateria()->getTipo()]['min']) ? 0 : $voto->getUnico();
          $mediaNum++;
        }
      }
      if ($no_voto > 0) {
        // voti non presenti
        $errore[] = $this->trans->trans('exception.no_voto_esito', ['sex' => $sesso, 'alunno' => $nome]);
      }
      if ($dati['esito']->getEsito() === null) {
        // manca esito
        $errore[] = $this->trans->trans('exception.manca_esito', ['sex' => $sesso, 'alunno' => $nome]);
      }
      if ($dati['esito']->getDati()['unanimita'] === null) {
        // manca delibera
        $errore[] = $this->trans->trans('exception.delibera_esito', ['sex' => $sesso, 'alunno' => $nome]);
      } elseif ($dati['esito']->getDati()['unanimita'] === false && empty($dati['esito']->getDati()['contrari'])) {
        // mancano contrari
        $errore[] = $this->trans->trans('exception.contrari_esito', ['sex' => $sesso, 'alunno' => $nome]);
      }
      if ($dati['esito']->getEsito() == 'N' && empty($dati['esito']->getDati()['giudizio'])) {
        // manca giudizio
        $errore[] = $this->trans->trans('exception.giudizio_esito', ['sex' => $sesso, 'alunno' => $nome]);
      }
      if ($dati['esito']->getEsito() == 'A' && $insuff_cont > 0 && $alunno->getClasse()->getAnno() != 5) {
        // insufficienze con ammissione (escluse quinte)
        $errore[] = $this->trans->trans('exception.insufficienze_ammissione_esito', ['sex' => $sesso, 'alunno' => $nome]);
      }
      if ($dati['esito']->getEsito() == 'N' && $insuff_cont == 0) {
        // solo sufficienze con non ammissione
        $errore[] = $this->trans->trans('exception.sufficienze_non_ammissione_esito', ['sex' => $sesso, 'alunno' => $nome]);
      }
      if ($dati['esito']->getEsito() == 'S' && $insuff_cont == 0) {
        // solo sufficienze con sospensione
        $errore[] = $this->trans->trans('exception.sufficienze_sospensione_esito', ['sex' => $sesso, 'alunno' => $nome]);
      }
      if ($dati['esito']->getEsito() == 'S' && $insuff_religione) {
        // insuff. religione incoerente con esito sospeso
        $errore[] = $this->trans->trans('exception.voto_religione_esito', ['sex' => $sesso, 'alunno' => $nome]);
      }
      if ($dati['esito']->getEsito() == 'S' && $insuff_condotta) {
        // insuff. condotta incoerente con esito
        $errore[] = $this->trans->trans('exception.voto_condotta_esito', ['sex' => $sesso, 'alunno' => $nome]);
      }
      if ($dati['esito']->getEsito() == 'S' && $insuff_cont > 3) {
        // giudizio sospeso con più di 3 materie
        $errore[] = $this->trans->trans('exception.num_materie_sospeso', ['sex' => $sesso, 'alunno' => $nome]);
      }
      if ($dati['esito']->getEsito() == 'S' && $alunno->getClasse()->getAnno() == 5) {
        // sospensione in quinta
        $errore[] = $this->trans->trans('exception.quinta_sospeso_esito', ['sex' => $sesso, 'alunno' => $nome]);
      }
      if ($dati['esito']->getEsito() == 'A' && $alunno->getClasse()->getAnno() == 5 && $insuff_cont > 1) {
        // ammissione in quinta con più insufficienze
        $errore[] = $this->trans->trans('exception.insufficienze_ammissione_quinta', ['sex' => $sesso, 'alunno' => $nome]);
      } elseif ($dati['esito']->getEsito() == 'A' && $alunno->getClasse()->getAnno() == 5 &&
                $insuff_cont == 1 && empty($dati['esito']->getDati()['giudizio'])) {
        // ammissione in quinta con una insufficienza ma senza motivazione
        $errore[] = $this->trans->trans('exception.motivazione_ammissione_quinta', ['sex' => $sesso, 'alunno' => $nome]);
      } elseif ($dati['esito']->getEsito() == 'A' && $alunno->getClasse()->getAnno() == 5 &&
                $insuff_condotta) {
        // ammissione in quinta con una insufficienza in condotta
        $errore[] = $this->trans->trans('exception.voto_condotta_esito', ['sex' => $sesso, 'alunno' => $nome]);
      }
      if (empty($errore) && empty($errore_condotta)) {
        // aggiorna media
        $dati['esito']->setMedia($mediaSomma / $mediaNum);
        $this->em->flush();
      }
    }
    // imposta eventuali messaggi di errore sulla condotta
    foreach ($errore_condotta as $msg=>$v) {
      $this->reqstack->getSession()->getFlashBag()->add('errore', $this->trans->trans($msg));
    }
    // imposta eventuali messaggi di errore
    foreach ($errore as $msg) {
      $this->reqstack->getSession()->getFlashBag()->add('errore', $msg);
    }
    if (empty($errore) && empty($errore_condotta)) {
      // aggiorna stato
      $scrutinio->setStato('6');
      $this->em->flush();
      // log
      $this->dblogger->logAzione('SCRUTINIO', 'Cambio stato', array(
        'Scrutinio' => $scrutinio->getId(),
        'Classe' => $classe->getId(),
        'Periodo' => 'F',
        'Stato iniziale' => '5',
        'Stato finale' => '6',
        ));
      // ok
      return true;
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
    // aggiorna stato
    $scrutinio->setStato('4');
    $this->em->flush();
    // log
    $this->dblogger->logAzione('SCRUTINIO', 'Cambio stato', array(
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
    // cancella medie
    $this->em->getConnection()
      ->prepare("UPDATE gs_esito SET media=NULL,credito=NULL,credito_precedente=NULL WHERE scrutinio_id=:scrutinio")
      ->execute(['scrutinio' => $scrutinio->getId()]);
    if ($classe->getAnno() == 2) {
      // cancella conferma certificazioni
      $esiti = $this->em->getRepository('App\Entity\Esito')->findByScrutinio($scrutinio);
      foreach ($esiti as $e) {
        $datiEsito = $e->getDati();
        if (isset($datiEsito['certificazione']) && $datiEsito['certificazione']) {
          $datiEsito['certificazione'] = false;
          $e->setDati($datiEsito);
        }
      }
      $this->em->flush();
    }
    // aggiorna stato
    $scrutinio->setStato('5');
    $this->em->flush();
    // log
    $this->dblogger->logAzione('SCRUTINIO', 'Cambio stato', array(
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
    $dati['alunni'] = [];
    $conversione = [
      21 => 26,
      22 => 28,
      23 => 29,
      24 => 30,
      25 => 31,
      26 => 33,
      27 => 34,
      28 => 35,
      29 => 36,
      30 => 38,
      31 => 39,
      32 => 40,
      33 => 41,
      34 => 43,
      35 => 44,
      36 => 45,
      37 => 46,
      38 => 48,
      39 => 49,
      40 => 50];
    // periodo dello scrutinio
    $dati['periodo'] = $periodo;
    // legge dati scrutinio
    $scrutinio = $this->em->getRepository('App\Entity\Scrutinio')->findOneBy(['periodo' => $periodo, 'classe' => $classe]);
    $dati['scrutinio'] = $scrutinio->getDati();
    // legge alunni
    $lista = $this->alunniInScrutinio($classe, $periodo);
    $alunni = $this->em->getRepository('App\Entity\Alunno')->createQueryBuilder('a')
      ->select('a.id,a.nome,a.cognome,a.dataNascita,a.religione,a.bes,a.note,a.credito3,a.credito4,e.id AS esito')
      ->join('App\Entity\Esito', 'e', 'WITH', 'a.id=e.alunno')
      ->join('e.scrutinio', 's')
      ->where('a.id in (:lista) AND e.esito=:ammesso AND s.classe=:classe AND s.periodo=:periodo')
      ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
      ->setParameters(['lista' => $lista, 'ammesso' => 'A', 'classe' => $classe, 'periodo' => $periodo])
      ->getQuery()
      ->getArrayResult();
    foreach ($alunni as $alu) {
      $dati['alunni'][$alu['id']] = $alu;
      if ($periodo == 'X') {
        // lettura dati religione e credito
        $dati['alunni'][$alu['id']]['credito3'] = $dati['scrutinio']['credito3'][$alu['id']];
        $dati['alunni'][$alu['id']]['credito4'] = null;
        $dati['alunni'][$alu['id']]['religione'] = $dati['scrutinio']['religione'][$alu['id']];
        $dati['alunni'][$alu['id']]['bes'] = $dati['scrutinio']['bes'][$alu['id']];
      }
      // legge esito
      $dati['esiti'][$alu['id']] = $this->em->getRepository('App\Entity\Esito')->find($alu['esito']);
      // crediti precedenti
      $dati['esiti'][$alu['id']]->setCreditoPrecedente($classe->getAnno() == 3 ? 0 :
        ($classe->getAnno() == 4 ? $dati['alunni'][$alu['id']]['credito3'] : $alu['credito3'] + $alu['credito4']));
      // conversione crediti quinta
      if ($classe->getAnno() == 5 && $dati['esiti'][$alu['id']]->getCredito() !== null) {
        $totale = $alu['credito3'] + $alu['credito4'] + $dati['esiti'][$alu['id']]->getCredito();
        $datiEsiti = $dati['esiti'][$alu['id']]->getDati();
        $datiEsiti['creditoConvertito'] = $conversione[$totale];
        $dati['esiti'][$alu['id']]->setDati($datiEsiti);
      }
      // memorizza su db
      $this->em->flush();
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
    $dati['alunni'] = [];
    // periodo dello scrutinio
    $dati['periodo'] = $periodo;
    // legge alunni
    $lista = $this->alunniInScrutinio($classe, $periodo);
    $alunni = $this->em->getRepository('App\Entity\Alunno')->createQueryBuilder('a')
      ->select('a.id,a.nome,a.cognome,a.dataNascita,a.religione,a.bes,a.note,e.id AS esito')
      ->join('App\Entity\Esito', 'e', 'WITH', 'a.id=e.alunno')
      ->join('e.scrutinio', 's')
      ->where('a.id in (:lista) AND e.esito=:ammesso AND s.classe=:classe AND s.periodo=:periodo')
      ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
      ->setParameters(['lista' => $lista, 'ammesso' => 'A', 'classe' => $classe, 'periodo' => $periodo])
      ->getQuery()
      ->getArrayResult();
    foreach ($alunni as $alu) {
      $dati['alunni'][$alu['id']] = $alu;
      // legge esito
      $dati['esiti'][$alu['id']] = $this->em->getRepository('App\Entity\Esito')->find($alu['esito']);
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
  public function passaggioStato_F_6_7(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // inizializza
    $errore = array();
    $this->reqstack->getSession()->getFlashBag()->clear();
    // alunni della classe
    $lista_id = $this->alunniInScrutinio($classe, 'F');
    // distingue per classe
    if ($classe->getAnno() == 2) {
      // competenze
      $competenze = $this->em->getRepository('App\Entity\Esito')->createQueryBuilder('e')
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
          $errore[] = $this->trans->trans('exception.no_certificazione_esito', ['sex' => $sesso, 'alunno' => $nome]);
        }
      }
    } elseif ($classe->getAnno() != 1) {
      // crediti
      $crediti = $this->em->getRepository('App\Entity\Esito')->createQueryBuilder('e')
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
        $errore[] = $this->trans->trans('exception.no_credito_esito', ['sex' => $sesso, 'alunno' => $nome]);
      }
    }
    if (empty($errore)) {
      // aggiorna stato
      $scrutinio->setStato('7');
      $this->em->flush();
      // log
      $this->dblogger->logAzione('SCRUTINIO', 'Cambio stato', array(
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
      $this->reqstack->getSession()->getFlashBag()->add('errore', $msg);
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
    $alunni = $this->em->getRepository('App\Entity\Alunno')->createQueryBuilder('a')
      ->select('a.id,a.nome,a.cognome,a.dataNascita,a.sesso,a.religione,a.bes,a.note,e.id AS esito,m.id AS materia_id,m.nomeBreve AS materia')
      ->join('App\Entity\Esito', 'e', 'WITH', 'a.id=e.alunno')
      ->join('e.scrutinio', 's')
      ->join('App\Entity\VotoScrutinio', 'vs', 'WITH', 'vs.scrutinio=s.id AND vs.alunno=a.id')
      ->join('vs.materia', 'm')
      ->where('a.id in (:lista) AND e.esito=:sospeso AND s.classe=:classe AND s.periodo=:periodo AND vs.unico<:suff AND m.tipo IN (:tipo)')
      ->orderBy('a.cognome,a.nome,a.dataNascita,m.ordinamento', 'ASC')
      ->setParameters(['lista' => $lista, 'sospeso' => 'S', 'classe' => $classe, 'periodo' => $periodo, 'suff' => 6, 'tipo' => ['N', 'E']])
      ->getQuery()
      ->getArrayResult();
    foreach ($alunni as $alu) {
      $dati['debiti'][$alu['id']][$alu['materia_id']]  = $alu;
      // legge esito
      $dati['esiti'][$alu['id']] = $this->em->getRepository('App\Entity\Esito')->find($alu['esito']);
    }
    // carenze
    $alunni = $this->em->getRepository('App\Entity\Alunno')->createQueryBuilder('a')
      ->select('a.id,a.nome,a.cognome,a.dataNascita,a.sesso,a.religione,a.bes,a.note,e.id AS esito,m.id AS materia_id,m.nomeBreve AS materia')
      ->join('App\Entity\Esito', 'e', 'WITH', 'a.id=e.alunno')
      ->join('e.scrutinio', 's')
      ->join('App\Entity\VotoScrutinio', 'vs', 'WITH', 'vs.scrutinio=s.id AND vs.alunno=a.id')
      ->join('App\Entity\PropostaVoto', 'pv', 'WITH', 'pv.classe=s.classe AND pv.periodo=s.periodo AND pv.alunno=a.id')
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
        $dati['esiti'][$alu['id']] = $this->em->getRepository('App\Entity\Esito')->find($alu['esito']);
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
  public function passaggioStato_F_7_6(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // legge alunni
    $lista = $this->alunniInScrutinio($classe, 'F');
    // legge esiti
    $esiti = $this->em->getRepository('App\Entity\Esito')->createQueryBuilder('e')
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
    $scrutinio->setStato('6');
    $this->em->flush();
    // log
    $this->dblogger->logAzione('SCRUTINIO', 'Cambio stato', array(
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
    $dati['debiti'] = $this->em->getRepository('App\Entity\VotoScrutinio')->createQueryBuilder('vs')
      ->join('vs.scrutinio', 's')
      ->join('vs.materia', 'm')
      ->join('App\Entity\Esito', 'e', 'WITH', 'e.alunno=vs.alunno AND e.scrutinio=s.id')
      ->where('vs.alunno=:alunno AND vs.unico<:suff AND s.classe=:classe AND s.periodo=:periodo AND m.tipo IN (:tipo) AND e.esito=:sospeso')
      ->orderBy('m.ordinamento', 'ASC')
      ->setParameters(['alunno' => $alunno, 'suff' => 6, 'classe' => $alunno->getClasse(), 'periodo' => $periodo,
        'tipo' => ['N', 'E'], 'sospeso' => 'S'])
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
    $dati['carenze'] = $this->em->getRepository('App\Entity\VotoScrutinio')->createQueryBuilder('vs')
      ->join('vs.scrutinio', 's')
      ->join('vs.materia', 'm')
      ->join('App\Entity\Esito', 'e', 'WITH', 'e.alunno=vs.alunno AND e.scrutinio=s.id')
      ->join('App\Entity\PropostaVoto', 'pv', 'WITH', 'pv.alunno=vs.alunno AND pv.classe=s.classe AND pv.periodo=s.periodo')
      ->where('vs.alunno=:alunno AND s.classe=:classe AND s.periodo=:periodo AND m.tipo=:tipo AND e.esito IN (:esiti) AND vs.materia=pv.materia AND pv.unico<:suff AND vs.unico>=:suff')
      ->orderBy('m.ordinamento', 'ASC')
      ->setParameters(['alunno' => $alunno, 'classe' => $alunno->getClasse(), 'periodo' => $periodo,
        'tipo' => 'N', 'esiti' => ['A','S'], 'suff' => 6])
      ->getQuery()
      ->getResult();
    // aggiunge proposte
    foreach ($dati['carenze'] as $voto) {
      $proposta = $this->em->getRepository('App\Entity\PropostaVoto')->createQueryBuilder('pv')
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
  public function passaggioStato_F_7_8(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // inizializza
    $errore = array();
    $this->reqstack->getSession()->getFlashBag()->clear();
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
            $errore[] = $this->trans->trans('exception.no_comunicazione_debiti', ['sex' => $sesso, 'alunno' => $nome]);
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
            $errore[] = $this->trans->trans('exception.no_comunicazione_carenze', ['sex' => $sesso, 'alunno' => $nome]);
            break;
          }
        }
      }
    }
    if (empty($errore)) {
      // aggiorna stato
      $scrutinio->setStato('8');
      $this->em->flush();
      // log
      $this->dblogger->logAzione('SCRUTINIO', 'Cambio stato', array(
        'Scrutinio' => $scrutinio->getId(),
        'Classe' => $classe->getId(),
        'Periodo' => 'F',
        'Stato iniziale' => '7',
        'Stato finale' => '8',
        ));
      // ok
      return true;
    }
    // imposta messaggi di errore
    foreach ($errore as $msg) {
      $this->reqstack->getSession()->getFlashBag()->add('errore', $msg);
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
  public function passaggioStato_F_8_7(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // legge definizione scrutinio e verbale
    $def = $this->em->getRepository('App\Entity\DefinizioneScrutinio')->findOneByPeriodo('F');
    $scrutinio_dati = $scrutinio->getDati();
    foreach ($def->getStruttura() as $step=>$args) {
      if ($args[0] == 'Argomento') {
        // resetta validazione
        $scrutinio_dati['verbale'][$step]['validato'] = false;
      }
    }
    // memorizza dati scrutinio
    $scrutinio->setDati($scrutinio_dati);
    // aggiorna stato
    $scrutinio->setStato('7');
    $this->em->flush();
    // log
    $this->dblogger->logAzione('SCRUTINIO', 'Cambio stato', array(
      'Scrutinio' => $scrutinio->getId(),
      'Classe' => $classe->getId(),
      'Periodo' => 'F',
      'Stato iniziale' => '8',
      'Stato finale' => '7',
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
  public function passaggioStato_F_8_C(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // inizializza messaggi di errore
    $this->reqstack->getSession()->getFlashBag()->clear();
    // legge dati form
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // controlli
      if (!$form->get('fine')->getData()) {
        // ora non presente
        $this->reqstack->getSession()->getFlashBag()->add('errore', $this->trans->trans('exception.scrutinio_fine'));
      }
      if ($form->get('in_presenza')->getData() === null) {
        // svolgimento non presente
        $this->reqstack->getSession()->getFlashBag()->add('errore', $this->trans->trans('exception.scrutinio_svolgimento'));
      }
      // controlla validazione argomenti
      $def = $this->em->getRepository('App\Entity\DefinizioneScrutinio')->findOneByPeriodo('F');
      foreach ($scrutinio->getDati()['verbale'] as $step=>$args) {
        // solo elementi da validare
        if (isset($args['validato']) && !$args['validato']) {
          // errore di validazione
          $this->reqstack->getSession()->getFlashBag()->add('errore', $this->trans->trans('exception.verbale_argomento_mancante',
            ['sezione' => $def->getStruttura()[$step][2]['sezione']]));
        }
      }
      // se niente errori cambia stato
      if (!$this->reqstack->getSession()->getFlashBag()->has('errore')) {
        // imposta dati
        $scrutinio->setFine($form->get('fine')->getData());
        $datiScrutinio = $scrutinio->getDati();
        $datiScrutinio['in_presenza'] = $form->get('in_presenza')->getData();
        $scrutinio->setDati($datiScrutinio);
        // aggiorna stato
        $scrutinio->setStato('C');
        $this->em->flush();
        // log
        $this->dblogger->logAzione('SCRUTINIO', 'Cambio stato', array(
          'Scrutinio' => $scrutinio->getId(),
          'Classe' => $classe->getId(),
          'Periodo' => 'F',
          'Stato iniziale' => '8',
          'Stato finale' => 'C',
          ));
        // ok
        return true;
      }
    } else {
      // imposta messaggi per eventuali altri errori del form
      foreach ($form->getErrors() as $error) {
        $this->reqstack->getSession()->getFlashBag()->add('errore', $error->getMessage());
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
  public function passaggioStato_F_C_8(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // controlla se è possibile riapertura
    if (!($docente instanceOf Staff) || $scrutinio->getSincronizzazione()) {
      // errore
      return false;
    }
    // inizializza messaggi di errore
    $this->reqstack->getSession()->getFlashBag()->clear();
    // rinomina documenti di classe
    $fs = new Filesystem();
    $finder = new Finder();
    $percorso = $this->root.'/'.$this->directory['F'].'/'.$classe->getAnno().$classe->getSezione();
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
    $scrutinio->setStato('8');
    $this->em->flush();
    // log
    $this->dblogger->logAzione('SCRUTINIO', 'Cambio stato', array(
      'Scrutinio' => $scrutinio->getId(),
      'Classe' => $classe->getId(),
      'Periodo' => 'F',
      'Stato iniziale' => 'C',
      'Stato finale' => '8',
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
    // periodo dello scrutinio
    $dati['periodo'] = $periodo;
    if ($periodo == 'X') {
      // scrutinio rinviato prec A.S.
      $scrutinio = $this->em->getRepository('App\Entity\Scrutinio')->findOneBy(['classe' => $classe,
        'periodo' => $periodo]);
      if (!$scrutinio) {
        // nessun dato presente
        return $dati;
      }
      // legge alunni (da dati in scrutinio)
      $rinviati = $this->em->getRepository('App\Entity\Alunno')->createQueryBuilder('a')
        ->select('a.id,a.nome,a.cognome,a.dataNascita')
        ->where('a.id IN (:lista)')
        ->setParameters(['lista' => $scrutinio->getDato('alunni')])
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
        ->getQuery()
        ->getArrayResult();
      foreach ($rinviati as $alu) {
        $dati['alunni'][$alu['id']] = $alu;
        $dati['alunni'][$alu['id']]['religione'] = $scrutinio->getDato('religione')[$alu['id']];
        $dati['alunni'][$alu['id']]['bes'] = $scrutinio->getDato('bes') ?
          $scrutinio->getDato('bes')[$alu['id']] : 'N';
      }
      // legge materie (da dati in scrutinio)
      $materie = $this->em->getRepository('App\Entity\Materia')->createQueryBuilder('m')
        ->select('m.id,m.nome,m.nomeBreve,m.tipo,m.media')
        ->where('m.id IN (:lista) AND m.tipo!=:sostegno')
        ->orderBy('m.ordinamento', 'ASC')
        ->setParameters(['lista' => $scrutinio->getDato('materie'), 'sostegno' => 'S'])
        ->getQuery()
        ->getArrayResult();
      foreach ($materie as $mat) {
        $dati['materie'][$mat['id']] = $mat;
      }
      // legge voti (da dati in scrutinio)
      foreach ($scrutinio->getDato('voti') as $alu=>$voti) {
        foreach ($voti as $mat=>$voto) {
          $dati['voti'][$alu][$mat]['unico'] = $voto['unico'];
          $dati['voti'][$alu][$mat]['assenze'] = $voto['assenze'];
          $dati['voti'][$alu][$mat]['recupero'] = 'C';
          $dati['voti'][$alu][$mat]['debito'] = 'debito';
          $dati['voti'][$alu][$mat]['dati'] = [];
        }
      }
    } else {
      // scrutinio sospeso o rinviato
      $lista = $this->alunniInScrutinio($classe, 'F');
      // considera solo alunni sospesi o con scrutinio rinviato
      $params = ['lista' => $lista, 'sospeso' => 'S', 'classe' => $classe, 'periodo' => 'F'];
      if ($periodo != 'G') {
        // alunni con scrutinio rinviato
        $params['periodo'] = 'G';
        $params['sospeso'] = 'X';
      }
      $sospesi = $this->em->getRepository('App\Entity\Esito')->createQueryBuilder('e')
        ->select('a.id,a.nome,a.cognome,a.dataNascita,a.religione,a.bes,a.note')
        ->join('e.scrutinio', 's')
        ->join('e.alunno', 'a')
        ->where('e.alunno in (:lista) AND e.esito=:sospeso AND s.classe=:classe AND s.periodo=:periodo')
        ->setParameters($params)
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
        ->getQuery()
        ->getArrayResult();
      foreach ($sospesi as $alu) {
        $dati['alunni'][$alu['id']] = $alu;
      }
      // legge materie
      $materie = $this->em->getRepository('App\Entity\Materia')->createQueryBuilder('m')
        ->select('DISTINCT m.id,m.nome,m.nomeBreve,m.tipo')
        ->join('App\Entity\Cattedra', 'c', 'WITH', 'c.materia=m.id')
        ->where('c.classe=:classe AND c.attiva=:attiva AND c.tipo=:tipo AND m.tipo!=:sostegno')
        ->orderBy('m.ordinamento', 'ASC')
        ->setParameters(['classe' => $classe, 'attiva' => 1, 'tipo' => 'N', 'sostegno' => 'S'])
        ->getQuery()
        ->getArrayResult();
      foreach ($materie as $mat) {
        $dati['materie'][$mat['id']] = $mat;
      }
      $condotta = $this->em->getRepository('App\Entity\Materia')->findOneByTipo('C');
      $dati['materie'][$condotta->getId()] = array(
        'id' => $condotta->getId(),
        'nome' => $condotta->getNome(),
        'nomeBreve' => $condotta->getNomeBreve(),
        'tipo' => $condotta->getTipo());
      // legge i voti dello scrutinio finale
      $voti = $this->em->getRepository('App\Entity\VotoScrutinio')->createQueryBuilder('vs')
        ->join('vs.scrutinio', 's')
        ->where('s.classe=:classe AND s.periodo=:periodo AND vs.alunno IN (:sospesi) AND vs.unico IS NOT NULL')
        ->setParameters(['classe' => $classe, 'periodo' => 'F', 'sospesi' => array_keys($dati['alunni'])])
        ->getQuery()
        ->getResult();
      foreach ($voti as $v) {
        // inserisce voti
        $dati['voti'][$v->getAlunno()->getId()][$v->getMateria()->getId()] = array(
          'id' => $v->getId(),
          'unico' => $v->getUnico(),
          'recupero' => $v->getRecupero(),
          'debito' => $v->getDebito(),
          'assenze' => $v->getAssenze(),
          'dati' => $v->getDati());
      }
    }
    // legge lista valutazioni
    $valutazioni['R'] = unserialize($this->em->getRepository('App\Entity\Configurazione')->getParametro('voti_finali_R'));
    $valutazioni['E'] = unserialize($this->em->getRepository('App\Entity\Configurazione')->getParametro('voti_finali_E'));
    $valutazioni['C'] = unserialize($this->em->getRepository('App\Entity\Configurazione')->getParametro('voti_finali_C'));
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
    $listaValori = explode(',', $valutazioni['C']['valori']);
    $listaVoti = explode(',', $valutazioni['C']['votiAbbr']);
    foreach ($listaValori as $key=>$val) {
      $valutazioni['C']['lista'][$val] = trim($listaVoti[$key], '"');
    }
    $listaValori = explode(',', $valutazioni['N']['valori']);
    $listaVoti = explode(',', $valutazioni['N']['votiAbbr']);
    foreach ($listaValori as $key=>$val) {
      $valutazioni['N']['lista'][$val] = trim($listaVoti[$key], '"');
    }
    $dati['valutazioni'] = $valutazioni;
    // imposta blocco
    $defScrutinio = $this->em->getRepository('App\Entity\DefinizioneScrutinio')->findOneByPeriodo($periodo);
    $oggi = new \DateTime();
    $dati['blocco'] = ($oggi < $defScrutinio->getData());
    // restituisce dati
    return $dati;
  }

  /**
   * Esegue il passaggio di stato N->1 per lo scrutinio del periodo G
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_G_N_1(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    $this->reqstack->getSession()->getFlashBag()->clear();
    // legge dati
    $dati = $this->riepilogoSospesi($docente, $classe, $scrutinio->getPeriodo());
    // alunni con voto in scrutinio
    $alunni_esistenti = $this->em->getRepository('App\Entity\VotoScrutinio')->alunni($scrutinio);
    // inserimento voti
    foreach ($dati['alunni'] as $alunno=>$alu) {
      $alunno_obj = $this->em->getRepository('App\Entity\Alunno')->find($alunno);
      foreach ($dati['materie'] as $materia=>$mat) {
        $materia_obj = $this->em->getRepository('App\Entity\Materia')->find($materia);
        // esclude alunni NA per religione
        if ($mat['tipo'] != 'R' || in_array($alu['religione'], ['S', 'A'])) {
          // inserisce voti e assenze
          if (array_key_exists($alunno, $alunni_esistenti) && in_array($materia, $alunni_esistenti[$alunno])) {
            // aggiorna dati esistenti
            $this->em->getRepository('App\Entity\VotoScrutinio')->createQueryBuilder('vs')
              ->update()
              ->set('vs.modificato', ':modificato')
              ->where('vs.scrutinio=:scrutinio AND vs.alunno=:alunno AND vs.materia=:materia')
              ->setParameters(['scrutinio' => $scrutinio, 'alunno' => $alunno, 'materia' => $materia,
                'modificato' => new \DateTime()])
              ->getQuery()
              ->getResult();
          } else {
            // inserisce nuovi dati
            $this->em->getConnection()
              ->prepare('INSERT INTO gs_voto_scrutinio '.
                '(scrutinio_id, alunno_id, materia_id, creato, modificato, unico, debito, recupero, assenze, dati) '.
                'VALUES (:scrutinio,:alunno,:materia,NOW(),NOW(),:unico,:debito,:recupero,:assenze,:dati)')
              ->execute(['scrutinio' => $scrutinio->getId(), 'alunno' => $alunno, 'materia' => $materia,
                'unico' => $dati['voti'][$alunno][$materia]['unico'],
                'debito' => $dati['voti'][$alunno][$materia]['unico'] < 6 ? $dati['voti'][$alunno][$materia]['debito'] : null,
                'recupero' => $dati['voti'][$alunno][$materia]['unico'] < 6 ? $dati['voti'][$alunno][$materia]['recupero'] : null,
                'assenze' => $dati['voti'][$alunno][$materia]['assenze'],
                'dati' => serialize($dati['voti'][$alunno][$materia]['dati'])]);
          }
        }
      }
    }
    $this->em->flush();
    // legge dati da scrutinio finale
    if ($scrutinio->getPeriodo() == 'X') {
      // scrutinio rimandata nell'A.S. precedente
      $scrutinabili = $scrutinio->getDato('scrutinabili');
    } else {
      // scrutinio sospeso o rimandato in stesso A.S.
      $scrutinio_F = $this->em->getRepository('App\Entity\Scrutinio')->findOneBy(['classe' => $classe, 'periodo' => 'F']);
      $scrutinabili = $scrutinio_F->getDato('scrutinabili');
    }
    // memorizza dati alunni
    $dati_scrutinio = $scrutinio->getDati();
    $dati_scrutinio['sospesi'] = array_keys($dati['alunni']);
    $dati_scrutinio['scrutinabili'] = $scrutinabili;
    $dati_scrutinio['valutazioni'] = $dati['valutazioni'];
    $scrutinio->setDati($dati_scrutinio);
    // aggiorna stato
    $scrutinio->setStato('1');
    $this->em->flush();
    // log
    $this->dblogger->logAzione('SCRUTINIO', 'Cambio stato', array(
      'Scrutinio' => $scrutinio->getId(),
      'Classe' => $classe->getId(),
      'Periodo' => $scrutinio->getPeriodo(),
      'Stato iniziale' => 'N',
      'Stato finale' => '1',
      ));
    // ok
    return true;
  }

  /**
   * Esegue il passaggio di stato 1->N per lo scrutinio del periodo G
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_G_1_N(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // controlla se docente fa parte di staff
    if (!($docente instanceOf Staff)) {
      // errore
      return false;
    }
    // inizializza messaggi di errore
    $this->reqstack->getSession()->getFlashBag()->clear();
    // aggiorna stato
    $scrutinio->setStato('N');
    $this->em->flush();
    // log
    $this->dblogger->logAzione('SCRUTINIO', 'Cambio stato', array(
      'Scrutinio' => $scrutinio->getId(),
      'Classe' => $classe->getId(),
      'Periodo' => $scrutinio->getPeriodo(),
      'Stato iniziale' => '1',
      'Stato finale' => 'N',
      ));
    // ok
    return true;
  }

  /**
   * Esegue il passaggio di stato 1->2 per lo scrutinio del periodo G
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_G_1_2(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // inizializza messaggi di errore
    $this->reqstack->getSession()->getFlashBag()->clear();
    // legge dati form
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // controlli
      if (!$form->get('data')->getData()) {
        // data non presente
        $this->reqstack->getSession()->getFlashBag()->add('errore', $this->trans->trans('exception.scrutinio_data'));
      }
      if (!$form->get('inizio')->getData()) {
        // ora non presente
        $this->reqstack->getSession()->getFlashBag()->add('errore', $this->trans->trans('exception.scrutinio_inizio'));
      }
      if ($form->get('presiede_ds')->getData() === null) {
        // presidente ds non presente
        $this->reqstack->getSession()->getFlashBag()->add('errore', $this->trans->trans('exception.scrutinio_presidente'));
      }
      if ($form->get('presiede_ds')->getData() === false && !$form->get('presiede_docente')->getData()) {
        // presidente docente non presente
        $this->reqstack->getSession()->getFlashBag()->add('errore', $this->trans->trans('exception.scrutinio_presidente'));
      }
      if (!$form->get('segretario')->getData()) {
        // segretario non presente
        $this->reqstack->getSession()->getFlashBag()->add('errore', $this->trans->trans('exception.scrutinio_segretario'));
      }
      // controlli sui presenti
      $errore_presenza = false;
      foreach ($form->get('lista')->getData() as $doc=>$val) {
        if (!$val || (!$val->getPresenza() && (!$val->getSostituto() || !$val->getSurrogaProtocollo() ||
            !$val->getSurrogaData()))) {
          $errore_presenza = true;
        }
      }
      if ($errore_presenza) {
        // docente non presente
        $this->reqstack->getSession()->getFlashBag()->add('errore', $this->trans->trans('exception.scrutinio_presenza'));
      }
      // se niente errori cambia stato
      if (!$this->reqstack->getSession()->getFlashBag()->has('errore')) {
        // dati docenti
        $dati_docenti = array();
        if ($scrutinio->getPeriodo() == 'X') {
          // scrutinio rinviata da prec. A.S. (legge dati da scrutinio)
          $dati_docenti = $scrutinio->getDato('docenti');
        } else {
          // altri periodi
          $docenti = $this->em->getRepository('App\Entity\Cattedra')->docentiScrutinio($classe);
          // memorizza dati docenti e materie
          foreach ($docenti as $doc) {
            $dati_docenti[$doc['id']][$doc['materia_id']] = $doc['tipo'];
          }
        }
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
        $valori['docenti'] = $dati_docenti;
        $scrutinio->setDati($valori);
        // aggiorna stato
        $scrutinio->setStato('2');
        $this->em->flush();
        // log
        $this->dblogger->logAzione('SCRUTINIO', 'Cambio stato', array(
          'Scrutinio' => $scrutinio->getId(),
          'Classe' => $classe->getId(),
          'Periodo' => $scrutinio->getPeriodo(),
          'Stato iniziale' => '1',
          'Stato finale' => '2',
          ));
        // ok
        return true;
      }
    } else {
      // imposta messaggi per eventuali altri errori del form
      foreach ($form->getErrors() as $error) {
        $this->reqstack->getSession()->getFlashBag()->add('errore', $error->getMessage());
      }
    }
    // errore
    return false;
  }

  /**
   * Esegue il passaggio di stato 2->1 per lo scrutinio del periodo G
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_G_2_1(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // inizializza messaggi di errore
    $this->reqstack->getSession()->getFlashBag()->clear();
    // aggiorna stato
    $scrutinio->setStato('1');
    $this->em->flush();
    // log
    $this->dblogger->logAzione('SCRUTINIO', 'Cambio stato', array(
      'Scrutinio' => $scrutinio->getId(),
      'Classe' => $classe->getId(),
      'Periodo' => $scrutinio->getPeriodo(),
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
    $scrutinio = $this->em->getRepository('App\Entity\Scrutinio')->createQueryBuilder('s')
      ->where('s.classe=:classe AND s.periodo=:periodo')
      ->setParameters(['classe' => $alunno->getClasse(), 'periodo' => $periodo])
      ->setMaxResults(1)
      ->getQuery()
      ->getOneOrNullResult();
    $dati['scrutinio'] = $scrutinio;
    // legge materie
    $materie = $this->em->getRepository('App\Entity\Materia')->createQueryBuilder('m')
      ->select('DISTINCT m.id,m.nome,m.nomeBreve,m.tipo')
      ->join('App\Entity\Cattedra', 'c', 'WITH', 'c.materia=m.id')
      ->where('c.classe=:classe AND c.attiva=:attiva AND c.tipo=:tipo AND m.tipo!=:sostegno')
      ->orderBy('m.ordinamento', 'ASC')
      ->setParameters(['classe' => $alunno->getClasse(), 'attiva' => 1, 'tipo' => 'N', 'sostegno' => 'S'])
      ->getQuery()
      ->getArrayResult();
    foreach ($materie as $mat) {
      $dati['materie'][$mat['id']] = $mat;
    }
    $condotta = $this->em->getRepository('App\Entity\Materia')->findOneByTipo('C');
    $dati['materie'][$condotta->getId()] = array(
      'id' => $condotta->getId(),
      'nome' => $condotta->getNome(),
      'nomeBreve' => $condotta->getNomeBreve(),
      'tipo' => $condotta->getTipo());
    // legge solo i voti con debito
    $voti = $this->em->getRepository('App\Entity\VotoScrutinio')->createQueryBuilder('vs')
      ->join('vs.materia', 'm')
      ->join('App\Entity\Scrutinio', 's', 'WITH', 's.classe=:classe AND s.periodo=:periodo')
      ->join('App\Entity\VotoScrutinio', 'vsf', 'WITH', 'vsf.scrutinio=s.id AND vsf.materia=m.id AND vsf.alunno=:alunno')
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
    $esito = $this->em->getRepository('App\Entity\Esito')->findOneBy(['scrutinio' => $scrutinio, 'alunno' => $alunno]);
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
   * Restituisce la lista dei voti dello scrutinio rinviato per l'alunno indicato
   *
   * @param Docente $docente Docente che esegue la lettura
   * @param Alunno $alunno Alunno di cui restituire i voti
   * @param string $periodo Periodo relativo allo scrutinio
   * @param boolean $tutti Se vero riporta tutti i voti, non solo quelli del debito
   *
   * @return array Dati formattati come un array associativo
   */
  public function elencoVotiAlunnoRinviato(Docente $docente, Alunno $alunno, Classe $classe, $periodo,
                                           $tutti=false) {
    $dati = array();
    $dati['voti'] = array();
    // alunni della classe
    $lista_id = $this->alunniInScrutinio($classe, $periodo);
    if (!in_array($alunno->getId(), $lista_id)) {
      // errore: alunno non previsto
      return null;
    }
    // legge scrutinio
    $scrutinio = $this->em->getRepository('App\Entity\Scrutinio')->createQueryBuilder('s')
      ->where('s.classe=:classe AND s.periodo=:periodo')
      ->setParameters(['classe' => $classe, 'periodo' => $periodo])
      ->setMaxResults(1)
      ->getQuery()
      ->getOneOrNullResult();
    $dati['scrutinio'] = $scrutinio;
    // legge materie
    $materie = $this->em->getRepository('App\Entity\Materia')->createQueryBuilder('m')
      ->select('m.id,m.nome,m.nomeBreve,m.tipo,m.media')
      ->where('m.id IN (:lista) AND m.tipo!=:sostegno')
      ->orderBy('m.ordinamento', 'ASC')
      ->setParameters(['lista' => $scrutinio->getDato('materie'), 'sostegno' => 'S'])
      ->getQuery()
      ->getArrayResult();
    foreach ($materie as $mat) {
      $dati['materie'][$mat['id']] = $mat;
    }
    // legge solo i voti con debito
    $voti = $this->em->getRepository('App\Entity\VotoScrutinio')->createQueryBuilder('vs')
      ->join('vs.materia', 'm')
      ->where('vs.scrutinio=:scrutinio AND vs.alunno=:alunno'.
        ($tutti ? '' : ' AND vs.debito IS NOT NULL'))
      ->orderBy('m.ordinamento', 'ASC')
      ->setParameters(['scrutinio' => $scrutinio, 'alunno' => $alunno])
      ->getQuery()
      ->getResult();
    foreach ($voti as $v) {
      // inserisce voti
      $dati['voti'][$v->getMateria()->getId()] = $v;
    }
    // legge esito
    $esito = $this->em->getRepository('App\Entity\Esito')->findOneBy(['scrutinio' => $scrutinio, 'alunno' => $alunno]);
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
   * Esegue il passaggio di stato 2->3 per lo scrutinio del periodo G
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_G_2_3(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // inizializza
    $this->reqstack->getSession()->getFlashBag()->clear();
    $errore = array();
    $valutazioni = $scrutinio->getDato('valutazioni');
    // scrtinio finale
    $scrutinioFinale = $this->em->getRepository('App\Entity\Scrutinio')->findOneBy(['periodo' => 'F',
      'classe' => $classe, 'stato' => 'C']);
    // alunni della classe
    $lista_id = $this->alunniInScrutinio($classe, $scrutinio->getPeriodo());
    foreach ($lista_id as $id) {
      // recupera alunno
      $alunno = $this->em->getRepository('App\Entity\Alunno')->find($id);
      $sesso = ($alunno->getSesso() == 'M' ? 'o' : 'a');
      $nome = $alunno->getCognome().' '.$alunno->getNome();
      // elenco voti dell'alunno
      $dati = $this->elencoVotiAlunno($docente, $alunno, $scrutinio->getPeriodo());
      $mediaSomma = 0;
      $mediaNum = 0;
      // controlla errori
      $no_voto = 0;
      $insuff_cont = 0;
      $insuff_religione = false;
      $insuff_condotta = false;
      foreach ($dati['voti'] as $key=>$voto) {
        // controllo voto
        if ($voto->getUnico() === null || $voto->getUnico() < $valutazioni[$voto->getMateria()->getTipo()]['min'] ||
            $voto->getUnico() > $valutazioni[$voto->getMateria()->getTipo()]['max']) {
          // voto non ammesso o non presente
          $no_voto++;
        } elseif ($voto->getMateria()->getTipo() == 'R' && $voto->getUnico() < $valutazioni['R']['suff']) {
          // voto religione insufficiente
          $insuff_religione = true;
          $insuff_cont++;
        } elseif ($voto->getMateria()->getTipo() == 'C' && $voto->getUnico() < $valutazioni['C']['suff']) {
          // voto condotta insufficiente
          $insuff_condotta = true;
          $insuff_cont++;
        } elseif ($voto->getUnico() < $valutazioni[$voto->getMateria()->getTipo()]['suff']) {
          // voto insufficiente
          $insuff_cont++;
        }
        // legge voto dello scrutinio finale (escluso scrutinio rimandato da prec. A.S.)
        $votoFinale = $this->em->getRepository('App\Entity\VotoScrutinio')->findOneBy([
          'scrutinio' => $scrutinioFinale, 'alunno' => $id, 'materia' => $voto->getMateria()]);
        if ($votoFinale && $voto->getUnico() < $votoFinale->getUnico()) {
          // voto inferiore a quello assegnato nello scrutinio finale
          $errore[] = $this->trans->trans('exception.voto_sospeso_inferiore_a_finale', ['sex' => $sesso,
            'alunno' => $nome, 'materia' => $voto->getMateria()->getNomeBreve()]);
        }
        // calcolo media
        if ($voto->getMateria()->getMedia()) {
          // somma voti
          $mediaSomma +=
            ($voto->getUnico() == $valutazioni[$voto->getMateria()->getTipo()]['min']) ? 0 : $voto->getUnico();
          $mediaNum++;
        }
      }
      if ($no_voto > 0) {
        // voti non presenti
        $errore[] = $this->trans->trans('exception.no_voto_esito', ['sex' => $sesso, 'alunno' => $nome]);
      }
      if ($dati['esito']->getEsito() === null) {
        // manca esito
        $errore[] = $this->trans->trans('exception.manca_esito', ['sex' => $sesso, 'alunno' => $nome]);
      }
      if ($dati['esito']->getDati()['unanimita'] === null && $dati['esito']->getEsito() != 'X') {
        // manca delibera
        $errore[] = $this->trans->trans('exception.delibera_esito', ['sex' => $sesso, 'alunno' => $nome]);
      } elseif ($dati['esito']->getDati()['unanimita'] === false && !$dati['esito']->getDati()['contrari'] && $dati['esito']->getEsito() != 'X') {
        // mancano contrari
        $errore[] = $this->trans->trans('exception.contrari_esito', ['sex' => $sesso, 'alunno' => $nome]);
      }
      if ($dati['esito']->getEsito() == 'N' && empty($dati['esito']->getDati()['giudizio'])) {
        // manca giudizio
        $errore[] = $this->trans->trans('exception.giudizio_esito', ['sex' => $sesso, 'alunno' => $nome]);
      }
      if ($dati['esito']->getEsito() == 'X' && empty($dati['esito']->getDati()['giudizio'])) {
        // manca giudizio
        $errore[] = $this->trans->trans('exception.motivo_scrutinio_rinviato', ['sex' => $sesso, 'alunno' => $nome]);
      }
      if ($dati['esito']->getEsito() == 'A' && $insuff_cont > 0) {
        // insufficienze con ammissione
        $errore[] = $this->trans->trans('exception.insufficienze_ammissione_esito', ['sex' => $sesso, 'alunno' => $nome]);
      }
      if ($dati['esito']->getEsito() == 'N' && $insuff_cont == 0) {
        // solo sufficienze con non ammissione
        $errore[] = $this->trans->trans('exception.sufficienze_non_ammissione_esito', ['sex' => $sesso, 'alunno' => $nome]);
      }
      if ($dati['esito']->getEsito() != 'N' && $insuff_religione) {
        // insuff. religione incoerente con esito
        $errore[] = $this->trans->trans('exception.voto_religione_esito', ['sex' => $sesso, 'alunno' => $nome]);
      }
      if ($dati['esito']->getEsito() != 'N' && $insuff_condotta) {
        // insuff. condotta incoerente con esito
        $errore[] = $this->trans->trans('exception.voto_condotta_esito', ['sex' => $sesso, 'alunno' => $nome]);
      }
      if (empty($errore)) {
        // aggiorna media
        $dati['esito']->setMedia($mediaSomma / $mediaNum);
      }
    }
    if (empty($errore)) {
      // aggiorna stato
      $scrutinio->setStato('3');
      $this->em->flush();
      // log
      $this->dblogger->logAzione('SCRUTINIO', 'Cambio stato', array(
        'Scrutinio' => $scrutinio->getId(),
        'Classe' => $classe->getId(),
        'Periodo' => $scrutinio->getPeriodo(),
        'Stato iniziale' => '2',
        'Stato finale' => '3',
        ));
      // ok
      return true;
    }
    // imposta messaggi di errore
    foreach ($errore as $msg) {
      $this->reqstack->getSession()->getFlashBag()->add('errore', $msg);
    }
    // errori presenti
    return false;
  }

  /**
   * Esegue il passaggio di stato 3->2 per lo scrutinio del periodo G
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_G_3_2(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // cancella medie
    $this->em->getConnection()
      ->prepare("UPDATE gs_esito SET media=NULL,credito=NULL,credito_precedente=NULL WHERE scrutinio_id=:scrutinio")
      ->execute(['scrutinio' => $scrutinio->getId()]);
    if ($classe->getAnno() == 2) {
      // cancella conferma certificazioni
      $esiti = $this->em->getRepository('App\Entity\Esito')->findByScrutinio($scrutinio);
      foreach ($esiti as $e) {
        $datiEsito = $e->getDati();
        if (isset($datiEsito['certificazione']) && $datiEsito['certificazione']) {
          $datiEsito['certificazione'] = false;
          $e->setDati($datiEsito);
        }
      }
      $this->em->flush();
    }
    // aggiorna stato
    $scrutinio->setStato('2');
    $this->em->flush();
    // log
    $this->dblogger->logAzione('SCRUTINIO', 'Cambio stato', array(
      'Scrutinio' => $scrutinio->getId(),
      'Classe' => $classe->getId(),
      'Periodo' => $scrutinio->getPeriodo(),
      'Stato iniziale' => '3',
      'Stato finale' => '2',
      ));
    // ok
    return true;
  }

  /**
   * Esegue il passaggio di stato 3->4 per lo scrutinio del periodo G
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_G_3_4(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // inizializza
    $errore = array();
    $this->reqstack->getSession()->getFlashBag()->clear();
    // alunni della classe
    $lista_id = $this->alunniInScrutinio($classe, $scrutinio->getPeriodo());
    // distingue per classe
    if ($classe->getAnno() == 2) {
      // competenze
      $competenze = $this->em->getRepository('App\Entity\Esito')->createQueryBuilder('e')
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
          $errore[] = $this->trans->trans('exception.no_certificazione_esito', ['sex' => $sesso, 'alunno' => $nome]);
        }
      }
    } elseif ($classe->getAnno() != 1) {
      // crediti
      $crediti = $this->em->getRepository('App\Entity\Esito')->createQueryBuilder('e')
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
        $errore[] = $this->trans->trans('exception.no_credito_esito', ['sex' => $sesso, 'alunno' => $nome]);
      }
    }
    if (empty($errore)) {
      // aggiorna stato
      $scrutinio->setStato('4');
      $this->em->flush();
      // log
      $this->dblogger->logAzione('SCRUTINIO', 'Cambio stato', array(
        'Scrutinio' => $scrutinio->getId(),
        'Classe' => $classe->getId(),
        'Periodo' => $scrutinio->getPeriodo(),
        'Stato iniziale' => '3',
        'Stato finale' => '4',
        ));
      // ok
      return true;
    }
    // imposta messaggi di errore
    foreach ($errore as $msg) {
      $this->reqstack->getSession()->getFlashBag()->add('errore', $msg);
    }
    // errori presenti
    return false;
  }

  /**
   * Esegue il passaggio di stato 4->3 per lo scrutinio del periodo G
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_G_4_3(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // legge definizione scrutinio e verbale
    $def = $this->em->getRepository('App\Entity\DefinizioneScrutinio')->findOneByPeriodo($scrutinio->getPeriodo());
    $scrutinio_dati = $scrutinio->getDati();
    foreach ($def->getStruttura() as $step=>$args) {
      if ($args[0] == 'Argomento') {
        // resetta validazione
        $scrutinio_dati['verbale'][$step]['validato'] = false;
      }
    }
    // memorizza dati scrutinio
    $scrutinio->setDati($scrutinio_dati);
    // aggiorna stato
    $scrutinio->setStato('3');
    $this->em->flush();
    // log
    $this->dblogger->logAzione('SCRUTINIO', 'Cambio stato', array(
      'Scrutinio' => $scrutinio->getId(),
      'Classe' => $classe->getId(),
      'Periodo' => $scrutinio->getPeriodo(),
      'Stato iniziale' => '4',
      'Stato finale' => '3',
      ));
    // ok
    return true;
  }

  /**
   * Esegue il passaggio di stato 4->C per lo scrutinio del periodo G
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_G_4_C(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // inizializza messaggi di errore
    $this->reqstack->getSession()->getFlashBag()->clear();
    // legge dati form
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // controlli
      if (!$form->get('fine')->getData()) {
        // ora non presente
        $this->reqstack->getSession()->getFlashBag()->add('errore', $this->trans->trans('exception.scrutinio_fine'));
      }
      if ($form->get('in_presenza')->getData() === null) {
        // svolgimento non presente
        $this->reqstack->getSession()->getFlashBag()->add('errore', $this->trans->trans('exception.scrutinio_svolgimento'));
      }
      // controlla validazione argomenti
      $def = $this->em->getRepository('App\Entity\DefinizioneScrutinio')->findOneByPeriodo($scrutinio->getPeriodo());
      foreach ($scrutinio->getDati()['verbale'] as $step=>$args) {
        // solo elementi da validare
        if (isset($args['validato']) && !$args['validato']) {
          // errore di validazione
          $this->reqstack->getSession()->getFlashBag()->add('errore', $this->trans->trans('exception.verbale_argomento_mancante',
            ['sezione' => $def->getStruttura()[$step][2]['sezione']]));
        }
      }
      // se niente errori cambia stato
      if (!$this->reqstack->getSession()->getFlashBag()->has('errore')) {
        // imposta dati
        $scrutinio->setFine($form->get('fine')->getData());
        $datiScrutinio = $scrutinio->getDati();
        $datiScrutinio['in_presenza'] = $form->get('in_presenza')->getData();
        $scrutinio->setDati($datiScrutinio);
        // aggiorna stato
        $scrutinio->setStato('C');
        $this->em->flush();
        // log
        $this->dblogger->logAzione('SCRUTINIO', 'Cambio stato', array(
          'Scrutinio' => $scrutinio->getId(),
          'Classe' => $classe->getId(),
          'Periodo' => $scrutinio->getPeriodo(),
          'Stato iniziale' => '4',
          'Stato finale' => 'C',
          ));
        // ok
        return true;
      }
    } else {
      // imposta messaggi per eventuali altri errori del form
      foreach ($form->getErrors() as $error) {
        $this->reqstack->getSession()->getFlashBag()->add('errore', $error->getMessage());
      }
    }
    // errore
    return false;
  }

  /**
   * Esegue il passaggio di stato C->4 per lo scrutinio del periodo G
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_G_C_4(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // controlla se è possibile riapertura
    if (!($docente instanceOf Staff) || $scrutinio->getSincronizzazione()) {
      // errore
      return false;
    }
    // inizializza messaggi di errore
    $this->reqstack->getSession()->getFlashBag()->clear();
    // rinomina documenti di classe
    $fs = new Filesystem();
    $finder = new Finder();
    $percorso = $this->root.'/'.$this->directory[$scrutinio->getPeriodo()].'/'.$classe->getAnno().$classe->getSezione();
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
    $this->dblogger->logAzione('SCRUTINIO', 'Cambio stato', array(
      'Scrutinio' => $scrutinio->getId(),
      'Classe' => $classe->getId(),
      'Periodo' => $scrutinio->getPeriodo(),
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
    $dati['obbligatorio'] = $args[2]['obbligatorio'];
    $dati['testo'] = isset($scrutinio->getDati()['argomento'][$num_arg]) ?
      $scrutinio->getDati()['argomento'][$num_arg] : (isset($args[2]['default']) ? $args[2]['default'] : '');
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
      ->add('testo', MessageType::class, array('label' => false,
        'data' => $dati['testo'],
        'attr' => ['rows' => 6],
        'trim' => true,
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' => ['class' => 'btn-primary']));
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
    $this->reqstack->getSession()->getFlashBag()->clear();
    // controlla form
    if ($form->isValid()) {
      // controlli
      if ($args[2]['obbligatorio'] && empty($form->get('testo')->getData())) {
        // testo non presente
        $this->reqstack->getSession()->getFlashBag()->add('errore', $this->trans->trans('exception.verbale_argomento_vuoto'));
      }
      // se niente errori modifica dati
      if (!$this->reqstack->getSession()->getFlashBag()->has('errore')) {
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
        $this->dblogger->logAzione('SCRUTINIO', 'Modifica verbale', array(
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
        $this->reqstack->getSession()->getFlashBag()->add('errore', $error->getMessage());
      }
    }
  }

  /**
   * Restituisce la situazione del precedente anno scolastico
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Classe $classe Classe relativa alle proposte di voto
   * @param string $periodo Periodo relativo allo scrutinio
   *
   * @return array Dati formattati come un array associativo
   */
  public function quadroVotiPrecedente(Docente $docente, Classe $classe) {
    $dati = array();
    $dati['alunni'] = array();
    // legge alunni
    $alunni = $this->em->getRepository('App\Entity\Alunno')->createQueryBuilder('a')
      ->select('a.id,a.nome,a.cognome,a.dataNascita,a.religione,a.bes,a.note,se.classe,se.esito,se.media,se.periodo,se.dati')
      ->join('App\Entity\StoricoEsito', 'se', 'WITH', 'se.alunno=a.id')
      ->where('a.classe=:classe')
      ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
      ->setParameters(['classe' => $classe->getId()])
      ->getQuery()
      ->getResult();
    foreach ($alunni as $alu) {
      if ($alu['esito'] == 'R' && !in_array($alu['id'], $alu['dati']['cessata_frequenza'])) {
        // non ammesso per limite assenze
        $alu['esito'] = 'L';
      }
      $dati['alunni'][$alu['id']] = $alu;
    }
    // legge i voti
    $voti = $this->em->getRepository('App\Entity\StoricoVoto')->createQueryBuilder('sv')
      ->select('sv.voto,sv.carenze,sv.dati,a.id AS alunno_id,m.id AS materia_id')
      ->join('sv.materia', 'm')
      ->join('sv.storicoEsito', 'se')
      ->join('se.alunno', 'a')
      ->join('App\Entity\Cattedra', 'c', 'WITH', 'c.materia=sv.materia AND c.attiva=:attiva AND c.docente=:docente AND c.classe=a.classe')
      ->where('a.classe=:classe')
      ->setParameters(['attiva' => 1, 'docente' => $docente->getId(), 'classe' => $classe->getId()])
      ->getQuery()
      ->getResult();
    foreach ($voti as $v) {
      $dati['voti'][$v['alunno_id']][$v['materia_id']] = $v;
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Gestione del verbale dello scrutinio
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Classe $classe Classe relativa allo scrutinio
   * @param string $periodo Periodo relativo allo scrutinio
   *
   * @return array Dati formattati come un array associativo
   */
  public function verbale(Docente $docente, Classe $classe, $periodo) {
    $dati = array();
    // periodo dello scrutinio
    $dati['periodo'] = $periodo;
    // legge scrutinio
    $scrutinio = $this->em->getRepository('App\Entity\Scrutinio')->findOneBY(['periodo' => $periodo, 'classe' => $classe]);
    $dati_scrutinio = $scrutinio->getDati();
    // legge ora fine
    $ora = \DateTime::createFromFormat('H:i', date('H').':'.((intval(date('i')) < 25) ? '00' : '30'));
    $dati['scrutinio']['fine'] = $scrutinio->getFine() ? $scrutinio->getFine() : $ora;
    // legge svolgimento scrutinio
    $dati['scrutinio']['in_presenza'] = isset($dati_scrutinio['in_presenza']) ? $dati_scrutinio['in_presenza'] : null;
    // legge definizione scrutinio e verbale
    $def = $this->em->getRepository('App\Entity\DefinizioneScrutinio')->findOneByPeriodo($periodo);
    $struttura = array();
    foreach ($def->getStruttura() as $step=>$args) {
      if ($args[1]) {
        // solo elementi da validare
        if ($args[0] == 'Argomento') {
          // info argomento
          $struttura[$step]['tipo'] = 'Argomento';
          $num_arg = $args[2]['argomento'];
          $struttura[$step]['validato'] = isset($scrutinio->getDati()['verbale'][$step]['validato']) ?
            $scrutinio->getDati()['verbale'][$step]['validato'] : false;
          $struttura[$step]['sezione'] = $args[2]['sezione'];
          $struttura[$step]['argomento'] = $def->getArgomenti()[$num_arg];
          $struttura[$step]['inizio'] = isset($args[2]['inizio']) ? $args[2]['inizio'] : '';
          $struttura[$step]['fine'] = isset($args[2]['fine']) ? $args[2]['fine'] : '';
          $struttura[$step]['testo'] = isset($scrutinio->getDati()['argomento'][$num_arg]) ?
            $scrutinio->getDati()['argomento'][$num_arg] : (isset($args[2]['default']) ? $args[2]['default'] : '');
        }
      }
    }
    $dati['verbale']['struttura'] = $struttura;
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
  public function verbaleForm(Classe $classe, $periodo, FormBuilder $form, $dati) {
    // crea form
    $form
      ->setAction($this->router->generate('coordinatore_scrutinio',
        ['classe' => $classe->getId(), 'stato' => 'C']))
      ->add('in_presenza', ChoiceType::class, array('label' => false,
        'data' => $dati['scrutinio']['in_presenza'],
        'choices' => ['label.scrutinio_svolto_in_presenza' => true, 'label.scrutinio_svolto_a_distanza' => false],
        'expanded' => true,
        'multiple' => false,
        'label_attr' => ['class' => 'gs-pt-0 gs-mr-5'],
        'required' => true))
      ->add('fine', TimeType::class, array('label' => false,
        'data'=> $dati['scrutinio']['fine'],
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-picker'],
        'required' => true));
    // restituisce form
    return $form;
  }

  /**
   * Esegue il passaggio di stato N->1 per lo scrutinio del periodo S
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_S_N_1(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // controlli sulle proposte
    $this->reqstack->getSession()->getFlashBag()->clear();
    $dati = $this->quadroProposte($docente, $classe, 'S');
    if (isset($dati['errori']) && in_array(1, array_values($dati['errori']))) {
      // mancano valutazioni
      return false;
    }
    $this->reqstack->getSession()->getFlashBag()->clear();
    // alunni con voto  in scrutinio
    $alunni_esistenti = $this->em->getRepository('App\Entity\VotoScrutinio')->alunni($scrutinio);
    // materia ed. civica
    $edcivica = $this->em->getRepository('App\Entity\Materia')->findOneByTipo('E');
    $dati['materie'][$edcivica->getId()] = ['id' => $edcivica->getId(), 'nome' => $edcivica->getNome(),
      'nomeBreve' => $edcivica->getNomeBreve(), 'tipo' => $edcivica->getTipo()];
    // conteggio assenze e inserimento voti
    $dati_delibera = serialize(['motivazione' => null, 'unanimita' => true, 'contrari' => null]);
    foreach ($dati['alunni'] as $alunno=>$alu) {
      foreach ($dati['materie'] as $materia=>$mat) {
        // esclude alunni NA per religione
        if (in_array($mat['tipo'], ['N', 'E']) || in_array($alu['religione'], ['S', 'A'])) {
          // calcola assenze di alunno
          $ore = $this->em->getRepository('App\Entity\AssenzaLezione')->createQueryBuilder('al')
            ->select('SUM(al.ore)')
            ->join('al.lezione', 'l')
            ->leftJoin('App\Entity\CambioClasse', 'cc', 'WITH', 'cc.alunno=al.alunno AND l.data BETWEEN cc.inizio AND cc.fine')
            ->where('al.alunno=:alunno AND l.materia=:materia AND l.data > :inizio AND l.data <= :fine AND (l.classe=:classe OR l.classe=cc.classe)')
            ->setParameters(['alunno' => $alunno, 'materia' => $materia,
              'inizio' => $this->reqstack->getSession()->get('/CONFIG/SCUOLA/periodo1_fine'),
              'fine' => $this->reqstack->getSession()->get('/CONFIG/SCUOLA/periodo2_fine'), 'classe' => $classe->getId()])
            ->getQuery()
            ->getSingleScalarResult();
          $ore = ($ore ? ((int) $ore) : 0);
          // inserisce voti e assenze
          if (array_key_exists($alunno, $alunni_esistenti) && in_array($materia, $alunni_esistenti[$alunno])) {
            // aggiorna dati esistenti
            $this->em->getRepository('App\Entity\VotoScrutinio')->createQueryBuilder('vs')
              ->update()
              ->set('vs.modificato', ':modificato')
              ->set('vs.assenze', ':assenze')
              ->where('vs.scrutinio=:scrutinio AND vs.alunno=:alunno AND vs.materia=:materia')
              ->setParameters(['scrutinio' => $scrutinio, 'alunno' => $alunno, 'materia' => $materia,
                'modificato' => new \DateTime(), 'assenze' => $ore])
              ->getQuery()
              ->getResult();
          } else {
            // inserisce nuovi dati
            if ($mat['tipo'] == 'E') {
              // ed.Civica non ha proposte
              $dati['proposte'][$alunno][$materia]['unico'] = null;
              $dati['proposte'][$alunno][$materia]['debito'] = null;
              $dati['proposte'][$alunno][$materia]['recupero'] = null;
            }
            $this->em->getConnection()
              ->prepare('INSERT INTO gs_voto_scrutinio '.
                '(scrutinio_id, alunno_id, materia_id, creato, modificato, unico, debito, recupero, assenze, dati) '.
                'VALUES (:scrutinio,:alunno,:materia,NOW(),NOW(),:unico,:debito,:recupero,:ore,:dati)')
              ->execute(['scrutinio' => $scrutinio->getId(), 'alunno' => $alunno, 'materia' => $materia,
                'unico' => $dati['proposte'][$alunno][$materia]['unico'],
                'debito' => $dati['proposte'][$alunno][$materia]['debito'],
                'recupero' => $dati['proposte'][$alunno][$materia]['recupero'],
                'ore' => $ore,
                'dati' => $dati_delibera]);
          }
        }
      }
    }
    // memorizza alunni
    $dati_scrutinio = $scrutinio->getDati();
    $dati_scrutinio['alunni'] = array_keys($dati['alunni']);
    $dati_scrutinio['valutazioni'] = $dati['valutazioni'];
    $scrutinio->setDati($dati_scrutinio);
    // aggiorna stato
    $scrutinio->setStato('1');
    $this->em->flush();
    // log
    $this->dblogger->logAzione('SCRUTINIO', 'Cambio stato', array(
      'Scrutinio' => $scrutinio->getId(),
      'Classe' => $classe->getId(),
      'Periodo' => 'S',
      'Stato iniziale' => 'N',
      'Stato finale' => '1',
      ));
    // ok
    return true;
  }

  /**
   * Esegue il passaggio di stato 1->N per lo scrutinio del periodo S
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_S_1_N(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // inizializza messaggi di errore
    $this->reqstack->getSession()->getFlashBag()->clear();
   // aggiorna stato
    $scrutinio->setStato('N');
    $this->em->flush();
    // log
    $this->dblogger->logAzione('SCRUTINIO', 'Cambio stato', array(
      'Scrutinio' => $scrutinio->getId(),
      'Classe' => $classe->getId(),
      'Periodo' => 'S',
      'Stato iniziale' => '1',
      'Stato finale' => 'N',
      ));
    // ok
    return true;
  }

  /**
   * Esegue il passaggio di stato 1->2 per lo scrutinio del periodo S
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_S_1_2(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // inizializza messaggi di errore
    $this->reqstack->getSession()->getFlashBag()->clear();
    // legge dati form
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // controlli
      if (!$form->get('data')->getData()) {
        // data non presente
        $this->reqstack->getSession()->getFlashBag()->add('errore', $this->trans->trans('exception.scrutinio_data'));
      }
      if (!$form->get('inizio')->getData()) {
        // ora non presente
        $this->reqstack->getSession()->getFlashBag()->add('errore', $this->trans->trans('exception.scrutinio_inizio'));
      }
      if ($form->get('presiede_ds')->getData() === null) {
        // presidente ds non presente
        $this->reqstack->getSession()->getFlashBag()->add('errore', $this->trans->trans('exception.scrutinio_presidente'));
      }
      if ($form->get('presiede_ds')->getData() === false && !$form->get('presiede_docente')->getData()) {
        // presidente docente non presente
        $this->reqstack->getSession()->getFlashBag()->add('errore', $this->trans->trans('exception.scrutinio_presidente'));
      }
      if (!$form->get('segretario')->getData()) {
        // segretario non presente
        $this->reqstack->getSession()->getFlashBag()->add('errore', $this->trans->trans('exception.scrutinio_segretario'));
      }
      // controlli sui presenti
      $errore_presenza = false;
      foreach ($form->get('lista')->getData() as $doc=>$val) {
        if (!$val || (!$val->getPresenza() && (!$val->getSostituto() || !$val->getSurrogaProtocollo() ||
            !$val->getSurrogaData()))) {
          $errore_presenza = true;
        }
      }
      if ($errore_presenza) {
        // docente non presente
        $this->reqstack->getSession()->getFlashBag()->add('errore', $this->trans->trans('exception.scrutinio_presenza'));
      }
      // se niente errori cambia stato
      if (!$this->reqstack->getSession()->getFlashBag()->has('errore')) {
        // dati docenti
        $docenti = $this->em->getRepository('App\Entity\Cattedra')->docentiScrutinio($classe);
        // memorizza dati docenti e materie
        $dati_docenti = array();
        foreach ($docenti as $doc) {
          $dati_docenti[$doc['id']][$doc['materia_id']] = $doc['tipo'];
        }
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
        $valori['docenti'] = $dati_docenti;
        $scrutinio->setDati($valori);
        // aggiorna stato
        $scrutinio->setStato('2');
        $this->em->flush();
        // log
        $this->dblogger->logAzione('SCRUTINIO', 'Cambio stato', array(
          'Scrutinio' => $scrutinio->getId(),
          'Classe' => $classe->getId(),
          'Periodo' => 'S',
          'Stato iniziale' => '1',
          'Stato finale' => '2',
          ));
        // ok
        return true;
      }
    } else {
      // imposta messaggi per eventuali altri errori del form
      foreach ($form->getErrors() as $error) {
        $this->reqstack->getSession()->getFlashBag()->add('errore', $error->getMessage());
      }
    }
    // errore
    return false;
  }

  /**
   * Esegue il passaggio di stato 2->1 per lo scrutinio del periodo S
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_S_2_1(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // inizializza messaggi di errore
    $this->reqstack->getSession()->getFlashBag()->clear();
    // aggiorna stato
    $scrutinio->setStato('1');
    $this->em->flush();
    // log
    $this->dblogger->logAzione('SCRUTINIO', 'Cambio stato', array(
      'Scrutinio' => $scrutinio->getId(),
      'Classe' => $classe->getId(),
      'Periodo' => 'S',
      'Stato iniziale' => '2',
      'Stato finale' => '1',
      ));
    // ok
    return true;
  }

  /**
   * Esegue il passaggio di stato 2->3 per lo scrutinio del periodo S
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
   public function passaggioStato_S_2_3(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // inizializza messaggi di errore
    $this->reqstack->getSession()->getFlashBag()->clear();
    // legge ed.civica
    $edcivica = $this->em->getRepository('App\Entity\Materia')->findOneByTipo('E');
    // elenco voti/alunni
    $dati = $this->elencoVoti($docente, $classe, $edcivica, 'S');
    // controlla errori
    $errore = array();
    foreach ($dati['voti'] as $alunno=>$voto) {
      if ($voto->getUnico() === null) {
        // voto non presente
        $errore['exception.voto_edcivica'] = true;
      }
    }
    // imposta messaggi di errore
    foreach ($errore as $msg=>$v) {
      $this->reqstack->getSession()->getFlashBag()->add('errore',
          $this->trans->trans($msg, ['materia' => $edcivica->getNomeBreve()]));
    }
    if (empty($errore)) {
      // aggiorna stato
      $scrutinio->setStato('3');
      $this->em->flush();
      // log
      $this->dblogger->logAzione('SCRUTINIO', 'Cambio stato', array(
        'Scrutinio' => $scrutinio->getId(),
        'Classe' => $classe->getId(),
        'Periodo' => 'S',
        'Stato iniziale' => '2',
        'Stato finale' => '3',
        ));
      // ok
      return true;
    }
    // errori presenti
    return false;
  }

  /**
   * Esegue il passaggio di stato 3->4 per lo scrutinio del periodo S
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_S_3_4(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // inizializza messaggi di errore
    $this->reqstack->getSession()->getFlashBag()->clear();
    // legge condotta
    $condotta = $this->em->getRepository('App\Entity\Materia')->findOneByTipo('C');
    // elenco voti/alunni
    $dati = $this->elencoVoti($docente, $classe, $condotta, 'S');
    // controlla errori
    $errore = array();
    foreach ($dati['voti'] as $alunno=>$voto) {
      if ($voto->getUnico() === null) {
        // voto non presente
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
    if (empty($errore)) {
      // aggiorna stato
      $scrutinio->setStato('4');
      $this->em->flush();
      // log
      $this->dblogger->logAzione('SCRUTINIO', 'Cambio stato', array(
        'Scrutinio' => $scrutinio->getId(),
        'Classe' => $classe->getId(),
        'Periodo' => 'S',
        'Stato iniziale' => '3',
        'Stato finale' => '4',
        ));
      // ok
      return true;
    }
    // imposta messaggi di errore
    foreach ($errore as $msg=>$v) {
      $this->reqstack->getSession()->getFlashBag()->add('errore', $this->trans->trans($msg));
    }
    // errori presenti
    return false;
  }

  /**
   * Esegue il passaggio di stato 3->2 per lo scrutinio del periodo S
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_S_3_2(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // inizializza messaggi di errore
    $this->reqstack->getSession()->getFlashBag()->clear();
    // aggiorna stato
    $scrutinio->setStato('2');
    $this->em->flush();
    // log
    $this->dblogger->logAzione('SCRUTINIO', 'Cambio stato', array(
      'Scrutinio' => $scrutinio->getId(),
      'Classe' => $classe->getId(),
      'Periodo' => 'S',
      'Stato iniziale' => '3',
      'Stato finale' => '2',
      ));
    // ok
    return true;
  }

  /**
   * Esegue il passaggio di stato 3->4 per lo scrutinio del periodo S
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_S_4_5(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // inizializza messaggi di errore
    $this->reqstack->getSession()->getFlashBag()->clear();
    // legge voti
    $dati = $this->quadroVoti($docente, $classe, 'S');
    // controlli
    $errori = array();
    foreach ($dati['alunni'] as $a=>$alu) {
      foreach ($dati['materie'] as $m=>$mat) {
        if ($mat['tipo'] == 'R') {
          // religione
          if (in_array($alu['religione'], ['S', 'A']) && !isset($dati['voti'][$a][$m])) {
            // mancano valutazioni
            $errori[$m] = 1;
          }
        } elseif (in_array($mat['tipo'], ['N', 'E'])) {
          // altre materie (esclusa condotta, compresa ed.civica)
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
        } else {
          // condotta
          if (!isset($dati['voti'][$a][$m]['unico'])) {
            // mancano valutazioni
            $errori[$m] = 1;
          } elseif (!isset($errori[$m]) && empty($dati['voti'][$a][$m]['dati']['motivazione'])) {
            // manca motivazione
            $errori[$m] = 11;
          } elseif (!isset($errori[$m]) && $dati['voti'][$a][$m]['dati']['unanimita'] === null) {
            // manca delibera
            $errori[$m] = 12;
          } elseif (!isset($errori[$m]) && $dati['voti'][$a][$m]['dati']['unanimita'] === false &&
                    empty($dati['voti'][$a][$m]['dati']['contrari'])) {
            // mancano contrari
            $errori[$m] = 13;
          }
        }
      }
    }
    if (empty($errori)) {
      // legge definizione scrutinio e verbale
      $def = $this->em->getRepository('App\Entity\DefinizioneScrutinio')->findOneByPeriodo('S');
      $scrutinio_dati = $scrutinio->getDati();
      foreach ($def->getStruttura() as $step=>$args) {
        if ($args[0] == 'Argomento') {
          // resetta validazione
          $scrutinio_dati['verbale'][$step]['validato'] = false;
        }
      }
      // memorizza dati scrutinio
      $scrutinio->setDati($scrutinio_dati);
      // aggiorna stato
      $scrutinio->setStato('5');
      $this->em->flush();
      // log
      $this->dblogger->logAzione('SCRUTINIO', 'Cambio stato', array(
        'Scrutinio' => $scrutinio->getId(),
        'Classe' => $classe->getId(),
        'Periodo' => 'S',
        'Stato iniziale' => '4',
        'Stato finale' => '5',
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
            $this->reqstack->getSession()->getFlashBag()->add('errore', $this->trans->trans('exception.no_voto_scrutinio',
              ['materia' => $mat['nomeBreve']]));
            break;
          case 2:
            // mancano recuperi
            $this->reqstack->getSession()->getFlashBag()->add('errore', $this->trans->trans('exception.no_recupero_scrutinio',
              ['materia' => $mat['nomeBreve']]));
            break;
          case 3:
            // mancano debiti
            $this->reqstack->getSession()->getFlashBag()->add('errore', $this->trans->trans('exception.no_debito_scrutinio',
              ['materia' => $mat['nomeBreve']]));
            break;
          case 11:
            // manca motivazione
            $this->reqstack->getSession()->getFlashBag()->add('errore', $this->trans->trans('exception.motivazione_condotta'));
            break;
          case 12:
            // manca delibera
            $this->reqstack->getSession()->getFlashBag()->add('errore', $this->trans->trans('exception.delibera_condotta'));
            break;
          case 13:
            // mancano contrari
            $this->reqstack->getSession()->getFlashBag()->add('errore', $this->trans->trans('exception.contrari_condotta'));
            break;
        }
      }
    }
    // errore
    return false;
  }

  /**
   * Esegue il passaggio di stato 4->3 per lo scrutinio del periodo S
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_S_4_3(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // inizializza messaggi di errore
    $this->reqstack->getSession()->getFlashBag()->clear();
    // aggiorna stato
    $scrutinio->setStato('3');
    $this->em->flush();
    // log
    $this->dblogger->logAzione('SCRUTINIO', 'Cambio stato', array(
      'Scrutinio' => $scrutinio->getId(),
      'Classe' => $classe->getId(),
      'Periodo' => 'S',
      'Stato iniziale' => '4',
      'Stato finale' => '3',
      ));
    // ok
    return true;
  }

  /**
   * Esegue il passaggio di stato 4->3 per lo scrutinio del periodo S
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_S_5_4(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // inizializza messaggi di errore
    $this->reqstack->getSession()->getFlashBag()->clear();
    // aggiorna stato
    $scrutinio->setStato('4');
    $this->em->flush();
    // log
    $this->dblogger->logAzione('SCRUTINIO', 'Cambio stato', array(
      'Scrutinio' => $scrutinio->getId(),
      'Classe' => $classe->getId(),
      'Periodo' => 'S',
      'Stato iniziale' => '5',
      'Stato finale' => '4',
      ));
    // ok
    return true;
  }

  /**
   * Esegue il passaggio di stato 5->C per lo scrutinio del periodo S
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_S_5_C(Docente $docente, Request $request, Form $form,
                                       Classe $classe, Scrutinio $scrutinio) {
    // inizializza messaggi di errore
    $this->reqstack->getSession()->getFlashBag()->clear();
    // legge dati form
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // controlli
      if (!$form->get('fine')->getData()) {
        // ora non presente
        $this->reqstack->getSession()->getFlashBag()->add('errore', $this->trans->trans('exception.scrutinio_fine'));
      }
      // controlla validazione argomenti
      $def = $this->em->getRepository('App\Entity\DefinizioneScrutinio')->findOneByPeriodo('S');
      if (!isset($scrutinio->getDati()['verbale'])) {
        // errore di validazione
        $this->reqstack->getSession()->getFlashBag()->add('errore', $this->trans->trans('exception.verbale_argomento_mancante',
          ['sezione' => '']));
      } else {
        foreach ($scrutinio->getDati()['verbale'] as $step=>$args) {
          // solo elementi da validare
          if (isset($args['validato']) && !$args['validato']) {
            // errore di validazione
            $this->reqstack->getSession()->getFlashBag()->add('errore', $this->trans->trans('exception.verbale_argomento_mancante',
              ['sezione' => $def->getStruttura()[$step][2]['sezione']]));
          }
        }
      }
      // se niente errori cambia stato
      if (!$this->reqstack->getSession()->getFlashBag()->has('errore')) {
        // imposta ora fine
        $scrutinio->setFine($form->get('fine')->getData());
        // aggiorna stato
        $scrutinio->setStato('C');
        $this->em->flush();
        // log
        $this->dblogger->logAzione('SCRUTINIO', 'Cambio stato', array(
          'Scrutinio' => $scrutinio->getId(),
          'Classe' => $classe->getId(),
          'Periodo' => 'S',
          'Stato iniziale' => '5',
          'Stato finale' => 'C',
          ));
        // ok
        return true;
      }
    } else {
      // imposta messaggi per eventuali altri errori del form
      foreach ($form->getErrors() as $error) {
        $this->reqstack->getSession()->getFlashBag()->add('errore', $error->getMessage());
      }
    }
    // errore
    return false;
  }

  /**
   * Esegue il passaggio di stato C->5 per lo scrutinio del periodo S
   *
   * @param Docente $docente Docente che inserisce i dati dello scrutinio
   * @param Request $request Pagina richiesta
   * @param Form $form Form per lo scrutinio
   * @param Classe $classe Classe di cui leggere i dati dello scrutinio
   * @param Scrutinio $scrutinio Scrutinio da modificare
   *
   * @return boolean Vero se passaggio di stato eseguito correttamente, falso altrimenti
   */
  public function passaggioStato_S_C_5(Docente $docente, Request $request, Form $form,
                                        Classe $classe, Scrutinio $scrutinio) {
    // controlla se è possibile riapertura
    if (!($docente instanceOf Staff) || $scrutinio->getSincronizzazione()) {
      // errore
      return false;
    }
    // inizializza messaggi di errore
    $this->reqstack->getSession()->getFlashBag()->clear();
    // rinomina documenti di classe
    $fs = new Filesystem();
    $finder = new Finder();
    $percorso = $this->root.'/'.$this->directory['S'].'/'.$classe->getAnno().$classe->getSezione();
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
    $scrutinio->setStato('5');
    $this->em->flush();
    // log
    $this->dblogger->logAzione('SCRUTINIO', 'Cambio stato', array(
      'Scrutinio' => $scrutinio->getId(),
      'Classe' => $classe->getId(),
      'Periodo' => 'S',
      'Stato iniziale' => 'C',
      'Stato finale' => '5',
      ));
    // ok
    return true;
  }

}
