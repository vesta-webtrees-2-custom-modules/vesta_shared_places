<?php

namespace Cissee\Webtrees\Module\SharedPlaces;

use Cissee\WebtreesExt\SharedPlace;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\Http\Controllers\AbstractBaseController;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Source;
use Fisharebest\Webtrees\Tree;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;
use Psr\Http\Message\ResponseInterface;
use Vesta\Hook\HookInterfaces\GovIdEditControlsInterface;
use Vesta\Hook\HookInterfaces\GovIdEditControlsUtils;

class SharedPlacesListController extends AbstractBaseController {

  protected $module;
  protected $moduleName;

  public function __construct($module) {
    $this->module = $module;
    $this->moduleName = $module->name();
  }
  
  public function sharedPlacesList(Tree $tree, $showLinkCounts): ResponseInterface {
    //TODO: filter places we can't show here, not in view?
    $sharedPlaces = SharedPlacesListController::allSharedPlaces($tree);

    //select2 initializers for modal placeholder ajax-modal-vesta.phtml used via EditSharedPlaceController.createSharedPlace(), urgh
    $select2Initializers = GovIdEditControlsUtils::accessibleModules($this->module, $tree, Auth::user())
            ->map(function (GovIdEditControlsInterface $module) {
              return $module->govIdEditControlSelect2ScriptSnippet();
            })
            ->toArray();
    
    return $this->viewResponse($this->moduleName . '::shared-places-list-page', [
                'tree' => $tree,
                'sharedPlaces' => $sharedPlaces,
                'showLinkCounts' => $showLinkCounts,
                'title' => I18N::translate('Shared places'),
                'moduleName' => $this->moduleName,
                'select2Initializers' => $select2Initializers,
    ]);
  }

  /**
   * Find all the shared place records in a tree.
   *
   * @param Tree $tree
   *
   * @return Collection|Source[]
   */
  private function allSharedPlaces(Tree $tree): Collection {
    return DB::table('other')
                    ->where('o_file', '=', $tree->id())
                    ->where('o_type', '=', '_LOC')
                    ->get()
                    ->map(SharedPlace::rowMapper($tree))
                    ->filter(GedcomRecord::accessFilter());
  }

}
