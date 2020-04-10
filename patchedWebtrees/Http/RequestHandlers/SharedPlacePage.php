<?php

declare(strict_types=1);

namespace Cissee\WebtreesExt\Http\RequestHandlers;

use Cissee\Webtrees\Module\SharedPlaces\SharedPlacesModule;
use Cissee\WebtreesExt\GedcomRecordExt;
use Cissee\WebtreesExt\SharedPlace;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\Http\ViewResponseTrait;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Tree;
use Illuminate\Support\Collection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use function assert;

//cf SourcePage
/**
 * Show a shared place's page.
 */
class SharedPlacePage implements RequestHandlerInterface {

    use ViewResponseTrait;
    
    // Show the shared place's facts in this order:
    private const FACT_ORDER = [
        1 => 'NAME',
        2 => 'MAP',
        3 => '_GOV',
        'ABBR',
        'AUTH',
        'DATA',
        'PUBL',
        'TEXT',
        'REPO',
        'NOTE',
        'OBJE',
        'REFN',
        'RIN',
        '_UID',
        'CHAN',
        'RESN',
    ];
  
    protected $module;
    
    public function __construct(ModuleService $moduleService) {
        //access level irrelevant here: there is no way to configure an access level for this specific functionality
        //(it's not a list, chart, etc. - we'd have to define it specifically)
        $this->module = $moduleService->findByInterface(SharedPlacesModule::class, false)->first();
        
        //otherwise we wouldn't even get here (router redirects)
        assert ($this->module instanceof SharedPlacesModule);
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface {
        $tree = $request->getAttribute('tree');
        assert($tree instanceof Tree);

        $xref = $request->getAttribute('xref');
        $record = GedcomRecordExt::getInstance($xref, $tree);

        //we don't need a specific method here
        Auth::checkRecordAccess($record, false);

        return $this->viewResponse($this->module->name() . '::shared-place-page', [
                    'module' => $this->module,
                    'moduleName' => $this->module->name(),
                    'facts' => $this->facts($record),
                    'families' => $record->linkedFamilies('_LOC'),
                    'individuals' => $record->linkedIndividuals('_LOC'),
                    'sharedPlace' => $record,
                    'meta_robots' => 'index,follow',
                    'title' => $record->fullName(),
                    'tree' => $tree
        ]);
    }
    
    private function facts(SharedPlace $record): Collection {
      $facts = $record->facts()
              ->sort(function (Fact $x, Fact $y): int {
        $sort_x = array_search($x->getTag(), self::FACT_ORDER) ?: PHP_INT_MAX;
        $sort_y = array_search($y->getTag(), self::FACT_ORDER) ?: PHP_INT_MAX;

        return $sort_x <=> $sort_y;
      });

      return $facts;
    }
}
