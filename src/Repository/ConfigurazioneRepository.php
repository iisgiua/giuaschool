<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Repository;

use Doctrine\ORM\EntityRepository;


/**
 * Configurazione - repository
 *
 * @author Antonello Dessì
 */
class ConfigurazioneRepository extends EntityRepository {

  /**
   * Restituisce il valore presente nella configurazione del parametro indicato
   *
   * @param string $nome Nome del parametro
   * @param mixed $default Valore di default nel caso il parametro non esista
   *
   * @return string Valore del parametro letto
   */
  public function getParametro($nome, mixed $default=null) {
    // legge valore parametro
    $parametro = $this->createQueryBuilder('c')
      ->select('c.valore')
      ->where('c.parametro=:nome')
      ->setParameter('nome', $nome)
      ->getQuery()
      ->getOneOrNullResult();
    // restituisce valore
    return ($parametro['valore'] ?? $default);
  }

  /**
   * Restituisce il valore presente nella configurazione del parametro indicato
   *
   * @param string $nome Nome del parametro
   * @param string $valore Valore del parametro
   */
  public function setParametro($nome, $valore) {
    // legge valore parametro
    $risultato = $this->createQueryBuilder('c')
      ->update()
      ->set('c.valore', ':valore')
      ->where('c.parametro=:nome')
      ->setParameter('nome', $nome)
      ->setParameter('valore', $valore)
      ->getQuery()
      ->getResult();
  }

  /**
   * Restituisce i parametri e i relativi valori da caricare in sessione
   *
   * @return array Lista dei parametri e dei valori
   */
  public function load() {
    $parametri = $this->createQueryBuilder('c')
      ->select('c.categoria,c.parametro,c.valore')
      ->getQuery()
      ->getArrayResult();
    // restituisce valori
    return $parametri;
  }

  /**
   * Restituisce i parametri per la configurazione dell'applicazione
   *
   * @return array Lista dei parametri di configurazione
   */
  public function parametriConfigurazione() {
    $parametri = $this->createQueryBuilder('c')
      ->where('c.gestito=:gestito')
      ->setParameter('gestito', 0)
      ->orderBy('c.categoria,c.parametro', 'ASC')
      ->getQuery()
      ->getResult();
    // restituisce valori
    return $parametri;
  }

  /**
   * Restituisce la lista dei periodi configurati per gli scrutini
   *
   * @return array Lista dei dati come array associativo
   */
  public function infoScrutini(): array {
    $lista = [];
    // legge nomi periodi
    $parametro1 = $this->findOneByParametro('periodo1_nome');
    $parametro2 = $this->findOneByParametro('periodo2_nome');
    $parametro3 = $this->findOneByParametro('periodo3_nome');
    $lista['P'] = $parametro1 ? $parametro1->getValore() : 'P';
    if (!$parametro3 || empty($parametro3->getValore())) {
      // solo 2 periodi (2 quadrimestri o trimestre+pentamestre)
      $lista['F'] = $parametro2 ? $parametro2->getValore() : 'F';
    } else {
      // 3 periodi (3 trimestri)
      $lista['S'] = $parametro2 ? $parametro2->getValore() : 'S';
      $lista['F'] = $parametro3->getValore();
    }
    // restituisce dati
    return $lista;
  }

}
