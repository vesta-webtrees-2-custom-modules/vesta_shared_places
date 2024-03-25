<?php

namespace Cissee\WebtreesExt;

use Cissee\WebtreesExt\Http\Controllers\PlaceUrls;
use Cissee\WebtreesExt\Http\Controllers\PlaceWithinHierarchy;
use Exception;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\Module\ModuleInterface;
use Fisharebest\Webtrees\Place;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\GedcomService;
use Fisharebest\Webtrees\Tree;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use stdClass;
use Vesta\Hook\HookInterfaces\FunctionsPlaceUtils;
use Vesta\Model\GedcomDateInterval;
use Vesta\Model\LocReference;
use Vesta\Model\MapCoordinates;
use Vesta\Model\PlaceStructure;
use Vesta\Model\Trace;

class PlaceViaSharedPlace implements PlaceWithinHierarchy {

  protected $actual;

  /** @var bool */
  protected $asAdditionalParticipant;

  /** @var PlaceUrls */
  protected $urls;

  protected $module;

  /** @var Collection */
  protected $sharedPlaces;

  /** @var MapCoordinates|null */
  protected $latLon = null;

  protected $latLonInitialized = false;

  public function __construct(
          Place $actual,
          bool $asAdditionalParticipant,
          PlaceUrls $urls,
          Collection $sharedPlaces,
          ModuleInterface $module) {

    $this->actual = $actual;
    $this->asAdditionalParticipant = $asAdditionalParticipant;
    $this->urls = $urls;
    $this->module = $module;
    $this->sharedPlaces = $sharedPlaces;
  }

  //Speedup
  //more efficient than $this->actual->url() when calling for lots of places
  //this should be in webtrees, e.g. via caching result of 'findByComponent' or even 'Auth::accessLevel'
  public function url(): string {
    return $this->urls->url($this->actual);
  }

  public function gedcomName(): string {
    return $this->actual->gedcomName();
  }

  public function placeName(): string {
    $uniqueSharedPlaces = boolval($this->module->getPreference('UNIQUE_SP_IN_HIERARCHY', '0'));

    if ($uniqueSharedPlaces && !$this->asAdditionalParticipant) {
      //by design only current names (exclude historical names!)
      $place_name = $this->sharedPlaces
              ->flatMap(function (SharedPlace $sharedPlace): array {
                return $sharedPlace->namesNNAt(null);
              })
              ->reduce(function ($carry, $item): string {
                  return ($carry === "")?$item:$carry . " | " . $item;
              }, "");

      return '<span dir="auto">' . e($place_name) . '</span>';
    }

    return $this->actual->placeName();
  }

