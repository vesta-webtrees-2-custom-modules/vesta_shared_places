<?php

use Composer\Autoload\ClassLoader;

$loader = new ClassLoader();
$loader->addPsr4('Cissee\\Webtrees\\Module\\SharedPlaces\\', __DIR__);
$loader->addPsr4('Cissee\\WebtreesExt\\', __DIR__ . "/patchedWebtrees");
$loader->addPsr4('Cissee\\WebtreesExt\\Services\\', __DIR__ . "/patchedWebtrees/Services");
$loader->addPsr4('Cissee\\WebtreesExt\\Functions\\', __DIR__ . "/patchedWebtrees/Functions");
$loader->addPsr4('Cissee\\WebtreesExt\\Exceptions\\', __DIR__ . "/patchedWebtrees/Exceptions");
$loader->addPsr4('Cissee\\WebtreesExt\\Http\\RequestHandlers\\', __DIR__ . "/patchedWebtrees/Http/RequestHandlers");
$loader->register();
