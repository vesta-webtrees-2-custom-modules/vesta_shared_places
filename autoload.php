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

//GedcomTag:
//adjustment for MAP is temporary, will be in webtrees 2.0.7
//adjustment for _LOC:_LOC hopefully possible in webtrees 2.1.x
$extend = !class_exists("Fisharebest\Webtrees\GedcomTag", false);
if ($extend) {
  require_once __DIR__ . '/replacedWebtrees/app/GedcomTag.php';
}