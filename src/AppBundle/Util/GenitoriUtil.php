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
use AppBundle\Entity\Genitore;
use AppBundle\Entity\Alunno;
use AppBundle\Entity\Classe;
use AppBundle\Entity\Materia;


/**
 * GenitoriUtil - classe di utilità per le funzioni disponibili ai genitori
 */
class GenitoriUtil {


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
   * @var RegistroUtil $regUtil Funzioni di utilità per il registro
   */
  private $regUtil;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Construttore
   *
   * @param RouterInterface $router Gestore delle URL
   * @param EntityManagerInterface $em Gestore delle entità
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param SessionInterface $session Gestore delle sessioni
   * @param RegistroUtil $regUtil Funzioni di utilità per il registro
   */
  public function __construct(RouterInterface $router, EntityManagerInterface $em, TranslatorInterface $trans,
                               SessionInterface $session, RegistroUtil $regUtil) {
    $this->router = $router;
    $this->em = $em;
    $this->trans = $trans;
    $this->session = $session;
    $this->regUtil = $regUtil;
  }

  /**
   * Restituisce l'alunno dato il genitore.
   *
   * @param Genitore $genitore Genitore dell'alunno
   *
   * @return Alunno Alunno figlio dell'utente genitore
   */
  public function alunno(Genitore $genitore) {
    $alunno = $this->em->getRepository('AppBundle:Alunno')->createQueryBuilder('a')
      ->join('AppBundle:Genitore', 'g', 'WHERE', 'a.id=g.alunno')
      ->where('g.id=:genitore AND a.abilitato=:abilitato AND g.abilitato=:abilitato')
      ->setParameters(['genitore' => $genitore, 'abilitato' => 1])
      ->getQuery()
      ->getOneOrNullResult();
    return $alunno;
  }

  /**
   * Restituisce i dati delle lezioni per la classe e la data indicata.
   *
   * @param \DateTime $data Data del giorno di lezione
   * @param Classe $classe Classe della lezione
   * @param Alunno $alunno Alunno di riferimento (per il sostegno)
   *
   * @return array Dati restituiti come array associativo
   */
  public function lezioni(\DateTime $data, Classe $classe, Alunno $alunno) {
    // inizializza
    $dati = array();
    // legge orario
    $scansioneoraria = $this->regUtil->orarioInData($data, $classe->getSede());
    // predispone dati lezioni come array associativo
    $dati_lezioni = array();
    foreach ($scansioneoraria as $s) {
      $ora = $s['ora'];
      $dati_lezioni[$ora]['inizio'] = substr($s['inizio'], 0, 5);
      $dati_lezioni[$ora]['fine'] = substr($s['fine'], 0, 5);
      // legge lezione
      $lezione = $this->em->getRepository('AppBundle:Lezione')->createQueryBuilder('l')
        ->where('l.data=:data AND l.classe=:classe AND l.ora=:ora')
        ->setParameters(['data' => $data->format('Y-m-d'), 'classe' => $classe, 'ora' => $ora])
        ->getQuery()
        ->getOneOrNullResult();
      if ($lezione) {
        // esiste lezione
        $dati_lezioni[$ora]['materia'] = $lezione->getMateria()->getNomeBreve();
        $dati_lezioni[$ora]['argomenti'] = trim($lezione->getArgomento());
        $dati_lezioni[$ora]['attivita'] = trim($lezione->getAttivita());
        $dati_lezioni[$ora]['sostegno'] = '';
        if ($alunno->getBes() == 'H') {
          // legge sostegno
          $sostegno = $this->em->getRepository('AppBundle:FirmaSostegno')->createQueryBuilder('fs')
            ->where('fs.lezione=:lezione AND fs.alunno=:alunno')
            ->setParameters(['lezione' => $lezione, 'alunno' => $alunno])
            ->getQuery()
            ->getResult();
          foreach ($sostegno as $sost) {
            $dati_lezioni[$ora]['sostegno'] .= ' '.trim($sost->getArgomento().' '.$sost->getAttivita());
          }
          $dati_lezioni[$ora]['sostegno'] = trim($dati_lezioni[$ora]['sostegno']);
        }
      } else {
        // nessuna lezione esistente
        $dati_lezioni[$ora]['materia'] = '';
        $dati_lezioni[$ora]['argomenti'] = '';
        $dati_lezioni[$ora]['attivita'] = '';
        $dati_lezioni[$ora]['sostegno'] = '';
      }
    }
    // memorizza lezioni del giorno
    $dati['lezioni'] = $dati_lezioni;
    // legge annotazioni
    $annotazioni = $this->em->getRepository('AppBundle:Annotazione')->createQueryBuilder('a')
      ->join('a.docente', 'd')
      ->where('a.data=:data AND a.classe=:classe AND a.visibile=:visibile')
      ->orderBy('a.modificato', 'DESC')
      ->setParameters(['data' => $data->format('Y-m-d'), 'classe' => $classe, 'visibile' => 1])
      ->getQuery()
      ->getResult();
    $lista = array();
    foreach ($annotazioni as $ann) {
      $lista[] = $ann->getTesto();
    }
    // memorizza annotazioni del giorno
    $dati['annotazioni'] = $lista;
    // restituisce dati
    return $dati;
  }

