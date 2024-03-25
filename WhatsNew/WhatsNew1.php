<?php

namespace Cissee\Webtrees\Module\SharedPlaces\WhatsNew;

use Cissee\WebtreesExt\WhatsNew\WhatsNewInterface;

class WhatsNew1 implements WhatsNewInterface {

  public function getMessage(): string {
    return "Vesta Shared Places: Additional edit controls on events for linking shared places via XREFs. It is now recommended to use this option. Furthermore, shared place names and hierarchies may now be restricted to specific dates, which allows to model historical location data.";
  }
}
