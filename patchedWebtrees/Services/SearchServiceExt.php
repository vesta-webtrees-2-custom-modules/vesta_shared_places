<?php

declare(strict_types=1);

namespace Cissee\WebtreesExt\Services;

use Cissee\WebtreesExt\SharedPlace;
use Closure;
use Fisharebest\Localization\Locale\LocaleInterface;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\Note;
use Fisharebest\Webtrees\Tree;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Collection;
use function mb_stripos;

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

    return $this->paginateQuery($query, SharedPlace::rowMapper(), GedcomRecord::accessFilter(), $offset, $limit);
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

    $field = DB::raw($field . ' /*! COLLATE ' . 'utf8_' . $this->locale->collation() . ' */');

    foreach ($search_terms as $search_term) {
      $query->whereContains($field, $search_term);
    }
  }

  /**
   * Apply soundex search filters to a SQL query column.
   *
   * @param Builder           $query
   * @param Expression|string $field
   * @param string            $soundex
   */
  private function wherePhonetic(Builder $query, $field, string $soundex): void {
    if ($soundex !== '') {
      $query->where(function (Builder $query) use ($soundex, $field): void {
        foreach (explode(':', $soundex) as $sdx) {
          $query->orWhere($field, 'LIKE', '%' . $sdx . '%');
        }
      });
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

  /**
   * A closure to filter records by privacy-filtered GEDCOM data.
   *
   * @param array $search_terms
   *
   * @return Closure
   */
  private function rawGedcomFilter(array $search_terms): Closure {
    return function (GedcomRecord $record) use ($search_terms): bool {
      // Ignore non-genealogy fields
      $gedcom = preg_replace('/\n\d (?:_UID) .*/', '', $record->gedcom());

      // Ignore matches in links
      $gedcom = preg_replace('/\n\d ' . Gedcom::REGEX_TAG . '( @' . Gedcom::REGEX_XREF . '@)?/', '', $gedcom);

      // Re-apply the filtering
      foreach ($search_terms as $search_term) {
        if (mb_stripos($gedcom, $search_term) === false) {
          return false;
        }
      }

      return true;
    };
  }

}