  /**
   * Restituisce le materie per la classe indicata.
   *
   * @param Classe $classe Classe della lezione
   * @param bool $sostegno Vero se si deve aggiungere il sostegno
   *
   * @return array Dati restituiti come array associativo
   */
  public function materie(Classe $classe, $sostegno) {
    $materie = $this->em->getRepository('AppBundle:Cattedra')->createQueryBuilder('c')
      ->select('DISTINCT m.id,m.nomeBreve')
      ->join('c.materia', 'm')
      ->where('c.classe=:classe AND c.tipo=:tipo AND c.attiva=:attiva')
      ->orderBy('m.nomeBreve', 'ASC')
      ->setParameters(['classe' => $classe, 'tipo' => 'N', 'attiva' => 1])
      ->getQuery()
      ->getArrayResult();
    if ($sostegno) {
      $materia_sost = $this->em->getRepository('AppBundle:Materia')->findOneByTipo('S');
      if ($materia_sost) {
        $materie = array_merge(
          [array('id' => $materia_sost->getId(), 'nomeBreve' => $materia_sost->getNomeBreve())],
          $materie);
      }
    }
    return $materie;
  }

  /**
   * Restituisce gli argomenti per la classe e materia indicata.
   *
   * @param Classe $classe Classe delle lezioni
   * @param Materia $materia Materia delle lezioni
   * @param Alunno $alunno Alunno di riferimento (per il sostegno)
   *
   * @return array Dati restituiti come array associativo
   */
  public function argomenti(Classe $classe, Materia $materia, Alunno $alunno) {
    $mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    $periodi = $this->regUtil->infoPeriodi();
    $dati = array();
    // legge lezioni
    $lezioni = $this->em->getRepository('AppBundle:Lezione')->createQueryBuilder('l')
      ->select('l.data,l.ora,l.argomento,l.attivita,fs.argomento AS argomento_sost,fs.attivita AS attivita_sost')
      ->leftJoin('AppBundle:FirmaSostegno', 'fs', 'WHERE', 'l.id=fs.lezione AND fs.alunno=:alunno')
      ->where('l.classe=:classe AND l.materia=:materia')
      ->orderBy('l.data', 'DESC')
      ->addOrderBy('l.ora', 'ASC')
      ->setParameters(['classe' => $classe, 'materia' => $materia, 'alunno' => $alunno])
      ->getQuery()
      ->getArrayResult();
    // imposta array associativo
    $vuoto = array();
    $data_prec = null;
    $num = 0;
    foreach ($lezioni as $l) {
      $data = $l['data']->format('Y-m-d');
      if ($data_prec && $data != $data_prec) {
        if ($num == 0) {
          // nessun argomento in data precedente
          $periodo = ($data_prec <= $periodi[1]['fine'] ? $periodi[1]['nome'] :
            ($data_prec <= $periodi[2]['fine'] ? $periodi[2]['nome'] : $periodi[3]['nome']));
          $data_str = intval(substr($data_prec, 8)).' '.$mesi[intval(substr($data_prec, 5, 2))];
          $dati[$periodo][$data_prec][$num]['data'] = $data_str;
          $dati[$periodo][$data_prec][$num]['argomento'] = '';
          $dati[$periodo][$data_prec][$num]['attivita'] = '';
          $dati[$periodo][$data_prec][$num]['argomento_sost'] = '';
          $dati[$periodo][$data_prec][$num]['attivita_sost'] = '';
        } else {
          // fa ripartire contatore
          $num = 0;
        }
      }
      if (trim($l['argomento'].$l['attivita'].$l['argomento_sost'].$l['attivita_sost']) != '') {
        // argomento presente
        if ($num == 0 || strcasecmp($l['argomento'], $dati[$periodo][$data][$num-1]['argomento']) ||
            strcasecmp($l['attivita'], $dati[$periodo][$data][$num-1]['attivita']) ||
            strcasecmp($l['argomento_sost'], $dati[$periodo][$data][$num-1]['argomento_sost']) ||
            strcasecmp($l['attivita_sost'], $dati[$periodo][$data][$num-1]['attivita_sost'])) {
          // evita ripetizioni identiche delgi argomenti
          $periodo = ($data <= $periodi[1]['fine'] ? $periodi[1]['nome'] :
            ($data <= $periodi[2]['fine'] ? $periodi[2]['nome'] : $periodi[3]['nome']));
          $data_str = intval(substr($data, 8)).' '.$mesi[intval(substr($data, 5, 2))];
          $dati[$periodo][$data][$num]['data'] = $data_str;
          $dati[$periodo][$data][$num]['argomento'] = $l['argomento'];
          $dati[$periodo][$data][$num]['attivita'] = $l['attivita'];
          $dati[$periodo][$data][$num]['argomento_sost'] = $l['argomento_sost'];
          $dati[$periodo][$data][$num]['attivita_sost'] = $l['attivita_sost'];
          $num++;
        }
      }
      $data_prec = $data;
    }
    if ($data_prec && $num == 0) {
      // nessun argomento in data precedente
      $periodo = ($data_prec <= $periodi[1]['fine'] ? $periodi[1]['nome'] :
        ($data_prec <= $periodi[2]['fine'] ? $periodi[2]['nome'] : $periodi[3]['nome']));
      $data_str = intval(substr($data_prec, 8)).' '.$mesi[intval(substr($data_prec, 5, 2))];
      $dati[$periodo][$data_prec][$num]['data'] = $data_str;
      $dati[$periodo][$data_prec][$num]['argomento'] = '';
      $dati[$periodo][$data_prec][$num]['attivita'] = '';
      $dati[$periodo][$data_prec][$num]['argomento_sost'] = '';
      $dati[$periodo][$data_prec][$num]['attivita_sost'] = '';
    }
    // restituisce dati come array associativo
    return $dati;
  }

