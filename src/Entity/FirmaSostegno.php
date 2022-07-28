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


namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;


/**
 * FirmaSostegno - dati per la firma di una lezione di sostegno
 *
 * @ORM\Entity(repositoryClass="App\Repository\FirmaSostegnoRepository")
 */
class FirmaSostegno extends Firma {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var string|null $argomento Argomento della lezione di sostegno
   *
   * @ORM\Column(type="text", nullable=true)
   */
  private ?string $argomento = '';

  /**
   * @var string|null $attivita Attività della lezione di sostegno
   *
   * @ORM\Column(type="text", nullable=true)
   */
  private ?string $attivita = '';

  /**
   * @var Alunno|null $alunno Alunno della cattedra di sostegno (importante quando più alunni con stesso docente in stessa classe)
   *
   * @ORM\ManyToOne(targetEntity="Alunno")
   * @ORM\JoinColumn(nullable=true)
   */
  private ?Alunno $alunno = null;


  //==================== METODI SETTER/GETTER ====================

  /**
   * Restituisce l'argomento della lezione di sostegno
   *
   * @return string|null Argomento della lezione di sostegno
   */
  public function getArgomento(): ?string {
    return $this->argomento;
  }

  /**
   * Modifica l'argomento della lezione di sostegno
   *
   * @param string|null $argomento Argomento della lezione di sostegno
   *
   * @return self Oggetto modificato
   */
  public function setArgomento(?string $argomento): self {
    $this->argomento = $argomento;
    return $this;
  }

  /**
   * Restituisce le attività della lezione di sostegno
   *
   * @return string|null Attività della lezione di sostegno
   */
  public function getAttivita(): ?string {
    return $this->attivita;
  }

  /**
   * Modifica le attività della lezione di sostegno
   *
   * @param string|null $attivita Attività della lezione di sostegno
   *
   * @return self Oggetto modificato
   */
  public function setAttivita(?string $attivita): self {
    $this->attivita = $attivita;
    return $this;
  }

  /**
   * Restituisce l'alunno della cattedra di sostegno (importante quando più alunni a stesso docente in stessa classe)
   *
   * @return Alunno|null Alunno della cattedra di sostegno
   */
  public function getAlunno(): ?Alunno {
    return $this->alunno;
  }

  /**
   * Modifica l'alunno della cattedra di sostegno (importante quando più alunni con stesso docente in stessa classe)
   *
   * @param Alunno|null $alunno Alunno della cattedra di sostegno
   *
   * @return self Oggetto modificato
   */
  public function setAlunno(?Alunno $alunno): self {
    $this->alunno = $alunno;
    return $this;
  }

}
