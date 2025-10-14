<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Repository;

use DateTime;
use App\Entity\Firma;
use App\Entity\AssenzaLezione;
use \Doctrine\ORM\EntityRepository;
use App\Entity\Docente;
use App\Entity\Classe;
use App\Entity\Materia;
use App\Entity\Alunno;


/**
 * Lezione - repository
 *
 * @author Antonello Dessì
 */
class LezioneRepository extends EntityRepository {

  /**
   * Restituisce le lezioni del docente nella data e cattedra definita (escluso sostegno)
   *
   * @param DateTime $data Data della lezione
   * @param Docente $docente Docente della lezione
   * @param Classe $classe Classe della lezione
   * @param Materia $materia Materia della lezione
   *
   * @return array Restituisce la lista delle lezioni
   */
  public function lezioniVoto(DateTime $data, Docente $docente, Classe $classe, Materia $materia): array {
    // legge lezioni
    $lezioni = $this->createQueryBuilder('l')
      ->join(Firma::class, 'f', 'WITH', 'l.id=f.lezione')
      ->where('l.data=:data AND l.classe=:classe AND l.materia=:materia AND f.docente=:docente')
      ->setParameter('data', $data->format('Y-m-d'))
      ->setParameter('classe', $classe)
      ->setParameter('materia', $materia)
      ->setParameter('docente', $docente)
      ->orderBy('l.ora', 'ASC')
      ->getQuery()
      ->getResult();
    // controlla se ed.civica
    if (count($lezioni) == 0 && $materia->getTipo() == 'E') {
      // legge lezione firmata con altra materia
      $lezioni = $this->createQueryBuilder('l')
        ->join(Firma::class, 'f', 'WITH', 'l.id=f.lezione')
        ->where('l.data=:data AND l.classe=:classe AND f.docente=:docente')
        ->setParameter('data', $data->format('Y-m-d'))
        ->setParameter('classe', $classe)
        ->setParameter('docente', $docente)
        ->orderBy('l.ora', 'ASC')
        ->getQuery()
        ->getResult();
    }
    // restituisce lezione o null
    return $lezioni;
  }

  /**
   * Restituisce vero se l'alunno è assente alla lezione, falso altrimenti
   *
   * @param array $lezioni Lista di lezioni da controllare
   * @param Alunno $alunno Alunno di cui controllare la presenza alla lezione
   *
   * @return bool Restituisce vero se l'alunno è assente, falso altrimenti
   */
  public function assenteLezioni(array $lezioni, Alunno $alunno): bool {
    // legge assenza di alunno
    $ore = $this->createQueryBuilder('l')
      ->select('SUM(al.ore)')
      ->join(AssenzaLezione::class, 'al', 'WITH', 'al.lezione=l.id AND al.alunno=:alunno')
      ->where('l.id IN (:lezioni) AND al.ore=1')
      ->setParameter('alunno', $alunno)
      ->setParameter('lezioni', array_map(fn($l) => $l->getId(), $lezioni))
      ->getQuery()
      ->getSingleScalarResult();
    // restituisce vero se l'alunno è assente per le intere ore di lezione
    return $ore == count($lezioni);
  }

  /**
   * Restituisce la lista degli alunni assenti alle lezioni
   *
   * @param array $lezioni Lista di lezioni da controllare
   * @param array $alunni Lista ID degli alunni di cui controllare la presenza alle lezioni
   *
   * @return array Lista dei nomi degli alunni assenti
   */
  public function assentiLezioni(array $lezioni, array $alunni): array {
    // legge assenza di alunni
    $ore = $this->createQueryBuilder('l')
      ->select('a.id,a.nome,a.cognome,a.dataNascita,SUM(al.ore) AS ore')
      ->join(AssenzaLezione::class, 'al', 'WITH', 'al.lezione=l.id AND al.alunno IN (:alunni)')
      ->join('al.alunno', 'a')
      ->where('l.id IN (:lezioni) AND al.ore=1')
      ->groupBy('a.id,a.nome,a.cognome,a.dataNascita')
      ->setParameter('alunni', $alunni)
      ->setParameter('lezioni', array_map(fn($l) => $l->getId(), $lezioni))
      ->getQuery()
      ->getArrayResult();
    // restituisce alunni assenti per tutte le ore di lezione
    $assenti = [];
    foreach ($ore as $o) {
      if ($o['ore'] == count($lezioni)) {
        $assenti[] = $o;
      }
    }
    return $assenti;
  }

}
