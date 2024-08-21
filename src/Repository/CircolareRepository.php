<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Repository;

use App\Entity\Circolare;
use App\Entity\Classe;
use App\Entity\Utente;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\Tools\Pagination\Paginator;


/**
 * Circolare - repository
 *
 * @author Antonello Dessì
 */
class CircolareRepository extends EntityRepository {

  /**
   * Restituisce il numero per la prossima circolare
   *
   * @return integer Il numero per la prossima circolare
   */
  public function prossimoNumero() {
    // A.S. in corso
    $anno = (int) substr($this->_em->getRepository(\App\Entity\Configurazione::class)->getParametro('anno_scolastico'), 0, 4);
    // legge l'ultima circolare dell'A.S. in corso
    $numero = $this->createQueryBuilder('c')
      ->select('MAX(c.numero)')
      ->where('c.anno=:anno')
      ->setParameters(['anno' => $anno])
      ->getQuery()
      ->getSingleScalarResult();
    // restituisce prossimo numero
    return ($numero + 1);
  }

  /**
   * Restituisce vero se il numero non è già in uso, falso altrimenti.
   *
   * @param Circolare $circolare Circolare esistente o da inserire
   *
   * @return bool Vero se il numero non è già in uso, falso altrimenti
   */
  public function controllaNumero(Circolare $circolare) {
    // legge la circolare in base e A.S.
    $trovato = $this->createQueryBuilder('c')
      ->where('c.numero=:numero AND c.anno=:anno')
      ->setParameters(['numero' => $circolare->getNumero(), 'anno' => $circolare->getAnno()]);
    if ($circolare->getId() > 0) {
      // circolare in modifica, esclude suo id
      $trovato
        ->andWhere('c.id!=:id')
        ->setParameter('id', $circolare->getId());
    }
    $trovato = $trovato
      ->getQuery()
      ->getOneOrNullResult();
    // restituisce vero se non esiste
    return ($trovato === null);
  }

  /**
   * Restituisce la lista delle circolari pubblicate nell'A.S. corrente, secondo i criteri di ricerca indicati
   *
   * @param array $search Lista dei criteri di ricerca
   * @param int $page Pagina corrente
   * @param int $limit Numero di elementi per pagina
   *
   * @return Paginator Oggetto Paginator
   */
  public function pubblicate($search, $page, $limit) {
    // A.S. in corso
    $anno = (int) substr($this->_em->getRepository(\App\Entity\Configurazione::class)->getParametro('anno_scolastico'), 0, 4);
    // crea query base
    $query = $this->createQueryBuilder('c')
      ->where('c.data BETWEEN :inizio AND :fine AND c.oggetto LIKE :oggetto AND c.pubblicata=:pubblicata AND c.anno=:anno')
      ->orderBy('c.data', 'DESC')
      ->addOrderBy('c.numero', 'DESC')
      ->setParameters(['inizio' => $search['inizio'], 'fine' => $search['fine'], 'oggetto' => '%'.$search['oggetto'].'%',
        'pubblicata' => 1, 'anno' => $anno]);
    // crea lista con pagine
    return $this->paginate($query->getQuery(), $page, $limit);
  }

  /**
   * Restituisce la lista delle circolari in bozza
   *
   * @return array Lista di circolari
   */
  public function bozza() {
    // crea query base
    $circolari = $this->createQueryBuilder('c')
      ->where('c.pubblicata=:bozza')
      ->orderBy('c.data', 'DESC')
      ->addOrderBy('c.numero', 'DESC')
      ->setParameters(['bozza' => 0])
      ->getQuery()
      ->getResult();
    // restituisce lista
    return $circolari;
  }

 /**
   * Paginatore dei risultati della query
   *
   * @param Query $dql Query da mostrare
   * @param int $page Pagina corrente
   * @param int $limit Numero di elementi per pagina
   *
   * @return Paginator Oggetto Paginator
   */
  public function paginate(Query $dql, int $page, int $limit): Paginator {
    $paginator = new Paginator($dql);
    $paginator->getQuery()
      ->setFirstResult($limit * ($page - 1))
      ->setMaxResults($limit);
    return $paginator;
  }

