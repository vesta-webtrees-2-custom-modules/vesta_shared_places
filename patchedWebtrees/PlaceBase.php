<?php

namespace Cissee\WebtreesExt;

use Cissee\Webtrees\Module\SharedPlaces\SharedPlacesModule;
use Cissee\WebtreesExt\Http\Controllers\PlaceWithinHierarchyBase;
use Fisharebest\Webtrees\Place;

class PlaceBase implements PlaceWithinHierarchyBase {
  
  /** @var Place */
  protected $actual;
          
  /** @var SharedPlacesModule|null */
  protected $module;
  
  public function __construct(
          Place $actual,
          ?SharedPlacesModule $module) {
    
    $this->actual = $actual;
    $this->module = $module;
  }
  
  public function url(): string {
    if ($this->module !== null) {
        return $this->module->listUrl($this->actual->tree(), [
            'place_id' => $this->actual->id(),
            'tree'     => $this->actual->tree()->name(),
        ]);
    }

    // The place-list module is disabled...
    return '#';
  }
  
  public function gedcomName(): string {
    return $this->actual->gedcomName();
  }
    
  public function parent(): PlaceWithinHierarchyBase {
    return new PlaceBase($this->actual->parent(), $this->module);
  }
  
  public function placeName(): string {
    return $this->actual->placeName();
  }
}
