<?php

namespace Cissee\WebtreesExt;

use Cissee\Webtrees\Module\SharedPlaces\SharedPlacesModule;
use Cissee\WebtreesExt\Http\Controllers\DefaultPlaceWithinHierarchyBase;
use Cissee\WebtreesExt\Http\Controllers\PlaceWithinHierarchy;
use Cissee\WebtreesExt\Http\Controllers\PlaceUrls;
use Cissee\WebtreesExt\Services\SearchServiceExt;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\Location;
use Fisharebest\Webtrees\Place;
use Fisharebest\Webtrees\Services\GedcomService;
use Fisharebest\Webtrees\Tree;
use Illuminate\Support\Collection;
use Vesta\Hook\HookInterfaces\FunctionsPlaceUtils;
use Vesta\Model\MapCoordinates;
use Vesta\Model\PlaceStructure;

class PlaceViaSharedPlace extends DefaultPlaceWithinHierarchyBase implements PlaceWithinHierarchy {
  
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
          ?SharedPlacesModule $module,
          SearchServiceExt $search_service_ext) {
    
    parent::__construct($actual, $urls);
    $this->urls = $urls;
    $this->module = $module;
    $this->sharedPlaces = $sharedPlaces;
    $this->search_service_ext = $search_service_ext;    
  }
  
  public function getChildPlaces(): array {
    $self = $this;
    if ($this->actual->id() === 0) {      
      //top-level
      return $this->search_service_ext
              ->searchTopLevelLocations([$this->tree()])
              ->filter(GedcomRecord::accessFilter())
              ->map(static function (Location $record): array {
                $name = $record->namesNN()[$record->getPrimaryName()];
                $actual = new Place($name, $record->tree());
                $actual->id(); //make sure place exists in db
                return ["actual" => $actual, "record" => $record];
              })
              ->mapToGroups(static function ($item): array {
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
              })
              //
              ->mapWithKeys(static function (PlaceViaSharedPlace $place): array {
                return [$place->id() => $place];
              })
              ->toArray();
    }
    
    $ret = new Collection();
    foreach ($this->sharedPlaces as $sharedPlace) {
      $ret = $ret->merge(
            $sharedPlace
            ->linkedLocations('_LOC')
            ->filter(GedcomRecord::accessFilter())
            ->map(static function (Location $record) use ($self): array {
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
    $ret = new Collection();
    foreach ($this->sharedPlaces as $sharedPlace) {
      $ret = $ret->merge($sharedPlace->linkedIndividuals('_LOC'));
    }
    return $ret->unique();    
  }
  
  public function countIndividualsInPlace(): int {
    return $this->searchIndividualsInPlace()->count();
  }
  
  public function searchFamiliesInPlace(): Collection {
    $ret = new Collection();
    foreach ($this->sharedPlaces as $sharedPlace) {
      $ret = $ret->merge($sharedPlace->linkedFamilies('_LOC'));
    }
    return $ret->unique(); 
  }
  
  public function countFamiliesInPlace(): int {
    return $this->searchFamiliesInPlace()->count();
  }
  
  protected function getLatLon(): ?MapCoordinates {
    $ps = $this->placeStructure();
    if ($ps === null) {
      return null;
    }
    return FunctionsPlaceUtils::plac2map($this->module, $ps, false);
  }
  
  public function latitude(): float {
    if (!$this->latLonInitialized) {
      $this->latLon = $this->getLatLon();
      $this->latLonInitialized = true;
    }
    
    //we don't go up the hierarchy here - there may be more than one parent!
    
    $lati = null;
    if ($this->latLon !== null) {
      $lati = $this->latLon->getLati();
    }
    if ($lati === null) {
      return 0.0;
    }
    
    $gedcom_service = new GedcomService();
    return $gedcom_service->readLatitude($lati);
  }
  
  public function longitude(): float {
    if (!$this->latLonInitialized) {
      $this->latLon = getLatLon();
      $this->latLonInitialized = true;
    }

    //we don't go up the hierarchy here - there may be more than one parent!
    
    $long = null;
    if ($this->latLon !== null) {
      $long = $this->latLon->getLong();
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
    //TODO OPTIMIZE with _LOC and coordinates, if available
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
}
