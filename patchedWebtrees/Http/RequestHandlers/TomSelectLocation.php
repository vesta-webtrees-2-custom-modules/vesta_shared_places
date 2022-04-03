<?php

declare(strict_types=1);

namespace Cissee\WebtreesExt\Http\RequestHandlers;

use Cissee\WebtreesExt\Services\SearchServiceExt;
use Fisharebest\Webtrees\Location;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\SearchService;
use Fisharebest\Webtrees\Tree;
use Illuminate\Support\Collection;
use Vesta\Model\GedcomDateInterval;
use function array_filter;
use function explode;

//[RC] extended with 'date'

/**
 * Autocomplete for locations.
 */
class TomSelectLocation extends AbstractTomSelectWithDateHandler
{
    protected SearchServiceExt $search_service;

    /**
     * TomSelectLocation constructor.
     *
     * @param SearchService $search_service
     */
    public function __construct(
        SearchServiceExt $search_service
    ) {
        $this->search_service = $search_service;
    }

    /**
     * Perform the search
     *
     * @param Tree   $tree
     * @param GedcomDateInterval   $date
     * @param string $query
     * @param int    $offset
     * @param int    $limit
     * @param string $at
     *
     * @return Collection<int,array{text:string,value:string}>
     */
    protected function search(
            Tree $tree, 
            GedcomDateInterval $date, 
            string $query, 
            int $offset, 
            int $limit, 
            string $at): Collection
    {
        // Search by XREF
        $location = Registry::locationFactory()->make($query, $tree);

        $paginate = false;
        
        if ($location instanceof Location) {
            $results = new Collection([$location]);
        } else {
            if (str_contains($query,',')) {
              //[PATCHED]
              //extended in order to find hierarchical shared places
              //overall not very efficient
              //TODO strictly only required if hierarchical shared places are enabled!
              
              $places = $this->search_service->searchPlaces($tree, $query);
              
              $results1 = $this->search_service->searchLocationsInPlaces($tree, $places);

              //add 'regular' results
              $results2 = $this->search_service->searchLocations([$tree], [$query]);
              
              $results = $results1->merge($results2)
                      
                      //skip duplicates
                      ->unique();
              
              $paginate = true;
              
            } else {
                $search  = array_filter(explode(' ', $query));
                $results = $this->search_service->searchLocations([$tree], $search, $offset, $limit);
            }
        }
        
        //[PATCHED]
        $ret = $results
                
                ->map(static function (Location $location) use ($at, $date, $query): array {
                    return [
                        'value' => $at . $location->xref() . $at,
                        'text'  => view('selects/location', ['location' => $location]),
                        'title' => $location->primaryPlaceAt($date, $query)->gedcomName(),
                    ];
                })                
                                      
                //sort
                ->sort(static function (array $x, array $y): int {
                    return $x['text'] <=> $y['text'];
                });
                
        if ($paginate) {
          $ret = $ret->slice($offset, $limit+$offset);
        }  
        
        //re-key for https://github.com/laravel/framework/issues/1335
        return $ret->values();
        
        /*
        // Search by XREF
        $location = Registry::locationFactory()->make($query, $tree);

        if ($location instanceof Location) {
            $results = new Collection([$location]);
        } else {
            $search  = array_filter(explode(' ', $query));
            $results = $this->search_service->searchLocations([$tree], $search, $offset, $limit);
        }

        return $results->map(static function (Location $location) use ($at): array {
            return [
                'text'  => view('selects/location', ['location' => $location]),
                'value' => $at . $location->xref() . $at,
            ];
        });         
        */
    }
}
