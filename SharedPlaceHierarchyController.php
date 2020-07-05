<?php

namespace Cissee\Webtrees\Module\SharedPlaces;

use Cissee\WebtreesExt\Http\Controllers\LocationHierarchyController;
use Cissee\WebtreesExt\Services\SearchServiceExt;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Module\ModuleInterface;
use Fisharebest\Webtrees\Module\ModuleListInterface;
use Fisharebest\Webtrees\Module\PlaceHierarchyListModule;
use Fisharebest\Webtrees\Place;
use Fisharebest\Webtrees\PlaceLocation;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Services\SearchService;
use Fisharebest\Webtrees\Statistics;
use Fisharebest\Webtrees\Tree;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;
use ReflectionProperty;
use Vesta\Hook\HookInterfaces\FunctionsPlaceUtils;
use Vesta\Model\MapCoordinates;
use Vesta\Model\PlaceStructure;
use function app;
use function collect;
use function view;

class SharedPlaceHierarchyController extends GenericPlaceHierarchyController {

  protected $module;
  protected $search_service;
  protected $statistics;
  protected $hasLocationsToFix;
    
  public function __construct(
          SharedPlacesModule $module, 
          SearchService $search_service, 
          SearchServiceExt $search_service_ext, 
          Statistics $statistics,
          string $redirectUrl,
          bool $hasLocationsToFix) {
    
    parent::__construct($search_service, $search_service_ext, $statistics, $redirectUrl);
    $this->module = $module;
    $this->search_service = $search_service;
    $this->statistics = $statistics;
    $this->hasLocationsToFix = $hasLocationsToFix;
  }
  
