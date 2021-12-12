<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2021 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2021
 */


namespace App\Repository;

use \Doctrine\ORM\EntityRepository;
use App\Entity\Docente;
use App\Entity\Classe;
use App\Entity\Materia;
use App\Entity\Lezione;
use App\Entity\Alunno;


/**
 * Lezione - repository
 */
class LezioneRepository extends EntityRepository {

  /**
   * Restituisce la lezione del docente nella data e cattedra definita (escluso sostegno)
   *
   * @param \DateTime $data Data della lezione
   * @param Docente $docente Docente della lezione
   * @param Classe $classe Classe della lezione
   * @param Materia $materia Materia della lezione
   *
   * @return int|null Restituisce l'identificatore della lezione o null se non trovata
   */
  public function lezioneVoto(\DateTime $data, Docente $docente, Classe $classe, Materia $materia) {
    // query base
    $lezione = $this->createQueryBuilder('l')
      ->join('App:Firma', 'f', 'WITH', 'l.id=f.lezione')
      ->where('l.data=:data AND l.classe=:classe AND f.docente=:docente AND l.materia=:materia')
      ->setParameters(['data' => $data->format('Y-m-d'), 'docente' => $docente, 'classe' => $classe,
        'materia' => $materia])
      ->orderBy('l.ora', 'ASC')
      ->setMaxResults(1)
      ->getQuery()
      ->getOneOrNullResult();
    // controlla se ed.civica
    if (!$lezione && $materia->getTipo() == 'E') {
      // legge lezione firmata con altra materia
      $lezione = $this->createQueryBuilder('l')
        ->join('App:Firma', 'f', 'WITH', 'l.id=f.lezione')
        ->where('l.data=:data AND l.classe=:classe AND f.docente=:docente')
        ->setParameters(['data' => $data->format('Y-m-d'), 'docente' => $docente, 'classe' => $classe])
        ->orderBy('l.ora', 'ASC')
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();
    }
    // restituisce lezione o null
    return $lezione;
  }

  /**
   * Restituisce vero se l'alunno è assente alla lezione, falso altrimenti
   *
   * @param Lezione $lezione Lezione da controllare
   * @param Alunno $alunno Alunno di cui controllare la presenza alla lezione
   *
   * @return bool Restituisce vero se l'alunno è assente, falso altrimenti
   */
  public function alunnoAssente(Lezione $lezione, Alunno $alunno) {
    // legge assenza di alunno
    $assenza = $this->createQueryBuilder('l')
      ->select('al.ore')
      ->join('App:AssenzaLezione', 'al', 'WITH', 'al.lezione=l.id AND al.alunno=:alunno')
      ->where('l.id=:lezione AND al.ore=:ora')
      ->setParameters(['alunno' => $alunno, 'lezione' => $lezione, 'ora' => 1])
      ->setMaxResults(1)
      ->getQuery()
      ->getOneOrNullResult();
    // restituisce vero se l'alunno è assente per l'intera ora di lezione
    return ($assenza !== null);
  }

  /**
   * Restituisce la lista degli alunni assenti alla lezione
   *
   * @param Lezione $lezione Lezione da controllare
   * @param array $alunni Lista ID degli alunni di cui controllare la presenza alla lezione
   *
   * @return array Lista dei nomi degli alunni assenti
   */
  public function alunniAssenti(Lezione $lezione, $alunni) {
    // legge assenza di alunno
    $assenti = $this->createQueryBuilder('l')
      ->select('a.nome,a.cognome,a.dataNascita')
      ->join('App:AssenzaLezione', 'al', 'WITH', 'al.lezione=l.id AND al.alunno IN (:alunni)')
      ->join('al.alunno', 'a')
      ->where('l.id=:lezione AND al.ore=:ora')
      ->setParameters(['alunni' => $alunni, 'lezione' => $lezione, 'ora' => 1])
      ->getQuery()
      ->getArrayResult();
    // restituisce alunni assenti
    return $assenti;
  }

}
