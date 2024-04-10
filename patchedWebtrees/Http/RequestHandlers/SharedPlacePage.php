<?php

declare(strict_types=1);

namespace Cissee\WebtreesExt\Http\RequestHandlers;

use Cissee\Webtrees\Module\SharedPlaces\SharedPlacesModule;
use Cissee\WebtreesExt\Functions\FunctionsPrintExtHelpLink;
use Cissee\WebtreesExt\SharedPlace;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\Http\ViewResponseTrait;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\ClipboardService;
use Fisharebest\Webtrees\Services\LinkedRecordService;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Validator;
use Illuminate\Support\Collection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Vesta\Hook\HookInterfaces\FunctionsPlaceUtils;
use Vesta\Model\PlaceStructure;
use function redirect;

//cf SourcePage
/**
 * Show a shared place's page.
 */
class SharedPlacePage implements RequestHandlerInterface {

    use ViewResponseTrait;

    // Show the shared place's facts in this order:
    private const FACT_ORDER = [
        1 => 'NAME',
        2 => 'TYPE',
        3 => '_LOC',
        4 => 'MAP',
        5 => '_GOV',
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
    protected LinkedRecordService $linked_record_service;
    protected ClipboardService $clipboard_service;

    public function __construct(
            ModuleService $moduleService,
            LinkedRecordService $linked_record_service,
            ClipboardService $clipboard_service) {

        //access level irrelevant here: there is no way to configure an access level for this specific functionality
        //(it's not a list, chart, etc. - we'd have to define it specifically)
        $this->module = $moduleService->findByInterface(SharedPlacesModule::class, false)->first();

        //otherwise we wouldn't even get here (router redirects)
        assert ($this->module instanceof SharedPlacesModule);

        $this->linked_record_service = $linked_record_service;
        $this->clipboard_service = $clipboard_service;
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface {

        $tree   = Validator::attributes($request)->tree();
        $xref   = Validator::attributes($request)->isXref()->string('xref');
        $slug   = Validator::attributes($request)->string('slug', '');
        $record = Registry::locationFactory()->make($xref, $tree);

        //we don't need a specific method here,
        //if we ever refactor this:
        //use SharedPlaceNotFoundException!
        $record = Auth::checkLocationAccess($record, false);

        // Redirect to correct xref/slug
        if ($record->xref() !== $xref || Registry::slugFactory()->make($record) !== $slug) {
            return redirect($record->url(), StatusCodeInterface::STATUS_MOVED_PERMANENTLY);
        }

        $canonical = $record->primaryPlace()->gedcomName();

        $hierarchyHtml = '';

        //what was the point of this? summary shows same data!
        /*
        $mainForDisplay = $sharedPlace->fullName();
        $canonicalForDisplay = $sharedPlace->primaryPlace()->fullName();

        if ($mainForDisplay !== $canonicalForDisplay) {
          $hierarchyHtml = '<tr class=""><th scope="row">' . I18N::translate('Shared place hierarchy') . '</th><td class="">' . $canonicalForDisplay . '</td></tr>';
        }
        */

        //summary (with any additional data such as GOV data, map links etc),
        //if there is a module that provides this summary
        $summaryHtml = '';
        if (!empty($record->namesNN())) {
            $refYear = intVal($this->module->getPreference('REF_YEAR', ''));
            if ($refYear) {
                $ps = PlaceStructure::fromNameAndLocWithYear($refYear, $canonical, $record->xref(), $record->tree(), 0, $record);
            } else {
                $ps = PlaceStructure::fromNameAndLocNow($canonical, $record->xref(), $record->tree(), 0, $record);
            }

            if ($ps !== null) {
                $summaryGve = FunctionsPlaceUtils::plac2html($this->module, $ps);

                //if ($summaryHtml !== '') {
                    $summaryHtml = '<tr class=""><th scope="row">' . I18N::translate('Summary') . FunctionsPrintExtHelpLink::helpLink($this->module->name(), 'Summary') . '</th><td class="">' . $summaryGve->getMain() . '</td></tr>';
                    //TODO: handle getScript()!
                //}
            }
        }

        return $this->viewResponse($this->module->name() . '::shared-place-page', [
            'module' => $this->module,
            'moduleName' => $this->module->name(),
            'hierarchyHtml' => $hierarchyHtml,
            'summaryHtml' => $summaryHtml,
            'facts' => $this->facts($record),

            'clipboard_facts'      => $this->clipboard_service->pastableFacts($record),
            //no, must use own impl in case of indirect links
            //'linked_individuals'   => $this->linked_record_service->linkedIndividuals($record, '_LOC'),
            //'linked_families'      => $this->linked_record_service->linkedFamilies($record, '_LOC'),
            'linked_individuals'   => $record->linkedIndividuals('_LOC'),
            'linked_families'      => $record->linkedFamilies('_LOC'),
            'llSharedPlaces'       => $this->linked_record_service->linkedLocations($record, '_LOC'),
            'linked_media_objects' => null, //TODO
            'linked_notes'         => null, //TODO
            'linked_sources'       => $record->linkedSources('_LOC'),
            'meta_robots'          => 'index,follow',
            'record' => $record,
            'title' => $record->fullName(),
            'tree' => $tree,
        ]);
    }

    private function facts(SharedPlace $record): Collection {

        $factsArray = $record->facts()->toArray();

        $facts = $record->facts()
            ->sort(function (Fact $x, Fact $y) use ($factsArray): int {
                [, $tag_x] = explode(':', $x->tag());
                [, $tag_y] = explode(':', $y->tag());

                $sort_x = array_search($tag_x, self::FACT_ORDER) ?: PHP_INT_MAX;
                $sort_y = array_search($tag_y, self::FACT_ORDER) ?: PHP_INT_MAX;

                $cmp = $sort_x <=> $sort_y;
                if ($cmp !== 0) {
                  return $cmp;
                }

                //fallback to original order within gedcom (e.g. for multiple names)
                return array_search($x, $factsArray) <=> array_search($y, $factsArray);
            });

        return $facts;
    }
}
