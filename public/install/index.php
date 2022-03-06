<?php

require dirname(dirname(__DIR__)).'/config/bootstrap.php';

use App\Install\Installer;


date_default_timezone_set('Europe/Rome');
set_error_handler("exception_error_handler", E_ALL & ~E_NOTICE & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
$path = dirname(__DIR__);
$installer = new Installer($path);
$installer->run();

function exception_error_handler($severity, $message, $file, $line) {
  if (strtolower(substr($message, 0, 25)) == 'use of undefined constant' ||
      strtolower(substr($message, 0, 50)) == 'stream_isatty() expects parameter 1 to be resource' ||
      strtolower(substr($message, 0, 41)) == 'is_executable(): open_basedir restriction') {
    // errore non considerato
    return false;
  }
  throw new ErrorException("$message<br>In $file:$line", 0, $severity, $file, $line);
}
