<?php

if (version_compare(PHP_VERSION, '8.2', '<')) {
  die("*** ERRORE CRITICO<br>L'applicazione richiede PHP 8.2");
}
require dirname(__DIR__, 2).'/src/Install/Updater.php';
use App\Install\Updater;


// impostazioni generali
date_default_timezone_set('Europe/Rome');
ini_set('max_execution_time', 0);
// legge parametri (solo valori scalari: un array o un valore non numerico causerebbe un errore fatale)
$token = $_GET['token'] ?? '';
$token = is_string($token) ? $token : '';
$step = $_GET['step'] ?? '';
$step = ((int) filter_var($step, FILTER_VALIDATE_INT));
$path = dirname(__DIR__);
// controlla esistenza file installazione
if (!file_exists($path.'/../.gs-updating')) {
  // crea file di installazione
  $token = bin2hex(random_bytes(16));
  $content = 'token="'.$token.'"'."\n".
    'version="0"'."\n".
    'build="0"'."\n";
  file_put_contents($path.'/../.gs-updating', $content);
}
// esegue passo di installazione
$installer = new Updater($path);
$installer->install($token, $step);
