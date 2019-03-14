<?php

namespace Cissee\WebtreesExt;

use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\Tree;

interface GedcomRecordFactory {

  public function createRecord(string $xref, string $gedcom, $pending, Tree $tree): GedcomRecord;
}
