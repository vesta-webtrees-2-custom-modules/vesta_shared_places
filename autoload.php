<?php

use Composer\Autoload\ClassLoader;

$loader = new ClassLoader();
$loader->addPsr4('Cissee\\Webtrees\\Module\\SharedPlaces\\', __DIR__);
$loader->addPsr4('Cissee\\WebtreesExt\\', __DIR__ . "/patchedWebtrees");
$loader->addPsr4('Cissee\\WebtreesExt\\Services\\', __DIR__ . "/patchedWebtrees/Services");
$loader->addPsr4('Cissee\\WebtreesExt\\Functions\\', __DIR__ . "/patchedWebtrees/Functions");
$loader->register();

$classMap = array();
$extend = !class_exists("Fisharebest\Webtrees\GedcomRecord", false);
        
if ($extend) {
  //explicitly load webtrees replacements so that the original files aren't autoloaded
  $classMap["Fisharebest\Webtrees\GedcomRecord"] = __DIR__ . '/replacedWebtrees/GedcomRecord.php';

  //these adjustments allow use to re-use existing code (webtrees controllers using GedcomRecord::getInstance)
  //where we only have to adjust routes (e.g. for editing shared places, 
  //where the editing itself is straightforward, but we want to return to our specific view of the shared place)

  $extend2 = !class_exists("Fisharebest\Webtrees\Html", false);
  if ($extend2) {
    $classMap["Fisharebest\Webtrees\Html"] = __DIR__ . '/replacedWebtrees/Html.php';
  }

  //
  //other adjustments
  //
  //adjustments for MAP
  $extend3 = !class_exists("Fisharebest\Webtrees\Config", false);
  if ($extend3) {
    $classMap["Fisharebest\Webtrees\Config"] = __DIR__ . '/replacedWebtrees/Config.php';
  }

  //adjustments for MAP
  $extend4 = !class_exists("Fisharebest\Webtrees\Functions\FunctionsEdit", false);
  if ($extend4) {
    $classMap["Fisharebest\Webtrees\Functions\FunctionsEdit"] = __DIR__ . '/replacedWebtrees/Functions/FunctionsEdit.php';
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
} else {
  //must use original files because they are already loaded
  //(this occurs currently (webtrees 2.0.3))
  //thus cannot use GedcomRecord::getInstance etc here  
}

$loader->addClassMap($classMap);        
$loader->register(true); //prepend in order to override definitions from default class loader
