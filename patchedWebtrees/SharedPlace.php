<?php

namespace Cissee\WebtreesExt;

use Exception;
use Closure;
use Illuminate\Database\Capsule\Manager as DB;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Place;
use Fisharebest\Webtrees\Tree;
use Illuminate\Support\Collection;
use stdClass;

/**
 * A GEDCOM level 0 shared place aka location (_LOC) object (complete structure)
 */
class SharedPlace extends GedcomRecord {

  const RECORD_TYPE = '_LOC';

  protected $moduleName;
  protected $useIndirectLinks;

  public function __construct(string $moduleName, bool $useIndirectLinks, string $xref, string $gedcom, $pending, Tree $tree) {
    parent::__construct($xref, $gedcom, $pending, $tree);
    $this->moduleName = $moduleName;
    $this->useIndirectLinks = $useIndirectLinks;
  }

  /**
   * A closure which will create a record from a database row.
   *
   * @return Closure
   */
  public static function rowMapper(): Closure {
    return function (stdClass $row): SharedPlace {
      return GedcomRecordExt::getInstance($row->o_id, Tree::findById((int) $row->o_file), $row->o_gedcom);
    };
  }

  /**
   * Generate a private version of this record
   *
   * @param int $access_level
   *
   * @return string
   */
  protected function createPrivateGedcomRecord($access_level): string {
    return '0 @' . $this->xref . "@ _LOC\n1 NAME " . I18N::translate('Private');
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

  public function url(): string {
    return route('module', [
        'module' => $this->moduleName,
        'action' => 'Single',
        'xref' => $this->xref(),
        'ged' => $this->tree->name(),
    ]);
  }

  public function linkedIndividuals(string $link): Collection {
    if (!$this->useIndirectLinks) {
      return parent::linkedIndividuals($link);
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
          if ($record instanceof Individual) {
            $list[] = $record;
          }
        }
      }
    }
    return Collection::wrap($list);
  }

  public function linkedFamilies(string $link): Collection {
    if (!$this->useIndirectLinks) {
      return parent::linkedFamilies($link);
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

    return Collection::wrap($list);
  }

}
