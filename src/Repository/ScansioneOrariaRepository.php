<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Repository;

use DateTime;
use App\Entity\Cattedra;
use Doctrine\ORM\EntityRepository;
use App\Entity\Sede;
use App\Entity\Docente;
use App\Entity\Lezione;
use App\Entity\Orario;
use App\Entity\ScansioneOraria;


/**
 * ScansioneOraria - repository
 *
 * @author Antonello Dessì
 */
class ScansioneOrariaRepository extends EntityRepository {

  /**
   * Restituisce l'ora di inizio delle lezioni per il giorno indicato (non festivo)
   *
   * @param DateTime $data Data di riferimento
   * @param Sede $sede Sede scolastica
   *
   * @return string Ora nel formato hh:mm
   */
  public function inizioLezioni(DateTime $data, Sede $sede): string {
    // legge la prima ora
    $ora = $this->createQueryBuilder('s')
      ->select('s.inizio')
      ->join('s.orario', 'o')
      ->where(':data BETWEEN o.inizio AND o.fine AND o.sede=:sede AND s.giorno=:giorno AND s.ora=:ora')
			->setParameter('data', $data->format('Y-m-d'))
			->setParameter('sede', $sede)
			->setParameter('giorno', $data->format('w'))
			->setParameter('ora', 1)
      ->setMaxResults(1)
      ->getQuery()
      ->getScalarResult();
    return (!empty($ora) ? substr((string) $ora[0]['inizio'], 0, 5) : '00:00');
  }

  /**
   * Restituisce l'ora di fine delle lezioni per il giorno indicato (non festivo)
   *
   * @param DateTime $data Data di riferimento
   * @param Sede $sede Sede scolastica
   *
   * @return string Ora nel formato hh:mm
   */
  public function fineLezioni(DateTime $data, Sede $sede): string {
    // legge la ultima ora
    $ora = $this->createQueryBuilder('s')
      ->select('s.fine')
      ->join('s.orario', 'o')
      ->where(':data BETWEEN o.inizio AND o.fine AND o.sede=:sede AND s.giorno=:giorno')
			->setParameter('data', $data->format('Y-m-d'))
			->setParameter('sede', $sede)
			->setParameter('giorno', $data->format('w'))
      ->orderBy('s.ora', 'DESC')
      ->setMaxResults(1)
      ->getQuery()
      ->getScalarResult();
    return (!empty($ora) ? substr((string) $ora[0]['fine'], 0, 5) : '23:59');
  }

  /**
   * Restituisce l'ora di fine delle lezioni per il docente nel giorno indicato (non festivo)
   *
   * @param DateTime $data Data di riferimento
   * @param Docente $docente Docente
   *
   * @return string Ora nel formato hh:mm
   */
  public function fineLezioniDocente(DateTime $data, Docente $docente): string {
    // legge la ultima ora
    $ora = $this->createQueryBuilder('s')
      ->select('s.fine')
      ->join('s.orario', 'o')
      ->join(Cattedra::class, 'c', 'WITH', 'c.docente=:docente')
      ->join('c.classe', 'cl')
      ->where(':data BETWEEN o.inizio AND o.fine AND o.sede=cl.sede AND s.giorno=:giorno')
			->setParameter('docente', $docente)
			->setParameter('data', $data->format('Y-m-d'))
			->setParameter('giorno', $data->format('w'))
      ->orderBy('s.fine', 'DESC')
      ->setMaxResults(1)
      ->getQuery()
      ->getScalarResult();
    return (!empty($ora) ? substr((string) $ora[0]['fine'], 0, 5) : '23:59');
  }

  /**
   * Restituisce i dati della scansione oraria di una lezione
   *
   * @param Lezione $lezione Lezione di cui leggere la scansione oraria
   *
   * @return ScansioneOraria Oggetto che rappresenta la scansione oraria
   */
  public function oraLezione(Lezione $lezione) {
    // legge l'ora della lezione
    $ora = $this->createQueryBuilder('s')
      ->join('s.orario', 'o')
      ->where(':data BETWEEN o.inizio AND o.fine AND o.sede=:sede AND s.giorno=:giorno AND s.ora=:ora')
			->setParameter('data', $lezione->getData()->format('Y-m-d'))
			->setParameter('sede', $lezione->getClasse()->getSede())
			->setParameter('giorno', $lezione->getData()->format('w'))
			->setParameter('ora', $lezione->getOra())
      ->setMaxResults(1)
      ->getQuery()
      ->getOneOrNullResult();
    return $ora;
  }

