<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


use App\Util\EnvFile;

// carica la configurazione dell'ambiente prima di avviare il kernel
require_once dirname(__DIR__).'/src/Util/EnvFile.php';
EnvFile::carica(dirname(__DIR__));

// il runtime Symfony non deve cercare il vecchio ".env": i file sono già stati caricati
$opzioniRuntime = $_SERVER['APP_RUNTIME_OPTIONS'] ?? $_ENV['APP_RUNTIME_OPTIONS'] ?? [];
if (!is_array($opzioniRuntime)) {
  $opzioniRuntime = [];
}
$opzioniRuntime['disable_dotenv'] = true;
$_SERVER['APP_RUNTIME_OPTIONS'] = $_ENV['APP_RUNTIME_OPTIONS'] = $opzioniRuntime;
