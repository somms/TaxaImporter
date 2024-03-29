<?php

/**
 * The bootstrap file creates and returns the container.
 */

use DI\ContainerBuilder;

$containerBuilder = new ContainerBuilder;
$containerBuilder->addDefinitions(__DIR__ . '/config.php');
$containerBuilder->useAttributes(true)
->useAutowiring(true);
$container = $containerBuilder->build();

return $container;