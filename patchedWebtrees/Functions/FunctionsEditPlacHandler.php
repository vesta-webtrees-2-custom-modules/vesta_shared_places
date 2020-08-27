<?php

namespace Cissee\WebtreesExt\Functions;

use Fisharebest\Webtrees\Functions\FunctionsEdit;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\GedcomTag;
use Fisharebest\Webtrees\Tree;

//also used for DATE!
class FunctionsEditPlacHandler {
  
  //for new-individual via cards/add-fact
  public function addSimpleTagsPlac(Tree $tree, string $fact): void {
    echo FunctionsEdit::addSimpleTag($tree, '0 PLAC', $fact, GedcomTag::getLabel($fact . ':PLAC'));

    if (preg_match_all('/(' . Gedcom::REGEX_TAG . ')/', $tree->getPreference('ADVANCED_PLAC_FACTS'), $match)) {
        foreach ($match[1] as $tag) {
            echo FunctionsEdit::addSimpleTag($tree, '0 ' . $tag, $fact, GedcomTag::getLabel($fact . ':PLAC:' . $tag));
        }
    }
    echo FunctionsEdit::addSimpleTag($tree, '0 MAP', $fact);
    echo FunctionsEdit::addSimpleTag($tree, '0 LATI', $fact);
    echo FunctionsEdit::addSimpleTag($tree, '0 LONG', $fact);
  }
  
  //for edit-fact (PLAC missing completely)
  public function insertMissingSubtagsPlac(Tree $tree, string $level1tag): void {
    echo FunctionsEdit::addSimpleTag($tree, '2 PLAC', $level1tag);
    
    if (preg_match_all('/(' . Gedcom::REGEX_TAG . ')/', $tree->getPreference('ADVANCED_PLAC_FACTS'), $match)) {
        foreach ($match[1] as $tag) {
            echo FunctionsEdit::addSimpleTag($tree, '3 ' . $tag, '', GedcomTag::getLabel($level1tag . ':PLAC:' . $tag));
        }
    }
    echo FunctionsEdit::addSimpleTag($tree, '3 MAP');
    echo FunctionsEdit::addSimpleTag($tree, '4 LATI');
    echo FunctionsEdit::addSimpleTag($tree, '4 LONG');
  }
  
  //for edit-fact (existing PLAC/_LOC, also missing subtags of PLAC)
  public static function addSimpleTag(Tree $tree, $tag, $upperlevel = '', $label = ''): string {
    return FunctionsEdit::addSimpleTag($tree, $tag, $upperlevel, $label);
  }
  
  //add-fact uses one of the above
}
