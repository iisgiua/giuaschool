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

use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Translation\TranslatorInterface;
use App\Entity\Utente;
use App\Entity\Docente;
use App\Entity\Genitore;
use App\Entity\Alunno;
use App\Entity\Staff;
use App\Entity\Avviso;
use App\Entity\AvvisoSede;
use App\Entity\AvvisoClasse;
use App\Entity\AvvisoIndividuale;
use App\Entity\Annotazione;
use App\Entity\Classe;


/**
 * BachecaUtil - classe di utilità per le funzioni di gestione della bacheca
 */
class BachecaUtil {


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


  //==================== METODI DELLA CLASSE ====================

  /**
   * Construttore
   *
   * @param RouterInterface $router Gestore delle URL
   * @param EntityManagerInterface $em Gestore delle entità
   * @param TranslatorInterface $trans Gestore delle traduzioni
   */
  public function __construct(RouterInterface $router, EntityManagerInterface $em, TranslatorInterface $trans) {
    $this->router = $router;
    $this->em = $em;
    $this->trans = $trans;
  }

  /**
   * Restituisce i destinatari indicati nei filtri dell'avviso
   *
   * @param Avviso $avviso Avviso da leggere
   *
   * @return array Dati come array associativo
   */
  public function filtriAvviso(Avviso $avviso) {
    $dati = array();
    // legge sedi
    $dati['sedi'] = $this->em->getRepository('App:AvvisoSede')->createQueryBuilder('avs')
      ->select('(avs.sede) AS sede,s.citta,avs.id')
      ->join('avs.sede', 's')
      ->where('avs.avviso=:avviso')
      ->setParameters(['avviso' => $avviso->getId()])
      ->orderBy('s.principale', 'DESC')
      ->addOrderBy('s.citta', 'ASC')
      ->getQuery()
      ->getArrayResult();
    // legge classi
    $dati['classi'] = $this->em->getRepository('App:AvvisoClasse')->createQueryBuilder('avc')
      ->select('(avc.classe) AS classe,c.anno,c.sezione,avc.lettoAlunni,avc.lettoCoordinatore,avc.id')
      ->join('avc.classe', 'c')
      ->where('avc.avviso=:avviso')
      ->setParameters(['avviso' => $avviso->getId()])
      ->orderBy('c.anno,c.sezione', 'ASC')
      ->getQuery()
      ->getArrayResult();
    // legge genitori
    $dati['utenti'] = $this->em->getRepository('App:AvvisoIndividuale')->createQueryBuilder('avi')
      ->select('(avi.genitore) AS genitore,(avi.alunno) AS alunno,a.cognome,a.nome,c.anno,c.sezione,avi.letto,avi.id')
      ->join('avi.alunno', 'a')
      ->join('a.classe', 'c')
      ->where('avi.avviso=:avviso')
      ->setParameters(['avviso' => $avviso->getId()])
      ->orderBy('a.cognome,a.nome', 'ASC')
      ->getQuery()
      ->getArrayResult();
    // restituisce dati
    return $dati;
  }

