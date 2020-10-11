<?php

namespace Cissee\Webtrees\Module\SharedPlaces;

use Cissee\WebtreesExt\Http\Controllers\PlaceHierarchyLink;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\Http\Controllers\AbstractBaseController;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Tree;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;
use Psr\Http\Message\ResponseInterface;
use Vesta\Hook\HookInterfaces\GovIdEditControlsInterface;
use Vesta\Hook\HookInterfaces\GovIdEditControlsUtils;

class SharedPlacesListController extends AbstractBaseController {

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
    
    //select2 initializers for modal placeholder ajax-modal-vesta.phtml used via CreateSharedPlaceModal, urgh
    $select2Initializers = GovIdEditControlsUtils::accessibleModules($tree, Auth::user())
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
