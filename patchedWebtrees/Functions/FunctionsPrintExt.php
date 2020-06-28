<?php

namespace Cissee\WebtreesExt\Functions;

use Fisharebest\Webtrees\Functions\FunctionsPrint;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\GedcomTag;
use Fisharebest\Webtrees\I18N;
use Illuminate\Support\Collection;

class FunctionsPrintExt {

  public static function printAddNewFact_LOC(GedcomRecord $record, Collection $usedfacts): void {
    $tree = $record->tree();
    //TODO make configurable via module preferences (and tree?)
    //$addfacts    = preg_split("/[, ;:]+/", $tree->getPreference('_LOC_FACTS_ADD'), -1, PREG_SPLIT_NO_EMPTY);
    //$uniquefacts = preg_split("/[, ;:]+/", $tree->getPreference('_LOC_FACTS_UNIQUE'), -1, PREG_SPLIT_NO_EMPTY);
    //$quickfacts  = preg_split("/[, ;:]+/", $tree->getPreference('_LOC_FACTS_QUICK'), -1, PREG_SPLIT_NO_EMPTY);
    //addfacts = non-unique only! 
    
    //TODO add after next webtrees release (otherwise confusion with MAP): "_LOC" => "_LOC", 
    $addfacts = array("NAME" => "NAME", "NOTE" => "NOTE", "SHARED_NOTE" => "SHARED_NOTE", "SOUR" => "SOUR");
    $uniquefacts = array("MAP" => "MAP", "_GOV" => "_GOV");
    $quickfacts = array("MAP" => "MAP", "NOTE" => "NOTE", "SHARED_NOTE" => "SHARED_NOTE", "_GOV" => "_GOV");

    //from here on same as in FunctionsPrint::printAddNewFact

    $addfacts = array_merge(FunctionsPrint::checkFactUnique($uniquefacts, $usedfacts), $addfacts);
    $quickfacts = array_intersect($quickfacts, $addfacts);
    $translated_addfacts = [];
    foreach ($addfacts as $addfact) {
      $translated_addfacts[$addfact] = GedcomTag::getLabel($addfact);
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