  /*
  protected function mapData(Place $placeObj): array
  {
      $places    = $placeObj->getChildPlaces();
      $features  = [];
      $sidebar   = '';
      
      //$flag_path = Webtrees::MODULES_DIR . 'openstreetmap/'; 
      //currently not used anyway, see https://github.com/fisharebest/webtrees/issues/3219#issuecomment-625891368
      //and https://github.com/fisharebest/webtrees/issues/2436 etc
      
      $show_link = true;

      if ($places === []) {
          $places[] = $placeObj;
          $show_link = false;
      }

      //[RC] adjusted - this should be in webtrees
      //batch load PlaceLocations
      $placeLocations = collect([]);
      foreach ($places as $id => $place) {
          $location = new PlaceLocation($place->gedcomName());
          $placeLocations->add($location);
      };

      //Speedup #1
      //this isn't all that relevant after all!!
      //what's dead slow is Place->url() AND stats()
      self::loadIdsAndDetails($placeLocations);
      
      //might as well use ourself here (useful in case more than one module is activated)
      $urlProvider = $this->module;
      
      //Speedup #3
      //details only up to a given threshold 
      //(relevant for stats in general (i.e. should be in main webtrees),
      //but also for our more complex location function)
      $detailsThreshold = intval($this->module->getPreference('DETAILS_THRESHOLD', 100));
      $showDetails = sizeof($places) <= $detailsThreshold;
      
      foreach ($places as $id => $place) {
          $sidebar_class = '';
          $flag = '';
          
          if ($showDetails) {          
            
            //TODO we could preserve (flags and) zooms via PlaceLocation!
            $zoom = 13; //note: standard default is 2. 

            //[RC] adjusted
            $latLon = $this->getLatLon($place);

            if ($latLon === null) {
                $sidebar_class = 'unmapped';
            } else {
                $latitude = $latLon->getLati();
                $longitude = $latLon->getLong();
              
                $sidebar_class = 'mapped';
                $features[]    = [
                    'type'       => 'Feature',
                    'id'         => $id,
                    'geometry'   => [
                        'type'        => 'Point',
                        'coordinates' => [$longitude, $latitude],
                    ],
                    'properties' => [
                        'tooltip' => $place->gedcomName(),
                        'popup'   => view('modules/place-hierarchy/popup', [
                            'showlink'  => $show_link,
                            'flag'      => $flag,
                            'place'     => $place,
                            'latitude'  => $latitude,
                            'longitude' => $longitude,
                        ]),
                        'zoom'    => $zoom,
                    ],
                ];
            }
          } 

          //Stats
          $placeStats = [];
          foreach (['INDI', 'FAM'] as $type) {
              if ($showDetails) {
                $tmp = $this->statistics->statsPlaces($type, '', $place->id());
              } else {
                $tmp = [];
              }
              
              $placeStats[$type] = $tmp === [] ? 0 : $tmp[0]->tot;
          }
          
          $sidebar .= view($this->module->name() . '::sidebar', [
              'showlink'      => $show_link,
              'flag'          => $flag,
              'id'            => $id,
              'place'         => $place,
              'url'           => self::placeUrl($place, $urlProvider),
              'sidebar_class' => $sidebar_class,
              'stats'         => $placeStats,
              'showDetails'   => $showDetails
          ]);
      }

      $bounds = [];
      $placeLocation = (new PlaceLocation($placeObj->gedcomName()));
      $bounds = $placeLocation->boundingRectangle();         
      
      return [
          'bounds'  => $bounds,
          'sidebar' => $sidebar,
          'markers' => [
              'type'     => 'FeatureCollection',
              'features' => $features,
          ]
      ];
  }
  
  public static function loadIdsAndDetails(Collection $placeLocations): void {
    //we load in batches, one per hierarchy level (higher levels first)
    //note: ultimately it may be easier to simply load the entire table??
    $parts_ = new ReflectionProperty('Fisharebest\Webtrees\PlaceLocation', 'parts');
    $parts_->setAccessible(true);
    $location_name_ = new ReflectionProperty('Fisharebest\Webtrees\PlaceLocation', 'location_name');
    $location_name_->setAccessible(true);

    $batches = [];
    
    foreach ($placeLocations as $placeLocation) {
      $pl = $placeLocation;
      do {
        $parts = $parts_->getValue($pl);
        $level = count($parts)-1;
        
        if ($level >= 0) {
          if (!array_key_exists($level, $batches)) {
            $batches[$level] = collect([]);
          }
          $batches[$level]->add($pl);
          $pl = $pl->parent();
        }
      } while ($level >= 0);
    }
    
    //now load batches in turn
    
    $level = 0;
    while (array_key_exists($level, $batches)) {
      $pls = $batches[$level]
              ->unique()
              ->map(static function (PlaceLocation $placeLocation): string {
                return $placeLocation->locationName();
              })->toArray();
            
      //note: this may load a bit too much (same pl_place, same pl_level, different pl_parent_id) - nevermind!
      $rows = DB::table('placelocation')
                ->whereIn('pl_place', $pls)
                ->where('pl_level', '=', $level)
                ->get();
      
      $rowsKeyed = [];
      foreach ($rows as $row) {
        $parentId = $row->pl_parent_id;
        $name = $row->pl_place;
        $key = $parentId . ':' . $name;
        $rowsKeyed[$key] = $row;
      }
      
      foreach ($batches[$level]->unique() as $placeLocation) {
        //ok (we have batch loaded parents already)
        $parentId = $placeLocation->parent()->id();
        $name = $placeLocation->locationName();        
        $key = $parentId . ':' . $name;
        
        if (array_key_exists($key, $rowsKeyed)) {
          $row = $rowsKeyed[$key];
          
          $location_id = (int)$row->pl_id;
          $location_name = $location_name_->getValue($placeLocation);
        
          app('cache.array')->remember('location-' . $location_name, function () use ($location_id) {            
            return $location_id;
          });

          app('cache.array')->remember('location-details-' . $location_id, function () use ($row) {
            return $row;
          });
        }
      }

      $level++;
    }
  }
  
  public static function placeUrlProvider(Tree $tree): ?PlaceHierarchyListModule {
    //find a module providing the place hierarchy
    return app(ModuleService::class)
        ->findByComponent(ModuleListInterface::class, $tree, Auth::user())
        ->first(static function (ModuleInterface $module): bool {
            return $module instanceof PlaceHierarchyListModule;
        });
  }
    
  //Speedup #2
  //more efficient than $place->url() when calling for lots of places
  //this should be in webtrees, e.g. via caching result of 'findByComponent' or even 'Auth::accessLevel'
  public static function placeUrl(Place $place, ?PlaceHierarchyListModule $phlm): string
    {
        if ($phlm !== null) {
            return $phlm->listUrl($place->tree(), [
                'place_id' => $place->id(),
                'tree'     => $place->tree()->name(),
            ]);
        }

        // The place-list module is disabled...
        return '#';
    }
    
  protected function getLatLon(Place $place): ?MapCoordinates {
    $ps = PlaceStructure::fromPlace($place);
    if ($ps === null) {
      return null;
    }
    return FunctionsPlaceUtils::plac2map($this->module, $ps, false);
  }
  */
}