  //cf DefaultPlaceWithinHierarchy, but map to SharedPlace/PlaceViaSharedPlace directly from query
  public function getChildPlacesCacheIds(
          Place $place): Collection {

    $self = $this;

    if ($place->gedcomName() !== '') {
        $parent_text = Gedcom::PLACE_SEPARATOR . $place->gedcomName();
    } else {
        $parent_text = '';
    }

    $tree = $place->tree();

    $place2sharedPlaceMap = DB::table('places')
        ->where('p_file', '=', $tree->id())
        ->where('p_parent_id', '=', $place->id())
        ->join('placelinks', static function (JoinClause $join): void {
            $join
                ->on('pl_file', '=', 'p_file')
                ->on('pl_p_id', '=', 'p_id');
        })
        ->join('other', static function (JoinClause $join): void {
            $join
                ->on('o_file', '=', 'pl_file')
                ->on('o_id', '=', 'pl_gid');
        })
        ->where('o_type', '=', '_LOC')
        ->get()
        ->map(function (stdClass $row) use ($parent_text, $tree): array {
            $place = new Place($row->p_place . $parent_text, $tree);
            $id = $row->p_id;
            Registry::cache()->array()->remember('place-' . $place->gedcomName(), function () use ($id): int {return $id;});

            $sharedPlace = Registry::locationFactory()->mapper($tree)($row);
            return ["actual" => $place, "record" => $sharedPlace];
        })
        //must filter as in SearchServiceExt::searchLocationsInPlace
        ->filter(function ($item): bool {
            //include only if name matches!
            $names = new Collection($item["record"]->namesAsPlacesAt(GedcomDateInterval::createEmpty()));
            return $names->has($item["actual"]->id());
        });

    if ($place2sharedPlaceMap->isEmpty()) {
      return new Collection();
    }

    $uniqueSharedPlaces = boolval($this->module->getPreference('UNIQUE_SP_IN_HIERARCHY', '0'));

    //if $asAdditionalParticipant, we just need the additional data (map coordinates)
    //and do not evaluate $uniqueSharedPlaces
    if ($uniqueSharedPlaces && !$this->asAdditionalParticipant) {
      $place2sharedPlaceMap = $place2sharedPlaceMap
        ->mapToGroups(static function ($item): array {
          /** @var SharedPlace $sharedPlace */
          $sharedPlace = $item["record"];
          return [$sharedPlace->xref() => $item];
        })
        ->map(static function (Collection $groupedItems): array {
          $first = $groupedItems->first();
          /** @var SharedPlace $sharedPlace */
          $sharedPlace = $first["record"];

          //which place to use? follow order from shared place
          $place = null;
          foreach ($sharedPlace->namesAsPlacesAt(GedcomDateInterval::createEmpty()) as $placeViaSharedPlace) {
            foreach ($groupedItems as $groupedItem) {
              if ($placeViaSharedPlace->id() === $groupedItem["actual"]->id()) {
                $place = $groupedItem["actual"];
                break 2;
              }
            }
          }

          if ($place === null) {
            throw new Exception("unexpected null place!");
          }
          return ["actual" => $place, "record" => $sharedPlace];
        });
    }

    $childPlaces = $place2sharedPlaceMap
        ->mapToGroups(static function ($item): array {
          $place = $item["actual"];
          return [$place->id() => $item];
        })
        ->map(static function (Collection $groupedItems) use ($self): PlaceViaSharedPlace {
          $first = $groupedItems->first();
          $sharedPlaces = $groupedItems
                  ->map(static function (array $inner): SharedPlace {
                      return $inner["record"];
                  })
                  ->unique(function (SharedPlace $sharedPlace): string {
                      return $sharedPlace->xref();
                  });

          return new PlaceViaSharedPlace(
                  $first["actual"],
                  $self->asAdditionalParticipant,
                  $self->urls,
                  $sharedPlaces,
                  $self->module);
        })
        ->sort(static function (PlaceViaSharedPlace $x, PlaceViaSharedPlace $y): int {
          return strtolower($x->gedcomName()) <=> strtolower($y->gedcomName());
        })
        ->mapWithKeys(static function (PlaceViaSharedPlace $place): array {
          return [$place->id() => $place];
        });

    return $childPlaces;
  }

  public function getChildPlaces(): array {
    $ret = $this
            ->getChildPlacesCacheIds($this->actual)
            ->toArray();

    return $ret;
  }

  public function id(): int {
    return $this->actual->id();
  }

  public function tree(): Tree {
    return $this->actual->tree();
  }

  public function fullName(bool $link = false): string {
    return $this->actual->fullName($link);
  }

  public function searchIndividualsInPlace(): Collection {
    return SharedPlace::linkedIndividualsRecords($this->sharedPlaces);
  }

  public function countIndividualsInPlace(): int {
    return SharedPlace::linkedIndividualsCount($this->sharedPlaces);
  }

  public function searchFamiliesInPlace(): Collection {
    return SharedPlace::linkedFamiliesRecords($this->sharedPlaces);
  }

  public function countFamiliesInPlace(): int {
    return SharedPlace::linkedFamiliesCount($this->sharedPlaces);
  }

