<?php

namespace Cissee\WebtreesExt;

use Vesta\Model\GedcomDateInterval;


class SharedPlaceParentAt {
  
  /* @var $date GedcomDateInterval */
  protected $date;
  
  /* @var $sharedPlace SharedPlace|null */
  protected $sharedPlace;
  
  /* @var $indexOfFact int */
  protected $indexOfFact;
  
  public function getDate(): GedcomDateInterval {
    return $this->date;
  }
  
  public function getSharedPlace(): ?SharedPlace {
    return $this->sharedPlace;
  }
  
  public function getIndexOfFact(): int {
    return $this->indexOfFact;
  }
  
  
  public function __construct(
          GedcomDateInterval $date, 
          ?SharedPlace $sharedPlace,
          int $indexOfFact) {
   
    $this->date = $date;
    $this->sharedPlace = $sharedPlace;
    $this->indexOfFact = $indexOfFact;
  }
}
