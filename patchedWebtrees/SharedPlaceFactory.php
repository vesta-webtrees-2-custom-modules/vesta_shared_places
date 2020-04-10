<?php

namespace Cissee\WebtreesExt;

use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\Tree;

class SharedPlaceFactory implements GedcomRecordFactory {

  protected $useIndirectLinks;

  public function __construct($useIndirectLinks) {
    $this->useIndirectLinks = $useIndirectLinks;
  }

  public function createRecord(string $xref, string $gedcom, $pending, Tree $tree): GedcomRecord {
    return new SharedPlace($this->useIndirectLinks, $xref, $gedcom, $pending, $tree);
  }

}
