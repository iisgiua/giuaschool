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

use \Doctrine\ORM\EntityRepository;


/**
 * Provisioning - repository
 */
class ProvisioningRepository extends EntityRepository {

  /**
   * Restituisce la lista dei comandi in attesa di effettuare il provisioning
   *
   * @return array Lista ID dei comandi in attesa
   */
  public function comandiInAttesa() {
    // legge comandi
    $comandi = $this->createQueryBuilder('p')
      ->select('p.id')
      ->where('p.stato=:attesa')
      ->orderBy('p.id', 'ASC')
      ->setParameters(['attesa' => 'A'])
      ->setMaxResults(20)
      ->getQuery()
      ->getArrayResult();
    // cambia stato ai comandi letti
    $ids = array_column($comandi, 'id');
    $risultato = $this->createQueryBuilder('p')
      ->update()
      ->set('p.modificato', ':ora')
      ->set('p.stato', ':processato')
      ->where('p.id IN (:lista) AND p.stato=:attesa')
      ->setParameters(['ora' => new \DateTime(), 'processato' => 'P', 'lista' => $ids, 'attesa' => 'A'])
      ->getQuery()
      ->getResult();
    // restituisce lista id comandi
    return $ids;
  }

  /**
   * Restituisce la lista dei dati necessari per eseguire il comando
   *
   * @param int $id ID del comando da eseguire
   *
   * @return array Lista dei dati necessari per eseguire il comando
   */
  public function comandoDaEseguire($id) {
    $dati = array();
    // legge comando
    $dati['provisioning'] = $this->find($id);
    if ($dati['provisioning']) {
      foreach ($dati['provisioning']->getDati() as $nm=>$dt) {
        switch ($nm) {
          case 'cattedra':
            $dati[$nm] = $this->_em->getRepository(Cattedra::class)->find($dt);
            break;
          case 'docente':
          case 'docente_prec':
            $dati[$nm] = $this->_em->getRepository(Docente::class)->find($dt);
            break;
          case 'classe':
          case 'classe_prec':
          case 'classe_origine':
          case 'classe_destinazione':
            $dati[$nm] = $this->_em->getRepository(Classe::class)->find($dt);
            break;
          case 'materia':
            $dati[$nm] = $this->_em->getRepository(Materia::class)->find($dt);
            break;
        }
      }
    }
    // restituisce lista dei dati necessari per eseguire il comando
    return $dati;
  }

  /**
   * Riporta nello stato di attesa i comandi non processati
   *
   * @param array $ids Lista ID dei comandi
   */
  public function ripristinaComandi($ids) {
    // cambia stato ai comandi
    $this->createQueryBuilder('p')
      ->update()
      ->set('p.modificato', ':ora')
      ->set('p.stato', ':attesa')
      ->where('p.id IN (:lista) AND p.stato=:processato')
      ->setParameters(['ora' => new \DateTime(), 'attesa' => 'A', 'lista' => $ids, 'processato' => 'P'])
      ->getQuery()
      ->getResult();
  }

  /**
   * Porta nello stato da cancellare il comando eseguito
   *
   * @param int $id ID del comandi
   * @param array $log Lista delle operazioni eseguite correttamente
   */
  public function provisioningEseguito($id, $log) {
    $comando = $this->find($id);
    $dati = $comando->getDati();
    $dati['log'] = $log;
    // cambia stato ai comandi
    $this->createQueryBuilder('p')
      ->update()
      ->set('p.modificato', ':ora')
      ->set('p.stato', ':cancellare')
      ->set('p.dati', ':dati')
      ->where('p.id=:id AND p.stato=:processato')
      ->setParameters(['ora' => new \DateTime(), 'cancellare' => 'C', 'dati' => serialize($dati),
        'id' => $id, 'processato' => 'P'])
      ->getQuery()
      ->getResult();
  }

  /**
   * Porta nello stato di errore il comando non eseguito
   *
   * @param int $id ID del comandi
   * @param array $log Lista delle operazioni eseguite correttamente
   * @param string $errore ID del comandi
   */
  public function provisioningErrato($id, $log, $errore) {
    $comando = $this->find($id);
    $dati = $comando->getDati();
    $dati['log'] = $log;
    $dati['errore'] = $errore;
    // cambia stato ai comandi
    $this->createQueryBuilder('p')
      ->update()
      ->set('p.modificato', ':ora')
      ->set('p.stato', ':errore')
      ->set('p.dati', ':dati')
      ->where('p.id=:id AND p.stato=:processato')
      ->setParameters(['ora' => new \DateTime(), 'errore' => 'E', 'dati' => serialize($dati),
        'id' => $id, 'processato' => 'P'])
      ->getQuery()
      ->getResult();
  }

  /**
   * Elimina i comandi eseguiti il giorno precedente
   *
   */
  public function cancellaComandi() {
    $limite = new \DateTime();
    $limite->modify('-1 day');
    // cambia stato ai comandi
    $this->createQueryBuilder('p')
      ->delete()
      ->where('p.stato=:cancellare AND p.modificato<:limite')
      ->setParameters(['cancellare' => 'C', 'limite' => $limite])
      ->getQuery()
      ->getResult();
  }

}
