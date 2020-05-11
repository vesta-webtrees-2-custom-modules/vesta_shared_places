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

$classMap = array();

//adjustments for MAP
$extend3 = !class_exists("Fisharebest\Webtrees\Config", false);
if ($extend3) {
  $classMap["Fisharebest\Webtrees\Config"] = __DIR__ . '/replacedWebtrees/Config.php';
}

//label for _GOV
$extend5 = !class_exists("Fisharebest\Webtrees\GedcomTag", false);
if ($extend5) {
  $classMap["Fisharebest\Webtrees\GedcomTag"] = __DIR__ . '/replacedWebtrees/GedcomTag.php';
}

//media links adjustments
$extend5 = !class_exists("Fisharebest\Webtrees\Http\Controllers\Admin\MediaController", false);
if ($extend5) {
  $classMap["Fisharebest\Webtrees\Http\Controllers\Admin\MediaController"] = __DIR__ . '/replacedWebtrees/Http/Controllers/Admin/MediaController.php';
}

//pending changes (required as long as we don't have the factories approach)
$extend6 = !class_exists("Fisharebest\Webtrees\Http\RequestHandlers\PendingChanges", false);
if ($extend6) {
  $classMap["Fisharebest\Webtrees\Http\RequestHandlers\PendingChanges"] = __DIR__ . '/replacedWebtrees/Http/RequestHandlers/PendingChanges.php';
}

$loader->addClassMap($classMap);        
$loader->register(true); //prepend in order to override definitions from default class loader
