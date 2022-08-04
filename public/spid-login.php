<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


/**
* Bridge con la libreria SPID-PHP per la richiesta di login SPID
*/

// inizializza
require_once(dirname(__DIR__).'/vendor/italia/spid-php/spid-php.php');
$spidsdk = new SPID_PHP();
// richiede login a IDP e imposta URL per gestire risposta
$spidsdk->login($_GET['idp'], 1, 'https://'.$_SERVER['HTTP_HOST'].'/spid-acs.php', 0);
