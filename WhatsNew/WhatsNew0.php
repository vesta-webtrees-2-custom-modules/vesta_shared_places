<?php

namespace Cissee\Webtrees\Module\SharedPlaces\WhatsNew;

use Cissee\WebtreesExt\WhatsNew\WhatsNewInterface;

class WhatsNew0 implements WhatsNewInterface {

  public function getMessage(): string {
    return "Vesta Shared Places: Option to use hierarchical shared places (enabled by default). See Readme and module configuration for details.";
  }
}
