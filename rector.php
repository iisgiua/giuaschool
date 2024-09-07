<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Symfony\Set\SymfonySetList;
use Rector\Php80\Rector\Class_\AnnotationToAttributeRector;
use Rector\Php80\ValueObject\AnnotationToAttribute;
use Rector\Php80\Rector\Property\NestedAnnotationToAttributeRector;
use Rector\Php80\ValueObject\AnnotationPropertyToAttributeClass;
use Rector\Php80\ValueObject\NestedAnnotationToAttribute;



return RectorConfig::configure()
    ->withSymfonyContainerXml(__DIR__ . '/var/cache/dev/App_KernelDevDebugContainer.xml')
    //--- PHP
    // ->withPhpSets(php74: true)
    // ->withPhpSets(php80: true)
    // ->withPhpSets(php81: true)
    // ->withPhpSets(php82: true)

    //--- Attributes
    ->withConfiguredRule(AnnotationToAttributeRector::class, [
        new AnnotationToAttribute('Symfony\\Component\\Routing\\Annotation\\Route'),
    ])
    ->withConfiguredRule(NestedAnnotationToAttributeRector::class, [
        new NestedAnnotationToAttribute('Doctrine\\ORM\\Mapping\\JoinTable', [
        new AnnotationPropertyToAttributeClass('Doctrine\\ORM\\Mapping\\JoinColumn', 'joinColumns'),
        new AnnotationPropertyToAttributeClass('Doctrine\\ORM\\Mapping\\InverseJoinColumn', 'inverseJoinColumns'),
        ]),
    ])


    // ->withSets([
        // SymfonySetList::SYMFONY_54,
        // SymfonySetList::SYMFONY_CODE_QUALITY,
        // SymfonySetList::SYMFONY_CONSTRUCTOR_INJECTION,
    // ])
    ;
