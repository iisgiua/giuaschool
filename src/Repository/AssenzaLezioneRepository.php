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

use App\Entity\Alunno;
use App\Entity\Classe;
use App\Entity\Lezione;


/**
 * AssenzaLezione - repository
 */
class AssenzaLezioneRepository extends BaseRepository {

  /**
   * Elimina le ore di assenza dell'alunno nel periodo indicato
   *
   * @param Alunno $alunno Alunno di cui si vogliono eliminare le assenze
   * @param \DateTime $inizio Data di inizio
   * @param \DateTime $fine Data di fine
   */
  public function elimina(Alunno $alunno, \DateTime $inizio, \DateTime $fine) {
    // recupera id
    $ids = $this->createQueryBuilder('al')
      ->select('al.id')
      ->join('al.lezione', 'l')
      ->where('al.alunno=:alunno AND l.data BETWEEN :inizio AND :fine')
      ->setParameters(['alunno' => $alunno, 'inizio' => $inizio->format('Y-m-d'), 'fine' => $fine->format('Y-m-d')])
      ->getQuery()
      ->getArrayResult();
    // cancella
    $this->createQueryBuilder('al')
      ->delete()
      ->where('al.id IN (:lista)')
      ->setParameters(['lista' => $ids])
      ->getQuery()
      ->execute();
  }

  /**
   * Restituisce la lista alunni degli assenti nell'ora precedente
   *
   * @param Classe $classe Classe della lezione
   * @param \DateTime $data Data della lezione
   * @param int $ora Ora della lezione
   *
   * @return array Lista degli ID degli alunni assenti
   */
  public function assentiLezionePrecedente(Classe $classe, \DateTime $data, $ora) {
    $lista = array();
    if ($ora > 1) {
      // recupera lezione precedente
      $lezione = $this->_em->getRepository(Lezione::class)->createQueryBuilder('l')
        ->where('l.classe=:classe AND l.data=:data AND l.ora<:ora')
        ->orderBy('l.ora', 'DESC')
        ->setParameters(['classe' => $classe, 'data' => $data->format('Y-m-d'), 'ora' => $ora])
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();
      if ($lezione) {
        // recupera alunni assenti
        $lista = $this->_em->getRepository(Alunno::class)->createQueryBuilder('a')
          ->join('App:AssenzaLezione', 'al', 'WITH', 'al.alunno=a.id')
          ->join('al.lezione', 'l')
          ->where('l.id=:lezione')
          ->setParameters(['lezione' => $lezione])
          ->getQuery()
          ->getResult();
      }
    }
    // restituisce assenti
    return $lista;
  }

  /**
   * Restituisce la lista degli alunni assenti nella lezione indicata
   *
   * @param Lezione $lezione Lezione di riferimento
   *
   * @return array Lista degli ID degli alunni assenti
   */
  public function assentiLezione(Lezione $lezione) {
    // recupera alunni assenti
    $lista = $this->_em->getRepository(Alunno::class)->createQueryBuilder('a')
      ->join('App:AssenzaLezione', 'al', 'WITH', 'al.alunno=a.id')
      ->join('al.lezione', 'l')
      ->where('l.id=:lezione')
      ->setParameters(['lezione' => $lezione])
      ->getQuery()
      ->getResult();
    // restituisce assenti
    return $lista;
  }

  /**
   * Restituisce la lista degli alunni assenti solo alla lezione indicata (nello stesso giorno)
   *
   * @param Lezione $lezione Lezione di cui leggere gli assenti
   *
   * @return array Lista degli assenti
   */
  public function assentiSoloLezione(Lezione $lezione) {
    // assenti in altre lezioni
    $altre = $this->createQueryBuilder('al2')
      ->join('al2.lezione', 'l2')
      ->where('al2.alunno=al.alunno AND l2.id!=:lezione AND l2.data=l.data')
      ->getDQL();
    // recupera alunni assenti
    $assenti = $this->_em->getRepository(Alunno::class)->createQueryBuilder('a')
      ->join('App:AssenzaLezione', 'al', 'WITH', 'al.alunno=a.id')
      ->join('al.lezione', 'l')
      ->where('l.id=:lezione AND NOT EXISTS ('.$altre.')')
      ->setParameters(['lezione' => $lezione])
      ->getQuery()
      ->getResult();
    // restituisce assenti
    return $assenti;
  }

  /**
   * Restituisce la lista degli alunni assenti, per ogni ora, nel giorno indicato
   *
   * @param CLasse $classe Classe delle lezioni
   * @param \DateTime $data Giorno delle lezioni
   *
   * @return array Lista degli alunni assenti, per ogni ora
   */
  public function assentiOre(CLasse $classe, \DateTime $data) {
    $assenti = array('nomi' => [], 'id' => []);
    // recupera alunni assenti
    $lista = $this->createQueryBuilder('al')
      ->select('l.ora,a.id,a.cognome,a.nome')
      ->join('al.alunno', 'a')
      ->join('al.lezione', 'l')
      ->where('l.data=:data AND l.classe=:classe')
      ->orderBy('l.ora,a.cognome,a.nome,a.dataNascita', 'ASC')
      ->setParameters(['data' => $data->format('Y-m-d'), 'classe' => $classe])
      ->getQuery()
      ->getArrayResult();
    foreach ($lista as $l) {
      $assenti['nomi'][$l['ora']][] = $l['cognome'].' '.$l['nome'];
      $assenti['id'][$l['ora']][] = $l['id'];
    }
    // restituisce assenti
    return $assenti;
  }

  /**
   * Restituisce la lista delle ore di assenza di un alunno in una certa data
   *
   * @param Alunno $alunno Alunno di cui recuperare le assenze
   * @param \DateTime $data Giorno delle lezioni
   *
   * @return array Lista delle ore di assenza
   */
  public function alunnoOreAssenze(Alunno $alunno, \DateTime $data) {
    // ore di assenza
    $lista = $this->createQueryBuilder('al')
      ->select('l.ora')
      ->join('al.alunno', 'a')
      ->join('al.lezione', 'l')
      ->where('a.id=:alunno AND l.data=:data')
      ->orderBy('l.ora', 'ASC')
      ->setParameters(['alunno' => $alunno, 'data' => $data->format('Y-m-d')])
      ->getQuery()
      ->getArrayResult();
    $ore = array_column($lista, 'ora');
    // restituisce assenze
    return $ore;
  }

}
