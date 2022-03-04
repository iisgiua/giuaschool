<?php

require dirname(dirname(__DIR__)).'/config/bootstrap.php';

use App\Install\Installer;


date_default_timezone_set('Europe/Rome');
$path = dirname(__DIR__);
$installer = new Installer($path);
$installer->run();
