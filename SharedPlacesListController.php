<?php

namespace Cissee\Webtrees\Module\SharedPlaces;

use Cissee\WebtreesExt\SharedPlace;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\Http\Controllers\AbstractBaseController;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Source;
use Fisharebest\Webtrees\Tree;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;
use Psr\Http\Message\ResponseInterface;

class SharedPlacesListController extends AbstractBaseController {

  protected $moduleName;

  public function __construct(string $moduleName) {
    $this->moduleName = $moduleName;
  }
  
  public function sharedPlacesList(Tree $tree, $showLinkCounts): ResponseInterface {
    //TODO: filter places we can't show here, not in view?
    $sharedPlaces = SharedPlacesListController::allSharedPlaces($tree);

    return $this->viewResponse($this->moduleName . '::shared-places-list-page', [
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
                    ->map(SharedPlace::rowMapper($tree))
                    ->filter(GedcomRecord::accessFilter());
  }

}
