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
use AppBundle\Entity\Utente;
use AppBundle\Entity\Genitore;
use AppBundle\Entity\Alunno;
use AppBundle\Entity\Classe;
use AppBundle\Entity\Docente;


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
    $dati = null;
    $oggi = new \DateTime('today');
    $ora = new \DateTime('now');
    if ($utente instanceof Genitore) {
      // notifiche per i genitori
      //-- $dati['annotazioni'] = null;
      $dati['colloqui'] = null;
      $dati['avvisi'] = 0;
      $alunno = $this->em->getRepository('AppBundle:Alunno')->createQueryBuilder('a')
        ->join('a.classe', 'c')
        ->join('AppBundle:Genitore', 'g', 'WHERE', 'a.id=g.alunno')
        ->where('g.id=:genitore AND a.abilitato=:abilitato AND g.abilitato=:abilitato')
        ->setParameters(['genitore' => $utente, 'abilitato' => 1])
        ->getQuery()
        ->getOneOrNullResult();
      if ($alunno) {
        // legge colloqui
        $dati['colloqui'] = $this->colloquiGenitore($oggi, $ora, $alunno);
        //-- // legge le annotazioni
        //-- $dati['annotazioni'] = $this->annotazioniGenitore($oggi, $alunno->getClasse());
        // legge avvisi
        $dati['avvisi'] = $this->numeroAvvisiGenitori($utente, $alunno);
        // legge verifiche
        $dati['verifiche'] = $this->numeroVerificheGenitori($alunno);
      }
    } elseif ($utente instanceof Docente) {
      // notifiche per i docenti
      $richieste = $this->em->getRepository('AppBundle:RichiestaColloquio')->colloquiDocente($utente, ['R']);
      $dati['richieste'] = count($richieste);
      $dati['colloqui'] = $this->colloquiDocente($oggi, $ora, $utente);
      // legge avvisi
      $dati['avvisi'] = $this->numeroAvvisi($utente);
      // legge verifiche
      $dati['verifiche'] = $this->numeroVerifiche($utente);
    }
    return $dati;
  }

  /**
   * Ricava alcune informazioni sull'utente e le memorizza nella sessione
   *
   * @param Utente $utente Utente selezionato
   */
  public function infoUtente(Utente $utente) {
    if ($utente instanceof Docente) {
      // coordinatore
      $classi = $this->em->getRepository('AppBundle:Classe')->createQueryBuilder('c')
        ->select('c.id')
        ->where('c.coordinatore=:docente')
        ->setParameters(['docente' => $utente])
        ->getQuery()
        ->getArrayResult();
      $lista = implode(',', array_column($classi, 'id'));
      $this->session->set('/APP/DOCENTE/coordinatore', $lista);
    }
  }

  /**
   * Restituisce le annotazioni dalla data indicata in poi, relative alla classe indicata
   *
   * @param \DateTime $data Data del giorno di lezione
   * @param Classe $classe Classe della lezione
   *
   * @return array Dati restituiti come array associativo
   */
  public function annotazioniGenitore(\DateTime $data, Classe $classe) {
    // legge annotazioni
    $annotazioni = $this->em->getRepository('AppBundle:Annotazione')->createQueryBuilder('a')
      ->select('a.data,a.testo')
      ->where('a.data>=:data AND a.classe=:classe AND a.visibile=:visibile')
      ->orderBy('a.data', 'ASC')
      ->setParameters(['data' => $data->format('Y-m-d'), 'classe' => $classe, 'visibile' => 1])
      ->getQuery()
      ->getArrayResult();
    // restituisce dati
    return $annotazioni;
  }

  /**
   * Restituisce gli appuntamenti confermati per i colloqui
   *
   * @param \DateTime $data Data del giorno iniziale
   * @param \DateTime $ora Ora iniziale
   * @param Alunno $alunno Alunno su cui fare i colloqui
   *
   * @return array Dati restituiti come array associativo
   */
  public function colloquiGenitore(\DateTime $data, \DateTime $ora, Alunno $alunno) {
    $settimana = ['Domenica', 'Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato'];
    $mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    $dati = null;
    // legge colloqui esistenti
    $colloqui = $this->em->getRepository('AppBundle:RichiestaColloquio')->createQueryBuilder('rc')
      ->select('rc.data,rc.stato,rc.messaggio,c.giorno,so.inizio,so.fine,d.cognome,d.nome,d.sesso')
      ->join('rc.colloquio', 'c')
      ->join('c.docente', 'd')
      ->join('c.orario', 'o')
      ->join('AppBundle:ScansioneOraria', 'so', 'WHERE', 'so.orario=o.id AND so.giorno=c.giorno AND so.ora=c.ora')
      ->where('rc.alunno=:alunno AND rc.data>=:data AND rc.stato=:stato')
      ->orderBy('rc.data,c.ora', 'ASC')
      ->setParameters(['alunno' => $alunno, 'data' => $data->format('Y-m-d'), 'stato' => 'C'])
      ->getQuery()
      ->getArrayResult();
    foreach ($colloqui as $c) {
      if ($c['data'] > $data || $c['fine'] <= $ora) {
        $c['data_str'] = $settimana[$c['giorno']].' '.intval($c['data']->format('d')).' '.
          $mesi[intval($c['data']->format('m'))].' '.$c['data']->format('Y');
        $c['ora_str'] = 'dalle '.$c['inizio']->format('G:i').' alle '.$c['fine']->format('G:i');
        $dati[] = $c;
      }
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Restituisce gli appuntamenti confermati per i colloqui
   *
   * @param \DateTime $data Data del giorno iniziale
   * @param \DateTime $ora Ora iniziale
   * @param Docente $docente Docente che deve fare i colloqui
   *
   * @return array Dati restituiti come array associativo
   */
  public function colloquiDocente(\DateTime $data, \DateTime $ora, Docente $docente) {
    $settimana = ['Domenica', 'Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato'];
    $mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    $dati = null;
    // legge colloqui confermati
    $colloqui = $this->em->getRepository('AppBundle:RichiestaColloquio')->colloquiDocente($docente, ['C'], $data, $ora);
    foreach ($colloqui as $c) {
      $c['data_str'] = $settimana[$c['giorno']].' '.intval($c['data']->format('d')).' '.
        $mesi[intval($c['data']->format('m'))].' '.$c['data']->format('Y');
      $c['ora_str'] = 'dalle '.$c['inizio']->format('G:i').' alle '.$c['fine']->format('G:i');
      $dati[] = $c;
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Controlla la presenza di nuovi avvisi destinati al docente indicato
   *
   * @param Docente $docente Docente a cui sono indirizzati gli avvisi
   *
   * @return int Numero di avvisi da leggere
   */
  public function numeroAvvisi(Docente $docente) {
    $ultimo_accesso = \DateTime::createFromFormat('d/m/Y H:i:s',
      ($this->session->get('/APP/UTENTE/ultimo_accesso') ? $this->session->get('/APP/UTENTE/ultimo_accesso') : '01/01/2018 00:00:00'));
    // conta nuovi avvisi (successivi ultimo accesso)
    $avvisi = $this->em->getRepository('AppBundle:Avviso')->createQueryBuilder('a')
      ->select('COUNT(DISTINCT a)')
      ->leftJoin('AppBundle:AvvisoClasse', 'avc', 'WHERE', 'avc.avviso=a.id')
      ->leftJoin('avc.classe', 'cl')
      ->leftJoin('AppBundle:Cattedra', 'c', 'WHERE', 'c.classe=cl.id AND c.docente=:docente AND c.attiva=:attiva')
      //-- ->leftJoin('AppBundle:Staff', 'st', 'WHERE', 'st.id=:docente')
      //-- ->leftJoin('AppBundle:AvvisoSede', 'avs', 'WHERE', 'avs.avviso=a.id')
      ->where('(a.destinatariDocenti=:destinatario AND c.id IS NOT NULL AND a.modificato>=:ultimo_accesso) OR '.
        '(a.destinatariCoordinatori=:destinatario AND cl.coordinatore=:docente AND avc.lettoCoordinatore IS NULL)')
        //-- '(a.destinatariStaff=:destinatario AND st.id IS NOT NULL AND a.modificato>=:ultimo_accesso AND (st.sede IS NULL OR st.sede=avs.sede))')
      ->setParameters(['docente' => $docente, 'attiva' => 1, 'destinatario' => 1,
        'ultimo_accesso' => $ultimo_accesso->format('Y-m-d H:i:s')])
      ->getQuery()
      ->getSingleScalarResult();
    // restituisce numero avvisi
    return $avvisi;
  }

  /**
   * Controlla la presenza di nuovi avvisi destinati al genitore indicato
   *
   * @param Genitore $genitore Genitore a cui sono indirizzati gli avvisi
   * @param Alunno $alunno Alunno del genitore a cui sono indirizzati gli avvisi
   *
   * @return int Numero di avvisi da leggere
   */
  public function numeroAvvisiGenitori(Genitore $genitore, Alunno $alunno) {
    $ultimo_accesso = \DateTime::createFromFormat('d/m/Y H:i:s',
      ($this->session->get('/APP/UTENTE/ultimo_accesso') ? $this->session->get('/APP/UTENTE/ultimo_accesso') : '01/01/2018 00:00:00'));
    // lista nuovi avvisi (successivi ultimo accesso o non letti)
    $avvisi = $this->em->getRepository('AppBundle:Avviso')->createQueryBuilder('a')
      ->select('COUNT(DISTINCT a)')
      ->leftJoin('AppBundle:AvvisoClasse', 'avc', 'WHERE', 'avc.avviso=a.id')
      ->leftJoin('avc.classe', 'cl')
      ->leftJoin('AppBundle:AvvisoIndividuale', 'avi', 'WHERE', 'avi.avviso=a.id')
      ->leftJoin('avi.genitore', 'g')
      ->leftJoin('avi.alunno', 'al')
      ->where('(a.destinatariGenitori=:destinatario AND a.modificato>=:ultimo_accesso AND cl.id IS NOT NULL AND cl.id=:classe) OR '.
              '(a.destinatariIndividuali=:destinatario AND g.id=:genitore AND al.id=:alunno AND avi.letto IS NULL)')
      ->setParameters(['destinatario' => 1, 'ultimo_accesso' => $ultimo_accesso->format('Y-m-d H:i:s'),
        'classe' => $alunno->getClasse(), 'genitore' => $genitore, 'alunno' => $alunno])
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
    if ($ora->format('H:i') < $this->session->get('/CONFIG/SCUOLA/lezioni_fine', '23:59')) {
      $dati['oggi'] = $this->em->getRepository('AppBundle:Avviso')->createQueryBuilder('a')
        ->select('COUNT(DISTINCT a)')
        ->where('a.tipo=:tipo AND a.docente=:docente AND a.data=:oggi')
        ->setParameters(['tipo' => 'V', 'docente' => $docente, 'oggi' => $ora->format('Y-m-d')])
        ->getQuery()
        ->getSingleScalarResult();
    }
    // conta prossime verifiche
    $inizio = clone $ora;
    $inizio->modify('+1 day');
    $fine = clone $inizio;
    $fine->modify('+1 days');
    $dati['prossime'] = $this->em->getRepository('AppBundle:Avviso')->createQueryBuilder('a')
      ->select('COUNT(DISTINCT a)')
      ->where('a.tipo=:tipo AND a.docente=:docente AND a.data BETWEEN :inizio AND :fine')
      ->setParameters(['tipo' => 'V', 'docente' => $docente, 'inizio' => $inizio->format('Y-m-d'),
        'fine' => $fine->format('Y-m-d')])
      ->getQuery()
      ->getSingleScalarResult();
    // restituisce dati
    return $dati;
  }

  /**
   * Controlla la presenza di verifiche previste per l'alunno
   *
   * @param Genitore $genitore Genitore a cui sono indirizzati gli avvisi
   * @param Alunno $alunno Alunno del genitore a cui sono indirizzati gli avvisi
   *
   * @return int Numero di veriche previste
   */
  public function numeroVerificheGenitori(Alunno $alunno) {
    // conta verifiche di oggi
    $ora = new \DateTime();
    $dati['oggi'] = 0;
    if ($ora->format('H:i') < $this->session->get('/CONFIG/SCUOLA/lezioni_fine', '23:59')) {
      $dati['oggi'] = $this->em->getRepository('AppBundle:Avviso')->createQueryBuilder('a')
        ->select('COUNT(DISTINCT a)')
        ->join('a.cattedra', 'c')
        ->leftJoin('AppBundle:AvvisoIndividuale', 'avi', 'WHERE', 'avi.avviso=a.id')
        ->leftJoin('avi.alunno', 'al')
        ->where('a.tipo=:tipo AND a.data=:oggi AND c.classe=:classe')
        ->andWhere('a.destinatariIndividuali=:no_destinatario OR al.id=:alunno')
        ->setParameters(['tipo' => 'V', 'oggi' => $ora->format('Y-m-d'),
          'classe' => $alunno->getClasse(), 'no_destinatario' => 0, 'alunno' => $alunno])
        ->getQuery()
        ->getSingleScalarResult();
    }
    // conta prossime verifiche
    $inizio = clone $ora;
    $inizio->modify('+1 day');
    $fine = clone $inizio;
    $fine->modify('+1 days');
    $dati['prossime'] = $this->em->getRepository('AppBundle:Avviso')->createQueryBuilder('a')
      ->select('COUNT(DISTINCT a)')
      ->join('a.cattedra', 'c')
      ->leftJoin('AppBundle:AvvisoIndividuale', 'avi', 'WHERE', 'avi.avviso=a.id')
      ->leftJoin('avi.alunno', 'al')
      ->where('a.tipo=:tipo AND a.data BETWEEN :inizio AND :fine AND c.classe=:classe')
      ->andWhere('a.destinatariIndividuali=:no_destinatario OR al.id=:alunno')
      ->setParameters(['tipo' => 'V', 'inizio' => $inizio->format('Y-m-d'), 'fine' => $fine->format('Y-m-d'),
        'classe' => $alunno->getClasse(), 'no_destinatario' => 0, 'alunno' => $alunno])
      ->getQuery()
      ->getSingleScalarResult();
    // restituisce dati
    return $dati;
  }

}

