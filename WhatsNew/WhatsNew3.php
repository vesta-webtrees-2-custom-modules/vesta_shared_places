<?php

namespace Cissee\Webtrees\Module\SharedPlaces\WhatsNew;

use Cissee\WebtreesExt\WhatsNew\WhatsNewInterface;

class WhatsNew3 implements WhatsNewInterface {

  public function getMessage(): string {
    return "Vesta Shared Places: Option to set a reference date for the summary on the shared place page (useful in combination with the Gov4Webtrees module).";
  }
}
