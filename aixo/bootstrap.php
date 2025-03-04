<?php
use MODX\Revolution\modX;
use MODX\Aixo\Aixo;

/** 
 * @var modX $modx
 */

// Define the namespace path if it's not already set
$namespace = [
    'path' => $modx->getOption('core_path') . 'components/aixo/'
];

// Register the PSR-4 autoloader for the Aixo namespace
$modx->getLoader()->addPsr4('MODX\\Aixo\\', $namespace['path'] . 'src/');

// Register Aixo in MODX's DI container
$modx->services->add('aixo', function ($container) use ($modx) {
    return new Aixo($modx);
});