  /**
   * Controlla la presenza di circolari non lette e destinate agli alunni della classe indicata
   *
   * @param Classe $classe Classe a cui sono indirizzate le circolari
   *
   * @return int Numero di circolari da leggere
   */
  public function numeroCircolariClasse(Classe $classe) {
    // A.S. in corso
    $anno = (int) substr($this->_em->getRepository(\App\Entity\Configurazione::class)->getParametro('anno_scolastico'), 0, 4);
    // lista circolari
    $circolari = $this->createQueryBuilder('c')
      ->select('COUNT(c)')
      ->join(\App\Entity\CircolareClasse::class, 'cc', 'WITH', 'cc.circolare=c.id AND cc.classe=:classe')
      ->where('c.pubblicata=:pubblicata AND c.anno=:anno AND cc.letta IS NULL')
      ->setParameters(['pubblicata' => 1, 'anno' => $anno, 'classe' => $classe])
      ->getQuery()
      ->getSingleScalarResult();
    // restituisce dati
    return $circolari;
  }

  /**
   * Controlla la presenza di circolari non lette e destinate all'utente
   *
   * @param Utente $utente Utente a cui sono indirizzate le circolari
   *
   * @return int Numero di circolari da leggere
   */
  public function numeroCircolariUtente(Utente $utente) {
    // A.S. in corso
    $anno = (int) substr($this->_em->getRepository(\App\Entity\Configurazione::class)->getParametro('anno_scolastico'), 0, 4);
  // lista circolari
    $circolari = $this->createQueryBuilder('c')
      ->select('COUNT(c)')
      ->join(\App\Entity\CircolareUtente::class, 'cu', 'WITH', 'cu.circolare=c.id AND cu.utente=:utente')
      ->where('c.pubblicata=:pubblicata AND c.anno=:anno AND cu.letta IS NULL')
      ->setParameters(['pubblicata' => 1, 'anno' => $anno, 'utente' => $utente])
      ->getQuery()
      ->getSingleScalarResult();
    // restituisce dati
    return $circolari;
  }

  /**
   * Lista delle circolari non lette e destinate agli alunni della classe indicata
   *
   * @param Classe $classe Classe a cui sono indirizzate le circolari
   *
   * @return array Dati formattati come array associativo
   */
  public function circolariClasse(Classe $classe) {
    // A.S. in corso
    $anno = (int) substr($this->_em->getRepository(\App\Entity\Configurazione::class)->getParametro('anno_scolastico'), 0, 4);
    // lista circolari
    $circolari = $this->createQueryBuilder('c')
      ->join(\App\Entity\CircolareClasse::class, 'cc', 'WITH', 'cc.circolare=c.id AND cc.classe=:classe')
      ->where('c.pubblicata=:pubblicata AND c.anno=:anno AND cc.letta IS NULL')
      ->setParameters(['pubblicata' => 1, 'anno' => $anno, 'classe' => $classe])
      ->getQuery()
      ->getResult();
    // restituisce dati
    return $circolari;
  }

  /**
   * Conferma la lettura della circolare da parte dell'utente
   *
   * @param Circolare $circolare Circolare da firmare
   * @param Utente $utente Destinatario della circolare
   *
   * @return bool Vero se inserita conferma di lettura, falso altrimenti
   */
  public function firma(Circolare $circolare, Utente $utente) {
    // dati destinatario
    $cu = $this->_em->getRepository(\App\Entity\CircolareUtente::class)->findOneBy(['circolare' => $circolare,
      'utente' => $utente]);
    if ($cu && !$cu->getConfermata()) {
      // imposta conferma di lettura
      $ora = new \DateTime();
      $cu
        ->setConfermata($ora)
        ->setLetta($ora);
      // memorizza dati
      $this->_em->flush();
      // conferma inserita
      return true;
    }
    // conferma non inserita
    return false;
  }

