<?php

namespace Cissee\WebtreesExt;

use Cissee\WebtreesExt\Http\RequestHandlers\SharedPlacePage;
use Exception;
use Fisharebest\Webtrees\Factory;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Location;
use Fisharebest\Webtrees\Place;
use Fisharebest\Webtrees\Tree;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;

/**
 * A GEDCOM level 0 shared place aka location (_LOC) object (complete structure)
 * note: webtrees now (2.0.4) has basic support for _LOC via Location.php
 */
class SharedPlace extends Location {
  
  public const RECORD_TYPE = '_LOC';

  protected const ROUTE_NAME  = SharedPlacePage::class;

  protected $useHierarchy;
  protected $useIndirectLinks;

  public function useHierarchy(): bool {
    return $this->useHierarchy;
  }
  
  public function __construct(
          bool $useHierarchy, 
          bool $useIndirectLinks, 
          string $xref, 
          string $gedcom, 
          $pending, 
          Tree $tree) {

    parent::__construct($xref, $gedcom, $pending, $tree);
    $this->useHierarchy = $useHierarchy;
    $this->useIndirectLinks = $useIndirectLinks;
    
    //make sure all places exist
    foreach ($this->namesAsPlaces() as $place) {
      $place->id();
    }  
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
        $record = Factory::gedcomRecord()->make($position->id, $this->tree);
        if ($record && $record->canShow()) {
          if ($record instanceof Individual) {
            $list[] = $record;
          }
        }
      }
    }
    $concatenated = $main->concat($list)->unique();
    
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
        $record = Factory::gedcomRecord()->make($position->id, $this->tree);
        if ($record && $record->canShow()) {
          if ($record instanceof Family) {
            $list[] = $record;
          }
        }
      }
    }

    $concatenated = $main->concat($list)->unique();
    
    return $concatenated;
  }
  
  public function getParents(): array {
    //note: could also use _link table!
    $sharedPlaces = [];
    preg_match_all('/\n1 _LOC @(' . Gedcom::REGEX_XREF . ')@/', $this->gedcom(), $matches);
    foreach ($matches[1] as $match) {
        $loc = Factory::location()->make($match, $this->tree());
        if ($loc && $loc->canShow()) {
            $sharedPlaces[] = $loc;
        }
    }

    return $sharedPlaces;
  }
  
  public function canonicalPlace(): Place {
    $head = $this->namesNN()[$this->getPrimaryName()];
    if (!$this->useHierarchy) {
      return new Place($head, $this->tree);
    }
    
    $parents = $this->getParents();
    if (empty($parents)) {
      return new Place($head, $this->tree);
    }

    //first parent wins
    foreach ($this->getParents() as $parent) {
      $parentPlace  = $parent->canonicalPlace();
      $full = $head . Gedcom::PLACE_SEPARATOR . $parentPlace->gedcomName();
      return new Place($full, $this->tree);
    }
  }
  
  //if shared places hierarchy is used, build returned place names via hierarchy!
  public function namesAsPlaces(): array {
    $places = array();
    foreach ($this->getAllNames() as $nameStructure) {
      $head = $nameStructure['fullNN'];
      if ($this->useHierarchy) {
        $parents = $this->getParents();
        if (empty($parents)) {
          $places[] = new Place($head, $this->tree);
        } else {
          foreach ($this->getParents() as $parent) {
            foreach ($parent->namesAsPlaces() as $parentPlace) {
              $full = $head . Gedcom::PLACE_SEPARATOR . $parentPlace->gedcomName();
              $places[] = new Place($full, $this->tree);
            }
          }      
        }
      } else {
        $places[] = new Place($head, $this->tree);
      }      
    }
    return $places;
  }
  
  public function matchesWithHierarchyAsArg(
          string $placeGedcomName,
          bool $useHierarchy): bool {
    
    if ($useHierarchy) {
      $parts = explode(Gedcom::PLACE_SEPARATOR, $placeGedcomName);
      $tail = implode(Gedcom::PLACE_SEPARATOR, array_slice($parts, 1));
      $head = reset($parts);
      
      foreach ($this->namesNN() as $name) {
        if (strtolower($head) === strtolower($name)) {
          //name matches - check parent hierarchy!
          $parents = $this->getParents();
          
          if ($head === $placeGedcomName) {
            //top-level: any parentless shared place matches!
            if (empty($parents)) {
              return true;
            }
          } else {
            foreach ($this->getParents() as $parent) {
              if ($parent->matches($tail)) {
                return true;
              }
            }              
          }          
        }
      }
      
      return false;
    }
    
    foreach ($this->namesNN() as $name) {
      if (strtolower($placeGedcomName) === strtolower($name)) {
        return true;
      }
    }
    
    return false;
  }
  
  public function matches(
          string $placeGedcomName): bool {
    
    return $this->matchesWithHierarchyAsArg($placeGedcomName, $this->useHierarchy);
  }

}
