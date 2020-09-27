<?php

namespace Cissee\WebtreesExt;

use Cissee\Webtrees\Module\SharedPlaces\SharedPlacesModule;
use Cissee\WebtreesExt\Http\Controllers\PlaceUrls;
use Cissee\WebtreesExt\Http\Controllers\PlaceWithinHierarchy;
use Cissee\WebtreesExt\Services\SearchServiceExt;
use Fisharebest\Webtrees\Factory;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\Location;
use Fisharebest\Webtrees\Place;
use Fisharebest\Webtrees\Services\GedcomService;
use Fisharebest\Webtrees\Tree;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use stdClass;
use Vesta\Hook\HookInterfaces\FunctionsPlaceUtils;
use Vesta\Model\GedcomDateInterval;
use Vesta\Model\LocReference;
use Vesta\Model\MapCoordinates;
use Vesta\Model\PlaceStructure;
use Vesta\Model\Trace;
use function app;

class PlaceViaSharedPlace implements PlaceWithinHierarchy {
  
  protected $actual;
  
  /** @var PlaceUrls */
  protected $urls;
  
  protected $module;
          
  /** @var Collection */
  protected $sharedPlaces;
  
  /** @var SearchServiceExt */
  protected $search_service_ext;
  
  /** @var MapCoordinates|null */
  protected $latLon = null;
  
  protected $latLonInitialized = false;
  
  public function __construct(
          Place $actual,
          PlaceUrls $urls,
          Collection $sharedPlaces,
          SharedPlacesModule $module,
          SearchServiceExt $search_service_ext) {

    $this->actual = $actual;
    $this->urls = $urls;
    $this->module = $module;
    $this->sharedPlaces = $sharedPlaces;
    $this->search_service_ext = $search_service_ext;    
  }  
    
  //Speedup
  //more efficient than $this->actual->url() when calling for lots of places
  //this should be in webtrees, e.g. via caching result of 'findByComponent' or even 'Auth::accessLevel'
  public function url(): string {
    return $this->urls->url($this->actual);
  }
  
  public function gedcomName(): string {
    return $this->actual->gedcomName();
  }
  
  public function placeName(): string {
    return $this->actual->placeName();
  }
  
  //cf DefaultPlaceWithinHierarchy, but map to SharedPlace/PlaceViaSharedPlace directly from query
  public function getChildPlacesCacheIds(Place $place): Collection {
    $self = $this;
    
    if ($place->gedcomName() !== '') {
        $parent_text = Gedcom::PLACE_SEPARATOR . $place->gedcomName();
    } else {
        $parent_text = '';
    }

    $tree = $place->tree();

    return DB::table('places')
        ->where('p_file', '=', $tree->id())
        ->where('p_parent_id', '=', $place->id())
        ->join('placelinks', static function (JoinClause $join): void {
            $join
                ->on('pl_file', '=', 'p_file')                    
                ->on('pl_p_id', '=', 'p_id');
        })
        ->join('other', static function (JoinClause $join): void {
            $join
                ->on('o_file', '=', 'pl_file')                    
                ->on('o_id', '=', 'pl_gid');
        })
        ->where('o_type', '=', '_LOC')
        ->get()
        ->map(function (stdClass $row) use ($parent_text, $tree): array {
            $place = new Place($row->p_place . $parent_text, $tree);
            $id = $row->p_id;
            app('cache.array')->remember('place-' . $place->gedcomName(), function () use ($id): int {return $id;});
            
            $sharedPlace = Factory::location()->mapper($tree)($row);
            return ["actual" => $place, "record" => $sharedPlace];
        })
        ->mapToGroups(static function ($item): array {
          $place = $item["actual"];
          return [$place->id() => $item];
        })
        ->map(static function (Collection $groupedItems) use ($self): PlaceViaSharedPlace {
          $first = $groupedItems->first();
          $sharedPlaces = $groupedItems
                  ->map(static function (array $inner): SharedPlace {
                      return $inner["record"];
                  })
                  ->unique(function (SharedPlace $sharedPlace): string {
                      return $sharedPlace->xref();
                  });

          return new PlaceViaSharedPlace($first["actual"], $self->urls, $sharedPlaces, $self->module, $self->search_service_ext);
        })
        ->sort(static function (PlaceViaSharedPlace $x, PlaceViaSharedPlace $y): int {
          return strtolower($x->gedcomName()) <=> strtolower($y->gedcomName());
        })
        //
        ->mapWithKeys(static function (PlaceViaSharedPlace $place): array {
          return [$place->id() => $place];
        });
  }
  
