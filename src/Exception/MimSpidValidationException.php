<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Exception;

use RuntimeException;


/**
 * MimSpidValidationException - Errore di validazione dell'ID token MIM-SPID
 *
 * Estende RuntimeException, cosi' da mantenere il comportamento previsto dai
 * contratti e dai controlli che trattano gli errori di validazione come
 * RuntimeException.
 *
 * @author Antonello Dessì
 */
class MimSpidValidationException extends RuntimeException {


}