  /**
   * Restituisce gli argomenti del sostegno per la classe e l'alunno indicato.
   *
   * @param Classe $classe Classe delle lezioni
   * @param Alunno $alunno Alunno di riferimento (per il sostegno)
   *
   * @return array Dati restituiti come array associativo
   */
  public function argomentiSostegno(Classe $classe, Alunno $alunno) {
    $mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    $periodi = $this->regUtil->infoPeriodi();
    $dati = array();
    // legge lezioni
    $lezioni = $this->em->getRepository('AppBundle:Lezione')->createQueryBuilder('l')
      ->select('l.data,l.ora,l.argomento,l.attivita,fs.argomento AS argomento_sost,fs.attivita AS attivita_sost,m.nomeBreve')
      ->join('l.materia', 'm')
      ->join('AppBundle:FirmaSostegno', 'fs', 'WHERE', 'l.id=fs.lezione')
      ->where('l.classe=:classe AND fs.alunno=:alunno')
      ->orderBy('l.data', 'DESC')
      ->addOrderBy('m.nomeBreve,l.ora', 'ASC')
      ->setParameters(['classe' => $classe, 'alunno' => $alunno])
      ->getQuery()
      ->getArrayResult();
    // imposta array associativo
    $vuoto = array();
    $data_prec = null;
    $materia_prec = null;
    $num = 0;
    foreach ($lezioni as $l) {
      $data = $l['data']->format('Y-m-d');
      $materia = $l['nomeBreve'];
      if ($data_prec && ($data != $data_prec || $materia != $materia_prec)) {
        if ($num == 0) {
          // nessun argomento
          $periodo = ($data_prec <= $periodi[1]['fine'] ? $periodi[1]['nome'] :
            ($data_prec <= $periodi[2]['fine'] ? $periodi[2]['nome'] : $periodi[3]['nome']));
          $data_str = intval(substr($data_prec, 8)).' '.$mesi[intval(substr($data_prec, 5, 2))];
          $dati[$periodo][$data_prec][$materia_prec][$num]['data'] = $data_str;
          $dati[$periodo][$data_prec][$materia_prec][$num]['argomento'] = '';
          $dati[$periodo][$data_prec][$materia_prec][$num]['attivita'] = '';
          $dati[$periodo][$data_prec][$materia_prec][$num]['argomento_sost'] = '';
          $dati[$periodo][$data_prec][$materia_prec][$num]['attivita_sost'] = '';
        } else {
          // fa ripartire contatore
          $num = 0;
        }
      }
      if (trim($l['argomento'].$l['attivita'].$l['argomento_sost'].$l['attivita_sost']) != '') {
        // argomento presente
        if ($num == 0 || strcasecmp($l['argomento'], $dati[$periodo][$data][$materia][$num-1]['argomento']) ||
            strcasecmp($l['attivita'], $dati[$periodo][$data][$materia][$num-1]['attivita']) ||
            strcasecmp($l['argomento_sost'], $dati[$periodo][$data][$materia][$num-1]['argomento_sost']) ||
            strcasecmp($l['attivita_sost'], $dati[$periodo][$data][$materia][$num-1]['attivita_sost'])) {
          // evita ripetizioni identiche di argomenti
          $periodo = ($data <= $periodi[1]['fine'] ? $periodi[1]['nome'] :
            ($data <= $periodi[2]['fine'] ? $periodi[2]['nome'] : $periodi[3]['nome']));
          $data_str = intval(substr($data, 8)).' '.$mesi[intval(substr($data, 5, 2))];
          $dati[$periodo][$data][$materia][$num]['data'] = $data_str;
          $dati[$periodo][$data][$materia][$num]['argomento'] = $l['argomento'];
          $dati[$periodo][$data][$materia][$num]['attivita'] = $l['attivita'];
          $dati[$periodo][$data][$materia][$num]['argomento_sost'] = $l['argomento_sost'];
          $dati[$periodo][$data][$materia][$num]['attivita_sost'] = $l['attivita_sost'];
          $num++;
        }
      }
      $data_prec = $data;
      $materia_prec = $materia;
    }
    if ($data_prec && $num == 0) {
      // nessun argomento
      $periodo = ($data_prec <= $periodi[1]['fine'] ? $periodi[1]['nome'] :
        ($data_prec <= $periodi[2]['fine'] ? $periodi[2]['nome'] : $periodi[3]['nome']));
      $data_str = intval(substr($data_prec, 8)).' '.$mesi[intval(substr($data_prec, 5, 2))];
      $dati[$periodo][$data_prec][$materia_prec][$num]['data'] = $data_str;
      $dati[$periodo][$data_prec][$materia_prec][$num]['argomento'] = '';
      $dati[$periodo][$data_prec][$materia_prec][$num]['attivita'] = '';
      $dati[$periodo][$data_prec][$materia_prec][$num]['argomento_sost'] = '';
      $dati[$periodo][$data_prec][$materia_prec][$num]['attivita_sost'] = '';
    }
    // restituisce dati come array associativo
    return $dati;
  }

