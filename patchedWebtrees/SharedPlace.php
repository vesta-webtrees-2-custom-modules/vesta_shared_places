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
use Illuminate\Database\Query\JoinClause;
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

  public function printLati(): string {
    //cf FunctionsPrint
    $cts = preg_match('/\d LATI (.*)/', $this->gedcom(), $match);
    if ($cts > 0) {
      return $match[1];
    }    
    return '';
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

  public function printLong(): string {
    //cf FunctionsPrint
    $cts = preg_match('/\d LONG (.*)/', $this->gedcom(), $match);
    if ($cts > 0) {
      return $match[1];
    }    
    return '';
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

  public function getAttributes($tag): array {
    preg_match_all('/\n1 (?:' . $tag . ') ?(.*(?:(?:\n2 CONT ?.*)*)*)/', $this->gedcom, $matches);
    $attributes = array();
    foreach ($matches[1] as $match) {
      $attributes[] = preg_replace("/\n2 CONT ?/", "\n", $match);
    }

    return $attributes;
  }
  
  public function linkedIndividualsC(string $link, bool $transitively): Collection {
    if (!$transitively) {
      return parent::linkedIndividuals($link);
    }
    
    //for compatibility with indirect links, we consider all child places as well
    //(that's how placelinks work)
    
    //performance unacceptable in mysql without type restriction in the _first_ left join
    //(others can take it or leave it - "explain" statement doesn't look different = joined size apparently the problem)
    //may still be problematic for large link tables, there are similar queries on places and placelinks though, with comparable sizes
    //
    //performance still too bad for multiple shared places!
    return DB::table('individuals')
            ->join('link AS l0', static function (JoinClause $join) use ($link): void {
                $join
                    ->on('l0.l_file', '=', 'i_file')
                    ->on('l0.l_from', '=', 'i_id')
                    ->where('l0.l_type', '=', $link);
            })
            ->leftJoin('link AS l1', static function (JoinClause $join) use ($link): void {
                $join
                    ->on('l1.l_file', '=', 'l0.l_file')
                    ->on('l1.l_from', '=', 'l0.l_to')
                    ->where('l1.l_type', '=', $link);
            })
            ->leftJoin('link AS l2', static function (JoinClause $join) use ($link): void {
                $join
                    ->on('l2.l_file', '=', 'l1.l_file')
                    ->on('l2.l_from', '=', 'l1.l_to')
                    ->where('l2.l_type', '=', $link);
            })
            ->leftJoin('link AS l3', static function (JoinClause $join) use ($link): void {
                $join
                    ->on('l3.l_file', '=', 'l2.l_file')
                    ->on('l3.l_from', '=', 'l2.l_to')
                    ->where('l3.l_type', '=', $link);
            })
            ->leftJoin('link AS l4', static function (JoinClause $join) use ($link): void {
                $join
                    ->on('l4.l_file', '=', 'l3.l_file')
                    ->on('l4.l_from', '=', 'l3.l_to')
                    ->where('l4.l_type', '=', $link);
            })
            ->leftJoin('link AS l5', static function (JoinClause $join) use ($link): void {
                $join
                    ->on('l5.l_file', '=', 'l4.l_file')
                    ->on('l5.l_from', '=', 'l4.l_to')
                    ->where('l5.l_type', '=', $link);
            })
            ->leftJoin('link AS l6', static function (JoinClause $join) use ($link): void {
                $join
                    ->on('l6.l_file', '=', 'l5.l_file')
                    ->on('l6.l_from', '=', 'l5.l_to')
                    ->where('l6.l_type', '=', $link);
            })
            ->leftJoin('link AS l7', static function (JoinClause $join) use ($link): void {
                $join
                    ->on('l7.l_file', '=', 'l6.l_file')
                    ->on('l7.l_from', '=', 'l6.l_to')
                    ->where('l7.l_type', '=', $link);
            })
            ->leftJoin('link AS l8', static function (JoinClause $join) use ($link): void {
                $join
                    ->on('l8.l_file', '=', 'l7.l_file')
                    ->on('l8.l_from', '=', 'l7.l_to')
                    ->where('l8.l_type', '=', $link);
            })
            ->leftJoin('link AS l9', static function (JoinClause $join) use ($link): void {
                $join
                    ->on('l9.l_file', '=', 'l8.l_file')
                    ->on('l9.l_from', '=', 'l8.l_to')
                    ->where('l9.l_type', '=', $link);
            })
            ->where('i_file', '=', $this->tree->id())
            //->where('l0.l_type', '=', $link)
            ->where('l0.l_to', '=', $this->xref)
                    ->orWhere('l1.l_to', '=', $this->xref)
                    ->orWhere('l2.l_to', '=', $this->xref)
                    ->orWhere('l3.l_to', '=', $this->xref)
                    ->orWhere('l4.l_to', '=', $this->xref)
                    ->orWhere('l5.l_to', '=', $this->xref)
                    ->orWhere('l6.l_to', '=', $this->xref)
                    ->orWhere('l7.l_to', '=', $this->xref)
                    ->orWhere('l8.l_to', '=', $this->xref)
                    ->orWhere('l9.l_to', '=', $this->xref)
            ->select(['individuals.*'])
            ->get()
            ->map(Factory::individual()->mapper($this->tree))
            ->filter(self::accessFilter());
  }
  
  public function linkedIndividuals(string $link): Collection {
    $main = $this->linkedIndividualsC($link, false); //should use true but performance is too bad
    
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
  
  public function linkedFamiliesC(string $link, bool $transitively): Collection {
    if (!$transitively) {
      return parent::linkedFamilies($link);
    }
    
    //for compatibility with indirect links, we consider all child places as well
    //(that's how placelinks work)
    
    //performance unacceptable in mysql without type restriction in the _first_ left join
    //(others can take it or leave it - "explain" statement doesn't look different = joined size apparently the problem)
    //may still be problematic for large link tables, there are similar queries on places and placelinks though, with comparable sizes
    //
    //performance still too bad for multiple shared places!
    return DB::table('families')
            ->join('link AS l0', static function (JoinClause $join) use ($link): void {
                $join
                    ->on('l0.l_file', '=', 'f_file')
                    ->on('l0.l_from', '=', 'f_id')
                    ->where('l0.l_type', '=', $link);
            })
            ->leftJoin('link AS l1', static function (JoinClause $join) use ($link): void {
                $join
                    ->on('l1.l_file', '=', 'l0.l_file')
                    ->on('l1.l_from', '=', 'l0.l_to')
                    ->where('l1.l_type', '=', $link);
            })
            ->leftJoin('link AS l2', static function (JoinClause $join) use ($link): void {
                $join
                    ->on('l2.l_file', '=', 'l1.l_file')
                    ->on('l2.l_from', '=', 'l1.l_to')
                    ->where('l2.l_type', '=', $link);
            })
            ->leftJoin('link AS l3', static function (JoinClause $join) use ($link): void {
                $join
                    ->on('l3.l_file', '=', 'l2.l_file')
                    ->on('l3.l_from', '=', 'l2.l_to')
                    ->where('l3.l_type', '=', $link);
            })
            ->leftJoin('link AS l4', static function (JoinClause $join) use ($link): void {
                $join
                    ->on('l4.l_file', '=', 'l3.l_file')
                    ->on('l4.l_from', '=', 'l3.l_to')
                    ->where('l4.l_type', '=', $link);
            })
            ->leftJoin('link AS l5', static function (JoinClause $join) use ($link): void {
                $join
                    ->on('l5.l_file', '=', 'l4.l_file')
                    ->on('l5.l_from', '=', 'l4.l_to')
                    ->where('l5.l_type', '=', $link);
            })
            ->leftJoin('link AS l6', static function (JoinClause $join) use ($link): void {
                $join
                    ->on('l6.l_file', '=', 'l5.l_file')
                    ->on('l6.l_from', '=', 'l5.l_to')
                    ->where('l6.l_type', '=', $link);
            })
            ->leftJoin('link AS l7', static function (JoinClause $join) use ($link): void {
                $join
                    ->on('l7.l_file', '=', 'l6.l_file')
                    ->on('l7.l_from', '=', 'l6.l_to')
                    ->where('l7.l_type', '=', $link);
            })
            ->leftJoin('link AS l8', static function (JoinClause $join) use ($link): void {
                $join
                    ->on('l8.l_file', '=', 'l7.l_file')
                    ->on('l8.l_from', '=', 'l7.l_to')
                    ->where('l8.l_type', '=', $link);
            })
            ->leftJoin('link AS l9', static function (JoinClause $join) use ($link): void {
                $join
                    ->on('l9.l_file', '=', 'l8.l_file')
                    ->on('l9.l_from', '=', 'l8.l_to')
                    ->where('l9.l_type', '=', $link);
            })
            ->where('f_file', '=', $this->tree->id())
            //->where('l0.l_type', '=', $link)
            ->where('l0.l_to', '=', $this->xref)
                    ->orWhere('l1.l_to', '=', $this->xref)
                    ->orWhere('l2.l_to', '=', $this->xref)
                    ->orWhere('l3.l_to', '=', $this->xref)
                    ->orWhere('l4.l_to', '=', $this->xref)
                    ->orWhere('l5.l_to', '=', $this->xref)
                    ->orWhere('l6.l_to', '=', $this->xref)
                    ->orWhere('l7.l_to', '=', $this->xref)
                    ->orWhere('l8.l_to', '=', $this->xref)
                    ->orWhere('l9.l_to', '=', $this->xref)
            ->select(['families.*'])
            ->get()
            ->map(Factory::family()->mapper($this->tree))
            ->filter(self::accessFilter());
  }
  
  public function linkedFamilies(string $link): Collection {
    $main = $this->linkedFamiliesC($link, false); //should use true but performance is too bad
    
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
