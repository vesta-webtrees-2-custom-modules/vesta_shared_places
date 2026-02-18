<?php

declare(strict_types=1);

namespace Cissee\WebtreesExt\Http\RequestHandlers;

use Cissee\WebtreesExt\Services\SearchServiceExt;
use Fisharebest\Webtrees\Http\RequestHandlers\AbstractAutocompleteHandler;
use Fisharebest\Webtrees\Module\ModuleMapAutocompleteInterface;
use Fisharebest\Webtrees\Place;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Services\SearchService;
use Fisharebest\Webtrees\Validator;
use Illuminate\Support\Collection;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Autocomplete handler for places
 */
class AutoCompletePlaceExt extends AbstractAutocompleteHandler {

    public function __construct(
        private readonly ModuleService $module_service,
        SearchService $search_service,
        private readonly SearchServiceExt $search_service_ext) {
        
        parent::__construct($search_service);
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return Collection<int,string>
     */
    protected function search(ServerRequestInterface $request): Collection {
        $tree  = Validator::attributes($request)->tree();
        $query = Validator::queryParams($request)->string('query');

        $data = $this->search_service_ext
            ->searchPlaces($tree, $query, false, 0, static::LIMIT)
            ->map(static function (Place $place): string {
                return $place->gedcomName();
            });

        if ($data->count() < static::LIMIT) {
            $data = $data->concat($this->search_service_ext
            ->searchPlaces($tree, $query, true, 0, static::LIMIT-$data->count())
            ->map(static function (Place $place): string {
                return $place->gedcomName();
            }))
            //do not sort, keep first results at front, but:
            ->unique() //drop duplicates
            ->values(); //re-key to 0,1,2,3 ...
        }

        // No place found? Use external gazetteers.
        foreach ($this->module_service->findByInterface(ModuleMapAutocompleteInterface::class) as $module) {
            if ($data->isEmpty()) {
                $data = $data->concat($module->searchPlaceNames($query))->sort();
            }
        }

        return $data;
    }
}
