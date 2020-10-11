<?php

namespace Cissee\WebtreesExt;

use Cissee\WebtreesExt\Http\RequestHandlers\SharedPlacePage;
use Exception;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Location;
use Fisharebest\Webtrees\Place;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Tree;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use stdClass;
use Vesta\Model\GedcomDateInterval;
use function str_contains;

/**
 * A GEDCOM level 0 shared place aka location (_LOC) object (complete structure)
 * note: webtrees now (2.0.4) has basic support for _LOC via Location.php
 */
class SharedPlace extends Location {
  
  public const RECORD_TYPE = '_LOC';

  protected const ROUTE_NAME  = SharedPlacePage::class;

  protected $preferences;

  public function useHierarchy(): bool {
    return $this->preferences->useHierarchy();
  }
  
  public function useIndirectLinks(): bool {
    return $this->preferences->useIndirectLinks();
  }
  
  public function preferences(): SharedPlacePreferences {
    return $this->preferences;
  }
  
  public function __construct(
          SharedPlacePreferences $preferences, 
          string $xref, 
          string $gedcom, 
          $pending, 
          Tree $tree) {

    parent::__construct($xref, $gedcom, $pending, $tree);
    $this->preferences = $preferences;
    
    //must not call this in constructor due to potential circular references!
    //(resolution (via fact->target()) uses SharedPlace constructor)
    //$this->check();
  }

