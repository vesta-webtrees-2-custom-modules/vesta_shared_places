<?php

declare(strict_types=1);

namespace Cissee\WebtreesExt\Services;

use Cissee\WebtreesExt\SharedPlace;
use Closure;
use Fisharebest\Localization\Locale\LocaleInterface;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\Note;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Tree;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Collection;
use Fisharebest\Webtrees\Factory;
use stdClass;
use function app;

/**
 * Search trees for genealogy records.
 */
class SearchServiceExt {

  /** @var LocaleInterface */
  private $locale;

  /**
   * SearchService constructor.
   *
   * @param LocaleInterface $locale
   */
  public function __construct(LocaleInterface $locale) {
    $this->locale = $locale;
  }

  /**
   * Search for shared places.
   *
   * @param Tree[]   $trees
   * @param string[] $search
   * @param int      $offset
   * @param int      $limit
   *
   * @return Collection|Note[]
   */
  public function searchSharedPlaces(array $trees, array $search, int $offset = 0, int $limit = PHP_INT_MAX): Collection {
    $query = DB::table('other')
            ->where('o_type', '=', '_LOC');

    $this->whereTrees($query, 'o_file', $trees);
    $this->whereSearch($query, 'o_gedcom', $search);
    
    return $this->paginateQuery($query, $this->sharedPlaceRowMapper(), GedcomRecord::accessFilter(), $offset, $limit);
  }
  
  private function sharedPlaceRowMapper(): Closure
    {
        return function (stdClass $row): SharedPlace {
            $tree = app(TreeService::class)->find((int) $row->o_file);
            return Factory::location()->mapper($tree)($row);
        };
    }

  /**
   * Paginate a search query.
   *
   * @param Builder $query      Searches the database for the desired records.
   * @param Closure $row_mapper Converts a row from the query into a record.
   * @param Closure $row_filter
   * @param int     $offset     Skip this many rows.
   * @param int     $limit      Take this many rows.
   *
   * @return Collection
   */
  private function paginateQuery(Builder $query, Closure $row_mapper, Closure $row_filter, int $offset, int $limit): Collection {
    $collection = new Collection();

    foreach ($query->cursor() as $row) {
      $record = $row_mapper($row);
      // If the object has a method "canShow()", then use it to filter for privacy.
      if ($row_filter($record)) {
        if ($offset > 0) {
          $offset--;
        } else {
          if ($limit > 0) {
            $collection->push($record);
          }

          $limit--;

          if ($limit === 0) {
            break;
          }
        }
      }
    }

    return $collection;
  }

  /**
   * Apply search filters to a SQL query column.  Apply collation rules to MySQL.
   *
   * @param Builder           $query
   * @param Expression|string $field
   * @param string[]          $search_terms
   */
  private function whereSearch(Builder $query, $field, array $search_terms): void {
    if ($field instanceof Expression) {
      $field = $field->getValue();
    }

    foreach ($search_terms as $search_term) {
      $query->where(new Expression($field), 'LIKE', '%' . addcslashes($search_term, '\\%_') . '%');
    }
  }

  /**
   * @param Builder $query
   * @param string  $tree_id_field
   * @param Tree[]  $trees
   */
  private function whereTrees(Builder $query, string $tree_id_field, array $trees): void {
    $tree_ids = array_map(function (Tree $tree) {
      return $tree->id();
    }, $trees);

    $query->whereIn($tree_id_field, $tree_ids);
  }
}
