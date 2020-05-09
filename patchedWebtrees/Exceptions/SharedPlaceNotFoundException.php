<?php

declare(strict_types=1);

namespace Cissee\WebtreesExt\Exceptions;

use Fisharebest\Webtrees\I18N;

class SharedPlaceNotFoundException extends HttpNotFoundException {
    
    public function __construct() {
        parent::__construct(I18N::translate(
            'This shared place does not exist or you do not have permission to view it.'
        ));
    }
}
