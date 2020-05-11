<?php

declare(strict_types=1);

namespace Cissee\WebtreesExt\Contracts;

use Cissee\WebtreesExt\SharedPlace;
use Closure;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\Tree;
use Illuminate\Support\Collection;

interface SharedPlaceFactoryInterface
{
    /**
     * Make a Shared Place object.
     */
    public function make(string $xref, Tree $tree, string $gedcom = null): ?SharedPlace;
      
    /**
     * Create a Shared Place object from a row in the database.
     *
     * @param Tree $tree
     *
     * @return Closure
     */
    public function mapper(Tree $tree): Closure;

    /**
     * Create a Shared Place object from raw GEDCOM data.
     *
     * @param string      $xref
     * @param string      $gedcom  an empty string for new/pending records
     * @param string|null $pending null for a record with no pending edits,
     *                             empty string for records with pending deletions
     * @param Tree        $tree
     *
     * @return SharedPlace
     */
    public function new(string $xref, string $gedcom, ?string $pending, Tree $tree): SharedPlace;
    
    /**
     * Find shared places linked to the given record.
     *
     * @param string $link
     *
     * @return Collection<SharedPlace>
     */
    public function linkedSharedPlaces(GedcomRecord $record, string $link): Collection;
}