  /**
   * Restituisce i voti per l'alunno e la materia indicata.
   *
   * @param Classe $classe Classe delle lezioni
   * @param Materia $materia Materia delle lezioni
   * @param Alunno $alunno Alunno di cui si desiderano le valutazioni
   *
   * @return array Dati restituiti come array associativo
   */
  public function voti(Classe $classe, Materia $materia=null, Alunno $alunno) {
    $mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    $periodi = $this->regUtil->infoPeriodi();
    $dati = array();
    // legge voti
    $voti = $this->em->getRepository('AppBundle:Valutazione')->createQueryBuilder('v')
      ->select('v.id,v.tipo,v.argomento,v.voto,v.giudizio,l.data,m.nomeBreve')
      ->join('v.lezione', 'l')
      ->join('l.materia', 'm')
      ->where('v.alunno=:alunno AND v.visibile=:visibile AND l.classe=:classe')
      ->orderBy('m.nomeBreve', 'ASC')
      ->addOrderBy('l.data', 'DESC')
      ->setParameters(['alunno' => $alunno, 'visibile' => 1, 'classe' => $classe]);
    if ($materia) {
      $voti = $voti
        ->andWhere('l.materia=:materia')
        ->setParameter('materia', $materia);
    }
    $voti = $voti
      ->getQuery()
      ->getArrayResult();
    // imposta array associativo
    $dati_periodo = array();
    foreach ($voti as $v) {
      $data = $v['data']->format('Y-m-d');
      $numperiodo = ($data <= $periodi[1]['fine'] ? 1 : ($data <= $periodi[2]['fine'] ? 2 : 3));
      $data_str = intval(substr($data, 8)).' '.$mesi[intval(substr($data, 5, 2))];
      $voto_str = '';
      if ($v['voto'] > 0) {
        $voto_int = intval($v['voto'] + 0.25);
        $voto_dec = $v['voto'] - intval($v['voto']);
        $voto_str = $voto_int.($voto_dec == 0.25 ? '+' : ($voto_dec == 0.75 ? '-' : ($voto_dec == 0.5 ? '½' : '')));
      }
      $dati_periodo[$numperiodo][$v['nomeBreve']][$data][] = array(
        'data' => $data_str,
        'id' => $v['id'],
        'tipo' => $v['tipo'],
        'argomento' => $v['argomento'],
        'voto' => $v['voto'],
        'voto_str' => $voto_str,
        'giudizio' => $v['giudizio']
        );
    }
    // ordina periodi
    for ($k = 3; $k >= 1; $k--) {
      if (isset($dati_periodo[$k])) {
        $dati[$periodi[$k]['nome']] = $dati_periodo[$k];
      }
    }
    // restituisce dati come array associativo
    return $dati;
  }

