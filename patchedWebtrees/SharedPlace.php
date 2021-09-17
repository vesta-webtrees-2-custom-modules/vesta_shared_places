<?php

namespace Cissee\WebtreesExt;

use Cissee\WebtreesExt\Elements\LanguageIdExt;
use Exception;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\Http\RequestHandlers\LocationPage;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Location;
use Fisharebest\Webtrees\Place;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Tree;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use stdClass;
use Vesta\Model\GedcomDateInterval;
use function mb_strtolower;
use function str_contains;
use Fisharebest\Webtrees\Cache;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * A GEDCOM level 0 shared place aka location (_LOC) object (complete structure)
 * note: webtrees now (2.0.4) has basic support for _LOC via Location.php
 */
class SharedPlace extends Location {
  
  public const RECORD_TYPE = '_LOC';

  //[from 2.0.12] use standard name (relevant e.g. for ClippingsCartModule.php, where the standard names are hard-coded)
  //and redefine this route with SharedPlacePage::class as handler!
  protected const ROUTE_NAME  = LocationPage::class;

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
  
  /** @var ArrayAdapter */
  private $array_adapter;
  
  //use private cache so we can unmake() the cache entries
  protected function array(): Cache {
    return new Cache($this->array_adapter);
  }
    
  public function __construct(
          SharedPlacePreferences $preferences, 
          string $xref, 
          string $gedcom, 
          $pending, 
          Tree $tree) {

    parent::__construct($xref, $gedcom, $pending, $tree);
    $this->preferences = $preferences;
    $this->array_adapter = new ArrayAdapter(0, false);
    
    //must not call this in constructor due to potential circular references!
    //(resolution (via fact->target()) uses SharedPlace constructor)
    //$this->check();
  }

  public function check(): void {
    //error_log("check?" . $this->xref());
    
    //have to use $this->gedcom() as additional key
    //because any update orphans our places via FunctionsImport::updateRecord
    //
    //not sufficient as only key because parent names may have changed
    $pending = $this->isPendingAddition()?'_P':'';
    $key = SharePlace::class . '_check_' . $this->xref() . '_' . $this->gedcom() . $pending . '_' . json_encode($this->namesAsPlaceStringsAt(GedcomDateInterval::createEmpty()));    

    //Issue #54
    //very expensive and called often, therefore cached to file
    //better solution would be to do this after import/update only, but that's intrusive (no hooks in webtrees core code for this)
    
    //do not use $key as cache key 
    //(wouldn't work for changes back to some previous gedcom that is still cached)
    //rather compare with previous state
    $doCheck = false;
    
    $previousKey = Registry::cache()->file()->remember($this->xref(), static function () use ($key, &$doCheck): string {
      //error_log("cache miss");
      $doCheck = true; //due to cache miss
      return $key;
    });
    
    if ($key != $previousKey) {
      //error_log("key change");
      $doCheck = true; //due to change
      
      //must forget and then re-cache
      Registry::cache()->file()->forget($this->xref());
      Registry::cache()->file()->remember($this->xref(), static function () use ($key): string {
        return $key;
      });
    }
    
    if ($doCheck) {
      $this->doCheck();
    }
  }
  
