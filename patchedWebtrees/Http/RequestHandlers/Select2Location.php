<?php

declare(strict_types=1);

namespace Cissee\WebtreesExt\Http\RequestHandlers;

use Cissee\WebtreesExt\Services\SearchServiceExt;
use Fisharebest\Webtrees\Factory;
use Fisharebest\Webtrees\Http\RequestHandlers\AbstractSelect2Handler;
use Fisharebest\Webtrees\Location;
use Fisharebest\Webtrees\Tree;
use Illuminate\Support\Collection;
use function view;

/**
 * Autocomplete for locations.
 */
class Select2Location extends AbstractSelect2Handler
{
    /** @var SearchServiceExt */
    protected $search_service;

    /**
     * Select2Note constructor.
     *
     * @param SearchServiceExt $search_service
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
     * @param string $query
     * @param int    $offset
     * @param int    $limit
     * @param string $at
     *
     * @return Collection<array<string,string>>
     */
    protected function search(Tree $tree, string $query, int $offset, int $limit, string $at): Collection
    {
        // Search by XREF
        $location = Factory::location()->make($query, $tree);

        if ($location instanceof Location) {
            $results = new Collection([$location]);
        } else {
            $results = $this->search_service->searchLocations([$tree], [$query], $offset, $limit);
        }

        return $results->map(static function (Location $location) use ($at): array {
            return [
                'id'    => $at . $location->xref() . $at,
                'text'  => view('selects/location', ['location' => $location]),
                'title' => ' ',
            ];
        });
    }
}
