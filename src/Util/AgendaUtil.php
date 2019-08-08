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
use App\Util\BachecaUtil;
use App\Entity\Alunno;
use App\Entity\Docente;
use App\Entity\Annotazione;
use App\Entity\AvvisoIndividuale;
use App\Entity\AvvisoClasse;
use App\Entity\Avviso;


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
    // colloqui
    $colloqui = $this->em->getRepository('App:RichiestaColloquio')->createQueryBuilder('rc')
      ->join('rc.colloquio', 'c')
      ->where('rc.stato=:stato AND MONTH(rc.data)=:mese AND c.docente=:docente')
      ->orderBy('rc.data', 'ASC')
      ->setParameters(['stato' => 'C', 'docente' => $docente, 'mese' => $mese->format('n')])
      ->getQuery()
      ->getResult();
    foreach ($colloqui as $c) {
      $dati[intval($c->getData()->format('j'))]['colloqui'] = 1;
    }
    // attivita
    $attivita = $this->em->getRepository('App:Avviso')->createQueryBuilder('a')
      ->join('App:AvvisoClasse', 'avc', 'WHERE', 'avc.avviso=a.id')
      ->join('avc.classe', 'cl')
      ->join('App:Cattedra', 'c', 'WHERE', 'c.classe=cl.id')
      ->where('a.destinatariDocenti=:destinatario AND a.tipo=:tipo AND MONTH(a.data)=:mese AND c.docente=:docente AND c.attiva=:attiva')
      ->setParameters(['destinatario' => 1, 'tipo' => 'A', 'mese' => $mese->format('n'),
        'docente' => $docente, 'attiva' => 1])
      ->getQuery()
      ->getResult();
    foreach ($attivita as $a) {
      $dati[intval($a->getData()->format('j'))]['attivita'] = 1;
    }
    // verifiche
    $verifiche = $this->em->getRepository('App:Avviso')->createQueryBuilder('a')
      ->where('a.docente=:docente AND a.tipo=:tipo AND MONTH(a.data)=:mese')
      ->setParameters(['docente' => $docente, 'tipo' => 'V', 'mese' => $mese->format('n')])
      ->getQuery()
      ->getResult();
    $verifiche2 = $this->em->getRepository('App:Avviso')->createQueryBuilder('a')
      ->join('a.cattedra', 'c')
      ->join('App:Cattedra', 'c2', 'WHERE', 'c2.classe=c.classe AND c2.docente=:docente AND c2.alunno IS NOT NULL')
      ->leftJoin('App:AvvisoIndividuale', 'avi', 'WHERE', 'avi.avviso=a.id')
      ->where('a.docente!=:docente AND a.tipo=:tipo AND MONTH(a.data)=:mese AND c2.attiva=:attiva')
      ->andWhere('a.destinatariIndividuali=:no_destinatario OR avi.alunno=c2.alunno')
      ->setParameters(['docente' => $docente, 'tipo' => 'V', 'mese' => $mese->format('n'), 'attiva' => 1,
        'no_destinatario' => 0])
      ->getQuery()
      ->getResult();
    foreach (array_merge($verifiche, $verifiche2) as $v) {
      $dati[intval($v->getData()->format('j'))]['verifiche'] = 1;
    }
    // compiti
    $compiti = $this->em->getRepository('App:Avviso')->createQueryBuilder('a')
      ->where('a.docente=:docente AND a.tipo=:tipo AND MONTH(a.data)=:mese')
      ->setParameters(['docente' => $docente, 'tipo' => 'P', 'mese' => $mese->format('n')])
      ->getQuery()
      ->getResult();
    $compiti2 = $this->em->getRepository('App:Avviso')->createQueryBuilder('a')
      ->join('a.cattedra', 'c')
      ->join('App:Cattedra', 'c2', 'WHERE', 'c2.classe=c.classe AND c2.docente=:docente AND c2.alunno IS NOT NULL')
      ->leftJoin('App:AvvisoIndividuale', 'avi', 'WHERE', 'avi.avviso=a.id')
      ->where('a.docente!=:docente AND a.tipo=:tipo AND MONTH(a.data)=:mese AND c2.attiva=:attiva')
      ->andWhere('a.destinatariIndividuali=:no_destinatario OR avi.alunno=c2.alunno')
      ->setParameters(['docente' => $docente, 'tipo' => 'P', 'mese' => $mese->format('n'), 'attiva' => 1,
        'no_destinatario' => 0])
      ->getQuery()
      ->getResult();
    foreach (array_merge($compiti, $compiti2) as $c) {
      $dati[intval($c->getData()->format('j'))]['compiti'] = 1;
    }
    // festività
    $festivi = $this->em->getRepository('App:Festivita')->createQueryBuilder('f')
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
      $dati['colloqui'] = $this->em->getRepository('App:RichiestaColloquio')->createQueryBuilder('rc')
        ->select('rc.id,rc.messaggio,c.giorno,so.inizio,so.fine,a.cognome,a.nome,a.sesso,cl.anno,cl.sezione')
        ->join('rc.alunno', 'a')
        ->join('a.classe', 'cl')
        ->join('rc.colloquio', 'c')
        ->join('c.orario', 'o')
        ->join('App:ScansioneOraria', 'so', 'WHERE', 'so.orario=o.id AND so.giorno=c.giorno AND so.ora=c.ora')
        ->where('rc.data=:data AND rc.stato=:stato AND c.docente=:docente')
        ->orderBy('c.ora,cl.anno,cl.sezione,a.cognome,a.nome', 'ASC')
        ->setParameters(['data' => $data->format('Y-m-d'), 'stato' => 'C', 'docente' => $docente])
        ->getQuery()
        ->getArrayResult();
    } elseif ($tipo == 'A') {
      // attività
      $attivita = $this->em->getRepository('App:Avviso')->createQueryBuilder('a')
        ->join('App:AvvisoClasse', 'avc', 'WHERE', 'avc.avviso=a.id')
        ->join('avc.classe', 'cl')
        ->join('App:Cattedra', 'c', 'WHERE', 'c.classe=cl.id')
        ->where('a.destinatariDocenti=:destinatario AND a.tipo=:tipo AND a.data=:data AND c.docente=:docente AND c.attiva=:attiva')
        ->setParameters(['destinatario' => 1, 'tipo' => 'A', 'data' => $data->format('Y-m-d'),
          'docente' => $docente, 'attiva' => 1])
        ->getQuery()
        ->getResult();
      foreach ($attivita as $a) {
        $dati['attivita'][] = $this->bac->dettagliAvviso($a);
      }
    } elseif ($tipo == 'V') {
      // verifiche
      $verifiche = $this->em->getRepository('App:Avviso')->createQueryBuilder('a')
        ->where('a.docente=:docente AND a.tipo=:tipo AND a.data=:data')
        ->setParameters(['docente' => $docente, 'tipo' => 'V', 'data' => $data->format('Y-m-d')])
        ->getQuery()
        ->getResult();
      $verifiche2 = $this->em->getRepository('App:Avviso')->createQueryBuilder('a')
        ->join('a.cattedra', 'c')
        ->join('App:Cattedra', 'c2', 'WHERE', 'c2.classe=c.classe AND c2.docente=:docente AND c2.alunno IS NOT NULL')
        ->leftJoin('App:AvvisoIndividuale', 'avi', 'WHERE', 'avi.avviso=a.id')
        ->where('a.docente!=:docente AND a.tipo=:tipo AND a.data=:data AND c2.attiva=:attiva')
        ->andWhere('a.destinatariIndividuali=:no_destinatario OR avi.alunno=c2.alunno')
        ->setParameters(['docente' => $docente, 'tipo' => 'V', 'data' => $data->format('Y-m-d'), 'attiva' => 1,
          'no_destinatario' => 0])
        ->getQuery()
        ->getResult();
      foreach (array_merge($verifiche, $verifiche2) as $k=>$v) {
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
      $compiti = $this->em->getRepository('App:Avviso')->createQueryBuilder('a')
        ->where('a.docente=:docente AND a.tipo=:tipo AND a.data=:data')
        ->setParameters(['docente' => $docente, 'tipo' => 'P', 'data' => $data->format('Y-m-d')])
        ->getQuery()
        ->getResult();
      $compiti2 = $this->em->getRepository('App:Avviso')->createQueryBuilder('a')
        ->join('a.cattedra', 'c')
        ->join('App:Cattedra', 'c2', 'WHERE', 'c2.classe=c.classe AND c2.docente=:docente AND c2.alunno IS NOT NULL')
        ->leftJoin('App:AvvisoIndividuale', 'avi', 'WHERE', 'avi.avviso=a.id')
        ->where('a.docente!=:docente AND a.tipo=:tipo AND a.data=:data AND c2.attiva=:attiva')
        ->andWhere('a.destinatariIndividuali=:no_destinatario OR avi.alunno=c2.alunno')
        ->setParameters(['docente' => $docente, 'tipo' => 'P', 'data' => $data->format('Y-m-d'), 'attiva' => 1,
          'no_destinatario' => 0])
        ->getQuery()
        ->getResult();
      foreach (array_merge($compiti, $compiti2) as $k=>$c) {
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
    // verifiche in stessa classe e stessa data
    $verifiche = $this->em->getRepository('App:Avviso')->createQueryBuilder('a')
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
    // restituisce dati
    return $verifiche;
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
    $lista = $this->em->getRepository('App:Festivita')->createQueryBuilder('f')
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
   * Recupera i dati degli eventi per l'alunno indicato relativamente al mese indicato
   *
   * @param Alunno $alunno Alunno a cui sono indirizzati gli eventi
   * @param \DateTime $mese Mese di riferemento degli eventi da recuperare
   *
   * @return Array Dati formattati come array associativo
   */
  public function agendaEventiGenitori(Alunno $alunno, $mese) {
    $dati = null;
    // colloqui
    $colloqui = $this->em->getRepository('App:RichiestaColloquio')->createQueryBuilder('rc')
      ->where('rc.stato=:stato AND rc.alunno=:alunno AND MONTH(rc.data)=:mese')
      ->orderBy('rc.data', 'ASC')
      ->setParameters(['stato' => 'C', 'alunno' => $alunno, 'mese' => $mese->format('n')])
      ->getQuery()
      ->getResult();
    foreach ($colloqui as $c) {
      $dati[intval($c->getData()->format('j'))]['colloqui'] = 1;
    }
    // attivita
    $attivita = $this->em->getRepository('App:Avviso')->createQueryBuilder('a')
      ->join('App:AvvisoClasse', 'avc', 'WHERE', 'avc.avviso=a.id')
      ->join('avc.classe', 'cl')
      ->where('a.destinatariGenitori=:destinatario AND a.tipo=:tipo AND MONTH(a.data)=:mese AND cl.id=:classe')
      ->setParameters(['destinatario' => 1, 'tipo' => 'A', 'mese' => $mese->format('n'),
        'classe' => $alunno->getClasse()])
      ->getQuery()
      ->getResult();
    foreach ($attivita as $a) {
      $dati[intval($a->getData()->format('j'))]['attivita'] = 1;
    }
    // verifiche
    $verifiche = $this->em->getRepository('App:Avviso')->createQueryBuilder('a')
      ->join('a.cattedra', 'c')
      ->leftJoin('App:AvvisoIndividuale', 'avi', 'WHERE', 'avi.avviso=a.id')
      ->leftJoin('avi.alunno', 'al')
      ->where('a.tipo=:tipo AND MONTH(a.data)=:mese AND c.classe=:classe')
      ->andWhere('a.destinatariIndividuali=:no_destinatario OR al.id=:alunno')
      ->setParameters(['tipo' => 'V', 'mese' => $mese->format('n'), 'classe' => $alunno->getClasse(),
        'no_destinatario' => 0, 'alunno' => $alunno])
      ->getQuery()
      ->getResult();
    foreach ($verifiche as $v) {
      $dati[intval($v->getData()->format('j'))]['verifiche'] = 1;
    }
    // compiti
    $compiti = $this->em->getRepository('App:Avviso')->createQueryBuilder('a')
      ->join('a.cattedra', 'c')
      ->leftJoin('App:AvvisoIndividuale', 'avi', 'WHERE', 'avi.avviso=a.id')
      ->leftJoin('avi.alunno', 'al')
      ->where('a.tipo=:tipo AND MONTH(a.data)=:mese AND c.classe=:classe')
      ->andWhere('a.destinatariIndividuali=:no_destinatario OR al.id=:alunno')
      ->setParameters(['tipo' => 'P', 'mese' => $mese->format('n'), 'classe' => $alunno->getClasse(),
        'no_destinatario' => 0, 'alunno' => $alunno])
      ->getQuery()
      ->getResult();
    foreach ($compiti as $c) {
      $dati[intval($c->getData()->format('j'))]['compiti'] = 1;
    }
    // festività
    $festivi = $this->em->getRepository('App:Festivita')->createQueryBuilder('f')
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
   * @param Alunno $alunno Alunno a cui sono indirizzati gli eventi
   * @param \DateTime $data Data di riferemento degli eventi da recuperare
   * @param string $tipo Tipo di evento da recuperare
   *
   * @return Array Dati formattati come array associativo
   */
  public function dettagliEventoGenitore(Alunno $alunno, $data, $tipo) {
    $dati = null;
    if ($tipo == 'C') {
      // colloqui
      $dati['colloqui'] = $this->em->getRepository('App:RichiestaColloquio')->createQueryBuilder('rc')
        ->select('rc.messaggio,so.inizio,so.fine,d.cognome,d.nome,d.sesso')
        ->join('rc.colloquio', 'c')
        ->join('c.docente', 'd')
        ->join('c.orario', 'o')
        ->join('App:ScansioneOraria', 'so', 'WHERE', 'so.orario=o.id AND so.giorno=c.giorno AND so.ora=c.ora')
        ->where('rc.data=:data AND rc.stato=:stato AND rc.alunno=:alunno')
        ->orderBy('c.ora', 'ASC')
        ->setParameters(['data' => $data->format('Y-m-d'), 'stato' => 'C', 'alunno' => $alunno])
        ->getQuery()
        ->getArrayResult();
    } elseif ($tipo == 'A') {
      // attività
      $attivita = $this->em->getRepository('App:Avviso')->createQueryBuilder('a')
        ->join('App:AvvisoClasse', 'avc', 'WHERE', 'avc.avviso=a.id')
        ->join('avc.classe', 'cl')
        ->where('a.destinatariGenitori=:destinatario AND a.tipo=:tipo AND a.data=:data AND cl.id=:classe')
        ->setParameters(['destinatario' => 1, 'tipo' => 'A', 'data' => $data->format('Y-m-d'),
          'classe' => $alunno->getClasse()])
        ->getQuery()
        ->getResult();
      foreach ($attivita as $a) {
        $dati['attivita'][] = $this->bac->dettagliAvviso($a);
      }
    } elseif ($tipo == 'V') {
      // verifiche
      $verifiche = $this->em->getRepository('App:Avviso')->createQueryBuilder('a')
        ->join('a.cattedra', 'c')
        ->leftJoin('App:AvvisoIndividuale', 'avi', 'WHERE', 'avi.avviso=a.id')
        ->leftJoin('avi.alunno', 'al')
        ->where('a.tipo=:tipo AND a.data=:data AND c.classe=:classe')
        ->andWhere('a.destinatariIndividuali=:no_destinatario OR al.id=:alunno')
        ->setParameters(['tipo' => 'V', 'data' => $data->format('Y-m-d'), 'classe' => $alunno->getClasse(),
          'no_destinatario' => 0, 'alunno' => $alunno])
        ->getQuery()
        ->getResult();
      foreach ($verifiche as $v) {
        $dati['verifiche'][] = $this->bac->dettagliAvviso($v);
      }
    } elseif ($tipo == 'P') {
      // compiti
      $compiti = $this->em->getRepository('App:Avviso')->createQueryBuilder('a')
        ->join('a.cattedra', 'c')
        ->leftJoin('App:AvvisoIndividuale', 'avi', 'WHERE', 'avi.avviso=a.id')
        ->leftJoin('avi.alunno', 'al')
        ->where('a.tipo=:tipo AND a.data=:data AND c.classe=:classe')
        ->andWhere('a.destinatariIndividuali=:no_destinatario OR al.id=:alunno')
        ->setParameters(['tipo' => 'P', 'data' => $data->format('Y-m-d'), 'classe' => $alunno->getClasse(),
          'no_destinatario' => 0, 'alunno' => $alunno])
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
  public function agendaEventiGenitoriAlunni(Alunno $alunno, $mese) {
    $dati = null;
    // attivita
    $attivita = $this->em->getRepository('App:Avviso')->createQueryBuilder('a')
      ->join('App:AvvisoClasse', 'avc', 'WHERE', 'avc.avviso=a.id')
      ->join('avc.classe', 'cl')
      ->where('a.destinatariAlunni=:destinatario AND a.tipo=:tipo AND MONTH(a.data)=:mese AND cl.id=:classe')
      ->setParameters(['destinatario' => 1, 'tipo' => 'A', 'mese' => $mese->format('n'),
        'classe' => $alunno->getClasse()])
      ->getQuery()
      ->getResult();
    foreach ($attivita as $a) {
      $dati[intval($a->getData()->format('j'))]['attivita'] = 1;
    }
    // verifiche
    $verifiche = $this->em->getRepository('App:Avviso')->createQueryBuilder('a')
      ->join('a.cattedra', 'c')
      ->leftJoin('App:AvvisoIndividuale', 'avi', 'WHERE', 'avi.avviso=a.id')
      ->leftJoin('avi.alunno', 'al')
      ->where('a.tipo=:tipo AND MONTH(a.data)=:mese AND c.classe=:classe')
      ->andWhere('a.destinatariIndividuali=:no_destinatario OR al.id=:alunno')
      ->setParameters(['tipo' => 'V', 'mese' => $mese->format('n'), 'classe' => $alunno->getClasse(),
        'no_destinatario' => 0, 'alunno' => $alunno])
      ->getQuery()
      ->getResult();
    foreach ($verifiche as $v) {
      $dati[intval($v->getData()->format('j'))]['verifiche'] = 1;
    }
    // compiti
    $compiti = $this->em->getRepository('App:Avviso')->createQueryBuilder('a')
      ->join('a.cattedra', 'c')
      ->leftJoin('App:AvvisoIndividuale', 'avi', 'WHERE', 'avi.avviso=a.id')
      ->leftJoin('avi.alunno', 'al')
      ->where('a.tipo=:tipo AND MONTH(a.data)=:mese AND c.classe=:classe')
      ->andWhere('a.destinatariIndividuali=:no_destinatario OR al.id=:alunno')
      ->setParameters(['tipo' => 'P', 'mese' => $mese->format('n'), 'classe' => $alunno->getClasse(),
        'no_destinatario' => 0, 'alunno' => $alunno])
      ->getQuery()
      ->getResult();
    foreach ($compiti as $c) {
      $dati[intval($c->getData()->format('j'))]['compiti'] = 1;
    }
    // festività
    $festivi = $this->em->getRepository('App:Festivita')->createQueryBuilder('f')
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
  public function dettagliEventoGenitoreAlunno(Alunno $alunno, $data, $tipo) {
    $dati = null;
    if ($tipo == 'A') {
      // attività
      $attivita = $this->em->getRepository('App:Avviso')->createQueryBuilder('a')
        ->join('App:AvvisoClasse', 'avc', 'WHERE', 'avc.avviso=a.id')
        ->join('avc.classe', 'cl')
        ->where('a.destinatariAlunni=:destinatario AND a.tipo=:tipo AND a.data=:data AND cl.id=:classe')
        ->setParameters(['destinatario' => 1, 'tipo' => 'A', 'data' => $data->format('Y-m-d'),
          'classe' => $alunno->getClasse()])
        ->getQuery()
        ->getResult();
      foreach ($attivita as $a) {
        $dati['attivita'][] = $this->bac->dettagliAvviso($a);
      }
    } elseif ($tipo == 'V') {
      // verifiche
      $verifiche = $this->em->getRepository('App:Avviso')->createQueryBuilder('a')
        ->join('a.cattedra', 'c')
        ->leftJoin('App:AvvisoIndividuale', 'avi', 'WHERE', 'avi.avviso=a.id')
        ->leftJoin('avi.alunno', 'al')
        ->where('a.tipo=:tipo AND a.data=:data AND c.classe=:classe')
        ->andWhere('a.destinatariIndividuali=:no_destinatario OR al.id=:alunno')
        ->setParameters(['tipo' => 'V', 'data' => $data->format('Y-m-d'), 'classe' => $alunno->getClasse(),
          'no_destinatario' => 0, 'alunno' => $alunno])
        ->getQuery()
        ->getResult();
      foreach ($verifiche as $v) {
        $dati['verifiche'][] = $this->bac->dettagliAvviso($v);
      }
    } elseif ($tipo == 'P') {
      // compiti
      $compiti = $this->em->getRepository('App:Avviso')->createQueryBuilder('a')
        ->join('a.cattedra', 'c')
        ->leftJoin('App:AvvisoIndividuale', 'avi', 'WHERE', 'avi.avviso=a.id')
        ->leftJoin('avi.alunno', 'al')
        ->where('a.tipo=:tipo AND a.data=:data AND c.classe=:classe')
        ->andWhere('a.destinatariIndividuali=:no_destinatario OR al.id=:alunno')
        ->setParameters(['tipo' => 'P', 'data' => $data->format('Y-m-d'), 'classe' => $alunno->getClasse(),
          'no_destinatario' => 0, 'alunno' => $alunno])
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
   * Restituisce i destinatari indicati nei filtri dell'avviso per verifiche o compiti
   *
   * @param Avviso $avviso Avviso da leggere
   *
   * @return array Dati come array associativo
   */
  public function filtriVerificheCompiti(Avviso $avviso) {
    $dati = array();
    // legge classi
    $dati['classi'] = $this->em->getRepository('App:AvvisoClasse')->createQueryBuilder('avc')
      ->select('(avc.classe) AS classe')
      ->join('avc.avviso', 'av')
      ->join('avc.classe', 'c')
      ->where('av.id=:avviso')
      ->setParameters(['avviso' => $avviso->getId()])
      ->getQuery()
      ->getArrayResult();
    // legge utenti
    $dati['utenti'] = $this->em->getRepository('App:AvvisoIndividuale')->createQueryBuilder('avi')
      ->select('(avi.genitore) AS genitore,(avi.alunno) AS alunno')
      ->join('avi.avviso', 'av')
      ->join('avi.alunno', 'a')
      ->join('avi.genitore', 'g')
      ->where('av.id=:avviso AND g.alunno = a.id AND a.abilitato=:abilitato')
      ->setParameters(['avviso' => $avviso->getId(), 'abilitato' => 1])
      ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
      ->getQuery()
      ->getArrayResult();
    // restituisce dati
    return $dati;
  }

  /**
   * Modifica i destinatari per un avviso esistente per verifiche o compiti
   *
   * @param Avviso $avviso Avviso da leggere
   * @param array $avviso_destinatari Lista dei destinatari esistenti
   * @param string $filtro Filtro per i destinatari [C=classe,I=individuale]
   * @param array $filtro_id Lista di ID del tipo indicato nel filtro
   */
  public function modificaFiltriVerificheCompiti(Avviso $avviso, $avviso_destinatari, $filtro, $filtro_id) {
    // controlla destinatari
    if ($filtro == 'C') {
      // destinataria intera classe
      $avviso->setDestinatariIndividuali(false);
      if (!in_array($filtro_id[0], array_column($avviso_destinatari['classi'], 'classe'))) {
        // nuova classe
        $classe = $this->em->getRepository('App:Classe')->find($filtro_id[0]);
        if ($classe) {
          $ac = (new AvvisoClasse())
            ->setAvviso($avviso)
            ->setClasse($classe);
          $this->em->persist($ac);
        }
      }
    } else {
      // destinatari individuali
      $avviso->setDestinatariIndividuali(true);
      // aggiunge nuovi alunni
      foreach (array_diff($filtro_id, array_column($avviso_destinatari['utenti'], 'alunno')) as $a) {
        // nuovo alunno (potrebbe avere più genitori)
        $lista = $this->em->getRepository('App:Genitore')->findBy(['alunno' => $a]);
        foreach ($lista as $gen) {
          $ai = (new AvvisoIndividuale())
            ->setAvviso($avviso)
            ->setGenitore($gen)
            ->setAlunno($gen->getAlunno());
          $this->em->persist($ai);
        }
      }
    }
    // cancella altre classi (assicura rimanga solo una classe)
    foreach (array_diff(array_column($avviso_destinatari['classi'], 'classe'), array($filtro_id[0])) as $c) {
      $ac = $this->em->getRepository('App:AvvisoClasse')->findOneBy(['avviso' => $avviso->getId(), 'classe' => $c]);
      if ($ac) {
        $this->em->remove($ac);
      }
    }
    // rimuove alunni non più presenti
    foreach (array_diff(array_column($avviso_destinatari['utenti'], 'alunno'), $filtro_id) as $a) {
      // cancella alunno (potrebbe avere più genitori)
      $lista = $this->em->getRepository('App:AvvisoIndividuale')->findBy(['avviso' => $avviso->getId(), 'alunno' => $a]);
      foreach ($lista as $ai) {
        $this->em->remove($ai);
      }
    }
  }

  /**
   * Elimina i destinatari per un avviso esistente per verifiche o compiti
   *
   * @param Avviso $avviso Avviso da leggere
   *
   * @return array Destinatari eliminati, come array associativo
   */
  public function eliminaFiltriVerificheCompiti(Avviso $avviso) {
    $modifiche = array();
    $modifiche['classi'] = [];
    $modifiche['utenti'] = [];
    // classi
    $classi = $this->em->getRepository('App:AvvisoClasse')->findBy(['avviso' => $avviso->getId()]);
    foreach ($classi as $c) {
      $modifiche['classi'][] = $c->getClasse()->getId();
      $this->em->remove($c);
    }
    // utenti
    $utenti = $this->em->getRepository('App:AvvisoIndividuale')->findBy(['avviso' => $avviso->getId()]);
    foreach ($utenti as $u) {
      $modifiche['utenti'][] = ['genitore' => $u->getGenitore()->getId(), 'alunno' => $u->getAlunno()->getId()];
      $this->em->remove($u);
    }
    // restituisce le modifiche
    return $modifiche;
  }

}

