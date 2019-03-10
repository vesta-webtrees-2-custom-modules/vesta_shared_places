<?php

declare(strict_types=1);

namespace Cissee\Webtrees\Module\SharedPlaces;

use Cissee\WebtreesExt\AbstractModuleBaseController;
use Cissee\WebtreesExt\GedcomRecordExt;
use Cissee\WebtreesExt\SharedPlace;
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Auth;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Collection;

//cf GedcomRecordController, SourceController
class SharedPlaceController extends AbstractModuleBaseController
{ 
		// Show the shared place's facts in this order:
    private const FACT_ORDER = [
        1 => 'NAME',
				2 => 'MAP',
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
		
		public function show(Request $request, Tree $tree): Response
    {
        $xref = $request->get('xref', '');
        $record = GedcomRecordExt::getInstance($xref, $tree);

				//we don't need a specific method here
        Auth::checkRecordAccess($record, false);

        return $this->viewResponse('shared-place-page', [
						'moduleName'         => $this->moduleName,
						'moduleDirectory'    => $this->directory,
            'facts'         => $this->facts($record),
            'families'      => $record->linkedFamilies('_LOC'),
            'individuals'   => $record->linkedIndividuals('_LOC'),
            'sharedPlace'   => $record,
            'meta_robots'   => 'index,follow',						
            'title'         => $record->fullName(),						
						'tree'          => $tree
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
