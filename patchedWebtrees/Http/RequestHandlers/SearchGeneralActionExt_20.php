<?php

declare(strict_types=1);

namespace Cissee\WebtreesExt\Http\RequestHandlers;

use Fisharebest\Webtrees\Http\RequestHandlers\SearchGeneralPage;
use Fisharebest\Webtrees\Tree;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use function assert;
use function redirect;
use function route;

/**
 * Search for genealogy data
 */
class SearchGeneralActionExt_20 implements RequestHandlerInterface
{
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

        $params = (array) $request->getParsedBody();
        
        return redirect(route(SearchGeneralPage::class, [
            'query'               => $params['query'] ?? '',
            'search_individuals'  => (bool) ($params['search_individuals'] ?? false),
            'search_families'     => (bool) ($params['search_families'] ?? false),
            'search_locations'    => (bool) ($params['search_locations'] ?? false),
            'search_repositories' => (bool) ($params['search_repositories'] ?? false),
            'search_sources'      => (bool) ($params['search_sources'] ?? false),
            'search_notes'        => (bool) ($params['search_notes'] ?? false),
            'search_trees'        => $params['search_trees'] ?? [],
            'tree'                => $tree->name(),
        ]));
    }
}
