<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Repository;

use App\Entity\AvvisoUtente;
use App\Entity\Ata;
use App\Entity\Docente;
use App\Entity\Classe;
use App\Entity\Genitore;
use App\Entity\Alunno;
use App\Entity\AvvisoClasse;
use App\Entity\Avviso;
use Doctrine\ORM\Tools\Pagination\Paginator;


/**
 * Avviso - repository
 *
 * @author Antonello Dessì
 */
class AvvisoRepository extends BaseRepository {

  /**
   * Restituisce le statistiche sulla lettura della circolare
   *
   * @param Avviso $avviso Avviso di cui fare le statistiche di lettura
   *
   * @return array Dati formattati come array associativo
   */
  public function statistiche(Avviso $avviso): array {
    $dati = [];
    $dati['ata'] = [0, 0, []];
    $dati['dsga'] = [0, 0, []];
    $dati['coordinatori'] = [0, 0, []];
    $dati['docenti'] = [0, 0, []];
    $dati['genitori'] = [0, 0, []];
    $dati['alunni'] = [0, 0, []];
    $dati['classi'] = [0, 0, []];
    // lettura utenti
    if (count($avviso->getDestinatariAta()) > 0) {
      // dsga/ata
      $utenti = $this->createQueryBuilder('a')
        ->select('ata.tipo,COUNT(au.id) AS tot,COUNT(au.letto) AS letti')
        ->join(AvvisoUtente::class, 'au', 'WITH', 'au.avviso=a.id')
        ->join(Ata::class, 'ata', 'WITH', 'ata.id=au.utente')
        ->where('a.id=:avviso')
        ->setParameter('avviso', $avviso)
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
      $utenti = $this->createQueryBuilder('a')
        ->select('ata.cognome,ata.nome,ata.tipo,au.letto')
        ->join(AvvisoUtente::class, 'au', 'WITH', 'au.avviso=a.id')
        ->join(Ata::class, 'ata', 'WITH', 'ata.id=au.utente')
        ->where('a.id=:avviso AND au.letto IS NOT NULL')
        ->setParameter('avviso', $avviso)
        ->orderBy('ata.cognome,ata.nome', 'ASC')
        ->getQuery()
        ->getArrayResult();
      foreach ($utenti as $utente) {
        $dati[$utente['tipo'] == 'D' ? 'dsga' : 'ata'][2][] = [
          $utente['letto'],
          $utente['cognome'].' '.$utente['nome']];
      }
    }
    if (in_array('C', $avviso->getDestinatari())) {
      // coordinatori
      $utenti = $this->createQueryBuilder('a')
        ->select('COUNT(au.id) AS tot,COUNT(au.letto) AS letti')
        ->join(AvvisoUtente::class, 'au', 'WITH', 'au.avviso=a.id')
        ->join(Docente::class, 'd', 'WITH', 'd.id=au.utente')
        ->join(Classe::class, 'c', 'WITH', 'c.coordinatore=d.id')
        ->where('a.id=:avviso')
        ->setParameter('avviso', $avviso)
        ->getQuery()
        ->getArrayResult();
      $dati['coordinatori'] = [$utenti[0]['tot'], $utenti[0]['letti'], []];
      // dati di lettura
      $utenti = $this->createQueryBuilder('a')
        ->select('d.cognome,d.nome,c.anno,c.sezione,c.gruppo,au.letto')
        ->join(AvvisoUtente::class, 'au', 'WITH', 'au.avviso=a.id')
        ->join(Docente::class, 'd', 'WITH', 'd.id=au.utente')
        ->join(Classe::class, 'c', 'WITH', 'c.coordinatore=d.id')
        ->where('a.id=:avviso AND au.letto IS NOT NULL')
			  ->setParameter('avviso', $avviso)
        ->orderBy('c.anno,c.sezione,c.gruppo', 'ASC')
        ->getQuery()
        ->getArrayResult();
      foreach ($utenti as $utente) {
        $dati['coordinatori'][2][] = [
          $utente['letto'],
          $utente['anno'].'ª '.$utente['sezione'].($utente['gruppo'] ? '-'.$utente['gruppo'] : '').' - '.
          $utente['cognome'].' '.$utente['nome']];
      }
    }
    if (in_array('D', $avviso->getDestinatari())) {
      // docenti
      $utenti = $this->createQueryBuilder('a')
        ->select('COUNT(au.id) AS tot,COUNT(au.letto) AS letti')
        ->join(AvvisoUtente::class, 'au', 'WITH', 'au.avviso=a.id')
        ->join(Docente::class, 'd', 'WITH', 'd.id=au.utente')
        ->where('a.id=:avviso')
			  ->setParameter('avviso', $avviso)
        ->getQuery()
        ->getArrayResult();
      $dati['docenti'] = [$utenti[0]['tot'], $utenti[0]['letti']];
      // dati di lettura
      $utenti = $this->createQueryBuilder('a')
        ->select('d.cognome,d.nome,au.letto')
        ->join(AvvisoUtente::class, 'au', 'WITH', 'au.avviso=a.id')
        ->join(Docente::class, 'd', 'WITH', 'd.id=au.utente')
        ->where('a.id=:avviso AND au.letto IS NOT NULL')
			  ->setParameter('avviso', $avviso)
        ->orderBy('d.cognome,d.nome', 'ASC')
        ->getQuery()
        ->getArrayResult();
      foreach ($utenti as $utente) {
        $dati['docenti'][2][] = [
          $utente['letto'],
          $utente['cognome'].' '.$utente['nome']];
      }
    }
    if (in_array('G', $avviso->getDestinatari())) {
      // genitori
      $utenti = $this->createQueryBuilder('a')
        ->select('COUNT(au.id) AS tot,COUNT(au.letto) AS letti')
        ->join(AvvisoUtente::class, 'au', 'WITH', 'au.avviso=a.id')
        ->join(Genitore::class, 'g', 'WITH', 'g.id=au.utente')
        ->where('a.id=:avviso')
			  ->setParameter('avviso', $avviso)
        ->getQuery()
        ->getArrayResult();
      $dati['genitori'] = [$utenti[0]['tot'], $utenti[0]['letti']];
      // dati di lettura
      $utenti = $this->createQueryBuilder('a')
        ->select('al.cognome,al.nome,c.anno,c.sezione,c.gruppo,g.cognome AS cognome_gen,g.nome AS nome_gen,au.letto')
        ->join(AvvisoUtente::class, 'au', 'WITH', 'au.avviso=a.id')
        ->join(Genitore::class, 'g', 'WITH', 'g.id=au.utente')
        ->join('g.alunno', 'al')
        ->join('al.classe', 'c')
        ->where('a.id=:avviso AND au.letto IS NOT NULL')
			  ->setParameter('avviso', $avviso)
        ->orderBy('c.anno,c.sezione,c.gruppo,al.cognome,al.nome', 'ASC')
        ->getQuery()
        ->getArrayResult();
      foreach ($utenti as $utente) {
        $dati['genitori'][2][] = [
          $utente['letto'],
          $utente['anno'].'ª '.$utente['sezione'].($utente['gruppo'] ? '-'.$utente['gruppo'] : '').' - '.
          $utente['cognome'].' '.$utente['nome'].
          ' ('.$utente['cognome_gen'].' '.$utente['nome_gen'].')'];
      }
    }
    if (in_array('A', $avviso->getDestinatari())) {
      // alunni
      $utenti = $this->createQueryBuilder('a')
        ->select('COUNT(au.id) AS tot,COUNT(au.letto) AS letti')
        ->join(AvvisoUtente::class, 'au', 'WITH', 'au.avviso=a.id')
        ->join(Alunno::class, 'al', 'WITH', 'al.id=au.utente')
        ->where('a.id=:avviso')
			  ->setParameter('avviso', $avviso)
        ->getQuery()
        ->getArrayResult();
      $dati['alunni'] = [$utenti[0]['tot'], $utenti[0]['letti']];
      // dati di lettura
      $utenti = $this->createQueryBuilder('a')
        ->select('al.cognome,al.nome,c.anno,c.sezione,c.gruppo,au.letto')
        ->join(AvvisoUtente::class, 'au', 'WITH', 'au.avviso=a.id')
        ->join(Alunno::class, 'al', 'WITH', 'al.id=au.utente')
        ->join('al.classe', 'c')
        ->where('a.id=:avviso AND au.letto IS NOT NULL')
			  ->setParameter('avviso', $avviso)
        ->orderBy('c.anno,c.sezione,c.gruppo,al.cognome,al.nome', 'ASC')
        ->getQuery()
        ->getArrayResult();
      foreach ($utenti as $utente) {
        $dati['alunni'][2][] = [
          $utente['letto'],
          $utente['anno'].'ª '.$utente['sezione'].($utente['gruppo'] ? '-'.$utente['gruppo'] : '').' - '.
          $utente['cognome'].' '.$utente['nome']];
      }
      // classi
      $classi = $this->createQueryBuilder('a')
        ->select('COUNT(ac.id) AS tot,COUNT(ac.letto) AS letti')
        ->join(AvvisoClasse::class, 'ac', 'WITH', 'ac.avviso=a.id')
        ->join('ac.classe', 'cl')
        ->where('a.id=:avviso')
			  ->setParameter('avviso', $avviso)
        ->getQuery()
        ->getArrayResult();
      if ($classi[0]['tot'] > 0) {
        $dati['classi'] = [$classi[0]['tot'], $classi[0]['letti'], []];
        if ($classi[0]['tot'] > $classi[0]['letti']) {
          // lista classi in cui va letta
          $classi = $this->createQueryBuilder('a')
            ->select("CONCAT(cl.anno,'ª ',cl.sezione) AS nome,cl.gruppo")
            ->join(AvvisoClasse::class, 'ac', 'WITH', 'ac.avviso=a.id')
            ->join('ac.classe', 'cl')
            ->where('a.id=:avviso AND ac.letto IS NULL')
			      ->setParameter('avviso', $avviso)
            ->orderBy('cl.anno,cl.sezione,cl.gruppo', 'ASC')
            ->getQuery()
            ->getArrayResult();
          $dati['classi'][2] = array_map(
            fn($c) => $c['nome'].($c['gruppo'] ? ('-'.$c['gruppo']) : ''), $classi);
        }
      }
    }
    // restituisce i dati
    return $dati;
  }

