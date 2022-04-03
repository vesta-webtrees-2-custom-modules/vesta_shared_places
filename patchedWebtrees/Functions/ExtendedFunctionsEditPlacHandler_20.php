<?php

namespace Cissee\WebtreesExt\Functions;

use Fisharebest\Webtrees\Functions\FunctionsEdit;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\GedcomTag;
use Fisharebest\Webtrees\Tree;


class ExtendedFunctionsEditPlacHandler_20 extends FunctionsEditPlacHandler_20 {
  
  public function expectedSubtagsPlac(): array {
    //note: could set _LOC via tree preferences, seems better to do it centrally
    //in particular a change at this point [2.0.12] would be surprising for users
    return ['MAP', '_LOC'];
  }
  
  //for new-individual via cards/add-fact
  public function addSimpleTagsPlac(Tree $tree, string $fact): void {

    //echo FunctionsEdit::addSimpleTag($tree, '0 PLAC', $fact, GedcomTag::getLabel($fact . ':PLAC'));
    echo FunctionsEditLoc::addSimpleTagWithGedcomRecord(null, $tree, '0 PLAC', $fact, ''/*GedcomTag::getLabel($fact . ':PLAC')*/);

    echo FunctionsEditLoc::addSimpleTagWithGedcomRecord(null, $tree, '0 _LOC', $fact . ':PLAC');

    if (preg_match_all('/(' . Gedcom::REGEX_TAG . ')/', $tree->getPreference('ADVANCED_PLAC_FACTS'), $match)) {
        foreach ($match[1] as $tag) {
            echo FunctionsEdit::addSimpleTag($tree, '0 ' . $tag, $fact, GedcomTag::getLabel($fact . ':PLAC:' . $tag));
        }
    }
    echo FunctionsEdit::addSimpleTag($tree, '0 MAP', $fact);
    //echo FunctionsEdit::addSimpleTag($tree, '0 LATI', $fact);
    echo FunctionsEditLoc::addSimpleTagWithGedcomRecord(null, $tree, '0 LATI', $fact); //must use this method to create proper 'child_of_'
    //echo FunctionsEdit::addSimpleTag($tree, '0 LONG', $fact);
    echo FunctionsEditLoc::addSimpleTagWithGedcomRecord(null, $tree, '0 LATI', $fact); //must use this method to create proper 'child_of_'
  }
  
  //for edit-fact (PLAC missing completely)
  public function insertMissingSubtagsPlac(Tree $tree, string $level1tag): void {
    //echo FunctionsEdit::addSimpleTag($tree, '2 PLAC', $level1tag);
    echo FunctionsEditLoc::addSimpleTagWithGedcomRecord(null, $tree, '2 PLAC', $level1tag, ''/*GedcomTag::getLabel($level1tag . ':PLAC')*/);
    
    echo FunctionsEditLoc::addSimpleTagWithGedcomRecord(null, $tree, '3 _LOC', $level1tag . ':PLAC');
    
    if (preg_match_all('/(' . Gedcom::REGEX_TAG . ')/', $tree->getPreference('ADVANCED_PLAC_FACTS'), $match)) {
        foreach ($match[1] as $tag) {
            echo FunctionsEdit::addSimpleTag($tree, '3 ' . $tag, '', GedcomTag::getLabel($level1tag . ':PLAC:' . $tag));
        }
    }
    echo FunctionsEdit::addSimpleTag($tree, '3 MAP');
    //echo FunctionsEdit::addSimpleTag($tree, '4 LATI');
    echo FunctionsEditLoc::addSimpleTagWithGedcomRecord(null, $tree, '4 LATI'); //must use this method to create proper 'child_of_'
    //echo FunctionsEdit::addSimpleTag($tree, '4 LONG');
    echo FunctionsEditLoc::addSimpleTagWithGedcomRecord(null, $tree, '4 LONG'); //must use this method to create proper 'child_of_'
  }
  
  //for edit-fact (existing PLAC/_LOC, also missing subtags of PLAC)
  public function addSimpleTag(Tree $tree, $tag, $upperlevel = '', $label = ''): string {
    return FunctionsEditLoc::addSimpleTagWithGedcomRecord(null, $tree, $tag, $upperlevel, $label);
  }
  
  //add-fact uses one of the above
}
