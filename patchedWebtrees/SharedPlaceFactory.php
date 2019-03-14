<?php

namespace Cissee\WebtreesExt;

use Fisharebest\Webtrees\GedcomRecord;

class SharedPlaceFactory implements GedcomRecordFactory {

  protected $moduleName;
  protected $useIndirectLinks;

  public function __construct($moduleName, $useIndirectLinks) {
    $this->moduleName = $moduleName;
    $this->useIndirectLinks = $useIndirectLinks;
  }

  public function createRecord($xref, $gedcom, $pending, $tree): GedcomRecord {
    return new SharedPlace($this->moduleName, $this->useIndirectLinks, $xref, $gedcom, $pending, $tree);
  }

}
