<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Exception;

use Exception;


/**
 * InstallAuthException - Errore di autenticazione durante una procedura di installazione o aggiornamento
 *
 * Viene usata per distinguere il fallimento del controllo del token (codice di sicurezza)
 * dagli errori operativi della procedura, cosi' da poter rispondere con un 403 senza
 * divulgare il token corretto nei collegamenti di ripetizione.
 *
 * @author Antonello Dessì
 */
class InstallAuthException extends Exception {

}