  /**
   * Restituisce i dati della scansione oraria di una un giorno e un orario specificato
   *
   * @param int $giorno Giorno settimnale (0=domenica, 1=lunedì, ...)
   * @param Orario $orario Orario a cui fare riferimento; se nullo si prende quello attuale della sede principale
   *
   * @return array Array che rappresenta la scansione oraria
   */
  public function orarioGiorno($giorno, Orario $orario=null) {
    if (!$orario) {
      $orario = $this->getEntityManager()->getRepository(Orario::class)->orarioSede(null);
    }
    // legge le ore del giorno
    $ore = $this->createQueryBuilder('s')
      ->select('s.ora,s.inizio,s.fine')
      ->where('s.orario=:orario AND s.giorno=:giorno')
      ->orderBy('s.ora', 'ASC')
			->setParameter('orario', $orario)
			->setParameter('giorno', $giorno)
      ->getQuery()
      ->getArrayResult();
    return $ore;
  }

  /**
   * Restituisce i dati della scansione oraria completa per un orario specificato.
   *
   * @param Orario $orario Orario a cui fare riferimento
   *
   * @return array Array associativo con la scansione oraria
   */
  public function orario(Orario $orario) {
    $dati = [1 => [], 2 => [], 3 => [], 4 => [], 5 => [], 6 => []];
    // legge le ore
    $ore = $this->createQueryBuilder('s')
      ->where('s.orario=:orario')
      ->orderBy('s.giorno,s.ora', 'ASC')
			->setParameter('orario', $orario)
      ->getQuery()
      ->getResult();
    foreach ($ore as $so) {
      $dati[$so->getGiorno()][$so->getOra()] = $so;
    }
    // riempe il resto con dati fittizi (ora = 0)
    for ($giorno = 1; $giorno <= 6; $giorno++) {
      for ($ora = count($dati[$giorno]) + 1; $ora <= 10; $ora++) {
        $dati[$giorno][$ora] = (new ScansioneOraria())
          ->setOrario($orario)
          ->setGiorno($giorno)
          ->setOra(0)
          ->setInizio(DateTime::createFromFormat('H:i', '08:30'))
          ->setFine(DateTime::createFromFormat('H:i', '09:30'))
          ->setDurata(1);
        $this->getEntityManager()->persist($dati[$giorno][$ora]);
      }
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Restituisce i dati della scansione oraria completa per una data sede.
   *
   * @param int $id Identificativo della sede
   *
   * @return array Dati formattati come array associativo
   */
  public function orarioSede(int $id): array {
    $dati = [];
    // legge le ore
    $ore = $this->createQueryBuilder('so')
      ->join(Orario::class, 'o', 'WITH', 'so.orario=o.id')
      ->where(':data BETWEEN o.inizio AND o.fine AND o.sede=:sede')
			->setParameter('data', (new DateTime())->format('Y-m-d'))
			->setParameter('sede', $id)
      ->getQuery()
      ->getResult();
    foreach ($ore as $so) {
      $dati[$so->getGiorno()][$so->getOra()] = $so;
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Restituisce l'ora di inizio e di fine delle lezioni per il giorno indicato (non festivo)
   *
   * @param DateTime $data Data di riferimento
   * @param array $sedi Lista degli ID delle sedi da considerare
   *
   * @return array Dati con chiavi 'inizio' e 'fine'
   */
  public function inizioFineLezioni(DateTime $data, array $sedi): array {
    // legge la ultima ora
    $ore = $this->createQueryBuilder('s')
      ->select('MAX(s.fine) AS fine, MIN(s.inizio) AS inizio')
      ->join('s.orario', 'o')
      ->where(':data BETWEEN o.inizio AND o.fine AND o.sede IN (:sedi) AND s.giorno=:giorno')
			->setParameter('data', $data->format('Y-m-d'))
			->setParameter('sedi', $sedi)
			->setParameter('giorno', $data->format('w'))
      ->getQuery()
      ->getScalarResult();
    // restituisce dati
    return $ore[0];
  }

}