  /**
   * Restituisce le assenze dell'alunno indicato.
   *
   * @param Classe $classe Classe dell'alunno
   * @param Alunno $alunno Alunno di cui si desiderano le assenze
   *
   * @return array Dati restituiti come array associativo
   */
  public function assenze(Classe $classe, Alunno $alunno) {
    $mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    $periodi = $this->regUtil->infoPeriodi();
    $dati = array();
    $dati['lista'] = array();
    // legge assenze
    $assenze = $this->em->getRepository('AppBundle:Alunno')->createQueryBuilder('a')
      ->select('ass.data,ass.giustificato')
      ->join('AppBundle:Assenza', 'ass', 'WHERE', 'a.id=ass.alunno')
      ->where('a.id=:alunno AND a.classe=:classe')
      ->setParameters(['alunno' => $alunno, 'classe' => $classe])
      ->getQuery()
      ->getArrayResult();
    // imposta array associativo per assenze
    $dati_periodo = array();
    foreach ($assenze as $a) {
      $data = $a['data']->format('Y-m-d');
      $numperiodo = ($data <= $periodi[1]['fine'] ? 1 : ($data <= $periodi[2]['fine'] ? 2 : 3));
      $data_str = intval(substr($data, 8)).' '.$mesi[intval(substr($data, 5, 2))];
      $dati_periodo[$numperiodo][$data]['assenza']['data'] = $data_str;
      $dati_periodo[$numperiodo][$data]['assenza']['giustificato'] = ($a['giustificato'] !== null);
    }
    // legge ritardi
    $ritardi = $this->em->getRepository('AppBundle:Alunno')->createQueryBuilder('a')
      ->select('e.data,e.ora,e.note,e.giustificato,e.valido')
      ->join('AppBundle:Entrata', 'e', 'WHERE', 'a.id=e.alunno')
      ->where('a.id=:alunno AND a.classe=:classe')
      ->setParameters(['alunno' => $alunno, 'classe' => $classe])
      ->getQuery()
      ->getArrayResult();
    // imposta array associativo per ritardi
    $num_ritardi = 0;
    $num_brevi = 0;
    $num_ritardi_validi = array(1 => 0, 2 => 0, 3 => 0);
    foreach ($ritardi as $r) {
      $data = $r['data']->format('Y-m-d');
      $numperiodo = ($data <= $periodi[1]['fine'] ? 1 : ($data <= $periodi[2]['fine'] ? 2 : 3));
      $data_str = intval(substr($data, 8)).' '.$mesi[intval(substr($data, 5, 2))];
      $breve = $this->regUtil->seRitardoBreve($r['data'], $r['ora'], $classe->getSede());
      if ($breve) {
        $num_brevi++;
      } else {
        $num_ritardi++;
      }
      if ($r['valido']) {
        $num_ritardi_validi[$numperiodo]++;
      }
      $dati_periodo[$numperiodo][$data]['ritardo']['data'] = $data_str;
      $dati_periodo[$numperiodo][$data]['ritardo']['ora'] = $r['ora'];
      $dati_periodo[$numperiodo][$data]['ritardo']['breve'] = $breve;
      $dati_periodo[$numperiodo][$data]['ritardo']['note'] = $r['note'];
      $dati_periodo[$numperiodo][$data]['ritardo']['giustificato'] = ($r['giustificato'] !== null);
      $dati_periodo[$numperiodo][$data]['ritardo']['valido'] = $r['valido'];
    }
    // legge uscite anticipate
    $uscite = $this->em->getRepository('AppBundle:Alunno')->createQueryBuilder('a')
      ->select('u.data,u.ora,u.note,u.valido')
      ->join('AppBundle:Uscita', 'u', 'WHERE', 'a.id=u.alunno')
      ->where('a.id=:alunno AND a.classe=:classe')
      ->setParameters(['alunno' => $alunno, 'classe' => $classe])
      ->getQuery()
      ->getArrayResult();
    // imposta array associativo per uscite
    $num_uscite_valide = array(1 => 0, 2 => 0, 3 => 0);
    foreach ($uscite as $u) {
      $data = $u['data']->format('Y-m-d');
      $numperiodo = ($data <= $periodi[1]['fine'] ? 1 : ($data <= $periodi[2]['fine'] ? 2 : 3));
      $data_str = intval(substr($data, 8)).' '.$mesi[intval(substr($data, 5, 2))];
      $dati_periodo[$numperiodo][$data]['uscita']['data'] = $data_str;
      $dati_periodo[$numperiodo][$data]['uscita']['ora'] = $u['ora'];
      $dati_periodo[$numperiodo][$data]['uscita']['note'] = $u['note'];
      $dati_periodo[$numperiodo][$data]['uscita']['valido'] = $u['valido'];
      if ($u['valido']) {
        $num_uscite_valide[$numperiodo]++;
      }
    }
    // ordina periodi
    for ($k = 3; $k >= 1; $k--) {
      if (isset($dati_periodo[$k])) {
        krsort($dati_periodo[$k]);
        $dati['lista'][$periodi[$k]['nome']] = $dati_periodo[$k];
      }
    }
    // totale ore di assenza
    $totale = $this->em->getRepository('AppBundle:AssenzaLezione')->createQueryBuilder('al')
      ->select('SUM(al.ore)')
      ->join('al.lezione', 'l')
      ->where('al.alunno=:alunno AND l.classe=:classe')
      ->setParameters(['alunno' => $alunno, 'classe' => $classe])
      ->getQuery()
      ->getSingleScalarResult();
    // percentuale ore di assenza
    $monte = $classe->getOreSettimanali() * 33;
    $perc = round($totale / $monte * 100, 2);
    // statistiche
    $data = (new \DateTime())->format('Y-m-d');
    $numperiodo = ($data <= $periodi[1]['fine'] ? 1 : ($data <= $periodi[2]['fine'] ? 2 : 3));
    $dati['stat']['assenze'] = count($assenze);
    $dati['stat']['brevi'] = $num_brevi;
    $dati['stat']['ritardi'] = $num_ritardi;
    $dati['stat']['ritardi_validi'] = $num_ritardi_validi[$numperiodo];
    $dati['stat']['uscite'] = count($uscite);
    $dati['stat']['uscite_valide'] = $num_uscite_valide[$numperiodo];
    $dati['stat']['ore'] = 0 + $totale;
    $dati['stat']['ore_perc'] = $perc;
    // restituisce dati come array associativo
    return $dati;
  }