  public function check(): void {
    
    //foreach ($this->namesAsPlacesAt(GedcomDateInterval::createEmpty()) as $place) {
    //  error_log("placename for ".$this->xref(). ": " . $place->gedcomName());
    //}

    //make sure all places _for_all_dates_ exist, and are linked to this record 
    //(otherwise they will be deleted again in next FunctionsImport::updateRecord() call as 'orphaned places')
    
    //also cleanup obsolete placelinks
    //(should all this be done on updateRecord()? tricky wrt place hierarchies!)
    
    //note that we only link shared place 1:1 to place name, not to higher-level place names
    //(this is different from indi:place and fam:place links elsewhere in webtrees)
    
    $allPlaceIds = new Collection();
    //cf FunctionsImport::updatePlaces
    foreach ($this->namesAsPlacesAt(GedcomDateInterval::createEmpty()) as $place) {

        if ($place->id() !== 0) {
            $allPlaceIds->add($place->id());          
        } //else actually unexpected!
      
        // Calling Place::id() will create the entry in the database, if it doesn't already exist.
        while ($place->id() !== 0) {
            $place = $place->parent();
        }
    }
    
    //error_log(print_r($allPlaceIds, true));
    
    // Place links (first step: delete obsolete links)
    DB::table('placelinks')
        ->where('pl_gid', '=', $this->xref())
        ->where('pl_file', '=', $this->tree()->id())
        ->whereNotIn('pl_p_id', $allPlaceIds)
        ->delete();
    
    $xref = $this->xref();
    $tree = $this->tree();
    $linkedPlaceIds = DB::table('placelinks')
        ->where('pl_gid', '=', $xref)
        ->where('pl_file', '=', $tree->id())
        ->pluck('pl_p_id');
    
    $rows = $allPlaceIds
            ->diff($linkedPlaceIds)
            ->unique()
            ->map(static function (int $placeId) use ($xref, $tree): array {
                return [
                    'pl_p_id' => $placeId,
                    'pl_gid'  => $xref,
                    'pl_file' => $tree->id(),
                ];
            })
            ->all();
    
    // Place links (second step: insert new links)
    DB::table('placelinks')->insert($rows);        
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
  
  public function linkedIndividuals(string $link): Collection {
    if ($link !== '_LOC') {
      throw new Exception("unexpected link!");
    }

    return SharedPlace::linkedIndividualsRecords(new Collection([$this]));
  }
  
  //more efficient than count(linkedIndividuals())
  public function countLinkedIndividuals(string $link): int {
    if ($link !== '_LOC') {
      throw new Exception("unexpected link!");
    }
    
    return SharedPlace::linkedIndividualsCount(new Collection([$this]));
  }
  
  public static function linkedIndividualsRecords(Collection $sharedPlaces): Collection {
    if ($sharedPlaces->count() === 0) {
      return new Collection();
    }
    $anySharedPlace = $sharedPlaces->first();
    return SharedPlace::linkedIndividualsRaw($sharedPlaces)
            ->map(Registry::individualFactory()->mapper($anySharedPlace->tree()))
            ->unique()
            ->filter(self::accessFilter());
  }
  
  // Count the number of linked records. These numbers include private records.
  // It is not good to bypass privacy, but many servers do not have the resources
  // to process privacy for every record in the tree
  public static function linkedIndividualsCount(Collection $sharedPlaces): int {
    return SharedPlace::linkedIndividualsRaw($sharedPlaces)
            ->map(function (stdClass $row): string {
              return $row->i_id;
            })
            ->unique()
            ->count();
  }
  
  //batch mode for multiple inputs could be optimized for count!
  public static function linkedIndividualsRaw(Collection $sharedPlaces): Collection {
    if ($sharedPlaces->count() === 0) {
      return new Collection();
    }
    $anySharedPlace = $sharedPlaces->first();
    
    //for compatibility with indirect links, we consider all child places as well
    //(that's how placelinks work)
    
    //in particular for batch operations, loading the entire _LOC-graph seems to be the most efficient solution if we cache it.
    $main = LocGraph::get($anySharedPlace->tree())
            ->linkedIndividuals($sharedPlaces->map(function (SharedPlace $sharedPlace): string {
              return $sharedPlace->xref();
            }));
    
    //consistent across all shared places
    $useIndirectLinks = $anySharedPlace->useIndirectLinks();    
            
    if ($useIndirectLinks) {
      //note: includes all individuals with child places (that's how placelinks work)
      //regardless of INDIRECT_LINKS_PARENT_LEVELS
      $placeIds = [];
      foreach ($sharedPlaces as $sharedPlace) {
        foreach ($sharedPlace->namesAsPlacesAt(GedcomDateInterval::createEmpty()) as $place) {
          $place_id = $place->id();
          $placeIds[] = $place_id;
        }
      }  

      //TODO optimize - use placelinks for loc:place as well!
      $indis = DB::table('placelinks')
            ->whereIn('pl_p_id', $placeIds)
            ->where('pl_file', '=', $anySharedPlace->tree()->id())
            ->join('individuals', function (JoinClause $join): void {
                $join
                ->on('pl_gid', '=', 'individuals.i_id')
                ->on('pl_file', '=', 'individuals.i_file');
              })
            ->select(['i_id','i_gedcom'])
            ->get();
              
      $main = $main->concat($indis); //not sufficient to use unique() here - structures are different! 
    }
    
    return $main;
  }
  
  public function linkedFamilies(string $link): Collection {
    if ($link !== '_LOC') {
      throw new Exception("unexpected link!");
    }

    return SharedPlace::linkedFamiliesRecords(new Collection([$this]));
  }
  
  //more efficient than count(linkedFamilies())
  public function countLinkedFamilies(string $link): int {
    if ($link !== '_LOC') {
      throw new Exception("unexpected link!");
    }
    
    return SharedPlace::linkedFamiliesCount(new Collection([$this]));
  }
  
  public static function linkedFamiliesRecords(Collection $sharedPlaces): Collection {
    if ($sharedPlaces->count() === 0) {
      return new Collection();
    }
    $anySharedPlace = $sharedPlaces->first();
    return SharedPlace::linkedFamiliesRaw($sharedPlaces)
            ->map(Registry::familyFactory()->mapper($anySharedPlace->tree()))
            ->unique()
            ->filter(self::accessFilter());
  }
  
  // Count the number of linked records. These numbers include private records.
  // It is not good to bypass privacy, but many servers do not have the resources
  // to process privacy for every record in the tree
  public static function linkedFamiliesCount(Collection $sharedPlaces): int {
    return SharedPlace::linkedFamiliesRaw($sharedPlaces)
            ->map(function (stdClass $row): string {
              return $row->f_id;
            })
            ->unique()
            ->count();
  }
  
  //batch mode for multiple inputs could be optimized for count!
  public static function linkedFamiliesRaw(Collection $sharedPlaces): Collection {
    if ($sharedPlaces->count() === 0) {
      return new Collection();
    }
    $anySharedPlace = $sharedPlaces->first();
    
    //for compatibility with indirect links, we consider all child places as well
    //(that's how placelinks work)
    
    //in particular for batch operations, loading the entire _LOC-graph seems to be the most efficient solution if we cache it.
    $main = LocGraph::get($anySharedPlace->tree())
            ->linkedFamilies($sharedPlaces->map(function (SharedPlace $sharedPlace): string {
              return $sharedPlace->xref();
            }));
    
    //consistent across all shared places
    $useIndirectLinks = $anySharedPlace->useIndirectLinks();    
            
    if ($useIndirectLinks) {
      //note: includes all individuals with child places (that's how placelinks work)
      //regardless of INDIRECT_LINKS_PARENT_LEVELS
      $placeIds = [];
      foreach ($sharedPlaces as $sharedPlace) {
        foreach ($sharedPlace->namesAsPlacesAt(GedcomDateInterval::createEmpty()) as $place) {
          $place_id = $place->id();
          $placeIds[] = $place_id;
        }
      }
      
      //TODO optimize - use placelinks for loc:place as well!
      $fams = DB::table('placelinks')
            ->whereIn('pl_p_id', $placeIds)
            ->where('pl_file', '=', $anySharedPlace->tree()->id())
            ->join('families', function (JoinClause $join): void {
                $join
                ->on('pl_gid', '=', 'families.f_id')
                ->on('pl_file', '=', 'families.f_file');
              })
            ->select(['f_id','f_gedcom'])
            ->get();
    
      $main = $main->concat($fams); //not sufficient to use unique() here - structures are different! 
    }
    
    return $main;
  }
  
  /**
   * 
   * @return Collection key: xref, value: Collection<SharedPlaceParentAt> (direct parents)
   */
  public function getTransitiveParentsAt(GedcomDateInterval $date): Collection {
    $ret = new Collection();
    
    //safer wrt loops (than to use method recursively)
    $queue = new Collection();        
    $queue->prepend($this);
        
    while ($queue->count() > 0) {
      $current = $queue->pop();
      $parents = $current->getWrappedParentsAt($date, true);
      $ret->put($current->xref(), $parents);
      foreach ($parents as $parent) {
        $parentSharedPlace = $parent->getSharedPlace();
        if (($parentSharedPlace !== null) && !$ret->has($parentSharedPlace->xref())) {
          $queue->prepend($parentSharedPlace);
        }
      }
    }
    
    return $ret;
  }
  
  //do not use recursively! Tree may have circular hierarchies
  public function getParents(): Collection {
    return $this->getWrappedParentsAt(GedcomDateInterval::createNow(), false)
            ->map(function (SharedPlaceParentAt $element): SharedPlace {
              //non-null because we don't fillInterval!
              return $element->getSharedPlace();
            });
  }
  
  //do not use recursively! Tree may have circular hierarchies
  /**
   * 
   * @param GedcomDateInterval $date
   * @return Collection<SharedPlaceParentAt>, sorted by date
   */
  public function getWrappedParentsAt(
          GedcomDateInterval $date,
          bool $fillInterval): Collection {
    
    $sharedPlaces = [];
    $indexOfFact = -1;
    foreach ($this->facts(['_LOC']) as $parent) {
      $indexOfFact++;
      $parentDate = GedcomDateInterval::create($parent->attribute("DATE"));
      
      $intersectedDate = $date->intersect($parentDate);
      if ($intersectedDate !== null) {
        $sharedPlaces[] = new SharedPlaceParentAt($intersectedDate, $parent->target(), $indexOfFact);
      } else if ($parent->attribute("DATE") === '') {
        $sharedPlaces[] = new SharedPlaceParentAt($date, $parent->target(), $indexOfFact);
      }
    }

    //order: by date.getFrom (nulls first), then by original order
    uasort($sharedPlaces, function (SharedPlaceParentAt $x, SharedPlaceParentAt $y): int {
        $xJulianDay = $x->getDate()->getFrom() ?? 0;
        $yJulianDay = $y->getDate()->getFrom() ?? 0;

        $cmp = $xJulianDay <=> $yJulianDay;

        if ($cmp === 0) {
          //use original order (we have to handle this explicitly, sort is only stable in php starting with 8.0)
          return $x->getIndexOfFact() <=> $y->getIndexOfFact();
        }

        return $cmp;
    });
    
    if (!$fillInterval) {
      return new Collection($sharedPlaces);
    }
    
    //fill given interval (which also means there is always at least one SharedPlaceParentAt)
    $filled = $date->fillInterval(
            new Collection($sharedPlaces),
            function (SharedPlaceParentAt $element): GedcomDateInterval {
              return $element->getDate();
            },
            function (GedcomDateInterval $date): SharedPlaceParentAt {
              //no actual parent
              return new SharedPlaceParentAt($date, null, -1);
            });
    
    return $filled;
  }
  
  //cf GedcomRecord addName(), but add GedcomDateInterval, indexOfFact
  protected function createName(
          string $type, 
          string $value, 
          string $gedcom,
          GedcomDateInterval $date,
          int $indexOfFact): array {
    
      return [
          'type'   => $type,
          'sort'   => preg_replace_callback('/([0-9]+)/', static function (array $matches): string {
              return str_pad($matches[0], 10, '0', STR_PAD_LEFT);
          }, $value),
          'full'   => '<span dir="auto">' . e($value) . '</span>',
          // This is used for display
          'fullNN' => $value,
          // This goes into the database
                  
          'date' => $date, 
          'indexOfFact' => $indexOfFact,       
      ];
  }
    
  //cf GedcomRecord extractNamesFromFacts() (but return results rather than write to cache array, and add date)
  /**
   * Get all the names of a record, including ROMN, FONE and _HEB alternatives.
   * Records without a name (e.g. FAM) will need to redefine this function.
   * Parameters: the level 1 fact containing the name.
   * Return value: an array of name structures, each containing
   * ['type'] = the gedcom fact, e.g. NAME, TITL, FONE, _HEB, etc.
   * ['full'] = the name as specified in the record, e.g. 'Vincent van Gogh' or 'John Unknown'
   * ['sort'] = a sortable version of the name (not for display), e.g. 'Gogh, Vincent' or '@N.N., John'
   * ['date'] = intersected date interval
   *
   * @param GedcomDateInterval $date
   * @param int                $level
   * @param string             $fact_type
   * @param Collection<Fact>   $facts
   *
   * @return array
   */
  protected function extractNamesFromFactsAt(
          GedcomDateInterval $date, 
          int $level, 
          string $fact_type, 
          Collection $facts): array {
    
      $extractedNames = [];    
    
      $sublevel    = $level + 1;
      $subsublevel = $sublevel + 1;
      
      $indexOfFact = -1;
      foreach ($facts as $fact) {
          $indexOfFact++;
        
          $nameDate = GedcomDateInterval::create($fact->attribute("DATE"));      
          
          //[RC] adjusted
          $intersectedDate = $date->intersect($nameDate);
          if ($intersectedDate === null) {
            continue;
          }
                
          if (preg_match_all("/^{$level} ({$fact_type}) (.+)((\n[{$sublevel}-9].+)*)/m", $fact->gedcom(), $matches, PREG_SET_ORDER)) {
              foreach ($matches as $match) {
                  // Treat 1 NAME / 2 TYPE married the same as _MARNM
                  if ($match[1] === 'NAME' && str_contains($match[3], "\n2 TYPE married")) {
                      $extractedNames []= $this->createName('_MARNM', $match[2], $fact->gedcom(), $intersectedDate, $indexOfFact);
                  } else {
                      $extractedNames []= $this->createName($match[1], $match[2], $fact->gedcom(), $intersectedDate, $indexOfFact);
                  }
                  if ($match[3] && preg_match_all("/^{$sublevel} (ROMN|FONE|_\w+) (.+)((\n[{$subsublevel}-9].+)*)/m", $match[3], $submatches, PREG_SET_ORDER)) {
                      foreach ($submatches as $submatch) {
                          $extractedNames []= $this->createName($submatch[1], $submatch[2], $match[3], $intersectedDate, $indexOfFact);
                      }
                  }
              }
          }
      }
      
      //order: by $intersectedDate.getFrom (nulls first), then by original order
      uasort($extractedNames, function (array $x, array $y): int {
          $xJulianDay = $x['date']->getFrom() ?? 0;
          $yJulianDay = $y['date']->getFrom() ?? 0;
          
          $cmp = $xJulianDay <=> $yJulianDay;
          
          if ($cmp === 0) {
            //use original order (we have to handle this explicitly, sort is only stable in php starting with 8.0)
            return $x['indexOfFact'] <=> $y['indexOfFact'];
          }
          
          return $cmp;
      });
      
      return $extractedNames;
  }
  
  //why is this even public in GedcomRecord? seems to be internal helper function!
  public function extractNames(): void {
    throw new \Exception("illegal access!"); 
  }

  protected function extractNamesAt(GedcomDateInterval $date): array {
    return $this->extractNamesFromFactsAt($date, 1, 'NAME', $this->facts(['NAME']));
  }

  public function getAllNames(): array {
    return $this->getAllNamesAt(null);
  }
  
  //cf GedcomRecord getAllNames() (but don't cache results unless $date is null)
  public function getAllNamesAt(?GedcomDateInterval $date): array {
    
    $actualDate = $date;
    if ($date === null) {
      if ($this->getAllNames !== null) {
        return $this->getAllNames;
      }
      
      //null was just for caching
      $actualDate = GedcomDateInterval::createNow();
    }
    
    $getAllNames = [];
    if ($this->canShowName()) {
        // Ask the record to extract its names
        $getAllNames = $this->extractNamesAt($actualDate);
        
        $self = $this;
        //and fill given interval (which also means there is always at least one name)
        $filled = $actualDate->fillInterval(
                new Collection($getAllNames),
                function (array $element): GedcomDateInterval {
                  return $element['date'];
                },
                function (GedcomDateInterval $date) use($self): array {
                  //fallback name
                  return $self->createName(static::RECORD_TYPE, $this->getFallBackName(), '', $date, -1);
                });
                
        $getAllNames = $filled->all();
    } else {
        $getAllNames []= $this->createName(static::RECORD_TYPE, I18N::translate('Private'), '', $actualDate, -1);
    }
    
    if ($date === null) {
      //cache
      $this->getAllNames = $getAllNames;
    }

    return $getAllNames;
  }
  
  public function getPrimaryNameIndexWrtUnfilteredNames(): int {
    return $this->getPrimaryNameAt(GedcomDateInterval::createNow());
  }
  
  public function getPrimaryNameIndexWrtUnfilteredNamesAt(GedcomDateInterval $date): int {
    $fallback = -1;
    $counter = 0;
    foreach ($this->facts(['NAME']) as $name) {
      $nameDate = GedcomDateInterval::create($name->attribute("DATE"));
      
      if ($date->intersect($nameDate) !== null) {
        return $counter;
      }
      
      if (($name->attribute("DATE") === '') && ($fallback === -1)) {
        $fallback = $counter;
      }
      
      $counter++;
    }
    
    return ($fallback === -1)?0:$fallback;
  }
  
  /**
   * 
   * @param string $date
   * @param bool $primaryOnly
   * @param string $xref
   * @param Collection $alreadySeenXrefs
   * @param array $nextNames
   * @param Collection $transitiveParents
   * @param array $currentNames
   * @return array place names (unnamed places and circular hierarchies properly handled)
   */
  private static function namesAsStringsAt(
          ?GedcomDateInterval $date,
          bool $primaryOnly,
          string $xref,
          Collection $alreadySeenXrefs,
          array $nextNames,
          Collection $transitiveParents,
          array $currentNames): array {
    
    if ($alreadySeenXrefs->contains($xref)) {      
      //mark as circular and treat as leaf
      $ret = [];
      
      if (sizeof($currentNames) === 0) {
        throw new \Exception("unexpected!");
      }
      
      foreach ($currentNames as $head) {
        if (strlen($head) === 0) {
          throw new \Exception("unexpected!");
        }
        
        //append
        $ret []= $head . Gedcom::PLACE_SEPARATOR . /*json_decode('"\u221E"') .*/ " <" . I18N::translate("circular shared place hierarchy") . ">";
      }
      
      return $ret;
    }
    
    //copy collection
    $nextAlreadySeenXrefs = $alreadySeenXrefs->map(function ($item) {
        return $item;
    });
    $nextAlreadySeenXrefs->add($xref);

    //append own name and handle parents
      
    $ret = [];
    foreach ($nextNames as $nameStructure) {            
      $fullNN = $nameStructure['fullNN'];
      
      /* @var $nameDate GedcomDateInterval */
      $nameDate = $nameStructure['date'];
      
      $toMerge = SharedPlace::namesAsStringsSingleAt(
          $date,
          $primaryOnly,
          $xref, 
          $nextAlreadySeenXrefs,
          $fullNN,
          $nameDate, 
          $transitiveParents,
          $currentNames);
      
      $ret = array_merge($ret, $toMerge);
    }
    return $ret;
  }
  
  private static function namesAsStringsSingleAt(
          ?GedcomDateInterval $date,
          bool $primaryOnly,
          string $xref,
          Collection $alreadySeenXrefs,
          string $nextName,
          GedcomDateInterval $nextNameDate,
          Collection $transitiveParents,
          array $currentNames): array {
    
    $nextNames = [];
    
    if (sizeof($currentNames) === 0) {
      //root
      $nextNames []= $nextName;
    } else {
      foreach ($currentNames as $head) {
        //append
        $nextNames []= $head . Gedcom::PLACE_SEPARATOR . $nextName;
      }
    }
    
    /* @var $parents Collection<SharedPlaceParentAt> */
    $parents = $transitiveParents->get($xref);
    
    $ret = [];
    foreach ($parents as $parent) {          

      /* @var $parent SharedPlaceParentAt */
      
      //special intersect! 
      $intersectedDate = $parent->getDate()->intersect($nextNameDate, true);
      
      if ($intersectedDate === null) {
        //irrelevant parent for this name
        continue;
      }
      
      /* @var $parentSharedPlace SharedPlace */
      $parentSharedPlace = $parent->getSharedPlace();
      
      if ($parentSharedPlace === null) {
        //leaf
        $ret = array_merge($ret, $nextNames);
      } else {
        
        //we have an actual parent with an intersecting date
        $parentNames = $parentSharedPlace->getAllNamesAt($date);
        
        if ($primaryOnly) {
          //only use primary name, also restrict to primary parent
          $parentNames = [$parentNames[0]];
          
          return SharedPlace::namesAsStringsAt(
              $intersectedDate,
              $primaryOnly, 
              $parentSharedPlace->xref(), 
              $alreadySeenXrefs,
              $parentNames,
              $transitiveParents,
              $nextNames);
        }

        $toMerge = SharedPlace::namesAsStringsAt(
              $intersectedDate,
              $primaryOnly, 
              $parentSharedPlace->xref(), 
              $alreadySeenXrefs,
              $parentNames,
              $transitiveParents,
              $nextNames);
        
        $ret = array_merge($ret, $toMerge);
      }
    }
    return $ret;
  }
  
  //if shared places hierarchy is used, build returned place names via hierarchy!
  //but beware of loops (circular references in shared place hierarchy)
  public function namesAsPlaces(): array {
    return $this->namesAsPlacesAt(null);
  }
  
  public function namesAsPlacesAt(?GedcomDateInterval $date): array {
    if (!$this->useHierarchy()) {
      $places = new Collection();
      foreach ($this->getAllNamesAt($date) as $nameStructure) {
        $full = $nameStructure['fullNN'];
        $places->add(new Place($full, $this->tree));
      }
      return $places->unique(function ($place) {
          return $place->id();
      })->all();
    }

    $places = new Collection();
    $namesAsString = SharedPlace::namesAsStringsAt(
            $date,
            false,
            $this->xref(), 
            new Collection(),
            $this->getAllNamesAt($date),
            $this->getTransitiveParentsAt(($date == null)?GedcomDateInterval::createNow():$date),
            []);

    foreach ($namesAsString as $name) {
      $places->add(new Place($name, $this->tree));
    }
    
    return $places->unique(function ($place) {
        return $place->id();
    })->all();

    //not safe wrt loops
    /*
    foreach ($allNames as $nameStructure) {
      $head = $nameStructure['fullNN'];

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
    }
    */    
  }
       
  public function primaryPlace(): Place {
    return $this->primaryPlaceAt(GedcomDateInterval::createNow());
  }
  
  public function primaryPlaceAt(GedcomDateInterval $date): Place {
    if (!$this->useHierarchy()) {
      $primaryIndex = $this->getPrimaryNameIndexWrtUnfilteredNamesAt($date);
      $name = $this->namesNN()[$primaryIndex];
      return new Place($name, $this->tree);
    }
    
    $names = [$this->getAllNamesAt($date)[0]];
    $namesAsString = SharedPlace::namesAsStringsAt(
              $date,
              true,
              $this->xref(), 
              new Collection(),
              $names,
              $this->getTransitiveParentsAt($date),
              []);
    
    if (sizeof($namesAsString) === 0) {
      throw new \Exception("unexpectedly empty!");
    }
    
    if (sizeof($namesAsString) !== 1) {
      throw new \Exception("unexpected!");
    }
    
    foreach ($namesAsString as $name) {
      return new Place($name, $this->tree);
    }
    
    //////////////////////////////////////
  }
  
  public static function placeNameParts(string $placeGedcomName): array {
    // Ignore any empty parts in place names such as "Village, , , Country".
    $partsColl = new Collection(explode(Gedcom::PLACE_SEPARATOR, $placeGedcomName));      
    $parts = $partsColl->filter()->toArray();
    return $parts;
  }
  
  public static function placeNamePartsTail(array $parts): string {
    $tail = implode(Gedcom::PLACE_SEPARATOR, array_slice($parts, 1));
    return $tail;
  }

  public function updateFact(string $fact_id, string $gedcom, bool $update_chan): void {
    parent::updateFact($fact_id, $gedcom, $update_chan);
    
    //reset cached data (buggy in webtrees, which leads to 'undefined offset' errors wrt names)
    $this->getAllNames = null;
    $this->getPrimaryName = null;
    $this->getSecondaryName = null;
  }
  
  public function updateRecord(string $gedcom, bool $update_chan): void {
    parent::updateRecord($gedcom, $update_chan);
    
    //reset cached data (buggy in webtrees, which leads to 'undefined offset' errors wrt names)
    $this->getAllNames = null;
    $this->getPrimaryName = null;
    $this->getSecondaryName = null;
  }
}