  /**
   * Restituisce la lista degli utenti a cui deve essere inviata una notifica per l'avviso indicato
   *
   * @param Avviso $avviso Avviso da notificare
   *
   * @return array Lista degli utenti
   */
  public function notifica(Avviso $avviso) {
    // legge destinatari
    $destinatari = $this->getEntityManager()->getRepository(AvvisoUtente::class)->createQueryBuilder('au')
      ->join('au.utente', 'u')
      ->where('au.avviso=:avviso AND au.letto IS NULL')
			->setParameter('avviso', $avviso)
      ->getQuery()
      ->getResult();
    $utenti = [];
    foreach ($destinatari as $dest) {
      $utenti[] = $dest->getUtente();
    }
    // restituisce lista utenti
    return $utenti;
  }

  /**
   * Restituisce la lista degli anni scolastici presenti nell'archivio degli avvisi
   *
   * @return array Dati formattati come array associativo
   */
  public function anniScolastici(): array {
    // inizializza
    $dati = [];
    // legge anni
    $anni = $this->createQueryBuilder('a')
      ->select('DISTINCT a.anno')
      ->where('a.anno > 0')
      ->orderBy('a.anno', 'DESC')
      ->getQuery()
      ->getArrayResult();
    foreach ($anni as $val) {
      $dati['A.S. '.$val['anno'].'/'.($val['anno'] + 1)] = $val['anno'];
    }
    // restituisce dati formattati
    return $dati;
  }