  /**
   * Restituisce le note dell'alunno indicato.
   *
   * @param Classe $classe Classe dell'alunno
   * @param Alunno $alunno Alunno di cui si desiderano le assenze
   *
   * @return array Dati restituiti come array associativo
   */
  public function note(Classe $classe, Alunno $alunno) {
    $mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    $periodi = $this->regUtil->infoPeriodi();
    $dati = array();
    // legge note di classe
    $note = $this->em->getRepository('AppBundle:Nota')->createQueryBuilder('n')
      ->select("n.data,n.testo,CONCAT(d.nome,' ',d.cognome) AS docente,n.provvedimento,CONCAT(dp.nome,' ',dp.cognome) AS docente_prov")
      ->join('n.docente', 'd')
      ->leftJoin('n.docenteProvvedimento', 'dp')
      ->where('n.tipo=:tipo AND n.classe=:classe')
      ->setParameters(['tipo' => 'C', 'classe' => $classe])
      ->getQuery()
      ->getArrayResult();
    // imposta array associativo per note di classe
    $dati_periodo = array();
    foreach ($note as $n) {
      $data = $n['data']->format('Y-m-d');
      $numperiodo = ($data <= $periodi[1]['fine'] ? 1 : ($data <= $periodi[2]['fine'] ? 2 : 3));
      $data_str = intval(substr($data, 8)).' '.$mesi[intval(substr($data, 5, 2))];
      $dati_periodo[$numperiodo][$data]['classe']['data'] = $data_str;
      $dati_periodo[$numperiodo][$data]['classe']['nota'] = $n['testo'];
      $dati_periodo[$numperiodo][$data]['classe']['nota_doc'] = $n['docente'];
      $dati_periodo[$numperiodo][$data]['classe']['provvedimento'] = $n['provvedimento'];
      $dati_periodo[$numperiodo][$data]['classe']['provvedimento_doc'] = $n['docente_prov'];
    }
    // legge note individuali
    $individuali = $this->em->getRepository('AppBundle:Nota')->createQueryBuilder('n')
      ->select("n.data,n.testo,CONCAT(d.nome,' ',d.cognome) AS docente,n.provvedimento,CONCAT(dp.nome,' ',dp.cognome) AS docente_prov")
      ->join('n.alunni', 'a')
      ->join('n.docente', 'd')
      ->leftJoin('n.docenteProvvedimento', 'dp')
      ->where('n.tipo=:tipo AND n.classe=:classe AND a.id=:alunno')
      ->setParameters(['tipo' => 'I', 'classe' => $classe, 'alunno' => $alunno])
      ->getQuery()
      ->getArrayResult();
    // imposta array associativo per note di classe
    foreach ($individuali as $i) {
      $data = $i['data']->format('Y-m-d');
      $numperiodo = ($data <= $periodi[1]['fine'] ? 1 : ($data <= $periodi[2]['fine'] ? 2 : 3));
      $data_str = intval(substr($data, 8)).' '.$mesi[intval(substr($data, 5, 2))];
      $dati_periodo[$numperiodo][$data]['individuale']['data'] = $data_str;
      $dati_periodo[$numperiodo][$data]['individuale']['nota'] = $i['testo'];
      $dati_periodo[$numperiodo][$data]['individuale']['nota_doc'] = $i['docente'];
      $dati_periodo[$numperiodo][$data]['individuale']['provvedimento'] = $i['provvedimento'];
      $dati_periodo[$numperiodo][$data]['individuale']['provvedimento_doc'] = $i['docente_prov'];
    }
    // ordina periodi
    for ($k = 3; $k >= 1; $k--) {
      if (isset($dati_periodo[$k])) {
        krsort($dati_periodo[$k]);
        $dati[$periodi[$k]['nome']] = $dati_periodo[$k];
      }
    }
    // restituisce dati come array associativo
    return $dati;
  }

