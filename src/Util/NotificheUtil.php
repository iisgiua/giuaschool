<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2020 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2020
 */


namespace App\Util;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use App\Entity\Utente;
use App\Entity\Genitore;
use App\Entity\Alunno;
use App\Entity\Classe;
use App\Entity\Docente;
use App\Entity\Ata;


/**
 * NotificheUtil - classe di utilità per le funzioni sulle notifiche
 */
class NotificheUtil {


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


  //==================== METODI DELLA CLASSE ====================

  /**
   * Construttore
   *
   * @param RouterInterface $router Gestore delle URL
   * @param EntityManagerInterface $em Gestore delle entità
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param SessionInterface $session Gestore delle sessioni
   */
  public function __construct(RouterInterface $router, EntityManagerInterface $em, TranslatorInterface $trans,
                               SessionInterface $session) {
    $this->router = $router;
    $this->em = $em;
    $this->trans = $trans;
    $this->session = $session;
  }

  /**
   * Restituisce le notifiche da mostrare in home
   *
   * @param Utente $utente Utente a cui sono destinate le notifiche
   *
   * @return array Dati restituiti come array associativo
   */
  public function notificheHome(Utente $utente) {
    $dati = array();
    $oggi = new \DateTime('today');
    $ora = new \DateTime('now');
    if ($utente instanceof Genitore) {
      // notifiche per i genitori
      $dati['colloqui'] = null;
      $dati['avvisi'] = 0;
      $dati['circolari'] = 0;
      $dati['verifiche']['oggi'] = 0;
      $dati['verifiche']['prossime'] = 0;
      $dati['compiti']['oggi'] = 0;
      $dati['compiti']['domani'] = 0;
      $alunno = $this->em->getRepository('App:Alunno')->createQueryBuilder('a')
        ->join('a.classe', 'c')
        ->join('App:Genitore', 'g', 'WITH', 'a.id=g.alunno')
        ->where('g.id=:genitore AND a.abilitato=:abilitato AND g.abilitato=:abilitato')
        ->setParameters(['genitore' => $utente, 'abilitato' => 1])
        ->getQuery()
        ->getOneOrNullResult();
      if ($alunno) {
        // legge colloqui
        $dati['colloqui'] = $this->colloquiGenitore($oggi, $alunno);
        // legge avvisi
        $dati['avvisi'] = $this->numeroAvvisi($utente);
        // legge circolari
        $dati['circolari'] = $this->em->getRepository('App:Circolare')->numeroCircolariUtente($utente);
        // legge verifiche
        $dati['verifiche'] = $this->numeroVerificheGenitori($alunno);
        // legge compiti
        $dati['compiti'] = $this->numeroCompitiGenitori($alunno);
        // legge assenze da giustificare
        $dati['giustificazioni'] = $this->em->getRepository('App:Assenza')->assenzeIngiustificate($alunno);
      }
    } elseif ($utente instanceof Alunno) {
      // legge avvisi
      $dati['avvisi'] = $this->numeroAvvisi($utente);
      // legge circolari
      $dati['circolari'] = $this->em->getRepository('App:Circolare')->numeroCircolariUtente($utente);
      // legge verifiche
      $dati['verifiche'] = $this->numeroVerificheGenitori($utente);
      // legge compiti
      $dati['compiti'] = $this->numeroCompitiGenitori($utente);
      // legge assenze da giustificare
      $dati['giustificazioni'] = $this->em->getRepository('App:Assenza')->assenzeIngiustificate($utente);
    } elseif ($utente instanceof Docente) {
      // notifiche per i docenti
      $richieste = $this->em->getRepository('App:RichiestaColloquio')->colloquiDocente($utente, ['R']);
      $dati['richieste'] = count($richieste);
      $dati['colloqui'] = $this->colloquiDocente($oggi, $utente);
      // legge avvisi
      $dati['avvisi'] = $this->numeroAvvisi($utente);
      // legge circolari
      $dati['circolari'] = $this->em->getRepository('App:Circolare')->numeroCircolariUtente($utente);
      // legge verifiche
      $dati['verifiche'] = $this->numeroVerifiche($utente);
      // legge compiti
      $dati['compiti'] = $this->numeroCompiti($utente);
    } elseif ($utente instanceof Ata) {
      // notifiche per gli ata
      $dati['avvisi'] = $this->numeroAvvisi($utente);
      $dati['circolari'] = $this->em->getRepository('App:Circolare')->numeroCircolariUtente($utente);
    }
    return $dati;
  }

