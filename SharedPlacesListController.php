<?php

namespace Cissee\Webtrees\Module\SharedPlaces;

use Cissee\WebtreesExt\AbstractModuleBaseController;
use Cissee\WebtreesExt\SharedPlace;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\GedcomRecord;
use Illuminate\Support\Collection;
use Illuminate\Database\Capsule\Manager as DB;
use Symfony\Component\HttpFoundation\Response;

class SharedPlacesListController extends AbstractModuleBaseController {

  public function sharedPlacesList(Tree $tree, $showLinkCounts): Response {
    //TODO: filter places we can't show here, not in view?
    $sharedPlaces = SharedPlacesListController::allSharedPlaces($tree);

    return $this->viewResponse('shared-places-list-page', [
                'tree' => $tree,
                'sharedPlaces' => $sharedPlaces,
                'showLinkCounts' => $showLinkCounts,
                'title' => I18N::translate('Shared places'),
                'moduleName' => $this->moduleName
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
                    ->map(SharedPlace::rowMapper())
                    ->filter(GedcomRecord::accessFilter());
  }

}
