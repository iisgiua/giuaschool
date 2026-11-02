<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Exception;

use Exception;


/**
 * InstallException - Errore verificatosi durante una procedura di installazione o aggiornamento
 *
 * Il codice dell'eccezione indica il passo della procedura da cui riprendere
 * (zero se il passo non e' definito).
 *
 * @author Antonello Dessì
 */
class InstallException extends Exception {


}