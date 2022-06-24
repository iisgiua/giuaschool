<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2022 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2022
 */


namespace App\Util;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use App\Util\BachecaUtil;
use App\Entity\Alunno;
use App\Entity\Genitore;
use App\Entity\Docente;
use App\Entity\Annotazione;
use App\Entity\AvvisoIndividuale;
use App\Entity\AvvisoClasse;
use App\Entity\Avviso;
use App\Entity\AvvisoUtente;
use App\Entity\Festivita;
use App\Entity\RichiestaColloquio;


/**
 * AgendaUtil - classe di utilità per le funzioni di gestione dell'agenda
 */
class AgendaUtil {


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
   * @var BachecaUtil $bac Classe di utilità per le funzioni di gestione della bacheca
   */
  private $bac;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Construttore
   *
   * @param RouterInterface $router Gestore delle URL
   * @param EntityManagerInterface $em Gestore delle entità
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param BachecaUtil $bac Classe di utilità per le funzioni di gestione della bacheca
   */
  public function __construct(RouterInterface $router, EntityManagerInterface $em, TranslatorInterface $trans,
                               BachecaUtil $bac) {
    $this->router = $router;
    $this->em = $em;
    $this->trans = $trans;
    $this->bac = $bac;
  }

  /**
   * Recupera i dati degli eventi per il docente indicato relativamente al mese indicato
   *
   * @param Docente $docente Docente a cui sono indirizzati gli eventi
   * @param \DateTime $mese Mese di riferemento degli eventi da recuperare
   *
   * @return Array Dati formattati come array associativo
   */
  public function agendaEventi(Docente $docente, $mese) {
    $dati = null;
    // colloqui confermati con il docente
    $colloqui = $this->em->getRepository('App\Entity\RichiestaColloquio')->createQueryBuilder('rc')
      ->join('rc.colloquio', 'c')
      ->where('rc.stato=:stato AND MONTH(rc.appuntamento)=:mese AND c.docente=:docente')
      ->orderBy('rc.appuntamento', 'ASC')
      ->setParameters(['stato' => 'C', 'docente' => $docente, 'mese' => $mese->format('n')])
      ->getQuery()
      ->getResult();
    foreach ($colloqui as $c) {
      $dati[intval($c->getAppuntamento()->format('j'))]['colloqui'] = 1;
    }
    // attivita che coinvolgono il docente o la classe
    $attivita = $this->em->getRepository('App\Entity\Avviso')->createQueryBuilder('a')
      ->join('App\Entity\AvvisoUtente', 'au', 'WITH', 'au.avviso=a.id')
      ->where('a.tipo=:tipo AND MONTH(a.data)=:mese AND au.utente=:docente')
      ->setParameters(['tipo' => 'A', 'mese' => $mese->format('n'), 'docente' => $docente])
      ->getQuery()
      ->getResult();
    foreach ($attivita as $a) {
      $dati[intval($a->getData()->format('j'))]['attivita'] = 1;
    }
    // verifiche inserite dal docente
    $verifiche1 = $this->em->getRepository('App\Entity\Avviso')->createQueryBuilder('a')
      ->where('a.docente=:docente AND a.tipo=:tipo AND MONTH(a.data)=:mese')
      ->setParameters(['docente' => $docente, 'tipo' => 'V', 'mese' => $mese->format('n')])
      ->getQuery()
      ->getResult();
    // verifiche sulla cattedra del docente inserite da itp
    $verifiche2 = $this->em->getRepository('App\Entity\Avviso')->createQueryBuilder('a')
      ->join('a.cattedra', 'c')
      ->join('App\Entity\Cattedra', 'c2', 'WITH', 'c2.classe=c.classe AND c2.materia=c.materia AND c2.docente=:docente')
      ->where('a.docente!=:docente AND a.tipo=:tipo AND MONTH(a.data)=:mese AND c2.attiva=:attiva')
      ->setParameters(['docente' => $docente, 'tipo' => 'V', 'mese' => $mese->format('n'), 'attiva' => 1])
      ->getQuery()
      ->getResult();
    // verifiche sulla cattedra del docente inserite da sostegno
    $verifiche3 = $this->em->getRepository('App\Entity\Avviso')->createQueryBuilder('a')
      ->join('a.cattedra', 'c')
      ->join('App\Entity\Cattedra', 'c2', 'WITH', 'c2.classe=c.classe AND c2.materia=a.materia AND c2.docente=:docente')
      ->where('a.docente!=:docente AND a.tipo=:tipo AND MONTH(a.data)=:mese AND c2.attiva=:attiva')
      ->setParameters(['docente' => $docente, 'tipo' => 'V', 'mese' => $mese->format('n'), 'attiva' => 1])
      ->getQuery()
      ->getResult();
    // verifiche dell'alunno per cattedre di sostegno
    $verifiche4 = $this->em->getRepository('App\Entity\Avviso')->createQueryBuilder('a')
      ->join('a.cattedra', 'c')
      ->join('App\Entity\AvvisoUtente', 'au', 'WITH', 'au.avviso=a.id')
      ->join('App\Entity\Cattedra', 'c2', 'WITH', 'c2.classe=c.classe AND c2.docente=:docente AND c2.alunno=au.utente')
      ->where('a.docente!=:docente AND a.tipo=:tipo AND MONTH(a.data)=:mese AND c2.attiva=:attiva')
      ->setParameters(['docente' => $docente, 'tipo' => 'V', 'mese' => $mese->format('n'), 'attiva' => 1])
      ->getQuery()
      ->getResult();
    foreach (array_merge($verifiche1, $verifiche2, $verifiche3, $verifiche4) as $v) {
      $dati[intval($v->getData()->format('j'))]['verifiche'] = 1;
    }
    // compiti inseriti dal docente
    $compiti1 = $this->em->getRepository('App\Entity\Avviso')->createQueryBuilder('a')
      ->where('a.docente=:docente AND a.tipo=:tipo AND MONTH(a.data)=:mese')
      ->setParameters(['docente' => $docente, 'tipo' => 'P', 'mese' => $mese->format('n')])
      ->getQuery()
      ->getResult();
    // compiti sulla cattedra del docente inserite da itp
    $compiti2 = $this->em->getRepository('App\Entity\Avviso')->createQueryBuilder('a')
      ->join('a.cattedra', 'c')
      ->join('App\Entity\Cattedra', 'c2', 'WITH', 'c2.classe=c.classe AND c2.materia=c.materia AND c2.docente=:docente')
      ->where('a.docente!=:docente AND a.tipo=:tipo AND MONTH(a.data)=:mese AND c2.attiva=:attiva')
      ->setParameters(['docente' => $docente, 'tipo' => 'P', 'mese' => $mese->format('n'), 'attiva' => 1])
      ->getQuery()
      ->getResult();
    // compiti sulla cattedra del docente inserite da sostegno
    $compiti3 = $this->em->getRepository('App\Entity\Avviso')->createQueryBuilder('a')
      ->join('a.cattedra', 'c')
      ->join('App\Entity\Cattedra', 'c2', 'WITH', 'c2.classe=c.classe AND c2.materia=a.materia AND c2.docente=:docente')
      ->where('a.docente!=:docente AND a.tipo=:tipo AND MONTH(a.data)=:mese AND c2.attiva=:attiva')
      ->setParameters(['docente' => $docente, 'tipo' => 'P', 'mese' => $mese->format('n'), 'attiva' => 1])
      ->getQuery()
      ->getResult();
    // compiti dell'alunno per cattedre di sostegno
    $compiti4 = $this->em->getRepository('App\Entity\Avviso')->createQueryBuilder('a')
      ->join('a.cattedra', 'c')
      ->join('App\Entity\AvvisoUtente', 'au', 'WITH', 'au.avviso=a.id')
      ->join('App\Entity\Cattedra', 'c2', 'WITH', 'c2.classe=c.classe AND c2.docente=:docente AND c2.alunno=au.utente')
      ->where('a.docente!=:docente AND a.tipo=:tipo AND MONTH(a.data)=:mese AND c2.attiva=:attiva')
      ->setParameters(['docente' => $docente, 'tipo' => 'P', 'mese' => $mese->format('n'), 'attiva' => 1])
      ->getQuery()
      ->getResult();
    foreach (array_merge($compiti1, $compiti2, $compiti3, $compiti4) as $c) {
      $dati[intval($c->getData()->format('j'))]['compiti'] = 1;
    }
    // festività
    $festivi = $this->em->getRepository('App\Entity\Festivita')->createQueryBuilder('f')
      ->where('f.sede IS NULL AND f.tipo=:tipo AND MONTH(f.data)=:mese')
      ->setParameters(['tipo' => 'F', 'mese' => $mese->format('n')])
      ->orderBy('f.data', 'ASC')
      ->getQuery()
      ->getResult();
    foreach ($festivi as $f) {
      $dati[intval($f->getData()->format('j'))]['festivo'] = 1;
    }
    // azione add
    if ($this->azioneEvento('add', new \DateTime(), $docente, null)) {
      // pulsante add
      $dati['azioni']['add'] = 1;
    }
    // restituisce dati
    return $dati;
  }
  /**

   * Recupera i dettagli degli eventi per il docente indicato relativamente alla data indicata
   *
   * @param Docente $docente Docente a cui sono indirizzati gli eventi
   * @param \DateTime $data Data di riferemento degli eventi da recuperare
   * @param string $tipo Tipo di evento da recuperare
   *
   * @return Array Dati formattati come array associativo
   */
  public function dettagliEvento(Docente $docente, $data, $tipo) {
    $dati = null;
    if ($tipo == 'C') {
      // colloqui
      $dati['colloqui'] = $this->em->getRepository('App\Entity\RichiestaColloquio')->createQueryBuilder('rc')
        ->select('rc.id,rc.messaggio,rc.appuntamento,rc.durata,a.cognome,a.nome,a.sesso,cl.anno,cl.sezione')
        ->join('rc.alunno', 'a')
        ->join('a.classe', 'cl')
        ->join('rc.colloquio', 'c')
        ->where("DATE_FORMAT(rc.appuntamento,'%Y-%m-%d')=:data AND rc.stato=:stato AND c.docente=:docente")
        ->orderBy('rc.appuntamento,cl.anno,cl.sezione,a.cognome,a.nome', 'ASC')
        ->setParameters(['data' => $data->format('Y-m-d'), 'stato' => 'C', 'docente' => $docente])
        ->getQuery()
        ->getArrayResult();
    } elseif ($tipo == 'A') {
      // attività
      $attivita = $this->em->getRepository('App\Entity\Avviso')->createQueryBuilder('a')
        ->join('App\Entity\AvvisoUtente', 'au', 'WITH', 'au.avviso=a.id')
        ->where('a.tipo=:tipo AND a.data=:data AND au.utente=:docente')
        ->setParameters(['tipo' => 'A', 'data' => $data->format('Y-m-d'), 'docente' => $docente])
        ->getQuery()
        ->getResult();
      foreach ($attivita as $a) {
        $dati['attivita'][] = $this->bac->dettagliAvviso($a);
      }
    } elseif ($tipo == 'V') {
      // verifiche inserite dal docente
      $verifiche1 = $this->em->getRepository('App\Entity\Avviso')->createQueryBuilder('a')
        ->where('a.docente=:docente AND a.tipo=:tipo AND a.data=:data')
        ->setParameters(['docente' => $docente, 'tipo' => 'V', 'data' => $data->format('Y-m-d')])
        ->getQuery()
        ->getResult();
      // verifiche sulla cattedra del docente inserite da itp
      $verifiche2 = $this->em->getRepository('App\Entity\Avviso')->createQueryBuilder('a')
        ->join('a.cattedra', 'c')
        ->join('App\Entity\Cattedra', 'c2', 'WITH', 'c2.classe=c.classe AND c2.materia=c.materia AND c2.docente=:docente')
        ->where('a.docente!=:docente AND a.tipo=:tipo AND a.data=:data AND c2.attiva=:attiva')
        ->setParameters(['docente' => $docente, 'tipo' => 'V', 'data' => $data->format('Y-m-d'), 'attiva' => 1])
        ->getQuery()
        ->getResult();
      // verifiche sulla cattedra del docente inserite da sostegno
      $verifiche3 = $this->em->getRepository('App\Entity\Avviso')->createQueryBuilder('a')
        ->join('a.cattedra', 'c')
        ->join('App\Entity\Cattedra', 'c2', 'WITH', 'c2.classe=c.classe AND c2.materia=a.materia AND c2.docente=:docente')
        ->where('a.docente!=:docente AND a.tipo=:tipo AND a.data=:data AND c2.attiva=:attiva')
        ->setParameters(['docente' => $docente, 'tipo' => 'V', 'data' => $data->format('Y-m-d'), 'attiva' => 1])
        ->getQuery()
        ->getResult();
      // verifiche dell'alunno per cattedre di sostegno
      $verifiche4 = $this->em->getRepository('App\Entity\Avviso')->createQueryBuilder('a')
        ->join('a.cattedra', 'c')
        ->join('App\Entity\AvvisoUtente', 'au', 'WITH', 'au.avviso=a.id')
        ->join('App\Entity\Cattedra', 'c2', 'WITH', 'c2.classe=c.classe AND c2.docente=:docente AND c2.alunno=au.utente')
        ->where('a.docente!=:docente AND a.tipo=:tipo AND a.data=:data AND c2.attiva=:attiva')
        ->setParameters(['docente' => $docente, 'tipo' => 'V', 'data' => $data->format('Y-m-d'), 'attiva' => 1])
        ->getQuery()
        ->getResult();
      foreach (array_merge($verifiche1, $verifiche2, $verifiche3, $verifiche4) as $k=>$v) {
        $dati['verifiche'][$k] = $this->bac->dettagliAvviso($v);
        // edit
        if ($this->azioneEvento('edit', $v->getData(), $docente, $v)) {
          // pulsante edit
          $dati['verifiche'][$k]['azioni']['edit'] = 1;
        }
        // delete
        if ($this->azioneEvento('delete', $v->getData(), $docente, $v)) {
          // pulsante delete
          $dati['verifiche'][$k]['azioni']['delete'] = 1;
        }
      }
    } elseif ($tipo == 'P') {
      // compiti
      // verifiche inserite dal docente
      $compiti1 = $this->em->getRepository('App\Entity\Avviso')->createQueryBuilder('a')
        ->where('a.docente=:docente AND a.tipo=:tipo AND a.data=:data')
        ->setParameters(['docente' => $docente, 'tipo' => 'P', 'data' => $data->format('Y-m-d')])
        ->getQuery()
        ->getResult();
      // compiti sulla cattedra del docente inserite da itp
      $compiti2 = $this->em->getRepository('App\Entity\Avviso')->createQueryBuilder('a')
        ->join('a.cattedra', 'c')
        ->join('App\Entity\Cattedra', 'c2', 'WITH', 'c2.classe=c.classe AND c2.materia=c.materia AND c2.docente=:docente')
        ->where('a.docente!=:docente AND a.tipo=:tipo AND a.data=:data AND c2.attiva=:attiva')
        ->setParameters(['docente' => $docente, 'tipo' => 'P', 'data' => $data->format('Y-m-d'), 'attiva' => 1])
        ->getQuery()
        ->getResult();
      // compiti sulla cattedra del docente inserite da sostegno
      $compiti3 = $this->em->getRepository('App\Entity\Avviso')->createQueryBuilder('a')
        ->join('a.cattedra', 'c')
        ->join('App\Entity\Cattedra', 'c2', 'WITH', 'c2.classe=c.classe AND c2.materia=a.materia AND c2.docente=:docente')
        ->where('a.docente!=:docente AND a.tipo=:tipo AND a.data=:data AND c2.attiva=:attiva')
        ->setParameters(['docente' => $docente, 'tipo' => 'P', 'data' => $data->format('Y-m-d'), 'attiva' => 1])
        ->getQuery()
        ->getResult();
      // compiti dell'alunno per cattedre di sostegno
      $compiti4 = $this->em->getRepository('App\Entity\Avviso')->createQueryBuilder('a')
        ->join('a.cattedra', 'c')
        ->join('App\Entity\AvvisoUtente', 'au', 'WITH', 'au.avviso=a.id')
        ->join('App\Entity\Cattedra', 'c2', 'WITH', 'c2.classe=c.classe AND c2.docente=:docente AND c2.alunno=au.utente')
        ->where('a.docente!=:docente AND a.tipo=:tipo AND a.data=:data AND c2.attiva=:attiva')
        ->setParameters(['docente' => $docente, 'tipo' => 'P', 'data' => $data->format('Y-m-d'), 'attiva' => 1])
        ->getQuery()
        ->getResult();
      foreach (array_merge($compiti1, $compiti2, $compiti3, $compiti4) as $k=>$c) {
        $dati['compiti'][$k] = $this->bac->dettagliAvviso($c);
        // edit
        if ($this->azioneEvento('edit', $c->getData(), $docente, $c)) {
          // pulsante edit
          $dati['compiti'][$k]['azioni']['edit'] = 1;
        }
        // delete
        if ($this->azioneEvento('delete', $c->getData(), $docente, $c)) {
          // pulsante delete
          $dati['compiti'][$k]['azioni']['delete'] = 1;
        }
      }
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Controlla se è possibile eseguire l'azione specificata relativamente agli eventi.
   *
   * @param string $azione Azione da controllare
   * @param \DateTime $data Data dell'evento
   * @param Docente $docente Docente che esegue l'azione
   * @param Avviso $avviso Avviso su cui eseguire l'azione
   *
   * @return bool Restituisce vero se l'azione è permessa
   */
  public function azioneEvento($azione, \DateTime $data, Docente $docente, Avviso $avviso=null) {
    if ($azione == 'add') {
      // azione di creazione
      if (!$avviso) {
        // nuovo avviso
        if ($data >= new \DateTime('today')) {
          // data non in passato, ok
          return true;
        }
      }
    } elseif ($azione == 'edit') {
      // azione di modifica
      if ($avviso) {
        // esiste avviso
        if ($data >= new \DateTime('today')) {
          // data non in passato
          if ($docente->getId() == $avviso->getDocente()->getId()) {
            // stesso docente: ok
            return true;
          }
        }
      }
    } elseif ($azione == 'delete') {
      // azione di cancellazione
      if ($avviso) {
        // esiste avviso
        if ($docente->getId() == $avviso->getDocente()->getId()) {
          // stesso docente: ok
          return true;
        }
      }
    }
    // non consentito
    return false;
  }

  /**
   * Controlla la presenza di altre verifiche nello stesso giorno
   *
   * @param Avviso $avviso Avviso su cui eseguire l'azione
   *
   * @return Array Dati formattati come array associativo
   */
  public function controlloVerifiche(Avviso $avviso) {
    $dati = array();
    // verifiche in stessa classe e stessa data
    $verifiche = $this->em->getRepository('App\Entity\Avviso')->createQueryBuilder('a')
      ->join('a.cattedra', 'c')
      ->join('c.classe', 'cl')
      ->where('a.tipo=:tipo AND a.data=:data AND cl.id=:classe')
      ->setParameters(['tipo' => 'V', 'data' => $avviso->getData()->format('Y-m-d'),
        'classe' => $avviso->getCattedra()->getClasse()])
      ->orderBy('cl.anno,cl.sezione', 'ASC');
    if ($avviso->getId()) {
      // modifica di avviso esistente
      $verifiche = $verifiche
        ->andWhere('a.id!=:avviso')
      ->setParameter('avviso', $avviso->getId());
    }
    $verifiche = $verifiche
      ->getQuery()
      ->getResult();
    foreach ($verifiche as $k=>$v) {
      $dati[$k] = $this->bac->dettagliAvviso($v);
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Controlla la presenza di altri compiti nello stesso giorno
   *
   * @param Avviso $avviso Avviso su cui eseguire l'azione
   *
   * @return Array Dati formattati come array associativo
   */
  public function controlloCompiti(Avviso $avviso) {
    $dati = array();
    // compiti in stessa classe e stessa data
    $compiti = $this->em->getRepository('App\Entity\Avviso')->createQueryBuilder('a')
      ->join('a.cattedra', 'c')
      ->join('c.classe', 'cl')
      ->where('a.tipo=:tipo AND a.data=:data AND cl.id=:classe')
      ->setParameters(['tipo' => 'P', 'data' => $avviso->getData()->format('Y-m-d'),
        'classe' => $avviso->getCattedra()->getClasse()])
      ->orderBy('cl.anno,cl.sezione', 'ASC');
    if ($avviso->getId()) {
      // modifica di avviso esistente
      $compiti = $compiti
        ->andWhere('a.id!=:avviso')
      ->setParameter('avviso', $avviso->getId());
    }
    $compiti = $compiti
      ->getQuery()
      ->getResult();
    foreach ($compiti as $k=>$c) {
      $dati[$k] = $this->bac->dettagliAvviso($c);
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Crea l'annotazione sul registro in base ai dati dell'avviso
   *
   * @param Avviso $avviso Avviso di cui recuperare i dati
   */
  public function creaAnnotazione(Avviso $avviso) {
    // crea annotazione
    $a = (new Annotazione())
      ->setData($avviso->getData())
      ->setTesto($avviso->getOggetto()."\n".$avviso->getTesto())
      ->setVisibile(false)
      ->setAvviso($avviso)
      ->setClasse($avviso->getCattedra()->getClasse())
      ->setDocente($avviso->getDocente());
    $this->em->persist($a);
    $avviso->addAnnotazione($a);
  }

  /**
   * Restituisce la lista delle date dei giorni festivi.
   * Non sono considerate le assemblee di istituto (non sono giorni festivi).
   * Sono esclusi i giorni che precedono o seguono il periodo dell'anno scolastico.
   * Non sono indicati i riposi settimanali (domenica ed eventuali altri).
   *
   * @return string Lista di giorni festivi come stringhe di date
   */
  public function festivi() {
    // query
    $lista = $this->em->getRepository('App\Entity\Festivita')->createQueryBuilder('f')
      ->where('f.sede IS NULL AND f.tipo=:tipo')
      ->setParameters(['tipo' => 'F'])
      ->orderBy('f.data', 'ASC')
      ->getQuery()
      ->getResult();
    // crea lista date
    $lista_date = '';
    foreach ($lista as $f) {
      $lista_date .= ',"'.$f->getData()->format('d/m/Y').'"';
    }
    return '['.substr($lista_date, 1).']';
  }

  /**
   * Recupera i dati degli eventi per il genitore dell'alunno indicato relativamente al mese indicato
   *
   * @param Genitore $genitore Genitore a cui sono indirizzati gli eventi
   * @param Alunno $alunno Alunno di riferimento
   * @param \DateTime $mese Mese di riferemento degli eventi da recuperare
   *
   * @return Array Dati formattati come array associativo
   */
  public function agendaEventiGenitori(Genitore $genitore, Alunno $alunno, $mese) {
    $dati = null;
    // colloqui
    $colloqui = $this->em->getRepository('App\Entity\RichiestaColloquio')->createQueryBuilder('rc')
      ->where('rc.stato=:stato AND rc.alunno=:alunno AND MONTH(rc.appuntamento)=:mese')
      ->orderBy('rc.appuntamento', 'ASC')
      ->setParameters(['stato' => 'C', 'alunno' => $alunno, 'mese' => $mese->format('n')])
      ->getQuery()
      ->getResult();
    foreach ($colloqui as $c) {
      $dati[intval($c->getAppuntamento()->format('j'))]['colloqui'] = 1;
    }
    // attivita
    $attivita = $this->em->getRepository('App\Entity\Avviso')->createQueryBuilder('a')
      ->join('App\Entity\AvvisoUtente', 'au', 'WITH', 'au.avviso=a.id')
      ->where('a.tipo=:tipo AND MONTH(a.data)=:mese AND au.utente=:genitore')
      ->setParameters(['tipo' => 'A', 'mese' => $mese->format('n'), 'genitore' => $genitore])
      ->getQuery()
      ->getResult();
    foreach ($attivita as $a) {
      $dati[intval($a->getData()->format('j'))]['attivita'] = 1;
    }
    // verifiche
    $verifiche = $this->em->getRepository('App\Entity\Avviso')->createQueryBuilder('a')
      ->join('App\Entity\AvvisoUtente', 'au', 'WITH', 'au.avviso=a.id')
      ->where('a.tipo=:tipo AND MONTH(a.data)=:mese AND au.utente=:alunno')
      ->setParameters(['tipo' => 'V', 'mese' => $mese->format('n'), 'alunno' => $alunno])
      ->getQuery()
      ->getResult();
    foreach ($verifiche as $v) {
      $dati[intval($v->getData()->format('j'))]['verifiche'] = 1;
    }
    // compiti
    $compiti = $this->em->getRepository('App\Entity\Avviso')->createQueryBuilder('a')
      ->join('App\Entity\AvvisoUtente', 'au', 'WITH', 'au.avviso=a.id')
      ->where('a.tipo=:tipo AND MONTH(a.data)=:mese AND au.utente=:alunno')
      ->setParameters(['tipo' => 'P', 'mese' => $mese->format('n'), 'alunno' => $alunno])
      ->getQuery()
      ->getResult();
    foreach ($compiti as $c) {
      $dati[intval($c->getData()->format('j'))]['compiti'] = 1;
    }
    // festività
    $festivi = $this->em->getRepository('App\Entity\Festivita')->createQueryBuilder('f')
      ->where('f.sede IS NULL AND f.tipo=:tipo AND MONTH(f.data)=:mese')
      ->setParameters(['tipo' => 'F', 'mese' => $mese->format('n')])
      ->orderBy('f.data', 'ASC')
      ->getQuery()
      ->getResult();
    foreach ($festivi as $f) {
      $dati[intval($f->getData()->format('j'))]['festivo'] = 1;
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Recupera i dettagli degli eventi per il docente indicato relativamente alla data indicata
   *
   * @param Genitore $genitore Genitore a cui sono indirizzati gli eventi
   * @param Alunno $alunno Alunno a cui sono indirizzati gli eventi
   * @param \DateTime $data Data di riferemento degli eventi da recuperare
   * @param string $tipo Tipo di evento da recuperare
   *
   * @return Array Dati formattati come array associativo
   */
  public function dettagliEventoGenitore(Genitore $genitore, Alunno $alunno, $data, $tipo) {
    $dati = null;
    if ($tipo == 'C') {
      // colloqui
      $dati['colloqui'] = $this->em->getRepository('App\Entity\RichiestaColloquio')->createQueryBuilder('rc')
        ->select('rc.messaggio,rc.appuntamento,rc.durata,d.cognome,d.nome,d.sesso')
        ->join('rc.colloquio', 'c')
        ->join('c.docente', 'd')
        ->where("DATE_FORMAT(rc.appuntamento,'%Y-%m-%d')=:data AND rc.stato=:stato AND rc.alunno=:alunno")
        ->orderBy('c.ora', 'ASC')
        ->setParameters(['data' => $data->format('Y-m-d'), 'stato' => 'C', 'alunno' => $alunno])
        ->getQuery()
        ->getArrayResult();
    } elseif ($tipo == 'A') {
      // attività
      $attivita = $this->em->getRepository('App\Entity\Avviso')->createQueryBuilder('a')
        ->join('App\Entity\AvvisoUtente', 'au', 'WITH', 'au.avviso=a.id')
        ->where('a.tipo=:tipo AND a.data=:data AND au.utente=:genitore')
        ->setParameters(['tipo' => 'A', 'data' => $data->format('Y-m-d'), 'genitore' => $genitore])
        ->getQuery()
        ->getResult();
      foreach ($attivita as $a) {
        $dati['attivita'][] = $this->bac->dettagliAvviso($a);
      }
    } elseif ($tipo == 'V') {
      // verifiche
      $verifiche = $this->em->getRepository('App\Entity\Avviso')->createQueryBuilder('a')
        ->join('App\Entity\AvvisoUtente', 'au', 'WITH', 'au.avviso=a.id')
        ->where('a.tipo=:tipo AND a.data=:data AND au.utente=:alunno')
        ->setParameters(['tipo' => 'V', 'data' => $data->format('Y-m-d'), 'alunno' => $alunno])
        ->getQuery()
        ->getResult();
      foreach ($verifiche as $v) {
        $dati['verifiche'][] = $this->bac->dettagliAvviso($v);
      }
    } elseif ($tipo == 'P') {
      // compiti
      $compiti = $this->em->getRepository('App\Entity\Avviso')->createQueryBuilder('a')
        ->join('App\Entity\AvvisoUtente', 'au', 'WITH', 'au.avviso=a.id')
        ->where('a.tipo=:tipo AND a.data=:data AND au.utente=:alunno')
        ->setParameters(['tipo' => 'P', 'data' => $data->format('Y-m-d'), 'alunno' => $alunno])
        ->getQuery()
        ->getResult();
      foreach ($compiti as $c) {
        $dati['compiti'][] = $this->bac->dettagliAvviso($c);
      }
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Recupera i dati degli eventi per l'alunno indicato relativamente al mese indicato
   *
   * @param Alunno $alunno Alunno a cui sono indirizzati gli eventi
   * @param \DateTime $mese Mese di riferemento degli eventi da recuperare
   *
   * @return Array Dati formattati come array associativo
   */
  public function agendaEventiAlunni(Alunno $alunno, $mese) {
    $dati = null;
    // attivita
    $attivita = $this->em->getRepository('App\Entity\Avviso')->createQueryBuilder('a')
      ->join('App\Entity\AvvisoUtente', 'au', 'WITH', 'au.avviso=a.id')
      ->where('a.tipo=:tipo AND MONTH(a.data)=:mese AND au.utente=:alunno')
      ->setParameters(['tipo' => 'A', 'mese' => $mese->format('n'), 'alunno' => $alunno])
      ->getQuery()
      ->getResult();
    foreach ($attivita as $a) {
      $dati[intval($a->getData()->format('j'))]['attivita'] = 1;
    }
    // verifiche
    $verifiche = $this->em->getRepository('App\Entity\Avviso')->createQueryBuilder('a')
      ->join('App\Entity\AvvisoUtente', 'au', 'WITH', 'au.avviso=a.id')
      ->where('a.tipo=:tipo AND MONTH(a.data)=:mese AND au.utente=:alunno')
      ->setParameters(['tipo' => 'V', 'mese' => $mese->format('n'), 'alunno' => $alunno])
      ->getQuery()
      ->getResult();
    foreach ($verifiche as $v) {
      $dati[intval($v->getData()->format('j'))]['verifiche'] = 1;
    }
    // compiti
    $compiti = $this->em->getRepository('App\Entity\Avviso')->createQueryBuilder('a')
      ->join('App\Entity\AvvisoUtente', 'au', 'WITH', 'au.avviso=a.id')
      ->where('a.tipo=:tipo AND MONTH(a.data)=:mese AND au.utente=:alunno')
      ->setParameters(['tipo' => 'P', 'mese' => $mese->format('n'), 'alunno' => $alunno])
      ->getQuery()
      ->getResult();
    foreach ($compiti as $c) {
      $dati[intval($c->getData()->format('j'))]['compiti'] = 1;
    }
    // festività
    $festivi = $this->em->getRepository('App\Entity\Festivita')->createQueryBuilder('f')
      ->where('f.sede IS NULL AND f.tipo=:tipo AND MONTH(f.data)=:mese')
      ->setParameters(['tipo' => 'F', 'mese' => $mese->format('n')])
      ->orderBy('f.data', 'ASC')
      ->getQuery()
      ->getResult();
    foreach ($festivi as $f) {
      $dati[intval($f->getData()->format('j'))]['festivo'] = 1;
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Recupera i dettagli degli eventi per l'alunno indicato relativamente alla data indicata
   *
   * @param Alunno $alunno Alunno a cui sono indirizzati gli eventi
   * @param \DateTime $data Data di riferemento degli eventi da recuperare
   * @param string $tipo Tipo di evento da recuperare
   *
   * @return Array Dati formattati come array associativo
   */
  public function dettagliEventoAlunno(Alunno $alunno, $data, $tipo) {
    $dati = null;
    if ($tipo == 'A') {
      // attività
      $attivita = $this->em->getRepository('App\Entity\Avviso')->createQueryBuilder('a')
        ->join('App\Entity\AvvisoUtente', 'au', 'WITH', 'au.avviso=a.id')
        ->where('a.tipo=:tipo AND a.data=:data AND au.utente=:alunno')
        ->setParameters(['tipo' => 'A', 'data' => $data->format('Y-m-d'), 'alunno' => $alunno])
        ->getQuery()
        ->getResult();
      foreach ($attivita as $a) {
        $dati['attivita'][] = $this->bac->dettagliAvviso($a);
      }
    } elseif ($tipo == 'V') {
      // verifiche
      $verifiche = $this->em->getRepository('App\Entity\Avviso')->createQueryBuilder('a')
        ->join('App\Entity\AvvisoUtente', 'au', 'WITH', 'au.avviso=a.id')
        ->where('a.tipo=:tipo AND a.data=:data AND au.utente=:alunno')
        ->setParameters(['tipo' => 'V', 'data' => $data->format('Y-m-d'), 'alunno' => $alunno])
        ->getQuery()
        ->getResult();
      foreach ($verifiche as $v) {
        $dati['verifiche'][] = $this->bac->dettagliAvviso($v);
      }
    } elseif ($tipo == 'P') {
      // compiti
      $compiti = $this->em->getRepository('App\Entity\Avviso')->createQueryBuilder('a')
        ->join('App\Entity\AvvisoUtente', 'au', 'WITH', 'au.avviso=a.id')
        ->where('a.tipo=:tipo AND a.data=:data AND au.utente=:alunno')
        ->setParameters(['tipo' => 'P', 'data' => $data->format('Y-m-d'), 'alunno' => $alunno])
        ->getQuery()
        ->getResult();
      foreach ($compiti as $c) {
        $dati['compiti'][] = $this->bac->dettagliAvviso($c);
      }
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Aggiorna data lettura dell'evento
   *
   * @param Avviso $avviso Evento di cui segnare la lettura
   * @param Utente $utente Destinatario dell'avviso
   */
  public function letturaEvento(Avviso $avviso, Utente $utente) {
    // solo avviso indicato
    $au = $this->em->getRepository('App\Entity\AvvisoUtente')->createQueryBuilder('au')
      ->where('au.avviso=:avviso AND au.utente=:utente AND au.letto IS NULL')
      ->setParameters(['avviso' => $avviso, 'utente' => $utente])
      ->getQuery()
      ->getOneOrNullResult();
    // aggiorna data lettura
    if ($au) {
      $au->setLetto(new \DateTime());
      $this->em->flush();
    }
  }

}
