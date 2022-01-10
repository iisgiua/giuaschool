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


/**
* Bridge con la libreria SPID-PHP per la richiesta di login SPID
*/

// inizializza
require_once(dirname(__DIR__).'/vendor/italia/spid-php/spid-php.php');
$spidsdk = new SPID_PHP();
// richiede login a IDP e imposta URL per gestire risposta
$spidsdk->login($_GET['idp'], 1, 'https://'.$_SERVER['HTTP_HOST'].'/spid-acs.php', 0);
