<?php

namespace Cissee\WebtreesExt\Factories;

use Cissee\WebtreesExt\Contracts\SharedPlaceFactoryInterface;
use Fisharebest\Webtrees\Cache;
use Fisharebest\Webtrees\Factories\GedcomRecordFactory;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\Tree;

class CustomGedcomRecordFactory extends GedcomRecordFactory {

  protected $sharedPlaceFactory;
  
  public function __construct(
          Cache $cache,
          SharedPlaceFactoryInterface $sharedPlaceFactory) {
    
    parent::__construct($cache);
    $this->sharedPlaceFactory = $sharedPlaceFactory;
  }
  
  public function make(string $xref, Tree $tree, string $gedcom = null): ?GedcomRecord {
    $sharedPlace = $this->sharedPlaceFactory->make($xref, $tree, $gedcom);
    return $sharedPlace ?? parent::make($xref, $tree, $gedcom);
  }
    
  protected function newGedcomRecord(string $type, string $xref, string $gedcom, ?string $pending, Tree $tree): ?GedcomRecord
    {
        if ($type === '_LOC') {
            return $this->sharedPlaceFactory->new($xref, $gedcom, $pending, $tree);
        }

        return parent::newGedcomRecord($type, $xref, $gedcom, $pending, $tree);
    }
}

