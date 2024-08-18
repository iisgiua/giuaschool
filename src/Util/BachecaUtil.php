<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Util;

use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;
use App\Entity\Utente;
use App\Entity\Ata;
use App\Entity\Docente;
use App\Entity\Genitore;
use App\Entity\Alunno;
use App\Entity\Staff;
use App\Entity\Avviso;
use App\Entity\AvvisoClasse;
use App\Entity\Annotazione;
use App\Entity\Classe;
use App\Entity\AvvisoUtente;
use App\Entity\Materia;
use App\Entity\Sede;

/**
 * BachecaUtil - classe di utilità per le funzioni di gestione della bacheca
 *
 * @author Antonello Dessì
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


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param RouterInterface $router Gestore delle URL
   * @param EntityManagerInterface $em Gestore delle entità
   */
  public function __construct(RouterInterface $router, EntityManagerInterface $em) {
    $this->router = $router;
    $this->em = $em;
  }

  /**
   * Restituisce le informazioni sui filtri dell'avviso
   *
   * @param Avviso $avviso Avviso da leggere
   *
   * @return array Dati come array associativo
   */
  public function filtriAvviso(Avviso $avviso) {
    $dati = array();
    $dati['sedi'] = [];
    $dati['classi'] = [];
    $dati['utenti'] = [];
    $dati['materie'] = [];
    // legge sedi
    $dati['sedi'] = $this->em->getRepository('App\Entity\Sede')->createQueryBuilder('s')
      ->select('s.citta')
      ->where('s.id IN (:lista)')
      ->setParameters(['lista' => array_map(function ($s) { return $s->getId(); }, $avviso->getSedi()->toArray())])
      ->orderBy('s.ordinamento', 'ASC')
      ->getQuery()
      ->getArrayResult();
    // legge filtri
    if ($avviso->getFiltroTipo() == 'C') {
      // filtro classi
      $dati['classi'] = $this->em->getRepository('App\Entity\Classe')->createQueryBuilder('c')
        ->select('c.anno,c.sezione,c.gruppo')
        ->where('c.id IN (:lista)')
        ->orderBy('c.anno,c.sezione,c.gruppo', 'ASC')
        ->setParameter('lista', $avviso->getFiltro())
        ->getQuery()
        ->getArrayResult();
    } elseif ($avviso->getFiltroTipo() == 'U') {
      // filtro utenti
      $dati['utenti'] = $this->em->getRepository('App\Entity\Alunno')->createQueryBuilder('a')
        ->select('DISTINCT(a.id),a.cognome,a.nome,a.dataNascita,c.anno,c.sezione,c.gruppo,aa.letto,ag1.letto AS letto_genitore1,ag2.letto AS letto_genitore2')
        ->join('a.classe', 'c')
        ->join('App\Entity\Genitore', 'g1', 'WITH', 'g1.alunno=a.id')
        ->leftJoin('App\Entity\Genitore', 'g2', 'WITH', 'g2.alunno=a.id AND g2.id!=g1.id')
        ->leftJoin('App\Entity\AvvisoUtente', 'aa', 'WITH', 'aa.utente=a.id AND aa.avviso=:avviso')
        ->leftJoin('App\Entity\AvvisoUtente', 'ag1', 'WITH', 'ag1.utente=g1.id AND ag1.avviso=:avviso')
        ->leftJoin('App\Entity\AvvisoUtente', 'ag2', 'WITH', 'ag2.utente=g2.id AND ag2.avviso=:avviso')
        ->where('a.id IN (:lista)')
        ->setParameters(['lista' => $avviso->getFiltro(), 'avviso' => $avviso])
        ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC')
        ->getQuery()
        ->getArrayResult();
    } elseif ($avviso->getFiltroTipo() == 'M') {
      // filtro materie
      $dati['materie'] = $this->em->getRepository('App\Entity\Materia')->createQueryBuilder('m')
        ->select('m.nome')
        ->where('m.id IN (:lista)')
        ->setParameters(['lista' => $avviso->getFiltro()])
        ->orderBy('m.nome', 'ASC')
        ->getQuery()
        ->getArrayResult();
    }
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
   * Crea l'annotazione sul registro in base ai dati dell'avviso
   *
   * @param Avviso $avviso Avviso di cui recuperare i dati
   * @param array $sedi Sedi di servizio (lista ID di Sede)
   */
  public function creaAnnotazione(Avviso $avviso, $sedi) {
    $classi = array();
    // legge classi
    if ($avviso->getFiltroTipo() == 'T') {
      // tutte le classi di sedi
      $classi = $this->em->getRepository('App\Entity\Classe')->getIdClasse($sedi, null);
    } elseif ($avviso->getFiltroTipo() == 'C') {
      // classi del filtro
      $classi = $this->em->getRepository('App\Entity\Classe')->getIdClasse($sedi, $avviso->getFiltro());
    } elseif ($avviso->getFiltroTipo() == 'U') {
      // classi di alunni/genitori
      $classi = $this->em->getRepository('App\Entity\Classe')->getIdClasseAlunni($sedi, $avviso->getFiltro());
    }
    // crea annotazioni
    $testo = $this->testoAvviso($avviso);
    foreach ($classi as $c) {
      $a = (new Annotazione())
        ->setData($avviso->getData())
        ->setTesto($testo)
        ->setVisibile(false)
        ->setAvviso($avviso)
        ->setClasse($this->em->getReference('App\Entity\Classe', $c))
        ->setDocente($avviso->getDocente());
      $this->em->persist($a);
      $avviso->addAnnotazioni($a);
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
    $testo = str_replace(['{DATA}', '{ORA}', '{INIZIO}', '{FINE}'], [$data, $ora1, $ora1, $ora2], $testo);
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
   * @return array Dati formattati come array associativo
   */
  public function listaAvvisi($ricerca, $pagina, $limite, Docente $docente, $tipo) {
    $dati = array();
    // legge avvisi (solo anno corrente)
    $avvisi = $this->em->getRepository('App\Entity\Avviso')->createQueryBuilder('a')
      ->where('a.tipo=:tipo AND a.anno=0');
    if ($ricerca['docente']) {
      $avvisi = $avvisi->andWhere('a.docente=:docente')->setParameter('docente', $ricerca['docente']);
    }
    if (isset($ricerca['destinatari'])) {
      if (in_array($ricerca['destinatari'], ['C', 'D', 'G', 'A', 'R', 'I', 'L', 'S', 'P'])) {
        $avvisi = $avvisi->andWhere('INSTR(a.destinatari, :destinatari)>0')
          ->setParameter('destinatari', $ricerca['destinatari']);
      } elseif ($ricerca['destinatari'] == 'E') {
        $avvisi = $avvisi->andWhere('INSTR(a.destinatariAta, :destinatari)>0')
          ->setParameter('destinatari', 'D');
      } elseif ($ricerca['destinatari'] == 'T') {
        $avvisi = $avvisi->andWhere('INSTR(a.destinatariAta, :destinatari)>0')
          ->setParameter('destinatari', 'A');
      } elseif ($ricerca['destinatari'] == 'Z') {
        $avvisi = $avvisi->andWhere('INSTR(a.destinatariSpeciali, :destinatari)>0')
          ->setParameter('destinatari', 'S');
      }
    }
    if (isset($ricerca['classe']) && $ricerca['classe']) {
      $avvisi = $avvisi
        ->andWhere("a.filtroTipo=:tipoC AND INSTR(CONCAT(',',a.filtro,','), :classe)>0")
        ->setParameter('tipoC', 'C')
        ->setParameter('classe', ','.$ricerca['classe'].',');
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
      // legge classi destinazione
      $dati[$a->getId()] = $this->filtriAvviso($a);
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
   * @return array Dati formattati come array associativo
   */
  public function dettagliAvviso(Avviso $avviso) {
    $dati = array();
    // destinatari
    $dati = $this->filtriAvviso($avviso);
    // statistiche lettura
    $dati['statistiche'] = $this->em->getRepository('App\Entity\Avviso')->statistiche($avviso);
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
    if ($this->destinatario($avviso, $utente)) {
      // è destinatario: ok
      return true;
    }
    if ($utente instanceOf Docente && $utente->getId() == $avviso->getDocente()->getId()) {
      // è autore: ok
      return true;
    }
    // controlla destinatario classe
    if ($utente instanceOf Docente && $this->em->getRepository('App\Entity\AvvisoClasse')->findOneByAvviso($avviso)) {
      // docente può leggere gli avvisi rivolti alla classe
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
   *
   * @return bool Restituisce True se l'utente risulta destinatario dell'avviso, False altrimenti
   */
  public function destinatario(Avviso $avviso, Utente $utente): bool {
    // controlla destinatario
    $dest = $this->em->getRepository('App\Entity\AvvisoUtente')->createQueryBuilder('au')
      ->where('au.avviso=:avviso AND au.utente=:utente')
      ->setParameters(['avviso' => $avviso, 'utente' => $utente])
      ->setMaxResults(1)
      ->getQuery()
      ->getOneOrNullResult();
    if ($dest) {
      // destinatario corretto
      return true;
    }
    // non è destinatario
    return false;
  }

  /**
   * Recupera gli avvisi destinati all'utente indicato
   *
   * @param array $search Criteri di ricerca
   * @param int $pagina Numero di pagina per l'elenco da visualizzare
   * @param int $limite Numero massimo di elementi per pagina
   * @param Utente $utente Utente a cui sono indirizzati gli avvisi
   *
   * @return array Dati formattati come array associativo
   */
  public function bachecaAvvisi($search, $pagina, $limite, Utente $utente) {
    // lista avvisi
    $avvisi = $this->em->getRepository('App\Entity\Avviso')->createQueryBuilder('a')
      ->select('a as avviso,au.letto')
      ->join('App\Entity\AvvisoUtente', 'au', 'WITH', 'au.avviso=a.id')
      ->where('a.anno=0 AND au.utente=:utente')
      ->orderBy('a.data', 'DESC')
      ->setParameters(['utente' => $utente]);
    if ($search['visualizza'] == 'D') {
      $avvisi = $avvisi
        ->andWhere('au.letto IS NULL');
    }
    if ($search['oggetto']) {
      $avvisi = $avvisi
        ->andWhere('a.oggetto LIKE :oggetto')
        ->setParameter('oggetto', '%'.$search['oggetto'].'%');
    }
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
   * Controlla la presenza di avvisi non letti destinati agli alunni della classe indicata
   *
   * @param Classe $classe Classe a cui sono indirizzati gli avvisi
   *
   * @return int Numero di avvisi da leggere
   */
  public function bachecaNumeroAvvisiAlunni(Classe $classe) {
    // lista avvisi non letti
    $avvisi = $this->em->getRepository('App\Entity\AvvisoClasse')->createQueryBuilder('avc')
      ->select('COUNT(avc.avviso)')
      ->where('avc.classe=:classe AND avc.letto IS NULL')
      ->setParameters(['classe' => $classe])
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
   * @return array Dati formattati come array associativo
   */
  public function bachecaAvvisiAlunni(Classe $classe) {
    // lista avvisi non letti
    $avvisi = $this->em->getRepository('App\Entity\Avviso')->createQueryBuilder('a')
      ->join('App\Entity\AvvisoClasse', 'avc', 'WITH', 'avc.avviso=a.id')
      ->where('a.anno=0 AND avc.classe=:classe AND avc.letto IS NULL')
      ->orderBy('a.data', 'ASC')
      ->setParameters(['classe' => $classe])
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
   * @param string $id ID dell'avviso o "ALL" per tutti gli avvisi della classe
   */
  public function letturaAvvisoAlunni(Classe $classe, string $id) {
    if ($id == 'ALL') {
      // tutti gli avvisi
      $avc = $this->em->getRepository('App\Entity\AvvisoClasse')->createQueryBuilder('avc')
        ->where('avc.classe=:classe AND avc.letto IS NULL')
        ->setParameters(['classe' => $classe])
        ->getQuery()
        ->getResult();
    } elseif ((int) $id) > 0) {
      // solo avviso indicato
      $avc = $this->em->getRepository('App\Entity\AvvisoClasse')->createQueryBuilder('avc')
        ->where('avc.avviso=:avviso AND avc.classe=:classe AND avc.letto IS NULL')
        ->setParameters(['avviso' => (int) $id, 'classe' => $classe])
        ->getQuery()
        ->getResult();
    }
    // firma avvisi
    foreach ($avc as $av) {
      $av->setLetto(new \DateTime());
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
   * @return array Dati formattati come array associativo
   */
  public function listaAvvisiCoordinatore($pagina, $limite, Docente $docente, Classe $classe) {
    $dati = array();
    // legge avvisi
    $avvisi = $this->em->getRepository('App\Entity\Avviso')->createQueryBuilder('a')
      ->join('a.cattedra', 'c')
      ->where('a.tipo=:tipo AND c.classe=:classe')
      ->setParameters(['tipo' => 'O', 'classe' => $classe])
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
   * Restituisce i destinatari per un'avviso
   *
   * @param Avviso $avviso Avviso a cui fare riferimento
   *
   * @return array Destinatari dell'avviso, come array associativo
   */
  public function destinatariAvviso(Avviso $avviso) {
    $utenti = array();
    $classi = array();
    $sedi = array_map(function ($s) { return $s->getId(); }, $avviso->getSedi()->toArray());
    // controllo classi
    $filtroClassi = [];
    if ($avviso->getFiltroTipo() == 'C') {
      $filtroClassi = $avviso->getFiltro();
      $articolate = $this->em->getRepository('App\Entity\Classe')->classiArticolate($filtroClassi);
      foreach ($articolate as $articolata) {
        if (!empty($articolata['comune'])) {
          $filtroClassi[] = $articolata['comune'];
        } else {
          $filtroClassi = array_merge($filtroClassi, $articolata['gruppi']);
        }
      }
    }
    // dsga
    if (in_array('D', $avviso->getDestinatariAta())) {
      // aggiunge DSGA
      $utenti = $this->em->getRepository('App\Entity\Ata')->getIdDsga();
    }
    // ata
    if (in_array('A', $avviso->getDestinatariAta())) {
      // aggiunge ATA
      $utenti = array_merge($utenti, $this->em->getRepository('App\Entity\Ata')->getIdAta($sedi));
    }
    // RSPP
    if (in_array('S', $avviso->getDestinatariSpeciali())) {
      // aggiunge RSPP
      $utenti = array_merge($utenti, $this->em->getRepository('App\Entity\Docente')->getIdRspp());
    }
    // coordinatori
    if (in_array('C', $avviso->getDestinatari())) {
      // aggiunge coordinatori
      $utenti = array_merge($utenti, $this->em->getRepository('App\Entity\Docente')
        ->getIdCoordinatore($sedi, $avviso->getFiltroTipo() == 'C' ? $filtroClassi : null));
    }
    // docenti
    if (in_array('D', $avviso->getDestinatari())) {
      // aggiunge docenti
      $utenti = array_merge($utenti, $this->em->getRepository('App\Entity\Docente')
        ->getIdDocente($sedi, $avviso->getFiltroTipo(),
          $avviso->getFiltroTipo() == 'C' ? $filtroClassi : $avviso->getFiltro()));
    }
    // genitori
    if (in_array('G', $avviso->getDestinatari())) {
      // aggiunge genitori
      $utenti = array_merge($utenti, $this->em->getRepository('App\Entity\Genitore')
        ->getIdGenitore($sedi, $avviso->getFiltroTipo(),
          $avviso->getFiltroTipo() == 'C' ? $filtroClassi : $avviso->getFiltro()));
    }
    // alunni
    if (in_array('A', $avviso->getDestinatari())) {
      // aggiunge alunni
      $utenti = array_merge($utenti, $this->em->getRepository('App\Entity\Alunno')
        ->getIdAlunno($sedi, $avviso->getFiltroTipo(),
        $avviso->getFiltroTipo() == 'C' ? $filtroClassi : $avviso->getFiltro()));
      if ($avviso->getFiltroTipo() != 'U') {
        // aggiunge classi
        $classi = array_merge($classi, $this->em->getRepository('App\Entity\Classe')
          ->getIdClasse($sedi, $avviso->getFiltroTipo() == 'C' ? $avviso->getFiltro() : null));
      }
    }
    // RSU, consiglio di istituto, consulta provinciale
    if ((in_array('R', $avviso->getDestinatari()) || in_array('I', $avviso->getDestinatari()) ||
        in_array('P', $avviso->getDestinatari())) && $avviso->getFiltroTipo() == 'T') {
      // aggiunge utenti
      $utenti = array_merge($utenti, $this->em->getRepository('App\Entity\Utente')
        ->getIdRappresentanti($avviso->getDestinatari()));
    }
    // rappresentanti di classe
    if (in_array('L', $avviso->getDestinatari()) || in_array('S', $avviso->getDestinatari())) {
      // aggiunge utenti
      $utenti = array_merge($utenti, $this->em->getRepository('App\Entity\Utente')
        ->getIdRappresentantiClasse($avviso->getDestinatari(), $sedi, $avviso->getFiltroTipo(),
          $avviso->getFiltro()));
    }
    // restituisce destinatari
    $dati['sedi'] = $sedi;
    $dati['utenti'] = array_unique($utenti);
    $dati['classi'] = array_unique($classi);
    return $dati;
  }

  /**
   * Aggiorna data lettura dell'avviso
   *
   * @param Avviso $avviso Avviso di cui segnare la lettura
   * @param Utente $utente Destinatario dell'avviso
   */
  public function letturaAvviso(Avviso $avviso, Utente $utente) {
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
