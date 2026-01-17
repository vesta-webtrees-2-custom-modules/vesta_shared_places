<?php

declare(strict_types=1);

namespace Cissee\WebtreesExt\Http\RequestHandlers;

use Fisharebest\Webtrees\Module\ModuleMapAutocompleteInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\AbstractAutocompleteHandler;
use Fisharebest\Webtrees\Place;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Services\SearchService;
use Fisharebest\Webtrees\Validator;
use Illuminate\Support\Collection;
use Psr\Http\Message\ServerRequestInterface;

//same as webtrees AutoCompletePlace, but non-final
class AutoCompletePlacePatched extends AbstractAutocompleteHandler
{
    public function __construct(
        private readonly ModuleService $module_service,
        SearchService $search_service,
    ) {
        parent::__construct($search_service);
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return Collection<int,string>
     */
    protected function search(ServerRequestInterface $request): Collection
    {
        $tree  = Validator::attributes($request)->tree();
        $query = Validator::queryParams($request)->string('query');

        $data = $this->search_service
            ->searchPlaces($tree, $query, 0, static::LIMIT)
            ->map(static fn (Place $place): string => $place->gedcomName());

        // No place found? Use external gazetteers.
        foreach ($this->module_service->findByInterface(ModuleMapAutocompleteInterface::class) as $module) {
            if ($data->isEmpty()) {
                $data = $data->concat($module->searchPlaceNames($query))->sort();
            }
        }

        return $data;
    }
}