  public function getChildPlaces(): array {
    $ret = $this
            ->getChildPlacesCacheIds($this->actual)
            ->toArray();
    
    return $ret;
  }
  
  //needlessly complicated! 
  public function getChildPlacesLegacy(): array {
    $self = $this;
    if ($this->actual->id() === 0) {      
      
      //top-level
      $ret = $this->search_service_ext
              ->searchTopLevelLocations([$this->tree()])
              ->filter(GedcomRecord::accessFilter())
              ->flatMap(static function (SharedPlace $record): array {
                //don't use the primary name only - confusing because other names exist in hierarchy anyway via places table
                //$name = $record->namesNN()[$record->getPrimaryName()];                
                //$actual = new Place($name, $record->tree());
                //$actual->id(); //make sure place exists in db
                //return ["actual" => $actual, "record" => $record];
                
                //TODO: optimize - filter immediately to top-level via arg?
                $places = $record->namesAsPlacesAt(GedcomDateInterval::createEmpty());
                
                $ret = [];                
                foreach ($places as $place) {
                  //only use the top-level place names!
                  /* @var $place Place */
                  if ($place->parent()->id() === 0) {
                    $ret []= ["actual" => $place, "record" => $record];
                  }                  
                }                
                return $ret;
              })
              ->mapToGroups(static function ($item): array {
                $place = $item["actual"];
                return [$place->id() => $item];
              })
              ->map(static function (Collection $groupedItems) use ($self): PlaceViaSharedPlace {
                $first = $groupedItems->first();
                $sharedPlaces = $groupedItems
                        ->map(static function (array $inner): SharedPlace {
                            return $inner["record"];
                        })
                        ->unique(function (SharedPlace $sharedPlace): string {
                            return $sharedPlace->xref();
                        });
                        
                return new PlaceViaSharedPlace($first["actual"], $self->urls, $sharedPlaces, $self->module, $self->search_service_ext);
              })
              ->sort(static function (PlaceViaSharedPlace $x, PlaceViaSharedPlace $y): int {
                return strtolower($x->gedcomName()) <=> strtolower($y->gedcomName());
              })
              //
              ->mapWithKeys(static function (PlaceViaSharedPlace $place): array {
                return [$place->id() => $place];
              })
              ->toArray();
              
      return $ret;        
    }
    
    $ret = new Collection();
    foreach ($this->sharedPlaces as $sharedPlace) {
      $ret = $ret->merge(
            $sharedPlace
            ->linkedLocations('_LOC')
            ->filter(GedcomRecord::accessFilter())
            ->map(static function (Location $record) use ($self): array {
              //TODO: must choose proper name here - which isn'T always the primary name!
              $name = $record->namesNN()[$record->getPrimaryName()];
              $actual = new Place($name . Gedcom::PLACE_SEPARATOR . $self->gedcomName(), $record->tree());
              $actual->id(); //make sure place exists in db
              return ["actual" => $actual, "record" => $record];
            })
            ->mapToGroups(static function ($item): array {
              //group by place id
              $place = $item["actual"];
              return [$place->id() => $item];
            })
            ->map(static function (Collection $groupedItems) use ($self): PlaceViaSharedPlace {
              $first = $groupedItems->first();
              $sharedPlaces = $groupedItems->map(static function ($inner): Location {
                return $inner["record"];
              });
              return new PlaceViaSharedPlace($first["actual"], $self->urls, $sharedPlaces, $self->module, $self->search_service_ext);
            })
            ->sort(static function (PlaceViaSharedPlace $x, PlaceViaSharedPlace $y): int {
              return strtolower($x->gedcomName()) <=> strtolower($y->gedcomName());
            }));
    }
    
    return $ret
            ->unique()
            //must do this after merging because we use numeric keys
            ->mapWithKeys(static function (PlaceViaSharedPlace $place): array {
              return [$place->id() => $place];
            })
            ->toArray();
  }
  
  public function id(): int {
    return $this->actual->id();
  }
  
  public function tree(): Tree {
    return $this->actual->tree();
  }
  
  public function fullName(bool $link = false): string {
    return $this->actual->fullName($link);
  }
  
  public function searchIndividualsInPlace(): Collection {
    return SharedPlace::linkedIndividualsRecords($this->sharedPlaces);
  }
  
  public function countIndividualsInPlace(): int {
    return SharedPlace::linkedIndividualsCount($this->sharedPlaces);
  }
  
