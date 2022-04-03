<?php

namespace Cissee\Webtrees\Module\SharedPlaces;

use Cissee\WebtreesExt\Http\Controllers\PlaceHierarchyLink;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\Http\ViewResponseTrait;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Webtrees;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;
use Psr\Http\Message\ResponseInterface;
use Vesta\Hook\HookInterfaces\GovIdEditControlsInterface;
use Vesta\Hook\HookInterfaces\GovIdEditControlsUtils;

class SharedPlacesListController {

    use ViewResponseTrait;

    protected $module;
    protected $moduleName;
    protected $hasLocationsToFix;
    protected $link;

    public function __construct(
        $module,
        bool $hasLocationsToFix,
        ?PlaceHierarchyLink $link) {

        $this->module = $module;
        $this->moduleName = $module->name();
        $this->hasLocationsToFix = $hasLocationsToFix;
        $this->link = $link;
    }

    public function sharedPlacesList(Tree $tree, $showLinkCounts): ResponseInterface {
        $sharedPlaces = SharedPlacesListController::allSharedPlaces($tree);

        //select initializers for modal placeholder ajax-modal-vesta.phtml used via CreateSharedPlaceModal, urgh
        $select2Initializers = GovIdEditControlsUtils::accessibleModules($tree, Auth::user())
            ->map(function (GovIdEditControlsInterface $module) {
                return $module->govIdEditControlSelectScriptSnippet();
            })
            ->toArray();

        if (str_starts_with(Webtrees::VERSION, '2.1')) {
            return $this->viewResponse($this->moduleName . '::shared-places-list-page', [
                    'tree' => $tree,
                    'sharedPlaces' => $sharedPlaces,
                    'showLinkCounts' => $showLinkCounts,
                    'title' => I18N::translate('Shared places'),
                    'moduleName' => $this->moduleName,
                    'select2Initializers' => $select2Initializers,
                    'hasLocationsToFix' => $this->hasLocationsToFix,
                    'link' => $this->link,
            ]);
        }

        return $this->viewResponse($this->moduleName . '::shared-places-list-page_20', [
                'tree' => $tree,
                'sharedPlaces' => $sharedPlaces,
                'showLinkCounts' => $showLinkCounts,
                'title' => I18N::translate('Shared places'),
                'moduleName' => $this->moduleName,
                'select2Initializers' => $select2Initializers,
                'hasLocationsToFix' => $this->hasLocationsToFix,
                'link' => $this->link,
        ]);
    }

    /**
     * Find all the shared place records in a tree.
     *
     * @param Tree $tree
     *
     * @return Collection
     */
    private function allSharedPlaces(Tree $tree): Collection {
        /*
          $count = DB::table('other')
          ->where('o_file', '=', $tree->id())
          ->where('o_type', '=', '_LOC')
          ->count();

          error_log("count".$count);
         */

        return DB::table('other')
                ->where('o_file', '=', $tree->id())
                ->where('o_type', '=', '_LOC')
                ->get()
                ->map(Registry::locationFactory()->mapper($tree))
                ->filter(GedcomRecord::accessFilter());
    }

}
