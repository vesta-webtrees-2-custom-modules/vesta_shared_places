<?php

namespace Cissee\Webtrees\Module\SharedPlaces;

use Cissee\WebtreesExt\MoreI18N;
use Fisharebest\Webtrees\I18N;
use function view;

class HelpTexts {

  public static function helpText($help) {
    switch ($help) {
      case 'Summary':
        $title = I18N::translate('Shared place summary');
        $text = '<p>' .
                I18N::translate('The summary shows the shared place data, formatted in the same way as for events with a place mapped to the respective shared place.') . ' ' .
                I18N::translate('Therefore, the place name is displayed here including the full hierarchy.') .
                '</p>';
        break;      
      default:
        $title = MoreI18N::xlate('Help');
        $text = MoreI18N::xlate('The help text has not been written for this item.');
        break;
    }

    return view('modals/help', [
        'title' => $title,
        'text' => $text,
    ]);
  }

}
