<?php

declare(strict_types=1);

/*
 * twig-cs-fixer - configurazione del progetto
 *
 * - standard TwigCsFixer (le stesse regole usate per allineare i template del repository)
 * - template analizzati: solo quelli in "templates/"
 *
 * Uso:
 *   vendor/bin/twig-cs-fixer lint   # controlla lo stile
 *   vendor/bin/twig-cs-fixer fix    # corregge
 */

use TwigCsFixer\Config\Config;
use TwigCsFixer\File\Finder;
use TwigCsFixer\Ruleset\Ruleset;
use TwigCsFixer\Standard\TwigCsFixer;

$config = new Config('giuaschool');

$config->setFinder((new Finder())->in(__DIR__.'/templates'));
$config->setRuleset((new Ruleset())->addStandard(new TwigCsFixer()));

return $config;
