<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Message;


/**
 * UtenteActionMessage - dati per le azioni sugli utenti
 *
 * @author Antonello Dessì
 */
class UtenteActionMessage extends GenericActionMessage {

  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param int $id Identificativo dell'utente
   * @param string $class Nome della classe di riferimento
   * @param string $action Nome che identifica l'azione eseguita
   * @param array $data Dati aggiuntivi
   */
  public function __construct(int $id, string $class, string $action, array $data=[]) {
    // definizione lista azioni previste
    GenericActionMessage::$list['Docente']['add'] = null;
    GenericActionMessage::$list['Docente']['addCattedra'] = 'Cattedra';
    GenericActionMessage::$list['Docente']['removeCattedra'] = 'Cattedra';
    GenericActionMessage::$list['Docente']['addCoordinatore'] = 'Classe';
    GenericActionMessage::$list['Docente']['removeCoordinatore'] = 'Classe';
    GenericActionMessage::$list['Alunno']['add'] = null;
    GenericActionMessage::$list['Alunno']['addClasse'] = 'Classe';
    GenericActionMessage::$list['Alunno']['removeClasse'] = 'Classe';
    GenericActionMessage::$list['Ata']['add'] = null;
    // costruttore
    parent::__construct($id, $class, $action, $data);
  }

}