  protected function doCheck(): void { 
    
    //error_log("doCheck");
    
    /*
    foreach ($this->namesAsPlacesAt(GedcomDateInterval::createEmpty()) as $place) {
      error_log("doCheck placename for ".$this->xref(). ": " . $place->gedcomName());
    }
    */

    //make sure all places _for_all_dates_ exist, and are linked to this record 
    //(otherwise they will be deleted again in next FunctionsImport::updateRecord() call as 'orphaned places')
    
    //note that we must not only link shared place 1:1 to place name, but to higher-level place names as well
    //(as with indi:place and fam:place links elsewhere in webtrees)
    //(again, otherwise they will be deleted again in next FunctionsImport::updateRecord() call as 'orphaned places')
    
    //also cleanup obsolete placelinks
    //(should all this be done on updateRecord()? ultimately yes, but note that we will have to update children as well)
    
    $allPlaceIds = new Collection();
    //cf FunctionsImport::updatePlaces
    foreach ($this->namesAsPlacesAt(GedcomDateInterval::createEmpty()) as $place) {
      
        // Calling Place::id() will create the entry in the database, if it doesn't already exist.
        while ($place->id() !== 0) {
            $allPlaceIds->add($place->id());
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

  public function namesNNAt(?GedcomDateInterval $date) {
    $names = array();
    foreach ($this->getAllNamesAt($date, I18N::locale()->code()) as $nameStructure) {
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
    $ret = SharedPlace::linkedIndividualsRecords(new Collection([$this]));
    return $ret;
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
    
    //for compatibility with indirect links, we consider all child places as well (that's how placelinks work)
    
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
    
    //for compatibility with indirect links, we consider all child places as well (that's how placelinks work)
    
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
  
  //this impl is buggy because it always uses $date, rather than specific $intersectedDate
  //e.g. for $date (1801-2000): A has parent B from (1801-1900), B has parent C from (1901-2000);
  //then C isn't relevant here at all.
  /*
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
  */
  
  /**
   * 
   * @return Collection key: xref, value: Collection<SharedPlaceParentAt> (direct parents)
   */
  public function getTransitiveParentsAt(GedcomDateInterval $date): Collection {
    $ret = new Collection();
    
    //safer wrt loops (than to use method recursively)
    $queue = new Collection(); //of SharedPlaceParentAt, we reuse this class here for convenience
    $queue->prepend(new SharedPlaceParentAt($date, $this, -1));
        
    while ($queue->count() > 0) {
      $currentSharedPlaceParentAt = $queue->pop();
      $current = $currentSharedPlaceParentAt->getSharedPlace();
      
      //important to use this rather than original $date:
      //e.g. for $date (1801-2000): A has parent B from (1801-1900), B has parent C from (1901-2000);
      //then C isn't relevant here at all.
      $intersectedDate = $currentSharedPlaceParentAt->getDate();
              
      //we only add elements with non-null shared place to the queue!
      assert ($current !== null);
      
      $parents = $current->getWrappedParentsAt($intersectedDate, true);
      $ret->put($current->xref(), $parents);
      foreach ($parents as $parent) {
        $parentSharedPlace = $parent->getSharedPlace();
        if (($parentSharedPlace !== null) && !$ret->has($parentSharedPlace->xref())) {
          $queue->prepend($parent);
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
   * returned elements have non-null return for ->getSharedPlace() if $fillInterval is false!
   */
  public function getWrappedParentsAt(
          GedcomDateInterval $date,
          bool $fillInterval): Collection {
    
    $sharedPlaces = [];
    $indexOfFact = -1;
    
    foreach ($this->facts(['_LOC']) as $parent) {      
      $indexOfFact++;
      $parentDate = GedcomDateInterval::create($parent->attribute("DATE"));
      
      $target = $parent->target();
      
      if ($target !== null) {
        $intersectedDate = $date->intersect($parentDate);
        if ($intersectedDate !== null) {
          $sharedPlaces[] = new SharedPlaceParentAt($intersectedDate, $target, $indexOfFact);
        } else if ($parent->attribute("DATE") === '') {
          $sharedPlaces[] = new SharedPlaceParentAt($date, $target, $indexOfFact);
        }
      } //else could not make() target, e.g. due to invalid xref (or missing data during import), skip
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
    
    //fill given interval 
    //(which also means there is always at least one SharedPlaceParentAt, but its target may be null)
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
  
  //cf GedcomRecord addName(), but add GedcomDateInterval, lang, indexOfFact
  protected function createName(
          string $type, 
          string $value, 
          string $gedcom,
          GedcomDateInterval $intersectedDate,
          GedcomDateInterval $originalDate,
          string $lang = null,
          int $indexOfFact = -1): array {
    
      return [
          'type' => $type,
          'sort' => preg_replace_callback('/([0-9]+)/', static function (array $matches): string {
              return str_pad($matches[0], 10, '0', STR_PAD_LEFT);
          }, $value),
          'full' => '<span dir="auto">' . e($value) . '</span>',
          // This is used for display
          'fullNN' => $value,
          // This goes into the database
                  
          'date' => $intersectedDate, 
          'originalDate' => $originalDate,     
          'lang' => (($lang === null)?'':$lang),
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
          
          $lang = $fact->attribute("LANG");
          $score = ($lang === null)?0:1;
          
          /*      
          $locales = LanguageIdExt::values();

          if ($lang === null) {
            $score = 0;
          } else {            
            if (($preferredLangCodeForSort !== null) && array_key_exists($lang, $locales)) {
              if ($locales[$lang]->code() === $preferredLangCodeForSort) {
                $score = -1;
              }
            }
            //else lang doesn't match: do not adjust score
          }
          */
      
          if (preg_match_all("/^{$level} ({$fact_type}) (.+)((\n[{$sublevel}-9].+)*)/m", $fact->gedcom(), $matches, PREG_SET_ORDER)) {
              foreach ($matches as $match) {
                  // Treat 1 NAME / 2 TYPE married the same as _MARNM
                  if ($match[1] === 'NAME' && str_contains($match[3], "\n2 TYPE married")) {
                      $extractedNames []= $this->createName(
                              '_MARNM', 
                              $match[2], 
                              $fact->gedcom(), 
                              $intersectedDate, 
                              $nameDate,
                              $lang,
                              $indexOfFact);
                  } else {
                      $extractedNames []= $this->createName(
                              $match[1], 
                              $match[2], 
                              $fact->gedcom(), 
                              $intersectedDate, 
                              $nameDate,
                              $lang,
                              $indexOfFact);
                  }
                  if ($match[3] && preg_match_all("/^{$sublevel} (ROMN|FONE|_\w+) (.+)((\n[{$subsublevel}-9].+)*)/m", $match[3], $submatches, PREG_SET_ORDER)) {
                      foreach ($submatches as $submatch) {
                          $extractedNames []= $this->createName(
                                  $submatch[1], 
                                  $submatch[2], 
                                  $match[3], 
                                  $intersectedDate, 
                                  $nameDate,
                                  $lang,
                                  $indexOfFact);
                      }
                  }
              }
          }
      }
      
      //order
      //1. by $intersectedDate.getFrom (nulls first) (important to have this first for fillInterval)
      //2. by $intersectedDate.getTo (but reversed, longer intervals first)
      //3. by lang
      //4. by original order
      uasort($extractedNames, function (array $x, array $y): int {
          $xScore = ($x['lang'] === '')?0:1;
          $yScore = ($y['lang'] === '')?0:1;
          $cmp = $xScore <=> $yScore;
          if ($cmp !== 0) {
            return $cmp;
          }
          
          $xJulianDay = $x['date']->getFrom() ?? 0;
          $yJulianDay = $y['date']->getFrom() ?? 0;
          
          $cmp = $xJulianDay <=> $yJulianDay;
          if ($cmp !== 0) {
            return $cmp;
          }
          
          $xJulianDay = $x['date']->getTo() ?? 0;
          $yJulianDay = $y['date']->getTo() ?? 0;
          
          $cmp = $yJulianDay <=> $xJulianDay; //reversed!
          if ($cmp !== 0) {
            return $cmp;
          }
          
          //use original order (we have to handle this explicitly, sort is only stable in php starting with 8.0)
          return $x['indexOfFact'] <=> $y['indexOfFact'];
      });
      
      return $extractedNames;
  }
  
  //why is this even public in GedcomRecord? seems to be internal helper function!
  public function extractNames(): void {
    throw new \Exception("illegal access!"); 
  }

  protected function extractNamesAt(
          GedcomDateInterval $date): array {
    
    return $this->extractNamesFromFactsAt(
            $date, 
            1, 
            'NAME', 
            $this->facts(['NAME']));
  }

  public function getAllNames(): array {
    return $this->getAllNamesAt(null, I18N::locale()->code());
  }
  
  //see issue #94: there are no privacy restrictions!
  public function canShow(int $access_level = null): bool {
    return true;
  }
    
  //see issue #94: there are no privacy restrictions!
  public function canShowName(int $access_level = null): bool {
    return true;
  }
  
  //cf GedcomRecord getAllNames() (but don't store in super field (functionality which should be moved to cache anyway?) results unless $date is null)
  public function getAllNamesAt(
          ?GedcomDateInterval $date,
          ?string $preferredLangCodeForSort): array {
    
    $self = $this;
    
    //Issue #54
    //somewhat expensive and called often, therefore cached
    return $this->array()->remember(SharedPlace::class . 'getAllNamesAt_' . $this->xref() . '_' . (($date === null)?'null':json_encode($date)) . '_' . $preferredLangCodeForSort, static function () use ($self, $date, $preferredLangCodeForSort): array {
      $actualDate = $date;
      if ($date === null) {
        if ($self->getAllNames !== null) {
          return $self->getAllNames;
        }

        //null was just for caching
        $actualDate = GedcomDateInterval::createNow();
      }

      $getAllNames = [];
      //if ($self->canShowName()) {
      
          // Ask the record to extract its names
          $getAllNames = $self->extractNamesAt($actualDate);

          //and fill given interval (which also means there is always at least one name)
          $filled = $actualDate->fillInterval(
                  new Collection($getAllNames),
                  function (array $element): GedcomDateInterval {
                    return $element['date'];
                  },
                  function (GedcomDateInterval $date) use ($self): array {
                    //fallback name
                    return $self->createName(
                            static::RECORD_TYPE, 
                            $self->getFallBackName(), 
                            '', 
                            $date, 
                            $date);
                  });

          $getAllNames = $filled->all();
          
          if ($preferredLangCodeForSort !== null) {
            //re-order
            $locales = LanguageIdExt::values();
          
            //1. by lang
            //2. by $intersectedDate.getFrom (nulls first) (important to have this first for fillInterval)
            //2. by $intersectedDate.getTo (but reversed, longer intervals first)
            //3. by original order
            
            uasort($getAllNames, function (array $x, array $y) use ($self, $locales, $preferredLangCodeForSort): int {
                $xScore = $self->getScore($x, $locales, $preferredLangCodeForSort);
                $yScore = $self->getScore($y, $locales, $preferredLangCodeForSort);
                $cmp = $xScore <=> $yScore;
                if ($cmp !== 0) {
                  return $cmp;
                }

                $xJulianDay = $x['date']->getFrom() ?? 0;
                $yJulianDay = $y['date']->getFrom() ?? 0;

                $cmp = $xJulianDay <=> $yJulianDay;
                if ($cmp !== 0) {
                  return $cmp;
                }

                $xJulianDay = $x['date']->getTo() ?? 0;
                $yJulianDay = $y['date']->getTo() ?? 0;
          
                $cmp = $yJulianDay <=> $xJulianDay; //reversed!
                if ($cmp !== 0) {
                  return $cmp;
                }
          
                //use original order (we have to handle this explicitly, sort is only stable in php starting with 8.0)
                return $x['indexOfFact'] <=> $y['indexOfFact'];
            });
          }
        
      /*    
      } else {
          //issue #94: this would be bad for check(), and other call sites using Place functionality
          //(i.e. basically everywhere)
          //for now canShowName() is always true
          $getAllNames []= $self->createName(
                  static::RECORD_TYPE, 
                  I18N::translate('Private'), 
                  '',
                  $actualDate,
                  $actualDate); //set proper language here?
      }
      */

      if ($date === null) {
        //store
        $self->getAllNames = $getAllNames;
      }

      return $getAllNames;
    });  
  }
  
  protected function getScore(
          array $name,
          $locales,
          ?string $preferredLangCode): int {
    
    $lang = $name['lang'];
    
    $score = 1;
    if ($lang === null) {
      $score = 0;
    } else {            
      if (($preferredLangCode !== null) && array_key_exists($lang, $locales)) {
        if ($locales[$lang]->code() === $preferredLangCode) {
          $score = -1;
        }
      }
      //else lang doesn't match: do not adjust score
    }
    
    return $score;
  }
  
  private static function namesAsStringsAt(
          ?GedcomDateInterval $date,
          bool $primaryOnly,
          string $xref /* for parents */,
          array /* of array with nameStructure fields */ $nextNames,
          Collection $transitiveParents): array {

    $ret = SharedPlace::namesAsStringsAtA(
            $primaryOnly,
            $xref, 
            new Collection(),
            $nextNames,
            $transitiveParents,
            []);
    
    //error_log(print_r($ret, true));
    return $ret;
    
    /*
    return SharedPlace::namesAsStringsAtOrig(
            $date,
            $primaryOnly,
            $xref, 
            new Collection(),
            $nextNames,
            $transitiveParents,
            []);
     */
  }
      
  private static function namesAsStringsAtOrig(
          ?GedcomDateInterval $date,
          bool $primaryOnly,
          string $xref /* for parents */,
          Collection $alreadySeenXrefs,
          array /* of array with nameStructure fields */ $nextNames,
          Collection $transitiveParents,
          array /* of array with fields 'name' (partially built name hierarchy) and 'lang' */ $currentNames): array {
    
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

    ///////////////////////////////////////////////////////////////////////////
    //append next names and handle parents
    
    $ret = [];
    foreach ($nextNames as $nameStructure) {
      $fullNN = $nameStructure['fullNN'];
      
      /* @var $nameDate GedcomDateInterval */
      $nameDate = $nameStructure['date'];
      
      //note that this $nameDate is (assumed to be) already properly intersected!
      
      /* @var $nameLang string */
      $nameLang = $nameStructure['lang'];
      
      $toMerge = SharedPlace::namesAsStringsSingleAt(
          $date,
          $primaryOnly,
          $xref, 
          $nextAlreadySeenXrefs,
          $fullNN,
          $nameDate, 
          $nameLang,
          $transitiveParents,
          $currentNames);
      
      $ret = array_merge($ret, $toMerge);
    }
    return $ret;
  }
  
  /**
   * 
   * @param bool $primaryOnly
   * @param string $xref
   * @param Collection $alreadySeenXrefs
   * @param array $nextNames
   * @param Collection $transitiveParents
   * @param array $currentNames
   * @return array place names (unnamed places and circular hierarchies properly handled)
   */
  private static function namesAsStringsAtA(
          bool $primaryOnly,
          string $xref /* for parents */,
          Collection $alreadySeenXrefs,
          array /* of array with nameStructure fields */ $nextNames,
          Collection $transitiveParents,
          array /* of array (keyed by language) of partially built names */ $currentNames): array {
    
    if ($alreadySeenXrefs->contains($xref)) {      
      //mark as circular and treat as leaf
      $ret = [];
      
      if (sizeof($currentNames) === 0) {
        throw new \Exception("unexpected!");
      }
      
      foreach ($currentNames as $heads) {
        foreach ($heads as $head) {
          if (strlen($head) === 0) {
            throw new \Exception("unexpected!");
          }
        
          //append
          $ret []= $head . Gedcom::PLACE_SEPARATOR . /*json_decode('"\u221E"') .*/ " <" . I18N::translate("circular shared place hierarchy") . ">";
        }        
      }
      
      return $ret;
    }
    
    //copy collection
    $nextAlreadySeenXrefs = $alreadySeenXrefs->map(function ($item) {
        return $item;
    });
    $nextAlreadySeenXrefs->add($xref);

    ///////////////////////////////////////////////////////////////////////////
    //append next names and handle parents
    
    //create batches with same original date
    //(we assume that names with same original date are conceptually just translations)
    //TODO EXPLAIN THIS - IMPORTANT!
    $byOriginalDate = [];
    foreach ($nextNames as $nameStructure) {
      /* @var $originalDate GedcomDateInterval */
      $originalDate = $nameStructure['originalDate'];
      $key = json_encode($originalDate);
      
      if (!array_key_exists($key, $byOriginalDate)) {
        $byOriginalDate[$key] = [];
      }
      
      $byOriginalDate[$key] []= $nameStructure;
    }
    
    $ret = [];
    foreach ($byOriginalDate as $batch) {
      //use each batch entry only with its language
      //(and the first entry as fallback for absent languages if necessary)
      
      //and then move on to next level
      //(note that for each batch, $nameDate must also be the same since $originalDate is the same)
      
      $toMerge = SharedPlace::namesAsStringsAtB(
          $primaryOnly,
          $xref, 
          $nextAlreadySeenXrefs,
          $batch,
          $transitiveParents,
          $currentNames);
      
      $ret = array_merge($ret, $toMerge);
    }

    return $ret;
  }
  
  private static function namesAsStringsAtB(
          bool $primaryOnly,
          string $xref,
          Collection $alreadySeenXrefs,
          array /* of nameStructure, all with same dates */ $nextBatch,
          Collection $transitiveParents,
          array /* of array (keyed by language) of partially built names */ $currentNames): array {

    $nextNames = [];
    if (sizeof($currentNames) === 0) {
      //roots      
      $firstLang = null;
      
      foreach ($nextBatch as $nameStructure) {
        /* @var $nameLang string */
        $nameLang = $nameStructure['lang'];
        
        if ($firstLang === null) {
          $firstLang = $nameLang;
        }
        
        if (!array_key_exists($nameLang, $nextNames)) {
          $nextNames[$nameLang] = [];
        }
        
        $nextName = $nameStructure['fullNN'];
        $nextNames[$nameLang][$nextName] = $nextName; //use key to avoid duplicates
      }
      
      if ($firstLang !== '') {
        //make sure to always add 'any-language', if necessary by duplicating entries
        $nextNames[''] = [];
        foreach ($nextNames[$firstLang] as $nextName) {
          $nextNames[''][$nextName] = $nextName; //use key to avoid duplicates
        }        
      }
    } else {

      $firstLang = null;
      $languages = [];   
      foreach ($nextBatch as $nameStructure) {
        /* @var $nameLang string */
        $nameLang = $nameStructure['lang'];
        
        if ($firstLang === null) {
          $firstLang = $nameLang;
        }
        
        $sourceLang = $nameLang;
        if (!array_key_exists($nameLang, $currentNames)) {
          //if there is a new language,
          //use the 'any-language' entries as prototype
          $sourceLang = '';
        }
        
        $languages[$nameLang] = $sourceLang;
      }
      
      //error_log("languages: ". print_r($languages, true));
      
      //also:
      //any language not seen in batch but present in current names:
      //use the first entry (or entries with the same language) from batch
      $substituteLangs = [];
      foreach ($currentNames as $lang => $unused) {
        if (!array_key_exists($lang, $languages)) {
          $substituteLangs []= $lang;
        }
      }      
      
      //error_log("substituteLangs: ". print_r($substituteLangs, true));
      
      foreach ($nextBatch as $nameStructure) {
        /* @var $nameLang string */
        $nameLang = $nameStructure['lang'];
        
        $nextName = $nameStructure['fullNN'];
        
        if ($nameLang === $firstLang) {
          foreach ($substituteLangs as $substituteLang) {
            if (!array_key_exists($substituteLang, $nextNames)) {
              $nextNames[$substituteLang] = [];
            }
            foreach ($currentNames[$substituteLang] as $head) {
              //append          
              $headAndNextName = $head . Gedcom::PLACE_SEPARATOR . $nextName;          
              $nextNames[$substituteLang][$headAndNextName] = $headAndNextName; //use key to avoid duplicates
              
              //error_log("substitute: '".$substituteLang."' = ".$headAndNextName);
            }
          }
        }
        
        $sourceLang = $languages[$nameLang];
        if (!array_key_exists($sourceLang, $nextNames)) {
          $nextNames[$sourceLang] = [];
        }
        
        foreach ($currentNames[$sourceLang] as $head) {
          //append          
          $headAndNextName = $head . Gedcom::PLACE_SEPARATOR . $nextName;          
          $nextNames[$nameLang][$headAndNextName] = $headAndNextName; //use key to avoid duplicates
          
          //error_log("regular: '".$nameLang."' = ".$headAndNextName);
        }
      }
    }

    //all dates are the same, so pick any
    $nameStructure = reset($nextBatch);
    
    /* @var $nameDate GedcomDateInterval */
    $nextDate = $nameStructure['date'];
    
    if ($nextDate === null) {
      error_log("unexpected:" . print_r($nextBatch, true));
    }
    
    return SharedPlace::namesAsStringsAtC(
          $primaryOnly,
          $xref, 
          $alreadySeenXrefs,
          $nextDate, 
          $transitiveParents,
          $nextNames);
  }
  
  private static function namesAsStringsAtC(
          bool $primaryOnly,
          string $xref,
          Collection $alreadySeenXrefs,
          GedcomDateInterval $nextDate,
          Collection $transitiveParents,
          array /* of array (keyed by language) of partially built names */ $currentNames): array {
    
    /* @var $parents Collection<SharedPlaceParentAt> */
    $parents = $transitiveParents->get($xref);
    
    $ret = [];
    foreach ($parents as $parent) {          

      /* @var $parent SharedPlaceParentAt */
      
      //special intersect! 
      $intersectedDate = $parent->getDate()->intersect($nextDate, true);
      
      if ($intersectedDate === null) {
        //irrelevant parent for this name
        continue;
      }
      
      /* @var $parentSharedPlace SharedPlace */
      $parentSharedPlace = $parent->getSharedPlace();
      
      if ($parentSharedPlace === null) {
        //leaf, just add $currentNames, flattened
        foreach ($currentNames as $lang => $currentNamesByLanguage) {
          //error_log($lang . ":" . print_r($currentNamesByLanguage, true));
          $ret = array_merge($ret, $currentNamesByLanguage);
        }
      } else {
        
        //we have an actual parent with an intersecting date
        //[2021/09] adjusted: seems more correct to use $intersectedDate here as well
        //$parentNames = $parentSharedPlace->getAllNamesAt($date);
        $parentNames = $parentSharedPlace->getAllNamesAt($intersectedDate, null);
        
        if ($primaryOnly) {
          //only use primary name, also restrict to primary parent
          $parentNames = [$parentNames[0]];
          
          return SharedPlace::namesAsStringsAtA(
              $primaryOnly, 
              $parentSharedPlace->xref(), 
              $alreadySeenXrefs,
              $parentNames,
              $transitiveParents,
              $currentNames);
        }

        $toMerge = SharedPlace::namesAsStringsAtA(
              $primaryOnly, 
              $parentSharedPlace->xref(), 
              $alreadySeenXrefs,
              $parentNames,
              $transitiveParents,
              $currentNames);
        
        $ret = array_merge($ret, $toMerge);
      }
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
          string $nextNameLang,
          Collection $transitiveParents,
          array /* of array with fields 'name' (partially built name hierarchy) and 'lang' */ $currentNames): array {
    
    //1. build names
    
    $nextNames = [];
    $nextNamesForLeaf = [];
    
    if (sizeof($currentNames) === 0) {
      //root
      $nextNames []= [
          'name' => $nextName,
          'lang' => $nextNameLang,
      ];
      $nextNamesForLeaf []= $nextName;
    } else {
      foreach ($currentNames as $head) {
        //append
        
        //but exclude certain language combinations
        //gah we need 
        //one current CONCAT all next IN ORDER TO ANALYZE
        //not
        //all current CONCAT one next
        //
        //we can flip this except for $nextNameDate
        //
        //so build names in a separate step, and then process further by currentName and date
        //tricky to get all combinations right
        //lang a range x-y-z CONCAT lang a range x-y sure but
        //lang a range x-y-z CONCAT lang b range y-z meh?
        //we even have to split up the ranges argh
        
        $headAndNextName = $head['name'] . Gedcom::PLACE_SEPARATOR . $nextName;
        
        $nextNames []= [
          'name' => $headAndNextName,
          'lang' => $nextNameLang,
        ];
        $nextNamesForLeaf []= $headAndNextName;
      }
    }
    
    //2. add parent names
    
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
        $ret = array_merge($ret, $nextNamesForLeaf);
      } else {
        
        //we have an actual parent with an intersecting date
        //[2021/09] adjusted: seems more correct to use $intersectedDate here as well
        //$parentNames = $parentSharedPlace->getAllNamesAt($date);
        $parentNames = $parentSharedPlace->getAllNamesAt($intersectedDate, null);
        
        if ($primaryOnly) {
          //only use primary name, also restrict to primary parent
          $parentNames = [$parentNames[0]];
          
          return SharedPlace::namesAsStringsAtOrig(
              $intersectedDate,
              $primaryOnly, 
              $parentSharedPlace->xref(), 
              $alreadySeenXrefs,
              $parentNames,
              $transitiveParents,
              $nextNames);
        }

        $toMerge = SharedPlace::namesAsStringsAtOrig(
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
  
  //TODO refactor via namesAsPlaceStringsAt
  public function namesAsPlacesAt(?GedcomDateInterval $date): array {
    if (!$this->useHierarchy()) {
      $places = new Collection();
      foreach ($this->getAllNamesAt($date, null) as $nameStructure) {
        $full = $nameStructure['fullNN'];
        $place = new Place($full, $this->tree);
        $places->put($place->id(), $place);
      }
      return $places->all();
    }

    $places = new Collection();
    $namesAsString = SharedPlace::namesAsStringsAt(
            $date,
            false,
            $this->xref(), 
            $this->getAllNamesAt($date, null),
            $this->getTransitiveParentsAt(($date == null)?GedcomDateInterval::createNow():$date));

    foreach ($namesAsString as $name) {
      $place = new Place($name, $this->tree);
      $places->put($place->id(), $place);
    }

    return $places->all();

    //note: impl via recursion would not be safe wrt loops   
  }
  
  //if shared places hierarchy is used, build returned place names via hierarchy!
  //but beware of loops (circular references in shared place hierarchy)
  public function namesAsPlaceStrings(): Collection {
    return $this->namesAsPlaceStringsAt(null);
  }
  
  public function namesAsPlaceStringsAt(?GedcomDateInterval $date): Collection {
    if (!$this->useHierarchy()) {
      $places = new Collection();
      foreach ($this->getAllNamesAt($date, null) as $nameStructure) {
        $full = $nameStructure['fullNN'];
        $places->add($full);
      }
      return $places;
    }

    $places = new Collection();
    $namesAsString = SharedPlace::namesAsStringsAt(
            $date,
            false,
            $this->xref(), 
            $this->getAllNamesAt($date, I18N::locale()->code()),
            $this->getTransitiveParentsAt(($date == null)?GedcomDateInterval::createNow():$date));

    foreach ($namesAsString as $name) {
      $places->add($name);
    }

    return $places;

    //note: impl via recursion would not be safe wrt loops   
  }
  
  public function primaryPlace(): Place {
    return $this->primaryPlaceAt(GedcomDateInterval::createNow());
  }
  
  public function primaryPlaceAt(GedcomDateInterval $date, ?string $query = null): Place {
    $firstMatch = null;
    
    if ($query !== null) {
      //which of the names best matches the query?
      //(note: none of them may match the query, which may have matched some other part of the location's gedcom)
      
      foreach ($this->getAllNamesAt($date, I18N::locale()->code()) as $nameAtDate) {
        $fullNN = $nameAtDate['fullNN'];
        if (Str::contains(mb_strtolower($fullNN), mb_strtolower($query))) {
          $firstMatch = $nameAtDate;
          break;
        }
      }
    }
    
    if ($firstMatch === null) {
      $firstMatch = $this->getAllNamesAt($date, I18N::locale()->code())[0];
    }
    
    if (!$this->useHierarchy()) {
      $fullNN = $firstMatch['fullNN'];
      return new Place($fullNN, $this->tree);
    }
      
    $names = [$firstMatch];
    
    $namesAsString = SharedPlace::namesAsStringsAt(
              $date,
              true,
              $this->xref(), 
              $names,
              $this->getTransitiveParentsAt($date));
    
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
    //$partsColl = new Collection(explode(Gedcom::PLACE_SEPARATOR, $placeGedcomName));
    //$parts = $partsColl->filter()->toArray();
    
    //.. but match badly formed separators as well
    $parts = preg_split(Gedcom::PLACE_SEPARATOR_REGEX, $placeGedcomName, -1, PREG_SPLIT_NO_EMPTY);
        
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
