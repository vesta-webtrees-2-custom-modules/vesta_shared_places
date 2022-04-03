<?php

declare(strict_types=1);

namespace Cissee\WebtreesExt\Http\RequestHandlers;

use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Validator;
use Illuminate\Support\Collection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Vesta\Model\GedcomDateInterval;
use function response;
use function route;

/**
 * Autocomplete for TomSelect based controls.
 */
abstract class AbstractTomSelectWithDateHandler implements RequestHandlerInterface
{
    // For clients that request one page of data at a time.
    private const RESULTS_PER_PAGE = 50;

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $tree  = Validator::attributes($request)->tree();
        $at    = Validator::queryParams($request)->isInArray(['', '@'])->string('at');
        $page  = Validator::queryParams($request)->integer('page', 1);
        $query = Validator::queryParams($request)->string('query');
        
        $dateStr = Validator::queryParams($request)->string('dateStr', '');
        $date = ($dateStr === '')?GedcomDateInterval::createNow():GedcomDateInterval::create($dateStr);

        // Fetch one more row than we need, so we can know if more rows exist.
        $offset = ($page - 1) * self::RESULTS_PER_PAGE;
        $limit  = self::RESULTS_PER_PAGE + 1;

        // Perform the search.
        if ($query !== '') {
            $results = $this->search($tree, $date, $query, $offset, $limit, $at ? '@' : '');
        } else {
            $results = new Collection();
        }

        if ($results->count() > self::RESULTS_PER_PAGE) {
            $next_url = route(static::class, ['tree' => $tree->name(), 'at' => $at ? '@' : '', 'page' => $page + 1]);
        } else {
            $next_url = null;
        }

        return response([
            'data'    => $results->slice(0, self::RESULTS_PER_PAGE)->all(),
            'nextUrl' => $next_url,
        ]);
    }

    /**
     * Perform the search
     *
     * @param Tree   $tree
     * @param string $query
     * @param int    $offset
     * @param int    $limit
     * @param string $at    "@" or ""
     *
     * @return Collection<int,array{text:string,value:string}>
     */
    abstract protected function search(Tree $tree, GedcomDateInterval $date, string $query, int $offset, int $limit, string $at): Collection;
}
