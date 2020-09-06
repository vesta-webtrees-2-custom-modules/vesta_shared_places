<?php

namespace Cissee\WebtreesExt\Functions;

use Cissee\WebtreesExt\SharedPlace;
use Fisharebest\Webtrees\Functions\FunctionsPrint;
use Fisharebest\Webtrees\GedcomTag;
use Fisharebest\Webtrees\I18N;
use Illuminate\Support\Collection;
use function view;

class FunctionsPrintExt {

  public static function adjust(array $fromPrefs): array {
    $ret = [];
    foreach ($fromPrefs as $keyForLabel) {
      $value = $keyForLabel;
      if (strpos($value,':') !== false) {
        $value = substr($value, strpos($value,':')+1);
      }
      $ret[$keyForLabel] = $value;
    }
    
    return $ret;
  }
          
  public static function printAddNewFact_LOC(SharedPlace $record, Collection $usedfacts): void {
    $tree = $record->tree();
    
    /*
    //$addfacts    = preg_split("/[, ;:]+/", $tree->getPreference('_LOC_FACTS_ADD'), -1, PREG_SPLIT_NO_EMPTY);
    //$uniquefacts = preg_split("/[, ;:]+/", $tree->getPreference('_LOC_FACTS_UNIQUE'), -1, PREG_SPLIT_NO_EMPTY);
    //$quickfacts  = preg_split("/[, ;:]+/", $tree->getPreference('_LOC_FACTS_QUICK'), -1, PREG_SPLIT_NO_EMPTY);
    //addfacts = non-unique only! 
    
    $addfacts = array("NAME" => "NAME", "_LOC:TYPE" => "TYPE", "NOTE" => "NOTE", "SHARED_NOTE" => "SHARED_NOTE", "SOUR" => "SOUR", "_LOC:_LOC" => "_LOC");
    $uniquefacts = array("MAP" => "MAP", "_GOV" => "_GOV");
    $quickfacts = array("MAP" => "MAP", "NOTE" => "NOTE", "SHARED_NOTE" => "SHARED_NOTE", "_GOV" => "_GOV");
    */

    $addfacts = $record->preferences()->addfacts();
    $uniquefacts = $record->preferences()->uniquefacts();
    $quickfacts = $record->preferences()->quickfacts();
    
    //from here on same as in FunctionsPrint::printAddNewFact, except adjustment '$keyForLabel'

    $addfacts = array_merge(FunctionsPrint::checkFactUnique($uniquefacts, $usedfacts), $addfacts);
    $quickfacts = array_intersect($quickfacts, $addfacts);
    $translated_addfacts = [];
    foreach ($addfacts as $keyForLabel => $addfact) {
      $translated_addfacts[$addfact] = GedcomTag::getLabel($keyForLabel);
    }
    uasort($translated_addfacts, function (string $x, string $y): int {
      return I18N::strcasecmp(I18N::translate($x), I18N::translate($y));
    });

    echo view('edit/add-fact-row', [
            'add_facts'   => $translated_addfacts,
            'quick_facts' => $quickfacts,
            'record'      => $record,
            'tree'        => $tree,
        ]);
  }

}
