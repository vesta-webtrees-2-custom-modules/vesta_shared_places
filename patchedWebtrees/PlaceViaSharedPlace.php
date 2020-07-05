<?php

namespace Cissee\WebtreesExt;

use Cissee\Webtrees\Module\SharedPlaces\SharedPlacesModule;
use Cissee\WebtreesExt\Http\Controllers\PlaceBaseWithinHierarchy;
use Cissee\WebtreesExt\Http\Controllers\PlaceWithinHierarchy;
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

class PlaceViaSharedPlace implements PlaceWithinHierarchy {
  
  /** @var Place */
  protected $actual;
  
  /** @var SharedPlace|null */
  protected $sharedPlace;
          
  /** @var SharedPlacesModule|null */
  protected $module;
  
  /** @var SearchServiceExt */
  protected $search_service_ext;
  
  /** @var MapCoordinates|null */
  protected $latLon = null;
  
  protected $latLonInitialized = false;
  
  public function __construct(
          Place $actual,
          ?SharedPlace $sharedPlace,
          ?SharedPlacesModule $module,
          SearchServiceExt $search_service_ext) {
    
    $this->actual = $actual;
    $this->sharedPlace = $sharedPlace;
    $this->module = $module;
    $this->search_service_ext = $search_service_ext;    
  }
  
  //only required for breadcrumbs, so no need to override
  //public function parent(): Place {
  
  public function getChildPlaces(): array {
    $self = $this;
    if ($this->sharedPlace === null) {      
      //top-level
      return $this->search_service_ext
              ->searchTopLevelLocations([$this->tree()])
              ->filter(GedcomRecord::accessFilter())
              ->map(static function (Location $record) use ($self): PlaceViaSharedPlace {
                $name = $record->namesNN()[$record->getPrimaryName()];
                $actual = new Place($name, $record->tree());
                $actual->id(); //make sure place exists in db
                return new PlaceViaSharedPlace($actual, $record, $self->module, $self->search_service_ext);
              })
              ->sort(static function (PlaceViaSharedPlace $x, PlaceViaSharedPlace $y): int {
                return strtolower($x->gedcomName()) <=> strtolower($y->gedcomName());
              })
              ->toArray();
    }
    
    return $this->sharedPlace
            ->linkedLocations('_LOC')
            ->filter(GedcomRecord::accessFilter())
            ->map(static function (Location $record) use ($self): PlaceViaSharedPlace {
                $name = $record->namesNN()[$record->getPrimaryName()];
                $actual = new Place($name . Gedcom::PLACE_SEPARATOR . $self->gedcomName(), $record->tree());
                $actual->id(); //make sure place exists in db
                return new PlaceViaSharedPlace($actual, $record, $self->module, $self->search_service_ext);
              })
            ->sort(static function (PlaceViaSharedPlace $x, PlaceViaSharedPlace $y): int {
              return strtolower($x->gedcomName()) <=> strtolower($y->gedcomName());
            })
            ->toArray();
  }
  
  public function id(): int {
    return $this->actual->id();
  }
  
  public function url(): string {
    if ($this->module !== null) {
        return $this->module->listUrl($this->tree(), [
            'place_id' => $this->id(),
            'tree'     => $this->tree()->name(),
        ]);
    }

    // The place-list module is disabled...
    return '#';
  }
  
  public function tree(): Tree {
    return $this->actual->tree();
  }
  
  public function gedcomName(): string {
    return $this->actual->gedcomName();
  }
    
  public function parent(): Place {
    return $this->actual->parent();
  }
  
  public function placeName(): string {
    return $this->actual->placeName();
  }
  
  public function fullName(bool $link = false): string {
    return $this->actual->fullName($link);
  }
  
  //SearchService::searchIndividualsInPlace
  public function searchIndividualsInPlace(): Collection {
    if ($this->sharedPlace !== null) {
      return $this->sharedPlace->linkedIndividuals('_LOC');
    }
    return new Collection();    
  }
  
  //SearchService::searchFamiliesInPlace
  public function searchFamiliesInPlace(): Collection {
    if ($this->sharedPlace !== null) {
      return $this->sharedPlace->linkedFamilies('_LOC');
    }
    return new Collection();
  }
  
  protected function getLatLon(): ?MapCoordinates {
    $ps = PlaceStructure::fromPlace($this->actual);
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
      if ($this->sharedPlace === null) {
        //why doesn't original impl calculate bounding rectangle for world? Too expensive?
        return [[-180.0, -90.0], [180.0, 90.0]];
      }
      */

      $latitudes = [];
      $longitudes = [];
      
      if ($this->sharedPlace !== null) {
        if ($this->latitude() !== 0.0) {
          $latitudes[] = $this->latitude();
        }
        if ($this->longitude() !== 0.0) {
          $longitudes[] = $this->longitude();
        }
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
      
      if ($latiMin === $latiMax) {
        $latiMin -= 0.5;
        $latiMax += 0.5;
      }
      
      if ($longMin === $longMax) {
        $longMin -= 0.5;
        $longMax += 0.5;
      }
      
      return [[$latiMin, $longMin], [$latiMax, $longMax]];
  }
    
  ////////////////////////////////////////////////////////////////////////////////  
  //own extensions

  public function additionalLinksHtmlBeforeName(): string {
    if ($this->module !== null) {
      if ($this->sharedPlace !== null) {
        return $this->module->getLinkForSharedPlace($this->sharedPlace);
      }
    }
    
    return '';
  }
  
}
