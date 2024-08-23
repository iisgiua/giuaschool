<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Message;


/**
 * GenericActionMessage - gestione dei messaggi per le azioni eseguite (classe base)
 *
 * @author Antonello Dessì
 */
class GenericActionMessage {

  /**
   * @var string $tag Testo usato per identificare l'azione
   */
  private string $tag;

  /**
   * @var array $list Lista delle azioni permesse: $list[nomeClasse][nomeAzione] = null|nomeAltraClasse
   */
  protected static array $list = [];


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param int $id Identificativo dell'avviso da notificare
   * @param string $class Nome della classe di riferimento
   * @param string $action Nome che identifica l'azione eseguita
   * @param array $data Dati aggiuntivi
   */
  public function __construct(
      private int $id,
      private string $class,
      private string $action,
      private array $data) {
    $this->tag = '<!AZIONE!><!'.$this->class.'.'.$this->action.'.'.$this->id.'!>';
    if (!$this->check()) {
      // errore: azione non prevista
      throw new \Exception('Undefined action in message constructor: "'.$this->class.'.'.$this->action.'"');
    }
  }

  /**
   * Restituisce l'identificativo dell'istanza di riferimento
   *
   * @return int Identificativo dell'istanza di riferimento
   */
  public function getId(): int {
    return $this->id;
  }

  /**
   * Restituisce il nome della classe di riferimento
   *
   * @return string Nome della classe di riferimento
   */
  public function getClass(): string {
    return $this->class;
  }

  /**
   * Restituisce il nome che identifica l'azione eseguita
   *
   * @return string Nome che identifica l'azione eseguita
   */
  public function getAction(): string {
    return $this->action;
  }

  /**
   * Restituisce i dati aggiuntivi
   *
   * @return array Dati aggiuntivi
   */
  public function getData(): array {
    return $this->data;
  }

  /**
   * Restituisce l'identificativo completo dell'azione
   *
   * @return string Tag identificativo completo dell'azione
   */
  public function getTag(): string {
    return $this->tag;
  }

  /**
   * Controlla che l'azione sia tra quelle previste
   *
   * @return bool Vero se l'azione è tra quelle previste
   */
  public function check(): bool {
    if (!array_key_exists($this->class, GenericActionMessage::$list) ||
        !array_key_exists($this->action, GenericActionMessage::$list[$this->class])) {
      // l'azione indicata non è presente
      return false;
    }
    $other = GenericActionMessage::$list[$this->class][$this->action];
    if ($other && empty($this->data[$other])) {
      // l'altra istanza prevista non esiste
      return false;
    }
    // tutto ok
    return true;
  }

}
