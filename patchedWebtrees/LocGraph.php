<?php

namespace Cissee\WebtreesExt;

use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Tree;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;

class LocGraph {

    protected $indi;
    protected $fam;
    protected $sour;
    protected $loc;

    public function __construct(
        array $indi,
        array $fam,
        array $sour,
        array $loc) {

        $this->indi = $indi;
        $this->fam = $fam;
        $this->sour = $sour;
        $this->loc = $loc;
    }

    public function linkedIndividuals(Collection $locXrefs): Collection {

        $ret = new Collection();
        $handled = new Collection();

        //safer wrt loops (than to use method recursively)
        $queue = new Collection();
        foreach ($locXrefs as $locXref) {
            $queue->prepend($locXref);
        }

        while ($queue->count() > 0) {
            $current = $queue->pop();

            $handled->add($current);
            if (array_key_exists($current, $this->indi)) {
                foreach ($this->indi[$current] as $indi => $row) {
                    $ret->add($row);
                }
            }

            if (array_key_exists($current, $this->loc)) {
                foreach ($this->loc[$current] as $next => $row) {
                    if (!$handled->contains($next)) {
                        $queue->prepend($next);
                    }
                }
            }
        }

        return $ret->unique();
    }

    public function linkedFamilies(Collection $locXrefs): Collection {
        $ret = new Collection();
        $handled = new Collection();

        //safer wrt loops (than to use method recursively)
        $queue = new Collection();
        foreach ($locXrefs as $locXref) {
            $queue->prepend($locXref);
        }

        while ($queue->count() > 0) {
            $current = $queue->pop();

            $handled->add($current);
            if (array_key_exists($current, $this->fam)) {
                foreach ($this->fam[$current] as $fam => $row) {
                    $ret->add($row);
                }
            }

            if (array_key_exists($current, $this->loc)) {
                foreach ($this->loc[$current] as $next => $row) {
                    if (!$handled->contains($next)) {
                        $queue->prepend($next);
                    }
                }
            }
        }

        return $ret->unique();
    }

    public function linkedSources(Collection $locXrefs): Collection {
        $ret = new Collection();
        $handled = new Collection();

        //safer wrt loops (than to use method recursively)
        $queue = new Collection();
        foreach ($locXrefs as $locXref) {
            $queue->prepend($locXref);
        }

        while ($queue->count() > 0) {
            $current = $queue->pop();

            $handled->add($current);
            if (array_key_exists($current, $this->sour)) {
                foreach ($this->sour[$current] as $sour => $row) {
                    $ret->add($row);
                }
            }

            if (array_key_exists($current, $this->loc)) {
                foreach ($this->loc[$current] as $next => $row) {
                    if (!$handled->contains($next)) {
                        $queue->prepend($next);
                    }
                }
            }
        }

        return $ret->unique();
    }
    
    public static function get(Tree $tree): LocGraph {
        return Registry::cache()->array()->remember('locGraph', function () use ($tree) {
                return LocGraph::create($tree);
            });
    }

    protected static function create(Tree $tree): LocGraph {
        $indi = array();
        $fam = array();
        $sour = array();
        $loc = array();

        $query = DB::table('link')
            ->leftJoin('individuals', function (JoinClause $join): void {
                $join
                ->on('link.l_from', '=', 'individuals.i_id')
                ->on('link.l_file', '=', 'individuals.i_file');
            })
            ->leftJoin('families', function (JoinClause $join): void {
                $join
                ->on('link.l_from', '=', 'families.f_id')
                ->on('link.l_file', '=', 'families.f_file');
            })
            ->leftJoin('sources', function (JoinClause $join): void {
                $join
                ->on('link.l_from', '=', 'sources.s_id')
                ->on('link.l_file', '=', 'sources.s_file');
            })
            ->leftJoin('other', function (JoinClause $join): void {
                $join
                ->on('link.l_from', '=', 'other.o_id')
                ->on('link.l_file', '=', 'other.o_file')
                ->where('other.o_type', '=', "_LOC");
            })
            ->where('l_file', '=', $tree->id())
            ->where('l_type', '=', '_LOC')
            ->select(['l_from', 'l_to', 'i_id', 'i_gedcom', 'f_id', 'f_gedcom', 's_id', 's_gedcom', 'o_id']);

        $rows = $query->get();

        foreach ($rows as $row) {
            if ($row->i_id !== null) {
                $indi[$row->l_to][$row->l_from] = $row;
            } else if ($row->f_id !== null) {
                $fam[$row->l_to][$row->l_from] = $row;
            } else if ($row->s_id !== null) {
                $sour[$row->l_to][$row->l_from] = $row;
            } else if ($row->o_id !== null) {
                $loc[$row->l_to][$row->l_from] = $row;
            } //else some other type which we currently don't care about        
        }

        return new LocGraph($indi, $fam, $sour, $loc);
    }

}
