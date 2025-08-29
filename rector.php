<?php

declare(strict_types=1);


use Rector\Config\RectorConfig;
use Rector\Symfony\Set\SymfonySetList;
use Rector\Doctrine\Set\DoctrineSetList;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Symfony\Set\TwigSetList;



//============================================================


return RectorConfig::configure()
    // indentazione dei file generati
    ->withIndent(indentChar: ' ', indentSize: 2)

    // container Symfony (facoltativo ma utile per alcuni refactoring)
    ->withSymfonyContainerXml(__DIR__ . '/var/cache/dev/App_KernelDevDebugContainer.xml')

    // import automatico dei nomi (use) + rimozione import inutilizzati
    ->withImportNames(true, true, true, true)

    // directory da processare
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])

  //--- PHP
  // ->withPhpSets(php82: true)

  //--- Attributes
  // ->withAttributesSets(doctrine: true)
  // ->withAttributesSets(sensiolabs: true)
  // ->withAttributesSets(symfony: true)
  // ->withAttributesSets(phpunit: true)

  // --- Symphony
    // ->withSets([
    // SymfonySetList::SYMFONY_64,
    // SymfonySetList::SYMFONY_CODE_QUALITY,
    // SymfonySetList::SYMFONY_CONSTRUCTOR_INJECTION])
    // ->withPreparedSets(symfonyCodeQuality: true, symfonyConfigs: true)


  // --- Doctrine
  // ->withSets([
  //   DoctrineSetList::DOCTRINE_DBAL_30,
  //   DoctrineSetList::DOCTRINE_ORM_25,
  //   DoctrineSetList::DOCTRINE_ORM_29,
  //   DoctrineSetList::DOCTRINE_BUNDLE_210,
  //   DoctrineSetList::DOCTRINE_COLLECTION_22,
  //   DoctrineSetList::TYPED_COLLECTIONS,
  //   DoctrineSetList::DOCTRINE_CODE_QUALITY,
  //   ])
  // ->withPreparedSets(doctrineCodeQuality: true)

  // --- PHPUnit
  // ->withSets([
  //   PHPUnitSetList::PHPUNIT_100,
  //   PHPUnitSetList::ANNOTATIONS_TO_ATTRIBUTES,
  //   PHPUnitSetList::PHPUNIT_CODE_QUALITY,
  //   ])
  // ->withPreparedSets(phpunit: true, phpunitCodeQuality: true)

  // --- Twig
  // ->withSets([
  //   TwigSetList::TWIG_240,
  //   ])
  // ->withPreparedSets(twig: true)


  // --- REGOLA PERSONALIZZATA (nota: metodo CORRETTO è withRules([...]))
  // ->withRules([
  //     AddMapEntityRector::class
  // ])


  ;
