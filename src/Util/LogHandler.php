<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2020 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2020
 */


namespace App\Util;

use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Log;
use App\Entity\Utente;


/**
 * LogHandler - classe di utilità per l'inserimento dei log
 */
class LogHandler {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var EntityManagerInterface $em Gestore delle entità
   */
  private $em;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Construttore
   *
   * @param EntityManagerInterface $em Gestore delle entità
   */
  public function __construct(EntityManagerInterface $em) {
    $this->em = $em;
  }

  /**
   * Scrive sul database le informazioni di log
   *
   * @param Utente $utente Utente connesso
   * @param string $ip Indirizzo IP dell'utente connesso
   * @param string $categoria Categoria dell'azione dell'utente
   * @param string $azione Azione dell'utente
   * @param string $origine Procedura che ha generato il log (namespace/classe/metodo)
   * @param array $dati Lista di dati che descrivono l'azione
   */
  public function write(Utente $utente, $ip, $categoria, $azione, $origine, $dati) {
    $log = (new Log())
      ->setUtente($utente)
      ->setIp($ip)
      ->setCategoria($categoria)
      ->setAzione($azione)
      ->setOrigine($origine)
      ->setDati($dati);
    $this->em->persist($log);
    $this->em->flush();
  }

}

