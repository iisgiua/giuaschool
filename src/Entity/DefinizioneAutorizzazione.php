<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Entity;

use App\Repository\DefinizioneAutorizzazioneRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;


/**
 * DefinizioneAutorizzazione - dati per la definizione di una autorizzazione
 *
 * @author Antonello Dessì
 */
#[ORM\Entity(repositoryClass: DefinizioneAutorizzazioneRepository::class)]
class DefinizioneAutorizzazione extends DefinizioneConsultazione {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var array|null $dati Lista di informazioni per la gestione delle autorizzazioni
   */
  #[ORM\Column(type: Types::JSON, nullable: true)]
  private ?array $dati = [];


  //==================== METODI SETTER/GETTER ====================

  /**
   * Restituisce la lista di informazioni per la gestione delle autorizzazioni
   *
   * @return array|null Lista di informazioni per la gestione delle autorizzazioni
   */
  public function getDati(): ?array {
    return $this->dati;
  }

  /**
   * Modifica la lista di informazioni per la gestione delle autorizzazioni
   *
   * @param array $dati Lista di informazioni per la gestione delle autorizzazioni
   *
   * @return self Oggetto modificato
   */
  public function setDati(array $dati): self {
    $this->dati = $dati;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   */
  public function __construct() {
    // inizializzazione
    $this->setTipo('A');
    $this->setUnica(true);
    $this->setGestione(false);
    $this->setRichiedenti('GN,AM');
    $this->setModulo('definizione_autorizzazione.html.twig');
    $this->setDestinatari('PN,SN');
    $this->setCampi([]);
    $this->setAllegati(0);
  }

    /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString(): string {
    return 'Autorizzazione: '.$this->getNome();
  }

}
