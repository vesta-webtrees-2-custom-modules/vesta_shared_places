<?php

declare(strict_types=1);

namespace Cissee\WebtreesExt\Http\RequestHandlers;

use Fisharebest\Webtrees\Date;
use Fisharebest\Webtrees\Tree;
use Illuminate\Support\Collection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Vesta\Model\GedcomDateInterval;
use function assert;
use function response;
use function strlen;

/**
 * Autocomplete for Select2 based controls.
 */
abstract class AbstractSelect2WithDateHandler implements RequestHandlerInterface
{
    // For clients that request one page of data at a time.
    private const RESULTS_PER_PAGE = 20;

    // Minimum number of characters for a search.
    public const MINIMUM_INPUT_LENGTH = 2;

    // Wait for the user to pause typing before sending request.
    public const AJAX_DELAY = 350;

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $tree = $request->getAttribute('tree');
        assert($tree instanceof Tree);

        $params = (array) $request->getParsedBody();
        $query  = $params['q'] ?? '';
        $at     = (bool) ($params['at'] ?? false);
        $page   = (int) ($params['page'] ?? 1);
        $dateStr= $params['dateStr'] ?? '';

        $date = ($dateStr === '')?GedcomDateInterval::createNow():GedcomDateInterval::create($dateStr);
        
        // Fetch one more row than we need, so we can know if more rows exist.
        $offset = ($page - 1) * self::RESULTS_PER_PAGE;
        $limit  = self::RESULTS_PER_PAGE + 1;

        // Perform the search.
        if (strlen($query) >= self::MINIMUM_INPUT_LENGTH) {
            $results = $this->search($tree, $date, $query, $offset, $limit, $at ? '@' : '');
        } else {
            $results = new Collection();
        }

        return response([
            'results'    => $results->slice(0, self::RESULTS_PER_PAGE)->all(),
            'pagination' => [
                'more' => $results->count() > self::RESULTS_PER_PAGE,
            ],
        ]);
    }

    /**
     * Perform the search
     *
     * @param Tree   $tree
     * @param GedcomDateInterval $date
     * @param string $query
     * @param int    $offset
     * @param int    $limit
     * @param string $at    "@" or ""
     *
     * @return Collection<array<string,string>>
     */
    abstract protected function search(Tree $tree, GedcomDateInterval $date, string $query, int $offset, int $limit, string $at): Collection;
}
