<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


/**
* Bridge con la libreria SPID-PHP per gestire la risposta a una richiesta di login SPID
*/

// inizializza
require_once(dirname(__DIR__).'/vendor/italia/spid-php/spid-php.php');
$spidsdk = new SPID_PHP();
$data = [];
// controlla processo di autenticazione
if (!$spidsdk->isAuthenticated()) {
  // utente non autenticato: redirect a pagina di login (non dovrebbe mai capitare)
  header('Location: https://'.$_SERVER['HTTP_HOST'].'/');
  die();
}
// dati di autenticazione
$data['idp'] = $spidsdk->getIdP();
$data['response_id'] = $spidsdk->getResponseID();
$data['attr_name'] = $spidsdk->getAttribute('name');
$data['attr_family_name'] = $spidsdk->getAttribute('familyName');
$data['attr_fiscal_number'] = $spidsdk->getAttribute('fiscalNumber');
$data['logout_url'] = $spidsdk->getLogoutURL('https://'.$_SERVER['HTTP_HOST'].'/login/form');
$data['state'] = 'A';
// connette a database condiviso con symfony
// NB: impostare nome db, user e password su config di simplesamlphp
$db = \SimpleSAML\Database::getInstance();
// inserisce dati
$sql = "INSERT INTO gs_spid (creato, modificato, ".implode(', ', array_keys($data)).") ".
  "VALUES (NOW(), NOW(), :".implode(', :', array_keys($data)).")";
$query = $db->write($sql, $data);
// redirect alla pagina di login dell'applicazione
header('Location: https://'.$_SERVER['HTTP_HOST'].'/spid/acs/'.$data['response_id']);
die();
