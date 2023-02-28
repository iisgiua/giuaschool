<?php

require dirname(dirname(__DIR__)).'/src/Install/Updater.php';
use App\Install\Updater;


// impostazioni generali
date_default_timezone_set('Europe/Rome');
// legge parametri
$token = $_GET['token'] ?? '';
$step = $_GET['step'] ?? '0';
$path = dirname(__DIR__);
// esegue passo di installazione
$installer = new Updater($path);
$installer->update($token, $step);
