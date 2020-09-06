<?php

namespace Cissee\WebtreesExt;

class SharedPlacePreferences {
  
  protected $useHierarchy;
  protected $useIndirectLinks;
  protected $addfacts;
  protected $uniquefacts;
  protected $requiredfacts;
  protected $quickfacts;
              
  public function useHierarchy(): bool {
    return $this->useHierarchy;
  }
  
  public function useIndirectLinks(): bool {
    return $this->useIndirectLinks;
  }
  
  public function addfacts(): array {
    return $this->addfacts;
  }
    
  public function uniquefacts(): array {
    return $this->uniquefacts;
  }
    
  public function requiredfacts(): array {
    return $this->requiredfacts;
  }
    
  public function quickfacts(): array {
    return $this->quickfacts;
  }
  
  public function __construct(
          bool $useHierarchy, 
          bool $useIndirectLinks,
          array $addfacts,
          array $uniquefacts,
          array $requiredfacts,
          array $quickfacts) {

    $this->useHierarchy = $useHierarchy;
    $this->useIndirectLinks = $useIndirectLinks;
    $this->addfacts = $addfacts;
    $this->uniquefacts = $uniquefacts;
    $this->requiredfacts = $requiredfacts;
    $this->quickfacts = $quickfacts;
  }
}