  /**
   * Controlla se è possibile eseguire l'azione specificata relativamente agli avvisi.
   *
   * @param string $azione Azione da controllare
   * @param \DateTime $data Data dell'evento
   * @param Docente $docente Docente che esegue l'azione
   * @param Avviso $avviso Avviso su cui eseguire l'azione
   *
   * @return bool Restituisce vero se l'azione è permessa
   */
  public function azioneAvviso($azione, \DateTime $data, Docente $docente, Avviso $avviso=null) {
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
          if (in_array('ROLE_STAFF', $avviso->getDocente()->getRoles()) && in_array('ROLE_STAFF', $docente->getRoles())) {
            // docente è dello staff come anche chi ha scritto avviso: ok
            return true;
          }
        }
      }
    } elseif ($azione == 'delete') {
      // azione di cancellazione
      if ($avviso) {
        // esiste annotazione
        if ($docente->getId() == $avviso->getDocente()->getId()) {
          // stesso docente: ok
          return true;
        }
        if (in_array('ROLE_STAFF', $avviso->getDocente()->getRoles()) && in_array('ROLE_STAFF', $docente->getRoles())) {
          // docente è dello staff come anche chi ha scritto avviso: ok
          return true;
        }
      }
    }
    // non consentito
    return false;
  }

  /**
   * Modifica i destinatari per un avviso esistente
   *
   * @param Avviso $avviso Avviso da leggere
   * @param array $avviso_destinatari Lista dei destinatari esistenti
   * @param string $staff_filtro Filtro per lo staff [N=nessuno,T=tutti,S=sede]
   * @param array $staff_sedi Lista di ID di sede
   * @param array $destinatari Lista di destinatari [C=coordinatori,D=docenti,G=genitori,A=alunni]
   * @param string $filtro Filtro per i destinatari [N=nessuno,T=tutti,S=sede,C=classe,I=individuale (solo genitori)]
   * @param array $filtro_id Lista di ID del tipo indicato nel filtro
   *
   * @return array Destinatari modificati, come array associativo
   */
  public function modificaFiltriAvviso(Avviso $avviso, $avviso_destinatari, $staff_filtro, $staff_sedi, $destinatari,
                                        $filtro, $filtro_id) {
    $modifiche = array();
    $modifiche['sedi']['add'] = [];
    $modifiche['classi']['add'] = [];
    $modifiche['utenti']['add'] = [];
    $modifiche['sedi']['delete'] = [];
    $modifiche['classi']['delete'] = [];
    $modifiche['utenti']['delete'] = [];
    // destinatari staff
    $avviso->setDestinatariStaff($staff_filtro != 'N');
    // sedi per destinari staff
    $sedi = array();
    if ($staff_filtro == 'T') {
      $sedi = $this->em->getRepository('App:Sede')->createQueryBuilder('s')
        ->getQuery()
        ->getResult();
    } elseif ($staff_filtro == 'S') {
      $sedi = $this->em->getRepository('App:Sede')->createQueryBuilder('s')
        ->where('s.id IN (:sedi)')
        ->setParameter('sedi', $staff_sedi)
        ->getQuery()
        ->getResult();
    }
    foreach ($sedi as $s) {
      $cnt = count($avviso_destinatari['sedi']);
      // elimina sede se esiste già
      $avviso_destinatari['sedi'] = array_filter($avviso_destinatari['sedi'], function ($e) use ($s) {
        return ($e['sede'] != $s->getId()); });
      if ($cnt == count($avviso_destinatari['sedi'])) {
        // nuova sede
        $as = (new AvvisoSede())
          ->setAvviso($avviso)
          ->setSede($s);
        $this->em->persist($as);
        $modifiche['sedi']['add'][] = $s->getId();
      }
    }
    // altri destinatari
    $avviso->setDestinatariCoordinatori(in_array('C', $destinatari));
    $avviso->setDestinatariDocenti(in_array('D', $destinatari));
    $avviso->setDestinatariGenitori(in_array('G', $destinatari));
    $avviso->setDestinatariAlunni(in_array('A', $destinatari));
    $avviso->setDestinatariIndividuali($filtro == 'I' && $avviso->getDestinatariGenitori());
    // classi per altri destinatari
    $classi = array();
    if ($filtro == 'T') {
      $classi = $this->em->getRepository('App:Classe')->createQueryBuilder('c')
        ->getQuery()
        ->getResult();
    } elseif ($filtro == 'S') {
      $classi = $this->em->getRepository('App:Classe')->createQueryBuilder('c')
        ->where('c.sede IN (:sedi)')
        ->setParameter('sedi', $filtro_id)
        ->getQuery()
        ->getResult();
    } elseif ($filtro == 'C') {
      $classi = $this->em->getRepository('App:Classe')->createQueryBuilder('c')
        ->where('c.id IN (:classi)')
        ->setParameter('classi', $filtro_id)
        ->getQuery()
        ->getResult();
    }
    foreach ($classi as $c) {
      $cnt = count($avviso_destinatari['classi']);
      // elimina classe se esiste già
      $avviso_destinatari['classi'] = array_filter($avviso_destinatari['classi'], function ($e) use ($c) {
        return ($e['classe'] != $c->getId()); });
      if ($cnt == count($avviso_destinatari['classi'])) {
        // nuova classe
        $ac = (new AvvisoClasse())
          ->setAvviso($avviso)
          ->setClasse($c);
        $this->em->persist($ac);
        $modifiche['classi']['add'][] = $c->getId();
      }
    }
    // destinatario individuale per genitori
    if ($avviso->getDestinatariIndividuali()) {
      $genitori = $this->em->getRepository('App:Genitore')->createQueryBuilder('g')
        ->where('g.alunno IN (:alunni)')
        ->setParameter('alunni', $filtro_id)
        ->getQuery()
        ->getResult();
      foreach ($genitori as $g) {
        $cnt = count($avviso_destinatari['utenti']);
        // elimina utente se esiste già
        $avviso_destinatari['utenti'] = array_filter($avviso_destinatari['utenti'], function ($e) use ($g) {
          return ($e['genitore'] != $g->getId() || $e['alunno'] != $g->getAlunno()->getId()); });
        if ($cnt == count($avviso_destinatari['utenti'])) {
          // nuovo utente
          $ai = (new AvvisoIndividuale())
            ->setAvviso($avviso)
            ->setGenitore($g)
            ->setAlunno($g->getAlunno());
          $this->em->persist($ai);
          $modifiche['utenti']['add'][] = ['genitore' => $g->getId(), 'alunno' => $g->getAlunno()->getId()];
        }
      }
    }
    // cancella sedi
    foreach ($avviso_destinatari['sedi'] as $s) {
      $as = $this->em->getRepository('App:AvvisoSede')->find($s['id']);
      if ($as) {
        $modifiche['sedi']['delete'][] = $s['sede'];
        $this->em->remove($as);
      }
    }
    // cancella classi
    foreach ($avviso_destinatari['classi'] as $c) {
      $ac = $this->em->getRepository('App:AvvisoClasse')->find($c['id']);
      if ($ac) {
        $modifiche['classi']['delete'][] = $c['classe'];
        $this->em->remove($ac);
      }
    }
    // cancella utenti
    foreach ($avviso_destinatari['utenti'] as $u) {
      $ai = $this->em->getRepository('App:AvvisoIndividuale')->find($u['id']);
      if ($ai) {
        $modifiche['utenti']['delete'][] = ['genitore' => $u['genitore'], 'alunno' => $u['alunno']];
        $this->em->remove($ai);
      }
    }
    // restituisce le modifiche
    return $modifiche;
  }

  /**
   * Crea l'annotazione sul registro in base ai dati dell'avviso
   *
   * @param Avviso $avviso Avviso di cui recuperare i dati
   * @param string $filtro Filtro per i destinatari [N=nessuno,T=tutti,S=sede,C=classe,I=individuale (solo genitori)]
   * @param array $filtro_id Lista di ID del tipo indicato nel filtro
   * @param int $filtro_id_classe ID della classe per il filtro individuale
   */
  public function creaAnnotazione(Avviso $avviso, $filtro, $filtro_id, $filtro_id_classe) {
    // legge classi
    switch ($filtro) {
      case 'T':
        // tutte
        $classi = $this->em->getRepository('App:Classe')->createQueryBuilder('c')
          ->getQuery()
          ->getResult();
        break;
      case 'S':
        // filtro sede
        $classi = $this->em->getRepository('App:Classe')->createQueryBuilder('c')
          ->where('c.sede IN (:sedi)')
          ->setParameter('sedi', $filtro_id)
          ->getQuery()
          ->getResult();
        break;
      case 'C':
        // filtro classi
        $classi = $this->em->getRepository('App:Classe')->createQueryBuilder('c')
          ->where('c.id IN (:classi)')
          ->setParameter('classi', $filtro_id)
          ->getQuery()
          ->getResult();
        break;
      case 'I':
        // filtro individuale
        $classi = $this->em->getRepository('App:Classe')->createQueryBuilder('c')
          ->where('c.id=:classe')
          ->setParameter('classe', $filtro_id_classe)
          ->getQuery()
          ->getResult();
        break;
    }
    // crea annotazioni
    $testo = $this->testoAvviso($avviso);
    foreach ($classi as $c) {
      $a = (new Annotazione())
        ->setData($avviso->getData())
        ->setTesto($testo)
        ->setVisibile(false)
        ->setAvviso($avviso)
        ->setClasse($c)
        ->setDocente($avviso->getDocente());
      $this->em->persist($a);
      $avviso->addAnnotazione($a);
    }
  }

  /**
   * Restituisce il testo da mostrare dell'avviso, valorizzando i campi presenti [%DATA%,%ORA%,%INIZIO%,%FINE%]
   *
   * @param Avviso $avviso Avviso da leggere
   *
   * @return string Testo dell'avviso
   */
  public function testoAvviso(Avviso $avviso) {
    $testo = $avviso->getTesto();
    $data = $avviso->getData()->format('d/m/Y');
    $ora1 = ($avviso->getOra() ? $avviso->getOra()->format('G:i') : '');
    $ora2 = ($avviso->getOraFine() ? $avviso->getOraFine()->format('G:i') : '');
    $testo = str_replace(['%DATA%', '%ORA%', '%INIZIO%', '%FINE%'], [$data, $ora1, $ora1, $ora2], $testo);
    // restituisce il testo
    return $testo;
  }

  /**
   * Restituisce gli avvisi secondo i criteri di ricerca inseriti.
   *
   * @param array $ricerca Criteri di ricerca
   * @param int $pagina Pagina corrente
   * @param int $limite Numero di elementi per pagina
   * @param Docente $docente Docente che richiede i dati
   * @param string $tipo Tipo di avviso
   *
   * @return Array Dati formattati come array associativo
   */
  public function listaAvvisi($ricerca, $pagina, $limite, Docente $docente, $tipo) {
    $dati = array();
    // legge avvisi
    $avvisi = $this->em->getRepository('App:Avviso')->createQueryBuilder('a')
      ->where('a.tipo=:tipo');
    if ($ricerca['docente']) {
      $avvisi = $avvisi->andWhere('a.docente=:docente')->setParameter('docente', $ricerca['docente']);
    }
    if (isset($ricerca['destinatari'])) {
      if ($ricerca['destinatari'] == 'S') {
        $avvisi = $avvisi->andWhere('a.destinatariStaff=:destinatari')->setParameter('destinatari', 1);
      } elseif ($ricerca['destinatari'] == 'C') {
        $avvisi = $avvisi->andWhere('a.destinatariCoordinatori=:destinatari')->setParameter('destinatari', 1);
      } elseif ($ricerca['destinatari'] == 'D') {
        $avvisi = $avvisi->andWhere('a.destinatariDocenti=:destinatari')->setParameter('destinatari', 1);
      } elseif ($ricerca['destinatari'] == 'G') {
        $avvisi = $avvisi->andWhere('a.destinatariGenitori=:destinatari')->setParameter('destinatari', 1);
      } elseif ($ricerca['destinatari'] == 'A') {
        $avvisi = $avvisi->andWhere('a.destinatariAlunni=:destinatari')->setParameter('destinatari', 1);
      }
    }
    if (isset($ricerca['classe']) && $ricerca['classe']) {
      $avvisi = $avvisi
        ->join('App:AvvisoClasse', 'ac', 'WITH', 'a.id=ac.avviso')
        ->andWhere('ac.classe=:classe')
        ->setParameter('classe', $ricerca['classe']);
    }
    if (isset($ricerca['classe_individuale']) && $ricerca['classe_individuale']) {
      $avvisi = $avvisi
        ->join('App:AvvisoIndividuale', 'ai', 'WITH', 'a.id=ai.avviso')
        ->join('ai.alunno', 'al')
        ->andWhere('al.classe=:classe')
        ->setParameter('classe', $ricerca['classe_individuale']);
    }
    $avvisi = $avvisi
      ->orderBy('a.data', 'DESC')
      ->addOrderBy('a.ora', 'ASC')
      ->setParameter('tipo', $tipo)
      ->getQuery();
    // paginazione
    $paginator = new Paginator($avvisi);
    $paginator->getQuery()
      ->setFirstResult($limite * ($pagina - 1))
      ->setMaxResults($limite);
    $dati['lista'] = $paginator;
    // controllo azioni e aggiunta info
    foreach ($dati['lista'] as $k=>$a) {
      // edit
      if ($this->azioneAvviso('edit', $a->getData(), $docente, $a)) {
        // pulsante edit
        $dati['azioni'][$k]['edit'] = 1;
      }
      // delete
      if ($this->azioneAvviso('delete', $a->getData(), $docente, $a)) {
        // pulsante delete
        $dati['azioni'][$k]['delete'] = 1;
      }
      if ($tipo == 'C') {
        // legge sedi di staff
        $dati['sedi'][$a->getId()] = $this->em->getRepository('App:AvvisoSede')->createQueryBuilder('avs')
          ->select('s.citta')
          ->join('avs.sede', 's')
          ->where('avs.avviso=:avviso')
          ->orderBy('s.principale', 'DESC')
          ->addOrderBy('s.citta', 'ASC')
          ->setParameter('avviso', $a->getId())
          ->getQuery()
          ->getArrayResult();
      } elseif ($tipo == 'I') {
        // legge classe di avviso individuale
        $dati['classe_individuale'][$a->getId()] = $this->em->getRepository('App:AvvisoIndividuale')->createQueryBuilder('avi')
          ->select('DISTINCT c.anno,c.sezione')
          ->join('avi.alunno', 'a')
          ->join('a.classe', 'c')
          ->where('avi.avviso=:avviso')
          ->setParameter('avviso', $a->getId())
          ->getQuery()
          ->getArrayResult();
      } else {
        // legge classi
        $dati['classi'][$a->getId()] = $this->em->getRepository('App:AvvisoClasse')->createQueryBuilder('avc')
          ->select('c.anno,c.sezione')
          ->join('avc.classe', 'c')
          ->where('avc.avviso=:avviso')
          ->orderBy('c.anno,c.sezione', 'ASC')
          ->setParameter('avviso', $a->getId())
          ->getQuery()
          ->getArrayResult();
      }
    }
    // add
    if ($this->azioneAvviso('add', new \DateTime(), $docente, null)) {
      // pulsante add
      $dati['azioni']['add'] = 1;
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Restituisce i dettagli dell'avviso
   *
   * @param Avviso $avviso Avviso di cui recuperare i dati
   *
   * @return Array Dati formattati come array associativo
   */
  public function dettagliAvviso(Avviso $avviso) {
    $dati = array();
    // destinatari
    $dati = $this->filtriAvviso($avviso);
    // dati avviso
    $dati['avviso'] = $avviso;
    $dati['testo'] = $this->testoAvviso($avviso);
    // restituisce dati
    return $dati;
  }

  /**
   * Controlla se l'utente è autorizzato alla lettura dell'avviso
   *
   * @param Avviso $avviso Avviso da leggere
   * @param Utente $utente Utente da controllare
   *
   * @return boolean Restituisce True se l'utente è autorizzato alla lettura, False altrimenti
   */
  public function permessoLettura(Avviso $avviso, Utente $utente) {
    $letto = null;
    if ($this->destinatario($avviso, $utente, $letto)) {
      // è destinatario: ok
      return true;
    }
    if ($utente instanceOf Docente && $utente->getId() == $avviso->getDocente()->getId()) {
      // è autore: ok
      return true;
    }
    if ($utente instanceOf Staff) {
      // fa parte dello staff: ok
      return true;
    }
    // non è autorizzato
    return false;
  }

  /**
   * Controlla se l'utente è destinatario dell'avviso
   *
   * @param Avviso $avviso Avviso di cui recuperare i dati
   * @param Utente $utente Utente da controllare
   * @param DateTime|null $letto Data e ora di lettura, se presente
   *
   * @return boolean Restituisce True se l'utente risulta destinatario dell'avviso, False altrimenti
   */
  public function destinatario(Avviso $avviso, Utente $utente, &$letto) {
    $letto = null;
    if (($utente instanceOf Genitore) && $avviso->getDestinatariGenitori()) {
      // genitore
      if ($avviso->getDestinatariIndividuali()) {
        // filtro utente
        $avi = $this->em->getRepository('App:AvvisoIndividuale')->createQueryBuilder('avi')
          ->join('avi.genitore', 'g')
          ->join('avi.alunno', 'a')
          ->where('avi.avviso=:avviso AND g.id=:utente AND g.alunno=a.id')
          ->setParameters(['avviso' => $avviso, 'utente' => $utente])
          ->getQuery()
          ->getOneOrNullResult();
        $letto = ($avi ? $avi->getLetto() : null);
        return ($avi !== null);
      } else {
        // filtro classe
        $avc = $this->em->getRepository('App:AvvisoClasse')->createQueryBuilder('avc')
          ->join('avc.classe', 'c')
          ->join('App:Alunno', 'a', 'WITH', 'c.id=a.classe')
          ->join('App:Genitore', 'g', 'WITH', 'a.id=g.alunno')
          ->where('avc.avviso=:avviso AND g.id=:utente')
          ->setParameters(['avviso' => $avviso, 'utente' => $utente])
          ->getQuery()
          ->getOneOrNullResult();
        return ($avc !== null);
      }
    } elseif (($utente instanceOf Docente)) {
      // docente
      if ($avviso->getDestinatariDocenti()) {
        // filtro docenti
        $avc = $this->em->getRepository('App:AvvisoClasse')->createQueryBuilder('avc')
          ->join('avc.classe', 'cl')
          ->join('App:Cattedra', 'c', 'WITH', 'cl.id=c.classe')
          ->where('avc.avviso=:avviso AND c.docente=:utente AND c.attiva=:attiva')
          ->setParameters(['avviso' => $avviso, 'utente' => $utente, 'attiva' => 1])
          ->setMaxResults(1)
          ->getQuery()
          ->getOneOrNullResult();
        if ($avc !== null) {
          // ok, destinatario docente
          return true;
        }
      }
      if ($avviso->getDestinatariCoordinatori()) {
        // filtro coordinatori
        $avc = $this->em->getRepository('App:AvvisoClasse')->createQueryBuilder('avc')
          ->join('avc.classe', 'cl')
          ->where('avc.avviso=:avviso AND cl.coordinatore=:utente')
          ->setParameters(['avviso' => $avviso, 'utente' => $utente])
          ->setMaxResults(1)
          ->getQuery()
          ->getOneOrNullResult();
        $letto = ($avc ? $avc->getLettoCoordinatore() : null);
        if ($avc !== null) {
          // ok, destinatario coordinatore
          return true;
        }
      }
      if (($utente instanceOf Staff) && $avviso->getDestinatariStaff()) {
        // staff
        if ($utente->getSede() === null) {
          // ok, destinatario staff (qualsiasi sede)
          return true;
        }
        $avs = $this->em->getRepository('App:AvvisoSede')->createQueryBuilder('avs')
          ->join('avs.sede', 's')
          ->join('App:Staff', 'st', 'WITH', 's.id=st.sede')
          ->where('avs.avviso=:avviso AND st.id=:utente')
          ->setParameters(['avviso' => $avviso, 'utente' => $utente])
          ->getQuery()
          ->getOneOrNullResult();
        if ($avs !== null) {
          // ok, destinatario staff
          return true;
        }
      }
    } elseif (($utente instanceOf Alunno) && $avviso->getDestinatariAlunni()) {
      // alunno
      $avc = $this->em->getRepository('App:AvvisoClasse')->createQueryBuilder('avc')
        ->join('avc.classe', 'c')
        ->join('App:Alunno', 'a', 'WITH', 'c.id=a.classe')
        ->where('avc.avviso=:avviso AND a.id=:utente')
        ->setParameters(['avviso' => $avviso, 'utente' => $utente])
        ->getQuery()
        ->getOneOrNullResult();
      return ($avc !== null);
    }
    // non è destinatario
    return false;
  }

  /**
   * Elimina i destinatari per un avviso esistente
   *
   * @param Avviso $avviso Avviso da leggere
   *
   * @return array Destinatari eliminati, come array associativo
   */
  public function eliminaFiltriAvviso(Avviso $avviso) {
    $modifiche = array();
    $modifiche['sedi'] = [];
    $modifiche['classi'] = [];
    $modifiche['utenti'] = [];
    // sedi
    $sedi = $this->em->getRepository('App:AvvisoSede')->createQueryBuilder('avs')
      ->join('avs.sede', 's')
      ->where('avs.avviso=:avviso')
      ->setParameters(['avviso' => $avviso->getId()])
      ->orderBy('s.principale', 'DESC')
      ->addOrderBy('s.citta', 'ASC')
      ->getQuery()
      ->getResult();
    foreach ($sedi as $s) {
      $modifiche['sedi'][] = $s->getSede()->getId();
      $this->em->remove($s);
    }
    // classi
    $classi = $this->em->getRepository('App:AvvisoClasse')->createQueryBuilder('avc')
      ->join('avc.classe', 'c')
      ->where('avc.avviso=:avviso')
      ->setParameters(['avviso' => $avviso->getId()])
      ->orderBy('c.anno,c.sezione', 'ASC')
      ->getQuery()
      ->getResult();
    foreach ($classi as $c) {
      $modifiche['classi'][] = $c->getClasse()->getId();
      $this->em->remove($c);
    }
    // utenti
    $utenti = $this->em->getRepository('App:AvvisoIndividuale')->createQueryBuilder('avi')
      ->join('avi.alunno', 'a')
      ->where('avi.avviso=:avviso')
      ->setParameters(['avviso' => $avviso->getId()])
      ->orderBy('a.cognome,a.nome', 'ASC')
      ->getQuery()
      ->getResult();
    foreach ($utenti as $u) {
      $modifiche['utenti'][] = ['genitore' => $u->getGenitore()->getId(), 'alunno' => $u->getAlunno()->getId()];
      $this->em->remove($u);
    }
    // restituisce le modifiche
    return $modifiche;
  }

  /**
   * Recupera gli avvisi destinati al docente indicato
   *
   * @param int $pagina Numero di pagina per l'elenco da visualizzare
   * @param int $limite Numero massimo di elementi per pagina
   * @param Docente $docente Docente a cui sono indirizzati gli avvisi
   * @param \DateTime $ultimo_accesso Ultimo accesso del docente al registro
   *
   * @return Array Dati formattati come array associativo
   */
  public function bachecaAvvisi($pagina, $limite, Docente $docente, \DateTime $ultimo_accesso) {
    // lista nuovi avvisi (successivi ultimo accesso)
    $nuovi = $this->em->getRepository('App:Avviso')->createQueryBuilder('a')
      ->leftJoin('App:AvvisoClasse', 'avc', 'WITH', 'avc.avviso=a.id')
      ->leftJoin('avc.classe', 'cl')
      ->leftJoin('App:Cattedra', 'c', 'WITH', 'c.classe=cl.id AND c.docente=:docente AND c.attiva=:attiva')
      //-- ->leftJoin('App:Staff', 'st', 'WITH', 'st.id=:docente')
      //-- ->leftJoin('App:AvvisoSede', 'avs', 'WITH', 'avs.avviso=a.id')
      ->where('(a.destinatariDocenti=:destinatario AND c.id IS NOT NULL AND a.modificato>=:ultimo_accesso) OR '.
        '(a.destinatariCoordinatori=:destinatario AND cl.coordinatore=:docente AND avc.lettoCoordinatore IS NULL)')
        //-- '(a.destinatariStaff=:destinatario AND st.id IS NOT NULL AND a.modificato>=:ultimo_accesso AND (st.sede IS NULL OR st.sede=avs.sede))')
      ->orderBy('a.data', 'DESC')
      ->setParameters(['docente' => $docente, 'attiva' => 1, 'destinatario' => 1,
        'ultimo_accesso' => $ultimo_accesso->format('Y-m-d H:i:s')])
      ->getQuery()
      ->getResult();
    $dati['nuovi'] = $nuovi;
    // aggiunta info
    foreach ($dati['nuovi'] as $k=>$a) {
      if ($a->getTipo() == 'C') {
        // legge sedi di staff
        $dati['sedi'][$a->getId()] = $this->em->getRepository('App:AvvisoSede')->createQueryBuilder('avs')
          ->select('s.citta')
          ->join('avs.sede', 's')
          ->where('avs.avviso=:avviso')
          ->orderBy('s.principale', 'DESC')
          ->addOrderBy('s.citta', 'ASC')
          ->setParameter('avviso', $a->getId())
          ->getQuery()
          ->getArrayResult();
      }
    }
    // lista avvisi (precedenti ultimo accesso)
    $avvisi = $this->em->getRepository('App:Avviso')->createQueryBuilder('a')
      ->leftJoin('App:AvvisoClasse', 'avc', 'WITH', 'avc.avviso=a.id')
      ->leftJoin('avc.classe', 'cl')
      ->leftJoin('App:Cattedra', 'c', 'WITH', 'c.classe=cl.id AND c.docente=:docente AND c.attiva=:attiva')
      //-- ->leftJoin('App:Staff', 'st', 'WITH', 'st.id=:docente')
      //-- ->leftJoin('App:AvvisoSede', 'avs', 'WITH', 'avs.avviso=a.id')
      ->where('(a.destinatariDocenti=:destinatario AND c.id IS NOT NULL AND a.modificato<:ultimo_accesso) OR '.
        '(a.destinatariCoordinatori=:destinatario AND cl.coordinatore=:docente AND avc.lettoCoordinatore IS NOT NULL)')
        //-- '(a.destinatariStaff=:destinatario AND st.id IS NOT NULL AND a.modificato<:ultimo_accesso AND (st.sede IS NULL OR st.sede=avs.sede))')
      ->orderBy('a.data', 'DESC')
      ->setParameters(['docente' => $docente, 'attiva' => 1, 'destinatario' => 1,
        'ultimo_accesso' => $ultimo_accesso->format('Y-m-d H:i:s')])
      ->getQuery();
    // paginazione
    $paginator = new Paginator($avvisi);
    $paginator->getQuery()
      ->setFirstResult($limite * ($pagina - 1))
      ->setMaxResults($limite);
    $dati['lista'] = $paginator;
    // aggiunta info
    foreach ($dati['lista'] as $k=>$a) {
      if ($a->getTipo() == 'C') {
        // legge sedi di staff
        $dati['sedi'][$a->getId()] = $this->em->getRepository('App:AvvisoSede')->createQueryBuilder('avs')
          ->select('s.citta')
          ->join('avs.sede', 's')
          ->where('avs.avviso=:avviso')
          ->orderBy('s.principale', 'DESC')
          ->addOrderBy('s.citta', 'ASC')
          ->setParameter('avviso', $a->getId())
          ->getQuery()
          ->getArrayResult();
      }
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Controlla la presenza di avvisi non letti destinati agli alunni della classe indicata
   *
   * @param Classe $classe Classe a cui sono indirizzati gli avvisi
   *
   * @return int Numero di avvisi da leggere
   */
  public function bachecaNumeroAvvisiAlunni(Classe $classe) {
    // lista avvisi non letti
    $avvisi = $this->em->getRepository('App:Avviso')->createQueryBuilder('a')
      ->select('COUNT(a)')
      ->join('App:AvvisoClasse', 'avc', 'WITH', 'avc.avviso=a.id')
      ->join('avc.classe', 'cl')
      ->where('a.destinatariAlunni=:destinatario AND cl.id=:classe AND avc.lettoAlunni IS NULL')
      ->setParameters(['destinatario' => 1, 'classe' => $classe])
      ->getQuery()
      ->getSingleScalarResult();
    // restituisce dati
    return $avvisi;
  }

  /**
   * Recupera gli avvisi (non letti) destinati agli alunni della classe indicata
   *
   * @param Classe $classe Classe a cui sono indirizzati gli avvisi
   *
   * @return Array Dati formattati come array associativo
   */
  public function bachecaAvvisiAlunni(Classe $classe) {
    // lista avvisi non letti
    $avvisi = $this->em->getRepository('App:Avviso')->createQueryBuilder('a')
      ->join('App:AvvisoClasse', 'avc', 'WITH', 'avc.avviso=a.id')
      ->join('avc.classe', 'cl')
      ->where('a.destinatariAlunni=:destinatario AND cl.id=:classe AND avc.lettoAlunni IS NULL')
      ->orderBy('a.data', 'ASC')
      ->setParameters(['destinatario' => 1, 'classe' => $classe])
      ->getQuery()
      ->getResult();
    $dati['lista'] = $avvisi;
    // aggiunge info
    foreach ($dati['lista'] as $k=>$a) {
      // legge testo
      $dati['testo'][$a->getId()] = $this->testoAvviso($a);
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Segna come letti alla classe gli avvisi indicati
   *
   * @param Classe $classe Classe a cui sono indirizzati gli avvisi
   * @param mixed $id ID dell'avviso o "ALL" per tutti gli avvisi della classe
   */
  public function letturaAvvisoAlunni(Classe $classe, $id) {
    if ($id == 'ALL') {
      // tutti gli avvisi
      $avc = $this->em->getRepository('App:AvvisoClasse')->createQueryBuilder('avc')
        ->join('avc.avviso', 'a')
        ->join('avc.classe', 'cl')
        ->where('a.destinatariAlunni=:destinatario AND cl.id=:classe AND avc.lettoAlunni IS NULL')
        ->setParameters(['destinatario' => 1, 'classe' => $classe])
        ->getQuery()
        ->getResult();
    } elseif (intval($id) > 0) {
      // solo avviso indicato
      $avc = $this->em->getRepository('App:AvvisoClasse')->createQueryBuilder('avc')
        ->join('avc.avviso', 'a')
        ->join('avc.classe', 'cl')
        ->where('a.destinatariAlunni=:destinatario AND a.id=:avviso AND cl.id=:classe AND avc.lettoAlunni IS NULL')
        ->setParameters(['destinatario' => 1, 'avviso' => $id, 'classe' => $classe])
        ->getQuery()
        ->getResult();
    }
    // firma avvisi
    foreach ($avc as $av) {
      $av->setLettoAlunni(new \DateTime());
    }
  }

  /**
   * Recupera gli avvisi destinati al genitore indicato
   *
   * @param int $pagina Numero di pagina per l'elenco da visualizzare
   * @param int $limite Numero massimo di elementi per pagina
   * @param Genitore $genitore Genitore a cui sono indirizzati gli avvisi
   * @param Alunno $alunno Figlio del genitore a cui sono indirizzati gli avvisi
   * @param \DateTime $ultimo_accesso Ultimo accesso del docente al registro
   *
   * @return Array Dati formattati come array associativo
   */
  public function bachecaAvvisiGenitori($pagina, $limite, Genitore $genitore, Alunno $alunno, \DateTime $ultimo_accesso) {
    // lista nuovi avvisi (successivi ultimo accesso o non letti)
    $nuovi = $this->em->getRepository('App:Avviso')->createQueryBuilder('a')
      ->leftJoin('App:AvvisoClasse', 'avc', 'WITH', 'avc.avviso=a.id')
      ->leftJoin('avc.classe', 'cl')
      ->leftJoin('App:AvvisoIndividuale', 'avi', 'WITH', 'avi.avviso=a.id')
      ->leftJoin('avi.genitore', 'g')
      ->leftJoin('avi.alunno', 'al')
      ->where('(a.destinatariGenitori=:destinatario AND a.modificato>=:ultimo_accesso AND cl.id IS NOT NULL AND cl.id=:classe) OR '.
              '(a.destinatariGenitori=:destinatario AND a.destinatariIndividuali=:destinatario AND g.id=:genitore AND al.id=:alunno AND avi.letto IS NULL)')
      ->orderBy('a.data', 'DESC')
      ->setParameters(['destinatario' => 1, 'ultimo_accesso' => $ultimo_accesso->format('Y-m-d H:i:s'),
        'classe' => $alunno->getClasse(), 'genitore' => $genitore, 'alunno' => $alunno])
      ->getQuery()
      ->getResult();
    $dati['nuovi'] = $nuovi;
    // lista avvisi (precedenti ultimo accesso o letti)
    $avvisi = $this->em->getRepository('App:Avviso')->createQueryBuilder('a')
      ->leftJoin('App:AvvisoClasse', 'avc', 'WITH', 'avc.avviso=a.id')
      ->leftJoin('avc.classe', 'cl')
      ->leftJoin('App:AvvisoIndividuale', 'avi', 'WITH', 'avi.avviso=a.id')
      ->leftJoin('avi.genitore', 'g')
      ->leftJoin('avi.alunno', 'al')
      ->where('(a.destinatariGenitori=:destinatario AND a.modificato<:ultimo_accesso AND cl.id IS NOT NULL AND cl.id=:classe) OR '.
              '(a.destinatariGenitori=:destinatario AND a.destinatariIndividuali=:destinatario AND g.id=:genitore AND al.id=:alunno AND avi.letto IS NOT NULL)')
      ->orderBy('a.data', 'DESC')
      ->setParameters(['destinatario' => 1, 'ultimo_accesso' => $ultimo_accesso->format('Y-m-d H:i:s'),
        'classe' => $alunno->getClasse(), 'genitore' => $genitore, 'alunno' => $alunno])
      ->getQuery();
    // paginazione
    $paginator = new Paginator($avvisi);
    $paginator->getQuery()
      ->setFirstResult($limite * ($pagina - 1))
      ->setMaxResults($limite);
    $dati['lista'] = $paginator;
    // restituisce dati
    return $dati;
  }

  /**
   * Segna come letto dal genitore cui è indirizzato l'avviso indicato
   *
   * @param Avviso $avviso Avviso di cui segnare la lettura
   * @param Genitore $genitore Genitore destinatario dell'avviso
   */
  public function letturaAvvisoGenitori(Avviso $avviso, Genitore $genitore) {
    // solo avviso indicato
    $avi = $this->em->getRepository('App:AvvisoIndividuale')->createQueryBuilder('avi')
      ->where('avi.avviso=:avviso AND avi.genitore=:genitore AND avi.alunno=:alunno')
      ->setParameters(['avviso' => $avviso, 'genitore' => $genitore, 'alunno' => $genitore->getAlunno()])
      ->getQuery()
      ->getOneOrNullResult();
    // firma avviso
    if ($avi) {
      $avi->setLetto(new \DateTime());
    }
  }

  /**
   * Segna come letto dal coordinatore cui è indirizzato l'avviso indicato
   *
   * @param Avviso $avviso Avviso di cui segnare la lettura
   * @param Docente $docente Coordinatore destinatario dell'avviso
   */
  public function letturaAvvisoCoordinatori(Avviso $avviso, Docente $docente) {
    // solo avviso indicato
    $avc = $this->em->getRepository('App:AvvisoClasse')->createQueryBuilder('avc')
      ->join('avc.avviso', 'a')
      ->join('avc.classe', 'cl')
      ->where('a.id=:avviso AND a.destinatariCoordinatori=:destinatario AND cl.coordinatore=:docente AND avc.lettoCoordinatore IS NULL')
      ->setParameters(['avviso' => $avviso, 'destinatario' => 1, 'docente' => $docente])
      ->getQuery()
      ->getResult();
    // firma avviso
    foreach ($avc as $ac) {
      $ac->setLettoCoordinatore(new \DateTime());
    }
  }

  /**
   * Restituisce gli avvisi dei coordinatori
   *
   * @param int $pagina Pagina corrente
   * @param int $limite Numero di elementi per pagina
   * @param Docente $docente Docente coordinatore
   * @param Classe $classe Classe a cui è rivolto l'avviso
   *
   * @return Array Dati formattati come array associativo
   */
  public function listaAvvisiCoordinatore($pagina, $limite, Docente $docente, Classe $classe) {
    $dati = array();
    // legge avvisi
    $avvisi = $this->em->getRepository('App:Avviso')->createQueryBuilder('a')
      ->leftJoin('App:AvvisoClasse', 'ac', 'WITH', 'a.id=ac.avviso')
      ->leftJoin('App:AvvisoIndividuale', 'ai', 'WITH', 'a.id=ai.avviso')
      ->leftJoin('ai.alunno', 'al')
      ->where('a.tipo=:tipo')
      ->andWhere('(a.destinatariIndividuali=:no_indiv AND ac.classe=:classe) OR (a.destinatariIndividuali=:indiv AND al.classe=:classe)')
      ->setParameters(['tipo' => 'O', 'no_indiv' => 0, 'classe' => $classe, 'indiv' => 1])
      ->orderBy('a.data', 'DESC')
      ->getQuery();
    // paginazione
    $paginator = new Paginator($avvisi);
    $paginator->getQuery()
      ->setFirstResult($limite * ($pagina - 1))
      ->setMaxResults($limite);
    $dati['lista'] = $paginator;
    // controllo azioni e aggiunta info
    foreach ($dati['lista'] as $k=>$a) {
      // edit
      if ($this->azioneAvviso('edit', $a->getData(), $docente, $a)) {
        // pulsante edit
        $dati['azioni'][$k]['edit'] = 1;
      }
      // delete
      if ($this->azioneAvviso('delete', $a->getData(), $docente, $a)) {
        // pulsante delete
        $dati['azioni'][$k]['delete'] = 1;
      }
      if ($a->getDestinatariIndividuali()) {
        // legge destinatari individuali
        $dati['utenti'][$a->getId()] = $this->em->getRepository('App:AvvisoIndividuale')->createQueryBuilder('avi')
          ->select('DISTINCT a.cognome,a.nome')
          ->join('avi.alunno', 'a')
          ->where('avi.avviso=:avviso')
          ->orderBy('a.cognome,a.nome', 'ASC')
          ->setParameter('avviso', $a->getId())
          ->getQuery()
          ->getArrayResult();
      }
    }
    // add
    if ($this->azioneAvviso('add', new \DateTime(), $docente, null)) {
      // pulsante add
      $dati['azioni']['add'] = 1;
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Recupera gli avvisi destinati all'alunno indicato
   *
   * @param int $pagina Numero di pagina per l'elenco da visualizzare
   * @param int $limite Numero massimo di elementi per pagina
   * @param Alunno $alunno Alunno a cui sono indirizzati gli avvisi
   * @param \DateTime $ultimo_accesso Ultimo accesso del docente al registro
   *
   * @return Array Dati formattati come array associativo
   */
  public function bachecaAvvisiGenitoriAlunni($pagina, $limite, Alunno $alunno, \DateTime $ultimo_accesso) {
    // lista nuovi avvisi (successivi ultimo accesso o non letti)
    $nuovi = $this->em->getRepository('App:Avviso')->createQueryBuilder('a')
      ->join('App:AvvisoClasse', 'avc', 'WITH', 'avc.avviso=a.id')
      ->join('avc.classe', 'cl')
      ->where('a.destinatariAlunni=:destinatario AND a.modificato>=:ultimo_accesso AND cl.id=:classe')
      ->orderBy('a.data', 'DESC')
      ->setParameters(['destinatario' => 1, 'ultimo_accesso' => $ultimo_accesso->format('Y-m-d H:i:s'),
        'classe' => $alunno->getClasse()])
      ->getQuery()
      ->getResult();
    $dati['nuovi'] = $nuovi;
    // lista avvisi (precedenti ultimo accesso o letti)
    $avvisi = $this->em->getRepository('App:Avviso')->createQueryBuilder('a')
      ->join('App:AvvisoClasse', 'avc', 'WITH', 'avc.avviso=a.id')
      ->join('avc.classe', 'cl')
      ->where('a.destinatariAlunni=:destinatario AND a.modificato<:ultimo_accesso AND cl.id=:classe')
      ->orderBy('a.data', 'DESC')
      ->setParameters(['destinatario' => 1, 'ultimo_accesso' => $ultimo_accesso->format('Y-m-d H:i:s'),
        'classe' => $alunno->getClasse()])
      ->getQuery();
    // paginazione
    $paginator = new Paginator($avvisi);
    $paginator->getQuery()
      ->setFirstResult($limite * ($pagina - 1))
      ->setMaxResults($limite);
    $dati['lista'] = $paginator;
    // restituisce dati
    return $dati;
  }

}

