<?php

namespace Cissee\WebtreesExt\Functions;

use Fisharebest\Webtrees\Functions\FunctionsPrint;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\GedcomTag;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Services\ClipboardService;
use Illuminate\Support\Collection;

class FunctionsPrintExt {

  public static function printAddNewFact_LOC(string $moduleName, GedcomRecord $record, Collection $usedfacts): void {
    //$tree = $record->tree();
    //TODO make configurable via module preferences (and tree?)
    //$addfacts    = preg_split("/[, ;:]+/", $tree->getPreference('_LOC_FACTS_ADD'), -1, PREG_SPLIT_NO_EMPTY);
    //$uniquefacts = preg_split("/[, ;:]+/", $tree->getPreference('_LOC_FACTS_UNIQUE'), -1, PREG_SPLIT_NO_EMPTY);
    //$quickfacts  = preg_split("/[, ;:]+/", $tree->getPreference('_LOC_FACTS_QUICK'), -1, PREG_SPLIT_NO_EMPTY);
    //addfacts = all (without unique) 
    $addfacts = array("NOTE" => "NOTE", "SHARED_NOTE" => "SHARED_NOTE", "SOUR" => "SOUR"); //,"_GOV"			
    $uniquefacts = array("MAP" => "MAP", "NAME" => "NAME"); //"_GOV"; TODO: NAME: spec says 1:M! handle this.
    $quickfacts = array("MAP" => "MAP", "NOTE" => "NOTE", "SHARED_NOTE" => "SHARED_NOTE");

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

    $clipboard_service = new ClipboardService();

    $pastable_facts = $clipboard_service->pastableFacts($record, new Collection());

    //[RC] TODO: check: do we have to adjust here as well?
    echo view('edit/paste-fact-row', [
        'facts' => $pastable_facts,
        'record' => $record,
    ]);

    //[RC] adjusted
    echo view($moduleName . '::edit/shared-place-add-fact-row', [
        'moduleName' => $moduleName,
        'add_facts' => $translated_addfacts,
        'quick_facts' => $quickfacts,
        'record' => $record,
    ]);
  }

}
