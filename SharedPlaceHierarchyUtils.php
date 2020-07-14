<?php

namespace Cissee\Webtrees\Module\SharedPlaces;

use Cissee\WebtreesExt\Http\Controllers\PlaceHierarchyUtils;
use Cissee\WebtreesExt\Http\Controllers\PlaceUrls;
use Cissee\WebtreesExt\Http\Controllers\PlaceWithinHierarchy;
use Cissee\WebtreesExt\PlaceViaSharedPlace;
use Cissee\WebtreesExt\Services\SearchServiceExt;
use Fisharebest\Webtrees\Factory;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Place;
use Fisharebest\Webtrees\Tree;
use Illuminate\Support\Collection;

//obsolete
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
  
  public function findPlace(int $id, Tree $tree, array $requestParameters_unused): PlaceWithinHierarchy {
    $actual = Place::find($id, $tree);
    
    //find matching shared places, otherwise reset
    $sharedPlaces = $this->module->placename2sharedPlacesImpl($actual->gedcomName(), $actual->tree(), true);
    if ($sharedPlaces->count() === 0) {
      $actual = new Place('', $actual->tree());
    }
    
    $urls = new PlaceUrls($this->module, [], new Collection());
    return new PlaceViaSharedPlace($actual, $urls, $sharedPlaces, $this->module, $this->search_service_ext);
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
    if ($locationsToFix->count() > 0) {
      error_log("Locations to fix:");
      foreach ($locationsToFix as $locationToFix) {
        error_log($locationToFix);
        error_log(Factory::location()->make($locationToFix, $tree)->gedcom());
      }
    }
    return ($locationsToFix->count() > 0);
  }
}