  public function searchFamiliesInPlace(): Collection {
    return SharedPlace::linkedFamiliesRecords($this->sharedPlaces);
  }
  
  public function countFamiliesInPlace(): int {
    return SharedPlace::linkedFamiliesCount($this->sharedPlaces);
  }
  
  protected function initLatLon(): ?MapCoordinates {
    $useIndirectLinks = boolval($this->module->getPreference('INDIRECT_LINKS', '1'));
    
    if (!$useIndirectLinks) {
      //check shared places directly, they won't be checked via plac2map
      foreach ($this->sharedPlaces as $sharedPlace) {
        /* @var $sharedPlace SharedPlace */
        
        $locReference = new LocReference($sharedPlace->xref(), $sharedPlace->tree(), new Trace(''));
        $mapCoordinates = FunctionsPlaceUtils::loc2map($this->module, $locReference);
        if ($mapCoordinates !== null) {
          return $mapCoordinates;
        }
      }
    }
    
    $ps = $this->placeStructure();
    if ($ps === null) {
      return null;
    }
    return FunctionsPlaceUtils::plac2map($this->module, $ps, false);
  }
  
  public function getLatLon(): ?MapCoordinates {
    if (!$this->latLonInitialized) {
      $this->latLon = $this->initLatLon();
      $this->latLonInitialized = true;
    }
    
    return $this->latLon;
  }
  
  public function latitude(): float {
    //we don't go up the hierarchy here - there may be more than one parent!
    
    $lati = null;
    if ($this->getLatLon() !== null) {
      $lati = $this->getLatLon()->getLati();
    }
    if ($lati === null) {
      return 0.0;
    }
    
    $gedcom_service = new GedcomService();
    return $gedcom_service->readLatitude($lati);
  }
  
  public function longitude(): float {
    //we don't go up the hierarchy here - there may be more than one parent!
    
    $long = null;
    if ($this->getLatLon() !== null) {
      $long = $this->getLatLon()->getLong();
    }
    if ($long === null) {
      return 0.0;
    }
    
    $gedcom_service = new GedcomService();
    return $gedcom_service->readLongitude($long);
  }
  
  public function icon(): string {
    return '';
  }
  
  public function boundingRectangleWithChildren(array $children): array
  {
      /*
      if (top-level) {
        //why doesn't original impl calculate bounding rectangle for world? Too expensive?
        return [[-180.0, -90.0], [180.0, 90.0]];
      }
      */

      $latitudes = [];
      $longitudes = [];
      
      if ($this->latitude() !== 0.0) {
        $latitudes[] = $this->latitude();
      }
      if ($this->longitude() !== 0.0) {
        $longitudes[] = $this->longitude();
      }
      
      foreach ($children as $child) { 
        if ($child->latitude() !== 0.0) {
          $latitudes[] = $child->latitude();
        }
        if ($child->longitude() !== 0.0) {
          $longitudes[] = $child->longitude();
        }
      }

      if ((count($latitudes) === 0) || (count($longitudes) === 0)) {
        return [[-180.0, -90.0], [180.0, 90.0]];
      }
      
      $latiMin = (new Collection($latitudes))->min();
      $longMin = (new Collection($longitudes))->min();
      $latiMax = (new Collection($latitudes))->max();
      $longMax = (new Collection($longitudes))->max();
      
      //never zoom in too far (in particular if there is only one place, but also if the places are close together)
      $latiSpread = $latiMax - $latiMin;
      if ($latiSpread < 1) {
        $latiMin -= (1 - $latiSpread)/2;
        $latiMax += (1 - $latiSpread)/2;
      }

      $longSpread = $longMax - $longMin;
      if ($longSpread < 1) {
        $longMin -= (1 - $longSpread)/2;
        $longMax += (1 - $longSpread)/2;
      }
      
      return [[$latiMin, $longMin], [$latiMax, $longMax]];
  }

  public function placeStructure(): ?PlaceStructure {
    return PlaceStructure::fromPlace($this->actual);
  }

  public function additionalLinksHtmlBeforeName(): string {
    $html = '';
    if ($this->module !== null) {      
      foreach ($this->sharedPlaces as $sharedPlace) {
        $html .= $this->module->getLinkForSharedPlace($sharedPlace);
      }
    }
    
    return $html;
  }
  
  public function links(): Collection {
    return $this->urls->links($this->actual);
  }
  
  public function parent(): PlaceWithinHierarchy {
    return $this->module->findPlace($this->actual->parent()->id(), $this->actual->tree(), $this->urls);
  }
}
