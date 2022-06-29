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


namespace App\Repository;

use App\Entity\Avviso;


/**
 * Avviso - repository
 */
class AvvisoRepository extends BaseRepository {

  /**
   * Restituisce le statistiche sulla lettura della circolare
   *
   * @param Avviso $avviso Avviso di cui fare le statistiche di lettura
   *
   * @return array Dati formattati come array associativo
   */
  public function statistiche(Avviso $avviso) {
    $dati = array();
    $dati['ata'] = array(0, 0);
    $dati['dsga'] = array(0, 0);
    $dati['coordinatori'] = array(0, 0);
    $dati['docenti'] = array(0, 0);
    $dati['genitori'] = array(0, 0);
    $dati['alunni'] = array(0, 0);
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
      $ata = array(0, 0);
      foreach ($utenti as $u) {
        if ($u['tipo'] == 'D') {
          $dati['dsga'] = array($u['tot'], $u['letti']);
        } else {
          $ata[0] += $u['tot'];
          $ata[1] += $u['letti'];
        }
      }
      if ($ata[0] > 0) {
        $dati['ata'] = $ata;
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
      $dati['coordinatori'] = array($utenti[0]['tot'], $utenti[0]['letti']);
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
            ->select("CONCAT(cl.anno,'ª ',cl.sezione) AS nome")
            ->join('App\Entity\AvvisoClasse', 'ac', 'WITH', 'ac.avviso=a.id')
            ->join('ac.classe', 'cl')
            ->where('a.id=:avviso AND ac.letto IS NULL')
            ->setParameters(['avviso' => $avviso])
            ->orderBy('cl.anno,cl.sezione', 'ASC')
            ->getQuery()
            ->getArrayResult();
          $dati['classi'][2] = array_column($classi, 'nome');
        }
      }
    }
    // restituisce i dati
    return $dati;
  }

}
