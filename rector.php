<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Symfony\Set\SymfonySetList;



return RectorConfig::configure()
  ->withIndent(indentChar: ' ', indentSize: 2)
  ->withSymfonyContainerXml(__DIR__ . '/var/cache/dev/App_KernelDevDebugContainer.xml')
  ->withImportNames(true, true, true, true)

  //--- PHP
  // ->withPhpSets(php74: true)
  // ->withPhpSets(php80: true)
  // ->withPhpSets(php81: true)
  // ->withPhpSets(php82: true)

  //--- Attributes
  // ->withAttributesSets(doctrine: true)
  // ->withAttributesSets(sensiolabs: true)
  // ->withAttributesSets(symfony: true)
  // ->withAttributesSets(phpunit: true)

  // --- Symphony
  // ->withSets([
    // SymfonySetList::SYMFONY_54,
    // SymfonySetList::SYMFONY_60,
    // SymfonySetList::SYMFONY_61,
    // SymfonySetList::SYMFONY_62,
    // SymfonySetList::SYMFONY_63,
    // SymfonySetList::SYMFONY_64,
    // SymfonySetList::SYMFONY_CODE_QUALITY,
    // SymfonySetList::SYMFONY_CONSTRUCTOR_INJECTION])


//  doctrine... twig core...
    ;
