<?php

namespace Cissee\Webtrees\Module\SharedPlaces;

use Cissee\WebtreesExt\Http\Controllers\PlaceHierarchyUtils;
use Cissee\WebtreesExt\Http\Controllers\PlaceWithinHierarchy;
use Cissee\WebtreesExt\PlaceViaSharedPlace;
use Cissee\WebtreesExt\Services\SearchServiceExt;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Location;
use Fisharebest\Webtrees\Place;
use Fisharebest\Webtrees\Tree;
use Illuminate\Support\Collection;

class SharedPlaceHierarchyUtils implements PlaceHierarchyUtils {
  
  /** @var SharedPlacesModule */
  protected $module;
  
  /** @var SearchServiceExt */
  protected $search_service_ext;
  
  public function __construct(
          SharedPlacesModule $module,
          SearchServiceExt $search_service_ext) {
    
    $this->module = $module;
    $this->search_service_ext = $search_service_ext;
  }
  
  public function findPlace(int $id, Tree $tree): PlaceWithinHierarchy {
    $actual = Place::find($id, $tree);
    
    //find matching shared places, otherwise reset
    $sharedPlaces = $this->module->placename2sharedPlacesImpl($actual->gedcomName(), $actual->tree(), true);
    if ($sharedPlaces->count() === 0) {
      $actual = new Place('', $actual->tree());
    }
    
    return new PlaceViaSharedPlace($actual, $sharedPlaces, $this->module, $this->search_service_ext);
  }
  
  //SearchService::searchPlaces
  public function searchPlaces(Tree $tree): Collection {
    $self = $this;
    return $this->search_service_ext
            ->searchLocations([$tree], [])
            ->filter(GedcomRecord::accessFilter())
            ->map(static function (Location $record): array {
              $actual = $record->canonicalPlace();
              $actual->id(); //make sure place exists in db
              //return new PlaceViaSharedPlace($actual, $record, $self->module, $self->search_service_ext);
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
              return new PlaceViaSharedPlace($first["actual"], $sharedPlaces, $self->module, $self->search_service_ext);
            })
            ->sort(static function (PlaceViaSharedPlace $x, PlaceViaSharedPlace $y): int {
              return strtolower($x->gedcomName()) <=> strtolower($y->gedcomName());
            });
  }
    
  public function hierarchyActionLabel(): string {
    return I18N::translate('Show shared place hierarchy');
  }
  
  public function listActionLabel(): string {
    return I18N::translate('Show all shared places in a list');
  }
  
  public function pageLabel(): string {
    return I18N::translate('Shared places');
  }
  
  public function placeHierarchyView(): string {
    return 'modules/generic-place-hierarchy-shared-places/place-hierarchy';
  }
  
  public function listView(): string {
    return 'modules/generic-place-hierarchy-shared-places/list';
  }
  
  public function pageView(): string {
    return 'modules/generic-place-hierarchy-shared-places/page';
  }
  
  public function sidebarView(): string {
    return 'modules/generic-place-hierarchy-shared-places/sidebar';
  }
  
  ////////////////////////////////////////////////////////////////////////////////
  // extensions for own view
    
  public function hasLocationsToFix(Tree $tree): bool {
    $locationsToFix = $this->module->locationsToFix($tree, []);
    return ($locationsToFix->count() > 0);
  }
}