  /**
   * Restituisce gli appuntamenti confermati per i colloqui
   *
   * @param \DateTime $data Data del giorno iniziale
   * @param Alunno $alunno Alunno su cui fare i colloqui
   *
   * @return array Dati restituiti come array associativo
   */
  public function colloquiGenitore(\DateTime $data, Alunno $alunno) {
    $settimana = ['Domenica', 'Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato'];
    $mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    $dati = null;
    // legge colloqui esistenti
    $colloqui = $this->em->getRepository('App:RichiestaColloquio')->createQueryBuilder('rc')
      ->select('rc.appuntamento,rc.durata,rc.stato,rc.messaggio,c.dati,d.cognome,d.nome,d.sesso')
      ->join('rc.colloquio', 'c')
      ->join('c.docente', 'd')
      ->where('rc.alunno=:alunno AND rc.appuntamento>=:data AND rc.stato=:stato')
      ->orderBy('rc.appuntamento,c.ora', 'ASC')
      ->setParameters(['alunno' => $alunno, 'data' => $data, 'stato' => 'C'])
      ->getQuery()
      ->getArrayResult();
    foreach ($colloqui as $c) {
      $c['data_str'] = $settimana[$c['appuntamento']->format('w')].' '.intval($c['appuntamento']->format('d')).' '.
        $mesi[intval($c['appuntamento']->format('m'))].' '.$c['appuntamento']->format('Y');
      $c['ora_str'] = 'dalle '.$c['appuntamento']->format('G:i').' alle '.
        $c['appuntamento']->modify('+'.$c['durata'].' minutes')->format('G:i');
      $dati[] = $c;
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Restituisce gli appuntamenti confermati per i colloqui
   *
   * @param \DateTime $data Data e ora del giorno iniziale
   * @param Docente $docente Docente che deve fare i colloqui
   *
   * @return array Dati restituiti come array associativo
   */
  public function colloquiDocente(\DateTime $data, Docente $docente) {
    $settimana = ['Domenica', 'Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato'];
    $mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    $dati = null;
    // legge colloqui confermati
    $colloqui = $this->em->getRepository('App:RichiestaColloquio')->colloquiDocente($docente, ['C'], $data);
    foreach ($colloqui as $c) {
      $c['data_str'] = $settimana[$c['appuntamento']->format('w')].' '.intval($c['appuntamento']->format('d')).' '.
        $mesi[intval($c['appuntamento']->format('m'))].' '.$c['appuntamento']->format('Y');
      $c['ora_str'] = 'dalle '.$c['appuntamento']->format('G:i').' alle '.
        $c['appuntamento']->modify('+'.$c['durata'].' minutes')->format('G:i');
      $dati[] = $c;
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Controlla la presenza di nuovi avvisi destinati all'utente (escluse verifiche/compiti)
   *
   * @param Utente $utente Utente a cui sono indirizzati gli avvisi
   *
   * @return int Numero di avvisi da leggere
   */
  public function numeroAvvisi(Utente $utente) {
    // conta nuovi avvisi
    $avvisi = $this->em->getRepository('App:Avviso')->createQueryBuilder('a')
      ->select('COUNT(a.id)')
      ->join('App:AvvisoUtente', 'au', 'WITH', 'au.avviso=a.id')
      ->where('au.utente=:utente AND au.letto is NULL')
      ->setParameters(['utente' => $utente])
      ->getQuery()
      ->getSingleScalarResult();
    // restituisce numero avvisi
    return $avvisi;
  }

  /**
   * Controlla la presenza di verifiche previste dal docente indicato
   *
   * @param Docente $docente Docente che ha inserito le verifiche
   *
   * @return int Numero di veriche previste
   */
  public function numeroVerifiche(Docente $docente) {
    // conta verifiche di oggi
    $ora = new \DateTime();
    $dati['oggi'] = 0;
    // verifiche per giorno di lezione
    $dati['oggi'] = $this->em->getRepository('App:Avviso')->createQueryBuilder('a')
      ->select('COUNT(a.id)')
      ->where('a.tipo=:tipo AND a.docente=:docente AND a.data=:oggi')
      ->setParameters(['tipo' => 'V', 'docente' => $docente, 'oggi' => $ora->format('Y-m-d')])
      ->getQuery()
      ->getSingleScalarResult();
    // aggiunge verifiche dell'alunno per cattedre di sostegno
    $dati['oggi'] += $this->em->getRepository('App:Avviso')->createQueryBuilder('a')
      ->select('COUNT(a.id)')
      ->join('a.cattedra', 'c')
      ->join('App:AvvisoUtente', 'au', 'WITH', 'au.avviso=a.id')
      ->join('App:Cattedra', 'c2', 'WITH', 'c2.classe=c.classe AND c2.docente=:docente AND c2.alunno=au.utente')
      ->where('a.docente!=:docente AND a.tipo=:tipo AND a.data=:data AND c2.attiva=:attiva')
      ->setParameters(['docente' => $docente, 'tipo' => 'V', 'data' => $ora->format('Y-m-d'), 'attiva' => 1])
      ->getQuery()
      ->getSingleScalarResult();
    // conta prossime verifiche
    $inizio = clone $ora;
    $inizio->modify('+1 day');
    $fine = clone $inizio;
    $fine->modify('+2 days');
    $dati['prossime'] = $this->em->getRepository('App:Avviso')->createQueryBuilder('a')
      ->select('COUNT(a.id)')
      ->where('a.tipo=:tipo AND a.docente=:docente AND a.data BETWEEN :inizio AND :fine')
      ->setParameters(['tipo' => 'V', 'docente' => $docente, 'inizio' => $inizio->format('Y-m-d'),
        'fine' => $fine->format('Y-m-d')])
      ->getQuery()
      ->getSingleScalarResult();
    // aggiunge verifiche dell'alunno per cattedre di sostegno
    $dati['prossime'] += $this->em->getRepository('App:Avviso')->createQueryBuilder('a')
      ->select('COUNT(a.id)')
      ->join('a.cattedra', 'c')
      ->join('App:AvvisoUtente', 'au', 'WITH', 'au.avviso=a.id')
      ->join('App:Cattedra', 'c2', 'WITH', 'c2.classe=c.classe AND c2.docente=:docente AND c2.alunno=au.utente')
      ->where('a.docente!=:docente AND a.tipo=:tipo AND a.data BETWEEN :inizio AND :fine AND c2.attiva=:attiva')
      ->setParameters(['docente' => $docente, 'tipo' => 'V', 'inizio' => $inizio->format('Y-m-d'),
        'fine' => $fine->format('Y-m-d'), 'attiva' => 1])
      ->getQuery()
      ->getSingleScalarResult();
    // restituisce dati
    return $dati;
  }

  /**
   * Controlla la presenza di verifiche previste per l'alunno
   *
   * @param Alunno $alunno Alunno del genitore a cui sono indirizzati gli avvisi
   *
   * @return int Numero di veriche previste
   */
  public function numeroVerificheGenitori(Alunno $alunno) {
    // conta verifiche di oggi
    $ora = new \DateTime();
    $dati['oggi'] = 0;
    // verifiche per giorno di lezione
    $dati['oggi'] = $this->em->getRepository('App:Avviso')->createQueryBuilder('a')
      ->select('COUNT(a.id)')
      ->join('App:AvvisoUtente', 'au', 'WITH', 'au.avviso=a.id')
      ->where('a.tipo=:tipo AND a.data=:oggi AND au.utente=:alunno')
      ->setParameters(['tipo' => 'V', 'oggi' => $ora->format('Y-m-d'), 'alunno' => $alunno])
      ->getQuery()
      ->getSingleScalarResult();
    // conta prossime verifiche
    $inizio = clone $ora;
    $inizio->modify('+1 day');
    $fine = clone $inizio;
    $fine->modify('+2 days');
    $dati['prossime'] = $this->em->getRepository('App:Avviso')->createQueryBuilder('a')
      ->select('COUNT(a.id)')
      ->join('App:AvvisoUtente', 'au', 'WITH', 'au.avviso=a.id')
      ->where('a.tipo=:tipo AND a.data BETWEEN :inizio AND :fine AND au.utente=:alunno')
      ->setParameters(['tipo' => 'V', 'inizio' => $inizio->format('Y-m-d'), 'fine' => $fine->format('Y-m-d'),
        'alunno' => $alunno])
      ->getQuery()
      ->getSingleScalarResult();
    // restituisce dati
    return $dati;
  }

  /**
   * Controlla la presenza di compiti assegati all'alunno
   *
   * @param Alunno $alunno Alunno del genitore a cui sono indirizzati gli avvisi
   *
   * @return int Numero di compiti assegnati
   */
  public function numeroCompitiGenitori(Alunno $alunno) {
    // conta compiti di oggi
    $ora = new \DateTime();
    $dati['oggi'] = 0;
    // compiti per giorno di lezione
    $dati['oggi'] = $this->em->getRepository('App:Avviso')->createQueryBuilder('a')
      ->select('COUNT(a.id)')
      ->join('App:AvvisoUtente', 'au', 'WITH', 'au.avviso=a.id')
      ->where('a.tipo=:tipo AND a.data=:oggi AND au.utente=:alunno')
      ->setParameters(['tipo' => 'P', 'oggi' => $ora->format('Y-m-d'), 'alunno' => $alunno])
      ->getQuery()
      ->getSingleScalarResult();
    // conta compiti per il giorno dopo
    $domani = clone $ora;
    $domani->modify('+1 day');
    $dati['domani'] = $this->em->getRepository('App:Avviso')->createQueryBuilder('a')
      ->select('COUNT(a.id)')
      ->join('App:AvvisoUtente', 'au', 'WITH', 'au.avviso=a.id')
      ->where('a.tipo=:tipo AND a.data=:domani AND au.utente=:alunno')
      ->setParameters(['tipo' => 'P', 'domani' => $domani->format('Y-m-d'), 'alunno' => $alunno])
      ->getQuery()
      ->getSingleScalarResult();
    // restituisce dati
    return $dati;
  }

  /**
   * Controlla la presenza di compiti previsti dal docente indicato
   *
   * @param Docente $docente Docente che ha inserito i compiti
   *
   * @return int Numero di compiti previsti
   */
  public function numeroCompiti(Docente $docente) {
    // conta verifiche di oggi
    $ora = new \DateTime();
    $dati['oggi'] = 0;
    // compiti per giorno di lezione
    $dati['oggi'] = $this->em->getRepository('App:Avviso')->createQueryBuilder('a')
      ->select('COUNT(a.id)')
      ->where('a.tipo=:tipo AND a.docente=:docente AND a.data=:oggi')
      ->setParameters(['tipo' => 'P', 'docente' => $docente, 'oggi' => $ora->format('Y-m-d')])
      ->getQuery()
      ->getSingleScalarResult();
    // aggiunge compiti dell'alunno per cattedre di sostegno
    $dati['oggi'] += $this->em->getRepository('App:Avviso')->createQueryBuilder('a')
      ->select('COUNT(a.id)')
      ->join('a.cattedra', 'c')
      ->join('App:AvvisoUtente', 'au', 'WITH', 'au.avviso=a.id')
      ->join('App:Cattedra', 'c2', 'WITH', 'c2.classe=c.classe AND c2.docente=:docente AND c2.alunno=au.utente')
      ->where('a.docente!=:docente AND a.tipo=:tipo AND a.data=:data AND c2.attiva=:attiva')
      ->setParameters(['docente' => $docente, 'tipo' => 'P', 'data' => $ora->format('Y-m-d'), 'attiva' => 1])
      ->getQuery()
      ->getSingleScalarResult();
    // conta compiti per il giorno dopo
    $domani = clone $ora;
    $domani->modify('+1 day');
    $dati['domani'] = $this->em->getRepository('App:Avviso')->createQueryBuilder('a')
      ->select('COUNT(a.id)')
      ->where('a.tipo=:tipo AND a.docente=:docente AND a.data=:domani')
      ->setParameters(['tipo' => 'P', 'docente' => $docente, 'domani' => $domani->format('Y-m-d')])
      ->getQuery()
      ->getSingleScalarResult();
    // aggiunge compiti dell'alunno per cattedre di sostegno
    $dati['domani'] += $this->em->getRepository('App:Avviso')->createQueryBuilder('a')
      ->select('COUNT(a.id)')
      ->join('a.cattedra', 'c')
      ->join('App:AvvisoUtente', 'au', 'WITH', 'au.avviso=a.id')
      ->join('App:Cattedra', 'c2', 'WITH', 'c2.classe=c.classe AND c2.docente=:docente AND c2.alunno=au.utente')
      ->where('a.docente!=:docente AND a.tipo=:tipo AND a.data=:domani AND c2.attiva=:attiva')
      ->setParameters(['docente' => $docente, 'tipo' => 'P', 'domani' => $domani->format('Y-m-d'),
        'attiva' => 1])
      ->getQuery()
      ->getSingleScalarResult();
    // restituisce dati
    return $dati;
  }

}