  /**
   * Restituisce la lista degli autori di avvisi presenti nell'archivio
   *
   * @return array Dati formattati come array associativo
   */
  public function autori(): array {
    // inizializza
    $dati = [];
    // legge docenti
    $docenti = $this->createQueryBuilder('a')
      ->select('DISTINCT (a.docente) AS id,d.cognome,d.nome')
      ->join('a.docente', 'd')
      ->where('a.anno > 0')
      ->orderBy('d.cognome,d.nome', 'ASC')
      ->getQuery()
      ->getArrayResult();
    foreach ($docenti as $val) {
      $dati[$val['nome'].' '.$val['cognome']] = $val['id'];
    }
    // restituisce dati formattati
    return $dati;
  }

  /**
   * Restituisce la lista degli avvisi in archivio che rispondono ai criteri di ricerca impostati
   *
   * @param array $ricerca Criteri di ricerca
   * @param int $pagina Pagina corrente
   * @param int $limite Numero di elementi per pagina
   *
   * @return array Dati formattati come array associativo
   */
  public function listaArchivio($ricerca, $pagina, $limite): Paginator {
    // crea query base
    $query = $this->createQueryBuilder('a')
      ->where('a.anno > 0')
      ->orderBy('a.data,a.ora', 'ASC');
    if ($ricerca['anno'] > 0) {
      // filtra per anno
      $query->andWhere('a.anno = :anno')->setParameter('anno', $ricerca['anno']);
    }
    if ($ricerca['mese'] > 0) {
      // filtra per mese
      $query->andWhere('MONTH(a.data) = :mese')->setParameter('mese', $ricerca['mese']);
    }
    if ($ricerca['docente'] > 0) {
      // filtra per autore
      $query->andWhere('a.docente = :docente')->setParameter('docente', $ricerca['docente']);
    }
    if (!empty($ricerca['destinatari'])) {
      // filtra per destinatari
      if (in_array($ricerca['destinatari'], ['C', 'D', 'G', 'A', 'R', 'I', 'L', 'S', 'P'])) {
        $query->andWhere('INSTR(a.destinatari, :destinatari)>0')
          ->setParameter('destinatari', $ricerca['destinatari']);
      } elseif ($ricerca['destinatari'] == 'E') {
        $query->andWhere('INSTR(a.destinatariAta, :destinatari)>0')
          ->setParameter('destinatari', 'D');
      } elseif ($ricerca['destinatari'] == 'T') {
        $query->andWhere('INSTR(a.destinatariAta, :destinatari)>0')
          ->setParameter('destinatari', 'A');
      } elseif ($ricerca['destinatari'] == 'Z') {
        $query->andWhere('INSTR(a.destinatariSpeciali, :destinatari)>0')
          ->setParameter('destinatari', 'S');
      }
    }
    if (!empty($ricerca['oggetto'])) {
      // filtra per oggetto
      $query->andWhere('a.oggetto LIKE :oggetto')
        ->setParameter('oggetto', '%'.$ricerca['oggetto'].'%');
    }
    // crea lista con pagine
    $dati = $this->paginazione($query->getQuery(), $pagina);
    return $dati['lista'];
  }

}
