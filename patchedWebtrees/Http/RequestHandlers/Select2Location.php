<?php

declare(strict_types=1);

namespace Cissee\WebtreesExt\Http\RequestHandlers;

use Cissee\WebtreesExt\Services\SearchServiceExt;
use Fisharebest\Webtrees\Location;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Tree;
use Illuminate\Support\Collection;
use Vesta\Model\GedcomDateInterval;
use function view;

/**
 * Autocomplete for locations.
 */
class Select2Location extends AbstractSelect2WithDateHandler
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
    protected function search(Tree $tree, GedcomDateInterval $date, string $query, int $offset, int $limit, string $at): Collection
    {
        // Search by XREF
        $location = Registry::locationFactory()->make($query, $tree);

        if ($location instanceof Location) {
            $results = new Collection([$location]);
        } else {
            $results = $this->search_service->searchLocations([$tree], [$query], $offset, $limit);
        }

        return $results->map(static function (Location $location) use ($at, $date): array {
            return [
                'id'    => $at . $location->xref() . $at,
                'text'  => view('selects/location', ['location' => $location]),
                'title' => $location->primaryPlaceAt($date)->gedcomName(),
            ];
        });
    }
}
