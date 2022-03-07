<?php

require dirname(dirname(__DIR__)).'/config/bootstrap.php';

use App\Install\Installer;


date_default_timezone_set('Europe/Rome');
set_error_handler("exception_error_handler", E_ALL & ~E_NOTICE & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
$path = dirname(__DIR__);
$installer = new Installer($path);
$installer->run();

function exception_error_handler($severity, $message, $file, $line) {
  if ($severity == E_WARNING) {
    if (strtolower(substr($message, 0, 18)) == 'file_get_contents(' ||
        strtolower(substr($message, 0, 7)) == 'rename(') {
      // invia una eccezione
      throw new ErrorException("$message<br>In $file:$line", 0, $severity, $file, $line);
    }
  }
  // ritorna alla gestione php
  return false;
}
