<?php

// i test usano sempre l'ambiente "test": carica .env.example e .env.test
putenv('APP_ENV=test');
$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'test';

require dirname(__DIR__).'/config/bootstrap.php';
require dirname(__DIR__).'/vendor/autoload.php';
