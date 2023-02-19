<?php

namespace Cissee\WebtreesExt\Services;

use Cissee\WebtreesExt\SharedPlace;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\Location;
use Fisharebest\Webtrees\Services\GedcomImportService;
use Fisharebest\Webtrees\Tree;

class GedcomImportServiceExt extends GedcomImportService {
    
    public function importRecord(
        string $gedrec, 
        Tree $tree, 
        bool $update): void {
        
        parent::importRecord($gedrec, $tree, $update);
        
        // import different types of records
        if (preg_match('/^0 @(' . Gedcom::REGEX_XREF . ')@ (' . Gedcom::REGEX_TAG . ')/', $gedrec, $match)) {
            [, $xref, $type] = $match;
        } else {
            return;
        }
        
        switch ($type) {
            case Location::RECORD_TYPE:
                
                // Update the cross-reference/index tables.
                
                //cannot do it like this - parent places may not exist yet (during import)
                //and anyway child places must be re-checked as well
                //$record = Registry::gedcomRecordFactory()->make($xref, $tree);
                //$record->updatePlaces();
                
                //rather mark for check, and handle actual check elsewhere (as before)
                //
                //Issue #148
                //we have to do this in addition to the existing check logic because
                //webtrees always resets the placelinks during update
                //(even for shared places where our 'inner' cache key doesn't change)
                SharedPlace::forget($tree, $xref);                
        }
    }
}
