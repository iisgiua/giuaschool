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

use Doctrine\ORM\EntityRepository;
use App\Entity\Classe;


/**
 * PropostaVoto - repository
 */
class PropostaVotoRepository extends EntityRepository {

  /**
   * Restituisce la lista delle proposte di voto di Ed.Civica per gli alunni indicati
   *
   * @param Classe $classe Classe dello scrutinio
   * @param string $periodo Periodo dello scrutinio
   * @param array $alunni Lista degli ID degli alunni di cui caricare le proposte di voto
   *
   * @return array Array associativo con i dati delle proposte di voto
   */
  public function proposteEdCivica(Classe $classe, $periodo, $alunni) {
    // legge proposte
    $proposte = $this->createQueryBuilder('pv')
      ->select('(pv.alunno) AS id_alunno,pv.unico,pv.debito,pv.recupero,d.cognome,d.nome')
      ->join('pv.materia', 'm')
      ->join('pv.docente', 'd')
      ->where('pv.classe=:classe AND pv.periodo=:periodo AND pv.unico IS NOT NULL AND pv.alunno IN (:lista) AND m.tipo=:edcivica')
      ->setParameters(['classe' => $classe, 'periodo' => $periodo, 'lista' => $alunni, 'edcivica' => 'E'])
      ->orderBY('d.cognome,d.nome')
      ->getQuery()
      ->getArrayResult();
    // formatta i dati
    $dati = array();
    foreach ($proposte as $prop) {
      // proposta di voto di un alunno
      $docente = $prop['nome'].' '.$prop['cognome'];
      $dati[$prop['id_alunno']]['proposte'][$docente] = $prop['unico'];
      // aggiunge eventuali argomenti da recuperare
      if ($prop['unico'] < 6 && $prop['debito']) {
        $dati[$prop['id_alunno']]['debito'] = (isset($dati[$prop['id_alunno']]['debito']) ?
          ($dati[$prop['id_alunno']]['debito'].' ') : '').$prop['debito'];
      }
      // somma voti per media
      $dati[$prop['id_alunno']]['media'] = (isset($dati[$prop['id_alunno']]['media']) ?
        $dati[$prop['id_alunno']]['media'] : 0) + ($prop['unico'] == 3 ? 0 : $prop['unico']);
    }
    // calcola medie
    foreach ($dati as $id_alunno=>$prop) {
      $dati[$id_alunno]['media'] = $dati[$id_alunno]['media'] / count($prop['proposte']);
    }
    // restituisce dati
    return $dati;
  }

}