  /**
   * Restituisce le osservazioni sull'alunno indicato.
   *
   * @param Alunno $alunno Alunno di cui si desiderano le osservazioni
   *
   * @return array Dati restituiti come array associativo
   */
  public function osservazioni(Alunno $alunno) {
    $mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    $periodi = $this->regUtil->infoPeriodi();
    $dati = array();
    // legge osservazioni
    $osservazioni = $this->em->getRepository('AppBundle:OsservazioneAlunno')->createQueryBuilder('o')
      ->select('o.id,o.data,o.testo,c.id AS cattedra_id,d.cognome,d.nome,m.nomeBreve')
      ->join('o.cattedra', 'c')
      ->join('c.docente', 'd')
      ->join('c.materia', 'm')
      ->where('o.alunno=:alunno')
      ->orderBy('o.data', 'DESC')
      ->setParameters(['alunno' => $alunno])
      ->getQuery()
      ->getArrayResult();
    // imposta array associativo per note di classe
    foreach ($osservazioni as $o) {
      $data = $o['data']->format('Y-m-d');
      $periodo = ($data <= $periodi[1]['fine'] ? $periodi[1]['nome'] :
        ($data <= $periodi[2]['fine'] ? $periodi[2]['nome'] : $periodi[3]['nome']));
      $data_str = intval(substr($data, 8)).' '.$mesi[intval(substr($data, 5, 2))];
      $dati[$periodo][$data]['data'] = $data_str;
      $dati[$periodo][$data]['materia'] = $o['nomeBreve'];
      $dati[$periodo][$data]['docente'] = $o['nome'].' '.$o['cognome'];
      $dati[$periodo][$data]['testo'] = $o['testo'];
    }
    // restituisce dati come array associativo
    return $dati;
  }

}

