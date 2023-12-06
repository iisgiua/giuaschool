<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Repository;

use App\Entity\Avviso;


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
    $dati = array();
    $dati['ata'] = array(0, 0, []);
    $dati['dsga'] = array(0, 0, []);
    $dati['coordinatori'] = array(0, 0, []);
    $dati['docenti'] = array(0, 0, []);
    $dati['genitori'] = array(0, 0, []);
    $dati['alunni'] = array(0, 0, []);
    $dati['classi'] = array(0, 0, []);
    // lettura utenti
    if (count($avviso->getDestinatariAta()) > 0) {
      // dsga/ata
      $utenti = $this->createQueryBuilder('a')
        ->select('ata.tipo,COUNT(au.id) AS tot,COUNT(au.letto) AS letti')
        ->join('App\Entity\AvvisoUtente', 'au', 'WITH', 'au.avviso=a.id')
        ->join('App\Entity\Ata', 'ata', 'WITH', 'ata.id=au.utente')
        ->where('a.id=:avviso')
        ->setParameters(['avviso' => $avviso])
        ->groupBy('ata.tipo')
        ->getQuery()
        ->getArrayResult();
      $ata = array(0, 0, []);
      foreach ($utenti as $u) {
        if ($u['tipo'] == 'D') {
          // dsga
          $dati['dsga'] = array($u['tot'], $u['letti'], []);
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
        ->join('App\Entity\AvvisoUtente', 'au', 'WITH', 'au.avviso=a.id')
        ->join('App\Entity\Ata', 'ata', 'WITH', 'ata.id=au.utente')
        ->where('a.id=:avviso AND au.letto IS NOT NULL')
        ->setParameters(['avviso' => $avviso])
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
        ->join('App\Entity\AvvisoUtente', 'au', 'WITH', 'au.avviso=a.id')
        ->join('App\Entity\Docente', 'd', 'WITH', 'd.id=au.utente')
        ->join('App\Entity\Classe', 'c', 'WITH', 'c.coordinatore=d.id')
        ->where('a.id=:avviso')
        ->setParameters(['avviso' => $avviso])
        ->getQuery()
        ->getArrayResult();
      $dati['coordinatori'] = array($utenti[0]['tot'], $utenti[0]['letti'], []);
      // dati di lettura
      $utenti = $this->createQueryBuilder('a')
        ->select('d.cognome,d.nome,c.anno,c.sezione,c.gruppo,au.letto')
        ->join('App\Entity\AvvisoUtente', 'au', 'WITH', 'au.avviso=a.id')
        ->join('App\Entity\Docente', 'd', 'WITH', 'd.id=au.utente')
        ->join('App\Entity\Classe', 'c', 'WITH', 'c.coordinatore=d.id')
        ->where('a.id=:avviso AND au.letto IS NOT NULL')
        ->setParameters(['avviso' => $avviso])
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
        ->join('App\Entity\AvvisoUtente', 'au', 'WITH', 'au.avviso=a.id')
        ->join('App\Entity\Docente', 'd', 'WITH', 'd.id=au.utente')
        ->where('a.id=:avviso')
        ->setParameters(['avviso' => $avviso])
        ->getQuery()
        ->getArrayResult();
      $dati['docenti'] = array($utenti[0]['tot'], $utenti[0]['letti']);
      // dati di lettura
      $utenti = $this->createQueryBuilder('a')
        ->select('d.cognome,d.nome,au.letto')
        ->join('App\Entity\AvvisoUtente', 'au', 'WITH', 'au.avviso=a.id')
        ->join('App\Entity\Docente', 'd', 'WITH', 'd.id=au.utente')
        ->where('a.id=:avviso AND au.letto IS NOT NULL')
        ->setParameters(['avviso' => $avviso])
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
        ->join('App\Entity\AvvisoUtente', 'au', 'WITH', 'au.avviso=a.id')
        ->join('App\Entity\Genitore', 'g', 'WITH', 'g.id=au.utente')
        ->where('a.id=:avviso')
        ->setParameters(['avviso' => $avviso])
        ->getQuery()
        ->getArrayResult();
      $dati['genitori'] = array($utenti[0]['tot'], $utenti[0]['letti']);
      // dati di lettura
      $utenti = $this->createQueryBuilder('a')
        ->select('al.cognome,al.nome,c.anno,c.sezione,c.gruppo,g.cognome AS cognome_gen,g.nome AS nome_gen,au.letto')
        ->join('App\Entity\AvvisoUtente', 'au', 'WITH', 'au.avviso=a.id')
        ->join('App\Entity\Genitore', 'g', 'WITH', 'g.id=au.utente')
        ->join('g.alunno', 'al')
        ->join('al.classe', 'c')
        ->where('a.id=:avviso AND au.letto IS NOT NULL')
        ->setParameters(['avviso' => $avviso])
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
        ->join('App\Entity\AvvisoUtente', 'au', 'WITH', 'au.avviso=a.id')
        ->join('App\Entity\Alunno', 'al', 'WITH', 'al.id=au.utente')
        ->where('a.id=:avviso')
        ->setParameters(['avviso' => $avviso])
        ->getQuery()
        ->getArrayResult();
      $dati['alunni'] = array($utenti[0]['tot'], $utenti[0]['letti']);
      // dati di lettura
      $utenti = $this->createQueryBuilder('a')
        ->select('al.cognome,al.nome,c.anno,c.sezione,c.gruppo,au.letto')
        ->join('App\Entity\AvvisoUtente', 'au', 'WITH', 'au.avviso=a.id')
        ->join('App\Entity\Alunno', 'al', 'WITH', 'al.id=au.utente')
        ->join('al.classe', 'c')
        ->where('a.id=:avviso AND au.letto IS NOT NULL')
        ->setParameters(['avviso' => $avviso])
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
        ->join('App\Entity\AvvisoClasse', 'ac', 'WITH', 'ac.avviso=a.id')
        ->join('ac.classe', 'cl')
        ->where('a.id=:avviso')
        ->setParameters(['avviso' => $avviso])
        ->getQuery()
        ->getArrayResult();
      if ($classi[0]['tot'] > 0) {
        $dati['classi'] = array($classi[0]['tot'], $classi[0]['letti'], []);
        if ($classi[0]['tot'] > $classi[0]['letti']) {
          // lista classi in cui va letta
          $classi = $this->createQueryBuilder('a')
            ->select("CONCAT(cl.anno,'ª ',cl.sezione) AS nome,cl.gruppo")
            ->join('App\Entity\AvvisoClasse', 'ac', 'WITH', 'ac.avviso=a.id')
            ->join('ac.classe', 'cl')
            ->where('a.id=:avviso AND ac.letto IS NULL')
            ->setParameters(['avviso' => $avviso])
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
    $destinatari = $this->_em->getRepository('App\Entity\AvvisoUtente')->createQueryBuilder('au')
      ->join('au.utente', 'u')
      ->where('au.avviso=:avviso AND au.letto IS NULL')
      ->setParameters(['avviso' => $avviso])
      ->getQuery()
      ->getResult();
    $utenti = [];
    foreach ($destinatari as $dest) {
      $utenti[] = $dest->getUtente();
    }
    // restituisce lista utenti
    return $utenti;
  }

}
