<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Repository;

use App\Entity\Classe;
use App\Entity\Comunicazione;
use DateTime;


/**
 * ComunicazioneClasse - repository
 *
 * @author Antonello Dessì
 */
class ComunicazioneClasseRepository extends BaseRepository {

  /**
   * Conferma la lettura della comunicazione alla classe
   *
   * @param Classe $classe Classe a cui è stata letta la circolare
   * @param Comunicazione $comunicazione Comunicazione da firmare
   *
   * @return bool Vero se inserita conferma di lettura, falso altrimenti
   */
  public function firmaClasse(Classe $classe, Comunicazione $comunicazione): bool {
    // dati classe destinataria
    $cc = $this->findOneBy(['comunicazione' => $comunicazione, 'classe' => $classe]);
    if ($cc && !$cc->getLetto()) {
      // imposta conferma di lettura
      $ora = new DateTime();
      $cc->setLetto($ora);
      // memorizza dati
      $this->getEntityManager()->flush();
      // conferma inserita
      return true;
    }
    // conferma non inserita
    return false;
  }

  /**
   * Restituisce la lista delle classi associate ad una data comunicazione
   *
   * @param Comunicazione $comunicazione Comunicazione da controllare
   *
   * @return array Lista delle classi come testo leggibile
   */
  public function classiComunicazione(Comunicazione $comunicazione): array {
    $dati = [];
    // legge classi
    $classi = $this->createQueryBuilder('cc')
      ->select('cl.anno,cl.sezione,cl.gruppo')
      ->join('cc.classe', 'cl')
      ->where("cc.comunicazione=:comunicazione")
      ->setParameter('comunicazione', $comunicazione)
      ->orderBy('cl.anno,cl.sezione,cl.gruppo', 'ASC')
      ->getQuery()
      ->getArrayResult();
    // crea lista
    foreach ($classi as $classe) {
      $dati[] = $classe['anno'].$classe['sezione'].($classe['gruppo'] ? '-'.$classe['gruppo'] : '');
    }
    // restituisce classi
    return $dati;
  }

}
