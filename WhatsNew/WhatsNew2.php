<?php

namespace Cissee\Webtrees\Module\SharedPlaces\WhatsNew;

use Cissee\WebtreesExt\WhatsNew\WhatsNewInterface;

class WhatsNew2 implements WhatsNewInterface {
  
  public function getMessage(): string {
    return "Vesta Shared Places: Additional data fixes which allow to move all location data to shared places.";
  }
}
