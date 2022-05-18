<?php

declare(strict_types=1);

namespace Cissee\WebtreesExt\Elements;

use Cissee\WebtreesExt\Http\RequestHandlers\CreateSharedPlaceModal;
use Fisharebest\Webtrees\Elements\AbstractXrefElement;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Tree;
use function e;
use function route;
use function trim;
use function view;

//[RC] replace XrefLocation because we
//- use a different edit control (select-location, but that's swapped elsewhere)
//- use a different modal

/**
 * XREF:_LOC := {Size=1:22}
 * A pointer to, or a cross-reference identifier of, a location record.
 */
class XrefSharedPlace extends AbstractXrefElement
{
    /**
     * An edit control for this data.
     *
     * @param string $id
     * @param string $name
     * @param string $value
     * @param Tree   $tree
     *
     * @return string
     */
    public function edit(string $id, string $name, string $value, Tree $tree): string
    {
        //[RC] view adjusted
        $select = view('components/select-location-ext', [
            'id'       => $id,
            'name'     => $name,
            'location' => Registry::locationFactory()->make(trim($value, '@'), $tree),
            'tree'     => $tree,
            'at'       => '@',
        ]);

        $selector = '[id$=PLAC]';
        
        $route = route(CreateSharedPlaceModal::class, [
            'tree' => $tree->name(),
            'shared-place-name-selector' => $selector,
            ]);
        
        return
            '<div class="input-group">' .
            '<button class="btn btn-secondary" type="button" data-bs-toggle="modal" data-bs-backdrop="static" data-bs-target="#wt-ajax-modal-vesta" data-wt-href="' . e($route) . '" data-wt-select-id="' . $id . '" title="' . I18N::translate('Create a shared place') . '">' .
            view('icons/add') .
            '</button>' .
            $select .
            '</div>';
    }

    /**
     * Display the value of this type of element.
     *
     * @param string $value
     * @param Tree   $tree
     *
     * @return string
     */
    public function value(string $value, Tree $tree): string
    {
        return $this->valueXrefLink($value, $tree, Registry::locationFactory());
    }
}