  protected function initLatLon(): ?MapCoordinates {
    $useIndirectLinks = boolval($this->module->getPreference('INDIRECT_LINKS', '1'));

    if (!$useIndirectLinks) {
      //check shared places directly, they won't be checked via plac2map
      foreach ($this->sharedPlaces as $sharedPlace) {
        /* @var $sharedPlace SharedPlace */

        $locReference = new LocReference($sharedPlace->xref(), $sharedPlace->tree(), new Trace(''));
        $mapCoordinates = FunctionsPlaceUtils::loc2map($this->module, $locReference);
        if ($mapCoordinates !== null) {
          return $mapCoordinates;
        }
      }
    }

    $ps = $this->placeStructure();
    if ($ps === null) {
      return null;
    }
    return FunctionsPlaceUtils::plac2map($this->module, $ps, false);
  }

  public function getLatLon(): ?MapCoordinates {
    if (!$this->latLonInitialized) {
      $this->latLon = $this->initLatLon();
      $this->latLonInitialized = true;
    }

    return $this->latLon;
  }

  public function latitude(): ?float {
    //we don't go up the hierarchy here - there may be more than one parent!

    $lati = null;
    if ($this->getLatLon() !== null) {
      $lati = $this->getLatLon()->getLati();
    }
    if ($lati === null) {
      return null;
    }

    $gedcom_service = new GedcomService();
    return $gedcom_service->readLatitude($lati);
  }

  public function longitude(): ?float {
    //we don't go up the hierarchy here - there may be more than one parent!

    $long = null;
    if ($this->getLatLon() !== null) {
      $long = $this->getLatLon()->getLong();
    }
    if ($long === null) {
      return null;
    }

    $gedcom_service = new GedcomService();
    return $gedcom_service->readLongitude($long);
  }

  public function icon(): string {
    return '';
  }

  public function boundingRectangleWithChildren(array $children): array
  {
      /*
      if (top-level) {
        //why doesn't original impl calculate bounding rectangle for world? Too expensive?
        return [[-180.0, -90.0], [180.0, 90.0]];
      }
      */

      $latitudes = [];
      $longitudes = [];

      if ($this->latitude() !== null) {
        $latitudes[] = $this->latitude();
      }
      if ($this->longitude() !== null) {
        $longitudes[] = $this->longitude();
      }

      foreach ($children as $child) {
        if ($child->latitude() !== null) {
          $latitudes[] = $child->latitude();
        }
        if ($child->longitude() !== null) {
          $longitudes[] = $child->longitude();
        }
      }

      if ((count($latitudes) === 0) || (count($longitudes) === 0)) {
        return [[-180.0, -90.0], [180.0, 90.0]];
      }

      $latiMin = (new Collection($latitudes))->min();
      $longMin = (new Collection($longitudes))->min();
      $latiMax = (new Collection($latitudes))->max();
      $longMax = (new Collection($longitudes))->max();

      //never zoom in too far (in particular if there is only one place, but also if the places are close together)
      $latiSpread = $latiMax - $latiMin;
      if ($latiSpread < 1) {
        $latiMin -= (1 - $latiSpread)/2;
        $latiMax += (1 - $latiSpread)/2;
      }

      $longSpread = $longMax - $longMin;
      if ($longSpread < 1) {
        $longMin -= (1 - $longSpread)/2;
        $longMax += (1 - $longSpread)/2;
      }

      return [[$latiMin, $longMin], [$latiMax, $longMax]];
  }

  public function placeStructure(): ?PlaceStructure {
    return PlaceStructure::fromPlace($this->actual);
  }

  public function additionalLinksHtmlBeforeName(): string {
    $html = '';
    if ($this->module !== null) {
      foreach ($this->sharedPlaces as $sharedPlace) {
        $html .= $this->module->getLinkForSharedPlace($sharedPlace);
      }
    }

    return $html;
  }

  public function links(): Collection {
    return $this->urls->links($this->actual);
  }

  public function parent(): PlaceWithinHierarchy {
    return $this->module->findPlace($this->actual->parent()->id(), $this->actual->tree(), $this->urls);
  }
}
