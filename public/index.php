<?php

use App\Kernel;


########## MODIFICA-INIZIO
date_default_timezone_set('Europe/Rome');
########## MODIFICA-FINE


require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return fn(array $context) => new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
