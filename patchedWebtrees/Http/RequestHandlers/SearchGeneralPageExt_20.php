<?php

declare(strict_types=1);

namespace Cissee\WebtreesExt\Http\RequestHandlers;

use Cissee\WebtreesExt\MoreI18N;
use Cissee\WebtreesExt\Services\SearchServiceExt;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\Http\ViewResponseTrait;
use Fisharebest\Webtrees\Services\SearchService;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Site;
use Fisharebest\Webtrees\Tree;
use Illuminate\Support\Collection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use const PREG_SET_ORDER;
use function assert;
use function preg_replace;
use function redirect;
use function trim;

/**
 * Search for genealogy data
 */
class SearchGeneralPageExt_20 implements RequestHandlerInterface
{
    use ViewResponseTrait;

    /** @var SearchService */
    private $search_service;

    /** @var SearchServiceExt */
    private $search_service_ext;

    /** @var TreeService */
    private $tree_service;

    /**
     * SearchGeneralPageExt constructor.
     *
     * @param SearchService $search_service
     * @param SearchServiceExt $search_service_ext
     * @param TreeService   $tree_service
     */
    public function __construct(
            SearchService $search_service, 
            SearchServiceExt $search_service_ext, 
            TreeService $tree_service)
    {
        $this->search_service = $search_service;
        $this->search_service_ext = $search_service_ext;
        $this->tree_service = $tree_service;
    }

    /**
     * The standard search.
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $tree = $request->getAttribute('tree');
        assert($tree instanceof Tree);

        $params = $request->getQueryParams();
        $query  = $params['query'] ?? '';
        
        // What type of records to search?
        $search_individuals  = (bool) ($params['search_individuals'] ?? false);
        $search_families     = (bool) ($params['search_families'] ?? false);
        $search_repositories = (bool) ($params['search_repositories'] ?? false);
        $search_sources      = (bool) ($params['search_sources'] ?? false);
        $search_notes        = (bool) ($params['search_notes'] ?? false);
        $search_locations    = (bool) ($params['search_locations'] ?? false);

        // Default to families and individuals only
        if (!$search_individuals && !$search_families && !$search_repositories && !$search_sources && !$search_notes && !$search_locations) {
            $search_families    = true;
            $search_individuals = true;
        }

        // What to search for?
        $search_terms = $this->extractSearchTerms($query);

        // What trees to search?
        if (Site::getPreference('ALLOW_CHANGE_GEDCOM') === '1') {
            $all_trees = $this->tree_service->all();
        } else {
            $all_trees = new Collection([$tree]);
        }

        $search_tree_names = new Collection($params['search_trees'] ?? []);

        $search_trees = $all_trees
            ->filter(static function (Tree $tree) use ($search_tree_names): bool {
                return $search_tree_names->containsStrict($tree->name());
            });

        if ($search_trees->isEmpty()) {
            $search_trees->add($tree);
        }

        // Do the search
        $individuals  = new Collection();
        $families     = new Collection();
        $repositories = new Collection();
        $sources      = new Collection();
        $notes        = new Collection();
        $locations    = new Collection();

        if ($search_terms !== []) {
            if ($search_individuals) {
                $individuals = $this->search_service->searchIndividuals($search_trees->all(), $search_terms);
            }

            if ($search_families) {
                $tmp1 = $this->search_service->searchFamilies($search_trees->all(), $search_terms);
                $tmp2 = $this->search_service->searchFamilyNames($search_trees->all(), $search_terms);

                $families = $tmp1->merge($tmp2)->unique(static function (Family $family): string {
                    return $family->xref() . '@' . $family->tree()->id();
                });
            }

            if ($search_repositories) {
                $repositories = $this->search_service->searchRepositories($search_trees->all(), $search_terms);
            }

            if ($search_sources) {
                $sources = $this->search_service->searchSources($search_trees->all(), $search_terms);
            }

            if ($search_notes) {
                $notes = $this->search_service->searchNotes($search_trees->all(), $search_terms);
            }
            
            if ($search_locations) {
                $locations = $this->search_service_ext->searchLocations($search_trees->all(), $search_terms);
            }
        }

        // If only 1 item is returned, automatically forward to that item
        if ($individuals->count() === 1 && $families->isEmpty() && $repositories->isEmpty() && $sources->isEmpty() && $notes->isEmpty() && $locations->isEmpty()) {
            return redirect($individuals->first()->url());
        }

        if ($individuals->isEmpty() && $families->count() === 1 && $repositories->isEmpty() && $sources->isEmpty() && $notes->isEmpty() && $locations->isEmpty()) {
            return redirect($families->first()->url());
        }

        if ($individuals->isEmpty() && $families->isEmpty() && $repositories->count() === 1 && $sources->isEmpty() && $notes->isEmpty() && $locations->isEmpty()) {
            return redirect($repositories->first()->url());
        }
        
        if ($individuals->isEmpty() && $families->isEmpty() && $repositories->isEmpty() && $sources->count() === 1 && $notes->isEmpty() && $locations->isEmpty()) {
            return redirect($sources->first()->url());
        }

        if ($individuals->isEmpty() && $families->isEmpty() && $repositories->isEmpty() && $sources->isEmpty() && $notes->count() === 1 && $locations->isEmpty()) {
            return redirect($notes->first()->url());
        }

        if ($individuals->isEmpty() && $families->isEmpty() && $repositories->isEmpty() && $sources->isEmpty() && $notes->isEmpty() && $locations->count() === 1) {
            return redirect($locations->first()->url());
        }
        
        $title = MoreI18N::xlate('General search');

        return $this->viewResponse('search-general-page-ext', [
            'all_trees'           => $all_trees,
            'families'            => $families,
            'individuals'         => $individuals,
            'locations'           => $locations,
            'notes'               => $notes,
            'query'               => $query,
            'repositories'        => $repositories,
            'search_families'     => $search_families,
            'search_individuals'  => $search_individuals,
            'search_locations'    => $search_locations,
            'search_notes'        => $search_notes,
            'search_repositories' => $search_repositories,
            'search_sources'      => $search_sources,
            'search_trees'        => $search_trees,
            'sources'             => $sources,
            'title'               => $title,
            'tree'                => $tree,
        ]);
    }

    /**
     * Convert the query into an array of search terms
     *
     * @param string $query
     *
     * @return array<string>
     */
    private function extractSearchTerms(string $query): array
    {
        $search_terms = [];

        // Words in double quotes stay together
        preg_match_all('/"([^"]+)"/', $query, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $search_terms[] = trim($match[1]);
            // Remove this string from the search query
            $query = strtr($query, [$match[0] => '']);
        }

        // Treat CJK characters as separate words, not as characters.
        $query = preg_replace('/\p{Han}/u', '$0 ', $query);

        // Other words get treated separately
        preg_match_all('/[\S]+/', $query, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $search_terms[] = $match[0];
        }

        return $search_terms;
    }
}