  /**
   * Conferma la lettura della circolare alla classe
   *
   * @param Classe $classe Classe a cui è stata letta la circolare
   * @param int $id ID della circolare (0 indica tutte)
   *
   * @return array Lista delle circolari lette
   */
  public function firmaClasse(Classe $classe, $id) {
    $firme = [];
    // query
    $circolari = $this->_em->getRepository(\App\Entity\CircolareClasse::class)->createQueryBuilder('cc')
      ->join('cc.circolare', 'c')
      ->where('cc.classe=:classe AND cc.letta IS NULL AND c.pubblicata=:pubblicata')
      ->setParameters(['classe' => $classe, 'pubblicata' => 1]);
    if ($id > 0) {
      // singola circolare
      $circolari->andWhere('c.id=:id')->setParameter('id', $id);
    }
    $circolari = $circolari
      ->orderBy('c.numero', 'ASC')
      ->getQuery()
      ->getResult();
    // firma circolare
    $ora = new \DateTime();
    foreach ($circolari as $c) {
      $c->setLetta($ora);
      $this->_em->flush();
      $firme[] = $c->getCircolare();
    }
    // restituisce lista circolari firmate
    return $firme;
  }

  /**
   * Restituisce le statistiche sulla lettura della circolare
   *
   * @param Circolare $circolare Circolare da firmare
   *
   * @return array Dati formattati come array associativo
   */
  public function statistiche(Circolare $circolare) {
    $dati = [];
    $dati['ata'] = [0, 0, []];
    $dati['dsga'] = [0, 0, []];
    $dati['coordinatori'] = [0, 0, []];
    $dati['docenti'] = [0, 0, []];
    $dati['genitori'] = [0, 0, []];
    $dati['alunni'] = [0, 0, []];
    $dati['classi'] = [0, 0, []];
    // utenti DSGA/ATA
    if ($circolare->getDsga() || $circolare->getAta()) {
      // dsga/ata
      $utenti = $this->createQueryBuilder('c')
        ->select('ata.tipo,COUNT(cu.id) AS tot,COUNT(cu.letta) AS letti')
        ->join(\App\Entity\CircolareUtente::class, 'cu', 'WITH', 'cu.circolare=c.id')
        ->join(\App\Entity\Ata::class, 'ata', 'WITH', 'ata.id=cu.utente')
        ->where('c.id=:circolare')
        ->setParameters(['circolare' => $circolare])
        ->groupBy('ata.tipo')
        ->getQuery()
        ->getArrayResult();
      $ata = [0, 0, []];
      foreach ($utenti as $u) {
        if ($u['tipo'] == 'D') {
          // dsga
          $dati['dsga'] = [$u['tot'], $u['letti'], []];
        } else {
          // altri ata
          $ata[0] += $u['tot'];
          $ata[1] += $u['letti'];
        }
      }
      if ($ata[0] > 0) {
        $dati['ata'] = $ata;
      }
      // dati di lettura
      $utenti = $this->createQueryBuilder('c')
        ->select('ata.cognome,ata.nome,ata.tipo,cu.letta')
        ->join(\App\Entity\CircolareUtente::class, 'cu', 'WITH', 'cu.circolare=c.id')
        ->join(\App\Entity\Ata::class, 'ata', 'WITH', 'ata.id=cu.utente')
        ->where('c.id=:circolare AND cu.letta IS NOT NULL')
        ->setParameters(['circolare' => $circolare])
        ->orderBy('ata.cognome,ata.nome', 'ASC')
        ->getQuery()
        ->getArrayResult();
      foreach ($utenti as $utente) {
        $dati[$utente['tipo'] == 'D' ? 'dsga' : 'ata'][2][] = [
          $utente['letta'],
          $utente['cognome'].' '.$utente['nome']];
      }
    }
    // utenti coordinatori
    if ($circolare->getCoordinatori() != 'N') {
      // coordinatori
      $utenti = $this->createQueryBuilder('c')
        ->select('COUNT(cu.id) AS tot,COUNT(cu.letta) AS letti')
        ->join(\App\Entity\CircolareUtente::class, 'cu', 'WITH', 'cu.circolare=c.id')
        ->join(\App\Entity\Docente::class, 'd', 'WITH', 'd.id=cu.utente')
        ->join(\App\Entity\Classe::class, 'cl', 'WITH', 'cl.coordinatore=d.id')
        ->where('c.id=:circolare')
        ->setParameters(['circolare' => $circolare])
        ->getQuery()
        ->getArrayResult();
      $dati['coordinatori'] = [$utenti[0]['tot'], $utenti[0]['letti'], []];
      // dati di lettura
      $utenti = $this->createQueryBuilder('c')
        ->select('d.cognome,d.nome,cl.anno,cl.sezione,cl.gruppo,cu.letta')
        ->join(\App\Entity\CircolareUtente::class, 'cu', 'WITH', 'cu.circolare=c.id')
        ->join(\App\Entity\Docente::class, 'd', 'WITH', 'd.id=cu.utente')
        ->join(\App\Entity\Classe::class, 'cl', 'WITH', 'cl.coordinatore=d.id')
        ->where('c.id=:circolare AND cu.letta IS NOT NULL')
        ->setParameters(['circolare' => $circolare])
        ->orderBy('cl.anno,cl.sezione,cl.gruppo', 'ASC')
        ->getQuery()
        ->getArrayResult();
      foreach ($utenti as $utente) {
        $dati['coordinatori'][2][] = [
          $utente['letta'],
          $utente['anno'].'ª '.$utente['sezione'].($utente['gruppo'] ? '-'.$utente['gruppo'] : '').' - '.
          $utente['cognome'].' '.$utente['nome']];
      }
    }
    // utenti docenti
    if ($circolare->getDocenti() != 'N') {
      // docenti
      $utenti = $this->createQueryBuilder('c')
        ->select('COUNT(cu.id) AS tot,COUNT(cu.letta) AS letti')
        ->join(\App\Entity\CircolareUtente::class, 'cu', 'WITH', 'cu.circolare=c.id')
        ->join(\App\Entity\Docente::class, 'd', 'WITH', 'd.id=cu.utente')
        ->where('c.id=:circolare')
        ->setParameters(['circolare' => $circolare])
        ->getQuery()
        ->getArrayResult();
      $dati['docenti'] = [$utenti[0]['tot'], $utenti[0]['letti']];
      // dati di lettura
      $utenti = $this->createQueryBuilder('c')
        ->select('d.cognome,d.nome,cu.letta')
        ->join(\App\Entity\CircolareUtente::class, 'cu', 'WITH', 'cu.circolare=c.id')
        ->join(\App\Entity\Docente::class, 'd', 'WITH', 'd.id=cu.utente')
        ->where('c.id=:circolare AND cu.letta IS NOT NULL')
        ->setParameters(['circolare' => $circolare])
        ->orderBy('d.cognome,d.nome', 'ASC')
        ->getQuery()
        ->getArrayResult();
      foreach ($utenti as $utente) {
        $dati['docenti'][2][] = [
          $utente['letta'],
          $utente['cognome'].' '.$utente['nome']];
      }
    }
    // utenti genitori
    if ($circolare->getGenitori() != 'N') {
      // genitori
      $utenti = $this->createQueryBuilder('c')
        ->select('COUNT(cu.id) AS tot,COUNT(cu.letta) AS letti')
        ->join(\App\Entity\CircolareUtente::class, 'cu', 'WITH', 'cu.circolare=c.id')
        ->join(\App\Entity\Genitore::class, 'g', 'WITH', 'g.id=cu.utente')
        ->where('c.id=:circolare')
        ->setParameters(['circolare' => $circolare])
        ->getQuery()
        ->getArrayResult();
      $dati['genitori'] = [$utenti[0]['tot'], $utenti[0]['letti']];
      // dati di lettura
      $utenti = $this->createQueryBuilder('c')
        ->select('a.cognome,a.nome,cl.anno,cl.sezione,cl.gruppo,g.cognome AS cognome_gen,g.nome AS nome_gen,cu.letta')
        ->join(\App\Entity\CircolareUtente::class, 'cu', 'WITH', 'cu.circolare=c.id')
        ->join(\App\Entity\Genitore::class, 'g', 'WITH', 'g.id=cu.utente')
        ->join('g.alunno', 'a')
        ->join('a.classe', 'cl')
        ->where('c.id=:circolare AND cu.letta IS NOT NULL')
        ->setParameters(['circolare' => $circolare])
        ->orderBy('cl.anno,cl.sezione,cl.gruppo,a.cognome,a.nome', 'ASC')
        ->getQuery()
        ->getArrayResult();
      foreach ($utenti as $utente) {
        $dati['genitori'][2][] = [
          $utente['letta'],
          $utente['anno'].'ª '.$utente['sezione'].($utente['gruppo'] ? '-'.$utente['gruppo'] : '').' - '.
          $utente['cognome'].' '.$utente['nome'].
          ' ('.$utente['cognome_gen'].' '.$utente['nome_gen'].')'];
      }
    }
    // utenti alunni
    if ($circolare->getAlunni() != 'N') {
      // alunni
      $utenti = $this->createQueryBuilder('c')
        ->select('COUNT(cu.id) AS tot,COUNT(cu.letta) AS letti')
        ->join(\App\Entity\CircolareUtente::class, 'cu', 'WITH', 'cu.circolare=c.id')
        ->join(\App\Entity\Alunno::class, 'a', 'WITH', 'a.id=cu.utente')
        ->where('c.id=:circolare')
        ->setParameters(['circolare' => $circolare])
        ->getQuery()
        ->getArrayResult();
      $dati['alunni'] = [$utenti[0]['tot'], $utenti[0]['letti']];
      // dati di lettura
      $utenti = $this->createQueryBuilder('c')
        ->select('a.cognome,a.nome,cl.anno,cl.sezione,cl.gruppo,cu.letta')
        ->join(\App\Entity\CircolareUtente::class, 'cu', 'WITH', 'cu.circolare=c.id')
        ->join(\App\Entity\Alunno::class, 'a', 'WITH', 'a.id=cu.utente')
        ->join('a.classe', 'cl')
        ->where('c.id=:circolare AND cu.letta IS NOT NULL')
        ->setParameters(['circolare' => $circolare])
        ->orderBy('cl.anno,cl.sezione,cl.gruppo,a.cognome,a.nome', 'ASC')
        ->getQuery()
        ->getArrayResult();
      foreach ($utenti as $utente) {
        $dati['alunni'][2][] = [
          $utente['letta'],
          $utente['anno'].'ª '.$utente['sezione'].($utente['gruppo'] ? '-'.$utente['gruppo'] : '').' - '.
          $utente['cognome'].' '.$utente['nome']];
      }
    }
    // lettura classi
    $sql = "SELECT COUNT(*) AS tot,COUNT(cc.letta) AS lette ".
      "FROM gs_circolare AS c, gs_circolare_classe AS cc, gs_classe AS cl ".
      "WHERE c.id=:id AND c.id=cc.circolare_id AND cl.id=cc.classe_id";
    $query = $this->_em->getConnection()->prepare($sql);
    $stat = $query->executeQuery(['id' => $circolare->getId()]);
    $stat = $stat->fetchAllAssociative();
    if ($stat[0]['tot'] > 0) {
      $dati['classi'] = [$stat[0]['tot'], $stat[0]['lette'], []];
    }
    if ($stat[0]['tot'] > $stat[0]['lette']) {
      // lista classi in cui va letta
      $classi = $this->createQueryBuilder('c')
        ->select("CONCAT(cl.anno,'ª ',cl.sezione) AS nome,cl.gruppo")
        ->join(\App\Entity\CircolareClasse::class, 'cc', 'WITH', 'cc.circolare=c.id')
        ->join('cc.classe', 'cl')
        ->where('c.id=:id AND cc.letta IS NULL')
        ->setParameters(['id' => $circolare->getId()])
        ->orderBy('cl.anno,cl.sezione,cl.gruppo', 'ASC')
        ->getQuery()
        ->getScalarResult();
      $dati['classi'][2] = array_map(
        fn($c) => $c['nome'].($c['gruppo'] ? ('-'.$c['gruppo']) : ''), $classi);
    }
    // restituisce i dati
    return $dati;
  }

