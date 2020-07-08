<?php

namespace Cissee\WebtreesExt\Factories;

use Cissee\WebtreesExt\SharedPlace;
use Closure;
use Fisharebest\Webtrees\Cache;
use Fisharebest\Webtrees\Contracts\LocationFactoryInterface;
use Fisharebest\Webtrees\Factories\AbstractGedcomRecordFactory;
use Fisharebest\Webtrees\Location;
use Fisharebest\Webtrees\Tree;
use Illuminate\Database\Capsule\Manager as DB;
use stdClass;

class SharedPlaceFactory extends AbstractGedcomRecordFactory implements LocationFactoryInterface {

  private const TYPE_CHECK_REGEX = '/^0 @[^@]+@ ' . SharedPlace::RECORD_TYPE . '/';
  
  protected $useHierarchy;
  protected $useIndirectLinks;

  public function __construct(
          Cache $cache, 
          bool $useHierarchy,
          bool $useIndirectLinks) {
    
    parent::__construct($cache); 
    $this->useHierarchy = $useHierarchy;
    $this->useIndirectLinks = $useIndirectLinks;
  }

  public function unmake(string $xref, Tree $tree) {
    $this->cache->forget(__CLASS__ . $xref . '@' . $tree->id());
  }
    
  /**
   * Create a shared place.
   *
   * @param string      $xref
   * @param Tree        $tree
   * @param string|null $gedcom
   *
   * @return Location|null
   */
  public function make(string $xref, Tree $tree, string $gedcom = null): ?Location
  {
      return $this->cache->remember(__CLASS__ . $xref . '@' . $tree->id(), function () use ($xref, $tree, $gedcom) {
          $gedcom  = $gedcom ?? $this->gedcom($xref, $tree);
          $pending = $this->pendingChanges($tree)->get($xref);

          if ($gedcom === null && ($pending === null || !preg_match(self::TYPE_CHECK_REGEX, $pending))) {
              return null;
          }

          $xref = $this->extractXref($gedcom ?? $pending, $xref);
          
          return new SharedPlace($this->useHierarchy, $this->useIndirectLinks, $xref, $gedcom ?? '', $pending, $tree);
      });
  }
    
  /**
   * Create a SharedPlace object from a row in the database.
   *
   * @param Tree $tree
   *
   * @return Closure
   */
  public function mapper(Tree $tree): Closure
  {
      return function (stdClass $row) use ($tree): SharedPlace {
          $sharedPlace = $this->make($row->o_id, $tree, $row->o_gedcom);
          assert($sharedPlace instanceof SharedPlace);
          return $sharedPlace;
      };
  }
    
  /**
   * Create a SharedPlace object from raw GEDCOM data.
   *
   * @param string      $xref
   * @param string      $gedcom  an empty string for new/pending records
   * @param string|null $pending null for a record with no pending edits,
   *                             empty string for records with pending deletions
   * @param Tree        $tree
   *
   * @return SharedPlace
   */
  public function new(string $xref, string $gedcom, ?string $pending, Tree $tree): Location {
    return new SharedPlace($this->useHierarchy, $this->useIndirectLinks, $xref, $gedcom, $pending, $tree);
  }
  
  /**
   * Fetch GEDCOM data from the database.
   *
   * @param string $xref
   * @param Tree   $tree
   *
   * @return string|null
   */
  public function gedcom(string $xref, Tree $tree): ?string
  {
      return DB::table('other')
          ->where('o_id', '=', $xref)
          ->where('o_file', '=', $tree->id())
          ->whereIn('o_type', [
              SharedPlace::RECORD_TYPE
          ])
          ->value('o_gedcom');
  }
}
