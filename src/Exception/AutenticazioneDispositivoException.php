<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Exception;

use Exception;


/**
 * AutenticazioneDispositivoException - Richiesta di autenticazione di un dispositivo non valida
 *
 * Viene lanciata quando la richiesta di autenticazione e' assente, scaduta
 * oppure gia' stata utilizzata. Estende Exception per mantenere il
 * comportamento dei blocchi di gestione degli errori che fanno rollback
 * della transazione.
 *
 * @author Antonello Dessì
 */
class AutenticazioneDispositivoException extends Exception {


}