  /**
   * Restituisce la lista degli utenti a cui deve essere inviata una notifica per la circolare indicata
   *
   * @param Circolare $circolare Circolare da notificare
   *
   * @return array Lista degli utenti
   */
  public function notifica(Circolare $circolare) {
    // legge destinatari
    $destinatari = $this->_em->getRepository(\App\Entity\CircolareUtente::class)->createQueryBuilder('cu')
      ->select('(cu.utente) AS utente')
      ->join('cu.circolare', 'c')
      ->where('c.id=:circolare AND c.pubblicata=:pubblicata AND cu.letta IS NULL')
      ->setParameters(['circolare' => $circolare, 'pubblicata' => 1])
      ->getQuery()
      ->getArrayResult();
    // restituisce lista utenti
    return array_column($destinatari, 'utente');
  }

  /**
   * Restituisce la lista delle circolari rispondenti alle condizioni di ricerca.
   *
   * @param array $cerca Lista dei criteri di ricerca
   * @param int $pagina Pagina corrente
   * @param int $limite Numero di elementi per pagina
   * @param Utente $utente Destinatario delle circolari
   *
   * @return array Dati formattati come array associativo
   */
  public function lista($cerca, $pagina, $limite, Utente $utente) {
    $dati = [];
    // A.S.
    if (isset($cerca['anno'])) {
      // legge A.S. da criteri di ricerca
      $anno = $cerca['anno'];
    } else {
      // A.S. in corso
      $anno = (int) substr($this->_em->getRepository(\App\Entity\Configurazione::class)->getParametro('anno_scolastico'), 0, 4);
    }
    // legge circolari
    $query = $this->createQueryBuilder('c')
      ->where('c.pubblicata=:pubblicata AND c.anno=:anno')
      ->setParameter('pubblicata', 1)
      ->setParameter('anno', $anno)
      ->orderBy('c.data', 'DESC')
      ->addOrderBy('c.numero', 'DESC');
    if ($cerca['visualizza'] != 'T') {
      // solo circolari destinate all'utente
      $query
        ->join(\App\Entity\CircolareUtente::class, 'cu', 'WITH', 'cu.circolare=c.id AND cu.utente=:utente')
        ->setParameter('utente', $utente);
      if ($cerca['visualizza'] == 'D') {
        // solo quelle da leggere
        $query
          ->andWhere('cu.letta IS NULL');
      }
    }
    if ($cerca['mese']) {
      // filtra per data
      $query
        ->andWhere('MONTH(c.data)=:mese')
        ->setParameter('mese', (int) $cerca['mese']);
    }
    if ($cerca['oggetto']) {
      // filtra per oggetto
      $query
        ->andWhere('c.oggetto LIKE :oggetto')
        ->setParameter('oggetto', '%'.$cerca['oggetto'].'%');
    }
    $dati['lista'] = $this->paginate($query->getQuery(), $pagina, $limite);
    // aggiunge dati di lettura
    foreach ($dati['lista'] as $c) {
      $dati['stato'][$c->getId()] = $this->_em->getRepository(\App\Entity\CircolareUtente::class)->findOneBy([
        'circolare' => $c, 'utente' => $utente]);
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Restituisce le circolari non lette e destinate agli alunni della classe indicata
   *
   * @param Classe $classe Classe a cui sono indirizzate le circolari
   *
   * @return array Lista di circolari da leggere
   */
  public function listaCircolariClasse(Classe $classe) {
    // A.S. in corso
    $anno = (int) substr($this->_em->getRepository(\App\Entity\Configurazione::class)->getParametro('anno_scolastico'), 0, 4);
    // lista circolari
    $circolari = $this->createQueryBuilder('c')
      ->join(\App\Entity\CircolareClasse::class, 'cc', 'WITH', 'cc.circolare=c.id AND cc.classe=:classe')
      ->where('c.pubblicata=:pubblicata AND c.anno=:anno AND cc.letta IS NULL')
      ->setParameters(['pubblicata' => 1, 'anno' => $anno, 'classe' => $classe])
      ->orderBy('c.data', 'ASC')
      ->addOrderBy('c.numero', 'ASC')
      ->getQuery()
      ->getArrayResult();
    // restituisce dati
    return $circolari;
  }

  /**
   * Restituisce la lista degli anni scolastici presenti nell'archivio delle circolari
   *
   * @return array Dati formattati come array associativo
   */
  public function anniScolastici() {
    // inizializza
    $dati = [];
    // legge anni
    $anni = $this->createQueryBuilder('c')
      ->select('DISTINCT c.anno')
      ->where('c.pubblicata=:pubblicata')
      ->setParameters(['pubblicata' => 1])
      ->orderBy('c.anno', 'DESC')
      ->getQuery()
      ->getArrayResult();
    foreach ($anni as $val) {
      $dati['A.S. '.$val['anno'].'/'.($val['anno'] + 1)] = $val['anno'];
    }
    // restituisce dati formattati
    return $dati;
  }

}
