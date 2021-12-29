<?php

require dirname(dirname(__DIR__)).'/src/Install/Installer.php';

use App\Install\Installer;


date_default_timezone_set('Europe/Rome');
$installer = new Installer();
$installer->run();
