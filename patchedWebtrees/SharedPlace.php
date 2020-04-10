<?php

namespace Cissee\WebtreesExt;

use Closure;
use Exception;
use Cissee\WebtreesExt\Http\RequestHandlers\SharedPlacePage;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Place;
use Fisharebest\Webtrees\Tree;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;
use stdClass;

/**
 * A GEDCOM level 0 shared place aka location (_LOC) object (complete structure)
 */
class SharedPlace extends GedcomRecord {

  public const RECORD_TYPE = '_LOC';

  protected const ROUTE_NAME  = SharedPlacePage::class;

  protected $useIndirectLinks;

  public function __construct(
          bool $useIndirectLinks, 
          string $xref, 
          string $gedcom, 
          $pending, 
          Tree $tree) {

    parent::__construct($xref, $gedcom, $pending, $tree);
    $this->useIndirectLinks = $useIndirectLinks;
  }

  /**
   * A closure which will create a record from a database row.
   *
   * @return Closure
   */
  public static function rowMapper(Tree $tree): Closure {
    return function (stdClass $row) use ($tree): SharedPlace {
      return GedcomRecordExt::getInstance($row->o_id, $tree, $row->o_gedcom);
    };
  }

  /**
   * Generate a private version of this record
   *
   * @param int $access_level
   *
   * @return string
   */
  protected function createPrivateGedcomRecord(int $access_level): string {
    return '0 @' . $this->xref . "@ _LOC\n1 NAME " . MoreI18N::xlate('Private');
  }

  /**
   * Extract names from the GEDCOM record.
   */
  public function extractNames(): void {
    parent::extractNamesFromFacts(1, 'NAME', $this->facts(['NAME']));
  }

  public function names() {
    $names = array();
    foreach ($this->getAllNames() as $nameStructure) {
      $names[] = $nameStructure['full'];
    }
    return $names;
  }

  public function namesNN() {
    $names = array();
    foreach ($this->getAllNames() as $nameStructure) {
      $names[] = $nameStructure['fullNN'];
    }
    return $names;
  }

  public function namesAsPlaces() {
    $places = array();
    foreach ($this->getAllNames() as $nameStructure) {
      $places[] = new Place($nameStructure['fullNN'], $this->tree);
    }
    return $places;
  }

  public function getLati() {
    //cf FunctionsPrint
    $map_lati = null;
    $cts = preg_match('/\d LATI (.*)/', $this->gedcom(), $match);
    if ($cts > 0) {
      $map_lati = $match[1];
    }
    if ($map_lati) {
      $map_lati = trim(strtr($map_lati, "NSEW,�", " - -. ")); // S5,6789 ==> -5.6789
      return $map_lati;
    }
    return null;
  }

  public function getLong() {
    //cf FunctionsPrint
    $map_long = null;
    $cts = preg_match('/\d LONG (.*)/', $this->gedcom(), $match);
    if ($cts > 0) {
      $map_long = $match[1];
    }
    if ($map_long) {
      $map_long = trim(strtr($map_long, "NSEW,�", " - -. ")); // E3.456� ==> 3.456
      return $map_long;
    }
    return null;
  }

  public function getGov() {
    return $this->getAttribute('_GOV');
  }

  public function getAttribute($tag) {
    if (preg_match('/1 (?:' . $tag . ') ?(.*(?:(?:\n2 CONT ?.*)*)*)/', $this->gedcom, $match)) {
      return preg_replace("/\n2 CONT ?/", "\n", $match[1]);
    }
    return null;
  }

  public function linkedIndividuals(string $link): Collection {
    $main = parent::linkedIndividuals($link);
    
    if (!$this->useIndirectLinks) {
      return $main;
    }

    if ($link !== '_LOC') {
      throw new Exception("unexpected link!");
    }

    $list = [];

    //note: includes all individuals with child places (that's how placelinks work)
    //regardless of INDIRECT_LINKS_PARENT_LEVELS
    foreach ($this->namesAsPlaces() as $place) {
      $place_id = $place->id();

      $positions = DB::table('placelinks')
              ->where('pl_p_id', '=', $place_id)
              ->where('pl_file', '=', $this->tree->id())
              ->select(['pl_gid AS id'])
              ->get();

      foreach ($positions as $position) {
        $record = GedcomRecord::getInstance($position->id, $this->tree);
        if ($record && $record->canShow()) {
          if ($record instanceof Individual) {
            $list[] = $record;
          }
        }
      }
    }
    $concatenated = $main->concat($list);
    
    return $concatenated;
  }

  public function linkedFamilies(string $link): Collection {
    $main = parent::linkedFamilies($link);
    
    if (!$this->useIndirectLinks) {
      return $main;
    }

    if ($link !== '_LOC') {
      throw new Exception("unexpected link!");
    }

    $list = [];

    foreach ($this->namesAsPlaces() as $place) {
      $place_id = $place->id();

      $positions = DB::table('placelinks')
              ->where('pl_p_id', '=', $place_id)
              ->where('pl_file', '=', $this->tree->id())
              ->select(['pl_gid AS id'])
              ->get();

      foreach ($positions as $position) {
        $record = GedcomRecord::getInstance($position->id, $this->tree);
        if ($record && $record->canShow()) {
          if ($record instanceof Family) {
            $list[] = $record;
          }
        }
      }
    }

    $concatenated = $main->concat($list);
    
    return $concatenated;
  }

}
