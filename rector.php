<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
// use Rector\Symfony\Set\SymfonySetList;



return RectorConfig::configure()
    ->withSymfonyContainerXml(__DIR__ . '/var/cache/dev/App_KernelDevDebugContainer.xml')
    //--- PHP
    // ->withPhpSets(php74: true)
    // ->withPhpSets(php80: true)
    // ->withPhpSets(php81: true)
    // ->withPhpSets(php82: true)

    //--- Attributes
    ->withAttributesSets(doctrine: true)
    ->withAttributesSets(sensiolabs: true)
    ->withAttributesSets(symfony: true)
    ->withAttributesSets(phpunit: true)



    // ->withConfiguredRule(AnnotationToAttributeRector::class, [
    //     new AnnotationToAttribute('Symfony\\Component\\Routing\\Annotation\\Route'),
    // ])


// core... symfony doctrine... twig
    // ->withSets([
        // SymfonySetList::SYMFONY_54,
        // SymfonySetList::SYMFONY_CODE_QUALITY,
        // SymfonySetList::SYMFONY_CONSTRUCTOR_INJECTION,
    // ])
    ;
