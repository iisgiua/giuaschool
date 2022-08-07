<?php
use App\Kernel;

require_once dirname(dirname(__DIR__)).'/vendor/autoload_runtime.php';


use App\Install\Installer;


date_default_timezone_set('Europe/Rome');
$path = dirname(__DIR__);
$installer = new Installer($path);
$installer->run();
