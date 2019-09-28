<?php

namespace Cissee\WebtreesExt;

use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\Tree;

class SharedPlaceFactory implements GedcomRecordFactory {

  protected $moduleName;
  protected $useIndirectLinks;

  public function __construct($moduleName, $useIndirectLinks) {
    $this->moduleName = $moduleName;
    $this->useIndirectLinks = $useIndirectLinks;
  }

  public function createRecord(string $xref, string $gedcom, $pending, Tree $tree): GedcomRecord {
    return new SharedPlace($this->moduleName, $this->useIndirectLinks, $xref, $gedcom, $pending, $tree);
  }

}
